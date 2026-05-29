<script lang="ts">
    import { onMount } from 'svelte';
    import { page, router } from '@inertiajs/svelte';
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import Pagination from '@/components/ui/Pagination.svelte';
    import Chart from 'chart.js/auto';

    const primaryColor = $derived(page.props.theme?.primary_color || '#0c4cb4');
    const secondaryColor = $derived(page.props.theme?.secondary_color || '#fa7315');

    let {
        customers = { data: [], links: [], total: 0 },
        metrics = { new_customers: 0, active_customers: 0, total_customers: 0 },
        chartData = { labels: [], counts: [] },
        filters = { date_from: '', date_to: '', search: '' }
    } = $props();

    let dateFrom = $state(filters.date_from);
    let dateTo = $state(filters.date_to);
    let searchQuery = $state(filters.search || '');

    let regCanvas: HTMLCanvasElement;
    let regChart: Chart;

    function applyFilter() {
        router.get('/admin/reports/customers', {
            date_from: dateFrom,
            date_to: dateTo,
            search: searchQuery
        }, {
            preserveState: true
        });
    }

    function resetFilter() {
        dateFrom = filters.date_from;
        dateTo = filters.date_to;
        searchQuery = '';
        applyFilter();
    }

    function exportToCSV() {
        const headers = ['Nama Pelanggan', 'Email', 'Tanggal Daftar', 'Jumlah Transaksi Sukses', 'Total Belanja'];
        const csvRows = [headers.join(',')];

        customers.data.forEach((c: any) => {
            const row = [
                `"${c.name.replace(/"/g, '""')}"`,
                c.email,
                new Date(c.registered_at).toISOString().split('T')[0],
                c.orders_count,
                Math.round(c.total_spent)
            ];
            csvRows.push(row.join(','));
        });

        const csvContent = "data:text/csv;charset=utf-8," + csvRows.join("\n");
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", `laporan_aktivitas_pelanggan_${dateFrom}_sd_${dateTo}.csv`);
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
        if (!dateStr) return '-';
        return new Date(dateStr).toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        });
    }

    onMount(() => {
        if (regCanvas && chartData.labels.length > 0) {
            const ctx = regCanvas.getContext('2d');
            if (ctx) {
                regChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: [...chartData.labels],
                        datasets: [
                            {
                                label: 'Pendaftaran Baru',
                                data: [...chartData.counts],
                                backgroundColor: primaryColor,
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
                            y: { grid: { color: '#f1f5f9' }, ticks: { font: { size: 9 }, stepSize: 1 } },
                            x: { grid: { display: false }, ticks: { font: { size: 9 } } }
                        }
                    }
                });
            }
        }

        return () => {
            if (regChart) regChart.destroy();
        };
    });
</script>

<svelte:head>
    <title>Laporan Pelanggan</title>
</svelte:head>

