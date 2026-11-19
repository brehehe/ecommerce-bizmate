<script lang="ts">
    /* eslint-disable svelte/require-each-key */
    let {
        reviews,
        primary,
        secondary,
        withOpacity,
        reviewLightboxMedia = $bindable(),
        reviewLightboxOpen = $bindable(),
        openReportModal,
    } = $props();
</script>

<!-- Ulasan Section -->
{#if reviews && reviews.length > 0}
    <div
        class="bg-white rounded-none sm:rounded-2xl border-y sm:border border-slate-100 shadow-sm p-5 sm:p-7"
    >
        <h3
            class="text-base font-bold text-slate-800 flex items-center gap-2 mb-5"
        >
            <i class="ti ti-star text-lg" style="color: {primary};"></i>
            Ulasan Pembeli
            {#if reviews.length > 0}
                <span class="ml-auto text-xs font-semibold text-slate-500"
                    >{reviews.length} ulasan</span
                >
            {/if}
        </h3>

        {#if reviews.length > 0}
            {@const avgRating =
                reviews.reduce((s: number, r: any) => s + Number(r.rating), 0) /
                reviews.length}
            <!-- Rating Summary -->
            <div
                class="flex items-center gap-5 p-4 rounded-xl mb-5"
                style="background:{withOpacity(
                    primary,
                    0.04,
                )}; border: 1px solid {withOpacity(primary, 0.1)};"
            >
                <div class="text-center shrink-0">
                    <p class="text-4xl font-black" style="color:{primary}">
                        {avgRating.toFixed(1)}
                    </p>
                    <div class="flex items-center gap-0.5 mt-1 justify-center">
                        {#each [1, 2, 3, 4, 5] as s}
                            <i
                                class="ti ti-star-filled text-sm"
                                style="color:{s <= Math.round(avgRating)
                                    ? '#f59e0b'
                                    : '#e2e8f0'};"
                            ></i>
                        {/each}
                    </div>
                    <p class="text-[10px] text-slate-500 mt-0.5">
                        {reviews.length} ulasan
                    </p>
                </div>
                <div class="flex-grow space-y-1">
                    {#each [5, 4, 3, 2, 1] as star}
                        {@const count = reviews.filter(
                            (r: any) => Number(r.rating) === star,
                        ).length}
                        {@const pct =
                            reviews.length > 0
                                ? (count / reviews.length) * 100
                                : 0}
                        <div class="flex items-center gap-2">
                            <span
                                class="text-[10px] font-semibold text-slate-500 w-3 text-right"
                                >{star}</span
                            >
                            <i
                                class="ti ti-star-filled text-[10px] text-amber-400"
                            ></i>
                            <div
                                class="flex-grow h-1.5 bg-slate-200 rounded-full overflow-hidden"
                            >
                                <div
                                    class="h-full rounded-full transition-all"
                                    style="width:{pct}%; background:{primary};"
                                ></div>
                            </div>
                            <span class="text-[10px] text-slate-400 w-4"
                                >{count}</span
                            >
                        </div>
                    {/each}
                </div>
            </div>

            <!-- Review list -->
            <div class="space-y-4">
                {#each reviews as review}
                    <div
                        class="border-b border-slate-100 pb-4 last:border-0 last:pb-0"
                    >
                        <div class="flex items-start gap-3">
                            <!-- Avatar -->
                            <div
                                class="w-9 h-9 rounded-full flex items-center justify-center shrink-0 font-bold text-white text-sm"
                                style="background: linear-gradient(135deg, {primary}, {secondary});"
                            >
                                {(review.user?.name ?? 'A')
                                    .charAt(0)
                                    .toUpperCase()}
                            </div>
                            <div class="flex-grow min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span
                                        class="text-sm font-bold text-slate-800"
                                        >{review.is_anonymous
                                            ? 'Pengguna Anonim'
                                            : (review.user?.name ??
                                              'Pembeli')}</span
                                    >
                                    {#if review.product_variant?.options?.length > 0}
                                        <span
                                            class="text-[10px] bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full"
                                        >
                                            {review.product_variant.options
                                                .map((o: any) => o.value)
                                                .join(', ')}
                                        </span>
                                    {/if}
                                    <span
                                        class="text-[10px] text-slate-400 ml-auto"
                                    >
                                        {new Date(
                                            review.created_at,
                                        ).toLocaleDateString('id-ID', {
                                            day: 'numeric',
                                            month: 'short',
                                            year: 'numeric',
                                        })}
                                    </span>
                                </div>
                                <!-- Stars -->
                                <div class="flex items-center gap-0.5 mt-1">
                                    {#each [1, 2, 3, 4, 5] as s}
                                        <i
                                            class="ti ti-star-filled text-xs"
                                            style="color:{s <= review.rating
                                                ? '#f59e0b'
                                                : '#e2e8f0'};"
                                        ></i>
                                    {/each}
                                </div>
                                {#if review.comment}
                                    <p
                                        class="text-sm text-slate-700 mt-2 leading-relaxed"
                                    >
                                        {review.comment}
                                    </p>
                                {/if}
                                <!-- Media -->
                                {#if review.media && review.media.length > 0}
                                    <div class="flex gap-2 mt-2 flex-wrap">
                                        {#each review.media as mediaUrl}
                                            {@const isVideo =
                                                /\.(mp4|mov|avi|webm)$/i.test(
                                                    mediaUrl,
                                                )}
                                            {#if isVideo}
                                                <video
                                                    src={mediaUrl}
                                                    class="w-16 h-16 object-cover rounded-lg border border-slate-200 cursor-pointer"
                                                    muted
                                                    playsinline
                                                    onclick={(e: any) => {
                                                        e.preventDefault();
                                                        reviewLightboxMedia = {
                                                            type: 'video',
                                                            url: mediaUrl,
                                                        };
                                                        reviewLightboxOpen = true;
                                                    }}
                                                ></video>
                                            {:else}
                                                <!-- svelte-ignore a11y_click_events_have_key_events -->
                                                <!-- svelte-ignore a11y_no_noninteractive_element_interactions -->
                                                <img
                                                    src={mediaUrl}
                                                    alt="Foto ulasan"
                                                    class="w-16 h-16 object-cover rounded-lg border border-slate-200 cursor-pointer hover:opacity-90 transition"
                                                    onclick={() => {
                                                        reviewLightboxMedia = {
                                                            type: 'image',
                                                            url: mediaUrl,
                                                        };
                                                        reviewLightboxOpen = true;
                                                    }}
                                                    onerror={(e: any) => {
                                                        e.target.style.display =
                                                            'none';
                                                    }}
                                                />
                                            {/if}
                                        {/each}
                                    </div>
                                {/if}
                                <!-- Report button -->
                                <div class="flex justify-end mt-2">
                                    {#if review.is_reported}
                                        <span
                                            class="text-[10px] text-orange-400 flex items-center gap-1"
                                            ><i class="ti ti-flag-filled"></i> Dilaporkan</span
                                        >
                                    {:else}
                                        <button
                                            aria-label="Laporkan ulasan"
                                            onclick={() =>
                                                openReportModal(review)}
                                            class="text-[10px] text-slate-400 hover:text-red-400 flex items-center gap-1 transition"
                                        >
                                            <i class="ti ti-flag"></i> Laporkan
                                        </button>
                                    {/if}
                                </div>
                            </div>
                        </div>
                    </div>
                {/each}
            </div>
        {:else}
            <div class="text-center py-10">
                <div
                    class="w-14 h-14 rounded-2xl flex items-center justify-center mx-auto mb-3"
                    style="background:{withOpacity(
                        primary,
                        0.06,
                    )}; color:{primary};"
                >
                    <i class="ti ti-star text-2xl"></i>
                </div>
                <p class="font-bold text-slate-700 mb-1">Belum ada ulasan</p>
                <p class="text-sm text-slate-400">
                    Jadilah yang pertama memberikan ulasan
                </p>
            </div>
        {/if}
    </div>
{/if}
