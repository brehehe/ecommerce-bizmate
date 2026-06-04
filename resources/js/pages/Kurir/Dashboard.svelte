<script lang="ts">
    import { page, router, Link } from '@inertiajs/svelte';
    import { showToast } from '@/utils/toast';
    import { onMount } from 'svelte';

    let {
        transactions,
        statusLabels = {},
        filters = {},
        storeName = '',
        storeLogo = '',
    } = $props();

    const primary = $derived((page.props as any).theme?.primary_color ?? '#0c4cb4');
    const secondary = $derived((page.props as any).theme?.secondary_color ?? '#fa7315');
    const currentUser = $derived((page.props as any).auth?.user ?? null);

    const shownFlashIds = new Set();
    $effect(() => {
        const flash = (page.props as any).flash;
        if (!flash || !flash.id || shownFlashIds.has(flash.id)) return;
        if (flash.success) { showToast(flash.success, 'success'); shownFlashIds.add(flash.id); }
        if (flash.error) { showToast(flash.error, 'error'); shownFlashIds.add(flash.id); }
    });

    // Search state
    let searchInput = $state((filters as any).search ?? '');
    let activeTab = $state((filters as any).tab ?? 'my_tasks');

    function applyFilters() {
        const params: Record<string, string> = {};
        if (searchInput.trim()) {
            params.search = searchInput.trim();
        }
        if (activeTab) {
            params.tab = activeTab;
        }
        router.get('/kurir/dashboard', params, { preserveScroll: true });
    }

    function setTab(tab: string) {
        activeTab = tab;
        const params: Record<string, string> = {};
        if (searchInput.trim()) {
            params.search = searchInput.trim();
        }
        if (tab) {
            params.tab = tab;
        }
        router.get('/kurir/dashboard', params, { preserveScroll: true });
    }

    // Scanner state
    let showScanModal = $state(false);
    let scanInput = $state('');
    let scanning = $state(false);
    let scannerError = $state('');
    let html5QrCode: any = null;
    let scannerStarted = $state(false);

    async function startScanner() {
        scannerError = '';
        try {
            const { Html5Qrcode } = await import('html5-qrcode');
            html5QrCode = new Html5Qrcode('kurir-qr-reader');
            await html5QrCode.start(
                { facingMode: 'environment' },
                { fps: 10, qrbox: { width: 250, height: 250 } },
                (decodedText: string) => {
                    stopScanner();
                    scanInput = decodedText.trim();
                    handleScan();
                },
                () => {}
            );
            scannerStarted = true;
        } catch (err: any) {
            scannerError = 'Kamera tidak dapat diakses. Gunakan input manual.';
            scannerStarted = false;
        }
    }

    function stopScanner() {
        if (html5QrCode && scannerStarted) {
            html5QrCode.stop().catch(() => {});
            html5QrCode.clear();
            scannerStarted = false;
        }
    }

    function closeScanModal() {
        stopScanner();
        showScanModal = false;
        scanInput = '';
        scannerError = '';
    }

    $effect(() => {
        if (showScanModal) {
            setTimeout(() => startScanner(), 300);
        }
    });

    async function handleScan() {
        if (!scanInput.trim()) {
            showToast('Masukkan kode transaksi.', 'error');
            return;
        }
        scanning = true;
        scannerError = '';

        try {
            const res = await fetch(`/kurir/scan/${encodeURIComponent(scanInput.trim())}`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await res.json();

            if (data.success) {
                closeScanModal();
                router.visit(data.redirect_url);
            } else {
                scannerError = data.message ?? 'Transaksi tidak ditemukan.';
            }
        } catch {
            scannerError = 'Gagal menghubungi server.';
        } finally {
            scanning = false;
        }
    }

    function logout() {
        router.post('/kurir/logout');
    }

    // Status badge helpers
    const statusColors: Record<string, string> = {
        menunggu: 'bg-yellow-100 text-yellow-800',
        diproses: 'bg-blue-100 text-blue-800',
        dikemas: 'bg-indigo-100 text-indigo-800',
        out_for_pickup: 'bg-orange-100 text-orange-800',
        dikirim: 'bg-cyan-100 text-cyan-800',
        selesai: 'bg-emerald-100 text-emerald-800',
    };

    function statusBadge(status: string) {
        return statusColors[status] ?? 'bg-slate-100 text-slate-600';
    }

    const filterTabs = [
        { key: 'my_tasks', label: 'Tugas Saya' },
        { key: 'available', label: 'Belum Dipickup' },
        { key: 'history', label: 'Riwayat Selesai' },
    ];

    function fmtDate(d: string) {
        if (!d) return '-';
        return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
    }
</script>

<svelte:head>
    <title>Dashboard Kurir — {storeName}</title>
</svelte:head>

<!-- Mobile-first Layout -->
<div class="min-h-screen font-sans" style="background: #f8fafc;">

    <!-- Top Navigation Bar -->
    <header class="sticky top-0 z-30 bg-white border-b border-slate-200 shadow-sm">
        <div class="max-w-2xl mx-auto px-4 h-16 flex items-center justify-between gap-4">
            <!-- Logo & Name -->
            <div class="flex items-center gap-3">
                {#if storeLogo}
                    <img src={storeLogo} alt={storeName} class="h-9 w-9 object-contain rounded-xl border border-slate-100" />
                {:else}
                    <div class="h-9 w-9 rounded-xl flex items-center justify-center text-white shrink-0" style="background: linear-gradient(135deg, {primary}, #1e40af);">
                        <i class="ti ti-truck-delivery text-lg"></i>
                    </div>
                {/if}
                <div class="min-w-0">
                    <div class="text-sm font-black text-slate-900 truncate" style="font-family: 'Outfit', sans-serif;">{storeName}</div>
                    <div class="text-[10px] font-bold text-slate-400 tracking-widest uppercase">Portal Kurir</div>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <!-- Scan button -->
                <button
                    id="btn-open-scanner"
                    onclick={() => showScanModal = true}
                    class="flex items-center gap-1.5 px-3 py-2 rounded-xl text-white text-xs font-bold shadow-sm transition hover:-translate-y-0.5 active:scale-95"
                    style="background: linear-gradient(135deg, {primary}, #1e40af);"
                >
                    <i class="ti ti-qrcode text-base"></i>
                    <span class="hidden sm:inline">Scan</span>
                </button>

                <!-- Logout -->
                <button
                    onclick={logout}
                    class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-xl transition"
                    title="Keluar"
                >
                    <i class="ti ti-logout text-xl"></i>
                </button>
            </div>
        </div>
    </header>

    <!-- Courier greeting card -->
    {#if currentUser}
        <div class="max-w-2xl mx-auto px-4 pt-5 pb-2">
            <div class="rounded-2xl p-4 text-white" style="background: linear-gradient(135deg, {primary}, #1e40af);">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-2xl bg-white/20 flex items-center justify-center shrink-0">
                        <i class="ti ti-user-circle text-2xl"></i>
                    </div>
                    <div>
                        <p class="text-xs text-blue-200 font-medium">Hai, Kurir!</p>
                        <p class="font-black text-lg leading-tight" style="font-family: 'Outfit', sans-serif;">{currentUser.name}</p>
                    </div>
                    <div class="ml-auto text-right">
                        <p class="text-xs text-blue-200">Total Pesanan</p>
                        <p class="text-2xl font-black">{transactions?.total ?? 0}</p>
                    </div>
                </div>
            </div>
        </div>
    {/if}

    <!-- Search & Filters -->
    <div class="max-w-2xl mx-auto px-4 py-3 space-y-3">
        <!-- Search bar -->
        <div class="relative">
            <i class="ti ti-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg pointer-events-none"></i>
            <input
                type="text"
                id="kurir-search"
                bind:value={searchInput}
                placeholder="Cari no. transaksi, kode booking, nama pelanggan..."
                class="w-full pl-11 pr-20 py-3.5 bg-white border border-slate-200 rounded-2xl text-sm focus:outline-none focus:ring-2 focus:border-transparent transition"
                style="--tw-ring-color: {primary}40;"
                onkeydown={(e) => e.key === 'Enter' && applyFilters()}
            />
            <button
                onclick={applyFilters}
                class="absolute right-2 top-1/2 -translate-y-1/2 px-3 py-2 rounded-xl text-white text-xs font-bold transition"
                style="background-color: {primary};"
            >
                Cari
            </button>
        </div>

        <!-- Status filter tabs -->
        <div class="flex gap-2 overflow-x-auto pb-1 scrollbar-hide">
            {#each filterTabs as tab}
                <button
                    onclick={() => setTab(tab.key)}
                    class="px-4 py-2 rounded-full text-xs font-bold whitespace-nowrap transition shrink-0 {activeTab === tab.key
                        ? 'text-white shadow-sm'
                        : 'bg-white text-slate-600 border border-slate-200 hover:border-slate-300'}"
                    style={activeTab === tab.key ? `background-color: ${primary};` : ''}
                >
                    {tab.label}
                </button>
            {/each}
        </div>
    </div>

    <!-- Transaction list -->
    <div class="max-w-2xl mx-auto px-4 pb-8 space-y-3">
        {#if transactions?.data?.length > 0}
            {#each transactions.data as trx}
                <Link
                    href={`/kurir/transactions/${trx.id}`}
                    class="block bg-white rounded-2xl border border-slate-100 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all overflow-hidden active:scale-[0.99]"
                >
                    <div class="p-4">
                        <!-- Header row -->
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <i class="ti ti-receipt text-slate-400 text-sm"></i>
                                    <span class="text-xs font-black text-slate-700">{trx.transaction_number}</span>
                                </div>
                                {#if trx.booking_code}
                                    <div class="flex items-center gap-2">
                                        <i class="ti ti-barcode text-slate-300 text-sm"></i>
                                        <span class="text-[11px] text-slate-400 font-mono">{trx.booking_code}</span>
                                    </div>
                                {/if}
                            </div>
                            <span class="px-2.5 py-1 rounded-full text-[11px] font-black shrink-0 {statusBadge(trx.status)}">
                                {statusLabels[trx.status] ?? trx.status}
                            </span>
                        </div>

                        <!-- Customer info -->
                        <div class="flex items-center gap-2 mb-3">
                            <div class="w-8 h-8 rounded-xl bg-slate-100 flex items-center justify-center shrink-0">
                                <i class="ti ti-user text-slate-500 text-sm"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-slate-800 truncate">{trx.user?.name ?? '-'}</p>
                                <p class="text-xs text-slate-400 truncate">{trx.customer_address?.city ?? trx.customer_address?.regency_name ?? '-'}</p>
                            </div>
                        </div>

                        <!-- Address snippet -->
                        {#if trx.customer_address?.address}
                            <div class="flex items-start gap-2 mb-3">
                                <i class="ti ti-map-pin text-slate-300 text-sm mt-0.5 shrink-0"></i>
                                <p class="text-xs text-slate-500 line-clamp-2">{trx.customer_address.address}</p>
                            </div>
                        {/if}

                        <!-- Footer -->
                        <div class="flex items-center justify-between pt-3 border-t border-slate-50">
                            <span class="text-xs text-slate-400">{fmtDate(trx.created_at)}</span>
                            {#if trx.tracking_number}
                                <span class="flex items-center gap-1 text-xs text-emerald-600 font-bold">
                                    <i class="ti ti-truck text-sm"></i>
                                    {trx.tracking_number}
                                </span>
                            {:else if trx.status === 'dikirim'}
                                <span class="text-xs text-orange-500 font-bold">Belum ada resi</span>
                            {/if}
                            <i class="ti ti-chevron-right text-slate-300 text-lg"></i>
                        </div>
                    </div>
                </Link>
            {/each}

            <!-- Pagination -->
            {#if transactions.last_page > 1}
                <div class="flex justify-center items-center gap-2 pt-4">
                    {#if transactions.prev_page_url}
                        <button
                            onclick={() => router.visit(transactions.prev_page_url)}
                            class="px-4 py-2 bg-white border border-slate-200 rounded-xl text-sm font-bold text-slate-600 hover:bg-slate-50 transition"
                        >
                            <i class="ti ti-chevron-left"></i> Prev
                        </button>
                    {/if}
                    <span class="text-sm text-slate-500 font-medium">
                        {transactions.current_page} / {transactions.last_page}
                    </span>
                    {#if transactions.next_page_url}
                        <button
                            onclick={() => router.visit(transactions.next_page_url)}
                            class="px-4 py-2 bg-white border border-slate-200 rounded-xl text-sm font-bold text-slate-600 hover:bg-slate-50 transition"
                        >
                            Next <i class="ti ti-chevron-right"></i>
                        </button>
                    {/if}
                </div>
            {/if}

        {:else}
            <!-- Empty state -->
            <div class="flex flex-col items-center justify-center py-20 text-center">
                <div class="w-20 h-20 rounded-3xl bg-slate-100 flex items-center justify-center mb-4">
                    <i class="ti ti-package-off text-slate-400 text-4xl"></i>
                </div>
                <h3 class="text-lg font-black text-slate-800 mb-2">Tidak Ada Pesanan</h3>
                <p class="text-sm text-slate-500 max-w-xs">
                    {searchInput || activeTab !== 'my_tasks' ? 'Tidak ditemukan pesanan yang cocok. Coba ubah filter.' : 'Belum ada pesanan tugas saya yang perlu diantar saat ini.'}
                </p>
                {#if searchInput || activeTab !== 'my_tasks'}
                    <button
                        onclick={() => { searchInput = ''; activeTab = 'my_tasks'; router.get('/kurir/dashboard'); }}
                        class="mt-4 px-4 py-2 rounded-xl text-sm font-bold text-white transition"
                        style="background-color: {primary};"
                    >
                        Reset Filter
                    </button>
                {/if}
            </div>
        {/if}
    </div>

    <!-- Floating Scan Button (mobile) -->
    <button
        id="btn-fab-scanner"
        onclick={() => showScanModal = true}
        class="fixed bottom-6 right-6 w-14 h-14 rounded-2xl text-white flex items-center justify-center shadow-xl hover:shadow-2xl transition-all hover:-translate-y-1 active:scale-95 z-20 lg:hidden"
        style="background: linear-gradient(135deg, {primary}, #1e40af);"
        title="Scan Kode Pesanan"
    >
        <i class="ti ti-qrcode text-2xl"></i>
    </button>
</div>

<!-- Scanner Modal -->
{#if showScanModal}
    <!-- Backdrop -->
    <div
        class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 flex items-end sm:items-center justify-center p-4"
        onclick={(e) => { if (e.target === e.currentTarget) closeScanModal(); }}
        role="dialog"
        aria-label="Scan QR Code"
    >
        <div class="bg-white rounded-3xl w-full max-w-md shadow-2xl overflow-hidden animate-in slide-in-from-bottom-5 duration-300">
            <!-- Modal Header -->
            <div class="flex items-center justify-between px-6 pt-6 pb-4 border-b border-slate-100">
                <div>
                    <h3 class="font-black text-slate-900 text-lg" style="font-family: 'Outfit', sans-serif;">Scan / Cari Pesanan</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Arahkan kamera ke QR code atau masukkan kode manual</p>
                </div>
                <button
                    id="btn-close-scanner"
                    onclick={closeScanModal}
                    class="w-9 h-9 rounded-xl bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition text-slate-500"
                >
                    <i class="ti ti-x text-lg"></i>
                </button>
            </div>

            <!-- QR Scanner Area -->
            <div class="px-6 pt-4">
                <div id="kurir-qr-reader" class="w-full rounded-2xl overflow-hidden bg-slate-900" style="min-height: 220px;"></div>

                {#if scannerError}
                    <div class="mt-3 flex items-center gap-2 text-sm text-amber-700 bg-amber-50 rounded-xl px-3 py-2">
                        <i class="ti ti-alert-triangle text-base shrink-0"></i>
                        <span>{scannerError}</span>
                    </div>
                {/if}
            </div>

            <!-- Manual Input -->
            <div class="px-6 pb-6 pt-4">
                <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">— atau masukkan kode manual —</p>
                <div class="relative flex gap-2">
                    <input
                        type="text"
                        id="kurir-scan-input"
                        bind:value={scanInput}
                        placeholder="No. transaksi / kode booking / resi..."
                        class="flex-1 pl-4 pr-4 py-3.5 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 transition"
                        style="--tw-ring-color: {primary}40;"
                        onkeydown={(e) => e.key === 'Enter' && handleScan()}
                    />
                    <button
                        id="btn-scan-submit"
                        onclick={handleScan}
                        disabled={scanning || !scanInput.trim()}
                        class="px-5 py-3.5 rounded-xl text-sm font-bold text-white transition disabled:opacity-60 flex items-center gap-2 shrink-0"
                        style="background-color: {primary};"
                    >
                        {#if scanning}
                            <i class="ti ti-loader animate-spin text-base"></i>
                        {:else}
                            <i class="ti ti-search text-base"></i>
                        {/if}
                        Cari
                    </button>
                </div>
            </div>
        </div>
    </div>
{/if}

<style>
    .scrollbar-hide::-webkit-scrollbar { display: none; }
    .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
</style>
