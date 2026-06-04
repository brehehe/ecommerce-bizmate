<script>
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { page, router, Link } from '@inertiajs/svelte';
    import { useForm } from '@inertiajs/svelte';
    import Pagination from '@/components/ui/Pagination.svelte';
    import { showToast } from '@/utils/toast';
    import SelectSearch from '@/components/ui/SelectSearch.svelte';
    import Select from '@/components/ui/Select.svelte';
    import Input from '@/components/ui/Input.svelte';

    let {
        users = { data: [], links: [], total: 0 },
        roles = [],
        filters = {},
    } = $props();

    // svelte-ignore state_referenced_locally
    let searchQuery = $state(filters.search || '');
    // svelte-ignore state_referenced_locally
    let activeFilter = $state(filters.role || 'Semua');
    // svelte-ignore state_referenced_locally
    let perPage = $state(filters.perPage || 10);
    let searchTimeout;

    // Checkbox state
    let selectedAdmins = $state([]);
    let selectAll = $derived(
        selectedAdmins.length === users.data.length && users.data.length > 0,
    );

    // Modal state
    let isModalOpen = $state(false);
    let isEditing = $state(false);
    let editId = $state(null);
    let deleteModalOpen = $state(false);
    let itemToDelete = $state(null);

    const form = useForm({
        name: '',
        email: '',
        password: '',
        role: 'Super Admin',
    });

    function toggleSelectAll() {
        if (selectAll) {
            selectedAdmins = [];
        } else {
            selectedAdmins = users.data.map((u) => u.id);
        }
    }

    function toggleSelect(id) {
        if (selectedAdmins.includes(id)) {
            selectedAdmins = selectedAdmins.filter((adminId) => adminId !== id);
        } else {
            selectedAdmins = [...selectedAdmins, id];
        }
    }

    function updateQuery() {
        router.get(
            '/admin/master-data/admins',
            { search: searchQuery, role: activeFilter, perPage: perPage },
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

    function setFilter(filter) {
        activeFilter = filter;
        updateQuery();
    }

    function openAddModal() {
        isEditing = false;
        editId = null;
        form.reset();
        form.clearErrors();
        isModalOpen = true;
    }

    function openEditModal(admin) {
        isEditing = true;
        editId = admin.id;
        form.reset();
        form.clearErrors();
        form.name = admin.name;
        form.email = admin.email;
        form.role =
            admin.roles && admin.roles.length > 0
                ? admin.roles[0].name
                : 'Super Admin';
        form.password = '';
        isModalOpen = true;
    }

    function closeModal() {
        isModalOpen = false;
        form.reset();
    }

    function saveAdmin(e) {
        e.preventDefault();
        if (isEditing) {
            form.put(`/admin/master-data/admins/${editId}`, {
                onSuccess: () => {
                    showToast('Admin berhasil diperbarui', 'success');
                    closeModal();
                },
                onError: (err) => {
                    if (err.error) showToast(err.error, 'error');
                },
            });
        } else {
            form.post('/admin/master-data/admins', {
                onSuccess: () => {
                    showToast('Admin berhasil ditambahkan', 'success');
                    closeModal();
                },
                onError: (err) => {
                    if (err.error) showToast(err.error, 'error');
                },
            });
        }
    }

    function confirmDelete(admin) {
        if (admin.roles && admin.roles.some((r) => r.name === 'Super Admin')) {
            showToast('Super Admin tidak dapat dihapus.', 'error');
            return;
        }
        itemToDelete = admin;
        deleteModalOpen = true;
    }

    function executeDelete() {
        if (!itemToDelete) return;

        router.delete(`/admin/master-data/admins/${itemToDelete.id}`, {
            onSuccess: () => {
                showToast('Admin berhasil dihapus', 'success');
                deleteModalOpen = false;
                itemToDelete = null;
            },
            onError: (err) => {
                showToast(err?.error || 'Gagal menghapus admin', 'error');
                deleteModalOpen = false;
            },
        });
    }

    function toggleStatus(admin) {
        router.post(
            `/admin/master-data/admins/${admin.id}/toggle-active`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    showToast(
                        `Status ${admin.name} berhasil diubah`,
                        'success',
                    );
                },
                onError: (err) => {
                    showToast(err?.error || 'Gagal mengubah status', 'error');
                },
            },
        );
    }

    function getInitials(name) {
        if (!name) return 'A';
        return name
            .split(' ')
            .map((n) => n[0])
            .join('')
            .substring(0, 2)
            .toUpperCase();
    }

    function getRoleStyles(roleName) {
        if (roleName === 'Super Admin') {
            return {
                bg: 'bg-purple-50 text-purple-600 border border-purple-200/50',
                avatar: 'from-purple-500 to-indigo-600',
            };
        } else if (roleName === 'Admin Penjualan') {
            return {
                bg: 'bg-pink-50 text-pink-600 border border-pink-200/50',
                avatar: 'from-rose-500 to-pink-600',
            };
        } else if (roleName === 'Admin Toko') {
            return {
                bg: 'bg-blue-50 text-brand-blueRoyal border border-blue-200/50',
                avatar: 'from-brand-blueRoyal to-sky-500',
            };
        }
        return {
            bg: 'bg-slate-50 text-slate-600 border border-slate-200/50',
            avatar: 'from-slate-500 to-slate-600',
        };
    }
