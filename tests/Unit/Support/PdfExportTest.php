<?php

use App\Support\PdfExport;
use Illuminate\Support\Carbon;

afterEach(function () {
    Carbon::setLocale('en');
});

test('pdf filenames sanitize unsafe characters and collapse separators', function () {
    expect(PdfExport::filename('Jurnal_Umum', 'BUMDes/Unit-Wisata', "\"Ringkas\";\r\n", 'September   2026'))
        ->toBe('Jurnal Umum BUMDes Unit Wisata Ringkas September 2026.pdf');
});

test('pdf filenames cap the basename and keep the extension', function () {
    $filename = PdfExport::filename(str_repeat('A', 220));

    expect(mb_strlen($filename))->toBeLessThanOrEqual(184)
        ->and($filename)->toEndWith('.pdf')
        ->and($filename)->not->toContain('/');
});

test('pdf signature dates use Indonesian month names', function () {
    Carbon::setLocale('id');

    expect(PdfExport::signatureDate(Carbon::create(2026, 9, 30)))->toBe('30 September 2026');
});
