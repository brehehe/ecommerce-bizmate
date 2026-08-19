<script lang="ts">
    import { adjustColorOpacity } from '@/utils/color';
    import { showToast } from '@/utils/toast';

    import { onMount } from 'svelte';
    import { page, router, Link, Deferred, usePoll } from '@inertiajs/svelte';
    const primaryColor = $derived(page.props.theme?.primary_color || '#0c4cb4');
    const secondaryColor = $derived(
        page.props.theme?.secondary_color || '#fa7315',
    );
    const isSellerEnabled = $derived(
        Boolean(
            (page.props as any).app_config?.is_seller_enabled ??
                (page.props as any).settings?.is_seller_enabled ??
                (page.props as any).isSellerMode ??
                false,
        ),
    );
    import Chart from 'chart.js/auto';
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import Pagination from '@/components/ui/Pagination.svelte';

    let {
        isSeller = false,
        stats,
        visitorStats = {
            onlineVisitors: 0,
            uniqueVisitors: 0,
            uniqueVisitorsChange: { type: 'neutral', value: '0%' },
            pageviewsCount: 0,
            pageviewsChange: { type: 'neutral', value: '0%' },
            devices: { mobile: 0, desktop: 0, tablet: 0, mobileCount: 0, desktopCount: 0 },
        },
        topVisitedPages = [],
        visitorIpLogs = { data: [] },
        ipTrafficAnalytics = {
            total_unique_ips: 0,
            top_ips: [],
            traffic_sources: {
                direct: 0,
                google: 0,
                social: 0,
                external: 0,
                direct_count: 0,
                google_count: 0,
                social_count: 0,
                external_count: 0,
            },
        },
        orderStats = { unpaidCount: 0, pendingCount: 0, newCount: 0, readyCount: 0, shippingCount: 0 },
        recentOrders = [],
        topProducts = [],
        chartData = { labels: [], data: [], refunds: [], returns: [] },
        currentFilter: initialFilter = '7_hari',
        productStockInfo = { data: [] },
        recentStockOut = [],
        recentCustomers = [],
        search: initialSearch = '',
        refundStats = { count: 0, totalAmount: 0, formattedAmount: 'Rp 0', countChange: { type: 'neutral', value: '0%' }, amountChange: { type: 'neutral', value: '0%' } },
        returnStats = { count: 0, totalAmount: 0, formattedAmount: 'Rp 0', countChange: { type: 'neutral', value: '0%' }, amountChange: { type: 'neutral', value: '0%' } },
        refundPipeline = { pending: 0, approved: 0, completed: 0, rejected: 0 },
        returnPipeline = { pending: 0, approved: 0, inTransit: 0, received: 0, refunding: 0, completed: 0, rejected: 0 },
        recentRefunds = [],
        recentReturns = [],
    } = $props();

    // Auto-refresh real-time online visitor counter and IP logs every 15 seconds
    usePoll(15000, { only: ['visitorStats', 'topVisitedPages', 'visitorIpLogs', 'ipTrafficAnalytics'] });

    // svelte-ignore state_referenced_locally
    let selectedFilter = $state(initialFilter);
    // svelte-ignore state_referenced_locally
    let stockSearchInput = $state(initialSearch);
    let chartMetric = $state<'all' | 'revenue' | 'refund' | 'return'>('all');
    let activePipelineTab = $state<'transactions' | 'refunds' | 'returns'>('transactions');

    // Visitor IP Tracking UI State
    let activeVisitorTab = $state<'recent' | 'top_ips' | 'sources'>('recent');

    const filterLabels: Record<string, string> = {
        'hari_ini': 'Hari Ini',
        '7_hari': '7 Hari Terakhir',
        '1_bulan': '30 Hari Terakhir',
        '1_tahun': '1 Tahun Terakhir',
        'tahun_lalu': 'Tahun Lalu',
    };

    let visitorLogsList = $derived(Array.isArray(visitorIpLogs) ? visitorIpLogs : (visitorIpLogs?.data ?? []));
    let selectedVisitorLog = $state<any | null>(null);
    let isVisitorDetailModalOpen = $state(false);
    let copiedIp = $state<string | null>(null);

    function copyIp(ip: string) {
        if (!ip) return;
        navigator.clipboard.writeText(ip);
        copiedIp = ip;
        showToast(`Alamat IP ${ip} berhasil disalin`, 'success');
        setTimeout(() => {
            if (copiedIp === ip) {
                copiedIp = null;
            }
        }, 2000);
    }

    let canvas = $state<HTMLCanvasElement>();
    let chartInstance: Chart | null = null;

    $effect(() => {
        selectedFilter = initialFilter;
    });

    $effect(() => {
        stockSearchInput = initialSearch;
    });

    $effect(() => {
        if (canvas && chartData && chartData.labels) {
            const ctx = canvas.getContext('2d');
            if (!ctx) return;

            if (chartInstance) {
                chartInstance.destroy();
            }

            const pColor = page.props.theme?.primary_color || '#0c4cb4';

            let gradRevenue = ctx.createLinearGradient(0, 0, 0, 300);
            gradRevenue.addColorStop(0, adjustColorOpacity(pColor, '33'));
            gradRevenue.addColorStop(1, adjustColorOpacity(pColor, '00'));

            let gradRefund = ctx.createLinearGradient(0, 0, 0, 300);
            gradRefund.addColorStop(0, 'rgba(245, 158, 11, 0.25)');
            gradRefund.addColorStop(1, 'rgba(245, 158, 11, 0.0)');

            let gradReturn = ctx.createLinearGradient(0, 0, 0, 300);
            gradReturn.addColorStop(0, 'rgba(139, 92, 246, 0.25)');
            gradReturn.addColorStop(1, 'rgba(139, 92, 246, 0.0)');

            const datasets: any[] = [];

            if (chartMetric === 'all' || chartMetric === 'revenue') {
                datasets.push({
                    label: 'Revenue (Rp Juta)',
                    data: [...(chartData.data || [])],
                    borderColor: pColor,
                    backgroundColor: gradRevenue,
                    borderWidth: 3,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: pColor,
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    fill: true,
                    tension: 0.4,
                });
            }

            if (!isSeller && !isSellerEnabled && (chartMetric === 'all' || chartMetric === 'refund')) {
                datasets.push({
                    label: 'Refund (Rp Juta)',
                    data: [...(chartData.refunds || [])],
                    borderColor: '#f59e0b',
                    backgroundColor: gradRefund,
                    borderWidth: 2.5,
                    borderDash: chartMetric === 'all' ? [4, 4] : [],
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#f59e0b',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    fill: true,
                    tension: 0.4,
                });
            }

            if (!isSeller && !isSellerEnabled && (chartMetric === 'all' || chartMetric === 'return')) {
                datasets.push({
                    label: 'Retur (Rp Juta)',
                    data: [...(chartData.returns || [])],
                    borderColor: '#8b5cf6',
                    backgroundColor: gradReturn,
                    borderWidth: 2.5,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#8b5cf6',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    fill: true,
                    tension: 0.4,
                });
            }

            chartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: [...chartData.labels],
                    datasets,
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            align: 'end',
                            labels: {
                                boxWidth: 12,
                                usePointStyle: true,
                                font: {
                                    family: "'Plus Jakarta Sans', sans-serif",
                                    size: 11,
                                },
                            },
                        },
                        tooltip: {
                            backgroundColor: '#0f172a',
                            titleFont: {
                                family: "'Plus Jakarta Sans', sans-serif",
                                size: 13,
                            },
                            bodyFont: {
                                family: "'Plus Jakarta Sans', sans-serif",
                                size: 12,
                                weight: 'bold',
                            },
                            padding: 12,
                            displayColors: true,
                        },
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: '#f1f5f9' },
                            ticks: {
                                font: {
                                    family: "'Plus Jakarta Sans', sans-serif",
                                    size: 11,
                                },
                                color: '#94a3b8',
                                callback: function (val) {
                                    return 'Rp ' + val + 'M';
                                },
                            },
                        },
                        x: {
                            grid: { display: false },
                            ticks: {
                                font: {
                                    family: "'Plus Jakarta Sans', sans-serif",
                                    size: 11,
                                },
                                color: '#94a3b8',
                            },
                        },
                    },
                    interaction: { intersect: false, mode: 'index' },
                },
            });
        }
    });

    onMount(() => {
        return () => {
            if (chartInstance) {
                chartInstance.destroy();
            }
        };
    });

    function handleFilterChange(e: Event) {
        const value = (e.target as HTMLSelectElement).value;
        router.get(
            '/admin/dashboard',
            { filter: value, search: stockSearchInput },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    let searchTimeout: any;
    function handleStockSearch() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            router.get(
                '/admin/dashboard',
                {
                    filter: selectedFilter,
                    search: stockSearchInput,
                },
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                },
            );
        }, 300);
    }

    function dragScroll(node: HTMLElement) {
        let isDown = false;
        let startX = 0;
        let scrollLeft = 0;
        let hasDragged = false;

        function onMouseDown(e: MouseEvent) {
            isDown = true;
            hasDragged = false;
            startX = e.pageX - node.offsetLeft;
            scrollLeft = node.scrollLeft;
            node.style.cursor = 'grabbing';
            node.style.userSelect = 'none';
        }

        function onMouseLeave() {
            isDown = false;
            node.style.cursor = 'grab';
            node.style.removeProperty('user-select');
        }

        function onMouseUp() {
            isDown = false;
            node.style.cursor = 'grab';
            node.style.removeProperty('user-select');
        }

        function onMouseMove(e: MouseEvent) {
            if (!isDown) return;
            e.preventDefault();
            const x = e.pageX - node.offsetLeft;
            const walk = (x - startX) * 1.2;
            if (Math.abs(walk) > 3) hasDragged = true;
            node.scrollLeft = scrollLeft - walk;
        }

        function onClickCapture(e: MouseEvent) {
            if (hasDragged) e.stopPropagation();
        }

        node.style.cursor = 'grab';
        node.addEventListener('mousedown', onMouseDown);
        node.addEventListener('mouseleave', onMouseLeave);
        node.addEventListener('mouseup', onMouseUp);
        node.addEventListener('mousemove', onMouseMove);
        node.addEventListener('click', onClickCapture, true);

        return {
            destroy() {
                node.removeEventListener('mousedown', onMouseDown);
                node.removeEventListener('mouseleave', onMouseLeave);
                node.removeEventListener('mouseup', onMouseUp);
                node.removeEventListener('mousemove', onMouseMove);
                node.removeEventListener('click', onClickCapture, true);
            },
        };
    }
