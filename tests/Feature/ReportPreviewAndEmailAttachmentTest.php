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
use App\Services\ReportExportService;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminSeeder;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

if (! function_exists('configureGraphForTests')) {
    function configureGraphForTests(): void
    {
        config([
            'services.microsoft_graph.tenant_id' => '014947f0-8696-4ff4-b4b0-873d91eb1665',
            'services.microsoft_graph.client_id' => 'f48ae449-09c4-46c6-b187-d1238ee09190',
            'services.microsoft_graph.client_secret' => 'test-secret-value',
            'services.microsoft_graph.mailbox' => 'credentialing@revantagehbs.com',
        ]);

        Cache::flush();
    }
}

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

test('styled excel download matches sample header group and approved colors', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $practice = makePractice('Excel Style Practice', 'ESP');
    $provider = makeProvider('Excel Style Provider', '5555555555', $practice);
    $payer = Payer::create(['name' => 'Humana', 'is_active' => true]);
    $status = Status::where('name', 'Approved')->firstOrFail();
    makeCase($practice, $provider, $payer, $status);

    $this->actingAs($admin, 'admin');

    Livewire::actingAs($admin, 'admin')
        ->test(PracticeCredentialingReportPage::class)
        ->call('downloadExcel')
        ->assertFileDownloaded('total_credentialing_report_by_practice_'.now()->format('Ymd').'.xlsx');

    $export = app(ReportExportService::class);
    $contents = $export->xlsxContents('practice_credentialing_status');
    expect($contents)->toStartWith('PK');

    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pcr-style-'.uniqid().'.xlsx';
    file_put_contents($path, $contents);
    $sheet = IOFactory::load($path)->getActiveSheet();

    expect($sheet->getTitle())->toBe('Credentialing Report')
        ->and($sheet->getCell('A1')->getValue())->toBe('Total Credentialing Report by Practice')
        ->and($sheet->getCell('A1')->getStyle()->getFill()->getStartColor()->getRGB())->toBe($export->excelTitleFillColor())
        ->and($sheet->getCell('A8')->getValue())->toBe('Practice')
        ->and($sheet->getCell('A8')->getStyle()->getFill()->getStartColor()->getRGB())->toBe($export->excelTitleFillColor())
        ->and($sheet->getCell('A5')->getValue())->toBe('Total Cases 1')
        ->and($sheet->getCell('D5')->getValue())->toBe('Approved 1');

    $foundPractice = false;
    $approvedFill = null;
    $zebraFill = null;
    foreach ($sheet->getRowIterator() as $row) {
        $index = $row->getRowIndex();
        $label = (string) $sheet->getCell('A'.$index)->getValue();
        if (str_contains($label, 'Excel Style Practice')) {
            $foundPractice = true;
            $zebraFill = $sheet->getCell('A'.$index)->getStyle()->getFill()->getStartColor()->getRGB();
        }
        if ((string) $sheet->getCell('H'.$index)->getValue() === 'Approved') {
            $approvedFill = $sheet->getCell('H'.$index)->getStyle()->getFill()->getStartColor()->getRGB();
        }
    }

    expect($foundPractice)->toBeTrue()
        ->and($approvedFill)->toBe('DCFCE7')
        ->and($approvedFill)->toBe($export->statusBucketFillColor('approved'))
        ->and(in_array($zebraFill, ['FFFFFF', $export->excelZebraFillColor()], true))->toBeTrue();

    @unlink($path);
});

test('excel export respects practice filters', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $visible = makePractice('Excel Visible Practice', 'EVP');
    $hidden = makePractice('Excel Hidden Practice', 'EHP');
    $payer = Payer::create(['name' => 'UHC', 'is_active' => true]);
    $status = Status::where('name', 'At Payer')->firstOrFail();

    makeCase($visible, makeProvider('Excel Visible Doc', '6666666666', $visible), $payer, $status);
    makeCase($hidden, makeProvider('Excel Hidden Doc', '7777777777', $hidden), $payer, $status);

    $this->actingAs($admin, 'admin');

    $contents = app(ReportExportService::class)->xlsxContents('practice_credentialing_status', [
        'practice_id' => $visible->id,
    ]);

    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pcr-filter-'.uniqid().'.xlsx';
    file_put_contents($path, $contents);
    $sheet = IOFactory::load($path)->getActiveSheet();
    $values = [];
    foreach ($sheet->getRowIterator() as $row) {
        $index = $row->getRowIndex();
        $values[] = (string) $sheet->getCell('A'.$index)->getValue();
        $values[] = (string) $sheet->getCell('C'.$index)->getValue();
    }
    $blob = implode("\n", $values);

    expect($blob)->toContain('Excel Visible Practice')
        ->and($blob)->toContain('Excel Visible Doc')
        ->and($blob)->not->toContain('Excel Hidden Practice')
        ->and($blob)->not->toContain('Excel Hidden Doc');

    @unlink($path);
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

test('email view keeps encoded graph message id in livewire state for json safety', function () {
    // Graph ids can contain non-UTF-8 bytes when decoded; Livewire must store the URL-safe form.
    $binaryId = "AAMk\x00\xFF\x80binary-id";
    $encoded = EmailViewPage::encodeId($binaryId);

    expect(EmailViewPage::isValidEncodedId($encoded))->toBeTrue()
        ->and(EmailViewPage::decodeId($encoded))->toBe($binaryId);

    $json = json_encode(['messageId' => $encoded], JSON_THROW_ON_ERROR);

    expect($json)->toContain($encoded);
});

test('email attachment preserves binary integrity for png and xlsx downloads', function () {
    configureGraphForTests();

    $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    $xlsxBytes = "PK\x03\x04".str_repeat('y', 80);

    Http::fake(function (Request $request) use ($pngBytes) {
        $url = $request->url();

        if (str_contains($url, 'login.microsoftonline.com')) {
            return Http::response(['access_token' => 'fake-graph-token', 'expires_in' => 3600], 200);
        }

        if (str_contains($url, 'att-bin') && (str_contains($url, '$value') || str_contains($url, '%24value'))) {
            return Http::response($pngBytes, 200, ['Content-Type' => 'image/png']);
        }

        if (str_contains($url, 'att-bin')) {
            return Http::response([
                '@odata.type' => '#microsoft.graph.fileAttachment',
                'id' => 'att-bin',
                'name' => 'photo.png',
                'contentType' => 'image/png',
                'size' => strlen($pngBytes),
            ], 200);
        }

        return Http::response(['value' => []], 200);
    });

    $admin = Admin::where('username', 'superadmin')->first();
    $response = $this->actingAs($admin, 'admin')
        ->get(route('admin.email.attachment', [
            'folder' => 'inbox',
            'messageId' => EmailViewPage::encodeId('msg-bin'),
            'attachmentId' => EmailViewPage::encodeId('att-bin'),
        ]));

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/png');

    expect($response->getContent())->toBe($pngBytes);
});
