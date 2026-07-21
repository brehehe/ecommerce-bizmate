<script lang="ts">
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { page, router } from '@inertiajs/svelte';
    import Sortable from 'sortablejs';
    import { useForm } from '@inertiajs/svelte';
    import Pagination from '@/components/ui/Pagination.svelte';
    import { showToast } from '@/utils/toast';
    import Select from '@/components/ui/Select.svelte';
    import Input from '@/components/ui/Input.svelte';
    import Toggle from '@/components/ui/Toggle.svelte';
    import { fade } from 'svelte/transition';
    import { bulkDelete as brandBulkDelete, reorder as brandReorder } from '@/routes/admin/master-data/brands';

    let { brands = { data: [], links: [], total: 0 }, filters = {} } = $props();

    const primary = $derived(
        (page.props as any).theme?.primary_color ?? '#0c4cb4',
    );

    // svelte-ignore state_referenced_locally
    let searchQuery = $state(filters.search || '');
    // svelte-ignore state_referenced_locally
    let filterStatus = $state(filters.status || '');
    // svelte-ignore state_referenced_locally
    let perPage = $state(filters.perPage || 10);
    // svelte-ignore state_referenced_locally
    let filterSort = $state(filters.sort || 'order-asc');
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
            { search: searchQuery, perPage: perPage, sort: filterSort },
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
                    const first =
                        Object.values(err)[0] ||
                        'Gagal menghapus brand terpilih.';
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

    function sortable(node: HTMLElement, options: any) {
        let sortableInstance = Sortable.create(node, options);
        return {
            update(newOptions: any) {
                sortableInstance.option(newOptions);
            },
            destroy() {
                sortableInstance.destroy();
            },
        };
    }

    let isReordering = $state(false);

    function saveSortOrder() {
        if (isReordering) return;
        isReordering = true;

        const startOrder = brands.from || 1;
        const rows = document.querySelectorAll('.brand-row');
        const data = Array.from(rows).map((row: any, index) => ({
            id: row.dataset.id,
            order: startOrder + index,
        }));

        router.post(
            brandReorder.url(),
            { brands: data },
            {
                preserveScroll: true,
                onFinish: () => {
                    isReordering = false;
                },
            }
        );
    }
</script>

<svelte:head>
    <title>Master Data: Brand</title>
</svelte:head>

