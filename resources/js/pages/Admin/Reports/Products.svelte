<script lang="ts">
    import { adjustColorOpacity } from '@/utils/color';

    import { onMount } from 'svelte';
    import { page, router } from '@inertiajs/svelte';
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import Pagination from '@/components/ui/Pagination.svelte';
    import Chart from 'chart.js/auto';

    const primaryColor = $derived(page.props.theme?.primary_color || '#0c4cb4');
    const secondaryColor = $derived(
        page.props.theme?.secondary_color || '#fa7315',
    );
    const storeName = $derived((page.props as any).settings?.store_name || 'Bizmate');

    function formatDate(dateStr: string) {
        if (!dateStr) return '—';
        return new Date(dateStr).toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        });
    }

    let {
        productSales = { data: [], links: [], total: 0 },
        categories = [],
        metrics = { total_qty_sold: 0, total_revenue: 0, top_category: '-' },
        chartData = { topProducts: [], categorySales: [] },
        filters = { date_from: '', date_to: '', search: '', category_id: '', preset: '' },
    } = $props();

    // svelte-ignore state_referenced_locally
    let dateFrom = $state(filters.date_from);
    // svelte-ignore state_referenced_locally
    let dateTo = $state(filters.date_to);
    // svelte-ignore state_referenced_locally
    let activePreset = $state(filters.preset || 'bulanan');
    // svelte-ignore state_referenced_locally
    let searchQuery = $state(filters.search || '');
    // svelte-ignore state_referenced_locally
    let selectedCategory = $state(filters.category_id || '');

    let productCanvas = $state<HTMLCanvasElement>();
    let categoryCanvas = $state<HTMLCanvasElement>();

    let productChart: Chart | undefined;
    let categoryChart: Chart | undefined;

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
            '/admin/reports/products',
            {
                date_from: dateFrom,
                date_to: dateTo,
                preset: activePreset,
                search: searchQuery,
                category_id: selectedCategory,
            },
            {
                preserveState: true,
            },
        );
    }

    function applyFilter() {
        router.get(
            '/admin/reports/products',
            {
                date_from: dateFrom,
                date_to: dateTo,
                preset: activePreset,
                search: searchQuery,
                category_id: selectedCategory,
            },
            {
                preserveState: true,
            },
        );
    }

    function resetFilter() {
        dateFrom = formatDateLocal(new Date(new Date().setDate(new Date().getDate() - 29)));
        dateTo = formatDateLocal(new Date());
        activePreset = 'bulanan';
        searchQuery = '';
        selectedCategory = '';

        router.get(
            '/admin/reports/products',
            {
                date_from: dateFrom,
                date_to: dateTo,
                preset: 'bulanan',
                search: '',
                category_id: '',
            },
            {
                preserveState: true,
            },
        );
    }

    function exportToCSV() {
        const headers = [
            'Nama Produk',
            'SKU',
            'Kategori',
            'Qty Terjual',
            'Harga Rata-rata',
            'Total Omset',
        ];
        const csvRows = [headers.join(',')];

        // Export data tabular (dapat diekspor seluruh data teragregasi yang didapat dari database)
        productSales.data.forEach((p: any) => {
            const row = [
                `"${p.product_name.replace(/"/g, '""')}"`,
                p.product_sku,
                p.category_name,
                p.qty_sold,
                Math.round(p.avg_price),
                Math.round(p.total_revenue),
            ];
            csvRows.push(row.join(','));
        });

        const csvContent = 'data:text/csv;charset=utf-8,' + csvRows.join('\n');
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement('a');
        link.setAttribute('href', encodedUri);
        link.setAttribute(
            'download',
            `laporan_penjualan_produk_${dateFrom}_sd_${dateTo}.csv`,
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

    onMount(() => {
        const cleanChartData = $state.snapshot(chartData);

        // 1. Horizontal Bar Chart: Top 5 Produk Terlaris
        if (productCanvas && cleanChartData.topProducts.length > 0) {
            const ctx = productCanvas.getContext('2d');
            if (ctx) {
                productChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: cleanChartData.topProducts.map((p: any) =>
                            p.name.length > 20
                                ? p.name.substring(0, 20) + '...'
                                : p.name,
                        ),
                        datasets: [
                            {
                                label: 'Jumlah Terjual (pcs)',
                                data: cleanChartData.topProducts.map(
                                    (p: any) => p.qty,
                                ),
                                backgroundColor: adjustColorOpacity(primaryColor, 'cc'),
                                hoverBackgroundColor: primaryColor,
                                borderRadius: 8,
                                borderWidth: 0,
                            },
                        ],
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                        },
                        scales: {
                            x: {
                                grid: { color: '#f1f5f9' },
                                ticks: { font: { size: 10 } },
                            },
                            y: {
                                grid: { display: false },
                                ticks: { font: { size: 10 } },
                            },
                        },
                    },
                });
            }
        }

        // 2. Pie Chart: Kontribusi Kategori Produk
        if (categoryCanvas && cleanChartData.categorySales.length > 0) {
            const ctx = categoryCanvas.getContext('2d');
            if (ctx) {
                categoryChart = new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: cleanChartData.categorySales.map((c: any) => c.name),
                        datasets: [
                            {
                                data: cleanChartData.categorySales.map(
                                    (c: any) => c.revenue,
                                ),
                                backgroundColor: [
                                    primaryColor,
                                    secondaryColor,
                                    '#8b5cf6',
                                    '#06b6d4',
                                    '#10b981',
                                    '#f43f5e',
                                    '#6b7280',
                                ],
                                borderWidth: 1,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { boxWidth: 10, font: { size: 10 } },
                            },
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        return (
                                            context.label +
                                            ': ' +
                                            formatRupiah(Number(context.raw))
                                        );
                                    },
                                },
                            },
                        },
                    },
                });
            }
        }

        return () => {
            if (productChart) productChart.destroy();
            if (categoryChart) categoryChart.destroy();
        };
    });
