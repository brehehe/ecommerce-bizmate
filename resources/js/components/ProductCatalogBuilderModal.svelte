<script>
    import { toPng } from 'html-to-image';

    let { show = false, productName = '', productDescription = '', productImages = [], onselect, onclose } = $props();

    let title = $state('Produk Premium');
    let subtitle = $state('PREMIUM QUALITY');
    let description = $state('Produk berkualitas tinggi, terjangkau, dan terpercaya.');
    let bannerText = $state('COCOK UNTUK SEHARI-HARI');
    let showSizes = $state(true);
    let mainImageIndex = $state(0);

    let detailSlots = $state([
        { imgIndex: 0, label: 'DETAIL FOTO 1', desc: 'Detail produk berkualitas' },
        { imgIndex: 1, label: 'DETAIL FOTO 2', desc: 'Detail jahitan & finishing' },
        { imgIndex: 2, label: 'DETAIL FOTO 3', desc: 'Tekstur bahan premium' },
        { imgIndex: 3, label: 'DETAIL FOTO 4', desc: 'Desain presisi & rapi' },
    ]);

    let features = $state([
        { text: 'BAHAN COMBED 30s', desc: 'Halus, lembut, dan nyaman', icon: 'ti-shirt' },
        { text: 'ADEM & NYAMAN', desc: 'Sirkulasi udara maksimal', icon: 'ti-wind' },
        { text: 'KUAT & TAHAN LAMA', desc: 'Jahitan rapi dan kuat', icon: 'ti-shield-check' },
        { text: 'WARNA AWET', desc: 'Tidak mudah pudar', icon: 'ti-palette' },
    ]);

    let activeSizes = $state(['S', 'M', 'L', 'XL', 'XXL']);
    let newSizeInput = $state('');
    let isExporting = $state(false);
    let previewElement = $state(null);
    let error = $state(null);

    const iconOptions = [
        { value: 'ti-circle-check', label: 'Ceklist' },
        { value: 'ti-shirt', label: 'Kaos' },
        { value: 'ti-wind', label: 'Angin' },
        { value: 'ti-shield-check', label: 'Perisai' },
        { value: 'ti-palette', label: 'Warna' },
        { value: 'ti-settings', label: 'Gear' },
        { value: 'ti-bolt', label: 'Petir' },
        { value: 'ti-battery', label: 'Baterai' },
        { value: 'ti-device-mobile', label: 'Gadget' },
        { value: 'ti-award', label: 'Medali' },
        { value: 'ti-tag', label: 'Harga' },
        { value: 'ti-heart', label: 'Hati' },
        { value: 'ti-truck', label: 'Pengiriman' },
        { value: 'ti-star', label: 'Bintang' },
        { value: 'ti-refresh', label: 'Garansi' },
        { value: 'ti-package', label: 'Kemasan' },
    ];

    const templates = [
        {
            id: 'clothing',
            name: '👔 Pakaian & Fashion',
            subtitle: 'PREMIUM QUALITY',
            description: 'Bahan combed premium berkualitas tinggi, nyaman dipakai, adem, dan serat kain halus.',
            bannerText: 'COCOK UNTUK SEHARI-HARI',
            showSizes: true,
            activeSizes: ['S', 'M', 'L', 'XL', 'XXL'],
            features: [
                { text: 'BAHAN COMBED 30s', desc: 'Halus, lembut, dan nyaman', icon: 'ti-shirt' },
                { text: 'ADEM & NYAMAN', desc: 'Sirkulasi udara maksimal', icon: 'ti-wind' },
                { text: 'KUAT & TAHAN LAMA', desc: 'Jahitan rapi dan kuat', icon: 'ti-shield-check' },
                { text: 'WARNA AWET', desc: 'Tidak mudah pudar', icon: 'ti-palette' },
            ],
            detailSlots: [
                { label: 'DETAIL LEHER', desc: 'Leher kaos tidak mudah melar' },
                { label: 'JAHITAN RAPI', desc: 'Jahitan rantai standar distro' },
                { label: 'SERAT KAIN', desc: 'Halus, adem, menyerap keringat' },
                { label: 'POTONGAN PRESISI', desc: 'Sangat pas dan nyaman dipasang' },
            ],
        },
        {
            id: 'automotive_bike',
            name: '🚲 Sepeda & Otomotif',
            subtitle: 'HIGH PERFORMANCE',
            description: 'Desain sporty dan modern, rangka sangat kokoh dengan suspensi empuk, siap menerjang segala medan.',
            bannerText: 'GARANSI RESMI PABRIK',
            showSizes: false,
            activeSizes: [],
            features: [
                { text: 'RANGKA ALLOY KOKOH', desc: 'Ringan, kuat, anti karat', icon: 'ti-settings' },
                { text: 'DUAL SUSPENSION', desc: 'Meredam getaran di jalan terjal', icon: 'ti-bolt' },
                { text: 'REM CAKRAM PRESISI', desc: 'Keamanan pengereman maksimal', icon: 'ti-shield-check' },
                { text: 'GIGI MULTI-SPEED', desc: 'Transmisi halus dan responsif', icon: 'ti-circle-check' },
            ],
            detailSlots: [
                { label: 'SUSPENSI EMPUK', desc: 'Peredam kejut depan belakang' },
                { label: 'PIRINGAN REM', desc: 'Cakram mekanik responsif' },
                { label: 'STANG ERGONOMIS', desc: 'Kemudi stabil & nyaman genggam' },
                { label: 'BAN LUAR PREMIUM', desc: 'Bantalan tebal anti-selip' },
            ],
        },
        {
            id: 'electronics',
            name: '⚡ Elektronik & Gadget',
            subtitle: 'SMART TECHNOLOGY',
            description: 'Performa tinggi dengan teknologi mutakhir, daya tahan baterai ekstra lama, dan layar jernih berkualitas.',
            bannerText: '100% ORIGINAL & SEGEL',
            showSizes: false,
            activeSizes: [],
            features: [
                { text: 'PROSESOR CEPAT', desc: 'Performa lancar anti lemot', icon: 'ti-bolt' },
                { text: 'BATERAI AWET', desc: 'Daya tahan seharian penuh', icon: 'ti-battery' },
                { text: 'LAYAR ULTRA HD', desc: 'Warna jernih dan tajam', icon: 'ti-device-mobile' },
                { text: 'GARANSI 1 TAHUN', desc: 'Layanan purna jual resmi', icon: 'ti-award' },
            ],
            detailSlots: [
                { label: 'CHIPSET KUAT', desc: 'Kecepatan pemrosesan tinggi' },
                { label: 'PORT PENGISIAN', desc: 'Mendukung fast charging' },
                { label: 'MODUL KAMERA', desc: 'Resolusi tinggi tangkapan tajam' },
                { label: 'DESAIN TIPIS', desc: 'Ringan dan mudah dibawa' },
            ],
        },
        {
            id: 'general',
            name: '🛍️ Umum / Lainnya',
            subtitle: 'BEST SELLER',
            description: 'Produk pilihan terbaik dengan kualitas terjamin, fungsional, dan awet digunakan untuk jangka panjang.',
            bannerText: '100% PRODUK ORIGINAL',
            showSizes: false,
            activeSizes: [],
            features: [
                { text: 'KUALITAS TERJAMIN', desc: 'Lolos uji kontrol kualitas', icon: 'ti-circle-check' },
                { text: 'DESAIN ELEGAN', desc: 'Tampilan modern dan stylish', icon: 'ti-palette' },
                { text: 'FUNGSI MAKSIMAL', desc: 'Sesuai dengan kebutuhan Anda', icon: 'ti-settings' },
                { text: 'HARGA TERJANGKAU', desc: 'Nilai terbaik untuk uang Anda', icon: 'ti-tag' },
            ],
            detailSlots: [
                { label: 'DETAIL PRODUK 1', desc: 'Spesifikasi bagian depan' },
                { label: 'DETAIL PRODUK 2', desc: 'Spesifikasi bagian samping' },
                { label: 'FINISHING RAPI', desc: 'Detail pengerjaan sempurna' },
                { label: 'KEMASAN AMAN', desc: 'Proteksi ekstra pengiriman' },
            ],
        },
    ];

    let selectedTemplateId = $state('clothing');

    function applyTemplate(templateId) {
        selectedTemplateId = templateId;
        const tpl = templates.find((t) => t.id === templateId);
        if (tpl) {
            subtitle = tpl.subtitle;
            description = tpl.description;
            bannerText = tpl.bannerText;
            showSizes = tpl.showSizes;
            activeSizes = [...tpl.activeSizes];
            features = JSON.parse(JSON.stringify(tpl.features));
            detailSlots = tpl.detailSlots.map((s, i) => ({
                imgIndex: Math.min(i, Math.max(0, productImages.length - 1)),
                label: s.label,
                desc: s.desc,
            }));
        }
    }

    function addSlot() {
        detailSlots = [
            ...detailSlots,
            {
                imgIndex: Math.min(detailSlots.length, Math.max(0, productImages.length - 1)),
                label: 'DETAIL FOTO ' + (detailSlots.length + 1),
                desc: 'Penjelasan detail',
            },
        ];
    }

    function removeSlot(idx) {
        detailSlots = detailSlots.filter((_, i) => i !== idx);
    }

    function addFeature() {
        features = [
            ...features,
            { text: 'FITUR BARU', desc: 'Penjelasan fitur', icon: 'ti-circle-check' },
        ];
    }

    function removeFeature(idx) {
        features = features.filter((_, i) => i !== idx);
    }

    function addCustomSize() {
        const val = newSizeInput.trim().toUpperCase();
        if (val && !activeSizes.includes(val)) {
            activeSizes = [...activeSizes, val];
        }
        newSizeInput = '';
    }

    function removeSize(sz) {
        activeSizes = activeSizes.filter((s) => s !== sz);
    }

    function handleSizeKeydown(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            addCustomSize();
        }
    }

    $effect(() => {
        if (productImages.length > 0) {
            if (mainImageIndex >= productImages.length) {
                mainImageIndex = 0;
            }
            detailSlots.forEach((slot, i) => {
                if (slot.imgIndex === undefined || slot.imgIndex >= productImages.length) {
                    slot.imgIndex = Math.min(i, productImages.length - 1);
                }
            });
        }
    });

    function cleanHtml(html) {
        if (!html) return '';
        return html.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
    }

    let isOpen = false;
    $effect(() => {
        if (show && !isOpen) {
            title = productName;
            const cleanDesc = cleanHtml(productDescription);
            if (cleanDesc) {
                description = cleanDesc.substring(0, 150) + (cleanDesc.length > 150 ? '...' : '');
            }
            if (productName.toLowerCase().includes('sepeda') || productName.toLowerCase().includes('bike') || productName.toLowerCase().includes('polygon')) {
                applyTemplate('automotive_bike');
            } else if (productName.toLowerCase().includes('hp') || productName.toLowerCase().includes('laptop') || productName.toLowerCase().includes('charger') || productName.toLowerCase().includes('earphone')) {
                applyTemplate('electronics');
            } else if (productName.toLowerCase().includes('baju') || productName.toLowerCase().includes('kaos') || productName.toLowerCase().includes('t-shirt') || productName.toLowerCase().includes('kemeja') || productName.toLowerCase().includes('jeans')) {
                applyTemplate('clothing');
            } else {
                applyTemplate('general');
            }
            error = null;
        }
        isOpen = show;
    });

    async function handleGenerate() {
        if (!previewElement || isExporting) return;
        isExporting = true;
        error = null;
        try {
            await new Promise((resolve) => setTimeout(resolve, 300));
            const dataUrl = await toPng(previewElement, {
                width: 800,
                height: 800,
                style: { transform: 'scale(1)', transformOrigin: 'top left' },
                cacheBust: true,
            });
            onselect(dataUrl);
            onclose();
        } catch (err) {
            console.error('Error generating infographic image:', err);
            error = 'Gagal merender gambar katalog: ' + err.message;
        } finally {
            isExporting = false;
        }
    }
