<script lang="ts">
    import { onMount } from 'svelte';
    import { page, router } from '@inertiajs/svelte';
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import Pagination from '@/components/ui/Pagination.svelte';
    import Chart from 'chart.js/auto';

    const primaryColor = $derived(page.props.theme?.primary_color || '#0c4cb4');
    const secondaryColor = $derived(
        page.props.theme?.secondary_color || '#fa7315',
    );

    let {
        customers = { data: [], links: [], total: 0 },
        metrics = { new_customers: 0, active_customers: 0, total_customers: 0 },
        chartData = { labels: [], counts: [] },
        filters = { date_from: '', date_to: '', search: '' },
    } = $props();

    // svelte-ignore state_referenced_locally
    let dateFrom = $state(filters.date_from);
    // svelte-ignore state_referenced_locally
    let dateTo = $state(filters.date_to);
    // svelte-ignore state_referenced_locally
    let searchQuery = $state(filters.search || '');

    let regCanvas = $state<HTMLCanvasElement>();
    let regChart = $state<Chart>();

    let selectedCustomer = $state<any>(null);
    let showModal = $state(false);
    let transactionsData = $state<any>({
        data: [],
        links: [],
        current_page: 1,
        last_page: 1,
        total: 0,
    });
    let isLoadingTransactions = $state(false);

    async function fetchTransactions(customerId: number, page: number = 1) {
        isLoadingTransactions = true;
        try {
            const response = await fetch(
                `/admin/reports/customers/${customerId}/transactions?page=${page}`,
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                },
            );
            if (response.ok) {
                transactionsData = await response.json();
            } else {
                console.error('Failed to fetch transactions');
            }
        } catch (error) {
            console.error('Error fetching transactions:', error);
        } finally {
            isLoadingTransactions = false;
        }
    }

    function openCustomerDetail(customer: any) {
        selectedCustomer = customer;
        showModal = true;
        transactionsData = {
            data: [],
            links: [],
            current_page: 1,
            last_page: 1,
            total: 0,
        };
        fetchTransactions(customer.id, 1);
    }

    function getStatusBadgeClass(status: string) {
        switch (status) {
            case 'belum_bayar':
                return 'bg-amber-50 text-amber-700 border border-amber-200';
            case 'menunggu':
                return 'bg-blue-50 text-blue-700 border border-blue-200';
            case 'diproses':
                return 'bg-indigo-50 text-indigo-700 border border-indigo-200';
            case 'dikemas':
                return 'bg-purple-50 text-purple-700 border border-purple-200';
            case 'dikirim':
                return 'bg-sky-50 text-sky-700 border border-sky-200';
            case 'selesai':
                return 'bg-emerald-50 text-emerald-700 border border-emerald-200';
            case 'batal':
                return 'bg-rose-50 text-rose-700 border border-rose-200';
            default:
                return 'bg-slate-50 text-slate-700 border border-slate-200';
        }
    }

    function getStatusLabel(status: string) {
        const labels: Record<string, string> = {
            belum_bayar: 'Belum Bayar',
            menunggu: 'Menunggu Konfirmasi',
            diproses: 'Diproses',
            dikemas: 'Dikemas',
            out_for_pickup: 'Out for Pickup',
            dikirim: 'Dikirim',
            selesai: 'Selesai',
            batal: 'Batal',
        };
        return labels[status] || status;
    }

    function applyFilter() {
        router.get(
            '/admin/reports/customers',
            {
                date_from: dateFrom,
                date_to: dateTo,
                search: searchQuery,
            },
            {
                preserveState: true,
            },
        );
    }

    function resetFilter() {
        dateFrom = filters.date_from;
        dateTo = filters.date_to;
        searchQuery = '';
        applyFilter();
    }

    function exportToCSV() {
        const headers = [
            'Nama Pelanggan',
            'Email',
            'Tanggal Daftar',
            'Jumlah Transaksi Sukses',
            'Rata-rata Belanja (AOV)',
            'Total Diskon',
            'Koin Ditebus',
            'Terakhir Belanja',
            'Total Belanja',
        ];
        const csvRows = [headers.join(',')];

        customers.data.forEach((c: any) => {
            const row = [
                `"${c.name.replace(/"/g, '""')}"`,
                c.email,
                new Date(c.registered_at).toISOString().split('T')[0],
                c.orders_count,
                Math.round(c.average_order_value || 0),
                Math.round(c.total_discounts || 0),
                Math.round(c.total_coins_redeemed || 0),
                c.last_order_date
                    ? new Date(c.last_order_date).toISOString().split('T')[0]
                    : '-',
                Math.round(c.total_spent),
            ];
            csvRows.push(row.join(','));
        });

        const csvContent = 'data:text/csv;charset=utf-8,' + csvRows.join('\n');
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement('a');
        link.setAttribute('href', encodedUri);
        link.setAttribute(
            'download',
            `laporan_aktivitas_pelanggan_${dateFrom}_sd_${dateTo}.csv`,
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
        if (!dateStr) return '-';
        return new Date(dateStr).toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
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
                                ticks: { font: { size: 9 }, stepSize: 1 },
                            },
                            x: {
                                grid: { display: false },
                                ticks: { font: { size: 9 } },
                            },
                        },
                    },
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
        <div
            class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4"
        >
            <div>
                <h1
                    class="font-outfit font-black text-2xl sm:text-3xl text-slate-800 tracking-tight"
                >
                    Laporan Aktivitas Pelanggan
                </h1>
                <p class="text-sm text-slate-500 font-medium mt-1">
                    Analisis pertumbuhan pendaftaran pelanggan baru dan daftar
                    top spender belanja.
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
        <div
            class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4"
        >
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="space-y-1.5">
                    <label
                        for="date_from"
                        class="text-xs font-bold text-slate-500 uppercase tracking-wider"
                        >Tanggal Mulai</label
                    >
                    <input
                        type="date"
                        id="date_from"
                        bind:value={dateFrom}
                        class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-sm font-semibold rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-blueRoyal/20 focus:border-brand-blueRoyal transition"
                    />
                </div>
                <div class="space-y-1.5">
                    <label
                        for="date_to"
                        class="text-xs font-bold text-slate-500 uppercase tracking-wider"
                        >Tanggal Selesai</label
                    >
                    <input
                        type="date"
                        id="date_to"
                        bind:value={dateTo}
                        class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-sm font-semibold rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-blueRoyal/20 focus:border-brand-blueRoyal transition"
                    />
                </div>
                <div class="space-y-1.5">
                    <label
                        for="search"
                        class="text-xs font-bold text-slate-500 uppercase tracking-wider"
                        >Cari Nama / Email</label
                    >
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
            <div
                class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm flex items-center justify-between"
            >
                <div>
                    <p
                        class="text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit"
                    >
                        Total Pelanggan Baru
                    </p>
                    <h3
                        class="font-outfit font-black text-xl sm:text-2xl text-slate-800 mt-1"
                    >
                        {metrics.new_customers} orang
                    </h3>
                    <p class="text-[11px] text-slate-400 mt-1">
                        Mendaftar dalam periode rentang waktu ini.
                    </p>
                </div>
                <span
                    class="w-12 h-12 rounded-2xl flex items-center justify-center text-2xl text-blue-600 bg-blue-50"
                >
                    <i class="ti ti-user-plus"></i>
                </span>
            </div>

            <div
                class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm flex items-center justify-between"
            >
                <div>
                    <p
                        class="text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit"
                    >
                        Pelanggan Aktif Belanja
                    </p>
                    <h3
                        class="font-outfit font-black text-xl sm:text-2xl text-slate-800 mt-1"
                    >
                        {metrics.active_customers} orang
                    </h3>
                    <p class="text-[11px] text-slate-400 mt-1">
                        Belanja minimal 1 kali transaksi sukses.
                    </p>
                </div>
                <span
                    class="w-12 h-12 rounded-2xl flex items-center justify-center text-2xl text-emerald-600 bg-emerald-50"
                >
                    <i class="ti ti-users-group"></i>
                </span>
            </div>

            <div
                class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm flex items-center justify-between"
            >
                <div>
                    <p
                        class="text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit"
                    >
                        Total Basis Pelanggan
                    </p>
                    <h3
                        class="font-outfit font-black text-xl sm:text-2xl text-slate-800 mt-1"
                    >
                        {metrics.total_customers} orang
                    </h3>
                    <p class="text-[11px] text-slate-400 mt-1">
                        Akumulasi seluruh akun pelanggan terdaftar.
                    </p>
                </div>
                <span
                    class="w-12 h-12 rounded-2xl flex items-center justify-center text-2xl text-purple-600 bg-purple-50"
                >
                    <i class="ti ti-database"></i>
                </span>
            </div>
        </div>

        <!-- Registration Chart -->
        <div class="grid grid-cols-1 gap-6">
            <div
                class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm"
            >
                <h3 class="font-outfit font-black text-lg text-slate-800 mb-2">
                    Tren Pendaftaran Pelanggan Baru
                </h3>
                <p class="text-xs text-slate-500 font-medium mb-4">
                    Grafik jumlah registrasi akun customer baru per hari.
                </p>
                <div class="h-64 w-full">
                    {#if chartData.labels.length === 0}
                        <div
                            class="h-full flex items-center justify-center text-xs text-slate-400"
                        >
                            Tidak ada pendaftaran baru dalam rentang waktu ini.
                        </div>
                    {:else}
                        <canvas bind:this={regCanvas}></canvas>
                    {/if}
                </div>
            </div>
        </div>

        <!-- Spenders Table -->
        <div
            class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-sm"
        >
            <div class="p-6 border-b border-slate-100">
                <h3 class="font-outfit font-black text-lg text-slate-800">
                    Daftar Kontribusi Belanja Pelanggan
                </h3>
                <p class="text-xs text-slate-500 font-medium">
                    Pelanggan diurutkan berdasarkan total nilai nominal belanja
                    terbanyak. Klik baris pelanggan untuk melihat riwayat
                    transaksi detail.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[800px]">
                    <thead>
                        <tr
                            class="bg-slate-50/50 text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit border-b border-slate-100"
                        >
                            <th class="py-4 px-6">Nama Pelanggan</th>
                            <th class="py-4 px-6">Email</th>
                            <th class="py-4 px-6">Tanggal Bergabung</th>
                            <th class="py-4 px-6 text-center"
                                >Jumlah Transaksi</th
                            >
                            <th class="py-4 px-6 text-right"
                                >Rata-rata Belanja (AOV)</th
                            >
                            <th class="py-4 px-6 text-right">Total Diskon</th>
                            <th class="py-4 px-6 text-right">Koin Ditebus</th>
                            <th class="py-4 px-6 text-right"
                                >Terakhir Belanja</th
                            >
                            <th class="py-4 px-6 text-right"
                                >Total Belanja (Periode)</th
                            >
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-slate-100 text-slate-700 text-sm font-medium"
                    >
                        {#if customers.data.length === 0}
                            <tr>
                                <td
                                    colspan="9"
                                    class="py-12 text-center text-slate-400 font-bold font-outfit"
                                >
                                    <i
                                        class="ti ti-users text-3xl block mb-2 text-slate-300"
                                    ></i>
                                    Tidak ada data aktivitas pelanggan.
                                </td>
                            </tr>
                        {:else}
                            {#each customers.data as item}
                                <tr
                                    class="hover:bg-slate-50/50 transition cursor-pointer"
                                    onclick={() => openCustomerDetail(item)}
                                >
                                    <td
                                        class="py-4 px-6 font-bold text-slate-800"
                                        >{item.name}</td
                                    >
                                    <td
                                        class="py-4 px-6 text-slate-500 text-xs font-semibold"
                                        >{item.email}</td
                                    >
                                    <td class="py-4 px-6 text-slate-500 text-xs"
                                        >{formatDate(item.registered_at)}</td
                                    >
                                    <td
                                        class="py-4 px-6 text-center font-bold text-slate-600"
                                        >{item.orders_count} kali</td
                                    >
                                    <td
                                        class="py-4 px-6 text-right font-bold text-slate-700"
                                        >{formatRupiah(
                                            item.average_order_value,
                                        )}</td
                                    >
                                    <td
                                        class="py-4 px-6 text-right font-bold text-rose-600"
                                        >-{formatRupiah(
                                            item.total_discounts,
                                        )}</td
                                    >
                                    <td
                                        class="py-4 px-6 text-right font-bold text-amber-600"
                                        >{item.total_coins_redeemed} koin</td
                                    >
                                    <td
                                        class="py-4 px-6 text-right text-slate-500 text-xs"
                                        >{formatDate(item.last_order_date)}</td
                                    >
                                    <td
                                        class="py-4 px-6 text-right font-black text-slate-800"
                                        >{formatRupiah(item.total_spent)}</td
                                    >
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

{#if showModal && selectedCustomer}
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <!-- Backdrop -->
        <div
            class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity"
            onclick={() => (showModal = false)}
            onkeydown={() => (showModal = false)}
            role="button"
            tabindex="0"
        ></div>

        <!-- Modal Box -->
        <div
            class="relative bg-white rounded-3xl shadow-2xl w-full max-w-4xl overflow-hidden z-10 animate-in fade-in zoom-in duration-200 flex flex-col max-h-[85vh]"
        >
            <!-- Modal Header -->
            <div
                class="p-6 border-b border-slate-100 flex items-center justify-between bg-slate-50 shrink-0"
            >
                <div class="flex items-center gap-3">
                    <span
                        class="w-10 h-10 rounded-2xl flex items-center justify-center text-xl text-blue-600 bg-blue-50"
                    >
                        <i class="ti ti-user"></i>
                    </span>
                    <div>
                        <h3
                            class="font-outfit font-black text-lg text-slate-800 leading-tight"
                        >
                            Riwayat Penjualan: {selectedCustomer.name}
                        </h3>
                        <p class="text-xs text-slate-400 font-medium">
                            {selectedCustomer.email} &bull; Bergabung pada {formatDate(
                                selectedCustomer.registered_at,
                            )}
                        </p>
                    </div>
                </div>
                <button
                    onclick={() => (showModal = false)}
                    class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 transition flex items-center justify-center cursor-pointer border-0"
                    aria-label="Tutup Modal"
                >
                    <i class="ti ti-x text-lg"></i>
                </button>
            </div>

            <!-- Modal Body (List of transactions) -->
            <div class="p-6 overflow-y-auto flex-grow space-y-4">
                {#if isLoadingTransactions}
                    <div
                        class="py-12 flex flex-col items-center justify-center text-slate-400 space-y-3"
                    >
                        <div
                            class="animate-spin rounded-full h-8 w-8 border-b-2 border-slate-400"
                        ></div>
                        <span
                            class="text-xs font-bold font-outfit uppercase tracking-wider"
                            >Memuat riwayat transaksi...</span
                        >
                    </div>
                {:else if transactionsData.data.length === 0}
                    <div
                        class="py-12 text-center text-slate-400 font-bold font-outfit"
                    >
                        <i
                            class="ti ti-receipt-off text-3xl block mb-2 text-slate-300"
                        ></i>
                        Belum ada riwayat transaksi untuk pelanggan ini.
                    </div>
                {:else}
                    <div
                        class="overflow-x-auto border border-slate-100 rounded-2xl"
                    >
                        <table
                            class="w-full text-left border-collapse min-w-[700px]"
                        >
                            <thead>
                                <tr
                                    class="bg-slate-50/50 text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit border-b border-slate-100"
                                >
                                    <th class="py-3 px-4">No. Transaksi</th>
                                    <th class="py-3 px-4">Tanggal</th>
                                    <th class="py-3 px-4">Metode Bayar</th>
                                    <th class="py-3 px-4 text-center">Item</th>
                                    <th class="py-3 px-4 text-right">Diskon</th>
                                    <th class="py-3 px-4 text-right"
                                        >Koin Ditukar</th
                                    >
                                    <th class="py-3 px-4 text-right"
                                        >Total Belanja</th
                                    >
                                    <th class="py-3 px-4 text-center">Status</th
                                    >
                                    <th class="py-3 px-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-slate-100 text-slate-700 text-xs font-medium"
                            >
                                {#each transactionsData.data as tx}
                                    <tr
                                        class="hover:bg-slate-50/30 transition animate-in fade-in duration-150"
                                    >
                                        <td
                                            class="py-3 px-4 font-mono font-bold text-slate-800"
                                            >{tx.transaction_number}</td
                                        >
                                        <td class="py-3 px-4 text-slate-500"
                                            >{formatDate(tx.created_at)}</td
                                        >
                                        <td class="py-3 px-4">
                                            <span
                                                class="px-2 py-0.5 bg-slate-100 rounded-lg text-slate-600 font-bold border border-slate-200"
                                            >
                                                {tx.payment_method?.name || '-'}
                                            </span>
                                        </td>
                                        <td
                                            class="py-3 px-4 text-center font-bold text-slate-600"
                                            >{tx.items_count} pcs</td
                                        >
                                        <td
                                            class="py-3 px-4 text-right text-rose-500 font-semibold"
                                        >
                                            {tx.discount_amount > 0
                                                ? `-${formatRupiah(tx.discount_amount)}`
                                                : '-'}
                                        </td>
                                        <td
                                            class="py-3 px-4 text-right text-amber-600 font-semibold"
                                        >
                                            {tx.coins_redeemed > 0
                                                ? `${tx.coins_redeemed} koin`
                                                : '-'}
                                        </td>
                                        <td
                                            class="py-3 px-4 text-right font-bold text-slate-800"
                                            >{formatRupiah(tx.grand_total)}</td
                                        >
                                        <td class="py-3 px-4 text-center">
                                            <span
                                                class="inline-block px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider {getStatusBadgeClass(
                                                    tx.status,
                                                )}"
                                            >
                                                {getStatusLabel(tx.status)}
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <a
                                                href={`/admin/transactions/${tx.id}`}
                                                target="_blank"
                                                class="inline-flex items-center justify-center gap-1.5 px-3 py-1 bg-white border border-slate-200 text-slate-600 font-bold rounded-xl text-[10px] hover:bg-slate-50 transition shadow-3xs uppercase tracking-wider font-outfit"
                                            >
                                                <span>Detail</span>
                                                <i class="ti ti-external-link"
                                                ></i>
                                            </a>
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>

                    <!-- Modal Pagination -->
                    <div
                        class="flex items-center justify-between mt-4 px-2 py-3 bg-slate-50/50 border-t border-slate-100 shrink-0"
                    >
                        <span
                            class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider"
                        >
                            Halaman {transactionsData.current_page} dari {transactionsData.last_page}
                            ({transactionsData.total} Transaksi)
                        </span>
                        <div class="flex gap-2">
                            <button
                                disabled={transactionsData.current_page === 1}
                                onclick={() =>
                                    fetchTransactions(
                                        selectedCustomer.id,
                                        transactionsData.current_page - 1,
                                    )}
                                class="px-3 py-1.5 bg-white border border-slate-200 text-slate-600 hover:text-slate-800 font-bold rounded-xl text-xs hover:bg-slate-50 transition disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                            >
                                Sebelumnya
                            </button>
                            <button
                                disabled={transactionsData.current_page ===
                                    transactionsData.last_page}
                                onclick={() =>
                                    fetchTransactions(
                                        selectedCustomer.id,
                                        transactionsData.current_page + 1,
                                    )}
                                class="px-3 py-1.5 bg-white border border-slate-200 text-slate-600 hover:text-slate-800 font-bold rounded-xl text-xs hover:bg-slate-50 transition disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
                            >
                                Berikutnya
                            </button>
                        </div>
                    </div>
                {/if}
            </div>

            <!-- Modal Footer -->
            <div
                class="p-6 border-t border-slate-100 bg-slate-50/50 flex justify-end shrink-0"
            >
                <button
                    onclick={() => (showModal = false)}
                    class="px-5 py-2.5 bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold rounded-xl text-xs transition uppercase tracking-wider font-outfit cursor-pointer border-0"
                >
                    Tutup
                </button>
            </div>
        </div>
    </div>
{/if}
