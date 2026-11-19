<script lang="ts">
    let {
        promoCountdowns,
        promoExpiredNames,
        soonestPromoCountdown,
        isPromoExpiringSoon,
    }: {
        promoCountdowns: Array<{
            name: string;
            remaining: number;
            label: string;
        }>;
        promoExpiredNames: string[];
        soonestPromoCountdown: {
            name: string;
            remaining: number;
            label: string;
        } | null;
        isPromoExpiringSoon: boolean;
    } = $props();
</script>

{#if promoCountdowns.length > 0}
    <div
        class="rounded-2xl px-4 py-3 flex items-center gap-3 border {isPromoExpiringSoon
            ? 'bg-red-50 border-red-200'
            : 'bg-amber-50 border-amber-200'}"
    >
        <div
            class="shrink-0 w-9 h-9 rounded-full flex items-center justify-center {isPromoExpiringSoon
                ? 'bg-red-100'
                : 'bg-amber-100'}"
        >
            <i
                class="ti ti-clock-exclamation text-lg {isPromoExpiringSoon
                    ? 'text-red-500'
                    : 'text-amber-500'}"
            ></i>
        </div>
        <div class="flex-1 min-w-0">
            <p
                class="text-xs font-bold {isPromoExpiringSoon
                    ? 'text-red-700'
                    : 'text-amber-700'}"
            >
                {soonestPromoCountdown?.name} berakhir dalam
                <span class="font-black tabular-nums"
                    >{soonestPromoCountdown?.label}</span
                >
            </p>
            <p
                class="text-[11px] {isPromoExpiringSoon
                    ? 'text-red-500'
                    : 'text-amber-500'} mt-0.5"
            >
                Segera selesaikan checkout sebelum harga kembali normal.
            </p>
        </div>
    </div>
{/if}

{#if promoExpiredNames.length > 0}
    <div
        class="rounded-2xl px-4 py-3 flex items-center gap-3 bg-slate-50 border border-slate-200"
    >
        <div
            class="shrink-0 w-9 h-9 rounded-full flex items-center justify-center bg-slate-100"
        >
            <i class="ti ti-refresh text-lg text-slate-500 animate-spin"></i>
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-xs font-bold text-slate-700">Memperbarui harga...</p>
            <p class="text-[11px] text-slate-500 mt-0.5">
                {promoExpiredNames.join(', ')} telah berakhir. Harga sedang diperbarui
                ke harga normal.
            </p>
        </div>
    </div>
{/if}
