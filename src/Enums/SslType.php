<?php

declare(strict_types=1);

namespace Namecheap\Enums;

/**
 * SSL certificate types
 */
enum SslType: string
{
    case POSITIVE_SSL = 'PositiveSSL';
    case POSITIVE_SSL_WILDCARD = 'PositiveSSL Wildcard';
    case ESSENTIAL_SSL = 'EssentialSSL';
    case ESSENTIAL_SSL_WILDCARD = 'EssentialSSL Wildcard';
    case INSTANT_SSL = 'InstantSSL';
    case INSTANT_SSL_PRO = 'InstantSSL Pro';
    case PREMIUM_SSL = 'PremiumSSL';
    case PREMIUM_SSL_WILDCARD = 'PremiumSSL Wildcard';
    case EV_SSL = 'EV SSL';
    case EV_MULTI_DOMAIN_SSL = 'EV Multi Domain SSL';
}
