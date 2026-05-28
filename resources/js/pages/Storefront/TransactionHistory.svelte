<script lang="ts">
    import StorefrontLayout from '@/components/layouts/StorefrontLayout.svelte';
    import { page, Link } from '@inertiajs/svelte';

    let { transactions, statusLabels = {}, storeName = '', storeLogo = '' } = $props();

    const primary = $derived((page.props as any).theme?.primary_color ?? '#ee4d2d');

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
        if (!path) return '/images/placeholder.png';
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
</script>

<StorefrontLayout {storeName} {storeLogo} hideMobileFooter={true}>
    <div class="min-h-screen bg-slate-50">
        <!-- Header -->
        <div class="bg-white border-b border-slate-200 sticky top-0 z-30">
            <div class="max-w-3xl mx-auto px-4 h-14 flex items-center gap-3">
                <Link href="/" class="p-2 hover:bg-slate-100 rounded-full transition">
                    <i class="ti ti-arrow-left text-xl text-slate-700"></i>
                </Link>
                <h1 class="text-base font-bold text-slate-800">Riwayat Pesanan</h1>
            </div>
        </div>

        <div class="max-w-3xl mx-auto px-4 py-4 pb-12">
            {#if transactions.data.length === 0}
                <div class="flex flex-col items-center justify-center py-20 text-center">
                    <div class="w-20 h-20 rounded-full bg-slate-100 flex items-center justify-center mb-4">
                        <i class="ti ti-shopping-bag text-3xl text-slate-300"></i>
                    </div>
                    <p class="font-bold text-slate-700 text-lg mb-1">Belum ada pesanan</p>
                    <p class="text-sm text-slate-400 mb-6">Mulai belanja dan pesananmu akan muncul di sini.</p>
                    <Link
                        href="/"
                        class="px-6 py-3 rounded-xl font-bold text-white transition"
                        style="background:{primary}"
                    >Mulai Belanja</Link>
                </div>
            {:else}
                <div class="space-y-3">
                    {#each transactions.data as trx}
                        {@const statusStyle = statusColors[trx.status] ?? { bg: '#f1f5f9', text: '#475569' }}
                        <Link
                            href="/transactions/{trx.id}"
                            class="block bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden hover:shadow-md transition-shadow"
                        >
                            <!-- Transaction Header -->
                            <div class="px-4 pt-3 pb-2 flex items-center justify-between border-b border-slate-50">
                                <div>
                                    <p class="text-xs font-bold text-slate-800">{trx.transaction_number}</p>
                                    <p class="text-xs text-slate-400 mt-0.5">{fmtDate(trx.created_at)}</p>
                                </div>
                                <span
                                    class="text-[10px] font-black px-2.5 py-1 rounded-full"
                                    style="background:{statusStyle.bg}; color:{statusStyle.text}"
                                >
                                    {statusLabels[trx.status] ?? trx.status}
                                </span>
                            </div>

                            <!-- Items Preview -->
                            <div class="px-4 py-2">
                                {#each (trx.items ?? []).slice(0, 2) as item}
                                    <div class="flex items-center gap-2 py-1">
                                        {#if item.product_image}
                                            <img
                                                src={formatImagePath(item.product_image)}
                                                alt={item.product_name}
                                                class="w-10 h-10 object-cover rounded-lg shrink-0 border border-slate-100"
                                                onerror={(e: any) => { e.target.src = '/images/placeholder.png'; }}
                                            />
                                        {:else}
                                            <div class="w-10 h-10 rounded-lg bg-slate-100 shrink-0 flex items-center justify-center">
                                                <i class="ti ti-package text-slate-300 text-sm"></i>
                                            </div>
                                        {/if}
                                        <div class="flex-1 min-w-0">
                                            <p class="text-xs font-semibold text-slate-800 truncate">{item.product_name}</p>
                                            {#if item.variant_name}
                                                <p class="text-[10px] text-slate-400">{item.variant_name}</p>
                                            {/if}
                                        </div>
                                        <span class="text-xs text-slate-500 shrink-0">x{item.quantity}</span>
                                    </div>
                                {/each}
                                {#if (trx.items ?? []).length > 2}
                                    <p class="text-xs text-slate-400 mt-1">+{(trx.items ?? []).length - 2} produk lainnya</p>
                                {/if}
                            </div>

                            <!-- Footer -->
                            <div class="px-4 py-2.5 border-t border-slate-50 flex items-center justify-between">
                                <div>
                                    <span class="text-xs text-slate-500">Total Pembayaran</span>
                                    <p class="text-sm font-black" style="color:{primary}">{fmt(trx.grand_total)}</p>
                                </div>
                                <div class="flex items-center gap-1 text-xs text-slate-500">
                                    <span>Lihat Detail</span>
                                    <i class="ti ti-chevron-right text-sm"></i>
                                </div>
                            </div>
                        </Link>
                    {/each}
                </div>

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
                        <span class="text-sm text-slate-500">
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
            {/if}
        </div>
    </div>
</StorefrontLayout>
