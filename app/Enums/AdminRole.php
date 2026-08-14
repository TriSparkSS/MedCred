<?php

namespace App\Enums;

enum AdminRole: string
{
    case SystemAdmin = 'system_admin';
    case CredentialingManager = 'credentialing_manager';
    case CredentialingExecutive = 'credentialing_executive';
    case BillingReadonly = 'billing_readonly';

    public function label(): string
    {
        return match ($this) {
            self::SystemAdmin => 'System Administrator',
            self::CredentialingManager => 'Credentialing Manager',
            self::CredentialingExecutive => 'Credentialing Executive',
            self::BillingReadonly => 'Billing / Operations Read Only',
        };
    }
}
