<?php

declare(strict_types=1);

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case ProgrammaticRevenue = 'programmatic_revenue';
    case AdOps = 'adops';
    case Tech = 'tech';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::ProgrammaticRevenue => 'Programmatic Revenue',
            self::AdOps => 'AdOps',
            self::Tech => 'Tech',
            self::Viewer => 'Viewer',
        };
    }
}
