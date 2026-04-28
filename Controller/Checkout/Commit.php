<?php
/**
 * Copyright © Fintoc. All rights reserved.
 */

namespace Fintoc\Payment\Controller\Checkout;

use Exception;
use Fintoc\Payment\Api\Data\TransactionInterface;
use Fintoc\Payment\Api\TransactionRepositoryInterface;
use Fintoc\Payment\Api\TransactionServiceInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Fintoc\Payment\Api\LoggerServiceInterface as LoggerInterface;

/**
 * Controller for handling Fintoc payment callbacks (success/cancel)
 */
class Commit extends Action
{
    /**
     * @var RedirectFactory
     */
    protected $resultRedirectFactory;

    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * @var OrderFactory
     */
    protected $orderFactory;

    /**
     * @var EncryptorInterface
     */
    protected $encryptor;

    /**
     * @var TransactionServiceInterface
     */
    protected $transactionService;

    /**
     * @var TransactionRepositoryInterface
     */
    protected $transactionRepository;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @param Context $context
     * @param RedirectFactory $resultRedirectFactory
     * @param ManagerInterface $messageManager
     * @param OrderFactory $orderFactory
     * @param EncryptorInterface $encryptor
     * @param TransactionServiceInterface $transactionService
     * @param TransactionRepositoryInterface $transactionRepository
     * @param LoggerInterface $logger
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(
        Context                        $context,
        RedirectFactory                $resultRedirectFactory,
        ManagerInterface               $messageManager,
        OrderFactory                   $orderFactory,
        EncryptorInterface             $encryptor,
        TransactionServiceInterface    $transactionService,
        TransactionRepositoryInterface $transactionRepository,
        LoggerInterface                $logger,
        CheckoutSession                $checkoutSession
    )
    {
        parent::__construct($context);
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->messageManager = $messageManager;
        $this->orderFactory = $orderFactory;
        $this->encryptor = $encryptor;
        $this->transactionService = $transactionService;
        $this->transactionRepository = $transactionRepository;
        $this->logger = $logger;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Execute action based on a request and return result
     *
     * @return ResultInterface
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        try {
            // Get action parameter (success or cancel)
            $action = $this->getRequest()->getParam('action');

            // Get encrypted transaction ID
            $encryptedTransactionId = $this->getRequest()->getParam('tr');

            if (!$encryptedTransactionId) {
                throw new LocalizedException(__('Missing transaction ID parameter'));
            }

            // Decrypt the transaction ID
            try {
                $transactionId = $encryptedTransactionId;
            } catch (Exception $e) {
                $this->logger->error('Commit: Error decrypting transaction ID: ' . $e->getMessage(), ['exception' => $e]);
                throw new LocalizedException(__('Invalid transaction ID'));
            }

            // Get the transaction from the repository
            try {
                $transaction = $this->transactionRepository->getByTransactionId($transactionId);
            } catch (Exception $e) {
                $this->logger->error('Commit: Error retrieving transaction: ' . $e->getMessage(), ['exception' => $e]);
                throw new LocalizedException(__('Transaction not found'));
            }

            // Get the order
            $orderId = $transaction->getOrderId();
            $order = $this->orderFactory->create()->load($orderId);

            if (!$order->getId()) {
                throw new LocalizedException(__('Order not found'));
            }

            // Process based on action
            if ($action === 'success') {
                return $this->processSuccessAction($transaction, $order);
            } elseif ($action === 'cancel') {
                return $this->processCancelAction($transaction, $order);
            } else {
                throw new LocalizedException(__('Invalid action parameter'));
            }

        } catch (Exception $e) {
            $this->logger->error('Commit: Error processing Fintoc callback: ' . $e->getMessage(), ['exception' => $e]);
            $this->messageManager->addErrorMessage($e->getMessage());
            try {
                $this->checkoutSession->restoreQuote();
            } catch (Exception $e2) {
                $this->logger->debug('Commit: Restore quote failed: ' . $e2->getMessage());
            }
            return $resultRedirect->setPath('checkout/cart');
        }
    }

