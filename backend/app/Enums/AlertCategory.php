<?php

declare(strict_types=1);

namespace App\Enums;

enum AlertCategory: string
{
    case Bidding = 'bidding';
    case Prebid = 'prebid';
    case Gam = 'gam';
    case WebVitals = 'web_vitals';
    case Revenue = 'revenue';
    case Network = 'network';
    case Slot = 'slot';
    case Server = 'server';
}
