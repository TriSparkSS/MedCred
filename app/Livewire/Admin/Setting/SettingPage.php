<?php

namespace App\Livewire\Admin\Setting;

use App\Models\AuditLog;
use App\Models\CredentialingCase;
use App\Models\DocumentType;
use App\Models\Payer;
use App\Models\Practice;
use App\Models\Setting;
use App\Models\SlaRule;
use App\Models\Status;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::admin', ['title' => 'Settings'])]
class SettingPage extends Component
{
    public int $record_retention_days = 365;

    public int $password_expiry_days = 90;

    public bool $encrypt_documents_at_rest = false;

    public string $auditSearch = '';

    public string $auditActionFilter = '';

    public function mount(): void
    {
        $retention = Setting::where('key', 'record_retention_days')->value('value');
        $expiry = Setting::where('key', 'password_expiry_days')->value('value');
        $encrypt = Setting::where('key', 'encrypt_documents_at_rest')->value('value');

        $this->record_retention_days = $retention !== null ? (int) $retention : 365;
        $this->password_expiry_days = $expiry !== null ? (int) $expiry : 90;
        $this->encrypt_documents_at_rest = in_array($encrypt, ['1', 'true', 1, true], true);
    }

    public function saveSecuritySettings(): void
    {
        abort_unless(Auth::guard('admin')->user()?->can('admin.settings.manage'), 403);

        $this->validate([
            'record_retention_days' => 'required|integer|min:1|max:3650',
            'password_expiry_days' => 'required|integer|min:1|max:365',
            'encrypt_documents_at_rest' => 'boolean',
        ]);

        Setting::set('record_retention_days', (string) $this->record_retention_days);
        Setting::set('password_expiry_days', (string) $this->password_expiry_days);
        Setting::set('encrypt_documents_at_rest', $this->encrypt_documents_at_rest ? '1' : '0');

        AuditLog::record('settings.security_updated', null, Auth::guard('admin')->id(), [
            'record_retention_days' => $this->record_retention_days,
            'password_expiry_days' => $this->password_expiry_days,
            'encrypt_documents_at_rest' => $this->encrypt_documents_at_rest,
        ]);

        flash()->success('Security and retention settings saved successfully.');
    }

    public function saveSettings(): void
    {
        $this->saveSecuritySettings();
    }

    public function runRetentionNow(): void
    {
        abort_unless(Auth::guard('admin')->user()?->can('admin.settings.manage'), 403);

        Artisan::call('credentialing:apply-retention', [
            '--days' => $this->record_retention_days,
        ]);

        flash()->success('Record retention policy executed successfully.');
    }

    public function downloadBackupSnapshot()
    {
        abort_unless(Auth::guard('admin')->user()?->can('admin.settings.manage'), 403);

        $snapshot = [
            'generated_at' => now()->toIso8601String(),
            'generated_by_admin_id' => Auth::guard('admin')->id(),
            'counts' => [
                'practices' => Practice::count(),
                'payers' => Payer::count(),
                'statuses' => Status::count(),
                'document_types' => DocumentType::count(),
                'sla_rules' => SlaRule::count(),
                'credentialing_cases' => CredentialingCase::count(),
                'settings' => Setting::count(),
            ],
            'practices' => Practice::query()->get()->toArray(),
            'payers' => Payer::query()->get()->toArray(),
            'statuses' => Status::query()->get()->toArray(),
            'document_types' => DocumentType::query()->get()->toArray(),
            'sla_rules' => SlaRule::query()->get()->toArray(),
            'credentialing_cases' => CredentialingCase::query()->limit(500)->get()->toArray(),
            'settings' => Setting::query()
                ->where('key', 'not like', '%password%')
                ->where('key', 'not like', '%secret%')
                ->get(['key', 'value', 'updated_at'])
                ->toArray(),
        ];

        AuditLog::record('system.backup_downloaded', null, Auth::guard('admin')->id(), [
            'counts' => $snapshot['counts'],
        ]);

        $filename = 'medcred_backup_'.now()->format('Ymd').'.json';

        return response()->streamDownload(function () use ($snapshot) {
            echo json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }

    public function render()
    {
        $viewsExist = true;
        foreach (['vw_credentialing_case_summary', 'vw_delay_ownership_summary', 'vw_provider_compliance_summary'] as $viewName) {
            try {
                DB::table($viewName)->limit(1)->get();
            } catch (\Throwable) {
                $viewsExist = false;
                break;
            }
        }

        $defaultDriver = (string) config('database.default');
        $sqlsrvConfigured = filled(config('database.connections.sqlsrv.host'));

        $storageWritable = false;
        try {
            $storageWritable = Storage::disk('public')->exists('.') || is_writable(Storage::disk('public')->path(''));
        } catch (\Throwable) {
            $storageWritable = false;
        }

        $mailConfigured = filled(Setting::where('key', 'mail_host')->value('value'))
            || filled(Setting::where('key', 'graph_client_id')->value('value'))
            || filled(config('mail.mailers.smtp.host'));

        $readinessChecks = [
            [
                'key' => 'database',
                'label' => 'Database & SQL Reporting Views',
                'passed' => $viewsExist,
                'detail' => "Driver: {$defaultDriver}"
                    .($sqlsrvConfigured ? ' | SQL Server configured' : '')
                    .($viewsExist ? ' | 3/3 SQL views active' : ' | SQL views missing'),
            ],
            [
                'key' => 'debug',
                'label' => 'Production Debug Mode Off',
                'passed' => config('app.debug') === false,
                'detail' => config('app.debug') === false ? 'APP_DEBUG is disabled' : 'APP_DEBUG is enabled (disable for production)',
            ],
            [
                'key' => 'https',
                'label' => 'HTTPS Enforced (APP_URL)',
                'passed' => str_starts_with((string) config('app.url'), 'https://'),
                'detail' => (string) config('app.url'),
            ],
            [
                'key' => 'storage',
                'label' => 'Document Storage Writable',
                'passed' => $storageWritable,
                'detail' => $storageWritable ? 'Public document storage is accessible' : 'Storage disk is not writable',
            ],
            [
                'key' => 'mail',
                'label' => 'Mail / Microsoft Graph Configured',
                'passed' => $mailConfigured,
                'detail' => $mailConfigured ? 'Outbound mail/Graph credentials configured' : 'Configure SMTP or Graph in Mail Settings',
            ],
        ];

        $auditLogs = AuditLog::with('admin:id,name,username')
            ->when($this->auditSearch !== '', function ($q) {
                $term = '%'.trim($this->auditSearch).'%';
                $q->where(function ($sub) use ($term) {
                    $sub->where('action', 'like', $term)
                        ->orWhere('auditable_type', 'like', $term)
                        ->orWhereHas('admin', fn ($aq) => $aq->where('name', 'like', $term)->orWhere('username', 'like', $term));
                });
            })
            ->when($this->auditActionFilter !== '', fn ($q) => $q->where('action', $this->auditActionFilter))
            ->latest('id')
            ->limit(25)
            ->get();

        return view('livewire.admin.setting.setting-page', compact('readinessChecks', 'auditLogs'));
    }
}
