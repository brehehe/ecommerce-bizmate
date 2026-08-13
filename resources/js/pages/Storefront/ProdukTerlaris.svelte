<script lang="ts">
    import StorefrontLayout from '@/components/layouts/StorefrontLayout.svelte';
    import { page, Link, router } from '@inertiajs/svelte';
    import InputCurrency from '@/components/ui/InputCurrency.svelte';
    import { showToast } from '@/utils/toast';
    import Toggle from '@/components/ui/Toggle.svelte';
    import VariantSelectorModal from '@/components/Storefront/VariantSelectorModal.svelte';
    import Pagination from '@/components/ui/Pagination.svelte';
    import LocationModal from '@/components/ui/LocationModal.svelte';

    let {
        categories = [],
        brands = [],
        products = { data: [], links: [] },
        filters = {
            q: '',
            category: '',
            brand: '',
            min_price: '',
            max_price: '',
            sort: 'popular',
            type: 'all',
            promo: false,
            condition: 'all',
            rating: '',
        },
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

    const isSellerEnabled = $derived(
        ((page.props as any).app_config?.is_seller_enabled ?? (page.props as any).settings?.is_seller_enabled ?? (page.props as any).is_seller_enabled) ?? false
    );

    const sortOptions = $derived([
        { id: 'latest', label: 'Terbaru' },
        { id: 'oldest', label: 'Terlama' },
        ...(!isSellerEnabled ? [{ id: 'popular', label: 'Terlaris' }] : []),
        { id: 'price_asc', label: 'Harga ↑' },
        { id: 'price_desc', label: 'Harga ↓' },
    ]);

    const popularLocations = [
        'Jakarta',
        'Surabaya',
        'Bandung',
        'Medan',
        'Semarang',
        'Yogyakarta',
        'Makassar',
        'Bali',
    ];

    let showLocationModal = $state(false);

    // Filter states
    // svelte-ignore state_referenced_locally
    let searchQ = $state(filters.q || '');
    // svelte-ignore state_referenced_locally
    let selectedSort = $state((!filters.sort || filters.sort === 'relevance') ? 'latest' : filters.sort);

    function getCategoriesFromFilter(catFilter: any) {
        if (!catFilter) return [];
        if (Array.isArray(catFilter)) return catFilter;
        return [catFilter];
    }

    // svelte-ignore state_referenced_locally
    let selectedCategories = $state(getCategoriesFromFilter(filters.category));
    // svelte-ignore state_referenced_locally
    let minPrice = $state(filters.min_price || '');
    // svelte-ignore state_referenced_locally
    let maxPrice = $state(filters.max_price || '');
    function getBrandsFromFilter(val: any) {
        if (!val) return [];
        if (Array.isArray(val)) return val;
        return typeof val === 'string' ? val.split(',') : [val.toString()];
    }

    // svelte-ignore state_referenced_locally
    let selectedBrands = $state(getBrandsFromFilter(filters.brand));
    // svelte-ignore state_referenced_locally
    let selectedType = $state(filters.type || 'all');
    // svelte-ignore state_referenced_locally
    let promoOnly = $state(filters.promo || false);
    // svelte-ignore state_referenced_locally
    let selectedCondition = $state(filters.condition || 'all');
    // svelte-ignore state_referenced_locally
    let selectedRating = $state(filters.rating || '');
    // svelte-ignore state_referenced_locally
    let selectedLocation = $state(filters.location || '');

    // Mobile filter overlay state
    let showMobileFilters = $state(false);

    // Sync state if props change (Inertia navigate)
    $effect(() => {
        searchQ = filters.q || '';
        selectedCategories = getCategoriesFromFilter(filters.category);
        selectedBrands = getBrandsFromFilter(filters.brand);
        minPrice = filters.min_price || '';
        maxPrice = filters.max_price || '';
        selectedSort = (!filters.sort || filters.sort === 'relevance') ? 'latest' : filters.sort;
        selectedType = filters.type || 'all';
        promoOnly = filters.promo || false;
        selectedCondition = filters.condition || 'all';
        selectedRating = filters.rating || '';
        selectedLocation = filters.location || '';
    });

    function applyFilters(closeDrawer = true) {
        if (closeDrawer) showMobileFilters = false;
        router.get(
            '/produk-terlaris',
            {
                q: searchQ,
                category: selectedCategories,
                brand: selectedBrands,
                min_price: minPrice,
                max_price: maxPrice,
                sort: selectedSort,
                type: selectedType,
                promo: promoOnly ? 1 : 0,
                condition: selectedCondition,
                rating: selectedRating,
                location: selectedLocation,
            },
            {
                preserveState: true,
                replace: true,
            },
        );
    }

    function resetFilters(keepMobileOpen = false) {
        searchQ = '';
        selectedCategories = [];
        selectedBrands = [];
        minPrice = '';
        maxPrice = '';
        selectedSort = 'popular';
        selectedType = 'all';
        promoOnly = false;
        selectedCondition = 'all';
        selectedRating = '';
        selectedLocation = '';

        if (!keepMobileOpen) {
            showMobileFilters = false;
        }

        router.get(
            '/produk-terlaris',
            {},
            {
                preserveState: true,
                replace: true,
            },
        );
    }

    // Di mobile drawer: hanya toggle state, tidak langsung navigasi
    function selectCategory(catSlug: string) {
        if (selectedCategories.includes(catSlug)) {
            selectedCategories = selectedCategories.filter(
                (slug) => slug !== catSlug,
            );
        } else {
            selectedCategories = [...selectedCategories, catSlug];
        }
    }

    // Di desktop sidebar: toggle dan langsung terapkan filter
    function selectCategoryDesktop(catSlug: string) {
        if (selectedCategories.includes(catSlug)) {
            selectedCategories = selectedCategories.filter(
                (slug) => slug !== catSlug,
            );
        } else {
            selectedCategories = [...selectedCategories, catSlug];
        }
        applyFilters(false);
    }

    function selectBrand(brandSlug: string) {
        if (selectedBrands.includes(brandSlug)) {
            selectedBrands = selectedBrands.filter(
                (slug) => slug !== brandSlug,
            );
        } else {
            selectedBrands = [...selectedBrands, brandSlug];
        }
    }

    function selectBrandDesktop(brandSlug: string) {
        selectBrand(brandSlug);
        applyFilters(false);
    }

    function formatPrice(price: any) {
        const n = Number(price);
        if (!n) return 'Rp 0';
        return 'Rp ' + n.toLocaleString('id-ID');
    }

    function getProductImage(product: any) {
        let path = null;
        if (product?.images?.length > 0) {
            path = product.images[0].url || product.images[0].path;
        } else if (product?.image) {
            path = product.image;
        }
        if (!path || typeof path !== 'string') return '/noimage/image.png';
        if (
            path.startsWith('http://') ||
            path.startsWith('https://') ||
            path.startsWith('/')
        ) {
            return path;
        }
        return '/' + path;
    }

    // ── Pagination ────────────────────────────────────────────────────────────
    let allProducts = $state<any[]>([]);
    let isLoadingMore = $state(false);
    let currentPage = $state(1);
    let lastPage = $state(1);
    let total = $state(0);

    $effect(() => {
        if (!products) return;
        allProducts = products.data || [];
        currentPage = products.current_page || 1;
        lastPage = products.last_page || 1;
        total = products.total || 0;
        isLoadingMore = false;
    });

    function goToPage(page: number) {
        if (page < 1 || page > lastPage || page === currentPage) return;
        isLoadingMore = true;
        router.get(
            '/produk-terlaris',
            {
                q: searchQ,
                category: selectedCategories,
                min_price: minPrice,
                max_price: maxPrice,
                sort: selectedSort,
                page,
            },
            {
                preserveState: true,
                replace: true,
                onFinish: () => {
                    isLoadingMore = false;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                },
            },
        );
    }

    const pageNumbers = $derived(() => {
        const pages: (number | '...')[] = [];
        if (lastPage <= 7) {
            for (let i = 1; i <= lastPage; i++) pages.push(i);
            return pages;
        }
        pages.push(1);
        if (currentPage > 4) pages.push('...');
        const start = Math.max(2, currentPage - 1);
        const end = Math.min(lastPage - 1, currentPage + 1);
        for (let i = start; i <= end; i++) pages.push(i);
        if (currentPage < lastPage - 3) pages.push('...');
        pages.push(lastPage);
        return pages;
    });

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
            },
        };
    }
