<?php

namespace Fintoc\Payment\Exceptions;

/**
 * Exception thrown when authentication fails.
 */
class AuthenticationException extends ApiException
{
    /**
     * Create a new authentication exception.
     *
     * @param string $message The exception message.
     * @param int $code The exception code.
     * @param \Throwable|null $previous The previous exception.
     * @param array $errorData Additional error data.
     * @param string|null $requestId The request ID.
     * @param string|null $method The HTTP method.
     * @param string|null $path The request path.
     * @param int $statusCode The HTTP status code.
     */
    public function __construct(
        string $message = "Authentication failed",
        int $code = 401,
        \Throwable $previous = null,
        array $errorData = [],
        string $requestId = null,
        string $method = null,
        string $path = null,
        int $statusCode = 401
    ) {
        parent::__construct($message, $code, $previous, $errorData, $requestId, $method, $path, $statusCode);
    }
}
