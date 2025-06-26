# Modern Namecheap SDK for PHP 8.4

A modern, fully typed PHP SDK for the Namecheap API, built with PHP 8.4 features including strict typing, enums,
readonly classes, and contemporary coding standards.

*Built with inspiration from the original [NaturalBuild/namecheap-sdk](https://github.com/NaturalBuild/namecheap-sdk),
this is a complete rewrite designed for modern PHP development.*

[![PHP Version](https://img.shields.io/badge/php-%5E8.4-blue)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)
[![PHPStan Level](https://img.shields.io/badge/phpstan-level%20max-brightgreen)](phpstan.neon)

## ✨ Features

- **🚀 PHP 8.4 Ready**: Built with modern PHP features and strict typing
- **🔒 Type Safety**: Full type declarations, enums, and readonly properties
- **📦 PSR Standards**: PSR-4 autoloading, PSR-12 coding standards
- **🛡️ Exception Handling**: Comprehensive exception hierarchy with context
- **📊 Code Quality**: PHPStan Level max, Laravel Pint, Rector integration
- **🔄 Modern HTTP**: PSR-18 HTTP client support
- **📝 Rich DTOs**: Data Transfer Objects for type-safe data handling

## 🎯 Supported APIs

- **Domains**: Registration, renewal, transfer, management
- **DNS**: Record management, email forwarding, nameservers
- **SSL Certificates**: Purchase, activation, management
- **User Account**: Balance, pricing, account management
- **WhoisGuard**: Privacy protection management

## 📋 Requirements

- PHP 8.4 or higher
- cURL extension
- JSON extension
- SimpleXML extension

## 📦 Installation

Install via Composer:

```bash
composer require dsolodev/namecheap-sdk-modern
```

## 🚀 Quick Start

```php
<?php

declare(strict_types=1);

use Namecheap\ApiClient;
use Namecheap\Services\DomainService;
use Namecheap\Enums\ResponseFormat;

// Create API client
$client = ApiClient::create(
    apiUser: 'your_api_user',
    apiKey: 'your_api_key', 
    userName: 'your_username',
    clientIp: '192.168.1.100',
    sandbox: true // Use sandbox for testing
);

// Get domain service
$domainService = new DomainService($client);

// Check domain availability
$availability = $domainService->check(['example.com', 'test.org']);
foreach ($availability as $domain => $available) {
    echo "{$domain}: " . ($available ? 'Available' : 'Taken') . "\n";
}

// Get your domains
$domains = $domainService->getList();
foreach ($domains as $domain) {
    echo "{$domain->name} expires on {$domain->expirationDate->format('Y-m-d')}\n";
}
```

## 🔧 Configuration

### Basic Configuration

```php
use Namecheap\ApiClient;

$client = ApiClient::create(
    apiUser: 'your_api_user',
    apiKey: 'your_api_key',
    userName: 'your_username', 
    clientIp: '192.168.1.100',
    sandbox: false, // Production mode
    timeout: 30,
    curlOptions: [
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT => 'My App 1.0'
    ]
);
```

### Advanced Configuration with Logging

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Namecheap\DataTransferObjects\ApiConfiguration;

// Create logger
$logger = new Logger('namecheap');
$logger->pushHandler(new StreamHandler('namecheap.log', Logger::DEBUG));

// Create configuration
$config = new ApiConfiguration(
    apiUser: 'your_api_user',
    apiKey: 'your_api_key',
    userName: 'your_username',
    clientIp: '192.168.1.100',
    sandbox: true,
    timeout: 60,
    curlOptions: [CURLOPT_SSL_VERIFYPEER => false]
);

$client = new ApiClient($config, $logger);
```

## 📚 Usage Examples

### Domain Management

```php
use Namecheap\Services\DomainService;
use Namecheap\DataTransferObjects\ContactInfo;

$domainService = new DomainService($client);

// Create contact information
$contact = new ContactInfo(
    firstName: 'John',
    lastName: 'Doe', 
    address1: '123 Main St',
    city: 'Anytown',
    stateProvince: 'CA',
    postalCode: '12345',
    country: 'US',
    phone: '+1.1234567890',
    email: 'john@example.com'
);

// Register a domain
$success = $domainService->create(
    domainName: 'mynewdomain.com',
    years: 1,
    registrant: $contact,
    admin: $contact,
    tech: $contact,
    auxBilling: $contact
);

// Renew a domain
$success = $domainService->renew('example.com', 2);

// Get domain information
$domain = $domainService->getInfo('example.com');
echo "Domain expires: {$domain->expirationDate->format('Y-m-d')}";
```

### DNS Management

```php
use Namecheap\Services\DnsService;
use Namecheap\DataTransferObjects\DnsRecord;
use Namecheap\Enums\DnsRecordType;

$dnsService = new DnsService($client);

// Create DNS records
$records = [
    new DnsRecord(
        type: DnsRecordType::A,
        name: 'www',
        value: '192.168.1.1',
        ttl: 1800
    ),
    new DnsRecord(
        type: DnsRecordType::CNAME,
        name: 'blog', 
        value: 'www.example.com',
        ttl: 3600
    ),
    new DnsRecord(
        type: DnsRecordType::MX,
        name: '@',
        value: 'mail.example.com',
        ttl: 1800,
        mxPref: 10
    )
];

// Set DNS records
$success = $dnsService->setHosts('example', 'com', $records);

// Get current DNS records
$currentRecords = $dnsService->getHosts('example', 'com');
```

### SSL Certificate Management

```php
use Namecheap\Services\SslService;
use Namecheap\Enums\SslType;

$sslService = new SslService($client);

// Get SSL certificates
$certificates = $sslService->getList();

// Create new SSL certificate
$csr = "-----BEGIN CERTIFICATE REQUEST-----\n...\n-----END CERTIFICATE REQUEST-----";

$certId = $sslService->create(
    type: SslType::POSITIVE_SSL,
    years: 1,
    csr: $csr,
    adminEmail: 'admin@example.com',
    sanDomains: ['www.example.com']
);

// Activate certificate
$success = $sslService->activate(
    certificateId: $certId,
    csr: $csr,
    adminEmail: 'admin@example.com'
);
```

### User Account Management

```php
use Namecheap\Services\UserService;
use Namecheap\Enums\PricingType;

$userService = new UserService($client);

// Get account balance
$balance = $userService->getBalances();
echo "Available: {$balance->availableBalance} {$balance->currency}";

// Get domain pricing
$pricing = $userService->getPricing(PricingType::DOMAIN);
foreach ($pricing as $price) {
    echo ".{$price->tld}: Register {$price->registerPrice} {$price->currency}";
}
```

## 🔒 Exception Handling

The SDK provides a comprehensive exception hierarchy:

```php
use Namecheap\Exceptions\{
    NamecheapException,
    AuthenticationException, 
    ValidationException,
    ApiException,
    NetworkException,
    ParseException
};

try {
    $domains = $domainService->getList();
} catch (AuthenticationException $e) {
    echo "Authentication failed: {$e->getMessage()}";
    echo "Context: " . json_encode($e->getContext());
} catch (ValidationException $e) {
    echo "Validation errors: " . json_encode($e->getErrors());
} catch (ApiException $e) {
    echo "API error: {$e->getMessage()}";
    echo "API code: {$e->getApiErrorCode()}";
} catch (NetworkException $e) {
    echo "Network error: {$e->getMessage()}";
} catch (NamecheapException $e) {
    echo "SDK error: {$e->getMessage()}";
}
```

## 🔍 Code Quality

The project includes comprehensive code quality tools:

```bash
# Run PHPStan analysis
composer phpstan

# Fix code style
composer cs-fix

# Check code style
composer cs-check

# Run Rector for PHP upgrades
composer rector

# Run all quality checks
composer quality
```

## 📝 Configuration Files

The SDK includes modern configuration files:

- `composer.json` - Dependencies and scripts
- `pint.json` - Laravel Pint code style rules
- `phpstan.neon` - PHPStan configuration
- `rector.php` - Rector upgrade rules

## 🌟 Modern PHP Features Used

- **Strict Types**: `declare(strict_types=1)` throughout
- **Readonly Classes**: Immutable DTOs
- **Enums**: Type-safe constants
- **Constructor Property Promotion**: Concise constructors
- **Named Arguments**: Clear method calls
- **Match Expressions**: Modern conditionals
- **Union Types**: Flexible type declarations
- **Attributes**: Modern annotations for metadata

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes following coding standards
4. Run quality checks: `composer quality`
5. Submit a pull request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- Original [NaturalBuild/namecheap-sdk](https://github.com/NaturalBuild/namecheap-sdk) for API structure reference
- PHP community for modern language features
- Contributors and maintainers

## 📞 Support

- [GitHub Issues](https://github.com/NaturalBuild/namecheap-sdk-modern/issues)
- [Namecheap API Documentation](https://www.namecheap.com/support/api/)
- [PHP 8.4 Documentation](https://php.net/releases/8.4/)