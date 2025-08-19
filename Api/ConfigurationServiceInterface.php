<?php
/**
 * Copyright © Fintoc. All rights reserved.
 */
declare(strict_types=1);

namespace Fintoc\Payment\Api;

/**
 * Interface for the configuration service
 */
interface ConfigurationServiceInterface
{
    /**
     * Check if the payment method is active
     *
     * @param string|null $scopeCode
     * @return bool
     */
    public function isActive(?string $scopeCode = null): bool;

    /**
     * Get payment method title
     *
     * @param string|null $scopeCode
     * @return string
     */
    public function getTitle(?string $scopeCode = null): string;

    /**
     * Get order status
     *
     * @param string|null $scopeCode
     * @return string
     */
    public function getOrderStatus(?string $scopeCode = null): string;

    /**
     * Get payment action
     *
     * @param string|null $scopeCode
     * @return string
     */
    public function getPaymentAction(?string $scopeCode = null): string;

    /**
     * Check if specific countries are allowed
     *
     * @param string|null $scopeCode
     * @return bool
     */
    public function isAllowSpecific(?string $scopeCode = null): bool;

    /**
     * Get specific countries allowed
     *
     * @param string|null $scopeCode
     * @return array
     */
    public function getSpecificCountries(?string $scopeCode = null): array;

    /**
     * Get public API key
     *
     * @param string|null $scopeCode
     * @return string
     */
    public function getApiKey(?string $scopeCode = null): string;

    /**
     * Get decrypted API secret
     *
     * @param string|null $scopeCode
     * @return string
     */
    public function getApiSecret(?string $scopeCode = null): string;

    /**
     * Get decrypted webhook secret
     *
     * @param string|null $scopeCode
     * @return string
     */
    public function getWebhookSecret(?string $scopeCode = null): string;

    /**
     * Check if debug mode is enabled
     *
     * @param string|null $scopeCode
     * @return bool
     */
    public function isDebugMode(?string $scopeCode = null): bool;

    /**
     * Check if logging is enabled
     *
     * @param string|null $scopeCode
     * @return bool
     */
    public function isLoggingEnabled(?string $scopeCode = null): bool;

    /**
     * Get the debug level
     *
     * @param string|null $scopeCode
     * @return int
     */
    public function getDebugLevel(?string $scopeCode = null): int;

    /**
     * Check if sensitive data logging is enabled
     *
     * @param string|null $scopeCode
     * @return bool
     */
    public function isLogSensitiveDataEnabled(?string $scopeCode = null): bool;

    /**
     * Get maximum order amount for Fintoc payment
     *
     * @param string|null $scopeCode
     * @return float|null
     */
    public function getMaxOrderAmount(?string $scopeCode = null): ?float;

    /**
     * Get sort order
     *
     * @param string|null $scopeCode
     * @return int
     */
    public function getSortOrder(?string $scopeCode = null): int;

    /**
     * Get a configuration value
     *
     * @param string $path The configuration path
     * @param string $scope The scope
     * @param int|string|null $scopeId The scope ID
     * @return mixed
     */
    public function getConfig(string $path, string $scope = 'store', $scopeId = null);
}
