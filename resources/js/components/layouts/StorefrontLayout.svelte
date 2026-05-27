<script lang="ts">
    import { page, router, Link } from '@inertiajs/svelte';
    import { slide, fade } from 'svelte/transition';
    import { onMount } from 'svelte';

    let { children, hideMobileHeader = false, hideMobileFooter = false } = $props();

    // Theme from settings
    const primary = $derived(page.props.theme?.primary_color || '#0c4cb4');
    const secondary = $derived(page.props.theme?.secondary_color || '#fa7315');

    function withOpacity(hex: string, opacity: number): string {
        if (!hex) return '';
        const trimmed = hex.trim();
        if (!trimmed.startsWith('#')) return trimmed;
        let cleanHex = trimmed.slice(1);
        if (cleanHex.length === 8) {
            cleanHex = cleanHex.slice(0, 6);
        } else if (cleanHex.length === 4) {
            cleanHex = cleanHex.slice(0, 3);
        }
        const alphaHex = Math.round(opacity * 255)
            .toString(16)
            .padStart(2, '0');
        return `#${cleanHex}${alphaHex}`;
    }
    const auth = $derived(page.props.auth?.user);
    const storeName = $derived(
        (page.props as any).storeName ||
            (page.props as any).settings?.store_name ||
            page.props.name ||
            'Toko Kami',
    );
    const storeLogo = $derived(
        (page.props as any).storeLogo ||
            (page.props as any).settings?.store_logo,
    );

    // Modal state
    let authModalOpen = $state(false);
    let authTab = $state<'login' | 'register'>('login');

    // Search
    let searchQuery = $state('');

    // Mobile menu
    let mobileMenuOpen = $state(false);

    // Profile dropdown
    let profileDropOpen = $state(false);

    const cartCount = $derived((page.props as any).cartCount || 0);

    function goToCart() {
        if (auth) {
            router.visit('/cart');
        } else {
            openLogin();
        }
    }

    // Login form
    let loginEmail = $state('');
    let loginPassword = $state('');
    let loginError = $state('');
    let loginLoading = $state(false);

    // Register form
    let registerName = $state('');
    let registerEmail = $state('');
    let registerPassword = $state('');
    let registerPasswordConfirmation = $state('');
    let registerError = $state('');
    let registerLoading = $state(false);

    function openLogin() {
        authTab = 'login';
        authModalOpen = true;
        loginError = '';
    }

    onMount(() => {
        const handleOpenLogin = () => openLogin();
        const handleToggleDropdown = () => profileDropOpen = !profileDropOpen;
        
        window.addEventListener('open-login-modal', handleOpenLogin);
        window.addEventListener('toggle-profile-dropdown', handleToggleDropdown);
        
        return () => {
            window.removeEventListener('open-login-modal', handleOpenLogin);
            window.removeEventListener('toggle-profile-dropdown', handleToggleDropdown);
        };
    });

    function openRegister() {
        authTab = 'register';
        authModalOpen = true;
        registerError = '';
    }

    function closeAuthModal() {
        authModalOpen = false;
        loginEmail = '';
        loginPassword = '';
        loginError = '';
        registerName = '';
        registerEmail = '';
        registerPassword = '';
        registerPasswordConfirmation = '';
        registerError = '';
    }

    async function submitLogin(e: Event) {
        e.preventDefault();
        loginLoading = true;
        loginError = '';

        router.post(
            '/login',
            {
                email: loginEmail,
                password: loginPassword,
                remember: false,
            },
            {
                onError: (errors) => {
                    loginError =
                        errors.email ||
                        errors.password ||
                        'Login gagal, coba lagi.';
                    loginLoading = false;
                },
                onSuccess: () => {
                    closeAuthModal();
                    loginLoading = false;
                },
            },
        );
    }

    async function submitRegister(e: Event) {
        e.preventDefault();
        registerLoading = true;
        registerError = '';

        router.post(
            '/register',
            {
                name: registerName,
                email: registerEmail,
                password: registerPassword,
                password_confirmation: registerPasswordConfirmation,
            },
            {
                onError: (errors) => {
                    registerError =
                        errors.email ||
                        errors.password ||
                        errors.name ||
                        'Pendaftaran gagal.';
                    registerLoading = false;
                },
                onSuccess: () => {
                    closeAuthModal();
                    registerLoading = false;
                },
            },
        );
    }

    // Sync search input state with current URL query params
    $effect(() => {
        const urlParams = new URLSearchParams(window.location.search);
        const q = urlParams.get('q') || urlParams.get('search') || '';
        if (q) {
            searchQuery = q;
        }
    });

    function handleSearch(e: Event) {
        e.preventDefault();
        if (searchQuery.trim()) {
            router.get('/search', { q: searchQuery });
        } else {
            router.get('/search');
        }
    }

    function logout() {
        profileDropOpen = false;
        router.post('/logout');
    }

    function getInitials(name: string) {
        if (!name) return 'U';
        return name
            .split(' ')
            .map((n) => n[0])
            .join('')
            .substring(0, 2)
            .toUpperCase();
    }
