<?php

namespace App\Livewire\Transactions;

use App\Models\BusinessUnit;
use App\Models\DailyTransaction;
use App\Models\JournalEntry;
use App\Models\TransactionItem;
use App\Support\BumdesCashBalance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class RevenueTab extends Component
{
    #[Reactive]
    public $unitId = null;

    #[Reactive]
    public string $mode = 'daily';

    #[Reactive]
    public string $transactionDate = '';

    #[Reactive]
    public string $week = '';

    #[Reactive]
    public string $month = '';

    #[Reactive]
    public string $semester = '1';

    #[Reactive]
    public string $semesterYear = '';

    #[Reactive]
    public string $year = '';

    private function isWeeklyUnit(?BusinessUnit $unit): bool
    {
        return $unit && $unit->input_frequency === 'weekly';
    }

    private function periodRange(?BusinessUnit $unit): ?array
    {
        $isWeekly = $this->isWeeklyUnit($unit);

        switch ($this->mode) {
            case 'daily':
                $date = Carbon::parse($this->transactionDate ?: Carbon::today()->format('Y-m-d'));

                return [$date->copy()->startOfDay(), $date->copy()->endOfDay()];

            case 'weekly':
                $weekStart = Carbon::parse($this->week ?: Carbon::now()->startOfWeek()->format('Y-m-d'));
                $weekEnd = $weekStart->copy()->endOfWeek();

                return [$weekStart, $weekEnd];

            case 'monthly':
                $date = Carbon::parse(($this->month ?: Carbon::now()->format('Y-m')).'-01');

                return [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()];

            case 'semester':
                $year = (int) ($this->semesterYear ?: Carbon::now()->format('Y'));
                if ($this->semester === '1') {
                    return [
                        Carbon::create($year, 1, 1)->startOfDay(),
                        Carbon::create($year, 6, 30)->endOfDay(),
                    ];
                } else {
                    return [
                        Carbon::create($year, 7, 1)->startOfDay(),
                        Carbon::create($year, 12, 31)->endOfDay(),
                    ];
                }

            case 'yearly':
                $year = (int) ($this->year ?: Carbon::now()->format('Y'));

                return [
                    Carbon::create($year, 1, 1)->startOfDay(),
                    Carbon::create($year, 12, 31)->endOfDay(),
                ];

            default:
                return null;
        }
    }

    private function buildDailyTransactionQuery(?BusinessUnit $unit, ?array $range)
    {
        if ($range === null) {
            return DailyTransaction::query()->whereRaw('1 = 0');
        }

        [$start, $end] = $range;
        $q = DailyTransaction::query();

        if ($this->unitId && is_numeric($this->unitId)) {
            $q->where('business_unit_id', (int) $this->unitId);
        }

        $q->whereBetween('transaction_date', [$start->format('Y-m-d'), $end->format('Y-m-d')]);

        return $q;
    }

    #[Computed]
    public function selectedUnit(): ?BusinessUnit
    {
        return ($this->unitId && is_numeric($this->unitId)) ? BusinessUnit::find($this->unitId) : null;
    }

    #[Computed]
    public function isKepalaUnit(): bool
    {
        return Auth::user()->hasRole('kepala_unit');
    }

    public function isBumdesScope(): bool
    {
        return ! Auth::user()?->hasRole('kepala_unit') && ($this->unitId === 'bumdes' || empty($this->unitId));
    }

    #[Computed]
    public function periodLabel(): string
    {
        switch ($this->mode) {
            case 'daily':
                $date = Carbon::parse($this->transactionDate ?: Carbon::today()->format('Y-m-d'));

                return $date->translatedFormat('d F Y');

            case 'weekly':
                $weekStart = Carbon::parse($this->week ?: Carbon::now()->startOfWeek()->format('Y-m-d'));
                $weekEnd = $weekStart->copy()->endOfWeek();

                return $weekStart->translatedFormat('d F Y').' – '.$weekEnd->translatedFormat('d F Y');

            case 'monthly':
                $date = Carbon::parse(($this->month ?: Carbon::now()->format('Y-m')).'-01');

                return $date->translatedFormat('F Y');

            case 'semester':
                $year = $this->semesterYear ?: Carbon::now()->format('Y');

                return 'Semester '.$this->semester.' Tahun '.$year;

            case 'yearly':
                return 'Tahun '.($this->year ?: Carbon::now()->format('Y'));

            default:
                return '-';
        }
    }

    /**
     * Income/expense/net totals read from the same source as Laba Rugi:
     * journal entries grouped by COA account type over the same period and
     * unit scope. Per-category rows below still come from transaction items
     * (they have no journal equivalent), but the headline figures always
     * match the profit/loss report.
     *
     * BUMDes scope: revenue = saldo sebelumnya + net income per unit,
     * expenses = total KBM entries.
     *
     * @return array{0: float, 1: float}
     */
    private function journalTotals(array $range): array
    {
        [$start, $end] = $range;

        if ($this->isBumdesScope()) {
            $directRevenue = (float) JournalEntry::where('voucher_number', 'like', 'DBM%')
                ->whereNull('business_unit_id')
                ->whereNull('daily_transaction_id')
                ->whereDate('transaction_date', '>=', $start->format('Y-m-d'))
                ->whereDate('transaction_date', '<=', $end->format('Y-m-d'))
                ->whereHas('account', fn ($q) => $q->whereIn('type', ['pendapatan', 'pendapatan_lain']))
                ->sum(DB::raw('credit - debit'));

            $revenue = BumdesCashBalance::getTotalNetUnitIncome($start, $end) + $directRevenue;

            $expenses = (float) JournalEntry::where('voucher_number', 'like', 'KBM%')
                ->whereDate('transaction_date', '>=', $start->format('Y-m-d'))
                ->whereDate('transaction_date', '<=', $end->format('Y-m-d'))
                ->whereHas('account', fn ($q) => $q->whereIn('type', ['hpp', 'beban', 'beban_lain']))
                ->sum(DB::raw('debit - credit'));

            return [$revenue, $expenses];
        }

        $sums = JournalEntry::query()
            ->join('accounts', 'accounts.id', '=', 'journal_entries.account_id')
            ->whereIn('accounts.type', ['pendapatan', 'pendapatan_lain', 'hpp', 'beban', 'beban_lain'])
            ->whereBetween('journal_entries.transaction_date', [$start->format('Y-m-d'), $end->format('Y-m-d')]);

        if ($this->unitId === 'all') {
            $sums->whereNotNull('journal_entries.business_unit_id');
        } elseif ($this->unitId && is_numeric($this->unitId)) {
            $sums->where('journal_entries.business_unit_id', (int) $this->unitId);
        }

        $sums = $sums->selectRaw('accounts.type as type, SUM(journal_entries.debit) as d, SUM(journal_entries.credit) as c')
            ->groupBy('accounts.type')
            ->get();

        $netOf = fn (string $type, string $direction): float => (float) $sums
            ->where('type', $type)
            ->sum(fn ($row) => $direction === 'credit' ? $row->c - $row->d : $row->d - $row->c);

        $revenue = $netOf('pendapatan', 'credit') + $netOf('pendapatan_lain', 'credit');
        $expenses = $netOf('hpp', 'debit') + $netOf('beban', 'debit') + $netOf('beban_lain', 'debit');

        return [$revenue, $expenses];
    }

    #[Computed]
    public function reportData(): array
    {
        $unit = ($this->unitId && is_numeric($this->unitId)) ? BusinessUnit::find($this->unitId) : null;
        $range = $this->periodRange($unit);
        $unitName = $unit ? $unit->name : ($this->unitId === 'bumdes' ? 'BUMDes' : 'Semua Unit Usaha');

        if ($range === null) {
            return [
                'unit' => $unitName,
                'categoryRows' => collect([]),
                'totalRevenue' => 0,
                'unitTotalExpense' => 0,
                'netIncome' => 0,
                'isEmpty' => true,
                'emptyMessage' => 'Mode Harian tidak tersedia untuk unit TPS karena data diinput per minggu. Silakan pilih mode Mingguan atau Bulanan.',
            ];
        }

        // BUMDes scope: headline from virtual entries + KBM, no category rows
        if ($this->isBumdesScope()) {
            [$journalRevenue, $journalExpenses] = $this->journalTotals($range);

            return [
                'unit' => $unitName,
                'categoryRows' => collect([]),
                'totalRevenue' => $journalRevenue,
                'unitTotalExpense' => $journalExpenses,
                'netIncome' => $journalRevenue - $journalExpenses,
                'isEmpty' => false,
                'emptyMessage' => '',
            ];
        }

        $transactionQuery = $this->buildDailyTransactionQuery($unit, $range);
        $transactionIds = $transactionQuery->pluck('id');

        if ($transactionIds->isEmpty()) {
            return [
                'unit' => $unitName,
                'categoryRows' => collect([]),
                'totalRevenue' => 0,
                'unitTotalExpense' => 0,
                'netIncome' => 0,
                'isEmpty' => true,
                'emptyMessage' => 'Tidak ada data pemasukan pada periode ini.',
            ];
        }

        $aggregatedRows = TransactionItem::whereIn('daily_transaction_id', $transactionIds)
            ->select(
                'transaction_category_id',
                DB::raw('SUM(quantity) as totalQuantity'),
                DB::raw('MAX(unit_price) as unit_price'),
                DB::raw('SUM(subtotal) as totalSubtotal'),
                DB::raw('MIN(daily_transaction_id) as daily_transaction_id')
            )
            ->groupBy('transaction_category_id')
            ->with('transactionCategory.businessUnit')
            ->get();

        $categoryRows = $aggregatedRows->map(function ($row) {
            $category = $row->transactionCategory;

            return [
                'categoryId' => $category->id,
                'category' => $category->name,
                'unitName' => $category->businessUnit?->name ?? '-',
                'type' => $category->type->value,
                'unit_price' => $category->type->usesQuantity() ? (float) $row->unit_price : null,
                'totalQuantity' => $category->type->usesQuantity() ? (int) $row->totalQuantity : null,
                'subtotal' => (float) $row->totalSubtotal,
                'daily_transaction_id' => (int) $row->daily_transaction_id,
            ];
        })->sortBy('category')->values();

        $totalRevenue = $categoryRows->sum('subtotal');
        $dailyTransactionId = $this->mode === 'daily' ? $categoryRows->first()['daily_transaction_id'] ?? null : null;

        // Headline figures from journals (same as Laba Rugi), not from items/headers.
        [$journalRevenue, $journalExpenses] = $this->journalTotals($range);

        return [
            'unit' => $unitName,
            'categoryRows' => $categoryRows,
            'totalRevenue' => $journalRevenue,
            'unitTotalExpense' => $journalExpenses,
            'netIncome' => $journalRevenue - $journalExpenses,
            'isEmpty' => $categoryRows->isEmpty(),
            'emptyMessage' => 'Tidak ada data pemasukan pada periode ini.',
            'dailyTransactionId' => $dailyTransactionId,
        ];
    }

    public function render()
    {
        $user = Auth::user();
        if ($user && $user->hasRole('kepala_unit') && (int) $this->unitId !== (int) $user->business_unit_id) {
            $this->unitId = $user->business_unit_id;
        }

        $this->mode = in_array($this->mode, ['daily', 'weekly', 'monthly', 'semester', 'yearly'], true) ? $this->mode : 'daily';

        return view('livewire.transactions.revenue-tab');
    }
}
