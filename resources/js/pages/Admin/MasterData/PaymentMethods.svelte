<script>
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { page, router } from '@inertiajs/svelte';
    import { useForm } from '@inertiajs/svelte';
    import Pagination from '@/components/ui/Pagination.svelte';
    import { showToast } from '@/utils/toast';
    import SelectSearch from '@/components/ui/SelectSearch.svelte';
    import Select from '@/components/ui/Select.svelte';
    import Input from '@/components/ui/Input.svelte';
    import InputCurrency from '@/components/ui/InputCurrency.svelte';

    let {
        paymentMethods = { data: [], links: [], total: 0 },
        filters = {},
    } = $props();

    let searchQuery = $state(filters.search || '');
    let perPage = $state(filters.perPage || 10);
    let searchTimeout;

    // Checkbox state
    let selectedMethods = $state([]);
    let selectAll = $derived(
        selectedMethods.length === paymentMethods.data.length && paymentMethods.data.length > 0,
    );

    // Modal state
    let isModalOpen = $state(false);
    let isEditing = $state(false);
    let editId = $state(null);
    let deleteModalOpen = $state(false);
    let itemToDelete = $state(null);

    const form = useForm({
        name: '',
        type: 'manual',
        bank_name: '',
        account_number: '',
        account_name: '',
        api_key: '',
        api_secret: '',
        url: '',
        webhook_token: '',
        admin_fee: 0,
    });

    function toggleSelectAll() {
        if (selectAll) {
            selectedMethods = [];
        } else {
            selectedMethods = paymentMethods.data.map((u) => u.id);
        }
    }

    function toggleSelect(id) {
        if (selectedMethods.includes(id)) {
            selectedMethods = selectedMethods.filter((methodId) => methodId !== id);
        } else {
            selectedMethods = [...selectedMethods, id];
        }
    }

    function updateQuery() {
        router.get(
            '/admin/master-data/payment-methods',
            { search: searchQuery, perPage: perPage },
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

    function openAddModal() {
        isEditing = false;
        editId = null;
        form.reset();
        form.clearErrors();
        isModalOpen = true;
    }

    function openEditModal(method) {
        isEditing = true;
        editId = method.id;
        form.reset();
        form.clearErrors();
        form.name = method.name;
        form.type = method.type;
        form.bank_name = method.bank_name || '';
        form.account_number = method.account_number || '';
        form.account_name = method.account_name || '';
        form.api_key = method.api_key || '';
        form.api_secret = method.api_secret || '';
        form.url = method.settings?.url || '';
        form.webhook_token = method.settings?.webhook_token || '';
        form.admin_fee = method.admin_fee || 0;
        isModalOpen = true;
    }

    function closeModal() {
        isModalOpen = false;
        form.reset();
    }

    function saveMethod(e) {
        e.preventDefault();
        if (isEditing) {
            form.put(`/admin/master-data/payment-methods/${editId}`, {
                onSuccess: () => {
                    showToast('Metode Pembayaran berhasil diperbarui', 'success');
                    closeModal();
                },
                onError: (err) => {
                    if (err.error) showToast(err.error, 'error');
                },
            });
        } else {
            form.post('/admin/master-data/payment-methods', {
                onSuccess: () => {
                    showToast('Metode Pembayaran berhasil ditambahkan', 'success');
                    closeModal();
                },
                onError: (err) => {
                    if (err.error) showToast(err.error, 'error');
                },
            });
        }
    }

    function confirmDelete(method) {
        itemToDelete = method;
        deleteModalOpen = true;
    }

    function executeDelete() {
        if (!itemToDelete) return;

        router.delete(`/admin/master-data/payment-methods/${itemToDelete.id}`, {
            onSuccess: () => {
                showToast('Metode Pembayaran berhasil dihapus', 'success');
                deleteModalOpen = false;
                itemToDelete = null;
            },
            onError: (err) => {
                showToast(err?.error || 'Gagal menghapus metode pembayaran', 'error');
                deleteModalOpen = false;
            },
        });
    }

    function toggleStatus(method) {
        router.post(
            `/admin/master-data/payment-methods/${method.id}/toggle-active`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    showToast(
                        `Status ${method.name} berhasil diubah`,
                        'success',
                    );
                },
                onError: (err) => {
                    showToast(err?.error || 'Gagal mengubah status', 'error');
                },
            },
        );
    }