</script>

<svelte:head>
    <title>Dashboard — Admin</title>
</svelte:head>

<AdminLayout>
    <main class="w-full max-w-[1600px] mx-auto px-4 sm:px-6 py-6 space-y-6">

        <!-- Page header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-semibold tracking-tight text-slate-900">Dashboard Admin</h1>
                <p class="mt-0.5 text-sm text-slate-500">Pantau performa transaksi, pengajuan refund, retur barang, dan stok.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <div class="relative">
                    <select
                        value={selectedFilter}
                        onchange={handleFilterChange}
                        class="appearance-none h-9 rounded-lg border border-slate-200 bg-white pl-3 pr-8 text-sm font-medium text-slate-700 shadow-xs focus:outline-none focus:ring-2 focus:ring-slate-900/10 cursor-pointer transition-colors hover:border-slate-300"
                    >
                        <option value="hari_ini">Hari Ini</option>
                        <option value="7_hari">7 Hari</option>
                        <option value="1_bulan">1 Bulan</option>
                        <option value="1_tahun">1 Tahun</option>
                        <option value="tahun_lalu">Tahun Lalu</option>
                    </select>
                    <i class="ti ti-chevron-down absolute right-2.5 top-1/2 -translate-y-1/2 text-xs text-slate-400 pointer-events-none"></i>
                </div>
                <Link
                    href="/admin/transactions"
                    class="inline-flex items-center gap-1.5 h-9 rounded-lg px-3 text-sm font-medium text-white shadow-xs transition-opacity hover:opacity-90"
                    style="background-color: {primaryColor};"
                >
                    <i class="ti ti-receipt text-sm"></i>
                    <span class="hidden sm:inline">Transaksi</span>
                </Link>
                {#if !isSeller && !isSellerEnabled}
                    <Link
                        href="/admin/refunds"
                        class="inline-flex items-center gap-1.5 h-9 rounded-lg border border-amber-200 bg-amber-50 px-3 text-sm font-medium text-amber-700 shadow-xs transition-colors hover:bg-amber-100"
                    >
                        <i class="ti ti-rotate-2 text-sm"></i>
                        <span class="hidden sm:inline">Refund</span>
                    </Link>
                    <Link
                        href="/admin/returns"
                        class="inline-flex items-center gap-1.5 h-9 rounded-lg border border-violet-200 bg-violet-50 px-3 text-sm font-medium text-violet-700 shadow-xs transition-colors hover:bg-violet-100"
                    >
                        <i class="ti ti-replace text-sm"></i>
                        <span class="hidden sm:inline">Retur</span>
                    </Link>
                {/if}
            </div>
        </div>

        <!-- KPI stat cards -->
        <Deferred data={['stats', 'refundStats', 'returnStats', 'visitorStats']}>
            {#snippet fallback()}
                <div class="grid grid-cols-2 gap-3.5 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-7 animate-pulse">
                    {#each Array(7) as _}
                        <div class="rounded-xl border border-slate-200 bg-white p-4 h-24 flex items-center justify-between">
                            <div class="space-y-2 w-full">
                                <div class="h-3 w-16 bg-slate-100 rounded-md"></div>
                                <div class="h-6 w-24 bg-slate-200 rounded-md"></div>
                            </div>
                        </div>
                    {/each}
                </div>
            {/snippet}

            <div class="grid grid-cols-2 gap-3.5 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-7">

                <!-- Revenue -->
                {@render StatCard({
                    icon: 'ti-wallet',
                    iconBg: adjustColorOpacity(primaryColor, '15'),
                    iconColor: primaryColor,
                    label: 'Total Revenue',
                    value: stats.revenueFormatted,
                    change: stats.revenueChange,
                })}

                <!-- Orders -->
                {@render StatCard({
                    icon: 'ti-shopping-cart',
                    iconBg: '#f0fdf4',
                    iconColor: '#16a34a',
                    label: 'Total Transaksi',
                    value: stats.ordersCount,
                    change: stats.ordersChange,
                })}

                <!-- Live Online Visitors -->
                <div class="group relative overflow-hidden rounded-xl border border-emerald-200/80 bg-gradient-to-br from-emerald-50/50 via-white to-white p-4 shadow-xs transition-all hover:shadow-md hover:border-emerald-300">
                    <div class="flex items-start justify-between">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700 text-base shadow-xs">
                            <i class="ti ti-broadcast"></i>
                        </div>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100/90 px-2 py-0.5 text-[10px] font-bold text-emerald-800 tracking-wide">
                            <span class="relative flex h-2 w-2">
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                            </span>
                            LIVE
                        </span>
                    </div>
                    <div class="mt-3">
                        <p class="text-xl font-bold tracking-tight text-slate-900 truncate">
                            {visitorStats.onlineVisitors} <span class="text-xs font-semibold text-emerald-600">Online</span>
                        </p>
                        <p class="mt-0.5 text-[11px] font-medium text-slate-500 truncate">Pengunjung Aktif (5 mnt)</p>
                    </div>
                </div>

                <!-- Unique Visitors -->
                {@render StatCard({
                    icon: 'ti-users-group',
                    iconBg: '#f0f9ff',
                    iconColor: '#0284c7',
                    label: 'Pengunjung Unik',
                    value: visitorStats.uniqueVisitors,
                    change: visitorStats.uniqueVisitorsChange,
                })}

                <!-- Total Pageviews -->
                {@render StatCard({
                    icon: 'ti-eye',
                    iconBg: '#fdf4ff',
                    iconColor: '#a855f7',
                    label: 'Total Kunjungan',
                    value: visitorStats.pageviewsCount,
                    change: visitorStats.pageviewsChange,
                })}

                {#if !isSeller && !isSellerEnabled}
                    <!-- Refund -->
                    {@render StatCard({
                        icon: 'ti-rotate-2',
                        iconBg: '#fffbeb',
                        iconColor: '#d97706',
                        label: `Refund (${refundStats.count})`,
                        value: refundStats.formattedAmount,
                        change: refundStats.amountChange,
                    })}

                    <!-- Retur -->
                    {@render StatCard({
                        icon: 'ti-replace',
                        iconBg: '#f5f3ff',
                        iconColor: '#7c3aed',
                        label: `Retur (${returnStats.count})`,
                        value: returnStats.formattedAmount,
                        change: returnStats.countChange,
                    })}
                {/if}

                <!-- Products -->
                {@render StatCard({
                    icon: 'ti-box',
                    iconBg: '#eef2ff',
                    iconColor: '#4f46e5',
                    label: 'Produk Aktif',
                    value: stats.activeProductsCount,
                    change: stats.productsChange,
                })}

                <!-- Customers -->
                {@render StatCard({
                    icon: 'ti-users',
                    iconBg: '#fff7ed',
                    iconColor: '#c2410c',
                    label: 'Pelanggan',
                    value: stats.customersCount,
                    change: stats.customersChange,
                })}
            </div>
        </Deferred>

        <!-- Multi-Pipeline Section (Tabbed & Interactive Operational Status) -->
        <Deferred data={['orderStats', 'refundPipeline', 'returnPipeline']}>
            {#snippet fallback()}
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white p-6 h-28 animate-pulse">
                    <div class="h-4 bg-slate-200 rounded w-1/4 mb-3"></div>
                    <div class="h-8 bg-slate-100 rounded w-3/4"></div>
                </div>
            {/snippet}

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-800">Pipeline Operasional Real-Time</p>
                        <p class="text-xs text-slate-400 mt-0.5">Status alur proses transaksi, klaim refund, dan retur barang</p>
                    </div>
                    <div class="flex items-center gap-1 rounded-lg bg-slate-100 p-1 text-xs font-medium">
                        <button
                            type="button"
                            onclick={() => (activePipelineTab = 'transactions')}
                            class="rounded-md px-3 py-1.5 transition-colors {activePipelineTab === 'transactions' ? 'bg-white font-semibold text-slate-800 shadow-xs' : 'text-slate-500 hover:text-slate-800'}"
                        >
                            <i class="ti ti-receipt text-xs mr-1"></i> Transaksi
                        </button>
                        {#if !isSeller && !isSellerEnabled}
                            <button
                                type="button"
                                onclick={() => (activePipelineTab = 'refunds')}
                                class="rounded-md px-3 py-1.5 transition-colors {activePipelineTab === 'refunds' ? 'bg-white font-semibold text-slate-800 shadow-xs' : 'text-slate-500 hover:text-slate-800'}"
                            >
                                <i class="ti ti-rotate-2 text-xs mr-1"></i> Refund ({refundPipeline.pending + refundPipeline.approved})
                            </button>
                            <button
                                type="button"
                                onclick={() => (activePipelineTab = 'returns')}
                                class="rounded-md px-3 py-1.5 transition-colors {activePipelineTab === 'returns' ? 'bg-white font-semibold text-slate-800 shadow-xs' : 'text-slate-500 hover:text-slate-800'}"
                            >
                                <i class="ti ti-replace text-xs mr-1"></i> Retur ({returnPipeline.pending + returnPipeline.approved + returnPipeline.inTransit + returnPipeline.received + returnPipeline.refunding})
                            </button>
                        {/if}
                    </div>
                </div>

                <!-- Tab 1: Pipeline Transaksi -->
                {#if activePipelineTab === 'transactions'}
                    <div class="grid grid-cols-2 divide-x divide-y divide-slate-100 sm:grid-cols-4 sm:divide-y-0">
                        <Link href="/admin/transactions?status=belum_bayar" class="flex items-center gap-3 p-4 transition-colors hover:bg-slate-50">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600 text-lg">
                                <i class="ti ti-clock"></i>
                            </div>
                            <div>
                                <p class="text-xl font-bold text-slate-800 leading-none">{orderStats.unpaidCount ?? orderStats.newCount ?? 0}</p>
                                <p class="mt-1 text-xs text-slate-500">Belum Bayar</p>
                            </div>
                        </Link>
                        <Link href="/admin/transactions?status=menunggu" class="flex items-center gap-3 p-4 transition-colors hover:bg-slate-50">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600 text-lg">
                                <i class="ti ti-loader"></i>
                            </div>
                            <div>
                                <p class="text-xl font-bold text-slate-800 leading-none">{orderStats.pendingCount ?? 0}</p>
                                <p class="mt-1 text-xs text-slate-500">Menunggu Konfirmasi</p>
                            </div>
                        </Link>
                        <Link href="/admin/transactions?status=diproses" class="flex items-center gap-3 p-4 transition-colors hover:bg-slate-50">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-lg" style="background-color: {primaryColor}15; color: {primaryColor};">
                                <i class="ti ti-package"></i>
                            </div>
                            <div>
                                <p class="text-xl font-bold text-slate-800 leading-none">{orderStats.readyCount ?? 0}</p>
                                <p class="mt-1 text-xs text-slate-500">Diproses</p>
                            </div>
                        </Link>
                        <Link href="/admin/transactions?status=dikirim" class="flex items-center gap-3 p-4 transition-colors hover:bg-slate-50">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 text-lg">
                                <i class="ti ti-truck-delivery"></i>
                            </div>
                            <div>
                                <p class="text-xl font-bold text-slate-800 leading-none">{orderStats.shippingCount ?? 0}</p>
                                <p class="mt-1 text-xs text-slate-500">Dikirim</p>
                            </div>
                        </Link>
                    </div>
                {/if}

                <!-- Tab 2: Pipeline Refund -->
                {#if activePipelineTab === 'refunds'}
                    <div class="grid grid-cols-2 divide-x divide-y divide-slate-100 sm:grid-cols-4 sm:divide-y-0">
                        <Link href="/admin/refunds?status=menunggu_konfirmasi" class="flex items-center gap-3 p-4 transition-colors hover:bg-slate-50">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600 text-lg">
                                <i class="ti ti-hourglass-low"></i>
                            </div>
                            <div>
                                <p class="text-xl font-bold text-slate-800 leading-none">{refundPipeline.pending}</p>
                                <p class="mt-1 text-xs text-slate-500">Menunggu Konfirmasi</p>
                            </div>
                        </Link>
                        <Link href="/admin/refunds?status=disetujui" class="flex items-center gap-3 p-4 transition-colors hover:bg-slate-50">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600 text-lg">
                                <i class="ti ti-circle-check"></i>
                            </div>
                            <div>
                                <p class="text-xl font-bold text-slate-800 leading-none">{refundPipeline.approved}</p>
                                <p class="mt-1 text-xs text-slate-500">Disetujui</p>
                            </div>
                        </Link>
                        <Link href="/admin/refunds?status=selesai" class="flex items-center gap-3 p-4 transition-colors hover:bg-slate-50">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 text-lg">
                                <i class="ti ti-circle-check-filled"></i>
                            </div>
                            <div>
                                <p class="text-xl font-bold text-slate-800 leading-none">{refundPipeline.completed}</p>
                                <p class="mt-1 text-xs text-slate-500">Refund Selesai</p>
                            </div>
                        </Link>
                        <Link href="/admin/refunds?status=ditolak" class="flex items-center gap-3 p-4 transition-colors hover:bg-slate-50">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-rose-50 text-rose-600 text-lg">
                                <i class="ti ti-circle-x"></i>
                            </div>
                            <div>
                                <p class="text-xl font-bold text-slate-800 leading-none">{refundPipeline.rejected}</p>
                                <p class="mt-1 text-xs text-slate-500">Ditolak</p>
                            </div>
                        </Link>
                    </div>
                {/if}

                <!-- Tab 3: Pipeline Retur -->
                {#if activePipelineTab === 'returns'}
                    <div class="grid grid-cols-2 divide-x divide-y divide-slate-100 sm:grid-cols-6 sm:divide-y-0 text-xs">
                        <Link href="/admin/returns?status=menunggu_review" class="flex flex-col gap-1 p-3 transition-colors hover:bg-slate-50">
                            <div class="flex items-center justify-between">
                                <span class="text-slate-500">Menunggu Review</span>
                                <i class="ti ti-eye text-amber-500"></i>
                            </div>
                            <p class="text-lg font-bold text-slate-800">{returnPipeline.pending}</p>
                        </Link>
                        <Link href="/admin/returns?status=disetujui" class="flex flex-col gap-1 p-3 transition-colors hover:bg-slate-50">
                            <div class="flex items-center justify-between">
                                <span class="text-slate-500">Disetujui</span>
                                <i class="ti ti-check text-blue-500"></i>
                            </div>
                            <p class="text-lg font-bold text-slate-800">{returnPipeline.approved}</p>
                        </Link>
                        <Link href="/admin/returns?status=barang_dikirim_customer" class="flex flex-col gap-1 p-3 transition-colors hover:bg-slate-50">
                            <div class="flex items-center justify-between">
                                <span class="text-slate-500">Dikirim Cust</span>
                                <i class="ti ti-truck text-indigo-500"></i>
                            </div>
                            <p class="text-lg font-bold text-slate-800">{returnPipeline.inTransit}</p>
                        </Link>
                        <Link href="/admin/returns?status=barang_diterima_toko" class="flex flex-col gap-1 p-3 transition-colors hover:bg-slate-50">
                            <div class="flex items-center justify-between">
                                <span class="text-slate-500">Diterima Toko</span>
                                <i class="ti ti-building-store text-violet-500"></i>
                            </div>
                            <p class="text-lg font-bold text-slate-800">{returnPipeline.received}</p>
                        </Link>
                        <Link href="/admin/returns?status=refund_diproses" class="flex flex-col gap-1 p-3 transition-colors hover:bg-slate-50">
                            <div class="flex items-center justify-between">
                                <span class="text-slate-500">Refund Diproses</span>
                                <i class="ti ti-rotate-2 text-amber-600"></i>
                            </div>
                            <p class="text-lg font-bold text-slate-800">{returnPipeline.refunding}</p>
                        </Link>
                        <Link href="/admin/returns?status=selesai" class="flex flex-col gap-1 p-3 transition-colors hover:bg-slate-50">
                            <div class="flex items-center justify-between">
                                <span class="text-slate-500">Selesai</span>
                                <i class="ti ti-circle-check-filled text-emerald-600"></i>
                            </div>
                            <p class="text-lg font-bold text-slate-800">{returnPipeline.completed}</p>
                        </Link>
                    </div>
                {/if}
            </div>
        </Deferred>

        <!-- Interactive Chart Analytics + Top Products -->
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">

            <!-- Revenue, Refund, & Retur Chart -->
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white lg:col-span-2">
                <Deferred data="chartData">
                    {#snippet fallback()}
                        <div class="p-6 h-[350px] animate-pulse flex flex-col justify-between">
                            <div class="h-4 bg-slate-200 rounded w-1/3"></div>
                            <div class="h-56 bg-slate-100 rounded-lg"></div>
                        </div>
                    {/snippet}

                    <div class="flex flex-col gap-2 border-b border-slate-100 px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm font-semibold text-slate-800">
                                {isSeller || isSellerEnabled ? 'Tren Penjualan' : 'Tren Finansial & Pengembalian'}
                            </p>
                            <p class="text-xs text-slate-400 mt-0.5">
                                {isSeller || isSellerEnabled ? 'Tren Penjualan (6 Bulan)' : 'Perbandingan Penjualan vs Refund & Retur (6 Bulan)'}
                            </p>
                        </div>
                        <div class="flex items-center gap-1 rounded-lg bg-slate-100 p-1 text-xs font-medium">
                            <button
                                type="button"
                                onclick={() => (chartMetric = 'all')}
                                class="rounded-md px-2.5 py-1 transition-colors {chartMetric === 'all' ? 'bg-white font-semibold text-slate-800 shadow-xs' : 'text-slate-500 hover:text-slate-800'}"
                            >
                                Semua
                            </button>
                            <button
                                type="button"
                                onclick={() => (chartMetric = 'revenue')}
                                class="rounded-md px-2.5 py-1 transition-colors {chartMetric === 'revenue' ? 'bg-white font-semibold text-slate-800 shadow-xs' : 'text-slate-500 hover:text-slate-800'}"
                            >
                                Revenue
                            </button>
                            {#if !isSeller && !isSellerEnabled}
                                <button
                                    type="button"
                                    onclick={() => (chartMetric = 'refund')}
                                    class="rounded-md px-2.5 py-1 transition-colors {chartMetric === 'refund' ? 'bg-white font-semibold text-slate-800 shadow-xs' : 'text-slate-500 hover:text-slate-800'}"
                                >
                                    Refund
                                </button>
                                <button
                                    type="button"
                                    onclick={() => (chartMetric = 'return')}
                                    class="rounded-md px-2.5 py-1 transition-colors {chartMetric === 'return' ? 'bg-white font-semibold text-slate-800 shadow-xs' : 'text-slate-500 hover:text-slate-800'}"
                                >
                                    Retur
                                </button>
                            {/if}
                        </div>
                    </div>
                    <div class="p-5">
                        <div class="h-64">
                            <canvas bind:this={canvas}></canvas>
                        </div>
                    </div>
                </Deferred>
            </div>

            <!-- Top products -->
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <Deferred data="topProducts">
                    {#snippet fallback()}
                        <div class="p-6 h-[350px] animate-pulse space-y-4">
                            <div class="h-4 bg-slate-200 rounded w-1/2"></div>
                            {#each Array(3) as _}
                                <div class="flex items-center gap-3">
                                    <div class="h-8 w-8 bg-slate-200 rounded-lg"></div>
                                    <div class="h-4 bg-slate-100 rounded w-2/3"></div>
                                </div>
                            {/each}
                        </div>
                    {/snippet}

                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3.5">
                        <div>
                            <p class="text-sm font-semibold text-slate-800">Top Produk</p>
                            <p class="text-xs text-slate-400 mt-0.5">Volume penjualan tertinggi</p>
                        </div>
                        <Link href="/admin/products" class="text-xs font-medium" style="color: {primaryColor};">
                            Semua →
                        </Link>
                    </div>
                    <div class="divide-y divide-slate-100">
                        {#each topProducts as product, i}
                            <div class="flex items-center gap-3 px-5 py-3">
                                <span class="w-5 shrink-0 text-center text-xs font-semibold text-slate-400">{i + 1}</span>
                                <img src={product.image} alt={product.name} class="h-9 w-9 shrink-0 rounded-lg object-cover bg-slate-100" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-slate-800">{product.name}</p>
                                    <p class="text-xs text-slate-400">{product.category}</p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <p class="text-sm font-bold text-slate-800">{product.sales}</p>
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-emerald-500">sold</p>
                                </div>
                            </div>
                        {:else}
                            <div class="flex flex-col items-center justify-center py-10 text-center px-4">
                                <i class="ti ti-chart-bar-off text-2xl text-slate-300 mb-2"></i>
                                <p class="text-xs text-slate-400">Belum ada data produk</p>
                            </div>
                        {/each}
                    </div>
                </Deferred>
            </div>
        </div>

        <!-- Traffic Analytics & Top Visited Pages -->
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <!-- Top Visited Pages (2 cols) -->
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white lg:col-span-2">
                <Deferred data="topVisitedPages">
                    {#snippet fallback()}
                        <div class="p-6 h-[260px] animate-pulse space-y-3">
                            <div class="h-4 bg-slate-200 rounded w-1/4"></div>
                            <div class="h-10 bg-slate-100 rounded w-full"></div>
                            <div class="h-10 bg-slate-100 rounded w-full"></div>
                        </div>
                    {/snippet}

                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3.5">
                        <div>
                            <p class="text-sm font-semibold text-slate-800">Halaman & Toko Paling Sering Dikunjungi</p>
                            <p class="text-xs text-slate-400 mt-0.5">Aktivitas trafik pengunjung pada periode ini</p>
                        </div>
                        <span class="inline-flex items-center gap-1 text-xs text-slate-500 font-medium bg-slate-50 px-2.5 py-1 rounded-lg border border-slate-100">
                            <i class="ti ti-chart-bar text-slate-400"></i>
                            {visitorStats.pageviewsCount} Total Views
                        </span>
                    </div>
                    <div class="p-5">
                        {#if topVisitedPages && topVisitedPages.length > 0}
                            <div class="space-y-3.5">
                                {#each topVisitedPages as item}
                                    <div class="space-y-1">
                                        <div class="flex items-center justify-between text-xs">
                                            <div class="flex items-center gap-2 truncate max-w-[70%]">
                                                <span class="font-semibold text-slate-800 truncate">{item.title}</span>
                                                <span class="text-slate-400 font-mono text-[10px] truncate hidden sm:inline">{item.path}</span>
                                            </div>
                                            <div class="flex items-center gap-2 shrink-0 text-slate-600 font-medium">
                                                <span><strong class="text-slate-900">{item.views}</strong> views</span>
                                                <span class="text-slate-400 text-[11px]">({item.unique_views} unik)</span>
                                            </div>
                                        </div>
                                        <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                                            <div
                                                class="h-full rounded-full transition-all duration-500"
                                                style="width: {item.percentage}%; background-color: {primaryColor};"
                                            ></div>
                                        </div>
                                    </div>
                                {/each}
                            </div>
                        {:else}
                            <div class="py-8 text-center text-xs text-slate-400">
                                <i class="ti ti-eye-off text-2xl mb-1 text-slate-300 block"></i>
                                Belum ada data kunjungan pada periode ini.
                            </div>
                        {/if}
                    </div>
                </Deferred>
            </div>

            <!-- Device Distribution (1 col) -->
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3.5">
                    <div>
                        <p class="text-sm font-semibold text-slate-800">Distribusi Perangkat</p>
                        <p class="text-xs text-slate-400 mt-0.5">Perangkat yang digunakan pengunjung</p>
                    </div>
                    <i class="ti ti-devices text-slate-400"></i>
                </div>
                <div class="p-5 flex flex-col justify-between h-[calc(100%-53px)] space-y-4">
                    <div class="space-y-4">
                        <!-- Mobile -->
                        <div>
                            <div class="flex items-center justify-between text-xs mb-1.5">
                                <span class="flex items-center gap-1.5 text-slate-700 font-medium">
                                    <i class="ti ti-device-mobile text-slate-500"></i> Mobile Smartphone
                                </span>
                                <span class="font-bold text-slate-900">{visitorStats.devices?.mobile ?? 0}%</span>
                            </div>
                            <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-blue-500" style="width: {visitorStats.devices?.mobile ?? 0}%;"></div>
                            </div>
                        </div>

                        <!-- Desktop -->
                        <div>
                            <div class="flex items-center justify-between text-xs mb-1.5">
                                <span class="flex items-center gap-1.5 text-slate-700 font-medium">
                                    <i class="ti ti-device-laptop text-slate-500"></i> Komputer / Laptop
                                </span>
                                <span class="font-bold text-slate-900">{visitorStats.devices?.desktop ?? 0}%</span>
                            </div>
                            <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-indigo-500" style="width: {visitorStats.devices?.desktop ?? 0}%;"></div>
                            </div>
                        </div>

                        <!-- Tablet -->
                        <div>
                            <div class="flex items-center justify-between text-xs mb-1.5">
                                <span class="flex items-center gap-1.5 text-slate-700 font-medium">
                                    <i class="ti ti-device-tablet text-slate-500"></i> Tablet
                                </span>
                                <span class="font-bold text-slate-900">{visitorStats.devices?.tablet ?? 0}%</span>
                            </div>
                            <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-amber-500" style="width: {visitorStats.devices?.tablet ?? 0}%;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Live heartbeat footer -->
                    <div class="rounded-lg bg-emerald-50/70 border border-emerald-100 p-3 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="relative flex h-2.5 w-2.5">
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                            </span>
                            <span class="text-xs font-semibold text-emerald-900">Live Traffic Polling</span>
                        </div>
                        <span class="text-[11px] text-emerald-700 font-medium">Update tiap 15s</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── REAL-TIME VISITOR IP LOGS & TRAFFIC AUDIT (Super Admin & Admin Only) ── -->
        {#if !isSeller && visitorIpLogs}
            <div class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-xs">
                <!-- Header -->
                <div class="border-b border-slate-100 bg-slate-50/50 p-4 sm:p-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-bold text-slate-800 flex items-center gap-2">
                                <i class="ti ti-map-pin-2 text-brand-blueRoyal text-lg"></i>
                                Log Aktivitas Pengunjung & IP Terkini
                            </h3>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-100 text-purple-800 border border-purple-200">
                                <i class="ti ti-shield-lock text-xs"></i> Khusus Admin
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5">Pantau alamat IP pengunjung, halaman yang diakses, perangkat, dan asal trafik secara real-time</p>
                    </div>

                    <!-- Right Controls / Stats summary -->
                    <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap">
                        <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white border border-slate-200/80 shadow-2xs text-xs font-semibold text-slate-700">
                            <i class="ti ti-fingerprint text-brand-blueRoyal text-sm"></i>
                            <span>IP Unik: <strong class="text-slate-900">{ipTrafficAnalytics?.total_unique_ips ?? 0}</strong></span>
                        </div>

                        <!-- Tab Switcher -->
                        <div class="flex items-center bg-slate-100 p-0.5 rounded-xl border border-slate-200/70">
                            <button
                                onclick={() => (activeVisitorTab = 'recent')}
                                class="px-3 py-1 rounded-lg text-xs font-semibold transition cursor-pointer flex items-center gap-1.5 {activeVisitorTab === 'recent'
                                    ? 'bg-white text-slate-900 shadow-2xs'
                                    : 'text-slate-500 hover:text-slate-800'}"
                            >
                                <i class="ti ti-activity text-sm"></i>
                                <span>Aktivitas Real-time</span>
                            </button>
                            <button
                                onclick={() => (activeVisitorTab = 'top_ips')}
                                class="px-3 py-1 rounded-lg text-xs font-semibold transition cursor-pointer flex items-center gap-1.5 {activeVisitorTab === 'top_ips'
                                    ? 'bg-white text-slate-900 shadow-2xs'
                                    : 'text-slate-500 hover:text-slate-800'}"
                            >
                                <i class="ti ti-trophy text-sm"></i>
                                <span>Top IP</span>
                            </button>
                            <button
                                onclick={() => (activeVisitorTab = 'sources')}
                                class="px-3 py-1 rounded-lg text-xs font-semibold transition cursor-pointer flex items-center gap-1.5 {activeVisitorTab === 'sources'
                                    ? 'bg-white text-slate-900 shadow-2xs'
                                    : 'text-slate-500 hover:text-slate-800'}"
                            >
                                <i class="ti ti-world-share text-sm"></i>
                                <span>Sumber Trafik</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tab 1: Real-time Visitor Stream -->
                {#if activeVisitorTab === 'recent'}
                    <div class="overflow-x-auto custom-scrollbar">
                        {#if !visitorLogsList || visitorLogsList.length === 0}
                            <div class="py-12 text-center text-xs text-slate-400">
                                <i class="ti ti-radar text-3xl mb-1 text-slate-300 block"></i>
                                Belum ada riwayat aktivitas pengunjung pada periode {filterLabels[selectedFilter] || selectedFilter}.
                            </div>
                        {:else}
                            <table class="w-full text-left text-xs border-collapse min-w-[760px]">
                                <thead>
                                    <tr class="border-b border-slate-150 bg-slate-50/60 text-[10.5px] font-bold uppercase tracking-wider text-slate-500">
                                        <th class="py-3 px-4">Pengunjung / IP Address</th>
                                        <th class="py-3 px-4">Akun / Identitas</th>
                                        <th class="py-3 px-4">Halaman Diakses</th>
                                        <th class="py-3 px-4">Perangkat & Browser</th>
                                        <th class="py-3 px-4">Sumber (Referer)</th>
                                        <th class="py-3 px-4">Waktu</th>
                                        <th class="py-3 px-4 text-center">Detail</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-slate-700">
                                    {#each visitorLogsList as log (log.id)}
                                        <tr class="hover:bg-slate-50/70 transition duration-150">
                                            <!-- IP Address & Online Badge -->
                                            <td class="py-3 px-4">
                                                <div class="flex items-center gap-2">
                                                    <span class="relative flex h-2 w-2 shrink-0">
                                                        {#if log.is_online}
                                                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                                                            <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                                                        {:else}
                                                            <span class="relative inline-flex h-2 w-2 rounded-full bg-slate-300"></span>
                                                        {/if}
                                                    </span>
                                                    <span class="font-mono font-bold text-slate-900 bg-slate-100/80 px-2 py-0.5 rounded-md border border-slate-200/60 select-all text-[11px]">
                                                        {log.ip_address}
                                                    </span>
                                                    <button
                                                        type="button"
                                                        onclick={() => copyIp(log.ip_address)}
                                                        class="p-1 text-slate-400 hover:text-slate-700 rounded transition cursor-pointer"
                                                        title="Salin IP Address"
                                                    >
                                                        {#if copiedIp === log.ip_address}
                                                            <i class="ti ti-check text-emerald-600"></i>
                                                        {:else}
                                                            <i class="ti ti-copy text-xs"></i>
                                                        {/if}
                                                    </button>
                                                </div>
                                            </td>

                                            <!-- User Name or Guest -->
                                            <td class="py-3 px-4">
                                                {#if log.user_name}
                                                    <div class="flex items-center gap-2">
                                                        <div class="w-6 h-6 rounded-full bg-brand-blueRoyal/10 text-brand-blueRoyal flex items-center justify-center font-bold text-[10px] shrink-0">
                                                            {log.user_name.charAt(0).toUpperCase()}
                                                        </div>
                                                        <div class="min-w-0">
                                                            <p class="font-bold text-slate-800 truncate max-w-[130px]" title={log.user_name}>{log.user_name}</p>
                                                            <p class="text-[10px] text-slate-400 truncate max-w-[130px]">{log.user_email}</p>
                                                        </div>
                                                    </div>
                                                {:else}
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-100 text-slate-600 border border-slate-200/50">
                                                        <i class="ti ti-user-circle text-slate-400"></i> Tamu / Guest
                                                    </span>
                                                {/if}
                                            </td>

                                            <!-- Page / Path -->
                                            <td class="py-3 px-4">
                                                <div class="min-w-0 max-w-[200px]">
                                                    <a
                                                        href={log.path}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        class="font-semibold text-slate-800 hover:text-brand-blueRoyal transition truncate block"
                                                        title={log.title}
                                                    >
                                                        {log.title}
                                                    </a>
                                                    <span class="font-mono text-[10px] text-slate-400 truncate block">{log.path}</span>
                                                </div>
                                            </td>

                                            <!-- Device, Browser & OS -->
                                            <td class="py-3 px-4">
                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-700 border border-slate-200/60">
                                                        {#if log.device === 'mobile'}
                                                            <i class="ti ti-device-mobile text-blue-500"></i>
                                                        {:else if log.device === 'tablet'}
                                                            <i class="ti ti-device-tablet text-amber-500"></i>
                                                        {:else}
                                                            <i class="ti ti-device-laptop text-indigo-500"></i>
                                                        {/if}
                                                        <span>{log.device}</span>
                                                    </span>
                                                    <span class="text-[10.5px] text-slate-600 font-medium truncate max-w-[110px]" title="{log.browser} • {log.os}">
                                                        {log.browser} ({log.os})
                                                    </span>
                                                </div>
                                            </td>

                                            <!-- Referer -->
                                            <td class="py-3 px-4">
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10.5px] font-medium bg-slate-100/80 text-slate-700 border border-slate-200/60 truncate max-w-[140px]" title={log.referer || 'Direct / Langsung'}>
                                                    <i class="ti {log.referer_source?.icon ?? 'ti-world'} text-slate-500"></i>
                                                    <span class="truncate">{log.referer_source?.label ?? 'Langsung'}</span>
                                                </span>
                                            </td>

                                            <!-- Timestamp -->
                                            <td class="py-3 px-4 whitespace-nowrap">
                                                <p class="font-medium text-slate-800">{log.time_ago}</p>
                                                <p class="text-[10px] text-slate-400">{log.formatted_time}</p>
                                            </td>

                                            <!-- Detail Button -->
                                            <td class="py-3 px-4 text-center">
                                                <button
                                                    type="button"
                                                    onclick={() => { selectedVisitorLog = log; isVisitorDetailModalOpen = true; }}
                                                    class="p-1.5 rounded-lg text-slate-400 hover:text-brand-blueRoyal hover:bg-brand-blueRoyal/5 transition cursor-pointer"
                                                    title="Lihat Detail Teknis"
                                                >
                                                    <i class="ti ti-info-circle text-base"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    {/each}
                                </tbody>
                            </table>

                            {#if visitorIpLogs && visitorIpLogs.links && visitorIpLogs.links.length > 3}
                                <div class="p-4 border-t border-slate-100 bg-slate-50/50 flex flex-col items-center gap-2.5 text-center">
                                    <Pagination
                                        data={visitorIpLogs}
                                        itemLabel="Aktivitas Pengunjung"
                                        class="flex flex-col items-center gap-2.5 text-center"
                                    />
                                </div>
                            {/if}
                        {/if}
                    </div>
                {/if}

                <!-- Tab 2: Top Active IP Addresses -->
                {#if activeVisitorTab === 'top_ips'}
                    <div class="p-5">
                        {#if !ipTrafficAnalytics?.top_ips || ipTrafficAnalytics.top_ips.length === 0}
                            <div class="py-8 text-center text-xs text-slate-400">
                                <i class="ti ti-shield-search text-3xl mb-1 text-slate-300 block"></i>
                                Belum ada data IP teratas pada periode ini.
                            </div>
                        {:else}
                            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3.5">
                                {#each ipTrafficAnalytics.top_ips as ipItem, index}
                                    <div class="rounded-xl border border-slate-200 bg-slate-50/40 p-3.5 hover:bg-white hover:shadow-xs transition duration-200 flex flex-col justify-between space-y-3">
                                        <div class="flex items-start justify-between">
                                            <div class="flex items-center gap-2">
                                                <span class="w-5 h-5 rounded-md bg-slate-200/80 text-slate-700 font-bold text-[10px] flex items-center justify-center">
                                                    #{index + 1}
                                                </span>
                                                <span class="font-mono font-bold text-slate-900 text-xs select-all">
                                                    {ipItem.ip_address}
                                                </span>
                                            </div>
                                            {#if ipItem.is_online}
                                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-bold bg-emerald-100 text-emerald-800">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Online
                                                </span>
                                            {/if}
                                        </div>

                                        <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-150 text-xs">
                                            <div>
                                                <p class="text-[10px] text-slate-400 font-semibold uppercase">Total Hit</p>
                                                <p class="font-extrabold text-slate-900 text-sm mt-0.5">{ipItem.total_requests} <span class="text-[10px] font-normal text-slate-500">req</span></p>
                                            </div>
                                            <div>
                                                <p class="text-[10px] text-slate-400 font-semibold uppercase">Halaman Unik</p>
                                                <p class="font-extrabold text-brand-blueRoyal text-sm mt-0.5">{ipItem.unique_paths} <span class="text-[10px] font-normal text-slate-500">page</span></p>
                                            </div>
                                        </div>

                                        <div class="flex items-center justify-between text-[10.5px] text-slate-500 pt-1">
                                            <span class="flex items-center gap-1">
                                                <i class="ti {ipItem.device === 'mobile' ? 'ti-device-mobile text-blue-500' : 'ti-device-laptop text-indigo-500'}"></i>
                                                {ipItem.device}
                                            </span>
                                            <span class="font-medium text-slate-600">{ipItem.last_seen_ago}</span>
                                        </div>
                                    </div>
                                {/each}
                            </div>
                        {/if}
                    </div>
                {/if}

                <!-- Tab 3: Sources & Referers -->
                {#if activeVisitorTab === 'sources'}
                    <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-5">
                        <!-- Referrer Source Breakdown -->
                        <div class="rounded-xl border border-slate-200 p-4 space-y-3 bg-white">
                            <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                                <i class="ti ti-world text-brand-blueRoyal"></i>
                                Komposisi Sumber Pengunjung (Traffic Source)
                            </h4>
                            <div class="space-y-3 pt-1">
                                <!-- Direct -->
                                <div>
                                    <div class="flex items-center justify-between text-xs mb-1">
                                        <span class="flex items-center gap-1.5 text-slate-700 font-medium">
                                            <i class="ti ti-link text-slate-500"></i> Langsung / Direct URL
                                        </span>
                                        <span class="font-bold text-slate-900">{ipTrafficAnalytics?.traffic_sources?.direct ?? 0}% ({ipTrafficAnalytics?.traffic_sources?.direct_count ?? 0})</span>
                                    </div>
                                    <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                                        <div class="h-full rounded-full bg-blue-500" style="width: {ipTrafficAnalytics?.traffic_sources?.direct ?? 0}%;"></div>
                                    </div>
                                </div>
                                <!-- Google Search -->
                                <div>
                                    <div class="flex items-center justify-between text-xs mb-1">
                                        <span class="flex items-center gap-1.5 text-slate-700 font-medium">
                                            <i class="ti ti-brand-google text-red-500"></i> Google Search
                                        </span>
                                        <span class="font-bold text-slate-900">{ipTrafficAnalytics?.traffic_sources?.google ?? 0}% ({ipTrafficAnalytics?.traffic_sources?.google_count ?? 0})</span>
                                    </div>
                                    <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                                        <div class="h-full rounded-full bg-emerald-500" style="width: {ipTrafficAnalytics?.traffic_sources?.google ?? 0}%;"></div>
                                    </div>
                                </div>
                                <!-- Social Media -->
                                <div>
                                    <div class="flex items-center justify-between text-xs mb-1">
                                        <span class="flex items-center gap-1.5 text-slate-700 font-medium">
                                            <i class="ti ti-brand-instagram text-pink-500"></i> Media Sosial (IG, TikTok, FB, WA)
                                        </span>
                                        <span class="font-bold text-slate-900">{ipTrafficAnalytics?.traffic_sources?.social ?? 0}% ({ipTrafficAnalytics?.traffic_sources?.social_count ?? 0})</span>
                                    </div>
                                    <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                                        <div class="h-full rounded-full bg-purple-500" style="width: {ipTrafficAnalytics?.traffic_sources?.social ?? 0}%;"></div>
                                    </div>
                                </div>
                                <!-- External Websites -->
                                <div>
                                    <div class="flex items-center justify-between text-xs mb-1">
                                        <span class="flex items-center gap-1.5 text-slate-700 font-medium">
                                            <i class="ti ti-external-link text-slate-500"></i> Tautan Eksternal Lainnya
                                        </span>
                                        <span class="font-bold text-slate-900">{ipTrafficAnalytics?.traffic_sources?.external ?? 0}% ({ipTrafficAnalytics?.traffic_sources?.external_count ?? 0})</span>
                                    </div>
                                    <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                                        <div class="h-full rounded-full bg-amber-500" style="width: {ipTrafficAnalytics?.traffic_sources?.external ?? 0}%;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Info Tips & Insights -->
                        <div class="rounded-xl border border-blue-200 bg-blue-50/40 p-4 space-y-3 flex flex-col justify-between">
                            <div>
                                <h4 class="text-xs font-bold text-blue-900 uppercase tracking-wider flex items-center gap-1.5">
                                    <i class="ti ti-bulb text-blue-600"></i>
                                    Keamanan & Privasi Trafik
                                </h4>
                                <p class="text-xs text-blue-800/80 leading-relaxed mt-2">
                                    Sistem merekam IP dan aktivitas pengunjung secara otomatis untuk mendeteksi pola trafik, mencegah aktivitas bot mencurigakan, serta memonitor efektivitas kampanye promosi dan kunjungan toko.
                                </p>
                            </div>
                            <div class="p-3 rounded-lg bg-white border border-blue-100 flex items-center justify-between text-xs">
                                <span class="font-medium text-slate-700">Interval Polling Data Realtime:</span>
                                <span class="font-bold text-emerald-600 flex items-center gap-1">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span> 15 Detik
                                </span>
                            </div>
                        </div>
                    </div>
                {/if}
            </div>
        {/if}

        <!-- Technical Detail Modal for Visitor Log -->
        {#if isVisitorDetailModalOpen && selectedVisitorLog}
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs animate-in fade-in duration-150">
                <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl border border-slate-200 overflow-hidden">
                    <div class="px-5 py-4 border-b border-slate-150 flex items-center justify-between bg-slate-50/50">
                        <div class="flex items-center gap-2">
                            <i class="ti ti-device-analytics text-brand-blueRoyal text-lg"></i>
                            <h4 class="font-bold text-slate-900 text-sm">Detail Informasi Pengunjung & IP</h4>
                        </div>
                        <button
                            type="button"
                            onclick={() => (isVisitorDetailModalOpen = false)}
                            class="p-1 text-slate-400 hover:text-slate-700 rounded-lg transition cursor-pointer"
                        >
                            <i class="ti ti-x text-base"></i>
                        </button>
                    </div>
                    <div class="p-5 space-y-3.5 text-xs">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-150">
                                <p class="text-[10px] font-bold text-slate-400 uppercase">Alamat IP</p>
                                <p class="font-mono font-bold text-slate-900 text-sm mt-0.5 select-all">{selectedVisitorLog.ip_address}</p>
                            </div>
                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-150">
                                <p class="text-[10px] font-bold text-slate-400 uppercase">Status Pengguna</p>
                                <p class="font-bold text-slate-900 text-sm mt-0.5">{selectedVisitorLog.user_name ? selectedVisitorLog.user_name : 'Tamu / Guest'}</p>
                            </div>
                        </div>

                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-150 space-y-1">
                            <p class="text-[10px] font-bold text-slate-400 uppercase">Halaman & URL Lengkap</p>
                            <p class="font-semibold text-slate-800">{selectedVisitorLog.title}</p>
                            <p class="font-mono text-[11px] text-brand-blueRoyal break-all">{selectedVisitorLog.path}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-150">
                                <p class="text-[10px] font-bold text-slate-400 uppercase">Perangkat & OS</p>
                                <p class="font-semibold text-slate-900 mt-0.5">{selectedVisitorLog.device} • {selectedVisitorLog.os}</p>
                            </div>
                            <div class="p-3 rounded-xl bg-slate-50 border border-slate-150">
                                <p class="text-[10px] font-bold text-slate-400 uppercase">Browser</p>
                                <p class="font-semibold text-slate-900 mt-0.5">{selectedVisitorLog.browser}</p>
                            </div>
                        </div>

                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-150 space-y-1">
                            <p class="text-[10px] font-bold text-slate-400 uppercase">Referer (Sumber Kunjungan)</p>
                            <p class="font-mono text-[11px] text-slate-700 break-all">{selectedVisitorLog.referer || 'Direct / Langsung (Ketik URL atau Bookmark)'}</p>
                        </div>

                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-150 space-y-1">
                            <p class="text-[10px] font-bold text-slate-400 uppercase">User-Agent Header</p>
                            <p class="font-mono text-[10px] text-slate-600 leading-relaxed break-all bg-white p-2 rounded border border-slate-200">{selectedVisitorLog.user_agent || '-'}</p>
                        </div>

                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-150 flex items-center justify-between">
                            <span class="text-slate-500 font-medium">Waktu Akses:</span>
                            <span class="font-bold text-slate-800">{selectedVisitorLog.formatted_time} ({selectedVisitorLog.time_ago})</span>
                        </div>
                    </div>
                    <div class="px-5 py-3 border-t border-slate-150 bg-slate-50/50 flex justify-end">
                        <button
                            type="button"
                            onclick={() => (isVisitorDetailModalOpen = false)}
                            class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-900 text-white font-semibold text-xs transition cursor-pointer"
                        >
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        {/if}

        <!-- Grid Table 1: Recent Refunds + Recent Returs -->
        {#if !isSeller && !isSellerEnabled}
            <Deferred data={['recentRefunds', 'recentReturns']}>
                {#snippet fallback()}
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <div class="p-6 border border-slate-200 rounded-xl bg-white h-[250px] animate-pulse space-y-4">
                            <div class="h-4 bg-slate-200 rounded w-1/3"></div>
                            <div class="space-y-2">
                                <div class="h-10 bg-slate-100 rounded"></div>
                                <div class="h-10 bg-slate-100 rounded"></div>
                            </div>
                        </div>
                        <div class="p-6 border border-slate-200 rounded-xl bg-white h-[250px] animate-pulse space-y-4">
                            <div class="h-4 bg-slate-200 rounded w-1/3"></div>
                            <div class="space-y-2">
                                <div class="h-10 bg-slate-100 rounded"></div>
                                <div class="h-10 bg-slate-100 rounded"></div>
                            </div>
                        </div>
                    </div>
                {/snippet}

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">

                    <!-- Recent Refunds Table -->
                    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3.5">
                            <div>
                                <p class="text-sm font-semibold text-slate-800">Pengajuan Refund Terbaru</p>
                                <p class="text-xs text-slate-400 mt-0.5">Klaim pengembalian dana terkini</p>
                            </div>
                            <Link href="/admin/refunds" class="text-xs font-medium" style="color: {primaryColor};">
                                Semua Refund →
                            </Link>
                        </div>

                        {#if recentRefunds && recentRefunds.length > 0}
                            <div class="overflow-x-auto" use:dragScroll>
                                <table class="w-full responsive-table text-xs">
                                    <thead>
                                        <tr class="border-b border-slate-100 bg-slate-50/50">
                                            <th class="px-4 py-2.5 text-left font-semibold uppercase tracking-wider text-slate-400 text-[10px]">No. Refund</th>
                                            <th class="px-4 py-2.5 text-left font-semibold uppercase tracking-wider text-slate-400 text-[10px]">Pelanggan</th>
                                            <th class="px-4 py-2.5 text-left font-semibold uppercase tracking-wider text-slate-400 text-[10px]">Nominal</th>
                                            <th class="px-4 py-2.5 text-left font-semibold uppercase tracking-wider text-slate-400 text-[10px]">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        {#each recentRefunds as refund}
                                            <tr class="transition-colors hover:bg-slate-50/50">
                                                <td class="px-4 py-3" data-label="No. Refund">
                                                    <Link
                                                        href="/admin/refunds/{refund.id}"
                                                        class="font-mono text-xs font-semibold text-amber-600 hover:underline"
                                                    >
                                                        {refund.refund_number}
                                                    </Link>
                                                    <p class="text-[10px] text-slate-400">Trx: {refund.transaction_number}</p>
                                                </td>
                                                <td class="px-4 py-3" data-label="Pelanggan">
                                                    <p class="font-medium text-slate-800">{refund.customer}</p>
                                                    <p class="text-[10px] text-slate-400 truncate max-w-[120px]">{refund.email}</p>
                                                </td>
                                                <td class="px-4 py-3 font-semibold text-slate-800" data-label="Nominal">
                                                    {refund.amount_formatted}
                                                </td>
                                                <td class="px-4 py-3" data-label="Status">
                                                    {@render RefundBadge(refund.status, refund.status_label)}
                                                </td>
                                            </tr>
                                        {/each}
                                    </tbody>
                                </table>
                            </div>
                        {:else}
                            <div class="flex flex-col items-center justify-center py-12 text-center px-4">
                                <i class="ti ti-rotate-2 text-2xl text-slate-300 mb-2"></i>
                                <p class="text-sm font-medium text-slate-500">Belum ada pengajuan refund</p>
                            </div>
                        {/if}
                    </div>

                    <!-- Recent Returs Table -->
                    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3.5">
                            <div>
                                <p class="text-sm font-semibold text-slate-800">Pengajuan Retur Terbaru</p>
                                <p class="text-xs text-slate-400 mt-0.5">Klaim pengembalian & tukar barang</p>
                            </div>
                            <Link href="/admin/returns" class="text-xs font-medium" style="color: {primaryColor};">
                                Semua Retur →
                            </Link>
                        </div>

                        {#if recentReturns && recentReturns.length > 0}
                            <div class="overflow-x-auto" use:dragScroll>
                                <table class="w-full responsive-table text-xs">
                                    <thead>
                                        <tr class="border-b border-slate-100 bg-slate-50/50">
                                            <th class="px-4 py-2.5 text-left font-semibold uppercase tracking-wider text-slate-400 text-[10px]">No. Retur</th>
                                            <th class="px-4 py-2.5 text-left font-semibold uppercase tracking-wider text-slate-400 text-[10px]">Pelanggan</th>
                                            <th class="px-4 py-2.5 text-left font-semibold uppercase tracking-wider text-slate-400 text-[10px]">Tipe</th>
                                            <th class="px-4 py-2.5 text-left font-semibold uppercase tracking-wider text-slate-400 text-[10px]">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        {#each recentReturns as ret}
                                            <tr class="transition-colors hover:bg-slate-50/50">
                                                <td class="px-4 py-3" data-label="No. Retur">
                                                    <Link
                                                        href="/admin/returns/{ret.id}"
                                                        class="font-mono text-xs font-semibold text-violet-600 hover:underline"
                                                    >
                                                        {ret.return_number}
                                                    </Link>
                                                    <p class="text-[10px] text-slate-400">Trx: {ret.transaction_number}</p>
                                                </td>
                                                <td class="px-4 py-3" data-label="Pelanggan">
                                                    <p class="font-medium text-slate-800">{ret.customer}</p>
                                                    <p class="text-[10px] text-slate-400 truncate max-w-[120px]">{ret.email}</p>
                                                </td>
                                                <td class="px-4 py-3" data-label="Tipe">
                                                    <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-semibold {ret.type === 'Tukar Barang' ? 'bg-indigo-50 text-indigo-700' : 'bg-amber-50 text-amber-700'}">
                                                        {ret.type}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3" data-label="Status">
                                                    {@render ReturnBadge(ret.status, ret.status_label)}
                                                </td>
                                            </tr>
                                        {/each}
                                    </tbody>
                                </table>
                            </div>
                        {:else}
                            <div class="flex flex-col items-center justify-center py-12 text-center px-4">
                                <i class="ti ti-replace text-2xl text-slate-300 mb-2"></i>
                                <p class="text-sm font-medium text-slate-500">Belum ada pengajuan retur</p>
                            </div>
                        {/if}
                    </div>

                </div>
            </Deferred>
        {/if}

        <!-- Recent transactions + Stock info -->
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">

            <!-- Recent transactions -->
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white lg:col-span-2">
                <Deferred data="recentOrders">
                    {#snippet fallback()}
                        <div class="p-6 h-[250px] animate-pulse space-y-4">
                            <div class="h-4 bg-slate-200 rounded w-1/4"></div>
                            <div class="space-y-2">
                                <div class="h-10 bg-slate-100 rounded"></div>
                                <div class="h-10 bg-slate-100 rounded"></div>
                            </div>
                        </div>
                    {/snippet}

                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3.5">
                        <div>
                            <p class="text-sm font-semibold text-slate-800">Transaksi Terbaru</p>
                            <p class="text-xs text-slate-400 mt-0.5">Pesanan masuk terkini</p>
                        </div>
                        <Link href="/admin/transactions" class="text-xs font-medium" style="color: {primaryColor};">
                            Semua →
                        </Link>
                    </div>

                    {#if recentOrders && recentOrders.length > 0}
                        <div class="overflow-x-auto" use:dragScroll>
                            <table class="w-full responsive-table">
                                <thead>
                                    <tr class="border-b border-slate-100 bg-slate-50/50">
                                        <th class="px-5 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">No. Pesanan</th>
                                        <th class="px-5 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Pelanggan</th>
                                        <th class="px-5 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Total</th>
                                        <th class="px-5 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    {#each recentOrders as order}
                                        <tr class="group transition-colors hover:bg-slate-50/50">
                                            <td class="px-5 py-3" data-label="No. Pesanan">
                                                <Link
                                                    href="/admin/transactions/{order.raw_id}"
                                                    class="font-mono text-xs font-semibold transition-colors hover:underline"
                                                    style="color: {primaryColor};"
                                                >
                                                    {order.id}
                                                </Link>
                                            </td>
                                            <td class="px-5 py-3" data-label="Pelanggan">
                                                <div>
                                                    <p class="text-sm font-medium text-slate-800">{order.customer}</p>
                                                    {#if order.email}
                                                        <p class="text-xs text-slate-400">{order.email}</p>
                                                    {/if}
                                                </div>
                                            </td>
                                            <td class="px-5 py-3" data-label="Total">
                                                <p class="text-sm font-semibold text-slate-800">
                                                    {new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(order.amount)}
                                                </p>
                                            </td>
                                            <td class="px-5 py-3" data-label="Status">
                                                {@render StatusBadge(order.status)}
                                            </td>
                                        </tr>
                                    {/each}
                                </tbody>
                            </table>
                        </div>
                    {:else}
                        <div class="flex flex-col items-center justify-center py-12 text-center px-4">
                            <i class="ti ti-receipt-off text-2xl text-slate-300 mb-2"></i>
                            <p class="text-sm font-medium text-slate-500">Belum ada transaksi</p>
                            <p class="text-xs text-slate-400 mt-1">Transaksi baru akan muncul di sini</p>
                        </div>
                    {/if}
                </Deferred>
            </div>

            <!-- Stock info -->
            <div class="flex flex-col overflow-hidden rounded-xl border border-slate-200 bg-white">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3.5">
                    <div>
                        <p class="text-sm font-semibold text-slate-800">Info Stok</p>
                        <p class="text-xs text-slate-400 mt-0.5">Produk perlu diperhatikan</p>
                    </div>
                    <Link href="/admin/reports/stocks" class="text-xs font-medium" style="color: {primaryColor};">
                        Laporan →
                    </Link>
                </div>

                <!-- Search -->
                <div class="border-b border-slate-100 px-4 py-2.5">
                    <div class="relative flex items-center">
                        <i class="ti ti-search absolute left-3 text-xs text-slate-400 pointer-events-none"></i>
                        <input
                            type="search"
                            placeholder="Cari produk..."
                            bind:value={stockSearchInput}
                            oninput={handleStockSearch}
                            class="h-8 w-full rounded-lg border border-slate-200 bg-slate-50 pl-8 pr-3 text-xs text-slate-700 placeholder-slate-400 focus:border-slate-300 focus:bg-white focus:outline-none focus:ring-0 transition-colors"
                        />
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto max-h-72 custom-scrollbar divide-y divide-slate-100">
                    {#if productStockInfo.data && productStockInfo.data.length > 0}
                        {#each productStockInfo.data as product}
                            <div class="flex items-center gap-3 px-4 py-2.5">
                                <img
                                    src={product.image}
                                    alt={product.name}
                                    class="h-8 w-8 shrink-0 rounded-md object-cover bg-slate-100"
                                />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-xs font-medium text-slate-800">{product.name}</p>
                                    <p class="text-[10px] text-slate-400">{product.sku || '—'}</p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <p class="text-sm font-bold {product.is_unlimited ? 'text-emerald-500' : product.current_stock === 0 ? 'text-rose-500' : product.current_stock <= 5 ? 'text-amber-500' : 'text-emerald-500'}">
                                        {product.is_unlimited ? '∞' : product.current_stock}
                                    </p>
                                    <p class="text-[10px] text-slate-400">stok</p>
                                </div>
                            </div>
                        {/each}
                    {:else}
                        <div class="flex flex-col items-center justify-center py-10 text-center px-4">
                            <i class="ti ti-package-off text-2xl text-slate-300 mb-2"></i>
                            <p class="text-xs text-slate-400">Tidak ada produk</p>
                        </div>
                    {/if}
                </div>

                {#if productStockInfo.data && productStockInfo.data.length > 0}
                    <Pagination
                        data={productStockInfo}
                        class="p-4 flex flex-col items-center gap-2.5 text-center"
                    />
                {/if}
            </div>
        </div>

        <!-- Recent customers -->
        {#if !isSeller}
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <Deferred data="recentCustomers">
                    {#snippet fallback()}
                        <div class="p-6 h-[250px] animate-pulse space-y-4">
                            <div class="h-4 bg-slate-200 rounded w-1/4"></div>
                            <div class="space-y-2">
                                <div class="h-10 bg-slate-100 rounded"></div>
                                <div class="h-10 bg-slate-100 rounded"></div>
                            </div>
                        </div>
                    {/snippet}

                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3.5">
                        <div>
                            <p class="text-sm font-semibold text-slate-800">Pelanggan Terbaru</p>
                            <p class="text-xs text-slate-400 mt-0.5">Daftar pelanggan yang baru terdaftar</p>
                        </div>
                        <Link href="/admin/master-data/customers" class="text-xs font-medium transition-colors hover:text-slate-900" style="color: {primaryColor};">
                            Semua →
                        </Link>
                    </div>
                    {#if recentCustomers && recentCustomers.length > 0}
                        <div class="overflow-x-auto" use:dragScroll>
                            <table class="w-full responsive-table text-sm">
                                <thead>
                                    <tr class="border-b border-slate-100 bg-slate-50/50">
                                        <th class="px-5 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Nama</th>
                                        <th class="px-5 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Email</th>
                                        <th class="px-5 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">No. Telepon</th>
                                        <th class="px-5 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Tanggal Gabung</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    {#each recentCustomers as customer}
                                        <tr class="group transition-colors hover:bg-slate-50/50">
                                            <td class="px-5 py-3" data-label="Nama">
                                                <div class="flex items-center gap-2.5">
                                                    <div class="flex h-7 w-7 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-600">
                                                        {customer.initials}
                                                    </div>
                                                    <p class="text-sm font-medium text-slate-800">{customer.name}</p>
                                                </div>
                                            </td>
                                            <td class="px-5 py-3" data-label="Email">
                                                <span class="text-xs text-slate-600">{customer.email}</span>
                                            </td>
                                            <td class="px-5 py-3" data-label="No. Telepon">
                                                <span class="text-xs text-slate-600">{customer.phone}</span>
                                            </td>
                                            <td class="px-5 py-3" data-label="Tanggal Gabung">
                                                <span class="text-xs text-slate-500">{customer.date}</span>
                                            </td>
                                        </tr>
                                    {/each}
                                </tbody>
                            </table>
                        </div>
                    {:else}
                        <div class="flex flex-col items-center justify-center py-12 text-center px-4">
                            <i class="ti ti-users-off text-2xl text-slate-300 mb-2"></i>
                            <p class="text-sm font-medium text-slate-500">Belum ada pelanggan</p>
                        </div>
                    {/if}
                </Deferred>
            </div>
        {/if}

        <!-- Recent stock out -->
        <Deferred data="recentStockOut">
            {#snippet fallback()}
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white p-6 h-[250px] animate-pulse space-y-4">
                    <div class="h-4 bg-slate-200 rounded w-1/4"></div>
                    <div class="h-28 bg-slate-100 rounded"></div>
                </div>
            {/snippet}

            {#if recentStockOut && recentStockOut.length > 0}
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3.5">
                        <div>
                            <p class="text-sm font-semibold text-slate-800">Stok Keluar Terbaru</p>
                            <p class="text-xs text-slate-400 mt-0.5">Riwayat pergerakan stok terakhir</p>
                        </div>
                        <Link href="/admin/stock-movements" class="text-xs font-medium" style="color: {primaryColor};">
                            Semua →
                        </Link>
                    </div>
                    <div class="overflow-x-auto" use:dragScroll>
                        <table class="w-full responsive-table">
                            <thead>
                                <tr class="border-b border-slate-100 bg-slate-50/50">
                                    <th class="px-5 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Produk</th>
                                    <th class="px-5 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Jumlah</th>
                                    <th class="px-5 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">No. Transaksi</th>
                                    <th class="px-5 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Keterangan</th>
                                    <th class="px-5 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Tanggal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                {#each recentStockOut as mov}
                                    <tr class="transition-colors hover:bg-slate-50/50">
                                        <td class="px-5 py-3" data-label="Produk">
                                            <div>
                                                <p class="text-sm font-medium text-slate-800">{mov.product_name}</p>
                                                {#if mov.variant_name}
                                                    <p class="text-xs text-slate-400">{mov.variant_name}</p>
                                                {/if}
                                            </div>
                                        </td>
                                        <td class="px-5 py-3" data-label="Jumlah">
                                            <span class="inline-flex items-center rounded-md bg-rose-50 px-2 py-0.5 text-xs font-semibold text-rose-600">
                                                {mov.quantity}
                                            </span>
                                        </td>
                                        <td class="px-5 py-3" data-label="No. Transaksi">
                                            {#if mov.transaction_number}
                                                <Link
                                                    href="/admin/transactions/{mov.transaction_id}"
                                                    class="font-mono text-xs font-semibold hover:underline"
                                                    style="color: {primaryColor};"
                                                >
                                                    {mov.transaction_number}
                                                </Link>
                                            {:else}
                                                <span class="text-xs text-slate-400">—</span>
                                            {/if}
                                        </td>
                                        <td class="px-5 py-3" data-label="Keterangan">
                                            <span class="text-xs text-slate-500">{mov.notes ?? '—'}</span>
                                        </td>
                                        <td class="px-5 py-3" data-label="Tanggal">
                                            <span class="whitespace-nowrap text-xs text-slate-500">{mov.date}</span>
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                </div>
            {/if}
        </Deferred>

    </main>
</AdminLayout>

<!-- ── Snippets ─────────────────────────────────────────── -->

{#snippet StatCard(props: { icon: string; iconBg: string; iconColor: string; label: string; value: any; change: { type: string; value: string } })}
    <div class="group overflow-hidden rounded-xl border border-slate-200 bg-white p-4 transition-shadow hover:shadow-md">
        <div class="flex items-start justify-between">
            <div
                class="flex h-9 w-9 items-center justify-center rounded-lg text-base transition-transform duration-200 group-hover:scale-105"
                style="background-color: {props.iconBg}; color: {props.iconColor};"
            >
                <i class="ti {props.icon}"></i>
            </div>
            {#if props.change.type === 'up'}
                <span class="inline-flex items-center gap-0.5 rounded-full bg-emerald-50 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-600">
                    <i class="ti ti-trending-up text-[10px]"></i>
                    {props.change.value}
                </span>
            {:else if props.change.type === 'down'}
                <span class="inline-flex items-center gap-0.5 rounded-full bg-rose-50 px-1.5 py-0.5 text-[10px] font-semibold text-rose-600">
                    <i class="ti ti-trending-down text-[10px]"></i>
                    {props.change.value}
                </span>
            {:else}
                <span class="inline-flex items-center gap-0.5 rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-500">
                    {props.change.value}
                </span>
            {/if}
        </div>
        <div class="mt-3">
            <p class="text-xl font-bold tracking-tight text-slate-900 truncate">{props.value}</p>
            <p class="mt-0.5 text-[11px] font-medium text-slate-500 truncate">{props.label}</p>
        </div>
    </div>
{/snippet}

{#snippet StatusBadge(status: string)}
    {@const cfg = {
        belum_bayar: { label: 'Belum Bayar',   cls: 'bg-amber-50 text-amber-700' },
        menunggu:    { label: 'Menunggu',       cls: 'bg-blue-50 text-blue-700' },
        diproses:    { label: 'Diproses',       cls: 'bg-violet-50 text-violet-700' },
        dikirim:     { label: 'Dikirim',        cls: 'bg-emerald-50 text-emerald-700' },
        selesai:     { label: 'Selesai',        cls: 'bg-emerald-50 text-emerald-700' },
        dibatalkan:  { label: 'Dibatalkan',     cls: 'bg-rose-50 text-rose-700' },
    }[status] ?? { label: status, cls: 'bg-slate-100 text-slate-600' }}
    <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-semibold {cfg.cls}">
        {cfg.label}
    </span>
{/snippet}

{#snippet RefundBadge(status: string, label: string)}
    {@const cfg = {
        menunggu_konfirmasi: 'bg-amber-50 text-amber-700 border border-amber-200',
        disetujui: 'bg-blue-50 text-blue-700 border border-blue-200',
        selesai: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
        ditolak: 'bg-rose-50 text-rose-700 border border-rose-200',
    }[status] ?? 'bg-slate-100 text-slate-600'}
    <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-semibold {cfg}">
        {label || status}
    </span>
{/snippet}

{#snippet ReturnBadge(status: string, label: string)}
    {@const cfg = {
        menunggu_review: 'bg-amber-50 text-amber-700 border border-amber-200',
        disetujui: 'bg-blue-50 text-blue-700 border border-blue-200',
        barang_dikirim_customer: 'bg-indigo-50 text-indigo-700 border border-indigo-200',
        barang_diterima_toko: 'bg-violet-50 text-violet-700 border border-violet-200',
        refund_diproses: 'bg-purple-50 text-purple-700 border border-purple-200',
        selesai: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
        ditolak: 'bg-rose-50 text-rose-700 border border-rose-200',
    }[status] ?? 'bg-slate-100 text-slate-600'}
    <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-semibold {cfg}">
        {label || status}
    </span>
{/snippet}
