<?php
/**
 * Copyright © Fintoc. All rights reserved.
 */

namespace Fintoc\Payment\Service\Checkout;

use Fintoc\Payment\Api\Checkout\MetadataBuilderInterface;
use Fintoc\Payment\Api\Checkout\RequestBuilderInterface;
use Fintoc\Payment\Utils\AmountUtils;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Default request payload builder for creating Fintoc checkout sessions.
 *
 * Third-parties can modify the payload by creating plugins for
 * \Fintoc\Payment\Api\Checkout\RequestBuilderInterface::build().
 */
class RequestBuilder implements RequestBuilderInterface
{
    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var MetadataBuilderInterface */
    private $metadataBuilder;

    /**
     * @param StoreManagerInterface $storeManager
     * @param MetadataBuilderInterface $metadataBuilder
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        MetadataBuilderInterface $metadataBuilder
    ) {
        $this->storeManager = $storeManager;
        $this->metadataBuilder = $metadataBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function build(OrderInterface $order, string $transactionId): array
    {
        $baseUrl = $this->storeManager->getStore()->getBaseUrl();

        $payload = [
            'amount' => AmountUtils::roundToIntHalfUp((float) $order->getGrandTotal()),
            'currency' => $order->getOrderCurrencyCode(),
            'cancel_url' => $baseUrl . 'fintoc/checkout/commit/action/cancel/tr/' . rawurlencode($transactionId),
            'success_url' => $baseUrl . 'fintoc/checkout/commit/action/success/tr/' . rawurlencode($transactionId),
            'customer_email' => $order->getCustomerEmail(),
            'metadata' => $this->metadataBuilder->build($order, $transactionId),
        ];

        // Hook point: Plugins may add/edit/remove top-level payload keys here.
        return $payload;
    }
}
