<?php

declare(strict_types=1);

namespace Namecheap\DataTransferObjects;

use DateTimeImmutable;

/**
 * SSL certificate data transfer object
 *
 * @param array<string> $sanDomains
 */
readonly class SslCertificate
{
    public function __construct(
        public string            $certificateId,
        public string            $type,
        public string            $status,
        public DateTimeImmutable $createdDate,
        public DateTimeImmutable $expirationDate,
        public string            $commonName,
        /** @var array<string> */ public array $sanDomains = [],
        public ?string           $organizationName = null,
        public ?string           $organizationUnit = null,
        public ?string           $locality = null,
        public ?string           $stateProvince = null,
        public ?string           $country = null,
    ) {
    }
}
