<script lang="ts">
    import AccountLayout from '@/components/layouts/AccountLayout.svelte';
    import { page, Link } from '@inertiajs/svelte';

    let {
        refunds,
        statusLabels = {} as Record<string, string>,
        storeName = '',
        storeLogo = '',
    } = $props();

    const primary = $derived(
        (page.props as any).theme?.primary_color ?? '#fa7315',
    );
    const secondary = $derived(
        (page.props as any).theme?.secondary_color ?? '#0c4cb4',
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

<svelte:head>
    <title>Pengajuan Refund | Akun Saya</title>
</svelte:head>

<AccountLayout activeMenu="refunds">
    <div
        class="bg-white rounded-2xl border border-slate-200/80 shadow-2xs p-6 space-y-6"
    >
        <div
            class="border-b border-slate-100 pb-4 flex items-center justify-between"
        >
            <div>
                <h1
                    class="text-xl font-black text-slate-900 font-outfit tracking-tight"
                >
                    Refund
                </h1>
                <p class="text-xs text-slate-500 font-medium mt-0.5">
                    Daftar riwayat pengajuan pembatalan transaksi dan refund
                    dana Anda.
                </p>
            </div>
            <Link
                href="/transactions"
                class="px-4 py-2 rounded-xl text-xs font-bold border border-slate-200 text-slate-700 hover:bg-slate-50 transition"
            >
                Kembali ke Pesanan
            </Link>
        </div>

        {#if !refunds || refunds.total === 0 || (refunds.data && refunds.data.length === 0)}
            <div class="py-16 text-center space-y-3">
                <div
                    class="w-16 h-16 rounded-full bg-slate-100 flex items-center justify-center mx-auto text-slate-300"
                >
                    <i class="ti ti-receipt-refund text-3xl"></i>
                </div>
                <p class="text-sm font-bold text-slate-700">
                    Belum Ada Pengajuan Refund
                </p>
                <p class="text-xs text-slate-400 max-w-sm mx-auto">
                    Anda tidak memiliki riwayat pengajuan refund atau
                    pengembalian produk saat ini.
                </p>
                <Link
                    href="/transactions"
                    class="inline-block px-5 py-2.5 rounded-xl font-bold text-xs text-white shadow-xs transition hover:opacity-90"
                    style="background-color: {primary};"
                >
                    Lihat Pesanan Saya
                </Link>
            </div>
        {:else}
            <div class="space-y-4">
                {#each refunds.data as refund (refund.id)}
                    {@const statusColor = refundStatusColors[refund.status] ?? {
                        bg: '#f1f5f9',
                        text: '#475569',
                    }}
                    <Link
                        href={`/refunds/${refund.id}`}
                        class="block bg-white rounded-xl border border-slate-200 hover:border-slate-300 p-4 transition shadow-2xs space-y-3"
                    >
                        <div
                            class="flex items-center justify-between border-b border-slate-100 pb-2"
                        >
                            <span class="text-xs font-bold text-slate-700">
                                Transaksi #{refund.transaction
                                    ?.transaction_number ||
                                    refund.transaction_id}
                            </span>
                            <span
                                class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                                style="background-color: {statusColor.bg}; color: {statusColor.text};"
                            >
                                {statusLabels[refund.status] || refund.status}
                            </span>
                        </div>

                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-500 font-medium"
                                >Nominal Refund:</span
                            >
                            <span
                                class="font-bold text-slate-800"
                                style="color: {primary};"
                            >
                                {fmt(refund.refund_amount || refund.amount)}
                            </span>
                        </div>

                        <div
                            class="text-[11px] text-slate-400 flex items-center justify-between"
                        >
                            <span>Diajukan: {fmtDate(refund.created_at)}</span>
                            <span
                                class="text-blue-600 font-bold flex items-center gap-1"
                            >
                                Detail Refund <i
                                    class="ti ti-chevron-right text-xs"
                                ></i>
                            </span>
                        </div>
                    </Link>
                {/each}
            </div>
        {/if}
    </div>
</AccountLayout>
