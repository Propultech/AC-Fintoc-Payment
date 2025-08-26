<?php
/**
 * Copyright © Fintoc. All rights reserved.
 */
declare(strict_types=1);

namespace Fintoc\Payment\Service;

use Fintoc\Payment\Api\ConfigurationServiceInterface;
use Fintoc\Payment\Api\LoggerServiceInterface;
use Fintoc\Payment\Logger\Logger;
use Monolog\Logger as MonologLogger;
use Psr\Log\LoggerInterface;
use Stringable;

/**
 * Service for logging messages
 */
class LoggerService implements LoggerServiceInterface, LoggerInterface
{
    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var ConfigurationServiceInterface
     */
    private $configService;

    /**
     * @var DataFilterService
     */
    private $dataFilter;

    /**
     * @param Logger $logger
     * @param ConfigurationServiceInterface $configService
     * @param DataFilterService $dataFilter
     */
    public function __construct(
        Logger                        $logger,
        ConfigurationServiceInterface $configService,
        DataFilterService             $dataFilter
    ) {
        $this->logger = $logger;
        $this->configService = $configService;
        $this->dataFilter = $dataFilter;
    }

    /**
     * @inheritDoc
     */
    public function notice($message, array $context = [])
    {
        $this->log(MonologLogger::NOTICE, $message, $context);
    }

    /**
     * Log a message with the given level
     *
     * @param int $level The log level
     * @param string|Stringable $message The message to log
     * @param array $context Additional context data
     * @return void
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        // Skip logging if it's disabled or the level is below the configured level
        if (!$this->configService->isLoggingEnabled() || $level < $this->configService->getDebugLevel()) {
            return;
        }

        // Filter sensitive data
        $message = $this->dataFilter->filterSensitiveData($message);
        $context = $this->dataFilter->filterSensitiveData($context);

        // Add timestamp to context
        $context['timestamp'] = date('Y-m-d H:i:s');

        // Log the message
        $this->logger->addRecord($level, $message, $context);
    }

    /**
     * @inheritDoc
     */
    public function warning($message, array $context = [])
    {
        $this->log(MonologLogger::WARNING, $message, $context);
    }

    /**
     * @inheritDoc
     */
    public function error($message, array $context = [])
    {
        $this->log(MonologLogger::ERROR, $message, $context);
    }

    /**
     * @inheritDoc
     */
    public function critical($message, array $context = [])
    {
        $this->log(MonologLogger::CRITICAL, $message, $context);
    }

    /**
     * @inheritDoc
     */
    public function alert($message, array $context = [])
    {
        $this->log(MonologLogger::ALERT, $message, $context);
    }

    /**
     * @inheritDoc
     */
    public function emergency($message, array $context = [])
    {
        $this->log(MonologLogger::EMERGENCY, $message, $context);
    }

    /**
     * @inheritDoc
     */
    public function logWebhook(string $eventType, array $data, array $context = [])
    {
        $context = array_merge($context, ['event_type' => $eventType]);
        $this->info('Webhook received: ' . $eventType, array_merge($context, ['data' => $data]));
    }

    /**
     * @inheritDoc
     */
    public function info($message, array $context = [])
    {
        $this->log(MonologLogger::INFO, $message, $context);
    }

    /**
     * @inheritDoc
     */
    public function logApiRequest(string $method, string $endpoint, array $params = [], array $context = [])
    {
        $context = array_merge($context, [
            'method' => $method,
            'endpoint' => $endpoint,
            'params' => $params
        ]);
        $this->debug('API Request: ' . $method . ' ' . $endpoint, $context);
    }

    /**
     * @inheritDoc
     */
    public function debug($message, array $context = [])
    {
        $this->log(MonologLogger::DEBUG, $message, $context);
    }

    /**
     * @inheritDoc
     */
    public function logApiResponse(string $method, string $endpoint, int $statusCode, array $response = [], array $context = [])
    {
        $context = array_merge($context, [
            'method' => $method,
            'endpoint' => $endpoint,
            'status_code' => $statusCode,
            'response' => $response
        ]);

        $level = $this->getLogLevelForStatusCode($statusCode);
        $this->log($level, 'API Response: ' . $method . ' ' . $endpoint . ' ' . $statusCode, $context);
    }

    /**
     * Get the log level for the given status code
     *
     * @param int $statusCode The HTTP status code
     * @return int The log level
     */
    private function getLogLevelForStatusCode(int $statusCode): int
    {
        if ($statusCode >= 500) {
            return MonologLogger::ERROR;
        }

        if ($statusCode >= 400) {
            return MonologLogger::WARNING;
        }

        return MonologLogger::INFO;
    }

    /**
     * @inheritDoc
     */
    public function logTransaction(string $transactionId, string $type, array $data = [], array $context = [])
    {
        $context = array_merge($context, [
            'transaction_id' => $transactionId,
            'type' => $type,
            'data' => $data
        ]);
        $this->info('Transaction: ' . $type . ' ' . $transactionId, $context);
    }
}
