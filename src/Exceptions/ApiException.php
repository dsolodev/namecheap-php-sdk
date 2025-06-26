<?php

declare(strict_types=1);

namespace Namecheap\Exceptions;

use Throwable;

/**
 * Exception thrown when API returns an error response
 */
class ApiException extends NamecheapException
{
    public function __construct(
        string                   $message = 'API request failed',
        int                      $code = 500,
        ?Throwable               $previous = null,
        private readonly ?string $apiErrorCode = null,
        array                    $context = [],
    ) {
        parent::__construct($message, $code, $previous, $context);
    }

    public function getApiErrorCode(): ?string
    {
        return $this->apiErrorCode;
    }
}
