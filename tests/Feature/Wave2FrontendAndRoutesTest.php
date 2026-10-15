<?php

use App\Livewire\Admin\Email\EmailDashboardPage;
use App\Livewire\Admin\Email\EmailViewPage;
use App\Livewire\Admin\Master\CaseTypeManager;
use App\Livewire\Admin\Master\DelayOwnerManager;
use App\Livewire\Admin\Master\DocumentTypeManager;
use App\Livewire\Admin\Master\NotificationTemplateManager;
use App\Livewire\Admin\Master\PriorityManager;
use App\Livewire\Admin\Provider\ProviderEditPage;
use App\Models\Admin;
use App\Models\CredentialingCase;
use App\Models\NotificationTemplate;
use App\Models\Payer;
use App\Models\Practice;
use App\Models\ProviderDetails;
use App\Models\Status;
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
    $this->seed(RoleSeeder::class);
    $this->seed(PermissionSeeder::class);
    $this->seed(AdminPermissionSeeder::class);
    $this->seed(AdminSeeder::class);
    $this->seed(MasterDataSeeder::class);
});

function wave2Practice(string $code): Practice
{
    $user = User::create([
        'name' => $code.' Practice User',
        'email' => strtolower($code).'-practice@example.com',
        'password' => 'password',
    ]);
    $user->assignRole('practice');

    return Practice::create([
        'legal_name' => 'Wave2 Practice '.$code,
        'client_code' => $code,
        'email' => strtolower($code).'@example.com',
        'status' => 'active',
        'user_id' => $user->id,
    ]);
}

function wave2Provider(Practice $practice, string $suffix = 'A'): ProviderDetails
{
    $user = User::create([
        'name' => 'Dr. Wave2 '.$practice->client_code.' '.$suffix,
        'email' => 'dr-wave2-'.strtolower($practice->client_code).'-'.strtolower($suffix).'@example.com',
        'password' => 'password',
    ]);
    $user->assignRole('provider');

    $provider = ProviderDetails::create([
        'user_id' => $user->id,
        'npi' => (string) random_int(1000000000, 1999999999),
        'status' => 'active',
        'practice' => $practice->legal_name,
        'address' => '100 Main St',
        'city' => 'Austin',
        'state' => 'TX',
        'zip' => '78701',
    ]);

    $provider->practices()->syncWithoutDetaching([$practice->id => ['primary_flag' => true]]);

    return $provider;
}

test('master data managers ignore invalid sortField and sortDirection SQL injection attempts', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();

    foreach ([
        CaseTypeManager::class,
        DelayOwnerManager::class,
        DocumentTypeManager::class,
        PriorityManager::class,
    ] as $component) {
        Livewire::actingAs($admin, 'admin')
            ->test($component)
            ->call('sortBy', '1; DROP TABLE users --')
            ->assertSet('sortField', 'name')
            ->set('sortField', 'non_existent_column; --')
            ->set('sortDirection', 'invalid_dir')
            ->assertOk();
    }
});

test('notification template manager validates unique template key', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();

    NotificationTemplate::create([
        'name' => 'Existing Template',
        'template_key' => 'duplicate_key',
        'subject' => 'Subject 1',
        'body' => 'Body 1',
        'category' => 'general',
        'is_active' => true,
    ]);

    Livewire::actingAs($admin, 'admin')
        ->test(NotificationTemplateManager::class)
        ->call('openCreateModal')
        ->set('formData.name', 'Another Template')
        ->set('formData.template_key', 'duplicate_key')
        ->set('formData.subject', 'Subject 2')
        ->set('formData.body', 'Body 2')
        ->set('formData.category', 'general')
        ->call('save')
        ->assertHasErrors(['formData.template_key']);
});

test('provider edit page returns validation error on duplicate user email instead of 500 error', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = wave2Practice('PED');
    $providerA = wave2Provider($practice, 'A');
    $providerB = wave2Provider($practice, 'B');

    Livewire::actingAs($admin, 'admin')
        ->test(ProviderEditPage::class, ['provider' => $providerA->id])
        ->set('userData.email', $providerB->user->email)
        ->call('save')
        ->assertHasErrors(['userData.email' => 'unique']);
});

