<script>
    /* eslint-disable svelte/require-each-key */
    let {
        hasVariations,
        product,
        getSelectedLabel,
        primary,
        getOptionImage,
        isOptionAvailable,
        isSelected,
        selectOption,
        secondary,
        withOpacity,
        showCheckoutSettings,
        decQty,
        qty,
        currentMinPurchase,
        currentIsUnlimited,
        currentStock,
        handleQtyInput,
        handleQtyBlur,
        incQty,
        fullySelected,
        isInStock,
        fmt,
        totalPrice,
        isSplitPricing,
        promoStockRemaining,
    } = $props();
</script>

<!-- Desktop-only Variations & Quantity (inline) -->
<div class="hidden md:block divide-y divide-slate-100">
    <!-- ── VARIATIONS ──────────────────────────── -->
    {#if hasVariations}
        {#each product.variations as variation}
            {@const selLabel = getSelectedLabel(variation)}
            <div class="py-4 flex flex-col gap-3">
                <!-- Label row -->
                <div class="flex items-center gap-2">
                    <span class="text-sm font-bold text-slate-700">
                        Pilih {variation.name}:
                    </span>
                    {#if selLabel}
                        <span
                            class="text-sm font-bold"
                            style="color: {primary};">{selLabel}</span
                        >
                    {/if}
                </div>

                <!-- Option buttons -->
                <div class="flex flex-wrap gap-2">
                    {#each variation.options as opt}
                        {@const optImg = getOptionImage(opt.id, variation.name)}
                        {@const available = isOptionAvailable(opt.id)}
                        {@const sel = isSelected(String(variation.id), opt.id)}
                        <button
                            onclick={() =>
                                available &&
                                selectOption(String(variation.id), opt.id)}
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

    <!-- ── STOK & KUANTITAS ────────────────────────────── -->
    <div class="py-4 flex items-center gap-5">
        <span class="text-sm text-slate-500 w-28 shrink-0 font-medium"
            >{showCheckoutSettings ? 'Kuantitas' : 'Stok Produk'}</span
        >
        <div class="flex items-center gap-3 flex-wrap">
            <!-- Stepper (Controlled by SHOW_CHECKOUT_SETTINGS) -->
            {#if showCheckoutSettings}
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
                        max={!currentIsUnlimited ? currentStock : undefined}
                        class="w-12 text-center text-sm font-black text-slate-800 tabular-nums border-none outline-none focus:ring-0 focus:outline-none p-0 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                        oninput={handleQtyInput}
                        onblur={handleQtyBlur}
                    />
                    <button
                        aria-label="Increase quantity"
                        onclick={incQty}
                        disabled={!currentIsUnlimited && qty >= currentStock}
                        class="w-9 h-9 flex items-center justify-center hover:bg-slate-100 transition text-slate-600 disabled:opacity-30"
                    >
                        <i class="ti ti-plus text-sm"></i>
                    </button>
                </div>
            {/if}

            <!-- Stock label (Always shown) -->
            {#if hasVariations && !fullySelected}
                <span class="text-xs text-slate-400"
                    >Pilih variasi terlebih dahulu</span
                >
            {:else if currentIsUnlimited}
                <span class="text-xs font-bold text-green-600 tracking-wide"
                    >TERSEDIA</span
                >
            {:else if isInStock}
                <span class="text-xs font-bold text-green-600"
                    >Stok: {currentStock}</span
                >
            {:else}
                <span class="text-xs font-bold text-red-500">HABIS</span>
            {/if}

            {#if showCheckoutSettings && currentMinPurchase > 1}
                <span class="text-xs text-slate-400 font-medium"
                    >(Min. Pembelian {currentMinPurchase} pcs)</span
                >
            {/if}
        </div>
    </div>

    <!-- Desktop Live Calculation & Min Purchase Alert -->
    {#if !hasVariations || fullySelected}
        <div
            class="py-2.5 flex items-center gap-5 border-t border-slate-100 mt-2"
        >
            <span class="text-sm text-slate-500 w-28 shrink-0 font-medium"
                >Subtotal</span
            >
            <div class="flex items-center gap-3 flex-wrap text-xs">
                <span class="font-bold text-slate-700 tabular-nums">
                    {fmt(totalPrice)}
                </span>
            </div>
        </div>
    {/if}

    {#if currentMinPurchase > 1}
        <div class="py-2.5 flex items-center gap-5 border-t border-slate-100">
            <span class="text-sm text-slate-500 w-28 shrink-0 font-medium"
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
        <i class="ti ti-info-circle text-amber-500 text-sm mt-0.5 shrink-0"></i>
        <span>
            <strong>{promoStockRemaining} pcs</strong>
            dengan harga promo,
            <strong>{qty - promoStockRemaining} pcs</strong>
            dengan harga normal. Sisa kuota promo: {promoStockRemaining}
            pcs.
        </span>
    </div>
{/if}
