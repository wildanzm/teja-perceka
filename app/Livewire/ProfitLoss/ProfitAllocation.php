<?php

namespace App\Livewire\ProfitLoss;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\ProfitAllocationRecord;
use App\Support\BumdesCashBalance;
use App\Support\PdfExport;
use App\Support\SafeDates;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Alokasi Laba')]
class ProfitAllocation extends Component
{
    /** 'monthly' | 'semester' | 'yearly' */
    public string $mode = 'monthly';

    public string $period = '';

    public string $semester = '1';

    public string $semesterYear = '';

    // Add/Edit Row Modal
    public bool $showModal = false;

    public ?string $editingDescription = null;

    public string $formDescription = '';

    public string $formGroup = 'pengurang'; // 'pengurang' | 'ad_art'

    public string $formPercentage = '';

    public ?string $deleteDescription = null;

    public bool $showDeleteModal = false;

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

    private function periodRange(): array
    {
        if ($this->mode === 'yearly') {
            $year = (int) ($this->period ?: Carbon::now()->format('Y'));

            return [
                Carbon::create($year, 1, 1)->startOfDay(),
                Carbon::create($year, 12, 31)->endOfDay(),
            ];
        }

        if ($this->mode === 'semester') {
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
        }

        $date = SafeDates::month($this->period);

        return [
            $date->copy()->startOfMonth(),
            $date->copy()->endOfMonth(),
        ];
    }