test('provider profile and practice providers and profile routes are reachable by authenticated portal users', function () {
    $practice = wave2Practice('PRT');
    $provider = wave2Provider($practice, 'P');

    $this->actingAs($provider->user, 'web')
        ->get(route('provider.profile'))
        ->assertOk()
        ->assertSee($provider->user->name);

    $this->actingAs($practice->user, 'web')
        ->get(route('practice.providers'))
        ->assertOk()
        ->assertSee($provider->user->name);

    $this->actingAs($practice->user, 'web')
        ->get(route('practice.profile'))
        ->assertOk()
        ->assertSee($practice->legal_name);
});

test('email view page and email dashboard page enforce send and link permissions and case scoping', function () {
    $billing = Admin::where('username', 'billing')->firstOrFail();
    $manager = Admin::where('username', 'manager')->firstOrFail();

    $assignedPractice = wave2Practice('EMA');
    $unassignedPractice = wave2Practice('EMU');
    $assignedProvider = wave2Provider($assignedPractice, 'A');
    $unassignedProvider = wave2Provider($unassignedPractice, 'U');
    $statusId = Status::where('name', 'Not Started')->firstOrFail()->id;
    $payerId = Payer::create(['name' => 'Wave2 Email Payer', 'is_active' => true])->id;

    $assignedCase = CredentialingCase::create([
        'practice_id' => $assignedPractice->id,
        'provider_id' => $assignedProvider->id,
        'payer_id' => $payerId,
        'status_id' => $statusId,
        'state' => 'TX',
    ]);
    $unassignedCase = CredentialingCase::create([
        'practice_id' => $unassignedPractice->id,
        'provider_id' => $unassignedProvider->id,
        'payer_id' => $payerId,
        'status_id' => $statusId,
        'state' => 'TX',
    ]);

    $billing->practices()->sync([$assignedPractice->id]);
    $manager->practices()->sync([$assignedPractice->id]);

    // Billing lacks admin.emails.send and admin.emails.link permissions
    Livewire::actingAs($billing, 'admin')
        ->test(EmailDashboardPage::class)
        ->call('sendCompose')
        ->assertForbidden();

    Livewire::actingAs($billing, 'admin')
        ->test(EmailDashboardPage::class)
        ->call('quickLinkCase', 'msg-123@example.com', $assignedCase->id)
        ->assertForbidden();

    Livewire::actingAs($billing, 'admin')
        ->test(EmailViewPage::class, ['folder' => 'inbox', 'messageId' => EmailViewPage::encodeId('msg-1')])
        ->call('sendReply')
        ->assertForbidden();

    Livewire::actingAs($billing, 'admin')
        ->test(EmailViewPage::class, ['folder' => 'inbox', 'messageId' => EmailViewPage::encodeId('msg-1')])
        ->call('linkToCase')
        ->assertForbidden();

    // Practice-scoped manager cannot link or send against unassigned practice's case
    Livewire::actingAs($manager, 'admin')
        ->test(EmailDashboardPage::class)
        ->call('quickLinkCase', 'msg-123@example.com', $unassignedCase->id)
        ->assertForbidden();

    Livewire::actingAs($manager, 'admin')
        ->test(EmailDashboardPage::class)
        ->set('composeTo', 'recipient@example.com')
        ->set('composeSubject', 'Test Subject')
        ->set('composeBody', 'Test Body')
        ->set('composeCaseId', (string) $unassignedCase->id)
        ->call('sendCompose')
        ->assertForbidden();

    Livewire::actingAs($manager, 'admin')
        ->test(EmailViewPage::class, ['folder' => 'inbox', 'messageId' => EmailViewPage::encodeId('msg-1')])
        ->set('linkMessageId', 'msg-123@example.com')
        ->set('linkCaseId', (string) $unassignedCase->id)
        ->call('linkToCase')
        ->assertForbidden();

    // Practice-scoped manager CAN hydrate from assigned case & template
    $template = NotificationTemplate::create([
        'name' => 'Follow Up Template',
        'template_key' => 'follow_up_wave2',
        'subject' => 'Case {{case_number}} for {{provider_name}}',
        'body' => 'Practice: {{practice_name}}',
        'category' => 'general',
        'is_active' => true,
    ]);

    Livewire::actingAs($manager, 'admin')
        ->test(EmailDashboardPage::class)
        ->set('composeCaseId', (string) $assignedCase->id)
        ->assertSet('composeTo', $assignedProvider->user->email)
        ->set('composeTemplateId', (string) $template->id)
        ->assertSet('composeSubject', 'Case '.$assignedCase->case_number.' for '.$assignedProvider->user->name)
        ->assertSet('composeBody', 'Practice: '.$assignedPractice->legal_name);
});
