<script lang="ts">
    import { onMount } from 'svelte';
    import { page, router } from '@inertiajs/svelte';
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import Chart from 'chart.js/auto';

    const primaryColor = $derived(page.props.theme?.primary_color || '#0c4cb4');
    const secondaryColor = $derived(page.props.theme?.secondary_color || '#fa7315');

    let { 
        metrics = { net_sales: 0, gross_sales: 0, total_discount: 0, total_shipping: 0, total_admin: 0, order_count: 0, items_sold: 0, aov: 0 },
        salesTrend = [],
        paymentDistribution = [],
        statusDistribution = [],
        chartData = { labels: [], revenue: [], orders: [] },
        filters = { date_from: '', date_to: '' }
    } = $props();

    // svelte-ignore state_referenced_locally
    let dateFrom = $state(filters.date_from);
    // svelte-ignore state_referenced_locally
    let dateTo = $state(filters.date_to);

    let trendCanvas: HTMLCanvasElement | undefined = $state();
    let paymentCanvas: HTMLCanvasElement | undefined = $state();
    let statusCanvas: HTMLCanvasElement | undefined = $state();

    let trendChart: Chart;
    let paymentChart: Chart;
    let statusChart: Chart;

    function applyFilter() {
        router.get('/admin/reports/sales', {
            date_from: dateFrom,
            date_to: dateTo
        }, {
            preserveState: true
        });
    }

    function exportToCSV() {
        const headers = ['Tanggal', 'Jumlah Pesanan', 'Omset Kotor', 'Total Diskon', 'Total Ongkir', 'Total Admin Fee', 'Penjualan Bersih'];
        const csvRows = [headers.join(',')];

        salesTrend.forEach((t: any) => {
            const row = [
                formatDate(t.date),
                t.order_count,
                Math.round(t.gross_sales),
                Math.round(t.total_discount),
                Math.round(t.total_shipping),
                Math.round(t.total_admin),
                Math.round(t.net_sales)
            ];
            csvRows.push(row.join(','));
        });

        const csvContent = "data:text/csv;charset=utf-8," + csvRows.join("\n");
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", `laporan_penjualan_${dateFrom}_sd_${dateTo}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    function formatRupiah(value: number) {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0
        }).format(value);
    }

    function formatDate(dateStr: string) {
        return new Date(dateStr).toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        });
    }

    onMount(() => {
        // 1. Line Chart: Tren Penjualan Harian
        if (trendCanvas) {
            const ctx = trendCanvas.getContext('2d');
            if (ctx) {
                let gradient = ctx.createLinearGradient(0, 0, 0, 300);
                gradient.addColorStop(0, primaryColor + '33');
                gradient.addColorStop(1, primaryColor + '00');

                trendChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: [...chartData.labels],
                        datasets: [
                            {
                                label: 'Penjualan Bersih (Rp)',
                                data: [...chartData.revenue],
                                borderColor: primaryColor,
                                backgroundColor: gradient,
                                borderWidth: 3,
                                fill: true,
                                tension: 0.3,
                                pointBackgroundColor: '#ffffff',
                                pointBorderColor: primaryColor,
                                pointBorderWidth: 2,
                                pointRadius: 4,
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return 'Penjualan: ' + formatRupiah(context.parsed.y);
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                grid: { color: '#f1f5f9' },
                                ticks: {
                                    callback: function(value) {
                                        return formatRupiah(Number(value));
                                    },
                                    font: { size: 10 }
                                }
                            },
                            x: { grid: { display: false }, ticks: { font: { size: 10 } } }
                        }
                    }
                });
            }
        }

        // 2. Doughnut Chart: Distribusi Metode Pembayaran
        if (paymentCanvas) {
            const ctx = paymentCanvas.getContext('2d');
            if (ctx) {
                paymentChart = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: paymentDistribution.map((p: any) => p.name),
                        datasets: [
                            {
                                data: paymentDistribution.map((p: any) => p.count),
                                backgroundColor: [
                                    primaryColor,
                                    secondaryColor,
                                    '#8b5cf6',
                                    '#06b6d4',
                                    '#10b981',
                                    '#f43f5e'
                                ],
                                borderWidth: 2,
                                hoverOffset: 4
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { boxWidth: 12, font: { size: 11 } }
                            }
                        }
                    }
                });
            }
        }

        // 3. Doughnut Chart: Distribusi Status Transaksi
        if (statusCanvas) {
            const ctx = statusCanvas.getContext('2d');
            if (ctx) {
                const statusLabelsMap: any = {
                    'belum_bayar': 'Belum Bayar',
                    'menunggu': 'Menunggu',
                    'diproses': 'Diproses',
                    'dikemas': 'Dikemas',
                    'dikirim': 'Dikirim',
                    'selesai': 'Selesai',
                    'batal': 'Batal'
                };

                const statusColorsMap: any = {
                    'belum_bayar': '#94a3b8',
                    'menunggu': '#eab308',
                    'diproses': '#6366f1',
                    'dikemas': '#a855f7',
                    'dikirim': '#06b6d4',
                    'selesai': '#10b981',
                    'batal': '#ef4444'
                };

                statusChart = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: statusDistribution.map((s: any) => statusLabelsMap[s.status] || s.status),
                        datasets: [
                            {
                                data: statusDistribution.map((s: any) => s.count),
                                backgroundColor: statusDistribution.map((s: any) => statusColorsMap[s.status] || '#cbd5e1'),
                                borderWidth: 2,
                                hoverOffset: 4
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { boxWidth: 12, font: { size: 11 } }
                            }
                        }
                    }
                });
            }
        }

        return () => {
            if (trendChart) trendChart.destroy();
            if (paymentChart) paymentChart.destroy();
            if (statusChart) statusChart.destroy();
        };
    });
