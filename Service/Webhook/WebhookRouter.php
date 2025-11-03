<?php
declare(strict_types=1);

namespace Fintoc\Payment\Service\Webhook;

use Fintoc\Payment\Api\Webhook\WebhookRouterInterface;
use Fintoc\Payment\Api\Webhook\WebhookHandlerInterface;
use Psr\Log\LoggerInterface;

class WebhookRouter implements WebhookRouterInterface
{
    /** @var array<string, WebhookHandlerInterface> */
    private $handlers;
    /** @var LoggerInterface */
    private $logger;

    /**
     * @param array<string, WebhookHandlerInterface> $handlers
     */
    public function __construct(array $handlers, LoggerInterface $logger)
    {
        $this->handlers = $handlers;
        $this->logger = $logger;
    }

    /**
     * @param WebhookEvent $event
     * @return void
     */
    public function dispatch(WebhookEvent $event): void
    {
        $type = $event->getEventType();
        if ($type && isset($this->handlers[$type])) {
            $this->handlers[$type]->handle($event);
            return;
        }
        // Fallbacks based on object
        $object = $event->getObject();
        $guessed = null;
        if (isset($object['object']) && $object['object'] === 'payment_intent') {
            $status = (string)($object['status'] ?? '');
            if ($status === 'succeeded') { $guessed = 'payment_intent.succeeded'; }
            elseif (in_array($status, ['failed','rejected','expired'], true)) { $guessed = 'payment_intent.failed'; }
            elseif ($status === 'pending') { $guessed = 'payment_intent.pending'; }
        } elseif (isset($object['object']) && $object['object'] === 'checkout_session') {
            $status = (string)($object['status'] ?? '');
            if ($status === 'finished') { $guessed = 'checkout_session.finished'; }
            elseif ($status === 'expired') { $guessed = 'checkout_session.expired'; }
        } elseif (isset($object['object']) && strpos((string)$object['object'], 'refund') !== false) {
            $guessed = 'refund.event';
        }
        if ($guessed && isset($this->handlers[$guessed])) {
            $this->handlers[$guessed]->handle($event);
            return;
        }
        $this->logger->warning('Unhandled Fintoc webhook event', [
            'event_type' => $type,
            'keys' => array_keys($object),
        ]);
    }
}