</script>

{#if show}
<div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl w-full max-w-6xl max-h-[90vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-200">

        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-150 bg-slate-50">
            <div>
                <h3 class="text-base font-bold text-slate-950 flex items-center gap-2">
                    <i class="ti ti-layout-grid text-brand-blueRoyal"></i>
                    Pembuat Infografis & Katalog Produk
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">Gabungkan gambar produk dan detail ke dalam satu foto katalog komersial yang mewah.</p>
            </div>
            <button type="button" onclick={onclose} disabled={isExporting}
                class="w-8 h-8 rounded-lg flex items-center justify-center hover:bg-slate-150 text-slate-500 hover:text-slate-900 transition cursor-pointer">
                <i class="ti ti-x text-lg"></i>
            </button>
        </div>

        <!-- Body -->
        <div class="flex-1 overflow-y-auto p-6 grid grid-cols-1 lg:grid-cols-12 gap-8">

            <!-- Left Column: Editor -->
            <div class="lg:col-span-5 space-y-5">

                <!-- Preset -->
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-3">
                    <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider">Preset Kategori</h4>
                    <select value={selectedTemplateId} onchange={(e) => applyTemplate(e.target.value)}
                        class="w-full h-9 px-3 rounded-lg border border-slate-250 bg-white text-sm focus:border-brand-blueRoyal focus:ring-1 focus:ring-brand-blueRoyal outline-hidden cursor-pointer">
                        {#each templates as tpl}
                            <option value={tpl.id}>{tpl.name}</option>
                        {/each}
                    </select>
                </div>

                <!-- Text Settings -->
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-3">
                    <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider">Pengaturan Teks</h4>
                    <div class="space-y-1">
                        <label class="text-xs font-semibold text-slate-600">Judul Produk</label>
                        <input type="text" bind:value={title} class="w-full h-9 px-3 rounded-lg border border-slate-250 bg-white text-sm focus:border-brand-blueRoyal focus:ring-1 focus:ring-brand-blueRoyal outline-hidden" />
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-semibold text-slate-600">Label Kualitas (Subtitle)</label>
                        <input type="text" bind:value={subtitle} class="w-full h-9 px-3 rounded-lg border border-slate-250 bg-white text-sm focus:border-brand-blueRoyal focus:ring-1 focus:ring-brand-blueRoyal outline-hidden" />
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-semibold text-slate-600">Deskripsi Singkat</label>
                        <textarea bind:value={description} rows="2" class="w-full px-3 py-2 rounded-lg border border-slate-250 bg-white text-sm focus:border-brand-blueRoyal focus:ring-1 focus:ring-brand-blueRoyal outline-hidden resize-none"></textarea>
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-semibold text-slate-600">Teks Banner (Hijau)</label>
                        <input type="text" bind:value={bannerText} class="w-full h-9 px-3 rounded-lg border border-slate-250 bg-white text-sm focus:border-brand-blueRoyal focus:ring-1 focus:ring-brand-blueRoyal outline-hidden" />
                    </div>
                </div>

                <!-- Image + Slots -->
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-4">
                    <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider">Pilih Gambar</h4>
                    <div class="space-y-2">
                        <label class="text-xs font-semibold text-slate-600 block">Foto Utama (Tengah)</label>
                        {#if productImages.length === 0}
                            <p class="text-xs text-rose-500">Unggah foto produk terlebih dahulu.</p>
                        {:else}
                            <div class="flex gap-2 overflow-x-auto pb-1">
                                {#each productImages as img, idx}
                                    <button type="button" onclick={() => mainImageIndex = idx}
                                        class="w-12 h-12 rounded-lg border-2 overflow-hidden flex-shrink-0 cursor-pointer transition {mainImageIndex === idx ? 'border-brand-blueRoyal ring-2 ring-brand-blueRoyal/20' : 'border-slate-200 hover:border-slate-400'}">
                                        <img src={img} alt="Product Source" class="w-full h-full object-cover" />
                                    </button>
                                {/each}
                            </div>
                        {/if}
                    </div>

                    <!-- Slot Detail — CRUD -->
                    <div class="space-y-2 pt-2 border-t border-slate-200">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-semibold text-slate-600">Slot Foto Detail (Kanan)</label>
                            <button type="button" onclick={addSlot}
                                class="flex items-center gap-1 h-7 px-2.5 rounded-lg bg-brand-blueRoyal text-white text-xs font-semibold hover:bg-brand-blueRoyalHover transition cursor-pointer">
                                <i class="ti ti-plus text-xs"></i> Tambah Slot
                            </button>
                        </div>
                        {#if detailSlots.length === 0}
                            <p class="text-xs text-slate-400 italic">Belum ada slot. Klik "Tambah Slot".</p>
                        {/if}
                        {#each detailSlots as slot, slotIdx}
                            <div class="flex items-center gap-2 p-2 bg-white rounded-lg border border-slate-200">
                                <div class="relative flex-shrink-0">
                                    <div class="w-10 h-10 rounded-md border border-slate-200 overflow-hidden bg-slate-100">
                                        {#if productImages[slot.imgIndex]}
                                            <img src={productImages[slot.imgIndex]} alt="Slot" class="w-full h-full object-cover" />
                                        {:else}
                                            <div class="w-full h-full flex items-center justify-center text-slate-300">
                                                <i class="ti ti-photo text-sm"></i>
                                            </div>
                                        {/if}
                                    </div>
                                    <select bind:value={slot.imgIndex} class="absolute inset-0 opacity-0 cursor-pointer w-full h-full">
                                        {#each productImages as _, idx}
                                            <option value={idx}>Foto {idx + 1}</option>
                                        {/each}
                                    </select>
                                </div>
                                <div class="flex-1 min-w-0 grid grid-cols-2 gap-1.5">
                                    <input type="text" placeholder="Label" bind:value={slot.label} class="h-8 px-2 rounded-md border border-slate-200 text-xs focus:border-brand-blueRoyal outline-hidden" />
                                    <input type="text" placeholder="Penjelasan" bind:value={slot.desc} class="h-8 px-2 rounded-md border border-slate-200 text-xs focus:border-brand-blueRoyal outline-hidden" />
                                </div>
                                <button type="button" onclick={() => removeSlot(slotIdx)} title="Hapus slot"
                                    class="flex-shrink-0 w-7 h-7 rounded-lg flex items-center justify-center text-rose-400 hover:bg-rose-50 hover:text-rose-600 transition cursor-pointer">
                                    <i class="ti ti-trash text-sm"></i>
                                </button>
                            </div>
                        {/each}
                    </div>
                </div>

                <!-- Ukuran — CRUD -->
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-3">
                    <div class="flex items-center justify-between">
                        <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider">Ukuran Produk</h4>
                        <label class="flex items-center gap-1.5 cursor-pointer text-xs font-semibold text-slate-600">
                            <input type="checkbox" bind:checked={showSizes} class="rounded border-slate-300 text-brand-blueRoyal focus:ring-brand-blueRoyal" />
                            Tampilkan Ukuran
                        </label>
                    </div>
                    {#if showSizes}
                        {#if activeSizes.length > 0}
                            <div class="flex flex-wrap gap-1.5">
                                {#each activeSizes as sz}
                                    <span class="inline-flex items-center gap-1 h-7 pl-2.5 pr-1 rounded-lg bg-slate-900 text-white text-xs font-semibold">
                                        {sz}
                                        <button type="button" onclick={() => removeSize(sz)} title="Hapus {sz}"
                                            class="w-4 h-4 rounded flex items-center justify-center hover:bg-white/20 cursor-pointer transition">
                                            <i class="ti ti-x text-[10px]"></i>
                                        </button>
                                    </span>
                                {/each}
                            </div>
                        {:else}
                            <p class="text-xs text-slate-400 italic">Belum ada ukuran. Ketik di bawah lalu klik Tambah.</p>
                        {/if}
                        <div class="flex gap-2">
                            <input type="text" bind:value={newSizeInput} onkeydown={handleSizeKeydown}
                                placeholder="Ukuran baru... (mis: 4XL, 34, 38)"
                                class="flex-1 h-8 px-3 rounded-lg border border-slate-250 bg-white text-xs focus:border-brand-blueRoyal focus:ring-1 focus:ring-brand-blueRoyal outline-hidden" />
                            <button type="button" onclick={addCustomSize}
                                class="h-8 px-3 rounded-lg bg-brand-blueRoyal text-white text-xs font-semibold hover:bg-brand-blueRoyalHover transition flex items-center gap-1 cursor-pointer">
                                <i class="ti ti-plus text-xs"></i> Tambah
                            </button>
                        </div>
                    {/if}
                </div>

                <!-- Keunggulan — CRUD -->
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-3">
                    <div class="flex items-center justify-between">
                        <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider">Poin Keunggulan</h4>
                        <button type="button" onclick={addFeature}
                            class="flex items-center gap-1 h-7 px-2.5 rounded-lg bg-brand-blueRoyal text-white text-xs font-semibold hover:bg-brand-blueRoyalHover transition cursor-pointer">
                            <i class="ti ti-plus text-xs"></i> Tambah
                        </button>
                    </div>
                    {#if features.length === 0}
                        <p class="text-xs text-slate-400 italic">Belum ada poin. Klik "Tambah".</p>
                    {/if}
                    {#each features as feat, idx}
                        <div class="p-2 bg-white rounded-lg border border-slate-200 space-y-1.5">
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 rounded-full bg-brand-blueRoyal/10 text-brand-blueRoyal flex items-center justify-center border border-brand-blueRoyal/15 flex-shrink-0">
                                    <i class="ti {feat.icon} text-sm"></i>
                                </div>
                                <select bind:value={feat.icon}
                                    class="h-7 px-1 rounded-md border border-slate-250 bg-white text-[10px] focus:border-brand-blueRoyal outline-hidden cursor-pointer flex-1">
                                    {#each iconOptions as ico}
                                        <option value={ico.value}>{ico.label}</option>
                                    {/each}
                                </select>
                                <button type="button" onclick={() => removeFeature(idx)} title="Hapus poin"
                                    class="w-7 h-7 rounded-lg flex items-center justify-center text-rose-400 hover:bg-rose-50 hover:text-rose-600 transition cursor-pointer flex-shrink-0">
                                    <i class="ti ti-trash text-sm"></i>
                                </button>
                            </div>
                            <div class="grid grid-cols-2 gap-1.5">
                                <input type="text" bind:value={feat.text} placeholder="NAMA FITUR"
                                    class="h-8 px-2 rounded-md border border-slate-250 text-xs focus:border-brand-blueRoyal outline-hidden font-bold" />
                                <input type="text" bind:value={feat.desc} placeholder="Penjelasan singkat"
                                    class="h-8 px-2 rounded-md border border-slate-250 text-xs focus:border-brand-blueRoyal outline-hidden" />
                            </div>
                        </div>
                    {/each}
                </div>

            </div>

            <!-- Right Column: Preview -->
            <div class="lg:col-span-7 flex flex-col items-center justify-start space-y-4">
                <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider self-start">Hasil Pratinjau Katalog (1:1)</h4>

                <div class="w-full overflow-hidden flex items-center justify-center p-4 bg-slate-100 rounded-2xl border border-slate-200 relative min-h-[520px]">
                    <div bind:this={previewElement} id="infographic-preview-canvas"
                        class="w-[800px] h-[800px] bg-white rounded-3xl p-6 flex flex-row select-none relative font-sans text-slate-800 shadow-xl flex-shrink-0"
                        style="transform: scale(0.6); transform-origin: center center;">

                        <!-- Left: Features -->
                        <div class="w-[35%] flex flex-col justify-between h-full pr-4 border-r border-slate-100">
                            <div>
                                <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight leading-tight uppercase font-sans">{title}</h1>
                                <span class="inline-block bg-amber-500 text-white text-[10px] font-black px-2 py-0.5 rounded-md mt-2 tracking-wide uppercase">{subtitle}</span>
                                <p class="text-slate-500 text-xs mt-3 leading-relaxed font-medium">{description}</p>
                                <div class="mt-6 space-y-4">
                                    {#each features as feat}
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-7 h-7 rounded-full bg-brand-blueRoyal/10 text-brand-blueRoyal flex items-center justify-center flex-shrink-0 shadow-sm border border-brand-blueRoyal/15">
                                                <i class="ti {feat.icon} text-sm"></i>
                                            </div>
                                            <div class="min-w-0">
                                                <h5 class="text-[10px] font-bold text-slate-900 leading-tight tracking-wide">{feat.text}</h5>
                                                <p class="text-[9px] text-slate-400 mt-0.5 leading-none">{feat.desc}</p>
                                            </div>
                                        </div>
                                    {/each}
                                </div>
                            </div>
                            <div class="space-y-3">
                                {#if bannerText}
                                    <div class="bg-emerald-50 text-emerald-800 rounded-lg p-2 flex items-center gap-2 border border-emerald-100">
                                        <i class="ti ti-square-rounded-check text-sm text-emerald-600"></i>
                                        <span class="text-[9px] font-black tracking-wide uppercase leading-tight">{bannerText}</span>
                                    </div>
                                {/if}
                                {#if showSizes && activeSizes.length > 0}
                                    <div class="flex flex-wrap items-center gap-1 mt-2">
                                        <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest mr-1">UKURAN:</span>
                                        {#each activeSizes as sz}
                                            <div class="w-6 h-6 rounded bg-slate-900 text-white text-[9px] font-black flex items-center justify-center shadow-xs">{sz}</div>
                                        {/each}
                                    </div>
                                {/if}
                            </div>
                        </div>

                        <!-- Center: Main Image -->
                        <div class="w-[42%] h-full flex flex-col items-center justify-center px-4 relative">
                            <div class="w-full aspect-square max-h-[85%] rounded-2xl overflow-hidden relative border border-slate-100 shadow-md bg-white">
                                {#if productImages[mainImageIndex]}
                                    <img src={productImages[mainImageIndex]} alt="Main product" class="w-full h-full object-cover" />
                                {:else}
                                    <div class="w-full h-full flex flex-col items-center justify-center text-slate-350 bg-slate-50 gap-2">
                                        <i class="ti ti-photo text-4xl"></i>
                                        <span class="text-[10px] font-bold">Utama</span>
                                    </div>
                                {/if}
                            </div>
                            <div class="absolute bottom-2 text-center">
                                <p class="text-[9px] font-black tracking-widest text-slate-400 uppercase">*TAMPILAN UTAMA</p>
                            </div>
                        </div>

                        <!-- Right: Detail Slots -->
                        <div class="w-[23%] flex flex-col justify-between h-full pl-4 border-l border-slate-100 space-y-2">
                            {#each detailSlots as slot}
                                <div class="flex-1 flex flex-col items-center justify-center bg-slate-50/50 rounded-xl p-1.5 border border-slate-100 shadow-xs">
                                    <div class="w-[85%] aspect-square rounded-lg overflow-hidden bg-white border border-slate-150 relative shadow-inner">
                                        {#if productImages[slot.imgIndex]}
                                            <img src={productImages[slot.imgIndex]} alt="Detail" class="w-full h-full object-cover" />
                                        {:else}
                                            <div class="w-full h-full flex items-center justify-center text-slate-300">
                                                <i class="ti ti-photo text-sm"></i>
                                            </div>
                                        {/if}
                                    </div>
                                    <div class="text-center mt-1 w-full px-1">
                                        <h6 class="text-[9px] font-bold text-slate-800 leading-tight truncate">{slot.label || 'DETAIL FOTO'}</h6>
                                        <p class="text-[8px] text-slate-400 mt-0.5 truncate leading-none">{slot.desc || 'Foto pendukung'}</p>
                                    </div>
                                </div>
                            {/each}
                            {#if detailSlots.length === 0}
                                <div class="flex-1 flex items-center justify-center text-slate-300">
                                    <i class="ti ti-photo text-2xl"></i>
                                </div>
                            {/if}
                        </div>
                    </div>
                </div>

                {#if error}
                    <div class="w-full p-3 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-600 flex items-center gap-2">
                        <i class="ti ti-alert-circle text-base"></i>
                        <span>{error}</span>
                    </div>
                {/if}

                <div class="flex items-center justify-end gap-3 w-full pt-4 border-t border-slate-150">
                    <button type="button" onclick={onclose} disabled={isExporting}
                        class="h-10 px-5 rounded-xl border border-slate-200 text-slate-700 font-semibold text-sm hover:bg-slate-50 cursor-pointer disabled:opacity-50">
                        Batal
                    </button>
                    <button type="button" onclick={handleGenerate} disabled={isExporting || productImages.length === 0}
                        class="h-10 px-6 rounded-xl bg-brand-blueRoyal hover:bg-brand-blueRoyalHover text-white font-semibold text-sm transition flex items-center gap-1.5 cursor-pointer disabled:opacity-50">
                        {#if isExporting}
                            <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Sedang Membuat...
                        {:else}
                            <i class="ti ti-layout-grid-add text-sm"></i>
                            Buat & Terapkan Gambar
                        {/if}
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>
{/if}
