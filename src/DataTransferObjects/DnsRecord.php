<?php

declare(strict_types=1);

namespace Namecheap\DataTransferObjects;

use Namecheap\Enums\DnsRecordType;

/**
 * DNS record data transfer object
 */
readonly class DnsRecord
{
    public function __construct(
        public DnsRecordType $type,
        public string        $name,
        public string        $value,
        public int           $ttl = 1800,
        public ?int          $mxPref = null,
        public ?int          $priority = null,
        public ?int          $weight = null,
        public ?int          $port = null,
        public ?string       $target = null,
    ) {
    }
}
