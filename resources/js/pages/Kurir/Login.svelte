<script lang="ts">
    import { useForm, page, Link } from '@inertiajs/svelte';
    import { slide } from 'svelte/transition';
    import { showToast } from '@/utils/toast';

    const primaryColor = $derived((page.props as any).theme?.primary_color || '#0c4cb4');
    const secondaryColor = $derived((page.props as any).theme?.secondary_color || '#fa7315');
    const storeName = $derived((page.props as any).settings?.store_name || 'Bizmate');
    const storeIcon = $derived((page.props as any).settings?.store_icon || null);
    const storeLogo = $derived((page.props as any).settings?.store_logo || null);

    const form = useForm({
        email: '',
        password: '',
        remember: false,
    });

    let showPassword = $state(false);

    const shownFlashIds = new Set();
    $effect(() => {
        const flash = (page.props as any).flash;
        if (!flash || !flash.id || shownFlashIds.has(flash.id)) return;
        if (flash.success) { showToast(flash.success, 'success', 'top'); shownFlashIds.add(flash.id); }
        if (flash.error) { showToast(flash.error, 'error', 'top'); shownFlashIds.add(flash.id); }
    });

    const submit = () => { form.post('/kurir/login'); };
</script>

<svelte:head>
    <title>Login Kurir — {storeName}</title>
</svelte:head>

