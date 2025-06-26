<?php

declare(strict_types=1);

namespace Namecheap\DataTransferObjects;

use DateTimeImmutable;
use Namecheap\Enums\DomainStatus;

/**
 * Domain information data transfer object
 */
readonly class Domain
{
    public function __construct(
        public string            $name,
        public DomainStatus      $status,
        public DateTimeImmutable $createdDate,
        public DateTimeImmutable $expirationDate,
        public bool              $autoRenew = false,
        public bool              $whoisGuard = false,
        public bool              $isPremium = false,
        public ?string           $registrar = null,
    ) {
    }
}
