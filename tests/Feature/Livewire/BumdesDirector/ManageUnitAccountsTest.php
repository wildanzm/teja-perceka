<?php

use App\Livewire\BumdesDirector\ManageUnitAccounts;
use App\Models\BusinessUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

beforeEach(function () {
    seedCoreBumdesData();
});

test('director creates a unit head account', function () {
    $this->actingAs(createUserWithRole('direktur_bumdes'));
    $unit = BusinessUnit::where('code', 'SB')->firstOrFail();

    Livewire::test(ManageUnitAccounts::class)
        ->call('openCreate')
        ->set('createName', 'Kepala Baru')
        ->set('createEmail', 'kepala.baru@tejaperceka.test')
        ->set('createPassword', 'password123')
        ->set('createPassword_confirmation', 'password123')
        ->set('createBusinessUnitId', $unit->id)
        ->call('storeUser')
        ->assertHasNoErrors()
        ->assertDispatched('swal-alert', icon: 'success');

    $user = User::where('email', 'kepala.baru@tejaperceka.test')->firstOrFail();

    expect($user->hasRole('kepala_unit'))->toBeTrue()
        ->and($user->business_unit_id)->toBe($unit->id);
});

test('director cannot manage a user outside the unit-head boundary', function () {
    $this->actingAs(createUserWithRole('direktur_bumdes'));
    $villageHead = createUserWithRole('kepala_desa');

    expect(fn () => Livewire::test(ManageUnitAccounts::class)->call('openEdit', $villageHead->id))
        ->toThrow(ModelNotFoundException::class);
});

test('non-directors cannot open the account manager', function () {
    $this->actingAs(createUserWithRole('bendahara'));

    Livewire::test(ManageUnitAccounts::class)->assertForbidden();
});
