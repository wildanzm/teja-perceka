<?php

namespace App\Livewire\ProfitLoss;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\ProfitAllocationClosing;
use App\Models\ProfitAllocationRecord;
use App\Support\BumdesCashBalance;
use App\Support\PdfExport;
use App\Support\Rupiah;
use App\Support\SafeDates;
use App\Support\VoucherNumber;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Alokasi Laba')]
class ProfitAllocation extends Component
{
    /**
     * Period identity. These stay client-writable because the period pickers bind to
     * them, so they are NOT trusted as ledger keys: periodKey() canonicalises them
     * through the same normalisation periodRange()/periodLabel() use, so a forged or
     * malformed value can never create a closeable period that disagrees with the date
     * range whose profit it consumes.
     */
    public string $mode = 'monthly';

    public string $period = '';

    public string $semester = '1';

    public string $semesterYear = '';

    // Add/Edit Row Modal
    public bool $showModal = false;

    public ?string $editingDescription = null;

    public string $formDescription = '';

    public string $formGroup = 'pengurang'; // 'pengurang' | 'ad_art'

    public string $formCalcType = 'percentage'; // 'percentage' | 'nominal'

    public string $formPercentage = '';

    public string $formAmount = '';

    public string $formFlowType = 'outflow'; // 'outflow' | 'retained'

    public ?int $formAccountId = null;

    public ?string $deleteDescription = null;

    public bool $showDeleteModal = false;

    public bool $showClosingConfirmModal = false;

    public bool $showRollbackConfirmModal = false;

    public function mount(): void
    {
        $user = Auth::user();

        // Unit heads cannot access this page via routing/middleware;
        // ensure access is denied defensively.
        if ($user->hasRole('kepala_unit')) {
            abort(403);
        }

        $now = Carbon::now();
        $this->period = $this->mode === 'monthly' ? $now->format('Y-m') : $now->format('Y');
        $this->semesterYear = $now->format('Y');
        $this->semester = $now->month <= 6 ? '1' : '2';
    }

