<script lang="ts">
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { page, router, Link } from '@inertiajs/svelte';

    let {
        member = {},
        membership = null,
        histories = [],
        vouchers = [],
        points = [],
        cashbacks = [],
        levels = [],
        progress = null,
    } = $props();

    const primary   = $derived(page.props.theme?.primary_color   || '#0c4cb4');
    const secondary = $derived(page.props.theme?.secondary_color || '#fa7315');

    let showAssignModal = $state(false);
    let assignLevelId   = $state(membership?.membership_level_id || '');
    let assignReason    = $state('');
    let isAssigning     = $state(false);
    let isSyncing       = $state(false);

    function fmtCurrency(n: number): string {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n ?? 0);
    }
    function fmt(n: number): string {
        return new Intl.NumberFormat('id-ID').format(n ?? 0);
    }

    function submitAssign() {
        if (!assignLevelId) return;
        isAssigning = true;
        router.post(`/admin/membership/members/${member.id}/assign-level`, {
            membership_level_id: assignLevelId,
            reason: assignReason,
        }, {
            onSuccess: () => { showAssignModal = false; isAssigning = false; },
            onError: () => { isAssigning = false; },
        });
    }

    function syncMembership() {
        isSyncing = true;
        router.post(`/admin/membership/members/${member.id}/sync`, {}, {
            onSuccess: () => { isSyncing = false; },
            onError: () => { isSyncing = false; },
        });
    }

    const actionLabels: Record<string, string> = {
        upgraded: 'Naik Level', downgraded: 'Turun Level',
        assigned: 'Ditetapkan', renewed: 'Diperbarui', expired: 'Kedaluwarsa',
    };
    const actionColors: Record<string, string> = {
        upgraded: 'bg-emerald-50 text-emerald-700', downgraded: 'bg-rose-50 text-rose-700',
        assigned: 'bg-blue-50 text-blue-700', renewed: 'bg-violet-50 text-violet-700',
        expired: 'bg-slate-100 text-slate-500',
    };
    const voucherStatusColors: Record<string, string> = {
        active: 'bg-emerald-50 text-emerald-700',
        used: 'bg-slate-100 text-slate-500',
        expired: 'bg-rose-50 text-rose-600',
    };
</script>

<svelte:head><title>{member.name} — Detail Member</title></svelte:head>

