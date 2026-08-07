<script lang="ts">
    import AccountLayout from '@/components/layouts/AccountLayout.svelte';
    import { page, router, Link } from '@inertiajs/svelte';
    import { showToast } from '@/utils/toast';

    let {
        bankAccounts = [] as any[],
        storeName = '',
        storeLogo = '',
    } = $props();

    const primary = $derived(
        (page.props as any).theme?.primary_color ?? '#fa7315',
    );

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
        'BCA',
        'BRI',
        'BNI',
        'Mandiri',
        'CIMB Niaga',
        'BTN',
        'Danamon',
        'Permata',
        'OVO',
        'GoPay',
        'Dana',
        'ShopeePay',
        'BRI Syariah',
        'BSI',
        'Muamalat',
        'BNI Syariah',
    ];

    function openAddModal() {
        formData = {
            bank_name: '',
            account_number: '',
            account_name: '',
            is_primary: bankAccounts.length === 0,
        };
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
        if (
            !formData.bank_name.trim() ||
            !formData.account_number.trim() ||
            !formData.account_name.trim()
        ) {
            showToast('Mohon lengkapi semua bidang.', 'error');
            return;
        }

        saving = true;
        router.post('/profile/bank-accounts', formData, {
            onSuccess: () => {
                showAddModal = false;
                saving = false;
                showToast('Rekening bank berhasil ditambahkan!', 'success');
            },
            onError: () => {
                saving = false;
                showToast('Gagal menambahkan rekening bank.', 'error');
            },
        });
    }

    function submitEdit() {
        if (
            !formData.bank_name.trim() ||
            !formData.account_number.trim() ||
            !formData.account_name.trim()
        ) {
            showToast('Mohon lengkapi semua bidang.', 'error');
            return;
        }

        saving = true;
        router.put(`/profile/bank-accounts/${editingAccount.id}`, formData, {
            onSuccess: () => {
                showEditModal = false;
                saving = false;
                showToast('Rekening bank berhasil diperbarui!', 'success');
            },
            onError: () => {
                saving = false;
                showToast('Gagal memperbarui rekening bank.', 'error');
            },
        });
    }

    function submitDelete() {
        saving = true;
        router.delete(`/profile/bank-accounts/${deletingAccount.id}`, {
            onSuccess: () => {
                showDeleteModal = false;
                saving = false;
                showToast('Rekening bank berhasil dihapus!', 'success');
            },
            onError: () => {
                saving = false;
                showToast('Gagal menghapus rekening bank.', 'error');
            },
        });
    }

    function makePrimary(account: any) {
        router.post(
            `/profile/bank-accounts/${account.id}/make-primary`,
            {},
            {
                onSuccess: () => {
                    showToast('Rekening utama berhasil diubah!', 'success');
                },
                onError: () => {
                    showToast('Gagal mengubah rekening utama.', 'error');
                },
            },
        );
    }

    function getBankIcon(bankName: string): string {
        const name = bankName?.toLowerCase() ?? '';
        if (
            name.includes('ovo') ||
            name.includes('gopay') ||
            name.includes('dana') ||
            name.includes('shopee')
        ) {
            return 'ti-device-mobile';
        }
        return 'ti-building-bank';
    }
</script>

<svelte:head>
    <title>Bank & Kartu | Akun Saya</title>
</svelte:head>

