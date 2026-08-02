<?php

namespace App\Services;

use App\Models\Setting;
use Webklex\PHPIMAP\ClientManager;

class MailSettingsService
{
    public const KEY_ENABLED = 'mail.enabled';

    public const KEY_HOST = 'mail.host';

    public const KEY_PORT = 'mail.port';

    public const KEY_ENCRYPTION = 'mail.encryption';

    public const KEY_SCHEME = 'mail.scheme';

    public const KEY_USERNAME = 'mail.username';

    public const KEY_PASSWORD = 'mail.password';

    public const KEY_FROM_ADDRESS = 'mail.from_address';

    public const KEY_FROM_NAME = 'mail.from_name';

    public const KEY_IMAP_ENABLED = 'mail.imap.enabled';

    public const KEY_IMAP_HOST = 'mail.imap.host';

    public const KEY_IMAP_PORT = 'mail.imap.port';

    public const KEY_IMAP_ENCRYPTION = 'mail.imap.encryption';

    public const KEY_IMAP_FOLDER = 'mail.imap.folder';

    public const KEY_IMAP_SENT_ENABLED = 'mail.imap.sent.enabled';

    public const KEY_IMAP_SENT_FOLDER = 'mail.imap.sent.folder';

    public const KEY_IMAP_USERNAME = 'mail.imap.username';

    public const KEY_IMAP_LAST_SYNC_AT = 'mail.imap_last_sync_at';

    public const KEY_IMAP_LAST_UID = 'mail.imap_last_uid';

    public const KEY_IMAP_SENT_LAST_UID = 'mail.imap.last_uid.sent';

    public function defaults(): array
    {
        return [
            'enabled' => true,
            'host' => 'smtp.office365.com',
            'port' => 587,
            'encryption' => 'tls',
            'scheme' => 'smtp',
            'username' => 'credentialing@revantagehbs.com',
            'password' => '',
            'from_address' => 'credentialing@revantagehbs.com',
            'from_name' => 'Revantage Credentialing',
            'imap_enabled' => true,
            'imap_host' => 'outlook.office365.com',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_folder' => 'INBOX',
            'imap_sent_enabled' => true,
            'imap_sent_folder' => 'Sent Items',
            'imap_username' => 'credentialing@revantagehbs.com',
        ];
    }

    public function getSettings(): array
    {
        $settings = $this->loadSettingsFromStorage();
        $settings['from_address'] = $this->resolveFromAddress($settings);
        $settings['from_name'] = $this->resolveFromName($settings);

        return $settings;
    }

    /**
     * @return array<string, mixed>
     */
    protected function loadSettingsFromStorage(): array
    {
        $defaults = $this->defaults();

        $port = (int) (Setting::getDecrypted(self::KEY_PORT, (string) $defaults['port']) ?? $defaults['port']);
        $imapPort = (int) (Setting::getDecrypted(self::KEY_IMAP_PORT, (string) $defaults['imap_port']) ?? $defaults['imap_port']);

        return [
            'enabled' => filter_var(Setting::getDecrypted(self::KEY_ENABLED, $defaults['enabled'] ? '1' : '0'), FILTER_VALIDATE_BOOLEAN),
            'host' => Setting::getDecrypted(self::KEY_HOST, $defaults['host']) ?? $defaults['host'],
            'port' => $port,
            'encryption' => Setting::getDecrypted(self::KEY_ENCRYPTION, $defaults['encryption']) ?? $defaults['encryption'],
            'scheme' => Setting::getDecrypted(self::KEY_SCHEME, $defaults['scheme']) ?? $defaults['scheme'],
            'username' => Setting::getDecrypted(self::KEY_USERNAME, $defaults['username']) ?? $defaults['username'],
            'password' => Setting::getDecrypted(self::KEY_PASSWORD, '') ?? '',
            'from_address' => Setting::getDecrypted(self::KEY_FROM_ADDRESS, $defaults['from_address']) ?? $defaults['from_address'],
            'from_name' => Setting::getDecrypted(self::KEY_FROM_NAME, $defaults['from_name']) ?? $defaults['from_name'],
            'has_password' => Setting::hasEncrypted(self::KEY_PASSWORD),
            'imap_enabled' => filter_var(Setting::getDecrypted(self::KEY_IMAP_ENABLED, $defaults['imap_enabled'] ? '1' : '0'), FILTER_VALIDATE_BOOLEAN),
            'imap_host' => Setting::getDecrypted(self::KEY_IMAP_HOST, $defaults['imap_host']) ?? $defaults['imap_host'],
            'imap_port' => $imapPort,
            'imap_encryption' => Setting::getDecrypted(self::KEY_IMAP_ENCRYPTION, $defaults['imap_encryption']) ?? $defaults['imap_encryption'],
            'imap_folder' => Setting::getDecrypted(self::KEY_IMAP_FOLDER, $defaults['imap_folder']) ?? $defaults['imap_folder'],
            'imap_sent_enabled' => filter_var(Setting::getDecrypted(self::KEY_IMAP_SENT_ENABLED, $defaults['imap_sent_enabled'] ? '1' : '0'), FILTER_VALIDATE_BOOLEAN),
            'imap_sent_folder' => Setting::getDecrypted(self::KEY_IMAP_SENT_FOLDER, $defaults['imap_sent_folder']) ?? $defaults['imap_sent_folder'],
            'imap_username' => Setting::getDecrypted(self::KEY_IMAP_USERNAME, $defaults['imap_username']) ?? $defaults['imap_username'],
            'imap_last_sync_at' => Setting::getDecrypted(self::KEY_IMAP_LAST_SYNC_AT),
            'imap_last_uid' => (int) (Setting::getDecrypted(self::KEY_IMAP_LAST_UID, '0') ?? 0),
            'imap_sent_last_uid' => (int) (Setting::getDecrypted(self::KEY_IMAP_SENT_LAST_UID, '0') ?? 0),
        ];
    }