<AdminLayout>
    <main class="w-full max-w-[1400px] mx-auto px-4 sm:px-6 py-6 space-y-6">

        <!-- Header -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-center gap-3">
                <Link href="/admin/membership/members"
                    class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50 transition-colors">
                    <i class="ti ti-arrow-left text-sm"></i>
                </Link>
                <div class="flex items-center gap-3">
                    {#if member.avatar}
                        <img src="/storage/{member.avatar}" alt={member.name} class="h-10 w-10 rounded-full object-cover" />
                    {:else}
                        <div class="flex h-10 w-10 items-center justify-center rounded-full text-sm font-bold text-white"
                            style="background: linear-gradient(135deg, {primary}, {secondary});">
                            {member.name?.substring(0, 2)?.toUpperCase() ?? 'MB'}
                        </div>
                    {/if}
                    <div>
                        <h1 class="text-xl font-semibold tracking-tight text-slate-900">{member.name}</h1>
                        <p class="text-sm text-slate-400">{member.email}</p>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <button type="button" onclick={syncMembership} disabled={isSyncing}
                    class="inline-flex items-center gap-1.5 h-9 rounded-lg border border-slate-200 px-3 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors disabled:opacity-50">
                    <i class="ti ti-refresh text-sm {isSyncing ? 'animate-spin' : ''} text-slate-400"></i>
                    {isSyncing ? 'Sync...' : 'Sync Membership'}
                </button>
                <button type="button" onclick={() => (showAssignModal = true)}
                    class="inline-flex items-center gap-1.5 h-9 rounded-lg px-3 text-sm font-semibold text-white transition-opacity hover:opacity-90"
                    style="background-color: {primary};">
                    <i class="ti ti-award text-sm"></i>
                    Ubah Level
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">

            <!-- Left: Member info + membership card -->
            <div class="space-y-5">

                <!-- Member info -->
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                    <div class="border-b border-slate-100 px-5 py-3.5">
                        <p class="text-sm font-semibold text-slate-800">Informasi Member</p>
                    </div>
                    <div class="px-5 py-4 space-y-3">
                        {#each [
                            { icon: 'ti-mail', label: 'Email', value: member.email },
                            { icon: 'ti-phone', label: 'Telepon', value: member.phone || '—' },
                            { icon: 'ti-cake', label: 'Tanggal Lahir', value: member.birth_date || '—' },
                            { icon: 'ti-calendar', label: 'Bergabung', value: member.created_at || '—' },
                        ] as info}
                            <div class="flex items-start gap-3">
                                <i class="ti {info.icon} text-slate-400 text-sm mt-0.5 shrink-0"></i>
                                <div class="min-w-0">
                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">{info.label}</p>
                                    <p class="text-sm text-slate-700 truncate">{info.value}</p>
                                </div>
                            </div>
                        {/each}
                    </div>
                </div>

                <!-- Current membership level -->
                {#if membership?.level}
                    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                        <div class="px-5 py-4" style="background: linear-gradient(135deg, {membership.level.badge_color}22, {membership.level.badge_color}08);">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl text-white text-lg"
                                    style="background-color: {membership.level.badge_color};">
                                    <i class="ti {membership.level.icon}"></i>
                                </div>
                                <div>
                                    <p class="text-[10px] font-semibold uppercase tracking-widest" style="color: {membership.level.badge_color};">Level Saat Ini</p>
                                    <p class="text-base font-bold text-slate-900">{membership.level.name}</p>
                                </div>
                            </div>
                        </div>
                        <div class="px-5 py-4 border-t border-slate-100 space-y-2">
                            {#each [
                                { label: 'Total Belanja', value: fmtCurrency(membership.total_purchase) },
                                { label: 'Total Transaksi', value: fmt(membership.total_transactions) + 'x' },
                                { label: 'Total Produk', value: fmt(membership.total_products) + ' produk' },
                                { label: 'Total Poin', value: fmt(membership.total_points) + ' poin' },
                                { label: 'Total Cashback', value: fmtCurrency(membership.total_cashback) },
                                { label: 'Bergabung', value: membership.joined_at || '—' },
                                { label: 'Kedaluwarsa', value: membership.expires_at || 'Selamanya' },
                            ] as s}
                                <div class="flex justify-between text-sm">
                                    <span class="text-slate-500">{s.label}</span>
                                    <span class="font-medium text-slate-800">{s.value}</span>
                                </div>
                            {/each}
                        </div>

                        <!-- Progress to next level -->
                        {#if progress?.next_level && progress?.progress_purchase !== undefined}
                            <div class="px-5 py-4 border-t border-slate-100 bg-slate-50/50">
                                <div class="flex items-center justify-between mb-2">
                                    <p class="text-xs font-semibold text-slate-600">Menuju {progress.next_level.name}</p>
                                    <p class="text-xs font-bold" style="color: {progress.next_level.badge_color};">{progress.progress_purchase}%</p>
                                </div>
                                <div class="h-2 w-full rounded-full bg-slate-200 overflow-hidden">
                                    <div class="h-full rounded-full transition-all" style="width: {progress.progress_purchase}%; background-color: {progress.next_level.badge_color};"></div>
                                </div>
                                {#if progress.remaining_purchase > 0}
                                    <p class="text-xs text-slate-400 mt-1.5">Sisa {fmtCurrency(progress.remaining_purchase)} untuk naik ke <span class="font-semibold" style="color: {progress.next_level.badge_color};">{progress.next_level.name}</span></p>
                                {/if}
                            </div>
                        {/if}

                        <!-- Benefits -->
                        {#if membership.level.active_benefits?.length > 0}
                            <div class="px-5 py-4 border-t border-slate-100">
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-2">Benefit Aktif</p>
                                <div class="space-y-1.5">
                                    {#each membership.level.active_benefits as b}
                                        <div class="flex items-center gap-2">
                                            <i class="ti {b.icon ?? 'ti-check'} text-sm shrink-0" style="color: {membership.level.badge_color};"></i>
                                            <span class="text-xs text-slate-600">{b.label}</span>
                                        </div>
                                    {/each}
                                </div>
                            </div>
                        {/if}
                    </div>
                {:else}
                    <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-5 py-6 text-center">
                        <i class="ti ti-award text-2xl text-slate-300 mb-2 block"></i>
                        <p class="text-sm font-semibold text-slate-600">Belum ada membership</p>
                        <button onclick={() => (showAssignModal = true)}
                            class="mt-3 text-sm font-medium transition-opacity hover:opacity-70" style="color: {primary};">
                            Tetapkan level sekarang →
                        </button>
                    </div>
                {/if}
            </div>

            <!-- Right: histories, vouchers, points -->
            <div class="space-y-5 lg:col-span-2">

                <!-- Level histories -->
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                    <div class="border-b border-slate-100 px-5 py-3.5">
                        <p class="text-sm font-semibold text-slate-800">Riwayat Level</p>
                    </div>
                    {#if histories.length === 0}
                        <div class="flex flex-col items-center justify-center py-10 text-center">
                            <i class="ti ti-history text-2xl text-slate-300 mb-2"></i>
                            <p class="text-sm text-slate-400">Belum ada riwayat perubahan level</p>
                        </div>
                    {:else}
                        <div class="divide-y divide-slate-100">
                            {#each histories as h}
                                <div class="flex items-start gap-3 px-5 py-3">
                                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg mt-0.5"
                                        style="background-color: {h.to_level?.badge_color ?? '#94a3b8'}22; color: {h.to_level?.badge_color ?? '#94a3b8'};">
                                        <i class="ti ti-award text-sm"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            {#if h.from_level}
                                                <span class="text-xs text-slate-400">{h.from_level.name}</span>
                                                <i class="ti ti-arrow-right text-[10px] text-slate-400"></i>
                                            {/if}
                                            <span class="text-xs font-semibold text-slate-800">{h.to_level?.name ?? '—'}</span>
                                            <span class="rounded-md px-1.5 py-0.5 text-[10px] font-semibold {actionColors[h.action] ?? 'bg-slate-100 text-slate-500'}">
                                                {actionLabels[h.action] ?? h.action}
                                            </span>
                                        </div>
                                        {#if h.reason}
                                            <p class="text-xs text-slate-400 mt-0.5">{h.reason}</p>
                                        {/if}
                                        <p class="text-[10px] text-slate-400 mt-0.5">
                                            {h.created_at} &nbsp;·&nbsp;
                                            {h.processed_by ? h.processed_by.name : 'Otomatis'}
                                        </p>
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <p class="text-xs font-semibold text-slate-700">{fmtCurrency(h.total_purchase_at_time)}</p>
                                        <p class="text-[10px] text-slate-400">{h.total_transactions_at_time}x</p>
                                    </div>
                                </div>
                            {/each}
                        </div>
                    {/if}
                </div>

                <!-- Vouchers -->
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                    <div class="border-b border-slate-100 px-5 py-3.5">
                        <p class="text-sm font-semibold text-slate-800">Voucher Membership</p>
                    </div>
                    {#if vouchers.length === 0}
                        <div class="py-8 text-center">
                            <p class="text-sm text-slate-400">Belum ada voucher</p>
                        </div>
                    {:else}
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="border-b border-slate-100 bg-slate-50/50">
                                        <th class="px-5 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Kode</th>
                                        <th class="px-5 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Diskon</th>
                                        <th class="px-5 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Berlaku</th>
                                        <th class="px-5 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    {#each vouchers as v}
                                        <tr class="hover:bg-slate-50/50 transition-colors">
                                            <td class="px-5 py-3">
                                                <p class="font-mono text-xs font-semibold text-slate-800">{v.code}</p>
                                                <p class="text-[10px] text-slate-400">{v.label}</p>
                                            </td>
                                            <td class="px-5 py-3 text-sm font-medium text-slate-700">
                                                {v.discount_type === 'percentage' ? v.discount_value + '%' : fmtCurrency(v.discount_value)}
                                            </td>
                                            <td class="px-5 py-3 text-xs text-slate-500">
                                                {v.valid_from} – {v.valid_until}
                                            </td>
                                            <td class="px-5 py-3">
                                                <span class="rounded-md px-2 py-0.5 text-[11px] font-semibold {voucherStatusColors[v.status] ?? 'bg-slate-100 text-slate-500'}">
                                                    {v.status}
                                                </span>
                                            </td>
                                        </tr>
                                    {/each}
                                </tbody>
                            </table>
                        </div>
                    {/if}
                </div>

                <!-- Points -->
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                    <div class="border-b border-slate-100 px-5 py-3.5">
                        <p class="text-sm font-semibold text-slate-800">Riwayat Poin</p>
                    </div>
                    {#if points.length === 0}
                        <div class="py-8 text-center">
                            <p class="text-sm text-slate-400">Belum ada riwayat poin</p>
                        </div>
                    {:else}
                        <div class="divide-y divide-slate-100">
                            {#each points as pt}
                                <div class="flex items-center justify-between px-5 py-3">
                                    <div>
                                        <p class="text-sm text-slate-700">{pt.description || pt.type}</p>
                                        <p class="text-[10px] text-slate-400">{pt.created_at}</p>
                                    </div>
                                    <span class="text-sm font-bold {pt.type === 'earned' || pt.type === 'bonus' ? 'text-emerald-600' : 'text-rose-500'}">
                                        {pt.type === 'earned' || pt.type === 'bonus' ? '+' : '-'}{fmt(Math.abs(pt.amount))}
                                    </span>
                                </div>
                            {/each}
                        </div>
                    {/if}
                </div>

            </div>
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
