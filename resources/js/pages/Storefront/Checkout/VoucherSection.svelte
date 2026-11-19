<script lang="ts">
    let {
        primary,
        visibleVouchers,
        activeAppliedVouchers,
        shippingDiscount,
        discountAmount,
        voucherError,
        fmt,
        withOpacity,
        onOpen,
        deselectVoucher,
    }: {
        primary: string;
        visibleVouchers: any[];
        activeAppliedVouchers: any[];
        shippingDiscount: number;
        discountAmount: number;
        voucherError: string;
        fmt: (value: number) => string;
        withOpacity: (color: string, opacity: number) => string;
        onOpen: () => void;
        deselectVoucher: (voucher: any) => void;
    } = $props();
</script>

<div
    class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden p-5 space-y-3"
>
    <div class="flex items-center gap-2">
        <i class="ti ti-ticket text-xl" style="color: {primary};"></i>
        <h3
            class="font-outfit font-black text-xs uppercase tracking-wider text-slate-400"
        >
            Voucher & Promo
        </h3>
    </div>

    <button
        onclick={onOpen}
        style="color: {primary}; border-color: {primary}30; background-color: {primary}08;"
        class="w-full flex items-center justify-between px-3 py-2.5 border rounded-xl text-left text-xs font-bold transition group cursor-pointer"
    >
        <span class="flex items-center gap-2">
            <i class="ti ti-gift text-sm"></i>
            {visibleVouchers.length > 0
                ? 'Pilih promo yang tersedia'
                : 'Tidak ada promo tersedia'}
        </span>
        <i
            class="ti ti-chevron-right text-xs transition group-hover:translate-x-0.5"
        ></i>
    </button>

    {#if activeAppliedVouchers.length > 0}
        <div class="space-y-1.5 pt-1.5 border-t border-slate-50">
            <p
                class="text-[9px] font-black text-slate-400 uppercase tracking-widest px-0.5"
            >
                Promo yang digunakan:
            </p>
            <div class="flex flex-col gap-1.5">
                {#each activeAppliedVouchers as voucher (voucher.id)}
                    {@const isShipping =
                        voucher.type === 'voucher_gratis_ongkir'}
                    <div
                        class="flex items-center justify-between px-2.5 py-1.5 rounded-xl border text-[10px] font-bold uppercase tracking-wider transition-all"
                        style="background: {withOpacity(
                            primary,
                            0.06,
                        )}; border-color: {withOpacity(primary, 0.25)};"
                    >
                        <div class="flex items-center gap-1.5 min-w-0">
                            <i
                                class="ti {isShipping
                                    ? 'ti-truck'
                                    : 'ti-ticket'} text-xs shrink-0"
                                style="color: {primary};"
                            ></i>
                            <span
                                class="shrink-0 font-black"
                                style="color: {primary};"
                            >
                                {voucher.code}
                            </span>
                            <span
                                class="text-[8.5px] text-slate-600 normal-case font-bold truncate"
                            >
                                • {voucher.name} (
                                {#if isShipping}
                                    S/d {fmt(voucher.discount_value)}
                                    {#if shippingDiscount > 0}
                                        - Hemat {fmt(shippingDiscount)}
                                    {/if}
                                {:else}
                                    {voucher.discount_type === 'percentage'
                                        ? Number(voucher.discount_value) + '%'
                                        : fmt(voucher.discount_value)}
                                    {#if discountAmount > 0}
                                        - Hemat {fmt(discountAmount)}
                                    {/if}
                                {/if}
                                )
                            </span>
                        </div>
                        <button
                            onclick={() => deselectVoucher(voucher)}
                            class="text-slate-400 hover:text-slate-655 transition p-0.5 border-0 bg-transparent cursor-pointer flex items-center justify-center shrink-0 rounded-full hover:bg-slate-200/40 w-4 h-4 ml-1"
                            title="Lepas Voucher"
                        >
                            <i class="ti ti-x text-xs"></i>
                        </button>
                    </div>
                {/each}
            </div>
        </div>
    {/if}

    {#if voucherError}
        <p class="text-[10.5px] text-red-500 font-bold mt-1">
            {voucherError}
        </p>
    {/if}
</div>
