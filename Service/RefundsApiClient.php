<?php
declare(strict_types=1);

namespace Fintoc\Payment\Service;

use Fintoc\Payment\Api\ConfigurationServiceInterface as PaymentConfigServiceInterface;
use Fintoc\Payment\Api\RefundsApiClientInterface;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

/**
 * HTTP client for Fintoc Refunds API.
 * Implements create and cancel using configurable base URL and endpoint paths.
 */
class RefundsApiClient implements RefundsApiClientInterface
{
    /** @var GuzzleClient */
    private $httpClient;
    /** @var PaymentConfigServiceInterface */
    private $configService;
    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        GuzzleClient $httpClient,
        PaymentConfigServiceInterface $configService,
        LoggerInterface $logger
    ) {
        $this->httpClient = $httpClient;
        $this->configService = $configService;
        $this->logger = $logger;
    }

    /**
     * Create a refund at Fintoc.
     * @param string $paymentIntentId
     * @param int $amountCents
     * @param string $currency
     * @param array $metadata
     * @return array{external_id:string,status:string,response:array}
     * @throws GuzzleException
     */
    public function createRefund(string $paymentIntentId, int $amountCents, string $currency, array $metadata = []): array
    {
        $baseUrl = rtrim((string)$this->configService->getConfig('payment/fintoc_payment/api_base_url') ?: 'https://api.fintoc.com', '/');
        $path = (string)$this->configService->getConfig('payment/fintoc_payment/refunds_create_path') ?: '/v1/refunds';
        $url = $baseUrl . $path;

        $secret = trim((string)$this->configService->getApiSecret());
        $idempotency = $this->buildIdempotencyKey($paymentIntentId, $amountCents, $currency, $metadata);

        $payload = [
            'resource_id' => $paymentIntentId,
            'resource_type' => 'payment_intent',
            'amount' => $amountCents,
            'currency' => $currency,
        ];
        if (!empty($metadata)) {
            $payload['metadata'] = $metadata;
        }

        $headers = [
            'Authorization' => 'Bearer ' . $secret,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Idempotency-Key' => $idempotency,
        ];

        $response = $this->httpClient->request('POST', $url, [
            'headers' => $headers,
            'json' => $payload,
        ]);

        $statusCode = (int)$response->getStatusCode();
        $body = (string)$response->getBody();
        $data = $this->safeJsonDecode($body);

        if ($statusCode >= 400) {
            $this->logApiError('create', $statusCode, $data, $body);
            $message = isset($data['error']['message']) ? (string)$data['error']['message'] : 'Refund create failed';
            throw new \RuntimeException($message);
        }

        $externalId = (string)($data['id'] ?? $data['refund_id'] ?? '');
        $status = (string)($data['status'] ?? 'pending');
        if ($externalId === '') {
            // Some APIs return in data.data
            $obj = isset($data['data']) && is_array($data['data']) ? $data['data'] : $data;
            $externalId = (string)($obj['id'] ?? '');
            $status = (string)($obj['status'] ?? $status);
        }

        return [
            'external_id' => $externalId,
            'status' => $status ?: 'pending',
            'response' => is_array($data) ? $data : ['raw' => $body],
        ];
    }

    /**
     * Cancel a refund at Fintoc while pending.
     * @param string $externalRefundId
     * @return array{canceled:bool,response:array}
     * @throws GuzzleException
     */
    public function cancelRefund(string $externalRefundId): array
    {
        $baseUrl = rtrim((string)$this->configService->getConfig('payment/fintoc_payment/api_base_url') ?: 'https://api.fintoc.com', '/');
        $cancelPathTmpl = (string)$this->configService->getConfig('payment/fintoc_payment/refunds_cancel_path') ?: '/v1/refunds/{id}/cancel';
        $path = str_replace('{id}', rawurlencode($externalRefundId), $cancelPathTmpl);
        $url = $baseUrl . $path;

        $secret = trim((string)$this->configService->getApiSecret());
        $headers = [
            'Authorization' => 'Bearer ' . $secret,
            'Accept' => 'application/json',
        ];

        $response = $this->httpClient->request('POST', $url, [
            'headers' => $headers,
        ]);

        $statusCode = (int)$response->getStatusCode();
        $body = (string)$response->getBody();
        $data = $this->safeJsonDecode($body);

        if ($statusCode >= 400) {
            $this->logApiError('cancel', $statusCode, $data, $body);
            $message = isset($data['error']['message']) ? (string)$data['error']['message'] : 'Refund cancel failed';
            throw new \RuntimeException($message);
        }

        $status = strtolower((string)($data['status'] ?? ($data['data']['status'] ?? '')));
        $canceled = ($status === 'canceled' || $status === 'cancelled' || ($data['canceled'] ?? false));

        return [
            'canceled' => (bool)$canceled,
            'response' => is_array($data) ? $data : ['raw' => $body],
        ];
    }

    /**
     * @param string $paymentIntentId
     * @param int $amountCents
     * @param string $currency
     * @param array $metadata
     * @return string
     */
    private function buildIdempotencyKey(string $paymentIntentId, int $amountCents, string $currency, array $metadata): string
    {
        $parts = $paymentIntentId . '|' . $amountCents . '|' . $currency;
        if (isset($metadata['mode'])) {
            $parts .= '|' . (string)$metadata['mode'];
        }
        // Stable hash to ensure idempotency per (intent,amount,currency,mode)
        return 'magento-' . substr(sha1($parts), 0, 32);
    }

    /**
     * @param string $json
     * @return array
     */
    private function safeJsonDecode(string $json): array
    {
        try {
            $data = json_decode($json, true);
            return is_array($data) ? $data : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @param string $op
     * @param int $statusCode
     * @param array $data
     * @param string $raw
     * @return void
     */
    private function logApiError(string $op, int $statusCode, array $data, string $raw): void
    {
        $this->logger->error('Fintoc refund API error', [
            'operation' => $op,
            'status' => $statusCode,
            'error' => $data['error'] ?? null,
            'raw' => $raw,
        ]);
    }
}
