<?php

use App\Livewire\ProfitLoss\ProfitLoss;
use App\Models\OpeningBalance;
use Livewire\Livewire;

beforeEach(function () {
    seedCoreBumdesData();
    $this->actingAs(createUserWithRole('direktur_bumdes'));
    OpeningBalance::create(['period' => '2026-08', 'amount' => 1_000_000]);
});

test('monthly profit and loss reconciles the opening balance', function () {
    $component = Livewire::test(ProfitLoss::class)
        ->set('unit_id', 'bumdes')
        ->set('mode', 'monthly')
        ->set('period', '2026-09');

    expect($component->get('reportData')['netIncome'])->toBe(1_000_000.0);
});
