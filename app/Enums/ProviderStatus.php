<?php

namespace App\Enums;

enum ProviderStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case ON_HOLD = 'on_hold';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
            self::ON_HOLD => 'On Hold',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::INACTIVE => 'secondary',
            self::ON_HOLD => 'warning',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
