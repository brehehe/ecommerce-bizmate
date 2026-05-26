<script lang="ts">
    import StorefrontLayout from '@/components/layouts/StorefrontLayout.svelte';
    import { page, Link } from '@inertiajs/svelte';

    let { product, relatedProducts = [], storeName = '' } = $props();

    const primary = $derived(
        (page.props as any).theme?.primary_color ?? '#0c4cb4',
    );
    const secondary = $derived(
        (page.props as any).theme?.secondary_color ?? '#fa7315',
    );

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

    // ═══════════════════════════════════════
    //  GALLERY
    // ═══════════════════════════════════════
    function formatImagePath(path: string | null): string {
        if (!path) return '/noimage/image.png';
        if (
            path.startsWith('http://') ||
            path.startsWith('https://') ||
            path.startsWith('/')
        ) {
            return path;
        }
        return '/' + path;
    }

    function buildGallery(p: any): string[] {
        const imgs: string[] = [];
        if (p.images?.length > 0) {
            p.images.forEach((img: any) => {
                const src = img.url || img.path;
                if (src) imgs.push(formatImagePath(src));
            });
        }
        if (imgs.length === 0 && p.image) imgs.push(formatImagePath(p.image));
        return imgs;
    }

    const gallery = $derived(buildGallery(product));
    let activeIdx = $state(0);
    let variantOverride = $state<string | null>(null); // image from selected variant
    let lightboxOpen = $state(false);
    let mainImageHasError = $state(false);

    $effect(() => {
        displayImage;
        mainImageHasError = false;
    });

    /** The image shown in the main viewer */
    const displayImage = $derived(
        variantOverride ?? gallery[activeIdx] ?? null,
    );

    function pickGallery(i: number) {
        activeIdx = i;
        variantOverride = null;
    }

    // ═══════════════════════════════════════
    //  COMBINED SLIDES (gallery + variant images)
    //  Used only for the mobile slider.
    // ═══════════════════════════════════════
    type Slide =
        | { src: string; type: 'gallery'; galleryIdx: number }
        | {
              src: string;
              type: 'variant';
              optionId: number;
              optionName: string;
              variationId: string;
              variationName: string;
              available: boolean;
          };

    /**
     * Reactive array combining gallery images + variant option images.
     * $derived.by is required here so Svelte tracks reactive reads
     * inside the function and returns a plain array (not a function).
     */
    const combinedSlides: Slide[] = $derived.by(() => {
        const slides: Slide[] = gallery.map((src, i) => ({
            src,
            type: 'gallery' as const,
            galleryIdx: i,
        }));
        for (const variation of product.variations ?? []) {
            for (const opt of variation.options ?? []) {
                const img = getOptionImage(opt.id, variation.name);
                if (img) {
                    slides.push({
                        src: img,
                        type: 'variant' as const,
                        optionId: Number(opt.id),
                        optionName: String(opt.name ?? ''),
                        variationId: String(variation.id),
                        variationName: String(variation.name ?? ''),
                        available: isOptionAvailable(opt.id),
                    });
                }
            }
        }
        return slides;
    });

    let activeSlideIdx = $state(0);

    /**
     * Navigate mobile slider to index `idx` and imperatively sync
     * the gallery index or selected variant — no $effect needed,
     * avoiding the effect_update_depth_exceeded infinite loop.
     */
    function goToSlide(idx: number) {
        const len = combinedSlides.length;
        if (len === 0) return;
        activeSlideIdx = ((idx % len) + len) % len;
        const slide = combinedSlides[activeSlideIdx];
        if (!slide) return;
        if (slide.type === 'variant') {
            selectedOptions = {
                ...selectedOptions,
                [slide.variationId]: slide.optionId,
            };
            variantOverride = slide.src;
        } else {
            activeIdx = slide.galleryIdx;
            variantOverride = null;
        }
    }

    function sliderPrev() {
        goToSlide(activeSlideIdx - 1);
    }
    function sliderNext() {
        goToSlide(activeSlideIdx + 1);
    }

    // ═══════════════════════════════════════
    //  TOUCH SWIPE (mobile slider)
    // ═══════════════════════════════════════
    let touchStartX = $state(0);

    function onTouchStart(e: TouchEvent) {
        touchStartX = e.touches[0].clientX;
    }
    function onTouchEnd(e: TouchEvent) {
        const dx = e.changedTouches[0].clientX - touchStartX;
        if (Math.abs(dx) < 30) return; // ignore small nudges
        if (dx < 0) sliderNext();
        else sliderPrev();
    }

    let scrollY = $state(0);
    let searchQuery = $state('');
    let mobileMenuOpen = $state(false);
    const isScrolled = $derived(scrollY > 80);

    function shareProduct() {
        if (navigator.share) {
            navigator
                .share({
                    title: product.name,
                    text:
                        product.summary ||
                        `Beli ${product.name} di ${storeName}`,
                    url: window.location.href,
                })
                .catch(() => {});
        } else {
            navigator.clipboard.writeText(window.location.href);
            alert('Tautan produk berhasil disalin ke papan klip!');
        }
    }

    // ═══════════════════════════════════════
    //  VARIATIONS & VARIANTS
    //  Structure:
    //    product.variations[]  → { id, name, options[{ id, name, image }] }
    //    product.variants[]    → { id, image, productPrice{price}, productStock{stock,is_unlimited,min_purchase}, options[{id}] }
    //  Pivot: product_variant_option_combinations links variants ↔ options
    // ═══════════════════════════════════════
    const hasVariations = $derived(
        (product.variations?.length ?? 0) > 0 &&
            (product.variants?.length ?? 0) > 0,
    );

    /** selectedOptions: { [variationId]: optionId } */
    let selectedOptions: Record<string, number> = $state({});

    const variationCount = $derived(product.variations?.length ?? 0);
    const fullySelected = $derived(
        Object.keys(selectedOptions).length >= variationCount,
    );

    /** Returns the variant whose options exactly match selected option IDs */
    function findVariant(): any | null {
        if (!hasVariations) return null;
        const selectedIds = Object.values(selectedOptions).map(Number);
        if (selectedIds.length < variationCount) return null;

        return (
            product.variants?.find((v: any) => {
                const vOptIds: number[] =
                    v.options?.map((o: any) => Number(o.id)) ?? [];
                return (
                    vOptIds.length === selectedIds.length &&
                    selectedIds.every((id) => vOptIds.includes(id))
                );
            }) ?? null
        );
    }

    const matchingVariant = $derived(findVariant());

    function selectOption(variationId: string, optionId: number) {
        selectedOptions = { ...selectedOptions, [variationId]: optionId };
        qty = currentMinPurchase; // reset qty
        // Update main image if this option's variant has an image
        const variation = product.variations?.find(
            (v: any) => String(v.id) === variationId,
        );
        const optImg = getOptionImage(optionId, variation?.name || '');
        if (optImg) variantOverride = optImg;
        // Jump mobile slider to this variant's slide
        const idx = combinedSlides.findIndex(
            (s) => s.type === 'variant' && s.optionId === optionId,
        );
        if (idx !== -1) activeSlideIdx = idx;
    }

    function isSelected(variationId: string, optionId: number): boolean {
        return Number(selectedOptions[variationId]) === optionId;
    }

    function getSelectedLabel(variation: any): string | null {
        const optId = selectedOptions[String(variation.id)];
        if (optId == null) return null;
        return (
            variation.options?.find((o: any) => Number(o.id) === optId)?.name ??
            null
        );
    }

    /**
     * Find the FIRST variant that contains this option (used to get its image
     * or check availability).
     */
    function getVariantForOption(optionId: number): any | null {
        return (
            product.variants?.find((v: any) =>
                v.options?.some((o: any) => Number(o.id) === optionId),
            ) ?? null
        );
    }

    function getOptionImage(
        optionId: number,
        variationName: string,
    ): string | null {
        const lowerName = variationName.toLowerCase();
        const isColorVariation =
            lowerName.includes('warna') ||
            lowerName.includes('color') ||
            lowerName.includes('colour');

        // 1) Option itself may carry an image
        for (const variation of product.variations ?? []) {
            const opt = variation.options?.find(
                (o: any) => Number(o.id) === optionId,
            );
            if (opt?.image) return formatImagePath(opt.image);
        }

        // 2) Fall back to variant image only for color variations
        if (isColorVariation) {
            const varImg = getVariantForOption(optionId)?.image;
            return varImg ? formatImagePath(varImg) : null;
        }

        return null;
    }

    /** Is this option in-stock (has at least one variant with stock or unlimited)? */
    function isOptionAvailable(optionId: number): boolean {
        const variants =
            product.variants?.filter((v: any) =>
                v.options?.some((o: any) => Number(o.id) === optionId),
            ) ?? [];
        return variants.some(
            (v: any) =>
                v.product_stock?.is_unlimited ||
                (v.product_stock?.stock ?? 0) > 0,
        );
    }

    // ═══════════════════════════════════════
    //  PRICE
    //  - If variant fully selected → variant price
    //  - Else → base product price
    // ═══════════════════════════════════════
    const basePrice = $derived(product.product_price?.price ?? 0);
    const currentPrice = $derived(
        matchingVariant
            ? matchingVariant.is_promo
                ? Number(matchingVariant.promo_price)
                : Number(matchingVariant.product_price?.price ?? basePrice)
            : product.is_promo
              ? Number(product.promo_price)
              : Number(basePrice),
    );
    const originalPrice = $derived(
        matchingVariant
            ? matchingVariant.is_promo
                ? Number(matchingVariant.original_price)
                : null
            : product.is_promo
              ? Number(product.original_price)
              : null,
    );
    const discountPercentage = $derived(
        matchingVariant
            ? matchingVariant.is_promo
                ? Number(matchingVariant.discount_percentage)
                : 0
            : product.is_promo
              ? Number(product.discount_percentage)
              : 0,
    );

    function fmt(price: any): string {
        const n = Number(price);
        if (!n) return 'Hubungi Kami';
        return 'Rp ' + n.toLocaleString('id-ID');
    }

    // ═══════════════════════════════════════
    //  STOCK
    //  Rules (from user spec):
    //   - is_unlimited: ambil dari utama ATAU dari variant masing-masing
    //   - stock: dari variant jika ada (pisah-pisah), jika tidak ada dari utama
    //   - min_purchase: dari variant jika ada, else dari utama
    // ═══════════════════════════════════════
    const baseIsUnlimited = $derived(
        product.product_stock?.is_unlimited ?? false,
    );
    const baseStock = $derived(product.product_stock?.stock ?? 0);
    const baseMinPurchase = $derived(product.product_stock?.min_purchase ?? 1);

    const currentIsUnlimited = $derived(
        matchingVariant != null
            ? (matchingVariant.product_stock?.is_unlimited ?? baseIsUnlimited)
            : baseIsUnlimited,
    );
    const currentStock = $derived(
        matchingVariant?.product_stock?.stock ?? baseStock,
    );
    const currentMinPurchase = $derived(
        matchingVariant?.product_stock?.min_purchase ?? baseMinPurchase,
    );
    const isInStock = $derived(currentIsUnlimited || currentStock > 0);

    // ═══════════════════════════════════════
    //  QUANTITY
    // ═══════════════════════════════════════
    let qty = $state(1);

    $effect(() => {
        qty = currentMinPurchase;
    });

    function decQty() {
        if (qty > currentMinPurchase) qty--;
    }
    function incQty() {
        if (!currentIsUnlimited && qty >= currentStock) return;
        qty++;
    }

    // ═══════════════════════════════════════
    //  WHATSAPP CTA
    // ═══════════════════════════════════════
    const waNumber = $derived(
        (page.props as any).settings?.store_whatsapp ?? '',
    );

    function openWhatsapp() {
        const parts: string[] = [];
        (product.variations ?? []).forEach((v: any) => {
            const label = getSelectedLabel(v);
            if (label) parts.push(label);
        });
        const varNote = parts.length ? ` (${parts.join(' / ')})` : '';
        const msg = encodeURIComponent(
            `Halo, saya ingin memesan *${product.name}*${varNote} sebanyak ${qty} pcs dengan harga ${fmt(currentPrice)}. Apakah masih tersedia?`,
        );
        const num = waNumber.replace(/\D/g, '');
        window.open(`https://wa.me/${num}?text=${msg}`, '_blank');
    }

    // ═══════════════════════════════════════
    //  RELATED
    // ═══════════════════════════════════════
    function relImg(p: any): string | null {
        const path =
            p.images?.length > 0
                ? p.images[0].url || p.images[0].path
                : p.image;
        return path ? formatImagePath(path) : null;
    }

    // ═══════════════════════════════════════
    //  LIGHTBOX NAV
    // ═══════════════════════════════════════
    function lightboxPrev() {
        activeIdx = (activeIdx - 1 + gallery.length) % gallery.length;
        variantOverride = null;
    }
    function lightboxNext() {
        activeIdx = (activeIdx + 1) % gallery.length;
        variantOverride = null;
    }

    /** Helper: check if a specific option's variant is out of stock */
    function isVariantSlideOutOfStock(slide: Slide): boolean {
        if (slide.type !== 'variant') return false;
        return !slide.available;
    }
