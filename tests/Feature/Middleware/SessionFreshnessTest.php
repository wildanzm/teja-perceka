<?php

test('a fresh session adopts the stored session version', function () {
    $user = createUserWithRole('bendahara', null, ['session_version' => 2]);

    $this->actingAs($user)
        ->get(route('bendahara.dashboard'))
        ->assertOk()
        ->assertSessionHas('session_version', 2);
});

test('a bumped session version logs the user out', function () {
    $user = createUserWithRole('bendahara', null, ['session_version' => 2]);

    $user->increment('session_version');

    $this->actingAs($user->fresh())
        ->withSession(['session_version' => 2])
        ->get(route('bendahara.dashboard'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});
