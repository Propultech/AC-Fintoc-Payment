<?php
/**
 * Copyright © Fintoc. All rights reserved.
 */
declare(strict_types=1);

namespace Fintoc\Payment\Model\Source;

use Fintoc\Payment\Api\Data\TransactionInterface;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Transaction status source model
 */
class TransactionStatus implements OptionSourceInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => TransactionInterface::STATUS_PENDING,
                'label' => __('Pending')
            ],
            [
                'value' => TransactionInterface::STATUS_SUCCESS,
                'label' => __('Success')
            ],
            [
                'value' => TransactionInterface::STATUS_FAILED,
                'label' => __('Failed')
            ],
            [
                'value' => TransactionInterface::STATUS_PROCESSING,
                'label' => __('Processing')
            ],
            [
                'value' => TransactionInterface::STATUS_CANCELED,
                'label' => __('Canceled')
            ]
        ];
    }
}
