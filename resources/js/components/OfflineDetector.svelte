<script lang="ts">
    import { onMount } from 'svelte';
    import { router } from '@inertiajs/svelte';

    let isOffline = $state(false);
    let checking = $state(false);

    function updateOnlineStatus() {
        if (typeof navigator !== 'undefined') {
            isOffline = !navigator.onLine;
        }
    }

    async function checkConnection() {
        if (checking) return;
        checking = true;

        // Check local navigator status first
        if (typeof navigator !== 'undefined' && !navigator.onLine) {
            setTimeout(() => {
                checking = false;
            }, 800);
            return;
        }

        // Try a lightweight request to the server's health endpoint to be 100% sure
        try {
            const response = await fetch('/up', {
                method: 'HEAD',
                cache: 'no-store',
            });
            if (response.ok) {
                isOffline = false;
            } else {
                isOffline = true;
            }
        } catch (error) {
            isOffline = true;
        } finally {
            checking = false;
        }
    }

    onMount(() => {
        // Initial check
        updateOnlineStatus();

        // Listen for browser connectivity events
        window.addEventListener('online', updateOnlineStatus);
        window.addEventListener('offline', updateOnlineStatus);

        // Hook into Inertia's networkError event
        const unbindNetworkError = router.on('networkError', (event) => {
            event.preventDefault();
            isOffline = true;
        });

        return () => {
            window.removeEventListener('online', updateOnlineStatus);
            window.removeEventListener('offline', updateOnlineStatus);
            unbindNetworkError();
        };
    });
</script>

