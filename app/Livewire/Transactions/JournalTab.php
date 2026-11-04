<?php

namespace App\Livewire\Transactions;

use App\Models\BusinessUnit;
use App\Models\DailyTransaction;
use App\Models\JournalEntry;
use App\Support\BumdesCashBalance;
use App\Support\PdfExport;
use App\Support\VoucherNumber;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Reactive;
use Livewire\Component;
use Livewire\Features\SupportPagination\WithoutUrlPagination;
use Livewire\WithPagination;

class JournalTab extends Component
{
    use WithoutUrlPagination, WithPagination;

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

    #[Reactive]
    public string $sortField = 'transaction_date';

    #[Reactive]
    public string $sortDirection = 'asc';

    /** Journal view: 'summary' (aggregated per description) or 'detailed' (per voucher). Set from the parent filter. */
    #[Reactive]
    public string $viewMode = 'detailed';

    /** Free-text filter from the parent filter card: description, voucher number, account code, or account name. */
    #[Reactive]
    public string $search = '';

    public function updating(string $name, mixed $value): void
    {
        if (in_array($name, ['unitId', 'mode', 'transactionDate', 'week', 'month', 'semester', 'semesterYear', 'year', 'sortField', 'sortDirection', 'viewMode', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function mount(): void
    {
        // Filter change remounts this tab via wire:key, but the page number
        // lingers in the query string (?page=3) — always start from page 1.
        $this->resetPage();
    }

    /**
     * Check whether current view is within BUMDes scope.
     */
    public function isBumdesScope(): bool
    {
        return ! Auth::user()?->hasRole('kepala_unit') && ($this->unitId === 'bumdes' || empty($this->unitId));
    }

    /**
     * Apply query scope based on user role and selected unit:
     * - kepala_unit: restricted to their assigned unit
     * - non-kepala-unit:
     *   - 'bumdes' or default: BUMDes expenses (KBM) and direct BUMDes journals (DBM)
     *   - 'all': all business unit journals (where business_unit_id is not null)
     *   - numeric ID: specific business unit journal
     */
    private function applyRoleScope($query)
    {
        if (Auth::user()?->hasRole('kepala_unit')) {
            $query->where('business_unit_id', Auth::user()->business_unit_id);

            return $query;
        }

        if ($this->unitId === 'all') {
            $query->whereNotNull('business_unit_id');
        } elseif ($this->unitId && is_numeric($this->unitId)) {
            $query->where('business_unit_id', (int) $this->unitId);
        } else {
            // 'bumdes' or default: BUMDes expenses and direct BUMDes journals
            $query->where(function ($q) {
                $q->where('voucher_number', 'like', 'KBM%')
                    ->orWhere(function ($sub) {
                        $sub->where('voucher_number', 'like', 'DBM%')
                            ->whereNull('daily_transaction_id')
                            ->whereNull('business_unit_id');
                    });
            });
        }

        return $query;
    }

    /**
     * Normalized, length-capped search term shared by SQL and in-memory filters.
     */
    private function searchTerm(): string
    {
        return mb_substr(trim($this->search), 0, 100);
    }

    /**
     * Apply free-text search on description, voucher number, and account.
     */
    private function applySearch($query)
    {
        $term = $this->searchTerm();
        if ($term === '') {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function ($q) use ($like) {
            $q->where('journal_entries.description', 'like', $like)
                ->orWhere('journal_entries.voucher_number', 'like', $like)
                ->orWhereHas('account', function ($accountQuery) use ($like) {
                    $accountQuery->where('code', 'like', $like)
                        ->orWhere('name', 'like', $like);
                });
        });
    }

    /**
     * Filter in-memory journals (including BUMDes virtual entries) by the same term.
     */
    private function filterSearchCollection(Collection $journals): Collection
    {
        $term = mb_strtolower($this->searchTerm());
        if ($term === '') {
            return $journals;
        }

        return $journals->filter(function ($journal) use ($term) {
            $haystack = mb_strtolower(implode(' ', array_map(
                fn ($value) => (string) $value,
                [$journal->voucher_number, $journal->description, $journal->account?->code, $journal->account?->name]
            )));

            return str_contains($haystack, $term);
        })->values();
    }

    // ── Delete state ──────────────────────────────────────────────────────────
    public ?int $deleteId = null;

    public bool $showDeleteModal = false;

    #[Computed]
    public function dateRange(): array
    {
        switch ($this->mode) {
            case 'daily':
                $date = Carbon::parse($this->transactionDate ?: Carbon::today()->format('Y-m-d'));

                return [$date->copy()->startOfDay(), $date->copy()->endOfDay()];

            case 'weekly':
                $start = Carbon::parse($this->week ?: Carbon::now()->startOfWeek()->format('Y-m-d'));

                return [$start->copy()->startOfWeek(), $start->copy()->endOfWeek()];

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

                return [Carbon::create($year, 1, 1)->startOfDay(), Carbon::create($year, 12, 31)->endOfDay()];
        }

        $today = Carbon::today();

        return [$today->copy()->startOfDay(), $today->copy()->endOfDay()];
    }

    #[Computed]
    public function periodLabel(): string
    {
        [$start, $end] = $this->dateRange;

        return match ($this->mode) {
            'daily' => $start->translatedFormat('d F Y'),
            'weekly' => $start->month === $end->month
                ? $start->format('d').' - '.$end->translatedFormat('d F Y')
                : $start->translatedFormat('d M').' - '.$end->translatedFormat('d M Y'),
            'monthly' => $start->translatedFormat('F Y'),
            'semester' => 'Semester '.$this->semester.' Tahun '.($this->semesterYear ?: Carbon::now()->format('Y')),
            'yearly' => $start->format('Y'),
            default => '-',
        };
    }

    /**
     * Voucher group key: number + month + unit. Voucher numbers reset every
     * month per unit, so the number alone is not unique across months
     * (groups would merge in semester/yearly filters).
     */
    private function voucherGroupKey($journal): string
    {
        return $journal->voucher_number.'|'.Carbon::parse($journal->transaction_date)->format('Y-m').'|'.($journal->business_unit_id ?? 'null');
    }

    #[Computed]
    public function transactions()
    {
        [$start, $end] = $this->dateRange;

        $sortField = in_array($this->sortField, ['transaction_date', 'voucher_number']) ? $this->sortField : 'transaction_date';
        $sortDirection = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        $query = JournalEntry::with(['businessUnit', 'account'])
            ->whereDate('transaction_date', '>=', $start->format('Y-m-d'))
            ->whereDate('transaction_date', '<=', $end->format('Y-m-d'))
            ->orderBy($sortField, $sortDirection)
            ->orderBy($sortField === 'transaction_date' ? 'voucher_number' : 'transaction_date', $sortDirection)
            ->orderBy('id', $sortDirection);

        $this->applyRoleScope($query);
        $this->applySearch($query);

        if ($this->isBumdesScope()) {
            $dbTransactions = $query->get();
            $allTransactions = BumdesCashBalance::attachToTransactions(
                $dbTransactions,
                $start,
                $end,
                $this->mode,
                $sortField,
                $sortDirection
            );
            $allTransactions = $this->filterSearchCollection($allTransactions);

            $page = LengthAwarePaginator::resolveCurrentPage();
            $perPage = 40;
            $items = $allTransactions->slice(($page - 1) * $perPage, $perPage)->values();
            $paginated = new LengthAwarePaginator(
                $items,
                $allTransactions->count(),
                $perPage,
                $page,
                ['path' => LengthAwarePaginator::resolveCurrentPath()]
            );

            $groups = $items->groupBy(fn ($journal) => $this->voucherGroupKey($journal));

            return [
                'paginator' => $paginated,
                'groups' => $groups,
            ];
        }

        $paginated = (clone $query)
            ->paginate(40);

        $groups = $paginated->getCollection()->groupBy(fn ($journal) => $this->voucherGroupKey($journal));

        // We need to return an object that contains both the grouped transactions and the paginator
        return [
            'paginator' => $paginated,
            'groups' => $groups,
        ];
    }

    /**
     * Excel-style summary view: DB aggregation per (description + account + unit)
     * within the period. Stored rows stay detailed, saved numbers untouched.
     * Display date = period end date for every row.
     * Rows sharing (description + first voucher + unit) merge into one group
     * (rowspan like the detailed view), groups ordered by voucher number.
     *
     * @return array{paginator: LengthAwarePaginator, groups: Collection, displayDate: string}
     */
    #[Computed]
    public function summaryRows(): array
    {
        ['groups' => $groups, 'displayDate' => $displayDate] = $this->buildSummaryGroups();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 20;
        $items = $groups->slice(($page - 1) * $perPage, $perPage)->values();
        $paginated = new LengthAwarePaginator(
            $items,
            $groups->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()]
        );

        return [
            'paginator' => $paginated,
            'groups' => $items,
            'displayDate' => $displayDate,
        ];
    }

    /**
     * Full summary groups without pagination (one screen page + PDF).
     *
     * @return array{groups: Collection, displayDate: string}
     */
    private function buildSummaryGroups(): array
    {
        [$start, $end] = $this->dateRange;

        $key = DB::raw('TRIM(journal_entries.description)');

        $query = JournalEntry::query()
            ->leftJoin('accounts', 'accounts.id', '=', 'journal_entries.account_id')
            ->leftJoin('business_units', 'business_units.id', '=', 'journal_entries.business_unit_id')
            ->selectRaw('MIN(journal_entries.id) as id, TRIM(journal_entries.description) as description, journal_entries.account_id, journal_entries.business_unit_id, accounts.code as code, accounts.name as accountName, business_units.name as unitName, SUM(journal_entries.debit) as totalDebit, SUM(journal_entries.credit) as totalCredit, COUNT(*) as rowCount, COUNT(DISTINCT journal_entries.voucher_number) as voucherCount, MIN(journal_entries.voucher_number) as firstVoucher, MAX(journal_entries.voucher_number) as lastVoucher, MIN(journal_entries.transaction_date) as minDate')
            ->whereBetween('journal_entries.transaction_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->groupBy($key, 'journal_entries.account_id', 'journal_entries.business_unit_id', 'accounts.code', 'accounts.name', 'business_units.name', DB::raw('SUBSTR(journal_entries.transaction_date, 1, 7)'));

        $this->applyRoleScope($query);
        $this->applySearch($query);

        $rows = $query->get();

        // Voucher numbers reset every month: collapse monthly rows so the
        // voucher count spans months (per-month DISTINCT counts summed).
        $rows = $rows
            ->groupBy(fn ($row) => mb_strtolower(trim((string) $row->description)).'|'.$row->account_id.'|'.($row->business_unit_id ?? 'null'))
            ->map(function ($monthRows) {
                $first = $monthRows->first();
                $first->totalDebit = (float) $monthRows->sum('totalDebit');
                $first->totalCredit = (float) $monthRows->sum('totalCredit');
                $first->rowCount = (int) $monthRows->sum('rowCount');
                $first->voucherCount = (int) $monthRows->sum('voucherCount');
                $first->firstVoucher = $monthRows->min('firstVoucher');
                $first->lastVoucher = $monthRows->max('lastVoucher');
                $first->minDate = $monthRows->min('minDate');

                return $first;
            })
            ->values();

        if ($this->isBumdesScope()) {
            $rows = $this->mergeVirtualSummary($rows, $start, $end);
        }

        $sortField = in_array($this->sortField, ['transaction_date', 'voucher_number']) ? $this->sortField : 'transaction_date';
        $sortDirection = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        $groups = $rows
            ->groupBy(fn ($row) => mb_strtolower(trim((string) $row->description)).'|'.($row->firstVoucher ?? '').'|'.($row->business_unit_id ?? 'null'))
            ->map(function ($group) use ($end) {
                // If all rows share the same firstVoucher and lastVoucher,
                // it's a single voucher (debit + credit rows inflate voucherCount).
                $singleVoucher = $group->max('lastVoucher') === $group->min('firstVoucher');
                $groupDate = $singleVoucher
                    ? $group->min('minDate')
                    : $end->format('Y-m-d');

                return [
                    'rows' => $group->sortByDesc(fn ($row) => (float) $row->totalDebit > 0)->values(),
                    'displayDate' => $groupDate,
                ];
            })
            ->sortBy(function ($item) {
                $first = $item['rows']->first();

                return ($first->firstVoucher ?? '').'|'.mb_strtolower(trim((string) $first->description));
            })
            ->values();

        // Assign sequential voucher numbers per prefix and business unit so summary entries are consecutive.
        // Filtered results keep their real numbers: renumbering a subset would restart at 001 and no longer match the ledger.
        if ($this->searchTerm() === '') {
            $counters = [];
            foreach ($groups as $group) {
                $first = $group['rows']->first();
                $origVoucher = (string) ($first->firstVoucher ?? '');
                $prefix = VoucherNumber::prefixOf($origVoucher);
                if (! $prefix) {
                    continue;
                }

                $scopeKey = ($first->business_unit_id ?? 'bumdes').'|'.$prefix;
                $counters[$scopeKey] = ($counters[$scopeKey] ?? 0) + 1;
                $newVoucher = $prefix.str_pad((string) $counters[$scopeKey], 3, '0', STR_PAD_LEFT);

                foreach ($group['rows'] as $row) {
                    $row->firstVoucher = $newVoucher;
                }
            }
        }

        $groups = $groups
            ->sortBy(function ($item) use ($sortField) {
                $key = match ($sortField) {
                    'voucher_number' => ($item['rows']->first()->firstVoucher ?? '').'|'.mb_strtolower(trim((string) $item['rows']->first()->description)),
                    default => ($item['displayDate'] ?? '').'|'.($item['rows']->first()->firstVoucher ?? '').'|'.mb_strtolower(trim((string) $item['rows']->first()->description)),
                };

                return $key;
            }, SORT_STRING, $sortDirection === 'desc')
            ->values();

        return [
            'groups' => $groups,
            'displayDate' => $end->format('Y-m-d'),
        ];
    }

    /**
     * Merge BUMDes virtual entries (opening balance + unit revenue) into summary rows.
     *
     * @param  Collection<int, object>  $rows
     * @return Collection<int, object>
     */
    private function mergeVirtualSummary($rows, Carbon $start, Carbon $end)
    {
        $virtuals = $this->filterSearchCollection(
            BumdesCashBalance::getBumdesVirtualEntries($start, $end, $this->mode)
        );
        if ($virtuals->isEmpty()) {
            return $rows->values();
        }

        $grouped = [];
        foreach ($virtuals as $entry) {
            $description = trim((string) $entry->description);
            $mapKey = mb_strtolower($description).'|'.$entry->account_id.'|'.($entry->business_unit_id ?? 'null');
            if (! isset($grouped[$mapKey])) {
                $grouped[$mapKey] = (object) [
                    'id' => 0,
                    'description' => $description,
                    'account_id' => $entry->account_id,
                    'business_unit_id' => $entry->business_unit_id,
                    'code' => $entry->account?->code,
                    'accountName' => $entry->account?->name,
                    'unitName' => $entry->businessUnit?->name,
                    'totalDebit' => 0.0,
                    'totalCredit' => 0.0,
                    'rowCount' => 0,
                    'voucherCount' => 0,
                    'firstVoucher' => $entry->voucher_number,
                    'lastVoucher' => $entry->voucher_number,
                ];
            }
            $row = $grouped[$mapKey];
            $row->totalDebit += (float) $entry->debit;
            $row->totalCredit += (float) $entry->credit;
            $row->rowCount++;
            $row->firstVoucher = min($row->firstVoucher, $entry->voucher_number);
            $row->lastVoucher = max($row->lastVoucher, $entry->voucher_number);
        }

        $merged = $rows->all();
        foreach ($grouped as $mapKey => $virtual) {
            $found = false;
            foreach ($merged as $row) {
                if (mb_strtolower(trim((string) $row->description)).'|'.$row->account_id.'|'.($row->business_unit_id ?? 'null') === $mapKey) {
                    $row->totalDebit += $virtual->totalDebit;
                    $row->totalCredit += $virtual->totalCredit;
                    $row->rowCount += $virtual->rowCount;
                    $row->voucherCount += 1;
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                $virtual->voucherCount = 1;
                $merged[] = $virtual;
            }
        }

        return collect($merged)->sort(function ($a, $b) {
            $cmp = strcmp((string) ($a->firstVoucher ?? ''), (string) ($b->firstVoucher ?? ''));
            if ($cmp !== 0) {
                return $this->sortDirection === 'desc' ? -$cmp : $cmp;
            }
            $cmp = strcmp(mb_strtolower((string) $a->description), mb_strtolower((string) $b->description));

            return $this->sortDirection === 'desc' ? -$cmp : $cmp;
        })->values();
    }

    #[Computed]
    public function totalDebit(): float
    {
        [$start, $end] = $this->dateRange;
        $query = JournalEntry::whereDate('transaction_date', '>=', $start->format('Y-m-d'))
            ->whereDate('transaction_date', '<=', $end->format('Y-m-d'));
        $this->applyRoleScope($query);
        $this->applySearch($query);

        $total = (float) $query->sum('debit');
        if ($this->isBumdesScope()) {
            $total += (float) $this->filterSearchCollection(
                BumdesCashBalance::getBumdesVirtualEntries($start, $end, $this->mode)
            )->sum('debit');
        }

        return $total;
    }

    #[Computed]
    public function totalCredit(): float
    {
        [$start, $end] = $this->dateRange;
        $query = JournalEntry::whereDate('transaction_date', '>=', $start->format('Y-m-d'))
            ->whereDate('transaction_date', '<=', $end->format('Y-m-d'));
        $this->applyRoleScope($query);
        $this->applySearch($query);

        $total = (float) $query->sum('credit');
        if ($this->isBumdesScope()) {
            $total += (float) $this->filterSearchCollection(
                BumdesCashBalance::getBumdesVirtualEntries($start, $end, $this->mode)
            )->sum('credit');
        }

        return $total;
    }

    #[Computed]
    public function canExportPdf(): bool
    {
        return in_array($this->mode, ['monthly', 'semester', 'yearly']);
    }

    /**
     * Authorized to delete: sekretaris, bendahara, direktur_bumdes, kepala_unit (own unit only).
     * Disallowed from deleting: kepala_desa, pengawas.
     */
    #[Computed]
    public function canDelete(): bool
    {
        return Auth::user()->hasAnyRole(['sekretaris', 'bendahara', 'direktur_bumdes', 'kepala_unit']);
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    public function confirmDelete(int $id): void
    {
        $this->deleteId = $id;
        $this->showDeleteModal = true;
    }

    public function executeDelete(): void
    {
        if (! $this->canDelete || ! $this->deleteId) {
            abort(403);
        }

        $journal = JournalEntry::find($this->deleteId);
        if (! $journal) {
            return;
        }

        // Unit heads: only delete own unit's journals
        if (Auth::user()->hasRole('kepala_unit')) {
            if ($journal->business_unit_id !== Auth::user()->business_unit_id) {
                abort(403);
            }
        }

        DB::transaction(function () use ($journal) {
            if ($journal->daily_transaction_id) {
                DailyTransaction::where('id', $journal->daily_transaction_id)->delete();
            }
            // Same voucher scope: number + month + unit (numbers reset monthly).
            $monthStart = $journal->transaction_date->copy()->startOfMonth()->format('Y-m-d');
            $monthEnd = $journal->transaction_date->copy()->endOfMonth()->format('Y-m-d');
            JournalEntry::where('voucher_number', $journal->voucher_number)
                ->whereBetween('transaction_date', [$monthStart, $monthEnd])
                ->when($journal->business_unit_id !== null,
                    fn ($query) => $query->where('business_unit_id', $journal->business_unit_id),
                    fn ($query) => $query->whereNull('business_unit_id'))
                ->delete();

            // Keep the remaining numbers gapless (001, 002, ...).
            $prefix = VoucherNumber::prefixOf($journal->voucher_number);
            if ($prefix !== null) {
                VoucherNumber::renumberScope(
                    $prefix,
                    $journal->transaction_date->format('Y-m'),
                    $journal->business_unit_id
                );
            }
        });

        $this->dispatch('swal-alert', icon: 'success', title: 'Berhasil', text: 'Satu set jurnal (debet & kredit) berhasil dihapus.');

        $this->showDeleteModal = false;
        $this->deleteId = null;
    }

    /**
     * Build the journal PDF for inline preview.
     *
     * @return array{0: \Barryvdh\DomPDF\PDF, 1: string}
     */
    public function buildReportPdf(): array
    {
        $unit = ($this->unitId && is_numeric($this->unitId)) ? BusinessUnit::find($this->unitId) : null;
        $period = $this->periodLabel;
        $unitLabel = $unit ? $unit->name : ($this->isBumdesScope() ? 'BUMDes' : 'Semua Unit');

        if ($this->viewMode === 'summary') {
            ['groups' => $groups, 'displayDate' => $displayDate] = $this->buildSummaryGroups();
            $totalDebit = (float) $groups->flatten()->sum('totalDebit');
            $totalCredit = (float) $groups->flatten()->sum('totalCredit');

            $pdf = Pdf::loadView('pdf.transaction-history-summary', compact(
                'groups',
                'displayDate',
                'period',
                'unit',
                'totalDebit',
                'totalCredit'
            ))->setPaper('a4', 'landscape');

            return [$pdf, PdfExport::filename('Jurnal Umum', $unitLabel, 'Ringkas', $period)];
        }

        [$start, $end] = $this->dateRange;

        $sortField = in_array($this->sortField, ['transaction_date', 'voucher_number']) ? $this->sortField : 'transaction_date';
        $sortDirection = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        $query = JournalEntry::with(['businessUnit', 'account'])
            ->whereDate('transaction_date', '>=', $start->format('Y-m-d'))
            ->whereDate('transaction_date', '<=', $end->format('Y-m-d'))
            ->orderBy($sortField, $sortDirection)
            ->orderBy($sortField === 'transaction_date' ? 'voucher_number' : 'transaction_date', $sortDirection)
            ->orderBy('id', $sortDirection);

        $this->applyRoleScope($query);

        $transactions = $query->get();
        if ($this->isBumdesScope()) {
            $transactions = BumdesCashBalance::attachToTransactions(
                $transactions,
                $start,
                $end,
                $this->mode,
                $sortField,
                $sortDirection
            );
        }
        $totalDebit = $transactions->sum('debit');
        $totalCredit = $transactions->sum('credit');

        $pdf = Pdf::loadView('pdf.transaction-history', compact(
            'transactions',
            'period',
            'unit',
            'totalDebit',
            'totalCredit'
        ))->setPaper('a4', 'landscape');

        return [$pdf, PdfExport::filename('Jurnal Umum', $unitLabel, 'Rinci', $period)];
    }

    public function render()
    {
        $user = Auth::user();
        if ($user && $user->hasRole('kepala_unit') && (int) $this->unitId !== (int) $user->business_unit_id) {
            $this->unitId = $user->business_unit_id;
        }

        $this->mode = in_array($this->mode, ['daily', 'weekly', 'monthly', 'semester', 'yearly'], true) ? $this->mode : 'daily';

        return view('livewire.transactions.journal-tab');
    }
}
