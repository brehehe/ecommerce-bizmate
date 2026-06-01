<script lang="ts">
    import StorefrontLayout from '@/components/layouts/StorefrontLayout.svelte';
    import { page, router, Link } from '@inertiajs/svelte';

    let {
        transactions,
        statusLabels = {},
        storeName = '',
        storeLogo = '',
        currentStatus = 'all',
        statusCounts = { all: 0, belum_bayar: 0, berjalan: 0, selesai: 0, batal: 0 }
    } = $props();

    const primary = $derived((page.props as any).theme?.primary_color ?? '#ee4d2d');
    const secondary = $derived((page.props as any).theme?.secondary_color ?? '#fa7315');

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
        if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/')) {
            return path;
        }
        return '/storage/' + path;
    }

    const statusColors: Record<string, { bg: string; text: string }> = {
        belum_bayar: { bg: '#fef3c7', text: '#92400e' },
        menunggu: { bg: '#dbeafe', text: '#1e40af' },
        diproses: { bg: '#ede9fe', text: '#5b21b6' },
        dikemas: { bg: '#cffafe', text: '#0e7490' },
        dikirim: { bg: '#ffedd5', text: '#9a3412' },
        selesai: { bg: '#dcfce7', text: '#166534' },
        batal: { bg: '#fee2e2', text: '#991b1b' },
    };

    const selectedStatus = $derived(currentStatus);

    function switchTab(statusKey: string) {
        router.get(
            '/transactions',
            { status: statusKey },
            { preserveState: true, preserveScroll: true, replace: true }
        );
    }

    const filteredTransactions = $derived(transactions.data);
</script>