    private function periodLabel(): string
    {
        if ($this->mode === 'yearly') {
            return 'Tahun '.($this->period ?: Carbon::now()->format('Y'));
        }

        if ($this->mode === 'semester') {
            $year = $this->semesterYear ?: Carbon::now()->format('Y');

            return 'Semester '.$this->semester.' Tahun '.$year;
        }

        return SafeDates::month($this->period)->translatedFormat('F Y');
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
     * Get all active allocation rows (latest per description, global for all periods).
     */
    #[Computed]
    public function allocationRows(): Collection
    {
        $latestRecords = ProfitAllocationRecord::orderBy('effective_from', 'desc')
            ->orderBy('id', 'desc')
            ->get()
            ->unique('description');

        // Exclude rows with 0 percentage (marked as deleted)
        return $latestRecords->filter(fn ($r) => (float) $r->percentage > 0)->values();
    }

    /**
     * Deduction group rows — calculated from raw net profit.
     */
    #[Computed]
    public function deductionRows(): Collection
    {
        $netIncome = $this->netIncome;

        return $this->allocationRows
            ->where('allocation_group', 'pengurang')
            ->map(function ($row) use ($netIncome) {
                $amount = (float) $row->percentage / 100 * $netIncome;

                return [
                    'description' => $row->description,
                    'percentage' => (float) $row->percentage,
                    'amount' => $amount,
                ];
            })->values();
    }

    /**
     * Total amount of all deduction rows.
     */
    #[Computed]
    public function totalDeductions(): float
    {
        return $this->deductionRows->sum('amount');
    }

    /**
     * Names of all deduction rows joined for display, e.g. "A", "A dan B", "A, B, dan C".
     */
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

    /**
     * Net profit after subtracting all deductions.
     */
    #[Computed]
    public function netIncomeAfterDeductions(): float
    {
        return $this->netIncome - $this->totalDeductions;
    }

    /**
     * AD/ART group rows — calculated from net profit after deductions.
     */
    #[Computed]
    public function adArtRows(): Collection
    {
        $base = $this->netIncomeAfterDeductions;

        return $this->allocationRows
            ->where('allocation_group', 'ad_art')
            ->map(function ($row) use ($base) {
                $amount = (float) $row->percentage / 100 * $base;

                return [
                    'description' => $row->description,
                    'percentage' => (float) $row->percentage,
                    'amount' => $amount,
                ];
            })->values();
    }

    /**
     * Total percentage of all AD/ART rows.
     */
    #[Computed]
    public function totalAdArtPercent(): float
    {
        return $this->allocationRows->where('allocation_group', 'ad_art')->sum('percentage');
    }

    /**
     * Total amount of all AD/ART rows.
     */
    #[Computed]
    public function totalAdArtAmount(): float
    {
        return $this->adArtRows->sum('amount');
    }

    #[Computed]
    public function canEdit(): bool
    {
        // Only the director, secretary, and treasurer can edit
        return Auth::user()->hasAnyRole(['direktur_bumdes', 'sekretaris', 'bendahara']);
    }

    /**
     * Kepala desa dan pengawas hanya melihat angka: tanpa blok tanda tangan.
     */
    #[Computed]
    public function showSignature(): bool
    {
        return ! Auth::user()->hasAnyRole(['kepala_desa', 'pengawas']);
    }

    // ─── Actions ─────────────────────────────────────────────────────────

    public function openCreate(): void
    {
        if (! $this->canEdit) {
            abort(403);
        }

        $this->reset(['formDescription', 'formGroup', 'formPercentage', 'editingDescription']);
        $this->formGroup = 'pengurang';
        $this->showModal = true;
        $this->resetErrorBag();
    }

    public function openEdit(string $description): void
    {
        if (! $this->canEdit) {
            abort(403);
        }

        $row = $this->allocationRows->firstWhere('description', $description);
        if (! $row) {
            return;
        }

        $this->editingDescription = $row->description;
        $this->formDescription = $row->description;
        $this->formGroup = $row->allocation_group;
        $this->formPercentage = (string) $row->percentage;
        $this->showModal = true;
        $this->resetErrorBag();
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset(['formDescription', 'formGroup', 'formPercentage', 'editingDescription']);
        $this->formGroup = 'pengurang';
        $this->resetErrorBag();
    }

    public function saveRow(): void
    {
        if (! $this->canEdit) {
            abort(403);
        }

        try {
            $this->validate([
                'formDescription' => 'required|string|max:100',
                'formGroup' => 'required|in:pengurang,ad_art',
                'formPercentage' => 'required|numeric|min:0.01|max:100',
            ]);
        } catch (ValidationException $e) {
            $this->dispatch('swal-alert', icon: 'error', title: 'Gagal menyimpan', text: (string) $e->validator->errors()->first());

            throw $e;
        }

        $today = Carbon::now()->format('Y-m-d');

        // Rename: retire old description globally so it disappears for all periods
        if ($this->editingDescription !== null && $this->formDescription !== $this->editingDescription) {
            ProfitAllocationRecord::create([
                'description' => $this->editingDescription,
                'percentage' => 0,
                'allocation_group' => 'pengurang',
                'effective_from' => $today,
                'business_unit_id' => null,
            ]);
        }

        ProfitAllocationRecord::create([
            'description' => $this->formDescription,
            'percentage' => (float) $this->formPercentage,
            'allocation_group' => $this->formGroup,
            'effective_from' => $today,
            'business_unit_id' => null, // Global level
        ]);

        $isEdit = $this->editingDescription !== null;

        $this->reset(['formDescription', 'formGroup', 'formPercentage', 'showModal', 'editingDescription']);
        $this->formGroup = 'pengurang'; // Reset to default
        $this->resetErrorBag();
        $this->dispatch('swal-alert', icon: 'success', title: 'Berhasil', text: $isEdit ? 'Baris alokasi berhasil diperbarui.' : 'Baris alokasi berhasil ditambahkan.');
    }

    public function confirmDelete(string $description): void
    {
        $this->deleteDescription = $description;
        $this->showDeleteModal = true;
    }

    public function executeDelete(): void
    {
        if (! $this->canEdit || ! $this->deleteDescription) {
            abort(403);
        }

        $today = Carbon::now()->format('Y-m-d');

        // Set percentage = 0 to mark as deleted globally (immutable audit history)
        ProfitAllocationRecord::create([
            'description' => $this->deleteDescription,
            'percentage' => 0,
            'allocation_group' => 'pengurang', // Category is irrelevant on deletion
            'effective_from' => $today,
            'business_unit_id' => null,
        ]);

        $this->dispatch('swal-alert', icon: 'success', title: 'Berhasil', text: 'Baris alokasi berhasil dihapus.');

        $this->showDeleteModal = false;
        $this->deleteDescription = null;
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

        set_time_limit(120);

        $pdf = Pdf::loadView('pdf.profit-allocation', array_merge(compact(
            'entityName', 'printDate', 'signatureDate', 'periodLabel', 'signatory', 'position',
            'netIncome', 'deductionRows', 'totalDeductions', 'deductionLabel', 'netIncomeAfterDeductions',
            'adArtRows', 'totalAdArtPercent', 'totalAdArtAmount'
        ), ['showSignature' => $this->showSignature]))->setPaper('a4', 'portrait');

        return [$pdf, PdfExport::filename('Alokasi Laba', 'BUMDes', $periodLabel)];
    }

    public function render()
    {
        return view('livewire.profit-loss.profit-allocation');
    }
}
