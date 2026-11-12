<?php

use App\Livewire\Reports\GeneralLedger;
use App\Models\BusinessUnit;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    seedCoreBumdesData();
    Carbon::setTestNow('2026-09-15 10:00:00');
});

test('general ledger renders for a financial role', function () {
    $this->actingAs(createUserWithRole('bendahara'));

    Livewire::test(GeneralLedger::class)
        ->assertOk()
        ->assertSet('unit_id', 'bumdes')
        ->assertSet('period', '2026-09');
});

test('unit heads are pinned to their own unit', function () {
    $unit = BusinessUnit::where('code', 'SC')->firstOrFail();
    $this->actingAs(createUserWithRole('kepala_unit', $unit));

    Livewire::test(GeneralLedger::class)
        ->assertOk()
        ->assertSet('unit_id', $unit->id);
});

test('unit heads cannot switch to another unit', function () {
    $unit = BusinessUnit::where('code', 'SC')->firstOrFail();
    $other = BusinessUnit::where('code', 'SB')->firstOrFail();
    $this->actingAs(createUserWithRole('kepala_unit', $unit));

    Livewire::test(GeneralLedger::class)
        ->set('unit_id', $other->id)
        ->assertForbidden();
});
