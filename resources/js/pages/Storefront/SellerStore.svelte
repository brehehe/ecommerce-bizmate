<script lang="ts">
    import StorefrontLayout from '@/components/layouts/StorefrontLayout.svelte';
    import { Link, page, router } from '@inertiajs/svelte';
    import Pagination from '@/components/ui/Pagination.svelte';
    import VariantSelectorModal from '@/components/Storefront/VariantSelectorModal.svelte';
    import FoodModifierModal from '@/components/Storefront/FoodModifierModal.svelte';
    import { showToast } from '@/utils/toast';

    let {
        seller,
        products = undefined,
        filters = { q: '', sort: 'latest' },
        storeName = '',
        isFoodPick: propIsFoodPick = false,
    } = $props();

    const primary = $derived(page.props.theme?.primary_color || '#0c4cb4');
    const secondary = $derived(page.props.theme?.secondary_color || '#fa7315');
    const auth = $derived(page.props.auth?.user);
    const cartButtonStyle = $derived(
        (page.props.settings as any)?.storefront_cart_button_style || 'button',
    );
    const isSellerEnabled = $derived(
        (page.props as any).app_config?.is_seller_enabled ??
            (page.props as any).settings?.is_seller_enabled ??
            false,
    );
    const resto = $derived(seller?.resto_profile || seller?.restoProfile);
    const isFoodPick = $derived(
        propIsFoodPick ||
            Boolean((page.props as any).isFoodPick) ||
            Boolean((page.props as any).is_foodpick) ||
            Boolean((page.props as any).app_config?.is_foodpick) ||
            Boolean((page.props as any).settings?.is_foodpick) ||
            Boolean(resto) ||
            false,
    );
    const isCheckoutWithoutLogin = $derived(
        (page.props as any).is_checkout_without_login ??
            (page.props as any).app_config?.is_checkout_without_login ??
            true,
    );

    const isAdmin = $derived.by(() => {
        return (
            auth?.roles?.some(
                (r: any) => r.name === 'Super Admin' || r.name === 'Admin',
            ) ?? false
        );
    });

    const canManageStore = $derived.by(() => {
        if (!auth) return false;
        if (isAdmin) return true;
        if (auth.is_seller && seller) {
            return auth.id === seller.id;
        }
        return false;
    });

    let searchQ = $state('');
    let selectedSort = $state('latest');

    $effect(() => {
        searchQ = filters?.q || '';
        selectedSort = filters?.sort || 'latest';
    });

    let selectedVariantProduct = $state<any>(null);
    let showVariantModal = $state(false);
    let selectedFoodProduct = $state<any>(null);
    let showFoodModifierModal = $state(false);

    function formatPrice(price: any) {
        const n = Number(price);
        if (!n) return 'Rp 0';
        return 'Rp ' + n.toLocaleString('id-ID');
    }

    function getProductImage(product: any) {
        let path: string | null = null;
        if (product?.images?.length > 0) {
            path = product.images[0].url || product.images[0].path;
        } else if (product?.image) {
            path = product.image;
        }
        if (!path || typeof path !== 'string') { return null; }
        if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/')) {
            return path;
        }
        return '/' + path;
    }

    function applyFilters() {
        router.get(
            `/${seller.store_slug}`,
            { q: searchQ, sort: selectedSort },
            { preserveState: true, replace: true },
        );
    }

    function handleAddToCart(product: any, e: MouseEvent) {
        e.preventDefault();
        e.stopPropagation();

        if (!auth && !isCheckoutWithoutLogin) {
            window.dispatchEvent(new CustomEvent('open-login-modal'));
            return;
        }

        if (resto && resto.is_open_now === false) {
            showToast('Resto sedang tutup sementara dan tidak dapat menerima pesanan saat ini.', 'error');
            return;
        }

        if (isFoodPick) {
            selectedFoodProduct = product;
            showFoodModifierModal = true;
            return;
        }

        if (product.variants && product.variants.length > 0) {
            selectedVariantProduct = product;
            showVariantModal = true;
            return;
        }

        router.post(
            '/cart',
            { product_id: product.id, product_variant_id: null, quantity: 1 },
            {
                preserveScroll: true,
                onSuccess: (pageResp: any) => {
                    const flashSuccess = pageResp.props?.flash?.success;
                    showToast(flashSuccess || 'Produk berhasil ditambahkan ke keranjang!', 'success');
                },
                onError: () => showToast('Gagal menambahkan ke keranjang.', 'error'),
            },
        );
    }

    const sellerLogoUrl = $derived.by(() => {
        if (seller?.store_logo) return `/storage/${seller.store_logo}`;
        if (resto?.logo_path) {
            return resto.logo_path.startsWith('http') ? resto.logo_path : `/storage/${resto.logo_path.replace(/^\/+/, '')}`;
        }
        if (resto?.logo_url) return resto.logo_url;
        return null;
    });

    const restoBannerUrl = $derived.by(() => {
        if (resto?.banner_url) return resto.banner_url;
        if (resto?.banner_path) {
            return resto.banner_path.startsWith('http') ? resto.banner_path : `/storage/${resto.banner_path.replace(/^\/+/, '')}`;
        }
        return null;
    });

    const productList = $derived(products?.data ?? []);
    const totalProducts = $derived(products?.total ?? 0);

    let selectedMenuCategory = $state<string | null>(null);

    const menuCategories = $derived.by(() => {
        if (!productList || productList.length === 0) return [];
        const cats = new Set<string>();
        for (const p of productList) {
            if (p.category?.name) {
                cats.add(p.category.name);
            }
        }
        return Array.from(cats);
    });

    const displayProducts = $derived.by(() => {
        if (!isFoodPick || !selectedMenuCategory) return productList;
        return productList.filter((p: any) => p.category?.name === selectedMenuCategory);
    });

    function stripHtml(html: string | null | undefined): string {
        if (!html) return '';
        return html.replace(/<[^>]*>?/gm, '').trim();
    }

    const sortOptions = [
        { id: 'latest', label: 'Terbaru' },
        { id: 'popular', label: 'Terlaris' },
        { id: 'price_asc', label: 'Harga Terendah' },
        { id: 'price_desc', label: 'Harga Tertinggi' },
    ];
