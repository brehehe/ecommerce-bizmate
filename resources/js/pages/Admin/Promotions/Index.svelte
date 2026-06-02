<script lang="ts">
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { Link, router } from '@inertiajs/svelte';
    import { showToast } from '@/utils/toast';
    import Pagination from '@/components/ui/Pagination.svelte';
    import {
        index as adminPromotionsIndex,
        create as adminPromotionsCreate,
        edit as adminPromotionsEdit,
        destroy as adminPromotionsDestroy,
        toggleActive as adminPromotionsToggleActive,
    } from '@/routes/admin/promotions';

    let {
        promotions = { data: [], links: [], total: 0, from: 0, to: 0 },
        filters = { search: '', type: 'all', status: 'all' },
        metrics = { total_promotions: 0, active_promotions: 0, total_vouchers: 0, active_flash_sales: 0 }
    } = $props();

    // svelte-ignore state_referenced_locally
    let searchInput = $state(filters.search || '');
    // svelte-ignore state_referenced_locally
    let filterStatus = $state(filters.status || 'all');
    // svelte-ignore state_referenced_locally
    let filterType = $state(filters.type || 'all');

    let expandedPromotions = $state(new Set());

    function togglePromotionDrawer(promoId) {
        if (expandedPromotions.has(promoId)) {
            expandedPromotions.delete(promoId);
        } else {
            expandedPromotions.add(promoId);
        }
        expandedPromotions = new Set(expandedPromotions);
    }

    // Delete Modal State
    let deleteModalOpen = $state(false);
    let deletePromoId = $state<number | null>(null);

    function applyFilters() {
        router.get(
            adminPromotionsIndex.url(),
            {
                search: searchInput,
                type: filterType,
                status: filterStatus,
            },
            { preserveState: true, preserveScroll: true, replace: true }
        );
    }

    let searchTimeout;
    function handleSearchInput() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(applyFilters, 300);
    }

    function toggleActive(promoId: number) {
        router.post(
            adminPromotionsToggleActive.url({ promotion: promoId }),
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    showToast('Status promosi berhasil diubah.', 'success');
                }
            }
        );
    }

    function confirmDelete(id: number) {
        deletePromoId = id;
        deleteModalOpen = true;
    }

    function executeDelete() {
        if (deletePromoId) {
            router.delete(
                adminPromotionsDestroy.url({ promotion: deletePromoId }),
                {
                    onSuccess: () => {
                        deleteModalOpen = false;
                        deletePromoId = null;
                        showToast('Promosi berhasil dihapus.', 'success');
                    }
                }
            );
        }
    }

    function getPromotionTypeBadge(type: string) {
        switch (type) {
            case 'promo_toko':
                return { label: 'Promo Toko', class: 'bg-blue-50 text-blue-600 border-blue-100' };
            case 'voucher_belanja':
                return { label: 'Voucher Belanja', class: 'bg-emerald-50 text-emerald-600 border-emerald-100' };
            case 'voucher_gratis_ongkir':
                return { label: 'Gratis Ongkir', class: 'bg-teal-50 text-teal-600 border-teal-100' };
            case 'flash_sale':
                return { label: 'Flash Sale', class: 'bg-rose-50 text-rose-600 border-rose-100 animate-pulse' };
            case 'bundling_gift':
                return { label: 'Bundling & Gift', class: 'bg-purple-50 text-purple-600 border-purple-100' };

            default:
                return { label: 'Promosi', class: 'bg-slate-50 text-slate-600 border-slate-100' };
        }
    }

    function getDiscountText(promo: any) {
        if (promo.type === 'voucher_gratis_ongkir') {
            return promo.discount_value 
                ? `Potongan Ongkir Rp ${(Number(promo.discount_value)).toLocaleString('id-ID')}` 
                : 'Gratis Ongkir 100%';
        }
        if (promo.type === 'bundling_gift') {
            return 'Bundling / Gift';
        }
        if (promo.discount_type === 'percentage') {
            return `${Number(promo.discount_value)}% Off`;
        }
        if (promo.discount_type === 'fixed') {
            return `Rp ${Number(promo.discount_value).toLocaleString('id-ID')} Off`;
        }
        return '-';
    }

    function getScheduleStatus(promo: any) {
        const now = new Date();
        const start = new Date(promo.start_time);
        const end = new Date(promo.end_time);

        if (now < start) {
            return { text: 'Belum Mulai', class: 'bg-slate-100 text-slate-500 border-slate-200' };
        }
        if (now > end) {
            return { text: 'Berakhir', class: 'bg-red-50 text-red-500 border-red-100' };
        }
        return { text: 'Aktif', class: 'bg-emerald-50 text-emerald-600 border-emerald-100' };
    }

    function formatDateTime(dateTimeStr: string) {
        const date = new Date(dateTimeStr);
        return date.toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
</script>

<svelte:head>
    <title>Manajemen Promosi</title>
</svelte:head>

<AdminLayout>
    <div class="flex-grow flex flex-col min-h-screen">
        <main class="flex-grow p-4 sm:p-6 xl:p-8 w-full max-w-full mx-auto space-y-6 overflow-hidden">
            
            <!-- Page Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h3 class="font-outfit font-black text-2xl text-slate-800">
                        Manajemen Promosi
                    </h3>
                    <p class="text-xs text-slate-400 font-bold uppercase tracking-wider mt-1">
                        Buat Voucher, Flash Sale, Promo Toko, & Bundling Menarik Untuk Pelanggan Anda
                    </p>
                </div>
                <div class="flex items-center gap-2.5 w-full sm:w-auto">
                    <Link
                        href={adminPromotionsCreate.url()}
                        class="flex-1 sm:flex-none justify-center flex items-center gap-2 px-5 py-2.5 bg-brand-blueRoyal hover:bg-blue-800 text-white font-bold rounded-xl text-xs transition shadow-lg shadow-brand-blueRoyal/20 font-outfit uppercase tracking-wider"
                    >
                        <i class="ti ti-plus text-sm"></i> Buat Promosi Baru
                    </Link>
                </div>
            </div>

            <!-- Metrics Overview -->
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-soft">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest font-outfit mb-1">
                        Total Promosi
                    </p>
                    <p class="text-2xl font-black text-slate-800">
                        {metrics.total_promotions}
                    </p>
                </div>
                <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-soft">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest font-outfit mb-1">
                        Promosi Aktif Saat Ini
                    </p>
                    <p class="text-2xl font-black text-emerald-600">
                        {metrics.active_promotions}
                    </p>
                </div>
                <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-soft">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest font-outfit mb-1">
                        Voucher Belanja & Ongkir
                    </p>
                    <p class="text-2xl font-black text-purple-600">
                        {metrics.total_vouchers}
                    </p>
                </div>
                <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-soft">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest font-outfit mb-1">
                        Flash Sale Berjalan
                    </p>
                    <p class="text-2xl font-black text-rose-500">
                        {metrics.active_flash_sales}
                    </p>
                </div>
            </div>

            <!-- Advanced Data Table -->
            <div class="bg-white rounded-3xl border border-slate-200 shadow-card flex flex-col">
                <!-- Filters Bar -->
                <div class="p-4 sm:p-6 border-b border-slate-100 flex flex-col xl:flex-row gap-4 justify-between items-stretch xl:items-center bg-slate-50/50 rounded-t-3xl">
                    <div class="flex items-center gap-3 w-full xl:w-auto">
                        <div class="relative w-full xl:w-64">
                            <i class="ti ti-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                            <input
                                type="text"
                                bind:value={searchInput}
                                oninput={handleSearchInput}
                                placeholder="Cari Nama Promosi, Kode..."
                                class="w-full pl-10 pr-4 py-2 rounded-xl border border-slate-200 text-sm text-slate-700 focus:border-brand-blueRoyal focus:ring-1 focus:ring-brand-blueRoyal focus:outline-none"
                            />
                        </div>
                    </div>
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full xl:w-auto">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 w-full sm:w-auto">
                            <select
                                bind:value={filterType}
                                onchange={applyFilters}
                                class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-600 focus:outline-none"
                            >
                                <option value="all">Semua Tipe</option>
                                <option value="promo_toko">Promo Toko</option>
                                <option value="voucher_belanja">Voucher Belanja</option>
                                <option value="voucher_gratis_ongkir">Gratis Ongkir</option>
                                <option value="flash_sale">Flash Sale</option>
                                <option value="bundling_gift">Bundling & Gift</option>

                            </select>
                            <select
                                bind:value={filterStatus}
                                onchange={applyFilters}
                                class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-600 focus:outline-none"
                            >
                                <option value="all">Status: Semua</option>
                                <option value="active">Status: Aktif</option>
                                <option value="inactive">Status: Nonaktif</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Table View -->
                <div class="overflow-x-auto flex-grow custom-scrollbar">
                    <table class="w-full text-left border-collapse min-w-[900px] xl:min-w-0 table-fixed">
                        <thead>
                            <tr class="bg-white">
                                <th class="px-4 py-4 w-[28%] text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Info Promosi</th>
                                <th class="px-4 py-4 w-[12%] text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Kode</th>
                                <th class="px-4 py-4 w-[16%] text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Nilai Diskon</th>
                                <th class="px-4 py-4 w-[24%] text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Masa Berlaku</th>
                                <th class="px-4 py-4 w-[10%] text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100 text-center">Kuota / Terpakai</th>
                                <th class="px-4 py-4 w-[8%] text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100 text-center">Status</th>
                                <th class="px-4 py-4 w-[8%] text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm text-slate-700 divide-y divide-slate-100">
                            {#if promotions.data.length === 0}
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center text-slate-400 font-medium">
                                        <i class="ti ti-ticket-off text-4xl block mb-2 opacity-50"></i>
                                        Tidak ada promosi ditemukan.
                                    </td>
                                </tr>
                            {:else}
                                {#each promotions.data as promo (promo.id)}
                                    {@const typeBadge = getPromotionTypeBadge(promo.type)}
                                    {@const scheduleBadge = getScheduleStatus(promo)}
                                    <tr class="table-row-hover transition {!promo.is_active ? 'bg-slate-50/30' : ''}">
                                        <td class="px-4 py-4">
                                            <div class="flex items-center gap-3">
                                                {#if promo.items && promo.items.length > 0}
                                                    <button
                                                        onclick={() => togglePromotionDrawer(promo.id)}
                                                        class="w-7 h-7 rounded-xl bg-slate-50 hover:bg-brand-blueLight hover:text-brand-blueRoyal border border-slate-200/60 hover:border-brand-blueRoyal/20 flex items-center justify-center text-slate-400 transition duration-200 shrink-0 shadow-soft"
                                                        title="Lihat produk target"
                                                    >
                                                        <i
                                                            class="ti ti-chevron-down text-xs transition-transform duration-300 {expandedPromotions.has(promo.id) ? 'rotate-180' : ''}"
                                                        ></i>
                                                    </button>
                                                {:else}
                                                    <div class="w-7 h-7 shrink-0"></div>
                                                {/if}
                                                <div class="flex flex-col gap-1">
                                                    <span class="font-bold text-slate-800 block truncate max-w-[200px]" title={promo.name}>
                                                        {promo.name}
                                                    </span>
                                                    <span class="inline-flex items-center self-start px-2 py-0.5 border rounded-md text-[9px] font-extrabold uppercase tracking-wider {typeBadge.class}">
                                                        {typeBadge.label}
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4">
                                            {#if promo.code}
                                                <span class="font-mono font-bold text-xs bg-slate-100 text-slate-600 px-2 py-1 rounded border border-slate-200">
                                                    {promo.code}
                                                </span>
                                            {:else}
                                                <span class="text-slate-400 font-medium">-</span>
                                            {/if}
                                        </td>
                                        <td class="px-4 py-4">
                                            <span class="font-black text-slate-800 text-[12px] xl:text-[13px]">
                                                {getDiscountText(promo)}
                                            </span>
                                            {#if promo.min_purchase > 0}
                                                <p class="text-[10px] text-slate-400 font-medium mt-0.5">
                                                    Min: Rp {Number(promo.min_purchase).toLocaleString('id-ID')}
                                                </p>
                                            {/if}
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="flex flex-col gap-0.5">
                                                <p class="text-xs text-slate-600 font-semibold">
                                                    Mulai: {formatDateTime(promo.start_time)}
                                                </p>
                                                <p class="text-xs text-slate-500 font-medium">
                                                    Selesai: {formatDateTime(promo.end_time)}
                                                </p>
                                                <span class="inline-flex items-center self-start px-1.5 py-0.5 border rounded text-[8px] font-bold uppercase tracking-wider mt-1 {scheduleBadge.class}">
                                                    {scheduleBadge.text}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4 text-center">
                                            {#if promo.quota}
                                                <div class="flex flex-col items-center">
                                                    <span class="font-bold text-slate-700 text-xs">
                                                        {promo.used_count} / {promo.quota}
                                                    </span>
                                                    <div class="w-16 bg-slate-100 rounded-full h-1 mt-1 overflow-hidden">
                                                        <div 
                                                            class="bg-brand-blueRoyal h-1 rounded-full" 
                                                            style="width: {Math.min(100, (promo.used_count / promo.quota) * 100)}%"
                                                        ></div>
                                                    </div>
                                                </div>
                                            {:else}
                                                <span class="text-xs text-slate-500 bg-slate-100 px-2 py-0.5 rounded border border-slate-200/40 font-bold whitespace-nowrap">
                                                    ∞ Tanpa Batas
                                                </span>
                                            {/if}
                                        </td>
                                        <td class="px-4 py-4 text-center">
                                            <label class="relative inline-flex items-center cursor-pointer">
                                                <input
                                                    type="checkbox"
                                                    checked={promo.is_active}
                                                    onchange={() => toggleActive(promo.id)}
                                                    class="sr-only peer"
                                                />
                                                <div class="w-9 h-5 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-brand-blueRoyal"></div>
                                            </label>
                                        </td>
                                        <td class="px-4 py-4 text-right whitespace-nowrap">
                                            <Link
                                                href={adminPromotionsEdit.url({ promotion: promo.id })}
                                                class="p-2 text-slate-400 hover:text-brand-blueRoyal rounded-lg transition inline-block"
                                                title="Edit"
                                            >
                                                <i class="ti ti-edit text-lg"></i>
                                            </Link>
                                            <button
                                                onclick={() => confirmDelete(promo.id)}
                                                class="p-2 text-slate-400 hover:text-red-500 rounded-lg transition inline-block"
                                                title="Hapus"
                                            >
                                                <i class="ti ti-trash text-lg"></i>
                                            </button>
                                        </td>
                                    </tr>

                                    {#if promo.items && promo.items.length > 0 && expandedPromotions.has(promo.id)}
                                        {#each promo.items as item (item.id)}
                                            {@const variantName = item.variant ? (item.variant.options ? item.variant.options.map(o => o.name).join(' - ') : item.variant.sku) : ''}
                                            {@const itemFullName = item.product ? (variantName ? `${item.product.name} - ${variantName}` : item.product.name) : 'Produk Dihapus'}
                                            {@const rawImage = item.variant?.image || item.product?.image}
                                            {@const itemImage = !rawImage ? 'https://via.placeholder.com/150' : (
                                                rawImage.startsWith('http://') || rawImage.startsWith('https://') || rawImage.startsWith('/')
                                                    ? rawImage
                                                    : '/' + rawImage
                                            )}
                                            {@const itemSku = item.variant?.sku || item.product?.sku || '-'}
                                            {@const basePrice = item.variant?.product_price?.price ?? item.product?.product_price?.price ?? 0}
                                            {@const promoPrice = item.promo_price > 0 ? Number(item.promo_price) : (
                                                item.discount_type === 'percentage' 
                                                    ? Math.max(0, basePrice - (basePrice * Number(item.discount_value || 0)) / 100)
                                                    : (item.discount_type === 'fixed' ? Math.max(0, basePrice - Number(item.discount_value || 0)) : basePrice)
                                            )}
                                            
                                            <tr class="bg-slate-50/20 hover:bg-slate-50/50 border-b border-slate-100/80 transition duration-150">
                                                <td class="px-4 py-3 pl-8">
                                                    <div class="flex items-center gap-3">
                                                        <div class="w-3.5 h-3.5 border-l-2 border-b-2 border-slate-200 rounded-bl-md -mt-2.5 shrink-0 ml-1"></div>
                                                        <img
                                                            class="w-10 h-10 rounded-lg border border-slate-200 object-cover bg-slate-50 shrink-0"
                                                            src={itemImage}
                                                            alt={itemFullName}
                                                        />
                                                        <div class="min-w-0">
                                                            <p class="font-bold text-xs text-slate-700 truncate max-w-[220px]" title={itemFullName}>
                                                                {itemFullName}
                                                            </p>
                                                            <p class="text-[10px] text-slate-400 font-mono mt-0.5">
                                                                SKU: {itemSku}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 text-slate-300 font-semibold text-xs text-center">-</td>
                                                <td class="px-4 py-3">
                                                    <div class="flex flex-col gap-0.5">
                                                        {#if item.discount_type === 'percentage'}
                                                            <span class="font-bold text-xs text-slate-700">Diskon {Number(item.discount_value)}%</span>
                                                        {:else if item.discount_type === 'fixed'}
                                                            <span class="font-bold text-xs text-slate-700">Potongan Rp {Number(item.discount_value).toLocaleString('id-ID')}</span>
                                                        {:else}
                                                            <span class="font-bold text-xs text-slate-700">Kustom</span>
                                                        {/if}
                                                        
                                                        <div class="flex items-center text-[10px] mt-0.5">
                                                            {#if basePrice > 0}
                                                                <span class="text-slate-400 line-through mr-1.5">Rp {Number(basePrice).toLocaleString('id-ID')}</span>
                                                            {/if}
                                                            {#if promoPrice >= 0}
                                                                <span class="text-emerald-650 font-extrabold font-outfit">Rp {Number(promoPrice).toLocaleString('id-ID')}</span>
                                                            {/if}
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 text-slate-300 font-semibold text-xs text-center">-</td>
                                                <td class="px-4 py-3 text-center">
                                                    {#if item.promo_stock}
                                                        <div class="flex flex-col items-center">
                                                            <span class="font-bold text-slate-700 text-xs">
                                                                {item.promo_stock}
                                                            </span>
                                                            <p class="text-[9px] text-slate-400 font-bold uppercase tracking-wider mt-0.5">STOK PROMO</p>
                                                        </div>
                                                    {:else}
                                                        <span class="text-xs text-slate-500 bg-slate-100 px-2 py-0.5 rounded border border-slate-200/40 font-bold whitespace-nowrap">
                                                            ∞ Tanpa Batas
                                                        </span>
                                                    {/if}
                                                </td>
                                                <td class="px-4 py-3 text-slate-300 font-semibold text-xs text-center">-</td>
                                                <td class="px-4 py-3 text-slate-300 font-semibold text-xs text-right">-</td>
                                            </tr>
                                        {/each}
                                    {/if}
                                {/each}
                            {/if}
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <Pagination paginator={promotions} itemLabel="Promosi" />
            </div>
        </main>
    </div>

    <!-- Delete Confirmation Modal -->
    {#if deleteModalOpen}
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div
                class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity"
                onclick={() => (deleteModalOpen = false)}
                onkeypress={() => (deleteModalOpen = false)}
                role="button"
                tabindex="0"
            ></div>

            <div class="bg-white rounded-3xl p-6 sm:p-8 max-w-md w-full relative z-10 shadow-2xl animate-in fade-in zoom-in duration-200">
                <div class="w-16 h-16 rounded-full bg-red-50 text-red-500 flex items-center justify-center text-3xl mb-5 mx-auto">
                    <i class="ti ti-alert-triangle"></i>
                </div>
                <h4 class="font-outfit font-black text-xl text-center text-slate-800 mb-2">
                    Hapus Promosi?
                </h4>
                <p class="text-sm text-center text-slate-500 font-medium mb-8">
                    Data promosi ini akan terhapus secara permanen dari database dan tidak dapat dikembalikan.
                </p>
                <div class="flex items-center gap-3">
                    <button
                        onclick={() => (deleteModalOpen = false)}
                        class="flex-1 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-sm transition"
                    >
                        Batal
                    </button>
                    <button
                        onclick={executeDelete}
                        class="flex-1 py-3 bg-red-500 hover:bg-red-600 text-white font-bold rounded-xl text-sm shadow-lg shadow-red-500/30 transition"
                    >
                        Ya, Hapus
                    </button>
                </div>
            </div>
        </div>
    {/if}
</AdminLayout>
