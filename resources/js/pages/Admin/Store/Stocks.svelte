<script>
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { useForm, page, inertia, router } from '@inertiajs/svelte';
    import Input from '@/components/ui/Input.svelte';
    import Toggle from '@/components/ui/Toggle.svelte';
    import Pagination from '@/components/ui/Pagination.svelte';

    let { products = { data: [], links: [] }, filters = {} } = $props();

    // Keep internal local $state array so inputs are highly reactive and fast
    let localProducts = $state([]);

    $effect(() => {
        localProducts = products.data.map((p) => {
            const hasVariants = p.variants && p.variants.length > 0;
            const variantsData = hasVariants
                ? p.variants.map((v) => {
                      const optionNames = v.options
                          ? v.options.map((o) => o.name).join(' - ')
                          : v.sku;
                      return {
                          id: v.id,
                          name: optionNames || v.sku,
                          sku: v.sku,
                          stock:
                              v.product_stock !== null
                                  ? v.product_stock.stock
                                  : '',
                          min_stock:
                              v.product_stock !== null
                                  ? v.product_stock.min_stock
                                  : '',
                          min_purchase:
                              v.product_stock !== null
                                  ? v.product_stock.min_purchase
                                  : '',
                          is_unlimited:
                              v.product_stock !== null
                                  ? !!v.product_stock.is_unlimited
                                  : false,
                      };
                  })
                : [];

            const hasCustomVariantStock = variantsData.some(
                (v) => v.stock !== '',
            );

            return {
                id: p.id,
                name: p.name,
                category_name: p.category?.name || 'Umum',
                stock: p.product_stock?.stock || 0,
                min_stock: p.product_stock?.min_stock || 0,
                min_purchase: p.product_stock?.min_purchase || 1,
                is_unlimited: !!p.product_stock?.is_unlimited,
                has_variants: hasVariants,
                use_custom: hasCustomVariantStock,
                expanded: false, // accordion state
                variants: variantsData,
            };
        });
    });

    function handleToggleCustom(product) {
        if (!product.use_custom) {
            product.variants.forEach((v) => {
                v.stock = '';
                v.min_stock = '';
                v.min_purchase = '';
                v.is_unlimited = false;
            });
            product.expanded = false;
        } else {
            product.expanded = true;
        }
    }

    // svelte-ignore state_referenced_locally
    let searchQuery = $state(filters.search || '');
    let searchTimeout;

    function handleSearch() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            router.get(
                '/admin/store/stocks',
                { search: searchQuery },
                { preserveState: true, replace: true },
            );
        }, 500);
    }

    // Inertia form submission
    const form = useForm({
        products: [],
    });

    function submit() {
        form.products = $state.snapshot(localProducts);
        form.post('/admin/store/stocks', {
            onSuccess: () => {
                // Success feedback is handled globally
            },
        });
    }
</script>

<svelte:head>
    <title>Store Management: Atur Stok</title>
</svelte:head>

