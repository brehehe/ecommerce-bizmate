<script lang="ts">
    import { useForm, page } from '@inertiajs/svelte';
    import { slide } from 'svelte/transition';

    // Fallback colors if settings aren't loaded yet globally
    const primaryColor = $derived(page.props.theme?.primary_color || '#0c4cb4');
    const secondaryColor = $derived(
        page.props.theme?.secondary_color || '#fa7315',
    );

    const form = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = () => {
        form.post('/login');
    };
</script>

<svelte:head>
    <title>Login - Bizmate</title>
</svelte:head>

<div
    class="min-h-screen flex font-sans selection:text-white"
    style="selection:bg-color: {primaryColor};"
>
    <!-- Bagian Kiri: Gambar & Informasi (Disembunyikan di Mobile) -->
    <div
        class="hidden lg:flex lg:w-1/2 relative bg-slate-900 overflow-hidden items-center justify-center"
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
            class="relative z-10 px-12 lg:px-24 flex flex-col justify-center max-w-2xl text-white"
        >
            <div
                class="w-16 h-16 rounded-3xl shadow-2xl flex items-center justify-center text-white text-3xl mb-8 backdrop-blur-md bg-white/20 border border-white/30 transition-transform hover:scale-105"
            >
                <i class="ti ti-sofa"></i>
            </div>

            <h1
                class="text-4xl lg:text-5xl font-outfit font-black mb-6 leading-tight tracking-tight"
            >
                Kelola bisnis Anda dengan lebih mudah.
            </h1>
            <p class="text-lg text-slate-200 font-medium leading-relaxed mb-10">
                Bizmate memberikan Anda kendali penuh atas toko online,
                manajemen inventaris, laporan keuangan, dan pelanggan—semuanya
                dalam satu dashboard elegan yang terpusat.
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
        class="w-full lg:w-1/2 flex flex-col justify-center py-12 px-6 sm:px-12 lg:px-24 bg-white relative"
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

        <div class="max-w-md w-full mx-auto relative z-10">
            <!-- Logo (Hanya muncul di Mobile) -->
            <div class="flex lg:hidden items-center gap-3 mb-10">
                <div
                    class="w-12 h-12 rounded-2xl shadow-md flex items-center justify-center text-white text-2xl"
                    style="background: linear-gradient(to bottom right, {primaryColor}, {secondaryColor});"
                >
                    <i class="ti ti-sofa"></i>
                </div>
                <div class="flex flex-col">
                    <span
                        class="font-outfit font-black text-2xl text-slate-800 tracking-tight leading-none"
                        >bizmate</span
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
            <p class="text-sm text-slate-500 font-medium mb-10">
                Silakan masuk menggunakan kredensial toko Anda.
            </p>

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
                        class="p-4 bg-rose-50 border border-rose-100 rounded-xl flex items-start gap-3"
                    >
                        <i
                            class="ti ti-alert-circle text-rose-500 text-xl mt-0.5"
                        ></i>
                        <p class="text-sm font-bold text-rose-600">
                            {form.errors.email}
                        </p>
                    </div>
                {/if}

                <div class="group">
                    <label
                        for="email"
                        class="block text-sm font-bold text-slate-700 mb-2 transition-colors group-focus-within:text-slate-900"
                    >
                        Alamat Email
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
                            type="email"
                            bind:value={form.email}
                            required
                            class="block w-full pl-11 pr-4 py-3.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:outline-none transition-all bg-slate-50 focus:bg-white hover:border-slate-300"
                            style="--tw-ring-color: {primaryColor}30; focus-border-color: {primaryColor};"
                            placeholder="admin@bizmate.com"
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
                            type="password"
                            bind:value={form.password}
                            required
                            class="block w-full pl-11 pr-4 py-3.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:outline-none transition-all bg-slate-50 focus:bg-white hover:border-slate-300"
                            style="--tw-ring-color: {primaryColor}30;"
                            placeholder="••••••••"
                        />
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
                        <a
                            href="#forgot"
                            class="font-bold hover:underline transition-opacity hover:opacity-80"
                            style="color: {primaryColor};"
                        >
                            Lupa kata sandi?
                        </a>
                    </div>
                </div>

                <div class="pt-4">
                    <button
                        type="submit"
                        disabled={form.processing}
                        class="w-full flex justify-center items-center py-4 px-4 border border-transparent rounded-xl shadow-lg shadow-blue-900/10 text-sm font-bold text-white transition-all hover:shadow-xl hover:-translate-y-0.5 focus:outline-none focus:ring-4 disabled:opacity-70 disabled:hover:translate-y-0 font-outfit uppercase tracking-wider"
                        style="background-color: {primaryColor}; --tw-ring-color: {primaryColor}50;"
                    >
                        {#if form.processing}
                            <i class="ti ti-loader animate-spin mr-2 text-xl"
                            ></i> Memproses...
                        {:else}
                            Masuk ke Dashboard <i
                                class="ti ti-arrow-right ml-2 text-xl"
                            ></i>
                        {/if}
                    </button>
                </div>
            </form>

            <div
                class="mt-12 text-center text-sm font-medium text-slate-500 bg-slate-50 py-4 rounded-2xl border border-slate-100"
            >
                <p>Kredensial Uji Coba</p>
                <div class="mt-2 flex justify-center gap-3">
                    <span
                        class="px-3 py-1.5 bg-white rounded-lg text-xs font-bold text-slate-700 shadow-sm border border-slate-200"
                        >admin@bizmate.com</span
                    >
                    <span
                        class="px-3 py-1.5 bg-white rounded-lg text-xs font-bold text-slate-700 shadow-sm border border-slate-200"
                        >password</span
                    >
                </div>
            </div>
        </div>
    </div>
</div>
