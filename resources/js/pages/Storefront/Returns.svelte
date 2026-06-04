<script lang="ts">
    import StorefrontLayout from '@/components/layouts/StorefrontLayout.svelte';
    import { page, Link, router } from '@inertiajs/svelte';
    import { showToast } from '@/utils/toast';

    let {
        returns,
        returnStatusLabels = {} as Record<string, string>,
        storeName = '',
        storeLogo = '',
    } = $props();

    let selectedReturn = $state<any>(null);
    let trackingNumber = $state('');
    let courierName = $state('');
    let submittingTracking = $state(false);

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

    function openReturnDetail(ret: any) {
        selectedReturn = ret;
        trackingNumber = ret.return_tracking_number || '';
        courierName = ret.return_courier_name || '';
    }

    function submitTrackingNumber() {
        if (!trackingNumber.trim()) {
            showToast('Nomor resi wajib diisi.', 'error');
            return;
        }
        submittingTracking = true;
        router.post(`/returns/${selectedReturn.id}/tracking`, {
            return_tracking_number: trackingNumber,
            return_courier_name: courierName,
        }, {
            onSuccess: (pageObj) => {
                showToast('Nomor resi retur berhasil disimpan!', 'success');
                const updated = (pageObj.props as any).returns.data.find((r: any) => r.id === selectedReturn.id);
                if (updated) {
                    selectedReturn = updated;
                } else {
                    selectedReturn = null;
                }
                submittingTracking = false;
            },
            onError: (errors) => {
                const err = Object.values(errors)[0] as string;
                showToast(err || 'Gagal menyimpan nomor resi.', 'error');
                submittingTracking = false;
            }
        });
    }

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
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    function formatImagePath(path: string | null | undefined): string {
        if (!path) return '/noimage/image.png';
        if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/')) {
            return path;
        }
        return '/storage/' + path;
    }

    const returnStatusColors: Record<string, { bg: string; text: string }> = {
        menunggu_review: { bg: '#fef3c7', text: '#92400e' },
        disetujui: { bg: '#dbeafe', text: '#1e40af' },
        ditolak: { bg: '#fee2e2', text: '#991b1b' },
        barang_dikirim_customer: { bg: '#ffedd5', text: '#9a3412' },
        barang_diterima_toko: { bg: '#ede9fe', text: '#5b21b6' },
        refund_diproses: { bg: '#cffafe', text: '#0e7490' },
        selesai: { bg: '#dcfce7', text: '#166534' },
    };
</script>