{#if isOffline}
    <div
        class="fixed inset-0 z-[9999] bg-slate-900/60 backdrop-blur-md flex items-center justify-center p-4 font-sans select-none animate-in fade-in duration-300"
    >
        <!-- Premium background grid pattern -->
        <div
            class="absolute inset-0 bg-grid opacity-[0.25] pointer-events-none"
        ></div>

        <!-- Glowing dynamic backdrop blobs (Warning Rose/Orange Theme) -->
        <div
            class="absolute -top-[10%] -left-[10%] w-[60vw] h-[60vw] rounded-full bg-rose-500/10 blur-[120px] animate-float pointer-events-none"
        ></div>
        <div
            class="absolute -bottom-[10%] -right-[10%] w-[60vw] h-[60vw] rounded-full bg-orange-500/10 blur-[120px] animate-float-delayed pointer-events-none"
        ></div>

        <!-- Ambient drifting floating particles -->
        <div class="absolute inset-0 pointer-events-none overflow-hidden">
            <div class="particle p1"></div>
            <div class="particle p2"></div>
            <div class="particle p3"></div>
            <div class="particle p4"></div>
        </div>

        <!-- Glassmorphic Card -->
        <div
            class="relative bg-white/85 backdrop-blur-xl w-full max-w-sm rounded-3xl p-8 text-center shadow-[0_20px_50px_rgba(0,0,0,0.15)] border border-white/50 flex flex-col items-center gap-6 animate-in zoom-in-95 duration-200"
        >
            <!-- Subtle diagonal shine line -->
            <div
                class="absolute inset-0 bg-gradient-to-tr from-transparent via-white/10 to-transparent pointer-events-none"
            ></div>

            <!-- Glowing Core Portal for Offline Wifi Status -->
            <div class="relative flex items-center justify-center select-none">
                <!-- Ping waves -->
                <div
                    class="absolute w-32 h-32 rounded-full bg-rose-500/10 animate-ping opacity-75"
                ></div>
                <div
                    class="absolute w-28 h-28 rounded-full bg-rose-500/5 animate-pulse"
                ></div>

                <!-- Rotating Core Ring (matches the visual language of Error.svelte) -->
                <div
                    class="w-24 h-24 rounded-[1.8rem] relative flex items-center justify-center overflow-hidden bg-slate-950 shadow-2xl border-4 border-slate-900 shadow-rose-500/25"
                >
                    <div
                        class="absolute inset-0 bg-gradient-to-tr from-rose-500 via-orange-500 to-amber-500 animate-spin duration-5000 opacity-90"
                    ></div>
                    <div
                        class="absolute inset-[3px] rounded-[1.6rem] bg-slate-950 flex items-center justify-center"
                    >
                        <i
                            class="ti ti-wifi-off text-3xl text-white animate-bounce-custom"
                        ></i>
                    </div>
                </div>
            </div>

            <!-- Heading & Description -->
            <div class="space-y-2.5">
                <!-- Offline Badge -->
                <span
                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest bg-rose-500 text-white shadow-xs"
                >
                    <span class="w-1.5 h-1.5 rounded-full bg-white animate-ping"
                    ></span>
                    Tidak Ada Jaringan
                </span>

                <h3
                    class="font-outfit font-black text-slate-900 text-xl leading-none pt-1"
                >
                    Koneksi Terputus
                </h3>
                <p
                    class="text-xs text-slate-500 font-bold leading-relaxed max-w-[260px] mx-auto"
                >
                    Sepertinya perangkat Anda tidak terhubung ke jaringan
                    internet. Harap periksa Wi-Fi atau paket data seluler Anda.
                </p>
            </div>

            <div class="w-full h-px bg-slate-100"></div>

            <!-- Action button to retry -->
            <button
                onclick={checkConnection}
                disabled={checking}
                class="w-full py-3.5 rounded-2xl text-xs font-black text-white shadow-lg shadow-orange-500/15 transition active:scale-95 disabled:opacity-75 flex items-center justify-center gap-2 cursor-pointer"
                style="background: linear-gradient(135deg, #0c4cb4, #fa7315);"
            >
                {#if checking}
                    <i class="ti ti-loader animate-spin text-sm"></i>
                    Menghubungkan Kembali...
                {:else}
                    <i class="ti ti-refresh text-sm animate-pulse"></i>
                    Hubungkan Kembali
                {/if}
            </button>
        </div>
    </div>
{/if}

<style>
    /* CSS Grid background pattern */
    .bg-grid {
        background-size: 32px 32px;
        background-image:
            linear-gradient(to right, #cbd5e1 1px, transparent 1px),
            linear-gradient(to bottom, #cbd5e1 1px, transparent 1px);
        mask-image: radial-gradient(
            circle at center,
            black 45%,
            transparent 90%
        );
        -webkit-mask-image: radial-gradient(
            circle at center,
            black 45%,
            transparent 90%
        );
    }

    /* Floating blobs animation */
    @keyframes float-anim {
        0%,
        100% {
            transform: translateY(0px) scale(1);
        }
        50% {
            transform: translateY(-20px) scale(1.05);
        }
    }
    .animate-float {
        animation: float-anim 10s ease-in-out infinite;
    }
    .animate-float-delayed {
        animation: float-anim 12s ease-in-out infinite;
        animation-delay: 2s;
    }

    /* Ambient floating particles */
    .particle {
        position: absolute;
        border-radius: 50%;
        background-color: #cbd5e1;
        opacity: 0.4;
        pointer-events: none;
    }
    .p1 {
        width: 8px;
        height: 8px;
        top: 20%;
        left: 15%;
        animation: particle-float 15s linear infinite;
    }
    .p2 {
        width: 12px;
        height: 12px;
        top: 70%;
        left: 25%;
        animation: particle-float-delayed 18s linear infinite;
    }
    .p3 {
        width: 6px;
        height: 6px;
        top: 35%;
        right: 20%;
        animation: particle-float 14s linear infinite;
    }
    .p4 {
        width: 10px;
        height: 10px;
        top: 75%;
        right: 15%;
        animation: particle-float-delayed 16s linear infinite;
    }

    @keyframes particle-float {
        0%,
        100% {
            transform: translateY(0) translateX(0);
        }
        50% {
            transform: translateY(-35px) translateX(15px);
        }
    }
    @keyframes particle-float-delayed {
        0%,
        100% {
            transform: translateY(0) translateX(0);
        }
        50% {
            transform: translateY(-25px) translateX(-10px);
        }
    }

    /* Custom core portal animation */
    .duration-5000 {
        animation-duration: 5s;
    }
    @keyframes bounce-custom {
        0%,
        100% {
            transform: translateY(0) scale(1);
        }
        50% {
            transform: translateY(-5px) scale(1.03);
        }
    }
    .animate-bounce-custom {
        animation: bounce-custom 2.5s ease-in-out infinite;
    }
</style>
