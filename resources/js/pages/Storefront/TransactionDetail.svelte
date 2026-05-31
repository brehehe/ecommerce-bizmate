<script lang="ts">
    import StorefrontLayout from '@/components/layouts/StorefrontLayout.svelte';
    import { page, router, Link } from '@inertiajs/svelte';
    import { showToast } from '@/utils/toast';

    let {
        transaction,
        statusLabels = {},
        paymentMethods = [] as any[],
        userReviews = {} as Record<string, any>,
        storeName = '',
        storeLogo = '',
    } = $props();

    const primary = $derived(
        (page.props as any).theme?.primary_color ?? '#ee4d2d',
    );
    const secondary = $derived(
        (page.props as any).theme?.secondary_color ?? '#fa7315',
    );

    let proofFile: File | null = $state(null);
    let proofPreview = $state('');
    let uploadingProof = $state(false);
    let showUploadModal = $state(false);

    // Cancel order modal
    let showCancelModal = $state(false);
    let cancelReason = $state('');
    let cancellingOrder = $state(false);

    // Change payment method modal
    let showChangePaymentModal = $state(false);
    let selectedPaymentMethodId = $state(transaction?.payment_method_id ?? '');
    let changingPayment = $state(false);

    // Complete order
    let completingOrder = $state(false);

    // Review modal
    let showReviewModal = $state(false);
    let reviewItem: any = $state(null);
    let reviewRating = $state(0);
    let reviewHoverRating = $state(0);
    let reviewComment = $state('');
    let reviewFiles: File[] = $state([]);
    let reviewPreviews: { url: string; type: string }[] = $state([]);
    let submittingReview = $state(false);

    const canCancel = $derived(
        ['belum_bayar', 'menunggu'].includes(transaction.status),
    );
    const canChangePayment = $derived(transaction.status === 'belum_bayar');
    const canCompleteOrder = $derived(transaction.status === 'dikirim');
    const isCompleted = $derived(transaction.status === 'selesai');

    // Check if mobile action bar should show
    const hasMobileAction = $derived(canCancel || canChangePayment || canCompleteOrder);

    function openCancelModal() {
        cancelReason = '';
        showCancelModal = true;
    }

    function submitCancel() {
        if (!cancelReason.trim()) {
            showToast('Alasan pembatalan harus diisi.', 'error');
            return;
        }
        cancellingOrder = true;
        router.post(
            `/transactions/${transaction.id}/cancel`,
            { cancel_reason: cancelReason },
            {
                onSuccess: () => {
                    showCancelModal = false;
                    showToast('Pesanan berhasil dibatalkan.', 'success');
                },
                onError: () => {
                    showToast('Gagal membatalkan pesanan.', 'error');
                },
                onFinish: () => {
                    cancellingOrder = false;
                },
            },
        );
    }

    function openChangePaymentModal() {
        selectedPaymentMethodId = transaction?.payment_method_id ?? '';
        showChangePaymentModal = true;
    }

    function submitChangePayment() {
        if (!selectedPaymentMethodId) {
            showToast('Pilih metode pembayaran terlebih dahulu.', 'error');
            return;
        }
        changingPayment = true;
        router.post(
            `/transactions/${transaction.id}/change-payment`,
            { payment_method_id: selectedPaymentMethodId },
            {
                onSuccess: () => {
                    showChangePaymentModal = false;
                    showToast('Metode pembayaran berhasil diubah.', 'success');
                },
                onError: () => {
                    showToast('Gagal mengubah metode pembayaran.', 'error');
                },
                onFinish: () => {
                    changingPayment = false;
                },
            },
        );
    }

    function completeOrder() {
        completingOrder = true;
        router.post(
            `/transactions/${transaction.id}/complete`,
            {},
            {
                onSuccess: () => {
                    showToast('Pesanan telah diterima. Terima kasih!', 'success');
                },
                onError: () => {
                    showToast('Gagal mengonfirmasi penerimaan pesanan.', 'error');
                },
                onFinish: () => {
                    completingOrder = false;
                },
            },
        );
    }

    function isItemReviewed(item: any): boolean {
        const key = String(item.product_id) + '_' + String(item.product_variant_id ?? '');
        return !!userReviews[key];
    }

    function openReviewModal(item: any) {
        reviewItem = item;
        reviewRating = 0;
        reviewHoverRating = 0;
        reviewComment = '';
        reviewFiles = [];
        reviewPreviews = [];
        showReviewModal = true;
    }

    function handleReviewFileChange(e: Event) {
        const input = e.target as HTMLInputElement;
        if (!input.files) return;
        const newFiles = Array.from(input.files);
        for (const file of newFiles) {
            if (reviewFiles.length >= 5) break;
            reviewFiles = [...reviewFiles, file];
            const url = URL.createObjectURL(file);
            reviewPreviews = [...reviewPreviews, { url, type: file.type.startsWith('video/') ? 'video' : 'image' }];
        }
        input.value = '';
    }

    function removeReviewFile(index: number) {
        URL.revokeObjectURL(reviewPreviews[index].url);
        reviewFiles = reviewFiles.filter((_, i) => i !== index);
        reviewPreviews = reviewPreviews.filter((_, i) => i !== index);
    }

    function submitReview() {
        if (reviewRating === 0) {
            showToast('Pilih bintang penilaian terlebih dahulu.', 'error');
            return;
        }
        submittingReview = true;

        const form = new FormData();
        form.append('product_id', String(reviewItem.product_id));
        if (reviewItem.product_variant_id) {
            form.append('product_variant_id', String(reviewItem.product_variant_id));
        }
        form.append('rating', String(reviewRating));
        if (reviewComment.trim()) {
            form.append('comment', reviewComment.trim());
        }
        for (const file of reviewFiles) {
            form.append('files[]', file);
        }

        router.post(
            `/transactions/${transaction.id}/review`,
            form as any,
            {
                forceFormData: true,
                onSuccess: () => {
                    showReviewModal = false;
                    showToast('Ulasan berhasil dikirim. Terima kasih!', 'success');
                    reviewPreviews.forEach(p => URL.revokeObjectURL(p.url));
                },
                onError: (errors: any) => {
                    const first = Object.values(errors)[0] as string;
                    showToast(first ?? 'Gagal mengirim ulasan.', 'error');
                },
                onFinish: () => {
                    submittingReview = false;
                },
            },
        );
    }

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

    const statusSteps = [
        { key: 'belum_bayar', label: 'Belum Bayar', icon: 'ti-cash' },
        { key: 'menunggu', label: 'Menunggu', icon: 'ti-clock' },
        { key: 'diproses', label: 'Diproses', icon: 'ti-settings' },
        { key: 'dikemas', label: 'Dikemas', icon: 'ti-package' },
        { key: 'dikirim', label: 'Dikirim', icon: 'ti-truck' },
        { key: 'selesai', label: 'Selesai', icon: 'ti-circle-check' },
    ];

    const statusIndex = $derived(
        transaction.status === 'batal'
            ? -1
            : statusSteps.findIndex((s) => s.key === transaction.status),
    );

    const statusColors: Record<string, string> = {
        belum_bayar: '#f59e0b',
        menunggu: '#3b82f6',
        diproses: '#8b5cf6',
        dikemas: '#06b6d4',
        dikirim: '#f97316',
        selesai: '#22c55e',
        batal: '#ef4444',
    };

    const currentStatusColor = $derived(
        statusColors[transaction.status] ?? '#64748b',
    );
    const paymentMethod = $derived(
        transaction.payment_method ?? transaction.paymentMethod,
    );
    const customerAddress = $derived(
        transaction.customer_address ?? transaction.customerAddress,
    );
    const latestPayment = $derived(
        transaction.payment ??
            (transaction.payments && transaction.payments.length > 0
                ? transaction.payments[transaction.payments.length - 1]
                : null),
    );

    const canUploadProof = $derived(
        (transaction.status === 'belum_bayar' ||
            transaction.status === 'menunggu') &&
            paymentMethod?.type === 'manual',
    );

    const isGateway = $derived(paymentMethod?.type === 'gateway');
    const gatewayName = $derived(
        paymentMethod?.name?.toLowerCase().includes('midtrans')
            ? 'Midtrans'
            : paymentMethod?.name?.toLowerCase().includes('flip')
              ? 'Flip'
              : 'Xendit',
    );

    function getInvoiceUrl(payment: any) {
        if (!payment || !payment.gateway_response) return null;
        try {
            const resp =
                typeof payment.gateway_response === 'string'
                    ? JSON.parse(payment.gateway_response)
                    : payment.gateway_response;
            let url =
                resp?.link_url ??
                resp?.invoice_url ??
                resp?.redirect_url ??
                null;
            if (url && !/^https?:\/\//i.test(url)) {
                url = 'https://' + url;
            }
            return url;
        } catch (e) {
            return null;
        }
    }

    function getGatewayError(payment: any) {
        if (!payment || !payment.gateway_response) return null;
        try {
            const resp =
                typeof payment.gateway_response === 'string'
                    ? JSON.parse(payment.gateway_response)
                    : payment.gateway_response;
            return resp?.error ?? null;
        } catch (e) {
            return null;
        }
    }

    const gatewayInvoiceUrl = $derived(
        isGateway ? getInvoiceUrl(latestPayment) : null,
    );
    const gatewayError = $derived(
        isGateway && !gatewayInvoiceUrl ? getGatewayError(latestPayment) : null,
    );

    function handleFileChange(e: Event) {
        const input = e.target as HTMLInputElement;
        if (input.files?.[0]) {
            proofFile = input.files[0];
            proofPreview = URL.createObjectURL(proofFile);
        }
    }

    function uploadProof() {
        if (!proofFile) return;
        uploadingProof = true;

        const form = new FormData();
        form.append('proof_image', proofFile);
        form.append('_method', 'POST');

        router.post(
            `/transactions/${transaction.id}/upload-proof`,
            form as any,
            {
                forceFormData: true,
                onSuccess: () => {
                    showToast('Bukti pembayaran berhasil diunggah!', 'success');
                    showUploadModal = false;
                    proofFile = null;
                    proofPreview = '';
                },
                onError: () => {
                    showToast('Gagal mengunggah bukti pembayaran.', 'error');
                },
                onFinish: () => {
                    uploadingProof = false;
                },
            },
        );
    }
