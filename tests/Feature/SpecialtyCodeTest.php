<?php

use App\Livewire\Admin\Setting\Specialty\SpecialtyListPage;
use App\Models\Admin;
use App\Models\Specialty;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(AdminPermissionSeeder::class);
    $this->seed(AdminSeeder::class);
    $this->seed(RoleSeeder::class);
});

function specialtyTaxonomyFixture(array $rows, string $filename = 'taxonomy-fixture.xlsx'): string
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray(array_merge([
        ['Code', 'Display Name'],
    ], $rows));
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.$filename;
    (new Xlsx($spreadsheet))->save($path);

    return $path;
}

test('specialties table and model expose a unique code column', function () {
    expect(Schema::hasColumn('specialties', 'code'))->toBeTrue();

    $specialty = Specialty::factory()->create([
        'code' => '207Q00000X',
        'name' => 'Family Medicine Physician',
    ]);

    expect($specialty->getFillable())->toContain('code')
        ->and($specialty->code)->toBe('207Q00000X')
        ->and($specialty->name)->toBe('Family Medicine Physician');
});

test('specialty codes must be unique', function () {
    Specialty::factory()->create([
        'code' => '207Q00000X',
        'name' => 'Family Medicine Physician',
    ]);

    expect(fn () => Specialty::factory()->create([
        'code' => '207Q00000X',
        'name' => 'Duplicate Family Medicine',
    ]))->toThrow(UniqueConstraintViolationException::class);
});

test('specialty seeder imports rows from a real-shaped taxonomy fixture without duplicating on re-seed', function () {
    $path = base_path('tests/Fixtures/taxonomy-list-sample.xlsx');
    expect(is_readable($path))->toBeTrue();

    $seeder = new SpecialtySeeder;
    $seeder->run($path);
    $seeder->run($path);

    expect(Specialty::count())->toBe(3)
        ->and(Specialty::where('code', '193200000X')->value('name'))->toBe('Multi-Specialty Group')
        ->and(Specialty::where('code', '207KA0200X')->value('name'))->toBe('Allergy Physician')
        ->and(Specialty::where('code', '342000000X')->value('name'))->toBe('Transportation Network Company');
});

test('specialty seeder ignores duplicate taxonomy codes in an excel fixture', function () {
    $path = specialtyTaxonomyFixture([
        ['207Q00000X', 'Family Medicine Physician'],
        ['207R00000X', 'Internal Medicine Physician'],
        ['207Q00000X', 'Should be ignored duplicate code'],
    ]);

    (new SpecialtySeeder)->run($path);

    expect(Specialty::count())->toBe(2)
        ->and(Specialty::where('code', '207Q00000X')->value('name'))->toBe('Family Medicine Physician')
        ->and(Specialty::where('code', '207R00000X')->value('name'))->toBe('Internal Medicine Physician');
});

test('specialty seeder updates the display name for an existing taxonomy code', function () {
    Specialty::factory()->create([
        'code' => '207Q00000X',
        'name' => 'Old Family Medicine Name',
    ]);

    $path = specialtyTaxonomyFixture([
        ['207Q00000X', 'Family Medicine Physician'],
    ], 'taxonomy-update.xlsx');

    (new SpecialtySeeder)->run($path);

    expect(Specialty::where('code', '207Q00000X')->count())->toBe(1)
        ->and(Specialty::where('code', '207Q00000X')->value('name'))->toBe('Family Medicine Physician');
});

test('specialty seeder imports the real credentialing taxonomy workbook', function () {
    $seeder = new SpecialtySeeder;
    $path = $seeder->defaultSourcePath();

    if (! is_readable($path)) {
        $this->markTestSkipped('Credentialing taxonomy workbook is not available.');
    }

    $spreadsheet = IOFactory::load($path);
    $excelRows = $seeder->rowsFromSheet($spreadsheet->getActiveSheet());

    $seeder->run($path);

    expect($excelRows->count())->toBe(883)
        ->and(Specialty::count())->toBe(883)
        ->and(Specialty::where('code', '193200000X')->value('name'))->toBe($excelRows->first()['name'])
        ->and(Specialty::where('code', '193200000X')->value('name'))->toBe('Multi-Specialty Group')
        ->and(Specialty::where('code', '207Q00000X')->value('name'))->toBe('Family Medicine Physician')
        ->and(Specialty::where('code', '342000000X')->value('name'))->toBe($excelRows->last()['name'])
        ->and(Specialty::where('code', '342000000X')->value('name'))->toBe('Transportation Network Company')
        ->and(Specialty::whereNotNull('code')->count())->toBe(883);
});

test('specialty admin list create edit and search include code', function () {
    $admin = Admin::where('username', 'superadmin')->firstOrFail();
    $specialty = Specialty::factory()->create([
        'code' => '207Q00000X',
        'name' => 'Family Medicine Physician',
        'description' => 'Primary care',
    ]);

    Livewire::actingAs($admin, 'admin')
        ->test(SpecialtyListPage::class)
        ->assertSee('Code')
        ->assertSee('207Q00000X')
        ->assertSee('Family Medicine Physician')
        ->set('search', '207Q00000X')
        ->assertSee('Family Medicine Physician')
        ->set('search', 'missing-code-xyz')
        ->assertSee('No specialties found');

    Livewire::actingAs($admin, 'admin')
        ->test(SpecialtyListPage::class)
        ->call('openCreateModal')
        ->set('formData.code', '208D00000X')
        ->set('formData.name', 'General Practice Physician')
        ->set('formData.description', 'General practice')
        ->call('save')
        ->assertHasNoErrors();

    expect(Specialty::where('code', '208D00000X')->value('name'))->toBe('General Practice Physician');

    Livewire::actingAs($admin, 'admin')
        ->test(SpecialtyListPage::class)
        ->call('openEditModal', $specialty->id)
        ->assertSet('formData.code', '207Q00000X')
        ->set('formData.code', '207QA0401X')
        ->set('formData.name', 'Addiction Medicine (Family Medicine) Physician')
        ->call('save')
        ->assertHasNoErrors();

    expect($specialty->fresh()->code)->toBe('207QA0401X')
        ->and($specialty->fresh()->name)->toBe('Addiction Medicine (Family Medicine) Physician');

    Livewire::actingAs($admin, 'admin')
        ->test(SpecialtyListPage::class)
        ->call('openCreateModal')
        ->set('formData.code', '207QA0401X')
        ->set('formData.name', 'Duplicate Code Specialty')
        ->call('save')
        ->assertHasErrors(['formData.code']);
});
