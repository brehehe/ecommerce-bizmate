<script lang="ts">
    import { onMount } from 'svelte';
    import { page, router } from '@inertiajs/svelte';
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import Chart from 'chart.js/auto';
    import Pagination from '@/components/ui/Pagination.svelte';

    const primaryColor = $derived(page.props.theme?.primary_color || '#0c4cb4');
    const secondaryColor = $derived(
        page.props.theme?.secondary_color || '#fa7315',
    );
    const storeName = $derived((page.props as any).settings?.store_name || 'Bizmate');

    let {
        metrics = {
            net_sales: 0,
            gross_sales: 0,
            total_discount: 0,
            total_shipping: 0,
            total_admin: 0,
            order_count: 0,
            items_sold: 0,
            aov: 0,
        },
        salesTrend = [],
        salesTrendPaginated = { data: [], links: [], total: 0 },
        transactions = { data: [], links: [], total: 0 },
        paymentDistribution = [],
        statusDistribution = [],
        paymentMethods = [],
        chartData = { labels: [], revenue: [], orders: [] },
        filters = { date_from: '', date_to: '', search: '', payment_method_id: 'all', status: 'all', preset: '', per_page: 15 },
    } = $props();

    // svelte-ignore state_referenced_locally
    let dateFrom = $state(filters.date_from);
    // svelte-ignore state_referenced_locally
    let dateTo = $state(filters.date_to);
    // svelte-ignore state_referenced_locally
    let activePreset = $state(filters.preset || 'bulanan');
    // svelte-ignore state_referenced_locally
    let searchInput = $state(filters.search || '');
    // svelte-ignore state_referenced_locally
    let paymentMethodFilter = $state(filters.payment_method_id || 'all');
    // svelte-ignore state_referenced_locally
    let statusFilter = $state(filters.status || 'all');
    // svelte-ignore state_referenced_locally
    let perPageFilter = $state(filters.per_page || 15);

    let activeTab = $state('daily');

    let trendCanvas: HTMLCanvasElement | undefined;
    let paymentCanvas: HTMLCanvasElement | undefined;
    let statusCanvas: HTMLCanvasElement | undefined;

    let trendChart: Chart | undefined;
    let paymentChart: Chart | undefined;
    let statusChart: Chart | undefined;

    function formatDateLocal(date: Date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function selectPreset(preset: string) {
        activePreset = preset;
        const now = new Date();
        let fromDate = new Date();

        if (preset === 'harian') {
            fromDate = now;
        } else if (preset === 'mingguan') {
            fromDate.setDate(now.getDate() - 6);
        } else if (preset === 'bulanan') {
            fromDate.setDate(now.getDate() - 29);
        } else if (preset === 'tahunan') {
            fromDate = new Date(now.getFullYear(), 0, 1);
        }

        dateFrom = formatDateLocal(fromDate);
        dateTo = formatDateLocal(now);

        router.get(
            '/admin/reports/sales',
            {
                date_from: dateFrom,
                date_to: dateTo,
                preset: activePreset,
                search: searchInput,
                payment_method_id: paymentMethodFilter,
                status: statusFilter,
                per_page: perPageFilter,
            },
            {
                preserveState: true,
            },
        );
    }

    function applyFilter() {
        router.get(
            '/admin/reports/sales',
            {
                date_from: dateFrom,
                date_to: dateTo,
                preset: activePreset,
                search: searchInput,
                payment_method_id: paymentMethodFilter,
                status: statusFilter,
                per_page: perPageFilter,
            },
            {
                preserveState: true,
            },
        );
    }

    function resetFilters() {
        dateFrom = formatDateLocal(new Date(new Date().setDate(new Date().getDate() - 29)));
        dateTo = formatDateLocal(new Date());
        activePreset = 'bulanan';
        searchInput = '';
        paymentMethodFilter = 'all';
        statusFilter = 'all';
        perPageFilter = 15;
        
        router.get(
            '/admin/reports/sales',
            {
                date_from: dateFrom,
                date_to: dateTo,
                preset: 'bulanan',
                search: '',
                payment_method_id: 'all',
                status: 'all',
                per_page: 15,
            },
            {
                preserveState: true,
            },
        );
    }

    let searchTimeout: ReturnType<typeof setTimeout>;
    function handleSearchInput() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(applyFilter, 300);
    }

    function exportToCSV() {
        const headers = [
            'Tanggal',
            'Jumlah Pesanan',
            'Omset Kotor',
            'Total Diskon',
            'Total Ongkir',
            'Total Admin Fee',
            'Penjualan Bersih',
        ];
        const csvRows = [headers.join(',')];

        salesTrend.forEach((t: any) => {
            const row = [
                formatDate(t.date),
                t.order_count,
                Math.round(t.gross_sales),
                Math.round(t.total_discount),
                Math.round(t.total_shipping),
                Math.round(t.total_admin),
                Math.round(t.net_sales),
            ];
            csvRows.push(row.join(','));
        });

        const csvContent = 'data:text/csv;charset=utf-8,' + csvRows.join('\n');
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement('a');
        link.setAttribute('href', encodedUri);
        link.setAttribute(
            'download',
            `laporan_penjualan_${dateFrom}_sd_${dateTo}.csv`,
        );
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    function formatRupiah(value: number) {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
        }).format(value);
    }

    function formatDate(dateStr: string) {
        if (!dateStr) return '—';
        return new Date(dateStr).toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        });
    }

    function formatTime(dateStr: string) {
        if (!dateStr) return '';
        return new Date(dateStr).toLocaleTimeString('id-ID', {
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    const statusLabels: Record<string, string> = {
        belum_bayar: 'Belum Bayar',
        menunggu: 'Menunggu',
        diproses: 'Diproses',
        dikemas: 'Dikemas',
        dikirim: 'Dikirim',
        selesai: 'Selesai',
        batal: 'Batal',
    };

    const statusColors: Record<string, { bg: string; text: string }> = {
        belum_bayar: { bg: '#f1f5f9', text: '#475569' },
        menunggu: { bg: '#fef3c7', text: '#d97706' },
        diproses: { bg: '#e0f2fe', text: '#0284c7' },
        dikemas: { bg: '#fae8ff', text: '#c026d3' },
        dikirim: { bg: '#e0e7ff', text: '#4f46e5' },
        selesai: { bg: '#dcfce7', text: '#16a34a' },
        batal: { bg: '#fee2e2', text: '#dc2626' },
    };

    onMount(() => {
        const cleanChartData = $state.snapshot(chartData);
        const cleanPaymentDistribution = $state.snapshot(paymentDistribution);
        const cleanStatusDistribution = $state.snapshot(statusDistribution);

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
                        labels: [...cleanChartData.labels],
                        datasets: [
                            {
                                label: 'Penjualan Bersih (Rp)',
                                data: [...cleanChartData.revenue],
                                borderColor: primaryColor,
                                backgroundColor: gradient,
                                borderWidth: 3,
                                fill: true,
                                tension: 0.3,
                                pointBackgroundColor: '#ffffff',
                                pointBorderColor: primaryColor,
                                pointBorderWidth: 2,
                                pointRadius: 4,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        return (
                                            'Penjualan: ' +
                                            formatRupiah(context.parsed.y)
                                        );
                                    },
                                },
                            },
                        },
                        scales: {
                            y: {
                                grid: { color: '#f1f5f9' },
                                ticks: {
                                    callback: function (value) {
                                        return formatRupiah(Number(value));
                                    },
                                    font: { size: 10 },
                                },
                            },
                            x: {
                                grid: { display: false },
                                ticks: { font: { size: 10 } },
                            },
                        },
                    },
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
                        labels: cleanPaymentDistribution.map((p: any) => p.name),
                        datasets: [
                            {
                                data: cleanPaymentDistribution.map(
                                    (p: any) => p.count,
                                ),
                                backgroundColor: [
                                    primaryColor,
                                    secondaryColor,
                                    '#8b5cf6',
                                    '#06b6d4',
                                    '#10b981',
                                    '#f43f5e',
                                ],
                                borderWidth: 2,
                                hoverOffset: 4,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { boxWidth: 12, font: { size: 11 } },
                            },
                        },
                    },
                });
            }
        }

        // 3. Doughnut Chart: Distribusi Status Transaksi
        if (statusCanvas) {
            const ctx = statusCanvas.getContext('2d');
            if (ctx) {
                const statusLabelsMap: any = {
                    belum_bayar: 'Belum Bayar',
                    menunggu: 'Menunggu',
                    diproses: 'Diproses',
                    dikemas: 'Dikemas',
                    dikirim: 'Dikirim',
                    selesai: 'Selesai',
                    batal: 'Batal',
                };

                const statusColorsMap: any = {
                    belum_bayar: '#94a3b8',
                    menunggu: '#eab308',
                    diproses: '#6366f1',
                    dikemas: '#a855f7',
                    dikirim: '#06b6d4',
                    selesai: '#10b981',
                    batal: '#ef4444',
                };

                statusChart = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: cleanStatusDistribution.map(
                            (s: any) => statusLabelsMap[s.status] || s.status,
                        ),
                        datasets: [
                            {
                                data: cleanStatusDistribution.map(
                                    (s: any) => s.count,
                                ),
                                backgroundColor: cleanStatusDistribution.map(
                                    (s: any) =>
                                        statusColorsMap[s.status] || '#cbd5e1',
                                ),
                                borderWidth: 2,
                                hoverOffset: 4,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { boxWidth: 12, font: { size: 11 } },
                            },
                        },
                    },
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
    <title>Laporan Penjualan — {storeName}</title>
</svelte:head>

<AdminLayout>
    <main class="flex-grow p-4 sm:p-8 w-full max-w-[1600px] mx-auto space-y-6 print:p-0 print:bg-white">
        <!-- Print Header -->
        <div class="hidden print:block text-center space-y-1.5 mb-6">
            <h1 class="font-outfit font-black text-2xl text-slate-800 tracking-tight">{storeName}</h1>
            <h2 class="font-outfit font-bold text-lg text-slate-700">Laporan Penjualan</h2>
            <p class="text-xs text-slate-500 font-medium">Periode: {formatDate(dateFrom)} s/d {formatDate(dateTo)}</p>
            <p class="text-[10px] text-slate-400">Dicetak pada: {new Date().toLocaleString('id-ID')}</p>
        </div>

        <!-- Page Header -->
        <div
            class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 print:hidden"
        >
            <div>
                <h1
                    class="font-outfit font-black text-2xl sm:text-3xl text-slate-800 tracking-tight"
                >
                    Laporan Penjualan
                </h1>
                <p class="text-sm text-slate-500 font-medium mt-1">
                    Analisis performa omset, transaksi, dan metode pembayaran
                    toko Anda.
                </p>
            </div>

            <div class="flex items-center gap-3 shrink-0 w-full sm:w-auto">
                <button
                    onclick={() => window.print()}
                    class="flex-grow sm:flex-grow-0 flex items-center justify-center gap-2 px-5 py-3 bg-white border border-slate-200 text-slate-700 font-bold rounded-2xl text-xs hover:bg-slate-50 transition duration-200 shadow-sm uppercase tracking-wider font-outfit shrink-0 cursor-pointer"
                >
                    <i class="ti ti-printer text-base"></i>
                    <span>Cetak PDF</span>
                </button>
                <button
                    onclick={exportToCSV}
                    class="flex-grow sm:flex-grow-0 flex items-center justify-center gap-2 px-5 py-3 bg-white border border-slate-200 text-slate-700 font-bold rounded-2xl text-xs hover:bg-slate-50 transition duration-200 shadow-sm uppercase tracking-wider font-outfit shrink-0 cursor-pointer"
                >
                    <i class="ti ti-download text-base"></i>
                    <span>Ekspor CSV</span>
                </button>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="bg-white rounded-3xl border border-slate-200 p-5 sm:p-6 shadow-sm space-y-4 print:hidden">
            <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-4">
                <!-- Presets -->
                <div class="flex flex-wrap items-center gap-1.5 bg-slate-100/80 p-1 rounded-2xl w-full xl:w-auto">
                    <button
                        onclick={() => selectPreset('harian')}
                        class="flex-1 xl:flex-none text-center px-4 py-2 text-[11px] font-bold rounded-xl transition-all duration-200 whitespace-nowrap uppercase tracking-wider font-outfit
                            {activePreset === 'harian' ? 'bg-white text-slate-800 shadow-xs' : 'text-slate-500 hover:text-slate-800'}"
                    >
                        Harian
                    </button>
                    <button
                        onclick={() => selectPreset('mingguan')}
                        class="flex-1 xl:flex-none text-center px-4 py-2 text-[11px] font-bold rounded-xl transition-all duration-200 whitespace-nowrap uppercase tracking-wider font-outfit
                            {activePreset === 'mingguan' ? 'bg-white text-slate-800 shadow-xs' : 'text-slate-500 hover:text-slate-800'}"
                    >
                        Mingguan
                    </button>
                    <button
                        onclick={() => selectPreset('bulanan')}
                        class="flex-1 xl:flex-none text-center px-4 py-2 text-[11px] font-bold rounded-xl transition-all duration-200 whitespace-nowrap uppercase tracking-wider font-outfit
                            {activePreset === 'bulanan' ? 'bg-white text-slate-800 shadow-xs' : 'text-slate-500 hover:text-slate-800'}"
                    >
                        Bulanan
                    </button>
                    <button
                        onclick={() => selectPreset('tahunan')}
                        class="flex-1 xl:flex-none text-center px-4 py-2 text-[11px] font-bold rounded-xl transition-all duration-200 whitespace-nowrap uppercase tracking-wider font-outfit
                            {activePreset === 'tahunan' ? 'bg-white text-slate-800 shadow-xs' : 'text-slate-500 hover:text-slate-800'}"
                    >
                        Tahunan
                    </button>
                </div>
            </div>

            <!-- Advanced Filters Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-12 gap-3 items-end pt-2 border-t border-slate-100">
                <!-- Search Input -->
                <div class="xl:col-span-3 space-y-1.5">
                    <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider" for="sales-search">Cari Penjualan</label>
                    <div class="relative">
                        <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input
                            id="sales-search"
                            type="search"
                            bind:value={searchInput}
                            oninput={handleSearchInput}
                            placeholder="Cari produk, pelanggan, no. transaksi..."
                            class="w-full bg-slate-50 border border-slate-200 text-slate-755 text-xs font-semibold rounded-xl pl-8 pr-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-blueRoyal/20 focus:border-brand-blueRoyal transition cursor-pointer"
                        />
                    </div>
                </div>

                <!-- Custom Dates -->
                <div class="xl:col-span-3 space-y-1.5">
                    <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider" for="date-from">Periode Tanggal</label>
                    <div class="flex items-center gap-2">
                        <div class="relative flex-1">
                            <input
                                id="date-from"
                                type="date"
                                bind:value={dateFrom}
                                onchange={() => activePreset = 'custom'}
                                class="w-full bg-slate-50 border border-slate-200 text-slate-755 text-xs font-semibold rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-blueRoyal/20 focus:border-brand-blueRoyal transition cursor-pointer"
                            />
                        </div>
                        <span class="text-xs text-slate-400 font-bold uppercase tracking-wider">s/d</span>
                        <div class="relative flex-1">
                            <input
                                id="date-to"
                                type="date"
                                bind:value={dateTo}
                                onchange={() => activePreset = 'custom'}
                                class="w-full bg-slate-50 border border-slate-200 text-slate-755 text-xs font-semibold rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-blueRoyal/20 focus:border-brand-blueRoyal transition cursor-pointer"
                            />
                        </div>
                    </div>
                </div>

                <!-- Payment Method Filter -->
                <div class="xl:col-span-2 space-y-1.5">
                    <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider" for="sales-payment">Metode Bayar</label>
                    <select
                        id="sales-payment"
                        bind:value={paymentMethodFilter}
                        onchange={applyFilter}
                        class="w-full bg-slate-50 border border-slate-200 text-slate-755 text-xs font-semibold rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-blueRoyal/20 focus:border-brand-blueRoyal transition cursor-pointer"
                    >
                        <option value="all">Semua Metode</option>
                        {#each paymentMethods as pm}
                            <option value={pm.id}>{pm.name}</option>
                        {/each}
                    </select>
                </div>

                <!-- Status Filter -->
                <div class="xl:col-span-2 space-y-1.5">
                    <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider" for="sales-status">Status</label>
                    <select
                        id="sales-status"
                        bind:value={statusFilter}
                        onchange={applyFilter}
                        class="w-full bg-slate-50 border border-slate-200 text-slate-755 text-xs font-semibold rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-blueRoyal/20 focus:border-brand-blueRoyal transition cursor-pointer"
                    >
                        <option value="all">Semua Lunas</option>
                        <option value="belum_bayar">Belum Bayar</option>
                        <option value="menunggu">Menunggu</option>
                        <option value="diproses">Diproses</option>
                        <option value="dikemas">Dikemas</option>
                        <option value="dikirim">Dikirim</option>
                        <option value="selesai">Selesai</option>
                        <option value="batal">Batal</option>
                    </select>
                </div>

                <!-- Per Page Filter -->
                <div class="xl:col-span-2 space-y-1.5">
                    <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider" for="sales-perpage">Per Halaman</label>
                    <select
                        id="sales-perpage"
                        bind:value={perPageFilter}
                        onchange={applyFilter}
                        class="w-full bg-slate-50 border border-slate-200 text-slate-755 text-xs font-semibold rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-blueRoyal/20 focus:border-brand-blueRoyal transition cursor-pointer"
                    >
                        <option value={10}>10</option>
                        <option value={15}>15</option>
                        <option value={25}>25</option>
                        <option value={50}>50</option>
                        <option value={100}>100</option>
                    </select>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row justify-end items-center gap-2 pt-2 border-t border-slate-50">
                <button
                    onclick={resetFilters}
                    class="w-full sm:w-auto px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition duration-200 uppercase tracking-wider font-outfit shrink-0 cursor-pointer"
                >
                    Reset Filter
                </button>
                <button
                    onclick={applyFilter}
                    class="w-full sm:w-auto px-6 py-2.5 bg-brand-blueRoyal hover:bg-blue-800 text-white font-bold rounded-xl text-xs transition duration-200 shadow-md shadow-brand-blueRoyal/10 uppercase tracking-wider font-outfit shrink-0 cursor-pointer"
                >
                    Terapkan Filter
                </button>
            </div>
        </div>

        <!-- KPI Cards Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
            <!-- Card 1: Omset Bersih -->
            <div
                class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm flex flex-col justify-between"
            >
                <div>
                    <span
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-lg text-emerald-600 bg-emerald-50 mb-3"
                    >
                        <i class="ti ti-wallet"></i>
                    </span>
                    <p
                        class="text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit"
                    >
                        Penjualan Bersih
                    </p>
                    <h3
                        class="font-outfit font-black text-xl sm:text-2xl text-slate-800 mt-1"
                    >
                        {formatRupiah(metrics.net_sales)}
                    </h3>
                </div>
            </div>

            <!-- Card 2: Jumlah Pesanan -->
            <div
                class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm flex flex-col justify-between"
            >
                <div>
                    <span
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-lg text-blue-600 bg-blue-50 mb-3"
                    >
                        <i class="ti ti-shopping-cart"></i>
                    </span>
                    <p
                        class="text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit"
                    >
                        Total Pesanan
                    </p>
                    <h3
                        class="font-outfit font-black text-xl sm:text-2xl text-slate-800 mt-1"
                    >
                        {metrics.order_count}
                    </h3>
                </div>
            </div>

            <!-- Card 3: Rata-rata Nilai Belanja (AOV) -->
            <div
                class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm flex flex-col justify-between"
            >
                <div>
                    <span
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-lg text-purple-600 bg-purple-50 mb-3"
                    >
                        <i class="ti ti-receipt"></i>
                    </span>
                    <p
                        class="text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit"
                    >
                        Rata-rata Keranjang (AOV)
                    </p>
                    <h3
                        class="font-outfit font-black text-xl sm:text-2xl text-slate-800 mt-1"
                    >
                        {formatRupiah(metrics.aov)}
                    </h3>
                </div>
            </div>

            <!-- Card 4: Kuantitas Terjual -->
            <div
                class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm flex flex-col justify-between"
            >
                <div>
                    <span
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-lg text-rose-600 bg-rose-50 mb-3"
                    >
                        <i class="ti ti-package"></i>
                    </span>
                    <p
                        class="text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit"
                    >
                        Produk Terjual
                    </p>
                    <h3
                        class="font-outfit font-black text-xl sm:text-2xl text-slate-800 mt-1"
                    >
                        {metrics.items_sold} pcs
                    </h3>
                </div>
            </div>
        </div>

        <!-- Charts Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 print:hidden">
            <!-- Tren Line Chart -->
            <div
                class="lg:col-span-2 bg-white rounded-3xl border border-slate-200 p-6 shadow-sm"
            >
                <div class="mb-4">
                    <h3 class="font-outfit font-black text-lg text-slate-800">
                        Tren Pendapatan Bersih
                    </h3>
                    <p class="text-xs text-slate-500 font-medium">
                        Grafik fluktuasi penjualan harian.
                    </p>
                </div>
                <div class="h-80 w-full">
                    <canvas bind:this={trendCanvas}></canvas>
                </div>
            </div>

            <!-- Distribusi Pembayaran & Status -->
            <div
                class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm flex flex-col gap-6"
            >
                <div>
                    <h3 class="font-outfit font-black text-lg text-slate-800">
                        Metode Pembayaran
                    </h3>
                    <p class="text-xs text-slate-500 font-medium">
                        Berdasarkan volume pesanan lunas.
                    </p>
                    <div class="h-44 w-full mt-4">
                        {#if paymentDistribution.length === 0}
                            <div
                                class="h-full flex items-center justify-center text-xs text-slate-400"
                            >
                                Tidak ada data pembayaran.
                            </div>
                        {:else}
                            <canvas bind:this={paymentCanvas}></canvas>
                        {/if}
                    </div>
                </div>

                <div class="border-t border-slate-100 pt-6">
                    <h3 class="font-outfit font-black text-lg text-slate-800">
                        Status Transaksi
                    </h3>
                    <p class="text-xs text-slate-500 font-medium">
                        Distribusi seluruh pesanan masuk.
                    </p>
                    <div class="h-44 w-full mt-4">
                        {#if statusDistribution.length === 0}
                            <div
                                class="h-full flex items-center justify-center text-xs text-slate-400"
                            >
                                Tidak ada data transaksi.
                            </div>
                        {:else}
                            <canvas bind:this={statusCanvas}></canvas>
                        {/if}
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabbed Tables -->
        <div
            class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-sm"
        >
            <!-- Tab Headers -->
            <div class="flex border-b border-slate-100 print:hidden">
                <button
                    onclick={() => activeTab = 'daily'}
                    class="px-6 py-4 text-xs font-black uppercase tracking-wider border-b-2 transition-all duration-200 cursor-pointer font-outfit
                        {activeTab === 'daily' ? 'text-slate-800' : 'border-transparent text-slate-400 hover:text-slate-700'}"
                    style={activeTab === 'daily' ? `border-color: ${primaryColor};` : ''}
                >
                    Ringkasan Harian
                </button>
                <button
                    onclick={() => activeTab = 'detail'}
                    class="px-6 py-4 text-xs font-black uppercase tracking-wider border-b-2 transition-all duration-200 cursor-pointer font-outfit
                        {activeTab === 'detail' ? 'text-slate-800' : 'border-transparent text-slate-400 hover:text-slate-700'}"
                    style={activeTab === 'detail' ? `border-color: ${primaryColor};` : ''}
                >
                    Detail Transaksi ({transactions.total ?? 0})
                </button>
            </div>

            {#if activeTab === 'daily'}
                <div class="p-6 border-b border-slate-100 bg-slate-50/20">
                    <h3 class="font-outfit font-black text-base text-slate-800">
                        Rincian Penjualan Harian
                    </h3>
                    <p class="text-xs text-slate-500 font-medium">
                        Data transaksi yang dikelompokkan per hari.
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse responsive-table sales-report-table">
                        <thead>
                            <tr
                                class="bg-slate-50/50 text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit border-b border-slate-100"
                            >
                                <th class="py-4 px-6">Tanggal</th>
                                <th class="py-4 px-6 text-center">Jumlah Pesanan</th>
                                <th class="py-4 px-6 text-right">Omset Kotor</th>
                                <th class="py-4 px-6 text-right">Subsidi Diskon</th>
                                <th class="py-4 px-6 text-right">Ongkos Kirim</th>
                                <th class="py-4 px-6 text-right">Biaya Admin</th>
                                <th class="py-4 px-6 text-right">Penjualan Bersih</th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-100 text-slate-700 text-sm font-medium"
                        >
                            {#if salesTrendPaginated.data.length === 0}
                                <tr>
                                    <td
                                        colspan="7"
                                        class="py-12 text-center text-slate-400 font-bold font-outfit"
                                    >
                                        <i
                                            class="ti ti-receipt-off text-3xl block mb-2 text-slate-300"
                                        ></i>
                                        Tidak ada data penjualan pada periode ini.
                                    </td>
                                </tr>
                            {:else}
                                {#each salesTrendPaginated.data as row}
                                    <tr class="hover:bg-slate-50/50 transition">
                                        <td
                                            class="py-4 px-6 font-bold text-slate-800"
                                            data-label="Tanggal"
                                            >{formatDate(row.date)}</td
                                        >
                                        <td
                                            class="py-4 px-6 text-center text-slate-600 font-semibold"
                                            data-label="Pesanan"
                                            >{row.order_count}</td
                                        >
                                        <td
                                            class="py-4 px-6 text-right text-slate-600 font-semibold"
                                            data-label="Omset Kotor"
                                            >{formatRupiah(row.gross_sales)}</td
                                        >
                                        <td
                                            class="py-4 px-6 text-right text-rose-500 font-semibold"
                                            data-label="Diskon"
                                            >- {formatRupiah(
                                                row.total_discount,
                                            )}</td
                                        >
                                        <td
                                            class="py-4 px-6 text-right text-slate-500 font-semibold"
                                            data-label="Ongkir"
                                            >{formatRupiah(row.total_shipping)}</td
                                        >
                                        <td
                                            class="py-4 px-6 text-right text-emerald-600 font-semibold"
                                            data-label="Admin Fee"
                                            >+ {formatRupiah(row.total_admin)}</td
                                        >
                                        <td
                                            class="py-4 px-6 text-right font-black text-slate-800"
                                            data-label="Penjualan Bersih"
                                            >{formatRupiah(row.net_sales)}</td
                                        >
                                    </tr>
                                {/each}
                            {/if}
                        </tbody>
                    </table>
                </div>

                <Pagination paginator={salesTrendPaginated} itemLabel="hari" />
            {:else}
                <div class="p-6 border-b border-slate-100 bg-slate-50/20">
                    <h3 class="font-outfit font-black text-base text-slate-800">
                        Daftar Transaksi Detail
                    </h3>
                    <p class="text-xs text-slate-500 font-medium">
                        Rincian per transaksi yang terjadi pada periode tanggal ini.
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse responsive-table sales-report-table">
                        <thead>
                            <tr
                                class="bg-slate-50/50 text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit border-b border-slate-100"
                            >
                                <th class="py-4 px-6">No. Transaksi</th>
                                <th class="py-4 px-6">Pelanggan</th>
                                <th class="py-4 px-6">Metode Bayar</th>
                                <th class="py-4 px-6">Status</th>
                                <th class="py-4 px-6 text-right">Total Transaksi</th>
                                <th class="py-4 px-6">Tanggal</th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-100 text-slate-700 text-sm font-medium"
                        >
                            {#if transactions.data.length === 0}
                                <tr>
                                    <td
                                        colspan="6"
                                        class="py-12 text-center text-slate-400 font-bold font-outfit"
                                    >
                                        <i
                                            class="ti ti-receipt-off text-3xl block mb-2 text-slate-300"
                                        ></i>
                                        Tidak ada data transaksi pada periode ini.
                                    </td>
                                </tr>
                            {:else}
                                {#each transactions.data as row}
                                    {@const badge = statusColors[row.status] ?? { bg: '#f1f5f9', text: '#475569' }}
                                    <tr class="hover:bg-slate-50/50 transition">
                                        <td
                                            class="py-4 px-6 font-mono font-bold"
                                            data-label="No. Transaksi"
                                        >
                                            <a
                                                href={`/admin/transactions/${row.id}`}
                                                class="hover:underline"
                                                style={`color: ${primaryColor};`}
                                            >
                                                {row.transaction_number}
                                            </a>
                                        </td>
                                        <td
                                            class="py-4 px-6 text-slate-650 font-bold text-xs"
                                            data-label="Pelanggan"
                                        >
                                            {row.user?.name ?? 'Umum/Tamu'}
                                        </td>
                                        <td
                                            class="py-4 px-6 text-slate-500 font-semibold text-xs"
                                            data-label="Metode Bayar"
                                        >
                                            {row.payment_method?.name ?? '-'}
                                        </td>
                                        <td
                                            class="py-4 px-6"
                                            data-label="Status"
                                        >
                                            <span
                                                class="inline-flex items-center gap-1 text-[9px] font-black px-2 py-0.5 rounded-md uppercase tracking-wider"
                                                style={`background: ${badge.bg}; color: ${badge.text}`}
                                            >
                                                {statusLabels[row.status] ?? row.status}
                                            </span>
                                        </td>
                                        <td
                                            class="py-4 px-6 text-right font-black text-slate-800"
                                            data-label="Total"
                                        >
                                            {formatRupiah(row.grand_total)}
                                        </td>
                                        <td
                                            class="py-4 px-6 text-slate-500 font-bold text-xs"
                                            data-label="Tanggal"
                                        >
                                            <div>{formatDate(row.created_at)}</div>
                                            <div class="text-[10px] text-slate-400 font-medium mt-0.5">{formatTime(row.created_at)}</div>
                                        </td>
                                    </tr>
                                {/each}
                            {/if}
                        </tbody>
                    </table>
                </div>

                <Pagination paginator={transactions} itemLabel="transaksi" />
            {/if}
        </div>
    </main>
</AdminLayout>

<style>
    @media (max-width: 640px) {
        .sales-report-table td:first-child {
            display: flex !important;
        }
    }
</style>
