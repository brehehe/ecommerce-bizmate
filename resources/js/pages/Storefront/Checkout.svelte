<script lang="ts">
    import StorefrontLayout from '@/components/layouts/StorefrontLayout.svelte';
    import { page, router, Link } from '@inertiajs/svelte';
    import { showToast } from '@/utils/toast';
    import { fade } from 'svelte/transition';
    import { untrack } from 'svelte';

    let {
        cartItems = [],
        addresses = [],
        paymentMethods = [],
        activePromotions = [],
        vouchers = [],
        storeName = '',
        storeLogo = '',
        storeOriginCity = null,
        appFee = 0,
        appliedVoucher: initialAppliedVoucher = null,
        couriers = [],
        isNewUser = false,
    } = $props();

    const primary = $derived(
        (page.props as any).theme?.primary_color ?? '#ee4d2d',
    );
    const secondary = $derived(
        (page.props as any).theme?.secondary_color ?? '#fa7315',
    );

    const storeSettings = $derived((page.props as any).settings || {});
    const userIsNew = $derived(isNewUser || (page.props as any).isNewUser || false);

    // Store Open Logic
    const isStoreOpen = $derived.by(() => {
        if (storeSettings.holiday_mode) return false;
        if (storeSettings.always_open) return true;
        if (!storeSettings.operational_hours) return true;

        const now = new Date();
        const days = [
            'sunday',
            'monday',
            'tuesday',
            'wednesday',
            'thursday',
            'friday',
            'saturday',
        ];
        const currentDay = days[now.getDay()];
        const currentHours = storeSettings.operational_hours[currentDay];

        if (!currentHours || !currentHours.active) return false;

        const currentTime = now.getHours() * 60 + now.getMinutes();

        const openParts = currentHours.open.split(':');
        const openTime = parseInt(openParts[0]) * 60 + parseInt(openParts[1]);

        const closeParts = currentHours.close.split(':');
        const closeTime =
            parseInt(closeParts[0]) * 60 + parseInt(closeParts[1]);

        return currentTime >= openTime && currentTime <= closeTime;
    });

    /** Driven by CHECKOUT_LOCKED + CHECKOUT_LOCKED_MESSAGE in .env */
    const isCheckoutLocked = $derived(
        (storeSettings.checkout_locked as boolean) === true,
    );
    const checkoutLockedMessage = $derived(
        (storeSettings.checkout_locked_message as string) ||
            'Checkout sedang dinonaktifkan sementara. Silakan coba lagi nanti.',
    );

    const visibleVouchers = $derived.by(() => {
        const base = (vouchers || []).filter(
            (v: any) =>
                v.type === 'voucher_gratis_ongkir' ||
                v.type === 'voucher_belanja',
        );
        const extra = [];
        if (appliedVoucher && Array.isArray(appliedVoucher.promotions)) {
            for (const p of appliedVoucher.promotions) {
                if (!base.some((b) => b.id === p.id)) {
                    extra.push(p);
                }
            }
        } else if (appliedVoucher && appliedVoucher.promotion) {
            const p = appliedVoucher.promotion;
            if (!base.some((b) => b.id === p.id)) {
                extra.push(p);
            }
        }
        return [...base, ...extra];
    });

    let expandedTerms = $state<Record<number, boolean>>({});

    function toggleTerms(id: number) {
        expandedTerms[id] = !expandedTerms[id];
    }

    const shippingVouchers = $derived(
        visibleVouchers.filter((v: any) => v.type === 'voucher_gratis_ongkir'),
    );

    const discountVouchers = $derived(
        visibleVouchers.filter((v: any) => v.type !== 'voucher_gratis_ongkir'),
    );

    const activeAppliedVouchers = $derived.by(() => {
        if (!appliedVoucher) return [];
        let list: any[] = [];
        if (Array.isArray(appliedVoucher.promotions)) {
            list = appliedVoucher.promotions;
        } else if (appliedVoucher.promotion) {
            list = [appliedVoucher.promotion];
        }
        return list;
    });

    // Selected address
    // svelte-ignore state_referenced_locally
    let selectedAddressId = $state(
        addresses.find((a: any) => a.is_primary)?.id ??
            addresses[0]?.id ??
            null,
    );
    $effect(() => {
        if (!selectedAddressId && addresses && addresses.length > 0) {
            selectedAddressId =
                addresses.find((a: any) => a.is_primary)?.id ??
                addresses[0]?.id;
        }
    });
    let showAddressModal = $state(false);

    // Payment
    // svelte-ignore state_referenced_locally
    let selectedPaymentMethodId = $state(paymentMethods[0]?.id ?? null);
    const selectedPaymentMethod = $derived(
        paymentMethods.find((pm: any) => pm.id === selectedPaymentMethodId),
    );

    // Shipping
    const availableCouriers = $derived.by(() => {
        const list = couriers.map((c: any) => c.code);
        if (storeSettings.store_courier_enabled) {
            list.push('store_courier');
        }
        if (storeSettings.self_pickup_enabled) {
            list.push('self_pickup');
        }
        return list;
    });

    const courierLabels = $derived.by(() => {
        const labels = couriers.reduce(
            (acc: Record<string, string>, c: any) => {
                acc[c.code] = c.name;
                return acc;
            },
            {},
        );
        labels['store_courier'] = 'Kurir Toko';
        labels['self_pickup'] = 'Ambil di Toko';
        return labels;
    });

    let selectedCourier = $state('');
    const selectedCourierId = $derived(
        couriers.find((c: any) => c.code === selectedCourier)?.id ?? null,
    );
    let isInternational = $state(false);
    let internationalCountries = $state([]);
    let selectedCountryId = $state('');
    let loadingCountries = $state(false);

    async function fetchInternationalCountries() {
        if (internationalCountries.length > 0) return;
        loadingCountries = true;
        try {
            const resp = await fetch('/checkout/international-destinations');
            const data = await resp.json();
            if (resp.ok && data.destinations) {
                internationalCountries = data.destinations;
            }
        } catch (e) {
            console.error('Failed to load international countries', e);
        } finally {
            loadingCountries = false;
        }
    }

    $effect(() => {
        if (isInternational) {
            fetchInternationalCountries();
        }
    });

    let searchCourierQuery = $state('');
    let showCourierDropdown = $state(false);
    function closeCourierDropdown(e: MouseEvent) {
        const target = e.target as HTMLElement;
        if (!target.closest('.courier-select-container')) {
            showCourierDropdown = false;
        }
    }
    let shippingOptions: any[] = $state([]);
    let selectedShipping: any = $state(null);
    let loadingShipping = $state(false);
    let shippingError = $state('');
    // Track couriers that returned no available routes for the current address
    let unavailableCouriers = $state<Set<string>>(new Set());

    // Couriers filtered to exclude those with no coverage for current address
    const visibleCouriers = $derived(
        availableCouriers.filter((c: string) => !unavailableCouriers.has(c)),
    );

    async function autoSelectShipping(courierIndex = 0) {
        if (isDigitalOnly) return;
        if (!selectedAddressId) return;
        if (courierIndex >= availableCouriers.length) {
            selectedCourier = '';
            shippingOptions = [];
            selectedShipping = null;
            shippingError =
                'Tidak ada layanan pengiriman yang tersedia untuk alamat ini.';
            return;
        }

        selectedCourier = availableCouriers[courierIndex];
        await fetchShipping();

        if (!shippingOptions || shippingOptions.length === 0) {
            await autoSelectShipping(courierIndex + 1);
        }
    }

    let lastAddressId = $state(untrack(() => selectedAddressId));
    let initialAutoSelectDone = $state(false);

    $effect(() => {
        if (isDigitalOnly) return;
        if (
            selectedAddressId &&
            !initialAutoSelectDone &&
            availableCouriers.length > 0
        ) {
            initialAutoSelectDone = true;
            autoSelectShipping(0);
        } else if (selectedAddressId && selectedAddressId !== lastAddressId) {
            lastAddressId = selectedAddressId;
            // Reset unavailable couriers — may differ per address
            unavailableCouriers = new Set();
            if (selectedCourier) {
                fetchShipping().then(() => {
                    if (!shippingOptions || shippingOptions.length === 0) {
                        autoSelectShipping(0);
                    }
                });
            } else {
                autoSelectShipping(0);
            }
        }
    });

    // Voucher
    let initialVoucherCode = $derived.by(() => {
        if (!initialAppliedVoucher) return '';
        if (Array.isArray(initialAppliedVoucher.promotions)) {
            return initialAppliedVoucher.promotions
                .map((p: any) => p.code)
                .join(',');
        } else if (initialAppliedVoucher.promotion) {
            return initialAppliedVoucher.promotion.code;
        }
        return '';
    });

    // svelte-ignore state_referenced_locally
    let voucherInputCode = $state(initialVoucherCode);
    let manualPromoCode = $state('');
    // svelte-ignore state_referenced_locally
    let appliedVoucher: any = $state(initialAppliedVoucher);
    let voucherError = $state('');
    let voucherLoading = $state(false);
    let voucherModalOpen = $state(false);

    // Read query parameter and apply voucher reactively using page.url to prevent SPA race conditions
    const queryVoucher = $derived.by(() => {
        const urlStr = page.url;
        if (!urlStr) return '';
        try {
            const url = new URL(urlStr, 'http://localhost');
            return url.searchParams.get('voucher') ?? '';
        } catch {
            return '';
        }
    });

    // svelte-ignore state_referenced_locally
    let lastAppliedQueryVoucher = $state(initialVoucherCode);
    $effect(() => {
        const currentQuery = queryVoucher;
        if (currentQuery && currentQuery !== lastAppliedQueryVoucher) {
            lastAppliedQueryVoucher = currentQuery;
            voucherInputCode = currentQuery;
            applyVoucherCodes(currentQuery);
        }
    });

    // Reactive effect to re-apply vouchers if shipping fee changes to ensure shipping discounts calculate correctly
    $effect(() => {
        if (voucherInputCode && shippingFee > 0) {
            applyVoucherCodes(voucherInputCode);
        }
    });

    function selectVoucher(voucher: any) {
        let codes = voucherInputCode
            .split(',')
            .map((c) => c.trim().toUpperCase())
            .filter(Boolean);

        if (voucher.type === 'voucher_gratis_ongkir') {
            const existingShipping = vouchers.find(
                (v) =>
                    v.type === 'voucher_gratis_ongkir' &&
                    codes.includes(v.code.toUpperCase()),
            );
            if (existingShipping) {
                codes = codes.filter(
                    (c) => c !== existingShipping.code.toUpperCase(),
                );
            }
            codes.push(voucher.code.toUpperCase());
        } else {
            const existingDiscount = vouchers.find(
                (v) =>
                    v.type !== 'voucher_gratis_ongkir' &&
                    codes.includes(v.code.toUpperCase()),
            );
            if (existingDiscount) {
                codes = codes.filter(
                    (c) => c !== existingDiscount.code.toUpperCase(),
                );
            }
            codes.push(voucher.code.toUpperCase());
        }

        voucherInputCode = codes.join(',');
        applyVoucherCodes(voucherInputCode);
    }

    function deselectVoucher(voucher: any) {
        let codes = voucherInputCode
            .split(',')
            .map((c) => c.trim().toUpperCase())
            .filter(Boolean);
        codes = codes.filter((c) => c !== voucher.code.toUpperCase());
        voucherInputCode = codes.join(',');
        applyVoucherCodes(voucherInputCode);
    }

    // Notes
    let orderNotes = $state('');
    let itemNotes = $state<Record<number, string>>({});
    const isDigitalOnly = $derived(
        (cartItems || []).every((item: any) => item.product?.is_digital),
    );

    // Submitting
    let isSubmitting = $state(false);

    function fmt(price: any): string {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
        }).format(Number(price) || 0);
    }

    function formatNumber(num: number): string {
        return new Intl.NumberFormat('id-ID').format(num);
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

    /**
     * Returns the correct image src:
     * - Full URLs (http/https) are returned as-is
     * - Paths starting with / are returned as-is
     * - Other paths get a leading /
     * - null/empty → noimage fallback
     */
    function formatImagePath(path: string | null | undefined): string {
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

    function getCartItemImage(item: any): string {
        const variant = item.productVariant ?? item.product_variant;
        const product = item.product;
        if (variant?.image) return formatImagePath(variant.image);
        if (product?.images?.length > 0) {
            return formatImagePath(
                product.images[0].url || product.images[0].path,
            );
        }
        if (product?.image) return formatImagePath(product.image);
        return '/noimage/image.png';
    }

    // --- Bundling eligibility (mirrors Cart.svelte) ---
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
        // In checkout all items are checked
        const currentQty = cartItem ? cartItem.quantity : 0;
        const requiredQty = Number(buyItem.qty);
        return {
            inCart: !!cartItem,
            currentQty,
            requiredQty,
            isMet: currentQty >= requiredQty,
        };
    }

    function checkPromoEligibility(promo: any): boolean {
        const bundle = promo.settings?.bundle;
        if (!bundle || !bundle.buy_items) return false;
        return bundle.buy_items.every(
            (buyItem: any) => getBuyItemCartStatus(buyItem).isMet,
        );
    }

    const eligibleBundlingPromos = $derived(
        (activePromotions as any[]).filter(
            (p) => p.type === 'bundling_gift' && checkPromoEligibility(p),
        ),
    );

    const selectedAddress = $derived(
        addresses.find((a: any) => a.id === selectedAddressId),
    );

    // Calculate totals
    const subtotal = $derived(
        cartItems.reduce(
            (acc: number, item: any) =>
                acc + Number(item.unit_price ?? 0) * item.quantity,
            0,
        ),
    );
    const shippingFee = $derived(
        isDigitalOnly
            ? 0
            : Number(
                  selectedShipping ? (selectedShipping.cost[0]?.value ?? 0) : 0,
              ),
    );
    const shippingDiscount = $derived(
        Number(appliedVoucher?.shipping_discount ?? 0),
    );
    const discountAmount = $derived(
        Number(appliedVoucher?.discount_amount ?? 0),
    );
    const adminFee = $derived(
        Number((selectedPaymentMethod as any)?.admin_fee ?? 0),
    );
    const applicationFee = $derived(Number(appFee ?? 0));

    // Coins Integration
    let useCoins = $state(false);

    // Dynamic calculations for coins
    const coinsSettings = $derived((page.props as any).settings);
    const authUser = $derived((page.props as any).auth?.user);
    const coinsEnabled = $derived(coinsSettings?.coins_enabled);
    const coinConversionRate = $derived(
        Number(coinsSettings?.coin_conversion_rate || 1),
    );
    const coinMinPurchaseRedeem = $derived(
        Number(coinsSettings?.coin_min_purchase_redeem || 0),
    );
    const coinMaxRedeemPerTxn = $derived(
        Number(coinsSettings?.coin_max_redeem_per_txn || 50000),
    );
    const coinMaxRedeemPercentage = $derived(
        Number(coinsSettings?.coin_max_redeem_percentage || 100),
    );

    // User's balance
    const userCoinsBalance = $derived(Number(authUser?.coins_balance || 0));
    const userCoinsValue = $derived(userCoinsBalance * coinConversionRate);

    // Validate if user meets minimum purchase rule to redeem coins
    const isCoinsMinPurchaseMet = $derived(subtotal >= coinMinPurchaseRedeem);

    // Calculate maximum coins we can redeem for this order
    const maxCoinsRedeemValueAllowed = $derived.by(() => {
        // Max based on percentage of order subtotal
        const maxPercentValue = (subtotal * coinMaxRedeemPercentage) / 100;
        // Max cap per txn in Rupiah
        const maxCapValue = coinMaxRedeemPerTxn;

        return Math.min(userCoinsValue, maxPercentValue, maxCapValue);
    });

    const maxCoinsRedeemAllowed = $derived(
        Math.floor(maxCoinsRedeemValueAllowed / coinConversionRate),
    );

    // Dynamic discount applied by coins
    const coinDiscountAmount = $derived.by(() => {
        if (
            !useCoins ||
            !coinsEnabled ||
            !isCoinsMinPurchaseMet ||
            maxCoinsRedeemAllowed <= 0
        ) {
            return 0;
        }
        return maxCoinsRedeemAllowed * coinConversionRate;
    });

    // Prospective coins earned
    const coinEarningMethod = $derived(
        coinsSettings?.coin_earning_method || 'proportional',
    );
    const coinEarningRateRupiah = $derived(
        Number(coinsSettings?.coin_earning_rate_rupiah || 1000),
    );
    const coinEarningRateCoins = $derived(
        Number(coinsSettings?.coin_earning_rate_coins || 1),
    );
    const coinEarningTiers = $derived(coinsSettings?.coin_earning_tiers || []);

    const prospectiveCoinsEarned = $derived.by(() => {
        if (!coinsEnabled) return 0;

        if (appliedVoucher && appliedVoucher.is_points_voucher) {
            return Number(appliedVoucher.voucher_points || 0);
        }

        if (useCoins && coinDiscountAmount > 0) return 0;

        if (coinEarningMethod === 'proportional') {
            if (coinEarningRateRupiah <= 0) return 0;
            return (
                Math.floor(subtotal / coinEarningRateRupiah) *
                coinEarningRateCoins
            );
        } else if (coinEarningMethod === 'tiered') {
            const sortedTiers = [...coinEarningTiers].sort(
                (a, b) => Number(b.min_purchase) - Number(a.min_purchase),
            );
            for (const tier of sortedTiers) {
                if (subtotal >= Number(tier.min_purchase)) {
                    return Number(tier.earn_coins);
                }
            }
        }
        return 0;
    });

    const grandTotal = $derived(
        Math.max(
            0,
            subtotal -
                discountAmount -
                coinDiscountAmount +
                (shippingFee - shippingDiscount) +
                adminFee +
                applicationFee,
        ),
    );

    // Total weight for shipping
    const totalWeightGrams = $derived(
        cartItems.reduce((acc: number, item: any) => {
            if (item.product?.is_digital) {
                return acc;
            }
            const w =
                (item.productVariant ?? item.product_variant)?.weight ??
                item.product?.weight ??
                1000;
            return acc + w * item.quantity;
        }, 0),
    );

    function getCleanEtd(etd: any, service: string = ''): string {
        if (!etd) {
            return getFallbackEtdRaw(service);
        }
        const clean = etd
            .toString()
            .toLowerCase()
            .replace(/days?|hari/gi, '')
            .trim();
        if (clean === '' || clean === '-') {
            return getFallbackEtdRaw(service);
        }
        return clean;
    }

    function getFallbackEtdRaw(service: string): string {
        const s = service.toLowerCase();
        if (s.includes('cargo') || s.includes('gokil') || s.includes('jtr')) {
            return '3-7';
        }
        if (
            s.includes('instant') ||
            s.includes('sameday') ||
            s.includes('same day') ||
            s.includes('gojek') ||
            s.includes('grab')
        ) {
            return '0';
        }
        return '2-4';
    }

    function getEtdLabel(etd: any, service: string = ''): string {
        const clean = getCleanEtd(etd, service);
        if (
            clean === '0' ||
            clean === '0-0' ||
            clean === '0-0 hari' ||
            clean === '0 - 0'
        ) {
            return 'Hari yang sama (Same Day)';
        }
        return `Estimasi: ${clean} hari`;
    }

    async function fetchShipping() {
        if (isInternational && !selectedCountryId) {
            shippingError = 'Pilih negara tujuan terlebih dahulu.';
            return;
        }
        if (selectedCourier !== 'self_pickup' && !selectedAddress) return;
        if (!selectedCourier) return;

        loadingShipping = true;
        shippingError = '';
        shippingOptions = [];
        selectedShipping = null;

        try {
            const resp = await fetch('/checkout/shipping-cost', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as any
                        )?.content ?? '',
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    destination:
                        selectedCourier === 'self_pickup'
                            ? ''
                            : isInternational
                              ? selectedCountryId
                              : (selectedAddress?.city_id ??
                                selectedAddress?.regency_id ??
                                ''),
                    weight:
                        Math.max(1, Math.ceil(totalWeightGrams / 1000)) * 1000,
                    courier: selectedCourier,
                    is_international: isInternational,
                    address_id:
                        selectedCourier === 'self_pickup'
                            ? null
                            : (selectedAddress?.id ?? null),
                }),
            });

            const data = await resp.json();
            if (resp.ok && data.results && data.results.length > 0) {
                // Collect costs from ALL result groups (Biteship returns all services
                // for one courier grouped; other providers may return multiple groups)
                const allServices: any[] = [];
                for (const result of data.results) {
                    const costs = result.costs ?? [];
                    allServices.push(...costs);
                }
                shippingOptions = allServices;
                if (shippingOptions.length > 0) {
                    shippingOptions.sort(
                        (a: any, b: any) =>
                            (a.cost?.[0]?.value ?? 0) -
                            (b.cost?.[0]?.value ?? 0),
                    );
                    selectedShipping = shippingOptions[0];
                    // Mark courier as available for this address
                    unavailableCouriers = new Set(
                        [...unavailableCouriers].filter(
                            (c) => c !== selectedCourier,
                        ),
                    );
                } else {
                    // No services under this courier — mark as unavailable
                    unavailableCouriers = new Set([
                        ...unavailableCouriers,
                        selectedCourier,
                    ]);
                    shippingError =
                        'Tidak ada layanan pengiriman tersedia untuk kurir ini.';
                }
            } else {
                // API returned error or empty — mark courier as unavailable
                unavailableCouriers = new Set([
                    ...unavailableCouriers,
                    selectedCourier,
                ]);
                shippingError =
                    data.error ?? 'Tidak ada layanan pengiriman tersedia.';
            }
        } catch {
            shippingError = 'Gagal memuat ongkir. Coba lagi.';
        } finally {
            loadingShipping = false;
        }
    }

    async function applyVoucherCodes(codesStr: string) {
        if (!codesStr.trim()) {
            appliedVoucher = null;
            voucherInputCode = '';
            return;
        }
        voucherLoading = true;
        voucherError = '';

        try {
            const resp = await fetch('/checkout/apply-voucher', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as any
                        )?.content ?? '',
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    code: codesStr,
                    subtotal: subtotal,
                    shipping_fee: shippingFee,
                }),
            });

            const data = await resp.json();
            if (data.valid) {
                appliedVoucher = data;
                voucherInputCode = codesStr;
            } else {
                voucherError = data.message ?? 'Voucher tidak valid.';
            }
        } catch {
            voucherError = 'Gagal memvalidasi voucher.';
        } finally {
            voucherLoading = false;
        }
    }

    async function applyVoucher() {
        const codeToApply = manualPromoCode.trim().toUpperCase();
        if (!codeToApply) return;

        voucherLoading = true;
        voucherError = '';

        // Add codeToApply to the comma-separated list
        let codes = voucherInputCode
            .split(',')
            .map((c) => c.trim().toUpperCase())
            .filter(Boolean);
        if (!codes.includes(codeToApply)) {
            codes.push(codeToApply);
        }

        const newCodesStr = codes.join(',');

        try {
            const resp = await fetch('/checkout/apply-voucher', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as any
                        )?.content ?? '',
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    code: newCodesStr,
                    subtotal: subtotal,
                    shipping_fee: shippingFee,
                }),
            });

            const data = await resp.json();
            if (data.valid) {
                appliedVoucher = data;
                voucherInputCode = newCodesStr;
                manualPromoCode = ''; // Clear manual input upon success!
                showToast(
                    data.message ?? 'Voucher berhasil diterapkan!',
                    'success',
                );
            } else {
                voucherError = data.message ?? 'Voucher tidak valid.';
            }
        } catch {
            voucherError = 'Gagal memvalidasi voucher.';
        } finally {
            voucherLoading = false;
        }
    }

    function removeVoucher() {
        appliedVoucher = null;
        voucherInputCode = '';
        voucherError = '';
        manualPromoCode = '';
    }

    function submitCheckout() {
        if (isCheckoutLocked) {
            showToast(checkoutLockedMessage, 'error');
            return;
        }
        if (!isStoreOpen) {
            showToast(
                'Toko sedang tutup. Checkout tidak tersedia saat ini.',
                'error',
            );
            return;
        }
        if (!isDigitalOnly) {
            if (selectedCourier !== 'self_pickup' && !selectedAddressId) {
                showToast('Pilih alamat pengiriman terlebih dahulu.', 'error');
                return;
            }
            if (!selectedCourier) {
                showToast('Pilih kurir pengiriman terlebih dahulu.', 'error');
                return;
            }
            if (!selectedShipping) {
                showToast('Pilih layanan pengiriman terlebih dahulu.', 'error');
                return;
            }
        }
        if (!selectedPaymentMethodId) {
            showToast('Pilih metode pembayaran.', 'error');
            return;
        }

        isSubmitting = true;

        router.post(
            '/checkout',
            {
                customer_address_id: isDigitalOnly
                    ? null
                    : selectedCourier === 'self_pickup'
                      ? null
                      : selectedAddressId,
                payment_method_id: selectedPaymentMethodId,
                courier_id: isDigitalOnly ? null : selectedCourierId,
                shipping_courier: isDigitalOnly ? 'digital' : selectedCourier,
                shipping_service: isDigitalOnly
                    ? 'email'
                    : (selectedShipping?.service ?? ''),
                shipping_etd: isDigitalOnly
                    ? '0'
                    : getCleanEtd(
                          selectedShipping?.cost?.[0]?.etd,
                          selectedShipping?.service ||
                              selectedShipping?.description,
                      ),
                shipping_fee: isDigitalOnly ? 0 : shippingFee,
                voucher_code: voucherInputCode ?? '',
                notes: orderNotes,
                use_coins: useCoins,
                item_notes: itemNotes,
            },
            {
                onError: () => {
                    isSubmitting = false;
                    showToast(
                        'Terjadi kesalahan. Periksa data dan coba lagi.',
                        'error',
                    );
                },
                onFinish: () => {
                    isSubmitting = false;
                },
            },
        );
    }

    // ─── Promo Expiry Tracking ───────────────────────────────────────────────
    // Collect all unique promo end times from cart items (flash sale, promo_produk, etc.)
    const promoEndTimes = $derived.by(() => {
        const times: { name: string; endTime: Date }[] = [];
        const seen = new Set<string>();
        for (const item of cartItems as any[]) {
            const src = (item.productVariant ??
                item.product_variant ??
                item.product) as any;
            if (src?.promo_end_time && src?.is_promo && src?.promo_type) {
                const key = src.promo_end_time;
                if (!seen.has(key)) {
                    seen.add(key);
                    const d = new Date(String(key).replace(' ', 'T'));
                    if (!isNaN(d.getTime())) {
                        times.push({
                            name:
                                src.promo_type === 'flash_sale'
                                    ? 'Flash Sale'
                                    : src.promo_type === 'promo_produk'
                                      ? 'Promo Produk'
                                      : src.promo_type === 'bundling_gift'
                                        ? 'Bundling Gift'
                                        : 'Promo',
                            endTime: d,
                        });
                    }
                }
            }
        }
        // Sort by soonest to expire first
        return times.sort((a, b) => a.endTime.getTime() - b.endTime.getTime());
    });

    let promoCountdowns = $state<
        { name: string; remaining: number; label: string }[]
    >([]);
    let promoExpiredNames = $state<string[]>([]);
    let promoExpiryTimer: ReturnType<typeof setInterval> | null = null;

    function computePromoCountdowns() {
        const now = Date.now();
        const updated: typeof promoCountdowns = [];
        const expired: string[] = [];

        for (const item of promoEndTimes) {
            const diff = item.endTime.getTime() - now;
            if (diff <= 0) {
                expired.push(item.name);
            } else {
                const h = Math.floor(diff / 3_600_000);
                const m = Math.floor((diff % 3_600_000) / 60_000);
                const s = Math.floor((diff % 60_000) / 1_000);
                const label =
                    h > 0
                        ? `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
                        : `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
                updated.push({ name: item.name, remaining: diff, label });
            }
        }

        promoCountdowns = updated;

        // If newly expired promos detected, reload to get fresh pricing from server
        if (expired.length > 0) {
            const newExpired = expired.filter(
                (n) => !promoExpiredNames.includes(n),
            );
            if (newExpired.length > 0) {
                promoExpiredNames = [...promoExpiredNames, ...newExpired];
                showToast(
                    `${newExpired.join(', ')} telah berakhir. Harga diperbarui ke harga normal.`,
                    'error',
                );
                // Reload checkout page so server recalculates prices without the expired promo
                setTimeout(() => router.reload(), 1500);
            }
        }
    }

    $effect(() => {
        if (promoEndTimes.length > 0) {
            computePromoCountdowns();
            promoExpiryTimer = setInterval(computePromoCountdowns, 1000);
        } else {
            if (promoExpiryTimer) clearInterval(promoExpiryTimer);
            promoExpiryTimer = null;
        }
        return () => {
            if (promoExpiryTimer) clearInterval(promoExpiryTimer);
        };
    });

    // Soonest expiring promo (to show main warning banner)
    const soonestPromoCountdown = $derived(
        promoCountdowns.length > 0 ? promoCountdowns[0] : null,
    );

    // Show urgent warning when promo expires in less than 5 minutes
    const isPromoExpiringSoon = $derived(
        soonestPromoCountdown !== null &&
            soonestPromoCountdown.remaining < 5 * 60 * 1000,
    );

    const statusColors: Record<string, string> = {
        belum_bayar: '#f59e0b',
        menunggu: '#3b82f6',
        diproses: '#8b5cf6',
        dikemas: '#06b6d4',
        dikirim: '#f97316',
        selesai: '#22c55e',
        batal: '#ef4444',
    };
