<?php

declare(strict_types=1);

namespace Namecheap\Services;

use DateTimeImmutable;
use Exception;
use Namecheap\ApiClient;
use Namecheap\DataTransferObjects\ContactInfo;
use Namecheap\DataTransferObjects\Domain;
use Namecheap\Enums\DomainStatus;
use Namecheap\Enums\ResponseFormat;
use Namecheap\Exceptions\ApiException;
use Namecheap\Exceptions\AuthenticationException;
use Namecheap\Exceptions\NetworkException;
use Namecheap\Exceptions\ParseException;
use Namecheap\Exceptions\ValidationException;

use function is_array;
use function is_string;

/**
 * Domain management service
 */
readonly class DomainService
{
    public function __construct(
        private ApiClient $apiClient,
    ) {
    }

    /**
     * Get list of domains
     *
     * @throws ApiException
     * @throws ValidationException
     * @throws AuthenticationException
     * @throws NetworkException
     * @throws ParseException
     *
     * @return array<Domain>
     */
    public function getList(
        int     $page = 1,
        int     $pageSize = 20,
        ?string $searchTerm = null,
        ?string $sortBy = null,
    ): array {
        $parameters = [
            'PageSize' => $pageSize,
            'Page' => $page,
        ];

        if ($searchTerm !== null) {
            $parameters['SearchTerm'] = $searchTerm;
        }

        if ($sortBy !== null) {
            $parameters['SortBy'] = $sortBy;
        }

        $response = $this->apiClient->request(
            'namecheap.domains.getList',
            $parameters,
            ResponseFormat::ARRAY,
        );

        return $this->parseDomainList($response->data);
    }

    /**
     * Get domain information
     *
     * @throws ApiException
     * @throws AuthenticationException
     * @throws NetworkException
     * @throws ParseException
     * @throws ValidationException
     */
    public function getInfo(string $domainName): Domain
    {
        $parameters = [
            'DomainName' => $domainName,
        ];

        $response = $this->apiClient->request(
            'namecheap.domains.getInfo',
            $parameters,
            ResponseFormat::ARRAY,
        );

        return $this->parseDomainInfo($response->data);
    }

    /**
     * Get domain contacts
     *
     * @throws ApiException
     * @throws AuthenticationException
     * @throws NetworkException
     * @throws ParseException
     * @throws ValidationException
     *
     * @return array{registrant: ContactInfo, admin: ContactInfo, tech: ContactInfo, auxBilling: ContactInfo}
     */
    public function getContacts(string $domainName): array
    {
        $parameters = [
            'DomainName' => $domainName,
        ];

        $response = $this->apiClient->request(
            'namecheap.domains.getContacts',
            $parameters,
            ResponseFormat::ARRAY,
        );

        return $this->parseContactsInfo($response->data);
    }

    /**
     * Set domain contacts
     *
     * @throws ApiException
     * @throws AuthenticationException
     * @throws NetworkException
     * @throws ParseException
     * @throws ValidationException
     */
    public function setContacts(
        string      $domainName,
        ContactInfo $registrant,
        ContactInfo $admin,
        ContactInfo $tech,
        ContactInfo $auxBilling,
    ): bool {
        $parameters = [
            'DomainName' => $domainName,
            ...$this->buildContactParameters('Registrant', $registrant),
            ...$this->buildContactParameters('Admin', $admin),
            ...$this->buildContactParameters('Tech', $tech),
            ...$this->buildContactParameters('AuxBilling', $auxBilling),
        ];

        $response = $this->apiClient->request(
            'namecheap.domains.setContacts',
            $parameters,
            ResponseFormat::ARRAY,
        );

        return $response->success;
    }

    /**
     * Create (register) a new domain
     *
     * @param array<string, mixed> $additionalParameters
     *
     * @throws ApiException
     * @throws AuthenticationException
     * @throws NetworkException
     * @throws ParseException
     * @throws ValidationException
     */
    public function create(
        string      $domainName,
        int         $years,
        ContactInfo $registrant,
        ContactInfo $admin,
        ContactInfo $tech,
        ContactInfo $auxBilling,
        array       $additionalParameters = [],
    ): bool {
        $parameters = [
            'DomainName' => $domainName,
            'Years' => $years,
            ...$this->buildContactParameters('Registrant', $registrant),
            ...$this->buildContactParameters('Admin', $admin),
            ...$this->buildContactParameters('Tech', $tech),
            ...$this->buildContactParameters('AuxBilling', $auxBilling),
            ...$additionalParameters,
        ];

        $response = $this->apiClient->request(
            'namecheap.domains.create',
            $parameters,
            ResponseFormat::ARRAY,
        );

        return $response->success;
    }

    /**
     * Renew a domain
     *
     * @throws ApiException
     * @throws AuthenticationException
     * @throws NetworkException
     * @throws ParseException
     * @throws ValidationException
     */
    public function renew(string $domainName, int $years): bool
    {
        $parameters = [
            'DomainName' => $domainName,
            'Years' => $years,
        ];

        $response = $this->apiClient->request(
            'namecheap.domains.renew',
            $parameters,
            ResponseFormat::ARRAY,
        );

        return $response->success;
    }

    /**
     * Reactivate an expired domain
     *
     * @throws ApiException
     * @throws AuthenticationException
     * @throws NetworkException
     * @throws ParseException
     * @throws ValidationException
     */
    public function reactivate(string $domainName): bool
    {
        $parameters = [
            'DomainName' => $domainName,
        ];

        $response = $this->apiClient->request(
            'namecheap.domains.reactivate',
            $parameters,
            ResponseFormat::ARRAY,
        );

        return $response->success;
    }

    /**
     * Check domain availability
     *
     * @param array<string> $domainNames
     *
     * @throws ApiException
     * @throws AuthenticationException
     * @throws NetworkException
     * @throws ParseException
     * @throws ValidationException
     *
     * @return array<string, bool>
     */
    public function check(array $domainNames): array
    {
        if (empty($domainNames)) {
            throw new ValidationException('At least one domain name is required');
        }

        $parameters = [
            'DomainList' => implode(',', $domainNames),
        ];

        $response = $this->apiClient->request(
            'namecheap.domains.check',
            $parameters,
            ResponseFormat::ARRAY,
        );

        return $this->parseAvailabilityCheck($response->data);
    }

    /**
     * Parse domain list from API response
     *
     * @param array<string, mixed> $data
     *
     * @return array<Domain>
     */
    private function parseDomainList(array $data): array
    {
        $domains = [];
        $domainList = $data['DomainGetListResult'] ?? [];

        if (!is_array($domainList)) {
            return [];
        }

        if (isset($domainList['Domain'])) {
            $domainData = $domainList['Domain'];

            if (!is_array($domainData)) {
                return [];
            }

            // Handle single domain
            if (isset($domainData['@Name'])) {
                $domains[] = $this->createDomainFromArray($domainData);
            } else {
                // Handle multiple domains
                foreach ($domainData as $domain) {
                    if (is_array($domain) && isset($domain['@Name'])) {
                        $domains[] = $this->createDomainFromArray($domain);
                    }
                }
            }
        }

        return $domains;
    }

    /**
     * Create Domain object from array data
     *
     * @param array<string, mixed> $data
     */
    private function createDomainFromArray(array $data): Domain
    {
        $name = $this->getStringValue($data, '@Name', '@DomainName');
        $statusValue = $this->getStringValue($data, '@Status') ?: 'Active';
        $createdValue = $this->getStringValue($data, '@Created') ?: 'now';
        $expiresValue = $this->getStringValue($data, '@Expires') ?: 'now';
        $autoRenewValue = $this->getStringValue($data, '@AutoRenew') ?: 'false';
        $whoisGuardValue = $this->getStringValue($data, '@WhoisGuard') ?: 'DISABLED';
        $isPremiumValue = $this->getStringValue($data, '@IsPremium') ?: 'false';
        $registrarValue = $this->getStringValue($data, '@Registrar');

        try {
            $status = DomainStatus::from($statusValue);
        } catch (Exception) {
            $status = DomainStatus::ACTIVE;
        }

        try {
            $createdDate = new DateTimeImmutable($createdValue);
        } catch (Exception) {
            $createdDate = new DateTimeImmutable();
        }

        try {
            $expirationDate = new DateTimeImmutable($expiresValue);
        } catch (Exception) {
            $expirationDate = new DateTimeImmutable();
        }

        return new Domain(
            name          : $name,
            status        : $status,
            createdDate   : $createdDate,
            expirationDate: $expirationDate,
            autoRenew     : $autoRenewValue === 'true',
            whoisGuard    : $whoisGuardValue === 'ENABLED',
            isPremium     : $isPremiumValue === 'true',
            registrar     : $registrarValue,
        );
    }

    /**
     * Safely get string value from array with fallback keys
     *
     * @param array<string, mixed> $data
     */
    private function getStringValue(array $data, string $key, ?string $fallbackKey = null): string
    {
        $value = $data[$key] ?? null;

        if ($value === null && $fallbackKey !== null) {
            $value = $data[$fallbackKey] ?? null;
        }

        return is_string($value) ? $value : '';
    }

    /**
     * Parse domain info from API response
     *
     * @param array<string, mixed> $data
     *
     * @throws ApiException
     */
    private function parseDomainInfo(array $data): Domain
    {
        $domainInfo = $data['DomainGetInfoResult'] ?? [];

        if (!is_array($domainInfo)) {
            throw new ApiException('Invalid domain info response format');
        }

        return $this->createDomainFromArray($domainInfo);
    }

    /**
     * Parse contacts info from API response
     *
     * @param array<string, mixed> $data
     *
     * @throws ApiException
     *
     * @return array{registrant: ContactInfo, admin: ContactInfo, tech: ContactInfo, auxBilling: ContactInfo}
     */
    private function parseContactsInfo(array $data): array
    {
        $contactsData = $data['DomainContactsResult'] ?? [];

        if (!is_array($contactsData)) {
            throw new ApiException('Invalid contacts response format');
        }

        return [
            'registrant' => $this->createContactFromArray($contactsData, 'Registrant'),
            'admin' => $this->createContactFromArray($contactsData, 'Admin'),
            'tech' => $this->createContactFromArray($contactsData, 'Tech'),
            'auxBilling' => $this->createContactFromArray($contactsData, 'AuxBilling'),
        ];
    }

    /**
     * Create ContactInfo object from array data
     *
     * @param array<string, mixed> $data
     */
    private function createContactFromArray(array $data, string $type): ContactInfo
    {
        $firstName = $this->getStringValue($data, $type . 'FirstName');
        $lastName = $this->getStringValue($data, $type . 'LastName');
        $address1 = $this->getStringValue($data, $type . 'Address1');
        $city = $this->getStringValue($data, $type . 'City');
        $stateProvince = $this->getStringValue($data, $type . 'StateProvince');
        $postalCode = $this->getStringValue($data, $type . 'PostalCode');
        $country = $this->getStringValue($data, $type . 'Country');
        $phone = $this->getStringValue($data, $type . 'Phone');
        $email = $this->getStringValue($data, $type . 'EmailAddress');
        $address2 = $this->getStringValue($data, $type . 'Address2');
        $organization = $this->getStringValue($data, $type . 'Organization');
        $jobTitle = $this->getStringValue($data, $type . 'JobTitle');
        $fax = $this->getStringValue($data, $type . 'Fax');

        return new ContactInfo(
            firstName    : $firstName,
            lastName     : $lastName,
            address1     : $address1,
            city         : $city,
            stateProvince: $stateProvince,
            postalCode   : $postalCode,
            country      : $country,
            phone        : $phone,
            email        : $email,
            address2     : $address2,
            organization : $organization,
            jobTitle     : $jobTitle,
            fax          : $fax,
        );
    }

    /**
     * Build contact parameters for API request
     *
     * @return array<string, string>
     */
    private function buildContactParameters(string $type, ContactInfo $contact): array
    {
        return [
            $type . 'FirstName' => $contact->firstName,
            $type . 'LastName' => $contact->lastName,
            $type . 'Address1' => $contact->address1,
            $type . 'City' => $contact->city,
            $type . 'StateProvince' => $contact->stateProvince,
            $type . 'PostalCode' => $contact->postalCode,
            $type . 'Country' => $contact->country,
            $type . 'Phone' => $contact->phone,
            $type . 'EmailAddress' => $contact->email,
            $type . 'Address2' => $contact->address2 ?? '',
            $type . 'Organization' => $contact->organization ?? '',
            $type . 'JobTitle' => $contact->jobTitle ?? '',
            $type . 'Fax' => $contact->fax ?? '',
        ];
    }

    /**
     * Parse availability check results
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, bool>
     */
    private function parseAvailabilityCheck(array $data): array
    {
        $results = [];
        $checkResult = $data['DomainCheckResult'] ?? [];

        if (!is_array($checkResult)) {
            return [];
        }

        if (isset($checkResult['@Domain'])) {
            // Single domain
            $domain = $this->getStringValue($checkResult, '@Domain');
            $available = $this->getStringValue($checkResult, '@Available') === 'true';
            if ($domain !== '') {
                $results[$domain] = $available;
            }
        } else {
            // Multiple domains
            foreach ($checkResult as $domainData) {
                if (is_array($domainData)) {
                    $domain = $this->getStringValue($domainData, '@Domain');
                    $available = $this->getStringValue($domainData, '@Available') === 'true';
                    if ($domain !== '') {
                        $results[$domain] = $available;
                    }
                }
            }
        }

        return $results;
    }
}
