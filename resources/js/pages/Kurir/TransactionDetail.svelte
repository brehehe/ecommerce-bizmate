<script lang="ts">
    import { page, router, Link } from '@inertiajs/svelte';
    import { showToast } from '@/utils/toast';

    let {
        transaction,
        statusLabels = {},
        storeName = '',
        storeLogo = '',
    } = $props();

    const primary = $derived((page.props as any).theme?.primary_color ?? '#0c4cb4');
    const secondary = $derived((page.props as any).theme?.secondary_color ?? '#fa7315');

    const shownFlashIds = new Set();
    $effect(() => {
        const flash = (page.props as any).flash;
        if (!flash || !flash.id || shownFlashIds.has(flash.id)) return;
        if (flash.success) { showToast(flash.success, 'success'); shownFlashIds.add(flash.id); }
        if (flash.error) { showToast(flash.error, 'error'); shownFlashIds.add(flash.id); }
    });

    let isUpdating = $state(false);
    let showConfirmModal = $state(false);
    let pendingAction = $state<'pickup' | 'delivering' | 'arrived' | null>(null);

    // Photo upload states (supporting multiple photos)
    let deliveryPhotoFiles = $state<File[]>([]);
    let deliveryPhotoPreviews = $state<string[]>([]);
    let fileInputEl = $state<HTMLInputElement | null>(null);

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
            previewIndex = (previewIndex - 1 + previewItems.length) % previewItems.length;
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

    function handleFileChange(event: Event) {
        const input = event.target as HTMLInputElement;
        if (input.files && input.files.length > 0) {
            const files = Array.from(input.files);
            deliveryPhotoFiles = [...deliveryPhotoFiles, ...files];
            
            files.forEach((file) => {
                const reader = new FileReader();
                reader.onload = (e) => {
                    if (e.target?.result) {
                        deliveryPhotoPreviews = [...deliveryPhotoPreviews, e.target.result as string];
                    }
                };
                reader.readAsDataURL(file);
            });
        }
    }

    function removePhoto(index: number) {
        deliveryPhotoFiles = deliveryPhotoFiles.filter((_, i) => i !== index);
        deliveryPhotoPreviews = deliveryPhotoPreviews.filter((_, i) => i !== index);
        if (fileInputEl) {
            fileInputEl.value = '';
        }
    }

    function resetPhotos() {
        deliveryPhotoFiles = [];
        deliveryPhotoPreviews = [];
        if (fileInputEl) {
            fileInputEl.value = '';
        }
    }

    const actionConfig = {
        pickup: {
            label: 'Ambil Paket',
            desc: 'Konfirmasi bahwa Anda sudah menjemput paket dari toko/gudang.',
            icon: 'ti-package',
            color: 'bg-orange-500',
            gradient: 'from-orange-500 to-amber-500',
            nextStatus: 'Out for Pickup',
        },
        delivering: {
            label: 'Mulai Antar',
            desc: 'Konfirmasi bahwa Anda sudah berangkat mengantar paket. Nomor resi akan otomatis dibuat.',
            icon: 'ti-truck',
            color: 'bg-blue-500',
            gradient: 'from-blue-500 to-cyan-500',
            nextStatus: 'Dikirim',
        },
        arrived: {
            label: 'Paket Telah Tiba',
            desc: 'Konfirmasi bahwa paket sudah berhasil diterima oleh pelanggan di alamat tujuan. Email notifikasi akan dikirim ke pelanggan.',
            icon: 'ti-map-pin-check',
            color: 'bg-emerald-500',
            gradient: 'from-emerald-500 to-teal-500',
            nextStatus: 'Tiba di Tujuan',
        },
    };

    // Determine what action is available based on current status.
    // IMPORTANT: must use IIFE so $derived receives the *value*, not the function.
    const availableAction = $derived(
        (() => {
            if (transaction.delivery_arrived_at) return null;
            if (['dikemas', 'diproses', 'menunggu'].includes(transaction.status)) {
                if (!transaction.booking_code || !transaction.tracking_number) return null;
                return 'pickup';
            }
            if (transaction.status === 'out_for_pickup') return 'delivering';
            if (transaction.status === 'dikirim') return 'arrived';
            return null;
        })() as 'pickup' | 'delivering' | 'arrived' | null
    );

    function confirmAction(action: 'pickup' | 'delivering' | 'arrived') {
        pendingAction = action;
        showConfirmModal = true;
        resetPhotos();
    }

    function executeAction() {
        if (!pendingAction) return;
        isUpdating = true;

        const data: Record<string, any> = { action: pendingAction };
        if (pendingAction === 'arrived' && deliveryPhotoFiles.length > 0) {
            data.delivery_photos = deliveryPhotoFiles;
        }

        router.post(
            `/kurir/transactions/${transaction.id}/update-status`,
            data,
            {
                onSuccess: () => {
                    showConfirmModal = false;
                    pendingAction = null;
                    resetPhotos();
                },
                onError: (err: any) => {
                    const msg = Object.values(err)[0] as string;
                    showToast(msg ?? 'Gagal memperbarui status.', 'error');
                },
                onFinish: () => {
                    isUpdating = false;
                },
            }
        );
    }

    function fmt(price: any): string {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(Number(price) || 0);
    }

    function fmtDate(d: string): string {
        if (!d) return '-';
        return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    }

    // Delivery timeline steps
    const deliverySteps = [
        { key: 'dikemas', label: 'Dikemas', icon: 'ti-package', desc: 'Paket sedang dikemas' },
        { key: 'out_for_pickup', label: 'Dijemput', icon: 'ti-building-warehouse', desc: 'Paket sudah dipick oleh kurir' },
        { key: 'dikirim', label: 'Dikirim', icon: 'ti-truck', desc: 'Dalam perjalanan ke tujuan' },
        { key: 'arrived', label: 'Tiba', icon: 'ti-map-pin-check', desc: 'Paket telah tiba di tujuan' },
        { key: 'selesai', label: 'Selesai', icon: 'ti-circle-check', desc: 'Pesanan selesai dikonfirmasi pelanggan' },
    ];

    const currentStatus = $derived(
        transaction.status === 'selesai'
            ? 'selesai'
            : (transaction.delivery_arrived_at ? 'arrived' : transaction.status)
    );

    function getStepState(stepKey: string): 'done' | 'active' | 'pending' {
        const statusOrder = ['dikemas', 'out_for_pickup', 'dikirim', 'arrived', 'selesai'];
        const currentIdx = statusOrder.indexOf(currentStatus);
        const stepIdx = statusOrder.indexOf(stepKey);

        if (stepIdx < currentIdx) {
            return 'done';
        }
        if (stepIdx === currentIdx) {
            return 'active';
        }
        return 'pending';
    }

    const statusColors: Record<string, string> = {
        menunggu: 'bg-yellow-100 text-yellow-700',
        diproses: 'bg-blue-100 text-blue-700',
        dikemas: 'bg-indigo-100 text-indigo-700',
        out_for_pickup: 'bg-orange-100 text-orange-700',
        dikirim: 'bg-cyan-100 text-cyan-700',
        selesai: 'bg-emerald-100 text-emerald-700',
        batal: 'bg-red-100 text-red-700',
    };
