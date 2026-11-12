<?php

use App\Models\CategoryPriceHistory;
use App\Models\TransactionCategory;

test('priceAt returns the latest price effective on or before the date', function () {
    $category = TransactionCategory::factory()->create();

    CategoryPriceHistory::factory()->for($category)->create([
        'price' => 10_000,
        'effective_from' => '2026-01-01',
    ]);
    CategoryPriceHistory::factory()->for($category)->create([
        'price' => 15_000,
        'effective_from' => '2026-06-01',
    ]);

    expect($category->priceAt('2025-12-31'))->toBe(0.0)
        ->and($category->priceAt('2026-03-15'))->toBe(10_000.0)
        ->and($category->priceAt('2026-06-02'))->toBe(15_000.0);
});
