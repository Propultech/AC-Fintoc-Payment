<?php
declare(strict_types=1);

namespace Fintoc\Payment\Api;

interface RefundsApiClientInterface
{
    /**
     * Create a refund in Fintoc and return the external refund ID and initial status.
     *
     * @param string $paymentIntentId The Fintoc payment intent/transaction ID to refund
     * @param int $amountCents Amount in minor units (e.g., cents)
     * @param string $currency ISO currency code
     * @param array $metadata Optional metadata
     * @return array { external_id: string, status: string, response: array }
     */
    public function createRefund(string $paymentIntentId, int $amountCents, string $currency, array $metadata = []): array;

    /**
     * Cancel a refund while pending.
     *
     * @param string $externalRefundId
     * @return array { canceled: bool, response: array }
     */
    public function cancelRefund(string $externalRefundId): array;
}
