<?php

declare(strict_types=1);

namespace Namecheap\Exceptions;

use Throwable;

/**
 * Exception thrown when API authentication fails
 */
class AuthenticationException extends NamecheapException
{
    public function __construct(
        string     $message = 'Authentication failed',
        int        $code = 401,
        ?Throwable $previous = null,
        array      $context = [],
    ) {
        parent::__construct($message, $code, $previous, $context);
    }
}
