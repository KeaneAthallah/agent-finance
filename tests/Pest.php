<?php

use App\Models\Opd;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(Tests\TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function testRole(string $slug): Role
{
    return Role::query()->firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug)]);
}

function testOpd(): Opd
{
    return Opd::query()->firstOrCreate(['code' => '9.99.0'], ['name' => 'Test OPD']);
}

function testUser(string $roleSlug, ?Opd $opd = null, array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'role_id' => testRole($roleSlug)->id,
        'opd_id' => $opd?->id,
        'is_active' => true,
    ], $attributes));
}

/**
 * Fake the configured documents disk and return its name, so tests never
 * hardcode the disk id (spec §8).
 */
function fakeDocumentsDisk(): string
{
    $disk = (string) config('agent-finance.documents.disk', 'documents');

    Storage::fake($disk);

    return $disk;
}

/**
 * Builds a minimal but fully valid single-page PDF (with xref/startxref)
 * containing the given text, so the real parser can process it in tests.
 */
function minimalPdf(string $text = 'Nomor: SPM-001/2026 Jumlah Rp125.000.000'): string
{
    $stream = 'BT /F1 12 Tf 72 720 Td ('.addcslashes($text, '()\\').') Tj ET';

    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>',
        '<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream",
    ];

    $pdf = "%PDF-1.4\n";
    $offsets = [];

    foreach ($objects as $index => $body) {
        $offsets[$index + 1] = strlen($pdf);
        $pdf .= ($index + 1)." 0 obj\n".$body."\nendobj\n";
    }

    $xrefPosition = strlen($pdf);
    $pdf .= 'xref\n0 '.(count($objects) + 1)."\n";
    $pdf .= "0000000000 65535 f \n";

    foreach ($offsets as $offset) {
        $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT)." 00000 n \n";
    }

    $pdf .= 'trailer\n<< /Size '.(count($objects) + 1)." /Root 1 0 R >>\n";
    $pdf .= 'startxref\n'.$xrefPosition."\n%%EOF";

    return $pdf;
}