    public function resolveFromAddress(?array $settings = null): string
    {
        $settings ??= $this->loadSettingsFromStorage();
        $defaults = $this->defaults();

        $from = trim($settings['from_address'] ?? '');
        if (filled($from)) {
            return $from;
        }

        $username = trim($settings['username'] ?? '');
        if (filled($username)) {
            return $username;
        }

        return config('credentialing.mailbox.from_address', $defaults['from_address']);
    }

    public function resolveFromName(?array $settings = null): string
    {
        $settings ??= $this->loadSettingsFromStorage();
        $defaults = $this->defaults();

        $name = trim($settings['from_name'] ?? '');

        return filled($name) ? $name : ($defaults['from_name'] ?? 'Revantage Credentialing');
    }

    public function saveSettings(array $data): void
    {
        Setting::set(self::KEY_ENABLED, ($data['enabled'] ?? false) ? '1' : '0');
        Setting::set(self::KEY_HOST, $data['host'] ?? '');
        Setting::set(self::KEY_PORT, (string) ($data['port'] ?? 587));
        Setting::set(self::KEY_ENCRYPTION, $data['encryption'] ?? 'tls');
        Setting::set(self::KEY_SCHEME, $data['scheme'] ?? 'smtp');
        Setting::set(self::KEY_USERNAME, $data['username'] ?? '');
        Setting::set(self::KEY_FROM_ADDRESS, $data['from_address'] ?? '');
        Setting::set(self::KEY_FROM_NAME, $data['from_name'] ?? '');

        if (array_key_exists('imap_enabled', $data)) {
            Setting::set(self::KEY_IMAP_ENABLED, ($data['imap_enabled'] ?? false) ? '1' : '0');
        }
        if (array_key_exists('imap_host', $data)) {
            Setting::set(self::KEY_IMAP_HOST, $data['imap_host'] ?? '');
        }
        if (array_key_exists('imap_port', $data)) {
            Setting::set(self::KEY_IMAP_PORT, (string) ($data['imap_port'] ?? 993));
        }
        if (array_key_exists('imap_encryption', $data)) {
            Setting::set(self::KEY_IMAP_ENCRYPTION, $data['imap_encryption'] ?? 'ssl');
        }
        if (array_key_exists('imap_folder', $data)) {
            Setting::set(self::KEY_IMAP_FOLDER, $data['imap_folder'] ?? 'INBOX');
        }
        if (array_key_exists('imap_sent_enabled', $data)) {
            Setting::set(self::KEY_IMAP_SENT_ENABLED, ($data['imap_sent_enabled'] ?? false) ? '1' : '0');
        }
        if (array_key_exists('imap_sent_folder', $data)) {
            Setting::set(self::KEY_IMAP_SENT_FOLDER, $data['imap_sent_folder'] ?? 'Sent Items');
        }
        if (array_key_exists('imap_username', $data)) {
            Setting::set(self::KEY_IMAP_USERNAME, $data['imap_username'] ?? '');
        }

        if (! empty($data['password'])) {
            Setting::set(self::KEY_PASSWORD, $data['password'], encrypted: true);
        }

        $this->applyToConfig();
    }

    public function setImapLastSync(int $uid, ?string $uidKey = null): void
    {
        Setting::set($uidKey ?? self::KEY_IMAP_LAST_UID, (string) $uid);
        Setting::set(self::KEY_IMAP_LAST_SYNC_AT, now()->toIso8601String());
    }

    public function setImapLastSyncForFolder(string $uidKey, int $uid): void
    {
        $this->setImapLastSync($uid, $uidKey);
    }

