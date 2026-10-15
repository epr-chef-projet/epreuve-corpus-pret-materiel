<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class PdfEngineTest extends TestCase
{
    public function test_local_fonts_accents_long_lines_and_one_hundred_rows_are_rendered_with_scripts_disabled(): void
    {
        $this->assertFalse(config('dompdf.options.enable_php'));
        $this->assertFalse(config('dompdf.options.enable_javascript'));
        $this->assertFalse(config('dompdf.options.enable_remote'));
        $assetRoot = storage_path('app/private/pdf-assets');
        if (! is_dir($assetRoot) && ! mkdir($assetRoot, 0700, true)) {
            $this->fail('Could not create private PDF asset directory.');
        }
        $this->assertSame($assetRoot, config('dompdf.options.chroot'));
        $rows = '';
        for ($row = 1; $row <= 100; $row++) {
            $rows .= '<tr><td>'.$row.'</td><td>'.e('Équipement à prêter — Loan equipment — '.str_repeat('à vérifier ', 8)).'</td></tr>';
        }
        $html = '<html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans;font-size:10pt}tr{page-break-inside:avoid}</style></head><body><h1>Prêt — Loan</h1><table>'.$rows.'</table></body></html>';
        $document = Pdf::loadHTML($html)->setPaper('a4');
        $bytes = $document->output();
        $this->assertStringStartsWith('%PDF-', $bytes);
        $this->assertGreaterThan(1, $document->getDomPDF()->getCanvas()->get_page_count());
        $this->assertStringNotContainsString('/JavaScript', $bytes);
        $this->assertGreaterThan(10000, strlen($bytes));
        $this->assertTrue(Storage::disk('local')->put('foundation/pdf-probe.pdf', $bytes));
    }
}
