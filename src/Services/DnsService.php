<?php

declare(strict_types=1);

namespace Namecheap\Services;

use Exception;
use Namecheap\ApiClient;
use Namecheap\DataTransferObjects\DnsRecord;
use Namecheap\Enums\DnsRecordType;
use Namecheap\Enums\ResponseFormat;
use Namecheap\Exceptions\ApiException;
use Namecheap\Exceptions\AuthenticationException;
use Namecheap\Exceptions\NetworkException;
use Namecheap\Exceptions\ParseException;
use Namecheap\Exceptions\ValidationException;

use function count;
use function is_array;
use function is_int;
use function is_string;

/**
 * DNS management service
 */
readonly class DnsService
{
    public function __construct(
        private ApiClient $apiClient,
    ) {
    }

    /**
     * Get DNS records for a domain
     *
     * @throws ValidationException
     * @throws AuthenticationException
     * @throws NetworkException
     * @throws ParseException
     * @throws ApiException
     *
     * @return array<DnsRecord>
     */
    public function getHosts(string $domain, string $tld): array
    {
        $parameters = [
            'SLD' => $domain,
            'TLD' => $tld,
        ];

        $response = $this->apiClient->request(
            'namecheap.domains.dns.getHosts',
            $parameters,
            ResponseFormat::ARRAY,
        );

        return $this->parseDnsRecords($response->data);
    }

    /**
     * Set DNS records for a domain
     *
     * @param array<DnsRecord> $records
     *
     * @throws ApiException
     * @throws AuthenticationException
     * @throws NetworkException
     * @throws ParseException
     * @throws ValidationException
     */
    public function setHosts(string $domain, string $tld, array $records): bool
    {
        if (empty($records)) {
            throw new ValidationException('At least one DNS record is required');
        }

        $parameters = [
            'SLD' => $domain,
            'TLD' => $tld,
            ...$this->buildHostParameters($records),
        ];

        $response = $this->apiClient->request(
            'namecheap.domains.dns.setHosts',
            $parameters,
            ResponseFormat::ARRAY,
        );

        return $response->success;
    }

    /**
     * Get email forwarding settings
     *
     * @throws AuthenticationException
     * @throws NetworkException
     * @throws ParseException
     * @throws ValidationException
     * @throws ApiException
     *
     * @return array<string, string>
     */
    public function getEmailForwarding(string $domain, string $tld): array
    {
        $parameters = [
            'DomainName' => $domain . '.' . $tld,
        ];

        $response = $this->apiClient->request(
            'namecheap.domains.dns.getEmailForwarding',
            $parameters,
            ResponseFormat::ARRAY,
        );

        return $this->parseEmailForwarding($response->data);
    }

    /**
     * Set email forwarding
     *
     * @param array<string, string> $forwards Map of mailbox to forward address
     *
     * @throws ApiException
     * @throws AuthenticationException
     * @throws NetworkException
     * @throws ParseException
     * @throws ValidationException
     */
    public function setEmailForwarding(string $domain, string $tld, array $forwards): bool
    {
        $parameters = [
            'DomainName' => $domain . '.' . $tld,
            ...$this->buildEmailForwardingParameters($forwards),
        ];

        $response = $this->apiClient->request(
            'namecheap.domains.dns.setEmailForwarding',
            $parameters,
            ResponseFormat::ARRAY,
        );

        return $response->success;
    }

    /**
     * Set default DNS servers
     *
     * @throws ApiException
     * @throws AuthenticationException
     * @throws NetworkException
     * @throws ParseException
     * @throws ValidationException
     */
    public function setDefault(string $domain, string $tld): bool
    {
        $parameters = [
            'SLD' => $domain,
            'TLD' => $tld,
        ];

        $response = $this->apiClient->request(
            'namecheap.domains.dns.setDefault',
            $parameters,
            ResponseFormat::ARRAY,
        );

        return $response->success;
    }

    /**
     * Get list of DNS servers
     *
     * @throws AuthenticationException
     * @throws NetworkException
     * @throws ParseException
     * @throws ValidationException
     * @throws ApiException
     *
     * @return array<string>
     */
    public function getList(string $domain, string $tld): array
    {
        $parameters = [
            'SLD' => $domain,
            'TLD' => $tld,
        ];

        $response = $this->apiClient->request(
            'namecheap.domains.dns.getList',
            $parameters,
            ResponseFormat::ARRAY,
        );

        return $this->parseDnsServerList($response->data);
    }

    /**
     * Set custom DNS servers
     *
     * @param array<string> $nameservers
     *
     * @throws ApiException
     * @throws AuthenticationException
     * @throws NetworkException
     * @throws ParseException
     * @throws ValidationException
     */
    public function setCustom(string $domain, string $tld, array $nameservers): bool
    {
        if (empty($nameservers)) {
            throw new ValidationException('At least one nameserver is required');
        }

        if (count($nameservers) > 12) {
            throw new ValidationException('Maximum 12 nameservers allowed');
        }

        $parameters = [
            'SLD' => $domain,
            'TLD' => $tld,
            'Nameservers' => implode(',', $nameservers),
        ];

        $response = $this->apiClient->request(
            'namecheap.domains.dns.setCustom',
            $parameters,
            ResponseFormat::ARRAY,
        );

        return $response->success;
    }

    /**
     * Parse DNS records from API response
     *
     * @param array<string, mixed> $data
     *
     * @return array<DnsRecord>
     */
    private function parseDnsRecords(array $data): array
    {
        $records = [];
        $hostResult = $data['DomainDNSGetHostsResult'] ?? [];

        if (!is_array($hostResult)) {
            return [];
        }

        if (isset($hostResult['host'])) {
            $hostData = $hostResult['host'];

            if (!is_array($hostData)) {
                return [];
            }

            // Handle single record
            if (isset($hostData['@HostId'])) {
                $record = $this->createDnsRecordFromArray($hostData);
                if ($record !== null) {
                    $records[] = $record;
                }
            } else {
                // Handle multiple records
                foreach ($hostData as $host) {
                    if (is_array($host) && isset($host['@HostId'])) {
                        $record = $this->createDnsRecordFromArray($host);
                        if ($record !== null) {
                            $records[] = $record;
                        }
                    }
                }
            }
        }

        return $records;
    }

    /**
     * Create DnsRecord object from array data
     *
     * @param array<string, mixed> $data
     */
    private function createDnsRecordFromArray(array $data): ?DnsRecord
    {
        $typeValue = $this->getStringValue($data, '@Type');
        $name = $this->getStringValue($data, '@Name');
        $value = $this->getStringValue($data, '@Address');
        $ttlValue = $this->getStringValue($data, '@TTL') ?: '1800';

        if ($typeValue === '' || $name === '' || $value === '') {
            return null;
        }

        try {
            $type = DnsRecordType::from($typeValue);
        } catch (Exception) {
            return null;
        }

        $ttl = is_numeric($ttlValue) ? (int)$ttlValue : 1800;
        $mxPref = $this->getIntValue($data, '@MXPref');
        $priority = $this->getIntValue($data, '@Priority');
        $weight = $this->getIntValue($data, '@Weight');
        $port = $this->getIntValue($data, '@Port');
        $target = $this->getStringValue($data, '@Target') ?: null;

        return new DnsRecord(
            type    : $type,
            name    : $name,
            value   : $value,
            ttl     : $ttl,
            mxPref  : $mxPref,
            priority: $priority,
            weight  : $weight,
            port    : $port,
            target  : $target,
        );
    }

    /**
     * Safely get string value from array
     *
     * @param array<string, mixed> $data
     */
    private function getStringValue(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? $value : '';
    }

    /**
     * Safely get integer value from array
     *
     * @param array<string, mixed> $data
     */
    private function getIntValue(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int)$value;
        }

        return null;
    }

    /**
     * Build host parameters for API request
     *
     * @param array<DnsRecord> $records
     *
     * @return array<string, mixed>
     */
    private function buildHostParameters(array $records): array
    {
        $parameters = [];

        foreach ($records as $index => $record) {
            $hostNum = $index + 1;

            $parameters["HostName{$hostNum}"] = $record->name;
            $parameters["RecordType{$hostNum}"] = $record->type->value;
            $parameters["Address{$hostNum}"] = $record->value;
            $parameters["TTL{$hostNum}"] = $record->ttl;

            if ($record->mxPref !== null) {
                $parameters["MXPref{$hostNum}"] = $record->mxPref;
            }

            if ($record->priority !== null) {
                $parameters["Priority{$hostNum}"] = $record->priority;
            }

            if ($record->weight !== null) {
                $parameters["Weight{$hostNum}"] = $record->weight;
            }

            if ($record->port !== null) {
                $parameters["Port{$hostNum}"] = $record->port;
            }

            if ($record->target !== null) {
                $parameters["Target{$hostNum}"] = $record->target;
            }
        }

        return $parameters;
    }

    /**
     * Parse email forwarding settings
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, string>
     */
    private function parseEmailForwarding(array $data): array
    {
        $forwarding = [];
        $forwardingResult = $data['DomainDNSGetEmailForwardingResult'] ?? [];

        if (!is_array($forwardingResult)) {
            return [];
        }

        if (isset($forwardingResult['Forward'])) {
            $forwardData = $forwardingResult['Forward'];

            if (!is_array($forwardData)) {
                return [];
            }

            // Handle single forward
            if (isset($forwardData['@mailbox'])) {
                $mailbox = $this->getStringValue($forwardData, '@mailbox');
                $forwardTo = $this->getStringValue($forwardData, '$');
                if ($mailbox !== '') {
                    $forwarding[$mailbox] = $forwardTo;
                }
            } else {
                // Handle multiple forwards
                foreach ($forwardData as $forward) {
                    if (is_array($forward)) {
                        $mailbox = $this->getStringValue($forward, '@mailbox');
                        $forwardTo = $this->getStringValue($forward, '$');
                        if ($mailbox !== '') {
                            $forwarding[$mailbox] = $forwardTo;
                        }
                    }
                }
            }
        }

        return $forwarding;
    }

    /**
     * Build email forwarding parameters
     *
     * @param array<string, string> $forwards
     *
     * @return array<string, string>
     */
    private function buildEmailForwardingParameters(array $forwards): array
    {
        $parameters = [];
        $index = 1;

        foreach ($forwards as $mailbox => $forwardTo) {
            $parameters["MailBox{$index}"] = $mailbox;
            $parameters["ForwardTo{$index}"] = $forwardTo;
            $index++;
        }

        return $parameters;
    }

    /**
     * Parse DNS server list
     *
     * @param array<string, mixed> $data
     *
     * @return array<string>
     */
    private function parseDnsServerList(array $data): array
    {
        $servers = [];
        $dnsResult = $data['DomainDNSGetListResult'] ?? [];

        if (!is_array($dnsResult)) {
            return [];
        }

        $isUsingOurDns = $this->getStringValue($dnsResult, '@IsUsingOurDNS');
        if ($isUsingOurDns === 'true') {
            return ['default']; // Using Namecheap DNS
        }

        if (isset($dnsResult['Nameserver'])) {
            $nsData = $dnsResult['Nameserver'];

            // Handle single nameserver
            if (is_string($nsData)) {
                $servers[] = $nsData;
            } elseif (is_array($nsData)) {
                // Handle multiple nameservers
                foreach ($nsData as $ns) {
                    if (is_string($ns)) {
                        $servers[] = $ns;
                    }
                }
            }
        }

        return $servers;
    }
}
