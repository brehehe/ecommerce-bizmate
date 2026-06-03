<script lang="ts">
    import StorefrontLayout from '@/components/layouts/StorefrontLayout.svelte';
    import { page, router, Link } from '@inertiajs/svelte';
    import { showToast } from '@/utils/toast';

    let {
        transaction,
        statusLabels = {},
        returnStatusLabels = {} as Record<string, string>,
        paymentMethods = [] as any[],
        userReviews = {} as Record<string, any>,
        userBankAccounts = [] as any[],
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
    let refundMethod = $state('transfer');
    let bankName = $state('');
    let accountNumber = $state('');
    let accountName = $state('');

    $effect(() => {
        if (userBankAccounts && userBankAccounts.length > 0) {
            const primaryAcc = userBankAccounts.find((acc: any) => acc.is_primary) || userBankAccounts[0];
            if (primaryAcc) {
                bankName = primaryAcc.bank_name || '';
                accountNumber = primaryAcc.account_number || '';
                accountName = primaryAcc.account_name || '';
            }
        }
    });

    const activeRefundRequest = $derived(
        transaction.active_refund_request ?? transaction.refund_requests?.[0] ?? null
    );

    const canCancelDirectly = $derived(transaction.status === 'belum_bayar');
    const canRequestCancel = $derived(
        ['menunggu', 'diproses'].includes(transaction.status) && !activeRefundRequest
    );
    const canCancel = $derived(canCancelDirectly || canRequestCancel);
    const canChangePayment = $derived(transaction.status === 'belum_bayar');
    const canCompleteOrder = $derived(transaction.status === 'dikirim');
    const isCompleted = $derived(transaction.status === 'selesai');

    // Change payment method modal
    let showChangePaymentModal = $state(false);
    let selectedPaymentMethodId = $state('');
    $effect(() => {
        if (!selectedPaymentMethodId && transaction?.payment_method_id) {
            selectedPaymentMethodId = transaction.payment_method_id;
        }
    });
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

    // Return/Retur system
    let showReturnModal = $state(false);
    let returnStep = $state<'form' | 'items'>('form');
    let returType = $state<'refund' | 'penggantian_barang'>('refund');
    let returReason = $state('');
    // svelte-ignore state_referenced_locally
    let returItems = $state(
        (transaction.items ?? []).map((item: any) => ({
            transaction_item_id: item.id,
            product_name: item.product_name ?? item.product?.name ?? '',
            variant_name: item.variant_name ?? '',
            quantity: item.quantity,
            returnQty: 0,
            price: item.harga_akhir ?? item.harga_jual ?? 0,
            selected: false,
        })),
    );
    let returFiles: File[] = $state([]);
    let returPreviews: { url: string; type: string }[] = $state([]);
    let submittingReturn = $state(false);

    const activeReturn = $derived(
        transaction.active_return ?? transaction.returns?.[0] ?? null,
    );
    const canRetur = $derived(
        transaction.status === 'selesai' && !activeReturn,
    );

    const returnStatusColors: Record<string, { bg: string; text: string }> = {
        menunggu_review: { bg: '#fef3c7', text: '#92400e' },
        disetujui: { bg: '#dbeafe', text: '#1e40af' },
        ditolak: { bg: '#fee2e2', text: '#991b1b' },
        barang_dikirim_customer: { bg: '#ffedd5', text: '#9a3412' },
        barang_diterima_toko: { bg: '#ede9fe', text: '#5b21b6' },
        refund_diproses: { bg: '#cffafe', text: '#0e7490' },
        selesai: { bg: '#dcfce7', text: '#166534' },
    };

    const refundStatusColors: Record<string, { bg: string; text: string }> = {
        menunggu_konfirmasi: { bg: '#fef3c7', text: '#92400e' },
        disetujui: { bg: '#dbeafe', text: '#1e40af' },
        ditolak: { bg: '#fee2e2', text: '#991b1b' },
        selesai: { bg: '#dcfce7', text: '#166534' },
    };

    const refundStatusLabels: Record<string, string> = {
        menunggu_konfirmasi: 'Menunggu Review',
        disetujui: 'Disetujui',
        ditolak: 'Ditolak',
        selesai: 'Selesai',
    };

    const isUnderMinLimit = $derived(
        !canCancelDirectly && (
            (refundMethod === 'transfer' && Number(transaction.grand_total) < Number((page.props as any).refund_min_amount_transfer ?? 0)) ||
            (refundMethod === 'poin' && Number(transaction.grand_total) < Number((page.props as any).refund_min_amount_points ?? 0))
        )
    );

    const isSubmitCancelDisabled = $derived(
        cancellingOrder || 
        !cancelReason.trim() || 
        isUnderMinLimit || 
        (!canCancelDirectly && refundMethod === 'transfer' && (!bankName.trim() || !accountNumber.trim() || !accountName.trim()))
    );

    // Check if mobile action bar should show
    const hasMobileAction = $derived(
        canCancel || canChangePayment || canCompleteOrder || canRetur,
    );

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

        if (canCancelDirectly) {
            router.post(
                `/transactions/${transaction.id}/cancel`,
                { cancel_reason: cancelReason },
                {
                    onSuccess: () => {
                        showCancelModal = false;
                    },
                    onError: () => {
                        showToast('Gagal membatalkan pesanan.', 'error');
                    },
                    onFinish: () => {
                        cancellingOrder = false;
                    },
                },
            );
        } else {
            const payload: any = {
                reason: cancelReason,
                refund_method: refundMethod,
            };

            if (refundMethod === 'transfer') {
                if (!bankName.trim() || !accountNumber.trim() || !accountName.trim()) {
                    showToast('Data rekening bank harus diisi lengkap.', 'error');
                    cancellingOrder = false;
                    return;
                }
                payload.bank_name = bankName;
                payload.account_number = accountNumber;
                payload.account_name = accountName;
            }

            router.post(
                `/transactions/${transaction.id}/refund-request`,
                payload,
                {
                    onSuccess: () => {
                        showCancelModal = false;
                    },
                    onError: (errors) => {
                        const errMsg = Object.values(errors)[0] || 'Gagal mengajukan pembatalan.';
                        showToast(errMsg, 'error');
                    },
                    onFinish: () => {
                        cancellingOrder = false;
                    },
                },
            );
        }
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
                },
                onError: () => {
                    showToast(
                        'Gagal mengonfirmasi penerimaan pesanan.',
                        'error',
                    );
                },
                onFinish: () => {
                    completingOrder = false;
                },
            },
        );
    }

    function isItemReviewed(item: any): boolean {
        const key =
            String(item.product_id) +
            '_' +
            String(item.product_variant_id ?? '');
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
            reviewPreviews = [
                ...reviewPreviews,
                {
                    url,
                    type: file.type.startsWith('video/') ? 'video' : 'image',
                },
            ];
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
            form.append(
                'product_variant_id',
                String(reviewItem.product_variant_id),
            );
        }
        form.append('rating', String(reviewRating));
        if (reviewComment.trim()) {
            form.append('comment', reviewComment.trim());
        }
        for (const file of reviewFiles) {
            form.append('files[]', file);
        }

        router.post(`/transactions/${transaction.id}/review`, form as any, {
            forceFormData: true,
            onSuccess: () => {
                showReviewModal = false;
                reviewPreviews.forEach((p) => URL.revokeObjectURL(p.url));
            },
            onError: (errors: any) => {
                const first = Object.values(errors)[0] as string;
                showToast(first ?? 'Gagal mengirim ulasan.', 'error');
            },
            onFinish: () => {
                submittingReview = false;
            },
        });
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
        { key: 'out_for_pickup', label: 'Out for Pickup', icon: 'ti-truck-delivery' },
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
        out_for_pickup: '#d97706',
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
    const isQris = $derived(
        paymentMethod?.name?.toLowerCase().includes('qris')
    );
    const qrisData = $derived.by(() => {
        if (!isQris || !latestPayment || !latestPayment.gateway_response) return null;
        try {
            const resp = typeof latestPayment.gateway_response === 'string'
                ? JSON.parse(latestPayment.gateway_response)
                : latestPayment.gateway_response;
            return {
                image: resp?.qris_image ?? '',
                string: resp?.qris_string ?? '',
            };
        } catch (e) {
            return null;
        }
    });

    const gatewayName = $derived(
        paymentMethod?.name?.toLowerCase().includes('midtrans')
            ? 'Midtrans'
            : paymentMethod?.name?.toLowerCase().includes('flip')
              ? 'Flip'
              : paymentMethod?.name?.toLowerCase().includes('qris')
                ? 'QRIS Komerce'
                : paymentMethod?.name?.toLowerCase().includes('komerce')
                  ? 'Komerce Payment'
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
                resp?.checkout_url ??
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

    let trackingHistory = $state([]);
    let loadingTracking = $state(false);
    let trackingError = $state('');

    async function fetchTrackingHistory() {
        if (!transaction.tracking_number) return;
        loadingTracking = true;
        trackingError = '';
        try {
            const resp = await fetch(`/transactions/${transaction.id}/komerce/track`);
            const data = await resp.json();
            if (resp.ok && data.success) {
                trackingHistory = data.history;
            } else {
                trackingError = data.error ?? 'Gagal melacak pengiriman.';
            }
        } catch (e) {
            trackingError = 'Gagal memuat status pelacakan.';
        } finally {
            loadingTracking = false;
        }
    }

    $effect(() => {
        if (transaction.tracking_number) {
            fetchTrackingHistory();
        }
    });

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

    // Retur functions
    function openReturnModal() {
        returnStep = 'form';
        returType = 'refund';
        returReason = '';
        returItems = (transaction.items ?? []).map((item: any) => ({
            transaction_item_id: item.id,
            product_name: item.product_name ?? item.product?.name ?? '',
            variant_name: item.variant_name ?? '',
            quantity: item.quantity,
            returnQty: 1,
            price: item.harga_akhir ?? item.harga_jual ?? 0,
            selected: false,
        }));
        returFiles = [];
        returPreviews = [];
        showReturnModal = true;
    }

    function handleReturnFileChange(e: Event) {
        const input = e.target as HTMLInputElement;
        if (!input.files) return;
        for (const file of Array.from(input.files)) {
            if (returFiles.length >= 5) break;
            returFiles = [...returFiles, file];
            returPreviews = [
                ...returPreviews,
                {
                    url: URL.createObjectURL(file),
                    type: file.type.startsWith('video/') ? 'video' : 'image',
                },
            ];
        }
        input.value = '';
    }

    function removeReturnFile(index: number) {
        URL.revokeObjectURL(returPreviews[index].url);
        returFiles = returFiles.filter((_, i) => i !== index);
        returPreviews = returPreviews.filter((_, i) => i !== index);
    }

    function submitReturn() {
        const selectedItems = returItems.filter(
            (i) => i.selected && i.returnQty > 0,
        );
        if (selectedItems.length === 0) {
            showToast('Pilih minimal 1 produk untuk diretur.', 'error');
            return;
        }
        if (!returReason.trim()) {
            showToast('Alasan retur wajib diisi.', 'error');
            return;
        }
        if (returFiles.length === 0) {
            showToast('Bukti foto/video wajib dilampirkan.', 'error');
            return;
        }

        submittingReturn = true;
        const form = new FormData();
        form.append('type', returType);
        form.append('reason', returReason);
        selectedItems.forEach((item, idx) => {
            form.append(
                `items[${idx}][transaction_item_id]`,
                String(item.transaction_item_id),
            );
            form.append(
                `items[${idx}][quantity_returned]`,
                String(item.returnQty),
            );
        });
        returFiles.forEach((file) => form.append('media[]', file));

        router.post(`/transactions/${transaction.id}/return`, form as any, {
            forceFormData: true,
            onSuccess: () => {
                showReturnModal = false;
                returPreviews.forEach((p) => URL.revokeObjectURL(p.url));
            },
        });
    }

    // Tracking form for return
    let returnCourierName = $state('');
    let returnTrackingNumber = $state('');
    let submittingTracking = $state(false);

    function submitReturnTracking() {
        if (!returnTrackingNumber.trim()) {
            showToast('Nomor resi wajib diisi.', 'error');
            return;
        }
        submittingTracking = true;
        router.post(
            `/returns/${activeReturn.id}/tracking`,
            {
                return_courier_name: returnCourierName,
                return_tracking_number: returnTrackingNumber,
            },
            {
                onSuccess: () => {
                    returnCourierName = '';
                    returnTrackingNumber = '';
                },
                onError: (errors: any) => {
                    const first = Object.values(errors)[0] as string;
                    showToast(first ?? 'Gagal mengirim nomor resi.', 'error');
                },
                onFinish: () => {
                    submittingTracking = false;
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
                            {completingOrder
                                ? 'Memproses...'
                                : 'Pesanan Diterima'}
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
                    {#if canRetur}
                        <button
                            onclick={openReturnModal}
                            class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl border-2 text-xs font-bold transition active:scale-95 hover:bg-orange-50"
                            style="border-color:{secondary}; color:{secondary};"
                        >
                            <i class="ti ti-arrow-back-up text-sm"></i>
                            Ajukan Retur
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
                    <!-- Refund Status Banner -->
                    {#if activeRefundRequest}
                        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden mb-4">
                            <div class="h-1 w-full"></div>
                            <div class="p-5">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold w-fit"
                                          style="background-color: {refundStatusColors[activeRefundRequest.status]?.bg || '#f1f5f9'}; color: {refundStatusColors[activeRefundRequest.status]?.text || '#64748b'};">
                                        <i class="ti ti-info-circle text-xs"></i>
                                        Pengajuan Pembatalan: {refundStatusLabels[activeRefundRequest.status] ?? activeRefundRequest.status}
                                    </span>
                                    <span class="text-xs text-slate-400 self-start sm:self-auto font-medium">{fmtDate(activeRefundRequest.created_at)}</span>
                                </div>
                                <div class="flex items-start gap-4">
                                    <div class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0 mt-0.5"
                                         style="background-color: {primary}15; color: {primary};">
                                        <i class="ti ti-receipt-refund text-xl"></i>
                                    </div>
                                    <div class="flex-grow min-w-0">
                                        <p class="font-bold text-slate-800 text-sm">Pengajuan Pembatalan & Refund</p>
                                        <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                                            Anda telah mengajukan pembatalan untuk pesanan ini dengan alasan: <strong>"{activeRefundRequest.reason}"</strong>.
                                        </p>
                                        <div class="mt-3 bg-slate-50 rounded-xl p-3.5 border border-slate-100 text-xs text-slate-600 space-y-2">
                                            <div class="grid grid-cols-[100px_1fr] gap-2">
                                                <span class="text-slate-400 font-medium">Metode Refund</span>
                                                <span class="text-slate-700 font-semibold">: {activeRefundRequest.refund_method === 'poin' ? 'Poin Toko (Koin)' : 'Transfer Bank'}</span>
                                            </div>
                                            <div class="grid grid-cols-[100px_1fr] gap-2">
                                                <span class="text-slate-400 font-medium">Nominal Refund</span>
                                                <span class="text-slate-700 font-bold" style="color: {primary}">: {fmt(activeRefundRequest.refund_amount)}</span>
                                            </div>
                                            {#if activeRefundRequest.refund_method === 'transfer'}
                                                <div class="grid grid-cols-[100px_1fr] gap-2">
                                                    <span class="text-slate-400 font-medium">Rekening</span>
                                                    <span class="text-slate-750 font-semibold">: {activeRefundRequest.bank_name} - {activeRefundRequest.account_number} <br class="sm:hidden" /><span class="hidden sm:inline"> </span>a.n {activeRefundRequest.account_name}</span>
                                                </div>
                                            {/if}
                                            {#if activeRefundRequest.notes_admin}
                                                <div class="pt-2 border-t border-slate-200 mt-2 grid grid-cols-[100px_1fr] gap-2 text-slate-700">
                                                    <span class="text-slate-400 font-medium">Catatan Admin</span>
                                                    <span class="font-semibold">: {activeRefundRequest.notes_admin}</span>
                                                </div>
                                            {/if}
                                        </div>
                                        <div class="mt-4 flex gap-2">
                                            <Link href={`/refunds/${activeRefundRequest.id}`} class="text-xs font-bold transition flex items-center gap-1 hover:underline" style="color: {primary};">
                                                Detail Refund Saya <i class="ti ti-chevron-right text-xs"></i>
                                            </Link>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    {/if}

                    <!-- Status Banner -->
                    {#if transaction.status === 'batal'}
                        <div
                            class="bg-white rounded-2xl shadow-sm overflow-hidden"
                        >
                            <div class="h-1 w-full"></div>
                            <div class="p-5">
                                <div
                                    class="flex items-center justify-between mb-4"
                                >
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
                                            Pesanan ini telah dibatalkan dan
                                            tidak dapat diproses lebih lanjut.
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
                                <div
                                    class="mt-4 p-3 bg-orange-50 border border-orange-200 rounded-xl flex items-center gap-3"
                                >
                                    <div
                                        class="w-9 h-9 rounded-full bg-orange-100 flex items-center justify-center shrink-0"
                                    >
                                        <i
                                            class="ti ti-truck-delivery text-orange-500 text-base"
                                        ></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p
                                            class="text-xs font-bold text-slate-800 leading-tight"
                                        >
                                            Pesanan sedang dalam pengiriman
                                        </p>
                                        <p
                                            class="text-[10px] text-slate-500 leading-relaxed mt-0.5"
                                        >
                                            Jika pesanan sudah tiba, klik tombol
                                            "Pesanan Diterima".
                                        </p>
                                    </div>
                                </div>
                            {/if}

                            <!-- Order Completed Banner (when status is selesai) -->
                            {#if isCompleted}
                                <div
                                    class="mt-4 p-3 rounded-xl flex items-center gap-3"
                                    style="background:{primary}10; border: 1px solid {primary}30;"
                                >
                                    <div
                                        class="w-9 h-9 rounded-full flex items-center justify-center shrink-0"
                                        style="background:{primary}20;"
                                    >
                                        <i
                                            class="ti ti-circle-check text-base"
                                            style="color:{primary}"
                                        ></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p
                                            class="text-xs font-bold text-slate-800 leading-tight"
                                        >
                                            Pesanan selesai!
                                        </p>
                                        <p
                                            class="text-[10px] text-slate-500 leading-relaxed mt-0.5"
                                        >
                                            Terima kasih telah berbelanja.
                                            Jangan lupa beri ulasan produknya!
                                        </p>
                                    </div>
                                </div>
                            {/if}
                        </div>

                        <!-- Return Request Panel -->
                        {#if activeReturn}
                            <div
                                class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden"
                            >
                                <div class="p-5">
                                    <div
                                        class="flex items-center justify-between flex-wrap gap-2 mb-3"
                                    >
                                        <div class="flex items-center gap-2">
                                            <div
                                                class="w-9 h-9 rounded-xl font-black flex items-center justify-center text-orange-500"
                                                style="background-color: {secondary}15"
                                            >
                                                <i
                                                    class="ti ti-arrow-back-up text-lg"
                                                ></i>
                                            </div>
                                            <div>
                                                <h3
                                                    class="font-bold text-slate-800 text-sm"
                                                >
                                                    Detail Retur Pesanan
                                                </h3>
                                                <p
                                                    class="text-[10px] font-mono text-slate-400"
                                                >
                                                    #{activeReturn.return_number}
                                                </p>
                                            </div>
                                        </div>
                                        <span
                                            class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold"
                                            style="background-color: {returnStatusColors[
                                                activeReturn.status
                                            ]?.bg ??
                                                '#f1f5f9'}; color: {returnStatusColors[
                                                activeReturn.status
                                            ]?.text ?? '#475569'}"
                                        >
                                            {returnStatusLabels[
                                                activeReturn.status
                                            ] ?? activeReturn.status}
                                        </span>
                                    </div>

                                    <div
                                        class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4 bg-slate-50 p-4 rounded-2xl border border-slate-100"
                                    >
                                        <div>
                                            <p
                                                class="text-[9px] font-bold text-slate-400 uppercase tracking-wider"
                                            >
                                                Jenis Solusi
                                            </p>
                                            <p
                                                class="text-xs font-bold text-slate-750 mt-0.5"
                                            >
                                                {activeReturn.type === 'refund'
                                                    ? 'Refund Dana (Pengembalian Uang)'
                                                    : 'Tukar Barang (Penggantian Produk Baru)'}
                                            </p>
                                        </div>
                                        {#if activeReturn.type === 'refund'}
                                            <div>
                                                <p
                                                    class="text-[9px] font-bold text-slate-400 uppercase tracking-wider"
                                                >
                                                    Estimasi Pengembalian
                                                </p>
                                                <p
                                                    class="text-xs font-black mt-0.5"
                                                    style="color: {primary}"
                                                >
                                                    {fmt(
                                                        activeReturn.refund_amount,
                                                    )}
                                                </p>
                                            </div>
                                        {/if}
                                        <div class="md:col-span-2">
                                            <p
                                                class="text-[9px] font-bold text-slate-400 uppercase tracking-wider"
                                            >
                                                Alasan Retur
                                            </p>
                                            <p
                                                class="text-xs text-slate-600 mt-1 leading-relaxed whitespace-pre-line"
                                            >
                                                {activeReturn.reason}
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Return Media Evidence -->
                                    {#if activeReturn.media && activeReturn.media.length > 0}
                                        <div class="mt-4">
                                            <p
                                                class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2"
                                            >
                                                Bukti Foto / Video
                                            </p>
                                            <div class="flex flex-wrap gap-2">
                                                {#each activeReturn.media as media}
                                                    <a
                                                        href={formatImagePath(
                                                            media.file_path,
                                                        )}
                                                        target="_blank"
                                                        class="relative w-14 h-14 rounded-xl border border-slate-200 overflow-hidden bg-slate-50 hover:opacity-85 transition group"
                                                    >
                                                        {#if media.file_type === 'video'}
                                                            <div
                                                                class="w-full h-full flex items-center justify-center bg-slate-900"
                                                            >
                                                                <i
                                                                    class="ti ti-video text-white text-base"
                                                                ></i>
                                                            </div>
                                                        {:else}
                                                            <img
                                                                src={formatImagePath(
                                                                    media.file_path,
                                                                )}
                                                                alt="Bukti Retur"
                                                                class="w-full h-full object-cover"
                                                            />
                                                        {/if}
                                                    </a>
                                                {/each}
                                            </div>
                                        </div>
                                    {/if}

                                    <!-- Admin response notes if available -->
                                    {#if activeReturn.notes_admin}
                                        <div
                                            class="mt-4 p-3 bg-blue-50 border border-blue-100 rounded-xl"
                                        >
                                            <p
                                                class="text-[9px] font-bold text-blue-500 uppercase tracking-wider"
                                            >
                                                Catatan Admin
                                            </p>
                                            <p
                                                class="text-xs text-slate-700 mt-1 leading-relaxed"
                                            >
                                                {activeReturn.notes_admin}
                                            </p>
                                        </div>
                                    {/if}
                                </div>

                                <!-- Customer Input tracking form if return is approved and tracking code is empty -->
                                {#if activeReturn.status === 'disetujui'}
                                    <div
                                        class="bg-blue-50/30 p-5 border-t border-slate-100"
                                    >
                                        <h4
                                            class="text-xs font-bold text-slate-800 flex items-center gap-1.5 mb-2"
                                        >
                                            <i
                                                class="ti ti-truck text-base"
                                                style="color: {primary}"
                                            ></i>
                                            Kirimkan Barang Retur ke Toko
                                        </h4>
                                        <p
                                            class="text-xs text-slate-500 mb-4 leading-relaxed"
                                        >
                                            Pengajuan retur Anda telah <strong
                                                >Disetujui</strong
                                            >. Silakan kirimkan produk yang
                                            ingin diretur ke alamat toko kami
                                            dan masukkan nomor resi pengiriman
                                            di bawah agar kami dapat
                                            memprosesnya segera.
                                        </p>

                                        <form
                                            onsubmit={(e) => {
                                                e.preventDefault();
                                                submitReturnTracking();
                                            }}
                                            class="space-y-3"
                                        >
                                            <div
                                                class="grid grid-cols-1 sm:grid-cols-2 gap-3"
                                            >
                                                <div>
                                                    <label
                                                        class="block text-[10px] font-bold text-slate-500 uppercase mb-1"
                                                        for="return-courier"
                                                        >Nama Kurir / Ekspedisi</label
                                                    >
                                                    <input
                                                        type="text"
                                                        id="return-courier"
                                                        bind:value={
                                                            returnCourierName
                                                        }
                                                        placeholder="Contoh: JNE, J&T, SiCepat"
                                                        class="w-full border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:border-transparent bg-white"
                                                        style="--tw-ring-color: {primary}20"
                                                    />
                                                </div>
                                                <div>
                                                    <label
                                                        class="block text-[10px] font-bold text-slate-500 uppercase mb-1"
                                                        for="return-resi"
                                                        >Nomor Resi Pengiriman <span
                                                            class="text-red-500"
                                                            >*</span
                                                        ></label
                                                    >
                                                    <input
                                                        type="text"
                                                        id="return-resi"
                                                        bind:value={
                                                            returnTrackingNumber
                                                        }
                                                        placeholder="Masukkan nomor resi pengiriman"
                                                        class="w-full border border-slate-200 rounded-xl px-3 py-2 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:border-transparent bg-white font-mono"
                                                        style="--tw-ring-color: {primary}20"
                                                        required
                                                    />
                                                </div>
                                            </div>
                                            <div class="flex justify-end">
                                                <button
                                                    type="submit"
                                                    disabled={submittingTracking ||
                                                        !returnTrackingNumber.trim()}
                                                    class="px-5 py-2.5 rounded-xl font-bold text-white text-xs transition active:scale-95 disabled:opacity-50 flex items-center gap-1.5 shadow-sm"
                                                    style="background: {primary}"
                                                >
                                                    {#if submittingTracking}
                                                        <i
                                                            class="ti ti-loader animate-spin"
                                                        ></i>
                                                        Mengirim...
                                                    {:else}
                                                        <i class="ti ti-send"
                                                        ></i>
                                                        Kirim Resi
                                                    {/if}
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                {:else if activeReturn.return_tracking_number}
                                    <div
                                        class="bg-slate-50 p-4 border-t border-slate-100 flex items-center justify-between text-xs"
                                    >
                                        <div>
                                            <p
                                                class="text-[9px] font-bold text-slate-400 uppercase tracking-wider"
                                            >
                                                Barang Dikirim oleh Anda
                                            </p>
                                            <p
                                                class="font-bold text-slate-750 mt-0.5"
                                            >
                                                {activeReturn.return_courier_name ||
                                                    'Kurir'} -
                                                <span class="font-mono"
                                                    >{activeReturn.return_tracking_number}</span
                                                >
                                            </p>
                                        </div>
                                        <span
                                            class="text-[10px] text-slate-400 font-semibold"
                                        >
                                            Resi telah diinput
                                        </span>
                                    </div>
                                {/if}
                            </div>
                        {/if}

                        <!-- Status History Timeline -->
                        {#if transaction.status_histories && transaction.status_histories.length > 0}
                            <div
                                class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4"
                            >
                                <div class="flex items-center gap-2 mb-4">
                                    <i
                                        class="ti ti-timeline text-base"
                                        style="color:{primary}"
                                    ></i>
                                    <span
                                        class="font-bold text-slate-800 text-sm"
                                        >Riwayat Status</span
                                    >
                                </div>
                                <div class="relative">
                                    <!-- Vertical line -->
                                    <div
                                        class="absolute left-[11px] top-0 bottom-0 w-0.5 bg-slate-100"
                                    ></div>
                                    <div class="space-y-3">
                                        {#each [...transaction.status_histories].reverse() as hist, i}
                                            {@const histColor =
                                                statusColors[hist.status] ??
                                                '#64748b'}
                                            {@const isLatest = i === 0}
                                            <div class="flex gap-3 relative">
                                                <div
                                                    class="rounded-full border-2 border-white flex items-center justify-center shrink-0 z-10 mt-0.5"
                                                    style="background:{isLatest
                                                        ? histColor
                                                        : '#e2e8f0'}; min-width:22px; min-height:22px; max-width:22px; max-height:22px;"
                                                >
                                                    {#if isLatest}
                                                        <i
                                                            class="ti ti-circle-filled text-white"
                                                            style="font-size:6px;"
                                                        ></i>
                                                    {/if}
                                                </div>
                                                <div
                                                    class="flex-1 min-w-0 pb-1"
                                                >
                                                    <div
                                                        class="flex items-center gap-2 flex-wrap"
                                                    >
                                                        <span
                                                            class="text-[10px] font-black px-2 py-0.5 rounded-full uppercase tracking-wider"
                                                            style="background:{histColor}18; color:{histColor};"
                                                        >
                                                            {statusLabels[
                                                                hist.status
                                                            ] ?? hist.status}
                                                        </span>
                                                        <span
                                                            class="text-[10px] text-slate-400"
                                                            >{fmtDate(
                                                                hist.created_at,
                                                            )}</span
                                                        >
                                                    </div>
                                                    {#if hist.description}
                                                        <p
                                                            class="text-xs text-slate-500 mt-1 leading-relaxed"
                                                        >
                                                            {hist.description}
                                                        </p>
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
                                            <Link
                                                href={item.product?.slug
                                                    ? `/products/${item.product.slug}`
                                                    : '#'}
                                                class="shrink-0"
                                            >
                                                <img
                                                    src={formatImagePath(
                                                        item.product_image,
                                                    )}
                                                    alt={item.product_name}
                                                    class="w-14 h-14 object-cover rounded-lg shrink-0 border border-slate-100 hover:opacity-85 transition"
                                                    onerror={(e: any) => {
                                                        e.target.src =
                                                            '/noimage/image.png';
                                                    }}
                                                />
                                            </Link>
                                        {:else}
                                            <div
                                                class="w-14 h-14 rounded-lg bg-slate-100 shrink-0 flex items-center justify-center border border-slate-100"
                                            >
                                                <i
                                                    class="ti ti-package text-2xl text-slate-300"
                                                ></i>
                                            </div>
                                        {/if}
                                        <div class="flex-1 min-w-0">
                                            <Link
                                                href={item.product?.slug
                                                    ? `/products/${item.product.slug}`
                                                    : '#'}
                                                class="text-sm font-semibold text-slate-800 leading-tight whitespace-pre-wrap break-words hover:text-brand-blueRoyal hover:underline transition"
                                            >
                                                {item.product_name}
                                            </Link>
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
                                                <span
                                                    class="text-xs text-slate-500"
                                                    >x{item.quantity}</span
                                                >
                                                <div class="text-right">
                                                    {#if item.diskon_item > 0}
                                                        <p
                                                            class="text-xs text-slate-400 line-through"
                                                        >
                                                            {fmt(
                                                                item.harga_jual,
                                                            )}
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
                                                <span
                                                    class="inline-flex items-center gap-1 text-[10px] font-bold text-green-600 bg-green-50 px-3 py-1.5 rounded-xl border border-green-200"
                                                >
                                                    <i
                                                        class="ti ti-circle-check text-xs"
                                                    ></i>
                                                    Sudah Diulas
                                                </span>
                                            {:else}
                                                <button
                                                    onclick={() =>
                                                        openReviewModal(item)}
                                                    class="inline-flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded-xl text-white transition active:scale-95 hover:opacity-90"
                                                    style="background:{primary}"
                                                >
                                                    <i
                                                        class="ti ti-star text-xs"
                                                    ></i>
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
                                {#if isQris && qrisData}
                                    <div class="flex flex-col items-center justify-center py-4 bg-slate-50/50 border border-slate-100 rounded-2xl">
                                        <p class="text-xs font-bold text-slate-700 mb-2">Scan QRIS Untuk Bayar</p>
                                        
                                        {#if qrisData.image}
                                            <img
                                                src={qrisData.image}
                                                alt="QRIS Code"
                                                class="w-56 h-56 object-contain bg-white p-2 rounded-xl shadow-sm border border-slate-100"
                                            />
                                        {:else}
                                            <div class="w-56 h-56 bg-slate-200 animate-pulse rounded-xl flex items-center justify-center">
                                                <i class="ti ti-qrcode text-4xl text-slate-400"></i>
                                            </div>
                                        {/if}
                                        
                                        <!-- <p class="text-[10px] text-slate-500 mt-2 font-mono text-center max-w-[200px] break-all">
                                            {qrisData.string || 'Kode QRIS sedang dimuat...'}
                                        </p> -->
                                        
                                        <div class="mt-4 pt-3 border-t border-slate-200/60 w-full px-4 flex justify-between items-center text-xs">
                                            <span class="text-slate-500">Total Tagihan:</span>
                                            <span class="font-black text-slate-800" style="color:{primary}">{fmt(transaction.grand_total)}</span>
                                        </div>
                                    </div>
                                {:else}
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
                                {/if}
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
                    <div
                        class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4"
                    >
                        <div class="flex items-center gap-2 mb-3">
                            <i
                                class="ti ti-truck-delivery text-base"
                                style="color:{primary}"
                            ></i>
                            <span class="font-bold text-slate-800 text-sm"
                                >Informasi Pengiriman</span
                            >
                        </div>
                        <div class="space-y-3">
                            <div
                                class="flex justify-between items-center text-xs"
                            >
                                <span class="text-slate-500"
                                    >Kurir / Layanan</span
                                >
                                <span
                                    class="font-bold text-slate-800 uppercase"
                                >
                                    {transaction.courier_name ||
                                        transaction.shipping_courier ||
                                        '-'}
                                    {#if transaction.shipping_service}
                                        ({transaction.shipping_service})
                                    {/if}
                                </span>
                            </div>

                            <div
                                class="pt-2 border-t border-slate-100 flex flex-col gap-1"
                            >
                                <span class="text-xs text-slate-500"
                                    >Nomor Resi</span
                                >
                                {#if transaction.tracking_number}
                                    <div
                                        class="flex items-center justify-between bg-slate-50 px-3 py-2 rounded-xl border border-slate-100 mt-1"
                                    >
                                        <span
                                            class="font-mono font-bold text-sm text-slate-800 select-all"
                                            >{transaction.tracking_number}</span
                                        >
                                        <button
                                            onclick={() => {
                                                navigator.clipboard.writeText(
                                                    transaction.tracking_number,
                                                );
                                                showToast(
                                                    'Nomor resi berhasil disalin!',
                                                    'success',
                                                );
                                            }}
                                            class="p-1 hover:bg-slate-200 rounded-lg text-slate-400 hover:text-slate-600 transition"
                                            title="Salin Resi"
                                        >
                                            <i class="ti ti-copy text-sm"></i>
                                        </button>
                                    </div>
                                {:else}
                                    <div
                                        class="flex items-center gap-2 text-amber-600 bg-amber-50 px-3 py-2 rounded-xl border border-amber-100 mt-1 text-xs"
                                    >
                                        <i class="ti ti-alert-circle text-sm"
                                        ></i>
                                        <span class="font-medium"
                                            >Nomor resi belum tersedia</span
                                        >
                                    </div>
                                {/if}
                            </div>
                        </div>
                    </div>

                    <!-- Komerce Shipment Tracking Timeline -->
                    {#if transaction.tracking_number}
                        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4">
                            <div class="flex items-center gap-2 mb-3">
                                <i class="ti ti-history text-base" style="color:{primary}"></i>
                                <span class="font-bold text-slate-800 text-sm">Status Pengiriman</span>
                            </div>

                            {#if loadingTracking}
                                <div class="space-y-3 py-2">
                                    {#each [1, 2] as _}
                                        <div class="flex gap-3 animate-pulse">
                                            <div class="w-2.5 h-2.5 rounded-full bg-slate-200 shrink-0 mt-1"></div>
                                            <div class="space-y-1.5 w-full">
                                                <div class="h-3 bg-slate-200 rounded-md w-1/3"></div>
                                                <div class="h-3 bg-slate-200 rounded-md w-3/4"></div>
                                            </div>
                                        </div>
                                    {/each}
                                </div>
                            {:else if trackingError}
                                <div class="p-3 bg-slate-50 border border-slate-100 text-slate-500 rounded-xl text-xs flex items-center gap-2">
                                    <i class="ti ti-info-circle text-base"></i>
                                    <span>{trackingError}</span>
                                </div>
                            {:else if trackingHistory && trackingHistory.length > 0}
                                <div class="relative pl-4 border-l-2 border-slate-100 space-y-4 py-1">
                                    {#each trackingHistory as step, idx}
                                        <div class="relative">
                                            <!-- Circle bullet -->
                                            <div
                                                class="absolute -left-[21px] top-1 w-2.5 h-2.5 rounded-full border-2 bg-white"
                                                style={idx === 0
                                                    ? `border-color:${primary}; background-color:${primary}; box-shadow:0 0 8px ${primary}40;`
                                                    : 'border-color:#cbd5e1;'}
                                            ></div>
                                            
                                            <div>
                                                <p
                                                    class="text-[10px] font-bold text-slate-400"
                                                    style={idx === 0 ? `color:${primary};` : ''}
                                                >
                                                    {step.date}
                                                </p>
                                                <p
                                                    class="text-xs mt-0.5 leading-relaxed"
                                                    class:font-semibold={idx === 0}
                                                    class:text-slate-800={idx === 0}
                                                    class:text-slate-600={idx > 0}
                                                >
                                                    {step.desc}
                                                </p>
                                            </div>
                                        </div>
                                    {/each}
                                </div>
                            {:else}
                                <div class="p-3 bg-slate-50 border border-slate-100 text-slate-400 rounded-xl text-xs italic text-center">
                                    Belum ada data perjalanan paket.
                                </div>
                            {/if}
                        </div>
                    {/if}

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
                            {#if transaction.coins_value > 0}
                                <div
                                    class="flex justify-between text-emerald-600"
                                >
                                    <span
                                        >Potongan Poin Saya ({new Intl.NumberFormat(
                                            'id-ID',
                                        ).format(transaction.coins_redeemed)} Poin)</span
                                    >
                                    <span class="font-semibold"
                                        >-{fmt(transaction.coins_value)}</span
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

                        {#if transaction.coins_earned > 0}
                            <div
                                class="mt-3 pt-2.5 border-t border-slate-100 flex items-center gap-1.5 text-xs text-emerald-600 font-bold bg-emerald-50/50 p-2.5 rounded-xl border border-emerald-100/50"
                            >
                                <i
                                    class="ti ti-coins text-base text-amber-500 shrink-0"
                                ></i>
                                <span>
                                    {#if transaction.status === 'selesai'}
                                        Berhasil mendapatkan {new Intl.NumberFormat(
                                            'id-ID',
                                        ).format(transaction.coins_earned)} Poin Toko!
                                    {:else if transaction.status === 'batal'}
                                        Mendapatkan {new Intl.NumberFormat(
                                            'id-ID',
                                        ).format(transaction.coins_earned)} Poin dibatalkan
                                    {:else}
                                        Anda akan mendapatkan {new Intl.NumberFormat(
                                            'id-ID',
                                        ).format(transaction.coins_earned)} Poin setelah
                                        transaksi selesai.
                                    {/if}
                                </span>
                            </div>
                        {/if}

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
            {#if canRetur}
                <button
                    onclick={openReturnModal}
                    class="flex-1 flex items-center justify-center gap-2 py-3 rounded-xl border-2 text-sm font-bold transition active:scale-95"
                    style="border-color:{secondary}; color:{secondary};"
                >
                    <i class="ti ti-arrow-back-up text-base"></i>
                    Ajukan Retur
                </button>
            {/if}
        </div>
    {/if}

    <!-- Upload Proof Modal -->
    {#if showUploadModal}
        <!-- svelte-ignore a11y_no_static_element_interactions -->
        <!-- svelte-ignore a11y_click_events_have_key_events -->
        <div
            role="presentation"
            class="fixed inset-0 z-50 flex items-end lg:items-center justify-center"
            onclick={() => (showUploadModal = false)}
        >
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
            <!-- svelte-ignore a11y_no_static_element_interactions -->
            <!-- svelte-ignore a11y_click_events_have_key_events -->
            <div
                role="presentation"
                class="relative z-10 bg-white w-full lg:max-w-md rounded-t-3xl lg:rounded-2xl p-5"
                onclick={(e: any) => e.stopPropagation()}
            >
                <h3 class="font-bold text-slate-800 mb-4">
                    Upload Bukti Pembayaran
                </h3>

                <!-- svelte-ignore a11y_click_events_have_key_events -->
                <div
                    role="button"
                    tabindex="0"
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
        <!-- svelte-ignore a11y_no_static_element_interactions -->
        <!-- svelte-ignore a11y_click_events_have_key_events -->
        <div
            role="presentation"
            class="fixed inset-0 z-[9999] flex items-end lg:items-center justify-center p-0 lg:p-4"
            onclick={() => (showCancelModal = false)}
        >
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
            <!-- svelte-ignore a11y_no_static_element_interactions -->
            <!-- svelte-ignore a11y_click_events_have_key_events -->
            <div
                role="presentation"
                class="relative z-10 bg-white w-full lg:max-w-lg rounded-t-3xl lg:rounded-2xl p-6 shadow-2xl max-h-[90vh] overflow-y-auto"
                onclick={(e: any) => e.stopPropagation()}
            >
                <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-4">
                    <div class="flex items-center gap-2">
                        <i class="ti ti-alert-triangle text-2xl text-red-500"></i>
                        <h3 class="font-bold text-slate-800 text-lg">
                            {canCancelDirectly ? 'Batalkan Pesanan' : 'Ajukan Pembatalan & Refund'}
                        </h3>
                    </div>
                    <button aria-label="Tutup" onclick={() => (showCancelModal = false)} class="p-1 hover:bg-slate-100 rounded-full transition text-slate-400">
                        <i class="ti ti-x text-lg"></i>
                    </button>
                </div>

                <p class="text-xs text-slate-500 mb-5 leading-relaxed">
                    {canCancelDirectly 
                        ? 'Pesanan yang dibatalkan tidak dapat dikembalikan. Silakan masukkan alasan pembatalan Anda.' 
                        : 'Pesanan Anda sudah dibayar/diproses. Anda dapat mengajukan pembatalan dan dana akan direfund setelah disetujui admin.'}
                </p>

                <div class="space-y-4">
                    <!-- Alasan Pembatalan -->
                    <div class="mb-2">
                        <label class="block text-xs font-bold text-slate-700 mb-1.5" for="cancel-reason">
                            Alasan Pembatalan <span class="text-red-500">*</span>
                        </label>
                        <textarea
                            id="cancel-reason"
                            bind:value={cancelReason}
                            rows="3"
                            placeholder="Contoh: Ingin mengganti produk, salah pilih item, dll."
                            class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-800 resize-none focus:outline-none focus:ring-2 transition"
                            style="--tw-ring-color:{primary}20;"
                            maxlength="500"
                        ></textarea>
                        <p class="text-[10px] text-slate-400 mt-1 text-right">
                            {cancelReason.length}/500
                        </p>
                    </div>

                    <!-- Refund Methods Selection (If paid/processed) -->
                    {#if !canCancelDirectly}
                        <div>
                            <span class="block text-xs font-bold text-slate-700 mb-2">
                                Metode Refund <span class="text-red-500">*</span>
                            </span>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <button
                                    type="button"
                                    onclick={() => (refundMethod = 'transfer')}
                                    class="flex items-center gap-3 p-3.5 rounded-xl border-2 transition text-left cursor-pointer"
                                    style={refundMethod === 'transfer'
                                        ? `border-color:${primary}; background-color:${primary}08;`
                                        : 'border-color:#e2e8f0;'}
                                >
                                    <div
                                        class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0"
                                        style={refundMethod === 'transfer'
                                            ? `background-color:${primary}; color:white;`
                                            : 'background-color:#f1f5f9; color:#94a3b8;'}
                                    >
                                        <i class="ti ti-building-bank text-sm"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-bold text-slate-800 truncate">
                                            Transfer Bank
                                        </p>
                                        <p class="text-[9px] text-slate-400 truncate">
                                            Proses {(page.props as any).refund_transfer_days ?? '3-5'} hari kerja
                                        </p>
                                    </div>
                                    {#if refundMethod === 'transfer'}
                                        <i class="ti ti-circle-check text-base shrink-0" style="color:{primary}"></i>
                                    {/if}
                                </button>

                                {#if (page.props as any).refund_points_enabled}
                                    <button
                                        type="button"
                                        onclick={() => (refundMethod = 'poin')}
                                        class="flex items-center gap-3 p-3.5 rounded-xl border-2 transition text-left cursor-pointer"
                                        style={refundMethod === 'poin'
                                            ? `border-color:${primary}; background-color:${primary}08;`
                                            : 'border-color:#e2e8f0;'}
                                    >
                                        <div
                                            class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0"
                                            style={refundMethod === 'poin'
                                                ? `background-color:${primary}; color:white;`
                                                : 'background-color:#f1f5f9; color:#94a3b8;'}
                                        >
                                            <i class="ti ti-coins text-sm"></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-xs font-bold text-slate-800 truncate">
                                                Koin Toko (Poin)
                                            </p>
                                            <p class="text-[9px] text-slate-400 truncate">
                                                Refund langsung masuk (instan)
                                            </p>
                                        </div>
                                        {#if refundMethod === 'poin'}
                                            <i class="ti ti-circle-check text-base shrink-0" style="color:{primary}"></i>
                                        {/if}
                                    </button>
                                {/if}
                            </div>
                        </div>

                        <!-- Bank Details (If transfer chosen) -->
                        {#if refundMethod === 'transfer'}
                            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 space-y-3">
                                <h4 class="text-xs font-bold text-slate-800">Detail Rekening Bank Penerima</h4>

                                {#if userBankAccounts && userBankAccounts.length > 0}
                                    <div class="space-y-1">
                                        <label class="block text-[10px] font-bold text-slate-600" for="saved-bank-account">
                                            Pilih Rekening Tersimpan
                                        </label>
                                        <select
                                            id="saved-bank-account"
                                            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-xs bg-white text-slate-800 focus:outline-none focus:ring-1 transition"
                                            onchange={(e) => {
                                                const val = e.currentTarget.value;
                                                if (val) {
                                                    const acc = userBankAccounts.find(a => String(a.id) === val);
                                                    if (acc) {
                                                        bankName = acc.bank_name || '';
                                                        accountNumber = acc.account_number || '';
                                                        accountName = acc.account_name || '';
                                                    }
                                                } else {
                                                    bankName = '';
                                                    accountNumber = '';
                                                    accountName = '';
                                                }
                                            }}
                                        >
                                            <option value="">-- Masukkan Rekening Baru --</option>
                                            {#each userBankAccounts as acc}
                                                <option value={acc.id}>
                                                    {acc.bank_name} - {acc.account_number} ({acc.account_name})
                                                </option>
                                            {/each}
                                        </select>
                                    </div>
                                {/if}

                                <div class="grid grid-cols-2 gap-3">
                                    <div class="space-y-1">
                                        <label class="block text-[10px] font-bold text-slate-600" for="bank-name">
                                            Nama Bank <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            id="bank-name"
                                            type="text"
                                            bind:value={bankName}
                                            placeholder="Contoh: BCA"
                                            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-xs bg-white text-slate-800 focus:outline-none focus:ring-1 transition"
                                        />
                                    </div>
                                    <div class="space-y-1">
                                        <label class="block text-[10px] font-bold text-slate-600" for="account-number">
                                            No. Rekening <span class="text-red-500">*</span>
                                        </label>
                                        <input
                                            id="account-number"
                                            type="text"
                                            bind:value={accountNumber}
                                            placeholder="Contoh: 12345678"
                                            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-xs bg-white text-slate-800 focus:outline-none focus:ring-1 transition"
                                        />
                                    </div>
                                </div>
                                <div class="space-y-1">
                                    <label class="block text-[10px] font-bold text-slate-600" for="account-name">
                                        Nama Pemilik Rekening <span class="text-red-500">*</span>
                                    </label>
                                    <input
                                        id="account-name"
                                        type="text"
                                        bind:value={accountName}
                                        placeholder="Sesuai nama di rekening"
                                        class="w-full border border-slate-200 rounded-lg px-3 py-2 text-xs bg-white text-slate-800 focus:outline-none focus:ring-1 transition"
                                    />
                                </div>
                            </div>
                        {/if}

                        <!-- Terms & Conditions (S&K) and Limits -->
                        <div class="p-4 rounded-2xl bg-amber-50/50 border border-amber-100/50 space-y-2">
                            <div class="flex items-center gap-1.5 text-amber-700 text-xs font-bold">
                                <i class="ti ti-info-circle text-sm"></i>
                                <span>Syarat & Ketentuan Refund</span>
                            </div>
                            
                            <div class="text-[10px] text-slate-600 leading-relaxed whitespace-pre-line">
                                {#if refundMethod === 'transfer'}
                                    {(page.props as any).refund_terms_transfer || 'Tidak ada S&K khusus untuk refund transfer bank.'}
                                {:else}
                                    {(page.props as any).refund_terms_points || 'Tidak ada S&K khusus untuk refund koin toko.'}
                                {/if}
                            </div>

                            <div class="h-px bg-amber-100 my-2"></div>

                            <div class="flex justify-between items-center text-[10px] font-semibold">
                                <span class="text-slate-500">Nominal Pesanan:</span>
                                <span class="text-slate-800 font-bold">{fmt(transaction.grand_total)}</span>
                            </div>

                            <div class="flex justify-between items-center text-[10px] font-semibold">
                                <span class="text-slate-500">Minimal Batas Refund:</span>
                                <span class="text-slate-800 font-bold">
                                    {#if refundMethod === 'transfer'}
                                        {fmt((page.props as any).refund_min_amount_transfer ?? 0)}
                                    {:else}
                                        {fmt((page.props as any).refund_min_amount_points ?? 0)}
                                    {/if}
                                </span>
                            </div>

                            {#if isUnderMinLimit}
                                <div class="bg-red-50 text-red-600 p-2 rounded-xl text-[10px] font-bold mt-2 flex items-center gap-1.5">
                                    <i class="ti ti-circle-x text-sm"></i>
                                    <span>Nominal pesanan kurang dari minimal batas refund ({fmt(refundMethod === 'transfer' ? ((page.props as any).refund_min_amount_transfer ?? 0) : ((page.props as any).refund_min_amount_points ?? 0))}).</span>
                                </div>
                            {/if}
                        </div>
                    {/if}
                </div>

                <div class="flex gap-3 mt-6 border-t border-slate-100 pt-4">
                    <button
                        onclick={() => (showCancelModal = false)}
                        class="flex-1 py-3 rounded-xl border-2 border-slate-200 text-slate-700 font-semibold text-sm hover:bg-slate-50 transition"
                    >
                        Kembali
                    </button>
                    <button
                        onclick={submitCancel}
                        disabled={isSubmitCancelDisabled}
                        class="flex-1 py-3 rounded-xl font-bold text-white text-sm transition disabled:opacity-50 bg-red-500 hover:bg-red-600 active:scale-95 flex items-center justify-center gap-1.5"
                    >
                        {#if cancellingOrder}
                            <div class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
                            <span>Memproses...</span>
                        {:else}
                            <span>Ya, Batalkan</span>
                        {/if}
                    </button>
                </div>
            </div>
        </div>
    {/if}

    <!-- Change Payment Method Modal -->
    {#if showChangePaymentModal}
        <!-- svelte-ignore a11y_no_static_element_interactions -->
        <!-- svelte-ignore a11y_click_events_have_key_events -->
        <div
            role="presentation"
            class="fixed inset-0 z-[9999] flex items-end lg:items-center justify-center"
            onclick={() => (showChangePaymentModal = false)}
        >
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
            <!-- svelte-ignore a11y_no_static_element_interactions -->
            <!-- svelte-ignore a11y_click_events_have_key_events -->
            <div
                role="presentation"
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
            role="presentation"
            class="fixed inset-0 z-[9999] flex items-end lg:items-center justify-center"
            onclick={() => (showReviewModal = false)}
        >
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>
            <div
                role="presentation"
                class="relative z-10 bg-white w-full lg:max-w-lg rounded-t-3xl lg:rounded-2xl max-h-[90dvh] overflow-y-auto"
                onclick={(e: any) => e.stopPropagation()}
            >
                <div
                    class="sticky top-0 bg-white px-5 pt-5 pb-3 border-b border-slate-100 flex items-center justify-between"
                >
                    <div>
                        <h3 class="font-bold text-slate-800 text-base">
                            Beri Ulasan
                        </h3>
                        <p
                            class="text-xs text-slate-500 mt-0.5 leading-tight line-clamp-1"
                        >
                            {reviewItem.product_name}
                        </p>
                    </div>
                    <button
                        aria-label="Tutup"
                        onclick={() => (showReviewModal = false)}
                        class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center hover:bg-slate-200 transition"
                    >
                        <i class="ti ti-x text-sm text-slate-600"></i>
                    </button>
                </div>

                <div class="p-5 space-y-5">
                    <div
                        class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl border border-slate-100"
                    >
                        {#if reviewItem.product_image}
                            <img
                                src={formatImagePath(reviewItem.product_image)}
                                alt={reviewItem.product_name}
                                class="w-12 h-12 object-cover rounded-lg shrink-0 border border-slate-100"
                                onerror={(e: any) => {
                                    e.target.src = '/noimage/image.png';
                                }}
                            />
                        {:else}
                            <div
                                class="w-12 h-12 rounded-lg bg-slate-200 shrink-0 flex items-center justify-center"
                            >
                                <i class="ti ti-package text-slate-400 text-lg"
                                ></i>
                            </div>
                        {/if}
                        <div class="flex-1 min-w-0">
                            <p
                                class="text-sm font-bold text-slate-800 leading-tight line-clamp-2"
                            >
                                {reviewItem.product_name}
                            </p>
                            {#if reviewItem.variant_name}
                                <p class="text-xs text-slate-500 mt-0.5">
                                    {reviewItem.variant_name}
                                </p>
                            {/if}
                        </div>
                    </div>

                    <div>
                        <p class="text-xs font-bold text-slate-700 mb-2">
                            Penilaian Produk <span class="text-red-500">*</span>
                        </p>
                        <div class="flex items-center gap-2">
                            {#each [1, 2, 3, 4, 5] as star}
                                <button
                                    aria-label="Rating {star}"
                                    onclick={() => (reviewRating = star)}
                                    onmouseenter={() =>
                                        (reviewHoverRating = star)}
                                    onmouseleave={() => (reviewHoverRating = 0)}
                                    class="text-3xl transition-transform hover:scale-110 active:scale-95"
                                    style="color:{(reviewHoverRating ||
                                        reviewRating) >= star
                                        ? '#f59e0b'
                                        : '#e2e8f0'};"
                                >
                                    <i class="ti ti-star-filled"></i>
                                </button>
                            {/each}
                            {#if reviewRating > 0}
                                <span
                                    class="text-xs font-bold text-amber-500 ml-1"
                                >
                                    {reviewRating === 1
                                        ? 'Buruk'
                                        : reviewRating === 2
                                          ? 'Kurang'
                                          : reviewRating === 3
                                            ? 'Cukup'
                                            : reviewRating === 4
                                              ? 'Bagus'
                                              : 'Sempurna'}
                                </span>
                            {/if}
                        </div>
                    </div>

                    <div>
                        <label
                            class="block text-xs font-bold text-slate-700 mb-2"
                            for="review-comment"
                        >
                            Ulasan <span class="text-slate-400 font-normal"
                                >(opsional)</span
                            >
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
                        <p class="text-[10px] text-slate-400 mt-1 text-right">
                            {reviewComment.length}/1000
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-bold text-slate-700 mb-2">
                            Foto / Video <span
                                class="text-slate-400 font-normal"
                                >(maks. 5 file)</span
                            >
                        </p>

                        {#if reviewPreviews.length > 0}
                            <div class="grid grid-cols-5 gap-2 mb-3">
                                {#each reviewPreviews as preview, idx}
                                    <div
                                        class="relative aspect-square rounded-xl overflow-hidden border border-slate-200 bg-slate-50 group"
                                    >
                                        {#if preview.type === 'video'}
                                            <video
                                                src={preview.url}
                                                class="w-full h-full object-cover"
                                                muted
                                            ></video>
                                            <div
                                                class="absolute inset-0 flex items-center justify-center bg-black/20"
                                            >
                                                <i
                                                    class="ti ti-player-play text-white text-base"
                                                ></i>
                                            </div>
                                        {:else}
                                            <img
                                                src={preview.url}
                                                alt="Preview {idx + 1}"
                                                class="w-full h-full object-cover"
                                            />
                                        {/if}
                                        <button
                                            aria-label="Hapus file"
                                            onclick={() =>
                                                removeReviewFile(idx)}
                                            class="absolute top-1 right-1 w-5 h-5 bg-red-500 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition"
                                        >
                                            <i
                                                class="ti ti-x text-white"
                                                style="font-size:9px;"
                                            ></i>
                                        </button>
                                    </div>
                                {/each}
                                {#if reviewPreviews.length < 5}
                                    <button
                                        aria-label="Tambah file"
                                        onclick={() =>
                                            (
                                                document.getElementById(
                                                    'review-file-input',
                                                ) as HTMLInputElement
                                            )?.click()}
                                        class="aspect-square rounded-xl border-2 border-dashed border-slate-300 flex flex-col items-center justify-center text-slate-400 hover:border-slate-400 hover:text-slate-500 transition"
                                    >
                                        <i class="ti ti-plus text-lg"></i>
                                    </button>
                                {/if}
                            </div>
                        {:else}
                            <button
                                onclick={() =>
                                    (
                                        document.getElementById(
                                            'review-file-input',
                                        ) as HTMLInputElement
                                    )?.click()}
                                class="w-full border-2 border-dashed border-slate-200 rounded-xl py-6 flex flex-col items-center gap-2 text-slate-400 hover:border-slate-300 hover:text-slate-500 transition"
                            >
                                <i class="ti ti-camera text-2xl"></i>
                                <p class="text-xs font-semibold">
                                    Tambahkan foto atau video
                                </p>
                                <p class="text-[10px]">
                                    JPG, PNG, WEBP, MP4, MOV (maks. 20MB/file)
                                </p>
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

    <!-- ===== Return Request Modal ===== -->
    {#if showReturnModal}
        <div
            role="presentation"
            class="fixed inset-0 z-50 flex items-end lg:items-center justify-center font-sans"
            onclick={() => (showReturnModal = false)}
        >
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
            <div
                role="presentation"
                class="relative z-10 bg-white w-full lg:max-w-lg rounded-t-3xl lg:rounded-2xl p-5 max-h-[90dvh] overflow-y-auto"
                onclick={(e: any) => e.stopPropagation()}
            >
                <div
                    class="flex items-center justify-between mb-4 pb-2 border-b border-slate-100"
                >
                    <h3 class="font-outfit font-black text-slate-800 text-base">
                        Pengajuan Retur Produk
                    </h3>
                    <button
                        aria-label="Tutup"
                        onclick={() => (showReturnModal = false)}
                        class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center hover:bg-slate-200 transition"
                    >
                        <i class="ti ti-x text-sm text-slate-600"></i>
                    </button>
                </div>

                <div class="space-y-4">
                    <!-- Step 1: Items Selection -->
                    {#if returnStep === 'form'}
                        <div class="space-y-3">
                            <p
                                class="text-xs font-bold text-slate-500 uppercase tracking-wider"
                            >
                                1. Pilih Produk & Jumlah Retur
                            </p>

                            <div
                                class="space-y-2.5 max-h-[220px] overflow-y-auto divide-y divide-slate-100 pr-1"
                            >
                                {#each returItems as item, idx}
                                    <div
                                        class="pt-2.5 first:pt-0 flex items-start gap-3"
                                    >
                                        <input
                                            type="checkbox"
                                            bind:checked={item.selected}
                                            class="w-4 h-4 rounded mt-1.5 accent-current"
                                            style="accent-color: {primary}"
                                        />
                                        <div class="flex-grow min-w-0">
                                            <p
                                                class="text-xs font-bold text-slate-800 leading-snug line-clamp-2"
                                            >
                                                {item.product_name}
                                            </p>
                                            {#if item.variant_name}
                                                <p
                                                    class="text-[10px] text-slate-400 font-medium mt-0.5"
                                                >
                                                    Varian: {item.variant_name}
                                                </p>
                                            {/if}
                                            <p
                                                class="text-xs font-black text-slate-900 mt-1"
                                            >
                                                Rp {new Intl.NumberFormat(
                                                    'id-ID',
                                                ).format(item.price)}
                                            </p>
                                        </div>

                                        {#if item.selected}
                                            <!-- Qty selector -->
                                            <div
                                                class="flex items-center border border-slate-200 rounded-lg shrink-0 overflow-hidden bg-slate-50"
                                            >
                                                <button
                                                    type="button"
                                                    disabled={item.returnQty <=
                                                        1}
                                                    onclick={() =>
                                                        item.returnQty--}
                                                    class="px-2.5 py-1 text-xs font-black text-slate-500 hover:bg-slate-100 transition disabled:opacity-30"
                                                >
                                                    -
                                                </button>
                                                <span
                                                    class="px-2 text-xs font-bold text-slate-700 min-w-[20px] text-center"
                                                >
                                                    {item.returnQty}
                                                </span>
                                                <button
                                                    type="button"
                                                    disabled={item.returnQty >=
                                                        item.quantity}
                                                    onclick={() =>
                                                        item.returnQty++}
                                                    class="px-2.5 py-1 text-xs font-black text-slate-500 hover:bg-slate-100 transition disabled:opacity-30"
                                                >
                                                    +
                                                </button>
                                            </div>
                                        {/if}
                                    </div>
                                {/each}
                            </div>

                            <!-- Choose Return Type -->
                            <div class="pt-3 border-t border-slate-100">
                                <p
                                    class="block text-xs font-bold text-slate-650 mb-2"
                                >
                                    Pilih Solusi Retur
                                </p>
                                <div class="grid grid-cols-2 gap-3">
                                    <label
                                        class="flex items-center gap-2 p-3 rounded-xl border cursor-pointer hover:bg-slate-50 transition {returType ===
                                        'refund'
                                            ? 'border-brand-blueRoyal bg-brand-blueRoyal/5'
                                            : 'border-slate-200'}"
                                        style={returType === 'refund'
                                            ? `border-color: ${primary}; background-color: ${primary}08;`
                                            : ''}
                                    >
                                        <input
                                            type="radio"
                                            name="retur_type"
                                            value="refund"
                                            bind:group={returType}
                                            class="w-4 h-4 accent-current"
                                            style="accent-color: {primary}"
                                        />
                                        <div class="min-w-0">
                                            <p
                                                class="text-xs font-bold text-slate-700 leading-tight"
                                            >
                                                Refund Dana
                                            </p>
                                            <p
                                                class="text-[9px] text-slate-400 mt-0.5 leading-snug"
                                            >
                                                Kembali uang
                                            </p>
                                        </div>
                                    </label>

                                    <label
                                        class="flex items-center gap-2 p-3 rounded-xl border cursor-pointer hover:bg-slate-50 transition {returType ===
                                        'penggantian_barang'
                                            ? 'border-brand-blueRoyal bg-brand-blueRoyal/5'
                                            : 'border-slate-200'}"
                                        style={returType ===
                                        'penggantian_barang'
                                            ? `border-color: ${primary}; background-color: ${primary}08;`
                                            : ''}
                                    >
                                        <input
                                            type="radio"
                                            name="retur_type"
                                            value="penggantian_barang"
                                            bind:group={returType}
                                            class="w-4 h-4 accent-current"
                                            style="accent-color: {primary}"
                                        />
                                        <div class="min-w-0">
                                            <p
                                                class="text-xs font-bold text-slate-700 leading-tight"
                                            >
                                                Tukar Barang
                                            </p>
                                            <p
                                                class="text-[9px] text-slate-400 mt-0.5 leading-snug"
                                            >
                                                Kirim barang baru
                                            </p>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-3 pt-4 border-t border-slate-100">
                            <button
                                onclick={() => (showReturnModal = false)}
                                class="flex-1 py-3 rounded-xl border-2 border-slate-200 text-slate-700 font-semibold text-sm hover:bg-slate-50 transition"
                            >
                                Batal
                            </button>
                            <button
                                onclick={() => {
                                    const selected = returItems.some(
                                        (i) => i.selected,
                                    );
                                    if (!selected) {
                                        showToast(
                                            'Pilih minimal 1 produk untuk diretur.',
                                            'error',
                                        );
                                    } else {
                                        returnStep = 'items';
                                    }
                                }}
                                class="flex-1 py-3 rounded-xl font-bold text-white text-sm transition active:scale-95 shadow-md"
                                style="background:{primary}"
                            >
                                Lanjutkan
                            </button>
                        </div>

                        <!-- Step 2: Reason & Upload Evidence -->
                    {:else if returnStep === 'items'}
                        <div class="space-y-4">
                            <p
                                class="text-xs font-bold text-slate-500 uppercase tracking-wider"
                            >
                                2. Alasan & Bukti Retur
                            </p>

                            <!-- Textarea Reason -->
                            <div>
                                <label
                                    class="block text-xs font-bold text-slate-700 mb-2"
                                    for="retur-reason"
                                >
                                    Alasan Retur secara Detail <span
                                        class="text-red-500">*</span
                                    >
                                </label>
                                <textarea
                                    id="retur-reason"
                                    bind:value={returReason}
                                    placeholder="Jelaskan alasan mengapa Anda mengembalikan barang ini (misal: ukuran salah, jahitan robek, pecah, dsb)..."
                                    rows="4"
                                    class="w-full border border-slate-200 rounded-xl px-4 py-3 text-xs text-slate-800 focus:outline-none focus:ring-2 transition"
                                    style="--tw-ring-color: {primary}40"
                                    maxlength="1000"
                                ></textarea>
                                <p
                                    class="text-[10px] text-slate-400 text-right mt-1 font-semibold"
                                >
                                    {returReason.length}/1000 karakter
                                </p>
                            </div>

                            <!-- File uploads -->
                            <div>
                                <p
                                    class="block text-xs font-bold text-slate-700 mb-2"
                                >
                                    Lampirkan Foto/Video Bukti <span
                                        class="text-red-500">*</span
                                    >
                                </p>
                                <p
                                    class="text-[10px] text-slate-400 mb-3 leading-relaxed"
                                >
                                    Wajib melampirkan minimal 1 foto/video
                                    kondisi produk yang ingin Anda retur.
                                    Maksimal 5 file (maks 50MB per file).
                                </p>

                                <div class="flex flex-wrap gap-2">
                                    <!-- Previews list -->
                                    {#each returPreviews as file, index}
                                        <div
                                            class="relative w-16 h-16 rounded-xl border border-slate-200 overflow-hidden shrink-0 bg-slate-50"
                                        >
                                            {#if file.type === 'video'}
                                                <div
                                                    class="w-full h-full flex items-center justify-center bg-slate-800"
                                                >
                                                    <i
                                                        class="ti ti-video text-white text-lg"
                                                    ></i>
                                                </div>
                                            {:else}
                                                <img
                                                    src={file.url}
                                                    alt="Preview"
                                                    class="w-full h-full object-cover"
                                                />
                                            {/if}
                                            <button
                                                type="button"
                                                onclick={() =>
                                                    removeReturnFile(index)}
                                                class="absolute top-0.5 right-0.5 w-4 h-4 rounded-full bg-black/60 text-white flex items-center justify-center hover:bg-black transition"
                                                aria-label="Hapus file"
                                            >
                                                <i class="ti ti-x text-[8px]"
                                                ></i>
                                            </button>
                                        </div>
                                    {/each}

                                    <!-- Upload button -->
                                    {#if returFiles.length < 5}
                                        <label
                                            class="w-16 h-16 rounded-xl border-2 border-dashed border-slate-300 hover:border-slate-400 transition flex flex-col items-center justify-center cursor-pointer shrink-0 bg-slate-50"
                                        >
                                            <input
                                                type="file"
                                                multiple
                                                accept="image/*,video/*"
                                                onchange={handleReturnFileChange}
                                                class="hidden"
                                            />
                                            <i
                                                class="ti ti-camera text-base text-slate-400"
                                            ></i>
                                            <span
                                                class="text-[8px] font-bold text-slate-400 mt-1"
                                                >Upload</span
                                            >
                                        </label>
                                    {/if}
                                </div>
                            </div>
                        </div>

                        <div
                            class="flex gap-3 pt-4 border-t border-slate-100 mt-2"
                        >
                            <button
                                onclick={() => (returnStep = 'form')}
                                class="flex-1 py-3 rounded-xl border-2 border-slate-200 text-slate-700 font-semibold text-sm hover:bg-slate-50 transition"
                            >
                                Kembali
                            </button>
                            <button
                                onclick={submitReturn}
                                disabled={submittingReturn}
                                class="flex-1 py-3 rounded-xl font-bold text-white text-sm transition disabled:opacity-50 active:scale-95 shadow-md"
                                style="background:{primary}"
                            >
                                {#if submittingReturn}
                                    <i
                                        class="ti ti-loader animate-spin text-sm mr-1.5"
                                    ></i>
                                    Mengirim...
                                {:else}
                                    Kirim Retur
                                {/if}
                            </button>
                        </div>
                    {/if}
                </div>
            </div>
        </div>
    {/if}
</StorefrontLayout>
