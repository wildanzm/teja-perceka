<?php

use App\Livewire\UnitHead\Dashboard;
use App\Models\BusinessUnit;
use App\Models\DailyTransaction;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    seedCoreBumdesData();
});

afterEach(function () {
    Carbon::setTestNow();
});

test('weekly unit dashboards flag the current week as submitted once it has an entry', function () {
    Carbon::setTestNow('2026-09-16 09:00:00');

    $unit = BusinessUnit::where('code', 'TPS')->firstOrFail();

    DailyTransaction::factory()->for($unit)->create([
        'transaction_date' => '2026-09-15',
    ]);

    $this->actingAs(createUserWithRole('kepala_unit', $unit));

    Livewire::test(Dashboard::class)
        ->assertSet('isCurrentPeriodSubmitted', true);
});

test('weekly unit dashboards flag an empty week as not submitted', function () {
    Carbon::setTestNow('2026-09-16 09:00:00');

    $unit = BusinessUnit::where('code', 'TPS')->firstOrFail();

    $this->actingAs(createUserWithRole('kepala_unit', $unit));

    Livewire::test(Dashboard::class)
        ->assertSet('isCurrentPeriodSubmitted', false);
});

test('changing the dashboard period refreshes the recap chart', function () {
    Carbon::setTestNow('2026-09-16 09:00:00');

    $unit = BusinessUnit::where('code', 'TPS')->firstOrFail();
    $this->actingAs(createUserWithRole('kepala_unit', $unit));

    Livewire::test(Dashboard::class)
        ->set('period', 'mingguan')
        ->assertDispatched('recap-chart-update');
});

test('non unit heads cannot open the unit dashboard', function () {
    $this->actingAs(createUserWithRole('bendahara'));

    Livewire::test(Dashboard::class)->assertForbidden();
});
