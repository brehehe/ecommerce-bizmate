<script lang="ts">
    let {
        primary,
        grandTotal,
        isSubmitting,
        isCheckoutDisabled,
        fmt,
        submitCheckout,
    }: {
        primary: string;
        grandTotal: number;
        isSubmitting: boolean;
        isCheckoutDisabled: boolean;
        fmt: (value: number) => string;
        submitCheckout: () => void;
    } = $props();
</script>

<div
    class="fixed bottom-0 left-0 right-0 z-40 lg:hidden bg-white border-t border-slate-200 px-4 py-3 safe-area-bottom"
>
    <div class="flex items-center justify-between gap-4">
        <div>
            <p class="text-xs text-slate-500">Total Pembayaran</p>
            <p class="text-lg font-black" style="color:{primary}">
                {fmt(grandTotal)}
            </p>
        </div>
        <button
            onclick={submitCheckout}
            disabled={isCheckoutDisabled}
            class="flex items-center gap-2 px-6 py-3 rounded-xl font-bold text-white transition disabled:opacity-50 disabled:cursor-not-allowed"
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

<style>
    .safe-area-bottom {
        padding-bottom: max(0.75rem, env(safe-area-inset-bottom));
    }
</style>