</script>

<svelte:head>
    <title>{seller?.store_name || seller?.name} — {storeName || 'Toko'}</title>
    <meta name="description" content={seller?.store_description || `Belanja di toko ${seller?.store_name || seller?.name}`} />
</svelte:head>

<StorefrontLayout>
    {#if isFoodPick}
        <!-- ── FoodPick Navigation Breadcrumb ───────────────────── -->
        <div class="bg-slate-50/90 border-b border-slate-200/60 py-2.5">
            <div class="max-w-6xl mx-auto px-4 sm:px-6">
                <Link
                    href="/"
                    class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-600 hover:text-slate-900 transition cursor-pointer"
                >
                    <i class="ti ti-arrow-left text-sm"></i>
                    <span>Kembali ke Direktori Resto</span>
                </Link>
            </div>
        </div>
    {/if}

    <!-- ── Store Hero Header ─────────────────────────────── -->
    <div class="bg-white border-b border-slate-200/80">
        <!-- Cover / Accent Strip -->
        <div class="h-36 sm:h-52 lg:h-60 bg-slate-900 relative overflow-hidden">
            {#if restoBannerUrl}
                <img
                    src={restoBannerUrl}
                    alt={resto?.resto_name || seller?.store_name}
                    class="w-full h-full object-cover"
                />
            {:else if isFoodPick}
                <img
                    src="/images/food-hero.jpg"
                    alt="Foto Dapur Resto"
                    class="w-full h-full object-cover opacity-90"
                />
            {:else}
                <div class="absolute inset-0 bg-gradient-to-r from-slate-800 via-slate-900 to-slate-800"></div>
                <div class="absolute inset-0 bg-gradient-to-r from-amber-600/30 via-orange-600/20 to-transparent"></div>
                <div class="absolute inset-0 bg-slate-900"></div>
            {/if}
            <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent pointer-events-none"></div>
        </div>

        <!-- Store Info Container (Positioned fully visible with zero overlap bugs) -->
        <div class="max-w-6xl mx-auto px-4 sm:px-6 relative z-10">
            <div class="flex flex-col sm:flex-row sm:items-start gap-4 sm:gap-6 pb-5 sm:pb-6">
                <!-- Store Logo: Only the logo has negative margin to overlap banner nicely -->
                <div
                    class="shrink-0 -mt-12 sm:-mt-16 w-24 h-24 sm:w-28 sm:h-28 rounded-2xl sm:rounded-3xl border-4 border-white shadow-md overflow-hidden text-white flex items-center justify-center relative z-20 bg-slate-900"
                >
                    {#if sellerLogoUrl}
                        <img
                            src={sellerLogoUrl}
                            alt={resto?.resto_name || seller?.store_name}
                            class="w-full h-full object-cover"
                            onerror={(e) => {
                                (e.currentTarget as HTMLImageElement).style.display = 'none';
                            }}
                        />
                    {:else}
                        <div class="flex flex-col items-center justify-center gap-0.5">
                            <i class="ti ti-tools-kitchen-2 text-2xl sm:text-3xl text-amber-400"></i>
                            <span class="text-white text-xs font-black uppercase tracking-wider">
                                {(resto?.resto_name || seller?.store_name || seller?.name || '?').substring(0, 2)}
                            </span>
                        </div>
                    {/if}
                </div>

                <!-- Store Info & Metadata: Placed completely on white background below banner -->
                <div class="flex-1 min-w-0 pt-2 sm:pt-3">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <h1 class="font-outfit font-black text-2xl sm:text-3xl text-slate-900 tracking-tight leading-tight">
                                    {resto?.resto_name || seller?.store_name || seller?.name || 'Resto'}
                                </h1>
                                {#if resto?.approval_status === 'approved'}
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200" title="Resto Resmi Terverifikasi">
                                        <i class="ti ti-circle-check-filled text-blue-600 text-xs"></i>
                                        <span>Terverifikasi</span>
                                    </span>
                                {/if}
                            </div>
                            {#if seller?.store_slug && !isFoodPick}
                                <p class="text-xs text-slate-400 mt-1 font-mono flex items-center gap-1">
                                    <i class="ti ti-at text-slate-300"></i>
                                    <span>{seller.store_slug}</span>
                                </p>
                            {/if}
                        </div>
                    </div>

                    {#if seller?.store_description}
                        <p class="text-xs sm:text-sm text-slate-600 mt-1.5 line-clamp-2 max-w-2xl leading-relaxed">
                            {seller.store_description}
                        </p>
                    {/if}

                    <!-- Unified Resto Information Row -->
                    <div class="mt-3 flex items-center gap-x-2.5 gap-y-2 flex-wrap text-xs text-slate-600">
                        {#if resto}
                            <!-- Kategori Kuliner -->
                            <span
                                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold text-white shadow-2xs"
                                style="background-color: {secondary};"
                            >
                                <i class="ti ti-tools-kitchen text-xs"></i>
                                <span>{resto.culinary_category || 'Resto Mitra'}</span>
                            </span>

                            <!-- Status Buka / Tutup -->
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold {resto.is_open_now !== false ? 'text-emerald-700 bg-emerald-50 border border-emerald-300' : 'text-slate-600 bg-slate-100 border border-slate-200'}">
                                <span class="w-2 h-2 rounded-full {resto.is_open_now !== false ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400'}"></span>
                                <span>{resto.is_open_now !== false ? 'Buka Sekarang' : 'Tutup Sementara'}</span>
                            </span>

                            <!-- Rating Resto Real-Time -->
                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-white border border-slate-200 text-xs font-bold text-amber-700 shadow-2xs">
                                <i class="ti ti-star-filled text-amber-500 text-xs"></i>
                                <span>{resto.reviews_avg_rating ? `${Number(resto.reviews_avg_rating).toFixed(1)} (${resto.reviews_count} ulasan)` : 'Rating Baru'}</span>
                            </span>

                            <!-- Waktu Masak & Jumlah Menu -->
                            {#if resto.avg_prep_time_minutes}
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white border border-slate-200 text-slate-700 text-xs font-medium shadow-2xs">
                                    <i class="ti ti-clock text-slate-400"></i>
                                    <span>Siap ~{resto.avg_prep_time_minutes} Menit</span>
                                    <span class="text-slate-300">|</span>
                                    <span>{totalProducts} {isFoodPick ? 'menu' : 'produk'}</span>
                                </span>
                            {/if}
                        {:else}
                            <span class="font-medium text-slate-800">Toko Resmi</span>
                            <span class="text-slate-300">•</span>
                            <span class="text-slate-500 font-medium">
                                {totalProducts} produk
                            </span>
                        {/if}

                        <!-- Alamat Resto jika ada -->
                        {#if resto?.address}
                            <span class="text-slate-500 flex items-center gap-1 text-xs" title={resto.address}>
                                <i class="ti ti-map-pin text-slate-400"></i>
                                <span class="truncate max-w-xs">{resto.address}</span>
                            </span>
                        {/if}
                    </div>

                    <!-- Owner Utilities Bar (Clearly Separated) -->
                    {#if canManageStore}
                        <div class="mt-4 pt-3 border-t border-slate-100 flex items-center gap-2 flex-wrap">
                            <span class="text-[11px] font-semibold text-slate-400 mr-1 flex items-center gap-1">
                                <i class="ti ti-user-cog text-xs"></i> Mode Pemilik:
                            </span>
                            <Link
                                href="/admin/products/create"
                                class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-bold rounded-lg text-white shadow-xs transition hover:opacity-90 cursor-pointer"
                                style="background-color: {primary};"
                            >
                                <i class="ti ti-plus text-xs"></i>
                                <span>{isFoodPick ? 'Tambah Menu' : 'Tambah Produk'}</span>
                            </Link>
                            <Link
                                href="/admin/products"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-white text-slate-700 hover:bg-slate-50 border border-slate-200 transition cursor-pointer shadow-2xs"
                            >
                                <i class="ti ti-packages text-xs text-slate-500"></i>
                                <span>Kelola Produk</span>
                            </Link>
                            {#if isFoodPick}
                                <Link
                                    href="/admin/kitchen"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-white hover:bg-slate-50 border border-slate-200 transition cursor-pointer shadow-2xs"
                                    style="color: {secondary};"
                                >
                                    <i class="ti ti-tools-kitchen text-xs"></i>
                                    <span>Layar Dapur (KDS)</span>
                                </Link>
                            {/if}
                        </div>
                    {/if}
                </div>
            </div>
        </div>
    </div>
    <!-- ── Search & Filter Bar ────────────────────────────── -->
    <div class="sticky top-0 z-30 bg-white/95 backdrop-blur-md border-b border-slate-200/80 shadow-xs">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-3">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <!-- Category Tabs (if categories exist) -->
                {#if isFoodPick && menuCategories.length > 0}
                    <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar py-0.5">
                        <button
                            type="button"
                            onclick={() => selectedMenuCategory = null}
                            class="shrink-0 px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer whitespace-nowrap {selectedMenuCategory === null ? 'text-white shadow-xs' : 'bg-slate-50 text-slate-700 hover:bg-slate-100 border border-slate-200/80'}"
                            style={selectedMenuCategory === null ? `background-color: ${primary};` : ''}
                        >
                            Semua Menu
                        </button>
                        {#each menuCategories as cat}
                            <button
                                type="button"
                                onclick={() => selectedMenuCategory = selectedMenuCategory === cat ? null : cat}
                                class="shrink-0 px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer whitespace-nowrap {selectedMenuCategory === cat ? 'text-white shadow-xs' : 'bg-slate-50 text-slate-700 hover:bg-slate-100 border border-slate-200/80'}"
                                style={selectedMenuCategory === cat ? `background-color: ${primary};` : ''}
                            >
                                {cat}
                            </button>
                        {/each}
                    </div>
                {:else if isFoodPick}
                    <div class="flex items-center gap-2">
                        <h2 class="font-outfit font-black text-sm sm:text-base text-slate-800 flex items-center gap-2">
                            <i class="ti ti-tools-kitchen text-amber-500"></i>
                            <span>Daftar Menu Hidangan</span>
                        </h2>
                        <span class="text-[11px] font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full">
                            {totalProducts} menu
                        </span>
                    </div>
                {/if}

                <div class="flex items-center gap-2.5 flex-1 sm:justify-end">
                    <!-- Search Input -->
                    <form
                        onsubmit={(e) => { e.preventDefault(); applyFilters(); }}
                        class="flex-1 sm:max-w-xs relative"
                    >
                        <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input
                            type="text"
                            bind:value={searchQ}
                            placeholder={isFoodPick ? "Cari menu makanan..." : "Cari produk di toko ini..."}
                            class="w-full pl-8 pr-7 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:bg-white focus:border-slate-400 transition"
                        />
                        {#if searchQ}
                            <button
                                type="button"
                                onclick={() => { searchQ = ''; applyFilters(); }}
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-xs cursor-pointer"
                            >
                                <i class="ti ti-x"></i>
                            </button>
                        {/if}
                    </form>

                    <!-- Sort Select -->
                    <select
                        bind:value={selectedSort}
                        onchange={applyFilters}
                        class="shrink-0 pl-3 pr-8 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:bg-white focus:border-slate-400 transition appearance-none cursor-pointer"
                    >
                        {#each sortOptions as opt}
                            <option value={opt.id}>{opt.label}</option>
                        {/each}
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Menu / Product List ────────────────────────────── -->
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-6 sm:py-8">
        {#if displayProducts.length === 0}
            <div class="py-20 text-center">
                <div
                    class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4"
                    style="background-color: {primary}15;"
                >
                    <i class="ti ti-package-off text-3xl" style="color: {primary};"></i>
                </div>
                <p class="font-outfit font-black text-xl text-slate-700 mb-1">
                    {filters.q ? (isFoodPick ? 'Menu Tidak Ditemukan' : 'Produk Tidak Ditemukan') : (isFoodPick ? 'Belum Ada Menu Makanan' : 'Belum Ada Produk')}
                </p>
                <p class="text-sm text-slate-400">
                    {#if filters.q}
                        Coba kata kunci lain atau hapus pencarian.
                    {:else}
                        {isFoodPick ? 'Resto ini belum memiliki menu makanan yang siap dipesan.' : 'Toko ini belum memiliki produk aktif.'}
                    {/if}
                </p>
                {#if filters.q}
                    <button
                        type="button"
                        onclick={() => { searchQ = ''; applyFilters(); }}
                        class="mt-4 px-5 py-2 text-sm font-bold rounded-xl text-white cursor-pointer"
                        style="background-color: {primary};"
                    >
                        Hapus Pencarian
                    </button>
                {:else if canManageStore}
                    <div class="mt-5 flex items-center justify-center gap-3">
                        <Link
                            href="/admin/products/create"
                            class="px-5 py-2.5 text-sm font-bold rounded-xl text-white shadow-xs hover:opacity-90 active:scale-95 inline-flex items-center gap-2 cursor-pointer transition"
                            style="background-color: {primary};"
                        >
                            <i class="ti ti-plus text-base font-bold"></i>
                            <span>{isFoodPick ? 'Tambah Menu Sekarang' : 'Tambah Produk Sekarang'}</span>
                        </Link>
                    </div>
                {/if}
            </div>
        {:else if isFoodPick}
            <!-- ═══════════════════════════════════════════════════
             FOODPICK CULINARY MENU CARDS (SPECIFICATION UI/UX)
            ═══════════════════════════════════════════════════ -->
            {#if resto && resto.is_open_now === false}
                <div class="mb-4 p-4 rounded-2xl bg-amber-50 border border-amber-200 flex items-start gap-3">
                    <span class="p-2 rounded-xl bg-amber-100 text-amber-800 shrink-0">
                        <i class="ti ti-door-exit text-lg"></i>
                    </span>
                    <div>
                        <h3 class="text-sm font-bold text-amber-900">
                            Resto Sedang Tutup Sementara
                        </h3>
                        <p class="text-xs text-amber-700 mt-0.5 leading-relaxed">
                            Resto ini sedang tidak dapat menerima pesanan baru saat ini. Anda tetap dapat menjelajahi daftar menu yang tersedia.
                        </p>
                    </div>
                </div>
            {/if}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5 sm:gap-4">
                {#each displayProducts as product (product.id)}
                    {@const img = getProductImage(product)}
                    {@const isPromo = product.is_promo}
                    {@const price = isPromo ? product.promo_price : (product.product_price?.price ?? product.price ?? 0)}
                    {@const originalPrice = isPromo ? product.original_price : 0}
                    {@const discountPct = isPromo ? product.discount_percentage : 0}
                    {@const cleanDesc = stripHtml(product.description || product.summary)}

                    <div class="group bg-white rounded-2xl border border-slate-200/80 hover:border-slate-300 p-3.5 sm:p-4 shadow-2xs hover:shadow-md transition-all flex flex-col justify-between gap-3 relative">
                        <div class="flex items-start justify-between gap-3 sm:gap-4">
                            <!-- Left: Details -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <h3 class="font-outfit font-black text-sm sm:text-base text-slate-900 group-hover:opacity-85 transition-opacity line-clamp-1">
                                        {product.name}
                                    </h3>
                                    {#if isPromo && discountPct > 0}
                                        <span
                                            class="text-white text-[10px] font-bold px-1.5 py-0.5 rounded-md shadow-2xs"
                                            style="background-color: {secondary};"
                                        >
                                            -{discountPct}%
                                        </span>
                                    {/if}
                                </div>

                                {#if cleanDesc}
                                    <p class="text-xs text-slate-500 mt-1 line-clamp-2 leading-relaxed">
                                        {cleanDesc}
                                    </p>
                                {/if}

                                <!-- Price -->
                                <div class="mt-2.5 flex items-baseline gap-2">
                                    <span class="font-black text-sm sm:text-base tracking-tight" style="color: {primary};">
                                        {formatPrice(price)}
                                    </span>
                                    {#if isPromo && originalPrice > 0}
                                        <span class="text-xs text-slate-400 line-through font-semibold">
                                            {formatPrice(originalPrice)}
                                        </span>
                                    {/if}
                                </div>
                            </div>

                            <!-- Right: Food Photo with optional Edit link -->
                            <div class="relative shrink-0">
                                {#if img}
                                    <img
                                        src={img}
                                        alt={product.name}
                                        class="w-24 h-24 sm:w-28 sm:h-28 rounded-xl object-cover border border-slate-100 group-hover:scale-102 transition duration-200"
                                        onerror={(e) => { (e.currentTarget as HTMLImageElement).src = '/images/default-resto-dish.jpg'; }}
                                    />
                                {:else}
                                    <div class="w-24 h-24 sm:w-28 sm:h-28 rounded-xl bg-slate-100 flex items-center justify-center text-slate-400 border border-slate-200/60">
                                        <i class="ti ti-tools-kitchen-2 text-2xl"></i>
                                    </div>
                                {/if}

                                {#if canManageStore}
                                    <Link
                                        href={`/admin/products/${product.id}/edit`}
                                        class="absolute top-1 right-1 p-1 rounded-md bg-white/95 hover:bg-white text-slate-600 hover:text-amber-700 text-xs shadow-xs border border-slate-200 transition cursor-pointer"
                                        title="Edit Menu Ini"
                                    >
                                        <i class="ti ti-pencil"></i>
                                    </Link>
                                {/if}
                            </div>
                        </div>

                        <!-- Bottom Action Bar (Always visible for FoodPick!) -->
                        <div class="pt-2.5 border-t border-slate-100 flex items-center justify-between gap-2">
                            <span class="text-[11px] text-slate-400 flex items-center gap-1">
                                {#if (product.menu_modifiers?.length > 0) || (product.menuModifiers?.length > 0)}
                                    <i class="ti ti-flame text-amber-500"></i>
                                    <span>Pilihan level & topping</span>
                                {:else}
                                    <i class="ti ti-sparkles text-emerald-500"></i>
                                    <span>Menu favorit segar</span>
                                {/if}
                            </span>

                            <button
                                type="button"
                                disabled={resto?.is_open_now === false}
                                onclick={(e) => handleAddToCart(product, e)}
                                class="inline-flex items-center gap-1.5 px-4 py-1.5 sm:py-2 rounded-xl text-xs font-bold transition {resto?.is_open_now === false ? 'bg-slate-200 text-slate-400 cursor-not-allowed border border-slate-200' : 'text-white shadow-xs hover:opacity-90 active:scale-95 cursor-pointer'}"
                                style={resto?.is_open_now !== false ? `background-color: ${primary};` : ''}
                            >
                                <i class="ti {resto?.is_open_now === false ? 'ti-lock' : 'ti-plus'} text-xs"></i>
                                <span>{resto?.is_open_now === false ? 'Resto Tutup' : 'Tambah ke Nampan'}</span>
                            </button>
                        </div>
                    </div>
                {/each}
            </div>

            <!-- Pagination Component -->
            <Pagination
                data={products}
                itemLabel="Menu"
                {primary}
                class="mt-8 flex flex-col sm:flex-row gap-3.5 sm:items-center sm:justify-between py-4 w-full"
            />
        {:else}
            <!-- ═══════════════════════════════════════════════════
             STANDARD E-COMMERCE 4-COLUMN PRODUCT GRID
            ═══════════════════════════════════════════════════ -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                {#each displayProducts as product (product.id)}
                    {@const img = getProductImage(product)}
                    {@const isPromo = product.is_promo}
                    {@const price = isPromo ? product.promo_price : (product.product_price?.price ?? product.price ?? 0)}
                    {@const originalPrice = isPromo ? product.original_price : 0}
                    {@const discountPct = isPromo ? product.discount_percentage : 0}

                    <div class="relative group bg-white border border-slate-100 hover:border-slate-200 hover:shadow-lg rounded-xl overflow-hidden transition flex flex-col h-full">
                        <a
                            href={`/products/${product.id}`}
                            class="flex flex-col flex-1 cursor-pointer"
                        >
                            <!-- Product Image -->
                            <div class="relative aspect-square overflow-hidden border-b border-slate-50">
                                {#if img}
                                    <img
                                        src={img}
                                        alt={product.name}
                                        class="w-full h-full object-cover group-hover:scale-105 transition duration-300"
                                        onerror={(e) => {
                                            (e.currentTarget as HTMLImageElement).src = '/noimage/image.png';
                                        }}
                                    />
                                {:else}
                                    <img
                                        src="/noimage/image.png"
                                        alt="Produk tanpa gambar"
                                        class="w-full h-full object-cover"
                                    />
                                {/if}

                                <div class="absolute top-1.5 left-1.5 z-10 flex flex-col gap-1 items-start pointer-events-none">
                                    {#if isSellerEnabled && !isFoodPick}
                                        <span
                                            class="text-white text-[9px] font-black px-1.5 py-0.5 rounded-md shadow-xs {product.condition === 'rent' ? 'bg-purple-600' : (product.condition === 'used' || product.condition === 'second' ? 'bg-amber-600' : 'bg-emerald-600')}"
                                        >
                                            {product.condition === 'rent' ? 'Rent' : (product.condition === 'used' || product.condition === 'second' ? 'Second' : 'New')}
                                        </span>
                                    {/if}
                                    {#if isPromo && discountPct > 0}
                                        <span
                                            class="text-white text-[9px] font-black px-1.5 py-0.5 rounded-md shadow-sm"
                                            style="background-color: {secondary};"
                                        >
                                            -{discountPct}%
                                        </span>
                                    {/if}
                                </div>

                                {#if canManageStore}
                                    <div class="absolute top-1.5 right-1.5 z-20">
                                        <Link
                                            href={`/admin/products/${product.id}/edit`}
                                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-white/95 hover:bg-white text-amber-700 hover:text-amber-800 text-[10px] font-bold shadow-xs border border-amber-200/80 transition active:scale-95"
                                            title="Edit Produk Ini"
                                            onclick={(e) => e.stopPropagation()}
                                        >
                                            <i class="ti ti-pencil text-[10px]"></i>
                                            Edit
                                        </Link>
                                    </div>
                                {/if}
                            </div>

                            <!-- Product Info -->
                            <div class="p-2.5 sm:p-3 flex-1 flex flex-col justify-between">
                                <div>
                                    <p class="text-xs sm:text-sm font-medium text-slate-800 line-clamp-2 leading-snug mb-1">
                                        {product.name}
                                    </p>
                                    {#if product.avg_rating}
                                        <div class="flex items-center gap-1 mb-1">
                                            <i class="ti ti-star-filled text-[10px] text-amber-400"></i>
                                            <span class="text-[10px] text-slate-500">
                                                {Number(product.avg_rating).toFixed(1)}
                                                {#if product.review_count}
                                                    ({product.review_count})
                                                {/if}
                                            </span>
                                        </div>
                                    {/if}
                                </div>
                                <div class="mt-0.5">
                                    <p
                                        class="text-base sm:text-lg font-black leading-tight tracking-tight"
                                        style="color: {primary};"
                                    >
                                        {formatPrice(price)}
                                    </p>
                                    {#if isPromo && originalPrice > 0}
                                        <p class="text-sm text-red-600 line-through font-bold">
                                            {formatPrice(originalPrice)}
                                        </p>
                                    {/if}
                                </div>
                            </div>
                        </a>

                        <!-- Add to Cart Button -->
                        {#if cartButtonStyle !== 'none'}
                            <div class="px-2.5 pb-2.5">
                                <button
                                    type="button"
                                    onclick={(e) => handleAddToCart(product, e)}
                                    class="w-full py-1.5 text-xs font-bold rounded-lg border transition cursor-pointer"
                                    style="border-color: {primary}; color: {primary};"
                                    class:hover:text-white={true}
                                >
                                    + Keranjang
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

    <!-- ── FLOATING ACTION BUTTON: Tambah Produk (Super Admin, Admin, & Store Owner) ── -->
    {#if canManageStore}
        <aside
            aria-label="Aksi Cepat Kelola Toko"
            class="fixed z-40 flex items-center gap-2 bottom-20 right-4 sm:bottom-24 sm:right-6 transition-all duration-300 pointer-events-auto"
        >
            <Link
                href="/admin/products/create"
                class="flex items-center gap-2.5 px-4 py-2.5 rounded-full text-white shadow-lg hover:opacity-95 active:scale-95 transition-all duration-200 cursor-pointer group"
                style="background-color: {primary};"
                title="Bypass Tambah Produk Baru"
            >
                <div class="w-7 h-7 rounded-full bg-white/20 flex items-center justify-center shrink-0 group-hover:rotate-90 transition-transform duration-300">
                    <i class="ti ti-plus text-sm text-white font-bold"></i>
                </div>
                <div class="flex flex-col text-left pr-1">
                    <span class="text-xs font-black leading-tight tracking-tight">Tambah Produk</span>
                    <span class="text-[9px] text-white/80 font-semibold leading-none">
                        {isAdmin ? 'Akses Admin' : 'Seller Toko'}
                    </span>
                </div>
            </Link>
        </aside>
    {/if}
</StorefrontLayout>

{#if showVariantModal && selectedVariantProduct}
    <VariantSelectorModal
        product={selectedVariantProduct}
        onClose={() => { showVariantModal = false; selectedVariantProduct = null; }}
    />
{/if}

{#if showFoodModifierModal && selectedFoodProduct}
    <FoodModifierModal
        product={selectedFoodProduct}
        show={showFoodModifierModal}
        {primary}
        {secondary}
        onClose={() => { showFoodModifierModal = false; selectedFoodProduct = null; }}
    />
{/if}