    /**
     * Process success action
     *
     * @param TransactionInterface $transaction
     * @param Order $order
     * @return ResultInterface
     * @throws LocalizedException
     */
    private function processSuccessAction(TransactionInterface $transaction, Order $order)
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        // Update transaction status
        $this->transactionService->updateTransactionStatus(
            $transaction,
            TransactionInterface::STATUS_SUCCESS,
            [
                'updated_by' => 'commit_controller',
                'error_message' => null
            ]
        );

        // Add comment to order history
        $order->addCommentToStatusHistory(
            __(
                'Fintoc payment successful. Transaction ID: %1, Amount: %2 %3',
                $transaction->getTransactionId(),
                $transaction->getAmount(),
                $transaction->getCurrency()
            )
        );

        // Add additional payment information
        $payment = $order->getPayment();
        $payment->setAdditionalInformation('fintoc_transaction_status', TransactionInterface::STATUS_SUCCESS);
        $payment->setAdditionalInformation('fintoc_transaction_completed_at', date('Y-m-d H:i:s'));

        // Store transaction response data if available
        $responseData = $transaction->getResponseData();
        if ($responseData) {
            try {
                $responseArray = json_decode($responseData, true);
                if (is_array($responseArray)) {
                    foreach ($responseArray as $key => $value) {
                        if (is_scalar($value)) {
                            $payment->setAdditionalInformation('fintoc_response_' . $key, $value);
                        }
                    }
                }
            } catch (Exception $e) {
                $this->logger->error('Commit: Error processing response data: ' . $e->getMessage());
            }
        }

        $payment->save();
        $order->save();

        // Log the success
        $this->logger->info(
            'Commit: Fintoc payment successful',
            [
                'transaction_id' => $transaction->getTransactionId(),
                'order_id' => $order->getIncrementId()
            ]
        );

        // Add a success message
        $this->messageManager->addSuccessMessage(__('Your payment was successful.'));

        // Redirect to success page
        return $resultRedirect->setPath('checkout/onepage/success');
    }

    /**
     * Process cancel action
     *
     * @param TransactionInterface $transaction
     * @param Order $order
     * @return ResultInterface
     * @throws LocalizedException
     */
    private function processCancelAction(TransactionInterface $transaction, Order $order)
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        // Update Fintoc-side transaction status only.
        // The order itself stays in its current state and is finalized by the webhook
        // (checkout_session.expired / payment_intent.failed / payment_intent.succeeded)
        // to avoid race conditions where the bank confirms the payment after the user
        // returned via cancel_url.
        $this->transactionService->updateTransactionStatus(
            $transaction,
            TransactionInterface::STATUS_CANCELED,
            [
                'updated_by' => 'commit_controller',
                'error_message' => 'Customer clicked cancel - awaiting webhook confirmation'
            ]
        );

        $order->addCommentToStatusHistory(
            __(
                'Customer clicked cancel on Fintoc redirect. Order will be finalized by webhook. Transaction ID: %1',
                $transaction->getTransactionId()
            )
        );

        $payment = $order->getPayment();
        $payment->setAdditionalInformation('fintoc_user_canceled_at', date('Y-m-d H:i:s'));
        $payment->save();
        $order->save();

        $this->logger->info(
            'Commit: Customer clicked cancel, awaiting webhook',
            [
                'transaction_id' => $transaction->getTransactionId(),
                'order_id' => $order->getIncrementId(),
            ]
        );

        $this->messageManager->addNoticeMessage(
            __('Your payment was canceled. We will confirm with the bank shortly.')
        );

        // Restore quote to allow customer to retry checkout
        try {
            $this->checkoutSession->restoreQuote();
        } catch (Exception $e) {
            $this->logger->debug('Commit: Restore quote failed on cancel: ' . $e->getMessage());
        }

        return $resultRedirect->setPath('checkout/cart');
    }
}
