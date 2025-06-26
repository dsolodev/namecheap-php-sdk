<?php

declare(strict_types=1);

namespace Namecheap\Enums;

/**
 * Pricing types
 */
enum PricingType: string
{
    case DOMAIN = 'DOMAIN';
    case DOMAIN_REGISTER = 'DOMAIN_REGISTER';
    case DOMAIN_RENEW = 'DOMAIN_RENEW';
    case DOMAIN_REACTIVATE = 'DOMAIN_REACTIVATE';
    case DOMAIN_TRANSFER = 'DOMAIN_TRANSFER';
    case DOMAIN_WG = 'DOMAIN_WG';
    case DOMAIN_WG_RENEW = 'DOMAIN_WG_RENEW';
}
