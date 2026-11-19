<script lang="ts">
    let {
        primary,
        subtotal,
        discountAmount,
        coinDiscountAmount,
        isDigitalOnly,
        selectedShipping,
        shippingFee,
        shippingDiscount,
        adminFee,
        applicationFee,
        activeAdditionalCosts,
        grandTotal,
        isCheckoutLocked,
        checkoutLockedMessage,
        isStoreOpen,
        isSubmitting,
        isCheckoutDisabled,
        fmt,
        submitCheckout,
    }: {
        primary: string;
        subtotal: number;
        discountAmount: number;
        coinDiscountAmount: number;
        isDigitalOnly: boolean;
        selectedShipping: any;
        shippingFee: number;
        shippingDiscount: number;
        adminFee: number;
        applicationFee: number;
        activeAdditionalCosts: any[];
        grandTotal: number;
        isCheckoutLocked: boolean;
        checkoutLockedMessage: string;
        isStoreOpen: boolean;
        isSubmitting: boolean;
        isCheckoutDisabled: boolean;
        fmt: (value: number) => string;
        submitCheckout: () => void;
    } = $props();
</script>

<div
    class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden lg:sticky lg:top-20"
>
    <div class="px-4 pt-4 pb-4">
        <div class="flex items-center gap-2 mb-3">
            <i class="ti ti-receipt text-lg" style="color:{primary}"></i>
            <span class="font-bold text-slate-800 text-sm"
                >Ringkasan Pesanan</span
            >
        </div>
        <div class="space-y-2 text-sm">
            <div class="flex justify-between">
                <span class="text-slate-600">Subtotal Produk</span>
                <span class="font-semibold text-slate-800">{fmt(subtotal)}</span
                >
            </div>
            {#if discountAmount > 0}
                <div class="flex justify-between text-green-600">
                    <span>Diskon Voucher</span>
                    <span class="font-semibold">-{fmt(discountAmount)}</span>
                </div>
            {/if}
            {#if coinDiscountAmount > 0}
                <div class="flex justify-between text-emerald-600">
                    <span>Potongan Poin Saya</span>
                    <span class="font-semibold">-{fmt(coinDiscountAmount)}</span
                    >
                </div>
            {/if}
            {#if !isDigitalOnly}
                <div class="flex justify-between">
                    <div class="flex items-center gap-1 group relative">
                        <span class="text-slate-600">Ongkos Kirim</span>
                        <i
                            class="ti ti-info-circle text-[10px] text-slate-400 cursor-help"
                        ></i>
                        <div
                            class="absolute bottom-full left-0 mb-1 hidden group-hover:block z-50 w-48 bg-slate-850 text-white text-[10px] rounded-lg p-2 shadow-lg leading-relaxed font-normal normal-case"
                        >
                            Biaya pengiriman barang berdasarkan lokasi dan kurir
                            pilihan Anda.
                        </div>
                    </div>
                    <span class="font-semibold text-slate-800"
                        >{selectedShipping ? fmt(shippingFee) : '-'}</span
                    >
                </div>
                {#if shippingDiscount > 0}
                    <div class="flex justify-between text-green-600">
                        <span>Gratis Ongkir</span>
                        <span class="font-semibold"
                            >-{fmt(shippingDiscount)}</span
                        >
                    </div>
                {/if}
            {/if}
            {#if adminFee > 0}
                <div class="flex justify-between">
                    <div class="flex items-center gap-1 group relative">
                        <span class="text-slate-600">Biaya Admin</span>
                        <i
                            class="ti ti-info-circle text-[10px] text-slate-400 cursor-help"
                        ></i>
                        <div
                            class="absolute bottom-full left-0 mb-1 hidden group-hover:block z-50 w-48 bg-slate-850 text-white text-[10px] rounded-lg p-2 shadow-lg leading-relaxed font-normal normal-case"
                        >
                            Biaya administrasi metode pembayaran yang Anda
                            pilih.
                        </div>
                    </div>
                    <span class="font-semibold text-slate-800"
                        >{fmt(adminFee)}</span
                    >
                </div>
            {/if}
            {#if applicationFee > 0}
                <div class="flex justify-between">
                    <div class="flex items-center gap-1 group relative">
                        <span class="text-slate-600">Biaya Aplikasi</span>
                        <i
                            class="ti ti-info-circle text-[10px] text-slate-400 cursor-help"
                        ></i>
                        <div
                            class="absolute bottom-full left-0 mb-1 hidden group-hover:block z-50 w-48 bg-slate-850 text-white text-[10px] rounded-lg p-2 shadow-lg leading-relaxed font-normal normal-case"
                        >
                            Biaya layanan platform untuk pemeliharaan sistem.
                        </div>
                    </div>
                    <span class="font-semibold text-slate-800"
                        >{fmt(applicationFee)}</span
                    >
                </div>
            {/if}
            {#each activeAdditionalCosts as cost (cost.id)}
                {#if Number(cost.value) > 0}
                    <div class="flex justify-between">
                        <div class="flex items-center gap-1 group relative">
                            <span class="text-slate-600">{cost.name}</span>
                            <i
                                class="ti ti-info-circle text-[10px] text-slate-400 cursor-help"
                            ></i>
                            <div
                                class="absolute bottom-full left-0 mb-1 hidden group-hover:block z-50 w-48 bg-slate-850 text-white text-[10px] rounded-lg p-2 shadow-lg leading-relaxed font-normal normal-case"
                            >
                                Biaya operasional tambahan: {cost.name}.
                            </div>
                        </div>
                        <span class="font-semibold text-slate-800"
                            >{fmt(Number(cost.value))}</span
                        >
                    </div>
                {/if}
            {/each}
            <div class="border-t border-slate-100 pt-2 flex justify-between">
                <span class="font-bold text-slate-800">Total</span>
                <span class="font-black text-lg" style="color:{primary}"
                    >{fmt(grandTotal)}</span
                >
            </div>
        </div>

        {#if isCheckoutLocked}
            <div
                class="bg-red-50/80 border border-red-200/60 rounded-xl p-3 flex gap-3 items-start mt-4 mb-2 text-left"
            >
                <div
                    class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center shrink-0 mt-0.5"
                >
                    <i class="ti ti-lock text-red-600 text-lg"></i>
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
                    <i class="ti ti-clock-pause text-amber-600 text-lg"></i>
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
                        Mohon maaf, proses pesanan dinonaktifkan sementara.
                    </p>
                </div>
            </div>
        {/if}
        <button
            onclick={submitCheckout}
            disabled={isCheckoutDisabled}
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