</script>

<svelte:head>
    <title>Master Data: Metode Pembayaran</title>
</svelte:head>

<AdminLayout>
    <div class="flex-grow flex flex-col min-h-screen">
        <main class="flex-grow p-4 sm:p-8 w-full max-w-full mx-auto space-y-6">
            <!-- Page Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h3 class="font-outfit font-black text-2xl text-slate-800">
                        Master Metode Pembayaran
                    </h3>
                    <p class="text-xs text-slate-400 font-bold uppercase tracking-wider mt-1">
                        Atur Pembayaran Manual & Payment Gateway
                    </p>
                </div>
                <button
                    onclick={openAddModal}
                    class="flex items-center justify-center gap-2 px-5 py-3 bg-brand-blueRoyal hover:bg-blue-800 text-white font-bold rounded-2xl text-xs transition duration-200 shadow-lg shadow-brand-blueRoyal/20 shrink-0 font-outfit uppercase tracking-wider"
                >
                    <i class="ti ti-plus text-base"></i>
                    <span>Tambah Metode</span>
                </button>
            </div>

            <!-- Main Section: Table Card -->
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-card overflow-hidden">
                <!-- Header, PerPage & Search -->
                <div class="p-6 border-b border-slate-100 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 bg-slate-50/20">
                    <!-- PerPage Selector -->
                    <div class="shrink-0 w-full sm:w-32">
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

                    <!-- Search Bar -->
                    <div class="flex-grow sm:max-w-md w-full sm:ml-auto">
                        <Input
                            type="text"
                            bind:value={searchQuery}
                            oninput={handleSearch}
                            placeholder="Cari nama metode..."
                            icon="ti-search"
                        />
                    </div>
                </div>

                {#if paymentMethods.data.length === 0}
                    <div class="py-12 text-center text-slate-400 font-bold font-outfit">
                        <i class="ti ti-credit-card text-4xl block mb-2 text-slate-300"></i>
                        Belum ada data metode pembayaran.
                    </div>
                {:else}
                    <!-- Table Area -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-100 bg-slate-50/50 text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit">
                                    <th class="py-6 px-6 w-12 text-center">
                                        <input
                                            type="checkbox"
                                            checked={selectAll}
                                            onchange={toggleSelectAll}
                                            class="rounded border-slate-300 text-brand-blueRoyal focus:ring-brand-blueRoyal/20 w-4 h-4 cursor-pointer"
                                        />
                                    </th>
                                    <th class="py-6 px-6">Nama Metode</th>
                                    <th class="py-6 px-6">Tipe</th>
                                    <th class="py-6 px-6">Detail Pembayaran</th>
                                    <th class="py-6 px-6">Status</th>
                                    <th class="py-6 px-6 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-700 text-sm font-medium">
                                {#each paymentMethods.data as method (method.id)}
                                    {@const isActive = method.is_active ?? true}
                                    {@const isSelected = selectedMethods.includes(method.id)}

                                    <tr class="hover:bg-slate-50/50 transition duration-150 border-b border-slate-100 {isSelected ? 'bg-brand-blueRoyal/5' : ''}">
                                        <td class="py-6 px-6 text-center">
                                            <input
                                                type="checkbox"
                                                checked={isSelected}
                                                onchange={() => toggleSelect(method.id)}
                                                class="rounded border-slate-300 text-brand-blueRoyal focus:ring-brand-blueRoyal/20 w-4 h-4 cursor-pointer"
                                            />
                                        </td>
                                        <td class="py-6 px-6">
                                            <h4 class="text-sm font-bold text-slate-800">
                                                {method.name}
                                            </h4>
                                        </td>
                                        <td class="py-6 px-6">
                                            <span class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider {method.type === 'manual' ? 'bg-indigo-50 text-indigo-600 border border-indigo-200/50' : 'bg-emerald-50 text-emerald-600 border border-emerald-200/50'}">
                                                {method.type === 'manual' ? 'Manual Transfer' : 'Gateway'}
                                            </span>
                                        </td>
                                        <td class="py-6 px-6">
                                            {#if method.type === 'manual'}
                                                <div class="text-[11px] text-slate-500 font-medium">
                                                    <strong>Bank:</strong> {method.bank_name} <br/>
                                                    <strong>No:</strong> {method.account_number} <br/>
                                                    <strong>A.N:</strong> {method.account_name}
                                                </div>
                                            {:else}
                                                <div class="text-[11px] text-slate-500 font-medium">
                                                    <strong>API Key:</strong> ••••••••
                                                    {#if method.settings?.url}
                                                        <br/><strong>URL:</strong> <span class="break-all">{method.settings.url}</span>
                                                    {/if}
                                                    {#if method.settings?.webhook_token}
                                                        <br/><strong>Webhook Token:</strong> ••••••••
                                                    {/if}
                                                </div>
                                            {/if}
                                            {#if method.admin_fee > 0}
                                                <div class="text-[11px] text-brand-blueRoyal font-bold mt-1">
                                                    + Rp {new Intl.NumberFormat('id-ID').format(method.admin_fee)} (Admin)
                                                </div>
                                            {/if}
                                        </td>
                                        <td class="py-6 px-6">
                                            <span class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider {isActive ? 'bg-emerald-50 text-emerald-600 border border-emerald-200/50' : 'bg-slate-50 text-slate-500 border border-slate-200/50'}">
                                                {isActive ? 'Aktif' : 'Nonaktif'}
                                            </span>
                                        </td>
                                        <td class="py-6 px-6 text-center">
                                            <div class="flex items-center justify-center gap-2">
                                                <button
                                                    onclick={() => openEditModal(method)}
                                                    class="w-8 h-8 rounded-lg border border-slate-200 hover:bg-brand-blueLight hover:text-brand-blueRoyal text-slate-500 flex items-center justify-center transition"
                                                    title="Ubah Data"
                                                >
                                                    <i class="ti ti-pencil text-sm"></i>
                                                </button>
                                                <button
                                                    onclick={() => toggleStatus(method)}
                                                    class="w-8 h-8 rounded-lg border border-slate-200 {isActive ? 'hover:bg-amber-50 hover:text-amber-600 text-slate-500' : 'hover:bg-emerald-50 hover:text-emerald-600 text-slate-400'} flex items-center justify-center transition"
                                                    title="Ubah Status (Aktif/Nonaktif)"
                                                >
                                                    <i class="ti {isActive ? 'ti-ban' : 'ti-check'} text-sm"></i>
                                                </button>
                                                <button
                                                    onclick={() => confirmDelete(method)}
                                                    class="w-8 h-8 rounded-lg border border-slate-200 hover:bg-rose-50 hover:text-rose-600 text-slate-500 flex items-center justify-center transition"
                                                    title="Hapus Metode"
                                                >
                                                    <i class="ti ti-trash text-sm"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                {/if}

                <Pagination paginator={paymentMethods} />
            </div>
        </main>
    </div>
</AdminLayout>

<!-- Modal -->
{#if isModalOpen}
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div
            class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity"
            onclick={closeModal}
            onkeypress={closeModal}
            role="button"
            tabindex="0"
        ></div>
        <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl w-full max-w-lg relative z-10 transform transition-all duration-300 overflow-hidden animate-in fade-in zoom-in duration-200">
            <!-- Modal Header -->
            <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                <h3 class="font-outfit font-black text-lg text-slate-800">
                    {isEditing ? 'Ubah Metode Pembayaran' : 'Tambah Metode Pembayaran'}
                </h3>
                <button
                    type="button"
                    onclick={closeModal}
                    class="p-1 text-slate-400 hover:text-slate-700 transition"
                >
                    <i class="ti ti-x text-xl"></i>
                </button>
            </div>

            <!-- Modal Body Form -->
            <form onsubmit={saveMethod} class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
                <Input
                    type="text"
                    bind:value={form.name}
                    label="Nama Metode"
                    required={true}
                    placeholder="Contoh: Transfer BCA, QRIS, Midtrans"
                    error={form.errors.name}
                />

                <SelectSearch
                    bind:value={form.type}
                    label="Tipe Metode"
                    required={true}
                    options={[
                        { id: 'manual', name: 'Manual Transfer' },
                        { id: 'gateway', name: 'Payment Gateway' }
                    ]}
                    error={form.errors.type}
                />

                {#if form.type === 'manual'}
                    <Input
                        type="text"
                        bind:value={form.bank_name}
                        label="Nama Bank"
                        required={true}
                        placeholder="Contoh: Bank BCA"
                        error={form.errors.bank_name}
                    />
                    <Input
                        type="text"
                        bind:value={form.account_number}
                        label="Nomor Rekening"
                        required={true}
                        placeholder="Contoh: 123456789"
                        error={form.errors.account_number}
                    />
                    <Input
                        type="text"
                        bind:value={form.account_name}
                        label="Atas Nama"
                        required={true}
                        placeholder="Contoh: PT Toko Kita"
                        error={form.errors.account_name}
                    />
                {/if}

                {#if form.type === 'gateway'}
                    <Input
                        type="text"
                        bind:value={form.api_key}
                        label="API Key / Server Key"
                        required={true}
                        placeholder="Masukkan API Key Gateway"
                        error={form.errors.api_key}
                    />
                    <Input
                        type="text"
                        bind:value={form.api_secret}
                        label="API Secret / Client Key (Opsional)"
                        placeholder="Masukkan API Secret Gateway jika diperlukan"
                        error={form.errors.api_secret}
                    />
                    <Input
                        type="text"
                        bind:value={form.url}
                        label="Gateway API Base URL"
                        placeholder="Contoh: https://api.xendit.co/"
                        error={form.errors.url}
                    />
                    <Input
                        type="text"
                        bind:value={form.webhook_token}
                        label="Webhook Verification Token"
                        placeholder="Masukkan Token Webhook untuk validasi callback"
                        error={form.errors.webhook_token}
                    />
                {/if}

                <InputCurrency
                    bind:value={form.admin_fee}
                    label="Biaya Admin - Opsional"
                    placeholder="4500"
                    error={form.errors.admin_fee}
                />

                <!-- Buttons -->
                <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3 mt-4">
                    <button
                        type="button"
                        onclick={closeModal}
                        class="px-5 py-3 border border-slate-200 hover:bg-slate-50 text-slate-500 font-bold rounded-2xl text-xs transition duration-200 uppercase tracking-wider font-outfit"
                        >Batal</button>
                    <button
                        type="submit"
                        disabled={form.processing}
                        class="px-5 py-3 bg-brand-blueRoyal hover:bg-blue-800 text-white font-bold rounded-2xl text-xs transition duration-200 shadow-lg shadow-brand-blueRoyal/20 uppercase tracking-wider font-outfit disabled:opacity-70 flex items-center gap-2"
                    >
                        {#if form.processing}
                            <i class="ti ti-loader animate-spin"></i>
                        {/if}
                        Simpan Metode
                    </button>
                </div>
            </form>
        </div>
    </div>
{/if}

<!-- Delete Confirmation Modal -->
{#if deleteModalOpen}
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div
            class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity"
            onclick={() => (deleteModalOpen = false)}
            onkeypress={() => (deleteModalOpen = false)}
            role="button"
            tabindex="0"
        ></div>

        <div class="bg-white rounded-3xl p-6 sm:p-8 max-w-md w-full relative z-10 shadow-2xl animate-in fade-in zoom-in duration-200">
            <div class="w-16 h-16 rounded-full bg-red-50 text-red-500 flex items-center justify-center text-3xl mb-5 mx-auto">
                <i class="ti ti-alert-triangle"></i>
            </div>
            <h4 class="font-outfit font-black text-xl text-center text-slate-800 mb-2">
                Hapus Metode Pembayaran?
            </h4>
            <p class="text-sm text-center text-slate-500 font-medium mb-8">
                Data metode <strong>{itemToDelete?.name}</strong> akan terhapus secara permanen dan tidak dapat dikembalikan.
            </p>
            <div class="flex items-center gap-3">
                <button
                    onclick={() => (deleteModalOpen = false)}
                    class="flex-1 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-sm transition"
                >
                    Batal
                </button>
                <button
                    onclick={executeDelete}
                    class="flex-1 py-3 bg-red-500 hover:bg-red-600 text-white font-bold rounded-xl text-sm shadow-lg shadow-red-500/30 transition"
                >
                    Ya, Hapus
                </button>
            </div>
        </div>
    </div>
{/if}
