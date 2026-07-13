<script lang="ts">
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { page, router } from '@inertiajs/svelte';
    import { useForm } from '@inertiajs/svelte';
    import Pagination from '@/components/ui/Pagination.svelte';
    import { showToast } from '@/utils/toast';
    import Select from '@/components/ui/Select.svelte';
    import Input from '@/components/ui/Input.svelte';
    import Toggle from '@/components/ui/Toggle.svelte';
    import InputCurrency from '@/components/ui/InputCurrency.svelte';
    import { fade, slide } from 'svelte/transition';
    import { bulkDelete as courierBulkDelete } from '@/routes/admin/master-data/couriers';

    let {
        couriers = { data: [], links: [], total: 0 },
        filters = {},
        settings = {} as any,
    } = $props();

    // svelte-ignore state_referenced_locally
    let searchQuery = $state(filters.search || '');
    // svelte-ignore state_referenced_locally
    let perPage = $state(filters.perPage || 10);
    let searchTimeout: any;

    // Checkbox state
    let selectedCouriers = $state<string[]>([]);
    let selectAll = $derived(
        selectedCouriers.length === couriers.data.length &&
            couriers.data.length > 0,
    );

    // Modal state
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

    // Store Courier settings form
    // svelte-ignore state_referenced_locally
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
            { search: searchQuery, perPage: perPage },
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
    <title>Master Data: Kurir</title>
</svelte:head>

