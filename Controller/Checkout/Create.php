<?php
/**
 * Copyright © Fintoc. All rights reserved.
 */

namespace Fintoc\Payment\Controller\Checkout;

use Exception;
use Fintoc\Payment\Api\ConfigurationServiceInterface;
use Fintoc\Payment\Api\Data\TransactionInterface;
use Fintoc\Payment\Api\LoggerServiceInterface;
use Fintoc\Payment\Api\TransactionServiceInterface;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Fintoc\Payment\Utils\AmountUtils;
use Fintoc\Payment\Service\ConfigurationService;

/**
 * Controller for creating Fintoc checkout sessions
 */
class Create extends Action
{
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var ConfigurationServiceInterface
     */
    protected $configService;

    /**
     * @var GuzzleClient
     */
    protected $httpClient;

    /**
     * @var LoggerServiceInterface
     */
    protected $logger;

    /**
     * @var EncryptorInterface
     */
    protected $encryptor;

    /**
     * @var TransactionServiceInterface
     */
    protected $transactionService;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param CheckoutSession $checkoutSession
     * @param StoreManagerInterface $storeManager
     * @param ConfigurationServiceInterface $configService
     * @param GuzzleClient $httpClient
     * @param LoggerInterface $logger
     * @param EncryptorInterface $encryptor
     * @param TransactionServiceInterface $transactionService
     */
    public function __construct(
        Context                       $context,
        JsonFactory                   $resultJsonFactory,
        CheckoutSession               $checkoutSession,
        StoreManagerInterface         $storeManager,
        ConfigurationServiceInterface $configService,
        GuzzleClient                  $httpClient,
        LoggerInterface               $logger,
        EncryptorInterface            $encryptor,
        TransactionServiceInterface   $transactionService
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->checkoutSession = $checkoutSession;
        $this->storeManager = $storeManager;
        $this->configService = $configService;
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->encryptor = $encryptor;
        $this->transactionService = $transactionService;
    }

