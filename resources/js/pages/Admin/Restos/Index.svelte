<script lang="ts">
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { usePage, router, Link } from '@inertiajs/svelte';
    import { showToast } from '@/utils/toast';

    let {
        restos,
        counts = { all: 0, pending: 0, approved: 0, rejected: 0, suspended: 0 },
        filters = { tab: 'all', search: '' },
        storeName = '',
    } = $props();

    const page = usePage();
    const primary = $derived((page.props as any).theme?.primary_color ?? '#0c4cb4');
    const secondary = $derived((page.props as any).theme?.secondary_color ?? '#fa7315');

    let searchInput = $state('');
    let activeTab = $state('all');
    let actionLoadingId = $state<string | null>(null);

    $effect(() => {
        searchInput = filters?.search || '';
        activeTab = filters?.tab || 'all';
    });

    function applySearch() {
        router.get(
            '/admin/restos',
            { tab: activeTab, search: searchInput },
            { preserveState: true, replace: true },
        );
    }

    function switchTab(tab: string) {
        activeTab = tab;
        router.get(
            '/admin/restos',
            { tab, search: searchInput },
            { preserveState: true, replace: true },
        );
    }

    function handleApprove(resto: any) {
        if (!confirm(`Setujui pendaftaran mitra resto "${resto.resto_name}"?`)) return;

        actionLoadingId = resto.id;
        router.post(`/admin/restos/${resto.id}/approve`, {}, {
            preserveScroll: true,
            onFinish: () => { actionLoadingId = null; },
            onSuccess: () => showToast(`Resto "${resto.resto_name}" berhasil disetujui!`, 'success'),
            onError: () => showToast('Gagal menyetujui resto.', 'error'),
        });
    }

    function handleReject(resto: any) {
        if (!confirm(`Tolak pendaftaran mitra resto "${resto.resto_name}"?`)) return;

        actionLoadingId = resto.id;
        router.post(`/admin/restos/${resto.id}/reject`, {}, {
            preserveScroll: true,
            onFinish: () => { actionLoadingId = null; },
            onSuccess: () => showToast(`Pendaftaran resto "${resto.resto_name}" ditolak.`, 'success'),
            onError: () => showToast('Gagal menolak resto.', 'error'),
        });
    }

    function handleSuspend(resto: any) {
        if (!confirm(`Tangguhkan operasional mitra resto "${resto.resto_name}"?`)) return;

        actionLoadingId = resto.id;
        router.post(`/admin/restos/${resto.id}/suspend`, {}, {
            preserveScroll: true,
            onFinish: () => { actionLoadingId = null; },
            onSuccess: () => showToast(`Resto "${resto.resto_name}" ditangguhkan.`, 'success'),
            onError: () => showToast('Gagal menangguhkan resto.', 'error'),
        });
    }

    function handleToggleOpen(resto: any) {
        actionLoadingId = resto.id;
        router.post(`/admin/restos/${resto.id}/toggle-open`, {}, {
            preserveScroll: true,
            onFinish: () => { actionLoadingId = null; },
            onSuccess: () => showToast('Status operasional resto berhasil diubah.', 'success'),
            onError: () => showToast('Gagal mengubah status resto.', 'error'),
        });
    }

    function openWhatsApp(phone: string, restoName: string) {
        const clean = (phone || '').replace(/^0/, '62').replace(/[^0-9]/g, '');
        const message = `Halo pengelola resto ${restoName}, kami dari tim Bizmate F&B ingin mengonfirmasi pendaftaran dapur Anda.`;
        window.open(`https://wa.me/${clean}?text=${encodeURIComponent(message)}`, '_blank');
    }
</script>

<svelte:head>
    <title>Mitra Resto & Verifikasi — {storeName}</title>
</svelte:head>

