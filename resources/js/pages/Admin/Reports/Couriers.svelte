<script lang="ts">
    import { onMount } from 'svelte';
    import { page, router } from '@inertiajs/svelte';
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import Chart from 'chart.js/auto';

    const primaryColor = $derived(page.props.theme?.primary_color || '#0c4cb4');
    const secondaryColor = $derived(
        page.props.theme?.secondary_color || '#fa7315',
    );
    const storeName = $derived((page.props as any).settings?.store_name || 'Bizmate');

    let {
        metrics = {
            total_shipping_revenue: 0,
            total_delivery_volume: 0,
            completed_deliveries: 0,
            cancelled_deliveries: 0,
        },
        shippingSummary = [],
        rajaongkirBreakdown = [],
        courierPerformance = [],
        recentDeliveries = [],
        filters = { date_from: '', date_to: '', preset: '' },
    } = $props();

    // svelte-ignore state_referenced_locally
    let dateFrom = $state(filters.date_from);
    // svelte-ignore state_referenced_locally
    let dateTo = $state(filters.date_to);
    // svelte-ignore state_referenced_locally
    let activePreset = $state(filters.preset || 'bulanan');

    let methodCanvas = $state<HTMLCanvasElement>();
    let serviceCanvas = $state<HTMLCanvasElement>();

    let methodChart: Chart | undefined;
    let serviceChart: Chart | undefined;

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
            '/admin/reports/couriers',
            {
                date_from: dateFrom,
                date_to: dateTo,
                preset: activePreset,
            },
            {
                preserveState: true,
            },
        );
    }

    function applyFilter() {
        router.get(
            '/admin/reports/couriers',
            {
                date_from: dateFrom,
                date_to: dateTo,
                preset: activePreset,
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

        router.get(
            '/admin/reports/couriers',
            {
                date_from: dateFrom,
                date_to: dateTo,
                preset: 'bulanan',
            },
            {
                preserveState: true,
            },
        );
    }

    function exportToCSV() {
        const headers = [
            'Metode Pengiriman',
            'Total Pesanan',
            'Total Transaksi Selesai',
            'Total Transaksi Batal',
            'Total Nilai Transaksi',
            'Total Ongkir Terkumpul',
            'Rata-rata Ongkir',
        ];
        const csvRows = [headers.join(',')];

        shippingSummary.forEach((s: any) => {
            const methodName =
                s.method === 'store_courier'
                    ? 'Kurir Toko'
                    : s.method === 'self_pickup'
                      ? 'Ambil di Toko'
                      : 'RajaOngkir (3PL)';
            const row = [
                methodName,
                s.total_orders,
                s.completed_count,
                s.cancelled_count,
                Math.round(s.total_sales),
                Math.round(s.shipping_fees),
                Math.round(s.avg_shipping_fee),
            ];
            csvRows.push(row.join(','));
        });

        // Add blank row and then courier performance
        csvRows.push('');
        csvRows.push(
            [
                'Performa Kurir Toko (Driver)',
                'Total Ditugaskan',
                'Selesai',
                'Aktif',
                'Batal',
                'Rata-rata Waktu Kirim (Jam)',
            ].join(','),
        );
        courierPerformance.forEach((c: any) => {
            const row = [
                c.name,
                c.total_assigned,
                c.completed_count,
                c.active_count,
                c.cancelled_count,
                c.avg_delivery_hours !== null ? c.avg_delivery_hours : '-',
            ];
            csvRows.push(row.join(','));
        });

        const csvContent = 'data:text/csv;charset=utf-8,' + csvRows.join('\n');
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement('a');
        link.setAttribute('href', encodedUri);
        link.setAttribute(
            'download',
            `laporan_kurir_${dateFrom}_sd_${dateTo}.csv`,
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
        return new Date(dateStr).toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    onMount(() => {
        const cleanShippingSummary = $state.snapshot(shippingSummary);
        const cleanRajaongkirBreakdown = $state.snapshot(rajaongkirBreakdown);

        // 1. Doughnut Chart: Distribusi Metode Pengiriman
        if (methodCanvas) {
            const ctx = methodCanvas.getContext('2d');
            if (ctx) {
                const methodLabelsMap: any = {
                    store_courier: 'Kurir Toko',
                    self_pickup: 'Ambil di Toko',
                    rajaongkir: 'RajaOngkir (3PL)',
                };

                methodChart = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: cleanShippingSummary.map(
                            (s: any) => methodLabelsMap[s.method] || s.method,
                        ),
                        datasets: [
                            {
                                data: cleanShippingSummary.map(
                                    (s: any) => s.total_orders,
                                ),
                                backgroundColor: [
                                    primaryColor,
                                    secondaryColor,
                                    '#8b5cf6',
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

        // 2. Bar Chart: Breakdown RajaOngkir (3PL)
        if (serviceCanvas) {
            const ctx = serviceCanvas.getContext('2d');
            if (ctx) {
                serviceChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: cleanRajaongkirBreakdown.map((r: any) => r.name),
                        datasets: [
                            {
                                label: 'Jumlah Pesanan',
                                data: cleanRajaongkirBreakdown.map(
                                    (r: any) => r.total_orders,
                                ),
                                backgroundColor: primaryColor + 'cc',
                                borderColor: primaryColor,
                                borderWidth: 1,
                                borderRadius: 6,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                        },
                        scales: {
                            y: {
                                grid: { color: '#f1f5f9' },
                                ticks: { font: { size: 10 }, stepSize: 1 },
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

        return () => {
            if (methodChart) methodChart.destroy();
            if (serviceChart) serviceChart.destroy();
        };
    });
</script>

<svelte:head>
    <title>Laporan Kurir & Logistik — {storeName}</title>
</svelte:head>

<AdminLayout>
    <main class="flex-grow p-4 sm:p-8 w-full max-w-[1600px] mx-auto space-y-6 print:p-0 print:bg-white">
        <!-- Print Header -->
        <div class="hidden print:block text-center space-y-1.5 mb-6">
            <h1 class="font-outfit font-black text-2xl text-slate-800 tracking-tight">{storeName}</h1>
            <h2 class="font-outfit font-bold text-lg text-slate-700">Laporan Kurir & Logistik</h2>
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
                    Laporan Kurir & Logistik
                </h1>
                <p class="text-sm text-slate-500 font-medium mt-1">
                    Analisis distribusi pengiriman, performa kurir toko, dan
                    logistik pengantaran pesanan.
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
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-end pt-2 border-t border-slate-100">
                <!-- Custom Dates -->
                <div class="sm:col-span-2 space-y-1.5">
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
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- Card 1: Total Ongkir Terkumpul -->
            <div
                class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm flex flex-col justify-between"
            >
                <div>
                    <span
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-lg text-emerald-600 bg-emerald-50 mb-3"
                    >
                        <i class="ti ti-coin"></i>
                    </span>
                    <p
                        class="text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit"
                    >
                        Total Ongkos Kirim
                    </p>
                    <h3
                        class="font-outfit font-black text-xl sm:text-2xl text-slate-800 mt-1"
                    >
                        {formatRupiah(metrics.total_shipping_revenue)}
                    </h3>
                </div>
            </div>

            <!-- Card 2: Total Volume Pengiriman -->
            <div
                class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm flex flex-col justify-between"
            >
                <div>
                    <span
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-lg text-blue-600 bg-blue-50 mb-3"
                    >
                        <i class="ti ti-truck-delivery"></i>
                    </span>
                    <p
                        class="text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit"
                    >
                        Volume Pengiriman
                    </p>
                    <h3
                        class="font-outfit font-black text-xl sm:text-2xl text-slate-800 mt-1"
                    >
                        {metrics.total_delivery_volume} pesanan
                    </h3>
                </div>
            </div>

            <!-- Card 3: Selesai Pengiriman -->
            <div
                class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm flex flex-col justify-between"
            >
                <div>
                    <span
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-lg text-purple-600 bg-purple-50 mb-3"
                    >
                        <i class="ti ti-circle-check"></i>
                    </span>
                    <p
                        class="text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit"
                    >
                        Pengiriman Selesai
                    </p>
                    <h3
                        class="font-outfit font-black text-xl sm:text-2xl text-slate-800 mt-1"
                    >
                        {metrics.completed_deliveries}
                    </h3>
                </div>
            </div>

            <!-- Card 4: Gagal / Batal -->
            <div
                class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm flex flex-col justify-between"
            >
                <div>
                    <span
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-lg text-rose-600 bg-rose-50 mb-3"
                    >
                        <i class="ti ti-circle-x"></i>
                    </span>
                    <p
                        class="text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit"
                    >
                        Pengiriman Batal
                    </p>
                    <h3
                        class="font-outfit font-black text-xl sm:text-2xl text-slate-800 mt-1"
                    >
                        {metrics.cancelled_deliveries}
                    </h3>
                </div>
            </div>
        </div>

        <!-- Charts Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 print:hidden">
            <!-- Distribusi Metode -->
            <div
                class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm"
            >
                <div class="mb-4">
                    <h3 class="font-outfit font-black text-lg text-slate-800">
                        Distribusi Metode Pengiriman
                    </h3>
                    <p class="text-xs text-slate-500 font-medium">
                        Berdasarkan total pesanan masuk.
                    </p>
                </div>
                <div class="h-64 w-full">
                    {#if shippingSummary.length === 0}
                        <div
                            class="h-full flex items-center justify-center text-xs text-slate-400"
                        >
                            Tidak ada data.
                        </div>
                    {:else}
                        <canvas bind:this={methodCanvas}></canvas>
                    {/if}
                </div>
            </div>

            <!-- Breakdown 3PL RajaOngkir -->
            <div
                class="lg:col-span-2 bg-white rounded-3xl border border-slate-200 p-6 shadow-sm"
            >
                <div class="mb-4">
                    <h3 class="font-outfit font-black text-lg text-slate-800">
                        Volume Kurir Pihak Ketiga (RajaOngkir)
                    </h3>
                    <p class="text-xs text-slate-500 font-medium">
                        Grafik perbandingan pengiriman via logistik 3PL.
                    </p>
                </div>
                <div class="h-64 w-full">
                    {#if rajaongkirBreakdown.length === 0}
                        <div
                            class="h-full flex items-center justify-center text-xs text-slate-400"
                        >
                            Tidak ada pengiriman RajaOngkir pada periode ini.
                        </div>
                    {:else}
                        <canvas bind:this={serviceCanvas}></canvas>
                    {/if}
                </div>
            </div>
        </div>

        <!-- Shipping Methods Breakdown Table -->
        <div
            class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-sm"
        >
            <div class="p-6 border-b border-slate-100">
                <h3 class="font-outfit font-black text-lg text-slate-800">
                    Rincian Metode Pengiriman
                </h3>
                <p class="text-xs text-slate-500 font-medium">
                    Perbandingan performa keuangan dan volume dari masing-masing
                    tipe logistik.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse responsive-table couriers-methods-table">
                    <thead>
                        <tr
                            class="bg-slate-50/50 text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit border-b border-slate-100"
                        >
                            <th class="py-4 px-6">Metode</th>
                            <th class="py-4 px-6 text-center">Total Volume</th>
                            <th class="py-4 px-6 text-center">Selesai</th>
                            <th class="py-4 px-6 text-center">Batal</th>
                            <th class="py-4 px-6 text-right">Omset Pesanan</th>
                            <th class="py-4 px-6 text-right">Total Ongkir</th>
                            <th class="py-4 px-6 text-right"
                                >Rata-rata Ongkir</th
                            >
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-slate-100 text-slate-700 text-sm font-medium"
                    >
                        {#each shippingSummary as row}
                            <tr class="hover:bg-slate-50/50 transition">
                                <td class="py-4 px-6 font-bold text-slate-800" data-label="Metode">
                                    {#if row.method === 'store_courier'}
                                        <span
                                            class="inline-flex items-center gap-1.5"
                                        >
                                            <span
                                                class="w-2 h-2 rounded-full bg-blue-600"
                                            ></span>
                                            Kurir Toko
                                        </span>
                                    {:else if row.method === 'self_pickup'}
                                        <span
                                            class="inline-flex items-center gap-1.5"
                                        >
                                            <span
                                                class="w-2 h-2 rounded-full bg-amber-500"
                                            ></span>
                                            Ambil di Toko
                                        </span>
                                    {:else}
                                        <span
                                            class="inline-flex items-center gap-1.5"
                                        >
                                            <span
                                                class="w-2 h-2 rounded-full bg-purple-500"
                                            ></span>
                                            RajaOngkir (3PL)
                                        </span>
                                    {/if}
                                </td>
                                <td
                                    class="py-4 px-6 text-center text-slate-600 font-semibold"
                                    data-label="Total Volume"
                                    >{row.total_orders}</td
                                >
                                <td
                                    class="py-4 px-6 text-center text-emerald-600 font-semibold"
                                    data-label="Selesai"
                                    >{row.completed_count}</td
                                >
                                <td
                                    class="py-4 px-6 text-center text-rose-500 font-semibold"
                                    data-label="Batal"
                                    >{row.cancelled_count}</td
                                >
                                <td
                                    class="py-4 px-6 text-right text-slate-600 font-semibold"
                                    data-label="Omset Pesanan"
                                    >{formatRupiah(row.total_sales)}</td
                                >
                                <td
                                    class="py-4 px-6 text-right text-slate-600 font-semibold"
                                    data-label="Total Ongkir"
                                    >{formatRupiah(row.shipping_fees)}</td
                                >
                                <td
                                    class="py-4 px-6 text-right font-black text-slate-800"
                                    data-label="Rata-rata Ongkir"
                                    >{formatRupiah(row.avg_shipping_fee)}</td
                                >
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Store Courier (Driver) Performance -->
        <div
            class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-sm"
        >
            <div
                class="p-6 border-b border-slate-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2"
            >
                <div>
                    <h3 class="font-outfit font-black text-lg text-slate-800">
                        Performa Driver (Kurir Toko)
                    </h3>
                    <p class="text-xs text-slate-500 font-medium">
                        Metrik penugasan, penyelesaian pengantaran, dan estimasi
                        rata-rata waktu kirim.
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse responsive-table couriers-performance-table">
                    <thead>
                        <tr
                            class="bg-slate-50/50 text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit border-b border-slate-100"
                        >
                            <th class="py-4 px-6">Nama Driver</th>
                            <th class="py-4 px-6">Email / Kontak</th>
                            <th class="py-4 px-6 text-center"
                                >Total Ditugaskan</th
                            >
                            <th class="py-4 px-6 text-center"
                                >Pengiriman Selesai</th
                            >
                            <th class="py-4 px-6 text-center"
                                >Pengiriman Aktif</th
                            >
                            <th class="py-4 px-6 text-center"
                                >Pengiriman Batal</th
                            >
                            <th class="py-4 px-6 text-center"
                                >Rata-rata Waktu Kirim</th
                            >
                            <th class="py-4 px-6 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-slate-100 text-slate-700 text-sm font-medium"
                    >
                        {#if courierPerformance.length === 0}
                            <tr>
                                <td
                                    colspan="8"
                                    class="py-12 text-center text-slate-400 font-bold font-outfit"
                                >
                                    <i
                                        class="ti ti-users-minus text-3xl block mb-2 text-slate-300"
                                    ></i>
                                    Belum ada staff dengan peran Kurir Toko.
                                </td>
                            </tr>
                        {:else}
                            {#each courierPerformance as row}
                                <tr class="hover:bg-slate-50/50 transition">
                                    <td
                                        class="py-4 px-6 font-bold text-slate-800"
                                        data-label="Nama Driver"
                                        >{row.name}</td
                                    >
                                    <td
                                        class="py-4 px-6 text-slate-500 text-xs"
                                        data-label="Email / Kontak"
                                    >
                                        <div>{row.email}</div>
                                        <div
                                            class="mt-0.5 font-semibold text-slate-600"
                                        >
                                            {row.phone_number || '-'}
                                        </div>
                                    </td>
                                    <td
                                        class="py-4 px-6 text-center text-slate-600 font-semibold"
                                        data-label="Total Ditugaskan"
                                        >{row.total_assigned}</td
                                    >
                                    <td
                                        class="py-4 px-6 text-center text-emerald-600 font-semibold"
                                        data-label="Pengiriman Selesai"
                                        >{row.completed_count}</td
                                    >
                                    <td
                                        class="py-4 px-6 text-center text-blue-600 font-semibold"
                                        data-label="Pengiriman Aktif"
                                        >{row.active_count}</td
                                    >
                                    <td
                                        class="py-4 px-6 text-center text-rose-500 font-semibold"
                                        data-label="Pengiriman Batal"
                                        >{row.cancelled_count}</td
                                    >
                                    <td
                                        class="py-4 px-6 text-center font-bold text-slate-800"
                                        data-label="Rata-rata Waktu Kirim"
                                    >
                                        {#if row.avg_delivery_hours !== null}
                                            {row.avg_delivery_hours} jam
                                        {:else}
                                            <span
                                                class="text-slate-400 font-normal"
                                                >-</span
                                            >
                                        {/if}
                                    </td>
                                    <td class="py-4 px-6 text-center" data-label="Aksi">
                                        <a
                                            href="/admin/master-data/admins/{row.id}/courier-history"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-50 hover:bg-slate-100 text-slate-600 hover:text-slate-800 font-bold text-xs rounded-xl transition border border-slate-200"
                                        >
                                            <i class="ti ti-history text-sm"
                                            ></i>
                                            <span>Riwayat</span>
                                        </a>
                                    </td>
                                </tr>
                            {/each}
                        {/if}
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Delivery Logs List -->
        <div
            class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-sm"
        >
            <div class="p-6 border-b border-slate-100">
                <h3 class="font-outfit font-black text-lg text-slate-800">
                    Log Pengiriman Terbaru
                </h3>
                <p class="text-xs text-slate-500 font-medium">
                    Daftar 20 pesanan teratas yang sedang dikirim atau
                    diselesaikan dalam periode ini.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse responsive-table recent-deliveries-table">
                    <thead>
                        <tr
                            class="bg-slate-50/50 text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit border-b border-slate-100"
                        >
                            <th class="py-4 px-6">No. Transaksi</th>
                            <th class="py-4 px-6">Pelanggan</th>
                            <th class="py-4 px-6">Alamat Tujuan</th>
                            <th class="py-4 px-6">Kurir & Layanan</th>
                            <th class="py-4 px-6">Kode Booking / Resi</th>
                            <th class="py-4 px-6 text-center">Status</th>
                            <th class="py-4 px-6">Tanggal Dibuat</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-slate-100 text-slate-700 text-sm font-medium"
                    >
                        {#if recentDeliveries.length === 0}
                            <tr>
                                <td
                                    colspan="7"
                                    class="py-12 text-center text-slate-400 font-bold font-outfit"
                                >
                                    <i
                                        class="ti ti-package-off text-3xl block mb-2 text-slate-300"
                                    ></i>
                                    Tidak ada data log pengiriman untuk periode ini.
                                </td>
                            </tr>
                        {:else}
                            {#each recentDeliveries as row}
                                <tr class="hover:bg-slate-50/50 transition">
                                    <td class="py-4 px-6" data-label="No. Transaksi">
                                        <a
                                            href="/admin/transactions/{row.id}"
                                            class="font-bold text-brand-blueRoyal hover:underline"
                                        >
                                            {row.transaction_number}
                                        </a>
                                    </td>
                                    <td
                                        class="py-4 px-6 font-semibold text-slate-800"
                                        data-label="Pelanggan"
                                        >{row.user_name}</td
                                    >
                                    <td
                                        class="py-4 px-6 text-xs text-slate-600 max-w-[250px] truncate"
                                        data-label="Alamat Tujuan"
                                    >
                                        {#if row.address}
                                            <div
                                                class="font-bold text-slate-700"
                                            >
                                                {row.address.name} ({row.address
                                                    .phone})
                                            </div>
                                            <div class="text-slate-500 mt-0.5">
                                                {row.address.address_line}, {row
                                                    .address.city}
                                            </div>
                                        {:else}
                                            <span class="text-slate-400">-</span>
                                        {/if}
                                    </td>
                                    <td class="py-4 px-6" data-label="Kurir & Layanan">
                                        <div
                                            class="font-semibold text-slate-800"
                                        >
                                            {row.courier_name}
                                        </div>
                                        {#if row.shipping_courier === 'store_courier' && row.courier_driver_name}
                                            <div
                                                class="text-[10px] text-blue-600 font-bold uppercase mt-0.5"
                                            >
                                                Driver: {row.courier_driver_name}
                                            </div>
                                        {:else}
                                            <div
                                                class="text-slate-500 text-xs mt-0.5"
                                            >
                                                {row.shipping_service || '-'}
                                            </div>
                                        {/if}
                                    </td>
                                    <td class="py-4 px-6 text-xs font-mono" data-label="Kode Booking / Resi">
                                        {#if row.booking_code}
                                            <div
                                                class="text-slate-700 font-semibold"
                                            >
                                                <span
                                                    class="text-[9px] text-slate-400 font-sans uppercase"
                                                    >Booking:</span
                                                >
                                                {row.booking_code}
                                            </div>
                                        {/if}
                                        {#if row.tracking_number}
                                            <div class="text-slate-500 mt-0.5">
                                                <span
                                                    class="text-[9px] text-slate-400 font-sans uppercase"
                                                    >Resi:</span
                                                >
                                                {row.tracking_number}
                                            </div>
                                        {/if}
                                        {#if !row.booking_code && !row.tracking_number}
                                            <span class="text-slate-400">-</span>
                                        {/if}
                                    </td>
                                    <td class="py-4 px-6 text-center" data-label="Status">
                                        {#if row.status === 'selesai'}
                                            <span
                                                class="px-2.5 py-1 bg-emerald-50 text-emerald-700 text-xs font-bold rounded-lg border border-emerald-100"
                                                >Selesai</span
                                            >
                                        {:else}
                                            <span
                                                class="px-2.5 py-1 bg-slate-50 text-slate-600 text-xs font-bold rounded-lg border border-slate-100"
                                                >{row.status}</span
                                            >
                                        {/if}
                                    </td>
                                    <td class="py-4 px-6 text-xs text-slate-500" data-label="Tanggal Dibuat"
                                        >{formatDate(row.created_at)}</td
                                    >
                                </tr>
                            {/each}
                        {/if}
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</AdminLayout>

<style>
    @media (max-width: 640px) {
        .couriers-methods-table td:first-child,
        .couriers-performance-table td:first-child,
        .recent-deliveries-table td:first-child {
            display: flex !important;
        }
        .couriers-methods-table td[data-label="Metode"],
        .couriers-performance-table td[data-label="Nama Driver"],
        .couriers-performance-table td[data-label="Email / Kontak"],
        .recent-deliveries-table td[data-label="No. Transaksi"],
        .recent-deliveries-table td[data-label="Pelanggan"],
        .recent-deliveries-table td[data-label="Alamat Tujuan"] {
            flex-direction: column !important;
            align-items: flex-start !important;
            gap: 4px;
        }
        .couriers-methods-table td[data-label="Metode"] > *,
        .couriers-performance-table td[data-label="Nama Driver"] > *,
        .couriers-performance-table td[data-label="Email / Kontak"] > *,
        .recent-deliveries-table td[data-label="No. Transaksi"] > *,
        .recent-deliveries-table td[data-label="Pelanggan"] > *,
        .recent-deliveries-table td[data-label="Alamat Tujuan"] > * {
            text-align: left !important;
            width: 100% !important;
        }
    }
</style>
