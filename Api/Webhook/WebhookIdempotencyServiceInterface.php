<?php
declare(strict_types=1);

namespace Fintoc\Payment\Api\Webhook;

interface WebhookIdempotencyServiceInterface
{
    /**
     * Marks an event as seen based on the provided event ID.
     *
     * @param string $eventId The unique identifier of the event to mark as seen.
     * @return bool Returns true if the event was successfully marked as seen, false otherwise.
     */
    public function seen(string $eventId): bool;

    /**
     * Marks the specified event as seen.
     *
     * @param string $eventId The unique identifier of the event to be marked as seen.
     * @return void
     */
    public function markSeen(string $eventId): void;
}