    /**
     * Execute action based on request and return result
     *
     * @return ResultInterface
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        try {
            $order = $this->checkoutSession->getLastRealOrder();

            if (!$order->getId()) {
                throw new LocalizedException(__('No order found'));
            }

            $apiSecret = $this->getApiSecret();
            $baseUrl = $this->storeManager->getStore()->getBaseUrl();
            $apiBaseUrl = rtrim((string)$this->configService->getConfig('payment/fintoc_payment/api_base_url') ?: ConfigurationService::DEFAULT_API_BASE_URL, '/');
            $checkoutEndpoint = $apiBaseUrl . '/v1/checkout_sessions';

            // Generate a unique transaction ID
            $transactionId = uniqid('fintoc_', true);

            // Add comment to order history
            $order->addCommentToStatusHistory(
                __(
                    'Fintoc payment initiated. Transaction ID: %1, Amount: %2 %3',
                    $transactionId,
                    $order->getGrandTotal(),
                    $order->getOrderCurrencyCode()
                )
            );
            $order->save();

            // Create a transaction record in the database
            $transaction = $this->transactionService->createPreAuthorizationTransaction(
                $transactionId,
                $order,
                $order->getGrandTotal(),
                $order->getOrderCurrencyCode(),
                [],
                [
                    'created_by' => 'checkout',
                    'ip_address' => $this->getRequest()->getClientIp(),
                    'user_agent' => $this->getRequest()->getHeader('User-Agent')
                ]
            );

            // Encrypt the transaction ID for URLs
            $encryptedTransactionId = $transactionId;

            // Prepare request data with updated URLs
            $requestData = [
                'amount' => AmountUtils::roundToIntHalfUp((float)$order->getGrandTotal()),
                'currency' => $order->getOrderCurrencyCode(),
                'cancel_url' => $baseUrl . 'fintoc/checkout/commit/action/cancel/tr/' . urlencode($encryptedTransactionId),
                'success_url' => $baseUrl . 'fintoc/checkout/commit/action/success/tr/' . urlencode($encryptedTransactionId),
                'customer_email' => $order->getCustomerEmail(),
                'metadata' => [
                    'ecommerce_order_id' => $order->getIncrementId()
                ]
            ];

            // Log the request
            $this->logger->debug('Creating Fintoc checkout session', ['request' => $requestData]);

            // Set up the API call options
            $options = [
                'headers' => [
                    'Authorization' => 'Bearer '.$apiSecret,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ],
                'json' => $requestData
            ];

            try {
                // Make the API call
                $response = $this->httpClient->request(
                    'POST',
                    $checkoutEndpoint,
                    $options
                );

                // Get the response
                $response = json_decode($response->getBody()->getContents(), true);

                // Update transaction with response data
                $this->transactionService->createPostAuthorizationTransaction(
                    $transactionId,
                    $order,
                    $order->getGrandTotal(),
                    $order->getOrderCurrencyCode(),
                    $response,
                    isset($response['redirect_url'])
                        ? TransactionInterface::STATUS_PENDING
                        : TransactionInterface::STATUS_FAILED
                );

                // Add additional payment information
                $payment = $order->getPayment();
                $payment->setAdditionalInformation('fintoc_transaction_id', $transactionId);
                $payment->setAdditionalInformation('fintoc_amount', $order->getGrandTotal());
                $payment->setAdditionalInformation('fintoc_currency', $order->getOrderCurrencyCode());
                if (isset($response['id'])) {
                    $payment->setAdditionalInformation('fintoc_checkout_id', $response['id']);
                }
                if (isset($response['redirect_url'])) {
                    $payment->setAdditionalInformation('fintoc_redirect_url', $response['redirect_url']);
                }
                $payment->save();

            } catch (GuzzleException $e) {
                // Update transaction with error
                $this->transactionService->updateTransactionStatus(
                    $transaction,
                    TransactionInterface::STATUS_FAILED,
                    [
                        'error_message' => $e->getMessage(),
                        'updated_by' => 'checkout'
                    ]
                );

                // Add comment to order history
                $order->addCommentToStatusHistory(
                    __(
                        'Fintoc payment failed. Transaction ID: %1, Error: %2',
                        $transactionId,
                        $e->getMessage()
                    )
                );

                // Add additional payment information
                $payment = $order->getPayment();
                $payment->setAdditionalInformation('fintoc_transaction_id', $transactionId);
                $payment->setAdditionalInformation('fintoc_transaction_status', TransactionInterface::STATUS_FAILED);
                $payment->setAdditionalInformation('fintoc_error_message', $e->getMessage());
                $payment->setAdditionalInformation('fintoc_failed_at', date('Y-m-d H:i:s'));
                $payment->save();

                $order->save();

                // Restore quote so the customer can try again
                try {
                    $this->checkoutSession->restoreQuote();
                } catch (Exception $ex) {
                    $this->logger->debug('Restore quote failed after API error: ' . $ex->getMessage());
                }

                throw new LocalizedException(__('Error communicating with Fintoc API: %1', $e->getMessage()));
            }

            // Log the response
            $this->logger->debug('Fintoc checkout session response', ['response' => $response]);

            if (!isset($response['redirect_url'])) {
                // Add comment to order history
                $order->addCommentToStatusHistory(
                    __(
                        'Fintoc payment failed. Transaction ID: %1, Error: Invalid response from Fintoc API',
                        $transactionId
                    )
                );

                // Add additional payment information
                $payment = $order->getPayment();
                $payment->setAdditionalInformation('fintoc_transaction_id', $transactionId);
                $payment->setAdditionalInformation('fintoc_transaction_status', TransactionInterface::STATUS_FAILED);
                $payment->setAdditionalInformation('fintoc_error_message', 'Invalid response from Fintoc API');
                $payment->setAdditionalInformation('fintoc_failed_at', date('Y-m-d H:i:s'));
                if (is_array($response)) {
                    $payment->setAdditionalInformation('fintoc_response_data', json_encode($response));
                }
                $payment->save();

                $order->save();

                // Restore quote so the customer can try again
                try {
                    $this->checkoutSession->restoreQuote();
                } catch (Exception $ex) {
                    $this->logger->debug('Restore quote failed after invalid response: ' . $ex->getMessage());
                }

                throw new LocalizedException(__('Invalid response from Fintoc API'));
            }

            return $result->setData([
                'success' => true,
                'redirect_url' => $response['redirect_url']
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error creating Fintoc checkout session: ' . $e->getMessage(), ['exception' => $e]);
            // Restore quote on any failure in create controller
            try {
                $this->checkoutSession->restoreQuote();
            } catch (Exception $ex) {
                $this->logger->debug('Restore quote failed in create outer catch: ' . $ex->getMessage());
            }
            return $result->setData([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get the Fintoc API key from configuration
     *
     * @return string
     * @throws LocalizedException
     */
    private function getApiSecret()
    {
        $apiSecret = $this->configService->getApiSecret();

        if (!$apiSecret) {
            throw new LocalizedException(__('Fintoc API key is not configured'));
        }

        return $apiSecret;
    }
}
