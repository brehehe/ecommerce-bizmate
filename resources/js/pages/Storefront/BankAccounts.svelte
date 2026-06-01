<script lang="ts">
    import StorefrontLayout from '@/components/layouts/StorefrontLayout.svelte';
    import { page, router, Link } from '@inertiajs/svelte';
    import { showToast } from '@/utils/toast';

    let {
        bankAccounts = [] as any[],
        storeName = '',
        storeLogo = '',
    } = $props();

    const primary = $derived((page.props as any).theme?.primary_color ?? '#ee4d2d');

    // Add modal
    let showAddModal = $state(false);
    let showEditModal = $state(false);
    let showDeleteModal = $state(false);
    let saving = $state(false);
    let editingAccount: any = $state(null);
    let deletingAccount: any = $state(null);

    let formData = $state({
        bank_name: '',
        account_number: '',
        account_name: '',
        is_primary: false,
    });

    const popularBanks = [
        'BCA', 'BRI', 'BNI', 'Mandiri', 'CIMB Niaga', 'BTN', 'Danamon',
        'Permata', 'OVO', 'GoPay', 'Dana', 'ShopeePay', 'BRI Syariah',
        'BSI', 'Muamalat', 'BNI Syariah'
    ];

    function openAddModal() {
        formData = { bank_name: '', account_number: '', account_name: '', is_primary: bankAccounts.length === 0 };
        showAddModal = true;
    }

    function openEditModal(account: any) {
        editingAccount = account;
        formData = {
            bank_name: account.bank_name,
            account_number: account.account_number,
            account_name: account.account_name,
            is_primary: account.is_primary,
        };
        showEditModal = true;
    }

    function openDeleteModal(account: any) {
        deletingAccount = account;
        showDeleteModal = true;
    }

    function submitAdd() {
        if (!formData.bank_name.trim() || !formData.account_number.trim() || !formData.account_name.trim()) {
            showToast('Semua kolom wajib diisi.', 'error');
            return;
        }
        saving = true;
        router.post('/profile/bank-accounts', formData, {
            onSuccess: () => {
                showAddModal = false;
                showToast('Rekening berhasil ditambahkan!', 'success');
            },
            onError: (errors: any) => {
                const first = Object.values(errors)[0] as string;
                showToast(first ?? 'Gagal menyimpan rekening.', 'error');
            },
            onFinish: () => { saving = false; },
        });
    }

    function submitEdit() {
        if (!formData.bank_name.trim() || !formData.account_number.trim() || !formData.account_name.trim()) {
            showToast('Semua kolom wajib diisi.', 'error');
            return;
        }
        saving = true;
        router.put(`/profile/bank-accounts/${editingAccount.id}`, formData, {
            onSuccess: () => {
                showEditModal = false;
                showToast('Rekening berhasil diperbarui!', 'success');
            },
            onError: (errors: any) => {
                const first = Object.values(errors)[0] as string;
                showToast(first ?? 'Gagal memperbarui rekening.', 'error');
            },
            onFinish: () => { saving = false; },
        });
    }

    function submitDelete() {
        saving = true;
        router.delete(`/profile/bank-accounts/${deletingAccount.id}`, {
            onSuccess: () => {
                showDeleteModal = false;
                showToast('Rekening berhasil dihapus.', 'success');
            },
            onError: () => {
                showToast('Gagal menghapus rekening.', 'error');
            },
            onFinish: () => { saving = false; },
        });
    }

    function makePrimary(account: any) {
        router.post(`/profile/bank-accounts/${account.id}/make-primary`, {}, {
            onSuccess: () => {
                showToast('Rekening utama berhasil diubah!', 'success');
            },
            onError: () => {
                showToast('Gagal mengubah rekening utama.', 'error');
            },
        });
    }

    function getBankIcon(bankName: string): string {
        const name = bankName?.toLowerCase() ?? '';
        if (name.includes('ovo') || name.includes('gopay') || name.includes('dana') || name.includes('shopee')) {
            return 'ti-device-mobile';
        }
        return 'ti-building-bank';
    }

    function getBankColor(bankName: string): string {
        const name = bankName?.toLowerCase() ?? '';
        if (name.includes('bca')) return '#0066cc';
        if (name.includes('bri')) return '#003d82';
        if (name.includes('bni')) return '#f77f00';
        if (name.includes('mandiri')) return '#ffcc00';
        if (name.includes('cimb')) return '#c8102e';
        if (name.includes('ovo')) return '#4c3494';
        if (name.includes('gopay')) return '#00aed6';
        if (name.includes('dana')) return '#118eea';
        if (name.includes('shopee')) return '#ee4d2d';
        return '#64748b';
    }