<AdminLayout>
    <main class="w-full max-w-[1600px] mx-auto px-4 sm:px-6 py-6 space-y-5">
        <!-- ── Top Header ─────────────────────────────────────────── -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex items-center gap-1.5 text-xs text-slate-500 mb-1">
                    <Link href="/admin/dashboard" class="hover:text-slate-800 transition-colors">Dashboard</Link>
                    <span>/</span>
                    <span class="text-slate-800 font-medium">Mitra Resto</span>
                </div>
                <div class="flex items-center gap-2.5">
                    <h1 class="text-xl font-semibold tracking-tight text-slate-900">
                        Mitra Resto & Dapur Kuliner
                    </h1>
                    {#if counts.pending > 0}
                        <span class="text-xs px-2.5 py-0.5 rounded-full font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                            {counts.pending} Perlu Approval
                        </span>
                    {/if}
                </div>
                <p class="mt-0.5 text-sm text-slate-500">
                    Kelola verifikasi pendaftaran mitra resto baru, status izin operasional, dan direktori kuliner
                </p>
            </div>

            <div class="flex items-center gap-2.5">
                <Link
                    href="/register-resto"
                    target="_blank"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 shadow-2xs hover:bg-slate-50 transition-colors cursor-pointer"
                >
                    <i class="ti ti-external-link text-base text-slate-600"></i>
                    <span>Form Registrasi Resto</span>
                </Link>
            </div>
        </div>

        <!-- ── Status Tabs & Search ────────────────────────────────── -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-200 pb-0">
            <div class="flex gap-1.5 overflow-x-auto scrollbar-none">
                <button
                    onclick={() => switchTab('all')}
                    class="flex shrink-0 items-center gap-1.5 border-b-2 px-3 pb-3 pt-1 text-xs font-semibold transition-colors whitespace-nowrap {activeTab === 'all' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700'}"
                    type="button"
                >
                    <span>Semua Resto</span>
                    <span class="rounded-full px-1.5 py-0.2 text-[10px] font-bold {activeTab === 'all' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600'}">
                        {counts.all}
                    </span>
                </button>

                <button
                    onclick={() => switchTab('pending')}
                    class="flex shrink-0 items-center gap-1.5 border-b-2 px-3 pb-3 pt-1 text-xs font-semibold transition-colors whitespace-nowrap {activeTab === 'pending' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700'}"
                    type="button"
                >
                    <span>Menunggu Approval</span>
                    <span class="rounded-full px-1.5 py-0.2 text-[10px] font-bold {activeTab === 'pending' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600'}">
                        {counts.pending}
                    </span>
                </button>

                <button
                    onclick={() => switchTab('approved')}
                    class="flex shrink-0 items-center gap-1.5 border-b-2 px-3 pb-3 pt-1 text-xs font-semibold transition-colors whitespace-nowrap {activeTab === 'approved' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700'}"
                    type="button"
                >
                    <span>Aktif / Disetujui</span>
                    <span class="rounded-full px-1.5 py-0.2 text-[10px] font-bold {activeTab === 'approved' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600'}">
                        {counts.approved}
                    </span>
                </button>

                <button
                    onclick={() => switchTab('rejected')}
                    class="flex shrink-0 items-center gap-1.5 border-b-2 px-3 pb-3 pt-1 text-xs font-semibold transition-colors whitespace-nowrap {activeTab === 'rejected' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700'}"
                    type="button"
                >
                    <span>Ditolak</span>
                    <span class="rounded-full px-1.5 py-0.2 text-[10px] font-bold {activeTab === 'rejected' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600'}">
                        {counts.rejected}
                    </span>
                </button>

                <button
                    onclick={() => switchTab('suspended')}
                    class="flex shrink-0 items-center gap-1.5 border-b-2 px-3 pb-3 pt-1 text-xs font-semibold transition-colors whitespace-nowrap {activeTab === 'suspended' ? 'border-slate-900 text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-700'}"
                    type="button"
                >
                    <span>Ditangguhkan</span>
                    <span class="rounded-full px-1.5 py-0.2 text-[10px] font-bold {activeTab === 'suspended' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600'}">
                        {counts.suspended}
                    </span>
                </button>
            </div>

            <!-- Search Form -->
            <form onsubmit={(e) => { e.preventDefault(); applySearch(); }} class="relative sm:w-72 pb-2 sm:pb-2">
                <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                <input
                    type="search"
                    bind:value={searchInput}
                    placeholder="Cari resto, pemilik, alamat..."
                    class="h-9 w-full rounded-lg border border-slate-200 bg-white pl-8 pr-3 text-sm text-slate-700 placeholder-slate-400 focus:border-slate-400 focus:outline-none focus:ring-0 transition-colors"
                />
                {#if searchInput}
                    <button
                        type="button"
                        onclick={() => { searchInput = ''; applySearch(); }}
                        class="absolute right-2.5 top-2.5 text-slate-400 hover:text-slate-600 text-xs"
                    >
                        <i class="ti ti-x"></i>
                    </button>
                {/if}
            </form>
        </div>

        <!-- ── Resto Cards List ────────────────────────────────────── -->
        {#if !restos?.data || restos.data.length === 0}
            <div class="bg-white p-12 text-center rounded-2xl border border-slate-200 shadow-2xs">
                <div class="w-14 h-14 mx-auto rounded-full bg-slate-100 text-slate-400 flex items-center justify-center text-2xl mb-3">
                    🏪
                </div>
                <h3 class="text-sm font-bold text-slate-800">Tidak Ada Mitra Resto</h3>
                <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                    {searchInput ? 'Tidak ditemukan resto yang sesuai dengan kata kunci pencarian.' : 'Belum ada data pendaftaran resto di tab ini.'}
                </p>
            </div>
        {:else}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                {#each restos.data as resto}
                    <div class="bg-white rounded-2xl border border-slate-200/90 shadow-2xs hover:shadow-xs transition flex flex-col justify-between overflow-hidden">
                        <!-- Resto Header -->
                        <div class="p-4 border-b border-slate-100">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-start gap-3 min-w-0">
                                    <div class="w-12 h-12 rounded-xl bg-slate-50 border border-slate-200/80 overflow-hidden shrink-0 flex items-center justify-center">
                                        {#if resto.logo_path}
                                            <img src={resto.logo_path.startsWith('http') ? resto.logo_path : `/storage/${resto.logo_path}`} alt={resto.resto_name} class="w-full h-full object-cover" />
                                        {:else}
                                            <i class="ti ti-building-store text-xl text-slate-400"></i>
                                        {/if}
                                    </div>
                                    <div class="min-w-0">
                                        <h3 class="font-semibold text-sm sm:text-base text-slate-900 leading-snug line-clamp-2" title={resto.resto_name}>
                                            {resto.resto_name}
                                        </h3>
                                        <p class="text-xs text-slate-500 font-medium mt-0.5 truncate">
                                            {resto.culinary_category || 'Aneka Kuliner'}
                                        </p>
                                    </div>
                                </div>

                                <!-- Status Badge -->
                                <div class="shrink-0">
                                    {#if resto.approval_status === 'approved'}
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200/60">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            Disetujui
                                        </span>
                                    {:else if resto.approval_status === 'pending_approval'}
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200/60">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                            Menunggu Review
                                        </span>
                                    {:else if resto.approval_status === 'rejected'}
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-rose-50 text-rose-700 border border-rose-200/60">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                            Ditolak
                                        </span>
                                    {:else}
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-600 border border-slate-200/60">
                                            <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                            Ditangguhkan
                                        </span>
                                    {/if}
                                </div>
                            </div>
                        </div>

                        <!-- Resto Details Body -->
                        <div class="p-4 space-y-3.5 flex-1 text-xs">
                            <!-- Owner & Contact Card -->
                            <div class="flex items-center justify-between gap-3 rounded-xl bg-slate-50/80 border border-slate-100 p-3">
                                <div class="min-w-0">
                                    <div class="text-[11px] text-slate-400 font-medium">Pemilik / Pengelola</div>
                                    <div class="text-xs font-semibold text-slate-800 truncate">{resto.user?.name || 'Pemilik Resto'}</div>
                                    <div class="text-[11px] text-slate-500 font-mono mt-0.5 truncate">{resto.user?.phone_number || resto.user?.email || '-'}</div>
                                </div>
                                {#if resto.user?.phone_number}
                                    <button
                                        type="button"
                                        onclick={() => openWhatsApp(resto.user.phone_number, resto.resto_name)}
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium text-emerald-700 bg-white hover:bg-emerald-50 border border-emerald-200 transition shadow-2xs cursor-pointer shrink-0"
                                        title="Kirim pesan WhatsApp ke pemilik"
                                    >
                                        <i class="ti ti-brand-whatsapp text-sm text-emerald-600"></i>
                                        <span>WhatsApp</span>
                                    </button>
                                {/if}
                            </div>

                            <!-- Address & Location -->
                            <div class="space-y-1">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-medium text-slate-500 flex items-center gap-1">
                                        <i class="ti ti-map-pin text-slate-400"></i>
                                        Lokasi Dapur Resto
                                    </span>
                                    {#if resto.latitude && resto.longitude}
                                        <a
                                            href="https://www.google.com/maps/search/?api=1&query={resto.latitude},{resto.longitude}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="text-xs font-medium text-blue-600 hover:text-blue-700 hover:underline inline-flex items-center gap-1"
                                        >
                                            <span>Buka di Peta</span>
                                            <i class="ti ti-external-link text-[10px]"></i>
                                        </a>
                                    {/if}
                                </div>
                                <p class="text-xs text-slate-600 leading-relaxed line-clamp-2">
                                    {resto.address || 'Alamat dapur belum diisi'}
                                </p>
                            </div>

                            <!-- Stats & Prep Time -->
                            <div class="grid grid-cols-3 divide-x divide-slate-100 rounded-xl bg-slate-50/70 border border-slate-100 py-2.5 px-1 text-center">
                                <div>
                                    <div class="text-[11px] text-slate-400 font-medium">Menu</div>
                                    <div class="text-sm font-semibold text-slate-800 mt-0.5">{resto.products_count || 0}</div>
                                </div>
                                <div>
                                    <div class="text-[11px] text-slate-400 font-medium">Pesanan</div>
                                    <div class="text-sm font-semibold text-slate-800 mt-0.5">{resto.transactions_count || 0}</div>
                                </div>
                                <div>
                                    <div class="text-[11px] text-slate-400 font-medium">Masak</div>
                                    <div class="text-sm font-semibold text-slate-800 mt-0.5">~{resto.avg_prep_time_minutes || 20}m</div>
                                </div>
                            </div>
                        </div>

                        <!-- Card Footer / Actions -->
                        <div class="p-3 bg-slate-50/80 border-t border-slate-100 flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <!-- Kitchen Status Toggle -->
                                <button
                                    type="button"
                                    disabled={actionLoadingId === resto.id}
                                    onclick={() => handleToggleOpen(resto)}
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium transition border cursor-pointer {resto.is_open_now ? 'bg-emerald-50 text-emerald-700 border-emerald-200/80 hover:bg-emerald-100' : 'bg-slate-100 text-slate-600 border-slate-200 hover:bg-slate-200'}"
                                    title="Klik untuk ubah status operasional resto"
                                >
                                    <span class="w-1.5 h-1.5 rounded-full {resto.is_open_now ? 'bg-emerald-500' : 'bg-slate-400'}"></span>
                                    <span>{resto.is_open_now ? 'Dapur Buka' : 'Dapur Tutup'}</span>
                                </button>

                                {#if resto.slug || resto.user?.store_slug}
                                    <a
                                        href="/{resto.slug || resto.user.store_slug}"
                                        target="_blank"
                                        class="inline-flex items-center gap-1 px-2 py-1.5 rounded-lg text-xs font-medium text-slate-600 hover:text-slate-900 hover:bg-white border border-transparent hover:border-slate-200 transition"
                                        title="Kunjungi etalase resto publik"
                                    >
                                        <span>Lihat Toko</span>
                                        <i class="ti ti-external-link text-xs text-slate-400"></i>
                                    </a>
                                {/if}
                            </div>

                            <div class="flex items-center gap-1.5">
                                {#if resto.approval_status !== 'approved'}
                                    <button
                                        type="button"
                                        disabled={actionLoadingId === resto.id}
                                        onclick={() => handleApprove(resto)}
                                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium text-white bg-emerald-600 hover:bg-emerald-700 shadow-2xs transition cursor-pointer disabled:opacity-50"
                                    >
                                        <i class="ti ti-check text-xs"></i>
                                        <span>Setujui</span>
                                    </button>
                                {/if}

                                {#if resto.approval_status === 'pending_approval'}
                                    <button
                                        type="button"
                                        disabled={actionLoadingId === resto.id}
                                        onclick={() => handleReject(resto)}
                                        class="px-2.5 py-1.5 rounded-lg text-xs font-medium text-rose-700 bg-white hover:bg-rose-50 border border-rose-200 transition cursor-pointer disabled:opacity-50"
                                    >
                                        Tolak
                                    </button>
                                {:else if resto.approval_status === 'approved'}
                                    <button
                                        type="button"
                                        disabled={actionLoadingId === resto.id}
                                        onclick={() => handleSuspend(resto)}
                                        class="px-2.5 py-1.5 rounded-lg text-xs font-medium text-slate-600 bg-white hover:bg-slate-100 border border-slate-200 transition cursor-pointer disabled:opacity-50"
                                    >
                                        Suspend
                                    </button>
                                {/if}
                            </div>
                        </div>
                    </div>
                {/each}
            </div>
        {/if}
    </main>
</AdminLayout>

