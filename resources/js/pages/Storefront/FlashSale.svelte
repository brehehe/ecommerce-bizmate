<script lang="ts">
    import StorefrontLayout from '@/components/layouts/StorefrontLayout.svelte';
    import { page, Link, router } from '@inertiajs/svelte';
    import { onMount, onDestroy } from 'svelte';

    let {
        products = { data: [], links: [] },
        activeFlashSale = null,
        storeName = ''
    } = $props();

    const primary = $derived(page.props.theme?.primary_color || '#0c4cb4');
    const secondary = $derived(page.props.theme?.secondary_color || '#fa7315');

    // Search state & handler
    let searchQ = $state('');

    function handleSearch() {
        if (searchQ.trim()) {
            router.get('/search', { q: searchQ });
        }
    }

    // ──────────────────────────────────────────────────
    // FLASH SALE COUNTDOWN
    // ──────────────────────────────────────────────────
    let flashSaleEnd = $state<Date | null>(null);
    let countdown = $state({ h: '00', m: '00', s: '00' });
    let countdownTimer: ReturnType<typeof setInterval>;

    function updateCountdown() {
        if (!flashSaleEnd) {
            countdown = { h: '00', m: '00', s: '00' };
            return;
        }
        const diff = flashSaleEnd.getTime() - Date.now();
        if (diff <= 0) {
            countdown = { h: '00', m: '00', s: '00' };
            return;
        }
        const h = Math.floor(diff / 3_600_000);
        const m = Math.floor((diff % 3_600_000) / 60_000);
        const s = Math.floor((diff % 60_000) / 1_000);
        countdown = {
            h: String(h).padStart(2, '0'),
            m: String(m).padStart(2, '0'),
            s: String(s).padStart(2, '0'),
        };
    }

    onMount(() => {
        if (activeFlashSale?.end_time) {
            const timeStr = String(activeFlashSale.end_time).replace(' ', 'T');
            flashSaleEnd = new Date(timeStr);
        } else {
            flashSaleEnd = new Date();
            flashSaleEnd.setHours(flashSaleEnd.getHours() + 5, 30, 0, 0);
        }
        updateCountdown();
        countdownTimer = setInterval(updateCountdown, 1000);
    });
    
    onDestroy(() => clearInterval(countdownTimer));

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
            const newItems = (products.data || []).filter((p: any) => !existingIds.has(p.id));
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
                    'X-Inertia-Partial-Component': 'Storefront/FlashSale',
                    'X-Inertia-Partial-Data': 'products',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Inertia-Version': page.version || ''
                }
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
                    const existingIds = new Set(allProducts.map((p: any) => p.id));
                    const filteredNewItems = newItems.filter((p: any) => !existingIds.has(p.id));
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
            { rootMargin: '200px' }
        );
        observer.observe(node);
        return {
            destroy() {
                observer.disconnect();
            }
        };
    }
</script>

<svelte:head>
    <title>Flash Sale ⚡ - {storeName || 'Toko Kami'}</title>
</svelte:head>