<AdminLayout>
    <main class="w-full max-w-[1600px] mx-auto px-4 sm:px-6 py-6 space-y-5">
        <!-- Page Header -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-semibold tracking-tight text-slate-900">Master Brand Produk</h1>
                <p class="mt-0.5 text-sm text-slate-500">Atur Daftar Merek (Brand) Katalog Toko</p>
            </div>
            <button
                onclick={openAddModal}
                class="h-9 px-4 text-xs font-semibold text-white rounded-lg transition-opacity hover:opacity-90 flex items-center justify-center gap-1.5 cursor-pointer shadow-xs"
                style="background-color: {primary};"
            >
                <i class="ti ti-plus text-sm"></i>
                <span>Tambah Brand</span>
            </button>
        </div>

        <!-- Main Section: Table Card -->
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <!-- Header, PerPage & Search -->
            <div class="p-4 border-b border-slate-100 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 bg-slate-50/20">
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

                <!-- Sort Selector -->
                <div class="shrink-0 w-full sm:w-44">
                    <select
                        bind:value={filterSort}
                        onchange={updateQuery}
                        class="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 focus:border-slate-400 focus:outline-none transition-colors cursor-pointer"
                    >
                        <option value="order-asc">Urutan Kustom</option>
                        <option value="name-asc">Nama: A - Z</option>
                        <option value="name-desc">Nama: Z - A</option>
                        <option value="latest">Merek Terbaru</option>
                        <option value="oldest">Merek Terlama</option>
                    </select>
                </div>

                <!-- Search Bar -->
                <div class="flex-grow sm:max-w-md w-full sm:ml-4">
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
                    class="px-4 py-2.5 bg-slate-50 border-b border-slate-150 flex items-center justify-between gap-4 flex-wrap"
                >
                    <div class="flex items-center gap-3">
                        <span class="text-xs font-bold text-slate-655 uppercase tracking-wider flex items-center gap-1.5">
                            <i class="ti ti-checkbox text-slate-800 text-sm"></i>
                            {selectedBrands.length} Brand Terpilih
                        </span>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            onclick={() => {
                                selectedBrands = [];
                            }}
                            class="h-7 px-3 border border-slate-200 hover:bg-slate-55 text-slate-500 font-semibold rounded-lg text-[10px] transition uppercase tracking-wider cursor-pointer bg-white"
                        >
                            Batal Pilihan
                        </button>
                        <button
                            onclick={() => (deleteBulkModalOpen = true)}
                            class="h-7 px-3 bg-rose-500 hover:bg-rose-650 text-white font-semibold rounded-lg text-[10px] transition uppercase tracking-wider flex items-center gap-1 cursor-pointer"
                        >
                            <i class="ti ti-trash"></i>
                            Hapus Terpilih
                        </button>
                    </div>
                </div>
            {/if}

            {#if brands.data.length === 0}
                <div class="py-12 text-center text-slate-400 font-medium">
                    <i class="ti ti-tags text-4xl block mb-2 text-slate-350"></i>
                    Belum ada data brand produk.
                </div>
            {:else}
                <!-- Table Area -->
                <div class="overflow-x-auto">
                {#key brands.data}
                    <table class="w-full text-left border-collapse responsive-table">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50/50 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                <th class="py-3 px-2 w-8 text-center"></th>
                                <th class="py-3 px-4 w-12 text-center sm:table-cell hidden">
                                    <input
                                        type="checkbox"
                                        checked={selectAll}
                                        onchange={toggleSelectAll}
                                        class="rounded border-slate-300 text-slate-900 focus:ring-0 focus:outline-none w-4 h-4 cursor-pointer accent-slate-900"
                                    />
                                </th>
                                <th class="py-3 px-4">Nama Brand</th>
                                <th class="py-3 px-4">Slug</th>
                                <th class="py-3 px-4">Status</th>
                                <th class="py-3 px-4 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody 
                            class="divide-y divide-slate-100 text-slate-700 text-sm"
                            use:sortable={{
                                animation: 150,
                                handle: '.brand-drag-handle',
                                draggable: '.brand-row',
                                onEnd: saveSortOrder,
                            }}
                        >
                            {#each brands.data as brand (brand.id)}
                                {@const isActive = brand.is_active ?? true}
                                {@const isSelected = selectedBrands.includes(brand.id)}

                                <tr 
                                    class="brand-row hover:bg-slate-55/30 transition border-b border-slate-100 {isSelected ? 'bg-slate-50/50' : ''}"
                                    data-id={brand.id}
                                >
                                    <td class="py-3 px-2 text-center w-8">
                                        <span class="text-slate-400 cursor-move brand-drag-handle flex items-center justify-center" title="Geser Urutan">
                                            <i class="ti ti-grip-vertical text-base"></i>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-center sm:table-cell hidden">
                                        <input
                                            type="checkbox"
                                            checked={isSelected}
                                            onchange={() => toggleSelect(brand.id)}
                                            class="rounded border-slate-300 text-slate-900 focus:ring-0 focus:outline-none w-4 h-4 cursor-pointer accent-slate-900"
                                        />
                                    </td>
                                    <td class="py-3.5 px-4" data-label="Nama Brand">
                                        <div class="flex items-center gap-3">
                                            <!-- Checkbox for Mobile View -->
                                            <input
                                                type="checkbox"
                                                checked={isSelected}
                                                onchange={() => toggleSelect(brand.id)}
                                                class="rounded border-slate-300 text-slate-900 focus:ring-0 focus:outline-none w-4 h-4 cursor-pointer accent-slate-900 sm:hidden block"
                                            />
                                            <div class="w-8 h-8 rounded-lg bg-slate-50 border border-slate-200 text-slate-550 flex items-center justify-center">
                                                <i class="ti ti-tag text-base"></i>
                                            </div>
                                            <h4 class="text-sm font-semibold text-slate-800">
                                                {brand.name}
                                            </h4>
                                        </div>
                                    </td>
                                    <td class="py-3.5 px-4 font-mono text-xs text-slate-500" data-label="Slug">
                                        {brand.slug}
                                    </td>
                                    <td class="py-3.5 px-4" data-label="Status">
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider {isActive ? 'bg-emerald-50 text-emerald-600 border border-emerald-200/30' : 'bg-slate-100 text-slate-500 border border-slate-200/50'}">
                                            {isActive ? 'Aktif' : 'Nonaktif'}
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-center" data-label="Aksi">
                                        <div class="flex items-center justify-end sm:justify-center gap-1.5">
                                            <button
                                                aria-label="Edit"
                                                onclick={() => openEditModal(brand)}
                                                class="w-7 h-7 rounded-md border border-slate-200 hover:bg-slate-50 hover:text-slate-800 text-slate-555 flex items-center justify-center transition-colors cursor-pointer"
                                                title="Ubah Data"
                                            >
                                                <i class="ti ti-pencil text-xs"></i>
                                            </button>
                                            <button
                                                onclick={() => toggleStatus(brand)}
                                                class="w-7 h-7 rounded-md border border-slate-200 {isActive ? 'hover:bg-amber-50 hover:text-amber-600 text-slate-500' : 'hover:bg-emerald-50 hover:text-emerald-600 text-slate-400'} flex items-center justify-center transition-colors cursor-pointer"
                                                title="Ubah Status (Aktif/Nonaktif)"
                                            >
                                                <i class="ti {isActive ? 'ti-ban' : 'ti-check'} text-xs"></i>
                                            </button>
                                            <button
                                                onclick={() => confirmDelete(brand)}
                                                class="w-7 h-7 rounded-md border border-slate-200 hover:bg-rose-50 hover:text-rose-600 text-slate-555 flex items-center justify-center transition-colors cursor-pointer"
                                                title="Hapus Brand"
                                            >
                                                <i class="ti ti-trash text-xs"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            {/each}
                    </table>
                {/key}
                </div>
            {/if}

            <Pagination paginator={brands} />
        </div>
    </main>
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
                Apakah Anda yakin ingin menghapus <strong
                    >{selectedBrands.length} brand</strong
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
