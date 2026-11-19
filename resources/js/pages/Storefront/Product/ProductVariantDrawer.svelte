<script lang="ts">
    /* eslint-disable svelte/require-each-key */
    import { fade } from 'svelte/transition';

    let {
        drawerOpen = $bindable(),
        drawerAction,
        displayImage,
        product,
        primary,
        secondary,
        activeUnitPrice,
        hasVariations,
        fullySelected,
        isPromoActive,
        shouldKeepTierPricesDuringPromo,
        hasWholesalePrices,
        matchingVariant,
        activeTierPrices,
        activeTier,
        showCheckoutSettings,
        currentIsUnlimited,
        currentStock,
        currentMinPurchase,
        qty,
        totalPrice,
        isEffectivelyOutOfStock,
        isPromoStockExhausted,
        fmt,
        getSelectedLabel,
        isOptionAvailable,
        getOptionImage,
        isSelected,
        selectOption,
        withOpacity,
        decQty,
        incQty,
        handleQtyInput,
        handleQtyBlur,
        buyNow,
        addToCart,
    }: any = $props();

    function handleKeydown(event: KeyboardEvent) {
        if (event.key === 'Escape') {
            drawerOpen = false;
        }
    }
</script>

<svelte:window onkeydown={handleKeydown} />

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
                        <span class="text-green-600 font-bold">Stok Ready</span>
                    {/if}
                </div>
            </div>
        </div>

        <!-- Scrollable Content -->
        <div class="flex-grow overflow-y-auto px-5 py-4 flex flex-col gap-4">
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
                                <span class="text-sm font-bold text-slate-700">
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
                        <i class="ti ti-tags text-brand-blueRoyal text-sm"></i>
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

            <!-- Stock Info & Quantity Stepper -->
            <div
                class="py-3 flex flex-col gap-2 border-t border-slate-100 mt-2 shrink-0"
            >
                <div class="flex items-center justify-between">
                    <div class="flex flex-col gap-0.5">
                        <span class="text-sm font-bold text-slate-700"
                            >{showCheckoutSettings
                                ? 'Jumlah'
                                : 'Stok Produk'}</span
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
                                <span class="text-[11px] font-bold text-red-500"
                                    >HABIS</span
                                >
                            {/if}
                            {#if showCheckoutSettings && currentMinPurchase > 1}
                                <span
                                    class="text-[10px] text-slate-400 font-semibold"
                                    >(Min. {currentMinPurchase} pcs)</span
                                >
                            {/if}
                        </div>
                    </div>
                    {#if showCheckoutSettings}
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
                    {/if}
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
                    <span class="font-extrabold text-sm text-brand-blueRoyal"
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

        <!-- Sticky Bottom CTA Button inside Drawer -->
        <div class="p-4 border-t border-slate-100 bg-slate-50 shrink-0">
            {#if isEffectivelyOutOfStock}
                <div
                    class="w-full py-3.5 rounded-xl font-bold text-sm text-center bg-slate-100 text-slate-400 border border-slate-200"
                >
                    {isPromoStockExhausted ? 'Stok Promo Habis' : 'Stok Habis'}
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
