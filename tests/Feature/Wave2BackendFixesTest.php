<?php

use App\Enums\AdminRole;
use App\Enums\TaskType;
use App\Models\Admin;
use App\Models\AdminNotification;
use App\Models\CaseActivity;
use App\Models\CaseSlaTimer;
use App\Models\CredentialingCase;
use App\Models\DelayOwner;
use App\Models\DelayOwnerHistory;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Location;
use App\Models\Payer;
use App\Models\Practice;
use App\Models\ProviderDetails;
use App\Models\ProviderPracticeLocation;
use App\Models\SlaRule;
use App\Models\Status;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use App\Services\CredentialingCaseService;
use App\Services\DelayOwnershipService;
use App\Services\DocumentService;
use App\Services\ProviderLocationService;
use App\Services\SlaEngineService;
use App\Services\TaskNotificationService;
use App\Services\TaskService;
use App\Support\UsStates;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\Phase2Seeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(AdminPermissionSeeder::class);
    $this->seed(AdminSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(MasterDataSeeder::class);
    Storage::fake('public');
});

function wave2PracticeAndProvider(string $tag): array
{
    $pUser = User::create([
        'name' => "Practice {$tag}",
        'email' => "practice-{$tag}@example.com",
        'password' => 'password123',
    ]);
    $practice = Practice::create([
        'user_id' => $pUser->id,
        'legal_name' => "Practice {$tag} Group",
        'client_code' => "W2{$tag}",
        'email' => "practice-{$tag}@example.com",
        'status' => 'active',
    ]);

    $drUser = User::create([
        'name' => "Dr. {$tag}",
        'email' => "dr-{$tag}@example.com",
        'password' => 'password123',
    ]);
    $provider = ProviderDetails::create([
        'user_id' => $drUser->id,
        'npi' => str_pad((string) crc32($tag), 10, '0', STR_PAD_LEFT),
        'status' => 'active',
    ]);
    $provider->practices()->attach($practice->id, ['primary_flag' => true]);

    return [$practice, $provider];
}

test('Phase2Seeder runs cleanly and SlaEngineService creates timers and deduplicated tasks', function () {
    $this->seed(Phase2Seeder::class);

    expect(SlaRule::count())->toBe(5)
        ->and(SlaRule::where('rule_key', 'payer_follow_up')->value('business_days'))->toBe(7)
        ->and(SlaRule::where('rule_key', 'payer_follow_up')->value('dashboard_category'))->toBe('payer');

    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    [$practice, $provider] = wave2PracticeAndProvider('SLA');
    $payer = Payer::create(['name' => 'UnitedHealthcare', 'is_active' => true]);
    $payerStatus = Status::where('name', 'At Payer')->firstOrFail();

    $case = app(CredentialingCaseService::class)->create([
        'practice_id' => $practice->id,
        'provider_id' => $provider->id,
        'payer_id' => $payer->id,
        'status_id' => $payerStatus->id,
        'assigned_admin_id' => $admin->id,
        'state' => 'TX',
    ], $admin->id);

    $timer = CaseSlaTimer::where('credentialing_case_id', $case->id)->where('rule_key', 'payer_follow_up')->first();
    expect($timer)->not->toBeNull();

    $timer->update(['due_at' => now()->subHour()]);

    $slaEngine = app(SlaEngineService::class);
    $result = $slaEngine->runDueChecks();

    expect($result['tasks'])->toBe(1)
        ->and(Task::where('credentialing_case_id', $case->id)->where('automation_key', 'sla_task:'.$case->id.':payer_follow_up')->count())->toBe(1);
});

test('CredentialingCaseService::changeStatus creates 1 status_change CaseActivity and DelayOwnershipService dispatches DelayOwnerChanged', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    [$practice, $provider] = wave2PracticeAndProvider('CHG');
    $payer = Payer::create(['name' => 'BCBS', 'is_active' => true]);
    $notStarted = Status::where('name', 'Not Started')->firstOrFail();
    $atPayer = Status::where('name', 'At Payer')->firstOrFail();

    $caseService = app(CredentialingCaseService::class);
    $case = $caseService->create([
        'practice_id' => $practice->id,
        'provider_id' => $provider->id,
        'payer_id' => $payer->id,
        'status_id' => $notStarted->id,
        'assigned_admin_id' => $admin->id,
        'state' => 'TX',
    ], $admin->id);

    expect(CaseActivity::where('credentialing_case_id', $case->id)->where('activity_type', 'status_change')->count())->toBe(1);

    $caseService->changeStatus($case, $atPayer->id, $admin->id, 'Moved to payer');

    expect(CaseActivity::where('credentialing_case_id', $case->id)->where('activity_type', 'status_change')->count())->toBe(2);

    $internalOwner = DelayOwner::where('name', 'Revantage Team')->firstOrFail();
    app(DelayOwnershipService::class)->overrideDelayOwner($case->fresh(), $internalOwner->id, 'Internal QA needed', $admin->id);

    expect(DelayOwnerHistory::where('credentialing_case_id', $case->id)->where('delay_owner_id', $internalOwner->id)->exists())->toBeTrue()
        ->and(CaseActivity::where('credentialing_case_id', $case->id)->where('activity_type', 'delay_change')->exists())->toBeTrue();
});

