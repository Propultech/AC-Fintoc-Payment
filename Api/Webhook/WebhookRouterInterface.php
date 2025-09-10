<?php
declare(strict_types=1);

namespace Fintoc\Payment\Api\Webhook;

use Fintoc\Payment\Service\Webhook\WebhookEvent;

interface WebhookRouterInterface
{
    /**
     * @param WebhookEvent $event
     * @return void
     */
    public function dispatch(WebhookEvent $event): void;
}
