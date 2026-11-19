<script>
    let {
        isFoodPick,
        productModifiers,
        formatPrice,
        showFoodModifierModal = $bindable(),
        showCheckoutSettings,
        isEffectivelyOutOfStock,
        isPromoStockExhausted,
        hasVariations,
        fullySelected,
        showToast,
        addToCart,
        primary,
        secondary,
        withOpacity,
        buyNow,
        product,
        isSellerEnabled,
    } = $props();

    const isRestoClosed = $derived(
        Boolean(
            isFoodPick &&
            (product?.seller?.resto_profile?.is_open_now === false ||
             product?.seller?.restoProfile?.is_open_now === false)
        )
    );
</script>

{#if isFoodPick}
    <section class="mt-5 border-t border-slate-200 pt-5">
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <i
                        class="ti ti-tools-kitchen-2 text-base text-slate-500"
                        aria-hidden="true"
                    ></i>
                    <h2 class="text-sm font-semibold text-slate-900">
                        Topping & kustomisasi
                    </h2>
                </div>
                <p class="mt-1 text-xs leading-relaxed text-slate-500">
                    Periksa pilihan yang tersedia sebelum menambahkan menu.
                </p>
            </div>
            <span class="shrink-0 pt-0.5 text-xs text-slate-500">
                {productModifiers.length > 0
                    ? `${productModifiers.length} grup`
                    : 'Menu standar'}
            </span>
        </div>

        {#if productModifiers.length > 0}
            <div
                class="mt-4 divide-y divide-slate-100 overflow-hidden rounded-lg border border-slate-200 bg-white"
            >
                {#each productModifiers as mod (mod.id || mod.group_name)}
                    <div class="p-4">
                        <div class="flex items-start justify-between gap-4">
                            <h3
                                class="min-w-0 text-sm font-semibold text-slate-800"
                            >
                                {mod.group_name}
                            </h3>
                            <p
                                class="shrink-0 text-right text-[11px] font-medium {mod.is_required
                                    ? 'text-rose-600'
                                    : 'text-slate-500'}"
                            >
                                {mod.is_required
                                    ? 'Wajib'
                                    : 'Opsional'}{mod.is_multiple
                                    ? `, maks. ${mod.max_selectable || 5}`
                                    : ', pilih satu'}
                            </p>
                        </div>

                        <div
                            class="mt-3 grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-2"
                        >
                            {#each mod.items || [] as item (item.id || item.name)}
                                <div
                                    class="flex min-w-0 items-baseline justify-between gap-3 border-b border-slate-100 pb-2 text-xs last:border-b-0 sm:[&:nth-last-child(-n+2)]:border-b-0"
                                >
                                    <span
                                        class="min-w-0 truncate text-slate-700"
                                    >
                                        {item.name}
                                    </span>
                                    {#if Number(item.additional_price) > 0}
                                        <span
                                            class="foodpick-price shrink-0 font-semibold text-orange-700"
                                        >
                                            +{formatPrice(
                                                item.additional_price,
                                            )}
                                        </span>
                                    {/if}
                                </div>
                            {/each}
                        </div>
                    </div>
                {/each}
            </div>
        {:else}
            <p
                class="mt-4 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs leading-relaxed text-slate-600"
            >
                Menu ini disajikan standar tanpa topping tambahan. Anda dapat
                menentukan jumlah dan menuliskan catatan khusus koki saat
                memasukkan ke nampan.
            </p>
        {/if}

        <button
            type="button"
            onclick={() => (showFoodModifierModal = true)}
            class="foodpick-action mt-4 flex min-h-11 w-full cursor-pointer items-center justify-center gap-2 rounded-lg border border-orange-300 bg-white px-4 py-2.5 text-sm font-semibold text-orange-800 transition-colors hover:bg-orange-50 active:bg-orange-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orange-600"
        >
            <i class="ti ti-adjustments-horizontal text-base" aria-hidden="true"
            ></i>
            <span
                >{productModifiers.length > 0
                    ? 'Atur topping'
                    : 'Tambahkan catatan pesanan'}</span
            >
        </button>
    </section>
{/if}

<!-- ── CTA BUTTONS ─────────────────────────── -->
{#if showCheckoutSettings}
    <div class="pt-5 hidden md:flex flex-col sm:flex-row gap-3">
        {#if isRestoClosed}
            <div
                class="flex-grow py-3.5 px-4 rounded-xl font-bold text-sm text-center bg-slate-100 text-slate-500 border border-slate-200 flex items-center justify-center gap-2"
            >
                <i class="ti ti-lock text-base text-slate-400"></i>
                <span>Resto Sedang Tutup Sementara</span>
            </div>
        {:else if isEffectivelyOutOfStock}
            <!-- Out-of-stock state -->
            <div
                class="flex-grow py-3.5 rounded-xl font-bold text-sm text-center bg-slate-100 text-slate-400 border border-slate-200"
            >
                {isPromoStockExhausted ? 'Stok Promo Habis' : 'Stok Habis'}
            </div>
        {:else}
            <button
                onclick={() => {
                    if (hasVariations && !fullySelected) {
                        showToast(
                            'Pilih variasi terlebih dahulu',
                            'error',
                            'top',
                        );

                        return;
                    }

                    addToCart();
                }}
                class="flex-grow flex items-center justify-center gap-2 py-3.5 px-5 rounded-xl font-bold text-sm text-white shadow-sm hover:opacity-95 active:scale-[0.98] transition duration-150 cursor-pointer"
                style="background-color: {primary};"
            >
                <i class="ti ti-shopping-cart text-base"></i>
                {isFoodPick ? '+ Nampan' : '+ Keranjang'}
            </button>
            <button
                onclick={() => buyNow()}
                class="flex-grow flex items-center justify-center gap-2 py-3.5 px-5 rounded-xl font-bold text-sm text-white shadow-sm hover:opacity-95 active:scale-[0.98] transition duration-150 cursor-pointer"
                style="background-color: {secondary};"
            >
                <i class="ti ti-credit-card text-base"></i>
                {isFoodPick ? 'Pesan Sekarang' : 'Beli Sekarang'}
            </button>
        {/if}
    </div>
{/if}

<!-- Product meta footer -->
{#if product.weight || product.brand || (product.brands && product.brands.length > 0) || product.category || product.condition || (isSellerEnabled && product.created_at)}
    <div
        class="{isSellerEnabled
            ? 'pt-2.5'
            : 'pt-4'} flex flex-wrap gap-x-5 gap-y-1 text-[11px] text-slate-400"
    >
        {#if product.category}
            <span
                >Kategori: <b class="text-slate-600">{product.category.name}</b
                ></span
            >
        {/if}
        {#if product.brands && product.brands.length > 0}
            <span
                >Merk: <b class="text-slate-600"
                    >{product.brands.map((b) => b.name).join(', ')}</b
                ></span
            >
        {:else if product.brand && !isFoodPick}
            <span>Merk: <b class="text-slate-600">{product.brand}</b></span>
        {/if}
        {#if !isFoodPick}
            <span
                >Kondisi: <b class="text-slate-600"
                    >{product.condition === 'rent'
                        ? 'Rent'
                        : product.condition === 'used' ||
                            product.condition === 'second'
                          ? 'Second'
                          : 'New'}</b
                ></span
            >
            {#if product.usage_period}
                <span
                    >Pemakaian: <b class="text-slate-700 font-bold"
                        >{product.usage_period}</b
                    ></span
                >
            {/if}
        {/if}
        {#if product.weight}
            <span>Berat: <b class="text-slate-600">{product.weight} g</b></span>
        {/if}
    </div>
{/if}

<style>
    :global(.sf-dark) .foodpick-price {
        color: #fdba74;
    }

    :global(.sf-dark) .foodpick-action {
        border-color: #c2410c !important;
        background-color: #202c3f !important;
        color: #fed7aa !important;
    }

    :global(.sf-dark) .foodpick-action:hover {
        background-color: rgb(194 65 12 / 18%) !important;
    }

    :global(.sf-dark) .foodpick-action:active {
        background-color: rgb(194 65 12 / 28%) !important;
    }

    :global(.sf-dark) .foodpick-action:focus-visible {
        outline-color: #fb923c !important;
    }
</style>
