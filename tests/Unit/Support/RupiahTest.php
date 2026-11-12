<?php

use App\Support\Rupiah;

test('rupiah parsing removes formatting and safely handles invalid input', function (mixed $input, float $expected) {
    expect(Rupiah::parse($input))->toBe($expected);
})->with([
    ['15.000', 15000.0],
    ['Rp 1.250.500', 1250500.0],
    [25000, 25000.0],
    ['', 0.0],
    [null, 0.0],
    ['abc', 0.0],
]);

test('rupiah formatting uses Indonesian thousand separators', function (mixed $input, string $expected) {
    expect(Rupiah::format($input))->toBe($expected);
})->with([
    [0, '0'],
    [null, '0'],
    [15000, '15.000'],
    [2500000.4, '2.500.000'],
]);
