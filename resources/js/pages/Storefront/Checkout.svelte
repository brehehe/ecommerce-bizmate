<script lang="ts">
    import StorefrontLayout from '@/components/layouts/StorefrontLayout.svelte';
    import { page, router } from '@inertiajs/svelte';
    import { showToast } from '@/utils/toast';

    let {
        cartItems = [],
        addresses = [],
        paymentMethods = [],
        activePromotions = [],
        storeName = '',
        storeLogo = '',
        storeOriginCity = null,
        appFee = 0,
    } = $props();

    const primary = $derived(
        (page.props as any).theme?.primary_color ?? '#ee4d2d',
    );
    const secondary = $derived(
        (page.props as any).theme?.secondary_color ?? '#fa7315',
    );

    // Selected address
    let selectedAddressId = $state(
        addresses.find((a: any) => a.is_primary)?.id ??
            addresses[0]?.id ??
            null,
    );
    let showAddressModal = $state(false);

    // Payment
    let selectedPaymentMethodId = $state(paymentMethods[0]?.id ?? null);
    const selectedPaymentMethod = $derived(
        paymentMethods.find((pm: any) => pm.id === selectedPaymentMethodId),
    );

    // Shipping
    let availableCouriers = [
        'jne',
        'tiki',
        'pos',
        'jnt',
        'sicepat',
        'ninja',
        'ide',
        'sap',
        'wahana',
        'lion',
        'rex',
        'sentral',
    ];
    const courierLabels: Record<string, string> = {
        jne: 'JNE',
        tiki: 'TIKI',
        pos: 'POS Indonesia',
        jnt: 'J&T Express',
        sicepat: 'SiCepat',
        ninja: 'Ninja Xpress',
        ide: 'ID Express',
        sap: 'SAP Express',
        wahana: 'Wahana Express',
        lion: 'Lion Parcel',
        rex: 'REX',
        sentral: 'Sentral Cargo',
    };
    let selectedCourier = $state('');
    let shippingOptions: any[] = $state([]);
    let selectedShipping: any = $state(null);
    let loadingShipping = $state(false);
    let shippingError = $state('');

    // Voucher
    let voucherInputCode = $state('');
    let appliedVoucher: any = $state(null);
    let voucherError = $state('');
    let voucherLoading = $state(false);

    // Notes
    let orderNotes = $state('');

    // Submitting
    let isSubmitting = $state(false);

    function fmt(price: any): string {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
        }).format(Number(price) || 0);
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
        const variant = item.productVariant;
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
        Number(selectedShipping ? (selectedShipping.cost[0]?.value ?? 0) : 0),
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
    const grandTotal = $derived(
        Math.max(
            0,
            subtotal -
                discountAmount +
                (shippingFee - shippingDiscount) +
                adminFee +
                applicationFee,
        ),
    );

    // Total weight for shipping
    const totalWeightGrams = $derived(
        cartItems.reduce((acc: number, item: any) => {
            const w =
                item.productVariant?.weight ?? item.product?.weight ?? 1000;
            return acc + w * item.quantity;
        }, 0),
    );

    async function fetchShipping() {
        if (!selectedAddress || !selectedCourier) return;

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
                        selectedAddress.city_id ??
                        selectedAddress.regency_id ??
                        '',
                    weight:
                        Math.max(1, Math.ceil(totalWeightGrams / 1000)) * 1000,
                    courier: selectedCourier,
                }),
            });

            const data = await resp.json();
            if (data.results && data.results.length > 0) {
                const services = data.results[0]?.costs ?? [];
                shippingOptions = services;
            } else {
                shippingError = 'Tidak ada layanan pengiriman tersedia.';
            }
        } catch {
            shippingError = 'Gagal memuat ongkir. Coba lagi.';
        } finally {
            loadingShipping = false;
        }
    }

    async function applyVoucher() {
        if (!voucherInputCode.trim()) return;
        voucherLoading = true;
        voucherError = '';
        appliedVoucher = null;

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
                    code: voucherInputCode,
                    subtotal: subtotal,
                    shipping_fee: shippingFee,
                }),
            });

            const data = await resp.json();
            if (data.valid) {
                appliedVoucher = data;
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
    }

    function submitCheckout() {
        if (!selectedAddressId) {
            showToast('Pilih alamat pengiriman terlebih dahulu.', 'error');
            return;
        }
        if (!selectedPaymentMethodId) {
            showToast('Pilih metode pembayaran.', 'error');
            return;
        }
        if (!selectedShipping) {
            showToast('Pilih layanan pengiriman terlebih dahulu.', 'error');
            return;
        }

        isSubmitting = true;

        router.post(
            '/checkout',
            {
                customer_address_id: selectedAddressId,
                payment_method_id: selectedPaymentMethodId,
                shipping_courier: selectedCourier,
                shipping_service: selectedShipping?.service,
                shipping_etd: selectedShipping?.cost?.[0]?.etd ?? '',
                shipping_fee: shippingFee,
                voucher_code:
                    appliedVoucher?.promotion?.code ?? voucherInputCode ?? '',
                notes: orderNotes,
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

<StorefrontLayout {storeName} {storeLogo} hideMobileFooter={true}>
    <div class="min-h-screen bg-slate-50">
        <!-- Header -->
        <div class="bg-white border-b border-slate-200 sticky top-0 z-30">
            <div class="max-w-6xl mx-auto px-4 h-14 flex items-center gap-3">
                <button
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
                    <!-- Address Section -->
                    <div
                        class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden"
                    >
                        <div
                            class="py-2.5 px-4 flex items-center justify-between border-b border-slate-100"
                        >
                            <div class="flex items-center gap-2">
                                <i
                                    class="ti ti-map-pin text-lg"
                                    style="color:{primary}"
                                ></i>
                                <span class="font-bold text-slate-800 text-sm"
                                    >Alamat Pengiriman</span
                                >
                            </div>
                            <button
                                onclick={() => (showAddressModal = true)}
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
                                            {#if selectedAddress.village_name}, {selectedAddress.village_name}{/if}
                                            {#if selectedAddress.district_name}, {selectedAddress.district_name}{/if}
                                            {#if selectedAddress.regency_name}, {selectedAddress.regency_name}{/if}
                                            {#if selectedAddress.province_name}, {selectedAddress.province_name}{/if}
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
                                    <i class="ti ti-alert-triangle mr-1"></i>
                                    Belum ada alamat.
                                    <a
                                        href="/profile/addresses?from=checkout"
                                        use:inertia
                                        class="font-black underline"
                                        >Tambah Alamat</a
                                    >
                                </div>
                            </div>
                        {/if}
                    </div>

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
                                {@const variant = item.productVariant}
                                {@const imgUrl = getCartItemImage(item)}
                                <div class="px-4 py-3 flex gap-3">
                                    <img
                                        src={imgUrl}
                                        alt={product?.name}
                                        class="w-14 h-14 object-cover rounded-lg shrink-0 border border-slate-100"
                                        onerror={(e: any) => {
                                            e.target.src = '/noimage/image.png';
                                        }}
                                    />
                                    <div class="flex-1 min-w-0">
                                        <p
                                            class="text-sm font-semibold text-slate-800 leading-tight truncate"
                                        >
                                            {product?.name}
                                        </p>
                                        {#if variant}
                                            <p
                                                class="text-xs text-slate-500 mt-0.5"
                                            >
                                                {variant.options
                                                    ?.map((o: any) => o.name)
                                                    .join(' / ')}
                                            </p>
                                        {/if}
                                        <div
                                            class="flex items-center justify-between mt-1.5"
                                        >
                                            <span class="text-xs text-slate-500"
                                                >x{item.quantity}</span
                                            >
                                            <span
                                                class="text-sm font-bold"
                                                style="color:{primary}"
                                                >{fmt(item.unit_price)}</span
                                            >
                                        </div>
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

                    <!-- Shipping Section -->
                    <div
                        class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden"
                    >
                        <div class="px-4 pt-4 pb-3">
                            <div class="flex items-center gap-2 mb-3">
                                <i
                                    class="ti ti-truck text-lg"
                                    style="color:{primary}"
                                ></i>
                                <span class="font-bold text-slate-800 text-sm"
                                    >Pengiriman</span
                                >
                            </div>

                            {#if !selectedAddress}
                                <p class="text-xs text-slate-400 italic">
                                    Pilih alamat terlebih dahulu untuk mengecek
                                    ongkir.
                                </p>
                            {:else}
                                <!-- Courier selection -->
                                <div class="mb-3">
                                    <p
                                        class="text-xs font-semibold text-slate-600 mb-2"
                                    >
                                        Pilih Kurir
                                    </p>
                                    <div class="flex flex-wrap gap-2">
                                        {#each availableCouriers as courier}
                                            <button
                                                onclick={() => {
                                                    selectedCourier = courier;
                                                    fetchShipping();
                                                }}
                                                class="px-3 py-1.5 rounded-lg text-xs font-bold border-2 transition {selectedCourier ===
                                                courier
                                                    ? 'text-white border-transparent'
                                                    : 'border-slate-200 text-slate-600 hover:border-slate-300'}"
                                                style={selectedCourier ===
                                                courier
                                                    ? `background-color:${primary}; border-color:${primary}`
                                                    : ''}
                                                >{courierLabels[courier] ??
                                                    courier.toUpperCase()}</button
                                            >
                                        {/each}
                                    </div>
                                </div>

                                {#if loadingShipping}
                                    <div class="space-y-2">
                                        {#each [1, 2, 3] as _}
                                            <div
                                                class="h-14 bg-slate-100 rounded-xl animate-pulse"
                                            ></div>
                                        {/each}
                                    </div>
                                {:else if shippingError}
                                    <p class="text-xs text-red-500">
                                        {shippingError}
                                    </p>
                                {:else if shippingOptions.length > 0}
                                    <div class="space-y-2">
                                        {#each shippingOptions as opt}
                                            <button
                                                onclick={() =>
                                                    (selectedShipping = opt)}
                                                class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl border-2 text-left transition {selectedShipping?.service ===
                                                opt.service
                                                    ? 'border-transparent'
                                                    : 'border-slate-200 hover:border-slate-300'}"
                                                style={selectedShipping?.service ===
                                                opt.service
                                                    ? `border-color:${primary}; background-color:${primary}10`
                                                    : ''}
                                            >
                                                <div>
                                                    <p
                                                        class="text-sm font-bold text-slate-800"
                                                    >
                                                        {selectedCourier.toUpperCase()}
                                                        {opt.service}
                                                    </p>
                                                    <p
                                                        class="text-xs text-slate-500"
                                                    >
                                                        {opt.description} • Estimasi
                                                        {opt.cost?.[0]?.etd ??
                                                            '-'} hari
                                                    </p>
                                                </div>
                                                <div class="text-right">
                                                    <p
                                                        class="text-sm font-bold"
                                                        style="color:{primary}"
                                                    >
                                                        {fmt(
                                                            opt.cost?.[0]
                                                                ?.value ?? 0,
                                                        )}
                                                    </p>
                                                    {#if selectedShipping?.service === opt.service}
                                                        <i
                                                            class="ti ti-check text-xs"
                                                            style="color:{primary}"
                                                        ></i>
                                                    {/if}
                                                </div>
                                            </button>
                                        {/each}
                                    </div>
                                {:else if selectedCourier}
                                    <p class="text-xs text-slate-400 italic">
                                        Memilih kurir untuk melihat layanan...
                                    </p>
                                {:else}
                                    <p class="text-xs text-slate-400 italic">
                                        Pilih kurir pengiriman di atas.
                                    </p>
                                {/if}
                            {/if}
                        </div>
                    </div>

                    <!-- Voucher Section -->
                    <div
                        class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden"
                    >
                        <div class="px-4 pt-4 pb-4">
                            <div class="flex items-center gap-2 mb-3">
                                <i
                                    class="ti ti-ticket text-lg"
                                    style="color:{primary}"
                                ></i>
                                <span class="font-bold text-slate-800 text-sm"
                                    >Voucher</span
                                >
                            </div>

                            {#if appliedVoucher}
                                <div
                                    class="flex items-center justify-between bg-green-50 border border-green-200 rounded-xl px-3 py-2.5"
                                >
                                    <div>
                                        <p
                                            class="text-sm font-bold text-green-700"
                                        >
                                            {appliedVoucher.promotion_name}
                                        </p>
                                        <p class="text-xs text-green-600">
                                            {#if appliedVoucher.discount_amount > 0}
                                                Hemat {fmt(
                                                    appliedVoucher.discount_amount,
                                                )}
                                            {:else if appliedVoucher.shipping_discount > 0}
                                                Gratis ongkir {fmt(
                                                    appliedVoucher.shipping_discount,
                                                )}
                                            {/if}
                                        </p>
                                    </div>
                                    <button
                                        onclick={removeVoucher}
                                        class="text-green-600 hover:text-green-800 p-1"
                                    >
                                        <i class="ti ti-x"></i>
                                    </button>
                                </div>
                            {:else}
                                <div class="flex gap-2">
                                    <input
                                        type="text"
                                        bind:value={voucherInputCode}
                                        placeholder="Masukkan kode voucher"
                                        class="flex-1 px-3 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:border-transparent uppercase"
                                        style="--tw-ring-color:{primary}"
                                        onkeydown={(e: any) =>
                                            e.key === 'Enter' && applyVoucher()}
                                    />
                                    <button
                                        onclick={applyVoucher}
                                        disabled={voucherLoading ||
                                            !voucherInputCode.trim()}
                                        class="px-4 py-2 rounded-xl text-sm font-bold text-white transition disabled:opacity-50"
                                        style="background:{primary}"
                                    >
                                        {voucherLoading ? '...' : 'Pakai'}
                                    </button>
                                </div>
                                {#if voucherError}
                                    <p class="text-xs text-red-500 mt-1">
                                        {voucherError}
                                    </p>
                                {/if}
                            {/if}
                        </div>
                    </div>

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
                                                    Pembayaran Otomatis Terverifikasi
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
                                <div class="flex justify-between">
                                    <span class="text-slate-600"
                                        >Ongkos Kirim</span
                                    >
                                    <span class="font-semibold text-slate-800"
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

                            <!-- Desktop submit button -->
                            <button
                                onclick={submitCheckout}
                                disabled={isSubmitting ||
                                    !selectedAddressId ||
                                    !selectedPaymentMethodId ||
                                    !selectedShipping}
                                class="hidden lg:flex w-full mt-4 items-center justify-center gap-2 px-6 py-3 rounded-xl font-bold text-white transition disabled:opacity-50 disabled:cursor-not-allowed"
                                style="background:linear-gradient(to right, {primary}, {secondary})"
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
                        !selectedAddressId ||
                        !selectedPaymentMethodId ||
                        !selectedShipping}
                    class="flex items-center gap-2 px-6 py-3 rounded-xl font-bold text-white transition disabled:opacity-50 disabled:cursor-not-allowed"
                    style="background:linear-gradient(to right, {primary}, {secondary})"
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
        <div
            class="fixed inset-0 z-50 flex items-end lg:items-center justify-center"
            onclick={() => (showAddressModal = false)}
        >
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
            <div
                class="relative z-10 bg-white w-full lg:max-w-lg rounded-t-3xl lg:rounded-2xl max-h-[80vh] overflow-y-auto"
                onclick={(e: any) => e.stopPropagation()}
            >
                <div
                    class="sticky top-0 bg-white px-4 pt-4 pb-3 border-b border-slate-100 flex items-center justify-between"
                >
                    <h3 class="font-bold text-slate-800">Pilih Alamat</h3>
                    <button
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
                    <a
                        href="/profile/addresses?from=checkout"
                        class="flex items-center gap-2 text-sm font-semibold px-3 py-2.5 rounded-xl border-2 border-dashed border-slate-200 hover:border-slate-300 text-slate-500 hover:text-slate-700 transition"
                    >
                        <i class="ti ti-plus text-lg"></i>
                        Tambah Alamat Baru
                    </a>
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
