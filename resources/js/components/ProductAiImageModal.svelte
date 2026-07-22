<script>
    let { show = false, productName = '', productDescription = '', onselect, onclose } = $props();

    let prompt = $state('');
    let generatedImages = $state([]);
    let selectedImages = $state([]);
    let isGenerating = $state(false);
    let error = $state(null);

    // Helpers
    function cleanHtml(html) {
        if (!html) return '';
        return html.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
    }

    let isOpen = false;
    // Auto-initialize prompt when modal opens
    $effect(() => {
        if (show && !isOpen) {
            const cleanDesc = cleanHtml(productDescription);
            // Combine name and clean description for the prompt (caps length)
            const basePrompt = productName + (cleanDesc ? `, ${cleanDesc}` : '');
            prompt = basePrompt.substring(0, 300); // Reasonably sized prompt
            error = null;
        }
        isOpen = show;
    });

    async function generateImage() {
        if (!prompt.trim() || isGenerating) return;
        isGenerating = true;
        error = null;

        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        try {
            const res = await fetch('/admin/ai/generate-image', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token,
                },
                body: JSON.stringify({ prompt }),
            });

            if (!res.ok) {
                const errData = await res.json();
                throw new Error(errData.message || 'Gagal memproduksi gambar AI.');
            }

            const data = await res.json();
            if (data.image) {
                // Add the generated image to the list
                generatedImages = [data.image, ...generatedImages];
                // Auto-select the newly generated image
                selectedImages = [...selectedImages, data.image];
            } else {
                throw new Error('Format respon gambar dari AI tidak valid.');
            }
        } catch (err) {
            console.error('Error generating image via AI:', err);
            error = err.message || 'Terjadi kesalahan saat memproduksi gambar AI.';
        } finally {
            isGenerating = false;
        }
    }

    function toggleSelect(imageSrc) {
        if (selectedImages.includes(imageSrc)) {
            selectedImages = selectedImages.filter(src => src !== imageSrc);
        } else {
            selectedImages = [...selectedImages, imageSrc];
        }
    }

    function deleteGeneratedImage(imageSrc) {
        generatedImages = generatedImages.filter(img => img !== imageSrc);
        selectedImages = selectedImages.filter(src => src !== imageSrc);
    }

    function handleSave() {
        if (selectedImages.length === 0) return;
        onselect(selectedImages);
        // Clear state
        generatedImages = [];
        selectedImages = [];
        onclose();
    }
</script>

