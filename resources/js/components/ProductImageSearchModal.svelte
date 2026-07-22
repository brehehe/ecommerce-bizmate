<script>
    let { show = false, productName = '', onselect, onclose } = $props();

    let query = $state('');
    let images = $state([]);
    let loading = $state(false);
    let selectedUrls = $state([]);
    let isSaving = $state(false);
    let saveProgress = $state('');
    let downloadingUrl = $state(null);
    let error = $state(null);

    let isOpen = false;
    // Auto-search & reset when modal opens
    $effect(() => {
        if (show && !isOpen) {
            query = productName;
            images = [];
            selectedUrls = [];
            error = null;
            if (query.trim()) {
                searchImages();
            }
        }
        isOpen = show;
    });

    async function searchImages() {
        if (!query.trim()) return;
        loading = true;
        error = null;
        images = [];

        try {
            const res = await fetch(`/admin/products/search-web-images?q=${encodeURIComponent(query)}`, {
                headers: {
                    'Accept': 'application/json',
                }
            });

            if (!res.ok) {
                throw new Error('Gagal memuat hasil pencarian gambar.');
            }

            const data = await res.json();
            images = data.images || [];
            if (images.length === 0) {
                error = 'Tidak ditemukan gambar untuk pencarian ini.';
            }
        } catch (err) {
            error = err.message || 'Terjadi kesalahan saat mencari gambar.';
        } finally {
            loading = false;
        }
    }

    function toggleSelect(imageUrl) {
        if (isSaving) return;
        if (selectedUrls.includes(imageUrl)) {
            selectedUrls = selectedUrls.filter(url => url !== imageUrl);
        } else {
            selectedUrls = [...selectedUrls, imageUrl];
        }
    }

    async function handleSave() {
        if (selectedUrls.length === 0 || isSaving) return;
        isSaving = true;
        error = null;

        const base64Images = [];
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        try {
            for (let i = 0; i < selectedUrls.length; i++) {
                const url = selectedUrls[i];
                saveProgress = `Mengunduh ${i + 1} dari ${selectedUrls.length}...`;
                downloadingUrl = url; // Show spinner on this specific card

                const res = await fetch('/admin/products/proxy-web-image', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token,
                    },
                    body: JSON.stringify({ url }),
                });

                if (!res.ok) {
                    const errData = await res.json();
                    throw new Error(errData.message || 'Gagal mengunduh gambar.');
                }

                const data = await res.json();
                if (data.image) {
                    base64Images.push(data.image);
                } else {
                    throw new Error('Format gambar tidak valid.');
                }
            }

            if (base64Images.length > 0) {
                onselect(base64Images);
                onclose();
            }
        } catch (err) {
            error = err.message || 'Terjadi kesalahan saat mengunduh gambar.';
        } finally {
            isSaving = false;
            saveProgress = '';
            downloadingUrl = null;
        }
    }
</script>

