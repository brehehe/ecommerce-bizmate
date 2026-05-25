<script>
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { useForm, page, inertia, router } from '@inertiajs/svelte';
    import Input from '@/components/ui/Input.svelte';
    import InputCurrency from '@/components/ui/InputCurrency.svelte';
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
                          price:
                              v.product_price?.price &&
                              Number(v.product_price.price) > 0
                                  ? v.product_price.price
                                  : '',
                          cost:
                              v.product_price?.cost &&
                              Number(v.product_price.cost) > 0
                                  ? v.product_price.cost
                                  : '',
                      };
                  })
                : [];

            const hasCustomVariantPrice = variantsData.some(
                (v) => v.price !== '' && Number(v.price) > 0,
            );

            return {
                id: p.id,
                name: p.name,
                category_name: p.category?.name || 'Umum',
                price: p.product_price?.price || 0,
                cost: p.product_price?.cost || 0,
                tax_enabled: !!p.tax_enabled,
                has_variants: hasVariants,
                use_custom: hasCustomVariantPrice,
                expanded: false, // accordion state closed by default
                variants: variantsData,
            };
        });
    });

    function handleToggleCustom(product) {
        if (!product.use_custom) {
            product.variants.forEach((v) => {
                v.price = '';
                v.cost = '';
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
                '/admin/store/prices',
                { search: searchQuery },
                { preserveState: true, replace: true },
            );
        }, 500);
    }

    // Inertia form submission
    const form = useForm({
        products: [],
    });

    let globalTaxEnabled = $derived(page.props.settings?.tax_enabled ?? false);
    let globalTaxPercentage = $derived(
        page.props.settings?.tax_percentage ?? 0,
    );

    function submit() {
        // Build the payload from current localProducts state
        form.products = $state.snapshot(localProducts);
        form.post('/admin/store/prices', {
            onSuccess: () => {
                // Flash success notification or alert is handled by global layout
            },
        });
    }
</script>

