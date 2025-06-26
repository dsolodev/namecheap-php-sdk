<?php

declare(strict_types=1);

namespace Namecheap\Enums;

/**
 * API environment modes
 */
enum Environment: string
{
    case PRODUCTION = 'production';
    case SANDBOX = 'sandbox';

    public function getApiUrl(): string
    {
        return match ($this) {
            self::PRODUCTION => 'https://api.namecheap.com/xml.response',
            self::SANDBOX => 'https://api.sandbox.namecheap.com/xml.response',
        };
    }
}
