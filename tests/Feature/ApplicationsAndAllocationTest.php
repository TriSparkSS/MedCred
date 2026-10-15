<?php

use App\Enums\EnrollmentLevel;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Livewire\Admin\Credential\CredentialCreatePage;
use App\Livewire\Admin\Credential\CredentialListPage;
use App\Livewire\Admin\Documents\DocumentListPage;
use App\Livewire\Admin\ProviderPractice\ProviderPracticeAssignmentPage;
use App\Livewire\Admin\Task\TaskBoardPage;
use App\Models\Admin;
use App\Models\CredentialingCase;
use App\Models\Document;
use App\Models\Payer;
use App\Models\Practice;
use App\Models\ProviderDetails;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminSeeder;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(AdminPermissionSeeder::class);
    $this->seed(AdminSeeder::class);
    $this->seed(MasterDataSeeder::class);
    $this->seed(RoleSeeder::class);
});

function allocPractice(string $code): Practice
{
    return Practice::create([
        'legal_name' => 'Alloc Practice '.$code,
        'client_code' => $code,
        'email' => strtolower($code).'@example.com',
        'status' => 'active',
        'user_id' => User::create([
            'name' => $code.' Practice User',
            'email' => strtolower($code).'-practice-'.uniqid().'@example.com',
            'password' => 'password',
        ])->id,
    ]);
}

function allocProvider(Practice $practice): ProviderDetails
{
    $provider = ProviderDetails::create([
        'user_id' => User::create([
            'name' => 'Dr. Alloc '.$practice->client_code,
            'email' => 'dr-alloc-'.uniqid().'@example.com',
            'password' => 'password',
        ])->id,
        'npi' => (string) random_int(1000000000, 1999999999),
        'status' => 'active',
        'practice' => $practice->legal_name,
    ]);

    $provider->practices()->syncWithoutDetaching([$practice->id => ['primary_flag' => true]]);

    return $provider;
}

test('creating group and individual applications stores enrollment_level on practice and provider', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = allocPractice('GRP');
    $provider = allocProvider($practice);
    $statusId = Status::where('name', 'Not Started')->firstOrFail()->id;

    Livewire::actingAs($admin, 'admin')
        ->test(CredentialCreatePage::class)
        ->set('formData.enrollment_level', EnrollmentLevel::Group->value)
        ->set('formData.provider_id', $provider->id)
        ->set('formData.practice_id', $practice->id)
        ->set('formData.status_id', $statusId)
        ->set('payerName', 'Group Enrollment Payer')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.applications'));

    $group = CredentialingCase::where('practice_id', $practice->id)->latest('id')->firstOrFail();
    expect($group->enrollment_level)->toBe(EnrollmentLevel::Group)
        ->and($group->provider_id)->toBe($provider->id)
        ->and($group->practice_id)->toBe($practice->id);

    $practiceB = allocPractice('IND');
    $providerB = allocProvider($practiceB);

    Livewire::actingAs($admin, 'admin')
        ->test(CredentialCreatePage::class)
        ->set('formData.enrollment_level', EnrollmentLevel::Individual->value)
        ->set('formData.provider_id', $providerB->id)
        ->set('formData.status_id', $statusId)
        ->set('payerName', 'Individual Enrollment Payer')
        ->call('save')
        ->assertHasNoErrors();

    $individual = CredentialingCase::where('provider_id', $providerB->id)->firstOrFail();
    expect($individual->enrollment_level)->toBe(EnrollmentLevel::Individual)
        ->and($individual->practice_id)->toBe($practiceB->id);
});

test('applications hub lists new application and filters by enrollment type', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = allocPractice('HUB');
    $provider = allocProvider($practice);
    $statusId = Status::where('name', 'Not Started')->firstOrFail()->id;

    $group = CredentialingCase::create([
        'practice_id' => $practice->id,
        'provider_id' => $provider->id,
        'payer_id' => Payer::create(['name' => 'Hub Group Payer', 'is_active' => true])->id,
        'status_id' => $statusId,
        'enrollment_level' => EnrollmentLevel::Group,
        'state' => 'TX',
    ]);
    $individual = CredentialingCase::create([
        'practice_id' => $practice->id,
        'provider_id' => $provider->id,
        'payer_id' => Payer::create(['name' => 'Hub Individual Payer', 'is_active' => true])->id,
        'status_id' => $statusId,
        'enrollment_level' => EnrollmentLevel::Individual,
        'state' => 'TX',
    ]);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.applications'))
        ->assertOk()
        ->assertSee('Applications')
        ->assertSee('New Application')
        ->assertSee('Enrollment');

    Livewire::actingAs($admin, 'admin')
        ->test(CredentialListPage::class)
        ->set('filterEnrollment', EnrollmentLevel::Group->value)
        ->assertSee($group->case_number)
        ->assertDontSee($individual->case_number);
});

