<script lang="ts">
    import { onMount } from 'svelte';
    import { page, router } from '@inertiajs/svelte';
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import Chart from 'chart.js/auto';

    const primaryColor = $derived(page.props.theme?.primary_color || '#0c4cb4');
    const secondaryColor = $derived(page.props.theme?.secondary_color || '#fa7315');

    let {
        metrics = { product_revenue: 0, admin_fee_revenue: 0, total_revenue: 0, total_cogs: 0, gross_profit: 0, voucher_discounts: 0, shipping_discounts: 0, total_expenses: 0, net_profit: 0, profit_margin: 0 },
        trendData = [],
        filters = { date_from: '', date_to: '' }
    } = $props();

    let dateFrom = $state(filters.date_from);
    let dateTo = $state(filters.date_to);

    let plCanvas: HTMLCanvasElement;
    let plChart: Chart;

    function applyFilter() {
        router.get('/admin/reports/profit-loss', {
            date_from: dateFrom,
            date_to: dateTo
        }, {
            preserveState: true
        });
    }

    function setQuickPeriod(type: string) {
        const today = new Date();
        let fromDate = new Date();

        if (type === 'this_month') {
            fromDate = new Date(today.getFullYear(), today.getMonth(), 1);
        } else if (type === 'last_month') {
            fromDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            const lastDay = new Date(today.getFullYear(), today.getMonth(), 0);
            dateFrom = fromDate.toISOString().split('T')[0];
            dateTo = lastDay.toISOString().split('T')[0];
            applyFilter();
            return;
        } else if (type === 'this_year') {
            fromDate = new Date(today.getFullYear(), 0, 1);
        }

        dateFrom = fromDate.toISOString().split('T')[0];
        dateTo = today.toISOString().split('T')[0];
        applyFilter();
    }

    function exportToCSV() {
        const csvRows = [
            ['Laporan Laba Rugi', `${dateFrom} s/d ${dateTo}`].join(','),
            [],
            ['Kategori Akun', 'Nilai (Rp)'],
            ['PENDAPATAN', ''],
            ['  Penjualan Produk', Math.round(metrics.product_revenue)],
            ['  Pendapatan Biaya Admin', Math.round(metrics.admin_fee_revenue)],
            ['TOTAL PENDAPATAN KOTOR', Math.round(metrics.total_revenue)],
            [],
            ['HARGA POKOK PENJUALAN (HPP)', ''],
            ['  HPP Barang Terjual', -Math.round(metrics.total_cogs)],
            ['TOTAL HPP', -Math.round(metrics.total_cogs)],
            [],
            ['LABA KOTOR', Math.round(metrics.gross_profit)],
            [],
            ['PENGELUARAN / SUBSIDI', ''],
            ['  Diskon Voucher Toko', -Math.round(metrics.voucher_discounts)],
            ['  Subsidi Ongkos Kirim', -Math.round(metrics.shipping_discounts)],
            ['TOTAL BEBAN OPERASIONAL', -Math.round(metrics.total_expenses)],
            [],
            ['LABA BERSIH (NET PROFIT)', Math.round(metrics.net_profit)],
            ['MARGIN LABA BERSIH', `${metrics.profit_margin.toFixed(2)}%`]
        ];

        const csvContent = "data:text/csv;charset=utf-8," + csvRows.map(e => e.join(',')).join("\n");
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", `laporan_laba_rugi_${dateFrom}_sd_${dateTo}.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    function printReport() {
        window.print();
    }

    function formatRupiah(value: number) {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0
        }).format(value);
    }

    onMount(() => {
        if (plCanvas && trendData.length > 0) {
            const ctx = plCanvas.getContext('2d');
            if (ctx) {
                plChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: trendData.map((d: any) => d.month),
                        datasets: [
                            {
                                label: 'Pendapatan (Rp)',
                                data: trendData.map((d: any) => d.revenue),
                                borderColor: primaryColor,
                                backgroundColor: primaryColor + '22',
                                borderWidth: 2,
                                fill: false,
                                tension: 0.2
                            },
                            {
                                label: 'HPP / COGS (Rp)',
                                data: trendData.map((d: any) => d.cogs),
                                borderColor: '#94a3b8',
                                backgroundColor: '#94a3b822',
                                borderWidth: 2,
                                fill: false,
                                tension: 0.2
                            },
                            {
                                label: 'Laba Bersih (Rp)',
                                data: trendData.map((d: any) => d.net_profit),
                                borderColor: '#10b981',
                                backgroundColor: '#10b98122',
                                borderWidth: 3,
                                fill: false,
                                tension: 0.2
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { font: { size: 10 } }
                            }
                        },
                        scales: {
                            y: {
                                grid: { color: '#f1f5f9' },
                                ticks: {
                                    callback: function(value) {
                                        return formatRupiah(Number(value));
                                    },
                                    font: { size: 9 }
                                }
                            },
                            x: { grid: { display: false }, ticks: { font: { size: 9 } } }
                        }
                    }
                });
            }
        }

        return () => {
            if (plChart) plChart.destroy();
        };
    });