<style>
    .scrollbar-none::-webkit-scrollbar {
        display: none;
    }
    .scrollbar-none {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
</style>

<StorefrontLayout {storeName} {storeLogo} hideMobileFooter={true}>
    <div class="min-h-dvh bg-slate-50">
        <!-- Header -->
        <div class="bg-white border-b border-slate-200 sticky top-0 z-30">
            <div class="max-w-6xl mx-auto px-4 h-14 flex items-center gap-3">
                <Link href="/" class="p-2 hover:bg-slate-100 rounded-full transition">
                    <i class="ti ti-arrow-left text-xl text-slate-700"></i>
                </Link>
                <h1 class="text-base font-bold text-slate-800">Riwayat Pesanan</h1>
            </div>
        </div>

        <div class="max-w-6xl mx-auto px-4 py-6 pb-12">
            {#if statusCounts.all === 0}
                <div class="w-full max-w-6xl mx-auto bg-white rounded-3xl border border-slate-100 shadow-sm p-8 py-20 flex flex-col items-center justify-center text-center">
                    <div class="w-20 h-20 rounded-full bg-slate-100 flex items-center justify-center mb-4">
                        <i class="ti ti-shopping-bag text-3xl text-slate-300"></i>
                    </div>
                    <p class="font-bold text-slate-700 text-lg mb-1">Belum ada pesanan</p>
                    <p class="text-sm text-slate-400 mb-6 font-medium">Mulai belanja dan pesananmu akan muncul di sini.</p>
                    <Link
                        href="/"
                        class="px-6 py-3 rounded-xl font-bold text-white transition active:scale-95 hover:opacity-95 shadow-md shadow-brand-blueRoyal/15"
                        style="background:{primary}"
                    >Mulai Belanja</Link>
                </div>
            {:else}
                <div class="max-w-6xl mx-auto space-y-4">
                    <!-- Right Main Content -->
                    <div class="space-y-4">
                        <!-- Navigation Status Tabs -->
                        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-1.5 overflow-x-auto scrollbar-none flex gap-1.5 sticky top-[56px] lg:top-auto z-20">
                            {#each [
                                { key: 'all', label: 'Semua', count: statusCounts.all },
                                { key: 'belum_bayar', label: 'Belum Bayar', count: statusCounts.belum_bayar },
                                { key: 'berjalan', label: 'Dalam Proses', count: statusCounts.berjalan },
                                { key: 'selesai', label: 'Selesai', count: statusCounts.selesai },
                                { key: 'batal', label: 'Dibatalkan', count: statusCounts.batal }
                            ] as tab}
                                {@const isActive = selectedStatus === tab.key}
                                <button
                                    onclick={() => switchTab(tab.key)}
                                    class="px-4 py-2.5 rounded-xl text-xs font-bold transition whitespace-nowrap flex items-center gap-1.5 cursor-pointer flex-grow text-center justify-center {isActive ? 'text-white shadow-sm' : 'text-slate-500 hover:text-slate-700 hover:bg-slate-50'}"
                                    style={isActive ? `background-color:${primary}; box-shadow: 0 4px 10px -2px ${primary}30;` : ''}
                                >
                                    {tab.label}
                                    {#if tab.count > 0}
                                        <span class="text-[10px] px-1.5 py-0.5 rounded-full font-black leading-none {isActive ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500'}">
                                            {tab.count}
                                        </span>
                                    {/if}
                                </button>
                            {/each}
                        </div>

                        <!-- Transactions Listing -->
                        {#if filteredTransactions.length === 0}
                            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-12 text-center text-slate-400">
                                <i class="ti ti-clipboard-text text-4xl text-slate-200 mb-2 block"></i>
                                <p class="text-xs font-bold">Tidak ada transaksi dengan status ini</p>
                            </div>
                        {:else}
                            <div class="space-y-4">
                                {#each filteredTransactions as trx (trx.id)}
                                    {@const statusStyle = statusColors[trx.status] ?? { bg: '#f1f5f9', text: '#475569' }}
                                    <Link
                                        href="/transactions/{trx.id}"
                                        class="block bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden hover:shadow-md transition duration-200"
                                    >
                                        <!-- Transaction Header -->
                                        <div class="px-4 py-3 flex items-center justify-between border-b border-slate-50 bg-slate-50/20">
                                            <div>
                                                <p class="text-xs font-bold text-slate-800">{trx.transaction_number}</p>
                                                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mt-0.5">{fmtDate(trx.created_at)}</p>
                                            </div>
                                            <span
                                                class="text-[10px] font-black px-2.5 py-1 rounded-full uppercase tracking-wider border"
                                                style="background:{statusStyle.bg}; color:{statusStyle.text}; border-color:{statusStyle.text}15;"
                                            >
                                                {statusLabels[trx.status] ?? trx.status}
                                            </span>
                                        </div>

                                        <!-- Items Preview -->
                                        <div class="px-4 py-3 divide-y divide-slate-50">
                                            {#each (trx.items ?? []).slice(0, 2) as item}
                                                <button
                                                    onclick={(e) => {
                                                        e.preventDefault();
                                                        e.stopPropagation();
                                                        if (item.product?.slug) {
                                                            router.get(`/products/${item.product.slug}`);
                                                        } else {
                                                            router.get(`/transactions/${trx.id}`);
                                                        }
                                                    }}
                                                    class="w-full flex items-center gap-3 py-2 text-left hover:bg-slate-50/70 rounded-xl px-2 -mx-2 transition cursor-pointer group"
                                                >
                                                    {#if item.product_image}
                                                        <img
                                                            src={formatImagePath(item.product_image)}
                                                            alt={item.product_name}
                                                            class="w-12 h-12 object-cover rounded-xl shrink-0 border border-slate-100 group-hover:opacity-90 transition"
                                                            onerror={(e: any) => { e.target.src = '/noimage/image.png'; }}
                                                        />
                                                    {:else}
                                                        <div class="w-12 h-12 rounded-xl bg-slate-50 shrink-0 flex items-center justify-center border border-slate-100">
                                                            <i class="ti ti-package text-slate-300 text-lg"></i>
                                                        </div>
                                                    {/if}
                                                    <div class="flex-1 min-w-0">
                                                        <p class="text-xs font-bold text-slate-800 whitespace-pre-wrap break-words leading-tight group-hover:text-brand-blueRoyal transition">{item.product_name}</p>
                                                        {#if item.variant_name}
                                                            <span class="inline-block text-[9px] font-bold px-1.5 py-0.5 bg-slate-50 border border-slate-100 rounded text-slate-500 mt-1">{item.variant_name}</span>
                                                        {/if}
                                                    </div>
                                                    <div class="text-right shrink-0">
                                                        <p class="text-xs text-slate-400 font-bold">x{item.quantity}</p>
                                                        <p class="text-xs font-bold text-slate-700 mt-0.5">{fmt(item.harga_akhir ?? item.harga_jual)}</p>
                                                    </div>
                                                </button>
                                            {/each}
                                            {#if (trx.items ?? []).length > 2}
                                                <p class="text-xs text-slate-400 font-bold mt-2 pt-2 border-t border-slate-50">+{(trx.items ?? []).length - 2} produk lainnya</p>
                                            {/if}
                                        </div>

                                        <!-- Footer -->
                                        <div class="px-4 py-3 border-t border-slate-50 bg-slate-50/20 flex items-center justify-between">
                                            <div>
                                                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Total Pembayaran</span>
                                                <p class="text-sm font-black mt-0.5" style="color:{primary}">{fmt(trx.grand_total)}</p>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <button
                                                    onclick={(e) => {
                                                        e.preventDefault();
                                                        e.stopPropagation();
                                                        window.open(`/transactions/${trx.id}/print-invoice?download=1`, '_blank');
                                                    }}
                                                    class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 bg-white border border-slate-200 px-3 py-1.5 rounded-xl hover:bg-slate-100 transition"
                                                    title="Cetak Invoice"
                                                >
                                                    <i class="ti ti-printer text-base"></i>
                                                    <span class="hidden sm:inline">Cetak Invoice</span>
                                                </button>
                                                <div class="flex items-center gap-1 text-xs font-bold text-slate-500 bg-white border border-slate-200 px-3 py-1.5 rounded-xl hover:bg-slate-50 hover:text-slate-700 hover:shadow-3xs transition">
                                                    <span>Lihat Detail</span>
                                                    <i class="ti ti-chevron-right text-xs"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </Link>
                                {/each}
                            </div>
                        {/if}

                        <!-- Pagination -->
                        {#if transactions.last_page > 1}
                            <div class="flex items-center justify-center gap-2 mt-6">
                                {#if transactions.prev_page_url}
                                    <Link
                                        href={transactions.prev_page_url}
                                        class="px-4 py-2 rounded-xl border-2 border-slate-200 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition"
                                    >
                                        <i class="ti ti-chevron-left"></i>
                                    </Link>
                                {/if}
                                <span class="text-sm font-bold text-slate-500">
                                    Halaman {transactions.current_page} dari {transactions.last_page}
                                </span>
                                {#if transactions.next_page_url}
                                    <Link
                                        href={transactions.next_page_url}
                                        class="px-4 py-2 rounded-xl border-2 border-slate-200 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition"
                                    >
                                        <i class="ti ti-chevron-right"></i>
                                    </Link>
                                {/if}
                            </div>
                        {/if}
                    </div>
                </div>
            {/if}
        </div>
    </div>
</StorefrontLayout>
