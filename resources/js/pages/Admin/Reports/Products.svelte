<script lang="ts">
    import { onMount } from 'svelte';
    import { page, router } from '@inertiajs/svelte';
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import Pagination from '@/components/ui/Pagination.svelte';
    import Chart from 'chart.js/auto';

    const primaryColor = $derived(page.props.theme?.primary_color || '#0c4cb4');
    const secondaryColor = $derived(page.props.theme?.secondary_color || '#fa7315');

    let {
        productSales = { data: [], links: [], total: 0 },
        categories = [],
        metrics = { total_qty_sold: 0, total_revenue: 0, top_category: '-' },
        chartData = { topProducts: [], categorySales: [] },
        filters = { date_from: '', date_to: '', search: '', category_id: '' }
    } = $props();

    let dateFrom = $state(filters.date_from);
    let dateTo = $state(filters.date_to);
    let searchQuery = $state(filters.search || '');
    let selectedCategory = $state(filters.category_id || '');

    let productCanvas: HTMLCanvasElement;
    let categoryCanvas: HTMLCanvasElement;

    let productChart: Chart;
    let categoryChart: Chart;

    function applyFilter() {
        router.get('/admin/reports/products', {
            date_from: dateFrom,
            date_to: dateTo,
            search: searchQuery,
            category_id: selectedCategory
        }, {
            preserveState: true
        });
    }

    function resetFilter() {
        dateFrom = filters.date_from;
        dateTo = filters.date_to;
        searchQuery = '';
        selectedCategory = '';
        applyFilter();
    }

    function exportToCSV() {
        const headers = ['Nama Produk', 'SKU', 'Kategori', 'Qty Terjual', 'Harga Rata-rata', 'Total Omset'];
        const csvRows = [headers.join(',')];

        // Export data tabular (dapat diekspor seluruh data teragregasi yang didapat dari database)
        productSales.data.forEach((p: any) => {
            const row = [
                `"${p.product_name.replace(/"/g, '""')}"`,
                p.product_sku,
                p.category_name,
                p.qty_sold,
                Math.round(p.avg_price),
                Math.round(p.total_revenue)
            ];
            csvRows.push(row.join(','));
        });

        const csvContent = "data:text/csv;charset=utf-8," + csvRows.join("\n");
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", `laporan_penjualan_produk_${dateFrom}_sd_${dateTo}.csv`);
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

    onMount(() => {
        // 1. Horizontal Bar Chart: Top 5 Produk Terlaris
        if (productCanvas && chartData.topProducts.length > 0) {
            const ctx = productCanvas.getContext('2d');
            if (ctx) {
                productChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: chartData.topProducts.map((p: any) => p.name.length > 20 ? p.name.substring(0, 20) + '...' : p.name),
                        datasets: [
                            {
                                label: 'Jumlah Terjual (pcs)',
                                data: chartData.topProducts.map((p: any) => p.qty),
                                backgroundColor: primaryColor + 'cc',
                                hoverBackgroundColor: primaryColor,
                                borderRadius: 8,
                                borderWidth: 0
                            }
                        ]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            x: { grid: { color: '#f1f5f9' }, ticks: { font: { size: 10 } } },
                            y: { grid: { display: false }, ticks: { font: { size: 10 } } }
                        }
                    }
                });
            }
        }

        // 2. Pie Chart: Kontribusi Kategori Produk
        if (categoryCanvas && chartData.categorySales.length > 0) {
            const ctx = categoryCanvas.getContext('2d');
            if (ctx) {
                categoryChart = new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: chartData.categorySales.map((c: any) => c.name),
                        datasets: [
                            {
                                data: chartData.categorySales.map((c: any) => c.revenue),
                                backgroundColor: [
                                    primaryColor,
                                    secondaryColor,
                                    '#8b5cf6',
                                    '#06b6d4',
                                    '#10b981',
                                    '#f43f5e',
                                    '#6b7280'
                                ],
                                borderWidth: 1
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { boxWidth: 10, font: { size: 10 } }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.label + ': ' + formatRupiah(Number(context.raw));
                                    }
                                }
                            }
                        }
                    }
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
    <title>Laporan Penjualan Produk</title>
</svelte:head>

