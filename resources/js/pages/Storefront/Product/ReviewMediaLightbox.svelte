<script lang="ts">
    let { reviewLightboxOpen = $bindable(), reviewLightboxMedia }: any =
        $props();

    function handleKeydown(event: KeyboardEvent) {
        if (event.key === 'Escape') {
            reviewLightboxOpen = false;
        }
    }
</script>

<svelte:window onkeydown={handleKeydown} />

<!-- Lightbox for Review Media -->
{#if reviewLightboxOpen && reviewLightboxMedia}
    <!-- svelte-ignore a11y_click_events_have_key_events -->
    <!-- svelte-ignore a11y_no_static_element_interactions -->
    <div
        class="fixed inset-0 z-[100] flex items-center justify-center bg-black/95 p-4 sm:p-8 animate-pop"
        onclick={() => {
            reviewLightboxOpen = false;
        }}
    >
        <button
            class="absolute top-4 right-4 sm:top-6 sm:right-6 text-white/70 hover:text-white transition p-2 bg-black/20 rounded-full"
            aria-label="Tutup"
            onclick={(e) => {
                e.stopPropagation();
                reviewLightboxOpen = false;
            }}
        >
            <i class="ti ti-x text-2xl"></i>
        </button>

        <!-- svelte-ignore a11y_click_events_have_key_events -->
        <!-- svelte-ignore a11y_no_static_element_interactions -->
        <div
            class="relative max-w-4xl w-full max-h-full flex items-center justify-center rounded-xl overflow-hidden"
            onclick={(e) => e.stopPropagation()}
        >
            {#if reviewLightboxMedia.type === 'video'}
                <video
                    src={reviewLightboxMedia.url}
                    class="max-w-full h-auto max-h-[85vh] object-contain bg-black"
                    controls
                    autoplay
                    playsinline
                >
                    <track kind="captions" />
                </video>
            {:else}
                <img
                    src={reviewLightboxMedia.url}
                    alt="Preview ulasan"
                    class="max-w-full h-auto max-h-[85vh] object-contain bg-white rounded-md"
                />
            {/if}
        </div>
    </div>
{/if}
