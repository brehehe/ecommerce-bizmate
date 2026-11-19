<script lang="ts">
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import OrderItemModifiers from '@/components/Storefront/OrderItemModifiers.svelte';
    import { usePage, router, Link } from '@inertiajs/svelte';
    import { showToast } from '@/utils/toast';

    let {
        transactions,
        couriers = [],
        filters = { tab: 'ready', search: '' },
        counts = { ready: 0, delivering: 0, completed: 0, couriers: 0 },
        storeName = '',
    } = $props();

    const page = usePage();
    const primary = $derived((page.props as any).theme?.primary_color ?? '#0c4cb4');
    const secondary = $derived((page.props as any).theme?.secondary_color ?? '#fa7315');

    let searchInput = $state('');
    let activeTab = $state('ready');
    let isActionLoading = $state<string | null>(null);

    $effect(() => {
        searchInput = filters?.search || '';
        activeTab = filters?.tab || 'ready';
    });

    // Modals & Form State for Courier Management
    let showAddCourierModal = $state(false);
    let showCouriersListModal = $state(false);
    let isSavingCourier = $state(false);
    let courierForm = $state({
        name: '',
        email: '',
        phone_number: '',
        password: '',
    });

    function applySearch() {
        router.get(
            '/admin/courier-dispatch',
            { tab: activeTab, search: searchInput },
            { preserveState: true, replace: true },
        );
    }

    function switchTab(tab: string) {
        activeTab = tab;
        router.get(
            '/admin/courier-dispatch',
            { tab, search: searchInput },
            { preserveState: true, replace: true },
        );
    }

    function updateDeliveryStatus(trxId: string, status: string, notes: string = '') {
        isActionLoading = trxId;
        router.post(
            `/admin/courier-dispatch/${trxId}/status`,
            { status, notes },
            {
                preserveScroll: true,
                onFinish: () => {
                    isActionLoading = null;
                },
                onSuccess: () => {
                    showToast('Status pengantaran berhasil diperbarui!', 'success');
                },
                onError: () => {
                    showToast('Gagal memperbarui status pengantaran.', 'error');
                },
            },
        );
    }

    function assignCourierToTrx(trxId: string, courierUserId: string) {
        if (!courierUserId) return;
        isActionLoading = trxId;
        router.post(
            `/admin/courier-dispatch/${trxId}/assign`,
            { courier_user_id: courierUserId },
            {
                preserveScroll: true,
                onFinish: () => {
                    isActionLoading = null;
                },
                onSuccess: () => {
                    showToast('Kurir resto berhasil ditugaskan!', 'success');
                },
                onError: (err) => {
                    const msg = Object.values(err)[0] || 'Gagal menugaskan kurir';
                    showToast(msg as string, 'error');
                },
            },
        );
    }

    function handleCreateCourier(e: Event) {
        e.preventDefault();
        if (!courierForm.name || !courierForm.email || !courierForm.phone_number || !courierForm.password) {
            showToast('Lengkapi semua kolom pendaftaran kurir.', 'error');
            return;
        }

        isSavingCourier = true;
        router.post('/admin/courier-dispatch/couriers', courierForm, {
            preserveScroll: true,
            onSuccess: () => {
                showToast(`Kurir ${courierForm.name} berhasil didaftarkan!`, 'success');
                showAddCourierModal = false;
                courierForm = { name: '', email: '', phone_number: '', password: '' };
            },
            onError: (err) => {
                const msg = Object.values(err)[0] || 'Gagal mendaftarkan kurir';
                showToast(msg as string, 'error');
            },
            onFinish: () => {
                isSavingCourier = false;
            },
        });
    }

    function isCod(trx: any) {
        const name = (trx.payment_method?.name || '').toLowerCase();
        return name.includes('cod') || name.includes('bayar di tempat');
    }

    function openWhatsAppCustomer(trx: any) {
        const phone = trx.customer_phone || trx.user?.phone_number || '';
        const cleanPhone = phone.replace(/^0/, '62').replace(/[^0-9]/g, '');
        const customerName = trx.customer_name || trx.user?.name || 'Pelanggan';
        const trxNo = trx.transaction_number;

        let message = '';
        if (trx.status === 'dikirim' || trx.status === 'sedang_dikirim') {
            message = `Halo Kak ${customerName}, kurir kami sedang dalam perjalanan mengantar pesanan makanan Anda (${trxNo}). Mohon pastikan handphone aktif ya. Terima kasih! 🙏`;
        } else {
            message = `Halo Kak ${customerName}, pesanan makanan Anda (${trxNo}) sedang disiapkan di resto kami. Kami akan segera mengabari saat kurir berangkat. Terima kasih! 🙏`;
        }

        const url = `https://wa.me/${cleanPhone}?text=${encodeURIComponent(message)}`;
        window.open(url, '_blank');
    }

    function openGoogleMapsRoute(trx: any) {
        const addr = trx.customerAddress?.full_address || trx.shipping_address || trx.user?.store_address || '';
        const lat = trx.customerAddress?.latitude;
        const lng = trx.customerAddress?.longitude;

        let query = '';
        if (lat && lng) {
            query = `${lat},${lng}`;
        } else {
            query = encodeURIComponent(addr);
        }

        window.open(`https://www.google.com/maps/search/?api=1&query=${query}`, '_blank');
    }

    function formatRupiah(amount: number) {
        return 'Rp ' + Number(amount || 0).toLocaleString('id-ID');
    }
