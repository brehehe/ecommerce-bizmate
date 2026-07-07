<script lang="ts">
    import { useForm, page, Link } from '@inertiajs/svelte';
    import { slide } from 'svelte/transition';
    import { showToast } from '@/utils/toast';

    const primaryColor = $derived(
        (page.props as any).theme?.primary_color || '#0c4cb4',
    );
    const secondaryColor = $derived(
        (page.props as any).theme?.secondary_color || '#fa7315',
    );

    const storeName = $derived(
        (page.props as any).settings?.store_name || 'Bizmate',
    );
    const storeLogo = $derived(
        (page.props as any).settings?.store_logo || null,
    );

    const userEmail = $derived(
        (page.props as any).auth?.user?.email || 'email Anda',
    );

    const form = useForm({});

    const submit = () => {
        form.post('/email/verification-notification', {
            onSuccess: () => {
                showToast(
                    'Link verifikasi berhasil dikirim!',
                    'success',
                    'top',
                );
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0] as string;
                showToast(
                    firstError || 'Gagal mengirim link verifikasi.',
                    'error',
                    'top',
                );
            },
        });
    };
</script>

<svelte:head>
    <title>Verifikasi Email - {storeName}</title>
</svelte:head>

<div
    class="grid grid-cols-1 lg:grid-cols-2 min-h-dvh w-full font-sans bg-white selection:bg-slate-900 selection:text-white overflow-x-hidden"
>
    <!-- Left Side: Image & Info (Hidden on Mobile/Tablet) -->
    <div
        class="hidden lg:flex relative bg-slate-900 overflow-hidden items-center justify-center p-12"
    >
        <div class="absolute inset-0 z-0">
            <img
                src="https://images.unsplash.com/photo-1557200134-90327ee9fafa?q=80&w=1000&auto=format&fit=crop"
                alt="E-commerce abstract"
                class="w-full h-full object-cover opacity-60"
            />
            <div
                class="absolute inset-0 bg-slate-900/65 mix-blend-multiply"
            ></div>
            <div
                class="absolute inset-0 mix-blend-overlay opacity-80"
                style="background: linear-gradient(to bottom right, {primaryColor}, {secondaryColor});"
            ></div>
        </div>

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
                    <i class="ti ti-mail-fast"></i>
                </div>
            {/if}

            <h1
                class="text-4xl font-outfit font-black mb-6 leading-tight tracking-tight"
            >
                Verifikasikan Email Anda.
            </h1>
            <p class="text-base text-slate-200 font-medium leading-relaxed">
                Kami telah mengirimkan tautan verifikasi ke email Anda. Silakan
                periksa kotak masuk atau folder spam Anda untuk mengaktifkan
                akun secara penuh.
            </p>
        </div>
    </div>

    <!-- Right Side: Content -->
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
            <!-- Logo (Hanya muncul di Mobile) -->
            <div class="flex lg:hidden items-center gap-3 mb-8">
                {#if storeLogo}
                    <img
                        src={storeLogo}
                        alt="Store Icon"
                        class="w-12 h-12 object-contain"
                    />
                {:else}
                    <div
                        class="w-12 h-12 rounded-2xl shadow-md flex items-center justify-center text-white text-2xl"
                        style="background: linear-gradient(to bottom right, {primaryColor}, {secondaryColor});"
                    >
                        <i class="ti ti-mail"></i>
                    </div>
                {/if}
                <div class="flex flex-col">
                    <span
                        class="font-outfit font-black text-2xl text-slate-800 tracking-tight leading-none"
                        >{storeName}</span
                    >
                    <span
                        class="font-sans font-bold text-[10px] text-slate-400 tracking-widest mt-1 uppercase"
                        >E-commerce Portal</span
                    >
                </div>
            </div>

            <div
                class="w-16 h-16 rounded-3xl bg-blue-50 text-blue-600 flex items-center justify-center text-3xl mb-6"
                style="color: {primaryColor}; background-color: {primaryColor}12;"
            >
                <i class="ti ti-mail-opened"></i>
            </div>

            <h2
                class="text-3xl sm:text-4xl font-outfit font-black text-slate-900 tracking-tight mb-2"
            >
                Verifikasi Email
            </h2>
            <p class="text-sm text-slate-500 font-medium mb-6">
                Terima kasih telah mendaftar! Sebelum memulai, silakan
                verifikasi alamat email Anda dengan mengeklik tautan yang baru
                saja kami kirimkan ke:
                <strong
                    class="text-slate-800 block mt-1 break-all font-semibold"
                    >{userEmail}</strong
                >
            </p>

            {#if (page.props as any).flash?.success}
                <div
                    transition:slide
                    class="p-4 mb-6 bg-emerald-50 border border-emerald-100 rounded-xl flex items-start gap-3"
                >
                    <i
                        class="ti ti-circle-check text-emerald-500 text-xl mt-0.5"
                    ></i>
                    <p class="text-sm font-bold text-emerald-600">
                        {(page.props as any).flash.success}
                    </p>
                </div>
            {/if}

            <form
                class="space-y-4"
                onsubmit={(e) => {
                    e.preventDefault();
                    submit();
                }}
            >
                <div>
                    <button
                        type="submit"
                        disabled={form.processing}
                        class="w-full flex justify-center items-center py-4 px-4 border border-transparent rounded-xl shadow-lg text-sm font-bold text-white transition-all hover:shadow-xl hover:-translate-y-0.5 focus:outline-none focus:ring-4 disabled:opacity-70 disabled:hover:translate-y-0 font-outfit uppercase tracking-wider active:scale-[0.98]"
                        style="background-color: {primaryColor}; --tw-ring-color: {primaryColor}50;"
                    >
                        {#if form.processing}
                            <i class="ti ti-loader animate-spin mr-2 text-xl"
                            ></i> Mengirim Ulang...
                        {:else}
                            Kirim Ulang Email Verifikasi <i
                                class="ti ti-send ml-2 text-lg"
                            ></i>
                        {/if}
                    </button>
                </div>
            </form>

            <div
                class="mt-6 flex items-center justify-between border-t border-slate-100 pt-6"
            >
                <Link
                    href="/"
                    class="text-xs font-bold text-slate-500 hover:text-slate-800 transition flex items-center gap-1.5"
                >
                    <i class="ti ti-arrow-left text-sm"></i>
                    Kembali ke Beranda
                </Link>

                <Link
                    href="/logout"
                    method="post"
                    as="button"
                    class="text-xs font-bold text-rose-500 hover:text-rose-700 transition flex items-center gap-1.5 cursor-pointer bg-transparent border-0 p-0"
                >
                    <i class="ti ti-logout text-sm"></i>
                    Keluar Akun
                </Link>
            </div>
        </div>
    </div>
</div>
