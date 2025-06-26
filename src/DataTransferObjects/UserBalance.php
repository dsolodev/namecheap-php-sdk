<?php

declare(strict_types=1);

namespace Namecheap\DataTransferObjects;

/**
 * User balance data transfer object
 */
readonly class UserBalance
{
    public function __construct(
        public float  $availableBalance,
        public string $currency,
        public float  $accountBalance,
        public float  $earnedAmount,
        public float  $withdrawableAmount,
        public float  $fundsRequiredForAutoRenew,
    ) {
    }
}
