<?php
/**
 * Copyright © Fintoc. All rights reserved.
 */

namespace Fintoc\Payment\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Fintoc\Payment\Model\Payment;

/**
 * Configuration provider for Fintoc payment method
 */
class ConfigProvider implements ConfigProviderInterface
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        return [
            'payment' => [
                Payment::CODE => [
                    'title' => $this->getTitle(),
                    'apiKey' => $this->getPublicApiKey(),
                    'isDebugMode' => $this->isDebugMode()
                ]
            ]
        ];
    }

    /**
     * Get payment method title
     *
     * @return string
     */
    protected function getTitle()
    {
        return $this->scopeConfig->getValue(
            'payment/' . Payment::CODE . '/title',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get public API key
     *
     * @return string
     */
    protected function getPublicApiKey()
    {
        return $this->scopeConfig->getValue(
            'payment/' . Payment::CODE . '/api_key',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Check if debug mode is enabled
     *
     * @return bool
     */
    protected function isDebugMode()
    {
        return (bool) $this->scopeConfig->getValue(
            'payment/' . Payment::CODE . '/debug',
            ScopeInterface::SCOPE_STORE
        );
    }
}
