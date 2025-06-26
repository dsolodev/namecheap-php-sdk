<?php

declare(strict_types=1);

namespace Namecheap\DataTransferObjects;

/**
 * Contact information data transfer object
 */
readonly class ContactInfo
{
    public function __construct(
        public string  $firstName,
        public string  $lastName,
        public string  $address1,
        public string  $city,
        public string  $stateProvince,
        public string  $postalCode,
        public string  $country,
        public string  $phone,
        public string  $email,
        public ?string $address2 = null,
        public ?string $organization = null,
        public ?string $jobTitle = null,
        public ?string $fax = null,
    ) {
    }
}
