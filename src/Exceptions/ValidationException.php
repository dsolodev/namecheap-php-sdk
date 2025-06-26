<?php

declare(strict_types = 1);

namespace Namecheap\Exceptions;

use Throwable;

/**
 * Exception thrown when API request validation fails
 */
class ValidationException extends NamecheapException
{
    /**
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     * @param array<string> $errors
     * @param array<string, mixed> $context
     */
    public function __construct(
        string                 $message = 'Validation failed',
        int                    $code = 400,
        ?Throwable             $previous = null,
        private readonly array $errors = [],
        array                  $context = [],
    ) {
        parent::__construct($message, $code, $previous, $context);
    }

    /** @return array<string> */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
