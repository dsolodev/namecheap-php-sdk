<?php

declare(strict_types=1);

namespace Namecheap\DataTransferObjects;

/**
 * Domain pricing data transfer object
 */
readonly class DomainPricing
{
    public function __construct(
        public string $tld,
        public float  $registerPrice,
        public float  $renewPrice,
        public float  $transferPrice,
        public float  $restorePrice,
        public string $currency,
        public int    $yearsMin = 1,
        public int    $yearsMax = 10,
    ) {
    }
}
