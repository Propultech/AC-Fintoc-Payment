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
     * Check if logging is enabled
     *
     * @return bool
     */
    public function isLoggingEnabled(): bool;

    /**
     * Get the debug level
     *
     * @return int
     */
    public function getDebugLevel(): int;

    /**
     * Check if sensitive data logging is enabled
     *
     * @return bool
     */
    public function isLogSensitiveDataEnabled(): bool;

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
