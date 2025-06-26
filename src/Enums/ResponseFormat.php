<?php

declare(strict_types=1);

namespace Namecheap\Enums;

/**
 * API response format types
 */
enum ResponseFormat: string
{
    case XML = 'xml';
    case JSON = 'json';
    case ARRAY = 'array';
}
