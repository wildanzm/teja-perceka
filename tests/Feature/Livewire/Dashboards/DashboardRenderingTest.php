<?php

use App\Livewire\BumdesDirector\Dashboard as DirectorDashboard;
use Livewire\Livewire;

beforeEach(function () {
    seedCoreBumdesData();
});

test('director dashboard refreshes the recap chart when the period changes', function () {
    $this->actingAs(createUserWithRole('direktur_bumdes'));

    Livewire::test(DirectorDashboard::class)
        ->set('period', 'semester')
        ->assertSet('period', 'semester')
        ->assertDispatched('recap-chart-update');
});
