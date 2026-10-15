<?php

use App\Enums\AdminRole;
use App\Enums\TaskType;
use App\Models\Admin;
use App\Models\AdminNotification;
use App\Models\AuditLog;
use App\Models\CaseActivity;
use App\Models\CaseSlaTimer;
use App\Models\CaseStatusHistory;
use App\Models\CredentialingCase;
use App\Models\Payer;
use App\Models\Practice;
use App\Models\ProviderDetails;
use App\Models\SlaRule;
use App\Models\Status;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use App\Services\CredentialingCaseService;
use App\Services\FollowUpEngine;
use App\Services\PortalActionItemsService;
use App\Services\PortalAssignedItemsService;
use App\Services\TaskService;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(AdminPermissionSeeder::class);
    $this->seed(AdminSeeder::class);
    $this->seed(RoleSeeder::class);
});

function createPracticeAndProvider(string $suffix = 'A'): array
{
    $practiceUser = User::create([
        'name' => "Practice {$suffix}",
        'email' => "practice-{$suffix}@example.com",
        'password' => 'password123',
    ]);

    $practice = Practice::create([
        'user_id' => $practiceUser->id,
        'legal_name' => "Practice {$suffix} LLC",
        'client_code' => "PR{$suffix}01",
        'email' => "practice-{$suffix}@example.com",
        'status' => 'active',
    ]);

    $providerUser = User::create([
        'name' => "Dr. Provider {$suffix}",
        'email' => "provider-{$suffix}@example.com",
        'password' => 'password123',
    ]);

    $provider = ProviderDetails::create([
        'user_id' => $providerUser->id,
        'npi' => str_pad((string) crc32($suffix), 10, '0', STR_PAD_LEFT),
        'status' => 'active',
    ]);

    $provider->practices()->syncWithoutDetaching([
        $practice->id => ['primary_flag' => true],
    ]);

    return [$practice, $provider];
}

test('case creation and status change fire listeners once without duplicate timeline activities, sla timers, or notifications', function () {
    $creator = Admin::where('username', 'superadmin')->firstOrFail();
    $assignee = Admin::create([
        'name' => 'Specialist Assignee',
        'username' => 'specialist_assignee',
        'email' => 'specialist-assignee@example.com',
        'password' => 'password123',
        'status' => 1,
    ]);
    $assignee->assignRole(AdminRole::CredentialingExecutive->value);

    [$practice, $provider] = createPracticeAndProvider('EVT');

    $payer = Payer::create([
        'name' => 'Aetna Test Payer',
        'slug' => 'aetna-test-payer',
        'payer_type' => 'Commercial',
    ]);

    $intakeStatus = Status::create([
        'name' => 'Intake Pending',
        'slug' => 'intake-pending',
        'dashboard_category' => 'provider',
        'sort_order' => 1,
    ]);

    $submittedStatus = Status::create([
        'name' => 'Application Submitted',
        'slug' => 'application-submitted',
        'dashboard_category' => 'payer',
        'sort_order' => 2,
    ]);

    $caseService = app(CredentialingCaseService::class);

    $case = $caseService->create([
        'provider_id' => $provider->id,
        'practice_id' => $practice->id,
        'payer_id' => $payer->id,
        'status_id' => $intakeStatus->id,
        'assigned_admin_id' => $assignee->id,
        'state' => 'TX',
    ], $creator->id);

    expect(CaseStatusHistory::where('credentialing_case_id', $case->id)->count())->toBe(1)
        ->and(CaseActivity::where('credentialing_case_id', $case->id)->where('activity_type', 'status_change')->count())->toBe(1)
        ->and(AuditLog::where('action', 'case.status_changed')->where('auditable_id', $case->id)->count())->toBe(1);

    $caseService->changeStatus($case, $submittedStatus->id, $creator->id, 'Submitted to payer');

    expect(CaseStatusHistory::where('credentialing_case_id', $case->id)->count())->toBe(2)
        ->and(CaseActivity::where('credentialing_case_id', $case->id)->where('activity_type', 'status_change')->count())->toBe(2)
        ->and(AuditLog::where('action', 'case.status_changed')->where('auditable_id', $case->id)->count())->toBe(2)
        ->and(Task::where('credentialing_case_id', $case->id)->where('automation_key', 'payer_follow_up:'.$case->id)->count())->toBe(1);

    $taskService = app(TaskService::class);
    $task = $taskService->create([
        'title' => 'Single Notification Check',
        'credentialing_case_id' => $case->id,
        'provider_id' => $provider->id,
        'task_type' => TaskType::Manual->value,
        'assigned_admin_id' => $assignee->id,
        'due_date' => now()->addDays(2)->toDateString(),
    ], $creator->id);

    expect(TaskActivity::where('task_id', $task->id)->where('activity_type', 'created')->count())->toBe(1)
        ->and(TaskActivity::where('task_id', $task->id)->where('activity_type', 'assigned')->count())->toBe(1)
        ->and(AdminNotification::where('admin_id', $assignee->id)->where('type', 'task_assigned')->where('message', 'Single Notification Check')->count())->toBe(1);
});

