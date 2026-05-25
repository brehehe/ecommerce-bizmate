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
                          weight: v.weight !== null ? v.weight : '',
                          length: v.length !== null ? v.length : '',
                          width: v.width !== null ? v.width : '',
                          height: v.height !== null ? v.height : '',
                      };
                  })
                : [];

            const hasCustomVariantShipping = variantsData.some(
                (v) =>
                    v.weight !== '' ||
                    v.length !== '' ||
                    v.width !== '' ||
                    v.height !== '',
            );

            return {
                id: p.id,
                name: p.name,
                category_name: p.category?.name || 'Umum',
                weight: p.weight || 0,
                length: p.length || 0,
                width: p.width || 0,
                height: p.height || 0,
                has_variants: hasVariants,
                use_custom: hasCustomVariantShipping,
                expanded: false, // accordion state
                variants: variantsData,
            };
        });
    });

    function handleToggleCustom(product) {
        if (!product.use_custom) {
            product.variants.forEach((v) => {
                v.weight = '';
                v.length = '';
                v.width = '';
                v.height = '';
            });
            product.expanded = false;
        } else {
            product.expanded = true;
        }
    }

    let searchQuery = $state(filters.search || '');
    let searchTimeout;

    function handleSearch() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            router.get(
                '/admin/store/shipping',
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
        form.post('/admin/store/shipping', {
            onSuccess: () => {
                // Success feedback is handled globally
            },
        });
    }
</script>

