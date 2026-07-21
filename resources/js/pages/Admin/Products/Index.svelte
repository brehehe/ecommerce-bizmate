<script>
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { Link, useForm, router, usePage } from '@inertiajs/svelte';
    import Sortable from 'sortablejs';
    import SelectSearchMultiple from '@/components/ui/SelectSearchMultiple.svelte';
    import Pagination from '@/components/ui/Pagination.svelte';
    import Toggle from '@/components/ui/Toggle.svelte';
    import { showToast } from '@/utils/toast';
    import { fade } from 'svelte/transition';

    const page = usePage();
    let globalTaxEnabled = $derived(page.props.settings?.tax_enabled ?? false);
    let globalTaxPercentage = $derived(
        page.props.settings?.tax_percentage ?? 0,
    );
    let secondary = $derived(page.props.theme?.secondary_color ?? '#fa7315');

    let isImportModalOpen = $state(false);
    let importFile = $state(null);
    let previewProducts = $state([]);
    let importTaxEnabled = $state(false);
    let isImporting = $state(false);
    let importError = $state('');

    // Trigger file selection
    function triggerFileSelect() {
        document.getElementById('import-file-input').click();
    }

    // JS CSV Parser
    function parseCSV(text) {
        // Simple delimiter detection
        let delimiter = ',';
        const firstLine = text.split(/\r?\n/)[0] || '';

        // If sep=X line is present, use X as delimiter
        let hasSepLine = false;
        if (firstLine.trim().toLowerCase().startsWith('sep=')) {
            const sepChar = firstLine.trim().charAt(4);
            if (sepChar) {
                delimiter = sepChar;
                hasSepLine = true;
            }
        } else {
            // Auto detect comma vs semicolon based on occurrences in the first line
            const commaCount = (firstLine.match(/,/g) || []).length;
            const semiCount = (firstLine.match(/;/g) || []).length;
            if (semiCount > commaCount) {
                delimiter = ';';
            }
        }

        const lines = [];
        let row = [''];
        let insideQuote = false;
        for (let i = 0; i < text.length; i++) {
            const char = text[i];
            const nextChar = text[i + 1];
            if (char === '"') {
                if (insideQuote && nextChar === '"') {
                    row[row.length - 1] += '"';
                    i++;
                } else {
                    insideQuote = !insideQuote;
                }
            } else if (char === delimiter && !insideQuote) {
                row.push('');
            } else if ((char === '\r' || char === '\n') && !insideQuote) {
                if (char === '\r' && nextChar === '\n') {
                    i++;
                }
                lines.push(row);
                row = [''];
            } else {
                row[row.length - 1] += char;
            }
        }
        if (row.length > 1 || row[0] !== '') {
            lines.push(row);
        }

        // Strip sep= line if present
        if (hasSepLine && lines.length > 0) {
            lines.shift();
        }

        return lines;
    }

    function handleFileChange(e) {
        const file = e.target.files?.[0];
        if (!file) return;
        importFile = file;
        importError = '';

        const reader = new FileReader();
        reader.onload = (event) => {
            try {
                const text = event.target.result;
                const rows = parseCSV(text);
                if (rows.length < 2) {
                    importError = 'File CSV kosong atau tidak valid.';
                    return;
                }

                const headers = rows[0].map((h) => h.trim().toLowerCase());

                // Map header names to indices
                const headerMap = {
                    name: headers.indexOf('nama produk'),
                    sku: headers.indexOf('sku'),
                    categories: headers.indexOf('kategori'),
                    brand: headers.indexOf('brand'),
                    summary: headers.indexOf('ringkasan singkat'),
                    price: headers.indexOf('harga jual'),
                    cost: headers.indexOf('harga modal'),
                    stock: headers.indexOf('stok'),
                    min_stock: headers.indexOf('batas minimum'),
                    min_purchase: headers.indexOf('min pembelian'),
                    weight: headers.indexOf('berat (gram)'),
                    length: headers.indexOf('panjang (cm)'),
                    width: headers.indexOf('lebar (cm)'),
                    height: headers.indexOf('tinggi (cm)'),
                    description: headers.indexOf('deskripsi'),
                    is_digital: headers.indexOf('apakah digital'),
                    is_unlimited: headers.indexOf('apakah unlimited stock'),
                    specifications: headers.indexOf('spesifikasi'),
                    var1_name: headers.indexOf('variasi 1 nama'),
                    var1_val: headers.indexOf('variasi 1 nilai'),
                    var2_name: headers.indexOf('variasi 2 nama'),
                    var2_val: headers.indexOf('variasi 2 nilai'),
                    var_price: headers.indexOf('harga varian'),
                    var_stock: headers.indexOf('stok varian'),
                };

                if (headerMap.name === -1 || headerMap.sku === -1) {
                    importError =
                        'Kolom wajib "Nama Produk" dan "SKU" tidak ditemukan di file CSV.';
                    return;
                }

                const productsMap = {};

                for (let i = 1; i < rows.length; i++) {
                    const row = rows[i];
                    if (row.length < 2 || !row[headerMap.sku]?.trim()) continue;

                    const sku = row[headerMap.sku].trim();
                    const name = row[headerMap.name]?.trim() || '';
                    if (!name) continue;

                    // Parse specifications: "Bahan: Kayu Solid; Tahan Air: Ya"
                    const specRaw =
                        headerMap.specifications !== -1
                            ? row[headerMap.specifications] || ''
                            : '';
                    const specifications = {};
                    if (specRaw.trim()) {
                        const pairs = specRaw.split(';');
                        for (let pIdx = 0; pIdx < pairs.length; pIdx++) {
                            const pair = pairs[pIdx];
                            const parts = pair.split(':');
                            if (parts.length >= 2) {
                                specifications[parts[0].trim()] =
                                    parts[1].trim();
                            }
                        }
                    }

                    if (!productsMap[sku]) {
                        productsMap[sku] = {
                            name: name,
                            sku: sku,
                            category_names:
                                headerMap.categories !== -1
                                    ? row[headerMap.categories] || ''
                                    : '',
                            brand_name:
                                headerMap.brand !== -1
                                    ? row[headerMap.brand] || ''
                                    : '',
                            summary:
                                headerMap.summary !== -1
                                    ? row[headerMap.summary] || ''
                                    : '',
                            price:
                                headerMap.price !== -1
                                    ? parseFloat(row[headerMap.price]) || 0
                                    : 0,
                            cost:
                                headerMap.cost !== -1
                                    ? parseFloat(row[headerMap.cost]) || null
                                    : null,
                            stock:
                                headerMap.stock !== -1
                                    ? parseInt(row[headerMap.stock]) || 0
                                    : 0,
                            min_stock:
                                headerMap.min_stock !== -1
                                    ? parseInt(row[headerMap.min_stock]) || 0
                                    : 0,
                            min_purchase:
                                headerMap.min_purchase !== -1
                                    ? parseInt(row[headerMap.min_purchase]) || 1
                                    : 1,
                            weight:
                                headerMap.weight !== -1
                                    ? parseInt(row[headerMap.weight]) || 0
                                    : 0,
                            length:
                                headerMap.length !== -1
                                    ? parseInt(row[headerMap.length]) || 0
                                    : 0,
                            width:
                                headerMap.width !== -1
                                    ? parseInt(row[headerMap.width]) || 0
                                    : 0,
                            height:
                                headerMap.height !== -1
                                    ? parseInt(row[headerMap.height]) || 0
                                    : 0,
                            description:
                                headerMap.description !== -1
                                    ? row[headerMap.description] ||
                                      'Impor dari CSV'
                                    : 'Impor dari CSV',
                            is_digital:
                                headerMap.is_digital !== -1
                                    ? row[headerMap.is_digital] === '1' ||
                                      row[
                                          headerMap.is_digital
                                      ]?.toLowerCase() === 'ya'
                                    : false,
                            is_unlimited:
                                headerMap.is_unlimited !== -1
                                    ? row[headerMap.is_unlimited] === '1' ||
                                      row[
                                          headerMap.is_unlimited
                                      ]?.toLowerCase() === 'ya'
                                    : false,
                            specifications: specifications,
                            variations: [],
                            variants: [],
                        };
                    }

                    const p = productsMap[sku];

                    // Process variations and variants
                    const var1Name =
                        headerMap.var1_name !== -1
                            ? row[headerMap.var1_name]?.trim()
                            : '';
                    const var1Val =
                        headerMap.var1_val !== -1
                            ? row[headerMap.var1_val]?.trim()
                            : '';
                    const var2Name =
                        headerMap.var2_name !== -1
                            ? row[headerMap.var2_name]?.trim()
                            : '';
                    const var2Val =
                        headerMap.var2_val !== -1
                            ? row[headerMap.var2_val]?.trim()
                            : '';

                    if (var1Name && var1Val) {
                        let v1 = p.variations.find(
                            (v) =>
                                v.name.toLowerCase() === var1Name.toLowerCase(),
                        );
                        if (!v1) {
                            v1 = { name: var1Name, options: [] };
                            p.variations.push(v1);
                        }
                        if (
                            !v1.options.find(
                                (o) =>
                                    o.name.toLowerCase() ===
                                    var1Val.toLowerCase(),
                            )
                        ) {
                            v1.options.push({ id: var1Val, name: var1Val });
                        }

                        let optionComboId = var1Val;

                        if (var2Name && var2Val) {
                            let v2 = p.variations.find(
                                (v) =>
                                    v.name.toLowerCase() ===
                                    var2Name.toLowerCase(),
                            );
                            if (!v2) {
                                v2 = { name: var2Name, options: [] };
                                p.variations.push(v2);
                            }
                            if (
                                !v2.options.find(
                                    (o) =>
                                        o.name.toLowerCase() ===
                                        var2Val.toLowerCase(),
                                )
                            ) {
                                v2.options.push({ id: var2Val, name: var2Val });
                            }
                            optionComboId = `${var1Val}_${var2Val}`;
                        }

                        const varPrice =
                            headerMap.var_price !== -1
                                ? parseFloat(row[headerMap.var_price]) || null
                                : null;
                        const varStock =
                            headerMap.var_stock !== -1
                                ? parseInt(row[headerMap.var_stock]) || null
                                : null;

                        p.variants.push({
                            id: optionComboId,
                            sku: `${sku}-${var1Val.toUpperCase()}${var2Val ? '-' + var2Val.toUpperCase() : ''}`,
                            price: varPrice,
                            cost: p.cost,
                            stock: varStock,
                            is_custom: varPrice !== null || varStock !== null,
                            custom_price: varPrice !== null,
                            custom_stock: varStock !== null,
                        });
                    }
                }

                previewProducts = Object.values(productsMap);
                if (previewProducts.length === 0) {
                    importError =
                        'Tidak ada produk yang valid untuk di-import.';
                }
            } catch (err) {
                console.error(err);
                importError =
                    'Gagal memproses file CSV. Pastikan format file benar.';
            }
        };
        reader.readAsText(file);
    }

    async function submitImport() {
        if (previewProducts.length === 0 || isImporting) return;
        isImporting = true;
        importError = '';

        // Inject tax settings into all products in payload
        const finalPayload = previewProducts.map((p) => {
            return {
                ...p,
                tax_enabled: importTaxEnabled,
            };
        });

        try {
            const response = await fetch('/admin/products/import', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN':
                        document.querySelector('meta[name="csrf-token"]')
                            ?.content || '',
                },
                body: JSON.stringify({ products: finalPayload }),
            });

            if (response.ok) {
                showToast(
                    `${finalPayload.length} produk berhasil di-import.`,
                    'success',
                );
                isImportModalOpen = false;
                importFile = null;
                previewProducts = [];
                router.reload();
            } else {
                const data = await response.json();
                importError = data.message || 'Gagal melakukan import produk.';
            }
        } catch (err) {
            console.error(err);
            importError = 'Terjadi kesalahan sistem saat memproses import.';
        } finally {
            isImporting = false;
        }
    }
    import {
        create as adminProductsCreate,
        edit as adminProductsEdit,
        destroy as adminProductsDestroy,
        toggleActive as adminProductsToggleActive,
        bulkDelete as adminProductsBulkDelete,
        reorder as adminProductsReorder,
    } from '@/routes/admin/products';

    let {
        products = { data: [], links: [], total: 0, from: 0, to: 0 },
        categories = [],
        brands = [],
        filters = { search: '', category: [], brand: [], status: 'all' },
    } = $props();

    // svelte-ignore state_referenced_locally
    let searchInput = $state(filters.search || '');
    // svelte-ignore state_referenced_locally
    let filterCategories = $state(filters.category || []);
    // svelte-ignore state_referenced_locally
    let filterBrands = $state(filters.brand || []);
    // svelte-ignore state_referenced_locally
    let filterStatus = $state(filters.status || 'all');
    // svelte-ignore state_referenced_locally
    let filterSort = $state(filters.sort || 'order-asc');

    let currentViewMode = $state('list'); // 'list' or 'grid'

    // Selection & Modal States
    let selectedProducts = $state([]);
    let deleteBulkModalOpen = $state(false);
    let deleteSingleModalOpen = $state(false);
    let productToDelete = $state(null);
    let submittingBulkDelete = $state(false);
    let submittingSingleDelete = $state(false);

    let selectAll = $derived(
        selectedProducts.length === products.data.length &&
            products.data.length > 0,
    );

    function toggleSelectAll() {
        if (selectAll) {
            selectedProducts = [];
        } else {
            selectedProducts = products.data.map((p) => p.id);
        }
    }

    function toggleSelect(id) {
        if (selectedProducts.includes(id)) {
            selectedProducts = selectedProducts.filter((pId) => pId !== id);
        } else {
            selectedProducts = [...selectedProducts, id];
        }
    }

    function confirmDeleteProduct(product) {
        productToDelete = product;
        deleteSingleModalOpen = true;
    }

    function executeSingleDelete() {
        if (!productToDelete) return;
        submittingSingleDelete = true;
        router.delete(
            adminProductsDestroy.url({ product: productToDelete.id }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    selectedProducts = selectedProducts.filter(
                        (id) => id !== productToDelete.id,
                    );
                    deleteSingleModalOpen = false;
                    productToDelete = null;
                },
                onError: (err) => {
                    const first =
                        Object.values(err)[0] || 'Gagal menghapus produk.';
                    showToast(first, 'error');
                },
                onFinish: () => {
                    submittingSingleDelete = false;
                },
            },
        );
    }

    function executeBulkDelete() {
        if (selectedProducts.length === 0) return;
        submittingBulkDelete = true;

        router.post(
            adminProductsBulkDelete.url(),
            {
                ids: selectedProducts,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    selectedProducts = [];
                    deleteBulkModalOpen = false;
                },
                onError: (err) => {
                    const first =
                        Object.values(err)[0] ||
                        'Gagal menghapus produk terpilih.';
                    showToast(first, 'error');
                },
                onFinish: () => {
                    submittingBulkDelete = false;
                },
            },
        );
    }

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
                sort: filterSort,
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
        confirmDeleteProduct(product);
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
        if (!path) return '/noimage/image.png';
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

    let isReordering = $state(false);

    function saveSortOrder() {
        if (isReordering) return;
        isReordering = true;

        const startOrder = products.from || 1;
        const rows = document.querySelectorAll('.product-row');
        const data = Array.from(rows).map((row, index) => ({
            id: row.dataset.id,
            order: startOrder + index,
        }));

        router.post(
            adminProductsReorder.url(),
            { products: data },
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
    <title>Semua Produk</title>
</svelte:head>

<AdminLayout>
    <main class="w-full max-w-[1600px] mx-auto px-4 sm:px-6 py-6 space-y-5">
        <!-- Page Header -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-semibold tracking-tight text-slate-900">Katalog Produk</h1>
                <p class="mt-0.5 text-sm text-slate-550">Kelola stok, harga, dan variasi produk Anda</p>
            </div>
            <div class="flex items-center gap-2">
                <button
                    onclick={() => (isImportModalOpen = true)}
                    class="h-9 rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-50 flex items-center gap-2 cursor-pointer"
                >
                    <i class="ti ti-file-import"></i> Import
                </button>
                <Link
                    href={adminProductsCreate.url()}
                    class="h-9 rounded-lg px-4 text-sm font-semibold text-white transition-opacity hover:opacity-90 flex items-center gap-2 cursor-pointer"
                    style="background-color: {secondary};"
                >
                    <i class="ti ti-plus"></i> Tambah Baru
                </Link>
            </div>
        </div>

        <!-- Metrics Overview -->
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            <div class="border border-slate-200 bg-white rounded-xl p-4 shadow-xs">
                <p class="text-xs font-semibold text-slate-500 mb-1">Semua Produk</p>
                <p class="text-2xl font-semibold text-slate-900">{products.total}</p>
            </div>
            <div class="border border-slate-200 bg-white rounded-xl p-4 shadow-xs">
                <p class="text-xs font-semibold text-slate-500 mb-1">Aktif &amp; Ditampilkan</p>
                <p class="text-2xl font-semibold text-emerald-600">-</p>
            </div>
            <div class="border border-slate-200 bg-white rounded-xl p-4 shadow-xs">
                <p class="text-xs font-semibold text-slate-500 mb-1">Status Draft</p>
                <p class="text-2xl font-semibold text-amber-600">-</p>
            </div>
            <div class="border border-slate-200 bg-white rounded-xl p-4 shadow-xs">
                <p class="text-xs font-semibold text-slate-500 mb-1">Stok Habis</p>
                <p class="text-2xl font-semibold text-rose-600">-</p>
            </div>
        </div>

        <!-- Advanced Data Table -->
        <div class="border border-slate-200 bg-white rounded-xl flex flex-col overflow-hidden shadow-xs">
            <!-- Filters Bar -->
            <div class="p-4 border-b border-slate-150 flex flex-col xl:flex-row gap-3 justify-between items-stretch xl:items-center bg-slate-50/50">
                <div class="flex-grow flex flex-col sm:flex-row gap-3 items-stretch sm:items-center">
                    <div class="relative flex-1 min-w-[240px]">
                        <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                        <input
                            type="text"
                            bind:value={searchInput}
                            oninput={handleSearchInput}
                            placeholder="Cari nama produk, SKU..."
                            class="h-9 w-full rounded-lg border border-slate-200 bg-white pl-8 pr-3 text-sm text-slate-700 placeholder-slate-400 focus:border-slate-400 focus:outline-none focus:ring-0 transition-colors"
                        />
                    </div>
                    <div class="flex flex-col sm:flex-row gap-2.5 items-stretch sm:items-center">
                        <div class="w-full sm:w-48">
                            <SelectSearchMultiple
                                bind:value={filterCategories}
                                options={categoryOptions}
                                placeholder="Semua Kategori"
                                onchange={applyFilters}
                            />
                        </div>
                        <div class="w-full sm:w-48">
                            <SelectSearchMultiple
                                bind:value={filterBrands}
                                options={brandOptions}
                                placeholder="Semua Brand"
                                onchange={applyFilters}
                            />
                        </div>
                        <div class="w-full sm:w-40">
                            <select
                                bind:value={filterStatus}
                                onchange={applyFilters}
                                class="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 focus:border-slate-400 focus:outline-none transition-colors cursor-pointer"
                            >
                                <option value="all">Status: Semua</option>
                                <option value="active">Status: Aktif</option>
                                <option value="draft">Status: Draft</option>
                            </select>
                        </div>
                        <div class="w-full sm:w-44">
                            <select
                                bind:value={filterSort}
                                onchange={applyFilters}
                                class="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 focus:border-slate-400 focus:outline-none transition-colors cursor-pointer"
                            >
                                <option value="order-asc">Urutan Kustom</option>
                                <option value="name-asc">Nama: A - Z</option>
                                <option value="name-desc">Nama: Z - A</option>
                                <option value="price-asc">Harga: Terendah</option>
                                <option value="price-desc">Harga: Tertinggi</option>
                                <option value="stock-asc">Stok: Terendah</option>
                                <option value="stock-desc">Stok: Tertinggi</option>
                                <option value="latest">Terbaru</option>
                                <option value="oldest">Terlama</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- View Switcher -->
                <div class="flex items-center bg-slate-100 p-0.5 rounded-lg border border-slate-200/80 shrink-0 self-start xl:self-auto">
                    <button
                        onclick={() => (currentViewMode = 'list')}
                        class="px-3 py-1 rounded-md text-xs font-semibold transition flex items-center gap-1.5 {currentViewMode === 'list'
                            ? 'bg-white text-slate-800 shadow-xs border border-slate-200/50'
                            : 'text-slate-500 hover:text-slate-800'}"
                        aria-label="Tampilan Daftar"
                    >
                        <i class="ti ti-list text-sm"></i>
                        <span>Daftar</span>
                    </button>
                    <button
                        onclick={() => (currentViewMode = 'grid')}
                        class="px-3 py-1 rounded-md text-xs font-semibold transition flex items-center gap-1.5 {currentViewMode === 'grid'
                            ? 'bg-white text-slate-800 shadow-xs border border-slate-200/50'
                            : 'text-slate-500 hover:text-slate-800'}"
                        aria-label="Tampilan Kartu"
                    >
                        <i class="ti ti-layout-grid text-sm"></i>
                        <span>Kartu</span>
                    </button>
                </div>
            </div>

            <!-- Bulk Actions Bar -->
            {#if selectedProducts.length > 0}
                <div
                    transition:fade={{ duration: 150 }}
                    class="px-4 py-2.5 bg-blue-50/40 border-b border-slate-150 flex items-center justify-between gap-4 flex-wrap"
                >
                    <span class="text-xs font-semibold text-slate-700">
                        {selectedProducts.length} Produk Terpilih
                    </span>

                    <div class="flex items-center gap-2">
                        <button
                            onclick={() => {
                                selectedProducts = [];
                            }}
                            class="h-7 rounded-md px-3 text-xs font-semibold border border-slate-200 hover:bg-slate-50 text-slate-650 transition-colors bg-white cursor-pointer"
                        >
                            Batal Pilihan
                        </button>
                        <button
                            onclick={() => (deleteBulkModalOpen = true)}
                            class="h-7 rounded-md px-3 text-xs font-semibold bg-red-500 hover:bg-red-600 text-white transition-opacity flex items-center gap-1.5 cursor-pointer"
                        >
                            <i class="ti ti-trash"></i>
                            Hapus Terpilih
                        </button>
                    </div>
                </div>
            {/if}

                <!-- Table List View Container -->
                <div
                    class="overflow-x-auto flex-grow custom-scrollbar {currentViewMode ===
                    'grid'
                        ? 'hidden'
                        : ''}"
                >
                {#key products.data}
                    <table
                        class="w-full responsive-table table-fixed"
                    >
                        <thead>
                            <tr class="bg-white">
                                <th class="px-2 py-4 w-8 border-b border-slate-100"></th>
                                <th
                                    class="px-3 xl:px-4 py-4 w-10 border-b border-slate-100"
                                    ><input
                                        type="checkbox"
                                        checked={selectAll}
                                        onchange={toggleSelectAll}
                                        class="rounded border-slate-300 text-brand-blueRoyal focus:ring-brand-blueRoyal/20 w-4 h-4 cursor-pointer"
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
                            use:sortable={{
                                animation: 150,
                                handle: '.product-drag-handle',
                                draggable: '.product-row',
                                onEnd: saveSortOrder,
                            }}
                        >
                            {#if products.data.length === 0}
                                <tr>
                                    <td
                                        colspan="8"
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
                                        class="product-row table-row-hover transition {!product.active
                                            ? 'bg-slate-50/30'
                                            : ''} {selectedProducts.includes(
                                            product.id,
                                        )
                                            ? 'bg-brand-blueRoyal/5'
                                            : ''}"
                                        data-id={product.id}
                                    >
                                        <td class="px-2 py-4 w-8 text-center">
                                            <span class="text-slate-400 cursor-move product-drag-handle flex items-center justify-center" title="Geser Urutan">
                                                <i class="ti ti-grip-vertical text-base"></i>
                                            </span>
                                        </td>
                                        <td class="px-3 xl:px-4 py-4" data-label="Pilih"
                                            ><input
                                                type="checkbox"
                                                checked={selectedProducts.includes(
                                                    product.id,
                                                )}
                                                onchange={() =>
                                                    toggleSelect(product.id)}
                                                class="rounded border-slate-300 text-brand-blueRoyal focus:ring-brand-blueRoyal/20 w-4 h-4 cursor-pointer"
                                            /></td
                                        >
                                        <td class="px-3 xl:px-4 py-4" data-label="Info Produk">
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
                                                    onerror={(e) => { e.target.src = '/noimage/image.png'; }}
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
                                                        <p
                                                            class="text-[10px] text-brand-blueRoyal font-bold uppercase tracking-wider mt-0.5"
                                                        >
                                                            Merek: {product.brands
                                                                .map(
                                                                    (b) =>
                                                                        b.name,
                                                                )
                                                                .join(', ')}
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
                                        <td class="px-3 xl:px-4 py-4" data-label="Kategori">
                                            {#if product.categories && product.categories.length > 0}
                                                <div
                                                    class="flex flex-wrap gap-1 max-w-[120px]"
                                                >
                                                    <span
                                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-slate-100 border border-slate-200 rounded-lg text-[10px] font-bold text-slate-600 truncate max-w-[90px]"
                                                        title={product
                                                            .categories[0].name}
                                                    >
                                                        {product.categories[0]
                                                            .name}
                                                    </span>
                                                    {#if product.categories.length > 1}
                                                        <span
                                                            class="inline-flex items-center px-1.5 py-0.5 bg-brand-blueLight text-brand-blueRoyal border border-blue-100 rounded-lg text-[9px] font-extrabold"
                                                            title={product.categories
                                                                .map(
                                                                    (c) =>
                                                                        c.name,
                                                                )
                                                                .join(', ')}
                                                        >
                                                            +{product.categories
                                                                .length - 1}
                                                        </span>
                                                    {/if}
                                                </div>
                                            {:else}
                                                <span
                                                    class="text-slate-300 font-semibold text-xs"
                                                    >-</span
                                                >
                                            {/if}
                                        </td>
                                        <td class="px-3 xl:px-4 py-4" data-label="Harga & Stok">
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
                                        <td class="px-3 xl:px-4 py-4" data-label="Performa">
                                            <div class="flex flex-col">
                                                <div
                                                    class="flex items-center gap-1 text-slate-700 font-extrabold text-[13px]"
                                                >
                                                    <i
                                                        class="ti ti-trending-up text-emerald-500"
                                                    ></i>
                                                    <span>{product.performance_sold ?? 0}</span>
                                                </div>
                                                <span
                                                    class="text-[10px] text-slate-400 font-bold mt-0.5 uppercase tracking-wide leading-snug"
                                                    >Rp {Number(product.performance_revenue ?? 0).toLocaleString('id-ID')}</span
                                                >
                                            </div>
                                        </td>
                                        <td
                                            class="px-3 xl:px-4 py-4 text-center" data-label="Tampil"
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
                                            class="px-3 xl:px-4 py-4 text-right whitespace-nowrap" data-label="Aksi"
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
                                                class="variant-row bg-slate-50/20 hover:bg-slate-50/50 border-b border-slate-100/80 transition duration-150"
                                            >
                                                <td class="px-3 xl:px-4 py-3"
                                                ></td>
                                                <td
                                                    class="px-3 xl:px-4 py-3 pl-16"
                                                    data-label="Varian"
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
                                                                onerror={(e) => { e.target.src = '/noimage/image.png'; }}
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
                                                <td class="px-3 xl:px-4 py-3" data-label="Harga & Stok">
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
                                                            <span>{variant.performance_sold ?? 0}</span>
                                                        </div>
                                                        <span
                                                            class="text-[10px] text-slate-400 font-bold mt-0.5 uppercase tracking-wide"
                                                            >Rp {Number(variant.performance_revenue ?? 0).toLocaleString('id-ID')}</span
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
                    </table>
                {/key}
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
                                class="rounded-3xl border bg-white hover:shadow-card hover:border-slate-300/80 transition-all duration-300 flex flex-col justify-between overflow-hidden relative group p-4 space-y-4 {selectedProducts.includes(
                                    product.id,
                                )
                                    ? 'ring-2 ring-brand-blueRoyal/30 border-brand-blueRoyal bg-brand-blueRoyal/[0.01]'
                                    : 'border-slate-200'}"
                            >
                                <!-- Image Container -->
                                <div
                                    class="relative w-full aspect-square rounded-2xl overflow-hidden bg-slate-50 border border-slate-100"
                                >
                                    <input
                                        type="checkbox"
                                        checked={selectedProducts.includes(
                                            product.id,
                                        )}
                                        onchange={() =>
                                            toggleSelect(product.id)}
                                        class="absolute top-3 left-3 z-10 w-5 h-5 rounded-md border-slate-300 text-brand-blueRoyal focus:ring-brand-blueRoyal/20 bg-white/95 shadow-soft cursor-pointer transition-transform duration-200 hover:scale-105 active:scale-95"
                                        aria-label={`Pilih ${product.name}`}
                                    />
                                    <img
                                        class="w-full h-full object-cover group-hover:scale-105 transition duration-500 {!product.active
                                            ? 'opacity-60 grayscale'
                                            : ''}"
                                        src={getImageUrl(product.image)}
                                        onerror={(e) => { e.target.src = '/noimage/image.png'; }}
                                        alt={product.name}
                                    />

                                    {#if product.categories && product.categories.length > 0}
                                        <span
                                            class="absolute top-3 left-10 px-2 py-0.5 bg-white/95 backdrop-blur border border-slate-100 rounded-lg text-[9px] font-black uppercase tracking-wider text-slate-600 shadow-soft"
                                        >
                                            {product.categories[0].name}
                                            {#if product.categories.length > 1}
                                                <span
                                                    class="text-brand-blueRoyal ml-0.5 font-extrabold text-[8px]"
                                                    >(+{product.categories
                                                        .length - 1})</span
                                                >
                                            {/if}
                                        </span>
                                    {:else}
                                        <span
                                            class="absolute top-3 left-10 px-2 py-0.5 bg-white/95 backdrop-blur border border-slate-100 rounded-lg text-[9px] font-black uppercase tracking-wider text-slate-400 shadow-soft"
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
                                                    <p
                                                        class="text-[10px] text-brand-blueRoyal font-bold uppercase tracking-wider mt-0.5"
                                                    >
                                                        {product.brands
                                                            .map((b) => b.name)
                                                            .join(', ')}
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
                                                        onerror={(e) => { e.target.src = '/noimage/image.png'; }}
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
                <Pagination paginator={products} />
            </div>
        </main>

    <!-- Import Modal -->
    {#if isImportModalOpen}
        <div
            class="fixed inset-0 z-50 bg-white flex flex-col overflow-hidden animate-in fade-in duration-200"
            transition:fade={{ duration: 150 }}
        >
            <!-- Header -->
            <div
                class="px-6 py-5 border-b border-slate-100 flex items-center justify-between shrink-0 bg-slate-50/50"
            >
                <div>
                    <h4
                        class="font-outfit font-black text-lg text-slate-800 flex items-center gap-2"
                    >
                        <i
                            class="ti ti-file-import text-brand-blueRoyal text-xl"
                        ></i>
                        Import Katalog Produk
                    </h4>
                    <p
                        class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mt-0.5"
                    >
                        Unggah produk massal via file spreadsheet CSV
                    </p>
                </div>
                <button
                    onclick={() => !isImporting && (isImportModalOpen = false)}
                    class="w-8 h-8 rounded-full flex items-center justify-center border border-slate-200 hover:bg-slate-50 transition cursor-pointer text-slate-400 hover:text-slate-600"
                    disabled={isImporting}
                    aria-label="Tutup"
                >
                    <i class="ti ti-x text-sm"></i>
                </button>
            </div>

            <!-- Body -->
            <div
                class="flex-grow flex flex-col lg:flex-row overflow-y-auto lg:overflow-hidden bg-slate-50"
            >
                <!-- Step 1: Download & Upload (Sidebar Pane) -->
                <div
                    class="w-full lg:w-96 border-b lg:border-b-0 lg:border-r border-slate-200 bg-slate-50 flex flex-col lg:overflow-y-auto p-6 space-y-5 shrink-0"
                >
                    <!-- Download Template Card -->
                    <div
                        class="p-4 bg-white border border-slate-200 rounded-2xl shadow-2xs"
                    >
                        <h5
                            class="text-xs font-black text-slate-700 flex items-center gap-1.5 mb-1.5 font-outfit uppercase tracking-wider"
                        >
                            <i
                                class="ti ti-download text-sm text-brand-blueRoyal"
                            ></i>
                            1. Format Excel / CSV
                        </h5>
                        <p
                            class="text-[11px] text-slate-500 leading-relaxed mb-4"
                        >
                            Gunakan template resmi kami agar format kolom sesuai
                            dan proses impor berhasil tanpa kendala.
                        </p>
                        <a
                            href="/admin/products/import/template"
                            class="w-full justify-center flex items-center gap-2 py-2.5 bg-slate-50 border border-slate-200 hover:bg-slate-100 text-slate-700 font-bold rounded-xl text-xs transition duration-150 cursor-pointer"
                        >
                            <i
                                class="ti ti-file-spreadsheet text-sm text-emerald-600"
                            ></i> Download Template CSV
                        </a>
                    </div>

                    <!-- Upload Area -->
                    <div
                        class="p-4 bg-white border-2 border-dashed border-slate-200 rounded-2xl flex flex-col items-center justify-center text-center shadow-2xs"
                    >
                        <input
                            type="file"
                            id="import-file-input"
                            accept=".csv"
                            class="hidden"
                            onchange={handleFileChange}
                        />
                        <i
                            class="ti ti-cloud-upload text-3xl text-slate-400 mb-2"
                        ></i>
                        <h5
                            class="text-xs font-black text-slate-700 mb-1 font-outfit uppercase tracking-wider"
                        >
                            2. Pilih File CSV
                        </h5>
                        <p
                            class="text-[10px] text-slate-400 max-w-xs leading-normal mb-4"
                        >
                            Seret file ke sini atau klik tombol untuk memilih
                            berkas dari komputer Anda.
                        </p>
                        <button
                            type="button"
                            onclick={triggerFileSelect}
                            class="py-2 px-4 bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold rounded-xl text-xs transition duration-150 cursor-pointer"
                        >
                            Pilih Berkas
                        </button>
                        {#if importFile}
                            <div
                                class="mt-3 flex items-center gap-1.5 text-xs text-brand-blueRoyal bg-blue-50/50 px-2.5 py-1.5 rounded-lg border border-blue-100 max-w-full truncate"
                            >
                                <i class="ti ti-file-check text-sm"></i>
                                <span class="truncate font-semibold"
                                    >{importFile.name}</span
                                >
                            </div>
                        {/if}
                    </div>

                    <!-- Tax Settings Toggle -->
                    {#if globalTaxEnabled}
                        <div
                            class="p-4 bg-white border border-slate-200 rounded-2xl shadow-2xs"
                        >
                            <Toggle
                                bind:checked={importTaxEnabled}
                                label="Belum Termasuk Pajak"
                                description="Aktifkan jika harga dalam file CSV belum ditambah pajak PPN {globalTaxPercentage}%"
                                icon="ti-receipt-tax"
                            />
                        </div>
                    {/if}
                </div>

                <!-- Step 2: Preview Area (Main Workspace Pane) -->
                <div
                    class="flex-grow flex flex-col lg:overflow-y-auto p-6 bg-white space-y-4"
                >
                    <h5
                        class="text-xs font-black text-slate-700 flex items-center gap-1.5"
                    >
                        <i class="ti ti-table text-sm text-slate-500"></i>
                        Pratinjau Produk ({previewProducts.length} Ditemukan)
                    </h5>

                    {#if importError}
                        <div
                            class="p-4 bg-rose-50 border border-rose-100 rounded-2xl flex items-start gap-2.5 text-xs text-rose-700 font-medium"
                        >
                            <i
                                class="ti ti-alert-circle text-lg shrink-0 mt-0.5"
                            ></i>
                            <div>{importError}</div>
                        </div>
                    {/if}

                    {#if previewProducts.length === 0}
                        <div
                            class="border border-slate-200 bg-slate-50/50 rounded-2xl p-6 space-y-4 text-slate-600"
                        >
                            <div
                                class="flex items-center gap-3 border-b border-slate-200 pb-3"
                            >
                                <div
                                    class="w-10 h-10 rounded-full bg-blue-50 text-brand-blueRoyal flex items-center justify-center text-lg shrink-0"
                                >
                                    <i class="ti ti-info-circle"></i>
                                </div>
                                <div>
                                    <h6
                                        class="text-xs font-black text-slate-800"
                                    >
                                        Panduan Pengisian Berkas Impor
                                    </h6>
                                    <p
                                        class="text-[10px] text-slate-400 font-semibold mt-0.5"
                                    >
                                        Harap ikuti pedoman ini agar data produk
                                        tersimpan dengan benar
                                    </p>
                                </div>
                            </div>

                            <ul class="text-[11px] space-y-2.5 list-none pl-0">
                                <li class="flex items-start gap-2">
                                    <span
                                        class="inline-flex w-4 h-4 rounded-full bg-slate-200 text-[10px] font-black items-center justify-center shrink-0 text-slate-700 mt-0.5"
                                        >1</span
                                    >
                                    <span
                                        ><strong
                                            >Kolom Wajib & Informasi Dasar:</strong
                                        >
                                        Kolom
                                        <code
                                            class="px-1.5 py-0.5 bg-slate-100 border border-slate-200 rounded-md text-slate-700 font-mono text-[10px]"
                                            >Nama Produk</code
                                        >,
                                        <code
                                            class="px-1.5 py-0.5 bg-slate-100 border border-slate-200 rounded-md text-slate-700 font-mono text-[10px]"
                                            >SKU</code
                                        >, dan
                                        <code
                                            class="px-1.5 py-0.5 bg-slate-100 border border-slate-200 rounded-md text-slate-700 font-mono text-[10px]"
                                            >Deskripsi</code
                                        >
                                        wajib diisi. Tambahkan penjelasan singkat
                                        pada
                                        <code class="text-slate-700"
                                            >Ringkasan Singkat</code
                                        > untuk ditampilkan di halaman list.</span
                                    >
                                </li>
                                <li class="flex items-start gap-2">
                                    <span
                                        class="inline-flex w-4 h-4 rounded-full bg-slate-200 text-[10px] font-black items-center justify-center shrink-0 text-slate-700 mt-0.5"
                                        >2</span
                                    >
                                    <span
                                        ><strong>Kategori & Brand:</strong>
                                        Pisahkan kategori dengan koma (misal:
                                        <code class="text-slate-700"
                                            >Pakaian, Kaos</code
                                        >). Kategori & Brand baru akan otomatis
                                        dibuat oleh sistem jika belum terdaftar.</span
                                    >
                                </li>
                                <li class="flex items-start gap-2">
                                    <span
                                        class="inline-flex w-4 h-4 rounded-full bg-slate-200 text-[10px] font-black items-center justify-center shrink-0 text-slate-700 mt-0.5"
                                        >3</span
                                    >
                                    <span
                                        ><strong
                                            >Harga, Batas Minimum & Min Beli:</strong
                                        >
                                        Tulis angka murni tanpa titik/koma (misal:
                                        <code class="text-slate-700"
                                            >150000</code
                                        >). Kolom
                                        <code class="text-slate-700"
                                            >Batas Minimum</code
                                        >
                                        untuk pengingat stok menipis dan
                                        <code class="text-slate-700"
                                            >Min Pembelian</code
                                        > untuk jumlah minimum pembelian oleh customer.</span
                                    >
                                </li>
                                <li class="flex items-start gap-2">
                                    <span
                                        class="inline-flex w-4 h-4 rounded-full bg-slate-200 text-[10px] font-black items-center justify-center shrink-0 text-slate-700 mt-0.5"
                                        >4</span
                                    >
                                    <span
                                        ><strong>Spesifikasi & Atribut:</strong>
                                        Tuliskan spesifikasi produk dengan format
                                        pasangan
                                        <code class="text-slate-700"
                                            >Nama: Nilai</code
                                        >
                                        dipisahkan oleh titik koma (contoh:
                                        <code class="text-slate-700"
                                            >Material: Kayu Solid; Tahan Air: Ya</code
                                        >).</span
                                    >
                                </li>
                                <li class="flex items-start gap-2">
                                    <span
                                        class="inline-flex w-4 h-4 rounded-full bg-slate-200 text-[10px] font-black items-center justify-center shrink-0 text-slate-700 mt-0.5"
                                        >5</span
                                    >
                                    <span
                                        ><strong
                                            >Produk Tanpa Variasi (Tunggal):</strong
                                        >
                                        Isi kolom utama
                                        <code class="text-slate-700"
                                            >Harga Jual</code
                                        >,
                                        <code class="text-slate-700"
                                            >Harga Modal</code
                                        >, dan
                                        <code class="text-slate-700">Stok</code
                                        >. Biarkan kolom variasi (Variasi 1
                                        Nama, Variasi 1 Nilai, dll) kosong
                                        (seperti baris <i>Sepeda Gunung</i> pada contoh
                                        tabel).</span
                                    >
                                </li>
                                <li class="flex items-start gap-2">
                                    <span
                                        class="inline-flex w-4 h-4 rounded-full bg-slate-200 text-[10px] font-black items-center justify-center shrink-0 text-slate-700 mt-0.5"
                                        >6</span
                                    >
                                    <span
                                        ><strong
                                            >Produk Dengan Variasi
                                            (Multi-varian):</strong
                                        ></span
                                    >
                                </li>
                            </ul>

                            <div
                                class="pl-6 text-[11px] text-slate-500 space-y-2 border-l-2 border-slate-200 ml-2"
                            >
                                <p>
                                    * Tulis **SKU utama yang sama** di beberapa
                                    baris berturut-turut untuk menyatukan
                                    variasi ke dalam satu produk.
                                </p>
                                <p>
                                    * Isi kolom variasi, misal: <code
                                        class="text-slate-700"
                                        >Variasi 1 Nama: Warna</code
                                    >,
                                    <code class="text-slate-700"
                                        >Variasi 1 Nilai: Merah</code
                                    >,
                                    <code class="text-slate-700"
                                        >Variasi 2 Nama: Ukuran</code
                                    >,
                                    <code class="text-slate-700"
                                        >Variasi 2 Nilai: M</code
                                    >.
                                </p>
                                <p>
                                    * Tentukan <code class="text-slate-700"
                                        >Harga Varian</code
                                    >
                                    dan
                                    <code class="text-slate-700"
                                        >Stok Varian</code
                                    > khusus untuk kombinasi baris tersebut.
                                </p>
                            </div>

                            <!-- Visual Guide Table Preview -->
                            <div class="mt-4 pt-3 border-t border-slate-200">
                                <div
                                    class="flex items-center justify-between mb-2"
                                >
                                    <div
                                        class="text-[11px] font-black text-slate-700 font-outfit uppercase tracking-wider flex items-center gap-1.5"
                                    >
                                        <i
                                            class="ti ti-table-shortcut text-emerald-600"
                                        ></i> Contoh Struktur Tabel Excel/CSV
                                    </div>
                                    <span
                                        class="text-[9px] text-slate-400 font-extrabold bg-slate-100 border border-slate-200 rounded px-2 py-0.5 uppercase tracking-wide"
                                    >
                                        Format 24 Kolom
                                    </span>
                                </div>

                                <!-- Excel-like Spreadsheet Mockup Container -->
                                <div
                                    class="overflow-hidden rounded-xl border border-slate-300 shadow-sm bg-[#f3f2f1]"
                                >
                                    <!-- Excel Title/Tab Bar -->
                                    <div
                                        class="bg-[#107c41] px-3 py-2 text-white flex items-center gap-2"
                                    >
                                        <i
                                            class="ti ti-file-spreadsheet text-base text-emerald-100"
                                        ></i>
                                        <span
                                            class="text-[10px] font-bold tracking-wide font-sans"
                                            >Microsoft Excel -
                                            template_import_produk.csv</span
                                        >
                                    </div>

                                    <!-- Spreadsheet Toolbar Quick Settings -->
                                    <div
                                        class="bg-[#f3f2f1] px-3 py-1.5 border-b border-slate-300 flex items-center gap-4 text-[10px] text-slate-500 font-sans select-none"
                                    >
                                        <div
                                            class="flex items-center gap-2.5 border-r border-slate-300 pr-4"
                                        >
                                            <span
                                                class="font-bold text-slate-700 cursor-pointer"
                                                >File</span
                                            >
                                            <span
                                                class="font-medium hover:text-slate-800 cursor-pointer"
                                                >Beranda</span
                                            >
                                            <span
                                                class="font-medium hover:text-slate-800 cursor-pointer"
                                                >Sisipkan</span
                                            >
                                            <span
                                                class="font-medium hover:text-slate-800 cursor-pointer"
                                                >Tata Letak</span
                                            >
                                            <span
                                                class="font-medium hover:text-slate-800 cursor-pointer"
                                                >Data</span
                                            >
                                        </div>
                                        <div
                                            class="flex items-center gap-2 font-mono text-[9px] text-slate-400 bg-white border border-slate-300 px-2 py-0.5 rounded shadow-2xs"
                                        >
                                            <span
                                                class="text-slate-700 font-bold"
                                                >A1</span
                                            >
                                            <span class="text-slate-300">|</span
                                            >
                                            <span class="text-slate-500"
                                                >fx: Nama Produk</span
                                            >
                                        </div>
                                    </div>

                                    <!-- Scrollable Sheet Table -->
                                    <div
                                        class="overflow-x-auto custom-scrollbar"
                                    >
                                        <table
                                            class="w-full text-[10px] border-collapse bg-white font-mono whitespace-nowrap"
                                        >
                                            <thead>
                                                <!-- Excel Column Letter Headers (A, B, C...) -->
                                                <tr
                                                    class="bg-[#f3f2f1] text-center text-slate-500 font-semibold select-none border-b border-slate-300"
                                                >
                                                    <!-- Top-left corner cell for row numbers -->
                                                    <th
                                                        class="w-10 px-2 py-1 bg-[#e1dfdd] border-r border-b border-slate-300 text-[9px] font-bold text-slate-600"
                                                    ></th>
                                                    <th
                                                        class="px-3 py-1 border-r border-b border-slate-300"
                                                        >A</th
                                                    >
                                                    <th
                                                        class="px-3 py-1 border-r border-b border-slate-300"
                                                        >B</th
                                                    >
                                                    <th
                                                        class="px-3 py-1 border-r border-b border-slate-300"
                                                        >C</th
                                                    >
                                                    <th
                                                        class="px-3 py-1 border-r border-b border-slate-300"
                                                        >D</th
                                                    >
                                                    <th
                                                        class="px-3 py-1 border-r border-b border-slate-300"
                                                        >E</th
                                                    >
                                                    <th
                                                        class="px-3 py-1 border-r border-b border-slate-300"
                                                        >F</th
                                                    >
                                                    <th
                                                        class="px-3 py-1 border-r border-b border-slate-300"
                                                        >G</th
                                                    >
                                                    <th
                                                        class="px-3 py-1 border-r border-b border-slate-300"
                                                        >H</th
                                                    >
                                                    <th
                                                        class="px-3 py-1 border-r border-b border-slate-300"
                                                        >I</th
                                                    >
                                                    <th
                                                        class="px-3 py-1 border-r border-b border-slate-300"
                                                        >J</th
                                                    >
                                                    <th
                                                        class="px-3 py-1 border-r border-b border-slate-300"
                                                        >K</th
                                                    >
                                                    <th
                                                        class="px-3 py-1 border-r border-b border-slate-300"
                                                        >L</th
                                                    >
                                                    <th
                                                        class="px-3 py-1 border-r border-b border-slate-300"
                                                        >M</th
                                                    >
                                                    <th
                                                        class="px-3 py-1 border-r border-b border-slate-300"
                                                        >N</th
                                                    >
                                                    <th
                                                        class="px-3 py-1 border-r border-b border-slate-300"
                                                        >O</th
                                                    >
                                                    <th
                                                        class="px-3 py-1 border-r border-b border-slate-300"
                                                        >P</th
                                                    >
                                                    <th
                                                        class="px-3 py-1 border-r border-b border-slate-300"
                                                        >Q</th
                                                    >
                                                    <th
                                                        class="px-3 py-1 border-r border-b border-slate-300"
                                                        >R</th
                                                    >
                                                    <th
                                                        class="px-3 py-1 border-r border-b border-slate-300"
                                                        >S</th
                                                    >
                                                    <th
                                                        class="px-3 py-1 border-r border-b border-slate-300"
                                                        >T</th
                                                    >
                                                    <th
                                                        class="px-3 py-1 border-r border-b border-slate-300"
                                                        >U</th
                                                    >
                                                    <th
                                                        class="px-3 py-1 border-r border-b border-slate-300"
                                                        >V</th
                                                    >
                                                    <th
                                                        class="px-3 py-1 border-r border-b border-slate-300"
                                                        >W</th
                                                    >
                                                    <th
                                                        class="px-3 py-1 border-b border-slate-300"
                                                        >X</th
                                                    >
                                                </tr>
                                                <!-- Row 1: Header Names -->
                                                <tr
                                                    class="bg-[#f3f2f1] text-slate-700 font-bold border-b border-slate-300 text-[10px]"
                                                >
                                                    <!-- Row header '1' -->
                                                    <td
                                                        class="w-10 px-2 py-1.5 bg-[#e1dfdd] border-r border-b border-slate-300 text-center font-bold text-slate-600 select-none text-[9px]"
                                                        >1</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-b border-slate-300 font-sans text-[#107c41]"
                                                        >Nama Produk</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-b border-slate-300 font-sans text-[#107c41]"
                                                        >SKU</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-b border-slate-300 font-sans"
                                                        >Kategori</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-b border-slate-300 font-sans"
                                                        >Brand</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-b border-slate-300 font-sans"
                                                        >Ringkasan Singkat</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-b border-slate-300 font-sans"
                                                        >Deskripsi</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-b border-slate-300 font-sans"
                                                        >Apakah Digital</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-b border-slate-300 font-sans text-[#107c41]"
                                                        >Harga Jual</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-b border-slate-300 font-sans"
                                                        >Harga Modal</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-b border-slate-300 font-sans"
                                                        >Stok</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-b border-slate-300 font-sans"
                                                        >Batas Minimum</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-b border-slate-300 font-sans"
                                                        >Min Pembelian</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-b border-slate-300 font-sans"
                                                        >Apakah Unlimited Stock</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-b border-slate-300 font-sans"
                                                        >Berat (gram)</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-b border-slate-300 font-sans"
                                                        >Panjang (cm)</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-b border-slate-300 font-sans"
                                                        >Lebar (cm)</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-b border-slate-300 font-sans"
                                                        >Tinggi (cm)</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-b border-slate-300 font-sans"
                                                        >Spesifikasi</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-b border-slate-300 font-sans"
                                                        >Variasi 1 Nama</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-b border-slate-300 font-sans"
                                                        >Variasi 1 Nilai</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-b border-slate-300 font-sans"
                                                        >Variasi 2 Nama</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-b border-slate-300 font-sans"
                                                        >Variasi 2 Nilai</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-b border-slate-300 font-sans"
                                                        >Harga Varian</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-b border-slate-300 font-sans"
                                                        >Stok Varian</td
                                                    >
                                                </tr>
                                            </thead>
                                            <tbody
                                                class="text-slate-600 divide-y divide-[#e1dfdd] text-[10px]"
                                            >
                                                <!-- Row 2: Kaos Combed 30s Red L -->
                                                <tr
                                                    class="hover:bg-slate-50 transition-colors"
                                                >
                                                    <td
                                                        class="w-10 px-2 py-1.5 bg-[#f3f2f1] border-r border-b border-slate-350 text-center font-bold text-slate-600 select-none text-[9px]"
                                                        >2</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] font-sans font-bold text-slate-800"
                                                        >Kaos Combed 30s</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-slate-800 font-semibold"
                                                        >COM-30S-001</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] font-sans"
                                                        >Pakaian Pria, Kaos</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] font-sans"
                                                        >KaosKu</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] font-sans"
                                                        >Kaos combed premium
                                                        super adem.</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] font-sans"
                                                        >Bahan Cotton 30s.</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-center"
                                                        >0</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-right"
                                                        >100000</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-right"
                                                        >70000</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-center"
                                                        >100</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-center"
                                                        >5</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-center"
                                                        >1</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-center"
                                                        >0</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-center"
                                                        >200</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-center"
                                                        >30</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-center"
                                                        >25</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-center"
                                                        >2</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] font-sans"
                                                        >Bahan: Cotton; Gaya:
                                                        Kasual</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] font-sans"
                                                        >Warna</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] font-sans"
                                                        >Merah</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] font-sans"
                                                        >Ukuran</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] font-sans"
                                                        >L</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-right text-slate-800"
                                                        >100000</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 text-center text-slate-800"
                                                        >50</td
                                                    >
                                                </tr>
                                                <!-- Row 3: Kaos Combed 30s Red XL -->
                                                <tr
                                                    class="hover:bg-slate-50 transition-colors"
                                                >
                                                    <td
                                                        class="w-10 px-2 py-1.5 bg-[#f3f2f1] border-r border-b border-slate-350 text-center font-bold text-slate-600 select-none text-[9px]"
                                                        >3</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] font-sans font-bold text-slate-800"
                                                        >Kaos Combed 30s</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-slate-800 font-semibold"
                                                        >COM-30S-001</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] font-sans"
                                                        >Pakaian Pria, Kaos</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] font-sans"
                                                        >KaosKu</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] font-sans"
                                                        >Kaos combed premium
                                                        super adem.</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] font-sans"
                                                        >Bahan Cotton 30s.</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-center"
                                                        >0</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-right"
                                                        >100000</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-right"
                                                        >70000</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-center"
                                                        >100</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-center"
                                                        >5</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-center"
                                                        >1</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-center"
                                                        >0</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-center"
                                                        >200</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-center"
                                                        >30</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-center"
                                                        >25</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-center"
                                                        >2</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] font-sans"
                                                        >Bahan: Cotton; Gaya:
                                                        Kasual</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] font-sans"
                                                        >Warna</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] font-sans"
                                                        >Merah</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] font-sans"
                                                        >Ukuran</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] font-sans"
                                                        >XL</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-right text-emerald-600 font-bold"
                                                        >105000</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 text-center text-slate-800"
                                                        >30</td
                                                    >
                                                </tr>
                                                <!-- Row 4: Kaos Combed 30s Black L -->
                                                <tr
                                                    class="hover:bg-slate-50 transition-colors"
                                                >
                                                    <td
                                                        class="w-10 px-2 py-1.5 bg-[#f3f2f1] border-r border-b border-slate-355 text-center font-bold text-slate-600 select-none text-[9px]"
                                                        >4</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] font-sans font-bold text-slate-800"
                                                        >Kaos Combed 30s</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-slate-800 font-semibold"
                                                        >COM-30S-001</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] font-sans"
                                                        >Pakaian Pria, Kaos</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] font-sans"
                                                        >KaosKu</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] font-sans"
                                                        >Kaos combed premium
                                                        super adem.</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] font-sans"
                                                        >Bahan Cotton 30s.</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-center"
                                                        >0</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-right"
                                                        >100000</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-right"
                                                        >70000</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-center"
                                                        >100</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-center"
                                                        >5</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-center"
                                                        >1</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-center"
                                                        >0</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-center"
                                                        >200</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-center"
                                                        >30</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-center"
                                                        >25</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-center"
                                                        >2</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] font-sans"
                                                        >Bahan: Cotton; Gaya:
                                                        Kasual</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] font-sans"
                                                        >Warna</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] font-sans"
                                                        >Hitam</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] font-sans"
                                                        >Ukuran</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] font-sans"
                                                        >L</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-right text-slate-800"
                                                        >100000</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 text-center text-slate-800"
                                                        >20</td
                                                    >
                                                </tr>
                                                <!-- Row 5: Sepeda Gunung (Tunggal) -->
                                                <tr
                                                    class="hover:bg-slate-50 transition-colors"
                                                >
                                                    <td
                                                        class="w-10 px-2 py-1.5 bg-[#f3f2f1] border-r border-b border-slate-360 text-center font-bold text-slate-600 select-none text-[9px]"
                                                        >5</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] font-sans font-bold text-slate-800"
                                                        >Sepeda Gunung</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-slate-800 font-semibold"
                                                        >BIKE-MTB-001</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] font-sans"
                                                        >Sepeda, Olahraga</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] font-sans"
                                                        >Polygon</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] font-sans"
                                                        >Sepeda gunung Polygon
                                                        tangguh.</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] font-sans"
                                                        >Sepeda gunung dual
                                                        suspension.</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-center"
                                                        >0</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-right"
                                                        >3500000</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-right"
                                                        >2500000</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-center"
                                                        >10</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-center"
                                                        >2</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-center"
                                                        >1</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-center"
                                                        >0</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-center"
                                                        >15000</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-center"
                                                        >140</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-center"
                                                        >20</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-center"
                                                        >80</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] font-sans"
                                                        >Frame: AluxX; Fork: SR
                                                        Suntour</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-center text-slate-300"
                                                        >-</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-center text-slate-300"
                                                        >-</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-center text-slate-300"
                                                        >-</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-center text-slate-300"
                                                        >-</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 border-r border-[#e1dfdd] text-center text-slate-300"
                                                        >-</td
                                                    >
                                                    <td
                                                        class="px-3 py-1.5 text-center text-slate-300"
                                                        >-</td
                                                    >
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    {:else}
                        <div
                            class="border border-slate-200 rounded-2xl overflow-x-auto bg-white shadow-2xs custom-scrollbar"
                        >
                            <table
                                class="w-full text-left border-collapse text-xs"
                            >
                                <thead>
                                    <tr
                                        class="bg-slate-50 border-b border-slate-100 text-[10px] font-bold text-slate-500 uppercase"
                                    >
                                        <th class="p-3">Nama / SKU</th>
                                        <th class="p-3">Kategori / Brand</th>
                                        <th class="p-3 text-right"
                                            >Harga Jual</th
                                        >
                                        {#if globalTaxEnabled && importTaxEnabled}
                                            <th
                                                class="p-3 text-right text-rose-500"
                                                >PPN ({globalTaxPercentage}%)</th
                                            >
                                            <th
                                                class="p-3 text-right text-brand-blueRoyal"
                                                >Total</th
                                            >
                                        {/if}
                                        <th class="p-3 text-center">Stok</th>
                                        <th class="p-3 text-center">Varian</th>
                                    </tr>
                                </thead>
                                <tbody
                                    class="divide-y divide-slate-100 text-slate-700"
                                >
                                    {#each previewProducts as p}
                                        <tr
                                            class="bg-white hover:bg-slate-50/30 transition-colors"
                                        >
                                            <td class="p-3">
                                                <div
                                                    class="font-outfit font-black text-slate-800 text-xs"
                                                >
                                                    {p.name}
                                                </div>
                                                <div
                                                    class="text-[10px] text-slate-400 font-mono mt-0.5"
                                                >
                                                    {p.sku}
                                                </div>
                                                {#if p.summary}
                                                    <div
                                                        class="text-[10px] text-slate-400 italic mt-1 max-w-[200px] truncate"
                                                        title={p.summary}
                                                    >
                                                        {p.summary}
                                                    </div>
                                                {/if}
                                                <div
                                                    class="flex items-center gap-1.5 mt-1.5 flex-wrap"
                                                >
                                                    {#if p.is_digital}
                                                        <span
                                                            class="inline-flex items-center px-1.5 py-0.5 bg-indigo-50 text-indigo-600 text-[8px] font-black rounded-md border border-indigo-100 uppercase tracking-wide"
                                                        >
                                                            <i
                                                                class="ti ti-device-laptop mr-0.5"
                                                            ></i> Digital
                                                        </span>
                                                    {/if}
                                                    {#if p.is_unlimited}
                                                        <span
                                                            class="inline-flex items-center px-1.5 py-0.5 bg-amber-50 text-amber-600 text-[8px] font-black rounded-md border border-amber-100 uppercase tracking-wide"
                                                        >
                                                            <i
                                                                class="ti ti-infinity mr-0.5"
                                                            ></i> Unlimited Stock
                                                        </span>
                                                    {/if}
                                                </div>
                                            </td>
                                            <td class="p-3">
                                                <div
                                                    class="text-[10px] text-slate-500 font-semibold"
                                                >
                                                    {p.category_names || '-'}
                                                </div>
                                                <div
                                                    class="text-[10px] text-slate-400 mt-0.5"
                                                >
                                                    {p.brand_name || '-'}
                                                </div>
                                            </td>
                                            <td
                                                class="p-3 text-right font-bold font-mono"
                                            >
                                                Rp {p.price.toLocaleString(
                                                    'id-ID',
                                                )}
                                                {#if p.cost}
                                                    <div
                                                        class="text-[9px] text-slate-400 font-normal mt-0.5"
                                                    >
                                                        HPP: Rp {p.cost.toLocaleString(
                                                            'id-ID',
                                                        )}
                                                    </div>
                                                {/if}
                                            </td>
                                            {#if globalTaxEnabled && importTaxEnabled}
                                                {@const ppn =
                                                    (p.price *
                                                        globalTaxPercentage) /
                                                    100}
                                                {@const total = p.price + ppn}
                                                <td
                                                    class="p-3 text-right text-rose-500 font-bold font-mono"
                                                >
                                                    +Rp {ppn.toLocaleString(
                                                        'id-ID',
                                                    )}
                                                </td>
                                                <td
                                                    class="p-3 text-right text-brand-blueRoyal font-black font-mono"
                                                >
                                                    Rp {total.toLocaleString(
                                                        'id-ID',
                                                    )}
                                                </td>
                                            {/if}
                                            <td class="p-3 text-center">
                                                <div
                                                    class="font-bold font-mono"
                                                >
                                                    {p.is_unlimited
                                                        ? '∞'
                                                        : p.stock}
                                                </div>
                                                {#if p.min_stock > 0 || p.min_purchase > 1}
                                                    <div
                                                        class="text-[9px] text-slate-400 font-normal mt-0.5"
                                                    >
                                                        {#if p.min_stock > 0}Min
                                                            Alert: {p.min_stock}{/if}
                                                        {#if p.min_stock > 0 && p.min_purchase > 1}
                                                            |
                                                        {/if}
                                                        {#if p.min_purchase > 1}Min
                                                            Beli: {p.min_purchase}{/if}
                                                    </div>
                                                {/if}
                                            </td>
                                            <td class="p-3 text-center">
                                                {#if p.variants.length > 0}
                                                    <span
                                                        class="inline-flex px-2 py-0.5 bg-blue-50 text-brand-blueRoyal text-[10px] font-black rounded-md border border-blue-100"
                                                    >
                                                        {p.variants.length} Varian
                                                    </span>
                                                {:else}
                                                    <span
                                                        class="text-slate-400 font-semibold"
                                                        >-</span
                                                    >
                                                {/if}
                                            </td>
                                        </tr>
                                        {#if (p.specifications && Object.keys(p.specifications).length > 0) || p.variants.length > 0}
                                            <tr class="bg-slate-50/50">
                                                <td
                                                    colspan={globalTaxEnabled &&
                                                    importTaxEnabled
                                                        ? 7
                                                        : 5}
                                                    class="px-5 py-3 border-t border-slate-100/50"
                                                >
                                                    <div class="space-y-3">
                                                        <!-- Specifications list -->
                                                        {#if p.specifications && Object.keys(p.specifications).length > 0}
                                                            <div>
                                                                <div
                                                                    class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5 flex items-center gap-1"
                                                                >
                                                                    <i
                                                                        class="ti ti-list text-slate-500"
                                                                    ></i> Spesifikasi
                                                                    Produk:
                                                                </div>
                                                                <div
                                                                    class="flex flex-wrap gap-1.5"
                                                                >
                                                                    {#each Object.entries(p.specifications) as [name, value]}
                                                                        <span
                                                                            class="inline-flex items-center gap-1 px-2 py-1 bg-white border border-slate-200/80 rounded-lg text-[10px] text-slate-600 shadow-2xs"
                                                                        >
                                                                            <strong
                                                                                class="text-slate-700"
                                                                                >{name}:</strong
                                                                            >
                                                                            {value}
                                                                        </span>
                                                                    {/each}
                                                                </div>
                                                            </div>
                                                        {/if}

                                                        <!-- Variants combination details -->
                                                        {#if p.variants.length > 0}
                                                            <div>
                                                                <div
                                                                    class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2 flex items-center gap-1"
                                                                >
                                                                    <i
                                                                        class="ti ti-git-branch text-slate-500"
                                                                    ></i> Detail Kombinasi
                                                                    Varian:
                                                                </div>
                                                                <div
                                                                    class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2"
                                                                >
                                                                    {#each p.variants as v}
                                                                        <div
                                                                            class="bg-white border border-slate-200/80 rounded-xl p-2.5 flex flex-col justify-between shadow-2xs hover:border-slate-300 transition-colors"
                                                                        >
                                                                            <div
                                                                            >
                                                                                <span
                                                                                    class="text-[9px] font-black text-slate-700 bg-slate-100 px-1.5 py-0.5 rounded-md border border-slate-200/50"
                                                                                >
                                                                                    {v.id.replace(
                                                                                        '_',
                                                                                        ' / ',
                                                                                    )}
                                                                                </span>
                                                                                <div
                                                                                    class="text-[9px] font-mono text-slate-400 mt-1.5 truncate"
                                                                                    title={v.sku}
                                                                                >
                                                                                    {v.sku}
                                                                                </div>
                                                                            </div>
                                                                            <div
                                                                                class="mt-2.5 pt-1.5 border-t border-slate-100 flex justify-between items-center text-[10px]"
                                                                            >
                                                                                <span
                                                                                    class="text-slate-500 font-medium"
                                                                                    >Stok:
                                                                                    <strong
                                                                                        >{v.stock !==
                                                                                        null
                                                                                            ? v.stock
                                                                                            : p.stock}</strong
                                                                                    ></span
                                                                                >
                                                                                <span
                                                                                    class="font-bold font-mono text-slate-800"
                                                                                >
                                                                                    Rp
                                                                                    {(
                                                                                        (v.price !==
                                                                                        null
                                                                                            ? v.price
                                                                                            : p.price) +
                                                                                        (globalTaxEnabled &&
                                                                                        importTaxEnabled
                                                                                            ? ((v.price !==
                                                                                              null
                                                                                                  ? v.price
                                                                                                  : p.price) *
                                                                                                  globalTaxPercentage) /
                                                                                              100
                                                                                            : 0)
                                                                                    ).toLocaleString(
                                                                                        'id-ID',
                                                                                    )}
                                                                                </span>
                                                                            </div>
                                                                        </div>
                                                                    {/each}
                                                                </div>
                                                            </div>
                                                        {/if}
                                                    </div>
                                                </td>
                                            </tr>
                                        {/if}
                                    {/each}
                                </tbody>
                            </table>
                        </div>
                    {/if}
                </div>
            </div>

            <!-- Footer -->
            <div
                class="px-6 py-4 border-t border-slate-100 bg-slate-50/50 flex items-center justify-between shrink-0"
            >
                <div
                    class="text-[10px] text-slate-400 font-semibold leading-normal font-outfit"
                >
                    * Data dengan SKU yang sama akan otomatis memperbarui produk
                    yang sudah ada (updateOrCreate).
                </div>
                <div class="flex items-center gap-3">
                    <button
                        onclick={() =>
                            !isImporting && (isImportModalOpen = false)}
                        class="py-2.5 px-4 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition duration-150 cursor-pointer"
                        disabled={isImporting}
                    >
                        Batal
                    </button>
                    <button
                        onclick={submitImport}
                        class="py-2.5 px-5 bg-brand-blueRoyal hover:bg-blue-800 text-white font-bold rounded-xl text-xs transition duration-150 shadow-md shadow-brand-blueRoyal/10 cursor-pointer flex items-center gap-1.5"
                        disabled={previewProducts.length === 0 || isImporting}
                    >
                        {#if isImporting}
                            <i class="ti ti-loader animate-spin text-sm"></i> Memproses...
                        {:else}
                            <i class="ti ti-check text-sm"></i> Proses Import
                        {/if}
                    </button>
                </div>
            </div>
        </div>
    {/if}

    <!-- Single Delete Confirmation Modal -->
    {#if deleteSingleModalOpen}
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div
                class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity"
                onclick={() => (deleteSingleModalOpen = false)}
                onkeypress={() => (deleteSingleModalOpen = false)}
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
                    Hapus Produk?
                </h4>
                <p class="text-sm text-center text-slate-550 font-medium mb-8">
                    Produk <strong>{productToDelete?.name}</strong> akan dihapus secara
                    permanen dari sistem. Tindakan ini tidak dapat dibatalkan.
                </p>
                <div class="flex items-center gap-3">
                    <button
                        onclick={() => (deleteSingleModalOpen = false)}
                        class="flex-1 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-sm transition cursor-pointer"
                    >
                        Batal
                    </button>
                    <button
                        onclick={executeSingleDelete}
                        disabled={submittingSingleDelete}
                        class="flex-1 py-3 bg-red-500 hover:bg-red-600 text-white font-bold rounded-xl text-sm shadow-lg shadow-red-500/30 transition cursor-pointer disabled:opacity-50"
                    >
                        {submittingSingleDelete ? 'Memproses...' : 'Ya, Hapus'}
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
                    Hapus {selectedProducts.length} Produk Terpilih?
                </h4>
                <p class="text-sm text-center text-slate-550 font-medium mb-8">
                    Apakah Anda yakin ingin menghapus <strong
                        >{selectedProducts.length} produk</strong
                    > yang terpilih secara permanen dari sistem? Tindakan ini tidak
                    dapat dibatalkan.
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
                        {submittingBulkDelete
                            ? 'Memproses...'
                            : 'Ya, Hapus Semua'}
                    </button>
                </div>
            </div>
        </div>
    {/if}
</AdminLayout>
