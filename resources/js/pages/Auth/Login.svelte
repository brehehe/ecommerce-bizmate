<script lang="ts">
    import { useForm, page, Link, router } from '@inertiajs/svelte';
    import { slide } from 'svelte/transition';
    import { showToast } from '@/utils/toast';

    // Fallback colors if settings aren't loaded yet globally
    const primaryColor = $derived(
        (page.props as any).theme?.primary_color || '#0c4cb4',
    );
    const secondaryColor = $derived(
        (page.props as any).theme?.secondary_color || '#fa7315',
    );

    const storeName = $derived(
        (page.props as any).settings?.store_name || 'Bizmate',
    );
    const storeIcon = $derived(
        (page.props as any).settings?.store_icon || null,
    );
    const storeLogo = $derived(
        (page.props as any).settings?.store_logo || null,
    );
    const padelgigsLoginEnabled = $derived(
        (page.props as any).app_config?.padelgigs_login_enabled ??
            (page.props as any).settings?.padelgigs_login_enabled ??
            true,
    );

    const form = useForm({
        email: '',
        password: '',
        remember: false,
    });

    let showPassword = $state(false);
    let isResending = $state(false);

    const resendVerification = () => {
        isResending = true;
        router.post(
            '/email/resend-verification-guest',
            {
                email: form.email,
            },
            {
                onSuccess: () => {
                    isResending = false;
                },
                onError: (err) => {
                    const firstError = Object.values(err)[0] as string;
                    showToast(
                        firstError || 'Gagal mengirim link.',
                        'error',
                        'top',
                    );
                    isResending = false;
                },
                onFinish: () => {
                    isResending = false;
                },
            },
        );
    };

    const shownFlashIds = new Set();
    $effect(() => {
        const flash = (page.props as any).flash;
        if (!flash || !flash.id || shownFlashIds.has(flash.id)) return;

        if (flash.success) {
            showToast(flash.success, 'success', 'top');
            shownFlashIds.add(flash.id);
        }
        if (flash.error) {
            showToast(flash.error, 'error', 'top');
            shownFlashIds.add(flash.id);
        }
    });

    const submit = () => {
        form.post('/login');
    };
</script>

<svelte:head>
    <title>Login - {storeName}</title>
</svelte:head>

<div
    class="grid grid-cols-1 lg:grid-cols-2 min-h-dvh w-full font-sans bg-white selection:bg-slate-900 selection:text-white overflow-x-hidden"
