<?php

use App\Livewire\ProfitLoss\ProfitAllocation;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\OpeningBalance;
use App\Models\ProfitAllocationClosing;
use Livewire\Livewire;

beforeEach(function () {
    seedCoreBumdesData();
    $this->actingAs(createUserWithRole('direktur_bumdes'));
    OpeningBalance::create(['period' => '2026-08', 'amount' => 1_000_000]);
});

test('profit allocation exposes the calculated net income', function () {
    $component = Livewire::test(ProfitAllocation::class)
        ->set('mode', 'monthly')
        ->set('period', '2026-09');

    expect($component->get('netIncome'))->toBe(1_000_000.0);
});

test('applying an allocation locks the period and writes journals', function () {
    $outflowAccount = Account::where('code', '6-0020')->firstOrFail();
    $component = Livewire::test(ProfitAllocation::class)
        ->set('mode', 'monthly')
        ->set('period', '2026-09');

    $component->call('openCreate')
        ->set('formDescription', 'Dana Sosial')
        ->set('formGroup', 'pengurang')
        ->set('formCalcType', 'percentage')
        ->set('formPercentage', '10')
        ->set('formFlowType', 'outflow')
        ->set('formAccountId', $outflowAccount->id)
        ->call('saveRow');

    $component->call('applyAllocation')
        ->assertDispatched('swal-alert', icon: 'success');

    $closing = ProfitAllocationClosing::where('period_key', '2026-09')->firstOrFail();

    expect($component->get('isLocked'))->toBeTrue()
        ->and($closing->journal_ids)->toHaveCount(2);

    $component->call('openCreate')
        ->assertSet('showModal', false);
});

test('rollback removes the closing record and its generated journals', function () {
    $outflowAccount = Account::where('code', '6-0020')->firstOrFail();
    $component = Livewire::test(ProfitAllocation::class)
        ->set('mode', 'monthly')
        ->set('period', '2026-09');

    $component->call('openCreate')
        ->set('formDescription', 'Dana Sosial')
        ->set('formGroup', 'pengurang')
        ->set('formCalcType', 'percentage')
        ->set('formPercentage', '10')
        ->set('formFlowType', 'outflow')
        ->set('formAccountId', $outflowAccount->id)
        ->call('saveRow')
        ->call('applyAllocation');

    $closing = ProfitAllocationClosing::where('period_key', '2026-09')->firstOrFail();

    $component->call('rollbackAllocation')
        ->assertDispatched('swal-alert', icon: 'success');

    expect(ProfitAllocationClosing::where('period_key', '2026-09')->exists())->toBeFalse()
        ->and(JournalEntry::whereIn('id', $closing->journal_ids)->exists())->toBeFalse();
});