</script>

<svelte:head>
    <title>Laporan Penjualan</title>
</svelte:head>

<AdminLayout>
    <main class="flex-grow p-4 sm:p-8 w-full max-w-[1600px] mx-auto space-y-6">
        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h1 class="font-outfit font-black text-2xl sm:text-3xl text-slate-800 tracking-tight">
                    Laporan Penjualan
                </h1>
                <p class="text-sm text-slate-500 font-medium mt-1">
                    Analisis performa omset, transaksi, dan metode pembayaran toko Anda.
                </p>
            </div>
            
            <button 
                onclick={exportToCSV}
                class="flex items-center justify-center gap-2 px-5 py-3 bg-white border border-slate-200 text-slate-700 font-bold rounded-2xl text-xs hover:bg-slate-50 transition duration-200 shadow-sm uppercase tracking-wider font-outfit shrink-0"
            >
                <i class="ti ti-download text-base"></i>
                <span>Ekspor CSV</span>
            </button>
        </div>

        <!-- Filter Card -->
        <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
            <div class="flex flex-col sm:flex-row items-end gap-4">
                <div class="flex-grow grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label for="date_from" class="text-xs font-bold text-slate-500 uppercase tracking-wider">Tanggal Mulai</label>
                        <input 
                            type="date" 
                            id="date_from"
                            bind:value={dateFrom}
                            class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-sm font-semibold rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-blueRoyal/20 focus:border-brand-blueRoyal transition"
                        />
                    </div>
                    <div class="space-y-1.5">
                        <label for="date_to" class="text-xs font-bold text-slate-500 uppercase tracking-wider">Tanggal Selesai</label>
                        <input 
                            type="date" 
                            id="date_to"
                            bind:value={dateTo}
                            class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-sm font-semibold rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-blueRoyal/20 focus:border-brand-blueRoyal transition"
                        />
                    </div>
                </div>
                <button 
                    onclick={applyFilter}
                    class="w-full sm:w-auto px-6 py-3 bg-brand-blueRoyal hover:bg-blue-800 text-white font-bold rounded-2xl text-xs transition duration-200 shadow-lg shadow-brand-blueRoyal/20 uppercase tracking-wider font-outfit"
                >
                    Terapkan Filter
                </button>
            </div>
        </div>

        <!-- KPI Cards Grid -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- Card 1: Omset Bersih -->
            <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm flex flex-col justify-between">
                <div>
                    <span class="w-8 h-8 rounded-lg flex items-center justify-center text-lg text-emerald-600 bg-emerald-50 mb-3">
                        <i class="ti ti-wallet"></i>
                    </span>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit">Penjualan Bersih</p>
                    <h3 class="font-outfit font-black text-xl sm:text-2xl text-slate-800 mt-1">
                        {formatRupiah(metrics.net_sales)}
                    </h3>
                </div>
            </div>

            <!-- Card 2: Jumlah Pesanan -->
            <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm flex flex-col justify-between">
                <div>
                    <span class="w-8 h-8 rounded-lg flex items-center justify-center text-lg text-blue-600 bg-blue-50 mb-3">
                        <i class="ti ti-shopping-cart"></i>
                    </span>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit">Total Pesanan</p>
                    <h3 class="font-outfit font-black text-xl sm:text-2xl text-slate-800 mt-1">
                        {metrics.order_count}
                    </h3>
                </div>
            </div>

            <!-- Card 3: Rata-rata Nilai Belanja (AOV) -->
            <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm flex flex-col justify-between">
                <div>
                    <span class="w-8 h-8 rounded-lg flex items-center justify-center text-lg text-purple-600 bg-purple-50 mb-3">
                        <i class="ti ti-receipt"></i>
                    </span>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit">Rata-rata Keranjang (AOV)</p>
                    <h3 class="font-outfit font-black text-xl sm:text-2xl text-slate-800 mt-1">
                        {formatRupiah(metrics.aov)}
                    </h3>
                </div>
            </div>

            <!-- Card 4: Kuantitas Terjual -->
            <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm flex flex-col justify-between">
                <div>
                    <span class="w-8 h-8 rounded-lg flex items-center justify-center text-lg text-rose-600 bg-rose-50 mb-3">
                        <i class="ti ti-package"></i>
                    </span>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit">Produk Terjual</p>
                    <h3 class="font-outfit font-black text-xl sm:text-2xl text-slate-800 mt-1">
                        {metrics.items_sold} pcs
                    </h3>
                </div>
            </div>
        </div>

        <!-- Charts Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Tren Line Chart -->
            <div class="lg:col-span-2 bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
                <div class="mb-4">
                    <h3 class="font-outfit font-black text-lg text-slate-800">Tren Pendapatan Bersih</h3>
                    <p class="text-xs text-slate-500 font-medium">Grafik fluktuasi penjualan harian.</p>
                </div>
                <div class="h-80 w-full">
                    <canvas bind:this={trendCanvas}></canvas>
                </div>
            </div>

            <!-- Distribusi Pembayaran & Status -->
            <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm flex flex-col gap-6">
                <div>
                    <h3 class="font-outfit font-black text-lg text-slate-800">Metode Pembayaran</h3>
                    <p class="text-xs text-slate-500 font-medium">Berdasarkan volume pesanan lunas.</p>
                    <div class="h-44 w-full mt-4">
                        {#if paymentDistribution.length === 0}
                            <div class="h-full flex items-center justify-center text-xs text-slate-400">Tidak ada data pembayaran.</div>
                        {:else}
                            <canvas bind:this={paymentCanvas}></canvas>
                        {/if}
                    </div>
                </div>

                <div class="border-t border-slate-100 pt-6">
                    <h3 class="font-outfit font-black text-lg text-slate-800">Status Transaksi</h3>
                    <p class="text-xs text-slate-500 font-medium">Distribusi seluruh pesanan masuk.</p>
                    <div class="h-44 w-full mt-4">
                        {#if statusDistribution.length === 0}
                            <div class="h-full flex items-center justify-center text-xs text-slate-400">Tidak ada data transaksi.</div>
                        {:else}
                            <canvas bind:this={statusCanvas}></canvas>
                        {/if}
                    </div>
                </div>
            </div>
        </div>

        <!-- Aggregated Table -->
        <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-sm">
            <div class="p-6 border-b border-slate-100">
                <h3 class="font-outfit font-black text-lg text-slate-800">Rincian Penjualan Harian</h3>
                <p class="text-xs text-slate-500 font-medium">Data transaksi yang dikelompokkan per hari.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[900px]">
                    <thead>
                        <tr class="bg-slate-50/50 text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit border-b border-slate-100">
                            <th class="py-4 px-6">Tanggal</th>
                            <th class="py-4 px-6 text-center">Jumlah Pesanan</th>
                            <th class="py-4 px-6 text-right">Omset Kotor</th>
                            <th class="py-4 px-6 text-right">Subsidi Diskon</th>
                            <th class="py-4 px-6 text-right">Ongkos Kirim</th>
                            <th class="py-4 px-6 text-right">Biaya Admin</th>
                            <th class="py-4 px-6 text-right">Penjualan Bersih</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700 text-sm font-medium">
                        {#if salesTrend.length === 0}
                            <tr>
                                <td colspan="7" class="py-12 text-center text-slate-400 font-bold font-outfit">
                                    <i class="ti ti-receipt-off text-3xl block mb-2 text-slate-300"></i>
                                    Tidak ada data penjualan pada periode ini.
                                </td>
                            </tr>
                        {:else}
                            {#each salesTrend as row}
                                <tr class="hover:bg-slate-50/50 transition">
                                    <td class="py-4 px-6 font-bold text-slate-800">{formatDate(row.date)}</td>
                                    <td class="py-4 px-6 text-center text-slate-600 font-semibold">{row.order_count}</td>
                                    <td class="py-4 px-6 text-right text-slate-600 font-semibold">{formatRupiah(row.gross_sales)}</td>
                                    <td class="py-4 px-6 text-right text-rose-500 font-semibold">- {formatRupiah(row.total_discount)}</td>
                                    <td class="py-4 px-6 text-right text-slate-500 font-semibold">{formatRupiah(row.total_shipping)}</td>
                                    <td class="py-4 px-6 text-right text-emerald-600 font-semibold">+ {formatRupiah(row.total_admin)}</td>
                                    <td class="py-4 px-6 text-right font-black text-slate-800">{formatRupiah(row.net_sales)}</td>
                                </tr>
                            {/each}
                        {/if}
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</AdminLayout>
