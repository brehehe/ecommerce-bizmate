<script lang="ts">
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { page, useForm } from '@inertiajs/svelte';
    import { showToast } from '@/utils/toast';
    import Toggle from '@/components/ui/Toggle.svelte';
    import InputCurrency from '@/components/ui/InputCurrency.svelte';
    import Input from '@/components/ui/Input.svelte';

    let {
        settings = {} as any,
        isSellerMode = false,
    } = $props();

    const primaryColor = $derived(
        (page.props as any).theme?.primary_color ?? '#0c4cb4',
    );

    // svelte-ignore state_referenced_locally
    const form = useForm({
        product_listing_daily_rate: Number(settings.product_listing_daily_rate || 1000),
        product_listing_max_custom_days: Number(settings.product_listing_max_custom_days || 15),
        product_listing_custom_daily_rate: Number(settings.product_listing_custom_daily_rate || 1000),
        product_listing_fee_enabled: Boolean(settings.product_listing_fee_enabled ?? true),
        product_listing_packages: Array.isArray(settings.product_listing_packages) && settings.product_listing_packages.length > 0
            ? settings.product_listing_packages
            : [
                { id: 'pkg_15', name: 'Paket 15 Hari', days: 15, price: 15000, is_popular: false },
                { id: 'pkg_30', name: 'Paket 30 Hari', days: 30, price: 30000, is_popular: true },
            ],
    });

    function addPackageRow() {
        const count = form.product_listing_packages.length + 1;
        const defaultDays = count * 10;
        form.product_listing_packages = [
            ...form.product_listing_packages,
            {
                id: 'pkg_' + Date.now(),
                name: `Paket ${defaultDays} Hari`,
                days: defaultDays,
                price: defaultDays * 1000,
                is_popular: false,
            },
        ];
    }

    function removePackageRow(index: number) {
        if (form.product_listing_packages.length <= 1) {
            showToast('Minimal harus ada 1 paket masa aktif.', 'warning');
            return;
        }
        form.product_listing_packages = form.product_listing_packages.filter((_, i) => i !== index);
    }

    function handleSubmit(e: Event) {
        e.preventDefault();

        form.post('/admin/master-data/price-upload', {
            preserveScroll: true,
            onSuccess: () => {
                showToast('Pengaturan biaya upload produk berhasil disimpan!', 'success');
            },
            onError: (err: any) => {
                const first = Object.values(err)[0] as string;
                showToast(first ?? 'Gagal menyimpan pengaturan.', 'error');
            },
        });
    }

    function fmt(val: number): string {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
        }).format(val || 0);
    }
</script>

<svelte:head>
    <title>Biaya Upload Produk — Master Data</title>
</svelte:head>