test('DocumentService::upload for two different providers with same state-specific type and state does not overwrite the first provider document', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    [$practiceA, $providerA] = wave2PracticeAndProvider('DOCA');
    [$practiceB, $providerB] = wave2PracticeAndProvider('DOCB');

    $stateLicenseType = DocumentType::where('name', 'State License')->firstOrFail();
    $docService = app(DocumentService::class);

    $docA = $docService->upload([
        'provider_id' => $providerA->id,
        'document_type_id' => $stateLicenseType->id,
        'title' => 'Provider A TX License',
        'document_number' => 'TX-A-100',
        'state' => 'TX',
    ], UploadedFile::fake()->create('provider-a-tx.pdf', 50, 'application/pdf'), $admin->id);

    $docB = $docService->upload([
        'provider_id' => $providerB->id,
        'document_type_id' => $stateLicenseType->id,
        'title' => 'Provider B TX License',
        'document_number' => 'TX-B-200',
        'state' => 'TX',
    ], UploadedFile::fake()->create('provider-b-tx.pdf', 50, 'application/pdf'), $admin->id);

    // Also upload a Practice-scoped TX State License for Practice A to verify entity scoping
    $docPracticeA = $docService->upload([
        'provider_id' => $providerA->id,
        'practice_id' => $practiceA->id,
        'document_type_id' => $stateLicenseType->id,
        'title' => 'Provider A Practice Scoped TX License',
        'document_number' => 'TX-PA-300',
        'state' => 'TX',
    ], UploadedFile::fake()->create('provider-a-practice-tx.pdf', 50, 'application/pdf'), $admin->id);

    expect($docA->id)->not->toBe($docB->id)
        ->and($docA->id)->not->toBe($docPracticeA->id)
        ->and($docA->fresh()->title)->toBe('Provider A TX License')
        ->and($docB->fresh()->title)->toBe('Provider B TX License');
});

test('TaskService::update dispatches TaskReassigned and TaskNotificationService deduplicates daily reminders', function () {
    $creator = Admin::where('username', 'superadmin')->firstOrFail();
    $exec1 = Admin::create([
        'name' => 'Exec One',
        'username' => 'exec_one',
        'email' => 'exec1@example.com',
        'password' => 'password123',
        'status' => 1,
    ]);
    $exec1->assignRole(AdminRole::CredentialingExecutive->value);

    $exec2 = Admin::create([
        'name' => 'Exec Two',
        'username' => 'exec_two',
        'email' => 'exec2@example.com',
        'password' => 'password123',
        'status' => 1,
    ]);
    $exec2->assignRole(AdminRole::CredentialingExecutive->value);

    $taskService = app(TaskService::class);
    $task = $taskService->create([
        'title' => 'Due Tomorrow Task',
        'task_type' => TaskType::Manual->value,
        'assigned_admin_id' => $exec1->id,
        'due_date' => now()->addDay()->toDateString(),
    ], $creator->id);

    $taskService->update($task, [
        'assigned_admin_id' => $exec2->id,
    ], $creator->id);

    expect(TaskActivity::where('task_id', $task->id)->where('activity_type', 'reassigned')->count())->toBe(1)
        ->and(AdminNotification::where('admin_id', $exec2->id)->where('type', 'task_assigned')->count())->toBe(1);

    $notificationService = app(TaskNotificationService::class);
    $firstRun = $notificationService->sendDueTomorrowReminders();
    $secondRun = $notificationService->sendDueTomorrowReminders();

    expect($firstRun)->toBe(1)
        ->and($secondRun)->toBe(0)
        ->and(AdminNotification::where('admin_id', $exec2->id)->where('type', 'task_due_tomorrow')->count())->toBe(1);
});

test('UsStates::normalize rejects invalid 2-letter codes and ProviderLocationService promotes remaining primary location on sync', function () {
    expect(UsStates::normalize('XX'))->toBeNull()
        ->and(UsStates::normalize('ZZZ'))->toBeNull()
        ->and(UsStates::normalize('CA'))->toBe('CA')
        ->and(UsStates::normalize('California'))->toBe('CA');

    [$practice, $provider] = wave2PracticeAndProvider('LOC');

    $loc1 = Location::create([
        'practice_id' => $practice->id,
        'name' => 'Main Clinic',
        'address1' => '100 Main St',
        'city' => 'Austin',
        'state' => 'TX',
        'zip_code' => '78701',
        'is_primary' => true,
        'status' => 'active',
    ]);
    $loc2 = Location::create([
        'practice_id' => $practice->id,
        'name' => 'North Clinic',
        'address1' => '200 North St',
        'city' => 'Austin',
        'state' => 'TX',
        'zip_code' => '78702',
        'is_primary' => false,
        'status' => 'active',
    ]);

    $locationService = app(ProviderLocationService::class);
    $locationService->syncForPractice($provider, $practice->id, [$loc1->id, $loc2->id]);

    expect(ProviderPracticeLocation::where('provider_id', $provider->id)->where('location_id', $loc1->id)->value('is_primary'))->toBeTrue()
        ->and(ProviderPracticeLocation::where('provider_id', $provider->id)->where('location_id', $loc2->id)->value('is_primary'))->toBeFalse();

    // Remove the primary location (loc1), leaving only loc2
    $locationService->syncForPractice($provider, $practice->id, [$loc2->id]);

    expect(ProviderPracticeLocation::where('provider_id', $provider->id)->count())->toBe(1)
        ->and(ProviderPracticeLocation::where('provider_id', $provider->id)->where('location_id', $loc2->id)->value('is_primary'))->toBeTrue();
});
