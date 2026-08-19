<script lang="ts">
    import StorefrontLayout from '@/components/layouts/StorefrontLayout.svelte';
    import { page, Link, router } from '@inertiajs/svelte';
    import { onMount, onDestroy } from 'svelte';
    import { fade, scale } from 'svelte/transition';
    import { showToast } from '@/utils/toast';
    import VariantSelectorModal from '@/components/Storefront/VariantSelectorModal.svelte';

    let {
        categories = undefined,
        brands = undefined,
        featuredProducts = undefined,
        newProducts = undefined,
        bestSellerProducts = undefined,
        activeFlashSale = undefined,
        storeName = '',
        heroBanners: incomingHeroBanners = [],
        sideBanners: incomingSideBanners = [],
        middleWideBanner = null,
        recentReviews = undefined,
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
    const showBrands = $derived(
        ((page.props.settings as any)?.show_brands ?? true) !== false,
    );
    const isSellerEnabled = $derived(
        Boolean(
            (page.props as any).app_config?.is_seller_enabled ??
            (page.props as any).settings?.is_seller_enabled ??
            (page.props as any).is_seller_enabled ??
            (page.props as any).isSellerMode ??
            false,
        ),
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
        middleWideBanner && middleWideBanner.is_active === false
            ? null
            : middleWideBanner && middleWideBanner.image
              ? middleWideBanner
              : {
                    image: '/banners/flash-sale.png',
                    alt: 'Flash Sale Promo',
                    link: '#',
                    is_active: true,
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
        const introEnabled =
            (page.props.settings as any)?.show_intro_animation !== false;
        if (
            introEnabled &&
            !sessionStorage.getItem('storefront_intro_played')
        ) {
            showIntro = true;
            sessionStorage.setItem('storefront_intro_played', 'true');
            setTimeout(() => {
                showIntro = false;
                checkAndShowPopup();
            }, 1500);
        } else if (!introEnabled) {
            checkAndShowPopup();
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
    function handleBannerClick(banner: any, e?: MouseEvent) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        if (!banner) return;
        const link = banner.link ? String(banner.link).trim() : '';
        if (link && link !== '#') {
            if (link.startsWith('http://') || link.startsWith('https://')) {
                window.open(link, '_blank');
            } else {
                router.visit(link);
            }
        } else if (banner.image) {
            activeLightboxImage = banner.image;
        }
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
        if (!path || typeof path !== 'string') return null;
        if (
            path.startsWith('http://') ||
            path.startsWith('https://') ||
            path.startsWith('/')
        ) {
            return path;
        }
        return '/' + path;
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

    const defaultQuickShortcuts = [
        {
            id: 'see-all',
            name: 'Lihat Semua',
            icon: 'launcher',
            badge: null,
            href: '/search',
            gradient: 'from-blue-500 via-sky-400 to-teal-400',
            textColor: 'text-white',
        },
        {
            id: 'priority-shipping',
            name: 'Pengiriman Prioritas',
            icon: 'ti-box-seam',
            badge: 'Baru',
            badgeBg: 'bg-rose-500',
            target: 'recommendations-section',
            gradient: 'from-blue-600 to-indigo-600',
            textColor: 'text-white',
        },
        {
            id: 'special-discount',
            name: 'Diskon s.d. 80%',
            icon: 'ti-ticket',
            badge: 'Promo',
            badgeBg: 'bg-rose-500',
            target: 'bestsellers-section',
            gradient: 'from-pink-500 via-rose-500 to-red-500',
            textColor: 'text-white',
        },
        {
            id: 'flash-sale',
            name: 'Flash Sale',
            icon: 'ti-bolt',
            badge: 'Hot',
            badgeBg: 'bg-orange-500',
            target: 'flash-sale-section',
            gradient: 'from-amber-400 via-orange-500 to-red-500',
            textColor: 'text-white',
        },
        {
            id: 'rewards',
            name: 'Tagihan & Poin',
            icon: 'ti-receipt-2',
            badge: null,
            target: 'recommendations-section',
            gradient: 'from-sky-400 via-blue-500 to-indigo-500',
            textColor: 'text-white',
        },
        {
            id: 'official-mart',
            name: 'Supermarket',
            icon: 'ti-basket',
            badge: 'Gratis',
            badgeBg: 'bg-emerald-500',
            target: 'recommendations-section',
            gradient: 'from-emerald-400 via-teal-500 to-green-600',
            textColor: 'text-white',
        },
        {
            id: 'gadgets',
            name: 'Gadget & Elektronik',
            icon: 'ti-devices',
            badge: null,
            target: 'recommendations-section',
            gradient: 'from-purple-500 via-indigo-500 to-blue-600',
            textColor: 'text-white',
        },
    ];

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

    // Infinite Scroll State for Hanya Untukmu / Rekomendasi Produk
    let displayedCount = $state(10);
    let loadingMore = $state(false);
    let activeLightboxImage = $state<string | null>(null);
    let sentinelEl = $state<HTMLElement | null>(null);

    let selectedBrandId = $state<string | null>(null);

    const availableBrands = $derived.by(() => {
        if (brands && brands.length > 0) {
            return brands;
        }
        if (!newProducts || newProducts.length === 0) return [];
        const map = new Map();
        newProducts.forEach((p: any) => {
            if (p.brands && p.brands.length > 0) {
                p.brands.forEach((b: any) => {
                    const bId = b.id || b.name;
                    if (!map.has(bId)) {
                        map.set(bId, { id: bId, name: b.name, slug: b.slug || b.name });
                    }
                });
            } else if (p.brand) {
                const bId = p.brand.id || p.brand.name;
                if (!map.has(bId)) {
                    map.set(bId, { id: bId, name: p.brand.name, slug: p.brand.slug || p.brand.name });
                }
            }
        });
        return Array.from(map.values());
    });

    // Stable recommendations list (DETERMINISTIC ORDER - NEVER SHUFFLES ON REFRESH)
    const filteredRecommendations = $derived.by(() => {
        if (!newProducts || newProducts.length === 0) return [];
        let list = [...newProducts];

        if (selectedBrandId) {
            list = list.filter((p: any) => {
                if (p.brands && p.brands.length > 0) {
                    return p.brands.some(
                        (b: any) => b.id === selectedBrandId || b.slug === selectedBrandId || b.name === selectedBrandId
                    );
                }
                if (p.brand) {
                    return p.brand.id === selectedBrandId || p.brand.name === selectedBrandId;
                }
                return p.brand_id === selectedBrandId;
            });
        }

        // Prioritize sponsored/ad products to the top
        list.sort((a: any, b: any) => {
            const aAd = (a.is_promoted || a.is_ad) ? 1 : 0;
            const bAd = (b.is_promoted || b.is_ad) ? 1 : 0;
            return bAd - aAd;
        });

        return list;
    });

    const recommendedProducts = $derived(
        filteredRecommendations.length > 0
            ? filteredRecommendations.slice(0, displayedCount)
            : undefined,
    );
    const hasMore = $derived(
        filteredRecommendations.length > 0
            ? displayedCount < filteredRecommendations.length
            : false,
    );

    function loadMoreRecommendations() {
        if (loadingMore || !hasMore) return;
        loadingMore = true;
        setTimeout(() => {
            displayedCount = Math.min(
                displayedCount + 10,
                filteredRecommendations.length,
            );
            loadingMore = false;
        }, 300);
    }

    // IntersectionObserver for infinite scroll
    let scrollObserver: IntersectionObserver | null = null;

    $effect(() => {
        if (sentinelEl) {
            scrollObserver?.disconnect();
            scrollObserver = new IntersectionObserver(
                (entries) => {
                    if (entries[0]?.isIntersecting && hasMore && !loadingMore) {
                        loadMoreRecommendations();
                    }
                },
                { threshold: 0.1 },
            );
            scrollObserver.observe(sentinelEl);
        }
        return () => {
            scrollObserver?.disconnect();
        };
    });

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
    {#if heroBanners && heroBanners.length > 0 && heroBanners[0].image}
        <link rel="preload" as="image" href={heroBanners[0].image} fetchpriority="high" />
    {/if}
</svelte:head>

<StorefrontLayout hideMobileFooter={true}>
    <!-- ═══════════════════════════════════════════════════
     SECTION 1: HERO BANNER (1 besar + 2 kecil kanan)
═══════════════════════════════════════════════════ -->
    <section class="px-0 sm:px-5 lg:px-8 pt-0 sm:pt-4 pb-0 sm:pb-3">
        <div class="max-w-6xl mx-auto">
            <div class="flex gap-2.5 lg:gap-3 items-start">
                <!-- ── MOBILE: Responsive aspect ratio to prevent CLS layout shift ── -->
                <!-- svelte-ignore a11y_no_static_element_interactions -->
                <div
                    role="presentation"
                    ontouchstart={handleTouchStart}
                    ontouchend={handleTouchEnd}
                    class="sm:hidden relative w-full overflow-hidden bg-slate-100 group cursor-pointer aspect-[2.8/1]"
                >
                    {#each heroBanners as banner, i}
                        <button
                            onclick={(e) => handleBannerClick(banner, e)}
                            class="w-full h-full text-left cursor-pointer {i === activeHero ? 'block' : 'hidden'}"
                        >
                            <img
                                src={banner.mobile_image || banner.image}
                                alt={banner.alt}
                                fetchpriority={i === 0 ? 'high' : 'low'}
                                loading={i === 0 ? 'eager' : 'lazy'}
                                decoding={i === 0 ? 'sync' : 'async'}
                                class="w-full h-full object-cover block"
                            />
                        </button>
                    {/each}

                    <!-- Dots (mobile) -->
                    {#if heroBanners.length > 1}
                        <div class="absolute bottom-2 left-1/2 -translate-x-1/2 z-20 flex gap-1.5">
                            {#each heroBanners as _, i}
                                <button
                                    aria-label="Go to slide {i + 1}"
                                    onclick={() => goHero(i)}
                                    class="rounded-full transition-all duration-300 {activeHero === i ? 'w-5 h-2 bg-white' : 'w-2 h-2 bg-white/50'}"
                                ></button>
                            {/each}
                        </div>
                    {/if}
                </div>

                <!-- ── DESKTOP: Fixed aspect ratio crossfade slider ── -->
                <!-- svelte-ignore a11y_no_static_element_interactions -->
                <div
                    role="presentation"
                    ontouchstart={handleTouchStart}
                    ontouchend={handleTouchEnd}
                    class="hidden sm:block relative flex-1 min-w-0 rounded-2xl overflow-hidden w-full bg-slate-100 group cursor-pointer shrink-0 aspect-[3.5/1]"
                >
                    {#each heroBanners as banner, i}
                        <button
                            onclick={(e) => handleBannerClick(banner, e)}
                            class="absolute inset-0 w-full h-full transition-opacity duration-700 {i ===
                            activeHero
                                ? 'opacity-100 z-10 pointer-events-auto'
                                : 'opacity-0 z-0 pointer-events-none'} text-left cursor-pointer overflow-hidden"
                        >
                            <img
                                src={banner.image}
                                alt=""
                                loading="lazy"
                                decoding="async"
                                class="absolute inset-0 w-full h-full object-cover blur-2xl opacity-40 scale-110 pointer-events-none"
                                aria-hidden="true"
                            />
                            <img
                                src={banner.image}
                                alt={banner.alt}
                                fetchpriority={i === 0 ? 'high' : 'low'}
                                loading={i === 0 ? 'eager' : 'lazy'}
                                decoding={i === 0 ? 'sync' : 'async'}
                                class="absolute inset-0 z-10 w-full h-full object-contain object-center block"
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
                    <div class="absolute bottom-2.5 left-1/2 -translate-x-1/2 z-20 flex gap-1.5">
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

                <!-- Side banners (right, fixed square) — hidden on mobile -->
                <div class="hidden w-[300px] aspect-square shrink-0 self-start flex-col gap-2.5 overflow-hidden rounded-2xl">
                    {#each sideBanners as banner}
                        <button
                            onclick={(e) => handleBannerClick(banner, e)}
                            class="overflow-hidden block bg-slate-100 w-full text-left cursor-pointer relative group/side flex-1"
                        >
                            {#if banner.fit === 'contain'}
                                <!-- Ambient blurred background (always fills box) -->
                                <img
                                    src={banner.image}
                                    alt=""
                                    loading="lazy"
                                    decoding="async"
                                    class="absolute inset-0 w-full h-full object-cover blur-2xl opacity-40 scale-110 pointer-events-none"
                                    aria-hidden="true"
                                />
                                <!-- Main image: shown fully without crop, centered -->
                                <img
                                    src={banner.image}
                                    alt={banner.alt}
                                    loading="lazy"
                                    decoding="async"
                                    class="absolute inset-0 z-10 w-full h-full object-contain block group-hover/side:scale-105 transition duration-300"
                                />
                            {:else}
                                <!-- Cover mode: fills entire box, may crop edges -->
                                <img
                                    src={banner.image}
                                    alt={banner.alt}
                                    loading="lazy"
                                    decoding="async"
                                    class="absolute inset-0 z-10 w-full h-full object-cover block group-hover/side:scale-105 transition duration-300 object-center"
                                />
                            {/if}
                        </button>
                    {/each}

                </div>
            </div>
        </div>
    </section>

    <!-- ═══════════════════════════════════════════════════
     TRUST GUARANTEES STRIP (Clean Seamless Strip - No Card)
    ═══════════════════════════════════════════════════ -->
    <!-- <section class="px-3 sm:px-5 lg:px-8 pt-2 pb-1">
        <div class="max-w-6xl mx-auto">
            <div
                class="flex items-center justify-between gap-2 overflow-x-auto no-scrollbar py-1 text-[11px] sm:text-xs font-semibold text-slate-700"
            >
                <div class="flex items-center gap-1.5 shrink-0">
                    <i class="ti ti-rotate-clockwise-2 text-blue-600 text-sm"></i>
                    <span>Retur alasan apa pun</span>
                </div>
                <span class="text-slate-300 font-light shrink-0">|</span>
                <div class="flex items-center gap-1.5 shrink-0">
                    <i class="ti ti-clock-bolt text-amber-500 text-sm"></i>
                    <span>Jaminan tepat waktu</span>
                </div>
                <span class="text-slate-300 font-light shrink-0">|</span>
                <div class="flex items-center gap-1.5 shrink-0">
                    <i class="ti ti-shield-check text-emerald-600 text-sm"></i>
                    <span>Gratis perlindungan lengkap</span>
                </div>
            </div>
        </div>
    </section> -->

    <!-- ═══════════════════════════════════════════════════
     SECTION 3: KATEGORI (Clean, Compact, Real Categories Only)
    ═══════════════════════════════════════════════════ -->
    {#if categories === undefined}
        <section id="categories-section" class="px-3 sm:px-5 lg:px-8 py-2">
            <div class="max-w-6xl mx-auto">
                <div class="flex items-center justify-between mb-2">
                    <div
                        class="h-4 w-20 bg-slate-200 rounded-md animate-pulse"
                    ></div>
                    <div
                        class="h-3 w-14 bg-slate-200 rounded-md animate-pulse"
                    ></div>
                </div>
                <div class="overflow-x-auto no-scrollbar py-1">
                    <div class="flex gap-2.5 sm:gap-4">
                        {#each Array(6) as _}
                            <div
                                class="flex flex-col items-center gap-1.5 w-14 sm:w-16 shrink-0 animate-pulse"
                            >
                                <div
                                    class="w-11 h-11 sm:w-13 sm:h-13 rounded-2xl bg-slate-200/70"
                                ></div>
                                <div
                                    class="h-2.5 w-10 bg-slate-200/70 rounded"
                                ></div>
                            </div>
                        {/each}
                    </div>
                </div>
            </div>
        </section>
    {:else if categories && categories.length > 0}
        <section id="categories-section" class="px-3 sm:px-5 lg:px-8 py-2">
            <div class="max-w-6xl mx-auto">
                <!-- Section Header -->
                <div class="flex items-center justify-between mb-2.5 px-0.5">
                    <h2
                        class="font-outfit font-black text-sm sm:text-base text-slate-800 tracking-tight"
                    >
                        Kategori Pilihan
                    </h2>
                    <Link
                        href="/search"
                        prefetch
                        class="text-[11px] sm:text-xs font-bold flex items-center gap-0.5 transition hover:opacity-80"
                        style="color: {primary};"
                    >
                        Lihat Semua <i class="ti ti-chevron-right text-[10px]"
                        ></i>
                    </Link>
                </div>

                <!-- Categories Scroll Row (No Card Box, Sleek & Compact) -->
                <div class="overflow-x-auto no-scrollbar py-1">
                    <div class="flex gap-3.5 sm:gap-5 w-max sm:w-auto">
                        <!-- First Item: Lihat Semua -->
                        <!-- <Link
                            href="/search"
                            prefetch
                            class="flex flex-col items-center gap-1.5 group cursor-pointer w-[68px] sm:w-[76px] shrink-0 text-center"
                        >
                            <div
                                class="w-13 h-13 sm:w-15 sm:h-15 rounded-[1.25rem] sm:rounded-2xl flex items-center justify-center border border-white/20 transition-all duration-200 group-hover:scale-105 group-hover:shadow-md relative overflow-hidden bg-gradient-to-br from-blue-500 via-sky-400 to-teal-400 shadow-xs"
                            >
                                <div class="grid grid-cols-2 gap-1 w-6 h-6 p-0.5 bg-white/95 rounded-xl shadow-2xs">
                                    <div class="rounded-md bg-blue-500"></div>
                                    <div class="rounded-md bg-orange-400"></div>
                                    <div class="rounded-md bg-amber-400"></div>
                                    <div class="rounded-md bg-emerald-500"></div>
                                </div>
                            </div>
                            <span
                                class="text-[11px] sm:text-xs font-semibold text-slate-700 text-center leading-tight max-w-[72px] line-clamp-2 group-hover:text-slate-900 transition"
                            >
                                Lihat Semua
                            </span>
                        </Link> -->

                        <!-- Real Database Categories -->
                        {#each categories as cat, i}
                            <Link
                                href="/category/{cat.slug || cat.id}"
                                prefetch
                                class="flex flex-col items-center gap-1.5 group cursor-pointer w-[68px] sm:w-[76px] shrink-0 text-center"
                            >
                                <div
                                    class="w-13 h-13 sm:w-15 sm:h-15 rounded-[1.25rem] sm:rounded-2xl flex items-center justify-center border border-slate-200/80 transition-all duration-200 group-hover:scale-105 group-hover:shadow-md group-hover:border-slate-300 bg-white shadow-2xs"
                                    style="color: {primary};"
                                >
                                    {#if cat.image}
                                        <img
                                            src={cat.image}
                                            alt={cat.name}
                                            class="w-7 h-7 sm:w-8 sm:h-8 object-contain"
                                        />
                                    {:else}
                                        <i
                                            class="ti {getCategoryIcon(
                                                cat,
                                            )} text-2xl sm:text-3xl"
                                            style="color: {primary};"
                                        ></i>
                                    {/if}
                                </div>
                                <span
                                    class="text-[11px] sm:text-xs font-semibold text-slate-700 text-center leading-tight max-w-[72px] line-clamp-2 group-hover:text-slate-900 transition"
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
    {#if activeFlashSale === undefined}
        <section class="mt-2 px-3 sm:px-5 lg:px-8">
            <div
                class="max-w-6xl mx-auto bg-white rounded-2xl overflow-hidden shadow-sm"
            >
                <!-- Flash Sale Header Skeleton -->
                <div
                    class="flex items-center justify-between px-3 py-2.5 sm:px-6 sm:py-3 border-b border-slate-100 min-w-0 gap-2 bg-slate-100 animate-pulse"
                >
                    <div class="h-6 w-32 bg-slate-200 rounded-lg"></div>
                    <div class="h-4 w-16 bg-slate-200 rounded-lg"></div>
                </div>
                <!-- Flash Sale Products Skeleton (horizontal scroll) -->
                <div
                    class="overflow-x-auto pb-4 pt-4 px-3 sm:px-5 scrollbar-thin"
                >
                    <div class="flex gap-4">
                        {#each Array(5) as _}
                            <div
                                class="w-36 sm:w-40 bg-white border border-slate-100 rounded-xl overflow-hidden shrink-0 animate-pulse"
                            >
                                <div class="aspect-square bg-slate-100"></div>
                                <div class="p-3 space-y-2">
                                    <div
                                        class="h-3 bg-slate-150 rounded w-3/4"
                                    ></div>
                                    <div
                                        class="h-3 bg-slate-150 rounded w-1/2"
                                    ></div>
                                    <div
                                        class="h-4 bg-slate-150 rounded w-2/3 mt-2"
                                    ></div>
                                </div>
                            </div>
                        {/each}
                    </div>
                </div>
            </div>
        </section>
    {:else if activeFlashSale}
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
                                <a
                                    href={`/products/${product.id}`}
                                    class="w-36 sm:w-40 bg-white border border-slate-100 hover:border-slate-200 hover:shadow-md rounded-xl overflow-hidden transition group cursor-pointer shrink-0"
                                >
                                    <div
                                        class="relative aspect-square overflow-hidden border-b border-slate-50 group/img"
                                    >
                                        {#if img}
                                            <img
                                                src={img}
                                                alt={product.name}
                                                loading="lazy"
                                                decoding="async"
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
                                                loading="lazy"
                                                decoding="async"
                                                class="w-full h-full object-cover"
                                            />
                                        {/if}
                                        <div
                                            class="absolute top-1.5 left-1.5 z-10 flex flex-col gap-1 items-start pointer-events-none"
                                        >
                                            {#if isSellerEnabled}
                                                <span
                                                    class="text-white text-[9px] font-black px-1.5 py-0.5 rounded-md shadow-xs {product.condition ===
                                                    'rent'
                                                        ? 'bg-purple-600'
                                                        : product.condition ===
                                                                'used' ||
                                                            product.condition ===
                                                                'second'
                                                          ? 'bg-amber-600'
                                                          : 'bg-emerald-600'}"
                                                >
                                                    {product.condition ===
                                                    'rent'
                                                        ? 'Rent'
                                                        : product.condition ===
                                                                'used' ||
                                                            product.condition ===
                                                                'second'
                                                          ? 'Second'
                                                          : 'New'}
                                                </span>
                                            {/if}
                                            {#if product.remaining_promo_stock !== null && product.remaining_promo_stock !== undefined && product.remaining_promo_stock <= 0}
                                                <span
                                                    class="text-white text-[9px] font-black px-1.5 py-0.5 rounded-md shadow-sm bg-slate-500"
                                                >
                                                    HABIS
                                                </span>
                                            {:else if disc > 0}
                                                <span
                                                    class="text-white text-[9px] font-black px-1.5 py-0.5 rounded-md shadow-sm"
                                                    style="background-color: {secondary};"
                                                >
                                                    -{disc}%
                                                </span>
                                            {/if}
                                        </div>
                                    </div>
                                    <div class="p-2.5">
                                        <p
                                            class="text-[11px] text-slate-700 leading-tight line-clamp-2 mb-1.5 font-medium"
                                        >
                                            {product.name}
                                        </p>
                                        <p
                                            class="text-base sm:text-lg font-black leading-tight tracking-tight"
                                            style="color: {primary};"
                                        >
                                            {formatPrice(price)}
                                        </p>
                                        {#if product.is_promo && ori > price}
                                            <p
                                                class="text-[10px] text-red-600 line-through font-bold"
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
                                </a>
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
    {#if !isSellerEnabled && bestSellerProducts && bestSellerProducts.length > 0}
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
                    <a
                        href="/produk-terlaris"
                        class="text-xs font-bold flex items-center gap-1"
                        style="color: {primary};"
                    >
                        Lihat Semua <i class="ti ti-arrow-right text-sm"></i>
                    </a>
                </div>
                <div
                    class="overflow-x-auto pb-4 pt-4 px-3 sm:px-5 scrollbar-thin"
                >
                    <div
                        class="flex gap-4.5 {bestSellerProducts.length < 5
                            ? 'justify-start sm:justify-center w-full'
                            : ''}"
                        style="width: max-content; min-width: 100%;"
                    >
                        {#each bestSellerProducts as product}
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
                            {@const avgRating = product.avg_rating
                                ? Number(product.avg_rating)
                                : null}
                            {@const reviewCount = product.review_count ?? 0}
                            <a
                                href={`/products/${product.id}`}
                                class="w-36 sm:w-44 bg-white border border-slate-100 hover:border-slate-200 hover:shadow-md rounded-xl overflow-hidden transition group cursor-pointer shrink-0 flex flex-col h-full"
                            >
                                <div
                                    class="relative aspect-square overflow-hidden border-b border-slate-50 group/img"
                                >
                                    {#if img}
                                        <img
                                            src={img}
                                            alt={product.name}
                                            loading="lazy"
                                            decoding="async"
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
                                            loading="lazy"
                                            decoding="async"
                                            class="w-full h-full object-cover"
                                        />
                                    {/if}
                                    <div
                                        class="absolute top-1.5 left-1.5 z-10 flex flex-col gap-1 items-start pointer-events-none"
                                    >

                                        {#if isSellerEnabled}
                                            <span
                                                class="text-white text-[9px] font-black px-1.5 py-0.5 rounded-md shadow-xs {product.condition ===
                                                'rent'
                                                    ? 'bg-purple-600'
                                                    : product.condition ===
                                                            'used' ||
                                                        product.condition ===
                                                            'second'
                                                      ? 'bg-amber-600'
                                                      : 'bg-emerald-600'}"
                                            >
                                                {product.condition === 'rent'
                                                    ? 'Rent'
                                                    : product.condition ===
                                                            'used' ||
                                                        product.condition ===
                                                            'second'
                                                      ? 'Second'
                                                      : 'New'}
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
                                <div class="p-2.5 sm:p-3 flex-1 flex flex-col">
                                    <div>
                                        <p
                                            class="text-[9px] sm:text-[10px] font-black uppercase tracking-wider mb-1 line-clamp-1"
                                            style="color: {primary};"
                                        >
                                            {product.category?.name || 'PRODUK'}
                                        </p>
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
                                        <hr class="border-slate-100 my-1" />
                                        <div class="mb-0">
                                            <p
                                                class="text-base sm:text-lg font-black leading-tight tracking-tight"
                                                style="color: {secondary};"
                                            >
                                                {formatPrice(price)}
                                            </p>
                                            {#if isPromo && originalPrice > price}
                                                <p
                                                    class="text-[10px] sm:text-xs text-red-600 line-through font-bold mt-0.5"
                                                >
                                                    {formatPrice(originalPrice)}
                                                </p>
                                            {/if}
                                        </div>
                                    </div>
                                </div>
                            </a>
                        {/each}
                    </div>
                </div>
            </div>
        </section>
    {/if}

    <!-- ═══════════════════════════════════════════════════
     SECTION 7: BANNER WIDE (full width promo - Max 500px & Ratio 4.5:1 / 3.6:1)
═══════════════════════════════════════════════════ -->
    {#if middleWide && middleWide.image}
        <section class="px-3 sm:px-5 lg:px-8 mt-2.5 mb-1">
            <div
                class="max-w-6xl mx-auto rounded-2xl overflow-hidden shadow-sm hover:shadow transition relative max-h-[500px]"
            >
                <button
                    onclick={(e) => handleBannerClick(middleWide, e)}
                    class="block w-full text-left cursor-pointer relative overflow-hidden rounded-2xl"
                >
                    <!-- Main Middle Wide Banner Image (100% full width, natural responsive height) -->
                    <img
                        src={middleWide.image}
                        alt={middleWide.alt}
                        loading="lazy"
                        decoding="async"
                        class="relative z-10 block w-full h-auto max-h-[500px] object-cover object-center hover:opacity-95 transition rounded-2xl mx-auto"
                    />
                </button>
            </div>
        </section>
    {/if}

    {#if showBrands && availableBrands && availableBrands.length > 0}
    <!-- ═══════════════════════════════════════════════════
     SECTION 8: BRAND PILIHAN (Official Store - Sleek Cards Like Categories)
    ═══════════════════════════════════════════════════ -->
    <section id="brands-section" class="px-3 sm:px-5 lg:px-8 pt-4 pb-2">
        <div class="max-w-6xl mx-auto">
            <!-- Section Header -->
            <div class="flex items-center justify-between mb-3 px-0.5">
                <div class="flex items-center gap-2">
                    <h2
                        class="font-outfit font-black text-sm sm:text-base text-slate-900 tracking-tight flex items-center gap-1.5"
                    >
                        Brand Pilihan
                    </h2>
                    <div
                        class="flex items-center gap-1 text-[11px] font-bold px-2 py-0.5 rounded-full border shadow-2xs"
                        style="color: {primary}; background-color: {primary}12; border-color: {primary}30;"
                    >
                        <i class="ti ti-circle-check-filled text-xs" style="color: {primary};"></i>
                        <span>Official</span>
                    </div>
                </div>
                <a
                    href="/brands"
                    class="text-xs font-bold transition-opacity hover:opacity-80 flex items-center gap-1"
                    style="color: {primary};"
                >
                    Lihat Semua
                    <i class="ti ti-chevron-right text-xs"></i>
                </a>
            </div>

            <!-- Brand Icon Cards Scroll Row (Styled like Categories) -->
            <div class="relative group/brandrow">
                <div
                    class="flex items-center gap-3.5 sm:gap-4 overflow-x-auto no-scrollbar py-2 px-1 scroll-smooth"
                >
                    <!-- First Card: Semua Brand -->
                    <button
                        type="button"
                        onclick={() => (selectedBrandId = null)}
                        class="flex flex-col items-center gap-1.5 shrink-0 group cursor-pointer"
                    >
                        <div
                            class="w-13 h-13 sm:w-15 sm:h-15 rounded-[1.25rem] sm:rounded-2xl flex items-center justify-center border transition-all duration-200 group-hover:scale-105 group-hover:shadow-md relative overflow-hidden shadow-2xs {selectedBrandId === null
                                ? 'shadow-md border-transparent'
                                : 'bg-white border-slate-200/80 hover:border-slate-300'}"
                            style={selectedBrandId === null ? `background-color: ${primary}; border-color: ${primary}; color: #ffffff;` : `color: ${primary};`}
                        >
                            <i class="ti ti-building-store text-xl sm:text-2xl" style={selectedBrandId === null ? 'color: #ffffff;' : `color: {primary};`}></i>
                        </div>
                        <span
                            class="text-[11px] sm:text-xs font-semibold text-slate-700 text-center leading-tight max-w-[76px] line-clamp-2 group-hover:text-slate-900 transition {selectedBrandId === null ? 'font-bold text-slate-900' : ''}"
                        >
                            Semua Brand
                        </span>
                    </button>

                    {#if availableBrands && availableBrands.length > 0}
                        {#each availableBrands as brand}
                            {@const isSelected = selectedBrandId === brand.id || selectedBrandId === brand.name || selectedBrandId === brand.slug}
                            <button
                                type="button"
                                onclick={() => (selectedBrandId = isSelected ? null : (brand.id || brand.name))}
                                class="flex flex-col items-center gap-1.5 shrink-0 group cursor-pointer"
                            >
                                <div
                                    class="w-13 h-13 sm:w-15 sm:h-15 rounded-[1.25rem] sm:rounded-2xl flex items-center justify-center border transition-all duration-200 group-hover:scale-105 group-hover:shadow-md relative overflow-hidden bg-white shadow-2xs {isSelected ? 'ring-2 ring-offset-2 border-transparent' : 'border-slate-200/80 hover:border-slate-300'}"
                                    style={isSelected ? `box-shadow: 0 0 0 2px ${primary};` : ''}
                                >
                                    {#if brand.logo || brand.image}
                                        <img
                                            src={brand.logo || brand.image}
                                            alt={brand.name}
                                            class="w-9 h-9 sm:w-10 sm:h-10 object-contain"
                                        />
                                    {:else}
                                        <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-slate-100 flex items-center justify-center font-black text-slate-500 text-sm uppercase">
                                            {brand.name.charAt(0)}
                                        </div>
                                    {/if}
                                </div>
                                <span
                                    class="text-[11px] sm:text-xs font-semibold text-slate-700 text-center leading-tight max-w-[76px] line-clamp-2 group-hover:text-slate-900 transition"
                                >
                                    {brand.name}
                                </span>
                            </button>
                        {/each}
                    {/if}
                </div>
            </div>
        </div>
    </section>
    {/if}

    <!-- ═══════════════════════════════════════════════════
     SECTION 10: REKOMENDASI PRODUK (Urutan Tetap & Konsisten)
    ═══════════════════════════════════════════════════ -->
    <section id="recommendations-section" class="px-3 sm:px-5 lg:px-8 pt-3">
        <div class="max-w-6xl mx-auto bg-transparent shadow-none">
            <!-- Section Header Rekomendasi -->
            <div class="flex items-center justify-between mb-3 px-0.5">
                <div>
                    <h2
                        class="font-outfit font-black text-base sm:text-lg text-slate-900 tracking-tight flex items-center gap-2"
                    >
                        <i class="ti ti-sparkles text-lg" style="color: {secondary};"></i>
                        Rekomendasi Untuk Anda
                        {#if selectedBrandId}
                            {@const activeBrand = availableBrands.find(b => b.id === selectedBrandId || b.name === selectedBrandId || b.slug === selectedBrandId)}
                            {#if activeBrand}
                                <span
                                    class="text-xs font-bold border px-2.5 py-0.5 rounded-full flex items-center gap-1 shadow-2xs"
                                    style="color: {primary}; background-color: {primary}12; border-color: {primary}30;"
                                >
                                    Brand: {activeBrand.name}
                                    <button
                                        type="button"
                                        onclick={() => (selectedBrandId = null)}
                                        class="hover:opacity-75 ml-1"
                                        title="Hapus Filter"
                                    >
                                        <i class="ti ti-x text-xs"></i>
                                    </button>
                                </span>
                            {/if}
                        {/if}
                    </h2>
                    <p class="text-xs text-slate-500 font-medium">Koleksi produk unggulan berkualitas dengan penawaran terbaik</p>
                </div>
            </div>
            <!-- Masonry-style grid -->
            <div class="pt-1 pb-3 px-0">
                <div
                    class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3"
                >
                    {#each recommendedProducts && recommendedProducts.length > 0 ? recommendedProducts : Array(10) as product, i}
                        {@const isReal =
                            recommendedProducts &&
                            recommendedProducts.length > 0}
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
                            <a
                                href={isReal ? `/products/${product.id}` : '#'}
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
                                            loading="lazy"
                                            decoding="async"
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
                                            loading="lazy"
                                            decoding="async"
                                            class="w-full h-full object-cover"
                                        />
                                    {/if}
                                    <div
                                        class="absolute top-1.5 left-1.5 z-10 flex flex-col gap-1 items-start pointer-events-none"
                                    >

                                        {#if isReal && isSellerEnabled}
                                            <span
                                                class="text-white text-[9px] font-black px-1.5 py-0.5 rounded-md shadow-xs {product.condition ===
                                                'rent'
                                                    ? 'bg-purple-600'
                                                    : product.condition ===
                                                            'used' ||
                                                        product.condition ===
                                                            'second'
                                                      ? 'bg-amber-600'
                                                      : 'bg-emerald-600'}"
                                            >
                                                {product.condition === 'rent'
                                                    ? 'Rent'
                                                    : product.condition ===
                                                            'used' ||
                                                        product.condition ===
                                                            'second'
                                                      ? 'Second'
                                                      : 'New'}
                                            </span>
                                        {/if}
                                        {#if isReal && isPromo && discountPercentage > 0}
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
                                    {#if isReal}
                                        <div>
                                            <!-- <p
                                                class="text-[9px] sm:text-[10px] font-black uppercase tracking-wider mb-1 line-clamp-1"
                                                style="color: {primary};"
                                            >
                                                {product.category?.name ||
                                                    'PRODUK'}
                                            </p> -->
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
                                            <hr class="border-slate-100 my-1" />
                                            <div class="mb-0.5">
                                                <p
                                                    class="text-base sm:text-lg font-black leading-tight tracking-tight"
                                                    style="color: {secondary};"
                                                >
                                                    {formatPrice(price)}
                                                </p>
                                                {#if isPromo && originalPrice > price}
                                                    <p
                                                        class="text-[10px] sm:text-xs text-red-600 line-through font-bold mt-0.5"
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
                            </a>
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

                <!-- Infinite scroll sentinel -->
                {#if hasMore || loadingMore}
                    <div
                        bind:this={sentinelEl}
                        class="mt-3 w-full flex justify-center py-1"
                    >
                        {#if loadingMore}
                            <div
                                class="flex items-center gap-2 px-4 py-1.5 bg-white border border-slate-200 rounded-xl shadow-xs text-slate-600 font-bold text-[11px]"
                            >
                                <svg
                                    class="animate-spin h-4 w-4"
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
                                <span>Memuat produk lainnya...</span>
                            </div>
                        {/if}
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
            style="background: radial-gradient(circle at center, {withOpacity(
                primary,
                0.95,
            )} 0%, #030712 100%);"
            onclick={() => (showIntro = false)}
            role="dialog"
            aria-modal="true"
            tabindex="0"
        >
            <div
                class="flex flex-col items-center gap-6 text-center select-none"
                transition:scale={{ duration: 600, delay: 100, start: 0.9 }}
            >
                {#if storeLogo}
                    <div
                        class="relative w-24 h-24 sm:w-28 sm:h-28 flex items-center justify-center bg-white/10 backdrop-blur-md rounded-3xl p-4 shadow-2xl border border-white/20 animate-pulse"
                    >
                        <img
                            src={storeLogo}
                            alt={storeName}
                            class="w-full h-full object-contain filter drop-shadow-md"
                        />
                    </div>
                {:else}
                    <div
                        class="w-24 h-24 sm:w-28 sm:h-28 rounded-3xl bg-gradient-to-tr from-white/10 to-white/5 backdrop-blur-md flex items-center justify-center shadow-2xl border border-white/20 animate-pulse"
                    >
                        <i
                            class="ti ti-shopping-bag text-5xl sm:text-6xl text-white"
                        ></i>
                    </div>
                {/if}

                <div class="space-y-2">
                    <h1
                        class="font-outfit font-black text-2xl sm:text-3xl tracking-wide bg-gradient-to-r from-white via-white/95 to-white/80 bg-clip-text text-transparent"
                    >
                        {storeName}
                    </h1>
                    <p
                        class="text-xs sm:text-sm text-white/50 tracking-wider uppercase font-medium"
                    >
                        Belanja Mudah &amp; Terpercaya
                    </p>
                </div>

                <div
                    class="w-32 h-1 bg-white/10 rounded-full overflow-hidden relative mt-4"
                >
                    <div
                        class="absolute inset-0 bg-gradient-to-r from-white/40 via-white to-white/40 rounded-full animate-loadingBar"
                    ></div>
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
            tabindex="0"
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
                <button
                    onclick={(e) => {
                        showPopup = false;
                        handleBannerClick(popupBanner, e);
                    }}
                    class="block w-full overflow-hidden aspect-[4/5] bg-slate-100 relative cursor-pointer text-left"
                >
                    <img
                        src={popupBanner.image}
                        alt={popupBanner.alt || 'Promo Spesial'}
                        class="w-full h-full object-cover hover:scale-105 transition duration-500"
                    />
                </button>

                <!-- Optional Action Button -->
                {#if popupBanner.link && popupBanner.link !== '#'}
                    <div
                        class="p-4 bg-white border-t border-slate-50 flex justify-center"
                    >
                        <button
                            onclick={(e) => {
                                showPopup = false;
                                handleBannerClick(popupBanner, e);
                            }}
                            class="px-6 py-2.5 text-white font-bold rounded-xl text-xs sm:text-sm tracking-wide uppercase transition duration-200 shadow-md hover:shadow-lg w-full text-center cursor-pointer"
                            style="background-color: {primary};"
                        >
                            Lihat Detail Promo
                        </button>
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
</style>
