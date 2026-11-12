<?php

use App\Models\User;
use App\Support\RoleLabels;
use Spatie\Permission\Models\Role;

test('role labels map canonical and unknown roles', function () {
    expect(RoleLabels::label('bendahara'))->toBe('Bendahara')
        ->and(RoleLabels::label('kepala_unit'))->toBe('Kepala Unit')
        ->and(RoleLabels::label('unknown'))->toBe('Tidak ada Role');
});

test('existing roles are returned in canonical order', function () {
    Role::create(['name' => 'bendahara']);
    Role::create(['name' => 'kepala_unit']);
    Role::create(['name' => 'custom_extra']);

    expect(RoleLabels::existingOrdered())->toBe(['bendahara', 'kepala_unit', 'custom_extra']);
});

test('caseSql orders users by the configured role priority', function () {
    $cashier = createUserWithRole('bendahara');
    $unitHead = createUserWithRole('kepala_unit');

    [$sql, $bindings] = RoleLabels::caseSql(User::class, ['kepala_unit', 'bendahara']);

    $ordered = User::query()
        ->orderByRaw($sql, $bindings)
        ->pluck('id')
        ->all();

    expect($ordered)->toBe([$unitHead->id, $cashier->id]);
});
