<script lang="ts">
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { page, Link } from '@inertiajs/svelte';

    let {
        stats = {},
        growthData = [],
    } = $props();

    const primary   = $derived(page.props.theme?.primary_color   || '#0c4cb4');
    const secondary = $derived(page.props.theme?.secondary_color || '#fa7315');

    function fmt(n: number): string {
        return new Intl.NumberFormat('id-ID').format(n ?? 0);
    }
    function fmtCurrency(n: number): string {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n ?? 0);
    }
</script>

<svelte:head><title>Dashboard Membership — Admin</title></svelte:head>

<AdminLayout>
    <main class="w-full max-w-[1600px] mx-auto px-4 sm:px-6 py-6 space-y-6">

        <!-- Header -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-semibold tracking-tight text-slate-900">Dashboard Membership</h1>
                <p class="mt-0.5 text-sm text-slate-500">Ringkasan performa program membership toko</p>
            </div>
            <div class="flex items-center gap-2">
                <Link href="/admin/membership/levels" class="inline-flex items-center gap-1.5 h-9 rounded-lg border border-slate-200 px-3 text-sm font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                    <i class="ti ti-award text-sm text-slate-400"></i>
                    Kelola Level
                </Link>
                <Link href="/admin/membership/members" class="inline-flex items-center gap-1.5 h-9 rounded-lg px-3 text-sm font-semibold text-white transition-opacity hover:opacity-90" style="background-color: {primary};">
                    <i class="ti ti-users text-sm"></i>
                    Daftar Member
                </Link>
            </div>
        </div>

        <!-- KPI cards -->
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
            {#each [
                { label: 'Total Member',       value: fmt(stats.total_members),   icon: 'ti-users',          bg: `color-mix(in srgb,${primary} 10%,white)`,   color: primary },
                { label: 'Member Baru Bulan Ini', value: fmt(stats.new_this_month), icon: 'ti-user-plus',   bg: '#f0fdf4', color: '#16a34a' },
                { label: 'Total Cashback',     value: fmtCurrency(stats.total_cashback ?? 0), icon: 'ti-cash',   bg: '#fef3c7', color: '#d97706' },
                { label: 'Total Voucher',      value: fmt(stats.total_vouchers),  icon: 'ti-ticket',         bg: '#ede9fe', color: '#7c3aed' },
                { label: 'Total Poin',         value: fmt(stats.total_points),    icon: 'ti-star',           bg: '#fff7ed', color: '#c2410c' },
            ] as kpi}
                <div class="group overflow-hidden rounded-xl border border-slate-200 bg-white p-5 transition-shadow hover:shadow-md">
                    <div class="flex items-start justify-between">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg text-lg" style="background-color: {kpi.bg}; color: {kpi.color};">
                            <i class="ti {kpi.icon}"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <p class="text-2xl font-bold tracking-tight text-slate-900">{kpi.value}</p>
                        <p class="mt-0.5 text-xs font-medium text-slate-500">{kpi.label}</p>
                    </div>
                </div>
            {/each}
        </div>

        <!-- Distribusi per level -->
        {#if stats.by_level?.length > 0}
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <div class="border-b border-slate-100 px-5 py-3.5">
                    <p class="text-sm font-semibold text-slate-800">Distribusi Member per Level</p>
                    <p class="text-xs text-slate-400 mt-0.5">Jumlah member aktif di setiap level</p>
                </div>
                <div class="p-5 space-y-3">
                    {#each stats.by_level as lvl}
                        {@const pct = stats.total_members > 0 ? Math.round((lvl.count / stats.total_members) * 100) : 0}
                        <div class="flex items-center gap-4">
                            <div class="flex items-center gap-2 w-28 shrink-0">
                                <span class="h-2.5 w-2.5 rounded-full shrink-0" style="background-color: {lvl.color};"></span>
                                <span class="text-sm font-medium text-slate-700 truncate">{lvl.name}</span>
                            </div>
                            <div class="flex-1 h-2 rounded-full bg-slate-100 overflow-hidden">
                                <div class="h-full rounded-full transition-all" style="width: {pct}%; background-color: {lvl.color};"></div>
                            </div>
                            <div class="flex items-center gap-2 w-20 shrink-0 text-right">
                                <span class="text-sm font-semibold text-slate-800 ml-auto">{fmt(lvl.count)}</span>
                                <span class="text-xs text-slate-400">{pct}%</span>
                            </div>
                        </div>
                    {/each}
                </div>
            </div>
        {/if}

        <!-- Quick actions -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            {#each [
                { href: '/admin/membership/levels',   icon: 'ti-award',       title: 'Kelola Level',      desc: 'Buat dan atur level membership',        color: primary },
                { href: '/admin/membership/members',  icon: 'ti-users',       title: 'Daftar Member',     desc: 'Lihat & atur membership pelanggan',    color: '#16a34a' },
                { href: '/admin/membership/histories',icon: 'ti-history',     title: 'Riwayat Perubahan', desc: 'Log semua perubahan level member',      color: '#7c3aed' },
            ] as action}
                <Link
                    href={action.href}
                    class="flex items-start gap-4 rounded-xl border border-slate-200 bg-white p-5 transition-all hover:-translate-y-0.5 hover:shadow-md"
                >
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-white text-lg" style="background-color: {action.color};">
                        <i class="ti {action.icon}"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-800">{action.title}</p>
                        <p class="text-xs text-slate-400 mt-0.5">{action.desc}</p>
                    </div>
                    <i class="ti ti-chevron-right text-slate-300 ml-auto shrink-0 self-center"></i>
                </Link>
            {/each}
        </div>

    </main>
</AdminLayout>
