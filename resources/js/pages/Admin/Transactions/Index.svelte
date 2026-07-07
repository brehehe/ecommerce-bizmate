<script lang="ts">
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { page, router } from '@inertiajs/svelte';
    import { Html5Qrcode } from 'html5-qrcode';
    import { onDestroy } from 'svelte';
    import { dragScroll } from '@/utils/dragScroll';
    import Pagination from '@/components/ui/Pagination.svelte';

    let {
        transactions,
        statusLabels = {},
        filters = {},
        storeName = '',
        storeLogo = '',
    } = $props();

    const primary = $derived(
        (page.props as any).theme?.primary_color ?? '#0c4cb4',
    );
    const secondary = $derived(
        (page.props as any).theme?.secondary_color ?? '#fa7315',
    );

    const statusOrder = [
        'belum_bayar',
        'menunggu',
        'diproses',
        'dikemas',
        'out_for_pickup',
        'dikirim',
        'selesai',
    ];

    function initFiltersState(f: any) {
        return {
            status: f.status ?? '',
            dateFrom: f.date_from ?? '',
            dateTo: f.date_to ?? '',
            search: f.search ?? '',
        };
    }
    // svelte-ignore state_referenced_locally
    const initState = initFiltersState(filters);

    let filterStatus = $state(initState.status);
    let filterDateFrom = $state(initState.dateFrom);
    let filterDateTo = $state(initState.dateTo);
    let filterSearch = $state(initState.search);

    // Checkbox & Resi modal state
    let selectedIds: number[] = $state([]);
    let showResiModal = $state(false);
    let resiTransactionId = $state<number | null>(null);
    let resiTransactionNumber = $state('');
    let resiInput = $state('');
    let courierInput = $state('');
    let submittingResi = $state(false);

    // Toast state
    let toastMsg = $state('');
    let toastType = $state<'success' | 'error'>('success');
    let toastVisible = $state(false);
    let toastTimer: ReturnType<typeof setTimeout> | null = null;

    function showToast(msg: string, type: 'success' | 'error' = 'success') {
        toastMsg = msg;
        toastType = type;
        toastVisible = true;
        if (toastTimer) clearTimeout(toastTimer);
        toastTimer = setTimeout(() => {
            toastVisible = false;
        }, 3500);
    }

    // Bulk status update state and method
    let bulkStatusValue = $state('');
    let bulkCancelReason = $state('');
    let submittingBulkStatus = $state(false);

    // Bulk tracking (resi massal) state
    let showBulkResiModal = $state(false);
    let bulkTrackingData = $state<
        {
            id: number;
            transaction_number: string;
            tracking_number: string;
            courier_name: string;
        }[]
    >([]);
    let submittingBulkResi = $state(false);

    function submitBulkStatus() {
        if (!bulkStatusValue) {
            showToast('Pilih status terlebih dahulu.', 'error');
            return;
        }

        const validIds = selectedIds.filter((id) => {
            const trx = transactions.data.find((t: any) => t.id === id);
            if (!trx) return false;

            if (['selesai', 'batal'].includes(trx.status)) return false;
            if (trx.status === bulkStatusValue) return false;
            if (bulkStatusValue === 'batal') return true;

            const currentIdx = statusOrder.indexOf(trx.status);
            const targetIdx = statusOrder.indexOf(bulkStatusValue);

            return (
                currentIdx !== -1 && targetIdx !== -1 && targetIdx > currentIdx
            );
        });

        if (validIds.length === 0) {
            showToast(
                'Tidak ada transaksi terpilih yang dapat diperbarui ke status tersebut.',
                'error',
            );
            return;
        }

        if (bulkStatusValue === 'dikirim') {
            // Open Bulk Resi Modal
            const selectedTrxs = transactions.data.filter((t: any) =>
                validIds.includes(t.id),
            );
            bulkTrackingData = selectedTrxs.map((t: any) => ({
                id: t.id,
                transaction_number: t.transaction_number,
                tracking_number: t.tracking_number ?? '',
                courier_name: t.courier_name ?? '',
            }));
            showBulkResiModal = true;
            return;
        }

        if (bulkStatusValue === 'batal' && !bulkCancelReason.trim()) {
            showToast('Alasan pembatalan harus diisi.', 'error');
            return;
        }

        submittingBulkStatus = true;
        router.post(
            '/admin/transactions/bulk-status',
            {
                ids: validIds,
                status: bulkStatusValue,
                cancel_reason:
                    bulkStatusValue === 'batal'
                        ? bulkCancelReason.trim()
                        : null,
            },
            {
                onSuccess: () => {
                    showToast(
                        `${validIds.length} transaksi berhasil diperbarui.`,
                        'success',
                    );
                    selectedIds = [];
                    bulkStatusValue = '';
                    bulkCancelReason = '';
                },
                onError: (err: any) => {
                    const first = Object.values(err)[0] as string;
                    showToast(
                        first ?? 'Gagal memperbarui status transaksi.',
                        'error',
                    );
                },
                onFinish: () => {
                    submittingBulkStatus = false;
                },
            },
        );
    }

    function submitBulkTracking() {
        const missing = bulkTrackingData.some((d) => !d.tracking_number.trim());
        if (missing) {
            showToast(
                'Nomor resi untuk seluruh transaksi harus diisi.',
                'error',
            );
            return;
        }

        submittingBulkResi = true;
        router.post(
            '/admin/transactions/bulk-tracking',
            {
                tracking_data: bulkTrackingData.map((d) => ({
                    id: d.id,
                    tracking_number: d.tracking_number.trim(),
                    courier_name: d.courier_name.trim() || null,
                })),
            },
            {
                onSuccess: () => {
                    showToast(
                        'Nomor resi massal berhasil disimpan.',
                        'success',
                    );
                    selectedIds = [];
                    bulkStatusValue = '';
                    showBulkResiModal = false;
                    bulkTrackingData = [];
                },
                onError: (err: any) => {
                    const first = Object.values(err)[0] as string;
                    showToast(
                        first ?? 'Gagal menyimpan nomor resi massal.',
                        'error',
                    );
                },
                onFinish: () => {
                    submittingBulkResi = false;
                },
            },
        );
    }

    function applyFilters() {
        router.get(
            '/admin/transactions',
            {
                status: filterStatus || undefined,
                date_from: filterDateFrom || undefined,
                date_to: filterDateTo || undefined,
                search: filterSearch || undefined,
            },
            { preserveScroll: true },
        );
    }

    function resetFilters() {
        filterStatus = '';
        filterDateFrom = '';
        filterDateTo = '';
        filterSearch = '';
        router.get('/admin/transactions');
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
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

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
    const statusIcons: Record<string, string> = {
        belum_bayar: 'ti-wallet',
        menunggu: 'ti-hourglass-low',
        diproses: 'ti-settings',
        dikemas: 'ti-package',
        out_for_pickup: 'ti-truck-delivery',
        dikirim: 'ti-truck-delivery',
        selesai: 'ti-circle-check',
        batal: 'ti-circle-x',
    };

    function setStatusFilter(status: string) {
        filterStatus = status;
        applyFilters();
    }

    function toggleSelect(id: number) {
        if (selectedIds.includes(id)) {
            selectedIds = selectedIds.filter((x) => x !== id);
        } else {
            selectedIds = [...selectedIds, id];
        }
    }

    function toggleSelectAll() {
        const selectableIds = transactions.data
            .filter((t: any) => t.status !== 'selesai' && t.status !== 'batal')
            .map((t: any) => t.id);

        if (selectedIds.length === selectableIds.length) {
            selectedIds = [];
        } else {
            selectedIds = [...selectableIds];
        }
    }

    const allSelected = $derived(
        transactions.data.filter(
            (t: any) => t.status !== 'selesai' && t.status !== 'batal',
        ).length > 0 &&
            selectedIds.length ===
                transactions.data.filter(
                    (t: any) => t.status !== 'selesai' && t.status !== 'batal',
                ).length,
    );

    function openResiModal(trx: any) {
        resiTransactionId = trx.id;
        resiTransactionNumber = trx.transaction_number;
        resiInput = trx.tracking_number ?? '';
        courierInput = trx.courier_name ?? '';
        showResiModal = true;
    }

    function closeResiModal() {
        showResiModal = false;
        resiTransactionId = null;
        resiInput = '';
        courierInput = '';
    }

    function submitResi() {
        if (!resiInput.trim()) {
            showToast('Nomor resi tidak boleh kosong.', 'error');
            return;
        }
        submittingResi = true;
        router.post(
            `/admin/transactions/${resiTransactionId}/tracking`,
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
                    closeResiModal();
                },
                onError: (errors: any) => {
                    const first = Object.values(errors)[0] as string;
                    showToast(first ?? 'Gagal menyimpan nomor resi.', 'error');
                },
                onFinish: () => {
                    submittingResi = false;
                },
            },
        );
    }

    // QR/Barcode Scanner States
    let showScanModal = $state(false);
    let scanInputValue = $state('');
    let scanError = $state('');
    let isScanning = $state(false);
    let scanSubmitting = $state(false);
    let html5QrScanner = $state<Html5Qrcode | null>(null);
    let availableCameras = $state<any[]>([]);
    let selectedCameraId = $state('');
    let scannerInputEl = $state<HTMLInputElement | null>(null);

    // Audio Oscillator for premium beep sound
    function playBeep() {
        try {
            const ctx = new (
                window.AudioContext || (window as any).webkitAudioContext
            )();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(1000, ctx.currentTime); // 1000 Hz beep
            gain.gain.setValueAtTime(0, ctx.currentTime);
            gain.gain.linearRampToValueAtTime(0.3, ctx.currentTime + 0.05);
            gain.gain.exponentialRampToValueAtTime(
                0.0001,
                ctx.currentTime + 0.25,
            );
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.3);
        } catch (e) {
            console.error('AudioContext error:', e);
        }
    }

    async function handleScanSuccess(decodedText: string) {
        if (!decodedText || scanSubmitting) return;

        // Stop scanning immediately to prevent multiple scans
        await stopScanning();
        playBeep();

        scanInputValue = decodedText.trim();
        submitScannedCode();
    }

    function submitScannedCode() {
        if (!scanInputValue.trim()) return;
        scanSubmitting = true;
        scanError = '';

        fetch(
            `/admin/transactions/find-by-number/${encodeURIComponent(scanInputValue.trim())}`,
        )
            .then(async (resp) => {
                const data = await resp.json();
                if (resp.ok && data.success) {
                    showToast(
                        'Kode berhasil ditemukan! Mengalihkan...',
                        'success',
                    );
                    // Close modal and redirect
                    showScanModal = false;
                    router.visit(data.redirect_url);
                } else {
                    scanError = data.message || 'Transaksi tidak ditemukan.';
                    scanSubmitting = false;
                    // Restart scanner if camera was running
                    if (showScanModal) {
                        startScanning();
                    }
                }
            })
            .catch(() => {
                scanError = 'Gagal mencari transaksi. Hubungan terputus.';
                scanSubmitting = false;
                if (showScanModal) {
                    startScanning();
                }
            });
    }

    async function startScanning() {
        scanError = '';
        try {
            // Request permissions and get cameras
            const devices = await Html5Qrcode.getCameras();
            if (devices && devices.length > 0) {
                availableCameras = devices;
                if (!selectedCameraId) {
                    // Prefer back camera if available
                    const backCam = devices.find(
                        (d) =>
                            d.label.toLowerCase().includes('back') ||
                            d.label.toLowerCase().includes('rear'),
                    );
                    selectedCameraId = backCam ? backCam.id : devices[0].id;
                }

                // Initialize scanner
                if (!html5QrScanner) {
                    html5QrScanner = new Html5Qrcode('scanner-reader');
                }

                isScanning = true;
                await html5QrScanner.start(
                    selectedCameraId,
                    {
                        fps: 10,
                        qrbox: (width, height) => {
                            const min = Math.min(width, height);
                            return { width: min * 0.7, height: min * 0.7 };
                        },
                        aspectRatio: 1.0,
                    },
                    (decodedText) => {
                        handleScanSuccess(decodedText);
                    },
                    (errorMessage) => {
                        // Verbose debug scanning errors can be ignored
                    },
                );
            } else {
                scanError =
                    'Kamera tidak ditemukan. Pastikan izin kamera telah diberikan.';
            }
        } catch (err: any) {
            scanError = 'Gagal mengakses kamera: ' + (err.message || err);
            isScanning = false;
        }
    }

    async function stopScanning() {
        if (html5QrScanner && isScanning) {
            try {
                await html5QrScanner.stop();
            } catch (e) {
                console.error('Error stopping scanner:', e);
            }
            isScanning = false;
        }
    }

    async function changeCamera(cameraId: string) {
        selectedCameraId = cameraId;
        if (isScanning) {
            await stopScanning();
            await startScanning();
        }
    }

    function openScanModal() {
        showScanModal = true;
        scanInputValue = '';
        scanError = '';
        isScanning = false;
        scanSubmitting = false;

        // Auto focus manual input for barcode scanner gun
        setTimeout(() => {
            if (scannerInputEl) {
                scannerInputEl.focus();
            }
            // Auto start camera scanning
            startScanning();
        }, 150);
    }

    async function closeScanModal() {
        await stopScanning();
        showScanModal = false;
    }

    onDestroy(async () => {
        await stopScanning();
    });
    // scan modal state
    let scanModalOpen = $state(false);