{#if show}
    <!-- Backdrop -->
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs transition-opacity duration-300">
        <!-- Modal Content Container -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl w-full max-w-3xl max-h-[85vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            
            <!-- Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-150 bg-slate-50">
                <div>
                    <h3 class="text-base font-bold text-slate-950">Cari Gambar Produk Otomatis</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Cari gambar terbaik dari web dan pasang langsung ke produk Anda.</p>
                </div>
                <button
                    type="button"
                    onclick={onclose}
                    disabled={isSaving}
                    class="w-8 h-8 rounded-full flex items-center justify-center hover:bg-slate-200 text-slate-450 hover:text-slate-700 transition cursor-pointer disabled:opacity-50"
                >
                    <i class="ti ti-x text-base"></i>
                </button>
            </div>

            <!-- Body -->
            <div class="flex-1 overflow-y-auto p-6 space-y-6">
                <!-- Search bar -->
                <form onsubmit={(e) => { e.preventDefault(); searchImages(); }} class="flex gap-2">
                    <div class="relative flex-1">
                        <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input
                            type="text"
                            bind:value={query}
                            disabled={isSaving || loading}
                            placeholder="Ketik kata kunci pencarian..."
                            class="w-full h-10 pl-9 pr-4 rounded-xl border border-slate-200 bg-white text-sm text-slate-900 focus:outline-hidden focus:border-brand-blueRoyal focus:ring-1 focus:ring-brand-blueRoyal transition disabled:opacity-55"
                        />
                    </div>
                    <button
                        type="submit"
                        disabled={loading || isSaving}
                        class="h-10 px-5 rounded-xl bg-brand-blueRoyal hover:bg-brand-blueRoyalHover text-white text-sm font-semibold transition flex items-center justify-center gap-1.5 cursor-pointer disabled:opacity-50"
                    >
                        {#if loading}
                            <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Mencari...
                        {:else}
                            Cari
                        {/if}
                    </button>
                </form>

                <!-- Error alert -->
                {#if error}
                    <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-sm text-rose-600 flex items-center gap-2">
                        <i class="ti ti-alert-circle text-lg"></i>
                        <span>{error}</span>
                    </div>
                {/if}

                <!-- Loading state -->
                {#if loading}
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        {#each Array(8) as _}
                            <div class="border border-slate-150 rounded-xl overflow-hidden aspect-square bg-slate-50 animate-pulse flex flex-col justify-end p-2 space-y-2">
                                <div class="h-3 bg-slate-200 rounded-sm w-3/4"></div>
                                <div class="h-2 bg-slate-200 rounded-sm w-1/2"></div>
                            </div>
                        {/each}
                    </div>
                {/if}

                <!-- Images Results -->
                {#if !loading && images.length > 0}
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        {#each images as img}
                            <button
                                type="button"
                                onclick={() => toggleSelect(img.url)}
                                disabled={isSaving}
                                class="group relative border rounded-xl overflow-hidden aspect-square bg-slate-50 flex flex-col text-left transition duration-200 cursor-pointer shadow-xs focus:ring-2 focus:ring-brand-blueRoyal/50 disabled:opacity-75 disabled:cursor-not-allowed
                                {selectedUrls.includes(img.url) ? 'border-brand-blueRoyal ring-2 ring-brand-blueRoyal/30' : 'border-slate-200 hover:border-brand-blueRoyal'}"
                            >
                                <img
                                    src={img.thumbnail}
                                    alt={img.title}
                                    class="w-full h-full object-cover group-hover:scale-105 transition duration-300"
                                    loading="lazy"
                                />
                                
                                <!-- Selection Checkmark Badge -->
                                {#if selectedUrls.includes(img.url)}
                                    <div class="absolute top-2 right-2 bg-brand-blueRoyal text-white rounded-full w-5 h-5 flex items-center justify-center shadow-md animate-in zoom-in-50 duration-150">
                                        <i class="ti ti-check text-[10px] font-bold"></i>
                                    </div>
                                {/if}

                                <!-- Hover Overlay -->
                                <div class="absolute inset-0 bg-slate-950/40 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity duration-200">
                                    <span class="bg-white/95 text-slate-900 text-[10px] font-bold px-2.5 py-1.5 rounded-lg shadow-sm flex items-center gap-1">
                                        {#if selectedUrls.includes(img.url)}
                                            <i class="ti ti-minus text-xs"></i>
                                            Batal Pilih
                                        {:else}
                                            <i class="ti ti-plus text-xs"></i>
                                            Pilih Foto
                                        {/if}
                                    </span>
                                </div>

                                <!-- Loading Indicator when selected and saving -->
                                {#if downloadingUrl === img.url}
                                    <div class="absolute inset-0 bg-slate-900/75 flex flex-col items-center justify-center text-white p-3 text-center space-y-2">
                                        <svg class="animate-spin h-6 w-6 text-white" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span class="text-[9px] font-semibold tracking-wide">Mengunduh...</span>
                                    </div>
                                {/if}
                            </button>
                        {/each}
                    </div>
                {/if}
            </div>

            <!-- Footer -->
            <div class="px-6 py-4 border-t border-slate-150 bg-slate-50 flex items-center justify-between">
                <div class="text-xs text-slate-500 font-medium">
                    {#if isSaving}
                        <span class="flex items-center gap-2 text-brand-blueRoyal">
                            <svg class="animate-spin h-3.5 w-3.5 text-brand-blueRoyal" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {saveProgress}
                        </span>
                    {:else if selectedUrls.length > 0}
                        <span>Terpilih: <strong class="text-slate-900">{selectedUrls.length}</strong> gambar</span>
                    {:else}
                        <span>Pilih satu atau lebih gambar untuk disimpan</span>
                    {/if}
                </div>

                <div class="flex gap-2">
                    <button
                        type="button"
                        onclick={onclose}
                        disabled={isSaving}
                        class="h-9 px-4 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold transition cursor-pointer disabled:opacity-50"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        onclick={handleSave}
                        disabled={selectedUrls.length === 0 || isSaving}
                        class="h-9 px-5 rounded-lg bg-brand-blueRoyal hover:bg-brand-blueRoyalHover text-white text-xs font-bold transition flex items-center gap-1.5 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                    >
                        {#if isSaving}
                            Menyimpan...
                        {:else}
                            Simpan ({selectedUrls.length}) Gambar
                        {/if}
                    </button>
                </div>
            </div>
        </div>
    </div>
{/if}
