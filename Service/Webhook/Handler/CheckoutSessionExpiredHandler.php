<?php
declare(strict_types=1);

namespace Fintoc\Payment\Service\Webhook\Handler;

use Fintoc\Payment\Api\Data\TransactionInterface;
use Fintoc\Payment\Service\Webhook\WebhookConstants;
use Fintoc\Payment\Service\Webhook\WebhookEvent;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;

class CheckoutSessionExpiredHandler extends AbstractWebhookHandler
{
    /**
     * Handles the given webhook event to process an expired checkout session.
     *
     * This method processes the webhook event, verifies the associated order,
     * cancels it if necessary, updates the transaction information, and restores
     * the related quote for retrying the checkout.
     *
     * @param WebhookEvent $event The webhook event that contains the data for the expired checkout session.
     * @return void
     * @throws LocalizedException If no order ID is found in the checkout session metadata.
     */
    public function handle(WebhookEvent $event): void
    {
        $session = $event->getObject();
        $orderId = $this->extractOrderIncrementId($session);
        if (!$orderId) {
            throw new LocalizedException(__('No order ID found in checkout session metadata'));
        }
        $order = $this->loadOrderOrFail($orderId);

        // Upsert or update transaction as canceled and append payload
        $externalId = (string)($session['id'] ?? ('cs_' . uniqid()));
        $amount = isset($session['amount']) ? ((float)$session['amount']) / 100 : null;
        $currency = $session['currency'] ?? $order->getOrderCurrencyCode();
        $this->upsertAndAppendTransactionRaw(
            $order,
            $externalId,
            $amount,
            $currency,
            TransactionInterface::STATUS_CANCELED,
            $event,
            WebhookConstants::EV_CS_EXPIRED,
            [
                'reference' => $session['referenceId'] ?? ($session['reference_id'] ?? null),
                'error_message' => 'Checkout session expired'
            ],
            $session
        );

        // Update payment info (data-driven)
        $this->setPaymentAdditionalInformation($order, [
            'fintoc_checkout_session_id' => $session['id'] ?? null,
            'fintoc_checkout_session_status' => 'expired',
            'fintoc_checkout_session_expired_at' => date('Y-m-d H:i:s'),
        ]);

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
