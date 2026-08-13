<script lang="ts">
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { page, router, Link } from '@inertiajs/svelte';
    import { dragScroll } from '@/utils/dragScroll';
    import Pagination from '@/components/ui/Pagination.svelte';

    let {
        payments = { data: [], links: [], total: 0, from: 0, to: 0, current_page: 1, last_page: 1 },
        isSuperAdmin = false,
        filters = { q: '', status: 'all', date_from: '', date_to: '' },
    } = $props();

    const primary = $derived((page.props as any).theme?.primary_color ?? '#0c4cb4');

    // svelte-ignore state_referenced_locally
    let filterSearch = $state((filters as any).q ?? '');
    // svelte-ignore state_referenced_locally
    let filterStatus = $state((filters as any).status ?? 'all');
    // svelte-ignore state_referenced_locally
    let filterDateFrom = $state((filters as any).date_from ?? '');
    // svelte-ignore state_referenced_locally
    let filterDateTo = $state((filters as any).date_to ?? '');

    function applyFilters() {
        router.get(
            '/admin/listing-payments',
            {
                q: filterSearch || undefined,
                status: filterStatus !== 'all' ? filterStatus : undefined,
                date_from: filterDateFrom || undefined,
                date_to: filterDateTo || undefined,
            },
            { preserveScroll: true },
        );
    }

    function resetFilters() {
        filterSearch = '';
        filterStatus = 'all';
        filterDateFrom = '';
        filterDateTo = '';
        router.get('/admin/listing-payments');
    }

    let searchTimeout: ReturnType<typeof setTimeout>;
    function handleSearchInput() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(applyFilters, 350);
    }

    function fmt(val: number): string {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
        }).format(val || 0);
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
    let checkingOrderId = $state<string | null>(null);
    let checkingAll = $state(false);
    let toastMessage = $state<{ text: string; type: 'success' | 'info' | 'error' | 'warning' } | null>(null);

    function showToast(text: string, type: 'success' | 'info' | 'error' | 'warning' = 'info') {
        toastMessage = { text, type };
        setTimeout(() => {
            if (toastMessage?.text === text) toastMessage = null;
        }, 3500);
    }

    async function checkSinglePaymentStatus(item: any) {
        if (checkingOrderId) return;
        checkingOrderId = item.order_id;

        try {
            // Strictly query Midtrans Core API status via backend
            const res = await fetch(`/admin/listing-payment-status/${encodeURIComponent(item.order_id)}`, {
                headers: { Accept: 'application/json' },
            });
            if (res.ok) {
                const data = await res.json();
                if (data.status === 'paid' || data.status === 'settlement') {
                    showToast(`Tagihan ${item.order_id} Berhasil Dikonfirmasi Lunas di Midtrans!`, 'success');
                    router.reload({ preserveScroll: true });
                    return;
                } else if (data.status === 'failed' || data.status === 'expire' || data.status === 'cancel') {
                    showToast(`Tagihan ${item.order_id} berstatus Gagal / Kedaluwarsa.`, 'error');
                    router.reload({ preserveScroll: true });
                    return;
                } else {
                    showToast(`Tagihan ${item.order_id} masih berstatus MENUNGGU PEMBAYARAN di Midtrans.`, 'info');
                    return;
                }
            }

            showToast(`Gagal mengecek status ${item.order_id} ke Midtrans.`, 'error');
        } catch {
            showToast('Gagal terhubung ke server.', 'error');
        } finally {
            checkingOrderId = null;
        }
    }

    let cancellingOrderId = $state<string | null>(null);
    let payQrisModalData = $state<any>(null);
    let qrisPollingStatus = $state<'pending' | 'paid' | 'failed'>('pending');
    let qrisPollingInterval: ReturnType<typeof setInterval> | null = null;
    let activeListeningOrderId: string | null = null;

    function stopQrisPolling() {
        if (qrisPollingInterval !== null) {
            clearInterval(qrisPollingInterval);
            qrisPollingInterval = null;
        }
    }

    function handleListingPaymentConfirmed(eventData: any) {
        const payload = eventData?.data || eventData || {};
        const status = payload.status || eventData?.status;
        const orderId = payload.order_id || eventData?.order_id;

        if (payQrisModalData && (!orderId || orderId === payQrisModalData.order_id)) {
            if (status === 'paid' || status === 'settlement' || status === 'success') {
                qrisPollingStatus = 'paid';
                stopQrisPolling();
                stopReverbListener();
                showToast('Pembayaran QRIS Berhasil Dikonfirmasi!', 'success');
                setTimeout(() => {
                    closePayQrisModal();
                    router.reload({ preserveScroll: true });
                }, 1500);
            }
        }
    }

    function startReverbListener(orderId: string) {
        if (typeof window !== 'undefined' && (window as any).Echo) {
            stopReverbListener();
            activeListeningOrderId = orderId;
            (window as any).Echo.channel(`listing-payment.${orderId}`).listen('.listing.payment.confirmed', handleListingPaymentConfirmed);
        }
    }

    function stopReverbListener() {
        if (typeof window !== 'undefined' && (window as any).Echo) {
            if (activeListeningOrderId) {
                (window as any).Echo.leave(`listing-payment.${activeListeningOrderId}`);
                activeListeningOrderId = null;
            }
        }
    }

    function startQrisPolling(orderId: string) {
        stopQrisPolling();
        qrisPollingStatus = 'pending';
        startReverbListener(orderId);

        qrisPollingInterval = setInterval(async () => {
            try {
                const res = await fetch(`/admin/listing-payment-status/${encodeURIComponent(orderId)}`, {
                    headers: { Accept: 'application/json' },
                });
                if (!res.ok) return;
                const data = await res.json();
                if (data.status === 'paid' || data.status === 'settlement') {
                    qrisPollingStatus = 'paid';
                    stopQrisPolling();
                    stopReverbListener();
                    showToast('Pembayaran QRIS Berhasil Dikonfirmasi!', 'success');
                    setTimeout(() => {
                        closePayQrisModal();
                        router.reload({ preserveScroll: true });
                    }, 1500);
                } else if (data.status === 'failed') {
                    qrisPollingStatus = 'failed';
                    stopQrisPolling();
                    stopReverbListener();
                }
            } catch { /* ignore */ }
        }, 2000);
    }

    function closePayQrisModal() {
        stopQrisPolling();
        stopReverbListener();
        payQrisModalData = null;
        qrisPollingStatus = 'pending';
    }

    function openPayQrisModal(item: any) {
        payQrisModalData = {
            order_id: item.order_id,
            product_id: item.product_id,
            product_name: item.product?.name || 'Produk Listing',
            amount: item.amount,
            days: item.days,
            qr_image: `https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${encodeURIComponent('00020101021226680016ID.CO.QRIS.WWW01189360091400000000000215' + String(item.amount).padStart(12, '0') + '5802ID5910BIZMATE006007JAKARTA6304ABCD')}`,
        };
        startQrisPolling(item.order_id);
    }

    async function cancelPayment(item: any) {
        if (cancellingOrderId) return;
        if (!confirm(`Yakin ingin membatalkan tagihan ${item.order_id}?`)) return;

        cancellingOrderId = item.order_id;
        try {
            const tokenMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = tokenMeta ? tokenMeta.getAttribute('content') : '';
            const res = await fetch(`/admin/listing-payments/${encodeURIComponent(item.order_id)}/cancel`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || '',
                    'Accept': 'application/json',
                },
            });

            if (res.ok) {
                const data = await res.json();
                if (data.success) {
                    showToast(`Tagihan ${item.order_id} berhasil dibatalkan.`, 'info');
                    router.reload({ preserveScroll: true });
                    return;
                }
            }
            showToast('Gagal membatalkan tagihan.', 'error');
        } catch {
            showToast('Terjadi kesalahan koneksi.', 'error');
        } finally {
            cancellingOrderId = null;
        }
    }

    async function refreshAndCheckAllPending() {
        if (checkingAll) return;
        checkingAll = true;

        try {
            const pendingItems = payments?.data?.filter((p: any) => p.status === 'pending') || [];
            if (pendingItems.length > 0) {
                for (const p of pendingItems) {
                    await fetch(`/admin/listing-payment-status/${encodeURIComponent(p.order_id)}`, {
                        headers: { Accept: 'application/json' },
                    });
                }
            }

            router.reload({
                preserveScroll: true,
                onSuccess: () => {
                    showToast('Riwayat & status tagihan berhasil diperbarui!', 'success');
                },
            });
        } catch {
            showToast('Gagal memperbarui data.', 'error');
        } finally {
            checkingAll = false;
        }
    }
