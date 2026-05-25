<script>
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { page, inertia, router } from '@inertiajs/svelte';
    import Pagination from '@/components/ui/Pagination.svelte';

    let { roles = { data: [], links: [] }, filters = {} } = $props();

    let searchQuery = $state(filters.search || '');
    let searchTimeout;

    function handleSearch() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            router.get(
                '/admin/master-data/roles',
                { search: searchQuery },
                { preserveState: true, replace: true },
            );
        }, 500);
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
                <button
                    type="button"
                    class="px-5 py-2.5 bg-brand-blueRoyal text-white font-bold rounded-xl shadow-lg hover:bg-blue-800 transition flex items-center gap-2 text-sm"
                >
                    <i class="ti ti-plus"></i>
                    <span>Tambah Role</span>
                </button>
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
                            class="w-full text-left text-sm whitespace-nowrap"
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
                                        <td class="px-6 py-4">
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
                                        <td class="px-6 py-4">
                                            <span
                                                class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 text-slate-600"
                                            >
                                                {role.users_count || 0} Users
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <div
                                                class="flex items-center justify-end gap-2"
                                            >
                                                <button
                                                    class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:bg-brand-blueRoyal/10 hover:text-brand-blueRoyal transition"
                                                >
                                                    <i
                                                        class="ti ti-edit text-lg"
                                                    ></i>
                                                </button>
                                                <button
                                                    class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:bg-red-50 hover:text-red-500 transition"
                                                >
                                                    <i
                                                        class="ti ti-trash text-lg"
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
