<script lang="ts">
    import StorefrontLayout from '@/components/layouts/StorefrontLayout.svelte';
    import { page, Link, router } from '@inertiajs/svelte';
    import Pagination from '@/components/ui/Pagination.svelte';
    import InputCurrency from '@/components/ui/InputCurrency.svelte';
    import Select from '@/components/ui/Select.svelte';
    import Toggle from '@/components/ui/Toggle.svelte';

    let {
        categories = [],
        products = { data: [], links: [] },
        filters = { q: '', category: '', sort: 'latest', min_price: '', max_price: '', promo: false },
        storeName = ''
    } = $props();

    const primary = $derived(page.props.theme?.primary_color || '#0c4cb4');
    const secondary = $derived(page.props.theme?.secondary_color || '#fa7315');

    // Filter states
    let searchQ = $state(filters.q || '');
    
    function getCategoriesFromFilter(catFilter: any) {
        if (!catFilter) return [];
        if (Array.isArray(catFilter)) return catFilter;
        return [catFilter];
    }
    
    let selectedCategories = $state(getCategoriesFromFilter(filters.category));
    let selectedSort = $state(filters.sort || 'relevance');
    let minPrice = $state(filters.min_price || '');
    let maxPrice = $state(filters.max_price || '');
    let promoOnly = $state(filters.promo || false);

    // Mobile filter overlay state
    let showMobileFilters = $state(false);

    let initialPromoOnly = filters.promo || false;

    // Sync state if props change (Inertia navigate)
    $effect(() => {
        searchQ = filters.q || '';
        selectedCategories = getCategoriesFromFilter(filters.category);
        selectedSort = filters.sort || 'relevance';
        minPrice = filters.min_price || '';
        maxPrice = filters.max_price || '';
        promoOnly = filters.promo || false;
        initialPromoOnly = filters.promo || false;
    });

    function applyFilters() {
        showMobileFilters = false;
        router.get('/search', {
            q: searchQ,
            category: selectedCategories,
            sort: selectedSort,
            min_price: minPrice,
            max_price: maxPrice,
            promo: promoOnly ? 1 : 0
        }, {
            preserveState: true
        });
    }

    function resetFilters() {
        searchQ = '';
        selectedCategories = [];
        selectedSort = 'latest';
        minPrice = '';
        maxPrice = '';
        promoOnly = false;
        showMobileFilters = false;
        router.get('/search');
    }

    function selectCategory(catSlug: string) {
        if (selectedCategories.includes(catSlug)) {
            selectedCategories = selectedCategories.filter(slug => slug !== catSlug);
        } else {
            selectedCategories = [...selectedCategories, catSlug];
        }
        applyFilters();
    }

    function formatPrice(price: any) {
        const n = Number(price);
        if (!n) return 'Rp 0';
        return 'Rp ' + n.toLocaleString('id-ID');
    }

    function getProductImage(product: any) {
        if (product.images?.length > 0)
            return product.images[0].url || product.images[0].path;
        if (product.image) return product.image;
        return null;
    }

    function fakeRating() {
        return (4.3 + Math.random() * 0.6).toFixed(1);
    }
</script>

<svelte:head>
    <title>Cari Produk - {storeName || 'Toko Kami'}</title>
</svelte:head>

