<?php

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use App\Models\SlaRule;
use Illuminate\Database\Seeder;

class Phase2Seeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Document Request',
                'template_key' => 'document_request',
                'category' => 'request',
                'subject' => 'Document request — Case {{case_number}}',
                'body' => "Dear {{provider_name}},\n\nWe need additional documents for your credentialing application with {{payer_name}} (Case {{case_number}}).\n\nPlease upload the requested items at your earliest convenience.\n\nThank you,\nRevantage Credentialing Team",
            ],
            [
                'name' => 'Provider Reminder (3 Day)',
                'template_key' => 'provider_reminder_1',
                'category' => 'reminder',
                'subject' => 'Reminder: Documents needed — {{case_number}}',
                'body' => "Dear {{provider_name}},\n\nThis is a friendly reminder that we are still awaiting documents for case {{case_number}} with {{payer_name}}.\n\nPlease respond by {{next_follow_up_date}}.\n\nRevantage Credentialing",
            ],
            [
                'name' => 'Provider Reminder (7 Day)',
                'template_key' => 'provider_reminder_2',
                'category' => 'reminder',
                'subject' => 'Second reminder: {{case_number}}',
                'body' => "Dear {{provider_name}},\n\nWe have not yet received the requested documents for {{payer_name}} enrollment ({{case_number}}). Please submit as soon as possible to avoid delays.\n\nRevantage Credentialing",
            ],
            [
                'name' => 'Manager Escalation',
                'template_key' => 'provider_escalation',
                'category' => 'escalation',
                'subject' => 'Escalation: {{case_number}} — {{provider_name}}',
                'body' => "Case {{case_number}} for {{provider_name}} with {{payer_name}} requires manager review. Provider response SLA has been exceeded.\n\nPlease review and take action.",
            ],
            [
                'name' => 'Payer Follow-up',
                'template_key' => 'payer_follow_up',
                'category' => 'payer',
                'subject' => 'Payer follow-up — {{case_number}} / {{payer_name}}',
                'body' => "Follow-up required with {{payer_name}} regarding application {{case_number}} for {{provider_name}}.\n\nNext follow-up date: {{next_follow_up_date}}",
            ],
            [
                'name' => 'Revalidation Notice',
                'template_key' => 'revalidation',
                'category' => 'revalidation',
                'subject' => 'Revalidation due — {{provider_name}}',
                'body' => "Dear {{provider_name}},\n\nYour credentialing with {{payer_name}} is due for revalidation. Case reference: {{case_number}}.\n\nRevantage Credentialing",
            ],
        ];

        foreach ($templates as $template) {
            NotificationTemplate::updateOrCreate(
                ['template_key' => $template['template_key']],
                $template + ['is_active' => true]
            );
        }

        $templateIds = NotificationTemplate::pluck('id', 'template_key');

        $rules = [
            ['rule_key' => 'internal_review', 'name' => 'Internal Review SLA', 'days' => 2, 'applies_to' => 'internal', 'action' => 'shift_delay', 'template' => null],
            ['rule_key' => 'provider_reminder_1', 'name' => 'Provider Reminder (3 days)', 'days' => 3, 'applies_to' => 'provider', 'action' => 'reminder', 'template' => 'provider_reminder_1'],
            ['rule_key' => 'provider_reminder_2', 'name' => 'Provider Second Reminder (7 days)', 'days' => 7, 'applies_to' => 'provider', 'action' => 'reminder', 'template' => 'provider_reminder_2'],
            ['rule_key' => 'provider_escalation', 'name' => 'Manager Escalation (10 days)', 'days' => 10, 'applies_to' => 'provider', 'action' => 'escalate', 'template' => 'provider_escalation'],
            ['rule_key' => 'payer_follow_up', 'name' => 'Payer Follow-up (7 days)', 'days' => 7, 'applies_to' => 'payer', 'action' => 'task', 'template' => 'payer_follow_up'],
        ];

        foreach ($rules as $rule) {
            SlaRule::updateOrCreate(
                ['rule_key' => $rule['rule_key']],
                [
                    'name' => $rule['name'],
                    'days' => $rule['days'],
                    'business_days_only' => true,
                    'applies_to' => $rule['applies_to'],
                    'action' => $rule['action'],
                    'notification_template_id' => $rule['template'] ? ($templateIds[$rule['template']] ?? null) : null,
                    'is_active' => true,
                ]
            );
        }
    }
}
