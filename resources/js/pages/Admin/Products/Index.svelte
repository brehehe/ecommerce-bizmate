<script>
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { Link, useForm, router } from '@inertiajs/svelte';
    import SelectSearchMultiple from '@/components/ui/SelectSearchMultiple.svelte';
    import {
        create as adminProductsCreate,
        edit as adminProductsEdit,
        destroy as adminProductsDestroy,
        toggleActive as adminProductsToggleActive,
    } from '@/routes/admin/products';

    let {
        products = { data: [], links: [], total: 0, from: 0, to: 0 },
        categories = [],
        brands = [],
        filters = { search: '', category: [], brand: [], status: 'all' },
    } = $props();

    let searchInput = $state(filters.search || '');
    let filterCategories = $state(filters.category || []);
    let filterBrands = $state(filters.brand || []);
    let filterStatus = $state(filters.status || 'all');

    let currentViewMode = $state('list'); // 'list' or 'grid'

    let categoryOptions = $derived(
        categories.map((c) => ({ id: c.id, name: c.name })),
    );
    let brandOptions = $derived(
        brands.map((b) => ({ id: b.id, name: b.name })),
    );

    function applyFilters() {
        router.get(
            '/admin/products',
            {
                search: searchInput,
                category: filterCategories,
                brand: filterBrands,
                status: filterStatus,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    // Debounce search
    let searchTimeout;
    function handleSearchInput() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(applyFilters, 300);
    }

    function toggleActive(product) {
        router.post(
            adminProductsToggleActive.url({ product: product.id }),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    // optionally show a toast
                },
            },
        );
    }

    function deleteProduct(product) {
        if (confirm('Apakah Anda yakin ingin menghapus produk ini?')) {
            router.delete(adminProductsDestroy.url({ product: product.id }), {
                preserveScroll: true,
            });
        }
    }

    function getStockInfo(product, masterStockInfo = null) {
        const stockInfo = product.product_stock;
        if (!stockInfo) {
            if (masterStockInfo) {
                if (masterStockInfo.is_unlimited) {
                    return {
                        text: 'Ikut Stok Utama (∞)',
                        color: 'text-slate-500 font-medium bg-slate-100/50 px-2 py-0.5 rounded-md border border-slate-200/60',
                    };
                }
                const mStock = masterStockInfo.stock ?? 0;
                return {
                    text: `Ikut Stok Utama (${mStock})`,
                    color: 'text-slate-500 font-medium bg-slate-100/50 px-2 py-0.5 rounded-md border border-slate-200/60',
                };
            }
            return { text: 'Tidak Set', color: 'text-slate-400 font-medium' };
        }
        if (stockInfo.is_unlimited) {
            return {
                text: '∞ Unlimited',
                color: 'text-emerald-600 font-extrabold bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-100',
            };
        }
        const stock = stockInfo.stock ?? 0;
        const minStock = stockInfo.min_stock ?? 0;
        if (stock === 0) {
            return {
                text: 'Habis',
                color: 'text-rose-500 font-black bg-rose-50 px-2 py-0.5 rounded-md border border-rose-100',
            };
        }
        if (stock <= minStock) {
            return {
                text: `${stock} (Low)`,
                color: 'text-amber-600 font-bold bg-amber-50 px-2 py-0.5 rounded-md border border-amber-100',
            };
        }
        return { text: `${stock}`, color: 'text-slate-600 font-semibold' };
    }

    function getImageUrl(path) {
        if (!path) return 'https://via.placeholder.com/150';
        if (
            path.startsWith('http://') ||
            path.startsWith('https://') ||
            path.startsWith('/')
        )
            return path;
        return '/' + path;
    }

    let expandedProducts = $state(new Set());

    function toggleVariantDrawer(productId) {
        if (expandedProducts.has(productId)) {
            expandedProducts.delete(productId);
        } else {
            expandedProducts.add(productId);
        }
        expandedProducts = new Set(expandedProducts);
    }
</script>

<svelte:head>
    <title>Semua Produk</title>
</svelte:head>

