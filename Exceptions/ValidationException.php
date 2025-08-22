<?php

namespace Fintoc\Payment\Exceptions;

use Throwable;

/**
 * Exception thrown when validation fails.
 */
class ValidationException extends FintocException
{
    /**
     * @var array The validation errors.
     */
    protected $errors = [];

    /**
     * Create a new validation exception.
     *
     * @param string $message The exception message.
     * @param array $errors The validation errors.
     * @param int $code The exception code.
     * @param Throwable|null $previous The previous exception.
     * @param array $errorData Additional error data.
     */
    public function __construct(
        string     $message = "Validation failed",
        array      $errors = [],
        int        $code = 0,
        Throwable $previous = null,
        array      $errorData = []
    ) {
        parent::__construct($message, $code, $previous, $errorData);
        $this->errors = $errors;
    }

    /**
     * Get the validation errors.
     *
     * @return array The validation errors.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Check if there are errors for a specific field.
     *
     * @param string $field The field name.
     * @return bool Whether there are errors for the field.
     */
    public function hasErrorsFor(string $field): bool
    {
        return isset($this->errors[$field]);
    }

    /**
     * Get the errors for a specific field.
     *
     * @param string $field The field name.
     * @return array The errors for the field.
     */
    public function getErrorsFor(string $field): array
    {
        return $this->errors[$field] ?? [];
    }
}