</script>

<svelte:head>
    <title>Laporan Laba Rugi</title>
</svelte:head>

<AdminLayout>
    <main class="flex-grow p-4 sm:p-8 w-full max-w-full mx-auto space-y-6 print:p-0 print:bg-white">
        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 print:hidden">
            <div>
                <h1 class="font-outfit font-black text-2xl sm:text-3xl text-slate-800 tracking-tight">
                    Laporan Laba Rugi (P&L)
                </h1>
                <p class="text-sm text-slate-500 font-medium mt-1">
                    Lembar akuntansi pendapatan operasional, harga pokok penjualan, pengeluaran, dan profitabilitas.
                </p>
            </div>
            
            <div class="flex items-center gap-3 shrink-0">
                <button 
                    onclick={printReport}
                    class="flex items-center justify-center gap-2 px-5 py-3 bg-white border border-slate-200 text-slate-700 font-bold rounded-2xl text-xs hover:bg-slate-50 transition duration-200 shadow-sm uppercase tracking-wider font-outfit"
                >
                    <i class="ti ti-printer text-base"></i>
                    <span>Cetak</span>
                </button>
                <button 
                    onclick={exportToCSV}
                    class="flex items-center justify-center gap-2 px-5 py-3 bg-white border border-slate-200 text-slate-700 font-bold rounded-2xl text-xs hover:bg-slate-50 transition duration-200 shadow-sm uppercase tracking-wider font-outfit"
                >
                    <i class="ti ti-download text-base"></i>
                    <span>Ekspor CSV</span>
                </button>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4 print:hidden">
            <div class="flex flex-col md:flex-row items-end gap-4">
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
                <div class="flex flex-wrap gap-2 w-full md:w-auto">
                    <button 
                        onclick={() => setQuickPeriod('this_month')}
                        class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition"
                    >
                        Bulan Ini
                    </button>
                    <button 
                        onclick={() => setQuickPeriod('last_month')}
                        class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition"
                    >
                        Bulan Lalu
                    </button>
                    <button 
                        onclick={() => setQuickPeriod('this_year')}
                        class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition"
                    >
                        Tahun Ini
                    </button>
                    <button 
                        onclick={applyFilter}
                        class="px-6 py-3 bg-brand-blueRoyal hover:bg-blue-800 text-white font-bold rounded-2xl text-xs transition duration-200 shadow-lg shadow-brand-blueRoyal/20 uppercase tracking-wider font-outfit w-full sm:w-auto ml-auto"
                    >
                        Terapkan
                    </button>
                </div>
            </div>
        </div>

        <!-- Print Heading (Visible on print only) -->
        <div class="hidden print:block text-center space-y-2 mb-6">
            <h2 class="font-outfit font-black text-2xl text-slate-800">Laporan Laba Rugi</h2>
            <p class="text-sm font-bold text-slate-500">Periode: {formatDate(dateFrom)} s/d {formatDate(dateTo)}</p>
            <div class="border-b border-slate-200 my-4"></div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Account Sheet (Left 2 cols) -->
            <div class="md:col-span-2 bg-white rounded-3xl border border-slate-200 shadow-sm p-6 sm:p-8 flex flex-col justify-between">
                <div>
                    <h3 class="font-outfit font-black text-lg text-slate-800 mb-6 border-b border-slate-100 pb-3 uppercase tracking-wider text-xs font-bold text-slate-400">Lembar Akuntansi</h3>
                    <div class="space-y-4 font-medium text-slate-700 text-sm">
                        
                        <!-- PENDAPATAN -->
                        <div class="space-y-2">
                            <h4 class="font-black text-slate-800 border-b border-slate-100 pb-1 uppercase tracking-wider text-xs">I. PENDAPATAN</h4>
                            <div class="flex justify-between pl-4">
                                <span class="text-slate-600">Penjualan Produk (Omset Subtotal)</span>
                                <span class="font-semibold text-slate-800">{formatRupiah(metrics.product_revenue)}</span>
                            </div>
                            <div class="flex justify-between pl-4">
                                <span class="text-slate-600">Pendapatan Biaya Admin</span>
                                <span class="font-semibold text-slate-800">{formatRupiah(metrics.admin_fee_revenue)}</span>
                            </div>
                            <div class="flex justify-between pl-4 border-t border-dashed border-slate-100 pt-2 font-bold">
                                <span class="text-slate-800 uppercase text-xs">Total Pendapatan Kotor</span>
                                <span class="text-slate-900">{formatRupiah(metrics.total_revenue)}</span>
                            </div>
                        </div>

                        <!-- HPP -->
                        <div class="space-y-2 pt-4">
                            <h4 class="font-black text-slate-800 border-b border-slate-100 pb-1 uppercase tracking-wider text-xs">II. HARGA POKOK PENJUALAN (HPP)</h4>
                            <div class="flex justify-between pl-4">
                                <span class="text-slate-600">HPP Barang Terjual (COGS)</span>
                                <span class="font-semibold text-rose-500">- {formatRupiah(metrics.total_cogs)}</span>
                            </div>
                            <div class="flex justify-between pl-4 border-t border-dashed border-slate-100 pt-2 font-bold">
                                <span class="text-slate-800 uppercase text-xs">Total Harga Pokok Penjualan</span>
                                <span class="text-rose-600">- {formatRupiah(metrics.total_cogs)}</span>
                            </div>
                        </div>

                        <!-- LABA KOTOR -->
                        <div class="bg-slate-50 rounded-2xl p-4 flex justify-between items-center font-black mt-6 border border-slate-100">
                            <span class="text-slate-800 uppercase tracking-wider text-xs">III. LABA KOTOR (GROSS PROFIT)</span>
                            <span class="text-slate-900 text-base">{formatRupiah(metrics.gross_profit)}</span>
                        </div>

                        <!-- PENGELUARAN -->
                        <div class="space-y-2 pt-4">
                            <h4 class="font-black text-slate-800 border-b border-slate-100 pb-1 uppercase tracking-wider text-xs">IV. BEBAN & PENGELUARAN</h4>
                            <div class="flex justify-between pl-4">
                                <span class="text-slate-600">Subsidi Diskon Voucher Toko</span>
                                <span class="font-semibold text-rose-500">- {formatRupiah(metrics.voucher_discounts)}</span>
                            </div>
                            <div class="flex justify-between pl-4">
                                <span class="text-slate-600">Subsidi Ongkos Kirim</span>
                                <span class="font-semibold text-rose-500">- {formatRupiah(metrics.shipping_discounts)}</span>
                            </div>
                            <div class="flex justify-between pl-4 border-t border-dashed border-slate-100 pt-2 font-bold">
                                <span class="text-slate-800 uppercase text-xs">Total Beban Operasional</span>
                                <span class="text-rose-600">- {formatRupiah(metrics.total_expenses)}</span>
                            </div>
                        </div>

                        <!-- LABA BERSIH -->
                        <div class="bg-emerald-50 rounded-2xl p-5 flex justify-between items-center font-black mt-8 border border-emerald-100 text-emerald-800">
                            <span class="uppercase tracking-wider text-xs">V. LABA BERSIH (NET PROFIT)</span>
                            <span class="text-lg">{formatRupiah(metrics.net_profit)}</span>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Financial Metrics & Trend (Right 1 col) -->
            <div class="space-y-6 print:hidden">
                <!-- Ratio Margin -->
                <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
                    <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit mb-1">Rasio Margin Laba Bersih</h4>
                    <h3 class="font-outfit font-black text-3xl text-emerald-600">
                        {metrics.profit_margin.toFixed(2)}%
                    </h3>
                    <div class="w-full bg-slate-100 h-2 rounded-full mt-3 overflow-hidden">
                        <div 
                            class="bg-emerald-500 h-full rounded-full" 
                            style="width: {Math.max(0, Math.min(100, metrics.profit_margin))}%"
                        ></div>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-2 font-medium">Persentase laba bersih yang diserap dari total omset kotor.</p>
                </div>

                <!-- Monthly Trend Chart -->
                <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
                    <h4 class="font-outfit font-black text-sm text-slate-800 mb-4">Tren Laba Rugi Bulanan</h4>
                    <div class="h-64 w-full">
                        {#if trendData.length === 0}
                            <div class="h-full flex items-center justify-center text-xs text-slate-400">Tidak ada data tren bulanan.</div>
                        {:else}
                            <canvas bind:this={plCanvas}></canvas>
                        {/if}
                    </div>
                </div>
            </div>
        </div>
    </main>
</AdminLayout>

<script lang="ts" context="module">
    function formatDate(dateStr: string) {
        if (!dateStr) return '';
        return new Date(dateStr).toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'long',
            year: 'numeric'
        });
    }
</script>
