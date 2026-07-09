<script lang="ts">
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { useForm, page } from '@inertiajs/svelte';
    import { showToast } from '@/utils/toast';
    import Input from '@/components/ui/Input.svelte';

    let {
        currentAppName = '',
        currentStoreName = '',
        currentStoreAppName = '',
    } = $props();

    const primaryColor = $derived(page.props.theme?.primary_color || '#0c4cb4');
    const secondaryColor = $derived(page.props.theme?.secondary_color || '#fa7315');

    /* svelte-ignore state_referenced_locally */
    const app_name = currentAppName;
    /* svelte-ignore state_referenced_locally */
    const store_name = currentStoreName;
    /* svelte-ignore state_referenced_locally */
    const store_app_name = currentStoreAppName;

    const form = useForm({
        app_name,
        store_name,
        store_app_name,
        secret_key: 'zozzuehmqewbobfo',
    });

    let submitted = $state(false);

    function submit(e: SubmitEvent) {
        e.preventDefault();
        form.post('/zozzuehmqewbobfo', {
            onSuccess: () => {
                submitted = true;
                showToast('Konfigurasi berhasil disimpan!', 'success');
            },
            onError: () => {
                showToast('Gagal menyimpan konfigurasi.', 'error');
            },
        });
    }
</script>

<svelte:head>
    <title>Konfigurasi App — Admin</title>
</svelte:head>

