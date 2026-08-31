<?php

use App\Livewire\Admin\Email\EmailViewPage;
use App\Livewire\Admin\Reports\PracticeCredentialingReportPage;
use App\Models\Admin;
use App\Models\CredentialingCase;
use App\Models\Payer;
use App\Models\Practice;
use App\Models\ProviderDetails;
use App\Models\Status;
use App\Models\User;
use App\Services\GraphMailboxService;
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

function makePractice(string $name, string $code): Practice
{
    return Practice::create([
        'legal_name' => $name,
        'client_code' => $code,
        'email' => strtolower($code).'@example.com',
        'status' => 'active',
        'user_id' => User::create([
            'name' => $name.' User',
            'email' => strtolower($code).'-user@example.com',
            'password' => 'password',
        ])->id,
    ]);
}

function makeProvider(string $name, string $npi, Practice $practice): ProviderDetails
{
    $user = User::create([
        'name' => $name,
        'email' => strtolower(str_replace(' ', '.', $name)).'-'.uniqid().'@example.com',
        'password' => 'password',
    ]);

    $provider = ProviderDetails::create([
        'user_id' => $user->id,
        'npi' => $npi,
        'status' => 'approved',
    ]);

    $provider->practices()->attach($practice->id, ['primary_flag' => true]);

    return $provider;
}

function makeCase(Practice $practice, ProviderDetails $provider, Payer $payer, Status $status, string $state = 'MD'): CredentialingCase
{
    return CredentialingCase::create([
        'practice_id' => $practice->id,
        'provider_id' => $provider->id,
        'payer_id' => $payer->id,
        'status_id' => $status->id,
        'state' => $state,
        'effective_date' => now()->subMonth()->toDateString(),
        'revalidation_due_date' => now()->addYear()->toDateString(),
        'last_action_at' => now(),
    ]);
}

test('practice credentialing report page loads with title and kpi region', function () {
    $admin = Admin::where('username', 'superadmin')->first();
    $practice = makePractice('John Hopkins Imaging', 'JHI');
    $provider = makeProvider('Jayne Loom, MD', '1234567890', $practice);
    $payer = Payer::create(['name' => 'Aetna', 'is_active' => true]);
    $status = Status::where('name', 'Approved')->firstOrFail();
    makeCase($practice, $provider, $payer, $status);

    Livewire::actingAs($admin, 'admin')
        ->test(PracticeCredentialingReportPage::class)
        ->assertSee('Total Credentialing Report by Practice')
        ->assertSee('Total Practices')
        ->assertSee('Total Providers')
        ->assertSee('Total Payer Enrollments')
        ->assertSee('John Hopkins Imaging')
        ->assertSee('Jayne Loom, MD')
        ->assertSee('Aetna');
});

test('run report filters enrollments by practice', function () {
    $admin = Admin::where('username', 'superadmin')->first();
    $visible = makePractice('Visible Practice', 'VIS');
    $hidden = makePractice('Hidden Practice', 'HID');
    $payer = Payer::create(['name' => 'BCBS', 'is_active' => true]);
    $status = Status::where('name', 'At Payer')->firstOrFail();

    makeCase($visible, makeProvider('Visible Provider', '1111111111', $visible), $payer, $status);
    makeCase($hidden, makeProvider('Hidden Provider', '2222222222', $hidden), $payer, $status);

    Livewire::actingAs($admin, 'admin')
        ->test(PracticeCredentialingReportPage::class)
        ->set('draftPracticeId', (string) $visible->id)
        ->call('runReport')
        ->assertSet('appliedPracticeId', (string) $visible->id)
        ->assertSee('Visible Practice')
        ->assertSee('Visible Provider')
        ->assertDontSee('Hidden Provider')
        ->assertSee('Showing')
        ->assertSee('of')
        ->assertSee('practices');
});

test('scoped admin only sees assigned practices on report', function () {
    $visible = makePractice('Assigned Report Practice', 'ARP');
    $hidden = makePractice('Unassigned Report Practice', 'URP');
    $payer = Payer::create(['name' => 'Cigna', 'is_active' => true]);
    $status = Status::where('name', 'Approved')->firstOrFail();

    makeCase($visible, makeProvider('Assigned Doc', '3333333333', $visible), $payer, $status);
    makeCase($hidden, makeProvider('Hidden Doc', '4444444444', $hidden), $payer, $status);

    $billing = Admin::where('username', 'billing')->firstOrFail();
    $billing->practices()->sync([$visible->id]);

    Livewire::actingAs($billing, 'admin')
        ->test(PracticeCredentialingReportPage::class)
        ->assertSee('Assigned Report Practice')
        ->assertDontSee('Unassigned Report Practice')
        ->assertDontSee('Hidden Doc');
});

