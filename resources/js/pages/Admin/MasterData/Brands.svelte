<script lang="ts">
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { page, router } from '@inertiajs/svelte';
    import { useForm } from '@inertiajs/svelte';
    import Pagination from '@/components/ui/Pagination.svelte';
    import { showToast } from '@/utils/toast';
    import Select from '@/components/ui/Select.svelte';
    import Input from '@/components/ui/Input.svelte';
    import Toggle from '@/components/ui/Toggle.svelte';
    import { fade } from 'svelte/transition';
    import { bulkDelete as brandBulkDelete } from '@/routes/admin/master-data/brands';

    let { brands = { data: [], links: [], total: 0 }, filters = {} } = $props();

    // svelte-ignore state_referenced_locally
    let searchQuery = $state(filters.search || '');
    // svelte-ignore state_referenced_locally
    let filterStatus = $state(filters.status || '');
    // svelte-ignore state_referenced_locally
    let perPage = $state(filters.perPage || 10);
    let searchTimeout: any;

    // Checkbox state
    let selectedBrands = $state<string[]>([]);
    let selectAll = $derived(
        selectedBrands.length === brands.data.length && brands.data.length > 0,
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
        name: '',
        is_active: true,
    });

    function toggleSelectAll() {
        if (selectAll) {
            selectedBrands = [];
        } else {
            selectedBrands = brands.data.map((b: any) => b.id);
        }
    }

    function toggleSelect(id: string) {
        if (selectedBrands.includes(id)) {
            selectedBrands = selectedBrands.filter((bId) => bId !== id);
        } else {
            selectedBrands = [...selectedBrands, id];
        }
    }

    function updateQuery() {
        router.get(
            '/admin/master-data/brands',
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

    function openEditModal(brand: any) {
        isEditing = true;
        editId = brand.id;
        form.reset();
        form.clearErrors();
        form.name = brand.name;
        form.is_active = brand.is_active ?? true;
        isModalOpen = true;
    }

    function closeModal() {
        isModalOpen = false;
        form.reset();
    }

    function saveBrand(e: SubmitEvent) {
        e.preventDefault();
        if (isEditing) {
            form.put(`/admin/master-data/brands/${editId}`, {
                onSuccess: () => {
                    showToast('Brand berhasil diperbarui', 'success');
                    closeModal();
                },
                onError: (err) => {
                    if (err.error) showToast(err.error, 'error');
                },
            });
        } else {
            form.post('/admin/master-data/brands', {
                onSuccess: () => {
                    showToast('Brand berhasil ditambahkan', 'success');
                    closeModal();
                },
                onError: (err) => {
                    if (err.error) showToast(err.error, 'error');
                },
            });
        }
    }

    function confirmDelete(brand: any) {
        itemToDelete = brand;
        deleteModalOpen = true;
    }

    function executeBulkDelete() {
        if (selectedBrands.length === 0) return;
        submittingBulkDelete = true;
        router.post(
            brandBulkDelete.url(),
            {
                ids: selectedBrands,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    selectedBrands = [];
                    deleteBulkModalOpen = false;
                },
                onError: (err) => {
                    const first = Object.values(err)[0] || 'Gagal menghapus brand terpilih.';
                    showToast(first, 'error');
                },
                onFinish: () => {
                    submittingBulkDelete = false;
                }
            }
        );
    }

    function executeDelete() {
        if (!itemToDelete) return;

        router.delete(`/admin/master-data/brands/${itemToDelete.id}`, {
            onSuccess: () => {
                showToast('Brand berhasil dihapus', 'success');
                deleteModalOpen = false;
                itemToDelete = null;
            },
            onError: (err: any) => {
                showToast(err?.error || 'Gagal menghapus brand', 'error');
                deleteModalOpen = false;
            },
        });
    }

    function toggleStatus(brand: any) {
        router.post(
            `/admin/master-data/brands/${brand.id}/toggle-active`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    showToast(
                        `Status brand ${brand.name} berhasil diubah`,
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
    <title>Master Data: Brand</title>
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
                        Master Brand Produk
                    </h3>
                    <p
                        class="text-xs text-slate-400 font-bold uppercase tracking-wider mt-1"
                    >
                        Atur Daftar Merek (Brand) Katalog Toko
                    </p>
                </div>
                <button
                    onclick={openAddModal}
                    class="flex items-center justify-center gap-2 px-5 py-3 bg-brand-blueRoyal hover:bg-blue-800 text-white font-bold rounded-2xl text-xs transition duration-200 shadow-lg shadow-brand-blueRoyal/20 shrink-0 font-outfit uppercase tracking-wider"
                >
                    <i class="ti ti-plus text-base"></i>
                    <span>Tambah Brand</span>
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
                            placeholder="Cari brand..."
                            icon="ti-search"
                        />
                    </div>
                </div>

                <!-- Bulk Actions Bar -->
                {#if selectedBrands.length > 0}
                    <div
                        transition:fade={{ duration: 150 }}
                        class="px-6 py-4 bg-brand-blueLight/30 border-b border-slate-150 flex items-center justify-between gap-4 flex-wrap"
                    >
                        <div class="flex items-center gap-3">
                            <span class="text-xs font-bold text-slate-555 bg-white border border-slate-200 px-2.5 py-1 rounded-lg shadow-soft font-outfit uppercase tracking-wider flex items-center gap-1.5">
                                <i class="ti ti-checkbox text-brand-blueRoyal text-sm"></i>
                                {selectedBrands.length} Brand Terpilih
                            </span>
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                onclick={() => {
                                    selectedBrands = [];
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

                {#if brands.data.length === 0}
                    <div
                        class="py-12 text-center text-slate-400 font-bold font-outfit"
                    >
                        <i class="ti ti-tags text-4xl block mb-2 text-slate-300"
                        ></i>
                        Belum ada data brand produk.
                    </div>
                {:else}
                    <!-- Table Area -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
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
                                    <th class="py-6 px-6">Nama Brand</th>
                                    <th class="py-6 px-6">Slug</th>
                                    <th class="py-6 px-6">Status</th>
                                    <th class="py-6 px-6 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-slate-100 text-slate-700 text-sm font-medium"
                            >
                                {#each brands.data as brand (brand.id)}
                                    {@const isActive = brand.is_active ?? true}
                                    {@const isSelected =
                                        selectedBrands.includes(brand.id)}

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
                                                    toggleSelect(brand.id)}
                                                class="rounded border-slate-300 text-brand-blueRoyal focus:ring-brand-blueRoyal/20 w-4 h-4 cursor-pointer"
                                            />
                                        </td>
                                        <td class="py-6 px-6">
                                            <div
                                                class="flex items-center gap-3"
                                            >
                                                <div
                                                    class="w-8 h-8 rounded-lg bg-blue-50 text-brand-blueRoyal flex items-center justify-center"
                                                >
                                                    <i
                                                        class="ti ti-tag text-base"
                                                    ></i>
                                                </div>
                                                <h4
                                                    class="text-sm font-bold text-slate-800"
                                                >
                                                    {brand.name}
                                                </h4>
                                            </div>
                                        </td>
                                        <td
                                            class="py-6 px-6 font-mono text-xs text-slate-550"
                                        >
                                            {brand.slug}
                                        </td>
                                        <td class="py-6 px-6">
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
                                        <td class="py-6 px-6 text-center">
                                            <div
                                                class="flex items-center justify-center gap-2"
                                            >
                                                <button
                                                    aria-label="Edit"
                                                    onclick={() =>
                                                        openEditModal(brand)}
                                                    class="w-8 h-8 rounded-lg border border-slate-200 hover:bg-brand-blueLight hover:text-brand-blueRoyal text-slate-500 flex items-center justify-center transition"
                                                    title="Ubah Data"
                                                >
                                                    <i
                                                        class="ti ti-pencil text-sm"
                                                    ></i>
                                                </button>
                                                <button
                                                    onclick={() =>
                                                        toggleStatus(brand)}
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
                                                        confirmDelete(brand)}
                                                    class="w-8 h-8 rounded-lg border border-slate-200 hover:bg-rose-50 hover:text-rose-600 text-slate-500 flex items-center justify-center transition"
                                                    title="Hapus Brand"
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

                <Pagination paginator={brands} />
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
        <div
            class="bg-white rounded-3xl border border-slate-200 shadow-2xl w-full max-w-md relative z-10 transform transition-all duration-300 overflow-hidden animate-in fade-in zoom-in duration-200"
        >
            <!-- Modal Header -->
            <div
                class="px-6 py-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50"
            >
                <h3 class="font-outfit font-black text-lg text-slate-800">
                    {isEditing ? 'Ubah Brand Produk' : 'Tambah Brand Produk'}
                </h3>
                <button
                    aria-label="Tutup"
                    type="button"
                    onclick={closeModal}
                    class="p-1 text-slate-400 hover:text-slate-700 transition"
                >
                    <i class="ti ti-x text-xl"></i>
                </button>
            </div>

            <!-- Modal Body Form -->
            <form onsubmit={saveBrand} class="p-6 space-y-4">
                <Input
                    type="text"
                    bind:value={form.name}
                    label="Nama Brand"
                    required={true}
                    placeholder="Contoh: IKEA, ASUS, Uniqlo"
                    error={form.errors.name}
                />

                <div class="pt-2">
                    <Toggle
                        bind:checked={form.is_active}
                        label="Aktifkan Brand"
                        description="Tentukan apakah brand produk ini aktif dan dapat digunakan pada katalog produk."
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
                        Simpan Brand
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
                Hapus Brand Produk?
            </h4>
            <p class="text-sm text-center text-slate-500 font-medium mb-8">
                Data brand <strong>{itemToDelete?.name}</strong> akan terhapus secara
                permanen dari sistem.
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
                Hapus {selectedBrands.length} Brand Terpilih?
            </h4>
            <p class="text-sm text-center text-slate-555 font-medium mb-8">
                Apakah Anda yakin ingin menghapus <strong>{selectedBrands.length} brand</strong> yang terpilih secara permanen dari sistem? Tindakan ini tidak dapat dibatalkan.
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
