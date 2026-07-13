<script lang="ts">
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { page, router, Link } from '@inertiajs/svelte';

    let { levels = [] } = $props();

    const primary   = $derived(page.props.theme?.primary_color   || '#0c4cb4');
    const secondary = $derived(page.props.theme?.secondary_color || '#fa7315');

    // ── Level modal ───────────────────────────────────────────
    let showLevelModal = $state(false);
    let editingLevel   = $state<any>(null);
    let isSaving       = $state(false);

    const emptyLevel = () => ({
        name: '', icon: 'ti-award', badge_color: '#6366f1',
        description: '', order: 0, is_active: true,
        min_total_purchase: 0, min_total_transactions: 0, min_total_products: 0,
        period_type: 'lifetime', auto_upgrade: true, auto_downgrade: false,
        apply_discount_at_checkout: false,
        validity_months: '',
    });

    let levelForm = $state(emptyLevel());

    function openNewLevel() {
        editingLevel = null;
        levelForm = emptyLevel();
        showLevelModal = true;
    }

    function openEditLevel(level: any) {
        editingLevel = level;
        levelForm = { ...level, validity_months: level.validity_months ?? '' };
        showLevelModal = true;
    }

    function saveLevel() {
        isSaving = true;
        const url    = editingLevel ? `/admin/membership/levels/${editingLevel.id}` : '/admin/membership/levels';
        const method = editingLevel ? 'put' : 'post';
        router[method](url, levelForm, {
            onSuccess: () => { showLevelModal = false; isSaving = false; },
            onError:   () => { isSaving = false; },
        });
    }

    function deleteLevel(level: any) {
        if (!confirm(`Hapus level "${level.name}"?`)) return;
        router.delete(`/admin/membership/levels/${level.id}`);
    }

    // ── Benefit modal ─────────────────────────────────────────
    let showBenefitModal = $state(false);
    let activeLevelForBenefit = $state<any>(null);
    let editingBenefit = $state<any>(null);
    let isSavingBenefit = $state(false);

    const emptyBenefit = () => ({
        type: 'discount_percentage', label: '', description: '',
        value: 0, icon: 'ti-star', is_active: true, order: 0,
    });

    let benefitForm = $state(emptyBenefit());

    function openNewBenefit(level: any) {
        activeLevelForBenefit = level;
        editingBenefit = null;
        benefitForm = emptyBenefit();
        showBenefitModal = true;
    }

    function openEditBenefit(level: any, benefit: any) {
        activeLevelForBenefit = level;
        editingBenefit = benefit;
        benefitForm = { ...benefit };
        showBenefitModal = true;
    }

    function saveBenefit() {
        isSavingBenefit = true;
        const url    = editingBenefit
            ? `/admin/membership/benefits/${editingBenefit.id}`
            : `/admin/membership/levels/${activeLevelForBenefit.id}/benefits`;
        const method = editingBenefit ? 'put' : 'post';
        router[method](url, benefitForm, {
            onSuccess: () => { showBenefitModal = false; isSavingBenefit = false; },
            onError:   () => { isSavingBenefit = false; },
        });
    }

    function deleteBenefit(benefit: any) {
        if (!confirm(`Hapus benefit "${benefit.label}"?`)) return;
        router.delete(`/admin/membership/benefits/${benefit.id}`);
    }

    const benefitTypes = [
        {
            value: 'discount_percentage',
            label: 'Diskon Persentase',
            description: 'Memberikan potongan harga dalam persentase (%) dari total belanja pelanggan.',
            valueLabel: 'Nilai Diskon (%)',
            valuePlaceholder: 'Contoh: 5 (artinya 5%)',
            valueSuffix: '%',
            hasValue: true,
            defaultIcon: 'ti-discount',
            defaultLabel: 'Diskon {value}% untuk semua produk',
        },
        {
            value: 'discount_nominal',
            label: 'Diskon Nominal',
            description: 'Memberikan potongan harga dalam nominal rupiah (Rp) dari total belanja pelanggan.',
            valueLabel: 'Nilai Diskon (Rp)',
            valuePlaceholder: 'Contoh: 50000',
            valueSuffix: 'Rp',
            hasValue: true,
            defaultIcon: 'ti-discount',
            defaultLabel: 'Diskon Rp{value} untuk semua produk',
        },
        {
            value: 'free_shipping',
            label: 'Gratis Ongkir',
            description: 'Pelanggan mendapatkan gratis ongkos kirim. Tidak memerlukan nilai angka.',
            valueLabel: null,
            valuePlaceholder: null,
            valueSuffix: null,
            hasValue: false,
            defaultIcon: 'ti-truck',
            defaultLabel: 'Gratis ongkir untuk semua pengiriman',
        },
        // cashback_percentage and cashback_nominal hidden (system not yet ready)
        {
            value: 'point_multiplier',
            label: 'Multiplier Poin',
            description: 'Mengalikan poin yang didapatkan per transaksi. Nilai 2 berarti 2x poin dari normal (1 poin per Rp1.000).',
            valueLabel: 'Nilai Multiplier (x)',
            valuePlaceholder: 'Contoh: 2 (artinya 2x poin)',
            valueSuffix: 'x',
            hasValue: true,
            defaultIcon: 'ti-star',
            defaultLabel: 'Poin {value}x untuk setiap transaksi',
        },
        {
            value: 'auto_voucher',
            label: 'Voucher Otomatis',
            description: 'Sistem otomatis memberikan voucher diskon ketika pelanggan naik ke level ini.',
            valueLabel: 'Nilai Diskon Voucher (%)',
            valuePlaceholder: 'Contoh: 10 (voucher diskon 10%)',
            valueSuffix: '%',
            hasValue: true,
            defaultIcon: 'ti-ticket',
            defaultLabel: 'Voucher diskon {value}% saat naik level',
        },
        {
            value: 'birthday_bonus',
            label: 'Bonus Ulang Tahun',
            description: 'Pelanggan mendapatkan diskon spesial pada hari ulang tahunnya.',
            valueLabel: 'Nilai Diskon Ulang Tahun (%)',
            valuePlaceholder: 'Contoh: 15 (diskon 15% di HUT)',
            valueSuffix: '%',
            hasValue: true,
            defaultIcon: 'ti-cake',
            defaultLabel: 'Bonus diskon {value}% di hari ulang tahun',
        },
        {
            value: 'flash_sale_access',
            label: 'Akses Flash Sale Khusus',
            description: 'Pelanggan dapat mengakses Flash Sale lebih awal sebelum dibuka untuk umum (dalam menit).',
            valueLabel: 'Menit Lebih Awal',
            valuePlaceholder: 'Contoh: 60 (akses 60 menit lebih awal)',
            valueSuffix: 'menit',
            hasValue: true,
            defaultIcon: 'ti-bolt',
            defaultLabel: 'Akses Flash Sale {value} menit lebih awal',
        },
        {
            value: 'priority_cs',
            label: 'Prioritas Customer Service',
            description: 'Pelanggan mendapatkan layanan customer service dengan prioritas lebih tinggi. Tidak memerlukan nilai angka.',
            valueLabel: null,
            valuePlaceholder: null,
            valueSuffix: null,
            hasValue: false,
            defaultIcon: 'ti-headset',
            defaultLabel: 'Prioritas Customer Service',
        },
        {
            value: 'exclusive_product',
            label: 'Produk Eksklusif',
            description: 'Pelanggan mendapatkan akses ke produk eksklusif yang tidak tersedia untuk level lain. Tidak memerlukan nilai angka.',
            valueLabel: null,
            valuePlaceholder: null,
            valueSuffix: null,
            hasValue: false,
            defaultIcon: 'ti-lock-open',
            defaultLabel: 'Akses produk eksklusif member',
        },
        {
            value: 'early_access',
            label: 'Early Access Produk Baru',
            description: 'Pelanggan dapat melihat dan membeli produk baru sebelum dirilis ke publik. Tidak memerlukan nilai angka.',
            valueLabel: null,
            valuePlaceholder: null,
            valueSuffix: null,
            hasValue: false,
            defaultIcon: 'ti-eye',
            defaultLabel: 'Early access produk baru',
        },
    ];

    // Derived: info for currently selected benefit type
    const selectedBenefitType = $derived(
        benefitTypes.find(bt => bt.value === benefitForm.type) ?? benefitTypes[0]
    );

    // When type changes: auto-fill icon and suggest label
    function onBenefitTypeChange() {
        const bt = benefitTypes.find(b => b.value === benefitForm.type);
        if (!bt) return;
        benefitForm.icon = bt.defaultIcon;
        // Only auto-fill label if it's empty or matches a previous default
        const isDefaultLabel = benefitTypes.some(b => b.defaultLabel === benefitForm.label);
        if (!benefitForm.label || isDefaultLabel) {
            benefitForm.label = bt.defaultLabel.replace('{value}', benefitForm.value > 0 ? String(benefitForm.value) : '...');
        }
        if (!bt.hasValue) {
            benefitForm.value = 0;
        }
    }

    const periodTypes = [
        { value: 'lifetime',   label: 'Seumur Hidup' },
        { value: '12_months',  label: '12 Bulan Terakhir' },
        { value: '6_months',   label: '6 Bulan Terakhir' },
        { value: '3_months',   label: '3 Bulan Terakhir' },
    ];

    function fmtCurrency(n: number): string {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n ?? 0);
    }
