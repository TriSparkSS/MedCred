<?php

namespace Database\Seeders;

use App\Models\Specialty;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class SpecialtySeeder extends Seeder
{
    public const SOURCE_FILENAME = 'taxonomy list for Cred.xlsx';

    public const CODE_HEADER = 'code';

    public const NAME_HEADER = 'display name';

    public function run(?string $path = null): void
    {
        $path ??= $this->defaultSourcePath();

        if (! is_readable($path)) {
            throw new RuntimeException("Specialty taxonomy list not found at {$path}");
        }

        $spreadsheet = IOFactory::load($path);
        $sheet = $this->resolveTaxonomySheet($spreadsheet);
        $rows = $this->rowsFromSheet($sheet);

        if ($rows->isEmpty()) {
            throw new RuntimeException("No specialty rows found in {$path}");
        }

        foreach ($rows as $row) {
            Specialty::updateOrCreate(
                ['code' => $row['code']],
                ['name' => $row['name']]
            );
        }

        $leftover = Specialty::query()
            ->where(function ($query) {
                $query->whereNull('code')->orWhere('code', '');
            })
            ->count();

        $this->command?->info("Seeded {$rows->count()} specialties from {$path} (sheet: {$sheet->getTitle()}).");

        if ($leftover > 0) {
            $this->command?->warn("Left {$leftover} existing name-only specialties without taxonomy codes unchanged.");
        }
    }

    public function defaultSourcePath(): string
    {
        foreach ([
            base_path('docs/'.self::SOURCE_FILENAME),
            database_path('data/taxonomy-list.xlsx'),
        ] as $candidate) {
            if (is_readable($candidate)) {
                return $candidate;
            }
        }

        return base_path('docs/'.self::SOURCE_FILENAME);
    }

    protected function resolveTaxonomySheet(Spreadsheet $spreadsheet): Worksheet
    {
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $headers = $this->headerMap($sheet);
            if ($this->columnIndex($headers, [self::CODE_HEADER]) !== null
                && $this->columnIndex($headers, [self::NAME_HEADER, 'name']) !== null) {
                return $sheet;
            }
        }

        return $spreadsheet->getSheet($spreadsheet->getFirstSheetIndex());
    }

    /**
     * @return Collection<int, array{code: string, name: string}>
     */
    public function rowsFromSheet(Worksheet $sheet): Collection
    {
        $headers = $this->headerMap($sheet);
        $codeIndex = $this->columnIndex($headers, [self::CODE_HEADER]);
        $nameIndex = $this->columnIndex($headers, [self::NAME_HEADER, 'name']);

        if ($codeIndex === null || $nameIndex === null) {
            throw new RuntimeException(
                'Taxonomy workbook must have "Code" and "Display Name" columns. Found: '.implode(', ', $headers)
            );
        }

        $highestRow = $sheet->getHighestDataRow();
        $highestColumn = $sheet->getHighestDataColumn();
        $rows = collect();

        for ($row = 2; $row <= $highestRow; $row++) {
            $values = $sheet->rangeToArray('A'.$row.':'.$highestColumn.$row, null, true, false)[0] ?? [];
            $code = trim((string) ($values[$codeIndex] ?? ''));
            $name = trim((string) ($values[$nameIndex] ?? ''));

            if ($code === '' || $name === '') {
                continue;
            }

            $rows->push([
                'code' => $code,
                'name' => $name,
            ]);
        }

        return $rows->unique('code')->values();
    }

    /**
     * @return list<string>
     */
    protected function headerMap(Worksheet $sheet): array
    {
        $highestColumn = $sheet->getHighestDataColumn();
        $headerRow = $sheet->rangeToArray('A1:'.$highestColumn.'1', null, true, false)[0] ?? [];

        return collect($headerRow)
            ->map(fn ($value) => mb_strtolower(trim((string) $value)))
            ->all();
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string>  $candidates
     */
    protected function columnIndex(array $headers, array $candidates): ?int
    {
        foreach ($candidates as $candidate) {
            $index = array_search($candidate, $headers, true);
            if ($index !== false) {
                return (int) $index;
            }
        }

        return null;
    }
}
