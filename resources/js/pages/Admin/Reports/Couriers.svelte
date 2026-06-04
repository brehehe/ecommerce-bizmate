<script lang="ts">
    import { onMount } from 'svelte';
    import { page, router } from '@inertiajs/svelte';
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import Chart from 'chart.js/auto';

    const primaryColor = $derived(page.props.theme?.primary_color || '#0c4cb4');
    const secondaryColor = $derived(page.props.theme?.secondary_color || '#fa7315');

    let {
        metrics = { total_shipping_revenue: 0, total_delivery_volume: 0, completed_deliveries: 0, cancelled_deliveries: 0 },
        shippingSummary = [],
        rajaongkirBreakdown = [],
        courierPerformance = [],
        recentDeliveries = [],
        filters = { date_from: '', date_to: '' }
    } = $props();

    // svelte-ignore state_referenced_locally
    let dateFrom = $state(filters.date_from);
    // svelte-ignore state_referenced_locally
    let dateTo = $state(filters.date_to);

    let methodCanvas: HTMLCanvasElement | undefined = $state();
    let serviceCanvas: HTMLCanvasElement | undefined = $state();

    let methodChart: Chart;
    let serviceChart: Chart;

    function applyFilter() {
        router.get('/admin/reports/couriers', {
            date_from: dateFrom,
            date_to: dateTo
        }, {
            preserveState: true
        });
    }

    function exportToCSV() {
        const headers = ['Metode Pengiriman', 'Total Pesanan', 'Total Transaksi Selesai', 'Total Transaksi Batal', 'Total Nilai Transaksi', 'Total Ongkir Terkumpul', 'Rata-rata Ongkir'];
        const csvRows = [headers.join(',')];

        shippingSummary.forEach((s: any) => {
            const methodName = s.method === 'store_courier' ? 'Kurir Toko' : (s.method === 'self_pickup' ? 'Ambil di Toko' : 'RajaOngkir (3PL)');
            const row = [
                methodName,
                s.total_orders,
                s.completed_count,
                s.cancelled_count,
                Math.round(s.total_sales),
                Math.round(s.shipping_fees),
                Math.round(s.avg_shipping_fee)
            ];
            csvRows.push(row.join(','));
        });

        // Add blank row and then courier performance
        csvRows.push('');
        csvRows.push(['Performa Kurir Toko (Driver)', 'Total Ditugaskan', 'Selesai', 'Aktif', 'Batal', 'Rata-rata Waktu Kirim (Jam)'].join(','));
        courierPerformance.forEach((c: any) => {
            const row = [
                c.name,
                c.total_assigned,
                c.completed_count,
                c.active_count,
                c.cancelled_count,
                c.avg_delivery_hours !== null ? c.avg_delivery_hours : '-'
            ];
            csvRows.push(row.join(','));
        });

        const csvContent = "data:text/csv;charset=utf-8," + csvRows.join("\n");
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", `laporan_kurir_${dateFrom}_sd_${dateTo}.csv`);
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
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    onMount(() => {
        // 1. Doughnut Chart: Distribusi Metode Pengiriman
        if (methodCanvas) {
            const ctx = methodCanvas.getContext('2d');
            if (ctx) {
                const methodLabelsMap: any = {
                    'store_courier': 'Kurir Toko',
                    'self_pickup': 'Ambil di Toko',
                    'rajaongkir': 'RajaOngkir (3PL)'
                };

                methodChart = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: shippingSummary.map((s: any) => methodLabelsMap[s.method] || s.method),
                        datasets: [
                            {
                                data: shippingSummary.map((s: any) => s.total_orders),
                                backgroundColor: [
                                    primaryColor,
                                    secondaryColor,
                                    '#8b5cf6'
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

        // 2. Bar Chart: Breakdown RajaOngkir (3PL)
        if (serviceCanvas) {
            const ctx = serviceCanvas.getContext('2d');
            if (ctx) {
                serviceChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: rajaongkirBreakdown.map((r: any) => r.name),
                        datasets: [
                            {
                                label: 'Jumlah Pesanan',
                                data: rajaongkirBreakdown.map((r: any) => r.total_orders),
                                backgroundColor: primaryColor + 'cc',
                                borderColor: primaryColor,
                                borderWidth: 1,
                                borderRadius: 6
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            y: {
                                grid: { color: '#f1f5f9' },
                                ticks: { font: { size: 10 }, stepSize: 1 }
                            },
                            x: { grid: { display: false }, ticks: { font: { size: 10 } } }
                        }
                    }
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
    <title>Laporan Kurir & Logistik</title>
</svelte:head>

<AdminLayout>
    <main class="flex-grow p-4 sm:p-8 w-full max-w-[1600px] mx-auto space-y-6">
        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h1 class="font-outfit font-black text-2xl sm:text-3xl text-slate-800 tracking-tight">
                    Laporan Kurir & Logistik
                </h1>
                <p class="text-sm text-slate-500 font-medium mt-1">
                    Analisis distribusi pengiriman, performa kurir toko, dan logistik pengantaran pesanan.
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
            <!-- Card 1: Total Ongkir Terkumpul -->
            <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm flex flex-col justify-between">
                <div>
                    <span class="w-8 h-8 rounded-lg flex items-center justify-center text-lg text-emerald-600 bg-emerald-50 mb-3">
                        <i class="ti ti-coin"></i>
                    </span>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit">Total Ongkos Kirim</p>
                    <h3 class="font-outfit font-black text-xl sm:text-2xl text-slate-800 mt-1">
                        {formatRupiah(metrics.total_shipping_revenue)}
                    </h3>
                </div>
            </div>

            <!-- Card 2: Total Volume Pengiriman -->
            <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm flex flex-col justify-between">
                <div>
                    <span class="w-8 h-8 rounded-lg flex items-center justify-center text-lg text-blue-600 bg-blue-50 mb-3">
                        <i class="ti ti-truck-delivery"></i>
                    </span>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit">Volume Pengiriman</p>
                    <h3 class="font-outfit font-black text-xl sm:text-2xl text-slate-800 mt-1">
                        {metrics.total_delivery_volume} pesanan
                    </h3>
                </div>
            </div>

            <!-- Card 3: Selesai Pengiriman -->
            <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm flex flex-col justify-between">
                <div>
                    <span class="w-8 h-8 rounded-lg flex items-center justify-center text-lg text-purple-600 bg-purple-50 mb-3">
                        <i class="ti ti-circle-check"></i>
                    </span>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit">Pengiriman Selesai</p>
                    <h3 class="font-outfit font-black text-xl sm:text-2xl text-slate-800 mt-1">
                        {metrics.completed_deliveries}
                    </h3>
                </div>
            </div>

            <!-- Card 4: Gagal / Batal -->
            <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm flex flex-col justify-between">
                <div>
                    <span class="w-8 h-8 rounded-lg flex items-center justify-center text-lg text-rose-600 bg-rose-50 mb-3">
                        <i class="ti ti-circle-x"></i>
                    </span>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit">Pengiriman Batal</p>
                    <h3 class="font-outfit font-black text-xl sm:text-2xl text-slate-800 mt-1">
                        {metrics.cancelled_deliveries}
                    </h3>
                </div>
            </div>
        </div>

        <!-- Charts Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Distribusi Metode -->
            <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
                <div class="mb-4">
                    <h3 class="font-outfit font-black text-lg text-slate-800">Distribusi Metode Pengiriman</h3>
                    <p class="text-xs text-slate-500 font-medium">Berdasarkan total pesanan masuk.</p>
                </div>
                <div class="h-64 w-full">
                    {#if shippingSummary.length === 0}
                        <div class="h-full flex items-center justify-center text-xs text-slate-400">Tidak ada data.</div>
                    {:else}
                        <canvas bind:this={methodCanvas}></canvas>
                    {/if}
                </div>
            </div>

            <!-- Breakdown 3PL RajaOngkir -->
            <div class="lg:col-span-2 bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
                <div class="mb-4">
                    <h3 class="font-outfit font-black text-lg text-slate-800">Volume Kurir Pihak Ketiga (RajaOngkir)</h3>
                    <p class="text-xs text-slate-500 font-medium">Grafik perbandingan pengiriman via logistik 3PL.</p>
                </div>
                <div class="h-64 w-full">
                    {#if rajaongkirBreakdown.length === 0}
                        <div class="h-full flex items-center justify-center text-xs text-slate-400">Tidak ada pengiriman RajaOngkir pada periode ini.</div>
                    {:else}
                        <canvas bind:this={serviceCanvas}></canvas>
                    {/if}
                </div>
            </div>
        </div>

        <!-- Shipping Methods Breakdown Table -->
        <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-sm">
            <div class="p-6 border-b border-slate-100">
                <h3 class="font-outfit font-black text-lg text-slate-800">Rincian Metode Pengiriman</h3>
                <p class="text-xs text-slate-500 font-medium">Perbandingan performa keuangan dan volume dari masing-masing tipe logistik.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[800px]">
                    <thead>
                        <tr class="bg-slate-50/50 text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit border-b border-slate-100">
                            <th class="py-4 px-6">Metode</th>
                            <th class="py-4 px-6 text-center">Total Volume</th>
                            <th class="py-4 px-6 text-center">Selesai</th>
                            <th class="py-4 px-6 text-center">Batal</th>
                            <th class="py-4 px-6 text-right">Omset Pesanan</th>
                            <th class="py-4 px-6 text-right">Total Ongkir</th>
                            <th class="py-4 px-6 text-right">Rata-rata Ongkir</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700 text-sm font-medium">
                        {#each shippingSummary as row}
                            <tr class="hover:bg-slate-50/50 transition">
                                <td class="py-4 px-6 font-bold text-slate-800">
                                    {#if row.method === 'store_courier'}
                                        <span class="inline-flex items-center gap-1.5">
                                            <span class="w-2 h-2 rounded-full bg-blue-600"></span>
                                            Kurir Toko
                                        </span>
                                    {:else}
                                        {#if row.method === 'self_pickup'}
                                            <span class="inline-flex items-center gap-1.5">
                                                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                                Ambil di Toko
                                            </span>
                                        {:else}
                                            <span class="inline-flex items-center gap-1.5">
                                                <span class="w-2 h-2 rounded-full bg-purple-500"></span>
                                                RajaOngkir (3PL)
                                            </span>
                                        {/if}
                                    {/if}
                                </td>
                                <td class="py-4 px-6 text-center text-slate-600 font-semibold">{row.total_orders}</td>
                                <td class="py-4 px-6 text-center text-emerald-600 font-semibold">{row.completed_count}</td>
                                <td class="py-4 px-6 text-center text-rose-500 font-semibold">{row.cancelled_count}</td>
                                <td class="py-4 px-6 text-right text-slate-600 font-semibold">{formatRupiah(row.total_sales)}</td>
                                <td class="py-4 px-6 text-right text-slate-600 font-semibold">{formatRupiah(row.shipping_fees)}</td>
                                <td class="py-4 px-6 text-right font-black text-slate-800">{formatRupiah(row.avg_shipping_fee)}</td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Store Courier (Driver) Performance -->
        <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-sm">
            <div class="p-6 border-b border-slate-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2">
                <div>
                    <h3 class="font-outfit font-black text-lg text-slate-800">Performa Driver (Kurir Toko)</h3>
                    <p class="text-xs text-slate-500 font-medium">Metrik penugasan, penyelesaian pengantaran, dan estimasi rata-rata waktu kirim.</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[800px]">
                    <thead>
                        <tr class="bg-slate-50/50 text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit border-b border-slate-100">
                            <th class="py-4 px-6">Nama Driver</th>
                            <th class="py-4 px-6">Email / Kontak</th>
                            <th class="py-4 px-6 text-center">Total Ditugaskan</th>
                            <th class="py-4 px-6 text-center">Pengiriman Selesai</th>
                            <th class="py-4 px-6 text-center">Pengiriman Aktif</th>
                            <th class="py-4 px-6 text-center">Pengiriman Batal</th>
                            <th class="py-4 px-6 text-center">Rata-rata Waktu Kirim</th>
                            <th class="py-4 px-6 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700 text-sm font-medium">
                        {#if courierPerformance.length === 0}
                            <tr>
                                <td colspan="8" class="py-12 text-center text-slate-400 font-bold font-outfit">
                                    <i class="ti ti-users-minus text-3xl block mb-2 text-slate-300"></i>
                                    Belum ada staff dengan peran Kurir Toko.
                                </td>
                            </tr>
                        {:else}
                            {#each courierPerformance as row}
                                <tr class="hover:bg-slate-50/50 transition">
                                    <td class="py-4 px-6 font-bold text-slate-800">{row.name}</td>
                                    <td class="py-4 px-6 text-slate-500 text-xs">
                                        <div>{row.email}</div>
                                        <div class="mt-0.5 font-semibold text-slate-600">{row.phone_number || '-'}</div>
                                    </td>
                                    <td class="py-4 px-6 text-center text-slate-600 font-semibold">{row.total_assigned}</td>
                                    <td class="py-4 px-6 text-center text-emerald-600 font-semibold">{row.completed_count}</td>
                                    <td class="py-4 px-6 text-center text-blue-600 font-semibold">{row.active_count}</td>
                                    <td class="py-4 px-6 text-center text-rose-500 font-semibold">{row.cancelled_count}</td>
                                    <td class="py-4 px-6 text-center font-bold text-slate-800">
                                        {#if row.avg_delivery_hours !== null}
                                            {row.avg_delivery_hours} jam
                                        {:else}
                                            <span class="text-slate-400 font-normal">-</span>
                                        {/if}
                                    </td>
                                    <td class="py-4 px-6 text-center">
                                        <a 
                                            href="/admin/master-data/admins/{row.id}/courier-history"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-50 hover:bg-slate-100 text-slate-600 hover:text-slate-800 font-bold text-xs rounded-xl transition border border-slate-200"
                                        >
                                            <i class="ti ti-history text-sm"></i>
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
        <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-sm">
            <div class="p-6 border-b border-slate-100">
                <h3 class="font-outfit font-black text-lg text-slate-800">Log Pengiriman Terbaru</h3>
                <p class="text-xs text-slate-500 font-medium">Daftar 20 pesanan teratas yang sedang dikirim atau diselesaikan dalam periode ini.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[1000px]">
                    <thead>
                        <tr class="bg-slate-50/50 text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit border-b border-slate-100">
                            <th class="py-4 px-6">No. Transaksi</th>
                            <th class="py-4 px-6">Pelanggan</th>
                            <th class="py-4 px-6">Alamat Tujuan</th>
                            <th class="py-4 px-6">Kurir & Layanan</th>
                            <th class="py-4 px-6">Kode Booking / Resi</th>
                            <th class="py-4 px-6 text-center">Status</th>
                            <th class="py-4 px-6">Tanggal Dibuat</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700 text-sm font-medium">
                        {#if recentDeliveries.length === 0}
                            <tr>
                                <td colspan="7" class="py-12 text-center text-slate-400 font-bold font-outfit">
                                    <i class="ti ti-package-off text-3xl block mb-2 text-slate-300"></i>
                                    Tidak ada data log pengiriman untuk periode ini.
                                </td>
                            </tr>
                        {:else}
                            {#each recentDeliveries as row}
                                <tr class="hover:bg-slate-50/50 transition">
                                    <td class="py-4 px-6">
                                        <a href="/admin/transactions/{row.id}" class="font-bold text-brand-blueRoyal hover:underline">
                                            {row.transaction_number}
                                        </a>
                                    </td>
                                    <td class="py-4 px-6 font-semibold text-slate-800">{row.user_name}</td>
                                    <td class="py-4 px-6 text-xs text-slate-600 max-w-[250px] truncate">
                                        {#if row.address}
                                            <div class="font-bold text-slate-700">{row.address.name} ({row.address.phone})</div>
                                            <div class="text-slate-500 mt-0.5">{row.address.address_line}, {row.address.city}</div>
                                        {:else}
                                            <span class="text-slate-400">-</span>
                                        {/if}
                                    </td>
                                    <td class="py-4 px-6">
                                        <div class="font-semibold text-slate-800">{row.courier_name}</div>
                                        {#if row.shipping_courier === 'store_courier' && row.courier_driver_name}
                                            <div class="text-[10px] text-blue-600 font-bold uppercase mt-0.5">Driver: {row.courier_driver_name}</div>
                                        {:else}
                                            <div class="text-slate-500 text-xs mt-0.5">{row.shipping_service || '-'}</div>
                                        {/if}
                                    </td>
                                    <td class="py-4 px-6 text-xs font-mono">
                                        {#if row.booking_code}
                                            <div class="text-slate-700 font-semibold"><span class="text-[9px] text-slate-400 font-sans uppercase">Booking:</span> {row.booking_code}</div>
                                        {/if}
                                        {#if row.tracking_number}
                                            <div class="text-slate-500 mt-0.5"><span class="text-[9px] text-slate-400 font-sans uppercase">Resi:</span> {row.tracking_number}</div>
                                        {/if}
                                        {#if !row.booking_code && !row.tracking_number}
                                            <span class="text-slate-400">-</span>
                                        {/if}
                                    </td>
                                    <td class="py-4 px-6 text-center">
                                        {#if row.status === 'selesai'}
                                            <span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 text-xs font-bold rounded-lg border border-emerald-100">Selesai</span>
                                        {:else if row.status === 'batal'}
                                            <span class="px-2.5 py-1 bg-rose-50 text-rose-700 text-xs font-bold rounded-lg border border-rose-100">Batal</span>
                                        {:else if row.status === 'dikirim'}
                                            <span class="px-2.5 py-1 bg-blue-50 text-blue-700 text-xs font-bold rounded-lg border border-blue-100">Dikirim</span>
                                        {:else if row.status === 'out_for_pickup'}
                                            <span class="px-2.5 py-1 bg-purple-50 text-purple-700 text-xs font-bold rounded-lg border border-purple-100">Out for Pickup</span>
                                        {:else}
                                            <span class="px-2.5 py-1 bg-slate-50 text-slate-600 text-xs font-bold rounded-lg border border-slate-100">{row.status}</span>
                                        {/if}
                                    </td>
                                    <td class="py-4 px-6 text-xs text-slate-500">{formatDate(row.created_at)}</td>
                                </tr>
                            {/each}
                        {/if}
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</AdminLayout>
