<script lang="ts">
    import { page, useForm } from '@inertiajs/svelte';
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { showToast } from '@/utils/toast';

    let {
        heroBanners = [],
        sideBanners = [],
        middleWideBanner = null,
        popupBanner = null,
        storefrontUrl,
    } = $props();

    const primaryColor = $derived(page.props.theme?.primary_color || '#0c4cb4');
    const secondaryColor = $derived(page.props.theme?.secondary_color || '#fa7315');

    // svelte-ignore state_referenced_locally
    const form = useForm({
        hero_banners: heroBanners.length > 0 ? [...heroBanners] : [{ image: '/banners/promo-main.png', alt: 'Promo Spesial', link: '#' }],
        side_banners: sideBanners.length > 0 ? [...sideBanners] : [{ image: '/banners/fashion.png', alt: 'Fashion Diskon', link: '#' }],
        middle_wide_banner: middleWideBanner || { image: '/banners/flash-sale.png', alt: 'Flash Sale Promo', link: '#' },
        popup_banner: popupBanner
            ? { image: popupBanner.image || '', alt: popupBanner.alt || 'Promo Spesial', link: popupBanner.link || '#', is_active: !!popupBanner.is_active, orientation: popupBanner.orientation || 'portrait' }
            : { image: '', alt: 'Promo Spesial', link: '#', is_active: false, orientation: 'portrait' },
        hero_files: {} as Record<number, File>,
        side_files: {} as Record<number, File>,
        middle_wide_file: null as File | null,
        popup_file: null as File | null,
    });

    let previewKey = $state(0);
    let uploadErrors = $state<Record<string, string>>({});

    function addHeroBanner() {
        form.hero_banners = [...form.hero_banners, { image: '', alt: 'Banner Baru', link: '#' }];
    }
    function removeHeroBanner(index: number) {
        form.hero_banners = form.hero_banners.filter((_, i) => i !== index);
        if (form.hero_files[index]) { const f = { ...form.hero_files }; delete f[index]; form.hero_files = f; }
    }
    function addSideBanner() {
        form.side_banners = [...form.side_banners, { image: '', alt: 'Side Banner Baru', link: '#' }];
    }
    function removeSideBanner(index: number) {
        form.side_banners = form.side_banners.filter((_, i) => i !== index);
        if (form.side_files[index]) { const f = { ...form.side_files }; delete f[index]; form.side_files = f; }
    }

    function validateImageOrientation(file: File, expected: 'landscape' | 'portrait'): Promise<boolean> {
        return new Promise((resolve) => {
            const img = new Image();
            img.src = URL.createObjectURL(file);
            img.onload = () => {
                URL.revokeObjectURL(img.src);
                if (expected === 'landscape' && img.width <= img.height) resolve(false);
                else if (expected === 'portrait' && img.height <= img.width) resolve(false);
                else resolve(true);
            };
            img.onerror = () => resolve(false);
        });
    }

    async function handleHeroFileChange(index: number, e: Event) {
        const target = e.target as HTMLInputElement;
        const file = target.files?.[0];
        const key = `hero_${index}`;
        delete uploadErrors[key];
        if (!file) return;
        if (file.size > 2 * 1024 * 1024) { uploadErrors[key] = 'Maks. 2MB.'; target.value = ''; return; }
        if (!await validateImageOrientation(file, 'landscape')) { uploadErrors[key] = 'Gambar harus lanskap (lebar > tinggi).'; target.value = ''; return; }
        form.hero_files[index] = file;
        form.hero_banners[index].image = URL.createObjectURL(file);
    }

    async function handleSideFileChange(index: number, e: Event) {
        const target = e.target as HTMLInputElement;
        const file = target.files?.[0];
        const key = `side_${index}`;
        delete uploadErrors[key];
        if (!file) return;
        if (file.size > 2 * 1024 * 1024) { uploadErrors[key] = 'Maks. 2MB.'; target.value = ''; return; }
        if (!await validateImageOrientation(file, 'portrait')) { uploadErrors[key] = 'Gambar harus potret (tinggi > lebar).'; target.value = ''; return; }
        form.side_files[index] = file;
        form.side_banners[index].image = URL.createObjectURL(file);
    }

    async function handleMiddleWideFileChange(e: Event) {
        const target = e.target as HTMLInputElement;
        const file = target.files?.[0];
        const key = 'middle_wide';
        delete uploadErrors[key];
        if (!file) return;
        if (file.size > 2 * 1024 * 1024) { uploadErrors[key] = 'Maks. 2MB.'; target.value = ''; return; }
        if (!await validateImageOrientation(file, 'landscape')) { uploadErrors[key] = 'Gambar harus lanskap (lebar > tinggi).'; target.value = ''; return; }
        form.middle_wide_file = file;
        form.middle_wide_banner.image = URL.createObjectURL(file);
    }

    async function handlePopupFileChange(e: Event) {
        const target = e.target as HTMLInputElement;
        const file = target.files?.[0];
        const key = 'popup';
        delete uploadErrors[key];
        if (!file) return;
        if (file.size > 2 * 1024 * 1024) { uploadErrors[key] = 'Maks. 2MB.'; target.value = ''; return; }
        const orient = form.popup_banner.orientation || 'portrait';
        if (!await validateImageOrientation(file, orient)) {
            uploadErrors[key] = orient === 'portrait' ? 'Gambar harus potret (tinggi > lebar).' : 'Gambar harus lanskap (lebar > tinggi).';
            target.value = ''; return;
        }
        form.popup_file = file;
        form.popup_banner.image = URL.createObjectURL(file);
    }

    function clearPopupBanner() {
        form.popup_banner.image = '';
        form.popup_file = null;
        delete uploadErrors['popup'];
    }

    function submit() {
        form.post('/admin/cms/banners', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                previewKey += 1;
                showToast('Banner berhasil diperbarui!', 'success');
            },
        });
    }

    function refreshPreview() { previewKey += 1; }
