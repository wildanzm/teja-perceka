<?php

namespace App\Livewire\Transactions;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Support\Rupiah;
use App\Support\VoucherNumber;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Input Transaksi')]
class RecordIncome extends Component
{
    public string $transactionDate = '';

    public array $items = [];

    public float $totalIncome = 0;

    public string $filterMode = 'daily';

    public string $filterDate = '';

    public string $filterMonth = '';

    public string $filterSemester = '1';

    public string $filterSemesterYear = '';

    public string $filterYear = '';

    public bool $showCreateModal = false;

    public bool $showEditModal = false;

    public bool $showDeleteModal = false;

    public ?int $editingJournalId = null;

    public ?int $deletingJournalId = null;

    public string $editDate = '';

    public string $editAccountId = '';

    public string $editDescription = '';

    public mixed $editAmount = '';

    public function boot(): void
    {
        $user = Auth::user();

        if (! $user || ! $user->hasAnyRole(['direktur_bumdes', 'sekretaris', 'bendahara'])) {
            abort(403, 'Akses ditolak.');
        }
    }

    private function revenueAccountRule(): Exists
    {
        return Rule::exists('accounts', 'id')
            ->whereIn('type', ['pendapatan', 'pendapatan_lain'])
            ->whereNot('code', '4-4000');
    }

    public function mount(): void
    {
        $user = Auth::user();

        if (! $user || ! $user->hasAnyRole(['direktur_bumdes', 'sekretaris', 'bendahara'])) {
            abort(403, 'Akses ditolak.');
        }

        $today = Carbon::today();
        $this->transactionDate = $today->format('Y-m-d');

        $this->filterMode = 'daily';
        $this->filterDate = $today->format('Y-m-d');
        $this->filterMonth = $today->format('Y-m');
        $this->filterSemester = $today->month <= 6 ? '1' : '2';
        $this->filterSemesterYear = $today->format('Y');
        $this->filterYear = $today->format('Y');

        $this->addItem();
    }

    public function addItem(): void
    {
        $this->items[] = [
            'account_id' => '',
            'description' => '',
            'amount' => '',
        ];
    }

    public function removeItem(int $index): void
    {
        array_splice($this->items, $index, 1);
        $this->items = array_values($this->items);
        $this->calculateTotal();
    }

    public function updatedItems(): void
    {
        $this->calculateTotal();
    }

    public function openCreateModal(): void
    {
        $this->resetErrorBag();
        $this->reset(['items', 'totalIncome']);
        $this->transactionDate = Carbon::today()->format('Y-m-d');
        $this->addItem();
        $this->showCreateModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
        $this->resetErrorBag();
    }