</script>

<svelte:head>
    <title>Pengantaran & Kurir Resto — {storeName}</title>
</svelte:head>

<AdminLayout>
    <main class="w-full max-w-[1600px] mx-auto px-4 sm:px-6 py-6 space-y-5">
        <!-- ── Header & Breadcrumbs ────────────────────────────────── -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex items-center gap-1.5 text-xs text-slate-500 mb-1">
                    <Link href="/admin/dashboard" class="hover:text-slate-800 transition-colors">Dashboard</Link>
                    <span>/</span>
                    <span class="text-slate-800 font-medium">Kurir Pengantaran</span>
                </div>
                <div class="flex items-center gap-2.5">
                    <h1 class="text-xl font-semibold tracking-tight text-slate-900">
                        Pengantaran & Kurir Resto
                    </h1>
                    <span class="text-xs font-semibold text-slate-700 bg-slate-100 px-2.5 py-0.5 rounded-full">
                        Food Dispatch
                    </span>
                </div>
                <p class="mt-0.5 text-sm text-slate-500">
                    Kelola pengantaran pesanan makanan oleh armada kurir internal resto ke alamat tujuan pelanggan
                </p>
            </div>

            <div class="flex items-center gap-2 flex-wrap">
                <!-- Manage Couriers List -->
                <button
                    type="button"
                    onclick={() => (showCouriersListModal = true)}
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-2xs hover:bg-slate-50 transition-colors cursor-pointer"
                >
                    <i class="ti ti-users-group text-sm text-slate-600"></i>
                    <span>Armada Kurir ({couriers.length})</span>
                </button>

                <!-- Add Courier Button -->
                <button
                    type="button"
                    onclick={() => (showAddCourierModal = true)}
                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-bold text-white shadow-2xs transition-opacity hover:opacity-90 cursor-pointer"
                    style="background-color: {secondary};"
                >
                    <i class="ti ti-user-plus text-sm"></i>
                    <span>+ Tambah Kurir Resto</span>
                </button>

                <Link
                    href="/admin/kitchen"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-2xs hover:bg-slate-50 transition-colors"
                >
                    <i class="ti ti-tools-kitchen-2 text-sm text-slate-600"></i>
                    <span>KDS Dapur</span>
                </Link>
            </div>
        </div>

        <!-- ── Status Tabs & Search ────────────────────────────────── -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-200 pb-0">
            <div class="flex gap-1.5 overflow-x-auto scrollbar-none">
                <button
                    onclick={() => switchTab('ready')}
                    class="flex shrink-0 items-center gap-1.5 border-b-2 px-3 pb-3 pt-1 text-xs font-semibold transition-colors whitespace-nowrap {activeTab === 'ready' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700'}"
                    type="button"
                >
                    <i class="ti ti-clock text-sm"></i>
                    <span>Siap Antar</span>
                    {#if counts.ready > 0}
                        <span class="rounded-full px-1.5 py-0.2 text-[10px] font-bold {activeTab === 'ready' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600'}">
                            {counts.ready}
                        </span>
                    {/if}
                </button>

                <button
                    onclick={() => switchTab('delivering')}
                    class="flex shrink-0 items-center gap-1.5 border-b-2 px-3 pb-3 pt-1 text-xs font-semibold transition-colors whitespace-nowrap {activeTab === 'delivering' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700'}"
                    type="button"
                >
                    <i class="ti ti-moped text-sm"></i>
                    <span>Sedang Diantar</span>
                    {#if counts.delivering > 0}
                        <span class="rounded-full px-1.5 py-0.2 text-[10px] font-bold {activeTab === 'delivering' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600'}">
                            {counts.delivering}
                        </span>
                    {/if}
                </button>

                <button
                    onclick={() => switchTab('completed')}
                    class="flex shrink-0 items-center gap-1.5 border-b-2 px-3 pb-3 pt-1 text-xs font-semibold transition-colors whitespace-nowrap {activeTab === 'completed' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700'}"
                    type="button"
                >
                    <i class="ti ti-check text-sm"></i>
                    <span>Selesai Terkirim</span>
                    {#if counts.completed > 0}
                        <span class="rounded-full px-1.5 py-0.2 text-[10px] font-bold {activeTab === 'completed' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600'}">
                            {counts.completed}
                        </span>
                    {/if}
                </button>
            </div>

            <!-- Search Form -->
            <form onsubmit={(e) => { e.preventDefault(); applySearch(); }} class="relative sm:w-72 pb-2 sm:pb-2">
                <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                <input
                    type="search"
                    bind:value={searchInput}
                    placeholder="Cari no. TRX, pelanggan, HP..."
                    class="h-9 w-full rounded-lg border border-slate-200 bg-white pl-8 pr-3 text-sm text-slate-700 placeholder-slate-400 focus:border-slate-400 focus:outline-none focus:ring-0 transition-colors"
                />
            </form>
        </div>

        <!-- ── Dispatch Order Cards ────────────────────────────────── -->
        {#if !transactions?.data || transactions.data.length === 0}
            <div class="bg-white p-12 text-center rounded-xl border border-slate-200 shadow-2xs">
                <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 text-slate-400 flex items-center justify-center text-xl mb-3">
                    <i class="ti ti-moped"></i>
                </div>
                <h3 class="text-sm font-semibold text-slate-900">Tidak Ada Pesanan Pengantaran</h3>
                <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                    {searchInput ? 'Tidak ditemukan transaksi yang cocok dengan pencarian.' : 'Saat ini belum ada pesanan yang perlu diantar oleh kurir resto.'}
                </p>
            </div>
        {:else}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                {#each transactions.data as trx}
                    <div class="bg-white rounded-xl border border-slate-200 shadow-2xs hover:border-slate-300 transition-colors flex flex-col justify-between overflow-hidden">
                        <!-- Card Header -->
                        <div class="px-4 py-3 border-b border-slate-100 flex items-start justify-between gap-2">
                            <div>
                                <span class="font-mono text-xs font-semibold text-slate-900">
                                    #{trx.transaction_number}
                                </span>
                                <div class="text-[11px] text-slate-400 mt-0.5">
                                    {new Date(trx.created_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })} WIB
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <button
                                    type="button"
                                    onclick={() => window.open(`/admin/kitchen/${trx.id}/print?format=58mm&type=kitchen`, '_blank')}
                                    class="rounded-md border border-slate-200 bg-white p-1.5 text-slate-500 hover:bg-slate-50 hover:text-slate-800 transition-colors cursor-pointer"
                                    title="Cetak Slip Pengantaran Kurir"
                                >
                                    <i class="ti ti-printer text-xs"></i>
                                </button>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-semibold {trx.status === 'selesai' ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : trx.status === 'dikirim' ? 'bg-blue-50 text-blue-700 border border-blue-100' : 'bg-amber-50 text-amber-700 border border-amber-100'}">
                                    {trx.status === 'selesai' ? 'Terkirim' : trx.status === 'dikirim' ? 'Dalam Perjalanan' : 'Siap Diantar'}
                                </span>
                            </div>
                        </div>

                        <!-- Card Body: Customer & Destination -->
                        <div class="p-4 space-y-3 flex-1 text-xs">
                            <!-- Customer Info -->
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="font-semibold text-slate-900">
                                        {trx.customer_name || trx.user?.name || 'Pelanggan'}
                                    </div>
                                    <div class="text-[11px] text-slate-500 font-mono">
                                        {trx.customer_phone || trx.user?.phone_number || '-'}
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    onclick={() => openWhatsAppCustomer(trx)}
                                    class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-emerald-700 bg-emerald-50 hover:bg-emerald-100 rounded-lg border border-emerald-200 transition-colors cursor-pointer"
                                >
                                    <i class="ti ti-brand-whatsapp text-sm"></i>
                                    <span>Chat WA</span>
                                </button>
                            </div>

                            <!-- Destination Address -->
                            <div class="bg-slate-50 p-2.5 rounded-lg border border-slate-100 space-y-1">
                                <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 flex items-center justify-between">
                                    <span>Alamat Pengantaran</span>
                                    <button
                                        type="button"
                                        onclick={() => openGoogleMapsRoute(trx)}
                                        class="text-blue-600 hover:underline inline-flex items-center gap-1 cursor-pointer font-normal"
                                    >
                                        <i class="ti ti-map-pin"></i>
                                        <span>Peta GPS</span>
                                    </button>
                                </div>
                                <p class="text-xs text-slate-700 font-medium line-clamp-2">
                                    {trx.customerAddress?.full_address || trx.shipping_address || 'Alamat tujuan pelanggan'}
                                </p>
                            </div>

                            <!-- Payment & COD Status Notice -->
                            <div class="flex items-center justify-between text-[11px] p-2 rounded-lg {isCod(trx) ? (trx.payment_status === 'paid' ? 'bg-emerald-50/70 border border-emerald-100 text-emerald-800' : 'bg-amber-50/80 border border-amber-200 text-amber-900') : 'bg-slate-50 border border-slate-100 text-slate-700'}">
                                <div class="flex items-center gap-1.5 font-medium">
                                    <i class="ti {isCod(trx) ? 'ti-cash' : 'ti-credit-card'} text-sm"></i>
                                    <span>{trx.payment_method?.name || (isCod(trx) ? 'Bayar di Tempat (COD)' : 'Non-Tunai')}</span>
                                </div>
                                <div class="font-bold">
                                    {#if isCod(trx)}
                                        {#if trx.payment_status === 'paid'}
                                            <span class="text-emerald-700">✓ Lunas (COD)</span>
                                        {:else}
                                            <span class="text-amber-800">Tagih Tunai: {formatRupiah(trx.grand_total)}</span>
                                        {/if}
                                    {:else}
                                        <span class="text-emerald-700">✓ Lunas Online</span>
                                    {/if}
                                </div>
                            </div>

                            <!-- Courier Driver Assignment -->
                            <div class="bg-slate-50/70 p-2.5 rounded-lg border border-slate-100 space-y-1.5">
                                <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 flex items-center justify-between">
                                    <span>Kurir Resto Bertugas</span>
                                    {#if trx.courier_user}
                                        <span class="text-emerald-600 font-bold text-[10px]">✓ Ditugaskan</span>
                                    {:else}
                                        <span class="text-amber-600 font-bold text-[10px]">Belum Ada Driver</span>
                                    {/if}
                                </div>

                                {#if trx.courier_user}
                                    <div class="flex items-center justify-between bg-white px-2.5 py-1.5 rounded-md border border-slate-200">
                                        <div class="flex items-center gap-2">
                                            <div class="w-6 h-6 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center font-bold text-xs">
                                                <i class="ti ti-moped text-xs"></i>
                                            </div>
                                            <div>
                                                <p class="font-semibold text-slate-800 text-xs leading-none">{trx.courier_user.name}</p>
                                                <p class="text-[10px] text-slate-400 mt-0.5">{trx.courier_user.phone_number || '-'}</p>
                                            </div>
                                        </div>
                                        {#if trx.courier_user.phone_number}
                                            <a
                                                href={`https://wa.me/${trx.courier_user.phone_number.replace(/^0/, '62').replace(/\D/g, '')}?text=${encodeURIComponent(`Halo ${trx.courier_user.name}, ada pesanan #${trx.transaction_number} untuk diantar ke ${trx.customerAddress?.full_address || trx.shipping_address}.`)}`}
                                                target="_blank"
                                                class="text-emerald-600 hover:text-emerald-700 p-1 rounded hover:bg-emerald-50 transition"
                                                title="Chat Driver via WhatsApp"
                                            >
                                                <i class="ti ti-brand-whatsapp text-sm"></i>
                                            </a>
                                        {/if}
                                    </div>
                                {:else if couriers.length > 0}
                                    <div class="flex items-center gap-1.5">
                                        <select
                                            onchange={(e) => assignCourierToTrx(trx.id, (e.target as HTMLSelectElement).value)}
                                            class="flex-1 h-8 rounded-md border border-slate-200 bg-white px-2 text-xs text-slate-700 focus:outline-none"
                                        >
                                            <option value="">-- Pilih Kurir Resto --</option>
                                            {#each couriers as c}
                                                <option value={c.id}>{c.name} ({c.phone_number || 'No HP'})</option>
                                            {/each}
                                        </select>
                                    </div>
                                {:else}
                                    <p class="text-[11px] text-slate-400 italic">
                                        Belum ada kurir resto terdaftar.
                                        <button
                                            type="button"
                                            onclick={() => (showAddCourierModal = true)}
                                            class="text-blue-600 underline font-medium cursor-pointer"
                                        >
                                            Tambah Kurir
                                        </button>
                                    </p>
                                {/if}
                            </div>

                            <!-- Menu Items -->
                            <div>
                                <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-1">
                                    Menu Makanan ({trx.items?.length || 0} item)
                                </div>
                                <div class="space-y-1 max-h-24 overflow-y-auto scrollbar-none">
                                    {#each (trx.items || []) as item}
                                        <div class="flex items-start justify-between text-xs text-slate-700">
                                            <div class="flex-1 pr-2">
                                                <span class="font-semibold">{item.quantity}x</span> {item.product?.name || 'Menu Makanan'}
                                                <OrderItemModifiers {item} compact={true} class="mt-0.5" />
                                            </div>
                                            <span class="font-mono text-xs text-slate-500 shrink-0">
                                                {formatRupiah(item.price * item.quantity)}
                                            </span>
                                        </div>
                                    {/each}
                                </div>
                            </div>
                        </div>

                        <!-- Card Footer / Action Buttons -->
                        <div class="px-4 py-3 bg-slate-50/60 border-t border-slate-100 flex items-center gap-2">
                            {#if trx.status !== 'selesai'}
                                {#if trx.status !== 'dikirim'}
                                    <button
                                        type="button"
                                        disabled={isActionLoading === trx.id}
                                        onclick={() => updateDeliveryStatus(trx.id, 'dikirim', 'Kurir berangkat mengantar makanan')}
                                        class="flex-1 h-9 px-3 rounded-lg text-xs font-semibold text-white shadow-xs transition-opacity hover:opacity-90 flex items-center justify-center gap-1.5 cursor-pointer disabled:opacity-50"
                                        style="background-color: {primary};"
                                    >
                                        <i class="ti ti-moped text-sm"></i>
                                        <span>Kurir Berangkat</span>
                                    </button>
                                {:else}
                                    <button
                                        type="button"
                                        disabled={isActionLoading === trx.id}
                                        onclick={() => updateDeliveryStatus(trx.id, 'selesai', 'Makanan berhasil diserahkan ke pelanggan')}
                                        class="flex-1 h-9 px-3 rounded-lg text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-700 shadow-xs transition-opacity flex items-center justify-center gap-1.5 cursor-pointer disabled:opacity-50"
                                    >
                                        <i class="ti ti-check text-sm"></i>
                                        <span>Konfirmasi Selesai Antar</span>
                                    </button>
                                {/if}
                            {:else}
                                <div class="flex-1 py-1.5 text-center text-xs font-semibold text-emerald-700 bg-emerald-50 rounded-lg border border-emerald-200">
                                    ✓ Pesanan Selesai Diterima
                                </div>
                            {/if}

                            <Link
                                href="/admin/transactions/{trx.id}"
                                class="h-9 w-9 rounded-lg text-slate-600 bg-white hover:bg-slate-100 border border-slate-200 transition-colors flex items-center justify-center shrink-0"
                                title="Lihat Rincian Transaksi"
                            >
                                <i class="ti ti-eye text-sm"></i>
                            </Link>
                        </div>
                    </div>
                {/each}
            </div>
        {/if}
    </main>

    <!-- ── Modal: Tambah Akun Kurir Resto ──────────────────────────── -->
    {#if showAddCourierModal}
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-orange-50 text-orange-600 flex items-center justify-center">
                            <i class="ti ti-user-plus text-base"></i>
                        </div>
                        <h3 class="text-sm font-bold text-slate-900">Tambah Akun Kurir Resto</h3>
                    </div>
                    <button
                        type="button"
                        onclick={() => (showAddCourierModal = false)}
                        class="text-slate-400 hover:text-slate-600 cursor-pointer p-1 rounded"
                    >
                        <i class="ti ti-x text-base"></i>
                    </button>
                </div>

                <form onsubmit={handleCreateCourier} class="space-y-3.5 text-xs">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Nama Lengkap Kurir *</label>
                        <input
                            type="text"
                            bind:value={courierForm.name}
                            required
                            placeholder="Contoh: Budi Santoso"
                            class="w-full h-9 rounded-lg border border-slate-200 px-3 text-xs text-slate-800 placeholder-slate-400 focus:border-slate-400 focus:outline-none"
                        />
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Email Login Kurir *</label>
                        <input
                            type="email"
                            bind:value={courierForm.email}
                            required
                            placeholder="Contoh: kurir.budi@resto.com"
                            class="w-full h-9 rounded-lg border border-slate-200 px-3 text-xs text-slate-800 placeholder-slate-400 focus:border-slate-400 focus:outline-none"
                        />
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Nomor WhatsApp / HP Kurir *</label>
                        <input
                            type="tel"
                            bind:value={courierForm.phone_number}
                            required
                            placeholder="Contoh: 081234567890"
                            class="w-full h-9 rounded-lg border border-slate-200 px-3 text-xs text-slate-800 placeholder-slate-400 focus:border-slate-400 focus:outline-none"
                        />
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Kata Sandi Login *</label>
                        <input
                            type="password"
                            bind:value={courierForm.password}
                            required
                            minlength="6"
                            placeholder="Minimal 6 karakter"
                            class="w-full h-9 rounded-lg border border-slate-200 px-3 text-xs text-slate-800 placeholder-slate-400 focus:border-slate-400 focus:outline-none"
                        />
                    </div>

                    <div class="p-3 bg-blue-50/70 border border-blue-200 rounded-xl text-blue-900 text-[11px] space-y-1">
                        <p class="font-bold flex items-center gap-1">
                            <i class="ti ti-info-circle text-xs"></i>
                            Akses Portal Kurir Toko:
                        </p>
                        <p class="text-blue-800 leading-snug">
                            Kurir yang didaftarkan dapat langsung login di <strong>/kurir/login</strong> untuk menerima tugas pengantaran, membuka rute GPS, dan memperbarui status pesanan.
                        </p>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                        <button
                            type="button"
                            onclick={() => (showAddCourierModal = false)}
                            class="px-4 py-2 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 transition cursor-pointer font-medium"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            disabled={isSavingCourier}
                            class="px-4 py-2 rounded-lg text-white font-bold transition shadow-xs cursor-pointer disabled:opacity-50"
                            style="background-color: {secondary};"
                        >
                            {isSavingCourier ? 'Menyimpan...' : 'Simpan Akun Kurir'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    {/if}

    <!-- ── Modal: Daftar Armada Kurir Resto ────────────────────────── -->
    {#if showCouriersListModal}
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl border border-slate-200 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                            <i class="ti ti-users-group text-base"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">Armada Kurir Resto</h3>
                            <p class="text-[11px] text-slate-500">Total {couriers.length} kurir terdaftar</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onclick={() => (showCouriersListModal = false)}
                        class="text-slate-400 hover:text-slate-600 cursor-pointer p-1 rounded"
                    >
                        <i class="ti ti-x text-base"></i>
                    </button>
                </div>

                <div class="space-y-2 max-h-80 overflow-y-auto scrollbar-none text-xs">
                    {#if couriers.length === 0}
                        <div class="text-center py-8 text-slate-400 space-y-2">
                            <i class="ti ti-moped text-3xl"></i>
                            <p>Belum ada kurir yang didaftarkan untuk resto ini.</p>
                            <button
                                type="button"
                                onclick={() => {
                                    showCouriersListModal = false;
                                    showAddCourierModal = true;
                                }}
                                class="text-blue-600 underline font-semibold cursor-pointer"
                            >
                                + Daftarkan Kurir Baru
                            </button>
                        </div>
                    {:else}
                        {#each couriers as c}
                            <div class="flex items-center justify-between p-3 rounded-xl border border-slate-200 bg-slate-50/60 hover:bg-slate-50 transition">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-full bg-white border border-slate-200 text-slate-700 flex items-center justify-center font-bold text-xs shadow-2xs">
                                        <i class="ti ti-moped text-sm"></i>
                                    </div>
                                    <div>
                                        <p class="font-bold text-slate-900">{c.name}</p>
                                        <p class="text-[11px] text-slate-500 font-mono">{c.email} · {c.phone_number || '-'}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    {#if c.phone_number}
                                        <a
                                            href={`https://wa.me/${c.phone_number.replace(/^0/, '62').replace(/\D/g, '')}`}
                                            target="_blank"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 rounded-lg border border-emerald-200 transition"
                                        >
                                            <i class="ti ti-brand-whatsapp text-xs"></i>
                                            <span>WhatsApp</span>
                                        </a>
                                    {/if}
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-100">
                                        Aktif
                                    </span>
                                </div>
                            </div>
                        {/each}
                    {/if}
                </div>

                <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                    <button
                        type="button"
                        onclick={() => {
                            showCouriersListModal = false;
                            showAddCourierModal = true;
                        }}
                        class="inline-flex items-center gap-1.5 text-xs font-bold text-orange-600 hover:text-orange-700 cursor-pointer"
                    >
                        <i class="ti ti-plus"></i>
                        <span>Tambah Kurir Baru</span>
                    </button>
                    <button
                        type="button"
                        onclick={() => (showCouriersListModal = false)}
                        class="px-4 py-1.5 rounded-lg border border-slate-200 text-xs font-medium text-slate-700 hover:bg-slate-50 transition cursor-pointer"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    {/if}
</AdminLayout>

