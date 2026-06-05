<script lang="ts">
    import StorefrontLayout from '@/components/layouts/StorefrontLayout.svelte';
    import { page, Link, router } from '@inertiajs/svelte';
    import { onMount, onDestroy } from 'svelte';
    import { fade, scale } from 'svelte/transition';
    import { showToast } from '@/utils/toast';
    import VariantSelectorModal from '@/components/Storefront/VariantSelectorModal.svelte';

    let {
        categories = [],
        featuredProducts = [],
        newProducts = [],
        bestSellerProducts = [] as any[],
        activeFlashSale = null,
        storeName = '',
        heroBanners: incomingHeroBanners = [],
        sideBanners: incomingSideBanners = [],
        middleWideBanner = null,
        recentReviews = [] as any[],
        popupBanner = null,
    } = $props();

    let showIntro = $state(false);
    let showPopup = $state(false);
    const storeLogo = $derived(
        (page.props as any).storeLogo ||
            (page.props as any).settings?.store_logo,
    );

    const primary = $derived(page.props.theme?.primary_color || '#0c4cb4');
    const secondary = $derived(page.props.theme?.secondary_color || '#fa7315');
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

    function withOpacity(hex: string, opacity: number): string {
        if (!hex) return '';
        const trimmed = hex.trim();
        if (!trimmed.startsWith('#')) return trimmed;
        let cleanHex = trimmed.slice(1);
        if (cleanHex.length === 8) {
            cleanHex = cleanHex.slice(0, 6);
        } else if (cleanHex.length === 4) {
            cleanHex = cleanHex.slice(0, 3);
        }
        const alphaHex = Math.round(opacity * 255)
            .toString(16)
            .padStart(2, '0');
        return `#${cleanHex}${alphaHex}`;
    }

    // ──────────────────────────────────────────────────
    // HERO BANNER SLIDER
    // ──────────────────────────────────────────────────
    const defaultHeroBanners = [
        {
            image: '/banners/promo-main.png',
            alt: 'Promo Spesial Hari Ini',
            link: '#',
        },
        {
            image: '/banners/new-arrival.png',
            alt: 'New Arrival Produk Terbaru',
            link: '#',
        },
        {
            image: '/banners/flash-sale.png',
            alt: 'Flash Sale Hemat 70%',
            link: '#',
        },
    ];

    const defaultSideBanners = [
        {
            image: '/banners/fashion.png',
            alt: 'Fashion Lokal Diskon 50%',
            link: '#',
        },
        {
            image: '/banners/gratis-ongkir.png',
            alt: 'Gratis Ongkir Semua Produk',
            link: '#',
        },
    ];

    const heroBanners = $derived(
        incomingHeroBanners && incomingHeroBanners.length > 0
            ? incomingHeroBanners
            : defaultHeroBanners,
    );
    const sideBanners = $derived(
        incomingSideBanners && incomingSideBanners.length > 0
            ? incomingSideBanners
            : defaultSideBanners,
    );
    const middleWide = $derived(
        middleWideBanner && middleWideBanner.image
            ? middleWideBanner
            : {
                  image: '/banners/flash-sale.png',
                  alt: 'Flash Sale Promo',
                  link: '#',
              },
    );

    let activeHero = $state(0);
    let heroTimer: ReturnType<typeof setInterval>;

    function startHeroTimer() {
        heroTimer = setInterval(() => {
            activeHero = (activeHero + 1) % heroBanners.length;
        }, 4000);
    }

    function stopHeroTimer() {
        clearInterval(heroTimer);
    }

    function goHero(i: number) {
        activeHero = i;
        stopHeroTimer();
        startHeroTimer();
    }

    let touchStartX = 0;
    let touchEndX = 0;

    function handleTouchStart(e: TouchEvent) {
        touchStartX = e.changedTouches[0].screenX;
    }

    function handleTouchEnd(e: TouchEvent) {
        touchEndX = e.changedTouches[0].screenX;
        handleSwipe();
    }

    function handleSwipe() {
        const threshold = 50;
        if (touchEndX < touchStartX - threshold) {
            goHero((activeHero + 1) % heroBanners.length);
        } else if (touchEndX > touchStartX + threshold) {
            goHero((activeHero - 1 + heroBanners.length) % heroBanners.length);
        }
    }

    onMount(() => startHeroTimer());
    onDestroy(() => stopHeroTimer());

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

    onMount(() => {
        if (!sessionStorage.getItem('storefront_intro_played')) {
            showIntro = true;
            sessionStorage.setItem('storefront_intro_played', 'true');
            setTimeout(() => {
                showIntro = false;
                checkAndShowPopup();
            }, 1500);
        }
    });

    function checkAndShowPopup() {
        if (popupBanner && popupBanner.image && popupBanner.is_active) {
            setTimeout(() => {
                showPopup = true;
            }, 1500);
        }
    }

    // ──────────────────────────────────────────────────
    // HELPERS
    // ──────────────────────────────────────────────────
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

    function randomDiscount() {
        return [10, 15, 20, 25, 30, 40, 50][Math.floor(Math.random() * 7)];
    }

    function fakeOriginalPrice(price: number, discount: number) {
        return Math.round(price / (1 - discount / 100) / 1000) * 1000;
    }

    function fakeRating() {
        return (4.2 + Math.random() * 0.7).toFixed(1);
    }

    function fakeSold() {
        return Math.floor(Math.random() * 900 + 100);
    }

    const categoryIcons: Record<string, string> = {
        elektronik: 'ti-cpu',
        fashion: 'ti-shirt',
        pakaian: 'ti-shirt',
        rumah: 'ti-home',
        makanan: 'ti-salad',
        kesehatan: 'ti-stethoscope',
        olahraga: 'ti-ball-basketball',
        mainan: 'ti-building-castle',
        buku: 'ti-book',
        otomotif: 'ti-car',
        kecantikan: 'ti-sparkles',
        aksesoris: 'ti-watch',
    };

    const categoryColors = [
        '#ff6b6b',
        '#ff9f43',
        '#feca57',
        '#48dbfb',
        '#1dd1a1',
        '#54a0ff',
        '#c56ef3',
        '#ff9ff3',
        '#00d2d3',
        '#f368e0',
    ];

    function getCategoryIcon(cat: any) {
        if (cat.icon) return cat.icon;
        const key = cat.name.toLowerCase();
        for (const k of Object.keys(categoryIcons)) {
            if (key.includes(k)) return categoryIcons[k];
        }
        return 'ti-tag';
    }

    function getCategoryColor(i: number) {
        return categoryColors[i % categoryColors.length];
    }

    // Helper to flatten a list of products with their variants
    function flattenProductsWithVariants(productsList: any[]) {
        if (!productsList) return [];
        let items: any[] = [];
        productsList.forEach((p) => {
            if (!p.variants || p.variants.length === 0) {
                items.push({ ...p });
            } else {
                p.variants.forEach((v) => {
                    let item = { ...p };
                    if (v.product_price) {
                        item.product_price = v.product_price;
                    }
                    const optionNames = v.options
                        ? v.options.map((o) => o.name).join(' - ')
                        : '';
                    if (optionNames) {
                        item.name = `${item.name} - ${optionNames}`;
                    }
                    if (v.image) {
                        item.image = v.image;
                        item.images = []; // Clear parent images
                    }
                    if (v.product_stock) {
                        item.product_stock = v.product_stock;
                    }
                    item.product_variant_id = v.id;
                    items.push(item);
                });
            }
        });
        return items;
    }

    // Derived product lists
    const flashSaleProducts = $derived(
        activeFlashSale
            ? activeFlashSale.items?.length > 0
                ? activeFlashSale.items.map((item) => {
                      let p = { ...item.product }; // Clone to avoid mutating state
                      if (item.variant) {
                          if (item.variant.product_price) {
                              p.product_price = item.variant.product_price;
                          }
                          const optionNames = item.variant.options
                              ? item.variant.options
                                    .map((o) => o.name)
                                    .join(' - ')
                              : '';
                          if (optionNames) {
                              p.name = `${p.name} - ${optionNames}`;
                          }
                          if (item.variant.image) {
                              p.image = item.variant.image;
                              p.images = []; // Clear parent images so getProductImage falls back to p.image
                          }
                      }
                      const originalPrice = p.product_price?.price ?? 0;
                      let discountPercent = 0;
                      let promoPrice = originalPrice;

                      let discountType =
                          item.discount_type || activeFlashSale.discount_type;
                      let discountValue =
                          item.discount_value || activeFlashSale.discount_value;

                      if (discountType === 'percentage') {
                          discountPercent = Number(discountValue);
                          promoPrice =
                              originalPrice -
                              (originalPrice * discountPercent) / 100;
                      } else if (discountType === 'fixed') {
                          promoPrice = originalPrice - Number(discountValue);
                          discountPercent = Math.round(
                              (Number(discountValue) / originalPrice) * 100,
                          );
                      }

                      p.is_promo = true;
                      p.promo_price = Math.max(0, promoPrice);
                      p.discount_percentage =
                          discountPercent || randomDiscount();
                      p.promo_stock = item.promo_stock || 0;
                      p.remaining_promo_stock = item.remaining_promo_stock;
                      return p;
                  })
                : flattenProductsWithVariants(newProducts).map((product) => {
                      let p = { ...product };
                      const originalPrice = p.product_price?.price ?? 0;
                      let discountPercent = 0;
                      let promoPrice = originalPrice;

                      let discountType = activeFlashSale.discount_type;
                      let discountValue = activeFlashSale.discount_value;

                      if (discountType === 'percentage') {
                          discountPercent = Number(discountValue);
                          promoPrice =
                              originalPrice -
                              (originalPrice * discountPercent) / 100;
                      } else if (discountType === 'fixed') {
                          promoPrice = originalPrice - Number(discountValue);
                          discountPercent = Math.round(
                              (Number(discountValue) / originalPrice) * 100,
                          );
                      }

                      p.is_promo = true;
                      p.promo_price = Math.max(0, promoPrice);
                      p.discount_percentage =
                          discountPercent || randomDiscount();
                      p.promo_stock = p.product_stock?.stock ?? 0;
                      return p;
                  })
            : [],
    );
    // bestSellerProducts is now passed from the server (sorted by real sold count)

    // Infinite Scroll State for Hanya Untukmu
    let displayedCount = $state(10);
    let loadingMore = $state(false);
    let activeLightboxImage = $state<string | null>(null);

    const allRecommendations = $derived([...newProducts].reverse());
    const recommendedProducts = $derived(
        allRecommendations.slice(0, displayedCount),
    );
    const hasMore = $derived(displayedCount < allRecommendations.length);

    function setupObserver(node: HTMLElement) {
        const obs = new IntersectionObserver(
            (entries) => {
                if (entries[0].isIntersecting && !loadingMore && hasMore) {
                    loadingMore = true;
                    setTimeout(() => {
                        displayedCount = Math.min(
                            displayedCount + 10,
                            allRecommendations.length,
                        );
                        loadingMore = false;
                    }, 800);
                }
            },
            { rootMargin: '150px' },
        );
        obs.observe(node);
        return {
            destroy() {
                obs.disconnect();
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
            },
        };
    }

    // Special deal promo banners
    const dealBanners = [
        {
            bg: 'from-rose-500 to-rose-700',
            icon: 'ti-bolt',
            title: 'Kilat Promo',
            sub: 'Hanya 2 Jam!',
            badge: '⚡',
            cta: 'Ambil Sekarang',
        },
        {
            bg: 'from-violet-600 to-purple-700',
            icon: 'ti-gift',
            title: 'Cashback 20%',
            sub: 'Min. belanja Rp 100.000',
            badge: '🎁',
            cta: 'Klaim Cashback',
        },
        {
            bg: 'from-amber-500 to-orange-600',
            icon: 'ti-discount',
            title: 'Voucher Gratis',
            sub: 'Untuk Member Baru',
            badge: '🎫',
            cta: 'Ambil Voucher',
        },
        {
            bg: 'from-emerald-500 to-teal-600',
            icon: 'ti-truck',
            title: 'Gratis Ongkir',
            sub: 'Tanpa Minimal Belanja',
            badge: '🚚',
            cta: 'Belanja Sekarang',
        },
    ];