</script>

<svelte:head>
    <title>CMS Banner Manager</title>
</svelte:head>

<AdminLayout>
    <div class="flex-grow flex flex-col min-h-screen">
        <main class="flex-grow p-4 sm:p-8 w-full max-w-full mx-auto space-y-6">

            <!-- Page Header -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <div class="flex items-center gap-2.5 mb-1">
                        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-violet-500 to-purple-600 flex items-center justify-center shadow-sm shrink-0">
                            <i class="ti ti-photo-filled text-white text-base"></i>
                        </div>
                        <h1 class="font-outfit font-black text-2xl text-slate-800 tracking-tight">CMS Banner Manager</h1>
                    </div>
                    <p class="text-sm text-slate-500 font-medium ml-11">Kelola banner promosi halaman depan & lihat pratinjau mobile secara langsung.</p>
                </div>
                <button
                    onclick={submit}
                    disabled={form.processing}
                    class="w-full sm:w-auto px-5 py-2.5 text-white font-bold rounded-xl text-sm transition duration-200 shadow-lg font-outfit uppercase tracking-wider disabled:opacity-50 flex items-center justify-center gap-2 shrink-0"
                    style="background: linear-gradient(135deg, {primaryColor}, {primaryColor}cc);"
                >
                    {#if form.processing}
                        <span class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                        Menyimpan...
                    {:else}
                        <i class="ti ti-device-floppy text-base"></i>
                        Simpan Perubahan
                    {/if}
                </button>
            </div>

            <!-- Main Grid -->
            <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 items-start">

                <!-- Left: Banner Editors -->
                <div class="xl:col-span-7 space-y-5">

                    <!-- Hero Banners -->
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-100 bg-gradient-to-r from-blue-50 to-indigo-50/30 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center shadow-sm shrink-0">
                                    <i class="ti ti-slideshow text-white text-sm"></i>
                                </div>
                                <div>
                                    <h3 class="font-outfit font-black text-slate-800 text-sm">Main Hero Banners</h3>
                                    <p class="text-[11px] text-slate-400 font-medium">Banner slide utama (rasio 16:9 atau 21:9)</p>
                                </div>
                            </div>
                            <button type="button" onclick={addHeroBanner}
                                class="px-3 py-1.5 bg-white hover:bg-blue-50 text-blue-600 text-xs font-bold rounded-xl border border-blue-200 transition flex items-center gap-1.5 shadow-sm shrink-0">
                                <i class="ti ti-plus"></i> Tambah
                            </button>
                        </div>
                        <div class="p-5 space-y-4">
                            {#each form.hero_banners as banner, index}
                                <div class="p-4 bg-slate-50 rounded-xl border border-slate-200/60 relative group">
                                    <button type="button" onclick={() => removeHeroBanner(index)}
                                        class="absolute -top-2 -right-2 w-6 h-6 bg-rose-500 text-white rounded-full flex items-center justify-center shadow-md hover:bg-rose-600 transition z-10 opacity-0 group-hover:opacity-100"
                                        title="Hapus Banner">
                                        <i class="ti ti-x text-[10px]"></i>
                                    </button>
                                    <div class="flex items-center gap-2 mb-3">
                                        <span class="text-[10px] font-black text-blue-600 bg-blue-50 border border-blue-100 px-2 py-0.5 rounded-md uppercase tracking-wider font-outfit">Banner #{index + 1}</span>
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-4 items-start">
                                        <div class="sm:col-span-5">
                                            {#if banner.image}
                                                <div class="relative rounded-xl overflow-hidden aspect-[16/9] border border-slate-200 shadow-sm bg-white group/preview">
                                                    <img src={banner.image} alt="Preview" class="w-full h-full object-cover" />
                                                    <label class="absolute inset-0 bg-black/50 opacity-0 group-hover/preview:opacity-100 transition flex flex-col items-center justify-center cursor-pointer text-white gap-1">
                                                        <input type="file" accept="image/*" class="hidden" onchange={(e) => handleHeroFileChange(index, e)} />
                                                        <i class="ti ti-camera text-xl"></i>
                                                        <span class="text-[10px] font-bold">Ganti Gambar</span>
                                                    </label>
                                                </div>
                                            {:else}
                                                <label class="rounded-xl border-2 border-dashed border-slate-300 hover:border-blue-400 bg-white aspect-[16/9] flex flex-col items-center justify-center cursor-pointer transition group/drop hover:bg-blue-50/30">
                                                    <input type="file" accept="image/*" class="hidden" onchange={(e) => handleHeroFileChange(index, e)} />
                                                    <i class="ti ti-photo-up text-2xl text-slate-300 group-hover/drop:text-blue-400 transition mb-1"></i>
                                                    <span class="text-[10px] font-bold text-slate-400 group-hover/drop:text-blue-500">Klik untuk Upload</span>
                                                    <span class="text-[9px] text-slate-300 mt-0.5">Maks. 2MB · Lanskap</span>
                                                </label>
                                            {/if}
                                            {#if uploadErrors[`hero_${index}`] || form.errors[`hero_files.${index}`]}
                                                <div class="mt-1.5 text-[10px] font-bold text-rose-600 flex items-start gap-1 bg-rose-50 border border-rose-100 p-2 rounded-lg">
                                                    <i class="ti ti-alert-circle text-xs shrink-0 mt-0.5"></i>
                                                    <span>{uploadErrors[`hero_${index}`] || form.errors[`hero_files.${index}`]}</span>
                                                </div>
                                            {/if}
                                        </div>
                                        <div class="sm:col-span-7 space-y-3">
                                            <div>
                                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1">Alt Text</label>
                                                <input type="text" bind:value={banner.alt} placeholder="Contoh: Promo Spesial Elektronik 80%"
                                                    class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-400 transition" />
                                            </div>
                                            <div>
                                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1">Tautan URL</label>
                                                <div class="relative">
                                                    <i class="ti ti-link absolute left-3 top-1/2 -translate-y-1/2 text-slate-300 text-sm"></i>
                                                    <input type="text" bind:value={banner.link} placeholder="/search?category=elektronik"
                                                        class="w-full bg-white border border-slate-200 rounded-xl pl-8 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-400 transition" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            {/each}
                            {#if form.hero_banners.length === 0}
                                <div class="py-10 border-2 border-dashed border-slate-200 rounded-xl flex flex-col items-center justify-center gap-2">
                                    <i class="ti ti-photo text-3xl text-slate-300"></i>
                                    <p class="text-sm font-semibold text-slate-400">Belum ada banner utama</p>
                                    <button onclick={addHeroBanner} class="text-xs text-blue-500 font-bold hover:underline">+ Tambah sekarang</button>
                                </div>
                            {/if}
                        </div>
                    </div>

                    <!-- Side Banners -->
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-100 bg-gradient-to-r from-emerald-50 to-teal-50/30 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center shadow-sm shrink-0">
                                    <i class="ti ti-layout-sidebar text-white text-sm"></i>
                                </div>
                                <div>
                                    <h3 class="font-outfit font-black text-slate-800 text-sm">Side Promo Banners</h3>
                                    <p class="text-[11px] text-slate-400 font-medium">Banner kanan banner utama (desktop only)</p>
                                </div>
                            </div>
                            <button type="button" onclick={addSideBanner}
                                class="px-3 py-1.5 bg-white hover:bg-emerald-50 text-emerald-600 text-xs font-bold rounded-xl border border-emerald-200 transition flex items-center gap-1.5 shadow-sm shrink-0">
                                <i class="ti ti-plus"></i> Tambah
                            </button>
                        </div>
                        <div class="p-5 space-y-4">
                            {#each form.side_banners as banner, index}
                                <div class="p-4 bg-slate-50 rounded-xl border border-slate-200/60 relative group">
                                    <button type="button" onclick={() => removeSideBanner(index)}
                                        class="absolute -top-2 -right-2 w-6 h-6 bg-rose-500 text-white rounded-full flex items-center justify-center shadow-md hover:bg-rose-600 transition z-10 opacity-0 group-hover:opacity-100"
                                        title="Hapus Banner">
                                        <i class="ti ti-x text-[10px]"></i>
                                    </button>
                                    <div class="flex items-center gap-2 mb-3">
                                        <span class="text-[10px] font-black text-emerald-600 bg-emerald-50 border border-emerald-100 px-2 py-0.5 rounded-md uppercase tracking-wider font-outfit">Side #{index + 1}</span>
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-4 items-start">
                                        <div class="sm:col-span-4">
                                            {#if banner.image}
                                                <div class="relative rounded-xl overflow-hidden aspect-[3/4] border border-slate-200 shadow-sm bg-white group/preview max-w-[100px]">
                                                    <img src={banner.image} alt="Preview" class="w-full h-full object-cover" />
                                                    <label class="absolute inset-0 bg-black/50 opacity-0 group-hover/preview:opacity-100 transition flex flex-col items-center justify-center cursor-pointer text-white gap-1">
                                                        <input type="file" accept="image/*" class="hidden" onchange={(e) => handleSideFileChange(index, e)} />
                                                        <i class="ti ti-camera text-base"></i>
                                                        <span class="text-[9px] font-bold">Ganti</span>
                                                    </label>
                                                </div>
                                            {:else}
                                                <label class="rounded-xl border-2 border-dashed border-slate-300 hover:border-emerald-400 bg-white aspect-[3/4] flex flex-col items-center justify-center cursor-pointer transition group/drop hover:bg-emerald-50/30 max-w-[100px]">
                                                    <input type="file" accept="image/*" class="hidden" onchange={(e) => handleSideFileChange(index, e)} />
                                                    <i class="ti ti-photo-up text-lg text-slate-300 group-hover/drop:text-emerald-400 transition mb-1"></i>
                                                    <span class="text-[9px] font-bold text-slate-400 group-hover/drop:text-emerald-500 text-center leading-tight">Upload<br>Potret</span>
                                                </label>
                                            {/if}
                                            {#if uploadErrors[`side_${index}`] || form.errors[`side_files.${index}`]}
                                                <div class="mt-1.5 text-[10px] font-bold text-rose-600 flex items-start gap-1 bg-rose-50 border border-rose-100 p-2 rounded-lg">
                                                    <i class="ti ti-alert-circle text-xs shrink-0 mt-0.5"></i>
                                                    <span>{uploadErrors[`side_${index}`] || form.errors[`side_files.${index}`]}</span>
                                                </div>
                                            {/if}
                                        </div>
                                        <div class="sm:col-span-8 space-y-3">
                                            <div>
                                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1">Alt Text</label>
                                                <input type="text" bind:value={banner.alt} placeholder="Contoh: Gratis Ongkir Semua Produk"
                                                    class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 transition" />
                                            </div>
                                            <div>
                                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1">Tautan URL</label>
                                                <div class="relative">
                                                    <i class="ti ti-link absolute left-3 top-1/2 -translate-y-1/2 text-slate-300 text-sm"></i>
                                                    <input type="text" bind:value={banner.link} placeholder="/flash-sale atau #"
                                                        class="w-full bg-white border border-slate-200 rounded-xl pl-8 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 transition" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            {/each}
                            {#if form.side_banners.length === 0}
                                <div class="py-10 border-2 border-dashed border-slate-200 rounded-xl flex flex-col items-center justify-center gap-2">
                                    <i class="ti ti-layout-sidebar text-3xl text-slate-300"></i>
                                    <p class="text-sm font-semibold text-slate-400">Belum ada banner samping</p>
                                    <button onclick={addSideBanner} class="text-xs text-emerald-500 font-bold hover:underline">+ Tambah sekarang</button>
                                </div>
                            {/if}
                        </div>
                    </div>

                    <!-- Middle Wide Banner -->
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-100 bg-gradient-to-r from-amber-50 to-orange-50/30 flex items-center">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-amber-500 to-orange-500 flex items-center justify-center shadow-sm shrink-0">
                                    <i class="ti ti-panorama text-white text-sm"></i>
                                </div>
                                <div>
                                    <h3 class="font-outfit font-black text-slate-800 text-sm">Banner Lebar Tengah</h3>
                                    <p class="text-[11px] text-slate-400 font-medium">Banner memanjang tengah halaman (rasio 4.5:1)</p>
                                </div>
                            </div>
                        </div>
                        <div class="p-5">
                            <div class="p-4 bg-slate-50 rounded-xl border border-slate-200/60">
                                <div class="grid grid-cols-1 sm:grid-cols-12 gap-4 items-start">
                                    <div class="sm:col-span-5">
                                        {#if form.middle_wide_banner.image}
                                            <div class="relative rounded-xl overflow-hidden aspect-[4/1] border border-slate-200 shadow-sm bg-white group/preview">
                                                <img src={form.middle_wide_banner.image} alt="Preview" class="w-full h-full object-cover" />
                                                <label class="absolute inset-0 bg-black/50 opacity-0 group-hover/preview:opacity-100 transition flex flex-col items-center justify-center cursor-pointer text-white gap-1">
                                                    <input type="file" accept="image/*" class="hidden" onchange={handleMiddleWideFileChange} />
                                                    <i class="ti ti-camera text-xl"></i>
                                                    <span class="text-[10px] font-bold">Ganti Gambar</span>
                                                </label>
                                            </div>
                                        {:else}
                                            <label class="rounded-xl border-2 border-dashed border-slate-300 hover:border-amber-400 bg-white aspect-[4/1] flex flex-col items-center justify-center cursor-pointer transition group/drop hover:bg-amber-50/30">
                                                <input type="file" accept="image/*" class="hidden" onchange={handleMiddleWideFileChange} />
                                                <i class="ti ti-photo-up text-2xl text-slate-300 group-hover/drop:text-amber-400 transition mb-1"></i>
                                                <span class="text-[10px] font-bold text-slate-400 group-hover/drop:text-amber-500">Klik untuk Upload</span>
                                                <span class="text-[9px] text-slate-300 mt-0.5">Maks. 2MB · Lanskap</span>
                                            </label>
                                        {/if}
                                        {#if uploadErrors['middle_wide'] || form.errors['middle_wide_file']}
                                            <div class="mt-1.5 text-[10px] font-bold text-rose-600 flex items-start gap-1 bg-rose-50 border border-rose-100 p-2 rounded-lg">
                                                <i class="ti ti-alert-circle text-xs shrink-0 mt-0.5"></i>
                                                <span>{uploadErrors['middle_wide'] || form.errors['middle_wide_file']}</span>
                                            </div>
                                        {/if}
                                    </div>
                                    <div class="sm:col-span-7 space-y-3">
                                        <div>
                                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1">Alt Text</label>
                                            <input type="text" bind:value={form.middle_wide_banner.alt} placeholder="Contoh: Flash Sale Diskon Gila-gilaan"
                                                class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-200 focus:border-amber-400 transition" />
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1">Tautan URL</label>
                                            <div class="relative">
                                                <i class="ti ti-link absolute left-3 top-1/2 -translate-y-1/2 text-slate-300 text-sm"></i>
                                                <input type="text" bind:value={form.middle_wide_banner.link} placeholder="/flash-sale atau #"
                                                    class="w-full bg-white border border-slate-200 rounded-xl pl-8 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-200 focus:border-amber-400 transition" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Popup Banner -->
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-100 bg-gradient-to-r from-violet-50 to-purple-50/30 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-violet-500 to-purple-600 flex items-center justify-center shadow-sm shrink-0">
                                    <i class="ti ti-message-2 text-white text-sm"></i>
                                </div>
                                <div>
                                    <h3 class="font-outfit font-black text-slate-800 text-sm">Promo Popup Banner</h3>
                                    <p class="text-[11px] text-slate-400 font-medium">Popup otomatis setelah loading intro di beranda</p>
                                </div>
                            </div>
                            <label class="inline-flex items-center gap-2 cursor-pointer select-none shrink-0">
                                <span class="text-xs font-bold {form.popup_banner.is_active ? 'text-violet-600' : 'text-slate-400'}">
                                    {form.popup_banner.is_active ? 'Aktif' : 'Nonaktif'}
                                </span>
                                <div class="relative">
                                    <input type="checkbox" bind:checked={form.popup_banner.is_active} class="sr-only peer" />
                                    <div class="w-10 h-6 bg-slate-200 rounded-full peer peer-checked:bg-violet-500 transition-colors after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-4 after:shadow-sm relative"></div>
                                </div>
                            </label>
                        </div>
                        <div class="p-5">
                            <div class="p-4 bg-slate-50 rounded-xl border border-slate-200/60 relative group">
                                {#if form.popup_banner.image}
                                    <button type="button" onclick={clearPopupBanner}
                                        class="absolute -top-2 -right-2 w-6 h-6 bg-rose-500 text-white rounded-full flex items-center justify-center shadow-md hover:bg-rose-600 transition z-10 opacity-0 group-hover:opacity-100"
                                        title="Hapus Gambar Popup">
                                        <i class="ti ti-x text-[10px]"></i>
                                    </button>
                                {/if}
                                <div class="grid grid-cols-1 sm:grid-cols-12 gap-4 items-start">
                                    <div class="sm:col-span-4">
                                        {#if form.popup_banner.image}
                                            <div class="relative rounded-xl overflow-hidden border border-slate-200 shadow-sm bg-white group/preview max-w-[130px] mx-auto sm:mx-0 {form.popup_banner.orientation === 'landscape' ? 'aspect-[16/9]' : 'aspect-[4/5]'}">
                                                <img src={form.popup_banner.image} alt="Preview" class="w-full h-full object-cover" />
                                                <label class="absolute inset-0 bg-black/50 opacity-0 group-hover/preview:opacity-100 transition flex flex-col items-center justify-center cursor-pointer text-white gap-1">
                                                    <input type="file" accept="image/*" class="hidden" onchange={handlePopupFileChange} />
                                                    <i class="ti ti-camera text-xl"></i>
                                                    <span class="text-[10px] font-bold">Ganti</span>
                                                </label>
                                            </div>
                                        {:else}
                                            <label class="rounded-xl border-2 border-dashed border-slate-300 hover:border-violet-400 bg-white flex flex-col items-center justify-center cursor-pointer transition group/drop hover:bg-violet-50/30 max-w-[130px] mx-auto sm:mx-0 {form.popup_banner.orientation === 'landscape' ? 'aspect-[16/9]' : 'aspect-[4/5]'}">
                                                <input type="file" accept="image/*" class="hidden" onchange={handlePopupFileChange} />
                                                <i class="ti ti-photo-up text-xl text-slate-300 group-hover/drop:text-violet-400 transition mb-1"></i>
                                                <span class="text-[10px] font-bold text-slate-400 group-hover/drop:text-violet-500 text-center">Upload Popup</span>
                                            </label>
                                        {/if}
                                        {#if uploadErrors['popup'] || form.errors['popup_file']}
                                            <div class="mt-1.5 text-[10px] font-bold text-rose-600 flex items-start gap-1 bg-rose-50 border border-rose-100 p-2 rounded-lg max-w-[130px] mx-auto sm:mx-0">
                                                <i class="ti ti-alert-circle text-xs shrink-0 mt-0.5"></i>
                                                <span>{uploadErrors['popup'] || form.errors['popup_file']}</span>
                                            </div>
                                        {/if}
                                    </div>
                                    <div class="sm:col-span-8 space-y-3">
                                        <div>
                                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-2">Orientasi Gambar</label>
                                            <div class="grid grid-cols-2 gap-2">
                                                <label class="flex items-center gap-2 p-2.5 bg-white border rounded-xl cursor-pointer hover:bg-violet-50 transition {form.popup_banner.orientation === 'portrait' ? 'border-violet-400 bg-violet-50/50' : 'border-slate-200'}">
                                                    <input type="radio" name="popup_orientation" value="portrait" bind:group={form.popup_banner.orientation} class="text-violet-600 focus:ring-violet-500" />
                                                    <div>
                                                        <span class="text-xs font-bold text-slate-800 block">Potret</span>
                                                        <span class="text-[9px] text-slate-400">Rasio 4:5</span>
                                                    </div>
                                                </label>
                                                <label class="flex items-center gap-2 p-2.5 bg-white border rounded-xl cursor-pointer hover:bg-violet-50 transition {form.popup_banner.orientation === 'landscape' ? 'border-violet-400 bg-violet-50/50' : 'border-slate-200'}">
                                                    <input type="radio" name="popup_orientation" value="landscape" bind:group={form.popup_banner.orientation} class="text-violet-600 focus:ring-violet-500" />
                                                    <div>
                                                        <span class="text-xs font-bold text-slate-800 block">Lanskap</span>
                                                        <span class="text-[9px] text-slate-400">Rasio 16:9</span>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1">Alt Text</label>
                                            <input type="text" bind:value={form.popup_banner.alt} placeholder="Contoh: Popup Diskon Member Baru"
                                                class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-200 focus:border-violet-400 transition" />
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1">Tautan URL</label>
                                            <div class="relative">
                                                <i class="ti ti-link absolute left-3 top-1/2 -translate-y-1/2 text-slate-300 text-sm"></i>
                                                <input type="text" bind:value={form.popup_banner.link} placeholder="/flash-sale atau #"
                                                    class="w-full bg-white border border-slate-200 rounded-xl pl-8 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-200 focus:border-violet-400 transition" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Right: Live Mobile Preview -->
                <div class="xl:col-span-5 xl:sticky xl:top-8 space-y-4">
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                        <!-- Preview header -->
                        <div class="px-5 py-3.5 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-slate-100/50 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                                <h3 class="font-outfit font-black text-slate-700 text-xs uppercase tracking-wider">Pratinjau Mobile Live</h3>
                            </div>
                            <button type="button" onclick={refreshPreview}
                                class="p-1.5 bg-white border border-slate-200 rounded-lg text-slate-500 hover:bg-slate-50 shadow-sm transition flex items-center gap-1.5 text-xs font-bold">
                                <i class="ti ti-refresh text-sm"></i> Refresh
                            </button>
                        </div>

                        <!-- iPhone Mockup -->
                        <div class="p-6 flex justify-center bg-gradient-to-b from-slate-100 to-slate-200/60">
                            <div class="relative w-[360px] h-[720px] bg-slate-900 rounded-[42px] border-[10px] border-slate-900 shadow-2xl overflow-hidden ring-4 ring-slate-800 select-none">
                                <!-- Dynamic Island -->
                                <div class="absolute top-1.5 left-1/2 -translate-x-1/2 w-28 h-5.5 bg-black rounded-full z-50 flex items-center justify-center gap-1.5">
                                    <div class="w-2 h-2 rounded-full bg-slate-950/80"></div>
                                    <div class="w-1.5 h-1.5 rounded-full bg-slate-950/80"></div>
                                </div>
                                {#key previewKey}
                                    <iframe
                                        id="storefront-preview"
                                        src={storefrontUrl}
                                        class="w-full h-full border-none bg-white z-10 pt-7"
                                        title="Live Storefront Mobile Preview"
                                    ></iframe>
                                {/key}
                                <!-- Home Bar -->
                                <div class="absolute bottom-1 left-1/2 -translate-x-1/2 w-24 h-1 bg-black/60 rounded-full z-50"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Info -->
                    <div class="bg-blue-50 border border-blue-100 rounded-2xl p-4">
                        <h4 class="font-bold flex items-center gap-1.5 mb-2 text-[11px] uppercase tracking-wider text-blue-600">
                            <i class="ti ti-info-circle text-sm"></i> Cara Kerja Pratinjau
                        </h4>
                        <p class="text-xs text-blue-700 leading-relaxed">
                            Pratinjau memuat halaman depan toko secara interaktif. Klik
                            <strong>Simpan Perubahan</strong> untuk mempublikasikan dan melihat langsung efek banner di mockup.
                        </p>
                    </div>

                    <!-- Save button (mobile only) -->
                    <div class="xl:hidden">
                        <button onclick={submit} disabled={form.processing}
                            class="w-full px-6 py-3 text-white font-bold rounded-xl text-sm transition duration-200 shadow-lg font-outfit uppercase tracking-wider disabled:opacity-50 flex items-center justify-center gap-2"
                            style="background: linear-gradient(135deg, {primaryColor}, {primaryColor}cc);">
                            {#if form.processing}
                                <span class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                                Menyimpan...
                            {:else}
                                <i class="ti ti-device-floppy text-base"></i>
                                Simpan Perubahan
                            {/if}
                        </button>
                    </div>
                </div>

            </div>
        </main>
    </div>
</AdminLayout>
