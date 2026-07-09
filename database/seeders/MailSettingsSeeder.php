<?php

namespace Database\Seeders;

use App\Services\MailSettingsService;
use Illuminate\Database\Seeder;

class MailSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(MailSettingsService::class);
        $defaults = $service->defaults();

        $service->saveSettings([
            'enabled' => $defaults['enabled'],
            'host' => $defaults['host'],
            'port' => $defaults['port'],
            'encryption' => $defaults['encryption'],
            'scheme' => $defaults['scheme'],
            'username' => $defaults['username'],
            'password' => '',
            'from_address' => $defaults['from_address'],
            'from_name' => $defaults['from_name'],
            'imap_enabled' => $defaults['imap_enabled'],
            'imap_host' => $defaults['imap_host'],
            'imap_port' => $defaults['imap_port'],
            'imap_encryption' => $defaults['imap_encryption'],
            'imap_folder' => $defaults['imap_folder'],
            'imap_username' => $defaults['imap_username'],
        ]);
    }
}
