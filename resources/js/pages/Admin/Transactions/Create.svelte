<script lang="ts">
    import { page, router } from '@inertiajs/svelte';
    import { showToast } from '@/utils/toast';
    import Input from '@/components/ui/Input.svelte';
    import InputCurrency from '@/components/ui/InputCurrency.svelte';
    import Select from '@/components/ui/Select.svelte';
    import SelectSearch from '@/components/ui/SelectSearch.svelte';
    import Textarea from '@/components/ui/Textarea.svelte';
    import { onDestroy, onMount } from 'svelte';

    let {
        products = [],
        categories = [],
        customers = [],
        paymentMethods = [],
        midtransEnabledMethods = [],
        midtransAdminFee = 0,
        couriers = [],
        storeName = '',
    } = $props();

    // Live clock state
    let currentTime = $state('');
    let clockInterval: any = null;

    onMount(() => {
        const updateClock = () => {
            currentTime = new Date().toLocaleTimeString('id-ID', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
            });
        };
        updateClock();
        clockInterval = setInterval(updateClock, 1000);
    });

    onDestroy(async () => {
        if (clockInterval) clearInterval(clockInterval);
        await stopScanning();
    });

    // Helper functions to safely extract price, stock, and is_unlimited
    function getRawPrice(obj: any): number {
        if (!obj) return 0;
        if (typeof obj.price !== 'undefined' && obj.price !== null) return Number(obj.price) || 0;
        if (obj.product_price?.price) return Number(obj.product_price.price) || 0;
        if (obj.productPrice?.price) return Number(obj.productPrice.price) || 0;
        return 0;
    }

    function getRawStock(obj: any): number {
        if (!obj) return 0;
        if (typeof obj.stock !== 'undefined' && obj.stock !== null) return Number(obj.stock) || 0;
        if (typeof obj.quantity !== 'undefined' && obj.quantity !== null) return Number(obj.quantity) || 0;
        if (obj.product_stock?.stock) return Number(obj.product_stock.stock) || 0;
        if (obj.productStock?.stock) return Number(obj.productStock.stock) || 0;
        return 0;
    }

    function checkIsUnlimited(obj: any): boolean {
        if (!obj) return false;
        if (obj.is_unlimited || obj.isUnlimited) return true;
        if (obj.product_stock?.is_unlimited || obj.productStock?.is_unlimited) return true;
        return false;
    }

    function getProductPriceInfo(p: any): string {
        if (p.variants && p.variants.length > 0) {
            const prices = p.variants
                .map((v: any) => getRawPrice(v) || getRawPrice(p))
                .filter((pr: number) => pr > 0);

            if (prices.length > 0) {
                const minP = Math.min(...prices);
                const maxP = Math.max(...prices);
                if (minP === maxP) return fmt(minP);
                return `${fmt(minP)} - ${fmt(maxP)}`;
            }
        }
        return fmt(getRawPrice(p));
    }

    function getProductStockInfo(p: any): string {
        if (checkIsUnlimited(p)) return 'Unlimited';

        if (p.variants && p.variants.length > 0) {
            const hasUnlimited = p.variants.some((v: any) => checkIsUnlimited(v));
            if (hasUnlimited) return 'Unlimited';

            const totalStock = p.variants.reduce(
                (acc: number, v: any) => acc + getRawStock(v),
                0,
            );
            return totalStock.toString();
        }
        return getRawStock(p).toString();
    }

    // Filter states for catalog
    let searchQuery = $state('');
    let selectedCat = $state('all');

    // Filtered products list
    const filteredProducts = $derived(
        products.filter((p: any) => {
            const matchesCat =
                selectedCat === 'all' ||
                p.category_id?.toString() === selectedCat ||
                p.category?.slug === selectedCat;

            const query = searchQuery.trim().toLowerCase();
            if (!query) return matchesCat;

            const nameMatch = p.name?.toLowerCase().includes(query);
            const skuMatch = p.sku?.toLowerCase().includes(query);
            const variantSkuMatch = p.variants?.some((v: any) =>
                v.sku?.toLowerCase().includes(query),
            );

            return matchesCat && (nameMatch || skuMatch || variantSkuMatch);
        }),
    );

    // Variant modal state
    let showVariantModal = $state(false);
    let selectedProductForVariant = $state<any>(null);

    // QR/Barcode Scanner state
    let showScanModal = $state(false);
    let scanError = $state('');
    let isScanning = $state(false);
    let html5QrScanner: any = null;
    let availableCameras = $state<any[]>([]);
    let selectedCameraId = $state('');

    // Cart items state
    interface CartLineItem {
        id: string;
        product_id: number;
        variant_id: number | null;
        product_name: string;
        variant_name: string | null;
        image_url: string | null;
        unit_price: number;
        quantity: number;
        stock_available: number;
        is_unlimited: boolean;
    }

    let cart = $state<CartLineItem[]>([]);

    // Customer state connected to SelectSearch
    let selectedCustomerValue = $state('walkin');
    let customerName = $state('');
    let customerPhone = $state('');

    const customerOptions = $derived([
        { id: 'walkin', name: 'Pelanggan Umum (Walk-in)' },
        ...customers.map((c: any) => ({
            id: c.id.toString(),
            name: `${c.name} (${c.phone_number || c.email || 'Tanpa Kontak'})`,
        })),
    ]);

    const isWalkInCustomer = $derived(selectedCustomerValue === 'walkin');

    // Delivery options: ONLY Pickup / Direct Kasir di Toko
    let deliveryType = $state<string>('direct_cashier');

    const deliveryTypeOptions = [
        { id: 'direct_cashier', name: '⚡ Direct Kasir Toko (Langsung Serahkan)' },
        { id: 'pickup', name: '🏪 Pickup / Ambil Sendiri di Toko' },
    ];

    // Payment methods: ONLY Tunai / Cash methods
    const cashPaymentMethodsList = $derived.by(() => {
        const filtered = paymentMethods.filter((pm: any) => {
            const name = (pm.name || '').toLowerCase();
            const type = (pm.type || '').toLowerCase();
            return name.includes('cash') || name.includes('tunai') || name.includes('kasir') || type.includes('cash') || type.includes('tunai') || type.includes('manual');
        });
        if (filtered.length > 0) return filtered;
        return paymentMethods;
    });

    let selectedPaymentMethodId = $state<string>(
        cashPaymentMethodsList[0]?.id ? cashPaymentMethodsList[0].id.toString() : (paymentMethods[0]?.id ? paymentMethods[0].id.toString() : ''),
    );

    const paymentMethodOptions = $derived(
        cashPaymentMethodsList.map((pm: any) => ({
            id: pm.id.toString(),
            name: `💵 ${pm.name} (${pm.type ? pm.type.toUpperCase() : 'TUNAI'})`,
        })),
    );

    let transactionStatusMode = $state<string>('selesai');
    let discountAmount = $state('');
    let notes = $state('');

    const transactionStatusOptions = [
        { id: 'selesai', name: '⚡ LUNAS & Langsung Diambil (Selesai - Direct POS)' },
        { id: 'belum_bayar', name: '⏳ BELUM BAYAR (Menunggu Pembayaran / Invoice)' },
        { id: 'diproses', name: '📦 DIPROSES (Lunas, Siap Ditinggal / Diantar)' },
    ];

    // Cash Payment Calculator
    let cashPaid = $state('');
    const cashPaidNum = $derived(Number(cashPaid.replace(/\D/g, '')) || 0);

    // Totals calculations
    const subtotal = $derived(
        cart.reduce((acc, item) => acc + item.unit_price * item.quantity, 0),
    );
    const discountAmountNum = $derived(Number(discountAmount.replace(/\D/g, '')) || 0);

    const taxEnabled = $derived(
        (page.props as any).settings?.tax_enabled ?? false,
    );
    const taxPct = $derived(
        Number((page.props as any).settings?.tax_percentage) || 0,
    );
    const taxAmount = $derived(
        taxEnabled ? Math.round(subtotal * (taxPct / 100)) : 0,
    );

    const grandTotal = $derived(
        Math.max(0, subtotal + taxAmount - discountAmountNum),
    );

    const cashChange = $derived(
        Math.max(0, cashPaidNum - grandTotal),
    );

    // Form submission state
    let isSubmitting = $state(false);

    function addProductToCart(product: any, variant: any = null) {
        if (!variant && product.variants && product.variants.length > 0) {
            // Product has variants -> open variant selection modal
            selectedProductForVariant = product;
            showVariantModal = true;
            return;
        }

        let price = getRawPrice(product);
        let stock = getRawStock(product);
        let isUnlimited = checkIsUnlimited(variant || product);
        let varName: string | null = null;

        if (variant) {
            price = getRawPrice(variant) || price;
            stock = getRawStock(variant) || stock;
            isUnlimited = checkIsUnlimited(variant) || isUnlimited;
            varName =
                variant.options?.map((o: any) => o.value).join(', ') ||
                variant.sku ||
                'Varian';
        }

        const lineId = `${product.id}-${variant?.id ?? 0}`;
        const existingIndex = cart.findIndex((item) => item.id === lineId);

        const img =
            product.images?.[0]?.url ||
            product.images?.[0]?.path ||
            product.image ||
            null;

        if (existingIndex > -1) {
            const currentQty = cart[existingIndex].quantity;
            if (!isUnlimited && stock > 0 && currentQty + 1 > stock) {
                showToast(`Stok "${product.name}" tersisa ${stock}`, 'warning');
                return;
            }
            cart[existingIndex].quantity += 1;
        } else {
            if (!isUnlimited && stock <= 0) {
                showToast(`Stok produk "${product.name}" habis`, 'error');
                return;
            }
            cart.push({
                id: lineId,
                product_id: product.id,
                variant_id: variant?.id ?? null,
                product_name: product.name,
                variant_name: varName,
                image_url: img,
                unit_price: price,
                quantity: 1,
                stock_available: stock,
                is_unlimited: isUnlimited,
            });
        }
        showToast(`"${product.name}${varName ? ' (' + varName + ')' : ''}" ditambahkan`, 'success');
    }

    function addVariantDirect(variant: any) {
        if (!selectedProductForVariant) return;
        addProductToCart(selectedProductForVariant, variant);
        showVariantModal = false;
        selectedProductForVariant = null;
    }

    function updateQty(lineId: string, delta: number) {
        const index = cart.findIndex((item) => item.id === lineId);
        if (index > -1) {
            const item = cart[index];
            const newQty = item.quantity + delta;
            if (newQty <= 0) {
                cart.splice(index, 1);
            } else if (!item.is_unlimited && item.stock_available > 0 && newQty > item.stock_available) {
                cart[index].quantity = item.stock_available;
                showToast(`Kuantitas maksimal! Stok tersisa ${item.stock_available}`, 'warning');
            } else {
                cart[index].quantity = newQty;
            }
        }
    }

    function handleQtyInput(item: CartLineItem, e: Event) {
        const target = e.target as HTMLInputElement;
        let val = parseInt(target.value, 10);

        if (isNaN(val) || val < 1) {
            val = 1;
        }

        if (!item.is_unlimited && item.stock_available > 0 && val > item.stock_available) {
            val = item.stock_available;
            showToast(`Kuantitas maksimal! Stok tersisa ${item.stock_available}`, 'warning');
        }

        item.quantity = val;
        target.value = val.toString();
    }

    function removeLineItem(lineId: string) {
        cart = cart.filter((item) => item.id !== lineId);
    }

    function clearCart() {
        cart = [];
        cashPaid = '';
        notes = '';
        discountAmount = '';
    }

    // Barcode scanner helpers
    async function startScanning() {
        scanError = '';
        try {
            const { Html5Qrcode } = await import('html5-qrcode');
            const devices = await Html5Qrcode.getCameras();
            if (devices && devices.length > 0) {
                availableCameras = devices;
                if (!selectedCameraId) {
                    const backCam = devices.find(
                        (d: any) =>
                            d.label.toLowerCase().includes('back') ||
                            d.label.toLowerCase().includes('rear'),
                    );
                    selectedCameraId = backCam ? backCam.id : devices[0].id;
                }
                if (!html5QrScanner) {
                    html5QrScanner = new Html5Qrcode('pos-scanner-reader');
                }
                isScanning = true;
                await html5QrScanner.start(
                    selectedCameraId,
                    { fps: 10, qrbox: { width: 250, height: 250 } },
                    (decodedText: string) => {
                        handleScannedBarcode(decodedText);
                    },
                    () => {},
                );
            } else {
                scanError = 'Tidak ada kamera yang ditemukan pada perangkat.';
            }
        } catch (e: any) {
            scanError = 'Gagal mengakses kamera: ' + (e.message || 'Izin kamera ditolak');
        }
    }

    async function stopScanning() {
        if (html5QrScanner && isScanning) {
            try {
                await html5QrScanner.stop();
            } catch (e) {
                console.error('Error stopping scanner:', e);
            }
            isScanning = false;
        }
    }

    function handleScannedBarcode(code: string) {
        const query = code.trim().toLowerCase();
        const matched = products.find((p: any) => {
            return (
                p.sku?.toLowerCase() === query ||
                p.variants?.some((v: any) => v.sku?.toLowerCase() === query)
            );
        });

        if (matched) {
            const matchedVar = matched.variants?.find(
                (v: any) => v.sku?.toLowerCase() === query,
            );
            addProductToCart(matched, matchedVar || null);
            stopScanning();
            showScanModal = false;
        } else {
            showToast(`Produk dengan barcode "${code}" tidak ditemukan`, 'error');
        }
    }

    function handleCheckoutPOS() {
        if (cart.length === 0) {
            showToast('Keranjang kasir masih kosong.', 'error');
            return;
        }

        isSubmitting = true;

        const isPaidMode = transactionStatusMode === 'selesai' || transactionStatusMode === 'diproses';

        const payload = {
            user_id: isWalkInCustomer ? null : selectedCustomerValue,
            customer_name: isWalkInCustomer ? (customerName || 'Pelanggan Umum') : null,
            customer_phone: isWalkInCustomer ? (customerPhone || null) : null,
            items: cart.map((item) => ({
                product_id: item.product_id,
                variant_id: item.variant_id,
                quantity: item.quantity,
                unit_price: item.unit_price,
            })),
            payment_method_id: selectedPaymentMethodId ? Number(selectedPaymentMethodId) : null,
            payment_status: isPaidMode ? 'paid' : 'unpaid',
            status: transactionStatusMode,
            delivery_type: deliveryType,
            shipping_cost: 0,
            discount_amount: discountAmountNum,
            notes: notes || 'Transaksi Tunai Kasir POS',
        };

        router.post('/admin/transactions', payload, {
            onError: (errors: any) => {
                const first = Object.values(errors)[0] as string;
                showToast(first ?? 'Gagal membuat transaksi.', 'error');
                isSubmitting = false;
            },
            onFinish: () => {
                isSubmitting = false;
            },
        });
    }

    function fmt(val: number): string {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
        }).format(val || 0);
    }
