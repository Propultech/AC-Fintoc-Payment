<?php

namespace Fintoc\Payment\Exceptions;

/**
 * Exception thrown when a resource is not found.
 */
class ResourceNotFoundException extends ApiException
{
    /**
     * @var string|null The resource type.
     */
    protected $resourceType;

    /**
     * @var string|null The resource parameter.
     */
    protected $resourceParam;

    /**
     * Create a new resource not found exception.
     *
     * @param string|null $resourceType The resource type.
     * @param string|null $resourceParam The resource parameter.
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
        string $resourceType = null,
        string $resourceParam = null,
        string $message = "Resource not found",
        int $code = 404,
        \Throwable $previous = null,
        array $errorData = [],
        string $requestId = null,
        string $method = null,
        string $path = null,
        int $statusCode = 404
    ) {
        parent::__construct($message, $code, $previous, $errorData, $requestId, $method, $path, $statusCode);
        $this->resourceType = $resourceType;
        $this->resourceParam = $resourceParam;
    }

    /**
     * Get the resource type.
     *
     * @return string|null The resource type.
     */
    public function getResourceType(): ?string
    {
        return $this->resourceType;
    }

    /**
     * Get the resource parameter.
     *
     * @return string|null The resource parameter.
     */
    public function getResourceParam(): ?string
    {
        return $this->resourceParam;
    }
}
