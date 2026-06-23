<script lang="ts">
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { router } from '@inertiajs/svelte';
    import { useForm } from '@inertiajs/svelte';
    import Pagination from '@/components/ui/Pagination.svelte';
    import { showToast } from '@/utils/toast';
    import Select from '@/components/ui/Select.svelte';
    import Input from '@/components/ui/Input.svelte';
    import Toggle from '@/components/ui/Toggle.svelte';
    import { fade } from 'svelte/transition';
    import { bulkDelete as stickerBulkDelete } from '@/routes/admin/master-data/stickers';

    let { stickers = { data: [], links: [], total: 0 }, filters = {} } =
        $props();

    // svelte-ignore state_referenced_locally
    let searchQuery = $state(filters.search || '');
    // svelte-ignore state_referenced_locally
    let perPage = $state(filters.perPage || 20);
    let searchTimeout: any;

    // Checkbox state
    let selectedStickers = $state<string[]>([]);
    let selectAll = $derived(
        selectedStickers.length === stickers.data.length &&
            stickers.data.length > 0,
    );

    // Modal state
    let isModalOpen = $state(false);
    let isEditing = $state(false);
    let editId = $state<string | null>(null);
    let deleteModalOpen = $state(false);
    let deleteBulkModalOpen = $state(false);
    let itemToDelete = $state<any>(null);
    let submittingBulkDelete = $state(false);

    // Image preview
    let imagePreview = $state<string | null>(null);
    let imageFile = $state<File | null>(null);
    let fileInputEl: HTMLInputElement;

    const form = useForm({
        name: '',
        category: '',
        is_active: true,
    });

    function toggleSelectAll() {
        if (selectAll) {
            selectedStickers = [];
        } else {
            selectedStickers = stickers.data.map((s: any) => s.id);
        }
    }

    function toggleSelect(id: string) {
        if (selectedStickers.includes(id)) {
            selectedStickers = selectedStickers.filter((sId) => sId !== id);
        } else {
            selectedStickers = [...selectedStickers, id];
        }
    }

    function updateQuery() {
        router.get(
            '/admin/master-data/stickers',
            { search: searchQuery, perPage },
            { preserveState: true, replace: true },
        );
    }

    function handleSearch() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(updateQuery, 500);
    }

    function handlePerPageChange() {
        updateQuery();
    }

    function openAddModal() {
        isEditing = false;
        editId = null;
        form.reset();
        form.clearErrors();
        imagePreview = null;
        imageFile = null;
        isModalOpen = true;
    }

    function openEditModal(sticker: any) {
        isEditing = true;
        editId = sticker.id;
        form.reset();
        form.clearErrors();
        form.name = sticker.name;
        form.category = sticker.category || '';
        form.is_active = sticker.is_active ?? true;
        imagePreview = sticker.url;
        imageFile = null;
        isModalOpen = true;
    }

    function closeModal() {
        isModalOpen = false;
        imagePreview = null;
        imageFile = null;
        form.reset();
    }

    function handleImageChange(e: Event) {
        const input = e.target as HTMLInputElement;
        const file = input.files?.[0];
        if (!file) {
            return;
        }
        imageFile = file;
        const reader = new FileReader();
        reader.onload = (ev) => {
            imagePreview = ev.target?.result as string;
        };
        reader.readAsDataURL(file);
    }

    function saveSticker(e: SubmitEvent) {
        e.preventDefault();
        if (!isEditing && !imageFile) {
            showToast('Gambar stiker wajib dipilih', 'error');
            return;
        }

        const data = new FormData();
        data.append('name', form.name);
        data.append('category', form.category || '');
        data.append('is_active', form.is_active ? '1' : '0');
        if (imageFile) {
            data.append('image', imageFile);
        }

        if (isEditing) {
            data.append('_method', 'PUT');
            router.post(`/admin/master-data/stickers/${editId}`, data, {
                forceFormData: true,
                onSuccess: () => {
                    showToast('Stiker berhasil diperbarui', 'success');
                    closeModal();
                },
                onError: (err: any) => {
                    if (err.error) {
                        showToast(err.error, 'error');
                    }
                },
            });
        } else {
            router.post('/admin/master-data/stickers', data, {
                forceFormData: true,
                onSuccess: () => {
                    showToast('Stiker berhasil ditambahkan', 'success');
                    closeModal();
                },
                onError: (err: any) => {
                    if (err.error) {
                        showToast(err.error, 'error');
                    }
                },
            });
        }
    }

    function confirmDelete(sticker: any) {
        itemToDelete = sticker;
        deleteModalOpen = true;
    }

    function executeBulkDelete() {
        if (selectedStickers.length === 0) return;
        submittingBulkDelete = true;
        router.post(
            stickerBulkDelete.url(),
            {
                ids: selectedStickers,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    selectedStickers = [];
                    deleteBulkModalOpen = false;
                },
                onError: (err) => {
                    const first =
                        Object.values(err)[0] ||
                        'Gagal menghapus stiker terpilih.';
                    showToast(first, 'error');
                },
                onFinish: () => {
                    submittingBulkDelete = false;
                },
            },
        );
    }

    function executeDelete() {
        if (!itemToDelete) {
            return;
        }
        router.delete(`/admin/master-data/stickers/${itemToDelete.id}`, {
            onSuccess: () => {
                showToast('Stiker berhasil dihapus', 'success');
                deleteModalOpen = false;
                itemToDelete = null;
            },
            onError: (err: any) => {
                showToast(err?.error || 'Gagal menghapus stiker', 'error');
                deleteModalOpen = false;
            },
        });
    }

    function toggleStatus(sticker: any) {
        router.post(
            `/admin/master-data/stickers/${sticker.id}/toggle-active`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    showToast(
                        `Status stiker ${sticker.name} berhasil diubah`,
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
    <title>Master Data: Stiker Chat</title>
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
                        Master Stiker Chat
                    </h3>
                    <p
                        class="text-xs text-slate-400 font-bold uppercase tracking-wider mt-1"
                    >
                        Kelola koleksi stiker untuk fitur chat toko
                    </p>
                </div>
                <button
                    onclick={openAddModal}
                    class="flex items-center justify-center gap-2 px-5 py-3 bg-brand-blueRoyal hover:bg-blue-800 text-white font-bold rounded-2xl text-xs transition duration-200 shadow-lg shadow-brand-blueRoyal/20 shrink-0 font-outfit uppercase tracking-wider"
                >
                    <i class="ti ti-plus text-base"></i>
                    <span>Tambah Stiker</span>
                </button>
            </div>

            <!-- Table Card -->
            <div
                class="bg-white rounded-3xl border border-slate-200/80 shadow-card overflow-hidden"
            >
                <!-- Filters -->
                <div
                    class="p-6 border-b border-slate-100 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 bg-slate-50/20"
                >
                    <div class="shrink-0 flex items-center gap-3">
                        <Select
                            bind:value={perPage}
                            options={[
                                { id: 20, name: '20 Data' },
                                { id: 50, name: '50 Data' },
                                { id: 100, name: '100 Data' },
                            ]}
                            onchange={handlePerPageChange}
                        />
                        {#if stickers.data.length > 0}
                            <label
                                class="flex items-center gap-2 cursor-pointer text-xs font-bold text-slate-500 font-outfit uppercase select-none"
                            >
                                <input
                                    type="checkbox"
                                    checked={selectAll}
                                    onchange={toggleSelectAll}
                                    class="rounded border-slate-300 text-brand-blueRoyal focus:ring-brand-blueRoyal/20 w-4 h-4 cursor-pointer"
                                />
                                Pilih Semua
                            </label>
                        {/if}
                    </div>
                    <div class="flex-grow sm:max-w-md w-full sm:ml-auto">
                        <Input
                            type="text"
                            bind:value={searchQuery}
                            oninput={handleSearch}
                            placeholder="Cari nama atau kategori stiker..."
                            icon="ti-search"
                        />
                    </div>
                </div>

                <!-- Bulk Actions Bar -->
                {#if selectedStickers.length > 0}
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
                                {selectedStickers.length} Stiker Terpilih
                            </span>
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                onclick={() => {
                                    selectedStickers = [];
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

                {#if stickers.data.length === 0}
                    <div
                        class="py-16 text-center text-slate-400 font-bold font-outfit"
                    >
                        <i
                            class="ti ti-sticker text-5xl block mb-3 text-slate-300"
                        ></i>
                        Belum ada stiker. Tambahkan stiker pertama Anda!
                    </div>
                {:else}
                    <!-- Grid preview -->
                    <div
                        class="p-6 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4"
                    >
                        {#each stickers.data as sticker (sticker.id)}
                            {@const isActive = sticker.is_active ?? true}
                            {@const isSelected = selectedStickers.includes(
                                sticker.id,
                            )}
                            <div
                                class="group relative bg-slate-50 rounded-2xl border {isSelected
                                    ? 'border-brand-blueRoyal bg-brand-blueRoyal/5'
                                    : 'border-slate-200'} overflow-hidden flex flex-col items-center p-3 gap-2 transition hover:shadow-md {!isActive
                                    ? 'opacity-50'
                                    : ''}"
                            >
                                <!-- Checkbox -->
                                <div class="absolute top-2 left-2 z-10">
                                    <input
                                        type="checkbox"
                                        checked={isSelected}
                                        onchange={() =>
                                            toggleSelect(sticker.id)}
                                        class="rounded border-slate-300 text-brand-blueRoyal focus:ring-brand-blueRoyal/20 w-4 h-4 cursor-pointer shadow bg-white"
                                    />
                                </div>
                                <!-- Preview image -->
                                <div
                                    class="w-full aspect-square flex items-center justify-center rounded-xl overflow-hidden bg-white border border-slate-100"
                                >
                                    <img
                                        src={sticker.url}
                                        alt={sticker.name}
                                        class="max-w-full max-h-full object-contain p-1"
                                        loading="lazy"
                                    />
                                </div>

                                <!-- Name & Category -->
                                <div class="w-full text-center">
                                    <p
                                        class="text-[11px] font-bold text-slate-800 truncate"
                                    >
                                        {sticker.name}
                                    </p>
                                    {#if sticker.category}
                                        <p
                                            class="text-[10px] text-slate-400 truncate"
                                        >
                                            {sticker.category}
                                        </p>
                                    {/if}
                                </div>

                                <!-- Status badge -->
                                <span
                                    class="px-2 py-0.5 rounded-md text-[9px] font-black uppercase tracking-wider {isActive
                                        ? 'bg-emerald-50 text-emerald-600 border border-emerald-200/50'
                                        : 'bg-slate-100 text-slate-500'}"
                                >
                                    {isActive ? 'Aktif' : 'Nonaktif'}
                                </span>

                                <!-- Action buttons (appear on hover) -->
                                <div
                                    class="absolute top-2 right-2 flex flex-col gap-1 opacity-0 group-hover:opacity-100 transition-opacity"
                                >
                                    <button
                                        aria-label="Edit stiker"
                                        onclick={() => openEditModal(sticker)}
                                        class="w-7 h-7 rounded-lg bg-white border border-slate-200 hover:bg-brand-blueLight hover:text-brand-blueRoyal text-slate-500 flex items-center justify-center shadow-sm transition"
                                        title="Ubah"
                                    >
                                        <i class="ti ti-pencil text-xs"></i>
                                    </button>
                                    <button
                                        onclick={() => toggleStatus(sticker)}
                                        class="w-7 h-7 rounded-lg bg-white border border-slate-200 {isActive
                                            ? 'hover:bg-amber-50 hover:text-amber-600'
                                            : 'hover:bg-emerald-50 hover:text-emerald-600'} text-slate-500 flex items-center justify-center shadow-sm transition"
                                        title="Toggle Status"
                                    >
                                        <i
                                            class="ti {isActive
                                                ? 'ti-ban'
                                                : 'ti-check'} text-xs"
                                        ></i>
                                    </button>
                                    <button
                                        onclick={() => confirmDelete(sticker)}
                                        class="w-7 h-7 rounded-lg bg-white border border-slate-200 hover:bg-rose-50 hover:text-rose-600 text-slate-500 flex items-center justify-center shadow-sm transition"
                                        title="Hapus"
                                    >
                                        <i class="ti ti-trash text-xs"></i>
                                    </button>
                                </div>
                            </div>
                        {/each}
                    </div>
                {/if}

                <Pagination paginator={stickers} />
            </div>
        </main>
    </div>
</AdminLayout>

<!-- Add/Edit Modal -->
{#if isModalOpen}
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div
            class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"
            onclick={closeModal}
            onkeypress={closeModal}
            role="button"
            tabindex="0"
        ></div>
        <div
            class="bg-white rounded-3xl border border-slate-200 shadow-2xl w-full max-w-md relative z-10 overflow-hidden animate-in fade-in zoom-in duration-200"
        >
            <div
                class="px-6 py-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50"
            >
                <h3 class="font-outfit font-black text-lg text-slate-800">
                    {isEditing ? 'Ubah Stiker' : 'Tambah Stiker Baru'}
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

            <form onsubmit={saveSticker} class="p-6 space-y-4">
                <!-- Image upload -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-2">
                        Gambar Stiker {#if !isEditing}<span
                                class="text-rose-500">*</span
                            >{/if}
                    </label>
                    <div
                        class="w-full aspect-video rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50 flex flex-col items-center justify-center cursor-pointer hover:border-brand-blueRoyal hover:bg-blue-50/30 transition overflow-hidden relative"
                        onclick={() => fileInputEl.click()}
                        onkeydown={(e) =>
                            e.key === 'Enter' && fileInputEl.click()}
                        role="button"
                        tabindex="0"
                    >
                        {#if imagePreview}
                            <img
                                src={imagePreview}
                                alt="Preview"
                                class="max-h-40 object-contain p-4"
                            />
                            <span
                                class="text-[10px] text-slate-400 font-bold mt-1"
                                >Klik untuk ganti</span
                            >
                        {:else}
                            <i
                                class="ti ti-photo-up text-3xl text-slate-300 mb-2"
                            ></i>
                            <span class="text-xs font-bold text-slate-400"
                                >Klik untuk upload gambar</span
                            >
                            <span class="text-[10px] text-slate-300 mt-1"
                                >PNG, JPG, GIF, WebP — maks 2MB</span
                            >
                        {/if}
                    </div>
                    <input
                        bind:this={fileInputEl}
                        type="file"
                        accept="image/png,image/jpeg,image/gif,image/webp"
                        class="hidden"
                        onchange={handleImageChange}
                    />
                </div>

                <Input
                    type="text"
                    bind:value={form.name}
                    label="Nama Stiker"
                    required={true}
                    placeholder="Contoh: Halo, Terima Kasih, Suka"
                    error={form.errors.name}
                />

                <Input
                    type="text"
                    bind:value={form.category}
                    label="Kategori (opsional)"
                    placeholder="Contoh: Sapaan, Ekspresi, Belanja"
                    error={form.errors.category}
                />

                <div class="pt-1">
                    <Toggle
                        bind:checked={form.is_active}
                        label="Aktifkan Stiker"
                        description="Stiker yang aktif akan tersedia di menu kirim stiker pada chat."
                        icon="ti-toggle-left"
                    />
                </div>

                <div
                    class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3"
                >
                    <button
                        type="button"
                        onclick={closeModal}
                        class="px-5 py-3 border border-slate-200 hover:bg-slate-50 text-slate-500 font-bold rounded-2xl text-xs transition uppercase tracking-wider font-outfit"
                        >Batal</button
                    >
                    <button
                        type="submit"
                        disabled={form.processing}
                        class="px-5 py-3 bg-brand-blueRoyal hover:bg-blue-800 text-white font-bold rounded-2xl text-xs transition shadow-lg shadow-brand-blueRoyal/20 uppercase tracking-wider font-outfit disabled:opacity-70 flex items-center gap-2"
                    >
                        {#if form.processing}
                            <i class="ti ti-loader animate-spin"></i>
                        {/if}
                        {isEditing ? 'Perbarui Stiker' : 'Simpan Stiker'}
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
            class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm"
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
                Hapus Stiker?
            </h4>
            <p class="text-sm text-center text-slate-500 font-medium mb-8">
                Stiker <strong>{itemToDelete?.name}</strong> akan terhapus secara
                permanen beserta file gambarnya.
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
                Hapus {selectedStickers.length} Stiker Terpilih?
            </h4>
            <p class="text-sm text-center text-slate-555 font-medium mb-8">
                Apakah Anda yakin ingin menghapus <strong
                    >{selectedStickers.length} stiker</strong
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
