<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class MicrosoftGraphTokenService
{
    public function isConfigured(): bool
    {
        return filled(config('services.microsoft_graph.tenant_id'))
            && filled(config('services.microsoft_graph.client_id'))
            && filled(config('services.microsoft_graph.client_secret'))
            && filled(config('services.microsoft_graph.mailbox'));
    }

    /**
     * @return list<string>
     */
    public function missingConfigKeys(): array
    {
        $keys = [
            'GRAPH_TENANT_ID' => 'tenant_id',
            'GRAPH_CLIENT_ID' => 'client_id',
            'GRAPH_CLIENT_SECRET' => 'client_secret',
            'GRAPH_MAILBOX' => 'mailbox',
        ];

        $missing = [];

        foreach ($keys as $env => $configKey) {
            if (blank(config('services.microsoft_graph.'.$configKey))) {
                $missing[] = $env;
            }
        }

        return $missing;
    }

    public function getAccessToken(): string
    {
        if (! $this->isConfigured()) {
            $missing = implode(', ', $this->missingConfigKeys());

            throw new \RuntimeException(
                'Microsoft Graph is not configured. Set these in .env: '.$missing
            );
        }

        $this->assertClientIdLooksValid();

        $cacheKey = $this->cacheKey();
        $cached = Cache::get($cacheKey);

        if (filled($cached)) {
            return $cached;
        }

        return $this->requestAccessToken();
    }

    /**
     * Client IDs are GUIDs. Secrets often contain "~" and look nothing like a GUID —
     * catching a swap early avoids a confusing AADSTS700016 from Azure.
     */
    protected function assertClientIdLooksValid(): void
    {
        $clientId = (string) config('services.microsoft_graph.client_id');

        if (preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $clientId)) {
            return;
        }

        throw new \RuntimeException(
            'GRAPH_CLIENT_ID does not look like an Application (client) ID. '
            .'It must be the GUID from Entra → App registration → Overview (e.g. a1b2c3d4-...). '
            .'You likely put the client secret in GRAPH_CLIENT_ID — move the secret to GRAPH_CLIENT_SECRET instead.'
        );
    }

    public function forgetCachedToken(): void
    {
        Cache::forget($this->cacheKey());
    }

    protected function cacheKey(): string
    {
        return 'microsoft_graph_access_token_'.md5(
            (string) config('services.microsoft_graph.tenant_id').'|'.
            (string) config('services.microsoft_graph.client_id')
        );
    }

    protected function requestAccessToken(): string
    {
        $tenant = config('services.microsoft_graph.tenant_id');
        $url = "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token";

        $response = Http::asForm()
            ->timeout(30)
            ->post($url, [
                'grant_type' => 'client_credentials',
                'client_id' => config('services.microsoft_graph.client_id'),
                'client_secret' => config('services.microsoft_graph.client_secret'),
                'scope' => 'https://graph.microsoft.com/.default',
            ]);

        if (! $response->successful()) {
            $error = $response->json('error_description')
                ?? $response->json('error')
                ?? $response->body();

            throw new \RuntimeException('Microsoft Graph token request failed: '.$error);
        }

        $token = $response->json('access_token');

        if (blank($token)) {
            throw new \RuntimeException('Microsoft Graph token response did not include an access_token.');
        }

        $expiresIn = (int) ($response->json('expires_in') ?? 3600);
        $ttlSeconds = max(60, $expiresIn - 120);

        Cache::put($this->cacheKey(), $token, now()->addSeconds($ttlSeconds));

        return $token;
    }
}
