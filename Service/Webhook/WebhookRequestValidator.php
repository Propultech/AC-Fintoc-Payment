<?php
declare(strict_types=1);

namespace Fintoc\Payment\Service\Webhook;

use Fintoc\Payment\Api\Webhook\WebhookRequestValidatorInterface;
use Fintoc\Payment\Api\ConfigurationServiceInterface;
use Fintoc\Payment\Exceptions\WebhookSignatureError;
use Fintoc\Payment\Utils\WebhookSignature;
use Psr\Log\LoggerInterface;

class WebhookRequestValidator implements WebhookRequestValidatorInterface
{
    /**
     * @var ConfigurationServiceInterface
     */
    private $config;
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param ConfigurationServiceInterface $config
     * @param LoggerInterface $logger
     */
    public function __construct(ConfigurationServiceInterface $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * @param string $rawBody
     * @param array $headers
     * @return void
     * @throws WebhookSignatureError
     */
    public function validate(string $rawBody, array $headers): void
    {
        $signature = $headers['Fintoc-Signature'] ?? $headers['HTTP_FINTOC_SIGNATURE'] ?? null;
        if (!$signature) {
            throw new WebhookSignatureError(__('The fintoc signature was not found.')->render());
        }
        $secret = $this->config->getWebhookSecret();
        $logSensitive = $this->config->isLogSensitiveDataEnabled();
        if ($logSensitive) {
            $this->logger->debug('Webhook signature validation', ['signature' => $signature, 'has_payload' => $rawBody !== '' ]);
        } else {
            $this->logger->debug('Webhook signature validation', ['signature_present' => true, 'payload_present' => $rawBody !== '' ]);
        }
        WebhookSignature::verifyHeader($rawBody, (string)$signature, $secret);
    }
}