</script>

<svelte:head>
    <title>Transaksi — Admin</title>
</svelte:head>

<AdminLayout>
    <main class="w-full max-w-[1600px] mx-auto px-4 sm:px-6 py-6 space-y-5">

        <!-- Page header -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-semibold tracking-tight text-slate-900">Transaksi</h1>
                <p class="mt-0.5 text-sm text-slate-500">Kelola semua pesanan pelanggan</p>
            </div>
            <button
                onclick={openScanModal}
                class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold text-white shadow-xs transition-opacity hover:opacity-90 self-start sm:self-auto"
                style="background-color: {secondary};"
            >
                <i class="ti ti-scan text-base"></i>
                Scan QR / Barcode
            </button>
        </div>

        <!-- Status tabs -->
        <div class="flex gap-1.5 overflow-x-auto border-b border-slate-200 pb-0 scrollbar-none">
            <button
                onclick={() => setStatusFilter('')}
                class="flex shrink-0 items-center gap-1.5 border-b-2 px-3 pb-3 pt-1 text-xs font-semibold transition-colors whitespace-nowrap
                    {filterStatus === '' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700'}"
            >
                <i class="ti ti-layout-grid text-sm"></i>
                Semua
            </button>
            {#each Object.entries(statusLabels) as [key, label]}
                {@const icon = statusIcons[key] ?? 'ti-circle'}
                {@const isActive = filterStatus === key}
                <button
                    onclick={() => setStatusFilter(key)}
                    class="flex shrink-0 items-center gap-1.5 border-b-2 px-3 pb-3 pt-1 text-xs font-semibold transition-colors whitespace-nowrap
                        {isActive ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700'}"
                >
                    <i class="ti {icon} text-sm"></i>
                    {label}
                </button>
            {/each}
        </div>

        <!-- Filters bar -->
        <div class="flex flex-wrap items-end gap-3">
            <!-- Search -->
            <div class="relative flex-1 min-w-48">
                <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                <input
                    type="search"
                    placeholder="Cari no. transaksi, pelanggan..."
                    bind:value={filterSearch}
                    oninput={handleSearchInput}
                    class="h-9 w-full rounded-lg border border-slate-200 bg-white pl-8 pr-3 text-sm text-slate-700 placeholder-slate-400 focus:border-slate-400 focus:outline-none focus:ring-0 transition-colors"
                />
            </div>

            <!-- Date from -->
            <div class="relative">
                <input
                    type="date"
                    bind:value={filterDateFrom}
                    class="h-9 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 focus:border-slate-400 focus:outline-none transition-colors cursor-pointer"
                />
            </div>

            <!-- Date to -->
            <div class="relative">
                <input
                    type="date"
                    bind:value={filterDateTo}
                    class="h-9 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 focus:border-slate-400 focus:outline-none transition-colors cursor-pointer"
                />
            </div>

            <button
                onclick={applyFilters}
                class="h-9 rounded-lg px-4 text-sm font-semibold text-white transition-opacity hover:opacity-90"
                style="background-color: {primary};"
            >
                Filter
            </button>
            <button
                onclick={resetFilters}
                class="h-9 rounded-lg border border-slate-200 px-4 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50"
            >
                Reset
            </button>
        </div>

        <!-- Bulk action bar -->
        {#if selectedIds.length > 0}
            <div class="flex items-center gap-3 rounded-lg border border-slate-200 bg-white px-4 py-2.5 flex-wrap shadow-xs">
                <span class="text-sm font-medium text-slate-700">
                    <span class="font-semibold">{selectedIds.length}</span> transaksi dipilih
                </span>
                <div class="flex items-center gap-2 ml-auto flex-wrap">
                    {#each Object.entries(statusLabels) as [key, label]}
                        <button
                            onclick={() => bulkUpdateStatus(key)}
                            class="h-7 rounded-md px-3 text-xs font-semibold transition-colors hover:opacity-90"
                            style="background-color: {statusColors[key]?.bg ?? '#f1f5f9'}; color: {statusColors[key]?.text ?? '#475569'};"
                        >
                            → {label}
                        </button>
                    {/each}
                    <button
                        onclick={() => (selectedIds = [])}
                        class="h-7 rounded-md border border-slate-200 px-3 text-xs font-medium text-slate-500 transition-colors hover:bg-slate-50"
                    >
                        Batal
                    </button>
                </div>
            </div>
        {/if}

        <!-- Table -->
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <!-- Table toolbar -->
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
                <p class="text-sm text-slate-500">
                    {#if transactions.total !== undefined}
                        <span class="font-semibold text-slate-800">{transactions.total}</span> transaksi
                    {/if}
                </p>
                <div class="flex items-center gap-2">
                    <!-- Select all -->
                    {#if transactions.data.length > 0}
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                checked={selectedIds.length === transactions.data.length && transactions.data.length > 0}
                                onchange={(e) => {
                                    if (e.currentTarget.checked) {
                                        selectedIds = transactions.data.map((t: any) => t.id);
                                    } else {
                                        selectedIds = [];
                                    }
                                }}
                                class="rounded border-slate-300 accent-slate-900"
                            />
                            <span class="text-xs text-slate-500">Pilih semua</span>
                        </label>
                    {/if}
                </div>
            </div>

            {#if transactions.data.length === 0}
                <div class="flex flex-col items-center justify-center py-16 text-center px-4">
                    <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-slate-400">
                        <i class="ti ti-receipt-off text-xl"></i>
                    </div>
                    <p class="text-sm font-medium text-slate-600">Tidak ada transaksi</p>
                    <p class="mt-1 text-xs text-slate-400">Coba ubah filter atau kata kunci pencarian</p>
                </div>
            {:else}
                <div class="overflow-x-auto" use:dragScroll>
                    <table class="w-full responsive-table">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50/50">
                                <th class="w-10 px-4 py-2.5"></th>
                                <th class="px-4 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">No. Pesanan</th>
                                <th class="px-4 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Pelanggan</th>
                                <th class="px-4 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Produk</th>
                                <th class="px-4 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Total</th>
                                <th class="px-4 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Status</th>
                                <th class="px-4 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Tanggal</th>
                                <th class="px-4 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            {#each transactions.data as trx}
                                {@const statusStyle = statusColors[trx.status] ?? { bg: '#f1f5f9', text: '#475569' }}
                                {@const isSelected = selectedIds.includes(trx.id)}
                                <tr class="group transition-colors hover:bg-slate-50/50 {isSelected ? 'bg-blue-50/30' : ''}">
                                    <!-- Checkbox -->
                                    <td class="px-4 py-3">
                                        <input
                                            type="checkbox"
                                            checked={isSelected}
                                            onchange={() => {
                                                if (isSelected) {
                                                    selectedIds = selectedIds.filter((id: string) => id !== trx.id);
                                                } else {
                                                    selectedIds = [...selectedIds, trx.id];
                                                }
                                            }}
                                            class="rounded border-slate-300 accent-slate-900"
                                        />
                                    </td>

                                    <!-- No. Transaksi -->
                                    <td class="px-4 py-3" data-label="No. Pesanan">
                                        <div>
                                            <a
                                                href="/admin/transactions/{trx.id}"
                                                class="font-mono text-xs font-semibold transition-colors hover:underline"
                                                style="color: {primary};"
                                            >
                                                {trx.transaction_number}
                                            </a>
                                            {#if trx.payment_method}
                                                <p class="mt-0.5 text-[10px] text-slate-400">
                                                    {typeof trx.payment_method === 'object' ? trx.payment_method.name : trx.payment_method}
                                                </p>
                                            {/if}
                                        </div>
                                    </td>

                                    <!-- Pelanggan -->
                                    <td class="px-4 py-3" data-label="Pelanggan">
                                        <div>
                                            <p class="text-sm font-medium text-slate-800">{trx.customer_name ?? '—'}</p>
                                            {#if trx.customer_phone}
                                                <p class="text-xs text-slate-400">{trx.customer_phone}</p>
                                            {/if}
                                        </div>
                                    </td>

                                    <!-- Produk -->
                                    <td class="px-4 py-3" data-label="Produk">
                                        <p class="text-xs text-slate-600 line-clamp-2 max-w-48">
                                            {trx.items_summary ?? '—'}
                                        </p>
                                    </td>

                                    <!-- Total -->
                                    <td class="px-4 py-3" data-label="Total">
                                        <p class="text-sm font-semibold text-slate-800 whitespace-nowrap">{trx.grand_total_formatted ?? '—'}</p>
                                    </td>

                                    <!-- Status -->
                                    <td class="px-4 py-3" data-label="Status">
                                        <span
                                            class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[11px] font-semibold"
                                            style="background-color: {statusStyle.bg}; color: {statusStyle.text};"
                                        >
                                            {statusLabels[trx.status] ?? trx.status}
                                        </span>
                                    </td>

                                    <!-- Tanggal -->
                                    <td class="px-4 py-3" data-label="Tanggal">
                                        <p class="whitespace-nowrap text-xs text-slate-500">{trx.created_at_formatted ?? '—'}</p>
                                    </td>

                                    <!-- Aksi -->
                                    <td class="px-4 py-3" data-label="Aksi">
                                        <div class="flex items-center gap-1">
                                            <a
                                                href="/admin/transactions/{trx.id}"
                                                class="flex h-7 w-7 items-center justify-center rounded-md text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700"
                                                title="Detail"
                                            >
                                                <i class="ti ti-eye text-sm"></i>
                                            </a>
                                            <a
                                                href="/admin/transactions/{trx.id}/print-invoice"
                                                target="_blank"
                                                class="flex h-7 w-7 items-center justify-center rounded-md text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700"
                                                title="Cetak Invoice"
                                            >
                                                <i class="ti ti-printer text-sm"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="border-t border-slate-100 px-5 py-3">
                    <Pagination paginator={transactions} itemLabel="transaksi" />
                </div>
            {/if}
        </div>

    </main>

    <!-- QR Scan modal — preserved from original -->
    {#if scanModalOpen}
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
            onclick={(e) => { if (e.target === e.currentTarget) closeScanModal(); }}
        >
            <div class="w-full max-w-sm overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <h3 class="text-sm font-semibold text-slate-800">Scan QR / Barcode</h3>
                    <button
                        type="button"
                        onclick={closeScanModal}
                        class="flex h-7 w-7 items-center justify-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition-colors"
                        aria-label="Tutup"
                    >
                        <i class="ti ti-x text-sm"></i>
                    </button>
                </div>
                <div class="p-5">
                    <div id="qr-reader" class="rounded-xl overflow-hidden border border-slate-200"></div>
                    {#if scanError}
                        <p class="mt-3 text-center text-xs text-rose-500">{scanError}</p>
                    {/if}
                    {#if scannedValue}
                        <div class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-center">
                            <p class="text-xs font-semibold text-emerald-700">Ditemukan: {scannedValue}</p>
                        </div>
                    {/if}
                </div>
                <div class="border-t border-slate-100 px-5 py-3 text-right">
                    <button
                        type="button"
                        onclick={closeScanModal}
                        class="rounded-lg border border-slate-200 px-4 py-1.5 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    {/if}

</AdminLayout>
