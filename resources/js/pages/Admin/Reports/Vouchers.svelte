<script lang="ts">
    import { onMount } from 'svelte';
    import { page, router } from '@inertiajs/svelte';
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import Chart from 'chart.js/auto';
    import Pagination from '@/components/ui/Pagination.svelte';

    const primaryColor = $derived(page.props.theme?.primary_color || '#0c4cb4');
    const secondaryColor = $derived(page.props.theme?.secondary_color || '#fa7315');
    const storeName = $derived((page.props as any).settings?.store_name || 'Bizmate');

    let {
        vouchers = { data: [], links: [], total: 0, current_page: 1, last_page: 1, per_page: 15 },
        metrics = {
            total_usage: 0,
            total_discount_amount: 0,
            total_sales: 0
        },
        chartData = { labels: [], usage: [], discount: [] },
        filters = { date_from: '', date_to: '', search: '', preset: '', per_page: 15 }
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
    let perPageFilter = $state(filters.per_page || 15);

    let chartCanvas: HTMLCanvasElement | undefined;
    let trendChart: Chart;

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

        applyFilter();
    }

    function applyFilter() {
        router.get(
            '/admin/reports/vouchers',
            {
                date_from: dateFrom,
                date_to: dateTo,
                preset: activePreset,
                search: searchQuery,
                per_page: perPageFilter
            },
            {
                preserveState: true,
                preserveScroll: true
            }
        );
    }

    let searchTimeout: ReturnType<typeof setTimeout>;
    function handleSearchInput() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(applyFilter, 300);
    }

    function resetFilter() {
        dateFrom = formatDateLocal(new Date(new Date().setDate(new Date().getDate() - 29)));
        dateTo = formatDateLocal(new Date());
        activePreset = 'bulanan';
        searchQuery = '';
        perPageFilter = 15;
        applyFilter();
    }

    function printReport() {
        window.print();
    }

    function exportToCSV() {
        const headers = [
            'Kode Voucher',
            'Jumlah Penggunaan',
            'Total Diskon Diberikan',
            'Total Omset yang Dihasilkan',
            'Penggunaan Terakhir'
        ];
        const csvRows = [headers.join(',')];

        vouchers.data.forEach((v: any) => {
            const row = [
                `"${v.voucher_code.replace(/"/g, '""')}"`,
                v.usage_count,
                Math.round(v.total_discount),
                Math.round(v.total_sales_generated),
                v.last_used_at
            ];
            csvRows.push(row.join(','));
        });

        const csvContent = 'data:text/csv;charset=utf-8,' + csvRows.join('\n');
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement('a');
        link.setAttribute('href', encodedUri);
        link.setAttribute('download', `laporan_penggunaan_voucher.csv`);
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
        if (!dateStr) return '—';
        return new Date(dateStr).toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        });
    }

    onMount(() => {
        if (chartCanvas) {
            const cleanChartData = $state.snapshot(chartData);
            trendChart = new Chart(chartCanvas, {
                type: 'bar',
                data: {
                    labels: cleanChartData.labels,
                    datasets: [
                        {
                            label: 'Jumlah Penggunaan',
                            data: cleanChartData.usage,
                            backgroundColor: primaryColor,
                            borderColor: primaryColor,
                            type: 'bar',
                            yAxisID: 'y'
                        },
                        {
                            label: 'Total Diskon',
                            data: cleanChartData.discount,
                            borderColor: secondaryColor,
                            backgroundColor: adjustColorOpacity(secondaryColor, '20'),
                            borderWidth: 2,
                            tension: 0.3,
                            type: 'line',
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                font: {
                                    family: 'Outfit',
                                    weight: 'bold'
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false }
                        },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Frekuensi Penggunaan'
                            },
                            ticks: {
                                precision: 0
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Nilai Diskon (Rp)'
                            },
                            grid: {
                                drawOnChartArea: false
                            },
                            ticks: {
                                callback: function(value) {
                                    return 'Rp ' + Number(value).toLocaleString('id-ID');
                                }
                            }
                        }
                    }
                }
            });
        }

        return () => {
            if (trendChart) trendChart.destroy();
        };
    });
