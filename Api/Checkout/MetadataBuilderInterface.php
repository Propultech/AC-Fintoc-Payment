<?php
/**
 * Copyright © Fintoc. All rights reserved.
 */

namespace Fintoc\Payment\Api\Checkout;

use Magento\Sales\Api\Data\OrderInterface;

/**
 * Builds the metadata array for the Fintoc Checkout payload.
 *
 * Third-party developers can plugin around this interface to add/modify
 * metadata values that accompany the checkout session creation.
 */
interface MetadataBuilderInterface
{
    /**
     * Build the metadata payload.
     *
     * @param OrderInterface $order
     * @param string $transactionId
     * @return array
     */
    public function build(OrderInterface $order, string $transactionId): array;
}
