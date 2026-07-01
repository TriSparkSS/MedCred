<?php

namespace App\Enums;

enum TaskType: string
{
    case Manual = 'manual';
    case ProviderFollowUp = 'provider_follow_up';
    case PayerFollowUp = 'payer_follow_up';
    case FollowUp = 'follow_up';
    case Escalation = 'escalation';
    case Review = 'review';
    case Renewal = 'renewal';
    case BillingReadiness = 'billing_readiness';
    case Document = 'document';
    case Expiry = 'expiry';
    case Sla = 'sla';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::ProviderFollowUp => 'Provider Follow-up',
            self::PayerFollowUp => 'Payer Follow-up',
            self::FollowUp => 'Follow-up',
            self::Escalation => 'Escalation',
            self::Review => 'Review',
            self::Renewal => 'Renewal',
            self::BillingReadiness => 'Billing Readiness',
            self::Document => 'Document Request',
            self::Expiry => 'Expiry',
            self::Sla => 'SLA Follow-up',
        };
    }

    public function isProviderPortalVisible(): bool
    {
        return in_array($this, [
            self::Document,
            self::ProviderFollowUp,
            self::FollowUp,
            self::Renewal,
            self::Expiry,
        ], true);
    }

    public static function providerPortalTypes(): array
    {
        return array_values(array_map(
            fn (self $type) => $type->value,
            array_filter(self::cases(), fn (self $type) => $type->isProviderPortalVisible())
        ));
    }
}