</script>

<svelte:head>
    <title>Laporan Penggunaan Voucher — {storeName}</title>
</svelte:head>

<AdminLayout>
    <main class="flex-grow p-4 sm:p-8 w-full max-w-[1600px] mx-auto space-y-6 print:p-0 print:bg-white">
        
        <!-- Print Header -->
        <div class="hidden print:block text-center space-y-1.5 mb-6">
            <h1 class="font-outfit font-black text-2xl text-slate-800 tracking-tight">{storeName}</h1>
            <h2 class="font-outfit font-bold text-lg text-slate-700">Laporan Penggunaan Voucher & Diskon</h2>
            <p class="text-xs text-slate-500 font-medium">Periode: {formatDate(dateFrom)} s/d {formatDate(dateTo)}</p>
            <p class="text-[10px] text-slate-400">Dicetak pada: {new Date().toLocaleString('id-ID')}</p>
        </div>

        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 print:hidden">
            <div>
                <h1 class="font-outfit font-black text-2xl sm:text-3xl text-slate-800 tracking-tight">
                    Laporan Voucher & Diskon
                </h1>
                <p class="text-sm text-slate-500 font-medium mt-1">
                    Analisis efektivitas, frekuensi penggunaan, dan kontribusi omset dari masing-masing kode voucher promosi.
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

        <!-- KPI Cards Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
            <!-- Card 1: Total Usage -->
            <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm flex flex-col justify-between">
                <div>
                    <span class="w-8 h-8 rounded-lg flex items-center justify-center text-lg text-blue-600 bg-blue-50 mb-3">
                        <i class="ti ti-ticket"></i>
                    </span>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit">Total Penggunaan</p>
                    <h3 class="font-outfit font-black text-xl sm:text-2xl text-slate-800 mt-1">{metrics.total_usage} Kali</h3>
                </div>
            </div>

            <!-- Card 2: Total Discount Given -->
            <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm flex flex-col justify-between">
                <div>
                    <span class="w-8 h-8 rounded-lg flex items-center justify-center text-lg text-rose-600 bg-rose-50 mb-3">
                        <i class="ti ti-gift"></i>
                    </span>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit">Total Subsidi Potongan</p>
                    <h3 class="font-outfit font-black text-xl sm:text-2xl text-rose-600 mt-1">{formatRupiah(metrics.total_discount_amount)}</h3>
                </div>
            </div>

            <!-- Card 3: Average Discount -->
            <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm flex flex-col justify-between">
                <div>
                    <span class="w-8 h-8 rounded-lg flex items-center justify-center text-lg text-purple-600 bg-purple-50 mb-3">
                        <i class="ti ti-receipt"></i>
                    </span>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit">Rata-rata Potongan</p>
                    <h3 class="font-outfit font-black text-xl sm:text-2xl text-slate-800 mt-1">
                        {metrics.total_usage > 0 ? formatRupiah(metrics.total_discount_amount / metrics.total_usage) : 'Rp 0'}
                    </h3>
                </div>
            </div>

            <!-- Card 4: Total Sales Generated -->
            <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm flex flex-col justify-between">
                <div>
                    <span class="w-8 h-8 rounded-lg flex items-center justify-center text-lg text-emerald-600 bg-emerald-50 mb-3">
                        <i class="ti ti-shopping-bag"></i>
                    </span>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit">Omset Terkait</p>
                    <h3 class="font-outfit font-black text-xl sm:text-2xl text-emerald-600 mt-1">{formatRupiah(metrics.total_sales)}</h3>
                </div>
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
                    <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider" for="search-input">Cari Voucher</label>
                    <div class="relative">
                        <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input
                            id="search-input"
                            type="search"
                            bind:value={searchQuery}
                            oninput={handleSearchInput}
                            placeholder="Cari kode voucher..."
                            class="w-full bg-slate-50 border border-slate-200 text-slate-750 text-xs font-semibold rounded-xl pl-8 pr-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-blueRoyal/20 focus:border-brand-blueRoyal transition cursor-pointer"
                        />
                    </div>
                </div>

                <!-- Custom Dates -->
                <div class="lg:col-span-5 space-y-1.5">
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

                <!-- Per Halaman -->
                <div class="lg:col-span-3 space-y-1.5">
                    <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider" for="per-page-select">Per Halaman</label>
                    <select
                        id="per-page-select"
                        bind:value={perPageFilter}
                        onchange={applyFilter}
                        class="w-full bg-slate-50 border border-slate-200 text-slate-755 text-xs font-semibold rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-blueRoyal/20 focus:border-brand-blueRoyal transition cursor-pointer"
                    >
                        <option value={10}>10 Baris</option>
                        <option value={15}>15 Baris</option>
                        <option value={25}>25 Baris</option>
                        <option value={50}>50 Baris</option>
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

        <!-- Charts Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 print:hidden">
            <div class="lg:col-span-3 bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
                <div class="mb-4">
                    <h3 class="font-outfit font-black text-lg text-slate-800">
                        Tren Penggunaan Voucher
                    </h3>
                    <p class="text-xs text-slate-500 font-medium">
                        Grafik fluktuasi frekuensi penggunaan dan total subsidi potongan.
                    </p>
                </div>
                <div class="h-80 w-full">
                    <canvas bind:this={chartCanvas}></canvas>
                </div>
            </div>
        </div>

        <!-- Report Table Card -->
        <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-sm">
            <div class="p-6 border-b border-slate-100 bg-slate-50/20">
                <h3 class="font-outfit font-black text-base text-slate-800">
                    Rincian Penggunaan Voucher
                </h3>
                <p class="text-xs text-slate-500 font-medium">
                    Data efektivitas performa masing-masing kode voucher promosi.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse responsive-table vouchers-report-table">
                    <thead>
                        <tr class="bg-slate-50/50 text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit border-b border-slate-100">
                            <th class="py-4 px-6">Kode Voucher</th>
                            <th class="py-4 px-6 text-center">Jumlah Penggunaan</th>
                            <th class="py-4 px-6 text-right">Total Potongan (Subsidi)</th>
                            <th class="py-4 px-6 text-right">Omset Terkait</th>
                            <th class="py-4 px-6 text-right">Terakhir Digunakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700 text-sm font-medium">
                        {#each vouchers.data as v}
                            <tr class="hover:bg-slate-50/30 transition duration-150">
                                <td class="py-4 px-6 font-bold text-slate-800 font-mono tracking-wide" data-label="Kode Voucher">
                                    <span>{v.voucher_code}</span>
                                </td>
                                <td class="py-4 px-6 text-center font-mono font-bold text-slate-600" data-label="Jumlah Penggunaan">
                                    <span>{v.usage_count}</span>
                                </td>
                                <td class="py-4 px-6 text-right font-bold text-rose-600 font-mono" data-label="Total Potongan (Subsidi)">
                                    <span>{formatRupiah(v.total_discount)}</span>
                                </td>
                                <td class="py-4 px-6 text-right font-black text-slate-800 font-mono" data-label="Omset Terkait">
                                    <span>{formatRupiah(v.total_sales_generated)}</span>
                                </td>
                                <td class="py-4 px-6 text-right text-xs text-slate-400 font-medium font-mono" data-label="Terakhir Digunakan">
                                    <span>{formatDate(v.last_used_at)}</span>
                                </td>
                            </tr>
                        {:else}
                            <tr>
                                <td colspan="5" class="py-12 text-center text-slate-400 font-medium">
                                    <i class="ti ti-ticket-off text-4xl text-slate-300 block mb-2"></i>
                                    Tidak ada data penggunaan voucher untuk periode ini.
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>

            <!-- Pagination (Hidden on print) -->
            {#if vouchers.total > 0}
                <div class="border-t border-slate-100 p-6 print:hidden">
                    <Pagination links={vouchers.links} />
                </div>
            {/if}
        </div>

    </main>
</AdminLayout>

<style>
    @media (max-width: 640px) {
        .vouchers-report-table td:first-child {
            display: flex !important;
        }
    }
</style>
