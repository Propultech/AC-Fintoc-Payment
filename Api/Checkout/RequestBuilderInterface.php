<?php
/**
 * Copyright © Fintoc. All rights reserved.
 */

namespace Fintoc\Payment\Api\Checkout;

use Magento\Sales\Api\Data\OrderInterface;

/**
 * Builds the Checkout Session request payload sent to Fintoc API.
 *
 * Third-party developers can plugin around this interface to add/modify fields
 * in the request payload before it is sent to Fintoc.
 */
interface RequestBuilderInterface
{
    /**
     * Build the request payload for creating a checkout session.
     *
     * @param OrderInterface $order The Magento order associated to the session
     * @param string $transactionId The internal transaction id used by the module
     * @return array The payload that will be sent as JSON body
     */
    public function build(OrderInterface $order, string $transactionId): array;
}
