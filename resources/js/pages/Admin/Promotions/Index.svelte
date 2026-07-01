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
    let pdfLoading = $state<number | null>(null);

    function openQrCard(promo: any) {
        qrPromo = promo;
        qrModalOpen = true;
    }

    function getQrCodeUrl(code: string, size = 200) {
        return `https://api.qrserver.com/v1/create-qr-code/?size=${size}x${size}&data=${encodeURIComponent(code)}&margin=10&format=png`;
    }

    function downloadPdf(promo: any) {
        if (!promo || pdfLoading !== null) return;
        pdfLoading = promo.id;

        const runDownload = () => {
            const container = document.createElement('div');
            container.style.position = 'fixed';
            container.style.top = '0';
            container.style.left = '0';
            container.style.width = '1120px';
            container.style.height = '0';
            container.style.overflow = 'hidden';
            container.style.zIndex = '-9999';
            container.style.pointerEvents = 'none';

            const element = document.createElement('div');
            element.style.width = '1120px';
            element.style.background = 'white';

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
                ? `<div class="detail-row" style="font-size: 16px; color: #475569; margin-bottom: 8px;">Min. belanja: <strong>Rp ${Number(promo.min_purchase).toLocaleString('id-ID')}</strong></div>` : '';
            const maxDiscountRow = promo.max_discount > 0
                ? `<div class="detail-row" style="font-size: 16px; color: #475569; margin-bottom: 8px;">Maks. diskon: <strong>Rp ${Number(promo.max_discount).toLocaleString('id-ID')}</strong></div>` : '';
            const quotaRow = promo.quota
                ? `<div class="detail-row" style="font-size: 16px; color: #475569; margin-bottom: 8px;">Sisa kuota: <strong>${promo.quota - (promo.used_count || 0)}</strong></div>` : '';
            const termsText = promo.settings?.terms
                ? `<div class="terms" style="margin-top: 24px; padding-top: 16px; border-top: 1px dashed #cbd5e1; font-size: 12px; color: #64748b; line-height: 1.6;"><strong>Syarat & Ketentuan:</strong><br>${promo.settings.terms.replace(/\n/g, '<br>')}</div>` : '';
            const qrUrl = getQrCodeUrl(promo.code, 300);

            element.innerHTML = `
              <div class="card" style="width: 1120px; height: 792px; border: 3px dashed #cbd5e1; border-radius: 28px; overflow: hidden; background: white; font-family: 'Outfit', sans-serif; box-sizing: border-box; display: flex; flex-direction: column; justify-content: space-between;">
                <!-- Header -->
                <div class="card-header" style="background: linear-gradient(135deg, #1e293b, #334155); padding: 32px 48px; display: flex; align-items: center; justify-content: space-between; flex-direction: row; color: white;">
                  <div class="card-header-left">
                    <div class="subtitle" style="font-size: 12px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 3px; margin-bottom: 8px;">Voucher Spesial Untuk Kamu</div>
                    <div class="title" style="font-size: 32px; font-weight: 900; color: white; line-height: 1.2;">${promo.name}</div>
                    <span class="type-badge" style="display: inline-block; margin-top: 12px; background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.2); border-radius: 20px; padding: 6px 14px; font-size: 11px; font-weight: 800; color: white; text-transform: uppercase; letter-spacing: 1.5px;">${typeLabel}</span>
                  </div>
                  <div class="card-header-right" style="font-size: 13px; font-weight: 800; color: #38bdf8; text-transform: uppercase; letter-spacing: 2px; text-align: right; white-space: nowrap; border: 2px solid #38bdf8; padding: 8px 16px; border-radius: 8px; margin-left: 16px;">SCAN &amp; KLAIM!</div>
                </div>

                <!-- Body -->
                <div class="card-body" style="display: flex; flex-direction: row; flex: 1; min-height: 0;">
                  <!-- Left Info Column -->
                  <div class="card-info" style="flex: 1; padding: 32px 48px; display: flex; flex-direction: column; justify-content: center;">
                    <div class="discount-box" style="background: #f1f5f9; border: 1.5px solid #e2e8f0; border-radius: 16px; padding: 20px 28px; margin-bottom: 24px;">
                      <div class="discount-label" style="font-size: 12px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 6px;">Nilai Diskon</div>
                      <div class="discount-value" style="font-size: 42px; font-weight: 900; color: #0f172a;">${discountText}</div>
                    </div>
                    ${minPurchaseRow}
                    ${maxDiscountRow}
                    ${quotaRow}
                    <div class="validity" style="font-size: 16px; color: #475569; margin-top: 8px;">Berlaku s/d: <strong style="color: #0f172a;">${endDate}</strong></div>
                    ${termsText}
                  </div>

                  <!-- Right QR Column -->
                  <div class="card-qr" style="width: 360px; background: #f8fafc; border-left: 1px solid #e2e8f0; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 32px; gap: 14px;">
                    <img src="${qrUrl}" crossorigin="anonymous" alt="QR ${promo.code}" style="width: 200px; height: 200px; border: 1px solid #cbd5e1; border-radius: 12px; background: white; padding: 8px; object-fit: contain; box-shadow: 0 4px 12px rgba(0,0,0,0.05);" />
                    <div class="qr-code-label" style="font-size: 12px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 1.5px; text-align: center; margin-top: 8px;">Kode Voucher:</div>
                    <div class="qr-code-text" style="font-family: monospace; font-weight: 800; font-size: 22px; color: #0f172a; letter-spacing: 3px; text-align: center; background: #e2e8f0; padding: 6px 16px; border-radius: 8px; border: 1px solid #cbd5e1;">${promo.code}</div>
                  </div>
                </div>

                <!-- Footer -->
                <div class="card-footer" style="background: #f1f5f9; border-top: 1.5px solid #e2e8f0; padding: 24px 48px; display: grid; grid-template-columns: 1fr 1fr; gap: 32px;">
                  <div class="footer-col">
                    <div class="footer-title" style="font-size: 12px; font-weight: 900; color: #1e293b; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">📱 Klaim via QR Code:</div>
                    <div style="font-size: 12px; color: #475569; margin-bottom: 4px; line-height: 1.5;">1. Buka aplikasi kamera ponsel Anda</div>
                    <div style="font-size: 12px; color: #475569; margin-bottom: 4px; line-height: 1.5;">2. Pindai/Scan QR Code di atas</div>
                    <div style="font-size: 12px; color: #475569; margin-bottom: 4px; line-height: 1.5;">3. Buka tautan untuk mengeklaim voucher</div>
                  </div>
                  <div class="footer-col">
                    <div class="footer-title" style="font-size: 12px; font-weight: 900; color: #1e293b; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">⌨️ Klaim Manual di Aplikasi:</div>
                    <div style="font-size: 12px; color: #475569; margin-bottom: 4px; line-height: 1.5;">1. Pilih produk belanjaan Anda &amp; buka halaman Checkout</div>
                    <div style="font-size: 12px; color: #475569; margin-bottom: 4px; line-height: 1.5;">2. Klik bagian "Gunakan/Masukkan Voucher"</div>
                    <div style="font-size: 12px; color: #475569; margin-bottom: 4px; line-height: 1.5;">3. Ketikkan kode: <strong style="color: #0f172a;">${promo.code}</strong></div>
                  </div>
                </div>
              </div>
            `;

            container.appendChild(element);
            document.body.appendChild(container);

            const img = element.querySelector('img');
            const runHtml2Pdf = () => {
                const opt = {
                    margin: 0,
                    filename: `Voucher-${promo.code}.pdf`,
                    image: { type: 'jpeg', quality: 0.98 },
                    html2canvas: { scale: 2, useCORS: true },
                    jsPDF: { unit: 'mm', format: 'a4', orientation: 'landscape' }
                };

                // @ts-ignore
                html2pdf().set(opt).from(element).save().then(() => {
                    container.remove();
                    pdfLoading = null;
                }).catch((err: any) => {
                    console.error('html2pdf error:', err);
                    container.remove();
                    pdfLoading = null;
                });
            };

            if (img && img.complete) {
                runHtml2Pdf();
            } else if (img) {
                img.onload = runHtml2Pdf;
                img.onerror = () => {
                    runHtml2Pdf();
                };
            } else {
                runHtml2Pdf();
            }
        };

        if (typeof (window as any).html2pdf === 'undefined') {
            const script = document.createElement('script');
            script.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js';
            script.onload = runDownload;
            script.onerror = () => {
                showToast('Gagal memuat PDF engine. Coba lagi.', 'error');
                pdfLoading = null;
            };
            document.head.appendChild(script);
        } else {
            runDownload();
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
                                                    onclick={() => downloadPdf(promo)}
                                                    disabled={pdfLoading !== null}
                                                    class="p-2 text-slate-400 hover:text-purple-600 rounded-lg transition inline-block disabled:opacity-50"
                                                    title="Unduh PDF Voucher (QR Code)"
                                                >
                                                    {#if pdfLoading === promo.id}
                                                        <div class="w-4 h-4 border-2 border-purple-600 border-t-transparent rounded-full animate-spin"></div>
                                                    {:else}
                                                        <i class="ti ti-qrcode text-lg"></i>
                                                    {/if}
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
                        onclick={() => downloadPdf(qrPromo)}
                        disabled={pdfLoading}
                        class="flex-1 py-3 bg-gradient-to-br from-slate-800 to-slate-900 hover:from-slate-700 hover:to-slate-800 text-white font-bold rounded-xl text-sm shadow-lg shadow-slate-900/20 transition flex items-center justify-center gap-2 disabled:opacity-50"
                    >
                        {#if pdfLoading}
                            <div class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                            Mengunduh...
                        {:else}
                            <i class="ti ti-download text-base"></i>
                            Unduh PDF
                        {/if}
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
