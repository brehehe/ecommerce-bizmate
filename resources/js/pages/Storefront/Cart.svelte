<script lang="ts">
    import StorefrontLayout from '@/components/layouts/StorefrontLayout.svelte';
    import { page, Link, router } from '@inertiajs/svelte';
    import { fade } from 'svelte/transition';

    let { cartItems = [], storeName = '' } = $props();

    const primary = $derived((page.props as any).theme?.primary_color ?? '#ee4d2d');
    const secondary = $derived((page.props as any).theme?.secondary_color ?? '#fa7315');

    // Selection states
    let selectedIds = $state<number[]>([]);
    let hasInitialized = $state(false);

    $effect(() => {
        if (cartItems.length > 0 && !hasInitialized) {
            selectedIds = cartItems.map((item: any) => item.id);
            hasInitialized = true;
        }
    });

    const isAllSelected = $derived(
        cartItems.length > 0 && selectedIds.length === cartItems.length
    );

    function toggleSelectAll() {
        if (isAllSelected) {
            selectedIds = [];
        } else {
            selectedIds = cartItems.map((item: any) => item.id);
        }
    }

    function toggleItem(id: number) {
        if (selectedIds.includes(id)) {
            selectedIds = selectedIds.filter((x) => x !== id);
        } else {
            selectedIds = [...selectedIds, id];
        }
    }

    const selectedItems = $derived(
        cartItems.filter((item: any) => selectedIds.includes(item.id))
    );

    const selectedCartSubtotal = $derived(
        selectedItems.reduce((acc: number, item: any) => acc + (item.subtotal ?? 0), 0)
    );

    const selectedTotalQuantity = $derived(
        selectedItems.reduce((acc: number, item: any) => acc + item.quantity, 0)
    );

    // Format currency to Indonesian Rupiah
    function fmt(price: any): string {
        const n = Number(price);
        if (!n) return 'Rp 0';
        return 'Rp ' + n.toLocaleString('id-ID');
    }

    function withOpacity(hex: string, alpha: number) {
        if (!hex) return `rgba(0, 0, 0, ${alpha})`;
        let r = 0, g = 0, b = 0;
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
            return formatImagePath(item.product.images[0].url || item.product.images[0].path);
        }
        if (item.product?.image) {
            return formatImagePath(item.product.image);
        }
        return '/noimage/image.png';
    }

    function formatImagePath(path: string | null): string {
        if (!path) return '/noimage/image.png';
        if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/')) {
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
        
        // If stock is limited, check bounds
        if (!item.is_unlimited && newQty > item.stock) {
            alert(`Stok tidak mencukupi. Stok maksimal: ${item.stock}`);
            return;
        }

        router.patch(`/cart/${item.id}`, {
            quantity: newQty
        }, {
            preserveScroll: true
        });
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
                    selectedIds = selectedIds.filter((id) => id !== itemToDelete.id);
                    itemToDelete = null;
                }
            });
        } else if (deleteType === 'selected') {
            selectedIds.forEach((id) => {
                router.delete(`/cart/${id}`, {
                    preserveScroll: true,
                    preserveState: true
                });
            });
            selectedIds = [];
        } else if (deleteType === 'all') {
            cartItems.forEach((item: any) => {
                router.delete(`/cart/${item.id}`, {
                    preserveScroll: true,
                    preserveState: true
                });
            });
            selectedIds = [];
        }
    }

    // Payment Gateway Simulation States
    let paymentModalOpen = $state(false);
    let selectedMethod = $state<'qris' | 'bca' | 'mandiri' | 'cc'>('qris');
    let checkoutLoading = $state(false);
    let successModalOpen = $state(false);
    let checkoutSuccessData = $state<{ transactionId: string; totalAmount: number; selectedCount: number } | null>(null);

    // CC Mock states
    let cardNumber = $state('');
    let cardExpiry = $state('');
    let cardCvv = $state('');

    function startCheckout() {
        if (selectedItems.length === 0) {
            alert('Silakan pilih minimal satu produk untuk melakukan pembayaran.');
            return;
        }
        paymentModalOpen = true;
    }

    function processPayment() {
        if (selectedMethod === 'cc') {
            if (!cardNumber || !cardExpiry || !cardCvv) {
                alert('Silakan lengkapi informasi kartu kredit Anda.');
                return;
            }
        }
        checkoutLoading = true;
        
        setTimeout(() => {
            checkoutLoading = false;
            paymentModalOpen = false;
            
            // Set success data
            checkoutSuccessData = {
                transactionId: 'TRX-' + Math.floor(100000 + Math.random() * 900000),
                totalAmount: selectedCartSubtotal,
                selectedCount: selectedTotalQuantity
            };
            
            successModalOpen = true;

            // Delete purchased items from database via Inertia to keep database in sync!
            selectedIds.forEach((id) => {
                router.delete(`/cart/${id}`, {
                    preserveScroll: true,
                    preserveState: true
                });
            });
            
            selectedIds = [];
            cardNumber = '';
            cardExpiry = '';
            cardCvv = '';
        }, 2000);
    }

    // Variant Selection Dropdown States
    let activeVariantDropdownId = $state<number | null>(null);

    function selectVariant(item: any, variantId: number) {
        if (item.product_variant_id === variantId) return;
        router.patch(`/cart/${item.id}`, {
            product_variant_id: variantId
        }, {
            preserveScroll: true
        });
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
</script>

<svelte:head>
    <title>Keranjang Belanja 🛒 - {storeName || 'Bizmate Premium Store'}</title>
</svelte:head>

<StorefrontLayout hideMobileHeader={true} hideMobileFooter={true}>
    
    <!-- ═══════════════════════════════════════════════════
     STICKY MOBILE HEADER (Shopee Style - exact copy)
    ═══════════════════════════════════════════════════ -->
    <div class="md:hidden fixed top-0 left-0 right-0 z-40 bg-white border-b border-slate-100 flex items-center justify-between px-3 py-3 shadow-3xs">
        <div class="flex items-center gap-3">
            <button
                onclick={() => history.back()}
                class="w-8 h-8 flex items-center justify-center text-slate-700 hover:bg-slate-50 rounded-full active:scale-95 transition cursor-pointer border-0 bg-transparent"
                aria-label="Kembali"
            >
                <i class="ti ti-arrow-left text-xl" style="color: {primary};"></i>
            </button>
            <span class="font-bold text-base text-slate-800">Keranjang Saya ({cartItems.length})</span>
        </div>
        
        <div class="flex items-center gap-3.5">
            <button 
                onclick={deleteAll}
                disabled={cartItems.length === 0}
                class="text-xs font-bold text-rose-600 hover:text-rose-700 disabled:opacity-50 border-0 bg-transparent cursor-pointer"
            >
                Hapus Semua
            </button>
            <button class="relative w-8 h-8 flex items-center justify-center hover:bg-slate-50 rounded-full transition border-0 bg-transparent cursor-pointer" aria-label="Chat">
                <i class="ti ti-message-dots text-xl" style="color: {primary};"></i>
                <span class="absolute -top-1 -right-1.5 text-white text-[8px] font-black w-4.5 h-4.5 rounded-full flex items-center justify-center border border-white shadow-3xs" style="background-color: {primary};">
                    22
                </span>
            </button>
        </div>
    </div>
    
    <!-- Mobile Spacer -->
    <div class="md:hidden h-[53px]"></div>

    <!-- ═══════════════════════════════════════════════════
     MAIN WRAPPER
    ═══════════════════════════════════════════════════ -->
    <div class="max-w-6xl mx-auto px-0 md:px-6 lg:px-8 pt-0 md:pt-4 pb-28 md:py-8 flex-grow bg-white md:bg-slate-50/50 w-full min-h-screen md:min-h-0">
        
        <!-- Desktop Page Title -->
        <h1 class="hidden md:flex font-outfit font-black text-2xl sm:text-3xl text-slate-800 items-center gap-2.5 mb-8">
            <i class="ti ti-shopping-cart text-3xl" style="color: {primary};"></i>
            Keranjang Belanja
        </h1>

        {#if cartItems.length === 0}
            <!-- Empty Cart State -->
            <div
                class="flex flex-col items-center justify-center py-20 px-6 text-center w-full"
                transition:fade={{ duration: 200 }}
            >
                <div class="w-24 h-24 bg-slate-50 rounded-full flex items-center justify-center mb-6 text-slate-300">
                    <i class="ti ti-shopping-cart-x text-5xl"></i>
                </div>
                
                <h3 class="text-[#0a1d37] font-black text-xl sm:text-2xl mb-2 tracking-tight">Keranjang Belanja Kosong</h3>
                
                <p class="text-slate-400 text-xs sm:text-sm max-w-sm mx-auto leading-relaxed mb-8">
                    Sepertinya Anda belum menambahkan produk apapun ke keranjang. Ayo cari barang favoritmu sekarang!
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
                    <div class="hidden md:flex items-center justify-between bg-white border border-slate-200 rounded-3xl px-5 py-4 shadow-3xs">
                        <button
                            onclick={toggleSelectAll}
                            class="flex items-center gap-3 cursor-pointer border-0 bg-transparent p-0 text-left"
                            aria-label="Pilih Semua"
                        >
                            <div
                                class="w-5 h-5 rounded-md border flex items-center justify-center shrink-0 transition-all duration-200 border-slate-300"
                                class:border-transparent={isAllSelected}
                                style={isAllSelected ? `background-color: ${primary}; border-color: ${primary};` : ''}
                            >
                                {#if isAllSelected}
                                    <i class="ti ti-check text-white text-[10px] font-black"></i>
                                {/if}
                            </div>
                            <span class="text-xs sm:text-sm font-black text-slate-700 select-none">
                                Pilih Semua ({selectedIds.length}/{cartItems.length} produk)
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
                    <div class="bg-white rounded-none md:rounded-2xl border-t border-b-0 md:border border-slate-100 md:border-slate-200 shadow-none md:shadow-3xs pt-3 pb-0 md:p-3.5 space-y-3.5">
                        <!-- Green free shipping banner directly under header -->
                        <div class="bg-emerald-50/40 border md:border border-emerald-100/35 rounded-lg py-1.5 px-2.5 flex items-center justify-between text-[10px] sm:text-xs font-bold text-emerald-700 mx-3 md:mx-0">
                            <span class="flex items-center gap-1.5">
                                <i class="ti ti-crown text-emerald-500 text-sm"></i>
                                Kamu telah menikmati <span class="text-emerald-600 font-extrabold underline">Gratis Ongkir!</span>
                            </span>
                            <i class="ti ti-chevron-right text-[9px] text-emerald-400"></i>
                        </div>
                        <!-- Product items inside the store block -->
                        <div class="space-y-4 pt-1 px-3 md:px-0">
                            {#each cartItems as item, idx (item.id)}
                                {@const img = getProductImage(item)}
                                {@const varLabel = getVariantLabel(item)}
                                {@const isChecked = selectedIds.includes(item.id)}
                                {@const isItemFlashSale = item.product_variant
                                    ? (item.product_variant.is_promo && item.product_variant.promo_type === 'flash_sale')
                                    : (item.product.is_promo && item.product.promo_type === 'flash_sale')}
                                {@const originalPrice = item.product_variant
                                    ? (item.product_variant.original_price ?? (item.product_variant.product_price?.price ?? 0))
                                    : (item.product.original_price ?? (item.product.product_price?.price ?? 0))}
                                {@const hasDiscount = (item.product_variant
                                    ? (item.product_variant.is_promo && item.product_variant.discount_percentage > 0)
                                    : (item.product.is_promo && item.product.discount_percentage > 0)) || isItemFlashSale}
                                
                                <div class="flex gap-2.5 relative pt-4 border-t border-slate-100/60 first:pt-0 first:border-t-0">
                                    <!-- Checkbox -->
                                    <div class="flex items-center shrink-0">
                                        <button
                                            onclick={() => toggleItem(item.id)}
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
                                                    <i class="ti ti-check text-white text-[10px] font-black"></i>
                                                {/if}
                                            </div>
                                        </button>
                                    </div>
 
                                    <!-- Product Image (Compact) -->
                                    <div class="w-18 h-18 rounded-lg overflow-hidden bg-slate-50 border border-slate-150 shrink-0 relative select-none">
                                        <img
                                            src={img}
                                            alt={item.product.name}
                                            class="w-full h-full object-cover"
                                            onerror={(e) => {
                                                e.currentTarget.src = '/noimage/image.png';
                                            }}
                                        />
                                        {#if isItemFlashSale}
                                            <!-- Flash Sale Badge (Lightning bolt) -->
                                            <span class="absolute top-0.5 left-0.5 text-white text-[7px] font-black px-1 py-0.25 rounded-xs shadow-xs flex items-center gap-0.5 bg-rose-600">
                                                <i class="ti ti-bolt text-[8px] text-amber-300 animate-pulse"></i>
                                                FS
                                            </span>
                                        {:else if hasDiscount}
                                            {@const pct = item.product_variant ? (item.product_variant.discount_percentage ?? 0) : (item.product.discount_percentage ?? 0)}
                                            {#if pct > 0}
                                                <span class="absolute top-0.5 left-0.5 text-white text-[7px] font-black px-1.5 py-0.25 rounded shadow-xs" style="background-color: {primary};">
                                                    -{pct}%
                                                </span>
                                            {/if}
                                        {/if}
                                    </div>

                                    <!-- Product Details Column -->
                                    <div class="flex-grow flex flex-col justify-between min-w-0">
                                        <div class="space-y-1">
                                            <h3 class="text-xs font-bold text-slate-800 line-clamp-1 leading-snug">
                                                {item.product.name}
                                            </h3>

                                            <!-- Variant dropdown & Stepper aligned in one row as in screenshot -->
                                            <div class="flex items-center justify-between gap-3">
                                                {#if item.product.variants && item.product.variants.length > 0}
                                                    <div class="relative shrink-0">
                                                        <button 
                                                            onclick={(e) => {
                                                                e.stopPropagation();
                                                                activeVariantDropdownId = activeVariantDropdownId === item.id ? null : item.id;
                                                            }}
                                                            class="inline-flex items-center gap-1 bg-slate-50 text-slate-605 text-[9.5px] px-1.5 py-0.5 rounded border border-slate-150 transition hover:bg-slate-100 cursor-pointer shrink-0"
                                                        >
                                                            <span>{varLabel || 'Standard'}</span>
                                                            <i class="ti ti-chevron-down text-[8px]"></i>
                                                        </button>

                                                        {#if activeVariantDropdownId === item.id}
                                                            <div class="absolute left-0 mt-1 w-48 bg-white border border-slate-200 rounded-xl shadow-lg z-50 py-1.5 text-xs text-slate-700 animate-in fade-in slide-in-from-top-1 duration-100">
                                                                {#each item.product.variants as variant}
                                                                    {@const variantName = variant.options.map((o) => o.name).join(' / ')}
                                                                    {@const isSelected = item.product_variant_id === variant.id}
                                                                    <button
                                                                        onclick={() => {
                                                                            selectVariant(item, variant.id);
                                                                            activeVariantDropdownId = null;
                                                                        }}
                                                                        class="w-full text-left px-3 py-2 hover:bg-slate-50 transition flex items-center justify-between font-medium cursor-pointer border-0 bg-transparent"
                                                                        style={isSelected ? `color: ${primary}; font-weight: 800;` : ''}
                                                                    >
                                                                        <span>{variantName}</span>
                                                                        {#if isSelected}
                                                                            <i class="ti ti-check text-[10px]" style="color: {primary};"></i>
                                                                        {/if}
                                                                    </button>
                                                                {/each}
                                                            </div>
                                                        {/if}
                                                    </div>
                                                {:else}
                                                    <div></div>
                                                {/if}

                                                <!-- Stepper exactly on the right -->
                                                <div class="flex items-center border border-slate-200 rounded overflow-hidden shadow-3xs h-6 text-[10px] bg-slate-100 shrink-0">
                                                    <button
                                                        onclick={() => updateQty(item, item.quantity - 1)}
                                                        disabled={item.quantity <= 1}
                                                        class="w-5.5 h-full flex items-center justify-center hover:bg-slate-200 transition text-slate-550 disabled:opacity-30 cursor-pointer border-0 bg-transparent"
                                                    >
                                                        <i class="ti ti-minus text-[8px]"></i>
                                                    </button>
                                                    <input
                                                        type="number"
                                                        min="1"
                                                        max={item.is_unlimited ? undefined : item.stock}
                                                        value={item.quantity}
                                                        onchange={(e) => {
                                                            const val = parseInt((e.target as HTMLInputElement).value);
                                                            if (!isNaN(val) && val >= 1) {
                                                                updateQty(item, val);
                                                            } else {
                                                                (e.target as HTMLInputElement).value = String(item.quantity);
                                                            }
                                                        }}
                                                        class="w-8 h-full text-center font-bold text-slate-700 bg-white border-x border-slate-200 tabular-nums text-[10px] [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none focus:outline-none"
                                                    />
                                                    <button
                                                        onclick={() => updateQty(item, item.quantity + 1)}
                                                        disabled={!item.is_unlimited && item.quantity >= item.stock}
                                                        class="w-5.5 h-full flex items-center justify-center hover:bg-slate-200 transition text-slate-555 disabled:opacity-30 cursor-pointer border-0 bg-transparent"
                                                    >
                                                        <i class="ti ti-plus text-[8px]"></i>
                                                    </button>
                                                </div>
                                            </div>

                                            <!-- Shopee-style Badges -->
                                            <div class="flex gap-1.5 flex-wrap items-center">
                                                {#if isItemFlashSale}
                                                    <span class="text-[7.5px] font-black px-1.5 py-0.25 bg-rose-50 text-rose-600 border border-rose-200 rounded flex items-center gap-0.5 shrink-0">
                                                        <i class="ti ti-bolt text-[9px] text-rose-500 animate-pulse"></i>
                                                        Flash Sale
                                                    </span>
                                                {/if}
                                                <span class="text-[7.5px] font-black px-1.5 py-0.25 bg-[#26b99a]/10 text-[#26b99a] rounded">Gratis Ongkir XTRA</span>
                                            </div>
                                        </div>

                                        <!-- Price & Voucher note Row -->
                                        <div class="flex items-center justify-between gap-2 mt-1.5">
                                            <div class="flex items-center gap-1.5">
                                                {#if isItemFlashSale && originalPrice > item.unit_price}
                                                    <span class="text-[9.5px] text-slate-400 line-through tabular-nums leading-none shrink-0">
                                                        {fmt(originalPrice)}
                                                    </span>
                                                {/if}
                                                <span class="text-xs sm:text-sm font-black" style="color: {secondary};">
                                                    {fmt(item.unit_price)}
                                                </span>
                                                <!-- "Dengan Voucher" note exactly as in screenshot -->
                                                <span class="text-[8.5px] font-bold flex items-center gap-0.5 shrink-0" style="color: {primary};">
                                                    <i class="ti ti-ticket text-[10px]"></i>
                                                    Dengan Voucher
                                                </span>
                                            </div>

                                            <!-- Mini Trash Button -->
                                            <button 
                                                onclick={() => deleteItem(item)}
                                                class="text-slate-350 hover:text-rose-500 transition p-0.5 shrink-0 cursor-pointer border-0 bg-transparent"
                                                aria-label="Hapus Produk"
                                            >
                                                <i class="ti ti-trash text-sm"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            {/each}
                        </div>

                        <!-- Card Footer Voucher Banner as in screenshot -->
                        <div class="pt-3 border-t border-slate-100/80 flex items-center justify-between text-[10.5px] font-bold cursor-pointer hover:opacity-85 transition px-3 md:px-0">
                            <span class="flex items-center gap-2" style="color: {primary};">
                                <i class="ti ti-ticket text-xs"></i>
                                Diskon Rp431 dipakai, berakhir 21:55
                            </span>
                            <div class="flex items-center gap-1">
                                <span class="bg-rose-500 text-white text-[8px] font-bold px-1.5 py-0.25 rounded-sm">Baru</span>
                                <i class="ti ti-chevron-right text-[9px] text-slate-450"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── RIGHT COLUMN (Voucher and Summary Sidebar cards - Image 1 Style) ── -->
                <div class="hidden lg:block space-y-4">
                    
                    <!-- VOUCHER & PROMO Card (Image 1 Style) -->
                    <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-3xs space-y-3">
                        <div class="flex items-center gap-2">
                            <i class="ti ti-ticket text-xl" style="color: {primary};"></i>
                            <h3 class="font-outfit font-black text-xs uppercase tracking-wider text-slate-400">Voucher & Promo</h3>
                        </div>
                        <!-- Inner Coupon Button (Image 1 Style) -->
                        <button 
                            style="color: {primary}; border-color: {withOpacity(primary, 0.2)}; background-color: {withOpacity(primary, 0.05)};"
                            class="w-full flex items-center justify-between px-3 py-2.5 border rounded-xl text-left text-xs font-bold transition group cursor-pointer"
                        >
                            <span class="flex items-center gap-2">
                                <i class="ti ti-gift text-sm"></i>
                                {selectedIds.length > 0 ? 'Pilih promo yang tersedia' : 'Pilih produk sebelum pakai promo'}
                            </span>
                            <i class="ti ti-chevron-right text-xs transition group-hover:translate-x-0.5"></i>
                        </button>
                    </div>

                    <!-- Summary details card (Image 1 Style) -->
                    <div class="bg-white border border-slate-200 rounded-3xl p-5 shadow-3xs space-y-4">
                        <h2 class="font-outfit font-black text-xs text-slate-400 uppercase tracking-wider pb-2 border-b border-slate-100">
                            Ringkasan Belanja
                        </h2>

                        <!-- Pricing Breakdown -->
                        <div class="space-y-2.5 text-xs text-slate-600 font-medium">
                            <div class="flex items-center justify-between">
                                <span>Total Harga ({selectedTotalQuantity} barang)</span>
                                <span class="font-bold text-slate-800">{fmt(selectedCartSubtotal)}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span>Total Diskon</span>
                                <span class="font-bold text-emerald-600">-Rp0</span>
                            </div>
                            
                            <hr class="border-slate-100 my-2" />

                            <div class="flex items-center justify-between text-slate-800">
                                <span class="text-sm font-extrabold">Total Belanja</span>
                                <span class="text-base font-black" style="color: {secondary};">{fmt(selectedCartSubtotal)}</span>
                            </div>
                        </div>

                        <!-- Checkout Button (Image 1 Style) -->
                        <button
                            onclick={startCheckout}
                            disabled={selectedItems.length === 0}
                            style="background-color: {primary}; border-radius: 12px;"
                            class="w-full py-3.5 font-bold text-sm text-white flex items-center justify-center gap-2 shadow-md hover:shadow-lg disabled:opacity-50 disabled:pointer-events-none active:scale-[0.98] transition duration-200 cursor-pointer border-0"
                        >
                            <i class="ti ti-credit-card text-lg"></i>
                            Checkout ({selectedIds.length})
                        </button>
                        
                        <p class="text-center text-[10px] text-slate-400 font-bold">Didukung oleh Gateway Pembayaran Aman</p>
                    </div>

                    <!-- Jaminan Transaksi Aman Card (Image 1 Style) -->
                    <div class="bg-slate-50 border border-slate-200 rounded-3xl p-4 flex gap-3 text-[10px] text-slate-500 font-bold leading-normal">
                        <i class="ti ti-shield-check text-green-500 text-lg shrink-0 mt-0.5"></i>
                        <p>
                            Jaminan Transaksi Aman. Pembayaran akan diverifikasi otomatis oleh gerbang pembayaran aman kami.
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
        <div class="md:hidden fixed bottom-0 left-0 right-0 z-40 bg-white border-t border-slate-150 pb-safe shadow-[0_-8px_24px_rgba(0,0,0,0.06)]">
            
            <!-- Row 1: Voucher Select (Image 2 style) -->
            <button class="w-full bg-white border-b border-slate-100 py-2.5 px-3 flex items-center justify-between text-left transition hover:bg-slate-50 cursor-pointer border-0">
                <span class="flex items-center gap-2 text-[10.5px] font-black text-slate-700">
                    <i class="ti ti-ticket text-base" style="color: {primary};"></i>
                    Voucher
                </span>
                <span class="text-[10px] text-slate-400 flex items-center gap-1 font-bold">
                    Gunakan/masukkan kode
                    <i class="ti ti-chevron-right text-[10px]"></i>
                </span>
            </button>



            <!-- Row 3: Checkbox, Total, Button -->
            <div class="flex items-center justify-between px-3 py-2 gap-3 bg-white">
                <!-- Checkbox "Semua" -->
                <button
                    onclick={toggleSelectAll}
                    class="flex items-center gap-1.5 shrink-0 cursor-pointer border-0 bg-transparent p-0 text-left animate-none"
                    aria-label="Pilih Semua Mobile"
                >
                    <div
                        class="w-5 h-5 rounded-md border flex items-center justify-center shrink-0 transition-all duration-200 border-slate-300"
                        class:border-transparent={isAllSelected}
                        style={isAllSelected ? `background-color: ${primary}; border-color: ${primary};` : ''}
                    >
                        {#if isAllSelected}
                            <i class="ti ti-check text-white text-[10px] font-black"></i>
                        {/if}
                    </div>
                    <span class="text-[10.5px] font-black text-slate-600 select-none">Semua</span>
                </button>

                <!-- Total Payment display -->
                <div class="flex flex-col text-right">
                    <span class="text-[9.5px] text-slate-400 font-bold leading-none mb-0.5">Total Harga</span>
                    <span class="text-sm font-black" style="color: {secondary};">
                        {fmt(selectedCartSubtotal)}
                    </span>
                </div>

                <!-- Checkout Action button -->
                <button
                    onclick={startCheckout}
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
        <div class="fixed inset-0 z-[9999] flex items-center justify-center p-4" transition:fade={{ duration: 150 }}>
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs" onclick={() => !checkoutLoading && (paymentModalOpen = false)}></div>

            <!-- Modal Box -->
            <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden z-10 animate-in zoom-in-95 duration-200 flex flex-col max-h-[90vh]">
                <!-- Header -->
                <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50 shrink-0">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-xl bg-blue-600 text-white flex items-center justify-center text-sm shadow-sm font-black">
                            <i class="ti ti-shield-lock"></i>
                        </div>
                        <div>
                            <h3 class="text-xs font-black text-slate-800 tracking-tight uppercase">Secure Payment</h3>
                            <p class="text-[9px] text-slate-400 font-bold">TRANSAKSI AMAN DI DEKRIPSI</p>
                        </div>
                    </div>
                    <button 
                        disabled={checkoutLoading}
                        onclick={() => paymentModalOpen = false} 
                        class="text-slate-400 hover:text-slate-600 transition disabled:opacity-30 cursor-pointer border-0 bg-transparent"
                    >
                        <i class="ti ti-x text-lg"></i>
                    </button>
                </div>

                <!-- Body -->
                <div class="p-6 overflow-y-auto space-y-5 flex-grow">
                    <!-- Amount Section -->
                    <div style="background-color: {withOpacity(primary, 0.05)}; border-color: {withOpacity(primary, 0.15)};" class="border rounded-2xl p-4 flex items-center justify-between">
                        <div>
                            <span class="text-[9px] text-slate-400 font-bold block uppercase leading-none mb-1">Total Pembayaran</span>
                            <span class="text-lg font-black text-slate-800">{fmt(selectedCartSubtotal)}</span>
                        </div>
                        <span style="background-color: {withOpacity(primary, 0.1)}; color: {primary};" class="px-2.5 py-1 font-bold text-[9px] rounded-full">{selectedTotalQuantity} Barang</span>
                    </div>

                    <div class="space-y-2">
                        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Pilih Metode Pembayaran</span>
                        
                        <!-- List of Methods -->
                        <div class="space-y-2">
                            <!-- QRIS -->
                            <button 
                                onclick={() => selectedMethod = 'qris'}
                                class="w-full flex items-center justify-between p-3 border rounded-2xl transition text-left cursor-pointer bg-white"
                                style={selectedMethod === 'qris' 
                                    ? `border-color: ${primary}; background-color: ${withOpacity(primary, 0.05)};` 
                                    : 'border-color: #e2e8f0;'}
                            >
                                <span class="flex items-center gap-2 text-xs font-black text-slate-700">
                                    <i class="ti ti-qrcode text-lg text-rose-500"></i>
                                    QRIS (GoPay, OVO, ShopeePay)
                                </span>
                                <div class="w-4 h-4 rounded-full border flex items-center justify-center transition-all" 
                                     style={selectedMethod === 'qris' ? `border-color: ${primary};` : 'border-color: #cbd5e1;'}>
                                    {#if selectedMethod === 'qris'}
                                        <div class="w-2.5 h-2.5 rounded-full" style="background-color: {primary};"></div>
                                    {/if}
                                </div>
                            </button>

                            <!-- BCA VA -->
                            <button 
                                onclick={() => selectedMethod = 'bca'}
                                class="w-full flex items-center justify-between p-3 border rounded-2xl transition text-left cursor-pointer bg-white"
                                style={selectedMethod === 'bca' 
                                    ? `border-color: ${primary}; background-color: ${withOpacity(primary, 0.05)};` 
                                    : 'border-color: #e2e8f0;'}
                            >
                                <span class="flex items-center gap-2 text-xs font-black text-slate-700">
                                    <i class="ti ti-building-bank text-lg text-blue-600"></i>
                                    BCA Virtual Account
                                </span>
                                <div class="w-4 h-4 rounded-full border flex items-center justify-center transition-all" 
                                     style={selectedMethod === 'bca' ? `border-color: ${primary};` : 'border-color: #cbd5e1;'}>
                                    {#if selectedMethod === 'bca'}
                                        <div class="w-2.5 h-2.5 rounded-full" style="background-color: {primary};"></div>
                                    {/if}
                                </div>
                            </button>

                            <!-- Mandiri VA -->
                            <button 
                                onclick={() => selectedMethod = 'mandiri'}
                                class="w-full flex items-center justify-between p-3 border rounded-2xl transition text-left cursor-pointer bg-white"
                                style={selectedMethod === 'mandiri' 
                                    ? `border-color: ${primary}; background-color: ${withOpacity(primary, 0.05)};` 
                                    : 'border-color: #e2e8f0;'}
                            >
                                <span class="flex items-center gap-2 text-xs font-black text-slate-700">
                                    <i class="ti ti-building-bank text-lg text-orange-600"></i>
                                    Mandiri Virtual Account
                                </span>
                                <div class="w-4 h-4 rounded-full border flex items-center justify-center transition-all" 
                                     style={selectedMethod === 'mandiri' ? `border-color: ${primary};` : 'border-color: #cbd5e1;'}>
                                    {#if selectedMethod === 'mandiri'}
                                        <div class="w-2.5 h-2.5 rounded-full" style="background-color: {primary};"></div>
                                    {/if}
                                </div>
                            </button>

                            <!-- CC -->
                            <button 
                                onclick={() => selectedMethod = 'cc'}
                                class="w-full flex items-center justify-between p-3 border rounded-2xl transition text-left cursor-pointer bg-white"
                                style={selectedMethod === 'cc' 
                                    ? `border-color: ${primary}; background-color: ${withOpacity(primary, 0.05)};` 
                                    : 'border-color: #e2e8f0;'}
                            >
                                <span class="flex items-center gap-2 text-xs font-black text-slate-700">
                                    <i class="ti ti-credit-card text-lg text-purple-600"></i>
                                    Kartu Kredit / Debit
                                </span>
                                <div class="w-4 h-4 rounded-full border flex items-center justify-center transition-all" 
                                     style={selectedMethod === 'cc' ? `border-color: ${primary};` : 'border-color: #cbd5e1;'}>
                                    {#if selectedMethod === 'cc'}
                                        <div class="w-2.5 h-2.5 rounded-full" style="background-color: {primary};"></div>
                                    {/if}
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- Selected Method Expandable Details -->
                    <div class="border-t border-slate-100 pt-4">
                        {#if selectedMethod === 'qris'}
                            <div class="flex flex-col items-center justify-center bg-slate-50 border border-slate-200 rounded-2xl p-4 text-center space-y-2">
                                <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Pindai QRIS untuk Bayar</span>
                                <!-- QR Mockup -->
                                <div class="w-36 h-36 bg-white border border-slate-200 rounded-xl p-2 flex items-center justify-center relative">
                                    <div class="grid grid-cols-5 grid-rows-5 gap-1.5 w-full h-full opacity-80">
                                        {#each Array(25) as _, i}
                                            <div class="rounded-xs" class:bg-slate-800={(i % 2 === 0 && i !== 12) || i === 0 || i === 4 || i === 20 || i === 24} class:bg-slate-200={i % 2 !== 0 || i === 12}></div>
                                        {/each}
                                    </div>
                                    <div class="absolute inset-0 flex items-center justify-center">
                                        <div class="w-9 h-9 bg-white border border-slate-100 rounded-lg flex items-center justify-center shadow-xs text-[10px] font-black text-rose-600 tracking-tighter">QRIS</div>
                                    </div>
                                </div>
                                <p class="text-[9.5px] text-slate-500 font-bold leading-normal">Mendukung GoPay, OVO, ShopeePay, Dana, LinkAja & Mobile Banking</p>
                            </div>
                        {:else if selectedMethod === 'bca' || selectedMethod === 'mandiri'}
                            <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 space-y-2.5">
                                <div class="flex items-center justify-between">
                                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Nomor Virtual Account</span>
                                    <span class="px-2 py-0.5 font-black text-[8px] rounded uppercase" style="background-color: {withOpacity(primary, 0.1)}; color: {primary};">{selectedMethod} VA</span>
                                </div>
                                <div class="flex items-center justify-between bg-white border border-slate-200 rounded-xl px-3 py-2">
                                    <span class="text-sm font-black text-slate-700 font-mono tracking-wider font-semibold">
                                        {selectedMethod === 'bca' ? '8801209384728374' : '8901239847293847'}
                                    </span>
                                    <button 
                                        type="button"
                                        onclick={() => alert('Nomor VA disalin!')} 
                                        class="text-[10px] font-black cursor-pointer border-0 bg-transparent transition hover:opacity-80"
                                        style="color: {primary};"
                                    >
                                        SALIN
                                    </button>
                                </div>
                                <p class="text-[9px] text-slate-500 font-medium leading-relaxed">Silakan lakukan transfer dari mobile banking atau ATM terdekat ke nomor Virtual Account di atas sebelum batas waktu habis.</p>
                            </div>
                        {:else if selectedMethod === 'cc'}
                            <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 space-y-3">
                                <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Informasi Kartu Kredit / Debit</span>
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
                            <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
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
        <div class="fixed inset-0 z-[10000] flex items-center justify-center p-4" transition:fade={{ duration: 150 }}>
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs"></div>

            <!-- Modal Box -->
            <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-sm overflow-hidden z-10 animate-in zoom-in-95 duration-200 p-6 flex flex-col items-center text-center space-y-6">
                <!-- Checkmark Animation Mockup -->
                <div class="w-16 h-16 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center text-3xl shadow-inner border border-emerald-200">
                    <i class="ti ti-circle-check animate-bounce"></i>
                </div>

                <!-- Text Info -->
                <div class="space-y-1.5 w-full">
                    <h3 class="text-lg font-black text-slate-800 tracking-tight">Pembayaran Berhasil!</h3>
                    <p class="text-xs text-slate-400 leading-relaxed px-4">Terima kasih atas pembayaran Anda. Transaksi Anda telah berhasil diproses oleh gerbang pembayaran kami.</p>
                </div>

                <!-- Transaction Details Card -->
                <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 w-full text-left space-y-2 text-[11px] font-bold text-slate-600">
                    <div class="flex items-center justify-between">
                        <span>ID Transaksi</span>
                        <span class="text-slate-800 font-mono tracking-wider">{checkoutSuccessData.transactionId}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>Jumlah Barang</span>
                        <span class="text-slate-800">{checkoutSuccessData.selectedCount} pcs</span>
                    </div>
                    <hr class="border-slate-200 my-1" />
                    <div class="flex items-center justify-between text-slate-800">
                        <span class="font-extrabold text-xs">Total Pembayaran</span>
                        <span class="font-black text-sm" style="color: {secondary};">{fmt(checkoutSuccessData.totalAmount)}</span>
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
        <div class="fixed inset-0 z-[10005] flex items-center justify-center p-4" transition:fade={{ duration: 150 }}>
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
                        Apakah Anda yakin ingin menghapus semua produk (<strong>{cartItems.length}</strong> item) dari keranjang belanja Anda?
                    {:else if deleteType === 'selected'}
                        Apakah Anda yakin ingin menghapus <strong>{selectedIds.length}</strong> produk terpilih dari keranjang belanja Anda?
                    {:else}
                        Produk <strong>{itemToDelete?.product?.name || itemToDelete?.product_variant?.product?.name}</strong> akan terhapus dari keranjang belanja.
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
</StorefrontLayout>

<style>
    /* Styling for mobile safe area margins at the bottom */
    .pb-safe {
        padding-bottom: max(0px, env(safe-area-inset-bottom));
    }
</style>
