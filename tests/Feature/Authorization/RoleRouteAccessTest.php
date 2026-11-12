<?php

use App\Models\BusinessUnit;
use App\Models\User;

beforeEach(function () {
    seedCoreBumdesData();
});

test('allowed roles can reach their routes', function (string $routeName, string $role) {
    $unit = $role === 'kepala_unit' ? BusinessUnit::where('code', 'SC')->firstOrFail() : null;
    $user = createUserWithRole($role, $unit);

    $this->actingAs($user)
        ->get(route($routeName))
        ->assertSuccessful();
})->with('role route access');

test('users without a role are forbidden', function (string $routeName) {
    $this->actingAs(User::factory()->create())
        ->get(route($routeName))
        ->assertForbidden();
})->with('protected routes');
