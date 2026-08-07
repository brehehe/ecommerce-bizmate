<script lang="ts">
    import AccountLayout from '@/components/layouts/AccountLayout.svelte';
    import { page, router, Link } from '@inertiajs/svelte';

    let {
        transactions,
        statusLabels = {},
        storeName = '',
        storeLogo = '',
        currentStatus = 'all',
        statusCounts = {
            all: 0,
            belum_bayar: 0,
            berjalan: 0,
            selesai: 0,
            batal: 0,
        },
    } = $props();

    const primary = $derived(
        (page.props as any).theme?.primary_color ?? '#fa7315',
    );
    const secondary = $derived(
        (page.props as any).theme?.secondary_color ?? '#0c4cb4',
    );

    let searchQuery = $state('');

    function fmt(price: any): string {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
        }).format(Number(price) || 0);
    }

    function fmtDate(dateStr: string): string {
        if (!dateStr) return '-';
        return new Date(dateStr).toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        });
    }

    function formatImagePath(path: string | null | undefined): string {
        if (!path) return '/noimage/image.png';
        if (
            path.startsWith('http://') ||
            path.startsWith('https://') ||
            path.startsWith('/')
        ) {
            return path;
        }
        return '/storage/' + path;
    }

    const statusColors: Record<string, { bg: string; text: string; label: string }> = {
        belum_bayar: { bg: '#fef3c7', text: '#92400e', label: 'Belum Bayar' },
        menunggu: { bg: '#dbeafe', text: '#1e40af', label: 'Menunggu Pembayaran' },
        diproses: { bg: '#ede9fe', text: '#5b21b6', label: 'Diproses' },
        dikemas: { bg: '#cffafe', text: '#0e7490', label: 'Sedang Dikemas' },
        dikirim: { bg: '#ffedd5', text: '#9a3412', label: 'Sedang Dikirim' },
        selesai: { bg: '#dcfce7', text: '#166534', label: 'Pesanan Selesai' },
        batal: { bg: '#fee2e2', text: '#991b1b', label: 'Dibatalkan' },
        retur: { bg: '#f3e8ff', text: '#6b21a8', label: 'Pengembalian' },
    };

    const selectedStatus = $derived(currentStatus);

    function switchTab(statusKey: string) {
        router.get(
            '/transactions',
            { status: statusKey, search: searchQuery },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    const filteredTransactions = $derived.by(() => {
        let list = transactions?.data || transactions || [];
        if (!searchQuery.trim()) return list;
        const q = searchQuery.toLowerCase();
        return list.filter((trx: any) => {
            const num = (trx.transaction_number || '').toLowerCase();
            const store = (trx.store_name || trx.seller?.store_name || '').toLowerCase();
            const hasProduct = (trx.items || []).some((item: any) =>
                (item.product_name || item.product?.name || '').toLowerCase().includes(q)
            );
            return num.includes(q) || store.includes(q) || hasProduct;
        });
    });
</script>

<svelte:head>
    <title>Pesanan Saya | Riwayat Transaksi</title>
</svelte:head>

<AccountLayout activeMenu="transactions">
    <div class="space-y-4">
        <!-- Main Card Header -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-2xs p-4 sm:p-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-4 mb-4">
                <div>
                    <h1 class="text-lg font-bold text-slate-800 font-outfit">
                        Daftar Transaksi
                    </h1>
                    <p class="text-xs text-slate-400 font-medium mt-0.5">
                        Kelola dan pantau seluruh status pesanan belanja Anda
                    </p>
                </div>

                <!-- Search Input -->
                <div class="relative w-full sm:w-72">
                    <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                    <input
                        type="text"
                        bind:value={searchQuery}
                        placeholder="Cari transaksi / produk..."
                        class="w-full pl-9 pr-4 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:bg-white focus:ring-2 focus:ring-slate-300 transition"
                    />
                </div>
            </div>

            <!-- Status Tabs (Shopee / Tokopedia Style) -->
            <div class="overflow-x-auto scrollbar-none">
                <div class="flex items-center gap-1 border-b border-slate-100 pb-1 min-w-max">
                    {#each [
                        { key: 'all', label: 'Semua', count: statusCounts.all },
                        { key: 'belum_bayar', label: 'Belum Bayar', count: statusCounts.belum_bayar },
                        { key: 'berjalan', label: 'Sedang Dikemas / Dikirim', count: statusCounts.berjalan },
                        { key: 'selesai', label: 'Selesai', count: statusCounts.selesai },
                        { key: 'batal', label: 'Dibatalkan', count: statusCounts.batal },
                        { key: 'refund', label: 'Refund Dana', count: statusCounts.refund || 0 },
                        { key: 'retur', label: 'Retur Barang', count: statusCounts.retur || 0 }
                    ] as tab}
                        {@const isActive = selectedStatus === tab.key}
                        <button
                            onclick={() => switchTab(tab.key)}
                            class="px-4 py-2 text-xs font-bold transition-all duration-200 rounded-xl border-b-2 whitespace-nowrap flex items-center gap-2 cursor-pointer
                                   {isActive
                                ? 'border-amber-500 text-amber-600 bg-amber-50/50'
                                : 'border-transparent text-slate-500 hover:text-slate-800 hover:bg-slate-50'}"
                            style={isActive ? `border-color: ${primary}; color: ${primary};` : ''}
                        >
                            <span>{tab.label}</span>
                            {#if tab.count > 0}
                                <span
                                    class="px-1.5 py-0.5 rounded-full text-[10px] font-extrabold leading-none
                                           {isActive ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-500'}"
                                >
                                    {tab.count}
                                </span>
                            {/if}
                        </button>
                    {/each}
                </div>
            </div>
        </div>

        <!-- Transactions Listing -->
        {#if filteredTransactions.length === 0}
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-2xs p-12 text-center text-slate-400 space-y-3">
                <div class="w-16 h-16 rounded-full bg-slate-100 flex items-center justify-center mx-auto text-slate-300">
                    <i class="ti ti-receipt-off text-3xl"></i>
                </div>
                <p class="text-sm font-bold text-slate-700">Tidak ada transaksi ditemukan</p>
                <p class="text-xs text-slate-400">Belum ada pesanan dengan status ini atau kata kunci tidak sesuai.</p>
                <Link
                    href="/"
                    class="inline-block px-5 py-2 rounded-xl text-xs font-bold text-white shadow-md transition hover:opacity-90 mt-2"
                    style="background-color: {primary};"
                >
                    Mulai Belanja Sekarang
                </Link>
            </div>
        {:else}
            <div class="space-y-4">
                {#each filteredTransactions as trx (trx.id)}
                    {@const statusInfo = statusColors[trx.status] || { bg: '#f1f5f9', text: '#475569', label: trx.status }}
                    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-2xs overflow-hidden transition hover:border-slate-300">

                        <!-- Store Header & Status Bar -->
                        <div class="px-4 py-3 bg-slate-50/50 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-2 text-xs font-bold text-slate-800">
                                <i class="ti ti-building-store text-base text-amber-600"></i>
                                <span>{trx.store_name || trx.seller?.store_name || 'Toko Resmi'}</span>

                                {#if trx.seller?.store_slug}
                                    <Link
                                        href="/{trx.seller.store_slug}"
                                        class="px-2 py-0.5 rounded-lg text-[10px] font-bold text-blue-600 bg-blue-50 hover:bg-blue-100 transition"
                                    >
                                        Kunjungi Toko
                                    </Link>
                                {/if}
                            </div>

                            <div class="flex items-center gap-2">
                                <span
                                    class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider"
                                    style="background-color: {statusInfo.bg}; color: {statusInfo.text};"
                                >
                                    {statusLabels[trx.status] || statusInfo.label}
                                </span>
                            </div>
                        </div>

                        <!-- Products List -->
                        <Link href="/transactions/{trx.id}" class="block p-4 space-y-3 hover:bg-slate-50/30 transition">
                            {#each (trx.items || [trx]) as item}
                                <div class="flex items-start gap-4">
                                    <img
                                        src={formatImagePath(item.product_image || item.image || item.product?.image)}
                                        alt={item.product_name || item.name || 'Produk'}
                                        class="w-16 h-16 rounded-xl object-cover border border-slate-200 shrink-0"
                                    />
                                    <div class="flex-1 min-w-0">
                                        <h3 class="text-xs font-bold text-slate-800 line-clamp-2 leading-snug">
                                            {item.product_name || item.name || item.product?.name || 'Produk E-Commerce'}
                                        </h3>
                                        {#if item.variant_name || item.variant}
                                            <p class="text-[11px] text-slate-400 font-medium mt-0.5">
                                                Varian: {item.variant_name || item.variant}
                                            </p>
                                        {/if}
                                        <p class="text-[11px] text-slate-500 font-semibold mt-1">
                                            x{item.quantity || 1}
                                        </p>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <span class="text-xs font-bold text-slate-800">
                                            {fmt(item.price || trx.grand_total)}
                                        </span>
                                    </div>
                                </div>
                            {/each}
                        </Link>

                        <!-- Order Footer Info & Actions -->
                        <div class="px-4 py-3 bg-slate-50/30 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="text-xs text-slate-400">
                                <span class="font-medium">No. Transaksi: </span>
                                <span class="font-bold text-slate-700">{trx.transaction_number}</span>
                                <span class="mx-1">•</span>
                                <span>{fmtDate(trx.created_at)}</span>
                            </div>

                            <div class="flex items-center gap-4 justify-between sm:justify-end">
                                <div class="text-right">
                                    <span class="text-[11px] text-slate-400 block">Total Pesanan:</span>
                                    <span class="text-sm font-extrabold text-slate-900" style="color: {primary};">
                                        {fmt(trx.grand_total || trx.total_amount)}
                                    </span>
                                </div>

                                <div class="flex items-center gap-2">
                                    <Link
                                        href="/transactions/{trx.id}"
                                        class="px-4 py-2 rounded-xl text-xs font-bold border border-slate-200 text-slate-700 bg-white hover:bg-slate-50 transition shadow-2xs"
                                    >
                                        Detail Transaksi
                                    </Link>

                                    {#if trx.status === 'belum_bayar' || trx.status === 'menunggu'}
                                        <Link
                                            href="/transactions/{trx.id}"
                                            class="px-4 py-2 rounded-xl text-xs font-bold text-white shadow-xs transition hover:opacity-90"
                                            style="background-color: {primary};"
                                        >
                                            Bayar Sekarang
                                        </Link>
                                    {:else if trx.status === 'selesai'}
                                        <Link
                                            href="/transactions/{trx.id}"
                                            class="px-4 py-2 rounded-xl text-xs font-bold text-white shadow-xs transition hover:opacity-90"
                                            style="background-color: {primary};"
                                        >
                                            Beli Lagi
                                        </Link>
                                    {/if}
                                </div>
                            </div>
                        </div>

                    </div>
                {/each}
            </div>
        {/if}
    </div>
</AccountLayout>