test('task board select all matching overdue tasks for a practice can be bulk assigned', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $assignee = Admin::where('username', 'manager')->firstOrFail();
    $practiceA = allocPractice('TSA');
    $practiceB = allocPractice('TSB');
    $providerA = allocProvider($practiceA);
    $providerB = allocProvider($practiceB);
    $statusId = Status::where('name', 'At Payer')->firstOrFail()->id;

    $caseA = CredentialingCase::create([
        'practice_id' => $practiceA->id,
        'provider_id' => $providerA->id,
        'payer_id' => Payer::create(['name' => 'Alloc Payer A', 'is_active' => true])->id,
        'status_id' => $statusId,
        'state' => 'TX',
    ]);
    $caseB = CredentialingCase::create([
        'practice_id' => $practiceB->id,
        'provider_id' => $providerB->id,
        'payer_id' => Payer::create(['name' => 'Alloc Payer B', 'is_active' => true])->id,
        'status_id' => $statusId,
        'state' => 'TX',
    ]);

    $match = Task::create([
        'title' => 'Overdue at practice A',
        'credentialing_case_id' => $caseA->id,
        'provider_id' => $providerA->id,
        'payer_id' => $caseA->payer_id,
        'status' => TaskStatus::Open->value,
        'task_type' => TaskType::FollowUp->value,
        'due_date' => now()->subDays(2)->toDateString(),
    ]);
    $other = Task::create([
        'title' => 'Overdue at practice B',
        'credentialing_case_id' => $caseB->id,
        'provider_id' => $providerB->id,
        'payer_id' => $caseB->payer_id,
        'status' => TaskStatus::Open->value,
        'task_type' => TaskType::FollowUp->value,
        'due_date' => now()->subDays(2)->toDateString(),
    ]);

    Livewire::actingAs($admin, 'admin')
        ->test(TaskBoardPage::class)
        ->set('viewMode', 'list')
        ->set('filterPracticeId', (string) $practiceA->id)
        ->set('filterChip', 'overdue')
        ->assertSee($caseA->case_number)
        ->call('selectAllMatching')
        ->set('bulkAssigneeId', (string) $assignee->id)
        ->call('bulkAssignSelected')
        ->assertHasNoErrors();

    expect($match->fresh()->assigned_admin_id)->toBe($assignee->id)
        ->and($other->fresh()->assigned_admin_id)->toBeNull();
});

test('practice-scoped credentialing manager can load provider practice assignment page and only sees assigned practices', function () {
    $manager = Admin::where('username', 'manager')->firstOrFail();
    $assignedPractice = allocPractice('ASN');
    $unassignedPractice = allocPractice('UNA');
    $assignedProvider = allocProvider($assignedPractice);
    $unassignedProvider = allocProvider($unassignedPractice);

    $manager->practices()->sync([$assignedPractice->id]);

    Livewire::actingAs($manager, 'admin')
        ->test(ProviderPracticeAssignmentPage::class)
        ->assertOk()
        ->assertSee($assignedPractice->legal_name)
        ->assertSee($assignedProvider->user->name)
        ->assertDontSee($unassignedPractice->legal_name)
        ->assertDontSee($unassignedProvider->user->name);
});

test('document list page enforces verify and delete permissions and practice scope', function () {
    $billing = Admin::where('username', 'billing')->firstOrFail();
    $manager = Admin::where('username', 'manager')->firstOrFail();
    $assignedPractice = allocPractice('DCA');
    $unassignedPractice = allocPractice('DCU');

    $billing->practices()->sync([$assignedPractice->id]);
    $manager->practices()->sync([$assignedPractice->id]);

    $assignedDoc = Document::create([
        'title' => 'Assigned Practice Document',
        'practice_id' => $assignedPractice->id,
        'status' => 'active',
        'verification_status' => 'uploaded',
    ]);
    $unassignedDoc = Document::create([
        'title' => 'Unassigned Practice Document',
        'practice_id' => $unassignedPractice->id,
        'status' => 'active',
        'verification_status' => 'uploaded',
    ]);

    $manager->givePermissionTo(Permission::findOrCreate('admin.documents.delete', 'admin'));

    Livewire::actingAs($billing, 'admin')
        ->test(DocumentListPage::class)
        ->call('verifyDocument', $assignedDoc->id)
        ->assertForbidden();

    Livewire::actingAs($billing, 'admin')
        ->test(DocumentListPage::class)
        ->call('deleteDocument', $assignedDoc->id)
        ->assertForbidden();

    expect(fn () => Livewire::actingAs($manager, 'admin')
        ->test(DocumentListPage::class)
        ->call('verifyDocument', $unassignedDoc->id)
    )->toThrow(ModelNotFoundException::class);

    expect(fn () => Livewire::actingAs($manager, 'admin')
        ->test(DocumentListPage::class)
        ->call('deleteDocument', $unassignedDoc->id)
    )->toThrow(ModelNotFoundException::class);

    expect($unassignedDoc->fresh())->not->toBeNull()
        ->and($unassignedDoc->fresh()->verification_status)->toBe('uploaded');
});
