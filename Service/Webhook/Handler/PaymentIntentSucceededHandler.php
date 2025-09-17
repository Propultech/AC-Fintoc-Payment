<?php
declare(strict_types=1);

namespace Fintoc\Payment\Service\Webhook\Handler;

use Exception;
use Fintoc\Payment\Api\Data\TransactionInterface;
use Fintoc\Payment\Api\TransactionRepositoryInterface;
use Fintoc\Payment\Api\TransactionServiceInterface;
use Fintoc\Payment\Service\Webhook\WebhookEvent;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Framework\DB\Transaction as DbTransaction;
use Psr\Log\LoggerInterface;

class PaymentIntentSucceededHandler extends AbstractPaymentIntentHandler
{
    private $invoiceService;
    private $dbTransaction;
    private $invoiceSender;

    /**
     * @param OrderFactory $orderFactory
     * @param InvoiceService $invoiceService
     * @param DbTransaction $dbTransaction
     * @param InvoiceSender $invoiceSender
     * @param TransactionServiceInterface $transactionService
     * @param TransactionRepositoryInterface $transactionRepository
     * @param Json $json
     * @param LoggerInterface $logger
     */
    public function __construct(
        OrderFactory $orderFactory,
        InvoiceService $invoiceService,
        DbTransaction $dbTransaction,
        InvoiceSender $invoiceSender,
        TransactionServiceInterface $transactionService,
        TransactionRepositoryInterface $transactionRepository,
        Json $json,
        LoggerInterface $logger
    ) {
        parent::__construct($orderFactory, $transactionService, $transactionRepository, $json, $logger, null);
        $this->invoiceService = $invoiceService;
        $this->dbTransaction = $dbTransaction;
        $this->invoiceSender = $invoiceSender;
    }

    /**
     * Handles the webhook event for a payment intent and processes the associated order.
     *
     * @param WebhookEvent $event The webhook event containing payment intent information to process.
     *
     * @return void
     *
     * @throws LocalizedException If no order ID is found in payment intent metadata or other order processing exceptions occur.
     */
    public function handle(WebhookEvent $event): void
    {
        $pi = $this->normalizePaymentIntent($event->getObject());
        $orderId = $this->extractOrderIncrementId($pi);
        if (!$orderId) {
            throw new LocalizedException(__('No order ID found in payment intent metadata:  ' . $this->json->serialize($pi['metadata'] ?? '')));
        }
        $order = $this->loadOrderOrFail($orderId);

        if ($order->getState() === Order::STATE_PROCESSING) {
            $tx = $this->getFirstTransactionByOrder($orderId);
            if ($tx) {
                $this->appendWebhookPayload($tx, $event, \Fintoc\Payment\Service\Webhook\WebhookConstants::EV_PI_SUCCEEDED);
            }
            $this->logger->debug('Order already processed: ' . $orderId);
            return;
        }

        // Upsert transaction as SUCCESS and append payload
        $this->upsertAndAppendPiTransaction($order, $pi, TransactionInterface::STATUS_SUCCESS, $event, [
            'error_message' => null,
            'reference' => $pi['referenceId'] ?? null,
        ]);

        // Update payment info
        $extra = [];
        if (isset($pi['senderAccount'])) {
            $extra['fintoc_sender_account'] = $this->json->serialize($pi['senderAccount']);
        }
        $this->setPiPaymentInfo($order, $pi, $extra);

        // Add concise history comment
        $order->addCommentToStatusHistory(
            __(
                'Fintoc payment succeeded. ID: %1, Amount: %2 %3, Ref: %4',
                $pi['id'] ?? 'N/A',
                $pi['amount'] ?? 'N/A',
                $pi['currency'] ?? '',
                $pi['referenceId'] ?? 'N/A'
            )
        );

        // Create invoice
        try {
            if ($order->canInvoice()) {
                $invoice = $this->invoiceService->prepareInvoice($order);
                $invoice->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);
                $invoice->register();
                $invoice->save();

                $invoice->pay();
                $invoice->save();

                $transactionSave = $this->dbTransaction->addObject($invoice)->addObject($invoice->getOrder());
                $transactionSave->save();

                $this->invoiceSender->send($invoice);
                $order->addCommentToStatusHistory(__('Invoice #%1 created', $invoice->getIncrementId()));
            }
        } catch (Exception $invEx) {
            $this->logger->critical('Invoice creation failed after successful payment', [
                'order_id' => $orderId,
                'payment_id' => $pi['id'] ?? null,
                'error' => $invEx->getMessage(),
            ]);
        }

        $order->save();

        $this->logger->info('Payment succeeded and order processed', [
            'payment_id' => $pi['id'] ?? null,
            'order_id' => $orderId,
            'amount' => $pi['amount'] ?? null,
            'currency' => $pi['currency'] ?? null
        ]);
    }
}
