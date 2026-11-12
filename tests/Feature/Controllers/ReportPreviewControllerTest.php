<?php

use App\Models\Account;
use App\Models\BusinessUnit;

beforeEach(function () {
    seedCoreBumdesData();
    $this->actingAs(createUserWithRole('direktur_bumdes'));
});

test('the profit and loss report previews as a pdf', function () {
    $this->get(route('reports.preview', [
        'report' => 'laba-rugi',
        'unit' => 'bumdes',
        'mode' => 'monthly',
        'period' => '2026-09',
    ]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('the general ledger report previews as a pdf for a selected account', function () {
    $account = Account::where('code', '1-1100')->firstOrFail();

    $this->get(route('reports.preview', [
        'report' => 'buku-besar',
        'unit' => 'bumdes',
        'period' => '2026-09',
        'account' => $account->id,
    ]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('the general ledger preview is not found without an account', function () {
    $this->get(route('reports.preview', [
        'report' => 'buku-besar',
        'unit' => 'bumdes',
        'period' => '2026-09',
    ]))->assertNotFound();
});

test('the trial balance report previews as a pdf', function () {
    $this->get(route('reports.preview', [
        'report' => 'neraca-saldo',
        'unit' => 'bumdes',
        'period' => '2026-09',
    ]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('the profit allocation report previews as a pdf', function () {
    $this->get(route('reports.profit-allocation.preview', [
        'mode' => 'monthly',
        'period' => '2026-09',
    ]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('the general journal report previews as a pdf', function () {
    $this->get(route('history-recap.journal-preview', [
        'mode' => 'monthly',
        'month' => '2026-09',
    ]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('an unknown report type is not found', function () {
    $this->get(route('reports.preview', [
        'report' => 'tidak-ada',
        'unit' => 'bumdes',
        'mode' => 'monthly',
    ]))->assertNotFound();
});

test('report preview rejects an invalid unit scope', function () {
    $this->get(route('reports.preview', [
        'report' => 'laba-rugi',
        'unit' => 'unknown_scope',
        'mode' => 'monthly',
    ]))->assertUnprocessable();
});

test('report preview rejects an unsupported period mode', function () {
    $this->get(route('reports.preview', [
        'report' => 'laba-rugi',
        'unit' => 'bumdes',
        'mode' => 'daily',
    ]))->assertUnprocessable();
});

test('report preview rejects read-only roles', function () {
    $this->actingAs(createUserWithRole('pengawas'))
        ->get(route('reports.preview', [
            'report' => 'laba-rugi',
            'unit' => 'bumdes',
            'mode' => 'monthly',
        ]))
        ->assertForbidden();
});

test('a unit head always previews their own unit', function () {
    $unit = BusinessUnit::where('code', 'SC')->firstOrFail();
    $other = BusinessUnit::where('code', 'SB')->firstOrFail();

    $this->actingAs(createUserWithRole('kepala_unit', $unit))
        ->get(route('reports.preview', [
            'report' => 'laba-rugi',
            'unit' => $other->id,
            'mode' => 'monthly',
            'period' => '2026-09',
        ]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});
