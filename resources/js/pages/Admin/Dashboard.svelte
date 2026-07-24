<script lang="ts">
    import { adjustColorOpacity } from '@/utils/color';

    import { onMount } from 'svelte';
    import { page, router, Link, Deferred } from '@inertiajs/svelte';
    const primaryColor = $derived(page.props.theme?.primary_color || '#0c4cb4');
    const secondaryColor = $derived(
        page.props.theme?.secondary_color || '#fa7315',
    );
    import Chart from 'chart.js/auto';
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import Pagination from '@/components/ui/Pagination.svelte';

    let {
        stats,
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

    // svelte-ignore state_referenced_locally
    let selectedFilter = $state(initialFilter);
    // svelte-ignore state_referenced_locally
    let stockSearchInput = $state(initialSearch);
    let chartMetric = $state<'all' | 'revenue' | 'refund' | 'return'>('all');
    let activePipelineTab = $state<'transactions' | 'refunds' | 'returns'>('transactions');

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

            if (chartMetric === 'all' || chartMetric === 'refund') {
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

            if (chartMetric === 'all' || chartMetric === 'return') {
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
            </div>
        </div>

        <!-- KPI stat cards (6 grid columns) -->
        <Deferred data={['stats', 'refundStats', 'returnStats']}>
            {#snippet fallback()}
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6 animate-pulse">
                    {#each Array(6) as _}
                        <div class="rounded-xl border border-slate-200 bg-white p-4 h-24 flex items-center justify-between">
                            <div class="space-y-2 w-full">
                                <div class="h-3 w-16 bg-slate-100 rounded-md"></div>
                                <div class="h-6 w-24 bg-slate-200 rounded-md"></div>
                            </div>
                        </div>
                    {/each}
                </div>
            {/snippet}

            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">

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
                            <p class="text-sm font-semibold text-slate-800">Tren Finansial & Pengembalian</p>
                            <p class="text-xs text-slate-400 mt-0.5">Perbandingan Penjualan vs Refund & Retur (6 Bulan)</p>
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

        <!-- Grid Table 1: Recent Refunds + Recent Returs -->
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