<AdminLayout>
    <div class="flex-grow flex flex-col min-h-screen">
        <main class="flex-grow p-4 sm:p-8 w-full max-w-full mx-auto space-y-6">
            <!-- Page Header -->
            <div
                class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4"
            >
                <div>
                    <h3 class="font-outfit font-black text-2xl text-slate-800">
                        Master Kurir Pengiriman
                    </h3>
                    <p
                        class="text-xs text-slate-400 font-bold uppercase tracking-wider mt-1"
                    >
                        Atur Aktif/Nonaktif Kurir Pengiriman Toko
                    </p>
                </div>
                <button
                    onclick={openAddModal}
                    class="flex items-center justify-center gap-2 px-5 py-3 bg-brand-blueRoyal hover:bg-blue-800 text-white font-bold rounded-2xl text-xs transition duration-200 shadow-lg shadow-brand-blueRoyal/20 shrink-0 font-outfit uppercase tracking-wider"
                >
                    <i class="ti ti-plus text-base"></i>
                    <span>Tambah Kurir</span>
                </button>
            </div>

            <!-- Main Section: Table Card -->
            <div
                class="bg-white rounded-3xl border border-slate-200/80 shadow-card overflow-hidden"
            >
                <!-- Header, PerPage & Search -->
                <div
                    class="p-6 border-b border-slate-100 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 bg-slate-50/20"
                >
                    <!-- PerPage Selector -->
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

                    <!-- Search Bar -->
                    <div class="flex-grow sm:max-w-md w-full sm:ml-auto">
                        <Input
                            type="text"
                            bind:value={searchQuery}
                            oninput={handleSearch}
                            placeholder="Cari kurir atau kode..."
                            icon="ti-search"
                        />
                    </div>
                </div>

                <!-- Bulk Actions Bar -->
                {#if selectedCouriers.length > 0}
                    <div
                        transition:fade={{ duration: 150 }}
                        class="px-6 py-4 bg-brand-blueLight/30 border-b border-slate-150 flex items-center justify-between gap-4 flex-wrap"
                    >
                        <div class="flex items-center gap-3">
                            <span
                                class="text-xs font-bold text-slate-555 bg-white border border-slate-200 px-2.5 py-1 rounded-lg shadow-soft font-outfit uppercase tracking-wider flex items-center gap-1.5"
                            >
                                <i
                                    class="ti ti-checkbox text-brand-blueRoyal text-sm"
                                ></i>
                                {selectedCouriers.length} Kurir Terpilih
                            </span>
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                onclick={() => {
                                    selectedCouriers = [];
                                }}
                                class="px-4 py-2 border border-slate-200 hover:bg-slate-50 text-slate-555 font-bold rounded-xl text-xs transition uppercase tracking-wider font-outfit cursor-pointer"
                            >
                                Batal Pilihan
                            </button>
                            <button
                                onclick={() => (deleteBulkModalOpen = true)}
                                class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white font-bold rounded-xl text-xs transition shadow-lg shadow-red-500/20 uppercase tracking-wider font-outfit flex items-center gap-1.5 cursor-pointer"
                            >
                                <i class="ti ti-trash"></i>
                                Hapus Terpilih
                            </button>
                        </div>
                    </div>
                {/if}

                {#if couriers.data.length === 0}
                    <div
                        class="py-12 text-center text-slate-400 font-bold font-outfit"
                    >
                        <i
                            class="ti ti-truck text-4xl block mb-2 text-slate-300"
                        ></i>
                        Belum ada data kurir pengiriman.
                    </div>
                {:else}
                    <!-- Table Area -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse responsive-table couriers-table">
                            <thead>
                                <tr
                                    class="border-b border-slate-100 bg-slate-50/50 text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit"
                                >
                                    <th class="py-6 px-6 w-12 text-center">
                                        <input
                                            type="checkbox"
                                            checked={selectAll}
                                            onchange={toggleSelectAll}
                                            class="rounded border-slate-300 text-brand-blueRoyal focus:ring-brand-blueRoyal/20 w-4 h-4 cursor-pointer"
                                        />
                                    </th>
                                    <th class="py-6 px-6">Nama Kurir</th>
                                    <th class="py-6 px-6">Kode API</th>
                                    <th class="py-6 px-6">Status</th>
                                    <th class="py-6 px-6 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-slate-100 text-slate-700 text-sm font-medium"
                            >
                                {#each couriers.data as courier (courier.id)}
                                    {@const isActive =
                                        courier.is_active ?? true}
                                    {@const isSelected =
                                        selectedCouriers.includes(courier.id)}

                                    <tr
                                        class="hover:bg-slate-50/50 transition duration-150 border-b border-slate-100 {isSelected
                                            ? 'bg-brand-blueRoyal/5'
                                            : ''}"
                                    >
                                        <td class="py-6 px-6 text-center">
                                            <input
                                                type="checkbox"
                                                checked={isSelected}
                                                onchange={() =>
                                                    toggleSelect(courier.id)}
                                                class="rounded border-slate-300 text-brand-blueRoyal focus:ring-brand-blueRoyal/20 w-4 h-4 cursor-pointer"
                                            />
                                        </td>
                                        <td class="py-6 px-6" data-label="Nama Kurir">
                                            <div
                                                class="flex items-center gap-3"
                                            >
                                                <div
                                                    class="w-8 h-8 rounded-lg bg-blue-50 text-brand-blueRoyal flex items-center justify-center animate-none"
                                                >
                                                    <i
                                                        class="ti ti-truck text-base"
                                                    ></i>
                                                </div>
                                                <h4
                                                    class="text-sm font-bold text-slate-800 text-left"
                                                >
                                                    {courier.name}
                                                </h4>
                                            </div>
                                        </td>
                                        <td
                                            class="py-6 px-6 font-mono text-xs text-slate-500 uppercase"
                                            data-label="Kode API"
                                        >
                                            {courier.code}
                                        </td>
                                        <td class="py-6 px-6" data-label="Status">
                                            <span
                                                class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider {isActive
                                                    ? 'bg-emerald-50 text-emerald-600 border border-emerald-200/50'
                                                    : 'bg-slate-50 text-slate-500 border border-slate-200/50'}"
                                            >
                                                {isActive
                                                    ? 'Aktif'
                                                    : 'Nonaktif'}
                                            </span>
                                        </td>
                                        <td class="py-6 px-6 text-center" data-label="Aksi">
                                            <div
                                                class="flex items-center justify-center gap-2"
                                            >
                                                <button
                                                    onclick={() =>
                                                        openEditModal(courier)}
                                                    class="w-8 h-8 rounded-lg border border-slate-200 hover:bg-brand-blueLight hover:text-brand-blueRoyal text-slate-500 flex items-center justify-center transition"
                                                    title="Ubah Data"
                                                >
                                                    <i
                                                        class="ti ti-pencil text-sm"
                                                    ></i>
                                                </button>
                                                <button
                                                    onclick={() =>
                                                        toggleStatus(courier)}
                                                    class="w-8 h-8 rounded-lg border border-slate-200 {isActive
                                                        ? 'hover:bg-amber-50 hover:text-amber-600 text-slate-500'
                                                        : 'hover:bg-emerald-50 hover:text-emerald-600 text-slate-400'} flex items-center justify-center transition"
                                                    title="Ubah Status (Aktif/Nonaktif)"
                                                >
                                                    <i
                                                        class="ti {isActive
                                                            ? 'ti-ban'
                                                            : 'ti-check'} text-sm"
                                                    ></i>
                                                </button>
                                                <button
                                                    onclick={() =>
                                                        confirmDelete(courier)}
                                                    class="w-8 h-8 rounded-lg border border-slate-200 hover:bg-rose-50 hover:text-rose-600 text-slate-500 flex items-center justify-center transition"
                                                    title="Hapus Kurir"
                                                >
                                                    <i
                                                        class="ti ti-trash text-sm"
                                                    ></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                {/if}

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
                            Pengaturan Kurir Toko (Custom Delivery)
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
                                    description="Bulatkan jarak pengiriman di bawah 1 km atau kelipatan pecahan ke kilometer terdekat (misal: 0,2 km dibulatkan menjadi 1 km)."
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
                                            Radius Maksimal Pengiriman (Km)
                                        </label>
                                        <input
                                            id="input-store-courier-max-radius"
                                            type="number"
                                            bind:value={storeCourierForm.store_courier_max_radius}
                                            class="w-full px-3 py-1.5 border border-slate-200 rounded-xl text-xs focus:ring-1 focus:outline-none transition font-medium"
                                            placeholder="Contoh: 25"
                                            min="1"
                                            required
                                        />
                                    </div>
                                </div>
                            {:else if storeCourierForm.store_courier_type === 'radius_tiered'}
                                <div class="space-y-4 max-w-xl">
                                    <div class="flex justify-between items-center">
                                        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block font-sans">Aturan Jarak & Biaya (Bertingkat)</span>
                                        <button
                                            type="button"
                                            onclick={addCourierTier}
                                            class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-[11px] rounded-lg transition flex items-center gap-1 cursor-pointer font-sans"
                                        >
                                            <i class="ti ti-plus"></i> Tambah Tingkatan
                                        </button>
                                    </div>

                                    {#if storeCourierForm.store_courier_tiered_rates.length === 0}
                                        <div class="text-center py-6 border border-dashed border-slate-200 rounded-xl bg-white font-sans">
                                            <i class="ti ti-info-circle text-slate-300 text-2xl mb-1.5 block"></i>
                                            <span class="text-[11px] font-bold text-slate-500">Belum Ada Aturan Tingkatan</span>
                                            <p class="text-[9px] text-slate-400 mt-0.5 leading-none">Klik "+ Tambah Tingkatan" untuk membuat aturan baru.</p>
                                        </div>
                                    {:else}
                                        <div class="space-y-3 font-sans">
                                            {#each storeCourierForm.store_courier_tiered_rates as tier, index}
                                                <div class="flex items-center gap-3 bg-white p-3 rounded-xl border border-slate-200/50 shadow-sm" transition:slide>
                                                    <div class="flex-grow grid grid-cols-2 gap-3.5">
                                                        <div class="space-y-1">
                                                            <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider block">Jarak Maksimal (Km)</span>
                                                            <div class="relative">
                                                                <input type="number" min="0.1" step="0.1" bind:value={tier.max_distance} class="w-full px-3 py-1.5 border border-slate-200 rounded-lg text-xs focus:ring-1 focus:outline-none transition font-sans font-medium" placeholder="Contoh: 5" required />
                                                                <span class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[10px] font-bold font-sans">Km</span>
                                                            </div>
                                                        </div>
                                                        <div class="space-y-1">
                                                            <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider block">Biaya Pengiriman</span>
                                                            <div class="relative">
                                                                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs font-bold">Rp</span>
                                                                <input type="number" min="0" bind:value={tier.fee} class="w-full pl-9 pr-3.5 py-1.5 border border-slate-200 rounded-lg text-xs focus:ring-1 focus:outline-none transition font-sans font-medium" placeholder="Contoh: 10000" required />
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <button type="button" onclick={() => removeCourierTier(index)} class="p-2 text-rose-500 hover:bg-rose-50 rounded-xl transition self-end cursor-pointer" title="Hapus Aturan">
                                                        <i class="ti ti-trash text-sm"></i>
                                                    </button>
                                                </div>
                                            {/each}
                                        </div>
                                    {/if}

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
                                        <div class="space-y-1">
                                            <label for="input-store-courier-max-radius-tiered" class="block text-xs font-bold text-slate-600">
                                                Radius Maksimal Pengiriman (Km)
                                            </label>
                                            <input
                                                id="input-store-courier-max-radius-tiered"
                                                type="number"
                                                bind:value={storeCourierForm.store_courier_max_radius}
                                                class="w-full px-3 py-1.5 border border-slate-200 rounded-xl text-xs focus:ring-1 focus:outline-none transition font-medium"
                                                placeholder="Contoh: 25"
                                                min="1"
                                                required
                                            />
                                        </div>
                                    </div>
                                </div>
                            {/if}
                        </div>
                    {/if}
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
                    <button
                        type="submit"
                        disabled={storeCourierForm.processing}
                        class="px-5 py-2 bg-brand-blueRoyal hover:bg-blue-800 text-white font-bold rounded-2xl text-xs transition duration-200 shadow-md flex items-center gap-1.5 cursor-pointer uppercase tracking-wider font-outfit"
                    >
                        {#if storeCourierForm.processing}
                            <span class="w-3.5 h-3.5 border-2 border-white/35 border-t-white rounded-full animate-spin"></span>
                            Menyimpan...
                        {:else}
                            <i class="ti ti-device-floppy text-sm"></i>
                            Simpan Kurir Toko
                        {/if}
                    </button>
                </div>
            </form>
        </main>
    </div>
</AdminLayout>

<!-- Modal -->
{#if isModalOpen}
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div
            class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"
            onclick={closeModal}
            onkeypress={closeModal}
            role="button"
            tabindex="0"
        ></div>
        <div
            class="bg-white rounded-3xl border border-slate-200 shadow-2xl w-full max-w-md relative z-10 transform transition-all duration-300 overflow-hidden animate-in fade-in zoom-in duration-200"
        >
            <!-- Modal Header -->
            <div
                class="px-6 py-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50"
            >
                <h3 class="font-outfit font-black text-lg text-slate-800">
                    {isEditing
                        ? 'Ubah Kurir Pengiriman'
                        : 'Tambah Kurir Pengiriman'}
                </h3>
                <button
                    type="button"
                    aria-label="Close modal"
                    onclick={closeModal}
                    class="p-1 text-slate-400 hover:text-slate-700 transition"
                >
                    <i class="ti ti-x text-xl"></i>
                </button>
            </div>

            <!-- Modal Body Form -->
            <form onsubmit={saveCourier} class="p-6 space-y-4">
                <Input
                    type="text"
                    bind:value={form.name}
                    label="Nama Kurir"
                    required={true}
                    placeholder="Contoh: JNE Express, SiCepat"
                    error={form.errors.name}
                />

                <Input
                    type="text"
                    bind:value={form.code}
                    label="Kode API (RajaOngkir)"
                    required={true}
                    placeholder="Contoh: jne, sicepat, pos"
                    error={form.errors.code}
                    readonly={isEditing}
                />

                <div class="pt-2">
                    <Toggle
                        bind:checked={form.is_active}
                        label="Aktifkan Kurir"
                        description="Tentukan apakah kurir pengiriman ini dapat digunakan oleh pembeli saat checkout."
                        icon="ti-toggle-left"
                    />
                </div>

                <!-- Buttons -->
                <div
                    class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3 mt-4"
                >
                    <button
                        type="button"
                        onclick={closeModal}
                        class="px-5 py-3 border border-slate-200 hover:bg-slate-50 text-slate-500 font-bold rounded-2xl text-xs transition duration-200 uppercase tracking-wider font-outfit"
                        >Batal</button
                    >
                    <button
                        type="submit"
                        disabled={form.processing}
                        class="px-5 py-3 bg-brand-blueRoyal hover:bg-blue-800 text-white font-bold rounded-2xl text-xs transition duration-200 shadow-lg shadow-brand-blueRoyal/20 uppercase tracking-wider font-outfit disabled:opacity-70 flex items-center gap-2"
                    >
                        {#if form.processing}
                            <i class="ti ti-loader animate-spin"></i>
                        {/if}
                        Simpan Kurir
                    </button>
                </div>
            </form>
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
                Hapus Kurir Pengiriman?
            </h4>
            <p class="text-sm text-center text-slate-500 font-medium mb-8">
                Data kurir <strong>{itemToDelete?.name}</strong>
                ({itemToDelete?.code}) akan terhapus secara permanen dari
                sistem.
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

<!-- Bulk Delete Confirmation Modal -->
{#if deleteBulkModalOpen}
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div
            class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity"
            onclick={() => (deleteBulkModalOpen = false)}
            onkeypress={() => (deleteBulkModalOpen = false)}
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
                Hapus {selectedCouriers.length} Kurir Terpilih?
            </h4>
            <p class="text-sm text-center text-slate-555 font-medium mb-8">
                Apakah Anda yakin ingin menghapus <strong
                    >{selectedCouriers.length} kurir pengiriman</strong
                > yang terpilih secara permanen dari sistem? Tindakan ini tidak dapat
                dibatalkan.
            </p>
            <div class="flex items-center gap-3">
                <button
                    onclick={() => (deleteBulkModalOpen = false)}
                    class="flex-1 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-sm transition cursor-pointer"
                >
                    Batal
                </button>
                <button
                    onclick={executeBulkDelete}
                    disabled={submittingBulkDelete}
                    class="flex-1 py-3 bg-red-500 hover:bg-red-600 text-white font-bold rounded-xl text-sm shadow-lg shadow-red-500/30 transition cursor-pointer disabled:opacity-50"
                >
                    {submittingBulkDelete ? 'Memproses...' : 'Ya, Hapus Semua'}
                </button>
            </div>
        </div>
    </div>
{/if}

<style>
    @media (max-width: 640px) {
        .couriers-table td:nth-child(2) {
            display: flex !important;
        }
        .couriers-table td[data-label="Nama Kurir"] {
            flex-direction: column !important;
            align-items: flex-start !important;
            gap: 4px;
        }
        .couriers-table td[data-label="Nama Kurir"] > * {
            text-align: left !important;
            width: 100% !important;
        }
    }
</style>
