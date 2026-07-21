<script lang="ts">
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { useForm, router, Link } from '@inertiajs/svelte';
    import { page } from '@inertiajs/svelte';
    import { showToast } from '@/utils/toast';
    import Sortable from 'sortablejs';
    import {
        store as adminCategoriesStore,
        update as adminCategoriesUpdate,
        destroy as adminCategoriesDestroy,
        reorder as adminCategoriesReorder,
        bulkDelete as adminCategoriesBulkDelete,
    } from '@/routes/admin/categories';
    import Input from '@/components/ui/Input.svelte';

    let { categories } = $props();

    const primary = $derived(
        (page.props as any).theme?.primary_color ?? '#0c4cb4',
    );

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
    let deleteBulkModalOpen = $state(false);
    let submittingBulkDelete = $state(false);

    // Checkbox state
    let selectedCategories = $state<string[]>([]);
    
    // Get all category IDs (parents + children)
    const allCategoryIds = $derived([
        ...categories.map((c: any) => c.id),
        ...categories.flatMap((c: any) => c.children?.map((sub: any) => sub.id) || []),
    ]);

    const selectAll = $derived(
        selectedCategories.length === allCategoryIds.length &&
            allCategoryIds.length > 0,
    );

    function toggleSelectAll() {
        if (selectAll) {
            selectedCategories = [];
        } else {
            selectedCategories = [...allCategoryIds];
        }
    }

    function toggleSelect(id: string) {
        const parentCat = categories.find((c: any) => c.id === id);
        if (parentCat) {
            if (selectedCategories.includes(id)) {
                const childIds = parentCat.children?.map((c: any) => c.id) || [];
                selectedCategories = selectedCategories.filter(
                    (item) => item !== id && !childIds.includes(item)
                );
            } else {
                const childIds = parentCat.children?.map((c: any) => c.id) || [];
                selectedCategories = [...new Set([...selectedCategories, id, ...childIds])];
            }
        } else {
            if (selectedCategories.includes(id)) {
                selectedCategories = selectedCategories.filter((item) => item !== id);
                const parent = categories.find((c: any) => c.children?.some((sub: any) => sub.id === id));
                if (parent && selectedCategories.includes(parent.id)) {
                    selectedCategories = selectedCategories.filter((item) => item !== parent.id);
                }
            } else {
                selectedCategories = [...selectedCategories, id];
            }
        }
    }

    function executeBulkDelete() {
        if (selectedCategories.length === 0) return;
        submittingBulkDelete = true;
        router.post(
            adminCategoriesBulkDelete.url(),
            {
                ids: selectedCategories,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    selectedCategories = [];
                    deleteBulkModalOpen = false;
                    showToast('Kategori terpilih berhasil dihapus!', 'success');
                },
                onError: (err: any) => {
                    const first =
                        Object.values(err || {})[0] ||
                        'Gagal menghapus kategori terpilih.';
                    showToast(first, 'error');
                },
                onFinish: () => {
                    submittingBulkDelete = false;
                },
            },
        );
    }

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
            if (file.size > 2 * 1024 * 1024) {
                form.setError(
                    'image',
                    'Ukuran gambar tidak boleh lebih dari 2MB.',
                );
                form.image = null;
                imagePreview = null;
                e.target.value = '';
                return;
            }

            const img = new Image();
            img.src = URL.createObjectURL(file);
            img.onload = () => {
                if (img.height < img.width) {
                    form.setError(
                        'image',
                        'Gambar harus memiliki rasio 1:1 (persegi) atau portrait (tinggi lebih besar atau sama dengan lebar).',
                    );
                    form.image = null;
                    imagePreview = null;
                    e.target.value = '';
                } else {
                    form.clearErrors('image');
                    form.image = file;
                    imagePreview = img.src;
                }
            };
        } else {
            form.image = null;
            imagePreview = null;
            form.clearErrors('image');
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
        if (form.media_type === 'image') {
            if (!isEditing && !form.image) {
                form.setError(
                    'image',
                    'Gambar wajib diunggah saat tipe media visual adalah gambar.',
                );
                return;
            }
            if (isEditing && !form.image && !imagePreview) {
                form.setError(
                    'image',
                    'Gambar wajib diunggah saat tipe media visual adalah gambar.',
                );
                return;
            }
        }

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
            }
        );
    }
</script>

