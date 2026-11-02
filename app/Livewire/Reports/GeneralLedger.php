<?php

namespace App\Livewire\Reports;

use App\Models\Account;
use App\Models\BusinessUnit;
use App\Models\JournalEntry;
use App\Support\BumdesCashBalance;
use App\Support\PdfExport;
use App\Support\SafeDates;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Laporan Buku Besar')]
class GeneralLedger extends Component
{
    /** null = consolidate all units */
    public mixed $unit_id = null;

    /** Format Y-m */
    public string $period = '';

    public ?int $account_id = null;

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

        $firstAccount = Account::where('type', '!=', 'header')->orderBy('code')->first();
        if ($firstAccount) {
            $this->account_id = $firstAccount->id;
        }
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

    #[Computed]
    public function units(): Collection
    {
        return BusinessUnit::orderBy('name')->get();
    }

    #[Computed]
    public function accounts(): Collection
    {
        return Account::where('type', '!=', 'header')->orderBy('code')->get();
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
    public function reportData(): array
    {
        $transactions = collect();
        $openingBalance = 0;
        $selectedAccount = null;
        $normalBalance = 'debit';
        $totalDebit = 0;
        $totalCredit = 0;

        if ($this->account_id && $this->period) {
            $selectedAccount = Account::find($this->account_id);

            if ($selectedAccount) {
                $normalBalance = $this->getNormalBalanceType($selectedAccount->type);
                [$startDate, $endDate] = $this->periodRange();

                // Only the BUMDes ledger carries a prior-period balance: unit
                // ledgers restart at zero each month, and BUMDes cash is
                // already summarized by the virtual "Saldo Kas" row (DBM001),
                // so adding it here too would count it twice.
                $carriesOpening = $this->isBumdesScope()
                    && ! in_array($selectedAccount->code, ['1-1100', '1-1200'], true);

                if ($carriesOpening) {
                    $openingQuery = JournalEntry::where('account_id', $this->account_id)
                        ->whereDate('transaction_date', '<', $startDate->format('Y-m-d'))
                        ->where(function ($q) {
                            $q->where('voucher_number', 'like', 'KBM%')
                                ->orWhere(function ($sub) {
                                    $sub->where('voucher_number', 'like', 'DBM%')
                                        ->whereNull('daily_transaction_id')
                                        ->whereNull('business_unit_id');
                                });
                        });

                    $openingDebit = (clone $openingQuery)->sum('debit');
                    $openingCredit = (clone $openingQuery)->sum('credit');

                    $openingBalance = $normalBalance === 'debit'
                        ? $openingDebit - $openingCredit
                        : $openingCredit - $openingDebit;
                }

                // Get current transactions
                $currentQuery = JournalEntry::with('businessUnit')
                    ->where('account_id', $this->account_id)
                    ->whereBetween('transaction_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                    ->orderBy('transaction_date', 'asc')
                    ->orderBy('id', 'asc');

                if ($this->isBumdesScope()) {
                    $currentQuery->where(function ($q) {
                        $q->where('voucher_number', 'like', 'KBM%')
                            ->orWhere(function ($sub) {
                                $sub->where('voucher_number', 'like', 'DBM%')
                                    ->whereNull('daily_transaction_id')
                                    ->whereNull('business_unit_id');
                            });
                    });
                } elseif ($this->unit_id && is_numeric($this->unit_id)) {
                    $currentQuery->where('business_unit_id', (int) $this->unit_id);
                }

                $transactions = $currentQuery->get();
                $totalDebit = $transactions->sum('debit');
                $totalCredit = $transactions->sum('credit');

                // BUMDes scope: inject virtual entries (saldo sebelumnya + net income per unit)
                // into the transaction list for cash (1-1100), equity (3-2000), and revenue (4-2000) accounts.
                if ($this->isBumdesScope() && in_array($selectedAccount->code, ['1-1100', '1-1200', '3-2000', '4-2000'])) {
                    $isCash = in_array($selectedAccount->code, ['1-1100', '1-1200']);
                    $virtualEntries = BumdesCashBalance::getBumdesVirtualEntries($startDate, $endDate);
                    $relevantVirtuals = $virtualEntries->filter(fn ($entry) => $entry->account_id === $selectedAccount->id);

                    if ($relevantVirtuals->isNotEmpty()) {
                        $transactions = $transactions->concat($relevantVirtuals)->sortBy([
                            fn ($a, $b) => $a->transaction_date <=> $b->transaction_date,
                            fn ($a, $b) => $a->id <=> $b->id,
                        ])->values();
                        $totalDebit += $relevantVirtuals->sum('debit');
                        $totalCredit += $relevantVirtuals->sum('credit');
                    }
                }
            }
        }

        return compact('transactions', 'openingBalance', 'selectedAccount', 'normalBalance', 'totalDebit', 'totalCredit');
    }

    // ─── Preview PDF (inline, via ReportPreviewController) ────────────────

    /**
     * Build the ledger PDF for inline preview.
     *
     * @return array{0: \Barryvdh\DomPDF\PDF, 1: string}
     */
    public function buildReportPdf(): array
    {
        $data = $this->reportData;
        if (! $data['selectedAccount']) {
            abort(404, 'Kode Akun tidak ditemukan.');
        }

        $unit = $this->selectedUnit;
        $entityName = $unit ? strtoupper($unit->name) : 'BUMDESA TEJA PERCEKA';

        [$start, $end] = $this->periodRange();
        $printDate = strtoupper($end->translatedFormat('d F Y'));
        $signatureDate = PdfExport::signatureDate($end);
        $periodLabel = SafeDates::month($this->period)->translatedFormat('F Y');

        $signatory = Auth::user()->name;
        $position = match (true) {
            Auth::user()->hasRole('direktur_bumdes') => 'Direktur',
            Auth::user()->hasRole('sekretaris') => 'Sekretaris',
            Auth::user()->hasRole('bendahara') => 'Bendahara',
            Auth::user()->hasRole('kepala_unit') => 'Kepala Unit'.($unit ? ' '.$unit->name : ''),
            default => '',
        };

        set_time_limit(120);

        $pdf = Pdf::loadView('pdf.general-ledger', array_merge($data, compact('periodLabel', 'entityName', 'printDate', 'signatureDate', 'signatory', 'position'), ['showSignature' => $this->showSignature]))
            ->setPaper('a4', 'portrait');

        $unitLabel = $unit ? $unit->name : ($this->isBumdesScope() ? 'BUMDes' : 'Semua Unit');

        return [$pdf, PdfExport::filename('Buku Besar', $unitLabel, $data['selectedAccount']->code.' '.$data['selectedAccount']->name, $periodLabel)];
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

        return view('livewire.reports.general-ledger', [
            'entityName' => $unit ? strtoupper($unit->name) : 'BUMDESA TEJA PERCEKA',
        ]);
    }
}
