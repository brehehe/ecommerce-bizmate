<script lang="ts">
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { page, router, Link } from '@inertiajs/svelte';

    let { histories = { data: [], links: [] }, levels = [], filters = {} } = $props();

    const primary = $derived(page.props.theme?.primary_color || '#0c4cb4');

    const actionLabels: Record<string, string> = {
        upgraded:   'Naik Level',
        downgraded: 'Turun Level',
        assigned:   'Ditetapkan',
        renewed:    'Diperbarui',
        expired:    'Kedaluwarsa',
    };

    const actionColors: Record<string, string> = {
        upgraded:   'bg-emerald-50 text-emerald-700',
        downgraded: 'bg-rose-50 text-rose-700',
        assigned:   'bg-blue-50 text-blue-700',
        renewed:    'bg-violet-50 text-violet-700',
        expired:    'bg-slate-100 text-slate-500',
    };

    // svelte-ignore state_referenced_locally
    let searchQ    = $state((filters as any).search || '');
    // svelte-ignore state_referenced_locally
    let filterAction = $state((filters as any).action || '');
    // svelte-ignore state_referenced_locally
    let filterLevel  = $state((filters as any).level  || '');

    function applyFilters() {
        router.get('/admin/membership/histories', {
            search: searchQ,
            action: filterAction,
            level: filterLevel,
        }, { preserveState: true, replace: true });
    }

    function resetFilters() {
        searchQ = '';
        filterAction = '';
        filterLevel = '';
        router.get('/admin/membership/histories', {}, { preserveState: true, replace: true });
    }
</script>

<svelte:head><title>Riwayat Membership — Admin</title></svelte:head>

<AdminLayout>
    <main class="w-full max-w-[1600px] mx-auto px-4 sm:px-6 py-6 space-y-5">

        <!-- Header -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-semibold tracking-tight text-slate-900">Riwayat Membership</h1>
                <p class="mt-0.5 text-sm text-slate-500">Log semua perubahan level membership pelanggan</p>
            </div>
            <Link href="/admin/membership/dashboard" class="inline-flex items-center gap-1.5 h-9 rounded-lg border border-slate-200 px-3 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                <i class="ti ti-arrow-left text-sm text-slate-400"></i>
                Kembali
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
            <select bind:value={filterAction} onchange={applyFilters}
                class="h-9 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 focus:outline-none appearance-none cursor-pointer pr-8 min-w-36">
                <option value="">Semua Aksi</option>
                {#each Object.entries(actionLabels) as [val, label]}
                    <option value={val}>{label}</option>
                {/each}
            </select>
            <select bind:value={filterLevel} onchange={applyFilters}
                class="h-9 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 focus:outline-none appearance-none cursor-pointer pr-8 min-w-36">
                <option value="">Semua Level</option>
                {#each levels as lv}
                    <option value={lv.id}>{lv.name}</option>
                {/each}
            </select>
            <button onclick={resetFilters} class="h-9 rounded-lg border border-slate-200 px-3 text-sm font-medium text-slate-600 hover:bg-slate-50 transition-colors">Reset</button>
        </div>

        <!-- Table -->
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            {#if histories.data.length === 0}
                <div class="flex flex-col items-center justify-center py-16 text-center">
                    <i class="ti ti-history text-3xl text-slate-300 mb-3"></i>
                    <p class="text-sm font-semibold text-slate-700">Belum ada riwayat</p>
                    <p class="text-xs text-slate-400 mt-1">Riwayat perubahan level akan muncul di sini</p>
                </div>
            {:else}
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50/50">
                                <th class="px-5 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Pelanggan</th>
                                <th class="px-5 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Level Lama</th>
                                <th class="px-5 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Level Baru</th>
                                <th class="px-5 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Aksi</th>
                                <th class="px-5 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Total Belanja</th>
                                <th class="px-5 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Diproses Oleh</th>
                                <th class="px-5 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Tanggal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            {#each histories.data as h}
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-5 py-3">
                                        <p class="text-sm font-medium text-slate-800">{h.user?.name ?? '—'}</p>
                                        <p class="text-xs text-slate-400">{h.user?.email ?? ''}</p>
                                    </td>
                                    <td class="px-5 py-3">
                                        {#if h.from_level}
                                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold text-white" style="background-color: {h.from_level.badge_color};">
                                                {h.from_level.name}
                                            </span>
                                        {:else}
                                            <span class="text-xs text-slate-400">—</span>
                                        {/if}
                                    </td>
                                    <td class="px-5 py-3">
                                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold text-white" style="background-color: {h.to_level?.badge_color};">
                                            {h.to_level?.name ?? '—'}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3">
                                        <span class="rounded-md px-2 py-0.5 text-xs font-semibold {actionColors[h.action] ?? 'bg-slate-100 text-slate-500'}">
                                            {actionLabels[h.action] ?? h.action}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3">
                                        <p class="text-sm text-slate-700">
                                            {new Intl.NumberFormat('id-ID',{style:'currency',currency:'IDR',minimumFractionDigits:0}).format(h.total_purchase_at_time ?? 0)}
                                        </p>
                                        <p class="text-xs text-slate-400">{h.total_transactions_at_time} transaksi</p>
                                    </td>
                                    <td class="px-5 py-3">
                                        <p class="text-sm text-slate-700">{h.processed_by ? (h.processed_by.name ?? 'Admin') : 'Otomatis'}</p>
                                    </td>
                                    <td class="px-5 py-3">
                                        <p class="text-xs text-slate-500 whitespace-nowrap">{h.created_at}</p>
                                    </td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                {#if histories.links && histories.links.length > 3}
                    <div class="border-t border-slate-100 px-5 py-3 flex justify-center">
                        <div class="flex items-center gap-1">
                            {#each histories.links as link}
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
</AdminLayout>
