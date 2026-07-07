<script lang="ts">
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { page, router } from '@inertiajs/svelte';
    import { showToast } from '@/utils/toast';

    let {
        transaction,
        statusLabels = {},
        storeName = '',
        storeLogo = '',
        biteshipEnabled = false,
    } = $props();

    const primary = $derived(
        (page.props as any).theme?.primary_color ?? '#0c4cb4',
    );
    const secondary = $derived(
        (page.props as any).theme?.secondary_color ?? '#fa7315',
    );
    const storeSettings = $derived((page.props as any).settings ?? {});

    const deliverySteps = [
        {
            key: 'dikemas',
            label: 'Dikemas',
            icon: 'ti-package',
            desc: 'Paket sedang dikemas',
        },
        {
            key: 'out_for_pickup',
            label: 'Dijemput',
            icon: 'ti-building-warehouse',
            desc: 'Paket sudah dipick oleh kurir',
        },
        {
            key: 'dikirim',
            label: 'Dikirim',
            icon: 'ti-truck',
            desc: 'Dalam perjalanan ke tujuan',
        },
        {
            key: 'arrived',
            label: 'Tiba',
            icon: 'ti-map-pin-check',
            desc: 'Paket telah tiba di tujuan',
        },
        {
            key: 'selesai',
            label: 'Selesai',
            icon: 'ti-circle-check',
            desc: 'Pesanan selesai dikonfirmasi pelanggan',
        },
    ];

    const storeCourierCurrentStatus = $derived(
        transaction.status === 'selesai'
            ? 'selesai'
            : transaction.delivery_arrived_at
              ? 'arrived'
              : transaction.status,
    );

    function getStoreCourierStepState(
        stepKey: string,
    ): 'done' | 'active' | 'pending' {
        const statusOrder = [
            'dikemas',
            'out_for_pickup',
            'dikirim',
            'arrived',
            'selesai',
        ];
        const currentIdx = statusOrder.indexOf(storeCourierCurrentStatus);
        const stepIdx = statusOrder.indexOf(stepKey);

        if (stepIdx < currentIdx) {
            return 'done';
        }
        if (stepIdx === currentIdx) {
            return 'active';
        }
        return 'pending';
    }

    // svelte-ignore state_referenced_locally
    let newStatus = $state(transaction.status);
    let cancelReason = $state('');
    let showStatusModal = $state(false);
    let showRejectModal = $state(false);
    let rejectNotes = $state('');
    let isUpdating = $state(false);

    // Resi tracking modal state and method
    let showResiModal = $state(false);

    let itemDigitalNotes = $state<Record<string, string>>({});
    let savingDigitalNote = $state<Record<string, boolean>>({});

    $effect(() => {
        if (transaction?.items) {
            transaction.items.forEach((item: any) => {
                if (item.product?.is_digital) {
                    if (itemDigitalNotes[item.id] === undefined) {
                        itemDigitalNotes[item.id] = item.note || '';
                    }
                }
            });
        }
    });

    function saveDigitalNote(itemId: string) {
        const noteValue = itemDigitalNotes[itemId] || '';
        savingDigitalNote[itemId] = true;
        router.post(
            `/admin/transactions/${transaction.id}/items/${itemId}/note`,
            {
                note: noteValue,
            },
            {
                onSuccess: () => {
                    showToast(
                        'Informasi produk digital berhasil disimpan & email terkirim!',
                        'success',
                    );
                },
                onError: (err: any) => {
                    const first = Object.values(err)[0] as string;
                    showToast(first ?? 'Gagal menyimpan catatan.', 'error');
                },
                onFinish: () => {
                    savingDigitalNote[itemId] = false;
                },
            },
        );
    }

    // Image/Video Gallery Preview Modal
    let showPreviewModal = $state(false);
    let previewItems = $state<string[]>([]);
    let previewIndex = $state(0);

    function openPreview(items: string[], index: number) {
        previewItems = items;
        previewIndex = index;
        showPreviewModal = true;
    }

    function closePreview() {
        showPreviewModal = false;
    }

    function nextPreview() {
        if (previewItems.length > 0) {
            previewIndex = (previewIndex + 1) % previewItems.length;
        }
    }

    function prevPreview() {
        if (previewItems.length > 0) {
            previewIndex =
                (previewIndex - 1 + previewItems.length) % previewItems.length;
        }
    }

    function handleKeydown(event: KeyboardEvent) {
        if (!showPreviewModal) return;
        if (event.key === 'Escape') closePreview();
        if (event.key === 'ArrowRight') nextPreview();
        if (event.key === 'ArrowLeft') prevPreview();
    }

    function isVideo(path: string): boolean {
        const ext = path.split('.').pop()?.toLowerCase();
        return ['mp4', 'webm', 'ogg', 'mov', 'm4v'].includes(ext ?? '');
    }
    // svelte-ignore state_referenced_locally
    let resiInput = $state(transaction.tracking_number ?? '');
    // svelte-ignore state_referenced_locally
    let courierInput = $state(transaction.courier_name ?? '');
    let submittingResi = $state(false);

    function submitResi() {
        if (!resiInput.trim()) {
            showToast('Nomor resi tidak boleh kosong.', 'error');
            return;
        }
        submittingResi = true;
        router.post(
            `/admin/transactions/${transaction.id}/tracking`,
            {
                tracking_number: resiInput.trim(),
                courier_name: courierInput.trim() || null,
            },
            {
                onSuccess: () => {
                    showToast(
                        'Nomor resi berhasil disimpan. Status diubah ke Dikirim.',
                        'success',
                    );
                    showResiModal = false;
                },
                onError: (err: any) => {
                    const first = Object.values(err)[0] as string;
                    showToast(first ?? 'Gagal menyimpan nomor resi.', 'error');
                },
                onFinish: () => {
                    submittingResi = false;
                },
            },
        );
    }

    const paymentMethod = $derived(
        transaction.payment_method ?? transaction.paymentMethod,
    );
    const customerAddress = $derived(
        transaction.customer_address ?? transaction.customerAddress,
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

    const statusSteps = [
        { key: 'belum_bayar', label: 'Belum Bayar', icon: 'ti-cash' },
        { key: 'menunggu', label: 'Menunggu', icon: 'ti-clock' },
        { key: 'diproses', label: 'Diproses', icon: 'ti-settings' },
        { key: 'dikemas', label: 'Dikemas', icon: 'ti-package' },
        {
            key: 'out_for_pickup',
            label: 'Out for Pickup',
            icon: 'ti-truck-delivery',
        },
        { key: 'dikirim', label: 'Dikirim', icon: 'ti-truck' },
        { key: 'selesai', label: 'Selesai', icon: 'ti-circle-check' },
    ];

    const statusIndex = $derived(
        transaction.status === 'batal'
            ? -1
            : statusSteps.findIndex((s) => s.key === transaction.status),
    );

    const statusColors: Record<string, { bg: string; text: string }> = {
        belum_bayar: { bg: '#fef3c7', text: '#92400e' },
        menunggu: { bg: '#dbeafe', text: '#1e40af' },
        diproses: { bg: '#ede9fe', text: '#5b21b6' },
        dikemas: { bg: '#cffafe', text: '#0e7490' },
        out_for_pickup: { bg: '#fef3c7', text: '#b45309' }, // Amber-100 / Amber-700
        dikirim: { bg: '#ffedd5', text: '#9a3412' },
        selesai: { bg: '#dcfce7', text: '#166534' },
        batal: { bg: '#fee2e2', text: '#991b1b' },
    };

    const latestPayment = $derived(
        transaction.payments?.[transaction.payments.length - 1] ?? null,
    );

    function updateStatus() {
        isUpdating = true;
        router.post(
            `/admin/transactions/${transaction.id}/status`,
            {
                status: newStatus,
                cancel_reason: cancelReason,
            },
            {
                onSuccess: () => {
                    showToast(
                        'Status transaksi berhasil diperbarui.',
                        'success',
                    );
                    showStatusModal = false;
                },
                onError: () => {
                    showToast('Gagal memperbarui status.', 'error');
                },
                onFinish: () => {
                    isUpdating = false;
                },
            },
        );
    }

    function confirmPickup() {
        isUpdating = true;
        router.post(
            `/admin/transactions/${transaction.id}/status`,
            {
                status: 'selesai',
            },
            {
                onSuccess: () => {
                    showToast(
                        'Pesanan berhasil diserahterimakan (diambil)!',
                        'success',
                    );
                },
                onError: () => {
                    showToast('Gagal memproses pengambilan.', 'error');
                },
                onFinish: () => {
                    isUpdating = false;
                },
            },
        );
    }

    function updateTransactionStatus(targetStatus: string, successMsg: string) {
        isUpdating = true;
        router.post(
            `/admin/transactions/${transaction.id}/status`,
            {
                status: targetStatus,
            },
            {
                onSuccess: () => {
                    showToast(successMsg, 'success');
                },
                onError: () => {
                    showToast('Gagal mengubah status transaksi.', 'error');
                },
                onFinish: () => {
                    isUpdating = false;
                },
            },
        );
    }

    let storeBookingCode = $state('');
    let storeTrackingNumber = $state('');
    let customDeliveryLog = $state('');
    let showStoreResiForm = $state(true);
    let storeActionLoading = $state(false);

    $effect(() => {
        storeBookingCode = transaction.booking_code ?? '';
        storeTrackingNumber = transaction.tracking_number ?? '';
        showStoreResiForm = !transaction.tracking_number;
    });

    function generateStoreBooking() {
        storeActionLoading = true;
        const autoCode = 'ST-' + transaction.transaction_number;
        const autoResi =
            'RSI-' +
            transaction.transaction_number.replace('TRX-', '') +
            '-' +
            new Date().toISOString().slice(0, 10).replace(/-/g, '');
        router.post(
            `/admin/transactions/${transaction.id}/tracking`,
            {
                booking_code: autoCode,
                tracking_number: autoResi,
                status: 'dikemas',
            },
            {
                onSuccess: () => {
                    showToast(
                        'Kode booking & Resi kurir toko berhasil dibuat!',
                        'success',
                    );
                },
                onError: () => {
                    showToast('Gagal membuat kode booking.', 'error');
                },
                onFinish: () => {
                    storeActionLoading = false;
                },
            },
        );
    }

    function generateStoreResi() {
        const autoResi =
            'RSI-' +
            transaction.transaction_number.replace('TRX-', '') +
            '-' +
            new Date().toISOString().slice(0, 10).replace(/-/g, '');
        storeTrackingNumber = autoResi;
    }

    function saveStoreTracking() {
        if (!storeTrackingNumber.trim()) {
            showToast('Nomor resi tidak boleh kosong.', 'warning');
            return;
        }
        storeActionLoading = true;
        router.post(
            `/admin/transactions/${transaction.id}/tracking`,
            {
                tracking_number: storeTrackingNumber,
            },
            {
                onSuccess: () => {
                    showToast(
                        'Nomor resi kurir toko berhasil disimpan!',
                        'success',
                    );
                    showStoreResiForm = false;
                },
                onError: () => {
                    showToast('Gagal menyimpan nomor resi.', 'error');
                },
                onFinish: () => {
                    storeActionLoading = false;
                },
            },
        );
    }

    function addCustomLog() {
        if (!customDeliveryLog.trim()) {
            showToast('Catatan riwayat tidak boleh kosong.', 'warning');
            return;
        }
        storeActionLoading = true;
        router.post(
            `/admin/transactions/${transaction.id}/delivery-history`,
            {
                description: customDeliveryLog,
            },
            {
                onSuccess: () => {
                    showToast(
                        'Riwayat pengiriman berhasil ditambahkan!',
                        'success',
                    );
                    customDeliveryLog = '';
                },
                onError: () => {
                    showToast('Gagal menambahkan riwayat.', 'error');
                },
                onFinish: () => {
                    storeActionLoading = false;
                },
            },
        );
    }

    function confirmPayment() {
        isUpdating = true;
        router.post(
            `/admin/transactions/${transaction.id}/confirm-payment`,
            {},
            {
                onSuccess: () => {
                    showToast('Pembayaran berhasil dikonfirmasi.', 'success');
                },
                onError: () => {
                    showToast('Gagal mengkonfirmasi pembayaran.', 'error');
                },
                onFinish: () => {
                    isUpdating = false;
                },
            },
        );
    }

    function rejectPayment() {
        if (!rejectNotes.trim()) {
            showToast('Masukkan alasan penolakan.', 'error');
            return;
        }
        isUpdating = true;
        router.post(
            `/admin/transactions/${transaction.id}/reject-payment`,
            {
                notes: rejectNotes,
            },
            {
                onSuccess: () => {
                    showToast('Pembayaran ditolak.', 'success');
                    showRejectModal = false;
                    rejectNotes = '';
                },
                onError: () => {
                    showToast('Gagal menolak pembayaran.', 'error');
                },
                onFinish: () => {
                    isUpdating = false;
                },
            },
        );
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

    function fmtRp(val: any): string {
        const num = parseFloat(val);
        if (isNaN(num) || num === 0) return 'Rp 0';
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
        }).format(num);
    }

    let pickupTime = $state('');
    let showPickupModal = $state(false);
    let vehicleType = $state('motorcycle');
    let bookingLoading = $state(false);
    let trackingTimeline = $state<any[]>([]);
    let trackingLoading = $state(false);
    let trackingErr = $state('');

    function translateBiteshipDesc(desc) {
        if (!desc) return '';
        const descLower = desc.toLowerCase();

        if (descLower.includes('confirmed') && descLower.includes('notified')) {
            return 'Pesanan kurir terkonfirmasi. Kurir telah dinotifikasi untuk menjemput paket.';
        }
        if (
            descLower.includes('allocated') &&
            descLower.includes('ready to pick up')
        ) {
            return 'Kurir telah dialokasikan dan bersiap untuk menjemput paket.';
        }
        if (descLower.includes('on the way to pick up')) {
            return 'Kurir sedang dalam perjalanan menuju lokasi penjemputan.';
        }
        if (
            descLower.includes('picked') ||
            descLower.includes('dijemput') ||
            descLower.includes('diserahkan')
        ) {
            return 'Paket telah diambil oleh kurir.';
        }
        if (
            descLower.includes('in transit') ||
            descLower.includes('on the way to the destination')
        ) {
            return 'Paket sedang dalam perjalanan ke alamat penerima.';
        }
        if (
            descLower.includes('dropping off') ||
            descLower.includes('on the way to customer')
        ) {
            return 'Kurir sedang dalam perjalanan mengirimkan paket ke pelanggan.';
        }
        if (
            descLower.includes('delivered') ||
            descLower.includes('diterima') ||
            descLower.includes('sampai')
        ) {
            return 'Paket telah berhasil diterima oleh penerima.';
        }
        if (descLower.includes('returned') || descLower.includes('kembali')) {
            return 'Paket berhasil dikembalikan ke pengirim.';
        }
        if (
            descLower.includes('cancelled') ||
            descLower.includes('batal') ||
            descLower.includes('canceled')
        ) {
            return 'Pengiriman dibatalkan.';
        }
        if (descLower.includes('rejected') || descLower.includes('ditolak')) {
            return 'Pengiriman ditolak.';
        }
        if (
            descLower.includes('courier not found') ||
            descLower.includes('couriernotfound')
        ) {
            return 'Pengiriman dibatalkan karena tidak ada kurir yang tersedia.';
        }
        if (
            descLower.includes('on hold') ||
            descLower.includes('ditangguhkan')
        ) {
            return 'Pengiriman ditangguhkan sementara.';
        }
        if (descLower.includes('disposed')) {
            return 'Paket berhasil dimusnahkan.';
        }

        return desc;
    }

    async function loadAdminTracking() {
        if (!transaction.tracking_number) return;
        trackingLoading = true;
        trackingErr = '';
        try {
            const resp = await fetch(
                `/admin/transactions/${transaction.id}/komerce/track`,
            );
            const data = await resp.json();
            if (resp.ok && data.success) {
                trackingTimeline = (data.history || [])
                    .map((step) => ({
                        ...step,
                        desc: translateBiteshipDesc(step.desc),
                    }))
                    .reverse();
            } else {
                trackingErr = data.error ?? 'Gagal memuat history tracking.';
            }
        } catch {
            trackingErr = 'Gagal melacak pengiriman.';
        } finally {
            trackingLoading = false;
        }
    }

    $effect(() => {
        if (transaction.tracking_number) {
            loadAdminTracking();
        }
    });

    function storeKomerceShipment() {
        bookingLoading = true;
        router.post(
            `/admin/transactions/${transaction.id}/komerce/store`,
            {},
            {
                onError: (err) => {
                    const first = Object.values(err)[0] as string;
                    showToast(first ?? 'Gagal booking pengiriman.', 'error');
                },
                onFinish: () => {
                    bookingLoading = false;
                },
            },
        );
    }

    function cancelKomerceShipment() {
        bookingLoading = true;
        router.post(
            `/admin/transactions/${transaction.id}/komerce/cancel`,
            {},
            {
                onError: (err) => {
                    const first = Object.values(err)[0] as string;
                    showToast(first ?? 'Gagal membatalkan booking.', 'error');
                },
                onFinish: () => {
                    bookingLoading = false;
                },
            },
        );
    }

    function requestPickupKomerce() {
        if (biteshipEnabled) {
            bookingLoading = true;
            router.post(
                `/admin/transactions/${transaction.id}/komerce/pickup`,
                {},
                {
                    onSuccess: () => {
                        showPickupModal = false;
                        showToast(
                            'Request pickup Biteship berhasil diproses.',
                            'success',
                        );
                    },
                    onError: (err: any) => {
                        const first = Object.values(err)[0] as string;
                        showToast(
                            first ?? 'Gagal mengajukan request pickup.',
                            'error',
                        );
                    },
                    onFinish: () => {
                        bookingLoading = false;
                    },
                },
            );
            return;
        }
        if (!pickupTime) {
            showToast('Silakan pilih waktu pickup.', 'error');
            return;
        }
        bookingLoading = true;
        router.post(
            `/admin/transactions/${transaction.id}/komerce/pickup`,
            {
                pickup_time: pickupTime,
                vehicle_type: vehicleType,
            },
            {
                onSuccess: () => {
                    showPickupModal = false;
                },
                onError: (err: any) => {
                    const first = Object.values(err)[0] as string;
                    showToast(
                        first ?? 'Gagal mengajukan request pickup.',
                        'error',
                    );
                },
                onFinish: () => {
                    bookingLoading = false;
                },
            },
        );
    }

    // ── Extra state for redesigned template
    let previewImageUrl = $state<string | null>(null);

    const formattedAddress = $derived.by(() => {
        const addr = transaction.customer_address ?? (transaction as any).customerAddress;
        if (!addr) return null;
        if (typeof addr === 'string') return addr;
        const parts = [
            addr.receiver_name ? `Penerima: ${addr.receiver_name} (${addr.phone_number ?? ''})` : null,
            addr.full_address,
            addr.district_name,
            addr.regency_name,
            addr.province_name,
            addr.postal_code
        ].filter(Boolean);
        return parts.join(', ');
    });

    const paymentMethodLabel = $derived.by(() => {
        const pm = transaction.payment_method ?? (transaction as any).paymentMethod;
        if (!pm) return null;
        return typeof pm === 'object' ? pm.name : pm;
    });
