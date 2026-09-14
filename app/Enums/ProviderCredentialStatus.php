<?php

namespace App\Enums;

enum ProviderCredentialStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Pending = 'pending';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Expired => 'Expired',
            self::Pending => 'Pending',
            self::Inactive => 'Inactive',
        };
    }
}
