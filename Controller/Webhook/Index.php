<?php
/**
 * Copyright © Fintoc. All rights reserved.
 */

namespace Fintoc\Payment\Controller\Webhook;

use Fintoc\Fintoc;
use Fintoc\Utils\WebhookSignature;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Fintoc\Payment\Api\LoggerServiceInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Framework\DB\Transaction;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

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
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param OrderFactory $orderFactory
     * @param InvoiceService $invoiceService
     * @param Transaction $transaction
     * @param InvoiceSender $invoiceSender
     * @param LoggerServiceInterface $logger
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        OrderFactory $orderFactory,
        InvoiceService $invoiceService,
        Transaction $transaction,
        InvoiceSender $invoiceSender,
        LoggerServiceInterface $logger,
        ScopeConfigInterface $scopeConfig
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->orderFactory = $orderFactory;
        $this->invoiceService = $invoiceService;
        $this->transaction = $transaction;
        $this->invoiceSender = $invoiceSender;
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
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
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        try {
            // Get the request body
            $payload = file_get_contents('php://input');
            $data = json_decode($payload, true);

            if (!$data) {
                throw new LocalizedException(__('Invalid JSON payload'));
            }

            // Log the webhook
            $this->logger->logWebhook($data['type'] ?? 'unknown', $data);

            // Verify the webhook signature
            $webhookSecret = $this->getWebhookSecret();
            $signature = $this->getRequest()->getHeader('Fintoc-Signature');

            try {
                WebhookSignature::verifyHeader($payload, $signature, $webhookSecret);
            } catch (\Exception $e) {
                $this->logger->error('Webhook signature verification failed: ' . $e->getMessage(), ['exception' => $e]);
                throw new LocalizedException(__('Invalid signature'));
            }

            // Process the webhook event
            $this->processWebhookEvent($data);

            return $result->setData(['success' => true]);
        } catch (\Exception $e) {
            $this->logger->error('Webhook processing error: ' . $e->getMessage(), ['exception' => $e]);
            return $result->setData(['error' => $e->getMessage()]);
        }
    }

    /**
     * Process the webhook event
     *
     * @param array $data
     * @return void
     * @throws LocalizedException
     */
    protected function processWebhookEvent(array $data)
    {
        $eventType = $data['type'] ?? '';
        $object = $data['data']['object'] ?? [];

        switch ($eventType) {
            case 'payment_intent.succeeded':
                $this->processPaymentIntentSucceeded($object);
                break;
            case 'payment_intent.failed':
                $this->processPaymentIntentFailed($object);
                break;
            default:
                $this->logger->debug('Unhandled Fintoc Event: ' . $eventType);
                break;
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
        $orderId = $paymentIntent['metadata']['order_id'] ?? null;

        if (!$orderId) {
            throw new LocalizedException(__('No order ID found in payment intent metadata'));
        }

        $order = $this->orderFactory->create()->loadByIncrementId($orderId);

        if (!$order->getId()) {
            throw new LocalizedException(__('Order not found: %1', $orderId));
        }

        if ($order->getState() === Order::STATE_PROCESSING) {
            // Order already processed
            return;
        }

        // Update order status
        $order->setState(Order::STATE_PROCESSING)
            ->setStatus(Order::STATE_PROCESSING)
            ->addCommentToStatusHistory(
                __('Payment successfully processed by Fintoc. Payment ID: %1', $paymentIntent['id'])
            );

        // Create invoice
        if ($order->canInvoice()) {
            $invoice = $this->invoiceService->prepareInvoice($order);
            $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
            $invoice->register();
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
        $orderId = $paymentIntent['metadata']['order_id'] ?? null;

        if (!$orderId) {
            throw new LocalizedException(__('No order ID found in payment intent metadata'));
        }

        $order = $this->orderFactory->create()->loadByIncrementId($orderId);

        if (!$order->getId()) {
            throw new LocalizedException(__('Order not found: %1', $orderId));
        }

        // Update order status
        $order->setState(Order::STATE_CANCELED)
            ->setStatus(Order::STATE_CANCELED)
            ->addCommentToStatusHistory(
                __('Payment failed at Fintoc. Payment ID: %1. Reason: %2',
                   $paymentIntent['id'],
                   $paymentIntent['last_payment_error']['message'] ?? 'Unknown error')
            );

        $order->save();
    }

    /**
     * Get the webhook secret from configuration
     *
     * @return string
     * @throws LocalizedException
     */
    protected function getWebhookSecret()
    {
        $webhookSecret = $this->scopeConfig->getValue(
            'payment/fintoc_payment/webhook_secret',
            ScopeInterface::SCOPE_STORE
        );

        if (!$webhookSecret) {
            throw new LocalizedException(__('Webhook secret is not configured'));
        }

        return $webhookSecret;
    }
}
