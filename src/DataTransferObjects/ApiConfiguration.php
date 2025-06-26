<?php

declare(strict_types=1);

namespace Namecheap\DataTransferObjects;

/**
 * API configuration data transfer object
 *
 * @param array<string, mixed> $curlOptions
 */
readonly class ApiConfiguration
{
    public function __construct(
        public string $apiUser,
        public string $apiKey,
        public string $userName,
        public string $clientIp,
        public bool   $sandbox = false,
        public int    $timeout = 30,
        /** @var array<string, mixed> */ public array $curlOptions = [],
    ) {
    }
}
