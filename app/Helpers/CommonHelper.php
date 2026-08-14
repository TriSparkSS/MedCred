<?php

use App\Helpers\SettingHelper;
use Illuminate\Support\Facades\Auth;

if (! function_exists('getUserImageInitial')) {

    function getUserImageInitial($userId, $name)
    {
        return getAvatarUrl() . "?name=$name&size=100&rounded=true&color=fff&background=" . getRandomColor($userId);
    }
}

if (! function_exists('getAvatarUrl')) {

    function getAvatarUrl()
    {
        return 'https://ui-avatars.com/api/';
    }
}

if (! function_exists('getRandomColor')) {

    function getRandomColor($userId)
    {
        $colors = ['329af0', 'fc6369', 'ffaa2e', '42c9af', '7d68f0'];
        $index = $userId % 5;

        return $colors[$index];
    }
}

if (! function_exists('setting')) {

    function setting($key, $default = null)
    {
        return SettingHelper::get($key, $default);
    }
}

if (! function_exists('generateIndianMobileNumber')) {
    function generateIndianMobileNumber(): string
    {
        $prefixes = ['9', '8', '7', '6'];
        $firstDigit = $prefixes[array_rand($prefixes)];
        $remainingDigits = '';
        for ($i = 0; $i < 9; $i++) {
            $remainingDigits .= rand(0, 9);
        }

        return $firstDigit . $remainingDigits;
    }
}

if (! function_exists('generateRandomEmail')) {
    function generateRandomEmail($name): string
    {
        $cleanName = preg_replace('/[^a-z0-9]/i', '', strtolower($name));
        $randomNumber = rand(1000, 999999);
        $domains = ['gmail.com', 'yahoo.com', 'outlook.com', 'protonmail.com', 'hotmail.com', 'rediffmail.com', 'icloud.com', 'zoho.com'];
        $randomDomain = $domains[array_rand($domains)];

        return $cleanName . $randomNumber . '@' . $randomDomain;
    }
}

if (! function_exists('generateTxnRef')) {
    function generateTxnRef($prefix = 'TXN'): string
    {
        return strtoupper($prefix) . '-' . time() . '-' . bin2hex(random_bytes(4));
    }
}

if (! function_exists('formatAmount')) {
    function formatAmount($amount): float
    {
        return round((float) $amount, 2);
    }
}

if (! function_exists('statusUpper')) {
    function statusUpper($status): string
    {
        return strtoupper(trim($status));
    }
}

if (! function_exists('formatMobileWith91')) {
    function formatMobileWith91($mobile)
    {
        return '+91' . preg_replace('/\D/', '', $mobile);
    }
}

if (! function_exists('isJson')) {
    function isJson($string)
    {
        json_decode($string);

        return json_last_error() === JSON_ERROR_NONE;
    }
}

if (! function_exists('generateOtp')) {
    function generateOtp($length = 6)
    {
        return str_pad(rand(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
    }
}

if (! function_exists('safeJsonEncode')) {
    function safeJsonEncode($data)
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

if (! function_exists('convertToUpper')) {
    function convertToUpper($value)
    {
        return strtoupper(trim($value));
    }
}

if (! function_exists('can_do')) {
    function can_do(string $permission, $guard = 'web'): bool
    {
        $user = Auth::guard($guard)->user();

        if (! $user) {
            return false;
        }
        // if ($user->hasRole('super-admin')) {
        //     return true;
        // }

        return $user->can($permission);
    }
}

if (! function_exists('can_any')) {

    function can_any(array $permissions, $guard = 'web'): bool
    {
        $user = Auth::guard($guard)->user();

        if (! $user) {
            return false;
        }

        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }
}

if (! function_exists('can_do_api')) {
    function can_do_api(string $permission, $guard = 'web'): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }
        // if ($user->hasRole('super-admin')) {
        //     return true;
        // }

        return $user->can($permission);
    }
}
if (! function_exists('can_any_api')) {

    function can_any_api(array $permissions, $guard = 'web'): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }
}

if (! function_exists('user_permissions')) {
    function user_permissions($guard = 'web'): array
    {
        $user = Auth::guard($guard)->user();

        if (! $user) {
            return [];
        }

        return $user->getAllPermissions()
            ->pluck('name')
            ->toArray();
    }
}

if (! function_exists('payload_value')) {
    function payload_value(array $payload, string $key): mixed
    {
        return data_get($payload, $key);
    }
}
