<?php
declare(strict_types=1);

namespace Fintoc\Payment\Service;

use Fintoc\Payment\Api\RefundsApiClientInterface;

/**
 * Minimal stub implementation for Fintoc Refunds API.
 * Replace with real HTTP client that talks to Fintoc Refunds API.
 */
class RefundsApiClient implements RefundsApiClientInterface
{
    public function createRefund(string $paymentIntentId, int $amountCents, string $currency, array $metadata = []): array
    {
        // Stubbed response mimicking Fintoc Refunds API
        $externalId = 're_' . bin2hex(random_bytes(6));
        return [
            'external_id' => $externalId,
            'status' => 'pending',
            'response' => [
                'id' => $externalId,
                'payment_intent_id' => $paymentIntentId,
                'amount' => $amountCents,
                'currency' => $currency,
                'metadata' => $metadata,
                'object' => 'refund',
            ],
        ];
    }

    public function cancelRefund(string $externalRefundId): array
    {
        // Stubbed cancellation always succeeds
        return [
            'canceled' => true,
            'response' => [
                'id' => $externalRefundId,
                'status' => 'canceled',
                'object' => 'refund',
            ],
        ];
    }
}
