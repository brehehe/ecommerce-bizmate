<script lang="ts">
    let {
        useCoins = $bindable(),
        coinsEnabled,
        isGuest,
        userCoinsBalance,
        userCoinsValue,
        isCoinsMinPurchaseMet,
        maxCoinsRedeemAllowed,
        coinMinPurchaseRedeem,
        subtotal,
        coinDiscountAmount,
        coinConversionRate,
        coinMaxRedeemPercentage,
        coinMaxRedeemPerTxn,
        prospectiveCoinsEarned,
        appliedVoucher,
        formatNumber,
    }: any = $props();
</script>

<!-- Poin Saya Loyalty Section -->
{#if coinsEnabled && !isGuest}
    <div
        class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden p-5 space-y-4"
    >
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div
                    class="w-8 h-8 rounded-xl bg-amber-50 flex items-center justify-center text-amber-500 shrink-0"
                >
                    <i class="ti ti-coins text-xl"></i>
                </div>
                <div>
                    <h3
                        class="font-outfit font-black text-xs uppercase tracking-wider text-slate-400"
                    >
                        Poin Toko
                    </h3>
                    <p class="text-[11px] font-bold text-slate-700 mt-0.5">
                        {formatNumber(userCoinsBalance)} Poin
                        <span class="text-slate-400 font-normal"
                            >(Senilai Rp {formatNumber(userCoinsValue)})</span
                        >
                    </p>
                </div>
            </div>

            {#if isCoinsMinPurchaseMet && userCoinsBalance > 0 && maxCoinsRedeemAllowed > 0}
                <!-- Premium Custom Toggle -->
                <label
                    class="relative inline-flex items-center cursor-pointer select-none"
                >
                    <input
                        type="checkbox"
                        bind:checked={useCoins}
                        class="sr-only peer"
                    />
                    <div
                        class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"
                    ></div>
                </label>
            {/if}
        </div>

        {#if !isCoinsMinPurchaseMet}
            <!-- Warning alert: min purchase not met -->
            <div
                class="bg-amber-50/70 border border-amber-100 rounded-2xl p-3 flex gap-2.5 items-start"
            >
                <i
                    class="ti ti-info-circle text-amber-500 text-sm mt-0.5 shrink-0"
                ></i>
                <div class="min-w-0">
                    <p
                        class="text-[10px] font-bold text-amber-800 leading-normal"
                    >
                        Poin Belum Dapat Digunakan
                    </p>
                    <p class="text-[9px] text-amber-600 mt-0.5 leading-normal">
                        Minimal belanja untuk menggunakan Poin adalah <span
                            class="font-black"
                            >Rp {formatNumber(coinMinPurchaseRedeem)}</span
                        >
                        (Subtotal Anda: Rp {formatNumber(subtotal)}).
                    </p>
                </div>
            </div>
        {:else if userCoinsBalance <= 0}
            <div
                class="bg-slate-50 border border-slate-100 rounded-2xl p-3 flex gap-2.5 items-start"
            >
                <i
                    class="ti ti-info-circle text-slate-400 text-sm mt-0.5 shrink-0"
                ></i>
                <div class="min-w-0">
                    <p
                        class="text-[10px] font-bold text-slate-500 leading-normal"
                    >
                        Saldo Poin Kosong
                    </p>
                    <p class="text-[9px] text-slate-400 mt-0.5 leading-normal">
                        Belanja sekarang untuk mengumpulkan Poin!
                    </p>
                </div>
            </div>
        {:else if maxCoinsRedeemAllowed <= 0}
            <div
                class="bg-slate-50 border border-slate-100 rounded-2xl p-3 flex gap-2.5 items-start"
            >
                <i
                    class="ti ti-info-circle text-slate-400 text-sm mt-0.5 shrink-0"
                ></i>
                <div class="min-w-0">
                    <p
                        class="text-[10px] font-bold text-slate-500 leading-normal"
                    >
                        Poin Tidak Bisa Digunakan
                    </p>
                    <p class="text-[9px] text-slate-400 mt-0.5 leading-normal">
                        Batas maksimum diskon voucher / promo telah melampaui
                        aturan penggunaan Poin.
                    </p>
                </div>
            </div>
        {:else}
            <!-- Main Info Block -->
            <div
                class="bg-slate-50 rounded-2xl p-3.5 space-y-2 border border-slate-100/50"
            >
                <div
                    class="flex items-center justify-between text-[10px] font-bold text-slate-600"
                >
                    <span>Gunakan Poin:</span>
                    <span
                        class={useCoins
                            ? 'text-emerald-600 font-black'
                            : 'text-slate-500 font-black'}
                    >
                        {useCoins
                            ? `${formatNumber(maxCoinsRedeemAllowed)} Poin`
                            : 'Tidak digunakan'}
                    </span>
                </div>
                <div
                    class="flex items-center justify-between text-[10px] font-bold text-slate-600"
                >
                    <span>Potongan Belanja:</span>
                    <span
                        class={useCoins
                            ? 'text-emerald-600 font-black'
                            : 'text-slate-500 font-black'}
                    >
                        {useCoins
                            ? `-Rp ${formatNumber(coinDiscountAmount)}`
                            : 'Rp 0'}
                    </span>
                </div>

                <!-- Rules details small print -->
                <div
                    class="pt-2 border-t border-slate-100 text-[8.5px] text-slate-400 leading-normal space-y-0.5 font-sans"
                >
                    <p>
                        • Nilai Tukar: 1 Poin = Rp {formatNumber(
                            coinConversionRate,
                        )}
                    </p>
                    {#if coinMaxRedeemPercentage < 100}
                        <p>
                            • Maksimal diskon Poin: {coinMaxRedeemPercentage}%
                            dari subtotal belanja (Rp {formatNumber(
                                (subtotal * coinMaxRedeemPercentage) / 100,
                            )})
                        </p>
                    {/if}
                    {#if coinMaxRedeemPerTxn < 5000000}
                        <p>
                            • Batas maksimal penggunaan Poin per transaksi: Rp {formatNumber(
                                coinMaxRedeemPerTxn,
                            )}
                        </p>
                    {/if}
                </div>
            </div>
        {/if}

        {#if prospectiveCoinsEarned > 0}
            <!-- Earn info badge -->
            <div
                class="bg-emerald-50/60 border border-emerald-100/50 rounded-2xl p-3 flex gap-2.5 items-center"
            >
                <div
                    class="w-6 h-6 rounded-lg bg-emerald-500 flex items-center justify-center text-white shrink-0"
                >
                    <i class="ti ti-gift text-xs"></i>
                </div>
                <div class="min-w-0">
                    <p class="text-[9px] text-emerald-800 leading-tight">
                        {#if appliedVoucher && appliedVoucher.is_points_voucher}
                            Dapatkan <span class="font-black text-emerald-600"
                                >{formatNumber(prospectiveCoinsEarned)} Poin</span
                            > dari Voucher Belanja setelah pesanan Anda selesai!
                        {:else}
                            Dapatkan <span class="font-black text-emerald-600"
                                >{formatNumber(prospectiveCoinsEarned)} Poin</span
                            > setelah pesanan Anda selesai!
                        {/if}
                    </p>
                </div>
            </div>
        {:else if useCoins && coinDiscountAmount > 0}
            <!-- Info badge: no coins earned when using coins -->
            <div
                class="bg-amber-50/60 border border-amber-100/50 rounded-2xl p-3 flex gap-2.5 items-center"
            >
                <div
                    class="w-6 h-6 rounded-lg bg-amber-500 flex items-center justify-center text-white shrink-0"
                >
                    <i class="ti ti-info-circle text-xs"></i>
                </div>
                <div class="min-w-0">
                    <p class="text-[9px] text-amber-800 leading-tight">
                        Anda menggunakan Poin pada transaksi ini, sehingga <span
                            class="font-black text-amber-600"
                            >tidak akan mendapatkan Poin tambahan</span
                        >.
                    </p>
                </div>
            </div>
        {/if}
    </div>
{/if}
