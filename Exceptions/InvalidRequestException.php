<?php

namespace Fintoc\Payment\Exceptions;

use Throwable;

/**
 * Exception thrown when a request is invalid.
 */
class InvalidRequestException extends ApiException
{
    /**
     * @var string|null The invalid parameter.
     */
    protected $param;

    /**
     * Create a new invalid request exception.
     *
     * @param string|null $param The invalid parameter.
     * @param string $message The exception message.
     * @param int $code The exception code.
     * @param Throwable|null $previous The previous exception.
     * @param array $errorData Additional error data.
     * @param string|null $requestId The request ID.
     * @param string|null $method The HTTP method.
     * @param string|null $path The request path.
     * @param int|null $statusCode The HTTP status code.
     */
    public function __construct(
        string     $param = null,
        string     $message = "Invalid request",
        int        $code = 400,
        Throwable $previous = null,
        array      $errorData = [],
        string     $requestId = null,
        string     $method = null,
        string     $path = null,
        int        $statusCode = 400
    ) {
        parent::__construct($message, $code, $previous, $errorData, $requestId, $method, $path, $statusCode);
        $this->param = $param;
    }

    /**
     * Get the invalid parameter.
     *
     * @return string|null The invalid parameter.
     */
    public function getParam(): ?string
    {
        return $this->param;
    }
}
