<?php
declare(strict_types=1);

namespace Fintoc\Payment\Service\Webhook\Handler;

use Fintoc\Payment\Api\Data\TransactionInterface;
use Fintoc\Payment\Service\Webhook\WebhookEvent;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Psr\Log\LoggerInterface;

class PaymentIntentFailedHandler extends AbstractPaymentIntentHandler
{
    public function __construct(
        OrderFactory $orderFactory,
        LoggerInterface $logger,
        \Fintoc\Payment\Api\TransactionServiceInterface $transactionService,
        \Fintoc\Payment\Api\TransactionRepositoryInterface $transactionRepository,
        Json $json,
        CartRepositoryInterface $cartRepository
    ) {
        parent::__construct($orderFactory, $transactionService, $transactionRepository, $json, $logger, $cartRepository);
    }

    public function handle(WebhookEvent $event): void
    {
        $pi = $this->normalizePaymentIntent($event->getObject());
        $orderId = $this->extractOrderIncrementId($pi);
        if (!$orderId) {
            throw new LocalizedException(__('No order ID found in payment intent metadata:  ' . $this->json->serialize($pi['metadata'] ?? '')));
        }
        $order = $this->loadOrderOrFail($orderId);

        if ($order->getState() === Order::STATE_CANCELED) {
            $tx = $this->getFirstTransactionByOrder($orderId);
            if ($tx) {
                $this->appendWebhookPayload($tx, $event, \Fintoc\Payment\Service\Webhook\WebhookConstants::EV_PI_FAILED);
            }
            $this->restoreQuoteForOrder($order);
            return;
        }

        $error = $pi['errorReason'] ?? ($pi['last_payment_error']['message'] ?? 'Payment failed or canceled');
        $this->upsertAndAppendPiTransaction($order, $pi, TransactionInterface::STATUS_FAILED, $event, [
            'error_message' => $error,
            'reference' => $pi['referenceId'] ?? null,
        ]);

        $this->setPiPaymentInfo($order, $pi, [
            'fintoc_error_reason' => $pi['errorReason'] ?? null,
            'fintoc_failed_at' => date('Y-m-d H:i:s'),
        ]);

        $order->cancel();
        $order->addCommentToStatusHistory(
            __(
                'Fintoc payment failed. ID: %1, Amount: %2 %3, Status: %4%5',
                $pi['id'] ?? 'N/A',
                $pi['amount'] ?? 'N/A',
                $pi['currency'] ?? '',
                $pi['status'] ?? 'failed',
                $error ? (': ' . $error) : ''
            )
        );
        $order->save();

        $this->restoreQuoteForOrder($order);

        $this->logger->info('Payment failed and order canceled', [
            'payment_id' => $pi['id'] ?? null,
            'order_id' => $orderId,
            'status' => $pi['status'] ?? 'failed',
            'error_reason' => $pi['errorReason'] ?? null
        ]);
    }
}
