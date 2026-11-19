<script lang="ts">
    /* eslint-disable svelte/require-each-key */
    import PremiumVideoPlayer from '@/components/ui/PremiumVideoPlayer.svelte';

    let {
        combinedSlides,
        sliderContainer = $bindable(),
        handleSliderScroll,
        formatImagePath,
        secondary,
        openCameraModal,
        product,
        variantOverride = $bindable(),
        isZoomed = $bindable(),
        lightboxOpen = $bindable(),
        sliderPrev,
        sliderNext,
        goToSlide,
        activeSlideIdx,
        isFlashSale,
        primary,
        flashSaleCountdown,
        fmt,
        activeUnitPrice,
        originalPrice,
        currentPrice,
        discountPercentage,
        isPromoActive,
        shouldKeepTierPricesDuringPromo,
        hasWholesalePrices,
        activePromoRule,
        isPromoStockExhausted,
        desktopSlides,
        activeDesktopSlideIdx,
        mainImageHasError = $bindable(),
        displayImage,
        pickGallery,
    } = $props();
</script>

<!-- ══ LEFT: GALLERY ══════════════════════════════ -->
<div class="flex flex-col gap-3">
    <!-- Mobile Gallery (Slider — combined product + variant images) -->
    <div
        class="md:hidden relative aspect-square bg-slate-50 overflow-hidden -mx-4"
        style="width: calc(100% + 2rem)"
    >
        {#if combinedSlides.length > 0}
            <div
                bind:this={sliderContainer}
                onscroll={handleSliderScroll}
                class="w-full h-full flex overflow-x-auto snap-x snap-mandatory no-scrollbar"
                style="scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch;"
            >
                {#each combinedSlides as slide, i}
                    <div
                        class="w-full h-full shrink-0 snap-center relative aspect-square"
                    >
                        {#if slide.type === 'video'}
                            <div class="w-full h-full bg-black z-20 relative">
                                <PremiumVideoPlayer
                                    src={formatImagePath(slide.path)}
                                    themeColor={secondary}
                                />
                            </div>
                        {:else if slide.type === '3d'}
                            <!-- Stop touch propagation to scroll-snap container so model-viewer
                                 can receive gestures for orbit/rotate/zoom -->
                            <div
                                role="presentation"
                                class="w-full h-full bg-slate-50 relative flex items-center justify-center z-20"
                                ontouchstart={(e) => e.stopPropagation()}
                                ontouchmove={(e) => e.stopPropagation()}
                            >
                                <model-viewer
                                    src={formatImagePath(slide.path)}
                                    ios-src={slide.usdz_path
                                        ? formatImagePath(slide.usdz_path)
                                        : ''}
                                    ar
                                    ar-modes="webxr scene-viewer quick-look"
                                    camera-controls
                                    auto-rotate
                                    interaction-prompt="auto"
                                    class="w-full h-full relative z-20 bg-slate-50"
                                    style="--poster-color: transparent; background-color: #f8fafc; touch-action: none;"
                                >
                                    <!-- Two buttons side by side — raised above dot indicators and model-viewer AR icon -->
                                    <div
                                        class="absolute bottom-16 left-2 right-2 flex justify-between items-center gap-1.5 z-30"
                                    >
                                        <button
                                            type="button"
                                            onclick={() =>
                                                openCameraModal(
                                                    slide.path,
                                                    slide.usdz_path ?? '',
                                                )}
                                            class="bg-slate-900/80 hover:bg-slate-900 active:scale-95 backdrop-blur-sm text-white px-3 py-1.5 rounded-full shadow-lg font-bold text-[10px] transition flex items-center gap-1 border border-white/10"
                                        >
                                            <i class="ti ti-camera text-[11px]"
                                            ></i>
                                            Buka Kamera AR
                                        </button>
                                        <button
                                            slot="ar-button"
                                            class="bg-orange-500 hover:bg-orange-600 active:scale-95 text-white px-3 py-1.5 rounded-full shadow-lg font-bold text-[10px] transition flex items-center gap-1 border border-orange-400/20"
                                        >
                                            <i
                                                class="ti ti-augmented-reality text-[11px]"
                                            ></i>
                                            Lihat di Ruangan
                                        </button>
                                    </div>
                                </model-viewer>
                            </div>
                        {:else}
                            <!-- Slide image -->
                            <button
                                type="button"
                                class="w-full h-full block text-left cursor-zoom-in focus:outline-none"
                                onclick={() => {
                                    if (slide.src) {
                                        if (slide.type === 'gallery') {
                                            activeIdx = slide.galleryIdx;
                                            variantOverride = null;
                                        } else if (slide.type === 'variant') {
                                            variantOverride = slide.src;
                                        }

                                        isZoomed = false;
                                        lightboxOpen = true;
                                    }
                                }}
                            >
                                <img
                                    src={slide.src}
                                    alt="{product.name} {i + 1}"
                                    class="w-full h-full object-cover object-top transition-opacity duration-300 {slide.type ===
                                        'variant' && !slide.available
                                        ? 'opacity-40'
                                        : ''}"
                                    onerror={(e) => {
                                        (
                                            e.currentTarget as HTMLImageElement
                                        ).src = '/noimage/image.png';
                                    }}
                                />
                            </button>

                            <!-- Out-of-stock overlay for variant slides -->
                            {#if slide.type === 'variant' && !slide.available}
                                <div
                                    class="absolute inset-0 flex items-center justify-center z-10 pointer-events-none"
                                >
                                    <div
                                        class="bg-black/60 backdrop-blur-sm text-white font-black text-lg px-6 py-2 rounded-full tracking-widest shadow-xl border border-white/20"
                                    >
                                        HABIS
                                    </div>
                                </div>
                            {/if}

                            {#if slide.type === 'variant'}
                                <div
                                    class="absolute bottom-10 left-3 z-10 pointer-events-none"
                                >
                                    <span
                                        class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-1 rounded-lg bg-black/55 backdrop-blur-sm text-white shadow"
                                    >
                                        <span class="opacity-70"
                                            >{slide.variationName}:</span
                                        >
                                        {slide.optionName}
                                    </span>
                                </div>
                            {/if}
                        {/if}
                    </div>
                {/each}
            </div>

            {#if combinedSlides.length > 1}
                <!-- Prev button -->
                <button
                    onclick={sliderPrev}
                    class="absolute left-2 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-black/40 backdrop-blur-sm text-white flex items-center justify-center shadow-md active:scale-95 transition z-30"
                    aria-label="Sebelumnya"
                >
                    <svg
                        class="w-5 h-5"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.5"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M15 19l-7-7 7-7"
                        />
                    </svg>
                </button>

                <!-- Next button -->
                <button
                    onclick={sliderNext}
                    class="absolute right-2 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-black/40 backdrop-blur-sm text-white flex items-center justify-center shadow-md active:scale-95 transition z-30"
                    aria-label="Berikutnya"
                >
                    <svg
                        class="w-5 h-5"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.5"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M9 5l7 7-7 7"
                        />
                    </svg>
                </button>

                <!-- Dot indicators -->
                <div
                    class="absolute bottom-3 left-1/2 -translate-x-1/2 flex gap-1.5 z-30"
                >
                    {#each combinedSlides as _, i}
                        <button
                            onclick={() => goToSlide(i)}
                            class="rounded-full transition-all duration-300 {i ===
                            activeSlideIdx
                                ? 'w-5 h-1.5 bg-white'
                                : 'w-1.5 h-1.5 bg-white/50'}"
                            aria-label="Slide {i + 1}"
                        ></button>
                    {/each}
                </div>

                <!-- Fractional counter — top-right to avoid overlap with AR/camera buttons at bottom -->
                <div
                    class="absolute top-3 right-3 bg-black/50 text-white text-[11px] font-bold px-2.5 py-0.5 rounded-full backdrop-blur-sm select-none z-30"
                >
                    {activeSlideIdx + 1}/{combinedSlides.length}
                </div>
            {/if}
        {:else}
            <img
                src="/noimage/image.png"
                alt="Tidak ada gambar"
                class="w-full h-full object-cover object-top"
            />
        {/if}
    </div>

    <!-- Mobile Flash Sale Banner (edge-to-edge) -->
    {#if isFlashSale}
        <div class="md:hidden -mx-4 -mt-3 bg-white border-y border-slate-100">
            <!-- Header: FLASH SALE label + countdown -->
            <div
                class="flex items-center justify-between px-4 py-2 flex-wrap gap-2 border-b border-slate-50"
                style="background-color: {primary};"
            >
                <div
                    class="flex items-center gap-1 text-white font-black text-xs tracking-wide uppercase"
                >
                    <span class="text-yellow-300 text-sm drop-shadow-sm"
                        >⚡</span
                    >
                    Flash Sale
                </div>
                <div class="flex items-center gap-1.5">
                    <i class="ti ti-clock text-white/90 text-[10px]"></i>
                    <span
                        class="text-[9px] font-bold text-white uppercase tracking-wider"
                        >Berakhir Dalam</span
                    >
                    <div class="flex items-center gap-0.5">
                        <span
                            class="bg-white font-mono font-black text-[10px] leading-none px-1.5 py-1 rounded-sm shadow-sm tabular-nums"
                            style="color: {primary};"
                            >{flashSaleCountdown.h}</span
                        >
                        <span
                            class="text-white/90 font-black text-[10px] mx-0.5"
                            >:</span
                        >
                        <span
                            class="bg-white font-mono font-black text-[10px] leading-none px-1.5 py-1 rounded-sm shadow-sm tabular-nums"
                            style="color: {primary};"
                            >{flashSaleCountdown.m}</span
                        >
                        <span
                            class="text-white/90 font-black text-[10px] mx-0.5"
                            >:</span
                        >
                        <span
                            class="bg-white font-mono font-black text-[10px] leading-none px-1.5 py-1 rounded-sm shadow-sm tabular-nums"
                            style="color: {primary};"
                            >{flashSaleCountdown.s}</span
                        >
                    </div>
                </div>
            </div>
            <!-- Price row inside banner -->
            <div class="px-4 py-2.5 flex items-baseline gap-2.5 flex-wrap">
                <span class="text-2xl font-bold" style="color: {secondary};">
                    {fmt(activeUnitPrice)}
                </span>
                <span
                    class="text-xs font-black px-2 py-0.5 rounded-md uppercase tracking-wider border {product.price_type ===
                    'nego'
                        ? 'bg-amber-50 text-amber-800 border-amber-200'
                        : 'bg-slate-100 text-slate-700 border-slate-200'}"
                >
                    {product.price_type === 'nego' ? 'NEGO' : 'NET'}
                </span>
                {#if originalPrice && originalPrice > currentPrice}
                    <span class="text-base text-red-600 line-through font-bold"
                        >{fmt(originalPrice)}</span
                    >
                    <span
                        class="text-[10px] font-black px-1.5 py-0.5 rounded-sm text-white shadow-sm"
                        style="background-color: {secondary};"
                        >-{discountPercentage}%</span
                    >
                {/if}
            </div>
        </div>
    {:else}
        <!-- Mobile Regular Price Banner (edge-to-edge) -->
        <div
            class="md:hidden -mx-4 -mt-3 bg-white border-y border-slate-100 px-4 py-3"
        >
            {#if currentPrice > 0}
                <div class="flex items-baseline gap-2.5 flex-wrap">
                    <span
                        class="text-2xl font-bold"
                        style="color: {secondary};"
                    >
                        {fmt(activeUnitPrice)}
                    </span>
                    <span
                        class="text-xs font-black px-2 py-0.5 rounded-md uppercase tracking-wider border {product.price_type ===
                        'nego'
                            ? 'bg-amber-50 text-amber-800 border-amber-200'
                            : 'bg-slate-100 text-slate-700 border-slate-200'}"
                    >
                        {product.price_type === 'nego' ? 'NEGO' : 'NET'}
                    </span>
                    {#if originalPrice && originalPrice > currentPrice}
                        <span
                            class="text-base text-red-600 line-through font-bold"
                        >
                            {fmt(originalPrice)}
                        </span>
                        <span
                            class="text-[10px] font-black px-1.5 py-0.5 rounded-sm text-white shadow-sm"
                            style="background-color: {secondary};"
                        >
                            -{discountPercentage}%
                        </span>
                    {/if}
                </div>
            {:else}
                <span class="text-xl font-bold text-slate-700"
                    >Hubungi Kami</span
                >
            {/if}
        </div>
    {/if}

    <!-- Mobile Wholesale Promo Clash Alert -->
    {#if isPromoActive && !shouldKeepTierPricesDuringPromo && hasWholesalePrices}
        <div
            class="md:hidden flex items-start gap-3 p-4 rounded-2xl border text-xs my-3 shadow-xs"
            style="background-color: #fffaf0; border-color: #fbd38d;"
        >
            <div
                class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center shrink-0"
            >
                <i
                    class="ti ti-info-circle text-amber-600 text-base animate-pulse"
                ></i>
            </div>
            <div class="flex-grow text-[11px] leading-relaxed">
                <span class="font-bold text-amber-950 block text-xs"
                    >Harga Grosir Dijeda Sementara</span
                >
                <p class="text-amber-800 mt-0.5">
                    Produk ini sedang dalam masa promo aktif <span
                        class="font-black text-amber-950 uppercase"
                        >({isPromoActive
                            ? (
                                  matchingVariant?.promo_type ??
                                  product.promo_type ??
                                  'Promo'
                              ).replace('_', ' ')
                            : ''})</span
                    >. Selama masa promo berlangsung, potongan harga grosir
                    dinonaktifkan untuk memberikan harga terbaik.
                </p>
            </div>
        </div>
    {/if}

    <!-- Promo Produk Tiered Info Banner (Mobile) -->
    {#if activePromoRule && activePromoRule.min_qty > 1 && !isPromoStockExhausted}
        <div
            class="md:hidden flex items-start gap-3 p-4 rounded-2xl border text-xs my-3 shadow-xs transition duration-200"
            style="background-color: #f0f7ff; border-color: #bfdbfe;"
        >
            <div
                class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center shrink-0 animate-pulse"
            >
                <i class="ti ti-gift text-blue-600 text-base"></i>
            </div>
            <div class="flex-grow text-[11px] leading-relaxed">
                <span class="font-bold text-blue-950 block text-xs"
                    >Promo Beli Banyak Lebih Hemat!</span
                >
                <p class="text-blue-800 mt-0.5">
                    Beli minimal <strong class="text-blue-950"
                        >{activePromoRule.min_qty} pcs</strong
                    >
                    untuk mendapatkan harga
                    <strong class="text-blue-950">
                        {#if activePromoRule.promo_price}
                            {fmt(activePromoRule.promo_price)}
                        {:else if activePromoRule.discount_type === 'percentage'}
                            Diskon {Number(activePromoRule.discount_value)}%
                        {:else}
                            Potongan {fmt(activePromoRule.discount_value)}
                        {/if}
                    </strong>
                    per produk!
                    {#if activePromoRule.remaining_promo_stock !== null && activePromoRule.remaining_promo_stock !== undefined}
                        <span
                            class="block mt-1.5 font-black text-orange-600 bg-orange-50/80 px-2 py-0.5 rounded-md border border-orange-200/80 w-fit text-[9px] uppercase tracking-wide"
                        >
                            Sisa Stok Promo: {activePromoRule.remaining_promo_stock}
                            pcs
                        </span>
                    {/if}
                </p>
            </div>
        </div>
    {/if}

    <!-- Desktop Gallery (with Zoom and Thumbnails) -->
    <div class="hidden md:flex flex-col gap-3">
        <div
            class="relative aspect-square rounded-2xl overflow-hidden bg-slate-50 border border-slate-100 group select-none {(variantOverride ||
                (desktopSlides[activeDesktopSlideIdx] &&
                    desktopSlides[activeDesktopSlideIdx].type === 'image')) &&
            displayImage &&
            displayImage !== '/noimage/image.png' &&
            !mainImageHasError
                ? 'cursor-zoom-in'
                : ''}"
            onclick={() =>
                (variantOverride ||
                    (desktopSlides[activeDesktopSlideIdx] &&
                        desktopSlides[activeDesktopSlideIdx].type ===
                            'image')) &&
                displayImage &&
                displayImage !== '/noimage/image.png' &&
                !mainImageHasError &&
                (lightboxOpen = true)}
            role="button"
            tabindex="0"
            onkeydown={(e) =>
                e.key === 'Enter' &&
                (variantOverride ||
                    (desktopSlides[activeDesktopSlideIdx] &&
                        desktopSlides[activeDesktopSlideIdx].type ===
                            'image')) &&
                displayImage &&
                displayImage !== '/noimage/image.png' &&
                !mainImageHasError &&
                (lightboxOpen = true)}
        >
            {#if variantOverride}
                <img
                    src={variantOverride}
                    alt={product.name}
                    class="w-full h-full object-cover object-top transition duration-500 group-hover:scale-[1.03]"
                />
            {:else if desktopSlides[activeDesktopSlideIdx] && desktopSlides[activeDesktopSlideIdx].type === 'video'}
                <div class="w-full h-full bg-black z-20 relative">
                    <PremiumVideoPlayer
                        src={formatImagePath(
                            desktopSlides[activeDesktopSlideIdx].path,
                        )}
                        themeColor={secondary}
                    />
                </div>
            {:else if desktopSlides[activeDesktopSlideIdx] && desktopSlides[activeDesktopSlideIdx].type === '3d'}
                <div
                    class="w-full h-full bg-slate-50 relative flex items-center justify-center z-20"
                >
                    <model-viewer
                        src={formatImagePath(
                            desktopSlides[activeDesktopSlideIdx].path,
                        )}
                        ios-src={desktopSlides[activeDesktopSlideIdx].usdz_path
                            ? formatImagePath(
                                  desktopSlides[activeDesktopSlideIdx]
                                      .usdz_path,
                              )
                            : ''}
                        ar
                        ar-modes="webxr scene-viewer quick-look"
                        camera-controls
                        auto-rotate
                        interaction-prompt="auto"
                        class="w-full h-full relative z-20 bg-slate-50"
                        style="--poster-color: transparent; background-color: #f8fafc; touch-action: none;"
                    >
                        <!-- Two buttons side by side at bottom -->
                        <div
                            class="absolute bottom-4 left-4 right-4 flex justify-between items-center gap-2 z-30"
                        >
                            <button
                                type="button"
                                onclick={() =>
                                    openCameraModal(
                                        desktopSlides[activeDesktopSlideIdx]
                                            .path,
                                        desktopSlides[activeDesktopSlideIdx]
                                            .usdz_path ?? '',
                                    )}
                                class="bg-slate-900/80 hover:bg-slate-900 active:scale-95 backdrop-blur-sm text-white px-4 py-2 rounded-full shadow-lg font-semibold text-xs transition flex items-center gap-1.5 border border-white/10"
                            >
                                <i class="ti ti-camera text-base"></i>
                                Buka Kamera AR
                            </button>
                            <button
                                slot="ar-button"
                                class="bg-orange-500 hover:bg-orange-600 active:scale-95 text-white px-4 py-2 rounded-full shadow-lg font-semibold text-xs transition flex items-center gap-1.5 border border-orange-400/20"
                                onclick={(e) => e.stopPropagation()}
                            >
                                <i class="ti ti-augmented-reality text-base"
                                ></i>
                                Lihat di Ruangan (AR)
                            </button>
                        </div>
                    </model-viewer>
                </div>
            {:else}
                {#if displayImage && displayImage !== '/noimage/image.png' && !mainImageHasError}
                    <img
                        src={displayImage}
                        alt={product.name}
                        class="w-full h-full object-cover object-top transition duration-500 group-hover:scale-[1.03]"
                        onerror={() => {
                            mainImageHasError = true;
                        }}
                    />
                {:else}
                    <img
                        src="/noimage/image.png"
                        alt="Tidak ada gambar"
                        class="w-full h-full object-cover object-top"
                    />
                {/if}

                <!-- zoom hint -->
                {#if displayImage && displayImage !== '/noimage/image.png' && !mainImageHasError}
                    <div
                        class="absolute bottom-3 right-3 w-9 h-9 rounded-full bg-black/40 backdrop-blur-sm text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition shadow-lg"
                    >
                        <i class="ti ti-zoom-in text-sm"></i>
                    </div>
                {/if}
            {/if}
        </div>

        <!-- Thumbnails row -->
        {#if desktopSlides.length > 1}
            <div class="flex gap-2 overflow-x-auto pb-1 snap-x select-none">
                {#each desktopSlides as slide, i}
                    <button
                        onclick={() => pickGallery(i)}
                        class="w-[68px] h-[68px] sm:w-[76px] sm:h-[76px] rounded-xl overflow-hidden border-2 shrink-0 snap-start transition duration-200 relative
                           {activeDesktopSlideIdx === i && !variantOverride
                            ? 'border-orange-400 shadow-md shadow-orange-100'
                            : 'border-slate-200 hover:border-slate-400 opacity-80 hover:opacity-100'}"
                    >
                        {#if slide.type === 'image'}
                            <img
                                src={slide.src}
                                alt="{product.name} {i + 1}"
                                class="w-full h-full object-cover"
                                onerror={(e) => {
                                    e.currentTarget.src = '/noimage/image.png';
                                }}
                            />
                        {:else if slide.type === 'video'}
                            <div
                                class="w-full h-full bg-orange-50 flex flex-col items-center justify-center text-orange-600 border border-orange-100"
                            >
                                <i class="ti ti-video text-xl animate-pulse"
                                ></i>
                                <span
                                    class="text-[8px] font-black mt-0.5 tracking-wider uppercase leading-none"
                                    >Video</span
                                >
                            </div>
                        {:else if slide.type === '3d'}
                            <div
                                class="w-full h-full bg-blue-50 flex flex-col items-center justify-center text-blue-600 border border-blue-100"
                            >
                                <i class="ti ti-3d-cube-sphere text-xl"></i>
                                <span
                                    class="text-[8px] font-black mt-0.5 tracking-wider uppercase leading-none"
                                    >3D AR</span
                                >
                            </div>
                        {/if}
                    </button>
                {/each}
            </div>
        {/if}
    </div>
</div>