<div class="grid grid-cols-1 lg:grid-cols-2 min-h-dvh w-full font-sans bg-white selection:bg-slate-900 selection:text-white overflow-x-hidden">
    <!-- Left Panel: Branding -->
    <div class="hidden lg:flex relative overflow-hidden items-center justify-center p-12" style="background: linear-gradient(135deg, {primaryColor}, #1e40af, #312e81);">
        <!-- Animated Background -->
        <div class="absolute inset-0 z-0 overflow-hidden">
            <!-- Decorative circles -->
            <div class="absolute -top-20 -left-20 w-96 h-96 rounded-full opacity-10 bg-white blur-3xl"></div>
            <div class="absolute -bottom-20 -right-20 w-80 h-80 rounded-full opacity-10 bg-white blur-3xl"></div>
            <div class="absolute top-1/3 right-1/4 w-40 h-40 rounded-full opacity-5 bg-white blur-2xl"></div>
        </div>

        <!-- Floating delivery illustration cards -->
        <div class="absolute top-16 right-12 bg-white/10 backdrop-blur-md border border-white/20 rounded-2xl p-4 animate-bounce" style="animation-duration: 3s;">
            <i class="ti ti-package text-white text-3xl"></i>
        </div>
        <div class="absolute bottom-24 left-10 bg-white/10 backdrop-blur-md border border-white/20 rounded-2xl p-4 animate-bounce" style="animation-duration: 4s; animation-delay: 0.5s;">
            <i class="ti ti-map-pin text-white text-3xl"></i>
        </div>
        <div class="absolute top-1/2 right-8 bg-white/10 backdrop-blur-md border border-white/20 rounded-2xl p-3 animate-bounce" style="animation-duration: 5s; animation-delay: 1s;">
            <i class="ti ti-check text-white text-2xl"></i>
        </div>

        <!-- Content -->
        <div class="relative z-10 px-8 flex flex-col justify-center max-w-lg text-white">
            {#if storeLogo}
                <img src={storeLogo} alt="Store Logo" class="w-16 h-16 object-contain mb-8 rounded-3xl shadow-2xl bg-white/10 backdrop-blur-md border border-white/20 p-2 transition-transform hover:scale-105" />
            {:else}
                <div class="w-16 h-16 rounded-3xl shadow-2xl flex items-center justify-center text-white text-3xl mb-8 backdrop-blur-md bg-white/20 border border-white/30 transition-transform hover:scale-105">
                    <i class="ti ti-truck-delivery"></i>
                </div>
            {/if}

            <h1 class="text-4xl font-bold mb-6 leading-tight tracking-tight" style="font-family: 'Outfit', sans-serif;">
                Portal Kurir Toko
            </h1>
            <p class="text-base text-blue-100 font-medium leading-relaxed mb-10">
                Kelola pengiriman pesanan kurir toko. Scan QR, perbarui status pengiriman, dan kirimkan notifikasi ke pelanggan secara real-time.
            </p>

            <!-- Feature list -->
            <div class="space-y-3">
                {#each [
                    { icon: 'ti-qrcode', text: 'Scan QR & Barcode pesanan' },
                    { icon: 'ti-truck', text: 'Update status pengiriman langkah demi langkah' },
                    { icon: 'ti-bell', text: 'Notifikasi otomatis ke pelanggan' },
                ] as feature}
                    <div class="flex items-center gap-3 text-sm font-medium text-white/90">
                        <div class="w-8 h-8 rounded-xl bg-white/20 flex items-center justify-center shrink-0">
                            <i class="ti {feature.icon} text-base"></i>
                        </div>
                        <span>{feature.text}</span>
                    </div>
                {/each}
            </div>
        </div>
    </div>

    <!-- Right Panel: Login Form -->
    <div class="flex flex-col justify-center py-10 px-4 sm:px-12 md:px-16 lg:px-20 bg-white relative overflow-hidden min-h-dvh">
        <!-- Decorative blobs -->
        <div class="absolute top-0 right-0 -mr-20 -mt-20 w-64 h-64 rounded-full opacity-5 blur-3xl pointer-events-none" style="background-color: {primaryColor};"></div>
        <div class="absolute bottom-0 left-0 -ml-20 -mb-20 w-80 h-80 rounded-full opacity-5 blur-3xl pointer-events-none" style="background-color: {secondaryColor};"></div>

        <div class="max-w-md w-full mx-auto relative z-10 my-auto">
            <!-- Back link -->
            <div class="mb-6">
                <Link href="/" class="inline-flex items-center gap-2 text-sm font-bold text-slate-500 hover:text-slate-800 transition">
                    <i class="ti ti-arrow-left text-lg"></i>
                    Kembali ke Beranda
                </Link>
            </div>

            <!-- Logo (mobile only) -->
            <div class="flex lg:hidden items-center gap-3 mb-10">
                {#if storeIcon}
                    <img src={storeIcon} alt="Store Icon" class="w-12 h-12 object-contain" />
                {:else}
                    <div class="w-12 h-12 rounded-2xl shadow-md flex items-center justify-center text-white text-2xl" style="background: linear-gradient(to bottom right, {primaryColor}, {secondaryColor});">
                        <i class="ti ti-truck-delivery"></i>
                    </div>
                {/if}
                <div class="flex flex-col">
                    <span class="font-bold text-2xl text-slate-800 tracking-tight leading-none" style="font-family: 'Outfit', sans-serif;">{storeName}</span>
                    <span class="font-bold text-[10px] text-slate-400 tracking-widest mt-1 uppercase">Portal Kurir</span>
                </div>
            </div>

            <!-- Badge -->
            <div class="inline-flex items-center gap-2 px-3 py-1.5 bg-blue-50 text-blue-700 rounded-full text-xs font-black mb-6 tracking-wide">
                <i class="ti ti-truck-delivery text-sm"></i>
                KURIR TOKO
            </div>

            <h2 class="text-3xl sm:text-4xl font-black text-slate-900 tracking-tight mb-2" style="font-family: 'Outfit', sans-serif;">
                Selamat Datang, Kurir!
            </h2>
            <p class="text-sm text-slate-500 font-medium mb-10">
                Masuk menggunakan akun kurir toko Anda.
            </p>

            <form class="space-y-6" onsubmit={(e) => { e.preventDefault(); submit(); }}>
                {#if form.errors.email}
                    <div transition:slide class="p-4 bg-rose-50 border border-rose-100 rounded-xl flex items-start gap-3">
                        <i class="ti ti-alert-circle text-rose-500 text-xl mt-0.5"></i>
                        <p class="text-sm font-bold text-rose-600">{form.errors.email}</p>
                    </div>
                {/if}

                <div class="group">
                    <label for="email-kurir" class="block text-sm font-bold text-slate-700 mb-2 transition-colors group-focus-within:text-slate-900">
                        Alamat Email
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="ti ti-mail text-slate-400 text-lg transition-colors group-focus-within:text-slate-600"></i>
                        </div>
                        <input
                            id="email-kurir"
                            type="email"
                            bind:value={form.email}
                            required
                            class="block w-full pl-11 pr-4 py-3.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:outline-none transition-all bg-slate-50 focus:bg-white hover:border-slate-300"
                            placeholder="kurir@email.com"
                        />
                    </div>
                </div>

                <div class="group">
                    <label for="password-kurir" class="block text-sm font-bold text-slate-700 mb-2 transition-colors group-focus-within:text-slate-900">
                        Kata Sandi
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="ti ti-lock text-slate-400 text-lg transition-colors group-focus-within:text-slate-600"></i>
                        </div>
                        <input
                            id="password-kurir"
                            type={showPassword ? 'text' : 'password'}
                            bind:value={form.password}
                            required
                            class="block w-full pl-11 pr-12 py-3.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:outline-none transition-all bg-slate-50 focus:bg-white hover:border-slate-300"
                            placeholder="••••••••"
                        />
                        <button
                            type="button"
                            class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-slate-600 transition-colors"
                            onclick={() => showPassword = !showPassword}
                            aria-label={showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'}
                        >
                            <i class={showPassword ? 'ti ti-eye-off text-lg' : 'ti ti-eye text-lg'}></i>
                        </button>
                    </div>
                </div>

                <div class="pt-4">
                    <button
                        type="submit"
                        id="btn-kurir-login"
                        disabled={form.processing}
                        class="w-full flex justify-center items-center py-4 px-4 border border-transparent rounded-xl shadow-lg text-sm font-bold text-white transition-all hover:shadow-xl hover:-translate-y-0.5 focus:outline-none focus:ring-4 disabled:opacity-70 disabled:hover:translate-y-0 uppercase tracking-wider active:scale-[0.98]"
                        style="background: linear-gradient(135deg, {primaryColor}, #1e40af); font-family: 'Outfit', sans-serif;"
                    >
                        {#if form.processing}
                            <i class="ti ti-loader animate-spin mr-2 text-xl"></i> Memproses...
                        {:else}
                            <i class="ti ti-truck-delivery mr-2 text-xl"></i> Masuk ke Portal Kurir
                        {/if}
                    </button>
                </div>
            </form>

            <p class="mt-8 text-center text-xs text-slate-400">
                Khusus akun berperan <span class="font-bold text-slate-600">Kurir Toko</span>.
                Bukan kurir? <Link href="/login" class="font-bold hover:underline" style="color: {primaryColor};">Login sebagai Admin</Link>
            </p>
        </div>
    </div>
</div>