<StorefrontLayout>
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-grow">
        <!-- Breadcrumbs / Top Bar -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="font-outfit font-black text-xl sm:text-2xl text-slate-800 flex items-center gap-2">
                    <i class="ti ti-search" style="color: {primary};"></i>
                    {#if filters.q}
                        Hasil Pencarian untuk "{filters.q}"
                    {:else if selectedCategories.length > 0}
                        Katalog Kategori
                    {:else}
                        Semua Produk
                    {/if}
                </h1>
                <p class="text-xs text-slate-400 font-bold uppercase tracking-wider mt-1">
                    Menampilkan {products.from || 0} - {products.to || 0} dari {products.total || 0} produk
                </p>
            </div>

            <!-- Sorting & Mini Pagination & Mobile Filter trigger -->
            <div class="flex items-center gap-3.5 self-end md:self-auto">
                <!-- Sorting Tabs (Desktop) -->
                <div class="hidden md:flex items-center gap-2 z-20">
                    <span class="text-xs font-bold text-slate-400 uppercase whitespace-nowrap mr-1">Urutkan:</span>
                    
                    <button
                        onclick={() => { selectedSort = 'relevance'; applyFilters(); }}
                        class="px-4 py-2 text-xs font-bold rounded-xl border transition cursor-pointer
                               {selectedSort === 'relevance' 
                                   ? 'text-white' 
                                   : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'}"
                        style={selectedSort === 'relevance' ? `background-color: ${primary}; border-color: ${primary};` : ''}
                    >
                        Terkait
                    </button>

                    <button
                        onclick={() => { selectedSort = 'latest'; applyFilters(); }}
                        class="px-4 py-2 text-xs font-bold rounded-xl border transition cursor-pointer
                               {selectedSort === 'latest' 
                                   ? 'text-white' 
                                   : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'}"
                        style={selectedSort === 'latest' ? `background-color: ${primary}; border-color: ${primary};` : ''}
                    >
                        Terbaru
                    </button>

                    <button
                        onclick={() => { selectedSort = 'popular'; applyFilters(); }}
                        class="px-4 py-2 text-xs font-bold rounded-xl border transition cursor-pointer
                               {selectedSort === 'popular' 
                                   ? 'text-white' 
                                   : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'}"
                        style={selectedSort === 'popular' ? `background-color: ${primary}; border-color: ${primary};` : ''}
                    >
                        Terlaris
                    </button>

                    <div class="w-40">
                        <Select
                            value={['price_asc', 'price_desc'].includes(selectedSort) ? selectedSort : ''}
                            onchange={(val) => {
                                selectedSort = val;
                                applyFilters();
                            }}
                            placeholder="Harga"
                            options={[
                                { id: 'price_asc', name: 'Harga: Terendah' },
                                { id: 'price_desc', name: 'Harga: Tertinggi' }
                            ]}
                        />
                    </div>
                </div>

                <!-- Sorting Dropdown (Mobile) -->
                <div class="md:hidden flex items-center gap-2 z-20">
                    <span class="text-xs font-bold text-slate-400 uppercase whitespace-nowrap">Urutkan:</span>
                    <div class="w-36">
                        <Select
                            bind:value={selectedSort}
                            onchange={applyFilters}
                            options={[
                                { id: 'relevance', name: 'Terkait' },
                                { id: 'latest', name: 'Terbaru' },
                                { id: 'popular', name: 'Terlaris' },
                                { id: 'price_asc', name: 'Harga: Terendah' },
                                { id: 'price_desc', name: 'Harga: Tertinggi' }
                            ]}
                        />
                    </div>
                </div>

                <!-- Mini Pagination (Desktop only) -->
                {#if products.last_page > 1}
                    <div class="hidden md:flex items-center gap-2 border-l border-slate-150 pl-4 h-8">
                        <span class="text-xs font-bold text-slate-500">
                            <span style="color: {primary};">{products.current_page}</span>
                            <span class="text-slate-300">/</span>
                            {products.last_page}
                        </span>
                        <div class="flex items-center gap-1">
                            <Link
                                href={products.prev_page_url || '#'}
                                prefetch
                                class="w-8 h-8 rounded-xl flex items-center justify-center bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 transition active:scale-95 {!products.prev_page_url ? 'opacity-40 cursor-not-allowed pointer-events-none' : ''}"
                            >
                                <i class="ti ti-chevron-left text-sm"></i>
                            </Link>
                            <Link
                                href={products.next_page_url || '#'}
                                prefetch
                                class="w-8 h-8 rounded-xl flex items-center justify-center bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 transition active:scale-95 {!products.next_page_url ? 'opacity-40 cursor-not-allowed pointer-events-none' : ''}"
                            >
                                <i class="ti ti-chevron-right text-sm"></i>
                            </Link>
                        </div>
                    </div>
                {/if}

                <!-- Mobile Filter Button -->
                <button
                    onclick={() => showMobileFilters = true}
                    class="md:hidden flex items-center gap-1.5 px-3 py-2 bg-white border border-slate-200 text-xs font-bold text-slate-600 rounded-xl shadow-sm active:scale-95 transition"
                >
                    <i class="ti ti-filter text-sm"></i>
                    Filter
                </button>
            </div>
        </div>

        <div class="flex gap-8 items-start">
            <!-- ═══════════════════════════════════════════════════
             FILTER SIDEBAR (Desktop)
            ═══════════════════════════════════════════════════ -->
            <aside class="hidden md:block w-64 bg-white border border-slate-150 rounded-2xl p-5 shadow-soft shrink-0 space-y-6">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <span class="font-outfit font-black text-sm text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                        <i class="ti ti-filter text-base" style="color: {primary};"></i> Filter
                    </span>
                    <button
                        onclick={resetFilters}
                        class="text-[10px] font-black uppercase tracking-wider hover:underline"
                        style="color: {secondary};"
                    >
                        Reset
                    </button>
                </div>

                <!-- Kategori Filter -->
                <div class="space-y-2.5">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Kategori</span>
                    <div class="space-y-1.5 max-h-60 overflow-y-auto pr-1 scrollbar-thin">
                        {#each categories as cat}
                            <button
                                onclick={() => selectCategory(cat.slug || cat.id.toString())}
                                class="w-full text-left flex items-center justify-between py-1.5 px-2 rounded-lg text-xs font-bold transition
                                       {selectedCategories.includes(cat.slug || cat.id.toString())
                                           ? 'bg-slate-50'
                                           : 'text-slate-600 hover:text-slate-900'}"
                                style={selectedCategories.includes(cat.slug || cat.id.toString()) ? `color: ${primary};` : ''}
                            >
                                <span class="flex items-center gap-2">
                                    <i class="ti {cat.icon || 'ti-tag'} text-sm"></i>
                                    {cat.name}
                                </span>
                                {#if selectedCategories.includes(cat.slug || cat.id.toString())}
                                    <i class="ti ti-check text-xs"></i>
                                {/if}
                            </button>
                        {/each}
                    </div>
                </div>

                <hr class="border-slate-100" />

                <!-- Rentang Harga Filter -->
                <div class="space-y-3">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Rentang Harga</span>
                    <div class="space-y-3">
                        <InputCurrency
                            bind:value={minPrice}
                            placeholder="0"
                            prefix="Rp"
                            label="Harga Minimum"
                        />
                        <InputCurrency
                            bind:value={maxPrice}
                            placeholder="Maks"
                            prefix="Rp"
                            label="Harga Maksimum"
                        />
                    </div>
                    <button
                        onclick={applyFilters}
                        class="w-full py-2 rounded-xl text-xs font-bold text-white transition active:scale-[0.98] shadow-sm"
                        style="background-color: {primary};"
                    >
                        Terapkan Harga
                    </button>
                </div>

                <hr class="border-slate-100" />

                <!-- Promo Toko Checkbox -->
                <div 
                    onclick={() => {
                        setTimeout(applyFilters, 0);
                    }}
                >
                    <Toggle 
                        bind:checked={promoOnly} 
                        label="Hanya Promo Toko" 
                        description="Tampilkan diskon aktif"
                        icon="ti-tag" 
                    />
                </div>
            </aside>

            <!-- ═══════════════════════════════════════════════════
             PRODUCT GRID (Right Column)
            ═══════════════════════════════════════════════════ -->
            <div class="flex-grow flex flex-col min-w-0">
                {#if products.data.length === 0}
                    <div class="bg-white border border-slate-150 rounded-2xl p-16 text-center shadow-soft">
                        <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4 text-slate-300">
                            <i class="ti ti-package-off text-3xl"></i>
                        </div>
                        <h3 class="text-slate-800 font-bold text-lg mb-1">Produk Tidak Ditemukan</h3>
                        <p class="text-slate-400 text-sm max-w-sm mx-auto mt-2">
                            Kami tidak dapat menemukan produk yang cocok dengan pencarian atau filter Anda. Coba reset filter atau gunakan kata kunci lain.
                        </p>
                        <button
                            onclick={resetFilters}
                            class="mt-6 px-6 py-2.5 rounded-xl font-bold text-xs text-white shadow-md transition active:scale-95"
                            style="background-color: {primary};"
                        >
                            Reset Filter
                        </button>
                    </div>
                {:else}
                    <!-- Masonry Grid -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                        {#each products.data as product}
                            {@const img = getProductImage(product)}
                            {@const isPromo = product.is_promo}
                            {@const price = isPromo ? product.promo_price : (product.product_price?.price ?? 0)}
                            {@const originalPrice = isPromo ? product.original_price : 0}
                            {@const discountPercentage = isPromo ? product.discount_percentage : 0}
                            {@const rating = fakeRating()}

                            <Link
                                href={`/products/${product.slug || product.id}`}
                                prefetch
                                class="group bg-white border border-slate-100 hover:border-slate-200 hover:shadow-lg rounded-xl overflow-hidden transition cursor-pointer flex flex-col h-full"
                            >
                                <div class="relative aspect-square overflow-hidden border-b border-slate-50">
                                    {#if img}
                                        <img
                                            src={img}
                                            alt={product.name}
                                            class="w-full h-full object-cover group-hover:scale-105 transition duration-300"
                                            onerror={(e) => {
                                                e.currentTarget.src = '/noimage/image.png';
                                            }}
                                        />
                                    {:else}
                                        <img
                                            src="/noimage/image.png"
                                            alt="No Image"
                                            class="w-full h-full object-cover"
                                        />
                                    {/if}
                                    {#if isPromo && discountPercentage > 0}
                                        <span
                                            class="absolute top-1.5 left-1.5 text-white text-[9px] font-black px-1.5 py-0.5 rounded-md shadow-sm"
                                            style="background-color: {secondary};"
                                        >
                                            -{discountPercentage}%
                                        </span>
                                    {/if}
                                </div>
                                <div class="p-2.5 sm:p-3 flex-1 flex flex-col justify-between">
                                    <div>
                                        <p class="text-[9px] sm:text-[10px] font-black uppercase tracking-wider mb-1" style="color: {primary};">
                                            {product.category?.name || 'PRODUK'}
                                        </p>
                                        <p class="text-xs sm:text-sm font-black leading-tight line-clamp-2 mb-1" style="color: {primary};">
                                            {product.name}
                                        </p>
                                        <div class="flex items-center gap-1 mt-1">
                                            <i class="ti ti-star-filled text-amber-500 text-[10px]"></i>
                                            <span class="text-[10px] text-slate-500 font-bold">{rating}</span>
                                        </div>
                                        <hr class="border-slate-100 my-2" />
                                        <div class="mb-3">
                                            <p class="text-sm sm:text-base font-black leading-tight" style="color: {secondary};">
                                                {formatPrice(price)}
                                            </p>
                                            {#if isPromo && originalPrice > price}
                                                <p class="text-[10px] sm:text-xs text-slate-400 line-through font-medium mt-0.5">
                                                    {formatPrice(originalPrice)}
                                                </p>
                                            {/if}
                                        </div>
                                    </div>
                                    <div class="pt-3">
                                        <span
                                            class="w-full flex items-center justify-center gap-1.5 py-2 px-3 rounded-xl font-bold text-[10px] sm:text-xs text-white uppercase tracking-wider transition duration-200 hover:brightness-95 active:scale-[0.98]"
                                            style="background-color: {primary};"
                                        >
                                            <i class="ti ti-shopping-cart text-xs sm:text-sm"></i>
                                            + KERANJANG
                                        </span>
                                    </div>
                                </div>
                            </Link>
                        {/each}
                    </div>

                    <!-- Pagination -->
                    <div class="mt-8 flex justify-center">
                        <Pagination paginator={products} itemLabel="Produk" />
                    </div>
                {/if}
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════
     MOBILE FILTER DRAWER (Mobile)
    ═══════════════════════════════════════════════════ -->
    {#if showMobileFilters}
        <div class="fixed inset-0 z-50 flex justify-end md:hidden">
            <!-- Backdrop -->
            <button
                onclick={() => showMobileFilters = false}
                class="absolute inset-0 bg-black/40 backdrop-blur-xs w-full h-full cursor-default"
            ></button>

            <!-- Drawer body -->
            <div class="relative w-80 max-w-xs h-full bg-white shadow-2xl flex flex-col justify-between p-6 overflow-y-auto space-y-6">
                <div>
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-5">
                        <span class="font-outfit font-black text-sm text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                            <i class="ti ti-filter text-base" style="color: {primary};"></i> Filter
                        </span>
                        <button
                            onclick={() => showMobileFilters = false}
                            class="text-slate-400 hover:text-slate-600"
                        >
                            <i class="ti ti-x text-lg"></i>
                        </button>
                    </div>

                    <!-- Kategori Filter -->
                    <div class="space-y-2.5">
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Kategori</span>
                        <div class="space-y-1.5 max-h-60 overflow-y-auto pr-1">
                            {#each categories as cat}
                                <button
                                    onclick={() => selectCategory(cat.slug || cat.id.toString())}
                                    class="w-full text-left flex items-center justify-between py-1.5 px-2 rounded-lg text-xs font-bold transition
                                           {selectedCategories.includes(cat.slug || cat.id.toString())
                                               ? 'bg-slate-50'
                                               : 'text-slate-600 hover:text-slate-900'}"
                                    style={selectedCategories.includes(cat.slug || cat.id.toString()) ? `color: ${primary};` : ''}
                                >
                                    <span class="flex items-center gap-2">
                                        <i class="ti {cat.icon || 'ti-tag'} text-sm"></i>
                                        {cat.name}
                                    </span>
                                    {#if selectedCategories.includes(cat.slug || cat.id.toString())}
                                        <i class="ti ti-check text-xs"></i>
                                    {/if}
                                </button>
                            {/each}
                        </div>
                    </div>

                    <hr class="border-slate-100 my-5" />

                    <!-- Rentang Harga Filter -->
                    <div class="space-y-3">
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Rentang Harga</span>
                        <div class="space-y-3">
                            <InputCurrency
                                bind:value={minPrice}
                                placeholder="0"
                                prefix="Rp"
                                label="Harga Minimum"
                            />
                            <InputCurrency
                                bind:value={maxPrice}
                                placeholder="Maks"
                                prefix="Rp"
                                label="Harga Maksimum"
                            />
                        </div>
                    </div>

                    <hr class="border-slate-100 my-5" />

                    <!-- Promo Toko Checkbox -->
                    <div>
                        <Toggle 
                            bind:checked={promoOnly} 
                            label="Hanya Promo Toko" 
                            description="Tampilkan diskon aktif"
                            icon="ti-tag" 
                        />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 pt-6 border-t border-slate-100">
                    <button
                        onclick={resetFilters}
                        class="py-3 border border-slate-200 rounded-xl text-xs font-bold text-slate-500 active:scale-95 transition"
                    >
                        Reset
                    </button>
                    <button
                        onclick={applyFilters}
                        class="py-3 rounded-xl text-xs font-bold text-white shadow-md active:scale-95 transition"
                        style="background-color: {primary};"
                    >
                        Terapkan
                    </button>
                </div>
            </div>
        </div>
    {/if}
</StorefrontLayout>
