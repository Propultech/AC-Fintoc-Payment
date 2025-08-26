<?php
/**
 * Copyright © Fintoc. All rights reserved.
 */

namespace Fintoc\Payment\Controller\Webhook;

use Exception;
use Fintoc\Payment\Api\ConfigurationServiceInterface;
use Fintoc\Payment\Api\Data\TransactionInterface;
use Fintoc\Payment\Api\LoggerServiceInterface;
use Fintoc\Payment\Api\TransactionRepositoryInterface;
use Fintoc\Payment\Api\TransactionServiceInterface;
use Fintoc\Payment\Exceptions\WebhookSignatureError;
use Fintoc\Payment\Utils\WebhookSignature;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\DB\Transaction;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Service\InvoiceService;
use Psr\Log\LoggerInterface;

/**
 * Webhook controller for Fintoc payment events
 */
class Index extends Action implements CsrfAwareActionInterface
{
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var OrderFactory
     */
    protected $orderFactory;

    /**
     * @var InvoiceService
     */
    protected $invoiceService;

    /**
     * @var Transaction
     */
    protected $transaction;

    /**
     * @var InvoiceSender
     */
    protected $invoiceSender;

    /**
     * @var LoggerServiceInterface
     */
    protected $logger;

    /**
     * @var ConfigurationServiceInterface
     */
    protected $configService;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * @var TransactionServiceInterface
     */
    private $transactionService;

    /**
     * @var TransactionRepositoryInterface
     */
    private $transactionRepository;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param OrderFactory $orderFactory
     * @param InvoiceService $invoiceService
     * @param Transaction $transaction
     * @param InvoiceSender $invoiceSender
     * @param LoggerInterface $logger
     * @param ConfigurationServiceInterface $configService
     * @param EncryptorInterface $encryptor
     * @param TransactionServiceInterface $transactionService
     * @param TransactionRepositoryInterface $transactionRepository
     * @param Json $json
     * @param CartRepositoryInterface $cartRepository
     */
    public function __construct(
        Context                        $context,
        JsonFactory                    $resultJsonFactory,
        OrderFactory                   $orderFactory,
        InvoiceService                 $invoiceService,
        Transaction                    $transaction,
        InvoiceSender                  $invoiceSender,
        LoggerInterface                $logger,
        ConfigurationServiceInterface  $configService,
        EncryptorInterface             $encryptor,
        TransactionServiceInterface    $transactionService,
        TransactionRepositoryInterface $transactionRepository,
        Json                           $json,
        CartRepositoryInterface        $cartRepository
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->orderFactory = $orderFactory;
        $this->invoiceService = $invoiceService;
        $this->transaction = $transaction;
        $this->invoiceSender = $invoiceSender;
        $this->logger = $logger;
        $this->configService = $configService;
        $this->encryptor = $encryptor;
        $this->transactionService = $transactionService;
        $this->transactionRepository = $transactionRepository;
        $this->json = $json;
        $this->cartRepository = $cartRepository;
    }

    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
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
            // Get the request body using Magento request API
            $payload = $this->getRequest()->getContent();

            $data = $this->json->unserialize($payload);

            // Log the webhook
            $this->logger->debug('Received Fintoc webhook', ['payload' => $data]);

            // Verify the webhook signature if header is present
            $this->verifySignatureIfPresent($payload);

            // Determine event type and object
            list($eventType, $object) = $this->detectEvent($data);
            $this->logger->debug('Detected event type: ', [
                'event_type' => $eventType,
                'object' => $object,
            ]);
            // Route by event
            $this->routeEvent($eventType, $object);

