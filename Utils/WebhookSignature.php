<?php

namespace Fintoc\Payment\Utils;

use Fintoc\Payment\Exceptions\WebhookSignatureError;

/**
 * Utility class for validating webhook signatures.
 */
class WebhookSignature
{
    /**
     * Verify a webhook signature.
     *
     * @param string $payload The raw request body.
     * @param string $header The Fintoc-Signature header value.
     * @param string $secret The webhook secret.
     * @param int $tolerance The number of seconds to tolerate when checking timestamp (default: 300).
     * @return bool Whether the signature is valid.
     * @throws WebhookSignatureError If the signature is invalid or the timestamp is outside the tolerance window.
     */
    public static function verifyHeader(string $payload, string $header, string $secret, int $tolerance = 300): bool
    {
        // Parse the header
        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            $part = trim($part);
            if (strpos($part, 't=') === 0) {
                $timestamp = substr($part, 2);
            } elseif (strpos($part, 'v1=') === 0) {
                $signatures[] = substr($part, 3);
            }
        }

        if (!$timestamp || empty($signatures)) {
            throw new WebhookSignatureError('Invalid signature header');
        }

        // Check the timestamp
        $now = time();
        if ($now - $timestamp > $tolerance) {
            throw new WebhookSignatureError('Timestamp outside the tolerance window');
        }

        // Verify the signature
        $signedPayload = "{$timestamp}.{$payload}";
        $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expectedSignature, $signature)) {
                return true;
            }
        }

        throw new WebhookSignatureError('Invalid signature');
    }
}
