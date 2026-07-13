<script lang="ts">
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { page, router } from '@inertiajs/svelte';
    import { useForm } from '@inertiajs/svelte';
    import Pagination from '@/components/ui/Pagination.svelte';
    import { showToast } from '@/utils/toast';
    import Select from '@/components/ui/Select.svelte';
    import Input from '@/components/ui/Input.svelte';
    import Toggle from '@/components/ui/Toggle.svelte';
    import InputCurrency from '@/components/ui/InputCurrency.svelte';
    import Textarea from '@/components/ui/Textarea.svelte';
    import { fade, slide } from 'svelte/transition';

    let {
        histories = { data: [], links: [], total: 0 },
        filters = {} as any,
        stats = { total_earned: 0, total_redeemed: 0 },
        customers = [] as any[],
        settings = {} as any,
    } = $props();

    const primaryColor = $derived(
        (page.props as any).theme?.primary_color ?? '#0c4cb4',
    );

    // svelte-ignore state_referenced_locally
    let searchQuery = $state(filters.search || '');
    // svelte-ignore state_referenced_locally
    let filterType = $state(filters.type || 'all');
    // svelte-ignore state_referenced_locally
    let perPage = $state(filters.perPage || 10);
    let searchTimeout: any;

    // Settings form — identical to Settings page loyalty section
    // svelte-ignore state_referenced_locally
    const settingsForm = useForm({
        coins_enabled:
            settings.coins_enabled === 'true' ||
            settings.coins_enabled === true ||
            settings.coins_enabled === '1',
        coin_conversion_rate: settings.coin_conversion_rate || 1,
        coin_earning_method: settings.coin_earning_method || 'proportional',
        coin_earning_rate_rupiah: settings.coin_earning_rate_rupiah || 1000,
        coin_earning_rate_coins: settings.coin_earning_rate_coins || 1,
        coin_earning_tiers: Array.isArray(settings.coin_earning_tiers)
            ? settings.coin_earning_tiers
            : settings.coin_earning_tiers
              ? JSON.parse(settings.coin_earning_tiers)
              : [],
        coin_min_purchase_redeem: settings.coin_min_purchase_redeem || 0,
        coin_max_redeem_per_txn: settings.coin_max_redeem_per_txn || 50000,
        coin_max_redeem_percentage: settings.coin_max_redeem_percentage || 100,
        coin_terms_conditions: settings.coin_terms_conditions || '',
    });

    function addEarningTier() {
        settingsForm.coin_earning_tiers = [
            ...settingsForm.coin_earning_tiers,
            { min_purchase: 50000, earn_coins: 50 },
        ];
    }

    function removeEarningTier(index: number) {
        settingsForm.coin_earning_tiers = settingsForm.coin_earning_tiers.filter(
            (_: any, i: number) => i !== index,
        );
    }

    function submitSettings() {
        settingsForm
            .transform((data: any) => ({
                ...data,
                coin_earning_tiers: JSON.stringify(data.coin_earning_tiers || []),
            }))
            .post('/admin/settings', {
                preserveScroll: true,
            });
    }

    // Adjust Poin modal
    let isModalOpen = $state(false);
    let selectedCustomer = $state<any>(null);
    let customerSearch = $state('');

    const form = useForm({
        user_id: '',
        amount: 0,
        type: 'add' as 'add' | 'deduct',
        description: '',
    });

    const filteredCustomers = $derived(
        customers.filter(
            (c: any) =>
                !customerSearch ||
                c.name.toLowerCase().includes(customerSearch.toLowerCase()) ||
                c.email.toLowerCase().includes(customerSearch.toLowerCase()),
        ),
    );

    function updateQuery() {
        router.get(
            '/admin/master-data/loyalty-poin',
            {
                search: searchQuery || undefined,
                type: filterType !== 'all' ? filterType : undefined,
                perPage: perPage,
            },
            { preserveState: true, replace: true },
        );
    }

    function handleSearch() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            updateQuery();
        }, 500);
    }

    function handlePerPageChange() {
        updateQuery();
    }

    function handleTypeChange() {
        updateQuery();
    }

    function openModal() {
        form.reset();
        selectedCustomer = null;
        customerSearch = '';
        isModalOpen = true;
    }

    function closeModal() {
        isModalOpen = false;
        selectedCustomer = null;
        customerSearch = '';
    }

    function selectCustomer(customer: any) {
        selectedCustomer = customer;
        form.user_id = customer.id;
        customerSearch = customer.name;
    }

    function submitForm() {
        if (!form.user_id) {
            showToast('Pilih pelanggan terlebih dahulu.', 'error');
            return;
        }
        form.post('/admin/master-data/loyalty-poin', {
            preserveScroll: true,
            onSuccess: () => {
                closeModal();
                showToast(
                    form.type === 'add'
                        ? 'Poin berhasil ditambahkan!'
                        : 'Poin berhasil dikurangi!',
                    'success',
                );
            },
        });
    }

    function formatNumber(n: number) {
        return new Intl.NumberFormat('id-ID').format(n);
    }

    function formatDateTime(dateStr: string) {
        if (!dateStr) return '-';
        return new Date(dateStr).toLocaleString('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    function getTypeLabel(type: string) {
        const map: Record<string, { label: string; color: string; icon: string }> = {
            earn: { label: 'Earn', color: 'text-emerald-600 bg-emerald-50', icon: 'ti-plus' },
            redeem: { label: 'Redeem', color: 'text-rose-600 bg-rose-50', icon: 'ti-minus' },
            manual: { label: 'Manual', color: 'text-violet-600 bg-violet-50', icon: 'ti-adjustments-horizontal' },
            refund: { label: 'Refund', color: 'text-sky-600 bg-sky-50', icon: 'ti-refresh' },
        };
        return map[type] ?? { label: type, color: 'text-slate-600 bg-slate-100', icon: 'ti-coin' };
    }

    function getInitials(name: string) {
        if (!name) return '?';
        return name.split(' ').map((n) => n[0]).join('').substring(0, 2).toUpperCase();
    }
</script>

<svelte:head>
    <title>Master Data: Loyalty Poin</title>
</svelte:head>

<AdminLayout>
    <div class="flex-grow flex flex-col min-h-screen">
        <main class="flex-grow p-4 sm:p-8 w-full max-w-full mx-auto space-y-6">

            <!-- Page Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h3 class="font-outfit font-black text-2xl text-slate-800">
                        Loyalty Poin
                    </h3>
                    <p class="text-xs text-slate-400 font-bold uppercase tracking-wider mt-1">
                        Riwayat & Manajemen Poin Pelanggan
                    </p>
                </div>
                <button
                    onclick={openModal}
                    class="flex items-center justify-center gap-2 px-5 py-3 bg-brand-blueRoyal hover:bg-blue-800 text-white font-bold rounded-2xl text-xs transition duration-200 shadow-lg shadow-brand-blueRoyal/20 shrink-0 font-outfit uppercase tracking-wider"
                >
                    <i class="ti ti-adjustments-horizontal text-base"></i>
                    <span>Adjust Poin</span>
                </button>
            </div>

            <!-- Loyalty Settings — identical to Settings page -->
            <div class="bg-white border border-slate-100 shadow-sm rounded-3xl p-6 sm:p-8 space-y-6" id="section-loyalty">
                <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                    <div class="p-2.5 bg-amber-50 text-amber-500 rounded-xl">
                        <i class="ti ti-coins text-lg"></i>
                    </div>
                    <div>
                        <h3 class="font-outfit font-black text-slate-800 text-base leading-none">
                            Sistem Loyalty Poin Toko
                        </h3>
                        <p class="text-xs text-slate-400 font-medium mt-1">
                            Atur konversi Poin belanja, batas penukaran,
                            dan metode perolehan Poin kustom.
                        </p>
                    </div>
                </div>

                <div class="space-y-6">
                    <Toggle
                        bind:checked={settingsForm.coins_enabled}
                        label="Aktifkan Sistem Poin Toko"
                        icon="ti-coin"
                    />

                    {#if settingsForm.coins_enabled}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 pt-2" transition:slide>
                            <InputCurrency
                                id="input-coin-rate"
                                bind:value={settingsForm.coin_conversion_rate}
                                label="Nilai Tukar Poin (1 Poin = Berapa Rupiah?)"
                                placeholder="1"
                                required={true}
                            />
                            <InputCurrency
                                id="input-coin-min-redeem"
                                bind:value={settingsForm.coin_min_purchase_redeem}
                                label="Minimal Subtotal Pembelian untuk Menukar Poin"
                                placeholder="0"
                                required={true}
                            />
                            <InputCurrency
                                id="input-coin-max-redeem"
                                bind:value={settingsForm.coin_max_redeem_per_txn}
                                label="Maksimal Poin yang Dapat Ditukar per Transaksi"
                                placeholder="50000"
                                required={true}
                            />
                            <div class="space-y-1.5 font-sans">
                                <label for="input-coin-max-percentage" class="block text-xs font-bold text-slate-600">
                                    Maksimal Persentase Diskon Poin (%)
                                </label>
                                <div class="relative">
                                    <input
                                        id="input-coin-max-percentage"
                                        type="number"
                                        min="1"
                                        max="100"
                                        bind:value={settingsForm.coin_max_redeem_percentage}
                                        class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:outline-none transition font-sans"
                                        placeholder="Contoh: 50"
                                        required
                                    />
                                    <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none text-slate-400 font-bold font-sans">
                                        %
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="h-px bg-slate-100 my-2"></div>

                        <div class="space-y-4" transition:slide>
                            <span class="text-xs font-black text-slate-700 uppercase tracking-tight block">
                                Metode Perolehan Poin (Earning)
                            </span>
                            <div class="grid grid-cols-2 gap-4">
                                <button
                                    type="button"
                                    onclick={() => (settingsForm.coin_earning_method = 'proportional')}
                                    class="flex flex-col items-start p-4 rounded-2xl border text-left transition duration-200 hover:bg-slate-50 cursor-pointer
                                           {settingsForm.coin_earning_method === 'proportional' ? 'border-blue-500 bg-blue-50/20' : 'border-slate-200 bg-white'}"
                                >
                                    <span class="text-xs font-bold text-slate-800">Kelipatan Belanja (Akumulatif)</span>
                                    <span class="text-[10px] text-slate-400 mt-1">Dapatkan Poin berdasarkan kelipatan nilai total belanja.</span>
                                </button>
                                <button
                                    type="button"
                                    onclick={() => (settingsForm.coin_earning_method = 'tiered')}
                                    class="flex flex-col items-start p-4 rounded-2xl border text-left transition duration-200 hover:bg-slate-50 cursor-pointer
                                           {settingsForm.coin_earning_method === 'tiered' ? 'border-blue-500 bg-blue-50/20' : 'border-slate-200 bg-white'}"
                                >
                                    <span class="text-xs font-bold text-slate-800">Tingkat Belanja (Custom Tiers)</span>
                                    <span class="text-[10px] text-slate-400 mt-1">Dapatkan jumlah Poin tetap berdasarkan tingkat nominal belanja tertentu.</span>
                                </button>
                            </div>

                            {#if settingsForm.coin_earning_method === 'proportional'}
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 bg-slate-50 p-5 rounded-2xl border border-slate-100/60 mt-3" transition:slide>
                                    <InputCurrency
                                        id="input-coin-earn-rupiah"
                                        bind:value={settingsForm.coin_earning_rate_rupiah}
                                        label="Setiap Kelipatan Pembelian Sebesar"
                                        placeholder="1000"
                                        required={true}
                                    />
                                    <div class="space-y-1.5 font-sans">
                                        <label for="input-coin-earn-coins" class="block text-xs font-bold text-slate-600">
                                            Mendapatkan Poin Sejumlah
                                        </label>
                                        <input
                                            id="input-coin-earn-coins"
                                            type="number"
                                            min="1"
                                            bind:value={settingsForm.coin_earning_rate_coins}
                                            class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:outline-none transition font-sans"
                                            placeholder="1"
                                            required
                                        />
                                    </div>
                                    <p class="col-span-full text-[10px] text-slate-400 font-bold leading-normal italic font-sans">
                                        * Contoh: Jika diset Rp 10.000 mendapatkan 1 Poin, pembeli dengan transaksi Rp 50.000 akan mendapatkan 5 Poin secara akumulatif.
                                    </p>
                                </div>
                            {:else if settingsForm.coin_earning_method === 'tiered'}
                                <div class="bg-slate-50 p-5 rounded-2xl border border-slate-100/60 space-y-4 mt-3" transition:slide>
                                    <div class="flex justify-between items-center font-sans">
                                        <span class="text-xs font-bold text-slate-700">Aturan Tingkat Earning Poin</span>
                                        <button
                                            type="button"
                                            onclick={addEarningTier}
                                            class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-[10px] rounded-xl flex items-center gap-1 transition shadow-sm"
                                        >
                                            <i class="ti ti-plus"></i> Tambah Tingkatan
                                        </button>
                                    </div>

                                    {#if settingsForm.coin_earning_tiers.length === 0}
                                        <div class="text-center py-6 border border-dashed border-slate-200 rounded-xl bg-white font-sans">
                                            <i class="ti ti-info-circle text-slate-300 text-2xl mb-1.5 block"></i>
                                            <span class="text-[11px] font-bold text-slate-500">Belum Ada Aturan Tingkatan</span>
                                            <p class="text-[9px] text-slate-400 mt-0.5 leading-none">Klik "+ Tambah Tingkatan" untuk membuat aturan baru.</p>
                                        </div>
                                    {:else}
                                        <div class="space-y-3.5 font-sans">
                                            {#each settingsForm.coin_earning_tiers as tier, index}
                                                <div class="flex items-center gap-3 bg-white p-3 rounded-xl border border-slate-200/50 shadow-sm" transition:slide>
                                                    <div class="flex-grow grid grid-cols-2 gap-3.5">
                                                        <div class="space-y-1">
                                                            <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider block">Min. Belanja (Subtotal)</span>
                                                            <div class="relative">
                                                                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs font-bold">Rp</span>
                                                                <input
                                                                    type="number"
                                                                    min="0"
                                                                    bind:value={tier.min_purchase}
                                                                    class="w-full pl-9 pr-3.5 py-1.5 border border-slate-200 rounded-lg text-xs focus:ring-1 focus:outline-none transition font-sans"
                                                                    placeholder="50000"
                                                                    required
                                                                />
                                                            </div>
                                                        </div>
                                                        <div class="space-y-1">
                                                            <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider block">Poin yang Didapat</span>
                                                            <div class="relative font-sans">
                                                                <input
                                                                    type="number"
                                                                    min="1"
                                                                    bind:value={tier.earn_coins}
                                                                    class="w-full px-3.5 py-1.5 border border-slate-200 rounded-lg text-xs focus:ring-1 focus:outline-none transition font-sans"
                                                                    placeholder="50"
                                                                    required
                                                                />
                                                                <span class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-[10px] font-bold font-sans">Poin</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <button
                                                        type="button"
                                                        onclick={() => removeEarningTier(index)}
                                                        class="p-2.5 text-rose-500 hover:bg-rose-50 rounded-xl transition self-end cursor-pointer"
                                                        title="Hapus Aturan"
                                                    >
                                                        <i class="ti ti-trash text-sm"></i>
                                                    </button>
                                                </div>
                                            {/each}
                                            <p class="text-[10px] text-slate-400 font-bold leading-normal italic font-sans">
                                                * Sistem akan memilih tingkatan dengan syarat nominal pembelian tertinggi yang berhasil dipenuhi oleh belanjaan pelanggan.
                                            </p>
                                        </div>
                                    {/if}
                                </div>
                            {/if}
                        </div>

                        <div class="h-px bg-slate-100 my-2"></div>

                        <div class="space-y-1.5" transition:slide>
                            <Textarea
                                id="input-coin-tnc"
                                bind:value={settingsForm.coin_terms_conditions}
                                label="Syarat & Ketentuan Sistem Poin (S&K)"
                                placeholder="Tuliskan aturan, ketentuan kedaluwarsa, dan S&K penggunaan Poin untuk dibaca oleh pelanggan..."
                                rows={3}
                            />
                        </div>
                    {/if}

                    <div class="flex justify-end pt-2">
                        <button
                            type="button"
                            onclick={submitSettings}
                            disabled={settingsForm.processing}
                            class="flex items-center gap-2 px-6 py-3 bg-brand-blueRoyal hover:bg-blue-800 text-white font-bold rounded-2xl text-xs transition duration-200 shadow-lg shadow-brand-blueRoyal/20 uppercase tracking-wider font-outfit disabled:opacity-70"
                        >
                            {#if settingsForm.processing}
                                <i class="ti ti-loader animate-spin"></i>
                            {/if}
                            Simpan Pengaturan
                        </button>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-card p-5 flex items-center gap-4">
                    <div
                        class="w-12 h-12 rounded-xl flex items-center justify-center text-xl shrink-0"
                        style="background: color-mix(in srgb, {primaryColor} 12%, white); color: {primaryColor};"
                    >
                        <i class="ti ti-history"></i>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 font-bold uppercase tracking-wider font-outfit">Total Transaksi</p>
                        <p class="text-2xl font-black text-slate-800 font-outfit">{formatNumber(histories.total)}</p>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-card p-5 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-500 flex items-center justify-center text-xl shrink-0">
                        <i class="ti ti-trending-up"></i>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 font-bold uppercase tracking-wider font-outfit">Total Poin Earned</p>
                        <p class="text-2xl font-black text-emerald-600 font-outfit">+{formatNumber(stats.total_earned)}</p>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-card p-5 flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-rose-50 text-rose-500 flex items-center justify-center text-xl shrink-0">
                        <i class="ti ti-trending-down"></i>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 font-bold uppercase tracking-wider font-outfit">Total Poin Redeemed</p>
                        <p class="text-2xl font-black text-rose-600 font-outfit">-{formatNumber(stats.total_redeemed)}</p>
                    </div>
                </div>
            </div>

            <!-- Main Table Card -->
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-card overflow-hidden">

                <!-- Toolbar -->
                <div class="p-6 border-b border-slate-100 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 bg-slate-50/20">
                    <div class="flex items-center gap-3 shrink-0">
                        <div class="w-32">
                            <Select
                                bind:value={perPage}
                                options={[
                                    { id: 10, name: '10 Data' },
                                    { id: 25, name: '25 Data' },
                                    { id: 50, name: '50 Data' },
                                    { id: 100, name: '100 Data' },
                                ]}
                                onchange={handlePerPageChange}
                            />
                        </div>
                        <div class="w-36">
                            <Select
                                bind:value={filterType}
                                options={[
                                    { id: 'all', name: 'Semua Tipe' },
                                    { id: 'earn', name: 'Earn' },
                                    { id: 'redeem', name: 'Redeem' },
                                    { id: 'manual', name: 'Manual' },
                                    { id: 'refund', name: 'Refund' },
                                ]}
                                onchange={handleTypeChange}
                            />
                        </div>
                    </div>

                    <div class="flex-grow sm:max-w-md w-full sm:ml-auto">
                        <Input
                            type="text"
                            bind:value={searchQuery}
                            oninput={handleSearch}
                            placeholder="Cari nama pelanggan atau deskripsi..."
                            icon="ti-search"
                        />
                    </div>
                </div>

                <!-- Table -->
                {#if histories.data.length === 0}
                    <div class="py-20 text-center">
                        <div class="w-16 h-16 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-4">
                            <i class="ti ti-coin text-2xl text-slate-300"></i>
                        </div>
                        <p class="font-bold text-slate-400 font-outfit">Tidak ada riwayat poin ditemukan.</p>
                        <p class="text-xs text-slate-300 mt-1">Coba ubah kata kunci atau filter.</p>
                    </div>
                {:else}
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left border-b border-slate-100 bg-slate-50/60">
                                    <th class="px-6 py-3.5 font-bold text-xs text-slate-400 uppercase tracking-wider font-outfit">Pelanggan</th>
                                    <th class="px-4 py-3.5 font-bold text-xs text-slate-400 uppercase tracking-wider font-outfit">Tipe</th>
                                    <th class="px-4 py-3.5 font-bold text-xs text-slate-400 uppercase tracking-wider font-outfit text-right">Jumlah Poin</th>
                                    <th class="px-4 py-3.5 font-bold text-xs text-slate-400 uppercase tracking-wider font-outfit">Deskripsi</th>
                                    <th class="px-4 py-3.5 font-bold text-xs text-slate-400 uppercase tracking-wider font-outfit">Transaksi</th>
                                    <th class="px-6 py-3.5 font-bold text-xs text-slate-400 uppercase tracking-wider font-outfit">Tanggal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                {#each histories.data as history (history.id)}
                                    {@const typeInfo = getTypeLabel(history.type)}
                                    <tr class="hover:bg-slate-50/50 transition-colors" transition:fade={{ duration: 100 }}>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                {#if history.user?.avatar}
                                                    <img
                                                        src={history.user.avatar}
                                                        alt={history.user?.name}
                                                        class="w-9 h-9 rounded-full object-cover shrink-0 border border-slate-200"
                                                    />
                                                {:else}
                                                    <div
                                                        class="w-9 h-9 rounded-full flex items-center justify-center text-xs font-bold text-white shrink-0"
                                                        style="background: {primaryColor};"
                                                    >
                                                        {getInitials(history.user?.name ?? '')}
                                                    </div>
                                                {/if}
                                                <div>
                                                    <p class="font-semibold text-slate-800 text-xs">
                                                        {history.user?.name ?? '-'}
                                                    </p>
                                                    <p class="text-xs text-slate-400">{history.user?.email ?? ''}</p>
                                                </div>
                                            </div>
                                        </td>

                                        <td class="px-4 py-4">
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold {typeInfo.color}">
                                                <i class="ti {typeInfo.icon} text-xs"></i>
                                                {typeInfo.label}
                                            </span>
                                        </td>

                                        <td class="px-4 py-4 text-right">
                                            <span class="font-bold font-outfit text-sm {history.amount > 0 ? 'text-emerald-600' : 'text-rose-600'}">
                                                {history.amount > 0 ? '+' : ''}{formatNumber(history.amount)}
                                            </span>
                                        </td>

                                        <td class="px-4 py-4">
                                            <p class="text-xs text-slate-600 max-w-xs truncate" title={history.description}>
                                                {history.description}
                                            </p>
                                        </td>

                                        <td class="px-4 py-4">
                                            {#if history.transaction}
                                                <span class="text-xs font-mono text-slate-500 bg-slate-100 px-2 py-0.5 rounded-md">
                                                    {history.transaction.order_number ?? '-'}
                                                </span>
                                            {:else}
                                                <span class="text-xs text-slate-300">—</span>
                                            {/if}
                                        </td>

                                        <td class="px-6 py-4">
                                            <p class="text-xs text-slate-500">{formatDateTime(history.created_at)}</p>
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>

                    {#if histories.links && histories.links.length > 3}
                        <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-between gap-4">
                            <p class="text-xs text-slate-400 font-medium">
                                Menampilkan {histories.data.length} dari {formatNumber(histories.total)} riwayat
                            </p>
                            <Pagination links={histories.links} />
                        </div>
                    {/if}
                {/if}
            </div>
        </main>
    </div>
</AdminLayout>

<!-- Adjust Poin Modal -->
{#if isModalOpen}
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div
            class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm"
            onclick={closeModal}
            onkeypress={closeModal}
            role="button"
            tabindex="0"
            transition:fade={{ duration: 150 }}
        ></div>

        <div
            class="bg-white rounded-3xl p-6 sm:p-8 max-w-lg w-full relative z-10 shadow-2xl"
            transition:slide={{ duration: 200, axis: 'y' }}
        >
            <!-- Header -->
            <div class="flex items-start justify-between mb-6">
                <div class="flex items-center gap-3">
                    <div
                        class="w-11 h-11 rounded-xl flex items-center justify-center text-xl"
                        style="background: color-mix(in srgb, {primaryColor} 12%, white); color: {primaryColor};"
                    >
                        <i class="ti ti-adjustments-horizontal"></i>
                    </div>
                    <div>
                        <h4 class="font-outfit font-black text-lg text-slate-800">Adjust Poin</h4>
                        <p class="text-xs text-slate-400">Tambah atau kurangi poin pelanggan</p>
                    </div>
                </div>
                <button
                    onclick={closeModal}
                    class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-500 transition"
                >
                    <i class="ti ti-x text-sm"></i>
                </button>
            </div>

            <form
                onsubmit={(e) => { e.preventDefault(); submitForm(); }}
                class="space-y-5"
            >
                <!-- Customer Search -->
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">
                        Pelanggan <span class="text-rose-400">*</span>
                    </label>
                    <div class="relative">
                        <input
                            type="text"
                            bind:value={customerSearch}
                            placeholder="Cari nama atau email pelanggan..."
                            class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:border-transparent transition"
                            oninput={() => { if (selectedCustomer && customerSearch !== selectedCustomer.name) { selectedCustomer = null; form.user_id = ''; } }}
                        />
                        {#if customerSearch && !selectedCustomer && filteredCustomers.length > 0}
                            <div
                                class="absolute top-full left-0 right-0 mt-1 bg-white border border-slate-200 rounded-xl shadow-xl z-20 max-h-48 overflow-y-auto"
                                transition:slide={{ duration: 150 }}
                            >
                                {#each filteredCustomers.slice(0, 8) as customer (customer.id)}
                                    <button
                                        type="button"
                                        onclick={() => selectCustomer(customer)}
                                        class="w-full text-left px-4 py-3 hover:bg-slate-50 transition flex items-center gap-3 border-b border-slate-50 last:border-0"
                                    >
                                        <div
                                            class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold text-white shrink-0"
                                            style="background: {primaryColor};"
                                        >
                                            {getInitials(customer.name)}
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-slate-800 truncate">{customer.name}</p>
                                            <p class="text-xs text-slate-400 truncate">{customer.email}</p>
                                        </div>
                                        <div class="ml-auto shrink-0 text-right">
                                            <p class="text-xs font-bold text-slate-500">{formatNumber(customer.coins_balance ?? 0)} poin</p>
                                        </div>
                                    </button>
                                {/each}
                            </div>
                        {/if}
                    </div>
                    {#if selectedCustomer}
                        <div class="mt-2 flex items-center gap-2 px-3 py-2 bg-emerald-50 border border-emerald-200 rounded-xl">
                            <i class="ti ti-circle-check text-emerald-500 text-sm"></i>
                            <p class="text-xs font-semibold text-emerald-700">
                                {selectedCustomer.name} — Saldo saat ini: <strong>{formatNumber(selectedCustomer.coins_balance ?? 0)} poin</strong>
                            </p>
                        </div>
                    {/if}
                    {#if form.errors.user_id}
                        <p class="mt-1.5 text-xs text-rose-500 font-medium">{form.errors.user_id}</p>
                    {/if}
                </div>

                <!-- Tipe Adjust -->
                <div>
                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">
                        Tipe <span class="text-rose-400">*</span>
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        <button
                            type="button"
                            onclick={() => (form.type = 'add')}
                            class="flex items-center justify-center gap-2 py-3 rounded-xl border-2 font-bold text-sm transition {form.type === 'add' ? 'border-emerald-500 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-white text-slate-500 hover:border-slate-300'}"
                        >
                            <i class="ti ti-plus text-base"></i>
                            Tambah Poin
                        </button>
                        <button
                            type="button"
                            onclick={() => (form.type = 'deduct')}
                            class="flex items-center justify-center gap-2 py-3 rounded-xl border-2 font-bold text-sm transition {form.type === 'deduct' ? 'border-rose-500 bg-rose-50 text-rose-700' : 'border-slate-200 bg-white text-slate-500 hover:border-slate-300'}"
                        >
                            <i class="ti ti-minus text-base"></i>
                            Kurangi Poin
                        </button>
                    </div>
                </div>

                <!-- Jumlah Poin -->
                <div>
                    <label for="adjust-amount" class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">
                        Jumlah Poin <span class="text-rose-400">*</span>
                    </label>
                    <input
                        id="adjust-amount"
                        type="number"
                        min="1"
                        bind:value={form.amount}
                        placeholder="Masukkan jumlah poin..."
                        class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:border-transparent transition"
                    />
                    {#if form.errors.amount}
                        <p class="mt-1.5 text-xs text-rose-500 font-medium">{form.errors.amount}</p>
                    {/if}
                </div>

                <!-- Deskripsi -->
                <div>
                    <label for="adjust-description" class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">
                        Keterangan <span class="text-rose-400">*</span>
                    </label>
                    <input
                        id="adjust-description"
                        type="text"
                        bind:value={form.description}
                        placeholder="Alasan penyesuaian poin..."
                        class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:border-transparent transition"
                    />
                    {#if form.errors.description}
                        <p class="mt-1.5 text-xs text-rose-500 font-medium">{form.errors.description}</p>
                    {/if}
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-3 pt-1">
                    <button
                        type="button"
                        onclick={closeModal}
                        class="flex-1 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-sm transition"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        disabled={form.processing}
                        class="flex-1 py-3 text-white font-bold rounded-xl text-sm transition shadow-lg disabled:opacity-70 flex items-center justify-center gap-2 {form.type === 'deduct' ? 'bg-rose-500 hover:bg-rose-600 shadow-rose-500/20' : 'bg-brand-blueRoyal hover:bg-blue-800 shadow-brand-blueRoyal/20'}"
                    >
                        {#if form.processing}
                            <i class="ti ti-loader animate-spin"></i>
                        {/if}
                        {form.type === 'add' ? 'Tambah Poin' : 'Kurangi Poin'}
                    </button>
                </div>
            </form>
        </div>
    </div>
{/if}
