<?php
declare(strict_types=1);

namespace Fintoc\Payment\Api;

use Fintoc\Payment\Api\Data\TransactionInterface;
use Magento\Sales\Api\Data\OrderInterface;

interface RefundServiceInterface
{
    /**
     * Initiate a refund request for a Magento order.
     *
     * @param OrderInterface $order
     * @param float $amount
     * @param string|null $currency
     * @param array $metadata Optional metadata (e.g., reason)
     * @return TransactionInterface Refund transaction (pending initially)
     */
    public function requestRefund(OrderInterface $order, float $amount, ?string $currency = null, array $metadata = []): TransactionInterface;

    /**
     * Cancel a pending refund if supported.
     *
     * @param string $externalRefundId
     * @return bool True if canceled
     */
    public function cancelRefund(string $externalRefundId): bool;

    /**
     * Handle webhook payload for refunds.
     *
     * @param array $payload
     * @return void
     */
    public function handleWebhook(array $payload): void;
}
