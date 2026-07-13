<script lang="ts">
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { page, router, Link } from '@inertiajs/svelte';

    let { members = { data: [], links: [] }, levels = [], filters = {} } = $props();

    const primary   = $derived(page.props.theme?.primary_color   || '#0c4cb4');
    const secondary = $derived(page.props.theme?.secondary_color || '#fa7315');

    let searchQ     = $state((filters as any).search || '');
    let filterLevel = $state((filters as any).level  || '');
    let showAssignModal = $state(false);
    let selectedUser = $state<any>(null);
    let assignLevelId = $state('');
    let assignReason  = $state('');
    let isAssigning   = $state(false);

    function applyFilters() {
        router.get('/admin/membership/members', { search: searchQ, level: filterLevel }, { preserveState: true, replace: true });
    }

    function openAssignModal(member: any) {
        selectedUser  = member.user;
        assignLevelId = member.membership_level_id;
        assignReason  = '';
        showAssignModal = true;
    }

    function submitAssign() {
        if (!selectedUser || !assignLevelId) return;
        isAssigning = true;
        router.post(`/admin/membership/members/${selectedUser.id}/assign-level`, {
            membership_level_id: assignLevelId,
            reason: assignReason,
        }, {
            onSuccess: () => { showAssignModal = false; isAssigning = false; },
            onError: () => { isAssigning = false; },
        });
    }

    function fmtCurrency(n: number): string {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n ?? 0);
    }
</script>

<svelte:head><title>Daftar Member — Admin</title></svelte:head>

