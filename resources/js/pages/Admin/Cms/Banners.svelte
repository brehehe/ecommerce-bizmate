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
    const secondaryColor = $derived(
        page.props.theme?.secondary_color || '#fa7315',
    );

    // Create the Inertia form
    // svelte-ignore state_referenced_locally
    const form = useForm({
        hero_banners:
            heroBanners.length > 0
                ? [...heroBanners]
                : [
                      {
                          image: '/banners/promo-main.png',
                          alt: 'Promo Spesial',
                          link: '#',
                      },
                  ],
        side_banners:
            sideBanners.length > 0
                ? [...sideBanners]
                : [
                      {
                          image: '/banners/fashion.png',
                          alt: 'Fashion Diskon',
                          link: '#',
                      },
                  ],
        middle_wide_banner: middleWideBanner || {
            image: '/banners/flash-sale.png',
            alt: 'Flash Sale Promo',
            link: '#',
        },
        popup_banner: popupBanner
            ? {
                  image: popupBanner.image || '',
                  alt: popupBanner.alt || 'Promo Spesial',
                  link: popupBanner.link || '#',
                  is_active: !!popupBanner.is_active,
                  orientation: popupBanner.orientation || 'portrait',
              }
            : {
                  image: '',
                  alt: 'Promo Spesial',
                  link: '#',
                  is_active: false,
                  orientation: 'portrait',
              },
        hero_files: {} as Record<number, File>,
        side_files: {} as Record<number, File>,
        middle_wide_file: null as File | null,
        popup_file: null as File | null,
    });

    let iframeElement: HTMLIFrameElement;
    let previewKey = $state(0);

    function addHeroBanner() {
        form.hero_banners = [
            ...form.hero_banners,
            { image: '', alt: 'Banner Baru', link: '#' },
        ];
    }

    function removeHeroBanner(index: number) {
        form.hero_banners = form.hero_banners.filter((_, i) => i !== index);
        // Clear file reference if any
        if (form.hero_files[index]) {
            const newFiles = { ...form.hero_files };
            delete newFiles[index];
            form.hero_files = newFiles;
        }
    }

    function addSideBanner() {
        form.side_banners = [
            ...form.side_banners,
            { image: '', alt: 'Side Banner Baru', link: '#' },
        ];
    }

    function removeSideBanner(index: number) {
        form.side_banners = form.side_banners.filter((_, i) => i !== index);
        // Clear file reference if any
        if (form.side_files[index]) {
            const newFiles = { ...form.side_files };
            delete newFiles[index];
            form.side_files = newFiles;
        }
    }

    let uploadErrors = $state<Record<string, string>>({});

    function validateImageOrientation(
        file: File,
        expected: 'landscape' | 'portrait',
    ): Promise<boolean> {
        return new Promise((resolve) => {
            const img = new Image();
            img.src = URL.createObjectURL(file);
            img.onload = () => {
                const width = img.width;
                const height = img.height;
                URL.revokeObjectURL(img.src);

                if (expected === 'landscape' && width <= height) {
                    resolve(false);
                } else if (expected === 'portrait' && height <= width) {
                    resolve(false);
                } else {
                    resolve(true);
                }
            };
            img.onerror = () => {
                resolve(false);
            };
        });
    }

    async function handleHeroFileChange(index: number, e: Event) {
        const target = e.target as HTMLInputElement;
        const file = target.files?.[0];
        const key = `hero_${index}`;
        delete uploadErrors[key];

        if (file) {
            if (file.size > 2 * 1024 * 1024) {
                uploadErrors[key] =
                    'Ukuran gambar banner tidak boleh melebihi 2MB.';
                target.value = '';
                return;
            }

            const isValid = await validateImageOrientation(file, 'landscape');
            if (!isValid) {
                uploadErrors[key] = 'Gambar harus lanskap (lebar > tinggi).';
                target.value = '';
                return;
            }

            form.hero_files[index] = file;
            form.hero_banners[index].image = URL.createObjectURL(file);
        }
    }

    async function handleSideFileChange(index: number, e: Event) {
        const target = e.target as HTMLInputElement;
        const file = target.files?.[0];
        const key = `side_${index}`;
        delete uploadErrors[key];

        if (file) {
            if (file.size > 2 * 1024 * 1024) {
                uploadErrors[key] =
                    'Ukuran gambar banner tidak boleh melebihi 2MB.';
                target.value = '';
                return;
            }

            const isValid = await validateImageOrientation(file, 'portrait');
            if (!isValid) {
                uploadErrors[key] = 'Gambar harus potret (tinggi > lebar).';
                target.value = '';
                return;
            }

            form.side_files[index] = file;
            form.side_banners[index].image = URL.createObjectURL(file);
        }
    }

    async function handleMiddleWideFileChange(e: Event) {
        const target = e.target as HTMLInputElement;
        const file = target.files?.[0];
        const key = 'middle_wide';
        delete uploadErrors[key];

        if (file) {
            if (file.size > 2 * 1024 * 1024) {
                uploadErrors[key] =
                    'Ukuran gambar banner tidak boleh melebihi 2MB.';
                target.value = '';
                return;
            }

            const isValid = await validateImageOrientation(file, 'landscape');
            if (!isValid) {
                uploadErrors[key] = 'Gambar harus lanskap (lebar > tinggi).';
                target.value = '';
                return;
            }

            form.middle_wide_file = file;
            form.middle_wide_banner.image = URL.createObjectURL(file);
        }
    }

    async function handlePopupFileChange(e: Event) {
        const target = e.target as HTMLInputElement;
        const file = target.files?.[0];
        const key = 'popup';
        delete uploadErrors[key];

        if (file) {
            if (file.size > 2 * 1024 * 1024) {
                uploadErrors[key] =
                    'Ukuran gambar banner tidak boleh melebihi 2MB.';
                target.value = '';
                return;
            }

            const expectedOrientation =
                form.popup_banner.orientation || 'portrait';
            const isValid = await validateImageOrientation(
                file,
                expectedOrientation,
            );
            if (!isValid) {
                const message =
                    expectedOrientation === 'portrait'
                        ? 'Gambar harus potret (tinggi > lebar).'
                        : 'Gambar harus lanskap (lebar > tinggi).';
                uploadErrors[key] = message;
                target.value = '';
                return;
            }

            form.popup_file = file;
            form.popup_banner.image = URL.createObjectURL(file);
        }
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
                // Refresh preview iframe
                previewKey += 1;
            },
        });
    }

    function refreshPreview() {
        previewKey += 1;
    }
