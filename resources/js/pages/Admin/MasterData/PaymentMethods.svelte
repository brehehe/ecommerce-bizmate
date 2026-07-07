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
    import { fade, slide } from 'svelte/transition';
    import { bulkDelete as paymentMethodBulkDelete } from '@/routes/admin/master-data/payment-methods';

    const primaryColor = $derived(page.props.theme?.primary_color || '#0c4cb4');

    let { paymentMethods = { data: [], links: [], total: 0 }, filters = {}, gateway_settings = {}, env_keys = {} } = $props();

    // Midtrans Core API payment types definition (matches MidtransService::$paymentTypes)
    const midtransCoreApiMethods = [
        { key: 'bca_va', label: 'BCA Virtual Account', icon: '🏦', color: '#005baa' },
        { key: 'bni_va', label: 'BNI Virtual Account', icon: '🏦', color: '#f68b1f' },
        { key: 'bri_va', label: 'BRI Virtual Account', icon: '🏦', color: '#015cab' },
        { key: 'mandiri_bill', label: 'Mandiri Bill Payment', icon: '🏦', color: '#00346e' },
        { key: 'permata_va', label: 'Permata Virtual Account', icon: '🏦', color: '#e31e24' },
        { key: 'cimb_va', label: 'CIMB Niaga Virtual Account', icon: '🏦', color: '#005baa' },
        { key: 'danamon_va', label: 'Danamon Virtual Account', icon: '🏦', color: '#f68b1f' },
        { key: 'qris', label: 'QRIS (Semua E-Wallet)', icon: '📱', color: '#e31e24' },
        { key: 'gopay', label: 'GoPay', icon: '💚', color: '#00aa13' },
        { key: 'shopeepay', label: 'ShopeePay', icon: '🧡', color: '#ee4d2d' },
        { key: 'indomaret', label: 'Indomaret', icon: '🏪', color: '#005baa' },
        { key: 'alfamart', label: 'Alfamart / Alfamidi / Dan+Dan', icon: '🏪', color: '#ee4d2d' },
        { key: 'credit_card', label: 'Kartu Kredit / Debit', icon: '💳', color: '#0c4cb4' },
    ];

    // Setting key map
    const coreApiSettingKeys = {
        bca_va: 'midtrans_bca_va_enabled',
        bni_va: 'midtrans_bni_va_enabled',
        bri_va: 'midtrans_bri_va_enabled',
        mandiri_bill: 'midtrans_mandiri_enabled',
        permata_va: 'midtrans_permata_va_enabled',
        cimb_va: 'midtrans_cimb_va_enabled',
        danamon_va: 'midtrans_danamon_va_enabled',
        qris: 'midtrans_qris_enabled',
        gopay: 'midtrans_gopay_enabled',
        shopeepay: 'midtrans_shopeepay_enabled',
        indomaret: 'midtrans_indomaret_enabled',
        alfamart: 'midtrans_alfamart_enabled',
        credit_card: 'midtrans_credit_card_enabled',
    };

    // Core API enabled state
    let coreApiEnabled = $state(
        Object.fromEntries(
            midtransCoreApiMethods.map((m) => [m.key, gateway_settings[coreApiSettingKeys[m.key]] === '1'])
        )
    );

    // Payment Gateway state
    function getActiveGateway() {
        if (gateway_settings.payment_api_enabled === '1') return 'komerce';
        if (gateway_settings.qrisly_api_enabled === '1') return 'qrisly';
        if (gateway_settings.midtrans_api_enabled === '1') return 'midtrans';
        if (gateway_settings.xendit_api_enabled === '1') return 'xendit';
        return 'none';
    }
    let selectedGateway = $state(getActiveGateway());

    // svelte-ignore state_referenced_locally
    const gatewayForm = useForm({
        payment_api_enabled: gateway_settings.payment_api_enabled === '1',
        payment_api_key: gateway_settings.payment_api_key || '',
        payment_api_admin_fee: gateway_settings.payment_api_admin_fee || 0,
        qrisly_api_enabled: gateway_settings.qrisly_api_enabled === '1',
        qrisly_api_key: gateway_settings.qrisly_api_key || '',
        qrisly_api_admin_fee: gateway_settings.qrisly_api_admin_fee || 0,
        midtrans_api_enabled: gateway_settings.midtrans_api_enabled === '1',
        midtrans_server_key: gateway_settings.midtrans_server_key || '',
        midtrans_client_key: gateway_settings.midtrans_client_key || '',
        midtrans_snap_url: gateway_settings.midtrans_snap_url || 'https://app.sandbox.midtrans.com',
        midtrans_admin_fee: gateway_settings.midtrans_admin_fee || 0,
        xendit_api_enabled: gateway_settings.xendit_api_enabled === '1',
        xendit_secret_key: gateway_settings.xendit_secret_key || '',
        xendit_public_key: gateway_settings.xendit_public_key || '',
        xendit_url: gateway_settings.xendit_url || 'https://api.xendit.co',
        xendit_admin_fee: gateway_settings.xendit_admin_fee || 0,
    });

    const gateways = [
        {
            id: 'komerce',
            name: 'Payment API (Komerce)',
            subtitle: 'Tersedia untuk aktivasi',
            description: 'Payment gateway terintegrasi dari Komerce. Mendukung transfer bank, virtual account, dan e-wallet.',
            icon: 'ti-credit-card',
            color: '#2563eb',
            bgColor: '#dbeafe',
        },
        {
            id: 'qrisly',
            name: 'QRISLY API',
            subtitle: 'Tersedia untuk aktivasi',
            description: 'Payment gateway berbasis QRIS. Memungkinkan pembayaran via QR Code dari semua aplikasi e-wallet.',
            icon: 'ti-qrcode',
            color: '#7c3aed',
            bgColor: '#ede9fe',
        },
        {
            id: 'midtrans',
            name: 'Midtrans Snap',
            subtitle: 'Tersedia untuk aktivasi',
            description: 'Payment gateway populer di Indonesia. Mendukung Kartu Kredit, GoPay, ShopeePay, QRIS, dan VA.',
            icon: 'ti-wallet',
            color: '#059669',
            bgColor: '#d1fae5',
        },
        {
            id: 'xendit',
            name: 'Xendit Invoices',
            subtitle: 'Tersedia untuk aktivasi',
            description: 'Infrastruktur pembayaran digital terkemuka. Menyediakan opsi pembayaran terintegrasi instan.',
            icon: 'ti-coin',
            color: '#d97706',
            bgColor: '#fef3c7',
        },
        {
            id: 'none',
            name: 'Tanpa Payment Gateway',
            subtitle: 'Hanya pembayaran manual',
            description: 'Tidak menggunakan payment gateway API. Hanya metode pembayaran manual (transfer, COD) yang tersedia.',
            icon: 'ti-cash-off',
            color: '#64748b',
            bgColor: '#f1f5f9',
        },
    ];

    function saveGateway() {
        const data = {
            payment_api_enabled: '0',
            qrisly_api_enabled: '0',
            midtrans_api_enabled: gatewayForm.midtrans_api_enabled ? '1' : '0',
            xendit_api_enabled: '0',
            midtrans_server_key: gatewayForm.midtrans_server_key,
            midtrans_client_key: gatewayForm.midtrans_client_key,
            midtrans_snap_url: gatewayForm.midtrans_snap_url,
            midtrans_admin_fee: gatewayForm.midtrans_admin_fee,
            // Core API payment type toggles
            ...Object.fromEntries(
                midtransCoreApiMethods.map((m) => [coreApiSettingKeys[m.key], coreApiEnabled[m.key] ? '1' : '0'])
            ),
        };

        gatewayForm.transform(() => data).post('/admin/master-data/payment-gateway', {
            preserveScroll: true,
            onSuccess: () => showToast('Konfigurasi Midtrans berhasil disimpan.', 'success'),
        });
    }



    // svelte-ignore state_referenced_locally
    let searchQuery = $state(filters.search || '');
    // svelte-ignore state_referenced_locally
    let perPage = $state(filters.perPage || 10);
    let searchTimeout;

    // Checkbox state
    let selectedMethods = $state([]);
    let selectAll = $derived(
        selectedMethods.length === paymentMethods.data.length &&
            paymentMethods.data.length > 0,
    );

    // Modal state
    let isModalOpen = $state(false);
    let isEditing = $state(false);
    let editId = $state(null);
    let deleteModalOpen = $state(false);
    let deleteBulkModalOpen = $state(false);
    let itemToDelete = $state(null);
    let submittingBulkDelete = $state(false);

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
            selectedMethods = selectedMethods.filter(
                (methodId) => methodId !== id,
            );
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
                    showToast(
                        'Metode Pembayaran berhasil diperbarui',
                        'success',
                    );
                    closeModal();
                },
                onError: (err) => {
                    if (err.error) showToast(err.error, 'error');
                },
            });
        } else {
            form.post('/admin/master-data/payment-methods', {
                onSuccess: () => {
                    showToast(
                        'Metode Pembayaran berhasil ditambahkan',
                        'success',
                    );
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

    function executeBulkDelete() {
        if (selectedMethods.length === 0) return;
        submittingBulkDelete = true;
        router.post(
            paymentMethodBulkDelete.url(),
            {
                ids: selectedMethods,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    selectedMethods = [];
                    deleteBulkModalOpen = false;
                },
                onError: (err) => {
                    const first =
                        Object.values(err)[0] ||
                        'Gagal menghapus metode pembayaran terpilih.';
                    showToast(first, 'error');
                },
                onFinish: () => {
                    submittingBulkDelete = false;
                },
            },
        );
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
                showToast(
                    err?.error || 'Gagal menghapus metode pembayaran',
                    'error',
                );
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
            <div
                class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4"
            >
                <div>
                    <h3 class="font-outfit font-black text-2xl text-slate-800">
                        Master Metode Pembayaran
                    </h3>
                    <p
                        class="text-xs text-slate-400 font-bold uppercase tracking-wider mt-1"
                    >
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

            <!-- Payment Gateway Section -->
            <div class="bg-white border border-slate-100 shadow-sm rounded-3xl p-6 space-y-5">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-100 pb-4">
                    <div class="flex items-center gap-3">
                        <div class="p-2.5 rounded-xl bg-emerald-50 text-emerald-600">
                            <i class="ti ti-wallet text-lg"></i>
                        </div>
                        <div>
                            <h3 class="font-outfit font-black text-slate-800 text-base leading-none">Payment Gateway: Midtrans Snap</h3>
                            <p class="text-xs text-slate-400 font-medium mt-1">Gunakan Midtrans Snap untuk menerima pembayaran otomatis secara real-time langsung di website.</p>
                        </div>
                    </div>
                    <!-- Toggle Switch -->
                    <label class="relative inline-flex items-center cursor-pointer select-none shrink-0">
                        <input
                            type="checkbox"
                            bind:checked={gatewayForm.midtrans_api_enabled}
                            class="sr-only peer"
                        />
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                        <span class="ml-3 text-xs font-bold text-slate-700 uppercase tracking-wider">
                            {gatewayForm.midtrans_api_enabled ? 'Aktif' : 'Nonaktif'}
                        </span>
                    </label>
                </div>

                <!-- Config fields for Midtrans -->
                <div class="space-y-4 pt-2 font-sans">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <Input
                            id="input-midtrans-server-key"
                            bind:value={gatewayForm.midtrans_server_key}
                            label="Midtrans Server Key"
                            placeholder="SB-Mid-server-..."
                            required={gatewayForm.midtrans_api_enabled}
                        />
                        <Input
                            id="input-midtrans-client-key"
                            bind:value={gatewayForm.midtrans_client_key}
                            label="Midtrans Client Key"
                            placeholder="SB-Mid-client-..."
                            required={gatewayForm.midtrans_api_enabled}
                        />
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <Input
                            id="input-midtrans-snap-url"
                            bind:value={gatewayForm.midtrans_snap_url}
                            label="Snap Base URL"
                            placeholder="https://app.sandbox.midtrans.com"
                            required={gatewayForm.midtrans_api_enabled}
                        />
                        <InputCurrency
                            id="input-midtrans-admin-fee"
                            bind:value={gatewayForm.midtrans_admin_fee}
                            label="Biaya Admin (Rupiah)"
                            placeholder="Contoh: 4000"
                            required={gatewayForm.midtrans_api_enabled}
                        />
                    </div>
                </div>

                <!-- Core API Payment Methods Checkboxes -->
                {#if gatewayForm.midtrans_api_enabled}
                    <div class="border-t border-slate-100 pt-5 space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-2">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                                    <i class="ti ti-apps text-base"></i>
                                </div>
                                <div>
                                    <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Metode Pembayaran Midtrans (Core API)</h4>
                                    <p class="text-xs text-slate-500 font-medium mt-0.5">Pilih channel pembayaran aktif yang akan ditampilkan di checkout</p>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                            {#each midtransCoreApiMethods as method}
                                <label
                                    class="relative flex items-center gap-3 p-3.5 rounded-2xl border-2 cursor-pointer transition-all duration-200 select-none {coreApiEnabled[method.key] ? 'border-emerald-500 bg-emerald-50/60 shadow-sm' : 'border-slate-100 bg-white hover:border-slate-200 hover:shadow-sm'}"
                                >
                                    <input
                                        type="checkbox"
                                        bind:checked={coreApiEnabled[method.key]}
                                        class="sr-only"
                                    />
                                    <div class="w-5 h-5 rounded-lg border flex items-center justify-center transition-all duration-200 shrink-0 {coreApiEnabled[method.key] ? 'border-emerald-500 bg-emerald-500 text-white' : 'border-slate-300 bg-white'}">
                                        {#if coreApiEnabled[method.key]}
                                            <i class="ti ti-check text-xs font-black"></i>
                                        {/if}
                                    </div>
                                    <div class="flex items-center gap-2 overflow-hidden">
                                        <span class="text-sm shrink-0">{method.icon}</span>
                                        <span class="font-bold text-slate-700 text-xs leading-tight block truncate">{method.label}</span>
                                    </div>
                                </label>
                            {/each}
                        </div>
                        <p class="text-xs text-slate-400">Metode yang dicentang akan muncul sebagai pilihan di halaman checkout pelanggan Anda.</p>
                    </div>
                {/if}

                <div class="flex justify-end pt-1">
                    <button
                        type="button"
                        onclick={saveGateway}
                        disabled={gatewayForm.processing}
                        class="px-5 py-2.5 text-white font-bold text-sm rounded-xl shadow-sm hover:shadow-md transition flex items-center gap-2 disabled:opacity-70 cursor-pointer"
                        style="background-color: {primaryColor};"
                    >
                        {#if gatewayForm.processing}
                            <i class="ti ti-loader animate-spin text-base"></i>
                            <span>Menyimpan...</span>
                        {:else}
                            <i class="ti ti-device-floppy text-base"></i>
                            <span>Simpan Konfigurasi</span>
                        {/if}
                    </button>
                </div>
            </div>


            <!-- Main Section: Table Card -->
            <div
                class="bg-white rounded-3xl border border-slate-200/80 shadow-card overflow-hidden"
            >
                <!-- Header, PerPage & Search -->
                <div
                    class="p-6 border-b border-slate-100 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 bg-slate-50/20"
                >
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

                <!-- Bulk Actions Bar -->
                {#if selectedMethods.length > 0}
                    <div
                        transition:fade={{ duration: 150 }}
                        class="px-6 py-4 bg-brand-blueLight/30 border-b border-slate-150 flex items-center justify-between gap-4 flex-wrap"
                    >
                        <div class="flex items-center gap-3">
                            <span
                                class="text-xs font-bold text-slate-555 bg-white border border-slate-200 px-2.5 py-1 rounded-lg shadow-soft font-outfit uppercase tracking-wider flex items-center gap-1.5"
                            >
                                <i
                                    class="ti ti-checkbox text-brand-blueRoyal text-sm"
                                ></i>
                                {selectedMethods.length} Metode Terpilih
                            </span>
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                onclick={() => {
                                    selectedMethods = [];
                                }}
                                class="px-4 py-2 border border-slate-200 hover:bg-slate-50 text-slate-555 font-bold rounded-xl text-xs transition uppercase tracking-wider font-outfit cursor-pointer"
                            >
                                Batal Pilihan
                            </button>
                            <button
                                onclick={() => (deleteBulkModalOpen = true)}
                                class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white font-bold rounded-xl text-xs transition shadow-lg shadow-red-500/20 uppercase tracking-wider font-outfit flex items-center gap-1.5 cursor-pointer"
                            >
                                <i class="ti ti-trash"></i>
                                Hapus Terpilih
                            </button>
                        </div>
                    </div>
                {/if}

                {#if paymentMethods.data.length === 0}
                    <div
                        class="py-12 text-center text-slate-400 font-bold font-outfit"
                    >
                        <i
                            class="ti ti-credit-card text-4xl block mb-2 text-slate-300"
                        ></i>
                        Belum ada data metode pembayaran.
                    </div>
                {:else}
                    <!-- Table Area -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse responsive-table payment-methods-table">
                            <thead>
                                <tr
                                    class="border-b border-slate-100 bg-slate-50/50 text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit"
                                >
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
                            <tbody
                                class="divide-y divide-slate-100 text-slate-700 text-sm font-medium"
                            >
                                {#each paymentMethods.data as method (method.id)}
                                    {@const isActive = method.is_active ?? true}
                                    {@const isSelected =
                                        selectedMethods.includes(method.id)}

                                    <tr
                                        class="hover:bg-slate-50/50 transition duration-150 border-b border-slate-100 {isSelected
                                            ? 'bg-brand-blueRoyal/5'
                                            : ''}"
                                    >
                                        <td class="py-6 px-6 text-center">
                                            <input
                                                type="checkbox"
                                                checked={isSelected}
                                                onchange={() =>
                                                    toggleSelect(method.id)}
                                                class="rounded border-slate-300 text-brand-blueRoyal focus:ring-brand-blueRoyal/20 w-4 h-4 cursor-pointer"
                                            />
                                        </td>
                                        <td class="py-6 px-6" data-label="Nama Metode">
                                            <h4
                                                class="text-sm font-bold text-slate-800 text-left"
                                            >
                                                {method.name}
                                            </h4>
                                        </td>
                                        <td class="py-6 px-6" data-label="Tipe">
                                            <span
                                                class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider {method.type ===
                                                'manual'
                                                    ? 'bg-indigo-50 text-indigo-600 border border-indigo-200/50'
                                                    : 'bg-emerald-50 text-emerald-600 border border-emerald-200/50'}"
                                            >
                                                {method.type === 'manual'
                                                    ? 'Manual Transfer'
                                                    : 'Gateway'}
                                            </span>
                                        </td>
                                        <td class="py-6 px-6" data-label="Detail Pembayaran">
                                            {#if method.type === 'manual'}
                                                <div
                                                    class="text-[11px] text-slate-500 font-medium text-left"
                                                >
                                                    <strong>Bank:</strong>
                                                    {method.bank_name} <br />
                                                    <strong>No:</strong>
                                                    {method.account_number}
                                                    <br />
                                                    <strong>A.N:</strong>
                                                    {method.account_name}
                                                </div>
                                            {:else}
                                                <div
                                                    class="text-[11px] text-slate-500 font-medium text-left"
                                                >
                                                    <strong>API Key:</strong>
                                                    ••••••••
                                                    {#if method.settings?.url}
                                                        <br /><strong>URL:</strong>
                                                        <span class="break-all"
                                                            >{method.settings
                                                                .url}</span
                                                        >
                                                    {/if}
                                                    {#if method.settings?.webhook_token}
                                                        <br /><strong>Webhook Token:</strong> ••••••••
                                                    {/if}
                                                </div>
                                            {/if}
                                            {#if method.admin_fee > 0}
                                                <div
                                                    class="text-[11px] text-brand-blueRoyal font-bold mt-1 text-left"
                                                >
                                                    + Rp {new Intl.NumberFormat(
                                                        'id-ID',
                                                    ).format(method.admin_fee)} (Admin)
                                                </div>
                                            {/if}
                                        </td>
                                        <td class="py-6 px-6" data-label="Status">
                                            <span
                                                class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider {isActive
                                                    ? 'bg-emerald-50 text-emerald-600 border border-emerald-200/50'
                                                    : 'bg-slate-50 text-slate-500 border border-slate-200/50'}"
                                            >
                                                {isActive
                                                    ? 'Aktif'
                                                    : 'Nonaktif'}
                                            </span>
                                        </td>
                                        <td class="py-6 px-6 text-center" data-label="Aksi">
                                            <div
                                                class="flex items-center justify-center gap-2"
                                            >
                                                <button
                                                    onclick={() =>
                                                        openEditModal(method)}
                                                    class="w-8 h-8 rounded-lg border border-slate-200 hover:bg-brand-blueLight hover:text-brand-blueRoyal text-slate-500 flex items-center justify-center transition"
                                                    title="Ubah Data"
                                                >
                                                    <i
                                                        class="ti ti-pencil text-sm"
                                                    ></i>
                                                </button>
                                                <button
                                                    onclick={() =>
                                                        toggleStatus(method)}
                                                    class="w-8 h-8 rounded-lg border border-slate-200 {isActive
                                                        ? 'hover:bg-amber-50 hover:text-amber-600 text-slate-500'
                                                        : 'hover:bg-emerald-50 hover:text-emerald-600 text-slate-400'} flex items-center justify-center transition"
                                                    title="Ubah Status (Aktif/Nonaktif)"
                                                >
                                                    <i
                                                        class="ti {isActive
                                                            ? 'ti-ban'
                                                            : 'ti-check'} text-sm"
                                                    ></i>
                                                </button>
                                                <button
                                                    onclick={() =>
                                                        confirmDelete(method)}
                                                    class="w-8 h-8 rounded-lg border border-slate-200 hover:bg-rose-50 hover:text-rose-600 text-slate-500 flex items-center justify-center transition"
                                                    title="Hapus Metode"
                                                >
                                                    <i
                                                        class="ti ti-trash text-sm"
                                                    ></i>
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
        <div
            class="bg-white rounded-3xl border border-slate-200 shadow-2xl w-full max-w-lg relative z-10 transform transition-all duration-300 overflow-hidden animate-in fade-in zoom-in duration-200"
        >
            <!-- Modal Header -->
            <div
                class="px-6 py-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50"
            >
                <h3 class="font-outfit font-black text-lg text-slate-800">
                    {isEditing
                        ? 'Ubah Metode Pembayaran'
                        : 'Tambah Metode Pembayaran'}
                </h3>
                <button
                    type="button"
                    aria-label="Close"
                    onclick={closeModal}
                    class="p-1 text-slate-400 hover:text-slate-700 transition"
                >
                    <i class="ti ti-x text-xl"></i>
                </button>
            </div>

            <!-- Modal Body Form -->
            <form
                onsubmit={saveMethod}
                class="p-6 space-y-4 max-h-[70vh] overflow-y-auto"
            >
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
                        { id: 'gateway', name: 'Payment Gateway' },
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
                <div
                    class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3 mt-4"
                >
                    <button
                        type="button"
                        onclick={closeModal}
                        class="px-5 py-3 border border-slate-200 hover:bg-slate-50 text-slate-500 font-bold rounded-2xl text-xs transition duration-200 uppercase tracking-wider font-outfit"
                        >Batal</button
                    >
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

        <div
            class="bg-white rounded-3xl p-6 sm:p-8 max-w-md w-full relative z-10 shadow-2xl animate-in fade-in zoom-in duration-200"
        >
            <div
                class="w-16 h-16 rounded-full bg-red-50 text-red-500 flex items-center justify-center text-3xl mb-5 mx-auto"
            >
                <i class="ti ti-alert-triangle"></i>
            </div>
            <h4
                class="font-outfit font-black text-xl text-center text-slate-800 mb-2"
            >
                Hapus Metode Pembayaran?
            </h4>
            <p class="text-sm text-center text-slate-500 font-medium mb-8">
                Data metode <strong>{itemToDelete?.name}</strong> akan terhapus secara
                permanen dan tidak dapat dikembalikan.
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

<!-- Bulk Delete Confirmation Modal -->
{#if deleteBulkModalOpen}
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div
            class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity"
            onclick={() => (deleteBulkModalOpen = false)}
            onkeypress={() => (deleteBulkModalOpen = false)}
            role="button"
            tabindex="0"
        ></div>

        <div
            class="bg-white rounded-3xl p-6 sm:p-8 max-w-md w-full relative z-10 shadow-2xl animate-in fade-in zoom-in duration-200"
        >
            <div
                class="w-16 h-16 rounded-full bg-red-50 text-red-500 flex items-center justify-center text-3xl mb-5 mx-auto"
            >
                <i class="ti ti-alert-triangle"></i>
            </div>
            <h4
                class="font-outfit font-black text-xl text-center text-slate-800 mb-2"
            >
                Hapus {selectedMethods.length} Metode Pembayaran Terpilih?
            </h4>
            <p class="text-sm text-center text-slate-555 font-medium mb-8">
                Apakah Anda yakin ingin menghapus <strong
                    >{selectedMethods.length} metode pembayaran</strong
                > yang terpilih secara permanen dari sistem? Tindakan ini tidak dapat
                dibatalkan.
            </p>
            <div class="flex items-center gap-3">
                <button
                    onclick={() => (deleteBulkModalOpen = false)}
                    class="flex-1 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-sm transition cursor-pointer"
                >
                    Batal
                </button>
                <button
                    onclick={executeBulkDelete}
                    disabled={submittingBulkDelete}
                    class="flex-1 py-3 bg-red-500 hover:bg-red-600 text-white font-bold rounded-xl text-sm shadow-lg shadow-red-500/30 transition cursor-pointer disabled:opacity-50"
                >
                    {submittingBulkDelete ? 'Memproses...' : 'Ya, Hapus Semua'}
                </button>
            </div>
        </div>
    </div>
{/if}

<style>
    @media (max-width: 640px) {
        .payment-methods-table td:nth-child(2) {
            display: flex !important;
        }
        .payment-methods-table td[data-label="Nama Metode"],
        .payment-methods-table td[data-label="Detail Pembayaran"] {
            flex-direction: column !important;
            align-items: flex-start !important;
            gap: 4px;
        }
        .payment-methods-table td[data-label="Nama Metode"] > *,
        .payment-methods-table td[data-label="Detail Pembayaran"] > * {
            text-align: left !important;
            width: 100% !important;
        }
    }
</style>
