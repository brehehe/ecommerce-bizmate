<script>
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { page, router, Link } from '@inertiajs/svelte';
    import { useForm } from '@inertiajs/svelte';
    import Pagination from '@/components/ui/Pagination.svelte';
    import { showToast } from '@/utils/toast';
    import SelectSearch from '@/components/ui/SelectSearch.svelte';
    import Select from '@/components/ui/Select.svelte';
    import Input from '@/components/ui/Input.svelte';
    import { fade } from 'svelte/transition';
    import { bulkDelete as adminBulkDelete } from '@/routes/admin/master-data/admins';

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
    let deleteBulkModalOpen = $state(false);
    let itemToDelete = $state(null);
    let submittingBulkDelete = $state(false);

    // Dropdown action menu per-row
    let openMenuId = $state(null);
    let menuPos = $state({ top: 0, left: 0, above: false });
    let menuNode = $state(null);

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
        openMenuId = null;
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
                    closeModal();
                },
                onError: (err) => {
                    if (err.error) showToast(err.error, 'error');
                },
            });
        } else {
            form.post('/admin/master-data/admins', {
                onSuccess: () => {
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
        openMenuId = null;
    }

    function executeBulkDelete() {
        if (selectedAdmins.length === 0) return;
        submittingBulkDelete = true;
        router.post(
            adminBulkDelete.url(),
            {
                ids: selectedAdmins,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    selectedAdmins = [];
                    deleteBulkModalOpen = false;
                },
                onError: (err) => {
                    const first = Object.values(err)[0] || 'Gagal menghapus admin terpilih.';
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

        router.delete(`/admin/master-data/admins/${itemToDelete.id}`, {
            onSuccess: () => {
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
                    // Handled by flash message automatically
                },
                onError: (err) => {
                    showToast(err?.error || 'Gagal mengubah status', 'error');
                },
            },
        );
        openMenuId = null;
    }

    function openMenu(e, adminId) {
        e.stopPropagation();
        if (openMenuId === adminId) {
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
            left: rect.right + window.scrollX - 160, // align right edge
            above,
        };
        openMenuId = adminId;
    }

    function handleWindowClick() {
        openMenuId = null;
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
        } else if (roleName === 'Kurir Toko') {
            return {
                bg: 'bg-amber-50 text-amber-600 border border-amber-200/50',
                avatar: 'from-amber-500 to-orange-500',
            };
        }
        return {
            bg: 'bg-slate-50 text-slate-600 border border-slate-200/50',
            avatar: 'from-slate-500 to-slate-600',
        };
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
</script>

<svelte:window onclick={handleWindowClick} />

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
                class="bg-white rounded-3xl border border-slate-200/80 shadow-card p-6"
            >
                <div class="w-full sm:max-w-xs">
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
                            placeholder="Cari nama, email, atau no. HP..."
                            icon="ti-search"
                        />
                    </div>
                </div>

                <!-- Bulk Actions Bar -->
                {#if selectedAdmins.length > 0}
                    <div
                        transition:fade={{ duration: 150 }}
                        class="px-6 py-4 bg-brand-blueLight/30 border-b border-slate-150 flex items-center justify-between gap-4 flex-wrap"
                    >
                        <div class="flex items-center gap-3">
                            <span class="text-xs font-bold text-slate-550 bg-white border border-slate-200 px-2.5 py-1 rounded-lg shadow-soft font-outfit uppercase tracking-wider flex items-center gap-1.5">
                                <i class="ti ti-checkbox text-brand-blueRoyal text-sm"></i>
                                {selectedAdmins.length} Admin Terpilih
                            </span>
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                onclick={() => {
                                    selectedAdmins = [];
                                }}
                                class="px-4 py-2 border border-slate-200 hover:bg-slate-50 text-slate-550 font-bold rounded-xl text-xs transition uppercase tracking-wider font-outfit cursor-pointer"
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
                            <i
                                class="ti ti-shield-alert text-2xl text-slate-300"
                            ></i>
                        </div>
                        <p class="font-bold text-slate-400 font-outfit">
                            Tidak ada akun admin yang cocok.
                        </p>
                        <p class="text-xs text-slate-300 mt-1">
                            Coba ubah filter atau kata kunci pencarian.
                        </p>
                    </div>
                {:else}
                    <!-- Table Area -->
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
                                    <th class="py-4 px-6">Pengguna Admin</th>
                                    <th class="py-4 px-6">Hak Akses</th>
                                    <th class="py-4 px-6">No. HP</th>
                                    <th class="py-4 px-6">Bergabung</th>
                                    <th class="py-4 px-6">Terakhir Aktif</th>
                                    <th class="py-4 px-6">Status</th>
                                    <th class="py-4 px-6 text-center">Aksi</th>
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
                                                    toggleSelect(admin.id)}
                                                class="rounded border-slate-300 text-brand-blueRoyal focus:ring-brand-blueRoyal/20 w-4 h-4 cursor-pointer"
                                            />
                                        </td>

                                        <!-- User info -->
                                        <td class="py-4 px-6">
                                            <div
                                                class="flex items-center gap-3"
                                            >
                                                <!-- Avatar: photo or initials -->
                                                {#if admin.avatar}
                                                    <img
                                                        src="/storage/{admin.avatar}"
                                                        alt={admin.name}
                                                        class="w-10 h-10 rounded-full object-cover shadow-sm shrink-0 ring-2 ring-slate-100"
                                                    />
                                                {:else}
                                                    <div
                                                        class="w-10 h-10 rounded-full bg-gradient-to-tr {roleStyles.avatar} text-white flex items-center justify-center text-xs font-black shadow-sm shrink-0"
                                                    >
                                                        {getInitials(
                                                            admin.name,
                                                        )}
                                                    </div>
                                                {/if}
                                                <div>
                                                    <p
                                                        class="text-sm font-bold text-slate-800 leading-tight"
                                                    >
                                                        {admin.name}
                                                    </p>
                                                    <p
                                                        class="text-[11px] text-slate-400 mt-0.5"
                                                    >
                                                        {admin.email}
                                                    </p>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Role badge -->
                                        <td class="py-4 px-6">
                                            <span
                                                class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider whitespace-nowrap {roleStyles.bg}"
                                            >
                                                {primaryRole}
                                            </span>
                                        </td>

                                        <!-- Phone -->
                                        <td class="py-4 px-6">
                                            {#if admin.phone_number}
                                                <span
                                                    class="text-xs text-slate-600 font-medium flex items-center gap-1"
                                                >
                                                    <i
                                                        class="ti ti-phone text-slate-300"
                                                    ></i>
                                                    {admin.phone_number}
                                                </span>
                                            {:else}
                                                <span
                                                    class="text-xs text-slate-300 italic"
                                                    >—</span
                                                >
                                            {/if}
                                        </td>

                                        <!-- Join date -->
                                        <td class="py-4 px-6">
                                            <span
                                                class="text-xs text-slate-500 whitespace-nowrap"
                                            >
                                                {formatDate(admin.created_at) ??
                                                    '—'}
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
                                                    admin.last_active_at,
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
                                                    openMenu(e, admin.id)}
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
    {@const admin = users.data.find((a) => a.id === openMenuId)}
    {@const primaryRole = admin?.roles?.[0]?.name ?? 'Admin'}
    {@const isSuperAdmin = primaryRole === 'Super Admin'}
    {@const isActive = admin?.is_active ?? true}
    {@const isKurir = primaryRole === 'Kurir Toko'}

    <!-- svelte-ignore a11y_click_events_have_key_events -->
    <div
        bind:this={menuNode}
        role="menu"
        tabindex="-1"
        class="fixed z-[9999] bg-white border border-slate-200 rounded-2xl shadow-xl py-1.5 w-44 overflow-hidden"
        style="
            left: {menuPos.left}px;
            {menuPos.above
            ? `bottom: calc(100vh - ${menuPos.top}px);`
            : `top: ${menuPos.top}px;`}
        "
        onclick={(e) => e.stopPropagation()}
    >
        <button
            onclick={() => admin && openEditModal(admin)}
            class="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 transition text-left"
        >
            <i class="ti ti-pencil w-4 text-brand-blueRoyal"></i>
            Edit Admin
        </button>

        <button
            onclick={() => admin && toggleStatus(admin)}
            disabled={isSuperAdmin}
            class="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm transition text-left
                {isSuperAdmin
                ? 'text-slate-300 cursor-not-allowed'
                : isActive
                  ? 'text-amber-600 hover:bg-amber-50'
                  : 'text-emerald-600 hover:bg-emerald-50'}"
        >
            <i class="ti {isActive ? 'ti-ban' : 'ti-check'} w-4"></i>
            {isActive ? 'Nonaktifkan' : 'Aktifkan'}
        </button>

        {#if isKurir}
            <Link
                href="/admin/master-data/admins/{openMenuId}/courier-history"
                class="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 transition"
            >
                <i class="ti ti-history w-4 text-indigo-500"></i>
                Riwayat Kurir
            </Link>
        {/if}

        <div class="my-1 border-t border-slate-100"></div>

        <button
            onclick={() => admin && confirmDelete(admin)}
            disabled={isSuperAdmin}
            class="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm transition text-left
                {isSuperAdmin
                ? 'text-slate-300 cursor-not-allowed'
                : 'text-rose-600 hover:bg-rose-50'}"
        >
            <i class="ti ti-trash w-4"></i>
            Hapus Akun
        </button>
    </div>
{/if}

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
                Hapus {selectedAdmins.length} Admin Terpilih?
            </h4>
            <p class="text-sm text-center text-slate-550 font-medium mb-8">
                Apakah Anda yakin ingin menghapus <strong>{selectedAdmins.length} admin</strong> yang terpilih secara permanen dari sistem? Tindakan ini tidak dapat dibatalkan.
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
