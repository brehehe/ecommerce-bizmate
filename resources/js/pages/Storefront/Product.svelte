<script lang="ts">
    import StorefrontLayout from '@/components/layouts/StorefrontLayout.svelte';
    import { usePage, Link, router } from '@inertiajs/svelte';
    import { fade, fly } from 'svelte/transition';
    import { cubicOut } from 'svelte/easing';
    import { showToast } from '@/utils/toast';
    import VariantSelectorModal from '@/components/Storefront/VariantSelectorModal.svelte';
    import PremiumVideoPlayer from '@/components/ui/PremiumVideoPlayer.svelte';

    let {
        product,
        relatedProducts = [],
        storeName = '',
        bundlingPromos = [],
        reviews = [] as any[],
        shippingInfo = {} as any,
    } = $props();

    let selectedVariantProduct = $state<any>(null);
    let showVariantModal = $state(false);
    let showSpecsModal = $state(false);
    let userHeight = $state('');
    let userWeight = $state('');

    const recommendedSize = $derived.by(() => {
        const h = Number(userHeight);
        const w = Number(userWeight);
        if (!h || !w || !product.size_chart?.rows) return null;

        const matched = product.size_chart.rows.find((row) => {
            const hasHeightRule = row.min_height && row.max_height;
            const hasWeightRule = row.min_weight && row.max_weight;

            const heightOk = hasHeightRule
                ? h >= Number(row.min_height) && h <= Number(row.max_height)
                : true;
            const weightOk = hasWeightRule
                ? w >= Number(row.min_weight) && w <= Number(row.max_weight)
                : true;

            if (hasHeightRule || hasWeightRule) {
                return heightOk && weightOk;
            }
            return false;
        });

        return matched ? matched.size : null;
    });

    const page = usePage();

    const primary = $derived(
        (page.props as any).theme?.primary_color ?? '#0c4cb4',
    );
    const secondary = $derived(
        (page.props as any).theme?.secondary_color ?? '#fa7315',
    );
    const cartCount = $derived((page.props as any).cartCount || 0);

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
    function formatImagePath(path: any): string {
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

    const parsedSpecifications = $derived.by(() => {
        if (!product.specifications) return [];
        let specsObj = product.specifications;
        if (typeof specsObj === 'string') {
            try {
                specsObj = JSON.parse(specsObj);
            } catch (e) {
                return [];
            }
        }
        if (Array.isArray(specsObj)) {
            return specsObj
                .map((item) => {
                    if (Array.isArray(item) && item.length >= 2)
                        return [item[0], item[1]];
                    if (typeof item === 'object' && item !== null) {
                        const keys = Object.keys(item);
                        if (keys.length > 0) return [keys[0], item[keys[0]]];
                    }
                    return null;
                })
                .filter(Boolean) as [string, any][];
        }
        if (typeof specsObj === 'object' && specsObj !== null) {
            return Object.entries(specsObj);
        }
        return [];
    });
    let activeIdx = $state(0);
    let variantOverride = $state<string | null>(null); // image from selected variant
    let lightboxOpen = $state(false);
    let mainImageHasError = $state(false);

    // Lightbox for review media
    let reviewLightboxOpen = $state(false);
    let reviewLightboxMedia = $state<{
        type: 'video' | 'image';
        url: string;
    } | null>(null);

    // Report review
    let showReportModal = $state(false);
    let reportingReview = $state<any>(null);
    let reportReason = $state('');
    let submittingReport = $state(false);

    function openReportModal(review: any) {
        reportingReview = review;
        reportReason = '';
        showReportModal = true;
    }

    function submitReport() {
        if (!reportReason.trim()) return;
        submittingReport = true;
        router.post(
            `/reviews/${reportingReview.id}/report`,
            { reason: reportReason.trim() },
            {
                onSuccess: () => {
                    showReportModal = false;
                    showToast('Ulasan berhasil dilaporkan.', 'success', 'top');
                },
                onError: (errors: any) => {
                    const msg = Object.values(errors)[0] as string;
                    showToast(
                        msg ?? 'Gagal melaporkan ulasan.',
                        'error',
                        'top',
                    );
                },
                onFinish: () => {
                    submittingReport = false;
                },
            },
        );
    }

    type DesktopSlide =
        | { type: 'image'; src: string; idx: number }
        | { type: 'video'; path: string }
        | { type: '3d'; path: string; usdz_path?: string };

    const desktopSlides: DesktopSlide[] = $derived.by(() => {
        const slides: DesktopSlide[] = [];

        if (product.video_path) {
            slides.push({
                type: 'video' as const,
                path: product.video_path,
            });
        }

        if (product.model_3d_path) {
            slides.push({
                type: '3d' as const,
                path: product.model_3d_path,
                usdz_path: product.model_3d_usdz_path,
            });
        }

        gallery.forEach((src, i) => {
            slides.push({
                type: 'image' as const,
                src,
                idx: i,
            });
        });

        return slides;
    });

    let activeDesktopSlideIdx = $state(0);

    $effect(() => {
        displayImage;
        mainImageHasError = false;
    });

    /** The image shown in the main viewer */
    const displayImage = $derived(
        variantOverride ?? gallery[activeIdx] ?? null,
    );

    function pickGallery(i: number) {
        activeDesktopSlideIdx = i;
        variantOverride = null;
        const slide = desktopSlides[i];
        if (slide && slide.type === 'image') {
            activeIdx = slide.idx;
        }
    }

    // ═══════════════════════════════════════
    //  COMBINED SLIDES (gallery + variant images)
    //  Used only for the mobile slider.
    // ═══════════════════════════════════════
    type Slide =
        | { src: string; type: 'gallery'; galleryIdx: number }
        | { type: 'video'; path: string }
        | { type: '3d'; path: string; usdz_path?: string }
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
     * Reactive array combining video/3D assets + gallery images + variant option images.
     */
    const combinedSlides: Slide[] = $derived.by(() => {
        const slides: Slide[] = [];

        if (product.video_path) {
            slides.push({
                type: 'video' as const,
                path: product.video_path,
            });
        }

        if (product.model_3d_path) {
            slides.push({
                type: '3d' as const,
                path: product.model_3d_path,
                usdz_path: product.model_3d_usdz_path,
            });
        }

        gallery.forEach((src, i) => {
            slides.push({
                src,
                type: 'gallery' as const,
                galleryIdx: i,
            });
        });

        for (const variation of product.variations ?? []) {
            for (const opt of variation.options ?? []) {
                const img = getOptionImage(opt.id, variation.name);
                if (img) {
                    slides.push({
                        src: img,
                        type: 'variant' as const,
                        optionId: String(opt.id),
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
    let sliderContainer = $state<HTMLDivElement | null>(null);
    let isScrollingFromButton = false;

    function syncActiveStatesForIndex(index: number) {
        const slide = combinedSlides[index];
        if (!slide) return;
        if (slide.type === 'variant') {
            selectedOptions = {
                ...selectedOptions,
                [slide.variationId]: slide.optionId,
            };
            variantOverride = slide.src;
            const firstImgIdx = desktopSlides.findIndex(
                (ds) => ds.type === 'image',
            );
            activeDesktopSlideIdx = firstImgIdx !== -1 ? firstImgIdx : 0;
        } else if (slide.type === 'gallery') {
            activeIdx = slide.galleryIdx;
            variantOverride = null;
            const dsIdx = desktopSlides.findIndex(
                (ds) => ds.type === 'image' && ds.idx === slide.galleryIdx,
            );
            if (dsIdx !== -1) activeDesktopSlideIdx = dsIdx;
        } else if (slide.type === 'video') {
            variantOverride = null;
            const dsIdx = desktopSlides.findIndex((ds) => ds.type === 'video');
            if (dsIdx !== -1) activeDesktopSlideIdx = dsIdx;
        } else if (slide.type === '3d') {
            variantOverride = null;
            const dsIdx = desktopSlides.findIndex((ds) => ds.type === '3d');
            if (dsIdx !== -1) activeDesktopSlideIdx = dsIdx;
        }
    }

    function handleSliderScroll(e: Event) {
        if (isScrollingFromButton) return;
        const el = e.currentTarget as HTMLDivElement;
        const width = el.clientWidth;
        if (width > 0) {
            const index = Math.round(el.scrollLeft / width);
            if (
                index !== activeSlideIdx &&
                index >= 0 &&
                index < combinedSlides.length
            ) {
                activeSlideIdx = index;
                syncActiveStatesForIndex(index);
            }
        }
    }

    /**
     * Navigate mobile slider to index `idx` and imperatively sync
     * the gallery index or selected variant — no $effect needed,
     * avoiding the effect_update_depth_exceeded infinite loop.
     */
    function goToSlide(idx: number) {
        const len = combinedSlides.length;
        if (len === 0) return;
        activeSlideIdx = ((idx % len) + len) % len;

        if (sliderContainer) {
            isScrollingFromButton = true;
            sliderContainer.scrollTo({
                left: activeSlideIdx * sliderContainer.clientWidth,
                behavior: 'smooth',
            });
            setTimeout(() => {
                isScrollingFromButton = false;
            }, 500);
        }

        syncActiveStatesForIndex(activeSlideIdx);
    }

    function sliderPrev() {
        goToSlide(activeSlideIdx - 1);
    }
    function sliderNext() {
        goToSlide(activeSlideIdx + 1);
    }

    // ── Camera AR Modal ─────────────────────────────────────────────────────
    let isCameraModalOpen = $state(false);
    let webcamStream = $state<MediaStream | null>(null);
    let cameraVideoEl = $state<HTMLVideoElement | null>(null);
    /** Which 3D slide path is being AR-previewed */
    let arModelPath = $state('');
    let arModelUsdz = $state('');

    /**
     * Open the camera modal for a specific 3D slide.
     * The bad pattern before was: we set isWebcamActive=true which triggered a
     * $effect that read isWebcamActive → Svelte re-ran the effect immediately
     * and called stopWebcam(). Now we use an explicit modal pattern instead.
     */
    async function openCameraModal(modelPath: string, usdzPath: string = '') {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment' },
                audio: false,
            });
            arModelPath = modelPath;
            arModelUsdz = usdzPath;
            webcamStream = stream;
            isCameraModalOpen = true;
        } catch (err) {
            console.error('Gagal mengakses kamera:', err);
            showToast(
                'Gagal mengakses kamera. Pastikan izin kamera telah diberikan.',
                'error',
                'top',
            );
        }
    }

    function closeCameraModal() {
        isCameraModalOpen = false;
        if (webcamStream) {
            webcamStream.getTracks().forEach((track) => track.stop());
            webcamStream = null;
        }
        arModelPath = '';
        arModelUsdz = '';
    }

    /**
     * Once the camera modal is open and cameraVideoEl is bound, assign the stream.
     * This runs AFTER isCameraModalOpen=true has rendered the <video> element.
     */
    $effect(() => {
        const stream = webcamStream;
        const el = cameraVideoEl;
        if (stream && el && el.srcObject !== stream) {
            el.srcObject = stream;
        }
    });

    /**
     * Stop camera if user navigates away from the 3D slide.
     * We track the slide indices WITHOUT reading isCameraModalOpen reactively,
     * to avoid the "effect triggers its own dependencies" loop.
     */
    let _prevDesktopSlideIdx = $state(0);
    let _prevSlideIdx = $state(0);
    $effect(() => {
        const d = activeDesktopSlideIdx;
        const m = activeSlideIdx;
        if (d !== _prevDesktopSlideIdx || m !== _prevSlideIdx) {
            _prevDesktopSlideIdx = d;
            _prevSlideIdx = m;
            // Close camera if open — reading isCameraModalOpen here is fine
            // because we only change it when slide indices change, not when
            // isCameraModalOpen itself changes (no circular dependency).
            if (isCameraModalOpen) {
                closeCameraModal();
            }
        }
    });

    let scrollY = $state(0);
    let searchQuery = $state('');
    let mobileMenuOpen = $state(false);
    const isScrolled = $derived(scrollY > 80);

    function handleSearch(e: Event) {
        e.preventDefault();
        const query = searchQuery.trim();
        if (query) {
            router.get('/search', { q: query });
        } else {
            router.get('/search');
        }
    }

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
            showToast(
                'Tautan produk berhasil disalin ke papan klip!',
                'success',
                'top',
            );
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
    let selectedOptions: Record<string, string> = $state({});

    const variationCount = $derived(product.variations?.length ?? 0);
    const fullySelected = $derived(
        Object.keys(selectedOptions).length >= variationCount,
    );

    /** Returns the variant whose options exactly match selected option IDs */
    function findVariant(): any | null {
        if (!hasVariations) return null;
        const selectedIds = Object.values(selectedOptions).map(String);
        if (selectedIds.length < variationCount) return null;

        return (
            product.variants?.find((v: any) => {
                const vOptIds: string[] =
                    v.options?.map((o: any) => String(o.id)) ?? [];
                return (
                    vOptIds.length === selectedIds.length &&
                    selectedIds.every((id) => vOptIds.includes(id))
                );
            }) ?? null
        );
    }

    const matchingVariant = $derived(findVariant());

    function selectOption(variationId: string, optionId: string) {
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
        if (idx !== -1) {
            activeSlideIdx = idx;
            if (sliderContainer) {
                isScrollingFromButton = true;
                sliderContainer.scrollTo({
                    left: idx * sliderContainer.clientWidth,
                    behavior: 'auto',
                });
                setTimeout(() => {
                    isScrollingFromButton = false;
                }, 100);
            }
        }

        const firstImgIdx = desktopSlides.findIndex(
            (ds) => ds.type === 'image',
        );
        activeDesktopSlideIdx = firstImgIdx !== -1 ? firstImgIdx : 0;
    }

    function isSelected(variationId: string, optionId: string): boolean {
        return String(selectedOptions[variationId]) === String(optionId);
    }

    function getSelectedLabel(variation: any): string | null {
        const optId = selectedOptions[String(variation.id)];
        if (optId == null) return null;
        return (
            variation.options?.find((o: any) => String(o.id) === String(optId))
                ?.name ?? null
        );
    }

    /**
     * Find the FIRST variant that contains this option (used to get its image
     * or check availability).
     */
    function getVariantForOption(optionId: string): any | null {
        return (
            product.variants?.find((v: any) =>
                v.options?.some((o: any) => String(o.id) === String(optionId)),
            ) ?? null
        );
    }

    function getOptionImage(
        optionId: string,
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
                (o: any) => String(o.id) === String(optionId),
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
    function isOptionAvailable(optionId: string): boolean {
        const variants =
            product.variants?.filter((v: any) =>
                v.options?.some((o: any) => String(o.id) === String(optionId)),
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

    const activePromoRule = $derived(
        matchingVariant?.promo_rule ?? product.promo_rule ?? null,
    );

    const isPromoRuleSatisfied = $derived(
        activePromoRule ? qty >= activePromoRule.min_qty : false,
    );

    const derivedPromoPrice = $derived.by(() => {
        if (!activePromoRule) return null;
        if (
            activePromoRule.promo_price !== null &&
            activePromoRule.promo_price !== undefined
        ) {
            return Number(activePromoRule.promo_price);
        }
        const normalPrice = matchingVariant
            ? Number(matchingVariant.product_price?.price ?? basePrice)
            : Number(basePrice);

        if (activePromoRule.discount_type === 'percentage') {
            return (
                normalPrice -
                normalPrice * (Number(activePromoRule.discount_value) / 100)
            );
        } else if (activePromoRule.discount_type === 'fixed') {
            return normalPrice - Number(activePromoRule.discount_value);
        }
        return normalPrice;
    });

    const currentPrice = $derived.by(() => {
        if (matchingVariant) {
            if (matchingVariant.is_promo) {
                return Number(matchingVariant.promo_price);
            }
            if (isPromoRuleSatisfied && derivedPromoPrice !== null) {
                return Math.max(0, derivedPromoPrice);
            }
            return Number(matchingVariant.product_price?.price ?? basePrice);
        } else {
            if (product.is_promo) {
                return Number(product.promo_price);
            }
            if (isPromoRuleSatisfied && derivedPromoPrice !== null) {
                return Math.max(0, derivedPromoPrice);
            }
            return Number(basePrice);
        }
    });

    const originalPrice = $derived.by(() => {
        if (matchingVariant) {
            if (matchingVariant.is_promo) {
                return Number(matchingVariant.original_price);
            }
            if (isPromoRuleSatisfied && derivedPromoPrice !== null) {
                return Number(
                    matchingVariant.product_price?.price ?? basePrice,
                );
            }
            return null;
        } else {
            if (product.is_promo) {
                return Number(product.original_price);
            }
            if (isPromoRuleSatisfied && derivedPromoPrice !== null) {
                return Number(basePrice);
            }
            return null;
        }
    });

    const discountPercentage = $derived.by(() => {
        if (matchingVariant) {
            if (matchingVariant.is_promo) {
                return Number(matchingVariant.discount_percentage);
            }
            if (isPromoRuleSatisfied && derivedPromoPrice !== null) {
                const normal = Number(
                    matchingVariant.product_price?.price ?? basePrice,
                );
                if (normal > 0) {
                    return Math.round(
                        ((normal - derivedPromoPrice) / normal) * 100,
                    );
                }
            }
            return 0;
        } else {
            if (product.is_promo) {
                return Number(product.discount_percentage);
            }
            if (isPromoRuleSatisfied && derivedPromoPrice !== null) {
                const normal = Number(basePrice);
                if (normal > 0) {
                    return Math.round(
                        ((normal - derivedPromoPrice) / normal) * 100,
                    );
                }
            }
            return 0;
        }
    });

    // ═══════════════════════════════════════
    //  TIER PRICING (WHOLESALE / GROSIR)
    // ═══════════════════════════════════════
    const isPromoActive = $derived(
        matchingVariant
            ? matchingVariant.is_promo || isPromoRuleSatisfied
            : product.is_promo || isPromoRuleSatisfied,
    );

    const shouldKeepTierPricesDuringPromo = $derived(
        matchingVariant
            ? matchingVariant.keep_tier_prices
            : product.keep_tier_prices,
    );

    const showWholesalePrices = $derived(
        !isPromoActive || shouldKeepTierPricesDuringPromo,
    );

    const hasWholesalePrices = $derived(
        product.tier_prices?.length > 0 ||
            product.tierPrices?.length > 0 ||
            (matchingVariant &&
                (matchingVariant.tier_prices?.length > 0 ||
                    matchingVariant.tierPrices?.length > 0)),
    );

    const activeTierPrices = $derived.by(() => {
        if (!showWholesalePrices) {
            return [];
        }
        if (hasVariations) {
            if (fullySelected && matchingVariant) {
                const variantTiers =
                    matchingVariant.tier_prices || matchingVariant.tierPrices;
                if (variantTiers && variantTiers.length > 0) {
                    return variantTiers;
                }
            }
            return [];
        }
        return product.tier_prices || product.tierPrices || [];
    });

    const activeUnitPrice = $derived.by(() => {
        const base = currentPrice;
        if (!activeTierPrices || activeTierPrices.length === 0) {
            return base;
        }
        // Sort descending by min_qty so we match the highest threshold first
        const sortedTiers = [...activeTierPrices].sort(
            (a, b) => Number(b.min_qty) - Number(a.min_qty),
        );
        const matchingTier = sortedTiers.find(
            (tier) => qty >= Number(tier.min_qty),
        );
        return matchingTier ? Number(matchingTier.price) : base;
    });

    const totalPrice = $derived(activeUnitPrice * qty);

    const activeTier = $derived.by(() => {
        if (!activeTierPrices || activeTierPrices.length === 0) {
            return null;
        }
        const sortedTiers = [...activeTierPrices].sort(
            (a, b) => Number(b.min_qty) - Number(a.min_qty),
        );
        return sortedTiers.find((tier) => qty >= Number(tier.min_qty)) || null;
    });

    function fmt(price: any): string {
        const n = Number(price);
        if (!n) return 'Hubungi Kami';
        return 'Rp ' + n.toLocaleString('id-ID');
    }

    // Dynamic requirements text (e.g. "Beli 1 pcs Laptop ASUS untuk dapat hadiah...")
    function getPromoRequirementsText(promo: any): string {
        const bundle = promo.settings?.bundle;
        if (!bundle || !bundle.buy_items)
            return 'Beli produk bundling untuk dapat hadiah!';

        const itemsText = bundle.buy_items.map((buyItem: any) => {
            return `${buyItem.qty} pcs ${buyItem.product_name || 'produk syarat'}`;
        });

        if (itemsText.length === 1) {
            return `Beli ${itemsText[0]} untuk dapat hadiah bonus gratis!`;
        } else if (itemsText.length === 2) {
            return `Beli ${itemsText[0]} dan ${itemsText[1]} untuk dapat hadiah bonus gratis!`;
        } else {
            const last = itemsText.pop();
            return `Beli ${itemsText.join(', ')}, dan ${last} untuk dapat hadiah bonus gratis!`;
        }
    }

    // ═══════════════════════════════════════
    //  FLASH SALE COUNTDOWN
    // ═══════════════════════════════════════
    const isFlashSale = $derived(
        matchingVariant
            ? matchingVariant.is_promo &&
                  matchingVariant.promo_type === 'flash_sale'
            : product.is_promo && product.promo_type === 'flash_sale',
    );
    const flashSaleEndTime = $derived(
        matchingVariant?.promo_end_time ?? product.promo_end_time ?? null,
    );

    type CountdownDisplay = { h: string; m: string; s: string };
    let flashSaleCountdown = $state<CountdownDisplay>({
        h: '00',
        m: '00',
        s: '00',
    });
    let flashSaleInterval: ReturnType<typeof setInterval> | null = null;

    function updateFlashSaleCountdown() {
        const endStr = flashSaleEndTime;
        if (!endStr) return;
        const diff = Math.max(0, new Date(endStr).getTime() - Date.now());
        flashSaleCountdown = {
            h: String(Math.floor(diff / 3600000)).padStart(2, '0'),
            m: String(Math.floor((diff % 3600000) / 60000)).padStart(2, '0'),
            s: String(Math.floor((diff % 60000) / 1000)).padStart(2, '0'),
        };
    }

    $effect(() => {
        if (isFlashSale && flashSaleEndTime) {
            updateFlashSaleCountdown();
            flashSaleInterval = setInterval(updateFlashSaleCountdown, 1000);
        } else {
            if (flashSaleInterval) clearInterval(flashSaleInterval);
            flashSaleInterval = null;
        }
        return () => {
            if (flashSaleInterval) clearInterval(flashSaleInterval);
        };
    });

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

    // True when the promo stock limit has been fully consumed (remaining_promo_stock === 0)
    const isPromoStockExhausted = $derived(
        activePromoRule !== null &&
            activePromoRule !== undefined &&
            activePromoRule.remaining_promo_stock !== null &&
            activePromoRule.remaining_promo_stock !== undefined &&
            Number(activePromoRule.remaining_promo_stock) === 0,
    );

    // True when the product cannot be purchased (no physical stock OR promo quota fully gone)
    const isEffectivelyOutOfStock = $derived(
        !isInStock || isPromoStockExhausted,
    );

    // When promo stock is partially exhausted but product has regular stock,
    // show how many units can still get promo price
    const promoStockRemaining = $derived(
        activePromoRule?.remaining_promo_stock !== null &&
            activePromoRule?.remaining_promo_stock !== undefined
            ? Number(activePromoRule.remaining_promo_stock)
            : null,
    );

    // Whether qty exceeds promo stock quota (triggers split pricing on backend)
    const isSplitPricing = $derived(
        promoStockRemaining !== null &&
            promoStockRemaining > 0 &&
            qty > promoStockRemaining,
    );

    // ═══════════════════════════════════════
    //  QUANTITY
    // ═══════════════════════════════════════
    let qty = $state(1);
    let drawerOpen = $state(false);
    let drawerAction = $state<'buy' | 'cart'>('buy');

    // ═══════════════════════════════════════
    //  CHAT
    // ═══════════════════════════════════════
    let chatOpen = $state(false);
    let chatInput = $state('');
    let attachMenuOpen = $state(false);
    let productAttachOpen = $state(false);
    let productSearchQuery = $state('');
    let attachedProduct = $state<any>(null);

    let currentChatId = $state<number | null>(null);
    let chatMessages = $state<any[]>([]);
    let chatInterval: any = null;
    let attachedImage = $state<File | null>(null);
    let attachedImageUrl = $state<string | null>(null);
    let chatPreviewUrl = $state<string | null>(null);

    $effect(() => {
        if (chatOpen) {
            initializeChat();
        } else {
            stopChatPolling();
        }
        return () => {
            stopChatPolling();
        };
    });

    async function initializeChat() {
        if (!user) {
            chatOpen = false;
            window.dispatchEvent(new CustomEvent('open-login-modal'));
            return;
        }

        try {
            const response = await fetch('/chats', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as HTMLMetaElement
                        )?.content || '',
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    subject: product.name,
                    product_id: product.id,
                }),
            });

            if (response.ok) {
                const data = await response.json();
                currentChatId = data.id;

                attachedProduct = {
                    id: product.id,
                    name: product.name,
                    image:
                        product.image ||
                        (product.images?.[0]?.url ?? product.images?.[0]?.path),
                    price: product.price ?? product.product_price?.price ?? 0,
                };

                await fetchMessages();
                startChatPolling();
            }
        } catch (err) {
            console.error('Error initializing chat:', err);
        }
    }

    async function fetchMessages() {
        if (!currentChatId) return;
        try {
            const response = await fetch(`/chats/${currentChatId}/messages`, {
                headers: { Accept: 'application/json' },
            });
            if (response.ok) {
                const data = await response.json();
                chatMessages = Array.isArray(data) ? data : data.messages || [];
                setTimeout(scrollToBottom, 50);
            }
        } catch (err) {
            console.error('Error fetching messages:', err);
        }
    }

    function scrollToBottom() {
        const bodyEl = document.querySelector('.chat-body-container');
        if (bodyEl) {
            bodyEl.scrollTop = bodyEl.scrollHeight;
        }
    }

    function startChatPolling() {
        stopChatPolling();
        chatInterval = setInterval(async () => {
            if (!currentChatId) return;
            const lastMessageId =
                chatMessages.length > 0
                    ? chatMessages[chatMessages.length - 1].id
                    : 0;
            try {
                const response = await fetch(
                    `/chats/${currentChatId}/messages?after_id=${lastMessageId}`,
                    {
                        headers: { Accept: 'application/json' },
                    },
                );
                if (response.ok) {
                    const data = await response.json();
                    const newMsgs = Array.isArray(data)
                        ? data
                        : data.messages || [];
                    if (newMsgs.length > 0) {
                        chatMessages = [...chatMessages, ...newMsgs];
                        setTimeout(scrollToBottom, 50);
                    }
                }
            } catch (err) {
                console.error('Error polling chat messages:', err);
            }
        }, 5000);
    }

    function stopChatPolling() {
        if (chatInterval) {
            clearInterval(chatInterval);
            chatInterval = null;
        }
    }

    async function sendChat() {
        const text = chatInput.trim();
        if (!text && !attachedProduct && !attachedImage) return;
        if (!currentChatId) return;

        try {
            const formData = new FormData();
            if (text) formData.append('body', text);
            if (attachedProduct) {
                formData.append('attachment_type', 'product');
                formData.append(
                    'attachment_data',
                    JSON.stringify(attachedProduct),
                );
            }
            if (attachedImage) {
                formData.append('image', attachedImage);
            }

            chatInput = '';
            attachedProduct = null;
            attachedImage = null;
            attachedImageUrl = null;
            attachMenuOpen = false;

            const response = await fetch(`/chats/${currentChatId}/messages`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as HTMLMetaElement
                        )?.content || '',
                    Accept: 'application/json',
                },
                body: formData,
            });

            if (response.ok) {
                const msg = await response.json();
                if (Array.isArray(chatMessages)) {
                    if (!chatMessages.some((m) => m.id === msg.id)) {
                        chatMessages = [...chatMessages, msg];
                        setTimeout(scrollToBottom, 50);
                    }
                } else {
                    chatMessages = [msg];
                    setTimeout(scrollToBottom, 50);
                }
            }
        } catch (err) {
            console.error('Error sending message:', err);
        }
    }

    function triggerImageUpload() {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';
        input.onchange = (e: any) => {
            const file = e.target.files?.[0];
            if (file) {
                attachedImage = file;
                attachedImageUrl = URL.createObjectURL(file);
                attachMenuOpen = false;
            }
        };
        input.click();
    }

    function quickReply(text: string) {
        chatInput = text;
        sendChat();
    }

    const quickReplies = [
        'Hai, barang ini masih ada?',
        'Bisa dikirim ke luar kota?',
        'Terima kasih!',
    ];

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
    function handleQtyInput(e: Event) {
        const target = e.target as HTMLInputElement;
        let val = parseInt(target.value);
        if (isNaN(val)) {
            return;
        }
        if (!currentIsUnlimited && val > currentStock) {
            qty = currentStock;
        } else {
            qty = val;
        }
    }
    function handleQtyBlur(e: Event) {
        const target = e.target as HTMLInputElement;
        let val = parseInt(target.value);
        if (isNaN(val) || val < currentMinPurchase) {
            qty = currentMinPurchase;
        } else if (!currentIsUnlimited && val > currentStock) {
            qty = currentStock;
        } else {
            qty = val;
        }
    }

    // ═══════════════════════════════════════
    //  WHATSAPP CTA
    // ═══════════════════════════════════════
    const waNumber = $derived(
        (page.props as any).settings?.store_whatsapp ?? '',
    );

    function openWhatsapp(action: 'chat' | 'buy' | 'cart' = 'buy') {
        const parts: string[] = [];
        (product.variations ?? []).forEach((v: any) => {
            const label = getSelectedLabel(v);
            if (label) parts.push(label);
        });
        const varNote = parts.length ? ` (${parts.join(' / ')})` : '';

        let text = '';
        if (action === 'chat') {
            text = `Halo, saya ingin bertanya mengenai produk *${product.name}*${varNote}. Apakah ada informasi lebih detail?`;
        } else if (action === 'cart') {
            text = `Halo, saya tertarik dengan produk *${product.name}*${varNote} sebanyak ${qty} pcs. Tolong masukkan ke dalam pesanan/keranjang saya.`;
        } else {
            if (activeTier) {
                text = `Halo, saya ingin memesan *${product.name}*${varNote} sebanyak ${qty} pcs dengan harga grosir ${fmt(activeUnitPrice)} (Total ${fmt(totalPrice)}). Apakah masih tersedia?`;
            } else {
                text = `Halo, saya ingin memesan *${product.name}*${varNote} sebanyak ${qty} pcs dengan harga ${fmt(activeUnitPrice)} (Total ${fmt(totalPrice)}). Apakah masih tersedia?`;
            }
        }

        const msg = encodeURIComponent(text);
        const num = waNumber.replace(/\D/g, '');
        window.open(`https://wa.me/${num}?text=${msg}`, '_blank');
    }

    const user = $derived((page.props as any).auth?.user);

    function addToCart() {
        if (!user) {
            window.dispatchEvent(new CustomEvent('open-login-modal'));
            return;
        }

        if (hasVariations && !fullySelected) {
            showToast('Pilih variasi terlebih dahulu', 'error', 'top');
            return;
        }

        if (!isInStock) {
            showToast('Maaf, stok produk ini sudah habis.', 'error', 'top');
            return;
        }

        router.post(
            '/cart',
            {
                product_id: product.id,
                product_variant_id: matchingVariant ? matchingVariant.id : null,
                quantity: qty,
            },
            {
                // onSuccess: () => {
                //     showToast(
                //         'Produk berhasil ditambahkan ke keranjang!',
                //         'success',
                //         'top',
                //     );
                // },
                onError: (errors: any) => {
                    const errMsg =
                        errors?.error ??
                        errors?.message ??
                        'Gagal menambahkan produk ke keranjang';
                    showToast(errMsg, 'error', 'top');
                },
            },
        );
    }

    function buyNow() {
        if (!user) {
            window.dispatchEvent(new CustomEvent('open-login-modal'));
            return;
        }

        if (hasVariations && !fullySelected) {
            showToast('Pilih variasi terlebih dahulu', 'error', 'top');
            return;
        }

        if (!isInStock) {
            showToast('Maaf, stok produk ini sudah habis.', 'error', 'top');
            return;
        }

        router.post(
            '/cart',
            {
                product_id: product.id,
                product_variant_id: matchingVariant ? matchingVariant.id : null,
                quantity: qty,
                buy_now: true,
            },
            {
                onError: (errors: any) => {
                    const errMsg =
                        errors?.error ??
                        errors?.message ??
                        'Gagal memproses pembelian';
                    showToast(errMsg, 'error', 'top');
                },
            },
        );
    }

    const cartButtonStyle = $derived(
        ((page.props as any).settings as any)?.storefront_cart_button_style ||
            'button',
    );

    function handleDirectAddToCart(prod: any, e: MouseEvent) {
        e.preventDefault();
        e.stopPropagation();

        if (!user) {
            window.dispatchEvent(new CustomEvent('open-login-modal'));
            return;
        }

        if (prod.variants && prod.variants.length > 0) {
            selectedVariantProduct = prod;
            showVariantModal = true;
            return;
        }

        router.post(
            '/cart',
            {
                product_id: prod.id,
                product_variant_id: null,
                quantity: 1,
            },
            {
                preserveScroll: true,
                // onSuccess: () => {
                //     showToast(
                //         'Produk berhasil ditambahkan ke keranjang!',
                //         'success',
                //         'top',
                //     );
                // },
                onError: () => {
                    showToast(
                        'Gagal menambahkan produk ke keranjang.',
                        'error',
                        'top',
                    );
                },
            },
        );
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
    <title>{product.name} — {storeName}</title>
    <meta
        name="description"
        content={product.summary ??
            `Beli ${product.name} di ${storeName}. Harga terbaik, pengiriman cepat.`}
    />
    <script
        type="module"
        src="https://ajax.googleapis.com/ajax/libs/model-viewer/4.0.0/model-viewer.min.js"
    ></script>
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
                onclick={goBack}
                class="w-9 h-9 flex items-center justify-center text-slate-700 hover:bg-slate-100 rounded-full transition active:scale-95 shrink-0"
                aria-label="Kembali"
            >
                <i class="ti ti-arrow-left text-xl"></i>
            </button>

            <!-- Search Bar -->
            <div class="flex-grow">
                <form onsubmit={handleSearch} class="relative">
                    <input
                        type="text"
                        bind:value={searchQuery}
                        placeholder="Cari produk..."
                        class="w-full pl-3.5 pr-10 py-1.5 text-xs bg-slate-100 rounded-xl border border-transparent focus:outline-none focus:bg-white focus:border-slate-300 transition"
                    />
                    <button
                        type="submit"
                        aria-label="Search"
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400"
                    >
                        <i class="ti ti-search text-base"></i>
                    </button>
                </form>
            </div>

            <!-- Right Icons: Share, Cart, Menu -->
            <div class="flex items-center gap-1.5 shrink-0">
                <!-- Share Button -->
                <!-- <button
                    onclick={shareProduct}
                    class="w-8 h-8 flex items-center justify-center text-slate-600 hover:bg-slate-100 rounded-full transition"
                    aria-label="Bagikan"
                >
                    <i class="ti ti-share text-lg"></i>
                </button> -->

                <!-- Cart Button -->
                <button
                    onclick={() => {
                        if (user) {
                            router.visit('/cart');
                        } else {
                            window.dispatchEvent(
                                new CustomEvent('open-login-modal'),
                            );
                        }
                    }}
                    class="relative w-8 h-8 flex items-center justify-center text-slate-600 hover:bg-slate-100 rounded-full transition cursor-pointer"
                    aria-label="Keranjang"
                >
                    <i class="ti ti-shopping-cart text-lg"></i>
                    {#if cartCount > 0}
                        <span
                            class="absolute -top-0.5 -right-0.5 w-4 h-4 rounded-full text-[9px] font-black flex items-center justify-center text-white"
                            style="background-color: {secondary};"
                        >
                            {cartCount}
                        </span>
                    {/if}
                </button>

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
    <div class="bg-white pt-9 md:pt-0">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-5 sm:py-8">
            <div
                class="grid grid-cols-1 md:grid-cols-[380px_1fr] lg:grid-cols-[420px_1fr] gap-x-5 gap-y-2 lg:gap-10 items-start"
            >
                <!-- ══ LEFT: GALLERY ══════════════════════════════ -->
                <div class="flex flex-col gap-3">
                    <!-- Mobile Gallery (Slider — combined product + variant images) -->
                    <div
                        class="md:hidden relative aspect-square bg-slate-50 overflow-hidden -mx-4"
                        style="width: calc(100% + 2rem)"
                    >
                        {#if combinedSlides.length > 0}
                            <div
                                bind:this={sliderContainer}
                                onscroll={handleSliderScroll}
                                class="w-full h-full flex overflow-x-auto snap-x snap-mandatory no-scrollbar"
                                style="scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch;"
                            >
                                {#each combinedSlides as slide, i}
                                    <div
                                        class="w-full h-full shrink-0 snap-center relative aspect-square"
                                    >
                                        {#if slide.type === 'video'}
                                            <div
                                                class="w-full h-full bg-black z-20 relative"
                                            >
                                                <PremiumVideoPlayer
                                                    src={formatImagePath(
                                                        slide.path,
                                                    )}
                                                    themeColor={secondary}
                                                />
                                            </div>
                                        {:else if slide.type === '3d'}
                                            <!-- Stop touch propagation to scroll-snap container so model-viewer
                                                 can receive gestures for orbit/rotate/zoom -->
                                            <div
                                                role="presentation"
                                                class="w-full h-full bg-slate-50 relative flex items-center justify-center z-20"
                                                ontouchstart={(e) =>
                                                    e.stopPropagation()}
                                                ontouchmove={(e) =>
                                                    e.stopPropagation()}
                                            >
                                                <model-viewer
                                                    src={formatImagePath(
                                                        slide.path,
                                                    )}
                                                    ios-src={slide.usdz_path
                                                        ? formatImagePath(
                                                              slide.usdz_path,
                                                          )
                                                        : ''}
                                                    ar
                                                    ar-modes="webxr scene-viewer quick-look"
                                                    camera-controls
                                                    auto-rotate
                                                    interaction-prompt="auto"
                                                    class="w-full h-full relative z-20 bg-slate-50"
                                                    style="--poster-color: transparent; background-color: #f8fafc; touch-action: none;"
                                                >
                                                    <!-- Two buttons side by side — raised above dot indicators and model-viewer AR icon -->
                                                    <div
                                                        class="absolute bottom-16 left-2 right-2 flex justify-between items-center gap-1.5 z-30"
                                                    >
                                                        <button
                                                            type="button"
                                                            onclick={() =>
                                                                openCameraModal(
                                                                    slide.path,
                                                                    slide.usdz_path ??
                                                                        '',
                                                                )}
                                                            class="bg-slate-900/80 hover:bg-slate-900 active:scale-95 backdrop-blur-sm text-white px-3 py-1.5 rounded-full shadow-lg font-bold text-[10px] transition flex items-center gap-1 border border-white/10"
                                                        >
                                                            <i
                                                                class="ti ti-camera text-[11px]"
                                                            ></i>
                                                            Buka Kamera AR
                                                        </button>
                                                        <button
                                                            slot="ar-button"
                                                            class="bg-orange-500 hover:bg-orange-600 active:scale-95 text-white px-3 py-1.5 rounded-full shadow-lg font-bold text-[10px] transition flex items-center gap-1 border border-orange-400/20"
                                                        >
                                                            <i
                                                                class="ti ti-augmented-reality text-[11px]"
                                                            ></i>
                                                            Lihat di Ruangan
                                                        </button>
                                                    </div>
                                                </model-viewer>
                                            </div>
                                        {:else}
                                            <!-- Slide image -->
                                            <img
                                                src={slide.src}
                                                alt="{product.name} {i + 1}"
                                                class="w-full h-full object-cover transition-opacity duration-300 {slide.type ===
                                                    'variant' &&
                                                !slide.available
                                                    ? 'opacity-40'
                                                    : ''}"
                                                onerror={(e) => {
                                                    (
                                                        e.currentTarget as HTMLImageElement
                                                    ).src =
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
                                        {/if}
                                    </div>
                                {/each}
                            </div>

                            {#if combinedSlides.length > 1}
                                <!-- Prev button -->
                                <button
                                    onclick={sliderPrev}
                                    class="absolute left-2 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-black/40 backdrop-blur-sm text-white flex items-center justify-center shadow-md active:scale-95 transition z-30"
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
                                    class="absolute right-2 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-black/40 backdrop-blur-sm text-white flex items-center justify-center shadow-md active:scale-95 transition z-30"
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
                                    class="absolute bottom-3 left-1/2 -translate-x-1/2 flex gap-1.5 z-30"
                                >
                                    {#each combinedSlides as s, i}
                                        <button
                                            onclick={() => goToSlide(i)}
                                            class="rounded-full transition-all duration-300 {i ===
                                            activeSlideIdx
                                                ? 'w-5 h-1.5 bg-white'
                                                : 'w-1.5 h-1.5 bg-white/50'}"
                                            aria-label="Slide {i + 1}"
                                        ></button>
                                    {/each}
                                </div>

                                <!-- Fractional counter — top-right to avoid overlap with AR/camera buttons at bottom -->
                                <div
                                    class="absolute top-3 right-3 bg-black/50 text-white text-[11px] font-bold px-2.5 py-0.5 rounded-full backdrop-blur-sm select-none z-30"
                                >
                                    {activeSlideIdx + 1}/{combinedSlides.length}
                                </div>
                            {/if}
                        {:else}
                            <img
                                src="/noimage/image.png"
                                alt="Tidak ada gambar"
                                class="w-full h-full object-cover"
                            />
                        {/if}
                    </div>

                    <!-- Mobile Flash Sale Banner (edge-to-edge) -->
                    {#if isFlashSale}
                        <div
                            class="md:hidden -mx-4 -mt-3 bg-white border-y border-slate-100"
                        >
                            <!-- Header: FLASH SALE label + countdown -->
                            <div
                                class="flex items-center justify-between px-4 py-2 flex-wrap gap-2 border-b border-slate-50"
                                style="background-color: {primary};"
                            >
                                <div
                                    class="flex items-center gap-1 text-white font-black text-xs tracking-wide uppercase"
                                >
                                    <span
                                        class="text-yellow-300 text-sm drop-shadow-sm"
                                        >⚡</span
                                    >
                                    Flash Sale
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <i
                                        class="ti ti-clock text-white/90 text-[10px]"
                                    ></i>
                                    <span
                                        class="text-[9px] font-bold text-white uppercase tracking-wider"
                                        >Berakhir Dalam</span
                                    >
                                    <div class="flex items-center gap-0.5">
                                        <span
                                            class="bg-white font-mono font-black text-[10px] leading-none px-1.5 py-1 rounded-sm shadow-sm tabular-nums"
                                            style="color: {primary};"
                                            >{flashSaleCountdown.h}</span
                                        >
                                        <span
                                            class="text-white/90 font-black text-[10px] mx-0.5"
                                            >:</span
                                        >
                                        <span
                                            class="bg-white font-mono font-black text-[10px] leading-none px-1.5 py-1 rounded-sm shadow-sm tabular-nums"
                                            style="color: {primary};"
                                            >{flashSaleCountdown.m}</span
                                        >
                                        <span
                                            class="text-white/90 font-black text-[10px] mx-0.5"
                                            >:</span
                                        >
                                        <span
                                            class="bg-white font-mono font-black text-[10px] leading-none px-1.5 py-1 rounded-sm shadow-sm tabular-nums"
                                            style="color: {primary};"
                                            >{flashSaleCountdown.s}</span
                                        >
                                    </div>
                                </div>
                            </div>
                            <!-- Price row inside banner -->
                            <div
                                class="px-4 py-2.5 flex items-baseline gap-2.5 flex-wrap"
                            >
                                <span
                                    class="text-2xl font-bold"
                                    style="color: {secondary};"
                                >
                                    {fmt(activeUnitPrice)}
                                </span>
                                {#if originalPrice && originalPrice > currentPrice}
                                    <span
                                        class="text-xs text-slate-400 line-through font-medium"
                                        >{fmt(originalPrice)}</span
                                    >
                                    <span
                                        class="text-[10px] font-black px-1.5 py-0.5 rounded-sm text-white shadow-sm"
                                        style="background-color: {secondary};"
                                        >-{discountPercentage}%</span
                                    >
                                {/if}
                            </div>
                        </div>
                    {:else}
                        <!-- Mobile Regular Price Banner (edge-to-edge) -->
                        <div
                            class="md:hidden -mx-4 -mt-3 bg-white border-y border-slate-100 px-4 py-3"
                        >
                            {#if currentPrice > 0}
                                <div
                                    class="flex items-baseline gap-2.5 flex-wrap"
                                >
                                    <span
                                        class="text-2xl font-bold"
                                        style="color: {secondary};"
                                    >
                                        {fmt(activeUnitPrice)}
                                    </span>
                                    {#if originalPrice && originalPrice > currentPrice}
                                        <span
                                            class="text-xs text-slate-400 line-through font-medium"
                                        >
                                            {fmt(originalPrice)}
                                        </span>
                                        <span
                                            class="text-[10px] font-black px-1.5 py-0.5 rounded-sm text-white shadow-sm"
                                            style="background-color: {secondary};"
                                        >
                                            -{discountPercentage}%
                                        </span>
                                    {/if}
                                </div>
                            {:else}
                                <span class="text-xl font-bold text-slate-700"
                                    >Hubungi Kami</span
                                >
                            {/if}
                        </div>
                    {/if}

                    <!-- Mobile Wholesale Promo Clash Alert -->
                    {#if isPromoActive && !shouldKeepTierPricesDuringPromo && hasWholesalePrices}
                        <div
                            class="md:hidden flex items-start gap-3 p-4 rounded-2xl border text-xs my-3 shadow-xs"
                            style="background-color: #fffaf0; border-color: #fbd38d;"
                        >
                            <div
                                class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center shrink-0"
                            >
                                <i
                                    class="ti ti-info-circle text-amber-600 text-base animate-pulse"
                                ></i>
                            </div>
                            <div class="flex-grow text-[11px] leading-relaxed">
                                <span
                                    class="font-bold text-amber-950 block text-xs"
                                    >Harga Grosir Dijeda Sementara</span
                                >
                                <p class="text-amber-800 mt-0.5">
                                    Produk ini sedang dalam masa promo aktif <span
                                        class="font-black text-amber-950 uppercase"
                                        >({isPromoActive
                                            ? (
                                                  matchingVariant?.promo_type ??
                                                  product.promo_type ??
                                                  'Promo'
                                              ).replace('_', ' ')
                                            : ''})</span
                                    >. Selama masa promo berlangsung, potongan
                                    harga grosir dinonaktifkan untuk memberikan
                                    harga terbaik.
                                </p>
                            </div>
                        </div>
                    {/if}

                    <!-- Promo Produk Tiered Info Banner (Mobile) -->
                    {#if activePromoRule && activePromoRule.min_qty > 1}
                        <div
                            class="md:hidden flex items-start gap-3 p-4 rounded-2xl border text-xs my-3 shadow-xs transition duration-200"
                            style="background-color: #f0f7ff; border-color: #bfdbfe;"
                        >
                            <div
                                class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center shrink-0 animate-pulse"
                            >
                                <i class="ti ti-gift text-blue-600 text-base"
                                ></i>
                            </div>
                            <div class="flex-grow text-[11px] leading-relaxed">
                                <span
                                    class="font-bold text-blue-950 block text-xs"
                                    >Promo Beli Banyak Lebih Hemat!</span
                                >
                                <p class="text-blue-800 mt-0.5">
                                    Beli minimal <strong class="text-blue-950"
                                        >{activePromoRule.min_qty} pcs</strong
                                    >
                                    untuk mendapatkan harga
                                    <strong class="text-blue-950">
                                        {#if activePromoRule.promo_price}
                                            {fmt(activePromoRule.promo_price)}
                                        {:else if activePromoRule.discount_type === 'percentage'}
                                            Diskon {Number(
                                                activePromoRule.discount_value,
                                            )}%
                                        {:else}
                                            Potongan {fmt(
                                                activePromoRule.discount_value,
                                            )}
                                        {/if}
                                    </strong>
                                    per produk!
                                    {#if activePromoRule.remaining_promo_stock !== null && activePromoRule.remaining_promo_stock !== undefined}
                                        <span
                                            class="block mt-1.5 font-black text-orange-600 bg-orange-50/80 px-2 py-0.5 rounded-md border border-orange-200/80 w-fit text-[9px] uppercase tracking-wide"
                                        >
                                            Sisa Stok Promo: {activePromoRule.remaining_promo_stock}
                                            pcs
                                        </span>
                                    {/if}
                                </p>
                            </div>
                        </div>
                    {/if}

                    <!-- Desktop Gallery (with Zoom and Thumbnails) -->
                    <div class="hidden md:flex flex-col gap-3">
                        <div
                            class="relative aspect-square rounded-2xl overflow-hidden bg-slate-50 border border-slate-100 group select-none {(variantOverride ||
                                (desktopSlides[activeDesktopSlideIdx] &&
                                    desktopSlides[activeDesktopSlideIdx]
                                        .type === 'image')) &&
                            displayImage &&
                            displayImage !== '/noimage/image.png' &&
                            !mainImageHasError
                                ? 'cursor-zoom-in'
                                : ''}"
                            onclick={() =>
                                (variantOverride ||
                                    (desktopSlides[activeDesktopSlideIdx] &&
                                        desktopSlides[activeDesktopSlideIdx]
                                            .type === 'image')) &&
                                displayImage &&
                                displayImage !== '/noimage/image.png' &&
                                !mainImageHasError &&
                                (lightboxOpen = true)}
                            role="button"
                            tabindex="0"
                            onkeydown={(e) =>
                                e.key === 'Enter' &&
                                (variantOverride ||
                                    (desktopSlides[activeDesktopSlideIdx] &&
                                        desktopSlides[activeDesktopSlideIdx]
                                            .type === 'image')) &&
                                displayImage &&
                                displayImage !== '/noimage/image.png' &&
                                !mainImageHasError &&
                                (lightboxOpen = true)}
                        >
                            {#if variantOverride}
                                <img
                                    src={variantOverride}
                                    alt={product.name}
                                    class="w-full h-full object-cover transition duration-500 group-hover:scale-[1.03]"
                                />
                            {:else if desktopSlides[activeDesktopSlideIdx] && desktopSlides[activeDesktopSlideIdx].type === 'video'}
                                <div
                                    class="w-full h-full bg-black z-20 relative"
                                >
                                    <PremiumVideoPlayer
                                        src={formatImagePath(
                                            desktopSlides[activeDesktopSlideIdx]
                                                .path,
                                        )}
                                        themeColor={secondary}
                                    />
                                </div>
                            {:else if desktopSlides[activeDesktopSlideIdx] && desktopSlides[activeDesktopSlideIdx].type === '3d'}
                                <div
                                    class="w-full h-full bg-slate-50 relative flex items-center justify-center z-20"
                                >
                                    <model-viewer
                                        src={formatImagePath(
                                            desktopSlides[activeDesktopSlideIdx]
                                                .path,
                                        )}
                                        ios-src={desktopSlides[
                                            activeDesktopSlideIdx
                                        ].usdz_path
                                            ? formatImagePath(
                                                  desktopSlides[
                                                      activeDesktopSlideIdx
                                                  ].usdz_path,
                                              )
                                            : ''}
                                        ar
                                        ar-modes="webxr scene-viewer quick-look"
                                        camera-controls
                                        auto-rotate
                                        interaction-prompt="auto"
                                        class="w-full h-full relative z-20 bg-slate-50"
                                        style="--poster-color: transparent; background-color: #f8fafc; touch-action: none;"
                                    >
                                        <!-- Two buttons side by side at bottom -->
                                        <div
                                            class="absolute bottom-4 left-4 right-4 flex justify-between items-center gap-2 z-30"
                                        >
                                            <button
                                                type="button"
                                                onclick={() =>
                                                    openCameraModal(
                                                        desktopSlides[
                                                            activeDesktopSlideIdx
                                                        ].path,
                                                        desktopSlides[
                                                            activeDesktopSlideIdx
                                                        ].usdz_path ?? '',
                                                    )}
                                                class="bg-slate-900/80 hover:bg-slate-900 active:scale-95 backdrop-blur-sm text-white px-4 py-2 rounded-full shadow-lg font-semibold text-xs transition flex items-center gap-1.5 border border-white/10"
                                            >
                                                <i
                                                    class="ti ti-camera text-base"
                                                ></i>
                                                Buka Kamera AR
                                            </button>
                                            <button
                                                slot="ar-button"
                                                class="bg-orange-500 hover:bg-orange-600 active:scale-95 text-white px-4 py-2 rounded-full shadow-lg font-semibold text-xs transition flex items-center gap-1.5 border border-orange-400/20"
                                                onclick={(e) =>
                                                    e.stopPropagation()}
                                            >
                                                <i
                                                    class="ti ti-augmented-reality text-base"
                                                ></i>
                                                Lihat di Ruangan (AR)
                                            </button>
                                        </div>
                                    </model-viewer>
                                </div>
                            {:else}
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
                                        alt="Tidak ada gambar"
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
                            {/if}
                        </div>

                        <!-- Thumbnails row -->
                        {#if desktopSlides.length > 1}
                            <div
                                class="flex gap-2 overflow-x-auto pb-1 snap-x select-none"
                            >
                                {#each desktopSlides as slide, i}
                                    <button
                                        onclick={() => pickGallery(i)}
                                        class="w-[68px] h-[68px] sm:w-[76px] sm:h-[76px] rounded-xl overflow-hidden border-2 shrink-0 snap-start transition duration-200 relative
                                           {activeDesktopSlideIdx === i &&
                                        !variantOverride
                                            ? 'border-orange-400 shadow-md shadow-orange-100'
                                            : 'border-slate-200 hover:border-slate-400 opacity-80 hover:opacity-100'}"
                                    >
                                        {#if slide.type === 'image'}
                                            <img
                                                src={slide.src}
                                                alt="{product.name} {i + 1}"
                                                class="w-full h-full object-cover"
                                                onerror={(e) => {
                                                    e.currentTarget.src =
                                                        '/noimage/image.png';
                                                }}
                                            />
                                        {:else if slide.type === 'video'}
                                            <div
                                                class="w-full h-full bg-orange-50 flex flex-col items-center justify-center text-orange-600 border border-orange-100"
                                            >
                                                <i
                                                    class="ti ti-video text-xl animate-pulse"
                                                ></i>
                                                <span
                                                    class="text-[8px] font-black mt-0.5 tracking-wider uppercase leading-none"
                                                    >Video</span
                                                >
                                            </div>
                                        {:else if slide.type === '3d'}
                                            <div
                                                class="w-full h-full bg-blue-50 flex flex-col items-center justify-center text-blue-600 border border-blue-100"
                                            >
                                                <i
                                                    class="ti ti-3d-cube-sphere text-xl"
                                                ></i>
                                                <span
                                                    class="text-[8px] font-black mt-0.5 tracking-wider uppercase leading-none"
                                                    >3D AR</span
                                                >
                                            </div>
                                        {/if}
                                    </button>
                                {/each}
                            </div>
                        {/if}
                    </div>
                </div>

                <!-- ══ RIGHT: PRODUCT INFO ════════════════════════ -->
                <div class="flex flex-col gap-0 divide-y divide-slate-100">
                    <!-- Header: brand + name + rating/terjual -->
                    <div class="pb-4 flex flex-col">
                        {#if product.brands && product.brands.length > 0}
                            <div class="mb-1 flex flex-wrap gap-1">
                                {#each product.brands as brand}
                                    <span
                                        class="inline-flex items-center gap-1 text-[11px] font-bold px-2 py-0.5 rounded"
                                        style="background: {withOpacity(
                                            primary,
                                            0.1,
                                        )}; color: {primary};"
                                    >
                                        <i class="ti ti-star-filled text-[9px]"
                                        ></i>
                                        {brand.name}
                                    </span>
                                {/each}
                            </div>
                        {:else if product.brand}
                            <div class="mb-1">
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
                            class="text-lg sm:text-xl font-semibold text-slate-800 leading-tight"
                        >
                            {product.name}
                        </h1>
                        <!-- Rating / terjual row -->
                        <div
                            class="flex items-center gap-3 text-xs text-slate-400 flex-wrap mt-1.5"
                        >
                            {#if reviews.length > 0}
                                {@const avgRating =
                                    reviews.reduce(
                                        (s: number, r: any) =>
                                            s + Number(r.rating),
                                        0,
                                    ) / reviews.length}
                                <span
                                    class="flex items-center gap-1 border-r border-slate-200 pr-3"
                                >
                                    <i
                                        class="ti ti-star-filled text-amber-400 text-xs"
                                    ></i>
                                    <span class="font-bold text-slate-700"
                                        >{avgRating.toFixed(1)}</span
                                    >
                                    <span class="text-slate-400"
                                        >({reviews.length} ulasan)</span
                                    >
                                </span>
                            {:else}
                                <span
                                    class="text-slate-400 border-r border-slate-200 pr-3"
                                    >Belum Ada Penilaian</span
                                >
                            {/if}
                            <span class="border-r border-slate-200 pr-3"
                                >{product.sold_count != null &&
                                product.sold_count > 0
                                    ? (product.sold_count >= 1000
                                          ? (product.sold_count / 1000)
                                                .toFixed(1)
                                                .replace('.0', '') + 'rb'
                                          : product.sold_count) + ' Terjual'
                                    : '0 Terjual'}</span
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

                    <!-- Flash Sale Banner -->
                    {#if isFlashSale}
                        <div
                            class="rounded-2xl overflow-hidden mb-4 hidden md:block bg-white border border-slate-100 shadow-sm"
                        >
                            <!-- Header: FLASH SALE label + countdown -->
                            <div
                                class="flex items-center justify-between px-4 py-2.5 flex-wrap gap-2 border-b border-slate-50"
                                style="background-color: {primary};"
                            >
                                <div
                                    class="flex items-center gap-1.5 text-white font-black text-sm tracking-wide uppercase"
                                >
                                    <span
                                        class="text-yellow-300 text-base drop-shadow-sm"
                                        >⚡</span
                                    >
                                    Flash Sale
                                </div>
                                <div class="flex items-center gap-2">
                                    <i class="ti ti-clock text-white/90 text-xs"
                                    ></i>
                                    <span
                                        class="text-[10px] font-bold text-white uppercase tracking-wider"
                                        >Berakhir Dalam</span
                                    >
                                    <div class="flex items-center gap-1">
                                        <span
                                            class="bg-white font-mono font-black text-xs leading-none px-2 py-1 rounded shadow-sm tabular-nums"
                                            style="color: {primary};"
                                            >{flashSaleCountdown.h}</span
                                        >
                                        <span
                                            class="text-white/90 font-black text-xs"
                                            >:</span
                                        >
                                        <span
                                            class="bg-white font-mono font-black text-xs leading-none px-2 py-1 rounded shadow-sm tabular-nums"
                                            style="color: {primary};"
                                            >{flashSaleCountdown.m}</span
                                        >
                                        <span
                                            class="text-white/90 font-black text-xs"
                                            >:</span
                                        >
                                        <span
                                            class="bg-white font-mono font-black text-xs leading-none px-2 py-1 rounded shadow-sm tabular-nums"
                                            style="color: {primary};"
                                            >{flashSaleCountdown.s}</span
                                        >
                                    </div>
                                </div>
                            </div>
                            <!-- Price row inside banner -->
                            <div
                                class="px-4 py-3 flex items-baseline gap-3 flex-wrap"
                            >
                                <span
                                    class="text-2xl sm:text-3xl font-bold"
                                    style="color: {secondary};"
                                >
                                    {fmt(activeUnitPrice)}
                                </span>
                                {#if originalPrice && originalPrice > currentPrice}
                                    <span
                                        class="text-sm text-slate-400 line-through font-medium"
                                        >{fmt(originalPrice)}</span
                                    >
                                    <span
                                        class="text-xs font-black px-2 py-0.5 rounded-md text-white shadow-sm"
                                        style="background-color: {secondary};"
                                        >-{discountPercentage}%</span
                                    >
                                {/if}
                            </div>
                        </div>
                    {:else}
                        <!-- Price (regular) -->
                        <div class="py-4 hidden md:block">
                            {#if currentPrice > 0}
                                <div
                                    class="flex items-baseline gap-3 flex-wrap"
                                >
                                    <span
                                        class="text-3xl sm:text-4xl font-bold"
                                        style="color: {secondary};"
                                    >
                                        {fmt(activeUnitPrice)}
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
                            {:else}
                                <span class="text-2xl font-bold text-slate-700"
                                    >Hubungi Kami</span
                                >
                            {/if}
                        </div>
                    {/if}

                    <!-- Desktop Wholesale Tier Prices Table -->
                    {#if activeTierPrices && activeTierPrices.length > 0}
                        <div class="hidden md:block w-full my-4">
                            <div
                                class="flex items-center gap-1.5 mb-2 text-xs font-bold text-slate-700"
                            >
                                <i
                                    class="ti ti-tags text-brand-blueRoyal text-sm"
                                ></i>
                                <span>Daftar Harga Grosir</span>
                            </div>
                            <div
                                class="w-full overflow-hidden border border-slate-100 rounded-xl bg-white shadow-xs"
                            >
                                <table
                                    class="w-full text-left border-collapse text-xs"
                                >
                                    <thead>
                                        <tr
                                            class="bg-slate-50/65 border-b border-slate-100 text-[10px] text-slate-400 font-bold uppercase tracking-wider"
                                        >
                                            <th
                                                class="py-2.5 px-4 font-semibold"
                                                >Min. Pembelian</th
                                            >
                                            <th
                                                class="py-2.5 px-4 font-semibold text-right"
                                                >Harga Satuan</th
                                            >
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-50">
                                        {#each activeTierPrices as tier}
                                            {@const isActive =
                                                (!hasVariations ||
                                                    fullySelected) &&
                                                activeTier &&
                                                Number(activeTier.min_qty) ===
                                                    Number(tier.min_qty)}
                                            <tr
                                                class="transition duration-150 {isActive
                                                    ? 'bg-brand-blueRoyal/[0.02]'
                                                    : ''}"
                                            >
                                                <td
                                                    class="py-3 px-4 font-semibold text-slate-700"
                                                >
                                                    {tier.min_qty}+ pcs
                                                </td>
                                                <td
                                                    class="py-3 px-4 font-black text-slate-800 text-right"
                                                >
                                                    {fmt(tier.price)}
                                                    <span
                                                        class="text-[10px] text-slate-400 font-normal"
                                                        >/pc</span
                                                    >
                                                </td>
                                            </tr>
                                        {/each}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    {/if}

                    <!-- Desktop Wholesale Promo Clash Alert -->
                    {#if isPromoActive && !shouldKeepTierPricesDuringPromo && hasWholesalePrices}
                        <div
                            class="hidden md:flex items-start gap-3 p-4 rounded-2xl border text-xs my-3 shadow-xs"
                            style="background-color: #fffaf0; border-color: #fbd38d;"
                        >
                            <div
                                class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center shrink-0"
                            >
                                <i
                                    class="ti ti-info-circle text-amber-600 text-base animate-pulse"
                                ></i>
                            </div>
                            <div class="flex-grow text-[11px] leading-relaxed">
                                <span
                                    class="font-bold text-amber-950 block text-xs"
                                    >Harga Grosir Dijeda Sementara</span
                                >
                                <p class="text-amber-800 mt-0.5">
                                    Produk ini sedang dalam masa promo aktif <span
                                        class="font-black text-amber-950 uppercase"
                                        >({isPromoActive
                                            ? (
                                                  matchingVariant?.promo_type ??
                                                  product.promo_type ??
                                                  'Promo'
                                              ).replace('_', ' ')
                                            : ''})</span
                                    >. Selama masa promo berlangsung, potongan
                                    harga grosir dinonaktifkan untuk memberikan
                                    harga terbaik.
                                </p>
                            </div>
                        </div>
                    {/if}

                    <!-- Promo Produk Tiered Info Banner (Desktop) -->
                    {#if activePromoRule && activePromoRule.min_qty > 1}
                        <div
                            class="hidden md:flex items-start gap-3 p-4 rounded-2xl border text-xs my-4 shadow-sm transition duration-200"
                            style="background-color: #f0f7ff; border-color: #bfdbfe;"
                        >
                            <div
                                class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center shrink-0 animate-bounce"
                            >
                                <i class="ti ti-gift text-blue-600 text-base"
                                ></i>
                            </div>
                            <div>
                                <h4
                                    class="font-outfit font-black text-xs text-blue-800 uppercase tracking-wider"
                                >
                                    Promo Spesial Beli Banyak Lebih Hemat!
                                </h4>
                                <p
                                    class="text-[11px] text-blue-650 font-bold mt-0.5 leading-relaxed"
                                >
                                    Beli minimal <strong
                                        >{activePromoRule.min_qty} pcs</strong
                                    >
                                    untuk mendapatkan potongan harga menjadi
                                    <strong
                                        class="text-blue-900 font-extrabold"
                                    >
                                        {#if activePromoRule.promo_price}
                                            {fmt(activePromoRule.promo_price)}
                                        {:else if activePromoRule.discount_type === 'percentage'}
                                            Diskon {Number(
                                                activePromoRule.discount_value,
                                            )}%
                                        {:else}
                                            Potongan {fmt(
                                                activePromoRule.discount_value,
                                            )}
                                        {/if}
                                    </strong> per produk!
                                </p>
                                {#if activePromoRule.remaining_promo_stock !== null && activePromoRule.remaining_promo_stock !== undefined}
                                    <div
                                        class="mt-2 text-[10px] text-orange-700 bg-orange-50 border border-orange-200 px-2.5 py-1 rounded-lg inline-block font-extrabold uppercase tracking-wide"
                                    >
                                        Sisa Stok Promo: {activePromoRule.remaining_promo_stock}
                                        pcs
                                    </div>
                                {/if}
                                {#if qty < activePromoRule.min_qty}
                                    <div
                                        class="mt-2 text-[10px] text-amber-700 bg-amber-50 border border-amber-200 px-2.5 py-1 rounded-lg inline-block font-semibold"
                                    >
                                        Tambah {activePromoRule.min_qty - qty} pcs
                                        lagi untuk mengaktifkan promo ini!
                                    </div>
                                {:else}
                                    <div
                                        class="mt-2 text-[10px] text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-1 rounded-lg inline-block font-bold"
                                    >
                                        Selamat! Promo Beli Banyak Aktif 🎉
                                    </div>
                                {/if}
                            </div>
                        </div>
                    {/if}

                    <!-- Promo Bundling & Gift Section (Interactive & Shopee-style) -->
                    {#if bundlingPromos && bundlingPromos.length > 0}
                        {#each bundlingPromos as promo}
                            {@const bundle = promo.settings.bundle}
                            <div
                                class="my-4 p-4 rounded-3xl bg-blue-50/40 border border-blue-100/70 space-y-3.5 shadow-sm text-xs animate-in fade-in slide-in-from-top-1 duration-200"
                            >
                                <!-- Title Header -->
                                <div
                                    class="flex items-center gap-2 font-bold text-slate-800"
                                >
                                    <span
                                        class="w-6 h-6 rounded-full bg-brand-blueRoyal text-white flex items-center justify-center text-xs shadow-md animate-pulse shrink-0"
                                    >
                                        <i class="ti ti-gift"></i>
                                    </span>
                                    <div>
                                        <span
                                            class="text-brand-blueRoyal font-extrabold uppercase tracking-wider text-[10px] block leading-none mb-0.5"
                                            >Promo Bundling</span
                                        >
                                        <span
                                            class="text-slate-850 font-black text-sm"
                                            >{promo.name}</span
                                        >
                                    </div>
                                </div>

                                <p
                                    class="text-slate-600 font-semibold leading-relaxed"
                                >
                                    <strong
                                        >{getPromoRequirementsText(
                                            promo,
                                        )}</strong
                                    >
                                </p>

                                <div
                                    class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 pt-1.5 border-t border-slate-100"
                                >
                                    <!-- Buy Checklist -->
                                    <div class="space-y-2">
                                        <span
                                            class="text-[9.5px] text-slate-450 font-black uppercase tracking-wider block"
                                            >Produk Yang Harus Dibeli:</span
                                        >
                                        <div class="space-y-2">
                                            {#each bundle.buy_items as buyItem}
                                                {@const isCurrent =
                                                    Number(
                                                        buyItem.product_id,
                                                    ) === Number(product.id)}
                                                <div
                                                    class="flex items-center gap-2.5 p-2 rounded-2xl bg-white border border-slate-100 shadow-3xs hover:border-slate-200 transition duration-150 relative"
                                                >
                                                    <img
                                                        src={buyItem.product_image
                                                            ? buyItem.product_image.startsWith(
                                                                  'http',
                                                              ) ||
                                                              buyItem.product_image.startsWith(
                                                                  '/',
                                                              )
                                                                ? buyItem.product_image
                                                                : '/' +
                                                                  buyItem.product_image
                                                            : '/noimage/image.png'}
                                                        alt={buyItem.product_name}
                                                        class="w-9 h-9 rounded-lg object-cover bg-slate-50 border border-slate-100 shrink-0"
                                                        onerror={(e) => {
                                                            e.currentTarget.src =
                                                                '/noimage/image.png';
                                                        }}
                                                    />
                                                    <div
                                                        class="min-w-0 flex-grow pr-1"
                                                    >
                                                        {#if isCurrent}
                                                            <p
                                                                class="font-black text-brand-blueRoyal truncate text-[10.5px] flex items-center gap-1"
                                                            >
                                                                {buyItem.product_name ||
                                                                    'Produk Ini'}
                                                                <span
                                                                    class="text-[8px] bg-brand-blueLight text-brand-blueRoyal px-1.5 py-0.25 rounded-md uppercase font-black tracking-wider"
                                                                    >Produk Ini</span
                                                                >
                                                            </p>
                                                        {:else}
                                                            <a
                                                                href={`/products/${buyItem.product_slug}`}
                                                                class="font-bold text-slate-800 hover:text-brand-blueRoyal hover:underline truncate text-[10.5px] block"
                                                            >
                                                                {buyItem.product_name ||
                                                                    'Produk Lain'}
                                                            </a>
                                                        {/if}
                                                        <p
                                                            class="text-[9px] font-bold text-slate-400"
                                                        >
                                                            Min. Beli: {buyItem.qty}
                                                            pcs
                                                        </p>
                                                    </div>
                                                </div>
                                            {/each}
                                        </div>
                                    </div>

                                    <!-- Get Gifts List (Image 3 Style) -->
                                    <div class="space-y-2">
                                        <span
                                            class="text-[9.5px] text-slate-450 font-black uppercase tracking-wider block"
                                            >Hadiah Bonus Yang Didapat:</span
                                        >
                                        <div class="space-y-2">
                                            {#each bundle.get_items as gift}
                                                <div
                                                    class="flex gap-2.5 p-2 rounded-2xl bg-white border border-slate-100 shadow-3xs relative"
                                                >
                                                    <img
                                                        src={gift.product_image
                                                            ? gift.product_image.startsWith(
                                                                  'http',
                                                              ) ||
                                                              gift.product_image.startsWith(
                                                                  '/',
                                                              )
                                                                ? gift.product_image
                                                                : '/' +
                                                                  gift.product_image
                                                            : '/noimage/image.png'}
                                                        alt={gift.product_name}
                                                        class="w-12 h-12 rounded-lg object-cover bg-slate-50 border border-slate-100 shrink-0"
                                                        onerror={(e) => {
                                                            e.currentTarget.src =
                                                                '/noimage/image.png';
                                                        }}
                                                    />
                                                    <div
                                                        class="min-w-0 flex-grow flex flex-col justify-center"
                                                    >
                                                        <div>
                                                            <span
                                                                class="bg-emerald-500 text-white text-[8px] font-black px-1.5 py-0.5 rounded-md uppercase tracking-wider mb-0.5 inline-block"
                                                            >
                                                                Hadiah
                                                            </span>
                                                        </div>
                                                        <p
                                                            class="font-extrabold text-slate-800 truncate text-[10.5px] leading-tight mb-0.5"
                                                        >
                                                            {gift.product_name ||
                                                                'Hadiah Bonus'}
                                                        </p>
                                                        <p
                                                            class="text-[9px] font-bold text-slate-400 mb-1 leading-none"
                                                        >
                                                            Isi {gift.qty} pcs
                                                        </p>
                                                        <div
                                                            class="flex items-center flex-wrap gap-1.5 text-[9px] leading-none"
                                                        >
                                                            {#if gift.discount_type === 'free'}
                                                                <span
                                                                    class="bg-red-50 text-red-500 text-[8px] font-black px-1 py-0.25 rounded border border-red-100 leading-none"
                                                                >
                                                                    100%
                                                                </span>
                                                                <span
                                                                    class="text-[9px] text-slate-400 line-through font-bold font-mono"
                                                                >
                                                                    {fmt(
                                                                        gift.product_price,
                                                                    )}
                                                                </span>
                                                                <span
                                                                    class="text-red-500 font-black tracking-wide uppercase"
                                                                >
                                                                    GRATIS
                                                                </span>
                                                            {:else if gift.discount_type === 'percentage'}
                                                                <span
                                                                    class="bg-orange-50 text-orange-600 text-[8px] font-black px-1 py-0.25 rounded border border-orange-100 leading-none"
                                                                >
                                                                    {Number(
                                                                        gift.discount_value,
                                                                    )}%
                                                                </span>
                                                                <span
                                                                    class="text-[9px] text-slate-400 line-through font-bold font-mono"
                                                                >
                                                                    {fmt(
                                                                        gift.product_price,
                                                                    )}
                                                                </span>
                                                                <span
                                                                    class="text-orange-600 font-black tracking-wide"
                                                                >
                                                                    {fmt(
                                                                        gift.product_price *
                                                                            (1 -
                                                                                gift.discount_value /
                                                                                    100),
                                                                    )}
                                                                </span>
                                                            {:else}
                                                                <span
                                                                    class="bg-orange-50 text-orange-600 text-[8px] font-black px-1 py-0.25 rounded border border-orange-100 leading-none"
                                                                >
                                                                    POTONGAN
                                                                </span>
                                                                <span
                                                                    class="text-[9px] text-slate-400 line-through font-bold font-mono"
                                                                >
                                                                    {fmt(
                                                                        gift.product_price,
                                                                    )}
                                                                </span>
                                                                <span
                                                                    class="text-orange-600 font-black tracking-wide font-mono"
                                                                >
                                                                    {fmt(
                                                                        gift.product_price -
                                                                            gift.discount_value,
                                                                    )}
                                                                </span>
                                                            {/if}
                                                        </div>
                                                    </div>
                                                </div>
                                            {/each}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        {/each}
                    {/if}

                    <!-- Pengiriman -->
                    <div class="py-2.5 flex items-start gap-4">
                        <span
                            class="text-xs text-slate-400 w-20 sm:w-24 shrink-0 font-bold uppercase tracking-wider pt-0.5"
                            >Dikirim dari</span
                        >
                        <div
                            class="flex items-start gap-1.5 text-xs text-slate-700"
                        >
                            <i
                                class="ti ti-map-pin text-green-500 text-sm mt-0.5"
                            ></i>
                            <div>
                                <span class="font-bold text-slate-800">
                                    {shippingInfo.store_city || 'Lokasi toko'}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Jaminan -->
                    <div class="py-2.5 flex items-start gap-4">
                        <span
                            class="text-xs text-slate-400 w-20 sm:w-24 shrink-0 font-bold uppercase tracking-wider pt-0.5"
                            >Jaminan</span
                        >
                        <div
                            class="flex flex-wrap gap-x-3.5 gap-y-1.5 text-xs text-slate-650"
                        >
                            <span class="flex items-center gap-1">
                                <i
                                    class="ti ti-rosette-discount-check text-blue-500 text-xs"
                                ></i> Produk Original
                            </span>
                            {#if shippingInfo.enable_cod}
                                <span class="flex items-center gap-1">
                                    <i
                                        class="ti ti-cash text-orange-400 text-xs"
                                    ></i> COD Tersedia
                                </span>
                            {/if}
                        </div>
                    </div>

                    <!-- Desktop-only Variations & Quantity (inline) -->
                    <div class="hidden md:block divide-y divide-slate-100">
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
                                            {@const available =
                                                isOptionAvailable(opt.id)}
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
                                    class="flex items-center border border-slate-300 rounded-lg overflow-hidden bg-white"
                                >
                                    <button
                                        aria-label="Decrease quantity"
                                        onclick={decQty}
                                        disabled={qty <= currentMinPurchase}
                                        class="w-9 h-9 flex items-center justify-center hover:bg-slate-100 transition text-slate-600 disabled:opacity-30"
                                    >
                                        <i class="ti ti-minus text-sm"></i>
                                    </button>
                                    <input
                                        type="number"
                                        value={qty}
                                        min={currentMinPurchase}
                                        max={currentIsUnlimited
                                            ? undefined
                                            : currentStock}
                                        class="w-12 text-center text-sm font-black text-slate-800 tabular-nums border-none outline-none focus:ring-0 focus:outline-none p-0 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                        oninput={handleQtyInput}
                                        onblur={handleQtyBlur}
                                    />
                                    <button
                                        aria-label="Increase quantity"
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
                                    <span
                                        class="text-xs font-bold text-green-600"
                                        >Stok: {currentStock}</span
                                    >
                                {:else}
                                    <span class="text-xs font-bold text-red-500"
                                        >HABIS</span
                                    >
                                {/if}

                                {#if currentMinPurchase > 1}
                                    <span
                                        class="text-[11px] text-slate-400 font-semibold"
                                        >Min. {currentMinPurchase} pcs</span
                                    >
                                {/if}
                            </div>
                        </div>

                        <!-- Desktop Live Calculation & Min Purchase Alert -->
                        {#if !hasVariations || fullySelected}
                            <div
                                class="py-2.5 flex items-center gap-5 border-t border-slate-100 mt-2"
                            >
                                <span
                                    class="text-sm text-slate-500 w-28 shrink-0 font-medium"
                                    >Subtotal</span
                                >
                                <div
                                    class="flex items-center gap-3 flex-wrap text-xs"
                                >
                                    <span
                                        class="font-bold text-slate-700 tabular-nums"
                                    >
                                        {fmt(totalPrice)}
                                    </span>
                                </div>
                            </div>
                        {/if}

                        {#if currentMinPurchase > 1}
                            <div
                                class="py-2.5 flex items-center gap-5 border-t border-slate-100"
                            >
                                <span
                                    class="text-sm text-slate-500 w-28 shrink-0 font-medium"
                                    >Informasi</span
                                >
                                <div class="flex-grow">
                                    <div
                                        class="p-3 bg-blue-50/40 rounded-xl border border-blue-100/60 flex items-start gap-2 text-[11px] text-blue-700 font-medium max-w-md"
                                    >
                                        <i
                                            class="ti ti-info-circle text-blue-500 text-sm mt-0.5 shrink-0"
                                        ></i>
                                        <span>
                                            Minimum pembelian {currentMinPurchase}
                                            pcs
                                        </span>
                                    </div>
                                </div>
                            </div>
                        {/if}
                    </div>

                    <!-- Split Pricing Info Banner (when qty > remaining promo stock) -->
                    {#if isSplitPricing && promoStockRemaining !== null && promoStockRemaining > 0}
                        <div
                            class="mt-2 p-3 bg-amber-50 rounded-xl border border-amber-200 flex items-start gap-2 text-[11px] text-amber-700 font-medium max-w-md"
                        >
                            <i
                                class="ti ti-info-circle text-amber-500 text-sm mt-0.5 shrink-0"
                            ></i>
                            <span>
                                <strong>{promoStockRemaining} pcs</strong>
                                dengan harga promo,
                                <strong>{qty - promoStockRemaining} pcs</strong>
                                dengan harga normal. Sisa kuota promo: {promoStockRemaining}
                                pcs.
                            </span>
                        </div>
                    {/if}

                    <!-- ── CTA BUTTONS ─────────────────────────── -->
                    <div class="pt-5 hidden md:flex flex-col sm:flex-row gap-3">
                        {#if isEffectivelyOutOfStock}
                            <!-- Out-of-stock state -->
                            <div
                                class="flex-grow py-3.5 rounded-xl font-bold text-sm text-center bg-slate-100 text-slate-400 border border-slate-200"
                            >
                                {isPromoStockExhausted
                                    ? 'Stok Promo Habis'
                                    : 'Stok Habis'}
                            </div>
                        {:else}
                            <button
                                onclick={() => {
                                    if (hasVariations && !fullySelected) {
                                        showToast(
                                            'Pilih variasi terlebih dahulu',
                                            'error',
                                            'top',
                                        );
                                        return;
                                    }
                                    addToCart();
                                }}
                                class="flex-grow flex items-center justify-center gap-2 py-3.5 rounded-xl font-bold text-sm text-white shadow-lg hover:shadow-xl hover:-translate-y-0.5 transition duration-200 cursor-pointer"
                                style="background: linear-gradient(135deg, {primary}, {withOpacity(
                                    primary,
                                    0.8,
                                )});"
                            >
                                <i class="ti ti-shopping-cart text-base"></i>
                                + Keranjang
                            </button>
                            <button
                                onclick={() => buyNow()}
                                class="flex-grow flex items-center justify-center gap-2 py-3.5 rounded-xl font-bold text-sm text-white shadow-lg hover:shadow-xl hover:-translate-y-0.5 transition duration-200 cursor-pointer"
                                style="background: linear-gradient(135deg, {secondary}, {withOpacity(
                                    secondary,
                                    0.8,
                                )});"
                            >
                                <i class="ti ti-credit-card text-base"></i>
                                Beli Sekarang
                            </button>
                        {/if}
                        <button
                            onclick={() => {
                                if (window.innerWidth < 768) {
                                    chatOpen = true;
                                    attachMenuOpen = false;
                                } else {
                                    if (user) {
                                        window.dispatchEvent(
                                            new CustomEvent(
                                                'open-desktop-chat',
                                                {
                                                    detail: {
                                                        productId: product.id,
                                                        productName:
                                                            product.name,
                                                    },
                                                },
                                            ),
                                        );
                                    } else {
                                        window.dispatchEvent(
                                            new CustomEvent('open-login-modal'),
                                        );
                                    }
                                }
                            }}
                            class="px-5 flex items-center justify-center gap-2 py-3.5 rounded-xl font-bold text-sm border-2 hover:shadow-md transition duration-200 cursor-pointer"
                            style="border-color: {primary}; color: {primary};"
                        >
                            <i class="ti ti-message-dots text-base"></i>
                            Chat
                        </button>
                    </div>

                    <!-- Product meta footer -->
                    {#if product.weight || product.brand || (product.brands && product.brands.length > 0) || product.category}
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
                            {#if product.brands && product.brands.length > 0}
                                <span
                                    >Merk: <b class="text-slate-600"
                                        >{product.brands
                                            .map((b) => b.name)
                                            .join(', ')}</b
                                    ></span
                                >
                            {:else if product.brand}
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
        class="max-w-6xl mx-auto px-0 sm:px-6 lg:px-8 py-6 flex flex-col gap-4 md:gap-6 w-full min-w-0 overflow-hidden"
    >
        <!-- Combined Spesifikasi & Deskripsi Section -->
        <div
            class="bg-white rounded-none sm:rounded-2xl border-y sm:border border-slate-100 shadow-sm p-5 sm:p-7 w-full min-w-0 overflow-hidden"
        >
            <!-- Spesifikasi Row Trigger (Merged inside Deskripsi) -->
            {#if parsedSpecifications.length > 0}
                <button
                    onclick={() => (showSpecsModal = true)}
                    class="w-full flex items-center justify-between pb-5 mb-5 border-b border-slate-100/70 hover:bg-slate-50/50 transition cursor-pointer text-left focus:outline-none select-none"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 shadow-sm transition-all"
                            style="background: {withOpacity(
                                primary,
                                0.08,
                            )}; color: {primary};"
                        >
                            <i class="ti ti-list text-lg"></i>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-slate-800">
                                Spesifikasi Produk
                            </p>
                            <p class="text-[11px] text-slate-400 mt-0.5">
                                {#if product.brand || (product.brands && product.brands.length > 0)}
                                    Merk: {product.brand ||
                                        product.brands[0].name} •
                                {/if}
                                Lihat detail spesifikasi lengkap
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-1.5 text-slate-400">
                        <span
                            class="text-xs font-semibold text-slate-500 hidden sm:inline"
                            >Lihat</span
                        >
                        <i class="ti ti-chevron-right text-lg"></i>
                    </div>
                </button>
            {/if}

            <!-- Deskripsi Section -->
            <div class="w-full min-w-0 overflow-x-auto">
                <h3
                    class="text-base font-bold text-slate-800 flex items-center gap-2 mb-4"
                >
                    <i class="ti ti-file-text text-lg" style="color: {primary};"
                    ></i>
                    Deskripsi Produk
                </h3>
                {#if product.description}
                    <div
                        class="prose prose-slate max-w-none text-sm leading-relaxed text-slate-700 min-w-0"
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

            <!-- ── Panduan Ukuran & Kalkulator Rekomendasi ── -->
            {#if product.size_chart && product.size_chart.enabled}
                <div class="mt-6 border-t border-slate-100 pt-6">
                    <h3
                        class="text-base font-bold text-slate-800 flex items-center gap-2 mb-4"
                    >
                        <i class="ti ti-shirt text-lg" style="color: {primary};"
                        ></i>
                        Kalkulator & Panduan Ukuran
                    </h3>

                    <!-- Calculator Card -->
                    <div
                        class="bg-slate-50/70 rounded-2xl border border-slate-100 p-4 sm:p-5 mb-5 shadow-sm/5"
                    >
                        <h4
                            class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-3 flex items-center gap-1.5"
                        >
                            <i class="ti ti-calculator text-base text-slate-400"
                            ></i> Cari Ukuran Rekomendasi Anda
                        </h4>
                        <div
                            class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-end mb-4"
                        >
                            <div>
                                <label
                                    for="height-input"
                                    class="block text-[10px] font-bold text-slate-400 mb-1.5 uppercase tracking-wider"
                                >
                                    Tinggi Badan (cm)
                                </label>
                                <input
                                    type="number"
                                    id="height-input"
                                    bind:value={userHeight}
                                    placeholder="Cth: 170"
                                    class="w-full bg-white border border-slate-200 rounded-xl px-3.5 py-2 text-sm focus:border-brand-blueRoyal focus:outline-none text-slate-750 font-bold"
                                />
                            </div>
                            <div>
                                <label
                                    for="weight-input"
                                    class="block text-[10px] font-bold text-slate-400 mb-1.5 uppercase tracking-wider"
                                >
                                    Berat Badan (kg)
                                </label>
                                <input
                                    type="number"
                                    id="weight-input"
                                    bind:value={userWeight}
                                    placeholder="Cth: 65"
                                    class="w-full bg-white border border-slate-200 rounded-xl px-3.5 py-2 text-sm focus:border-brand-blueRoyal focus:outline-none text-slate-750 font-bold"
                                />
                            </div>
                        </div>

                        <!-- Calculator Results Alert -->
                        {#if recommendedSize}
                            <div
                                class="p-4 rounded-xl flex items-center gap-3.5 transition-all duration-300 shadow-sm"
                                style="background: {withOpacity(
                                    primary,
                                    0.08,
                                )}; border: 1px solid {withOpacity(
                                    primary,
                                    0.15,
                                )};"
                            >
                                <div
                                    class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 shadow-sm"
                                    style="background: linear-gradient(135deg, {primary}, {secondary}); color: #fff;"
                                >
                                    <i class="ti ti-sparkles text-base"></i>
                                </div>
                                <div>
                                    <p
                                        class="text-[10px] text-slate-500 font-bold uppercase tracking-wider"
                                    >
                                        Ukuran Rekomendasi
                                    </p>
                                    <p
                                        class="text-sm font-black mt-0.5"
                                        style="color: {primary};"
                                    >
                                        Ukuran yang paling pas untuk Anda adalah <span
                                            class="bg-white px-2 py-0.5 rounded-md border border-slate-100 shadow-sm ml-1"
                                            style="color: {secondary};"
                                            >{recommendedSize}</span
                                        >
                                    </p>
                                </div>
                            </div>
                        {:else if userHeight || userWeight}
                            <div
                                class="p-3 bg-amber-50/50 border border-amber-100 rounded-xl flex items-center gap-2 text-xs text-amber-600 font-medium"
                            >
                                <i class="ti ti-info-circle text-base"></i>
                                Belum ada ukuran yang pas untuk tinggi/berat tersebut.
                                Silakan hubungi admin via chat.
                            </div>
                        {/if}
                    </div>

                    <!-- Size Guide Table -->
                    <div
                        class="w-full max-w-full overflow-x-auto border border-slate-100 rounded-2xl bg-white shadow-sm/5"
                    >
                        <table
                            class="w-full text-left text-xs border-collapse min-w-[500px] sm:min-w-0"
                        >
                            <thead>
                                <tr
                                    class="bg-slate-50 border-b border-slate-100"
                                >
                                    <th
                                        class="p-2 sm:p-3.5 font-bold text-slate-500 uppercase tracking-wider text-center"
                                        >{product.size_chart.headers[0]}</th
                                    >
                                    {#each product.size_chart.headers.slice(1) as header}
                                        <th
                                            class="p-2 sm:p-3.5 font-bold text-slate-500 uppercase tracking-wider text-center"
                                            >{header}</th
                                        >
                                    {/each}
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                {#each product.size_chart.rows as row}
                                    <tr
                                        class="hover:bg-slate-50/40 transition {recommendedSize ===
                                        row.size
                                            ? 'bg-slate-50/75 font-bold'
                                            : ''}"
                                    >
                                        <td
                                            class="p-2 sm:p-3.5 text-center font-bold text-slate-800 bg-slate-50/20 w-20"
                                            >{row.size}</td
                                        >
                                        {#each row.values as val}
                                            <td
                                                class="p-2 sm:p-3.5 text-center text-slate-600 font-semibold"
                                                >{val}</td
                                            >
                                        {/each}
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>

                    <!-- Scroll Hint for Mobile -->
                    <div
                        class="flex items-center justify-end gap-1 text-[10px] text-slate-400 mt-2 sm:hidden"
                    >
                        <i class="ti ti-arrows-horizontal text-xs"></i>
                        <span>Geser tabel ke samping untuk melihat detail</span>
                    </div>
                </div>
            {/if}
        </div>

        <!-- Ulasan Section -->
        <div
            class="bg-white rounded-none sm:rounded-2xl border-y sm:border border-slate-100 shadow-sm p-5 sm:p-7"
        >
            <h3
                class="text-base font-bold text-slate-800 flex items-center gap-2 mb-5"
            >
                <i class="ti ti-star text-lg" style="color: {primary};"></i>
                Ulasan Pembeli
                {#if reviews.length > 0}
                    <span class="ml-auto text-xs font-semibold text-slate-500"
                        >{reviews.length} ulasan</span
                    >
                {/if}
            </h3>

            {#if reviews.length > 0}
                {@const avgRating =
                    reviews.reduce(
                        (s: number, r: any) => s + Number(r.rating),
                        0,
                    ) / reviews.length}
                <!-- Rating Summary -->
                <div
                    class="flex items-center gap-5 p-4 rounded-xl mb-5"
                    style="background:{withOpacity(
                        primary,
                        0.04,
                    )}; border: 1px solid {withOpacity(primary, 0.1)};"
                >
                    <div class="text-center shrink-0">
                        <p class="text-4xl font-black" style="color:{primary}">
                            {avgRating.toFixed(1)}
                        </p>
                        <div
                            class="flex items-center gap-0.5 mt-1 justify-center"
                        >
                            {#each [1, 2, 3, 4, 5] as s}
                                <i
                                    class="ti ti-star-filled text-sm"
                                    style="color:{s <= Math.round(avgRating)
                                        ? '#f59e0b'
                                        : '#e2e8f0'};"
                                ></i>
                            {/each}
                        </div>
                        <p class="text-[10px] text-slate-500 mt-0.5">
                            {reviews.length} ulasan
                        </p>
                    </div>
                    <div class="flex-grow space-y-1">
                        {#each [5, 4, 3, 2, 1] as star}
                            {@const count = reviews.filter(
                                (r: any) => Number(r.rating) === star,
                            ).length}
                            {@const pct =
                                reviews.length > 0
                                    ? (count / reviews.length) * 100
                                    : 0}
                            <div class="flex items-center gap-2">
                                <span
                                    class="text-[10px] font-semibold text-slate-500 w-3 text-right"
                                    >{star}</span
                                >
                                <i
                                    class="ti ti-star-filled text-[10px] text-amber-400"
                                ></i>
                                <div
                                    class="flex-grow h-1.5 bg-slate-200 rounded-full overflow-hidden"
                                >
                                    <div
                                        class="h-full rounded-full transition-all"
                                        style="width:{pct}%; background:{primary};"
                                    ></div>
                                </div>
                                <span class="text-[10px] text-slate-400 w-4"
                                    >{count}</span
                                >
                            </div>
                        {/each}
                    </div>
                </div>

                <!-- Review list -->
                <div class="space-y-4">
                    {#each reviews as review}
                        <div
                            class="border-b border-slate-100 pb-4 last:border-0 last:pb-0"
                        >
                            <div class="flex items-start gap-3">
                                <!-- Avatar -->
                                <div
                                    class="w-9 h-9 rounded-full flex items-center justify-center shrink-0 font-bold text-white text-sm"
                                    style="background: linear-gradient(135deg, {primary}, {secondary});"
                                >
                                    {(review.user?.name ?? 'A')
                                        .charAt(0)
                                        .toUpperCase()}
                                </div>
                                <div class="flex-grow min-w-0">
                                    <div
                                        class="flex items-center gap-2 flex-wrap"
                                    >
                                        <span
                                            class="text-sm font-bold text-slate-800"
                                            >{review.is_anonymous
                                                ? 'Pengguna Anonim'
                                                : (review.user?.name ??
                                                  'Pembeli')}</span
                                        >
                                        {#if review.product_variant?.options?.length > 0}
                                            <span
                                                class="text-[10px] bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full"
                                            >
                                                {review.product_variant.options
                                                    .map((o: any) => o.value)
                                                    .join(', ')}
                                            </span>
                                        {/if}
                                        <span
                                            class="text-[10px] text-slate-400 ml-auto"
                                        >
                                            {new Date(
                                                review.created_at,
                                            ).toLocaleDateString('id-ID', {
                                                day: 'numeric',
                                                month: 'short',
                                                year: 'numeric',
                                            })}
                                        </span>
                                    </div>
                                    <!-- Stars -->
                                    <div class="flex items-center gap-0.5 mt-1">
                                        {#each [1, 2, 3, 4, 5] as s}
                                            <i
                                                class="ti ti-star-filled text-xs"
                                                style="color:{s <= review.rating
                                                    ? '#f59e0b'
                                                    : '#e2e8f0'};"
                                            ></i>
                                        {/each}
                                    </div>
                                    {#if review.comment}
                                        <p
                                            class="text-sm text-slate-700 mt-2 leading-relaxed"
                                        >
                                            {review.comment}
                                        </p>
                                    {/if}
                                    <!-- Media -->
                                    {#if review.media && review.media.length > 0}
                                        <div class="flex gap-2 mt-2 flex-wrap">
                                            {#each review.media as mediaUrl}
                                                {@const isVideo =
                                                    /\.(mp4|mov|avi|webm)$/i.test(
                                                        mediaUrl,
                                                    )}
                                                {#if isVideo}
                                                    <video
                                                        src={mediaUrl}
                                                        class="w-16 h-16 object-cover rounded-lg border border-slate-200 cursor-pointer"
                                                        muted
                                                        playsinline
                                                        onclick={(e: any) => {
                                                            e.preventDefault();
                                                            reviewLightboxMedia =
                                                                {
                                                                    type: 'video',
                                                                    url: mediaUrl,
                                                                };
                                                            reviewLightboxOpen = true;
                                                        }}
                                                    ></video>
                                                {:else}
                                                    <!-- svelte-ignore a11y_click_events_have_key_events -->
                                                    <!-- svelte-ignore a11y_no_noninteractive_element_interactions -->
                                                    <img
                                                        src={mediaUrl}
                                                        alt="Foto ulasan"
                                                        class="w-16 h-16 object-cover rounded-lg border border-slate-200 cursor-pointer hover:opacity-90 transition"
                                                        onclick={() => {
                                                            reviewLightboxMedia =
                                                                {
                                                                    type: 'image',
                                                                    url: mediaUrl,
                                                                };
                                                            reviewLightboxOpen = true;
                                                        }}
                                                        onerror={(e: any) => {
                                                            e.target.style.display =
                                                                'none';
                                                        }}
                                                    />
                                                {/if}
                                            {/each}
                                        </div>
                                    {/if}
                                    <!-- Report button -->
                                    <!-- svelte-ignore a11y_click_events_have_key_events -->
                                    <div class="flex justify-end mt-2">
                                        {#if review.is_reported}
                                            <span
                                                class="text-[10px] text-orange-400 flex items-center gap-1"
                                                ><i class="ti ti-flag-filled"
                                                ></i> Dilaporkan</span
                                            >
                                        {:else}
                                            <button
                                                aria-label="Laporkan ulasan"
                                                onclick={() =>
                                                    openReportModal(review)}
                                                class="text-[10px] text-slate-400 hover:text-red-400 flex items-center gap-1 transition"
                                            >
                                                <i class="ti ti-flag"></i> Laporkan
                                            </button>
                                        {/if}
                                    </div>
                                </div>
                            </div>
                        </div>
                    {/each}
                </div>
            {:else}
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
                    <p class="font-bold text-slate-700 mb-1">
                        Belum ada ulasan
                    </p>
                    <p class="text-sm text-slate-400">
                        Jadilah yang pertama memberikan ulasan
                    </p>
                </div>
            {/if}
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
                                    alt="Produk tanpa gambar"
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
                            {#if cartButtonStyle === 'icon'}
                                <button
                                    onclick={(e) =>
                                        handleDirectAddToCart(rp, e)}
                                    class="absolute w-8 h-8 rounded-full bg-white/95 hover:bg-white text-slate-800 flex items-center justify-center shadow-md border transition-all duration-200 active:scale-90 hover:scale-105 z-10"
                                    style="top: 0.375rem; right: 0.375rem; border-color: {primary}; color: {primary};"
                                    title="Tambah ke Keranjang"
                                >
                                    <i class="ti ti-plus text-base font-black"
                                    ></i>
                                </button>
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
                                <div class="h-[2.5rem] overflow-hidden mb-1">
                                    <p
                                        class="text-xs sm:text-sm font-black leading-tight line-clamp-2"
                                        style="color: #1e293b;"
                                    >
                                        {rp.name}
                                    </p>
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
                            {#if cartButtonStyle === 'button'}
                                <div class="mt-auto pt-3">
                                    <button
                                        type="button"
                                        onclick={(e) => {
                                            e.preventDefault();
                                            handleDirectAddToCart(rp, e);
                                        }}
                                        class="w-full flex items-center justify-center gap-1.5 py-2 px-3 rounded-xl font-bold text-[10px] sm:text-xs text-white uppercase tracking-wider transition duration-200 hover:brightness-95 active:scale-[0.98] border-0"
                                        style="background-color: {primary};"
                                    >
                                        <i
                                            class="ti ti-shopping-cart text-xs sm:text-sm"
                                        ></i>
                                        + KERANJANG
                                    </button>
                                </div>
                            {/if}
                        </div>
                    </Link>
                {/each}
            </div>
        </div>
    {/if}

    <!-- ─────────────────────────────────────────────────────
     CAMERA AR MODAL — fullscreen popup with live camera feed + 3D overlay
───────────────────────────────────────────────────── -->
    {#if isCameraModalOpen && arModelPath}
        <div
            class="fixed inset-0 z-[210] flex flex-col bg-black"
            role="dialog"
            aria-modal="true"
            aria-label="Kamera AR"
            onkeydown={(e) => e.key === 'Escape' && closeCameraModal()}
            tabindex="-1"
        >
            <!-- Live camera feed -->
            <video
                bind:this={cameraVideoEl}
                autoplay
                playsinline
                muted
                class="absolute inset-0 w-full h-full object-cover"
            >
                <track kind="captions" />
            </video>

            <!-- 3D model overlaid transparently on camera feed -->
            <model-viewer
                src={formatImagePath(arModelPath)}
                camera-controls
                auto-rotate
                interaction-prompt="auto"
                class="absolute inset-0 w-full h-full"
                style="--poster-color: transparent; background-color: transparent; touch-action: none;"
            >
            </model-viewer>

            <!-- Top bar: title + close -->
            <div
                class="absolute top-0 left-0 right-0 flex items-center justify-between px-4 py-4 z-30 bg-gradient-to-b from-black/70 to-transparent"
            >
                <div class="flex items-center gap-2 text-white">
                    <i class="ti ti-camera text-lg"></i>
                    <span class="font-bold text-sm">Kamera AR Aktif</span>
                </div>
                <button
                    type="button"
                    onclick={closeCameraModal}
                    class="w-9 h-9 rounded-full bg-white/20 hover:bg-white/30 active:scale-95 backdrop-blur-sm text-white flex items-center justify-center transition shadow-lg"
                    aria-label="Tutup kamera"
                >
                    <i class="ti ti-x text-lg"></i>
                </button>
            </div>

            <!-- Bottom hint -->
            <div
                class="absolute bottom-0 left-0 right-0 px-4 pb-8 pt-16 z-30 bg-gradient-to-t from-black/70 to-transparent flex flex-col items-center gap-3"
            >
                <p class="text-white/80 text-xs text-center">
                    Arahkan kamera ke permukaan datar · Gunakan gesture untuk
                    memutar model 3D
                </p>
                <button
                    type="button"
                    onclick={closeCameraModal}
                    class="bg-white/20 hover:bg-white/30 active:scale-95 backdrop-blur-sm text-white px-5 py-2 rounded-full font-semibold text-sm transition flex items-center gap-2 border border-white/20"
                >
                    <i class="ti ti-camera-off text-base"></i>
                    Matikan Kamera
                </button>
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
                type="button"
                aria-label="Tutup"
                onclick={() => (lightboxOpen = false)}
                class="absolute top-4 right-4 w-11 h-11 rounded-full bg-white/15 hover:bg-white/25 text-white flex items-center justify-center transition border border-white/20 z-50"
            >
                <i class="ti ti-x text-lg"></i>
            </button>

            <!-- Prev/Next (only if gallery has multiple) -->
            {#if gallery.length > 1}
                <button
                    type="button"
                    aria-label="Sebelumnya"
                    onclick={(e) => {
                        e.stopPropagation();
                        lightboxPrev();
                    }}
                    class="absolute left-4 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-white/15 hover:bg-white/25 text-white flex items-center justify-center transition z-50"
                >
                    <i class="ti ti-chevron-left text-xl"></i>
                </button>
                <button
                    type="button"
                    aria-label="Selanjutnya"
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
                role="presentation"
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
                                type="button"
                                aria-label="Gambar {i + 1}"
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

    <!-- Sticky Bottom Bar (Mobile Only) -->
    <div
        class="fixed bottom-0 left-0 right-0 z-50 bg-white border-t border-slate-100 px-4 pt-2.5 pb-2.5 flex items-center gap-3 md:hidden shadow-[0_-4px_16px_rgba(0,0,0,0.06)]"
        style="padding-bottom: calc(0.625rem + env(safe-area-inset-bottom, 0px));"
    >
        <!-- Chat Button -->
        <button
            onclick={() => {
                chatOpen = true;
                attachMenuOpen = false;
            }}
            class="w-12 h-12 flex items-center justify-center rounded-xl border border-slate-200 text-slate-700 active:bg-slate-50 transition shrink-0"
            aria-label="Chat Penjual"
        >
            <i class="ti ti-message-dots text-xl"></i>
        </button>

        <!-- Beli Langsung (Outline style) -->
        {#if isEffectivelyOutOfStock}
            <div
                class="flex-1 py-3 px-1 rounded-xl font-bold text-xs sm:text-sm text-center bg-slate-100 text-slate-400 border border-slate-200"
            >
                {isPromoStockExhausted ? 'Stok Promo Habis' : 'Stok Habis'}
            </div>
        {:else}
            <button
                onclick={() => {
                    drawerAction = 'buy';
                    drawerOpen = true;
                }}
                class="flex-1 py-3 px-1 rounded-xl font-bold text-xs sm:text-sm border-2 transition active:scale-[0.98] text-center"
                style="border-color: {primary}; color: {primary};"
            >
                Beli Langsung
            </button>

            <!-- + Keranjang (Solid style) -->
            <button
                onclick={() => {
                    if (hasVariations) {
                        drawerAction = 'cart';
                        drawerOpen = true;
                    } else {
                        addToCart();
                    }
                }}
                class="flex-1 py-3 px-1 rounded-xl font-bold text-xs sm:text-sm text-white transition active:scale-[0.98] text-center shadow-md"
                style="background-color: {primary};"
            >
                + Keranjang
            </button>
        {/if}
    </div>

    <!-- Bottom Drawer for Varian Produk (Mobile Only) -->
    {#if drawerOpen}
        <!-- Backdrop -->
        <div
            role="presentation"
            class="fixed inset-0 z-[100] bg-black/60 backdrop-blur-sm md:hidden"
            onclick={() => (drawerOpen = false)}
            transition:fade={{ duration: 150 }}
        ></div>

        <!-- Drawer Content -->
        <div
            class="fixed bottom-0 left-0 right-0 z-[101] bg-white rounded-t-3xl md:hidden max-h-[85vh] flex flex-col shadow-2xl animate-slide-up"
            style="padding-bottom: env(safe-area-inset-bottom, 0px);"
        >
            <!-- Header -->
            <div
                class="flex items-center justify-between px-5 py-4 border-b border-slate-100 shrink-0"
            >
                <span class="text-base font-extrabold text-slate-800"
                    >Varian produk</span
                >
                <button
                    type="button"
                    aria-label="Tutup"
                    onclick={(e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        drawerOpen = false;
                    }}
                    class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition cursor-pointer relative z-55"
                >
                    <i class="ti ti-x text-base"></i>
                </button>
            </div>

            <!-- Fixed Product Overview at the top of drawer -->
            <div
                class="flex gap-4 items-start p-5 border-b border-slate-100 shrink-0 bg-white"
            >
                <div
                    class="w-24 h-24 rounded-xl overflow-hidden bg-slate-50 border border-slate-100 shrink-0 select-none"
                >
                    <img
                        src={displayImage || '/noimage/image.png'}
                        alt={product.name}
                        class="w-full h-full object-cover"
                        onerror={(e) => {
                            e.currentTarget.src = '/noimage/image.png';
                        }}
                    />
                </div>
                <div class="flex-grow pt-1">
                    <p class="text-xl font-bold" style="color: {secondary};">
                        {fmt(activeUnitPrice)}
                    </p>
                    <!-- Selected variations hint -->
                    <div
                        class="text-xs text-slate-400 mt-1 flex flex-wrap gap-1 items-center"
                    >
                        {#if hasVariations}
                            {@const selectedParts = []}
                            {#each product.variations as v}
                                {@const lbl = getSelectedLabel(v)}
                                {#if lbl}
                                    {selectedParts.push(lbl) && ''}
                                {/if}
                            {/each}
                            {#if selectedParts.length > 0}
                                <span
                                    class="bg-slate-50 text-slate-600 px-2 py-0.5 rounded font-medium border border-slate-100"
                                >
                                    {selectedParts.join(' / ')}
                                </span>
                            {:else}
                                <span class="text-slate-400"
                                    >Pilih variasi terlebih dahulu</span
                                >
                            {/if}
                        {:else}
                            <span class="text-green-600 font-bold"
                                >Stok Ready</span
                            >
                        {/if}
                    </div>
                </div>
            </div>

            <!-- Scrollable Content -->
            <div
                class="flex-grow overflow-y-auto px-5 py-4 flex flex-col gap-4"
            >
                <!-- Recommendation Badge -->
                <!-- <div class="flex items-center gap-2 p-3 bg-amber-50 rounded-xl border border-amber-100/60 text-amber-700 text-xs font-bold shrink-0 shadow-sm animate-pulse">
                    <span class="text-sm">👍</span>
                    <span>Pilihan tepat! 100% pembeli merasa puas!</span>
                </div> -->

                <!-- Variations List -->
                {#if hasVariations}
                    <div class="flex flex-col divide-y divide-slate-100">
                        {#each product.variations as variation}
                            {@const selLabel = getSelectedLabel(variation)}
                            <div class="py-3 flex flex-col gap-2.5">
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
                                        >
                                            {selLabel}
                                        </span>
                                    {/if}
                                </div>
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
                                            class="relative flex items-center gap-2 px-3 py-2 rounded-xl border-2 text-xs font-bold transition duration-150
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
                                                    class="w-7 h-7 rounded-lg object-cover shrink-0"
                                                    onerror={(e) => {
                                                        e.currentTarget.src =
                                                            '/noimage/image.png';
                                                    }}
                                                />
                                            {/if}
                                            {opt.name}
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
                    </div>
                {/if}

                <!-- Drawer Wholesale Promo Clash Alert -->
                {#if isPromoActive && !shouldKeepTierPricesDuringPromo && hasWholesalePrices}
                    <div
                        class="rounded-2xl p-3.5 flex items-start gap-3 border shadow-xs my-1"
                        style="background-color: #fffaf0; border-color: #fbd38d;"
                    >
                        <div
                            class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center shrink-0"
                        >
                            <i
                                class="ti ti-info-circle text-amber-600 text-base animate-pulse"
                            ></i>
                        </div>
                        <div class="flex-grow text-[11px] leading-relaxed">
                            <span class="font-bold text-amber-950 block text-xs"
                                >Harga Grosir Dijeda Sementara</span
                            >
                            <span class="text-amber-800 mt-0.5 block">
                                Karena promo <span
                                    class="font-black text-amber-950 uppercase"
                                    >{isPromoActive
                                        ? (
                                              matchingVariant?.promo_type ??
                                              product.promo_type ??
                                              'Promo'
                                          ).replace('_', ' ')
                                        : ''}</span
                                > aktif, diskon grosir dinonaktifkan untuk memberikan
                                harga promo terbaik.
                            </span>
                        </div>
                    </div>
                {/if}

                <!-- Drawer Wholesale Tier Prices Table -->
                {#if activeTierPrices && activeTierPrices.length > 0}
                    <div class="w-full my-1 px-1">
                        <div
                            class="flex items-center gap-1.5 mb-2 text-xs font-bold text-slate-700"
                        >
                            <i class="ti ti-tags text-brand-blueRoyal text-sm"
                            ></i>
                            <span>Daftar Harga Grosir</span>
                        </div>
                        <div
                            class="w-full overflow-hidden border border-slate-100 rounded-xl bg-white shadow-xs"
                        >
                            <table
                                class="w-full text-left border-collapse text-[11px]"
                            >
                                <thead>
                                    <tr
                                        class="bg-slate-50/65 border-b border-slate-100 text-[10px] text-slate-400 font-bold uppercase tracking-wider"
                                    >
                                        <th class="py-2.5 px-3 font-semibold"
                                            >Min. Pembelian</th
                                        >
                                        <th
                                            class="py-2.5 px-3 font-semibold text-right"
                                            >Harga Satuan</th
                                        >
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50">
                                    {#each activeTierPrices as tier}
                                        {@const isActive =
                                            (!hasVariations || fullySelected) &&
                                            activeTier &&
                                            Number(activeTier.min_qty) ===
                                                Number(tier.min_qty)}
                                        <tr
                                            class="transition duration-150 {isActive
                                                ? 'bg-brand-blueRoyal/[0.02]'
                                                : ''}"
                                        >
                                            <td
                                                class="py-2.5 px-3 font-semibold text-slate-700"
                                            >
                                                {tier.min_qty}+ pcs
                                            </td>
                                            <td
                                                class="py-2.5 px-3 font-black text-slate-800 text-right"
                                            >
                                                {fmt(tier.price)}
                                                <span
                                                    class="text-[9px] text-slate-400 font-normal"
                                                    >/pc</span
                                                >
                                            </td>
                                        </tr>
                                    {/each}
                                </tbody>
                            </table>
                        </div>
                    </div>
                {/if}

                <!-- Quantity Stepper -->
                <div
                    class="py-3 flex flex-col gap-2 border-t border-slate-100 mt-2 shrink-0"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex flex-col gap-0.5">
                            <span class="text-sm font-bold text-slate-700"
                                >Jumlah</span
                            >
                            <div class="flex items-center gap-1.5">
                                {#if hasVariations && !fullySelected}
                                    <span class="text-[11px] text-slate-400"
                                        >Pilih variasi terlebih dahulu</span
                                    >
                                {:else if currentIsUnlimited}
                                    <span
                                        class="text-[11px] font-bold text-green-600 tracking-wide"
                                        >TERSEDIA</span
                                    >
                                {:else if isInStock}
                                    <span
                                        class="text-[11px] font-bold text-green-600"
                                        >Stok: {currentStock}</span
                                    >
                                {:else}
                                    <span
                                        class="text-[11px] font-bold text-red-500"
                                        >HABIS</span
                                    >
                                {/if}
                                {#if currentMinPurchase > 1}
                                    <span
                                        class="text-[10px] text-slate-400 font-semibold"
                                        >(Min. {currentMinPurchase} pcs)</span
                                    >
                                {/if}
                            </div>
                        </div>
                        <div
                            class="flex items-center border border-slate-300 rounded-lg overflow-hidden shrink-0 bg-white"
                        >
                            <button
                                type="button"
                                aria-label="Kurangi jumlah"
                                onclick={decQty}
                                disabled={qty <= currentMinPurchase}
                                class="w-9 h-9 flex items-center justify-center hover:bg-slate-100 transition text-slate-600 disabled:opacity-30"
                            >
                                <i class="ti ti-minus text-sm"></i>
                            </button>
                            <input
                                type="number"
                                value={qty}
                                min={currentMinPurchase}
                                max={currentIsUnlimited
                                    ? undefined
                                    : currentStock}
                                class="w-12 text-center text-sm font-black text-slate-800 tabular-nums border-none outline-none focus:ring-0 focus:outline-none p-0 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                oninput={handleQtyInput}
                                onblur={handleQtyBlur}
                            />
                            <button
                                type="button"
                                aria-label="Tambah jumlah"
                                onclick={incQty}
                                disabled={!currentIsUnlimited &&
                                    qty >= currentStock}
                                class="w-9 h-9 flex items-center justify-center hover:bg-slate-100 transition text-slate-600 disabled:opacity-30"
                            >
                                <i class="ti ti-plus text-sm"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Total Price Live Calculation (quantity x harga = total) -->
                    {#if !hasVariations || fullySelected}
                        <div
                            class="flex items-center justify-between bg-slate-50 p-2.5 rounded-xl border border-slate-100 text-xs mt-1"
                        >
                            <span class="text-slate-500 font-medium"
                                >Subtotal Pemesanan</span
                            >
                            <span
                                class="font-extrabold text-sm text-brand-blueRoyal"
                                >{fmt(totalPrice)}</span
                            >
                        </div>
                    {/if}

                    <!-- Minimum Purchase Info Alert (If set) -->
                    {#if currentMinPurchase > 1}
                        <div
                            class="mt-1 p-3 bg-blue-50/40 rounded-xl border border-blue-100/60 flex items-start gap-2 text-[11px] text-blue-700 font-medium"
                        >
                            <i
                                class="ti ti-info-circle text-blue-500 text-sm mt-0.5 shrink-0"
                            ></i>
                            <span>
                                Minimum pembelian {currentMinPurchase} pcs
                            </span>
                        </div>
                    {/if}
                </div>
            </div>

            <!-- Sticky Bottom CTA Button inside Drawer -->
            <div class="p-4 border-t border-slate-100 bg-slate-50 shrink-0">
                {#if isEffectivelyOutOfStock}
                    <div
                        class="w-full py-3.5 rounded-xl font-bold text-sm text-center bg-slate-100 text-slate-400 border border-slate-200"
                    >
                        {isPromoStockExhausted
                            ? 'Stok Promo Habis'
                            : 'Stok Habis'}
                    </div>
                {:else if drawerAction === 'buy'}
                    <button
                        onclick={() => {
                            if (hasVariations && !fullySelected) {
                                return; // Variation must be chosen
                            }
                            drawerOpen = false;
                            buyNow();
                        }}
                        disabled={hasVariations && !fullySelected}
                        class="w-full py-3.5 rounded-xl font-bold text-sm text-white text-center shadow-lg transition duration-200 active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed"
                        style="background-color: {primary};"
                    >
                        Beli Langsung
                    </button>
                {:else}
                    <button
                        onclick={() => {
                            if (hasVariations && !fullySelected) {
                                return; // Variation must be chosen
                            }
                            drawerOpen = false;
                            addToCart();
                        }}
                        disabled={hasVariations && !fullySelected}
                        class="w-full py-3.5 rounded-xl font-bold text-sm text-white text-center shadow-lg transition duration-200 active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed"
                        style="background-color: {primary};"
                    >
                        + Keranjang
                    </button>
                {/if}
            </div>
        </div>
    {/if}

    <!-- Bottom Spacer to prevent overlap on mobile -->
    <div class="h-14 md:hidden"></div>

    <!-- ============================
         CHAT MODAL (Mobile Full-screen)
         ============================ -->
    {#if chatOpen}
        <!-- Full-screen overlay -->
        <div
            class="fixed inset-0 z-[200] flex flex-col bg-slate-100 md:hidden animate-slide-up"
            style="padding-bottom: env(safe-area-inset-bottom, 0px);"
        >
            <!-- ── Header ── -->
            <div
                class="bg-white flex items-center gap-3 px-4 py-3 border-b border-slate-100 shadow-sm shrink-0"
            >
                <button
                    onclick={() => {
                        chatOpen = false;
                        attachMenuOpen = false;
                        productAttachOpen = false;
                    }}
                    class="w-9 h-9 flex items-center justify-center rounded-full hover:bg-slate-100 transition shrink-0"
                    aria-label="Tutup chat"
                >
                    <i class="ti ti-x text-lg text-slate-700"></i>
                </button>
                <!-- Store avatar -->
                <div
                    class="w-9 h-9 rounded-full flex items-center justify-center text-white text-sm font-bold shrink-0 shadow"
                    style="background-color: {primary};"
                >
                    {storeName.charAt(0).toUpperCase()}
                </div>
                <div class="flex-1 min-w-0">
                    <p
                        class="font-outfit font-black text-sm text-slate-800 truncate"
                    >
                        {storeName}
                    </p>
                    <p
                        class="text-[10px] text-emerald-500 font-bold flex items-center gap-1"
                    >
                        <span
                            class="w-1.5 h-1.5 bg-emerald-400 rounded-full inline-block"
                        ></span>
                        Online
                    </p>
                </div>
                <button
                    class="w-9 h-9 flex items-center justify-center rounded-full hover:bg-slate-100 transition"
                    aria-label="Opsi"
                >
                    <i class="ti ti-dots-vertical text-lg text-slate-500"></i>
                </button>
            </div>

            <!-- ── Chat Body ── -->
            <div
                class="flex-1 overflow-y-auto px-4 py-4 space-y-3 relative chat-body-container"
            >
                <!-- Fraud warning banner -->
                <div
                    class="bg-amber-50 border border-amber-100 rounded-2xl px-4 py-3 text-xs text-amber-800 leading-relaxed text-center"
                >
                    ⚠️ Hati-hati penipuan! Jangan bertransaksi di luar platform
                    dan jangan berikan data pribadi kepada penjual.
                </div>

                <!-- Messages -->
                {#each chatMessages as msg (msg.id)}
                    <div
                        class="flex flex-col {msg.sender_type === 'user'
                            ? 'items-end'
                            : 'items-start'} gap-1"
                    >
                        {#if msg.attachment_type === 'product' && msg.attachment_data}
                            <!-- Product card bubble -->
                            <div
                                class="max-w-[75%] rounded-2xl overflow-hidden border shadow-sm {msg.sender_type ===
                                'user'
                                    ? 'rounded-tr-sm'
                                    : 'rounded-tl-sm'}"
                                style="background-color: {msg.sender_type ===
                                'user'
                                    ? primary
                                    : 'white'};"
                            >
                                <div class="flex items-center gap-2.5 p-3">
                                    <img
                                        src={formatImagePath(
                                            msg.attachment_data.image,
                                        )}
                                        alt={msg.attachment_data.name}
                                        class="w-12 h-12 rounded-xl object-cover shrink-0 bg-slate-100"
                                        onerror={(e: any) => {
                                            e.target.src = '/noimage/image.png';
                                        }}
                                    />
                                    <div class="min-w-0">
                                        <p
                                            class="text-xs font-bold truncate {msg.sender_type ===
                                            'user'
                                                ? 'text-white'
                                                : 'text-slate-800'}"
                                        >
                                            {msg.attachment_data.name}
                                        </p>
                                        <p
                                            class="text-xs mt-0.5 font-black {msg.sender_type ===
                                            'user'
                                                ? 'text-white/90'
                                                : 'text-orange-500'}"
                                        >
                                            Rp{Number(
                                                msg.attachment_data.price ?? 0,
                                            ).toLocaleString('id-ID')}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        {/if}

                        {#if msg.attachment_type === 'image' && msg.attachment_data?.url}
                            <!-- Image card bubble -->
                            <div
                                class="max-w-[75%] rounded-2xl overflow-hidden border shadow-sm {msg.sender_type ===
                                'user'
                                    ? 'rounded-tr-sm'
                                    : 'rounded-tl-sm'}"
                            >
                                <button
                                    type="button"
                                    aria-label="Lihat gambar"
                                    onclick={() =>
                                        (chatPreviewUrl =
                                            msg.attachment_data.url)}
                                    class="block w-full text-left p-0 border-0 bg-transparent cursor-pointer"
                                >
                                    <img
                                        src={msg.attachment_data.url}
                                        alt="Gambar terlampir"
                                        class="max-w-full max-h-60 object-contain bg-slate-100 rounded-xl"
                                    />
                                </button>
                            </div>
                        {/if}

                        {#if msg.body}
                            <div
                                class="max-w-[75%] px-4 py-2.5 rounded-2xl text-sm leading-relaxed shadow-sm {msg.sender_type ===
                                'user'
                                    ? 'rounded-tr-sm text-white'
                                    : 'rounded-tl-sm text-slate-800 bg-white'}"
                                style="background-color: {msg.sender_type ===
                                'user'
                                    ? primary
                                    : 'white'};"
                            >
                                {msg.body}
                            </div>
                        {/if}

                        <span class="text-[10px] text-slate-400 px-1"
                            >{msg.time}</span
                        >
                    </div>
                {/each}
            </div>

            <!-- ── Quick Replies ── -->
            <div
                class="flex gap-2 px-4 pb-2 overflow-x-auto shrink-0 no-scrollbar"
            >
                {#each quickReplies as qr}
                    <button
                        onclick={() => quickReply(qr)}
                        class="shrink-0 px-3 py-1.5 text-xs font-bold border-2 rounded-full transition active:scale-95 whitespace-nowrap"
                        style="border-color: {primary}; color: {primary}; background: white;"
                    >
                        {qr.length > 18 ? qr.slice(0, 16) + '...' : qr}
                    </button>
                {/each}
            </div>

            <!-- ── Attached Product Preview ── -->
            {#if attachedProduct}
                <div class="px-4 pb-2 shrink-0">
                    <div
                        class="flex items-center gap-2.5 bg-white border border-slate-200 rounded-2xl px-3 py-2 shadow-sm"
                    >
                        <img
                            src={formatImagePath(attachedProduct.image)}
                            alt={attachedProduct.name}
                            class="w-10 h-10 rounded-lg object-cover bg-slate-100 shrink-0"
                            onerror={(e: any) => {
                                e.target.src = '/noimage/image.png';
                            }}
                        />
                        <div class="flex-1 min-w-0">
                            <p
                                class="text-xs font-bold text-slate-800 truncate"
                            >
                                {attachedProduct.name}
                            </p>
                            <p class="text-xs font-black text-orange-500">
                                Rp{Number(
                                    attachedProduct.price ?? 0,
                                ).toLocaleString('id-ID')}
                            </p>
                        </div>
                        <button
                            onclick={() => (attachedProduct = null)}
                            class="text-slate-400 hover:text-slate-600 p-1"
                            aria-label="Hapus lampiran"
                        >
                            <i class="ti ti-x text-sm"></i>
                        </button>
                    </div>
                </div>
            {/if}

            <!-- ── Attached Image Preview ── -->
            {#if attachedImageUrl}
                <div class="px-4 pb-2 shrink-0">
                    <div
                        class="relative inline-block bg-white border border-slate-200 rounded-2xl p-2 shadow-sm"
                    >
                        <img
                            src={attachedImageUrl}
                            alt="Preview"
                            class="w-20 h-20 rounded-xl object-cover"
                        />
                        <button
                            onclick={() => {
                                attachedImage = null;
                                attachedImageUrl = null;
                            }}
                            class="absolute -top-1.5 -right-1.5 bg-rose-500 text-white hover:bg-rose-600 rounded-full w-5 h-5 flex items-center justify-center shadow"
                            aria-label="Hapus gambar"
                        >
                            <i class="ti ti-x text-xs"></i>
                        </button>
                    </div>
                </div>
            {/if}

            <!-- ── Input Bar ── -->
            <div
                class="bg-white border-t border-slate-100 px-4 pt-3 pb-3 shrink-0"
            >
                <div class="flex items-center gap-2">
                    <!-- Emoji placeholder -->
                    <button
                        class="text-slate-400 hover:text-slate-600 w-9 h-9 flex items-center justify-center rounded-full transition"
                        aria-label="Emoji"
                    >
                        <i class="ti ti-mood-smile text-xl"></i>
                    </button>

                    <!-- Text input -->
                    <input
                        type="text"
                        bind:value={chatInput}
                        onkeydown={(e: KeyboardEvent) => {
                            if (e.key === 'Enter') sendChat();
                        }}
                        placeholder="Tulis pesan..."
                        class="flex-1 bg-slate-100 rounded-full px-4 py-2.5 text-sm focus:outline-none focus:bg-white focus:ring-1 focus:ring-slate-300 transition"
                    />

                    <!-- Attach (+) -->
                    <button
                        onclick={() => {
                            attachMenuOpen = !attachMenuOpen;
                            productAttachOpen = false;
                        }}
                        class="text-slate-400 hover:text-slate-600 w-9 h-9 flex items-center justify-center rounded-full transition {attachMenuOpen
                            ? 'bg-slate-100'
                            : ''}"
                        aria-label="Lampirkan"
                    >
                        <i class="ti ti-plus text-xl"></i>
                    </button>

                    <!-- Send -->
                    <button
                        onclick={sendChat}
                        disabled={!chatInput.trim() &&
                            !attachedProduct &&
                            !attachedImage}
                        class="w-10 h-10 rounded-full flex items-center justify-center text-white shadow transition active:scale-95 disabled:opacity-40 cursor-pointer"
                        style="background-color: {primary};"
                        aria-label="Kirim pesan"
                    >
                        <i class="ti ti-send text-base"></i>
                    </button>
                </div>
            </div>

            <!-- ── Attachment Action Sheet ── -->
            {#if attachMenuOpen}
                <div
                    class="bg-white border-t border-slate-100 px-6 py-5 shrink-0 animate-slide-up"
                >
                    <div class="flex items-start gap-8">
                        <!-- Produk -->
                        <button
                            onclick={() => {
                                productAttachOpen = true;
                                attachMenuOpen = false;
                                productSearchQuery = '';
                            }}
                            class="flex flex-col items-center gap-2 cursor-pointer"
                        >
                            <div
                                class="w-14 h-14 rounded-full flex items-center justify-center shadow-md"
                                style="background: linear-gradient(135deg, #3b9ef0, #1d6ee6);"
                            >
                                <i class="ti ti-tag text-white text-2xl"></i>
                            </div>
                            <span class="text-xs font-bold text-slate-700"
                                >Produk</span
                            >
                        </button>

                        <!-- Gambar -->
                        <button
                            onclick={triggerImageUpload}
                            class="flex flex-col items-center gap-2 cursor-pointer"
                        >
                            <div
                                class="w-14 h-14 rounded-full flex items-center justify-center shadow-md"
                                style="background: linear-gradient(135deg, #f5a623, #e8891d);"
                            >
                                <i class="ti ti-photo text-white text-2xl"></i>
                            </div>
                            <span class="text-xs font-bold text-slate-700"
                                >Gambar</span
                            >
                        </button>

                        <!-- Invoice -->
                        <button
                            class="flex flex-col items-center gap-2 opacity-50 cursor-not-allowed"
                            disabled
                        >
                            <div
                                class="w-14 h-14 rounded-full flex items-center justify-center shadow-md"
                                style="background: linear-gradient(135deg, #9b5de5, #7b2fbe);"
                            >
                                <i class="ti ti-receipt text-white text-2xl"
                                ></i>
                            </div>
                            <span class="text-xs font-bold text-slate-700"
                                >Invoice</span
                            >
                        </button>
                    </div>
                </div>
            {/if}
        </div>

        <!-- ── Product Attach Modal ── -->
        {#if productAttachOpen}
            <div
                class="fixed inset-0 z-[210] flex flex-col bg-white md:hidden animate-slide-up"
            >
                <!-- Header -->
                <div
                    class="flex items-center gap-3 px-4 py-4 border-b border-slate-100 shrink-0"
                >
                    <button
                        onclick={() => {
                            productAttachOpen = false;
                            attachMenuOpen = true;
                        }}
                        class="w-9 h-9 flex items-center justify-center hover:bg-slate-100 rounded-full transition"
                        aria-label="Tutup"
                    >
                        <i class="ti ti-x text-lg text-slate-700"></i>
                    </button>
                    <h2 class="font-outfit font-black text-base text-slate-800">
                        Lampirkan Barang
                    </h2>
                </div>

                <!-- Search bar -->
                <div class="px-4 py-3 border-b border-slate-100 shrink-0">
                    <div class="relative">
                        <i
                            class="ti ti-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"
                        ></i>
                        <input
                            type="text"
                            bind:value={productSearchQuery}
                            placeholder="Cari produk di semua etalase"
                            class="w-full pl-10 pr-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:bg-white focus:ring-1 focus:ring-slate-300 transition"
                        />
                    </div>
                </div>

                <!-- Product List -->
                <div class="flex-1 overflow-y-auto divide-y divide-slate-100">
                    {#each [product, ...(relatedProducts ?? [])]
                        .filter((p: any) => !productSearchQuery || p.name
                                    ?.toLowerCase()
                                    .includes(productSearchQuery.toLowerCase()))
                        .slice(0, 20) as p (p.id ?? p.name)}
                        <button
                            onclick={() => {
                                attachedProduct = {
                                    name: p.name,
                                    image:
                                        p.image ||
                                        (p.images?.[0]?.url ??
                                            p.images?.[0]?.path),
                                    price:
                                        p.price ?? p.product_price?.price ?? 0,
                                };
                                productAttachOpen = false;
                                attachMenuOpen = false;
                            }}
                            class="w-full flex items-center gap-3 px-4 py-3 hover:bg-slate-50 transition text-left"
                        >
                            <img
                                src={formatImagePath(
                                    p.image ||
                                        (p.images?.[0]?.url ??
                                            p.images?.[0]?.path) ||
                                        null,
                                )}
                                alt={p.name}
                                class="w-14 h-14 rounded-2xl object-cover bg-slate-100 shrink-0"
                                onerror={(e: any) => {
                                    e.target.src = '/noimage/image.png';
                                }}
                            />
                            <div class="flex-1 min-w-0">
                                <p
                                    class="text-sm font-bold text-slate-800 line-clamp-2 leading-tight"
                                >
                                    {p.name}
                                </p>
                                <p
                                    class="text-sm font-black text-orange-500 mt-1"
                                >
                                    Rp{Number(
                                        p.price ?? p.product_price?.price ?? 0,
                                    ).toLocaleString('id-ID')}
                                </p>
                            </div>
                        </button>
                    {:else}
                        <div class="py-16 text-center text-slate-400">
                            <i class="ti ti-search text-3xl mb-2"></i>
                            <p class="text-sm font-bold">
                                Produk tidak ditemukan
                            </p>
                        </div>
                    {/each}
                </div>
            </div>
        {/if}
    {/if}

    <!-- Image Preview Modal -->
    {#if chatPreviewUrl}
        <!-- svelte-ignore a11y_no_static_element_interactions -->
        <!-- svelte-ignore a11y_click_events_have_key_events -->
        <div
            class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
            onclick={() => (chatPreviewUrl = null)}
        >
            <div class="absolute inset-0 bg-black/90 backdrop-blur-sm"></div>
            <!-- svelte-ignore a11y_no_static_element_interactions -->
            <!-- svelte-ignore a11y_click_events_have_key_events -->
            <div
                class="relative z-10 max-w-5xl w-full flex flex-col items-center justify-center"
                onclick={(e) => e.stopPropagation()}
            >
                <button
                    onclick={() => (chatPreviewUrl = null)}
                    class="absolute -top-12 right-0 text-white hover:text-slate-300 transition flex items-center gap-1 text-xs font-bold bg-white/10 hover:bg-white/20 px-3.5 py-1.5 rounded-full"
                >
                    <i class="ti ti-x text-sm"></i> Tutup
                </button>
                <img
                    src={chatPreviewUrl}
                    alt="Preview Attachment"
                    class="max-w-full max-h-[80vh] object-contain rounded-2xl shadow-2xl border border-white/10"
                />
            </div>
        </div>
    {/if}

    <VariantSelectorModal
        product={selectedVariantProduct}
        show={showVariantModal}
        onClose={() => (showVariantModal = false)}
        {primary}
        {secondary}
        {user}
    />

    <!-- ─────────────────────────────────────────────────────
     SPESIFIKASI MODAL (BOTTOM SHEET)
 ───────────────────────────────────────────────────── -->
    {#if showSpecsModal && parsedSpecifications.length > 0}
        <!-- Backdrop -->
        <!-- svelte-ignore a11y_click_events_have_key_events -->
        <!-- svelte-ignore a11y_no_static_element_interactions -->
        <div
            class="fixed inset-0 z-[150] bg-slate-900/50 backdrop-blur-sm"
            transition:fade={{ duration: 200 }}
            onclick={() => (showSpecsModal = false)}
        ></div>

        <!-- Bottom Sheet Panel -->
        <div
            class="fixed bottom-0 left-0 right-0 z-[151] bg-white rounded-t-3xl shadow-2xl flex flex-col max-h-[85vh] sm:max-w-lg sm:mx-auto sm:rounded-2xl sm:bottom-1/2 sm:translate-y-1/2 sm:inset-x-4 sm:top-auto sm:border sm:border-slate-100"
            transition:fly={{ y: 400, duration: 300, easing: cubicOut }}
        >
            <!-- Drag Handle for Mobile / Header bar -->
            <div class="flex justify-center pt-3 pb-1 shrink-0 sm:hidden">
                <div class="w-10 h-1 rounded-full bg-slate-200"></div>
            </div>

            <!-- Header Title + Close -->
            <div
                class="px-5 pt-3 pb-4 border-b border-slate-100 flex items-center justify-between shrink-0"
            >
                <h3
                    class="text-base font-black text-slate-800 flex items-center gap-2"
                >
                    <i class="ti ti-list text-lg" style="color: {primary};"></i>
                    Spesifikasi Produk
                </h3>
                <button
                    onclick={() => (showSpecsModal = false)}
                    aria-label="Tutup"
                    class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition"
                >
                    <i class="ti ti-x text-base"></i>
                </button>
            </div>

            <!-- Content Area (Scrollable) -->
            <div
                class="px-5 py-4 overflow-y-auto space-y-1 divide-y divide-slate-100"
            >
                {#each parsedSpecifications as [label, value]}
                    <div class="flex py-3.5 text-sm items-start gap-4">
                        <span
                            class="text-slate-400 w-1/3 font-bold uppercase tracking-wider text-[11px] shrink-0 mt-0.5"
                        >
                            {label}
                        </span>
                        <span class="text-slate-700 font-semibold flex-1">
                            {value}
                        </span>
                    </div>
                {/each}
            </div>

            <!-- Footer Action Button -->
            <div class="p-4 border-t border-slate-100 shrink-0">
                <button
                    onclick={() => (showSpecsModal = false)}
                    class="w-full py-3 rounded-xl font-bold text-sm text-white shadow-md hover:shadow-lg transition duration-200 cursor-pointer"
                    style="background: linear-gradient(135deg, {primary}, {secondary});"
                >
                    Tutup
                </button>
            </div>
        </div>
    {/if}

    <!-- Lightbox for Review Media -->
    {#if reviewLightboxOpen && reviewLightboxMedia}
        <!-- svelte-ignore a11y_click_events_have_key_events -->
        <!-- svelte-ignore a11y_no_static_element_interactions -->
        <div
            class="fixed inset-0 z-[100] flex items-center justify-center bg-black/95 p-4 sm:p-8 animate-pop"
            onclick={() => {
                reviewLightboxOpen = false;
            }}
        >
            <button
                class="absolute top-4 right-4 sm:top-6 sm:right-6 text-white/70 hover:text-white transition p-2 bg-black/20 rounded-full"
                aria-label="Tutup"
                onclick={(e) => {
                    e.stopPropagation();
                    reviewLightboxOpen = false;
                }}
            >
                <i class="ti ti-x text-2xl"></i>
            </button>

            <!-- svelte-ignore a11y_click_events_have_key_events -->
            <!-- svelte-ignore a11y_no_static_element_interactions -->
            <div
                class="relative max-w-4xl w-full max-h-full flex items-center justify-center rounded-xl overflow-hidden"
                onclick={(e) => e.stopPropagation()}
            >
                {#if reviewLightboxMedia.type === 'video'}
                    <video
                        src={reviewLightboxMedia.url}
                        class="max-w-full h-auto max-h-[85vh] object-contain bg-black"
                        controls
                        autoplay
                        playsinline
                    >
                        <track kind="captions" />
                    </video>
                {:else}
                    <img
                        src={reviewLightboxMedia.url}
                        alt="Preview ulasan"
                        class="max-w-full h-auto max-h-[85vh] object-contain bg-white rounded-md"
                    />
                {/if}
            </div>
        </div>
    {/if}

    <!-- Report Review Modal -->
    {#if showReportModal && reportingReview}
        <!-- svelte-ignore a11y_click_events_have_key_events -->
        <!-- svelte-ignore a11y_no_static_element_interactions -->
        <div
            class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
            onclick={() => (showReportModal = false)}
        >
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>
            <!-- svelte-ignore a11y_click_events_have_key_events -->
            <!-- svelte-ignore a11y_no_static_element_interactions -->
            <div
                class="relative z-10 bg-white w-full max-w-md rounded-2xl shadow-2xl"
                onclick={(e: any) => e.stopPropagation()}
            >
                <div
                    class="flex items-center justify-between px-5 pt-5 pb-3 border-b border-slate-100"
                >
                    <div>
                        <h3
                            class="font-bold text-slate-800 text-base flex items-center gap-2"
                        >
                            <i class="ti ti-flag text-red-500"></i>
                            Laporkan Ulasan
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Bantu kami menjaga kualitas ulasan
                        </p>
                    </div>
                    <button
                        aria-label="Tutup"
                        onclick={() => (showReportModal = false)}
                        class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center hover:bg-slate-200 transition"
                    >
                        <i class="ti ti-x text-sm text-slate-600"></i>
                    </button>
                </div>
                <div class="p-5 space-y-4">
                    <p class="text-xs text-slate-500 leading-relaxed">
                        Pilih alasan pelaporan ulasan ini. Laporan Anda akan
                        ditinjau oleh tim kami.
                    </p>
                    <div class="space-y-2">
                        {#each ['Konten tidak pantas / kasar', 'Spam atau promosi', 'Ulasan tidak relevan', 'Informasi palsu', 'Lainnya'] as reason}
                            <!-- svelte-ignore a11y_click_events_have_key_events -->
                            <div
                                role="button"
                                tabindex="0"
                                class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition"
                                style="border-color:{reportReason === reason
                                    ? primary + '60'
                                    : '#e2e8f0'}; background:{reportReason ===
                                reason
                                    ? primary + '08'
                                    : 'transparent'};"
                                onclick={() => (reportReason = reason)}
                                onkeydown={(e: KeyboardEvent) => {
                                    if (e.key === 'Enter' || e.key === ' ')
                                        reportReason = reason;
                                }}
                            >
                                <div
                                    class="w-4 h-4 rounded-full border-2 flex items-center justify-center shrink-0 transition"
                                    style="border-color:{reportReason === reason
                                        ? primary
                                        : '#cbd5e1'};"
                                >
                                    {#if reportReason === reason}
                                        <div
                                            class="w-2 h-2 rounded-full"
                                            style="background:{primary};"
                                        ></div>
                                    {/if}
                                </div>
                                <span class="text-sm text-slate-700"
                                    >{reason}</span
                                >
                            </div>
                        {/each}
                    </div>
                    <div class="flex gap-3 pt-1">
                        <button
                            onclick={() => (showReportModal = false)}
                            class="flex-1 py-3 rounded-xl border-2 border-slate-200 text-slate-700 font-semibold text-sm hover:bg-slate-50 transition"
                        >
                            Batal
                        </button>
                        <button
                            onclick={submitReport}
                            disabled={submittingReport || !reportReason.trim()}
                            class="flex-1 py-3 rounded-xl font-bold text-white text-sm transition disabled:opacity-50 active:scale-95"
                            style="background:#ef4444;"
                        >
                            {submittingReport ? 'Melaporkan...' : 'Laporkan'}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    {/if}
</StorefrontLayout>

<style>
    .no-scrollbar::-webkit-scrollbar {
        display: none;
    }
    .no-scrollbar {
        -ms-overflow-style: none; /* IE and Edge */
        scrollbar-width: none; /* Firefox */
    }

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

    @keyframes slide-up {
        from {
            transform: translateY(100%);
        }
        to {
            transform: translateY(0);
        }
    }
    .animate-slide-up {
        animation: slide-up 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
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
