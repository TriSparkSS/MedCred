<?php

namespace App\Livewire\Admin\Setting;

use App\Services\MailSettingsService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::admin', ['title' => 'Mail Settings | Settings'])]
class MailSettingsManager extends Component
{
    public bool $enabled = true;

    public string $host = '';

    public int $port = 587;

    public string $encryption = 'tls';

    public string $scheme = 'smtp';

    public string $username = '';

    public string $password = '';

    public string $from_address = '';

    public string $from_name = '';

    public bool $hasPassword = false;

    public bool $imap_enabled = true;

    public string $imap_host = '';

    public int $imap_port = 993;

    public string $imap_encryption = 'ssl';

    public string $imap_folder = 'INBOX';

    public bool $imap_sent_enabled = true;

    public string $imap_sent_folder = 'Sent Items';

    public string $imap_username = '';

    public string $testEmailTo = '';

    public function mount(MailSettingsService $mailSettings): void
    {
        $settings = $mailSettings->getSettings();

        $this->enabled = $settings['enabled'];
        $this->host = $settings['host'];
        $this->port = $settings['port'];
        $this->encryption = $settings['encryption'];
        $this->scheme = $settings['scheme'];
        $this->username = $settings['username'];
        $this->from_address = $settings['from_address'];
        $this->from_name = $settings['from_name'];
        $this->hasPassword = $settings['has_password'];
        $this->imap_enabled = $settings['imap_enabled'];
        $this->imap_host = $settings['imap_host'];
        $this->imap_port = $settings['imap_port'];
        $this->imap_encryption = $settings['imap_encryption'];
        $this->imap_folder = $settings['imap_folder'];
        $this->imap_sent_enabled = $settings['imap_sent_enabled'];
        $this->imap_sent_folder = $settings['imap_sent_folder'];
        $this->imap_username = $settings['imap_username'];
        $this->testEmailTo = Auth::guard('admin')->user()?->email ?? '';
    }

    protected function rules(): array
    {
        return [
            'enabled' => 'boolean',
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'encryption' => 'required|in:tls,ssl,none',
            'scheme' => 'nullable|string|max:20',
            'username' => 'required|email|max:255',
            'password' => 'nullable|string|min:4',
            'from_address' => 'required|email|max:255',
            'from_name' => 'required|string|max:255',
            'imap_enabled' => 'boolean',
            'imap_host' => 'required_if:imap_enabled,true|nullable|string|max:255',
            'imap_port' => 'required_if:imap_enabled,true|nullable|integer|min:1|max:65535',
            'imap_encryption' => 'required_if:imap_enabled,true|nullable|in:ssl,tls,none',
            'imap_folder' => 'nullable|string|max:255',
            'imap_sent_enabled' => 'boolean',
            'imap_sent_folder' => 'required_if:imap_sent_enabled,true|nullable|string|max:255',
            'imap_username' => 'nullable|email|max:255',
            'testEmailTo' => 'nullable|email|max:255',
        ];
    }

    protected function rulesForTest(): array
    {
        $rules = $this->rules();
        $rules['testEmailTo'] = 'required|email|max:255';

        return $rules;
    }

    protected function persistSettingsBeforeTest(MailSettingsService $mailSettings): bool
    {
        if (! $this->hasPassword && blank($this->password)) {
            $this->addError('password', 'SMTP password is required. Enter the password or save settings first.');

            return false;
        }

        $mailSettings->saveSettings($this->settingsPayload());
        $this->password = '';
        $this->hasPassword = true;

        return true;
    }

    protected function settingsPayload(): array
    {
        return [
            'enabled' => $this->enabled,
            'host' => $this->host,
            'port' => $this->port,
            'encryption' => $this->encryption,
            'scheme' => $this->scheme ?: 'smtp',
            'username' => $this->username,
            'password' => $this->password,
            'from_address' => $this->from_address,
            'from_name' => $this->from_name,
            'imap_enabled' => $this->imap_enabled,
            'imap_host' => $this->imap_host,
            'imap_port' => $this->imap_port,
            'imap_encryption' => $this->imap_encryption,
            'imap_folder' => $this->imap_folder ?: 'INBOX',
            'imap_sent_enabled' => $this->imap_sent_enabled,
            'imap_sent_folder' => $this->imap_sent_folder ?: 'Sent Items',
            'imap_username' => $this->imap_username ?: $this->username,
        ];
    }

    public function save(MailSettingsService $mailSettings): void
    {
        $this->validate();

        if (! $this->hasPassword && blank($this->password)) {
            $this->addError('password', 'SMTP password is required.');

            return;
        }

        $mailSettings->saveSettings($this->settingsPayload());

        $this->password = '';
        $this->hasPassword = true;

        flash()->success('Mail settings saved successfully.');
    }

    public function sendTest(MailSettingsService $mailSettings): void
    {
        $this->validate($this->rulesForTest());

        try {
            if (! $this->persistSettingsBeforeTest($mailSettings)) {
                return;
            }

            $message = $mailSettings->sendTestEmail($this->testEmailTo);

            if ($message->status === 'failed') {
                flash()->error('Test email failed: ' . ($message->error_message ?? 'Unknown error'));

                return;
            }

            flash()->success('Test email sent to ' . $this->testEmailTo . '. It appears in Email Center under Sent.');
        } catch (\Throwable $e) {
            flash()->error('Test email failed: ' . $e->getMessage());
        }
    }

    public function testImap(MailSettingsService $mailSettings): void
    {
        $this->validate($this->rules());

        try {
            if (! $this->persistSettingsBeforeTest($mailSettings)) {
                return;
            }

            $result = $mailSettings->testImapConnection();
            $folderSummary = collect($result['folders'] ?? [])
                ->map(fn (array $folder) => "\"{$folder['folder']}\" ({$folder['message_count']})")
                ->implode(', ');
            flash()->success('IMAP connected. Folders: ' . $folderSummary . '.');
        } catch (\Throwable $e) {
            flash()->error('IMAP test failed: ' . $mailSettings->formatImapError($e));
        }
    }

    public function render()
    {
        return view('livewire.admin.setting.mail-settings-manager');
    }
}
