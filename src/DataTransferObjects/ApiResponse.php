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
        public array   $data,
        public array   $errors = [],
        public array   $warnings = [],
        public ?string $server = null,
        public ?float  $executionTime = null,
        public ?string $gmtTimeDifference = null,
    ) {
    }
}
