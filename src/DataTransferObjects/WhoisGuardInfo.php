<?php

declare(strict_types=1);

namespace Namecheap\DataTransferObjects;

use DateTimeImmutable;

/**
 * WhoisGuard information data transfer object
 */
readonly class WhoisGuardInfo
{
    public function __construct(
        public string            $id,
        public string            $domainName,
        public string            $status,
        public DateTimeImmutable $createdDate,
        public DateTimeImmutable $expirationDate,
        public string            $forwardedToEmail,
        public bool              $enabled = true,
    ) {
    }
}