test('email attachment route encodes and decodes graph ids', function () {
    $messageId = 'AAMkAGI2TH+/message==';
    $attachmentId = 'AAMkAGI2TH+/attach==';
    $encodedMessage = EmailViewPage::encodeId($messageId);
    $encodedAttachment = EmailViewPage::encodeId($attachmentId);

    $admin = Admin::where('username', 'superadmin')->first();

    $this->mock(GraphMailboxService::class, function ($mock) use ($messageId, $attachmentId) {
        $mock->shouldReceive('downloadAttachment')
            ->once()
            ->with($messageId, $attachmentId)
            ->andReturn([
                'name' => 'letter.pdf',
                'contentType' => 'application/pdf',
                'content' => '%PDF-1.4 fake',
            ]);
    });

    $this->actingAs($admin, 'admin')
        ->get(route('admin.email.attachment', [
            'folder' => 'inbox',
            'messageId' => $encodedMessage,
            'attachmentId' => $encodedAttachment,
        ]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename="letter.pdf"; filename*=UTF-8\'\'letter.pdf');
});

test('email attachment returns inline disposition for pdf and images', function () {
    $admin = Admin::where('username', 'superadmin')->first();
    $messageId = EmailViewPage::encodeId('msg-1');
    $attachmentId = EmailViewPage::encodeId('att-1');

    $this->mock(GraphMailboxService::class, function ($mock) {
        $mock->shouldReceive('downloadAttachment')
            ->once()
            ->andReturn([
                'name' => 'scan.png',
                'contentType' => 'image/png',
                'content' => 'png-bytes',
            ]);
    });

    $this->actingAs($admin, 'admin')
        ->get(route('admin.email.attachment', [
            'folder' => 'inbox',
            'messageId' => $messageId,
            'attachmentId' => $attachmentId,
        ]))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'inline; filename="scan.png"; filename*=UTF-8\'\'scan.png');
});

test('email attachment forces download for non-viewable types and download query', function () {
    $admin = Admin::where('username', 'superadmin')->first();
    $messageId = EmailViewPage::encodeId('msg-2');
    $attachmentId = EmailViewPage::encodeId('att-2');

    $this->mock(GraphMailboxService::class, function ($mock) {
        $mock->shouldReceive('downloadAttachment')
            ->twice()
            ->andReturn(
                [
                    'name' => 'notes.docx',
                    'contentType' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'content' => 'docx-bytes',
                ],
                [
                    'name' => 'letter.pdf',
                    'contentType' => 'application/pdf',
                    'content' => '%PDF',
                ],
            );
    });

    $this->actingAs($admin, 'admin')
        ->get(route('admin.email.attachment', [
            'folder' => 'inbox',
            'messageId' => $messageId,
            'attachmentId' => $attachmentId,
        ]))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="notes.docx"; filename*=UTF-8\'\'notes.docx');

    $this->actingAs($admin, 'admin')
        ->get(route('admin.email.attachment', [
            'folder' => 'inbox',
            'messageId' => $messageId,
            'attachmentId' => $attachmentId,
        ]).'?download=1')
        ->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="letter.pdf"; filename*=UTF-8\'\'letter.pdf');
});

test('email attachment returns 502 when graph download fails', function () {
    $admin = Admin::where('username', 'superadmin')->first();

    $this->mock(GraphMailboxService::class, function ($mock) {
        $mock->shouldReceive('downloadAttachment')
            ->once()
            ->andThrow(new RuntimeException('Attachment is not downloadable.'));
    });

    $this->actingAs($admin, 'admin')
        ->get(route('admin.email.attachment', [
            'folder' => 'inbox',
            'messageId' => EmailViewPage::encodeId('msg-missing'),
            'attachmentId' => EmailViewPage::encodeId('att-missing'),
        ]))
        ->assertStatus(502);
});
