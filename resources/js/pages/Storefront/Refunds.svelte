<script lang="ts">
    import StorefrontLayout from '@/components/layouts/StorefrontLayout.svelte';
    import { page, Link } from '@inertiajs/svelte';

    let {
        refunds,
        statusLabels = {} as Record<string, string>,
        storeName = '',
        storeLogo = '',
    } = $props();

    const primary = $derived(
        (page.props as any).theme?.primary_color ?? '#ee4d2d',
    );
    const secondary = $derived(
        (page.props as any).theme?.secondary_color ?? '#fa7315',
    );

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
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    const refundStatusColors: Record<string, { bg: string; text: string }> = {
        menunggu_konfirmasi: { bg: '#fef3c7', text: '#92400e' },
        disetujui: { bg: '#dbeafe', text: '#1e40af' },
        ditolak: { bg: '#fee2e2', text: '#991b1b' },
        selesai: { bg: '#dcfce7', text: '#166534' },
    };
</script>

<StorefrontLayout {storeName} {storeLogo} hideMobileFooter={true}>
    <div class="min-h-dvh bg-slate-50">
        <!-- Header -->
        <div class="bg-white border-b border-slate-200 sticky top-0 z-30">
            <div class="max-w-6xl mx-auto px-4 h-14 flex items-center gap-3">
                <Link
                    href="/transactions"
                    class="p-2 hover:bg-slate-100 rounded-full transition"
                >
                    <i class="ti ti-arrow-left text-xl text-slate-700"></i>
                </Link>
                <h1 class="text-base font-bold text-slate-800">Refund Saya</h1>
            </div>
        </div>

        <div class="max-w-6xl mx-auto px-4 py-6 pb-12">
            {#if refunds.total === 0}
                <div
                    class="w-full max-w-6xl mx-auto bg-white rounded-3xl border border-slate-100 shadow-sm p-8 py-20 flex flex-col items-center justify-center text-center"
                >
                    <div
                        class="w-20 h-20 rounded-full flex items-center justify-center mb-4"
                        style="background-color: {secondary}10"
                    >
                        <i class="ti ti-receipt-refund text-3xl text-red-500"
                        ></i>
                    </div>
                    <p class="font-bold text-slate-700 text-lg mb-1">
                        Belum ada pengajuan refund
                    </p>
                    <p
                        class="text-sm text-slate-400 mb-6 font-medium leading-relaxed"
                    >
                        Anda tidak memiliki pengajuan pembatalan atau refund
                        dana aktif saat ini.
                    </p>
                    <Link
                        href="/transactions"
                        class="px-6 py-3 rounded-xl font-bold text-white transition active:scale-95 hover:opacity-95 shadow-md"
                        style="background:{primary}"
                    >
                        Lihat Pesanan Saya
                    </Link>
                </div>
            {:else}
                <div class="max-w-6xl mx-auto space-y-4">
                    {#each refunds.data as refund (refund.id)}
                        {@const statusColor = refundStatusColors[
                            refund.status
                        ] ?? { bg: '#f1f5f9', text: '#475569' }}
                        <Link
                            href={`/refunds/${refund.id}`}
                            class="w-full text-left block bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden hover:shadow-md transition duration-200 cursor-pointer"
                        >
                            <!-- Refund Request Header -->
                            <div
                                class="px-4 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/30"
                            >
                                <div class="min-w-0">
                                    <div
                                        class="flex items-center gap-2.5 flex-wrap"
                                    >
                                        <p
                                            class="text-sm font-black text-slate-800 tracking-tight whitespace-nowrap"
                                        >
                                            #{refund.refund_number}
                                        </p>
                                        <span
                                            class="text-[10px] font-black px-2 py-0.5 rounded bg-slate-100 text-slate-500 uppercase tracking-wider whitespace-nowrap"
                                        >
                                            {refund.refund_method === 'poin'
                                                ? 'Koin Toko'
                                                : 'Transfer Bank'}
                                        </span>
                                    </div>
                                    <p
                                        class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-1"
                                    >
                                        Diajukan: {fmtDate(refund.created_at)}
                                    </p>
                                </div>
                                <div class="shrink-0 flex items-center">
                                    <span
                                        class="text-[10px] font-black px-3 py-1 rounded-full uppercase tracking-wider border whitespace-nowrap"
                                        style="background:{statusColor.bg}; color:{statusColor.text}; border-color:{statusColor.text}15;"
                                    >
                                        {statusLabels[refund.status] ??
                                            refund.status}
                                    </span>
                                </div>
                            </div>

                            <!-- Refund Details -->
                            <div class="px-4 py-4 space-y-2">
                                <div
                                    class="flex justify-between items-center text-xs"
                                >
                                    <span class="text-slate-400 font-semibold"
                                        >No. Transaksi:</span
                                    >
                                    <span class="text-slate-700 font-bold"
                                        >#{refund.transaction
                                            ?.transaction_number}</span
                                    >
                                </div>
                                <div
                                    class="flex justify-between items-start text-xs gap-4"
                                >
                                    <span
                                        class="text-slate-400 font-semibold shrink-0"
                                        >Alasan Pembatalan:</span
                                    >
                                    <span
                                        class="text-slate-650 font-semibold truncate max-w-xs"
                                        >{refund.reason}</span
                                    >
                                </div>
                                {#if refund.refund_method === 'transfer' && refund.bank_name}
                                    <div
                                        class="flex justify-between items-center text-xs"
                                    >
                                        <span
                                            class="text-slate-400 font-semibold"
                                            >Tujuan Transfer:</span
                                        >
                                        <span class="text-slate-700 font-bold"
                                            >{refund.bank_name} - {refund.account_number}</span
                                        >
                                    </div>
                                {/if}
                            </div>

                            <!-- Footer Summary -->
                            <div
                                class="px-4 py-3 border-t border-slate-50 bg-slate-50/20 flex items-center justify-between"
                            >
                                <div>
                                    <span
                                        class="text-[10px] text-slate-400 font-bold uppercase tracking-wider"
                                        >Nominal Refund</span
                                    >
                                    <p
                                        class="text-sm font-black mt-0.5"
                                        style="color:{primary}"
                                    >
                                        {fmt(refund.refund_amount)}
                                    </p>
                                </div>
                                <div
                                    class="flex items-center gap-1.5 text-xs font-bold text-slate-500 bg-white border border-slate-200 px-3 py-1.5 rounded-xl hover:bg-slate-50 hover:text-slate-700 hover:shadow-3xs transition"
                                >
                                    <span>Detail Pengajuan</span>
                                    <i class="ti ti-chevron-right text-xs"></i>
                                </div>
                            </div>
                        </Link>
                    {/each}

                    <!-- Pagination -->
                    {#if refunds.last_page > 1}
                        <div
                            class="flex justify-center items-center gap-2 pt-6"
                        >
                            {#each refunds.links as link}
                                {#if link.url}
                                    <Link
                                        href={link.url}
                                        class="px-3.5 py-2 rounded-xl text-xs font-bold border transition
                                               {link.active
                                            ? 'text-white border-transparent'
                                            : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'}"
                                        style={link.active
                                            ? `background-color: ${primary}`
                                            : ''}
                                    >
                                        {@html link.label}
                                    </Link>
                                {:else}
                                    <span
                                        class="px-3.5 py-2 text-xs font-bold text-slate-400 border border-slate-100 bg-slate-50/50 rounded-xl cursor-default select-none"
                                    >
                                        {@html link.label}
                                    </span>
                                {/if}
                            {/each}
                        </div>
                    {/if}
                </div>
            {/if}
        </div>
    </div>
</StorefrontLayout>
