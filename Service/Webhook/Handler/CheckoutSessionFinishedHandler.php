<?php
declare(strict_types=1);

namespace Fintoc\Payment\Service\Webhook\Handler;

use Fintoc\Payment\Service\Webhook\WebhookConstants;
use Fintoc\Payment\Service\Webhook\WebhookEvent;
use Magento\Framework\Exception\LocalizedException;

class CheckoutSessionFinishedHandler extends AbstractWebhookHandler
{
    /**
     * Handles the webhook event for a checkout session completion.
     *
     * @param WebhookEvent $event The webhook event containing details about the session.
     * @return void
     * @throws LocalizedException If the order ID cannot be extracted from the session.
     */
    public function handle(WebhookEvent $event): void
    {
        $session = $event->getObject();
        $orderId = $this->extractOrderIncrementId($session);
        if (!$orderId) {
            throw new LocalizedException(__('No order ID found in checkout session metadata'));
        }

        $tx = $this->getFirstTransactionByOrder($orderId);
        if ($tx) {
            $this->appendWebhookPayload($tx, $event, WebhookConstants::EV_CS_FINISHED);
        }

        $this->logger->debug('Checkout session finished traced', ['order' => $orderId]);
    }
}
