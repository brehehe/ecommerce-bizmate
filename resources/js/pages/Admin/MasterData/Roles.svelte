<script>
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { updateRole } from '@/actions/App/Http/Controllers/Admin/MasterDataController';
    import { roles as rolesIndex } from '@/routes/admin/master-data';
    import { router } from '@inertiajs/svelte';
    import Pagination from '@/components/ui/Pagination.svelte';

    let { roles = { data: [], links: [] }, filters = {} } = $props();

    // svelte-ignore state_referenced_locally
    let searchQuery = $state(filters.search || '');
    let searchTimeout;
    let editingRole = $state(null);
    let roleName = $state('');
    let savingRole = $state(false);
    let roleError = $state('');

    function handleSearch() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            router.get(
                rolesIndex.url(),
                { search: searchQuery },
                { preserveState: true, replace: true },
            );
        }, 500);
    }

    function openEditRole(role) {
        editingRole = role;
        roleName = role.name;
        roleError = '';
    }

    function closeEditRole(force = false) {
        if (savingRole && !force) return;

        editingRole = null;
        roleName = '';
        roleError = '';
    }

    function saveRole() {
        if (!editingRole || !roleName.trim()) {
            roleError = 'Nama role wajib diisi.';
            return;
        }

        savingRole = true;
        roleError = '';

        router.put(
            updateRole.url({ role: editingRole.id }),
            { name: roleName.trim() },
            {
                preserveScroll: true,
                onSuccess: () => closeEditRole(true),
                onError: (errors) => {
                    roleError = errors.name ?? 'Gagal memperbarui nama role.';
                },
                onFinish: () => {
                    savingRole = false;
                },
            },
        );
    }
</script>

<svelte:head>
    <title>Master Data: Roles & Akses</title>
</svelte:head>

