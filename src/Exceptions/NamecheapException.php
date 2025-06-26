<?php

declare(strict_types=1);

namespace Namecheap\Exceptions;

use Exception;
use Throwable;

/**
 * Base exception for all Namecheap SDK exceptions
 */
abstract class NamecheapException extends Exception
{
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null, private readonly array $context = [])
    {
        parent::__construct($message, $code, $previous);
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
