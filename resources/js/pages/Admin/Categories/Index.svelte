<script lang="ts">
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { useForm, router } from '@inertiajs/svelte';
    import { showToast } from '@/utils/toast';
    import Sortable from 'sortablejs';
    import {
        store as adminCategoriesStore,
        update as adminCategoriesUpdate,
        destroy as adminCategoriesDestroy,
        reorder as adminCategoriesReorder,
    } from '@/routes/admin/categories';
    import Input from '@/components/ui/Input.svelte';

    let { categories } = $props();

    let iconSearch = $state('');
    let showDropdown = $state(false);

    const availableIcons = [
        // General
        'ti-home',
        'ti-category',
        'ti-category-2',
        'ti-box',
        'ti-package',
        'ti-tags',
        'ti-tag',
        'ti-discount',
        'ti-shopping-cart',
        'ti-basket',
        'ti-credit-card',
        'ti-wallet',
        'ti-cash',
        'ti-gift',
        'ti-star',
        'ti-heart',

        // Fashion & Clothing
        'ti-shirt',
        'ti-hanger',
        'ti-sunglasses',
        'ti-shoe',
        'ti-hat',
        'ti-jacket',
        'ti-tie',
        'ti-dress',
        'ti-bag',
        'ti-watch',
        'ti-diamond',

        // Furniture
        'ti-sofa',
        'ti-table',
        'ti-chair-director',
        'ti-armchair',
        'ti-bed',
        'ti-lamp',
        'ti-window',
        'ti-home-2',

        // Electronics
        'ti-device-tv',
        'ti-device-laptop',
        'ti-device-mobile',
        'ti-device-tablet',
        'ti-device-watch',
        'ti-device-speaker',
        'ti-device-gamepad',
        'ti-headphones',
        'ti-camera',
        'ti-printer',
        'ti-keyboard',
        'ti-mouse',

        // Home Appliances
        'ti-air-conditioning',
        'ti-fridge',
        'ti-microwave',
        'ti-wash-machine',
        'ti-vacuum-cleaner',

        // Beauty & Health
        'ti-brush',
        'ti-palette',
        'ti-paint',
        'ti-flower',
        'ti-spa',
        'ti-heartbeat',
        'ti-stethoscope',

        // Food & Beverage
        'ti-coffee',
        'ti-glass-full',
        'ti-cookie',
        'ti-ice-cream',
        'ti-chef-hat',
        'ti-bottle',

        // Baby & Kids
        'ti-baby-carriage',
        'ti-teddy-bear',
        'ti-balloon',

        // Automotive
        'ti-car',
        'ti-bike',
        'ti-scooter',
        'ti-engine',
        'ti-gas-station',

        // Office & School
        'ti-books',
        'ti-book',
        'ti-pencil',
        'ti-notebook',
        'ti-school',
        'ti-briefcase',

        // Sports & Hobby
        'ti-barbell',
        'ti-ball-football',
        'ti-ball-basketball',
        'ti-ball-tennis',
        'ti-swimming',
        'ti-music',

        // Building & Store
        'ti-building',
        'ti-building-store',
        'ti-building-warehouse',
        'ti-building-arch',
        'ti-store',
        'ti-tools',

        // Nature & Garden
        'ti-plant',
        'ti-leaf',
        'ti-tree',
        'ti-flower',

        // Misc
        'ti-box-multiple',
        'ti-archive',
        'ti-truck-delivery',
        'ti-world',
        'ti-map-pin',
    ];

    let filteredIcons = $derived(
        availableIcons.filter((icon) =>
            icon.toLowerCase().includes(iconSearch.toLowerCase()),
        ),
    );

    let imagePreview = $state(null);
    let isEditing = $state(false);
    let editingId = $state(null);

    // Delete Modal State
    let deleteModalOpen = $state(false);
    let deleteCategoryId = $state(null);

    const form = useForm({
        _method: 'post',
        name: '',
        slug: '',
        media_type: 'icon',
        icon: 'ti-folder',
        image: null,
        parent_id: '',
    });

    $effect(() => {
        if (!isEditing && form.name) {
            form.slug = form.name
                .toLowerCase()
                .replace(/[^a-z0-9\s-]/g, '')
                .replace(/\s+/g, '-')
                .replace(/-+/g, '-');
        }
    });

    function handleImageChange(e) {
        const file = e.target.files[0];
        if (file) {
            form.image = file;
            imagePreview = URL.createObjectURL(file);
        } else {
            form.image = null;
            imagePreview = null;
        }
    }

    function editCategory(category) {
        isEditing = true;
        editingId = category.id;
        form.name = category.name;
        form.slug = category.slug;
        form.media_type = category.image ? 'image' : 'icon';
        form.icon = category.icon || 'ti-folder';
        form.parent_id = category.parent_id || '';
        form.image = null; // Don't bind File object
        imagePreview = category.image || null;
        form._method = 'put'; // Method spoofing for file uploads

        // Scroll to form
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function cancelEdit() {
        isEditing = false;
        editingId = null;
        form.reset();
        form._method = 'post';
        imagePreview = null;
        const fileInput = document.getElementById('image-upload');
        if (fileInput) fileInput.value = '';
    }

    function submit() {
        if (isEditing) {
            form.post(adminCategoriesUpdate.url({ category: editingId }), {
                onSuccess: () => {
                    cancelEdit();
                    showToast('Kategori berhasil diperbarui!', 'success');
                },
            });
        } else {
            form._method = 'post';
            form.post(adminCategoriesStore.url(), {
                onSuccess: () => {
                    cancelEdit();
                    showToast('Kategori berhasil ditambahkan!', 'success');
                },
            });
        }
    }

    function confirmDelete(id) {
        deleteCategoryId = id;
        deleteModalOpen = true;
    }

    function executeDelete() {
        if (deleteCategoryId) {
            router.delete(
                adminCategoriesDestroy.url({ category: deleteCategoryId }),
                {
                    onSuccess: () => {
                        deleteModalOpen = false;
                        deleteCategoryId = null;
                        showToast('Kategori berhasil dihapus!', 'success');
                    },
                },
            );
        }
    }

    function sortable(node, options) {
        let sortableInstance = Sortable.create(node, options);
        return {
            update(newOptions) {
                sortableInstance.option(newOptions);
            },
            destroy() {
                sortableInstance.destroy();
            },
        };
    }

    function saveSortOrder() {
        const data = [];
        const parents = document.querySelectorAll('.category-item');
        parents.forEach((parentEl, index) => {
            data.push({
                id: parentEl.dataset.id,
                parent_id: null,
                order: index,
            });
            const children = parentEl.querySelectorAll('.subcategory-item');
            children.forEach((childEl, childIndex) => {
                data.push({
                    id: childEl.dataset.id,
                    parent_id: parentEl.dataset.id,
                    order: childIndex,
                });
            });
        });

        router.post(
            adminCategoriesReorder.url(),
            { categories: data },
            {
                preserveScroll: true,
                onSuccess: () => {
                    showToast(
                        'Urutan kategori berhasil diperbarui!',
                        'success',
                    );
                },
            },
        );
    }
</script>

<AdminLayout title="Kategori">
    <div class="flex-grow p-4 sm:p-8 w-full max-w-full mx-auto">
        <div
            class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6"
        >
            <div>
                <h3 class="font-outfit font-black text-2xl text-slate-800">
                    Kategori & Departemen Toko
                </h3>
                <p
                    class="text-xs text-slate-400 font-bold uppercase tracking-wider mt-1"
                >
                    Organisasikan Kategori & Taksonomi Produk Anda
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
            <div class="lg:col-span-2 space-y-6">
                <div
                    class="bg-white rounded-3xl border border-slate-200 shadow-card p-6 sm:p-8 space-y-6"
                >
                    <div>
                        <h3
                            class="font-outfit font-black text-lg text-slate-800"
                        >
                            Daftar Struktur Kategori
                        </h3>
                        <p class="text-xs text-slate-500 font-medium">
                            Susun prioritas menu toko (Drag & drop ikon titik
                            enam untuk mengubah urutan).
                        </p>
                    </div>

                    <div
                        class="space-y-3"
                        use:sortable={{
                            animation: 150,
                            handle: '.cursor-move',
                            onEnd: saveSortOrder,
                        }}
                    >
                        {#each categories as category (category.id)}
                            <div
                                class="category-item border border-slate-200 rounded-2xl p-4 bg-slate-50/50 flex flex-col gap-2"
                                data-id={category.id}
                            >
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <span class="text-slate-400 cursor-move"
                                            ><i
                                                class="ti ti-grip-vertical text-lg"
                                            ></i></span
                                        >
                                        {#if category.image}
                                            <img
                                                src={category.image}
                                                alt={category.name}
                                                class="w-10 h-10 rounded-xl object-cover border border-slate-200 bg-slate-50"
                                            />
                                        {:else}
                                            <div
                                                class="w-10 h-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-brand-blueRoyal text-xl"
                                            >
                                                <i
                                                    class="ti {category.icon ||
                                                        'ti-folder'}"
                                                ></i>
                                            </div>
                                        {/if}
                                        <div>
                                            <h4
                                                class="text-sm font-bold text-slate-800"
                                            >
                                                {category.name}
                                            </h4>
                                            <p
                                                class="text-[10px] text-slate-500 font-medium"
                                            >
                                                Slug: {category.slug}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button
                                            aria-label="Edit"
                                            onclick={() =>
                                                editCategory(category)}
                                            class="p-1 text-brand-blueRoyal hover:text-brand-blueRoyal"
                                            ><i class="ti ti-edit"></i></button
                                        >
                                        <button
                                            aria-label="Hapus"
                                            onclick={() =>
                                                confirmDelete(category.id)}
                                            class="p-1 text-red-500 hover:text-red-600"
                                            ><i class="ti ti-trash"></i></button
                                        >
                                    </div>
                                </div>

                                {#if category.children && category.children.length > 0}
                                    <div
                                        class="pl-12 space-y-2 border-l border-slate-200 mt-2 min-h-2"
                                        use:sortable={{
                                            animation: 150,
                                            handle: '.cursor-move-sub',
                                            onEnd: saveSortOrder,
                                        }}
                                    >
                                        {#each category.children as sub (sub.id)}
                                            <div
                                                class="subcategory-item flex items-center justify-between py-2 border-b border-slate-100 last:border-0"
                                                data-id={sub.id}
                                            >
                                                <div
                                                    class="flex items-center gap-3"
                                                >
                                                    <span
                                                        class="text-slate-300 cursor-move-sub hover:text-slate-500 transition"
                                                        ><i
                                                            class="ti ti-grip-vertical text-lg"
                                                        ></i></span
                                                    >
                                                    {#if sub.image}
                                                        <img
                                                            src={sub.image}
                                                            class="w-6 h-6 rounded object-cover"
                                                            alt=""
                                                        />
                                                    {:else}
                                                        <i
                                                            class="ti {sub.icon ||
                                                                'ti-folder'} text-brand-blueRoyal text-lg"
                                                        ></i>
                                                    {/if}
                                                    <div>
                                                        <span
                                                            class="text-xs font-bold text-slate-700"
                                                            >{sub.name}</span
                                                        >
                                                        <p
                                                            class="text-[9px] text-slate-400"
                                                        >
                                                            Slug: {sub.slug}
                                                        </p>
                                                    </div>
                                                </div>
                                                <div
                                                    class="flex items-center gap-2"
                                                >
                                                    <button
                                                        aria-label="Edit"
                                                        onclick={() =>
                                                            editCategory(sub)}
                                                        class="text-brand-blueRoyal hover:text-brand-blueRoyal"
                                                        ><i class="ti ti-edit"
                                                        ></i></button
                                                    >
                                                    <button
                                                        aria-label="Hapus Kategori"
                                                        onclick={() =>
                                                            confirmDelete(
                                                                sub.id,
                                                            )}
                                                        class="text-red-500 hover:text-red-600"
                                                        ><i class="ti ti-trash"
                                                        ></i></button
                                                    >
                                                </div>
                                            </div>
                                        {/each}
                                    </div>
                                {/if}
                            </div>
                        {/each}

                        {#if categories.length === 0}
                            <div
                                class="text-center py-8 text-slate-500 text-sm"
                            >
                                Belum ada kategori.
                            </div>
                        {/if}
                    </div>
                </div>
            </div>

            <div class="w-full space-y-6">
                <div
                    class="bg-white rounded-3xl border border-slate-200 shadow-card p-6 space-y-5 sticky top-6"
                >
                    <div
                        class="flex items-center justify-between pb-3 border-b border-slate-100"
                    >
                        <h3
                            class="font-outfit font-black text-lg text-slate-800"
                        >
                            {isEditing
                                ? 'Edit Kategori'
                                : 'Tambah Kategori Baru'}
                        </h3>
                        {#if isEditing}
                            <button
                                type="button"
                                onclick={cancelEdit}
                                class="text-xs font-bold text-slate-500 hover:text-slate-700 bg-slate-100 px-3 py-1.5 rounded-lg transition"
                            >
                                Batal
                            </button>
                        {/if}
                    </div>

                    <form
                        onsubmit={(e) => {
                            e.preventDefault();
                            submit();
                        }}
                        class="space-y-4"
                    >
                        <Input
                            id="input-category-name"
                            bind:value={form.name}
                            oninput={() => {
                                setTimeout(() => {
                                    if (!isEditing && form.name) {
                                        form.slug = form.name
                                            .toLowerCase()
                                            .replace(/[^a-z0-9\s-]/g, '')
                                            .replace(/\s+/g, '-')
                                            .replace(/-+/g, '-');
                                    } else if (!isEditing && !form.name) {
                                        form.slug = '';
                                    }
                                }, 10);
                            }}
                            label="Nama Kategori"
                            placeholder="Contoh: Meja Makan"
                            required={true}
                        />

                        <Input
                            id="input-category-slug"
                            bind:value={form.slug}
                            label="Slug URL"
                            placeholder="meja-makan"
                            required={true}
                        />

                        <div>
                            <p
                                class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-1.5 font-outfit"
                            >
                                Tipe Media Visual <span class="text-rose-500"
                                    >*</span
                                >
                            </p>
                            <div class="flex bg-slate-100 p-1 rounded-xl">
                                <button
                                    type="button"
                                    onclick={() => (form.media_type = 'icon')}
                                    class="flex-1 py-2 text-xs font-bold rounded-lg transition {form.media_type ===
                                    'icon'
                                        ? 'bg-white text-brand-blueRoyal shadow-sm'
                                        : 'text-slate-500 hover:text-slate-700'}"
                                >
                                    <i class="ti ti-star mr-1"></i> Gunakan Icon
                                </button>
                                <button
                                    type="button"
                                    onclick={() => (form.media_type = 'image')}
                                    class="flex-1 py-2 text-xs font-bold rounded-lg transition {form.media_type ===
                                    'image'
                                        ? 'bg-white text-brand-blueRoyal shadow-sm'
                                        : 'text-slate-500 hover:text-slate-700'}"
                                >
                                    <i class="ti ti-photo mr-1"></i> Upload Gambar
                                </button>
                            </div>
                        </div>

                        {#if form.media_type === 'icon'}
                            <div class="relative">
                                <p
                                    class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-1.5 font-outfit"
                                >
                                    Cari & Pilih Icon
                                </p>
                                <div class="relative relative-z">
                                    <span
                                        class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400"
                                    >
                                        <i class="ti ti-search"></i>
                                    </span>
                                    <input
                                        type="text"
                                        bind:value={iconSearch}
                                        onfocus={() => (showDropdown = true)}
                                        class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 text-sm text-slate-800 focus:outline-none focus:border-brand-blueRoyal focus:ring-1 focus:ring-brand-blueRoyal bg-white transition"
                                        placeholder="Cari nama icon (misal: sofa, bed)..."
                                    />
                                    <div
                                        class="absolute inset-y-0 right-2 flex items-center"
                                    >
                                        <div
                                            class="w-8 h-8 rounded-lg bg-brand-blueRoyal/10 text-brand-blueRoyal flex items-center justify-center text-lg"
                                        >
                                            <i class="ti {form.icon}"></i>
                                        </div>
                                    </div>
                                </div>

                                {#if showDropdown}
                                    <div
                                        class="absolute z-50 w-full mt-2 bg-white border border-slate-200 rounded-xl shadow-xl max-h-60 overflow-y-auto p-2 grid grid-cols-5 gap-2"
                                    >
                                        {#each filteredIcons as icon}
                                            <button
                                                type="button"
                                                aria-label="Pilih Icon"
                                                onclick={() => {
                                                    form.icon = icon;
                                                    showDropdown = false;
                                                }}
                                                class="w-10 h-10 rounded-lg bg-white border border-slate-200 hover:bg-brand-blueLight hover:text-brand-blueRoyal flex items-center justify-center text-lg transition {form.icon ===
                                                icon
                                                    ? 'border-2 border-brand-blueRoyal text-brand-blueRoyal'
                                                    : ''}"
                                            >
                                                <i class="ti {icon}"></i>
                                            </button>
                                        {/each}
                                        {#if filteredIcons.length === 0}
                                            <div
                                                class="col-span-5 text-center text-xs text-slate-500 py-4"
                                            >
                                                Icon tidak ditemukan
                                            </div>
                                        {/if}
                                    </div>
                                    <div
                                        class="fixed inset-0 z-40 bg-transparent"
                                        onclick={() => (showDropdown = false)}
                                        onkeypress={() =>
                                            (showDropdown = false)}
                                        role="button"
                                        tabindex="0"
                                    ></div>
                                {/if}
                            </div>
                        {:else}
                            <div>
                                <p
                                    class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-1.5 font-outfit"
                                >
                                    Upload Gambar
                                </p>
                                <div class="flex items-center gap-4">
                                    {#if imagePreview}
                                        <div
                                            class="w-16 h-16 shrink-0 rounded-xl border border-slate-200 overflow-hidden bg-slate-50"
                                        >
                                            <img
                                                src={imagePreview}
                                                class="w-full h-full object-cover"
                                                alt="Preview"
                                            />
                                        </div>
                                    {/if}
                                    <div class="flex-1 relative">
                                        <input
                                            type="file"
                                            id="image-upload"
                                            accept="image/*"
                                            onchange={handleImageChange}
                                            class="block w-full text-sm text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-brand-blueLight file:text-brand-blueRoyal hover:file:bg-brand-blueRoyal/20 transition cursor-pointer"
                                        />
                                    </div>
                                </div>
                                <p
                                    class="text-[10px] text-slate-400 font-medium mt-2"
                                >
                                    Maksimal 2MB. Disarankan rasio 1:1
                                    (persegi).
                                </p>
                                {#if form.errors.image}
                                    <p class="text-xs text-red-500 mt-1">
                                        {form.errors.image}
                                    </p>
                                {/if}
                            </div>
                        {/if}

                        <div>
                            <p
                                class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-1.5 font-outfit"
                            >
                                Kategori Induk (Parent)
                            </p>
                            <select
                                bind:value={form.parent_id}
                                class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm text-slate-800 focus:outline-none focus:border-brand-blueRoyal focus:ring-1 focus:ring-brand-blueRoyal focus:bg-white bg-slate-50 transition"
                            >
                                <option value=""
                                    >Tidak Ada (Jadikan Kategori Utama)</option
                                >
                                {#each categories as cat}
                                    <option value={cat.id}>{cat.name}</option>
                                {/each}
                            </select>
                            {#if form.errors.parent_id}
                                <p class="text-xs text-red-500 mt-1">
                                    {form.errors.parent_id}
                                </p>
                            {/if}
                        </div>

                        <div class="pt-4">
                            <button
                                type="submit"
                                disabled={form.processing}
                                class="w-full py-3 {isEditing
                                    ? 'bg-orange-500 hover:bg-orange-600 shadow-orange-500/20'
                                    : 'bg-brand-blueRoyal hover:bg-blue-800 shadow-brand-blueRoyal/20'} text-white font-bold rounded-xl text-xs transition shadow-lg font-outfit uppercase tracking-wider disabled:opacity-50 flex justify-center items-center gap-2"
                            >
                                {#if form.processing}
                                    <i class="ti ti-loader animate-spin text-lg"
                                    ></i> Menyimpan...
                                {:else}
                                    <i class="ti ti-device-floppy text-lg"></i>
                                    {isEditing
                                        ? 'Perbarui Kategori'
                                        : 'Simpan Kategori'}
                                {/if}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

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
                    Hapus Kategori?
                </h4>
                <p class="text-sm text-center text-slate-500 font-medium mb-8">
                    Data kategori ini beserta subkategori di bawahnya (jika ada)
                    akan ikut terhapus dan tidak dapat dikembalikan.
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