</script>

<svelte:head>
    <title>CMS Banner Manager</title>
</svelte:head>

<AdminLayout>
    <main class="flex-grow p-4 sm:p-8 w-full max-w-[1600px] mx-auto space-y-8">
        <!-- Page Header -->
        <div
            class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4"
        >
            <div>
                <h1
                    class="font-outfit font-black text-2xl sm:text-3xl text-slate-800 tracking-tight"
                >
                    CMS Banner Manager
                </h1>
                <p class="text-sm text-slate-500 font-medium mt-1">
                    Kelola banner promosi halaman depan toko Anda dan lihat
                    pratinjau tampilan mobile secara langsung.
                </p>
            </div>

            <button
                onclick={submit}
                disabled={form.processing}
                class="w-full sm:w-auto px-6 py-3 text-white font-bold rounded-xl text-sm transition duration-200 shadow-lg font-outfit uppercase tracking-wider disabled:opacity-50 flex items-center justify-center gap-2"
                style="background-color: {primaryColor};"
            >
                {#if form.processing}
                    <span
                        class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"
                    ></span>
                    Menyimpan...
                {:else}
                    <i class="ti ti-device-floppy text-base"></i> Simpan Perubahan
                {/if}
            </button>
        </div>

        {#if form.wasSuccessful}
            <div
                class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl flex items-center gap-3"
            >
                <i class="ti ti-circle-check text-xl"></i>
                <span class="text-sm font-bold"
                    >Banner berhasil diperbarui! Tampilan preview mobile telah
                    disinkronkan.</span
                >
            </div>
        {/if}

        <!-- Split Screen Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            <!-- Left Panel: Banner Editors (8 Cols) -->
            <div class="lg:col-span-7 space-y-8">
                <!-- Hero Banners Section -->
                <div
                    class="bg-white rounded-3xl border border-slate-200 shadow-soft p-6 space-y-6"
                >
                    <div
                        class="flex justify-between items-center border-b border-slate-100 pb-4"
                    >
                        <div>
                            <h3
                                class="font-outfit font-black text-lg text-slate-800"
                            >
                                Main Hero Banners
                            </h3>
                            <p class="text-xs text-slate-500 font-medium">
                                Banner slide besar utama di halaman teratas
                                (Rasio 16:9 atau 21:9 disarankan)
                            </p>
                        </div>
                        <button
                            type="button"
                            onclick={addHeroBanner}
                            class="px-4 py-2 bg-slate-50 hover:bg-slate-100 text-slate-700 text-xs font-bold rounded-xl border border-slate-200 transition flex items-center gap-1.5"
                        >
                            <i class="ti ti-plus"></i> Tambah Banner
                        </button>
                    </div>

                    <div class="space-y-6">
                        {#each form.hero_banners as banner, index}
                            <div
                                class="p-5 bg-slate-50/50 rounded-2xl border border-slate-200/60 relative group space-y-4"
                            >
                                <button
                                    type="button"
                                    onclick={() => removeHeroBanner(index)}
                                    class="absolute -top-2.5 -right-2.5 w-7 h-7 bg-rose-50 border border-rose-200 text-rose-600 rounded-full flex items-center justify-center shadow-sm hover:bg-rose-100 transition z-10"
                                    title="Hapus Banner"
                                >
                                    <i class="ti ti-trash text-sm"></i>
                                </button>

                                <div
                                    class="grid grid-cols-1 md:grid-cols-12 gap-4 items-center"
                                >
                                    <!-- Image Preview / File Dropzone -->
                                    <div class="md:col-span-4">
                                        {#if banner.image}
                                            <div
                                                class="relative rounded-xl overflow-hidden aspect-[16/9] border border-slate-200 shadow-sm bg-white group/preview"
                                            >
                                                <img
                                                    src={banner.image}
                                                    alt="Preview"
                                                    class="w-full h-full object-cover"
                                                />
                                                <label
                                                    class="absolute inset-0 bg-black/50 opacity-0 group-hover/preview:opacity-100 transition flex items-center justify-center cursor-pointer text-white font-bold text-xs gap-1.5"
                                                >
                                                    <input
                                                        type="file"
                                                        accept="image/*"
                                                        class="hidden"
                                                        onchange={(e) =>
                                                            handleHeroFileChange(
                                                                index,
                                                                e,
                                                            )}
                                                    />
                                                    <i class="ti ti-camera"></i> Ganti
                                                    Gambar
                                                </label>
                                            </div>
                                        {:else}
                                            <label
                                                class="rounded-xl border-2 border-dashed border-slate-300 hover:border-slate-400 bg-white aspect-[16/9] flex flex-col items-center justify-center cursor-pointer p-4 transition group/drop"
                                            >
                                                <input
                                                    type="file"
                                                    accept="image/*"
                                                    class="hidden"
                                                    onchange={(e) =>
                                                        handleHeroFileChange(
                                                            index,
                                                            e,
                                                        )}
                                                />
                                                <i
                                                    class="ti ti-photo-up text-2xl text-slate-400 group-hover/drop:scale-110 transition"
                                                ></i>
                                                <span
                                                    class="text-[10px] font-bold text-slate-500 mt-2 text-center"
                                                    >Upload Banner</span
                                                >
                                            </label>
                                        {/if}
                                        {#if uploadErrors[`hero_${index}`] || form.errors[`hero_files.${index}`]}
                                            <div
                                                class="mt-2 text-[10px] font-bold text-rose-500 flex items-start gap-1 bg-rose-50 border border-rose-100 p-2 rounded-lg"
                                            >
                                                <i
                                                    class="ti ti-alert-circle text-xs shrink-0 mt-0.5"
                                                ></i>
                                                <span
                                                    >{uploadErrors[
                                                        `hero_${index}`
                                                    ] ||
                                                        form.errors[
                                                            `hero_files.${index}`
                                                        ]}</span
                                                >
                                            </div>
                                        {/if}
                                    </div>

                                    <!-- Fields -->
                                    <div class="md:col-span-8 space-y-3">
                                        <div>
                                            <p
                                                class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1"
                                            >
                                                Alt Text (Deskripsi Gambar)
                                            </p>
                                            <input
                                                type="text"
                                                bind:value={banner.alt}
                                                placeholder="Contoh: Promo Spesial Elektronik 80%"
                                                class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-2 transition"
                                            />
                                        </div>
                                        <div>
                                            <p
                                                class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1"
                                            >
                                                Tautan URL (Link)
                                            </p>
                                            <input
                                                type="text"
                                                bind:value={banner.link}
                                                placeholder="Contoh: /search?category=elektronik atau #"
                                                class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-2 transition"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        {/each}

                        {#if form.hero_banners.length === 0}
                            <div
                                class="py-12 border-2 border-dashed border-slate-200 rounded-2xl flex flex-col items-center justify-center text-slate-400"
                            >
                                <i class="ti ti-photo text-4xl mb-2"></i>
                                <p class="text-sm font-semibold">
                                    Tidak ada banner utama. Silakan tambahkan
                                    banner baru.
                                </p>
                            </div>
                        {/if}
                    </div>
                </div>

                <!-- Side Banners Section -->
                <div
                    class="bg-white rounded-3xl border border-slate-200 shadow-soft p-6 space-y-6"
                >
                    <div
                        class="flex justify-between items-center border-b border-slate-100 pb-4"
                    >
                        <div>
                            <h3
                                class="font-outfit font-black text-lg text-slate-800"
                            >
                                Side Promos Banners
                            </h3>
                            <p class="text-xs text-slate-500 font-medium">
                                Banner kecil di sebelah kanan banner utama
                                (hanya tampil pada layar lebar/desktop)
                            </p>
                        </div>
                        <button
                            type="button"
                            onclick={addSideBanner}
                            class="px-4 py-2 bg-slate-50 hover:bg-slate-100 text-slate-700 text-xs font-bold rounded-xl border border-slate-200 transition flex items-center gap-1.5"
                        >
                            <i class="ti ti-plus"></i> Tambah Banner
                        </button>
                    </div>

                    <div class="space-y-6">
                        {#each form.side_banners as banner, index}
                            <div
                                class="p-5 bg-slate-50/50 rounded-2xl border border-slate-200/60 relative group space-y-4"
                            >
                                <button
                                    type="button"
                                    onclick={() => removeSideBanner(index)}
                                    class="absolute -top-2.5 -right-2.5 w-7 h-7 bg-rose-50 border border-rose-200 text-rose-600 rounded-full flex items-center justify-center shadow-sm hover:bg-rose-100 transition z-10"
                                    title="Hapus Banner"
                                >
                                    <i class="ti ti-trash text-sm"></i>
                                </button>

                                <div
                                    class="grid grid-cols-1 md:grid-cols-12 gap-4 items-center"
                                >
                                    <!-- Image Preview / File Dropzone -->
                                    <div class="md:col-span-4">
                                        {#if banner.image}
                                            <div
                                                class="relative rounded-xl overflow-hidden aspect-[2.1/1] border border-slate-200 shadow-sm bg-white group/preview"
                                            >
                                                <img
                                                    src={banner.image}
                                                    alt="Preview"
                                                    class="w-full h-full object-cover"
                                                />
                                                <label
                                                    class="absolute inset-0 bg-black/50 opacity-0 group-hover/preview:opacity-100 transition flex items-center justify-center cursor-pointer text-white font-bold text-xs gap-1.5"
                                                >
                                                    <input
                                                        type="file"
                                                        accept="image/*"
                                                        class="hidden"
                                                        onchange={(e) =>
                                                            handleSideFileChange(
                                                                index,
                                                                e,
                                                            )}
                                                    />
                                                    <i class="ti ti-camera"></i> Ganti
                                                    Gambar
                                                </label>
                                            </div>
                                        {:else}
                                            <label
                                                class="rounded-xl border-2 border-dashed border-slate-300 hover:border-slate-400 bg-white aspect-[2.1/1] flex flex-col items-center justify-center cursor-pointer p-4 transition group/drop"
                                            >
                                                <input
                                                    type="file"
                                                    accept="image/*"
                                                    class="hidden"
                                                    onchange={(e) =>
                                                        handleSideFileChange(
                                                            index,
                                                            e,
                                                        )}
                                                />
                                                <i
                                                    class="ti ti-photo-up text-2xl text-slate-400 group-hover/drop:scale-110 transition"
                                                ></i>
                                                <span
                                                    class="text-[10px] font-bold text-slate-500 mt-2 text-center"
                                                    >Upload Banner</span
                                                >
                                            </label>
                                        {/if}
                                        {#if uploadErrors[`side_${index}`] || form.errors[`side_files.${index}`]}
                                            <div
                                                class="mt-2 text-[10px] font-bold text-rose-500 flex items-start gap-1 bg-rose-50 border border-rose-100 p-2 rounded-lg"
                                            >
                                                <i
                                                    class="ti ti-alert-circle text-xs shrink-0 mt-0.5"
                                                ></i>
                                                <span
                                                    >{uploadErrors[
                                                        `side_${index}`
                                                    ] ||
                                                        form.errors[
                                                            `side_files.${index}`
                                                        ]}</span
                                                >
                                            </div>
                                        {/if}
                                    </div>

                                    <!-- Fields -->
                                    <div class="md:col-span-8 space-y-3">
                                        <div>
                                            <p
                                                class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1"
                                            >
                                                Alt Text (Deskripsi Gambar)
                                            </p>
                                            <input
                                                type="text"
                                                bind:value={banner.alt}
                                                placeholder="Contoh: Gratis Ongkir Semua Produk"
                                                class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-2 transition"
                                            />
                                        </div>
                                        <div>
                                            <p
                                                class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1"
                                            >
                                                Tautan URL (Link)
                                            </p>
                                            <input
                                                type="text"
                                                bind:value={banner.link}
                                                placeholder="Contoh: /flash-sale atau #"
                                                class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-2 transition"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        {/each}

                        {#if form.side_banners.length === 0}
                            <div
                                class="py-12 border-2 border-dashed border-slate-200 rounded-2xl flex flex-col items-center justify-center text-slate-400"
                            >
                                <i class="ti ti-photo text-4xl mb-2"></i>
                                <p class="text-sm font-semibold">
                                    Tidak ada banner samping. Silakan tambahkan
                                    banner baru.
                                </p>
                            </div>
                        {/if}
                    </div>
                </div>

                <!-- Middle Wide Banner Section -->
                <div
                    class="bg-white rounded-3xl border border-slate-200 shadow-soft p-6 space-y-6"
                >
                    <div
                        class="flex justify-between items-center border-b border-slate-100 pb-4"
                    >
                        <div>
                            <h3
                                class="font-outfit font-black text-lg text-slate-800"
                            >
                                Banner Lebar Tengah
                            </h3>
                            <p class="text-xs text-slate-500 font-medium">
                                Banner memanjang di tengah halaman (di atas
                                rekomendasi produk, Rasio disarankan 4.5:1)
                            </p>
                        </div>
                    </div>

                    <div
                        class="p-5 bg-slate-50/50 rounded-2xl border border-slate-200/60 relative group space-y-4"
                    >
                        <div
                            class="grid grid-cols-1 md:grid-cols-12 gap-4 items-center"
                        >
                            <!-- Image Preview / File Dropzone -->
                            <div class="md:col-span-4">
                                {#if form.middle_wide_banner.image}
                                    <div
                                        class="relative rounded-xl overflow-hidden aspect-[3.5/1] border border-slate-200 shadow-sm bg-white group/preview"
                                    >
                                        <img
                                            src={form.middle_wide_banner.image}
                                            alt="Preview"
                                            class="w-full h-full object-cover"
                                        />
                                        <label
                                            class="absolute inset-0 bg-black/50 opacity-0 group-hover/preview:opacity-100 transition flex items-center justify-center cursor-pointer text-white font-bold text-xs gap-1.5"
                                        >
                                            <input
                                                type="file"
                                                accept="image/*"
                                                class="hidden"
                                                onchange={handleMiddleWideFileChange}
                                            />
                                            <i class="ti ti-camera"></i> Ganti Gambar
                                        </label>
                                    </div>
                                {:else}
                                    <label
                                        class="rounded-xl border-2 border-dashed border-slate-300 hover:border-slate-400 bg-white aspect-[3.5/1] flex flex-col items-center justify-center cursor-pointer p-4 transition group/drop"
                                    >
                                        <input
                                            type="file"
                                            accept="image/*"
                                            class="hidden"
                                            onchange={handleMiddleWideFileChange}
                                        />
                                        <i
                                            class="ti ti-photo-up text-2xl text-slate-400 group-hover/drop:scale-110 transition"
                                        ></i>
                                        <span
                                            class="text-[10px] font-bold text-slate-500 mt-2 text-center"
                                            >Upload Banner</span
                                        >
                                    </label>
                                {/if}
                                {#if uploadErrors['middle_wide'] || form.errors['middle_wide_file']}
                                    <div
                                        class="mt-2 text-[10px] font-bold text-rose-500 flex items-start gap-1 bg-rose-50 border border-rose-100 p-2 rounded-lg"
                                    >
                                        <i
                                            class="ti ti-alert-circle text-xs shrink-0 mt-0.5"
                                        ></i>
                                        <span
                                            >{uploadErrors['middle_wide'] ||
                                                form.errors[
                                                    'middle_wide_file'
                                                ]}</span
                                        >
                                    </div>
                                {/if}
                            </div>

                            <!-- Fields -->
                            <div class="md:col-span-8 space-y-3">
                                <div>
                                    <p
                                        class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1"
                                    >
                                        Alt Text (Deskripsi Gambar)
                                    </p>
                                    <input
                                        type="text"
                                        bind:value={form.middle_wide_banner.alt}
                                        placeholder="Contoh: Flash Sale Diskon Gila-gilaan"
                                        class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-2 transition"
                                    />
                                </div>
                                <div>
                                    <p
                                        class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1"
                                    >
                                        Tautan URL (Link)
                                    </p>
                                    <input
                                        type="text"
                                        bind:value={
                                            form.middle_wide_banner.link
                                        }
                                        placeholder="Contoh: /flash-sale atau #"
                                        class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-2 transition"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Promo Popup Banner Section -->
                <div
                    class="bg-white rounded-3xl border border-slate-200 shadow-soft p-6 space-y-6"
                >
                    <div
                        class="flex justify-between items-center border-b border-slate-100 pb-4"
                    >
                        <div>
                            <h3
                                class="font-outfit font-black text-lg text-slate-800"
                            >
                                Promo Popup Banner
                            </h3>
                            <p class="text-xs text-slate-500 font-medium">
                                Banner popup gambar yang muncul otomatis setelah
                                loading intro selesai di beranda.
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <label
                                class="inline-flex items-center cursor-pointer select-none"
                            >
                                <input
                                    type="checkbox"
                                    bind:checked={form.popup_banner.is_active}
                                    class="sr-only peer"
                                />
                                <div
                                    class="w-10 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500 relative"
                                ></div>
                                <span
                                    class="ml-2 text-xs font-bold text-slate-600 uppercase tracking-wider"
                                >
                                    {form.popup_banner.is_active
                                        ? 'Aktif'
                                        : 'Nonaktif'}
                                </span>
                            </label>
                        </div>
                    </div>

                    <div
                        class="p-5 bg-slate-50/50 rounded-2xl border border-slate-200/60 relative group space-y-4"
                    >
                        {#if form.popup_banner.image}
                            <button
                                type="button"
                                onclick={clearPopupBanner}
                                class="absolute -top-2.5 -right-2.5 w-7 h-7 bg-rose-50 border border-rose-200 text-rose-600 rounded-full flex items-center justify-center shadow-sm hover:bg-rose-100 transition z-10"
                                title="Hapus Gambar Popup"
                            >
                                <i class="ti ti-trash text-sm"></i>
                            </button>
                        {/if}

                        <div
                            class="grid grid-cols-1 md:grid-cols-12 gap-4 items-center"
                        >
                            <!-- Image Preview / File Dropzone -->
                            <div class="md:col-span-4">
                                {#if form.popup_banner.image}
                                    <div
                                        class="relative rounded-xl overflow-hidden border border-slate-200 shadow-sm bg-white group/preview max-w-[150px] mx-auto md:mx-0 {form
                                            .popup_banner.orientation ===
                                        'landscape'
                                            ? 'aspect-[16/9]'
                                            : 'aspect-[4/5]'}"
                                    >
                                        <img
                                            src={form.popup_banner.image}
                                            alt="Preview"
                                            class="w-full h-full object-cover"
                                        />
                                        <label
                                            class="absolute inset-0 bg-black/50 opacity-0 group-hover/preview:opacity-100 transition flex items-center justify-center cursor-pointer text-white font-bold text-xs gap-1.5"
                                        >
                                            <input
                                                type="file"
                                                accept="image/*"
                                                class="hidden"
                                                onchange={handlePopupFileChange}
                                            />
                                            <i class="ti ti-camera"></i> Ganti Gambar
                                        </label>
                                    </div>
                                {:else}
                                    <label
                                        class="rounded-xl border-2 border-dashed border-slate-300 hover:border-slate-400 bg-white flex flex-col items-center justify-center cursor-pointer p-4 transition group/drop max-w-[150px] mx-auto md:mx-0 {form
                                            .popup_banner.orientation ===
                                        'landscape'
                                            ? 'aspect-[16/9]'
                                            : 'aspect-[4/5]'}"
                                    >
                                        <input
                                            type="file"
                                            accept="image/*"
                                            class="hidden"
                                            onchange={handlePopupFileChange}
                                        />
                                        <i
                                            class="ti ti-photo-up text-2xl text-slate-400 group-hover/drop:scale-110 transition"
                                        ></i>
                                        <span
                                            class="text-[10px] font-bold text-slate-500 mt-2 text-center"
                                            >Upload Popup</span
                                        >
                                    </label>
                                {/if}
                                {#if uploadErrors['popup'] || form.errors['popup_file']}
                                    <div
                                        class="mt-2 text-[10px] font-bold text-rose-500 flex items-start gap-1 bg-rose-50 border border-rose-100 p-2 rounded-lg max-w-[150px] mx-auto md:mx-0"
                                    >
                                        <i
                                            class="ti ti-alert-circle text-xs shrink-0 mt-0.5"
                                        ></i>
                                        <span
                                            >{uploadErrors['popup'] ||
                                                form.errors['popup_file']}</span
                                        >
                                    </div>
                                {/if}
                            </div>

                            <!-- Fields -->
                            <div class="md:col-span-8 space-y-3">
                                <div>
                                    <p
                                        class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1"
                                    >
                                        Orientasi Gambar Popup
                                    </p>
                                    <div class="grid grid-cols-2 gap-3 mb-2">
                                        <label
                                            class="flex items-center gap-2 p-2.5 bg-white border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-50 transition"
                                        >
                                            <input
                                                type="radio"
                                                name="popup_orientation"
                                                value="portrait"
                                                bind:group={
                                                    form.popup_banner
                                                        .orientation
                                                }
                                                class="text-blue-600 focus:ring-blue-500"
                                            />
                                            <div class="flex flex-col">
                                                <span
                                                    class="text-xs font-bold text-slate-800"
                                                    >Potret</span
                                                >
                                                <span
                                                    class="text-[9px] text-slate-400"
                                                    >Rasio 4:5 (Vertikal)</span
                                                >
                                            </div>
                                        </label>
                                        <label
                                            class="flex items-center gap-2 p-2.5 bg-white border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-50 transition"
                                        >
                                            <input
                                                type="radio"
                                                name="popup_orientation"
                                                value="landscape"
                                                bind:group={
                                                    form.popup_banner
                                                        .orientation
                                                }
                                                class="text-blue-600 focus:ring-blue-500"
                                            />
                                            <div class="flex flex-col">
                                                <span
                                                    class="text-xs font-bold text-slate-800"
                                                    >Lanskap</span
                                                >
                                                <span
                                                    class="text-[9px] text-slate-400"
                                                    >Rasio 16:9 (Horizontal)</span
                                                >
                                            </div>
                                        </label>
                                    </div>
                                </div>
                                <div>
                                    <p
                                        class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1"
                                    >
                                        Alt Text (Deskripsi Gambar)
                                    </p>
                                    <input
                                        type="text"
                                        bind:value={form.popup_banner.alt}
                                        placeholder="Contoh: Popup Diskon Member Baru"
                                        class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-2 transition"
                                    />
                                </div>
                                <div>
                                    <p
                                        class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1"
                                    >
                                        Tautan URL (Link)
                                    </p>
                                    <input
                                        type="text"
                                        bind:value={form.popup_banner.link}
                                        placeholder="Contoh: /flash-sale atau #"
                                        class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-2 transition"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Panel: Live Mobile Mockup Preview (5 Cols) -->
            <div class="lg:col-span-5 sticky top-8 space-y-4">
                <div class="flex justify-between items-center px-2">
                    <h3
                        class="font-outfit font-black text-slate-800 text-sm uppercase tracking-wider flex items-center gap-2"
                    >
                        <span
                            class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"
                        ></span>
                        Pratinjau Mobile Live
                    </h3>
                    <button
                        type="button"
                        onclick={refreshPreview}
                        class="p-2 bg-white border border-slate-200 rounded-xl text-slate-600 hover:bg-slate-50 shadow-sm transition flex items-center justify-center gap-1.5 text-xs font-bold"
                        title="Segarkan Pratinjau"
                    >
                        <i class="ti ti-refresh text-sm"></i> Refresh Preview
                    </button>
                </div>

                <!-- iPhone Frame Wrapper -->
                <div class="flex justify-center w-full">
                    <div
                        class="relative w-[340px] sm:w-[360px] h-[680px] bg-slate-900 rounded-[50px] border-[12px] border-slate-900 shadow-2xl overflow-hidden ring-[6px] ring-slate-800 flex flex-col items-center select-none"
                    >
                        <!-- iPhone Notch / Dynamic Island -->
                        <div
                            class="absolute top-2 w-28 h-6 bg-black rounded-full z-50 flex items-center justify-center"
                        >
                            <!-- Camera / Sensor dots -->
                            <div
                                class="w-2.5 h-2.5 rounded-full bg-slate-950/80 mr-3"
                            ></div>
                            <div
                                class="w-1.5 h-1.5 rounded-full bg-slate-950/80"
                            ></div>
                        </div>

                        <!-- iPhone Speaker Bar -->
                        <div
                            class="absolute top-0 w-16 h-1 bg-black rounded-b-md z-50"
                        ></div>

                        <!-- Iframe Content -->
                        {#key previewKey}
                            <iframe
                                id="storefront-preview"
                                src={storefrontUrl}
                                class="w-full h-full border-none bg-white z-10 pt-8"
                                title="Live Storefront Homepage Mobile Preview"
                            ></iframe>
                        {/key}

                        <!-- Home Indicator Bar at the bottom -->
                        <div
                            class="absolute bottom-1.5 w-32 h-1.5 bg-black rounded-full z-50"
                        ></div>
                    </div>
                </div>

                <div
                    class="bg-blue-50 border border-blue-100 rounded-2xl p-4 text-blue-800 text-xs leading-normal"
                >
                    <h4
                        class="font-bold flex items-center gap-1.5 mb-1 text-[11px] uppercase tracking-wider"
                    >
                        <i class="ti ti-info-circle text-sm"></i> Cara Kerja Pratinjau
                    </h4>
                    Pratinjau ini memuat halaman depan toko Anda secara interaktif.
                    Klik tombol<strong>Simpan Perubahan</strong> di atas untuk mempublikasikan
                    dan melihat langsung efek banner baru Anda di mockup.
                </div>
            </div>
        </div>
    </main>
</AdminLayout>

<style>
    /* Custom Scrollbar styling */
    :global(.scrollbar-thin::-webkit-scrollbar) {
        height: 6px;
    }
    :global(.scrollbar-thin::-webkit-scrollbar-track) {
        background: #f1f5f9;
        border-radius: 9999px;
    }
    :global(.scrollbar-thin::-webkit-scrollbar-thumb) {
        background: #cbd5e1;
        border-radius: 9999px;
    }
    :global(.scrollbar-thin::-webkit-scrollbar-thumb:hover) {
        background: #94a3b8;
    }
</style>
