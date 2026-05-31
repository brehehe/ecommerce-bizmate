<script lang="ts">
    import { onDestroy } from 'svelte';
    import Plyr from 'plyr';
    import 'plyr/dist/plyr.css';

    let {
        src = '',
        autoplay = true,
        loop = true,
        playsinline = true,
        controls = ['play-large', 'play', 'progress', 'current-time', 'mute', 'volume', 'fullscreen'],
        themeColor = '#fa7315'
    } = $props();

    let videoElement = $state<HTMLVideoElement | null>(null);
    let player: Plyr | null = null;

    $effect(() => {
        if (src && videoElement) {
            // Re-initialize player if src changes
            if (player) {
                player.destroy();
                player = null;
            }

            player = new Plyr(videoElement, {
                controls,
                autoplay,
                loop: { active: loop },
                keyboard: { focused: true, global: false }
            });
        }
    });

    onDestroy(() => {
        if (player) {
            player.destroy();
        }
    });
</script>

<div 
    class="w-full h-full bg-black flex items-center justify-center relative overflow-hidden"
    style="--plyr-color-main: {themeColor};"
>
    <!-- svelte-ignore a11y_media_has_caption -->
    <video
        bind:this={videoElement}
        src={src}
        {playsinline}
        class="w-full h-full object-contain"
    >
    </video>
</div>

<style>
    :global(.plyr) {
        width: 100% !important;
        height: 100% !important;
        border-radius: 0;
    }
    :global(.plyr__video-wrapper) {
        height: 100% !important;
    }
    :global(.plyr video) {
        object-fit: contain !important;
        height: 100% !important;
    }
</style>
