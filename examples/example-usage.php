<?php

declare(strict_types=1);

/**
 * Example usage of the modern Namecheap SDK for PHP 8.4
 */

require_once 'vendor/autoload.php';

use Namecheap\ApiClient;
use Namecheap\DataTransferObjects\ContactInfo;
use Namecheap\DataTransferObjects\DnsRecord;
use Namecheap\Enums\DnsRecordType;
use Namecheap\Enums\PricingType;
use Namecheap\Enums\SslType;
use Namecheap\Exceptions\ApiException;
use Namecheap\Exceptions\AuthenticationException;
use Namecheap\Exceptions\ValidationException;
use Namecheap\Services\DnsService;
use Namecheap\Services\DomainService;
use Namecheap\Services\SslService;
use Namecheap\Services\UserService;
use Psr\Log\NullLogger;

// Configuration
$apiUser = 'your_api_user';
$apiKey = 'your_api_key';
$userName = 'your_username';
$clientIp = '192.168.1.100';

try {
    // Create API client using factory method
    $client = ApiClient::create(
        apiUser    : $apiUser,
        apiKey     : $apiKey,
        userName   : $userName,
        clientIp   : $clientIp,
        sandbox    : true, // Enable sandbox for testing
        timeout    : 30,
        curlOptions: [
            CURLOPT_SSL_VERIFYPEER => false, // Only for development
        ],
        logger     : new NullLogger(),
    );

    // Example 1: Domain Management
    echo "=== Domain Management ===\n";

    $domainService = new DomainService($client);

    // Check domain availability
    $availability = $domainService->check(['example.com', 'test.org']);
    foreach ($availability as $domain => $available) {
        echo "Domain {$domain}: " . ($available ? 'Available' : 'Not Available') . "\n";
    }

    // Get domain list
    $domains = $domainService->getList(page: 1, pageSize: 10);
    echo "Found " . count($domains) . " domains\n";

    foreach ($domains as $domain) {
        echo "- {$domain->name} (Status: {$domain->status->value}, Expires: {$domain->expirationDate->format('Y-m-d')})\n";
    }

    // Example 2: DNS Management
    echo "\n=== DNS Management ===\n";

    $dnsService = new DnsService($client);

    // Get current DNS records
    $records = $dnsService->getHosts('example', 'com');
    echo "Current DNS records:\n";
    foreach ($records as $record) {
        echo "- {$record->type->value} {$record->name} -> {$record->value} (TTL: {$record->ttl})\n";
    }

    // Create new DNS records
    $newRecords = [
        new DnsRecord(
            type : DnsRecordType::A,
            name : 'www',
            value: '192.168.1.1',
            ttl  : 1800,
        ),
        new DnsRecord(
            type : DnsRecordType::CNAME,
            name : 'blog',
            value: 'www.example.com',
            ttl  : 3600,
        ),
        new DnsRecord(
            type  : DnsRecordType::MX,
            name  : '@',
            value : 'mail.example.com',
            ttl   : 1800,
            mxPref: 10,
        ),
    ];

    // Set DNS records
    $success = $dnsService->setHosts('example', 'com', $newRecords);
    echo "DNS records updated: " . ($success ? 'Success' : 'Failed') . "\n";

    // Example 3: SSL Certificate Management
    echo "\n=== SSL Certificate Management ===\n";

    $sslService = new SslService($client);

    // Get SSL certificate list
    $certificates = $sslService->getList();
    echo "SSL Certificates:\n";
    foreach ($certificates as $cert) {
        echo "- {$cert->commonName} ({$cert->type}) - Status: {$cert->status}\n";
    }

    // Create new SSL certificate (example CSR)
    $csr = "-----BEGIN CERTIFICATE REQUEST-----\nYour CSR content here\n-----END CERTIFICATE REQUEST-----";

    try {
        $certId = $sslService->create(
            type      : SslType::POSITIVE_SSL,
            years     : 1,
            csr       : $csr,
            adminEmail: 'admin@example.com',
            sanDomains: ['www.example.com', 'mail.example.com'],
        );
        echo "SSL Certificate created with ID: {$certId}\n";
    } catch (ValidationException $e) {
        echo "SSL creation validation error: {$e->getMessage()}\n";
    }

    // Example 4: User Account Management
    echo "\n=== User Account Management ===\n";

    $userService = new UserService($client);

    // Get account balance
    $balance = $userService->getBalances();
    echo "Account Balance: {$balance->availableBalance} {$balance->currency}\n";
    echo "Funds for Auto-Renew: {$balance->fundsRequiredForAutoRenew} {$balance->currency}\n";

    // Get domain pricing
    $pricing = $userService->getPricing(PricingType::DOMAIN);
    echo "Domain Pricing:\n";
    foreach (array_slice($pricing, 0, 5) as $price) { // Show first 5
        echo "- .{$price->tld}: Register {$price->registerPrice}, Renew {$price->renewPrice} {$price->currency}\n";
    }

    // Example 5: Contact Information Management
    echo "\n=== Contact Management ===\n";

    // Create contact information
    $contact = new ContactInfo(
        firstName    : 'John',
        lastName     : 'Doe',
        address1     : '123 Main Street',
        city         : 'Anytown',
        stateProvince: 'CA',
        postalCode   : '12345',
        country      : 'US',
        phone        : '+1.1234567890',
        email        : 'john.doe@example.com',
        organization : 'Example Corp',
    );

    // Get domain contacts
    $domainName = 'example.com';
    if (!empty($domains)) {
        $domainName = $domains[0]->name;

        try {
            $contacts = $domainService->getContacts($domainName);
            echo "Current contacts for {$domainName}:\n";
            echo "- Registrant: {$contacts['registrant']->firstName} {$contacts['registrant']->lastName}\n";
            echo "- Admin: {$contacts['admin']->email}\n";
        } catch (ApiException $e) {
            echo "Could not retrieve contacts: {$e->getMessage()}\n";
        }
    }

    // Example 6: Advanced Features with Modern PHP
    echo "\n=== Advanced Features ===\n";

    // Use match expression for response handling
    $handleResponse = function (bool $success, string $operation): string {
        return match ($success) {
            true => "✅ {$operation} completed successfully",
            false => "❌ {$operation} failed",
        };
    };

    echo $handleResponse(true, "Domain registration") . "\n";
    echo $handleResponse(false, "SSL activation") . "\n";

    // Use arrow function for data transformation
    $domainNames = array_map(
        fn (object $domain): string => $domain->name,
        $domains,
    );
    echo "Domain names: " . implode(', ', $domainNames) . "\n";

    // Use null coalescing assignment operator
    $config = [];
    $config['timeout'] ??= 30;
    $config['retries'] ??= 3;
    echo "Config: " . json_encode($config) . "\n";

} catch (AuthenticationException $e) {
    echo "Authentication failed: {$e->getMessage()}\n";
    echo "Context: " . json_encode($e->getContext()) . "\n";
} catch (ValidationException $e) {
    echo "Validation error: {$e->getMessage()}\n";
    echo "Errors: " . json_encode($e->getErrors()) . "\n";
} catch (ApiException $e) {
    echo "API error: {$e->getMessage()}\n";
    echo "API Error Code: {$e->getApiErrorCode()}\n";
} catch (Exception $e) {
    echo "Unexpected error: {$e->getMessage()}\n";
    echo "Trace: {$e->getTraceAsString()}\n";
}
