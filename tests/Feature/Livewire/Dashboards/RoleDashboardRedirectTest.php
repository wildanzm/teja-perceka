<?php

use App\Models\BusinessUnit;
use App\Models\User;

beforeEach(function () {
    seedCoreBumdesData();
});

test('dashboard routes users by role', function (string $role, string $routeName) {
    $unit = BusinessUnit::where('code', 'SB')->firstOrFail();
    $user = createUserWithRole($role, $role === 'kepala_unit' ? $unit : null);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route($routeName));
})->with([
    ['kepala_unit', 'dashboard.unit'],
    ['sekretaris', 'sekretaris.dashboard'],
    ['bendahara', 'bendahara.dashboard'],
    ['direktur_bumdes', 'direktur-bumdes.dashboard'],
    ['kepala_desa', 'kepala-desa.dashboard'],
    ['pengawas', 'pengawas.dashboard'],
]);

test('roleless users cannot reach the dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertForbidden();
});
