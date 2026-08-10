<script lang="ts">
    import AccountLayout from '@/components/layouts/AccountLayout.svelte';
    import { page, Link } from '@inertiajs/svelte';

    let { storeName = '', storeLogo = '' } = $props();

    const primary = $derived(
        (page.props as any).theme?.primary_color ?? '#fa7315',
    );
    const secondary = $derived(
        (page.props as any).theme?.secondary_color ?? '#0c4cb4',
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
    <title>Tentang Kami — {storeName || 'Toko Online'}</title>
</svelte:head>

<AccountLayout activeMenu="about">
    <div
        class="bg-white rounded-none sm:rounded-2xl border-y sm:border border-slate-200/80 shadow-2xs p-4 sm:p-6 md:p-8 space-y-6"
    >
        <!-- Card Header -->
        <div
            class="border-b border-slate-100 pb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
        >
            <div>
                <h1 class="text-lg font-bold text-slate-800 font-outfit">
                    Tentang {storeName || 'Kami'}
                </h1>
                <p class="text-xs text-slate-400 font-medium mt-0.5">
                    Mengenal lebih dekat layanan, komitmen, dan keunggulan
                    belanja di {storeName || 'toko kami'}
                </p>
            </div>
            <!-- <button
                type="button"
                onclick={goBack}
                class="hidden sm:inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 transition cursor-pointer shrink-0"
            >
                <i class="ti ti-arrow-left text-sm"></i>
                Kembali
            </button> -->
        </div>

        <!-- Banner / Intro Hero Box -->
        <div
            class="relative rounded-2xl overflow-hidden shadow-2xs border border-slate-100"
        >
            <div
                class="h-36 sm:h-44 relative flex items-center justify-center p-6 text-center text-white"
                style="background: linear-gradient(135deg, {primary}, {secondary});"
            >
                <div
                    class="absolute inset-0 bg-black/10 backdrop-blur-xs"
                ></div>
                <div class="relative z-10 space-y-2">
                    {#if storeLogo}
                        <img
                            src={storeLogo}
                            alt={storeName}
                            class="h-12 sm:h-14 mx-auto object-contain rounded-xl bg-white/20 p-1.5 backdrop-blur-md shadow-md"
                        />
                    {:else}
                        <div
                            class="w-14 h-14 rounded-2xl bg-white/20 backdrop-blur-md flex items-center justify-center text-2xl font-black mx-auto uppercase tracking-wider shadow-md"
                        >
                            {storeName ? storeName.substring(0, 2) : 'BM'}
                        </div>
                    {/if}
                    <h2
                        class="text-lg sm:text-xl font-black uppercase tracking-wider font-outfit"
                    >
                        {storeName}
                    </h2>
                    <p
                        class="text-xs text-white/90 font-medium max-w-md mx-auto line-clamp-2"
                    >
                        Platform e-commerce belanja online aman, tepercaya, dan
                        hemat untuk semua kebutuhan Anda.
                    </p>
                </div>
            </div>
        </div>

        <!-- Two-Column Section (Main Description + Brand Benefits) -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 items-start">
            <!-- Left Column: Key Advantages & Features (2/3 width) -->
            <div class="md:col-span-2 space-y-6">
                <div class="space-y-2">
                    <h3
                        class="text-sm font-bold text-slate-800 font-outfit uppercase tracking-wider"
                    >
                        Komitmen & Pelayanan Kami
                    </h3>
                    <p
                        class="text-xs text-slate-600 font-medium leading-relaxed"
                    >
                        Selamat datang di <strong>{storeName}</strong>! Kami
                        selalu berkomitmen untuk memberikan pengalaman belanja
                        online terbaik. Mulai dari kemudahan pencarian produk,
                        kejelasan informasi harga, jaminan barang berkualitas,
                        hingga kemudahan pembayaran dan proses retur yang
                        transparan.
                    </p>
                </div>

                <div class="space-y-3">
                    <span
                        class="text-xs font-bold text-slate-400 uppercase tracking-wider block"
                    >
                        Kenapa Memilih Belanja di {storeName}?
                    </span>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div
                            class="p-4 rounded-xl border border-slate-100 bg-slate-50/60 flex items-start gap-3"
                        >
                            <div
                                class="w-9 h-9 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center shrink-0"
                            >
                                <i class="ti ti-circle-check text-lg"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-slate-800 text-xs">
                                    Produk Terjamin Original
                                </h4>
                                <p
                                    class="text-[11px] text-slate-500 mt-0.5 leading-relaxed"
                                >
                                    Proses penyeleksian ketat untuk memastikan
                                    kualitas produk terbaik.
                                </p>
                            </div>
                        </div>

                        <div
                            class="p-4 rounded-xl border border-slate-100 bg-slate-50/60 flex items-start gap-3"
                        >
                            <div
                                class="w-9 h-9 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0"
                            >
                                <i class="ti ti-truck text-lg"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-slate-800 text-xs">
                                    Pengiriman Cepat & Aman
                                </h4>
                                <p
                                    class="text-[11px] text-slate-500 mt-0.5 leading-relaxed"
                                >
                                    Bekerja sama dengan jasa ekspedisi
                                    terpercaya ke seluruh Indonesia.
                                </p>
                            </div>
                        </div>

                        <div
                            class="p-4 rounded-xl border border-slate-100 bg-slate-50/60 flex items-start gap-3"
                        >
                            <div
                                class="w-9 h-9 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center shrink-0"
                            >
                                <i class="ti ti-shield-check text-lg"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-slate-800 text-xs">
                                    Jaminan Transaksi Aman
                                </h4>
                                <p
                                    class="text-[11px] text-slate-500 mt-0.5 leading-relaxed"
                                >
                                    Sistem pembayaran terlindungi dan prosedur
                                    retur dana transparan.
                                </p>
                            </div>
                        </div>

                        <div
                            class="p-4 rounded-xl border border-slate-100 bg-slate-50/60 flex items-start gap-3"
                        >
                            <div
                                class="w-9 h-9 rounded-xl bg-violet-100 text-violet-600 flex items-center justify-center shrink-0"
                            >
                                <i class="ti ti-headset text-lg"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-slate-800 text-xs">
                                    Customer Care Support
                                </h4>
                                <p
                                    class="text-[11px] text-slate-500 mt-0.5 leading-relaxed"
                                >
                                    Tim layanan pelanggan ramah dan siap
                                    membantu kebutuhan Anda.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pt-2">
                    <Link
                        href="/"
                        class="px-6 py-3 rounded-xl font-bold text-xs text-white shadow-md hover:opacity-90 active:scale-95 transition inline-flex items-center justify-center gap-2"
                        style="background-color: {primary};"
                    >
                        <i class="ti ti-shopping-cart text-sm"></i>
                        Mulai Belanja Sekarang
                    </Link>
                </div>
            </div>

            <!-- Right Column: Store Summary & Quick Guarantee Badge (1/3 width with left border line) -->
            <div
                class="md:col-span-1 border-t md:border-t-0 md:border-l border-slate-100 pt-6 md:pt-0 md:pl-8 flex flex-col items-center text-center space-y-4"
            >
                <div
                    class="w-20 h-20 rounded-2xl bg-slate-100 border-2 border-slate-200/80 shadow-2xs overflow-hidden flex items-center justify-center"
                >
                    {#if storeLogo}
                        <img
                            src={storeLogo}
                            alt={storeName}
                            class="w-full h-full object-contain p-2"
                        />
                    {:else}
                        <i class="ti ti-building-store text-3xl text-slate-400"
                        ></i>
                    {/if}
                </div>

                <div>
                    <h4 class="font-outfit font-black text-sm text-slate-800">
                        {storeName}
                    </h4>
                    <span
                        class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2.5 py-0.5 rounded-full border border-emerald-100 mt-1 inline-block"
                    >
                        Toko Resmi Terverifikasi
                    </span>
                </div>

                <div class="w-full pt-2 space-y-2 text-left">
                    <div
                        class="p-3 rounded-xl bg-slate-50 border border-slate-100 flex items-center gap-2.5 text-xs text-slate-700 font-medium"
                    >
                        <i class="ti ti-shield-check text-base text-blue-600"
                        ></i>
                        <span>100% Produk Original</span>
                    </div>
                    <div
                        class="p-3 rounded-xl bg-slate-50 border border-slate-100 flex items-center gap-2.5 text-xs text-slate-700 font-medium"
                    >
                        <i
                            class="ti ti-rotate-clockwise-2 text-base text-emerald-600"
                        ></i>
                        <span>Garansi Retur 7 Hari</span>
                    </div>
                    <div
                        class="p-3 rounded-xl bg-slate-50 border border-slate-100 flex items-center gap-2.5 text-xs text-slate-700 font-medium"
                    >
                        <i class="ti ti-message-dots text-base text-amber-600"
                        ></i>
                        <span>Layanan Chat Interaktif</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</AccountLayout>
