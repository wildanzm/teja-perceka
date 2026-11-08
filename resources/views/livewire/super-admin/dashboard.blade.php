<div class="space-y-6">
    <x-page-header title="Dashboard" description="Tinjauan ringkas performa keuangan seluruh unit BUMDes pada bulan ini.">
        <x-slot:actions>
            <a href="{{ route('super-admin.users') }}" wire:navigate class="inline-flex items-center gap-2 px-4 py-2 bg-brand-500 hover:bg-brand-600 text-white font-semibold text-sm rounded-xl transition-colors shadow-sm">
                <flux:icon icon="users" class="size-4" />
                <span>Manajemen User</span>
            </a>
            <a href="{{ route('super-admin.activity-logs') }}" wire:navigate class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-zinc-200 text-zinc-700 hover:bg-zinc-50 font-semibold text-sm rounded-xl transition-colors shadow-sm">
                <flux:icon icon="clock" class="size-4 text-zinc-500" />
                <span>Log Aktivitas</span>
            </a>
        </x-slot:actions>
    </x-page-header>

    <!-- Stat cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div class="bg-gradient-to-br from-emerald-50 via-white to-white border border-emerald-200/80 rounded-2xl p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-zinc-500">Total Pengguna</p>
                    <h3 class="text-2xl font-bold text-zinc-900 mt-1">{{ number_format($totalUsers) }}</h3>
                </div>
                <div class="p-3 bg-emerald-50 text-emerald-600 ring-1 ring-emerald-200/70 rounded-xl">
                    <flux:icon icon="users" class="size-6" />
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-cyan-50 via-white to-white border border-cyan-200/80 rounded-2xl p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-zinc-500">Unit Usaha</p>
                    <h3 class="text-2xl font-bold text-zinc-900 mt-1">{{ number_format($totalUnits) }}</h3>
                </div>
                <div class="p-3 bg-cyan-50 text-cyan-600 ring-1 ring-cyan-200/70 rounded-xl">
                    <flux:icon icon="building-office-2" class="size-6" />
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-purple-50 via-white to-white border border-purple-200/80 rounded-2xl p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-zinc-500">Jumlah Role</p>
                    <h3 class="text-2xl font-bold text-zinc-900 mt-1">{{ number_format($totalRoles) }}</h3>
                </div>
                <div class="p-3 bg-purple-50 text-purple-600 ring-1 ring-purple-200/70 rounded-xl">
                    <flux:icon icon="key" class="size-6" />
                </div>
            </div>
        </div>

        <div class="bg-gradient-to-br from-amber-50 via-white to-white border border-amber-200/80 rounded-2xl p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-zinc-500">Aktivitas Hari Ini</p>
                    <h3 class="text-2xl font-bold text-zinc-900 mt-1">{{ number_format($todayActivities) }}</h3>
                </div>
                <div class="p-3 bg-amber-50 text-amber-600 ring-1 ring-amber-200/70 rounded-xl">
                    <flux:icon icon="bolt" class="size-6" />
                </div>
            </div>
        </div>
    </div>

    <!-- Activity Monitoring Chart - Full Width -->
    <div class="bg-white border border-zinc-200 rounded-2xl p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-6 gap-2">
            <div>
                <h2 class="text-lg font-bold text-zinc-900">Grafik Monitoring Penggunaan User</h2>
                <p class="text-xs text-zinc-500">Trend aktivitas harian & penggunaan fitur penyamaran (7 Hari Terakhir)</p>
            </div>
            <a href="{{ route('super-admin.activity-logs') }}" wire:navigate class="text-xs font-semibold text-brand-600 hover:text-brand-700">Detail Audit Log →</a>
        </div>

        <div class="w-full relative" style="height: 350px;">
            <canvas id="activityMonitorChart"></canvas>
        </div>
    </div>
</div>

@script
<script>
    (function () {
        const chartDates = @json($chartDates);
        const chartActivityCounts = @json($chartActivityCounts);
        const chartImpersonateCounts = @json($chartImpersonateCounts);

        function initChart() {
            const canvas = document.getElementById('activityMonitorChart');
            if (!canvas) return;

            // Destroy existing chart instance if any (Livewire re-render)
            if (window._activityChartInstance) {
                window._activityChartInstance.destroy();
            }

            const ctx = canvas.getContext('2d');
            window._activityChartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartDates,
                    datasets: [
                        {
                            label: 'Total Aktivitas Pengguna',
                            data: chartActivityCounts,
                            borderColor: '#249e24',
                            backgroundColor: 'rgba(36, 158, 36, 0.08)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.35,
                            pointRadius: 5,
                            pointHoverRadius: 7,
                            pointBackgroundColor: '#249e24',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                        },
                        {
                            label: 'Aktivitas Penyamaran',
                            data: chartImpersonateCounts,
                            borderColor: '#f59e0b',
                            backgroundColor: 'rgba(245, 158, 11, 0.08)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.35,
                            pointRadius: 5,
                            pointHoverRadius: 7,
                            pointBackgroundColor: '#f59e0b',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                padding: 20,
                                font: { family: 'Poppins', size: 12 }
                            }
                        },
                        tooltip: {
                            padding: 12,
                            cornerRadius: 10,
                            titleFont: { family: 'Poppins' },
                            bodyFont: { family: 'Poppins' },
                        }
                    },
                    scales: {
                        x: {
                            grid: { color: 'rgba(0,0,0,0.04)' },
                            ticks: { font: { family: 'Poppins', size: 11 } }
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(0,0,0,0.04)' },
                            ticks: {
                                stepSize: 1,
                                precision: 0,
                                font: { family: 'Poppins', size: 11 }
                            }
                        }
                    }
                }
            });
        }

        initChart();
    })();
</script>
@endscript