</script>

<svelte:head>
    <title>Riwayat Pembayaran Listing — Admin</title>
</svelte:head>

<AdminLayout>
    {#if toastMessage}
        <div
            class="fixed top-5 right-5 z-50 flex items-center gap-2 px-4 py-3 rounded-xl shadow-lg text-xs font-bold transition-all border"
            class:bg-emerald-600={toastMessage.type === 'success'}
            class:bg-rose-600={toastMessage.type === 'error'}
            class:bg-amber-500={toastMessage.type === 'warning'}
            class:bg-blue-600={toastMessage.type === 'info'}
            class:text-white={true}
        >
            <i class="ti ti-info-circle text-base"></i>
            {toastMessage.text}
        </div>
    {/if}

    <main class="w-full max-w-[1600px] mx-auto px-4 sm:px-6 py-6 space-y-5">

        <!-- PAGE HEADER -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-semibold tracking-tight text-slate-900">Riwayat Pembayaran Listing</h1>
                <p class="mt-0.5 text-sm text-slate-500">
                    {#if isSuperAdmin}
                        Semua riwayat transaksi biaya listing produk seller.
                    {:else}
                        Riwayat transaksi biaya listing produk Anda.
                    {/if}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    disabled={checkingAll}
                    onclick={refreshAndCheckAllPending}
                    class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3.5 py-2 text-xs font-bold text-emerald-700 shadow-xs transition-colors hover:bg-emerald-100 cursor-pointer disabled:opacity-50"
                >
                    {#if checkingAll}
                        <i class="ti ti-loader-2 animate-spin text-sm"></i>
                        Mengecek Tagihan...
                    {:else}
                        <i class="ti ti-refresh text-sm"></i>
                        Refresh & Cek Tagihan
                    {/if}
                </button>
                {#if isSuperAdmin}
                    <span class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-semibold bg-purple-50 text-purple-700 border border-purple-200">
                        <i class="ti ti-shield-check text-sm"></i> Admin Mode
                    </span>
                {/if}
                <Link
                    href="/admin/products"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-xs transition-colors hover:bg-slate-50"
                >
                    <i class="ti ti-arrow-left text-sm"></i>
                    Kembali ke Produk
                </Link>
            </div>
        </div>

        <!-- FILTERS BAR -->
        <div class="flex flex-wrap items-end gap-3">
            <div class="relative flex-1 min-w-48">
                <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                <input
                    type="search"
                    placeholder="Cari Order ID, produk, atau nama toko..."
                    bind:value={filterSearch}
                    oninput={handleSearchInput}
                    class="h-9 w-full rounded-lg border border-slate-200 bg-white pl-8 pr-3 text-sm text-slate-700 placeholder-slate-400 focus:border-slate-400 focus:outline-none focus:ring-0 transition-colors"
                />
            </div>
            <div class="relative">
                <select
                    bind:value={filterStatus}
                    onchange={applyFilters}
                    class="h-9 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 focus:border-slate-400 focus:outline-none transition-colors cursor-pointer"
                >
                    <option value="all">Semua Status</option>
                    <option value="paid">Lunas</option>
                    <option value="pending">Menunggu Pembayaran</option>
                    <option value="failed">Gagal / Kedaluwarsa</option>
                </select>
            </div>
            <div class="relative">
                <input
                    type="date"
                    bind:value={filterDateFrom}
                    class="h-9 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 focus:border-slate-400 focus:outline-none transition-colors cursor-pointer"
                />
            </div>
            <div class="relative">
                <input
                    type="date"
                    bind:value={filterDateTo}
                    class="h-9 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 focus:border-slate-400 focus:outline-none transition-colors cursor-pointer"
                />
            </div>
            <button
                onclick={applyFilters}
                class="h-9 rounded-lg px-4 text-sm font-semibold text-white transition-opacity hover:opacity-90 cursor-pointer"
                style="background-color: {primary};"
            >
                Filter
            </button>
            <button
                onclick={resetFilters}
                class="h-9 rounded-lg border border-slate-200 px-4 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50 cursor-pointer"
            >
                Reset
            </button>
        </div>

        <!-- TABLE -->
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <!-- Toolbar -->
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
                <p class="text-sm text-slate-500">
                    {#if (payments?.total ?? 0) > 0}
                        <span class="font-semibold text-slate-800">{payments.total}</span> riwayat pembayaran
                    {:else}
                        Riwayat pembayaran listing
                    {/if}
                </p>
                <span class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[10px] font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                    <i class="ti ti-qrcode text-xs"></i> QRIS Midtrans
                </span>
            </div>

            {#if (payments?.data?.length ?? 0) === 0}
                <div class="flex flex-col items-center justify-center py-16 text-center px-4">
                    <div class="mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-slate-400">
                        <i class="ti ti-receipt-off text-xl"></i>
                    </div>
                    <p class="text-sm font-medium text-slate-600">Belum ada riwayat pembayaran</p>
                    <p class="mt-1 text-xs text-slate-400">Setiap perpanjangan masa aktif listing produk seller akan tercatat di sini.</p>
                </div>
            {:else}
                <div class="overflow-x-auto" use:dragScroll>
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50/50">
                                <th class="px-4 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400 whitespace-nowrap">Waktu Transaksi</th>
                                <th class="px-4 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400 whitespace-nowrap">Order ID</th>
                                {#if isSuperAdmin}
                                    <th class="px-4 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400 whitespace-nowrap">Toko / Seller</th>
                                {/if}
                                <th class="px-4 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Produk</th>
                                <th class="px-4 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400 whitespace-nowrap">Durasi</th>
                                <th class="px-4 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400 whitespace-nowrap">Nominal</th>
                                <th class="px-4 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400 whitespace-nowrap">Metode</th>
                                <th class="px-4 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400 whitespace-nowrap">Status</th>
                                <th class="px-4 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400 whitespace-nowrap">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            {#each payments.data as item}
                                <tr class="group transition-colors hover:bg-slate-50/50">
                                    <td class="px-4 py-3 whitespace-nowrap" data-label="Waktu Transaksi">
                                        <p class="text-xs text-slate-500">{fmtDate(item.paid_at || item.created_at)}</p>
                                    </td>
                                    <td class="px-4 py-3" data-label="Order ID">
                                        <span class="font-mono text-xs font-semibold text-slate-800">{item.order_id}</span>
                                    </td>
                                    {#if isSuperAdmin}
                                        <td class="px-4 py-3" data-label="Toko / Seller">
                                            <p class="text-sm font-medium text-slate-800 whitespace-nowrap">{item.user?.store_name || item.user?.name || '—'}</p>
                                            {#if item.user?.email}
                                                <p class="text-[10px] text-slate-400 font-mono">{item.user.email}</p>
                                            {/if}
                                        </td>
                                    {/if}
                                    <td class="px-4 py-3" data-label="Produk">
                                        <p class="text-xs font-medium text-slate-800 line-clamp-2 max-w-[200px]">{item.product?.name || 'Produk'}</p>
                                        {#if item.product?.sku}
                                            <p class="text-[10px] text-slate-400 font-mono mt-0.5">SKU: {item.product.sku}</p>
                                        {/if}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap" data-label="Durasi">
                                        <span class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[11px] font-semibold bg-blue-50 text-blue-700 border border-blue-100">
                                            <i class="ti ti-clock text-[10px]"></i> +{item.days} Hari
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap" data-label="Nominal">
                                        {#if item.promo_name || (item.original_amount && Number(item.original_amount) > Number(item.amount))}
                                            <div class="flex flex-col">
                                                <div class="flex items-center gap-1.5">
                                                    {#if item.original_amount && Number(item.original_amount) > Number(item.amount)}
                                                        <span class="text-xs text-slate-400 line-through font-medium">{fmt(item.original_amount)}</span>
                                                    {/if}
                                                    <span class="text-sm font-bold text-emerald-600">{fmt(item.amount)}</span>
                                                </div>
                                                {#if item.promo_name}
                                                    <span class="inline-flex items-center gap-1 text-[10px] font-extrabold text-amber-700 bg-amber-50 border border-amber-200 px-1.5 py-0.5 rounded-md mt-0.5 w-fit">
                                                        <i class="ti ti-discount-2 text-[11px]"></i>
                                                        {item.promo_name}
                                                    </span>
                                                {/if}
                                            </div>
                                        {:else}
                                            <p class="text-sm font-semibold text-slate-800">{fmt(item.amount)}</p>
                                        {/if}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap" data-label="Metode">
                                        <span class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[10px] font-semibold bg-rose-50 text-rose-700 border border-rose-100">
                                            <i class="ti ti-qrcode text-[10px]"></i> QRIS Midtrans
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap" data-label="Status">
                                        {#if item.status === 'paid' || item.status === 'settlement'}
                                            <span class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-100">
                                                <i class="ti ti-circle-check-filled text-xs"></i> Lunas
                                            </span>
                                        {:else if item.status === 'pending'}
                                            <span class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[11px] font-semibold bg-amber-50 text-amber-700 border border-amber-100">
                                                <i class="ti ti-clock-hour-4 text-xs"></i> Menunggu
                                            </span>
                                        {:else}
                                            <span class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[11px] font-semibold bg-rose-50 text-rose-700 border border-rose-100">
                                                <i class="ti ti-circle-x-filled text-xs"></i> Gagal
                                            </span>
                                        {/if}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap" data-label="Aksi">
                                        {#if item.status === 'pending'}
                                            <div class="flex items-center gap-1.5">
                                                <button
                                                    type="button"
                                                    onclick={() => openPayQrisModal(item)}
                                                    class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-bold bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white transition cursor-pointer shadow-xs"
                                                    title="Buka QRIS Pembayaran"
                                                >
                                                    <i class="ti ti-qrcode text-xs"></i>
                                                    Bayar
                                                </button>
                                                <button
                                                    type="button"
                                                    disabled={checkingOrderId === item.order_id}
                                                    onclick={() => checkSinglePaymentStatus(item)}
                                                    class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-semibold bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-200 transition cursor-pointer disabled:opacity-50"
                                                    title="Cek / Konfirmasi Status Tagihan"
                                                >
                                                    {#if checkingOrderId === item.order_id}
                                                        <i class="ti ti-loader-2 animate-spin text-xs"></i>
                                                    {:else}
                                                        <i class="ti ti-refresh text-xs"></i>
                                                    {/if}
                                                    Cek
                                                </button>
                                                <button
                                                    type="button"
                                                    disabled={cancellingOrderId === item.order_id}
                                                    onclick={() => cancelPayment(item)}
                                                    class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-semibold bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 transition cursor-pointer disabled:opacity-50"
                                                    title="Batalkan Tagihan Pembayaran"
                                                >
                                                    {#if cancellingOrderId === item.order_id}
                                                        <i class="ti ti-loader-2 animate-spin text-xs"></i>
                                                    {:else}
                                                        <i class="ti ti-x text-xs"></i>
                                                    {/if}
                                                    Batal
                                                </button>
                                            </div>
                                        {:else}
                                            <span class="text-xs text-slate-400 font-medium">—</span>
                                        {/if}
                                    </td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
                <Pagination paginator={payments} itemLabel="riwayat" />
            {/if}
        </div>

        <!-- QRIS Payment Modal for Listing Payment Page -->
        {#if payQrisModalData}
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs transition-opacity">
                <div class="bg-white rounded-3xl max-w-md w-full p-6 text-center shadow-2xl space-y-4 border border-slate-100 relative animate-scale-up">
                    <button
                        type="button"
                        onclick={closePayQrisModal}
                        class="absolute top-4 right-4 w-8 h-8 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 flex items-center justify-center cursor-pointer transition"
                    >
                        <i class="ti ti-x text-base"></i>
                    </button>

                    <div class="space-y-1 pt-2">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-blue-50 text-blue-700 border border-blue-100">
                            <i class="ti ti-qrcode"></i> Pembayaran QRIS Midtrans
                        </span>
                        <h3 class="text-lg font-bold text-slate-900 line-clamp-1">{payQrisModalData.product_name}</h3>
                        <p class="text-xs text-slate-500 font-mono">Order ID: {payQrisModalData.order_id}</p>
                    </div>

                    <div class="bg-white p-4 rounded-2xl border border-slate-200 inline-block shadow-sm">
                        <img src={payQrisModalData.qr_image} alt="QRIS Code" class="w-52 h-52 object-contain mx-auto rounded-lg" />
                    </div>

                    <div class="bg-blue-50/70 p-3.5 rounded-2xl border border-blue-100 space-y-1 text-center">
                        <span class="text-[11px] text-slate-500 font-medium">Total Nominal Tagihan:</span>
                        <p class="text-xl font-black text-blue-700">{fmt(payQrisModalData.amount)}</p>
                        <p class="text-[11px] font-bold text-slate-600">Durasi Perpanjangan: +{payQrisModalData.days} Hari</p>
                    </div>

                    <!-- Footer & Actions -->
                    <div class="pt-2 flex flex-col gap-2">
                        {#if qrisPollingStatus === 'paid'}
                            <div class="flex flex-col items-center gap-2 py-5 px-4 bg-emerald-50 rounded-2xl border border-emerald-200 text-center">
                                <i class="ti ti-circle-check-filled text-4xl text-emerald-600 animate-bounce"></i>
                                <h4 class="font-extrabold text-sm text-emerald-900">Terima Kasih!</h4>
                                <p class="text-xs text-emerald-700 font-medium">Pembayaran Berhasil Dikonfirmasi Lunas!</p>
                            </div>
                        {:else}
                            <div class="flex flex-col items-center gap-1.5 py-2.5 bg-slate-50 rounded-2xl border border-slate-200">
                                <div class="flex items-center gap-2">
                                    <span class="relative flex h-2.5 w-2.5">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                                    </span>
                                    <span class="text-xs font-bold text-slate-700">Menunggu Pembayaran</span>
                                </div>
                            </div>
                            <button
                                type="button"
                                onclick={() => checkSinglePaymentStatus(payQrisModalData)}
                                class="w-full py-2.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white font-bold text-xs flex items-center justify-center gap-2 transition shadow-sm cursor-pointer"
                            >
                                <i class="ti ti-refresh text-sm"></i> Cek Status / Konfirmasi Pembayaran
                            </button>
                            <button
                                type="button"
                                onclick={closePayQrisModal}
                                class="w-full py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-500 font-semibold text-xs transition cursor-pointer"
                            >
                                Tutup / Batal
                            </button>
                        {/if}
                    </div>
                </div>
            </div>
        {/if}

    </main>
</AdminLayout>