            return $result->setData(['success' => true]);
        } catch (Exception $e) {
            $this->logger->error('Webhook processing error: ' . $e->getMessage(), ['exception' => $e]);
            return $result->setStatusHeader(500)->setData(['error' => $e->getMessage()]);
        }
    }

    /**
     * @param string $payload
     * @return void
     * @throws LocalizedException
     * @throws WebhookSignatureError
     */
    private function verifySignatureIfPresent(string $payload): void
    {
        $signature = $this->getRequest()->getHeader('Fintoc-Signature');
        if ($signature) {
            $webhookSecret = $this->getWebhookSecret();
            $this->logger->debug('Webhook processing header', [
                'signature' => $signature,
                'webhook_secret' => $webhookSecret,
                'payload' => $payload,
            ]);
            WebhookSignature::verifyHeader($payload, $signature, $webhookSecret);
        }
    }

    /**
     * @return string
     * @throws LocalizedException
     */
    private function getWebhookSecret()
    {
        $webhookSecret = $this->configService->getWebhookSecret();

        if (!$webhookSecret) {
            throw new LocalizedException(__('Fintoc Webhook key is not configured'));
        }

        return $webhookSecret;
    }

    /**
     * @param array $data
     * @return array
     */
    private function detectEvent(array $data): array
    {
        $eventType = isset($data['type']) ? strtolower((string)$data['type']) : null;
        $object = null;

        if (isset($data['data'])) {
            // Fintoc may send either data as the object itself, or data.object as the object
            if (is_array($data['data']) && isset($data['data']['object'])) {
                // data is already the object (array with an 'object' key)
                $object = $data['data'];
            } elseif (isset($data['data']['object']) && is_array($data['data']['object'])) {
                // data.object is the object
                $object = $data['data']['object'];
            }
        }

        if (!$object) {
            // Direct object payload (no event wrapper)
            $object = $data;
            if (!$eventType && isset($object['object'])) {
                if ($object['object'] === 'payment_intent' && isset($object['status'])) {
                    $eventType = 'payment_intent.' . strtolower((string)$object['status']);
                } elseif ($object['object'] === 'checkout_session' && isset($object['status'])) {
                    $eventType = 'checkout_session.' . strtolower((string)$object['status']);
                }
            }
        }

        return [$eventType, $object];
    }

    /**
     * @param string|null $eventType
     * @param array $object
     * @return void
     * @throws LocalizedException
     */
    private function routeEvent(?string $eventType, array $object): void
    {
        switch ($eventType) {
            case 'payment_intent.succeeded':
                $this->processPaymentIntentSucceeded($object);
                break;
            case 'payment_intent.failed':
            case 'payment_intent.rejected':
            case 'payment_intent.expired':
                $this->processPaymentIntentFailed($object);
                break;
            case 'payment_intent.pending':
                $this->processPaymentIntentPending($object);
                break;
            case 'checkout_session.finished':
                $this->processCheckoutSessionFinished($object);
                break;
            case 'checkout_session.expired':
                $this->processCheckoutSessionExpired($object);
                break;
            default:
                // Fallback for direct payment_intent object without clear eventType
                if (($object['object'] ?? '') === 'payment_intent') {
                    $status = strtolower((string)($object['status'] ?? ''));
                    if ($status === 'succeeded') {
                        $this->processPaymentIntentSucceeded($object);
                    } elseif (in_array($status, ['failed', 'rejected', 'expired', 'canceled'])) {
                        $this->processPaymentIntentFailed($object);
                    } elseif ($status === 'pending') {
                        $this->processPaymentIntentPending($object);
                    } else {
                        $this->logger->debug('Unhandled payment intent status: ' . ($object['status'] ?? 'unknown'));
                    }
                } elseif (($object['object'] ?? '') === 'checkout_session') {
                    $status = strtolower((string)($object['status'] ?? ''));
                    if ($status === 'finished') {
                        $this->processCheckoutSessionFinished($object);
                    } elseif ($status === 'expired') {
                        $this->processCheckoutSessionExpired($object);
                    } else {
                        $this->logger->debug('Unhandled checkout session status: ' . ($object['status'] ?? 'unknown'));
                    }
                } else {
                    $this->logger->debug('Unhandled webhook payload');
                }
        }
    }

    /**
     * Process a successful payment intent
     *
     * @param array $paymentIntent
     * @return void
     * @throws LocalizedException
     */
    protected function processPaymentIntentSucceeded(array $paymentIntent)
    {
        // Normalize keys to handle snake_case payloads
        $paymentIntent = $this->normalizePaymentIntent($paymentIntent);

        // Get order ID from metadata (supports multiple keys)
        $orderId = $this->extractOrderIncrementId($paymentIntent);

        if (!$orderId) {
            throw new LocalizedException(__('No order ID found in payment intent metadata:  ' . $this->json->serialize($paymentIntent['metadata'] ?? '')));
        }

        $order = $this->orderFactory->create()->loadByIncrementId($orderId);

        if (!$order->getId()) {
            throw new LocalizedException(__('Order not found: %1', $orderId));
        }

        if ($order->getState() === Order::STATE_PROCESSING) {
            $this->logger->debug('Order already processed: ' . $orderId);
            return;
        }

        // Update transaction in database
        try {
            // Try to find existing transaction by order ID
            $searchResults = $this->transactionRepository->getByOrderIncrementId($orderId);
            $transactions = $searchResults->getItems();

            if (count($transactions) > 0) {
                // Update existing transaction
                $transaction = reset($transactions);
                $this->transactionService->updateTransactionStatus(
                    $transaction,
                    TransactionInterface::STATUS_SUCCESS,
                    [
                        'updated_by' => 'webhook',
                        'error_message' => null
                    ]
                );
            } else {
                // Create new webhook transaction
                $this->transactionService->createWebhookTransaction(
                    $paymentIntent['id'],
                    $order,
                    $paymentIntent['amount'] / 100, // Convert from cents to base currency
                    $paymentIntent['currency'],
                    $paymentIntent,
                    TransactionInterface::STATUS_SUCCESS,
                    [
                        'created_by' => 'webhook',
                        'reference' => $paymentIntent['referenceId'] ?? null
                    ]
                );
            }
        } catch (Exception $e) {
            $this->logger->error('Error updating transaction: ' . $e->getMessage(), ['exception' => $e]);
        }

        // Update payment information
        $payment = $order->getPayment();
        $payment->setAdditionalInformation('fintoc_payment_id', $paymentIntent['id']);
        $payment->setAdditionalInformation('fintoc_payment_status', $paymentIntent['status']);
        $payment->setAdditionalInformation('fintoc_payment_amount', $paymentIntent['amount']);
        $payment->setAdditionalInformation('fintoc_payment_currency', $paymentIntent['currency']);
        $payment->setAdditionalInformation('fintoc_payment_type', $paymentIntent['paymentType'] ?? null);
        $payment->setAdditionalInformation('fintoc_reference_id', $paymentIntent['referenceId'] ?? null);
        $payment->setAdditionalInformation('fintoc_transaction_date', $paymentIntent['transactionDate'] ?? null);

        if (isset($paymentIntent['senderAccount'])) {
            $payment->setAdditionalInformation('fintoc_sender_account', $this->json->serialize($paymentIntent['senderAccount']));
        }

        $payment->setLastTransId($paymentIntent['id']);
        $payment->save();

        // Update order status
        $order/*->setState(Order::STATE_PROCESSING)
            ->setStatus(Order::STATE_PROCESSING)*/
        ->addCommentToStatusHistory(
            __(
                'Payment successfully processed by Fintoc. Payment ID: %1, Amount: %2 %3, Reference: %4',
                $paymentIntent['id'],
                $paymentIntent['amount'] / 100, // Convert from cents to base currency
                $paymentIntent['currency'],
                $paymentIntent['referenceId'] ?? 'N/A'
            )
        );

        // Create invoice
        if ($order->canInvoice()) {
            $invoice = $this->invoiceService->prepareInvoice($order);
            $invoice->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);
            $invoice->register();
            $invoice->save();

            $invoice->pay();
            $invoice->save();

            $transactionSave = $this->transaction->addObject($invoice)->addObject($invoice->getOrder());
            $transactionSave->save();

            // Send invoice email
            $this->invoiceSender->send($invoice);

            $order->addCommentToStatusHistory(
                __('Invoice #%1 created', $invoice->getIncrementId())
            );
        }

        $order->save();

        $this->logger->info(
            'Payment succeeded and order processed',
            [
                'payment_id' => $paymentIntent['id'],
                'order_id' => $orderId,
                'amount' => $paymentIntent['amount'],
                'currency' => $paymentIntent['currency']
            ]
        );
    }

    /**
     * Normalize payment intent keys to camelCase while accepting snake_case from webhook.
     *
     * @param array $pi
     * @return array
     */
    private function normalizePaymentIntent(array $pi): array
    {
        // Only set camelCase if missing, keeping existing values intact
        if (!isset($pi['paymentType']) && isset($pi['payment_type'])) {
            $pi['paymentType'] = $pi['payment_type'];
        }
        if (!isset($pi['referenceId']) && isset($pi['reference_id'])) {
            $pi['referenceId'] = $pi['reference_id'];
        }
        if (!isset($pi['transactionDate']) && isset($pi['transaction_date'])) {
            $pi['transactionDate'] = $pi['transaction_date'];
        }
        if (!isset($pi['senderAccount']) && isset($pi['sender_account'])) {
            $pi['senderAccount'] = $pi['sender_account'];
        }
        if (!isset($pi['errorReason']) && isset($pi['error_reason'])) {
            $pi['errorReason'] = $pi['error_reason'];
        }
        return $pi;
    }

    /**
     * Extract Magento order increment ID from a webhook object metadata.
     *
     * @param array $object
     * @return string|null
     */
    private function extractOrderIncrementId(array $object): ?string
    {
        $this->logger->debug('extractOrderIncrementId from webhook object metadata', $object);
        $metadata = $object['metadata'] ?? null;
        if (is_array($metadata)) {
            $keys = ['ecommerceOrderId', 'ecommerce_order_id', 'order', 'order_id', 'order_increment_id'];
            foreach ($keys as $k) {
                if (!empty($metadata[$k]) && is_string($metadata[$k])) {
                    return $metadata[$k];
                }
            }
        }
        return null;
    }

    /**
     * Process a failed payment intent
     *
     * @param array $paymentIntent
     * @return void
     * @throws LocalizedException
     */
    protected function processPaymentIntentFailed(array $paymentIntent)
    {
        // Normalize keys to handle snake_case payloads
        $paymentIntent = $this->normalizePaymentIntent($paymentIntent);

        // Get order ID from metadata (supports multiple keys)
        $orderId = $this->extractOrderIncrementId($paymentIntent);

        if (!$orderId) {
            throw new LocalizedException(__('No order ID found in payment intent metadata:  ' . $this->json->serialize($paymentIntent['metadata'] ?? '')));
        }

        $order = $this->orderFactory->create()->loadByIncrementId($orderId);

        if (!$order->getId()) {
            throw new LocalizedException(__('Order not found: %1', $orderId));
        }

        if ($order->getState() === Order::STATE_CANCELED) {
            $this->logger->debug('Order already canceled: ' . $orderId);
            // Ensure quote is restored so the customer can retry
            $this->restoreQuoteForOrder($order);
            return;
        }

        // Update transaction in database
        try {
            // Try to find existing transaction by order ID
            $searchResults = $this->transactionRepository->getByOrderIncrementId($orderId);
            $transactions = $searchResults->getItems();

            if (count($transactions) > 0) {
                // Update existing transaction
                $transaction = reset($transactions);
                $this->transactionService->updateTransactionStatus(
                    $transaction,
                    TransactionInterface::STATUS_FAILED,
                    [
                        'updated_by' => 'webhook',
                        'error_message' => $paymentIntent['errorReason'] ?? 'Payment failed or canceled'
                    ]
                );
            } else {
                // Create new webhook transaction
                $this->transactionService->createWebhookTransaction(
                    $paymentIntent['id'],
                    $order,
                    $paymentIntent['amount'] / 100, // Convert from cents to base currency
                    $paymentIntent['currency'],
                    $paymentIntent,
                    TransactionInterface::STATUS_FAILED,
                    [
                        'created_by' => 'webhook',
                        'reference' => $paymentIntent['referenceId'] ?? null,
                        'error_message' => $paymentIntent['errorReason'] ?? 'Payment failed or canceled'
                    ]
                );
            }
        } catch (Exception $e) {
            $this->logger->error('Error updating transaction: ' . $e->getMessage(), ['exception' => $e]);
        }

        // Update payment information
        $payment = $order->getPayment();
        $payment->setAdditionalInformation('fintoc_payment_id', $paymentIntent['id']);
        $payment->setAdditionalInformation('fintoc_payment_status', $paymentIntent['status']);
        $payment->setAdditionalInformation('fintoc_payment_amount', $paymentIntent['amount']);
        $payment->setAdditionalInformation('fintoc_payment_currency', $paymentIntent['currency']);
        $payment->setAdditionalInformation('fintoc_payment_type', $paymentIntent['paymentType'] ?? null);
        $payment->setAdditionalInformation('fintoc_reference_id', $paymentIntent['referenceId'] ?? null);
        $payment->setAdditionalInformation('fintoc_error_reason', $paymentIntent['errorReason'] ?? null);
        $payment->setAdditionalInformation('fintoc_failed_at', date('Y-m-d H:i:s'));

        $payment->setLastTransId($paymentIntent['id']);
        $payment->save();

        // Cancel the order
        $reason = $paymentIntent['errorReason'] ?? ($paymentIntent['last_payment_error']['message'] ?? null);
        $errorSuffix = $reason ? ': ' . $reason : '';
        $order->cancel();
        $order->addCommentToStatusHistory(
            __(
                'Payment failed at Fintoc. Payment ID: %1, Amount: %2 %3, Status: %4%5',
                $paymentIntent['id'],
                $paymentIntent['amount'] / 100, // Convert from cents to base currency
                $paymentIntent['currency'],
                $paymentIntent['status'],
                $errorSuffix
            )
        );

        $order->save();

        // Restore quote so the customer can retry checkout
        $this->restoreQuoteForOrder($order);

        $this->logger->info(
            'Payment failed and order canceled',
            [
                'payment_id' => $paymentIntent['id'],
                'order_id' => $orderId,
                'status' => $paymentIntent['status'],
                'error_reason' => $paymentIntent['errorReason'] ?? null
            ]
        );
    }

    /**
     * Retrieves the webhook secret key for the Fintoc payment gateway.
     *
     * @return string The webhook secret key.
     * @throws LocalizedException If the webhook secret key is not configured.
     */
    private function restoreQuoteForOrder(Order $order): void
    {
        try {
            $quoteId = (int)$order->getQuoteId();
            if ($quoteId) {
                $quote = $this->cartRepository->get($quoteId);
                if ($quote && $quote->getId()) {
                    $quote->setIsActive(true);
                    $quote->setReservedOrderId(null);
                    $this->cartRepository->save($quote);
                    $this->logger->info('Quote restored for order', [
                        'order' => $order->getIncrementId(),
                        'quote_id' => $quoteId
                    ]);
                }
            }
        } catch (Exception $e) {
            $this->logger->debug('Restore quote failed in webhook: ' . $e->getMessage());
        }
    }

    /**
     * Process a pending payment intent (keep order pending)
     *
     * @param array $paymentIntent
     * @return void
     * @throws LocalizedException
     */
    protected function processPaymentIntentPending(array $paymentIntent): void
    {
        // Normalize keys to handle snake_case payloads
        $paymentIntent = $this->normalizePaymentIntent($paymentIntent);

        $orderId = $this->extractOrderIncrementId($paymentIntent);
        if (!$orderId) {
            throw new LocalizedException(__('No order ID found in payment intent metadata:  ' . $this->json->serialize($paymentIntent['metadata'] ?? '')));
        }

        $order = $this->orderFactory->create()->loadByIncrementId($orderId);
        if (!$order->getId()) {
            throw new LocalizedException(__('Order not found: %1', $orderId));
        }

        // Update or create transaction as pending
        try {
            $searchResults = $this->transactionRepository->getByOrderIncrementId($orderId);
            $transactions = $searchResults->getItems();
            if (count($transactions) > 0) {
                $transaction = reset($transactions);
                $this->transactionService->updateTransactionStatus(
                    $transaction,
                    TransactionInterface::STATUS_PENDING,
                    ['updated_by' => 'webhook']
                );
            } else {
                $this->transactionService->createWebhookTransaction(
                    $paymentIntent['id'] ?? ('pi_' . uniqid()),
                    $order,
                    isset($paymentIntent['amount']) ? ((float)$paymentIntent['amount']) / 100 : null,
                    $paymentIntent['currency'] ?? $order->getOrderCurrencyCode(),
                    $paymentIntent,
                    TransactionInterface::STATUS_PENDING,
                    ['created_by' => 'webhook']
                );
            }
        } catch (Exception $e) {
            $this->logger->error('Error updating pending transaction: ' . $e->getMessage(), ['exception' => $e]);
        }

        // Update payment additional information
        $payment = $order->getPayment();
        if ($payment) {
            if (!empty($paymentIntent['id'])) {
                $payment->setAdditionalInformation('fintoc_payment_id', $paymentIntent['id']);
            }
            if (isset($paymentIntent['status'])) {
                $payment->setAdditionalInformation('fintoc_payment_status', $paymentIntent['status']);
            }
            if (isset($paymentIntent['amount'])) {
                $payment->setAdditionalInformation('fintoc_payment_amount', $paymentIntent['amount']);
            }
            if (isset($paymentIntent['currency'])) {
                $payment->setAdditionalInformation('fintoc_payment_currency', $paymentIntent['currency']);
            }
            $payment->setAdditionalInformation('fintoc_pending_at', date('Y-m-d H:i:s'));
            if (!empty($paymentIntent['referenceId'])) {
                $payment->setAdditionalInformation('fintoc_reference_id', $paymentIntent['referenceId']);
            }
            $payment->save();
        }

        // Add order history comment (trace)
        $order->addCommentToStatusHistory(
            __(
                'Payment pending at Fintoc. Payment ID: %1%2',
                $paymentIntent['id'] ?? 'N/A',
                isset($paymentIntent['amount'], $paymentIntent['currency'])
                    ? __(', Amount: %1 %2', ((float)$paymentIntent['amount']) / 100, $paymentIntent['currency'])
                    : ''
            )
        );
        $order->save();

        $this->logger->info('Payment intent pending recorded', ['order' => $orderId]);
    }

    /**
     * Process checkout_session.finished (trace only)
     *
     * @param array $session
     * @return void
     * @throws LocalizedException
     */
    protected function processCheckoutSessionFinished(array $session): void
    {
        $orderId = $this->extractOrderIncrementId($session);
        if (!$orderId) {
            throw new LocalizedException(__('No order ID found in checkout session metadata'));
        }

        $order = $this->orderFactory->create()->loadByIncrementId($orderId);
        if (!$order->getId()) {
            throw new LocalizedException(__('Order not found: %1', $orderId));
        }

        $order->addCommentToStatusHistory(
            __(
                'Fintoc checkout session finished. Session ID: %1, Status: %2',
                $session['id'] ?? 'N/A',
                $session['status'] ?? 'finished'
            )
        );
        $order->save();

        $this->logger->debug('Checkout session finished traced', ['order' => $orderId]);
    }

    /**
     * Process checkout_session.expired (cancel the order)
     *
     * @param array $session
     * @return void
     * @throws LocalizedException
     */
    protected function processCheckoutSessionExpired(array $session): void
    {
        $orderId = $this->extractOrderIncrementId($session);
        if (!$orderId) {
            throw new LocalizedException(__('No order ID found in checkout session metadata'));
        }

        $order = $this->orderFactory->create()->loadByIncrementId($orderId);
        if (!$order->getId()) {
            throw new LocalizedException(__('Order not found: %1', $orderId));
        }

        // Update or create transaction as canceled
        try {
            $searchResults = $this->transactionRepository->getByOrderIncrementId($orderId);
            $transactions = $searchResults->getItems();
            if (count($transactions) > 0) {
                $transaction = reset($transactions);
                $this->transactionService->updateTransactionStatus(
                    $transaction,
                    TransactionInterface::STATUS_CANCELED,
                    [
                        'updated_by' => 'webhook',
                        'error_message' => 'Checkout session expired'
                    ]
                );
            } else {
                $this->transactionService->createWebhookTransaction(
                    $session['id'] ?? ('cs_' . uniqid()),
                    $order,
                    isset($session['amount']) ? ((float)$session['amount']) / 100 : null,
                    $session['currency'] ?? $order->getOrderCurrencyCode(),
                    $session,
                    TransactionInterface::STATUS_CANCELED,
                    [
                        'created_by' => 'webhook',
                        'reference' => $session['referenceId'] ?? ($session['reference_id'] ?? null),
                        'error_message' => 'Checkout session expired'
                    ]
                );
            }
        } catch (Exception $e) {
            $this->logger->error('Error updating transaction for session expired: ' . $e->getMessage(), ['exception' => $e]);
        }

        // Update payment info
        $payment = $order->getPayment();
        if ($payment) {
            if (!empty($session['id'])) {
                $payment->setAdditionalInformation('fintoc_checkout_session_id', $session['id']);
            }
            $payment->setAdditionalInformation('fintoc_checkout_session_status', 'expired');
            $payment->setAdditionalInformation('fintoc_checkout_session_expired_at', date('Y-m-d H:i:s'));
            $payment->save();
        }

        // Cancel order and add comment
        if ($order->getState() !== Order::STATE_CANCELED) {
            $order->cancel();
        }
        $order->addCommentToStatusHistory(
            __('Fintoc checkout session expired. Session ID: %1', $session['id'] ?? 'N/A')
        );
        $order->save();

        // Restore quote so the customer can retry checkout
        $this->restoreQuoteForOrder($order);

        $this->logger->info('Checkout session expired and order canceled', ['order' => $orderId]);
    }
}