</script>

<svelte:head><title>Level Membership — Admin</title></svelte:head>

<AdminLayout>
    <main class="w-full max-w-[1600px] mx-auto px-4 sm:px-6 py-6 space-y-5">

        <!-- Header -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-semibold tracking-tight text-slate-900">Level Membership</h1>
                <p class="mt-0.5 text-sm text-slate-500">Kelola level dan benefit program membership</p>
            </div>
            <div class="flex items-center gap-2">
                <Link href="/admin/membership/dashboard" class="inline-flex items-center gap-1.5 h-9 rounded-lg border border-slate-200 px-3 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                    <i class="ti ti-arrow-left text-sm text-slate-400"></i>
                    Dashboard
                </Link>
                <button type="button" onclick={openNewLevel}
                    class="inline-flex items-center gap-1.5 h-9 rounded-lg px-3 text-sm font-semibold text-white transition-opacity hover:opacity-90"
                    style="background-color: {primary};">
                    <i class="ti ti-plus text-sm"></i>
                    Tambah Level
                </button>
            </div>
        </div>

        <!-- Level cards -->
        {#if levels.length === 0}
            <div class="flex flex-col items-center justify-center py-20 text-center bg-white rounded-xl border border-slate-200">
                <div class="h-16 w-16 rounded-2xl bg-slate-100 flex items-center justify-center mb-4">
                    <i class="ti ti-award text-2xl text-slate-400"></i>
                </div>
                <p class="text-base font-semibold text-slate-700">Belum ada level membership</p>
                <p class="text-sm text-slate-400 mt-1">Buat level pertama untuk memulai program membership</p>
                <button onclick={openNewLevel}
                    class="mt-4 h-9 px-5 rounded-lg text-sm font-semibold text-white transition-opacity hover:opacity-90"
                    style="background-color: {primary};">
                    Buat Level Pertama
                </button>
            </div>
        {:else}
            <div class="grid grid-cols-1 gap-5 lg:grid-cols-2 xl:grid-cols-3">
                {#each levels as level}
                    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                        <!-- Level header -->
                        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 items-center justify-center rounded-xl text-white text-lg" style="background-color: {level.badge_color};">
                                    <i class="ti {level.icon}"></i>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <p class="text-sm font-semibold text-slate-800">{level.name}</p>
                                        {#if !level.is_active}
                                            <span class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-500">Nonaktif</span>
                                        {/if}
                                    </div>
                                    <p class="text-xs text-slate-400">{level.customer_memberships_count ?? 0} member</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-1">
                                <button type="button" onclick={() => openEditLevel(level)}
                                    class="flex h-7 w-7 items-center justify-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition-colors">
                                    <i class="ti ti-edit text-sm"></i>
                                </button>
                                <button type="button" onclick={() => deleteLevel(level)}
                                    class="flex h-7 w-7 items-center justify-center rounded-md text-slate-400 hover:bg-rose-50 hover:text-rose-500 transition-colors">
                                    <i class="ti ti-trash text-sm"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Requirements -->
                        <div class="px-5 py-3 border-b border-slate-100 bg-slate-50/50">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-2">Syarat Naik Level</p>
                            <div class="grid grid-cols-2 gap-x-4 gap-y-1">
                                <div class="text-xs text-slate-600">
                                    <span class="text-slate-400">Min. Belanja:</span>
                                    <span class="font-medium ml-1">{fmtCurrency(level.min_total_purchase)}</span>
                                </div>
                                <div class="text-xs text-slate-600">
                                    <span class="text-slate-400">Min. Transaksi:</span>
                                    <span class="font-medium ml-1">{level.min_total_transactions}x</span>
                                </div>
                                <div class="text-xs text-slate-600">
                                    <span class="text-slate-400">Periode:</span>
                                    <span class="font-medium ml-1">{periodTypes.find(p => p.value === level.period_type)?.label ?? level.period_type}</span>
                                </div>
                                <div class="text-xs text-slate-600">
                                    <span class="text-slate-400">Masa berlaku:</span>
                                    <span class="font-medium ml-1">{level.validity_months ? level.validity_months + ' bulan' : 'Selamanya'}</span>
                                </div>
                            </div>
                            {#if level.apply_discount_at_checkout}
                                <div class="mt-2 flex items-center gap-1.5 text-xs font-medium text-emerald-600">
                                    <i class="ti ti-discount text-xs"></i>
                                    Diskon otomatis aktif di checkout
                                </div>
                            {/if}
                        </div>

                        <!-- Benefits -->
                        <div class="px-5 py-3">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Benefit</p>
                                <button type="button" onclick={() => openNewBenefit(level)}
                                    class="flex items-center gap-1 text-xs font-medium transition-opacity hover:opacity-70"
                                    style="color: {primary};">
                                    <i class="ti ti-plus text-xs"></i> Tambah
                                </button>
                            </div>
                            {#if level.active_benefits?.length > 0}
                                <div class="space-y-1.5">
                                    {#each level.active_benefits as benefit}
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="flex items-center gap-2 min-w-0">
                                                <i class="ti {benefit.icon ?? 'ti-star'} text-sm shrink-0" style="color: {level.badge_color};"></i>
                                                <span class="text-xs text-slate-700 truncate">{benefit.label}</span>
                                            </div>
                                            <div class="flex items-center gap-1 shrink-0">
                                                <button type="button" onclick={() => openEditBenefit(level, benefit)}
                                                    class="flex h-5 w-5 items-center justify-center rounded text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition-colors">
                                                    <i class="ti ti-edit text-[10px]"></i>
                                                </button>
                                                <button type="button" onclick={() => deleteBenefit(benefit)}
                                                    class="flex h-5 w-5 items-center justify-center rounded text-slate-400 hover:bg-rose-50 hover:text-rose-500 transition-colors">
                                                    <i class="ti ti-trash text-[10px]"></i>
                                                </button>
                                            </div>
                                        </div>
                                    {/each}
                                </div>
                            {:else}
                                <p class="text-xs text-slate-400 italic">Belum ada benefit</p>
                            {/if}
                        </div>
                    </div>
                {/each}
            </div>
        {/if}

    </main>

    <!-- Level Modal -->
    {#if showLevelModal}
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
            role="dialog" aria-modal="true"
            onclick={(e) => { if (e.target === e.currentTarget) showLevelModal = false; }}>
            <div class="w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl max-h-[90dvh] overflow-y-auto">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 sticky top-0 bg-white z-10">
                    <h3 class="text-sm font-semibold text-slate-800">{editingLevel ? 'Edit Level' : 'Tambah Level Baru'}</h3>
                    <button type="button" onclick={() => (showLevelModal = false)}
                        class="flex h-7 w-7 items-center justify-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition-colors" aria-label="Tutup">
                        <i class="ti ti-x text-sm"></i>
                    </button>
                </div>
                <div class="p-5 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Nama Level *</label>
                            <input type="text" bind:value={levelForm.name} placeholder="Contoh: Gold"
                                class="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm focus:border-slate-400 focus:outline-none transition-colors" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Warna Badge</label>
                            <div class="flex items-center gap-2">
                                <input type="color" bind:value={levelForm.badge_color} class="h-9 w-14 rounded-lg border border-slate-200 cursor-pointer p-0.5" />
                                <input type="text" bind:value={levelForm.badge_color} class="h-9 flex-1 rounded-lg border border-slate-200 bg-white px-3 text-sm font-mono focus:border-slate-400 focus:outline-none" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Icon (Tabler)</label>
                            <input type="text" bind:value={levelForm.icon} placeholder="ti-award"
                                class="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm font-mono focus:border-slate-400 focus:outline-none transition-colors" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Urutan</label>
                            <input type="number" bind:value={levelForm.order} min="0"
                                class="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm focus:border-slate-400 focus:outline-none transition-colors" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Deskripsi</label>
                        <textarea bind:value={levelForm.description} rows={2} placeholder="Deskripsi singkat level ini..."
                            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:border-slate-400 focus:outline-none resize-none transition-colors"></textarea>
                    </div>

                    <div class="border-t border-slate-100 pt-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-3">Syarat Naik Level</p>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs text-slate-500 mb-1">Min. Total Belanja (Rp)</label>
                                <input type="number" bind:value={levelForm.min_total_purchase} min="0"
                                    class="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm focus:border-slate-400 focus:outline-none transition-colors" />
                            </div>
                            <div>
                                <label class="block text-xs text-slate-500 mb-1">Min. Transaksi</label>
                                <input type="number" bind:value={levelForm.min_total_transactions} min="0"
                                    class="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm focus:border-slate-400 focus:outline-none transition-colors" />
                            </div>
                            <div>
                                <label class="block text-xs text-slate-500 mb-1">Min. Produk Dibeli</label>
                                <input type="number" bind:value={levelForm.min_total_products} min="0"
                                    class="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm focus:border-slate-400 focus:outline-none transition-colors" />
                            </div>
                            <div>
                                <label class="block text-xs text-slate-500 mb-1">Masa Berlaku (bulan)</label>
                                <input type="number" bind:value={levelForm.validity_months} min="1" placeholder="Kosong = selamanya"
                                    class="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm focus:border-slate-400 focus:outline-none transition-colors" />
                            </div>
                            <div class="col-span-2">
                                <label class="block text-xs text-slate-500 mb-1">Periode Perhitungan</label>
                                <select bind:value={levelForm.period_type}
                                    class="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm focus:border-slate-400 focus:outline-none appearance-none cursor-pointer">
                                    {#each periodTypes as pt}
                                        <option value={pt.value}>{pt.label}</option>
                                    {/each}
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-slate-100 pt-4 flex flex-wrap gap-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" bind:checked={levelForm.is_active} class="rounded border-slate-300 accent-slate-900" />
                            <span class="text-sm text-slate-700">Level Aktif</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" bind:checked={levelForm.auto_upgrade} class="rounded border-slate-300 accent-slate-900" />
                            <span class="text-sm text-slate-700">Naik Level Otomatis</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" bind:checked={levelForm.auto_downgrade} class="rounded border-slate-300 accent-slate-900" />
                            <span class="text-sm text-slate-700">Turun Level Otomatis</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" bind:checked={levelForm.apply_discount_at_checkout} class="rounded border-slate-300 accent-slate-900" />
                            <div>
                                <span class="text-sm text-slate-700">Terapkan Diskon Saat Checkout</span>
                                <p class="text-xs text-slate-400 mt-0.5">Diskon membership otomatis dipotong dari total belanja</p>
                            </div>
                        </label>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2 border-t border-slate-100 px-5 py-3">
                    <button type="button" onclick={() => (showLevelModal = false)}
                        class="rounded-lg border border-slate-200 px-4 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-50 transition-colors">Batal</button>
                    <button type="button" onclick={saveLevel} disabled={isSaving || !levelForm.name}
                        class="rounded-lg px-4 py-1.5 text-sm font-semibold text-white transition-opacity hover:opacity-90 disabled:opacity-50"
                        style="background-color: {primary};">
                        {isSaving ? 'Menyimpan...' : 'Simpan'}
                    </button>
                </div>
            </div>
        </div>
    {/if}

    <!-- Benefit Modal -->
    {#if showBenefitModal}
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
            role="dialog" aria-modal="true"
            onclick={(e) => { if (e.target === e.currentTarget) showBenefitModal = false; }}>
            <div class="w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl max-h-[90dvh] overflow-y-auto">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4 sticky top-0 bg-white z-10">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg text-white text-sm" style="background-color: {activeLevelForBenefit?.badge_color ?? primary};">
                            <i class="ti {benefitForm.icon ?? 'ti-star'}"></i>
                        </div>
                        <h3 class="text-sm font-semibold text-slate-800">{editingBenefit ? 'Edit Benefit' : 'Tambah Benefit'}</h3>
                    </div>
                    <button type="button" onclick={() => (showBenefitModal = false)}
                        class="flex h-7 w-7 items-center justify-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition-colors" aria-label="Tutup">
                        <i class="ti ti-x text-sm"></i>
                    </button>
                </div>
                <div class="p-5 space-y-4">

                    <!-- Type selector -->
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Tipe Benefit</label>
                        <div class="grid grid-cols-1 gap-2 max-h-64 overflow-y-auto pr-1 scrollbar-thin">
                            {#each benefitTypes as bt}
                                <button
                                    type="button"
                                    onclick={() => { benefitForm.type = bt.value; onBenefitTypeChange(); }}
                                    class="flex items-start gap-3 rounded-xl border-2 px-3 py-2.5 text-left transition-all
                                        {benefitForm.type === bt.value
                                            ? 'border-slate-900 bg-slate-50'
                                            : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50/50'}"
                                >
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg mt-0.5"
                                        style="{benefitForm.type === bt.value ? `background-color: ${activeLevelForBenefit?.badge_color ?? primary}; color: white;` : 'background-color: #f1f5f9; color: #64748b;'}">
                                        <i class="ti {bt.defaultIcon} text-sm"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold {benefitForm.type === bt.value ? 'text-slate-900' : 'text-slate-700'}">{bt.label}</p>
                                        <p class="text-xs text-slate-400 mt-0.5 leading-snug">{bt.description}</p>
                                    </div>
                                    {#if benefitForm.type === bt.value}
                                        <i class="ti ti-check text-sm shrink-0 mt-1" style="color: {primary};"></i>
                                    {/if}
                                </button>
                            {/each}
                        </div>
                    </div>

                    <!-- Info box for selected type -->
                    {#if selectedBenefitType}
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <div class="flex items-center gap-2 mb-1">
                                <i class="ti {selectedBenefitType.defaultIcon} text-sm" style="color: {primary};"></i>
                                <p class="text-xs font-semibold text-slate-700">{selectedBenefitType.label}</p>
                            </div>
                            <p class="text-xs text-slate-500 leading-relaxed">{selectedBenefitType.description}</p>
                        </div>
                    {/if}

                    <!-- Value input (only for types that need it) -->
                    {#if selectedBenefitType?.hasValue}
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">
                                {selectedBenefitType.valueLabel}
                            </label>
                            <div class="relative">
                                <input
                                    type="number"
                                    bind:value={benefitForm.value}
                                    min="0"
                                    step="0.01"
                                    placeholder={selectedBenefitType.valuePlaceholder ?? '0'}
                                    oninput={() => {
                                        // Auto-update label when value changes
                                        const bt = selectedBenefitType;
                                        if (bt) {
                                            const isAutoLabel = benefitTypes.some(b => b.defaultLabel === benefitForm.label || benefitForm.label === b.defaultLabel.replace('{value}', '...'));
                                            if (!benefitForm.label || isAutoLabel) {
                                                benefitForm.label = bt.defaultLabel.replace('{value}', String(benefitForm.value));
                                            }
                                        }
                                    }}
                                    class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 pr-12 text-sm font-semibold text-slate-800 focus:border-slate-400 focus:outline-none transition-colors"
                                />
                                {#if selectedBenefitType.valueSuffix}
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">
                                        {selectedBenefitType.valueSuffix}
                                    </span>
                                {/if}
                            </div>
                        </div>
                    {/if}

                    <!-- Label -->
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Label Tampilan</label>
                        <input type="text" bind:value={benefitForm.label}
                            placeholder="Label yang ditampilkan ke pelanggan"
                            class="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm focus:border-slate-400 focus:outline-none transition-colors" />
                        <p class="text-[10px] text-slate-400 mt-1">Teks yang akan ditampilkan ke pelanggan di halaman profil membership</p>
                    </div>

                    <!-- Icon & active -->
                    <div class="flex items-center gap-3">
                        <div class="flex-1">
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Icon (Tabler)</label>
                            <div class="relative flex items-center">
                                <span class="absolute left-3 text-slate-400 pointer-events-none">
                                    <i class="ti {benefitForm.icon} text-base"></i>
                                </span>
                                <input type="text" bind:value={benefitForm.icon} placeholder="ti-star"
                                    class="h-9 w-full rounded-lg border border-slate-200 bg-white pl-9 pr-3 text-sm font-mono focus:border-slate-400 focus:outline-none transition-colors" />
                            </div>
                        </div>
                        <div class="pt-5">
                            <label class="flex items-center gap-2 cursor-pointer h-9">
                                <input type="checkbox" bind:checked={benefitForm.is_active} class="rounded border-slate-300 accent-slate-900" />
                                <span class="text-sm text-slate-700 whitespace-nowrap">Aktif</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2 border-t border-slate-100 px-5 py-3 sticky bottom-0 bg-white">
                    <button type="button" onclick={() => (showBenefitModal = false)}
                        class="rounded-lg border border-slate-200 px-4 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-50 transition-colors">Batal</button>
                    <button type="button" onclick={saveBenefit} disabled={isSavingBenefit || !benefitForm.label}
                        class="rounded-lg px-4 py-1.5 text-sm font-semibold text-white transition-opacity hover:opacity-90 disabled:opacity-50"
                        style="background-color: {primary};">
                        {isSavingBenefit ? 'Menyimpan...' : 'Simpan Benefit'}
                    </button>
                </div>
            </div>
        </div>
    {/if}

</AdminLayout>
