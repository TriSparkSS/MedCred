<?php

namespace Database\Seeders;

use App\Enums\PracticeStatus;
use App\Enums\ProviderStatus;
use App\Models\Admin;
use App\Models\Address;
use App\Models\CaseActivity;
use App\Models\CaseStatusHistory;
use App\Models\CaseType;
use App\Models\CredentialingCase;
use App\Models\DocumentType;
use App\Models\Location;
use App\Models\Payer;
use App\Models\PayerDocumentRequirement;
use App\Models\Practice;
use App\Models\PracticeContact;
use App\Models\Priority;
use App\Models\ProviderDetails;
use App\Models\Specialty;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskSyncService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DummyContentSeeder extends Seeder
{
    public const COUNT = 10;

    private const PASSWORD = '112233';

    private array $states = ['TX', 'CA', 'NY', 'FL', 'IL', 'PA', 'OH', 'GA', 'NC', 'AZ'];

    private array $specialtyNames = [
        'Family Medicine',
        'Internal Medicine',
        'Pediatrics',
        'Cardiology',
        'Dermatology',
        'Orthopedic Surgery',
        'Psychiatry',
        'Obstetrics & Gynecology',
        'Emergency Medicine',
        'Radiology',
    ];

    private array $payerNames = [
        'Blue Cross Blue Shield',
        'Aetna',
        'UnitedHealthcare',
        'Cigna',
        'Humana',
        'Anthem',
        'Medicare',
        'Medicaid',
        'Tricare',
        'Kaiser Permanente',
    ];

    private array $activityTypes = ['note', 'call', 'email', 'status_change', 'system'];

    public function run(): void
    {
        $this->ensurePrerequisites();

        $admin = Admin::first();
        $statuses = Status::where('is_active', true)->orderBy('sort_order')->get();
        $priorities = Priority::where('is_active', true)->orderBy('sort_order')->get();
        $caseTypes = CaseType::where('is_active', true)->get();
        $documentTypes = DocumentType::where('is_active', true)->get();

        if ($statuses->isEmpty() || $documentTypes->isEmpty()) {
            $this->command->error('Master data missing. Run MasterDataSeeder first.');

            return;
        }

        $specialties = $this->seedSpecialties();
        $practices = $this->seedPractices();
        $providers = $this->seedProviders($specialties);
        $payers = $this->seedPayers($documentTypes);

        $this->linkProvidersToPractices($providers, $practices);
        $cases = $this->seedCredentialingCases($providers, $practices, $payers, $statuses, $priorities, $caseTypes, $admin);
        $this->seedCaseActivities($cases, $admin);
        $this->seedTasks($cases, $providers, $priorities, $admin);
        $this->assignPracticesToDemoAdmins($practices);

        $this->command->info('Dummy content seeded: 10 specialties, practices, providers, payers, cases, and tasks.');
        $this->command->line('Portal login password for all dummy users: ' . self::PASSWORD);
    }

    private function assignPracticesToDemoAdmins(array $practices): void
    {
        $ids = collect($practices)->pluck('id')->all();
        if ($ids === []) {
            return;
        }

        $manager = Admin::where('username', 'manager')->first();
        $executive = Admin::where('username', 'executive')->first();
        $billing = Admin::where('username', 'billing')->first();

        $manager?->practices()->sync($ids);
        $first = [$ids[0]];
        $executive?->practices()->sync($first);
        $billing?->practices()->sync($first);
    }

    private function ensurePrerequisites(): void
    {
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            MasterDataSeeder::class,
        ]);
    }

    private function seedSpecialties(): array
    {
        $specialties = [];

        foreach ($this->specialtyNames as $index => $name) {
            $specialties[] = Specialty::firstOrCreate(
                ['name' => $name],
                ['description' => 'Dummy specialty #' . ($index + 1)]
            );
        }

        return $specialties;
    }

    private function seedPractices(): array
    {
        $practices = [];

        for ($i = 1; $i <= self::COUNT; $i++) {
            $email = "dummy-practice-{$i}@example.com";
            $legalName = "Dummy Medical Group {$i}";

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $legalName,
                    'phone' => fake()->numerify('555-####'),
                    'password' => Hash::make(self::PASSWORD),
                    'email_verified_at' => now(),
                ]
            );
            $user->syncRoles(['practice']);

            $practice = Practice::updateOrCreate(
                ['email' => $email],
                [
                    'user_id' => $user->id,
                    'legal_name' => $legalName,
                    'dba_name' => "DMG {$i} Clinic",
                    'ein_tin' => '99-' . str_pad((string) $i, 7, '0', STR_PAD_LEFT),
                    'group_npi' => '200000000' . $i,
                    'taxonomy_code' => '207Q00000X',
                    'phone' => fake()->numerify('555-####'),
                    'fax' => fake()->numerify('555-####'),
                    'website' => "https://dummy-practice-{$i}.example.com",
                    'status' => $i % 3 === 0 ? PracticeStatus::PENDING : PracticeStatus::ACTIVE,
                ]
            );

            $state = $this->states[$i - 1];
            $city = fake()->city();

            $practice->addresses()->updateOrCreate(
                ['type' => 'primary'],
                [
                    'location_name' => 'Main Office',
                    'address1' => fake()->streetAddress(),
                    'city' => $city,
                    'state' => $state,
                    'zip_code' => fake()->postcode(),
                    'country' => 'United States',
                    'phone' => $practice->phone,
                    'status' => 'active',
                ]
            );

            Location::updateOrCreate(
                ['practice_id' => $practice->id, 'name' => 'Primary Location'],
                [
                    'address1' => fake()->streetAddress(),
                    'city' => $city,
                    'state' => $state,
                    'zip_code' => fake()->postcode(),
                    'country' => 'United States',
                    'phone' => $practice->phone,
                    'is_primary' => true,
                    'status' => 'active',
                ]
            );

            PracticeContact::updateOrCreate(
                ['practice_id' => $practice->id, 'email' => "contact-{$i}@dummy-practice.example.com"],
                [
                    'name' => fake()->name(),
                    'title' => 'Office Manager',
                    'phone' => fake()->numerify('555-####'),
                    'is_primary' => true,
                ]
            );

            $practices[] = $practice->fresh(['user', 'locations', 'contacts']);
        }

        return $practices;
    }

    private function seedProviders(array $specialties): array
    {
        $providers = [];

        for ($i = 1; $i <= self::COUNT; $i++) {
            $email = "dummy-provider-{$i}@example.com";
            $name = "Dr. " . fake()->lastName() . " {$i}";

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'phone' => fake()->numerify('555-####'),
                    'password' => Hash::make(self::PASSWORD),
                    'email_verified_at' => now(),
                ]
            );
            $user->syncRoles(['provider']);

            $state = $this->states[$i - 1];

            $provider = ProviderDetails::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'specialty_id' => $specialties[$i - 1]->id,
                    'status' => ProviderStatus::APPROVED,
                    'npi' => '100000000' . $i,
                    'caqh_id' => (string) (10000000 + $i),
                    'license_number' => 'LIC-' . str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                    'license_state' => $state,
                    'dea' => 'AB' . str_pad((string) $i, 7, '0', STR_PAD_LEFT),
                    'taxonomy_code' => '207Q00000X',
                    'pecos_enrolled' => $i % 2 === 0,
                    'malpractice_carrier' => 'Dummy Insurance Co.',
                    'malpractice_policy_number' => 'MP-' . $i,
                    'malpractice_expiry' => now()->addYear(),
                    'board_certification' => $specialties[$i - 1]->name,
                    'board_cert_expiry' => now()->addYears(2),
                    'licensed_states' => [$state, $this->states[($i + 1) % self::COUNT]],
                    'practice' => "Dummy Medical Group {$i}",
                    'address' => fake()->streetAddress(),
                    'city' => fake()->city(),
                    'state' => $state,
                    'zip' => fake()->postcode(),
                ]
            );

            $providers[] = $provider->fresh('user');
        }

        return $providers;
    }

    private function seedPayers($documentTypes): array
    {
        $payers = [];

        for ($i = 1; $i <= self::COUNT; $i++) {
            $payer = Payer::updateOrCreate(
                ['name' => $this->payerNames[$i - 1] . ' (Dummy)'],
                [
                    'states_applicable' => implode(', ', array_slice($this->states, 0, 5)),
                    'application_type' => $i % 2 === 0 ? 'Individual' : 'Group',
                    'submission_channel' => $i % 3 === 0 ? 'Portal' : 'Paper',
                    'portal_url' => "https://payer-{$i}.example.com",
                    'email' => "payer-{$i}@example.com",
                    'phone' => fake()->numerify('800-###-####'),
                    'turnaround_days' => 30 + ($i * 3),
                    'participation_rules' => 'Dummy participation rules for testing.',
                    'is_active' => true,
                ]
            );

            foreach ($documentTypes->take(5) as $docType) {
                PayerDocumentRequirement::firstOrCreate(
                    [
                        'payer_id' => $payer->id,
                        'document_type_id' => $docType->id,
                        'state' => null,
                    ],
                    ['is_required' => true]
                );
            }

            $payers[] = $payer;
        }

        return $payers;
    }

    private function linkProvidersToPractices(array $providers, array $practices): void
    {
        foreach ($providers as $index => $provider) {
            $practice = $practices[$index];
            $provider->practices()->syncWithoutDetaching([
                $practice->id => [
                    'primary_flag' => true,
                    'start_date' => now()->subMonths(6)->toDateString(),
                ],
            ]);
        }
    }

    private function seedCredentialingCases(
        array $providers,
        array $practices,
        array $payers,
        $statuses,
        $priorities,
        $caseTypes,
        ?Admin $admin
    ): array {
        $cases = [];
        $taskSync = app(TaskSyncService::class);

        for ($i = 1; $i <= self::COUNT; $i++) {
            $provider = $providers[$i - 1];
            $practice = $practices[$i - 1];
            $payer = $payers[$i - 1];
            $state = $this->states[$i - 1];
            $status = $statuses[($i - 1) % $statuses->count()];

            $case = CredentialingCase::firstOrCreate(
                [
                    'provider_id' => $provider->id,
                    'practice_id' => $practice->id,
                    'payer_id' => $payer->id,
                ],
                [
                    'location_id' => $practice->locations->first()?->id,
                    'case_type_id' => $caseTypes->random()?->id,
                    'status_id' => $status->id,
                    'delay_owner_id' => $status->delay_owner_id,
                    'priority_id' => $priorities[($i - 1) % $priorities->count()]->id,
                    'assigned_admin_id' => $admin?->id,
                    'state' => $state,
                    'intake_date' => now()->subDays(30 - $i),
                    'submission_date' => $i > 3 ? now()->subDays(20 - $i) : null,
                    'next_follow_up_date' => now()->addDays($i * 2),
                    'expected_completion_date' => now()->addDays(45 + $i),
                    'notes' => "Dummy credentialing case #{$i} for demo and testing.",
                    'is_escalated' => $i % 5 === 0,
                    'last_action_at' => now()->subDays($i),
                ]
            );

            CaseStatusHistory::firstOrCreate(
                [
                    'credentialing_case_id' => $case->id,
                    'status_id' => $case->status_id,
                ],
                [
                    'delay_owner_id' => $case->delay_owner_id,
                    'changed_by_admin_id' => $admin?->id,
                    'notes' => 'Dummy case seeded',
                ]
            );

            if (! $case->activities()->where('summary', 'Credentialing case created')->exists()) {
                $case->addActivity('system', 'Credentialing case created', $admin?->id);
                $case->addActivity('note', $case->notes, $admin?->id);
            }

            $case->seedDocumentChecklist();
            $case->load('documentItems.documentType');

            foreach ($case->documentItems->where('is_required', true)->where('is_received', false) as $item) {
                $taskSync->ensureDocumentTask($case, $item, $admin?->id);
            }

            $cases[] = $case->fresh();
        }

        return $cases;
    }

    private function seedCaseActivities(array $cases, ?Admin $admin): void
    {
        $summaries = [
            'Called provider office — left voicemail',
            'Sent document request email',
            'Reviewed submitted license copy',
            'Payer follow-up scheduled',
            'Internal QA review completed',
            'Requested updated malpractice certificate',
            'Confirmed CAQH profile is active',
            'Escalated due to payer delay',
            'Provider confirmed receipt of checklist',
            'Status updated after payer response',
        ];

        foreach ($cases as $index => $case) {
            $type = $this->activityTypes[$index % count($this->activityTypes)];

            CaseActivity::firstOrCreate(
                [
                    'credentialing_case_id' => $case->id,
                    'summary' => $summaries[$index],
                ],
                [
                    'activity_type' => $type,
                    'admin_id' => $admin?->id,
                    'created_at' => now()->subDays(self::COUNT - $index),
                    'updated_at' => now()->subDays(self::COUNT - $index),
                ]
            );
        }
    }

    private function seedTasks(array $cases, array $providers, $priorities, ?Admin $admin): void
    {
        $taskTitles = [
            'Follow up with payer on application status',
            'Request updated W-9 from practice',
            'Verify state license expiration date',
            'Schedule provider credentialing call',
            'Review malpractice insurance coverage limits',
            'Submit corrected application to payer',
            'Confirm effective date with provider',
            'Complete internal document review',
            'Send reminder for outstanding checklist items',
            'Prepare credentialing packet for submission',
        ];

        foreach ($cases as $index => $case) {
            Task::firstOrCreate(
                [
                    'credentialing_case_id' => $case->id,
                    'title' => $taskTitles[$index],
                ],
                [
                    'description' => 'Dummy task for kanban and workflow testing.',
                    'provider_id' => $providers[$index]->id,
                    'assigned_admin_id' => $admin?->id,
                    'created_by_admin_id' => $admin?->id,
                    'priority_id' => $priorities[$index % $priorities->count()]->id,
                    'due_date' => now()->addDays($index + 1),
                    'task_type' => $index % 2 === 0 ? 'follow_up' : 'manual',
                    'completed_at' => $index % 4 === 0 ? now()->subDay() : null,
                ]
            );
        }
    }
}
