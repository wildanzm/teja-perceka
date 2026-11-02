<?php

namespace App\Livewire\UnitHead;

use App\Enums\CategoryType;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\BusinessUnit;
use App\Models\DailyTransaction;
use App\Models\JournalEntry;
use App\Models\TransactionCategory;
use App\Models\TransactionItem;
use App\Support\Rupiah;
use App\Support\VoucherNumber;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class RecordDailyTransaction extends Component
{
    #[Locked]
    public ?int $unitId = null;

    public ?BusinessUnit $unit = null;

    public string $transactionDate = '';

    public ?string $endDate = null;

    public bool $isWeekly = false;

    /** When set, the component is in edit mode for this DailyTransaction ID. */
    #[Locked]
    public ?int $editId = null;

    public bool $isEditing = false;

    // Holds the input state of each category
    // Format: [category_id => ['quantity' => value, 'amount' => value, 'active' => boolean, 'subtotal' => value]]
    public array $inputs = [];

    public bool $alreadySubmitted = false;

    public bool $showDuplicateError = false;

    public float $totalIncome = 0;

    public function mount(?int $editId = null)
    {
        $user = Auth::user();

        // Restrict access to the kepala_unit role owning a business unit
        if (! $user->hasRole('kepala_unit') || ! $user->business_unit_id) {
            abort(403, 'Akses ditolak. Anda bukan kepala unit yang valid.');
        }

        $this->unitId = $user->business_unit_id;
        $this->unit = BusinessUnit::findOrFail($this->unitId);

        $today = Carbon::today();
        $this->isWeekly = $this->unit->input_frequency === 'weekly';

        if ($editId) {
            $this->loadEditMode($editId);
        } else {
            if ($this->isWeekly) {
                // For weekly units (TPS), default to today as the specific entry date,
                // while endDate marks the end of the current week period.
                $this->transactionDate = $today->format('Y-m-d');
                $this->endDate = $today->copy()->endOfWeek()->format('Y-m-d');
            } else {
                $this->transactionDate = $today->format('Y-m-d');
            }

            $this->checkAlreadySubmitted();
            $this->initCategoryInputs();
        }
    }

    /**
     * Load an existing DailyTransaction into edit mode.
     */
    private function loadEditMode(int $editId): void
    {
        $transaction = DailyTransaction::with('items.transactionCategory')
            ->where('business_unit_id', $this->unitId)
            ->findOrFail($editId);

        $this->editId = $editId;
        $this->isEditing = true;
        $this->transactionDate = $transaction->transaction_date->format('Y-m-d');
        $this->endDate = $transaction->end_date?->format('Y-m-d');

        // Build a lookup of existing detail values keyed by category_id
        $existingDetails = $transaction->items->keyBy('transaction_category_id');

        $this->initCategoryInputs();

        // Overlay existing values onto the initialised inputs
        foreach ($existingDetails as $categoryId => $detail) {
            if (! isset($this->inputs[$categoryId])) {
                continue;
            }

            $type = $this->categoryList->get($categoryId)?->type;

            if ($type === null) {
                continue;
            }

            if ($type->usesQuantity()) {
                $this->inputs[$categoryId]['quantity'] = $detail->quantity ?? '';
            } elseif ($type === CategoryType::Flat) {
                $this->inputs[$categoryId]['active'] = $detail->subtotal > 0;
            } elseif ($type === CategoryType::Custom) {
                $this->inputs[$categoryId]['amount'] = $detail->subtotal > 0 ? (string) (int) $detail->subtotal : '';
            }
        }

        $this->recalculateSubtotals();
    }

    public function updatedTransactionDate(): void
    {
        if ($this->isWeekly) {
            // For weekly units (TPS), the user picks a specific day within the week.
            // Keep their chosen date and derive endDate as the end of that week.
            $date = Carbon::parse($this->transactionDate);
            $this->endDate = $date->copy()->endOfWeek()->format('Y-m-d');
        }

        if (! $this->isEditing) {
            $this->checkAlreadySubmitted();
        }

        // Recalculate all subtotals since prices may differ on the new date
        $this->recalculateSubtotals();
    }

    public function updatedInputs()
    {
        $this->recalculateSubtotals();
    }

    private function checkAlreadySubmitted(): void
    {
        $this->alreadySubmitted = DailyTransaction::where('business_unit_id', $this->unitId)
            ->whereDate('transaction_date', $this->transactionDate)
            ->exists();
    }

    #[Computed]
    public function categoryList()
    {
        return TransactionCategory::where('business_unit_id', $this->unitId)
            ->where('direction', TransactionType::Income)
            ->get()->keyBy('id');
    }

    private function initCategoryInputs()
    {
        $this->inputs = [];
        $currentYear = Carbon::parse($this->transactionDate)->year;

        foreach ($this->categoryList as $category) {
            // Special rule for yearly categories: hide when already submitted this year,
            // except while editing (show every category from the original data).
            if (! $this->isEditing && $category->type === CategoryType::Yearly) {
                // Check whether it was already submitted in the current year
                $yearlyExists = TransactionItem::where('transaction_category_id', $category->id)
                    ->whereHas('dailyTransaction', function ($query) use ($currentYear) {
                        $query->whereYear('transaction_date', $currentYear);
                    })->exists();

                if ($yearlyExists) {
                    continue; // Hide when already submitted this year
                }
            }

            $this->inputs[$category->id] = [
                'type' => $category->type->value,
                'quantity' => '',
                'amount' => '',
                'active' => false,
                'subtotal' => 0,
            ];
        }
    }

    private function recalculateSubtotals()
    {
        $total = 0;
        $date = Carbon::parse($this->transactionDate);

        foreach ($this->inputs as $id => $input) {
            $category = $this->categoryList->get($id);
            if (! $category) {
                continue;
            }

            $subtotal = 0;

            if ($category->type->usesQuantity()) {
                $quantity = (int) ($input['quantity'] ?: 0);
                if ($quantity > 0) {
                    $unitPrice = $category->priceAt($date);
                    $subtotal = $quantity * $unitPrice;
                }
            } elseif ($category->type === CategoryType::Flat) {
                if ($input['active']) {
                    $subtotal = $category->priceAt($date);
                }
            } elseif ($category->type === CategoryType::Custom) {
                $subtotal = Rupiah::parse($input['amount'] ?? null);
            }

            $this->inputs[$id]['subtotal'] = $subtotal;
            $total += $subtotal;
        }

        $this->totalIncome = $total;
    }

    public function submit()
    {
        $validCategoryIds = $this->categoryList->keys()->map(fn ($id) => (int) $id)->all();

        // Base validation
        $this->validate([
            'transactionDate' => 'required|date',
            'totalIncome' => 'required|numeric|min:0|max:999999999999',
        ]);

        foreach ($this->inputs as $categoryId => $input) {
            if (! in_array((int) $categoryId, $validCategoryIds, true)) {
                unset($this->inputs[$categoryId]);

                continue;
            }

            $category = $this->categoryList->get($categoryId);
            $quantity = $input['quantity'] ?? '';
            $amount = $input['amount'] ?? '';

            if ($amount !== '') {
                $amount = Rupiah::parse($amount);
                $this->inputs[$categoryId]['amount'] = $amount;
            }

            if (! $category->type->usesQuantity() && $quantity !== '') {
                $this->addError("inputs.{$categoryId}.quantity", 'Kuantitas tidak valid.');
                $this->dispatch('swal-alert', icon: 'warning', title: 'Perhatian', text: 'Kuantitas tidak valid.');

                return;
            }

            if ($category->type !== CategoryType::Custom && $amount !== '') {
                $this->addError("inputs.{$categoryId}.amount", 'Nominal tidak valid.');
                $this->dispatch('swal-alert', icon: 'warning', title: 'Perhatian', text: 'Nominal tidak valid.');

                return;
            }

            if ($quantity !== '' && (! is_numeric($quantity) || (int) $quantity < 0 || (int) $quantity > 1000000)) {
                $this->addError("inputs.{$categoryId}.quantity", 'Kuantitas tidak valid.');
                $this->dispatch('swal-alert', icon: 'warning', title: 'Perhatian', text: 'Kuantitas tidak valid.');

                return;
            }

            if ($amount !== '' && (! is_numeric($amount) || (float) $amount < 0 || (float) $amount > 999999999999)) {
                $this->addError("inputs.{$categoryId}.amount", 'Nominal tidak valid.');
                $this->dispatch('swal-alert', icon: 'warning', title: 'Perhatian', text: 'Nominal tidak valid.');

                return;
            }
        }

        $this->recalculateSubtotals();

        if ($this->totalIncome <= 0) {
            $this->addError('totalIncome', 'Total pemasukan tidak boleh nol. Silakan isi minimal satu transaksi.');
            $this->dispatch('swal-alert', icon: 'warning', title: 'Perhatian', text: 'Total pemasukan tidak boleh nol.');

            return;
        }

        if (! $this->isEditing) {
            $this->checkAlreadySubmitted();
            if ($this->alreadySubmitted) {
                $this->showDuplicateError = true;
                $this->dispatch('swal-alert', icon: 'warning', title: 'Perhatian', text: 'Transaksi untuk tanggal ini sudah pernah disimpan.');

                return;
            }
        }

        if ($this->isEditing && $this->editId) {
            return $this->executeUpdate();
        } else {
            return $this->executeCreate();
        }
    }

    private function executeCreate()
    {
        DB::beginTransaction();

        try {
            $transaction = DailyTransaction::create([
                'business_unit_id' => $this->unitId,
                'user_id' => Auth::id(),
                'transaction_date' => $this->transactionDate,
                'end_date' => $this->endDate,
                'total_income' => $this->totalIncome,
            ]);

            $date = Carbon::parse($this->transactionDate);

            foreach ($this->inputs as $id => $input) {
                $subtotal = $input['subtotal'];
                if ($subtotal > 0) {
                    $category = $this->categoryList->get($id);
                    $unitPrice = 0;
                    $quantity = null;

                    if ($category->type->usesQuantity()) {
                        $quantity = (int) $input['quantity'];
                        $unitPrice = $category->priceAt($date);
                    } elseif ($category->type === CategoryType::Flat) {
                        $unitPrice = $category->priceAt($date);
                    }

                    TransactionItem::create([
                        'daily_transaction_id' => $transaction->id,
                        'transaction_category_id' => $id,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'subtotal' => $subtotal,
                    ]);
                }
            }

            $this->createJournalEntries($transaction, $date);

            DB::commit();

            $this->dispatch('swal-alert', icon: 'success', title: 'Berhasil', text: 'Transaksi berhasil disimpan!');
            $this->dispatch('swal-redirect', url: route('unit.record-transaction'));

        } catch (QueryException $e) {
            DB::rollBack();
            report($e);
            $this->dispatch('swal-alert', icon: 'warning', title: 'Perhatian', text: 'Transaksi untuk tanggal ini sudah pernah disimpan.');
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            $this->dispatch('swal-alert', icon: 'error', title: 'Gagal menyimpan', text: 'Terjadi kesalahan saat menyimpan transaksi.');
        }
    }

    private function executeUpdate()
    {
        DB::beginTransaction();

        try {
            $transaction = DailyTransaction::where('business_unit_id', $this->unitId)
                ->findOrFail($this->editId);

            $date = Carbon::parse($this->transactionDate);
            $oldMonth = $transaction->transaction_date->format('Y-m');

            // Update header
            $transaction->update([
                'transaction_date' => $this->transactionDate,
                'end_date' => $this->endDate,
                'total_income' => $this->totalIncome,
                'user_id' => Auth::id(),
            ]);

            // Delete old details and regenerate
            $transaction->items()->delete();

            foreach ($this->inputs as $id => $input) {
                $subtotal = $input['subtotal'];
                if ($subtotal > 0) {
                    $category = $this->categoryList->get($id);
                    $unitPrice = 0;
                    $quantity = null;

                    if ($category->type->usesQuantity()) {
                        $quantity = (int) $input['quantity'];
                        $unitPrice = $category->priceAt($date);
                    } elseif ($category->type === CategoryType::Flat) {
                        $unitPrice = $category->priceAt($date);
                    }

                    TransactionItem::create([
                        'daily_transaction_id' => $transaction->id,
                        'transaction_category_id' => $id,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'subtotal' => $subtotal,
                    ]);
                }
            }

            // Regenerate income journals only. Expense vouchers (K prefix)
            // linked to this header must survive an income edit; wiping them
            // orphans the header expense total from the ledger.
            JournalEntry::where('daily_transaction_id', $transaction->id)
                ->where('voucher_number', 'like', 'D%')
                ->delete();
            $this->createJournalEntries($transaction, $date);

            // Regenerating in place frees the edited voucher's old number and
            // shifts the later ones, leaving a gap (e.g. DBS001, DBS003).
            // Always renumber the touched scope(s) so 001..N stays contiguous.
            $unitCode = strtoupper($this->unit->code ?? 'XX');
            $newMonth = $date->format('Y-m');
            VoucherNumber::renumberScope('D'.$unitCode, $newMonth, $this->unitId);
            if ($newMonth !== $oldMonth) {
                VoucherNumber::renumberScope('D'.$unitCode, $oldMonth, $this->unitId);
            }

            DB::commit();

            $this->dispatch('swal-alert', icon: 'success', title: 'Berhasil', text: 'Transaksi berhasil diperbarui!');
            $this->dispatch('swal-redirect', url: route('history-recap'));

        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            $this->dispatch('swal-alert', icon: 'error', title: 'Gagal memperbarui', text: 'Terjadi kesalahan saat memperbarui transaksi.');
        }
    }

    /**
     * Create JournalEntry entries for a DailyTransaction.
     *
     * @throws \Exception
     */
    private function createJournalEntries(DailyTransaction $transaction, Carbon $date): void
    {
        $cashAccount = Account::where('code', '1-1100')->first();
        if (! $cashAccount) {
            throw new \Exception('Akun Kas (1-1100) tidak ditemukan di sistem. Harap hubungi administrator.');
        }

        // Prefix: 'D' (Income) + business unit code
        $unitCode = strtoupper($this->unit->code ?? 'XX');
        $numberPrefix = 'D'.$unitCode;

        // Chronological insert-and-shift within the prefix+month+unit scope.
        $voucherNumber = VoucherNumber::next($numberPrefix, $date->format('Y-m-d'), $this->unitId)['number'];
        $journalDescription = 'Pemasukan Harian - '.$this->unit->name;

        // 1. Record Debit to Cash account
        JournalEntry::create([
            'voucher_number' => $voucherNumber,
            'transaction_date' => $this->transactionDate,
            'description' => $journalDescription,
            'account_id' => $cashAccount->id,
            'debit' => $this->totalIncome,
            'credit' => 0,
            'daily_transaction_id' => $transaction->id,
            'business_unit_id' => $this->unitId,
        ]);

        // 2. Group Credits per account_id from submitted inputs
        $creditGroups = [];
        foreach ($this->inputs as $id => $input) {
            $subtotal = $input['subtotal'];
            if ($subtotal > 0) {
                $category = $this->categoryList->get($id);
                $accountId = $category->account_id;
                if (! $accountId) {
                    throw new \Exception('Kategori "'.$category->name.'" belum terhubung ke Kode Akun (Chart of Account).');
                }

                if (! isset($creditGroups[$accountId])) {
                    $creditGroups[$accountId] = 0;
                }
                $creditGroups[$accountId] += $subtotal;
            }
        }

        // 3. Record Credit for each corresponding revenue account
        foreach ($creditGroups as $accountId => $creditAmount) {
            JournalEntry::create([
                'voucher_number' => $voucherNumber,
                'transaction_date' => $this->transactionDate,
                'description' => $journalDescription,
                'account_id' => $accountId,
                'debit' => 0,
                'credit' => $creditAmount,
                'daily_transaction_id' => $transaction->id,
                'business_unit_id' => $this->unitId,
            ]);
        }
    }

    public function render()
    {
        return view('livewire.unit-head.record-daily-transaction');
    }
}
