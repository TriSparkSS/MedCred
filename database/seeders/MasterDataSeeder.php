<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Status;
use App\Models\CaseType;
use App\Models\DelayOwner;
use App\Models\Priority;
use App\Models\DocumentType;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = ["Pending", "In Review", "Approved", "Rejected", "On Hold"];
        foreach ($statuses as $status) {
            Status::create(["name" => $status, "is_active" => true]);
        }

        $caseTypes = ["New Application", "Renewal", "Amendment", "Correction"];
        foreach ($caseTypes as $caseType) {
            CaseType::create(["name" => $caseType, "is_active" => true]);
        }

        $delayOwners = ["Provider", "Payer", "Internal", "Regulatory Body"];
        foreach ($delayOwners as $owner) {
            DelayOwner::create(["name" => $owner, "is_active" => true]);
        }

        $priorities = [
            ["name" => "Critical", "sort_order" => 1],
            ["name" => "High", "sort_order" => 2],
            ["name" => "Medium", "sort_order" => 3],
            ["name" => "Low", "sort_order" => 4],
        ];
        foreach ($priorities as $p) {
            Priority::create($p + ["is_active" => true]);
        }

        $docTypes = ["ID Proof", "License", "Certification", "Agreement", "Tax Document"];
        foreach ($docTypes as $docType) {
            DocumentType::create(["name" => $docType, "is_active" => true]);
        }
    }
}