<svelte:head>
    <title>Store Management: Atur Pengiriman & Dimensi</title>
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
                        Atur Pengiriman & Dimensi Produk
                    </h2>
                    <p
                        class="text-xs text-slate-400 font-bold uppercase tracking-wider"
                    >
                        Kelola berat pengiriman dan dimensi fisik (panjang,
                        lebar, tinggi) produk & varian secara massal
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
                class="bg-white rounded-3xl border border-slate-200 p-4 sm:p-6 shadow-soft flex flex-col sm:flex-row items-center gap-4 justify-between"
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
                <!-- Bulk Shipping Form -->
                <form
                    onsubmit={(e) => {
                        e.preventDefault();
                        submit();
                    }}
                    class="space-y-4 pb-24"
                >
                    {#each localProducts as product (product.id)}
                        <div
                            class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-soft transition-all"
                        >
                            <!-- Product Row Header -->
                            <div
                                class="p-5 sm:p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-slate-50/40"
                            >
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-10 h-10 rounded-xl bg-brand-blueRoyal/5 border border-brand-blueRoyal/10 flex items-center justify-center text-brand-blueRoyal shrink-0"
                                    >
                                        <i class="ti ti-box text-xl"></i>
                                    </div>
                                    <div>
                                        <h4
                                            class="font-outfit font-black text-slate-800 text-base"
                                        >
                                            {product.name}
                                        </h4>
                                        <span
                                            class="px-2 py-0.5 bg-slate-100 text-slate-500 font-bold text-[9px] uppercase tracking-wider rounded-md"
                                            >{product.category_name}</span
                                        >
                                    </div>
                                </div>

                                <div class="flex items-center gap-4">
                                    {#if product.has_variants}
                                        <!-- Centralized Toggle for Custom vs Master -->
                                        <div
                                            class="flex items-center gap-2 bg-white border border-slate-200 rounded-xl px-3 py-1.5 shadow-sm"
                                        >
                                            <span
                                                class="text-xs font-bold text-slate-500"
                                                >Pengiriman Varian:</span
                                            >
                                            <label
                                                class="relative inline-flex items-center cursor-pointer"
                                            >
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
                                                    class="w-9 h-5 bg-slate-200 rounded-full peer peer-focus:ring-2 peer-focus:ring-brand-blueRoyal/20 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-brand-blueRoyal"
                                                ></div>
                                            </label>
                                            <span
                                                class="text-xs font-bold {product.use_custom
                                                    ? 'text-brand-blueRoyal'
                                                    : 'text-emerald-600'}"
                                            >
                                                {product.use_custom
                                                    ? 'Kustom'
                                                    : 'Ikut Master'}
                                            </span>
                                        </div>

                                        {#if product.use_custom}
                                            <button
                                                type="button"
                                                onclick={() =>
                                                    (product.expanded =
                                                        !product.expanded)}
                                                class="px-4 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-50 flex items-center gap-2 transition"
                                            >
                                                <span
                                                    >{product.variants.length} Varian</span
                                                >
                                                <i
                                                    class="ti {product.expanded
                                                        ? 'ti-chevron-up'
                                                        : 'ti-chevron-down'} text-sm"
                                                ></i>
                                            </button>
                                        {/if}
                                    {/if}
                                </div>
                            </div>

                            <!-- Product Fields (Only if NO variants OR if variants are inheriting master shipping/dimensions) -->
                            {#if !product.has_variants || !product.use_custom}
                                <div
                                    class="p-5 sm:p-6 border-t border-slate-100 grid grid-cols-2 md:grid-cols-4 gap-6"
                                >
                                    <Input
                                        bind:value={product.weight}
                                        type="number"
                                        min="0"
                                        id={`weight-${product.id}`}
                                        label="Berat"
                                        prefix="Gram"
                                        placeholder="0"
                                    />
                                    <Input
                                        bind:value={product.length}
                                        type="number"
                                        min="0"
                                        id={`length-${product.id}`}
                                        label="Panjang"
                                        prefix="Cm"
                                        placeholder="0"
                                    />
                                    <Input
                                        bind:value={product.width}
                                        type="number"
                                        min="0"
                                        id={`width-${product.id}`}
                                        label="Lebar"
                                        prefix="Cm"
                                        placeholder="0"
                                    />
                                    <Input
                                        bind:value={product.height}
                                        type="number"
                                        min="0"
                                        id={`height-${product.id}`}
                                        label="Tinggi"
                                        prefix="Cm"
                                        placeholder="0"
                                    />
                                </div>
                            {/if}

                            <!-- Accordion Varian List -->
                            {#if product.has_variants && product.use_custom && product.expanded}
                                <div
                                    class="border-t border-slate-100 bg-slate-50/20 p-4 sm:p-6 space-y-4"
                                >
                                    <div
                                        class="flex justify-between items-center px-2"
                                    >
                                        <span
                                            class="text-xs font-bold text-slate-400 uppercase tracking-wider"
                                            >Daftar Kombinasi Varian</span
                                        >
                                    </div>
                                    <div class="grid grid-cols-1 gap-4">
                                        {#each product.variants as variant (variant.id)}
                                            <div
                                                class="bg-white border border-slate-200 rounded-2xl p-4 flex flex-col xl:flex-row xl:items-center justify-between gap-6 shadow-sm"
                                            >
                                                <!-- Variant Name / SKU -->
                                                <div
                                                    class="flex items-center gap-3 min-w-[200px]"
                                                >
                                                    <div
                                                        class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center text-slate-400 text-base font-bold shrink-0"
                                                    >
                                                        <i class="ti ti-box"
                                                        ></i>
                                                    </div>
                                                    <div>
                                                        <h5
                                                            class="font-bold text-slate-800 text-sm leading-tight"
                                                        >
                                                            {variant.name}
                                                        </h5>
                                                        <span
                                                            class="text-[10px] font-bold text-slate-400 uppercase tracking-widest"
                                                            >{variant.sku ||
                                                                'TANPA SKU'}</span
                                                        >
                                                    </div>
                                                </div>

                                                <!-- Shipping Fields (Always Visible) -->
                                                <div
                                                    class="grid grid-cols-2 sm:grid-cols-4 gap-4 flex-grow max-w-3xl"
                                                >
                                                    <Input
                                                        bind:value={
                                                            variant.weight
                                                        }
                                                        type="number"
                                                        min="0"
                                                        id={`v-weight-${variant.id}`}
                                                        label="Berat"
                                                        prefix="Gram"
                                                        placeholder={product.weight !==
                                                            '' &&
                                                        product.weight !== null
                                                            ? product.weight.toString()
                                                            : '0'}
                                                    />
                                                    <Input
                                                        bind:value={
                                                            variant.length
                                                        }
                                                        type="number"
                                                        min="0"
                                                        id={`v-length-${variant.id}`}
                                                        label="Panjang"
                                                        prefix="Cm"
                                                        placeholder={product.length !==
                                                            '' &&
                                                        product.length !== null
                                                            ? product.length.toString()
                                                            : '0'}
                                                    />
                                                    <Input
                                                        bind:value={
                                                            variant.width
                                                        }
                                                        type="number"
                                                        min="0"
                                                        id={`v-width-${variant.id}`}
                                                        label="Lebar"
                                                        prefix="Cm"
                                                        placeholder={product.width !==
                                                            '' &&
                                                        product.width !== null
                                                            ? product.width.toString()
                                                            : '0'}
                                                    />
                                                    <Input
                                                        bind:value={
                                                            variant.height
                                                        }
                                                        type="number"
                                                        min="0"
                                                        id={`v-height-${variant.id}`}
                                                        label="Tinggi"
                                                        prefix="Cm"
                                                        placeholder={product.height !==
                                                            '' &&
                                                        product.height !== null
                                                            ? product.height.toString()
                                                            : '0'}
                                                    />
                                                </div>

                                                <!-- Dynamic Custom / Master Fallback Badge -->
                                                {#if (variant.weight === '' || variant.weight === null) && (variant.length === '' || variant.length === null) && (variant.width === '' || variant.width === null) && (variant.height === '' || variant.height === null)}
                                                    <div
                                                        class="text-[10px] font-bold text-emerald-600 bg-emerald-50 border border-emerald-200/55 px-2.5 py-1 rounded-lg self-center flex items-center gap-1 shrink-0"
                                                    >
                                                        <i
                                                            class="ti ti-info-circle text-xs"
                                                        ></i> Ikut Master
                                                    </div>
                                                {:else}
                                                    <div
                                                        class="text-[10px] font-bold text-brand-blueRoyal bg-brand-blueRoyal/5 border border-brand-blueRoyal/20 px-2.5 py-1 rounded-lg self-center flex items-center gap-1 shrink-0"
                                                    >
                                                        <i
                                                            class="ti ti-settings text-xs"
                                                        ></i> Dimensi Kustom
                                                    </div>
                                                {/if}
                                            </div>
                                        {/each}
                                    </div>
                                </div>
                            {/if}
                        </div>
                    {/each}

                    <div>
                        <Pagination paginator={products} />
                    </div>
                    <!-- Sticky Submit Panel -->
                    <div
                        class="fixed bottom-0 left-0 lg:left-64 right-0 bg-white/80 backdrop-blur-xl border-t border-slate-200 p-4 flex items-center justify-between z-40 px-6 sm:px-8 shadow-lg"
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
