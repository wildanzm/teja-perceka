<?php

use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\ReportPreviewController;
use App\Livewire\Assets\ManageAssets;
use App\Livewire\BumdesDirector\Dashboard as BumdesDirectorDashboard;
use App\Livewire\BumdesDirector\ManageUnitAccounts;
use App\Livewire\Expenses\RecordExpense;
use App\Livewire\ProfitLoss\ProfitAllocation;
use App\Livewire\ProfitLoss\ProfitLoss;
use App\Livewire\Reports\GeneralLedger;
use App\Livewire\Reports\TrialBalance;
use App\Livewire\Secretary\Dashboard as SecretaryDashboard;
use App\Livewire\SuperAdmin\ActivityLogs as SuperAdminActivityLogs;
use App\Livewire\SuperAdmin\Dashboard as SuperAdminDashboard;
use App\Livewire\SuperAdmin\UserManager as SuperAdminUserManager;
use App\Livewire\Supervisor\Dashboard as SupervisorDashboard;
use App\Livewire\Transactions\HistoryRecap;
use App\Livewire\Transactions\RecordIncome;
use App\Livewire\Treasurer\Dashboard as TreasurerDashboard;
use App\Livewire\UnitHead\Dashboard as UnitHeadDashboard;
use App\Livewire\UnitHead\ManageRevenue;
use App\Livewire\UnitHead\RecordExpense as UnitHeadRecordExpense;
use App\Livewire\VillageHead\Dashboard as VillageHeadDashboard;
use App\Livewire\VillageHead\UserManager as VillageHeadUserManager;
use App\Models\DailyTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Root redirect to /login
Route::redirect('/', '/login')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {

    // Auto-redirect by role after login
    Route::get('/dashboard', function (Request $request) {
        $user = $request->user();

        if ($user->hasRole('super_admin')) {
            return redirect()->route('super-admin.dashboard');
        }
        if ($user->hasRole('kepala_unit')) {
            return redirect()->route('dashboard.unit');
        }
        if ($user->hasRole('sekretaris')) {
            return redirect()->route('sekretaris.dashboard');
        }
        if ($user->hasRole('bendahara')) {
            return redirect()->route('bendahara.dashboard');
        }
        if ($user->hasRole('direktur_bumdes')) {
            return redirect()->route('direktur-bumdes.dashboard');
        }
        if ($user->hasRole('kepala_desa')) {
            return redirect()->route('kepala-desa.dashboard');
        }
        if ($user->hasRole('pengawas')) {
            return redirect()->route('pengawas.dashboard');
        }

        abort(403, 'Role tidak dikenali.');
    })->name('dashboard');

    // ===== Unit heads =====
    Route::middleware(['role:kepala_unit'])->group(function () {
        Route::get('unit/dashboard', UnitHeadDashboard::class)
            ->name('dashboard.unit');

        Route::get('unit/input-transaksi', function () {
            return view('dashboard.unit-input');
        })->name('unit.record-transaction');

        Route::get('unit/riwayat-transaksi', fn () => redirect()->route('history-recap'))
            ->name('unit.transaction-history');

        Route::get('unit/kelola-harga', ManageRevenue::class)
            ->name('unit.manage-prices');

        Route::get('unit/catat-pengeluaran', UnitHeadRecordExpense::class)
            ->name('unit.record-expense');

        Route::get('unit/edit-transaksi/{dailyTransaction}', function (DailyTransaction $dailyTransaction) {
            return view('dashboard.unit-edit', ['editId' => $dailyTransaction->id]);
        })->name('unit.edit-transaction');
    });

    // ===== Secretary =====
    Route::middleware(['role:sekretaris'])->prefix('sekretaris')->group(function () {
        Route::get('/dashboard', SecretaryDashboard::class)->name('sekretaris.dashboard');
    });

    // ===== Treasurer =====
    Route::middleware(['role:bendahara'])->prefix('bendahara')->group(function () {
        Route::get('/dashboard', TreasurerDashboard::class)->name('bendahara.dashboard');
    });

    // Joint Secretary & Treasurer dashboard (shared placeholder page)
    Route::middleware(['role:sekretaris|bendahara'])->get('dashboard/keuangan', function () {
        return view('dashboard.finance');
    })->name('dashboard.finance');

    // ===== Village head =====
    Route::middleware(['role:kepala_desa'])->prefix('kepala-desa')->group(function () {
        Route::get('/dashboard', VillageHeadDashboard::class)->name('kepala-desa.dashboard');
        Route::get('/users', VillageHeadUserManager::class)->name('kepala-desa.users');
    });

    // ===== BUMDes director =====
    Route::middleware(['role:direktur_bumdes'])->prefix('direktur-bumdes')->group(function () {
        Route::get('/dashboard', BumdesDirectorDashboard::class)->name('direktur-bumdes.dashboard');

        Route::get('/kelola-akun', ManageUnitAccounts::class)->name('direktur-bumdes.manage-accounts');
    });

    // ===== Supervisor =====
    Route::middleware(['role:pengawas'])->prefix('pengawas')->group(function () {
        Route::get('/dashboard', SupervisorDashboard::class)->name('pengawas.dashboard');
    });

    // ===== Expenses (shared: direktur_bumdes, sekretaris, bendahara) =====
    Route::middleware(['role:direktur_bumdes|sekretaris|bendahara'])
        ->get('pengeluaran/catat', RecordExpense::class)
        ->name('expenses.record');

    // ===== Transactions (BUMDes income: direktur_bumdes, sekretaris, bendahara) =====
    Route::middleware(['role:direktur_bumdes|sekretaris|bendahara'])
        ->get('transaksi/input', RecordIncome::class)
        ->name('bumdes.record-transaction');
    // ===== Centralized financial reports =====
    Route::middleware(['role:kepala_unit|sekretaris|bendahara|direktur_bumdes|kepala_desa|pengawas'])->group(function () {
        Route::get('laporan/laba-rugi', ProfitLoss::class)->name('reports.profit-loss');
        Route::get('laporan/buku-besar', GeneralLedger::class)->name('reports.general-ledger');
        Route::get('laporan/neraca-saldo', TrialBalance::class)->name('reports.trial-balance');
        // Report PDF previews (inline): new tab, printable via Ctrl+P without downloading.
        // Same 4 print roles as canPrint; read-only roles must not export.
        Route::middleware(['role:kepala_unit|sekretaris|bendahara|direktur_bumdes'])
            ->get('laporan/{report}/print', ReportPreviewController::class)
            ->whereIn('report', ['laba-rugi', 'buku-besar', 'neraca-saldo'])
            ->name('reports.preview');
    });

    // ===== Profit allocation (excluding unit heads) =====
    Route::middleware(['role:sekretaris|bendahara|direktur_bumdes|kepala_desa|pengawas'])->group(function () {
        Route::get('laporan/alokasi-laba', ProfitAllocation::class)->name('reports.profit-allocation');
        Route::middleware(['role:sekretaris|bendahara|direktur_bumdes'])
            ->get('laporan/alokasi-laba/print', ReportPreviewController::class)
            ->defaults('report', 'alokasi-laba')
            ->name('reports.profit-allocation.preview');
    });

    // ===== Assets (Direktur, Secretary, Treasurer can CRUD; others read-only via policy) =====
    Route::middleware(['role:direktur_bumdes|sekretaris|bendahara|kepala_desa|pengawas|kepala_unit'])
        ->get('asset', ManageAssets::class)
        ->name('assets.manage');

    // ===== Income (all roles) — redirect to History & Recap =====
    Route::middleware(['role:kepala_unit|sekretaris|bendahara|direktur_bumdes|kepala_desa|pengawas'])
        ->get('pendapatan', fn () => redirect()->route('history-recap', ['tab' => 'revenue']))
        ->name('revenue');

    // ===== History & Recap (combined Income + General Journal) =====
    Route::middleware(['role:kepala_unit|sekretaris|bendahara|direktur_bumdes|kepala_desa|pengawas'])
        ->get('riwayat-rekap', HistoryRecap::class)
        ->name('history-recap');

    // Journal PDF preview (inline): opens in a new tab, printable via Ctrl+P without downloading.
    Route::middleware(['role:kepala_unit|sekretaris|bendahara|direktur_bumdes|kepala_desa|pengawas'])
        ->get('riwayat-rekap/jurnal/print', ReportPreviewController::class)
        ->defaults('report', 'jurnal-umum')
        ->name('history-recap.journal-preview');

    // ===== Super Admin =====
    Route::middleware(['role:super_admin'])->prefix('super-admin')->group(function () {
        Route::get('/dashboard', SuperAdminDashboard::class)->name('super-admin.dashboard');
        Route::get('/users', SuperAdminUserManager::class)->name('super-admin.users');
        Route::get('/activity-logs', SuperAdminActivityLogs::class)->name('super-admin.activity-logs');
    });

    // ===== Impersonation routes =====
    Route::post('/impersonate/start/{user}', [ImpersonationController::class, 'start'])->name('impersonate.start');
    Route::post('/impersonate/stop', [ImpersonationController::class, 'stop'])->name('impersonate.stop');

});

require __DIR__.'/settings.php';
