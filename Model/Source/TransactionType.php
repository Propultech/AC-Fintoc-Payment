<?php
/**
 * Copyright © Fintoc. All rights reserved.
 */
declare(strict_types=1);

namespace Fintoc\Payment\Model\Source;

use Fintoc\Payment\Api\Data\TransactionInterface;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Transaction type source model
 */
class TransactionType implements OptionSourceInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => TransactionInterface::TYPE_AUTHORIZATION,
                'label' => __('Authorization')
            ],
            [
                'value' => TransactionInterface::TYPE_CAPTURE,
                'label' => __('Capture')
            ],
            [
                'value' => TransactionInterface::TYPE_REFUND,
                'label' => __('Refund')
            ],
            [
                'value' => TransactionInterface::TYPE_VOID,
                'label' => __('Void')
            ],
            [
                'value' => TransactionInterface::TYPE_WEBHOOK,
                'label' => __('Webhook')
            ]
        ];
    }
}
