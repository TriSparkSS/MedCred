<?php

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Livewire\Admin\Credential\CredentialDetailsPage;
use App\Livewire\Admin\Task\TaskBoardPage;
use App\Models\Admin;
use App\Models\CredentialingCase;
use App\Models\Payer;
use App\Models\Practice;
use App\Models\ProviderDetails;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(AdminPermissionSeeder::class);
    $this->seed(AdminSeeder::class);
    $this->seed(MasterDataSeeder::class);
});

function bulkTaskPractice(): Practice
{
    return Practice::create([
        'legal_name' => 'Bulk Task Practice',
        'client_code' => 'BTP',
        'email' => 'bulk-task-practice@example.com',
        'status' => 'active',
        'user_id' => User::create([
            'name' => 'Bulk Task Practice User',
            'email' => 'bulk-task-practice-user@example.com',
            'password' => 'password',
        ])->id,
    ]);
}

function bulkTaskProvider(): ProviderDetails
{
    return ProviderDetails::create([
        'user_id' => User::create([
            'name' => 'Dr. Bulk Task',
            'email' => 'dr-bulk-task@example.com',
            'password' => 'password',
        ])->id,
        'npi' => '1555555555',
        'status' => 'approved',
        'practice' => 'Bulk Task Practice',
        'address' => '2 Bulk St',
        'city' => 'Austin',
        'state' => 'TX',
        'zip' => '78701',
    ]);
}

function bulkTaskCase(?Practice $practice = null, ?ProviderDetails $provider = null): CredentialingCase
{
    $practice ??= bulkTaskPractice();
    $provider ??= bulkTaskProvider();
    $provider->practices()->syncWithoutDetaching([$practice->id => ['primary_flag' => true]]);

    return CredentialingCase::create([
        'practice_id' => $practice->id,
        'provider_id' => $provider->id,
        'payer_id' => Payer::create(['name' => 'Bulk Payer '.uniqid(), 'is_active' => true])->id,
        'status_id' => Status::where('name', 'At Payer')->firstOrFail()->id,
        'state' => 'TX',
    ]);
}

function bulkOpenTask(CredentialingCase $case, array $overrides = []): Task
{
    return Task::create(array_merge([
        'title' => 'Open follow-up',
        'credentialing_case_id' => $case->id,
        'provider_id' => $case->provider_id,
        'payer_id' => $case->payer_id,
        'status' => TaskStatus::Open->value,
        'task_type' => TaskType::FollowUp->value,
        'due_date' => now()->addDays(3)->toDateString(),
    ], $overrides));
}

test('bulk assign updates selected tasks only', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $assignee = Admin::where('username', 'manager')->firstOrFail();
    $case = bulkTaskCase();

    $selectedA = bulkOpenTask($case, ['title' => 'Assign me A']);
    $selectedB = bulkOpenTask($case, ['title' => 'Assign me B']);
    $unselected = bulkOpenTask($case, [
        'title' => 'Leave me',
        'assigned_admin_id' => $admin->id,
    ]);

    Livewire::actingAs($admin, 'admin')
        ->test(TaskBoardPage::class)
        ->set('selectedTaskIds', [$selectedA->id, $selectedB->id])
        ->set('bulkAssigneeId', (string) $assignee->id)
        ->call('bulkAssignSelected')
        ->assertHasNoErrors();

    expect($selectedA->fresh()->assigned_admin_id)->toBe($assignee->id)
        ->and($selectedB->fresh()->assigned_admin_id)->toBe($assignee->id)
        ->and($unselected->fresh()->assigned_admin_id)->toBe($admin->id);
});

test('bulk complete and cancel update only selected tasks', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $case = bulkTaskCase();

    $completeMe = bulkOpenTask($case, ['title' => 'Complete me']);
    $cancelMe = bulkOpenTask($case, ['title' => 'Cancel me']);
    $leaveMe = bulkOpenTask($case, ['title' => 'Leave open']);

    Livewire::actingAs($admin, 'admin')
        ->test(TaskBoardPage::class)
        ->set('selectedTaskIds', [$completeMe->id])
        ->set('bulkStatus', 'completed')
        ->call('bulkUpdateSelected')
        ->assertHasNoErrors();

    expect($completeMe->fresh()->status)->toBe(TaskStatus::Completed)
        ->and($completeMe->fresh()->completed_at)->not->toBeNull()
        ->and($leaveMe->fresh()->status)->toBe(TaskStatus::Open);

    Livewire::actingAs($admin, 'admin')
        ->test(TaskBoardPage::class)
        ->set('selectedTaskIds', [$cancelMe->id])
        ->set('bulkStatus', 'cancelled')
        ->call('bulkUpdateSelected')
        ->assertHasNoErrors();

    expect($cancelMe->fresh()->status)->toBe(TaskStatus::Cancelled)
        ->and($cancelMe->fresh()->completed_at)->toBeNull()
        ->and($leaveMe->fresh()->status)->toBe(TaskStatus::Open);
});

