<script lang="ts">
    import StorefrontLayout from '@/components/layouts/StorefrontLayout.svelte';
    import { usePage, Link, router } from '@inertiajs/svelte';
    import { fade } from 'svelte/transition';
    import { showToast } from '@/utils/toast';

    let { product, relatedProducts = [], storeName = '', bundlingPromos = [] } = $props();

    const page = usePage();

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
            showToast('Tautan produk berhasil disalin ke papan klip!', 'success', 'top');
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

    const activePromoRule = $derived(
        matchingVariant?.promo_rule ?? product.promo_rule ?? null
    );

    const isPromoRuleSatisfied = $derived(
        activePromoRule ? (qty >= activePromoRule.min_qty) : false
    );

    const derivedPromoPrice = $derived.by(() => {
        if (!activePromoRule) return null;
        if (activePromoRule.promo_price !== null && activePromoRule.promo_price !== undefined) {
            return Number(activePromoRule.promo_price);
        }
        const normalPrice = matchingVariant 
            ? Number(matchingVariant.product_price?.price ?? basePrice)
            : Number(basePrice);
            
        if (activePromoRule.discount_type === 'percentage') {
            return normalPrice - (normalPrice * (Number(activePromoRule.discount_value) / 100));
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
                return Number(matchingVariant.product_price?.price ?? basePrice);
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
                const normal = Number(matchingVariant.product_price?.price ?? basePrice);
                if (normal > 0) {
                    return Math.round(((normal - derivedPromoPrice) / normal) * 100);
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
                    return Math.round(((normal - derivedPromoPrice) / normal) * 100);
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
            ? (matchingVariant.is_promo || isPromoRuleSatisfied) 
            : (product.is_promo || isPromoRuleSatisfied),
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
        if (!bundle || !bundle.buy_items) return 'Beli produk bundling untuk dapat hadiah!';
        
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
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    subject: product.name,
                    product_id: product.id,
                })
            });

            if (response.ok) {
                const data = await response.json();
                currentChatId = data.id;

                attachedProduct = {
                    id: product.id,
                    name: product.name,
                    image: product.image || (product.images?.[0]?.url ?? product.images?.[0]?.path),
                    price: product.price ?? product.product_price?.price ?? 0
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
                headers: { 'Accept': 'application/json' }
            });
            if (response.ok) {
                const data = await response.json();
                chatMessages = Array.isArray(data) ? data : (data.messages || []);
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
            const lastMessageId = chatMessages.length > 0 ? chatMessages[chatMessages.length - 1].id : 0;
            try {
                const response = await fetch(`/chats/${currentChatId}/messages?after_id=${lastMessageId}`, {
                    headers: { 'Accept': 'application/json' }
                });
                if (response.ok) {
                    const data = await response.json();
                    const newMsgs = Array.isArray(data) ? data : (data.messages || []);
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
                formData.append('attachment_data', JSON.stringify(attachedProduct));
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
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                    'Accept': 'application/json',
                },
                body: formData
            });

            if (response.ok) {
                const msg = await response.json();
                if (Array.isArray(chatMessages)) {
                    if (!chatMessages.some(m => m.id === msg.id)) {
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

    const quickReplies = ['Hai, barang ini masih ada?', 'Bisa dikirim ke luar kota?', 'Terima kasih!'];

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

        router.post('/cart', {
            product_id: product.id,
            product_variant_id: matchingVariant ? matchingVariant.id : null,
            quantity: qty,
        }, {
            onSuccess: () => {
                showToast('Produk berhasil ditambahkan ke keranjang!', 'success', 'top');
            },
            onError: (errors: any) => {
                showToast('Gagal menambahkan produk ke keranjang', 'error', 'top');
            }
        });
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

        router.post('/cart', {
            product_id: product.id,
            product_variant_id: matchingVariant ? matchingVariant.id : null,
            quantity: qty,
        }, {
            onSuccess: () => {
                router.visit('/cart');
            },
            onError: (errors: any) => {
                showToast('Gagal memproses pembelian', 'error', 'top');
            }
        });
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
        if (window.history.length > 1 && document.referrer && document.referrer.includes(window.location.host)) {
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
                <button
                    onclick={() => {
                        if (user) {
                            router.visit('/cart');
                        } else {
                            window.dispatchEvent(new CustomEvent('open-login-modal'));
                        }
                    }}
                    class="w-8 h-8 flex items-center justify-center text-slate-600 hover:bg-slate-100 rounded-full transition cursor-pointer"
                    aria-label="Keranjang"
                >
                    <i class="ti ti-shopping-cart text-lg"></i>
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
                                <i
                                    class="ti ti-gift text-blue-600 text-base"
                                ></i>
                            </div>
                            <div class="flex-grow text-[11px] leading-relaxed">
                                <span class="font-bold text-blue-950 block text-xs">Promo Beli Banyak Lebih Hemat!</span>
                                <p class="text-blue-800 mt-0.5">
                                    Beli minimal <strong class="text-blue-950">{activePromoRule.min_qty} pcs</strong> untuk mendapatkan harga 
                                    <strong class="text-blue-950">
                                        {#if activePromoRule.promo_price}
                                            {fmt(activePromoRule.promo_price)}
                                        {:else if activePromoRule.discount_type === 'percentage'}
                                            Diskon {Number(activePromoRule.discount_value)}%
                                        {:else}
                                            Potongan {fmt(activePromoRule.discount_value)}
                                        {/if}
                                    </strong> per produk!
                                    {#if activePromoRule.remaining_promo_stock !== null && activePromoRule.remaining_promo_stock !== undefined}
                                        <span class="block mt-1.5 font-black text-orange-600 bg-orange-50/80 px-2 py-0.5 rounded-md border border-orange-200/80 w-fit text-[9px] uppercase tracking-wide">
                                            Sisa Stok Promo: {activePromoRule.remaining_promo_stock} pcs
                                        </span>
                                    {/if}
                                </p>
                            </div>
                        </div>
                    {/if}

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
                </div>

                <!-- ══ RIGHT: PRODUCT INFO ════════════════════════ -->
                <div class="flex flex-col gap-0 divide-y divide-slate-100">
                    <!-- Header: brand + name + rating/terjual -->
                    <div class="pb-4 flex flex-col">
                        {#if product.brand}
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
                            <div class="flex items-center gap-1.5 mb-2 text-xs font-bold text-slate-700">
                                <i class="ti ti-tags text-brand-blueRoyal text-sm"></i>
                                <span>Daftar Harga Grosir</span>
                            </div>
                            <div class="w-full overflow-hidden border border-slate-100 rounded-xl bg-white shadow-xs">
                                <table class="w-full text-left border-collapse text-xs">
                                    <thead>
                                        <tr class="bg-slate-50/65 border-b border-slate-100 text-[10px] text-slate-400 font-bold uppercase tracking-wider">
                                            <th class="py-2.5 px-4 font-semibold">Min. Pembelian</th>
                                            <th class="py-2.5 px-4 font-semibold text-right">Harga Satuan</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-50">
                                        {#each activeTierPrices as tier}
                                            {@const isActive = (!hasVariations || fullySelected) && activeTier && Number(activeTier.min_qty) === Number(tier.min_qty)}
                                            <tr class="transition duration-150 {isActive ? 'bg-brand-blueRoyal/[0.02]' : ''}">
                                                <td class="py-3 px-4 font-semibold text-slate-700">
                                                    {tier.min_qty}+ pcs
                                                </td>
                                                <td class="py-3 px-4 font-black text-slate-800 text-right">
                                                    {fmt(tier.price)} <span class="text-[10px] text-slate-400 font-normal">/pc</span>
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
                                <i
                                    class="ti ti-gift text-blue-600 text-base"
                                ></i>
                            </div>
                            <div>
                                <h4 class="font-outfit font-black text-xs text-blue-800 uppercase tracking-wider">
                                    Promo Spesial Beli Banyak Lebih Hemat!
                                </h4>
                                <p class="text-[11px] text-blue-650 font-bold mt-0.5 leading-relaxed">
                                    Beli minimal <strong>{activePromoRule.min_qty} pcs</strong> untuk mendapatkan potongan harga menjadi 
                                    <strong class="text-blue-900 font-extrabold">
                                        {#if activePromoRule.promo_price}
                                            {fmt(activePromoRule.promo_price)}
                                        {:else if activePromoRule.discount_type === 'percentage'}
                                            Diskon {Number(activePromoRule.discount_value)}%
                                        {:else}
                                            Potongan {fmt(activePromoRule.discount_value)}
                                        {/if}
                                    </strong> per produk!
                                </p>
                                {#if activePromoRule.remaining_promo_stock !== null && activePromoRule.remaining_promo_stock !== undefined}
                                    <div class="mt-2 text-[10px] text-orange-700 bg-orange-50 border border-orange-200 px-2.5 py-1 rounded-lg inline-block font-extrabold uppercase tracking-wide">
                                        Sisa Stok Promo: {activePromoRule.remaining_promo_stock} pcs
                                    </div>
                                {/if}
                                {#if qty < activePromoRule.min_qty}
                                    <div class="mt-2 text-[10px] text-amber-700 bg-amber-50 border border-amber-200 px-2.5 py-1 rounded-lg inline-block font-semibold">
                                        Tambah {activePromoRule.min_qty - qty} pcs lagi untuk mengaktifkan promo ini!
                                    </div>
                                {:else}
                                    <div class="mt-2 text-[10px] text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-1 rounded-lg inline-block font-bold">
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
                            <div class="my-4 p-4 rounded-3xl bg-blue-50/40 border border-blue-100/70 space-y-3.5 shadow-sm text-xs animate-in fade-in slide-in-from-top-1 duration-200">
                                <!-- Title Header -->
                                <div class="flex items-center gap-2 font-bold text-slate-800">
                                    <span class="w-6 h-6 rounded-full bg-brand-blueRoyal text-white flex items-center justify-center text-xs shadow-md animate-pulse shrink-0">
                                        <i class="ti ti-gift"></i>
                                    </span>
                                    <div>
                                        <span class="text-brand-blueRoyal font-extrabold uppercase tracking-wider text-[10px] block leading-none mb-0.5">Promo Bundling</span>
                                        <span class="text-slate-850 font-black text-sm">{promo.name}</span>
                                    </div>
                                </div>

                                <p class="text-slate-600 font-semibold leading-relaxed">
                                    <strong>{getPromoRequirementsText(promo)}</strong>
                                </p>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 pt-1.5 border-t border-slate-100">
                                    <!-- Buy Checklist -->
                                    <div class="space-y-2">
                                        <span class="text-[9.5px] text-slate-450 font-black uppercase tracking-wider block">Produk Yang Harus Dibeli:</span>
                                        <div class="space-y-2">
                                            {#each bundle.buy_items as buyItem}
                                                {@const isCurrent = Number(buyItem.product_id) === Number(product.id)}
                                                <div class="flex items-center gap-2.5 p-2 rounded-2xl bg-white border border-slate-100 shadow-3xs hover:border-slate-200 transition duration-150 relative">
                                                    <img
                                                        src={buyItem.product_image ? (buyItem.product_image.startsWith('http') || buyItem.product_image.startsWith('/') ? buyItem.product_image : '/' + buyItem.product_image) : '/noimage/image.png'}
                                                        alt={buyItem.product_name}
                                                        class="w-9 h-9 rounded-lg object-cover bg-slate-50 border border-slate-100 shrink-0"
                                                        onerror={(e) => { e.currentTarget.src = '/noimage/image.png'; }}
                                                    />
                                                    <div class="min-w-0 flex-grow pr-1">
                                                        {#if isCurrent}
                                                            <p class="font-black text-brand-blueRoyal truncate text-[10.5px] flex items-center gap-1">
                                                                {buyItem.product_name || 'Produk Ini'}
                                                                <span class="text-[8px] bg-brand-blueLight text-brand-blueRoyal px-1.5 py-0.25 rounded-md uppercase font-black tracking-wider">Produk Ini</span>
                                                            </p>
                                                        {:else}
                                                            <a
                                                                href={`/products/${buyItem.product_slug}`}
                                                                class="font-bold text-slate-800 hover:text-brand-blueRoyal hover:underline truncate text-[10.5px] block"
                                                            >
                                                                {buyItem.product_name || 'Produk Lain'}
                                                            </a>
                                                        {/if}
                                                        <p class="text-[9px] font-bold text-slate-400">
                                                            Min. Beli: {buyItem.qty} pcs
                                                        </p>
                                                    </div>
                                                </div>
                                            {/each}
                                        </div>
                                    </div>

                                    <!-- Get Gifts List (Image 3 Style) -->
                                    <div class="space-y-2">
                                        <span class="text-[9.5px] text-slate-450 font-black uppercase tracking-wider block">Hadiah Bonus Yang Didapat:</span>
                                        <div class="space-y-2">
                                            {#each bundle.get_items as gift}
                                                <div class="flex gap-2.5 p-2 rounded-2xl bg-white border border-slate-100 shadow-3xs relative">
                                                    <img
                                                        src={gift.product_image ? (gift.product_image.startsWith('http') || gift.product_image.startsWith('/') ? gift.product_image : '/' + gift.product_image) : '/noimage/image.png'}
                                                        alt={gift.product_name}
                                                        class="w-12 h-12 rounded-lg object-cover bg-slate-50 border border-slate-100 shrink-0"
                                                        onerror={(e) => { e.currentTarget.src = '/noimage/image.png'; }}
                                                    />
                                                    <div class="min-w-0 flex-grow flex flex-col justify-center">
                                                        <div>
                                                            <span class="bg-emerald-500 text-white text-[8px] font-black px-1.5 py-0.5 rounded-md uppercase tracking-wider mb-0.5 inline-block">
                                                                Hadiah
                                                            </span>
                                                        </div>
                                                        <p class="font-extrabold text-slate-800 truncate text-[10.5px] leading-tight mb-0.5">
                                                            {gift.product_name || 'Hadiah Bonus'}
                                                        </p>
                                                        <p class="text-[9px] font-bold text-slate-400 mb-1 leading-none">
                                                            Isi {gift.qty} pcs
                                                        </p>
                                                        <div class="flex items-center flex-wrap gap-1.5 text-[9px] leading-none">
                                                            {#if gift.discount_type === 'free'}
                                                                <span class="bg-red-50 text-red-500 text-[8px] font-black px-1 py-0.25 rounded border border-red-100 leading-none">
                                                                    100%
                                                                </span>
                                                                <span class="text-[9px] text-slate-400 line-through font-bold font-mono">
                                                                    {fmt(gift.product_price)}
                                                                </span>
                                                                <span class="text-red-500 font-black tracking-wide uppercase">
                                                                    GRATIS
                                                                </span>
                                                            {:else if gift.discount_type === 'percentage'}
                                                                <span class="bg-orange-50 text-orange-600 text-[8px] font-black px-1 py-0.25 rounded border border-orange-100 leading-none">
                                                                    {Number(gift.discount_value)}%
                                                                </span>
                                                                <span class="text-[9px] text-slate-400 line-through font-bold font-mono">
                                                                    {fmt(gift.product_price)}
                                                                </span>
                                                                <span class="text-orange-600 font-black tracking-wide">
                                                                    {fmt(gift.product_price * (1 - gift.discount_value / 100))}
                                                                </span>
                                                            {:else}
                                                                <span class="bg-orange-50 text-orange-600 text-[8px] font-black px-1 py-0.25 rounded border border-orange-100 leading-none">
                                                                    POTONGAN
                                                                </span>
                                                                <span class="text-[9px] text-slate-400 line-through font-bold font-mono">
                                                                    {fmt(gift.product_price)}
                                                                </span>
                                                                <span class="text-orange-600 font-black tracking-wide font-mono">
                                                                    {fmt(gift.product_price - gift.discount_value)}
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
                            >Pengiriman</span
                        >
                        <div
                            class="flex items-start gap-1.5 text-xs text-slate-700"
                        >
                            <i class="ti ti-truck text-green-500 text-sm mt-0.5"
                            ></i>
                            <div
                                class="flex flex-wrap items-center gap-x-2 gap-y-0.5"
                            >
                                <span class="font-bold text-slate-800">
                                    Garansi tiba 1–3 hari kerja
                                </span>
                                <span
                                    class="text-[11px] text-slate-400 font-normal"
                                >
                                    (JNE · J&T · SiCepat · Gosend · Grab)
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
                                    class="ti ti-shield-check text-green-500 text-xs"
                                ></i> Bebas Pengembalian
                            </span>
                            <span class="flex items-center gap-1">
                                <i class="ti ti-cash text-orange-400 text-xs"
                                ></i> COD Tersedia
                            </span>
                            <span class="flex items-center gap-1">
                                <i
                                    class="ti ti-rosette-discount-check text-blue-500 text-xs"
                                ></i> Produk Original
                            </span>
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

                    <!-- ── CTA BUTTONS ─────────────────────────── -->
                    <div class="pt-5 hidden md:flex flex-col sm:flex-row gap-3">
                        <button
                            onclick={() => {
                                if (hasVariations && !fullySelected) {
                                    showToast('Pilih variasi terlebih dahulu', 'error', 'top');
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
                        <button
                            onclick={() => {
                                if (window.innerWidth < 768) {
                                    chatOpen = true;
                                    attachMenuOpen = false;
                                } else {
                                    if (user) {
                                        window.dispatchEvent(new CustomEvent('open-desktop-chat', {
                                            detail: {
                                                productId: product.id,
                                                productName: product.name
                                            }
                                        }));
                                    } else {
                                        window.dispatchEvent(new CustomEvent('open-login-modal'));
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

    <!-- Sticky Bottom Bar (Mobile Only) -->
    <div
        class="fixed bottom-0 left-0 right-0 z-50 bg-white border-t border-slate-100 px-4 pt-2.5 pb-2.5 flex items-center gap-3 md:hidden shadow-[0_-4px_16px_rgba(0,0,0,0.06)]"
        style="padding-bottom: calc(0.625rem + env(safe-area-inset-bottom, 0px));"
    >
        <!-- Chat Button -->
        <button
            onclick={() => { chatOpen = true; attachMenuOpen = false; }}
            class="w-12 h-12 flex items-center justify-center rounded-xl border border-slate-200 text-slate-700 active:bg-slate-50 transition shrink-0"
            aria-label="Chat Penjual"
        >
            <i class="ti ti-message-dots text-xl"></i>
        </button>

        <!-- Beli Langsung (Outline style) -->
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
    </div>

    <!-- Bottom Drawer for Varian Produk (Mobile Only) -->
    {#if drawerOpen}
        <!-- Backdrop -->
        <div
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
                        <div class="flex items-center gap-1.5 mb-2 text-xs font-bold text-slate-700">
                            <i class="ti ti-tags text-brand-blueRoyal text-sm"></i>
                            <span>Daftar Harga Grosir</span>
                        </div>
                        <div class="w-full overflow-hidden border border-slate-100 rounded-xl bg-white shadow-xs">
                            <table class="w-full text-left border-collapse text-[11px]">
                                <thead>
                                    <tr class="bg-slate-50/65 border-b border-slate-100 text-[10px] text-slate-400 font-bold uppercase tracking-wider">
                                        <th class="py-2.5 px-3 font-semibold">Min. Pembelian</th>
                                        <th class="py-2.5 px-3 font-semibold text-right">Harga Satuan</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50">
                                    {#each activeTierPrices as tier}
                                        {@const isActive = (!hasVariations || fullySelected) && activeTier && Number(activeTier.min_qty) === Number(tier.min_qty)}
                                        <tr class="transition duration-150 {isActive ? 'bg-brand-blueRoyal/[0.02]' : ''}">
                                            <td class="py-2.5 px-3 font-semibold text-slate-700">
                                                {tier.min_qty}+ pcs
                                            </td>
                                            <td class="py-2.5 px-3 font-black text-slate-800 text-right">
                                                {fmt(tier.price)} <span class="text-[9px] text-slate-400 font-normal">/pc</span>
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
                {#if drawerAction === 'buy'}
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
            <div class="bg-white flex items-center gap-3 px-4 py-3 border-b border-slate-100 shadow-sm shrink-0">
                <button
                    onclick={() => { chatOpen = false; attachMenuOpen = false; productAttachOpen = false; }}
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
                    <p class="font-outfit font-black text-sm text-slate-800 truncate">{storeName}</p>
                    <p class="text-[10px] text-emerald-500 font-bold flex items-center gap-1">
                        <span class="w-1.5 h-1.5 bg-emerald-400 rounded-full inline-block"></span>
                        Online
                    </p>
                </div>
                <button class="w-9 h-9 flex items-center justify-center rounded-full hover:bg-slate-100 transition" aria-label="Opsi">
                    <i class="ti ti-dots-vertical text-lg text-slate-500"></i>
                </button>
            </div>

            <!-- ── Chat Body ── -->
            <div class="flex-1 overflow-y-auto px-4 py-4 space-y-3 relative chat-body-container">

                <!-- Fraud warning banner -->
                <div class="bg-amber-50 border border-amber-100 rounded-2xl px-4 py-3 text-xs text-amber-800 leading-relaxed text-center">
                    ⚠️ Hati-hati penipuan! Jangan bertransaksi di luar platform dan jangan berikan data pribadi kepada penjual.
                </div>  

                <!-- Messages -->
                {#each chatMessages as msg (msg.id)}
                    <div class="flex flex-col {msg.sender_type === 'user' ? 'items-end' : 'items-start'} gap-1">

                        {#if msg.attachment_type === 'product' && msg.attachment_data}
                            <!-- Product card bubble -->
                            <div
                                class="max-w-[75%] rounded-2xl overflow-hidden border shadow-sm {msg.sender_type === 'user' ? 'rounded-tr-sm' : 'rounded-tl-sm'}"
                                style="background-color: {msg.sender_type === 'user' ? primary : 'white'};"
                            >
                                <div class="flex items-center gap-2.5 p-3">
                                    <img
                                        src={formatImagePath(msg.attachment_data.image)}
                                        alt={msg.attachment_data.name}
                                        class="w-12 h-12 rounded-xl object-cover shrink-0 bg-slate-100"
                                        onerror={(e: any) => { e.target.src = '/noimage/image.png'; }}
                                    />
                                    <div class="min-w-0">
                                        <p class="text-xs font-bold truncate {msg.sender_type === 'user' ? 'text-white' : 'text-slate-800'}">{msg.attachment_data.name}</p>
                                        <p class="text-xs mt-0.5 font-black {msg.sender_type === 'user' ? 'text-white/90' : 'text-orange-500'}">
                                            Rp{Number(msg.attachment_data.price ?? 0).toLocaleString('id-ID')}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        {/if}

                        {#if msg.attachment_type === 'image' && msg.attachment_data?.url}
                            <!-- Image card bubble -->
                            <div
                                class="max-w-[75%] rounded-2xl overflow-hidden border shadow-sm {msg.sender_type === 'user' ? 'rounded-tr-sm' : 'rounded-tl-sm'}"
                            >
                                <img
                                    src={msg.attachment_data.url}
                                    alt="Sent image"
                                    class="max-w-full max-h-60 object-contain bg-slate-100 cursor-pointer rounded-xl"
                                    onclick={() => window.open(msg.attachment_data.url, '_blank')}
                                />
                            </div>
                        {/if}

                        {#if msg.body}
                            <div
                                class="max-w-[75%] px-4 py-2.5 rounded-2xl text-sm leading-relaxed shadow-sm {msg.sender_type === 'user' ? 'rounded-tr-sm text-white' : 'rounded-tl-sm text-slate-800 bg-white'}"
                                style="background-color: {msg.sender_type === 'user' ? primary : 'white'};"
                            >
                                {msg.body}
                            </div>
                        {/if}

                        <span class="text-[10px] text-slate-400 px-1">{msg.time}</span>
                    </div>
                {/each}
            </div>

            <!-- ── Quick Replies ── -->
            <div class="flex gap-2 px-4 pb-2 overflow-x-auto shrink-0 no-scrollbar">
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
                    <div class="flex items-center gap-2.5 bg-white border border-slate-200 rounded-2xl px-3 py-2 shadow-sm">
                        <img
                            src={formatImagePath(attachedProduct.image)}
                            alt={attachedProduct.name}
                            class="w-10 h-10 rounded-lg object-cover bg-slate-100 shrink-0"
                            onerror={(e: any) => { e.target.src = '/noimage/image.png'; }}
                        />
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-bold text-slate-800 truncate">{attachedProduct.name}</p>
                            <p class="text-xs font-black text-orange-500">
                                Rp{Number(attachedProduct.price ?? 0).toLocaleString('id-ID')}
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
                    <div class="relative inline-block bg-white border border-slate-200 rounded-2xl p-2 shadow-sm">
                        <img
                            src={attachedImageUrl}
                            alt="Preview"
                            class="w-20 h-20 rounded-xl object-cover"
                        />
                        <button
                            onclick={() => { attachedImage = null; attachedImageUrl = null; }}
                            class="absolute -top-1.5 -right-1.5 bg-rose-500 text-white hover:bg-rose-600 rounded-full w-5 h-5 flex items-center justify-center shadow"
                            aria-label="Hapus gambar"
                        >
                            <i class="ti ti-x text-xs"></i>
                        </button>
                    </div>
                </div>
            {/if}

            <!-- ── Input Bar ── -->
            <div class="bg-white border-t border-slate-100 px-4 pt-3 pb-3 shrink-0">
                <div class="flex items-center gap-2">
                    <!-- Emoji placeholder -->
                    <button class="text-slate-400 hover:text-slate-600 w-9 h-9 flex items-center justify-center rounded-full transition" aria-label="Emoji">
                        <i class="ti ti-mood-smile text-xl"></i>
                    </button>

                    <!-- Text input -->
                    <input
                        type="text"
                        bind:value={chatInput}
                        onkeydown={(e: KeyboardEvent) => { if (e.key === 'Enter') sendChat(); }}
                        placeholder="Tulis pesan..."
                        class="flex-1 bg-slate-100 rounded-full px-4 py-2.5 text-sm focus:outline-none focus:bg-white focus:ring-1 focus:ring-slate-300 transition"
                    />

                    <!-- Attach (+) -->
                    <button
                        onclick={() => { attachMenuOpen = !attachMenuOpen; productAttachOpen = false; }}
                        class="text-slate-400 hover:text-slate-600 w-9 h-9 flex items-center justify-center rounded-full transition {attachMenuOpen ? 'bg-slate-100' : ''}"
                        aria-label="Lampirkan"
                    >
                        <i class="ti ti-plus text-xl"></i>
                    </button>

                    <!-- Send -->
                    <button
                        onclick={sendChat}
                        disabled={!chatInput.trim() && !attachedProduct && !attachedImage}
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
                <div class="bg-white border-t border-slate-100 px-6 py-5 shrink-0 animate-slide-up">
                    <div class="flex items-start gap-8">
                        <!-- Produk -->
                        <button
                            onclick={() => { productAttachOpen = true; attachMenuOpen = false; productSearchQuery = ''; }}
                            class="flex flex-col items-center gap-2 cursor-pointer"
                        >
                            <div class="w-14 h-14 rounded-full flex items-center justify-center shadow-md"
                                style="background: linear-gradient(135deg, #3b9ef0, #1d6ee6);">
                                <i class="ti ti-tag text-white text-2xl"></i>
                            </div>
                            <span class="text-xs font-bold text-slate-700">Produk</span>
                        </button>

                        <!-- Gambar -->
                        <button 
                            onclick={triggerImageUpload}
                            class="flex flex-col items-center gap-2 cursor-pointer"
                        >
                            <div class="w-14 h-14 rounded-full flex items-center justify-center shadow-md"
                                style="background: linear-gradient(135deg, #f5a623, #e8891d);">
                                <i class="ti ti-photo text-white text-2xl"></i>
                            </div>
                            <span class="text-xs font-bold text-slate-700">Gambar</span>
                        </button>

                        <!-- Invoice -->
                        <button class="flex flex-col items-center gap-2 opacity-50 cursor-not-allowed" disabled>
                            <div class="w-14 h-14 rounded-full flex items-center justify-center shadow-md"
                                style="background: linear-gradient(135deg, #9b5de5, #7b2fbe);">
                                <i class="ti ti-receipt text-white text-2xl"></i>
                            </div>
                            <span class="text-xs font-bold text-slate-700">Invoice</span>
                        </button>
                    </div>
                </div>
            {/if}
        </div>

        <!-- ── Product Attach Modal ── -->
        {#if productAttachOpen}
            <div class="fixed inset-0 z-[210] flex flex-col bg-white md:hidden animate-slide-up">
                <!-- Header -->
                <div class="flex items-center gap-3 px-4 py-4 border-b border-slate-100 shrink-0">
                    <button
                        onclick={() => { productAttachOpen = false; attachMenuOpen = true; }}
                        class="w-9 h-9 flex items-center justify-center hover:bg-slate-100 rounded-full transition"
                        aria-label="Tutup"
                    >
                        <i class="ti ti-x text-lg text-slate-700"></i>
                    </button>
                    <h2 class="font-outfit font-black text-base text-slate-800">Lampirkan Barang</h2>
                </div>

                <!-- Search bar -->
                <div class="px-4 py-3 border-b border-slate-100 shrink-0">
                    <div class="relative">
                        <i class="ti ti-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
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
                    {#each [product, ...(relatedProducts ?? [])].filter((p: any) =>
                        !productSearchQuery || p.name?.toLowerCase().includes(productSearchQuery.toLowerCase())
                    ).slice(0, 20) as p (p.id ?? p.name)}
                        <button
                            onclick={() => {
                                attachedProduct = { name: p.name, image: p.image || (p.images?.[0]?.url ?? p.images?.[0]?.path), price: p.price ?? p.product_price?.price ?? 0 };
                                productAttachOpen = false;
                                attachMenuOpen = false;
                            }}
                            class="w-full flex items-center gap-3 px-4 py-3 hover:bg-slate-50 transition text-left"
                        >
                            <img
                                src={formatImagePath(p.image || (p.images?.[0]?.url ?? p.images?.[0]?.path) || null)}
                                alt={p.name}
                                class="w-14 h-14 rounded-2xl object-cover bg-slate-100 shrink-0"
                                onerror={(e: any) => { e.target.src = '/noimage/image.png'; }}
                            />
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-bold text-slate-800 line-clamp-2 leading-tight">{p.name}</p>
                                <p class="text-sm font-black text-orange-500 mt-1">
                                    Rp{Number(p.price ?? p.product_price?.price ?? 0).toLocaleString('id-ID')}
                                </p>
                            </div>
                        </button>
                    {:else}
                        <div class="py-16 text-center text-slate-400">
                            <i class="ti ti-search text-3xl mb-2"></i>
                            <p class="text-sm font-bold">Produk tidak ditemukan</p>
                        </div>
                    {/each}
                </div>
            </div>
        {/if}
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