</script>

<svelte:head>
    <title>Master Data: Admin</title>
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
                        Manajemen Admin Users
                    </h3>
                    <p
                        class="text-xs text-slate-400 font-bold uppercase tracking-wider mt-1"
                    >
                        Pengaturan Hak Akses & Akun Console
                    </p>
                </div>
                <button
                    onclick={openAddModal}
                    class="flex items-center justify-center gap-2 px-5 py-3 bg-brand-blueRoyal hover:bg-blue-800 text-white font-bold rounded-2xl text-xs transition duration-200 shadow-lg shadow-brand-blueRoyal/20 shrink-0 font-outfit uppercase tracking-wider"
                >
                    <i class="ti ti-user-plus text-base"></i>
                    <span>Tambah Admin</span>
                </button>
            </div>
            <!-- Filter Role Card -->
            <div
                class="bg-white rounded-3xl border border-slate-200/80 shadow-card p-6 mb-6"
            >
                <div class="w-full">
                    <SelectSearch
                        bind:value={activeFilter}
                        options={[
                            { id: 'Semua', name: 'Semua Role' },
                            ...roles.map((r) => ({ id: r.name, name: r.name })),
                        ]}
                        onchange={() => updateQuery()}
                    />
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
                            placeholder="Cari nama, email, atau peran admin..."
                            icon="ti-search"
                        />
                    </div>
                </div>

                {#if users.data.length === 0}
                    <div
                        class="py-12 text-center text-slate-400 font-bold font-outfit"
                    >
                        <i
                            class="ti ti-shield-alert text-4xl block mb-2 text-slate-300"
                        ></i>
                        Tidak ada akun admin yang cocok.
                    </div>
                {:else}
                    <!-- Table Area -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
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
                                    <th class="py-6 px-6">Pengguna Admin</th>
                                    <th class="py-6 px-6"
                                        >Hak Akses Peran (Role)</th
                                    >
                                    <th class="py-6 px-6">Terakhir Aktif</th>
                                    <th class="py-6 px-6">Status</th>
                                    <th class="py-6 px-6 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-slate-100 text-slate-700 text-sm font-medium"
                            >
                                {#each users.data as admin (admin.id)}
                                    {@const primaryRole =
                                        admin.roles && admin.roles.length > 0
                                            ? admin.roles[0].name
                                            : 'Admin'}
                                    {@const roleStyles =
                                        getRoleStyles(primaryRole)}
                                    {@const isActive = admin.is_active ?? true}
                                    {@const isSuperAdmin =
                                        primaryRole === 'Super Admin'}
                                    {@const isSelected =
                                        selectedAdmins.includes(admin.id)}

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
                                                    toggleSelect(admin.id)}
                                                class="rounded border-slate-300 text-brand-blueRoyal focus:ring-brand-blueRoyal/20 w-4 h-4 cursor-pointer"
                                            />
                                        </td>
                                        <td class="py-6 px-6">
                                            <div
                                                class="flex items-center gap-3"
                                            >
                                                <div
                                                    class="w-10 h-10 rounded-full bg-gradient-to-tr {roleStyles.avatar} text-white flex items-center justify-center text-xs font-black shadow-md shadow-slate-100 shrink-0"
                                                >
                                                    {getInitials(admin.name)}
                                                </div>
                                                <div>
                                                    <h4
                                                        class="text-sm font-bold text-slate-800 flex items-center gap-1.5"
                                                    >
                                                        {admin.name}
                                                    </h4>
                                                    <p
                                                        class="text-[11px] text-slate-400 font-bold mt-0.5 flex items-center gap-1"
                                                    >
                                                        <span
                                                            >{admin.email}</span
                                                        >
                                                    </p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-6 px-6">
                                            <span
                                                class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider {roleStyles.bg}"
                                            >
                                                {primaryRole}
                                            </span>
                                        </td>
                                        <td class="py-6 px-6">
                                            <span
                                                class="text-xs text-slate-500 font-bold flex items-center gap-1.5"
                                            >
                                                <span
                                                    class="w-1.5 h-1.5 rounded-full {isActive
                                                        ? 'bg-emerald-400'
                                                        : 'bg-slate-300'}"
                                                ></span>
                                                {admin.last_active_at
                                                    ? new Date(
                                                          admin.last_active_at,
                                                      ).toLocaleString(
                                                          'id-ID',
                                                          {
                                                              day: 'numeric',
                                                              month: 'short',
                                                              year: 'numeric',
                                                              hour: '2-digit',
                                                              minute: '2-digit',
                                                          },
                                                      )
                                                    : 'Belum Login'}
                                            </span>
                                        </td>
                                        <td class="py-6 px-6">
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
                                        <td class="py-6 px-6 text-center">
                                            <div
                                                class="flex items-center justify-center gap-2"
                                            >
                                                <button
                                                    onclick={() =>
                                                        openEditModal(admin)}
                                                    class="w-8 h-8 rounded-lg border border-slate-200 hover:bg-brand-blueLight hover:text-brand-blueRoyal text-slate-500 flex items-center justify-center transition"
                                                    title="Ubah Hak Akses"
                                                >
                                                    <i
                                                        class="ti ti-pencil text-sm"
                                                    ></i>
                                                </button>
                                                <button
                                                    onclick={() =>
                                                        toggleStatus(admin)}
                                                    disabled={isSuperAdmin}
                                                    class="w-8 h-8 rounded-lg border border-slate-200 {isSuperAdmin
                                                        ? 'opacity-50 cursor-not-allowed bg-slate-50 text-slate-400'
                                                        : isActive
                                                          ? 'hover:bg-amber-50 hover:text-amber-600 text-slate-500'
                                                          : 'hover:bg-emerald-50 hover:text-emerald-600 text-slate-400'} flex items-center justify-center transition"
                                                    title={isSuperAdmin
                                                        ? 'Status Super Admin tidak dapat diubah'
                                                        : 'Ubah Status (Aktif/Nonaktif)'}
                                                >
                                                    <i
                                                        class="ti {isActive
                                                            ? 'ti-ban'
                                                            : 'ti-check'} text-sm"
                                                    ></i>
                                                </button>
                                                {#if primaryRole === 'Kurir Toko'}
                                                    <Link
                                                        href="/admin/master-data/admins/{admin.id}/courier-history"
                                                        class="w-8 h-8 rounded-lg border border-slate-200 hover:bg-emerald-50 hover:text-emerald-600 text-slate-500 flex items-center justify-center transition"
                                                        title="Riwayat Pengiriman"
                                                    >
                                                        <i class="ti ti-history text-sm"></i>
                                                    </Link>
                                                {/if}
                                                <button
                                                    onclick={() =>
                                                        confirmDelete(admin)}
                                                    class="w-8 h-8 rounded-lg border border-slate-200 hover:bg-rose-50 hover:text-rose-600 {isSuperAdmin
                                                        ? 'opacity-50 cursor-not-allowed bg-slate-50'
                                                        : 'text-slate-500'} flex items-center justify-center transition"
                                                    title="Hapus Akun"
                                                    disabled={isSuperAdmin}
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

                <Pagination paginator={users} />
            </div>
        </main>
    </div>
</AdminLayout>

<!-- Modal Admin -->
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
                        ? 'Ubah Hak Akses Admin'
                        : 'Tambah Akun Admin Baru'}
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
            <form onsubmit={saveAdmin} class="p-6 space-y-4">
                <Input
                    type="text"
                    bind:value={form.name}
                    label="Nama Lengkap"
                    required={true}
                    placeholder="Contoh: Admin Utama"
                    error={form.errors.name}
                />

                <Input
                    type="email"
                    bind:value={form.email}
                    label="Username / Email"
                    required={true}
                    readonly={isEditing}
                    placeholder="admin@domain.id"
                    error={form.errors.email}
                />

                <SelectSearch
                    bind:value={form.role}
                    label="Hak Akses Peran"
                    required={true}
                    options={roles.map((r) => ({
                        id: r.name,
                        name: r.name,
                    }))}
                    placeholder="Ketik atau pilih role..."
                    error={form.errors.role}
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
                Hapus Akun Admin?
            </h4>
            <p class="text-sm text-center text-slate-500 font-medium mb-8">
                Data admin <strong>{itemToDelete?.name}</strong> akan terhapus secara
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
