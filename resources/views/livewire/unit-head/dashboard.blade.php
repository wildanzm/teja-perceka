<div class="flex flex-col gap-5 max-w-full mx-auto w-full">

    <x-page-header :title="'Dashboard - ' . ($unit->name ?? 'Unit Usaha')" />

    {{-- Unsubmitted transaction alert --}}
    @if (!$isCurrentPeriodSubmitted)
        <div
            class="rounded-xl border border-red-200 bg-red-50 p-4 sm:p-5 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-start sm:items-center gap-3 min-w-0">
                <div class="size-10 rounded-full bg-red-100 flex items-center justify-center shrink-0">
                    <svg class="size-5 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none"
                        viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <h2 class="text-base font-bold text-red-900 leading-tight">
                        Peringatan: Belum Input Transaksi!
                    </h2>
                    <p class="text-sm text-red-700 mt-0.5 leading-relaxed">
                        Anda belum mengisi transaksi untuk
                        {{ $unit->input_frequency === 'weekly' ? 'week' : 'hari' }} ini. Silakan segera isi data
                        transaksi.
                    </p>
                </div>
            </div>
            <flux:button variant="danger" :href="route('unit.record-transaction')" wire:navigate
                class="w-full sm:w-auto shrink-0" icon="pencil-square">
                Input Sekarang
            </flux:button>
        </div>
    @endif

    <x-dashboard-period-filter :period="$period" :periodLabel="$periodLabel" :periodRange="$periodRange" />

    {{-- Recap cards --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5" wire:loading.remove.grid wire:target="period">
        {{-- Income --}}
        <div class="bg-gradient-to-br from-brand-50 via-white to-white p-6 rounded-2xl border border-brand-200/80 shadow-sm flex items-start gap-4 transition-shadow hover:shadow-md">
            <div class="size-12 rounded-xl bg-brand-50 text-brand-600 flex items-center justify-center shrink-0">
                <svg class="size-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 4.5l-15 15m0 0h11.25m-11.25 0V8.25" />
                </svg>
            </div>
            <div class="flex flex-col">
                <h3 class="text-sm font-semibold text-zinc-500">Total Pemasukan</h3>
                <p class="mt-1 text-2xl font-bold text-zinc-900">Rp {{ number_format($income, 0, ',', '.') }}</p>
            </div>
        </div>

        {{-- Expenses --}}
        <div class="bg-gradient-to-br from-red-50 via-white to-white p-6 rounded-2xl border border-red-200/80 shadow-sm flex items-start gap-4 transition-shadow hover:shadow-md">
            <div class="size-12 rounded-xl bg-red-50 text-red-600 flex items-center justify-center shrink-0">
                <svg class="size-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5l15-15m0 0H8.25m11.25 0v11.25" />
                </svg>
            </div>
            <div class="flex flex-col">
                <h3 class="text-sm font-semibold text-zinc-500">Total Pengeluaran</h3>
                <p class="mt-1 text-2xl font-bold text-zinc-900">Rp {{ number_format($expenses, 0, ',', '.') }}</p>
            </div>
        </div>

        {{-- Balance --}}
        <div class="bg-gradient-to-br from-emerald-50 via-white to-white p-6 rounded-2xl border border-emerald-200/80 shadow-sm flex items-start gap-4 transition-shadow hover:shadow-md">
            <div class="size-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                <svg class="size-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m18 0v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6m18 0V9M3 12V9m18 0a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 9m18 0V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v3" />
                </svg>
            </div>
            <div class="flex flex-col">
                <h3 class="text-sm font-semibold text-zinc-500">Laba Bersih</h3>
                <p class="mt-1 text-2xl font-bold text-emerald-600">Rp {{ number_format($netIncome, 0, ',', '.') }}</p>
            </div>
        </div>
    </div>

    <div wire:loading.grid wire:target="period" class="grid grid-cols-1 lg:grid-cols-3 gap-5" aria-hidden="true">
        <x-dashboard-card-skeleton cardClass="bg-gradient-to-br from-brand-50 via-white to-white p-6 rounded-2xl border border-brand-200/80 shadow-sm flex items-start gap-4" />
        <x-dashboard-card-skeleton cardClass="bg-gradient-to-br from-red-50 via-white to-white p-6 rounded-2xl border border-red-200/80 shadow-sm flex items-start gap-4" />
        <x-dashboard-card-skeleton cardClass="bg-gradient-to-br from-emerald-50 via-white to-white p-6 rounded-2xl border border-emerald-200/80 shadow-sm flex items-start gap-4" />
    </div>

    <div wire:loading.block wire:target="period" class="bg-white p-6 rounded-2xl border border-zinc-200 shadow-sm flex flex-col animate-pulse" aria-hidden="true">
        <div class="h-7 w-44 rounded-md bg-zinc-200 mb-4"></div>
        <div class="flex-1 w-full min-h-[350px] rounded-xl bg-zinc-100"></div>
    </div>

    {{-- Recap chart --}}
    <div class="bg-white p-6 rounded-2xl border border-zinc-200 shadow-sm flex flex-col w-full" wire:loading.remove.block wire:target="period">
        <h2 class="text-lg font-bold text-zinc-900 mb-4">Grafik Rekapitulasi</h2>
        <div class="w-full relative min-h-[350px]" wire:ignore>
            <canvas id="recapChart" role="img" aria-label="Grafik rekapitulasi pemasukan dan pengeluaran"></canvas>
        </div>
    </div>

</div>

@include('livewire.partials.recap-chart')