<AccountLayout activeMenu="bank-accounts">
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-2xs p-6 md:p-8 space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
            <div>
                <h1 class="text-lg font-bold text-slate-800 font-outfit">
                    Bank & Rekening Saya
                </h1>
                <p class="text-xs text-slate-400 font-medium mt-0.5">
                    Kelola rekening bank Anda untuk menerima dana retur & pengembalian belanja.
                </p>
            </div>

            <button
                onclick={openAddModal}
                class="flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold text-white shadow-xs transition hover:opacity-90 cursor-pointer"
                style="background-color: {primary};"
            >
                <i class="ti ti-plus text-sm"></i>
                Tambah Rekening Baru
            </button>
        </div>

        <!-- Info Banner -->
        <div class="bg-amber-50/60 border border-amber-200/80 rounded-xl p-4 flex items-start gap-3">
            <div class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center shrink-0 text-amber-600">
                <i class="ti ti-info-circle text-lg"></i>
            </div>
            <div>
                <p class="text-xs font-bold text-amber-900 leading-tight">
                    Rekening untuk Pengembalian Dana
                </p>
                <p class="text-[11px] text-amber-800/80 mt-0.5 leading-relaxed">
                    Rekening ini akan digunakan ketika Anda mengajukan retur produk dengan pilihan pengembalian dana. Pastikan data nama pemilik & nomor rekening sudah sesuai.
                </p>
            </div>
        </div>

        <!-- Accounts List -->
        {#if bankAccounts.length === 0}
            <div class="rounded-2xl border-2 border-dashed border-slate-200 p-12 text-center space-y-3">
                <div class="w-16 h-16 rounded-full bg-slate-100 flex items-center justify-center mx-auto text-slate-300">
                    <i class="ti ti-credit-card-off text-3xl"></i>
                </div>
                <p class="text-sm font-bold text-slate-700">Belum Ada Rekening Bank</p>
                <p class="text-xs text-slate-400 max-w-sm mx-auto">
                    Tambahkan rekening bank untuk mempermudah proses pengembalian dana jika terjadi retur produk.
                </p>
                <button
                    onclick={openAddModal}
                    class="px-5 py-2.5 rounded-xl font-bold text-xs text-white shadow-xs transition hover:opacity-90"
                    style="background-color: {primary};"
                >
                    Tambah Rekening Bank
                </button>
            </div>
        {:else}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {#each bankAccounts as account (account.id)}
                    <div class="border border-slate-200 rounded-2xl p-4 space-y-3 bg-white shadow-2xs hover:border-slate-300 transition relative">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <div class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center text-slate-700 font-bold">
                                    <i class="ti {getBankIcon(account.bank_name)} text-xl"></i>
                                </div>
                                <div>
                                    <h3 class="text-xs font-bold text-slate-800 uppercase">
                                        {account.bank_name}
                                    </h3>
                                    <p class="text-[11px] font-mono text-slate-500 font-bold">
                                        {account.account_number}
                                    </p>
                                </div>
                            </div>

                            {#if account.is_primary}
                                <span class="px-2.5 py-0.5 rounded-md text-[9px] font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                    Utama
                                </span>
                            {/if}
                        </div>

                        <div class="pt-2 border-t border-slate-100 flex items-center justify-between">
                            <span class="text-xs text-slate-600 font-semibold truncate max-w-[180px]">
                                a.n {account.account_name}
                            </span>

                            <div class="flex items-center gap-2">
                                {#if !account.is_primary}
                                    <button
                                        onclick={() => makePrimary(account)}
                                        class="text-[10px] font-bold text-blue-600 hover:underline"
                                    >
                                        Set Utama
                                    </button>
                                {/if}
                                <button
                                    onclick={() => openEditModal(account)}
                                    class="p-1 text-slate-400 hover:text-slate-600 transition"
                                    title="Edit"
                                >
                                    <i class="ti ti-pencil text-sm"></i>
                                </button>
                                <button
                                    onclick={() => openDeleteModal(account)}
                                    class="p-1 text-slate-400 hover:text-rose-600 transition"
                                    title="Hapus"
                                >
                                    <i class="ti ti-trash text-sm"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                {/each}
            </div>
        {/if}
    </div>
</AccountLayout>

<!-- Modal Add Account -->
{#if showAddModal}
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <div class="bg-white rounded-2xl p-6 max-w-md w-full shadow-2xl space-y-4">
            <div class="flex justify-between items-center pb-3 border-b border-slate-100">
                <h3 class="font-bold text-base text-slate-800">Tambah Rekening Bank</h3>
                <button onclick={() => (showAddModal = false)} class="text-slate-400 hover:text-slate-600">
                    <i class="ti ti-x text-base"></i>
                </button>
            </div>

            <div class="space-y-3 text-xs">
                <div>
                    <label for="bank_name" class="block font-bold text-slate-700 mb-1">Nama Bank / e-Wallet</label>
                    <input
                        id="bank_name"
                        type="text"
                        bind:value={formData.bank_name}
                        placeholder="Contoh: BCA, Mandiri, OVO"
                        class="w-full px-3.5 py-2 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-slate-300"
                    />
                </div>

                <div>
                    <label for="account_number" class="block font-bold text-slate-700 mb-1">Nomor Rekening</label>
                    <input
                        id="account_number"
                        type="text"
                        bind:value={formData.account_number}
                        placeholder="1234567890"
                        class="w-full px-3.5 py-2 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-slate-300 font-mono"
                    />
                </div>

                <div>
                    <label for="account_name" class="block font-bold text-slate-700 mb-1">Nama Pemilik Rekening</label>
                    <input
                        id="account_name"
                        type="text"
                        bind:value={formData.account_name}
                        placeholder="Sesuai buku tabungan"
                        class="w-full px-3.5 py-2 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-slate-300"
                    />
                </div>

                <label class="flex items-center gap-2 pt-1 cursor-pointer">
                    <input type="checkbox" bind:checked={formData.is_primary} class="rounded text-amber-500" />
                    <span class="font-semibold text-slate-700">Jadikan Rekening Utama</span>
                </label>
            </div>

            <div class="pt-2 flex justify-end gap-2">
                <button onclick={() => (showAddModal = false)} class="px-4 py-2 font-bold text-slate-600 bg-slate-100 rounded-xl text-xs">
                    Batal
                </button>
                <button
                    onclick={submitAdd}
                    disabled={saving}
                    class="px-5 py-2 font-bold text-white rounded-xl text-xs shadow-xs"
                    style="background-color: {primary};"
                >
                    {saving ? 'Memproses...' : 'Simpan Rekening'}
                </button>
            </div>
        </div>
    </div>
{/if}
