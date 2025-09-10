<?php
/**
 * Copyright © Fintoc. All rights reserved.
 */
declare(strict_types=1);

namespace Fintoc\Payment\Service;

use Fintoc\Payment\Api\ConfigurationServiceInterface;

/**
 * Service for filtering sensitive data from log messages
 */
class DataFilterService
{
    /**
     * @var ConfigurationServiceInterface
     */
    private $configService;

    /**
     * @var array
     */
    private $sensitiveKeys = [
        'api_secret',
        'apiSecret',
        'secret',
        'password',
        'token',
        'authorization',
        'auth',
        'card_number',
        'cardNumber',
        'cvv',
        'cvc',
        'expiry',
        'webhook_secret',
        'webhookSecret',
        'private_key',
        'privateKey'
    ];

    /**
     * @param ConfigurationServiceInterface $configService
     */
    public function __construct(
        ConfigurationServiceInterface $configService
    ) {
        $this->configService = $configService;
    }

    /**
     * Filter sensitive data from a message
     *
     * @param mixed $data The data to filter
     * @return mixed The filtered data
     */
    public function filterSensitiveData($data)
    {
        // If sensitive data logging is enabled, return the data as is
        if ($this->configService->isLogSensitiveDataEnabled()) {
            return $data;
        }

        // If the data is a string, filter it
        if (is_string($data)) {
            return $this->filterSensitiveString($data);
        }

        // If the data is an array, filter each element
        if (is_array($data)) {
            return $this->filterSensitiveArray($data);
        }

        // If the data is an object, convert it to an array, filter it, and convert it back
        if (is_object($data)) {
            $array = json_decode(json_encode($data), true);
            $filtered = $this->filterSensitiveArray($array);
            return json_decode(json_encode($filtered));
        }

        // Otherwise, return the data as is
        return $data;
    }

    /**
     * Filter sensitive data from a string
     *
     * @param string $string The string to filter
     * @return string The filtered string
     */
    private function filterSensitiveString(string $string): string
    {
        // Filter credit card numbers
        $string = preg_replace('/\b(?:\d[ -]*?){13,16}\b/', '[FILTERED]', $string);

        // Filter API keys and tokens
        foreach ($this->sensitiveKeys as $key) {
            $pattern = '/(["\']?' . preg_quote($key, '/') . '["\']?\s*[=:]\s*["\']?)([^"\'\s]+)(["\']?)/i';
            $string = preg_replace($pattern, '$1[FILTERED]$3', $string);
        }

        return $string;
    }

    /**
     * Filter sensitive data from an array
     *
     * @param array $array The array to filter
     * @return array The filtered array
     */
    private function filterSensitiveArray(array $array): array
    {
        foreach ($array as $key => $value) {
            // If the key is sensitive, filter the value
            if (in_array(strtolower($key), array_map('strtolower', $this->sensitiveKeys))) {
                $array[$key] = '[FILTERED]';
                continue;
            }

            // If the value is an array, filter it recursively
            if (is_array($value)) {
                $array[$key] = $this->filterSensitiveArray($value);
                continue;
            }

            // If the value is a string, filter it
            if (is_string($value)) {
                $array[$key] = $this->filterSensitiveString($value);
            }
        }

        return $array;
    }
}