</script>

<svelte:head>
    <title>Pesanan #{transaction.transaction_number} — Kurir {storeName}</title>
</svelte:head>

<div class="min-h-screen font-sans" style="background: #f8fafc;">

    <!-- Top bar -->
    <header class="sticky top-0 z-30 bg-white border-b border-slate-200 shadow-sm">
        <div class="max-w-2xl mx-auto px-4 h-14 flex items-center gap-3">
            <Link
                href="/kurir/dashboard"
                class="w-9 h-9 rounded-xl bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition text-slate-600 shrink-0"
            >
                <i class="ti ti-arrow-left text-lg"></i>
            </Link>
            <div class="min-w-0 flex-1">
                <h1 class="text-sm font-black text-slate-900 truncate">#{transaction.transaction_number}</h1>
                <p class="text-xs text-slate-400">Detail Pesanan Kurir</p>
            </div>
            <span class="px-3 py-1.5 rounded-full text-xs font-black shrink-0 {statusColors[transaction.status] ?? 'bg-slate-100 text-slate-600'}">
                {statusLabels[transaction.status] ?? transaction.status}
            </span>
        </div>
    </header>

    <div class="max-w-2xl mx-auto px-4 py-5 space-y-4">

        <!-- Action Banner -->
        {#if availableAction && !['selesai', 'batal'].includes(transaction.status)}
            {@const action = actionConfig[availableAction]}
            <div
                class="rounded-2xl p-5 text-white bg-gradient-to-r {action.gradient} shadow-lg"
            >
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-12 h-12 rounded-2xl bg-white/20 flex items-center justify-center shrink-0">
                        <i class="ti {action.icon} text-2xl"></i>
                    </div>
                    <div>
                        <p class="text-xs font-bold opacity-80 uppercase tracking-wider">Aksi Selanjutnya</p>
                        <p class="font-black text-lg leading-tight" style="font-family: 'Outfit', sans-serif;">{action.label}</p>
                    </div>
                </div>
                <p class="text-sm opacity-90 mb-4 leading-relaxed">{action.desc}</p>
                <button
                    id="btn-action-{availableAction}"
                    onclick={() => confirmAction(availableAction!)}
                    disabled={isUpdating}
                    class="w-full py-3.5 bg-white text-slate-900 rounded-xl font-black text-sm transition hover:bg-slate-50 active:scale-[0.98] disabled:opacity-70 flex items-center justify-center gap-2"
                >
                    <i class="ti {action.icon} text-lg"></i>
                    {action.label}
                </button>
            </div>
        {:else if transaction.status === 'selesai'}
            <div class="rounded-2xl p-5 bg-emerald-600 text-white shadow-lg">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-2xl bg-white/20 flex items-center justify-center shrink-0">
                        <i class="ti ti-circle-check text-2xl"></i>
                    </div>
                    <div>
                        <p class="text-xs font-bold opacity-80 uppercase tracking-wider">Pengiriman Selesai</p>
                        <p class="font-black text-lg leading-tight" style="font-family: 'Outfit', sans-serif;">Pesanan Selesai!</p>
                        <p class="text-xs opacity-80 mt-1">Pelanggan telah mengonfirmasi bahwa pesanan selesai diterima.</p>
                    </div>
                </div>
            </div>
        {:else if transaction.delivery_arrived_at}
            <div class="rounded-2xl p-5 bg-emerald-500 text-white shadow-lg">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-2xl bg-white/20 flex items-center justify-center shrink-0">
                        <i class="ti ti-circle-check text-2xl"></i>
                    </div>
                    <div>
                        <p class="text-xs font-bold opacity-80 uppercase tracking-wider">Pengiriman Selesai</p>
                        <p class="font-black text-lg leading-tight" style="font-family: 'Outfit', sans-serif;">Paket Telah Tiba!</p>
                        <p class="text-xs opacity-80 mt-1">Menunggu konfirmasi pesanan selesai dari pelanggan.</p>
                    </div>
                </div>
            </div>
        {/if}

        {#if transaction.delivery_photos && transaction.delivery_photos.length > 0}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                <h2 class="text-sm font-black text-slate-800 mb-3 flex items-center gap-2">
                    <i class="ti ti-camera text-base" style="color: {primary};"></i>
                    Foto Bukti Pengiriman ({transaction.delivery_photos.length})
                </h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    {#each transaction.delivery_photos as photo, idx}
                        <button
                            onclick={() => openPreview(transaction.delivery_photos, idx)}
                            class="block relative rounded-xl overflow-hidden aspect-video bg-slate-50 border border-slate-150 hover:opacity-90 transition text-left w-full cursor-pointer"
                        >
                            {#if isVideo(photo)}
                                <div class="w-full h-full bg-slate-800 flex flex-col items-center justify-center text-white gap-1">
                                    <i class="ti ti-video text-2xl text-slate-300"></i>
                                    <span class="text-[10px] font-bold opacity-75">Video Bukti</span>
                                </div>
                            {:else}
                                <img src="/storage/{photo}" alt="Bukti Pengiriman" class="w-full h-full object-cover" />
                            {/if}
                        </button>
                    {/each}
                </div>
            </div>
        {/if}

        <!-- Status Pengiriman Timeline -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
            <h2 class="text-sm font-black text-slate-800 mb-4 flex items-center gap-2">
                <i class="ti ti-truck-delivery text-base" style="color: {primary};"></i>
                Status Pengiriman
            </h2>

            <!-- Steps (Responsive, no overlap) -->
            <div class="relative space-y-1">
                {#each deliverySteps as step, i}
                    {@const state = getStepState(step.key)}
                    <div class="flex items-start gap-4 relative pb-5 {i === deliverySteps.length - 1 ? 'pb-0' : ''}">
                        <!-- Vertical line -->
                        {#if i < deliverySteps.length - 1}
                            <div class="absolute left-[18px] top-9 bottom-0 w-0.5 {state !== 'pending' ? 'bg-emerald-300' : 'bg-slate-100'}"></div>
                        {/if}

                        <!-- Icon -->
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center text-sm shrink-0 transition-all z-10
                            {state === 'done' ? 'bg-emerald-100 text-emerald-600' : state === 'active' ? 'text-white shadow-md' : 'bg-slate-100 text-slate-400'}"
                            style={state === 'active' ? `background-color: ${primary};` : ''}
                        >
                            {#if state === 'done'}
                                <i class="ti ti-check font-black"></i>
                            {:else}
                                <i class="ti {step.icon}"></i>
                            {/if}
                        </div>

                        <!-- Content -->
                        <div class="flex-1 pt-1.5 {state === 'pending' ? 'opacity-40' : ''}">
                            <p class="text-sm font-black text-slate-800 leading-none mb-1 {state === 'active' ? 'font-black' : ''}">{step.label}</p>
                            <p class="text-xs text-slate-500 leading-normal">{step.desc}</p>
                        </div>
                    </div>
                {/each}
            </div>
        </div>

        <!-- Riwayat Pesanan (Logs) -->
        {#if transaction.status_histories?.length > 0}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                <h2 class="text-sm font-black text-slate-800 mb-4 flex items-center gap-2">
                    <i class="ti ti-history text-base" style="color: {primary};"></i>
                    Riwayat Pesanan (Log Status)
                </h2>
                <div class="space-y-3 max-h-60 overflow-y-auto">
                    {#each [...transaction.status_histories].reverse() as hist}
                        <div class="flex items-start gap-3 text-xs">
                            <div class="w-1.5 h-1.5 rounded-full bg-slate-305 mt-1.5 shrink-0"></div>
                            <div class="flex-1 min-w-0">
                                <p class="text-slate-700 font-bold leading-relaxed">{hist.description}</p>
                                <p class="text-slate-400 text-[10px] mt-0.5">{fmtDate(hist.created_at)}</p>
                            </div>
                        </div>
                    {/each}
                </div>
            </div>
        {/if}

        <!-- Booking & Resi Info -->
        {#if transaction.booking_code || transaction.tracking_number}
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
                <h2 class="text-sm font-black text-slate-800 mb-4 flex items-center gap-2">
                    <i class="ti ti-barcode text-base" style="color: {primary};"></i>
                    Kode Pengiriman
                </h2>
                <div class="space-y-4">
                    {#if transaction.booking_code}
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs text-slate-500">Kode Booking</p>
                                <p class="text-sm font-black text-slate-800 font-mono break-all">{transaction.booking_code}</p>
                            </div>
                            <span class="px-2.5 py-1 bg-blue-50 text-blue-600 rounded-lg text-xs font-bold shrink-0">Booking</span>
                        </div>
                    {/if}
                    {#if transaction.tracking_number}
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                            <div class="min-w-0">
                                <p class="text-xs text-slate-500">Nomor Resi</p>
                                <p class="text-sm font-black text-emerald-700 font-mono break-all">{transaction.tracking_number}</p>
                            </div>
                            <div class="flex items-center gap-2 self-start sm:self-auto shrink-0">
                                <a
                                    href="/admin/transactions/{transaction.id}/print-shipping-label"
                                    target="_blank"
                                    class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-bold transition flex items-center gap-1.5"
                                    title="Cetak Label Pengiriman"
                                >
                                    <i class="ti ti-printer text-xs"></i>
                                    Cetak Label
                                </a>
                                <span class="px-2.5 py-1 bg-emerald-50 text-emerald-600 rounded-lg text-xs font-bold">Resi</span>
                            </div>
                        </div>
                    {:else if transaction.status === 'dikirim'}
                        <div class="flex items-center gap-2 text-xs text-amber-600 bg-amber-50 rounded-xl p-3">
                            <i class="ti ti-alert-triangle text-sm shrink-0"></i>
                            Nomor resi belum ada. Akan otomatis dibuat saat Anda menekan "Mulai Antar".
                        </div>
                    {/if}
                    {#if transaction.courier_user}
                        <div class="flex items-center justify-between pt-3 border-t border-slate-100/60 gap-3">
                            <div class="min-w-0">
                                <p class="text-xs text-slate-500">Petugas Kurir</p>
                                <p class="text-sm font-black text-slate-800 truncate">{transaction.courier_user.name}</p>
                            </div>
                            <span class="px-2.5 py-1 bg-slate-100 text-slate-600 rounded-lg text-xs font-bold shrink-0">Kurir</span>
                        </div>
                    {/if}
                </div>
            </div>
        {/if}

        <!-- Customer Info -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
            <h2 class="text-sm font-black text-slate-800 mb-4 flex items-center gap-2">
                <i class="ti ti-user text-base" style="color: {primary};"></i>
                Info Penerima
            </h2>
            <div class="space-y-3">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-slate-100 flex items-center justify-center shrink-0">
                        <i class="ti ti-user text-slate-500"></i>
                    </div>
                    <div>
                        <p class="text-sm font-black text-slate-800">{transaction.user?.name ?? '-'}</p>
                        <p class="text-xs text-slate-400">{transaction.user?.email ?? ''}</p>
                    </div>
                </div>
                {#if transaction.user?.phone_number}
                    <a href="tel:{transaction.user.phone_number}" class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl hover:bg-slate-100 transition">
                        <i class="ti ti-phone text-slate-400 text-base"></i>
                        <span class="text-sm font-bold text-slate-700">{transaction.user.phone_number}</span>
                        <span class="ml-auto text-xs text-blue-600 font-bold">Hubungi</span>
                    </a>
                {/if}
                {#if transaction.customer_address}
                    <div class="p-3 bg-slate-50 rounded-xl">
                        <div class="flex items-start gap-2">
                            <i class="ti ti-map-pin text-slate-400 text-base mt-0.5 shrink-0"></i>
                            <div>
                                <p class="text-xs font-bold text-slate-600 mb-0.5">{transaction.customer_address.label ?? 'Alamat Pengiriman'}</p>
                                <p class="text-sm text-slate-700 leading-relaxed">{transaction.customer_address.address}</p>
                                {#if transaction.customer_address.regency_name}
                                    <p class="text-xs text-slate-500 mt-1">{transaction.customer_address.regency_name}{transaction.customer_address.province_name ? ', ' + transaction.customer_address.province_name : ''}</p>
                                {/if}
                            </div>
                        </div>
                        {#if transaction.customer_address.latitude && transaction.customer_address.longitude}
                            <a
                                href="https://www.google.com/maps?q={transaction.customer_address.latitude},{transaction.customer_address.longitude}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="mt-3 flex items-center gap-2 text-xs font-bold text-blue-600 hover:text-blue-700 transition"
                            >
                                <i class="ti ti-map text-base"></i>
                                Buka di Google Maps
                                <i class="ti ti-external-link text-xs"></i>
                            </a>
                        {/if}
                    </div>
                {/if}
            </div>
        </div>

        <!-- Order Items -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
            <h2 class="text-sm font-black text-slate-800 mb-4 flex items-center gap-2">
                <i class="ti ti-shopping-bag text-base" style="color: {primary};"></i>
                Isi Paket ({transaction.items?.length ?? 0} item)
            </h2>
            <div class="space-y-3">
                {#each (transaction.items ?? []) as item}
                    <div class="flex items-center gap-3">
                        {#if item.product?.images?.[0]}
                            <img src={item.product.images[0].image_url ?? item.product.images[0].image_path} alt={item.product_name} class="w-12 h-12 rounded-xl object-cover border border-slate-100 shrink-0" />
                        {:else}
                            <div class="w-12 h-12 rounded-xl bg-slate-100 flex items-center justify-center shrink-0">
                                <i class="ti ti-photo-off text-slate-400"></i>
                            </div>
                        {/if}
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-bold text-slate-800 truncate">{item.product_name}</p>
                            {#if item.product_variant_name}
                                <p class="text-xs text-slate-500">{item.product_variant_name}</p>
                            {/if}
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-xs text-slate-500">x{item.quantity}</p>
                            <p class="text-sm font-black text-slate-700">{fmt(item.price * item.quantity)}</p>
                        </div>
                    </div>
                {/each}
            </div>
        </div>

    </div>
</div>

<!-- Confirm Action Modal -->
{#if showConfirmModal && pendingAction}
    {@const action = actionConfig[pendingAction]}
    <!-- svelte-ignore a11y_click_events_have_key_events -->
    <div
        class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-end sm:items-center justify-center p-4"
        onclick={(e) => { if (e.target === e.currentTarget) showConfirmModal = false; }}
        role="dialog"
        tabindex="-1"
    >
        <div class="bg-white rounded-3xl w-full max-w-sm shadow-2xl overflow-hidden animate-in slide-in-from-bottom-5 duration-300">
            <div class="p-6 text-center">
                <div class="w-16 h-16 rounded-2xl mx-auto mb-4 flex items-center justify-center bg-gradient-to-br {action.gradient}">
                    <i class="ti {action.icon} text-white text-3xl"></i>
                </div>
                <h3 class="text-xl font-black text-slate-900 mb-2" style="font-family: 'Outfit', sans-serif;">{action.label}</h3>
                <p class="text-sm text-slate-500 leading-relaxed mb-6">{action.desc}</p>

                {#if pendingAction === 'arrived'}
                    <div class="mb-6 text-left">
                        <span class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-2">Foto Bukti Pengiriman (Bisa Lebih dari 1)</span>
                        
                        {#if deliveryPhotoPreviews.length > 0}
                            <div class="grid grid-cols-2 gap-2 mb-3">
                                {#each deliveryPhotoPreviews as preview, idx}
                                    <div class="relative rounded-xl overflow-hidden aspect-video bg-slate-100 border border-slate-200 group">
                                        <img src={preview} alt="Preview Bukti" class="w-full h-full object-cover" />
                                        <button
                                            type="button"
                                            onclick={() => removePhoto(idx)}
                                            class="absolute top-1 right-1 w-6 h-6 rounded-full bg-black/70 hover:bg-black/90 flex items-center justify-center text-white transition active:scale-90"
                                            aria-label="Hapus foto"
                                        >
                                            <i class="ti ti-x text-xs"></i>
                                        </button>
                                    </div>
                                {/each}
                            </div>
                        {/if}

                        <button
                            type="button"
                            onclick={() => fileInputEl?.click()}
                            class="w-full py-4 border-2 border-dashed border-slate-200 rounded-2xl flex flex-col items-center justify-center gap-1 hover:border-slate-350 hover:bg-slate-100/50 transition text-slate-400 hover:text-slate-500 bg-slate-50"
                        >
                            <i class="ti ti-camera text-xl"></i>
                            <span class="text-xs font-black">
                                {deliveryPhotoPreviews.length > 0 ? 'Tambah Foto Lain' : 'Ambil Foto / Unggah Gambar'}
                            </span>
                        </button>

                        <input
                            type="file"
                            bind:this={fileInputEl}
                            accept="image/*"
                            capture="environment"
                            multiple
                            onchange={handleFileChange}
                            class="hidden"
                        />
                    </div>
                {/if}

                <div class="flex gap-3">
                    <button
                        onclick={() => { showConfirmModal = false; pendingAction = null; }}
                        class="flex-1 py-3.5 border border-slate-200 rounded-xl text-sm font-bold text-slate-600 hover:bg-slate-50 transition"
                    >
                        Batal
                    </button>
                    <button
                        id="btn-confirm-action"
                        onclick={executeAction}
                        disabled={isUpdating}
                        class="flex-1 py-3.5 rounded-xl text-sm font-black text-white transition disabled:opacity-70 flex items-center justify-center gap-2 bg-gradient-to-r {action.gradient} hover:opacity-90"
                    >
                        {#if isUpdating}
                            <i class="ti ti-loader animate-spin text-base"></i>
                        {:else}
                            <i class="ti {action.icon} text-base"></i>
                        {/if}
                        Konfirmasi
                    </button>
                </div>
            </div>
        </div>
    </div>
{/if}

<!-- Window Keydown Binder -->
<svelte:window onkeydown={handleKeydown} />

<!-- Full Screen Gallery Preview Modal -->
{#if showPreviewModal && previewItems.length > 0}
    <!-- Full-screen Backdrop -->
    <!-- svelte-ignore a11y_click_events_have_key_events -->
    <div
        class="fixed inset-0 bg-black/90 backdrop-blur-md z-50 flex flex-col justify-between p-4 sm:p-6 select-none"
        onclick={(e) => { if (e.target === e.currentTarget) closePreview(); }}
        role="dialog"
        aria-label="File Preview"
        tabindex="-1"
    >
        <!-- Top bar -->
        <div class="flex items-center justify-between text-white w-full max-w-5xl mx-auto z-10">
            <span class="text-sm font-bold opacity-75">
                {previewIndex + 1} / {previewItems.length}
            </span>
            <button
                onclick={closePreview}
                class="w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 active:scale-95 flex items-center justify-center transition"
                title="Tutup"
            >
                <i class="ti ti-x text-xl"></i>
            </button>
        </div>

        <!-- Center Viewport -->
        <div class="flex-1 flex items-center justify-center relative w-full max-w-5xl mx-auto my-4 overflow-hidden">
            <!-- Prev Button -->
            {#if previewItems.length > 1}
                <button
                    onclick={prevPreview}
                    class="absolute left-2 sm:left-4 z-10 w-12 h-12 rounded-full bg-white/10 hover:bg-white/20 active:scale-90 flex items-center justify-center text-white transition"
                    title="Sebelumnya"
                >
                    <i class="ti ti-chevron-left text-2xl"></i>
                </button>
            {/if}

            <!-- Media Content -->
            {#key previewIndex}
                <div class="max-w-full max-h-[75vh] flex items-center justify-center p-2 animate-in fade-in zoom-in-95 duration-200">
                    {#if isVideo(previewItems[previewIndex])}
                        <video
                            src="/storage/{previewItems[previewIndex]}"
                            controls
                            autoplay
                            class="max-w-full max-h-[70vh] rounded-2xl shadow-2xl object-contain border border-white/10"
                        >
                            <track kind="captions" />
                        </video>
                    {:else}
                        <img
                            src="/storage/{previewItems[previewIndex]}"
                            alt="Bukti Pengiriman"
                            class="max-w-full max-h-[70vh] rounded-2xl shadow-2xl object-contain border border-white/10"
                        />
                    {/if}
                </div>
            {/key}

            <!-- Next Button -->
            {#if previewItems.length > 1}
                <button
                    onclick={nextPreview}
                    class="absolute right-2 sm:right-4 z-10 w-12 h-12 rounded-full bg-white/10 hover:bg-white/20 active:scale-90 flex items-center justify-center text-white transition"
                    title="Selanjutnya"
                >
                    <i class="ti ti-chevron-right text-2xl"></i>
                </button>
            {/if}
        </div>

        <!-- Bottom Thumbnails -->
        {#if previewItems.length > 1}
            <div class="flex justify-center gap-2 overflow-x-auto py-3 w-full max-w-lg mx-auto z-10 scrollbar-hide">
                {#each previewItems as item, idx}
                    <button
                        onclick={() => previewIndex = idx}
                        class="w-16 h-10 rounded-lg overflow-hidden border-2 shrink-0 transition-all active:scale-95
                            {previewIndex === idx ? 'border-white scale-105 shadow-md' : 'border-transparent opacity-50 hover:opacity-80'}"
                    >
                        {#if isVideo(item)}
                            <div class="w-full h-full bg-slate-800 flex items-center justify-center text-white">
                                <i class="ti ti-video text-lg"></i>
                            </div>
                        {:else}
                            <img src="/storage/{item}" alt="Thumb" class="w-full h-full object-cover" />
                        {/if}
                    </button>
                {/each}
            </div>
        {:else}
            <div class="h-10"></div>
        {/if}
    </div>
{/if}

<style>
    .scrollbar-hide::-webkit-scrollbar { display: none; }
    .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
</style>
