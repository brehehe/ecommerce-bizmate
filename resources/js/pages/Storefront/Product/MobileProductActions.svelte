<script lang="ts">
    let {
        showFoodModifierModal = $bindable(),
        drawerAction = $bindable(),
        drawerOpen = $bindable(),
        isFoodPick,
        showCheckoutSettings,
        isEffectivelyOutOfStock,
        isPromoStockExhausted,
        hasVariations,
        primary,
        openChat,
        openWhatsApp,
        addToCart,
        product,
    }: any = $props();

    const isRestoClosed = $derived(
        Boolean(
            isFoodPick &&
            (product?.seller?.resto_profile?.is_open_now === false ||
             product?.seller?.restoProfile?.is_open_now === false)
        )
    );
</script>

<!-- Sticky Bottom Bar (Mobile Only) -->
<div
    class="fixed bottom-0 left-0 right-0 z-50 bg-white border-t border-slate-100 px-4 pt-2.5 pb-2.5 flex items-center gap-3 md:hidden shadow-[0_-4px_16px_rgba(0,0,0,0.06)]"
    style="padding-bottom: calc(0.625rem + env(safe-area-inset-bottom, 0px));"
>
    {#if !isFoodPick}
        <!-- Chat Button -->
        <button
            onclick={openChat}
            class="w-11 h-11 flex items-center justify-center rounded-xl border border-slate-200 text-slate-700 active:bg-slate-50 transition shrink-0 cursor-pointer"
            aria-label="Chat Penjual"
            title="Chat Penjual"
        >
            <i class="ti ti-message-dots text-xl"></i>
        </button>
        <!-- WhatsApp Button -->
        <button
            onclick={openWhatsApp}
            class="w-11 h-11 flex items-center justify-center rounded-xl border border-emerald-600 bg-emerald-600 text-white hover:bg-emerald-700 active:bg-emerald-800 transition shrink-0 cursor-pointer shadow-xs active:scale-[0.95]"
            aria-label="Tanya via WhatsApp"
            title="Tanya via WhatsApp"
        >
            <i class="ti ti-brand-whatsapp text-xl text-white"></i>
        </button>
    {/if}

    <!-- Beli Langsung & Keranjang (Controlled by SHOW_CHECKOUT_SETTINGS) -->
    {#if showCheckoutSettings}
        {#if isRestoClosed}
            <div
                class="flex-1 py-3 px-2 rounded-xl font-bold text-xs sm:text-sm text-center bg-slate-100 text-slate-500 border border-slate-200 flex items-center justify-center gap-1.5"
            >
                <i class="ti ti-lock text-sm text-slate-400"></i>
                <span>Resto Sedang Tutup</span>
            </div>
        {:else if isEffectivelyOutOfStock}
            <div
                class="flex-1 py-3 px-1 rounded-xl font-bold text-xs sm:text-sm text-center bg-slate-100 text-slate-400 border border-slate-200"
            >
                {isPromoStockExhausted ? 'Stok Promo Habis' : 'Stok Habis'}
            </div>
        {:else}
            <button
                onclick={() => {
                    if (isFoodPick) {
                        showFoodModifierModal = true;
                    } else {
                        drawerAction = 'buy';
                        drawerOpen = true;
                    }
                }}
                class="flex-1 py-3 px-1 rounded-xl font-bold text-xs sm:text-sm border-2 transition active:scale-[0.98] text-center cursor-pointer"
                style="border-color: {primary}; color: {primary};"
            >
                {isFoodPick ? 'Kustomisasi Menu' : 'Beli Langsung'}
            </button>

            <!-- + Keranjang (Solid style) -->
            <button
                onclick={() => {
                    if (isFoodPick) {
                        showFoodModifierModal = true;
                    } else if (hasVariations) {
                        drawerAction = 'cart';
                        drawerOpen = true;
                    } else {
                        addToCart();
                    }
                }}
                class="flex-1 py-3 px-1 rounded-xl font-bold text-xs sm:text-sm text-white transition active:scale-[0.98] text-center shadow-md cursor-pointer"
                style="background-color: {primary};"
            >
                {isFoodPick ? '+ Nampan' : '+ Keranjang'}
            </button>
        {/if}
    {/if}
</div>
