<?php

use App\Livewire\UnitHead\ManageRevenue;
use App\Models\Account;
use App\Models\BusinessUnit;
use App\Models\CategoryPriceHistory;
use App\Models\TransactionCategory;
use App\Notifications\CategoryPriceUpdated;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    seedCoreBumdesData();

    $this->unit = BusinessUnit::where('code', 'SC')->firstOrFail();
    $this->unitHead = createUserWithRole('kepala_unit', $this->unit);
    $this->treasurer = createUserWithRole('bendahara');
    $this->director = createUserWithRole('direktur_bumdes');
});

test('unit heads can update a category price', function () {
    $category = TransactionCategory::factory()->for($this->unit)->create([
        'account_id' => Account::where('code', '4-2000')->value('id'),
        'name' => 'Tiket SC',
    ]);
    CategoryPriceHistory::factory()->for($category)->create([
        'price' => 10_000,
        'effective_from' => '2026-01-01',
    ]);

    $this->actingAs($this->unitHead);

    Livewire::test(ManageRevenue::class)
        ->set("prices.{$category->id}", '15.000')
        ->call('updatePrice', $category->id)
        ->assertHasNoErrors()
        ->assertDispatched('swal-alert', icon: 'success');

    expect($category->priceAt(now()))->toBe(15_000.0);
});

test('price updates notify the financial roles', function () {
    Notification::fake();

    $category = TransactionCategory::factory()->for($this->unit)->create([
        'account_id' => Account::where('code', '4-2000')->value('id'),
        'name' => 'Tiket SC',
    ]);
    CategoryPriceHistory::factory()->for($category)->create([
        'price' => 10_000,
        'effective_from' => '2026-01-01',
    ]);

    $this->actingAs($this->unitHead);

    Livewire::test(ManageRevenue::class)
        ->set("prices.{$category->id}", '15.000')
        ->call('updatePrice', $category->id);

    Notification::assertSentTo(
        [$this->treasurer, $this->director],
        CategoryPriceUpdated::class,
    );
});
