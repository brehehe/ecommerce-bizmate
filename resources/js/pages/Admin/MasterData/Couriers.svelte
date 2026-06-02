<script lang="ts">
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { page, router } from '@inertiajs/svelte';
    import { useForm } from '@inertiajs/svelte';
    import Pagination from '@/components/ui/Pagination.svelte';
    import { showToast } from '@/utils/toast';
    import Select from '@/components/ui/Select.svelte';
    import Input from '@/components/ui/Input.svelte';
    import Toggle from '@/components/ui/Toggle.svelte';

    let {
        couriers = { data: [], links: [], total: 0 },
        filters = {},
    } = $props();

    // svelte-ignore state_referenced_locally
    let searchQuery = $state(filters.search || '');
    // svelte-ignore state_referenced_locally
    let perPage = $state(filters.perPage || 10);
    let searchTimeout: any;

    // Checkbox state
    let selectedCouriers = $state<string[]>([]);
    let selectAll = $derived(
        selectedCouriers.length === couriers.data.length && couriers.data.length > 0,
    );

    // Modal state
    let isModalOpen = $state(false);
    let isEditing = $state(false);
    let editId = $state<string | null>(null);
    let deleteModalOpen = $state(false);
    let itemToDelete = $state<any>(null);

    const form = useForm({
        code: '',
        name: '',
        is_active: true,
    });

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
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h3 class="font-outfit font-black text-2xl text-slate-800">
                        Master Kurir Pengiriman
                    </h3>
                    <p class="text-xs text-slate-400 font-bold uppercase tracking-wider mt-1">
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
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-card overflow-hidden">
                <!-- Header, PerPage & Search -->
                <div class="p-6 border-b border-slate-100 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 bg-slate-50/20">
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

                {#if couriers.data.length === 0}
                    <div class="py-12 text-center text-slate-400 font-bold font-outfit">
                        <i class="ti ti-truck text-4xl block mb-2 text-slate-300"></i>
                        Belum ada data kurir pengiriman.
                    </div>
                {:else}
                    <!-- Table Area -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-100 bg-slate-50/50 text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit">
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
                            <tbody class="divide-y divide-slate-100 text-slate-700 text-sm font-medium">
                                {#each couriers.data as courier (courier.id)}
                                    {@const isActive = courier.is_active ?? true}
                                    {@const isSelected = selectedCouriers.includes(courier.id)}

                                    <tr class="hover:bg-slate-50/50 transition duration-150 border-b border-slate-100 {isSelected ? 'bg-brand-blueRoyal/5' : ''}">
                                        <td class="py-6 px-6 text-center">
                                            <input
                                                type="checkbox"
                                                checked={isSelected}
                                                onchange={() => toggleSelect(courier.id)}
                                                class="rounded border-slate-300 text-brand-blueRoyal focus:ring-brand-blueRoyal/20 w-4 h-4 cursor-pointer"
                                            />
                                        </td>
                                        <td class="py-6 px-6">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 rounded-lg bg-blue-50 text-brand-blueRoyal flex items-center justify-center">
                                                    <i class="ti ti-truck text-base"></i>
                                                </div>
                                                <h4 class="text-sm font-bold text-slate-800">
                                                    {courier.name}
                                                </h4>
                                            </div>
                                        </td>
                                        <td class="py-6 px-6 font-mono text-xs text-slate-500 uppercase">
                                            {courier.code}
                                        </td>
                                        <td class="py-6 px-6">
                                            <span class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider {isActive ? 'bg-emerald-50 text-emerald-600 border border-emerald-200/50' : 'bg-slate-50 text-slate-500 border border-slate-200/50'}">
                                                {isActive ? 'Aktif' : 'Nonaktif'}
                                            </span>
                                        </td>
                                        <td class="py-6 px-6 text-center">
                                            <div class="flex items-center justify-center gap-2">
                                                <button
                                                    onclick={() => openEditModal(courier)}
                                                    class="w-8 h-8 rounded-lg border border-slate-200 hover:bg-brand-blueLight hover:text-brand-blueRoyal text-slate-500 flex items-center justify-center transition"
                                                    title="Ubah Data"
                                                >
                                                    <i class="ti ti-pencil text-sm"></i>
                                                </button>
                                                <button
                                                    onclick={() => toggleStatus(courier)}
                                                    class="w-8 h-8 rounded-lg border border-slate-200 {isActive ? 'hover:bg-amber-50 hover:text-amber-600 text-slate-500' : 'hover:bg-emerald-50 hover:text-emerald-600 text-slate-400'} flex items-center justify-center transition"
                                                    title="Ubah Status (Aktif/Nonaktif)"
                                                >
                                                    <i class="ti {isActive ? 'ti-ban' : 'ti-check'} text-sm"></i>
                                                </button>
                                                <button
                                                    onclick={() => confirmDelete(courier)}
                                                    class="w-8 h-8 rounded-lg border border-slate-200 hover:bg-rose-50 hover:text-rose-600 text-slate-500 flex items-center justify-center transition"
                                                    title="Hapus Kurir"
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

                <Pagination paginator={couriers} />
            </div>
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
        <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl w-full max-w-md relative z-10 transform transition-all duration-300 overflow-hidden animate-in fade-in zoom-in duration-200">
            <!-- Modal Header -->
            <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                <h3 class="font-outfit font-black text-lg text-slate-800">
                    {isEditing ? 'Ubah Kurir Pengiriman' : 'Tambah Kurir Pengiriman'}
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
                <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3 mt-4">
                    <button
                        type="button"
                        onclick={closeModal}
                        class="px-5 py-3 border border-slate-200 hover:bg-slate-50 text-slate-500 font-bold rounded-2xl text-xs transition duration-200 uppercase tracking-wider font-outfit"
                        >Batal</button>
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

        <div class="bg-white rounded-3xl p-6 sm:p-8 max-w-md w-full relative z-10 shadow-2xl animate-in fade-in zoom-in duration-200">
            <div class="w-16 h-16 rounded-full bg-red-50 text-red-500 flex items-center justify-center text-3xl mb-5 mx-auto">
                <i class="ti ti-alert-triangle"></i>
            </div>
            <h4 class="font-outfit font-black text-xl text-center text-slate-800 mb-2">
                Hapus Kurir Pengiriman?
            </h4>
            <p class="text-sm text-center text-slate-500 font-medium mb-8">
                Data kurir <strong>{itemToDelete?.name}</strong> ({itemToDelete?.code}) akan terhapus secara permanen dari sistem.
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
