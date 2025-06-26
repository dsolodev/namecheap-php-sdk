<?php

declare(strict_types = 1);

namespace Namecheap\Exceptions;

use Exception;
use Throwable;

/**
 * Base exception for all Namecheap SDK exceptions
 */
abstract class NamecheapException extends Exception
{
    /**
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     * @param array<string, mixed> $context
     */
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null, private readonly array $context = [])
    {
        parent::__construct($message, $code, $previous);
    }

    /** @return array<string, mixed> */
    public function getContext(): array
    {
        return $this->context;
    }
}