<AdminLayout>
    <main class="w-full max-w-[1600px] mx-auto px-4 sm:px-6 py-6 space-y-5">

        <!-- Header -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-semibold tracking-tight text-slate-900">Daftar Member</h1>
                <p class="mt-0.5 text-sm text-slate-500">Kelola membership pelanggan</p>
            </div>
            <Link href="/admin/membership/dashboard" class="inline-flex items-center gap-1.5 h-9 rounded-lg border border-slate-200 px-3 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                <i class="ti ti-arrow-left text-sm text-slate-400"></i>
                Dashboard
            </Link>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap items-end gap-3">
            <div class="relative flex-1 min-w-48">
                <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                <input type="search" placeholder="Cari nama / email..." bind:value={searchQ}
                    oninput={applyFilters}
                    class="h-9 w-full rounded-lg border border-slate-200 bg-white pl-8 pr-3 text-sm text-slate-700 placeholder-slate-400 focus:border-slate-400 focus:outline-none transition-colors" />
            </div>
            <select bind:value={filterLevel} onchange={applyFilters}
                class="h-9 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 focus:outline-none appearance-none cursor-pointer pr-8 min-w-40">
                <option value="">Semua Level</option>
                {#each levels as lv}
                    <option value={lv.id}>{lv.name}</option>
                {/each}
            </select>
        </div>

        <!-- Table -->
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            {#if members.data.length === 0}
                <div class="flex flex-col items-center justify-center py-16 text-center">
                    <i class="ti ti-users text-3xl text-slate-300 mb-3"></i>
                    <p class="text-sm font-semibold text-slate-700">Belum ada member</p>
                    <p class="text-xs text-slate-400 mt-1">Member akan muncul setelah pelanggan bergabung ke program membership</p>
                </div>
            {:else}
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50/50">
                                <th class="px-5 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Pelanggan</th>
                                <th class="px-5 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Level</th>
                                <th class="px-5 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Total Belanja</th>
                                <th class="px-5 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Transaksi</th>
                                <th class="px-5 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Poin</th>
                                <th class="px-5 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Bergabung</th>
                                <th class="px-5 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Status</th>
                                <th class="px-5 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            {#each members.data as m}
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-5 py-3">
                                        <p class="text-sm font-medium text-slate-800">{m.user?.name ?? '—'}</p>
                                        <p class="text-xs text-slate-400">{m.user?.email ?? ''}</p>
                                    </td>
                                    <td class="px-5 py-3">
                                        {#if m.level}
                                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold text-white" style="background-color: {m.level.badge_color};">
                                                <i class="ti {m.level.icon} text-[10px]"></i>
                                                {m.level.name}
                                            </span>
                                        {/if}
                                    </td>
                                    <td class="px-5 py-3">
                                        <p class="text-sm font-semibold text-slate-800">{fmtCurrency(m.total_purchase)}</p>
                                    </td>
                                    <td class="px-5 py-3">
                                        <p class="text-sm text-slate-700">{m.total_transactions}</p>
                                    </td>
                                    <td class="px-5 py-3">
                                        <p class="text-sm text-slate-700">{m.total_points}</p>
                                    </td>
                                    <td class="px-5 py-3">
                                        <p class="text-xs text-slate-500 whitespace-nowrap">{m.joined_at}</p>
                                    </td>
                                    <td class="px-5 py-3">
                                        {#if m.expires_at && new Date(m.expires_at) < new Date()}
                                            <span class="rounded-md bg-rose-50 px-2 py-0.5 text-xs font-semibold text-rose-700">Kedaluwarsa</span>
                                        {:else}
                                            <span class="rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700">Aktif</span>
                                        {/if}
                                    </td>
                                    <td class="px-5 py-3">
                                        <button
                                            type="button"
                                            onclick={() => openAssignModal(m)}
                                            class="flex h-7 items-center gap-1.5 rounded-md border border-slate-200 px-2.5 text-xs font-medium text-slate-600 hover:bg-slate-50 transition-colors"
                                        >
                                            <i class="ti ti-edit text-xs"></i>
                                            Ubah Level
                                        </button>
                                    </td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                {#if members.links && members.links.length > 3}
                    <div class="border-t border-slate-100 px-5 py-3 flex justify-center">
                        <div class="flex items-center gap-1">
                            {#each members.links as link}
                                {#if link.url}
                                    <a href={link.url} class="flex h-8 min-w-8 items-center justify-center rounded-lg border px-2.5 text-xs font-medium transition-all
                                        {link.active ? 'border-transparent text-white' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'}"
                                        style={link.active ? `background-color:${primary};border-color:${primary};` : ''}
                                    >{@html link.label}</a>
                                {:else}
                                    <span class="flex h-8 min-w-8 items-center justify-center rounded-lg border border-slate-100 px-2.5 text-xs text-slate-300">{@html link.label}</span>
                                {/if}
                            {/each}
                        </div>
                    </div>
                {/if}
            {/if}
        </div>

    </main>

    <!-- Assign Level Modal -->
    {#if showAssignModal}
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
            role="dialog" aria-modal="true"
            onclick={(e) => { if (e.target === e.currentTarget) showAssignModal = false; }}>
            <div class="w-full max-w-sm overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <h3 class="text-sm font-semibold text-slate-800">Ubah Level Membership</h3>
                    <button type="button" onclick={() => (showAssignModal = false)}
                        class="flex h-7 w-7 items-center justify-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition-colors" aria-label="Tutup">
                        <i class="ti ti-x text-sm"></i>
                    </button>
                </div>
                <div class="p-5 space-y-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Pelanggan</p>
                        <p class="text-sm font-medium text-slate-800">{selectedUser?.name}</p>
                        <p class="text-xs text-slate-400">{selectedUser?.email}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Level Baru</label>
                        <select bind:value={assignLevelId}
                            class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-700 focus:border-slate-400 focus:outline-none appearance-none cursor-pointer">
                            {#each levels as lv}
                                <option value={lv.id}>{lv.name}</option>
                            {/each}
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Alasan (opsional)</label>
                        <input type="text" bind:value={assignReason} placeholder="Contoh: Reward pelanggan setia"
                            class="h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-700 placeholder-slate-400 focus:border-slate-400 focus:outline-none transition-colors" />
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2 border-t border-slate-100 px-5 py-3">
                    <button type="button" onclick={() => (showAssignModal = false)}
                        class="rounded-lg border border-slate-200 px-4 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-50 transition-colors">Batal</button>
                    <button type="button" onclick={submitAssign} disabled={isAssigning || !assignLevelId}
                        class="rounded-lg px-4 py-1.5 text-sm font-semibold text-white transition-opacity hover:opacity-90 disabled:opacity-50"
                        style="background-color: {primary};">
                        {isAssigning ? 'Menyimpan...' : 'Simpan'}
                    </button>
                </div>
            </div>
        </div>
    {/if}

</AdminLayout>