<svelte:head>
    <title>Store Management: Atur Harga</title>
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
                        Atur Harga & HPP Produk
                    </h2>
                    <p
                        class="text-xs text-slate-400 font-bold uppercase tracking-wider"
                    >
                        Kelola harga jual, harga modal, dan detail kalkulasi
                        pajak produk & varian secara massal
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
                <!-- Bulk Pricing Form -->
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
                                                >Harga Varian:</span
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
                                    {:else}
                                        <!-- Tax toggle only for products without variants (placed at master level) -->
                                        {#if globalTaxEnabled}
                                            <div
                                                class="border-r pr-4 border-slate-200"
                                            >
                                                <Toggle
                                                    bind:checked={
                                                        product.tax_enabled
                                                    }
                                                    label="Belum Termasuk Pajak"
                                                />
                                            </div>
                                        {/if}
                                    {/if}
                                </div>
                            </div>

                            <!-- Product Fields (Only if NO variants OR if variants are inheriting master price) -->
                            {#if !product.has_variants || !product.use_custom}
                                <div
                                    class="p-5 sm:p-6 border-t border-slate-100 grid grid-cols-1 md:grid-cols-2 gap-6"
                                >
                                    <div class="space-y-4">
                                        <InputCurrency
                                            bind:value={product.price}
                                            id={`price-${product.id}`}
                                            label="Harga Jual *"
                                            prefix="Rp"
                                        />
                                        <InputCurrency
                                            bind:value={product.cost}
                                            id={`cost-${product.id}`}
                                            label="Harga Modal (HPP)"
                                            prefix="Rp"
                                        />
                                    </div>
                                    <div
                                        class="bg-slate-50 rounded-2xl p-5 border border-slate-100 flex flex-col justify-center"
                                    >
                                        <span
                                            class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-3"
                                            >Rincian Kalkulasi Pajak</span
                                        >
                                        {#if globalTaxEnabled && product.price > 0}
                                            {#if product.tax_enabled}
                                                <div
                                                    class="text-xs text-slate-600 font-medium space-y-1"
                                                >
                                                    <div
                                                        class="flex justify-between"
                                                    >
                                                        <span
                                                            >Harga Asli (DPP):</span
                                                        >
                                                        <span
                                                            class="font-bold text-slate-800"
                                                            >Rp {Number(
                                                                product.price,
                                                            ).toLocaleString(
                                                                'id-ID',
                                                            )}</span
                                                        >
                                                    </div>
                                                    <div
                                                        class="flex justify-between text-rose-500"
                                                    >
                                                        <span
                                                            >Pajak PPN ({globalTaxPercentage}%):</span
                                                        >
                                                        <span
                                                            >+ Rp {Math.round(
                                                                (Number(
                                                                    product.price,
                                                                ) *
                                                                    globalTaxPercentage) /
                                                                    100,
                                                            ).toLocaleString(
                                                                'id-ID',
                                                            )}</span
                                                        >
                                                    </div>
                                                    <div
                                                        class="flex justify-between text-slate-800 font-black pt-2 border-t border-dashed border-slate-200"
                                                    >
                                                        <span>Total Bayar:</span
                                                        >
                                                        <span
                                                            class="text-brand-blueRoyal font-black"
                                                            >Rp {Math.round(
                                                                Number(
                                                                    product.price,
                                                                ) *
                                                                    (1 +
                                                                        globalTaxPercentage /
                                                                            100),
                                                            ).toLocaleString(
                                                                'id-ID',
                                                            )}</span
                                                        >
                                                    </div>
                                                </div>
                                            {:else}
                                                <div
                                                    class="text-xs text-slate-600 font-medium space-y-1"
                                                >
                                                    <div
                                                        class="flex justify-between"
                                                    >
                                                        <span>Total Bayar:</span
                                                        >
                                                        <span
                                                            class="font-bold text-slate-800"
                                                            >Rp {Number(
                                                                product.price,
                                                            ).toLocaleString(
                                                                'id-ID',
                                                            )}</span
                                                        >
                                                    </div>
                                                    <div
                                                        class="flex justify-between text-slate-500"
                                                    >
                                                        <span
                                                            >Harga Asli (DPP):</span
                                                        >
                                                        <span
                                                            >Rp {Math.round(
                                                                Number(
                                                                    product.price,
                                                                ) /
                                                                    (1 +
                                                                        globalTaxPercentage /
                                                                            100),
                                                            ).toLocaleString(
                                                                'id-ID',
                                                            )}</span
                                                        >
                                                    </div>
                                                    <div
                                                        class="flex justify-between text-rose-500"
                                                    >
                                                        <span
                                                            >Pajak PPN ({globalTaxPercentage}%
                                                            di dalam):</span
                                                        >
                                                        <span
                                                            >Rp {Math.round(
                                                                Number(
                                                                    product.price,
                                                                ) -
                                                                    Number(
                                                                        product.price,
                                                                    ) /
                                                                        (1 +
                                                                            globalTaxPercentage /
                                                                                100),
                                                            ).toLocaleString(
                                                                'id-ID',
                                                            )}</span
                                                        >
                                                    </div>
                                                </div>
                                            {/if}
                                        {:else if !globalTaxEnabled}
                                            <div
                                                class="text-xs text-slate-400 font-medium leading-relaxed"
                                            >
                                                Perpajakan dinonaktifkan di
                                                setelan toko Anda.
                                            </div>
                                        {:else}
                                            <div
                                                class="text-xs text-slate-400 font-medium leading-relaxed"
                                            >
                                                Masukkan harga jual terlebih
                                                dahulu untuk melihat kalkulasi
                                                pajak secara detail.
                                            </div>
                                        {/if}
                                    </div>
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
                                        {#if globalTaxEnabled}
                                            <div class="scale-90 origin-right">
                                                <Toggle
                                                    bind:checked={
                                                        product.tax_enabled
                                                    }
                                                    label="Semua Varian Belum Termasuk Pajak"
                                                />
                                            </div>
                                        {/if}
                                    </div>
                                    <div class="grid grid-cols-1 gap-4">
                                        {#each product.variants as variant (variant.id)}
                                            {@const activePrice =
                                                variant.price !== '' &&
                                                variant.price !== null &&
                                                Number(variant.price) > 0
                                                    ? Number(variant.price)
                                                    : Number(product.price)}
                                            <div
                                                class="bg-white border border-slate-200 rounded-2xl p-4 flex flex-col lg:flex-row lg:items-center justify-between gap-6 shadow-sm"
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

                                                <!-- Price Fields (Always Visible) -->
                                                <div
                                                    class="grid grid-cols-1 sm:grid-cols-2 gap-4 flex-grow max-w-md"
                                                >
                                                    <InputCurrency
                                                        bind:value={
                                                            variant.price
                                                        }
                                                        id={`v-price-${variant.id}`}
                                                        label="Harga Jual *"
                                                        prefix="Rp"
                                                        placeholder={product.price
                                                            ? product.price.toString()
                                                            : '0'}
                                                    />
                                                    <InputCurrency
                                                        bind:value={
                                                            variant.cost
                                                        }
                                                        id={`v-cost-${variant.id}`}
                                                        label="Harga Modal"
                                                        prefix="Rp"
                                                        placeholder={product.cost
                                                            ? product.cost.toString()
                                                            : '0'}
                                                    />
                                                </div>

                                                <!-- Dynamic Custom Price / Master Fallback Badge -->
                                                {#if variant.price === '' || variant.price === null || Number(variant.price) === 0}
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
                                                        ></i> Harga Kustom
                                                    </div>
                                                {/if}

                                                <!-- Variant Tax Info -->
                                                {#if globalTaxEnabled && activePrice > 0}
                                                    <div
                                                        class="bg-slate-50/50 rounded-xl p-3 border border-slate-100 min-w-[200px] text-[11px] text-slate-500 space-y-1"
                                                    >
                                                        {#if product.tax_enabled}
                                                            <div
                                                                class="flex justify-between"
                                                            >
                                                                <span
                                                                    >Harga Asli:</span
                                                                >
                                                                <span
                                                                    >Rp {activePrice.toLocaleString(
                                                                        'id-ID',
                                                                    )}</span
                                                                >
                                                            </div>
                                                            <div
                                                                class="flex justify-between text-rose-500"
                                                            >
                                                                <span>PPN:</span
                                                                >
                                                                <span
                                                                    >+ Rp {Math.round(
                                                                        (activePrice *
                                                                            globalTaxPercentage) /
                                                                            100,
                                                                    ).toLocaleString(
                                                                        'id-ID',
                                                                    )}</span
                                                                >
                                                            </div>
                                                            <div
                                                                class="flex justify-between text-slate-800 font-black pt-1 border-t border-dashed border-slate-200"
                                                            >
                                                                <span
                                                                    >Total
                                                                    Bayar:</span
                                                                >
                                                                <span
                                                                    >Rp {Math.round(
                                                                        activePrice *
                                                                            (1 +
                                                                                globalTaxPercentage /
                                                                                    100),
                                                                    ).toLocaleString(
                                                                        'id-ID',
                                                                    )}</span
                                                                >
                                                            </div>
                                                        {:else}
                                                            <div
                                                                class="flex justify-between font-bold text-slate-800"
                                                            >
                                                                <span
                                                                    >Total
                                                                    Bayar:</span
                                                                >
                                                                <span
                                                                    >Rp {activePrice.toLocaleString(
                                                                        'id-ID',
                                                                    )}</span
                                                                >
                                                            </div>
                                                            <div
                                                                class="flex justify-between"
                                                            >
                                                                <span
                                                                    >Harga Asli:</span
                                                                >
                                                                <span
                                                                    >Rp {Math.round(
                                                                        activePrice /
                                                                            (1 +
                                                                                globalTaxPercentage /
                                                                                    100),
                                                                    ).toLocaleString(
                                                                        'id-ID',
                                                                    )}</span
                                                                >
                                                            </div>
                                                            <div
                                                                class="flex justify-between text-rose-500"
                                                            >
                                                                <span
                                                                    >PPN (di
                                                                    dlm):</span
                                                                >
                                                                <span
                                                                    >Rp {Math.round(
                                                                        activePrice -
                                                                            activePrice /
                                                                                (1 +
                                                                                    globalTaxPercentage /
                                                                                        100),
                                                                    ).toLocaleString(
                                                                        'id-ID',
                                                                    )}</span
                                                                >
                                                            </div>
                                                        {/if}
                                                    </div>
                                                {/if}
                                            </div>
                                        {/each}
                                    </div>
                                </div>
                            {/if}
                        </div>
                    {/each}

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
                <Pagination paginator={products} />
            {/if}
        </main>
    </div>
</AdminLayout>
