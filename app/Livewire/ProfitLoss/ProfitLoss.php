<?php

namespace App\Livewire\ProfitLoss;

use App\Models\Account;
use App\Models\BusinessUnit;
use App\Models\JournalEntry;
use App\Support\BumdesCashBalance;
use App\Support\PdfExport;
use App\Support\SafeDates;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Laporan Laba Rugi')]
class ProfitLoss extends Component
{
    public mixed $unit_id = null;

    /** 'monthly' | 'semester' | 'yearly' */
    public string $mode = 'monthly';

    /** Y-m format for monthly, Y for yearly */
    public string $period = '';

    public string $semester = '1';

    public string $semesterYear = '';

    public function mount(): void
    {
        $user = Auth::user();

        // Unit heads: locked to their own unit
        if ($user->hasRole('kepala_unit')) {
            $this->unit_id = $user->business_unit_id;
        } elseif (empty($this->unit_id)) {
            $this->unit_id = 'bumdes';
        }

        // Default to the current month/year
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

    public function isBumdesScope(): bool
    {
        return ! Auth::user()?->hasRole('kepala_unit') && ($this->unit_id === 'bumdes' || empty($this->unit_id));
    }

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
            return mb_strtoupper('Tahun '.SafeDates::year($this->period), 'UTF-8');
        }

        if ($this->mode === 'semester') {
            $year = SafeDates::year($this->semesterYear);

            return mb_strtoupper('Semester '.$this->semester.' Tahun '.$year, 'UTF-8');
        }