{#if show}
    <!-- Backdrop -->
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs transition-opacity duration-300">
        <!-- Modal Content Container -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl w-full max-w-2xl max-h-[85vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            
            <!-- Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-150 bg-slate-50">
                <div>
                    <h3 class="text-base font-bold text-slate-950 flex items-center gap-2">
                        <i class="ti ti-sparkles text-brand-blueRoyal"></i>
                        Buat Foto Produk dengan AI
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">Ubah teks deskripsi produk menjadi gambar berkualitas tinggi secara instan.</p>
                </div>
                <button
                    type="button"
                    onclick={onclose}
                    disabled={isGenerating}
                    class="w-8 h-8 rounded-full flex items-center justify-center hover:bg-slate-200 text-slate-450 hover:text-slate-700 transition cursor-pointer disabled:opacity-50"
                >
                    <i class="ti ti-x text-base"></i>
                </button>
            </div>

            <!-- Body -->
            <div class="flex-1 overflow-y-auto p-6 space-y-6">
                <!-- Prompt Input Area -->
                <div class="space-y-2">
                    <label for="ai-prompt-textarea" class="text-xs font-bold text-slate-700 uppercase tracking-wider">Prompt Deskripsi Gambar</label>
                    <div class="flex flex-col gap-3">
                        <textarea
                            id="ai-prompt-textarea"
                            bind:value={prompt}
                            disabled={isGenerating}
                            placeholder="Tulis deskripsi gambar yang ingin Anda buat..."
                            rows="3"
                            class="w-full p-3 rounded-xl border border-slate-200 bg-white text-sm text-slate-900 focus:outline-hidden focus:border-brand-blueRoyal focus:ring-1 focus:ring-brand-blueRoyal transition disabled:opacity-55 resize-none"
                        ></textarea>
                        
                        <div class="flex justify-end">
                            <button
                                type="button"
                                onclick={generateImage}
                                disabled={isGenerating || !prompt.trim()}
                                class="h-10 px-5 rounded-xl bg-brand-blueRoyal hover:bg-brand-blueRoyalHover text-white text-sm font-semibold transition flex items-center justify-center gap-1.5 cursor-pointer disabled:opacity-50"
                            >
                                {#if isGenerating}
                                    <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Merancang Gambar...
                                {:else}
                                    <i class="ti ti-wand text-sm"></i>
                                    Buat Gambar dengan AI
                                {/if}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Error alert -->
                {#if error}
                    <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-sm text-rose-600 flex items-center gap-2">
                        <i class="ti ti-alert-circle text-lg"></i>
                        <span>{error}</span>
                    </div>
                {/if}

                <!-- Generated Results Grid -->
                {#if generatedImages.length > 0}
                    <div class="space-y-3">
                        <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider">Hasil Gambar AI ({generatedImages.length})</h4>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                            {#each generatedImages as img}
                                <div
                                    role="button"
                                    tabindex="0"
                                    onclick={() => toggleSelect(img)}
                                    onkeydown={(e) => { if (e.key === 'Enter' || e.key === ' ') { toggleSelect(img); } }}
                                    class="group relative border rounded-xl overflow-hidden aspect-square bg-slate-50 flex flex-col text-left transition duration-200 cursor-pointer shadow-xs focus:ring-2 focus:ring-brand-blueRoyal/50
                                    {selectedImages.includes(img) ? 'border-brand-blueRoyal ring-2 ring-brand-blueRoyal/30' : 'border-slate-200 hover:border-brand-blueRoyal'}"
                                >
                                    <img
                                        src={img}
                                        alt="AI Generated Product"
                                        class="w-full h-full object-cover group-hover:scale-105 transition duration-300"
                                    />
                                    
                                    <!-- Delete Button -->
                                    <button
                                        type="button"
                                        title="Hapus gambar"
                                        onclick={(e) => {
                                            e.stopPropagation();
                                            deleteGeneratedImage(img);
                                        }}
                                        class="absolute top-2 left-2 w-6 h-6 rounded-full bg-slate-900/60 text-white hover:bg-rose-600 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity z-10 cursor-pointer"
                                    >
                                        <i class="ti ti-trash text-xs"></i>
                                    </button>

                                    <!-- Selection Checkmark Badge -->
                                    {#if selectedImages.includes(img)}
                                        <div class="absolute top-2 right-2 bg-brand-blueRoyal text-white rounded-full w-5 h-5 flex items-center justify-center shadow-md">
                                            <i class="ti ti-check text-[10px] font-bold"></i>
                                        </div>
                                    {/if}

                                    <!-- Hover Overlay -->
                                    <div class="absolute inset-0 bg-slate-950/40 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity duration-200">
                                        <span class="bg-white/95 text-slate-900 text-[10px] font-bold px-2.5 py-1.5 rounded-lg shadow-sm flex items-center gap-1">
                                            {#if selectedImages.includes(img)}
                                                <i class="ti ti-minus text-xs"></i>
                                                Batal Pilih
                                            {:else}
                                                <i class="ti ti-plus text-xs"></i>
                                                Pilih Foto
                                            {/if}
                                        </span>
                                    </div>
                                </div>
                            {/each}
                        </div>
                    </div>
                {/if}
            </div>

            <!-- Footer -->
            <div class="px-6 py-4 border-t border-slate-150 bg-slate-50 flex items-center justify-between">
                <div class="text-xs text-slate-500 font-medium">
                    {#if selectedImages.length > 0}
                        <span>Terpilih: <strong class="text-slate-900">{selectedImages.length}</strong> gambar</span>
                    {:else}
                        <span>Pilih gambar hasil AI untuk dimasukkan ke produk</span>
                    {/if}
                </div>

                <div class="flex gap-2">
                    <button
                        type="button"
                        onclick={onclose}
                        disabled={isGenerating}
                        class="h-9 px-4 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold transition cursor-pointer disabled:opacity-50"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        onclick={handleSave}
                        disabled={selectedImages.length === 0 || isGenerating}
                        class="h-9 px-5 rounded-lg bg-brand-blueRoyal hover:bg-brand-blueRoyalHover text-white text-xs font-bold transition flex items-center gap-1.5 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                    >
                        Terapkan ({selectedImages.length}) Gambar
                    </button>
                </div>
            </div>
        </div>
    </div>
{/if}
