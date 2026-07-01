<?php

namespace App\Enums;

enum DocumentVerificationStatus: string
{
    case Missing = 'missing';
    case Uploaded = 'uploaded';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Superseded = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::Missing => 'Missing',
            self::Uploaded => 'Uploaded',
            self::Verified => 'Verified',
            self::Rejected => 'Rejected',
            self::Expired => 'Expired',
            self::Superseded => 'Superseded',
        };
    }
}
