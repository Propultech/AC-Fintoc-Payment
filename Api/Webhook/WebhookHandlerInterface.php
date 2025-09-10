<?php
declare(strict_types=1);

namespace Fintoc\Payment\Api\Webhook;

use Fintoc\Payment\Service\Webhook\WebhookEvent;

interface WebhookHandlerInterface
{
    /**
     * Handles the incoming WebhookEvent and processes it accordingly.
     *
     * @param WebhookEvent $event The event object that contains the data for the webhook.
     * @return void
     */
    public function handle(WebhookEvent $event): void;
}