</script>

<svelte:head>
    <title>Kasir POS — Point of Sale (Tunai & Pickup Toko)</title>
</svelte:head>

<!-- FULLPAGE STANDALONE POS LAYOUT (Clean White Aesthetic) -->
<div class="fixed inset-0 z-50 bg-slate-100 flex flex-col font-sans overflow-hidden select-none">

    <!-- Standalone Professional White Top Bar -->
    <header class="bg-white text-slate-900 px-6 py-3 flex items-center justify-between shadow-2xs shrink-0 z-20 border-b border-slate-200">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-blue-600 flex items-center justify-center text-white font-black text-xl shadow-xs">
                <i class="ti ti-cash-register"></i>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-base font-outfit font-black tracking-wide uppercase leading-tight text-slate-900">
                        {storeName ? `${storeName} POS` : 'Point of Sale'}
                    </h1>
                    <span class="text-[10px] font-bold px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 tracking-wider">
                        KASIR TUNAI TOKO
                    </span>
                </div>
                <p class="text-[11px] text-slate-500 font-mono">
                    {currentTime ? `${currentTime} WIB` : 'Mesin Kasir Direct'}
                </p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button
                onclick={() => { showScanModal = true; startScanning(); }}
                class="px-4 py-2 bg-amber-50 hover:bg-amber-100 text-amber-900 border border-amber-200 rounded-xl text-xs font-bold transition flex items-center gap-2 active:scale-95 shadow-2xs"
            >
                <i class="ti ti-scan text-base text-amber-600"></i>
                <span>Scan SKU / Barcode</span>
            </button>

            {#if cart.length > 0}
                <button
                    onclick={clearCart}
                    class="px-4 py-2 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 rounded-xl text-xs font-bold transition flex items-center gap-1.5 active:scale-95"
                >
                    <i class="ti ti-trash text-sm"></i>
                    <span>Reset Keranjang ({cart.length})</span>
                </button>
            {/if}

            <div class="h-6 w-px bg-slate-200 mx-1"></div>

            <a
                href="/admin/transactions"
                class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5 active:scale-95 shadow-xs"
                title="Keluar dari mode Kasir"
            >
                <i class="ti ti-power text-sm"></i>
                <span>Keluar POS</span>
            </a>
        </div>
    </header>

    <!-- POS Main Grid (100% Height Fill) -->
    <div class="flex-1 p-4 overflow-hidden grid grid-cols-1 lg:grid-cols-12 gap-4">

        <!-- LEFT COLUMN: Catalog Grid (7/12) -->
        <div class="lg:col-span-7 flex flex-col h-full space-y-3 min-h-0">

            <!-- Search & Category Filter Header -->
            <div class="bg-white rounded-2xl p-3 border border-slate-200/90 shadow-2xs space-y-2.5 shrink-0">
                <Input
                    bind:value={searchQuery}
                    placeholder="Cari nama produk, SKU, atau varian..."
                    icon="ti-search"
                />

                <!-- Category Pills -->
                <div class="flex items-center gap-1.5 overflow-x-auto pb-0.5 scrollbar-none">
                    <button
                        onclick={() => selectedCat = 'all'}
                        class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition whitespace-nowrap
                               {selectedCat === 'all'
                            ? 'bg-blue-600 text-white shadow-xs'
                            : 'bg-slate-100 text-slate-600 hover:bg-slate-200'}"
                    >
                        Semua ({products.length})
                    </button>
                    {#each categories as cat}
                        <button
                            onclick={() => selectedCat = cat.id.toString()}
                            class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition whitespace-nowrap
                                   {selectedCat === cat.id.toString()
                                ? 'bg-blue-600 text-white shadow-xs'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'}"
                        >
                            {cat.name}
                        </button>
                    {/each}
                </div>
            </div>

            <!-- Product Cards Grid (Scrollable) -->
            <div class="flex-1 overflow-y-auto pr-1 scrollbar-thin">
                <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-3">
                    {#each filteredProducts as p}
                        {@const imgUrl = p.images?.[0]?.url || p.images?.[0]?.path || p.image || null}
                        {@const priceStr = getProductPriceInfo(p)}
                        {@const stockStr = getProductStockInfo(p)}
                        {@const hasVariants = p.variants && p.variants.length > 0}

                        <button
                            type="button"
                            onclick={() => addProductToCart(p)}
                            class="bg-white rounded-2xl border border-slate-200/90 shadow-2xs hover:border-blue-500 hover:shadow-md transition text-left flex flex-col justify-between overflow-hidden group cursor-pointer active:scale-98"
                        >
                            <div class="p-2.5 space-y-2">
                                <div class="relative w-full aspect-square rounded-xl bg-slate-50 overflow-hidden border border-slate-100">
                                    {#if imgUrl}
                                        <img src={imgUrl.startsWith('http') ? imgUrl : `/storage/${imgUrl}`} alt={p.name} class="w-full h-full object-cover group-hover:scale-105 transition duration-300" />
                                    {:else}
                                        <div class="w-full h-full flex items-center justify-center text-slate-300">
                                            <i class="ti ti-package text-3xl"></i>
                                        </div>
                                    {/if}

                                    <!-- Category Badge -->
                                    <span class="absolute top-1.5 left-1.5 px-2 py-0.5 rounded-md text-[9px] font-extrabold text-slate-700 bg-white/90 shadow-2xs border border-slate-200/80 backdrop-blur-xs truncate max-w-[80%]">
                                        {p.category?.name || 'Umum'}
                                    </span>

                                    <!-- Variant Indicator Badge -->
                                    {#if hasVariants}
                                        <span class="absolute bottom-1.5 right-1.5 px-2 py-0.5 rounded-md text-[9px] font-extrabold text-amber-900 bg-amber-100 border border-amber-200 shadow-2xs flex items-center gap-1">
                                            <i class="ti ti-layers-subtract text-[10px]"></i>
                                            {p.variants.length} Varian
                                        </span>
                                    {/if}
                                </div>

                                <div>
                                    <h4 class="font-bold text-xs text-slate-800 line-clamp-2 leading-tight group-hover:text-blue-600 transition">
                                        {p.name}
                                    </h4>
                                    {#if p.sku}
                                        <p class="text-[10px] font-mono text-slate-400 mt-0.5 truncate">SKU: {p.sku}</p>
                                    {/if}
                                </div>
                            </div>

                            <div class="p-2.5 pt-1.5 flex items-center justify-between border-t border-slate-100 bg-slate-50/60">
                                <div class="min-w-0 flex-1">
                                    <p class="font-black text-xs text-blue-700 truncate">{priceStr}</p>
                                    <p class="text-[10px] font-medium text-slate-500">Stok: {stockStr}</p>
                                </div>
                                <div class="w-7 h-7 rounded-xl bg-blue-600 text-white group-hover:bg-blue-700 transition flex items-center justify-center shadow-2xs shrink-0">
                                    <i class="ti {hasVariants ? 'ti-layers-subtract' : 'ti-plus'} text-xs font-bold"></i>
                                </div>
                            </div>
                        </button>
                    {:else}
                        <div class="col-span-full bg-white rounded-2xl p-8 text-center border border-slate-200 space-y-2">
                            <i class="ti ti-package-off text-4xl text-slate-300"></i>
                            <p class="text-sm font-bold text-slate-700">Tidak ada produk ditemukan</p>
                            <p class="text-xs text-slate-400">Coba gunakan kata kunci lain atau pilih kategori lain.</p>
                        </div>
                    {/each}
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: Order Cart Summary (5/12) - Fully Scrollable Layout -->
        <div class="lg:col-span-5 bg-white rounded-3xl border border-slate-200 shadow-sm p-4 flex flex-col h-full overflow-y-auto scrollbar-thin space-y-3">

            <!-- Header Order -->
            <div class="flex items-center justify-between pb-2.5 border-b border-slate-100 shrink-0">
                <span class="font-outfit font-black text-sm text-slate-900 uppercase tracking-wider flex items-center gap-2">
                    <i class="ti ti-shopping-cart text-lg text-blue-600"></i>
                    Keranjang Pesanan ({cart.length})
                </span>
                <span class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                    Direct Cashier
                </span>
            </div>

            <!-- Customer Selection connected to SelectSearch UI component -->
            <div class="space-y-2 bg-slate-50 p-3 rounded-2xl border border-slate-200/80 shrink-0">
                <SelectSearch
                    bind:value={selectedCustomerValue}
                    options={customerOptions}
                    label="PILIH PELANGGAN"
                    placeholder="Pilih Pelanggan..."
                />

                {#if isWalkInCustomer}
                    <div class="grid grid-cols-2 gap-1.5 pt-1">
                        <Input
                            bind:value={customerName}
                            placeholder="Nama Pelanggan (Umum)"
                        />
                        <Input
                            bind:value={customerPhone}
                            placeholder="No. HP / WA (Opsional)"
                        />
                    </div>
                {/if}
            </div>

            <!-- Cart Items List (Dedicated Scrollable Container) -->
            <div class="bg-slate-50/80 p-3 rounded-2xl border border-slate-200/80 space-y-2 min-h-[140px] max-h-56 overflow-y-auto scrollbar-thin shrink-0">
                <div class="flex items-center justify-between border-b border-slate-200/60 pb-1.5 mb-2">
                    <span class="text-[10px] font-extrabold text-slate-500 uppercase tracking-wider">
                        DAFTAR BARANG DALAM KERANJANG ({cart.length})
                    </span>
                    {#if cart.length > 0}
                        <button
                            onclick={clearCart}
                            class="text-[10px] text-rose-600 hover:text-rose-700 font-bold flex items-center gap-1"
                        >
                            <i class="ti ti-trash text-xs"></i>
                            Kosongkan
                        </button>
                    {/if}
                </div>

                {#each cart as item (item.id)}
                    <div class="flex items-center justify-between p-2 rounded-xl bg-white border border-slate-200/80 shadow-2xs gap-2">
                        <div class="flex items-center gap-2 min-w-0 flex-1">
                            <div class="w-9 h-9 rounded-xl bg-slate-50 border border-slate-200 overflow-hidden shrink-0">
                                {#if item.image_url}
                                    <img src={item.image_url.startsWith('http') ? item.image_url : `/storage/${item.image_url}`} alt={item.product_name} class="w-full h-full object-cover" />
                                {:else}
                                    <div class="w-full h-full flex items-center justify-center text-slate-300 text-xs">
                                        <i class="ti ti-package"></i>
                                    </div>
                                {/if}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="font-bold text-xs text-slate-800 truncate">{item.product_name}</p>
                                {#if item.variant_name}
                                    <span class="inline-block px-1.5 py-0.2 rounded bg-amber-100 text-amber-900 font-extrabold text-[9px] truncate">
                                        Varian: {item.variant_name}
                                    </span>
                                {/if}
                                <p class="text-[11px] font-black text-slate-900">{fmt(item.unit_price)}</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-1.5 shrink-0">
                            <!-- Editable Qty Input & Stepper Buttons -->
                            <div class="flex items-center rounded-lg bg-slate-50 border border-slate-200 p-0.5 shadow-2xs">
                                <button
                                    onclick={() => updateQty(item.id, -1)}
                                    class="w-5 h-5 rounded hover:bg-slate-200 flex items-center justify-center text-slate-600 text-xs font-bold transition active:scale-95"
                                    title="Kurangi"
                                >
                                    -
                                </button>
                                <input
                                    type="number"
                                    min="1"
                                    max={!item.is_unlimited && item.stock_available > 0 ? item.stock_available : undefined}
                                    value={item.quantity}
                                    oninput={(e) => handleQtyInput(item, e)}
                                    class="w-11 text-center font-extrabold text-xs text-slate-800 focus:outline-none focus:bg-blue-50 focus:ring-1 focus:ring-blue-400 rounded py-0.5 appearance-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                />
                                <button
                                    onclick={() => updateQty(item.id, 1)}
                                    disabled={!item.is_unlimited && item.stock_available > 0 && item.quantity >= item.stock_available}
                                    class="w-5 h-5 rounded hover:bg-slate-200 disabled:opacity-30 disabled:hover:bg-transparent flex items-center justify-center text-slate-600 text-xs font-bold transition active:scale-95"
                                    title="Tambah"
                                >
                                    +
                                </button>
                            </div>

                            <button
                                onclick={() => removeLineItem(item.id)}
                                class="w-6 h-6 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 flex items-center justify-center transition"
                            >
                                <i class="ti ti-trash text-xs"></i>
                            </button>
                        </div>
                    </div>
                {:else}
                    <div class="p-6 text-center border-2 border-dashed border-slate-200 rounded-2xl space-y-1 bg-white">
                        <i class="ti ti-shopping-cart-off text-3xl text-slate-300"></i>
                        <p class="text-xs font-bold text-slate-500">Keranjang Kasir Kosong</p>
                        <p class="text-[10px] text-slate-400">Klik produk pada katalog di sebelah kiri untuk menambah ke pesanan.</p>
                    </div>
                {/each}
            </div>

            <!-- Payment & Delivery Setup (ONLY Pickup & ONLY Cash / Tunai) -->
            <div class="pt-2 border-t border-slate-200 space-y-2.5 shrink-0">

                <!-- Metode Pembayaran -->
                <div>
                    <Select
                        bind:value={selectedPaymentMethodId}
                        options={paymentMethodOptions}
                        label="METODE PEMBAYARAN"
                    />
                </div>





                <!-- Total Amount Banner & Submit -->
                <div class="p-3 rounded-2xl bg-blue-600 text-white flex items-center justify-between gap-3 shadow-md">
                    <div>
                        <span class="text-[9px] text-blue-100 uppercase tracking-widest block font-bold">TOTAL HARGA</span>
                        <span class="font-outfit font-black text-xl text-white">{fmt(grandTotal)}</span>
                    </div>

                    <button
                        onclick={handleCheckoutPOS}
                        disabled={cart.length === 0 || isSubmitting}
                        class="px-5 py-2.5 rounded-xl bg-white text-blue-700 hover:bg-blue-50 disabled:opacity-50 font-black text-xs shadow-sm transition active:scale-95 flex items-center gap-1.5 cursor-pointer"
                    >
                        {#if isSubmitting}
                            <i class="ti ti-loader animate-spin text-sm"></i>
                            <span>Memproses...</span>
                        {:else}
                            <i class="ti ti-check text-base"></i>
                            <span>Proses & Bayar</span>
                        {/if}
                    </button>
                </div>
            </div>

        </div>

    </div>

</div>

<!-- VARIANT SELECTION MODAL -->
{#if showVariantModal && selectedProductForVariant}
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <div class="bg-white rounded-3xl p-5 max-w-md w-full shadow-2xl space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center text-amber-600 font-bold shrink-0">
                        <i class="ti ti-layers-subtract text-xl"></i>
                    </div>
                    <div class="min-w-0">
                        <h3 class="font-bold text-sm text-slate-900 truncate">{selectedProductForVariant.name}</h3>
                        <p class="text-xs text-slate-400">Pilih varian produk yang akan ditambahkan</p>
                    </div>
                </div>
                <button
                    onclick={() => { showVariantModal = false; selectedProductForVariant = null; }}
                    class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 flex items-center justify-center"
                >
                    <i class="ti ti-x"></i>
                </button>
            </div>

            <!-- List of Variants -->
            <div class="space-y-2 max-h-72 overflow-y-auto pr-1 scrollbar-thin">
                {#each selectedProductForVariant.variants as varItem}
                    {@const varName = varItem.options?.map((o: any) => o.value).join(', ') || varItem.sku || 'Varian'}
                    {@const price = getRawPrice(varItem) || getRawPrice(selectedProductForVariant)}
                    {@const stockStr = checkIsUnlimited(varItem) ? 'Unlimited' : (getRawStock(varItem) + ' unit')}

                    <div
                        class="p-3 rounded-2xl border border-slate-200 hover:border-blue-500 bg-slate-50/50 hover:bg-blue-50/40 transition flex items-center justify-between gap-3"
                    >
                        <div class="min-w-0 flex-1">
                            <span class="font-extrabold text-xs text-slate-800 block truncate">
                                {varName}
                            </span>
                            {#if varItem.sku}
                                <span class="text-[10px] font-mono text-slate-400">SKU: {varItem.sku}</span>
                            {/if}
                            <span class="text-[10px] font-medium text-slate-500 block">Stok: {stockStr}</span>
                        </div>

                        <div class="text-right shrink-0 flex items-center gap-3">
                            <span class="font-black text-xs text-blue-700 block">{fmt(price)}</span>
                            <button
                                onclick={() => addVariantDirect(varItem)}
                                class="px-3.5 py-1.5 rounded-xl bg-blue-600 text-white hover:bg-blue-700 font-bold text-xs transition active:scale-95 flex items-center gap-1 shadow-2xs cursor-pointer"
                            >
                                <i class="ti ti-plus text-xs"></i>
                                <span>Pilih</span>
                            </button>
                        </div>
                    </div>
                {/each}
            </div>

            <div class="pt-2 border-t border-slate-100 flex justify-end">
                <button
                    onclick={() => { showVariantModal = false; selectedProductForVariant = null; }}
                    class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold text-xs transition cursor-pointer"
                >
                    Tutup
                </button>
            </div>
        </div>
    </div>
{/if}

<!-- BARCODE / QR SCANNER MODAL -->
{#if showScanModal}
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <div class="bg-white rounded-3xl p-6 max-w-md w-full shadow-2xl space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2">
                    <i class="ti ti-scan text-xl text-amber-500"></i>
                    <h3 class="font-bold text-base text-slate-900">Scan Barcode / QR SKU</h3>
                </div>
                <button onclick={async () => { await stopScanning(); showScanModal = false; }} class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 flex items-center justify-center">
                    <i class="ti ti-x"></i>
                </button>
            </div>

            <div class="relative w-full aspect-square rounded-2xl bg-slate-900 overflow-hidden">
                <div id="pos-scanner-reader" class="w-full h-full"></div>
            </div>

            {#if scanError}
                <p class="text-xs font-bold text-rose-600 text-center">{scanError}</p>
            {/if}

            <p class="text-xs text-slate-500 text-center">Arahkan kamera ke barcode SKU produk untuk langsung menambah ke keranjang kasir.</p>
        </div>
    </div>
{/if}