        return mb_strtoupper(SafeDates::month($this->period)->translatedFormat('F Y'), 'UTF-8');
    }

    private function buildQuery()
    {
        [$start, $end] = $this->periodRange();
        $q = JournalEntry::query()->whereBetween('transaction_date', [$start->format('Y-m-d'), $end->format('Y-m-d')]);

        if ($this->isBumdesScope()) {
            $q->where(function ($query) {
                $query->where('voucher_number', 'like', 'KBM%')
                    ->orWhere(function ($sub) {
                        $sub->where('voucher_number', 'like', 'DBM%')
                            ->whereNull('daily_transaction_id')
                            ->whereNull('business_unit_id');
                    });
            });
        } elseif ($this->unit_id === 'all') {
            $q->whereNotNull('business_unit_id');
        } elseif ($this->unit_id && is_numeric($this->unit_id)) {
            $q->where('business_unit_id', (int) $this->unit_id);
        }

        return $q;
    }

    // Computed
    #[Computed]
    public function units(): \Illuminate\Database\Eloquent\Collection
    {
        return BusinessUnit::orderBy('name')->get();
    }

    private function accountWithBalance(string $type, string $normalDirection): Collection
    {
        $base = $this->buildQuery();

        $accountList = Account::where('type', $type)
            ->orderBy('sort_order')
            ->get();

        $accountIds = $accountList->pluck('id');

        if ($accountIds->isEmpty()) {
            return collect();
        }

        $sums = (clone $base)
            ->whereIn('account_id', $accountIds)
            ->selectRaw('account_id, '.($normalDirection === 'credit'
                ? 'SUM(credit) - SUM(debit) as amount'
                : 'SUM(debit) - SUM(credit) as amount'))
            ->groupBy('account_id')
            ->pluck('amount', 'account_id');

        // BUMDes scope: virtual revenue (prior cash balance + per-unit net income) is added
        // to account 4-2000, mirroring the virtual DBM001/DBM002+ journal entries.
        if ($this->isBumdesScope() && $type === 'pendapatan') {
            [$start, $end] = $this->periodRange();
            $revenueAccount = Account::where('code', '4-2000')->first();
            if ($revenueAccount && in_array($revenueAccount->id, $accountIds->toArray())) {
                $opening = BumdesCashBalance::getOpeningBalance($start);
                $virtualRevenue = $opening + BumdesCashBalance::getTotalNetUnitIncome($start, $end);
                $sums[$revenueAccount->id] = ($sums[$revenueAccount->id] ?? 0) + $virtualRevenue;
            }
        }

        return $accountList->map(function ($account) use ($sums) {
            return (object) [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'amount' => $sums[$account->id] ?? 0,
            ];
        });
    }

    #[Computed]
    public function reportData(): array
    {
        $revenueRows = $this->accountWithBalance('pendapatan', 'credit');
        $totalRevenue = $revenueRows->sum('amount');

        $cogsRows = $this->accountWithBalance('hpp', 'debit');
        $totalHpp = $cogsRows->sum('amount');

        $grossProfit = $totalRevenue - $totalHpp;

        $expenseRows = $this->accountWithBalance('beban', 'debit');
        $totalExpenses = $expenseRows->sum('amount');

        $otherRevenueRows = $this->accountWithBalance('pendapatan_lain', 'credit');
        $totalOtherRevenue = $otherRevenueRows->sum('amount');

        $otherExpenseRows = $this->accountWithBalance('beban_lain', 'debit');
        $totalOtherExpenses = $otherExpenseRows->sum('amount');

        $netIncome = $grossProfit - $totalExpenses + $totalOtherRevenue - $totalOtherExpenses;

        return compact(
            'revenueRows', 'totalRevenue',
            'cogsRows', 'totalHpp', 'grossProfit',
            'expenseRows', 'totalExpenses',
            'otherRevenueRows', 'totalOtherRevenue',
            'otherExpenseRows', 'totalOtherExpenses',
            'netIncome'
        );
    }

    #[Computed]
    public function isKepalaUnit(): bool
    {
        return Auth::user()->hasRole('kepala_unit');
    }

    #[Computed]
    public function canPrint(): bool
    {
        return Auth::user()->hasAnyRole(['kepala_unit', 'sekretaris', 'bendahara', 'direktur_bumdes']);
    }

    /**
     * Kepala desa and pengawas see the figures only, with no signature block.
     */
    #[Computed]
    public function showSignature(): bool
    {
        return ! Auth::user()->hasAnyRole(['kepala_desa', 'pengawas']);
    }

    #[Computed]
    public function selectedUnit(): ?BusinessUnit
    {
        return ($this->unit_id && is_numeric($this->unit_id)) ? BusinessUnit::find($this->unit_id) : null;
    }

    // Preview PDF (inline, via ReportPreviewController)
    /**
     * Build the profit-loss PDF for inline preview.
     *
     * @return array{0: \Barryvdh\DomPDF\PDF, 1: string}
     */
    public function buildReportPdf(): array
    {
        $data = $this->reportData;
        $periodLabel = $this->periodLabel();
        $unit = $this->selectedUnit;
        $entityName = $unit ? strtoupper($unit->name) : 'BUMDESA TEJA PERCEKA';

        [$start, $end] = $this->periodRange();
        $printDate = strtoupper($end->translatedFormat('d F Y'));
        $signatureDate = PdfExport::signatureDate($end);

        $signatory = Auth::user()->name;
        $position = match (true) {
            Auth::user()->hasRole('direktur_bumdes') => 'Direktur',
            Auth::user()->hasRole('sekretaris') => 'Sekretaris',
            Auth::user()->hasRole('bendahara') => 'Bendahara',
            Auth::user()->hasRole('kepala_unit') => 'Kepala Unit'.($unit ? ' '.$unit->name : ''),
            default => '',
        };

        set_time_limit(120);

        $pdf = Pdf::loadView('pdf.profit-loss', array_merge($data, compact('periodLabel', 'entityName', 'printDate', 'signatureDate', 'signatory', 'position'), ['showSignature' => $this->showSignature]))
            ->setPaper('a4', 'portrait');

        $unitLabel = $unit ? $unit->name : ($this->isBumdesScope() ? 'BUMDes' : 'Semua Unit');

        return [$pdf, PdfExport::filename('Laporan Laba Rugi', $unitLabel, $periodLabel)];
    }

    public function updatingUnitId($value): void
    {
        $user = Auth::user();
        if ($user && $user->hasRole('kepala_unit') && (int) $value !== (int) $user->business_unit_id) {
            abort(403, 'Unauthorized');
        }
    }

    public function render()
    {
        $user = Auth::user();
        if ($user && $user->hasRole('kepala_unit') && $this->unit_id !== $user->business_unit_id) {
            $this->unit_id = $user->business_unit_id;
        }

        $unit = $this->selectedUnit;
        $entityName = $unit ? strtoupper($unit->name) : 'BUMDESA TEJA PERCEKA';

        [$start, $end] = $this->periodRange();
        $printDate = strtoupper($end->translatedFormat('d F Y'));
        $signatureDate = PdfExport::signatureDate($end);
        $periodLabel = $this->periodLabel();

        $signatory = Auth::user()->name;
        $position = match (true) {
            Auth::user()->hasRole('direktur_bumdes') => 'Direktur',
            Auth::user()->hasRole('sekretaris') => 'Sekretaris',
            Auth::user()->hasRole('bendahara') => 'Bendahara',
            Auth::user()->hasRole('kepala_unit') => 'Kepala Unit'.($unit ? ' '.$unit->name : ''),
            default => '',
        };

        return view('livewire.profit-loss.profit-loss', array_merge(compact(
            'entityName', 'printDate', 'signatureDate', 'periodLabel', 'signatory', 'position'
        ), ['showSignature' => $this->showSignature]));
    }
}
