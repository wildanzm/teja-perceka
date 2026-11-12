<?php

use App\Livewire\Transactions\HistoryRecap;
use App\Models\BusinessUnit;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    seedCoreBumdesData();
    Carbon::setTestNow('2026-09-15 10:00:00');
});

test('history recap defaults to the current period and the BUMDes scope', function () {
    $this->actingAs(createUserWithRole('bendahara'));

    Livewire::test(HistoryRecap::class)
        ->assertOk()
        ->assertSet('tab', 'revenue')
        ->assertSet('unit_id', 'bumdes')
        ->assertSet('month', '2026-09');
});

test('unit heads are pinned to their own unit', function () {
    $unit = BusinessUnit::where('code', 'TPS')->firstOrFail();
    $this->actingAs(createUserWithRole('kepala_unit', $unit));

    Livewire::test(HistoryRecap::class)
        ->assertOk()
        ->assertSet('unit_id', $unit->id);
});

test('an unknown tab falls back to revenue', function () {
    $this->actingAs(createUserWithRole('bendahara'));

    Livewire::test(HistoryRecap::class)
        ->set('tab', 'invalid')
        ->assertSet('tab', 'revenue');
});
