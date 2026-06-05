<script lang="ts">
    import { useForm, page } from '@inertiajs/svelte';
    import { showToast } from '@/utils/toast';

    let {
        currentAppName = '',
        currentStoreName = '',
        currentStoreAppName = '',
    } = $props();

    const primaryColor = $derived(page.props.theme?.primary_color || '#0c4cb4');

    const form = useForm({
        app_name: currentAppName,
        store_name: currentStoreName,
        store_app_name: currentStoreAppName,
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
    <title>App Config</title>
    <meta name="robots" content="noindex, nofollow" />
</svelte:head>

<div
    class="min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 flex items-center justify-center p-4 font-sans"
>
    <div class="w-full max-w-lg">
        <!-- Header -->
        <div class="text-center mb-8">
            <div
                class="w-16 h-16 rounded-2xl flex items-center justify-center text-white text-2xl mx-auto mb-4 shadow-2xl"
                style="background: linear-gradient(135deg, {primaryColor}, #7c3aed);"
            >
                <i class="ti ti-settings-2"></i>
            </div>
            <h1 class="font-outfit font-black text-2xl text-white">
                App Configuration
            </h1>
            <p class="text-slate-400 text-sm mt-1">
                Superadmin — Konfigurasi nama aplikasi & toko
            </p>
        </div>

        <!-- Success Banner -->
        {#if (page.props as any).flash?.success || submitted}
            <div
                class="mb-6 p-4 bg-emerald-500/20 border border-emerald-500/30 rounded-2xl flex items-start gap-3"
            >
                <i
                    class="ti ti-circle-check text-emerald-400 text-xl shrink-0 mt-0.5"
                ></i>
                <div>
                    <p class="text-emerald-300 font-bold text-sm">
                        Berhasil diperbarui
                    </p>
                    <p class="text-emerald-400 text-xs mt-0.5">
                        {(page.props as any).flash?.success ||
                            'Konfigurasi telah disimpan.'}
                    </p>
                    <p class="text-emerald-500/80 text-xs mt-1.5">
                        ⚠️ Jalankan <code
                            class="bg-emerald-900/50 px-1.5 py-0.5 rounded font-mono"
                            >php artisan config:cache</code
                        >
                        di server produksi agar <strong>APP_NAME</strong> berlaku
                        penuh.
                    </p>
                </div>
            </div>
        {/if}

        <!-- Comparison banner -->
        <div class="mb-5 grid grid-cols-3 gap-2.5 text-[11px] leading-relaxed">
            <div
                class="bg-violet-500/10 border border-violet-500/20 rounded-2xl p-3 flex flex-col justify-between"
            >
                <div>
                    <div class="flex items-center gap-1.5 mb-1.5">
                        <i class="ti ti-brand-laravel text-violet-400 text-sm"
                        ></i>
                        <span
                            class="font-bold text-violet-300 uppercase tracking-wider text-[9px]"
                            >APP_NAME</span
                        >
                    </div>
                    <p class="text-violet-400/80">
                        Nama di file <code class="bg-violet-900/40 px-1 rounded"
                            >.env</code
                        >. Dipakai di email notifikasi & config sistem.
                    </p>
                </div>
            </div>
            <div
                class="bg-sky-500/10 border border-sky-500/20 rounded-2xl p-3 flex flex-col justify-between"
            >
                <div>
                    <div class="flex items-center gap-1.5 mb-1.5">
                        <i class="ti ti-store text-sky-400 text-sm"></i>
                        <span
                            class="font-bold text-sky-300 uppercase tracking-wider text-[9px]"
                            >STORE NAME</span
                        >
                    </div>
                    <p class="text-sky-400/80">
                        Nama toko untuk pembeli. Tampil di storefront, footer,
                        invoice & resi.
                    </p>
                </div>
            </div>
            <div
                class="bg-emerald-500/10 border border-emerald-500/20 rounded-2xl p-3 flex flex-col justify-between"
            >
                <div>
                    <div class="flex items-center gap-1.5 mb-1.5">
                        <i
                            class="ti ti-layout-dashboard text-emerald-400 text-sm"
                        ></i>
                        <span
                            class="font-bold text-emerald-300 uppercase tracking-wider text-[9px]"
                            >APP NAME</span
                        >
                    </div>
                    <p class="text-emerald-400/80">
                        Nama di panel admin. Tampil di sidebar & browser tab
                        admin console.
                    </p>
                </div>
            </div>
        </div>

        <!-- Card -->
        <div
            class="bg-white/5 backdrop-blur border border-white/10 rounded-3xl overflow-hidden shadow-2xl"
        >
            <form onsubmit={submit}>
                <!-- APP_NAME Section -->
                <div class="p-6 border-b border-white/10">
                    <div class="flex items-center gap-3 mb-4">
                        <div
                            class="w-8 h-8 rounded-xl bg-violet-500/20 border border-violet-500/30 flex items-center justify-center shrink-0"
                        >
                            <i
                                class="ti ti-brand-laravel text-violet-400 text-sm"
                            ></i>
                        </div>
                        <div>
                            <p
                                class="font-bold text-white text-sm leading-none"
                            >
                                APP_NAME
                            </p>
                            <p
                                class="text-violet-400/70 text-[10px] mt-0.5 font-mono"
                            >
                                .env → config('app.name')
                            </p>
                        </div>
                    </div>
                    <input
                        id="app_name"
                        type="text"
                        bind:value={form.app_name}
                        placeholder="Contoh: Bizmate"
                        class="w-full px-4 py-3 bg-white/10 border border-violet-500/30 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-500/40 focus:border-violet-500/60 transition text-sm font-medium"
                    />
                    {#if form.errors.app_name}
                        <p class="text-red-400 text-xs mt-1.5">
                            {form.errors.app_name}
                        </p>
                    {/if}
                    <div class="mt-3 space-y-1 text-[11px] text-slate-500">
                        <p class="flex items-center gap-1.5">
                            <i class="ti ti-mail text-xs text-slate-600"></i>
                            Subject email:
                            <em>"Reset Password di {form.app_name || '...'}"</em
                            >
                        </p>
                        <p class="flex items-center gap-1.5">
                            <i class="ti ti-terminal text-xs text-slate-600"
                            ></i>
                            <code
                                class="bg-slate-800 px-1.5 py-0.5 rounded font-mono"
                                >config('app.name')</code
                            >
                            → <em>{form.app_name || '...'}</em>
                        </p>
                        <p class="flex items-start gap-1.5">
                            <i
                                class="ti ti-alert-circle text-xs text-amber-600 mt-0.5 shrink-0"
                            ></i>
                            <span class="text-amber-600/80"
                                >Perlu restart server / <code class="font-mono"
                                    >config:cache</code
                                > agar aktif.</span
                            >
                        </p>
                    </div>
                </div>

                <!-- Store Name Section -->
                <div class="p-6 border-b border-white/10">
                    <div class="flex items-center gap-3 mb-4">
                        <div
                            class="w-8 h-8 rounded-xl bg-sky-500/20 border border-sky-500/30 flex items-center justify-center shrink-0"
                        >
                            <i class="ti ti-store text-sky-400 text-sm"></i>
                        </div>
                        <div>
                            <p
                                class="font-bold text-white text-sm leading-none"
                            >
                                Store Name
                            </p>
                            <p
                                class="text-sky-400/70 text-[10px] mt-0.5 font-mono"
                            >
                                settings → key: store_name
                            </p>
                        </div>
                    </div>
                    <input
                        id="store_name"
                        type="text"
                        bind:value={form.store_name}
                        placeholder="Contoh: Bizmate Premium Store"
                        class="w-full px-4 py-3 bg-white/10 border border-sky-500/30 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-sky-500/40 focus:border-sky-500/60 transition text-sm font-medium"
                    />
                    {#if form.errors.store_name}
                        <p class="text-red-400 text-xs mt-1.5">
                            {form.errors.store_name}
                        </p>
                    {/if}
                    <div class="mt-3 space-y-1 text-[11px] text-slate-500">
                        <p class="flex items-center gap-1.5">
                            <i class="ti ti-world text-xs text-slate-600"></i> Terlihat
                            oleh pembeli di: storefront, footer, invoice, resi
                        </p>
                        <p class="flex items-center gap-1.5">
                            <i class="ti ti-check text-xs text-emerald-600"></i>
                            <span class="text-emerald-600/80"
                                >Berlaku <strong>langsung</strong> tanpa restart.</span
                            >
                        </p>
                    </div>
                </div>

                <!-- App Name Section -->
                <div class="p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <div
                            class="w-8 h-8 rounded-xl bg-emerald-500/20 border border-emerald-500/30 flex items-center justify-center shrink-0"
                        >
                            <i
                                class="ti ti-layout-dashboard text-emerald-400 text-sm"
                            ></i>
                        </div>
                        <div>
                            <p
                                class="font-bold text-white text-sm leading-none"
                            >
                                App Name (Admin Panel)
                            </p>
                            <p
                                class="text-emerald-400/70 text-[10px] mt-0.5 font-mono"
                            >
                                settings → key: store_app_name
                            </p>
                        </div>
                    </div>
                    <input
                        id="store_app_name"
                        type="text"
                        bind:value={form.store_app_name}
                        placeholder="Contoh: Bizmate Console"
                        class="w-full px-4 py-3 bg-white/10 border border-emerald-500/30 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/40 focus:border-emerald-500/60 transition text-sm font-medium"
                    />
                    {#if form.errors.store_app_name}
                        <p class="text-red-400 text-xs mt-1.5">
                            {form.errors.store_app_name}
                        </p>
                    {/if}
                    <div class="mt-3 space-y-1 text-[11px] text-slate-500">
                        <p class="flex items-center gap-1.5">
                            <i
                                class="ti ti-layout-sidebar text-xs text-slate-600"
                            ></i>
                            Sidebar admin:
                            <em>"{form.store_app_name || '...'}"</em>
                        </p>
                        <p class="flex items-center gap-1.5">
                            <i class="ti ti-terminal-2 text-xs text-slate-600"
                            ></i>
                            Admin header:
                            <em>"{form.store_app_name || '...'} Console"</em>
                        </p>
                        <p class="flex items-center gap-1.5">
                            <i class="ti ti-check text-xs text-emerald-600"></i>
                            <span class="text-emerald-600/80"
                                >Berlaku <strong>langsung</strong> tanpa restart.</span
                            >
                        </p>
                    </div>
                </div>

                <!-- Submit -->
                <div class="px-6 pb-6">
                    <button
                        type="submit"
                        disabled={form.processing}
                        class="w-full py-3.5 rounded-2xl font-black text-sm text-white transition flex items-center justify-center gap-2 shadow-xl disabled:opacity-60"
                        style="background: linear-gradient(135deg, {primaryColor}, #7c3aed);"
                    >
                        {#if form.processing}
                            <i class="ti ti-loader animate-spin text-lg"></i>
                            Menyimpan...
                        {:else}
                            <i class="ti ti-device-floppy text-lg"></i>
                            Simpan Konfigurasi
                        {/if}
                    </button>
                </div>
            </form>
        </div>

        <p class="text-center text-slate-600 text-xs mt-6">
            Halaman ini tidak terindeks. Hanya dapat diakses melalui URL
            langsung.
        </p>
    </div>
</div>
