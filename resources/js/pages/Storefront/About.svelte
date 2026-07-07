<script lang="ts">
    import StorefrontLayout from '@/components/layouts/StorefrontLayout.svelte';
    import { page, Link } from '@inertiajs/svelte';

    let {
        storeName = '',
        storeLogo = '',
    } = $props();

    const primary = $derived(
        (page.props as any).theme?.primary_color ?? '#ee4d2d',
    );
    const secondary = $derived(
        (page.props as any).theme?.secondary_color ?? '#fa7315',
    );

    function goBack() {
        if (typeof window !== 'undefined' && window.history.length > 1) {
            window.history.back();
        } else {
            window.location.href = '/';
        }
    }
</script>

<svelte:head>
    <title>Tentang Kami</title>
</svelte:head>

<StorefrontLayout hideMobileFooter={true}>
    <div class="w-full md:max-w-6xl md:mx-auto md:px-6 lg:px-8 md:py-8 font-sans">
        <!-- ==================== MOBILE LAYOUT (hidden on desktop) ==================== -->
        <div class="max-w-md mx-auto min-h-[calc(100vh-56px)] md:hidden bg-slate-50 flex flex-col relative pb-20">
            <!-- Header -->
            <div class="sticky top-0 z-30 bg-white border-b border-slate-100 px-4 py-4 flex items-center gap-3">
                <button
                    onclick={goBack}
                    class="p-1 hover:bg-slate-100 rounded-full transition shrink-0"
                    aria-label="Kembali"
                >
                    <i class="ti ti-arrow-left text-xl text-slate-700"></i>
                </button>
                <h1 class="font-outfit font-black text-lg text-slate-800">
                    Tentang Kami
                </h1>
            </div>

            <!-- Content Mobile -->
            <div class="p-4 space-y-4">
                <!-- Hero Card Mobile -->
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
                    <div class="h-32 bg-gradient-to-br from-indigo-600 via-purple-600 to-pink-500 relative flex items-center justify-center p-4 text-center text-white">
                        <div class="absolute inset-0 bg-black/10 backdrop-blur-xs"></div>
                        <div class="relative z-10">
                            {#if storeLogo}
                                <img src={storeLogo} alt={storeName} class="h-12 mx-auto mb-2 object-contain rounded-lg bg-white/10 p-1 backdrop-blur-md" />
                            {:else}
                                <div class="w-12 h-12 rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center text-xl font-black mx-auto mb-2 uppercase tracking-wider">
                                    {storeName.substring(0, 2)}
                                </div>
                            {/if}
                            <h2 class="text-base font-black uppercase tracking-wider">{storeName}</h2>
                        </div>
                    </div>
                    <div class="p-5">
                        <p class="text-xs text-slate-600 font-medium leading-relaxed text-center">
                            Selamat datang di <strong>{storeName}</strong>! Kami berkomitmen untuk menghadirkan pengalaman belanja online terbaik dengan kualitas produk unggulan, harga bersaing, dan pelayanan yang ramah dan terpercaya.
                        </p>
                    </div>
                </div>

                <!-- Features Mobile -->
                <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 space-y-4">
                    <span class="text-[10px] font-black tracking-widest text-slate-400 uppercase block">
                        Kenapa Memilih Kami?
                    </span>
                    <div class="space-y-3">
                        <div class="flex gap-3 items-start">
                            <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                                <i class="ti ti-circle-check text-base"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-slate-800 text-xs">Produk Terjamin</h4>
                                <p class="text-[11px] text-slate-500 mt-0.5 leading-relaxed">
                                    Proses seleksi kualitas ketat sebelum dikirim ke pelanggan.
                                </p>
                            </div>
                        </div>
                        <div class="flex gap-3 items-start">
                            <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                                <i class="ti ti-truck text-base"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-slate-800 text-xs">Pengiriman Cepat</h4>
                                <p class="text-[11px] text-slate-500 mt-0.5 leading-relaxed">
                                    Bekerja sama dengan kurir terpercaya agar tiba tepat waktu.
                                </p>
                            </div>
                        </div>
                        <div class="flex gap-3 items-start">
                            <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                                <i class="ti ti-shield text-base"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-slate-800 text-xs">Belanja Aman</h4>
                                <p class="text-[11px] text-slate-500 mt-0.5 leading-relaxed">
                                    Sistem pembayaran aman dan garansi retur/refund mudah.
                                </p>
                            </div>
                        </div>
                        <div class="flex gap-3 items-start">
                            <div class="w-8 h-8 rounded-lg bg-violet-50 text-violet-600 flex items-center justify-center shrink-0">
                                <i class="ti ti-headset text-base"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-slate-800 text-xs">Layanan Bantuan</h4>
                                <p class="text-[11px] text-slate-500 mt-0.5 leading-relaxed">
                                    Tim CS responsif melayani via fitur chat interaktif.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CTA Mobile -->
                <div class="pt-2">
                    <Link
                        href="/"
                        class="w-full py-3.5 rounded-2xl font-bold text-white shadow-lg transition flex items-center justify-center gap-2 hover:opacity-90 active:scale-95 text-center text-xs uppercase tracking-wider font-outfit font-black"
                        style="background-color: {primary};"
                    >
                        <i class="ti ti-shopping-cart text-sm"></i> Mulai Belanja Sekarang
                    </Link>
                </div>
            </div>
        </div>

        <!-- ==================== DESKTOP LAYOUT (hidden on mobile) ==================== -->
        <div class="hidden md:block max-w-6xl mx-auto w-full">
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden p-6 sm:p-8 space-y-6">
                <!-- Header Desktop -->
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                    <div class="flex items-center gap-3">
                        <button
                            onclick={goBack}
                            class="w-9 h-9 flex items-center justify-center hover:bg-slate-100 rounded-full transition shrink-0"
                            aria-label="Kembali"
                        >
                            <i class="ti ti-arrow-left text-xl text-slate-700"></i>
                        </button>
                        <div>
                            <h1 class="font-outfit font-black text-xl text-slate-800">
                                Tentang Kami
                            </h1>
                            <p class="text-xs text-slate-400 font-medium mt-1">
                                Informasi lengkap mengenai platform e-commerce kami.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Content Desktop -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 pt-2">
                    <!-- Left: Hero/Branding -->
                    <div class="col-span-1 space-y-6">
                        <div class="bg-slate-50 border border-slate-100 rounded-2xl overflow-hidden shadow-xs">
                            <div class="h-32 bg-gradient-to-br from-indigo-600 via-purple-600 to-pink-500 relative flex items-center justify-center p-4 text-white">
                                <div class="absolute inset-0 bg-black/10 backdrop-blur-xs"></div>
                                <div class="relative z-10 text-center">
                                    {#if storeLogo}
                                        <img src={storeLogo} alt={storeName} class="h-12 mx-auto mb-2 object-contain rounded-lg bg-white/10 p-1 backdrop-blur-md" />
                                    {:else}
                                        <div class="w-12 h-12 rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center text-xl font-black mx-auto mb-2 uppercase tracking-wider">
                                            {storeName.substring(0, 2)}
                                        </div>
                                    {/if}
                                    <h2 class="text-base font-black uppercase tracking-wider">{storeName}</h2>
                                </div>
                            </div>
                            <div class="p-6">
                                <p class="text-xs text-slate-600 font-medium leading-relaxed text-center">
                                    Selamat datang di <strong>{storeName}</strong>! Kami berkomitmen untuk menghadirkan pengalaman belanja online terbaik dengan kualitas produk unggulan, harga bersaing, dan pelayanan yang ramah dan terpercaya.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Features & CTA -->
                    <div class="col-span-2 space-y-6">
                        <div class="border border-slate-100 rounded-2xl p-6 bg-white shadow-xs space-y-6">
                            <span class="text-xs font-black tracking-widest text-slate-400 uppercase block border-b border-slate-100 pb-2">
                                Kenapa Memilih Kami?
                            </span>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                <div class="flex gap-4 items-start">
                                    <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                                        <i class="ti ti-circle-check text-xl"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-slate-800 text-sm">Produk Terjamin</h4>
                                        <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                                            Proses seleksi kualitas ketat sebelum dikirim ke pelanggan.
                                        </p>
                                    </div>
                                </div>
                                <div class="flex gap-4 items-start">
                                    <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                                        <i class="ti ti-truck text-xl"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-slate-800 text-sm">Pengiriman Cepat</h4>
                                        <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                                            Bekerja sama dengan kurir terpercaya agar tiba tepat waktu.
                                        </p>
                                    </div>
                                </div>
                                <div class="flex gap-4 items-start">
                                    <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                                        <i class="ti ti-shield text-xl"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-slate-800 text-sm">Belanja Aman</h4>
                                        <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                                            Sistem pembayaran aman dan garansi retur/refund mudah.
                                        </p>
                                    </div>
                                </div>
                                <div class="flex gap-4 items-start">
                                    <div class="w-10 h-10 rounded-xl bg-violet-50 text-violet-600 flex items-center justify-center shrink-0">
                                        <i class="ti ti-headset text-xl"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-slate-800 text-sm">Layanan Bantuan</h4>
                                        <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                                            Tim CS responsif melayani via fitur chat interaktif.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- CTA Desktop -->
                        <div class="flex justify-end">
                            <Link
                                href="/"
                                class="inline-flex items-center gap-2 px-6 py-3.5 rounded-2xl font-black uppercase text-xs tracking-wider text-white shadow-md transition active:scale-95 hover:opacity-95 cursor-pointer font-outfit"
                                style="background: {primary};"
                            >
                                <i class="ti ti-shopping-cart text-sm"></i> Mulai Belanja Sekarang
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</StorefrontLayout>
