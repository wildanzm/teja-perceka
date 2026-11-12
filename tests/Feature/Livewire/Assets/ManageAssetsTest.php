<?php

use App\Livewire\Assets\ManageAssets;
use App\Models\Asset;
use Livewire\Livewire;

beforeEach(function () {
    seedCoreBumdesData();
});

test('treasurer can create an asset', function () {
    $this->actingAs(createUserWithRole('bendahara'));

    Livewire::test(ManageAssets::class)
        ->call('openCreate')
        ->set('name', 'Laptop Operasional')
        ->set('quantity', 2)
        ->set('unit', 'unit')
        ->set('price', '7.500.000')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('swal-alert', icon: 'success');

    expect(Asset::where('name', 'Laptop Operasional')->value('price'))->toBe(7_500_000.0);
});

test('read-only roles cannot mutate assets', function () {
    $this->actingAs(createUserWithRole('pengawas'));

    Livewire::test(ManageAssets::class)
        ->call('openCreate')
        ->assertForbidden();
});
