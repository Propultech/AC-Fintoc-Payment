<?php
/**
 * Copyright © Fintoc. All rights reserved.
 */
declare(strict_types=1);

namespace Fintoc\Payment\Service;

use Fintoc\Payment\Api\ConfigurationServiceInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Service for reading configuration values
 */
class ConfigurationService implements ConfigurationServiceInterface
{
    /**
     * Configuration paths
     */
    private const XML_PATH_LOGGING_ENABLED = 'payment/fintoc_payment/logging_enabled';
    private const XML_PATH_DEBUG_LEVEL = 'payment/fintoc_payment/debug_level';
    private const XML_PATH_LOG_SENSITIVE_DATA = 'payment/fintoc_payment/log_sensitive_data';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
    }

    /**
     * @inheritDoc
     */
    public function isLoggingEnabled(): bool
    {
        return (bool)$this->getConfig(self::XML_PATH_LOGGING_ENABLED);
    }

    /**
     * @inheritDoc
     */
    public function getDebugLevel(): int
    {
        return (int)$this->getConfig(self::XML_PATH_DEBUG_LEVEL);
    }

    /**
     * @inheritDoc
     */
    public function isLogSensitiveDataEnabled(): bool
    {
        return (bool)$this->getConfig(self::XML_PATH_LOG_SENSITIVE_DATA);
    }

    /**
     * @inheritDoc
     */
    public function getConfig(string $path, string $scope = ScopeInterface::SCOPE_STORE, $scopeId = null)
    {
        if ($scopeId === null) {
            try {
                $scopeId = $this->storeManager->getStore()->getId();
            } catch (\Exception $e) {
                $scopeId = null;
            }
        }

        return $this->scopeConfig->getValue($path, $scope, $scopeId);
    }
}
