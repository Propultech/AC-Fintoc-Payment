<?php
declare(strict_types=1);

namespace Fintoc\Payment\Service\Webhook\Handler;

use Fintoc\Payment\Api\Data\TransactionInterface;
use Fintoc\Payment\Service\Webhook\WebhookEvent;
use Magento\Framework\Exception\LocalizedException;

class PaymentIntentPendingHandler extends AbstractPaymentIntentHandler
{
    /**
     * Handles the incoming webhook event and processes the associated payment intent.
     *
     * @param WebhookEvent $event The webhook event containing the payment intent details to be processed.
     * @return void
     *
     * @throws LocalizedException If the order ID is not found in the payment intent metadata.
     */
    public function handle(WebhookEvent $event): void
    {
        $pi = $this->normalizePaymentIntent($event->getObject());
        $orderId = $this->extractOrderIncrementId($pi);
        if (!$orderId) {
            throw new LocalizedException(__('No order ID found in payment intent metadata:  ' . $this->json->serialize($pi['metadata'] ?? '')));
        }
        $order = $this->loadOrderOrFail($orderId);

        // Upsert/update transaction as PENDING and append payload
        $this->upsertAndAppendPiTransaction($order, $pi, TransactionInterface::STATUS_PENDING, $event);

        // Payment info (data-driven)
        $this->setPiPaymentInfo($order, $pi, [
            'fintoc_pending_at' => date('Y-m-d H:i:s'),
        ]);

        // Trace comment
        $order->addCommentToStatusHistory(
            __(
                'Fintoc payment pending. ID: %1%2',
                $pi['id'] ?? 'N/A',
                isset($pi['amount'], $pi['currency']) ? __(', Amount: %1 %2', $pi['amount'], $pi['currency']) : ''
            )
        );
        $order->save();

        $this->logger->info('Payment intent pending recorded', ['order' => $orderId]);
    }
}