</script>

<StorefrontLayout {storeName} {storeLogo} hideMobileFooter={true}>
    <div class="min-h-dvh bg-slate-50">
        <!-- Header -->
        <div class="bg-white border-b border-slate-200 sticky top-0 z-30">
            <div class="max-w-6xl mx-auto px-4 h-14 flex items-center gap-3">
                <Link
                    href="/transactions"
                    class="p-2 hover:bg-slate-100 rounded-full transition shrink-0"
                >
                    <i class="ti ti-arrow-left text-xl text-slate-700"></i>
                </Link>
                <div class="flex-1 min-w-0">
                    <h1 class="text-sm font-bold text-slate-800 leading-tight">
                        Detail Pesanan
                    </h1>
                    <p class="text-xs text-slate-500 leading-tight truncate">
                        {transaction.transaction_number}
                    </p>
                </div>
                <!-- Cetak Invoice Button -->
                <a
                    href={`/transactions/${transaction.id}/print-invoice?download=1`}
                    target="_blank"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 text-slate-600 text-xs font-bold transition active:scale-95 hover:bg-slate-50 shrink-0"
                    title="Cetak Invoice"
                >
                    <i class="ti ti-printer text-base"></i>
                    <span class="hidden sm:inline">Cetak Invoice</span>
                </a>
                <!-- Desktop action buttons (inside header, right side) -->
                <div class="hidden md:flex items-center gap-2 shrink-0">
                    {#if canCompleteOrder}
                        <button
                            onclick={completeOrder}
                            disabled={completingOrder}
                            class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold text-white transition active:scale-95 hover:opacity-90 disabled:opacity-60"
                            style="background:{primary}"
                        >
                            <i class="ti ti-circle-check text-sm"></i>
                            {completingOrder ? 'Memproses...' : 'Pesanan Diterima'}
                        </button>
                    {/if}
                    {#if canChangePayment}
                        <button
                            onclick={openChangePaymentModal}
                            class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl border-2 text-xs font-bold transition active:scale-95 hover:opacity-90"
                            style="border-color:{primary}; color:{primary};"
                        >
                            <i class="ti ti-credit-card text-sm"></i>
                            Ubah Pembayaran
                        </button>
                    {/if}
                    {#if canCancel}
                        <button
                            onclick={openCancelModal}
                            class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl border-2 border-red-400 text-red-500 text-xs font-bold transition active:scale-95 hover:bg-red-50"
                        >
                            <i class="ti ti-x text-sm"></i>
                            Batalkan
                        </button>
                    {/if}
                </div>
            </div>
        </div>

        <!-- Extra bottom padding on mobile to account for fixed action bar -->
        <div
            class="max-w-6xl mx-auto px-4 py-4 {hasMobileAction
                ? 'pb-28'
                : 'pb-6'} md:py-6 md:pb-6"
        >
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left/Main Column -->
                <div class="lg:col-span-2 space-y-4">
                    <!-- Status Banner -->
                    {#if transaction.status === 'batal'}
                        <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                            <div class="h-1 w-full"></div>
                            <div class="p-5">
                                <div class="flex items-center justify-between mb-4">
                                    <span
                                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-red-50 text-red-600 text-xs font-bold"
                                    >
                                        <i class="ti ti-x text-xs"></i>
                                        Dibatalkan
                                    </span>
                                    {#if transaction.cancelled_at}
                                        <span class="text-xs text-slate-400"
                                            >{fmtDate(
                                                transaction.cancelled_at,
                                            )}</span
                                        >
                                    {/if}
                                </div>

                                <div class="flex items-center gap-4 mb-4">
                                    <div
                                        class="w-14 h-14 rounded-2xl bg-red-50 border border-red-100 flex items-center justify-center shrink-0"
                                    >
                                        <i
                                            class="ti ti-package-off text-2xl text-red-400"
                                        ></i>
                                    </div>
                                    <div>
                                        <p
                                            class="font-bold text-slate-800 text-base leading-tight"
                                        >
                                            Pesanan Dibatalkan
                                        </p>
                                        <p
                                            class="text-xs text-slate-500 mt-0.5 leading-relaxed"
                                        >
                                            Pesanan ini telah dibatalkan dan tidak
                                            dapat diproses lebih lanjut.
                                        </p>
                                    </div>
                                </div>

                                {#if transaction.cancel_reason}
                                    <div
                                        class="bg-slate-50 rounded-xl px-4 py-3 border border-slate-100 flex items-start gap-2.5"
                                    >
                                        <i
                                            class="ti ti-message-circle text-sm text-slate-400 mt-0.5 shrink-0"
                                        ></i>
                                        <div>
                                            <p
                                                class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5"
                                            >
                                                Alasan Pembatalan
                                            </p>
                                            <p
                                                class="text-sm text-slate-700 leading-relaxed"
                                            >
                                                {transaction.cancel_reason}
                                            </p>
                                        </div>
                                    </div>
                                {/if}
                            </div>
                        </div>
                    {/if}

                    {#if transaction.status !== 'batal'}
                        <div
                            class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4"
                        >
                            <div class="flex items-center mb-4">
                                <div
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-white text-xs font-bold"
                                    style="background-color:{currentStatusColor}"
                                >
                                    <i class="ti ti-circle-check text-sm"></i>
                                    {statusLabels[transaction.status] ??
                                        transaction.status}
                                </div>
                                <span class="ml-auto text-xs text-slate-400"
                                    >{fmtDate(transaction.created_at)}</span
                                >
                            </div>

                            <div
                                class="flex items-center justify-between relative"
                            >
                                <div
                                    class="absolute left-4 right-4 top-4 h-0.5 bg-slate-200 z-0"
                                ></div>
                                {#if statusIndex >= 0}
                                    <div
                                        class="absolute left-4 top-4 h-0.5 z-0 transition-all duration-500"
                                        style="background:{primary}; width: calc({statusIndex >
                                        0
                                            ? (statusIndex /
                                                  (statusSteps.length - 1)) *
                                              100
                                            : 0}% - 2rem); right: auto;"
                                    ></div>
                                {/if}

                                {#each statusSteps as step, i}
                                    {@const isCompleted = statusIndex >= i}
                                    {@const isCurrent = statusIndex === i}
                                    <div
                                        class="flex flex-col items-center gap-1 z-10 flex-1"
                                    >
                                        <div
                                            class="w-8 h-8 rounded-full flex items-center justify-center border-2 transition-all"
                                            style={isCompleted
                                                ? `background:${primary}; border-color:${primary}`
                                                : 'background:white; border-color:#e2e8f0'}
                                        >
                                            <i
                                                class="ti {step.icon} text-sm"
                                                style={isCompleted
                                                    ? 'color:white'
                                                    : 'color:#94a3b8'}
                                            ></i>
                                        </div>
                                        <span
                                            class="text-[9px] font-semibold text-center leading-tight"
                                            style={isCurrent
                                                ? `color:${primary}`
                                                : isCompleted
                                                  ? 'color:#64748b'
                                                  : 'color:#94a3b8'}
                                        >
                                            {step.label}
                                        </span>
                                    </div>
                                {/each}
                            </div>

                            <!-- Complete Order Banner (when status is dikirim) -->
                            {#if canCompleteOrder}
                                <div class="mt-4 p-3 bg-orange-50 border border-orange-200 rounded-xl flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full bg-orange-100 flex items-center justify-center shrink-0">
                                        <i class="ti ti-truck-delivery text-orange-500 text-base"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-bold text-slate-800 leading-tight">Pesanan sedang dalam pengiriman</p>
                                        <p class="text-[10px] text-slate-500 leading-relaxed mt-0.5">Jika pesanan sudah tiba, klik tombol "Pesanan Diterima".</p>
                                    </div>
                                </div>
                            {/if}

                            <!-- Order Completed Banner (when status is selesai) -->
                            {#if isCompleted}
                                <div class="mt-4 p-3 rounded-xl flex items-center gap-3" style="background:{primary}10; border: 1px solid {primary}30;">
                                    <div class="w-9 h-9 rounded-full flex items-center justify-center shrink-0" style="background:{primary}20;">
                                        <i class="ti ti-circle-check text-base" style="color:{primary}"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-bold text-slate-800 leading-tight">Pesanan selesai!</p>
                                        <p class="text-[10px] text-slate-500 leading-relaxed mt-0.5">Terima kasih telah berbelanja. Jangan lupa beri ulasan produknya!</p>
                                    </div>
                                </div>
                            {/if}
                        </div>

                        <!-- Status History Timeline -->
                        {#if transaction.status_histories && transaction.status_histories.length > 0}
                            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4">
                                <div class="flex items-center gap-2 mb-4">
                                    <i class="ti ti-timeline text-base" style="color:{primary}"></i>
                                    <span class="font-bold text-slate-800 text-sm">Riwayat Status</span>
                                </div>
                                <div class="relative">
                                    <!-- Vertical line -->
                                    <div class="absolute left-[11px] top-0 bottom-0 w-0.5 bg-slate-100"></div>
                                    <div class="space-y-3">
                                        {#each [...transaction.status_histories].reverse() as hist, i}
                                            {@const histColor = statusColors[hist.status] ?? '#64748b'}
                                            {@const isLatest = i === 0}
                                            <div class="flex gap-3 relative">
                                                <div
                                                    class="rounded-full border-2 border-white flex items-center justify-center shrink-0 z-10 mt-0.5"
                                                    style="background:{isLatest ? histColor : '#e2e8f0'}; min-width:22px; min-height:22px; max-width:22px; max-height:22px;"
                                                >
                                                    {#if isLatest}
                                                        <i class="ti ti-circle-filled text-white" style="font-size:6px;"></i>
                                                    {/if}
                                                </div>
                                                <div class="flex-1 min-w-0 pb-1">
                                                    <div class="flex items-center gap-2 flex-wrap">
                                                        <span
                                                            class="text-[10px] font-black px-2 py-0.5 rounded-full uppercase tracking-wider"
                                                            style="background:{histColor}18; color:{histColor};"
                                                        >
                                                            {statusLabels[hist.status] ?? hist.status}
                                                        </span>
                                                        <span class="text-[10px] text-slate-400">{fmtDate(hist.created_at)}</span>
                                                    </div>
                                                    {#if hist.description}
                                                        <p class="text-xs text-slate-500 mt-1 leading-relaxed">{hist.description}</p>
                                                    {/if}
                                                </div>
                                            </div>
                                        {/each}
                                    </div>
                                </div>
                            </div>
                        {/if}
                    {/if}

                    <!-- Order Items -->
                    <div
                        class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden"
                    >
                        <div class="px-4 pt-4 pb-2">
                            <div class="flex items-center gap-2">
                                <i
                                    class="ti ti-package text-base"
                                    style="color:{primary}"
                                ></i>
                                <span class="font-bold text-slate-800 text-sm"
                                    >Produk Dipesan</span
                                >
                            </div>
                        </div>
                        <div class="divide-y divide-slate-100">
                            {#each transaction.items ?? [] as item}
                                {@const reviewed = isItemReviewed(item)}
                                <div class="px-4 py-3">
                                    <div class="flex gap-3">
                                        {#if item.product_image}
                                            <img
                                                src={formatImagePath(
                                                    item.product_image,
                                                )}
                                                alt={item.product_name}
                                                class="w-14 h-14 object-cover rounded-lg shrink-0 border border-slate-100"
                                                onerror={(e: any) => {
                                                    e.target.src =
                                                        '/noimage/image.png';
                                                }}
                                            />
                                        {:else}
                                            <div
                                                class="w-14 h-14 rounded-lg bg-slate-100 shrink-0 flex items-center justify-center"
                                            >
                                                <i
                                                    class="ti ti-package text-2xl text-slate-300"
                                                ></i>
                                            </div>
                                        {/if}
                                        <div class="flex-1 min-w-0">
                                            <p
                                                class="text-sm font-semibold text-slate-800 leading-tight whitespace-pre-wrap break-words"
                                            >
                                                {item.product_name}
                                            </p>
                                            {#if item.variant_name}
                                                <p
                                                    class="text-xs text-slate-500 whitespace-pre-wrap break-words"
                                                >
                                                    {item.variant_name}
                                                </p>
                                            {/if}
                                            {#if item.is_gift_item}
                                                <span
                                                    class="inline-block text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-green-100 text-green-700 mt-0.5"
                                                    >Gratis</span
                                                >
                                            {/if}
                                            <div
                                                class="flex items-center justify-between mt-1.5"
                                            >
                                                <span class="text-xs text-slate-500"
                                                    >x{item.quantity}</span
                                                >
                                                <div class="text-right">
                                                    {#if item.diskon_item > 0}
                                                        <p
                                                            class="text-xs text-slate-400 line-through"
                                                        >
                                                            {fmt(item.harga_jual)}
                                                        </p>
                                                    {/if}
                                                    <p
                                                        class="text-sm font-bold"
                                                        style="color:{primary}"
                                                    >
                                                        {fmt(item.harga_akhir)}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Beri Ulasan button for completed orders -->
                                    {#if isCompleted && !item.is_gift_item}
                                        <div class="mt-2 flex justify-end">
                                            {#if reviewed}
                                                <span class="inline-flex items-center gap-1 text-[10px] font-bold text-green-600 bg-green-50 px-3 py-1.5 rounded-xl border border-green-200">
                                                    <i class="ti ti-circle-check text-xs"></i>
                                                    Sudah Diulas
                                                </span>
                                            {:else}
                                                <button
                                                    onclick={() => openReviewModal(item)}
                                                    class="inline-flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded-xl text-white transition active:scale-95 hover:opacity-90"
                                                    style="background:{primary}"
                                                >
                                                    <i class="ti ti-star text-xs"></i>
                                                    Beri Ulasan
                                                </button>
                                            {/if}
                                        </div>
                                    {/if}
                                </div>
                            {/each}
                        </div>
                    </div>

                    <!-- Upload Proof (if manual payment & belum bayar/menunggu) -->
                    {#if canUploadProof}
                        <div
                            class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden"
                        >
                            <div
                                class="px-4 pt-4 pb-3 flex items-center gap-2 border-b border-slate-100"
                            >
                                <i
                                    class="ti ti-alert-triangle text-base text-amber-500"
                                ></i>
                                <span class="font-bold text-slate-800 text-sm"
                                    >Upload Bukti Pembayaran</span
                                >
                            </div>
                            <div class="p-4">
                                <div
                                    class="bg-slate-50 rounded-xl p-3 border border-slate-100 mb-3"
                                >
                                    <p class="text-xs text-slate-500 mb-0.5">
                                        Transfer ke:
                                    </p>
                                    <p class="text-sm font-bold text-slate-800">
                                        {paymentMethod?.bank_name}
                                    </p>
                                    <p
                                        class="text-xl font-black text-slate-900"
                                    >
                                        {paymentMethod?.account_number}
                                    </p>
                                    <p class="text-xs text-slate-500">
                                        a.n. {paymentMethod?.account_name}
                                    </p>
                                    <div
                                        class="mt-2 pt-2 border-t border-slate-200 flex items-center justify-between"
                                    >
                                        <p class="text-xs text-slate-500">
                                            Jumlah transfer:
                                        </p>
                                        <p
                                            class="text-base font-black"
                                            style="color:{primary}"
                                        >
                                            {fmt(transaction.grand_total)}
                                        </p>
                                    </div>
                                </div>
                                {#if latestPayment?.proof_image}
                                    <div class="mb-3">
                                        <p class="text-xs text-slate-500 mb-1">
                                            Bukti bayar yang diunggah:
                                        </p>
                                        <img
                                            src={formatImagePath(
                                                latestPayment.proof_image,
                                            )}
                                            alt="Bukti Pembayaran"
                                            class="w-28 h-28 object-cover rounded-xl border border-slate-200"
                                        />
                                        <p class="text-xs text-slate-400 mt-1">
                                            Diunggah {fmtDate(
                                                latestPayment.proof_uploaded_at,
                                            )}
                                            {#if latestPayment.status === 'rejected' && latestPayment.notes}
                                                <span
                                                    class="text-red-500 font-semibold"
                                                >
                                                    · Ditolak: {latestPayment.notes}</span
                                                >
                                            {/if}
                                        </p>
                                    </div>
                                {/if}
                                <button
                                    onclick={() => (showUploadModal = true)}
                                    class="w-full flex items-center justify-center gap-2 py-3 rounded-xl text-sm font-bold text-white transition active:scale-95"
                                    style="background:{primary}"
                                >
                                    <i class="ti ti-upload text-sm"></i>
                                    {latestPayment?.proof_image
                                        ? 'Ganti Bukti Bayar'
                                        : 'Upload Bukti Bayar'}
                                </button>
                            </div>
                        </div>
                    {/if}

                    <!-- Payment Gateway Block (if gateway payment & belum bayar) -->
                    {#if isGateway && transaction.status === 'belum_bayar'}
                        <div
                            class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden"
                        >
                            <div
                                class="px-4 pt-4 pb-3 flex items-center gap-2 border-b border-slate-100"
                            >
                                <i
                                    class="ti ti-credit-card text-base animate-pulse"
                                    style="color:{primary}"
                                ></i>
                                <span class="font-bold text-slate-800 text-sm"
                                    >Selesaikan Pembayaran</span
                                >
                            </div>
                            <div class="p-4">
                                <p
                                    class="text-xs text-slate-500 leading-relaxed mb-3"
                                >
                                    Pesanan Anda menggunakan sistem pembayaran
                                    otomatis terverifikasi. Silakan klik tombol
                                    di bawah untuk membuka portal pembayaran {gatewayName}.
                                </p>
                                <div
                                    class="flex items-center justify-between bg-slate-50 rounded-xl p-3 border border-slate-100"
                                >
                                    <div>
                                        <p class="text-xs text-slate-500">
                                            Total Tagihan:
                                        </p>
                                        <p
                                            class="text-base font-black"
                                            style="color:{primary}"
                                        >
                                            {fmt(transaction.grand_total)}
                                        </p>
                                    </div>
                                    {#if gatewayInvoiceUrl}
                                        <a
                                            href={gatewayInvoiceUrl}
                                            class="flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-black text-white transition active:scale-95 hover:opacity-90 shadow-sm"
                                            style="background:{primary}"
                                        >
                                            Bayar Sekarang
                                            <i class="ti ti-arrow-right"></i>
                                        </a>
                                    {:else if gatewayError}
                                        <div
                                            class="text-xs text-red-500 font-bold max-w-[180px] leading-relaxed text-right"
                                        >
                                            <i class="ti ti-alert-circle mr-1"
                                            ></i>
                                            {gatewayError}
                                        </div>
                                    {/if}
                                </div>
                            </div>
                        </div>
                    {/if}
                </div>

                <!-- Right/Sidebar Column -->
                <div class="space-y-4">
                    <!-- Shipping Address -->
                    {#if customerAddress}
                        <div
                            class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4"
                        >
                            <div class="flex items-center gap-2 mb-2">
                                <i
                                    class="ti ti-map-pin text-base"
                                    style="color:{primary}"
                                ></i>
                                <span class="font-bold text-slate-800 text-sm"
                                    >Alamat Pengiriman</span
                                >
                            </div>
                            <p
                                class="text-sm font-semibold text-slate-800 whitespace-pre-wrap break-words"
                            >
                                {customerAddress.receiver_name}
                            </p>
                            <p class="text-xs text-slate-500 break-all">
                                {customerAddress.phone_number}
                            </p>
                            <p
                                class="text-xs text-slate-600 mt-1 leading-relaxed whitespace-pre-wrap break-words"
                            >
                                {customerAddress.full_address}
                            </p>
                            {#if customerAddress.regency_name}
                                <p class="text-xs text-slate-500">
                                    {customerAddress.district_name}, {customerAddress.regency_name},
                                    {customerAddress.province_name}
                                    {customerAddress.postal_code}
                                </p>
                            {/if}
                            {#if transaction.shipping_courier}
                                <div
                                    class="mt-2 pt-2 border-t border-slate-100 flex items-center gap-2 text-xs text-slate-600"
                                >
                                    <i class="ti ti-truck"></i>
                                    <span class="font-semibold uppercase"
                                        >{transaction.shipping_courier}</span
                                    >
                                    <span>{transaction.shipping_service}</span>
                                    {#if transaction.shipping_etd}
                                        <span
                                            >· Est. {transaction.shipping_etd} hari</span
                                        >
                                    {/if}
                                </div>
                            {/if}
                        </div>
                    {/if}

                    <!-- Shipping Information (Resi) -->
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4">
                        <div class="flex items-center gap-2 mb-3">
                            <i class="ti ti-truck-delivery text-base" style="color:{primary}"></i>
                            <span class="font-bold text-slate-800 text-sm">Informasi Pengiriman</span>
                        </div>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center text-xs">
                                <span class="text-slate-500">Kurir / Layanan</span>
                                <span class="font-bold text-slate-800 uppercase">
                                    {transaction.courier_name || transaction.shipping_courier || '-'} 
                                    {#if transaction.shipping_service}
                                        ({transaction.shipping_service})
                                    {/if}
                                </span>
                            </div>
                            
                            <div class="pt-2 border-t border-slate-100 flex flex-col gap-1">
                                <span class="text-xs text-slate-500">Nomor Resi</span>
                                {#if transaction.tracking_number}
                                    <div class="flex items-center justify-between bg-slate-50 px-3 py-2 rounded-xl border border-slate-100 mt-1">
                                        <span class="font-mono font-bold text-sm text-slate-800 select-all">{transaction.tracking_number}</span>
                                        <button 
                                            onclick={() => {
                                                navigator.clipboard.writeText(transaction.tracking_number);
                                                showToast('Nomor resi berhasil disalin!', 'success');
                                            }}
                                            class="p-1 hover:bg-slate-200 rounded-lg text-slate-400 hover:text-slate-600 transition"
                                            title="Salin Resi"
                                        >
                                            <i class="ti ti-copy text-sm"></i>
                                        </button>
                                    </div>
                                {:else}
                                    <div class="flex items-center gap-2 text-amber-600 bg-amber-50 px-3 py-2 rounded-xl border border-amber-100 mt-1 text-xs">
                                        <i class="ti ti-alert-circle text-sm"></i>
                                        <span class="font-medium">Nomor resi belum tersedia</span>
                                    </div>
                                {/if}
                            </div>
                        </div>
                    </div>

                    <!-- Payment Summary -->
                    <div
                        class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4"
                    >
                        <div class="flex items-center gap-2 mb-3">
                            <i
                                class="ti ti-receipt text-base"
                                style="color:{primary}"
                            ></i>
                            <span class="font-bold text-slate-800 text-sm"
                                >Rincian Pembayaran</span
                            >
                        </div>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-slate-600"
                                    >Subtotal Produk</span
                                >
                                <span class="font-semibold"
                                    >{fmt(transaction.subtotal)}</span
                                >
                            </div>
                            {#if transaction.discount_amount > 0}
                                <div
                                    class="flex justify-between text-green-600"
                                >
                                    <span
                                        >Diskon Voucher{transaction.voucher_code
                                            ? ` (${transaction.voucher_code})`
                                            : ''}</span
                                    >
                                    <span class="font-semibold"
                                        >-{fmt(
                                            transaction.discount_amount,
                                        )}</span
                                    >
                                </div>
                            {/if}
                            <div class="flex justify-between">
                                <span class="text-slate-600">Ongkos Kirim</span>
                                <span class="font-semibold"
                                    >{fmt(transaction.shipping_fee)}</span
                                >
                            </div>
                            {#if transaction.shipping_discount > 0}
                                <div
                                    class="flex justify-between text-green-600"
                                >
                                    <span>Gratis Ongkir</span>
                                    <span class="font-semibold"
                                        >-{fmt(
                                            transaction.shipping_discount,
                                        )}</span
                                    >
                                </div>
                            {/if}
                            {#if transaction.admin_fee > 0}
                                <div class="flex justify-between">
                                    <span class="text-slate-600"
                                        >Biaya Admin</span
                                    >
                                    <span class="font-semibold"
                                        >{fmt(transaction.admin_fee)}</span
                                    >
                                </div>
                            {/if}
                            {#if transaction.application_fee > 0}
                                <div class="flex justify-between">
                                    <span class="text-slate-600"
                                        >Biaya Aplikasi</span
                                    >
                                    <span class="font-semibold"
                                        >{fmt(
                                            transaction.application_fee,
                                        )}</span
                                    >
                                </div>
                            {/if}
                            <div
                                class="border-t border-slate-100 pt-2 flex justify-between"
                            >
                                <span class="font-bold text-slate-800"
                                    >Total</span
                                >
                                <span
                                    class="font-black text-lg"
                                    style="color:{primary}"
                                    >{fmt(transaction.grand_total)}</span
                                >
                            </div>
                        </div>

                        <div
                            class="mt-3 pt-3 border-t border-slate-100 flex items-center gap-2"
                        >
                            <i class="ti ti-credit-card text-slate-400"></i>
                            <span class="text-xs text-slate-600">
                                {paymentMethod?.name ?? 'Metode Pembayaran'}
                                {#if latestPayment?.status === 'confirmed'}
                                    <span
                                        class="ml-2 text-green-600 font-semibold"
                                        >✓ Dikonfirmasi</span
                                    >
                                {:else if latestPayment?.status === 'rejected'}
                                    <span
                                        class="ml-2 text-red-500 font-semibold"
                                        >✗ Ditolak</span
                                    >
                                {/if}
                            </span>
                        </div>
                    </div>

                    {#if transaction.notes}
                        <div
                            class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4"
                        >
                            <div class="flex items-center gap-2 mb-2">
                                <i
                                    class="ti ti-notes text-base"
                                    style="color:{primary}"
                                ></i>
                                <span class="font-bold text-slate-800 text-sm"
                                    >Catatan</span
                                >
                            </div>
                            <p
                                class="text-sm text-slate-600 whitespace-pre-wrap break-words"
                            >
                                {transaction.notes}
                            </p>
                        </div>
                    {/if}
                </div>
            </div>
        </div>
    </div>

    <!-- ===== Mobile Fixed Bottom Action Bar ===== -->
    {#if hasMobileAction}
        <div
            class="md:hidden fixed bottom-0 left-0 right-0 z-40 bg-white border-t border-slate-200 px-4 py-3 flex gap-3 shadow-[0_-4px_20px_rgba(0,0,0,0.08)]"
        >
            {#if canCompleteOrder}
                <button
                    onclick={completeOrder}
                    disabled={completingOrder}
                    class="flex-1 flex items-center justify-center gap-2 py-3 rounded-xl text-sm font-bold text-white transition active:scale-95 disabled:opacity-60"
                    style="background:{primary}"
                >
                    <i class="ti ti-circle-check text-base"></i>
                    {completingOrder ? 'Memproses...' : 'Pesanan Diterima'}
                </button>
            {/if}
            {#if canChangePayment}
                <button
                    onclick={openChangePaymentModal}
                    class="flex-1 flex items-center justify-center gap-2 py-3 rounded-xl border-2 text-sm font-bold transition active:scale-95"
                    style="border-color:{primary}; color:{primary};"
                >
                    <i class="ti ti-credit-card text-base"></i>
                    Ubah Pembayaran
                </button>
            {/if}
            {#if canCancel}
                <button
                    onclick={openCancelModal}
                    class="flex-1 flex items-center justify-center gap-2 py-3 rounded-xl border-2 text-sm font-bold transition active:scale-95"
                    style="border-color:red; color:red;"
                >
                    <i class="ti ti-x text-base"></i>
                    Batalkan
                </button>
            {/if}
        </div>
    {/if}

    <!-- Upload Proof Modal -->
    {#if showUploadModal}
        <div
            class="fixed inset-0 z-50 flex items-end lg:items-center justify-center"
            onclick={() => (showUploadModal = false)}
        >
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
            <div
                class="relative z-10 bg-white w-full lg:max-w-md rounded-t-3xl lg:rounded-2xl p-5"
                onclick={(e: any) => e.stopPropagation()}
            >
                <h3 class="font-bold text-slate-800 mb-4">
                    Upload Bukti Pembayaran
                </h3>

                <div
                    class="border-2 border-dashed border-slate-300 rounded-xl p-6 text-center cursor-pointer hover:border-slate-400 transition"
                    onclick={() =>
                        (
                            document.getElementById(
                                'proof-upload-input',
                            ) as HTMLInputElement
                        )?.click()}
                >
                    {#if proofPreview}
                        <img
                            src={proofPreview}
                            alt="Preview"
                            class="max-h-48 mx-auto rounded-lg object-contain"
                        />
                        <p class="text-xs text-slate-500 mt-2">
                            Klik untuk ganti gambar
                        </p>
                    {:else}
                        <i class="ti ti-photo text-4xl text-slate-300"></i>
                        <p class="text-sm text-slate-500 mt-2">
                            Klik untuk memilih foto
                        </p>
                        <p class="text-xs text-slate-400 mt-1">
                            JPG, PNG, WEBP (maks. 5MB)
                        </p>
                    {/if}
                </div>
                <input
                    id="proof-upload-input"
                    type="file"
                    accept="image/*"
                    class="hidden"
                    onchange={handleFileChange}
                />

                <div class="flex gap-3 mt-4">
                    <button
                        onclick={() => (showUploadModal = false)}
                        class="flex-1 py-3 rounded-xl border-2 border-slate-200 text-slate-700 font-semibold text-sm hover:bg-slate-50 transition"
                    >
                        Batal
                    </button>
                    <button
                        onclick={uploadProof}
                        disabled={!proofFile || uploadingProof}
                        class="flex-1 py-3 rounded-xl font-bold text-white text-sm transition disabled:opacity-50"
                        style="background:{primary}"
                    >
                        {uploadingProof ? 'Mengunggah...' : 'Upload Sekarang'}
                    </button>
                </div>
            </div>
        </div>
    {/if}

    <!-- Cancel Order Modal -->
    {#if showCancelModal}
        <div
            class="fixed inset-0 z-[9999] flex items-end lg:items-center justify-center"
            onclick={() => (showCancelModal = false)}
        >
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
            <div
                class="relative z-10 bg-white w-full lg:max-w-md rounded-t-3xl lg:rounded-2xl p-5"
                onclick={(e: any) => e.stopPropagation()}
            >
                <div
                    class="w-14 h-14 rounded-full bg-red-50 flex items-center justify-center mx-auto mb-4"
                >
                    <i class="ti ti-alert-triangle text-2xl text-red-500"></i>
                </div>
                <h3 class="font-bold text-slate-800 text-center text-lg mb-1">
                    Batalkan Pesanan?
                </h3>
                <p
                    class="text-xs text-slate-500 text-center mb-5 leading-relaxed"
                >
                    Pesanan yang dibatalkan tidak dapat dikembalikan. Mohon
                    berikan alasan pembatalan.
                </p>

                <div class="mb-4">
                    <label
                        class="block text-xs font-bold text-slate-700 mb-1.5"
                        for="cancel-reason"
                    >
                        Alasan Pembatalan <span class="text-red-500">*</span>
                    </label>
                    <textarea
                        id="cancel-reason"
                        bind:value={cancelReason}
                        rows="3"
                        placeholder="Contoh: Ingin mengganti produk, salah pilih item, dll."
                        class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-800 resize-none focus:outline-none focus:ring-2 transition"
                        style="--tw-ring-color:{primary}20;"
                    ></textarea>
                    <p class="text-[10px] text-slate-400 mt-1 text-right">
                        {cancelReason.length}/500
                    </p>
                </div>

                <div class="flex gap-3">
                    <button
                        onclick={() => (showCancelModal = false)}
                        class="flex-1 py-3 rounded-xl border-2 border-slate-200 text-slate-700 font-semibold text-sm hover:bg-slate-50 transition"
                    >
                        Kembali
                    </button>
                    <button
                        onclick={submitCancel}
                        disabled={cancellingOrder || !cancelReason.trim()}
                        class="flex-1 py-3 rounded-xl font-bold text-white text-sm transition disabled:opacity-50 bg-red-500 hover:bg-red-600 active:scale-95"
                    >
                        {cancellingOrder ? 'Membatalkan...' : 'Ya, Batalkan'}
                    </button>
                </div>
            </div>
        </div>
    {/if}

    <!-- Change Payment Method Modal -->
    {#if showChangePaymentModal}
        <div
            class="fixed inset-0 z-[9999] flex items-end lg:items-center justify-center"
            onclick={() => (showChangePaymentModal = false)}
        >
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
            <div
                class="relative z-10 bg-white w-full lg:max-w-md rounded-t-3xl lg:rounded-2xl p-5"
                onclick={(e: any) => e.stopPropagation()}
            >
                <div
                    class="w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-4"
                    style="background-color:{primary}15"
                >
                    <i
                        class="ti ti-credit-card text-2xl"
                        style="color:{primary}"
                    ></i>
                </div>
                <h3 class="font-bold text-slate-800 text-center text-lg mb-1">
                    Ubah Metode Pembayaran
                </h3>
                <p
                    class="text-xs text-slate-500 text-center mb-5 leading-relaxed"
                >
                    Pilih metode pembayaran yang ingin digunakan untuk pesanan
                    ini.
                </p>

                <div class="space-y-2 mb-5">
                    {#each paymentMethods as method}
                        <button
                            onclick={() =>
                                (selectedPaymentMethodId = method.id)}
                            class="w-full flex items-center gap-3 p-3.5 rounded-xl border-2 transition text-left cursor-pointer"
                            style={selectedPaymentMethodId === method.id
                                ? `border-color:${primary}; background-color:${primary}08;`
                                : 'border-color:#e2e8f0;'}
                        >
                            <div
                                class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0"
                                style={selectedPaymentMethodId === method.id
                                    ? `background-color:${primary}; color:white;`
                                    : 'background-color:#f1f5f9; color:#94a3b8;'}
                            >
                                <i
                                    class="ti {method.type === 'gateway'
                                        ? 'ti-world-www'
                                        : 'ti-building-bank'} text-sm"
                                ></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p
                                    class="text-sm font-bold text-slate-800 truncate"
                                >
                                    {method.name}
                                </p>
                                <p class="text-[10px] text-slate-400">
                                    {method.type === 'gateway'
                                        ? 'Pembayaran Otomatis'
                                        : 'Transfer Manual'}
                                </p>
                            </div>
                            {#if selectedPaymentMethodId === method.id}
                                <i
                                    class="ti ti-circle-check text-lg shrink-0"
                                    style="color:{primary}"
                                ></i>
                            {/if}
                        </button>
                    {/each}
                </div>

                <div class="flex gap-3">
                    <button
                        onclick={() => (showChangePaymentModal = false)}
                        class="flex-1 py-3 rounded-xl border-2 border-slate-200 text-slate-700 font-semibold text-sm hover:bg-slate-50 transition"
                    >
                        Batal
                    </button>
                    <button
                        onclick={submitChangePayment}
                        disabled={changingPayment || !selectedPaymentMethodId}
                        class="flex-1 py-3 rounded-xl font-bold text-white text-sm transition disabled:opacity-50 active:scale-95"
                        style="background:{primary}"
                    >
                        {changingPayment ? 'Menyimpan...' : 'Simpan Perubahan'}
                    </button>
                </div>
            </div>
        </div>
    {/if}

    <!-- Beri Ulasan Modal -->
    {#if showReviewModal && reviewItem}
        <div
            class="fixed inset-0 z-[9999] flex items-end lg:items-center justify-center"
            onclick={() => (showReviewModal = false)}
        >
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>
            <div
                class="relative z-10 bg-white w-full lg:max-w-lg rounded-t-3xl lg:rounded-2xl max-h-[90dvh] overflow-y-auto"
                onclick={(e: any) => e.stopPropagation()}
            >
                <div class="sticky top-0 bg-white px-5 pt-5 pb-3 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-slate-800 text-base">Beri Ulasan</h3>
                        <p class="text-xs text-slate-500 mt-0.5 leading-tight line-clamp-1">{reviewItem.product_name}</p>
                    </div>
                    <button
                        onclick={() => (showReviewModal = false)}
                        class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center hover:bg-slate-200 transition"
                    >
                        <i class="ti ti-x text-sm text-slate-600"></i>
                    </button>
                </div>

                <div class="p-5 space-y-5">
                    <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl border border-slate-100">
                        {#if reviewItem.product_image}
                            <img
                                src={formatImagePath(reviewItem.product_image)}
                                alt={reviewItem.product_name}
                                class="w-12 h-12 object-cover rounded-lg shrink-0 border border-slate-100"
                                onerror={(e: any) => { e.target.src = '/noimage/image.png'; }}
                            />
                        {:else}
                            <div class="w-12 h-12 rounded-lg bg-slate-200 shrink-0 flex items-center justify-center">
                                <i class="ti ti-package text-slate-400 text-lg"></i>
                            </div>
                        {/if}
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-bold text-slate-800 leading-tight line-clamp-2">{reviewItem.product_name}</p>
                            {#if reviewItem.variant_name}
                                <p class="text-xs text-slate-500 mt-0.5">{reviewItem.variant_name}</p>
                            {/if}
                        </div>
                    </div>

                    <div>
                        <p class="text-xs font-bold text-slate-700 mb-2">Penilaian Produk <span class="text-red-500">*</span></p>
                        <div class="flex items-center gap-2">
                            {#each [1, 2, 3, 4, 5] as star}
                                <button
                                    onclick={() => (reviewRating = star)}
                                    onmouseenter={() => (reviewHoverRating = star)}
                                    onmouseleave={() => (reviewHoverRating = 0)}
                                    class="text-3xl transition-transform hover:scale-110 active:scale-95"
                                    style="color:{(reviewHoverRating || reviewRating) >= star ? '#f59e0b' : '#e2e8f0'};"
                                >
                                    <i class="ti ti-star-filled"></i>
                                </button>
                            {/each}
                            {#if reviewRating > 0}
                                <span class="text-xs font-bold text-amber-500 ml-1">
                                    {reviewRating === 1 ? 'Buruk' : reviewRating === 2 ? 'Kurang' : reviewRating === 3 ? 'Cukup' : reviewRating === 4 ? 'Bagus' : 'Sempurna'}
                                </span>
                            {/if}
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2" for="review-comment">
                            Ulasan <span class="text-slate-400 font-normal">(opsional)</span>
                        </label>
                        <textarea
                            id="review-comment"
                            bind:value={reviewComment}
                            rows="4"
                            maxlength="1000"
                            placeholder="Ceritakan pengalaman Anda dengan produk ini..."
                            class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-800 resize-none focus:outline-none focus:ring-2 focus:border-transparent transition leading-relaxed"
                            style="focus-ring-color:{primary}40;"
                        ></textarea>
                        <p class="text-[10px] text-slate-400 mt-1 text-right">{reviewComment.length}/1000</p>
                    </div>

                    <div>
                        <p class="text-xs font-bold text-slate-700 mb-2">Foto / Video <span class="text-slate-400 font-normal">(maks. 5 file)</span></p>

                        {#if reviewPreviews.length > 0}
                            <div class="grid grid-cols-5 gap-2 mb-3">
                                {#each reviewPreviews as preview, idx}
                                    <div class="relative aspect-square rounded-xl overflow-hidden border border-slate-200 bg-slate-50 group">
                                        {#if preview.type === 'video'}
                                            <video
                                                src={preview.url}
                                                class="w-full h-full object-cover"
                                                muted
                                            ></video>
                                            <div class="absolute inset-0 flex items-center justify-center bg-black/20">
                                                <i class="ti ti-player-play text-white text-base"></i>
                                            </div>
                                        {:else}
                                            <img
                                                src={preview.url}
                                                alt="Preview {idx + 1}"
                                                class="w-full h-full object-cover"
                                            />
                                        {/if}
                                        <button
                                            onclick={() => removeReviewFile(idx)}
                                            class="absolute top-1 right-1 w-5 h-5 bg-red-500 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition"
                                        >
                                            <i class="ti ti-x text-white" style="font-size:9px;"></i>
                                        </button>
                                    </div>
                                {/each}
                                {#if reviewPreviews.length < 5}
                                    <button
                                        onclick={() => (document.getElementById('review-file-input') as HTMLInputElement)?.click()}
                                        class="aspect-square rounded-xl border-2 border-dashed border-slate-300 flex flex-col items-center justify-center text-slate-400 hover:border-slate-400 hover:text-slate-500 transition"
                                    >
                                        <i class="ti ti-plus text-lg"></i>
                                    </button>
                                {/if}
                            </div>
                        {:else}
                            <button
                                onclick={() => (document.getElementById('review-file-input') as HTMLInputElement)?.click()}
                                class="w-full border-2 border-dashed border-slate-200 rounded-xl py-6 flex flex-col items-center gap-2 text-slate-400 hover:border-slate-300 hover:text-slate-500 transition"
                            >
                                <i class="ti ti-camera text-2xl"></i>
                                <p class="text-xs font-semibold">Tambahkan foto atau video</p>
                                <p class="text-[10px]">JPG, PNG, WEBP, MP4, MOV (maks. 20MB/file)</p>
                            </button>
                        {/if}
                        <input
                            id="review-file-input"
                            type="file"
                            accept="image/jpeg,image/png,image/jpg,image/gif,image/webp,video/mp4,video/mov,video/avi"
                            multiple
                            class="hidden"
                            onchange={handleReviewFileChange}
                        />
                    </div>

                    <div class="flex gap-3 pt-1">
                        <button
                            onclick={() => (showReviewModal = false)}
                            class="flex-1 py-3 rounded-xl border-2 border-slate-200 text-slate-700 font-semibold text-sm hover:bg-slate-50 transition"
                        >
                            Batal
                        </button>
                        <button
                            onclick={submitReview}
                            disabled={submittingReview || reviewRating === 0}
                            class="flex-1 py-3 rounded-xl font-bold text-white text-sm transition disabled:opacity-50 active:scale-95"
                            style="background:{primary}"
                        >
                            {submittingReview ? 'Mengirim...' : 'Kirim Ulasan'}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    {/if}
</StorefrontLayout>
