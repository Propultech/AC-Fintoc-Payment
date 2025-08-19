<?php

namespace Fintoc\Payment\Exceptions;

/**
 * Exception thrown when an API error occurs.
 */
class ApiException extends FintocException
{
    /**
     * @var string|null The request ID.
     */
    protected $requestId;

    /**
     * @var string|null The HTTP method.
     */
    protected $method;

    /**
     * @var string|null The request path.
     */
    protected $path;

    /**
     * @var int|null The HTTP status code.
     */
    protected $statusCode;

    /**
     * Create a new API exception.
     *
     * @param string $message The exception message.
     * @param int $code The exception code.
     * @param \Throwable|null $previous The previous exception.
     * @param array $errorData Additional error data.
     * @param string|null $requestId The request ID.
     * @param string|null $method The HTTP method.
     * @param string|null $path The request path.
     * @param int|null $statusCode The HTTP status code.
     */
    public function __construct(
        string $message = "An error occurred with the Fintoc API",
        int $code = 0,
        \Throwable $previous = null,
        array $errorData = [],
        string $requestId = null,
        string $method = null,
        string $path = null,
        int $statusCode = null
    ) {
        parent::__construct($message, $code, $previous, $errorData);
        $this->requestId = $requestId;
        $this->method = $method;
        $this->path = $path;
        $this->statusCode = $statusCode;
    }

    /**
     * Get the request ID.
     *
     * @return string|null The request ID.
     */
    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    /**
     * Get the HTTP method.
     *
     * @return string|null The HTTP method.
     */
    public function getMethod(): ?string
    {
        return $this->method;
    }

    /**
     * Get the request path.
     *
     * @return string|null The request path.
     */
    public function getPath(): ?string
    {
        return $this->path;
    }

    /**
     * Get the HTTP status code.
     *
     * @return int|null The HTTP status code.
     */
    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }
}
