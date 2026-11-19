<script lang="ts">
    import { fade } from 'svelte/transition';

    let {
        voucherModalOpen = $bindable(),
        manualPromoCode = $bindable(),
        primary,
        voucherLoading,
        visibleVouchers,
        shippingVouchers,
        discountVouchers,
        subtotal,
        appliedVoucher,
        voucherInputCode,
        expandedTerms,
        userIsNew,
        applyVoucher,
        openScanModal,
        withOpacity,
        fmt,
        toggleTerms,
        deselectVoucher,
        selectVoucher,
        formatNumber,
    }: any = $props();
</script>

<!-- ═══════════════════════════════════════════════════
     VOUCHER SELECTION MODAL (Premium Ticket Style)
    ═══════════════════════════════════════════════════ -->
{#if voucherModalOpen}
    <div
        class="fixed inset-0 z-50 flex items-end lg:items-center justify-center p-0 lg:p-4"
        transition:fade={{ duration: 150 }}
    >
        <!-- Backdrop -->
        <!-- svelte-ignore a11y_no_static_element_interactions -->
        <!-- svelte-ignore a11y_click_events_have_key_events -->
        <div
            class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs"
            onclick={() => (voucherModalOpen = false)}
        ></div>

        <!-- Modal Box -->
        <div
            class="relative bg-white rounded-t-3xl lg:rounded-3xl shadow-2xl w-full lg:max-w-md overflow-hidden z-10 animate-in slide-in-from-bottom lg:zoom-in-95 duration-200 flex flex-col max-h-[85vh] lg:max-h-[80vh]"
        >
            <!-- Header -->
            <div
                class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50 shrink-0"
            >
                <div class="flex items-center gap-2.5">
                    <div
                        class="w-7 h-7 rounded-lg text-white flex items-center justify-center text-xs shadow-sm font-black"
                        style="background-color: {primary};"
                    >
                        <i class="ti ti-ticket text-sm"></i>
                    </div>
                    <div>
                        <h3
                            class="text-[11px] font-black text-slate-800 tracking-tight uppercase"
                        >
                            Voucher & Promo Toko
                        </h3>
                        <p
                            class="text-[8px] text-slate-400 font-bold uppercase leading-none"
                        >
                            Pilih promo terbaik untuk belanjamu
                        </p>
                    </div>
                </div>
                <button
                    aria-label="Tutup"
                    onclick={() => (voucherModalOpen = false)}
                    class="w-6 h-6 flex items-center justify-center text-slate-400 hover:text-slate-650 transition cursor-pointer border-0 bg-transparent rounded-full hover:bg-slate-100/80"
                >
                    <i class="ti ti-x text-sm"></i>
                </button>
            </div>

            <!-- Body -->
            <div
                class="p-3.5 overflow-y-auto space-y-3 flex-grow bg-slate-50/50"
            >
                <!-- Manual Voucher Code Input Search Block -->
                <div
                    class="bg-white border border-slate-200 rounded-xl p-2 flex gap-2 items-center shadow-3xs mb-1.5"
                >
                    <div class="relative flex-grow">
                        <i
                            class="ti ti-ticket absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"
                        ></i>
                        <input
                            type="text"
                            bind:value={manualPromoCode}
                            placeholder="Masukkan kode voucher..."
                            class="w-full pl-8 pr-9 py-1.5 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-[var(--primary-glow)] focus:border-[var(--primary)] text-[10.5px] font-semibold text-slate-700 h-8 uppercase"
                            style="--primary-glow: {primary}30; --primary: {primary};"
                            onkeydown={(e) => {
                                if (e.key === 'Enter') {
                                    applyVoucher();
                                }
                            }}
                        />
                        <button
                            type="button"
                            onclick={openScanModal}
                            class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700 transition duration-150 border-0 bg-transparent cursor-pointer p-1"
                            title="Scan QR Code Voucher"
                        >
                            <i class="ti ti-scan text-sm"></i>
                        </button>
                    </div>
                    <button
                        onclick={applyVoucher}
                        disabled={voucherLoading || !manualPromoCode.trim()}
                        style="background-color: {primary};"
                        class="px-3.5 py-1.5 hover:opacity-90 text-white text-[10.5px] font-black rounded-lg uppercase tracking-wider transition duration-200 h-8 cursor-pointer border-0 shrink-0 disabled:opacity-50"
                    >
                        {voucherLoading ? '...' : 'Pakai'}
                    </button>
                </div>

                {#if visibleVouchers.length === 0}
                    <div
                        class="flex flex-col items-center justify-center py-10 px-4 text-center"
                    >
                        <div
                            class="w-12 h-12 bg-slate-100 text-slate-400 rounded-full flex items-center justify-center mb-2.5"
                        >
                            <i class="ti ti-ticket-off text-xl"></i>
                        </div>
                        <p class="text-xs font-bold text-slate-500">
                            Tidak Ada Voucher Tersedia
                        </p>
                        <p
                            class="text-[9px] text-slate-400 mt-1 max-w-xs leading-normal"
                        >
                            Saat ini tidak ada voucher gratis ongkir atau
                            belanja yang sedang aktif di toko.
                        </p>
                    </div>
                {:else}
                    <!-- Section 1: Gratis Ongkir -->
                    {#if shippingVouchers.length > 0}
                        <div class="space-y-1.5">
                            <h4
                                class="text-[9px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-1 px-1"
                            >
                                <i
                                    class="ti ti-truck text-xs"
                                    style="color: {primary};"
                                ></i> Voucher Gratis Ongkir
                            </h4>
                            <div class="space-y-1.5">
                                {#each shippingVouchers as voucher (voucher.id)}
                                    {@const minMet =
                                        subtotal >=
                                        Number(voucher.min_purchase ?? 0)}
                                    {@const isSelected =
                                        appliedVoucher &&
                                        ((Array.isArray(
                                            appliedVoucher.promotions,
                                        ) &&
                                            appliedVoucher.promotions.some(
                                                (p) => p.id === voucher.id,
                                            )) ||
                                            (appliedVoucher.promotion &&
                                                appliedVoucher.promotion.id ===
                                                    voucher.id) ||
                                            voucherInputCode
                                                .split(',')
                                                .map((c) =>
                                                    c.trim().toUpperCase(),
                                                )
                                                .includes(
                                                    voucher.code.toUpperCase(),
                                                ))}

                                    <div
                                        class="bg-white border border-slate-200/80 rounded-xl overflow-hidden shadow-3xs hover:shadow-2xs transition duration-200"
                                        class:opacity-60={!minMet}
                                    >
                                        <div
                                            class="relative flex items-stretch"
                                        >
                                            <!-- Left side color ribbon -->
                                            <div
                                                class="w-16 shrink-0 flex flex-col items-center justify-center p-2 text-white text-center select-none"
                                                style="background: linear-gradient(135deg, {primary}, {withOpacity(
                                                    primary,
                                                    0.85,
                                                )});"
                                            >
                                                <i
                                                    class="ti ti-truck text-base mb-0.5 shrink-0"
                                                ></i>
                                                <span
                                                    class="text-[7.5px] font-black uppercase tracking-wider leading-none"
                                                >
                                                    Free Ongkir
                                                </span>
                                            </div>

                                            <!-- Dashed Ticket Divider line -->
                                            <div
                                                class="absolute left-16 top-0 bottom-0 border-l border-dashed border-slate-200 z-10"
                                            ></div>
                                            <!-- Ticket Top & Bottom cutouts -->
                                            <div
                                                class="absolute left-[58px] -top-1.5 w-3 h-3 rounded-full bg-slate-50 border border-slate-200 z-10"
                                            ></div>
                                            <div
                                                class="absolute left-[58px] -bottom-1.5 w-3 h-3 rounded-full bg-slate-50 border border-slate-200 z-10"
                                            ></div>

                                            <!-- Right side content -->
                                            <div
                                                class="flex-grow py-2 pl-3.5 pr-2.5 flex flex-col justify-between min-w-0"
                                            >
                                                <div class="min-w-0">
                                                    <div
                                                        class="flex items-center gap-1 mb-0.5 flex-wrap"
                                                    >
                                                        <span
                                                            class="text-[8px] font-black px-1.5 py-0.5 rounded border uppercase tracking-wider leading-none shrink-0"
                                                            style="color: {primary}; border-color: {withOpacity(
                                                                primary,
                                                                0.35,
                                                            )}; background: {withOpacity(
                                                                primary,
                                                                0.06,
                                                            )};"
                                                        >
                                                            {voucher.code}
                                                        </span>
                                                        {#if isSelected}
                                                            <span
                                                                class="text-white text-[7.5px] font-black px-1 py-0.5 rounded leading-none shrink-0"
                                                                style="background-color: {primary};"
                                                                >TERPASANG</span
                                                            >
                                                        {/if}
                                                    </div>
                                                    <h4
                                                        class="font-extrabold text-[10px] text-slate-800 line-clamp-1 leading-snug"
                                                    >
                                                        {voucher.name}
                                                    </h4>
                                                    <p
                                                        class="text-[8.5px] text-slate-450 font-bold mt-0.5 leading-none"
                                                    >
                                                        Gratis Ongkir s/d {fmt(
                                                            voucher.max_discount ??
                                                                0,
                                                        )}
                                                    </p>
                                                </div>

                                                <div
                                                    class="flex items-center justify-between mt-1.5 pt-1.5 border-t border-slate-50 gap-1.5 shrink-0"
                                                >
                                                    <div
                                                        class="flex items-center gap-1.5 shrink-0"
                                                    >
                                                        <span
                                                            class="text-[8px] bg-slate-100/80 text-slate-500 font-extrabold px-1.5 py-0.5 rounded leading-none"
                                                        >
                                                            Min. {fmt(
                                                                voucher.min_purchase,
                                                            )}
                                                        </span>
                                                        {#if voucher.settings?.terms}
                                                            <button
                                                                type="button"
                                                                onclick={() =>
                                                                    toggleTerms(
                                                                        voucher.id,
                                                                    )}
                                                                class="text-[8px] text-slate-400 hover:text-slate-650 font-extrabold underline cursor-pointer border-0 bg-transparent flex items-center gap-0.5 shrink-0"
                                                            >
                                                                S&K <i
                                                                    class="ti {expandedTerms[
                                                                        voucher
                                                                            .id
                                                                    ]
                                                                        ? 'ti-chevron-up'
                                                                        : 'ti-chevron-down'} text-[8px]"
                                                                ></i>
                                                            </button>
                                                        {/if}
                                                    </div>

                                                    {#if minMet}
                                                        <button
                                                            onclick={() => {
                                                                if (
                                                                    isSelected
                                                                ) {
                                                                    deselectVoucher(
                                                                        voucher,
                                                                    );
                                                                } else {
                                                                    selectVoucher(
                                                                        voucher,
                                                                    );
                                                                }
                                                            }}
                                                            class="px-2.5 py-1 rounded-md text-[8.5px] font-black transition cursor-pointer border-0 active:scale-95 leading-none shrink-0 text-white"
                                                            style="background: {isSelected
                                                                ? '#cbd5e1'
                                                                : primary}; color: {isSelected
                                                                ? '#475569'
                                                                : 'white'};"
                                                        >
                                                            {isSelected
                                                                ? 'Lepas'
                                                                : 'Pakai'}
                                                        </button>
                                                    {:else}
                                                        <span
                                                            class="text-[8px] text-rose-500 font-extrabold leading-none shrink-0"
                                                        >
                                                            Kurang {fmt(
                                                                Number(
                                                                    voucher.min_purchase,
                                                                ) - subtotal,
                                                            )}
                                                        </span>
                                                    {/if}
                                                </div>
                                            </div>
                                        </div>
                                        {#if expandedTerms[voucher.id] && voucher.settings?.terms}
                                            <div
                                                class="px-3.5 py-2 bg-slate-50 border-t border-slate-100 text-[8.5px] text-slate-500 font-bold whitespace-pre-line leading-relaxed"
                                            >
                                                {voucher.settings.terms}
                                            </div>
                                        {/if}
                                    </div>
                                {/each}
                            </div>
                        </div>
                    {/if}

                    <!-- Section 2: Voucher Belanja -->
                    {#if discountVouchers.length > 0}
                        <div
                            class="space-y-1.5 {shippingVouchers.length > 0
                                ? 'mt-3.5'
                                : ''}"
                        >
                            <h4
                                class="text-[9px] font-black text-slate-450 uppercase tracking-widest flex items-center gap-1 px-1"
                            >
                                <i
                                    class="ti ti-ticket text-xs"
                                    style="color: {primary};"
                                ></i> Voucher Belanja
                            </h4>
                            <div class="space-y-1.5">
                                {#each discountVouchers as voucher (voucher.id)}
                                    {@const minMet =
                                        subtotal >=
                                        Number(voucher.min_purchase ?? 0)}
                                    {@const isSelected =
                                        appliedVoucher &&
                                        ((Array.isArray(
                                            appliedVoucher.promotions,
                                        ) &&
                                            appliedVoucher.promotions.some(
                                                (p) => p.id === voucher.id,
                                            )) ||
                                            (appliedVoucher.promotion &&
                                                appliedVoucher.promotion.id ===
                                                    voucher.id) ||
                                            voucherInputCode
                                                .split(',')
                                                .map((c) =>
                                                    c.trim().toUpperCase(),
                                                )
                                                .includes(
                                                    voucher.code.toUpperCase(),
                                                ))}

                                    <div
                                        class="bg-white border border-slate-200/80 rounded-xl overflow-hidden shadow-3xs hover:shadow-2xs transition duration-200"
                                        class:opacity-60={!minMet}
                                    >
                                        <div
                                            class="relative flex items-stretch"
                                        >
                                            <!-- Left side color ribbon -->
                                            <div
                                                class="w-16 shrink-0 flex flex-col items-center justify-center p-2 text-white text-center select-none"
                                                style="background: linear-gradient(135deg, {primary}, {withOpacity(
                                                    primary,
                                                    0.85,
                                                )});"
                                            >
                                                <i
                                                    class="ti ti-ticket text-base mb-0.5 shrink-0"
                                                ></i>
                                                <span
                                                    class="text-[7.5px] font-black uppercase tracking-wider leading-none"
                                                >
                                                    {#if voucher.settings?.is_points_voucher}Poin{:else}Diskon{/if}
                                                </span>
                                            </div>

                                            <!-- Dashed Ticket Divider line -->
                                            <div
                                                class="absolute left-16 top-0 bottom-0 border-l border-dashed border-slate-200 z-10"
                                            ></div>
                                            <!-- Ticket Top & Bottom cutouts -->
                                            <div
                                                class="absolute left-[58px] -top-1.5 w-3 h-3 rounded-full bg-slate-50 border border-slate-200 z-10"
                                            ></div>
                                            <div
                                                class="absolute left-[58px] -bottom-1.5 w-3 h-3 rounded-full bg-slate-50 border border-slate-200 z-10"
                                            ></div>

                                            <!-- Right side content -->
                                            <div
                                                class="flex-grow py-2 pl-3.5 pr-2.5 flex flex-col justify-between min-w-0"
                                            >
                                                <div class="min-w-0">
                                                    <div
                                                        class="flex items-center gap-1 mb-0.5 flex-wrap"
                                                    >
                                                        <span
                                                            class="text-[8px] font-black px-1.5 py-0.5 rounded border uppercase tracking-wider leading-none shrink-0"
                                                            style="color: {primary}; border-color: {withOpacity(
                                                                primary,
                                                                0.35,
                                                            )}; background: {withOpacity(
                                                                primary,
                                                                0.06,
                                                            )};"
                                                        >
                                                            {voucher.code}
                                                        </span>
                                                        {#if isSelected}
                                                            <span
                                                                class="text-white text-[7.5px] font-black px-1 py-0.5 rounded leading-none shrink-0"
                                                                style="background-color: {primary};"
                                                                >TERPASANG</span
                                                            >
                                                        {/if}
                                                    </div>
                                                    <h4
                                                        class="font-extrabold text-[10px] text-slate-800 line-clamp-1 leading-snug"
                                                    >
                                                        {voucher.name}
                                                    </h4>
                                                    <p
                                                        class="text-[8.5px] text-slate-450 font-bold mt-0.5 leading-none"
                                                    >
                                                        {#if voucher.settings?.is_points_voucher}
                                                            Konversi Poin: +{formatNumber(
                                                                userIsNew
                                                                    ? voucher
                                                                          .settings
                                                                          ?.points_new_user ||
                                                                          0
                                                                    : voucher
                                                                          .settings
                                                                          ?.points_existing_user ||
                                                                          0,
                                                            )} Poin
                                                        {:else}
                                                            Potongan {#if voucher.discount_type === 'percentage'}{Number(
                                                                    voucher.discount_value,
                                                                )}%{:else}{fmt(
                                                                    voucher.discount_value,
                                                                )}{/if}
                                                            {#if voucher.max_discount}s/d
                                                                {fmt(
                                                                    voucher.max_discount,
                                                                )}{/if}
                                                        {/if}
                                                    </p>
                                                </div>

                                                <div
                                                    class="flex items-center justify-between mt-1.5 pt-1.5 border-t border-slate-50 gap-1.5 shrink-0"
                                                >
                                                    <div
                                                        class="flex items-center gap-1.5 shrink-0"
                                                    >
                                                        <span
                                                            class="text-[8px] bg-slate-100/80 text-slate-500 font-extrabold px-1.5 py-0.5 rounded leading-none"
                                                        >
                                                            Min. {fmt(
                                                                voucher.min_purchase,
                                                            )}
                                                        </span>
                                                        {#if voucher.settings?.terms}
                                                            <button
                                                                type="button"
                                                                onclick={() =>
                                                                    toggleTerms(
                                                                        voucher.id,
                                                                    )}
                                                                class="text-[8px] text-slate-450 hover:text-slate-650 font-extrabold underline cursor-pointer border-0 bg-transparent flex items-center gap-0.5 shrink-0"
                                                            >
                                                                S&K <i
                                                                    class="ti {expandedTerms[
                                                                        voucher
                                                                            .id
                                                                    ]
                                                                        ? 'ti-chevron-up'
                                                                        : 'ti-chevron-down'} text-[8px]"
                                                                ></i>
                                                            </button>
                                                        {/if}
                                                    </div>

                                                    {#if minMet}
                                                        <button
                                                            onclick={() => {
                                                                if (
                                                                    isSelected
                                                                ) {
                                                                    deselectVoucher(
                                                                        voucher,
                                                                    );
                                                                } else {
                                                                    selectVoucher(
                                                                        voucher,
                                                                    );
                                                                }
                                                            }}
                                                            class="px-2.5 py-1 rounded-md text-[8.5px] font-black transition cursor-pointer border-0 active:scale-95 leading-none shrink-0 text-white"
                                                            style="background: {isSelected
                                                                ? '#cbd5e1'
                                                                : primary}; color: {isSelected
                                                                ? '#475569'
                                                                : 'white'};"
                                                        >
                                                            {isSelected
                                                                ? 'Lepas'
                                                                : 'Pakai'}
                                                        </button>
                                                    {:else}
                                                        <span
                                                            class="text-[8px] text-rose-500 font-extrabold leading-none shrink-0"
                                                        >
                                                            Kurang {fmt(
                                                                Number(
                                                                    voucher.min_purchase,
                                                                ) - subtotal,
                                                            )}
                                                        </span>
                                                    {/if}
                                                </div>
                                            </div>
                                        </div>
                                        {#if expandedTerms[voucher.id] && voucher.settings?.terms}
                                            <div
                                                class="px-3.5 py-2 bg-slate-50 border-t border-slate-100 text-[8.5px] text-slate-500 font-bold whitespace-pre-line leading-relaxed"
                                            >
                                                {voucher.settings.terms}
                                            </div>
                                        {/if}
                                    </div>
                                {/each}
                            </div>
                        </div>
                    {/if}
                {/if}
            </div>
        </div>
    </div>
{/if}