    public function closeEditModal(): void
    {
        $this->showEditModal = false;
        $this->reset(['editingJournalId', 'editAccountId', 'editDescription', 'editAmount', 'editDate']);
        $this->resetErrorBag();
    }

    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->deletingJournalId = null;
    }

    public function resetFilter(): void
    {
        $today = Carbon::today();
        $this->filterMode = 'daily';
        $this->filterDate = $today->format('Y-m-d');
        $this->filterMonth = $today->format('Y-m');
        $this->filterSemester = $today->month <= 6 ? '1' : '2';
        $this->filterSemesterYear = $today->format('Y');
        $this->filterYear = $today->format('Y');
    }

    private function calculateTotal(): void
    {
        $total = 0;
        foreach ($this->items as $item) {
            $total += Rupiah::parse($item['amount'] ?? null);
        }
        $this->totalIncome = $total;
    }

    public function submit(): void
    {
        foreach ($this->items as $index => $item) {
            $this->items[$index]['amount'] = Rupiah::parse($item['amount'] ?? null);
        }

        $this->validate([
            'transactionDate' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.account_id' => ['required', $this->revenueAccountRule()],
            'items.*.description' => 'required|string|max:500',
            'items.*.amount' => 'required|numeric|min:1|max:9999999999999',
        ], [
            'items.required' => 'Minimal satu item pendapatan harus diisi.',
            'items.*.account_id.required' => 'Pilih akun pendapatan untuk setiap item.',
            'items.*.description.required' => 'Keterangan wajib diisi untuk setiap item.',
            'items.*.amount.required' => 'Nominal wajib diisi untuk setiap item.',
            'items.*.amount.min' => 'Nominal harus lebih dari 0.',
            'items.*.amount.max' => 'Nominal terlalu besar.',
        ]);

        $validItems = array_values(array_filter($this->items, fn ($item) => (float) ($item['amount'] ?? 0) > 0 && ! empty($item['account_id'])));

        if (empty($validItems)) {
            $this->addError('items', 'Minimal satu item pendapatan dengan nominal valid harus diisi.');
            $this->dispatch('swal-alert', icon: 'error', title: 'Gagal menyimpan', text: 'Minimal satu item pendapatan dengan nominal valid harus diisi.');

            return;
        }

        DB::beginTransaction();

        try {
            $cashAccount = Account::where('code', '1-1100')->firstOrFail();

            $date = Carbon::parse($this->transactionDate);
            $voucherBatch = VoucherNumber::nextBatch('DBM', $date->format('Y-m-d'), count($validItems), null);

            foreach ($validItems as $index => $item) {
                $itemAmount = (float) $item['amount'];
                $voucherIdentifier = $voucherBatch[$index];
                $description = $item['description'];

                // 1. Debit to Cash (1-1100)
                JournalEntry::create([
                    'voucher_number' => $voucherIdentifier,
                    'transaction_date' => $this->transactionDate,
                    'description' => $description,
                    'account_id' => $cashAccount->id,
                    'debit' => $itemAmount,
                    'credit' => 0,
                    'daily_transaction_id' => null,
                    'business_unit_id' => null,
                ]);

                // 2. Credit to Revenue Account
                JournalEntry::create([
                    'voucher_number' => $voucherIdentifier,
                    'transaction_date' => $this->transactionDate,
                    'description' => $description,
                    'account_id' => (int) $item['account_id'],
                    'debit' => 0,
                    'credit' => $itemAmount,
                    'daily_transaction_id' => null,
                    'business_unit_id' => null,
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            $this->dispatch('swal-alert', icon: 'error', title: 'Gagal menyimpan', text: $e->getMessage());

            return;
        }

        $this->dispatch('swal-alert', icon: 'success', title: 'Berhasil', text: 'Pendapatan BUMDes berhasil dicatat!');

        $this->reset(['items', 'totalIncome']);
        $this->transactionDate = Carbon::today()->format('Y-m-d');
        $this->addItem();
        $this->showCreateModal = false;
    }

    private function historyGroup(int $journalId)
    {
        $anchor = JournalEntry::where('id', $journalId)
            ->whereNull('business_unit_id')
            ->whereNull('daily_transaction_id')
            ->where('voucher_number', 'like', 'DBM%')
            ->first();

        if (! $anchor) {
            return collect();
        }

        return JournalEntry::where('voucher_number', $anchor->voucher_number)
            ->whereNull('business_unit_id')
            ->whereNull('daily_transaction_id')
            ->orderBy('id')
            ->get();
    }

    public function editHistory(int $journalId): void
    {
        $group = $this->historyGroup($journalId);
        $revenueJournal = $group->first(fn ($row) => $row->credit > 0);

        if (! $revenueJournal) {
            return;
        }

        $this->editingJournalId = $journalId;
        $this->editDate = $revenueJournal->transaction_date
            ? Carbon::parse($revenueJournal->transaction_date)->format('Y-m-d')
            : Carbon::today()->format('Y-m-d');
        $this->editAccountId = (string) $revenueJournal->account_id;
        $this->editDescription = $revenueJournal->description;
        $this->editAmount = $revenueJournal->credit;
        $this->showEditModal = true;
    }

    public function updateHistory(): void
    {
        $this->editAmount = Rupiah::parse($this->editAmount);

        $validated = $this->validate([
            'editDate' => 'required|date',
            'editAccountId' => ['required', $this->revenueAccountRule()],
            'editDescription' => 'required|string|max:500',
            'editAmount' => 'required|numeric|min:1|max:9999999999999',
        ], [
            'editAccountId.required' => 'Pilih akun pendapatan.',
            'editDescription.required' => 'Keterangan wajib diisi.',
            'editAmount.required' => 'Nominal wajib diisi.',
            'editAmount.min' => 'Nominal harus lebih dari 0.',
        ]);

        $journals = $this->historyGroup((int) $this->editingJournalId);
        if ($journals->isEmpty()) {
            return;
        }

        $prefix = VoucherNumber::prefixOf($journals->first()->voucher_number);
        $oldMonth = substr((string) $journals->first()->transaction_date, 0, 7);
        $newMonth = substr($validated['editDate'], 0, 7);

        DB::transaction(function () use ($journals, $validated, $prefix, $oldMonth, $newMonth) {
            $cashAccount = Account::where('code', '1-1100')->firstOrFail();

            foreach ($journals as $j) {
                $isCash = $j->debit > 0;
                $j->update([
                    'transaction_date' => $validated['editDate'],
                    'description' => $validated['editDescription'],
                    'account_id' => $isCash ? $cashAccount->id : (int) $validated['editAccountId'],
                    'debit' => $isCash ? $validated['editAmount'] : 0,
                    'credit' => $isCash ? 0 : $validated['editAmount'],
                ]);
            }

            // Cross-month move leaves a gap behind: close it on both sides.
            if ($prefix !== null && $newMonth !== $oldMonth) {
                VoucherNumber::renumberScope($prefix, $oldMonth, null);
                VoucherNumber::renumberScope($prefix, $newMonth, null);
            }
        });

        $this->showEditModal = false;
        $this->reset(['editingJournalId', 'editAccountId', 'editDescription', 'editAmount', 'editDate']);

        $this->dispatch('swal-alert', icon: 'success', title: 'Berhasil', text: 'Data pendapatan berhasil diperbarui.');
    }

    public function confirmDelete(int $journalId): void
    {
        $this->deletingJournalId = $journalId;
        $this->showDeleteModal = true;
    }

    public function executeDelete(): void
    {
        if (! $this->deletingJournalId) {
            return;
        }

        $journals = $this->historyGroup($this->deletingJournalId);
        if ($journals->isNotEmpty()) {
            $prefix = VoucherNumber::prefixOf($journals->first()->voucher_number);
            $month = substr((string) $journals->first()->transaction_date, 0, 7);

            DB::transaction(function () use ($journals, $prefix, $month) {
                foreach ($journals as $j) {
                    $j->delete();
                }

                if ($prefix !== null) {
                    VoucherNumber::renumberScope($prefix, $month, null);
                }
            });
        }

        $this->showDeleteModal = false;
        $this->deletingJournalId = null;

        $this->dispatch('swal-alert', icon: 'success', title: 'Berhasil', text: 'Data pendapatan berhasil dihapus.');
    }

    public function updatedFilterMode(): void
    {
        $today = Carbon::today();
        match ($this->filterMode) {
            'daily' => $this->filterDate = $this->filterDate ?: $today->format('Y-m-d'),
            'monthly' => $this->filterMonth = $this->filterMonth ?: $today->format('Y-m'),
            'semester' => [
                $this->filterSemester = $this->filterSemester ?: '1',
                $this->filterSemesterYear = $this->filterSemesterYear ?: $today->format('Y'),
            ],
            'yearly' => $this->filterYear = $this->filterYear ?: $today->format('Y'),
            default => $this->filterDate = $this->filterDate ?: $today->format('Y-m-d'),
        };
    }

    #[Computed]
    public function history()
    {
        $query = JournalEntry::with('account')
            ->whereNull('business_unit_id')
            ->whereNull('daily_transaction_id')
            ->where('voucher_number', 'like', 'DBM%')
            ->where('credit', '>', 0);

        match ($this->filterMode) {
            'daily' => $query->whereDate('transaction_date', $this->filterDate ?: Carbon::today()->format('Y-m-d')),
            'monthly' => $query->whereBetween('transaction_date', [
                Carbon::parse(($this->filterMonth ?: date('Y-m')).'-01')->startOfMonth()->format('Y-m-d'),
                Carbon::parse(($this->filterMonth ?: date('Y-m')).'-01')->endOfMonth()->format('Y-m-d'),
            ]),
            'semester' => $query->whereBetween('transaction_date', [
                Carbon::create((int) ($this->filterSemesterYear ?: date('Y')), $this->filterSemester === '1' ? 1 : 7, 1)->startOfMonth()->format('Y-m-d'),
                Carbon::create((int) ($this->filterSemesterYear ?: date('Y')), $this->filterSemester === '1' ? 6 : 12, 1)->endOfMonth()->format('Y-m-d'),
            ]),
            'yearly' => $query->whereBetween('transaction_date', [
                Carbon::create((int) ($this->filterYear ?: date('Y')), 1, 1)->startOfDay()->format('Y-m-d'),
                Carbon::create((int) ($this->filterYear ?: date('Y')), 12, 31)->endOfDay()->format('Y-m-d'),
            ]),
            default => null,
        };

        $rows = $query->orderBy('transaction_date', 'asc')->orderBy('id', 'asc')->get();

        return $rows;
    }

    #[Computed]
    public function historyTotal(): float
    {
        return (float) $this->history->sum('credit');
    }

    public function render()
    {
        $revenueAccounts = Account::whereIn('type', ['pendapatan', 'pendapatan_lain'])
            ->where('code', '!=', '4-4000')
            ->orderBy('code')
            ->get();

        return view('livewire.transactions.record-income', [
            'revenueAccounts' => $revenueAccounts,
            'history' => $this->history,
            'historyTotal' => $this->historyTotal,
        ]);
    }
}