test('bulk follow-ups create one task per selected case', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = bulkTaskPractice();
    $provider = bulkTaskProvider();
    $caseA = bulkTaskCase($practice, $provider);
    $caseB = bulkTaskCase($practice, $provider);

    Livewire::actingAs($admin, 'admin')
        ->test(TaskBoardPage::class)
        ->call('openBulkFollowUpModal')
        ->set('followUpCaseIds', [$caseA->id, $caseB->id])
        ->set('followUpForm.title', 'Payer check-in')
        ->set('followUpForm.due_date', '2026-10-01')
        ->set('followUpForm.assigned_admin_id', $admin->id)
        ->set('followUpForm.repeat', false)
        ->set('followUpForm.sync_case_follow_up', true)
        ->call('saveBulkFollowUps')
        ->assertHasNoErrors();

    $tasks = Task::query()->where('title', 'Payer check-in')->orderBy('credentialing_case_id')->get();

    expect($tasks)->toHaveCount(2)
        ->and($tasks->pluck('credentialing_case_id')->all())->toEqualCanonicalizing([$caseA->id, $caseB->id])
        ->and($caseA->fresh()->next_follow_up_date?->toDateString())->toBe('2026-10-01')
        ->and($caseB->fresh()->next_follow_up_date?->toDateString())->toBe('2026-10-01');
});

test('repeating follow-ups create a dated series on one case', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $case = bulkTaskCase();

    Livewire::actingAs($admin, 'admin')
        ->test(TaskBoardPage::class)
        ->call('openBulkFollowUpModal')
        ->set('followUpCaseIds', [$case->id])
        ->set('followUpForm.title', 'Weekly payer follow-up')
        ->set('followUpForm.due_date', '2026-10-01')
        ->set('followUpForm.assigned_admin_id', $admin->id)
        ->set('followUpForm.repeat', true)
        ->set('followUpForm.interval_days', 7)
        ->set('followUpForm.occurrences', 3)
        ->set('followUpForm.sync_case_follow_up', false)
        ->call('saveBulkFollowUps')
        ->assertHasNoErrors();

    $tasks = Task::query()
        ->where('credentialing_case_id', $case->id)
        ->where('title', 'like', 'Weekly payer follow-up%')
        ->orderBy('due_date')
        ->get();

    expect($tasks)->toHaveCount(3)
        ->and($tasks->pluck('title')->all())->toBe([
            'Weekly payer follow-up (1/3)',
            'Weekly payer follow-up (2/3)',
            'Weekly payer follow-up (3/3)',
        ])
        ->and($tasks->map(fn (Task $task) => $task->due_date->toDateString())->all())->toBe([
            '2026-10-01',
            '2026-10-08',
            '2026-10-15',
        ]);
});

test('executive without assign permission cannot bulk assign', function () {
    $executive = Admin::where('username', 'executive')->firstOrFail();
    $assignee = Admin::where('username', 'manager')->firstOrFail();
    $task = bulkOpenTask(bulkTaskCase(), ['title' => 'Stay unassigned']);

    Livewire::actingAs($executive, 'admin')
        ->test(TaskBoardPage::class)
        ->set('selectedTaskIds', [$task->id])
        ->set('bulkAssigneeId', (string) $assignee->id)
        ->call('bulkAssignSelected')
        ->assertForbidden();

    expect($task->fresh()->assigned_admin_id)->toBeNull();
});

test('case tasks tab bulk assigns and creates a repeating follow-up series', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $assignee = Admin::where('username', 'manager')->firstOrFail();
    $case = bulkTaskCase();
    $task = bulkOpenTask($case, ['title' => 'Case tab assign me']);

    Livewire::actingAs($admin, 'admin')
        ->test(CredentialDetailsPage::class, ['case' => $case])
        ->set('activeTab', 'tasks')
        ->set('selectedTaskIds', [$task->id])
        ->set('bulkAssigneeId', (string) $assignee->id)
        ->call('bulkAssignSelected')
        ->assertHasNoErrors()
        ->set('newTaskTitle', 'Case series follow-up')
        ->set('newTaskDueDate', '2026-11-01')
        ->set('newTaskAssigneeId', (string) $admin->id)
        ->set('newTaskRepeat', true)
        ->set('newTaskIntervalDays', 7)
        ->set('newTaskOccurrences', 3)
        ->call('createFollowUpTask')
        ->assertHasNoErrors();

    expect($task->fresh()->assigned_admin_id)->toBe($assignee->id);

    $series = Task::query()
        ->where('credentialing_case_id', $case->id)
        ->where('title', 'like', 'Case series follow-up%')
        ->orderBy('due_date')
        ->get();

    expect($series)->toHaveCount(3)
        ->and($series->map(fn (Task $item) => $item->due_date->toDateString())->all())->toBe([
            '2026-11-01',
            '2026-11-08',
            '2026-11-15',
        ]);
});
