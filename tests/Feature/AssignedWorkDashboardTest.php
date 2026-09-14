<?php

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Livewire\Admin\DashboardPage;
use App\Livewire\Provider\ProviderDashboardPage;
use App\Models\Admin;
use App\Models\CaseDocumentItem;
use App\Models\CredentialingCase;
use App\Models\DocumentType;
use App\Models\Payer;
use App\Models\Practice;
use App\Models\ProviderDetails;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(AdminPermissionSeeder::class);
    $this->seed(AdminSeeder::class);
    $this->seed(MasterDataSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(PermissionSeeder::class);
});

function dashboardTestPractice(): Practice
{
    return Practice::create([
        'legal_name' => 'Dashboard Practice',
        'client_code' => 'DBP',
        'email' => 'dashboard-practice@example.com',
        'status' => 'active',
        'user_id' => User::create([
            'name' => 'Dashboard Practice User',
            'email' => 'dashboard-practice-user@example.com',
            'password' => 'password',
        ])->id,
    ]);
}

function dashboardTestProvider(?User $user = null): ProviderDetails
{
    $user ??= User::create([
        'name' => 'Dr. Dashboard',
        'email' => 'dr-dashboard@example.com',
        'password' => 'password',
    ]);

    return ProviderDetails::create([
        'user_id' => $user->id,
        'npi' => '1666666666',
        'status' => 'approved',
        'practice' => 'Dashboard Practice',
        'address' => '1 Dash St',
        'city' => 'Austin',
        'state' => 'TX',
        'zip' => '78701',
    ]);
}

test('admin dashboard assigned list shows own open work and hides other admins', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $other = Admin::where('username', 'manager')->firstOrFail();
    $practice = dashboardTestPractice();
    $provider = dashboardTestProvider();
    $provider->practices()->attach($practice->id, ['primary_flag' => true]);
    $payer = Payer::create(['name' => 'Dashboard Payer', 'is_active' => true]);
    $openStatus = Status::where('name', 'At Payer')->firstOrFail();
    $closedStatus = Status::where('name', 'Approved')->firstOrFail();

    $mineOpen = CredentialingCase::create([
        'practice_id' => $practice->id,
        'provider_id' => $provider->id,
        'payer_id' => $payer->id,
        'status_id' => $openStatus->id,
        'assigned_admin_id' => $admin->id,
        'state' => 'TX',
    ]);

    $mineClosed = CredentialingCase::create([
        'practice_id' => $practice->id,
        'provider_id' => $provider->id,
        'payer_id' => $payer->id,
        'status_id' => $closedStatus->id,
        'assigned_admin_id' => $admin->id,
        'state' => 'TX',
    ]);

    $theirs = CredentialingCase::create([
        'practice_id' => $practice->id,
        'provider_id' => $provider->id,
        'payer_id' => $payer->id,
        'status_id' => $openStatus->id,
        'assigned_admin_id' => $other->id,
        'state' => 'TX',
    ]);

    Task::create([
        'title' => 'My open follow-up',
        'assigned_admin_id' => $admin->id,
        'provider_id' => $provider->id,
        'status' => TaskStatus::Open->value,
        'task_type' => TaskType::FollowUp->value,
    ]);

    Task::create([
        'title' => 'My completed review',
        'assigned_admin_id' => $admin->id,
        'provider_id' => $provider->id,
        'status' => TaskStatus::Completed->value,
        'completed_at' => now(),
        'task_type' => TaskType::Review->value,
    ]);

    Task::create([
        'title' => 'Someone else task',
        'assigned_admin_id' => $other->id,
        'provider_id' => $provider->id,
        'status' => TaskStatus::Open->value,
        'task_type' => TaskType::FollowUp->value,
    ]);

    Livewire::actingAs($admin, 'admin')
        ->test(DashboardPage::class)
        ->assertSee($mineOpen->case_number)
        ->assertSee('My open follow-up')
        ->assertDontSee($theirs->case_number)
        ->assertDontSee('Someone else task')
        ->assertDontSee('My completed review')
        ->call('setAssignmentFilter', 'closed')
        ->assertSee($mineClosed->case_number)
        ->assertSee('My completed review')
        ->assertDontSee($mineOpen->case_number)
        ->assertDontSee('My open follow-up');
});

test('provider dashboard lists open cases and documents then closed approved cases', function () {
    $user = User::create([
        'name' => 'Dr. Portal Dash',
        'email' => 'portal-dash@example.com',
        'password' => 'password',
    ]);
    $user->assignRole('provider');

    $provider = dashboardTestProvider($user);
    $practice = dashboardTestPractice();
    $provider->practices()->attach($practice->id, ['primary_flag' => true]);
    $payer = Payer::create(['name' => 'Portal Payer', 'is_active' => true]);
    $openStatus = Status::where('name', 'Documents Requested from Provider')->firstOrFail();
    $closedStatus = Status::where('name', 'Approved')->firstOrFail();
    $docType = DocumentType::where('name', 'W-9')->firstOrFail();

    $openCase = CredentialingCase::create([
        'practice_id' => $practice->id,
        'provider_id' => $provider->id,
        'payer_id' => $payer->id,
        'status_id' => $openStatus->id,
        'state' => 'TX',
    ]);

    $closedCase = CredentialingCase::create([
        'practice_id' => $practice->id,
        'provider_id' => $provider->id,
        'payer_id' => $payer->id,
        'status_id' => $closedStatus->id,
        'state' => 'TX',
    ]);

    CaseDocumentItem::create([
        'credentialing_case_id' => $openCase->id,
        'document_type_id' => $docType->id,
        'is_required' => true,
        'is_received' => false,
    ]);

    Livewire::actingAs($user, 'web')
        ->test(ProviderDashboardPage::class)
        ->assertSee($openCase->case_number)
        ->assertSee('W-9')
        ->assertDontSee($closedCase->case_number)
        ->call('setAssignmentFilter', 'closed')
        ->assertSee($closedCase->case_number)
        ->assertDontSee('W-9');
});
