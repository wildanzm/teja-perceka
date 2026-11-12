<?php

use App\Support\SafeDates;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-09-15 10:00:00');
});

test('safe day parses valid dates and falls back to today', function () {
    expect(SafeDates::day('2026-08-20')->format('Y-m-d'))->toBe('2026-08-20')
        ->and(SafeDates::day(null)->format('Y-m-d'))->toBe('2026-09-15')
        ->and(SafeDates::day('not-a-date')->format('Y-m-d'))->toBe('2026-09-15');
});

test('safe month validates strict year-month values', function () {
    expect(SafeDates::month('2026-04')->format('Y-m-d'))->toBe('2026-04-01')
        ->and(SafeDates::month('2026-4')->format('Y-m-d'))->toBe('2026-09-01')
        ->and(SafeDates::month('2026-13')->format('Y-m-d'))->toBe('2026-09-01');
});

test('safe year clamps values and defaults invalid values', function () {
    expect(SafeDates::year('2025'))->toBe(2025)
        ->and(SafeDates::year('1995'))->toBe(2000)
        ->and(SafeDates::year('2150'))->toBe(2100)
        ->and(SafeDates::year('invalid'))->toBe(2026);
});
