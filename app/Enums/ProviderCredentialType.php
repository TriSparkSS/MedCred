<?php

namespace App\Enums;

enum ProviderCredentialType: string
{
    case License = 'license';
    case Dea = 'dea';
    case Cds = 'cds';

    public function label(): string
    {
        return match ($this) {
            self::License => 'State License',
            self::Dea => 'DEA',
            self::Cds => 'CDS',
        };
    }

    public function documentTypeName(): string
    {
        return match ($this) {
            self::License => 'State License',
            self::Dea => 'DEA Certificate',
            self::Cds => 'CDS Certificate',
        };
    }

    public static function fromDocumentTypeName(?string $name): ?self
    {
        $normalized = strtolower(trim((string) $name));

        return match (true) {
            $normalized === '' => null,
            str_contains($normalized, 'dea') => self::Dea,
            str_contains($normalized, 'cds') => self::Cds,
            str_contains($normalized, 'license') => self::License,
            default => null,
        };
    }
}
