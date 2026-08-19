<script lang="ts">
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { page, router, Link } from '@inertiajs/svelte';
    import { showToast } from '@/utils/toast';
    import Pagination from '@/components/ui/Pagination.svelte';

    let {
        wallet = { balance: 0, total_spent: 0, total_topup: 0 },
        kpi = {
            total_impressions: 0,
            total_clicks: 0,
            total_spent: 0,
            ctr: 0,
            active_campaigns_count: 0,
            total_campaigns_count: 0,
        },
        campaigns = [],
        transactions = { data: [], links: [] },
        availableProducts = [],
    } = $props();

    const primaryColor = $derived(page.props.theme?.primary_color || '#0c4cb4');
    const secondaryColor = $derived(page.props.theme?.secondary_color || '#fa7315');

    const placementOptions = [
        { id: 'home', label: 'Beranda (Rekomendasi)', icon: 'ti-home' },
        { id: 'search', label: 'Hasil Pencarian', icon: 'ti-search' },
        { id: 'category', label: 'Halaman Kategori', icon: 'ti-category' },
        { id: 'brand', label: 'Halaman Brand', icon: 'ti-tag' },
        { id: 'bestseller', label: 'Produk Terlaris & Flash Sale', icon: 'ti-flame' },
        { id: 'detail', label: 'Detail Produk Terkait', icon: 'ti-box' },
    ];

    // UI Tab State
    let activeTab = $state<'campaigns' | 'transactions'>('campaigns');

    // Create Ad Modal State
    let isCreateModalOpen = $state(false);
    let selectedProductId = $state('');
    let adType = $state<'cpc' | 'daily'>('cpc');
    let bidPerClick = $state(300);
    let dailyBudget = $state(10000);
    let showBadge = $state(false);
    let selectedPlacements = $state<string[]>(['home', 'search', 'category', 'brand', 'bestseller', 'detail']);
    let startDate = $state(new Date().toISOString().split('T')[0]);
    let endDate = $state('');
    let isCreating = $state(false);

    // Edit Ad Modal State
    let isEditModalOpen = $state(false);
    let editingAd = $state<any | null>(null);
    let editBidPerClick = $state(300);
    let editDailyBudget = $state(10000);
    let editShowBadge = $state(false);
    let editPlacements = $state<string[]>(['home', 'search', 'category', 'brand', 'bestseller', 'detail']);
    let editEndDate = $state('');
    let isUpdating = $state(false);

    // Delete Modal State
    let isDeleteModalOpen = $state(false);
    let deletingAd = $state<any | null>(null);
    let isDeleting = $state(false);

    // Top Up Modal State
    let isTopupModalOpen = $state(false);
    let topupAmount = $state(50000);
    let isGeneratingQris = $state(false);
    let qrisData = $state<any | null>(null);
    let checkPollInterval: any = null;
    let isCheckingStatus = $state(false);

    const presetTopupAmounts = [25000, 50000, 100000, 250000, 500000];
    const presetBids = [200, 300, 500, 1000, 2000];
    const presetBudgets = [10000, 25000, 50000, 100000, 200000];

    function fmtRupiah(num: number): string {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(num || 0);
    }

    function togglePlacement(id: string, isEdit = false) {
        if (isEdit) {
            if (editPlacements.includes(id)) {
                if (editPlacements.length > 1) {
                    editPlacements = editPlacements.filter((p) => p !== id);
                } else {
                    showToast('Minimal pilih 1 lokasi penayangan iklan.', 'error');
                }
            } else {
                editPlacements = [...editPlacements, id];
            }
        } else {
            if (selectedPlacements.includes(id)) {
                if (selectedPlacements.length > 1) {
                    selectedPlacements = selectedPlacements.filter((p) => p !== id);
                } else {
                    showToast('Minimal pilih 1 lokasi penayangan iklan.', 'error');
                }
            } else {
                selectedPlacements = [...selectedPlacements, id];
            }
        }
    }

    function selectAllPlacements(isEdit = false) {
        const all = ['home', 'search', 'category', 'brand', 'bestseller', 'detail'];
        if (isEdit) {
            editPlacements = [...all];
        } else {
            selectedPlacements = [...all];
        }
    }

    function openCreateModal() {
        if (availableProducts.length === 0) {
            showToast('Semua produk Anda sudah memiliki kampanye promosi aktif atau belum ada produk.', 'error');
            return;
        }
        selectedProductId = availableProducts[0]?.id || '';
        adType = 'cpc';
        bidPerClick = 300;
        dailyBudget = 10000;
        showBadge = false;
        selectedPlacements = ['home', 'search', 'category', 'brand', 'bestseller', 'detail'];
        startDate = new Date().toISOString().split('T')[0];
        endDate = '';
        isCreateModalOpen = true;
    }

    function submitCreateAd() {
        if (!selectedProductId) {
            showToast('Silakan pilih produk terlebih dahulu.', 'error');
            return;
        }
        isCreating = true;
        router.post(
            '/admin/ads',
            {
                product_id: selectedProductId,
                ad_type: adType,
                bid_per_click: bidPerClick,
                daily_budget: dailyBudget,
                show_badge: showBadge,
                placements: selectedPlacements,
                start_date: startDate || undefined,
                end_date: endDate || undefined,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    isCreateModalOpen = false;
                    isCreating = false;
                },
                onError: (errors) => {
                    isCreating = false;
                    const msg = Object.values(errors)[0] as string;
                    showToast(msg || 'Gagal membuat promosi produk.', 'error');
                },
            },
        );
    }

    function openEditModal(ad: any) {
        editingAd = ad;
        editBidPerClick = ad.bid_per_click;
        editDailyBudget = ad.daily_budget;
        editShowBadge = ad.show_badge ?? false;
        editPlacements = Array.isArray(ad.placements) && ad.placements.length > 0
            ? [...ad.placements]
            : ['home', 'search', 'category', 'brand', 'bestseller', 'detail'];
        editEndDate = ad.end_date || '';
        isEditModalOpen = true;
    }

    function submitEditAd() {
        if (!editingAd) return;
        isUpdating = true;
        router.put(
            `/admin/ads/${editingAd.id}`,
            {
                bid_per_click: editBidPerClick,
                daily_budget: editDailyBudget,
                show_badge: editShowBadge,
                placements: editPlacements,
                end_date: editEndDate || null,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    isEditModalOpen = false;
                    isUpdating = false;
                },
                onError: (errors) => {
                    isUpdating = false;
                    const msg = Object.values(errors)[0] as string;
                    showToast(msg || 'Gagal memperbarui promosi.', 'error');
                },
            },
        );
    }

    function toggleAdStatus(ad: any) {
        const nextStatus = ad.status === 'active' ? 'paused' : 'active';
        router.put(
            `/admin/ads/${ad.id}`,
            { status: nextStatus },
            {
                preserveScroll: true,
                onError: (errors) => {
                    const msg = Object.values(errors)[0] as string;
                    showToast(msg || 'Gagal mengubah status promosi.', 'error');
                },
            },
        );
    }

    function openDeleteModal(ad: any) {
        deletingAd = ad;
        isDeleteModalOpen = true;
    }

    function confirmDeleteAd() {
        if (!deletingAd) return;
        isDeleting = true;
        router.delete(`/admin/ads/${deletingAd.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                isDeleteModalOpen = false;
                isDeleting = false;
            },
            onError: () => {
                isDeleting = false;
                showToast('Gagal menghapus promosi.', 'error');
            },
        });
    }

    // ── TOP UP SALDO FLOW ──────────────────────────────
    function openTopupModal() {
        topupAmount = 50000;
        qrisData = null;
        isTopupModalOpen = true;
    }

    async function requestTopupQris() {
        if (topupAmount < 10000) {
            showToast('Minimal top up adalah Rp 10.000', 'error');
            return;
        }

        isGeneratingQris = true;
        try {
            const res = await fetch('/admin/ads/topup', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
                body: JSON.stringify({ amount: topupAmount }),
            });
            const data = await res.json();
            if (data.success) {
                qrisData = data;
                startCheckingPayment(data.order_id);
            } else {
                showToast(data.message || 'Gagal memproses QRIS Top Up.', 'error');
            }
        } catch (e) {
            showToast('Terjadi kesalahan jaringan.', 'error');
        } finally {
            isGeneratingQris = false;
        }
    }

    function startCheckingPayment(orderId: string) {
        clearInterval(checkPollInterval);
        checkPollInterval = setInterval(async () => {
            await checkPaymentStatus(orderId, false);
        }, 5000);
    }

    async function checkPaymentStatus(orderId: string, autoConfirm = false) {
        isCheckingStatus = true;
        try {
            const res = await fetch('/admin/ads/topup/check-status', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
                body: JSON.stringify({ order_id: orderId, auto_confirm: autoConfirm }),
            });
            const data = await res.json();
            if (data.success && data.status === 'paid') {
                clearInterval(checkPollInterval);
                showToast(data.message || 'Top Up Saldo Berhasil!', 'success');
                isTopupModalOpen = false;
                qrisData = null;
                router.reload({ only: ['wallet', 'kpi', 'campaigns', 'transactions'] });
            } else if (autoConfirm) {
                showToast('Status pembayaran masih pending.', 'error');
            }
        } catch (e) {
            // silent check fail
        } finally {
            isCheckingStatus = false;
        }
    }

    function closeTopupModal() {
        clearInterval(checkPollInterval);
        isTopupModalOpen = false;
        qrisData = null;
    }
</script>

<svelte:head>
    <title>Promosi & Iklan Produk — Admin</title>
</svelte:head>

<AdminLayout>
    <main class="w-full max-w-[1600px] mx-auto px-4 sm:px-6 py-6 space-y-6">

        <!-- ── HEADER ── -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl font-bold tracking-tight text-slate-900 flex items-center gap-2">
                        <i class="ti ti-speakerphone text-brand-blueRoyal text-2xl"></i>
                        Promosi & Iklan Produk
                    </h1>
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-900 border border-amber-200">
                        <i class="ti ti-sparkles text-xs"></i> Seller Ads
                    </span>
                </div>
                <p class="mt-0.5 text-xs sm:text-sm text-slate-500">
                    Tingkatkan penjualan dengan mempromosikan produk Anda di posisi teratas toko berbasis saldo (Pay-per-Click / CPC).
                </p>
            </div>

            <div class="flex items-center gap-2.5 flex-wrap">
                <button
                    type="button"
                    onclick={openTopupModal}
                    class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs sm:text-sm font-bold text-emerald-800 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 shadow-2xs transition cursor-pointer"
                >
                    <i class="ti ti-wallet text-base text-emerald-600"></i>
                    <span>Top Up Saldo</span>
                </button>
                <button
                    type="button"
                    onclick={openCreateModal}
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs sm:text-sm font-bold text-white shadow-xs transition hover:opacity-95 cursor-pointer"
                    style="background-color: {primaryColor};"
                >
                    <i class="ti ti-plus text-base"></i>
                    <span>Buat Promosi Produk</span>
                </button>
            </div>
        </div>

        <!-- ── WALLET & KPI CARDS ── -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
            <!-- Card 1: Saldo Iklan -->
            <div class="rounded-2xl border border-slate-200 bg-linear-to-br from-white to-emerald-50/30 p-5 shadow-xs flex flex-col justify-between relative overflow-hidden">
                <div class="absolute -right-3 -bottom-3 opacity-10 pointer-events-none">
                    <i class="ti ti-wallet text-8xl text-emerald-700"></i>
                </div>
                <div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Saldo Promosi Aktif</span>
                        <span class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-base">
                            <i class="ti ti-coins"></i>
                        </span>
                    </div>
                    <p class="text-2xl font-black text-slate-900 mt-2 font-mono">{fmtRupiah(wallet.balance)}</p>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-150/80 flex items-center justify-between text-xs text-slate-500">
                    <span>Total Top Up: <strong class="text-slate-800">{fmtRupiah(wallet.total_topup)}</strong></span>
                    <button
                        type="button"
                        onclick={openTopupModal}
                        class="text-emerald-700 font-bold hover:underline cursor-pointer flex items-center gap-0.5"
                    >
                        + Isi Saldo
                    </button>
                </div>
            </div>

            <!-- Card 2: Tayangan Iklan (Impressions) -->
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs flex flex-col justify-between relative overflow-hidden">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Tayangan</span>
                    <span class="w-8 h-8 rounded-xl bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-base">
                        <i class="ti ti-eye"></i>
                    </span>
                </div>
                <div>
                    <p class="text-2xl font-black text-slate-900 mt-2">{kpi.total_impressions.toLocaleString('id-ID')}</p>
                    <p class="text-xs text-slate-400 mt-0.5">Produk dilihat calon pembeli</p>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                    <span>Promosi Aktif: <strong class="text-slate-800">{kpi.active_campaigns_count} Produk</strong></span>
                </div>
            </div>

            <!-- Card 3: Total Klik & CTR -->
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs flex flex-col justify-between relative overflow-hidden">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Klik & CTR</span>
                    <span class="w-8 h-8 rounded-xl bg-purple-100 text-purple-700 flex items-center justify-center font-bold text-base">
                        <i class="ti ti-click"></i>
                    </span>
                </div>
                <div>
                    <div class="flex items-baseline gap-2 mt-2">
                        <p class="text-2xl font-black text-slate-900">{kpi.total_clicks.toLocaleString('id-ID')}</p>
                        <span class="text-xs font-bold text-purple-700 bg-purple-50 px-2 py-0.5 rounded-md border border-purple-200">
                            CTR: {kpi.ctr}%
                        </span>
                    </div>
                    <p class="text-xs text-slate-400 mt-0.5">Pengunjung yang mengklik produk</p>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                    <span>Efektivitas Tayang: <strong class="text-slate-800">{kpi.ctr > 0 ? 'Aktif Menarik' : 'Baru'}</strong></span>
                </div>
            </div>

            <!-- Card 4: Total Biaya Terpakai -->
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs flex flex-col justify-between relative overflow-hidden">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Biaya Promosi</span>
                    <span class="w-8 h-8 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center font-bold text-base">
                        <i class="ti ti-receipt-tax"></i>
                    </span>
                </div>
                <div>
                    <p class="text-2xl font-black text-slate-900 mt-2 font-mono">{fmtRupiah(kpi.total_spent)}</p>
                    <p class="text-xs text-slate-400 mt-0.5">Akumulasi pengeluaran promosi</p>
                </div>
                <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                    <span>Skema: <strong class="text-slate-800">Pay-per-Click (CPC)</strong></span>
                </div>
            </div>
        </div>

        <!-- ── TABS NAVIGATION ── -->
        <div class="flex items-center gap-2 border-b border-slate-200">
            <button
                type="button"
                onclick={() => (activeTab = 'campaigns')}
                class="px-4 py-2.5 text-xs sm:text-sm font-bold border-b-2 transition cursor-pointer flex items-center gap-2
                       {activeTab === 'campaigns'
                    ? 'border-brand-blueRoyal text-brand-blueRoyal'
                    : 'border-transparent text-slate-500 hover:text-slate-800'}"
            >
                <i class="ti ti-layout-grid text-base"></i>
                <span>Daftar Kampanye Promosi ({campaigns.length})</span>
            </button>
            <button
                type="button"
                onclick={() => (activeTab = 'transactions')}
                class="px-4 py-2.5 text-xs sm:text-sm font-bold border-b-2 transition cursor-pointer flex items-center gap-2
                       {activeTab === 'transactions'
                    ? 'border-brand-blueRoyal text-brand-blueRoyal'
                    : 'border-transparent text-slate-500 hover:text-slate-800'}"
            >
                <i class="ti ti-receipt text-base"></i>
                <span>Riwayat Transaksi Saldo</span>
            </button>
        </div>

        <!-- ── TAB 1: CAMPAIGNS LIST ── -->
        {#if activeTab === 'campaigns'}
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
                {#if campaigns.length === 0}
                    <div class="py-16 text-center px-4">
                        <div class="w-16 h-16 rounded-2xl bg-brand-blueRoyal/10 text-brand-blueRoyal flex items-center justify-center mx-auto mb-3">
                            <i class="ti ti-speakerphone text-3xl"></i>
                        </div>
                        <h3 class="text-base font-bold text-slate-800">Belum Ada Promosi Produk</h3>
                        <p class="text-xs text-slate-500 max-w-md mx-auto mt-1 mb-5">
                            Mulai promosikan produk unggulan toko Anda sekarang agar muncul di posisi paling atas pencarian dan beranda.
                        </p>
                        <button
                            type="button"
                            onclick={openCreateModal}
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs sm:text-sm font-bold text-white shadow-xs transition hover:opacity-95 cursor-pointer"
                            style="background-color: {primaryColor};"
                        >
                            <i class="ti ti-plus text-base"></i>
                            <span>Buat Promosi Sekarang</span>
                        </button>
                    </div>
                {:else}
                    <div class="overflow-x-auto custom-scrollbar">
                        <table class="w-full text-left text-xs border-collapse min-w-[840px]">
                            <thead>
                                <tr class="border-b border-slate-150 bg-slate-50/70 text-[10.5px] font-bold uppercase tracking-wider text-slate-500">
                                    <th class="py-3.5 px-4">Produk Beriklan</th>
                                    <th class="py-3.5 px-4">Tipe & Bid CPC</th>
                                    <th class="py-3.5 px-4">Budget Harian</th>
                                    <th class="py-3.5 px-4 text-center">Tayangan</th>
                                    <th class="py-3.5 px-4 text-center">Klik</th>
                                    <th class="py-3.5 px-4 text-center">CTR</th>
                                    <th class="py-3.5 px-4">Biaya Terpakai</th>
                                    <th class="py-3.5 px-4 text-center">Status</th>
                                    <th class="py-3.5 px-4 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-700">
                                {#each campaigns as ad (ad.id)}
                                    <tr class="hover:bg-slate-50/60 transition duration-150">
                                        <!-- Product Info -->
                                        <td class="py-3.5 px-4">
                                            <div class="flex items-start gap-3">
                                                <div class="w-11 h-11 rounded-xl bg-slate-100 border border-slate-200/80 overflow-hidden shrink-0 flex items-center justify-center mt-0.5">
                                                    {#if ad.product_image}
                                                        <img src={ad.product_image} alt={ad.product_name} class="w-full h-full object-cover" />
                                                    {:else}
                                                        <i class="ti ti-photo text-slate-300 text-lg"></i>
                                                    {/if}
                                                </div>
                                                <div class="min-w-0 max-w-[220px]">
                                                    <p class="font-bold text-slate-900 truncate" title={ad.product_name}>{ad.product_name}</p>
                                                    <div class="flex items-center gap-1.5 mt-1 flex-wrap">
                                                        <span class="text-[10px] text-slate-400 font-mono">SKU: {ad.product_sku || '-'}</span>
                                                    </div>
                                                    {#if ad.placements && ad.placements.length > 0}
                                                        <div class="flex items-center gap-1 mt-1.5 flex-wrap">
                                                            {#each ad.placements as pl}
                                                                <span class="px-1.5 py-0.5 rounded text-[8.5px] font-bold bg-blue-50 text-blue-700 border border-blue-150">
                                                                    {pl === 'home' ? 'Beranda' : pl === 'search' ? 'Pencarian' : pl === 'category' ? 'Kategori' : pl === 'brand' ? 'Brand' : pl === 'bestseller' ? 'Terlaris' : 'Detail'}
                                                                </span>
                                                            {/each}
                                                        </div>
                                                    {/if}
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Ad Type & Bid -->
                                        <td class="py-3.5 px-4">
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-blue-50 text-blue-800 border border-blue-200">
                                                {ad.ad_type === 'cpc' ? 'CPC (Per Klik)' : 'Harian'}
                                            </span>
                                            <p class="font-mono font-bold text-slate-900 text-xs mt-1">{fmtRupiah(ad.bid_per_click)} <span class="text-[10px] font-normal text-slate-400">/klik</span></p>
                                        </td>

                                        <!-- Daily Budget & Spent Today -->
                                        <td class="py-3.5 px-4">
                                            <p class="font-mono font-bold text-slate-900">{fmtRupiah(ad.daily_budget)}</p>
                                            <p class="text-[10px] text-slate-400 mt-0.5">Hari ini: <strong class="text-slate-700">{fmtRupiah(ad.spent_today)}</strong></p>
                                        </td>

                                        <!-- Impressions -->
                                        <td class="py-3.5 px-4 text-center font-bold text-slate-800">
                                            {ad.impressions_count.toLocaleString('id-ID')}
                                        </td>

                                        <!-- Clicks -->
                                        <td class="py-3.5 px-4 text-center font-bold text-purple-700">
                                            {ad.clicks_count.toLocaleString('id-ID')}
                                        </td>

                                        <!-- CTR -->
                                        <td class="py-3.5 px-4 text-center font-bold text-slate-800">
                                            {ad.ctr}%
                                        </td>

                                        <!-- Total Spent -->
                                        <td class="py-3.5 px-4 font-mono font-bold text-slate-900">
                                            {fmtRupiah(ad.total_spent)}
                                        </td>

                                        <!-- Status Badge -->
                                        <td class="py-3.5 px-4 text-center">
                                            {#if ad.status === 'active'}
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10.5px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Tayang
                                                </span>
                                            {:else if ad.status === 'depleted'}
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10.5px] font-bold bg-amber-100 text-amber-800 border border-amber-200" title="Saldo habis atau budget harian tercapai">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Saldo Habis
                                                </span>
                                            {:else}
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10.5px] font-bold bg-slate-100 text-slate-600 border border-slate-200">
                                                    Jeda (Paused)
                                                </span>
                                            {/if}
                                        </td>

                                        <!-- Actions -->
                                        <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                            <div class="flex items-center justify-end gap-1.5">
                                                <!-- Toggle Pause/Resume -->
                                                <button
                                                    type="button"
                                                    onclick={() => toggleAdStatus(ad)}
                                                    class="p-1.5 rounded-lg border transition cursor-pointer
                                                           {ad.status === 'active'
                                                        ? 'border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100'
                                                        : 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100'}"
                                                    title={ad.status === 'active' ? 'Jeda Promosi' : 'Aktifkan Promosi'}
                                                >
                                                    <i class="ti {ad.status === 'active' ? 'ti-player-pause' : 'ti-player-play'} text-sm"></i>
                                                </button>

                                                <!-- Edit Settings -->
                                                <button
                                                    type="button"
                                                    onclick={() => openEditModal(ad)}
                                                    class="p-1.5 rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition cursor-pointer"
                                                    title="Ubah Anggaran & Bid"
                                                >
                                                    <i class="ti ti-settings text-sm"></i>
                                                </button>

                                                <!-- Delete -->
                                                <button
                                                    type="button"
                                                    onclick={() => openDeleteModal(ad)}
                                                    class="p-1.5 rounded-lg border border-rose-200 bg-rose-50 text-rose-600 hover:bg-rose-100 transition cursor-pointer"
                                                    title="Hapus Promosi"
                                                >
                                                    <i class="ti ti-trash text-sm"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                {/if}
            </div>
        {/if}

        <!-- ── TAB 2: WALLET TRANSACTIONS ── -->
        {#if activeTab === 'transactions'}
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
                <div class="border-b border-slate-100 bg-slate-50/60 p-4 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-slate-800">Mutasi Saldo Promosi</h3>
                        <p class="text-xs text-slate-500">Catatan riwayat isi saldo dan pemotongan biaya promosi per klik.</p>
                    </div>
                    <button
                        type="button"
                        onclick={openTopupModal}
                        class="px-3 py-1.5 rounded-xl text-xs font-bold text-emerald-800 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 transition cursor-pointer flex items-center gap-1.5"
                    >
                        <i class="ti ti-plus text-xs"></i>
                        <span>Isi Saldo</span>
                    </button>
                </div>

                {#if !transactions.data || transactions.data.length === 0}
                    <div class="py-16 text-center px-4">
                        <i class="ti ti-receipt-off text-3xl text-slate-300 mb-2 block"></i>
                        <p class="text-xs text-slate-400">Belum ada riwayat transaksi saldo.</p>
                    </div>
                {:else}
                    <div class="overflow-x-auto custom-scrollbar">
                        <table class="w-full text-left text-xs border-collapse min-w-[700px]">
                            <thead>
                                <tr class="border-b border-slate-150 bg-slate-50/70 text-[10.5px] font-bold uppercase tracking-wider text-slate-500">
                                    <th class="py-3 px-4">Waktu</th>
                                    <th class="py-3 px-4">No. Referensi</th>
                                    <th class="py-3 px-4">Keterangan</th>
                                    <th class="py-3 px-4">Nominal</th>
                                    <th class="py-3 px-4">Sisa Saldo</th>
                                    <th class="py-3 px-4 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-700">
                                {#each transactions.data as tx (tx.id)}
                                    <tr class="hover:bg-slate-50/50 transition">
                                        <td class="py-3 px-4 whitespace-nowrap text-slate-500">{tx.paid_at}</td>
                                        <td class="py-3 px-4 font-mono font-bold text-slate-700 text-[11px]">{tx.order_id || '-'}</td>
                                        <td class="py-3 px-4 font-medium text-slate-800">{tx.description}</td>
                                        <td class="py-3 px-4 font-mono font-bold whitespace-nowrap
                                                   {tx.type === 'topup' ? 'text-emerald-600' : 'text-slate-900'}">
                                            {tx.type === 'topup' ? `+${fmtRupiah(tx.amount)}` : `-${fmtRupiah(tx.amount)}`}
                                        </td>
                                        <td class="py-3 px-4 font-mono text-slate-600 whitespace-nowrap">{fmtRupiah(tx.balance_after)}</td>
                                        <td class="py-3 px-4 text-center">
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold
                                                         {tx.status === 'paid' ? 'bg-emerald-100 text-emerald-800' : tx.status === 'pending' ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-800'}">
                                                {tx.status === 'paid' ? 'Berhasil' : tx.status === 'pending' ? 'Menunggu' : 'Gagal'}
                                            </span>
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>

                    {#if transactions.links && transactions.links.length > 3}
                        <div class="p-4 border-t border-slate-100 flex flex-col items-center">
                            <Pagination data={transactions} itemLabel="Transaksi" />
                        </div>
                    {/if}
                {/if}
            </div>
        {/if}

    </main>
</AdminLayout>

<!-- ═══════════════════════════════════════════════════════════
     MODAL: BUAT PROMOSI PRODUK BARU
════════════════════════════════════════════════════════════ -->
{#if isCreateModalOpen}
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <div class="bg-white rounded-2xl max-w-lg w-full border border-slate-200 shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
            <!-- Modal Header -->
            <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-brand-blueRoyal/10 text-brand-blueRoyal flex items-center justify-center font-bold text-base">
                        <i class="ti ti-speakerphone"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-800">Buat Promosi Produk Baru</h3>
                        <p class="text-xs text-slate-500">Pilih produk dan tentukan anggaran promosi Anda.</p>
                    </div>
                </div>
                <button
                    type="button"
                    onclick={() => (isCreateModalOpen = false)}
                    class="w-8 h-8 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-100 flex items-center justify-center transition cursor-pointer"
                >
                    <i class="ti ti-x text-base"></i>
                </button>
            </div>

            <!-- Modal Form -->
            <div class="p-5 overflow-y-auto space-y-4 text-xs">
                <!-- 1. Pilih Produk -->
                <div>
                    <label class="block font-bold text-slate-700 mb-1.5">Pilih Produk Yang Ingin Dipromosikan</label>
                    <select
                        bind:value={selectedProductId}
                        class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 bg-white text-slate-800 font-medium focus:ring-2 focus:ring-slate-900/10 focus:border-slate-300"
                    >
                        {#each availableProducts as prod}
                            <option value={prod.id}>{prod.name} ({prod.sku || 'No SKU'})</option>
                        {/each}
                    </select>
                </div>

                <!-- 2. Tipe Iklan -->
                <div>
                    <label class="block font-bold text-slate-700 mb-1.5">Skema Promosi</label>
                    <div class="grid grid-cols-2 gap-2.5">
                        <button
                            type="button"
                            onclick={() => (adType = 'cpc')}
                            class="p-3 rounded-xl border text-left transition cursor-pointer {adType === 'cpc'
                                ? 'border-brand-blueRoyal bg-brand-blueRoyal/5 text-brand-blueRoyal font-bold ring-1 ring-brand-blueRoyal'
                                : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'}"
                        >
                            <div class="flex items-center justify-between mb-1">
                                <span class="font-bold">Biaya per Klik (CPC)</span>
                                {#if adType === 'cpc'}<i class="ti ti-check text-brand-blueRoyal"></i>{/if}
                            </div>
                            <p class="text-[10.5px] font-normal text-slate-500">Saldo hanya terpotong saat calon pembeli mengklik produk.</p>
                        </button>

                        <button
                            type="button"
                            onclick={() => (adType = 'daily')}
                            class="p-3 rounded-xl border text-left transition cursor-pointer {adType === 'daily'
                                ? 'border-brand-blueRoyal bg-brand-blueRoyal/5 text-brand-blueRoyal font-bold ring-1 ring-brand-blueRoyal'
                                : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'}"
                        >
                            <div class="flex items-center justify-between mb-1">
                                <span class="font-bold">Biaya Harian</span>
                                {#if adType === 'daily'}<i class="ti ti-check text-brand-blueRoyal"></i>{/if}
                            </div>
                            <p class="text-[10.5px] font-normal text-slate-500">Budget tetap per hari selama kampanye aktif.</p>
                        </button>
                    </div>
                </div>

                <!-- 3. Bid per Klik (CPC) -->
                {#if adType === 'cpc'}
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="font-bold text-slate-700">Bid Biaya per Klik (CPC)</label>
                            <span class="font-mono font-bold text-slate-900">{fmtRupiah(bidPerClick)}</span>
                        </div>
                        <input
                            type="number"
                            min="100"
                            step="50"
                            bind:value={bidPerClick}
                            class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 bg-white text-slate-800 font-mono font-bold focus:ring-2 focus:ring-slate-900/10"
                        />
                        <div class="flex items-center gap-1.5 mt-2 flex-wrap">
                            {#each presetBids as bid}
                                <button
                                    type="button"
                                    onclick={() => (bidPerClick = bid)}
                                    class="px-2.5 py-1 rounded-lg text-[10.5px] font-semibold border transition cursor-pointer
                                           {bidPerClick === bid ? 'bg-slate-900 text-white border-slate-900' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 border-slate-200'}"
                                >
                                    {fmtRupiah(bid)}
                                </button>
                            {/each}
                        </div>
                    </div>
                {/if}

                <!-- 4. Budget Harian -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="font-bold text-slate-700">Batas Anggaran Harian</label>
                        <span class="font-mono font-bold text-slate-900">{fmtRupiah(dailyBudget)}/hari</span>
                    </div>
                    <input
                        type="number"
                        min="5000"
                        step="5000"
                        bind:value={dailyBudget}
                        class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 bg-white text-slate-800 font-mono font-bold focus:ring-2 focus:ring-slate-900/10"
                    />
                    <div class="flex items-center gap-1.5 mt-2 flex-wrap">
                        {#each presetBudgets as bg}
                            <button
                                type="button"
                                onclick={() => (dailyBudget = bg)}
                                class="px-2.5 py-1 rounded-lg text-[10.5px] font-semibold border transition cursor-pointer
                                       {dailyBudget === bg ? 'bg-slate-900 text-white border-slate-900' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 border-slate-200'}"
                            >
                                {fmtRupiah(bg)}
                            </button>
                        {/each}
                    </div>
                </div>

                <!-- 5. Tanggal Mulai & Selesai -->
                <div class="grid grid-cols-2 gap-3 pt-2">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Tanggal Mulai</label>
                        <input
                            type="date"
                            bind:value={startDate}
                            class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 bg-white text-slate-800 font-medium"
                        />
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Tanggal Berakhir (Opsional)</label>
                        <input
                            type="date"
                            bind:value={endDate}
                            class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 bg-white text-slate-800 font-medium"
                        />
                    </div>
                </div>

                <!-- 6. Penempatan Menu -->
                <div class="pt-3 border-t border-slate-150 space-y-3">
                    <!-- Placements Selector -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="font-bold text-slate-700">Pilih Menu / Lokasi Penayangan</label>
                            <button
                                type="button"
                                onclick={() => selectAllPlacements(false)}
                                class="text-[11px] font-bold text-brand-blueRoyal hover:underline cursor-pointer"
                            >
                                Pilih Semua
                            </button>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            {#each placementOptions as opt}
                                <button
                                    type="button"
                                    onclick={() => togglePlacement(opt.id, false)}
                                    class="p-2.5 rounded-xl border text-left flex items-center gap-2 transition cursor-pointer
                                           {selectedPlacements.includes(opt.id)
                                        ? 'border-brand-blueRoyal bg-brand-blueRoyal/5 text-brand-blueRoyal font-bold ring-1 ring-brand-blueRoyal'
                                        : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'}"
                                >
                                    <i class="ti {opt.icon} text-sm"></i>
                                    <span class="text-xs truncate flex-1">{opt.label}</span>
                                    {#if selectedPlacements.includes(opt.id)}
                                        <i class="ti ti-check text-xs text-brand-blueRoyal"></i>
                                    {/if}
                                </button>
                            {/each}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="p-4 border-t border-slate-100 bg-slate-50/50 flex items-center justify-between">
                <div class="text-xs text-slate-500">
                    Sisa Saldo: <strong class="text-slate-900 font-mono">{fmtRupiah(wallet.balance)}</strong>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        onclick={() => (isCreateModalOpen = false)}
                        class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-200 transition cursor-pointer"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        disabled={isCreating}
                        onclick={submitCreateAd}
                        class="px-4 py-2 rounded-xl text-xs font-bold text-white shadow-xs transition hover:opacity-95 cursor-pointer disabled:opacity-50"
                        style="background-color: {primaryColor};"
                    >
                        {isCreating ? 'Menyimpan...' : 'Aktifkan Promosi'}
                    </button>
                </div>
            </div>
        </div>
    </div>
{/if}

<!-- ═══════════════════════════════════════════════════════════
     MODAL: EDIT ANGGARAN PROMOSI
════════════════════════════════════════════════════════════ -->
{#if isEditModalOpen && editingAd}
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <div class="bg-white rounded-2xl max-w-md w-full border border-slate-200 shadow-2xl overflow-hidden">
            <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                <h3 class="text-sm font-bold text-slate-800">Ubah Anggaran Promosi</h3>
                <button
                    type="button"
                    onclick={() => (isEditModalOpen = false)}
                    class="text-slate-400 hover:text-slate-700 cursor-pointer"
                >
                    <i class="ti ti-x text-base"></i>
                </button>
            </div>

            <div class="p-5 space-y-4 text-xs">
                <p class="font-bold text-slate-800 truncate">{editingAd.product_name}</p>

                {#if editingAd.ad_type === 'cpc'}
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Bid Biaya per Klik (CPC)</label>
                        <input
                            type="number"
                            min="100"
                            step="50"
                            bind:value={editBidPerClick}
                            class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 bg-white text-slate-800 font-mono font-bold"
                        />
                    </div>
                {/if}

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Batas Anggaran Harian</label>
                    <input
                        type="number"
                        min="5000"
                        step="5000"
                        bind:value={editDailyBudget}
                        class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 bg-white text-slate-800 font-mono font-bold"
                    />
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Tanggal Berakhir (Opsional)</label>
                    <input
                        type="date"
                        bind:value={editEndDate}
                        class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 bg-white text-slate-800 font-medium"
                    />
                </div>

                <!-- Penempatan Menu di Edit -->
                <div class="pt-3 border-t border-slate-150 space-y-3">
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="font-bold text-slate-700">Menu / Lokasi Penayangan</label>
                            <button
                                type="button"
                                onclick={() => selectAllPlacements(true)}
                                class="text-[11px] font-bold text-brand-blueRoyal hover:underline cursor-pointer"
                            >
                                Pilih Semua
                            </button>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            {#each placementOptions as opt}
                                <button
                                    type="button"
                                    onclick={() => togglePlacement(opt.id, true)}
                                    class="p-2 rounded-xl border text-left flex items-center gap-2 transition cursor-pointer
                                           {editPlacements.includes(opt.id)
                                        ? 'border-brand-blueRoyal bg-brand-blueRoyal/5 text-brand-blueRoyal font-bold ring-1 ring-brand-blueRoyal'
                                        : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'}"
                                >
                                    <i class="ti {opt.icon} text-sm"></i>
                                    <span class="text-xs truncate flex-1">{opt.label}</span>
                                    {#if editPlacements.includes(opt.id)}
                                        <i class="ti ti-check text-xs text-brand-blueRoyal"></i>
                                    {/if}
                                </button>
                            {/each}
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-4 border-t border-slate-100 bg-slate-50/50 flex items-center justify-end gap-2 text-xs">
                <button
                    type="button"
                    onclick={() => (isEditModalOpen = false)}
                    class="px-4 py-2 rounded-xl font-bold text-slate-600 hover:bg-slate-200 cursor-pointer"
                >
                    Batal
                </button>
                <button
                    type="button"
                    disabled={isUpdating}
                    onclick={submitEditAd}
                    class="px-4 py-2 rounded-xl font-bold text-white shadow-xs hover:opacity-95 cursor-pointer disabled:opacity-50"
                    style="background-color: {primaryColor};"
                >
                    {isUpdating ? 'Menyimpan...' : 'Simpan Perubahan'}
                </button>
            </div>
        </div>
    </div>
{/if}

<!-- ═══════════════════════════════════════════════════════════
     MODAL: TOP UP SALDO (QRIS)
════════════════════════════════════════════════════════════ -->
{#if isTopupModalOpen}
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <div class="bg-white rounded-2xl max-w-md w-full border border-slate-200 shadow-2xl overflow-hidden flex flex-col">
            <!-- Modal Header -->
            <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold text-base">
                        <i class="ti ti-wallet"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-800">Top Up Saldo Promosi</h3>
                        <p class="text-xs text-slate-500">Saldo saat ini: <strong class="text-slate-900 font-mono">{fmtRupiah(wallet.balance)}</strong></p>
                    </div>
                </div>
                <button
                    type="button"
                    onclick={closeTopupModal}
                    class="w-8 h-8 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-100 flex items-center justify-center transition cursor-pointer"
                >
                    <i class="ti ti-x text-base"></i>
                </button>
            </div>

            <!-- Content Step 1: Input Nominal -->
            {#if !qrisData}
                <div class="p-5 space-y-4 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1.5">Pilih Nominal Top Up</label>
                        <div class="grid grid-cols-3 gap-2">
                            {#each presetTopupAmounts as amount}
                                <button
                                    type="button"
                                    onclick={() => (topupAmount = amount)}
                                    class="py-2.5 px-3 rounded-xl border text-xs font-bold transition cursor-pointer text-center
                                           {topupAmount === amount
                                        ? 'border-emerald-600 bg-emerald-50 text-emerald-800 ring-1 ring-emerald-600'
                                        : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'}"
                                >
                                    {fmtRupiah(amount)}
                                </button>
                            {/each}
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1.5">Atau Masukkan Nominal Lain</label>
                        <div class="relative">
                            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 font-bold text-slate-400 text-xs">Rp</span>
                            <input
                                type="number"
                                min="10000"
                                step="10000"
                                bind:value={topupAmount}
                                class="w-full pl-10 pr-4 py-2 text-xs rounded-xl border border-slate-200 bg-white text-slate-800 font-mono font-bold focus:ring-2 focus:ring-slate-900/10"
                            />
                        </div>
                        <span class="text-[10px] text-slate-400 mt-1 block">Minimal top up Rp 10.000</span>
                    </div>

                    <div class="rounded-xl border border-blue-200 bg-blue-50/60 p-3 text-[11px] text-blue-800 space-y-1">
                        <div class="flex items-center gap-1.5 font-bold">
                            <i class="ti ti-info-circle"></i>
                            <span>Pembayaran Instan via QRIS</span>
                        </div>
                        <p class="text-blue-700">Dapat dibayar menggunakan GoPay, OVO, DANA, BCA, Mandiri, ShopeePay, dan seluruh aplikasi perbankan berstandar QRIS.</p>
                    </div>
                </div>

                <div class="p-4 border-t border-slate-100 bg-slate-50/50 flex items-center justify-end gap-2 text-xs">
                    <button
                        type="button"
                        onclick={closeTopupModal}
                        class="px-4 py-2 rounded-xl font-bold text-slate-600 hover:bg-slate-200 cursor-pointer"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        disabled={isGeneratingQris || topupAmount < 10000}
                        onclick={requestTopupQris}
                        class="px-5 py-2 rounded-xl font-bold text-white bg-emerald-600 hover:bg-emerald-700 shadow-xs transition cursor-pointer disabled:opacity-50 flex items-center gap-1.5"
                    >
                        <i class="ti ti-qrcode"></i>
                        <span>{isGeneratingQris ? 'Menyiapkan QRIS...' : `Bayar ${fmtRupiah(topupAmount)}`}</span>
                    </button>
                </div>
            {:else}
                <!-- Content Step 2: QRIS Display -->
                <div class="p-5 text-center space-y-4">
                    <div class="inline-block p-4 rounded-2xl bg-white border border-slate-200 shadow-xs mx-auto">
                        <img
                            src={qrisData.qr_image}
                            alt="QRIS Top Up"
                            class="w-56 h-56 mx-auto object-contain rounded-lg"
                        />
                    </div>

                    <div>
                        <p class="text-xs text-slate-500">Total Pembayaran Top Up</p>
                        <p class="text-2xl font-black text-slate-900 font-mono mt-0.5">{fmtRupiah(qrisData.amount)}</p>
                        <p class="text-[11px] font-mono text-slate-400 mt-1">Ref: {qrisData.order_id}</p>
                    </div>

                    <div class="flex items-center justify-center gap-2 text-xs text-slate-500">
                        <span class="relative flex h-2 w-2">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                        </span>
                        <span>Menunggu pembayaran otomatis...</span>
                    </div>
                </div>

                <div class="p-4 border-t border-slate-100 bg-slate-50/50 flex items-center justify-between gap-2 text-xs">
                    <button
                        type="button"
                        onclick={() => (qrisData = null)}
                        class="px-3 py-2 rounded-xl font-bold text-slate-600 hover:bg-slate-200 cursor-pointer"
                    >
                        Ubah Nominal
                    </button>
                    <button
                        type="button"
                        disabled={isCheckingStatus}
                        onclick={() => checkPaymentStatus(qrisData.order_id, true)}
                        class="px-4 py-2 rounded-xl font-bold text-white bg-emerald-600 hover:bg-emerald-700 shadow-xs transition cursor-pointer flex items-center gap-1.5"
                    >
                        <i class="ti ti-refresh"></i>
                        <span>{isCheckingStatus ? 'Mengecek...' : 'Cek Status Pembayaran'}</span>
                    </button>
                </div>
            {/if}
        </div>
    </div>
{/if}

<!-- ═══════════════════════════════════════════════════════════
     MODAL: KONFIRMASI HAPUS PROMOSI
════════════════════════════════════════════════════════════ -->
{#if isDeleteModalOpen && deletingAd}
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <div class="bg-white rounded-2xl max-w-sm w-full border border-slate-200 shadow-2xl p-5 text-center space-y-4">
            <div class="w-12 h-12 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center mx-auto">
                <i class="ti ti-trash text-2xl"></i>
            </div>
            <div>
                <h3 class="text-base font-bold text-slate-900">Hapus Promosi Produk?</h3>
                <p class="text-xs text-slate-500 mt-1">
                    Kampanye promosi untuk <strong class="text-slate-800">{deletingAd.product_name}</strong> akan dihentikan dan dihapus permanen.
                </p>
            </div>
            <div class="flex items-center justify-center gap-2 pt-2 text-xs">
                <button
                    type="button"
                    onclick={() => (isDeleteModalOpen = false)}
                    class="px-4 py-2 rounded-xl font-bold text-slate-600 hover:bg-slate-100 cursor-pointer"
                >
                    Batal
                </button>
                <button
                    type="button"
                    disabled={isDeleting}
                    onclick={confirmDeleteAd}
                    class="px-4 py-2 rounded-xl font-bold text-white bg-rose-600 hover:bg-rose-700 shadow-xs cursor-pointer disabled:opacity-50"
                >
                    {isDeleting ? 'Menghapus...' : 'Ya, Hapus Promosi'}
                </button>
            </div>
        </div>
    </div>
{/if}
