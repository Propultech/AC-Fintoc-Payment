<?php

namespace Fintoc\Payment\Exceptions;

/**
 * Exception thrown when a webhook signature is invalid.
 */
class WebhookSignatureError extends FintocException
{
    /**
     * Create a new webhook signature error exception.
     *
     * @param string $message The exception message.
     * @param int $code The exception code.
     * @param \Throwable|null $previous The previous exception.
     * @param array $errorData Additional error data.
     */
    public function __construct(
        string $message = "Invalid webhook signature",
        int $code = 400,
        \Throwable $previous = null,
        array $errorData = []
    ) {
        parent::__construct($message, $code, $previous, $errorData);
    }
}
