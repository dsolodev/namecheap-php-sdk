<?php

declare(strict_types = 1);

namespace Namecheap\Exceptions;

use Throwable;

/**
 * Exception thrown when response parsing fails
 */
class ParseException extends NamecheapException
{
    /**
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     * @param string|null $rawResponse
     * @param array<string, mixed> $context
     */
    public function __construct(
        string                   $message = 'Failed to parse response',
        int                      $code = 0,
        ?Throwable               $previous = null,
        private readonly ?string $rawResponse = null,
        array                    $context = [],
    ) {
        parent::__construct($message, $code, $previous, $context);
    }

    public function getRawResponse(): ?string
    {
        return $this->rawResponse;
    }
}
