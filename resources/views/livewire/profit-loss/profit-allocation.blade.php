<div class="flex h-full w-full flex-col gap-4 sm:gap-6 max-w-full mx-auto pb-10">

    <x-page-header title="Alokasi Laba" description="Hitung pembagian laba bersih (persentase & nominal) per periode dan sinkronkan alokasi otomatis ke buku besar." />

    {{-- Filter Panel --}}
    <div class="bg-white p-4 sm:p-6 rounded-xl border border-brand-100 shadow-sm">
        <div class="flex flex-col md:flex-row gap-4 sm:gap-6 md:items-end justify-between">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 flex-1">
                {{-- Period Mode --}}
                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-medium text-zinc-700">Mode Periode</label>
                    <select wire:model.live.debounce.250ms="mode"
                        class="block w-full max-w-full rounded-xl border-2 border-zinc-200 text-zinc-900 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 focus:outline-none transition-colors cursor-pointer">
                        <option value="monthly">Bulanan</option>
                        <option value="semester">Semester</option>
                        <option value="yearly">Tahunan</option>
                    </select>
                </div>

                {{-- Period Picker --}}
                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-medium text-zinc-700">
                        @if($mode === 'monthly') Bulan
                        @elseif($mode === 'semester') Semester
                        @else Tahun @endif
                    </label>
                    @if ($mode === 'monthly')
                        <div wire:ignore wire:key="picker-bulan-{{ $mode }}" x-data="{ val: $wire.entangle('period').live }">
                            <input type="text" x-model="val"
                                x-init="window.flatpickr($el, {
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
                                class="block w-full max-w-full rounded-xl border-2 border-zinc-200 text-zinc-900 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 focus:outline-none transition-colors cursor-pointer" />
                        </div>
                    @elseif ($mode === 'semester')
                        <div wire:key="picker-semester-{{ $mode }}" class="flex gap-2">
                            <select wire:model.live.debounce.250ms="semester"
                                class="block w-full max-w-full rounded-xl border-2 border-zinc-200 text-zinc-900 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 focus:outline-none transition-colors cursor-pointer">
                                <option value="1">Semester 1 (Jan - Jun)</option>
                                <option value="2">Semester 2 (Jul - Des)</option>
                            </select>
                            <input type="number" wire:model.live.debounce.500ms="semesterYear" min="2020" max="2099" placeholder="Tahun"
                                class="block w-28 shrink-0 max-w-full rounded-xl border-2 border-zinc-200 text-zinc-900 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 focus:outline-none transition-colors">
                        </div>
                    @else
                        <input wire:key="picker-tahun-{{ $mode }}" type="number" wire:model.live.debounce.500ms="period" min="2020" max="2099" placeholder="Pilih Tahun"
                            class="block w-full max-w-full rounded-xl border-2 border-zinc-200 text-zinc-900 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 focus:outline-none transition-colors">
                    @endif
                </div>
            </div>

            {{-- Allocation / Automatic Actions --}}
            @if($this->canEdit)
                <div class="flex items-center gap-2.5 shrink-0">
                    @if($this->currentClosing)
                        <div class="inline-flex items-center gap-2 bg-emerald-50 border border-emerald-200 text-emerald-800 px-3.5 py-2.5 rounded-xl text-xs font-semibold">
                            <span class="inline-block size-2 rounded-full bg-emerald-500"></span>
                            <span>Alokasi Sudah Ditetapkan</span>
                        </div>
                        <button type="button" wire:click="$set('showRollbackConfirmModal', true)"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-xl border border-red-200 bg-red-50 text-red-700 text-xs font-semibold hover:bg-red-100 transition-colors shadow-sm">
                            <svg class="size-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                            </svg>
                            Batalkan Alokasi
                        </button>
                    @else
                        <button type="button" wire:click="$set('showClosingConfirmModal', true)"
                            class="inline-flex items-center justify-center px-4 py-2.5 rounded-xl bg-brand-600 text-white text-xs font-semibold hover:bg-brand-700 transition-all shadow-sm active:scale-95">
                            Tetapkan Alokasi Laba
                        </button>
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        {{-- Total Net Income --}}
        <div class="relative overflow-hidden rounded-2xl border border-brand-200/80 bg-gradient-to-br from-brand-50 via-white to-white p-4 sm:p-5 shadow-sm transition-shadow hover:shadow-md">
            <div class="flex items-start justify-between gap-3">
                <span class="flex size-9 items-center justify-center rounded-xl bg-brand-600 text-white shadow-sm shadow-brand-600/30">
                    <svg class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a2.25 2.25 0 0 0-2.25-2.25H15a3 3 0 1 1-6 0H5.25A2.25 2.25 0 0 0 3 12m18 0v6a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 18v-6m18 0V9M3 12V9m18 0a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 9m18 0V6a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 6v3" />
                    </svg>
                </span>
                <span class="inline-flex items-center rounded-full bg-brand-100 px-2.5 py-1 text-[10px] font-semibold text-brand-700">
                    {{ $this->periodLabel() }}
                </span>
            </div>
            <p class="mt-4 text-[11px] font-semibold uppercase tracking-wider text-brand-700/80">Total Laba Bersih</p>
            <p class="mt-1 text-2xl font-bold tracking-tight text-brand-950 tabular-nums">
                Rp {{ number_format($this->netIncome, 0, ',', '.') }}
            </p>
            <p class="mt-2 text-[11px] font-medium text-brand-700/60">Periode berjalan</p>
        </div>

        {{-- Profit Distribution --}}
        <div class="relative overflow-hidden rounded-2xl border border-amber-200/80 bg-gradient-to-br from-amber-50 via-white to-white p-4 sm:p-5 shadow-sm transition-shadow hover:shadow-md">
            <div class="flex items-start justify-between gap-3">
                <span class="flex size-9 items-center justify-center rounded-xl bg-amber-50 text-amber-600 ring-1 ring-amber-200/70">
                    <svg class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5l15-15m0 0H8.25m11.25 0v11.25" />
                    </svg>
                </span>
                <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-[10px] font-semibold text-amber-700 ring-1 ring-amber-200/70">
                    Kas Keluar
                </span>
            </div>
            <p class="mt-4 text-[11px] font-semibold uppercase tracking-wider text-zinc-500">Penyaluran Laba</p>
            <p class="mt-1 text-2xl font-bold tracking-tight text-zinc-900 tabular-nums">
                Rp {{ number_format($this->totalOutflow, 0, ',', '.') }}
            </p>
            <p class="mt-2 text-[11px] text-zinc-500">PADes Desa, bagi hasil, pajak, sosial, bonus</p>
        </div>

        {{-- Capital Reserve --}}
        <div class="relative overflow-hidden rounded-2xl border border-emerald-200/80 bg-gradient-to-br from-emerald-50 via-white to-white p-4 sm:p-5 shadow-sm transition-shadow hover:shadow-md">
            <div class="flex items-start justify-between gap-3">
                <span class="flex size-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 ring-1 ring-emerald-200/70">
                    <svg class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5m8.25 3v6.75m0 0l-3-3m3 3l3-3M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                    </svg>
                </span>
                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-semibold text-emerald-700 ring-1 ring-emerald-200/70">
                    Laba Ditahan
                </span>
            </div>
            <p class="mt-4 text-[11px] font-semibold uppercase tracking-wider text-zinc-500">Cadangan Modal</p>
            <p class="mt-1 text-2xl font-bold tracking-tight text-zinc-900 tabular-nums">
                Rp {{ number_format($this->totalRetained, 0, ',', '.') }}
            </p>
            <p class="mt-2 text-[11px] text-zinc-500">Disimpan untuk modal operasional BUMDes</p>
        </div>

        {{-- Remaining Profit --}}
        <div class="relative overflow-hidden rounded-2xl border p-4 sm:p-5 shadow-sm transition-shadow hover:shadow-md {{ $this->unallocatedProfit < 0 ? 'border-red-200/80 bg-gradient-to-br from-red-50 via-white to-white' : 'border-zinc-200/80 bg-gradient-to-br from-zinc-100 via-white to-white' }}">
            <div class="flex items-start justify-between gap-3">
                <span class="flex size-9 items-center justify-center rounded-xl {{ $this->unallocatedProfit < 0 ? 'bg-red-50 text-red-600 ring-1 ring-red-200/70' : 'bg-zinc-100 text-zinc-600 ring-1 ring-zinc-200/70' }}">
                    <svg class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 1 0 7.5 7.5h-7.5V6z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0 0 13.5 3v7.5z" />
                    </svg>
                </span>
                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[10px] font-semibold {{ $this->unallocatedProfit < 0 ? 'bg-red-50 text-red-700 ring-1 ring-red-200/70' : 'bg-zinc-100 text-zinc-600 ring-1 ring-zinc-200/70' }}">
                    {{ $this->unallocatedProfit < 0 ? 'Melebihi Laba' : 'Belum Dibagi' }}
                </span>
            </div>
            <p class="mt-4 text-[11px] font-semibold uppercase tracking-wider text-zinc-500">Sisa Laba</p>
            <p class="mt-1 text-2xl font-bold tracking-tight tabular-nums {{ $this->unallocatedProfit < 0 ? 'text-red-600' : 'text-zinc-900' }}">
                Rp {{ number_format($this->unallocatedProfit, 0, ',', '.') }}
            </p>
            <p class="mt-2 text-[11px] text-zinc-500">
                {{ $this->unallocatedProfit < 0 ? 'Periksa total alokasi periode ini' : 'Sisa laba bersih periode berjalan' }}
            </p>
        </div>
    </div>

    {{-- Main Allocation Table Card --}}
    <div class="bg-white rounded-xl border border-brand-100 shadow-sm overflow-hidden mb-6 pb-6">
        <div class="px-4 sm:px-6 py-4 border-b border-zinc-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <p class="text-sm font-bold text-zinc-900 uppercase">BUMDESA TEJA PERCEKA</p>
                <p class="text-xs text-zinc-500 mt-0.5">Rincian Pos Alokasi Laba — Periode {{ $this->periodLabel() }}</p>
            </div>

            @if($this->canEdit)
                <button type="button" wire:click="openCreate"
                    @disabled($this->isLocked)
                    title="{{ $this->isLocked ? 'Batalkan alokasi untuk mengubah pos' : 'Tambah Pos Alokasi' }}"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-brand-50 border border-brand-200 text-brand-700 rounded-xl text-xs font-semibold hover:bg-brand-100 transition-colors shadow-sm self-start sm:self-auto disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-brand-50">
                    <svg class="size-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Tambah Alokasi
                </button>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-zinc-700">
                <thead class="text-xs font-bold text-zinc-700 uppercase bg-zinc-50 border-b border-zinc-200">
                    <tr>
                        <th class="px-4 sm:px-6 py-3">Keterangan</th>
                        <th class="px-4 sm:px-6 py-3 text-center">Jenis Alokasi</th>
                        <th class="px-4 sm:px-6 py-3 text-center">Metode Perhitungan</th>
                        <th class="px-4 sm:px-6 py-3 text-right">Jumlah (Rp)</th>
                        @if($this->canEdit)
                            <th class="px-4 sm:px-6 py-3 text-center w-24">Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    {{-- CURRENT-YEAR NET INCOME --}}
                    <tr class="bg-zinc-100/50 border-b border-zinc-200">
                        <td class="px-4 sm:px-6 py-3 text-xs font-bold text-zinc-900 uppercase tracking-wider" colspan="3">
                            LABA BERSIH
                        </td>
                        <td class="px-4 sm:px-6 py-3 text-right font-bold text-brand-900">
                            Rp {{ number_format($this->netIncome, 0, ',', '.') }}
                        </td>
                        @if($this->canEdit)<td></td>@endif
                    </tr>

                    {{-- DEDUCTION ITEMS --}}
                    @forelse ($this->deductionRows as $row)
                        <tr class="hover:bg-zinc-50 transition-colors border-b border-zinc-100">
                            <td class="px-4 sm:px-6 py-3 text-zinc-800 pl-8 sm:pl-10">
                                <div class="font-medium text-zinc-900">{{ $row['description'] }}</div>
                                @if($row['account'])
                                    <div class="text-[11px] text-zinc-500 font-mono">
                                        {{ $row['account']->code }} - {{ $row['account']->name }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 sm:px-6 py-3 text-center">
                                @if($row['flow_type'] === 'outflow')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                        Penyaluran Laba
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        Cadangan Modal
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 sm:px-6 py-3 text-center font-sans text-zinc-600">
                                @if($row['calc_type'] === 'nominal')
                                    <span class="text-xs font-medium text-zinc-600 bg-zinc-100 px-2 py-0.5 rounded">
                                        Nominal Tetap
                                    </span>
                                @else
                                    <span class="font-semibold text-zinc-800">{{ number_format($row['percentage'], 2, '.', '') }}%</span>
                                @endif
                            </td>
                            <td class="px-4 sm:px-6 py-3 text-right font-semibold text-zinc-900">
                                Rp {{ number_format($row['amount'], 0, ',', '.') }}
                            </td>
                            @if($this->canEdit)
                                <td class="px-4 sm:px-6 py-3 text-center whitespace-nowrap">
                                    <div class="flex items-center justify-center gap-1.5">
                                        {{-- The description travels as a JSON-encoded wire:click argument and is
                                             never concatenated into a JavaScript string literal: HTML-escaping the
                                             apostrophe does not protect that context, because the browser decodes
                                             the attribute back to the raw character before Livewire evaluates it. --}}
                                        <button wire:click="openEdit({{ Js::from($row['description']) }})"
                                            type="button"
                                            title="{{ $this->isLocked ? 'Batalkan alokasi untuk mengubah pos' : 'Edit' }}"
                                            @disabled($this->isLocked)
                                            class="inline-flex items-center justify-center size-7 rounded-lg bg-amber-500 text-white hover:bg-amber-600 transition-colors shadow-sm disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-amber-500">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-3.5">
                                                <path d="M13.488 2.513a1.75 1.75 0 0 0-2.475 0L6.75 6.774a2.75 2.75 0 0 0-.596.892l-.848 2.047a.75.75 0 0 0 .98.98l2.047-.848a2.75 2.75 0 0 0 .892-.596l4.261-4.263a1.75 1.75 0 0 0 0-2.474Z" />
                                                <path d="M4.75 3.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h6.5c.69 0 1.25-.56 1.25-1.25V9A.75.75 0 0 1 13 9v2.25A2.75 2.75 0 0 1 10.25 14h-6.5A2.75 2.75 0 0 1 1 11.25v-6.5A2.75 2.75 0 0 1 3.75 2H6a.75.75 0 0 1 0 1.5H3.75Z" />
                                            </svg>
                                        </button>
                                        <button wire:click="confirmDelete({{ Js::from($row['description']) }})"
                                            type="button"
                                            title="{{ $this->isLocked ? 'Batalkan alokasi untuk menghapus pos' : 'Hapus' }}"
                                            @disabled($this->isLocked)
                                            class="inline-flex items-center justify-center size-7 rounded-lg bg-red-600 text-white hover:bg-red-700 transition-colors shadow-sm disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-red-600">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-3.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr class="border-b border-zinc-100">
                            <td class="px-4 sm:px-6 py-3 text-zinc-400 italic text-xs pl-8 sm:pl-10" colspan="{{ $this->canEdit ? 5 : 4 }}">
                                — Belum ada pos Pengurang —
                            </td>
                        </tr>
                    @endforelse

                    {{-- Profit after deductions --}}
                    <tr class="bg-zinc-100/70 border-y border-zinc-300">
                        <td class="px-4 sm:px-6 py-3 font-bold text-zinc-900 text-xs uppercase" colspan="3">
                            Laba/Rugi Bersih setelah {{ $this->deductionLabel }}
                        </td>
                        <td class="px-4 sm:px-6 py-3 text-right font-bold {{ $this->netIncomeAfterDeductions >= 0 ? 'text-brand-900' : 'text-red-700' }}">
                            Rp {{ number_format($this->netIncomeAfterDeductions, 0, ',', '.') }}
                        </td>
                        @if($this->canEdit)<td></td>@endif
                    </tr>

                    {{-- AD/ART SECTION HEADER --}}
                    <tr class="bg-zinc-50 border-b border-zinc-200">
                        <td class="px-4 sm:px-6 py-2.5 text-xs font-bold text-zinc-600 uppercase tracking-wider" colspan="{{ $this->canEdit ? 5 : 4 }}">
                            Alokasi Laba Bersih sesuai AD/ART
                        </td>
                    </tr>

                    {{-- AD/ART ITEMS --}}
                    @forelse ($this->adArtRows as $row)
                        <tr class="hover:bg-zinc-50 transition-colors border-b border-zinc-100">
                            <td class="px-4 sm:px-6 py-3 text-zinc-800 pl-8 sm:pl-10">
                                <div class="font-medium text-zinc-900">{{ $row['description'] }}</div>
                                @if($row['account'])
                                    <div class="text-[11px] text-zinc-500 font-mono">
                                        {{ $row['account']->code }} - {{ $row['account']->name }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 sm:px-6 py-3 text-center">
                                @if($row['flow_type'] === 'outflow')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                        Penyaluran Laba
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        Cadangan Modal
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 sm:px-6 py-3 text-center font-sans text-zinc-600">
                                @if($row['calc_type'] === 'nominal')
                                    <span class="text-xs font-medium text-zinc-600 bg-zinc-100 px-2 py-0.5 rounded">
                                        Nominal Tetap
                                    </span>
                                @else
                                    <span class="font-semibold text-zinc-800">{{ number_format($row['percentage'], 2, '.', '') }}%</span>
                                @endif
                            </td>
                            <td class="px-4 sm:px-6 py-3 text-right font-semibold text-zinc-900">
                                Rp {{ number_format($row['amount'], 0, ',', '.') }}
                            </td>
                            @if($this->canEdit)
                                <td class="px-4 sm:px-6 py-3 text-center whitespace-nowrap">
                                    <div class="flex items-center justify-center gap-1.5">
                                        {{-- The description travels as a JSON-encoded wire:click argument and is
                                             never concatenated into a JavaScript string literal: HTML-escaping the
                                             apostrophe does not protect that context, because the browser decodes
                                             the attribute back to the raw character before Livewire evaluates it. --}}
                                        <button wire:click="openEdit({{ Js::from($row['description']) }})"
                                            type="button"
                                            title="{{ $this->isLocked ? 'Batalkan alokasi untuk mengubah pos' : 'Edit' }}"
                                            @disabled($this->isLocked)
                                            class="inline-flex items-center justify-center size-7 rounded-lg bg-amber-500 text-white hover:bg-amber-600 transition-colors shadow-sm disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-amber-500">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-3.5">
                                                <path d="M13.488 2.513a1.75 1.75 0 0 0-2.475 0L6.75 6.774a2.75 2.75 0 0 0-.596.892l-.848 2.047a.75.75 0 0 0 .98.98l2.047-.848a2.75 2.75 0 0 0 .892-.596l4.261-4.263a1.75 1.75 0 0 0 0-2.474Z" />
                                                <path d="M4.75 3.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h6.5c.69 0 1.25-.56 1.25-1.25V9A.75.75 0 0 1 13 9v2.25A2.75 2.75 0 0 1 10.25 14h-6.5A2.75 2.75 0 0 1 1 11.25v-6.5A2.75 2.75 0 0 1 3.75 2H6a.75.75 0 0 1 0 1.5H3.75Z" />
                                            </svg>
                                        </button>
                                        <button wire:click="confirmDelete({{ Js::from($row['description']) }})"
                                            type="button"
                                            title="{{ $this->isLocked ? 'Batalkan alokasi untuk menghapus pos' : 'Hapus' }}"
                                            @disabled($this->isLocked)
                                            class="inline-flex items-center justify-center size-7 rounded-lg bg-red-600 text-white hover:bg-red-700 transition-colors shadow-sm disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-red-600">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-3.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr class="border-b border-zinc-100">
                            <td class="px-4 sm:px-6 py-3 text-zinc-400 italic text-xs pl-8 sm:pl-10" colspan="{{ $this->canEdit ? 5 : 4 }}">
                                — Belum ada pos AD/ART —
                            </td>
                        </tr>
                    @endforelse

                    {{-- AD/ART total --}}
                    @if($this->adArtRows->count() > 0)
                        <tr class="bg-zinc-50 border-t-2 border-zinc-300">
                            <td class="px-4 sm:px-6 py-3.5 font-bold text-zinc-900 uppercase text-xs" colspan="2">TOTAL AD/ART</td>
                            <td class="px-4 sm:px-6 py-3.5 text-center font-bold {{ $this->totalAdArtPercent > 100 ? 'text-red-600' : 'text-brand-800' }}">
                                {{ number_format($this->totalAdArtPercent, 2, '.', '') }}%
                                @if($this->totalAdArtPercent > 100)
                                    <span class="block text-[10px] text-red-500 font-normal">Persentase > 100%</span>
                                @endif
                            </td>
                            <td class="px-4 sm:px-6 py-3.5 text-right font-bold text-zinc-900">
                                Rp {{ number_format($this->totalAdArtAmount, 0, ',', '.') }}
                            </td>
                            @if($this->canEdit)<td></td>@endif
                        </tr>
                    @endif

                    @if($this->allocationRows->count() === 0)
                        <tr>
                            <td colspan="{{ $this->canEdit ? 5 : 4 }}" class="px-6 py-10 text-center text-zinc-500">
                                Belum ada pos alokasi untuk periode ini. Silakan klik tombol "Tambah Pos Alokasi".
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    <x-report-actions :preview-url="route('reports.profit-allocation.preview', ['mode' => $mode, 'period' => $period, 'semester' => $semester, 'semester_year' => $semesterYear])" sticky />

    {{-- ADD / EDIT MODAL --}}
    @if($showModal && $this->canEdit)
        <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="modal-title">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" wire:click="closeModal" aria-hidden="true"></div>
                <div class="relative w-full max-w-lg bg-white rounded-lg shadow-xl">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-zinc-100 bg-zinc-50 rounded-t-lg">
                        <h3 id="modal-title" class="text-lg font-semibold text-zinc-900">
                            {{ $editingDescription ? 'Edit Pos Alokasi' : 'Tambah Pos Alokasi' }}
                        </h3>
                        <button type="button" wire:click="closeModal"
                            class="flex items-center justify-center p-1 text-zinc-400 hover:text-zinc-600 transition-colors focus:outline-none"
                            aria-label="Tutup modal">
                            <svg class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form wire:submit.prevent="saveRow" class="p-6 space-y-5">
                        {{-- Item description --}}
                        <div>
                            <label for="formDescription" class="block text-sm font-medium text-zinc-700 mb-1.5">
                                Keterangan Pos Alokasi <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="formDescription" wire:model="formDescription" placeholder="Contoh: PADes Desa, Pajak, Donasi, Cadangan Modal..." autofocus
                                class="w-full rounded-xl border-2 border-zinc-200 text-zinc-900 px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 focus:outline-none transition-colors" />
                            @error('formDescription')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                            {{-- Allocation type --}}
                            <div>
                                <label class="block text-sm font-medium text-zinc-700 mb-1.5">Jenis Alokasi</label>
                                <div x-data="{
                                    open: false,
                                    selectedVal: @entangle('formGroup').live,
                                    options: [
                                        { val: 'pengurang', label: 'Pengurang Laba' },
                                        { val: 'ad_art', label: 'Pembagian AD/ART' }
                                    ],
                                    get selectedLabel() {
                                        const selected = this.options.find(o => o.val === this.selectedVal);
                                        return selected ? selected.label : 'Pilih Jenis Alokasi';
                                    }
                                }" class="relative">
                                    <button type="button" @click="open = !open" @click.outside="open = false"
                                        class="w-full flex items-center justify-between rounded-xl border-2 border-zinc-200 bg-white px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 focus:outline-none transition-colors text-zinc-900">
                                        <span x-text="selectedLabel" class="truncate font-medium"></span>
                                        <svg class="size-4 text-zinc-400 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </button>
                                    <div x-show="open" x-transition style="display: none;"
                                        class="absolute z-20 w-full mt-1 bg-white border border-zinc-200 rounded-xl shadow-lg max-h-48 overflow-y-auto">
                                        <ul class="py-1">
                                            <template x-for="option in options" :key="option.val">
                                                <li>
                                                    <button type="button" @click="selectedVal = option.val; open = false"
                                                        class="w-full text-left px-4 py-2 text-sm hover:bg-brand-50 hover:text-brand-700 transition-colors"
                                                        :class="selectedVal === option.val ? 'bg-brand-50 text-brand-700 font-medium' : 'text-zinc-700'"
                                                        x-text="option.label"></button>
                                                </li>
                                            </template>
                                        </ul>
                                    </div>
                                    <select wire:model.live="formGroup" class="hidden">
                                        <option value="pengurang">Tahap 1: Pengurang Laba</option>
                                        <option value="ad_art">Tahap 2: Pembagian AD/ART</option>
                                    </select>
                                </div>
                                @error('formGroup')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Fund designation --}}
                            <div>
                                <label class="block text-sm font-medium text-zinc-700 mb-1.5">Peruntukan Dana</label>
                                <div x-data="{
                                    open: false,
                                    selectedVal: @entangle('formFlowType').live,
                                    options: [
                                        { val: 'outflow', label: 'Penyaluran Laba (Kas Keluar)' },
                                        { val: 'retained', label: 'Cadangan Modal (Laba Ditahan)' }
                                    ],
                                    get selectedLabel() {
                                        const selected = this.options.find(o => o.val === this.selectedVal);
                                        return selected ? selected.label : 'Pilih Peruntukan';
                                    }
                                }" class="relative">
                                    <button type="button" @click="open = !open" @click.outside="open = false"
                                        class="w-full flex items-center justify-between rounded-xl border-2 border-zinc-200 bg-white px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 focus:outline-none transition-colors text-zinc-900">
                                        <span x-text="selectedLabel" class="truncate font-medium"></span>
                                        <svg class="size-4 text-zinc-400 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </button>
                                    <div x-show="open" x-transition style="display: none;"
                                        class="absolute z-20 w-full mt-1 bg-white border border-zinc-200 rounded-xl shadow-lg max-h-48 overflow-y-auto">
                                        <ul class="py-1">
                                            <template x-for="option in options" :key="option.val">
                                                <li>
                                                    <button type="button" @click="selectedVal = option.val; open = false"
                                                        class="w-full text-left px-4 py-2 text-sm hover:bg-brand-50 hover:text-brand-700 transition-colors"
                                                        :class="selectedVal === option.val ? 'bg-brand-50 text-brand-700 font-medium' : 'text-zinc-700'"
                                                        x-text="option.label"></button>
                                                </li>
                                            </template>
                                        </ul>
                                    </div>
                                    <select wire:model.live="formFlowType" class="hidden">
                                        <option value="outflow">Penyaluran Laba (Kas Keluar)</option>
                                        <option value="retained">Cadangan Modal (Laba Ditahan)</option>
                                    </select>
                                </div>
                                @error('formFlowType')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Allocation form: percentage vs nominal --}}
                        <div>
                            <label class="block text-sm font-medium text-zinc-700 mb-1.5">Bentuk Perhitungan <span class="text-red-500">*</span></label>
                            <div class="grid grid-cols-2 gap-3">
                                <label class="flex items-center justify-center p-3 rounded-xl border-2 {{ $formCalcType === 'percentage' ? 'border-brand-500 bg-brand-50/50 text-brand-900 font-bold' : 'border-zinc-200 bg-white text-zinc-700 font-medium hover:bg-zinc-50' }} cursor-pointer transition-colors text-center shadow-sm">
                                    <input type="radio" wire:model.live="formCalcType" value="percentage" class="sr-only">
                                    <span class="text-sm">Persentase</span>
                                </label>
                                <label class="flex items-center justify-center p-3 rounded-xl border-2 {{ $formCalcType === 'nominal' ? 'border-brand-500 bg-brand-50/50 text-brand-900 font-bold' : 'border-zinc-200 bg-white text-zinc-700 font-medium hover:bg-zinc-50' }} cursor-pointer transition-colors text-center shadow-sm">
                                    <input type="radio" wire:model.live="formCalcType" value="nominal" class="sr-only">
                                    <span class="text-sm">Nominal</span>
                                </label>
                            </div>
                        </div>

                        {{-- Value input --}}
                        @if($formCalcType === 'percentage')
                            <div>
                                <label for="formPercentage" class="block text-sm font-medium text-zinc-700 mb-1.5">Persentase <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <input type="number" step="0.01" min="0.01" max="100" id="formPercentage" wire:model.live.debounce.300ms="formPercentage"
                                        x-on:input="if (parseFloat($el.value) > 100) { $el.value = '100'; $wire.set('formPercentage', '100'); }"
                                        placeholder="0"
                                        class="w-full rounded-xl border-2 border-zinc-200 text-zinc-900 pr-9 pl-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 focus:outline-none transition-colors" />
                                    <span class="absolute right-3.5 top-1/2 -translate-y-1/2 text-sm text-zinc-500 font-bold pointer-events-none">%</span>
                                </div>
                                @error('formPercentage')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        @else
                            <div>
                                <label for="formAmount" class="block text-sm font-medium text-zinc-700 mb-1.5">Nominal <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-sm text-zinc-500 font-medium pointer-events-none">Rp</span>
                                    <input type="text" inputmode="numeric" data-rupiah id="formAmount" wire:model.live.debounce.300ms="formAmount" placeholder="0"
                                        class="w-full rounded-xl border-2 border-zinc-200 text-zinc-900 pl-10 pr-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 focus:outline-none transition-colors" />
                                </div>
                                @error('formAmount')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif

                        {{-- Related COA account (general ledger) --}}
                        <div x-show="$wire.formFlowType === 'outflow'">
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="block text-sm font-medium text-zinc-700">Pilih Kode Akun (Beban Penyaluran)</label>
                                <span class="text-xs text-zinc-400">Opsional</span>
                            </div>
                            <div x-data="{
                                open: false,
                                selectedId: @entangle('formAccountId'),
                                options: [
                                    { id: '', label: '-- Pilih Kode Akun (Opsional) --' },
                                    @foreach($this->availableAccounts as $account)
                                    { id: '{{ $account->id }}', label: '{{ $account->code }} - {{ $account->name }}' },
                                    @endforeach
                                ],
                                get selectedLabel() {
                                    const selected = this.options.find(o => o.id == this.selectedId);
                                    return selected ? selected.label : '-- Pilih Kode Akun (Opsional) --';
                                }
                            }" class="relative">
                                <button type="button" @click="open = !open" @click.outside="open = false"
                                    class="w-full flex items-center justify-between rounded-xl border-2 border-zinc-200 bg-white px-3.5 py-2.5 text-sm shadow-sm focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 focus:outline-none transition-colors"
                                    :class="selectedId ? 'text-zinc-900' : 'text-zinc-500'">
                                    <span x-text="selectedLabel" class="truncate"></span>
                                    <svg class="size-4 text-zinc-400 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>
                                <div x-show="open" x-transition style="display: none;"
                                    class="absolute z-20 w-full mt-1 bg-white border border-zinc-200 rounded-xl shadow-lg max-h-48 overflow-y-auto">
                                    <ul class="py-1">
                                        <template x-for="option in options" :key="option.id">
                                            <li>
                                                <button type="button" @click="selectedId = option.id ? Number(option.id) : null; open = false"
                                                    class="w-full text-left px-4 py-2 text-sm hover:bg-brand-50 hover:text-brand-700 transition-colors"
                                                    :class="selectedId == option.id ? 'bg-brand-50 text-brand-700 font-medium' : 'text-zinc-700'"
                                                    x-text="option.label"></button>
                                            </li>
                                        </template>
                                    </ul>
                                </div>
                                <select wire:model="formAccountId" class="hidden">
                                    <option value="">Pilih</option>
                                    @foreach($this->availableAccounts as $account)
                                        <option value="{{ $account->id }}">{{ $account->code }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @error('formAccountId')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="text-xs text-zinc-600 bg-zinc-50 rounded-xl p-3.5 border border-zinc-200">
                            @if($formGroup === 'pengurang')
                                <span class="font-semibold text-zinc-900">Kelompok Pengurang Laba Bersih:</span>
                                Dihitung langsung dari Laba Bersih (Rp {{ number_format($this->netIncome, 0, ',', '.') }}).
                            @else
                                <span class="font-semibold text-zinc-900">Kelompok AD/ART:</span>
                                Dihitung dari sisa Laba setelah potongan (Rp {{ number_format($this->netIncomeAfterDeductions, 0, ',', '.') }}).
                            @endif
                        </div>

                        <div class="pt-4 border-t border-zinc-100 flex flex-col sm:flex-row gap-3">
                            <button type="button" wire:click="closeModal"
                                class="flex-1 sm:flex-none px-5 py-2.5 text-sm font-semibold text-zinc-700 bg-white border border-zinc-300 rounded-xl hover:bg-zinc-50 transition-colors">
                                Batal
                            </button>
                            <button type="submit" wire:loading.attr="disabled" wire:target="saveRow"
                                class="flex-1 sm:flex-none px-5 py-2.5 text-sm font-semibold text-white bg-brand-600 hover:bg-brand-700 rounded-xl transition-colors shadow-sm disabled:opacity-70 disabled:cursor-wait">
                                <span wire:loading.remove wire:target="saveRow">{{ $editingDescription ? 'Perbarui' : 'Simpan Pos Alokasi' }}</span>
                                <span wire:loading wire:target="saveRow">Menyimpan...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- APPLY CLOSING CONFIRMATION MODAL --}}
    @if ($showClosingConfirmModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="closing-modal-title">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" wire:click="$set('showClosingConfirmModal', false)" aria-hidden="true"></div>
                <div class="relative w-full max-w-lg bg-white rounded-lg shadow-xl overflow-hidden font-sans" style="font-family: 'Poppins', ui-sans-serif, system-ui, sans-serif;">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-zinc-100 bg-zinc-50/80">
                        <div>
                            <h3 id="closing-modal-title" class="text-base font-semibold text-zinc-900 leading-tight">
                                Tetapkan Alokasi Laba
                            </h3>
                            <p class="text-xs text-zinc-500 mt-0.5">Periode: <span class="font-medium text-zinc-700">{{ $this->periodLabel() }}</span></p>
                        </div>
                        <button type="button" wire:click="$set('showClosingConfirmModal', false)"
                            class="flex items-center justify-center p-1 text-zinc-400 hover:text-zinc-600 transition-colors focus:outline-none"
                            aria-label="Tutup modal">
                            <svg class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="p-6 space-y-4">
                        <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 space-y-3">
                            <div class="flex items-center justify-between text-xs sm:text-sm">
                                <span class="text-zinc-600 font-medium">Total Laba Bersih</span>
                                <span class="font-bold text-zinc-900">Rp {{ number_format($this->netIncome, 0, ',', '.') }}</span>
                            </div>

                            <div class="border-t border-zinc-200/80 pt-2.5 space-y-2">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="flex items-center gap-1.5 text-zinc-700">
                                        <span class="size-1.5 rounded-full bg-amber-500"></span>
                                        Penyaluran Laba (Kas Keluar)
                                    </span>
                                    <span class="font-semibold text-amber-700">Rp {{ number_format($this->totalOutflow, 0, ',', '.') }}</span>
                                </div>
                                <div class="flex items-center justify-between text-xs">
                                    <span class="flex items-center gap-1.5 text-zinc-700">
                                        <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                        Cadangan Modal (Laba Ditahan)
                                    </span>
                                    <span class="font-semibold text-emerald-700">Rp {{ number_format($this->totalRetained, 0, ',', '.') }}</span>
                                </div>
                            </div>

                            <div class="border-t border-zinc-200/80 pt-2.5 flex items-center justify-between text-xs sm:text-sm font-bold">
                                <span class="text-zinc-900">Sisa Laba Berjalan</span>
                                <span class="{{ $this->unallocatedProfit < 0 ? 'text-red-600' : 'text-zinc-900' }}">
                                    Rp {{ number_format($this->unallocatedProfit, 0, ',', '.') }}
                                </span>
                            </div>
                        </div>

                        <div class="flex items-start gap-2.5 rounded-lg bg-brand-50/60 p-3 border border-brand-100/80 text-xs text-brand-900">
                            <svg class="size-4 text-brand-600 shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                            </svg>
                            <p class="leading-relaxed text-zinc-600">
                                Sistem akan menetapkan perhitungan alokasi laba periode ini dan membukukan laporan penutup otomatis untuk akun terkait ke buku besar. Saldo kas awal bulan berikutnya akan disinkronkan secara otomatis.
                            </p>
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-zinc-50 border-t border-zinc-100 flex items-center justify-end gap-3 rounded-b-lg">
                        <button type="button" wire:click="$set('showClosingConfirmModal', false)"
                            class="px-4 py-2.5 text-sm font-medium text-zinc-700 bg-white border border-zinc-300 rounded-lg hover:bg-zinc-50 hover:text-zinc-900 transition-colors">
                            Batal
                        </button>
                        <button type="button" wire:click="applyAllocation" wire:loading.attr="disabled"
                            class="inline-flex items-center justify-center px-5 py-2.5 text-sm font-semibold text-white bg-brand-600 hover:bg-brand-700 rounded-lg transition-colors shadow-sm disabled:opacity-60">
                            <span wire:loading.remove wire:target="applyAllocation">
                                Ya, Tetapkan Alokasi Laba
                            </span>
                            <span wire:loading wire:target="applyAllocation" class="inline-flex items-center gap-2">
                                Menetapkan Alokasi...
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ROLLBACK CONFIRMATION MODAL --}}
    @if ($showRollbackConfirmModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="rollback-modal-title">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" wire:click="$set('showRollbackConfirmModal', false)" aria-hidden="true"></div>
                <div class="relative w-full max-w-md bg-white rounded-lg shadow-xl overflow-hidden font-sans" style="font-family: 'Poppins', ui-sans-serif, system-ui, sans-serif;">
                    <div class="p-6">
                        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-red-100 text-red-600">
                            <svg class="size-7" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                            </svg>
                        </div>
                        <h3 id="rollback-modal-title" class="text-center text-lg font-bold text-zinc-900 mb-3">Batalkan Alokasi?</h3>
                        <p class="text-left text-sm text-zinc-500 leading-relaxed">
                            Alokasi otomatis untuk periode <strong>{{ $this->periodLabel() }}</strong> akan dihapus dari buku besar. Saldo kas dan cadangan modal akan kembali ke kondisi sebelum alokasi diterapkan.
                        </p>
                    </div>
                    <div class="px-6 py-4 bg-zinc-50 border-t border-zinc-100 flex items-center justify-end gap-3 rounded-b-lg">
                        <button type="button" wire:click="$set('showRollbackConfirmModal', false)"
                            class="px-4 py-2.5 text-sm font-medium text-zinc-700 bg-white border border-zinc-300 rounded-lg hover:bg-zinc-50 hover:text-zinc-900 transition-colors">
                            Batal
                        </button>
                        <button type="button" wire:click="rollbackAllocation" wire:loading.attr="disabled"
                            class="px-5 py-2.5 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors shadow-sm disabled:opacity-70">
                            <span wire:loading.remove wire:target="rollbackAllocation">Ya, Batalkan Alokasi</span>
                            <span wire:loading wire:target="rollbackAllocation">Membatalkan...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- DELETE ROW MODAL --}}
    @if ($showDeleteModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="delete-modal-title">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" wire:click="$set('showDeleteModal', false)" aria-hidden="true"></div>
                <div class="relative w-full max-w-md bg-white rounded-lg shadow-xl overflow-hidden font-sans" style="font-family: 'Poppins', ui-sans-serif, system-ui, sans-serif;">
                    <div class="p-6">
                        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-red-100 text-red-600">
                            <svg class="size-7" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                            </svg>
                        </div>
                        <h3 id="delete-modal-title" class="text-center text-lg font-bold text-zinc-900 mb-3">Hapus Pos Alokasi</h3>
                        <p class="text-left text-sm text-zinc-500 leading-relaxed">
                            Apakah Anda yakin ingin menghapus pos alokasi <strong>"{{ $deleteDescription }}"</strong>? Data pos ini akan dinonaktifkan khusus untuk periode <strong>{{ $this->periodLabel() }}</strong>.
                        </p>
                    </div>
                    <div class="px-6 py-4 bg-zinc-50 border-t border-zinc-100 flex items-center justify-end gap-3 rounded-b-lg">
                        <button type="button" wire:click="$set('showDeleteModal', false)"
                            class="px-4 py-2.5 text-sm font-medium text-zinc-700 bg-white border border-zinc-300 rounded-lg hover:bg-zinc-50 hover:text-zinc-900 transition-colors">
                            Batal
                        </button>
                        <button type="button" wire:click="executeDelete"
                            class="px-5 py-2.5 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors shadow-sm">
                            Ya, Hapus
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

</div>
