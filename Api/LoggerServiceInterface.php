<?php
/**
 * Copyright © Fintoc. All rights reserved.
 */
declare(strict_types=1);

namespace Fintoc\Payment\Api;

/**
 * Interface for the logger service
 */
interface LoggerServiceInterface
{
    /**
     * Log a debug message
     *
     * @param string|array $message The message to log
     * @param array $context Additional context data
     * @return void
     */
    public function debug($message, array $context = []);

    /**
     * Log an info message
     *
     * @param string|array $message The message to log
     * @param array $context Additional context data
     * @return void
     */
    public function info($message, array $context = []);

    /**
     * Log a notice message
     *
     * @param string|array $message The message to log
     * @param array $context Additional context data
     * @return void
     */
    public function notice($message, array $context = []);

    /**
     * Log a warning message
     *
     * @param string|array $message The message to log
     * @param array $context Additional context data
     * @return void
     */
    public function warning($message, array $context = []);

    /**
     * Log an error message
     *
     * @param string|array $message The message to log
     * @param array $context Additional context data
     * @return void
     */
    public function error($message, array $context = []);

    /**
     * Log a critical message
     *
     * @param string|array $message The message to log
     * @param array $context Additional context data
     * @return void
     */
    public function critical($message, array $context = []);

    /**
     * Log an alert message
     *
     * @param string|array $message The message to log
     * @param array $context Additional context data
     * @return void
     */
    public function alert($message, array $context = []);

    /**
     * Log an emergency message
     *
     * @param string|array $message The message to log
     * @param array $context Additional context data
     * @return void
     */
    public function emergency($message, array $context = []);

    /**
     * Log a webhook event
     *
     * @param string $eventType The webhook event type
     * @param array $data The webhook data
     * @param array $context Additional context data
     * @return void
     */
    public function logWebhook(string $eventType, array $data, array $context = []);

    /**
     * Log an API request
     *
     * @param string $method The HTTP method
     * @param string $endpoint The API endpoint
     * @param array $params The request parameters
     * @param array $context Additional context data
     * @return void
     */
    public function logApiRequest(string $method, string $endpoint, array $params = [], array $context = []);

    /**
     * Log an API response
     *
     * @param string $method The HTTP method
     * @param string $endpoint The API endpoint
     * @param int $statusCode The response status code
     * @param array $response The response data
     * @param array $context Additional context data
     * @return void
     */
    public function logApiResponse(string $method, string $endpoint, int $statusCode, array $response = [], array $context = []);

    /**
     * Log a payment transaction
     *
     * @param string $transactionId The transaction ID
     * @param string $type The transaction type
     * @param array $data The transaction data
     * @param array $context Additional context data
     * @return void
     */
    public function logTransaction(string $transactionId, string $type, array $data = [], array $context = []);
}
