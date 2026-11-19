<script>
    let {
        isCameraModalOpen,
        arModelPath,
        cameraVideoEl = $bindable(),
        formatImagePath,
        closeCameraModal,
    } = $props();
</script>

<!-- ─────────────────────────────────────────────────────
     CAMERA AR MODAL — fullscreen popup with live camera feed + 3D overlay
───────────────────────────────────────────────────── -->
{#if isCameraModalOpen && arModelPath}
    <div
        class="fixed inset-0 z-[210] flex flex-col bg-black"
        role="dialog"
        aria-modal="true"
        aria-label="Kamera AR"
        onkeydown={(e) => e.key === 'Escape' && closeCameraModal()}
        tabindex="-1"
    >
        <!-- Live camera feed -->
        <video
            bind:this={cameraVideoEl}
            autoplay
            playsinline
            muted
            class="absolute inset-0 w-full h-full object-cover"
        >
            <track kind="captions" />
        </video>

        <!-- 3D model overlaid transparently on camera feed -->
        <model-viewer
            src={formatImagePath(arModelPath)}
            camera-controls
            auto-rotate
            interaction-prompt="auto"
            class="absolute inset-0 w-full h-full"
            style="--poster-color: transparent; background-color: transparent; touch-action: none;"
        >
        </model-viewer>

        <!-- Top bar: title + close -->
        <div
            class="absolute top-0 left-0 right-0 flex items-center justify-between px-4 py-4 z-30 bg-gradient-to-b from-black/70 to-transparent"
        >
            <div class="flex items-center gap-2 text-white">
                <i class="ti ti-camera text-lg"></i>
                <span class="font-bold text-sm">Kamera AR Aktif</span>
            </div>
            <button
                type="button"
                onclick={closeCameraModal}
                class="w-9 h-9 rounded-full bg-white/20 hover:bg-white/30 active:scale-95 backdrop-blur-sm text-white flex items-center justify-center transition shadow-lg"
                aria-label="Tutup kamera"
            >
                <i class="ti ti-x text-lg"></i>
            </button>
        </div>

        <!-- Bottom hint -->
        <div
            class="absolute bottom-0 left-0 right-0 px-4 pb-8 pt-16 z-30 bg-gradient-to-t from-black/70 to-transparent flex flex-col items-center gap-3"
        >
            <p class="text-white/80 text-xs text-center">
                Arahkan kamera ke permukaan datar · Gunakan gesture untuk
                memutar model 3D
            </p>
            <button
                type="button"
                onclick={closeCameraModal}
                class="bg-white/20 hover:bg-white/30 active:scale-95 backdrop-blur-sm text-white px-5 py-2 rounded-full font-semibold text-sm transition flex items-center gap-2 border border-white/20"
            >
                <i class="ti ti-camera-off text-base"></i>
                Matikan Kamera
            </button>
        </div>
    </div>
{/if}