<StorefrontLayout hideMobileHeader={true} hideMobileFooter={true}>

    <!-- ═══════════════════════════════════════════════════
     STICKY MOBILE TOP BAR (mobile only, replaces global header)
    ═══════════════════════════════════════════════════ -->
    <div
        class="md:hidden fixed top-0 left-0 right-0 z-40 bg-white border-b border-slate-100 shadow-sm"
    >
        <!-- Header row with Back button and Inline Search Input -->
        <div class="flex items-center gap-2 px-3 py-2.5" style="background-color: {primary};">
            <!-- Back button -->
            <button
                onclick={() => history.back()}
                class="shrink-0 w-9 h-9 flex items-center justify-center rounded-full bg-white/20 text-white active:scale-90 transition"
                aria-label="Kembali"
            >
                <i class="ti ti-arrow-left text-xl"></i>
            </button>

            <!-- Inline search input -->
            <form
                onsubmit={(e) => { e.preventDefault(); handleSearch(); }}
                class="flex-grow"
            >
                <div class="relative">
                    <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm pointer-events-none"></i>
                    <input
                        type="text"
                        bind:value={searchQ}
                        placeholder="Cari produk, merek..."
                        class="w-full pl-9 pr-4 py-2 text-sm bg-white rounded-xl border-0 focus:outline-none focus:ring-2 shadow-sm"
                        style="--tw-ring-color: {primary}40;"
                    />
                    {#if searchQ}
                        <button
                            type="button"
                            onclick={() => { searchQ = ''; }}
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition"
                        >
                            <i class="ti ti-x text-sm"></i>
                        </button>
                    {/if}
                </div>
            </form>
        </div>
    </div>

    <!-- Spacer for mobile sticky bar -->
    <div class="md:hidden h-[50px]"></div>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pt-5 pb-8 md:py-8 flex-grow">
        <!-- Desktop Header (Desktop only, no tabs) -->
        <div class="hidden md:flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <h1 class="font-outfit font-black text-xl sm:text-2xl text-slate-800 flex items-center gap-2">
                ⚡ Flash Sale
            </h1>
            
            <p class="text-xs text-slate-400 font-bold uppercase tracking-wider self-end md:self-auto">
                Menampilkan {products.from || 0} - {products.to || 0} dari {products.total || 0} produk
            </p>
        </div>

        <div class="w-full">
            <!-- ═══════════════════════════════════════════════════
             PRODUCT GRID
            ═══════════════════════════════════════════════════ -->
            <div class="flex-grow flex flex-col min-w-0">
                <!-- Flash Sale Info Banner (if flash_sale is active) -->
                {#if activeFlashSale}
                    <div class="mb-6 p-4 rounded-[20px] text-white flex flex-col sm:flex-row items-center justify-between gap-4 shadow-xs" style="background: linear-gradient(135deg, {primary}, {secondary});">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-white shrink-0">
                                <i class="ti ti-bolt text-2xl animate-pulse"></i>
                            </div>
                            <div>
                                <h3 class="font-outfit font-black text-sm sm:text-base leading-tight">Flash Sale Sedang Berlangsung!</h3>
                                <p class="text-[11px] text-white/85 mt-0.5">Dapatkan diskon heboh sebelum waktu habis.</p>
                            </div>
                        </div>
                        <!-- Countdown timer -->
                        <div class="flex items-center gap-1.5 bg-black/35 rounded-xl px-3 py-2 backdrop-blur-sm shrink-0">
                            <span class="text-white text-[10px] font-bold mr-1">Berakhir dalam</span>
                            {#each [countdown.h, countdown.m, countdown.s] as unit, ui}
                                {#if ui > 0}<span class="text-white/60 font-bold text-xs">:</span>{/if}
                                <span class="bg-white font-black text-xs px-2 py-0.5 rounded-md min-w-[24px] text-center tabular-nums" style="color: {primary};">
                                    {unit}
                                </span>
                            {/each}
                        </div>
                    </div>
                {:else}
                    <!-- Flash Sale is not active empty state -->
                    <div class="mb-6 p-6 rounded-[20px] bg-slate-100/70 border border-slate-200/50 text-slate-500 text-center">
                        <i class="ti ti-bolt-off text-3xl mb-2 block text-slate-400"></i>
                        <p class="text-xs font-bold">Saat ini tidak ada Flash Sale yang sedang aktif.</p>
                        <p class="text-[10px] text-slate-400 mt-0.5">Silakan kembali di lain waktu atau lihat produk terlaris kami!</p>
                    </div>
                {/if}

                {#if allProducts.length === 0 && !isLoadingMore}
                    <div class="bg-white border border-slate-150 rounded-[28px] py-16 px-6 sm:px-12 text-center max-w-4xl mx-auto w-full transition-all duration-300">
                        <div class="w-16 h-16 sm:w-20 sm:h-20 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-6 text-slate-300">
                            <i class="ti ti-package-off text-3xl sm:text-4xl"></i>
                        </div>
                        
                        <h3 class="text-[#0a1d37] font-bold text-xl sm:text-2xl mb-2 tracking-tight">Produk Tidak Ditemukan</h3>
                        
                        <p class="text-slate-400 text-xs sm:text-sm max-w-md mx-auto leading-relaxed mt-2">
                            Kami tidak dapat menemukan produk flash sale saat ini.
                        </p>
                    </div>
                {:else}
                    <!-- Product Grid -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                        {#each allProducts as product (product.id)}
                            {@const img = getProductImage(product)}
                            {@const isPromo = product.is_promo}
                            {@const price = isPromo ? product.promo_price : (product.product_price?.price ?? 0)}
                            {@const originalPrice = isPromo ? product.original_price : 0}
                            {@const discountPercentage = isPromo ? product.discount_percentage : 0}

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

                    <!-- Infinite scroll sentinel + loading indicator -->
                    <div use:setupObserver class="py-8 flex flex-col items-center justify-center gap-3">
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
</StorefrontLayout>
