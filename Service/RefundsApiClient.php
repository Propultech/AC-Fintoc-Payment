<?php
declare(strict_types=1);

namespace Fintoc\Payment\Service;

use Fintoc\Payment\Api\ConfigurationServiceInterface as PaymentConfigServiceInterface;
use Fintoc\Payment\Api\RefundsApiClientInterface;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Fintoc\Payment\Service\ConfigurationService;
use Fintoc\Payment\Utils\AmountUtils;

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
     * @param float|null $amount Amount to refund. Omit (null) for full refunds.
     * @param string $currency
     * @param array $metadata
     * @return array{external_id:string,status:string,response:array}
     * @throws GuzzleException
     */
    public function createRefund(string $paymentIntentId, ?float $amount, string $currency, array $metadata = []): array
    {
        $baseUrl = rtrim((string)$this->configService->getConfig('payment/fintoc_payment/api_base_url') ?: ConfigurationService::DEFAULT_API_BASE_URL, '/');
        $path = (string)$this->configService->getConfig('payment/fintoc_payment/refunds_create_path') ?: '/v1/refunds';
        $url = $baseUrl . $path;

        $secret = trim((string)$this->configService->getApiSecret());
        $normalizedMeta = $this->normalizeMetadata($metadata);
        $idempotency = $this->buildIdempotencyKey($paymentIntentId, $amount, $currency, $normalizedMeta);

        $payload = [
            'resource_type' => 'payment_intent',
            'resource_id' => $paymentIntentId,
        ];
        if ($amount !== null) {
            $payload['amount'] = AmountUtils::roundToIntHalfUp((float)$amount);
        }
        if (!empty($normalizedMeta)) {
            $payload['metadata'] = $normalizedMeta;
        }
        // Safety: do not send metadata if it somehow becomes an empty string or empty structure
        if (isset($payload['metadata']) && (!is_array($payload['metadata']) || $payload['metadata'] === [])) {
            unset($payload['metadata']);
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
        $baseUrl = rtrim((string)$this->configService->getConfig('payment/fintoc_payment/api_base_url') ?: ConfigurationService::DEFAULT_API_BASE_URL, '/');
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
     * Normalize metadata: ensure it's an associative array, stripping empty strings, nulls, and empty arrays.
     * Keeps numeric zeros and booleans.
     *
     * @param mixed $metadata
     * @return array
     */
    private function normalizeMetadata($metadata): array
    {
        // Ensure metadata is an associative array of string=>string pairs, no empties.
        // - Non-string scalars are cast to strings
        // - Arrays/objects are JSON-encoded strings
        // - Empty strings are dropped
        if (!is_array($metadata)) {
            return [];
        }
        $result = [];
        foreach ($metadata as $k => $v) {
            // Normalize key to string
            $key = (string)$k;
            if ($key === '') {
                continue;
            }

            // Handle nulls
            if ($v === null) {
                continue;
            }

            // For arrays/objects: JSON encode (single level in result)
            if (is_array($v) || is_object($v)) {
                // Remove empties recursively for cleaner JSON
                $clean = $this->normalizeMetadata((array)$v);
                if ($clean === []) {
                    continue;
                }
                $str = json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if (!is_string($str) || trim($str) === '') {
                    continue;
                }
                $result[$key] = $str;
                continue;
            }

            // Scalars: cast to string and trim
            if (is_bool($v)) {
                $str = $v ? 'true' : 'false';
            } else {
                $str = (string)$v;
            }
            $str = trim($str);
            if ($str === '') {
                continue;
            }
            $result[$key] = $str;
        }
        return $result;
    }

    /**
     * @param string $paymentIntentId
     * @param float|null $amount
     * @param string $currency
     * @param array $metadata
     * @return string
     */
    private function buildIdempotencyKey(string $paymentIntentId, ?float $amount, string $currency, array $metadata): string
    {
        $amountPart = $amount === null ? 'full' : (string)$amount;
        $parts = $paymentIntentId . '|' . $amountPart . '|' . $currency;
        if (isset($metadata['mode'])) {
            $parts .= '|' . (string)$metadata['mode'];
        }
        // Stable hash to ensure idempotency per (intent,amount/case,currency,mode)
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
