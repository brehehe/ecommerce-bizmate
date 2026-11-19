<script>
    /* eslint-disable svelte/require-each-key */
    let {
        isFlashSale,
        primary,
        secondary,
        flashSaleCountdown,
        activeUnitPrice,
        fmt,
        product,
        originalPrice,
        currentPrice,
        discountPercentage,
        activeTierPrices,
        activeTier,
        matchingVariant,
        isPromoActive,
        shouldKeepTierPricesDuringPromo,
        hasWholesalePrices,
        activePromoRule,
        isPromoStockExhausted,
        qty,
        bundlingPromos,
        getPromoRequirementsText,
    } = $props();
</script>

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
                <span class="text-yellow-300 text-base drop-shadow-sm">⚡</span>
                Flash Sale
            </div>
            <div class="flex items-center gap-2">
                <i class="ti ti-clock text-white/90 text-xs"></i>
                <span
                    class="text-[10px] font-bold text-white uppercase tracking-wider"
                    >Berakhir Dalam</span
                >
                <div class="flex items-center gap-1">
                    <span
                        class="bg-white font-mono font-black text-xs leading-none px-2 py-1 rounded shadow-sm tabular-nums"
                        style="color: {primary};">{flashSaleCountdown.h}</span
                    >
                    <span class="text-white/90 font-black text-xs">:</span>
                    <span
                        class="bg-white font-mono font-black text-xs leading-none px-2 py-1 rounded shadow-sm tabular-nums"
                        style="color: {primary};">{flashSaleCountdown.m}</span
                    >
                    <span class="text-white/90 font-black text-xs">:</span>
                    <span
                        class="bg-white font-mono font-black text-xs leading-none px-2 py-1 rounded shadow-sm tabular-nums"
                        style="color: {primary};">{flashSaleCountdown.s}</span
                    >
                </div>
            </div>
        </div>
        <!-- Price row inside banner -->
        <div class="px-4 py-3 flex items-baseline gap-3 flex-wrap">
            <span
                class="text-2xl sm:text-3xl font-bold"
                style="color: {secondary};"
            >
                {fmt(activeUnitPrice)}
            </span>
            <span
                class="text-xs font-black px-2 py-0.5 rounded-md uppercase tracking-wider border {product.price_type ===
                'nego'
                    ? 'bg-amber-50 text-amber-800 border-amber-200'
                    : 'bg-slate-100 text-slate-700 border-slate-200'}"
            >
                {product.price_type === 'nego' ? 'NEGO' : 'NET'}
            </span>
            {#if originalPrice && originalPrice > currentPrice}
                <span class="text-lg text-red-600 line-through font-bold"
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
            <div class="flex items-baseline gap-3 flex-wrap">
                <span
                    class="text-3xl sm:text-4xl font-bold"
                    style="color: {secondary};"
                >
                    {fmt(activeUnitPrice)}
                </span>
                <span
                    class="text-xs font-black px-2 py-0.5 rounded-md uppercase tracking-wider border {product.price_type ===
                    'nego'
                        ? 'bg-amber-50 text-amber-800 border-amber-200'
                        : 'bg-slate-100 text-slate-700 border-slate-200'}"
                >
                    {product.price_type === 'nego' ? 'NEGO' : 'NET'}
                </span>
                {#if originalPrice && originalPrice > currentPrice}
                    <span
                        class="text-lg sm:text-xl text-red-600 line-through font-bold"
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
            <span class="text-2xl font-bold text-slate-700">Hubungi Kami</span>
        {/if}
    </div>
{/if}

<!-- Desktop Wholesale Tier Prices Table -->
{#if activeTierPrices && activeTierPrices.length > 0}
    <div class="hidden md:block w-full my-4">
        <div
            class="flex items-center gap-1.5 mb-2 text-xs font-bold text-slate-700"
        >
            <i class="ti ti-tags text-brand-blueRoyal text-sm"></i>
            <span>Daftar Harga Grosir</span>
        </div>
        <div
            class="w-full overflow-hidden border border-slate-100 rounded-xl bg-white shadow-xs"
        >
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr
                        class="bg-slate-50/65 border-b border-slate-100 text-[10px] text-slate-400 font-bold uppercase tracking-wider"
                    >
                        <th class="py-2.5 px-4 font-semibold">Min. Pembelian</th
                        >
                        <th class="py-2.5 px-4 font-semibold text-right"
                            >Harga Satuan</th
                        >
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    {#each activeTierPrices as tier}
                        {@const isActive =
                            (!hasVariations || fullySelected) &&
                            activeTier &&
                            Number(activeTier.min_qty) === Number(tier.min_qty)}
                        <tr
                            class="transition duration-150 {isActive
                                ? 'bg-brand-blueRoyal/[0.02]'
                                : ''}"
                        >
                            <td class="py-3 px-4 font-semibold text-slate-700">
                                {tier.min_qty}+ pcs
                            </td>
                            <td
                                class="py-3 px-4 font-black text-slate-800 text-right"
                            >
                                {fmt(tier.price)}
                                <span
                                    class="text-[10px] text-slate-400 font-normal"
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

<!-- Desktop Wholesale Promo Clash Alert -->
{#if isPromoActive && !shouldKeepTierPricesDuringPromo && hasWholesalePrices}
    <div
        class="hidden md:flex items-start gap-3 p-4 rounded-2xl border text-xs my-3 shadow-xs"
        style="background-color: #fffaf0; border-color: #fbd38d;"
    >
        <div
            class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center shrink-0"
        >
            <i class="ti ti-info-circle text-amber-600 text-base animate-pulse"
            ></i>
        </div>
        <div class="flex-grow text-[11px] leading-relaxed">
            <span class="font-bold text-amber-950 block text-xs"
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
                >. Selama masa promo berlangsung, potongan harga grosir
                dinonaktifkan untuk memberikan harga terbaik.
            </p>
        </div>
    </div>
{/if}

<!-- Promo Produk Tiered Info Banner (Desktop) -->
{#if activePromoRule && activePromoRule.min_qty > 1 && !isPromoStockExhausted}
    <div
        class="hidden md:flex items-start gap-3 p-4 rounded-2xl border text-xs my-4 shadow-sm transition duration-200"
        style="background-color: #f0f7ff; border-color: #bfdbfe;"
    >
        <div
            class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center shrink-0 animate-bounce"
        >
            <i class="ti ti-gift text-blue-600 text-base"></i>
        </div>
        <div>
            <h4
                class="font-outfit font-black text-xs text-blue-800 uppercase tracking-wider"
            >
                Promo Spesial Beli Banyak Lebih Hemat!
            </h4>
            <p
                class="text-[11px] text-blue-650 font-bold mt-0.5 leading-relaxed"
            >
                Beli minimal <strong>{activePromoRule.min_qty} pcs</strong>
                untuk mendapatkan potongan harga menjadi
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
                <div
                    class="mt-2 text-[10px] text-orange-700 bg-orange-50 border border-orange-200 px-2.5 py-1 rounded-lg inline-block font-extrabold uppercase tracking-wide"
                >
                    Sisa Stok Promo: {activePromoRule.remaining_promo_stock}
                    pcs
                </div>
            {/if}
            {#if qty < activePromoRule.min_qty}
                <div
                    class="mt-2 text-[10px] text-amber-700 bg-amber-50 border border-amber-200 px-2.5 py-1 rounded-lg inline-block font-semibold"
                >
                    Tambah {activePromoRule.min_qty - qty} pcs lagi untuk mengaktifkan
                    promo ini!
                </div>
            {:else}
                <div
                    class="mt-2 text-[10px] text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-1 rounded-lg inline-block font-bold"
                >
                    Selamat! Promo Beli Banyak Aktif 🎉
                </div>
            {/if}
        </div>
    </div>
{/if}

<!-- Promo Bundling & Gift Section (Interactive & Shopee-style) -->
{#if bundlingPromos === undefined}
    <div
        class="my-4 p-4 rounded-3xl bg-slate-50 border border-slate-100 space-y-3.5 shadow-sm animate-pulse"
    >
        <div class="flex items-center gap-2">
            <div class="w-6 h-6 rounded-full bg-slate-200 shrink-0"></div>
            <div class="space-y-1.5 flex-grow">
                <div class="h-3 bg-slate-200 rounded w-16"></div>
                <div class="h-4 bg-slate-200 rounded w-32"></div>
            </div>
        </div>
        <div class="h-3 bg-slate-200 rounded w-2/3"></div>
    </div>
{:else if bundlingPromos && bundlingPromos.length > 0}
    {#each bundlingPromos as promo}
        {@const bundle = promo.settings.bundle}
        <div
            class="my-4 p-4 rounded-3xl bg-blue-50/40 border border-blue-100/70 space-y-3.5 shadow-sm text-xs animate-in fade-in slide-in-from-top-1 duration-200"
        >
            <!-- Title Header -->
            <div class="flex items-center gap-2 font-bold text-slate-800">
                <span
                    class="w-6 h-6 rounded-full bg-brand-blueRoyal text-white flex items-center justify-center text-xs shadow-md animate-pulse shrink-0"
                >
                    <i class="ti ti-gift"></i>
                </span>
                <div>
                    <span
                        class="text-brand-blueRoyal font-extrabold uppercase tracking-wider text-[10px] block leading-none mb-0.5"
                        >Promo Bundling</span
                    >
                    <span class="text-slate-850 font-black text-sm"
                        >{promo.name}</span
                    >
                </div>
            </div>

            <p class="text-slate-600 font-semibold leading-relaxed">
                <strong>{getPromoRequirementsText(promo)}</strong>
            </p>

            <div
                class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 pt-1.5 border-t border-slate-100"
            >
                <!-- Buy Checklist -->
                <div class="space-y-2">
                    <span
                        class="text-[9.5px] text-slate-450 font-black uppercase tracking-wider block"
                        >Produk Yang Harus Dibeli:</span
                    >
                    <div class="space-y-2">
                        {#each bundle.buy_items as buyItem}
                            {@const isCurrent =
                                Number(buyItem.product_id) ===
                                Number(product.id)}
                            <div
                                class="flex items-center gap-2.5 p-2 rounded-2xl bg-white border border-slate-100 shadow-3xs hover:border-slate-200 transition duration-150 relative"
                            >
                                <img
                                    src={buyItem.product_image
                                        ? buyItem.product_image.startsWith(
                                              'http',
                                          ) ||
                                          buyItem.product_image.startsWith('/')
                                            ? buyItem.product_image
                                            : '/' + buyItem.product_image
                                        : '/noimage/image.png'}
                                    alt={buyItem.product_name}
                                    class="w-9 h-9 rounded-lg object-cover bg-slate-50 border border-slate-100 shrink-0"
                                    onerror={(e) => {
                                        e.currentTarget.src =
                                            '/noimage/image.png';
                                    }}
                                />
                                <div class="min-w-0 flex-grow pr-1">
                                    {#if isCurrent}
                                        <p
                                            class="font-black text-brand-blueRoyal truncate text-[10.5px] flex items-center gap-1"
                                        >
                                            {buyItem.product_name ||
                                                'Produk Ini'}
                                            <span
                                                class="text-[8px] bg-brand-blueLight text-brand-blueRoyal px-1.5 py-0.25 rounded-md uppercase font-black tracking-wider"
                                                >Produk Ini</span
                                            >
                                        </p>
                                    {:else}
                                        <a
                                            href={`/products/${buyItem.product_id}`}
                                            class="font-bold text-slate-800 hover:text-brand-blueRoyal hover:underline truncate text-[10.5px] block"
                                        >
                                            {buyItem.product_name ||
                                                'Produk Lain'}
                                        </a>
                                    {/if}
                                    <p
                                        class="text-[9px] font-bold text-slate-400"
                                    >
                                        Min. Beli: {buyItem.qty}
                                        pcs
                                    </p>
                                </div>
                            </div>
                        {/each}
                    </div>
                </div>

                <!-- Get Gifts List (Image 3 Style) -->
                <div class="space-y-2">
                    <span
                        class="text-[9.5px] text-slate-450 font-black uppercase tracking-wider block"
                        >Hadiah Bonus Yang Didapat:</span
                    >
                    <div class="space-y-2">
                        {#each bundle.get_items as gift}
                            <div
                                class="flex gap-2.5 p-2 rounded-2xl bg-white border border-slate-100 shadow-3xs relative"
                            >
                                <img
                                    src={gift.product_image
                                        ? gift.product_image.startsWith(
                                              'http',
                                          ) ||
                                          gift.product_image.startsWith('/')
                                            ? gift.product_image
                                            : '/' + gift.product_image
                                        : '/noimage/image.png'}
                                    alt={gift.product_name}
                                    class="w-12 h-12 rounded-lg object-cover bg-slate-50 border border-slate-100 shrink-0"
                                    onerror={(e) => {
                                        e.currentTarget.src =
                                            '/noimage/image.png';
                                    }}
                                />
                                <div
                                    class="min-w-0 flex-grow flex flex-col justify-center"
                                >
                                    <div>
                                        <span
                                            class="bg-emerald-500 text-white text-[8px] font-black px-1.5 py-0.5 rounded-md uppercase tracking-wider mb-0.5 inline-block"
                                        >
                                            Hadiah
                                        </span>
                                    </div>
                                    <p
                                        class="font-extrabold text-slate-800 truncate text-[10.5px] leading-tight mb-0.5"
                                    >
                                        {gift.product_name || 'Hadiah Bonus'}
                                    </p>
                                    <p
                                        class="text-[9px] font-bold text-slate-400 mb-1 leading-none"
                                    >
                                        Isi {gift.qty} pcs
                                    </p>
                                    <div
                                        class="flex items-center flex-wrap gap-1.5 text-[9px] leading-none"
                                    >
                                        {#if gift.discount_type === 'free'}
                                            <span
                                                class="bg-red-50 text-red-500 text-[8px] font-black px-1 py-0.25 rounded border border-red-100 leading-none"
                                            >
                                                100%
                                            </span>
                                            <span
                                                class="text-xs text-red-600 line-through font-bold font-mono"
                                            >
                                                {fmt(gift.product_price)}
                                            </span>
                                            <span
                                                class="text-red-500 font-black tracking-wide uppercase"
                                            >
                                                GRATIS
                                            </span>
                                        {:else if gift.discount_type === 'percentage'}
                                            <span
                                                class="bg-orange-50 text-orange-600 text-[8px] font-black px-1 py-0.25 rounded border border-orange-100 leading-none"
                                            >
                                                {Number(gift.discount_value)}%
                                            </span>
                                            <span
                                                class="text-xs text-red-600 line-through font-bold font-mono"
                                            >
                                                {fmt(gift.product_price)}
                                            </span>
                                            <span
                                                class="text-orange-600 font-black tracking-wide"
                                            >
                                                {fmt(
                                                    gift.product_price *
                                                        (1 -
                                                            gift.discount_value /
                                                                100),
                                                )}
                                            </span>
                                        {:else}
                                            <span
                                                class="bg-orange-50 text-orange-600 text-[8px] font-black px-1 py-0.25 rounded border border-orange-100 leading-none"
                                            >
                                                POTONGAN
                                            </span>
                                            <span
                                                class="text-xs text-red-600 line-through font-bold font-mono"
                                            >
                                                {fmt(gift.product_price)}
                                            </span>
                                            <span
                                                class="text-orange-600 font-black tracking-wide font-mono"
                                            >
                                                {fmt(
                                                    gift.product_price -
                                                        gift.discount_value,
                                                )}
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
