<?php

use App\Livewire\Settings\TwoFactor\RecoveryCodes;
use App\Models\User;
use Laravel\Fortify\Features;
use Livewire\Livewire;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
});

test('stored recovery codes are loaded on mount', function () {
    $this->actingAs(User::factory()->withTwoFactor()->create());

    Livewire::test(RecoveryCodes::class)
        ->assertSet('recoveryCodes', ['recovery-code-1']);
});

test('regenerating replaces the stored recovery codes', function () {
    $this->actingAs(User::factory()->withTwoFactor()->create());

    $component = Livewire::test(RecoveryCodes::class)
        ->call('regenerateRecoveryCodes')
        ->assertHasNoErrors()
        ->assertDispatched('swal-alert', icon: 'success');

    $codes = $component->get('recoveryCodes');

    expect($codes)->toHaveCount(8)
        ->and($codes)->not->toContain('recovery-code-1');
});
