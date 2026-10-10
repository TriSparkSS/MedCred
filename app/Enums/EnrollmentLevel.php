<?php

namespace App\Enums;

enum EnrollmentLevel: string
{
    case Individual = 'individual';
    case Group = 'group';

    public function label(): string
    {
        return match ($this) {
            self::Individual => 'Individual',
            self::Group => 'Group',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
