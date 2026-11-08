<?php

namespace App\Livewire\Reports;

use App\Models\Account;
use App\Models\BusinessUnit;
use App\Models\JournalEntry;
use App\Support\BumdesCashBalance;
use App\Support\PdfExport;
use App\Support\SafeDates;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Neraca Saldo')]
class TrialBalance extends Component
{
    /** null = consolidate all units */
    public mixed $unit_id = null;

    /** Format Y-m */
    public string $period = '';

    public function mount(): void
    {
        $user = Auth::user();

        // Kepala unit: kunci ke unit sendiri
        if ($user->hasRole('kepala_unit')) {
            $this->unit_id = $user->business_unit_id;
        } elseif (empty($this->unit_id)) {
            $this->unit_id = 'bumdes';
        }

        $this->period = Carbon::now()->format('Y-m');
    }

    private function getNormalBalanceType(string $type): string
    {
        $type = strtolower($type);
        if (in_array($type, ['aktiva', 'aset', 'beban', 'beban_lain', 'hpp'])) {
            return 'debit';
        }

        return 'credit';
    }

    public function isBumdesScope(): bool
    {
        return ! Auth::user()?->hasRole('kepala_unit') && ($this->unit_id === 'bumdes' || empty($this->unit_id));
    }

    private function periodRange(): array
    {
        $date = SafeDates::month($this->period);

        return [
            $date->copy()->startOfMonth(),
            $date->copy()->endOfMonth(),
        ];
    }

    private function applyReportScope(Builder $query): void
    {
        if ($this->isBumdesScope()) {
            $query->where(function ($q) {
                $q->where('voucher_number', 'like', 'KBM%')
                    ->orWhere(function ($sub) {
                        $sub->where('voucher_number', 'like', 'DBM%')
                            ->whereNull('daily_transaction_id')
                            ->whereNull('business_unit_id');
                    });
            });
        } elseif ($this->unit_id && is_numeric($this->unit_id)) {
            $query->where('business_unit_id', (int) $this->unit_id);
        } else {
            // Fail closed, matching GeneralLedger::reportData(): a kepala_unit whose unit
            // is missing satisfies neither branch, and falling through would silently
            // return every unit's rows.
            abort(403, 'Unit scope tidak dapat ditentukan untuk laporan ini.');
        }
    }

    private function sumDebitCreditPerAccount(Builder $query): BaseCollection
    {
        return $query->select(
            'account_id',
            DB::raw('SUM(debit) as totalDebit'),
            DB::raw('SUM(credit) as totalCredit')
        )
            ->groupBy('account_id')
            ->get()
            ->keyBy('account_id');
    }