</script>

<svelte:head>
    <title>Produk Terlaris 🔥 - {storeName || 'Toko Kami'}</title>
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
                class="w-8 h-8 flex items-center justify-center text-white shrink-0 hover:bg-white/10 rounded-xl transition cursor-pointer"
                aria-label="Kembali"
            >
                <i class="ti ti-arrow-left text-xl"></i>
            </button>

            <!-- Inline search input -->
            <form
                onsubmit={(e) => {
                    e.preventDefault();
                    applyFilters();
                }}
                class="flex-grow"
            >
                <div class="relative flex items-center bg-white/20 hover:bg-white/25 focus-within:bg-white/25 border border-white/30 rounded-xl transition shadow-xs">
                    <input
                        type="text"
                        bind:value={searchQ}
                        placeholder="Cari produk..."
                        class="w-full pl-3.5 pr-8 py-1.5 text-xs sm:text-sm bg-transparent text-white placeholder-white/70 focus:outline-none"
                    />
                    {#if searchQ}
                        <button
                            aria-label="Tutup"
                            type="button"
                            onclick={() => {
                                searchQ = '';
                                applyFilters();
                            }}
                            class="absolute right-2.5 text-white/80 hover:text-white transition"
                        >
                            <i class="ti ti-x text-sm"></i>
                        </button>
                    {:else}
                        <button
                            type="submit"
                            aria-label="Search"
                            class="absolute right-2.5 text-white/90 hover:text-white transition flex items-center justify-center p-1"
                        >
                            <i class="ti ti-search text-xl"></i>
                        </button>
                    {/if}
                </div>
            </form>

            <!-- Right actions -->
            <div class="flex items-center gap-1.5 shrink-0">
                <!-- Cart icon -->
                {#if !isSellerEnabled && !auth?.is_seller}
                    <div class="relative shrink-0">
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
                            class="w-8 h-8 flex items-center justify-center text-white hover:bg-white/10 rounded-xl transition cursor-pointer"
                            aria-label="Keranjang"
                        >
                            <i class="ti ti-shopping-cart text-xl"></i>
                        </button>
                        {#if cartCount > 0}
                            <span
                                class="absolute -top-0.5 -right-0.5 w-4 h-4 rounded-full text-[8px] font-black flex items-center justify-center text-white border border-white/20 shadow-xs pointer-events-none"
                                style="background-color: {secondary}; font-family: sans-serif;"
                            >
                                {cartCount}
                            </span>
                        {/if}
                    </div>
                {/if}

                <!-- Notifications Bell -->
                {#if auth}
                    <button
                        onclick={() => {
                            window.dispatchEvent(
                                new CustomEvent('toggle-notif-dropdown'),
                            );
                        }}
                        class="w-8 h-8 flex items-center justify-center text-white shrink-0 hover:bg-white/10 rounded-xl transition cursor-pointer"
                        aria-label="Notifikasi"
                    >
                        <i class="ti ti-bell text-xl"></i>
                    </button>
                {/if}

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
                        class="w-8 h-8 rounded-full overflow-hidden border border-white/40 flex items-center justify-center font-black text-xs text-white shrink-0 cursor-pointer hover:opacity-90 transition"
                    >
                        {#if auth.avatar}
                            <img
                                src="/storage/{auth.avatar}"
                                alt={auth.name}
                                class="w-full h-full object-cover"
                            />
                        {:else}
                            <div
                                class="w-full h-full bg-white/20 flex items-center justify-center"
                            >
                                {auth.name
                                    .split(' ')
                                    .map((n: string) => n[0])
                                    .slice(0, 2)
                                    .join('')
                                    .toUpperCase()}
                            </div>
                        {/if}
                    </button>
                {:else}
                    <button
                        onclick={() =>
                            window.dispatchEvent(
                                new CustomEvent('open-login-modal'),
                            )}
                        class="w-8 h-8 flex items-center justify-center text-white shrink-0 hover:bg-white/10 rounded-xl transition cursor-pointer"
                        aria-label="Masuk"
                    >
                        <i class="ti ti-user-circle text-xl"></i>
                    </button>
                {/if}
            </div>
        </div>

        <!-- Row 2: Sort pills + Filter button -->
        <div
            class="flex items-center gap-2 px-3 py-2 bg-white overflow-x-auto no-scrollbar border-b border-slate-100"
        >
            {#each sortOptions as sortOpt}
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
                       {selectedCategories.length > 0 || minPrice || maxPrice
                    ? 'text-white border-transparent'
                    : 'bg-white border-slate-200 text-slate-600'}"
                style={selectedCategories.length > 0 || minPrice || maxPrice
                    ? `background-color: ${secondary};`
                    : ''}
                aria-label="Filter"
            >
                <i class="ti ti-adjustments-horizontal"></i>
                Filter
            </button>
        </div>
    </div>

    <!-- Full-height wrapper on mobile (no bottom nav) -->
    <div class="md:contents min-h-dvh flex flex-col">
        <!-- Spacer for mobile sticky bar -->
        <div class="md:hidden h-[92px]"></div>

        <div
            class="flex-1 md:flex-none max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pt-5 pb-8 md:py-8 w-full"
        >
            <!-- Desktop Header (Desktop only, no tabs) -->
            <div
                class="hidden md:flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6"
            >
                <div>
                    <h1
                        class="font-outfit font-black text-xl sm:text-2xl text-slate-800 flex items-center gap-2"
                    >
                        🔥 Produk Terlaris
                    </h1>
                </div>
            </div>

            <div class="flex gap-8 items-start">
                <!-- ═══════════════════════════════════════════════════
             FILTER SIDEBAR (Desktop)
            ═══════════════════════════════════════════════════ -->
            <aside class="hidden md:block w-64 shrink-0 space-y-6 pt-1">
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
                        class="px-2.5 py-1 text-xs font-bold text-rose-600 bg-rose-50 hover:bg-rose-100 border border-rose-200/80 rounded-lg transition-all flex items-center gap-1 cursor-pointer active:scale-95 shadow-2xs"
                        title="Reset semua filter"
                    >
                        <i class="ti ti-rotate-2 text-xs"></i>
                        <span>Reset</span>
                    </button>
                    </div>

                    <!-- Kategori Filter -->
                    <div class="space-y-2.5">
                        <span
                            class="text-xs font-bold text-slate-400 uppercase tracking-wider block"
                            >Kategori</span
                        >
                        <div
                            class="space-y-1.5 max-h-60 overflow-y-auto pr-1 scrollbar-thin"
                        >
                            {#each categories || [] as cat}
                                <button
                                    onclick={() =>
                                        selectCategoryDesktop(
                                            cat.slug || cat.id.toString(),
                                        )}
                                    class="w-full text-left flex items-center justify-between py-1.5 px-2 rounded-lg text-xs font-bold transition
                                       {selectedCategories.includes(
                                        cat.slug || cat.id.toString(),
                                    )
                                        ? 'bg-slate-50'
                                        : 'text-slate-600 hover:text-slate-900'}"
                                    style={selectedCategories.includes(
                                        cat.slug || cat.id.toString(),
                                    )
                                        ? `color: ${primary};`
                                        : ''}
                                >
                                    <span class="flex items-center gap-2">
                                        <i
                                            class="ti {cat.icon ||
                                                'ti-tag'} text-sm"
                                        ></i>
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

                {#if brands && brands.length > 0}
                    <!-- Brand / Merek Filter -->
                    <div class="space-y-2.5">
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Merek / Brand</span>
                        <div class="space-y-1 max-h-48 overflow-y-auto pr-1">
                            {#each brands || [] as brand}
                                <button
                                    onclick={() => selectBrandDesktop(brand.slug || brand.id.toString())}
                                    class="w-full text-left flex items-center justify-between py-1.5 px-2 rounded-lg text-xs font-bold transition
                                       {selectedBrands.includes(brand.slug || brand.id.toString())
                                        ? 'bg-slate-50'
                                        : 'text-slate-600 hover:text-slate-900'}"
                                    style={selectedBrands.includes(brand.slug || brand.id.toString()) ? `color: ${primary};` : ''}
                                >
                                    <span class="truncate">{brand.name}</span>
                                    {#if selectedBrands.includes(brand.slug || brand.id.toString())}
                                        <i class="ti ti-check text-xs"></i>
                                    {/if}
                                </button>
                            {/each}
                        </div>
                    </div>
                    <hr class="border-slate-100" />
                {/if}

                <!-- Rentang Harga Filter -->
                <div class="space-y-3">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Rentang Harga</span>
                    <div class="space-y-2">
                        <InputCurrency bind:value={minPrice} placeholder="0" prefix="Rp" label="Harga Minimum" />
                        <InputCurrency bind:value={maxPrice} placeholder="Maks" prefix="Rp" label="Harga Maksimum" />
                    </div>
                    <button
                        onclick={applyFilters}
                        class="w-full py-2 rounded-xl text-xs font-bold text-white transition active:scale-[0.98] shadow-sm"
                        style="background-color: {primary};"
                    >
                        Terapkan Harga
                    </button>
                </div>

                {#if !isSellerEnabled}
                    <hr class="border-slate-100" />

                    <!-- Promo Toko Checkbox -->
                    <div
                        role="button"
                        tabindex="0"
                        onkeydown={(e) => e.key === 'Enter' && setTimeout(applyFilters, 0)}
                        onclick={() => setTimeout(applyFilters, 0)}
                    >
                        <Toggle bind:checked={promoOnly} label="Hanya Promo Toko" description="Tampilkan diskon aktif" icon="ti-tag" />
                    </div>
                {/if}

                <hr class="border-slate-100" />

                <!-- Jenis Produk Filter -->
                <div class="space-y-2.5">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Jenis Produk</span>
                    <select
                        bind:value={selectedType}
                        onchange={() => applyFilters(false)}
                        class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-slate-300"
                    >
                        <option value="all">Semua Produk</option>
                        <option value="physical">Produk Fisik</option>
                        <option value="digital">Produk Digital</option>
                    </select>
                </div>

                <hr class="border-slate-100" />

                <!-- Kondisi Produk Filter -->
                <div class="space-y-2.5">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Kondisi Produk</span>
                    <div class="grid grid-cols-4 gap-1">
                        {#each [{ id: 'all', label: 'Semua' }, { id: 'new', label: 'New' }, { id: 'second', label: 'Second' }, { id: 'rent', label: 'Rent' }] as cond}
                            <button
                                onclick={() => { selectedCondition = cond.id; applyFilters(false); }}
                                class="py-1.5 text-[11px] font-bold rounded-lg border transition text-center
                                       {selectedCondition === cond.id
                                    ? 'bg-slate-800 text-white border-slate-800'
                                    : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'}"
                            >
                                {cond.label}
                            </button>
                        {/each}
                    </div>
                </div>

                {#if !isSellerEnabled}
                    <hr class="border-slate-100" />

                    <!-- Rating Filter -->
                    <div class="space-y-2.5">
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Rating Minimum</span>
                        <div class="space-y-1">
                            {#each [{ value: '', label: 'Semua Rating' }, { value: '5', label: '⭐ 5 Bintang' }, { value: '4', label: '⭐ 4 ke atas' }, { value: '3', label: '⭐ 3 ke atas' }] as rate}
                                <button
                                    onclick={() => { selectedRating = rate.value; applyFilters(false); }}
                                    class="w-full text-left flex items-center justify-between py-1.5 px-2 rounded-lg text-xs font-bold transition
                                           {selectedRating === rate.value
                                        ? 'bg-amber-50 text-amber-700 font-extrabold'
                                        : 'text-slate-600 hover:text-slate-900'}"
                                >
                                    <span>{rate.label}</span>
                                    {#if selectedRating === rate.value}
                                        <i class="ti ti-check text-xs text-amber-600 font-bold"></i>
                                    {/if}
                                </button>
                            {/each}
                        </div>
                    </div>
                {/if}

                <hr class="border-slate-100" />

                <!-- Lokasi Filter -->
                <div class="space-y-2.5">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">
                        Lokasi Penjual / Kota
                    </span>
                    <div class="relative mb-2">
                        <i class="ti ti-map-pin absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <input
                            type="text"
                            bind:value={selectedLocation}
                            onchange={() => applyFilters(false)}
                            placeholder="Cari Kota / Provinsi..."
                            class="w-full pl-8 pr-8 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-slate-300"
                        />
                        {#if selectedLocation}
                            <button
                                type="button"
                                onclick={() => {
                                    selectedLocation = '';
                                    applyFilters(false);
                                }}
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                            >
                                <i class="ti ti-x text-xs"></i>
                            </button>
                        {/if}
                    </div>
                    <div class="space-y-1 max-h-48 overflow-y-auto pr-1 scrollbar-thin">
                        {#each popularLocations as loc}
                            <button
                                type="button"
                                onclick={() => {
                                    selectedLocation = selectedLocation === loc ? '' : loc;
                                    applyFilters(false);
                                }}
                                class="w-full text-left flex items-center justify-between py-1.5 px-2 rounded-lg text-xs font-bold transition cursor-pointer
                                       {selectedLocation === loc
                                    ? 'bg-amber-50 text-amber-700 font-extrabold'
                                    : 'text-slate-600 hover:text-slate-900'}"
                            >
                                <span class="flex items-center gap-2 truncate">
                                    <i class="ti ti-map-pin text-sm text-slate-400"></i>
                                    {loc}
                                </span>
                                {#if selectedLocation === loc}
                                    <i class="ti ti-check text-xs text-amber-600 font-bold"></i>
                                {/if}
                            </button>
                        {/each}
                    </div>
                    <button
                        type="button"
                        onclick={() => (showLocationModal = true)}
                        class="w-full text-left flex items-center justify-between py-1.5 px-2 rounded-lg text-xs font-bold text-slate-500 hover:text-slate-900 hover:bg-slate-100 transition mt-1.5 border border-dashed border-slate-200"
                    >
                        <span class="flex items-center gap-1.5">
                            Lainnya
                            <i class="ti ti-chevron-down text-xs"></i>
                        </span>
                    </button>
                </div>
            </aside>

                <!-- ═══════════════════════════════════════════════════
             PRODUCT GRID (Right Column)
            ═══════════════════════════════════════════════════ -->
                <div class="flex-grow flex flex-col min-w-0">
                    {#if products === undefined}
                        <!-- Skeleton Grid -->
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                            {#each Array(8) as _}
                                <div class="bg-white rounded-xl border border-slate-100 overflow-hidden animate-pulse">
                                    <div class="aspect-square bg-slate-100"></div>
                                    <div class="p-3 space-y-2">
                                        <div class="h-3 bg-slate-100 rounded w-3/4"></div>
                                        <div class="h-3 bg-slate-100 rounded w-1/2"></div>
                                        <div class="h-4 bg-slate-100 rounded w-2/3 mt-2"></div>
                                    </div>
                                </div>
                            {/each}
                        </div>
                    {:else if allProducts.length === 0 && !isLoadingMore}
                        <div
                            class="py-16 px-6 sm:px-12 text-center w-full transition-all duration-300 flex flex-col items-center justify-center"
                        >
                            <div
                                class="w-16 h-16 sm:w-20 sm:h-20 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-6 text-slate-300"
                            >
                                <i
                                    class="ti ti-package-off text-3xl sm:text-4xl"
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
                                Kami tidak dapat menemukan produk yang cocok
                                dengan pencarian atau filter Anda. Coba reset
                                filter atau gunakan kata kunci lain.
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

                                <div
                                    class="relative group bg-white border border-slate-100 hover:border-slate-200 hover:shadow-lg rounded-xl overflow-hidden transition flex flex-col h-full"
                                >
                                    <a
                                        href={`/products/${product.id}`}
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
                                                    alt=""
                                                    class="w-full h-full object-cover"
                                                />
                                            {/if}
                                            <div class="absolute top-1.5 left-1.5 z-10 flex flex-col gap-1 items-start pointer-events-none">
                                                {#if isSellerEnabled}
                                                    <span
                                                        class="text-white text-[9px] font-black px-1.5 py-0.5 rounded-md shadow-xs {product.condition === 'rent' ? 'bg-purple-600' : (product.condition === 'used' || product.condition === 'second' ? 'bg-amber-600' : 'bg-emerald-600')}"
                                                    >
                                                        {product.condition === 'rent' ? 'Rent' : (product.condition === 'used' || product.condition === 'second' ? 'Second' : 'New')}
                                                    </span>
                                                {/if}
                                                {#if isPromo && discountPercentage > 0}
                                                    <span
                                                        class="text-white text-[9px] font-black px-1.5 py-0.5 rounded-md shadow-sm"
                                                        style="background-color: {secondary};"
                                                    >
                                                        -{discountPercentage}%
                                                    </span>
                                                {/if}
                                            </div>
                                        </div>
                                        <div
                                            class="p-2.5 sm:p-3 flex-1 flex flex-col justify-between"
                                        >
                                            <div>
                                                 <div class="flex items-center justify-between gap-1 mb-1">
                                                     <p
                                                         class="text-[9px] sm:text-[10px] font-black uppercase tracking-wider truncate"
                                                         style="color: {primary};"
                                                     >
                                                         {product.category?.name ||
                                                             'PRODUK'}
                                                     </p>
                                                     {#if product.seller?.store_name || product.seller?.name}
                                                         <span class="inline-flex items-center gap-1 text-[10px] font-bold text-slate-500 bg-slate-100 px-1.5 py-0.5 rounded shrink-0 max-w-[50%] truncate">
                                                             <i class="ti ti-building-store text-blue-600 text-[11px]"></i>
                                                             <span class="truncate">{product.seller.store_name || product.seller.name}</span>
                                                         </span>
                                                     {/if}
                                                 </div>
                                                <div
                                                    class="h-[2rem] overflow-hidden mb-0.5"
                                                >
                                                    <p
                                                        class="text-xs sm:text-sm font-black leading-tight line-clamp-2"
                                                        style="color: #1e293b;"
                                                    >
                                                        {product.name}
                                                    </p>
                                                </div>
                                                {#if product.sold_count != null && product.sold_count > 0}
                                                    <p
                                                        class="text-[9px] text-slate-400 font-medium mb-1"
                                                    >
                                                        {product.sold_count >=
                                                        1000
                                                            ? (
                                                                  product.sold_count /
                                                                  1000
                                                              )
                                                                  .toFixed(1)
                                                                  .replace(
                                                                      '.0',
                                                                      '',
                                                                  ) + 'rb'
                                                            : product.sold_count}+
                                                        terjual
                                                    </p>
                                                {/if}
                                                <hr
                                                    class="border-slate-100 my-1.5"
                                                />
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
                                                            {formatPrice(
                                                                originalPrice,
                                                            )}
                                                        </p>
                                                    {/if}
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                    <!-- Cart buttons OUTSIDE Link to prevent Inertia navigation -->
                                    {#if cartButtonStyle === 'icon'}
                                        <button
                                            type="button"
                                            onclick={(e) =>
                                                handleAddToCart(product, e)}
                                            class="absolute top-2.5 right-2.5 w-8 h-8 rounded-full bg-white/90 hover:bg-white flex items-center justify-center shadow-md border transition-all duration-200 active:scale-90 hover:scale-105 z-10"
                                            style="border-color: {primary}; color: {primary};"
                                            title="Tambah ke Keranjang"
                                        >
                                            <i
                                                class="ti ti-plus text-2xl sm:text-base font-black"
                                            ></i>
                                        </button>
                                    {/if}
                                    {#if cartButtonStyle === 'button'}
                                        <div class="px-2.5 pb-2.5">
                                            <button
                                                type="button"
                                                onclick={(e) =>
                                                    handleAddToCart(product, e)}
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

                    <!-- Pagination Component -->
                    <Pagination
                        data={products}
                        itemLabel="Produk"
                        {primary}
                        class="mt-8 flex flex-col sm:flex-row gap-3.5 sm:items-center sm:justify-between py-4 w-full"
                    />
                    {/if}
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════
     MOBILE FILTER DRAWER (Mobile)
    ═══════════════════════════════════════════════════ -->
        {#if showMobileFilters}
            <div class="fixed inset-0 z-[200] flex justify-end md:hidden">
                <!-- Backdrop -->
                <button
                    aria-label="Tutup"
                    onclick={() => (showMobileFilters = false)}
                    class="absolute inset-0 bg-black/40 backdrop-blur-xs w-full h-full cursor-default border-0"
                ></button>

                <!-- Drawer body -->
                <div
                    class="relative w-80 max-w-xs h-full bg-white shadow-2xl flex flex-col justify-between overflow-hidden"
                >
                    <div class="flex-1 overflow-y-auto p-6 space-y-6">
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
                                aria-label="Close filters"
                                onclick={() => (showMobileFilters = false)}
                                class="text-slate-400 hover:text-slate-600"
                            >
                                <i class="ti ti-x text-lg"></i>
                            </button>
                        </div>

                        <!-- Kategori Filter -->
                        <div class="space-y-2.5">
                            <span
                                class="text-xs font-bold text-slate-400 uppercase tracking-wider block"
                                >Kategori</span
                            >
                            <div
                                class="space-y-1.5 max-h-60 overflow-y-auto pr-1"
                            >
                                {#each categories || [] as cat}
                                    <button
                                        onclick={() =>
                                            selectCategory(
                                                cat.slug || cat.id.toString(),
                                            )}
                                        class="w-full text-left flex items-center justify-between py-1.5 px-2 rounded-lg text-xs font-bold transition
                                           {selectedCategories.includes(
                                            cat.slug || cat.id.toString(),
                                        )
                                            ? 'bg-slate-50'
                                            : 'text-slate-600 hover:text-slate-900'}"
                                        style={selectedCategories.includes(
                                            cat.slug || cat.id.toString(),
                                        )
                                            ? `color: ${primary};`
                                            : ''}
                                    >
                                        <span class="flex items-center gap-2">
                                            <i
                                                class="ti {cat.icon ||
                                                    'ti-tag'} text-sm"
                                            ></i>
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

                        <!-- Lokasi Filter Mobile Drawer -->
                        <div class="space-y-3 mt-6">
                            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">
                                Lokasi Penjual / Kota
                            </span>
                            <div class="relative mb-2">
                                <i class="ti ti-map-pin absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                                <input
                                    type="text"
                                    bind:value={selectedLocation}
                                    placeholder="Cari Kota / Provinsi..."
                                    class="w-full pl-8 pr-8 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-slate-300"
                                />
                                {#if selectedLocation}
                                    <button
                                        type="button"
                                        onclick={() => (selectedLocation = '')}
                                        class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                                    >
                                        <i class="ti ti-x text-xs"></i>
                                    </button>
                                {/if}
                            </div>
                            <div class="space-y-1 max-h-48 overflow-y-auto pr-1 scrollbar-thin">
                                {#each popularLocations as loc}
                                    <button
                                        type="button"
                                        onclick={() => (selectedLocation = selectedLocation === loc ? '' : loc)}
                                        class="w-full text-left flex items-center justify-between py-1.5 px-2 rounded-lg text-xs font-bold transition
                                               {selectedLocation === loc
                                            ? 'bg-amber-50 text-amber-700 font-extrabold'
                                            : 'text-slate-600 hover:text-slate-900'}"
                                    >
                                        <span class="flex items-center gap-2 truncate">
                                            <i class="ti ti-map-pin text-sm text-slate-400"></i>
                                            {loc}
                                        </span>
                                        {#if selectedLocation === loc}
                                            <i class="ti ti-check text-xs text-amber-600 font-bold"></i>
                                        {/if}
                                    </button>
                                {/each}
                            </div>
                            <button
                                type="button"
                                onclick={() => (showLocationModal = true)}
                                class="w-full text-left flex items-center justify-between py-1.5 px-2 rounded-lg text-xs font-bold text-slate-500 hover:text-slate-900 hover:bg-slate-100 transition mt-1.5 border border-dashed border-slate-200"
                            >
                                <span class="flex items-center gap-1.5">
                                    Lainnya
                                    <i class="ti ti-chevron-down text-xs"></i>
                                </span>
                            </button>
                    </div>
                </div>

                <div
                    class="p-4 pb-24 md:pb-6 bg-white border-t border-slate-100 grid grid-cols-2 gap-3 shrink-0 shadow-[0_-4px_12px_rgba(0,0,0,0.05)]"
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
    </div>
    <!-- end full-height wrapper -->

    <VariantSelectorModal
        product={selectedVariantProduct}
        show={showVariantModal}
        onClose={() => (showVariantModal = false)}
        {primary}
        {secondary}
        user={auth}
    />

    <LocationModal
        show={showLocationModal}
        onClose={() => (showLocationModal = false)}
        {selectedLocation}
        onSelectLocation={(loc) => {
            selectedLocation = loc;
            applyFilters(false);
        }}
        {primary}
    />
</StorefrontLayout>
