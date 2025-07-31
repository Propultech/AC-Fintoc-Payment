<?php
/**
 * Copyright © Fintoc. All rights reserved.
 */
declare(strict_types=1);

namespace Fintoc\Payment\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;
use Monolog\Logger;

/**
 * Debug level source model
 */
class DebugLevel implements ArrayInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => (string)Logger::DEBUG, 'label' => __('Debug (100)')],
            ['value' => (string)Logger::INFO, 'label' => __('Info (200)')],
            ['value' => (string)Logger::NOTICE, 'label' => __('Notice (250)')],
            ['value' => (string)Logger::WARNING, 'label' => __('Warning (300)')],
            ['value' => (string)Logger::ERROR, 'label' => __('Error (400)')],
            ['value' => (string)Logger::CRITICAL, 'label' => __('Critical (500)')],
            ['value' => (string)Logger::ALERT, 'label' => __('Alert (550)')],
            ['value' => (string)Logger::EMERGENCY, 'label' => __('Emergency (600)')]
        ];
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray(): array
    {
        $options = [];
        foreach ($this->toOptionArray() as $option) {
            $options[$option['value']] = $option['label'];
        }
        return $options;
    }
}
