<?php
declare(strict_types=1);

namespace Fintoc\Payment\Service\Webhook;

class WebhookEvent
{
    /** @var string|null */
    private $eventId;
    /** @var string|null */
    private $eventType;
    /** @var array */
    private $object;
    /** @var array */
    private $fullPayload;

    public function __construct(?string $eventId, ?string $eventType, array $object, array $fullPayload)
    {
        $this->eventId = $eventId;
        $this->eventType = $eventType;
        $this->object = $object;
        $this->fullPayload = $fullPayload;
    }

    public function getEventId(): ?string { return $this->eventId; }
    public function getEventType(): ?string { return $this->eventType; }
    public function getObject(): array { return $this->object; }
    public function getFullPayload(): array { return $this->fullPayload; }
}
