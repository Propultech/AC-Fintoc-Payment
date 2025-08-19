<?php

namespace Fintoc\Payment\Exceptions;

/**
 * Base exception class for all Fintoc exceptions.
 */
class FintocException extends \Exception
{
    /**
     * @var array Additional error data.
     */
    protected $errorData;

    /**
     * Create a new Fintoc exception.
     *
     * @param string $message The exception message.
     * @param int $code The exception code.
     * @param \Throwable|null $previous The previous exception.
     * @param array $errorData Additional error data.
     */
    public function __construct(
        string $message = "An error occurred with the Fintoc API",
        int $code = 0,
        \Throwable $previous = null,
        array $errorData = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->errorData = $errorData;
    }

    /**
     * Get the additional error data.
     *
     * @return array The error data.
     */
    public function getErrorData(): array
    {
        return $this->errorData;
    }
}
