<?php

namespace App\Livewire\Transactions;

use App\Models\BusinessUnit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Riwayat & Rekap')]
class HistoryRecap extends Component
{
    #[Url]
    public string $tab = 'revenue'; // 'revenue' or 'journal'

    public $unit_id = null;

    #[Url(as: 'periode')]
    public string $mode = 'daily';

    #[Url]
    public string $transactionDate = '';

    #[Url]
    public string $week = '';

    #[Url]
    public string $month = '';

    #[Url]
    public string $semester = '1';

    #[Url]
    public string $semesterYear = '';

    #[Url]
    public string $year = '';

    public string $sortField = 'transaction_date';

    public string $sortDirection = 'asc';

    public string $sortOption = 'date-asc';

    /** Journal tab view: 'summary' (aggregated) or 'detailed' (per voucher). */
    public string $viewMode = 'detailed';

    /** Journal tab free-text filter. */
    public string $search = '';

    public function updatedSortOption(): void
    {
        [$field, $direction] = array_pad(explode('-', $this->sortOption, 2), 2, 'asc');

        if (! in_array($field, ['transaction_date', 'voucher_number'])) {
            $field = 'transaction_date';
        }

        $this->sortField = $field;
        $this->sortDirection = $direction === 'desc' ? 'desc' : 'asc';
        $this->sortOption = $field.'-'.$this->sortDirection;
    }

    public function sortJournalsBy(string $field): void
    {
        if (! in_array($field, ['transaction_date', 'voucher_number'])) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->sortOption = $this->sortField.'-'.$this->sortDirection;
    }

    public function mount(): void
    {
        $user = Auth::user();

        if ($user->hasRole('kepala_unit')) {
            $this->unit_id = $user->business_unit_id;
        } elseif (empty($this->unit_id)) {
            $this->unit_id = 'bumdes';
        }

        // Default all modes to current period
        $now = Carbon::now();
        $this->transactionDate = $now->format('Y-m-d');
        $this->week = $now->startOfWeek()->format('Y-m-d');
        $this->month = $now->format('Y-m');
        $this->semesterYear = $now->format('Y');
        $this->semester = $now->month <= 6 ? '1' : '2';
        $this->year = $now->format('Y');

        if (! in_array($this->tab, ['revenue', 'journal'])) {
            $this->tab = 'revenue';
        }
    }

    private function isWeeklyUnit(?BusinessUnit $unit): bool
    {
        return $unit && $unit->input_frequency === 'weekly';
    }

    #[Computed]
    public function units(): Collection
    {
        return BusinessUnit::orderBy('name')->get();
    }

    #[Computed]
    public function selectedUnit(): ?BusinessUnit
    {
        return ($this->unit_id && is_numeric($this->unit_id)) ? BusinessUnit::find($this->unit_id) : null;
    }

    public function updatedMode(): void
    {
        // No overrides needed
    }

    public function updatingUnitId($value): void
    {
        $user = Auth::user();
        if ($user && $user->hasRole('kepala_unit') && $value !== $user->business_unit_id) {
            abort(403, 'Unauthorized');
        }
    }

    public function updatedUnitId(): void
    {
        // No overrides needed
    }

    public function updatedTab(): void
    {
        if (! in_array($this->tab, ['revenue', 'journal'])) {
            $this->tab = 'revenue';
        }
    }

    public function render()
    {
        // Security Fallback: Ensure kepala_unit cannot manipulate unit_id state via browser
        $user = Auth::user();
        if ($user && $user->hasRole('kepala_unit') && $this->unit_id !== $user->business_unit_id) {
            $this->unit_id = $user->business_unit_id;
        }

        return view('livewire.transactions.history-recap');
    }
}
