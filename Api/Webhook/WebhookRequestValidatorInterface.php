<?php
declare(strict_types=1);

namespace Fintoc\Payment\Api\Webhook;

interface WebhookRequestValidatorInterface
{
    /**
     * Validate signature and required headers; throw \InvalidArgumentException on error.
     * @param string $rawBody
     * @param array $headers
     */
    public function validate(string $rawBody, array $headers): void;
}
