<script lang="ts">
    let {
        primary,
        scanError,
        isScanning,
        startScanning,
        closeScanModal,
    }: {
        primary: string;
        scanError: string;
        isScanning: boolean;
        startScanning: () => void | Promise<void>;
        closeScanModal: () => void | Promise<void>;
    } = $props();

    function handleKeydown(event: KeyboardEvent) {
        if (event.key === 'Escape') {
            void closeScanModal();
        }
    }
</script>

<svelte:window onkeydown={handleKeydown} />

<!-- svelte-ignore a11y_no_static_element_interactions -->
<!-- svelte-ignore a11y_click_events_have_key_events -->
<div
    class="fixed inset-0 z-50 flex items-center justify-center p-4 print:hidden"
    onclick={closeScanModal}
>
    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-xs"></div>
    <!-- svelte-ignore a11y_click_events_have_key_events -->
    <div
        class="relative z-10 bg-white rounded-3xl border border-slate-200 shadow-2xl p-6 w-full max-w-sm flex flex-col max-h-[90vh]"
        role="dialog"
        aria-modal="true"
        aria-labelledby="scanner-modal-title"
        onclick={(event: MouseEvent) => event.stopPropagation()}
    >
        <div
            class="flex items-center justify-between pb-4 border-b border-slate-100"
        >
            <div class="flex items-center gap-2">
                <i class="ti ti-scan text-lg" style="color: {primary}"></i>
                <h3
                    id="scanner-modal-title"
                    class="font-outfit font-black text-slate-800 text-sm uppercase tracking-wider"
                >
                    Pindai QR Voucher
                </h3>
            </div>
            <button
                onclick={closeScanModal}
                class="w-6 h-6 rounded-full bg-slate-100 hover:bg-slate-200 flex items-center justify-center transition shrink-0 border-0 cursor-pointer"
                aria-label="Tutup"
            >
                <i class="ti ti-x text-xs text-slate-500"></i>
            </button>
        </div>

        <div class="py-6 space-y-4 flex-1 flex flex-col items-center">
            <div
                class="relative w-full aspect-square bg-slate-950 rounded-2xl overflow-hidden border border-slate-800 shadow-inner flex items-center justify-center"
            >
                <div
                    id="checkout-scanner-reader"
                    class="w-full h-full object-cover"
                ></div>

                {#if !isScanning}
                    <div
                        class="absolute inset-0 flex flex-col items-center justify-center p-4 text-center bg-slate-950/80"
                    >
                        <i class="ti ti-camera-off text-2xl text-slate-650 mb-2"
                        ></i>
                        <p class="text-[10px] text-slate-400 font-medium">
                            Kamera tidak aktif
                        </p>
                        <button
                            onclick={startScanning}
                            class="mt-3 px-4 py-2 text-white rounded-xl text-xs font-bold transition hover:opacity-90 active:scale-95 border-0 cursor-pointer"
                            style="background: {primary}"
                        >
                            Aktifkan Kamera
                        </button>
                    </div>
                {/if}

                {#if isScanning}
                    <div
                        class="scanner-line absolute left-0 right-0 h-0.5 bg-red-500 shadow-[0_0_8px_rgba(239,68,68,0.8)] pointer-events-none z-10"
                    ></div>
                    <div
                        class="absolute inset-8 border border-white/20 pointer-events-none rounded-xl"
                    ></div>
                {/if}
            </div>

            {#if scanError}
                <p
                    class="text-xs text-red-500 font-bold text-center leading-tight bg-red-50 p-2.5 rounded-xl border border-red-100 w-full"
                >
                    {scanError}
                </p>
            {/if}

            <p class="text-[9.5px] text-slate-400 text-center leading-relaxed">
                Arahkan kamera ke kode QR Voucher cetak Anda. Kode akan otomatis
                terpindai dan dimasukkan ke form voucher.
            </p>
        </div>
    </div>
</div>

<style>
    @keyframes laser {
        0%,
        100% {
            top: 5%;
        }
        50% {
            top: 95%;
        }
    }

    .scanner-line {
        animation: laser 2s infinite linear;
    }

    @media (prefers-reduced-motion: reduce) {
        .scanner-line {
            animation: none;
            top: 50%;
        }
    }
</style>