<AdminLayout>
    <div class="flex-grow flex flex-col min-h-screen">
        <main
            class="flex-grow p-4 sm:p-6 xl:p-8 w-full max-w-full mx-auto space-y-6 overflow-hidden"
        >
            <!-- Page Header -->
            <div
                class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4"
            >
                <div>
                    <h3 class="font-outfit font-black text-2xl text-slate-800">
                        Katalog Produk
                    </h3>
                    <p
                        class="text-xs text-slate-400 font-bold uppercase tracking-wider mt-1"
                    >
                        Kelola Stok, Harga, dan Variasi Produk Furniture Anda
                    </p>
                </div>
                <div class="flex items-center gap-2.5 w-full sm:w-auto">
                    <button
                        class="flex-1 sm:flex-none justify-center flex items-center gap-2 px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold rounded-xl text-xs transition"
                    >
                        <i class="ti ti-file-import"></i> Import
                    </button>
                    <Link
                        href={adminProductsCreate.url()}
                        class="flex-1 sm:flex-none justify-center flex items-center gap-2 px-5 py-2.5 bg-brand-blueRoyal hover:bg-blue-800 text-white font-bold rounded-xl text-xs transition shadow-lg shadow-brand-blueRoyal/20 font-outfit uppercase tracking-wider"
                    >
                        <i class="ti ti-plus"></i> Tambah Baru
                    </Link>
                </div>
            </div>

            <!-- Metrics Overview (Mock Data for now, can be updated later from controller stats) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                <div
                    class="bg-white border border-slate-200 rounded-2xl p-4 shadow-soft"
                >
                    <p
                        class="text-[10px] font-bold text-slate-500 uppercase tracking-widest font-outfit mb-1"
                    >
                        Semua Produk
                    </p>
                    <p class="text-2xl font-black text-slate-800">
                        {products.total}
                    </p>
                </div>
                <div
                    class="bg-white border border-slate-200 rounded-2xl p-4 shadow-soft"
                >
                    <p
                        class="text-[10px] font-bold text-slate-500 uppercase tracking-widest font-outfit mb-1"
                    >
                        Aktif & Ditampilkan
                    </p>
                    <p class="text-2xl font-black text-emerald-600">-</p>
                </div>
                <div
                    class="bg-white border border-slate-200 rounded-2xl p-4 shadow-soft"
                >
                    <p
                        class="text-[10px] font-bold text-slate-500 uppercase tracking-widest font-outfit mb-1"
                    >
                        Status Draft
                    </p>
                    <p class="text-2xl font-black text-amber-600">-</p>
                </div>
                <div
                    class="bg-white border border-slate-200 rounded-2xl p-4 shadow-soft"
                >
                    <p
                        class="text-[10px] font-bold text-slate-500 uppercase tracking-widest font-outfit mb-1"
                    >
                        Stok Habis
                    </p>
                    <p class="text-2xl font-black text-red-500">-</p>
                </div>
            </div>

            <!-- Advanced Data Table -->
            <div
                class="bg-white rounded-3xl border border-slate-200 shadow-card flex flex-col"
            >
                <!-- Filters Bar -->
                <div
                    class="p-4 sm:p-6 border-b border-slate-100 flex flex-col xl:flex-row gap-4 justify-between items-stretch xl:items-center bg-slate-50/50 rounded-t-3xl"
                >
                    <div class="flex items-center gap-3 w-full xl:w-auto">
                        <div class="relative w-full xl:w-64">
                            <i
                                class="ti ti-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"
                            ></i>
                            <input
                                type="text"
                                bind:value={searchInput}
                                oninput={handleSearchInput}
                                placeholder="Cari Nama Produk, SKU..."
                                class="w-full pl-10 pr-4 py-2 rounded-xl border border-slate-200 text-sm text-slate-700 focus:border-brand-blueRoyal focus:ring-1 focus:ring-brand-blueRoyal focus:outline-none"
                            />
                        </div>
                    </div>
                    <div
                        class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full xl:w-auto justify-between xl:justify-start"
                    >
                        <div
                            class="grid grid-cols-1 sm:grid-cols-3 gap-3 w-full xl:w-auto min-w-[280px] sm:min-w-[600px] items-center"
                        >
                            <div class="w-full">
                                <SelectSearchMultiple
                                    bind:value={filterCategories}
                                    options={categoryOptions}
                                    placeholder="Semua Kategori"
                                    onchange={applyFilters}
                                />
                            </div>
                            <div class="w-full">
                                <SelectSearchMultiple
                                    bind:value={filterBrands}
                                    options={brandOptions}
                                    placeholder="Semua Brand"
                                    onchange={applyFilters}
                                />
                            </div>
                            <div class="w-full">
                                <select
                                    bind:value={filterStatus}
                                    onchange={applyFilters}
                                    class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-600 focus:outline-none min-h-[44px]"
                                >
                                    <option value="all">Status: Semua</option>
                                    <option value="active">Status: Aktif</option>
                                    <option value="draft">Status: Draft</option>
                                </select>
                            </div>
                        </div>

                        <div
                            class="h-6 w-px bg-slate-200 hidden xl:block"
                        ></div>

                        <!-- View Switcher -->
                        <div
                            class="flex items-center bg-slate-100 p-0.5 rounded-xl border border-slate-200/80 shrink-0 w-full sm:w-auto"
                        >
                            <button
                                onclick={() => (currentViewMode = 'list')}
                                class="flex-1 sm:flex-none justify-center px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5 {currentViewMode ===
                                'list'
                                    ? 'bg-white text-brand-blueRoyal shadow-sm border border-slate-200/50'
                                    : 'text-slate-500 hover:text-slate-800'}"
                                aria-label="Tampilan Daftar"
                            >
                                <i class="ti ti-list text-sm"></i>
                                <span class="hidden sm:inline">Daftar</span>
                            </button>
                            <button
                                onclick={() => (currentViewMode = 'grid')}
                                class="flex-1 sm:flex-none justify-center px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5 {currentViewMode ===
                                'grid'
                                    ? 'bg-white text-brand-blueRoyal shadow-sm border border-slate-200/50'
                                    : 'text-slate-500 hover:text-slate-800'}"
                                aria-label="Tampilan Kartu"
                            >
                                <i class="ti ti-layout-grid text-sm"></i>
                                <span class="hidden sm:inline">Kartu</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Table List View Container -->
                <div
                    class="overflow-x-auto flex-grow custom-scrollbar {currentViewMode ===
                    'grid'
                        ? 'hidden'
                        : ''}"
                >
                    <table
                        class="w-full text-left border-collapse min-w-[900px] xl:min-w-0 table-fixed"
                    >
                        <thead>
                            <tr class="bg-white">
                                <th
                                    class="px-3 xl:px-4 py-4 w-10 border-b border-slate-100"
                                    ><input
                                        type="checkbox"
                                        class="rounded border-slate-300 text-brand-blueRoyal focus:ring-brand-blueRoyal"
                                    /></th
                                >
                                <th
                                    class="px-3 xl:px-4 py-4 w-[32%] text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100"
                                    >Info Produk</th
                                >
                                <th
                                    class="px-3 xl:px-4 py-4 w-[13%] text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100"
                                    >Kategori</th
                                >
                                <th
                                    class="px-3 xl:px-4 py-4 w-[18%] text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100"
                                    >Harga & Stok</th
                                >
                                <th
                                    class="px-3 xl:px-4 py-4 w-[15%] text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100"
                                    >Performa</th
                                >
                                <th
                                    class="px-3 xl:px-4 py-4 w-[10%] text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100 text-center"
                                    >Tampil</th
                                >
                                <th
                                    class="px-3 xl:px-4 py-4 w-[8%] text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100 text-right"
                                    >Aksi</th
                                >
                            </tr>
                        </thead>
                        <tbody
                            class="text-sm text-slate-700 divide-y divide-slate-100"
                        >
                            {#if products.data.length === 0}
                                <tr>
                                    <td
                                        colspan="7"
                                        class="px-6 py-12 text-center text-slate-400 font-medium"
                                    >
                                        <i
                                            class="ti ti-box-off text-4xl block mb-2 opacity-50"
                                        ></i>
                                        Tidak ada produk ditemukan.
                                    </td>
                                </tr>
                            {:else}
                                {#each products.data as product (product.id)}
                                    {@const info = getStockInfo(product)}
                                    {@const hasVariants =
                                        product.variants &&
                                        product.variants.length > 0}
                                    <tr
                                        class="table-row-hover transition {!product.active
                                            ? 'bg-slate-50/30'
                                            : ''}"
                                    >
                                        <td class="px-3 xl:px-4 py-4"
                                            ><input
                                                type="checkbox"
                                                class="rounded border-slate-300 text-brand-blueRoyal"
                                            /></td
                                        >
                                        <td class="px-3 xl:px-4 py-4">
                                            <div
                                                class="flex items-center gap-3 {!product.active
                                                    ? 'opacity-60 grayscale'
                                                    : ''}"
                                            >
                                                {#if hasVariants}
                                                    <button
                                                        onclick={() =>
                                                            toggleVariantDrawer(
                                                                product.id,
                                                            )}
                                                        class="w-7 h-7 rounded-xl bg-slate-50 hover:bg-brand-blueLight hover:text-brand-blueRoyal border border-slate-200/60 hover:border-brand-blueRoyal/20 flex items-center justify-center text-slate-400 transition duration-200 shrink-0 shadow-soft"
                                                        title="Lihat variasi"
                                                    >
                                                        <i
                                                            class="ti ti-chevron-down text-xs transition-transform duration-300 {expandedProducts.has(
                                                                product.id,
                                                            )
                                                                ? 'rotate-180'
                                                                : ''}"
                                                        ></i>
                                                    </button>
                                                {:else}
                                                    <div
                                                        class="w-7 h-7 shrink-0"
                                                    ></div>
                                                {/if}
                                                <img
                                                    class="w-12 h-12 xl:w-14 xl:h-14 rounded-xl border border-slate-200 object-cover bg-slate-50 shrink-0"
                                                    src={getImageUrl(
                                                        product.image,
                                                    )}
                                                    alt={product.name}
                                                />
                                                <div class="min-w-0">
                                                    <Link
                                                        href={adminProductsEdit.url(
                                                            {
                                                                product:
                                                                    product.id,
                                                            },
                                                        )}
                                                        class="font-bold cursor-pointer transition truncate max-w-[220px] xl:max-w-[280px] block {product.active
                                                            ? 'text-slate-800 hover:text-brand-blueRoyal'
                                                            : 'text-slate-400'}"
                                                        title={product.name}
                                                        >{product.name}</Link
                                                    >
                                                    <p
                                                        class="text-[11px] text-slate-500 font-mono mt-0.5"
                                                    >
                                                        SKU Induk: {product.sku}
                                                    </p>
                                                    {#if product.brands && product.brands.length > 0}
                                                        <p class="text-[10px] text-brand-blueRoyal font-bold uppercase tracking-wider mt-0.5">
                                                            Merek: {product.brands.map(b => b.name).join(', ')}
                                                        </p>
                                                    {/if}
                                                    {#if hasVariants}
                                                        <div
                                                            class="flex items-center gap-1.5 mt-1.5"
                                                        >
                                                            <span
                                                                class="inline-flex items-center gap-1 px-2 py-0.5 bg-brand-blueLight text-brand-blueRoyal border border-blue-100 rounded-md text-[9px] font-extrabold uppercase tracking-wider"
                                                            >
                                                                <i
                                                                    class="ti ti-layers-difference text-[10px]"
                                                                ></i>
                                                                <span
                                                                    >{product
                                                                        .variants
                                                                        .length} Varian</span
                                                                >
                                                            </span>
                                                            <span
                                                                class="text-[9px] text-slate-400 font-bold bg-slate-100 px-1.5 py-0.5 rounded border border-slate-200/40 uppercase tracking-wide truncate max-w-[120px]"
                                                                title={(
                                                                    product.variations ||
                                                                    []
                                                                )
                                                                    .map(
                                                                        (v) =>
                                                                            v.name,
                                                                    )
                                                                    .join(', ')}
                                                            >
                                                                {(
                                                                    product.variations ||
                                                                    []
                                                                )
                                                                    .map(
                                                                        (v) =>
                                                                            v.name,
                                                                    )
                                                                    .join(', ')}
                                                            </span>
                                                        </div>
                                                    {/if}
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-3 xl:px-4 py-4">
                                            {#if product.categories && product.categories.length > 0}
                                                <div class="flex flex-wrap gap-1 max-w-[120px]">
                                                    <span
                                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-slate-100 border border-slate-200 rounded-lg text-[10px] font-bold text-slate-600 truncate max-w-[90px]"
                                                        title={product.categories[0].name}
                                                    >
                                                        {product.categories[0].name}
                                                    </span>
                                                    {#if product.categories.length > 1}
                                                        <span
                                                            class="inline-flex items-center px-1.5 py-0.5 bg-brand-blueLight text-brand-blueRoyal border border-blue-100 rounded-lg text-[9px] font-extrabold"
                                                            title={product.categories.map(c => c.name).join(', ')}
                                                        >
                                                            +{product.categories.length - 1}
                                                        </span>
                                                    {/if}
                                                </div>
                                            {:else}
                                                <span class="text-slate-300 font-semibold text-xs">-</span>
                                            {/if}
                                        </td>
                                        <td class="px-3 xl:px-4 py-4">
                                            <div class="flex flex-col gap-0.5">
                                                {#if hasVariants}
                                                    {@const prices =
                                                        product.variants.map(
                                                            (v) =>
                                                                Number(
                                                                    v
                                                                        .product_price
                                                                        ?.price ??
                                                                        v.price ??
                                                                        product
                                                                            .product_price
                                                                            ?.price ??
                                                                        0,
                                                                ),
                                                        )}
                                                    {@const minPrice =
                                                        prices.length
                                                            ? Math.min(
                                                                  ...prices,
                                                              )
                                                            : 0}
                                                    {@const maxPrice =
                                                        prices.length
                                                            ? Math.max(
                                                                  ...prices,
                                                              )
                                                            : 0}
                                                    {@const customStockSum =
                                                        product.variants
                                                            .filter(
                                                                (v) =>
                                                                    v.product_stock,
                                                            )
                                                            .reduce(
                                                                (sum, v) =>
                                                                    sum +
                                                                    (v
                                                                        .product_stock
                                                                        .stock ??
                                                                        0),
                                                                0,
                                                            )}
                                                    {@const hasAnyVariantUsingMasterStock =
                                                        product.variants.some(
                                                            (v) =>
                                                                !v.product_stock,
                                                        )}
                                                    {@const totalStock =
                                                        customStockSum +
                                                        (hasAnyVariantUsingMasterStock
                                                            ? (product
                                                                  .product_stock
                                                                  ?.stock ?? 0)
                                                            : 0)}
                                                    <span
                                                        class="font-black text-slate-800 text-[12px] xl:text-[13px] leading-snug break-words"
                                                    >
                                                        {minPrice === maxPrice
                                                            ? `Rp ${minPrice.toLocaleString('id-ID')}`
                                                            : `Rp ${minPrice.toLocaleString('id-ID')} - Rp ${maxPrice.toLocaleString('id-ID')}`}
                                                    </span>
                                                    <div
                                                        class="flex items-center text-[10.5px] uppercase tracking-wider gap-1 mt-0.5"
                                                    >
                                                        <span
                                                            class="text-slate-400 font-bold"
                                                            >Stok:</span
                                                        >
                                                        <span
                                                            class={totalStock ===
                                                            0
                                                                ? 'text-rose-500 font-black'
                                                                : 'text-slate-600 font-semibold'}
                                                            >{totalStock}</span
                                                        >
                                                    </div>
                                                {:else}
                                                    <span
                                                        class="font-black text-slate-800 text-[12px] xl:text-[13px] leading-snug break-words"
                                                        >Rp {Number(
                                                            product
                                                                .product_price
                                                                ?.price ?? 0,
                                                        ).toLocaleString(
                                                            'id-ID',
                                                        )}</span
                                                    >
                                                    <div
                                                        class="flex items-center text-[10.5px] uppercase tracking-wider gap-1 mt-0.5"
                                                    >
                                                        <span
                                                            class="text-slate-400 font-bold"
                                                            >Stok:</span
                                                        >
                                                        <span class={info.color}
                                                            >{info.text}</span
                                                        >
                                                    </div>
                                                {/if}
                                            </div>
                                        </td>
                                        <td class="px-3 xl:px-4 py-4">
                                            <div class="flex flex-col">
                                                <div
                                                    class="flex items-center gap-1 text-slate-700 font-extrabold text-[13px]"
                                                >
                                                    <i
                                                        class="ti ti-trending-up text-emerald-500"
                                                    ></i>
                                                    <span>0</span>
                                                </div>
                                                <span
                                                    class="text-[10px] text-slate-400 font-bold mt-0.5 uppercase tracking-wide leading-snug"
                                                    >Rp 0</span
                                                >
                                            </div>
                                        </td>
                                        <td
                                            class="px-3 xl:px-4 py-4 text-center"
                                        >
                                            <label
                                                class="relative inline-flex items-center cursor-pointer"
                                                title="Tampilkan/Sembunyikan"
                                            >
                                                <input
                                                    type="checkbox"
                                                    checked={product.active}
                                                    onchange={() =>
                                                        toggleActive(product)}
                                                    class="sr-only peer"
                                                    aria-label={`Aktifkan produk ${product.name}`}
                                                />
                                                <div
                                                    class="w-9 h-5 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-brand-blueRoyal"
                                                ></div>
                                            </label>
                                        </td>
                                        <td
                                            class="px-3 xl:px-4 py-4 text-right whitespace-nowrap"
                                        >
                                            <Link
                                                href={adminProductsEdit.url({
                                                    product: product.id,
                                                })}
                                                class="p-2 text-slate-400 hover:text-brand-blueRoyal rounded-lg transition inline-block"
                                                title="Edit"
                                                ><i class="ti ti-edit"
                                                ></i></Link
                                            >
                                            <button
                                                onclick={() =>
                                                    deleteProduct(product)}
                                                class="p-2 text-slate-400 hover:text-red-500 rounded-lg transition inline-block"
                                                title="Delete"
                                                ><i class="ti ti-trash"
                                                ></i></button
                                            >
                                        </td>
                                    </tr>

                                    {#if hasVariants && expandedProducts.has(product.id)}
                                        {#each product.variants as variant (variant.id)}
                                            {@const vInfo = getStockInfo(
                                                {
                                                    product_stock:
                                                        variant.product_stock,
                                                },
                                                product.product_stock,
                                            )}
                                            {@const variantName =
                                                variant.options
                                                    ? variant.options
                                                          .map((o) => o.name)
                                                          .join(' - ')
                                                    : variant.sku}
                                            <tr
                                                class="bg-slate-50/20 hover:bg-slate-50/50 border-b border-slate-100/80 transition duration-150"
                                            >
                                                <td class="px-3 xl:px-4 py-3"
                                                ></td>
                                                <td
                                                    class="px-3 xl:px-4 py-3 pl-16"
                                                >
                                                    <div
                                                        class="flex items-center gap-3"
                                                    >
                                                        <div
                                                            class="w-3.5 h-3.5 border-l-2 border-b-2 border-slate-200 rounded-bl-md -mt-2.5 shrink-0 ml-1"
                                                        ></div>
                                                        {#if variant.image}
                                                            <img
                                                                class="w-10 h-10 rounded-lg border border-slate-200 object-cover bg-slate-50 shrink-0"
                                                                src={getImageUrl(
                                                                    variant.image,
                                                                )}
                                                                alt={variant.sku}
                                                            />
                                                        {:else}
                                                            <div
                                                                class="w-10 h-10 rounded-lg bg-slate-50 border border-dashed border-slate-200 flex items-center justify-center text-slate-400 shrink-0"
                                                            >
                                                                <i
                                                                    class="ti ti-box text-sm"
                                                                ></i>
                                                            </div>
                                                        {/if}
                                                        <div class="min-w-0">
                                                            <p
                                                                class="font-bold text-xs text-slate-700 truncate max-w-[220px]"
                                                                title={variantName}
                                                            >
                                                                {variantName}
                                                            </p>
                                                            <p
                                                                class="text-[10px] text-slate-400 font-mono mt-0.5"
                                                            >
                                                                Kode Variasi: {variant.sku}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-3 xl:px-4 py-3">
                                                    <span
                                                        class="text-slate-300 font-semibold text-xs"
                                                        >-</span
                                                    >
                                                </td>
                                                <td class="px-3 xl:px-4 py-3">
                                                    <div class="flex flex-col">
                                                        <span
                                                            class="font-extrabold text-xs text-slate-700"
                                                            >Rp {Number(
                                                                variant
                                                                    .product_price
                                                                    ?.price ??
                                                                    product
                                                                        .product_price
                                                                        ?.price ??
                                                                    0,
                                                            ).toLocaleString(
                                                                'id-ID',
                                                            )}</span
                                                        >
                                                        <div
                                                            class="flex items-center text-[10px] uppercase tracking-wider gap-1 mt-0.5"
                                                        >
                                                            <span
                                                                class="text-slate-400 font-bold"
                                                                >Stok:</span
                                                            >
                                                            <span
                                                                class={vInfo.color}
                                                                >{vInfo.text}</span
                                                            >
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-3 xl:px-4 py-3">
                                                    <div class="flex flex-col">
                                                        <div
                                                            class="flex items-center gap-1 text-slate-700 font-extrabold text-[12px]"
                                                        >
                                                            <i
                                                                class="ti ti-trending-up text-emerald-500"
                                                            ></i>
                                                            <span>0</span>
                                                        </div>
                                                        <span
                                                            class="text-[10px] text-slate-400 font-bold mt-0.5 uppercase tracking-wide"
                                                            >Rp 0</span
                                                        >
                                                    </div>
                                                </td>
                                                <td
                                                    class="px-3 xl:px-4 py-3 text-center"
                                                >
                                                    <span
                                                        class="text-slate-300 font-semibold text-xs"
                                                        >-</span
                                                    >
                                                </td>
                                                <td
                                                    class="px-3 xl:px-4 py-3 text-right"
                                                >
                                                    <span
                                                        class="text-slate-300 font-semibold text-xs"
                                                        >-</span
                                                    >
                                                </td>
                                            </tr>
                                        {/each}
                                    {/if}
                                {/each}
                            {/if}
                        </tbody>
                    </table>
                </div>

                <!-- Grid Card View Container -->
                <div
                    class="p-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 bg-slate-50/20 {currentViewMode ===
                    'list'
                        ? 'hidden'
                        : ''}"
                >
                    {#if products.data.length === 0}
                        <div
                            class="col-span-full py-16 text-center text-slate-400 font-medium bg-white rounded-3xl border border-slate-200/60 shadow-soft flex flex-col items-center justify-center w-full"
                        >
                            <i
                                class="ti ti-box-off text-5xl block mb-3 text-slate-300"
                            ></i>
                            <p
                                class="font-outfit font-bold text-slate-700 text-base"
                            >
                                Tidak ada produk ditemukan
                            </p>
                            <p class="text-xs text-slate-400 mt-1">
                                Coba sesuaikan kata kunci pencarian atau filter
                                Anda.
                            </p>
                        </div>
                    {:else}
                        {#each products.data as product (product.id)}
                            {@const info = getStockInfo(product)}
                            {@const hasVariants =
                                product.variants && product.variants.length > 0}
                            <div
                                class="rounded-3xl border border-slate-200 bg-white hover:shadow-card hover:border-slate-300/80 transition-all duration-300 flex flex-col justify-between overflow-hidden relative group p-4 space-y-4"
                            >
                                <!-- Image Container -->
                                <div
                                    class="relative w-full aspect-square rounded-2xl overflow-hidden bg-slate-50 border border-slate-100"
                                >
                                    <img
                                        class="w-full h-full object-cover group-hover:scale-105 transition duration-500 {!product.active
                                            ? 'opacity-60 grayscale'
                                            : ''}"
                                        src={getImageUrl(product.image)}
                                        alt={product.name}
                                    />

                                    {#if product.categories && product.categories.length > 0}
                                        <span
                                            class="absolute top-3 left-3 px-2 py-0.5 bg-white/95 backdrop-blur border border-slate-100 rounded-lg text-[9px] font-black uppercase tracking-wider text-slate-600 shadow-soft"
                                        >
                                            {product.categories[0].name}
                                            {#if product.categories.length > 1}
                                                <span class="text-brand-blueRoyal ml-0.5 font-extrabold text-[8px]">(+{product.categories.length - 1})</span>
                                            {/if}
                                        </span>
                                    {:else}
                                        <span
                                            class="absolute top-3 left-3 px-2 py-0.5 bg-white/95 backdrop-blur border border-slate-100 rounded-lg text-[9px] font-black uppercase tracking-wider text-slate-400 shadow-soft"
                                        >
                                            -
                                        </span>
                                    {/if}

                                    {#if !product.active}
                                        <span
                                            class="absolute top-3 right-3 px-2 py-0.5 bg-slate-100/90 backdrop-blur border border-slate-200 rounded-lg text-[9px] font-extrabold uppercase tracking-wider text-slate-500 shadow-soft"
                                        >
                                            Draft
                                        </span>
                                    {/if}

                                    <span
                                        class="absolute bottom-3 left-3 px-2 py-0.5 border rounded-lg text-[9px] font-black uppercase tracking-wider shadow-soft bg-white/95 backdrop-blur border-slate-100 flex items-center gap-1"
                                    >
                                        <span class="text-slate-400">Stok:</span
                                        >
                                        {#if hasVariants}
                                            {@const customStockSum =
                                                product.variants
                                                    .filter(
                                                        (v) => v.product_stock,
                                                    )
                                                    .reduce(
                                                        (sum, v) =>
                                                            sum +
                                                            (v.product_stock
                                                                .stock ?? 0),
                                                        0,
                                                    )}
                                            {@const hasAnyVariantUsingMasterStock =
                                                product.variants.some(
                                                    (v) => !v.product_stock,
                                                )}
                                            {@const totalStock =
                                                customStockSum +
                                                (hasAnyVariantUsingMasterStock
                                                    ? (product.product_stock
                                                          ?.stock ?? 0)
                                                    : 0)}
                                            <span
                                                class={totalStock === 0
                                                    ? 'text-rose-500 font-extrabold'
                                                    : 'text-emerald-600 font-extrabold'}
                                                >{totalStock}</span
                                            >
                                        {:else}
                                            <span
                                                class={info.color.includes(
                                                    'rose',
                                                )
                                                    ? 'text-rose-500 font-extrabold'
                                                    : info.color.includes(
                                                            'amber',
                                                        )
                                                      ? 'text-amber-600 font-extrabold'
                                                      : info.color.includes(
                                                              'emerald',
                                                          )
                                                        ? 'text-emerald-600 font-extrabold'
                                                        : 'text-slate-500 font-bold'}
                                                >{info.text}</span
                                            >
                                        {/if}
                                    </span>
                                </div>

                                <!-- Info Area -->
                                <div
                                    class="flex-grow flex flex-col justify-between"
                                >
                                    <div>
                                        <div
                                            class="flex items-start justify-between gap-3"
                                        >
                                            <div class="min-w-0 flex-grow">
                                                <Link
                                                    href={adminProductsEdit.url(
                                                        { product: product.id },
                                                    )}
                                                    class="font-outfit font-bold text-sm cursor-pointer transition line-clamp-2 {product.active
                                                        ? 'text-slate-800 hover:text-brand-blueRoyal'
                                                        : 'text-slate-400'}"
                                                    title={product.name}
                                                >
                                                    {product.name}
                                                </Link>
                                                <p
                                                    class="text-[10px] text-slate-400 font-mono mt-1"
                                                >
                                                    {hasVariants
                                                        ? 'SKU Induk'
                                                        : 'SKU'}: {product.sku}
                                                </p>
                                                {#if product.brands && product.brands.length > 0}
                                                    <p class="text-[10px] text-brand-blueRoyal font-bold uppercase tracking-wider mt-0.5">
                                                        {product.brands.map(b => b.name).join(', ')}
                                                    </p>
                                                {/if}
                                            </div>

                                            <!-- Toggle Active Status -->
                                            <label
                                                class="relative inline-flex items-center cursor-pointer shrink-0 animate-fade-in"
                                                title="Tampilkan/Sembunyikan"
                                            >
                                                <input
                                                    type="checkbox"
                                                    checked={product.active}
                                                    onchange={() =>
                                                        toggleActive(product)}
                                                    class="sr-only peer"
                                                    aria-label={`Aktifkan produk ${product.name}`}
                                                />
                                                <div
                                                    class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-brand-blueRoyal"
                                                ></div>
                                            </label>
                                        </div>

                                        <div class="mt-3">
                                            {#if hasVariants}
                                                {@const prices =
                                                    product.variants.map((v) =>
                                                        Number(
                                                            v.product_price
                                                                ?.price ??
                                                                v.price ??
                                                                product
                                                                    .product_price
                                                                    ?.price ??
                                                                0,
                                                        ),
                                                    )}
                                                {@const minPrice = prices.length
                                                    ? Math.min(...prices)
                                                    : 0}
                                                {@const maxPrice = prices.length
                                                    ? Math.max(...prices)
                                                    : 0}
                                                <p
                                                    class="font-outfit font-black text-slate-800 text-[13px] xl:text-[14px] leading-none"
                                                >
                                                    {minPrice === maxPrice
                                                        ? `Rp ${minPrice.toLocaleString('id-ID')}`
                                                        : `Rp ${minPrice.toLocaleString('id-ID')} - Rp ${maxPrice.toLocaleString('id-ID')}`}
                                                </p>
                                                <div
                                                    class="flex flex-col gap-1.5 mt-2.5"
                                                >
                                                    <span
                                                        class="inline-flex items-center gap-1.5 self-start px-2 py-0.5 bg-brand-blueLight text-brand-blueRoyal border border-blue-100 rounded-lg text-[9px] font-black uppercase tracking-wider"
                                                    >
                                                        <i
                                                            class="ti ti-layers-difference text-[10px]"
                                                        ></i>
                                                        <span
                                                            >{product.variants
                                                                .length} Varian</span
                                                        >
                                                    </span>
                                                    <span
                                                        class="text-[9px] text-slate-400 font-bold bg-slate-100 self-start px-2 py-0.5 rounded-lg border border-slate-200/40 truncate max-w-full uppercase tracking-wide"
                                                        title={(
                                                            product.variations ||
                                                            []
                                                        )
                                                            .map((v) => v.name)
                                                            .join(', ')}
                                                    >
                                                        {(
                                                            product.variations ||
                                                            []
                                                        )
                                                            .map((v) => v.name)
                                                            .join(', ')}
                                                    </span>
                                                </div>
                                            {:else}
                                                <p
                                                    class="font-outfit font-black text-slate-800 text-[15px] leading-none"
                                                >
                                                    Rp {Number(
                                                        product.product_price
                                                            ?.price ?? 0,
                                                    ).toLocaleString('id-ID')}
                                                </p>
                                            {/if}
                                        </div>
                                    </div>
                                </div>

                                <!-- Collapsible Variants Area (Grid view) -->
                                {#if hasVariants && expandedProducts.has(product.id)}
                                    <div
                                        class="mt-3 pt-3 border-t border-dashed border-slate-200/80 space-y-2 max-h-48 overflow-y-auto custom-scrollbar"
                                    >
                                        {#each product.variants as variant}
                                            {@const vStockInfo = getStockInfo(
                                                {
                                                    product_stock:
                                                        variant.product_stock,
                                                },
                                                product.product_stock,
                                            )}
                                            {@const variantName =
                                                variant.options
                                                    ? variant.options
                                                          .map((o) => o.name)
                                                          .join(' - ')
                                                    : variant.sku}
                                            <div
                                                class="flex items-start gap-2.5 p-2 bg-slate-50/50 hover:bg-slate-50 border border-slate-100 rounded-xl transition duration-150"
                                            >
                                                {#if variant.image}
                                                    <img
                                                        class="w-9 h-9 rounded-lg border border-slate-200 object-cover bg-white shrink-0"
                                                        src={getImageUrl(
                                                            variant.image,
                                                        )}
                                                        alt={variant.sku}
                                                    />
                                                {:else}
                                                    <div
                                                        class="w-9 h-9 rounded-lg bg-white border border-dashed border-slate-200 flex items-center justify-center text-slate-400 shrink-0"
                                                    >
                                                        <i
                                                            class="ti ti-box text-xs"
                                                        ></i>
                                                    </div>
                                                {/if}
                                                <div class="flex-grow min-w-0">
                                                    <p
                                                        class="font-bold text-xs text-slate-700 truncate"
                                                        title={variantName}
                                                    >
                                                        {variantName}
                                                    </p>
                                                    <p
                                                        class="text-[9px] text-slate-400 font-mono"
                                                    >
                                                        SKU: {variant.sku}
                                                    </p>
                                                    <div
                                                        class="flex items-center justify-between mt-1 text-[10px]"
                                                    >
                                                        <span
                                                            class="font-extrabold text-slate-700"
                                                            >Rp {Number(
                                                                variant
                                                                    .product_price
                                                                    ?.price ??
                                                                    product
                                                                        .product_price
                                                                        ?.price ??
                                                                    0,
                                                            ).toLocaleString(
                                                                'id-ID',
                                                            )}</span
                                                        >
                                                        <span
                                                            class={vStockInfo.color.includes(
                                                                'rose',
                                                            )
                                                                ? 'text-rose-500 font-extrabold'
                                                                : vStockInfo.color.includes(
                                                                        'amber',
                                                                    )
                                                                  ? 'text-amber-600 font-extrabold'
                                                                  : 'text-slate-500 font-bold'}
                                                            >{vStockInfo.text}</span
                                                        >
                                                    </div>
                                                </div>
                                            </div>
                                        {/each}
                                    </div>
                                {/if}

                                <!-- Action Buttons Footer -->
                                <div
                                    class="pt-3 border-t border-slate-100/80 flex items-center justify-between gap-2 shrink-0"
                                >
                                    {#if hasVariants}
                                        <button
                                            onclick={() =>
                                                toggleVariantDrawer(product.id)}
                                            class="px-3 py-1.5 rounded-xl bg-slate-50 hover:bg-brand-blueLight hover:text-brand-blueRoyal border border-slate-200/60 hover:border-brand-blueRoyal/20 flex items-center gap-1 text-slate-500 hover:text-brand-blueRoyal font-extrabold text-xs transition duration-200 shadow-soft"
                                        >
                                            <span>Varian</span>
                                            <i
                                                class="ti ti-chevron-down text-[10px] transition-transform duration-300 {expandedProducts.has(
                                                    product.id,
                                                )
                                                    ? 'rotate-180'
                                                    : ''}"
                                            ></i>
                                        </button>
                                    {/if}
                                    <div
                                        class="flex items-center gap-1.5 ml-auto"
                                    >
                                        <Link
                                            href={adminProductsEdit.url({
                                                product: product.id,
                                            })}
                                            class="w-8 h-8 rounded-xl bg-slate-50 border border-slate-200 hover:bg-brand-blueRoyal/5 hover:text-brand-blueRoyal hover:border-brand-blueRoyal/20 flex items-center justify-center text-slate-500 transition duration-200 shadow-soft"
                                            title="Edit"
                                        >
                                            <i class="ti ti-edit text-sm"></i>
                                        </Link>
                                        <button
                                            onclick={() =>
                                                deleteProduct(product)}
                                            class="w-8 h-8 rounded-xl bg-slate-50 border border-slate-200 hover:bg-red-50 hover:text-red-500 hover:border-red-200 flex items-center justify-center text-slate-500 transition duration-200 shadow-soft"
                                            title="Hapus"
                                        >
                                            <i class="ti ti-trash text-sm"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        {/each}
                    {/if}
                </div>

                <!-- Pagination -->
                <div
                    class="p-4 sm:p-6 border-t border-slate-100 flex flex-col sm:flex-row gap-4 sm:items-center sm:justify-between"
                >
                    <p class="text-xs font-bold text-slate-400">
                        Menampilkan {products.from || 0} - {products.to || 0} dari
                        {products.total} Produk
                    </p>
                    <div class="flex items-center gap-2">
                        {#each products.links as link}
                            <Link
                                href={link.url || '#'}
                                class="w-8 h-8 rounded-lg flex items-center justify-center text-xs font-bold transition {link.active
                                    ? 'bg-brand-blueRoyal text-white shadow-sm'
                                    : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'} {!link.url
                                    ? 'opacity-50 cursor-not-allowed'
                                    : ''}"
                            >
                                {#if link.label.includes('Previous')}
                                    <i class="ti ti-chevron-left text-sm"></i>
                                {:else if link.label.includes('Next')}
                                    <i class="ti ti-chevron-right text-sm"></i>
                                {:else}
                                    {@html link.label}
                                {/if}
                            </Link>
                        {/each}
                    </div>
                </div>
            </div>
        </main>
    </div>
</AdminLayout>
