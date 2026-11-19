<script lang="ts">
    import { router, usePage } from '@inertiajs/svelte';
    import StorefrontLayout from '@/components/layouts/StorefrontLayout.svelte';
    import FoodModifierModal from '@/components/Storefront/FoodModifierModal.svelte';
    import VariantSelectorModal from '@/components/Storefront/VariantSelectorModal.svelte';
    import { showToast } from '@/utils/toast';
    import EditProductFab from './Product/EditProductFab.svelte';
    import MobileProductActions from './Product/MobileProductActions.svelte';
    import ProductBreadcrumbs from './Product/ProductBreadcrumbs.svelte';
    import ProductCameraArModal from './Product/ProductCameraArModal.svelte';
    import ProductChatPanel from './Product/ProductChatPanel.svelte';
    import ProductDetails from './Product/ProductDetails.svelte';
    import ProductGallery from './Product/ProductGallery.svelte';
    import ProductGalleryLightbox from './Product/ProductGalleryLightbox.svelte';
    import ProductLocation from './Product/ProductLocation.svelte';
    import ProductMobileNavigation from './Product/ProductMobileNavigation.svelte';
    import ProductPricingAndPromotions from './Product/ProductPricingAndPromotions.svelte';
    import ProductPurchaseActions from './Product/ProductPurchaseActions.svelte';
    import ProductReviews from './Product/ProductReviews.svelte';
    import ProductSellerCard from './Product/ProductSellerCard.svelte';
    import ProductSpecificationsModal from './Product/ProductSpecificationsModal.svelte';
    import ProductSummary from './Product/ProductSummary.svelte';
    import ProductVariantDrawer from './Product/ProductVariantDrawer.svelte';
    import ProductVariantsAndQuantity from './Product/ProductVariantsAndQuantity.svelte';
    import RelatedProducts from './Product/RelatedProducts.svelte';
    import ReportReviewModal from './Product/ReportReviewModal.svelte';
    import ReviewMediaLightbox from './Product/ReviewMediaLightbox.svelte';

    let {
        product,
        relatedProducts = undefined,
        storeName = '',
        bundlingPromos = undefined,
        reviews = [] as any[],
        shippingInfo = {} as any,
    } = $props();

    let selectedVariantProduct = $state<any>(null);
    let showVariantModal = $state(false);
    let showFoodModifierModal = $state(false);
    let showSpecsModal = $state(false);
    let userHeight = $state('');
    let userWeight = $state('');

    const recommendedSize = $derived.by(() => {
        const h = Number(userHeight);
        const w = Number(userWeight);

        if (!h || !w || !product.size_chart?.rows) {
            return null;
        }

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
    const isSellerEnabled = $derived(
        (page.props as any).app_config?.is_seller_enabled ??
            (page.props as any).settings?.is_seller_enabled ??
            (page.props as any).is_seller_enabled ??
            false,
    );
    const showCheckoutSettings = $derived(
        Boolean(
            (page.props as any).app_config?.show_checkout_settings ??
            (page.props as any).settings?.show_checkout_settings ??
            (page.props as any).show_checkout_settings ??
            true,
        ),
    );
    const enable3dModels = $derived(
        (page.props as any).settings?.enable_3d_models ?? true,
    );

    const primary = $derived(
        (page.props as any).theme?.primary_color ?? '#0c4cb4',
    );
    const secondary = $derived(
        (page.props as any).theme?.secondary_color ?? '#fa7315',
    );
    const cartCount = $derived((page.props as any).cartCount || 0);

    function formatDate(dateStr: string | null | undefined): string {
        if (!dateStr) {
            return '-';
        }

        try {
            const d = new Date(dateStr);

            return d.toLocaleDateString('id-ID', {
                day: 'numeric',
                month: 'short',
                year: 'numeric',
            });
        } catch {
            return '-';
        }
    }

    function withOpacity(hex: string, opacity: number): string {
        if (!hex) {
            return '';
        }

        const trimmed = hex.trim();

        if (!trimmed.startsWith('#')) {
            return trimmed;
        }

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
        if (!path || typeof path !== 'string') {
            return '/noimage/image.png';
        }

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

                if (src) {
                    imgs.push(formatImagePath(src));
                }
            });
        }

        if (imgs.length === 0 && p.image) {
            imgs.push(formatImagePath(p.image));
        }

        return imgs;
    }

    const gallery = $derived(buildGallery(product));

    const parsedSpecifications = $derived.by(() => {
        if (!product.specifications) {
            return [];
        }

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
                    if (Array.isArray(item) && item.length >= 2) {
                        return [item[0], item[1]];
                    }

                    if (typeof item === 'object' && item !== null) {
                        const keys = Object.keys(item);

                        if (keys.length > 0) {
                            return [keys[0], item[keys[0]]];
                        }
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
    let isZoomed = $state(false);
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
        if (!reportReason.trim()) {
            return;
        }

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

        if (enable3dModels && product.model_3d_path) {
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

    let adClickTracked = false;
    $effect(() => {
        displayImage;
        mainImageHasError = false;

        if (!adClickTracked && (product?.is_promoted || product?.is_ad)) {
            adClickTracked = true;
            fetch('/api/ads/track-click', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as HTMLMetaElement
                        )?.content || '',
                },
                body: JSON.stringify({ product_id: product.id }),
            }).catch(() => {});
        }
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

        if (enable3dModels && product.model_3d_path) {
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

        if (!slide) {
            return;
        }

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

            if (dsIdx !== -1) {
                activeDesktopSlideIdx = dsIdx;
            }
        } else if (slide.type === 'video') {
            variantOverride = null;
            const dsIdx = desktopSlides.findIndex((ds) => ds.type === 'video');

            if (dsIdx !== -1) {
                activeDesktopSlideIdx = dsIdx;
            }
        } else if (slide.type === '3d') {
            variantOverride = null;
            const dsIdx = desktopSlides.findIndex((ds) => ds.type === '3d');

            if (dsIdx !== -1) {
                activeDesktopSlideIdx = dsIdx;
            }
        }
    }

    function handleSliderScroll(e: Event) {
        if (isScrollingFromButton) {
            return;
        }

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

        if (len === 0) {
            return;
        }

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
        if (!hasVariations) {
            return null;
        }

        const selectedIds = Object.values(selectedOptions).map(String);

        if (selectedIds.length < variationCount) {
            return null;
        }

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

        if (optImg) {
            variantOverride = optImg;
        }

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

        if (optId == null) {
            return null;
        }

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

            if (opt?.image) {
                return formatImagePath(opt.image);
            }
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
        activePromoRule
            ? qty >= activePromoRule.min_qty && !isPromoStockExhausted
            : false,
    );

    const derivedPromoPrice = $derived.by(() => {
        if (!activePromoRule) {
            return null;
        }

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

        if (!n) {
            return 'Hubungi Kami';
        }

        return 'Rp ' + n.toLocaleString('id-ID');
    }

    function formatPrice(val: any): string {
        const n = Number(val) || 0;

        return 'Rp ' + n.toLocaleString('id-ID');
    }

    // Dynamic requirements text (e.g. "Beli 1 pcs Laptop ASUS untuk dapat hadiah...")
    function getPromoRequirementsText(promo: any): string {
        const bundle = promo.settings?.bundle;

        if (!bundle || !bundle.buy_items) {
            return 'Beli produk bundling untuk dapat hadiah!';
        }

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

        if (!endStr) {
            return;
        }

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
            if (flashSaleInterval) {
                clearInterval(flashSaleInterval);
            }

            flashSaleInterval = null;
        }

        return () => {
            if (flashSaleInterval) {
                clearInterval(flashSaleInterval);
            }
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

    // True when the product cannot be purchased (no physical stock)
    const isEffectivelyOutOfStock = $derived(!isInStock);

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

    function openChat() {
        if (!user) {
            window.dispatchEvent(new CustomEvent('open-login-modal'));

            return;
        }

        if (window.innerWidth >= 768) {
            window.dispatchEvent(
                new CustomEvent('open-desktop-chat', {
                    detail: {
                        productId: product.id,
                        productName: product.name,
                        productImage:
                            product.image ||
                            (product.images?.[0]?.url ??
                                product.images?.[0]?.path),
                        productPrice:
                            product.price ?? product.product_price?.price ?? 0,
                    },
                }),
            );
        } else {
            chatOpen = true;
            attachMenuOpen = false;
        }
    }

    function openWhatsApp() {
        if (!user) {
            window.dispatchEvent(new CustomEvent('open-login-modal'));

            return;
        }

        const sellerObj = product.seller || product.user;
        const settings = (page.props.settings as any) || {};

        // Retrieve phone directly from database (product.contact_phone, seller phone_number, or DB settings store_whatsapp/store_phone)
        const dbPhone =
            product.contact_phone ||
            sellerObj?.phone_number ||
            sellerObj?.phone ||
            settings.store_whatsapp ||
            settings.store_phone;

        if (!dbPhone) {
            alert('Nomor telepon/WhatsApp belum diatur di database.');

            return;
        }

        let cleanPhone = String(dbPhone).replace(/\D/g, '');

        if (cleanPhone.startsWith('0')) {
            cleanPhone = '62' + cleanPhone.slice(1);
        } else if (!cleanPhone.startsWith('62') && cleanPhone.length > 5) {
            cleanPhone = '62' + cleanPhone;
        }

        const productUrl = window.location.href;
        const messageText = `Halo, saya ingin bertanya tentang produk *${product.name}*:\n${productUrl}`;
        const waUrl = `https://wa.me/${cleanPhone}?text=${encodeURIComponent(messageText)}`;

        window.open(waUrl, '_blank');
    }

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
        if (!currentChatId) {
            return;
        }

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
            if (!currentChatId) {
                return;
            }

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

        if (!text && !attachedProduct && !attachedImage) {
            return;
        }

        if (!currentChatId) {
            return;
        }

        try {
            const formData = new FormData();

            if (text) {
                formData.append('body', text);
            }

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
        if (qty > currentMinPurchase) {
            qty--;
        }
    }
    function incQty() {
        if (!currentIsUnlimited && qty >= currentStock) {
            return;
        }

        qty++;
    }
    function handleQtyInput(e: Event) {
        const target = e.target as HTMLInputElement;
        let val = parseInt(target.value);

        if (isNaN(val)) {
            return;
        }

        if (!currentIsUnlimited && currentStock > 0 && val > currentStock) {
            qty = currentStock;
            target.value = String(currentStock);
        } else {
            qty = val;
        }
    }
    function handleQtyBlur(e: Event) {
        const target = e.target as HTMLInputElement;
        let val = parseInt(target.value);

        if (isNaN(val) || val < currentMinPurchase) {
            qty = currentMinPurchase;
            target.value = String(currentMinPurchase);
        } else if (
            !currentIsUnlimited &&
            currentStock > 0 &&
            val > currentStock
        ) {
            qty = currentStock;
            target.value = String(currentStock);
        } else {
            qty = val;
            target.value = String(val);
        }
    }

    // ═══════════════════════════════════════
    //  WHATSAPP CTA
    // ═══════════════════════════════════════
    const waNumber = $derived(
        (page.props as any).settings?.store_whatsapp ?? '',
    );

    function openWhatsapp(action: 'chat' | 'buy' | 'cart' = 'buy') {
        if (!user) {
            window.dispatchEvent(new CustomEvent('open-login-modal'));

            return;
        }

        const parts: string[] = [];
        (product.variations ?? []).forEach((v: any) => {
            const label = getSelectedLabel(v);

            if (label) {
                parts.push(label);
            }
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

    const isAdmin = $derived.by(() => {
        return (
            user?.roles?.some(
                (r: any) => r.name === 'Super Admin' || r.name === 'Admin',
            ) ?? false
        );
    });

    const canEditProduct = $derived.by(() => {
        if (!user) {
            return false;
        }

        if (isAdmin) {
            return true;
        }

        if (user.is_seller) {
            const ownerId = product?.user_id || product?.seller?.id;

            return Boolean(ownerId && ownerId === user.id);
        }

        return false;
    });

    const isCheckoutWithoutLogin = $derived(
        Boolean(
            (page.props as any).app_config?.is_checkout_without_login ??
            (page.props as any).settings?.is_checkout_without_login ??
            (page.props as any).isCheckoutWithoutLogin ??
            false,
        ),
    );

    const isFoodPick = $derived(
        Boolean(
            (page.props as any).is_foodpick ||
            (page.props as any).isFoodPick ||
            (page.props as any).app_config?.is_foodpick ||
            product?.resto_profile ||
            product?.restoProfile ||
            product?.seller?.resto_profile ||
            product?.seller?.restoProfile ||
            (product?.menu_modifiers && product.menu_modifiers.length > 0) ||
            (product?.menuModifiers && product.menuModifiers.length > 0),
        ),
    );

    const productModifiers = $derived<any[]>(
        product?.menu_modifiers || product?.menuModifiers || [],
    );

    function addToCart() {
        if (!user && !isCheckoutWithoutLogin) {
            window.dispatchEvent(new CustomEvent('open-login-modal'));

            return;
        }

        if (isFoodPick) {
            showFoodModifierModal = true;

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
                preserveScroll: true,
                onSuccess: (page: any) => {
                    // Dispatch event so layout updates badge immediately
                    const newCartCount = page.props?.cartCount;
                    window.dispatchEvent(
                        new CustomEvent('cart-updated', {
                            detail:
                                newCartCount !== undefined
                                    ? { cartCount: newCartCount }
                                    : { delta: qty },
                        }),
                    );
                    showToast(
                        'Produk berhasil ditambahkan ke keranjang!',
                        'success',
                        'top',
                    );
                },
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
        if (!user && !isCheckoutWithoutLogin) {
            window.dispatchEvent(new CustomEvent('open-login-modal'));

            return;
        }

        if (isFoodPick) {
            showFoodModifierModal = true;

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

        if (!user && !isCheckoutWithoutLogin) {
            window.dispatchEvent(new CustomEvent('open-login-modal'));

            return;
        }

        if (isFoodPick) {
            showFoodModifierModal = true;

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

    let touchStartX = 0;
    let touchStartY = 0;

    function handleTouchStart(e: TouchEvent) {
        if (e.touches.length === 1) {
            touchStartX = e.touches[0].clientX;
            touchStartY = e.touches[0].clientY;
        }
    }

    function handleTouchEnd(e: TouchEvent) {
        if (isZoomed) {
            return;
        }

        if (e.changedTouches.length === 1 && gallery.length > 1) {
            const touchEndX = e.changedTouches[0].clientX;
            const diffX = touchEndX - touchStartX;
            const diffY = Math.abs(e.changedTouches[0].clientY - touchStartY);

            if (Math.abs(diffX) > 40 && diffY < 60) {
                if (diffX < 0) {
                    lightboxNext();
                } else {
                    lightboxPrev();
                }
            }
        }
    }

    /** Helper: check if a specific option's variant is out of stock */
    function isVariantSlideOutOfStock(slide: Slide): boolean {
        if (slide.type !== 'variant') {
            return false;
        }

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

<StorefrontLayout
    hideMobileHeader={true}
    hideMobileFooter={true}
    hideMobileBottomNav={true}
>
    <ProductMobileNavigation
        {goBack}
        {handleSearch}
        bind:searchQuery
        {showCheckoutSettings}
        {user}
        {cartCount}
        {secondary}
        {canEditProduct}
        {product}
        bind:mobileMenuOpen
        {shareProduct}
        {openWhatsapp}
        {isFoodPick}
    />

    <ProductBreadcrumbs {product} />

    <!-- ─────────────────────────────────────────────────────
     MAIN PRODUCT CARD
───────────────────────────────────────────────────── -->
    <div class="bg-white pt-9 md:pt-0">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-5 sm:py-8">
            <div
                class="grid grid-cols-1 md:grid-cols-[380px_1fr] lg:grid-cols-[420px_1fr] gap-x-5 gap-y-2 lg:gap-10 items-start"
            >
                <ProductGallery
                    {combinedSlides}
                    bind:sliderContainer
                    {handleSliderScroll}
                    {formatImagePath}
                    {secondary}
                    {openCameraModal}
                    {product}
                    bind:variantOverride
                    bind:isZoomed
                    bind:lightboxOpen
                    {sliderPrev}
                    {sliderNext}
                    {goToSlide}
                    {activeSlideIdx}
                    {isFlashSale}
                    {primary}
                    {flashSaleCountdown}
                    {fmt}
                    {activeUnitPrice}
                    {originalPrice}
                    {currentPrice}
                    {discountPercentage}
                    {isPromoActive}
                    {shouldKeepTierPricesDuringPromo}
                    {hasWholesalePrices}
                    {activePromoRule}
                    {isPromoStockExhausted}
                    {desktopSlides}
                    {activeDesktopSlideIdx}
                    bind:mainImageHasError
                    {displayImage}
                    {pickGallery}
                />

                <!-- ══ RIGHT: PRODUCT INFO ════════════════════════ -->
                <div class="flex flex-col gap-0 divide-y divide-slate-100">
                    <ProductSummary
                        {product}
                        {canEditProduct}
                        {shareProduct}
                        {reviews}
                        {isSellerEnabled}
                        {formatDate}
                    />
                    <ProductPricingAndPromotions
                        {isFlashSale}
                        {primary}
                        {secondary}
                        {flashSaleCountdown}
                        {activeUnitPrice}
                        {fmt}
                        {product}
                        {originalPrice}
                        {currentPrice}
                        {discountPercentage}
                        {activeTierPrices}
                        {activeTier}
                        {matchingVariant}
                        {isPromoActive}
                        {shouldKeepTierPricesDuringPromo}
                        {hasWholesalePrices}
                        {activePromoRule}
                        {isPromoStockExhausted}
                        {qty}
                        {bundlingPromos}
                        {getPromoRequirementsText}
                    />
                    <ProductLocation {shippingInfo} />

                    <ProductVariantsAndQuantity
                        {hasVariations}
                        {product}
                        {getSelectedLabel}
                        {primary}
                        {getOptionImage}
                        {isOptionAvailable}
                        {isSelected}
                        {selectOption}
                        {secondary}
                        {withOpacity}
                        {showCheckoutSettings}
                        {decQty}
                        {qty}
                        {currentMinPurchase}
                        {currentIsUnlimited}
                        {currentStock}
                        {handleQtyInput}
                        {handleQtyBlur}
                        {incQty}
                        {fullySelected}
                        {isInStock}
                        {fmt}
                        {totalPrice}
                        {isSplitPricing}
                        {promoStockRemaining}
                    />
                    <ProductPurchaseActions
                        {isFoodPick}
                        {productModifiers}
                        {formatPrice}
                        bind:showFoodModifierModal
                        {showCheckoutSettings}
                        {isEffectivelyOutOfStock}
                        {isPromoStockExhausted}
                        {hasVariations}
                        {fullySelected}
                        {showToast}
                        {addToCart}
                        {primary}
                        {secondary}
                        {withOpacity}
                        {buyNow}
                        {product}
                        {isSellerEnabled}
                    />
                </div>
                <!-- /RIGHT -->
            </div>
            <!-- /grid -->
        </div>
    </div>

    <div class="bg-white">
        <ProductSellerCard
            {product}
            {user}
            {isFoodPick}
            {openChat}
            {openWhatsApp}
        />

        <!-- ─────────────────────────────────────────────────────
     DESKRIPSI / PENGIRIMAN / ULASAN (STACKED VERTICALLY)
 ───────────────────────────────────────────────────── -->
        <div
            class="max-w-6xl mx-auto px-0 sm:px-6 lg:px-8 {isSellerEnabled
                ? 'pt-2 pb-6 flex flex-col gap-3 md:gap-4'
                : 'py-6 flex flex-col gap-4 md:gap-6'} w-full min-w-0 overflow-hidden"
        >
            <ProductDetails
                {parsedSpecifications}
                bind:showSpecsModal
                {primary}
                {secondary}
                {product}
                {withOpacity}
                bind:userHeight
                bind:userWeight
                {recommendedSize}
            />

            <ProductReviews
                {reviews}
                {primary}
                {secondary}
                {withOpacity}
                bind:reviewLightboxMedia
                bind:reviewLightboxOpen
                {openReportModal}
            />
        </div>

        <RelatedProducts
            {relatedProducts}
            {primary}
            {secondary}
            {product}
            {relImg}
            {isSellerEnabled}
            {cartButtonStyle}
            {handleDirectAddToCart}
            {fmt}
        />

        <ProductCameraArModal
            {isCameraModalOpen}
            {arModelPath}
            bind:cameraVideoEl
            {formatImagePath}
            {closeCameraModal}
        />

        <!-- ─────────────────────────────────────────────────────
     LIGHTBOX
───────────────────────────────────────────────────── -->
        <ProductGalleryLightbox
            bind:lightboxOpen
            bind:isZoomed
            bind:activeIdx
            bind:variantOverride
            {displayImage}
            {product}
            {gallery}
            {handleTouchStart}
            {handleTouchEnd}
            {lightboxNext}
            {lightboxPrev}
        />
    </div>
    <!-- /bg-white content wrapper -->

    <MobileProductActions
        bind:showFoodModifierModal
        bind:drawerAction
        bind:drawerOpen
        {isFoodPick}
        {showCheckoutSettings}
        {isEffectivelyOutOfStock}
        {isPromoStockExhausted}
        {hasVariations}
        {primary}
        {openChat}
        {openWhatsApp}
        {addToCart}
        {product}
    />
    <ProductVariantDrawer
        bind:drawerOpen
        {drawerAction}
        {displayImage}
        {product}
        {primary}
        {secondary}
        {activeUnitPrice}
        {hasVariations}
        {fullySelected}
        {isPromoActive}
        {shouldKeepTierPricesDuringPromo}
        {hasWholesalePrices}
        {matchingVariant}
        {activeTierPrices}
        {activeTier}
        {showCheckoutSettings}
        {currentIsUnlimited}
        {currentStock}
        {currentMinPurchase}
        {qty}
        {totalPrice}
        {isEffectivelyOutOfStock}
        {isPromoStockExhausted}
        {fmt}
        {getSelectedLabel}
        {isOptionAvailable}
        {getOptionImage}
        {isSelected}
        {selectOption}
        {withOpacity}
        {decQty}
        {incQty}
        {handleQtyInput}
        {handleQtyBlur}
        {buyNow}
        {addToCart}
    />
    <ProductChatPanel
        bind:chatOpen
        bind:attachedProduct
        bind:attachedImage
        bind:attachedImageUrl
        bind:chatInput
        bind:attachMenuOpen
        bind:productAttachOpen
        bind:productSearchQuery
        bind:chatPreviewUrl
        {isFoodPick}
        {primary}
        {storeName}
        {chatMessages}
        {quickReplies}
        {product}
        {relatedProducts}
        {formatImagePath}
        {quickReply}
        {sendChat}
        {triggerImageUpload}
    />
    <VariantSelectorModal
        product={selectedVariantProduct}
        show={showVariantModal}
        onClose={() => (showVariantModal = false)}
        {primary}
        {secondary}
        {user}
    />

    <FoodModifierModal
        {product}
        selectedVariantId={matchingVariant ? matchingVariant.id : null}
        show={showFoodModifierModal}
        onClose={() => (showFoodModifierModal = false)}
        {primary}
        {secondary}
    />

    <ProductSpecificationsModal
        bind:showSpecsModal
        {parsedSpecifications}
        {primary}
        {secondary}
    />
    <ReviewMediaLightbox bind:reviewLightboxOpen {reviewLightboxMedia} />
    <ReportReviewModal
        bind:showReportModal
        bind:reportReason
        {reportingReview}
        {primary}
        {submitReport}
        {submittingReport}
    />
    <EditProductFab
        {canEditProduct}
        {product}
        {primary}
        {secondary}
        {isAdmin}
    />
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
    :global(.prose h1),
    :global(.prose h2),
    :global(.prose h3) {
        font-weight: 700;
        margin-bottom: 0.5rem;
        color: #1e293b;
    }
    :global(.prose p) {
        margin-bottom: 0.75rem;
    }
    :global(.prose ul),
    :global(.prose ol) {
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

    /* Fix inline styles (background and text color) in dark mode inside product description */
    :global(.dark .prose [style*='background-color']),
    :global(.sf-dark .prose [style*='background-color']) {
        background-color: transparent !important;
    }
    :global(.dark .prose [style*='color']),
    :global(.sf-dark .prose [style*='color']) {
        color: inherit !important;
    }

    /* Ensure text colors inside prose are visible in dark mode */
    :global(.dark .prose),
    :global(.sf-dark .prose) {
        color: #cbd5e1 !important; /* slate-300 */
    }
    :global(.dark .prose h1),
    :global(.dark .prose h2),
    :global(.dark .prose h3),
    :global(.dark .prose strong),
    :global(.sf-dark .prose h1),
    :global(.sf-dark .prose h2),
    :global(.sf-dark .prose h3),
    :global(.sf-dark .prose strong) {
        color: #f8fafc !important; /* slate-50 */
    }
</style>