</script>

<div
    class="min-h-screen flex flex-col bg-slate-50 font-sans"
    style="--primary: {primary}; --secondary: {secondary};"
>
    <!-- ====== NAVBAR ====== -->
    <header
        class="{hideMobileHeader
            ? 'hidden md:block'
            : ''} sticky top-0 z-40 bg-white shadow-sm"
    >
        <!-- Top bar (desktop) -->
        <div
            class="hidden md:block border-b border-slate-100"
            style="background: linear-gradient(135deg, {primary}, {withOpacity(
                primary,
                0.85,
            )});"
        >
            <div
                class="max-w-6xl mx-auto px-6 lg:px-8 py-3 flex items-center justify-between w-full"
            >
                <div class="flex items-center gap-3">
                    <!-- Logo -->
                    <Link
                        href="/"
                        prefetch
                        class="flex items-center gap-2.5 shrink-0"
                    >
                        {#if storeLogo}
                            <img
                                src={storeLogo}
                                alt={storeName}
                                class="h-9 w-auto object-contain"
                            />
                        {:else}
                            <div
                                class="w-9 h-9 rounded-xl flex items-center justify-center text-white text-lg"
                                style="background: rgba(255,255,255,0.2);"
                            >
                                <i class="ti ti-shopping-bag"></i>
                            </div>
                        {/if}
                        <!-- <span class="font-outfit font-black text-xl text-white tracking-tight">{storeName}</span> -->
                    </Link>
                </div>

                <!-- Search bar (desktop) -->
                <form onsubmit={handleSearch} class="flex-grow max-w-2xl mx-8">
                    <div class="relative">
                        <input
                            type="text"
                            bind:value={searchQuery}
                            placeholder="Cari produk, merek, kategori..."
                            class="w-full pl-4 pr-12 py-2.5 text-sm bg-white rounded-2xl border-2 border-transparent focus:border-white focus:outline-none focus:bg-white shadow-md"
                        />
                        <button
                            type="submit"
                            class="absolute right-0 top-0 bottom-0 px-4 rounded-r-2xl text-white font-bold text-sm flex items-center gap-1.5 transition"
                            style="background-color: {secondary};"
                        >
                            <i class="ti ti-search text-lg"></i>
                        </button>
                    </div>
                </form>

                <!-- Right actions (desktop) -->
                <div class="flex items-center gap-4 shrink-0">
                    <!-- Cart -->
                    <button
                        onclick={goToCart}
                        class="relative p-2.5 text-white hover:bg-white/20 rounded-xl transition flex flex-col items-center"
                        aria-label="Keranjang"
                    >
                        <i class="ti ti-shopping-cart text-2xl"></i>
                        {#if cartCount > 0}
                            <span
                                class="absolute -top-1 -right-1 w-5 h-5 rounded-full text-[10px] font-black flex items-center justify-center text-white"
                                style="background-color: {secondary};"
                            >
                                {cartCount}
                            </span>
                        {/if}
                        <span class="text-[10px] font-bold text-white/80 mt-0.5"
                            >Keranjang</span
                        >
                    </button>

                    <!-- Profile / Auth -->
                    {#if auth}
                        <div class="relative">
                            <button
                                onclick={() =>
                                    (profileDropOpen = !profileDropOpen)}
                                class="flex items-center gap-2 text-white hover:bg-white/20 px-3 py-2 rounded-xl transition"
                            >
                                <div
                                    class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center font-black text-xs border border-white/40"
                                >
                                    {getInitials(auth.name)}
                                </div>
                                <div class="text-left">
                                    <p
                                        class="text-xs font-bold text-white leading-tight"
                                    >
                                        {auth.name.split(' ')[0]}
                                    </p>
                                    <p class="text-[10px] text-white/70">
                                        Akun Saya
                                    </p>
                                </div>
                                <i
                                    class="ti ti-chevron-down text-sm text-white/80 transition {profileDropOpen
                                        ? 'rotate-180'
                                        : ''}"
                                ></i>
                            </button>

                            {#if profileDropOpen}
                                <div
                                    transition:fade={{ duration: 150 }}
                                    class="absolute right-0 top-full mt-2 w-52 bg-white rounded-2xl shadow-xl border border-slate-100 overflow-hidden z-50"
                                >
                                    <div class="p-3 border-b border-slate-100">
                                        <p
                                            class="text-sm font-bold text-slate-800"
                                        >
                                            {auth.name}
                                        </p>
                                        <p class="text-xs text-slate-400">
                                            {auth.email}
                                        </p>
                                    </div>
                                    <div class="p-1">
                                        <Link
                                            href="/profile"
                                            prefetch
                                            class="flex items-center gap-2.5 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 rounded-xl transition"
                                        >
                                            <i class="ti ti-user text-base"></i> Profil
                                            Saya
                                        </Link>
                                        <Link
                                            href="/profile/addresses"
                                            prefetch
                                            class="flex items-center gap-2.5 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 rounded-xl transition"
                                        >
                                            <i class="ti ti-map-pin text-base"></i> Alamat Pengiriman
                                        </Link>
                                        <Link
                                            href="/orders"
                                            prefetch
                                            class="flex items-center gap-2.5 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 rounded-xl transition"
                                        >
                                            <i class="ti ti-package text-base"
                                            ></i> Pesanan
                                        </Link>
                                    </div>
                                    <div class="p-1 border-t border-slate-100">
                                        <button
                                            onclick={logout}
                                            class="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-rose-600 hover:bg-rose-50 rounded-xl transition"
                                        >
                                            <i class="ti ti-logout text-base"
                                            ></i> Keluar
                                        </button>
                                    </div>
                                </div>
                            {/if}
                        </div>
                    {:else}
                        <div class="flex items-center gap-2">
                            <button
                                onclick={openLogin}
                                class="px-4 py-2 text-sm font-bold text-white border border-white/40 rounded-xl hover:bg-white/20 transition"
                            >
                                Masuk
                            </button>
                            <button
                                onclick={openRegister}
                                class="px-4 py-2 text-sm font-bold rounded-xl transition shadow-md"
                                style="background-color: {secondary}; color: white;"
                            >
                                Daftar
                            </button>
                        </div>
                    {/if}
                </div>
            </div>
        </div>

        {#if !hideMobileHeader}
            <div
                class="flex md:hidden items-center gap-3 px-4 py-3"
                style="background: linear-gradient(135deg, {primary}, {withOpacity(
                    primary,
                    0.85,
                )});"
            >
                <!-- Logo / Store name (Ubah 'hidden' menjadi 'flex' jika ingin menampilkannya kembali) -->
                <Link
                    href="/"
                    prefetch
                    class="hidden items-center gap-2 shrink-0"
                >
                    {#if storeLogo}
                        <img
                            src={storeLogo}
                            alt={storeName}
                            class="h-7 w-auto"
                        />
                    {:else}
                        <i class="ti ti-shopping-bag text-white text-2xl"></i>
                    {/if}
                    <span class="font-outfit font-black text-base text-white"
                        >{storeName}</span
                    >
                </Link>

                <!-- Mobile search -->
                <form onsubmit={handleSearch} class="flex-grow">
                    <div class="relative">
                        <input
                            type="text"
                            bind:value={searchQuery}
                            placeholder="Cari produk..."
                            class="w-full pl-3 pr-10 py-2 text-sm bg-white/20 rounded-xl border border-white/30 text-white placeholder-white/60 focus:outline-none focus:bg-white focus:text-slate-800 focus:placeholder-slate-400 transition"
                        />
                        <button
                            type="submit"
                            class="absolute right-2 top-1/2 -translate-y-1/2 text-white"
                        >
                            <i class="ti ti-search text-lg"></i>
                        </button>
                    </div>
                </form>

                <!-- Mobile right icons -->
                <div class="flex items-center gap-2 shrink-0">
                    <!-- Cart -->
                    <button
                        onclick={goToCart}
                        class="relative text-white p-1.5"
                        aria-label="Keranjang"
                    >
                        <i class="ti ti-shopping-cart text-2xl"></i>
                        {#if cartCount > 0}
                            <span
                                class="absolute -top-0.5 -right-0.5 w-4 h-4 rounded-full text-[9px] font-black flex items-center justify-center text-white"
                                style="background-color: {secondary};"
                            >
                                {cartCount}
                            </span>
                        {/if}
                    </button>

                    <!-- Profile / Login -->
                    {#if auth}
                        <button
                            onclick={() => (profileDropOpen = !profileDropOpen)}
                            class="w-8 h-8 rounded-full bg-white/20 border border-white/40 flex items-center justify-center font-black text-xs text-white"
                        >
                            {getInitials(auth.name)}
                        </button>
                    {:else}
                        <button
                            onclick={openLogin}
                            class="text-white p-1.5"
                            aria-label="Masuk"
                        >
                            <i class="ti ti-user-circle text-2xl"></i>
                        </button>
                    {/if}
                </div>
            </div>
        {/if}


    </header>

    <!-- Mobile profile dropdown -->
    {#if profileDropOpen && auth}
        <div
            class="md:hidden fixed top-[56px] left-0 right-0 z-[999] bg-white border-b border-slate-100 shadow-2xl"
        >
            <div
                class="p-4 border-b border-slate-100 flex items-center gap-3"
            >
                <div
                    class="w-10 h-10 rounded-full flex items-center justify-center font-black text-sm text-white"
                    style="background-color: {primary};"
                >
                    {getInitials(auth.name)}
                </div>
                <div>
                    <p class="text-sm font-bold text-slate-800">
                        {auth.name}
                    </p>
                    <p class="text-xs text-slate-400">{auth.email}</p>
                </div>
            </div>
            <div class="p-2">
                <Link
                    href="/profile"
                    prefetch
                    class="flex items-center gap-3 px-4 py-3 text-sm text-slate-700 hover:bg-slate-50 rounded-xl transition"
                >
                    <i class="ti ti-user text-lg"></i> Profil Saya
                </Link>
                <Link
                    href="/profile/addresses"
                    prefetch
                    class="flex items-center gap-3 px-4 py-3 text-sm text-slate-700 hover:bg-slate-50 rounded-xl transition"
                >
                    <i class="ti ti-map-pin text-lg"></i> Alamat Pengiriman
                </Link>
                <Link
                    href="/orders"
                    prefetch
                    class="flex items-center gap-3 px-4 py-3 text-sm text-slate-700 hover:bg-slate-50 rounded-xl transition"
                >
                    <i class="ti ti-package text-lg"></i> Pesanan Saya
                </Link>

                <button
                    onclick={logout}
                    class="w-full flex items-center gap-3 px-4 py-3 text-sm text-rose-600 hover:bg-rose-50 rounded-xl transition"
                >
                    <i class="ti ti-logout text-lg"></i> Keluar
                </button>
            </div>
        </div>
    {/if}

    <!-- ====== MAIN CONTENT ====== -->
    <main class="flex-grow">
        {@render children()}
    </main>

    <!-- ====== FOOTER ====== -->
    <footer class="{hideMobileFooter ? 'hidden md:block' : ''} bg-slate-900 text-slate-400 mt-16 py-6">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div
                class="flex flex-col sm:flex-row items-center justify-between gap-4"
            >
                <!-- Brand -->
                <div class="flex items-center gap-2">
                    <div
                        class="w-8 h-8 rounded-xl flex items-center justify-center text-white"
                        style="background: linear-gradient(135deg, {primary}, {secondary});"
                    >
                        <i class="ti ti-shopping-bag text-lg"></i>
                    </div>
                    <span class="font-outfit font-black text-lg text-white"
                        >{storeName}</span
                    >
                </div>

                <!-- Copyright -->
                <p class="text-xs text-slate-500">
                    © {new Date().getFullYear()}
                    {storeName}. Hak cipta dilindungi.
                </p>

                <!-- Socials & Payment info -->
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2">
                        <a
                            href="#"
                            class="w-8 h-8 bg-slate-800 hover:bg-slate-700 rounded-lg flex items-center justify-center transition text-slate-300"
                        >
                            <i class="ti ti-brand-instagram text-base"></i>
                        </a>
                        <a
                            href="#"
                            class="w-8 h-8 bg-slate-800 hover:bg-slate-700 rounded-lg flex items-center justify-center transition text-slate-300"
                        >
                            <i class="ti ti-brand-facebook text-base"></i>
                        </a>
                        <a
                            href="#"
                            class="w-8 h-8 bg-slate-800 hover:bg-slate-700 rounded-lg flex items-center justify-center transition text-slate-300"
                        >
                            <i class="ti ti-brand-whatsapp text-base"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </footer>
</div>

<!-- ====== AUTH MODAL ====== -->
{#if authModalOpen}
    <div
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        transition:fade={{ duration: 150 }}
    >
        <!-- Backdrop -->
        <div
            class="fixed inset-0 bg-slate-900/70 backdrop-blur-sm"
            onclick={closeAuthModal}
        ></div>

        <!-- Modal Box -->
        <div
            class="relative bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden z-10 animate-in zoom-in-95 duration-200"
        >
            <!-- Colored top bar -->
            <div
                class="h-2 w-full"
                style="background: linear-gradient(90deg, {primary}, {secondary});"
            ></div>

            <!-- Modal Header with Tabs -->
            <div class="p-6 pb-0">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-2">
                        <div
                            class="w-9 h-9 rounded-xl flex items-center justify-center text-white"
                            style="background: linear-gradient(135deg, {primary}, {secondary});"
                        >
                            <i class="ti ti-shopping-bag text-lg"></i>
                        </div>
                        <span
                            class="font-outfit font-black text-lg text-slate-800"
                            >{storeName}</span
                        >
                    </div>
                    <button
                        onclick={closeAuthModal}
                        class="text-slate-400 hover:text-slate-700 transition p-1"
                    >
                        <i class="ti ti-x text-xl"></i>
                    </button>
                </div>

                <!-- Tab switcher -->
                <div class="flex bg-slate-100 rounded-2xl p-1 gap-1">
                    <button
                        onclick={() => {
                            authTab = 'login';
                            loginError = '';
                        }}
                        class="flex-1 py-2.5 text-sm font-bold rounded-xl transition {authTab ===
                        'login'
                            ? 'bg-white text-slate-800 shadow-sm'
                            : 'text-slate-500 hover:text-slate-700'}"
                    >
                        Masuk
                    </button>
                    <button
                        onclick={() => {
                            authTab = 'register';
                            registerError = '';
                        }}
                        class="flex-1 py-2.5 text-sm font-bold rounded-xl transition {authTab ===
                        'register'
                            ? 'bg-white text-slate-800 shadow-sm'
                            : 'text-slate-500 hover:text-slate-700'}"
                    >
                        Daftar Akun
                    </button>
                </div>
            </div>

            <div class="p-6">
                <!-- LOGIN TAB -->
                {#if authTab === 'login'}
                    <div transition:slide={{ duration: 150 }}>
                        <h2
                            class="text-xl font-outfit font-black text-slate-800 mb-1"
                        >
                            Selamat Datang Kembali!
                        </h2>
                        <p class="text-sm text-slate-400 mb-5">
                            Masuk untuk melanjutkan belanja Anda.
                        </p>

                        {#if loginError}
                            <div
                                class="mb-4 p-3 bg-rose-50 border border-rose-100 rounded-xl text-sm text-rose-600 font-medium flex items-center gap-2"
                            >
                                <i class="ti ti-alert-circle text-base"></i>
                                {loginError}
                            </div>
                        {/if}

                        <form onsubmit={submitLogin} class="space-y-4">
                            <div>
                                <label
                                    class="block text-xs font-bold text-slate-600 mb-1.5"
                                    >Email</label
                                >
                                <div class="relative">
                                    <i
                                        class="ti ti-mail absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"
                                    ></i>
                                    <input
                                        type="email"
                                        bind:value={loginEmail}
                                        required
                                        placeholder="email@contoh.com"
                                        class="w-full pl-10 pr-4 py-3 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 transition"
                                        style="--tw-ring-color: {primary}30;"
                                    />
                                </div>
                            </div>
                            <div>
                                <label
                                    class="block text-xs font-bold text-slate-600 mb-1.5"
                                    >Kata Sandi</label
                                >
                                <div class="relative">
                                    <i
                                        class="ti ti-lock absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"
                                    ></i>
                                    <input
                                        type="password"
                                        bind:value={loginPassword}
                                        required
                                        placeholder="••••••••"
                                        class="w-full pl-10 pr-4 py-3 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 transition"
                                        style="--tw-ring-color: {primary}30;"
                                    />
                                </div>
                            </div>

                            <div class="flex items-center justify-end">
                                <a
                                    href="#"
                                    class="text-xs font-bold hover:underline"
                                    style="color: {primary};"
                                    >Lupa kata sandi?</a
                                >
                            </div>

                            <button
                                type="submit"
                                disabled={loginLoading}
                                class="w-full py-3 text-sm font-bold text-white rounded-xl transition shadow-lg disabled:opacity-70 flex items-center justify-center gap-2"
                                style="background: linear-gradient(135deg, {primary}, {withOpacity(
                                    primary,
                                    0.8,
                                )});"
                            >
                                {#if loginLoading}
                                    <i
                                        class="ti ti-loader animate-spin text-base"
                                    ></i> Memproses...
                                {:else}
                                    Masuk <i class="ti ti-arrow-right text-base"
                                    ></i>
                                {/if}
                            </button>
                        </form>

                        <p class="text-center text-sm text-slate-400 mt-5">
                            Belum punya akun?
                            <button
                                onclick={() => {
                                    authTab = 'register';
                                    loginError = '';
                                }}
                                class="font-bold hover:underline"
                                style="color: {secondary};"
                            >
                                Daftar sekarang
                            </button>
                        </p>
                    </div>

                    <!-- REGISTER TAB -->
                {:else}
                    <div transition:slide={{ duration: 150 }}>
                        <h2
                            class="text-xl font-outfit font-black text-slate-800 mb-1"
                        >
                            Buat Akun Baru
                        </h2>
                        <p class="text-sm text-slate-400 mb-5">
                            Gratis! Nikmati belanja yang mudah & hemat.
                        </p>

                        {#if registerError}
                            <div
                                class="mb-4 p-3 bg-rose-50 border border-rose-100 rounded-xl text-sm text-rose-600 font-medium flex items-center gap-2"
                            >
                                <i class="ti ti-alert-circle text-base"></i>
                                {registerError}
                            </div>
                        {/if}

                        <form onsubmit={submitRegister} class="space-y-4">
                            <div>
                                <label
                                    class="block text-xs font-bold text-slate-600 mb-1.5"
                                    >Nama Lengkap</label
                                >
                                <div class="relative">
                                    <i
                                        class="ti ti-user absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"
                                    ></i>
                                    <input
                                        type="text"
                                        bind:value={registerName}
                                        required
                                        placeholder="Nama Lengkap Anda"
                                        class="w-full pl-10 pr-4 py-3 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 transition"
                                        style="--tw-ring-color: {primary}30;"
                                    />
                                </div>
                            </div>
                            <div>
                                <label
                                    class="block text-xs font-bold text-slate-600 mb-1.5"
                                    >Email</label
                                >
                                <div class="relative">
                                    <i
                                        class="ti ti-mail absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"
                                    ></i>
                                    <input
                                        type="email"
                                        bind:value={registerEmail}
                                        required
                                        placeholder="email@contoh.com"
                                        class="w-full pl-10 pr-4 py-3 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 transition"
                                        style="--tw-ring-color: {primary}30;"
                                    />
                                </div>
                            </div>
                            <div>
                                <label
                                    class="block text-xs font-bold text-slate-600 mb-1.5"
                                    >Kata Sandi</label
                                >
                                <div class="relative">
                                    <i
                                        class="ti ti-lock absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"
                                    ></i>
                                    <input
                                        type="password"
                                        bind:value={registerPassword}
                                        required
                                        placeholder="Min. 8 karakter"
                                        class="w-full pl-10 pr-4 py-3 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 transition"
                                        style="--tw-ring-color: {primary}30;"
                                    />
                                </div>
                            </div>
                            <div>
                                <label
                                    class="block text-xs font-bold text-slate-600 mb-1.5"
                                    >Konfirmasi Kata Sandi</label
                                >
                                <div class="relative">
                                    <i
                                        class="ti ti-lock-check absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"
                                    ></i>
                                    <input
                                        type="password"
                                        bind:value={
                                            registerPasswordConfirmation
                                        }
                                        required
                                        placeholder="Ulangi kata sandi"
                                        class="w-full pl-10 pr-4 py-3 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 transition"
                                        style="--tw-ring-color: {primary}30;"
                                    />
                                </div>
                            </div>

                            <button
                                type="submit"
                                disabled={registerLoading}
                                class="w-full py-3 text-sm font-bold text-white rounded-xl transition shadow-lg disabled:opacity-70 flex items-center justify-center gap-2"
                                style="background: linear-gradient(135deg, {secondary}, {withOpacity(
                                    secondary,
                                    0.8,
                                )});"
                            >
                                {#if registerLoading}
                                    <i
                                        class="ti ti-loader animate-spin text-base"
                                    ></i> Mendaftarkan...
                                {:else}
                                    Daftar Sekarang <i
                                        class="ti ti-user-plus text-base"
                                    ></i>
                                {/if}
                            </button>
                        </form>

                        <p class="text-center text-sm text-slate-400 mt-5">
                            Sudah punya akun?
                            <button
                                onclick={() => {
                                    authTab = 'login';
                                    registerError = '';
                                }}
                                class="font-bold hover:underline"
                                style="color: {primary};"
                            >
                                Masuk di sini
                            </button>
                        </p>
                    </div>
                {/if}
            </div>
        </div>
    </div>
{/if}

<!-- Click outside to close profile dropdown -->
{#if profileDropOpen}
    <div
        class="fixed inset-0 z-30"
        onclick={() => (profileDropOpen = false)}
    ></div>
{/if}
