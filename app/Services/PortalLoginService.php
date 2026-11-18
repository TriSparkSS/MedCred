<?php

namespace App\Services;

use App\Models\PortalLogin;
use Illuminate\Database\Eloquent\Model;

/**
 * Single source of truth for portal logins (practice or provider owner).
 * The password is passed to the PortalLogin model, whose 'encrypted' cast encrypts it at rest.
 */
class PortalLoginService
{
    public const PERMISSION = 'admin.portal-credentials.manage';

    /**
     * Validation rules for one login. $prefix is e.g. 'formData' or 'portalLogins.*'.
     */
    public static function rules(string $prefix = 'formData', bool $passwordRequired = true): array
    {
        return [
            "{$prefix}.portal_name" => 'required|string|max:255',
            "{$prefix}.assigned_id" => 'nullable|string|max:50|regex:/^[A-Za-z0-9\-]+$/',
            "{$prefix}.username" => 'nullable|string|max:255',
            "{$prefix}.password" => ($passwordRequired ? 'required' : 'nullable').'|string|max:500',
            "{$prefix}.mfa_email" => 'nullable|email|max:255',
            "{$prefix}.mfa_phone" => 'nullable|string|max:30',
            "{$prefix}.portal_url" => 'nullable|string|max:500',
            "{$prefix}.security_notes" => 'nullable|string|max:5000',
            "{$prefix}.account_details" => 'nullable|string|max:5000',
        ];
    }

    public static function emptyRow(): array
    {
        return [
            'portal_name' => '',
            'assigned_id' => '',
            'username' => '',
            'password' => '',
            'mfa_email' => '',
            'mfa_phone' => '',
            'portal_url' => '',
            'security_notes' => '',
            'account_details' => '',
        ];
    }

    /**
     * @param  Model  $owner  Practice or ProviderDetails (both expose portalLogins()).
     */
    public function create(Model $owner, array $data): PortalLogin
    {
        return $owner->portalLogins()->create($this->payload($data));
    }

    public function update(PortalLogin $login, array $data): PortalLogin
    {
        $login->update($this->payload($data));

        return $login->fresh();
    }

    /**
     * Blank strings become null; the password is only included when filled
     * (so editing without retyping keeps the stored password).
     */
    public function payload(array $data): array
    {
        $payload = [
            'portal_name' => $data['portal_name'],
            'assigned_id' => ($data['assigned_id'] ?? '') !== '' ? $data['assigned_id'] : null,
            'username' => ($data['username'] ?? null) ?: null,
            'mfa_email' => ($data['mfa_email'] ?? null) ?: null,
            'mfa_phone' => ($data['mfa_phone'] ?? null) ?: null,
            'portal_url' => ($data['portal_url'] ?? null) ?: null,
            'security_notes' => ($data['security_notes'] ?? null) ?: null,
            'account_details' => ($data['account_details'] ?? null) ?: null,
        ];

        if (filled($data['password'] ?? null)) {
            $payload['password'] = $data['password'];
        }

        return $payload;
    }
}
