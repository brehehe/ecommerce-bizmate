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
        metrics = {
            total_promotions: 0,
            active_promotions: 0,
            total_vouchers: 0,
            active_flash_sales: 0,
        },
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

    // QR Code Print Card Modal State
    let qrModalOpen = $state(false);
    let qrPromo = $state<any>(null);

    function openQrCard(promo: any) {
        qrPromo = promo;
        qrModalOpen = true;
    }

    function getQrCodeUrl(code: string, size = 200) {
        return `https://api.qrserver.com/v1/create-qr-code/?size=${size}x${size}&data=${encodeURIComponent(code)}&margin=10&format=png`;
    }

    function openPrintWindow(promo: any) {
        const discountText = getDiscountText(promo);

        const typeLabel = (() => {
            switch (promo.type) {
                case 'voucher_belanja': return '🎫 Voucher Belanja';
                case 'voucher_gratis_ongkir': return '🚚 Gratis Ongkir';
                case 'promo_toko': return '🏪 Promo Toko';
                case 'flash_sale': return '⚡ Flash Sale';
                default: return '🎁 Promosi';
            }
        })();

        const endDate = new Date(promo.end_time).toLocaleDateString('id-ID', {
            day: 'numeric', month: 'long', year: 'numeric'
        });

        const minPurchaseRow = promo.min_purchase > 0
            ? `<div class="detail-row">Min. belanja: <strong>Rp ${Number(promo.min_purchase).toLocaleString('id-ID')}</strong></div>` : '';
        const maxDiscountRow = promo.max_discount > 0
            ? `<div class="detail-row">Maks. diskon: <strong>Rp ${Number(promo.max_discount).toLocaleString('id-ID')}</strong></div>` : '';
        const quotaRow = promo.quota
            ? `<div class="detail-row">Sisa kuota: <strong>${promo.quota - (promo.used_count || 0)}</strong></div>` : '';
        const termsText = promo.settings?.terms
            ? `<div class="terms"><strong>Syarat & Ketentuan:</strong><br>${promo.settings.terms.replace(/\n/g, '<br>')}</div>` : '';
        const qrUrl = getQrCodeUrl(promo.code, 240);

        const html = `<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Voucher: ${promo.code}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;700;900&family=JetBrains+Mono:wght@700&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Outfit', sans-serif; background: #f8f9fa; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
    .card { width: 600px; border: 2px dashed #cbd5e1; border-radius: 20px; overflow: hidden; background: white; box-shadow: 0 8px 32px rgba(0,0,0,0.12); }
    .card-header { background: linear-gradient(135deg, #1e293b, #334155); padding: 20px 24px; display: flex; align-items: flex-start; justify-content: space-between; }
    .card-header-left .subtitle { font-size: 9px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 4px; }
    .card-header-left .title { font-size: 18px; font-weight: 900; color: white; line-height: 1.2; }
    .type-badge { display: inline-block; margin-top: 8px; background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.2); border-radius: 20px; padding: 3px 10px; font-size: 9px; font-weight: 800; color: white; text-transform: uppercase; letter-spacing: 1px; }
    .card-header-right { font-size: 9px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 1px; text-align: right; white-space: nowrap; margin-left: 16px; }
    .card-body { display: flex; }
    .card-info { flex: 1; padding: 20px; }
    .discount-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px 16px; margin-bottom: 14px; }
    .discount-label { font-size: 9px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 2px; }
    .discount-value { font-size: 22px; font-weight: 900; color: #1e293b; }
    .detail-row { font-size: 11px; color: #475569; margin-bottom: 4px; }
    .validity { font-size: 11px; color: #475569; margin-top: 4px; }
    .terms { margin-top: 12px; padding-top: 12px; border-top: 1px solid #e2e8f0; font-size: 9px; color: #64748b; line-height: 1.6; }
    .card-qr { width: 180px; background: #f8fafc; border-left: 1px solid #e2e8f0; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 16px; gap: 8px; }
    .card-qr img { width: 148px; height: 148px; border: 1px solid #e2e8f0; border-radius: 10px; background: white; padding: 4px; object-fit: contain; }
    .qr-code-label { font-size: 9px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; text-align: center; }
    .qr-code-text { font-family: 'JetBrains Mono', monospace; font-weight: 700; font-size: 13px; color: #1e293b; letter-spacing: 2px; text-align: center; }
    .card-footer { background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 14px 24px; display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .footer-col .footer-title { font-size: 9px; font-weight: 900; color: #475569; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px; }
    .footer-col ol { padding-left: 0; list-style: none; }
    .footer-col li { font-size: 9px; color: #64748b; margin-bottom: 2px; display: flex; gap: 5px; }
    .footer-col li span { font-weight: 700; color: #334155; }
    @media print {
      body { background: white; padding: 0; }
      .card { box-shadow: none; border-style: dashed; width: 100%; }
    }
  </style>
</head>
<body>
  <div class="card">
    <div class="card-header">
      <div class="card-header-left">
        <div class="subtitle">Voucher Spesial Untuk Kamu</div>
        <div class="title">${promo.name}</div>
        <span class="type-badge">${typeLabel}</span>
      </div>
      <div class="card-header-right">SCAN &amp; KLAIM!</div>
    </div>
    <div class="card-body">
      <div class="card-info">
        <div class="discount-box">
          <div class="discount-label">Nilai Diskon</div>
          <div class="discount-value">${discountText}</div>
        </div>
        ${minPurchaseRow}
        ${maxDiscountRow}
        ${quotaRow}
        <div class="validity">Berlaku s/d: <strong>${endDate}</strong></div>
        ${termsText}
      </div>
      <div class="card-qr">
        <img src="${qrUrl}" alt="QR ${promo.code}" />
        <div class="qr-code-label">Kode:</div>
        <div class="qr-code-text">${promo.code}</div>
      </div>
    </div>
    <div class="card-footer">
      <div class="footer-col">
        <div class="footer-title">Klaim via QR Code:</div>
        <ol>
          <li><span>1.</span> Buka kamera ponsel</li>
          <li><span>2.</span> Scan QR Code ini</li>
          <li><span>3.</span> Buka link yang muncul</li>
          <li><span>4.</span> Masukkan kode di checkout</li>
        </ol>
      </div>
      <div class="footer-col">
        <div class="footer-title">Klaim Manual:</div>
        <ol>
          <li><span>1.</span> Buka halaman checkout</li>
          <li><span>2.</span> Klik Voucher &amp; Promo</li>
          <li><span>3.</span> Ketik kode: <strong>${promo.code}</strong></li>
          <li><span>4.</span> Klik Pakai &amp; selesai!</li>
        </ol>
      </div>
    </div>
  </div>
  <script>
    window.onload = function() {
      // Wait for QR image to load before printing
      var img = document.querySelector('img');
      if (img && img.complete) { window.print(); }
      else if (img) { img.onload = function() { window.print(); }; }
      else { window.print(); }
    };
  <\/script>
</body>
</html>`;

        const printWindow = window.open('', '_blank', 'width=700,height=600');
        if (printWindow) {
            printWindow.document.write(html);
            printWindow.document.close();
        }
    }

    function applyFilters() {
        router.get(
            adminPromotionsIndex.url(),
            {
                search: searchInput,
                type: filterType,
                status: filterStatus,
            },
            { preserveState: true, preserveScroll: true, replace: true },
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
                },
            },
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
                    },
                },
            );
        }
    }

    function getPromotionTypeBadge(type: string) {
        switch (type) {
            case 'promo_toko':
                return {
                    label: 'Promo Toko',
                    class: 'bg-blue-50 text-blue-600 border-blue-100',
                };
            case 'voucher_belanja':
                return {
                    label: 'Voucher Belanja',
                    class: 'bg-emerald-50 text-emerald-600 border-emerald-100',
                };
            case 'voucher_gratis_ongkir':
                return {
                    label: 'Gratis Ongkir',
                    class: 'bg-teal-50 text-teal-600 border-teal-100',
                };
            case 'flash_sale':
                return {
                    label: 'Flash Sale',
                    class: 'bg-rose-50 text-rose-600 border-rose-100 animate-pulse',
                };
            case 'bundling_gift':
                return {
                    label: 'Bundling & Gift',
                    class: 'bg-purple-50 text-purple-600 border-purple-100',
                };

            default:
                return {
                    label: 'Promosi',
                    class: 'bg-slate-50 text-slate-600 border-slate-100',
                };
        }
    }

    function getDiscountText(promo: any) {
        if (!promo) return '-';
        if (promo.settings?.is_points_voucher) {
            const newUserPoints = Number(promo.settings.points_new_user || 0).toLocaleString('id-ID');
            const oldUserPoints = Number(promo.settings.points_existing_user || 0).toLocaleString('id-ID');
            return `+${newUserPoints} Poin (Baru) / +${oldUserPoints} Poin (Lama)`;
        }
        if (promo.type === 'voucher_gratis_ongkir') {
            return promo.discount_value
                ? `Potongan Ongkir Rp ${Number(promo.discount_value).toLocaleString('id-ID')}`
                : 'Gratis Ongkir 100%';
        }
        if (promo.type === 'bundling_gift') {
            return 'Bundling / Gift';
        }
        if (promo.discount_type === 'percentage') {
            return `${Number(promo.discount_value)}% OFF`;
        }
        if (promo.discount_type === 'fixed') {
            return `Rp ${Number(promo.discount_value).toLocaleString('id-ID')} OFF`;
        }
        return '-';
    }

    function getScheduleStatus(promo: any) {
        const now = new Date();
        const start = new Date(promo.start_time);
        const end = new Date(promo.end_time);

        if (now < start) {
            return {
                text: 'Belum Mulai',
                class: 'bg-slate-100 text-slate-500 border-slate-200',
            };
        }
        if (now > end) {
            return {
                text: 'Berakhir',
                class: 'bg-red-50 text-red-500 border-red-100',
            };
        }
        return {
            text: 'Aktif',
            class: 'bg-emerald-50 text-emerald-600 border-emerald-100',
        };
    }

    function formatDateTime(dateTimeStr: string) {
        const date = new Date(dateTimeStr);
        return date.toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    }
