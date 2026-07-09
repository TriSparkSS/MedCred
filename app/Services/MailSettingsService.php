<?php

namespace App\Services;

use App\Models\EmailMessage;
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

    public const KEY_IMAP_USERNAME = 'mail.imap.username';

    public const KEY_IMAP_LAST_SYNC_AT = 'mail.imap_last_sync_at';

    public const KEY_IMAP_LAST_UID = 'mail.imap_last_uid';

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
            'imap_username' => 'credentialing@revantagehbs.com',
        ];
    }

    public function getSettings(): array
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
            'imap_username' => Setting::getDecrypted(self::KEY_IMAP_USERNAME, $defaults['imap_username']) ?? $defaults['imap_username'],
            'imap_last_sync_at' => Setting::getDecrypted(self::KEY_IMAP_LAST_SYNC_AT),
            'imap_last_uid' => (int) (Setting::getDecrypted(self::KEY_IMAP_LAST_UID, '0') ?? 0),
        ];
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
        if (array_key_exists('imap_username', $data)) {
            Setting::set(self::KEY_IMAP_USERNAME, $data['imap_username'] ?? '');
        }

        if (! empty($data['password'])) {
            Setting::set(self::KEY_PASSWORD, $data['password'], encrypted: true);
        }

        $this->applyToConfig();
    }

    public function setImapLastSync(int $uid): void
    {
        Setting::set(self::KEY_IMAP_LAST_UID, (string) $uid);
        Setting::set(self::KEY_IMAP_LAST_SYNC_AT, now()->toIso8601String());
    }

    public function applyToConfig(): void
    {
        $settings = $this->getSettings();

        if (! $settings['enabled'] || blank($settings['host'])) {
            return;
        }

        $scheme = $settings['scheme'] ?: (($settings['port'] === 465) ? 'smtps' : 'smtp');

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => $settings['host'],
            'mail.mailers.smtp.port' => $settings['port'],
            'mail.mailers.smtp.scheme' => $scheme,
            'mail.mailers.smtp.username' => $settings['username'],
            'mail.mailers.smtp.password' => $settings['password'],
            'mail.from.address' => $settings['from_address'],
            'mail.from.name' => $settings['from_name'],
            'credentialing.mailbox.from_address' => $settings['from_address'],
            'credentialing.mailbox.from_name' => $settings['from_name'],
        ]);

        if (app()->bound('mail.manager')) {
            app('mail.manager')->purge('smtp');
        }
    }

    public function isConfigured(): bool
    {
        $settings = $this->getSettings();

        return $settings['enabled']
            && filled($settings['host'])
            && filled($settings['username'])
            && ($settings['has_password'] || filled($settings['password']));
    }

    public function assertConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('SMTP is not configured. Save mail settings at Admin → Settings → Mail.');
        }
    }

    public function isImapConfigured(): bool
    {
        $settings = $this->getSettings();

        return $settings['imap_enabled']
            && filled($settings['imap_host'])
            && filled($settings['imap_username'])
            && ($settings['has_password'] || filled($settings['password']));
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
        ];
    }

    /**
     * @return array{success: bool, message_count: int, folder: string}
     */
    public function testImapConnection(): array
    {
        if (! $this->isImapConfigured()) {
            throw new \RuntimeException('IMAP is not configured or password is missing.');
        }

        $settings = $this->getSettings();
        $client = (new ClientManager)->make($this->imapClientConfig());
        $client->connect();

        $folder = $client->getFolder($settings['imap_folder']);
        $count = $folder->messages()->all()->count();

        $client->disconnect();

        return [
            'success' => true,
            'message_count' => $count,
            'folder' => $settings['imap_folder'],
        ];
    }

    public function sendTestEmail(string $toAddress): EmailMessage
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
