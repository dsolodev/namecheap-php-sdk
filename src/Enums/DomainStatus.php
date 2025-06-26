<?php

declare(strict_types=1);

namespace Namecheap\Enums;

/**
 * Domain status types
 */
enum DomainStatus: string
{
    case ACTIVE = 'Active';
    case EXPIRED = 'Expired';
    case LOCKED = 'Locked';
    case PENDING = 'Pending';
    case SUSPENDED = 'Suspended';
}