<AdminLayout>
    <main class="flex-grow p-4 sm:p-8 w-full max-w-[1600px] mx-auto space-y-6">
        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h1 class="font-outfit font-black text-2xl sm:text-3xl text-slate-800 tracking-tight">
                    Laporan Aktivitas Pelanggan
                </h1>
                <p class="text-sm text-slate-500 font-medium mt-1">
                    Analisis pertumbuhan pendaftaran pelanggan baru dan daftar top spender belanja.
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
        <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
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
                    <label for="search" class="text-xs font-bold text-slate-500 uppercase tracking-wider">Cari Nama / Email</label>
                    <input 
                        type="text" 
                        id="search"
                        bind:value={searchQuery}
                        placeholder="Ketik nama atau email..."
                        class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-sm font-semibold rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-blueRoyal/20 focus:border-brand-blueRoyal transition"
                    />
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
                    Terapkan Filter
                </button>
            </div>
        </div>

        <!-- KPI Cards Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit">Total Pelanggan Baru</p>
                    <h3 class="font-outfit font-black text-xl sm:text-2xl text-slate-800 mt-1">
                        {metrics.new_customers} orang
                    </h3>
                    <p class="text-[11px] text-slate-400 mt-1">Mendaftar dalam periode rentang waktu ini.</p>
                </div>
                <span class="w-12 h-12 rounded-2xl flex items-center justify-center text-2xl text-blue-600 bg-blue-50">
                    <i class="ti ti-user-plus"></i>
                </span>
            </div>

            <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit">Pelanggan Aktif Belanja</p>
                    <h3 class="font-outfit font-black text-xl sm:text-2xl text-slate-800 mt-1">
                        {metrics.active_customers} orang
                    </h3>
                    <p class="text-[11px] text-slate-400 mt-1">Belanja minimal 1 kali transaksi sukses.</p>
                </div>
                <span class="w-12 h-12 rounded-2xl flex items-center justify-center text-2xl text-emerald-600 bg-emerald-50">
                    <i class="ti ti-users-group"></i>
                </span>
            </div>

            <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit">Total Basis Pelanggan</p>
                    <h3 class="font-outfit font-black text-xl sm:text-2xl text-slate-800 mt-1">
                        {metrics.total_customers} orang
                    </h3>
                    <p class="text-[11px] text-slate-400 mt-1">Akumulasi seluruh akun pelanggan terdaftar.</p>
                </div>
                <span class="w-12 h-12 rounded-2xl flex items-center justify-center text-2xl text-purple-600 bg-purple-50">
                    <i class="ti ti-database"></i>
                </span>
            </div>
        </div>

        <!-- Registration Chart -->
        <div class="grid grid-cols-1 gap-6">
            <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
                <h3 class="font-outfit font-black text-lg text-slate-800 mb-2">Tren Pendaftaran Pelanggan Baru</h3>
                <p class="text-xs text-slate-500 font-medium mb-4">Grafik jumlah registrasi akun customer baru per hari.</p>
                <div class="h-64 w-full">
                    {#if chartData.labels.length === 0}
                        <div class="h-full flex items-center justify-center text-xs text-slate-400">Tidak ada pendaftaran baru dalam rentang waktu ini.</div>
                    {:else}
                        <canvas bind:this={regCanvas}></canvas>
                    {/if}
                </div>
            </div>
        </div>

        <!-- Spenders Table -->
        <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-sm">
            <div class="p-6 border-b border-slate-100">
                <h3 class="font-outfit font-black text-lg text-slate-800">Daftar Kontribusi Belanja Pelanggan</h3>
                <p class="text-xs text-slate-500 font-medium">Pelanggan diurutkan berdasarkan total nilai nominal belanja terbanyak.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[800px]">
                    <thead>
                        <tr class="bg-slate-50/50 text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit border-b border-slate-100">
                            <th class="py-4 px-6">Nama Pelanggan</th>
                            <th class="py-4 px-6">Email</th>
                            <th class="py-4 px-6">Tanggal Bergabung</th>
                            <th class="py-4 px-6 text-center">Jumlah Transaksi</th>
                            <th class="py-4 px-6 text-right">Terakhir Belanja</th>
                            <th class="py-4 px-6 text-right">Total Belanja (Periode)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700 text-sm font-medium">
                        {#if customers.data.length === 0}
                            <tr>
                                <td colspan="6" class="py-12 text-center text-slate-400 font-bold font-outfit">
                                    <i class="ti ti-users text-3xl block mb-2 text-slate-300"></i>
                                    Tidak ada data aktivitas pelanggan.
                                </td>
                            </tr>
                        {:else}
                            {#each customers.data as item}
                                <tr class="hover:bg-slate-50/50 transition">
                                    <td class="py-4 px-6 font-bold text-slate-800">{item.name}</td>
                                    <td class="py-4 px-6 text-slate-500 text-xs font-semibold">{item.email}</td>
                                    <td class="py-4 px-6 text-slate-500 text-xs">{formatDate(item.registered_at)}</td>
                                    <td class="py-4 px-6 text-center font-bold text-slate-600">{item.orders_count} kali</td>
                                    <td class="py-4 px-6 text-right text-slate-500 text-xs">{formatDate(item.last_order_date)}</td>
                                    <td class="py-4 px-6 text-right font-black text-slate-800">{formatRupiah(item.total_spent)}</td>
                                </tr>
                            {/each}
                        {/if}
                    </tbody>
                </table>
            </div>

            <Pagination paginator={customers} />
        </div>
    </main>
</AdminLayout>