</script>

<svelte:head>
    <title>{product.name} — {storeName}</title>
    <meta
        name="description"
        content={product.summary ??
            `Beli ${product.name} di ${storeName}. Harga terbaik, pengiriman cepat.`}
    />
</svelte:head>

<svelte:window bind:scrollY />

<StorefrontLayout hideMobileHeader={true} hideMobileFooter={true}>
    <!-- MOBILE NAVBAR (Always visible, matching Image 3) -->
    <div
        class="md:hidden fixed top-0 left-0 right-0 z-40 bg-white border-b border-slate-100 shadow-xs py-2.5 px-3"
    >
        <div class="flex items-center gap-3">
            <!-- Back Button -->
            <button
                onclick={() => window.history.back()}
                class="w-9 h-9 flex items-center justify-center text-slate-700 hover:bg-slate-100 rounded-full transition active:scale-95 shrink-0"
                aria-label="Kembali"
            >
                <i class="ti ti-arrow-left text-xl"></i>
            </button>

            <!-- Search Bar -->
            <div class="flex-grow">
                <form
                    onsubmit={(e) => {
                        e.preventDefault();
                        if (searchQuery.trim()) {
                            router.get('/search', { q: searchQuery });
                        }
                    }}
                    class="relative"
                >
                    <input
                        type="text"
                        bind:value={searchQuery}
                        placeholder="Cari produk..."
                        class="w-full pl-3.5 pr-10 py-1.5 text-xs bg-slate-100 rounded-xl border border-transparent focus:outline-none focus:bg-white focus:border-slate-300 transition"
                    />
                    <button
                        type="submit"
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400"
                    >
                        <i class="ti ti-search text-base"></i>
                    </button>
                </form>
            </div>

            <!-- Right Icons: Share, Cart, Menu -->
            <div class="flex items-center gap-1.5 shrink-0">
                <!-- Share Button -->
                <button
                    onclick={shareProduct}
                    class="w-8 h-8 flex items-center justify-center text-slate-600 hover:bg-slate-100 rounded-full transition"
                    aria-label="Bagikan"
                >
                    <i class="ti ti-share text-lg"></i>
                </button>

                <!-- Cart Button -->
                <Link
                    href="/"
                    prefetch
                    class="w-8 h-8 flex items-center justify-center text-slate-600 hover:bg-slate-100 rounded-full transition"
                    aria-label="Keranjang"
                >
                    <i class="ti ti-shopping-cart text-lg"></i>
                </Link>

                <!-- Menu Button -->
                <button
                    onclick={() => (mobileMenuOpen = !mobileMenuOpen)}
                    class="w-8 h-8 flex items-center justify-center text-slate-600 hover:bg-slate-100 rounded-full transition"
                    aria-label="Menu"
                >
                    <i class="ti ti-dots text-lg"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile Dropdown Menu -->
    {#if mobileMenuOpen}
        <div
            class="fixed inset-0 z-50 md:hidden"
            onclick={() => (mobileMenuOpen = false)}
            role="presentation"
        >
            <!-- Backdrop -->
            <div class="absolute inset-0 bg-black/10"></div>
            <!-- Menu Box -->
            <div
                class="absolute right-4 top-14 w-44 bg-white rounded-2xl shadow-xl border border-slate-100 py-1.5 overflow-hidden z-10 animate-in zoom-in-95 duration-100"
                onclick={(e) => e.stopPropagation()}
                role="presentation"
            >
                <Link
                    href="/"
                    prefetch
                    class="flex items-center gap-2.5 px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition"
                >
                    <i class="ti ti-home text-sm text-slate-400"></i>
                    Beranda
                </Link>
                <button
                    onclick={() => {
                        mobileMenuOpen = false;
                        shareProduct();
                    }}
                    class="w-full flex items-center gap-2.5 px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition text-left"
                >
                    <i class="ti ti-share text-sm text-slate-400"></i>
                    Bagikan Produk
                </button>
                <button
                    onclick={() => {
                        mobileMenuOpen = false;
                        openWhatsapp();
                    }}
                    class="w-full flex items-center gap-2.5 px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition text-left border-t border-slate-100"
                >
                    <i
                        class="ti ti-brand-whatsapp text-sm text-green-500 animate-pulse"
                    ></i>
                    Tanya Penjual
                </button>
            </div>
        </div>
    {/if}

    <!-- ─────────────────────────────────────────────────────
     BREADCRUMB
───────────────────────────────────────────────────── -->
    <div class="bg-white border-b border-slate-100 py-2.5 hidden md:block">
        <nav
            class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center gap-1 text-[11px] sm:text-xs text-slate-400 flex-wrap"
        >
            <Link href="/" prefetch class="hover:text-slate-700 transition"
                >Beranda</Link
            >
            <i class="ti ti-chevron-right text-[10px]"></i>
            {#if product.category}
                <Link
                    href="/category/{product.category.slug ||
                        product.category.id}"
                    prefetch
                    class="hover:text-slate-700 transition"
                    >{product.category.name}</Link
                >
                <i class="ti ti-chevron-right text-[10px]"></i>
            {/if}
            <span class="text-slate-700 font-medium line-clamp-1"
                >{product.name}</span
            >
        </nav>
    </div>

    <!-- ─────────────────────────────────────────────────────
     MAIN PRODUCT CARD
───────────────────────────────────────────────────── -->
    <div class="bg-white md:pt-0">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-5 sm:py-8">
            <div
                class="grid grid-cols-1 md:grid-cols-[380px_1fr] lg:grid-cols-[420px_1fr] gap-5 lg:gap-10 items-start"
            >
                <!-- ══ LEFT: GALLERY ══════════════════════════════ -->
                <div class="flex flex-col gap-3">
                    <!-- Mobile Gallery (Slider — combined product + variant images) -->
                    <div
                        class="md:hidden relative aspect-square bg-slate-50 overflow-hidden -mx-4"
                        style="width: calc(100% + 2rem)"
                        ontouchstart={onTouchStart}
                        ontouchend={onTouchEnd}
                    >
                        {#if combinedSlides.length > 0}
                            {@const slide = combinedSlides[activeSlideIdx]}

                            <!-- Slide image -->
                            <img
                                src={slide.src}
                                alt="{product.name} {activeSlideIdx + 1}"
                                class="w-full h-full object-cover transition-opacity duration-300 {slide.type ===
                                    'variant' && !slide.available
                                    ? 'opacity-40'
                                    : ''}"
                                onerror={(e) => {
                                    (e.currentTarget as HTMLImageElement).src =
                                        '/noimage/image.png';
                                }}
                            />

                            <!-- Out-of-stock overlay for variant slides -->
                            {#if slide.type === 'variant' && !slide.available}
                                <div
                                    class="absolute inset-0 flex items-center justify-center z-10 pointer-events-none"
                                >
                                    <div
                                        class="bg-black/60 backdrop-blur-sm text-white font-black text-lg px-6 py-2 rounded-full tracking-widest shadow-xl border border-white/20"
                                    >
                                        HABIS
                                    </div>
                                </div>
                            {/if}

                            <!-- Variant name label (bottom-left) -->
                            {#if slide.type === 'variant'}
                                <div
                                    class="absolute bottom-10 left-3 z-10 pointer-events-none"
                                >
                                    <span
                                        class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-1 rounded-lg bg-black/55 backdrop-blur-sm text-white shadow"
                                    >
                                        <span class="opacity-70"
                                            >{slide.variationName}:</span
                                        >
                                        {slide.optionName}
                                    </span>
                                </div>
                            {/if}

                            {#if combinedSlides.length > 1}
                                <!-- Prev button -->
                                <button
                                    onclick={sliderPrev}
                                    class="absolute left-2 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-black/40 backdrop-blur-sm text-white flex items-center justify-center shadow-md active:scale-95 transition z-10"
                                    aria-label="Sebelumnya"
                                >
                                    <svg
                                        class="w-5 h-5"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2.5"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M15 19l-7-7 7-7"
                                        />
                                    </svg>
                                </button>

                                <!-- Next button -->
                                <button
                                    onclick={sliderNext}
                                    class="absolute right-2 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-black/40 backdrop-blur-sm text-white flex items-center justify-center shadow-md active:scale-95 transition z-10"
                                    aria-label="Berikutnya"
                                >
                                    <svg
                                        class="w-5 h-5"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2.5"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M9 5l7 7-7 7"
                                        />
                                    </svg>
                                </button>

                                <!-- Dot indicators -->
                                <div
                                    class="absolute bottom-3 left-1/2 -translate-x-1/2 flex gap-1.5 z-10"
                                >
                                    {#each combinedSlides as s, i}
                                        <button
                                            onclick={() => goToSlide(i)}
                                            class="rounded-full transition-all duration-300 {i ===
                                            activeSlideIdx
                                                ? s.type === 'variant'
                                                    ? 'w-5 h-1.5 bg-white'
                                                    : 'w-5 h-1.5 bg-white'
                                                : s.type === 'variant'
                                                  ? 'w-1.5 h-1.5 bg-white/50'
                                                  : 'w-1.5 h-1.5 bg-white/50'}"
                                            aria-label="Slide {i + 1}"
                                        ></button>
                                    {/each}
                                </div>

                                <!-- Fractional counter -->
                                <div
                                    class="absolute bottom-4 right-4 bg-black/50 text-white text-[11px] font-bold px-2.5 py-0.5 rounded-full backdrop-blur-sm select-none z-10"
                                >
                                    {activeSlideIdx + 1}/{combinedSlides.length}
                                </div>
                            {/if}
                        {:else}
                            <img
                                src="/noimage/image.png"
                                alt="No Image"
                                class="w-full h-full object-cover"
                            />
                        {/if}
                    </div>

                    <!-- Desktop Gallery (with Zoom and Thumbnails) -->
                    <div class="hidden md:flex flex-col gap-3">
                        <div
                            class="relative aspect-square rounded-2xl overflow-hidden bg-slate-50 border border-slate-100 group select-none {displayImage &&
                            displayImage !== '/noimage/image.png' &&
                            !mainImageHasError
                                ? 'cursor-zoom-in'
                                : ''}"
                            onclick={() =>
                                displayImage &&
                                displayImage !== '/noimage/image.png' &&
                                !mainImageHasError &&
                                (lightboxOpen = true)}
                            role="button"
                            tabindex="0"
                            onkeydown={(e) =>
                                e.key === 'Enter' &&
                                displayImage &&
                                displayImage !== '/noimage/image.png' &&
                                !mainImageHasError &&
                                (lightboxOpen = true)}
                        >
                            {#if displayImage && displayImage !== '/noimage/image.png' && !mainImageHasError}
                                <img
                                    src={displayImage}
                                    alt={product.name}
                                    class="w-full h-full object-cover transition duration-500 group-hover:scale-[1.03]"
                                    onerror={() => {
                                        mainImageHasError = true;
                                    }}
                                />
                            {:else}
                                <img
                                    src="/noimage/image.png"
                                    alt="No Image"
                                    class="w-full h-full object-cover"
                                />
                            {/if}

                            <!-- zoom hint -->
                            {#if displayImage && displayImage !== '/noimage/image.png' && !mainImageHasError}
                                <div
                                    class="absolute bottom-3 right-3 w-9 h-9 rounded-full bg-black/40 backdrop-blur-sm text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition shadow-lg"
                                >
                                    <i class="ti ti-zoom-in text-sm"></i>
                                </div>
                            {/if}
                        </div>

                        <!-- Thumbnails row -->
                        {#if gallery.length > 1}
                            <div class="flex gap-2 overflow-x-auto pb-1 snap-x">
                                {#each gallery as src, i}
                                    <button
                                        onclick={() => pickGallery(i)}
                                        class="w-[68px] h-[68px] sm:w-[76px] sm:h-[76px] rounded-xl overflow-hidden border-2 shrink-0 snap-start transition duration-200
                                           {activeIdx === i && !variantOverride
                                            ? 'border-orange-400 shadow-md shadow-orange-100'
                                            : 'border-slate-200 hover:border-slate-400 opacity-80 hover:opacity-100'}"
                                    >
                                        <img
                                            {src}
                                            alt="{product.name} {i + 1}"
                                            class="w-full h-full object-cover"
                                            onerror={(e) => {
                                                e.currentTarget.src =
                                                    '/noimage/image.png';
                                            }}
                                        />
                                    </button>
                                {/each}
                            </div>
                        {/if}
                    </div>

                    <!-- Share icons -->
                    <div class="flex items-center gap-2 pt-1">
                        <span class="text-xs text-slate-400 font-medium"
                            >Bagikan:</span
                        >
                        <a
                            href="https://web.whatsapp.com/send?text={encodeURIComponent(
                                product.name,
                            )}"
                            target="_blank"
                            rel="noopener"
                            class="w-7 h-7 rounded-full bg-green-500 text-white flex items-center justify-center hover:bg-green-600 transition text-xs"
                            ><i class="ti ti-brand-whatsapp"></i></a
                        >
                        <a
                            href="https://www.facebook.com/sharer/sharer.php?u=."
                            target="_blank"
                            rel="noopener"
                            class="w-7 h-7 rounded-full bg-blue-600 text-white flex items-center justify-center hover:bg-blue-700 transition text-xs"
                            ><i class="ti ti-brand-facebook"></i></a
                        >
                        <a
                            href="https://twitter.com/intent/tweet?text={encodeURIComponent(
                                product.name,
                            )}"
                            target="_blank"
                            rel="noopener"
                            class="w-7 h-7 rounded-full bg-slate-900 text-white flex items-center justify-center hover:bg-slate-700 transition text-xs"
                            ><i class="ti ti-brand-x"></i></a
                        >
                    </div>
                </div>

                <!-- ══ RIGHT: PRODUCT INFO ════════════════════════ -->
                <div class="flex flex-col gap-0 divide-y divide-slate-100">
                    <!-- Header: brand + name + rating/terjual -->
                    <div class="pb-4 flex flex-col gap-2">
                        {#if product.brand}
                            <div>
                                <span
                                    class="inline-flex items-center gap-1 text-[11px] font-bold px-2 py-0.5 rounded"
                                    style="background: {withOpacity(
                                        primary,
                                        0.1,
                                    )}; color: {primary};"
                                >
                                    <i class="ti ti-star-filled text-[9px]"></i>
                                    {product.brand}
                                </span>
                            </div>
                        {/if}
                        <h1
                            class="text-lg sm:text-xl font-semibold text-slate-800 leading-snug"
                        >
                            {product.name}
                        </h1>
                        <!-- Rating / terjual row -->
                        <div
                            class="flex items-center gap-3 text-xs text-slate-400 flex-wrap"
                        >
                            <span
                                class="text-slate-500 border-r border-slate-200 pr-3"
                                >Belum Ada Penilaian</span
                            >
                            <span class="border-r border-slate-200 pr-3"
                                >0 Terjual</span
                            >
                            {#if product.sku}
                                <span
                                    >SKU: <b class="text-slate-600"
                                        >{product.sku}</b
                                    ></span
                                >
                            {/if}
                        </div>
                    </div>

                    <!-- Price -->
                    <div class="py-4">
                        {#if currentPrice > 0}
                            <div class="flex items-baseline gap-3 flex-wrap">
                                <span
                                    class="text-3xl sm:text-4xl font-bold"
                                    style="color: {secondary};"
                                >
                                    {fmt(currentPrice)}
                                </span>
                                {#if originalPrice && originalPrice > currentPrice}
                                    <span
                                        class="text-sm sm:text-base text-slate-400 line-through font-medium"
                                    >
                                        {fmt(originalPrice)}
                                    </span>
                                    <span
                                        class="text-xs font-black px-2 py-0.5 rounded-md text-white shadow-sm"
                                        style="background-color: {secondary};"
                                    >
                                        -{discountPercentage}%
                                    </span>
                                {/if}
                            </div>
                            {#if hasVariations && !fullySelected}
                                <p class="text-xs text-slate-400 mt-1.5">
                                    Pilih semua variasi untuk melihat harga
                                </p>
                            {/if}
                        {:else}
                            <span class="text-2xl font-bold text-slate-700"
                                >Hubungi Kami</span
                            >
                        {/if}
                    </div>

                    <!-- Pengiriman -->
                    <div class="py-4 flex items-start gap-5">
                        <span
                            class="text-sm text-slate-500 w-28 shrink-0 font-medium pt-0.5"
                            >Pengiriman</span
                        >
                        <div
                            class="flex items-start gap-2 text-sm text-slate-700"
                        >
                            <i
                                class="ti ti-truck text-green-500 mt-0.5 text-base"
                            ></i>
                            <div>
                                <p class="font-semibold text-slate-800">
                                    Garansi tiba 1–3 hari kerja
                                </p>
                                <p class="text-xs text-slate-400 mt-0.5">
                                    JNE · J&T · SiCepat · Gosend · Grab
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Jaminan -->
                    <div class="py-4 flex items-start gap-5">
                        <span
                            class="text-sm text-slate-500 w-28 shrink-0 font-medium pt-0.5"
                            >Jaminan</span
                        >
                        <div
                            class="flex flex-wrap gap-3 text-xs text-slate-600"
                        >
                            <span class="flex items-center gap-1.5">
                                <i
                                    class="ti ti-shield-check text-green-500 text-sm"
                                ></i> Bebas Pengembalian
                            </span>
                            <span class="flex items-center gap-1.5">
                                <i class="ti ti-cash text-orange-400 text-sm"
                                ></i> COD Tersedia
                            </span>
                            <span class="flex items-center gap-1.5">
                                <i
                                    class="ti ti-rosette-discount-check text-blue-500 text-sm"
                                ></i> Produk Original
                            </span>
                        </div>
                    </div>

                    <!-- ── VARIATIONS ──────────────────────────── -->
                    {#if hasVariations}
                        {#each product.variations as variation}
                            {@const selLabel = getSelectedLabel(variation)}
                            <div class="py-4 flex flex-col gap-3">
                                <!-- Label row -->
                                <div class="flex items-center gap-2">
                                    <span
                                        class="text-sm font-bold text-slate-700"
                                    >
                                        Pilih {variation.name}:
                                    </span>
                                    {#if selLabel}
                                        <span
                                            class="text-sm font-bold"
                                            style="color: {primary};"
                                            >{selLabel}</span
                                        >
                                    {/if}
                                </div>

                                <!-- Option buttons -->
                                <div class="flex flex-wrap gap-2">
                                    {#each variation.options as opt}
                                        {@const optImg = getOptionImage(
                                            opt.id,
                                            variation.name,
                                        )}
                                        {@const available = isOptionAvailable(
                                            opt.id,
                                        )}
                                        {@const sel = isSelected(
                                            String(variation.id),
                                            opt.id,
                                        )}
                                        <button
                                            onclick={() =>
                                                available &&
                                                selectOption(
                                                    String(variation.id),
                                                    opt.id,
                                                )}
                                            class="relative flex items-center gap-2 px-3 py-2 rounded-xl border-2 text-xs font-semibold transition duration-150
                                               {sel
                                                ? 'shadow-sm'
                                                : 'border-slate-200 text-slate-700 bg-white hover:border-slate-300'}
                                               {!available
                                                ? 'opacity-40 cursor-not-allowed'
                                                : 'cursor-pointer'}"
                                            style={sel
                                                ? `border-color: ${secondary}; background: ${withOpacity(secondary, 0.03)}; color: ${secondary};`
                                                : ''}
                                            disabled={!available}
                                        >
                                            {#if optImg}
                                                <img
                                                    src={optImg}
                                                    alt={opt.name}
                                                    class="w-8 h-8 rounded-lg object-cover shrink-0"
                                                    onerror={(e) => {
                                                        e.currentTarget.src =
                                                            '/noimage/image.png';
                                                    }}
                                                />
                                            {/if}
                                            {opt.name}
                                            <!-- Out-of-stock diagonal stripe hint -->
                                            {#if !available}
                                                <span
                                                    class="absolute top-0.5 right-0.5 text-[9px] font-black text-red-400"
                                                    >✕</span
                                                >
                                            {/if}
                                        </button>
                                    {/each}
                                </div>
                            </div>
                        {/each}
                    {/if}

                    <!-- ── QUANTITY ────────────────────────────── -->
                    <div class="py-4 flex items-center gap-5">
                        <span
                            class="text-sm text-slate-500 w-28 shrink-0 font-medium"
                            >Kuantitas</span
                        >
                        <div class="flex items-center gap-3 flex-wrap">
                            <!-- Stepper -->
                            <div
                                class="flex items-center border border-slate-300 rounded-lg overflow-hidden"
                            >
                                <button
                                    onclick={decQty}
                                    disabled={qty <= currentMinPurchase}
                                    class="w-9 h-9 flex items-center justify-center hover:bg-slate-100 transition text-slate-600 disabled:opacity-30"
                                >
                                    <i class="ti ti-minus text-sm"></i>
                                </button>
                                <span
                                    class="w-12 text-center text-sm font-black text-slate-800 tabular-nums"
                                    >{qty}</span
                                >
                                <button
                                    onclick={incQty}
                                    disabled={!currentIsUnlimited &&
                                        qty >= currentStock}
                                    class="w-9 h-9 flex items-center justify-center hover:bg-slate-100 transition text-slate-600 disabled:opacity-30"
                                >
                                    <i class="ti ti-plus text-sm"></i>
                                </button>
                            </div>

                            <!-- Stock label -->
                            {#if hasVariations && !fullySelected}
                                <span class="text-xs text-slate-400"
                                    >Pilih variasi terlebih dahulu</span
                                >
                            {:else if currentIsUnlimited}
                                <span
                                    class="text-xs font-bold text-green-600 tracking-wide"
                                    >TERSEDIA</span
                                >
                            {:else if isInStock}
                                <span class="text-xs font-bold text-green-600"
                                    >Stok: {currentStock}</span
                                >
                            {:else}
                                <span class="text-xs font-bold text-red-500"
                                    >HABIS</span
                                >
                            {/if}

                            {#if currentMinPurchase > 1}
                                <span class="text-[11px] text-slate-400"
                                    >Min. {currentMinPurchase} pcs</span
                                >
                            {/if}
                        </div>
                    </div>

                    <!-- ── CTA BUTTONS ─────────────────────────── -->
                    <div class="pt-5 flex flex-col sm:flex-row gap-3">
                        <button
                            onclick={openWhatsapp}
                            class="flex-1 flex items-center justify-center gap-2 py-3.5 rounded-xl font-bold text-sm text-white shadow-lg hover:shadow-xl hover:-translate-y-0.5 transition duration-200"
                            style="background: linear-gradient(135deg, {secondary}, {withOpacity(
                                secondary,
                                0.8,
                            )});"
                        >
                            <i class="ti ti-shopping-cart text-base"></i>
                            Beli Sekarang
                        </button>
                        <button
                            onclick={openWhatsapp}
                            class="flex-1 flex items-center justify-center gap-2 py-3.5 rounded-xl font-bold text-sm border-2 hover:shadow-md transition duration-200"
                            style="border-color: {primary}; color: {primary};"
                        >
                            <i class="ti ti-brand-whatsapp text-base"></i>
                            Chat WhatsApp
                        </button>
                    </div>

                    <!-- Product meta footer -->
                    {#if product.weight || product.brand || product.category}
                        <div
                            class="pt-4 flex flex-wrap gap-x-5 gap-y-1 text-[11px] text-slate-400"
                        >
                            {#if product.category}
                                <span
                                    >Kategori: <b class="text-slate-600"
                                        >{product.category.name}</b
                                    ></span
                                >
                            {/if}
                            {#if product.brand}
                                <span
                                    >Merk: <b class="text-slate-600"
                                        >{product.brand}</b
                                    ></span
                                >
                            {/if}
                            {#if product.weight}
                                <span
                                    >Berat: <b class="text-slate-600"
                                        >{product.weight} g</b
                                    ></span
                                >
                            {/if}
                        </div>
                    {/if}
                </div>
                <!-- /RIGHT -->
            </div>
            <!-- /grid -->
        </div>
    </div>

    <!-- ─────────────────────────────────────────────────────
     DESKRIPSI / PENGIRIMAN / ULASAN (STACKED VERTICALLY)
───────────────────────────────────────────────────── -->
    <div
        class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 flex flex-col gap-6"
    >
        <!-- Deskripsi Section -->
        <div
            class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 sm:p-7"
        >
            <h3
                class="text-base font-bold text-slate-800 flex items-center gap-2 mb-5"
            >
                <i class="ti ti-file-text text-lg" style="color: {primary};"
                ></i>
                Deskripsi Produk
            </h3>
            {#if product.description}
                <div
                    class="prose prose-slate max-w-none text-sm leading-relaxed text-slate-700"
                >
                    {@html product.description}
                </div>
            {:else if product.summary}
                <p class="text-sm text-slate-600 leading-relaxed">
                    {product.summary}
                </p>
            {:else}
                <div class="text-center py-10 text-slate-400">
                    <i class="ti ti-file-text text-5xl block mb-2"></i>
                    <p class="text-sm">Deskripsi belum tersedia</p>
                </div>
            {/if}
        </div>

        <!-- Pengiriman Section -->
        <div
            class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 sm:p-7"
        >
            <h3
                class="text-base font-bold text-slate-800 flex items-center gap-2 mb-5"
            >
                <i class="ti ti-truck text-lg" style="color: {primary};"></i>
                Informasi Pengiriman
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {#each [{ icon: 'ti-map-pin', title: 'Dikirim dari', desc: 'Lokasi toko' }, { icon: 'ti-clock', title: 'Estimasi tiba', desc: '1–3 hari kerja setelah pembayaran dikonfirmasi' }, { icon: 'ti-package', title: 'Pengemasan', desc: 'Dikemas aman: bubble wrap + kardus double layer' }, { icon: 'ti-truck', title: 'Ekspedisi', desc: 'JNE · J&T Express · SiCepat · Gosend · Grab Express' }] as row}
                    <div
                        class="flex items-start gap-4 p-4 rounded-xl bg-slate-50 border border-slate-100"
                    >
                        <div
                            class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0"
                            style="background:{withOpacity(
                                primary,
                                0.08,
                            )}; color:{primary};"
                        >
                            <i class="ti {row.icon}"></i>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-slate-700">
                                {row.title}
                            </p>
                            <p class="text-sm text-slate-500 mt-0.5">
                                {row.desc}
                            </p>
                        </div>
                    </div>
                {/each}
            </div>
        </div>

        <!-- Ulasan Section -->
        <div
            class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 sm:p-7"
        >
            <h3
                class="text-base font-bold text-slate-800 flex items-center gap-2 mb-5"
            >
                <i class="ti ti-star text-lg" style="color: {primary};"></i>
                Ulasan Pembeli
            </h3>
            <div class="text-center py-10">
                <div
                    class="w-14 h-14 rounded-2xl flex items-center justify-center mx-auto mb-3"
                    style="background:{withOpacity(
                        primary,
                        0.06,
                    )}; color:{primary};"
                >
                    <i class="ti ti-star text-2xl"></i>
                </div>
                <p class="font-bold text-slate-700 mb-1">Belum ada ulasan</p>
                <p class="text-sm text-slate-400">
                    Jadilah yang pertama memberikan ulasan
                </p>
            </div>
        </div>
    </div>

    <!-- ─────────────────────────────────────────────────────
     RELATED PRODUCTS
───────────────────────────────────────────────────── -->
    {#if relatedProducts.length > 0}
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pb-10">
            <div class="flex items-center justify-between mb-4">
                <h2
                    class="font-outfit font-black text-base sm:text-lg text-slate-800 flex items-center gap-2"
                >
                    <div
                        class="w-7 h-7 rounded-lg flex items-center justify-center text-white text-xs"
                        style="background: linear-gradient(135deg, {primary}, {secondary});"
                    >
                        <i class="ti ti-sparkles"></i>
                    </div>
                    Produk Serupa
                </h2>
                {#if product.category}
                    <Link
                        href="/category/{product.category.slug ||
                            product.category.id}"
                        prefetch
                        class="text-xs font-bold flex items-center gap-1 hover:underline"
                        style="color:{primary};"
                    >
                        Lihat Semua <i class="ti ti-arrow-right text-xs"></i>
                    </Link>
                {/if}
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                {#each relatedProducts as rp}
                    {@const ri = relImg(rp)}
                    {@const isPromo = rp.is_promo}
                    {@const price = isPromo
                        ? rp.promo_price
                        : (rp.product_price?.price ?? 0)}
                    {@const originalPrice = isPromo ? rp.original_price : 0}
                    {@const discountPercentage = isPromo
                        ? rp.discount_percentage
                        : 0}
                    {@const rating = (4.5 + ((rp.id || 0) % 6) * 0.1).toFixed(
                        1,
                    )}
                    <Link
                        href="/products/{rp.slug || rp.id}"
                        prefetch
                        class="group bg-white rounded-xl border border-slate-100 hover:border-slate-200 hover:shadow-md overflow-hidden transition cursor-pointer flex flex-col h-full"
                    >
                        <div class="relative aspect-square overflow-hidden">
                            {#if ri}
                                <img
                                    src={ri}
                                    alt={rp.name}
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
                        <div class="p-3 flex-1 flex flex-col">
                            <div>
                                <p
                                    class="text-[9px] sm:text-[10px] font-black uppercase tracking-wider mb-1"
                                    style="color: {primary};"
                                >
                                    {rp.category?.name || 'PRODUK'}
                                </p>
                                <p
                                    class="text-xs sm:text-sm font-black leading-tight line-clamp-2 mb-1"
                                    style="color: {primary};"
                                >
                                    {rp.name}
                                </p>
                                <div class="flex items-center gap-1 mt-1">
                                    <i
                                        class="ti ti-star-filled text-amber-500 text-[10px]"
                                    ></i>
                                    <span
                                        class="text-[10px] text-slate-500 font-bold"
                                        >{rating}</span
                                    >
                                </div>
                                <hr class="border-slate-100 my-2" />
                                <div class="mb-3">
                                    {#if price > 0}
                                        <p
                                            class="text-sm sm:text-base font-black leading-tight"
                                            style="color: {secondary};"
                                        >
                                            {fmt(price)}
                                        </p>
                                        {#if isPromo && originalPrice > price}
                                            <p
                                                class="text-[10px] sm:text-xs text-slate-400 line-through font-medium mt-0.5"
                                            >
                                                {fmt(originalPrice)}
                                            </p>
                                        {/if}
                                    {:else}
                                        <p
                                            class="text-xs text-slate-400 font-semibold"
                                        >
                                            Hubungi Kami
                                        </p>
                                    {/if}
                                </div>
                            </div>
                            <div class="mt-auto pt-3">
                                <span
                                    class="w-full flex items-center justify-center gap-1.5 py-2 px-3 rounded-xl font-bold text-[10px] sm:text-xs text-white uppercase tracking-wider transition duration-200 hover:brightness-95 active:scale-[0.98]"
                                    style="background-color: {primary};"
                                >
                                    <i
                                        class="ti ti-shopping-cart text-xs sm:text-sm"
                                    ></i>
                                    + KERANJANG
                                </span>
                            </div>
                        </div>
                    </Link>
                {/each}
            </div>
        </div>
    {/if}

    <!-- ─────────────────────────────────────────────────────
     LIGHTBOX
───────────────────────────────────────────────────── -->
    {#if lightboxOpen && displayImage}
        <div
            class="fixed inset-0 z-[200] flex items-center justify-center bg-black/90 backdrop-blur-md"
            onclick={() => (lightboxOpen = false)}
            role="button"
            tabindex="0"
            onkeydown={(e) => e.key === 'Escape' && (lightboxOpen = false)}
        >
            <!-- Close -->
            <button
                onclick={() => (lightboxOpen = false)}
                class="absolute top-4 right-4 w-11 h-11 rounded-full bg-white/15 hover:bg-white/25 text-white flex items-center justify-center transition border border-white/20 z-50"
            >
                <i class="ti ti-x text-lg"></i>
            </button>

            <!-- Prev/Next (only if gallery has multiple) -->
            {#if gallery.length > 1}
                <button
                    onclick={(e) => {
                        e.stopPropagation();
                        lightboxPrev();
                    }}
                    class="absolute left-4 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-white/15 hover:bg-white/25 text-white flex items-center justify-center transition z-50"
                >
                    <i class="ti ti-chevron-left text-xl"></i>
                </button>
                <button
                    onclick={(e) => {
                        e.stopPropagation();
                        lightboxNext();
                    }}
                    class="absolute right-4 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-white/15 hover:bg-white/25 text-white flex items-center justify-center transition z-50"
                >
                    <i class="ti ti-chevron-right text-xl"></i>
                </button>
            {/if}

            <!-- Image -->
            <div
                class="relative w-[92vw] max-w-3xl px-10 sm:px-16"
                onclick={(e) => e.stopPropagation()}
            >
                <img
                    src={displayImage}
                    alt={product.name}
                    class="w-full max-h-[80vh] object-contain rounded-xl shadow-2xl animate-pop"
                    onerror={(e) => {
                        (e.currentTarget as HTMLImageElement).src =
                            '/noimage/image.png';
                    }}
                />
                <!-- Dot indicators -->
                {#if gallery.length > 1}
                    <div class="flex justify-center gap-2 mt-4">
                        {#each gallery as _, i}
                            <button
                                onclick={(e) => {
                                    e.stopPropagation();
                                    pickGallery(i);
                                }}
                                class="w-2 h-2 rounded-full transition {activeIdx ===
                                i
                                    ? 'bg-white'
                                    : 'bg-white/30'}"
                            ></button>
                        {/each}
                    </div>
                {/if}
            </div>
        </div>
    {/if}
</StorefrontLayout>

<style>
    @keyframes pop {
        from {
            transform: scale(0.95);
            opacity: 0;
        }
        to {
            transform: scale(1);
            opacity: 1;
        }
    }
    .animate-pop {
        animation: pop 0.2s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    /* prose reset for product descriptions */
    :global(.prose h1, .prose h2, .prose h3) {
        font-weight: 700;
        margin-bottom: 0.5rem;
        color: #1e293b;
    }
    :global(.prose p) {
        margin-bottom: 0.75rem;
    }
    :global(.prose ul, .prose ol) {
        padding-left: 1.25rem;
        margin-bottom: 0.75rem;
    }
    :global(.prose li) {
        margin-bottom: 0.25rem;
    }
    :global(.prose strong) {
        font-weight: 700;
        color: #1e293b;
    }
    :global(.prose a) {
        color: #2563eb;
        text-decoration: underline;
    }
</style>
