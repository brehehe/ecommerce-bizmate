<script>
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { page, router } from '@inertiajs/svelte';
    import { useForm } from '@inertiajs/svelte';
    import Pagination from '@/components/ui/Pagination.svelte';
    import { showToast } from '@/utils/toast';
    import Select from '@/components/ui/Select.svelte';
    import Input from '@/components/ui/Input.svelte';
    import { fade } from 'svelte/transition';
    import { bulkDelete as customerBulkDelete } from '@/routes/admin/master-data/customers';

    let { users = { data: [], links: [], total: 0 }, filters = {} } = $props();

    // svelte-ignore state_referenced_locally
    let searchQuery = $state(filters.search || '');
    // svelte-ignore state_referenced_locally
    let perPage = $state(filters.perPage || 10);
    let searchTimeout;

    // Checkbox state
    let selectedCustomers = $state([]);
    let selectAll = $derived(
        selectedCustomers.length === users.data.length && users.data.length > 0,
    );

    // Modal state
    let isModalOpen = $state(false);
    let isEditing = $state(false);
    let editId = $state(null);
    let deleteModalOpen = $state(false);
    let deleteBulkModalOpen = $state(false);
    let itemToDelete = $state(null);
    let submittingBulkDelete = $state(false);

    // Detail drawer
    let drawerOpen = $state(false);
    let drawerCustomer = $state(null);

    // Dropdown action menu per-row
    let openMenuId = $state(null);
    let menuPos = $state({ top: 0, left: 0, above: false });
    let menuNode = $state(null);

    const form = useForm({
        name: '',
        email: '',
        password: '',
    });

    function toggleSelectAll() {
        if (selectAll) {
            selectedCustomers = [];
        } else {
            selectedCustomers = users.data.map((u) => u.id);
        }
    }

    function toggleSelect(id) {
        if (selectedCustomers.includes(id)) {
            selectedCustomers = selectedCustomers.filter(
                (custId) => custId !== id,
            );
        } else {
            selectedCustomers = [...selectedCustomers, id];
        }
    }

    function updateQuery() {
        router.get(
            '/admin/master-data/customers',
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

    function openEditModal(customer) {
        isEditing = true;
        editId = customer.id;
        form.reset();
        form.clearErrors();
        form.name = customer.name;
        form.email = customer.email;
        form.password = '';
        isModalOpen = true;
        openMenuId = null;
    }

    function closeModal() {
        isModalOpen = false;
        form.reset();
    }

    function saveCustomer(e) {
        e.preventDefault();
        if (isEditing) {
            form.put(`/admin/master-data/customers/${editId}`, {
                onSuccess: () => {
                    showToast('Pelanggan berhasil diperbarui', 'success');
                    closeModal();
                },
                onError: (err) => {
                    if (err.error) showToast(err.error, 'error');
                },
            });
        } else {
            form.post('/admin/master-data/customers', {
                onSuccess: () => {
                    showToast('Pelanggan berhasil ditambahkan', 'success');
                    closeModal();
                },
                onError: (err) => {
                    if (err.error) showToast(err.error, 'error');
                },
            });
        }
    }

    function confirmDelete(customer) {
        itemToDelete = customer;
        deleteModalOpen = true;
        openMenuId = null;
    }

    function executeBulkDelete() {
        if (selectedCustomers.length === 0) return;
        submittingBulkDelete = true;
        router.post(
            customerBulkDelete.url(),
            {
                ids: selectedCustomers,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    selectedCustomers = [];
                    deleteBulkModalOpen = false;
                },
                onError: (err) => {
                    const first = Object.values(err)[0] || 'Gagal menghapus pelanggan terpilih.';
                    showToast(first, 'error');
                },
                onFinish: () => {
                    submittingBulkDelete = false;
                }
            }
        );
    }

    function executeDelete() {
        if (!itemToDelete) return;

        router.delete(`/admin/master-data/customers/${itemToDelete.id}`, {
            onSuccess: () => {
                showToast('Pelanggan berhasil dihapus', 'success');
                deleteModalOpen = false;
                itemToDelete = null;
            },
            onError: (err) => {
                showToast(err?.error || 'Gagal menghapus pelanggan', 'error');
                deleteModalOpen = false;
            },
        });
    }

    function toggleStatus(customer) {
        router.post(
            `/admin/master-data/customers/${customer.id}/toggle-active`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    showToast(
                        `Status ${customer.name} berhasil diubah`,
                        'success',
                    );
                },
                onError: (err) => {
                    showToast(err?.error || 'Gagal mengubah status', 'error');
                },
            },
        );
        openMenuId = null;
    }

    function openDrawer(customer) {
        drawerCustomer = customer;
        drawerOpen = true;
        openMenuId = null;
    }

    function openMenu(e, custId) {
        e.stopPropagation();
        if (openMenuId === custId) {
            openMenuId = null;
            return;
        }
        const btn = e.currentTarget;
        const rect = btn.getBoundingClientRect();
        const viewportH = window.innerHeight;
        const spaceBelow = viewportH - rect.bottom;
        const above = spaceBelow < 180;
        menuPos = {
            top: above
                ? rect.top + window.scrollY - 4
                : rect.bottom + window.scrollY + 4,
            left: rect.right + window.scrollX - 168,
            above,
        };
        openMenuId = custId;
    }

    function handleWindowClick() {
        openMenuId = null;
    }

    function getInitials(name) {
        if (!name) return 'C';
        return name
            .split(' ')
            .map((n) => n[0])
            .join('')
            .substring(0, 2)
            .toUpperCase();
    }

    function formatDate(dateStr) {
        if (!dateStr) return null;
        return new Date(dateStr).toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        });
    }

    function formatDateTime(dateStr) {
        if (!dateStr) return 'Belum Login';
        return new Date(dateStr).toLocaleString('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    function getGenderLabel(gender) {
        if (gender === 'male')
            return {
                label: 'Laki-laki',
                icon: 'ti-gender-male',
                color: 'text-sky-500',
            };
        if (gender === 'female')
            return {
                label: 'Perempuan',
                icon: 'ti-gender-female',
                color: 'text-pink-500',
            };
        return null;
    }

    function calculateAge(birthDate) {
        if (!birthDate) return null;
        const today = new Date();
        const birth = new Date(birthDate);
        let age = today.getFullYear() - birth.getFullYear();
        const m = today.getMonth() - birth.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
        return age;
    }
</script>

<svelte:window onclick={handleWindowClick} />

<svelte:head>
    <title>Master Data: Pelanggan</title>
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
                        Manajemen Pelanggan
                    </h3>
                    <p
                        class="text-xs text-slate-400 font-bold uppercase tracking-wider mt-1"
                    >
                        Pengaturan Akun & Data Pelanggan
                    </p>
                </div>
                <button
                    onclick={openAddModal}
                    class="flex items-center justify-center gap-2 px-5 py-3 bg-brand-blueRoyal hover:bg-blue-800 text-white font-bold rounded-2xl text-xs transition duration-200 shadow-lg shadow-brand-blueRoyal/20 shrink-0 font-outfit uppercase tracking-wider"
                >
                    <i class="ti ti-user-plus text-base"></i>
                    <span>Tambah Pelanggan</span>
                </button>
            </div>

            <!-- Main Section: Table Card -->
            <div
                class="bg-white rounded-3xl border border-slate-200/80 shadow-card overflow-hidden"
            >
                <!-- Header, PerPage & Search -->
                <div
                    class="p-6 border-b border-slate-100 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 bg-slate-50/20"
                >
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

                    <div class="flex-grow sm:max-w-md w-full sm:ml-auto">
                        <Input
                            type="text"
                            bind:value={searchQuery}
                            oninput={handleSearch}
                            placeholder="Cari nama, email, atau no. HP..."
                            icon="ti-search"
                        />
                    </div>
                </div>

                <!-- Bulk Actions Bar -->
                {#if selectedCustomers.length > 0}
                    <div
                        transition:fade={{ duration: 150 }}
                        class="px-6 py-4 bg-brand-blueLight/30 border-b border-slate-150 flex items-center justify-between gap-4 flex-wrap"
                    >
                        <div class="flex items-center gap-3">
                            <span class="text-xs font-bold text-slate-555 bg-white border border-slate-200 px-2.5 py-1 rounded-lg shadow-soft font-outfit uppercase tracking-wider flex items-center gap-1.5">
                                <i class="ti ti-checkbox text-brand-blueRoyal text-sm"></i>
                                {selectedCustomers.length} Pelanggan Terpilih
                            </span>
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                onclick={() => {
                                    selectedCustomers = [];
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

                {#if users.data.length === 0}
                    <div class="py-16 text-center">
                        <div
                            class="w-16 h-16 rounded-full bg-slate-100 flex items-center justify-center mx-auto mb-4"
                        >
                            <i class="ti ti-users text-2xl text-slate-300"></i>
                        </div>
                        <p class="font-bold text-slate-400 font-outfit">
                            Tidak ada data pelanggan yang cocok.
                        </p>
                        <p class="text-xs text-slate-300 mt-1">
                            Coba ubah kata kunci pencarian.
                        </p>
                    </div>
                {:else}
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr
                                    class="border-b border-slate-100 bg-slate-50/50 text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit"
                                >
                                    <th class="py-4 px-6 w-12 text-center">
                                        <input
                                            type="checkbox"
                                            checked={selectAll}
                                            onchange={toggleSelectAll}
                                            class="rounded border-slate-300 text-brand-blueRoyal focus:ring-brand-blueRoyal/20 w-4 h-4 cursor-pointer"
                                        />
                                    </th>
                                    <th class="py-4 px-6">Pelanggan</th>
                                    <th class="py-4 px-6">No. HP</th>
                                    <th class="py-4 px-6">Gender / Usia</th>
                                    <th class="py-4 px-6 text-center"
                                        >Transaksi</th
                                    >
                                    <th class="py-4 px-6 text-center">Koin</th>
                                    <th class="py-4 px-6">Bergabung</th>
                                    <th class="py-4 px-6">Terakhir Aktif</th>
                                    <th class="py-4 px-6">Status</th>
                                    <th class="py-4 px-6 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-slate-100 text-slate-700 text-sm font-medium"
                            >
                                {#each users.data as customer (customer.id)}
                                    {@const isActive =
                                        customer.is_active ?? true}
                                    {@const isSelected =
                                        selectedCustomers.includes(customer.id)}
                                    {@const genderInfo = getGenderLabel(
                                        customer.gender,
                                    )}
                                    {@const age = calculateAge(
                                        customer.birth_date,
                                    )}

                                    <tr
                                        class="hover:bg-slate-50/50 transition duration-150 {isSelected
                                            ? 'bg-brand-blueRoyal/5'
                                            : ''}"
                                    >
                                        <!-- Checkbox -->
                                        <td class="py-4 px-6 text-center">
                                            <input
                                                type="checkbox"
                                                checked={isSelected}
                                                onchange={() =>
                                                    toggleSelect(customer.id)}
                                                class="rounded border-slate-300 text-brand-blueRoyal focus:ring-brand-blueRoyal/20 w-4 h-4 cursor-pointer"
                                            />
                                        </td>

                                        <!-- Customer info -->
                                        <td class="py-4 px-6">
                                            <div
                                                class="flex items-center gap-3"
                                            >
                                                <!-- Avatar: photo or initials -->
                                                {#if customer.avatar}
                                                    <img
                                                        src="/storage/{customer.avatar}"
                                                        alt={customer.name}
                                                        class="w-10 h-10 rounded-full object-cover shadow-sm shrink-0 ring-2 ring-slate-100"
                                                    />
                                                {:else}
                                                    <div
                                                        class="w-10 h-10 rounded-full bg-gradient-to-tr from-brand-blueRoyal to-sky-500 text-white flex items-center justify-center text-xs font-black shadow-sm shrink-0"
                                                    >
                                                        {getInitials(
                                                            customer.name,
                                                        )}
                                                    </div>
                                                {/if}
                                                <div>
                                                    <p
                                                        class="text-sm font-bold text-slate-800 leading-tight"
                                                    >
                                                        {customer.name}
                                                    </p>
                                                    <p
                                                        class="text-[11px] text-slate-400 mt-0.5"
                                                    >
                                                        {customer.email}
                                                    </p>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Phone -->
                                        <td class="py-4 px-6">
                                            {#if customer.phone_number}
                                                <span
                                                    class="text-xs text-slate-600 font-medium flex items-center gap-1"
                                                >
                                                    <i
                                                        class="ti ti-phone text-slate-300"
                                                    ></i>
                                                    {customer.phone_number}
                                                </span>
                                            {:else}
                                                <span
                                                    class="text-xs text-slate-300 italic"
                                                    >—</span
                                                >
                                            {/if}
                                        </td>

                                        <!-- Gender / Age -->
                                        <td class="py-4 px-6">
                                            {#if genderInfo}
                                                <div
                                                    class="flex flex-col gap-0.5"
                                                >
                                                    <span
                                                        class="text-xs font-semibold flex items-center gap-1 {genderInfo.color}"
                                                    >
                                                        <i
                                                            class="ti {genderInfo.icon}"
                                                        ></i>
                                                        {genderInfo.label}
                                                    </span>
                                                    {#if age !== null}
                                                        <span
                                                            class="text-[11px] text-slate-400"
                                                        >
                                                            {age} tahun
                                                            {#if customer.birth_date}
                                                                &middot; {formatDate(
                                                                    customer.birth_date,
                                                                )}
                                                            {/if}
                                                        </span>
                                                    {/if}
                                                </div>
                                            {:else}
                                                <span
                                                    class="text-xs text-slate-300 italic"
                                                    >—</span
                                                >
                                            {/if}
                                        </td>

                                        <!-- Transactions count -->
                                        <td class="py-4 px-6 text-center">
                                            <span
                                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-black bg-indigo-50 text-indigo-600 border border-indigo-100"
                                            >
                                                <i
                                                    class="ti ti-shopping-bag text-[11px]"
                                                ></i>
                                                {customer.transactions_count ??
                                                    0}
                                            </span>
                                        </td>

                                        <!-- Coins -->
                                        <td class="py-4 px-6 text-center">
                                            {#if (customer.coins_balance ?? 0) > 0}
                                                <span
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-black bg-amber-50 text-amber-600 border border-amber-100"
                                                >
                                                    <i
                                                        class="ti ti-coin text-[11px]"
                                                    ></i>
                                                    {(
                                                        customer.coins_balance ??
                                                        0
                                                    ).toLocaleString('id-ID')}
                                                </span>
                                            {:else}
                                                <span
                                                    class="text-xs text-slate-300"
                                                    >0</span
                                                >
                                            {/if}
                                        </td>

                                        <!-- Join date -->
                                        <td class="py-4 px-6">
                                            <span
                                                class="text-xs text-slate-500 whitespace-nowrap"
                                            >
                                                {formatDate(
                                                    customer.created_at,
                                                ) ?? '—'}
                                            </span>
                                        </td>

                                        <!-- Last active -->
                                        <td class="py-4 px-6">
                                            <span
                                                class="text-xs text-slate-500 flex items-center gap-1.5 whitespace-nowrap"
                                            >
                                                <span
                                                    class="w-1.5 h-1.5 rounded-full shrink-0 {isActive
                                                        ? 'bg-emerald-400'
                                                        : 'bg-slate-300'}"
                                                ></span>
                                                {formatDateTime(
                                                    customer.last_active_at,
                                                )}
                                            </span>
                                        </td>

                                        <!-- Status badge -->
                                        <td class="py-4 px-6">
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

                                        <!-- Actions dropdown -->
                                        <td class="py-4 px-6 text-center">
                                            <button
                                                onclick={(e) =>
                                                    openMenu(e, customer.id)}
                                                class="w-8 h-8 rounded-lg border border-slate-200 hover:bg-slate-100 text-slate-500 flex items-center justify-center transition mx-auto"
                                                title="Tindakan"
                                            >
                                                <i
                                                    class="ti ti-dots-vertical text-sm"
                                                ></i>
                                            </button>
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                {/if}

                <Pagination paginator={users} />
            </div>
        </main>
    </div>
</AdminLayout>

<!-- Fixed dropdown action menu -->
{#if openMenuId !== null}
    {@const customer = users.data.find((c) => c.id === openMenuId)}
    {@const isActive = customer?.is_active ?? true}

    <!-- svelte-ignore a11y_click_events_have_key_events -->
    <div
        bind:this={menuNode}
        role="menu"
        tabindex="-1"
        class="fixed z-[9999] bg-white border border-slate-200 rounded-2xl shadow-xl py-1.5 w-48 overflow-hidden"
        style="
            left: {menuPos.left}px;
            {menuPos.above
            ? `bottom: calc(100vh - ${menuPos.top}px);`
            : `top: ${menuPos.top}px;`}
        "
        onclick={(e) => e.stopPropagation()}
    >
        <button
            onclick={() => customer && openDrawer(customer)}
            class="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 transition text-left"
        >
            <i class="ti ti-eye w-4 text-indigo-500"></i>
            Detail Pelanggan
        </button>

        <button
            onclick={() => customer && openEditModal(customer)}
            class="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 transition text-left"
        >
            <i class="ti ti-pencil w-4 text-brand-blueRoyal"></i>
            Edit Data
        </button>

        <button
            onclick={() => customer && toggleStatus(customer)}
            class="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm transition text-left
                {isActive
                ? 'text-amber-600 hover:bg-amber-50'
                : 'text-emerald-600 hover:bg-emerald-50'}"
        >
            <i class="ti {isActive ? 'ti-ban' : 'ti-check'} w-4"></i>
            {isActive ? 'Nonaktifkan' : 'Aktifkan'}
        </button>

        <div class="my-1 border-t border-slate-100"></div>

        <button
            onclick={() => customer && confirmDelete(customer)}
            class="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm text-rose-600 hover:bg-rose-50 transition text-left"
        >
            <i class="ti ti-trash w-4"></i>
            Hapus Akun
        </button>
    </div>
{/if}

<!-- Detail Drawer -->
{#if drawerOpen && drawerCustomer}
    <!-- svelte-ignore a11y_click_events_have_key_events -->
    <div
        class="fixed inset-0 z-40 bg-slate-900/40 backdrop-blur-sm"
        onclick={() => (drawerOpen = false)}
        role="button"
        tabindex="0"
    ></div>
    <aside
        class="fixed right-0 top-0 bottom-0 z-50 w-full max-w-sm bg-white shadow-2xl flex flex-col animate-in slide-in-from-right duration-200"
    >
        <!-- Drawer header -->
        <div
            class="px-6 py-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50"
        >
            <h3 class="font-outfit font-black text-lg text-slate-800">
                Detail Pelanggan
            </h3>
            <button
                onclick={() => (drawerOpen = false)}
                class="p-1 text-slate-400 hover:text-slate-700 transition"
                aria-label="Tutup Detail"
            >
                <i class="ti ti-x text-xl"></i>
            </button>
        </div>

        <!-- Drawer body -->
        <div class="flex-grow overflow-y-auto p-6 space-y-6">
            <!-- Profile header -->
            <div class="flex flex-col items-center text-center gap-3">
                {#if drawerCustomer.avatar}
                    <img
                        src="/storage/{drawerCustomer.avatar}"
                        alt={drawerCustomer.name}
                        class="w-20 h-20 rounded-full object-cover ring-4 ring-slate-100 shadow-md"
                    />
                {:else}
                    <div
                        class="w-20 h-20 rounded-full bg-gradient-to-tr from-brand-blueRoyal to-sky-500 text-white flex items-center justify-center text-2xl font-black shadow-md"
                    >
                        {getInitials(drawerCustomer.name)}
                    </div>
                {/if}
                <div>
                    <p class="font-black text-slate-800 text-xl font-outfit">
                        {drawerCustomer.name}
                    </p>
                    <p class="text-sm text-slate-400 mt-0.5">
                        {drawerCustomer.email}
                    </p>
                    <span
                        class="mt-2 inline-flex px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider
                        {(drawerCustomer.is_active ?? true)
                            ? 'bg-emerald-50 text-emerald-600 border border-emerald-200/50'
                            : 'bg-slate-50 text-slate-500 border border-slate-200/50'}"
                    >
                        {(drawerCustomer.is_active ?? true)
                            ? 'Aktif'
                            : 'Nonaktif'}
                    </span>
                </div>
            </div>

            <!-- Stats row -->
            <div class="grid grid-cols-2 gap-3">
                <div
                    class="rounded-2xl bg-indigo-50 border border-indigo-100 p-4 text-center"
                >
                    <p class="text-2xl font-black text-indigo-600 font-outfit">
                        {drawerCustomer.transactions_count ?? 0}
                    </p>
                    <p
                        class="text-[11px] font-bold text-indigo-400 uppercase tracking-wider mt-0.5"
                    >
                        Transaksi
                    </p>
                </div>
                <div
                    class="rounded-2xl bg-amber-50 border border-amber-100 p-4 text-center"
                >
                    <p class="text-2xl font-black text-amber-600 font-outfit">
                        {(drawerCustomer.coins_balance ?? 0).toLocaleString(
                            'id-ID',
                        )}
                    </p>
                    <p
                        class="text-[11px] font-bold text-amber-400 uppercase tracking-wider mt-0.5"
                    >
                        Koin
                    </p>
                </div>
            </div>

            <!-- Detail info list -->
            <div class="space-y-3">
                {#each [{ icon: 'ti-phone', label: 'No. HP', value: drawerCustomer.phone_number || '—' }, { icon: 'ti-gender-bigender', label: 'Gender', value: getGenderLabel(drawerCustomer.gender)?.label || '—' }, { icon: 'ti-cake', label: 'Tanggal Lahir', value: drawerCustomer.birth_date ? `${formatDate(drawerCustomer.birth_date)} (${calculateAge(drawerCustomer.birth_date)} tahun)` : '—' }, { icon: 'ti-calendar-plus', label: 'Bergabung', value: formatDate(drawerCustomer.created_at) || '—' }, { icon: 'ti-clock', label: 'Terakhir Aktif', value: formatDateTime(drawerCustomer.last_active_at) }] as info}
                    <div class="flex items-start gap-3">
                        <div
                            class="w-8 h-8 rounded-xl bg-slate-100 flex items-center justify-center shrink-0"
                        >
                            <i class="ti {info.icon} text-slate-500 text-sm"
                            ></i>
                        </div>
                        <div>
                            <p
                                class="text-[10px] font-bold text-slate-400 uppercase tracking-wider"
                            >
                                {info.label}
                            </p>
                            <p
                                class="text-sm font-semibold text-slate-700 mt-0.5"
                            >
                                {info.value}
                            </p>
                        </div>
                    </div>
                {/each}
            </div>
        </div>

        <!-- Drawer footer actions -->
        <div class="p-6 border-t border-slate-100 flex gap-3">
            <button
                onclick={() => openEditModal(drawerCustomer)}
                class="flex-1 flex items-center justify-center gap-2 py-3 bg-brand-blueRoyal hover:bg-blue-800 text-white font-bold rounded-2xl text-xs transition shadow-lg shadow-brand-blueRoyal/20 font-outfit uppercase tracking-wider"
            >
                <i class="ti ti-pencil"></i>
                Edit
            </button>
            <button
                onclick={() => confirmDelete(drawerCustomer)}
                class="w-11 h-11 flex items-center justify-center rounded-2xl border border-rose-200 text-rose-500 hover:bg-rose-50 transition shrink-0"
                title="Hapus"
            >
                <i class="ti ti-trash text-sm"></i>
            </button>
        </div>
    </aside>
{/if}

<!-- Modal Customer -->
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
                        ? 'Ubah Data Pelanggan'
                        : 'Tambah Pelanggan Baru'}
                </h3>
                <button
                    type="button"
                    aria-label="Close modal"
                    onclick={closeModal}
                    class="p-1 text-slate-400 hover:text-slate-700 transition"
                >
                    <i class="ti ti-x text-xl"></i>
                </button>
            </div>

            <!-- Modal Body Form -->
            <form onsubmit={saveCustomer} class="p-6 space-y-4">
                <Input
                    type="text"
                    bind:value={form.name}
                    label="Nama Lengkap"
                    required={true}
                    placeholder="Contoh: John Doe"
                    error={form.errors.name}
                />

                <Input
                    type="email"
                    bind:value={form.email}
                    label="Username / Email"
                    required={true}
                    readonly={isEditing}
                    placeholder="john@domain.id"
                    error={form.errors.email}
                />

                <Input
                    type="password"
                    bind:value={form.password}
                    label={isEditing
                        ? 'Kata Sandi (Kosongkan jika tak diubah)'
                        : 'Kata Sandi'}
                    required={!isEditing}
                    placeholder="••••••••"
                    error={form.errors.password}
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
                        Simpan Akun
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
                Hapus Pelanggan?
            </h4>
            <p class="text-sm text-center text-slate-500 font-medium mb-8">
                Data pelanggan <strong>{itemToDelete?.name}</strong> akan terhapus
                secara permanen dan tidak dapat dikembalikan.
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
                Hapus {selectedCustomers.length} Pelanggan Terpilih?
            </h4>
            <p class="text-sm text-center text-slate-550 font-medium mb-8">
                Apakah Anda yakin ingin menghapus <strong>{selectedCustomers.length} pelanggan</strong> yang terpilih secara permanen dari sistem? Tindakan ini tidak dapat dibatalkan.
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
