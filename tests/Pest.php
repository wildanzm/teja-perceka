<?php

use App\Models\Account;
use App\Models\BusinessUnit;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Database\Seeders\BusinessUnitSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Unit tests stay database-free on purpose: they exercise pure value objects
| and helpers. Anything that boots the application or touches the database
| belongs in tests/Feature.
|
*/

pest()->extend(TestCase::class)
    ->use(LazilyRefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Seed the production reference data every role-driven feature needs.
 */
function seedCoreBumdesData(): void
{
    (new RoleSeeder)->run();
    (new BusinessUnitSeeder)->run();
    (new AccountSeeder)->run();
}

/**
 * @param  array<string, mixed>  $attributes
 */
function createUserWithRole(string $role, ?BusinessUnit $unit = null, array $attributes = []): User
{
    Role::firstOrCreate(['name' => $role]);

    $user = User::factory()->create(array_merge([
        'business_unit_id' => $unit?->id,
        'is_active' => true,
        'session_version' => 0,
    ], $attributes));

    $user->assignRole($role);

    return $user;
}

/**
 * Roles that must be able to reach each application route.
 *
 * @return array<string, array{0: string, 1: string}>
 */
function roleRouteAccessMatrix(): array
{
    return [
        'secretary dashboard' => ['sekretaris.dashboard', 'sekretaris'],
        'treasurer dashboard' => ['bendahara.dashboard', 'bendahara'],
        'finance dashboard' => ['dashboard.finance', 'bendahara'],
        'village head dashboard' => ['kepala-desa.dashboard', 'kepala_desa'],
        'village head user manager' => ['kepala-desa.users', 'kepala_desa'],
        'director dashboard' => ['direktur-bumdes.dashboard', 'direktur_bumdes'],
        'director account manager' => ['direktur-bumdes.manage-accounts', 'direktur_bumdes'],
        'supervisor dashboard' => ['pengawas.dashboard', 'pengawas'],
        'unit dashboard' => ['dashboard.unit', 'kepala_unit'],
        'unit price manager' => ['unit.manage-prices', 'kepala_unit'],
        'unit expense form' => ['unit.record-expense', 'kepala_unit'],
        'expenses form' => ['expenses.record', 'bendahara'],
        'BUMDes income form' => ['bumdes.record-transaction', 'bendahara'],
        'profit and loss report' => ['reports.profit-loss', 'pengawas'],
        'general ledger report' => ['reports.general-ledger', 'pengawas'],
        'trial balance report' => ['reports.trial-balance', 'pengawas'],
        'profit allocation report' => ['reports.profit-allocation', 'pengawas'],
        'assets page' => ['assets.manage', 'kepala_unit'],
        'history recap' => ['history-recap', 'pengawas'],
    ];
}

dataset('role route access', roleRouteAccessMatrix());

dataset('protected routes', array_map(fn (array $row): string => $row[0], roleRouteAccessMatrix()));

/**
 * Load the chart of accounts from its production seeder and key it by alias.
 *
 * @return array<string, Account>
 */
function createCoreAccounts(): array
{
    (new AccountSeeder)->run();

    $aliases = [
        'cash' => '1-1100',
        'bank' => '1-1200',
        'payable' => '2-1100',
        'retained' => '3-2000',
        'net_income' => '3-3000',
        'revenue' => '4-2000',
        'return_revenue' => '4-4000',
        'hpp' => '5-1000',
        'expense' => '6-0001',
        'other_revenue' => '7-1000',
        'other_expense' => '7-2000',
    ];

    $accounts = Account::whereIn('code', array_values($aliases))->get()->keyBy('code');

    $result = [];

    foreach ($aliases as $alias => $code) {
        $result[$alias] = $accounts->get($code) ?? throw new RuntimeException("Seed account [{$code}] is missing.");
    }

    return $result;
}
