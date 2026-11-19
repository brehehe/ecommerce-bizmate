<script lang="ts">
    /* eslint-disable svelte/require-each-key */
    let {
        showReportModal = $bindable(),
        reportReason = $bindable(),
        reportingReview,
        primary,
        submitReport,
        submittingReport,
    }: any = $props();

    function handleKeydown(event: KeyboardEvent) {
        if (event.key === 'Escape') {
            showReportModal = false;
        }
    }
</script>

<svelte:window onkeydown={handleKeydown} />

<!-- Report Review Modal -->
{#if showReportModal && reportingReview}
    <!-- svelte-ignore a11y_click_events_have_key_events -->
    <!-- svelte-ignore a11y_no_static_element_interactions -->
    <div
        class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
        onclick={() => (showReportModal = false)}
    >
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>
        <!-- svelte-ignore a11y_click_events_have_key_events -->
        <!-- svelte-ignore a11y_no_static_element_interactions -->
        <div
            class="relative z-10 bg-white w-full max-w-md rounded-2xl shadow-2xl"
            onclick={(e: any) => e.stopPropagation()}
        >
            <div
                class="flex items-center justify-between px-5 pt-5 pb-3 border-b border-slate-100"
            >
                <div>
                    <h3
                        class="font-bold text-slate-800 text-base flex items-center gap-2"
                    >
                        <i class="ti ti-flag text-red-500"></i>
                        Laporkan Ulasan
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Bantu kami menjaga kualitas ulasan
                    </p>
                </div>
                <button
                    aria-label="Tutup"
                    onclick={() => (showReportModal = false)}
                    class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center hover:bg-slate-200 transition"
                >
                    <i class="ti ti-x text-sm text-slate-600"></i>
                </button>
            </div>
            <div class="p-5 space-y-4">
                <p class="text-xs text-slate-500 leading-relaxed">
                    Pilih alasan pelaporan ulasan ini. Laporan Anda akan
                    ditinjau oleh tim kami.
                </p>
                <div class="space-y-2">
                    {#each ['Konten tidak pantas / kasar', 'Spam atau promosi', 'Ulasan tidak relevan', 'Informasi palsu', 'Lainnya'] as reason}
                        <div
                            role="button"
                            tabindex="0"
                            class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition"
                            style="border-color:{reportReason === reason
                                ? primary + '60'
                                : '#e2e8f0'}; background:{reportReason ===
                            reason
                                ? primary + '08'
                                : 'transparent'};"
                            onclick={() => (reportReason = reason)}
                            onkeydown={(e: KeyboardEvent) => {
                                if (e.key === 'Enter' || e.key === ' ') {
                                    reportReason = reason;
                                }
                            }}
                        >
                            <div
                                class="w-4 h-4 rounded-full border-2 flex items-center justify-center shrink-0 transition"
                                style="border-color:{reportReason === reason
                                    ? primary
                                    : '#cbd5e1'};"
                            >
                                {#if reportReason === reason}
                                    <div
                                        class="w-2 h-2 rounded-full"
                                        style="background:{primary};"
                                    ></div>
                                {/if}
                            </div>
                            <span class="text-sm text-slate-700">{reason}</span>
                        </div>
                    {/each}
                </div>
                <div class="flex gap-3 pt-1">
                    <button
                        onclick={() => (showReportModal = false)}
                        class="flex-1 py-3 rounded-xl border-2 border-slate-200 text-slate-700 font-semibold text-sm hover:bg-slate-50 transition"
                    >
                        Batal
                    </button>
                    <button
                        onclick={submitReport}
                        disabled={submittingReport || !reportReason.trim()}
                        class="flex-1 py-3 rounded-xl font-bold text-white text-sm transition disabled:opacity-50 active:scale-95"
                        style="background:#ef4444;"
                    >
                        {submittingReport ? 'Melaporkan...' : 'Laporkan'}
                    </button>
                </div>
            </div>
        </div>
    </div>
{/if}
