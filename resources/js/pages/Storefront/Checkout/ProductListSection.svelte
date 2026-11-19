<script lang="ts">
    import OrderItemModifiers from '@/components/Storefront/OrderItemModifiers.svelte';
    let {
        primary,
        cartItems,
        subtotal,
        eligibleBundlingPromos,
        itemNotes = $bindable(),
        fmt,
        getCartItemImage,
        formatImagePath,
    }: {
        primary: string;
        cartItems: any[];
        subtotal: number;
        eligibleBundlingPromos: any[];
        itemNotes: Record<number, string>;
        fmt: (value: number) => string;
        getCartItemImage: (item: any) => string;
        formatImagePath: (path: string | null | undefined) => string;
    } = $props();
</script>

<div
    class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden"
>
    <div class="px-4 pt-4 pb-2">
        <div class="flex items-center gap-2">
            <i class="ti ti-package text-lg" style="color:{primary}"></i>
            <span class="font-bold text-slate-800 text-sm"
                >Produk Dipesan ({cartItems.length} item)</span
            >
        </div>
    </div>
    <div class="divide-y divide-slate-100">
        {#each cartItems as item, idx (item.id || `${item.product_id}_${item.product_variant_id ?? '0'}_${idx}`)}
            {@const product = item.product}
            {@const variant = item.productVariant ?? item.product_variant}
            {@const imgUrl = getCartItemImage(item)}
            <div class="px-4 py-3.5">
                <div class="flex gap-3">
                    <img
                        src={imgUrl}
                        alt={product?.name}
                        width="56"
                        height="56"
                        class="w-14 h-14 object-cover rounded-lg shrink-0 border border-slate-100"
                        onerror={(e: any) => {
                            e.target.src = '/noimage/image.png';
                        }}
                    />
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-1.5 flex-wrap">
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
                            <p class="text-xs text-slate-500 mt-0.5">
                                {variant.options
                                    ?.map((option: any) => option.name)
                                    .join(' / ')}
                            </p>
                        {/if}

                        <!-- Food Modifiers / Toppings Display -->
                        <OrderItemModifiers {item} compact={true} class="my-1.5" />

                        <div class="flex items-center justify-between mt-1.5">
                            <span class="text-xs text-slate-500"
                                >x{item.quantity}</span
                            >
                            <span
                                class="text-sm font-bold"
                                style="color:{primary}"
                                >{fmt(
                                    item.unit_price ??
                                        item.computed_price ??
                                        item.price ??
                                        item.product?.product_price?.price ??
                                        item.product?.productPrice?.price ??
                                        0,
                                )}</span
                            >
                        </div>
                    </div>
                </div>
                <div class="mt-2" style="padding-left: 4.25rem;">
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
    <div class="px-4 py-3 border-t border-slate-100 flex justify-between">
        <span class="text-sm text-slate-600">Subtotal Produk</span>
        <span class="text-sm font-bold text-slate-800">{fmt(subtotal)}</span>
    </div>

    {#if eligibleBundlingPromos.length > 0}
        <div class="px-4 pb-4 pt-2 border-t border-slate-100">
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
                {#each eligibleBundlingPromos as promo (promo.id)}
                    {@const bundle = promo.settings.bundle}
                    {@const buyNames = bundle.buy_items
                        .map(
                            (buyItem: any) =>
                                buyItem.product_name || 'Produk Syarat',
                        )
                        .join(' + ')}
                    {#each bundle.get_items as gift (gift.product_id ?? gift.id ?? gift.product_name)}
                        <div
                            class="flex gap-3 p-3 rounded-xl bg-gradient-to-br from-emerald-50 to-teal-50 border border-emerald-100"
                        >
                            <img
                                src={formatImagePath(gift.product_image)}
                                alt={gift.product_name}
                                width="56"
                                height="56"
                                class="w-14 h-14 rounded-lg object-cover border border-slate-100 shrink-0"
                                onerror={(e: any) => {
                                    e.target.src = '/noimage/image.png';
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
                                <p class="text-xs text-slate-500 mt-0.5">
                                    Bundling: {buyNames}
                                </p>
                                <div class="flex items-center gap-2 mt-1">
                                    <span
                                        class="text-base line-through text-red-600 font-bold"
                                        >{fmt(gift.product_price ?? 0)}</span
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
