<?php

declare(strict_types=1);

namespace Namecheap\Exceptions;

use Throwable;

/**
 * Exception thrown when network request fails
 */
class NetworkException extends NamecheapException
{
    public function __construct(
        string     $message = 'Network request failed',
        int        $code = 0,
        ?Throwable $previous = null,
        array      $context = [],
    ) {
        parent::__construct($message, $code, $previous, $context);
    }
}