    #[Computed]
    public function units(): Collection
    {
        return BusinessUnit::orderBy('name')->get();
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
     * Kepala desa dan pengawas hanya melihat angka: tanpa blok tanda tangan.
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

    #[Computed]
    public function periodLabel(): string
    {
        // Guard through SafeDates so a crafted user-supplied period string
        // falls back to the current month instead of throwing a 500.
        return SafeDates::month($this->period)->translatedFormat('F Y');
    }

    #[Computed]
    public function reportData(): array
    {
        $accounts = Account::where('type', '!=', 'header')->orderBy('code')->get();

        $currentAssets = collect();
        $fixedAssets = collect();
        $currentLiabilities = collect();
        $longTermLiabilities = collect();
        $equity = collect();

        $totalCurrentAssets = 0;
        $totalFixedAssets = 0;
        $totalCurrentLiabilities = 0;
        $totalLongTermLiabilities = 0;
        $totalEquity = 0;

        $totalRevenue = 0;
        $totalExpenses = 0;

        if ($this->period) {
            [$startDate, $endDate] = $this->periodRange();
            $startDay = $startDate->format('Y-m-d');
            $endDay = $endDate->format('Y-m-d');

            // Strictly monthly: only the filtered month's movements, no prior accumulation/injection.
            $monthlyQuery = JournalEntry::whereBetween('transaction_date', [$startDay, $endDay]);
            $this->applyReportScope($monthlyQuery);
            $balancePerAccount = $this->sumDebitCreditPerAccount($monthlyQuery);

            // BUMDes scope: inject opening balance (Kas & Laba/Rugi Bulan Lalu)
            // and current-month unit income (all units) via virtual entries.
            if ($this->isBumdesScope()) {
                $openingBalance = BumdesCashBalance::getOpeningBalance($startDate);
                $netUnitIncome = BumdesCashBalance::getTotalNetUnitIncome($startDate, $endDate);
                $totalIncome = $openingBalance + $netUnitIncome;

                // Cash account: opening balance + net unit income (debit)
                $cashAccount = Account::where('code', '1-1100')->first();
                if ($cashAccount && $totalIncome > 0) {
                    $row = $balancePerAccount->get($cashAccount->id);
                    if ($row) {
                        $row->totalDebit += $totalIncome;
                    } else {
                        $balancePerAccount->put($cashAccount->id, (object) [
                            'account_id' => $cashAccount->id,
                            'totalDebit' => $totalIncome,
                            'totalCredit' => 0,
                        ]);
                    }
                }

                $totalRevenue += $totalIncome;
            }

            foreach ($accounts as $account) {
                $balance = $balancePerAccount->get($account->id);
                $debitTotal = $balance ? $balance->totalDebit : 0;
                $creditTotal = $balance ? $balance->totalCredit : 0;

                $normalBalance = $this->getNormalBalanceType($account->type);
                $closingBalance = 0;

                if ($normalBalance === 'debit') {
                    $closingBalance = $debitTotal - $creditTotal;
                } else {
                    $closingBalance = $creditTotal - $debitTotal;
                }

                $prefix = substr($account->code, 0, 3);
                $firstDigit = substr($account->code, 0, 1);

                // Profit/loss from the filtered month's movements (nominal accounts are already monthly).
                // Classify by account type, not code prefix: 7-x holds both pendapatan_lain
                // and beban_lain, and the prefix alone would treat expenses as revenue.
                if (in_array($account->type, ['pendapatan', 'pendapatan_lain'], true)) {
                    $totalRevenue += $closingBalance; // Credit-normal balance, so closing is credit minus debit
                } elseif (in_array($account->type, ['hpp', 'beban', 'beban_lain'], true)) {
                    $totalExpenses += $closingBalance; // Debit-normal balance, so closing is debit minus credit
                }

                if ($closingBalance == 0 && $firstDigit !== '3' && $firstDigit !== '1' && $firstDigit !== '2') {
                    continue; // Nominal accounts with zero balance are never displayed anyway.
                }

                $item = (object) [
                    'id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->name,
                    'balance' => $closingBalance,
                ];

                if ($prefix === '1-1') {
                    $currentAssets->push($item);
                    $totalCurrentAssets += $closingBalance;
                } elseif ($prefix === '1-2') {
                    $fixedAssets->push($item);
                    $totalFixedAssets += $closingBalance;
                } elseif ($prefix === '2-1') {
                    $currentLiabilities->push($item);
                    $totalCurrentLiabilities += $closingBalance;
                } elseif ($prefix === '2-2') {
                    $longTermLiabilities->push($item);
                    $totalLongTermLiabilities += $closingBalance;
                } elseif ($firstDigit === '3') {
                    if ($account->code === '3-3000') {
                        // Skip injecting Laba Bersih here, we'll do it manually after the loop
                    } else {
                        $equity->push($item);
                        $totalEquity += $closingBalance;
                    }
                }
            }

            // Inject Laba Bersih
            $netIncome = $totalRevenue - $totalExpenses;

            $netIncomeAccount = Account::where('code', '3-3000')->first();
            if ($netIncomeAccount) {
                $equity->push((object) [
                    'id' => $netIncomeAccount->id,
                    'code' => $netIncomeAccount->code,
                    'name' => 'LABA BERSIH', // Override name to match excel
                    'balance' => $netIncome,
                ]);
                $totalEquity += $netIncome;
            }
        }

        $totalAssets = $totalCurrentAssets + $totalFixedAssets;
        $totalLiabilities = $totalCurrentLiabilities + $totalLongTermLiabilities;
        $totalLiabilitiesEquity = $totalLiabilities + $totalEquity;

        return compact(
            'currentAssets', 'fixedAssets', 'totalCurrentAssets', 'totalFixedAssets', 'totalAssets',
            'currentLiabilities', 'longTermLiabilities', 'totalCurrentLiabilities', 'totalLongTermLiabilities', 'totalLiabilities',
            'equity', 'totalEquity', 'totalLiabilitiesEquity', 'netIncome'
        );
    }

    /**
     * Build the trial-balance PDF for inline preview.
     *
     * @return array{0: \Barryvdh\DomPDF\PDF, 1: string}
     */
    public function buildReportPdf(): array
    {
        // Re-pin the unit head exactly as render() does, so the export path does not
        // depend on the caller (ReportPreviewController) having scoped correctly.
        $user = Auth::user();
        if ($user && $user->hasRole('kepala_unit')) {
            $this->unit_id = $user->business_unit_id;
        }

        $data = $this->reportData;
        $unit = $this->selectedUnit;
        $entityName = $unit ? strtoupper($unit->name) : 'BUMDESA TEJA PERCEKA';

        [$start, $end] = $this->periodRange();
        $printDate = strtoupper($end->translatedFormat('d F Y'));
        $signatureDate = PdfExport::signatureDate($end);
        $periodLabel = $this->periodLabel;

        $signatory = Auth::user()->name;
        $position = match (true) {
            Auth::user()->hasRole('direktur_bumdes') => 'Direktur',
            Auth::user()->hasRole('sekretaris') => 'Sekretaris',
            Auth::user()->hasRole('bendahara') => 'Bendahara',
            Auth::user()->hasRole('kepala_unit') => 'Kepala Unit'.($unit ? ' '.$unit->name : ''),
            default => '',
        };

        set_time_limit(120);

        $pdf = Pdf::loadView('pdf.trial-balance', array_merge($data, compact('periodLabel', 'entityName', 'printDate', 'signatureDate', 'signatory', 'position'), ['showSignature' => $this->showSignature]))
            ->setPaper('a4', 'portrait');

        $unitLabel = $unit ? $unit->name : ($this->isBumdesScope() ? 'BUMDes' : 'Semua Unit');

        return [$pdf, PdfExport::filename('Neraca Saldo', $unitLabel, $periodLabel)];
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

        return view('livewire.reports.trial-balance', [
            'entityName' => $unit ? strtoupper($unit->name) : 'BUMDESA TEJA PERCEKA',
        ]);
    }
}
