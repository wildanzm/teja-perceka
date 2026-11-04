<div class="flex flex-col gap-6 max-w-full mx-auto w-full pb-20"
    x-data
    x-effect="document.body.style.overflow = ($wire.showCreateModal || $wire.showEditModal || $wire.showDeleteModal) ? 'hidden' : ''">

    <x-page-header title="Catat Pengeluaran" description="Riwayat pengeluaran operasional BUMDes.">
        <x-slot:actions>
            <button type="button" wire:click="openCreateModal"
            class="flex items-center justify-center gap-1.5 text-sm font-semibold text-white bg-brand-600 hover:bg-brand-700 px-4 py-2.5 rounded-xl transition-all shadow-sm shrink-0">
            <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Tambah Pengeluaran
        </button>
        </x-slot:actions>
    </x-page-header>

    {{-- Global error --}}
    @error('items')
        <div class="p-4 bg-red-50 border border-red-200 rounded-2xl text-sm text-red-700">
            {{ $message }}
        </div>
    @enderror

    {{-- Expense history (expense records) --}}
    <div class="bg-white rounded-2xl shadow-sm border border-zinc-200 overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-zinc-100 bg-zinc-50">
            <div class="flex flex-col gap-4">
                {{-- Title row --}}
                <div class="flex items-center gap-2">
                    <svg class="size-5 text-zinc-500 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none"
                        viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                    </svg>
                    <h2 class="text-base font-semibold text-zinc-800">Riwayat Pengeluaran</h2>
                </div>

                {{-- Filter controls --}}
                <div
                    class="flex flex-col md:flex-row gap-5 md:items-end p-4 bg-white rounded-xl shadow-sm border border-brand-100">

                    {{-- Mode selector --}}
                    <div class="flex flex-col gap-1.5 w-full md:w-auto">
                        <label for="filterMode" class="text-xs font-semibold text-zinc-600">Periode</label>
                        <select id="filterMode" wire:model.live.debounce.250ms="filterMode"
                            class="w-full sm:min-w-40 rounded-xl border-2 border-zinc-200 text-zinc-900 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 focus:outline-none transition-colors">
                            <option value="daily">Harian</option>
                            <option value="monthly">Bulanan</option>
                            <option value="semester">Semester</option>
                            <option value="yearly">Tahunan</option>
                        </select>
                    </div>

                    {{-- Date/period picker based on filterMode --}}
                    <div class="flex flex-col gap-1.5 w-full md:w-auto md:min-w-48">
                        {{-- Daily --}}
                        @if ($filterMode === 'daily')
                            <label for="filterDate" class="text-xs font-semibold text-zinc-600">Tanggal</label>
                            <div wire:ignore wire:key="picker-harian" x-data="{ val: $wire.entangle('filterDate').live }">
                                <input type="text" x-model="val" x-init="window.flatpickr($el, {
                                    locale: window.flatpickrIndonesian,
                                    dateFormat: 'Y-m-d',
                                    defaultDate: val,
                                    altInput: true,
                                    altFormat: 'd F Y',
                                    disableMobile: true
                                })"
                                    class="w-full rounded-xl border-2 border-zinc-200 text-zinc-900 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 focus:outline-none transition-colors cursor-pointer" />
                            </div>

                            {{-- Monthly --}}
                        @elseif($filterMode === 'monthly')
                            <label for="filterMonth" class="text-xs font-semibold text-zinc-600">Bulan</label>
                            <div wire:ignore wire:key="picker-bulanan" x-data="{ val: $wire.entangle('filterMonth').live }">
                                <input type="text" x-model="val" x-init="window.flatpickr($el, {
                                    locale: window.flatpickrIndonesian,
                                    plugins: [
                                        new window.flatpickrMonthSelect({
                                            shorthand: false,
                                            dateFormat: 'Y-m',
                                            altFormat: 'F Y',
                                            theme: 'light'
                                        })
                                    ],
                                    defaultDate: val,
                                    altInput: true,
                                    disableMobile: true
                                })"
                                    class="w-full rounded-xl border-2 border-zinc-200 text-zinc-900 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 focus:outline-none transition-colors cursor-pointer" />
                            </div>

                            {{-- Semesterly --}}
                        @elseif($filterMode === 'semester')
                            <div wire:key="picker-semester" class="flex flex-col sm:flex-row gap-5">
                                <div class="flex flex-col gap-1.5 w-full sm:w-auto">
                                    <label for="filterSemester" class="text-xs font-semibold text-zinc-600">Semester</label>
                                    <select wire:model.live.debounce.250ms="filterSemester"
                                        class="w-full sm:min-w-40 rounded-xl border-2 border-zinc-200 text-zinc-900 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 focus:outline-none transition-colors">
                                        <option value="1">1 (Jan-Jun)</option>
                                        <option value="2">2 (Jul-Des)</option>
                                    </select>
                                </div>
                                <div class="flex flex-col gap-1.5 w-full sm:w-auto">
                                    <label for="filterSemesterYear" class="text-xs font-semibold text-zinc-600">Tahun</label>
                                    <select wire:model.live.debounce.500ms="filterSemesterYear"
                                        class="w-full sm:min-w-32 rounded-xl border-2 border-zinc-200 text-zinc-900 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 focus:outline-none transition-colors">
                                        @for ($y = date('Y'); $y >= date('Y') - 5; $y--)
                                            <option value="{{ $y }}">{{ $y }}</option>
                                        @endfor
                                    </select>
                                </div>
                            </div>

                            {{-- Yearly --}}
                        @elseif($filterMode === 'yearly')
                            <div wire:key="picker-tahunan">
                                <label for="filterYear" class="text-xs font-semibold text-zinc-600">Tahun</label>
                                <select wire:model.live.debounce.500ms="filterYear"
                                    class="w-full sm:min-w-40 rounded-xl border-2 border-zinc-200 text-zinc-900 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 focus:outline-none transition-colors">
                                    @for ($y = date('Y'); $y >= date('Y') - 5; $y--)
                                        <option value="{{ $y }}">{{ $y }}</option>
                                    @endfor
                                </select>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto hidden md:block">
            <table class="w-full">
                <thead class="bg-zinc-50 border-b border-zinc-100">
                    <tr>
                        <th class="py-3 px-4 text-left text-xs font-bold text-zinc-600 uppercase tracking-wider">Tanggal
                        </th>
                        <th class="py-3 px-4 text-left text-xs font-bold text-zinc-600 uppercase tracking-wider">No.
                            Bukti</th>
                        <th class="py-3 px-4 text-left text-xs font-bold text-zinc-600 uppercase tracking-wider">
                            Keterangan</th>
                        <th class="py-3 px-4 text-left text-xs font-bold text-zinc-600 uppercase tracking-wider">Akun
                        </th>
                        <th class="py-3 px-4 text-right text-xs font-bold text-zinc-600 uppercase tracking-wider">
                            Nominal</th>
                        <th class="py-3 px-4 text-center text-xs font-bold text-zinc-600 uppercase tracking-wider">Aksi
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @forelse($history as $journal)
                        <tr class="hover:bg-zinc-50 transition-colors">
                            <td class="py-3.5 px-4 whitespace-nowrap text-sm text-zinc-700">
                                {{ \Carbon\Carbon::parse($journal->transaction_date)->translatedFormat('d M Y') }}
                            </td>
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <span
                                    class="text-xs font-semibold text-zinc-600">{{ $journal->voucher_number }}</span>
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="text-sm text-zinc-700 line-clamp-2">{{ $journal->description }}</span>
                            </td>
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="px-2 py-1 rounded-md text-xs font-bold bg-brand-50 text-brand-700 border border-brand-100">
                                        {{ $journal->account->code }}
                                    </span>
                                    <span class="text-sm text-zinc-600 truncate max-w-[160px]"
                                        title="{{ $journal->account->name }}">
                                        {{ $journal->account->name }}
                                    </span>
                                </div>
                            </td>
                            <td class="py-3.5 px-4 whitespace-nowrap text-right">
                                <span class="text-sm font-bold text-red-600">Rp
                                    {{ number_format($journal->debit, 0, ',', '.') }}</span>
                            </td>
                            <td class="py-3.5 px-4 whitespace-nowrap text-center">
                                <div class="flex flex-row justify-center gap-1.5 items-center">
                                    <button wire:click="editHistory({{ $journal->id }})"
                                        type="button"
                                        style="background-color:#fbbf24;color:#1c1917;"
                                        title="Edit"
                                        class="inline-flex items-center justify-center size-7 rounded-lg transition-colors hover:opacity-90">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-3.5">
                                            <path d="M13.488 2.513a1.75 1.75 0 0 0-2.475 0L6.75 6.774a2.75 2.75 0 0 0-.596.892l-.848 2.047a.75.75 0 0 0 .98.98l2.047-.848a2.75 2.75 0 0 0 .892-.596l4.261-4.263a1.75 1.75 0 0 0 0-2.474Z" />
                                            <path d="M4.75 3.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h6.5c.69 0 1.25-.56 1.25-1.25V9A.75.75 0 0 1 13 9v2.25A2.75 2.75 0 0 1 10.25 14h-6.5A2.75 2.75 0 0 1 1 11.25v-6.5A2.75 2.75 0 0 1 3.75 2H6a.75.75 0 0 1 0 1.5H3.75Z" />
                                        </svg>
                                    </button>
                                    <flux:button wire:click="confirmDelete({{ $journal->id }})"
                                        variant="danger" size="xs" icon="trash" title="Hapus" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center text-zinc-500 text-sm">
                                <svg class="size-10 text-zinc-300 mx-auto" xmlns="http://www.w3.org/2000/svg"
                                    fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <p class="mt-2">Belum ada catatan pengeluaran pada periode ini.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($historyTotal > 0)
                    <tfoot>
                        <tr class="bg-brand-50/40 border-t-2 border-brand-200">
                            <td colspan="4" class="py-3.5 px-4 text-right text-sm font-bold text-zinc-700">Total
                                Pengeluaran</td>
                            <td class="py-3.5 px-4 text-right text-base font-extrabold text-red-600 whitespace-nowrap">
                                Rp {{ number_format($historyTotal, 0, ',', '.') }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        {{-- Mobile cards --}}
        <div class="md:hidden divide-y divide-zinc-100">
            @forelse($history as $journal)
                <div class="p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-zinc-500">{{ \Carbon\Carbon::parse($journal->transaction_date)->translatedFormat('d M Y') }}</span>
                        <span class="text-xs text-zinc-500">{{ $journal->voucher_number }}</span>
                    </div>
                    <p class="text-sm text-zinc-700">{{ $journal->description }}</p>
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded-md text-xs font-bold bg-brand-50 text-brand-700 border border-brand-100">
                            {{ $journal->account->code }}
                        </span>
                        <span class="text-xs text-zinc-600 truncate">{{ $journal->account->name }}</span>
                    </div>
                    <div class="flex items-center justify-between pt-2">
                        <span class="text-sm font-bold text-red-600">Rp {{ number_format($journal->debit, 0, ',', '.') }}</span>
                        <div class="flex items-center gap-1.5">
                            <button wire:click="editHistory({{ $journal->id }})"
                                type="button"
                                style="background-color:#fbbf24;color:#1c1917;"
                                title="Edit"
                                class="inline-flex items-center justify-center size-7 rounded-lg transition-colors hover:opacity-90">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-3.5">
                                    <path d="M13.488 2.513a1.75 1.75 0 0 0-2.475 0L6.75 6.774a2.75 2.75 0 0 0-.596.892l-.848 2.047a.75.75 0 0 0 .98.98l2.047-.848a2.75 2.75 0 0 0 .892-.596l4.261-4.263a1.75 1.75 0 0 0 0-2.474Z" />
                                    <path d="M4.75 3.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h6.5c.69 0 1.25-.56 1.25-1.25V9A.75.75 0 0 1 13 9v2.25A2.75 2.75 0 0 1 10.25 14h-6.5A2.75 2.75 0 0 1 1 11.25v-6.5A2.75 2.75 0 0 1 3.75 2H6a.75.75 0 0 1 0 1.5H3.75Z" />
                                </svg>
                            </button>
                            <flux:button wire:click="confirmDelete({{ $journal->id }})"
                                variant="danger" size="xs" icon="trash" title="Hapus" />
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-12 text-center text-zinc-500 text-sm">
                    Belum ada catatan pengeluaran pada periode ini.
                </div>
            @endforelse
        </div>

        {{-- Mobile total --}}
        @if ($historyTotal > 0)
            <div
                class="md:hidden bg-brand-50 border-t border-brand-200 p-4 flex items-center justify-between">
                <span class="text-sm font-bold text-zinc-700">Total Pengeluaran</span>
                <span class="text-base font-extrabold text-red-600">Rp
                    {{ number_format($historyTotal, 0, ',', '.') }}</span>
            </div>
        @endif
    </div>

    {{-- ============================ CREATE MODAL ============================ --}}
    @if ($showCreateModal)
        <div
            class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="create-modal-title">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" wire:click="closeCreateModal" aria-hidden="true"></div>
                <div class="relative w-full max-w-lg bg-white rounded-lg shadow-xl">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-zinc-100 bg-zinc-50 rounded-t-lg">
                        <h3 id="create-modal-title" class="text-lg font-semibold text-zinc-900">Tambah Pengeluaran</h3>
                        <button type="button" wire:click="closeCreateModal"
                            class="p-1.5 text-zinc-400 hover:text-zinc-600 rounded-lg transition-colors"
                            aria-label="Tutup modal">
                            <svg class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <form wire:submit.prevent="submit" class="p-6 space-y-5">
                        @error('items')
                            <div class="p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">
                                {{ $message }}
                            </div>
                        @enderror

                        <div>
                            <label for="transaction_date" class="block text-sm font-medium text-zinc-700 mb-1.5">Tanggal</label>
                            <div x-data="{
                                    value: $wire.entangle('transactionDate'),
                                    fp: null,
                                    init() {
                                        this.fp = flatpickr(this.$refs.dateInput, {
                                            dateFormat: 'Y-m-d',
                                            locale: window.flatpickrIndonesian,
                                            altInput: true,
                                            altFormat: 'd F Y',
                                            defaultDate: this.value ? this.value : new Date(),
                                            onChange: (selectedDates, dateStr) => {
                                                if (selectedDates.length > 0) {
                                                    this.value = window.flatpickr.formatDate(selectedDates[0], 'Y-m-d');
                                                } else {
                                                    this.value = null;
                                                }
                                            }
                                        });
                                        this.$watch('value', (val) => {
                                            if (val && this.fp) {
                                                this.fp.setDate(val, false);
                                            } else if (!val && this.fp) {
                                                this.fp.clear();
                                            }
                                        });
                                    }
                                }" wire:ignore wire:key="create-datepicker-{{ $showCreateModal ? 'on' : 'off' }}">
                                <input type="text" id="transaction_date" placeholder="Pilih tanggal"
                                    x-ref="dateInput"
                                    class="w-full rounded-xl border-2 border-zinc-200 text-zinc-900 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 focus:outline-none transition-colors" />
                            </div>
                            @error('transaction_date')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-3">
                            @foreach($items as $index => $item)
                                <div class="p-3 border border-zinc-200 rounded-xl space-y-3 bg-zinc-50/50">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-bold text-zinc-600 uppercase">Item {{ $index + 1 }}</span>
                                        @if(count($items) > 1)
                                            <button type="button" wire:click="removeItem({{ $index }})"
                                                class="p-1 text-zinc-400 hover:text-red-500 rounded transition-colors"
                                                aria-label="Hapus item">
                                                <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        @endif
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-zinc-700 mb-1.5">Jenis Biaya</label>
                                        <div x-data="{
                                            open: false,
                                            selectedId: @entangle('items.'.$index.'.account_id'),
                                            options: [
                                                @foreach($expenseAccounts as $account)
                                                { id: '{{ $account->id }}', label: '{{ $account->code }} - {{ $account->name }}' },
                                                @endforeach
                                            ],
                                            get selectedLabel() {
                                                const selected = this.options.find(o => o.id == this.selectedId);
                                                return selected ? selected.label : 'Pilih jenis biaya';
                                            }
                                        }" class="relative">
                                            <button type="button" @click="open = !open" @click.outside="open = false"
                                                class="w-full flex items-center justify-between rounded-xl border-2 border-zinc-200 bg-white px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 focus:outline-none transition-colors"
                                                :class="selectedId ? 'text-zinc-900' : 'text-zinc-500'">
                                                <span x-text="selectedLabel" class="truncate"></span>
                                                <svg class="size-4 text-zinc-400 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                            </button>
                                            <div x-show="open" x-transition style="display: none;"
                                                class="absolute z-10 w-full mt-1 bg-white border border-zinc-200 rounded-xl shadow-lg max-h-48 overflow-y-auto">
                                                <ul class="py-1">
                                                    <template x-for="option in options" :key="option.id">
                                                        <li>
                                                            <button type="button" @click="selectedId = option.id; open = false"
                                                                class="w-full text-left px-4 py-2 text-sm hover:bg-brand-50 hover:text-brand-700 transition-colors"
                                                                :class="selectedId == option.id ? 'bg-brand-50 text-brand-700 font-medium' : 'text-zinc-700'"
                                                                x-text="option.label"></button>
                                                        </li>
                                                    </template>
                                                </ul>
                                            </div>
                                            <select wire:model="items.{{ $index }}.account_id" class="hidden" required>
                                                <option value="">Pilih</option>
                                                @foreach($expenseAccounts as $account)
                                                    <option value="{{ $account->id }}">{{ $account->code }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        @error("items.{$index}.account_id")
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-zinc-700 mb-1.5">Nominal</label>
                                        <div class="relative">
                                            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-sm text-zinc-500 font-medium pointer-events-none">Rp</span>
                                            <input type="text" inputmode="numeric" data-rupiah id="nominal-{{ $index }}" wire:model.live.debounce.300ms="items.{{ $index }}.amount" placeholder="0"
                                                class="w-full rounded-xl border-2 border-zinc-200 text-zinc-900 pl-10 pr-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 focus:outline-none transition-colors"
                                                required />
                                        </div>
                                        @error("items.{$index}.amount")
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-zinc-700 mb-1.5">Keterangan</label>
                                        <textarea wire:model="items.{{ $index }}.description" rows="2"
                                            placeholder="Masukkan keterangan pengeluaran"
                                            class="w-full rounded-xl border-2 border-zinc-200 text-zinc-900 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 focus:outline-none transition-colors resize-none"
                                            required></textarea>
                                        @error("items.{$index}.description")
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <button type="button" wire:click="addItem"
                            class="w-full sm:w-auto flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-brand-600 bg-brand-50 border border-brand-200 rounded-xl hover:bg-brand-100 transition-colors">
                            <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            Tambah Item
                        </button>

                        <div class="pt-4 border-t border-zinc-100 space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-zinc-700">Total</span>
                                <span class="text-lg font-extrabold text-red-600">Rp {{ number_format($totalExpense, 0, ',', '.') }}</span>
                            </div>
                            <div class="flex flex-col sm:flex-row gap-3 pt-2">
                                <button type="button" wire:click="closeCreateModal"
                                    class="flex-1 sm:flex-none px-5 py-2.5 text-sm font-semibold text-zinc-700 bg-white border border-zinc-300 rounded-xl hover:bg-zinc-50 transition-colors">
                                    Batal
                                </button>
                                <button type="submit"
                                    class="flex-1 sm:flex-none px-5 py-2.5 text-sm font-semibold text-white bg-brand-600 hover:bg-brand-700 rounded-xl transition-colors shadow-sm">
                                    Simpan Pengeluaran
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- ============================ EDIT MODAL ============================ --}}
    @if ($showEditModal)
        <div
            class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="edit-modal-title">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" wire:click="closeEditModal" aria-hidden="true"></div>
                <div class="relative w-full max-w-lg bg-white rounded-lg shadow-xl">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-zinc-100 bg-zinc-50 rounded-t-lg">
                        <h3 id="edit-modal-title" class="text-lg font-semibold text-zinc-900">Edit Pengeluaran</h3>
                        <button type="button" wire:click="closeEditModal"
                            class="p-1.5 text-zinc-400 hover:text-zinc-600 rounded-lg transition-colors"
                            aria-label="Tutup modal">
                            <svg class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <form wire:submit.prevent="updateHistory" class="p-6 space-y-5">
                        @error('editAccountId')
                            <div class="p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">{{ $message }}</div>
                        @enderror
                        @error('editDescription')
                            <div class="p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">{{ $message }}</div>
                        @enderror
                        @error('editAmount')
                            <div class="p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">{{ $message }}</div>
                        @enderror
                        @error('editDate')
                            <div class="p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">{{ $message }}</div>
                        @enderror

                        <div>
                            <label for="editDate" class="block text-sm font-medium text-zinc-700 mb-1.5">Tanggal</label>
                            <div x-data="{
                                    value: $wire.entangle('editDate'),
                                    fp: null,
                                    init() {
                                        this.fp = flatpickr(this.$refs.dateInput, {
                                            dateFormat: 'Y-m-d',
                                            locale: window.flatpickrIndonesian,
                                            altInput: true,
                                            altFormat: 'd F Y',
                                            defaultDate: this.value ? this.value : new Date(),
                                            onChange: (selectedDates, dateStr) => {
                                                if (selectedDates.length > 0) {
                                                    this.value = window.flatpickr.formatDate(selectedDates[0], 'Y-m-d');
                                                } else {
                                                    this.value = null;
                                                }
                                            }
                                        });
                                        this.$watch('value', (val) => {
                                            if (val && this.fp) {
                                                this.fp.setDate(val, false);
                                            } else if (!val && this.fp) {
                                                this.fp.clear();
                                            }
                                        });
                                    }
                                }" wire:ignore wire:key="edit-datepicker-{{ $showEditModal ? 'on' : 'off' }}">
                                <input type="text" id="editDate" placeholder="Pilih tanggal"
                                    x-ref="dateInput"
                                    class="w-full rounded-xl border-2 border-zinc-200 text-zinc-900 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 focus:outline-none transition-colors" />
                            </div>
                        </div>
                        <div>
                            <label for="editAccountId" class="block text-sm font-medium text-zinc-700 mb-1.5">Jenis Biaya</label>
                            <div x-data="{
                                open: false,
                                selectedId: @entangle('editAccountId'),
                                options: [
                                    @foreach($expenseAccounts as $account)
                                    { id: '{{ $account->id }}', label: '{{ $account->code }} - {{ $account->name }}' },
                                    @endforeach
                                ],
                                get selectedLabel() {
                                    const selected = this.options.find(o => o.id == this.selectedId);
                                    return selected ? selected.label : 'Pilih jenis biaya';
                                }
                            }" class="relative">
                                <button type="button" @click="open = !open" @click.outside="open = false"
                                    class="w-full flex items-center justify-between rounded-xl border-2 border-zinc-200 bg-white px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 focus:outline-none transition-colors"
                                    :class="selectedId ? 'text-zinc-900' : 'text-zinc-500'">
                                    <span x-text="selectedLabel" class="truncate"></span>
                                    <svg class="size-4 text-zinc-400 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <div x-show="open" x-transition style="display: none;"
                                    class="absolute z-10 w-full mt-1 bg-white border border-zinc-200 rounded-xl shadow-lg max-h-48 overflow-y-auto">
                                    <ul class="py-1">
                                        <template x-for="option in options" :key="option.id">
                                            <li>
                                                <button type="button" @click="selectedId = option.id; open = false"
                                                    class="w-full text-left px-4 py-2 text-sm hover:bg-brand-50 hover:text-brand-700 transition-colors"
                                                    :class="selectedId == option.id ? 'bg-brand-50 text-brand-700 font-medium' : 'text-zinc-700'"
                                                    x-text="option.label"></button>
                                            </li>
                                        </template>
                                    </ul>
                                </div>
                                <select wire:model="editAccountId" id="editAccountId" class="hidden" required>
                                    <option value="">Pilih</option>
                                    @foreach($expenseAccounts as $account)
                                        <option value="{{ $account->id }}">{{ $account->code }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div>
                            <label for="editDescription" class="block text-sm font-medium text-zinc-700 mb-1.5">Keterangan</label>
                            <textarea wire:model="editDescription" id="editDescription" rows="3"
                                placeholder="Masukkan keterangan pengeluaran"
                                class="w-full rounded-xl border-2 border-zinc-200 text-zinc-900 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 focus:outline-none transition-colors resize-none"
                                required></textarea>
                        </div>
                        <div>
                            <label for="editAmount" class="block text-sm font-medium text-zinc-700 mb-1.5">Nominal</label>
                            <div class="relative">
                                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-sm text-zinc-500 font-medium pointer-events-none">Rp</span>
                                <input type="text" inputmode="numeric" data-rupiah id="editAmount" wire:model.live.debounce.300ms="editAmount" placeholder="0"
                                    class="w-full rounded-xl border-2 border-zinc-200 text-zinc-900 pl-10 pr-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 focus:outline-none transition-colors"
                                    required />
                            </div>
                        </div>
                        <div class="pt-4 border-t border-zinc-100 flex flex-col sm:flex-row gap-3">
                            <button type="button" wire:click="closeEditModal"
                                class="flex-1 sm:flex-none px-5 py-2.5 text-sm font-semibold text-zinc-700 bg-white border border-zinc-300 rounded-xl hover:bg-zinc-50 transition-colors">
                                Batal
                            </button>
                            <button type="submit"
                                class="flex-1 sm:flex-none px-5 py-2.5 text-sm font-semibold text-white bg-brand-600 hover:bg-brand-700 rounded-xl transition-colors shadow-sm">
                                Perbarui
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- ============================ DELETE MODAL ============================ --}}
    @if ($showDeleteModal)
        <div
            class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="delete-modal-title">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" wire:click="closeDeleteModal" aria-hidden="true"></div>
                <div class="relative w-full max-w-md bg-white rounded-lg shadow-xl">
                    <div class="px-6 py-6 text-center">
                        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-red-100 text-red-600">
                            <svg class="size-7" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                            </svg>
                        </div>
                        <h3 id="delete-modal-title" class="text-lg font-semibold text-zinc-900 mb-2">Hapus Pengeluaran?</h3>
                        <p class="text-zinc-600 text-sm">
                            Apakah Anda yakin ingin menghapus data pengeluaran ini? Tindakan ini tidak dapat dibatalkan.
                        </p>
                    </div>
                    <div class="px-6 py-4 bg-zinc-50 border-t border-zinc-100 flex flex-col-reverse sm:flex-row gap-3 sm:justify-end rounded-b-lg">
                        <button type="button" wire:click="closeDeleteModal"
                            class="flex-1 sm:flex-none px-5 py-2.5 text-sm font-semibold text-zinc-700 bg-white border border-zinc-300 rounded-xl hover:bg-zinc-50 transition-colors">
                            Batal
                        </button>
                        <button type="button" wire:click="executeDelete"
                            class="flex-1 sm:flex-none px-5 py-2.5 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 rounded-xl transition-colors shadow-sm">
                            Hapus
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

</div>