>
    <!-- Bagian Kiri: Gambar & Informasi (Disembunyikan di Mobile) -->
    <div
        class="hidden lg:flex relative bg-slate-900 overflow-hidden items-center justify-center p-12"
    >
        <!-- Background Image & Overlay Gradasi -->
        <div class="absolute inset-0 z-0">
            <img
                src="https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?q=80&w=1000&auto=format&fit=crop"
                alt="E-commerce abstract"
                class="w-full h-full object-cover opacity-60"
            />
            <div
                class="absolute inset-0 bg-slate-900/60 mix-blend-multiply"
            ></div>
            <div
                class="absolute inset-0 mix-blend-overlay opacity-80"
                style="background: linear-gradient(to bottom right, {primaryColor}, {secondaryColor});"
            ></div>
        </div>

        <!-- Konten Informasi -->
        <div
            class="relative z-10 px-8 flex flex-col justify-center max-w-lg text-white"
        >
            {#if storeLogo}
                <img
                    src={storeLogo}
                    alt="Store Icon"
                    class="w-16 h-16 object-contain mb-8 rounded-3xl shadow-2xl transition-transform hover:scale-105 bg-white/10 backdrop-blur-md border border-white/20 p-2"
                />
            {:else}
                <div
                    class="w-16 h-16 rounded-3xl shadow-2xl flex items-center justify-center text-white text-3xl mb-8 backdrop-blur-md bg-white/20 border border-white/30 transition-transform hover:scale-105"
                >
                    <i class="ti ti-sofa"></i>
                </div>
            {/if}

            <h1
                class="text-4xl font-outfit font-black mb-6 leading-tight tracking-tight"
            >
                Kelola bisnis Anda dengan lebih mudah.
            </h1>
            <p
                class="text-base text-slate-200 font-medium leading-relaxed mb-10"
            >
                {storeName} memberikan Anda kendali penuh atas toko online, manajemen
                inventaris, laporan keuangan, dan pelanggan—semuanya dalam satu dashboard
                elegan yang terpusat.
            </p>

            <div
                class="flex items-center gap-4 text-sm font-bold text-white/90"
            >
                <div class="flex -space-x-3">
                    <img
                        class="w-10 h-10 rounded-full border-2 border-slate-900 object-cover"
                        src="https://i.pravatar.cc/100?img=1"
                        alt="Avatar"
                    />
                    <img
                        class="w-10 h-10 rounded-full border-2 border-slate-900 object-cover"
                        src="https://i.pravatar.cc/100?img=2"
                        alt="Avatar"
                    />
                    <img
                        class="w-10 h-10 rounded-full border-2 border-slate-900 object-cover"
                        src="https://i.pravatar.cc/100?img=3"
                        alt="Avatar"
                    />
                    <div
                        class="w-10 h-10 rounded-full border-2 border-slate-900 bg-slate-800 flex items-center justify-center text-xs"
                    >
                        +10k
                    </div>
                </div>
                <span>Bergabung dengan 10,000+ merchant lainnya</span>
            </div>
        </div>
    </div>

    <!-- Bagian Kanan: Form Login -->
    <div
        class="flex flex-col justify-center py-10 px-4 sm:px-12 md:px-16 lg:px-20 bg-white relative overflow-hidden min-h-dvh"
    >
        <!-- Hiasan Bulat Dekoratif -->
        <div
            class="absolute top-0 right-0 -mr-20 -mt-20 w-64 h-64 rounded-full opacity-5 blur-3xl pointer-events-none"
            style="background-color: {primaryColor};"
        ></div>
        <div
            class="absolute bottom-0 left-0 -ml-20 -mb-20 w-80 h-80 rounded-full opacity-5 blur-3xl pointer-events-none"
            style="background-color: {secondaryColor};"
        ></div>

        <div class="max-w-md w-full mx-auto relative z-10 my-auto">
            <!-- Back to Home Link -->
            <div class="mb-6">
                <Link
                    href="/"
                    class="inline-flex items-center gap-2 text-sm font-bold text-slate-500 hover:text-slate-800 transition"
                >
                    <i class="ti ti-arrow-left text-lg"></i>
                    Kembali ke Beranda
                </Link>
            </div>

            <!-- Logo (Hanya muncul di Mobile) -->
            <div class="flex lg:hidden items-center gap-3 mb-10">
                {#if storeIcon}
                    <img
                        src={storeIcon}
                        alt="Store Icon"
                        class="w-12 h-12 object-contain"
                    />
                {:else}
                    <div
                        class="w-12 h-12 rounded-2xl shadow-md flex items-center justify-center text-white text-2xl"
                        style="background: linear-gradient(to bottom right, {primaryColor}, {secondaryColor});"
                    >
                        <i class="ti ti-sofa"></i>
                    </div>
                {/if}
                <div class="flex flex-col">
                    <span
                        class="font-outfit font-black text-2xl text-slate-800 tracking-tight leading-none"
                        >{storeName}</span
                    >
                    <span
                        class="font-sans font-bold text-[10px] text-slate-400 tracking-widest mt-1 uppercase"
                        >Admin Console</span
                    >
                </div>
            </div>

            <h2
                class="text-3xl sm:text-4xl font-outfit font-black text-slate-900 tracking-tight mb-2"
            >
                Selamat Datang Kembali
            </h2>
            <p class="text-sm text-slate-500 font-medium mb-6">
                Silakan masuk menggunakan akun Google, PadelGigs, atau email Anda.
            </p>

            <div class="mb-6">
                <p class="text-xs font-semibold text-slate-500 mb-2.5">Masuk dengan</p>
                <div class="grid {padelgigsLoginEnabled ? 'grid-cols-2' : 'grid-cols-1'} gap-3">
                    {#if padelgigsLoginEnabled}
                        <!-- PadelGigs OAuth Button (Left) -->
                        <a
                            href="/auth/padelgigs"
                            class="flex items-center justify-center gap-2.5 py-3 px-4 bg-white hover:bg-slate-50 border border-slate-200 hover:border-slate-300 active:scale-[0.98] text-slate-700 font-semibold text-sm rounded-xl shadow-xs transition-all duration-200 group no-underline"
                        >
                            <img
                                src="/logo/Logo-padelgigs-bunder-hitam.png"
                                alt="PadelGigs"
                                class="w-5 h-5 shrink-0 object-contain group-hover:scale-110 transition-transform"
                            />
                            <span class="font-outfit text-slate-800 text-sm">PadelGigs</span>
                        </a>
                    {/if}

                    <!-- Google OAuth Button (Right) -->
                    <a
                        href="/auth/google"
                        class="flex items-center justify-center gap-2.5 py-3 px-4 bg-white hover:bg-slate-50 border border-slate-200 hover:border-slate-300 active:scale-[0.98] text-slate-700 font-semibold text-sm rounded-xl shadow-xs transition-all duration-200 group no-underline"
                    >
                        <svg class="w-4 h-4 shrink-0 group-hover:scale-110 transition-transform" viewBox="0 0 24 24">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                        </svg>
                        <span class="font-outfit text-slate-800 text-sm">{padelgigsLoginEnabled ? 'Google' : 'Masuk dengan Google'}</span>
                    </a>
                </div>
            </div>

            <!-- Separator -->
            <div class="relative my-6 flex items-center justify-center">
                <div class="border-t border-slate-200 w-full"></div>
                <span
                    class="bg-white px-3 text-xs font-bold text-slate-400 uppercase tracking-widest absolute"
                    >atau masuk dengan email</span
                >
            </div>

            <form
                class="space-y-6"
                onsubmit={(e) => {
                    e.preventDefault();
                    submit();
                }}
            >
                {#if form.errors.email}
                    <div
                        transition:slide
                        class="p-4 bg-rose-50 border border-rose-100 rounded-xl flex flex-col gap-2"
                    >
                        <div class="flex items-start gap-3">
                            <i
                                class="ti ti-alert-circle text-rose-500 text-xl mt-0.5"
                            ></i>
                            <p class="text-sm font-bold text-rose-600">
                                {form.errors.email}
                            </p>
                        </div>
                        {#if form.errors.email.includes('belum diverifikasi')}
                            <div class="pl-8 pt-1">
                                <button
                                    type="button"
                                    onclick={resendVerification}
                                    disabled={isResending}
                                    class="text-xs font-bold text-rose-700 underline hover:text-rose-900 transition disabled:opacity-50 flex items-center gap-1.5 p-0 bg-transparent border-0 cursor-pointer"
                                >
                                    {#if isResending}
                                        <i
                                            class="ti ti-loader animate-spin text-sm"
                                        ></i> Mengirim...
                                    {:else}
                                        <i class="ti ti-send text-sm"></i> Kirim Ulang
                                        Link Verifikasi
                                    {/if}
                                </button>
                            </div>
                        {/if}
                    </div>
                {/if}

                <div class="group">
                    <label
                        for="email"
                        class="block text-sm font-bold text-slate-700 mb-2 transition-colors group-focus-within:text-slate-900"
                    >
                        Alamat Email / No. HP
                    </label>
                    <div class="relative">
                        <div
                            class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none"
                        >
                            <i
                                class="ti ti-mail text-slate-400 text-lg transition-colors group-focus-within:text-slate-600"
                            ></i>
                        </div>
                        <input
                            id="email"
                            type="text"
                            bind:value={form.email}
                            required
                            class="block w-full pl-11 pr-4 py-3.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:outline-none transition-all bg-slate-50 focus:bg-white hover:border-slate-300"
                            style="--tw-ring-color: {primaryColor}30; focus-border-color: {primaryColor};"
                            placeholder="admin@email.com atau 08123456789"
                        />
                    </div>
                </div>

                <div class="group">
                    <label
                        for="password"
                        class="block text-sm font-bold text-slate-700 mb-2 transition-colors group-focus-within:text-slate-900"
                    >
                        Kata Sandi
                    </label>
                    <div class="relative">
                        <div
                            class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none"
                        >
                            <i
                                class="ti ti-lock text-slate-400 text-lg transition-colors group-focus-within:text-slate-600"
                            ></i>
                        </div>
                        <input
                            id="password"
                            type={showPassword ? 'text' : 'password'}
                            bind:value={form.password}
                            required
                            class="block w-full pl-11 pr-12 py-3.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:outline-none transition-all bg-slate-50 focus:bg-white hover:border-slate-300"
                            style="--tw-ring-color: {primaryColor}30;"
                            placeholder="••••••••"
                        />
                        <button
                            type="button"
                            class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-slate-600 transition-colors"
                            onclick={() => (showPassword = !showPassword)}
                            aria-label={showPassword
                                ? 'Sembunyikan kata sandi'
                                : 'Tampilkan kata sandi'}
                        >
                            <i
                                class={showPassword
                                    ? 'ti ti-eye-off text-lg'
                                    : 'ti ti-eye text-lg'}
                            ></i>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-2">
                    <div class="flex items-center">
                        <input
                            id="remember"
                            type="checkbox"
                            bind:checked={form.remember}
                            class="h-4 w-4 rounded border-slate-300 transition-colors cursor-pointer"
                            style="color: {primaryColor};"
                        />
                        <label
                            for="remember"
                            class="ml-2 block text-sm font-semibold text-slate-600 cursor-pointer select-none"
                        >
                            Ingat saya
                        </label>
                    </div>

                    <div class="text-sm">
                        <Link
                            href="/forgot-password"
                            class="font-bold hover:underline transition-opacity hover:opacity-80"
                            style="color: {primaryColor};"
                        >
                            Lupa kata sandi?
                        </Link>
                    </div>
                </div>

                <div class="pt-4">
                    <button
                        type="submit"
                        disabled={form.processing}
                        class="w-full flex justify-center items-center py-4 px-4 border border-transparent rounded-xl shadow-lg shadow-blue-900/10 text-sm font-bold text-white transition-all hover:shadow-xl focus:outline-none focus:ring-4 disabled:opacity-70 disabled:hover:translate-y-0 font-outfit uppercase tracking-wider"
                        style="background-color: {primaryColor}; --tw-ring-color: {primaryColor}50;"
                    >
                        {#if form.processing}
                            <i class="ti ti-loader animate-spin mr-2 text-xl"
                            ></i> Memproses...
                        {:else}
                            Masuk Sekarang <i
                                class="ti ti-arrow-right ml-2 text-xl"
                            ></i>
                        {/if}
                    </button>
                </div>

                <div class="pt-2 text-center text-sm text-slate-600 font-medium">
                    Belum memiliki akun?
                    <Link
                        href="/register"
                        class="font-bold hover:underline transition-opacity hover:opacity-80 ml-1"
                        style="color: {primaryColor};"
                    >
                        Daftar Sekarang
                    </Link>
                </div>
            </form>
        </div>
    </div>
</div>