</script>

<svelte:head>
    <title>{storeName} — Belanja Mudah, Hemat & Terpercaya</title>
    <meta
        name="description"
        content="Belanja produk berkualitas di {storeName}. Flash Sale, Gratis Ongkir, Diskon s.d. 80%. Belanja aman & terpercaya."
    />
</svelte:head>

<StorefrontLayout hideMobileFooter={true}>
    <!-- ═══════════════════════════════════════════════════
     SECTION 1: HERO BANNER (1 besar + 2 kecil kanan)
═══════════════════════════════════════════════════ -->
    <section class="px-0 sm:px-5 lg:px-8 pt-0 sm:pt-4 pb-0 sm:pb-3">
        <div class="max-w-6xl mx-auto">
            <div class="flex gap-2.5 lg:gap-3">
                <!-- Main slider (left, 2/3 width) -->
                <!-- svelte-ignore a11y_no_static_element_interactions -->
                <div
                    role="presentation"
                    ontouchstart={handleTouchStart}
                    ontouchend={handleTouchEnd}
                    class="relative flex-[2] rounded-none sm:rounded-2xl overflow-hidden aspect-[16/9] lg:aspect-[2.1/1] w-full bg-slate-100 group cursor-pointer shrink-0"
                >
                    {#each heroBanners as banner, i}
                        <button
                            onclick={() => (activeLightboxImage = banner.image)}
                            class="absolute inset-0 transition-opacity duration-700 {i ===
                            activeHero
                                ? 'opacity-100 z-10'
                                : 'opacity-0 z-0'} w-full h-full text-left"
                        >
                            <img
                                src={banner.image}
                                alt={banner.alt}
                                class="w-full h-full object-cover"
                            />
                        </button>
                    {/each}

                    <!-- Prev / Next arrows -->
                    <button
                        aria-label="Previous"
                        onclick={() =>
                            goHero(
                                (activeHero - 1 + heroBanners.length) %
                                    heroBanners.length,
                            )}
                        class="absolute left-2 top-1/2 -translate-y-1/2 z-20 w-8 h-8 rounded-full bg-black/30 hover:bg-black/60 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition backdrop-blur-sm"
                    >
                        <i class="ti ti-chevron-left text-base"></i>
                    </button>
                    <button
                        aria-label="Next"
                        onclick={() =>
                            goHero((activeHero + 1) % heroBanners.length)}
                        class="absolute right-2 top-1/2 -translate-y-1/2 z-20 w-8 h-8 rounded-full bg-black/30 hover:bg-black/60 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition backdrop-blur-sm"
                    >
                        <i class="ti ti-chevron-right text-base"></i>
                    </button>

                    <!-- Dots -->
                    <div
                        class="absolute bottom-2.5 left-1/2 -translate-x-1/2 z-20 flex gap-1.5"
                    >
                        {#each heroBanners as _, i}
                            <button
                                aria-label="Go to slide {i + 1}"
                                onclick={() => goHero(i)}
                                class="rounded-full transition-all duration-300 {activeHero ===
                                i
                                    ? 'w-5 h-2 bg-white'
                                    : 'w-2 h-2 bg-white/50'}"
                            >
                            </button>
                        {/each}
                    </div>
                </div>

                <!-- Side banners (right, 1/3 width) — hidden on mobile -->
                <div class="hidden lg:flex flex-1 flex-col gap-2.5 min-w-0">
                    {#each sideBanners as banner}
                        <button
                            onclick={() => (activeLightboxImage = banner.image)}
                            class="flex-1 rounded-2xl overflow-hidden block bg-slate-100 aspect-[2.1/1] w-full relative text-left"
                        >
                            <img
                                src={banner.image}
                                alt={banner.alt}
                                class="w-full h-full object-cover hover:scale-105 transition duration-300"
                            />
                        </button>
                    {/each}
                </div>
            </div>
        </div>
    </section>

    <!-- ═══════════════════════════════════════════════════
     SECTION 2: QUICK ACCESS STRIPS (Shopee/Tokped style)
═══════════════════════════════════════════════════ -->
    <!-- <section class="bg-white mt-2 px-3 sm:px-5 lg:px-8 py-3">
        <div class="max-w-6xl mx-auto">
            <div class="grid grid-cols-4 sm:grid-cols-8 gap-2">
                {#each [
                    { icon: 'ti-layout-grid', label: 'Kategori', color: '#ff6b6b', target: 'categories-section' },
                    { icon: 'ti-truck', label: 'Gratis Ongkir', color: '#1dd1a1' },
                    { icon: 'ti-gift', label: 'Voucher', color: '#a29bfe' },
                    { icon: 'ti-cash', label: 'COD', color: '#fd79a8' },
                    { icon: 'ti-refresh', label: 'Retur Mudah', color: '#fdcb6e' },
                    { icon: 'ti-star', label: 'Best Seller', color: '#e17055', target: 'bestsellers-section' },
                    { icon: 'ti-sparkles', label: 'New Arrival', color: '#74b9ff', target: 'recommendations-section' },
                    { icon: 'ti-tag', label: 'Flash Deals', color: '#6c5ce7', target: 'bestsellers-section' }
                ] as item}
                    <button
                        onclick={() => {
                            if (item.target) {
                                document.getElementById(item.target)?.scrollIntoView({ behavior: 'smooth' });
                            }
                        }}
                        class="flex flex-col items-center gap-1.5 py-3 px-1 hover:bg-slate-50 rounded-xl transition group"
                    >
                        <div
                            class="w-10 h-10 rounded-full flex items-center justify-center text-white shadow-sm transition group-hover:scale-110"
                            style="background-color: {item.color};"
                        >
                            <i class="ti {item.icon} text-lg"></i>
                        </div>
                        <span
                            class="text-[10px] sm:text-xs font-bold text-slate-600 text-center leading-tight"
                            >{item.label}</span
                        >
                    </button>
                {/each}
            </div>
        </div>
    </section> -->

    <!-- ═══════════════════════════════════════════════════
     SECTION 3: KATEGORI
