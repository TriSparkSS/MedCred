<?php

namespace App\Models;

use App\Helpers\SettingHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    public static function set(string $key, ?string $value, bool $encrypted = false): void
    {
        if ($encrypted && $value !== null && $value !== '') {
            $value = Crypt::encryptString($value);
        }

        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );

        SettingHelper::clearCache();
    }

    public static function getDecrypted(string $key, ?string $default = null): ?string
    {
        $value = SettingHelper::get($key);

        if ($value === null || $value === '') {
            return $default;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return $value;
        }
    }

    public static function hasEncrypted(string $key): bool
    {
        $value = SettingHelper::get($key);

        return $value !== null && $value !== '';
    }
}