<StorefrontLayout {storeName} {storeLogo} hideMobileFooter={true}>
    <div class="min-h-dvh bg-slate-50">
        <!-- Header -->
        <div class="bg-white border-b border-slate-200 sticky top-0 z-30">
            <div class="max-w-6xl mx-auto px-4 h-14 flex items-center gap-3">
                <Link href="/" class="p-2 hover:bg-slate-100 rounded-full transition">
                    <i class="ti ti-arrow-left text-xl text-slate-700"></i>
                </Link>
                <h1 class="text-base font-bold text-slate-800">Retur Saya</h1>
            </div>
        </div>

        <div class="max-w-6xl mx-auto px-4 py-6 pb-12">
            {#if returns.total === 0}
                <div class="w-full max-w-6xl mx-auto bg-white rounded-3xl border border-slate-100 shadow-sm p-8 py-20 flex flex-col items-center justify-center text-center">
                    <div class="w-20 h-20 rounded-full flex items-center justify-center mb-4" style="background-color: {secondary}10">
                        <i class="ti ti-arrow-back-up text-3xl text-orange-500"></i>
                    </div>
                    <p class="font-bold text-slate-700 text-lg mb-1">Belum ada pengajuan retur</p>
                    <p class="text-sm text-slate-400 mb-6 font-medium leading-relaxed">
                        Anda tidak memiliki pengajuan retur aktif maupun riwayat pengembalian produk saat ini.
                    </p>
                    <Link
                        href="/transactions"
                        class="px-6 py-3 rounded-xl font-bold text-white transition active:scale-95 hover:opacity-95 shadow-md shadow-brand-blueRoyal/15"
                        style="background:{primary}"
                    >
                        Lihat Pesanan Saya
                    </Link>
                </div>
            {:else}
                <div class="max-w-6xl mx-auto space-y-4">
                    {#each returns.data as ret (ret.id)}
                                {@const statusColor = returnStatusColors[ret.status] ?? { bg: '#f1f5f9', text: '#475569' }}
                                <button
                                    type="button"
                                    onclick={() => openReturnDetail(ret)}
                                    class="w-full text-left block bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden hover:shadow-md transition duration-200 cursor-pointer"
                                >
                                    <!-- Return Request Header -->
                                    <div class="px-4 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/30">
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2.5 flex-wrap">
                                                <p class="text-sm font-black text-slate-800 tracking-tight whitespace-nowrap">#{ret.return_number}</p>
                                                <span class="text-[10px] font-black px-2 py-0.5 rounded bg-slate-100 text-slate-500 uppercase tracking-wider whitespace-nowrap">
                                                    {ret.type === 'refund' ? 'Refund Dana' : 'Tukar Barang'}
                                                </span>
                                            </div>
                                            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-1">Diajukan: {fmtDate(ret.created_at)}</p>
                                        </div>
                                        <div class="shrink-0 flex items-center">
                                            <span
                                                class="text-[10px] font-black px-3 py-1 rounded-full uppercase tracking-wider border whitespace-nowrap"
                                                style="background:{statusColor.bg}; color:{statusColor.text}; border-color:{statusColor.text}15;"
                                            >
                                                {returnStatusLabels[ret.status] ?? ret.status}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Returned Items Preview -->
                                    <div class="px-4 py-3 divide-y divide-slate-50">
                                        {#each ret.items ?? [] as item}
                                            <div class="w-full flex items-center gap-3 py-2">
                                                <div class="w-10 h-10 rounded-lg bg-slate-100 shrink-0 flex items-center justify-center border border-slate-150">
                                                    <i class="ti ti-package text-slate-350 text-lg"></i>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-xs font-bold text-slate-800 leading-tight truncate">{item.product_name}</p>
                                                    {#if item.variant_name}
                                                        <span class="inline-block text-[9px] font-semibold text-slate-400 mt-0.5">{item.variant_name}</span>
                                                    {/if}
                                                </div>
                                                <div class="text-right shrink-0">
                                                    <p class="text-xs text-slate-400 font-bold">x{item.quantity_returned}</p>
                                                    <p class="text-xs font-bold text-slate-700 mt-0.5">{fmt(item.unit_price)}</p>
                                                </div>
                                            </div>
                                        {/each}
                                    </div>

                                    <!-- Footer Summary -->
                                    <div class="px-4 py-3 border-t border-slate-50 bg-slate-50/20 flex items-center justify-between">
                                        <div>
                                            {#if ret.type === 'refund'}
                                                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Total Refund</span>
                                                <p class="text-sm font-black mt-0.5" style="color:{primary}">{fmt(ret.refund_amount)}</p>
                                            {:else}
                                                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Tipe Retur</span>
                                                <p class="text-xs font-bold text-slate-750 mt-0.5">Tukar Barang Baru</p>
                                            {/if}
                                        </div>
                                        <div class="flex items-center gap-1.5 text-xs font-bold text-slate-500 bg-white border border-slate-200 px-3 py-1.5 rounded-xl hover:bg-slate-50 hover:text-slate-700 hover:shadow-3xs transition">
                                            <span>Detail Transaksi & Retur</span>
                                            <i class="ti ti-chevron-right text-xs"></i>
                                        </div>
                                    </div>
                                </button>
                            {/each}
                </div>
            {/if}
        </div>
    </div>

    <!-- Return Detail Modal -->
    {#if selectedReturn}
        {@const currentStatusIndex = ['menunggu_review', 'disetujui', 'barang_dikirim_customer', 'barang_diterima_toko', 'refund_diproses', 'selesai'].indexOf(selectedReturn.status)}
        <!-- svelte-ignore a11y_no_static_element_interactions -->
        <!-- svelte-ignore a11y_click_events_have_key_events -->
        <div class="fixed inset-0 z-50 flex items-end lg:items-center justify-center" onclick={() => (selectedReturn = null)}>
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
            <!-- svelte-ignore a11y_no_static_element_interactions -->
            <!-- svelte-ignore a11y_click_events_have_key_events -->
            <div class="relative z-10 bg-white w-full lg:max-w-2xl rounded-t-3xl lg:rounded-3xl p-6 max-h-[92dvh] overflow-y-auto shadow-2xl flex flex-col gap-6" onclick={(e: any) => e.stopPropagation()}>
                
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="font-outfit font-black text-slate-800 text-lg">Detail Pengajuan Retur</h3>
                            <span class="text-[10px] font-black px-2 py-0.5 rounded bg-slate-100 text-slate-500 uppercase tracking-wider">
                                {selectedReturn.type === 'refund' ? 'Refund Dana' : 'Tukar Barang'}
                            </span>
                        </div>
                        <p class="text-xs text-slate-400 font-bold mt-1">#{selectedReturn.return_number} &bull; Diajukan {fmtDate(selectedReturn.created_at)}</p>
                    </div>
                    <button aria-label="Tutup" onclick={() => (selectedReturn = null)} class="w-9 h-9 rounded-full bg-slate-100 flex items-center justify-center hover:bg-slate-200 transition">
                        <i class="ti ti-x text-base text-slate-600"></i>
                    </button>
                </div>

                <!-- Status Banner / Timeline -->
                {#if selectedReturn.status === 'ditolak'}
                    <div class="bg-rose-50 border border-rose-100 rounded-2xl p-4 flex gap-3 text-rose-800">
                        <i class="ti ti-alert-triangle text-xl shrink-0"></i>
                        <div>
                            <p class="font-bold text-sm">Pengajuan Retur Ditolak</p>
                            <p class="text-xs mt-1 text-rose-600 leading-relaxed font-semibold">Alasan penolakan: {selectedReturn.notes_admin || 'Tidak ada alasan spesifik.'}</p>
                        </div>
                    </div>
                {:else}
                    <div class="bg-slate-50 rounded-2xl p-4 border border-slate-150/40">
                        <h4 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-3 text-center">Status Pengajuan</h4>
                        
                        <div class="relative py-4">
                            <!-- Connecting Line -->
                            <div class="absolute top-[28px] left-[8%] right-[8%] h-0.5 bg-slate-200 z-0">
                                <div class="h-full bg-emerald-500 transition duration-300" style="width: {Math.max(0, currentStatusIndex) * 20}%"></div>
                            </div>
                            
                            <!-- Timeline Steps -->
                            <div class="grid grid-cols-6 gap-1 text-center text-[9px] sm:text-[10px] font-bold text-slate-400 select-none relative z-10">
                                {#each [
                                    { label: 'Diajukan', status: 'menunggu_review', icon: 'ti-file-text' },
                                    { label: 'Disetujui', status: 'disetujui', icon: 'ti-circle-check' },
                                    { label: 'Dikirim', status: 'barang_dikirim_customer', icon: 'ti-truck' },
                                    { label: 'Diterima', status: 'barang_diterima_toko', icon: 'ti-building-warehouse' },
                                    { label: 'Diproses', status: 'refund_diproses', icon: 'ti-refresh' },
                                    { label: 'Selesai', status: 'selesai', icon: 'ti-circle-check-filled' }
                                ] as step, index}
                                    {@const isDone = currentStatusIndex >= index}
                                    {@const isCurrent = currentStatusIndex === index}
                                    <div class="flex flex-col items-center gap-1.5">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center border-2 transition bg-white" 
                                             style="border-color: {isDone ? '#10b981' : '#e2e8f0'}; color: {isDone ? '#10b981' : '#94a3b8'}; box-shadow: {isCurrent ? '0 0 0 4px #10b98125' : 'none'}">
                                            <i class="ti {step.icon} text-base"></i>
                                        </div>
                                        <span class="leading-tight {isDone ? 'text-slate-800 font-extrabold' : 'text-slate-400'}">{step.label}</span>
                                    </div>
                                {/each}
                            </div>
                        </div>
                    </div>
                {/if}

                <!-- Returned Items List -->
                <div class="space-y-3">
                    <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider">Produk Yang Diretur</h4>
                    <div class="bg-slate-50/50 rounded-2xl border border-slate-100 p-3 divide-y divide-slate-100">
                        {#each selectedReturn.items ?? [] as item}
                            <div class="w-full flex items-center gap-3 py-2.5 first:pt-1 last:pb-1">
                                <div class="w-12 h-12 rounded-xl bg-slate-100 shrink-0 flex items-center justify-center border border-slate-200">
                                    <i class="ti ti-package text-slate-400 text-xl"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-bold text-slate-800 leading-tight truncate">{item.product_name}</p>
                                    {#if item.variant_name}
                                        <span class="inline-block text-[9px] font-bold text-slate-400 bg-slate-100 px-1.5 py-0.5 rounded border border-slate-200 mt-1">{item.variant_name}</span>
                                    {/if}
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="text-xs text-slate-400 font-bold">x{item.quantity_returned}</p>
                                    <p class="text-xs font-bold text-slate-700 mt-0.5">{fmt(item.unit_price)}</p>
                                </div>
                            </div>
                        {/each}
                    </div>
                </div>

                <!-- Return Details (Reason & Evidence) -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Reason -->
                    <div class="space-y-2">
                        <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider">Alasan Pengajuan</h4>
                        <div class="bg-slate-50 rounded-2xl p-4 border border-slate-100 text-xs text-slate-700 leading-relaxed font-semibold">
                            {selectedReturn.reason}
                        </div>
                    </div>
                    
                    <!-- Evidence Media -->
                    {#if selectedReturn.media && selectedReturn.media.length > 0}
                        <div class="space-y-2">
                            <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider">Bukti Foto &amp; Video</h4>
                            <div class="grid grid-cols-3 gap-2">
                                {#each selectedReturn.media as mediaFile, idx}
                                    <button type="button" 
                                            class="aspect-square bg-slate-50 rounded-xl overflow-hidden border border-slate-200 relative group cursor-pointer text-left w-full h-full"
                                            onclick={() => openPreview(selectedReturn.media.map(m => m.file_path), idx)}>
                                        {#if mediaFile.file_type === 'video'}
                                            <!-- svelte-ignore a11y_media_has_caption -->
                                            <video src={formatImagePath(mediaFile.file_path)} class="w-full h-full object-cover"></video>
                                            <div class="absolute inset-0 bg-black/40 flex items-center justify-center">
                                                <i class="ti ti-player-play-filled text-white text-lg"></i>
                                            </div>
                                        {:else}
                                            <img src={formatImagePath(mediaFile.file_path)} alt="Bukti Retur" class="w-full h-full object-cover group-hover:scale-105 transition duration-200" />
                                        {/if}
                                    </button>
                                {/each}
                            </div>
                        </div>
                    {/if}
                </div>

                <!-- Refund Details or Replacement Details -->
                {#if selectedReturn.type === 'refund'}
                    <div class="bg-blue-50/50 rounded-2xl p-4 border border-blue-100 space-y-2">
                        <h4 class="text-xs font-black text-blue-900 uppercase tracking-wider flex items-center gap-1.5">
                            <i class="ti ti-credit-card"></i> Informasi Pengembalian Dana (Refund)
                        </h4>
                        <div class="grid grid-cols-2 gap-y-2 text-xs">
                            <div>
                                <span class="text-blue-600/70 font-bold">Total Refund</span>
                                <p class="font-extrabold text-sm text-blue-900 mt-0.5">{fmt(selectedReturn.refund_amount)}</p>
                            </div>
                            <div>
                                <span class="text-blue-600/70 font-bold">Metode Payout</span>
                                <p class="font-bold text-blue-900 mt-0.5">Transfer Bank Manual oleh Admin</p>
                            </div>
                        </div>
                    </div>
                {:else}
                    <div class="bg-blue-50/50 rounded-2xl p-4 border border-blue-100 space-y-2">
                        <h4 class="text-xs font-black text-blue-900 uppercase tracking-wider flex items-center gap-1.5">
                            <i class="ti ti-refresh"></i> Informasi Tukar Barang Baru
                        </h4>
                        <div class="text-xs space-y-2 text-blue-900 leading-relaxed">
                            <div class="flex items-center gap-1.5">
                                <span class="font-bold text-blue-600/70">Status Penukaran:</span>
                                <span class="font-black">
                                    {#if selectedReturn.replacement_transaction_id}
                                        Barang Pengganti Telah Dikirim!
                                    {:else}
                                        Menunggu Barang Retur Tiba di Toko &amp; Diproses Admin
                                    {/if}
                                </span>
                            </div>
                            {#if selectedReturn.replacement_transaction_id}
                                <div class="grid grid-cols-2 gap-2 pt-1.5 border-t border-blue-100/50">
                                    <div>
                                        <span class="text-blue-600/70 font-bold">Kurir</span>
                                        <p class="font-bold text-blue-900 mt-0.5">{selectedReturn.replacement_courier_name || '-'}</p>
                                    </div>
                                    <div>
                                        <span class="text-blue-600/70 font-bold">Nomor Resi</span>
                                        <p class="font-mono font-bold text-blue-900 mt-0.5">{selectedReturn.replacement_tracking_number || '-'}</p>
                                    </div>
                                </div>
                                <div class="pt-1.5 flex justify-end">
                                    <Link href="/transactions/{selectedReturn.replacement_transaction_id}" 
                                          class="inline-flex items-center gap-1 text-xs font-black hover:opacity-80 transition" style="color: {primary}">
                                        <span>Lihat Pesanan Pengganti</span>
                                        <i class="ti ti-chevron-right text-xs"></i>
                                    </Link>
                                </div>
                            {/if}
                        </div>
                    </div>
                {/if}

                <!-- Customer Ship Back Form (Tracking number input) -->
                {#if selectedReturn.status === 'disetujui'}
                    <div class="bg-amber-50 border border-amber-100 rounded-2xl p-4 space-y-4">
                        <div class="flex gap-3 text-amber-800">
                            <i class="ti ti-info-circle text-xl shrink-0 mt-0.5"></i>
                            <div>
                                <p class="font-bold text-sm">Kirimkan Barang &amp; Input Resi</p>
                                <p class="text-xs mt-1 text-amber-600 leading-relaxed font-semibold">
                                    Silakan kirimkan barang Anda kembali ke alamat toko kami. Setelah dikirim, silakan input nama kurir dan nomor resi pengiriman Anda di bawah ini agar kami dapat memantau dan memproses retur Anda segera.
                                </p>
                            </div>
                        </div>
                        
                        <div class="bg-white rounded-xl p-4 border border-amber-100/70 space-y-3">
                            <h5 class="text-xs font-black text-slate-700 uppercase tracking-wider">Data Pengiriman Balik</h5>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 mb-1" for="courier-name">Nama Kurir</label>
                                    <input id="courier-name" type="text" bind:value={courierName} placeholder="Contoh: JNE, J&T, Sicepat" 
                                           class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-slate-350 transition" />
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 mb-1" for="tracking-number">Nomor Resi <span class="text-red-500">*</span></label>
                                    <input id="tracking-number" type="text" bind:value={trackingNumber} placeholder="Masukkan nomor resi pengiriman" 
                                           class="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-slate-350 transition" />
                                </div>
                            </div>
                            <button onclick={submitTrackingNumber} disabled={submittingTracking}
                                    class="w-full py-2.5 rounded-xl font-bold text-white text-xs transition disabled:opacity-50 active:scale-95 shadow-md flex items-center justify-center gap-1.5 mt-2"
                                    style="background: {primary}">
                                {#if submittingTracking}
                                    <i class="ti ti-loader animate-spin text-sm"></i>
                                    Mengirim...
                                {:else}
                                    <i class="ti ti-send text-sm"></i>
                                    Kirim Nomor Resi
                                {/if}
                            </button>
                        </div>
                    </div>
                {:else if selectedReturn.return_tracking_number}
                    <!-- Display customer return tracking number if already submitted -->
                    <div class="bg-slate-50 rounded-2xl p-4 border border-slate-100 space-y-2">
                        <h4 class="text-xs font-black text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                            <i class="ti ti-truck"></i> Pengiriman Kembali Oleh Customer
                        </h4>
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div>
                                <span class="text-slate-400 font-bold">Kurir</span>
                                <p class="font-bold text-slate-800 mt-0.5">{selectedReturn.return_courier_name || '-'}</p>
                            </div>
                            <div>
                                <span class="text-slate-400 font-bold">Nomor Resi</span>
                                <p class="font-mono font-bold text-slate-800 mt-0.5">{selectedReturn.return_tracking_number}</p>
                            </div>
                        </div>
                    </div>
                {/if}

                <!-- Modal Actions -->
                <div class="flex gap-3 border-t border-slate-100 pt-4">
                    <Link href="/transactions/{selectedReturn.transaction_id}" 
                          class="flex-1 py-3 rounded-2xl border-2 border-slate-200 hover:bg-slate-50 text-slate-700 font-bold text-xs transition text-center flex items-center justify-center gap-1.5">
                        <i class="ti ti-receipt text-sm"></i> Detail Transaksi Asal
                    </Link>
                    <button onclick={() => (selectedReturn = null)}
                            class="flex-1 py-3 rounded-2xl text-white font-bold text-xs transition active:scale-95 text-center flex items-center justify-center gap-1.5"
                            style="background: {primary}">
                        Tutup Detail
                    </button>
                </div>
            </div>
        </div>
    {/if}
</StorefrontLayout>

<!-- Window Keydown Binder -->
<svelte:window onkeydown={handleKeydown} />

<!-- Full Screen Gallery Preview Modal -->
{#if showPreviewModal && previewItems.length > 0}
    <!-- Full-screen Backdrop -->
    <!-- svelte-ignore a11y_no_static_element_interactions -->
    <!-- svelte-ignore a11y_click_events_have_key_events -->
    <!-- svelte-ignore a11y_interactive_supports_focus -->
    <div
        class="fixed inset-0 bg-black/90 backdrop-blur-md z-[100] flex flex-col justify-between p-4 sm:p-6 select-none"
        onclick={(e) => { if (e.target === e.currentTarget) closePreview(); }}
        role="dialog"
        aria-label="File Preview"
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
                            src={formatImagePath(previewItems[previewIndex])}
                            controls
                            autoplay
                            class="max-w-full max-h-[70vh] rounded-2xl shadow-2xl object-contain border border-white/10"
                        >
                            <track kind="captions" />
                        </video>
                    {:else}
                        <img
                            src={formatImagePath(previewItems[previewIndex])}
                            alt="Bukti Retur"
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
                            <img src={formatImagePath(item)} alt="Thumb" class="w-full h-full object-cover" />
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