test('processPayerNoResponseCases and tasks:process-due-reminders execute without SQL error and create escalation task', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    [$practice, $provider] = createPracticeAndProvider('PNR');

    $payer = Payer::create([
        'name' => 'BCBS Payer',
        'slug' => 'bcbs-payer',
        'payer_type' => 'Commercial',
    ]);

    $payerStatus = Status::create([
        'name' => 'At Payer Review',
        'slug' => 'at-payer-review',
        'dashboard_category' => 'payer',
        'sort_order' => 1,
    ]);

    $staleCase = CredentialingCase::create([
        'provider_id' => $provider->id,
        'practice_id' => $practice->id,
        'payer_id' => $payer->id,
        'status_id' => $payerStatus->id,
        'assigned_admin_id' => $admin->id,
        'state' => 'TX',
        'last_action_at' => now()->subDays(15),
    ]);
    CredentialingCase::whereKey($staleCase->id)->update([
        'last_action_at' => now()->subDays(15),
    ]);

    $recentEmailCase = CredentialingCase::create([
        'provider_id' => $provider->id,
        'practice_id' => $practice->id,
        'payer_id' => $payer->id,
        'status_id' => $payerStatus->id,
        'assigned_admin_id' => $admin->id,
        'state' => 'NY',
        'last_action_at' => now()->subDays(15),
    ]);
    CredentialingCase::whereKey($recentEmailCase->id)->update([
        'last_action_at' => now()->subDays(15),
    ]);
    CaseActivity::create([
        'credentialing_case_id' => $recentEmailCase->id,
        'activity_type' => 'email_received',
        'summary' => 'Inbound payer email received',
    ]);

    $this->artisan('tasks:process-due-reminders')
        ->assertExitCode(0);

    expect($staleCase->fresh()->is_escalated)->toBeTrue()
        ->and(Task::where('credentialing_case_id', $staleCase->id)->where('automation_key', 'payer_no_response:'.$staleCase->id)->exists())->toBeTrue()
        ->and($recentEmailCase->fresh()->is_escalated)->toBeFalse()
        ->and(Task::where('credentialing_case_id', $recentEmailCase->id)->where('automation_key', 'payer_no_response:'.$recentEmailCase->id)->exists())->toBeFalse();
});

test('PortalActionItemsService and PortalAssignedItemsService do not fail with ambiguous id and do not leak tasks across practices for shared providers', function () {
    [$practiceA, $sharedProvider] = createPracticeAndProvider('SHRA');
    [$practiceB] = createPracticeAndProvider('SHRB');

    // Link the shared provider to Practice B as well
    $sharedProvider->practices()->syncWithoutDetaching([
        $practiceB->id => ['primary_flag' => false],
    ]);

    $payer = Payer::create([
        'name' => 'UnitedHealthcare',
        'slug' => 'unitedhealthcare',
        'payer_type' => 'Commercial',
    ]);

    $status = Status::create([
        'name' => 'Provider Action Needed',
        'slug' => 'provider-action-needed',
        'dashboard_category' => 'provider',
        'sort_order' => 1,
    ]);

    $caseA = CredentialingCase::create([
        'provider_id' => $sharedProvider->id,
        'practice_id' => $practiceA->id,
        'payer_id' => $payer->id,
        'status_id' => $status->id,
        'state' => 'TX',
    ]);

    $caseB = CredentialingCase::create([
        'provider_id' => $sharedProvider->id,
        'practice_id' => $practiceB->id,
        'payer_id' => $payer->id,
        'status_id' => $status->id,
        'state' => 'CA',
    ]);

    $taskForPracticeA = Task::create([
        'title' => 'Upload DEA for Practice A',
        'provider_id' => $sharedProvider->id,
        'credentialing_case_id' => $caseA->id,
        'task_type' => TaskType::Document->value,
        'status' => 'open',
        'due_date' => now()->addDays(3)->toDateString(),
    ]);

    $taskForPracticeB = Task::create([
        'title' => 'Upload License for Practice B',
        'provider_id' => $sharedProvider->id,
        'credentialing_case_id' => $caseB->id,
        'task_type' => TaskType::Document->value,
        'status' => 'open',
        'due_date' => now()->addDays(3)->toDateString(),
    ]);

    $generalProviderTask = Task::create([
        'title' => 'Update General CAQH Profile',
        'provider_id' => $sharedProvider->id,
        'credentialing_case_id' => null,
        'task_type' => TaskType::ProviderFollowUp->value,
        'status' => 'open',
        'due_date' => now()->addDays(4)->toDateString(),
    ]);

    $actionItemsService = app(PortalActionItemsService::class);
    $assignedItemsService = app(PortalAssignedItemsService::class);

    $practiceATasks = $actionItemsService->practiceTasks($practiceA);
    $practiceBTasks = $actionItemsService->practiceTasks($practiceB);

    expect($practiceATasks->pluck('id')->all())
        ->toContain($taskForPracticeA->id, $generalProviderTask->id)
        ->not->toContain($taskForPracticeB->id);

    expect($practiceBTasks->pluck('id')->all())
        ->toContain($taskForPracticeB->id, $generalProviderTask->id)
        ->not->toContain($taskForPracticeA->id);

    $practiceAAssignedIds = $assignedItemsService->forPractice($practiceA, 'open')->pluck('id')->all();
    $practiceBAssignedIds = $assignedItemsService->forPractice($practiceB, 'open')->pluck('id')->all();

    expect($practiceAAssignedIds)
        ->toContain('task-'.$taskForPracticeA->id, 'task-'.$generalProviderTask->id)
        ->not->toContain('task-'.$taskForPracticeB->id);

    expect($practiceBAssignedIds)
        ->toContain('task-'.$taskForPracticeB->id, 'task-'.$generalProviderTask->id)
        ->not->toContain('task-'.$taskForPracticeA->id);
});
