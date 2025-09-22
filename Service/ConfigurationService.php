<?php
/**
 * Copyright © Fintoc. All rights reserved.
 */
declare(strict_types=1);

namespace Fintoc\Payment\Service;

use Exception;
use Fintoc\Payment\Api\ConfigurationServiceInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Service for reading configuration values
 */
class ConfigurationService implements ConfigurationServiceInterface
{
    /**
     * Centralized defaults
     */
    public const DEFAULT_API_BASE_URL = 'https://api.fintoc.com';

    /**
     * Configuration paths
     */
    private const XML_PATH_ACTIVE = 'payment/fintoc_payment/active';
    private const XML_PATH_TITLE = 'payment/fintoc_payment/title';
    private const XML_PATH_ORDER_STATUS = 'payment/fintoc_payment/order_status';
    private const XML_PATH_PAYMENT_ACTION = 'payment/fintoc_payment/payment_action';
    private const XML_PATH_ALLOW_SPECIFIC = 'payment/fintoc_payment/allowspecific';
    private const XML_PATH_SPECIFIC_COUNTRY = 'payment/fintoc_payment/specificcountry';
    private const XML_PATH_API_SECRET = 'payment/fintoc_payment/api_secret';
    private const XML_PATH_WEBHOOK_SECRET = 'payment/fintoc_payment/webhook_secret';
    private const XML_PATH_DEBUG = 'payment/fintoc_payment/debug';
    private const XML_PATH_LOGGING_ENABLED = 'payment/fintoc_payment/logging_enabled';
    private const XML_PATH_DEBUG_LEVEL = 'payment/fintoc_payment/debug_level';
    private const XML_PATH_LOG_SENSITIVE_DATA = 'payment/fintoc_payment/log_sensitive_data';
    private const XML_PATH_MAX_ORDER_AMOUNT = 'payment/fintoc_payment/max_order_amount';
    private const XML_PATH_SORT_ORDER = 'payment/fintoc_payment/sort_order';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        ScopeConfigInterface                             $scopeConfig,
        StoreManagerInterface                            $storeManager,
        EncryptorInterface $encryptor
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->encryptor = $encryptor;
    }

    /**
     * @inheritDoc
     */
    public function isActive(?string $scopeCode = null): bool
    {
        return (bool)$this->getConfig(self::XML_PATH_ACTIVE, ScopeInterface::SCOPE_STORE, $scopeCode);
    }

    /**
     * @inheritDoc
     */
    public function getConfig(string $path, string $scope = ScopeInterface::SCOPE_STORE, $scopeId = null)
    {
        return $this->scopeConfig->getValue($path, $scope, $scopeId);
    }

    /**
     * @inheritDoc
     */
    public function getTitle(?string $scopeCode = null): string
    {
        return (string)$this->getConfig(self::XML_PATH_TITLE, ScopeInterface::SCOPE_STORE, $scopeCode);
    }

    /**
     * @inheritDoc
     */
    public function getOrderStatus(?string $scopeCode = null): string
    {
        return (string)$this->getConfig(self::XML_PATH_ORDER_STATUS, ScopeInterface::SCOPE_STORE, $scopeCode);
    }

    /**
     * @inheritDoc
     */
    public function getPaymentAction(?string $scopeCode = null): string
    {
        return (string)$this->getConfig(self::XML_PATH_PAYMENT_ACTION, ScopeInterface::SCOPE_STORE, $scopeCode);
    }

    /**
     * @inheritDoc
     */
    public function isAllowSpecific(?string $scopeCode = null): bool
    {
        return (bool)$this->getConfig(self::XML_PATH_ALLOW_SPECIFIC, ScopeInterface::SCOPE_STORE, $scopeCode);
    }

    /**
     * @inheritDoc
     */
    public function getSpecificCountries(?string $scopeCode = null): array
    {
        $countries = $this->getConfig(self::XML_PATH_SPECIFIC_COUNTRY, ScopeInterface::SCOPE_STORE, $scopeCode);
        return $countries ? explode(',', $countries) : [];
    }

    /**
     * @inheritDoc
     */
    public function getApiSecret(?string $scopeCode = null): string
    {
        $encryptedValue = $this->getConfig(self::XML_PATH_API_SECRET, ScopeInterface::SCOPE_WEBSITE, $scopeCode);
        if (!$encryptedValue) {
            return '';
        }
        return $this->encryptor->decrypt($encryptedValue);
    }

    /**
     * @inheritDoc
     */
    public function getWebhookSecret(?string $scopeCode = null): string
    {
        $encryptedValue = $this->getConfig(self::XML_PATH_WEBHOOK_SECRET, ScopeInterface::SCOPE_WEBSITE, $scopeCode);
        if (!$encryptedValue) {
            return '';
        }
        return $this->encryptor->decrypt($encryptedValue);
    }

    /**
     * @inheritDoc
     */
    public function isDebugMode(?string $scopeCode = null): bool
    {
        return (bool)$this->getConfig(self::XML_PATH_DEBUG, ScopeInterface::SCOPE_STORE, $scopeCode);
    }

    /**
     * @inheritDoc
     */
    public function isLoggingEnabled(?string $scopeCode = null): bool
    {
        return (bool)$this->getConfig(self::XML_PATH_LOGGING_ENABLED, ScopeInterface::SCOPE_STORE, $scopeCode);
    }

    /**
     * @inheritDoc
     */
    public function getDebugLevel(?string $scopeCode = null): int
    {
        return (int)$this->getConfig(self::XML_PATH_DEBUG_LEVEL, ScopeInterface::SCOPE_STORE, $scopeCode);
    }

    /**
     * @inheritDoc
     */
    public function isLogSensitiveDataEnabled(?string $scopeCode = null): bool
    {
        return (bool)$this->getConfig(self::XML_PATH_LOG_SENSITIVE_DATA, ScopeInterface::SCOPE_STORE, $scopeCode);
    }

    /**
     * @inheritDoc
     */
    public function getMaxOrderAmount(?string $scopeCode = null): ?float
    {
        $value = $this->getConfig(self::XML_PATH_MAX_ORDER_AMOUNT, ScopeInterface::SCOPE_STORE, $scopeCode);
        return $value !== null && $value !== '' ? (float)$value : null;
    }

    /**
     * @inheritDoc
     */
    public function getSortOrder(?string $scopeCode = null): int
    {
        return (int)$this->getConfig(self::XML_PATH_SORT_ORDER, ScopeInterface::SCOPE_STORE, $scopeCode);
    }
}
