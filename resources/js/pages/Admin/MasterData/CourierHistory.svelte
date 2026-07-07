<script>
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { Link } from '@inertiajs/svelte';
    import Pagination from '@/components/ui/Pagination.svelte';

    let { courier = {}, transactions = { data: [], links: [], total: 0 } } =
        $props();

    function fmt(price) {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
        }).format(Number(price) || 0);
    }

    function fmtDate(d) {
        if (!d) return '-';
        return new Date(d).toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    // Calculate quick stats from current paginated page / total
    const totalDeliveries = $derived(transactions.total || 0);
    const activeDeliveries = $derived(
        transactions.data.filter(
            (t) => !['selesai', 'batal'].includes(t.status),
        ).length,
    );
    const completedDeliveries = $derived(
        transactions.data.filter((t) => t.status === 'selesai').length,
    );

    const statusColors = {
        menunggu: 'bg-yellow-50 text-yellow-700 border-yellow-250/50',
        diproses: 'bg-blue-50 text-blue-700 border-blue-200/50',
        dikemas: 'bg-indigo-50 text-indigo-700 border-indigo-200/50',
        out_for_pickup: 'bg-orange-50 text-orange-700 border-orange-200/50',
        dikirim: 'bg-cyan-50 text-cyan-700 border-cyan-200/50',
        selesai: 'bg-emerald-50 text-emerald-700 border-emerald-200/50',
        batal: 'bg-red-50 text-red-700 border-red-200/50',
    };

    const statusLabels = {
        menunggu: 'Menunggu',
        diproses: 'Diproses',
        dikemas: 'Dikemas',
        out_for_pickup: 'Out for Pickup',
        dikirim: 'Dikirim',
        selesai: 'Selesai',
        batal: 'Batal',
    };
</script>

<svelte:head>
    <title>Riwayat Kurir: {courier.name}</title>
</svelte:head>

<AdminLayout>
    <div class="flex-grow flex flex-col min-h-screen">
        <main class="flex-grow p-4 sm:p-8 w-full max-w-full mx-auto space-y-6">
            <!-- Back & Header -->
            <div class="space-y-4">
                <Link
                    href="/admin/master-data/admins"
                    class="inline-flex items-center gap-2 text-xs font-bold text-slate-500 hover:text-slate-800 transition font-outfit uppercase tracking-wider"
                >
                    <i class="ti ti-arrow-left text-sm"></i>
                    Kembali ke Admin Users
                </Link>

                <div
                    class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4"
                >
                    <div>
                        <h3
                            class="font-outfit font-black text-2xl text-slate-800"
                        >
                            Riwayat Pengiriman: {courier.name}
                        </h3>
                        <p
                            class="text-xs text-slate-400 font-bold uppercase tracking-wider mt-1"
                        >
                            Email: {courier.email} · Peran: Kurir Toko
                        </p>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                <!-- Card 1: Total Deliveries -->
                <div
                    class="bg-white rounded-3xl border border-slate-200/80 p-6 flex items-center gap-4 shadow-card"
                >
                    <div
                        class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl shrink-0"
                    >
                        <i class="ti ti-truck-delivery"></i>
                    </div>
                    <div>
                        <p
                            class="text-[11px] text-slate-400 font-bold uppercase tracking-wider"
                        >
                            Total Tugas
                        </p>
                        <p
                            class="text-2xl font-black text-slate-800 font-outfit leading-tight mt-0.5"
                        >
                            {totalDeliveries}
                        </p>
                    </div>
                </div>

                <!-- Card 2: Active Deliveries -->
                <div
                    class="bg-white rounded-3xl border border-slate-200/80 p-6 flex items-center gap-4 shadow-card"
                >
                    <div
                        class="w-12 h-12 rounded-2xl bg-orange-50 text-orange-600 flex items-center justify-center text-xl shrink-0"
                    >
                        <i class="ti ti-loader animate-spin-slow"></i>
                    </div>
                    <div>
                        <p
                            class="text-[11px] text-slate-400 font-bold uppercase tracking-wider"
                        >
                            Sedang Aktif (Hal Ini)
                        </p>
                        <p
                            class="text-2xl font-black text-slate-800 font-outfit leading-tight mt-0.5"
                        >
                            {activeDeliveries}
                        </p>
                    </div>
                </div>

                <!-- Card 3: Completed Deliveries -->
                <div
                    class="bg-white rounded-3xl border border-slate-200/80 p-6 flex items-center gap-4 shadow-card"
                >
                    <div
                        class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl shrink-0"
                    >
                        <i class="ti ti-circle-check"></i>
                    </div>
                    <div>
                        <p
                            class="text-[11px] text-slate-400 font-bold uppercase tracking-wider"
                        >
                            Tugas Selesai (Hal Ini)
                        </p>
                        <p
                            class="text-2xl font-black text-slate-800 font-outfit leading-tight mt-0.5"
                        >
                            {completedDeliveries}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Table Card -->
            <div
                class="bg-white rounded-3xl border border-slate-200/80 shadow-card overflow-hidden"
            >
                <div
                    class="p-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/20"
                >
                    <h4
                        class="font-outfit font-black text-sm text-slate-800 uppercase tracking-wider"
                    >
                        Daftar Pengiriman
                    </h4>
                    <span
                        class="px-3 py-1 bg-slate-100 text-slate-600 rounded-full text-xs font-bold font-mono"
                    >
                        {transactions.total} Transaksi
                    </span>
                </div>

                {#if transactions.data.length === 0}
                    <div
                        class="py-16 text-center text-slate-400 font-bold font-outfit"
                    >
                        <i
                            class="ti ti-truck text-4xl block mb-2 text-slate-300"
                        ></i>
                        Kurir ini belum pernah ditugaskan mengambil atau mengirim
                        paket.
                    </div>
                {:else}
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr
                                    class="border-b border-slate-100 bg-slate-50/50 text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit"
                                >
                                    <th class="py-5 px-6">No. Pesanan</th>
                                    <th class="py-5 px-6">Pelanggan</th>
                                    <th class="py-5 px-6">Tujuan Alamat</th>
                                    <th class="py-5 px-6"
                                        >Kode Booking / Resi</th
                                    >
                                    <th class="py-5 px-6">Tanggal Pesanan</th>
                                    <th class="py-5 px-6">Status</th>
                                    <th class="py-5 px-6">Total Pembayaran</th>
                                    <th class="py-5 px-6 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-slate-100 text-slate-700 text-sm font-medium"
                            >
                                {#each transactions.data as trx (trx.id)}
                                    <tr
                                        class="hover:bg-slate-50/50 transition duration-150"
                                    >
                                        <td class="py-5 px-6">
                                            <Link
                                                href="/admin/transactions/{trx.id}"
                                                class="font-outfit font-black text-brand-blueRoyal hover:underline"
                                            >
                                                #{trx.transaction_number}
                                            </Link>
                                        </td>
                                        <td class="py-5 px-6">
                                            <div>
                                                <p
                                                    class="font-bold text-slate-800"
                                                >
                                                    {trx.user?.name ?? '-'}
                                                </p>
                                                <p
                                                    class="text-[11px] text-slate-400 font-bold mt-0.5"
                                                >
                                                    {trx.user?.phone_number ??
                                                        '-'}
                                                </p>
                                            </div>
                                        </td>
                                        <td class="py-5 px-6">
                                            <div class="max-w-[200px] truncate">
                                                {#if trx.customer_address}
                                                    <p
                                                        class="text-xs text-slate-600 truncate"
                                                    >
                                                        {trx.customer_address
                                                            .district_name}, {trx
                                                            .customer_address
                                                            .regency_name}
                                                    </p>
                                                {:else}
                                                    <p
                                                        class="text-xs text-slate-400 italic"
                                                    >
                                                        Alamat tidak tersedia
                                                    </p>
                                                {/if}
                                            </div>
                                        </td>
                                        <td
                                            class="py-5 px-6 font-mono text-xs space-y-1"
                                        >
                                            {#if trx.booking_code}
                                                <div
                                                    class="flex items-center gap-1"
                                                >
                                                    <span
                                                        class="text-slate-400 uppercase text-[9px] font-bold"
                                                        >Book:</span
                                                    >
                                                    <span
                                                        class="text-slate-700 font-bold"
                                                        >{trx.booking_code}</span
                                                    >
                                                </div>
                                            {/if}
                                            {#if trx.tracking_number}
                                                <div
                                                    class="flex items-center gap-1"
                                                >
                                                    <span
                                                        class="text-emerald-500 uppercase text-[9px] font-bold"
                                                        >Resi:</span
                                                    >
                                                    <span
                                                        class="text-emerald-700 font-bold"
                                                        >{trx.tracking_number}</span
                                                    >
                                                </div>
                                            {/if}
                                            {#if !trx.booking_code && !trx.tracking_number}
                                                <span
                                                    class="text-slate-400 italic text-[11px]"
                                                    >-</span
                                                >
                                            {/if}
                                        </td>
                                        <td
                                            class="py-5 px-6 text-xs text-slate-500"
                                        >
                                            {fmtDate(trx.created_at)}
                                        </td>
                                        <td class="py-5 px-6">
                                            <span
                                                class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider border {statusColors[
                                                    trx.status
                                                ] ??
                                                    'bg-slate-55 text-slate-600 border-slate-200/50'}"
                                            >
                                                {statusLabels[trx.status] ??
                                                    trx.status}
                                            </span>
                                        </td>
                                        <td
                                            class="py-5 px-6 font-bold text-slate-800 font-outfit text-right"
                                        >
                                            {fmt(trx.grand_total)}
                                        </td>
                                        <td class="py-5 px-6 text-center">
                                            <Link
                                                href="/admin/transactions/{trx.id}"
                                                class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-500 hover:text-slate-800 transition"
                                                title="Lihat Detail Transaksi"
                                            >
                                                <i class="ti ti-eye text-sm"
                                                ></i>
                                            </Link>
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                {/if}

                <Pagination paginator={transactions} />
            </div>
        </main>
    </div>
</AdminLayout>
