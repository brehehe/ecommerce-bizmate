<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { fade, fly } from 'svelte/transition';
    import { cubicOut } from 'svelte/easing';
    import { showToast } from '@/utils/toast';

    let {
        product = null as any,
        show = false,
        onClose = () => {},
        onAdded = () => {},
        primary = '#0c4cb4',
        secondary = '#fa7315',
        user = null as any
    } = $props();

    let selectedOptions: Record<string, number> = $state({});
    let qty = $state(1);
    let processing = $state(false);

    // Reset when product changes or modal opens
    $effect(() => {
        if (show && product) {
            selectedOptions = {};
            qty = currentMinPurchase;
            processing = false;
        }
    });

    const hasVariations = $derived(
        product &&
        (product.variations?.length ?? 0) > 0 &&
        (product.variants?.length ?? 0) > 0,
    );

    const variationCount = $derived(product?.variations?.length ?? 0);
    const fullySelected = $derived(
        Object.keys(selectedOptions).length >= variationCount,
    );

    function findVariant(): any | null {
        if (!hasVariations || !product) return null;
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
        qty = currentMinPurchase; // reset qty to min purchase of variant
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

    function getVariantForOption(optionId: number): any | null {
        if (!product) return null;
        return (
            product.variants?.find((v: any) =>
                v.options?.some((o: any) => Number(o.id) === optionId),
            ) ?? null
        );
    }

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

    function getOptionImage(
        optionId: number,
        variationName: string,
    ): string | null {
        if (!product) return null;
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

    function isOptionAvailable(optionId: number): boolean {
        if (!product) return false;
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
    //  STOCK
    // ═══════════════════════════════════════
    const baseIsUnlimited = $derived(
        product?.product_stock?.is_unlimited ?? false,
    );
    const baseStock = $derived(product?.product_stock?.stock ?? 0);
    const baseMinPurchase = $derived(product?.product_stock?.min_purchase ?? 1);

    const currentIsUnlimited = $derived(
        product && matchingVariant != null
            ? (matchingVariant.product_stock?.is_unlimited ?? baseIsUnlimited)
            : baseIsUnlimited,
    );
    const currentStock = $derived(
        product && matchingVariant?.product_stock?.stock !== undefined
            ? matchingVariant.product_stock.stock
            : baseStock,
    );
    const currentMinPurchase = $derived(
        product && matchingVariant?.product_stock?.min_purchase !== undefined
            ? matchingVariant.product_stock.min_purchase
            : baseMinPurchase,
    );
    const isInStock = $derived(product && (currentIsUnlimited || currentStock > 0));

    // ═══════════════════════════════════════
    //  PRICE
    // ═══════════════════════════════════════
    const basePrice = $derived(product?.product_price?.price ?? 0);
    const currentPrice = $derived.by(() => {
        if (!product) return 0;
        if (matchingVariant) {
            if (matchingVariant.is_promo) {
                return Number(matchingVariant.promo_price);
            }
            return Number(matchingVariant.product_price?.price ?? basePrice);
        } else {
            if (product.is_promo) {
                return Number(product.promo_price);
            }
            return Number(basePrice);
        }
    });
    const originalPrice = $derived.by(() => {
        if (!product) return null;
        if (matchingVariant) {
            if (matchingVariant.is_promo) {
                return Number(matchingVariant.original_price);
            }
            return null;
        } else {
            if (product.is_promo) {
                return Number(product.original_price);
            }
            return null;
        }
    });
    const discountPercentage = $derived.by(() => {
        if (!product) return 0;
        if (matchingVariant) {
            if (matchingVariant.is_promo) {
                return Number(matchingVariant.discount_percentage);
            }
            return 0;
        } else {
            if (product.is_promo) {
                return Number(product.discount_percentage);
            }
            return 0;
        }
    });

    const displayImage = $derived.by(() => {
        if (!product) return '/noimage/image.png';
        if (matchingVariant?.image) {
            return formatImagePath(matchingVariant.image);
        }
        for (const key of Object.keys(selectedOptions)) {
            const optId = selectedOptions[key];
            const variation = product.variations?.find((v: any) => String(v.id) === key);
            const optImg = getOptionImage(optId, variation?.name || '');
            if (optImg) return optImg;
        }
        if (product.images?.length > 0) {
            const firstImg = product.images[0].url || product.images[0].path;
            if (firstImg) return formatImagePath(firstImg);
        }
        return formatImagePath(product.image);
    });

    function fmt(price: any): string {
        const n = Number(price);
        if (!n) return 'Rp 0';
        return 'Rp ' + n.toLocaleString('id-ID');
    }

    function withOpacity(hex: string, opacity: number): string {
        if (!hex) return '';
        const trimmed = hex.trim();
        if (!trimmed.startsWith('#')) return trimmed;
        let cleanHex = trimmed.slice(1);
        if (cleanHex.length === 8) {
            cleanHex = cleanHex.slice(0, 6);
        }
        const alphaHex = Math.round(opacity * 255)
            .toString(16)
            .padStart(2, '0');
        return `#${cleanHex}${alphaHex}`;
    }

    function decreaseQty() {
        if (qty > currentMinPurchase) {
            qty--;
        }
    }

    function increaseQty() {
        if (currentIsUnlimited || qty < currentStock) {
            qty++;
        } else {
            showToast(`Maksimal pembelian ${currentStock} pcs sesuai stok tersedia.`, 'info', 'top');
        }
    }

    function submitAddToCart() {
        if (!user) {
            onClose();
            window.dispatchEvent(new CustomEvent('open-login-modal'));
            return;
        }

        if (hasVariations && !fullySelected) {
            showToast('Silakan pilih variasi produk terlebih dahulu.', 'error', 'top');
            return;
        }

        if (!isInStock) {
            showToast('Produk ini sedang kehabisan stok.', 'error', 'top');
            return;
        }

        processing = true;

        router.post('/cart', {
            product_id: product.id,
            product_variant_id: matchingVariant ? matchingVariant.id : null,
            quantity: qty,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                processing = false;
                onAdded();
                onClose();
            },
            onError: () => {
                showToast('Gagal menambahkan produk ke keranjang.', 'error', 'top');
                processing = false;
            }
        });
    }
</script>

{#if show && product}
    <!-- Backdrop -->
    <!-- svelte-ignore a11y_click_events_have_key_events -->
    <!-- svelte-ignore a11y_no_static_element_interactions -->
    <div
        class="fixed inset-0 z-[150] bg-slate-900/50 backdrop-blur-sm"
        transition:fade={{ duration: 200 }}
        onclick={onClose}
    ></div>

    <!-- Bottom Sheet Panel -->
    <div
        class="fixed bottom-0 left-0 right-0 z-[151] bg-white rounded-t-3xl shadow-2xl flex flex-col"
        style="max-height: 90vh;"
        transition:fly={{ y: 400, duration: 300, easing: cubicOut }}
    >
        <!-- Drag Handle -->
        <div class="flex justify-center pt-3 pb-1 shrink-0">
            <div class="w-10 h-1 rounded-full bg-slate-200"></div>
        </div>

        <!-- Header Title + Close -->
        <div class="px-5 pt-2 pb-4 border-b border-slate-100 flex items-center justify-between shrink-0">
            <h3 class="text-base font-black text-slate-800">Varian produk</h3>
            <button
                onclick={onClose}
                aria-label="Tutup"
                class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition"
            >
                <i class="ti ti-x text-base"></i>
            </button>
        </div>

        <!-- Product Info -->
        <div class="px-5 py-4 border-b border-slate-100 flex gap-4 items-center shrink-0">
            <div class="w-20 h-20 rounded-2xl overflow-hidden border border-slate-200 shrink-0 bg-white shadow-sm">
                <img
                    src={displayImage}
                    alt={product.name}
                    class="w-full h-full object-cover"
                />
            </div>
            <div class="flex-grow">
                <p class="text-xl font-black" style="color: {secondary};">
                    {fmt(currentPrice)}
                </p>
                {#if originalPrice && originalPrice > currentPrice}
                    <p class="text-xs text-slate-400 line-through mt-0.5">
                        {fmt(originalPrice)}
                    </p>
                {/if}
                <p class="text-xs text-slate-500 mt-1">
                    {hasVariations && !fullySelected ? 'Pilih variasi terlebih dahulu' : product.name}
                </p>
            </div>
        </div>

        <!-- Body (Scrollable) -->
        <div class="flex-grow overflow-y-auto px-5 py-5 space-y-5">
            <!-- Variations -->
            {#if hasVariations}
                <div class="space-y-5">
                    {#each product.variations as variation}
                        {@const selLabel = getSelectedLabel(variation)}
                        <div class="space-y-3">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-black text-slate-700">
                                    Pilih {variation.name}:
                                </span>
                                {#if selLabel}
                                    <span class="text-sm font-bold" style="color: {primary};">
                                        {selLabel}
                                    </span>
                                {/if}
                            </div>
                            <div class="flex flex-wrap gap-2">
                                {#each variation.options as opt}
                                    {@const optImg = getOptionImage(opt.id, variation.name)}
                                    {@const available = isOptionAvailable(opt.id)}
                                    {@const sel = isSelected(String(variation.id), opt.id)}
                                    <button
                                        onclick={() => available && selectOption(String(variation.id), opt.id)}
                                        class="relative flex items-center gap-2 px-4 py-2.5 rounded-2xl border-2 text-sm font-bold transition duration-150
                                        {sel ? 'shadow-sm' : 'border-slate-200 text-slate-700 bg-white hover:border-slate-300'}
                                        {!available ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer'}"
                                        style={sel ? `border-color: ${secondary}; background: ${withOpacity(secondary, 0.03)}; color: ${secondary};` : ''}
                                        disabled={!available}
                                        aria-label={opt.name}
                                    >
                                        {#if optImg}
                                            <img
                                                src={optImg}
                                                alt={opt.name}
                                                class="w-6 h-6 rounded-lg object-cover shrink-0"
                                            />
                                        {/if}
                                        <span>{opt.name}</span>
                                    </button>
                                {/each}
                            </div>
                        </div>
                    {/each}
                </div>
            {/if}

            <!-- Quantity Selector -->
            <div class="flex items-center justify-between pt-4 border-t border-slate-100">
                <div>
                    <span class="text-sm font-black text-slate-700 block">Jumlah</span>
                    <span class="text-xs text-slate-400 font-medium">
                        {#if hasVariations && !fullySelected}
                            Pilih variasi terlebih dahulu
                        {:else if !currentIsUnlimited}
                            Stok: {currentStock} pcs
                        {/if}
                    </span>
                </div>

                <div class="flex items-center border border-slate-200 rounded-xl overflow-hidden bg-slate-50 p-0.5">
                    <button
                        onclick={decreaseQty}
                        disabled={qty <= currentMinPurchase}
                        aria-label="Kurangi jumlah"
                        class="w-9 h-9 rounded-lg flex items-center justify-center text-slate-600 hover:bg-white active:scale-95 disabled:opacity-30 disabled:cursor-not-allowed transition"
                    >
                        <i class="ti ti-minus text-sm"></i>
                    </button>
                    <span class="w-12 text-center text-sm font-black text-slate-800">
                        {qty}
                    </span>
                    <button
                        onclick={increaseQty}
                        disabled={!currentIsUnlimited && qty >= currentStock}
                        aria-label="Tambah jumlah"
                        class="w-9 h-9 rounded-lg flex items-center justify-center text-slate-600 hover:bg-white active:scale-95 disabled:opacity-30 disabled:cursor-not-allowed transition"
                    >
                        <i class="ti ti-plus text-sm"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Footer CTA -->
        <div
            class="px-5 pt-4 border-t border-slate-100 bg-white shrink-0"
            style="padding-bottom: max(1.5rem, env(safe-area-inset-bottom, 1.5rem));"
        >
            <button
                onclick={submitAddToCart}
                disabled={processing || (hasVariations && !fullySelected) || !isInStock}
                aria-label="Tambah ke Keranjang"
                class="w-full py-4 px-4 rounded-2xl text-white font-black text-sm uppercase tracking-wider transition duration-200 active:scale-[0.98] flex items-center justify-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed"
                style="background-color: {primary}; box-shadow: 0 4px 14px {withOpacity(primary, 0.35)};"
            >
                {#if processing}
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Memproses...
                {:else}
                    + Keranjang
                {/if}
            </button>
        </div>
    </div>
{/if}