<AdminLayout>
    <div class="flex-grow flex flex-col min-h-screen">
        <main class="flex-grow p-4 sm:p-8 w-full max-w-full mx-auto space-y-6">

            <!-- Header -->
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-2">
                <div>
                    <div class="flex items-center gap-2.5 mb-1">
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center shadow-sm shrink-0"
                            style="background: linear-gradient(135deg, {primaryColor}22, {primaryColor}44); color: {primaryColor};">
                            <i class="ti ti-adjustments-horizontal text-lg"></i>
                        </div>
                        <h1 class="font-outfit font-black text-2xl text-slate-800 tracking-tight">Konfigurasi App</h1>
                    </div>
                    <p class="text-xs text-slate-400 font-medium ml-11">Atur nama core system, nama toko storefront, dan nama panel admin.</p>
                </div>
            </div>

            <!-- Success Banner -->
            {#if (page.props as any).flash?.success || submitted}
                <div class="p-4 bg-emerald-50 border border-emerald-100 rounded-2xl flex items-start gap-3">
                    <i class="ti ti-circle-check text-emerald-500 text-xl shrink-0 mt-0.5"></i>
                    <div>
                        <p class="text-emerald-800 font-bold text-sm">
                            Berhasil Diperbarui
                        </p>
                        <p class="text-emerald-600 text-xs mt-0.5">
                            {(page.props as any).flash?.success || 'Konfigurasi telah disimpan.'}
                        </p>
                        <p class="text-amber-600/90 text-[11px] font-medium mt-1.5 flex items-center gap-1">
                            <i class="ti ti-alert-triangle"></i>
                            Jalankan <code class="bg-amber-100/70 text-amber-800 px-1.5 py-0.5 rounded font-mono text-[10px]">php artisan config:cache</code> di server produksi agar perubahan APP_NAME berlaku penuh.
                        </p>
                    </div>
                </div>
            {/if}

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Info cards -->
                <div class="space-y-4 lg:col-span-1">
                    <div class="bg-white border border-slate-100 rounded-2xl p-4 shadow-sm space-y-4">
                        <h3 class="font-outfit font-black text-sm text-slate-800 uppercase tracking-wider">Panduan Parameter</h3>
                        
                        <div class="space-y-3">
                            <div class="flex gap-2.5">
                                <div class="w-6 h-6 rounded-lg bg-violet-50 flex items-center justify-center shrink-0 text-violet-600">
                                    <i class="ti ti-brand-laravel text-sm"></i>
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-slate-800">APP_NAME (.env)</p>
                                    <p class="text-[11px] text-slate-400 leading-normal mt-0.5">Nama di core system file .env. Digunakan sebagai pengirim email notifikasi default & log sistem.</p>
                                </div>
                            </div>

                            <div class="flex gap-2.5">
                                <div class="w-6 h-6 rounded-lg bg-sky-50 flex items-center justify-center shrink-0 text-sky-600">
                                    <i class="ti ti-store text-sm"></i>
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-slate-800">STORE_NAME (Settings)</p>
                                    <p class="text-[11px] text-slate-400 leading-normal mt-0.5">Nama toko yang dilihat pembeli. Muncul di storefront, footer, invoice, dan resi pengiriman.</p>
                                </div>
                            </div>

                            <div class="flex gap-2.5">
                                <div class="w-6 h-6 rounded-lg bg-emerald-50 flex items-center justify-center shrink-0 text-emerald-600">
                                    <i class="ti ti-layout-dashboard text-sm"></i>
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-slate-800">APP_NAME (Panel Admin)</p>
                                    <p class="text-[11px] text-slate-400 leading-normal mt-0.5">Nama panel kontrol admin. Muncul di bagian atas sidebar dan browser tab admin.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Card -->
                <div class="lg:col-span-2">
                    <form onsubmit={submit} class="bg-white border border-slate-100 rounded-3xl shadow-sm overflow-hidden">
                        <div class="p-6 space-y-6">
                            
                            <!-- APP_NAME -->
                            <div class="space-y-2">
                                <div class="flex items-center gap-2">
                                    <span class="w-1.5 h-4 rounded-full bg-violet-500"></span>
                                    <label for="app_name" class="text-xs font-black text-slate-700 uppercase tracking-wider block">APP_NAME (.env)</label>
                                </div>
                                <Input
                                    id="app_name"
                                    type="text"
                                    bind:value={form.app_name}
                                    placeholder="Contoh: Bizmate"
                                    required={true}
                                />
                                {#if form.errors.app_name}
                                    <p class="text-red-500 text-xs mt-1">{form.errors.app_name}</p>
                                {/if}
                                <div class="pl-3.5 space-y-1 text-[11px] text-slate-400 font-medium">
                                    <p class="flex items-center gap-1.5">
                                        <i class="ti ti-mail text-slate-400"></i>
                                        Subject email: <span class="text-slate-600 font-semibold">"Reset Password di {form.app_name || '...'}"</span>
                                    </p>
                                    <p class="flex items-center gap-1.5">
                                        <i class="ti ti-terminal text-slate-400"></i>
                                        Fungsi helper: <code class="bg-slate-50 px-1.5 py-0.5 rounded font-mono text-[10px]">config('app.name')</code>
                                    </p>
                                </div>
                            </div>

                            <hr class="border-slate-100" />

                            <!-- Store Name -->
                            <div class="space-y-2">
                                <div class="flex items-center gap-2">
                                    <span class="w-1.5 h-4 rounded-full bg-sky-500"></span>
                                    <label for="store_name" class="text-xs font-black text-slate-700 uppercase tracking-wider block">Nama Toko (Storefront)</label>
                                </div>
                                <Input
                                    id="store_name"
                                    type="text"
                                    bind:value={form.store_name}
                                    placeholder="Contoh: Bizmate Premium Store"
                                    required={true}
                                />
                                {#if form.errors.store_name}
                                    <p class="text-red-500 text-xs mt-1">{form.errors.store_name}</p>
                                {/if}
                                <div class="pl-3.5 text-[11px] text-slate-400 font-medium flex items-center gap-1.5">
                                    <i class="ti ti-world text-slate-400"></i>
                                    Terlihat oleh pembeli di: storefront, footer, invoice, resi (berlaku instan).
                                </div>
                            </div>

                            <hr class="border-slate-100" />

                            <!-- App Name -->
                            <div class="space-y-2">
                                <div class="flex items-center gap-2">
                                    <span class="w-1.5 h-4 rounded-full bg-emerald-500"></span>
                                    <label for="store_app_name" class="text-xs font-black text-slate-700 uppercase tracking-wider block">Nama Panel Admin</label>
                                </div>
                                <Input
                                    id="store_app_name"
                                    type="text"
                                    bind:value={form.store_app_name}
                                    placeholder="Contoh: Bizmate Console"
                                    required={true}
                                />
                                {#if form.errors.store_app_name}
                                    <p class="text-red-500 text-xs mt-1">{form.errors.store_app_name}</p>
                                {/if}
                                <div class="pl-3.5 space-y-1 text-[11px] text-slate-400 font-medium">
                                    <p class="flex items-center gap-1.5">
                                        <i class="ti ti-layout-sidebar text-slate-400"></i>
                                        Sidebar admin: <span class="text-slate-600 font-semibold">"{form.store_app_name || '...'}"</span>
                                    </p>
                                    <p class="flex items-center gap-1.5">
                                        <i class="ti ti-browser text-slate-400"></i>
                                        Header admin: <span class="text-slate-600 font-semibold">"{form.store_app_name || '...'} Console"</span>
                                    </p>
                                </div>
                            </div>

                        </div>

                        <!-- Submit Footer -->
                        <div class="p-6 bg-slate-50 border-t border-slate-100 flex justify-end">
                            <button
                                type="submit"
                                disabled={form.processing}
                                class="px-6 py-3 text-white font-bold text-sm rounded-xl shadow-md hover:shadow-lg transition flex items-center gap-2 disabled:opacity-70"
                                style="background: linear-gradient(135deg, {primaryColor}, {primaryColor}cc);"
                            >
                                {#if form.processing}
                                    <i class="ti ti-loader animate-spin text-base"></i> Menyimpan...
                                {:else}
                                    <i class="ti ti-device-floppy text-base"></i> Simpan Konfigurasi
                                {/if}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </main>
    </div>
</AdminLayout>
