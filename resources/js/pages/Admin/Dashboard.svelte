<script lang="ts">
    import { onMount } from 'svelte';
    import { page, router, Link } from '@inertiajs/svelte';
    const primaryColor = $derived(page.props.theme?.primary_color || '#0c4cb4');
    const secondaryColor = $derived(
        page.props.theme?.secondary_color || '#fa7315',
    );
    import Chart from 'chart.js/auto';
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import Pagination from '@/components/ui/Pagination.svelte';

    let {
        stats,
        orderStats,
        recentOrders,
        topProducts,
        chartData,
        currentFilter: initialFilter = '7_hari',
        productStockInfo = { data: [] },
        recentStockOut = [],
        recentCustomers = [],
        search: initialSearch = '',
    } = $props();

    // svelte-ignore state_referenced_locally
    let selectedFilter = $state(initialFilter);
    // svelte-ignore state_referenced_locally
    let stockSearchInput = $state(initialSearch);
    let canvas: HTMLCanvasElement;
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

            const primaryColor = page.props.theme?.primary_color || '#0c4cb4';

            let gradient = ctx.createLinearGradient(0, 0, 0, 400);
            gradient.addColorStop(0, primaryColor + '33'); // 20% opacity
            gradient.addColorStop(1, primaryColor + '00'); // 0% opacity

            chartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: [...chartData.labels],
                    datasets: [
                        {
                            label: 'Revenue (Rp Juta)',
                            data: [...chartData.data],
                            borderColor: primaryColor, // brand-blueRoyal dynamic
                            backgroundColor: gradient,
                            borderWidth: 3,
                            pointBackgroundColor: '#ffffff',
                            pointBorderColor: primaryColor,
                            pointBorderWidth: 2,
                            pointRadius: 5,
                            pointHoverRadius: 7,
                            fill: true,
                            tension: 0.4,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
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
                            displayColors: false,
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
                <h1 class="text-xl font-semibold tracking-tight text-slate-900">Dashboard</h1>
                <p class="mt-0.5 text-sm text-slate-500">Pantau performa bisnis dan aktivitas operasional toko.</p>
            </div>
            <div class="flex items-center gap-2">
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
            </div>
        </div>

        <!-- KPI stat cards -->
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">

            <!-- Revenue -->
            {@render StatCard({
                icon: 'ti-wallet',
                iconBg: primaryColor + '15',
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
                label: 'Total Orders',
                value: stats.ordersCount,
                change: stats.ordersChange,
            })}

            <!-- Products -->
            {@render StatCard({
                icon: 'ti-box',
                iconBg: '#f5f3ff',
                iconColor: '#7c3aed',
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

        <!-- Order pipeline -->
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3.5">
                <div>
                    <p class="text-sm font-semibold text-slate-800">Pipeline Pesanan</p>
                    <p class="text-xs text-slate-400 mt-0.5">Status real-time pesanan masuk</p>
                </div>
                <Link href="/admin/transactions" class="text-xs font-medium transition-colors hover:text-slate-900" style="color: {primaryColor};">
                    Lihat semua →
                </Link>
            </div>
            <div class="grid grid-cols-2 divide-x divide-y divide-slate-100 sm:grid-cols-4 sm:divide-y-0">
                <Link href="/admin/transactions?status=belum_bayar" class="flex items-center gap-3 p-4 transition-colors hover:bg-slate-50">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600 text-lg">
                        <i class="ti ti-clock"></i>
                    </div>
                    <div>
                        <p class="text-xl font-bold text-slate-800 leading-none">{orderStats.unpaidCount}</p>
                        <p class="mt-1 text-xs text-slate-500">Belum Bayar</p>
                    </div>
                </Link>
                <Link href="/admin/transactions?status=menunggu" class="flex items-center gap-3 p-4 transition-colors hover:bg-slate-50">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600 text-lg">
                        <i class="ti ti-loader"></i>
                    </div>
                    <div>
                        <p class="text-xl font-bold text-slate-800 leading-none">{orderStats.pendingCount}</p>
                        <p class="mt-1 text-xs text-slate-500">Menunggu</p>
                    </div>
                </Link>
                <Link href="/admin/transactions?status=diproses" class="flex items-center gap-3 p-4 transition-colors hover:bg-slate-50">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-lg" style="background-color: {primaryColor}15; color: {primaryColor};">
                        <i class="ti ti-package"></i>
                    </div>
                    <div>
                        <p class="text-xl font-bold text-slate-800 leading-none">{orderStats.readyCount}</p>
                        <p class="mt-1 text-xs text-slate-500">Diproses</p>
                    </div>
                </Link>
                <Link href="/admin/transactions?status=dikirim" class="flex items-center gap-3 p-4 transition-colors hover:bg-slate-50">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 text-lg">
                        <i class="ti ti-truck-delivery"></i>
                    </div>
                    <div>
                        <p class="text-xl font-bold text-slate-800 leading-none">{orderStats.shippingCount}</p>
                        <p class="mt-1 text-xs text-slate-500">Dikirim</p>
                    </div>
                </Link>
            </div>
        </div>

        <!-- Chart + Top Products -->
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">

            <!-- Revenue chart -->
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white lg:col-span-2">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3.5">
                    <div>
                        <p class="text-sm font-semibold text-slate-800">Revenue Analytics</p>
                        <p class="text-xs text-slate-400 mt-0.5">Tren pendapatan per periode</p>
                    </div>
                </div>
                <div class="p-5">
                    <div class="h-64">
                        <canvas bind:this={canvas}></canvas>
                    </div>
                </div>
            </div>

            <!-- Top products -->
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
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
            </div>
        </div>

        <!-- Recent transactions + Stock info -->
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">

            <!-- Recent transactions -->
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white lg:col-span-2">
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
        </div>

        <!-- Recent stock out -->
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

    </main>
</AdminLayout>

<!-- ── Snippets ─────────────────────────────────────────── -->

{#snippet StatCard(props: { icon: string; iconBg: string; iconColor: string; label: string; value: any; change: { type: string; value: string } })}
    <div class="group overflow-hidden rounded-xl border border-slate-200 bg-white p-5 transition-shadow hover:shadow-md">
        <div class="flex items-start justify-between">
            <div
                class="flex h-10 w-10 items-center justify-center rounded-lg text-lg transition-transform duration-200 group-hover:scale-105"
                style="background-color: {props.iconBg}; color: {props.iconColor};"
            >
                <i class="ti {props.icon}"></i>
            </div>
            {#if props.change.type === 'up'}
                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-600">
                    <i class="ti ti-trending-up text-xs"></i>
                    {props.change.value}
                </span>
            {:else if props.change.type === 'down'}
                <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2 py-0.5 text-[11px] font-semibold text-rose-600">
                    <i class="ti ti-trending-down text-xs"></i>
                    {props.change.value}
                </span>
            {:else}
                <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-500">
                    {props.change.value}
                </span>
            {/if}
        </div>
        <div class="mt-4">
            <p class="text-2xl font-bold tracking-tight text-slate-900">{props.value}</p>
            <p class="mt-0.5 text-xs font-medium text-slate-500">{props.label}</p>
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
