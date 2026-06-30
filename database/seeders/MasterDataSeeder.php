<?php

namespace Database\Seeders;

use App\Models\CaseType;
use App\Models\DelayOwner;
use App\Models\DocumentType;
use App\Models\Priority;
use App\Models\Status;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $delayOwners = [
            'Revantage Team' => 'Internal',
            'Provider/Practice' => 'Provider',
            'Payer' => 'Payer',
            'No Delay / Closed' => 'Regulatory Body',
        ];

        foreach ($delayOwners as $name => $legacy) {
            DelayOwner::firstOrCreate(['name' => $name], ['is_active' => true]);
        }

        // Keep legacy delay owner names if they exist
        foreach (['Provider', 'Payer', 'Internal', 'Regulatory Body'] as $name) {
            DelayOwner::firstOrCreate(['name' => $name], ['is_active' => true]);
        }

        $delayOwnerMap = DelayOwner::pluck('id', 'name');

        $statuses = [
            ['name' => 'Not Started', 'sort_order' => 1, 'dashboard_category' => 'not_started', 'delay_owner' => null],
            ['name' => 'Pending Internal Review', 'sort_order' => 2, 'dashboard_category' => 'internal', 'delay_owner' => 'Revantage Team'],
            ['name' => 'Documents Requested from Provider', 'sort_order' => 3, 'dashboard_category' => 'provider', 'delay_owner' => 'Provider/Practice'],
            ['name' => 'Received - Under Review', 'sort_order' => 4, 'dashboard_category' => 'internal', 'delay_owner' => 'Revantage Team'],
            ['name' => 'Ready to File', 'sort_order' => 5, 'dashboard_category' => 'internal', 'delay_owner' => null],
            ['name' => 'Application Filed / Submitted', 'sort_order' => 6, 'dashboard_category' => 'payer', 'delay_owner' => 'Payer'],
            ['name' => 'At Payer', 'sort_order' => 7, 'dashboard_category' => 'payer', 'delay_owner' => 'Payer'],
            ['name' => 'Additional Information Requested by Payer', 'sort_order' => 8, 'dashboard_category' => 'payer', 'delay_owner' => 'Provider/Practice'],
            ['name' => 'Escalated to Payer', 'sort_order' => 9, 'dashboard_category' => 'payer', 'delay_owner' => 'Payer'],
            ['name' => 'Follow-up in Progress', 'sort_order' => 10, 'dashboard_category' => 'internal', 'delay_owner' => 'Revantage Team'],
            ['name' => 'Approved', 'sort_order' => 11, 'dashboard_category' => 'approved', 'delay_owner' => 'No Delay / Closed'],
            ['name' => 'Rejected / Denied', 'sort_order' => 12, 'dashboard_category' => 'closed', 'delay_owner' => 'No Delay / Closed'],
            ['name' => 'Panel Closed', 'sort_order' => 13, 'dashboard_category' => 'closed', 'delay_owner' => 'No Delay / Closed'],
            ['name' => 'Not Eligible', 'sort_order' => 14, 'dashboard_category' => 'closed', 'delay_owner' => 'No Delay / Closed'],
            ['name' => 'On Hold', 'sort_order' => 15, 'dashboard_category' => 'on_hold', 'delay_owner' => null],
            ['name' => 'Terminated', 'sort_order' => 16, 'dashboard_category' => 'closed', 'delay_owner' => 'No Delay / Closed'],
            ['name' => 'Revalidation / Recredentialing Due', 'sort_order' => 17, 'dashboard_category' => 'revalidation', 'delay_owner' => 'Provider/Practice'],
        ];

        foreach ($statuses as $status) {
            Status::firstOrCreate(
                ['name' => $status['name']],
                [
                    'is_active' => true,
                    'sort_order' => $status['sort_order'],
                    'dashboard_category' => $status['dashboard_category'],
                    'delay_owner_id' => $status['delay_owner'] ? ($delayOwnerMap[$status['delay_owner']] ?? null) : null,
                ]
            );
        }

        $caseTypes = [
            'New Application', 'Renewal', 'Amendment', 'Correction',
            'Credentialing', 'Participation', 'Reassignment', 'EFT', 'ERA',
            'Roster Enrollment', 'Group Enrollment', 'Recredentialing',
        ];
        foreach ($caseTypes as $caseType) {
            CaseType::firstOrCreate(['name' => $caseType], ['is_active' => true]);
        }

        $priorities = [
            ['name' => 'Critical', 'sort_order' => 1],
            ['name' => 'High', 'sort_order' => 2],
            ['name' => 'Medium', 'sort_order' => 3],
            ['name' => 'Low', 'sort_order' => 4],
        ];
        foreach ($priorities as $p) {
            Priority::firstOrCreate(['name' => $p['name']], $p + ['is_active' => true]);
        }

        $docTypes = [
            'ID Proof', 'State License', 'DEA Certificate', 'Board Certification',
            'Malpractice Insurance', 'W-9', 'Voided Check', 'CV / Resume',
            'CAQH Attestation', 'Tax Document', 'Payer Form', 'Agreement',
        ];
        foreach ($docTypes as $docType) {
            DocumentType::firstOrCreate(['name' => $docType], ['is_active' => true]);
        }
    }
}
