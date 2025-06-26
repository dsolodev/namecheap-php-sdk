<?php

declare(strict_types=1);

namespace Namecheap\DataTransferObjects;

/**
 * API response data transfer object
 *
 * @param array<string, mixed> $data
 * @param array<string>        $errors
 * @param array<string>        $warnings
 */
readonly class ApiResponse
{
    public function __construct(
        public bool    $success,
        public string  $command,
        /** @var array<string, mixed> */ public array $data,
        /** @var array<string> */ public array $errors = [],
        /** @var array<string> */ public array $warnings = [],
        public ?string $server = null,
        public ?float  $executionTime = null,
        public ?string $gmtTimeDifference = null,
    ) {
    }
}