    public function applyToConfig(): void
    {
        $settings = $this->getSettings();

        if (! $settings['enabled'] || blank($settings['host'])) {
            return;
        }

        $fromAddress = $this->resolveFromAddress($settings);
        $fromName = $this->resolveFromName($settings);
        $scheme = $settings['scheme'] ?: (($settings['port'] === 465) ? 'smtps' : 'smtp');

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => $settings['host'],
            'mail.mailers.smtp.port' => $settings['port'],
            'mail.mailers.smtp.scheme' => $scheme,
            'mail.mailers.smtp.username' => $settings['username'],
            'mail.mailers.smtp.password' => $settings['password'],
            'mail.from.address' => $fromAddress,
            'mail.from.name' => $fromName,
            'credentialing.mailbox.from_address' => $fromAddress,
            'credentialing.mailbox.from_name' => $fromName,
        ]);

        if (app()->bound('mail.manager')) {
            app('mail.manager')->purge('smtp');
        }
    }

    public function isConfigured(): bool
    {
        $settings = $this->loadSettingsFromStorage();

        return $settings['enabled']
            && filled($settings['host'])
            && filled($settings['username'])
            && filled($this->resolveFromAddress($settings))
            && ($settings['has_password'] || filled($settings['password']));
    }

    public function assertFromConfigured(): void
    {
        $settings = $this->loadSettingsFromStorage();

        if (! filled($this->resolveFromAddress($settings))) {
            throw new \RuntimeException('From email address is required. Set it in Admin → Settings → Mail.');
        }
    }

    public function assertConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('SMTP is not configured. Save mail settings at Admin → Settings → Mail.');
        }

        $this->assertFromConfigured();
    }

    public function isImapConfigured(): bool
    {
        $settings = $this->getSettings();

        return $settings['imap_enabled']
            && filled($settings['imap_host'])
            && filled($settings['imap_username'])
            && filled($settings['imap_folder'])
            && (! $settings['imap_sent_enabled'] || filled($settings['imap_sent_folder']))
            && ($settings['has_password'] || filled($settings['password']));
    }

    public function isMailboxSyncConfigured(): bool
    {
        return app(GraphMailboxService::class)->isConfigured();
    }

    public function mailboxSyncDriver(): string
    {
        return 'graph';
    }

    public function formatMailboxSyncError(\Throwable $e): string
    {
        return $e->getMessage();
    }

    public function imapClientConfig(): array
    {
        $settings = $this->getSettings();

        return [
            'host' => $settings['imap_host'],
            'port' => $settings['imap_port'],
            'encryption' => $settings['imap_encryption'] === 'none' ? false : $settings['imap_encryption'],
            'validate_cert' => true,
            'username' => $settings['imap_username'] ?: $settings['username'],
            'password' => $settings['password'],
            'protocol' => 'imap',
            'timeout' => 30,
        ];
    }

    public function formatImapError(\Throwable $e): string
    {
        $message = $e->getMessage();

        if (! $this->isImapAuthFailure($message)) {
            return $message;
        }

        return $message . ' — Microsoft 365 often allows SMTP with a password but blocks IMAP password login. '
            . 'Prefer Microsoft Graph OAuth: set GRAPH_TENANT_ID, GRAPH_CLIENT_ID, GRAPH_CLIENT_SECRET, and GRAPH_MAILBOX in .env '
            . '(Entra app with Mail.Read application permission + admin consent). '
            . 'IMAP fallback: enable IMAP under Users → Mail → Manage email apps, or confirm the tenant still allows IMAP basic auth.';
    }

    public function isImapAuthFailure(string $message): bool
    {
        $normalized = strtolower($message);

        return str_contains($normalized, 'authenticate failed')
            || str_contains($normalized, 'authentication failed')
            || str_contains($normalized, 'login failed')
            || str_contains($normalized, 'auth failed');
    }

    /**
     * @return array{success: bool, message_count: int, folder: string, folders: array<int, array{folder: string, message_count: int}>}
     */
    public function testImapConnection(): array
    {
        if (! $this->isImapConfigured()) {
            throw new \RuntimeException('IMAP is not configured or password is missing.');
        }

        $settings = $this->getSettings();

        if (! filled($settings['password'])) {
            throw new \RuntimeException('IMAP password is missing. Re-enter the mailbox password and save settings.');
        }

        $client = (new ClientManager)->make($this->imapClientConfig());

        try {
            $client->connect();
        } catch (\Throwable $e) {
            throw new \RuntimeException($this->formatImapError($e), previous: $e);
        }

        $foldersToTest = [$settings['imap_folder']];
        if ($settings['imap_sent_enabled'] && filled($settings['imap_sent_folder'])) {
            $foldersToTest[] = $settings['imap_sent_folder'];
        }

        $folderResults = [];
        $totalCount = 0;

        foreach (array_unique($foldersToTest) as $folderName) {
            $folder = $client->getFolder($folderName);
            $count = $folder->messages()->all()->count();
            $folderResults[] = [
                'folder' => $folderName,
                'message_count' => $count,
            ];
            $totalCount += $count;
        }

        $client->disconnect();

        return [
            'success' => true,
            'message_count' => $totalCount,
            'folder' => $settings['imap_folder'],
            'folders' => $folderResults,
        ];
    }

    public function sendTestEmail(string $toAddress): \App\Data\SentEmailResult
    {
        $this->assertConfigured();

        return app(CredentialingEmailService::class)->send(
            null,
            $toAddress,
            'Revantage CRM — SMTP Test',
            'This is a test email from Revantage CRM. Your Office 365 SMTP settings are working correctly.',
        );
    }
}