<AdminLayout>
    <div class="flex-grow flex flex-col min-h-screen">
        <main class="flex-grow p-4 sm:p-8 w-full max-w-full mx-auto space-y-6">
            <!-- Page Header -->
            <div
                class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4"
            >
                <div>
                    <h2 class="font-outfit font-black text-2xl text-slate-800">
                        Atur Stok Produk
                    </h2>
                    <p
                        class="text-xs text-slate-400 font-bold uppercase tracking-wider"
                    >
                        Kelola stok, batas minimum (alert), dan minimum
                        pembelian produk & varian secara massal
                    </p>
                </div>
            </div>

            <!-- Navigation Tabs -->
            <div
                class="flex border-b border-slate-200 gap-1 bg-slate-50 p-1.5 rounded-2xl w-fit"
            >
                <a
                    href="/admin/store/prices"
                    use:inertia
                    class="px-5 py-2.5 rounded-xl text-xs font-bold transition flex items-center gap-2 {page.url.startsWith(
                        '/admin/store/prices',
                    )
                        ? 'bg-white text-brand-blueRoyal shadow-sm border border-slate-200/50'
                        : 'text-slate-500 hover:text-slate-800'}"
                >
                    <i class="ti ti-coin text-base"></i>
                    <span>Atur Harga</span>
                </a>
                <a
                    href="/admin/store/stocks"
                    use:inertia
                    class="px-5 py-2.5 rounded-xl text-xs font-bold transition flex items-center gap-2 {page.url.startsWith(
                        '/admin/store/stocks',
                    )
                        ? 'bg-white text-brand-blueRoyal shadow-sm border border-slate-200/50'
                        : 'text-slate-500 hover:text-slate-800'}"
                >
                    <i class="ti ti-building-warehouse text-base"></i>
                    <span>Atur Stok</span>
                </a>
                <a
                    href="/admin/store/shipping"
                    use:inertia
                    class="px-5 py-2.5 rounded-xl text-xs font-bold transition flex items-center gap-2 {page.url.startsWith(
                        '/admin/store/shipping',
                    )
                        ? 'bg-white text-brand-blueRoyal shadow-sm border border-slate-200/50'
                        : 'text-slate-500 hover:text-slate-800'}"
                >
                    <i class="ti ti-truck text-base"></i>
                    <span>Atur Pengiriman</span>
                </a>
            </div>

            <!-- Search Bar Card -->
            <div
                class="bg-white rounded-3xl border border-slate-200 p-4 sm:p-5 shadow-soft flex flex-col sm:flex-row items-center gap-4 justify-between"
            >
                <div class="relative w-full sm:max-w-md">
                    <span
                        class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400"
                    >
                        <i class="ti ti-search text-lg"></i>
                    </span>
                    <input
                        type="text"
                        bind:value={searchQuery}
                        onkeyup={handleSearch}
                        placeholder="Cari nama produk..."
                        class="w-full pl-10 pr-4 py-2.5 text-sm bg-slate-50 border border-slate-200 focus:bg-white focus:ring-2 focus:ring-brand-blueRoyal/20 focus:border-brand-blueRoyal rounded-xl outline-none transition"
                    />
                </div>
                <div
                    class="text-xs font-bold text-slate-400 uppercase tracking-wider"
                >
                    Total: {products.total || 0} Produk
                </div>
            </div>

            {#if localProducts.length === 0}
                <div
                    class="bg-white rounded-3xl border border-slate-200 p-12 text-center shadow-soft"
                >
                    <div
                        class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4 text-slate-300"
                    >
                        <i class="ti ti-package-off text-3xl"></i>
                    </div>
                    <h3 class="text-slate-800 font-bold text-lg mb-1">
                        Produk Tidak Ditemukan
                    </h3>
                    <p class="text-slate-500 text-sm">
                        Coba gunakan kata kunci pencarian yang lain.
                    </p>
                </div>
            {:else}
                <!-- Bulk Stock Form -->
                <form
                    onsubmit={(e) => {
                        e.preventDefault();
                        submit();
                    }}
                    class="space-y-4 pb-24"
                >
                    <!-- Table Container -->
                    <div
                        class="bg-white rounded-3xl border border-slate-200 shadow-soft overflow-hidden"
                    >
                        <table class="w-full text-sm">
                            <thead>
                                <tr
                                    class="bg-slate-50/80 border-b border-slate-200"
                                >
                                    <th
                                        class="text-left px-5 py-3.5 text-[10px] font-black text-slate-400 uppercase tracking-wider w-[35%]"
                                    >
                                        Produk
                                    </th>
                                    <th
                                        class="text-left px-4 py-3.5 text-[10px] font-black text-slate-400 uppercase tracking-wider"
                                    >
                                        Stok Saat Ini
                                    </th>
                                    <th
                                        class="text-left px-4 py-3.5 text-[10px] font-black text-slate-400 uppercase tracking-wider"
                                    >
                                        Batas Min. (Alert)
                                    </th>
                                    <th
                                        class="text-left px-4 py-3.5 text-[10px] font-black text-slate-400 uppercase tracking-wider"
                                    >
                                        Min. Beli
                                    </th>
                                    <th
                                        class="text-left px-4 py-3.5 text-[10px] font-black text-slate-400 uppercase tracking-wider"
                                    >
                                        Opsi
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                {#each localProducts as product (product.id)}
                                    <!-- Master Row -->
                                    <tr
                                        class="hover:bg-slate-50/50 transition-colors group"
                                    >
                                        <!-- Product Name -->
                                        <td class="px-5 py-4">
                                            <div
                                                class="flex items-center gap-3"
                                            >
                                                <div
                                                    class="w-8 h-8 rounded-lg bg-brand-blueRoyal/5 border border-brand-blueRoyal/10 flex items-center justify-center text-brand-blueRoyal shrink-0"
                                                >
                                                    <i class="ti ti-box text-sm"
                                                    ></i>
                                                </div>
                                                <div class="min-w-0">
                                                    <p
                                                        class="font-outfit font-black text-slate-800 text-sm leading-tight truncate"
                                                    >
                                                        {product.name}
                                                    </p>
                                                    <span
                                                        class="px-1.5 py-0.5 bg-slate-100 text-slate-500 font-bold text-[9px] uppercase tracking-wider rounded-md"
                                                    >
                                                        {product.category_name}
                                                    </span>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Stock -->
                                        <td class="px-4 py-4">
                                            {#if !product.has_variants || !product.use_custom}
                                                <Input
                                                    bind:value={product.stock}
                                                    type="number"
                                                    min="0"
                                                    id={`stock-${product.id}`}
                                                    placeholder={product.is_unlimited
                                                        ? '∞ Unlimited'
                                                        : '0'}
                                                    readonly={product.is_unlimited}
                                                />
                                            {:else}
                                                <span
                                                    class="text-[11px] text-slate-400 italic"
                                                    >Per varian</span
                                                >
                                            {/if}
                                        </td>

                                        <!-- Min Stock -->
                                        <td class="px-4 py-4">
                                            {#if !product.has_variants || !product.use_custom}
                                                <Input
                                                    bind:value={
                                                        product.min_stock
                                                    }
                                                    type="number"
                                                    min="0"
                                                    id={`min-stock-${product.id}`}
                                                    placeholder="0"
                                                />
                                            {:else}
                                                <span
                                                    class="text-[11px] text-slate-400 italic"
                                                    >Per varian</span
                                                >
                                            {/if}
                                        </td>

                                        <!-- Min Purchase -->
                                        <td class="px-4 py-4">
                                            {#if !product.has_variants || !product.use_custom}
                                                <Input
                                                    bind:value={
                                                        product.min_purchase
                                                    }
                                                    type="number"
                                                    min="1"
                                                    id={`min-purchase-${product.id}`}
                                                    placeholder="1"
                                                />
                                            {:else}
                                                <span
                                                    class="text-[11px] text-slate-400 italic"
                                                    >Per varian</span
                                                >
                                            {/if}
                                        </td>

                                        <!-- Options -->
                                        <td class="px-4 py-4">
                                            <div
                                                class="flex items-center gap-3 flex-wrap"
                                            >
                                                {#if product.has_variants}
                                                    <!-- Toggle Kustom Varian -->
                                                    <label
                                                        class="flex items-center gap-1.5 cursor-pointer"
                                                        title="Aktifkan stok kustom per varian"
                                                    >
                                                        <div class="relative">
                                                            <input
                                                                type="checkbox"
                                                                bind:checked={
                                                                    product.use_custom
                                                                }
                                                                onchange={() =>
                                                                    handleToggleCustom(
                                                                        product,
                                                                    )}
                                                                class="sr-only peer"
                                                            />
                                                            <div
                                                                class="w-8 h-4 bg-slate-200 rounded-full peer peer-focus:ring-2 peer-focus:ring-brand-blueRoyal/20 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-brand-blueRoyal"
                                                            ></div>
                                                        </div>
                                                        <span
                                                            class="text-[10px] font-bold {product.use_custom
                                                                ? 'text-brand-blueRoyal'
                                                                : 'text-emerald-600'} whitespace-nowrap"
                                                        >
                                                            {product.use_custom
                                                                ? 'Kustom'
                                                                : 'Master'}
                                                        </span>
                                                    </label>

                                                    {#if product.use_custom}
                                                        <button
                                                            type="button"
                                                            onclick={() =>
                                                                (product.expanded =
                                                                    !product.expanded)}
                                                            class="flex items-center gap-1 px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-600 text-[10px] font-bold rounded-lg transition"
                                                        >
                                                            <span
                                                                >{product
                                                                    .variants
                                                                    .length} Varian</span
                                                            >
                                                            <i
                                                                class="ti {product.expanded
                                                                    ? 'ti-chevron-up'
                                                                    : 'ti-chevron-down'} text-xs"
                                                            ></i>
                                                        </button>
                                                    {/if}
                                                {:else}
                                                    <Toggle
                                                        bind:checked={
                                                            product.is_unlimited
                                                        }
                                                        label="Unlimited"
                                                    />
                                                {/if}
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Variant Rows (expanded accordion) -->
                                    {#if product.has_variants && product.use_custom && product.expanded}
                                        <!-- Variant header sub-row -->
                                        <tr class="bg-brand-blueRoyal/3">
                                            <td colspan="5" class="px-5 py-2">
                                                <div
                                                    class="flex items-center justify-between"
                                                >
                                                    <span
                                                        class="text-[10px] font-black text-brand-blueRoyal uppercase tracking-wider flex items-center gap-1.5"
                                                    >
                                                        <i
                                                            class="ti ti-list-details text-xs"
                                                        ></i>
                                                        Stok Per Varian — {product.name}
                                                    </span>
                                                    <Toggle
                                                        bind:checked={
                                                            product.is_unlimited
                                                        }
                                                        label="Semua Unlimited"
                                                    />
                                                </div>
                                            </td>
                                        </tr>
                                        {#each product.variants as variant (variant.id)}
                                            <tr
                                                class="bg-slate-50/60 hover:bg-brand-blueRoyal/2 transition-colors border-l-2 border-brand-blueRoyal/20"
                                            >
                                                <!-- Variant Name -->
                                                <td class="pl-12 pr-4 py-3">
                                                    <div
                                                        class="flex items-center gap-2"
                                                    >
                                                        <div
                                                            class="w-6 h-6 rounded-md bg-slate-200/70 flex items-center justify-center text-slate-400 text-xs shrink-0"
                                                        >
                                                            <i class="ti ti-box"
                                                            ></i>
                                                        </div>
                                                        <div>
                                                            <p
                                                                class="font-bold text-slate-700 text-xs leading-tight"
                                                            >
                                                                {variant.name}
                                                            </p>
                                                            <span
                                                                class="text-[9px] font-bold text-slate-400 uppercase tracking-widest"
                                                                >{variant.sku ||
                                                                    'TANPA SKU'}</span
                                                            >
                                                        </div>
                                                        {#if variant.stock === '' || variant.stock === null}
                                                            <span
                                                                class="ml-1 text-[9px] font-bold text-emerald-600 bg-emerald-50 border border-emerald-200/50 px-1.5 py-0.5 rounded flex items-center gap-0.5"
                                                            >
                                                                <i
                                                                    class="ti ti-info-circle text-[9px]"
                                                                ></i> Ikut Master
                                                            </span>
                                                        {:else}
                                                            <span
                                                                class="ml-1 text-[9px] font-bold text-brand-blueRoyal bg-brand-blueRoyal/5 border border-brand-blueRoyal/20 px-1.5 py-0.5 rounded flex items-center gap-0.5"
                                                            >
                                                                <i
                                                                    class="ti ti-settings text-[9px]"
                                                                ></i> Kustom
                                                            </span>
                                                        {/if}
                                                    </div>
                                                </td>

                                                <!-- Variant Stock -->
                                                <td class="px-4 py-3">
                                                    <Input
                                                        bind:value={
                                                            variant.stock
                                                        }
                                                        type="number"
                                                        min="0"
                                                        id={`v-stock-${variant.id}`}
                                                        readonly={variant.is_unlimited ||
                                                            product.is_unlimited}
                                                        placeholder={variant.is_unlimited ||
                                                        product.is_unlimited
                                                            ? '∞ Unlimited'
                                                            : '0'}
                                                    />
                                                </td>

                                                <!-- Variant Min Stock -->
                                                <td class="px-4 py-3">
                                                    <Input
                                                        bind:value={
                                                            variant.min_stock
                                                        }
                                                        type="number"
                                                        min="0"
                                                        id={`v-min-stock-${variant.id}`}
                                                        placeholder={product.min_stock !==
                                                            '' &&
                                                        product.min_stock !==
                                                            null
                                                            ? product.min_stock.toString()
                                                            : '0'}
                                                    />
                                                </td>

                                                <!-- Variant Min Purchase -->
                                                <td class="px-4 py-3">
                                                    <Input
                                                        bind:value={
                                                            variant.min_purchase
                                                        }
                                                        type="number"
                                                        min="1"
                                                        id={`v-min-purchase-${variant.id}`}
                                                        placeholder={product.min_purchase !==
                                                            '' &&
                                                        product.min_purchase !==
                                                            null
                                                            ? product.min_purchase.toString()
                                                            : '1'}
                                                    />
                                                </td>

                                                <!-- Variant Unlimited Toggle -->
                                                <td class="px-4 py-3">
                                                    <Toggle
                                                        bind:checked={
                                                            variant.is_unlimited
                                                        }
                                                        label="Unlimited"
                                                    />
                                                </td>
                                            </tr>
                                        {/each}
                                    {/if}
                                {/each}
                            </tbody>
                        </table>
                    </div>

                    <div>
                        <Pagination paginator={products} />
                    </div>

                    <!-- Sticky Submit Panel -->
                    <div
                        class="fixed bottom-0 left-0 lg:left-72 right-0 bg-white/80 backdrop-blur-xl border-t border-slate-200 p-4 flex items-center justify-between z-40 px-6 sm:px-8 shadow-lg"
                    >
                        <div
                            class="text-xs font-bold text-slate-400 uppercase tracking-wider"
                        >
                            Perubahan akan diterapkan secara massal
                        </div>
                        <button
                            type="submit"
                            disabled={form.processing}
                            class="px-8 py-3.5 bg-brand-blueRoyal text-white font-bold rounded-xl shadow-lg hover:bg-blue-800 transition flex items-center gap-2"
                        >
                            {#if form.processing}
                                <i class="ti ti-loader animate-spin text-base"
                                ></i>
                                <span>Menyimpan...</span>
                            {:else}
                                <i class="ti ti-device-floppy text-base"></i>
                                <span>Simpan Perubahan</span>
                            {/if}
                        </button>
                    </div>
                </form>
            {/if}
        </main>
    </div>
</AdminLayout>
