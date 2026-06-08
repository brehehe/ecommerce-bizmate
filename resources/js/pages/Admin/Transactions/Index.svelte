<script lang="ts">
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { page, router } from '@inertiajs/svelte';
    import { Html5Qrcode } from 'html5-qrcode';
    import { onDestroy } from 'svelte';
    import { dragScroll } from '@/utils/dragScroll';

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
            
            return currentIdx !== -1 && targetIdx !== -1 && targetIdx > currentIdx;
        });

        if (validIds.length === 0) {
            showToast('Tidak ada transaksi terpilih yang dapat diperbarui ke status tersebut.', 'error');
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
</script>

<AdminLayout>
    <div class="flex-grow p-4 sm:p-8 w-full max-w-full mx-auto">
        <div class="space-y-6">
            <!-- Header -->
            <div
                class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4"
            >
                <div>
                    <h3 class="font-outfit font-black text-2xl text-slate-800">
                        Transaksi
                    </h3>
                    <p
                        class="text-xs text-slate-400 font-bold uppercase tracking-wider mt-1"
                    >
                        Kelola semua pesanan customer
                    </p>
                </div>
                <div
                    class="flex items-center gap-2 self-stretch sm:self-auto justify-end"
                >
                    <button
                        onclick={openScanModal}
                        class="px-5 py-2.5 rounded-xl text-xs font-bold text-white transition font-outfit uppercase tracking-wider shadow-lg flex items-center gap-1.5 hover:opacity-90 active:scale-95"
                        style="background:{secondary}; box-shadow: 0 4px 12px -2px {secondary}40;"
                    >
                        <i class="ti ti-scan text-sm"></i>
                        Scan QR / Barcode
                    </button>
                </div>
            </div>

            <!-- Status Tabs -->
            <div
                class="bg-white rounded-3xl border border-slate-200/80 shadow-card p-2 overflow-x-auto scrollbar-none flex gap-2"
            >
                <button
                    onclick={() => setStatusFilter('')}
                    class="px-4 py-2.5 rounded-2xl text-xs font-bold transition whitespace-nowrap flex items-center gap-2 border {filterStatus ===
                    ''
                        ? 'bg-slate-900 border-slate-900 text-white shadow-sm'
                        : 'bg-transparent border-slate-100 hover:bg-slate-50 text-slate-600'}"
                >
                    <i class="ti ti-layout-grid text-sm"></i>
                    Semua Transaksi
                </button>
                {#each Object.entries(statusLabels) as [key, label]}
                    {@const color = statusColors[key] ?? {
                        bg: '#f1f5f9',
                        text: '#475569',
                    }}
                    {@const icon = statusIcons[key] ?? 'ti-circle'}
                    {@const isActive = filterStatus === key}
                    <button
                        onclick={() => setStatusFilter(key)}
                        class="px-4 py-2.5 rounded-2xl text-xs font-bold transition whitespace-nowrap flex items-center gap-2 border"
                        style={isActive
                            ? `background: ${color.bg}; border-color: ${color.bg}; color: ${color.text}; box-shadow: 0 4px 12px -2px ${color.text}20;`
                            : `background: transparent; border-color: #f1f5f9; color: #475569;`}
                        onmouseenter={(e) => {
                            if (!isActive)
                                e.currentTarget.style.backgroundColor =
                                    '#f8fafc';
                        }}
                        onmouseleave={(e) => {
                            if (!isActive)
                                e.currentTarget.style.backgroundColor =
                                    'transparent';
                        }}
                    >
                        <i class="ti {icon} text-sm"></i>
                        {label}
                    </button>
                {/each}
            </div>

            <!-- Filters -->
            <div
                class="bg-white rounded-3xl border border-slate-200/80 shadow-card p-6"
            >
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <!-- Search -->
                    <div>
                        <label
                            for="search-input"
                            class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-1.5 font-outfit"
                            >Cari</label
                        >
                        <div class="relative">
                            <i
                                class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"
                            ></i>
                            <input
                                id="search-input"
                                type="text"
                                bind:value={filterSearch}
                                placeholder="No. transaksi / nama customer..."
                                class="w-full pl-8 pr-3 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-slate-300 bg-white transition"
                                onkeydown={(e: any) =>
                                    e.key === 'Enter' && applyFilters()}
                            />
                        </div>
                    </div>

                    <!-- Date From -->
                    <div>
                        <label
                            for="date-from-input"
                            class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-1.5 font-outfit"
                            >Dari Tanggal</label
                        >
                        <input
                            id="date-from-input"
                            type="date"
                            bind:value={filterDateFrom}
                            class="w-full px-3 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-slate-300 bg-white transition"
                        />
                    </div>

                    <!-- Date To -->
                    <div>
                        <label
                            for="date-to-input"
                            class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-1.5 font-outfit"
                            >Sampai Tanggal</label
                        >
                        <input
                            id="date-to-input"
                            type="date"
                            bind:value={filterDateTo}
                            class="w-full px-3 py-2.5 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-slate-300 bg-white transition"
                        />
                    </div>
                </div>

                <div class="flex gap-2 mt-4">
                    <button
                        onclick={applyFilters}
                        class="px-5 py-2.5 rounded-xl text-xs font-bold text-white transition font-outfit uppercase tracking-wider shadow-lg"
                        style="background:{primary}; box-shadow: 0 4px 12px -2px {primary}40;"
                    >
                        <i class="ti ti-filter mr-1 text-sm"></i>Filter
                    </button>
                    <button
                        onclick={resetFilters}
                        class="px-5 py-2.5 rounded-xl text-xs font-bold text-slate-500 border border-slate-200 hover:bg-slate-50 transition font-outfit uppercase tracking-wider"
                    >
                        Reset
                    </button>
                </div>
            </div>

            <!-- Bulk action bar (when items selected) -->
            {#if selectedIds.length > 0}
                <div
                    class="bg-white rounded-2xl border border-slate-200 shadow-md px-5 py-3 flex items-center gap-4 flex-wrap"
                >
                    <span class="text-sm font-bold text-slate-700">
                        <i
                            class="ti ti-checkbox text-base mr-1"
                            style="color:{primary}"
                        ></i>
                        {selectedIds.length} transaksi dipilih
                    </span>

                    <div class="h-5 w-px bg-slate-200 hidden sm:block"></div>

                    <div class="flex items-center gap-2">
                        <span
                            class="text-xs font-bold text-slate-500 uppercase tracking-wider"
                            >Ubah Status Massal:</span
                        >
                        <select
                            bind:value={bulkStatusValue}
                            class="px-3 py-1.5 text-xs font-bold border border-slate-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-slate-300 bg-white"
                        >
                            <option value="">-- Pilih Status --</option>
                            {#each Object.entries(statusLabels) as [key, label]}
                                <option value={key}>{label as string}</option>
                            {/each}
                        </select>

                        {#if bulkStatusValue === 'batal'}
                            <input
                                type="text"
                                bind:value={bulkCancelReason}
                                placeholder="Alasan batal..."
                                class="px-3 py-1.5 text-xs border border-slate-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-slate-300 bg-white w-48"
                            />
                        {/if}

                        <button
                            onclick={submitBulkStatus}
                            disabled={submittingBulkStatus || !bulkStatusValue}
                            class="px-4 py-1.5 rounded-xl text-xs font-bold text-white transition disabled:opacity-50"
                            style="background:{primary}"
                        >
                            {submittingBulkStatus ? 'Memproses...' : 'Terapkan'}
                        </button>
                    </div>

                    <button
                        onclick={() => {
                            selectedIds = [];
                            bulkStatusValue = '';
                            bulkCancelReason = '';
                        }}
                        class="text-xs text-slate-400 hover:text-slate-600 font-bold ml-auto"
                    >
                        Batalkan Pilihan
                    </button>
                </div>
            {/if}

            <!-- Table -->
            <div
                class="bg-white rounded-3xl border border-slate-200/80 shadow-card overflow-hidden"
            >
                {#if transactions.data.length === 0}
                    <div
                        class="flex flex-col items-center justify-center py-16 text-center"
                    >
                        <i
                            class="ti ti-shopping-cart-off text-5xl text-slate-200 mb-3"
                        ></i>
                        <p class="text-slate-500 font-semibold">
                            Tidak ada transaksi
                        </p>
                    </div>
                {:else}
                    <div class="overflow-x-auto" use:dragScroll>
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr
                                    class="border-b border-slate-100 bg-slate-50/50 text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit"
                                >
                                    <th class="py-5 px-4">
                                        <input
                                            type="checkbox"
                                            checked={allSelected}
                                            onchange={toggleSelectAll}
                                            class="w-4 h-4 rounded border-slate-300 cursor-pointer accent-blue-600"
                                        />
                                    </th>
                                    <th class="py-5 px-4">Transaksi / Tanggal</th>
                                    <th class="py-5 px-4">Customer</th>
                                    <th class="py-5 px-4">Total / Items</th>
                                    <th class="py-5 px-4">Status / Pembayaran</th>
                                    <th class="py-5 px-4">Nomor Resi</th>
                                    <th class="py-5 px-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-slate-100 text-slate-700 text-sm font-medium"
                            >
                                {#each transactions.data as trx}
                                    {@const statusStyle = statusColors[
                                        trx.status
                                    ] ?? { bg: '#f1f5f9', text: '#475569' }}
                                    {@const paymentStatus = trx.payment?.status}
                                    {@const isSelected = selectedIds.includes(
                                        trx.id,
                                    )}
                                    <tr
                                        class="hover:bg-slate-50/50 transition duration-150 border-b border-slate-100 {isSelected
                                            ? 'bg-blue-50/40'
                                            : ''}"
                                    >
                                        <td class="py-5 px-4">
                                            {#if trx.status !== 'selesai' && trx.status !== 'batal'}
                                                <input
                                                    type="checkbox"
                                                    checked={isSelected}
                                                    onchange={() =>
                                                        toggleSelect(trx.id)}
                                                    class="w-4 h-4 rounded border-slate-300 cursor-pointer accent-blue-600"
                                                />
                                            {/if}
                                        </td>
                                        <td class="py-5 px-4">
                                            <div class="flex flex-col gap-1">
                                                <p
                                                    class="font-black text-slate-800 tracking-wide font-mono text-sm"
                                                >
                                                    #{trx.transaction_number}
                                                </p>
                                                <div
                                                    class="flex flex-wrap gap-1"
                                                >
                                                    {#if (trx.items || []).every((item) => item.product?.is_digital)}
                                                        <span
                                                            class="inline-flex items-center gap-0.5 px-1.5 py-0.5 bg-blue-50 text-blue-600 text-[9px] font-black rounded uppercase tracking-wider border border-blue-200/30"
                                                        >
                                                            <i
                                                                class="ti ti-device-laptop text-[10px]"
                                                            ></i> Digital
                                                        </span>
                                                    {:else if (trx.items || []).some((item) => item.product?.is_digital)}
                                                        <span
                                                            class="inline-flex items-center gap-0.5 px-1.5 py-0.5 bg-amber-50 text-amber-600 text-[9px] font-black rounded uppercase tracking-wider border border-amber-200/30"
                                                        >
                                                            <i
                                                                class="ti ti-package text-[10px]"
                                                            ></i> Campuran
                                                        </span>
                                                    {/if}
                                                </div>
                                                <span
                                                    class="text-xs text-slate-400 font-bold"
                                                    >{fmtDate(trx.created_at)}</span
                                                >
                                            </div>
                                        </td>
                                        <td class="py-5 px-4">
                                            <p
                                                class="font-bold text-slate-800 whitespace-pre-wrap break-words"
                                            >
                                                {trx.user?.name ?? '-'}
                                            </p>
                                            <p
                                                class="text-[11px] text-slate-400 font-bold mt-0.5 break-all"
                                            >
                                                {trx.user?.email ?? ''}
                                            </p>
                                        </td>
                                        <td class="py-5 px-4">
                                            <div class="flex flex-col gap-1">
                                                <span
                                                    class="font-black text-slate-800"
                                                    >{fmt(
                                                        trx.grand_total,
                                                    )}</span
                                                >
                                                <span
                                                    class="text-xs text-slate-500 font-bold"
                                                    >{(trx.items ?? []).length} item</span
                                                >
                                                {#if trx.coins_redeemed > 0}
                                                    <span
                                                        class="text-[9px] font-black text-amber-600 flex items-center gap-0.5 mt-0.5"
                                                        title="Poin yang digunakan"
                                                    >
                                                        <i class="ti ti-coins"
                                                        ></i>
                                                        -{fmt(trx.coins_value)}
                                                    </span>
                                                {/if}
                                            </div>
                                        </td>
                                        <td class="py-5 px-4">
                                            <div class="flex flex-col gap-1.5 items-start">
                                                <span
                                                    class="text-[10px] font-black px-2.5 py-1 rounded-lg uppercase tracking-wider"
                                                    style="background:{statusStyle.bg}; color:{statusStyle.text}"
                                                >
                                                    {statusLabels[trx.status] ??
                                                        trx.status}
                                                </span>
                                                {#if paymentStatus === 'confirmed'}
                                                    <span
                                                        class="text-[10px] font-black px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-600 border border-emerald-200/50 uppercase tracking-wider"
                                                        >Dikonfirmasi</span
                                                    >
                                                {:else if paymentStatus === 'rejected'}
                                                    <span
                                                        class="text-[10px] font-black px-2.5 py-1 rounded-lg bg-rose-50 text-rose-600 border border-rose-200/50 uppercase tracking-wider"
                                                        >Ditolak</span
                                                    >
                                                {:else if trx.payment?.proof_image}
                                                    <span
                                                        class="text-[10px] font-black px-2.5 py-1 rounded-lg bg-amber-50 text-amber-600 border border-amber-200/50 uppercase tracking-wider"
                                                        >Menunggu Review</span
                                                    >
                                                {:else}
                                                    <span
                                                        class="text-xs text-slate-400 font-bold"
                                                        >Belum ada bukti</span
                                                    >
                                                {/if}
                                            </div>
                                        </td>
                                        <!-- Nomor Resi Column -->
                                        <td class="py-5 px-4">
                                            {#if trx.shipping_courier === 'digital'}
                                                <span
                                                    class="inline-flex items-center gap-1 text-[10px] font-black px-2.5 py-1.5 rounded-lg bg-blue-50 text-blue-600 border border-blue-200/50 uppercase tracking-wider"
                                                >
                                                    <i
                                                        class="ti ti-mail text-xs"
                                                    ></i> Pengiriman Digital
                                                </span>
                                            {:else if trx.shipping_courier === 'self_pickup'}
                                                <span
                                                    class="inline-flex items-center gap-1 text-[10px] font-black px-2.5 py-1.5 rounded-lg bg-emerald-50 text-emerald-600 border border-emerald-200/50 uppercase tracking-wider"
                                                >
                                                    <i
                                                        class="ti ti-store text-xs"
                                                    ></i> Ambil di Toko
                                                </span>
                                            {:else if trx.tracking_number}
                                                <div
                                                    class="flex flex-col gap-0.5"
                                                >
                                                    <span
                                                        class="font-bold text-slate-800 text-xs font-mono"
                                                        >{trx.tracking_number}</span
                                                    >
                                                    {#if trx.courier_name}
                                                        <span
                                                            class="text-[10px] text-slate-400 font-semibold"
                                                            >{trx.courier_name}</span
                                                        >
                                                    {/if}
                                                    {#if trx.status !== 'selesai' && trx.status !== 'batal'}
                                                        <button
                                                            onclick={() =>
                                                                openResiModal(
                                                                    trx,
                                                                )}
                                                            class="text-[10px] font-bold mt-0.5 underline text-left"
                                                            style="color:{primary}"
                                                        >
                                                            Ubah Resi
                                                        </button>
                                                    {/if}
                                                </div>
                                            {:else if trx.status !== 'selesai' && trx.status !== 'batal'}
                                                <button
                                                    onclick={() =>
                                                        openResiModal(trx)}
                                                    class="inline-flex items-center gap-1 text-[10px] font-bold px-2.5 py-1.5 rounded-lg border-2 border-dashed transition hover:bg-orange-50"
                                                    style="border-color:{secondary}; color:{secondary}"
                                                >
                                                    <i
                                                        class="ti ti-truck-delivery text-xs"
                                                    ></i>
                                                    Input Resi
                                                </button>
                                            {:else}
                                                <span
                                                    class="text-xs text-slate-400 font-semibold"
                                                    >Resi tidak tersedia</span
                                                >
                                            {/if}
                                        </td>
                                        <td class="py-5 px-4 text-center">
                                            <div
                                                class="flex items-center justify-center gap-1.5"
                                            >
                                                <a
                                                    href="/admin/transactions/{trx.id}"
                                                    class="inline-flex items-center gap-1 text-xs font-bold px-3 py-2 rounded-xl text-white transition font-outfit uppercase tracking-wider"
                                                    style="background:{primary};"
                                                    title="Detail Transaksi"
                                                >
                                                    <i class="ti ti-eye text-sm"
                                                    ></i>
                                                </a>
                                                <a
                                                    href="/admin/transactions/{trx.id}/print-invoice"
                                                    target="_blank"
                                                    class="inline-flex items-center justify-center w-8.5 h-8.5 rounded-xl border border-slate-200 text-slate-500 hover:bg-slate-100 transition"
                                                    title="Cetak Invoice"
                                                >
                                                    <i
                                                        class="ti ti-printer text-base"
                                                    ></i>
                                                </a>
                                                {#if trx.tracking_number}
                                                    <a
                                                        href="/admin/transactions/{trx.id}/print-shipping-label"
                                                        target="_blank"
                                                        class="inline-flex items-center justify-center w-8.5 h-8.5 rounded-xl border border-slate-200 text-slate-500 hover:bg-slate-100 transition"
                                                        title="Cetak Resi"
                                                    >
                                                        <i
                                                            class="ti ti-barcode text-base"
                                                        ></i>
                                                    </a>
                                                {/if}
                                                {#if trx.shipping_courier === 'store_courier'}
                                                    <a
                                                        href="/admin/transactions/{trx.id}/print-surat-jalan"
                                                        target="_blank"
                                                        class="inline-flex items-center justify-center w-8.5 h-8.5 rounded-xl border border-emerald-200 text-emerald-600 hover:bg-emerald-50 transition"
                                                        title="Cetak Surat Jalan"
                                                    >
                                                        <i
                                                            class="ti ti-truck text-base"
                                                        ></i>
                                                    </a>
                                                {/if}
                                            </div>
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    {#if transactions.last_page > 1}
                        <div
                            class="flex items-center justify-between px-6 py-5 border-t border-slate-100 bg-slate-50/20"
                        >
                            <p
                                class="text-xs font-bold text-slate-500 font-outfit"
                            >
                                Menampilkan {transactions.from}–{transactions.to}
                                dari {transactions.total} transaksi
                            </p>
                            <div class="flex gap-1">
                                {#if transactions.prev_page_url}
                                    <a
                                        aria-label="Halaman sebelumnya"
                                        href={transactions.prev_page_url}
                                        class="w-8 h-8 rounded-lg border border-slate-200 text-slate-500 hover:bg-brand-blueLight hover:text-brand-blueRoyal flex items-center justify-center transition"
                                    >
                                        <i class="ti ti-chevron-left"></i>
                                    </a>
                                {/if}
                                {#if transactions.next_page_url}
                                    <a
                                        aria-label="Halaman selanjutnya"
                                        href={transactions.next_page_url}
                                        class="w-8 h-8 rounded-lg border border-slate-200 text-slate-500 hover:bg-brand-blueLight hover:text-brand-blueRoyal flex items-center justify-center transition"
                                    >
                                        <i class="ti ti-chevron-right"></i>
                                    </a>
                                {/if}
                            </div>
                        </div>
                    {/if}
                {/if}
            </div>
        </div>
    </div>
</AdminLayout>

<!-- ═══════════════════════════════════
     MODAL INPUT NOMOR RESI
═══════════════════════════════════ -->
{#if showResiModal}
    <!-- Backdrop -->
    <div
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        style="background:rgba(15,23,42,0.55); backdrop-filter:blur(4px);"
        role="dialog"
        aria-modal="true"
    >
        <div
            class="bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden"
            onclick={(e) => e.stopPropagation()}
            role="presentation"
        >
            <!-- Modal header -->
            <div
                class="px-6 pt-6 pb-4 border-b border-slate-100 flex items-start gap-4"
            >
                <div
                    class="w-11 h-11 rounded-2xl flex items-center justify-center shrink-0"
                    style="background:{secondary}18;"
                >
                    <i
                        class="ti ti-truck-delivery text-xl"
                        style="color:{secondary}"
                    ></i>
                </div>
                <div class="flex-1 min-w-0">
                    <h3
                        class="font-outfit font-black text-slate-800 text-base leading-tight"
                    >
                        Input Nomor Resi
                    </h3>
                    <p
                        class="text-xs text-slate-400 font-semibold mt-0.5 truncate"
                    >
                        {resiTransactionNumber}
                    </p>
                </div>
                <button
                    type="button"
                    aria-label="Tutup"
                    onclick={closeResiModal}
                    class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition shrink-0"
                >
                    <i class="ti ti-x text-sm text-slate-500"></i>
                </button>
            </div>

            <!-- Modal body -->
            <div class="px-6 py-5 space-y-4">
                <!-- Info banner -->
                <div
                    class="flex items-start gap-3 p-3 rounded-xl bg-orange-50 border border-orange-200/70"
                >
                    <i
                        class="ti ti-info-circle text-sm text-orange-500 mt-0.5 shrink-0"
                    ></i>
                    <p
                        class="text-xs text-orange-700 font-medium leading-relaxed"
                    >
                        Setelah nomor resi disimpan, status transaksi akan <strong
                            >otomatis berubah ke "Dikirim"</strong
                        >.
                    </p>
                </div>

                <!-- Nomor Resi -->
                <div>
                    <label
                        for="resi-input"
                        class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-1.5"
                    >
                        Nomor Resi <span class="text-red-500">*</span>
                    </label>
                    <input
                        id="resi-input"
                        type="text"
                        bind:value={resiInput}
                        placeholder="Contoh: JNE1234567890"
                        class="w-full px-4 py-3 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 transition font-mono font-bold tracking-wider"
                        style="focus:ring-color:{primary}"
                        onkeydown={(e) => e.key === 'Enter' && submitResi()}
                    />
                </div>

                <!-- Nama Kurir -->
                <div>
                    <label
                        for="courier-input"
                        class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-1.5"
                    >
                        Nama Kurir <span
                            class="text-slate-300 font-normal normal-case"
                            >(opsional)</span
                        >
                    </label>
                    <input
                        id="courier-input"
                        type="text"
                        bind:value={courierInput}
                        placeholder="Contoh: JNE, JT Express, SiCepat, dll."
                        class="w-full px-4 py-3 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 transition"
                    />
                </div>
            </div>

            <!-- Modal footer -->
            <div class="px-6 pb-6 flex gap-3">
                <button
                    onclick={closeResiModal}
                    disabled={submittingResi}
                    class="flex-1 py-3 rounded-xl border-2 border-slate-200 text-slate-600 text-sm font-bold hover:bg-slate-50 transition disabled:opacity-50"
                >
                    Batal
                </button>
                <button
                    onclick={submitResi}
                    disabled={submittingResi || !resiInput.trim()}
                    class="flex-1 py-3 rounded-xl text-white text-sm font-bold transition disabled:opacity-50 flex items-center justify-center gap-2"
                    style="background:{secondary}; box-shadow: 0 4px 14px -2px {secondary}50;"
                >
                    {#if submittingResi}
                        <i class="ti ti-loader-2 animate-spin text-sm"></i>
                        Menyimpan...
                    {:else}
                        <i class="ti ti-truck-delivery text-sm"></i>
                        Simpan Resi
                    {/if}
                </button>
            </div>
        </div>
    </div>
{/if}

<!-- Bulk Input Resi Modal (Resi Massal) -->
{#if showBulkResiModal}
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div
            class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"
            onclick={() => {
                showBulkResiModal = false;
            }}
            onkeydown={() => {
                showBulkResiModal = false;
            }}
            role="button"
            tabindex="0"
        ></div>
        <div
            class="relative z-10 bg-white rounded-3xl border border-slate-200 shadow-2xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[90vh]"
        >
            <!-- Header -->
            <div
                class="px-6 py-5 border-b border-slate-100 flex items-center justify-between"
            >
                <div>
                    <h3
                        class="font-outfit font-black text-slate-800 text-lg uppercase tracking-wider"
                    >
                        Input Resi Massal
                    </h3>
                    <p class="text-xs text-slate-400 font-semibold mt-0.5">
                        Masukkan nomor resi untuk {bulkTrackingData.length} transaksi
                        yang dipilih.
                    </p>
                </div>
                <button
                    type="button"
                    aria-label="Tutup"
                    onclick={() => {
                        showBulkResiModal = false;
                    }}
                    class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition shrink-0"
                >
                    <i class="ti ti-x text-sm text-slate-500"></i>
                </button>
            </div>

            <!-- Body (Scrollable table of inputs) -->
            <div class="p-6 overflow-y-auto space-y-4 flex-1">
                <div
                    class="flex items-start gap-3 p-3 rounded-xl bg-orange-50 border border-orange-200/70 mb-2"
                >
                    <i
                        class="ti ti-info-circle text-sm text-orange-500 mt-0.5 shrink-0"
                    ></i>
                    <p
                        class="text-xs text-orange-700 font-medium leading-relaxed"
                    >
                        Setelah disimpan, nomor resi masing-masing transaksi
                        akan di-update, dan statusnya otomatis berubah menjadi <strong
                            >"Dikirim"</strong
                        >.
                    </p>
                </div>

                <div class="space-y-4">
                    {#each bulkTrackingData as row, i (row.id)}
                        <div
                            class="p-4 bg-slate-50 border border-slate-200/80 rounded-2xl flex flex-col md:flex-row gap-4 items-start md:items-center"
                        >
                            <div class="flex-1 min-w-0">
                                <span
                                    class="text-[10px] font-black text-slate-400 uppercase tracking-widest block"
                                    >No. Transaksi</span
                                >
                                <span
                                    class="text-xs font-mono font-black text-slate-800 block mt-0.5"
                                    >{row.transaction_number}</span
                                >
                            </div>

                            <!-- Nomor Resi Input -->
                            <div class="w-full md:w-48 shrink-0">
                                <label
                                    for={`bulk-resi-input-${row.id}`}
                                    class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1"
                                >
                                    Resi <span class="text-red-500">*</span>
                                </label>
                                <input
                                    id={`bulk-resi-input-${row.id}`}
                                    type="text"
                                    bind:value={row.tracking_number}
                                    placeholder="Resi"
                                    class="w-full px-3 py-2 text-xs font-mono font-bold border border-slate-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-slate-400 bg-white"
                                />
                            </div>

                            <!-- Courier Name Input -->
                            <div class="w-full md:w-36 shrink-0">
                                <label
                                    for={`bulk-courier-input-${row.id}`}
                                    class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1"
                                >
                                    Kurir
                                </label>
                                <input
                                    id={`bulk-courier-input-${row.id}`}
                                    type="text"
                                    bind:value={row.courier_name}
                                    placeholder="JNE, J&T, etc."
                                    class="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-slate-400 bg-white"
                                />
                            </div>
                        </div>
                    {/each}
                </div>
            </div>

            <!-- Footer -->
            <div
                class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex gap-3"
            >
                <button
                    onclick={() => {
                        showBulkResiModal = false;
                    }}
                    disabled={submittingBulkResi}
                    class="flex-1 py-3 rounded-xl border border-slate-200 text-slate-500 bg-white text-xs font-bold hover:bg-slate-50 transition disabled:opacity-50 font-outfit uppercase tracking-wider"
                >
                    Batal
                </button>
                <button
                    onclick={submitBulkTracking}
                    disabled={submittingBulkResi ||
                        bulkTrackingData.some((d) => !d.tracking_number.trim())}
                    class="flex-1 py-3 rounded-xl text-white text-xs font-bold transition disabled:opacity-50 flex items-center justify-center gap-2 font-outfit uppercase tracking-wider"
                    style="background:{secondary}; box-shadow: 0 4px 14px -2px {secondary}50;"
                >
                    {#if submittingBulkResi}
                        <i class="ti ti-loader-2 animate-spin text-sm"></i>
                        Menyimpan...
                    {:else}
                        <i class="ti ti-truck-delivery text-sm"></i>
                        Simpan Semua Resi
                    {/if}
                </button>
            </div>
        </div>
    </div>
{/if}

<!-- Scan QR / Barcode Modal -->
{#if showScanModal}
    <div
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        onclick={closeScanModal}
        onkeypress={closeScanModal}
        role="button"
        tabindex="-1"
    >
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
        <!-- svelte-ignore a11y_no_noninteractive_element_interactions -->
        <!-- svelte-ignore a11y_click_events_have_key_events -->
        <div
            class="relative z-10 bg-white rounded-3xl border border-slate-200 shadow-2xl p-6 w-full max-w-md flex flex-col max-h-[90vh]"
            onclick={(e: any) => e.stopPropagation()}
            onkeypress={(e: any) => e.stopPropagation()}
            role="document"
        >
            <!-- Header -->
            <div
                class="flex items-center justify-between pb-4 border-b border-slate-100"
            >
                <div class="flex items-center gap-2">
                    <i class="ti ti-scan text-xl" style="color:{secondary}"></i>
                    <h3
                        class="font-outfit font-black text-slate-800 text-lg uppercase tracking-wider"
                    >
                        Scan QR / Barcode
                    </h3>
                </div>
                <button
                    onclick={closeScanModal}
                    class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition shrink-0"
                    aria-label="Tutup"
                >
                    <i class="ti ti-x text-sm text-slate-500"></i>
                </button>
            </div>

            <!-- Body -->
            <div
                class="py-6 space-y-5 overflow-y-auto flex-1 flex flex-col items-center"
            >
                <!-- Camera stream -->
                <div class="w-full flex flex-col items-center">
                    <div
                        class="relative w-full max-w-[280px] aspect-square bg-slate-950 rounded-2xl overflow-hidden border border-slate-800 shadow-inner flex items-center justify-center"
                    >
                        <div
                            id="scanner-reader"
                            class="w-full h-full object-cover"
                        ></div>

                        {#if !isScanning && !scanSubmitting}
                            <div
                                class="absolute inset-0 flex flex-col items-center justify-center p-4 text-center bg-slate-950/80"
                            >
                                <i
                                    class="ti ti-camera-off text-3xl text-slate-600 mb-2"
                                ></i>
                                <p class="text-xs text-slate-400 font-medium">
                                    Kamera tidak aktif
                                </p>
                                <button
                                    onclick={startScanning}
                                    class="mt-3 px-4 py-2 text-white rounded-xl text-xs font-bold transition hover:opacity-90 active:scale-95"
                                    style="background:{primary}"
                                >
                                    Aktifkan Kamera
                                </button>
                            </div>
                        {/if}

                        {#if isScanning}
                            <!-- Laser scan line animation -->
                            <div
                                class="absolute left-0 right-0 h-0.5 bg-red-500 shadow-[0_0_8px_rgba(239,68,68,0.8)] animate-laser pointer-events-none z-10"
                            ></div>
                            <!-- Crosshair border indicators -->
                            <div
                                class="absolute inset-8 border border-white/20 pointer-events-none rounded-xl"
                            ></div>
                        {/if}

                        {#if scanSubmitting}
                            <div
                                class="absolute inset-0 bg-slate-950/70 backdrop-blur-xs flex flex-col items-center justify-center z-20"
                            >
                                <i
                                    class="ti ti-loader-2 animate-spin text-3xl text-white mb-2"
                                ></i>
                                <p class="text-xs text-white font-bold">
                                    Mencari Transaksi...
                                </p>
                            </div>
                        {/if}
                    </div>

                    <!-- Camera Selector -->
                    {#if availableCameras.length > 1}
                        <div class="w-full max-w-[280px] mt-3">
                            <label
                                for="camera-select"
                                class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1"
                            >
                                Sumber Kamera
                            </label>
                            <select
                                id="camera-select"
                                value={selectedCameraId}
                                onchange={(e: any) =>
                                    changeCamera(e.target.value)}
                                class="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-slate-300 bg-white"
                            >
                                {#each availableCameras as cam}
                                    <option value={cam.id}
                                        >{cam.label ||
                                            `Kamera ${cam.id}`}</option
                                    >
                                {/each}
                            </select>
                        </div>
                    {/if}
                </div>

                <!-- Error Message -->
                {#if scanError}
                    <div
                        class="w-full p-3 rounded-xl bg-red-50 border border-red-200 flex items-start gap-2.5 text-red-700"
                    >
                        <i class="ti ti-alert-circle text-base shrink-0 mt-0.5"
                        ></i>
                        <p
                            class="text-xs font-semibold leading-relaxed break-words"
                        >
                            {scanError}
                        </p>
                    </div>
                {/if}

                <!-- Manual / Scanner Gun Input -->
                <div class="w-full border-t border-slate-100 pt-4">
                    <form
                        onsubmit={(e) => {
                            e.preventDefault();
                            submitScannedCode();
                        }}
                        class="space-y-2"
                    >
                        <label
                            for="scanner-manual-input"
                            class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest font-outfit"
                        >
                            Scan via Scanner Gun / Input Manual
                        </label>
                        <div class="flex gap-2">
                            <div class="relative flex-1">
                                <i
                                    class="ti ti-barcode absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"
                                ></i>
                                <input
                                    id="scanner-manual-input"
                                    type="text"
                                    bind:this={scannerInputEl}
                                    bind:value={scanInputValue}
                                    placeholder="Contoh: TRX-20260601-00001 / Resi"
                                    class="w-full pl-8 pr-3 py-2.5 text-xs font-mono font-bold tracking-wider border border-slate-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-slate-300 bg-white transition"
                                />
                            </div>
                            <button
                                type="submit"
                                disabled={scanSubmitting ||
                                    !scanInputValue.trim()}
                                class="px-4 py-2.5 rounded-xl text-xs font-bold text-white transition disabled:opacity-50 font-outfit uppercase tracking-wider shrink-0"
                                style="background:{primary};"
                            >
                                Cari
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
{/if}

<!-- Toast notification -->
{#if toastVisible}
    <div
        class="fixed bottom-6 right-6 z-[100] flex items-center gap-3 px-5 py-3.5 rounded-2xl shadow-2xl text-white text-sm font-bold transition-all duration-300"
        style="background:{toastType === 'success' ? '#22c55e' : '#ef4444'};"
    >
        <i
            class="ti {toastType === 'success'
                ? 'ti-circle-check'
                : 'ti-circle-x'} text-base"
        ></i>
        {toastMsg}
    </div>
{/if}

<style>
    .scrollbar-none::-webkit-scrollbar {
        display: none;
    }
    .scrollbar-none {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
    @keyframes laser {
        0%,
        100% {
            top: 5%;
        }
        50% {
            top: 95%;
        }
    }
    .animate-laser {
        animation: laser 2s infinite linear;
    }
</style>
