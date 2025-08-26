<?php

namespace Fintoc\Payment\Exceptions;

use Throwable;

/**
 * Exception thrown when the API rate limit is exceeded.
 */
class RateLimitException extends ApiException
{
    /**
     * @var int|null The rate limit.
     */
    protected $rateLimit;

    /**
     * @var int|null The remaining rate limit.
     */
    protected $rateRemaining;

    /**
     * @var int|null The rate limit reset time.
     */
    protected $rateReset;

    /**
     * Create a new rate limit exception.
     *
     * @param int|null $rateLimit The rate limit.
     * @param int|null $rateRemaining The remaining rate limit.
     * @param int|null $rateReset The rate limit reset time.
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
        int        $rateLimit = null,
        int        $rateRemaining = null,
        int        $rateReset = null,
        string     $message = "API rate limit exceeded",
        int        $code = 429,
        Throwable $previous = null,
        array      $errorData = [],
        string     $requestId = null,
        string     $method = null,
        string     $path = null,
        int        $statusCode = 429
    ) {
        parent::__construct($message, $code, $previous, $errorData, $requestId, $method, $path, $statusCode);
        $this->rateLimit = $rateLimit;
        $this->rateRemaining = $rateRemaining;
        $this->rateReset = $rateReset;
    }

    /**
     * Get the rate limit.
     *
     * @return int|null The rate limit.
     */
    public function getRateLimit(): ?int
    {
        return $this->rateLimit;
    }

    /**
     * Get the remaining rate limit.
     *
     * @return int|null The remaining rate limit.
     */
    public function getRateRemaining(): ?int
    {
        return $this->rateRemaining;
    }

    /**
     * Get the rate limit reset time.
     *
     * @return int|null The rate limit reset time.
     */
    public function getRateReset(): ?int
    {
        return $this->rateReset;
    }

    /**
     * Get the time until the rate limit resets.
     *
     * @return int|null The time until the rate limit resets in seconds.
     */
    public function getTimeUntilReset(): ?int
    {
        if ($this->rateReset === null) {
            return null;
        }

        return max(0, $this->rateReset - time());
    }
}