</script>

<StorefrontLayout {storeName} {storeLogo} hideMobileFooter={true}>
    <div class="w-full md:max-w-6xl md:mx-auto md:px-6 lg:px-8 md:py-8 font-sans">
        
        <!-- ==================== MOBILE LAYOUT (hidden on desktop) ==================== -->
        <div class="max-w-md mx-auto min-h-[calc(100vh-56px)] md:hidden bg-slate-50 flex flex-col relative pb-20">
            <!-- Header -->
            <div class="bg-white border-b border-slate-200 sticky top-0 z-30">
                <div class="max-w-2xl mx-auto px-4 h-14 flex items-center gap-3">
                    <Link href="/profile" class="p-2 hover:bg-slate-100 rounded-full transition shrink-0">
                        <i class="ti ti-arrow-left text-xl text-slate-700"></i>
                    </Link>
                    <div class="flex-1 min-w-0">
                        <h1 class="text-sm font-bold text-slate-800 leading-tight">Rekening Saya</h1>
                        <p class="text-xs text-slate-500 leading-tight">Digunakan untuk pengembalian dana retur</p>
                    </div>
                    <button
                        onclick={openAddModal}
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold text-white transition active:scale-95"
                        style="background:{primary}"
                    >
                        <i class="ti ti-plus text-sm"></i>
                        Tambah
                    </button>
                </div>
            </div>

            <div class="max-w-2xl mx-auto px-4 py-6 space-y-4">
                <!-- Info Banner -->
                <div class="bg-blue-50 border border-blue-200 rounded-2xl p-4 flex items-start gap-3">
                    <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center shrink-0">
                        <i class="ti ti-info-circle text-blue-500 text-base"></i>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-blue-800 leading-tight">Rekening untuk Pengembalian Dana</p>
                        <p class="text-[11px] text-blue-600 mt-0.5 leading-relaxed">
                            Rekening ini akan digunakan ketika Anda mengajukan retur produk dengan pilihan pengembalian dana.
                            Pastikan data rekening sudah benar.
                        </p>
                    </div>
                </div>

                {#if bankAccounts.length === 0}
                    <!-- Empty State -->
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-12 text-center">
                        <div class="w-20 h-20 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-4">
                            <i class="ti ti-building-bank text-3xl text-slate-300"></i>
                        </div>
                        <p class="font-bold text-slate-700 text-base mb-1">Belum ada rekening</p>
                        <p class="text-sm text-slate-400 mb-6">Tambahkan rekening bank untuk menerima pengembalian dana retur.</p>
                        <button
                            onclick={openAddModal}
                            class="px-6 py-3 rounded-xl font-bold text-white transition active:scale-95"
                            style="background:{primary}"
                        >
                            <i class="ti ti-plus mr-2"></i>Tambah Rekening
                        </button>
                    </div>
                {:else}
                    <!-- Bank Account List -->
                    <div class="space-y-3">
                        {#each bankAccounts as account (account.id)}
                            {@const bankColor = getBankColor(account.bank_name)}
                            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                                <!-- Card gradient top bar -->
                                <div class="h-1 w-full" style="background:{bankColor}"></div>
                                <div class="p-4">
                                    <div class="flex items-start gap-3">
                                        <!-- Bank icon -->
                                        <div
                                            class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
                                            style="background:{bankColor}15"
                                        >
                                            <i class="ti {getBankIcon(account.bank_name)} text-base" style="color:{bankColor}"></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <p class="text-sm font-black text-slate-800">{account.bank_name}</p>
                                                {#if account.is_primary}
                                                    <span class="inline-flex items-center gap-1 text-[9px] font-black px-2 py-0.5 rounded-full text-white" style="background:{primary}">
                                                        <i class="ti ti-star-filled" style="font-size:8px;"></i>
                                                        UTAMA
                                                    </span>
                                                {/if}
                                            </div>
                                            <p class="font-mono font-bold text-base text-slate-900 mt-0.5 tracking-wider">{account.account_number}</p>
                                            <p class="text-xs text-slate-500 mt-0.5">a.n. {account.account_name}</p>
                                        </div>
                                    </div>
                                    <!-- Actions -->
                                    <div class="flex items-center gap-2 mt-3 pt-3 border-t border-slate-100">
                                        {#if !account.is_primary}
                                            <button
                                                onclick={() => makePrimary(account)}
                                                class="flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded-xl border-2 transition hover:opacity-80"
                                                style="border-color:{primary}; color:{primary};"
                                            >
                                                <i class="ti ti-star text-xs"></i>
                                                Jadikan Utama
                                            </button>
                                        {/if}
                                        <button
                                            onclick={() => openEditModal(account)}
                                            class="flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded-xl border-2 border-slate-200 text-slate-600 transition hover:bg-slate-50"
                                        >
                                            <i class="ti ti-edit text-xs"></i>
                                            Edit
                                        </button>
                                        <button
                                            onclick={() => openDeleteModal(account)}
                                            class="flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded-xl border-2 border-red-100 text-red-500 transition hover:bg-red-50 ml-auto"
                                        >
                                            <i class="ti ti-trash text-xs"></i>
                                            Hapus
                                        </button>
                                    </div>
                                </div>
                            </div>
                        {/each}
                    </div>

                    <!-- Add more button -->
                    <button
                        onclick={openAddModal}
                        class="w-full py-3 rounded-2xl border-2 border-dashed border-slate-300 text-slate-500 text-sm font-bold hover:border-slate-400 hover:text-slate-700 transition flex items-center justify-center gap-2"
                    >
                        <i class="ti ti-plus text-base"></i>
                        Tambah Rekening Lain
                    </button>
                {/if}
            </div>
        </div>

        <!-- ==================== DESKTOP LAYOUT (hidden on mobile) ==================== -->
        <div class="hidden md:block max-w-6xl mx-auto w-full">
            <!-- Right Column: Bank Accounts List -->
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden p-6 sm:p-8 space-y-6">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                    <div>
                        <h1 class="font-outfit font-black text-xl text-slate-800">Rekening Saya</h1>
                        <p class="text-xs text-slate-400 font-medium mt-1">
                            Kelola rekening bank Anda untuk menerima dana retur.
                        </p>
                    </div>
                    <button
                        onclick={openAddModal}
                        class="flex items-center gap-1.5 px-4 py-2.5 rounded-2xl text-xs font-bold text-white transition active:scale-95 shadow-md"
                        style="background:{primary}"
                    >
                        <i class="ti ti-plus text-sm"></i>
                        Tambah Rekening
                    </button>
                </div>

                <!-- Info Banner -->
                <div class="bg-blue-50 border border-blue-200 rounded-2xl p-4 flex items-start gap-3">
                    <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center shrink-0">
                        <i class="ti ti-info-circle text-blue-500 text-base"></i>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-blue-800 leading-tight">Rekening untuk Pengembalian Dana</p>
                        <p class="text-[11px] text-blue-600 mt-0.5 leading-relaxed">
                            Rekening ini akan digunakan ketika Anda mengajukan retur produk dengan pilihan pengembalian dana.
                            Pastikan data rekening sudah benar.
                        </p>
                    </div>
                </div>

                {#if bankAccounts.length === 0}
                    <!-- Empty State -->
                    <div class="rounded-2xl border-2 border-dashed border-slate-200 p-12 text-center">
                        <div class="w-20 h-20 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-4">
                            <i class="ti ti-building-bank text-3xl text-slate-300"></i>
                        </div>
                        <p class="font-bold text-slate-700 text-base mb-1">Belum ada rekening</p>
                        <p class="text-sm text-slate-400 mb-6">Tambahkan rekening bank untuk menerima pengembalian dana retur.</p>
                        <button
                            onclick={openAddModal}
                            class="px-6 py-3 rounded-xl font-bold text-white transition active:scale-95 shadow-md"
                            style="background:{primary}"
                        >
                            <i class="ti ti-plus mr-2"></i>Tambah Rekening
                        </button>
                    </div>
                {:else}
                    <!-- Bank Account List -->
                    <div class="grid grid-cols-1 gap-4">
                        {#each bankAccounts as account (account.id)}
                            {@const bankColor = getBankColor(account.bank_name)}
                            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden hover:shadow-md transition">
                                <!-- Card gradient top bar -->
                                <div class="h-1.5 w-full" style="background:{bankColor}"></div>
                                <div class="p-5 flex flex-col justify-between h-full">
                                    <div class="flex items-start gap-4">
                                        <!-- Bank icon -->
                                        <div
                                            class="w-12 h-12 rounded-2xl flex items-center justify-center shrink-0"
                                            style="background:{bankColor}15"
                                        >
                                            <i class="ti {getBankIcon(account.bank_name)} text-lg" style="color:{bankColor}"></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <p class="text-base font-black text-slate-800 leading-none">{account.bank_name}</p>
                                                {#if account.is_primary}
                                                    <span class="inline-flex items-center gap-1 text-[9px] font-black px-2 py-0.5 rounded-full text-white" style="background:{primary}">
                                                        <i class="ti ti-star-filled" style="font-size:8px;"></i>
                                                        UTAMA
                                                    </span>
                                                {/if}
                                            </div>
                                            <p class="font-mono font-black text-lg text-slate-900 mt-1 tracking-wider">{account.account_number}</p>
                                            <p class="text-xs font-semibold text-slate-500 mt-0.5">a.n. {account.account_name}</p>
                                        </div>
                                    </div>
                                    <!-- Actions -->
                                    <div class="flex items-center gap-2 mt-4 pt-4 border-t border-slate-100">
                                        {#if !account.is_primary}
                                            <button
                                                onclick={() => makePrimary(account)}
                                                class="flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded-xl border-2 transition hover:opacity-80 active:scale-95"
                                                style="border-color:{primary}; color:{primary};"
                                            >
                                                <i class="ti ti-star text-xs"></i>
                                                Jadikan Utama
                                            </button>
                                        {/if}
                                        <button
                                            onclick={() => openEditModal(account)}
                                            class="flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded-xl border-2 border-slate-200 text-slate-600 transition hover:bg-slate-50 active:scale-95"
                                        >
                                            <i class="ti ti-edit text-xs"></i>
                                            Edit
                                        </button>
                                        <button
                                            onclick={() => openDeleteModal(account)}
                                            class="flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded-xl border-2 border-red-100 text-red-500 transition hover:bg-red-50 active:scale-95 ml-auto"
                                        >
                                            <i class="ti ti-trash text-xs"></i>
                                            Hapus
                                        </button>
                                    </div>
                                </div>
                            </div>
                        {/each}
                    </div>

                    <!-- Add more button -->
                    <button
                        onclick={openAddModal}
                        class="w-full py-4 rounded-2xl border-2 border-dashed border-slate-350 text-slate-500 text-sm font-bold hover:border-slate-400 hover:text-slate-700 transition flex items-center justify-center gap-2"
                    >
                        <i class="ti ti-plus text-base"></i>
                        Tambah Rekening Lain
                    </button>
                {/if}
            </div>
        </div>
    </div>

    <!-- Add Modal -->
    {#if showAddModal}
        <div class="fixed inset-0 z-50 flex items-end lg:items-center justify-center" onclick={() => (showAddModal = false)}>
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
            <div class="relative z-10 bg-white w-full lg:max-w-md rounded-t-3xl lg:rounded-2xl p-5 max-h-[92dvh] overflow-y-auto" onclick={(e: any) => e.stopPropagation()}>
                <div class="flex items-center justify-between mb-5">
                    <h3 class="font-bold text-slate-800 text-base">Tambah Rekening Bank</h3>
                    <button onclick={() => (showAddModal = false)} class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center hover:bg-slate-200 transition">
                        <i class="ti ti-x text-sm text-slate-600"></i>
                    </button>
                </div>

                <div class="space-y-4">
                    <!-- Bank Name -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2" for="add-bank-name">
                            Nama Bank / E-Wallet <span class="text-red-500">*</span>
                        </label>
                        <select
                            id="add-bank-name"
                            bind:value={formData.bank_name}
                            class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-800 focus:outline-none focus:ring-2 transition appearance-none bg-white"
                            style="--tw-ring-color:{primary}40"
                        >
                            <option value="">-- Pilih Bank --</option>
                            {#each popularBanks as bank}
                                <option value={bank}>{bank}</option>
                            {/each}
                        </select>
                        {#if !popularBanks.includes(formData.bank_name) && formData.bank_name}
                            <!-- kept -->
                        {/if}
                        <input
                            type="text"
                            placeholder="Atau ketik nama bank lainnya..."
                            class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-800 focus:outline-none focus:ring-2 transition mt-2"
                            style="--tw-ring-color:{primary}40"
                            oninput={(e: any) => { formData.bank_name = e.target.value; }}
                            value={formData.bank_name}
                        />
                    </div>

                    <!-- Account Number -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2" for="add-account-number">
                            Nomor Rekening <span class="text-red-500">*</span>
                        </label>
                        <input
                            id="add-account-number"
                            type="text"
                            bind:value={formData.account_number}
                            placeholder="Contoh: 1234567890"
                            class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm font-mono text-slate-800 focus:outline-none focus:ring-2 transition"
                            style="--tw-ring-color:{primary}40"
                        />
                    </div>

                    <!-- Account Name -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2" for="add-account-name">
                            Nama Pemilik Rekening <span class="text-red-500">*</span>
                        </label>
                        <input
                            id="add-account-name"
                            type="text"
                            bind:value={formData.account_name}
                            placeholder="Sesuai buku tabungan/kartu ATM"
                            class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-800 focus:outline-none focus:ring-2 transition"
                            style="--tw-ring-color:{primary}40"
                        />
                    </div>

                    <!-- Primary Checkbox -->
                    <label class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 cursor-pointer hover:bg-slate-50 transition">
                        <input
                            type="checkbox"
                            bind:checked={formData.is_primary}
                            class="w-4 h-4 rounded accent-current"
                            style="accent-color:{primary}"
                        />
                        <div>
                            <p class="text-sm font-bold text-slate-700">Jadikan Rekening Utama</p>
                            <p class="text-[10px] text-slate-400">Rekening ini akan digunakan secara default untuk refund retur.</p>
                        </div>
                    </label>
                </div>

                <div class="flex gap-3 mt-6">
                    <button
                        onclick={() => (showAddModal = false)}
                        class="flex-1 py-3 rounded-xl border-2 border-slate-200 text-slate-700 font-semibold text-sm hover:bg-slate-50 transition"
                    >
                        Batal
                    </button>
                    <button
                        onclick={submitAdd}
                        disabled={saving}
                        class="flex-1 py-3 rounded-xl font-bold text-white text-sm transition disabled:opacity-50 active:scale-95"
                        style="background:{primary}"
                    >
                        {saving ? 'Menyimpan...' : 'Simpan Rekening'}
                    </button>
                </div>
            </div>
        </div>
    {/if}

    <!-- Edit Modal -->
    {#if showEditModal && editingAccount}
        <div class="fixed inset-0 z-50 flex items-end lg:items-center justify-center" onclick={() => (showEditModal = false)}>
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
            <div class="relative z-10 bg-white w-full lg:max-w-md rounded-t-3xl lg:rounded-2xl p-5 max-h-[92dvh] overflow-y-auto" onclick={(e: any) => e.stopPropagation()}>
                <div class="flex items-center justify-between mb-5">
                    <h3 class="font-bold text-slate-800 text-base">Edit Rekening Bank</h3>
                    <button onclick={() => (showEditModal = false)} class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center hover:bg-slate-200 transition">
                        <i class="ti ti-x text-sm text-slate-600"></i>
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2" for="edit-bank-name">
                            Nama Bank / E-Wallet <span class="text-red-500">*</span>
                        </label>
                        <input
                            id="edit-bank-name"
                            type="text"
                            bind:value={formData.bank_name}
                            placeholder="Contoh: BCA, BRI, OVO, GoPay..."
                            class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-800 focus:outline-none focus:ring-2 transition"
                            style="--tw-ring-color:{primary}40"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2" for="edit-account-number">
                            Nomor Rekening <span class="text-red-500">*</span>
                        </label>
                        <input
                            id="edit-account-number"
                            type="text"
                            bind:value={formData.account_number}
                            placeholder="Contoh: 1234567890"
                            class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm font-mono text-slate-800 focus:outline-none focus:ring-2 transition"
                            style="--tw-ring-color:{primary}40"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2" for="edit-account-name">
                            Nama Pemilik Rekening <span class="text-red-500">*</span>
                        </label>
                        <input
                            id="edit-account-name"
                            type="text"
                            bind:value={formData.account_name}
                            placeholder="Sesuai buku tabungan"
                            class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-800 focus:outline-none focus:ring-2 transition"
                            style="--tw-ring-color:{primary}40"
                        />
                    </div>
                    <label class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 cursor-pointer hover:bg-slate-50 transition">
                        <input
                            type="checkbox"
                            bind:checked={formData.is_primary}
                            class="w-4 h-4 rounded"
                            style="accent-color:{primary}"
                        />
                        <div>
                            <p class="text-sm font-bold text-slate-700">Jadikan Rekening Utama</p>
                            <p class="text-[10px] text-slate-400">Rekening ini akan digunakan secara default untuk refund retur.</p>
                        </div>
                    </label>
                </div>

                <div class="flex gap-3 mt-6">
                    <button
                        onclick={() => (showEditModal = false)}
                        class="flex-1 py-3 rounded-xl border-2 border-slate-200 text-slate-700 font-semibold text-sm hover:bg-slate-50 transition"
                    >
                        Batal
                    </button>
                    <button
                        onclick={submitEdit}
                        disabled={saving}
                        class="flex-1 py-3 rounded-xl font-bold text-white text-sm transition disabled:opacity-50 active:scale-95"
                        style="background:{primary}"
                    >
                        {saving ? 'Menyimpan...' : 'Simpan Perubahan'}
                    </button>
                </div>
            </div>
        </div>
    {/if}

    <!-- Delete Confirm Modal -->
    {#if showDeleteModal && deletingAccount}
        <div class="fixed inset-0 z-50 flex items-end lg:items-center justify-center" onclick={() => (showDeleteModal = false)}>
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
            <div class="relative z-10 bg-white w-full lg:max-w-md rounded-t-3xl lg:rounded-2xl p-5" onclick={(e: any) => e.stopPropagation()}>
                <div class="w-14 h-14 rounded-full bg-red-50 flex items-center justify-center mx-auto mb-4">
                    <i class="ti ti-trash text-2xl text-red-500"></i>
                </div>
                <h3 class="font-bold text-slate-800 text-center text-lg mb-1">Hapus Rekening?</h3>
                <p class="text-xs text-slate-500 text-center mb-2 leading-relaxed">
                    Rekening <strong>{deletingAccount.bank_name} – {deletingAccount.account_number}</strong> akan dihapus permanen.
                </p>
                <div class="flex gap-3 mt-5">
                    <button
                        onclick={() => (showDeleteModal = false)}
                        class="flex-1 py-3 rounded-xl border-2 border-slate-200 text-slate-700 font-semibold text-sm hover:bg-slate-50 transition"
                    >
                        Batal
                    </button>
                    <button
                        onclick={submitDelete}
                        disabled={saving}
                        class="flex-1 py-3 rounded-xl font-bold text-white text-sm transition disabled:opacity-50 bg-red-500 hover:bg-red-600 active:scale-95"
                    >
                        {saving ? 'Menghapus...' : 'Ya, Hapus'}
                    </button>
                </div>
            </div>
        </div>
    {/if}
</StorefrontLayout>
