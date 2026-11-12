<?php

use App\Enums\CategoryType;

test('category type flags identify quantity based categories', function (CategoryType $type, bool $expected) {
    expect($type->usesQuantity())->toBe($expected)
        ->and($type->needsPrice())->toBe($expected);
})->with([
    [CategoryType::PriceTimesQuantity, true],
    [CategoryType::Yearly, true],
    [CategoryType::Flat, false],
    [CategoryType::Custom, false],
]);

test('category type value lists and labels are stable', function () {
    expect(CategoryType::manageableValues())->toBe(['harga_x_qty', 'tahunan', 'bebas'])
        ->and(CategoryType::quantityValues())->toBe(['harga_x_qty', 'tahunan'])
        ->and(CategoryType::Flat->label())->toBe('Tarif Flat')
        ->and(CategoryType::Custom->label())->toBe('Bebas (nominal diisi manual saat transaksi)');
});
