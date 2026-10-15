<?php

use App\Enums\AdminRole;
use App\Enums\ProviderStatus;
use App\Enums\TaskType;
use App\Livewire\Admin\Practices\PracticeDetailsPage;
use App\Livewire\Admin\Provider\ProviderDetailsPage;
use App\Models\Admin;
use App\Models\BusinessCalendar;
use App\Models\CaseActivity;
use App\Models\CredentialingCase;
use App\Models\DelayOwner;
use App\Models\Holiday;
use App\Models\Payer;
use App\Models\Practice;
use App\Models\ProviderDetails;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\ProductivityDashboardService;
use App\Support\BusinessDayCalculator;
use Carbon\Carbon;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    BusinessDayCalculator::clearCache();
    $this->seed(AdminPermissionSeeder::class);
    $this->seed(AdminSeeder::class);
    $this->seed(RoleSeeder::class);
});

function seedWave3PracticeAndProvider(string $suffix): array
{
    $practiceUser = User::create([
        'name' => "Practice {$suffix}",
        'email' => "practice-w3-{$suffix}@example.com",
        'password' => 'password123',
    ]);

    $practice = Practice::create([
        'user_id' => $practiceUser->id,
        'legal_name' => "Practice {$suffix} Medical Group",
        'client_code' => "W3{$suffix}01",
        'email' => "practice-w3-{$suffix}@example.com",
        'status' => 'active',
    ]);

    $providerUser = User::create([
        'name' => "Dr. Provider {$suffix}",
        'email' => "provider-w3-{$suffix}@example.com",
        'password' => 'password123',
    ]);

    $provider = ProviderDetails::create([
        'user_id' => $providerUser->id,
        'npi' => str_pad((string) abs(crc32("w3-{$suffix}")), 10, '0', STR_PAD_LEFT),
        'status' => ProviderStatus::ACTIVE->value,
    ]);

    $provider->practices()->syncWithoutDetaching([
        $practice->id => ['primary_flag' => true],
    ]);

    return [$practice, $provider];
}

test('BusinessDayCalculator memoizes default calendar and holidays with <= 2 queries across 30 business days', function () {
    $calendar = BusinessCalendar::create([
        'name' => 'US Standard Calendar',
        'exclude_weekends' => true,
        'is_default' => true,
        'is_active' => true,
    ]);

    Holiday::create([
        'business_calendar_id' => $calendar->id,
        'date' => '2026-01-19', // MLK Day (Monday)
        'name' => 'MLK Day',
    ]);

    BusinessDayCalculator::clearCache();

    $calculator = app(BusinessDayCalculator::class);
    $start = Carbon::parse('2026-01-05'); // Monday

    DB::flushQueryLog();
    DB::enableQueryLog();

    $endDate = $calculator->addBusinessDays($start, 30);
    $between = $calculator->businessDaysBetween($start, $endDate);
    $viaAddDays = $calculator->addDays($start, 30, true);

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect(count($queries))->toBeLessThanOrEqual(2)
        ->and($between)->toBe(30)
        ->and($endDate->toDateString())->toBe('2026-02-17')
        ->and($viaAddDays->toDateString())->toBe('2026-02-17');
});

