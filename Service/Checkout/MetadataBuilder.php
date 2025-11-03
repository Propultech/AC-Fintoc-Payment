<?php
/**
 * Copyright © Fintoc. All rights reserved.
 */

namespace Fintoc\Payment\Service\Checkout;

use Fintoc\Payment\Api\Checkout\MetadataBuilderInterface;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Default metadata builder for Fintoc Checkout payloads.
 *
 * This class is intentionally simple so third-parties can extend/modify
 * the metadata via plugins on the build() method.
 */
class MetadataBuilder implements MetadataBuilderInterface
{
    /**
     * {@inheritdoc}
     */
    public function build(OrderInterface $order, string $transactionId): array
    {
        $metadata = [
            'ecommerce_order_id' => $order->getIncrementId(),
        ];

        // Hook point: Plugins may add/edit/remove metadata keys here.
        return $metadata;
    }
}
