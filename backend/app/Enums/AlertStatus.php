<?php

declare(strict_types=1);

namespace App\Enums;

enum AlertStatus: string
{
    case Open = 'open';
    case Acknowledged = 'acknowledged';
    case Resolved = 'resolved';
}