test('ProductivityDashboardService::executiveMetrics pre-aggregates metrics per admin without N+1 queries', function () {
    $systemAdmin = Admin::where('username', 'superadmin')->firstOrFail();
    $this->actingAs($systemAdmin, 'admin');

    $execA = Admin::create([
        'name' => 'Executive Alpha',
        'username' => 'exec_alpha',
        'email' => 'exec-alpha@example.com',
        'password' => 'password123',
        'status' => 1,
    ]);
    $execA->assignRole(AdminRole::CredentialingExecutive->value);

    $execB = Admin::create([
        'name' => 'Executive Beta',
        'username' => 'exec_beta',
        'email' => 'exec-beta@example.com',
        'password' => 'password123',
        'status' => 1,
    ]);
    $execB->assignRole(AdminRole::CredentialingExecutive->value);

    [$practice, $provider] = seedWave3PracticeAndProvider('EXEC');

    $payer = Payer::create([
        'name' => 'Cigna Health',
        'slug' => 'cigna-health',
        'payer_type' => 'Commercial',
    ]);

    $activeStatus = Status::create([
        'name' => 'In Progress',
        'slug' => 'in-progress',
        'dashboard_category' => 'payer',
        'sort_order' => 1,
    ]);

    $approvedStatus = Status::create([
        'name' => 'Approved',
        'slug' => 'approved',
        'dashboard_category' => 'approved',
        'sort_order' => 2,
    ]);

    // Exec A: 1 active overdue case, 1 approved case with 20-day turnaround, 2 open tasks
    CredentialingCase::create([
        'provider_id' => $provider->id,
        'practice_id' => $practice->id,
        'payer_id' => $payer->id,
        'status_id' => $activeStatus->id,
        'assigned_admin_id' => $execA->id,
        'next_follow_up_date' => now()->subDays(2)->toDateString(),
        'state' => 'TX',
    ]);

    CredentialingCase::create([
        'provider_id' => $provider->id,
        'practice_id' => $practice->id,
        'payer_id' => $payer->id,
        'status_id' => $approvedStatus->id,
        'assigned_admin_id' => $execA->id,
        'submission_date' => '2026-01-01',
        'effective_date' => '2026-01-21',
        'state' => 'TX',
    ]);

    Task::create([
        'title' => 'Alpha Task 1',
        'provider_id' => $provider->id,
        'credentialing_case_id' => null,
        'assigned_admin_id' => $execA->id,
        'task_type' => TaskType::Manual->value,
        'status' => 'open',
        'due_date' => now()->addDays(2)->toDateString(),
    ]);

    Task::create([
        'title' => 'Alpha Task 2',
        'provider_id' => $provider->id,
        'credentialing_case_id' => null,
        'assigned_admin_id' => $execA->id,
        'task_type' => TaskType::Manual->value,
        'status' => 'open',
        'due_date' => now()->addDays(3)->toDateString(),
    ]);

    // Exec B: 1 active non-overdue case
    CredentialingCase::create([
        'provider_id' => $provider->id,
        'practice_id' => $practice->id,
        'payer_id' => $payer->id,
        'status_id' => $activeStatus->id,
        'assigned_admin_id' => $execB->id,
        'next_follow_up_date' => now()->addDays(5)->toDateString(),
        'state' => 'NY',
    ]);

    $service = app(ProductivityDashboardService::class);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $metrics = $service->executiveMetrics();

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    // Constant query count (6 bulk queries regardless of number of admins)
    expect(count($queries))->toBeLessThanOrEqual(8);

    $alphaMetrics = $metrics->firstWhere('admin_id', $execA->id);
    $betaMetrics = $metrics->firstWhere('admin_id', $execB->id);

    expect($alphaMetrics)->toMatchArray([
        'admin_id' => $execA->id,
        'name' => 'Executive Alpha',
        'active_cases' => 1,
        'approved_cases' => 1,
        'overdue_cases' => 1,
        'open_tasks' => 2,
        'avg_turnaround_days' => 20.0,
    ])->and($betaMetrics)->toMatchArray([
        'admin_id' => $execB->id,
        'name' => 'Executive Beta',
        'active_cases' => 1,
        'approved_cases' => 0,
        'overdue_cases' => 0,
        'open_tasks' => 0,
        'avg_turnaround_days' => null,
    ]);
});

