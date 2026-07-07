<script lang="ts">
    import StorefrontLayout from '@/components/layouts/StorefrontLayout.svelte';
    import { page, Link } from '@inertiajs/svelte';

    let {
        refund,
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

    // Calculate current step index for timeline
    // Timeline steps for transfer: 1. Diajukan (menunggu_konfirmasi), 2. Disetujui (disetujui), 3. Selesai (selesai)
    // Timeline steps for points: 1. Diajukan (menunggu_konfirmasi), 2. Selesai (selesai)
    const timelineSteps = $derived(
        refund.refund_method === 'transfer'
            ? [
                  {
                      label: 'Diajukan',
                      status: 'menunggu_konfirmasi',
                      icon: 'ti-file-text',
                  },
                  { label: 'Disetujui', status: 'disetujui', icon: 'ti-clock' },
                  {
                      label: 'Selesai',
                      status: 'selesai',
                      icon: 'ti-circle-check-filled',
                  },
              ]
            : [
                  {
                      label: 'Diajukan',
                      status: 'menunggu_konfirmasi',
                      icon: 'ti-file-text',
                  },
                  {
                      label: 'Selesai',
                      status: 'selesai',
                      icon: 'ti-circle-check-filled',
                  },
              ],
    );

    const currentStatusIndex = $derived(
        timelineSteps.findIndex((step) => step.status === refund.status),
    );

    // If it is 'selesai', then all steps are completed (e.g. index = last)
    // If it's disetujui, index = 1 for transfer.
    const activeStepIndex = $derived(
        refund.status === 'selesai'
            ? timelineSteps.length - 1
            : refund.status === 'ditolak'
              ? -1
              : currentStatusIndex,
    );
</script>

<StorefrontLayout {storeName} {storeLogo} hideMobileFooter={true}>
    <div class="min-h-dvh bg-slate-50">
        <!-- Header -->
        <div class="bg-white border-b border-slate-200 sticky top-0 z-30">
            <div class="max-w-3xl mx-auto px-4 h-14 flex items-center gap-3">
                <Link
                    href="/refunds"
                    class="p-2 hover:bg-slate-100 rounded-full transition"
                >
                    <i class="ti ti-arrow-left text-xl text-slate-700"></i>
                </Link>
                <h1 class="text-base font-bold text-slate-800">
                    Detail Refund
                </h1>
            </div>
        </div>

        <div class="max-w-3xl mx-auto px-4 py-6 pb-12 space-y-6">
            <!-- Refund Request Overview Card -->
            <div
                class="bg-white border border-slate-100 rounded-3xl p-6 shadow-sm space-y-4"
            >
                <div
                    class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-100"
                >
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3
                                class="font-outfit font-black text-slate-800 text-lg"
                            >
                                #{refund.refund_number}
                            </h3>
                            <span
                                class="text-[10px] font-black px-2 py-0.5 rounded bg-slate-100 text-slate-500 uppercase tracking-wider"
                            >
                                {refund.refund_method === 'poin'
                                    ? 'Koin Toko'
                                    : 'Transfer Bank'}
                            </span>
                        </div>
                        <p class="text-xs text-slate-400 font-bold mt-1">
                            Diajukan pada {fmtDate(refund.created_at)}
                        </p>
                    </div>
                    <div class="shrink-0">
                        <span
                            class="text-[11px] font-black px-4 py-1.5 rounded-full uppercase tracking-wider border whitespace-nowrap"
                            style="background:{refundStatusColors[refund.status]
                                ?.bg ?? '#f1f5f9'}; color:{refundStatusColors[
                                refund.status
                            ]?.text ??
                                '#475569'}; border-color:{refundStatusColors[
                                refund.status
                            ]?.text ?? '#475569'}15;"
                        >
                            {statusLabels[refund.status] ?? refund.status}
                        </span>
                    </div>
                </div>

                <!-- Timeline / Status Banner -->
                {#if refund.status === 'ditolak'}
                    <div
                        class="bg-rose-50 border border-rose-100 rounded-2xl p-4 flex gap-3 text-rose-800"
                    >
                        <i class="ti ti-alert-triangle text-xl shrink-0"></i>
                        <div>
                            <p class="font-bold text-sm">
                                Pengajuan Refund Ditolak
                            </p>
                            <p
                                class="text-xs mt-1 text-rose-600 leading-relaxed font-semibold"
                            >
                                Catatan Admin: {refund.notes_admin ||
                                    'Tidak ada alasan spesifik.'}
                            </p>
                        </div>
                    </div>
                {:else}
                    <div
                        class="bg-slate-50 rounded-2xl p-6 border border-slate-150/40"
                    >
                        <h4
                            class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4 text-center"
                        >
                            Status Pengajuan
                        </h4>

                        <div class="relative py-4">
                            <!-- Connecting Line -->
                            {#if timelineSteps.length > 1}
                                <div
                                    class="absolute top-[28px] left-[15%] right-[15%] h-0.5 bg-slate-200 z-0"
                                >
                                    <div
                                        class="h-full bg-emerald-500 transition duration-300"
                                        style="width: {timelineSteps.length > 2
                                            ? activeStepIndex >= 0
                                                ? activeStepIndex * 50
                                                : 0
                                            : activeStepIndex === 1
                                              ? 100
                                              : 0}%"
                                    ></div>
                                </div>
                            {/if}

                            <!-- Timeline Steps -->
                            <div
                                class="grid {timelineSteps.length === 3
                                    ? 'grid-cols-3'
                                    : 'grid-cols-2'} gap-1 text-center text-[10px] font-bold text-slate-400 select-none relative z-10"
                            >
                                {#each timelineSteps as step, index}
                                    {@const isDone = activeStepIndex >= index}
                                    {@const isCurrent =
                                        activeStepIndex === index}
                                    <div
                                        class="flex flex-col items-center gap-1.5"
                                    >
                                        <div
                                            class="w-8 h-8 rounded-full flex items-center justify-center border-2 transition bg-white"
                                            style="border-color: {isDone
                                                ? '#10b981'
                                                : '#e2e8f0'}; color: {isDone
                                                ? '#10b981'
                                                : '#94a3b8'}; box-shadow: {isCurrent
                                                ? '0 0 0 4px #10b98125'
                                                : 'none'}"
                                        >
                                            <i class="ti {step.icon} text-base"
                                            ></i>
                                        </div>
                                        <span
                                            class="leading-tight {isDone
                                                ? 'text-slate-800 font-extrabold'
                                                : 'text-slate-400'}"
                                            >{step.label}</span
                                        >
                                    </div>
                                {/each}
                            </div>
                        </div>

                        <!-- Status descriptions -->
                        <div
                            class="mt-4 pt-3 border-t border-slate-200/50 text-center text-xs text-slate-500 font-medium"
                        >
                            {#if refund.status === 'menunggu_konfirmasi'}
                                Pengajuan pembatalan sedang menunggu persetujuan
                                dan konfirmasi dari Admin.
                            {:else if refund.refund_method === 'transfer'}
                                {#if refund.status === 'disetujui'}
                                    Pengajuan disetujui. Admin akan segera
                                    mentransfer dana ke rekening Anda dalam
                                    waktu {(page.props as any)
                                        .refund_transfer_days ?? '3-5'} hari kerja.
                                {:else if refund.status === 'selesai'}
                                    Dana sebesar {fmt(refund.refund_amount)} telah
                                    ditransfer ke rekening Anda. Refund selesai.
                                {/if}
                            {:else if refund.status === 'selesai'}
                                Pengajuan disetujui. Refund berupa koin toko
                                sebesar {fmt(refund.refund_amount)} telah dikreditkan
                                ke saldo akun Anda.
                            {/if}
                        </div>
                    </div>
                {/if}
            </div>

            <!-- Refund Info Card -->
            <div
                class="bg-white border border-slate-100 rounded-3xl p-6 shadow-sm space-y-4"
            >
                <h4
                    class="text-xs font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 pb-3"
                >
                    Informasi Pengembalian Dana
                </h4>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-3">
                        <div class="text-xs">
                            <span class="text-slate-400 font-semibold block"
                                >Alasan Pembatalan:</span
                            >
                            <p
                                class="text-slate-700 font-bold mt-1 bg-slate-50 p-3 rounded-xl border border-slate-100 whitespace-pre-line"
                            >
                                {refund.reason}
                            </p>
                        </div>
                        <div class="text-xs">
                            <span class="text-slate-400 font-semibold block"
                                >Nominal Refund:</span
                            >
                            <p
                                class="text-base font-black mt-1"
                                style="color: {primary}"
                            >
                                {fmt(refund.refund_amount)}
                            </p>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="text-xs">
                            <span class="text-slate-400 font-semibold block"
                                >Metode Refund:</span
                            >
                            <p class="text-slate-700 font-bold mt-1">
                                {refund.refund_method === 'poin'
                                    ? 'Koin Toko (Poin)'
                                    : 'Transfer Bank'}
                            </p>
                        </div>

                        {#if refund.refund_method === 'transfer'}
                            <div
                                class="text-xs bg-blue-50/50 border border-blue-100 p-4 rounded-2xl space-y-2"
                            >
                                <span class="text-blue-700 font-black block"
                                    >Rekening Tujuan</span
                                >
                                <div
                                    class="grid grid-cols-2 gap-y-1 mt-1 text-[11px] text-blue-900"
                                >
                                    <span class="font-semibold text-blue-600/70"
                                        >Nama Bank:</span
                                    >
                                    <span class="font-bold text-right"
                                        >{refund.bank_name}</span
                                    >
                                    <span class="font-semibold text-blue-600/70"
                                        >No. Rekening:</span
                                    >
                                    <span class="font-mono font-bold text-right"
                                        >{refund.account_number}</span
                                    >
                                    <span class="font-semibold text-blue-600/70"
                                        >Atas Nama:</span
                                    >
                                    <span class="font-bold text-right"
                                        >{refund.account_name}</span
                                    >
                                </div>
                            </div>
                        {:else}
                            <div
                                class="text-xs bg-amber-50/50 border border-amber-100 p-4 rounded-2xl space-y-1"
                            >
                                <span class="text-amber-700 font-black block"
                                    >Pengembalian via Koin</span
                                >
                                <p
                                    class="text-[11px] text-amber-900 leading-relaxed font-semibold"
                                >
                                    Refund langsung dikreditkan ke saldo Koin
                                    akun Anda secara otomatis setelah pengajuan
                                    disetujui oleh admin.
                                </p>
                            </div>
                        {/if}
                    </div>
                </div>

                {#if refund.processed_at}
                    <div class="h-px bg-slate-100 my-2"></div>
                    <div
                        class="grid grid-cols-2 gap-4 text-xs font-semibold text-slate-500"
                    >
                        <div>
                            <span class="text-slate-400 font-bold block"
                                >Diproses Oleh:</span
                            >
                            <span class="text-slate-700 font-bold block mt-1"
                                >{refund.processed_by_user?.name ??
                                    'Admin'}</span
                            >
                        </div>
                        <div>
                            <span class="text-slate-400 font-bold block"
                                >Waktu Proses:</span
                            >
                            <span class="text-slate-700 font-bold block mt-1"
                                >{fmtDate(refund.processed_at)}</span
                            >
                        </div>
                    </div>
                {/if}
            </div>

            <!-- Original Transaction Card -->
            {#if refund.transaction}
                <div
                    class="bg-white border border-slate-100 rounded-3xl p-6 shadow-sm space-y-4"
                >
                    <div
                        class="flex justify-between items-center border-b border-slate-100 pb-3"
                    >
                        <h4
                            class="text-xs font-bold text-slate-400 uppercase tracking-widest"
                        >
                            Transaksi Asal
                        </h4>
                        <Link
                            href={`/transactions/${refund.transaction.id}`}
                            class="text-xs font-bold transition flex items-center gap-1"
                            style="color: {primary}"
                        >
                            <span>Detail Transaksi</span>
                            <i class="ti ti-chevron-right text-xs"></i>
                        </Link>
                    </div>

                    <div class="grid grid-cols-2 gap-4 text-xs font-semibold">
                        <div>
                            <span class="text-slate-400 font-bold"
                                >No. Pesanan:</span
                            >
                            <p class="text-slate-700 font-bold mt-1">
                                #{refund.transaction.transaction_number}
                            </p>
                        </div>
                        <div>
                            <span class="text-slate-400 font-bold"
                                >Metode Pembayaran:</span
                            >
                            <p class="text-slate-700 font-bold mt-1">
                                {refund.transaction.payment_method_name ??
                                    'Transfer'}
                            </p>
                        </div>
                    </div>

                    <!-- Items Preview -->
                    <div class="space-y-2 pt-2">
                        <span
                            class="text-xs font-bold text-slate-400 uppercase tracking-wider block"
                            >Produk Pesanan</span
                        >
                        <div
                            class="bg-slate-50/50 rounded-2xl border border-slate-100 p-3 divide-y divide-slate-100"
                        >
                            {#each refund.transaction.items ?? [] as item}
                                <div
                                    class="w-full flex items-center gap-3 py-2 first:pt-1 last:pb-1"
                                >
                                    <div
                                        class="w-10 h-10 rounded-lg bg-slate-100 shrink-0 flex items-center justify-center border border-slate-200"
                                    >
                                        <i
                                            class="ti ti-package text-slate-450 text-lg"
                                        ></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p
                                            class="text-xs font-bold text-slate-800 leading-tight truncate"
                                        >
                                            {item.product_name}
                                        </p>
                                        {#if item.variant_name}
                                            <span
                                                class="inline-block text-[9px] font-semibold text-slate-450 mt-0.5"
                                                >{item.variant_name}</span
                                            >
                                        {/if}
                                    </div>
                                    <div class="text-right shrink-0">
                                        <p
                                            class="text-xs text-slate-400 font-bold"
                                        >
                                            x{item.quantity}
                                        </p>
                                        <p
                                            class="text-xs font-bold text-slate-700 mt-0.5"
                                        >
                                            {fmt(
                                                item.harga_akhir ??
                                                    item.harga_jual,
                                            )}
                                        </p>
                                    </div>
                                </div>
                            {/each}
                        </div>
                    </div>
                </div>
            {/if}
        </div>
    </div>
</StorefrontLayout>
