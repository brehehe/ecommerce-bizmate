<script lang="ts">
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { page, router, Link, useForm } from '@inertiajs/svelte';
    import Pagination from '@/components/ui/Pagination.svelte';
    import { showToast } from '@/utils/toast';
    import Select from '@/components/ui/Select.svelte';
    import Toggle from '@/components/ui/Toggle.svelte';
    import { fade, slide } from 'svelte/transition';
    import { bulkDelete as courierBulkDelete } from '@/routes/admin/master-data/couriers';

    let {
        couriers = { data: [], links: [], total: 0 },
        restoCouriers = [] as any[],
        restos = [] as any[],
        filters = {} as any,
        settings = {} as any,
        isSeller = false,
        currentRestoId = null,
    } = $props();

    // ── Tab Management ────────────────────────────────────────────────────────
    let activeTab = $state<'armada' | 'ekspedisi'>('armada');

    // ── Resto Couriers Management State ───────────────────────────────────────
    let courierSearchQuery = $state('');
    let selectedRestoFilter = $state('');

    $effect(() => {
        activeTab = filters?.tab === 'ekspedisi' ? 'ekspedisi' : 'armada';
        courierSearchQuery = filters?.courier_search || '';
        selectedRestoFilter = filters?.resto_id || '';
    });
    let isRestoCourierModalOpen = $state(false);
    let isEditingRestoCourier = $state(false);
    let editingCourierId = $state<string | null>(null);
    let showCourierPassword = $state(false);

    let isResetPasswordModalOpen = $state(false);
    let targetCourierForReset = $state<any>(null);
    let showResetPassword = $state(false);

    let isDeleteRestoCourierModalOpen = $state(false);
    let targetCourierForDelete = $state<any>(null);

    const restoCourierForm = useForm({
        name: '',
        email: '',
        phone_number: '',
        password: '',
        resto_id: currentRestoId || '',
        is_active: true,
    });

    const resetPasswordForm = useForm({
        password: '',
        password_confirmation: '',
    });

    function openAddRestoCourierModal() {
        isEditingRestoCourier = false;
        editingCourierId = null;
        showCourierPassword = false;
        restoCourierForm.reset();
        restoCourierForm.clearErrors();
        restoCourierForm.name = '';
        restoCourierForm.email = '';
        restoCourierForm.phone_number = '';
        restoCourierForm.password = '';
        restoCourierForm.resto_id = currentRestoId || '';
        restoCourierForm.is_active = true;
        isRestoCourierModalOpen = true;
    }

    function openEditRestoCourierModal(courier: any) {
        isEditingRestoCourier = true;
        editingCourierId = courier.id;
        showCourierPassword = false;
        restoCourierForm.reset();
        restoCourierForm.clearErrors();
        restoCourierForm.name = courier.name;
        restoCourierForm.email = courier.email;
        restoCourierForm.phone_number = courier.phone_number || '';
        restoCourierForm.password = '';
        restoCourierForm.resto_id = courier.resto_id || currentRestoId || '';
        restoCourierForm.is_active = Boolean(courier.is_active ?? true);
        isRestoCourierModalOpen = true;
    }

    function closeRestoCourierModal() {
        isRestoCourierModalOpen = false;
        restoCourierForm.reset();
        restoCourierForm.clearErrors();
    }

    function saveRestoCourier(e: SubmitEvent) {
        e.preventDefault();
        if (isEditingRestoCourier && editingCourierId) {
            restoCourierForm.put(`/admin/master-data/resto-couriers/${editingCourierId}`, {
                preserveScroll: true,
                onSuccess: () => {
                    showToast('Data kurir resto berhasil diperbarui!', 'success');
                    closeRestoCourierModal();
                },
                onError: (errs) => {
                    const first = Object.values(errs)[0];
                    showToast(first || 'Gagal memperbarui data kurir.', 'error');
                },
            });
        } else {
            restoCourierForm.post('/admin/master-data/resto-couriers', {
                preserveScroll: true,
                onSuccess: () => {
                    showToast('Akun kurir resto berhasil didaftarkan!', 'success');
                    closeRestoCourierModal();
                },
                onError: (errs) => {
                    const first = Object.values(errs)[0];
                    showToast(first || 'Gagal mendaftarkan kurir baru.', 'error');
                },
            });
        }
    }

    function openResetPasswordModal(courier: any) {
        targetCourierForReset = courier;
        showResetPassword = false;
        resetPasswordForm.reset();
        resetPasswordForm.clearErrors();
        isResetPasswordModalOpen = true;
    }

    function closeResetPasswordModal() {
        isResetPasswordModalOpen = false;
        targetCourierForReset = null;
        resetPasswordForm.reset();
        resetPasswordForm.clearErrors();
    }

    function submitResetPassword(e: SubmitEvent) {
        e.preventDefault();
        if (!targetCourierForReset) return;

        resetPasswordForm.post(`/admin/master-data/resto-couriers/${targetCourierForReset.id}/reset-password`, {
            preserveScroll: true,
            onSuccess: () => {
                showToast(`Kata sandi kurir ${targetCourierForReset.name} berhasil diubah!`, 'success');
                closeResetPasswordModal();
            },
            onError: (errs) => {
                const first = Object.values(errs)[0];
                showToast(first || 'Gagal mereset kata sandi kurir.', 'error');
            },
        });
    }

    function toggleActiveRestoCourier(courier: any) {
        router.post(
            `/admin/master-data/resto-couriers/${courier.id}/toggle-active`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    showToast(`Status kurir ${courier.name} berhasil diperbarui.`, 'success');
                },
                onError: (errs: any) => {
                    showToast(errs?.error || 'Gagal mengubah status kurir.', 'error');
                },
            },
        );
    }

    function confirmDeleteRestoCourier(courier: any) {
        targetCourierForDelete = courier;
        isDeleteRestoCourierModalOpen = true;
    }

    function executeDeleteRestoCourier() {
        if (!targetCourierForDelete) return;

        router.delete(`/admin/master-data/resto-couriers/${targetCourierForDelete.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                showToast(`Akun kurir ${targetCourierForDelete.name} berhasil dihapus.`, 'success');
                isDeleteRestoCourierModalOpen = false;
                targetCourierForDelete = null;
            },
            onError: (errs: any) => {
                showToast(errs?.error || 'Gagal menghapus kurir.', 'error');
                isDeleteRestoCourierModalOpen = false;
            },
        });
    }

    function copyToClipboard(text: string, label: string = 'Teks') {
        navigator.clipboard.writeText(text);
        showToast(`${label} disalin ke clipboard!`, 'info');
    }

    function updateCourierFilter() {
        router.get(
            '/admin/master-data/couriers',
            {
                tab: 'armada',
                courier_search: courierSearchQuery,
                resto_id: selectedRestoFilter,
            },
            { preserveState: true, replace: true },
        );
    }

    let courierFilterTimeout: any;
    function handleCourierSearchInput() {
        clearTimeout(courierFilterTimeout);
        courierFilterTimeout = setTimeout(() => {
            updateCourierFilter();
        }, 400);
    }

    // ── 3rd-Party Courier & Store Courier Settings State ─────────────────────
    let searchQuery = $state('');
    let perPage = $state(10);
    let searchTimeout: any;

    $effect(() => {
        searchQuery = filters?.search || '';
        perPage = filters?.perPage || 10;
    });

    let selectedCouriers = $state<string[]>([]);
    let selectAll = $derived(
        selectedCouriers.length === couriers.data.length &&
            couriers.data.length > 0,
    );

    let isModalOpen = $state(false);
    let isEditing = $state(false);
    let editId = $state<string | null>(null);
    let deleteModalOpen = $state(false);
    let deleteBulkModalOpen = $state(false);
    let itemToDelete = $state<any>(null);
    let submittingBulkDelete = $state(false);

    const form = useForm({
        code: '',
        name: '',
        is_active: true,
    });

    const storeCourierForm = useForm({
        store_courier_enabled:
            settings.store_courier_enabled === 'true' ||
            settings.store_courier_enabled === true ||
            settings.store_courier_enabled === '1',
        store_courier_type: settings.store_courier_type || 'flat',
        store_courier_flat_fee: Number(settings.store_courier_flat_fee || 0),
        store_courier_per_km_fee: Number(settings.store_courier_per_km_fee || 0),
        store_courier_max_radius: Number(settings.store_courier_max_radius || 50),
        store_courier_round_up:
            settings.store_courier_round_up === 'true' ||
            settings.store_courier_round_up === true ||
            settings.store_courier_round_up === '1',
        store_courier_tiered_rates: (() => {
            if (Array.isArray(settings.store_courier_tiered_rates)) {
                return settings.store_courier_tiered_rates;
            }
            if (typeof settings.store_courier_tiered_rates === 'string') {
                try {
                    return JSON.parse(settings.store_courier_tiered_rates);
                } catch (e) {}
            }
            return [];
        })(),
    });

    function addCourierTier() {
        storeCourierForm.store_courier_tiered_rates = [
            ...storeCourierForm.store_courier_tiered_rates,
            { max_distance: 5, fee: 10000 },
        ];
    }

    function removeCourierTier(index: number) {
        storeCourierForm.store_courier_tiered_rates =
            storeCourierForm.store_courier_tiered_rates.filter(
                (_: any, i: number) => i !== index,
            );
    }

    function submitStoreCourier(e: Event) {
        e.preventDefault();
        storeCourierForm
            .transform((data: any) => ({
                ...data,
                store_courier_tiered_rates: JSON.stringify(
                    data.store_courier_tiered_rates || [],
                ),
            }))
            .post('/admin/settings', {
                preserveScroll: true,
                onSuccess: () => {
                    showToast('Pengaturan Kurir Toko berhasil disimpan.', 'success');
                },
                onError: () => {
                    showToast('Gagal menyimpan pengaturan Kurir Toko.', 'error');
                },
            });
    }

    function toggleSelectAll() {
        if (selectAll) {
            selectedCouriers = [];
        } else {
            selectedCouriers = couriers.data.map((c: any) => c.id);
        }
    }

    function toggleSelect(id: string) {
        if (selectedCouriers.includes(id)) {
            selectedCouriers = selectedCouriers.filter((cId) => cId !== id);
        } else {
            selectedCouriers = [...selectedCouriers, id];
        }
    }

    function updateQuery() {
        router.get(
            '/admin/master-data/couriers',
            { search: searchQuery, perPage: perPage, tab: 'ekspedisi' },
            { preserveState: true, replace: true },
        );
    }

    function handleSearch() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            updateQuery();
        }, 500);
    }

    function handlePerPageChange() {
        updateQuery();
    }

    function openAddModal() {
        isEditing = false;
        editId = null;
        form.reset();
        form.clearErrors();
        isModalOpen = true;
    }

    function openEditModal(courier: any) {
        isEditing = true;
        editId = courier.id;
        form.reset();
        form.clearErrors();
        form.code = courier.code;
        form.name = courier.name;
        form.is_active = courier.is_active ?? true;
        isModalOpen = true;
    }

    function closeModal() {
        isModalOpen = false;
        form.reset();
    }

    function saveCourier(e: SubmitEvent) {
        e.preventDefault();
        if (isEditing) {
            form.put(`/admin/master-data/couriers/${editId}`, {
                onSuccess: () => {
                    showToast('Kurir berhasil diperbarui', 'success');
                    closeModal();
                },
                onError: (err) => {
                    if (err.error) showToast(err.error, 'error');
                },
            });
        } else {
            form.post('/admin/master-data/couriers', {
                onSuccess: () => {
                    showToast('Kurir berhasil ditambahkan', 'success');
                    closeModal();
                },
                onError: (err) => {
                    if (err.error) showToast(err.error, 'error');
                },
            });
        }
    }

    function confirmDelete(courier: any) {
        itemToDelete = courier;
        deleteModalOpen = true;
    }

    function executeBulkDelete() {
        if (selectedCouriers.length === 0) return;
        submittingBulkDelete = true;
        router.post(
            courierBulkDelete.url(),
            {
                ids: selectedCouriers,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    selectedCouriers = [];
                    deleteBulkModalOpen = false;
                },
                onError: (err) => {
                    const first =
                        Object.values(err)[0] ||
                        'Gagal menghapus kurir terpilih.';
                    showToast(first, 'error');
                },
                onFinish: () => {
                    submittingBulkDelete = false;
                },
            },
        );
    }

    function executeDelete() {
        if (!itemToDelete) return;

        router.delete(`/admin/master-data/couriers/${itemToDelete.id}`, {
            onSuccess: () => {
                showToast('Kurir berhasil dihapus', 'success');
                deleteModalOpen = false;
                itemToDelete = null;
            },
            onError: (err: any) => {
                showToast(err?.error || 'Gagal menghapus kurir', 'error');
                deleteModalOpen = false;
            },
        });
    }

    function toggleStatus(courier: any) {
        router.post(
            `/admin/master-data/couriers/${courier.id}/toggle-active`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    showToast(
                        `Status kurir ${courier.name} berhasil diubah`,
                        'success',
                    );
                },
                onError: (err: any) => {
                    showToast(err?.error || 'Gagal mengubah status', 'error');
                },
            },
        );
    }
</script>

<svelte:head>
    <title>Master Data: Kurir & Armada Resto</title>
</svelte:head>

<AdminLayout>
    <div class="flex-grow flex flex-col min-h-screen">
        <main class="flex-grow p-4 sm:p-8 w-full max-w-full mx-auto space-y-6">
            <!-- Page Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h3 class="font-outfit font-black text-2xl text-slate-800">
                        Master Data Kurir
                    </h3>
                    <p class="text-xs text-slate-400 font-bold uppercase tracking-wider mt-1">
                        Kelola Akun Driver Armada Resto & Pengaturan Kurir Pengiriman
                    </p>
                </div>

                {#if activeTab === 'armada'}
                    <button
                        onclick={openAddRestoCourierModal}
                        class="flex items-center justify-center gap-2 px-5 py-3 bg-orange-600 hover:bg-orange-700 text-white font-bold rounded-2xl text-xs transition duration-200 shadow-lg shadow-orange-600/20 shrink-0 font-outfit uppercase tracking-wider cursor-pointer"
                    >
                        <i class="ti ti-user-plus text-base"></i>
                        <span>Tambah Kurir Resto</span>
                    </button>
                {:else}
                    <button
                        onclick={openAddModal}
                        class="flex items-center justify-center gap-2 px-5 py-3 bg-brand-blueRoyal hover:bg-blue-800 text-white font-bold rounded-2xl text-xs transition duration-200 shadow-lg shadow-brand-blueRoyal/20 shrink-0 font-outfit uppercase tracking-wider cursor-pointer"
                    >
                        <i class="ti ti-plus text-base"></i>
                        <span>Tambah Ekspedisi</span>
                    </button>
                {/if}
            </div>

            <!-- Tab Switcher -->
            <div class="flex items-center gap-2 border-b border-slate-200 pb-1">
                <button
                    type="button"
                    onclick={() => (activeTab = 'armada')}
                    class="pb-2.5 px-4 text-xs font-bold transition flex items-center gap-2 border-b-2 cursor-pointer {activeTab === 'armada'
                        ? 'border-orange-500 text-orange-600'
                        : 'border-transparent text-slate-500 hover:text-slate-800'}"
                >
                    <i class="ti ti-moped text-base"></i>
                    <span>Armada Kurir Resto</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {activeTab === 'armada' ? 'bg-orange-100 text-orange-800' : 'bg-slate-100 text-slate-600'}">
                        {restoCouriers.length} Kurir
                    </span>
                </button>

                {#if !isSeller}
                    <button
                        type="button"
                        onclick={() => (activeTab = 'ekspedisi')}
                        class="pb-2.5 px-4 text-xs font-bold transition flex items-center gap-2 border-b-2 cursor-pointer {activeTab === 'ekspedisi'
                            ? 'border-blue-600 text-blue-600'
                            : 'border-transparent text-slate-500 hover:text-slate-800'}"
                    >
                        <i class="ti ti-truck-delivery text-base"></i>
                        <span>Pengaturan Tarif & Ekspedisi Luar</span>
                    </button>
                {/if}
            </div>

            <!-- ══════════════════════════════════════════════════════════════ -->
            <!-- TAB 1: ARMADA KURIR RESTO                                      -->
            <!-- ══════════════════════════════════════════════════════════════ -->
            {#if activeTab === 'armada'}
                <!-- Quick Info Portal Banner -->
                <div class="p-4 rounded-2xl bg-gradient-to-r from-orange-50/80 to-amber-50/60 border border-orange-200/80 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-xs">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-orange-600 text-white flex items-center justify-center text-lg shrink-0 shadow-sm">
                            <i class="ti ti-moped"></i>
                        </div>
                        <div>
                            <h4 class="font-bold text-slate-900 text-sm">Portal Login Driver / Kurir Resto</h4>
                            <p class="text-slate-600 mt-0.5">
                                Driver dapat login di <strong class="text-orange-700 font-mono">http://localhost:8000/kurir/login</strong> menggunakan <em>Email</em> atau <em>No. WhatsApp</em> dan kata sandi yang Anda atur.
                            </p>
                        </div>
                    </div>
                    <a
                        href="/kurir/login"
                        target="_blank"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white hover:bg-slate-50 text-orange-700 font-bold rounded-xl border border-orange-200 shadow-2xs transition text-xs shrink-0"
                    >
                        <i class="ti ti-external-link"></i>
                        <span>Buka Halaman Login Kurir</span>
                    </a>
                </div>

                <!-- Table Card for Resto Couriers -->
                <div class="bg-white rounded-3xl border border-slate-200/80 shadow-card overflow-hidden">
                    <!-- Filters Bar -->
                    <div class="p-5 border-b border-slate-100 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 bg-slate-50/40">
                        <div class="relative flex-1 max-w-md">
                            <i class="ti ti-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <input
                                type="text"
                                bind:value={courierSearchQuery}
                                oninput={handleCourierSearchInput}
                                placeholder="Cari nama, email, atau no. WhatsApp kurir..."
                                class="w-full pl-9 pr-4 py-2 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-slate-400 bg-white"
                            />
                        </div>

                        {#if !isSeller && restos.length > 0}
                            <div class="shrink-0 w-full sm:w-56">
                                <select
                                    bind:value={selectedRestoFilter}
                                    onchange={updateCourierFilter}
                                    class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs bg-white focus:outline-none focus:border-slate-400 text-slate-700 font-medium"
                                >
                                    <option value="">Semua Resto</option>
                                    {#each restos as r}
                                        <option value={r.user_id}>{r.resto_name}</option>
                                    {/each}
                                </select>
                            </div>
                        {/if}
                    </div>

                    <!-- Couriers List -->
                    {#if restoCouriers.length === 0}
                        <div class="text-center py-16 px-4 space-y-3">
                            <div class="w-16 h-16 rounded-full bg-orange-50 text-orange-500 mx-auto flex items-center justify-center text-3xl">
                                <i class="ti ti-moped"></i>
                            </div>
                            <h4 class="font-bold text-slate-800 text-base">Belum Ada Kurir Resto</h4>
                            <p class="text-xs text-slate-500 max-w-sm mx-auto">
                                Daftarkan kurir resto pertama Anda agar dapat menerima pesanan delivery dan login ke aplikasi kurir.
                            </p>
                            <button
                                onclick={openAddRestoCourierModal}
                                class="inline-flex items-center gap-1.5 px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white font-bold rounded-xl text-xs transition cursor-pointer shadow-xs"
                            >
                                <i class="ti ti-plus"></i>
                                <span>Tambah Kurir Resto Sekarang</span>
                            </button>
                        </div>
                    {:else}
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="border-b border-slate-100 bg-slate-50/60 text-slate-500 font-bold uppercase tracking-wider text-[11px]">
                                        <th class="py-3.5 px-6">Driver / Kurir</th>
                                        <th class="py-3.5 px-4">Kontak Login</th>
                                        <th class="py-3.5 px-4">Resto Asal</th>
                                        <th class="py-3.5 px-4 text-center">Status</th>
                                        <th class="py-3.5 px-6 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    {#each restoCouriers as c}
                                        <tr class="hover:bg-slate-50/50 transition">
                                            <td class="py-4 px-6">
                                                <div class="flex items-center gap-3">
                                                    <div class="w-10 h-10 rounded-full bg-orange-100 text-orange-700 flex items-center justify-center font-bold text-sm shrink-0 border border-orange-200 shadow-2xs">
                                                        <i class="ti ti-moped text-lg"></i>
                                                    </div>
                                                    <div>
                                                        <p class="font-bold text-slate-800 text-sm leading-tight">{c.name}</p>
                                                        <span class="inline-block mt-0.5 px-2 py-0.2 rounded-md bg-slate-100 text-slate-600 text-[10px] font-mono">
                                                            ID: {c.id?.slice(0, 8)}...
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-4 px-4 font-mono text-[11.5px] text-slate-700 space-y-1">
                                                <div class="flex items-center gap-1.5">
                                                    <i class="ti ti-mail text-slate-400"></i>
                                                    <span>{c.email}</span>
                                                    <button
                                                        type="button"
                                                        onclick={() => copyToClipboard(c.email, 'Email')}
                                                        title="Salin Email"
                                                        class="text-slate-400 hover:text-slate-600 cursor-pointer"
                                                    >
                                                        <i class="ti ti-copy text-xs"></i>
                                                    </button>
                                                </div>
                                                {#if c.phone_number}
                                                    <div class="flex items-center gap-1.5">
                                                        <i class="ti ti-phone text-slate-400"></i>
                                                        <a
                                                            href={`https://wa.me/${c.phone_number.replace(/^0/, '62').replace(/\D/g, '')}`}
                                                            target="_blank"
                                                            class="text-emerald-700 hover:text-emerald-800 hover:underline font-semibold"
                                                        >
                                                            {c.phone_number}
                                                        </a>
                                                    </div>
                                                {/if}
                                            </td>
                                            <td class="py-4 px-4">
                                                <div class="flex items-center gap-1.5">
                                                    <i class="ti ti-building-store text-orange-500"></i>
                                                    <span class="font-semibold text-slate-800">
                                                        {c.assigned_resto_profile?.resto_name || c.assigned_resto_owner?.store_name || (c.resto_id ? 'Resto Mitra' : 'Semua Resto')}
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="py-4 px-4 text-center">
                                                <button
                                                    type="button"
                                                    onclick={() => toggleActiveRestoCourier(c)}
                                                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold cursor-pointer transition {c.is_active
                                                        ? 'bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100'
                                                        : 'bg-slate-100 text-slate-500 border border-slate-200 hover:bg-slate-200'}"
                                                >
                                                    <span class="w-1.5 h-1.5 rounded-full {c.is_active ? 'bg-emerald-500' : 'bg-slate-400'}"></span>
                                                    <span>{c.is_active ? 'Aktif' : 'Nonaktif'}</span>
                                                </button>
                                            </td>
                                            <td class="py-4 px-6 text-right">
                                                <div class="inline-flex items-center gap-1.5">
                                                    <button
                                                        type="button"
                                                        onclick={() => openResetPasswordModal(c)}
                                                        title="Ubah / Reset Kata Sandi"
                                                        class="p-2 rounded-xl text-amber-700 bg-amber-50 hover:bg-amber-100 border border-amber-200 transition cursor-pointer"
                                                    >
                                                        <i class="ti ti-key text-sm"></i>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onclick={() => openEditRestoCourierModal(c)}
                                                        title="Ubah Data Kurir"
                                                        class="p-2 rounded-xl text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200 transition cursor-pointer"
                                                    >
                                                        <i class="ti ti-pencil text-sm"></i>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onclick={() => confirmDeleteRestoCourier(c)}
                                                        title="Hapus Akun Kurir"
                                                        class="p-2 rounded-xl text-red-700 bg-red-50 hover:bg-red-100 border border-red-200 transition cursor-pointer"
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

            <!-- ══════════════════════════════════════════════════════════════ -->
            <!-- TAB 2: EKSPEDISI & PENGATURAN KURIR TOKO                        -->
            <!-- ══════════════════════════════════════════════════════════════ -->
            {#if activeTab === 'ekspedisi' && !isSeller}
                <!-- Main Section: Table Card -->
                <div class="bg-white rounded-3xl border border-slate-200/80 shadow-card overflow-hidden">
                    <!-- Header, PerPage & Search -->
                    <div class="p-6 border-b border-slate-100 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 bg-slate-50/20">
                        <div class="shrink-0 w-full sm:w-32">
                            <Select
                                bind:value={perPage}
                                options={[
                                    { id: 10, name: '10 Data' },
                                    { id: 25, name: '25 Data' },
                                    { id: 50, name: '50 Data' },
                                    { id: 100, name: '100 Data' },
                                ]}
                                onchange={handlePerPageChange}
                            />
                        </div>

                        <div class="relative flex-1 max-w-md">
                            <i class="ti ti-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <input
                                type="text"
                                bind:value={searchQuery}
                                oninput={handleSearch}
                                placeholder="Cari nama atau kode ekspedisi..."
                                class="w-full pl-9 pr-4 py-2 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-slate-400 bg-white"
                            />
                        </div>

                        {#if selectedCouriers.length > 0}
                            <button
                                onclick={() => (deleteBulkModalOpen = true)}
                                class="flex items-center gap-2 px-4 py-2 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 font-bold rounded-xl text-xs transition shrink-0 cursor-pointer"
                            >
                                <i class="ti ti-trash"></i>
                                <span>Hapus ({selectedCouriers.length})</span>
                            </button>
                        {/if}
                    </div>

                    <!-- Couriers Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-slate-100 bg-slate-50/60 text-slate-500 font-bold uppercase tracking-wider text-[11px]">
                                    <th class="py-3.5 px-6 w-10">
                                        <input
                                            type="checkbox"
                                            checked={selectAll}
                                            onchange={toggleSelectAll}
                                            class="rounded text-brand-blueRoyal focus:ring-0 cursor-pointer"
                                        />
                                    </th>
                                    <th class="py-3.5 px-6">Nama Ekspedisi</th>
                                    <th class="py-3.5 px-6">Kode</th>
                                    <th class="py-3.5 px-6 text-center">Status</th>
                                    <th class="py-3.5 px-6 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                {#each couriers.data as c}
                                    <tr class="hover:bg-slate-50/50 transition">
                                        <td class="py-4 px-6">
                                            <input
                                                type="checkbox"
                                                checked={selectedCouriers.includes(c.id)}
                                                onchange={() => toggleSelect(c.id)}
                                                class="rounded text-brand-blueRoyal focus:ring-0 cursor-pointer"
                                            />
                                        </td>
                                        <td class="py-4 px-6 font-bold text-slate-800">{c.name}</td>
                                        <td class="py-4 px-6 font-mono text-slate-500 uppercase">{c.code}</td>
                                        <td class="py-4 px-6 text-center">
                                            <button
                                                type="button"
                                                onclick={() => toggleStatus(c)}
                                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold cursor-pointer transition {c.is_active
                                                    ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                                                    : 'bg-slate-100 text-slate-500 border border-slate-200'}"
                                            >
                                                <span>{c.is_active ? 'Aktif' : 'Nonaktif'}</span>
                                            </button>
                                        </td>
                                        <td class="py-4 px-6 text-right">
                                            <div class="inline-flex items-center gap-2">
                                                <button
                                                    onclick={() => openEditModal(c)}
                                                    class="p-2 rounded-xl text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200 transition cursor-pointer"
                                                >
                                                    <i class="ti ti-pencil"></i>
                                                </button>
                                                <button
                                                    onclick={() => confirmDelete(c)}
                                                    class="p-2 rounded-xl text-red-700 bg-red-50 hover:bg-red-100 border border-red-200 transition cursor-pointer"
                                                >
                                                    <i class="ti ti-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>

                    <Pagination paginator={couriers} />
                </div>

                <!-- Store Courier Settings Card -->
                <form onsubmit={submitStoreCourier} class="bg-white rounded-3xl border border-slate-200/80 shadow-card overflow-hidden p-6 sm:p-8 space-y-6">
                    <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                        <div class="p-2.5 bg-blue-50 text-blue-600 rounded-xl">
                            <i class="ti ti-truck-delivery text-lg"></i>
                        </div>
                        <div>
                            <h3 class="font-outfit font-black text-slate-800 text-base leading-none">
                                Pengaturan Tarif Kurir Toko (Custom Delivery)
                            </h3>
                            <p class="text-xs text-slate-400 font-medium mt-1">
                                Atur perhitungan tarif pengiriman mandiri menggunakan armada toko sendiri.
                            </p>
                        </div>
                    </div>

                    <div class="space-y-6">
                        <Toggle
                            bind:checked={storeCourierForm.store_courier_enabled}
                            label="Aktifkan Kurir Toko"
                            icon="ti-truck-delivery"
                        />

                        {#if storeCourierForm.store_courier_enabled}
                            <div class="pl-6 border-l-2 border-slate-150 mt-2 space-y-4" transition:slide>
                                <div class="space-y-1.5 max-w-md">
                                    <label for="store-courier-type" class="text-xs font-bold text-slate-600 uppercase tracking-wider block">
                                        Metode Perhitungan Biaya
                                    </label>
                                    <select
                                        id="store-courier-type"
                                        bind:value={storeCourierForm.store_courier_type}
                                        class="w-full px-4 py-2 border border-slate-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-slate-300 bg-white transition text-sm font-medium"
                                    >
                                        <option value="flat">Tarif Flat (Sama Rata)</option>
                                        <option value="radius">Tarif Berdasarkan Radius (per Km)</option>
                                        <option value="radius_tiered">Tarif Berdasarkan Radius Bertingkat (Tiered)</option>
                                    </select>
                                </div>

                                {#if storeCourierForm.store_courier_type !== 'flat'}
                                    <Toggle
                                        bind:checked={storeCourierForm.store_courier_round_up}
                                        label="Bulatkan Jarak Ke Atas"
                                        description="Bulatkan jarak pengiriman di bawah 1 km atau kelipatan pecahan ke kilometer terdekat."
                                        icon="ti-arrows-sort"
                                    />
                                {/if}

                                {#if storeCourierForm.store_courier_type === 'flat'}
                                    <div class="max-w-xs space-y-1">
                                        <label for="input-store-courier-flat-fee" class="block text-xs font-bold text-slate-600">
                                            Biaya Pengiriman Flat
                                        </label>
                                        <div class="relative">
                                            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs font-bold font-mono">Rp</span>
                                            <input
                                                id="input-store-courier-flat-fee"
                                                type="number"
                                                min="0"
                                                bind:value={storeCourierForm.store_courier_flat_fee}
                                                class="w-full pl-8 pr-3 py-1.5 border border-slate-200 rounded-xl text-xs focus:ring-1 focus:outline-none transition font-medium"
                                                required
                                            />
                                        </div>
                                    </div>
                                {:else if storeCourierForm.store_courier_type === 'radius'}
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 max-w-xl">
                                        <div class="space-y-1">
                                            <label for="input-store-courier-per-km-fee" class="block text-xs font-bold text-slate-600">
                                                Biaya per Kilometer
                                            </label>
                                            <div class="relative">
                                                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs font-bold font-mono">Rp</span>
                                                <input
                                                    id="input-store-courier-per-km-fee"
                                                    type="number"
                                                    min="0"
                                                    bind:value={storeCourierForm.store_courier_per_km_fee}
                                                    class="w-full pl-8 pr-3 py-1.5 border border-slate-200 rounded-xl text-xs focus:ring-1 focus:outline-none transition font-medium"
                                                    required
                                                />
                                            </div>
                                        </div>
                                        <div class="space-y-1">
                                            <label for="input-store-courier-max-radius" class="block text-xs font-bold text-slate-600">
                                                Maksimal Radius Pengantaran
                                            </label>
                                            <div class="relative">
                                                <input
                                                    id="input-store-courier-max-radius"
                                                    type="number"
                                                    min="1"
                                                    bind:value={storeCourierForm.store_courier_max_radius}
                                                    class="w-full pr-8 pl-3 py-1.5 border border-slate-200 rounded-xl text-xs focus:ring-1 focus:outline-none transition font-medium"
                                                    required
                                                />
                                                <span class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs font-bold">km</span>
                                            </div>
                                        </div>
                                    </div>
                                {/if}
                            </div>
                        {/if}
                    </div>

                    <div class="pt-4 border-t border-slate-100 flex justify-end">
                        <button
                            type="submit"
                            disabled={storeCourierForm.processing}
                            class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl text-xs transition duration-200 cursor-pointer shadow-sm disabled:opacity-50"
                        >
                            Simpan Pengaturan Tarif
                        </button>
                    </div>
                </form>
            {/if}
        </main>
    </div>
</AdminLayout>

<!-- ── Modal: Tambah / Edit Kurir Resto ─────────────────────────────── -->
{#if isRestoCourierModalOpen}
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs">
        <div class="bg-white rounded-3xl max-w-md w-full p-6 sm:p-7 shadow-2xl border border-slate-100 space-y-5 animate-in fade-in zoom-in-95 duration-150">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3.5">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-orange-100 text-orange-600 flex items-center justify-center text-lg">
                        <i class="ti {isEditingRestoCourier ? 'ti-pencil' : 'ti-user-plus'}"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 font-outfit">
                            {isEditingRestoCourier ? 'Ubah Data Kurir Resto' : 'Tambah Kurir Resto Baru'}
                        </h3>
                        <p class="text-[11px] text-slate-500">
                            {isEditingRestoCourier ? 'Perbarui informasi driver armada resto' : 'Daftarkan akun driver untuk login di portal kurir'}
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    onclick={closeRestoCourierModal}
                    class="text-slate-400 hover:text-slate-600 cursor-pointer p-1 rounded-lg"
                >
                    <i class="ti ti-x text-base"></i>
                </button>
            </div>

            <form onsubmit={saveRestoCourier} class="space-y-4 text-xs">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Nama Lengkap Kurir *</label>
                    <input
                        type="text"
                        bind:value={restoCourierForm.name}
                        required
                        placeholder="Contoh: Totiyono Nugroho"
                        class="w-full h-9 rounded-xl border border-slate-200 px-3 text-xs text-slate-800 placeholder-slate-400 focus:border-orange-500 focus:outline-none"
                    />
                    {#if restoCourierForm.errors.name}
                        <p class="text-red-500 text-[11px] mt-1">{restoCourierForm.errors.name}</p>
                    {/if}
                </div>

                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Email Login Kurir *</label>
                    <input
                        type="email"
                        bind:value={restoCourierForm.email}
                        required
                        placeholder="Contoh: kurir@foodpick.id"
                        class="w-full h-9 rounded-xl border border-slate-200 px-3 text-xs text-slate-800 placeholder-slate-400 focus:border-orange-500 focus:outline-none font-mono"
                    />
                    {#if restoCourierForm.errors.email}
                        <p class="text-red-500 text-[11px] mt-1">{restoCourierForm.errors.email}</p>
                    {/if}
                </div>

                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Nomor WhatsApp / HP Kurir *</label>
                    <input
                        type="tel"
                        bind:value={restoCourierForm.phone_number}
                        required
                        placeholder="Contoh: 085656629097"
                        class="w-full h-9 rounded-xl border border-slate-200 px-3 text-xs text-slate-800 placeholder-slate-400 focus:border-orange-500 focus:outline-none font-mono"
                    />
                    {#if restoCourierForm.errors.phone_number}
                        <p class="text-red-500 text-[11px] mt-1">{restoCourierForm.errors.phone_number}</p>
                    {/if}
                </div>

                {#if !isSeller && restos.length > 0}
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Resto Penugasan Kurir</label>
                        <select
                            bind:value={restoCourierForm.resto_id}
                            class="w-full h-9 rounded-xl border border-slate-200 px-3 text-xs text-slate-800 bg-white focus:border-orange-500 focus:outline-none font-medium"
                        >
                            <option value="">Semua Resto / Resto Utama</option>
                            {#each restos as r}
                                <option value={r.user_id}>{r.resto_name}</option>
                            {/each}
                        </select>
                    </div>
                {/if}

                {#if !isEditingRestoCourier}
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Kata Sandi Login Kurir *</label>
                        <div class="relative">
                            <input
                                type={showCourierPassword ? 'text' : 'password'}
                                bind:value={restoCourierForm.password}
                                required
                                minlength="6"
                                placeholder="Minimal 6 karakter (contoh: password)"
                                class="w-full h-9 rounded-xl border border-slate-200 pl-3 pr-9 text-xs text-slate-800 placeholder-slate-400 focus:border-orange-500 focus:outline-none font-mono"
                            />
                            <button
                                type="button"
                                onclick={() => (showCourierPassword = !showCourierPassword)}
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 cursor-pointer p-1"
                            >
                                <i class="ti {showCourierPassword ? 'ti-eye-off' : 'ti-eye'} text-sm"></i>
                            </button>
                        </div>
                        {#if restoCourierForm.errors.password}
                            <p class="text-red-500 text-[11px] mt-1">{restoCourierForm.errors.password}</p>
                        {/if}
                    </div>
                {/if}

                <div class="pt-2 flex items-center justify-between border-t border-slate-100">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input
                            type="checkbox"
                            bind:checked={restoCourierForm.is_active}
                            class="rounded text-orange-600 focus:ring-0 cursor-pointer"
                        />
                        <span class="font-semibold text-slate-700">Akun Aktif</span>
                    </label>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            onclick={closeRestoCourierModal}
                            class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 transition cursor-pointer font-medium"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            disabled={restoCourierForm.processing}
                            class="px-5 py-2 rounded-xl bg-orange-600 hover:bg-orange-700 text-white font-bold transition shadow-xs cursor-pointer disabled:opacity-50"
                        >
                            {restoCourierForm.processing ? 'Menyimpan...' : (isEditingRestoCourier ? 'Simpan Perubahan' : 'Daftarkan Kurir')}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
{/if}

<!-- ── Modal: Reset Kata Sandi Kurir ───────────────────────────────── -->
{#if isResetPasswordModalOpen && targetCourierForReset}
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs">
        <div class="bg-white rounded-3xl max-w-sm w-full p-6 shadow-2xl border border-slate-100 space-y-4 animate-in fade-in zoom-in-95 duration-150">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center text-lg">
                        <i class="ti ti-key"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 font-outfit">Reset Kata Sandi Kurir</h3>
                        <p class="text-[11px] text-slate-500 font-medium truncate max-w-[200px]">{targetCourierForReset.name}</p>
                    </div>
                </div>
                <button
                    type="button"
                    onclick={closeResetPasswordModal}
                    class="text-slate-400 hover:text-slate-600 cursor-pointer p-1 rounded-lg"
                >
                    <i class="ti ti-x text-base"></i>
                </button>
            </div>

            <form onsubmit={submitResetPassword} class="space-y-3.5 text-xs">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Kata Sandi Baru *</label>
                    <div class="relative">
                        <input
                            type={showResetPassword ? 'text' : 'password'}
                            bind:value={resetPasswordForm.password}
                            required
                            minlength="6"
                            placeholder="Minimal 6 karakter (cth: password)"
                            class="w-full h-9 rounded-xl border border-slate-200 pl-3 pr-9 text-xs text-slate-800 placeholder-slate-400 focus:border-amber-500 focus:outline-none font-mono"
                        />
                        <button
                            type="button"
                            onclick={() => (showResetPassword = !showResetPassword)}
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 cursor-pointer p-1"
                        >
                            <i class="ti {showResetPassword ? 'ti-eye-off' : 'ti-eye'} text-sm"></i>
                        </button>
                    </div>
                    {#if resetPasswordForm.errors.password}
                        <p class="text-red-500 text-[11px] mt-1">{resetPasswordForm.errors.password}</p>
                    {/if}
                </div>

                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Konfirmasi Kata Sandi Baru *</label>
                    <input
                        type={showResetPassword ? 'text' : 'password'}
                        bind:value={resetPasswordForm.password_confirmation}
                        required
                        minlength="6"
                        placeholder="Ulangi kata sandi baru"
                        class="w-full h-9 rounded-xl border border-slate-200 px-3 text-xs text-slate-800 placeholder-slate-400 focus:border-amber-500 focus:outline-none font-mono"
                    />
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button
                        type="button"
                        onclick={closeResetPasswordModal}
                        class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 transition cursor-pointer font-medium"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        disabled={resetPasswordForm.processing}
                        class="px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-bold transition shadow-xs cursor-pointer disabled:opacity-50"
                    >
                        {resetPasswordForm.processing ? 'Menyimpan...' : 'Ubah Kata Sandi'}
                    </button>
                </div>
            </form>
        </div>
    </div>
{/if}

<!-- ── Modal: Hapus Kurir Resto ────────────────────────────────────── -->
{#if isDeleteRestoCourierModalOpen && targetCourierForDelete}
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs">
        <div class="bg-white rounded-3xl p-6 sm:p-7 max-w-sm w-full relative z-10 shadow-2xl border border-slate-100 text-center space-y-4 animate-in fade-in zoom-in-95 duration-150">
            <div class="w-14 h-14 rounded-full bg-red-50 text-red-500 flex items-center justify-center text-2xl mx-auto">
                <i class="ti ti-trash"></i>
            </div>
            <div>
                <h4 class="font-outfit font-black text-lg text-slate-800">
                    Hapus Akun Kurir?
                </h4>
                <p class="text-xs text-slate-500 mt-1">
                    Akun kurir <strong>{targetCourierForDelete.name}</strong> ({targetCourierForDelete.email}) akan dihapus secara permanen. Kurir tidak akan dapat login lagi.
                </p>
            </div>
            <div class="flex items-center gap-3 pt-2">
                <button
                    onclick={() => (isDeleteRestoCourierModalOpen = false)}
                    class="flex-1 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition cursor-pointer"
                >
                    Batal
                </button>
                <button
                    onclick={executeDeleteRestoCourier}
                    class="flex-1 py-2.5 bg-red-600 hover:bg-red-700 text-white font-bold rounded-xl text-xs shadow-md shadow-red-600/20 transition cursor-pointer"
                >
                    Ya, Hapus
                </button>
            </div>
        </div>
    </div>
{/if}

<!-- ── Modal: Ekspedisi Tambah/Edit (Admin) ─────────────────────────── -->
{#if isModalOpen}
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs">
        <div class="bg-white rounded-3xl p-6 max-w-md w-full shadow-2xl border border-slate-100 space-y-4 animate-in fade-in zoom-in-95 duration-150">
            <h4 class="font-outfit font-black text-base text-slate-800">
                {isEditing ? 'Edit Ekspedisi' : 'Tambah Ekspedisi'}
            </h4>
            <form onsubmit={saveCourier} class="space-y-4 text-xs">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Kode Ekspedisi</label>
                    <input
                        type="text"
                        bind:value={form.code}
                        required
                        placeholder="Contoh: jne, sicepat"
                        class="w-full h-9 rounded-xl border border-slate-200 px-3 text-xs uppercase"
                    />
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Nama Ekspedisi</label>
                    <input
                        type="text"
                        bind:value={form.name}
                        required
                        placeholder="Contoh: JNE Express"
                        class="w-full h-9 rounded-xl border border-slate-200 px-3 text-xs"
                    />
                </div>
                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button
                        type="button"
                        onclick={closeModal}
                        class="px-4 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 transition"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        disabled={form.processing}
                        class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold transition shadow-xs disabled:opacity-50"
                    >
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
{/if}

<!-- Delete Confirmation Modal (Ekspedisi) -->
{#if deleteModalOpen}
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs">
        <div class="bg-white rounded-3xl p-6 max-w-sm w-full text-center space-y-4 animate-in fade-in zoom-in-95 duration-150">
            <div class="w-14 h-14 rounded-full bg-red-50 text-red-500 flex items-center justify-center text-2xl mx-auto">
                <i class="ti ti-trash"></i>
            </div>
            <h4 class="font-outfit font-black text-lg text-slate-800">Hapus Ekspedisi?</h4>
            <p class="text-xs text-slate-500">
                Ekspedisi <strong>{itemToDelete?.name}</strong> akan dihapus dari daftar.
            </p>
            <div class="flex items-center gap-3">
                <button
                    onclick={() => (deleteModalOpen = false)}
                    class="flex-1 py-2.5 bg-slate-100 text-slate-700 font-bold rounded-xl text-xs"
                >
                    Batal
                </button>
                <button
                    onclick={executeDelete}
                    class="flex-1 py-2.5 bg-red-600 text-white font-bold rounded-xl text-xs shadow-md"
                >
                    Hapus
                </button>
            </div>
        </div>
    </div>
{/if}

<!-- Bulk Delete Confirmation Modal (Ekspedisi) -->
{#if deleteBulkModalOpen}
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs">
        <div class="bg-white rounded-3xl p-6 max-w-sm w-full text-center space-y-4 animate-in fade-in zoom-in-95 duration-150">
            <div class="w-14 h-14 rounded-full bg-red-50 text-red-500 flex items-center justify-center text-2xl mx-auto">
                <i class="ti ti-trash"></i>
            </div>
            <h4 class="font-outfit font-black text-lg text-slate-800">Hapus {selectedCouriers.length} Ekspedisi?</h4>
            <div class="flex items-center gap-3">
                <button
                    onclick={() => (deleteBulkModalOpen = false)}
                    class="flex-1 py-2.5 bg-slate-100 text-slate-700 font-bold rounded-xl text-xs"
                >
                    Batal
                </button>
                <button
                    onclick={executeBulkDelete}
                    disabled={submittingBulkDelete}
                    class="flex-1 py-2.5 bg-red-600 text-white font-bold rounded-xl text-xs shadow-md disabled:opacity-50"
                >
                    Hapus Semua
                </button>
            </div>
        </div>
    </div>
{/if}