</script>

<svelte:head>
    <title>Laporan Penjualan Produk — {storeName}</title>
</svelte:head>

<AdminLayout>
    <main class="flex-grow p-4 sm:p-8 w-full max-w-[1600px] mx-auto space-y-6 print:p-0 print:bg-white">
        <!-- Print Header -->
        <div class="hidden print:block text-center space-y-1.5 mb-6">
            <h1 class="font-outfit font-black text-2xl text-slate-800 tracking-tight">{storeName}</h1>
            <h2 class="font-outfit font-bold text-lg text-slate-700">Laporan Penjualan Produk</h2>
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
                    Laporan Penjualan Produk
                </h1>
                <p class="text-sm text-slate-500 font-medium mt-1">
                    Statistik penjualan barang, produk paling laris, dan
                    kontribusi kategori omset.
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
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-end pt-2 border-t border-slate-100">
                <!-- Search Input -->
                <div class="lg:col-span-4 space-y-1.5">
                    <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider" for="search">Cari Nama / SKU</label>
                    <input
                        type="text"
                        id="search"
                        bind:value={searchQuery}
                        placeholder="Ketik nama atau SKU..."
                        class="w-full bg-slate-50 border border-slate-200 text-slate-755 text-xs font-semibold rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-blueRoyal/20 focus:border-brand-blueRoyal transition"
                    />
                </div>

                <!-- Custom Dates -->
                <div class="lg:col-span-5 space-y-1.5">
                    <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider" for="date_from">Periode Tanggal</label>
                    <div class="flex items-center gap-2">
                        <div class="relative flex-1">
                            <input
                                id="date_from"
                                type="date"
                                bind:value={dateFrom}
                                onchange={() => activePreset = 'custom'}
                                class="w-full bg-slate-50 border border-slate-200 text-slate-755 text-xs font-semibold rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-blueRoyal/20 focus:border-brand-blueRoyal transition cursor-pointer"
                            />
                        </div>
                        <span class="text-xs text-slate-400 font-bold uppercase tracking-wider">s/d</span>
                        <div class="relative flex-1">
                            <input
                                id="date_to"
                                type="date"
                                bind:value={dateTo}
                                onchange={() => activePreset = 'custom'}
                                class="w-full bg-slate-50 border border-slate-200 text-slate-755 text-xs font-semibold rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-blueRoyal/20 focus:border-brand-blueRoyal transition cursor-pointer"
                            />
                        </div>
                    </div>
                </div>

                <!-- Category Filter -->
                <div class="lg:col-span-3 space-y-1.5">
                    <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider" for="category">Kategori</label>
                    <select
                        id="category"
                        bind:value={selectedCategory}
                        class="w-full bg-slate-50 border border-slate-200 text-slate-755 text-xs font-semibold rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-blueRoyal/20 focus:border-brand-blueRoyal transition cursor-pointer"
                    >
                        <option value="">Semua Kategori</option>
                        {#each categories as cat}
                            <option value={cat.id}>{cat.name}</option>
                        {/each}
                    </select>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row justify-end items-center gap-2 pt-2 border-t border-slate-50">
                <button
                    onclick={resetFilter}
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
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div
                class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm"
            >
                <span
                    class="w-8 h-8 rounded-lg flex items-center justify-center text-lg text-blue-600 bg-blue-50 mb-3"
                >
                    <i class="ti ti-package"></i>
                </span>
                <p
                    class="text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit"
                >
                    Total Volume Terjual
                </p>
                <h3
                    class="font-outfit font-black text-xl sm:text-2xl text-slate-800 mt-1"
                >
                    {metrics.total_qty_sold} pcs
                </h3>
            </div>

            <div
                class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm"
            >
                <span
                    class="w-8 h-8 rounded-lg flex items-center justify-center text-lg text-emerald-600 bg-emerald-50 mb-3"
                >
                    <i class="ti ti-wallet"></i>
                </span>
                <p
                    class="text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit"
                >
                    Total Nilai Penjualan
                </p>
                <h3
                    class="font-outfit font-black text-xl sm:text-2xl text-slate-800 mt-1"
                >
                    {formatRupiah(metrics.total_revenue)}
                </h3>
            </div>

            <div
                class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm"
            >
                <span
                    class="w-8 h-8 rounded-lg flex items-center justify-center text-lg text-purple-600 bg-purple-50 mb-3"
                >
                    <i class="ti ti-award"></i>
                </span>
                <p
                    class="text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit"
                >
                    Kategori Terlaris
                </p>
                <h3
                    class="font-outfit font-black text-xl sm:text-2xl text-slate-800 mt-1 truncate"
                >
                    {metrics.top_category}
                </h3>
            </div>
        </div>

        <!-- Charts Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 print:hidden">
            <!-- Top 5 Products Bar Chart -->
            <div
                class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm"
            >
                <div class="mb-4">
                    <h3 class="font-outfit font-black text-lg text-slate-800">
                        Top 5 Produk Terlaris
                    </h3>
                    <p class="text-xs text-slate-500 font-medium">
                        Berdasarkan kuantitas barang yang berhasil dijual.
                    </p>
                </div>
                <div class="h-64 w-full">
                    {#if chartData.topProducts.length === 0}
                        <div
                            class="h-full flex items-center justify-center text-xs text-slate-400"
                        >
                            Tidak ada data penjualan produk.
                        </div>
                    {:else}
                        <canvas bind:this={productCanvas}></canvas>
                    {/if}
                </div>
            </div>

            <!-- Category Pie Chart -->
            <div
                class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm flex flex-col"
            >
                <div class="mb-4">
                    <h3 class="font-outfit font-black text-lg text-slate-800">
                        Kontribusi Kategori
                    </h3>
                    <p class="text-xs text-slate-500 font-medium">
                        Berdasarkan total nominal omset penjualan kotor.
                    </p>
                </div>
                <div class="h-64 w-full flex-grow">
                    {#if chartData.categorySales.length === 0}
                        <div
                            class="h-full flex items-center justify-center text-xs text-slate-400"
                        >
                            Tidak ada data kontribusi kategori.
                        </div>
                    {:else}
                        <canvas bind:this={categoryCanvas}></canvas>
                    {/if}
                </div>
            </div>
        </div>

        <!-- Tabular Data Card -->
        <div
            class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-sm"
        >
            <div class="p-6 border-b border-slate-100">
                <h3 class="font-outfit font-black text-lg text-slate-800">
                    Rincian Penjualan per Produk
                </h3>
                <p class="text-xs text-slate-500 font-medium">
                    Daftar performa penjualan produk secara detail.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse responsive-table products-report-table">
                    <thead>
                        <tr
                            class="bg-slate-50/50 text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit border-b border-slate-100"
                        >
                            <th class="py-4 px-6">Nama Produk</th>
                            <th class="py-4 px-6">SKU</th>
                            <th class="py-4 px-6">Kategori</th>
                            <th class="py-4 px-6 text-center">Qty Terjual</th>
                            <th class="py-4 px-6 text-right"
                                >Harga Rata-rata Jual</th
                            >
                            <th class="py-4 px-6 text-right">Total Omset</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-slate-100 text-slate-700 text-sm font-medium"
                    >
                        {#if productSales.data.length === 0}
                            <tr>
                                <td
                                    colspan="6"
                                    class="py-12 text-center text-slate-400 font-bold font-outfit"
                                >
                                    <i
                                        class="ti ti-package-off text-3xl block mb-2 text-slate-300"
                                    ></i>
                                    Tidak ada produk terjual yang cocok.
                                </td>
                            </tr>
                        {:else}
                            {#each productSales.data as item}
                                <tr class="hover:bg-slate-50/50 transition">
                                    <td
                                        class="py-4 px-6 font-bold text-slate-800"
                                        data-label="Nama Produk"
                                        >{item.product_name}</td
                                    >
                                    <td
                                        class="py-4 px-6 text-slate-500 text-xs font-semibold"
                                        data-label="SKU"
                                        >{item.product_sku}</td
                                    >
                                    <td class="py-4 px-6" data-label="Kategori">
                                        <span
                                            class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-slate-100 text-slate-600"
                                        >
                                            {item.category_name}
                                        </span>
                                    </td>
                                    <td
                                        class="py-4 px-6 text-center font-bold text-slate-600"
                                        data-label="Qty Terjual"
                                        >{item.qty_sold} pcs</td
                                    >
                                    <td
                                        class="py-4 px-6 text-right text-slate-600 font-semibold"
                                        data-label="Harga Rata-rata Jual"
                                        >{formatRupiah(item.avg_price)}</td
                                    >
                                    <td
                                        class="py-4 px-6 text-right font-black text-slate-800"
                                        data-label="Total Omset"
                                        >{formatRupiah(item.total_revenue)}</td
                                    >
                                </tr>
                            {/each}
                        {/if}
                    </tbody>
                </table>
            </div>

            <Pagination paginator={productSales} />
        </div>
    </main>
</AdminLayout>

<style>
    @media (max-width: 640px) {
        .products-report-table td:first-child {
            display: flex !important;
        }
        .products-report-table td[data-label="Nama Produk"] {
            flex-direction: column !important;
            align-items: flex-start !important;
            gap: 4px;
        }
        .products-report-table td[data-label="Nama Produk"] > * {
            text-align: left !important;
            width: 100% !important;
        }
    }
</style>