<AdminLayout>
    <div class="flex-grow flex flex-col min-h-screen">
        <main class="flex-grow p-4 sm:p-8 w-full max-w-full mx-auto space-y-6">
            <!-- Page Header -->
            <div
                class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4"
            >
                <div>
                    <h2 class="font-outfit font-black text-2xl text-slate-800">
                        Master Data
                    </h2>
                    <p
                        class="text-xs text-slate-400 font-bold uppercase tracking-wider"
                    >
                        Kelola data pengguna, admin, pelanggan dan hak akses
                    </p>
                </div>
            </div>

            <!-- Search Bar -->
            <div
                class="flex flex-col sm:flex-row sm:items-center justify-between gap-4"
            >
                <div class="relative w-full sm:w-96">
                    <i
                        class="ti ti-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-lg"
                    ></i>
                    <input
                        type="text"
                        bind:value={searchQuery}
                        onkeyup={handleSearch}
                        placeholder="Cari nama role..."
                        class="w-full pl-10 pr-4 py-2.5 text-sm bg-slate-50 border border-slate-200 focus:bg-white focus:ring-2 focus:ring-brand-blueRoyal/20 focus:border-brand-blueRoyal rounded-xl outline-none transition"
                    />
                </div>
                <div
                    class="text-xs font-bold text-slate-400 uppercase tracking-wider"
                >
                    Total: {roles.total || 0} Role
                </div>
            </div>

            {#if roles.data.length === 0}
                <div
                    class="bg-white rounded-3xl border border-slate-200 p-12 text-center shadow-soft"
                >
                    <div
                        class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4 text-slate-300"
                    >
                        <i class="ti ti-shield-x text-3xl"></i>
                    </div>
                    <h3 class="text-slate-800 font-bold text-lg mb-1">
                        Role Tidak Ditemukan
                    </h3>
                    <p class="text-slate-500 text-sm">
                        Coba gunakan kata kunci pencarian yang lain.
                    </p>
                </div>
            {:else}
                <div
                    class="bg-white border border-slate-200 rounded-3xl overflow-hidden shadow-soft"
                >
                    <div class="overflow-x-auto">
                        <table
                            class="w-full text-left border-collapse responsive-table roles-table"
                        >
                            <thead
                                class="bg-slate-50/50 text-slate-500 text-xs uppercase font-bold tracking-wider"
                            >
                                <tr>
                                    <th
                                        class="px-6 py-4 border-b border-slate-200"
                                        >Nama Role</th
                                    >
                                    <th
                                        class="px-6 py-4 border-b border-slate-200"
                                        >Jumlah Pengguna</th
                                    >
                                    <th
                                        class="px-6 py-4 border-b border-slate-200 text-right"
                                        >Aksi</th
                                    >
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                {#each roles.data as role (role.id)}
                                    <tr
                                        class="hover:bg-slate-50/50 transition-colors"
                                    >
                                        <td class="px-6 py-4" data-label="Nama Role">
                                            <div
                                                class="flex items-center gap-3"
                                            >
                                                <div
                                                    class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center text-slate-500 shrink-0"
                                                >
                                                    <i
                                                        class="ti ti-shield text-lg"
                                                    ></i>
                                                </div>
                                                <div
                                                    class="font-bold text-slate-800"
                                                >
                                                    {role.name}
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4" data-label="Jumlah Pengguna">
                                            <span
                                                class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 text-slate-600"
                                            >
                                                {role.users_count || 0} Users
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-right" data-label="Aksi">
                                            <div
                                                class="flex items-center justify-end gap-2"
                                            >
                                                <button
                                                    aria-label="Edit role"
                                                    onclick={() => openEditRole(role)}
                                                    class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:bg-brand-blueRoyal/10 hover:text-brand-blueRoyal transition"
                                                >
                                                    <i
                                                        class="ti ti-edit text-lg"
                                                    ></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                </div>
                <Pagination paginator={roles} />
            {/if}
        </main>
    </div>
</AdminLayout>

{#if editingRole}
    <div
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/45"
        role="presentation"
        onclick={closeEditRole}
    >
        <div
            role="dialog"
            aria-modal="true"
            aria-labelledby="edit-role-title"
            class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl"
            onclick={(event) => event.stopPropagation()}
        >
            <div class="mb-5 flex items-start justify-between gap-4">
                <div>
                    <h3 id="edit-role-title" class="text-lg font-black text-slate-800">
                        Edit Nama Role
                    </h3>
                    <p class="mt-1 text-sm text-slate-500">
                        Pengguna dan akses yang sudah terhubung tetap dipertahankan.
                    </p>
                </div>
                <button
                    type="button"
                    aria-label="Tutup"
                    onclick={closeEditRole}
                    class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                >
                    <i class="ti ti-x text-xl"></i>
                </button>
            </div>

            <label for="role-name" class="mb-2 block text-sm font-bold text-slate-700">
                Nama Role
            </label>
            <input
                id="role-name"
                type="text"
                bind:value={roleName}
                onkeydown={(event) => event.key === 'Enter' && saveRole()}
                class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm outline-none transition focus:border-brand-blueRoyal focus:ring-2 focus:ring-brand-blueRoyal/20"
                class:border-red-400={roleError}
                autofocus
            />
            {#if roleError}
                <p class="mt-2 text-xs font-medium text-red-600">{roleError}</p>
            {/if}

            <div class="mt-6 flex justify-end gap-3">
                <button
                    type="button"
                    onclick={closeEditRole}
                    disabled={savingRole}
                    class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-600 transition hover:bg-slate-50 disabled:opacity-60"
                >
                    Batal
                </button>
                <button
                    type="button"
                    onclick={saveRole}
                    disabled={savingRole}
                    class="rounded-xl bg-brand-blueRoyal px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-blue-800 disabled:opacity-60"
                >
                    {savingRole ? 'Menyimpan...' : 'Simpan Perubahan'}
                </button>
            </div>
        </div>
    </div>
{/if}

<style>
    @media (max-width: 640px) {
        .roles-table td:first-child {
            display: flex !important;
        }
    }
</style>
