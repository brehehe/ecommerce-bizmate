<script lang="ts">
    import { useForm, page, Link } from '@inertiajs/svelte';
    import { slide } from 'svelte/transition';
    import { showToast } from '@/utils/toast';

    let { storeName = '', storeLogo = '' } = $props();

    const primaryColor = $derived((page.props as any).theme?.primary_color || '#0c4cb4');
    const secondaryColor = $derived((page.props as any).theme?.secondary_color || '#fa7315');

    const form = useForm({
        email: '',
    });

    const submit = () => {
        form.post('/forgot-password', {
            onSuccess: () => {
                form.reset();
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                showToast(firstError as string, 'error', 'top');
            }
        });
    };
</script>

<svelte:head>
    <title>Lupa Kata Sandi - {storeName || 'Bizmate'}</title>
</svelte:head>

<div
    class="grid grid-cols-1 lg:grid-cols-2 min-h-dvh w-full font-sans bg-white selection:bg-slate-900 selection:text-white overflow-x-hidden"
>
    <!-- Left Side: Image & Info (Hidden on Mobile/Tablet) -->
    <div class="hidden lg:flex relative bg-slate-900 overflow-hidden items-center justify-center p-12">
        <div class="absolute inset-0 z-0">
            <img
                src="https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?q=80&w=1000&auto=format&fit=crop"
                alt="E-commerce abstract"
                class="w-full h-full object-cover opacity-60"
            />
            <div class="absolute inset-0 bg-slate-900/65 mix-blend-multiply"></div>
            <div
                class="absolute inset-0 mix-blend-overlay opacity-80"
                style="background: linear-gradient(to bottom right, {primaryColor}, {secondaryColor});"
            ></div>
        </div>

        <div class="relative z-10 px-8 flex flex-col justify-center max-w-lg text-white">
            <div class="w-16 h-16 rounded-3xl shadow-2xl flex items-center justify-center text-white text-3xl mb-8 backdrop-blur-md bg-white/20 border border-white/30 transition-transform hover:scale-105">
                <i class="ti ti-lock-open"></i>
            </div>

            <h1 class="text-4xl font-outfit font-black mb-6 leading-tight tracking-tight">
                Pulihkan akses ke akun Anda.
            </h1>
            <p class="text-base text-slate-200 font-medium leading-relaxed">
                Masukkan email Anda dan kami akan mengirimkan instruksi untuk menyetel ulang kata sandi Anda secara aman.
            </p>
        </div>
    </div>

    <!-- Right Side: Form -->
    <div class="flex flex-col justify-center py-10 px-4 sm:px-12 md:px-16 lg:px-20 bg-white relative overflow-hidden min-h-dvh">
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
            <!-- Back to Login Link -->
            <div class="mb-8">
                <Link
                    href="/login"
                    class="inline-flex items-center gap-2 text-sm font-bold text-slate-500 hover:text-slate-800 transition"
                >
                    <i class="ti ti-arrow-left text-lg"></i>
                    Kembali ke Halaman Login
                </Link>
            </div>

            <h2 class="text-3xl sm:text-4xl font-outfit font-black text-slate-900 tracking-tight mb-2">
                Lupa Kata Sandi
            </h2>
            <p class="text-sm text-slate-500 font-medium mb-8">
                Kami akan mengirimkan link untuk mengatur ulang kata sandi Anda.
            </p>

            <form
                class="space-y-6"
                onsubmit={(e) => {
                    e.preventDefault();
                    submit();
                }}
            >
                {#if (page.props as any).flash?.success}
                    <div
                        transition:slide
                        class="p-4 bg-emerald-50 border border-emerald-100 rounded-xl flex items-start gap-3"
                    >
                        <i class="ti ti-circle-check text-emerald-500 text-xl mt-0.5"></i>
                        <p class="text-sm font-bold text-emerald-600">
                            {(page.props as any).flash.success}
                        </p>
                    </div>
                {/if}

                {#if form.errors.email}
                    <div
                        transition:slide
                        class="p-4 bg-rose-50 border border-rose-100 rounded-xl flex items-start gap-3"
                    >
                        <i class="ti ti-alert-circle text-rose-500 text-xl mt-0.5"></i>
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
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="ti ti-mail text-slate-400 text-lg transition-colors group-focus-within:text-slate-600"></i>
                        </div>
                        <input
                            id="email"
                            type="email"
                            bind:value={form.email}
                            required
                            class="block w-full pl-11 pr-4 py-3.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:outline-none transition-all bg-slate-50 focus:bg-white hover:border-slate-300"
                            style="--tw-ring-color: {primaryColor}30; focus-border-color: {primaryColor};"
                            placeholder="email@contoh.com"
                        />
                    </div>
                </div>

                <div class="pt-4">
                    <button
                        type="submit"
                        disabled={form.processing}
                        class="w-full flex justify-center items-center py-4 px-4 border border-transparent rounded-xl shadow-lg text-sm font-bold text-white transition-all hover:shadow-xl hover:-translate-y-0.5 focus:outline-none focus:ring-4 disabled:opacity-70 disabled:hover:translate-y-0 font-outfit uppercase tracking-wider active:scale-[0.98]"
                        style="background-color: {primaryColor}; --tw-ring-color: {primaryColor}50;"
                    >
                        {#if form.processing}
                            <i class="ti ti-loader animate-spin mr-2 text-xl"></i> Mengirim...
                        {:else}
                            Kirim Link Reset <i class="ti ti-send ml-2 text-lg"></i>
                        {/if}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