═══════════════════════════════════════════════════ -->
    {#if categories.length > 0}
        <section id="categories-section" class="mt-2 px-3 sm:px-5 lg:px-8">
            <div
                class="max-w-6xl mx-auto bg-white rounded-2xl py-4 px-4 sm:px-6 shadow-sm"
            >
                <!-- Header -->
                <div class="flex items-center justify-between mb-4">
                    <h2
                        class="font-outfit font-black text-base sm:text-lg text-slate-800"
                    >
                        Kategori
                    </h2>
                    <Link
                        href="/search"
                        prefetch
                        class="text-xs font-bold flex items-center gap-1"
                        style="color: {primary};"
                    >
                        Lihat Semua <i class="ti ti-arrow-right text-xs"></i>
                    </Link>
                </div>
                <!-- Categories scroll row -->
                <div
                    class="overflow-x-auto no-scrollbar -mx-3 px-3 sm:mx-0 sm:px-0 py-2"
                >
                    <div class="flex gap-3 w-max sm:w-auto sm:flex-wrap">
                        {#each categories as cat, i}
                            <Link
                                href="/category/{cat.slug || cat.id}"
                                prefetch
                                class="flex flex-col items-center gap-2 group cursor-pointer w-16 sm:w-20"
                            >
                                <div
                                    class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl flex items-center justify-center shadow-sm transition group-hover:scale-110 group-hover:shadow-md border border-slate-100/50"
                                    style="background: linear-gradient(135deg, {withOpacity(
                                        primary,
                                        0.08,
                                    )}, {withOpacity(
                                        secondary,
                                        0.08,
                                    )}); color: {primary};"
                                >
                                    {#if cat.image}
                                        <img
                                            src={cat.image}
                                            alt={cat.name}
                                            class="w-9 h-9 object-contain"
                                        />
                                    {:else}
                                        <i
                                            class="ti {getCategoryIcon(
                                                cat,
                                            )} text-2xl"
                                            style="color: {primary};"
                                        ></i>
                                    {/if}
                                </div>
                                <span
                                    class="text-[10px] sm:text-xs font-bold text-slate-600 text-center leading-tight group-hover:text-slate-800 transition"
                                >
                                    {cat.name}
                                </span>
                            </Link>
                        {/each}
                    </div>
                </div>
            </div>
        </section>
    {/if}

    <!-- ═══════════════════════════════════════════════════
     SECTION 4: FLASH SALE
    ═══════════════════════════════════════════════════ -->
    {#if activeFlashSale}
        <section class="mt-2 px-3 sm:px-5 lg:px-8">
            <div
                class="max-w-6xl mx-auto bg-white rounded-2xl overflow-hidden shadow-sm"
            >
                <!-- Flash Sale Header -->
                <div
                    class="flex items-center justify-between px-3 py-2.5 sm:px-6 sm:py-3 border-b border-slate-100 min-w-0 gap-2"
                    style="background: linear-gradient(135deg, {primary}, {secondary});"
                >
                    <div class="flex items-center gap-1.5 sm:gap-3 min-w-0">
                        <span
                            class="font-outfit font-black text-xs sm:text-base md:text-lg text-white flex items-center gap-1 sm:gap-2 shrink-0"
                        >
                            <i class="ti ti-bolt-filled animate-pulse"></i> Flash
                            Sale
                        </span>
                        <!-- Countdown -->
                        <div
                            class="flex items-center gap-0.5 sm:gap-1 bg-black/35 rounded-xl px-1.5 py-1 sm:px-3 sm:py-1.5 backdrop-blur-sm shrink-0"
                        >
                            <span
                                class="text-white text-[9px] font-bold mr-1 hidden sm:inline"
                                >Berakhir dalam</span
                            >
                            {#each [countdown.h, countdown.m, countdown.s] as unit, ui}
                                {#if ui > 0}<span
                                        class="text-white/60 font-bold text-xs"
                                        >:</span
                                    >{/if}
                                <span
                                    class="bg-white font-black text-[10px] sm:text-xs px-1.5 py-0.5 rounded-md min-w-[20px] sm:min-w-[26px] text-center tabular-nums"
                                    style="color: {primary};"
                                >
                                    {unit}
                                </span>
                            {/each}
                        </div>
                    </div>
                    <Link
                        href="/flash-sale"
                        prefetch
                        class="text-white/90 text-[10px] sm:text-xs font-bold flex items-center gap-0.5 sm:gap-1 hover:text-white transition shrink-0"
                    >
                        Lihat Semua <i class="ti ti-arrow-right text-sm"></i>
                    </Link>
                </div>

                <!-- Flash Sale Products (horizontal scroll) -->
                <div
                    class="overflow-x-auto pb-4 pt-4 px-3 sm:px-5 scrollbar-thin"
                >
                    <div
                        class="flex gap-4 {flashSaleProducts.length < 4
                            ? 'justify-start sm:justify-center w-full'
                            : ''}"
                        style="width: max-content; min-width: 100%;"
                    >
                        {#if flashSaleProducts.length > 0}
                            {#each flashSaleProducts as product}
                                {@const img = getProductImage(product)}
                                {@const price = product.is_promo
                                    ? product.promo_price
                                    : (product.product_price?.price ?? 150000)}
                                {@const disc = product.is_promo
                                    ? product.discount_percentage
                                    : randomDiscount()}
                                {@const ori = product.is_promo
                                    ? (product.product_price?.price ?? price)
                                    : fakeOriginalPrice(price, disc)}
                                {@const pct =
                                    product.remaining_promo_stock !== null &&
                                    product.remaining_promo_stock !==
                                        undefined &&
                                    product.promo_stock > 0
                                        ? (product.remaining_promo_stock /
                                              product.promo_stock) *
                                          100
                                        : product.remaining_promo_stock === 0
                                          ? 0
                                          : 100}
                                <Link
                                    href="/products/{product.slug ||
                                        product.id}"
                                    prefetch
                                    class="w-36 sm:w-40 bg-white border border-slate-100 hover:border-slate-200 hover:shadow-md rounded-xl overflow-hidden transition group cursor-pointer shrink-0"
                                >
                                    <div
                                        class="relative aspect-square overflow-hidden border-b border-slate-50 group/img"
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
                                                alt="Tidak ada gambar"
                                                class="w-full h-full object-cover"
                                            />
                                        {/if}
                                        {#if product.remaining_promo_stock !== null && product.remaining_promo_stock !== undefined && product.remaining_promo_stock <= 0}
                                            <span
                                                class="absolute top-1.5 left-1.5 text-white text-[9px] font-black px-1.5 py-0.5 rounded-md shadow-sm bg-slate-500"
                                            >
                                                HABIS
                                            </span>
                                        {:else}
                                            <span
                                                class="absolute top-1.5 left-1.5 text-white text-[9px] font-black px-1.5 py-0.5 rounded-md shadow-sm"
                                                style="background-color: {secondary};"
                                            >
                                                -{disc}%
                                            </span>
                                        {/if}
                                    </div>
                                    <div class="p-2.5">
                                        <p
                                            class="text-[11px] text-slate-700 leading-tight line-clamp-2 mb-1.5 font-medium"
                                        >
                                            {product.name}
                                        </p>
                                        <p
                                            class="text-sm font-black"
                                            style="color: {primary};"
                                        >
                                            {formatPrice(price)}
                                        </p>
                                        {#if product.is_promo && ori > price}
                                            <p
                                                class="text-[10px] text-slate-400 line-through"
                                            >
                                                {formatPrice(ori)}
                                            </p>
                                        {/if}
                                        <!-- Shopee Style Progress Bar & Remaining Stock -->
                                        <div
                                            class="mt-3.5 relative w-full h-3.5 rounded-full overflow-hidden flex items-center justify-center border shadow-inner"
                                            style="background-color: {primary}15; border-color: {primary}20;"
                                        >
                                            {#if product.remaining_promo_stock !== null && product.remaining_promo_stock !== undefined}
                                                {#if product.remaining_promo_stock <= 0}
                                                    <div
                                                        class="absolute left-0 top-0 h-full w-0 bg-slate-200"
                                                    ></div>
                                                    <span
                                                        class="absolute z-10 text-[9px] font-black uppercase tracking-wider text-slate-400 flex items-center gap-1"
                                                    >
                                                        <i
                                                            class="ti ti-package-off text-[10px]"
                                                        ></i>
                                                        Habis Terjual!
                                                    </span>
                                                {:else}
                                                    <div
                                                        class="absolute left-0 top-0 h-full rounded-full transition-all duration-500"
                                                        style="width: {pct}%; background: linear-gradient(to right, {secondary}, {primary});"
                                                    ></div>
                                                    <span
                                                        class="absolute z-10 text-[9px] font-black uppercase tracking-wider text-white drop-shadow-sm flex items-center gap-1"
                                                    >
                                                        <i
                                                            class="ti ti-flame text-[10px] animate-pulse"
                                                        ></i>
                                                        Tersisa {product.remaining_promo_stock}
                                                        Stok
                                                    </span>
                                                {/if}
                                            {:else}
                                                <div
                                                    class="absolute left-0 top-0 h-full rounded-full"
                                                    style="width: 100%; background: linear-gradient(to right, {secondary}, {primary});"
                                                ></div>
                                                <span
                                                    class="absolute z-10 text-[9px] font-black uppercase tracking-wider text-white drop-shadow-sm flex items-center gap-1"
                                                >
                                                    <i
                                                        class="ti ti-flame text-[10px] animate-pulse"
                                                    ></i>
                                                    Hampir Habis!
                                                </span>
                                            {/if}
                                        </div>
                                    </div>
                                </Link>
                            {/each}
                        {:else}
                            {#each Array(5) as _, i}
                                <div
                                    class="w-36 sm:w-40 bg-slate-100 rounded-xl overflow-hidden shrink-0 animate-pulse"
                                >
                                    <div
                                        class="aspect-square bg-slate-200"
                                    ></div>
                                    <div class="p-2.5 space-y-2">
                                        <div
                                            class="h-3 bg-slate-200 rounded"
                                        ></div>
                                        <div
                                            class="h-3 bg-slate-200 rounded w-2/3"
                                        ></div>
                                        <div
                                            class="h-3 bg-slate-200 rounded w-1/2"
                                        ></div>
                                    </div>
                                </div>
                            {/each}
                        {/if}
                    </div>
                </div>
            </div>
        </section>
    {/if}

    <!-- ═══════════════════════════════════════════════════
     SECTION 5: SPECIAL DEAL BANNERS (4 small promo cards)
    ═══════════════════════════════════════════════════ -->
    <!-- <section class="mt-2 px-3 sm:px-5 lg:px-8">
        <div class="max-w-6xl mx-auto">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                {#each dealBanners as deal}
                    <button
                        class="relative overflow-hidden rounded-2xl p-4 text-white flex items-center gap-3 cursor-pointer group transition hover:shadow-lg hover:-translate-y-0.5 bg-gradient-to-br {deal.bg}"
                    >
                        <div
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-white/20 text-5xl pointer-events-none"
                        >
                            <i class="ti {deal.icon}"></i>
                        </div>
                        <div class="text-2xl shrink-0 z-10">{deal.badge}</div>
                        <div class="z-10 text-left">
                            <p
                                class="font-outfit font-black text-sm leading-tight"
                            >
                                {deal.title}
                            </p>
                            <p
                                class="text-[10px] opacity-80 leading-tight mt-0.5"
                            >
                                {deal.sub}
                            </p>
                        </div>
                    </button>
                {/each}
            </div>
        </div>
    </section> -->

    <!-- ═══════════════════════════════════════════════════
     SECTION 6: PRODUK TERLARIS
═══════════════════════════════════════════════════ -->
    <section id="bestsellers-section" class="mt-2 px-3 sm:px-5 lg:px-8">
        <div class="max-w-6xl mx-auto bg-white rounded-2xl overflow-hidden">
            <div
                class="flex items-center justify-between px-4 sm:px-6 py-4 border-b border-slate-100"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="w-9 h-9 rounded-xl flex items-center justify-center text-white shadow-md"
                        style="background: linear-gradient(135deg, {primary}, {withOpacity(
                            primary,
                            0.65,
                        )});"
                    >
                        <i class="ti ti-trending-up text-lg"></i>
                    </div>
                    <div>
                        <h2
                            class="font-outfit font-black text-base sm:text-lg text-slate-800"
                        >
                            Produk Terlaris
                        </h2>
                        <p
                            class="text-[10px] text-slate-400 font-bold uppercase tracking-wider"
                        >
                            Paling Banyak Dibeli
                        </p>
                    </div>
                </div>
                <Link
                    href="/produk-terlaris"
                    prefetch
                    class="text-xs font-bold flex items-center gap-1"
                    style="color: {primary};"
                >
                    Lihat Semua <i class="ti ti-arrow-right text-sm"></i>
                </Link>
            </div>
            <div class="overflow-x-auto pb-4 pt-4 px-3 sm:px-5 scrollbar-thin">
                <div
                    class="flex gap-4.5 {bestSellerProducts.length < 5
                        ? 'justify-start sm:justify-center w-full'
                        : ''}"
                    style="width: max-content; min-width: 100%;"
                >
                    {#each bestSellerProducts.length > 0 ? bestSellerProducts : Array(8) as product, i}
                        {@const isReal = bestSellerProducts.length > 0}
                        {@const img = isReal ? getProductImage(product) : null}
                        {@const isPromo = isReal && product.is_promo}
                        {@const price = isReal
                            ? isPromo
                                ? product.promo_price
                                : (product.product_price?.price ?? 0)
                            : 0}
                        {@const originalPrice =
                            isReal && isPromo ? product.original_price : 0}
                        {@const discountPercentage =
                            isReal && isPromo ? product.discount_percentage : 0}
                        {@const avgRating = isReal
                            ? product.avg_rating
                                ? Number(product.avg_rating)
                                : null
                            : null}
                        {@const reviewCount = isReal
                            ? (product.review_count ?? 0)
                            : 0}
                        <Link
                            href={isReal
                                ? `/products/${product.slug || product.id}`
                                : '#'}
                            prefetch
                            class="w-36 sm:w-44 bg-white border border-slate-100 hover:border-slate-200 hover:shadow-md rounded-xl overflow-hidden transition group cursor-pointer shrink-0 flex flex-col h-full"
                        >
                            <div
                                class="relative aspect-square overflow-hidden border-b border-slate-50 group/img"
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
                                {:else if !isReal}
                                    <div
                                        class="w-full h-full bg-slate-200 animate-pulse"
                                    ></div>
                                {:else}
                                    <img
                                        src="/noimage/image.png"
                                        alt="Tidak ada gambar"
                                        class="w-full h-full object-cover"
                                    />
                                {/if}
                                {#if isReal && isPromo && discountPercentage > 0}
                                    <span
                                        class="absolute top-1.5 left-1.5 text-white text-[9px] font-black px-1.5 py-0.5 rounded-md"
                                        style="background-color: {secondary};"
                                    >
                                        -{discountPercentage}%
                                    </span>
                                {/if}
                            </div>
                            <div class="p-3 flex-1 flex flex-col">
                                {#if isReal}
                                    <div>
                                        <p
                                            class="text-[9px] sm:text-[10px] font-black uppercase tracking-wider mb-1 line-clamp-1"
                                            style="color: {primary};"
                                        >
                                            {product.category?.name || 'PRODUK'}
                                        </p>
                                        <div
                                            class="h-[2.5rem] overflow-hidden mb-1"
                                        >
                                            <p
                                                class="text-xs sm:text-sm font-black leading-tight line-clamp-2"
                                                style="color: #1e293b;"
                                            >
                                                {product.name}
                                            </p>
                                        </div>
                                        <div
                                            class="flex items-center gap-1 mt-1 h-4"
                                        >
                                            {#if avgRating !== null && reviewCount > 0}
                                                <i
                                                    class="ti ti-star-filled text-amber-500 text-[10px]"
                                                ></i>
                                                <span
                                                    class="text-[10px] text-slate-500 font-bold"
                                                    >{avgRating.toFixed(
                                                        1,
                                                    )}</span
                                                >
                                                <span
                                                    class="text-[10px] text-slate-400"
                                                    >({reviewCount})</span
                                                >
                                                <!-- {:else}
                                                <span
                                                    class="text-[10px] text-slate-400 italic"
                                                    >Belum ada ulasan</span
                                                > -->
                                            {/if}
                                        </div>
                                        <hr class="border-slate-100 my-2" />
                                        <div class="mb-0">
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
                                {:else}
                                    <div
                                        class="space-y-1.5 animate-pulse flex-1 flex flex-col justify-between"
                                    >
                                        <div class="space-y-1.5">
                                            <div
                                                class="h-3 bg-slate-200 rounded w-full"
                                            ></div>
                                            <div
                                                class="h-3 bg-slate-200 rounded w-3/4"
                                            ></div>
                                        </div>
                                        <div
                                            class="h-8 bg-slate-200 rounded-xl w-full mt-2"
                                        ></div>
                                    </div>
                                {/if}
                            </div>
                        </Link>
                    {/each}
                </div>
            </div>
        </div>
    </section>

    <!-- ═══════════════════════════════════════════════════
     SECTION 7: BANNER WIDE (full width promo)
═══════════════════════════════════════════════════ -->
    <section class="px-3 sm:px-5 lg:px-8 mt-4 mb-4">
        <div
            class="max-w-6xl mx-auto rounded-2xl overflow-hidden shadow-sm hover:shadow transition"
        >
            {#if middleWide.link && middleWide.link !== '#'}
                <Link href={middleWide.link} class="block w-full">
                    <img
                        src={middleWide.image}
                        alt={middleWide.alt}
                        class="w-full h-auto aspect-[3.5/1] sm:aspect-[4.5/1] object-cover hover:opacity-95 transition"
                    />
                </Link>
            {:else}
                <button
                    onclick={() => (activeLightboxImage = middleWide.image)}
                    class="w-full text-left"
                >
                    <img
                        src={middleWide.image}
                        alt={middleWide.alt}
                        class="w-full h-auto aspect-[3.5/1] sm:aspect-[4.5/1] object-cover hover:opacity-95 transition"
                    />
                </button>
            {/if}
        </div>
    </section>

    <!-- ═══════════════════════════════════════════════════
     SECTION 10: REKOMENDASI / HANYA UNTUKMU (Infinite Scroll)
═══════════════════════════════════════════════════ -->
    <section id="recommendations-section" class="mt-2 px-3 sm:px-5 lg:px-8">
        <div class="max-w-6xl mx-auto bg-transparent shadow-none">
            <!-- Masonry-style grid -->
            <div class="py-3 px-0">
                <div
                    class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3"
                >
                    {#each recommendedProducts.length > 0 ? recommendedProducts : Array(10) as product, i}
                        {@const isReal = recommendedProducts.length > 0}
                        {@const img = isReal ? getProductImage(product) : null}
                        {@const isPromo = isReal && product.is_promo}
                        {@const price = isReal
                            ? isPromo
                                ? product.promo_price
                                : (product.product_price?.price ?? 0)
                            : 0}
                        {@const originalPrice =
                            isReal && isPromo ? product.original_price : 0}
                        {@const discountPercentage =
                            isReal && isPromo ? product.discount_percentage : 0}
                        {@const avgRating = isReal
                            ? product.avg_rating
                                ? Number(product.avg_rating)
                                : null
                            : null}
                        {@const reviewCount = isReal
                            ? (product.review_count ?? 0)
                            : 0}
                        <div
                            class="relative group bg-white border border-slate-100 hover:border-slate-200 hover:shadow-lg rounded-xl overflow-hidden transition flex flex-col h-full"
                        >
                            <Link
                                href={isReal
                                    ? `/products/${product.slug || product.id}`
                                    : '#'}
                                prefetch
                                class="flex flex-col flex-1 cursor-pointer"
                            >
                                <!-- Rounded image container -->
                                <div
                                    class="relative aspect-square overflow-hidden border-b border-slate-50 group/img bg-slate-50"
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
                                    {:else if !isReal}
                                        <div
                                            class="w-full h-full bg-slate-200 animate-pulse"
                                        ></div>
                                    {:else}
                                        <img
                                            src="/noimage/image.png"
                                            alt="Tidak ada gambar"
                                            class="w-full h-full object-cover"
                                        />
                                    {/if}
                                    {#if isReal && isPromo && discountPercentage > 0}
                                        <span
                                            class="absolute top-1.5 left-1.5 text-white text-[9px] font-black px-1.5 py-0.5 rounded-md shadow-sm"
                                            style="background-color: {secondary};"
                                        >
                                            -{discountPercentage}%
                                        </span>
                                    {/if}
                                </div>
                                <div
                                    class="p-3 flex-1 flex flex-col justify-between"
                                >
                                    {#if isReal}
                                        <div>
                                            <p
                                                class="text-[9px] sm:text-[10px] font-black uppercase tracking-wider mb-1 line-clamp-1"
                                                style="color: {primary};"
                                            >
                                                {product.category?.name ||
                                                    'PRODUK'}
                                            </p>
                                            <div
                                                class="h-[2.5rem] overflow-hidden mb-1"
                                            >
                                                <p
                                                    class="text-xs sm:text-sm font-black leading-tight line-clamp-2"
                                                    style="color: #1e293b;"
                                                >
                                                    {product.name}
                                                </p>
                                            </div>
                                            <div
                                                class="flex items-center gap-1 mt-1 h-4"
                                            >
                                                {#if avgRating !== null && reviewCount > 0}
                                                    <i
                                                        class="ti ti-star-filled text-amber-500 text-[10px]"
                                                    ></i>
                                                    <span
                                                        class="text-[10px] text-slate-500 font-bold"
                                                        >{avgRating.toFixed(
                                                            1,
                                                        )}</span
                                                    >
                                                    <span
                                                        class="text-[10px] text-slate-400"
                                                        >({reviewCount})</span
                                                    >
                                                {/if}
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
                                                        {formatPrice(
                                                            originalPrice,
                                                        )}
                                                    </p>
                                                {/if}
                                            </div>
                                        </div>
                                    {:else}
                                        <div
                                            class="space-y-1.5 animate-pulse flex-1 flex flex-col justify-between"
                                        >
                                            <div class="space-y-1.5">
                                                <div
                                                    class="h-3 bg-slate-200 rounded"
                                                ></div>
                                                <div
                                                    class="h-4 bg-slate-200 rounded w-2/3"
                                                ></div>
                                            </div>
                                            <div
                                                class="h-8 bg-slate-200 rounded-xl w-full mt-2"
                                            ></div>
                                        </div>
                                    {/if}
                                </div>
                            </Link>
                            <!-- Cart buttons OUTSIDE Link to prevent Inertia navigation -->
                            {#if isReal && cartButtonStyle === 'icon'}
                                <button
                                    type="button"
                                    onclick={(e) => handleAddToCart(product, e)}
                                    class="absolute top-2.5 right-2.5 w-8 h-8 rounded-full bg-white/90 hover:bg-white flex items-center justify-center shadow-md border transition-all duration-200 active:scale-90 hover:scale-105 z-10"
                                    style="border-color: {primary}; color: {primary};"
                                    title="Tambah ke Keranjang"
                                >
                                    <i
                                        class="ti ti-plus text-2xl sm:text-base font-black"
                                    ></i>
                                </button>
                            {/if}
                            {#if isReal && cartButtonStyle === 'button'}
                                <div class="px-3 pb-3">
                                    <button
                                        type="button"
                                        onclick={(e) =>
                                            handleAddToCart(product, e)}
                                        class="w-full flex items-center justify-center gap-1.5 py-2.5 px-3 rounded-xl font-bold text-[10px] sm:text-xs text-white uppercase tracking-wider transition duration-200 hover:brightness-95 active:scale-[0.98] cursor-pointer"
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

                <!-- Sentinel for Infinite Scroll -->
                {#if hasMore}
                    <div use:setupObserver class="h-10"></div>
                {/if}

                <!-- Loading Spinner & Skeletons -->
                {#if loadingMore}
                    <div
                        class="mt-6 flex flex-col items-center justify-center gap-4"
                    >
                        <div
                            class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3 w-full"
                        >
                            {#each Array(5) as _}
                                <div
                                    class="bg-white border border-slate-100 rounded-xl overflow-hidden animate-pulse"
                                >
                                    <div
                                        class="aspect-square bg-slate-100"
                                    ></div>
                                    <div class="p-3 space-y-2">
                                        <div
                                            class="h-3 bg-slate-100 rounded w-full"
                                        ></div>
                                        <div
                                            class="h-3 bg-slate-100 rounded w-2/3"
                                        ></div>
                                        <div
                                            class="h-4 bg-slate-100 rounded w-1/2 mt-2"
                                        ></div>
                                    </div>
                                </div>
                            {/each}
                        </div>
                        <!-- Modern pulsing indicator -->
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
                            Memuat rekomendasi lebih banyak...
                        </div>
                    </div>
                {/if}
            </div>
        </div>
    </section>

    <!-- ═══════════════════════════════════════════════════
     LIGHTBOX / IMAGE POPUP MODAL
═══════════════════════════════════════════════════ -->
    {#if activeLightboxImage}
        <div
            class="fixed inset-0 z-[100] flex items-center justify-center bg-black/85 backdrop-blur-md transition-all duration-300"
            onclick={() => (activeLightboxImage = null)}
            role="button"
            tabindex="0"
            onkeydown={(e) =>
                e.key === 'Escape' && (activeLightboxImage = null)}
        >
            <!-- Close Button -->
            <button
                aria-label="Close"
                onclick={() => (activeLightboxImage = null)}
                class="absolute top-5 right-5 w-12 h-12 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition border border-white/20 z-50"
            >
                <i class="ti ti-x text-xl"></i>
            </button>

            <!-- Image Container -->
            <!-- svelte-ignore a11y_click_events_have_key_events -->
            <div
                role="presentation"
                class="relative w-[92vw] max-w-4xl flex flex-col items-center justify-center p-2"
                onclick={(e) => e.stopPropagation()}
            >
                <img
                    src={activeLightboxImage}
                    alt="Detail Gambar"
                    class="w-full max-h-[80vh] object-contain rounded-xl shadow-2xl border border-white/10 animate-zoomIn"
                />

                <!-- Download Button / Full size opener -->
                <a
                    href={activeLightboxImage}
                    download
                    target="_blank"
                    class="mt-4 px-4 py-2 rounded-full bg-white/15 hover:bg-white/25 text-white text-xs font-bold transition flex items-center gap-1.5 border border-white/15 backdrop-blur-md"
                >
                    <i class="ti ti-download text-sm"></i> Buka Ukuran Penuh
                </a>
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

    {#if showIntro}
        <!-- svelte-ignore a11y_click_events_have_key_events -->
        <!-- svelte-ignore a11y_no_noninteractive_element_interactions -->
        <div
            transition:fade={{ duration: 600 }}
            class="fixed inset-0 z-[99999] flex flex-col items-center justify-center text-white cursor-pointer"
            style="background: radial-gradient(circle at center, {withOpacity(primary, 0.95)} 0%, #030712 100%);"
            onclick={() => (showIntro = false)}
            role="dialog"
            aria-modal="true"
        >
            <div
                class="flex flex-col items-center gap-6 text-center select-none"
                transition:scale={{ duration: 600, delay: 100, start: 0.9 }}
            >
                {#if storeLogo}
                    <div class="relative w-24 h-24 sm:w-28 sm:h-28 flex items-center justify-center bg-white/10 backdrop-blur-md rounded-3xl p-4 shadow-2xl border border-white/20 animate-pulse">
                        <img
                            src={storeLogo}
                            alt={storeName}
                            class="w-full h-full object-contain filter drop-shadow-md"
                        />
                    </div>
                {:else}
                    <div class="w-24 h-24 sm:w-28 sm:h-28 rounded-3xl bg-gradient-to-tr from-white/10 to-white/5 backdrop-blur-md flex items-center justify-center shadow-2xl border border-white/20 animate-pulse">
                        <i class="ti ti-shopping-bag text-5xl sm:text-6xl text-white"></i>
                    </div>
                {/if}

                <div class="space-y-2">
                    <h1 class="font-outfit font-black text-2xl sm:text-3xl tracking-wide bg-gradient-to-r from-white via-white/95 to-white/80 bg-clip-text text-transparent">
                        {storeName}
                    </h1>
                    <p class="text-xs sm:text-sm text-white/50 tracking-wider uppercase font-medium">
                        Belanja Mudah &amp; Terpercaya
                    </p>
                </div>

                <div class="w-32 h-1 bg-white/10 rounded-full overflow-hidden relative mt-4">
                    <div class="absolute inset-0 bg-gradient-to-r from-white/40 via-white to-white/40 rounded-full animate-loadingBar"></div>
                </div>
            </div>
        </div>
    {/if}

    {#if showPopup}
        <!-- svelte-ignore a11y_click_events_have_key_events -->
        <!-- svelte-ignore a11y_no_noninteractive_element_interactions -->
        <div
            transition:fade={{ duration: 300 }}
            class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4"
            onclick={() => (showPopup = false)}
            role="dialog"
            aria-modal="true"
        >
            <!-- svelte-ignore a11y_no_static_element_interactions -->
            <div
                class="relative max-w-sm w-full bg-white rounded-3xl overflow-hidden shadow-2xl border border-slate-100 flex flex-col group"
                onclick={(e) => e.stopPropagation()}
                transition:scale={{ duration: 400, start: 0.95 }}
            >
                <!-- Close Button -->
                <button
                    onclick={() => (showPopup = false)}
                    class="absolute top-4 right-4 w-9 h-9 rounded-full bg-black/40 hover:bg-black/60 text-white flex items-center justify-center transition backdrop-blur-sm z-50 border border-white/10"
                    aria-label="Tutup Promo"
                >
                    <i class="ti ti-x text-lg"></i>
                </button>

                <!-- Banner Image Link -->
                <Link
                    href={popupBanner.link || '#'}
                    onclick={() => (showPopup = false)}
                    class="block w-full overflow-hidden aspect-[4/5] bg-slate-100 relative"
                >
                    <img
                        src={popupBanner.image}
                        alt={popupBanner.alt || 'Promo Spesial'}
                        class="w-full h-full object-cover hover:scale-105 transition duration-500"
                    />
                </Link>

                <!-- Optional Action Button -->
                {#if popupBanner.link && popupBanner.link !== '#'}
                    <div class="p-4 bg-white border-t border-slate-50 flex justify-center">
                        <Link
                            href={popupBanner.link}
                            onclick={() => (showPopup = false)}
                            class="px-6 py-2.5 text-white font-bold rounded-xl text-xs sm:text-sm tracking-wide uppercase transition duration-200 shadow-md hover:shadow-lg w-full text-center"
                            style="background-color: {primary};"
                        >
                            Lihat Detail Promo
                        </Link>
                    </div>
                {/if}
            </div>
        </div>
    {/if}
</StorefrontLayout>

<style>
    @keyframes loadingBar {
        0% {
            transform: translateX(-100%);
        }
        50% {
            transform: translateX(0);
        }
        100% {
            transform: translateX(100%);
        }
    }
    .animate-loadingBar {
        animation: loadingBar 1.5s infinite ease-in-out;
    }
    @keyframes zoomIn {
        from {
            transform: scale(0.96);
            opacity: 0;
        }
        to {
            transform: scale(1);
            opacity: 1;
        }
    }
    .animate-zoomIn {
        animation: zoomIn 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
    .no-scrollbar::-webkit-scrollbar {
        display: none;
    }
    .no-scrollbar {
        -ms-overflow-style: none; /* IE and Edge */
        scrollbar-width: none; /* Firefox */
    }

    /* Premium horizontal scrollbar styling */
    .scrollbar-thin::-webkit-scrollbar {
        height: 6px;
    }
    .scrollbar-thin::-webkit-scrollbar-track {
        background: #f8fafc;
        border-radius: 9999px;
    }
    .scrollbar-thin::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 9999px;
        transition: background 0.2s ease;
    }
    .scrollbar-thin::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }
    .scrollbar-thin {
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 #f8fafc;
        -webkit-overflow-scrolling: touch;
    }
</style>