</script>

<svelte:window onclick={closeCourierDropdown} />

<StorefrontLayout {storeName} {storeLogo} hideMobileFooter={true}>
    <div class="min-h-dvh bg-slate-50">
        <!-- Header -->
        <div class="bg-white border-b border-slate-200 sticky top-0 z-30">
            <div class="max-w-6xl mx-auto px-4 h-14 flex items-center gap-3">
                <button
                    aria-label="Kembali"
                    onclick={() => window.history.back()}
                    class="p-2 hover:bg-slate-100 rounded-full transition"
                >
                    <i class="ti ti-arrow-left text-xl text-slate-700"></i>
                </button>
                <h1 class="text-base font-bold text-slate-800">Checkout</h1>
            </div>
        </div>

        <div class="max-w-6xl mx-auto px-4 py-4 pb-24">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <!-- Left Column -->
                <div class="lg:col-span-2 space-y-4">
                    <!-- Promo Expiry Warning Banner -->
                    {#if promoCountdowns.length > 0}
                        <div
                            class="rounded-2xl px-4 py-3 flex items-center gap-3 border {isPromoExpiringSoon
                                ? 'bg-red-50 border-red-200'
                                : 'bg-amber-50 border-amber-200'}"
                        >
                            <div
                                class="shrink-0 w-9 h-9 rounded-full flex items-center justify-center {isPromoExpiringSoon
                                    ? 'bg-red-100'
                                    : 'bg-amber-100'}"
                            >
                                <i
                                    class="ti ti-clock-exclamation text-lg {isPromoExpiringSoon
                                        ? 'text-red-500'
                                        : 'text-amber-500'}"
                                ></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p
                                    class="text-xs font-bold {isPromoExpiringSoon
                                        ? 'text-red-700'
                                        : 'text-amber-700'}"
                                >
                                    {soonestPromoCountdown?.name} berakhir dalam
                                    <span class="font-black tabular-nums"
                                        >{soonestPromoCountdown?.label}</span
                                    >
                                </p>
                                <p
                                    class="text-[11px] {isPromoExpiringSoon
                                        ? 'text-red-500'
                                        : 'text-amber-500'} mt-0.5"
                                >
                                    Segera selesaikan checkout sebelum harga
                                    kembali normal.
                                </p>
                            </div>
                        </div>
                    {/if}

                    {#if promoExpiredNames.length > 0}
                        <div
                            class="rounded-2xl px-4 py-3 flex items-center gap-3 bg-slate-50 border border-slate-200"
                        >
                            <div
                                class="shrink-0 w-9 h-9 rounded-full flex items-center justify-center bg-slate-100"
                            >
                                <i
                                    class="ti ti-refresh text-lg text-slate-500 animate-spin"
                                ></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-bold text-slate-700">
                                    Memperbarui harga...
                                </p>
                                <p class="text-[11px] text-slate-500 mt-0.5">
                                    {promoExpiredNames.join(', ')} telah berakhir.
                                    Harga sedang diperbarui ke harga normal.
                                </p>
                            </div>
                        </div>
                    {/if}

                    {#if !isDigitalOnly}
                        <!-- Address Section -->
                        <div
                            class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden"
                        >
                            {#if selectedCourier === 'self_pickup'}
                                <div
                                    class="py-2.5 px-4 flex items-center justify-between border-b border-slate-100 bg-slate-50"
                                >
                                    <div class="flex items-center gap-2">
                                        <i
                                            class="ti ti-building-store text-lg text-emerald-600"
                                        ></i>
                                        <span
                                            class="font-bold text-slate-800 text-sm"
                                            >Lokasi Pengambilan (Ambil di Toko)</span
                                        >
                                    </div>
                                </div>
                                <div class="py-3 px-4">
                                    <p
                                        class="text-xs font-bold text-slate-800 mb-1"
                                    >
                                        {storeSettings.store_name || storeName}
                                    </p>
                                    <p
                                        class="text-xs text-slate-600 leading-relaxed font-medium"
                                    >
                                        {storeSettings.store_address}
                                        {#if storeSettings.store_village}, {storeSettings.store_village}{/if}
                                        {#if storeSettings.store_district}, {storeSettings.store_district}{/if}
                                        {#if storeSettings.store_regency}, {storeSettings.store_regency}{/if}
                                        {#if storeSettings.store_province}, {storeSettings.store_province}{/if}
                                        {#if storeSettings.store_postal_code}
                                            {storeSettings.store_postal_code}{/if}
                                    </p>
                                    <div
                                        class="mt-2.5 p-2.5 bg-emerald-50 rounded-xl border border-emerald-100 flex items-center gap-2 text-[11px] font-bold text-emerald-700"
                                    >
                                        <i
                                            class="ti ti-info-circle text-sm shrink-0"
                                        ></i>
                                        <span
                                            >Pesanan Anda akan disiapkan di
                                            toko. Anda dapat mengambilnya
                                            setelah status pembayaran selesai.</span
                                        >
                                    </div>
                                </div>
                            {:else}
                                <div
                                    class="py-2.5 px-4 flex items-center justify-between border-b border-slate-100"
                                >
                                    <div class="flex items-center gap-2">
                                        <i
                                            class="ti ti-map-pin text-lg"
                                            style="color:{primary}"
                                        ></i>
                                        <span
                                            class="font-bold text-slate-800 text-sm"
                                            >Alamat Pengiriman</span
                                        >
                                    </div>
                                    <button
                                        onclick={() =>
                                            (showAddressModal = true)}
                                        class="text-xs font-bold px-3 py-1.5 rounded-lg bg-slate-50 hover:bg-slate-100 transition"
                                        style="color:{primary}"
                                    >
                                        Ganti Alamat
                                    </button>
                                </div>

                                {#if selectedAddress}
                                    <div class="py-3 px-4">
                                        <div class="flex items-start gap-2">
                                            <div class="flex-1 min-w-0">
                                                <div
                                                    class="flex items-center gap-2 flex-wrap mb-1"
                                                >
                                                    <span
                                                        class="font-bold text-slate-800 text-sm"
                                                        >{selectedAddress.receiver_name}</span
                                                    >
                                                    <span
                                                        class="text-xs text-slate-500 font-medium"
                                                        >{selectedAddress.phone_number}</span
                                                    >
                                                    {#if selectedAddress.is_primary}
                                                        <span
                                                            class="text-[10px] font-black px-2 py-0.5 rounded-full text-white uppercase tracking-wider"
                                                            style="background:{primary}"
                                                            >Utama</span
                                                        >
                                                    {/if}
                                                </div>
                                                <p
                                                    class="text-xs text-slate-600 leading-relaxed font-medium"
                                                >
                                                    {selectedAddress.full_address}
                                                    {#if selectedAddress.village_name},
                                                        {selectedAddress.village_name}{/if}
                                                    {#if selectedAddress.district_name},
                                                        {selectedAddress.district_name}{/if}
                                                    {#if selectedAddress.regency_name},
                                                        {selectedAddress.regency_name}{/if}
                                                    {#if selectedAddress.province_name},
                                                        {selectedAddress.province_name}{/if}
                                                    {#if selectedAddress.postal_code}
                                                        {selectedAddress.postal_code}{/if}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                {:else}
                                    <div class="py-3 px-4">
                                        <div
                                            class="bg-amber-50 border border-amber-200 rounded-xl p-3 text-amber-700 text-sm font-medium"
                                        >
                                            <i class="ti ti-alert-triangle mr-1"
                                            ></i>
                                            Belum ada alamat.
                                            <Link
                                                href="/profile/addresses?from=checkout"
                                                class="font-black underline"
                                                >Tambah Alamat</Link
                                            >
                                        </div>
                                    </div>
                                {/if}
                            {/if}
                        </div>
                    {/if}

                    <!-- Product List -->
                    <div
                        class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden"
                    >
                        <div class="px-4 pt-4 pb-2">
                            <div class="flex items-center gap-2">
                                <i
                                    class="ti ti-package text-lg"
                                    style="color:{primary}"
                                ></i>
                                <span class="font-bold text-slate-800 text-sm"
                                    >Produk Dipesan ({cartItems.length} item)</span
                                >
                            </div>
                        </div>
                        <div class="divide-y divide-slate-100">
                            {#each cartItems as item}
                                {@const product = item.product}
                                {@const variant =
                                    item.productVariant ?? item.product_variant}
                                {@const imgUrl = getCartItemImage(item)}
                                <div class="px-4 py-3.5">
                                    <div class="flex gap-3">
                                        <img
                                            src={imgUrl}
                                            alt={product?.name}
                                            class="w-14 h-14 object-cover rounded-lg shrink-0 border border-slate-100"
                                            onerror={(e: any) => {
                                                e.target.src =
                                                    '/noimage/image.png';
                                            }}
                                        />
                                        <div class="flex-1 min-w-0">
                                            <div
                                                class="flex items-center gap-1.5 flex-wrap"
                                            >
                                                <p
                                                    class="text-sm font-semibold text-slate-800 leading-tight truncate"
                                                >
                                                    {product?.name}
                                                </p>
                                                {#if product?.is_digital}
                                                    <span
                                                        class="text-[9px] font-black px-1.5 py-0.5 rounded bg-blue-50 text-blue-600 border border-blue-100 shrink-0 uppercase tracking-wider"
                                                    >
                                                        Digital
                                                    </span>
                                                {/if}
                                            </div>
                                            {#if variant}
                                                <p
                                                    class="text-xs text-slate-500 mt-0.5"
                                                >
                                                    {variant.options
                                                        ?.map(
                                                            (o: any) => o.name,
                                                        )
                                                        .join(' / ')}
                                                </p>
                                            {/if}
                                            <div
                                                class="flex items-center justify-between mt-1.5"
                                            >
                                                <span
                                                    class="text-xs text-slate-500"
                                                    >x{item.quantity}</span
                                                >
                                                <span
                                                    class="text-sm font-bold"
                                                    style="color:{primary}"
                                                    >{fmt(
                                                        item.unit_price,
                                                    )}</span
                                                >
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        class="mt-2"
                                        style="padding-left: 4.25rem;"
                                    >
                                        <input
                                            type="text"
                                            bind:value={itemNotes[item.id]}
                                            placeholder="Catatan untuk item ini (opsional)..."
                                            class="w-full px-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-slate-300 font-medium"
                                        />
                                    </div>
                                </div>
                            {/each}
                        </div>
                        <div
                            class="px-4 py-3 border-t border-slate-100 flex justify-between"
                        >
                            <span class="text-sm text-slate-600"
                                >Subtotal Produk</span
                            >
                            <span class="text-sm font-bold text-slate-800"
                                >{fmt(subtotal)}</span
                            >
                        </div>

                        <!-- Bundling Gift Section -->
                        {#if eligibleBundlingPromos.length > 0}
                            <div
                                class="px-4 pb-4 pt-2 border-t border-slate-100"
                            >
                                <div class="flex items-center gap-2 mb-3">
                                    <span
                                        class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-xs shrink-0"
                                    >
                                        <i class="ti ti-gift-filled"></i>
                                    </span>
                                    <h3
                                        class="font-bold text-sm text-slate-800 uppercase tracking-wider"
                                    >
                                        Hadiah &amp; Bundling Gratis
                                    </h3>
                                </div>
                                <div class="space-y-2">
                                    {#each eligibleBundlingPromos as promo}
                                        {@const bundle = promo.settings.bundle}
                                        {@const buyNames = bundle.buy_items
                                            .map(
                                                (bi: any) =>
                                                    bi.product_name ||
                                                    'Produk Syarat',
                                            )
                                            .join(' + ')}
                                        {#each bundle.get_items as gift}
                                            <div
                                                class="flex gap-3 p-3 rounded-xl bg-gradient-to-br from-emerald-50 to-teal-50 border border-emerald-100"
                                            >
                                                <img
                                                    src={formatImagePath(
                                                        gift.product_image,
                                                    )}
                                                    alt={gift.product_name}
                                                    class="w-14 h-14 rounded-lg object-cover border border-slate-100 shrink-0"
                                                    onerror={(e: any) => {
                                                        e.target.src =
                                                            '/noimage/image.png';
                                                    }}
                                                />
                                                <div class="flex-1 min-w-0">
                                                    <div
                                                        class="flex items-center gap-1.5 flex-wrap mb-0.5"
                                                    >
                                                        <span
                                                            class="text-[10px] font-black px-2 py-0.5 rounded-full bg-emerald-500 text-white"
                                                            >HADIAH</span
                                                        >
                                                        <span
                                                            class="text-[10px] font-bold text-slate-500"
                                                            >x{gift.qty ?? 1} pcs</span
                                                        >
                                                    </div>
                                                    <p
                                                        class="text-sm font-semibold text-slate-800 leading-tight"
                                                    >
                                                        {gift.product_name}
                                                    </p>
                                                    <p
                                                        class="text-xs text-slate-500 mt-0.5"
                                                    >
                                                        Bundling: {buyNames}
                                                    </p>
                                                    <div
                                                        class="flex items-center gap-2 mt-1"
                                                    >
                                                        <span
                                                            class="text-xs line-through text-slate-400"
                                                            >{fmt(
                                                                gift.product_price ??
                                                                    0,
                                                            )}</span
                                                        >
                                                        <span
                                                            class="text-xs font-black text-emerald-600"
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
                    </div>

                    {#if !isDigitalOnly}
                        <!-- Shipping Section -->
                        <div
                            class="bg-white rounded-2xl shadow-sm border border-slate-100"
                        >
                            <div class="px-4 pt-4 pb-3">
                                <div class="flex items-center gap-2 mb-3">
                                    <i
                                        class="ti ti-truck text-lg"
                                        style="color:{primary}"
                                    ></i>
                                    <span
                                        class="font-bold text-slate-800 text-sm"
                                        >Pengiriman</span
                                    >
                                </div>

                                {#if selectedCourier !== 'self_pickup' && !selectedAddress}
                                    <p class="text-xs text-slate-400 italic">
                                        Pilih alamat terlebih dahulu untuk
                                        mengecek ongkir.
                                    </p>
                                {:else}
                                    <!-- International Shipping Option -->
                                    <!-- <div class="mb-4 p-3.5 bg-slate-50 border border-slate-100 rounded-2xl">
                                    <label class="flex items-center justify-between cursor-pointer">
                                        <div class="flex flex-col">
                                            <span class="text-xs font-bold text-slate-800">Kirim ke Luar Negeri?</span>
                                            <span class="text-[10px] text-slate-500">Gunakan Komerce RajaOngkir Internasional</span>
                                        </div>
                                        <input
                                            type="checkbox"
                                            bind:checked={isInternational}
                                            class="rounded text-indigo-600 focus:ring-indigo-500 h-4 w-4"
                                            onclick={() => {
                                                selectedShipping = null;
                                                shippingOptions = [];
                                                selectedCourier = '';
                                                selectedCountryId = '';
                                            }}
                                        />
                                    </label>
                                    
                                    {#if isInternational}
                                        <div class="mt-3">
                                            <p class="text-xs font-semibold text-slate-500 mb-1.5">Pilih Negara Tujuan</p>
                                            <div class="relative">
                                                <select
                                                    bind:value={selectedCountryId}
                                                    onchange={() => {
                                                        selectedShipping = null;
                                                        shippingOptions = [];
                                                        if (selectedCourier) fetchShipping();
                                                    }}
                                                    class="w-full p-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-slate-100 transition-all font-medium text-slate-700"
                                                >
                                                    <option value="">-- Pilih Negara --</option>
                                                    {#each internationalCountries as country}
                                                        <option value={country.country_id}>{country.country_name}</option>
                                                    {/each}
                                                </select>
                                                {#if loadingCountries}
                                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] text-slate-400">Loading...</span>
                                                {/if}
                                            </div>
                                        </div>
                                    {/if}
                                </div> -->

                                    <!-- Courier selection -->
                                    <div
                                        class="mb-3 relative courier-select-container"
                                    >
                                        <div
                                            class="flex items-center justify-between mb-2"
                                        >
                                            <p
                                                class="text-xs font-semibold text-slate-500"
                                            >
                                                Pilih Kurir
                                            </p>
                                            {#if totalWeightGrams > 0}
                                                <span
                                                    class="text-[10px] font-semibold text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full"
                                                >
                                                    Berat: {(
                                                        totalWeightGrams / 1000
                                                    ).toFixed(2)} kg ({totalWeightGrams.toLocaleString()}g)
                                                </span>
                                            {/if}
                                        </div>

                                        <!-- Dropdown Trigger -->
                                        <button
                                            onclick={() =>
                                                (showCourierDropdown =
                                                    !showCourierDropdown)}
                                            class="w-full flex items-center justify-between p-3.5 rounded-2xl border transition-all duration-200 text-left bg-slate-50/50 hover:bg-slate-50 hover:border-slate-200 active:scale-[0.99] {showCourierDropdown
                                                ? 'border-slate-300 bg-white ring-2 ring-slate-100'
                                                : 'border-slate-100'}"
                                        >
                                            <div
                                                class="flex items-center gap-2.5"
                                            >
                                                <i
                                                    class="ti ti-truck text-base text-slate-500"
                                                ></i>
                                                {#if selectedCourier}
                                                    <span
                                                        class="text-xs font-bold text-slate-800"
                                                    >
                                                        {courierLabels[
                                                            selectedCourier
                                                        ] ??
                                                            selectedCourier.toUpperCase()}
                                                    </span>
                                                {:else}
                                                    <span
                                                        class="text-xs font-medium text-slate-400"
                                                    >
                                                        Pilih kurir
                                                        pengiriman...
                                                    </span>
                                                {/if}
                                            </div>
                                            <i
                                                class="ti ti-chevron-down text-xs text-slate-400 transition-transform duration-200 {showCourierDropdown
                                                    ? 'rotate-180'
                                                    : ''}"
                                            ></i>
                                        </button>

                                        <!-- Dropdown Panel -->
                                        {#if showCourierDropdown}
                                            <div
                                                class="absolute z-50 left-0 right-0 mt-1.5 bg-white rounded-2xl border border-slate-100 shadow-xl overflow-hidden"
                                                transition:fade={{
                                                    duration: 100,
                                                }}
                                            >
                                                <!-- Search input -->
                                                <div
                                                    class="p-2 border-b border-slate-50 sticky top-0 bg-white z-10"
                                                >
                                                    <div class="relative">
                                                        <i
                                                            class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"
                                                        ></i>
                                                        <input
                                                            type="text"
                                                            bind:value={
                                                                searchCourierQuery
                                                            }
                                                            placeholder="Cari kurir..."
                                                            class="w-full pl-9 pr-8 py-2 bg-slate-50 border border-slate-100 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-slate-100 transition-all font-medium text-slate-700"
                                                        />
                                                        {#if searchCourierQuery}
                                                            <button
                                                                aria-label="Tutup pencarian"
                                                                onclick={() =>
                                                                    (searchCourierQuery =
                                                                        '')}
                                                                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 p-0.5"
                                                            >
                                                                <i
                                                                    class="ti ti-x text-xs"
                                                                ></i>
                                                            </button>
                                                        {/if}
                                                    </div>
                                                </div>

                                                <!-- Options List -->
                                                <div
                                                    class="max-h-60 overflow-y-auto p-1.5 space-y-0.5 scrollbar-thin"
                                                >
                                                    {#each visibleCouriers.filter( (c) => (courierLabels[c] ?? c)
                                                                .toLowerCase()
                                                                .includes(searchCourierQuery.toLowerCase()), ) as courier}
                                                        <button
                                                            onclick={() => {
                                                                selectedCourier =
                                                                    courier;
                                                                showCourierDropdown = false;
                                                                searchCourierQuery =
                                                                    '';
                                                                fetchShipping();
                                                            }}
                                                            class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-left text-xs font-semibold transition active:scale-[0.99] {selectedCourier ===
                                                            courier
                                                                ? 'text-white font-bold shadow-sm'
                                                                : 'text-slate-700 hover:bg-slate-50'}"
                                                            style={selectedCourier ===
                                                            courier
                                                                ? `background-color:${primary};`
                                                                : ''}
                                                        >
                                                            <span
                                                                >{courierLabels[
                                                                    courier
                                                                ] ??
                                                                    courier.toUpperCase()}</span
                                                            >
                                                            {#if selectedCourier === courier}
                                                                <i
                                                                    class="ti ti-check text-xs font-bold text-white"
                                                                ></i>
                                                            {/if}
                                                        </button>
                                                    {:else}
                                                        <div
                                                            class="py-6 text-center text-xs text-slate-400 flex flex-col items-center justify-center gap-1.5"
                                                        >
                                                            <i
                                                                class="ti ti-search-off text-lg text-slate-300"
                                                            ></i>
                                                            <span
                                                                >{searchCourierQuery
                                                                    ? `Kurir "${searchCourierQuery}" tidak ditemukan`
                                                                    : 'Semua kurir tidak tersedia untuk alamat ini'}</span
                                                            >
                                                        </div>
                                                    {/each}
                                                </div>
                                            </div>
                                        {/if}
                                    </div>

                                    {#if loadingShipping}
                                        <div class="space-y-2 mt-3">
                                            {#each [1, 2, 3] as _}
                                                <div
                                                    class="h-16 bg-slate-50 border border-slate-100 rounded-2xl animate-pulse flex items-center justify-between px-4"
                                                >
                                                    <div
                                                        class="space-y-2 w-2/3"
                                                    >
                                                        <div
                                                            class="h-4 bg-slate-200/80 rounded-md w-1/2"
                                                        ></div>
                                                        <div
                                                            class="h-3 bg-slate-200/80 rounded-md w-3/4"
                                                        ></div>
                                                    </div>
                                                    <div
                                                        class="h-5 bg-slate-200/80 rounded-md w-16"
                                                    ></div>
                                                </div>
                                            {/each}
                                        </div>
                                    {:else if shippingError}
                                        <div
                                            class="mt-3 p-3 bg-red-50 border border-red-100 text-red-600 rounded-2xl text-xs font-medium flex items-center gap-2"
                                        >
                                            <i
                                                class="ti ti-alert-circle text-base"
                                            ></i>
                                            <span>{shippingError}</span>
                                        </div>
                                    {:else if shippingOptions.length > 0}
                                        <div
                                            class="space-y-2 mt-3 max-h-[350px] overflow-y-auto scrollbar-thin pr-1"
                                        >
                                            {#each shippingOptions as opt}
                                                <button
                                                    onclick={() =>
                                                        (selectedShipping =
                                                            opt)}
                                                    class="w-full flex items-center justify-between gap-4 p-3.5 rounded-2xl border transition-all duration-200 text-left active:scale-[0.99] {selectedShipping?.service ===
                                                    opt.service
                                                        ? 'shadow-sm border-transparent'
                                                        : 'border-slate-100 hover:border-slate-200 bg-slate-50/30'}"
                                                    style={selectedShipping?.service ===
                                                    opt.service
                                                        ? `border-color:${primary}; background-color:${primary}08`
                                                        : ''}
                                                >
                                                    <div
                                                        class="flex items-center gap-3 flex-grow min-w-0"
                                                    >
                                                        <div
                                                            class="w-5 h-5 rounded-full border flex items-center justify-center transition-all duration-200 shrink-0"
                                                            style={selectedShipping?.service ===
                                                            opt.service
                                                                ? `border-color:${primary}; background-color:${primary}`
                                                                : 'border-slate-300 bg-white'}
                                                        >
                                                            {#if selectedShipping?.service === opt.service}
                                                                <i
                                                                    class="ti ti-check text-[10px] text-white"
                                                                ></i>
                                                            {/if}
                                                        </div>
                                                        <div
                                                            class="min-w-0 flex-grow"
                                                        >
                                                            <div
                                                                class="flex items-center gap-1.5 flex-wrap"
                                                            >
                                                                <span
                                                                    class="uppercase px-1.5 py-0.5 rounded bg-slate-100 text-[9px] font-bold text-slate-500 tracking-wider shrink-0"
                                                                >
                                                                    {courierLabels[
                                                                        selectedCourier
                                                                    ] ||
                                                                        selectedCourier}
                                                                </span>
                                                                <span
                                                                    class="text-sm font-bold text-slate-800"
                                                                >
                                                                    {opt.service}
                                                                </span>
                                                            </div>
                                                            <p
                                                                class="text-xs text-slate-500 mt-1 font-medium leading-relaxed"
                                                            >
                                                                {opt.description}
                                                            </p>
                                                            <p
                                                                class="text-[10px] text-slate-400 mt-1 flex items-center gap-1 font-medium"
                                                            >
                                                                <i
                                                                    class="ti ti-clock text-xs"
                                                                ></i>
                                                                <span
                                                                    >{getEtdLabel(
                                                                        opt
                                                                            .cost?.[0]
                                                                            ?.etd,
                                                                        opt.service ||
                                                                            opt.description,
                                                                    )}</span
                                                                >
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div
                                                        class="text-right shrink-0"
                                                    >
                                                        <p
                                                            class="text-sm font-bold"
                                                            style="color:{primary}"
                                                        >
                                                            {fmt(
                                                                opt.cost?.[0]
                                                                    ?.value ??
                                                                    0,
                                                            )}
                                                        </p>
                                                    </div>
                                                </button>
                                            {/each}
                                        </div>
                                    {:else if selectedCourier}
                                        <div
                                            class="flex flex-col items-center justify-center py-6 px-4 bg-slate-50/50 rounded-2xl border border-dashed border-slate-200 text-center mt-3"
                                        >
                                            <div
                                                class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center mb-2 animate-bounce"
                                            >
                                                <i
                                                    class="ti ti-loader text-lg text-slate-500"
                                                ></i>
                                            </div>
                                            <p
                                                class="text-xs font-medium text-slate-500"
                                            >
                                                Memilih kurir untuk melihat
                                                layanan...
                                            </p>
                                        </div>
                                    {:else}
                                        <div
                                            class="flex flex-col items-center justify-center py-6 px-4 bg-slate-50/50 rounded-2xl border border-dashed border-slate-200 text-center mt-3"
                                        >
                                            <div
                                                class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center mb-2"
                                            >
                                                <i
                                                    class="ti ti-truck-delivery text-lg text-slate-400"
                                                ></i>
                                            </div>
                                            <p
                                                class="text-xs font-medium text-slate-500"
                                            >
                                                Pilih kurir di atas untuk
                                                melihat pilihan layanan &
                                                ongkir.
                                            </p>
                                        </div>
                                    {/if}
                                {/if}
                            </div>
                        </div>
                    {/if}

                    <!-- Voucher Section -->
                    <div
                        class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden p-5 space-y-3"
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

                        <!-- Inner Coupon Button (Image 1 Style for modal triggers) -->
                        <button
                            onclick={() => (voucherModalOpen = true)}
                            style="color: {primary}; border-color: {primary}30; background-color: {primary}08;"
                            class="w-full flex items-center justify-between px-3 py-2.5 border rounded-xl text-left text-xs font-bold transition group cursor-pointer"
                        >
                            <span class="flex items-center gap-2">
                                <i class="ti ti-gift text-sm"></i>
                                {visibleVouchers.length > 0
                                    ? 'Pilih promo yang tersedia'
                                    : 'Tidak ada promo tersedia'}
                            </span>
                            <i
                                class="ti ti-chevron-right text-xs transition group-hover:translate-x-0.5"
                            ></i>
                        </button>

                        {#if activeAppliedVouchers.length > 0}
                            <div
                                class="space-y-1.5 pt-1.5 border-t border-slate-50"
                            >
                                <p
                                    class="text-[9px] font-black text-slate-400 uppercase tracking-widest px-0.5"
                                >
                                    Promo yang digunakan:
                                </p>
                                <div class="flex flex-col gap-1.5">
                                    {#each activeAppliedVouchers as voucher}
                                        {@const isShipping =
                                            voucher.type ===
                                            'voucher_gratis_ongkir'}
                                        <div
                                            class="flex items-center justify-between px-2.5 py-1.5 rounded-xl border text-[10px] font-bold uppercase tracking-wider transition-all"
                                            style="background: {withOpacity(
                                                isShipping ? primary : primary,
                                                0.06,
                                            )}; border-color: {withOpacity(
                                                isShipping ? primary : primary,
                                                0.25,
                                            )};"
                                        >
                                            <div
                                                class="flex items-center gap-1.5 min-w-0"
                                            >
                                                <i
                                                    class="ti {isShipping
                                                        ? 'ti-truck'
                                                        : 'ti-ticket'} text-xs shrink-0"
                                                    style="color: {isShipping
                                                        ? primary
                                                        : primary};"
                                                ></i>
                                                <span
                                                    class="shrink-0 font-black"
                                                    style="color: {isShipping
                                                        ? primary
                                                        : primary};"
                                                >
                                                    {voucher.code}
                                                </span>
                                                <span
                                                    class="text-[8.5px] text-slate-600 normal-case font-bold truncate"
                                                >
                                                    • {voucher.name} (
                                                    {#if isShipping}
                                                        S/d {fmt(
                                                            voucher.discount_value,
                                                        )}
                                                        {#if shippingDiscount > 0}
                                                            - Hemat {fmt(
                                                                shippingDiscount,
                                                            )}
                                                        {/if}
                                                    {:else}
                                                        {voucher.discount_type ===
                                                        'percentage'
                                                            ? Number(
                                                                  voucher.discount_value,
                                                              ) + '%'
                                                            : fmt(
                                                                  voucher.discount_value,
                                                              )}
                                                        {#if discountAmount > 0}
                                                            - Hemat {fmt(
                                                                discountAmount,
                                                            )}
                                                        {/if}
                                                    {/if}
                                                    )
                                                </span>
                                            </div>
                                            <button
                                                onclick={() =>
                                                    deselectVoucher(voucher)}
                                                class="text-slate-400 hover:text-slate-655 transition p-0.5 border-0 bg-transparent cursor-pointer flex items-center justify-center shrink-0 rounded-full hover:bg-slate-200/40 w-4 h-4 ml-1"
                                                title="Lepas Voucher"
                                            >
                                                <i class="ti ti-x text-xs"></i>
                                            </button>
                                        </div>
                                    {/each}
                                </div>
                            </div>
                        {/if}

                        {#if voucherError}
                            <p
                                class="text-[10.5px] text-red-500 font-bold mt-1"
                            >
                                {voucherError}
                            </p>
                        {/if}
                    </div>

                    <!-- Poin Saya Loyalty Section -->
                    {#if coinsEnabled}
                        <div
                            class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden p-5 space-y-4"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div
                                        class="w-8 h-8 rounded-xl bg-amber-50 flex items-center justify-center text-amber-500 shrink-0"
                                    >
                                        <i class="ti ti-coins text-xl"></i>
                                    </div>
                                    <div>
                                        <h3
                                            class="font-outfit font-black text-xs uppercase tracking-wider text-slate-400"
                                        >
                                            Poin Toko
                                        </h3>
                                        <p
                                            class="text-[11px] font-bold text-slate-700 mt-0.5"
                                        >
                                            {formatNumber(userCoinsBalance)} Poin
                                            <span
                                                class="text-slate-400 font-normal"
                                                >(Senilai Rp {formatNumber(
                                                    userCoinsValue,
                                                )})</span
                                            >
                                        </p>
                                    </div>
                                </div>

                                {#if isCoinsMinPurchaseMet && userCoinsBalance > 0 && maxCoinsRedeemAllowed > 0}
                                    <!-- Premium Custom Toggle -->
                                    <label
                                        class="relative inline-flex items-center cursor-pointer select-none"
                                    >
                                        <input
                                            type="checkbox"
                                            bind:checked={useCoins}
                                            class="sr-only peer"
                                        />
                                        <div
                                            class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"
                                        ></div>
                                    </label>
                                {/if}
                            </div>

                            {#if !isCoinsMinPurchaseMet}
                                <!-- Warning alert: min purchase not met -->
                                <div
                                    class="bg-amber-50/70 border border-amber-100 rounded-2xl p-3 flex gap-2.5 items-start"
                                >
                                    <i
                                        class="ti ti-info-circle text-amber-500 text-sm mt-0.5 shrink-0"
                                    ></i>
                                    <div class="min-w-0">
                                        <p
                                            class="text-[10px] font-bold text-amber-800 leading-normal"
                                        >
                                            Poin Belum Dapat Digunakan
                                        </p>
                                        <p
                                            class="text-[9px] text-amber-600 mt-0.5 leading-normal"
                                        >
                                            Minimal belanja untuk menggunakan
                                            Poin adalah <span class="font-black"
                                                >Rp {formatNumber(
                                                    coinMinPurchaseRedeem,
                                                )}</span
                                            >
                                            (Subtotal Anda: Rp {formatNumber(
                                                subtotal,
                                            )}).
                                        </p>
                                    </div>
                                </div>
                            {:else if userCoinsBalance <= 0}
                                <div
                                    class="bg-slate-50 border border-slate-100 rounded-2xl p-3 flex gap-2.5 items-start"
                                >
                                    <i
                                        class="ti ti-info-circle text-slate-400 text-sm mt-0.5 shrink-0"
                                    ></i>
                                    <div class="min-w-0">
                                        <p
                                            class="text-[10px] font-bold text-slate-500 leading-normal"
                                        >
                                            Saldo Poin Kosong
                                        </p>
                                        <p
                                            class="text-[9px] text-slate-400 mt-0.5 leading-normal"
                                        >
                                            Belanja sekarang untuk mengumpulkan
                                            Poin!
                                        </p>
                                    </div>
                                </div>
                            {:else if maxCoinsRedeemAllowed <= 0}
                                <div
                                    class="bg-slate-50 border border-slate-100 rounded-2xl p-3 flex gap-2.5 items-start"
                                >
                                    <i
                                        class="ti ti-info-circle text-slate-400 text-sm mt-0.5 shrink-0"
                                    ></i>
                                    <div class="min-w-0">
                                        <p
                                            class="text-[10px] font-bold text-slate-500 leading-normal"
                                        >
                                            Poin Tidak Bisa Digunakan
                                        </p>
                                        <p
                                            class="text-[9px] text-slate-400 mt-0.5 leading-normal"
                                        >
                                            Batas maksimum diskon voucher /
                                            promo telah melampaui aturan
                                            penggunaan Poin.
                                        </p>
                                    </div>
                                </div>
                            {:else}
                                <!-- Main Info Block -->
                                <div
                                    class="bg-slate-50 rounded-2xl p-3.5 space-y-2 border border-slate-100/50"
                                >
                                    <div
                                        class="flex items-center justify-between text-[10px] font-bold text-slate-600"
                                    >
                                        <span>Gunakan Poin:</span>
                                        <span
                                            class={useCoins
                                                ? 'text-emerald-600 font-black'
                                                : 'text-slate-500 font-black'}
                                        >
                                            {useCoins
                                                ? `${formatNumber(maxCoinsRedeemAllowed)} Poin`
                                                : 'Tidak digunakan'}
                                        </span>
                                    </div>
                                    <div
                                        class="flex items-center justify-between text-[10px] font-bold text-slate-600"
                                    >
                                        <span>Potongan Belanja:</span>
                                        <span
                                            class={useCoins
                                                ? 'text-emerald-600 font-black'
                                                : 'text-slate-500 font-black'}
                                        >
                                            {useCoins
                                                ? `-Rp ${formatNumber(coinDiscountAmount)}`
                                                : 'Rp 0'}
                                        </span>
                                    </div>

                                    <!-- Rules details small print -->
                                    <div
                                        class="pt-2 border-t border-slate-100 text-[8.5px] text-slate-400 leading-normal space-y-0.5 font-sans"
                                    >
                                        <p>
                                            • Nilai Tukar: 1 Poin = Rp {formatNumber(
                                                coinConversionRate,
                                            )}
                                        </p>
                                        {#if coinMaxRedeemPercentage < 100}
                                            <p>
                                                • Maksimal diskon Poin: {coinMaxRedeemPercentage}%
                                                dari subtotal belanja (Rp {formatNumber(
                                                    (subtotal *
                                                        coinMaxRedeemPercentage) /
                                                        100,
                                                )})
                                            </p>
                                        {/if}
                                        {#if coinMaxRedeemPerTxn < 5000000}
                                            <p>
                                                • Batas maksimal penggunaan Poin
                                                per transaksi: Rp {formatNumber(
                                                    coinMaxRedeemPerTxn,
                                                )}
                                            </p>
                                        {/if}
                                    </div>
                                </div>
                            {/if}

                            {#if prospectiveCoinsEarned > 0}
                                <!-- Earn info badge -->
                                <div
                                    class="bg-emerald-50/60 border border-emerald-100/50 rounded-2xl p-3 flex gap-2.5 items-center"
                                >
                                    <div
                                        class="w-6 h-6 rounded-lg bg-emerald-500 flex items-center justify-center text-white shrink-0"
                                    >
                                        <i class="ti ti-gift text-xs"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <p
                                            class="text-[9px] text-emerald-800 leading-tight"
                                        >
                                            {#if appliedVoucher && appliedVoucher.is_points_voucher}
                                                Dapatkan <span
                                                    class="font-black text-emerald-600"
                                                    >{formatNumber(
                                                        prospectiveCoinsEarned,
                                                    )} Poin</span
                                                > dari Voucher Belanja setelah pesanan Anda selesai!
                                            {:else}
                                                Dapatkan <span
                                                    class="font-black text-emerald-600"
                                                    >{formatNumber(
                                                        prospectiveCoinsEarned,
                                                    )} Poin</span
                                                > setelah pesanan Anda selesai!
                                            {/if}
                                        </p>
                                    </div>
                                </div>
                            {:else if useCoins && coinDiscountAmount > 0}
                                <!-- Info badge: no coins earned when using coins -->
                                <div
                                    class="bg-amber-50/60 border border-amber-100/50 rounded-2xl p-3 flex gap-2.5 items-center"
                                >
                                    <div
                                        class="w-6 h-6 rounded-lg bg-amber-500 flex items-center justify-center text-white shrink-0"
                                    >
                                        <i class="ti ti-info-circle text-xs"
                                        ></i>
                                    </div>
                                    <div class="min-w-0">
                                        <p
                                            class="text-[9px] text-amber-800 leading-tight"
                                        >
                                            Anda menggunakan Poin pada transaksi
                                            ini, sehingga <span
                                                class="font-black text-amber-600"
                                                >tidak akan mendapatkan Poin
                                                tambahan</span
                                            >.
                                        </p>
                                    </div>
                                </div>
                            {/if}
                        </div>
                    {/if}

                    <!-- Notes -->
                    <div
                        class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden"
                    >
                        <div class="px-4 pt-4 pb-4">
                            <div class="flex items-center gap-2 mb-3">
                                <i
                                    class="ti ti-notes text-lg"
                                    style="color:{primary}"
                                ></i>
                                <span class="font-bold text-slate-800 text-sm"
                                    >Catatan Pesanan</span
                                >
                            </div>
                            <textarea
                                bind:value={orderNotes}
                                rows="2"
                                placeholder="Tambahkan catatan untuk penjual (opsional)"
                                class="w-full px-3 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:border-transparent resize-none"
                                style="--tw-ring-color:{primary}30"
                            ></textarea>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Payment Method + Summary (desktop only) -->
                <div class="space-y-4">
                    <!-- Payment Method -->
                    <div
                        class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden"
                    >
                        <div class="px-4 pt-4 pb-2">
                            <div class="flex items-center gap-2 mb-3">
                                <i
                                    class="ti ti-credit-card text-lg"
                                    style="color:{primary}"
                                ></i>
                                <span class="font-bold text-slate-800 text-sm"
                                    >Metode Pembayaran</span
                                >
                            </div>
                            <div class="space-y-2">
                                {#each paymentMethods as pm}
                                    <button
                                        onclick={() =>
                                            (selectedPaymentMethodId = pm.id)}
                                        class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl border-2 text-left transition {selectedPaymentMethodId ===
                                        pm.id
                                            ? 'border-transparent'
                                            : 'border-slate-200 hover:border-slate-300'}"
                                        style={selectedPaymentMethodId === pm.id
                                            ? `border-color:${primary}; background:${primary}10`
                                            : ''}
                                    >
                                        <div
                                            class="w-8 h-8 rounded-lg flex items-center justify-center text-white shrink-0"
                                            style="background:{primary}"
                                        >
                                            <i
                                                class="ti {pm.type === 'manual'
                                                    ? 'ti-building-bank'
                                                    : 'ti-credit-card'} text-sm"
                                            ></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p
                                                class="text-sm font-bold text-slate-800 truncate"
                                            >
                                                {pm.name}
                                            </p>
                                            {#if pm.type === 'manual' && pm.bank_name}
                                                <p
                                                    class="text-xs text-slate-500"
                                                >
                                                    {pm.bank_name} · {pm.account_number}
                                                </p>
                                            {:else if pm.type === 'gateway'}
                                                <p
                                                    class="text-xs text-slate-500"
                                                >
                                                    Pembayaran Otomatis
                                                    Terverifikasi
                                                </p>
                                            {/if}
                                        </div>
                                        {#if selectedPaymentMethodId === pm.id}
                                            <i
                                                class="ti ti-circle-check text-lg shrink-0"
                                                style="color:{primary}"
                                            ></i>
                                        {:else}
                                            <div
                                                class="w-5 h-5 rounded-full border-2 border-slate-300 shrink-0"
                                            ></div>
                                        {/if}
                                    </button>
                                {/each}
                            </div>

                            <!-- Manual payment info -->
                            {#if selectedPaymentMethod?.type === 'manual'}
                                <div
                                    class="mt-3 bg-blue-50 border border-blue-200 rounded-xl p-3"
                                >
                                    <p
                                        class="text-xs font-bold text-blue-800 mb-1"
                                    >
                                        Info Transfer
                                    </p>
                                    <p class="text-xs text-blue-700">
                                        Bank: {selectedPaymentMethod.bank_name}
                                    </p>
                                    <p class="text-xs text-blue-700">
                                        No. Rekening: <span class="font-bold"
                                            >{selectedPaymentMethod.account_number}</span
                                        >
                                    </p>
                                    <p class="text-xs text-blue-700">
                                        Atas Nama: {selectedPaymentMethod.account_name}
                                    </p>
                                    <p class="text-xs text-blue-600 mt-1">
                                        Upload bukti bayar setelah pesanan
                                        dibuat.
                                    </p>
                                </div>
                            {/if}
                        </div>
                        <div class="pb-4"></div>
                    </div>

                    <!-- Order Summary (desktop sticky) -->
                    <div
                        class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden lg:sticky lg:top-20"
                    >
                        <div class="px-4 pt-4 pb-4">
                            <div class="flex items-center gap-2 mb-3">
                                <i
                                    class="ti ti-receipt text-lg"
                                    style="color:{primary}"
                                ></i>
                                <span class="font-bold text-slate-800 text-sm"
                                    >Ringkasan Pesanan</span
                                >
                            </div>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-slate-600"
                                        >Subtotal Produk</span
                                    >
                                    <span class="font-semibold text-slate-800"
                                        >{fmt(subtotal)}</span
                                    >
                                </div>
                                {#if discountAmount > 0}
                                    <div
                                        class="flex justify-between text-green-600"
                                    >
                                        <span>Diskon Voucher</span>
                                        <span class="font-semibold"
                                            >-{fmt(discountAmount)}</span
                                        >
                                    </div>
                                {/if}
                                {#if coinDiscountAmount > 0}
                                    <div
                                        class="flex justify-between text-emerald-600"
                                    >
                                        <span>Potongan Poin Saya</span>
                                        <span class="font-semibold"
                                            >-{fmt(coinDiscountAmount)}</span
                                        >
                                    </div>
                                {/if}
                                {#if !isDigitalOnly}
                                    <div class="flex justify-between">
                                        <span class="text-slate-600"
                                            >Ongkos Kirim</span
                                        >
                                        <span
                                            class="font-semibold text-slate-800"
                                            >{selectedShipping
                                                ? fmt(shippingFee)
                                                : '-'}</span
                                        >
                                    </div>
                                    {#if shippingDiscount > 0}
                                        <div
                                            class="flex justify-between text-green-600"
                                        >
                                            <span>Gratis Ongkir</span>
                                            <span class="font-semibold"
                                                >-{fmt(shippingDiscount)}</span
                                            >
                                        </div>
                                    {/if}
                                {/if}
                                {#if adminFee > 0}
                                    <div class="flex justify-between">
                                        <span class="text-slate-600"
                                            >Biaya Admin</span
                                        >
                                        <span
                                            class="font-semibold text-slate-800"
                                            >{fmt(adminFee)}</span
                                        >
                                    </div>
                                {/if}
                                {#if applicationFee > 0}
                                    <div class="flex justify-between">
                                        <span class="text-slate-600"
                                            >Biaya Aplikasi</span
                                        >
                                        <span
                                            class="font-semibold text-slate-800"
                                            >{fmt(applicationFee)}</span
                                        >
                                    </div>
                                {/if}
                                <div
                                    class="border-t border-slate-100 pt-2 flex justify-between"
                                >
                                    <span class="font-bold text-slate-800"
                                        >Total</span
                                    >
                                    <span
                                        class="font-black text-lg"
                                        style="color:{primary}"
                                        >{fmt(grandTotal)}</span
                                    >
                                </div>
                            </div>

                            <!-- Checkout locked (via .env CHECKOUT_LOCKED) -->
                            {#if isCheckoutLocked}
                                <div
                                    class="bg-red-50/80 border border-red-200/60 rounded-xl p-3 flex gap-3 items-start mt-4 mb-2 text-left"
                                >
                                    <div
                                        class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center shrink-0 mt-0.5"
                                    >
                                        <i
                                            class="ti ti-lock text-red-600 text-lg"
                                        ></i>
                                    </div>
                                    <div class="min-w-0">
                                        <h4
                                            class="font-bold text-red-800 text-[11px] uppercase tracking-tight"
                                        >
                                            Checkout Tidak Tersedia
                                        </h4>
                                        <p
                                            class="text-[9px] text-red-700 font-semibold mt-1 leading-snug"
                                        >
                                            {checkoutLockedMessage}
                                        </p>
                                    </div>
                                </div>
                            {:else if !isStoreOpen}
                                <div
                                    class="bg-amber-50/80 border border-amber-200/60 rounded-xl p-3 flex gap-3 items-start mt-4 mb-2 text-left"
                                >
                                    <div
                                        class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center shrink-0 mt-0.5"
                                    >
                                        <i
                                            class="ti ti-clock-pause text-amber-600 text-lg"
                                        ></i>
                                    </div>
                                    <div class="min-w-0">
                                        <h4
                                            class="font-bold text-amber-800 text-[11px] uppercase tracking-tight"
                                        >
                                            Toko Sedang Tutup
                                        </h4>
                                        <p
                                            class="text-[9px] text-amber-700 font-semibold mt-1 leading-snug"
                                        >
                                            Mohon maaf, proses pesanan
                                            dinonaktifkan sementara.
                                        </p>
                                    </div>
                                </div>
                            {/if}
                            <button
                                onclick={submitCheckout}
                                disabled={isSubmitting ||
                                    isCheckoutLocked ||
                                    (!isDigitalOnly &&
                                        selectedCourier !== 'self_pickup' &&
                                        !selectedAddressId) ||
                                    !selectedPaymentMethodId ||
                                    (!isDigitalOnly && !selectedShipping) ||
                                    !isStoreOpen}
                                class="hidden lg:flex w-full mt-4 items-center justify-center gap-2 px-6 py-3 rounded-xl font-bold text-white transition disabled:opacity-50 disabled:cursor-not-allowed"
                                style="background:linear-gradient(to right, {primary}, {primary})"
                            >
                                {#if isSubmitting}
                                    <i class="ti ti-loader-2 animate-spin"></i>
                                    Memproses...
                                {:else}
                                    <i class="ti ti-shopping-cart-check"></i>
                                    Buat Pesanan
                                {/if}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile Fixed Bottom Bar -->
        <div
            class="fixed bottom-0 left-0 right-0 z-40 lg:hidden bg-white border-t border-slate-200 px-4 py-3 safe-area-bottom"
        >
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-xs text-slate-500">Total Pembayaran</p>
                    <p class="text-lg font-black" style="color:{primary}">
                        {fmt(grandTotal)}
                    </p>
                </div>
                <button
                    onclick={submitCheckout}
                    disabled={isSubmitting ||
                        isCheckoutLocked ||
                        (!isDigitalOnly &&
                            selectedCourier !== 'self_pickup' &&
                            !selectedAddressId) ||
                        !selectedPaymentMethodId ||
                        (!isDigitalOnly && !selectedShipping) ||
                        !isStoreOpen}
                    class="flex items-center gap-2 px-6 py-3 rounded-xl font-bold text-white transition disabled:opacity-50 disabled:cursor-not-allowed"
                    style="background:linear-gradient(to right, {primary}, {primary})"
                >
                    {#if isSubmitting}
                        <i class="ti ti-loader-2 animate-spin"></i>
                        Memproses...
                    {:else}
                        <i class="ti ti-shopping-cart-check"></i>
                        Buat Pesanan
                    {/if}
                </button>
            </div>
        </div>
    </div>

    <!-- Address Modal -->
    {#if showAddressModal}
        <!-- svelte-ignore a11y_no_static_element_interactions -->
        <!-- svelte-ignore a11y_click_events_have_key_events -->
        <div
            class="fixed inset-0 z-50 flex items-end lg:items-center justify-center"
            onclick={() => (showAddressModal = false)}
        >
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
            <!-- svelte-ignore a11y_no_static_element_interactions -->
            <!-- svelte-ignore a11y_click_events_have_key_events -->
            <div
                class="relative z-10 bg-white w-full lg:max-w-lg rounded-t-3xl lg:rounded-2xl max-h-[80vh] overflow-y-auto"
                onclick={(e: any) => e.stopPropagation()}
            >
                <div
                    class="sticky top-0 bg-white px-4 pt-4 pb-3 border-b border-slate-100 flex items-center justify-between"
                >
                    <h3 class="font-bold text-slate-800">Pilih Alamat</h3>
                    <button
                        aria-label="Tutup"
                        onclick={() => (showAddressModal = false)}
                        class="p-1 rounded-full hover:bg-slate-100"
                    >
                        <i class="ti ti-x text-slate-600"></i>
                    </button>
                </div>
                <div class="p-4 space-y-3">
                    {#each addresses as addr}
                        <button
                            onclick={() => {
                                selectedAddressId = addr.id;
                                showAddressModal = false;
                                selectedShipping = null;
                                shippingOptions = [];
                            }}
                            class="w-full text-left p-3 rounded-xl border-2 transition {selectedAddressId ===
                            addr.id
                                ? 'border-transparent'
                                : 'border-slate-200 hover:border-slate-300'}"
                            style={selectedAddressId === addr.id
                                ? `border-color:${primary}; background:${primary}10`
                                : ''}
                        >
                            <div class="flex items-center gap-2 mb-1 flex-wrap">
                                <span class="font-bold text-sm text-slate-800"
                                    >{addr.receiver_name}</span
                                >
                                <span class="text-xs text-slate-500"
                                    >{addr.phone_number}</span
                                >
                                {#if addr.is_primary}
                                    <span
                                        class="text-[10px] font-bold px-1.5 py-0.5 rounded-full text-white"
                                        style="background:{primary}">Utama</span
                                    >
                                {/if}
                                <span
                                    class="text-[10px] text-slate-400 border border-slate-200 px-1.5 py-0.5 rounded-full"
                                    >{addr.label}</span
                                >
                            </div>
                            <p class="text-xs text-slate-600 leading-relaxed">
                                {addr.full_address}
                            </p>
                            {#if addr.regency_name}
                                <p class="text-xs text-slate-500">
                                    {addr.district_name}, {addr.regency_name}, {addr.province_name}
                                    {addr.postal_code}
                                </p>
                            {/if}
                        </button>
                    {/each}
                    <Link
                        href="/profile/addresses?from=checkout"
                        class="flex items-center gap-2 text-sm font-semibold px-3 py-2.5 rounded-xl border-2 border-dashed border-slate-200 hover:border-slate-300 text-slate-500 hover:text-slate-700 transition"
                    >
                        <i class="ti ti-plus text-lg"></i>
                        Tambah Alamat Baru
                    </Link>
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
            <!-- svelte-ignore a11y_no_static_element_interactions -->
            <!-- svelte-ignore a11y_click_events_have_key_events -->
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
                            class="w-7 h-7 rounded-lg text-white flex items-center justify-center text-xs shadow-sm font-black"
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
                        aria-label="Tutup"
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
                                bind:value={manualPromoCode}
                                placeholder="Masukkan kode voucher..."
                                class="w-full pl-8 pr-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--primary-glow)] focus:border-[var(--primary)] text-[10.5px] font-semibold text-slate-700 h-8 uppercase"
                                style="--primary-glow: {primary}30; --primary: {primary};"
                                onkeydown={(e) => {
                                    if (e.key === 'Enter') {
                                        applyVoucher();
                                    }
                                }}
                            />
                        </div>
                        <button
                            onclick={applyVoucher}
                            disabled={voucherLoading || !manualPromoCode.trim()}
                            style="background-color: {primary};"
                            class="px-3.5 py-1.5 hover:opacity-90 text-white text-[10.5px] font-black rounded-lg uppercase tracking-wider transition duration-200 h-8 cursor-pointer border-0 shrink-0 disabled:opacity-50"
                        >
                            {voucherLoading ? '...' : 'Pakai'}
                        </button>
                    </div>

                    {#if visibleVouchers.length === 0}
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
                                            subtotal >=
                                            Number(voucher.min_purchase ?? 0)}
                                        {@const isSelected =
                                            appliedVoucher &&
                                            ((Array.isArray(
                                                appliedVoucher.promotions,
                                            ) &&
                                                appliedVoucher.promotions.some(
                                                    (p) => p.id === voucher.id,
                                                )) ||
                                                (appliedVoucher.promotion &&
                                                    appliedVoucher.promotion
                                                        .id === voucher.id) ||
                                                voucherInputCode
                                                    .split(',')
                                                    .map((c) =>
                                                        c.trim().toUpperCase(),
                                                    )
                                                    .includes(
                                                        voucher.code.toUpperCase(),
                                                    ))}

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
                                                        0.85,
                                                    )});"
                                                >
                                                    <i
                                                        class="ti ti-truck text-base mb-0.5 shrink-0"
                                                    ></i>
                                                    <span
                                                        class="text-[7.5px] font-black uppercase tracking-wider leading-none"
                                                    >
                                                        Free Ongkir
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
                                                                    class="text-white text-[7.5px] font-black px-1 py-0.5 rounded leading-none shrink-0"
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
                                                                voucher.max_discount ??
                                                                    0,
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
                                                                class="text-[8px] bg-slate-100/80 text-slate-500 font-extrabold px-1.5 py-0.5 rounded leading-none"
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
                                                                onclick={() => {
                                                                    if (
                                                                        isSelected
                                                                    ) {
                                                                        deselectVoucher(
                                                                            voucher,
                                                                        );
                                                                    } else {
                                                                        selectVoucher(
                                                                            voucher,
                                                                        );
                                                                    }
                                                                }}
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
                                                                        subtotal,
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
                                    class="text-[9px] font-black text-slate-450 uppercase tracking-widest flex items-center gap-1 px-1"
                                >
                                    <i
                                        class="ti ti-ticket text-xs"
                                        style="color: {primary};"
                                    ></i> Voucher Belanja
                                </h4>
                                <div class="space-y-1.5">
                                    {#each discountVouchers as voucher (voucher.id)}
                                        {@const minMet =
                                            subtotal >=
                                            Number(voucher.min_purchase ?? 0)}
                                        {@const isSelected =
                                            appliedVoucher &&
                                            ((Array.isArray(
                                                appliedVoucher.promotions,
                                            ) &&
                                                appliedVoucher.promotions.some(
                                                    (p) => p.id === voucher.id,
                                                )) ||
                                                (appliedVoucher.promotion &&
                                                    appliedVoucher.promotion
                                                        .id === voucher.id) ||
                                                voucherInputCode
                                                    .split(',')
                                                    .map((c) =>
                                                        c.trim().toUpperCase(),
                                                    )
                                                    .includes(
                                                        voucher.code.toUpperCase(),
                                                    ))}

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
                                                        0.85,
                                                    )});"
                                                >
                                                    <i
                                                        class="ti ti-ticket text-base mb-0.5 shrink-0"
                                                    ></i>
                                                    <span
                                                        class="text-[7.5px] font-black uppercase tracking-wider leading-none"
                                                    >
                                                        {#if voucher.settings?.is_points_voucher}Poin{:else}Diskon{/if}
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
                                                                    class="text-white text-[7.5px] font-black px-1 py-0.5 rounded leading-none shrink-0"
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
                                                            {#if voucher.settings?.is_points_voucher}
                                                                Konversi Poin: +{formatNumber(userIsNew ? (voucher.settings?.points_new_user || 0) : (voucher.settings?.points_existing_user || 0))} Poin
                                                            {:else}
                                                                Potongan {#if voucher.discount_type === 'percentage'}{Number(
                                                                        voucher.discount_value,
                                                                    )}%{:else}{fmt(
                                                                        voucher.discount_value,
                                                                    )}{/if}
                                                                {#if voucher.max_discount}s/d
                                                                    {fmt(
                                                                        voucher.max_discount,
                                                                    )}{/if}
                                                            {/if}
                                                        </p>
                                                    </div>

                                                    <div
                                                        class="flex items-center justify-between mt-1.5 pt-1.5 border-t border-slate-50 gap-1.5 shrink-0"
                                                    >
                                                        <div
                                                            class="flex items-center gap-1.5 shrink-0"
                                                        >
                                                            <span
                                                                class="text-[8px] bg-slate-100/80 text-slate-500 font-extrabold px-1.5 py-0.5 rounded leading-none"
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
                                                                    class="text-[8px] text-slate-450 hover:text-slate-650 font-extrabold underline cursor-pointer border-0 bg-transparent flex items-center gap-0.5 shrink-0"
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
                                                                onclick={() => {
                                                                    if (
                                                                        isSelected
                                                                    ) {
                                                                        deselectVoucher(
                                                                            voucher,
                                                                        );
                                                                    } else {
                                                                        selectVoucher(
                                                                            voucher,
                                                                        );
                                                                    }
                                                                }}
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
                                                                        subtotal,
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
    .safe-area-bottom {
        padding-bottom: max(0.75rem, env(safe-area-inset-bottom));
    }
</style>
