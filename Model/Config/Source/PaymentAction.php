<?php
/**
 * Copyright © Fintoc. All rights reserved.
 */

namespace Fintoc\Payment\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

/**
 * Payment Action Source Model
 */
class PaymentAction implements ArrayInterface
{
    /**
     * Possible payment actions
     */
    public const ACTION_AUTHORIZE = 'authorize';
    public const ACTION_AUTHORIZE_CAPTURE = 'authorize_capture';

    /**
     * Get available payment actions
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => self::ACTION_AUTHORIZE,
                'label' => __('Authorize Only')
            ],
            [
                'value' => self::ACTION_AUTHORIZE_CAPTURE,
                'label' => __('Authorize and Capture')
            ]
        ];
    }
}