<AdminLayout>
    <main class="flex-grow p-4 sm:p-8 w-full max-w-[1600px] mx-auto space-y-6">
        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h1 class="font-outfit font-black text-2xl sm:text-3xl text-slate-800 tracking-tight">
                    Laporan Penjualan Produk
                </h1>
                <p class="text-sm text-slate-500 font-medium mt-1">
                    Statistik penjualan barang, produk paling laris, dan kontribusi kategori omset.
                </p>
            </div>
            
            <button 
                onclick={exportToCSV}
                class="flex items-center justify-center gap-2 px-5 py-3 bg-white border border-slate-200 text-slate-700 font-bold rounded-2xl text-xs hover:bg-slate-50 transition duration-200 shadow-sm uppercase tracking-wider font-outfit shrink-0"
            >
                <i class="ti ti-download text-base"></i>
                <span>Ekspor CSV Halaman Ini</span>
            </button>
        </div>

        <!-- Filter Card -->
        <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
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
                <div class="space-y-1.5">
                    <label for="search" class="text-xs font-bold text-slate-500 uppercase tracking-wider">Cari Nama / SKU</label>
                    <input 
                        type="text" 
                        id="search"
                        bind:value={searchQuery}
                        placeholder="Ketik nama atau SKU..."
                        class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-sm font-semibold rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-blueRoyal/20 focus:border-brand-blueRoyal transition"
                    />
                </div>
                <div class="space-y-1.5">
                    <label for="category" class="text-xs font-bold text-slate-500 uppercase tracking-wider">Kategori</label>
                    <select 
                        id="category"
                        bind:value={selectedCategory}
                        class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-sm font-semibold rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-blueRoyal/20 focus:border-brand-blueRoyal transition cursor-pointer"
                    >
                        <option value="">Semua Kategori</option>
                        {#each categories as cat}
                            <option value={cat.id}>{cat.name}</option>
                        {/each}
                    </select>
                </div>
            </div>
            
            <div class="flex items-center justify-end gap-3 pt-2">
                <button 
                    onclick={resetFilter}
                    class="px-5 py-3 border border-slate-200 hover:bg-slate-50 text-slate-500 font-bold rounded-2xl text-xs transition duration-200 uppercase tracking-wider font-outfit"
                >
                    Reset Filter
                </button>
                <button 
                    onclick={applyFilter}
                    class="px-6 py-3 bg-brand-blueRoyal hover:bg-blue-800 text-white font-bold rounded-2xl text-xs transition duration-200 shadow-lg shadow-brand-blueRoyal/20 uppercase tracking-wider font-outfit"
                >
                    Cari & Filter
                </button>
            </div>
        </div>

        <!-- KPI Cards Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm">
                <span class="w-8 h-8 rounded-lg flex items-center justify-center text-lg text-blue-600 bg-blue-50 mb-3">
                    <i class="ti ti-package"></i>
                </span>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit">Total Volume Terjual</p>
                <h3 class="font-outfit font-black text-xl sm:text-2xl text-slate-800 mt-1">
                    {metrics.total_qty_sold} pcs
                </h3>
            </div>

            <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm">
                <span class="w-8 h-8 rounded-lg flex items-center justify-center text-lg text-emerald-600 bg-emerald-50 mb-3">
                    <i class="ti ti-wallet"></i>
                </span>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit">Total Nilai Penjualan</p>
                <h3 class="font-outfit font-black text-xl sm:text-2xl text-slate-800 mt-1">
                    {formatRupiah(metrics.total_revenue)}
                </h3>
            </div>

            <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm">
                <span class="w-8 h-8 rounded-lg flex items-center justify-center text-lg text-purple-600 bg-purple-50 mb-3">
                    <i class="ti ti-award"></i>
                </span>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit">Kategori Terlaris</p>
                <h3 class="font-outfit font-black text-xl sm:text-2xl text-slate-800 mt-1 truncate">
                    {metrics.top_category}
                </h3>
            </div>
        </div>

        <!-- Charts Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Top 5 Products Bar Chart -->
            <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
                <div class="mb-4">
                    <h3 class="font-outfit font-black text-lg text-slate-800">Top 5 Produk Terlaris</h3>
                    <p class="text-xs text-slate-500 font-medium">Berdasarkan kuantitas barang yang berhasil dijual.</p>
                </div>
                <div class="h-64 w-full">
                    {#if chartData.topProducts.length === 0}
                        <div class="h-full flex items-center justify-center text-xs text-slate-400">Tidak ada data penjualan produk.</div>
                    {:else}
                        <canvas bind:this={productCanvas}></canvas>
                    {/if}
                </div>
            </div>

            <!-- Category Pie Chart -->
            <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm flex flex-col">
                <div class="mb-4">
                    <h3 class="font-outfit font-black text-lg text-slate-800">Kontribusi Kategori</h3>
                    <p class="text-xs text-slate-500 font-medium">Berdasarkan total nominal omset penjualan kotor.</p>
                </div>
                <div class="h-64 w-full flex-grow">
                    {#if chartData.categorySales.length === 0}
                        <div class="h-full flex items-center justify-center text-xs text-slate-400">Tidak ada data kontribusi kategori.</div>
                    {:else}
                        <canvas bind:this={categoryCanvas}></canvas>
                    {/if}
                </div>
            </div>
        </div>

        <!-- Tabular Data Card -->
        <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-sm">
            <div class="p-6 border-b border-slate-100">
                <h3 class="font-outfit font-black text-lg text-slate-800">Rincian Penjualan per Produk</h3>
                <p class="text-xs text-slate-500 font-medium">Daftar performa penjualan produk secara detail.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[800px]">
                    <thead>
                        <tr class="bg-slate-50/50 text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit border-b border-slate-100">
                            <th class="py-4 px-6">Nama Produk</th>
                            <th class="py-4 px-6">SKU</th>
                            <th class="py-4 px-6">Kategori</th>
                            <th class="py-4 px-6 text-center">Qty Terjual</th>
                            <th class="py-4 px-6 text-right">Harga Rata-rata Jual</th>
                            <th class="py-4 px-6 text-right">Total Omset</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700 text-sm font-medium">
                        {#if productSales.data.length === 0}
                            <tr>
                                <td colspan="6" class="py-12 text-center text-slate-400 font-bold font-outfit">
                                    <i class="ti ti-package-off text-3xl block mb-2 text-slate-300"></i>
                                    Tidak ada produk terjual yang cocok.
                                </td>
                            </tr>
                        {:else}
                            {#each productSales.data as item}
                                <tr class="hover:bg-slate-50/50 transition">
                                    <td class="py-4 px-6 font-bold text-slate-800">{item.product_name}</td>
                                    <td class="py-4 px-6 text-slate-500 text-xs font-semibold">{item.product_sku}</td>
                                    <td class="py-4 px-6">
                                        <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-slate-100 text-slate-600">
                                            {item.category_name}
                                        </span>
                                    </td>
                                    <td class="py-4 px-6 text-center font-bold text-slate-600">{item.qty_sold} pcs</td>
                                    <td class="py-4 px-6 text-right text-slate-600 font-semibold">{formatRupiah(item.avg_price)}</td>
                                    <td class="py-4 px-6 text-right font-black text-slate-800">{formatRupiah(item.total_revenue)}</td>
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