</script>

<svelte:head>
    <title>Manajemen Promosi</title>
</svelte:head>

<AdminLayout>
    <div class="flex-grow flex flex-col min-h-screen">
        <main
            class="flex-grow p-4 sm:p-6 xl:p-8 w-full max-w-full mx-auto space-y-6 overflow-hidden"
        >
            <!-- Page Header -->
            <div
                class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4"
            >
                <div>
                    <h3 class="font-outfit font-black text-2xl text-slate-800">
                        Manajemen Promosi
                    </h3>
                    <p
                        class="text-xs text-slate-400 font-bold uppercase tracking-wider mt-1"
                    >
                        Buat Voucher, Flash Sale, Promo Toko, & Bundling Menarik
                        Untuk Pelanggan Anda
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
                <div
                    class="bg-white border border-slate-200 rounded-2xl p-4 shadow-soft"
                >
                    <p
                        class="text-[10px] font-bold text-slate-500 uppercase tracking-widest font-outfit mb-1"
                    >
                        Total Promosi
                    </p>
                    <p class="text-2xl font-black text-slate-800">
                        {metrics.total_promotions}
                    </p>
                </div>
                <div
                    class="bg-white border border-slate-200 rounded-2xl p-4 shadow-soft"
                >
                    <p
                        class="text-[10px] font-bold text-slate-500 uppercase tracking-widest font-outfit mb-1"
                    >
                        Promosi Aktif Saat Ini
                    </p>
                    <p class="text-2xl font-black text-emerald-600">
                        {metrics.active_promotions}
                    </p>
                </div>
                <div
                    class="bg-white border border-slate-200 rounded-2xl p-4 shadow-soft"
                >
                    <p
                        class="text-[10px] font-bold text-slate-500 uppercase tracking-widest font-outfit mb-1"
                    >
                        Voucher Belanja & Ongkir
                    </p>
                    <p class="text-2xl font-black text-purple-600">
                        {metrics.total_vouchers}
                    </p>
                </div>
                <div
                    class="bg-white border border-slate-200 rounded-2xl p-4 shadow-soft"
                >
                    <p
                        class="text-[10px] font-bold text-slate-500 uppercase tracking-widest font-outfit mb-1"
                    >
                        Flash Sale Berjalan
                    </p>
                    <p class="text-2xl font-black text-rose-500">
                        {metrics.active_flash_sales}
                    </p>
                </div>
            </div>

            <!-- Advanced Data Table -->
            <div
                class="bg-white rounded-3xl border border-slate-200 shadow-card flex flex-col"
            >
                <!-- Filters Bar -->
                <div
                    class="p-4 sm:p-6 border-b border-slate-100 flex flex-col xl:flex-row gap-4 justify-between items-stretch xl:items-center bg-slate-50/50 rounded-t-3xl"
                >
                    <div class="flex items-center gap-3 w-full xl:w-auto">
                        <div class="relative w-full xl:w-64">
                            <i
                                class="ti ti-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"
                            ></i>
                            <input
                                type="text"
                                bind:value={searchInput}
                                oninput={handleSearchInput}
                                placeholder="Cari Nama Promosi, Kode..."
                                class="w-full pl-10 pr-4 py-2 rounded-xl border border-slate-200 text-sm text-slate-700 focus:border-brand-blueRoyal focus:ring-1 focus:ring-brand-blueRoyal focus:outline-none"
                            />
                        </div>
                    </div>
                    <div
                        class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full xl:w-auto"
                    >
                        <div
                            class="grid grid-cols-1 sm:grid-cols-2 gap-2 w-full sm:w-auto"
                        >
                            <select
                                bind:value={filterType}
                                onchange={applyFilters}
                                class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-600 focus:outline-none"
                            >
                                <option value="all">Semua Tipe</option>
                                <option value="promo_toko">Promo Toko</option>
                                <option value="voucher_belanja"
                                    >Voucher Belanja</option
                                >
                                <option value="voucher_gratis_ongkir"
                                    >Gratis Ongkir</option
                                >
                                <option value="flash_sale">Flash Sale</option>
                                <option value="bundling_gift"
                                    >Bundling & Gift</option
                                >
                            </select>
                            <select
                                bind:value={filterStatus}
                                onchange={applyFilters}
                                class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-600 focus:outline-none"
                            >
                                <option value="all">Status: Semua</option>
                                <option value="active">Status: Aktif</option>
                                <option value="inactive"
                                    >Status: Nonaktif</option
                                >
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Table View -->
                <div class="overflow-x-auto flex-grow custom-scrollbar">
                    <table
                        class="w-full text-left border-collapse min-w-[900px] xl:min-w-0 table-fixed"
                    >
                        <thead>
                            <tr class="bg-white">
                                <th
                                    class="px-4 py-4 w-[28%] text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100"
                                    >Info Promosi</th
                                >
                                <th
                                    class="px-4 py-4 w-[12%] text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100"
                                    >Kode</th
                                >
                                <th
                                    class="px-4 py-4 w-[16%] text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100"
                                    >Nilai Diskon</th
                                >
                                <th
                                    class="px-4 py-4 w-[24%] text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100"
                                    >Masa Berlaku</th
                                >
                                <th
                                    class="px-4 py-4 w-[10%] text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100 text-center"
                                    >Kuota / Terpakai</th
                                >
                                <th
                                    class="px-4 py-4 w-[8%] text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100 text-center"
                                    >Status</th
                                >
                                <th
                                    class="px-4 py-4 w-[8%] text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100 text-right"
                                    >Aksi</th
                                >
                            </tr>
                        </thead>
                        <tbody
                            class="text-sm text-slate-700 divide-y divide-slate-100"
                        >
                            {#if promotions.data.length === 0}
                                <tr>
                                    <td
                                        colspan="7"
                                        class="px-6 py-12 text-center text-slate-400 font-medium"
                                    >
                                        <i
                                            class="ti ti-ticket-off text-4xl block mb-2 opacity-50"
                                        ></i>
                                        Tidak ada promosi ditemukan.
                                    </td>
                                </tr>
                            {:else}
                                {#each promotions.data as promo (promo.id)}
                                    {@const typeBadge = getPromotionTypeBadge(
                                        promo.type,
                                    )}
                                    {@const scheduleBadge =
                                        getScheduleStatus(promo)}
                                    <tr
                                        class="table-row-hover transition {!promo.is_active
                                            ? 'bg-slate-50/30'
                                            : ''}"
                                    >
                                        <td class="px-4 py-4">
                                            <div
                                                class="flex items-center gap-3"
                                            >
                                                {#if promo.items && promo.items.length > 0}
                                                    <button
                                                        onclick={() =>
                                                            togglePromotionDrawer(
                                                                promo.id,
                                                            )}
                                                        class="w-7 h-7 rounded-xl bg-slate-50 hover:bg-brand-blueLight hover:text-brand-blueRoyal border border-slate-200/60 hover:border-brand-blueRoyal/20 flex items-center justify-center text-slate-400 transition duration-200 shrink-0 shadow-soft"
                                                        title="Lihat produk target"
                                                    >
                                                        <i
                                                            class="ti ti-chevron-down text-xs transition-transform duration-300 {expandedPromotions.has(
                                                                promo.id,
                                                            )
                                                                ? 'rotate-180'
                                                                : ''}"
                                                        ></i>
                                                    </button>
                                                {:else}
                                                    <div
                                                        class="w-7 h-7 shrink-0"
                                                    ></div>
                                                {/if}
                                                <div
                                                    class="flex flex-col gap-1"
                                                >
                                                    <span
                                                        class="font-bold text-slate-800 block truncate max-w-[200px]"
                                                        title={promo.name}
                                                    >
                                                        {promo.name}
                                                    </span>
                                                    <span
                                                        class="inline-flex items-center self-start px-2 py-0.5 border rounded-md text-[9px] font-extrabold uppercase tracking-wider {typeBadge.class}"
                                                    >
                                                        {typeBadge.label}
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4">
                                            {#if promo.code}
                                                <span
                                                    class="font-mono font-bold text-xs bg-slate-100 text-slate-600 px-2 py-1 rounded border border-slate-200"
                                                >
                                                    {promo.code}
                                                </span>
                                            {:else}
                                                <span
                                                    class="text-slate-400 font-medium"
                                                    >-</span
                                                >
                                            {/if}
                                        </td>
                                        <td class="px-4 py-4">
                                            <span
                                                class="font-black text-slate-800 text-[12px] xl:text-[13px]"
                                            >
                                                {getDiscountText(promo)}
                                            </span>
                                            {#if promo.min_purchase > 0}
                                                <p
                                                    class="text-[10px] text-slate-400 font-medium mt-0.5"
                                                >
                                                    Min: Rp {Number(
                                                        promo.min_purchase,
                                                    ).toLocaleString('id-ID')}
                                                </p>
                                            {/if}
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="flex flex-col gap-0.5">
                                                <p
                                                    class="text-xs text-slate-600 font-semibold"
                                                >
                                                    Mulai: {formatDateTime(
                                                        promo.start_time,
                                                    )}
                                                </p>
                                                <p
                                                    class="text-xs text-slate-500 font-medium"
                                                >
                                                    Selesai: {formatDateTime(
                                                        promo.end_time,
                                                    )}
                                                </p>
                                                <span
                                                    class="inline-flex items-center self-start px-1.5 py-0.5 border rounded text-[8px] font-bold uppercase tracking-wider mt-1 {scheduleBadge.class}"
                                                >
                                                    {scheduleBadge.text}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4 text-center">
                                            {#if promo.quota}
                                                <div
                                                    class="flex flex-col items-center"
                                                >
                                                    <span
                                                        class="font-bold text-slate-700 text-xs"
                                                    >
                                                        {promo.used_count} / {promo.quota}
                                                    </span>
                                                    <div
                                                        class="w-16 bg-slate-100 rounded-full h-1 mt-1 overflow-hidden"
                                                    >
                                                        <div
                                                            class="bg-brand-blueRoyal h-1 rounded-full"
                                                            style="width: {Math.min(
                                                                100,
                                                                (promo.used_count /
                                                                    promo.quota) *
                                                                    100,
                                                            )}%"
                                                        ></div>
                                                    </div>
                                                </div>
                                            {:else}
                                                <span
                                                    class="text-xs text-slate-500 bg-slate-100 px-2 py-0.5 rounded border border-slate-200/40 font-bold whitespace-nowrap"
                                                >
                                                    ∞ Tanpa Batas
                                                </span>
                                            {/if}
                                        </td>
                                        <td class="px-4 py-4 text-center">
                                            <label
                                                class="relative inline-flex items-center cursor-pointer"
                                            >
                                                <input
                                                    type="checkbox"
                                                    checked={promo.is_active}
                                                    onchange={() =>
                                                        toggleActive(promo.id)}
                                                    class="sr-only peer"
                                                />
                                                <div
                                                    class="w-9 h-5 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-brand-blueRoyal"
                                                ></div>
                                            </label>
                                        </td>
                                        <td
                                            class="px-4 py-4 text-right whitespace-nowrap"
                                        >
                                            {#if promo.code}
                                                <button
                                                    onclick={() => openQrCard(promo)}
                                                    class="p-2 text-slate-400 hover:text-purple-600 rounded-lg transition inline-block"
                                                    title="Lihat QR Code Voucher"
                                                >
                                                    <i class="ti ti-qrcode text-lg"></i>
                                                </button>
                                            {/if}
                                            <Link
                                                href={adminPromotionsEdit.url({
                                                    promotion: promo.id,
                                                })}
                                                class="p-2 text-slate-400 hover:text-brand-blueRoyal rounded-lg transition inline-block"
                                                title="Edit"
                                            >
                                                <i class="ti ti-edit text-lg"
                                                ></i>
                                            </Link>
                                            <button
                                                onclick={() =>
                                                    confirmDelete(promo.id)}
                                                class="p-2 text-slate-400 hover:text-red-500 rounded-lg transition inline-block"
                                                title="Hapus"
                                            >
                                                <i class="ti ti-trash text-lg"
                                                ></i>
                                            </button>
                                        </td>
                                    </tr>

                                    {#if promo.items && promo.items.length > 0 && expandedPromotions.has(promo.id)}
                                        {#each promo.items as item (item.id)}
                                            {@const variantName = item.variant
                                                ? item.variant.options
                                                    ? item.variant.options
                                                          .map((o) => o.name)
                                                          .join(' - ')
                                                    : item.variant.sku
                                                : ''}
                                            {@const itemFullName = item.product
                                                ? variantName
                                                    ? `${item.product.name} - ${variantName}`
                                                    : item.product.name
                                                : 'Produk Dihapus'}
                                            {@const rawImage =
                                                item.variant?.image ||
                                                item.product?.image}
                                            {@const itemImage = !rawImage
                                                ? 'https://via.placeholder.com/150'
                                                : rawImage.startsWith(
                                                        'http://',
                                                    ) ||
                                                    rawImage.startsWith(
                                                        'https://',
                                                    ) ||
                                                    rawImage.startsWith('/')
                                                  ? rawImage
                                                  : '/' + rawImage}
                                            {@const itemSku =
                                                item.variant?.sku ||
                                                item.product?.sku ||
                                                '-'}
                                            {@const basePrice =
                                                item.variant?.product_price
                                                    ?.price ??
                                                item.product?.product_price
                                                    ?.price ??
                                                0}
                                            {@const promoPrice =
                                                item.promo_price > 0
                                                    ? Number(item.promo_price)
                                                    : item.discount_type ===
                                                        'percentage'
                                                      ? Math.max(
                                                            0,
                                                            basePrice -
                                                                (basePrice *
                                                                    Number(
                                                                        item.discount_value ||
                                                                            0,
                                                                    )) /
                                                                    100,
                                                        )
                                                      : item.discount_type ===
                                                          'fixed'
                                                        ? Math.max(
                                                              0,
                                                              basePrice -
                                                                  Number(
                                                                      item.discount_value ||
                                                                          0,
                                                                  ),
                                                          )
                                                        : basePrice}

                                            <tr
                                                class="bg-slate-50/20 hover:bg-slate-50/50 border-b border-slate-100/80 transition duration-150"
                                            >
                                                <td class="px-4 py-3 pl-8">
                                                    <div
                                                        class="flex items-center gap-3"
                                                    >
                                                        <div
                                                            class="w-3.5 h-3.5 border-l-2 border-b-2 border-slate-200 rounded-bl-md -mt-2.5 shrink-0 ml-1"
                                                        ></div>
                                                        <img
                                                            class="w-10 h-10 rounded-lg border border-slate-200 object-cover bg-slate-50 shrink-0"
                                                            src={itemImage}
                                                            alt={itemFullName}
                                                        />
                                                        <div class="min-w-0">
                                                            <p
                                                                class="font-bold text-xs text-slate-700 truncate max-w-[220px]"
                                                                title={itemFullName}
                                                            >
                                                                {itemFullName}
                                                            </p>
                                                            <p
                                                                class="text-[10px] text-slate-400 font-mono mt-0.5"
                                                            >
                                                                SKU: {itemSku}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td
                                                    class="px-4 py-3 text-slate-300 font-semibold text-xs text-center"
                                                    >-</td
                                                >
                                                <td class="px-4 py-3">
                                                    <div
                                                        class="flex flex-col gap-0.5"
                                                    >
                                                        {#if item.discount_type === 'percentage'}
                                                            <span
                                                                class="font-bold text-xs text-slate-700"
                                                                >Diskon {Number(
                                                                    item.discount_value,
                                                                )}%</span
                                                            >
                                                        {:else if item.discount_type === 'fixed'}
                                                            <span
                                                                class="font-bold text-xs text-slate-700"
                                                                >Potongan Rp {Number(
                                                                    item.discount_value,
                                                                ).toLocaleString(
                                                                    'id-ID',
                                                                )}</span
                                                            >
                                                        {:else}
                                                            <span
                                                                class="font-bold text-xs text-slate-700"
                                                                >Kustom</span
                                                            >
                                                        {/if}

                                                        <div
                                                            class="flex items-center text-[10px] mt-0.5"
                                                        >
                                                            {#if basePrice > 0}
                                                                <span
                                                                    class="text-slate-400 line-through mr-1.5"
                                                                    >Rp {Number(
                                                                        basePrice,
                                                                    ).toLocaleString(
                                                                        'id-ID',
                                                                    )}</span
                                                                >
                                                            {/if}
                                                            {#if promoPrice >= 0}
                                                                <span
                                                                    class="text-emerald-650 font-extrabold font-outfit"
                                                                    >Rp {Number(
                                                                        promoPrice,
                                                                    ).toLocaleString(
                                                                        'id-ID',
                                                                    )}</span
                                                                >
                                                            {/if}
                                                        </div>
                                                    </div>
                                                </td>
                                                <td
                                                    class="px-4 py-3 text-slate-300 font-semibold text-xs text-center"
                                                    >-</td
                                                >
                                                <td
                                                    class="px-4 py-3 text-center"
                                                >
                                                    {#if item.promo_stock}
                                                        <div
                                                            class="flex flex-col items-center"
                                                        >
                                                            <span
                                                                class="font-bold text-slate-700 text-xs"
                                                            >
                                                                {item.promo_stock}
                                                            </span>
                                                            <p
                                                                class="text-[9px] text-slate-400 font-bold uppercase tracking-wider mt-0.5"
                                                            >
                                                                STOK PROMO
                                                            </p>
                                                        </div>
                                                    {:else}
                                                        <span
                                                            class="text-xs text-slate-500 bg-slate-100 px-2 py-0.5 rounded border border-slate-200/40 font-bold whitespace-nowrap"
                                                        >
                                                            ∞ Tanpa Batas
                                                        </span>
                                                    {/if}
                                                </td>
                                                <td
                                                    class="px-4 py-3 text-slate-300 font-semibold text-xs text-center"
                                                    >-</td
                                                >
                                                <td
                                                    class="px-4 py-3 text-slate-300 font-semibold text-xs text-right"
                                                    >-</td
                                                >
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

    <!-- QR Code Print Card Modal -->
    {#if qrModalOpen && qrPromo}
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 print:hidden" id="qr-modal-overlay">
            <div
                class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm"
                onclick={() => (qrModalOpen = false)}
                onkeypress={() => (qrModalOpen = false)}
                role="button"
                tabindex="0"
            ></div>

            <div class="bg-white rounded-3xl shadow-2xl max-w-lg w-full relative z-10 overflow-hidden animate-in fade-in zoom-in duration-200">
                <!-- Modal Header -->
                <div class="flex items-center justify-between px-6 pt-6 pb-4 border-b border-slate-100">
                    <div>
                        <h4 class="font-outfit font-black text-lg text-slate-800">QR Code Voucher</h4>
                        <p class="text-xs text-slate-500 font-medium mt-0.5">Cetak dan bagikan kepada pelanggan</p>
                    </div>
                    <button
                        onclick={() => (qrModalOpen = false)}
                        class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-500 transition"
                    >
                        <i class="ti ti-x text-sm"></i>
                    </button>
                </div>

                <!-- Print Card Preview -->
                <div class="p-6" id="qr-print-card">
                    <!-- Card Container -->
                    <div class="border-2 border-dashed border-slate-200 rounded-2xl overflow-hidden">
                        <!-- Card Top: Colored Header -->
                        <div class="bg-gradient-to-br from-slate-800 to-slate-900 px-5 py-4 flex items-start justify-between">
                            <div>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Voucher Spesial Untuk Kamu</p>
                                <h3 class="font-outfit font-black text-white text-base leading-tight">{qrPromo.name}</h3>
                                <div class="mt-2">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[9px] font-extrabold uppercase tracking-wider bg-white/15 text-white border border-white/20">
                                        {#if qrPromo.type === 'voucher_belanja'}
                                            🎫 Voucher Belanja
                                        {:else if qrPromo.type === 'voucher_gratis_ongkir'}
                                            🚚 Gratis Ongkir
                                        {:else if qrPromo.type === 'promo_toko'}
                                            🏪 Promo Toko
                                        {:else if qrPromo.type === 'flash_sale'}
                                            ⚡ Flash Sale
                                        {:else}
                                            🎁 Promosi
                                        {/if}
                                    </span>
                                </div>
                            </div>
                            <div class="text-right shrink-0 ml-4">
                                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">SCAN & KLAIM!</p>
                            </div>
                        </div>

                        <!-- Card Body -->
                        <div class="flex gap-0">
                            <!-- Left: Promo Info -->
                            <div class="flex-1 p-5 bg-white">
                                <!-- Discount Value -->
                                <div class="bg-slate-50 border border-slate-200 rounded-xl p-3 mb-3">
                                    <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider mb-1">Nilai Diskon</p>
                                    <p class="font-outfit font-black text-slate-800 text-lg">
                                        {getDiscountText(qrPromo)}
                                    </p>
                                </div>

                                <!-- Voucher Details -->
                                <div class="space-y-1.5">
                                    {#if qrPromo.min_purchase > 0}
                                        <div class="flex items-start gap-2">
                                            <i class="ti ti-shopping-cart text-slate-400 text-xs mt-0.5 shrink-0"></i>
                                            <p class="text-xs text-slate-600 font-medium">
                                                Min. belanja Rp {Number(qrPromo.min_purchase).toLocaleString('id-ID')}
                                            </p>
                                        </div>
                                    {/if}
                                    {#if qrPromo.max_discount > 0}
                                        <div class="flex items-start gap-2">
                                            <i class="ti ti-coin text-slate-400 text-xs mt-0.5 shrink-0"></i>
                                            <p class="text-xs text-slate-600 font-medium">
                                                Maks. diskon Rp {Number(qrPromo.max_discount).toLocaleString('id-ID')}
                                            </p>
                                        </div>
                                    {/if}
                                    {#if qrPromo.quota}
                                        <div class="flex items-start gap-2">
                                            <i class="ti ti-users text-slate-400 text-xs mt-0.5 shrink-0"></i>
                                            <p class="text-xs text-slate-600 font-medium">
                                                Kuota: {qrPromo.quota - (qrPromo.used_count || 0)} sisa
                                            </p>
                                        </div>
                                    {/if}
                                    <div class="flex items-start gap-2">
                                        <i class="ti ti-calendar text-slate-400 text-xs mt-0.5 shrink-0"></i>
                                        <p class="text-xs text-slate-600 font-medium">
                                            Berlaku s/d {new Date(qrPromo.end_time).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })}
                                        </p>
                                    </div>
                                </div>

                                <!-- Terms -->
                                {#if qrPromo.settings?.terms}
                                    <div class="mt-3 pt-3 border-t border-slate-100">
                                        <p class="text-[9px] text-slate-500 font-medium leading-relaxed">
                                            *{qrPromo.settings.terms}
                                        </p>
                                    </div>
                                {/if}
                            </div>

                            <!-- Right: QR Code -->
                            <div class="w-44 bg-slate-50 border-l border-slate-200 flex flex-col items-center justify-center p-4 gap-2">
                                <img
                                    src={getQrCodeUrl(qrPromo.code)}
                                    alt="QR Code {qrPromo.code}"
                                    class="w-36 h-36 object-contain rounded-lg border border-slate-200 bg-white p-1"
                                />
                                <div class="text-center">
                                    <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Kode:</p>
                                    <p class="font-mono font-black text-slate-800 text-sm tracking-widest mt-0.5">
                                        {qrPromo.code}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Card Footer: How to use -->
                        <div class="bg-slate-50 border-t border-slate-200 px-5 py-3">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <p class="text-[9px] font-black text-slate-600 uppercase tracking-wider mb-1.5">Klaim Melalui QR Code:</p>
                                    <ol class="space-y-0.5">
                                        {#each ['Buka kamera ponsel', 'Scan Kode QR ini', 'Buka link yang muncul', 'Masukkan kode di checkout'] as step, i}
                                            <li class="text-[9px] text-slate-500 font-medium flex gap-1.5">
                                                <span class="font-bold text-slate-700">{i + 1}.</span>
                                                {step}
                                            </li>
                                        {/each}
                                    </ol>
                                </div>
                                <div>
                                    <p class="text-[9px] font-black text-slate-600 uppercase tracking-wider mb-1.5">Klaim Manual:</p>
                                    <ol class="space-y-0.5">
                                        {#each ['Buka halaman checkout', 'Klik bagian Voucher & Promo', `Ketik kode: ${qrPromo.code}`, 'Klik Pakai & selesai!'] as step, i}
                                            <li class="text-[9px] text-slate-500 font-medium flex gap-1.5">
                                                <span class="font-bold text-slate-700">{i + 1}.</span>
                                                {step}
                                            </li>
                                        {/each}
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Actions -->
                <div class="px-6 pb-6 flex gap-3">
                    <button
                        onclick={() => (qrModalOpen = false)}
                        class="flex-1 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-sm transition"
                    >
                        Tutup
                    </button>
                    <button
                        onclick={() => openPrintWindow(qrPromo)}
                        class="flex-1 py-3 bg-gradient-to-br from-slate-800 to-slate-900 hover:from-slate-700 hover:to-slate-800 text-white font-bold rounded-xl text-sm shadow-lg shadow-slate-900/20 transition flex items-center justify-center gap-2"
                    >
                        <i class="ti ti-printer text-base"></i>
                        Cetak Kartu
                    </button>
                </div>
            </div>
        </div>
    {/if}

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

            <div
                class="bg-white rounded-3xl p-6 sm:p-8 max-w-md w-full relative z-10 shadow-2xl animate-in fade-in zoom-in duration-200"
            >
                <div
                    class="w-16 h-16 rounded-full bg-red-50 text-red-500 flex items-center justify-center text-3xl mb-5 mx-auto"
                >
                    <i class="ti ti-alert-triangle"></i>
                </div>
                <h4
                    class="font-outfit font-black text-xl text-center text-slate-800 mb-2"
                >
                    Hapus Promosi?
                </h4>
                <p class="text-sm text-center text-slate-500 font-medium mb-8">
                    Data promosi ini akan terhapus secara permanen dari database
                    dan tidak dapat dikembalikan.
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
