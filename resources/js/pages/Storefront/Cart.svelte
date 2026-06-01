<script lang="ts">
    import StorefrontLayout from '@/components/layouts/StorefrontLayout.svelte';
    import { page, Link, router } from '@inertiajs/svelte';
    import { fade } from 'svelte/transition';
    import { showToast } from '@/utils/toast';

    let { cartItems = [], storeName = '', activePromotions = [] } = $props();

    const primary = $derived(
        (page.props as any).theme?.primary_color ?? '#ee4d2d',
    );
    const secondary = $derived(
        (page.props as any).theme?.secondary_color ?? '#fa7315',
    );
    const chatUnreadCount = $derived((page.props as any).chatUnreadCount || 0);

    function goToChat() {
        const auth = (page.props as any).auth?.user;
        if (auth) {
            router.visit('/chats');
        } else {
            window.dispatchEvent(new CustomEvent('open-login-modal'));
        }
    }

    // Selection states
    const selectedIds = $derived(
        cartItems
            .filter((item: any) => item.is_checked)
            .map((item: any) => item.id),
    );

    const isAllSelected = $derived(
        cartItems.length > 0 && selectedIds.length === cartItems.length,
    );

    function toggleSelectAll() {
        router.patch(
            '/cart/bulk-update',
            {
                is_checked: !isAllSelected,
            },
            {
                preserveScroll: true,
            },
        );
    }

    function toggleItem(item: any) {
        router.patch(
            `/cart/${item.id}`,
            {
                is_checked: !item.is_checked,
            },
            {
                preserveScroll: true,
            },
        );
    }

    const selectedItems = $derived(
        cartItems.filter((item: any) => selectedIds.includes(item.id)),
    );

    const selectedCartSubtotal = $derived(
        selectedItems.reduce(
            (acc: number, item: any) => acc + (item.subtotal ?? 0),
            0,
        ),
    );

    const selectedTotalQuantity = $derived(
        selectedItems.reduce(
            (acc: number, item: any) => acc + item.quantity,
            0,
        ),
    );

    // Format currency to Indonesian Rupiah
    function fmt(price: any): string {
        const n = Number(price);
        if (!n) return 'Rp 0';
        return 'Rp ' + n.toLocaleString('id-ID');
    }

    function withOpacity(hex: string, alpha: number) {
        if (!hex) return `rgba(0, 0, 0, ${alpha})`;
        let r = 0,
            g = 0,
            b = 0;
        if (hex.length === 4) {
            r = parseInt(hex[1] + hex[1], 16);
            g = parseInt(hex[2] + hex[2], 16);
            b = parseInt(hex[3] + hex[3], 16);
        } else if (hex.length === 7) {
            r = parseInt(hex.substring(1, 3), 16);
            g = parseInt(hex.substring(3, 5), 16);
            b = parseInt(hex.substring(5, 7), 16);
        }
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }

    // Get suitable product image
    function getProductImage(item: any): string {
        if (item.product_variant?.image) {
            return formatImagePath(item.product_variant.image);
        }
        if (item.product?.images?.length > 0) {
            return formatImagePath(
                item.product.images[0].url || item.product.images[0].path,
            );
        }
        if (item.product?.image) {
            return formatImagePath(item.product.image);
        }
        return '/noimage/image.png';
    }

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

    // Get formatted variant label (e.g., "Red White / 40")
    function getVariantLabel(item: any): string {
        if (!item.product_variant || !item.product_variant.options) return '';
        return item.product_variant.options.map((o: any) => o.name).join(' / ');
    }

    // Stepper operations
    function updateQty(item: any, newQty: number) {
        if (newQty < 1) return;

        // If stock is limited, clamp to max and show toast
        if (!item.is_unlimited && newQty > item.stock) {
            showToast(`Stok maksimal: ${item.stock}`, 'warning');
            newQty = item.stock;
        }

        router.patch(
            `/cart/${item.id}`,
            {
                quantity: newQty,
            },
            {
                preserveScroll: true,
            },
        );
    }

    let deleteModalOpen = $state(false);
    let deleteType = $state<'single' | 'selected' | 'all'>('single');
    let itemToDelete = $state<any>(null);

    function deleteItem(item: any) {
        itemToDelete = item;
        deleteType = 'single';
        deleteModalOpen = true;
    }

    function deleteSelected() {
        if (selectedIds.length === 0) return;
        deleteType = 'selected';
        deleteModalOpen = true;
    }

    function deleteAll() {
        if (cartItems.length === 0) return;
        deleteType = 'all';
        deleteModalOpen = true;
    }

    function executeDelete() {
        deleteModalOpen = false;
        if (deleteType === 'single' && itemToDelete) {
            router.delete(`/cart/${itemToDelete.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    itemToDelete = null;
                },
            });
        } else if (deleteType === 'selected') {
            selectedIds.forEach((id) => {
                router.delete(`/cart/${id}`, {
                    preserveScroll: true,
                    preserveState: true,
                });
            });
        } else if (deleteType === 'all') {
            cartItems.forEach((item: any) => {
                router.delete(`/cart/${item.id}`, {
                    preserveScroll: true,
                    preserveState: true,
                });
            });
        }
    }

    // Payment Gateway Simulation States
    let paymentModalOpen = $state(false);
    let selectedMethod = $state<'qris' | 'bca' | 'mandiri' | 'cc'>('qris');
    let checkoutLoading = $state(false);
    let successModalOpen = $state(false);
    let checkoutSuccessData = $state<{
        transactionId: string;
        totalAmount: number;
        selectedCount: number;
    } | null>(null);

    // CC Mock states
    let cardNumber = $state('');
    let cardExpiry = $state('');
    let cardCvv = $state('');

    function startCheckout() {
        if (selectedItems.length === 0) {
            showToast(
                'Silakan pilih minimal satu produk untuk melakukan pembayaran.',
                'success',
            );
            return;
        }
        paymentModalOpen = true;
    }

    function processPayment() {
        if (selectedMethod === 'cc') {
            if (!cardNumber || !cardExpiry || !cardCvv) {
                showToast(
                    'Silakan lengkapi informasi kartu kredit Anda.',
                    'error',
                );
                return;
            }
        }
        checkoutLoading = true;

        setTimeout(() => {
            checkoutLoading = false;
            paymentModalOpen = false;

            // Set success data
            checkoutSuccessData = {
                transactionId:
                    'TRX-' + Math.floor(100000 + Math.random() * 900000),
                totalAmount: selectedCartSubtotal,
                selectedCount: selectedTotalQuantity,
            };

            successModalOpen = true;

            // Delete purchased items from database via Inertia to keep database in sync!
            selectedIds.forEach((id) => {
                router.delete(`/cart/${id}`, {
                    preserveScroll: true,
                    preserveState: true,
                });
            });

            cardNumber = '';
            cardExpiry = '';
            cardCvv = '';
        }, 2000);
    }

    // Variant Selection Dropdown States
    let activeVariantDropdownId = $state<number | null>(null);

    function selectVariant(item: any, variantId: number) {
        if (item.product_variant_id === variantId) return;
        router.patch(
            `/cart/${item.id}`,
            {
                product_variant_id: variantId,
            },
            {
                preserveScroll: true,
            },
        );
    }

    $effect(() => {
        const handleGlobalClick = () => {
            activeVariantDropdownId = null;
        };
        window.addEventListener('click', handleGlobalClick);
        return () => {
            window.removeEventListener('click', handleGlobalClick);
        };
    });

    // Helper to check if any required item of a promotion exists in the cart but is unchecked
    function hasUncheckedItemsInPromo(promo: any): boolean {
        const bundle = promo.settings?.bundle;
        if (!bundle || !bundle.buy_items) return false;
        return bundle.buy_items.some((buyItem: any) => {
            const cartItem = cartItems.find((ci: any) => {
                const matchProduct =
                    Number(ci.product_id) === Number(buyItem.product_id);
                const matchVariant = buyItem.product_variant_id
                    ? Number(ci.product_variant_id) ===
                      Number(buyItem.product_variant_id)
                    : true;
                return matchProduct && matchVariant;
            });
            return cartItem && !cartItem.is_checked;
        });
    }

    function getBundlingPromoForCartItem(item: any) {
        if (!activePromotions || !item.is_checked) return null;
        return activePromotions.find((promo: any) => {
            if (promo.type !== 'bundling_gift') return false;
            if (hasUncheckedItemsInPromo(promo)) return false;
            const bundle = promo.settings?.bundle;
            if (!bundle || !bundle.buy_items) return false;

            // Check if this item is part of the buy_items
            const matchesThisItem = bundle.buy_items.some((buyItem: any) => {
                const matchProduct =
                    Number(buyItem.product_id) === Number(item.product_id);
                const matchVariant = buyItem.product_variant_id
                    ? Number(buyItem.product_variant_id) ===
                      Number(item.product_variant_id)
                    : true;
                return matchProduct && matchVariant;
            });

            if (!matchesThisItem) return false;

            // To prevent double info, only return this promo if 'item' is the FIRST checked item in cartItems that matches it
            const firstMatchingItemInCart = cartItems.find((ci: any) => {
                if (!ci.is_checked) return false;
                return bundle.buy_items.some((buyItem: any) => {
                    const matchProduct =
                        Number(buyItem.product_id) === Number(ci.product_id);
                    const matchVariant = buyItem.product_variant_id
                        ? Number(buyItem.product_variant_id) ===
                          Number(ci.product_variant_id)
                        : true;
                    return matchProduct && matchVariant;
                });
            });

            return (
                firstMatchingItemInCart &&
                firstMatchingItemInCart.id === item.id
            );
        });
    }

    // 2. Check if all items in a promotion's buy_items are satisfied
    function checkPromoEligibility(promo: any): boolean {
        const bundle = promo.settings?.bundle;
        if (!bundle || !bundle.buy_items) return false;
        return bundle.buy_items.every((buyItem: any) => {
            const status = getBuyItemCartStatus(buyItem);
            return status.isMet;
        });
    }

    // 3. Get the current cart status of a required buyItem (only checked quantities count!)
    function getBuyItemCartStatus(buyItem: any) {
        const cartItem = cartItems.find((ci: any) => {
            const matchProduct =
                Number(ci.product_id) === Number(buyItem.product_id);
            const matchVariant = buyItem.product_variant_id
                ? Number(ci.product_variant_id) ===
                  Number(buyItem.product_variant_id)
                : true;
            return matchProduct && matchVariant;
        });
        const currentQty =
            cartItem && cartItem.is_checked ? cartItem.quantity : 0;
        const requiredQty = Number(buyItem.qty);
        return {
            inCart: !!cartItem && cartItem.is_checked,
            currentQty,
            requiredQty,
            isMet: currentQty >= requiredQty,
            cartItem,
        };
    }

    // 4. Quick add-to-cart for other products in the bundle
    function quickAddToCart(
        productId: number,
        variantId: number | null = null,
    ) {
        router.post(
            '/cart',
            {
                product_id: productId,
                product_variant_id: variantId,
                quantity: 1,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    showToast('Produk ditambahkan ke keranjang!', 'success');
                },
            },
        );
    }

    // 5. Dynamic per-item inline promotion prompt text
    function getItemPromoPromptText(item: any, promo: any): string {
        const bundle = promo.settings?.bundle;
        if (!bundle || !bundle.buy_items) return '';
        const buyReq = bundle.buy_items.find(
            (x: any) => Number(x.product_id) === Number(item.product_id),
        );
        const requiredQty = buyReq ? Number(buyReq.qty) : 1;
        const giftNames = bundle.get_items
            .map((x: any) => x.product_name)
            .join(', ');
        const otherBuyItems = bundle.buy_items.filter(
            (x: any) => Number(x.product_id) !== Number(item.product_id),
        );

        if (otherBuyItems.length === 0) {
            if (item.quantity < requiredQty) {
                return `Beli ${requiredQty - item.quantity} pcs lagi untuk dapat gratis ${giftNames}!`;
            }
        } else {
            const missingOthers = otherBuyItems.filter(
                (bi: any) => !getBuyItemCartStatus(bi).isMet,
            );
            if (item.quantity < requiredQty) {
                return `Tambah ${requiredQty - item.quantity} pcs lagi & lengkapi bundling untuk dapat gratis ${giftNames}!`;
            } else if (missingOthers.length > 0) {
                const missingNames = missingOthers
                    .map((x: any) => x.product_name)
                    .join(', ');
            }
        }
        return '';
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

    // Voucher & Promo Selection States
    const activeVouchers = $derived.by(() => {
        const base = (activePromotions || []).filter(
            (p: any) =>
                p.type === 'voucher_gratis_ongkir' ||
                p.type === 'voucher_belanja',
        );
        const extra = [];
        if (
            selectedShippingVoucher &&
            !base.some((p: any) => p.id === selectedShippingVoucher.id)
        ) {
            extra.push(selectedShippingVoucher);
        }
        if (
            selectedDiscountVoucher &&
            !base.some((p: any) => p.id === selectedDiscountVoucher.id)
        ) {
            extra.push(selectedDiscountVoucher);
        }
        return [...base, ...extra];
    });

    let voucherModalOpen = $state(false);
    let selectedShippingVoucher = $state<any>(null);
    let selectedDiscountVoucher = $state<any>(null);
    let manualVoucherCode = $state('');
    let expandedTerms = $state<Record<number, boolean>>({});

    function toggleTerms(id: number) {
        expandedTerms[id] = !expandedTerms[id];
    }

    const shippingVouchers = $derived(
        activeVouchers.filter((v: any) => v.type === 'voucher_gratis_ongkir'),
    );

    const discountVouchers = $derived(
        activeVouchers.filter((v: any) => v.type !== 'voucher_gratis_ongkir'),
    );

    const checkoutUrl = $derived.by(() => {
        const codes = [];
        if (selectedShippingVoucher) codes.push(selectedShippingVoucher.code);
        if (selectedDiscountVoucher) codes.push(selectedDiscountVoucher.code);
        return codes.length > 0
            ? `/checkout?voucher=${codes.join(',')}`
            : '/checkout';
    });

    const calculatedDiscount = $derived.by(() => {
        if (!selectedDiscountVoucher) return 0;
        if (
            selectedCartSubtotal <
            Number(selectedDiscountVoucher.min_purchase ?? 0)
        )
            return 0;

        const canStack =
            selectedDiscountVoucher.settings?.can_stack_with_promos !== false;
        let eligibleSubtotal = selectedCartSubtotal;

        if (!canStack) {
            eligibleSubtotal = selectedItems.reduce(
                (acc: number, item: any) => {
                    const variant =
                        item.product_variant ?? item.productVariant ?? null;
                    const product = item.product ?? null;
                    const isOnPromo = variant
                        ? (variant.is_promo ?? false)
                        : (product?.is_promo ?? false);
                    return isOnPromo ? acc : acc + (item.subtotal ?? 0);
                },
                0,
            );
        }

        let amount = 0;
        if (selectedDiscountVoucher.discount_type === 'percentage') {
            amount =
                eligibleSubtotal *
                (Number(selectedDiscountVoucher.discount_value) / 100);
        } else {
            amount = Number(selectedDiscountVoucher.discount_value);
        }

        amount = Math.min(amount, eligibleSubtotal);

        if (selectedDiscountVoucher.max_discount) {
            amount = Math.min(
                amount,
                Number(selectedDiscountVoucher.max_discount),
            );
        }

        return amount;
    });

    const calculatedGrandTotal = $derived(
        Math.max(0, selectedCartSubtotal - calculatedDiscount),
    );

    function toggleVoucher(promo: any) {
        if (selectedCartSubtotal < Number(promo.min_purchase ?? 0)) {
            showToast(
                `Belum memenuhi syarat minimal pembelian ${fmt(promo.min_purchase)}`,
                'warning',
            );
            return;
        }
        if (promo.type === 'voucher_gratis_ongkir') {
            if (selectedShippingVoucher?.id === promo.id) {
                selectedShippingVoucher = null;
                showToast('Voucher gratis ongkir dilepas.', 'info');
            } else {
                selectedShippingVoucher = promo;
                showToast(`Voucher ${promo.code} diterapkan!`, 'success');
            }
        } else {
            if (selectedDiscountVoucher?.id === promo.id) {
                selectedDiscountVoucher = null;
                showToast('Voucher belanja dilepas.', 'info');
            } else {
                selectedDiscountVoucher = promo;
                showToast(`Voucher ${promo.code} diterapkan!`, 'success');
            }
        }
    }

    function applyManualCode() {
        const code = manualVoucherCode.trim().toUpperCase();
        if (!code) return;

        const found = (activePromotions || []).find(
            (p) => p.code && p.code.toUpperCase() === code,
        );
        if (found) {
            toggleVoucher(found);
            manualVoucherCode = '';
        } else {
            showToast(
                'Kode voucher tidak ditemukan atau sudah kadaluarsa.',
                'error',
            );
        }
    }

    function handleCheckout() {
        if (selectedItems.length === 0) return;
        const codes = [];
        if (selectedShippingVoucher) codes.push(selectedShippingVoucher.code);
        if (selectedDiscountVoucher) codes.push(selectedDiscountVoucher.code);

        router.post(
            '/cart/select-vouchers',
            {
                vouchers: codes.length > 0 ? codes.join(',') : '',
            },
            {
                onSuccess: () => {
                    router.visit('/checkout');
                },
                onError: () => {
                    router.visit('/checkout');
                },
            },
        );
    }
</script>

<svelte:head>
    <title>Keranjang Belanja 🛒 - {storeName || 'Bizmate Premium Store'}</title>
</svelte:head>

<StorefrontLayout hideMobileHeader={true} hideMobileFooter={true}>
    <!-- ═══════════════════════════════════════════════════
     STICKY MOBILE HEADER (Shopee Style - exact copy)
    ═══════════════════════════════════════════════════ -->
    <div
        class="md:hidden fixed top-0 left-0 right-0 z-40 bg-white border-b border-slate-100 flex items-center justify-between px-3 py-3 shadow-3xs"
    >
        <div class="flex items-center gap-3">
            <button
                onclick={goBack}
                class="w-8 h-8 flex items-center justify-center text-slate-700 hover:bg-slate-50 rounded-full active:scale-95 transition cursor-pointer border-0 bg-transparent"
                aria-label="Kembali"
            >
                <i class="ti ti-arrow-left text-xl" style="color: {primary};"
                ></i>
            </button>
            <span class="font-bold text-base text-slate-800"
                >Keranjang Saya ({cartItems.length})</span
            >
        </div>

        <div class="flex items-center gap-3.5">
            <button
                onclick={deleteAll}
                disabled={cartItems.length === 0}
                class="text-xs font-bold text-rose-600 hover:text-rose-700 disabled:opacity-50 border-0 bg-transparent cursor-pointer"
            >
                Hapus Semua
            </button>
            <button
                onclick={goToChat}
                class="relative w-8 h-8 flex items-center justify-center hover:bg-slate-50 rounded-full transition border-0 bg-transparent cursor-pointer"
                aria-label="Chat"
            >
                <i class="ti ti-message-dots text-xl" style="color: {primary};"
                ></i>
                {#if chatUnreadCount > 0}
                    <span
                        class="absolute -top-1 -right-1.5 text-white text-[8px] font-black w-4.5 h-4.5 rounded-full flex items-center justify-center border border-white shadow-3xs"
                        style="background-color: {primary};"
                    >
                        {chatUnreadCount > 99 ? '99+' : chatUnreadCount}
                    </span>
                {/if}
            </button>
        </div>
    </div>

    <!-- Mobile Spacer -->
    <div class="md:hidden h-[53px]"></div>

    <!-- ═══════════════════════════════════════════════════
     MAIN WRAPPER
    ═══════════════════════════════════════════════════ -->
    <div
        class="max-w-6xl mx-auto px-0 md:px-6 lg:px-8 pt-0 md:pt-4 pb-28 md:py-8 bg-white md:bg-slate-50/50 w-full min-h-[calc(100dvh-53px)] md:min-h-0"
    >
        <!-- Desktop Page Title -->
        <h1
            class="hidden md:flex font-outfit font-black text-2xl sm:text-3xl text-slate-800 items-center gap-2.5 mb-8"
        >
            <i class="ti ti-shopping-cart text-3xl" style="color: {primary};"
            ></i>
            Keranjang Belanja
        </h1>

        {#if cartItems.length === 0}
            <!-- Empty Cart State -->
            <div
                class="flex flex-col items-center justify-center py-20 px-6 text-center w-full"
                transition:fade={{ duration: 200 }}
            >
                <div
                    class="w-24 h-24 bg-slate-50 rounded-full flex items-center justify-center mb-6 text-slate-300"
                >
                    <i class="ti ti-shopping-cart-x text-5xl"></i>
                </div>

                <h3
                    class="text-[#0a1d37] font-black text-xl sm:text-2xl mb-2 tracking-tight"
                >
                    Keranjang Belanja Kosong
                </h3>

                <p
                    class="text-slate-400 text-xs sm:text-sm max-w-sm mx-auto leading-relaxed mb-8"
                >
                    Sepertinya Anda belum menambahkan produk apapun ke
                    keranjang. Ayo cari barang favoritmu sekarang!
                </p>

                <Link
                    href="/"
                    class="inline-block px-8 py-3.5 rounded-lg font-bold text-xs sm:text-sm text-white transition active:scale-95 shadow-md border-0"
                    style="background-color: {primary};"
                >
                    Mulai Belanja
                </Link>
            </div>
        {:else}
            <!-- Cart Grid (Items list + Summary sidebar) -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
                <!-- ── LEFT COLUMN (Cart Items list) ── -->
                <div class="lg:col-span-2 space-y-4">
                    <!-- Select All Control (Desktop Header) -->
                    <div
                        class="hidden md:flex items-center justify-between bg-white border border-slate-200 rounded-3xl px-5 py-4 shadow-3xs"
                    >
                        <button
                            onclick={toggleSelectAll}
                            class="flex items-center gap-3 cursor-pointer border-0 bg-transparent p-0 text-left"
                            aria-label="Pilih Semua"
                        >
                            <div
                                class="w-5 h-5 rounded-md border flex items-center justify-center shrink-0 transition-all duration-200 border-slate-300"
                                class:border-transparent={isAllSelected}
                                style={isAllSelected
                                    ? `background-color: ${primary}; border-color: ${primary};`
                                    : ''}
                            >
                                {#if isAllSelected}
                                    <i
                                        class="ti ti-check text-white text-[10px] font-black"
                                    ></i>
                                {/if}
                            </div>
                            <span
                                class="text-xs sm:text-sm font-black text-slate-700 select-none"
                            >
                                Pilih Semua ({selectedIds.length}/{cartItems.length}
                                produk)
                            </span>
                        </button>

                        {#if selectedIds.length > 0}
                            <button
                                onclick={deleteSelected}
                                class="text-xs font-black hover:underline transition cursor-pointer border-0 bg-transparent"
                                style="color: {primary};"
                            >
                                Hapus Terpilih
                            </button>
                        {/if}
                    </div>

                    <!-- Compact Store Grouping Card (Shopee Style) -->
                    <div
                        class="bg-white rounded-none md:rounded-2xl border-t border-b-0 md:border border-slate-100 md:border-slate-200 shadow-none md:shadow-3xs pt-3 pb-0 md:p-3.5 space-y-3.5"
                    >
                        <!-- Product items inside the store block -->
                        <div class="space-y-4 pt-1 px-3 md:px-0">
                            {#each cartItems as item, idx (item.id)}
                                {@const img = getProductImage(item)}
                                {@const varLabel = getVariantLabel(item)}
                                {@const isChecked = selectedIds.includes(
                                    item.id,
                                )}
                                {@const isItemFlashSale = item.product_variant
                                    ? item.product_variant.is_promo &&
                                      item.product_variant.promo_type ===
                                          'flash_sale'
                                    : item.product.is_promo &&
                                      item.product.promo_type === 'flash_sale'}
                                {@const originalPrice = item.product_variant
                                    ? (item.product_variant.original_price ??
                                      item.product_variant.product_price
                                          ?.price ??
                                      0)
                                    : (item.product.original_price ??
                                      item.product.product_price?.price ??
                                      0)}
                                {@const hasDiscount =
                                    (item.product_variant
                                        ? item.product_variant.is_promo &&
                                          item.product_variant
                                              .discount_percentage > 0
                                        : item.product.is_promo &&
                                          item.product.discount_percentage >
                                              0) || isItemFlashSale}
                                {@const activePromo =
                                    item.product_variant?.promo_rule ??
                                    item.product?.promo_rule ??
                                    null}

                                <div
                                    class="flex flex-col gap-3 relative pt-4 border-t border-slate-100/60 first:pt-0 first:border-t-0"
                                >
                                    <!-- Inner Row for Top Components -->
                                    <div
                                        class="flex gap-2.5 items-start min-w-0"
                                    >
                                        <!-- Checkbox (Aligned to TOP) -->
                                        <div
                                            class="flex items-start pt-1.5 shrink-0"
                                        >
                                            <button
                                                onclick={() => toggleItem(item)}
                                                class="w-8 h-8 -ml-1.5 flex items-center justify-center rounded-full hover:bg-slate-50 transition shrink-0 cursor-pointer border-0 bg-transparent"
                                                aria-label="Pilih Produk"
                                            >
                                                <div
                                                    class="w-5 h-5 rounded-md border flex items-center justify-center shrink-0 transition-all duration-200"
                                                    style={isChecked
                                                        ? `background-color: ${primary}; border-color: ${primary};`
                                                        : 'border-color: #cbd5e1;'}
                                                >
                                                    {#if isChecked}
                                                        <i
                                                            class="ti ti-check text-white text-[10px] font-black"
                                                        ></i>
                                                    {/if}
                                                </div>
                                            </button>
                                        </div>

                                        <!-- Product Image (Compact) -->
                                        <div
                                            class="w-18 h-18 rounded-lg overflow-hidden bg-slate-50 border border-slate-150 shrink-0 relative select-none"
                                        >
                                            <img
                                                src={img}
                                                alt={item.product.name}
                                                class="w-full h-full object-cover"
                                                onerror={(e) => {
                                                    e.currentTarget.src =
                                                        '/noimage/image.png';
                                                }}
                                            />
                                            {#if isItemFlashSale}
                                                <!-- Flash Sale Badge (Lightning bolt) -->
                                                <span
                                                    class="absolute top-0.5 left-0.5 text-white text-[7px] font-black px-1 py-0.25 rounded-xs shadow-xs flex items-center gap-0.5 bg-rose-600"
                                                >
                                                    <i
                                                        class="ti ti-bolt text-[8px] text-amber-300 animate-pulse"
                                                    ></i>
                                                    FS
                                                </span>
                                            {:else if hasDiscount}
                                                {@const pct =
                                                    item.product_variant
                                                        ? (item.product_variant
                                                              .discount_percentage ??
                                                          0)
                                                        : (item.product
                                                              .discount_percentage ??
                                                          0)}
                                                {#if pct > 0}
                                                    <span
                                                        class="absolute top-0.5 left-0.5 text-white text-[7px] font-black px-1.5 py-0.25 rounded shadow-xs"
                                                        style="background-color: {primary};"
                                                    >
                                                        -{pct}%
                                                    </span>
                                                {/if}
                                            {/if}
                                        </div>

                                        <!-- Product Details Column -->
                                        <div
                                            class="flex-grow flex flex-col justify-between min-w-0 gap-1.5"
                                        >
                                            <!-- Product Name -->
                                            <h3
                                                class="text-xs font-bold text-slate-800 line-clamp-2 leading-snug"
                                            >
                                                {item.product.name}
                                            </h3>

                                            <!-- Variant Dropdown -->
                                            {#if item.product.variants && item.product.variants.length > 0}
                                                <div class="relative">
                                                    <button
                                                        onclick={(e) => {
                                                            e.stopPropagation();
                                                            activeVariantDropdownId =
                                                                activeVariantDropdownId ===
                                                                item.id
                                                                    ? null
                                                                    : item.id;
                                                        }}
                                                        class="inline-flex items-center gap-1 bg-slate-50 text-slate-600 text-[9.5px] px-1.5 py-0.5 rounded border border-slate-200 transition hover:bg-slate-100 cursor-pointer"
                                                    >
                                                        <span
                                                            >{varLabel ||
                                                                'Standard'}</span
                                                        >
                                                        <i
                                                            class="ti ti-chevron-down text-[8px]"
                                                        ></i>
                                                    </button>

                                                    {#if activeVariantDropdownId === item.id}
                                                        <div
                                                            class="absolute left-0 mt-1 w-48 bg-white border border-slate-200 rounded-xl shadow-lg z-50 py-1.5 text-xs text-slate-700 animate-in fade-in slide-in-from-top-1 duration-100"
                                                        >
                                                            {#each item.product.variants as variant}
                                                                {@const variantName =
                                                                    variant.options
                                                                        .map(
                                                                            (
                                                                                o,
                                                                            ) =>
                                                                                o.name,
                                                                        )
                                                                        .join(
                                                                            ' / ',
                                                                        )}
                                                                {@const isSelected =
                                                                    item.product_variant_id ===
                                                                    variant.id}
                                                                <button
                                                                    onclick={() => {
                                                                        selectVariant(
                                                                            item,
                                                                            variant.id,
                                                                        );
                                                                        activeVariantDropdownId =
                                                                            null;
                                                                    }}
                                                                    class="w-full text-left px-3 py-2 hover:bg-slate-50 transition flex items-center justify-between font-medium cursor-pointer border-0 bg-transparent"
                                                                    style={isSelected
                                                                        ? `color: ${primary}; font-weight: 800;`
                                                                        : ''}
                                                                >
                                                                    <span
                                                                        >{variantName}</span
                                                                    >
                                                                    {#if isSelected}
                                                                        <i
                                                                            class="ti ti-check text-[10px]"
                                                                            style="color: {primary};"
                                                                        ></i>
                                                                    {/if}
                                                                </button>
                                                            {/each}
                                                        </div>
                                                    {/if}
                                                </div>
                                            {/if}

                                            <!-- Badges -->
                                            <div
                                                class="flex gap-1.5 flex-wrap items-center"
                                            >
                                                {#if isItemFlashSale}
                                                    <span
                                                        class="text-[7.5px] font-black px-1.5 py-0.5 bg-rose-50 text-rose-600 border border-rose-200 rounded flex items-center gap-0.5"
                                                    >
                                                        <i
                                                            class="ti ti-bolt text-[9px] text-rose-500 animate-pulse"
                                                        ></i>
                                                        Flash Sale
                                                    </span>
                                                {/if}
                                                {#if activePromo && activePromo.remaining_promo_stock !== null && activePromo.remaining_promo_stock !== undefined}
                                                    <span
                                                        class="text-[7.5px] font-black px-1.5 py-0.5 bg-orange-50 text-orange-600 border border-orange-200 rounded flex items-center gap-0.5"
                                                    >
                                                        <i
                                                            class="ti ti-alert-circle text-[9px] text-orange-500 animate-pulse"
                                                        ></i>
                                                        Sisa Promo: {activePromo.remaining_promo_stock}
                                                        pcs
                                                    </span>
                                                {/if}
                                            </div>

                                            <!-- Bottom row: Price (left) — Stepper + Trash (right) -->
                                            <div
                                                class="flex items-center justify-between gap-2 mt-0.5"
                                            >
                                                <!-- Price -->
                                                <div
                                                    class="flex flex-col min-w-0"
                                                >
                                                    {#if isItemFlashSale && originalPrice > item.unit_price}
                                                        <span
                                                            class="text-[9px] text-slate-400 line-through tabular-nums leading-none"
                                                        >
                                                            {fmt(originalPrice)}
                                                        </span>
                                                    {/if}
                                                    <span
                                                        class="text-xs font-black leading-tight"
                                                        style="color: {primary};"
                                                    >
                                                        {fmt(item.unit_price)}
                                                    </span>
                                                </div>

                                                <!-- Stepper + Trash -->
                                                <div
                                                    class="flex items-center gap-1.5 shrink-0"
                                                >
                                                    <div
                                                        class="flex items-center border border-slate-200 rounded-lg overflow-hidden h-7 bg-white shadow-sm"
                                                    >
                                                        <button
                                                            onclick={() =>
                                                                updateQty(
                                                                    item,
                                                                    item.quantity -
                                                                        1,
                                                                )}
                                                            disabled={item.quantity <=
                                                                1}
                                                            class="w-7 h-full flex items-center justify-center hover:bg-slate-100 transition text-slate-500 disabled:opacity-30 cursor-pointer border-0 bg-transparent"
                                                        >
                                                            <i
                                                                class="ti ti-minus text-[9px]"
                                                            ></i>
                                                        </button>
                                                        <input
                                                            type="text"
                                                            inputmode="numeric"
                                                            pattern="[0-9]*"
                                                            value={item.quantity}
                                                            onfocus={(e) =>
                                                                (
                                                                    e.target as HTMLInputElement
                                                                ).select()}
                                                            onblur={(e) => {
                                                                const val =
                                                                    parseInt(
                                                                        (
                                                                            e.target as HTMLInputElement
                                                                        ).value,
                                                                    );
                                                                const clamped =
                                                                    isNaN(
                                                                        val,
                                                                    ) || val < 1
                                                                        ? 1
                                                                        : !item.is_unlimited &&
                                                                            val >
                                                                                item.stock
                                                                          ? item.stock
                                                                          : val;
                                                                updateQty(
                                                                    item,
                                                                    clamped,
                                                                );
                                                            }}
                                                            onkeydown={(e) => {
                                                                if (
                                                                    e.key ===
                                                                    'Enter'
                                                                )
                                                                    (
                                                                        e.target as HTMLInputElement
                                                                    ).blur();
                                                            }}
                                                            class="w-9 h-full text-center font-bold text-slate-700 bg-white border-x border-slate-200 tabular-nums text-[11px] focus:outline-none"
                                                        />
                                                        <button
                                                            onclick={() =>
                                                                updateQty(
                                                                    item,
                                                                    item.quantity +
                                                                        1,
                                                                )}
                                                            disabled={!item.is_unlimited &&
                                                                item.quantity >=
                                                                    item.stock}
                                                            class="w-7 h-full flex items-center justify-center hover:bg-slate-100 transition text-slate-500 disabled:opacity-30 cursor-pointer border-0 bg-transparent"
                                                        >
                                                            <i
                                                                class="ti ti-plus text-[9px]"
                                                            ></i>
                                                        </button>
                                                    </div>

                                                    <button
                                                        onclick={() =>
                                                            deleteItem(item)}
                                                        class="w-7 h-7 flex items-center justify-center text-slate-400 hover:text-rose-500 hover:bg-rose-50 rounded-lg transition cursor-pointer border border-slate-200 bg-white"
                                                        aria-label="Hapus Produk"
                                                    >
                                                        <i
                                                            class="ti ti-trash text-xs"
                                                        ></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Full-Width Inline Promo Banner (if unsatisfied) -->
                                    {#if getBundlingPromoForCartItem(item)}
                                        {@const promo =
                                            getBundlingPromoForCartItem(item)}
                                        {@const isEligible =
                                            checkPromoEligibility(promo)}

                                        {#if !isEligible}
                                            {@const bundle =
                                                promo.settings.bundle}
                                            {@const missingItems =
                                                bundle.buy_items
                                                    .map((bi) => {
                                                        const status =
                                                            getBuyItemCartStatus(
                                                                bi,
                                                            );
                                                        return status.isMet
                                                            ? null
                                                            : { bi, status };
                                                    })
                                                    .filter(Boolean)}

                                            <div
                                                class="w-full px-4 py-3.5 bg-gradient-to-r from-blue-50/60 to-indigo-50/40 border border-blue-100 rounded-2xl flex flex-col gap-2.5 text-[10.5px] sm:text-xs text-blue-700 animate-in fade-in duration-200 shadow-3xs"
                                            >
                                                <div
                                                    class="flex items-start gap-2 min-w-0"
                                                >
                                                    <span
                                                        class="flex items-center justify-center w-5 h-5 rounded-full text-white shrink-0 shadow-3xs text-[10px] mt-0.5"
                                                        style="background-color: {primary};"
                                                    >
                                                        <i class="ti ti-gift"
                                                        ></i>
                                                    </span>
                                                    <span
                                                        class="font-extrabold leading-relaxed break-words"
                                                    >
                                                        {getItemPromoPromptText(
                                                            item,
                                                            promo,
                                                        )}
                                                    </span>
                                                </div>

                                                {#if missingItems.length > 0}
                                                    <div
                                                        class="flex gap-1.5 shrink-0 justify-end border-t border-blue-100/40 pt-2"
                                                    >
                                                        {#each missingItems as missing}
                                                            <button
                                                                type="button"
                                                                onclick={() => {
                                                                    if (
                                                                        missing
                                                                            .status
                                                                            .inCart &&
                                                                        missing
                                                                            .status
                                                                            .cartItem
                                                                    ) {
                                                                        updateQty(
                                                                            missing
                                                                                .status
                                                                                .cartItem,
                                                                            missing
                                                                                .status
                                                                                .requiredQty,
                                                                        );
                                                                    } else {
                                                                        quickAddToCart(
                                                                            Number(
                                                                                missing
                                                                                    .bi
                                                                                    .product_id,
                                                                            ),
                                                                            missing
                                                                                .bi
                                                                                .product_variant_id
                                                                                ? Number(
                                                                                      missing
                                                                                          .bi
                                                                                          .product_variant_id,
                                                                                  )
                                                                                : null,
                                                                        );
                                                                    }
                                                                }}
                                                                class="text-white text-[9px] font-black px-2.5 py-1.5 rounded-lg transition hover:bg-opacity-95 shadow-3xs cursor-pointer border-0 flex items-center gap-1 active:scale-95 shrink-0"
                                                                style="background-color: {primary};"
                                                            >
                                                                <i
                                                                    class="ti ti-plus text-[9px]"
                                                                ></i>
                                                                <span>
                                                                    {missing
                                                                        .status
                                                                        .inCart
                                                                        ? 'Lengkapi'
                                                                        : missing
                                                                                .bi
                                                                                .product_name
                                                                          ? `${missing.bi.product_name.split(' ')[0]} (${fmt(missing.bi.product_price)})`
                                                                          : `Keranjang (${fmt(missing.bi.product_price)})`}
                                                                </span>
                                                            </button>
                                                        {/each}
                                                    </div>
                                                {/if}
                                            </div>
                                        {/if}
                                    {/if}
                                </div>
                            {/each}
                        </div>

                        {#if activePromotions}
                            {@const eligiblePromos = activePromotions.filter(
                                (p) =>
                                    p.type === 'bundling_gift' &&
                                    checkPromoEligibility(p),
                            )}
                            {#if eligiblePromos.length > 0}
                                <div
                                    class="mt-5 pt-4 border-t border-slate-100 space-y-3.5 px-3 md:px-0 animate-in fade-in duration-300"
                                >
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-xs shadow-3xs shrink-0"
                                        >
                                            <i class="ti ti-gift-filled"></i>
                                        </span>
                                        <h3
                                            class="font-outfit font-black text-sm text-slate-800 uppercase tracking-wider"
                                        >
                                            Hadiah & Bundling Gratis
                                        </h3>
                                    </div>

                                    <div
                                        class="grid grid-cols-1 md:grid-cols-2 gap-3"
                                    >
                                        {#each eligiblePromos as promo}
                                            {@const bundle =
                                                promo.settings.bundle}
                                            {@const buyItemsNames =
                                                bundle.buy_items
                                                    .map(
                                                        (bi) =>
                                                            bi.product_name ||
                                                            'Produk Syarat',
                                                    )
                                                    .join(' + ')}
                                            {#each bundle.get_items as gift}
                                                <div
                                                    class="flex gap-3 p-3 rounded-2xl bg-gradient-to-br from-emerald-50/40 to-teal-50/20 shadow-3xs relative overflow-hidden transition hover:shadow-2xs"
                                                >
                                                    <img
                                                        src={formatImagePath(
                                                            gift.product_image,
                                                        )}
                                                        alt={gift.product_name}
                                                        class="w-16 h-16 rounded-xl object-cover bg-slate-50 border border-slate-100 shrink-0"
                                                        onerror={(e) => {
                                                            e.currentTarget.src =
                                                                '/noimage/image.png';
                                                        }}
                                                    />
                                                    <div
                                                        class="min-w-0 flex-grow flex flex-col justify-center"
                                                    >
                                                        <div
                                                            class="flex items-center gap-1.5 mb-1.5 flex-wrap"
                                                        >
                                                            <span
                                                                class="bg-emerald-500 text-white text-[8.5px] font-black px-2 py-0.5 rounded-md uppercase tracking-wider leading-none shadow-3xs"
                                                                >Hadiah</span
                                                            >
                                                            <span
                                                                class="bg-red-50 text-red-500 text-[8.5px] font-black px-1.5 py-0.5 rounded border border-red-100 leading-none"
                                                                >100%</span
                                                            >
                                                        </div>
                                                        <p
                                                            class="font-extrabold text-slate-800 truncate text-[12px] leading-tight mb-0.5"
                                                        >
                                                            {gift.product_name}
                                                        </p>
                                                        <p
                                                            class="text-[10px] font-bold text-slate-450 mb-1 leading-none"
                                                        >
                                                            Kuantitas: {gift.qty}
                                                            pcs
                                                        </p>
                                                        <div
                                                            class="mt-1 mb-1.5 flex items-center gap-1 text-[9px] font-extrabold leading-none min-w-0"
                                                        >
                                                            <i
                                                                class="ti ti-package text-[10.5px] shrink-0"
                                                                style="color: {primary};"
                                                            ></i>
                                                            <span
                                                                class="truncate"
                                                                style="color: {primary};"
                                                                >Bundling: {buyItemsNames}</span
                                                            >
                                                        </div>
                                                        <div
                                                            class="flex items-center flex-wrap gap-2 text-[10.5px]"
                                                        >
                                                            <span
                                                                class="text-[10px] text-slate-400 line-through font-bold font-mono"
                                                                >{fmt(
                                                                    gift.product_price,
                                                                )}</span
                                                            >
                                                            <span
                                                                class="text-red-500 font-extrabold tracking-wide uppercase"
                                                                >GRATIS</span
                                                            >
                                                        </div>
                                                    </div>
                                                </div>
                                            {/each}
                                        {/each}
                                    </div>
                                </div>
                            {/if}
                        {/if}
                    </div>
                </div>

                <!-- ── RIGHT COLUMN (Voucher and Summary Sidebar cards - Image 1 Style) ── -->
                <div class="hidden lg:block space-y-4">
                    <!-- VOUCHER & PROMO Card (Image 1 Style) -->
                    <div
                        class="bg-white border border-slate-200 rounded-3xl p-5 shadow-3xs space-y-3"
                    >
                        <div class="flex items-center gap-2">
                            <i
                                class="ti ti-ticket text-xl"
                                style="color: {primary};"
                            ></i>
                            <h3
                                class="font-outfit font-black text-xs uppercase tracking-wider text-slate-400"
                            >
                                Voucher & Promo
                            </h3>
                        </div>
                        <!-- Inner Coupon Button (Image 1 Style) -->
                        <button
                            onclick={() =>
                                selectedIds.length > 0 &&
                                (voucherModalOpen = true)}
                            disabled={selectedIds.length === 0}
                            style="color: {primary}; border-color: {withOpacity(
                                primary,
                                0.2,
                            )}; background-color: {withOpacity(primary, 0.05)};"
                            class="w-full flex items-center justify-between px-3 py-2.5 border rounded-xl text-left text-xs font-bold transition group cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed"
                        >
                            <span class="flex items-center gap-2">
                                <i class="ti ti-gift text-sm"></i>
                                {selectedIds.length > 0
                                    ? 'Pilih promo yang tersedia'
                                    : 'Pilih produk sebelum pakai promo'}
                            </span>
                            <i
                                class="ti ti-chevron-right text-xs transition group-hover:translate-x-0.5"
                            ></i>
                        </button>

                        {#if selectedDiscountVoucher || selectedShippingVoucher}
                            <div
                                class="space-y-1.5 pt-1.5 border-t border-slate-50"
                            >
                                <p
                                    class="text-[9px] font-black text-slate-400 uppercase tracking-widest px-0.5"
                                >
                                    Promo yang digunakan:
                                </p>
                                <div class="flex flex-col gap-1.5">
                                    {#if selectedShippingVoucher}
                                        <div
                                            class="flex items-center justify-between px-2.5 py-1.5 rounded-xl border text-[10px] font-bold uppercase tracking-wider transition-all"
                                            style="background: {withOpacity(
                                                primary,
                                                0.06,
                                            )}; border-color: {withOpacity(
                                                primary,
                                                0.25,
                                            )};"
                                        >
                                            <div
                                                class="flex items-center gap-1.5 min-w-0"
                                            >
                                                <i
                                                    class="ti ti-truck text-xs shrink-0"
                                                    style="color: {primary};"
                                                ></i>
                                                <span
                                                    class="shrink-0 font-black"
                                                    style="color: {primary};"
                                                >
                                                    {selectedShippingVoucher.code}
                                                </span>
                                                <span
                                                    class="text-[8.5px] text-slate-600 normal-case font-bold truncate"
                                                >
                                                    • {selectedShippingVoucher.name}
                                                    (S/d {fmt(
                                                        selectedShippingVoucher.discount_value,
                                                    )})
                                                </span>
                                            </div>
                                            <button
                                                onclick={() =>
                                                    (selectedShippingVoucher =
                                                        null)}
                                                class="text-slate-400 hover:text-slate-600 transition p-0.5 border-0 bg-transparent cursor-pointer flex items-center justify-center shrink-0 rounded-full hover:bg-slate-200/40 w-4 h-4 ml-1"
                                                title="Lepas Voucher"
                                            >
                                                <i class="ti ti-x text-xs"></i>
                                            </button>
                                        </div>
                                    {/if}
                                    {#if selectedDiscountVoucher}
                                        <div
                                            class="flex items-center justify-between px-2.5 py-1.5 rounded-xl border text-[10px] font-bold uppercase tracking-wider transition-all"
                                            style="background: {withOpacity(
                                                primary,
                                                0.06,
                                            )}; border-color: {withOpacity(
                                                primary,
                                                0.25,
                                            )};"
                                        >
                                            <div
                                                class="flex items-center gap-1.5 min-w-0"
                                            >
                                                <i
                                                    class="ti ti-ticket text-xs shrink-0"
                                                    style="color: {primary};"
                                                ></i>
                                                <span
                                                    class="shrink-0 font-black"
                                                    style="color: {primary};"
                                                >
                                                    {selectedDiscountVoucher.code}
                                                </span>
                                                <span
                                                    class="text-[8.5px] text-slate-600 normal-case font-bold truncate"
                                                >
                                                    • {selectedDiscountVoucher.name}
                                                    ({selectedDiscountVoucher.discount_type ===
                                                    'percentage'
                                                        ? Number(
                                                              selectedDiscountVoucher.discount_value,
                                                          ) + '%'
                                                        : fmt(
                                                              selectedDiscountVoucher.discount_value,
                                                          )}{#if calculatedDiscount > 0}
                                                        - Hemat {fmt(
                                                            calculatedDiscount,
                                                        )}{/if})
                                                </span>
                                            </div>
                                            <button
                                                onclick={() =>
                                                    (selectedDiscountVoucher =
                                                        null)}
                                                class="text-slate-400 hover:text-slate-600 transition p-0.5 border-0 bg-transparent cursor-pointer flex items-center justify-center shrink-0 rounded-full hover:bg-slate-200/40 w-4 h-4 ml-1"
                                                title="Lepas Voucher"
                                            >
                                                <i class="ti ti-x text-xs"></i>
                                            </button>
                                        </div>
                                    {/if}
                                </div>
                            </div>
                        {/if}
                    </div>

                    <!-- Summary details card (Image 1 Style) -->
                    <div
                        class="bg-white border border-slate-200 rounded-3xl p-5 shadow-3xs space-y-4"
                    >
                        <h2
                            class="font-outfit font-black text-xs text-slate-400 uppercase tracking-wider pb-2 border-b border-slate-100"
                        >
                            Ringkasan Belanja
                        </h2>

                        <!-- Pricing Breakdown -->
                        <div
                            class="space-y-2.5 text-xs text-slate-600 font-medium"
                        >
                            <div class="flex items-center justify-between">
                                <span
                                    >Total Harga ({selectedTotalQuantity} barang)</span
                                >
                                <span class="font-bold text-slate-800"
                                    >{fmt(selectedCartSubtotal)}</span
                                >
                            </div>
                            <div class="flex items-center justify-between">
                                <span>Total Diskon</span>
                                <span class="font-bold text-emerald-600"
                                    >-{fmt(calculatedDiscount)}</span
                                >
                            </div>

                            <hr class="border-slate-100 my-2" />

                            <div
                                class="flex items-center justify-between text-slate-800"
                            >
                                <span class="text-sm font-extrabold"
                                    >Total Belanja</span
                                >
                                <span
                                    class="text-base font-black"
                                    style="color: {primary};"
                                    >{fmt(calculatedGrandTotal)}</span
                                >
                            </div>
                        </div>

                        <!-- Checkout Button (Image 1 Style) -->
                        <button
                            onclick={handleCheckout}
                            disabled={selectedItems.length === 0}
                            style="background-color: {primary}; border-radius: 12px;"
                            class="w-full py-3.5 font-bold text-sm text-white flex items-center justify-center gap-2 shadow-md hover:shadow-lg disabled:opacity-50 disabled:pointer-events-none active:scale-[0.98] transition duration-200 cursor-pointer border-0"
                        >
                            <i class="ti ti-credit-card text-lg"></i>
                            Checkout ({selectedIds.length})
                        </button>

                        <p
                            class="text-center text-[10px] text-slate-400 font-bold"
                        >
                            Didukung oleh Gateway Pembayaran Aman
                        </p>
                    </div>

                    <!-- Jaminan Transaksi Aman Card (Image 1 Style) -->
                    <div
                        class="bg-slate-50 border border-slate-200 rounded-3xl p-4 flex gap-3 text-[10px] text-slate-500 font-bold leading-normal"
                    >
                        <i
                            class="ti ti-shield-check text-green-500 text-lg shrink-0 mt-0.5"
                        ></i>
                        <p>
                            Jaminan Transaksi Aman. Pembayaran akan diverifikasi
                            otomatis oleh gerbang pembayaran aman kami.
                        </p>
                    </div>
                </div>
            </div>
        {/if}
    </div>

    <!-- ═══════════════════════════════════════════════════
     STICKY MOBILE BOTTOM BAR (Shopee Style - exact copy)
    ═══════════════════════════════════════════════════ -->
    {#if cartItems.length > 0}
        <div
            class="md:hidden fixed bottom-0 left-0 right-0 z-40 bg-white border-t border-slate-150 pb-safe shadow-[0_-8px_24px_rgba(0,0,0,0.06)]"
        >
            <!-- Row 1: Voucher Select (Image 2 style) -->
            <button
                onclick={() =>
                    selectedIds.length > 0 && (voucherModalOpen = true)}
                class="w-full bg-white border-b border-slate-100 py-2.5 px-3 flex items-center justify-between text-left transition hover:bg-slate-50 cursor-pointer border-0"
            >
                <span
                    class="flex items-center gap-2 text-[10.5px] font-black text-slate-700"
                >
                    <i class="ti ti-ticket text-base" style="color: {primary};"
                    ></i>
                    Voucher
                </span>
                <span
                    class="text-[10px] text-slate-400 flex items-center gap-1 font-bold"
                >
                    {#if selectedDiscountVoucher || selectedShippingVoucher}
                        <div class="flex items-center gap-1">
                            {#if selectedDiscountVoucher}
                                <span
                                    class="font-extrabold text-[8.5px] px-1.5 py-0.5 rounded border uppercase tracking-wider bg-white"
                                    style="color: {primary}; border-color: {withOpacity(
                                        primary,
                                        0.35,
                                    )};"
                                >
                                    {selectedDiscountVoucher.code}
                                </span>
                            {/if}
                            {#if selectedShippingVoucher}
                                <span
                                    class="font-extrabold text-[8.5px] px-1.5 py-0.5 rounded border uppercase tracking-wider bg-white"
                                    style="color: {primary}; border-color: {withOpacity(
                                        primary,
                                        0.35,
                                    )};"
                                >
                                    {selectedShippingVoucher.code}
                                </span>
                            {/if}
                        </div>
                    {:else if selectedIds.length > 0}
                        Gunakan/masukkan kode
                    {:else}
                        Pilih produk dulu
                    {/if}
                    <i class="ti ti-chevron-right text-[10px]"></i>
                </span>
            </button>

            <!-- Row 3: Checkbox, Total, Button -->
            <div
                class="flex items-center justify-between px-3 py-2 gap-3 bg-white"
            >
                <!-- Checkbox "Semua" -->
                <button
                    onclick={toggleSelectAll}
                    class="flex items-center gap-1.5 shrink-0 cursor-pointer border-0 bg-transparent p-0 text-left animate-none"
                    aria-label="Pilih Semua Mobile"
                >
                    <div
                        class="w-5 h-5 rounded-md border flex items-center justify-center shrink-0 transition-all duration-200 border-slate-300"
                        class:border-transparent={isAllSelected}
                        style={isAllSelected
                            ? `background-color: ${primary}; border-color: ${primary};`
                            : ''}
                    >
                        {#if isAllSelected}
                            <i
                                class="ti ti-check text-white text-[10px] font-black"
                            ></i>
                        {/if}
                    </div>
                    <span
                        class="text-[10.5px] font-black text-slate-600 select-none"
                        >Semua</span
                    >
                </button>

                <!-- Total Payment display -->
                <div class="flex flex-col text-right">
                    <span
                        class="text-[9.5px] text-slate-400 font-bold leading-none mb-0.5"
                        >Total Harga</span
                    >
                    <span class="text-sm font-black" style="color: {primary};">
                        {fmt(calculatedGrandTotal)}
                    </span>
                </div>

                <!-- Checkout Action button -->
                <button
                    onclick={handleCheckout}
                    disabled={selectedItems.length === 0}
                    class="px-5 py-2.5 font-bold text-xs text-white flex items-center justify-center gap-1.5 active:scale-95 disabled:opacity-50 disabled:pointer-events-none transition cursor-pointer min-w-[120px] border-0"
                    style="border-radius: 4px; background-color: {primary};"
                >
                    Checkout ({selectedTotalQuantity})
                </button>
            </div>
        </div>
    {/if}

    <!-- ═══════════════════════════════════════════════════
     SIMULATED PAYMENT GATEWAY MODAL (Xendit / Midtrans Style)
    ═══════════════════════════════════════════════════ -->
    {#if paymentModalOpen}
        <div
            class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
            transition:fade={{ duration: 150 }}
        >
            <!-- Backdrop -->
            <div
                class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs"
                onclick={() => !checkoutLoading && (paymentModalOpen = false)}
            ></div>

            <!-- Modal Box -->
            <div
                class="relative bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden z-10 animate-in zoom-in-95 duration-200 flex flex-col max-h-[90vh]"
            >
                <!-- Header -->
                <div
                    class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50 shrink-0"
                >
                    <div class="flex items-center gap-2">
                        <div
                            class="w-8 h-8 rounded-xl bg-blue-600 text-white flex items-center justify-center text-sm shadow-sm font-black"
                        >
                            <i class="ti ti-shield-lock"></i>
                        </div>
                        <div>
                            <h3
                                class="text-xs font-black text-slate-800 tracking-tight uppercase"
                            >
                                Secure Payment
                            </h3>
                            <p class="text-[9px] text-slate-400 font-bold">
                                TRANSAKSI AMAN DI DEKRIPSI
                            </p>
                        </div>
                    </div>
                    <button
                        disabled={checkoutLoading}
                        onclick={() => (paymentModalOpen = false)}
                        class="text-slate-400 hover:text-slate-600 transition disabled:opacity-30 cursor-pointer border-0 bg-transparent"
                    >
                        <i class="ti ti-x text-lg"></i>
                    </button>
                </div>

                <!-- Body -->
                <div class="p-6 overflow-y-auto space-y-5 flex-grow">
                    <!-- Amount Section -->
                    <div
                        style="background-color: {withOpacity(
                            primary,
                            0.05,
                        )}; border-color: {withOpacity(primary, 0.15)};"
                        class="border rounded-2xl p-4 flex items-center justify-between"
                    >
                        <div>
                            <span
                                class="text-[9px] text-slate-400 font-bold block uppercase leading-none mb-1"
                                >Total Pembayaran</span
                            >
                            <span class="text-lg font-black text-slate-800"
                                >{fmt(selectedCartSubtotal)}</span
                            >
                        </div>
                        <span
                            style="background-color: {withOpacity(
                                primary,
                                0.1,
                            )}; color: {primary};"
                            class="px-2.5 py-1 font-bold text-[9px] rounded-full"
                            >{selectedTotalQuantity} Barang</span
                        >
                    </div>

                    <div class="space-y-2">
                        <span
                            class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block"
                            >Pilih Metode Pembayaran</span
                        >

                        <!-- List of Methods -->
                        <div class="space-y-2">
                            <!-- QRIS -->
                            <button
                                onclick={() => (selectedMethod = 'qris')}
                                class="w-full flex items-center justify-between p-3 border rounded-2xl transition text-left cursor-pointer bg-white"
                                style={selectedMethod === 'qris'
                                    ? `border-color: ${primary}; background-color: ${withOpacity(primary, 0.05)};`
                                    : 'border-color: #e2e8f0;'}
                            >
                                <span
                                    class="flex items-center gap-2 text-xs font-black text-slate-700"
                                >
                                    <i
                                        class="ti ti-qrcode text-lg text-rose-500"
                                    ></i>
                                    QRIS (GoPay, OVO, ShopeePay)
                                </span>
                                <div
                                    class="w-4 h-4 rounded-full border flex items-center justify-center transition-all"
                                    style={selectedMethod === 'qris'
                                        ? `border-color: ${primary};`
                                        : 'border-color: #cbd5e1;'}
                                >
                                    {#if selectedMethod === 'qris'}
                                        <div
                                            class="w-2.5 h-2.5 rounded-full"
                                            style="background-color: {primary};"
                                        ></div>
                                    {/if}
                                </div>
                            </button>

                            <!-- BCA VA -->
                            <button
                                onclick={() => (selectedMethod = 'bca')}
                                class="w-full flex items-center justify-between p-3 border rounded-2xl transition text-left cursor-pointer bg-white"
                                style={selectedMethod === 'bca'
                                    ? `border-color: ${primary}; background-color: ${withOpacity(primary, 0.05)};`
                                    : 'border-color: #e2e8f0;'}
                            >
                                <span
                                    class="flex items-center gap-2 text-xs font-black text-slate-700"
                                >
                                    <i
                                        class="ti ti-building-bank text-lg text-blue-600"
                                    ></i>
                                    BCA Virtual Account
                                </span>
                                <div
                                    class="w-4 h-4 rounded-full border flex items-center justify-center transition-all"
                                    style={selectedMethod === 'bca'
                                        ? `border-color: ${primary};`
                                        : 'border-color: #cbd5e1;'}
                                >
                                    {#if selectedMethod === 'bca'}
                                        <div
                                            class="w-2.5 h-2.5 rounded-full"
                                            style="background-color: {primary};"
                                        ></div>
                                    {/if}
                                </div>
                            </button>

                            <!-- Mandiri VA -->
                            <button
                                onclick={() => (selectedMethod = 'mandiri')}
                                class="w-full flex items-center justify-between p-3 border rounded-2xl transition text-left cursor-pointer bg-white"
                                style={selectedMethod === 'mandiri'
                                    ? `border-color: ${primary}; background-color: ${withOpacity(primary, 0.05)};`
                                    : 'border-color: #e2e8f0;'}
                            >
                                <span
                                    class="flex items-center gap-2 text-xs font-black text-slate-700"
                                >
                                    <i
                                        class="ti ti-building-bank text-lg text-orange-600"
                                    ></i>
                                    Mandiri Virtual Account
                                </span>
                                <div
                                    class="w-4 h-4 rounded-full border flex items-center justify-center transition-all"
                                    style={selectedMethod === 'mandiri'
                                        ? `border-color: ${primary};`
                                        : 'border-color: #cbd5e1;'}
                                >
                                    {#if selectedMethod === 'mandiri'}
                                        <div
                                            class="w-2.5 h-2.5 rounded-full"
                                            style="background-color: {primary};"
                                        ></div>
                                    {/if}
                                </div>
                            </button>

                            <!-- CC -->
                            <button
                                onclick={() => (selectedMethod = 'cc')}
                                class="w-full flex items-center justify-between p-3 border rounded-2xl transition text-left cursor-pointer bg-white"
                                style={selectedMethod === 'cc'
                                    ? `border-color: ${primary}; background-color: ${withOpacity(primary, 0.05)};`
                                    : 'border-color: #e2e8f0;'}
                            >
                                <span
                                    class="flex items-center gap-2 text-xs font-black text-slate-700"
                                >
                                    <i
                                        class="ti ti-credit-card text-lg text-purple-600"
                                    ></i>
                                    Kartu Kredit / Debit
                                </span>
                                <div
                                    class="w-4 h-4 rounded-full border flex items-center justify-center transition-all"
                                    style={selectedMethod === 'cc'
                                        ? `border-color: ${primary};`
                                        : 'border-color: #cbd5e1;'}
                                >
                                    {#if selectedMethod === 'cc'}
                                        <div
                                            class="w-2.5 h-2.5 rounded-full"
                                            style="background-color: {primary};"
                                        ></div>
                                    {/if}
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- Selected Method Expandable Details -->
                    <div class="border-t border-slate-100 pt-4">
                        {#if selectedMethod === 'qris'}
                            <div
                                class="flex flex-col items-center justify-center bg-slate-50 border border-slate-200 rounded-2xl p-4 text-center space-y-2"
                            >
                                <span
                                    class="text-[9px] font-bold text-slate-400 uppercase tracking-wider"
                                    >Pindai QRIS untuk Bayar</span
                                >
                                <!-- QR Mockup -->
                                <div
                                    class="w-36 h-36 bg-white border border-slate-200 rounded-xl p-2 flex items-center justify-center relative"
                                >
                                    <div
                                        class="grid grid-cols-5 grid-rows-5 gap-1.5 w-full h-full opacity-80"
                                    >
                                        {#each Array(25) as _, i}
                                            <div
                                                class="rounded-xs"
                                                class:bg-slate-800={(i % 2 ===
                                                    0 &&
                                                    i !== 12) ||
                                                    i === 0 ||
                                                    i === 4 ||
                                                    i === 20 ||
                                                    i === 24}
                                                class:bg-slate-200={i % 2 !==
                                                    0 || i === 12}
                                            ></div>
                                        {/each}
                                    </div>
                                    <div
                                        class="absolute inset-0 flex items-center justify-center"
                                    >
                                        <div
                                            class="w-9 h-9 bg-white border border-slate-100 rounded-lg flex items-center justify-center shadow-xs text-[10px] font-black text-rose-600 tracking-tighter"
                                        >
                                            QRIS
                                        </div>
                                    </div>
                                </div>
                                <p
                                    class="text-[9.5px] text-slate-500 font-bold leading-normal"
                                >
                                    Mendukung GoPay, OVO, ShopeePay, Dana,
                                    LinkAja & Mobile Banking
                                </p>
                            </div>
                        {:else if selectedMethod === 'bca' || selectedMethod === 'mandiri'}
                            <div
                                class="bg-slate-50 border border-slate-200 rounded-2xl p-4 space-y-2.5"
                            >
                                <div class="flex items-center justify-between">
                                    <span
                                        class="text-[9px] font-bold text-slate-400 uppercase tracking-wider"
                                        >Nomor Virtual Account</span
                                    >
                                    <span
                                        class="px-2 py-0.5 font-black text-[8px] rounded uppercase"
                                        style="background-color: {withOpacity(
                                            primary,
                                            0.1,
                                        )}; color: {primary};"
                                        >{selectedMethod} VA</span
                                    >
                                </div>
                                <div
                                    class="flex items-center justify-between bg-white border border-slate-200 rounded-xl px-3 py-2"
                                >
                                    <span
                                        class="text-sm font-black text-slate-700 font-mono tracking-wider font-semibold"
                                    >
                                        {selectedMethod === 'bca'
                                            ? '8801209384728374'
                                            : '8901239847293847'}
                                    </span>
                                    <button
                                        type="button"
                                        onclick={() =>
                                            alert('Nomor VA disalin!')}
                                        class="text-[10px] font-black cursor-pointer border-0 bg-transparent transition hover:opacity-80"
                                        style="color: {primary};"
                                    >
                                        SALIN
                                    </button>
                                </div>
                                <p
                                    class="text-[9px] text-slate-500 font-medium leading-relaxed"
                                >
                                    Silakan lakukan transfer dari mobile banking
                                    atau ATM terdekat ke nomor Virtual Account
                                    di atas sebelum batas waktu habis.
                                </p>
                            </div>
                        {:else if selectedMethod === 'cc'}
                            <div
                                class="bg-slate-50 border border-slate-200 rounded-2xl p-4 space-y-3"
                            >
                                <span
                                    class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block"
                                    >Informasi Kartu Kredit / Debit</span
                                >
                                <div class="space-y-2">
                                    <input
                                        type="text"
                                        bind:value={cardNumber}
                                        placeholder="Nomor Kartu (16 digit)"
                                        maxlength="16"
                                        class="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 font-mono"
                                    />
                                    <div class="grid grid-cols-2 gap-2">
                                        <input
                                            type="text"
                                            bind:value={cardExpiry}
                                            placeholder="MM / YY"
                                            maxlength="5"
                                            class="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 text-center font-mono"
                                        />
                                        <input
                                            type="password"
                                            bind:value={cardCvv}
                                            placeholder="CVV (3 digit)"
                                            maxlength="3"
                                            class="w-full px-3 py-2 text-xs border border-slate-200 rounded-xl bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 text-center font-mono"
                                        />
                                    </div>
                                </div>
                            </div>
                        {/if}
                    </div>
                </div>

                <!-- Footer Checkout Button -->
                <div class="p-4 border-t border-slate-100 bg-slate-50 shrink-0">
                    <button
                        onclick={processPayment}
                        disabled={checkoutLoading}
                        class="w-full py-3.5 rounded-xl font-bold text-sm text-white text-center shadow-lg transition duration-200 active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 cursor-pointer border-0 text-white"
                        style="background-color: {primary};"
                    >
                        {#if checkoutLoading}
                            <svg
                                class="animate-spin h-4 w-4 text-white"
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
                            Memproses Pembayaran...
                        {:else}
                            <i class="ti ti-shield-check text-base"></i>
                            Bayar Sekarang {fmt(selectedCartSubtotal)}
                        {/if}
                    </button>
                </div>
            </div>
        </div>
    {/if}

    <!-- ═══════════════════════════════════════════════════
     SUCCESS MODAL (Highly Interactive Payment Successful)
    ═══════════════════════════════════════════════════ -->
    {#if successModalOpen && checkoutSuccessData}
        <div
            class="fixed inset-0 z-[10000] flex items-center justify-center p-4"
            transition:fade={{ duration: 150 }}
        >
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs"></div>

            <!-- Modal Box -->
            <div
                class="relative bg-white rounded-3xl shadow-2xl w-full max-w-sm overflow-hidden z-10 animate-in zoom-in-95 duration-200 p-6 flex flex-col items-center text-center space-y-6"
            >
                <!-- Checkmark Animation Mockup -->
                <div
                    class="w-16 h-16 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center text-3xl shadow-inner border border-emerald-200"
                >
                    <i class="ti ti-circle-check animate-bounce"></i>
                </div>

                <!-- Text Info -->
                <div class="space-y-1.5 w-full">
                    <h3
                        class="text-lg font-black text-slate-800 tracking-tight"
                    >
                        Pembayaran Berhasil!
                    </h3>
                    <p class="text-xs text-slate-400 leading-relaxed px-4">
                        Terima kasih atas pembayaran Anda. Transaksi Anda telah
                        berhasil diproses oleh gerbang pembayaran kami.
                    </p>
                </div>

                <!-- Transaction Details Card -->
                <div
                    class="bg-slate-50 border border-slate-200 rounded-2xl p-4 w-full text-left space-y-2 text-[11px] font-bold text-slate-600"
                >
                    <div class="flex items-center justify-between">
                        <span>ID Transaksi</span>
                        <span class="text-slate-800 font-mono tracking-wider"
                            >{checkoutSuccessData.transactionId}</span
                        >
                    </div>
                    <div class="flex items-center justify-between">
                        <span>Jumlah Barang</span>
                        <span class="text-slate-800"
                            >{checkoutSuccessData.selectedCount} pcs</span
                        >
                    </div>
                    <hr class="border-slate-200 my-1" />
                    <div
                        class="flex items-center justify-between text-slate-800"
                    >
                        <span class="font-extrabold text-xs"
                            >Total Pembayaran</span
                        >
                        <span
                            class="font-black text-sm"
                            style="color: {primary};"
                            >{fmt(checkoutSuccessData.totalAmount)}</span
                        >
                    </div>
                </div>

                <!-- Continue Shopping CTA -->
                <button
                    onclick={() => {
                        successModalOpen = false;
                        checkoutSuccessData = null;
                        router.visit('/');
                    }}
                    class="w-full py-3.5 rounded-xl font-bold text-xs text-white text-center shadow-lg transition active:scale-[0.98] cursor-pointer border-0 text-white font-bold"
                    style="background-color: {primary};"
                >
                    Kembali Berbelanja
                </button>
            </div>
        </div>
    {/if}

    <!-- ==========================================
     DELETE CONFIRMATION MODAL (Xendit-Style Premium Modal)
    ========================================== -->
    {#if deleteModalOpen}
        <div
            class="fixed inset-0 z-[10005] flex items-center justify-center p-4"
            transition:fade={{ duration: 150 }}
        >
            <!-- Backdrop -->
            <div
                class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity"
                onclick={() => (deleteModalOpen = false)}
                onkeypress={() => (deleteModalOpen = false)}
                role="button"
                tabindex="0"
            ></div>

            <!-- Modal Box -->
            <div
                class="bg-white rounded-3xl p-6 sm:p-8 max-w-md w-full relative z-10 shadow-2xl animate-in fade-in zoom-in duration-200"
            >
                <div
                    class="w-16 h-16 rounded-full bg-red-50 text-red-500 flex items-center justify-center text-3xl mb-5 mx-auto"
                >
                    <i class="ti ti-alert-triangle"></i>
                </div>
                <h4
                    class="font-outfit font-black text-xl text-center text-slate-800 mb-2"
                >
                    {#if deleteType === 'all'}
                        Hapus Semua Produk?
                    {:else if deleteType === 'selected'}
                        Hapus Produk Terpilih?
                    {:else}
                        Hapus Produk?
                    {/if}
                </h4>
                <p class="text-sm text-center text-slate-500 font-medium mb-8">
                    {#if deleteType === 'all'}
                        Apakah Anda yakin ingin menghapus semua produk (<strong
                            >{cartItems.length}</strong
                        > item) dari keranjang belanja Anda?
                    {:else if deleteType === 'selected'}
                        Apakah Anda yakin ingin menghapus <strong
                            >{selectedIds.length}</strong
                        > produk terpilih dari keranjang belanja Anda?
                    {:else}
                        Produk <strong
                            >{itemToDelete?.product?.name ||
                                itemToDelete?.product_variant?.product
                                    ?.name}</strong
                        > akan terhapus dari keranjang belanja.
                    {/if}
                </p>
                <div class="flex items-center gap-3">
                    <button
                        onclick={() => (deleteModalOpen = false)}
                        class="flex-grow py-3.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition cursor-pointer border-0"
                    >
                        Batal
                    </button>
                    <button
                        onclick={executeDelete}
                        class="flex-grow py-3.5 bg-red-500 hover:bg-red-600 text-white font-bold rounded-xl text-xs shadow-lg shadow-red-500/30 transition cursor-pointer border-0"
                    >
                        Ya, Hapus
                    </button>
                </div>
            </div>
        </div>
    {/if}

    <!-- ═══════════════════════════════════════════════════
     VOUCHER SELECTION MODAL (Premium Ticket Style)
    ═══════════════════════════════════════════════════ -->
    {#if voucherModalOpen}
        <div
            class="fixed inset-0 z-50 flex items-end lg:items-center justify-center p-0 lg:p-4"
            transition:fade={{ duration: 150 }}
        >
            <!-- Backdrop -->
            <div
                class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs"
                onclick={() => (voucherModalOpen = false)}
            ></div>

            <!-- Modal Box -->
            <div
                class="relative bg-white rounded-t-3xl lg:rounded-3xl shadow-2xl w-full lg:max-w-md overflow-hidden z-10 animate-in slide-in-from-bottom lg:zoom-in-95 duration-200 flex flex-col max-h-[85vh] lg:max-h-[80vh]"
            >
                <!-- Header -->
                <div
                    class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50 shrink-0"
                >
                    <div class="flex items-center gap-2.5">
                        <div
                            class="w-7 h-7 rounded-lg bg-orange-500 text-white flex items-center justify-center text-xs shadow-sm font-black"
                            style="background-color: {primary};"
                        >
                            <i class="ti ti-ticket text-sm"></i>
                        </div>
                        <div>
                            <h3
                                class="text-[11px] font-black text-slate-800 tracking-tight uppercase"
                            >
                                Voucher & Promo Toko
                            </h3>
                            <p
                                class="text-[8px] text-slate-400 font-bold uppercase leading-none"
                            >
                                Pilih promo terbaik untuk belanjamu
                            </p>
                        </div>
                    </div>
                    <button
                        onclick={() => (voucherModalOpen = false)}
                        class="w-6 h-6 flex items-center justify-center text-slate-400 hover:text-slate-650 transition cursor-pointer border-0 bg-transparent rounded-full hover:bg-slate-100/80"
                    >
                        <i class="ti ti-x text-sm"></i>
                    </button>
                </div>

                <!-- Body -->
                <div
                    class="p-3.5 overflow-y-auto space-y-3 flex-grow bg-slate-50/50"
                >
                    <!-- Manual Voucher Code Input Search Block -->
                    <div
                        class="bg-white border border-slate-200 rounded-xl p-2 flex gap-2 items-center shadow-3xs mb-1.5"
                    >
                        <div class="relative flex-grow">
                            <i
                                class="ti ti-ticket absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"
                            ></i>
                            <input
                                type="text"
                                bind:value={manualVoucherCode}
                                placeholder="Masukkan kode voucher..."
                                class="w-full pl-8 pr-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500/20 focus:border-orange-500 text-[10.5px] font-semibold text-slate-700 h-8 uppercase"
                                onkeydown={(e) => {
                                    if (e.key === 'Enter') {
                                        applyManualCode();
                                    }
                                }}
                            />
                        </div>
                        <button
                            onclick={applyManualCode}
                            disabled={!manualVoucherCode.trim()}
                            class="px-3.5 py-1.5 bg-orange-500 hover:bg-orange-600 text-white text-[10.5px] font-black rounded-lg uppercase tracking-wider transition duration-200 h-8 cursor-pointer border-0 shrink-0 disabled:opacity-50"
                            style="background-color: {primary};"
                        >
                            Pakai
                        </button>
                    </div>

                    {#if activeVouchers.length === 0}
                        <div
                            class="flex flex-col items-center justify-center py-10 px-4 text-center"
                        >
                            <div
                                class="w-12 h-12 bg-slate-100 text-slate-400 rounded-full flex items-center justify-center mb-2.5"
                            >
                                <i class="ti ti-ticket-off text-xl"></i>
                            </div>
                            <p class="text-xs font-bold text-slate-500">
                                Tidak Ada Voucher Tersedia
                            </p>
                            <p
                                class="text-[9px] text-slate-400 mt-1 max-w-xs leading-normal"
                            >
                                Saat ini tidak ada voucher gratis ongkir atau
                                belanja yang sedang aktif di toko.
                            </p>
                        </div>
                    {:else}
                        <!-- Section 1: Gratis Ongkir -->
                        {#if shippingVouchers.length > 0}
                            <div class="space-y-1.5">
                                <h4
                                    class="text-[9px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-1 px-1"
                                >
                                    <i
                                        class="ti ti-truck text-xs"
                                        style="color: {primary};"
                                    ></i> Voucher Gratis Ongkir
                                </h4>
                                <div class="space-y-1.5">
                                    {#each shippingVouchers as voucher (voucher.id)}
                                        {@const minMet =
                                            selectedCartSubtotal >=
                                            Number(voucher.min_purchase ?? 0)}
                                        {@const isSelected =
                                            selectedShippingVoucher?.id ===
                                            voucher.id}

                                        <div
                                            class="bg-white border border-slate-200/80 rounded-xl overflow-hidden shadow-3xs hover:shadow-2xs transition duration-200"
                                            class:opacity-60={!minMet}
                                        >
                                            <div
                                                class="relative flex items-stretch"
                                            >
                                                <!-- Left side color ribbon -->
                                                <div
                                                    class="w-16 shrink-0 flex flex-col items-center justify-center p-2 text-white text-center select-none"
                                                    style="background: linear-gradient(135deg, {primary}, {withOpacity(
                                                        primary,
                                                        0.8,
                                                    )});"
                                                >
                                                    <i
                                                        class="ti ti-truck text-base mb-0.5 shrink-0"
                                                    ></i>
                                                    <span
                                                        class="text-[7.5px] font-black uppercase tracking-wider leading-none"
                                                    >
                                                        Free
                                                    </span>
                                                </div>

                                                <!-- Dashed Ticket Divider line -->
                                                <div
                                                    class="absolute left-16 top-0 bottom-0 border-l border-dashed border-slate-200 z-10"
                                                ></div>
                                                <!-- Ticket Top & Bottom cutouts -->
                                                <div
                                                    class="absolute left-[58px] -top-1.5 w-3 h-3 rounded-full bg-slate-50 border border-slate-200 z-10"
                                                ></div>
                                                <div
                                                    class="absolute left-[58px] -bottom-1.5 w-3 h-3 rounded-full bg-slate-50 border border-slate-200 z-10"
                                                ></div>

                                                <!-- Right side content -->
                                                <div
                                                    class="flex-grow py-2 pl-3.5 pr-2.5 flex flex-col justify-between min-w-0"
                                                >
                                                    <div class="min-w-0">
                                                        <div
                                                            class="flex items-center gap-1 mb-0.5 flex-wrap"
                                                        >
                                                            <span
                                                                class="text-[8px] font-black px-1.5 py-0.5 rounded border uppercase tracking-wider leading-none shrink-0"
                                                                style="color: {primary}; border-color: {withOpacity(
                                                                    primary,
                                                                    0.35,
                                                                )}; background: {withOpacity(
                                                                    primary,
                                                                    0.06,
                                                                )};"
                                                            >
                                                                {voucher.code}
                                                            </span>
                                                            {#if isSelected}
                                                                <span
                                                                    class="bg-emerald-550 text-white text-[7.5px] font-black px-1 py-0.5 rounded leading-none shrink-0"
                                                                    style="background-color: {primary};"
                                                                    >TERPASANG</span
                                                                >
                                                            {/if}
                                                        </div>
                                                        <h4
                                                            class="font-extrabold text-[10px] text-slate-800 line-clamp-1 leading-snug"
                                                        >
                                                            {voucher.name}
                                                        </h4>
                                                        <p
                                                            class="text-[8.5px] text-slate-450 font-bold mt-0.5 leading-none"
                                                        >
                                                            Gratis Ongkir s/d {fmt(
                                                                voucher.discount_value,
                                                            )}
                                                        </p>
                                                    </div>

                                                    <div
                                                        class="flex items-center justify-between mt-1.5 pt-1.5 border-t border-slate-50 gap-1.5 shrink-0"
                                                    >
                                                        <div
                                                            class="flex items-center gap-1.5 shrink-0"
                                                        >
                                                            <span
                                                                class="text-[8px] bg-slate-100/80 text-slate-505 font-extrabold px-1.5 py-0.5 rounded leading-none"
                                                            >
                                                                Min. {fmt(
                                                                    voucher.min_purchase,
                                                                )}
                                                            </span>
                                                            {#if voucher.settings?.terms}
                                                                <button
                                                                    type="button"
                                                                    onclick={() =>
                                                                        toggleTerms(
                                                                            voucher.id,
                                                                        )}
                                                                    class="text-[8px] text-slate-400 hover:text-slate-650 font-extrabold underline cursor-pointer border-0 bg-transparent flex items-center gap-0.5 shrink-0"
                                                                >
                                                                    S&K <i
                                                                        class="ti {expandedTerms[
                                                                            voucher
                                                                                .id
                                                                        ]
                                                                            ? 'ti-chevron-up'
                                                                            : 'ti-chevron-down'} text-[8px]"
                                                                    ></i>
                                                                </button>
                                                            {/if}
                                                        </div>

                                                        {#if minMet}
                                                            <button
                                                                onclick={() =>
                                                                    toggleVoucher(
                                                                        voucher,
                                                                    )}
                                                                class="px-2.5 py-1 rounded-md text-[8.5px] font-black transition cursor-pointer border-0 active:scale-95 leading-none shrink-0 text-white"
                                                                style="background: {isSelected
                                                                    ? '#cbd5e1'
                                                                    : primary}; color: {isSelected
                                                                    ? '#475569'
                                                                    : 'white'};"
                                                            >
                                                                {isSelected
                                                                    ? 'Lepas'
                                                                    : 'Pakai'}
                                                            </button>
                                                        {:else}
                                                            <span
                                                                class="text-[8px] text-rose-500 font-extrabold leading-none shrink-0"
                                                            >
                                                                Kurang {fmt(
                                                                    Number(
                                                                        voucher.min_purchase,
                                                                    ) -
                                                                        selectedCartSubtotal,
                                                                )}
                                                            </span>
                                                        {/if}
                                                    </div>
                                                </div>
                                            </div>
                                            {#if expandedTerms[voucher.id] && voucher.settings?.terms}
                                                <div
                                                    class="px-3.5 py-2 bg-slate-50 border-t border-slate-100 text-[8.5px] text-slate-500 font-bold whitespace-pre-line leading-relaxed"
                                                >
                                                    {voucher.settings.terms}
                                                </div>
                                            {/if}
                                        </div>
                                    {/each}
                                </div>
                            </div>
                        {/if}

                        <!-- Section 2: Voucher Belanja -->
                        {#if discountVouchers.length > 0}
                            <div
                                class="space-y-1.5 {shippingVouchers.length > 0
                                    ? 'mt-3.5'
                                    : ''}"
                            >
                                <h4
                                    class="text-[9px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-1 px-1"
                                >
                                    <i
                                        class="ti ti-ticket text-xs"
                                        style="color: {primary};"
                                    ></i> Voucher Belanja
                                </h4>
                                <div class="space-y-1.5">
                                    {#each discountVouchers as voucher (voucher.id)}
                                        {@const minMet =
                                            selectedCartSubtotal >=
                                            Number(voucher.min_purchase ?? 0)}
                                        {@const isSelected =
                                            selectedDiscountVoucher?.id ===
                                            voucher.id}

                                        <div
                                            class="bg-white border border-slate-200/80 rounded-xl overflow-hidden shadow-3xs hover:shadow-2xs transition duration-200"
                                            class:opacity-60={!minMet}
                                        >
                                            <div
                                                class="relative flex items-stretch"
                                            >
                                                <!-- Left side color ribbon -->
                                                <div
                                                    class="w-16 shrink-0 flex flex-col items-center justify-center p-2 text-white text-center select-none"
                                                    style="background: linear-gradient(135deg, {primary}, {withOpacity(
                                                        primary,
                                                        0.8,
                                                    )});"
                                                >
                                                    <i
                                                        class="ti ti-ticket text-base mb-0.5 shrink-0"
                                                    ></i>
                                                    <span
                                                        class="text-[7.5px] font-black uppercase tracking-wider leading-none"
                                                    >
                                                        Diskon
                                                    </span>
                                                </div>

                                                <!-- Dashed Ticket Divider line -->
                                                <div
                                                    class="absolute left-16 top-0 bottom-0 border-l border-dashed border-slate-200 z-10"
                                                ></div>
                                                <!-- Ticket Top & Bottom cutouts -->
                                                <div
                                                    class="absolute left-[58px] -top-1.5 w-3 h-3 rounded-full bg-slate-50 border border-slate-200 z-10"
                                                ></div>
                                                <div
                                                    class="absolute left-[58px] -bottom-1.5 w-3 h-3 rounded-full bg-slate-50 border border-slate-200 z-10"
                                                ></div>

                                                <!-- Right side content -->
                                                <div
                                                    class="flex-grow py-2 pl-3.5 pr-2.5 flex flex-col justify-between min-w-0"
                                                >
                                                    <div class="min-w-0">
                                                        <div
                                                            class="flex items-center gap-1 mb-0.5 flex-wrap"
                                                        >
                                                            <span
                                                                class="text-[8px] font-black px-1.5 py-0.5 rounded border uppercase tracking-wider leading-none shrink-0"
                                                                style="color: {primary}; border-color: {withOpacity(
                                                                    primary,
                                                                    0.35,
                                                                )}; background: {withOpacity(
                                                                    primary,
                                                                    0.06,
                                                                )};"
                                                            >
                                                                {voucher.code}
                                                            </span>
                                                            {#if isSelected}
                                                                <span
                                                                    class="bg-emerald-550 text-white text-[7.5px] font-black px-1 py-0.5 rounded leading-none shrink-0"
                                                                    style="background-color: {primary};"
                                                                    >TERPASANG</span
                                                                >
                                                            {/if}
                                                        </div>
                                                        <h4
                                                            class="font-extrabold text-[10px] text-slate-800 line-clamp-1 leading-snug"
                                                        >
                                                            {voucher.name}
                                                        </h4>
                                                        <p
                                                            class="text-[8.5px] text-slate-450 font-bold mt-0.5 leading-none"
                                                        >
                                                            Potongan {#if voucher.discount_type === 'percentage'}{Number(
                                                                    voucher.discount_value,
                                                                )}%{:else}{fmt(
                                                                    voucher.discount_value,
                                                                )}{/if}
                                                            {#if voucher.max_discount}s/d
                                                                {fmt(
                                                                    voucher.max_discount,
                                                                )}{/if}
                                                        </p>
                                                    </div>

                                                    <div
                                                        class="flex items-center justify-between mt-1.5 pt-1.5 border-t border-slate-50 gap-1.5 shrink-0"
                                                    >
                                                        <div
                                                            class="flex items-center gap-1.5 shrink-0"
                                                        >
                                                            <span
                                                                class="text-[8px] bg-slate-100/80 text-slate-505 font-extrabold px-1.5 py-0.5 rounded leading-none"
                                                            >
                                                                Min. {fmt(
                                                                    voucher.min_purchase,
                                                                )}
                                                            </span>
                                                            {#if voucher.settings?.terms}
                                                                <button
                                                                    type="button"
                                                                    onclick={() =>
                                                                        toggleTerms(
                                                                            voucher.id,
                                                                        )}
                                                                    class="text-[8px] text-slate-400 hover:text-slate-650 font-extrabold underline cursor-pointer border-0 bg-transparent flex items-center gap-0.5 shrink-0"
                                                                >
                                                                    S&K <i
                                                                        class="ti {expandedTerms[
                                                                            voucher
                                                                                .id
                                                                        ]
                                                                            ? 'ti-chevron-up'
                                                                            : 'ti-chevron-down'} text-[8px]"
                                                                    ></i>
                                                                </button>
                                                            {/if}
                                                        </div>

                                                        {#if minMet}
                                                            <button
                                                                onclick={() =>
                                                                    toggleVoucher(
                                                                        voucher,
                                                                    )}
                                                                class="px-2.5 py-1 rounded-md text-[8.5px] font-black transition cursor-pointer border-0 active:scale-95 leading-none shrink-0 text-white"
                                                                style="background: {isSelected
                                                                    ? '#cbd5e1'
                                                                    : primary}; color: {isSelected
                                                                    ? '#475569'
                                                                    : 'white'};"
                                                            >
                                                                {isSelected
                                                                    ? 'Lepas'
                                                                    : 'Pakai'}
                                                            </button>
                                                        {:else}
                                                            <span
                                                                class="text-[8px] text-rose-500 font-extrabold leading-none shrink-0"
                                                            >
                                                                Kurang {fmt(
                                                                    Number(
                                                                        voucher.min_purchase,
                                                                    ) -
                                                                        selectedCartSubtotal,
                                                                )}
                                                            </span>
                                                        {/if}
                                                    </div>
                                                </div>
                                            </div>
                                            {#if expandedTerms[voucher.id] && voucher.settings?.terms}
                                                <div
                                                    class="px-3.5 py-2 bg-slate-50 border-t border-slate-100 text-[8.5px] text-slate-500 font-bold whitespace-pre-line leading-relaxed"
                                                >
                                                    {voucher.settings.terms}
                                                </div>
                                            {/if}
                                        </div>
                                    {/each}
                                </div>
                            </div>
                        {/if}
                    {/if}
                </div>
            </div>
        </div>
    {/if}
</StorefrontLayout>

<style>
    /* Styling for mobile safe area margins at the bottom */
    .pb-safe {
        padding-bottom: max(0px, env(safe-area-inset-bottom));
    }
</style>