<AdminLayout>
    <main class="w-full max-w-[1600px] mx-auto px-4 sm:px-6 py-6 space-y-6">
        <!-- Page Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl font-bold tracking-tight text-slate-900">
                        Pengaturan Biaya & Masa Aktif Upload Produk
                    </h1>
                    <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-extrabold text-amber-800 border border-amber-200">
                        Mode Seller (Multi-Tenant)
                    </span>
                </div>
                <p class="mt-1 text-xs text-slate-500">
                    Atur tarif listing upload produk, masa aktif, dan batas durasi custom khusus untuk akun Seller.
                </p>
            </div>

            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-blue-50 border border-blue-200 text-blue-800 text-xs font-bold">
                    <i class="ti ti-shield-check text-blue-600"></i>
                    Akses: Admin & Super Admin
                </span>
            </div>
        </div>

        <!-- Mode Alert Banner -->
        {#if !isSellerMode}
            <div class="rounded-2xl bg-slate-100 p-4 border border-slate-200/80 flex items-start gap-3">
                <i class="ti ti-info-circle text-slate-500 text-xl shrink-0 mt-0.5"></i>
                <div class="text-xs text-slate-700 leading-relaxed">
                    <p class="font-bold">Mode Toko Tunggal (IS_SELLER = False)</p>
                    <p class="text-slate-500 mt-0.5">
                        Saat ini sistem berjalan dalam mode toko tunggal. Upload produk berlaku <strong>Unlimited (Gratis tanpa masa kadaluarsa)</strong>. Pengaturan biaya di halaman ini tetap dapat dikonfigurasi dan akan berlaku otomatis ketika <code>IS_SELLER</code> diaktifkan.
                    </p>
                </div>
            </div>
        {:else}
            <div class="rounded-2xl bg-amber-50 p-4 border border-amber-200 flex items-start gap-3">
                <i class="ti ti-alert-triangle text-amber-600 text-xl shrink-0 mt-0.5"></i>
                <div class="text-xs text-amber-900 leading-relaxed">
                    <p class="font-bold">Mode Marketplace Seller (IS_SELLER = True)</p>
                    <p class="text-amber-800 mt-0.5">
                        Upload produk oleh seller dibatasi masa aktif berbayar. Produk yang masa aktifnya habis (kadaluarsa) akan otomatis tersembunyi dari storefront sampai diperpanjang lagi oleh seller.
                    </p>
                </div>
            </div>
        {/if}

        <form onsubmit={handleSubmit} class="space-y-6">

            <!-- Pricing Configuration Card -->
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xs">
                <div class="border-b border-slate-100 px-6 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-bold text-slate-800">Daftar Paket Biaya & Masa Aktif Listing</h2>
                        <p class="text-xs text-slate-500">Tambah, ubah, atau hapus paket durasi masa aktif yang tersedia untuk seller.</p>
                    </div>

                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            onclick={addPackageRow}
                            class="px-3 py-1.5 rounded-xl text-xs font-bold text-white transition flex items-center gap-1.5 shadow-xs cursor-pointer hover:opacity-90 active:scale-95"
                            style="background-color: {primaryColor};"
                        >
                            <i class="ti ti-plus text-sm"></i>
                            <span>Tambah Paket Baru</span>
                        </button>
                        <div class="h-6 w-px bg-slate-200"></div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-semibold text-slate-600">Status Biaya Upload:</span>
                            <Toggle
                                bind:checked={form.product_listing_fee_enabled}
                                label=""
                            />
                        </div>
                    </div>
                </div>

                <div class="p-6 space-y-6">
                    <!-- Dynamic Packages List -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        {#each form.product_listing_packages as pkg, idx}
                            <div class="rounded-2xl bg-slate-50 p-4 border border-slate-200 space-y-3 relative group">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-slate-800 flex items-center gap-1">
                                        <i class="ti ti-package text-blue-600"></i>
                                        <span>Paket #{idx + 1}</span>
                                    </span>
                                    <button
                                        type="button"
                                        onclick={() => removePackageRow(idx)}
                                        class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition cursor-pointer"
                                        title="Hapus Paket"
                                    >
                                        <i class="ti ti-trash text-sm"></i>
                                    </button>
                                </div>

                                <div>
                                    <label class="text-[11px] font-bold text-slate-600 mb-1 block">Nama Paket:</label>
                                    <input
                                        type="text"
                                        bind:value={pkg.name}
                                        placeholder="Contoh: Paket 20 Hari"
                                        class="w-full px-3 py-1.5 rounded-xl border border-slate-300 text-xs font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
                                    />
                                </div>

                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="text-[11px] font-bold text-slate-600 mb-1 block">Durasi (Hari):</label>
                                        <div class="flex items-center gap-1">
                                            <input
                                                type="number"
                                                min="1"
                                                max="365"
                                                bind:value={pkg.days}
                                                class="w-full px-3 py-1.5 rounded-xl border border-slate-300 text-xs font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
                                            />
                                            <span class="text-xs font-bold text-slate-500">Hari</span>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="text-[11px] font-bold text-slate-600 mb-1 block">Nominal Biaya:</label>
                                        <InputCurrency
                                            bind:value={pkg.price}
                                            placeholder="20000"
                                            prefix="Rp"
                                            label=""
                                        />
                                    </div>
                                </div>

                                <p class="text-[10px] text-slate-500 pt-1 border-t border-slate-200/60">
                                    Total biaya listing aktif {pkg.days} hari ({fmt(pkg.price)}).
                                </p>
                            </div>
                        {/each}
                    </div>

                    <div class="border-t border-slate-100 pt-5">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">
                            Pengaturan Durasi Custom (Max Hari)
                        </h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Custom Duration Rate -->
                            <div class="rounded-2xl bg-slate-50 p-4 border border-slate-200/80 space-y-2">
                                <span class="text-xs font-bold text-slate-700 block">Tarif Per Hari Untuk Durasi Custom</span>
                                <InputCurrency
                                    bind:value={form.product_listing_custom_daily_rate}
                                    placeholder="1000"
                                    prefix="Rp"
                                    label=""
                                />
                                <p class="text-[11px] text-slate-500">
                                    Biaya dihitung perkalian hari (misal 5 hari x {fmt(form.product_listing_custom_daily_rate)} = {fmt(5 * form.product_listing_custom_daily_rate)}).
                                </p>
                            </div>

                            <!-- Max Custom Days -->
                            <div class="rounded-2xl bg-slate-50 p-4 border border-slate-200/80 space-y-2">
                                <span class="text-xs font-bold text-slate-700 block">Batas Maksimal Hari Custom</span>
                                <Input
                                    type="number"
                                    min="1"
                                    max="365"
                                    bind:value={form.product_listing_max_custom_days}
                                    placeholder="15"
                                />
                                <p class="text-[11px] text-slate-500">
                                    Seller tidak bisa memilih durasi custom melebihi <strong>{form.product_listing_max_custom_days} hari</strong>.
                                </p>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="border-t border-slate-100 bg-slate-50/50 px-6 py-4 flex items-center justify-between">
                    <span class="text-xs text-slate-500">
                        Perubahan akan langsung berlaku untuk pembuatan & perpanjangan produk seller berikutnya.
                    </span>
                    <button
                        type="submit"
                        disabled={form.processing}
                        class="px-6 py-2.5 rounded-xl text-white font-bold text-xs transition shadow-xs active:scale-95 flex items-center gap-2 disabled:opacity-50 cursor-pointer"
                        style="background-color: {primaryColor};"
                    >
                        {#if form.processing}
                            <i class="ti ti-loader animate-spin text-sm"></i>
                            <span>Menyimpan...</span>
                        {:else}
                            <i class="ti ti-check text-sm"></i>
                            <span>Simpan Pengaturan</span>
                        {/if}
                    </button>
                </div>
            </div>

        </form>
    </main>
</AdminLayout>