test('DashboardService delayBreakdown avoids N+1 queries and practice-scoped admins include provider-linked overdue tasks', function () {
    $systemAdmin = Admin::where('username', 'superadmin')->firstOrFail();

    $scopedAdmin = Admin::create([
        'name' => 'Scoped Specialist',
        'username' => 'scoped_specialist',
        'email' => 'scoped-specialist@example.com',
        'password' => 'password123',
        'status' => 1,
    ]);
    $scopedAdmin->assignRole(AdminRole::CredentialingExecutive->value);

    [$practiceA, $providerA] = seedWave3PracticeAndProvider('SCOPEDA');
    [$practiceB, $providerB] = seedWave3PracticeAndProvider('SCOPEDB');

    $scopedAdmin->practices()->sync([$practiceA->id]);

    $payer = Payer::create([
        'name' => 'Humana',
        'slug' => 'humana',
        'payer_type' => 'Commercial',
    ]);

    $payerDelayOwner = DelayOwner::create([
        'name' => 'Payer',
        'is_active' => true,
    ]);

    $providerDelayOwner = DelayOwner::create([
        'name' => 'Provider/Practice',
        'is_active' => true,
    ]);

    $activeStatus = Status::create([
        'name' => 'Pending Payer',
        'slug' => 'pending-payer',
        'dashboard_category' => 'payer',
        'sort_order' => 1,
    ]);

    // Practice A: 2 Payer delay cases, 1 Provider delay case
    CredentialingCase::create([
        'provider_id' => $providerA->id,
        'practice_id' => $practiceA->id,
        'payer_id' => $payer->id,
        'status_id' => $activeStatus->id,
        'delay_owner_id' => $payerDelayOwner->id,
        'state' => 'TX',
    ]);

    CredentialingCase::create([
        'provider_id' => $providerA->id,
        'practice_id' => $practiceA->id,
        'payer_id' => $payer->id,
        'status_id' => $activeStatus->id,
        'delay_owner_id' => $payerDelayOwner->id,
        'state' => 'CA',
    ]);

    CredentialingCase::create([
        'provider_id' => $providerA->id,
        'practice_id' => $practiceA->id,
        'payer_id' => $payer->id,
        'status_id' => $activeStatus->id,
        'delay_owner_id' => $providerDelayOwner->id,
        'state' => 'FL',
    ]);

    // Practice B: 1 Provider delay case (outside scopedAdmin's practices)
    CredentialingCase::create([
        'provider_id' => $providerB->id,
        'practice_id' => $practiceB->id,
        'payer_id' => $payer->id,
        'status_id' => $activeStatus->id,
        'delay_owner_id' => $providerDelayOwner->id,
        'state' => 'NY',
    ]);

    // Overdue task linked ONLY to providerA (no credentialing_case_id)
    $providerOnlyOverdueTask = Task::create([
        'title' => 'Provider-Linked Overdue Task A',
        'provider_id' => $providerA->id,
        'credentialing_case_id' => null,
        'task_type' => TaskType::ProviderFollowUp->value,
        'status' => 'open',
        'due_date' => now()->subDays(3)->toDateString(),
    ]);

    // Overdue task linked ONLY to providerB (outside scopedAdmin's practices)
    Task::create([
        'title' => 'Provider-Linked Overdue Task B',
        'provider_id' => $providerB->id,
        'credentialing_case_id' => null,
        'task_type' => TaskType::ProviderFollowUp->value,
        'status' => 'open',
        'due_date' => now()->subDays(3)->toDateString(),
    ]);

    $dashboardService = app(DashboardService::class);

    // 1. System Admin sees all 4 cases and both overdue tasks in 1 query for delayBreakdown
    $systemAdmin->load('roles');
    $this->actingAs($systemAdmin, 'admin');

    DB::flushQueryLog();
    DB::enableQueryLog();
    $sysBreakdown = $dashboardService->delayBreakdown();
    $breakdownQueries = DB::getQueryLog();
    DB::disableQueryLog();

    expect(count($breakdownQueries))->toBe(1)
        ->and($sysBreakdown['labels'])->toBe(['Payer', 'Provider/Practice'])
        ->and($sysBreakdown['series'])->toBe([2, 2]);

    $sysStats = $dashboardService->stats();
    expect($sysStats['overdue_followups'])->toBe(2);

    // 2. Practice-scoped admin sees Practice A's 3 cases and providerA's provider-linked overdue task
    $this->actingAs($scopedAdmin, 'admin');

    $scopedBreakdown = $dashboardService->delayBreakdown();
    expect($scopedBreakdown['labels'])->toBe(['Payer', 'Provider/Practice'])
        ->and($scopedBreakdown['series'])->toBe([2, 1]);

    $scopedStats = $dashboardService->stats();
    $scopedNotifications = $dashboardService->notificationCounts();
    $scopedQueue = $dashboardService->workQueue();

    expect($scopedStats['overdue_followups'])->toBe(1)
        ->and($scopedNotifications['overdue_tasks'])->toBe(1)
        ->and($scopedQueue->pluck('id')->all())->toContain($providerOnlyOverdueTask->id);
});

test('PracticeDetailsPage and ProviderDetailsPage eager-load case and activity relationships', function () {
    $systemAdmin = Admin::where('username', 'superadmin')->firstOrFail();
    $this->actingAs($systemAdmin, 'admin');

    [$practice, $provider] = seedWave3PracticeAndProvider('DETAILS');

    $payer = Payer::create([
        'name' => 'Aetna',
        'slug' => 'aetna-details',
        'payer_type' => 'Commercial',
    ]);

    $status = Status::create([
        'name' => 'Submitted',
        'slug' => 'submitted-details',
        'dashboard_category' => 'payer',
        'sort_order' => 1,
    ]);

    $case = CredentialingCase::create([
        'provider_id' => $provider->id,
        'practice_id' => $practice->id,
        'payer_id' => $payer->id,
        'status_id' => $status->id,
        'assigned_admin_id' => $systemAdmin->id,
        'state' => 'TX',
    ]);

    CaseActivity::create([
        'credentialing_case_id' => $case->id,
        'activity_type' => 'note',
        'admin_id' => $systemAdmin->id,
        'summary' => 'Verified primary source credentials',
    ]);

    Livewire::test(PracticeDetailsPage::class, ['practice' => $practice->id])
        ->call('setTab', 'timeline')
        ->assertSee('Verified primary source credentials')
        ->assertSee($case->case_number);

    Livewire::test(ProviderDetailsPage::class, ['provider' => $provider])
        ->call('setTab', 'timeline')
        ->assertSee('Verified primary source credentials')
        ->assertSee($case->case_number);
});
