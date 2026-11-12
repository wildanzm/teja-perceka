<?php

use App\Models\User;

test('initials use the first and last words of the name', function (string $name, string $expected) {
    expect((new User(['name' => $name]))->initials())->toBe($expected);
})->with([
    ['Wildan Zaki Mubarok', 'WM'],
    ['Administrator', 'A'],
]);