</script>

<svelte:head>
    <title>Transaksi {transaction.transaction_number} — Admin</title>
</svelte:head>

<AdminLayout>
    <main class="w-full max-w-[1400px] mx-auto px-4 sm:px-6 py-6">

        <!-- Page header -->
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <!-- Left: back + title -->
            <div class="flex items-center gap-3">
                <a
                    href="/admin/transactions"
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-slate-200 text-slate-500 transition-colors hover:bg-slate-50 hover:text-slate-800"
                    aria-label="Kembali"
                >
                    <i class="ti ti-arrow-left text-sm"></i>
                </a>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="font-mono text-lg font-bold tracking-tight text-slate-900">
                            {transaction.transaction_number}
                        </h1>
                        <span
                            class="rounded-md px-2 py-0.5 text-[11px] font-semibold"
                            style="background-color: {(statusColors[transaction.status] ?? { bg: '#f1f5f9', text: '#475569' }).bg}; color: {(statusColors[transaction.status] ?? { bg: '#f1f5f9', text: '#475569' }).text};"
                        >
                            {statusLabels[transaction.status] ?? transaction.status}
                        </span>
                    </div>
                    <p class="mt-0.5 text-xs text-slate-400">{fmtDate(transaction.created_at)}</p>
                </div>
            </div>

            <!-- Right: actions -->
            <div class="flex flex-wrap items-center gap-2">
                <!-- Print invoice -->
                <a
                    href="/admin/transactions/{transaction.id}/print-invoice"
                    target="_blank"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:bg-slate-50"
                >
                    <i class="ti ti-printer text-sm text-slate-400"></i>
                    Invoice
                </a>

                <!-- Print shipping label -->
                {#if transaction.tracking_number}
                    <a
                        href="/admin/transactions/{transaction.id}/print-shipping-label"
                        target="_blank"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:bg-slate-50"
                    >
                        <i class="ti ti-barcode text-sm text-slate-400"></i>
                        Label Kirim
                    </a>
                {/if}

                <!-- Confirm pickup -->
                {#if transaction.status !== 'selesai' && transaction.status !== 'batal' && transaction.shipping_courier === 'self_pickup'}
                    <button
                        onclick={confirmPickup}
                        disabled={isUpdating}
                        class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-500 px-3 py-1.5 text-xs font-semibold text-white transition-opacity hover:opacity-90 disabled:opacity-50"
                    >
                        <i class="ti ti-check text-sm"></i>
                        Konfirmasi Pengambilan
                    </button>
                {/if}

                <!-- Biteship / Komerce Booking Actions -->
                {#if transaction.status !== 'selesai' && transaction.status !== 'batal' && transaction.shipping_courier !== 'digital' && transaction.shipping_courier !== 'store_courier' && transaction.shipping_courier !== 'self_pickup'}
                    {#if !transaction.booking_code}
                        <button
                            onclick={storeKomerceShipment}
                            disabled={bookingLoading}
                            class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white transition-opacity hover:opacity-90 disabled:opacity-50"
                        >
                            <i class="ti ti-send text-sm"></i>
                            {bookingLoading ? 'Booking...' : (biteshipEnabled ? 'Booking Biteship' : 'Booking Komerce')}
                        </button>
                    {:else}
                        {#if ['diproses', 'dikemas'].includes(transaction.status)}
                            <button
                                onclick={() => {
                                    if (biteshipEnabled) {
                                        requestPickupKomerce();
                                    } else {
                                        showPickupModal = true;
                                    }
                                }}
                                disabled={bookingLoading}
                                class="inline-flex items-center gap-1.5 rounded-lg bg-amber-500 px-3 py-1.5 text-xs font-semibold text-white transition-opacity hover:opacity-90 disabled:opacity-50"
                            >
                                <i class="ti ti-truck text-sm"></i>
                                {bookingLoading ? 'Requesting...' : 'Request Pickup'}
                            </button>
                        {/if}
                        <button
                            onclick={cancelKomerceShipment}
                            disabled={bookingLoading}
                            class="inline-flex items-center gap-1.5 rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white transition-opacity hover:opacity-90 disabled:opacity-50"
                        >
                            <i class="ti ti-x text-sm"></i>
                            {bookingLoading ? 'Membatalkan...' : 'Batal Booking'}
                        </button>
                    {/if}
                {/if}

                <!-- Input resi -->
                {#if transaction.status !== 'selesai' && transaction.status !== 'batal' && transaction.shipping_courier !== 'digital' && transaction.shipping_courier !== 'store_courier' && transaction.shipping_courier !== 'self_pickup'}
                    <button
                        onclick={() => (showResiModal = true)}
                        class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold text-white transition-opacity hover:opacity-90"
                        style="background-color: {secondary};"
                    >
                        <i class="ti ti-truck-delivery text-sm"></i>
                        {transaction.tracking_number ? 'Ubah Resi' : 'Input Resi'}
                    </button>
                {/if}

                <!-- Update status -->
                {#if transaction.status !== 'selesai' && transaction.status !== 'batal'}
                    <button
                        onclick={() => (showStatusModal = true)}
                        class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold text-white transition-opacity hover:opacity-90"
                        style="background-color: {primary};"
                    >
                        <i class="ti ti-edit text-sm"></i>
                        Ubah Status
                    </button>
                {/if}
            </div>
        </div>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">

            <!-- Main: left 2 cols -->
            <div class="space-y-5 lg:col-span-2">

                <!-- Order progress tracker -->
                {#if transaction.status !== 'batal'}
                    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                        <div class="border-b border-slate-100 px-5 py-3.5">
                            <p class="text-sm font-semibold text-slate-800">Perjalanan Pesanan</p>
                        </div>
                        <div class="px-5 py-5 overflow-x-auto custom-scrollbar">
                            <div class="relative flex items-start justify-between min-w-[560px]">
                                <!-- Progress line -->
                                <div class="absolute left-5 right-5 top-4 h-0.5 bg-slate-100"></div>
                                <div
                                    class="absolute left-5 top-4 h-0.5 transition-all duration-500"
                                    style="width: {statusIndex > 0 ? Math.min((statusIndex / (statusSteps.length - 1)) * 100, 100) : 0}%; background-color: {primary}; right: auto;"
                                ></div>

                                {#each statusSteps as step, i}
                                    {@const isCompleted = statusIndex >= i}
                                    {@const isCurrent = statusIndex === i}
                                    <div class="relative z-10 flex flex-col items-center gap-1.5">
                                        <div
                                            class="flex h-8 w-8 items-center justify-center rounded-full border-2 transition-all"
                                            style={isCompleted
                                                ? `background-color: ${primary}; border-color: ${primary};`
                                                : 'background-color: #fff; border-color: #e2e8f0;'}
                                        >
                                            <i
                                                class="ti {step.icon} text-xs"
                                                style={isCompleted ? 'color: #fff;' : 'color: #94a3b8;'}
                                            ></i>
                                        </div>
                                        <span
                                            class="text-[9px] font-semibold uppercase tracking-wide text-center w-14"
                                            style={isCurrent ? `color: ${primary};` : isCompleted ? 'color: #64748b;' : 'color: #94a3b8;'}
                                        >
                                            {step.label}
                                        </span>
                                    </div>
                                {/each}
                            </div>
                        </div>
                    </div>
                {:else}
                    <div class="rounded-xl border border-rose-200 bg-rose-50 px-5 py-4">
                        <div class="flex items-start gap-3">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-rose-100 text-rose-600">
                                <i class="ti ti-circle-x text-base"></i>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-rose-800">Pesanan Dibatalkan</p>
                                {#if transaction.cancel_reason}
                                    <p class="mt-1 text-xs leading-relaxed text-rose-600/90">{transaction.cancel_reason}</p>
                                {/if}
                            </div>
                        </div>
                    </div>
                {/if}

                <!-- Product items -->
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                    <div class="border-b border-slate-100 px-5 py-3.5">
                        <p class="text-sm font-semibold text-slate-800">Detail Pesanan</p>
                    </div>
                    <div class="divide-y divide-slate-100">
                        {#each transaction.items ?? [] as item}
                            <div class="flex items-start gap-3 px-5 py-4">
                                {#if item.product_image}
                                    <img src={item.product_image} alt={item.product_name} class="h-12 w-12 shrink-0 rounded-lg object-cover border border-slate-200 bg-slate-100" />
                                {:else}
                                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-100 text-slate-400">
                                        <i class="ti ti-package text-xl"></i>
                                    </div>
                                {/if}
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-slate-800">{item.product_name}</p>
                                    {#if item.variant_name}
                                        <p class="text-xs text-slate-400">{item.variant_name}</p>
                                    {/if}
                                    {#if item.note}
                                        <p class="mt-1 text-xs text-slate-500 italic">{item.note}</p>
                                    {/if}
                                </div>
                                <div class="shrink-0 text-right">
                                    <p class="text-sm font-semibold text-slate-800">
                                        {new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(item.subtotal)}
                                    </p>
                                    <p class="text-xs text-slate-400">
                                        {item.quantity}x {new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(item.harga_akhir)}
                                    </p>
                                </div>
                            </div>
                        {/each}
                    </div>

                    <!-- Price summary -->
                    <div class="border-t border-slate-100 bg-slate-50/50 px-5 py-4">
                        <div class="space-y-2">
                            <div class="flex justify-between text-sm text-slate-600">
                                <span>Subtotal</span>
                                <span class="font-medium">{transaction.subtotal_formatted ?? '—'}</span>
                            </div>
                            {#if parseFloat(transaction.shipping_fee) > 0}
                                <div class="flex justify-between text-sm text-slate-600">
                                    <span>Ongkos Kirim</span>
                                    <span class="font-medium">{transaction.shipping_cost_formatted ?? '—'}</span>
                                </div>
                            {/if}
                            {#if parseFloat(transaction.shipping_discount) > 0}
                                <div class="flex justify-between text-sm text-emerald-600">
                                    <span>Potongan Ongkir</span>
                                    <span class="font-medium">-{fmtRp(transaction.shipping_discount)}</span>
                                </div>
                            {/if}
                            {#if parseFloat(transaction.discount_amount) > 0}
                                <div class="flex justify-between text-sm text-emerald-600">
                                    <span>Diskon</span>
                                    <span class="font-medium">-{transaction.discount_amount_formatted ?? '—'}</span>
                                </div>
                            {/if}
                            {#if parseFloat(transaction.coins_value) > 0}
                                <div class="flex justify-between text-sm text-emerald-600">
                                    <span>Poin Digunakan</span>
                                    <span class="font-medium">-{fmtRp(transaction.coins_value)}</span>
                                </div>
                            {/if}
                            {#if parseFloat(transaction.admin_fee) > 0}
                                <div class="flex justify-between text-sm text-slate-600">
                                    <span>Biaya Admin</span>
                                    <span class="font-medium">{fmtRp(transaction.admin_fee)}</span>
                                </div>
                            {/if}
                            {#if parseFloat(transaction.application_fee) > 0}
                                <div class="flex justify-between text-sm text-slate-600">
                                    <span>Biaya Aplikasi</span>
                                    <span class="font-medium">{fmtRp(transaction.application_fee)}</span>
                                </div>
                            {/if}
                            <div class="flex justify-between border-t border-slate-200 pt-2 text-base font-bold text-slate-900">
                                <span>Total</span>
                                <span>{transaction.grand_total_formatted ?? '—'}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment proof -->
                {#if transaction.payments && transaction.payments.length > 0}
                    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3.5">
                            <p class="text-sm font-semibold text-slate-800">Bukti Pembayaran</p>
                        </div>
                        <div class="divide-y divide-slate-100">
                            {#each transaction.payments as payment}
                                <div class="px-5 py-4">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-medium text-slate-800">{payment.payment_method?.name ?? (typeof payment.payment_method === 'string' ? payment.payment_method : '—')}</p>
                                            <p class="text-xs text-slate-400">{payment.created_at_formatted ?? '—'}</p>
                                            {#if payment.amount_formatted}
                                                <p class="mt-1 text-sm font-semibold" style="color: {primary};">{payment.amount_formatted}</p>
                                            {/if}
                                        </div>
                                        <div class="flex items-center gap-2 shrink-0">
                                            {#if payment.status === 'confirmed'}
                                                <span class="rounded-md bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">Dikonfirmasi</span>
                                            {:else if payment.status === 'pending'}
                                                <button
                                                    onclick={confirmPayment}
                                                    disabled={isUpdating}
                                                    class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-500 px-3 py-1.5 text-xs font-semibold text-white transition-opacity hover:opacity-90 disabled:opacity-50"
                                                >
                                                    <i class="ti ti-check text-xs"></i>
                                                    Konfirmasi
                                                </button>
                                            {/if}
                                        </div>
                                    </div>
                                    {#if payment.proof_image}
                                        <div class="mt-3">
                                            <button
                                                type="button"
                                                onclick={() => (previewImageUrl = formatImagePath(payment.proof_image))}
                                                class="overflow-hidden rounded-lg border border-slate-200 cursor-zoom-in"
                                            >
                                                <img src={formatImagePath(payment.proof_image)} alt="Bukti pembayaran" class="max-h-40 w-auto object-contain transition-opacity hover:opacity-90" />
                                            </button>
                                        </div>
                                    {/if}
                                </div>
                            {/each}
                        </div>
                    </div>
                {/if}

                <!-- Shipping tracking -->
                {#if transaction.tracking_number}
                    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3.5">
                            <p class="text-sm font-semibold text-slate-800">Riwayat Pengiriman (Biteship/Komerce)</p>
                            {#if trackingLoading}
                                <span class="text-xs text-slate-400 flex items-center gap-1">
                                    <i class="ti ti-loader animate-spin text-sm"></i> Loading...
                                </span>
                            {/if}
                        </div>
                        <div class="px-5 py-4">
                            {#if trackingLoading && trackingTimeline.length === 0}
                                <div class="flex justify-center py-6">
                                    <i class="ti ti-loader animate-spin text-xl text-slate-400"></i>
                                </div>
                            {:else if trackingErr}
                                <div class="text-xs text-rose-500 py-2">
                                    <i class="ti ti-alert-circle"></i> {trackingErr}
                                </div>
                            {:else if trackingTimeline.length > 0}
                                <div class="relative space-y-4 pl-4 before:absolute before:left-1 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-200">
                                    {#each trackingTimeline as track}
                                        <div class="relative pl-4 before:absolute before:left-[-15px] before:top-1.5 before:h-2 before:w-2 before:rounded-full before:bg-slate-300 before:content-['']">
                                            <p class="text-xs font-semibold text-slate-800">{track.desc || track.description}</p>
                                            <p class="text-[10px] text-slate-400 font-medium">{fmtDate(track.date)}</p>
                                        </div>
                                    {/each}
                                </div>
                            {:else}
                                <p class="text-xs text-slate-400 text-center py-4">Tidak ada riwayat pengiriman terbaru.</p>
                            {/if}
                        </div>
                    </div>
                {/if}
            </div>

            <!-- Sidebar: right 1 col -->
            <div class="space-y-5">

                <!-- Customer info -->
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                    <div class="border-b border-slate-100 px-5 py-3.5">
                        <p class="text-sm font-semibold text-slate-800">Pelanggan</p>
                    </div>
                    <div class="px-5 py-4">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-bold uppercase text-white" style="background-color: {primary};">
                                {transaction.customer_name?.substring(0, 2) ?? 'PL'}
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-800 truncate">{transaction.customer_name ?? '—'}</p>
                                <p class="text-xs text-slate-400 truncate">{transaction.customer_email ?? '—'}</p>
                            </div>
                        </div>
                        {#if transaction.customer_phone}
                            <div class="flex items-center gap-2 text-xs text-slate-600">
                                <i class="ti ti-phone text-slate-400"></i>
                                {transaction.customer_phone}
                            </div>
                        {/if}
                    </div>
                </div>

                <!-- Shipping address -->
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                    <div class="border-b border-slate-100 px-5 py-3.5">
                        <p class="text-sm font-semibold text-slate-800">Pengiriman</p>
                    </div>
                    <div class="px-5 py-4 space-y-3">
                        {#if transaction.shipping_courier}
                            <div class="flex items-start gap-2">
                                <i class="ti ti-truck text-sm text-slate-400 mt-0.5 shrink-0"></i>
                                <div>
                                    <p class="text-xs font-medium text-slate-600">{transaction.shipping_courier_label ?? transaction.shipping_courier}</p>
                                    {#if transaction.shipping_service}
                                        <p class="text-xs text-slate-400">{transaction.shipping_service}</p>
                                    {/if}
                                </div>
                            </div>
                        {/if}
                        {#if transaction.tracking_number}
                            <div class="flex items-start gap-2">
                                <i class="ti ti-barcode text-sm text-slate-400 mt-0.5 shrink-0"></i>
                                <p class="font-mono text-xs font-semibold text-slate-700">{transaction.tracking_number}</p>
                            </div>
                        {/if}
                        {#if formattedAddress}
                            <div class="flex items-start gap-2">
                                <i class="ti ti-map-pin text-sm text-slate-400 mt-0.5 shrink-0"></i>
                                <p class="text-xs leading-relaxed text-slate-600">{formattedAddress}</p>
                            </div>
                        {/if}
                    </div>
                </div>

                <!-- Payment info -->
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                    <div class="border-b border-slate-100 px-5 py-3.5">
                        <p class="text-sm font-semibold text-slate-800">Pembayaran</p>
                    </div>
                    <div class="px-5 py-4 space-y-2">
                        {#if paymentMethodLabel}
                            <div class="flex items-center gap-2 text-xs">
                                <i class="ti ti-credit-card text-slate-400"></i>
                                <span class="text-slate-600">{paymentMethodLabel}</span>
                            </div>
                        {/if}
                        <div class="flex items-center justify-between text-sm font-bold text-slate-900">
                            <span>Total</span>
                            <span>{transaction.grand_total_formatted ?? '—'}</span>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                {#if transaction.notes}
                    <div class="overflow-hidden rounded-xl border border-amber-200 bg-amber-50">
                        <div class="border-b border-amber-200 px-5 py-3.5">
                            <p class="text-sm font-semibold text-amber-800">Catatan Pesanan</p>
                        </div>
                        <div class="px-5 py-4">
                            <p class="text-xs leading-relaxed text-amber-700">{transaction.notes}</p>
                        </div>
                    </div>
                {/if}

            </div>
        </div>
    </main>

    <!-- Status modal -->
    {#if showStatusModal}
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
            onclick={(e) => { if (e.target === e.currentTarget) showStatusModal = false; }}>
            <div class="w-full max-w-sm overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <h3 class="text-sm font-semibold text-slate-800">Ubah Status Pesanan</h3>
                    <button type="button" onclick={() => showStatusModal = false}
                        class="flex h-7 w-7 items-center justify-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition-colors" aria-label="Tutup">
                        <i class="ti ti-x text-sm"></i>
                    </button>
                </div>
                <div class="p-5 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">Status Baru</label>
                        <select bind:value={newStatus}
                            class="w-full h-9 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 focus:border-slate-400 focus:outline-none">
                            {#each Object.entries(statusLabels) as [key, label]}
                                <option value={key}>{label}</option>
                            {/each}
                        </select>
                    </div>
                    {#if newStatus === 'batal'}
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">Alasan Pembatalan</label>
                            <textarea bind:value={cancelReason} rows={3} placeholder="Masukkan alasan pembatalan..."
                                class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 placeholder-slate-400 focus:border-slate-400 focus:outline-none resize-none"></textarea>
                        </div>
                    {/if}
                </div>
                <div class="flex items-center justify-end gap-2 border-t border-slate-100 px-5 py-3">
                    <button type="button" onclick={() => showStatusModal = false}
                        class="rounded-lg border border-slate-200 px-4 py-1.5 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50">Batal</button>
                    <button type="button" onclick={updateStatus} disabled={isUpdating}
                        class="rounded-lg px-4 py-1.5 text-sm font-semibold text-white transition-opacity hover:opacity-90 disabled:opacity-50"
                        style="background-color: {primary};">
                        {isUpdating ? 'Menyimpan...' : 'Simpan'}
                    </button>
                </div>
            </div>
        </div>
    {/if}

    <!-- Pickup modal -->
    {#if showPickupModal}
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
            onclick={(e) => { if (e.target === e.currentTarget) showPickupModal = false; }}>
            <div class="w-full max-w-sm overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <h3 class="text-sm font-semibold text-slate-800">Request Pickup</h3>
                    <button type="button" onclick={() => showPickupModal = false}
                        class="flex h-7 w-7 items-center justify-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition-colors" aria-label="Tutup">
                        <i class="ti ti-x text-sm"></i>
                    </button>
                </div>
                <div class="p-5 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">Waktu Pickup (Min. 90 menit ke depan)</label>
                        <input type="datetime-local" bind:value={pickupTime}
                            class="w-full h-9 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 focus:border-slate-400 focus:outline-none" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">Jenis Kendaraan</label>
                        <select bind:value={vehicleType}
                            class="w-full h-9 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 focus:border-slate-400 focus:outline-none">
                            <option value="motorcycle">Motor (Max 5kg)</option>
                            <option value="car">Mobil</option>
                            <option value="truck">Truk (Wajib jika >= 10kg)</option>
                        </select>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2 border-t border-slate-100 px-5 py-3">
                    <button type="button" onclick={() => showPickupModal = false}
                        class="rounded-lg border border-slate-200 px-4 py-1.5 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50">Batal</button>
                    <button type="button" onclick={requestPickupKomerce} disabled={bookingLoading}
                        class="rounded-lg px-4 py-1.5 text-sm font-semibold text-white transition-opacity hover:opacity-90 disabled:opacity-50"
                        style="background-color: {primary};">
                        {bookingLoading ? 'Memproses...' : 'Request Pickup'}
                    </button>
                </div>
            </div>
        </div>
    {/if}

    <!-- Resi modal -->
    {#if showResiModal}
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
            onclick={(e) => { if (e.target === e.currentTarget) showResiModal = false; }}>
            <div class="w-full max-w-sm overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <h3 class="text-sm font-semibold text-slate-800">{transaction.tracking_number ? 'Ubah' : 'Input'} Nomor Resi</h3>
                    <button type="button" onclick={() => showResiModal = false}
                        class="flex h-7 w-7 items-center justify-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition-colors" aria-label="Tutup">
                        <i class="ti ti-x text-sm"></i>
                    </button>
                </div>
                <div class="p-5">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">Nomor Resi</label>
                    <input type="text" bind:value={resiInput} placeholder="Masukkan nomor resi..."
                        class="w-full h-9 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 placeholder-slate-400 focus:border-slate-400 focus:outline-none" />
                </div>
                <div class="flex items-center justify-end gap-2 border-t border-slate-100 px-5 py-3">
                    <button type="button" onclick={() => showResiModal = false}
                        class="rounded-lg border border-slate-200 px-4 py-1.5 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50">Batal</button>
                    <button type="button" onclick={submitResi} disabled={isUpdating}
                        class="rounded-lg px-4 py-1.5 text-sm font-semibold text-white transition-opacity hover:opacity-90 disabled:opacity-50"
                        style="background-color: {secondary};">
                        {isUpdating ? 'Menyimpan...' : 'Simpan Resi'}
                    </button>
                </div>
            </div>
        </div>
    {/if}

    <!-- Image preview modal -->
    {#if previewImageUrl}
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm"
            onclick={() => (previewImageUrl = null)}>
            <button class="absolute right-4 top-4 flex h-9 w-9 items-center justify-center rounded-lg bg-white/10 text-white hover:bg-white/20 transition-colors" aria-label="Tutup">
                <i class="ti ti-x"></i>
            </button>
            <img src={previewImageUrl} alt="Preview" class="max-h-[85vh] max-w-[90vw] rounded-xl object-contain shadow-2xl"
                onclick={(e) => e.stopPropagation()} />
        </div>
    {/if}

</AdminLayout>
