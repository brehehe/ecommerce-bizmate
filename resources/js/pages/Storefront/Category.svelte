<script lang="ts">
    import StorefrontLayout from '@/components/layouts/StorefrontLayout.svelte';
    import { page, Link, router } from '@inertiajs/svelte';
    import InputCurrency from '@/components/ui/InputCurrency.svelte';
    import { showToast } from '@/utils/toast';
    import VariantSelectorModal from '@/components/Storefront/VariantSelectorModal.svelte';

    let {
        category = { id: '', name: '', slug: '' },
        categories = [],
        products = { data: [], links: [] },
        filters = { q: '', min_price: '', max_price: '', sort: 'latest' },
        storeName = '',
    } = $props();

    const primary = $derived(page.props.theme?.primary_color || '#0c4cb4');
    const secondary = $derived(page.props.theme?.secondary_color || '#fa7315');
    const cartCount = $derived(page.props.cartCount || 0);
    const auth = $derived(page.props.auth?.user);
    const cartButtonStyle = $derived(
        (page.props.settings as any)?.storefront_cart_button_style || 'button',
    );

    let selectedVariantProduct = $state<any>(null);
    let showVariantModal = $state(false);

    function handleAddToCart(product: any, e: MouseEvent) {
        e.preventDefault();
        e.stopPropagation();

        if (!auth) {
            window.dispatchEvent(new CustomEvent('open-login-modal'));
            return;
        }

        if (product.variants && product.variants.length > 0) {
            selectedVariantProduct = product;
            showVariantModal = true;
            return;
        }

        router.post(
            '/cart',
            {
                product_id: product.id,
                product_variant_id: null,
                quantity: 1,
            },
            {
                preserveScroll: true,
                onError: () => {
                    showToast(
                        'Gagal menambahkan produk ke keranjang.',
                        'error',
                    );
                },
            },
        );
    }

    // Filter states
    let searchQ = $state(filters.q || '');
    let minPrice = $state(filters.min_price || '');
    let maxPrice = $state(filters.max_price || '');
    let selectedSort = $state(filters.sort || 'latest');

    // Mobile filter overlay state
    let showMobileFilters = $state(false);

    // Ref for auto-scrolling active category pill
    let categoryScrollRef: HTMLDivElement;

    // Sync state if props change (Inertia navigate)
    $effect(() => {
        searchQ = filters.q || '';
        minPrice = filters.min_price || '';
        maxPrice = filters.max_price || '';
        selectedSort = filters.sort || 'latest';
    });

    // Auto-scroll the active category tab into view whenever category changes
    $effect(() => {
        const _dep = category.id; // reactive dependency
        if (categoryScrollRef) {
            requestAnimationFrame(() => {
                const activeEl = categoryScrollRef?.querySelector(
                    '[data-active="true"]',
                );
                if (activeEl) {
                    (activeEl as HTMLElement).scrollIntoView({
                        inline: 'center',
                        behavior: 'instant',
                        block: 'nearest',
                    });
                }
            });
        }
    });

    // Navigate between category tabs without stacking browser history
    function navigateToCategory(cat: any) {
        router.get(
            `/category/${cat.slug || cat.id}`,
            {},
            {
                replace: true,
                preserveScroll: false,
            },
        );
    }

    function applyFilters(closeDrawer = true) {
        if (closeDrawer) showMobileFilters = false;
        router.get(
            `/category/${category.slug || category.id}`,
            {
                q: searchQ,
                min_price: minPrice,
                max_price: maxPrice,
                sort: selectedSort,
            },
            {
                preserveState: true,
                replace: true,
            },
        );
    }

    function resetFilters(keepMobileOpen = false) {
        searchQ = '';
        minPrice = '';
        maxPrice = '';
        selectedSort = 'latest';

        if (!keepMobileOpen) {
            showMobileFilters = false;
        }

        router.get(
            `/category/${category.slug || category.id}`,
            {},
            {
                preserveState: true,
                replace: true,
            },
        );
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

    // ── Infinite Scroll ──────────────────────────────────────────────────────
    let allProducts = $state<any[]>(products.data || []);
    let isLoadingMore = $state(false);
    let currentPage = $state(products.current_page || 1);
    let nextPageUrl = $state(products.next_page_url || null);

    $effect(() => {
        if (products.current_page === 1) {
            allProducts = products.data || [];
        } else {
            const existingIds = new Set(allProducts.map((p: any) => p.id));
            const newItems = (products.data || []).filter(
                (p: any) => !existingIds.has(p.id),
            );
            if (newItems.length > 0) {
                allProducts = [...allProducts, ...newItems];
            }
        }
        currentPage = products.current_page || 1;
        nextPageUrl = products.next_page_url || null;
        isLoadingMore = false;
    });

    async function loadMore() {
        if (isLoadingMore || !nextPageUrl) return;
        isLoadingMore = true;

        let fetchUrl = nextPageUrl;
        if (fetchUrl.startsWith('http://') || fetchUrl.startsWith('https://')) {
            try {
                const urlObj = new URL(fetchUrl);
                fetchUrl = urlObj.pathname + urlObj.search;
            } catch (e) {
                console.error('URL parse error:', e);
            }
        }

        try {
            const response = await fetch(fetchUrl, {
                headers: {
                    'X-Inertia': 'true',
                    'X-Inertia-Partial-Component': 'Storefront/Category',
                    'X-Inertia-Partial-Data': 'products',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Inertia-Version': page.version || '',
                },
            });

            if (response.status === 409) {
                window.location.reload();
                return;
            }

            if (response.ok) {
                const json = await response.json();
                const newProductsPaginator = json.props?.products;
                if (newProductsPaginator) {
                    currentPage = newProductsPaginator.current_page;
                    nextPageUrl = newProductsPaginator.next_page_url;

                    const newItems = newProductsPaginator.data || [];
                    const existingIds = new Set(
                        allProducts.map((p: any) => p.id),
                    );
                    const filteredNewItems = newItems.filter(
                        (p: any) => !existingIds.has(p.id),
                    );
                    if (filteredNewItems.length > 0) {
                        allProducts = [...allProducts, ...filteredNewItems];
                    }
                }
            }
        } catch (error) {
            console.error('Failed to load more products:', error);
        } finally {
            isLoadingMore = false;
        }
    }

    function setupObserver(node: HTMLElement) {
        const observer = new IntersectionObserver(
            (entries) => {
                if (entries[0].isIntersecting) {
                    loadMore();
                }
            },
            { rootMargin: '200px' },
        );
        observer.observe(node);
        return {
            destroy() {
                observer.disconnect();
            },
        };
    }

    function directClick(node: HTMLElement, callback: (e: MouseEvent) => void) {
        let currentCallback = callback;
        const handler = (e: MouseEvent) => {
            e.preventDefault();
            e.stopPropagation();
            currentCallback(e);
        };
        node.addEventListener('click', handler);
        return {
            update(newCallback: (e: MouseEvent) => void) {
                currentCallback = newCallback;
            },
            destroy() {
                node.removeEventListener('click', handler);
            }
        };
    }

    function goBack() {
        if (
            window.history.length > 1 &&
            document.referrer &&
            document.referrer.includes(window.location.host)
        ) {
            window.history.back();
        } else {
            router.visit('/');
        }
    }
</script>

<svelte:head>
    <title
        >Kategori: {category.name || 'Kategori'} - {storeName ||
            'Toko Kami'}</title
    >
</svelte:head>

<StorefrontLayout hideMobileHeader={true} hideMobileFooter={true}>
    <!-- ═══════════════════════════════════════════════════
     STICKY MOBILE TOP BAR (mobile only, replaces global header)
    ═══════════════════════════════════════════════════ -->
    <div
        class="md:hidden fixed top-0 left-0 right-0 z-40 bg-white border-b border-slate-100 shadow-sm"
    >
        <!-- Row 1: Back, Search, Cart, Profile -->
        <div
            class="flex items-center gap-3 px-3 py-2.5 text-white"
            style="background-color: {primary};"
        >
            <!-- Back button -->
            <button
                onclick={goBack}
                class="shrink-0 w-8 h-8 flex items-center justify-center rounded-full bg-white/20 text-white active:scale-90 transition"
                aria-label="Kembali"
            >
                <i class="ti ti-arrow-left text-lg"></i>
            </button>

            <!-- Inline search input -->
            <form
                onsubmit={(e) => {
                    e.preventDefault();
                    applyFilters();
                }}
                class="flex-grow"
            >
                <div class="relative">
                    <i
                        class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm pointer-events-none"
                    ></i>
                    <input
                        type="text"
                        bind:value={searchQ}
                        placeholder="Cari..."
                        class="w-full pl-8 pr-7 py-1.5 text-xs bg-white text-slate-800 rounded-xl border-0 focus:outline-none focus:ring-1 focus:ring-white/55 shadow-sm"
                    />
                    {#if searchQ}
                        <button
                            type="button"
                            onclick={() => {
                                searchQ = '';
                                applyFilters();
                            }}
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition"
                        >
                            <i class="ti ti-x text-xs"></i>
                        </button>
                    {/if}
                </div>
            </form>

            <!-- Cart icon -->
            <button
                onclick={() => {
                    if (auth) {
                        router.visit('/cart');
                    } else {
                        window.dispatchEvent(
                            new CustomEvent('open-login-modal'),
                        );
                    }
                }}
                class="shrink-0 relative w-8 h-8 flex items-center justify-center rounded-full bg-white/10 text-white active:scale-90 transition cursor-pointer"
                aria-label="Keranjang"
            >
                <i class="ti ti-shopping-cart text-lg"></i>
                {#if cartCount > 0}
                    <span
                        class="absolute -top-1 -right-1 w-4 h-4 rounded-full text-[8px] font-black flex items-center justify-center text-white"
                        style="background-color: {secondary};"
                    >
                        {cartCount}
                    </span>
                {/if}
            </button>

            <!-- Profile/Login icon -->
            {#if auth}
                <button
                    onclick={(e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        window.dispatchEvent(
                            new CustomEvent('toggle-profile-dropdown'),
                        );
                    }}
                    class="shrink-0 w-8 h-8 rounded-full bg-white/20 border border-white/40 flex items-center justify-center font-black text-[10px] text-white cursor-pointer"
                >
                    {auth.name
                        .split(' ')
                        .map((n: string) => n[0])
                        .slice(0, 2)
                        .join('')
                        .toUpperCase()}
                </button>
            {:else}
                <button
                    onclick={() =>
                        window.dispatchEvent(
                            new CustomEvent('open-login-modal'),
                        )}
                    class="shrink-0 w-8 h-8 flex items-center justify-center rounded-full bg-white/10 text-white active:scale-90 transition cursor-pointer"
                    aria-label="Masuk"
                >
                    <i class="ti ti-user-circle text-lg"></i>
                </button>
            {/if}
        </div>

        <!-- Row 2: Categories scroll row -->
        <div
            bind:this={categoryScrollRef}
            class="flex items-center gap-2 px-3 py-2 bg-white overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden border-b border-slate-100"
        >
            {#each categories as cat}
                <button
                    data-active={cat.id === category.id ? 'true' : 'false'}
                    onclick={() => navigateToCategory(cat)}
                    class="shrink-0 px-3.5 py-1.5 text-[10px] font-bold rounded-full border transition whitespace-nowrap active:scale-95
                           {cat.id === category.id
                        ? 'text-white border-transparent'
                        : 'bg-white border-slate-200 text-slate-600'}"
                    style={cat.id === category.id
                        ? `background-color: ${primary};`
                        : ''}
                >
                    <i class="ti {cat.icon || 'ti-tag'} mr-1"></i>
                    {cat.name}
                </button>
            {/each}
        </div>

        <!-- Row 3: Sort pills + Filter button -->
        <div
            class="flex items-center gap-2 px-3 py-2 bg-white overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden border-b border-slate-100"
        >
            {#each [{ id: 'relevance', label: 'Terkait' }, { id: 'latest', label: 'Terbaru' }, { id: 'popular', label: 'Terlaris' }, { id: 'price_asc', label: 'Harga ↑' }, { id: 'price_desc', label: 'Harga ↓' }] as sortOpt}
                <button
                    onclick={() => {
                        selectedSort = sortOpt.id;
                        applyFilters();
                    }}
                    class="shrink-0 px-3 py-1 text-xs font-bold rounded-full border transition whitespace-nowrap active:scale-95"
                    class:text-white={selectedSort === sortOpt.id}
                    class:border-transparent={selectedSort === sortOpt.id}
                    class:bg-white={selectedSort !== sortOpt.id}
                    class:border-slate-200={selectedSort !== sortOpt.id}
                    class:text-slate-600={selectedSort !== sortOpt.id}
                    style={selectedSort === sortOpt.id
                        ? `background-color: ${primary};`
                        : ''}
                >
                    {sortOpt.label}
                </button>
            {/each}

            <!-- Filter button at the end of sorting pills -->
            <button
                onclick={() => (showMobileFilters = true)}
                class="shrink-0 px-3 py-1 text-xs font-bold rounded-full border transition whitespace-nowrap active:scale-95 flex items-center gap-1
                       {minPrice || maxPrice
                    ? 'text-white border-transparent'
                    : 'bg-white border-slate-200 text-slate-600'}"
                style={minPrice || maxPrice
                    ? `background-color: ${secondary};`
                    : ''}
                aria-label="Filter"
            >
                <i class="ti ti-adjustments-horizontal"></i>
                Filter
            </button>
        </div>
    </div>

    <!-- Spacer for mobile sticky bar -->
    <div class="md:hidden h-[134px]"></div>

    <div
        class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pt-5 pb-8 md:py-8 min-h-[calc(100dvh-134px)] md:min-h-0"
    >
        <!-- Desktop Header -->
        <div class="hidden md:block mb-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h1
                        class="font-outfit font-black text-xl sm:text-2xl text-slate-800 flex items-center gap-2"
                    >
                        📂 {category.name}
                    </h1>
                </div>
            </div>
            <!-- Desktop sort pills row -->
            <div class="flex items-center gap-2 flex-wrap">
                {#each [{ id: 'relevance', label: 'Terkait' }, { id: 'latest', label: 'Terbaru' }, { id: 'popular', label: 'Terlaris' }, { id: 'price_asc', label: 'Harga ↑' }, { id: 'price_desc', label: 'Harga ↓' }] as sortOpt}
                    <button
                        onclick={() => {
                            selectedSort = sortOpt.id;
                            applyFilters(false);
                        }}
                        class="px-4 py-1.5 text-xs font-bold rounded-full border transition whitespace-nowrap active:scale-95"
                        class:text-white={selectedSort === sortOpt.id}
                        class:border-transparent={selectedSort === sortOpt.id}
                        class:bg-white={selectedSort !== sortOpt.id}
                        class:border-slate-200={selectedSort !== sortOpt.id}
                        class:text-slate-600={selectedSort !== sortOpt.id}
                        style={selectedSort === sortOpt.id
                            ? `background-color: ${primary};`
                            : ''}
                    >
                        {sortOpt.label}
                    </button>
                {/each}
            </div>
        </div>

        <div class="flex gap-8 items-start">
            <!-- ═══════════════════════════════════════════════════
             FILTER SIDEBAR (Desktop)
            ═══════════════════════════════════════════════════ -->
            <aside
                class="hidden md:block w-64 bg-white border border-slate-150 rounded-2xl p-5 shadow-soft shrink-0 space-y-6"
            >
                <div
                    class="flex items-center justify-between border-b border-slate-100 pb-3"
                >
                    <span
                        class="font-outfit font-black text-sm text-slate-800 uppercase tracking-wider flex items-center gap-1.5"
                    >
                        <i
                            class="ti ti-filter text-base"
                            style="color: {primary};"
                        ></i> Filter
                    </span>
                    <button
                        onclick={resetFilters}
                        class="text-[10px] font-black uppercase tracking-wider hover:underline"
                        style="color: {secondary};"
                    >
                        Reset
                    </button>
                </div>

                <!-- Kategori list (switch category) -->
                <div class="space-y-2.5">
                    <span
                        class="text-xs font-bold text-slate-400 uppercase tracking-wider block"
                        >Kategori</span
                    >
                    <div class="space-y-1 max-h-60 overflow-y-auto pr-1">
                        {#each categories as cat}
                            <button
                                onclick={() => navigateToCategory(cat)}
                                class="w-full text-left flex items-center justify-between py-1.5 px-2 rounded-lg text-xs font-bold transition
                                       {cat.id === category.id
                                    ? 'bg-slate-50'
                                    : 'text-slate-600 hover:text-slate-900'}"
                                style={cat.id === category.id
                                    ? `color: ${primary};`
                                    : ''}
                            >
                                <span class="flex items-center gap-2">
                                    <i class="ti {cat.icon || 'ti-tag'} text-sm"
                                    ></i>
                                    {cat.name}
                                </span>
                                {#if cat.id === category.id}
                                    <i class="ti ti-check text-xs"></i>
                                {/if}
                            </button>
                        {/each}
                    </div>
                </div>

                <hr class="border-slate-100" />

                <!-- Rentang Harga Filter -->
                <div class="space-y-3">
                    <span
                        class="text-xs font-bold text-slate-400 uppercase tracking-wider block"
                        >Rentang Harga</span
                    >
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
            </aside>

            <!-- ═══════════════════════════════════════════════════
             PRODUCT GRID (Right Column)
            ═══════════════════════════════════════════════════ -->
            <div class="flex-grow flex flex-col min-w-0">
                {#if allProducts.length === 0 && !isLoadingMore}
                    <div
                        class="bg-white border border-slate-150 rounded-[28px] py-16 px-6 sm:px-12 text-center max-w-4xl mx-auto w-full transition-all duration-300"
                    >
                        <div
                            class="w-16 h-16 sm:w-20 sm:h-20 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-6 text-slate-300"
                        >
                            <i class="ti ti-package-off text-3xl sm:text-4xl"
                            ></i>
                        </div>

                        <h3
                            class="text-[#0a1d37] font-bold text-xl sm:text-2xl mb-2 tracking-tight"
                        >
                            Produk Tidak Ditemukan
                        </h3>

                        <p
                            class="text-slate-400 text-xs sm:text-sm max-w-md mx-auto leading-relaxed mt-2 mb-8"
                        >
                            Kami tidak dapat menemukan produk yang cocok di
                            kategori ini dengan filter Anda. Coba reset filter
                            atau gunakan kata kunci lain.
                        </p>

                        <button
                            onclick={resetFilters}
                            class="px-8 py-3 rounded-xl font-bold text-xs sm:text-sm text-white transition active:scale-95 shadow-lg shadow-blue-600/25 hover:shadow-blue-600/35"
                            style="background-color: {primary};"
                        >
                            Reset Filter
                        </button>
                    </div>
                {:else}
                    <!-- Product Grid -->
                    <div
                        class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4"
                    >
                        {#each allProducts as product, index (product.id + '_' + index)}
                            {@const img = getProductImage(product)}
                            {@const isPromo = product.is_promo}
                            {@const price = isPromo
                                ? product.promo_price
                                : (product.product_price?.price ?? 0)}
                            {@const originalPrice = isPromo
                                ? product.original_price
                                : 0}
                            {@const discountPercentage = isPromo
                                ? product.discount_percentage
                                : 0}

                            <div class="relative group bg-white border border-slate-100 hover:border-slate-200 hover:shadow-lg rounded-xl overflow-hidden transition flex flex-col h-full">
                                <Link
                                    href={`/products/${product.slug || product.id}`}
                                    prefetch
                                    class="flex flex-col flex-1 cursor-pointer"
                                >
                                    <div
                                        class="relative aspect-square overflow-hidden border-b border-slate-50"
                                    >
                                        {#if img}
                                            <img
                                                src={img}
                                                alt={product.name}
                                                class="w-full h-full object-cover group-hover:scale-105 transition duration-300"
                                                onerror={(e) => {
                                                    e.currentTarget.src =
                                                        '/noimage/image.png';
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
                                    <div
                                        class="p-2.5 sm:p-3 flex-1 flex flex-col justify-between"
                                    >
                                        <div>
                                            <p
                                                class="text-[9px] sm:text-[10px] font-black uppercase tracking-wider mb-1"
                                                style="color: {primary};"
                                            >
                                                {product.category?.name || 'PRODUK'}
                                            </p>
                                            <div class="h-[2.5rem] overflow-hidden mb-1">
                                                <p
                                                    class="text-xs sm:text-sm font-black leading-tight line-clamp-2"
                                                    style="color: #1e293b;"
                                                >
                                                    {product.name}
                                                </p>
                                            </div>
                                            <hr class="border-slate-100 my-2" />
                                            <div class="mb-1">
                                                <p
                                                    class="text-sm sm:text-base font-black leading-tight"
                                                    style="color: {secondary};"
                                                >
                                                    {formatPrice(price)}
                                                </p>
                                                {#if isPromo && originalPrice > price}
                                                    <p
                                                        class="text-[10px] sm:text-xs text-slate-400 line-through font-medium mt-0.5"
                                                    >
                                                        {formatPrice(originalPrice)}
                                                    </p>
                                                {/if}
                                            </div>
                                        </div>
                                    </div>
                                </Link>
                                <!-- Cart buttons OUTSIDE Link to prevent Inertia navigation -->
                                {#if cartButtonStyle === 'icon'}
                                    <button
                                        type="button"
                                        onclick={(e) => handleAddToCart(product, e)}
                                        class="absolute top-2.5 right-2.5 w-8 h-8 rounded-full bg-white/90 hover:bg-white flex items-center justify-center shadow-md border transition-all duration-200 active:scale-90 hover:scale-105 z-10"
                                        style="border-color: {primary}; color: {primary};"
                                        title="Tambah ke Keranjang"
                                    >
                                        <i class="ti ti-plus text-base font-black"></i>
                                    </button>
                                {/if}
                                {#if cartButtonStyle === 'button'}
                                    <div class="px-2.5 pb-2.5">
                                        <button
                                            type="button"
                                            onclick={(e) => handleAddToCart(product, e)}
                                            class="w-full flex items-center justify-center gap-1.5 py-2 px-3 rounded-xl font-bold text-[10px] sm:text-xs text-white uppercase tracking-wider transition duration-200 hover:brightness-95 active:scale-[0.98] cursor-pointer"
                                            style="background-color: {primary};"
                                            title="Tambah ke Keranjang"
                                        >
                                            <i
                                                class="ti ti-shopping-cart text-xs sm:text-sm"
                                            ></i>
                                            + KERANJANG
                                        </button>
                                    </div>
                                {/if}
                            </div>
                        {/each}
                    </div>

                    <!-- Infinite scroll sentinel + loading indicator -->
                    <div
                        use:setupObserver
                        class="py-8 flex flex-col items-center justify-center gap-3"
                    >
                        {#if isLoadingMore}
                            <div
                                class="flex items-center gap-2 mt-4 text-slate-500 font-medium text-xs sm:text-sm animate-pulse"
                            >
                                <svg
                                    class="animate-spin h-5 w-5"
                                    style="color: {primary};"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <circle
                                        class="opacity-25"
                                        cx="12"
                                        cy="12"
                                        r="10"
                                        stroke="currentColor"
                                        stroke-width="4"
                                    ></circle>
                                    <path
                                        class="opacity-75"
                                        fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                    ></path>
                                </svg>
                                Memuat lebih banyak...
                            </div>
                        {/if}
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
                onclick={() => (showMobileFilters = false)}
                class="absolute inset-0 bg-black/40 backdrop-blur-xs w-full h-full cursor-default border-0"
            ></button>

            <!-- Drawer body -->
            <div
                class="relative w-80 max-w-xs h-full bg-white shadow-2xl flex flex-col justify-between p-6 overflow-y-auto space-y-6"
            >
                <div>
                    <div
                        class="flex items-center justify-between border-b border-slate-100 pb-3 mb-5"
                    >
                        <span
                            class="font-outfit font-black text-sm text-slate-800 uppercase tracking-wider flex items-center gap-1.5"
                        >
                            <i
                                class="ti ti-filter text-base"
                                style="color: {primary};"
                            ></i> Filter
                        </span>
                        <button
                            onclick={() => (showMobileFilters = false)}
                            class="text-slate-400 hover:text-slate-600"
                        >
                            <i class="ti ti-x text-lg"></i>
                        </button>
                    </div>

                    <!-- Rentang Harga Filter -->
                    <div class="space-y-3">
                        <span
                            class="text-xs font-bold text-slate-400 uppercase tracking-wider block"
                            >Rentang Harga</span
                        >
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
                </div>

                <div
                    class="grid grid-cols-2 gap-3 pt-6 border-t border-slate-100"
                >
                    <button
                        onclick={() => resetFilters(true)}
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

    <VariantSelectorModal
        product={selectedVariantProduct}
        show={showVariantModal}
        onClose={() => (showVariantModal = false)}
        {primary}
        {secondary}
        user={auth}
    />
</StorefrontLayout>
