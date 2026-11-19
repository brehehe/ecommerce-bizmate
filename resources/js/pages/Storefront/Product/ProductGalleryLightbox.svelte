<script lang="ts">
    /* eslint-disable svelte/require-each-key */
    let {
        lightboxOpen = $bindable(),
        isZoomed = $bindable(),
        activeIdx = $bindable(),
        variantOverride = $bindable(),
        displayImage,
        product,
        gallery,
        handleTouchStart,
        handleTouchEnd,
        lightboxNext,
        lightboxPrev,
    }: any = $props();
</script>

{#if lightboxOpen && displayImage}
    <!-- svelte-ignore a11y_no_noninteractive_element_interactions -->
    <div
        class="fixed inset-0 z-[200] flex flex-col items-center justify-between bg-black/95 backdrop-blur-md p-4 sm:p-6 select-none"
        onclick={() => {
            lightboxOpen = false;
            isZoomed = false;
        }}
        role="dialog"
        aria-label="Tampilan Foto Produk"
        tabindex="-1"
        onkeydown={(e) => {
            if (e.key === 'Escape') {
                lightboxOpen = false;
                isZoomed = false;
            }

            if (e.key === 'ArrowRight' && gallery.length > 1) {
                isZoomed = false;
                lightboxNext();
            }

            if (e.key === 'ArrowLeft' && gallery.length > 1) {
                isZoomed = false;
                lightboxPrev();
            }
        }}
    >
        <!-- Header -->
        <div class="w-full flex items-center justify-between z-50">
            <span
                class="text-xs font-bold text-slate-300 bg-white/10 px-3 py-1 rounded-full backdrop-blur-sm"
            >
                {activeIdx + 1} / {gallery.length || 1}
            </span>
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    aria-label="Zoom"
                    onclick={(e) => {
                        e.stopPropagation();
                        isZoomed = !isZoomed;
                    }}
                    class="w-10 h-10 rounded-full bg-white/15 hover:bg-white/25 text-white flex items-center justify-center transition border border-white/20 active:scale-95 cursor-pointer"
                >
                    <i
                        class="ti {isZoomed
                            ? 'ti-zoom-out'
                            : 'ti-zoom-in'} text-lg"
                    ></i>
                </button>
                <button
                    type="button"
                    aria-label="Tutup"
                    onclick={() => {
                        lightboxOpen = false;
                        isZoomed = false;
                    }}
                    class="w-10 h-10 rounded-full bg-white/15 hover:bg-white/25 text-white flex items-center justify-center transition border border-white/20 active:scale-95 cursor-pointer"
                >
                    <i class="ti ti-x text-lg"></i>
                </button>
            </div>
        </div>

        <!-- Main Image Preview Container (With Touch Swipe & Zoom Pan) -->
        <!-- svelte-ignore a11y_click_events_have_key_events -->
        <div
            class="relative max-w-5xl max-h-[78vh] flex items-center justify-center my-auto w-full transition-all duration-200 {isZoomed
                ? 'overflow-auto cursor-zoom-out'
                : 'overflow-hidden cursor-zoom-in'}"
            role="presentation"
            onclick={(e) => e.stopPropagation()}
            ontouchstart={handleTouchStart}
            ontouchend={handleTouchEnd}
        >
            {#if gallery.length > 1 && !isZoomed}
                <button
                    type="button"
                    aria-label="Sebelumnya"
                    onclick={(e) => {
                        e.stopPropagation();
                        isZoomed = false;
                        lightboxPrev();
                    }}
                    class="absolute left-2 sm:left-4 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-black/60 hover:bg-black/80 text-white flex items-center justify-center transition z-50 shadow-lg border border-white/20 active:scale-95 cursor-pointer"
                >
                    <i class="ti ti-chevron-left text-xl"></i>
                </button>
                <button
                    type="button"
                    aria-label="Selanjutnya"
                    onclick={(e) => {
                        e.stopPropagation();
                        isZoomed = false;
                        lightboxNext();
                    }}
                    class="absolute right-2 sm:right-4 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-black/60 hover:bg-black/80 text-white flex items-center justify-center transition z-50 shadow-lg border border-white/20 active:scale-95 cursor-pointer"
                >
                    <i class="ti ti-chevron-right text-xl"></i>
                </button>
            {/if}

            <img
                src={displayImage}
                alt={product.name}
                onclick={() => (isZoomed = !isZoomed)}
                class="max-w-full max-h-[72vh] object-contain rounded-2xl shadow-2xl transition-transform duration-300 select-none {isZoomed
                    ? 'scale-[2] my-16'
                    : 'scale-100'}"
                onerror={(e) => {
                    (e.currentTarget as HTMLImageElement).src =
                        '/noimage/image.png';
                }}
            />
        </div>

        <!-- Thumbnail Strip Preview -->
        {#if gallery.length > 1}
            <div
                class="flex items-center gap-2 overflow-x-auto max-w-full py-2 px-3 bg-black/40 rounded-2xl border border-white/10 z-50 no-scrollbar"
                role="presentation"
                onclick={(e) => e.stopPropagation()}
            >
                {#each gallery as imgUrl, i}
                    <button
                        type="button"
                        onclick={() => {
                            activeIdx = i;
                            variantOverride = null;
                            isZoomed = false;
                        }}
                        class="w-12 h-12 rounded-xl overflow-hidden border-2 transition shrink-0 cursor-pointer active:scale-95 {activeIdx ===
                            i && !variantOverride
                            ? 'border-white scale-105 shadow-md'
                            : 'border-transparent opacity-50 hover:opacity-100'}"
                    >
                        <img
                            src={imgUrl}
                            alt="Thumbnail {i + 1}"
                            class="w-full h-full object-cover"
                        />
                    </button>
                {/each}
            </div>
        {/if}
    </div>
{/if}
