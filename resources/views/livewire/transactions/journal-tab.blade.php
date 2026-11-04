{{-- General journal tab --}}
<div class="flex flex-col gap-5 min-w-0 pb-10">

    {{-- Journal list --}}
    @php
        $isSummary = ($viewMode ?? 'summary') === 'summary';
        $groups = $this->transactions['groups'] ?? collect();
        $paginator = $this->transactions['paginator'] ?? null;
        $summaryData = $isSummary ? $this->summaryRows : null;
        $summaryGroups = $summaryData['groups'] ?? collect();
        $summaryPaginator = $summaryData['paginator'] ?? null;
        $fallbackDate = $summaryData['displayDate'] ?? null;
        $listEmpty = $isSummary ? $summaryGroups->isEmpty() : $groups->isEmpty();
    @endphp

    @if($listEmpty)
        <div class="bg-white rounded-2xl border-2 border-dashed border-zinc-200 p-8 sm:p-12 text-center flex flex-col items-center justify-center min-h-[300px]">
            <div class="bg-zinc-100 text-zinc-400 p-4 rounded-full mb-4 inline-block">
                <svg class="w-8 h-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-zinc-900 mb-2">{{ trim($search) !== '' ? 'Jurnal Tidak Ditemukan' : 'Belum Ada Jurnal' }}</h3>
            <p class="text-zinc-500 text-sm max-w-md mx-auto">{{ trim($search) !== '' ? 'Tidak ada jurnal yang cocok dengan pencarian "' . $search . '".' : 'Belum ada catatan jurnal umum untuk periode ini.' }}</p>
        </div>
    @else
        @if($isSummary)
            {{-- Summary: same columns as detailed + voucher count column. No actions (edit/delete via Detailed). --}}
            <div class="hidden sm:block bg-white rounded-2xl shadow-sm border border-brand-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm whitespace-nowrap">
                        <thead>
                            <tr class="bg-brand-50/80 text-brand-900 border-b border-brand-100">
                                <th class="py-4 px-4 font-semibold uppercase tracking-wider text-xs text-center">Tanggal</th>
                                <th class="py-4 px-4 font-semibold uppercase tracking-wider text-xs text-center">Bukti</th>
                                @if($unitId === 'all')
                                    <th class="py-4 px-4 font-semibold uppercase tracking-wider text-xs text-center">Unit</th>
                                @endif
                                <th class="py-4 px-4 font-semibold uppercase tracking-wider text-xs w-full min-w-[200px]">Keterangan</th>
                                <th class="py-4 px-4 font-semibold uppercase tracking-wider text-xs">Kode Akun</th>
                                <th class="py-4 px-4 font-semibold uppercase tracking-wider text-xs text-center">Debit</th>
                                <th class="py-4 px-4 font-semibold uppercase tracking-wider text-xs text-center">Kredit</th>
                                <th class="py-4 px-4 font-semibold uppercase tracking-wider text-xs text-center">Jml Bukti</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 text-zinc-700">
                            @foreach($summaryGroups as $groupKey => $group)
                                @php
                                    $groupRows = is_array($group) ? $group['rows'] : $group;
                                    $groupDisplayDate = is_array($group) ? ($group['displayDate'] ?? $fallbackDate) : $fallbackDate;
                                    $groupFirst = $groupRows->first();
                                @endphp
                                @foreach($groupRows as $row)
                                    <tr class="hover:bg-zinc-50 transition-colors" wire:key="summary-{{ $row->id }}-{{ $row->account_id }}">
                                        @if($loop->first)
                                            <td rowspan="{{ $groupRows->count() }}" class="py-3 px-4 align-top border-r border-zinc-100">{{ \Carbon\Carbon::parse($groupDisplayDate)->translatedFormat('d F Y') }}</td>
                                            <td rowspan="{{ $groupRows->count() }}" class="py-3 px-4 text-xs text-zinc-500 align-top border-r border-zinc-100">{{ $groupFirst->firstVoucher }}</td>
                                            @if($unitId === 'all')
                                                <td rowspan="{{ $groupRows->count() }}" class="py-3 px-4 text-xs font-semibold text-zinc-600 align-top border-r border-zinc-100">{{ $groupFirst->unitName ?? 'BUMDes' }}</td>
                                            @endif
                                            <td rowspan="{{ $groupRows->count() }}" class="py-3 px-4 text-wrap leading-relaxed align-top border-r border-zinc-100">{{ $groupFirst->description }}</td>
                                        @endif
                                        <td class="py-3 px-4 text-xs border-l border-zinc-100">{{ $row->code ?? '-' }} - {{ $row->accountName ?? '?' }}</td>
                                        <td class="py-3 px-4 text-right font-medium text-brand-700 border-l border-zinc-100">{{ $row->totalDebit > 0 ? number_format($row->totalDebit, 0, ',', '.') : '-' }}</td>
                                        <td class="py-3 px-4 text-right font-medium text-red-600 border-l border-zinc-100">{{ $row->totalCredit > 0 ? number_format($row->totalCredit, 0, ',', '.') : '-' }}</td>
                                        @if($loop->first)
                                            <td rowspan="{{ $groupRows->count() }}" class="py-3 px-4 text-center text-zinc-500 align-middle border-l border-zinc-100">{{ $groupRows->max('voucherCount') }} bukti</td>
                                        @endif
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Mobile summary: one card per merged group --}}
            <div class="flex flex-col gap-3 sm:hidden">
                @foreach($summaryGroups as $groupKey => $group)
                    @php
                        $groupRows = is_array($group) ? $group['rows'] : $group;
                        $groupDisplayDate = is_array($group) ? ($group['displayDate'] ?? $fallbackDate) : $fallbackDate;
                        $groupFirst = $groupRows->first();
                    @endphp
                    <div class="bg-white rounded-2xl border border-zinc-200 shadow-sm overflow-hidden" wire:key="summary-m-{{ $groupFirst->firstVoucher }}-{{ $loop->index }}">
                        <div class="bg-brand-50/60 border-b border-brand-100 px-4 py-3 flex items-start justify-between gap-2">
                            <div>
                                <p class="text-xs text-zinc-500">{{ $groupFirst->firstVoucher }}</p>
                                <p class="text-sm font-semibold text-zinc-800 mt-0.5">{{ \Carbon\Carbon::parse($groupDisplayDate)->translatedFormat('d F Y') }}</p>
                                @if($unitId === 'all')
                                    <span class="inline-block mt-1 text-[11px] font-medium bg-brand-100 text-brand-800 px-2 py-0.5 rounded-md">
                                        {{ $groupFirst->unitName ?? 'BUMDes' }}
                                    </span>
                                @endif
                                <span class="inline-block mt-1 ml-1 text-[11px] font-medium bg-zinc-100 text-zinc-600 px-2 py-0.5 rounded-md">
                                    {{ $groupRows->max('voucherCount') }} bukti
                                </span>
                            </div>
                        </div>
                        <div class="px-4 py-3 border-b border-zinc-100">
                            <p class="text-xs font-medium text-zinc-500 uppercase tracking-wider mb-0.5">Keterangan</p>
                            <p class="text-sm text-zinc-800 leading-relaxed">{{ $groupFirst->description }}</p>
                        </div>
                        <div class="divide-y divide-zinc-100">
                            @foreach($groupRows as $row)
                                <div class="px-4 py-3 flex items-center justify-between gap-3">
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs text-zinc-500 truncate">{{ $row->code ?? '-' }}</p>
                                        <p class="text-sm text-zinc-700 font-medium truncate">{{ $row->accountName ?? '?' }}</p>
                                    </div>
                                    <div class="text-right shrink-0">
                                        @if($row->totalDebit > 0)
                                            <p class="text-xs font-medium text-zinc-400 uppercase tracking-wider">Debit</p>
                                            <p class="text-sm font-bold text-brand-700">Rp {{ number_format($row->totalDebit, 0, ',', '.') }}</p>
                                        @else
                                            <p class="text-xs font-medium text-zinc-400 uppercase tracking-wider">Kredit</p>
                                            <p class="text-sm font-bold text-red-600">Rp {{ number_format($row->totalCredit, 0, ',', '.') }}</p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @else
        {{-- Desktop: table view (hidden on mobile) --}}
        <div class="hidden sm:block bg-white rounded-2xl shadow-sm border border-brand-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm whitespace-nowrap">
                    <thead>
                        <tr class="bg-brand-50/80 text-brand-900 border-b border-brand-100">
                            <th class="py-4 px-4 font-semibold uppercase tracking-wider text-xs text-center">Tanggal</th>
                            <th class="py-4 px-4 font-semibold uppercase tracking-wider text-xs text-center">Bukti</th>
                            @if($unitId === 'all')
                                <th class="py-4 px-4 font-semibold uppercase tracking-wider text-xs text-center">Unit</th>
                            @endif
                            <th class="py-4 px-4 font-semibold uppercase tracking-wider text-xs w-full min-w-[200px]">Keterangan</th>
                            <th class="py-4 px-4 font-semibold uppercase tracking-wider text-xs">Kode Akun</th>
                            <th class="py-4 px-4 font-semibold uppercase tracking-wider text-xs text-center">Debit</th>
                            <th class="py-4 px-4 font-semibold uppercase tracking-wider text-xs text-center">Kredit</th>
                            @if($this->canDelete)
                                <th class="py-4 px-4 font-semibold uppercase tracking-wider text-xs text-center">Aksi</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 text-zinc-700">
                        @foreach($groups as $voucherNumber => $group)
                            @foreach($group as $journal)
                                <tr class="hover:bg-zinc-50 transition-colors">
                                    @if($loop->first)
                                        <td rowspan="{{ $group->count() }}" class="py-3 px-4 align-top border-r border-zinc-100">{{ $journal->transaction_date->translatedFormat('d F Y') }}</td>
                                        <td rowspan="{{ $group->count() }}" class="py-3 px-4 text-xs text-zinc-500 align-top border-r border-zinc-100">{{ $journal->voucher_number }}</td>
                                        @if($unitId === 'all')
                                            <td rowspan="{{ $group->count() }}" class="py-3 px-4 text-xs font-semibold text-zinc-600 align-top border-r border-zinc-100">{{ $journal->businessUnit?->name ?? 'BUMDes' }}</td>
                                        @endif
                                        <td rowspan="{{ $group->count() }}" class="py-3 px-4 text-wrap leading-relaxed align-top border-r border-zinc-100">{{ $journal->description }}</td>
                                    @endif
                                    <td class="py-3 px-4 text-xs border-l border-zinc-100">{{ $journal->account?->code ?? '-' }} - {{ $journal->account?->name ?? '?' }}</td>
                                    <td class="py-3 px-4 text-right font-medium text-brand-700 border-l border-zinc-100">{{ $journal->debit > 0 ? number_format($journal->debit, 0, ',', '.') : '-' }}</td>
                                    <td class="py-3 px-4 text-right font-medium text-red-600 border-l border-zinc-100">{{ $journal->credit > 0 ? number_format($journal->credit, 0, ',', '.') : '-' }}</td>
                                    @if($this->canDelete)
                                        @if($loop->first)
                                            <td rowspan="{{ $group->count() }}" class="py-3 px-4 align-middle text-center border-l border-zinc-100">
                                                <div class="flex flex-row justify-center gap-1.5 items-center">
                                                    @if($this->canDelete && $journal->id)
                                                        <flux:button wire:click="confirmDelete({{ $journal->id }})"
                                                            variant="danger" size="xs" icon="trash" title="Hapus" />
                                                    @endif
                                                </div>
                                            </td>
                                        @endif
                                    @endif
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Mobile: one card per transaction (mobile only) --}}
        <div class="flex flex-col gap-3 sm:hidden">
            @foreach($groups as $voucherNumber => $group)
                @php $firstJournal = $group->first(); @endphp
                <div class="bg-white rounded-2xl border border-zinc-200 shadow-sm overflow-hidden">
                    {{-- Card header --}}
                    <div class="bg-brand-50/60 border-b border-brand-100 px-4 py-3 flex items-start justify-between gap-2">
                        <div>
                            <p class="text-xs text-zinc-500">{{ $firstJournal->voucher_number }}</p>
                            <p class="text-sm font-semibold text-zinc-800 mt-0.5">{{ $firstJournal->transaction_date->translatedFormat('d F Y') }}</p>
                            @if($unitId === 'all')
                                <span class="inline-block mt-1 text-[11px] font-medium bg-brand-100 text-brand-800 px-2 py-0.5 rounded-md">
                                    {{ $firstJournal->businessUnit?->name ?? 'BUMDes' }}
                                </span>
                            @endif
                        </div>
                        <div class="flex gap-1.5 shrink-0 mt-0.5">
                            @if($this->canDelete && $firstJournal->id)
                                <flux:button wire:click="confirmDelete({{ $firstJournal->id }})"
                                    variant="danger" size="xs" icon="trash" />
                            @endif
                        </div>
                    </div>

                    {{-- Description --}}
                    <div class="px-4 py-3 border-b border-zinc-100">
                        <p class="text-xs font-medium text-zinc-500 uppercase tracking-wider mb-0.5">Keterangan</p>
                        <p class="text-sm text-zinc-800 leading-relaxed">{{ $firstJournal->description }}</p>
                    </div>

                    {{-- Journal rows (per debit/credit entry) --}}
                    <div class="divide-y divide-zinc-100">
                        @foreach($group as $journal)
                            <div class="px-4 py-3 flex items-center justify-between gap-3">
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs text-zinc-500 truncate">{{ $journal->account?->code ?? '-' }}</p>
                                    <p class="text-sm text-zinc-700 font-medium truncate">{{ $journal->account?->name ?? '?' }}</p>
                                </div>
                                <div class="text-right shrink-0">
                                    @if($journal->debit > 0)
                                        <p class="text-xs font-medium text-zinc-400 uppercase tracking-wider">Debit</p>
                                        <p class="text-sm font-bold text-brand-700">Rp {{ number_format($journal->debit, 0, ',', '.') }}</p>
                                    @else
                                        <p class="text-xs font-medium text-zinc-400 uppercase tracking-wider">Kredit</p>
                                        <p class="text-sm font-bold text-red-600">Rp {{ number_format($journal->credit, 0, ',', '.') }}</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
        @endif
    @endif

    {{-- Pagination links --}}
    @if($isSummary)
        @if($summaryPaginator && $summaryPaginator->hasPages())
            <div class="mt-6 px-4">
                {{ $summaryPaginator->links() }}
            </div>
        @endif
    @elseif($paginator && $paginator->hasPages())
        <div class="mt-6 px-4">
            {{ $paginator->links() }}
        </div>
    @endif

    {{-- Spacer for the mobile footer to prevent overlap --}}
    <div style="height: 350px; flex-shrink: 0;" class="w-full block sm:hidden"></div>

    {{-- Footer: grand total + print PDF --}}
    <div class="fixed bottom-0 left-0 right-0 z-20 sm:relative sm:bottom-auto sm:left-auto sm:right-auto sm:z-auto sm:mt-2">
        <div class="bg-white sm:rounded-2xl p-5 sm:p-6 pb-[calc(1.25rem+env(safe-area-inset-bottom))] sm:pb-6 shadow-[0_-8px_32px_-8px_rgba(0,0,0,0.1)] sm:shadow-md border-t sm:border border-brand-100 flex flex-col gap-4">
            
            <div class="flex flex-col gap-2 px-1 sm:px-2 mb-1">
                <div class="border-b border-brand-100 pb-2 mb-1 flex justify-between items-center">
                    <span class="text-zinc-500 font-semibold text-xs uppercase tracking-wider">Total Jurnal Umum</span>
                    <span class="text-zinc-600 text-xs font-medium">{{ $this->periodLabel }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-zinc-600 font-semibold text-sm sm:text-base">Total Debit</span>
                    <span class="text-xl sm:text-2xl font-extrabold text-brand-600 tracking-tight whitespace-nowrap">Rp {{ number_format($this->totalDebit, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-zinc-600 font-semibold text-sm sm:text-base">Total Kredit</span>
                    <span class="text-xl sm:text-2xl font-extrabold text-red-600 tracking-tight whitespace-nowrap">Rp {{ number_format($this->totalCredit, 0, ',', '.') }}</span>
                </div>
            </div>

            @if($this->canExportPdf)
                <x-report-actions :preview-url="route('history-recap.journal-preview', [
                        'unit' => $unitId,
                        'mode' => $mode,
                        'date' => $transactionDate,
                        'week' => $week,
                        'month' => $month,
                        'semester' => $semester,
                        'semester_year' => $semesterYear,
                        'year' => $year,
                        'sort' => $sortField,
                        'dir' => $sortDirection,
                        'view' => $viewMode,
                    ])" :card="false" />
            @endif
        </div>
    </div>

    {{-- Delete confirmation modal --}}
    @if ($showDeleteModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div wire:click="$set('showDeleteModal', false)" class="absolute inset-0 bg-zinc-900/40 backdrop-blur-sm transition-opacity"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md flex flex-col gap-0 overflow-hidden z-10">
                <div class="px-6 py-5 flex flex-col gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-zinc-900">Konfirmasi Hapus</h2>
                        <div class="mt-2 text-sm text-zinc-600">
                            <p>Yakin ingin menghapus data ini?</p>
                            <p>Data yang dihapus tidak dapat dikembalikan dan mungkin mempengaruhi kalkulasi laporan.</p>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="$set('showDeleteModal', false)" class="px-4 py-2 rounded-xl border border-zinc-200 text-sm font-semibold text-zinc-700 hover:bg-zinc-50 transition-colors">
                            Batal
                        </button>
                        <button type="button" wire:click="executeDelete" class="px-4 py-2 rounded-xl bg-red-600 text-white text-sm font-semibold hover:bg-red-700 transition-colors">
                            Ya, Hapus
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif


</div>
