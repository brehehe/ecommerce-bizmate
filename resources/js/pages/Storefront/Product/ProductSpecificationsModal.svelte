<script lang="ts">
    /* eslint-disable svelte/require-each-key */
    import { cubicOut } from 'svelte/easing';
    import { fade, fly } from 'svelte/transition';

    let {
        showSpecsModal = $bindable(),
        parsedSpecifications,
        primary,
        secondary,
    }: any = $props();

    function handleKeydown(event: KeyboardEvent) {
        if (event.key === 'Escape') {
            showSpecsModal = false;
        }
    }
</script>

<svelte:window onkeydown={handleKeydown} />

<!-- ─────────────────────────────────────────────────────
     SPESIFIKASI MODAL (BOTTOM SHEET)
 ───────────────────────────────────────────────────── -->
{#if showSpecsModal && parsedSpecifications.length > 0}
    <!-- Backdrop -->
    <!-- svelte-ignore a11y_click_events_have_key_events -->
    <!-- svelte-ignore a11y_no_static_element_interactions -->
    <div
        class="fixed inset-0 z-[150] bg-slate-900/50 backdrop-blur-sm"
        transition:fade={{ duration: 200 }}
        onclick={() => (showSpecsModal = false)}
    ></div>

    <!-- Bottom Sheet Panel -->
    <div
        class="fixed bottom-0 left-0 right-0 z-[151] bg-white rounded-t-3xl shadow-2xl flex flex-col max-h-[85vh] sm:max-w-lg sm:mx-auto sm:rounded-2xl sm:bottom-1/2 sm:translate-y-1/2 sm:inset-x-4 sm:top-auto sm:border sm:border-slate-100"
        transition:fly={{ y: 400, duration: 300, easing: cubicOut }}
    >
        <!-- Drag Handle for Mobile / Header bar -->
        <div class="flex justify-center pt-3 pb-1 shrink-0 sm:hidden">
            <div class="w-10 h-1 rounded-full bg-slate-200"></div>
        </div>

        <!-- Header Title + Close -->
        <div
            class="px-5 pt-3 pb-4 border-b border-slate-100 flex items-center justify-between shrink-0"
        >
            <h3
                class="text-base font-black text-slate-800 flex items-center gap-2"
            >
                <i class="ti ti-list text-lg" style="color: {primary};"></i>
                Spesifikasi Produk
            </h3>
            <button
                onclick={() => (showSpecsModal = false)}
                aria-label="Tutup"
                class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition"
            >
                <i class="ti ti-x text-base"></i>
            </button>
        </div>

        <!-- Content Area (Scrollable) -->
        <div
            class="px-5 py-4 overflow-y-auto space-y-1 divide-y divide-slate-100"
        >
            {#each parsedSpecifications as [label, value]}
                <div class="flex py-3.5 text-sm items-start gap-4">
                    <span
                        class="text-slate-400 w-1/3 font-bold uppercase tracking-wider text-[11px] shrink-0 mt-0.5"
                    >
                        {label}
                    </span>
                    <span class="text-slate-700 font-semibold flex-1">
                        {value}
                    </span>
                </div>
            {/each}
        </div>

        <!-- Footer Action Button -->
        <div class="p-4 border-t border-slate-100 shrink-0">
            <button
                onclick={() => (showSpecsModal = false)}
                class="w-full py-3 rounded-xl font-bold text-sm text-white shadow-md hover:shadow-lg transition duration-200 cursor-pointer"
                style="background: linear-gradient(135deg, {primary}, {secondary});"
            >
                Tutup
            </button>
        </div>
    </div>
{/if}
