<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\EmailMessage;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class ApplyRecordRetentionCommand extends Command
{
    protected $signature = 'credentialing:apply-retention {--days= : Override retention period in days}';

    protected $description = 'Apply configurable record retention policy to audit logs, login logs, and unlinked emails';

    public function handle(): int
    {
        $configuredDays = $this->option('days');
        if ($configuredDays === null || $configuredDays === '') {
            $configuredDays = Setting::where('key', 'record_retention_days')->value('value');
        }

        $days = max(1, (int) ($configuredDays ?: 365));
        $cutoff = now()->subDays($days);

        $prunedAuditLogs = 0;
        if (Schema::hasTable('audit_logs')) {
            $prunedAuditLogs = AuditLog::where('created_at', '<', $cutoff)->delete();
        }

        $prunedLoginLogs = 0;
        if (class_exists(\App\Models\LoginLog::class) && Schema::hasTable('login_logs')) {
            $prunedLoginLogs = \App\Models\LoginLog::where('created_at', '<', $cutoff)->delete();
        }

        $prunedUnlinkedEmails = 0;
        if (Schema::hasTable('email_messages')) {
            $prunedUnlinkedEmails = EmailMessage::unlinked()
                ->where('created_at', '<', $cutoff)
                ->delete();
        }

        AuditLog::record('system.retention_applied', null, Auth::guard('admin')->id(), [
            'retention_days' => $days,
            'cutoff' => $cutoff->toIso8601String(),
            'pruned_audit_logs' => $prunedAuditLogs,
            'pruned_login_logs' => $prunedLoginLogs,
            'pruned_unlinked_emails' => $prunedUnlinkedEmails,
        ]);

        $this->info("Record retention applied ({$days} days). Pruned {$prunedAuditLogs} audit logs, {$prunedLoginLogs} login logs, and {$prunedUnlinkedEmails} unlinked emails.");

        return self::SUCCESS;
    }
}