<AdminLayout>
    <main class="w-full max-w-[1600px] mx-auto px-4 sm:px-6 py-6 space-y-5">
        <!-- Page header -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-semibold tracking-tight text-slate-900">Kategori & Departemen Toko</h1>
                <p class="mt-0.5 text-sm text-slate-500">Organisasikan Kategori & Taksonomi Produk Anda</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 items-start">
            <!-- Left: Structural List -->
            <div class="space-y-5 lg:col-span-2">
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                    <div class="border-b border-slate-100 px-5 py-3.5">
                        <p class="text-sm font-semibold text-slate-800">Daftar Struktur Kategori</p>
                        <p class="text-xs text-slate-400 mt-0.5">Susun prioritas menu toko (Drag & drop ikon titik enam untuk mengubah urutan).</p>
                    </div>

                    <div class="p-5 space-y-4">
                        <!-- Bulk Actions Bar -->
                        {#if categories.length > 0}
                            <div class="flex items-center justify-between border border-slate-100 py-2.5 px-4 rounded-lg bg-slate-50/50">
                                <label class="flex items-center gap-2 cursor-pointer select-none">
                                    <input
                                        type="checkbox"
                                        checked={selectAll}
                                        onchange={toggleSelectAll}
                                        class="rounded border-slate-300 text-slate-900 focus:ring-0 focus:outline-none w-4 h-4 cursor-pointer accent-slate-900"
                                    />
                                    <span class="text-xs font-bold text-slate-650 uppercase tracking-wider">
                                        Pilih Semua ({selectedCategories.length} terpilih)
                                    </span>
                                </label>
                                {#if selectedCategories.length > 0}
                                    <div class="flex items-center gap-2">
                                        <button
                                            onclick={() => (selectedCategories = [])}
                                            class="h-7 px-3 border border-slate-200 hover:bg-slate-50 text-slate-500 font-semibold rounded-lg text-[10px] transition uppercase tracking-wider cursor-pointer bg-white"
                                        >
                                            Batal
                                        </button>
                                        <button
                                            onclick={() => (deleteBulkModalOpen = true)}
                                            class="h-7 px-3 bg-rose-500 hover:bg-rose-650 text-white font-semibold rounded-lg text-[10px] transition uppercase tracking-wider flex items-center gap-1 cursor-pointer"
                                        >
                                            <i class="ti ti-trash"></i>
                                            Hapus Terpilih
                                        </button>
                                    </div>
                                {/if}
                            </div>
                        {/if}

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
                                    class="category-item border border-slate-200 rounded-xl p-4 bg-slate-50/20 hover:bg-slate-50/40 transition flex flex-col gap-2"
                                    data-id={category.id}
                                >
                                    <div class="flex items-center justify-between min-w-0 gap-2">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <input
                                                type="checkbox"
                                                checked={selectedCategories.includes(category.id)}
                                                onchange={() => toggleSelect(category.id)}
                                                class="rounded border-slate-300 text-slate-900 focus:ring-0 focus:outline-none w-4 h-4 cursor-pointer accent-slate-900"
                                            />
                                            <span class="text-slate-400 cursor-move" title="Geser Urutan">
                                                <i class="ti ti-grip-vertical text-lg"></i>
                                            </span>
                                            {#if category.image}
                                                <img
                                                    src={category.image}
                                                    alt={category.name}
                                                    class="w-10 h-10 rounded-lg object-cover border border-slate-200 bg-white"
                                                />
                                            {:else}
                                                <div class="w-10 h-10 rounded-lg bg-white border border-slate-200 flex items-center justify-center text-slate-600 text-lg">
                                                    <i class="ti {category.icon || 'ti-folder'}"></i>
                                                </div>
                                            {/if}
                                            <div class="min-w-0">
                                                <h4 class="text-sm font-bold text-slate-800 truncate">
                                                    {category.name}
                                                </h4>
                                                <p class="text-[10px] text-slate-400 font-mono truncate">
                                                    slug: {category.slug}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2 shrink-0">
                                            <button
                                                aria-label="Edit"
                                                onclick={() => editCategory(category)}
                                                class="p-1.5 text-slate-500 hover:text-slate-900 transition-colors cursor-pointer"
                                            >
                                                <i class="ti ti-edit text-base"></i>
                                            </button>
                                            <button
                                                aria-label="Hapus"
                                                onclick={() => confirmDelete(category.id)}
                                                class="p-1.5 text-slate-400 hover:text-rose-600 transition-colors cursor-pointer"
                                            >
                                                <i class="ti ti-trash text-base"></i>
                                            </button>
                                        </div>
                                    </div>

                                    {#if category.children && category.children.length > 0}
                                        <div
                                            class="pl-4 sm:pl-12 space-y-2 border-l-2 border-slate-100 mt-2 min-h-2"
                                            use:sortable={{
                                                animation: 150,
                                                handle: '.cursor-move-sub',
                                                onEnd: saveSortOrder,
                                            }}
                                        >
                                            {#each category.children as sub (sub.id)}
                                                <div
                                                    class="subcategory-item flex items-center justify-between py-2 border-b border-slate-100 last:border-0 min-w-0 gap-2"
                                                    data-id={sub.id}
                                                >
                                                    <div class="flex items-center gap-3 min-w-0">
                                                        <input
                                                            type="checkbox"
                                                            checked={selectedCategories.includes(sub.id)}
                                                            onchange={() => toggleSelect(sub.id)}
                                                            class="rounded border-slate-300 text-slate-900 focus:ring-0 focus:outline-none w-4 h-4 cursor-pointer accent-slate-900"
                                                        />
                                                        <span class="text-slate-300 cursor-move-sub hover:text-slate-500 transition" title="Geser Urutan">
                                                            <i class="ti ti-grip-vertical text-lg"></i>
                                                        </span>
                                                        {#if sub.image}
                                                            <img
                                                                src={sub.image}
                                                                class="w-6 h-6 rounded object-cover border border-slate-200/50"
                                                                alt=""
                                                            />
                                                        {:else}
                                                            <i class="ti {sub.icon || 'ti-folder'} text-slate-400 text-base"></i>
                                                        {/if}
                                                        <div class="min-w-0">
                                                            <span class="text-xs font-semibold text-slate-700 block truncate">{sub.name}</span>
                                                            <p class="text-[9px] text-slate-450 font-mono truncate">
                                                                slug: {sub.slug}
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div class="flex items-center gap-2 shrink-0">
                                                        <button
                                                            aria-label="Edit"
                                                            onclick={() => editCategory(sub)}
                                                            class="p-1 text-slate-500 hover:text-slate-900 transition-colors cursor-pointer"
                                                        >
                                                            <i class="ti ti-edit"></i>
                                                        </button>
                                                        <button
                                                            aria-label="Hapus Kategori"
                                                            onclick={() => confirmDelete(sub.id)}
                                                            class="p-1 text-slate-400 hover:text-rose-600 transition-colors cursor-pointer"
                                                        >
                                                            <i class="ti ti-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            {/each}
                                        </div>
                                    {/if}
                                </div>
                            {/each}

                            {#if categories.length === 0}
                                <div class="text-center py-12 text-slate-400 text-sm">
                                    Belum ada kategori yang dibuat.
                                </div>
                            {/if}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Add / Edit Form -->
            <div class="w-full space-y-5">
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                    <div class="border-b border-slate-100 px-5 py-3.5 flex items-center justify-between">
                        <p class="text-sm font-semibold text-slate-800">
                            {isEditing ? 'Edit Kategori' : 'Tambah Kategori Baru'}
                        </p>
                        {#if isEditing}
                            <button
                                type="button"
                                onclick={cancelEdit}
                                class="h-6 rounded px-2.5 text-xs font-medium text-slate-500 border border-slate-200 hover:bg-slate-50 transition-colors cursor-pointer"
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
                        class="p-5 space-y-4"
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
                            <p class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">
                                Tipe Media Visual <span class="text-rose-500">*</span>
                            </p>
                            <div class="flex bg-slate-100 p-1 rounded-lg">
                                <button
                                    type="button"
                                    onclick={() => {
                                        form.media_type = 'icon';
                                        form.clearErrors('image');
                                    }}
                                    class="flex-1 py-1.5 text-xs font-semibold rounded-md transition-colors cursor-pointer {form.media_type === 'icon' ? 'bg-white text-slate-800 shadow-xs' : 'text-slate-500 hover:text-slate-700'}"
                                >
                                    <i class="ti ti-star mr-1"></i> Gunakan Icon
                                </button>
                                <button
                                    type="button"
                                    onclick={() => {
                                        form.media_type = 'image';
                                        form.clearErrors('image');
                                    }}
                                    class="flex-1 py-1.5 text-xs font-semibold rounded-md transition-colors cursor-pointer {form.media_type === 'image' ? 'bg-white text-slate-800 shadow-xs' : 'text-slate-500 hover:text-slate-700'}"
                                >
                                    <i class="ti ti-photo mr-1"></i> Upload Gambar
                                </button>
                            </div>
                        </div>

                        {#if form.media_type === 'icon'}
                            <div class="relative">
                                <p class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">
                                    Cari & Pilih Icon
                                </p>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                        <i class="ti ti-search text-xs"></i>
                                    </span>
                                    <input
                                        type="text"
                                        bind:value={iconSearch}
                                        onfocus={() => (showDropdown = true)}
                                        class="h-9 w-full pl-8 pr-10 rounded-lg border border-slate-200 text-sm text-slate-800 focus:border-slate-400 focus:outline-none bg-white transition-colors"
                                        placeholder="Cari nama icon (misal: sofa, bed)..."
                                    />
                                    <div class="absolute inset-y-0 right-2 flex items-center">
                                        <div class="w-7 h-7 rounded bg-slate-100 border border-slate-200 text-slate-700 flex items-center justify-center text-base">
                                            <i class="ti {form.icon}"></i>
                                        </div>
                                    </div>
                                </div>

                                {#if showDropdown}
                                    <div class="absolute z-50 w-full mt-2 bg-white border border-slate-200 rounded-lg shadow-lg max-h-60 overflow-y-auto p-2 grid grid-cols-5 gap-2 animate-in fade-in duration-100">
                                        {#each filteredIcons as icon}
                                            <button
                                                type="button"
                                                aria-label="Pilih Icon"
                                                onclick={() => {
                                                    form.icon = icon;
                                                    showDropdown = false;
                                                }}
                                                class="w-10 h-10 rounded-lg bg-white border border-slate-200 hover:bg-slate-50 hover:text-slate-800 flex items-center justify-center text-lg transition-colors cursor-pointer {form.icon === icon ? 'border-slate-800 text-slate-800 bg-slate-50/50' : ''}"
                                            >
                                                <i class="ti {icon}"></i>
                                            </button>
                                        {/each}
                                        {#if filteredIcons.length === 0}
                                            <div class="col-span-5 text-center text-xs text-slate-400 py-4">
                                                Icon tidak ditemukan
                                            </div>
                                        {/if}
                                    </div>
                                    <div
                                        class="fixed inset-0 z-40 bg-transparent"
                                        onclick={() => (showDropdown = false)}
                                        onkeypress={() => (showDropdown = false)}
                                        role="button"
                                        tabindex="0"
                                    ></div>
                                {/if}
                            </div>
                        {:else}
                            <div class="space-y-2">
                                <p class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">
                                    Upload Gambar <span class="text-rose-500">*</span>
                                </p>
                                <div class="flex items-center gap-4">
                                    {#if imagePreview}
                                        <div class="w-14 h-14 shrink-0 rounded-lg border border-slate-200 overflow-hidden bg-slate-50">
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
                                            class="block w-full text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200 transition-colors cursor-pointer"
                                        />
                                    </div>
                                </div>
                                <p class="text-[10px] text-slate-400 font-medium leading-normal">
                                    Maksimal 2MB. Wajib rasio 1:1 (persegi) atau portrait (tinggi >= lebar).
                                </p>
                                {#if form.errors.image}
                                    <p class="text-xs text-rose-500 mt-1">
                                        {form.errors.image}
                                    </p>
                                {/if}
                            </div>
                        {/if}

                        <div>
                            <p class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">
                                Kategori Induk (Parent)
                            </p>
                            <select
                                bind:value={form.parent_id}
                                class="h-9 w-full px-3 rounded-lg border border-slate-200 text-sm text-slate-800 focus:border-slate-400 focus:outline-none bg-white transition-colors cursor-pointer"
                            >
                                <option value="">Tidak Ada (Jadikan Kategori Utama)</option>
                                {#each categories as cat}
                                    <option value={cat.id}>{cat.name}</option>
                                {/each}
                            </select>
                            {#if form.errors.parent_id}
                                <p class="text-xs text-rose-500 mt-1">
                                    {form.errors.parent_id}
                                </p>
                            {/if}
                        </div>

                        <div class="pt-2">
                            <button
                                type="submit"
                                disabled={form.processing}
                                class="w-full py-2.5 rounded-lg text-xs font-semibold text-white transition-opacity hover:opacity-90 disabled:opacity-50 flex justify-center items-center gap-1.5 cursor-pointer shadow-xs"
                                style="background-color: {isEditing ? '#f97316' : primary};"
                            >
                                {#if form.processing}
                                    <i class="ti ti-loader animate-spin text-sm"></i> Menyimpan...
                                {:else}
                                    <i class="ti ti-device-floppy text-sm"></i>
                                    {isEditing ? 'Perbarui Kategori' : 'Simpan Kategori'}
                                {/if}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

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
                    Hapus {selectedCategories.length} Kategori Terpilih?
                </h4>
                <p class="text-sm text-center text-slate-500 font-medium mb-8">
                    Data kategori yang terpilih beserta subkategori di bawahnya (jika ada)
                    akan ikut terhapus secara permanen dan tidak dapat dikembalikan.
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
</AdminLayout>