    public function updatedMode(): void
    {
        $now = Carbon::now();
        if ($this->mode === 'monthly') {
            $this->period = $now->format('Y-m');
        } elseif ($this->mode === 'yearly') {
            $this->period = $now->format('Y');
        } elseif ($this->mode === 'semester') {
            $this->semesterYear = $now->format('Y');
            $this->semester = $now->month <= 6 ? '1' : '2';
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    public function periodRange(): array
    {
        if ($this->mode === 'yearly') {
            $year = SafeDates::year($this->period);

            return [
                Carbon::create($year, 1, 1)->startOfDay(),
                Carbon::create($year, 12, 31)->endOfDay(),
            ];
        }

        if ($this->mode === 'semester') {
            $year = SafeDates::year($this->semesterYear);
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
        }

        $date = SafeDates::month($this->period);

        return [
            $date->copy()->startOfMonth(),
            $date->copy()->endOfMonth(),
        ];
    }

    public function periodLabel(): string
    {
        if ($this->mode === 'yearly') {
            return 'Tahun '.SafeDates::year($this->period);
        }

        if ($this->mode === 'semester') {
            $semester = $this->semester === '1' ? '1' : '2';

            return 'Semester '.$semester.' Tahun '.SafeDates::year($this->semesterYear);
        }

        return SafeDates::month($this->period)->translatedFormat('F Y');
    }

    /**
     * Canonical ledger key for the selected period.
     *
     * Deliberately derives from the SAME normalisation that periodRange() and
     * periodLabel() use, rather than from the raw client-writable properties. If the
     * key were taken verbatim, a value such as '2026-1' or '2026-010' would become its
     * own closeable period while its reported date range silently collapsed to the
     * current month — allowing one month's profit to be closed under a key that
     * describes a different month.
     */
    #[Computed]
    public function periodKey(): string
    {
        if ($this->mode === 'yearly') {
            return 'yearly-'.SafeDates::year($this->period);
        }

        if ($this->mode === 'semester') {
            $year = SafeDates::year($this->semesterYear);

            // Bukan '1' => semester 2: samakan dengan periodRange().
            $semester = $this->semester === '1' ? '1' : '2';

            return 'semester-'.$semester.'-'.$year;
        }

        return SafeDates::month($this->period)->format('Y-m');
    }

    #[Computed]
    public function currentClosing(): ?ProfitAllocationClosing
    {
        return ProfitAllocationClosing::where('period_mode', $this->mode)
            ->where('period_key', $this->periodKey)
            ->first();
    }

    #[Computed]
    public function availableAccounts(): Collection
    {
        return Account::whereIn('type', ['beban', 'beban_lain'])
            ->where('is_header', false)
            ->orderBy('code')
            ->get();
    }

    // ─── Calculation Data ────────────────────────────────────────────────

    private function sumAccountBalance(string $type, string $normalDirection, array $range): float
    {
        [$start, $end] = $range;
        // Only calculate BUMDes transaction journals (vouchers DBM & KBM or business_unit_id IS NULL)
        $q = JournalEntry::query()
            ->whereBetween('transaction_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->where(function ($query) {
                $query->where('voucher_number', 'like', 'DBM%')
                    ->orWhere('voucher_number', 'like', 'KBM%')
                    ->orWhereNull('business_unit_id');
            });

        $accountIds = Account::where('type', $type)->pluck('id');
        if ($accountIds->isEmpty()) {
            return 0;
        }

        $sums = $q->whereIn('account_id', $accountIds)
            ->selectRaw($normalDirection === 'credit'
                ? 'SUM(credit) - SUM(debit) as amount'
                : 'SUM(debit) - SUM(credit) as amount')
            ->value('amount');

        return (float) $sums;
    }

    #[Computed]
    public function netIncome(): float
    {
        [$start, $end] = $this->periodRange();

        $revenue = $this->sumAccountBalance('pendapatan', 'credit', [$start, $end]) + BumdesCashBalance::getOpeningBalance($start) + BumdesCashBalance::getTotalNetUnitIncome($start, $end);
        $cogs = $this->sumAccountBalance('hpp', 'debit', [$start, $end]);
        $expenses = $this->sumAccountBalance('beban', 'debit', [$start, $end]);
        $otherRevenue = $this->sumAccountBalance('pendapatan_lain', 'credit', [$start, $end]);
        $otherExpenses = $this->sumAccountBalance('beban_lain', 'debit', [$start, $end]);

        $grossProfit = $revenue - $cogs;

        return $grossProfit - $expenses + $otherRevenue - $otherExpenses;
    }

    /**
     * Get active allocation rows for the currently selected period.
     * Falls back to the latest period or legacy templates if none configured yet.
     */
    #[Computed]
    public function allocationRows(): Collection
    {
        // 1. Explicit records for this specific period
        $periodRecords = ProfitAllocationRecord::with('account')
            ->where('period_key', $this->periodKey)
            ->orderBy('id', 'desc')
            ->get()
            ->unique('description');

        if ($periodRecords->isNotEmpty()) {
            $records = $periodRecords;
        } else {
            // 2. Fallback to latest configured period or legacy records (period_key IS NULL)
            $fallbackKey = ProfitAllocationRecord::whereNotNull('period_key')
                ->where('period_key', '!=', $this->periodKey)
                ->orderBy('id', 'desc')
                ->value('period_key');

            if ($fallbackKey) {
                $records = ProfitAllocationRecord::with('account')
                    ->where('period_key', $fallbackKey)
                    ->orderBy('id', 'desc')
                    ->get()
                    ->unique('description');
            } else {
                $records = ProfitAllocationRecord::with('account')
                    ->whereNull('period_key')
                    ->orderBy('id', 'desc')
                    ->get()
                    ->unique('description');
            }
        }

        // Exclude rows marked as deleted
        return $records->filter(function ($r) {
            $calcType = $r->calc_type ?? 'percentage';
            if ($calcType === 'nominal') {
                return (float) $r->amount > 0;
            }

            return (float) $r->percentage > 0;
        })->values();
    }

    /**
     * Deductions calculated from raw net profit.
     */
    #[Computed]
    public function deductionRows(): Collection
    {
        $netIncome = $this->netIncome;

        return $this->allocationRows
            ->where('allocation_group', 'pengurang')
            ->map(function ($row) use ($netIncome) {
                $calcType = $row->calc_type ?? 'percentage';
                $flowType = $row->flow_type ?? 'outflow';

                $amount = $calcType === 'nominal'
                    ? (float) $row->amount
                    : ((float) $row->percentage / 100 * $netIncome);

                return [
                    'id' => $row->id,
                    'description' => $row->description,
                    'calc_type' => $calcType,
                    'flow_type' => $flowType,
                    'percentage' => (float) $row->percentage,
                    'amount_setting' => (float) $row->amount,
                    'account_id' => $row->account_id,
                    'account' => $row->account,
                    'amount' => max(0, $amount),
                ];
            })->values();
    }

    #[Computed]
    public function totalDeductions(): float
    {
        return $this->deductionRows->sum('amount');
    }

    #[Computed]
    public function deductionLabel(): string
    {
        $names = $this->deductionRows->pluck('description')->all();
        $count = count($names);

        if ($count === 0) {
            return 'Pengurang';
        }

        if ($count === 1) {
            return $names[0];
        }

        if ($count === 2) {
            return $names[0].' dan '.$names[1];
        }

        return implode(', ', array_slice($names, 0, -1)).', dan '.end($names);
    }

    #[Computed]
    public function netIncomeAfterDeductions(): float
    {
        return $this->netIncome - $this->totalDeductions;
    }

    /**
     * AD/ART group rows calculated from net profit after deductions.
     */
    #[Computed]
    public function adArtRows(): Collection
    {
        $base = $this->netIncomeAfterDeductions;

        return $this->allocationRows
            ->where('allocation_group', 'ad_art')
            ->map(function ($row) use ($base) {
                $calcType = $row->calc_type ?? 'percentage';
                $flowType = $row->flow_type ?? 'retained';

                $amount = $calcType === 'nominal'
                    ? (float) $row->amount
                    : ((float) $row->percentage / 100 * $base);

                return [
                    'id' => $row->id,
                    'description' => $row->description,
                    'calc_type' => $calcType,
                    'flow_type' => $flowType,
                    'percentage' => (float) $row->percentage,
                    'amount_setting' => (float) $row->amount,
                    'account_id' => $row->account_id,
                    'account' => $row->account,
                    'amount' => max(0, $amount),
                ];
            })->values();
    }

    #[Computed]
    public function totalAdArtPercent(): float
    {
        return $this->allocationRows
            ->where('allocation_group', 'ad_art')
            ->where('calc_type', 'percentage')
            ->sum('percentage');
    }

    #[Computed]
    public function totalAdArtAmount(): float
    {
        return $this->adArtRows->sum('amount');
    }

    #[Computed]
    public function totalOutflow(): float
    {
        $deductions = $this->deductionRows->where('flow_type', 'outflow')->sum('amount');
        $adArt = $this->adArtRows->where('flow_type', 'outflow')->sum('amount');

        return $deductions + $adArt;
    }

    #[Computed]
    public function totalRetained(): float
    {
        $deductions = $this->deductionRows->where('flow_type', 'retained')->sum('amount');
        $adArt = $this->adArtRows->where('flow_type', 'retained')->sum('amount');

        return $deductions + $adArt;
    }

    #[Computed]
    public function totalAllocated(): float
    {
        return $this->totalOutflow + $this->totalRetained;
    }

    #[Computed]
    public function unallocatedProfit(): float
    {
        return $this->netIncome - $this->totalAllocated;
    }

    #[Computed]
    public function canEdit(): bool
    {
        return Auth::user()->hasAnyRole(['direktur_bumdes', 'sekretaris', 'bendahara']);
    }

    /**
     * Allocation rows are frozen once the journals for the period are applied;
     * they must be rolled back before any row can be created, edited, or deleted.
     */
    #[Computed]
    public function isLocked(): bool
    {
        return $this->currentClosing !== null;
    }

    #[Computed]
    public function showSignature(): bool
    {
        return ! Auth::user()->hasAnyRole(['kepala_desa', 'pengawas']);
    }

    // ─── Modal Actions ───────────────────────────────────────────────────

    /**
     * Ensure this specific period has its own distinct set of records
     * so changes in this period do not alter other periods.
     *
     * Only copies from a template when this period genuinely has no rows of its own,
     * and the copy runs inside a transaction so a failing caller insert cannot leave
     * an inherited snapshot committed on its own. The source row's business_unit_id is
     * preserved instead of being flattened to null, so a row never silently detaches
     * from the unit that owns it.
     */
    private function ensurePeriodRowsExist(): void
    {
        if (ProfitAllocationRecord::where('period_key', $this->periodKey)->exists()) {
            return;
        }

        $visibleRows = $this->allocationRows;

        if ($visibleRows->isEmpty()) {
            return;
        }

        $today = Carbon::now()->format('Y-m-d');

        DB::transaction(function () use ($visibleRows, $today) {
            foreach ($visibleRows as $row) {
                ProfitAllocationRecord::create([
                    'description' => $row->description,
                    'percentage' => (float) $row->percentage,
                    'calc_type' => $row->calc_type ?? 'percentage',
                    'amount' => (float) $row->amount,
                    'allocation_group' => $row->allocation_group,
                    'flow_type' => $row->flow_type ?? 'outflow',
                    'account_id' => $row->account_id,
                    'effective_from' => $today,
                    'period_mode' => $this->mode,
                    'period_key' => $this->periodKey,
                    'business_unit_id' => $row->business_unit_id,
                ]);
            }
        });
    }

    private function guardNotLocked(): bool
    {
        if ($this->isLocked) {
            $this->dispatch('swal-alert', icon: 'warning', title: 'Alokasi Terkunci', text: 'Jurnal alokasi periode ini sudah ditetapkan. Batalkan jurnal terlebih dahulu untuk mengubah pos alokasi.');

            return false;
        }

        return true;
    }

    public function openCreate(): void
    {
        if (! $this->canEdit) {
            abort(403);
        }

        if (! $this->guardNotLocked()) {
            return;
        }

        $this->reset([
            'formDescription', 'formGroup', 'formCalcType', 'formPercentage',
            'formAmount', 'formFlowType', 'formAccountId', 'editingDescription',
        ]);
        $this->formGroup = 'pengurang';
        $this->formCalcType = 'percentage';
        $this->formFlowType = 'outflow';
        $this->showModal = true;
        $this->resetErrorBag();
    }

    public function openEdit(string $description): void
    {
        if (! $this->canEdit) {
            abort(403);
        }

        if (! $this->guardNotLocked()) {
            return;
        }

        $row = $this->allocationRows->firstWhere('description', $description);
        if (! $row) {
            return;
        }

        $this->editingDescription = $row->description;
        $this->formDescription = $row->description;
        $this->formGroup = $row->allocation_group;
        $this->formCalcType = $row->calc_type ?? 'percentage';
        $this->formPercentage = (string) $row->percentage;
        $this->formAmount = (float) $row->amount > 0 ? Rupiah::format($row->amount) : '';
        $this->formFlowType = $row->flow_type ?? 'outflow';
        $this->formAccountId = $row->account_id ? (int) $row->account_id : null;
        $this->showModal = true;
        $this->resetErrorBag();
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset([
            'formDescription', 'formGroup', 'formCalcType', 'formPercentage',
            'formAmount', 'formFlowType', 'formAccountId', 'editingDescription',
        ]);
        $this->formGroup = 'pengurang';
        $this->formCalcType = 'percentage';
        $this->formFlowType = 'outflow';
        $this->resetErrorBag();
    }

    public function saveRow(): void
    {
        if (! $this->canEdit) {
            abort(403);
        }

        if (! $this->guardNotLocked()) {
            return;
        }

        $originalAmount = $this->formAmount;
        if ($this->formCalcType === 'nominal') {
            $parsedAmount = Rupiah::parse($this->formAmount);
            $this->formAmount = $parsedAmount > 0 ? (string) $parsedAmount : '';
        }

        try {
            $this->validate([
                'formDescription' => 'required|string|max:100',
                'formGroup' => 'required|in:pengurang,ad_art',
                'formCalcType' => 'required|in:percentage,nominal',
                'formPercentage' => 'exclude_unless:formCalcType,percentage|required|numeric|min:0.01|max:100',
                'formAmount' => 'exclude_unless:formCalcType,nominal|required|numeric|min:1',
                'formFlowType' => 'required|in:outflow,retained',
                'formAccountId' => 'nullable|exists:accounts,id',
            ], [
                'formPercentage.max' => 'Persentase maksimal 100%.',
                'formPercentage.min' => 'Persentase minimal 0.01%.',
                'formAmount.min' => 'Nominal harus diisi minimal Rp 1.',
            ], [
                'formDescription' => 'Keterangan pos alokasi',
                'formPercentage' => 'Persentase',
                'formAmount' => 'Nominal',
            ]);
        } catch (ValidationException $e) {
            $this->formAmount = $originalAmount;
            $this->dispatch('swal-alert', icon: 'error', title: 'Gagal menyimpan', text: (string) $e->validator->errors()->first());

            throw $e;
        }

        // Isolate records for this period, and write both the inherited snapshot and the
        // operator's own row in one transaction so a failing insert cannot leave the
        // inherited rows committed on their own.
        $today = Carbon::now()->format('Y-m-d');
        $isRename = $this->editingDescription !== null && $this->formDescription !== $this->editingDescription;
        $editingDescription = $this->editingDescription;
        $isEdit = $this->editingDescription !== null;

        DB::transaction(function () use ($today, $isRename, $editingDescription) {
            $this->ensurePeriodRowsExist();

            // Rename: retire old description within this period
            if ($isRename) {
                ProfitAllocationRecord::create([
                    'description' => $editingDescription,
                    'percentage' => 0,
                    'calc_type' => 'percentage',
                    'amount' => 0,
                    'allocation_group' => 'pengurang',
                    'flow_type' => 'outflow',
                    'account_id' => null,
                    'effective_from' => $today,
                    'period_mode' => $this->mode,
                    'period_key' => $this->periodKey,
                    'business_unit_id' => null,
                ]);
            }

            ProfitAllocationRecord::create([
                'description' => $this->formDescription,
                'percentage' => $this->formCalcType === 'percentage' ? (float) $this->formPercentage : 0,
                'calc_type' => $this->formCalcType,
                'amount' => $this->formCalcType === 'nominal' ? (float) $this->formAmount : 0,
                'allocation_group' => $this->formGroup,
                'flow_type' => $this->formFlowType,
                'account_id' => ($this->formFlowType === 'outflow' && $this->formAccountId) ? (int) $this->formAccountId : null,
                'effective_from' => $today,
                'period_mode' => $this->mode,
                'period_key' => $this->periodKey,
                'business_unit_id' => null,
            ]);
        });

        $this->closeModal();
        $this->dispatch('swal-alert', icon: 'success', title: 'Berhasil', text: $isEdit ? 'Baris alokasi berhasil diperbarui untuk periode ini.' : 'Baris alokasi berhasil ditambahkan untuk periode ini.');
    }

    public function confirmDelete(string $description): void
    {
        if (! $this->canEdit) {
            abort(403);
        }

        if (! $this->guardNotLocked()) {
            return;
        }

        // Only allow deleting a position that actually exists in the rendered rows for
        // this period. Without this, an arbitrary client string would still materialise
        // the period's inherited snapshot and then write a tombstone for a row that was
        // never shown.
        $exists = $this->allocationRows->contains(
            fn ($row) => $row->description === $description
        );

        if (! $exists) {
            $this->dispatch('swal-alert', icon: 'error', title: 'Gagal', text: 'Pos alokasi tidak ditemukan pada periode ini.');

            return;
        }

        $this->deleteDescription = $description;
        $this->showDeleteModal = true;
    }

    public function executeDelete(): void
    {
        if (! $this->canEdit || ! $this->deleteDescription) {
            abort(403);
        }

        if (! $this->guardNotLocked()) {
            return;
        }

        $description = $this->deleteDescription;
        $isAdArt = $this->allocationRows
            ->firstWhere('description', $description)?->allocation_group === 'ad_art';

        DB::transaction(function () use ($description, $isAdArt) {
            $this->ensurePeriodRowsExist();

            $today = Carbon::now()->format('Y-m-d');

            // Mark as deleted for this specific period. Keep the row's own group and
            // flow type so a tombstone for an ad_art/retained position does not
            // masquerade as a pengurang/outflow one.
            ProfitAllocationRecord::create([
                'description' => $description,
                'percentage' => 0,
                'calc_type' => 'percentage',
                'amount' => 0,
                'allocation_group' => $isAdArt ? 'ad_art' : 'pengurang',
                'flow_type' => 'outflow',
                'account_id' => null,
                'effective_from' => $today,
                'period_mode' => $this->mode,
                'period_key' => $this->periodKey,
                'business_unit_id' => null,
            ]);
        });

        $this->dispatch('swal-alert', icon: 'success', title: 'Berhasil', text: 'Baris alokasi berhasil dihapus dari periode ini.');

        $this->showDeleteModal = false;
        $this->deleteDescription = null;
    }

    // ─── Automatic Journal Posting Actions ───────────────────────────────

    public function applyAllocation(): void
    {
        if (! $this->canEdit) {
            abort(403);
        }

        if ($this->currentClosing) {
            $this->dispatch('swal-alert', icon: 'warning', title: 'Sudah Diterapkan', text: 'Alokasi laba untuk periode ini sudah pernah diterapkan ke jurnal.');

            return;
        }

        if ($this->netIncome <= 0) {
            $this->dispatch('swal-alert', icon: 'error', title: 'Laba Tidak Memenuhi', text: 'Laba bersih periode ini nihil atau minus, tidak dapat dilakukan alokasi.');

            return;
        }

        $allRows = $this->deductionRows->concat($this->adArtRows)->filter(fn ($r) => (float) $r['amount'] > 0)->values();

        if ($allRows->isEmpty()) {
            $this->dispatch('swal-alert', icon: 'warning', title: 'Alokasi Kosong', text: 'Tidak ada pos alokasi laba dengan nilai lebih dari 0.');

            return;
        }

        if ($this->totalAllocated > $this->netIncome) {
            $this->dispatch('swal-alert', icon: 'error', title: 'Alokasi Melebihi Laba', text: 'Total nilai alokasi melebihi total laba bersih yang tersedia.');

            return;
        }

        [$start, $end] = $this->periodRange();
        $closingDate = $end->format('Y-m-d');

        $cashAccount = Account::where('code', '1-1100')->first()
            ?? Account::where('type', 'aktiva')->where('is_header', false)->first();

        // Hanya alokasi penyaluran laba (outflow / kas keluar) yang dicatat ke jurnal umum & buku besar.
        // Pos cadangan modal (retained) tidak dicatat ke jurnal / tidak mengkredit kas.
        $outflowRows = $allRows->where('flow_type', 'outflow')->filter(fn ($r) => ! empty($r['account_id']))->values();

        if ($outflowRows->isEmpty()) {
            $this->dispatch('swal-alert', icon: 'warning', title: 'Tidak Ada Akun', text: 'Tidak ada pos penyaluran laba yang memiliki akun COA untuk dijurnal.');

            return;
        }

        try {
            DB::transaction(function () use (
                $outflowRows, $closingDate, $cashAccount
            ) {
                // Re-check under a row lock so the guard and the insert are atomic. The
                // unique key added in migration 2026_11_09_000000 is the final backstop;
                // this turns a would-be constraint violation into a clean message.
                $alreadyClosed = ProfitAllocationClosing::where('period_mode', $this->mode)
                    ->where('period_key', $this->periodKey)
                    ->lockForUpdate()
                    ->exists();

                if ($alreadyClosed) {
                    throw new \RuntimeException('Alokasi laba untuk periode ini sudah pernah diterapkan.');
                }

                $createdJournalIds = [];
                $periodLabel = $this->periodLabel();

                // Process Outflow (KBM vouchers - Kas keluar untuk penyaluran laba)
                $kbmBatch = VoucherNumber::nextBatch('KBM', $closingDate, $outflowRows->count());
                foreach ($outflowRows as $idx => $row) {
                    $targetAccount = Account::find($row['account_id']);
                    if (! $targetAccount) {
                        continue;
                    }

                    $voucher = $kbmBatch[$idx];
                    $amount = (float) $row['amount'];
                    $desc = "Alokasi Laba Keluar ({$periodLabel}): {$row['description']}";

                    $debitEntry = JournalEntry::create([
                        'voucher_number' => $voucher,
                        'transaction_date' => $closingDate,
                        'description' => $desc,
                        'account_id' => $targetAccount->id,
                        'debit' => $amount,
                        'credit' => 0,
                        'daily_transaction_id' => null,
                        'business_unit_id' => null,
                    ]);

                    $creditEntry = JournalEntry::create([
                        'voucher_number' => $voucher,
                        'transaction_date' => $closingDate,
                        'description' => $desc,
                        'account_id' => $cashAccount->id,
                        'debit' => 0,
                        'credit' => $amount,
                        'daily_transaction_id' => null,
                        'business_unit_id' => null,
                    ]);

                    $createdJournalIds[] = $debitEntry->id;
                    $createdJournalIds[] = $creditEntry->id;
                }

                ProfitAllocationClosing::create([
                    'period_mode' => $this->mode,
                    'period_key' => $this->periodKey,
                    'closing_date' => $closingDate,
                    'net_income' => $this->netIncome,
                    'total_outflow' => $this->totalOutflow,
                    'total_retained' => $this->totalRetained,
                    'journal_ids' => $createdJournalIds,
                    'created_by' => Auth::id(),
                ]);
            });

            $this->showClosingConfirmModal = false;
            $this->dispatch('swal-alert', icon: 'success', title: 'Berhasil Diterapkan', text: 'Jurnal otomatis alokasi laba berhasil dibuat dan disinkronkan ke buku besar.');
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('swal-alert', icon: 'error', title: 'Gagal Memproses Jurnal', text: $e->getMessage());
        }
    }

    public function rollbackAllocation(): void
    {
        if (! $this->canEdit) {
            abort(403);
        }

        $closing = $this->currentClosing;
        if (! $closing) {
            return;
        }

        try {
            DB::transaction(function () use ($closing) {
                if (! empty($closing->journal_ids)) {
                    JournalEntry::whereIn('id', $closing->journal_ids)->delete();
                }
                $closing->delete();
            });

            $this->showRollbackConfirmModal = false;
            $this->dispatch('swal-alert', icon: 'success', title: 'Jurnal Dibatalkan', text: 'Jurnal alokasi laba periode ini telah dihapus. Saldo kembali sebelum alokasi.');
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('swal-alert', icon: 'error', title: 'Gagal Membatalkan Jurnal', text: $e->getMessage());
        }
    }

    // ─── Preview PDF (inline, via ReportPreviewController) ────────────────

    /**
     * Build the allocation PDF for inline preview.
     *
     * @return array{0: \Barryvdh\DomPDF\PDF, 1: string}
     */
    public function buildReportPdf(): array
    {
        $entityName = 'BUMDESA TEJA PERCEKA';

        [$start, $end] = $this->periodRange();
        $printDate = strtoupper($end->translatedFormat('d F Y'));
        $signatureDate = PdfExport::signatureDate($end);
        $periodLabel = $this->periodLabel();

        $signatory = Auth::user()->name;
        $position = match (true) {
            Auth::user()->hasRole('direktur_bumdes') => 'Direktur',
            Auth::user()->hasRole('sekretaris') => 'Sekretaris',
            Auth::user()->hasRole('bendahara') => 'Bendahara',
            default => '',
        };

        $netIncome = $this->netIncome;
        $deductionRows = $this->deductionRows;
        $totalDeductions = $this->totalDeductions;
        $deductionLabel = $this->deductionLabel;
        $netIncomeAfterDeductions = $this->netIncomeAfterDeductions;
        $adArtRows = $this->adArtRows;
        $totalAdArtPercent = $this->totalAdArtPercent;
        $totalAdArtAmount = $this->totalAdArtAmount;
        $totalOutflow = $this->totalOutflow;
        $totalRetained = $this->totalRetained;
        $totalAllocated = $this->totalAllocated;
        $unallocatedProfit = $this->unallocatedProfit;

        set_time_limit(120);

        $pdf = Pdf::loadView('pdf.profit-allocation', array_merge(compact(
            'entityName', 'printDate', 'signatureDate', 'periodLabel', 'signatory', 'position',
            'netIncome', 'deductionRows', 'totalDeductions', 'deductionLabel', 'netIncomeAfterDeductions',
            'adArtRows', 'totalAdArtPercent', 'totalAdArtAmount',
            'totalOutflow', 'totalRetained', 'totalAllocated', 'unallocatedProfit'
        ), ['showSignature' => $this->showSignature]))->setPaper('a4', 'portrait');

        return [$pdf, PdfExport::filename('Alokasi Laba', 'BUMDes', $periodLabel)];
    }

    public function render()
    {
        return view('livewire.profit-loss.profit-allocation');
    }
}
