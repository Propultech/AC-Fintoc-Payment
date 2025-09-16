<?php
/**
 * Copyright © Fintoc. All rights reserved.
 */
declare(strict_types=1);

namespace Fintoc\Payment\Api;

use Fintoc\Payment\Api\Data\TransactionInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * REST-facing management interface for creating refunds.
 *
 * This contract is tailored for Web API usage and delegates to RefundServiceInterface.
 */
interface RefundManagementInterface
{
    /**
     * Create a refund for a Magento order by its increment ID.
     *
     * - If amount is omitted (null), a full refund is requested.
     * - If amount is provided, a partial refund is requested (if allowed by configuration).
     *
     * @param string $orderIncrementId Magento order increment ID (visible order number)
     * @param float|null $amount Amount to refund; null for full refund
     * @param string|null $currency Optional currency code (defaults to order currency)
     * @param array $metadata Optional metadata (e.g., reason)
     * @return TransactionInterface The created refund transaction (initially pending)
     *
     * @throws NoSuchEntityException If order is not found
     * @throws LocalizedException For validation errors or configuration restrictions
     */
    public function createRefund(string $orderIncrementId, ?float $amount = null, ?string $currency = null, array $metadata = []): TransactionInterface;
}
