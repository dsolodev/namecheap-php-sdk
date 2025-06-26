<?php

declare(strict_types=1);

namespace Namecheap\Enums;

/**
 * DNS record types
 */
enum DnsRecordType: string
{
    case A = 'A';
    case AAAA = 'AAAA';
    case ALIAS = 'ALIAS';
    case CAA = 'CAA';
    case CNAME = 'CNAME';
    case MX = 'MX';
    case MXE = 'MXE';
    case NS = 'NS';
    case PTR = 'PTR';
    case SRV = 'SRV';
    case TXT = 'TXT';
    case URL = 'URL';
    case URL301 = 'URL301';
    case FRAME = 'FRAME';
}
