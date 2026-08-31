<?php

use App\Livewire\Admin\Credential\CredentialListPage;
use App\Models\Admin;
use App\Models\CredentialingCase;
use App\Models\Payer;
use App\Models\Practice;
use App\Models\ProviderDetails;
use App\Models\Status;
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

function escalationPractice(): Practice
{
    return Practice::create([
        'legal_name' => 'Escalation Test Practice',
        'client_code' => 'ETP',
        'email' => 'etp@example.com',
        'status' => 'active',
        'user_id' => User::create([
            'name' => 'Escalation Practice User',
            'email' => 'etp-user@example.com',
            'password' => 'password',
        ])->id,
    ]);
}

function escalationCase(): CredentialingCase
{
    $practice = escalationPractice();
    $provider = ProviderDetails::create([
        'user_id' => User::create([
            'name' => 'Dr. Escalation',
            'email' => 'dr-escalation@example.com',
            'password' => 'password',
        ])->id,
        'npi' => '9999999999',
        'status' => 'approved',
    ]);
    $provider->practices()->attach($practice->id, ['primary_flag' => true]);

    return CredentialingCase::create([
        'practice_id' => $practice->id,
        'provider_id' => $provider->id,
        'payer_id' => Payer::create(['name' => 'Test Payer', 'is_active' => true])->id,
        'status_id' => Status::where('name', 'At Payer')->firstOrFail()->id,
        'state' => 'MD',
        'is_escalated' => false,
    ]);
}

test('superadmin can escalate and de-escalate a case from tracker', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $case = escalationCase();

    Livewire::actingAs($admin, 'admin')
        ->test(CredentialListPage::class)
        ->call('toggleEscalation', $case->id)
        ->assertHasNoErrors();

    expect($case->fresh()->is_escalated)->toBeTrue();

    Livewire::actingAs($admin, 'admin')
        ->test(CredentialListPage::class)
        ->call('toggleEscalation', $case->id)
        ->assertHasNoErrors();

    expect($case->fresh()->is_escalated)->toBeFalse();
});

test('executive cannot escalate a case from tracker', function () {
    $admin = Admin::where('username', 'executive')->firstOrFail();
    $case = escalationCase();
    $admin->practices()->sync([$case->practice_id]);

    expect($admin->can('admin.tasks.escalate'))->toBeTrue();

    Livewire::actingAs($admin, 'admin')
        ->test(CredentialListPage::class)
        ->call('toggleEscalation', $case->id)
        ->assertHasNoErrors();

    expect($case->fresh()->is_escalated)->toBeTrue();
});

test('billing user cannot escalate a case from tracker', function () {
    $admin = Admin::where('username', 'billing')->firstOrFail();
    $case = escalationCase();
    $admin->practices()->sync([$case->practice_id]);

    Livewire::actingAs($admin, 'admin')
        ->test(CredentialListPage::class)
        ->call('toggleEscalation', $case->id)
        ->assertForbidden();

    expect($case->fresh()->is_escalated)->toBeFalse();
});
