<script lang="ts">
    import { onMount } from 'svelte';
    import { page, router, Link } from '@inertiajs/svelte';
    const primaryColor = $derived(page.props.theme?.primary_color || '#0c4cb4');
    const secondaryColor = $derived(
        page.props.theme?.secondary_color || '#fa7315',
    );
    import Chart from 'chart.js/auto';
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';

    let { stats, orderStats, recentOrders, topProducts, chartData, currentFilter = '7_hari' } = $props();

    let selectedFilter = $state(currentFilter);
    let canvas: HTMLCanvasElement;
    let chartInstance: Chart | null = null;

    $effect(() => {
        selectedFilter = currentFilter;
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
        router.get('/admin/dashboard', { filter: value }, { preserveState: true });
    }
</script>

<svelte:head>
    <title>Admin Dashboard</title>
</svelte:head>

<AdminLayout>
    <main class="flex-grow p-4 sm:p-8 w-full max-w-[1600px] mx-auto space-y-8">
        <!-- Page Header & Actions -->
        <div
            class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4"
        >
            <div>
                <h1
                    class="font-outfit font-black text-2xl sm:text-3xl text-slate-800 tracking-tight"
                >
                    Overview Dashboard
                </h1>
                <p class="text-sm text-slate-500 font-medium mt-1">
                    Pantau performa bisnis dan aktivitas operasional toko Anda
                    hari ini.
                </p>
            </div>

            <div class="flex items-center gap-3 w-full sm:w-auto">
                <div class="relative w-full sm:w-auto">
                    <select
                        value={selectedFilter}
                        onchange={handleFilterChange}
                        class="w-full sm:w-auto appearance-none bg-white border border-slate-200 text-slate-700 text-sm font-bold rounded-xl px-4 py-2.5 pr-10 focus:outline-none focus:ring-2 transition cursor-pointer"
                    >
                        <option value="hari_ini">Hari Ini</option>
                        <option value="7_hari">7 Hari</option>
                        <option value="1_bulan">1 Bulan</option>
                        <option value="1_tahun">1 Tahun</option>
                        <option value="tahun_lalu">Tahun Lalu</option>
                    </select>
                    <i
                        class="ti ti-calendar absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"
                    ></i>
                </div>
                <button
                    class="hidden sm:flex items-center justify-center w-10 h-10 bg-white border border-slate-200 text-slate-600 rounded-xl hover:bg-slate-50 transition shadow-sm"
                    title="Export Report"
                >
                    <i class="ti ti-download"></i>
                </button>
            </div>
        </div>

        <!-- Analytics Cards Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- Card 1: Revenue -->
            <div
                class="bg-white rounded-3xl py-8 px-6 border border-slate-200 shadow-soft hover:shadow-lg transition duration-300 group"
            >
                <div class="flex justify-between items-start mb-4">
                    <div
                        class="w-12 h-12 rounded-2xl bg-slate-50 flex items-center justify-center text-2xl group-hover:scale-110 transition"
                        style="color: {primaryColor}; background-color: {primaryColor}1A;"
                    >
                        <i class="ti ti-wallet"></i>
                    </div>
                    {#if stats.revenueChange.type === 'up'}
                        <span
                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-600 text-[11px] font-extrabold"
                        >
                            <i class="ti ti-trending-up text-sm"></i> {stats.revenueChange.value}
                        </span>
                    {:else if stats.revenueChange.type === 'down'}
                        <span
                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-rose-50 text-rose-600 text-[11px] font-extrabold"
                        >
                            <i class="ti ti-trending-down text-sm"></i> {stats.revenueChange.value}
                        </span>
                    {:else}
                        <span
                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-slate-100 text-slate-500 text-[11px] font-extrabold"
                        >
                            <i class="ti ti-minus text-sm"></i> {stats.revenueChange.value}
                        </span>
                    {/if}
                </div>
                <p
                    class="text-xs font-bold text-slate-500 uppercase tracking-widest font-outfit mb-1"
                >
                    Total Revenue
                </p>
                <h3
                    class="font-outfit font-black text-2xl sm:text-3xl text-slate-800"
                >
                    {stats.revenueFormatted}
                </h3>
            </div>

            <!-- Card 2: Orders -->
            <div
                class="bg-white rounded-3xl py-8 px-6 border border-slate-200 shadow-soft hover:shadow-lg transition duration-300 group"
            >
                <div class="flex justify-between items-start mb-4">
                    <div
                        class="w-12 h-12 rounded-2xl bg-slate-50 flex items-center justify-center text-2xl group-hover:scale-110 transition"
                        style="color: {secondaryColor}; background-color: {secondaryColor}1A;"
                    >
                        <i class="ti ti-shopping-cart"></i>
                    </div>
                    {#if stats.ordersChange.type === 'up'}
                        <span
                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-600 text-[11px] font-extrabold"
                        >
                            <i class="ti ti-trending-up text-sm"></i> {stats.ordersChange.value}
                        </span>
                    {:else if stats.ordersChange.type === 'down'}
                        <span
                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-rose-50 text-rose-600 text-[11px] font-extrabold"
                        >
                            <i class="ti ti-trending-down text-sm"></i> {stats.ordersChange.value}
                        </span>
                    {:else}
                        <span
                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-slate-100 text-slate-500 text-[11px] font-extrabold"
                        >
                            <i class="ti ti-minus text-sm"></i> {stats.ordersChange.value}
                        </span>
                    {/if}
                </div>
                <p
                    class="text-xs font-bold text-slate-500 uppercase tracking-widest font-outfit mb-1"
                >
                    Total Orders
                </p>
                <h3
                    class="font-outfit font-black text-2xl sm:text-3xl text-slate-800"
                >
                    {stats.ordersCount}
                </h3>
            </div>

            <!-- Card 3: Products -->
            <div
                class="bg-white rounded-3xl py-8 px-6 border border-slate-200 shadow-soft hover:shadow-lg transition duration-300 group"
            >
                <div class="flex justify-between items-start mb-4">
                    <div
                        class="w-12 h-12 rounded-2xl bg-purple-50 text-purple-600 flex items-center justify-center text-2xl group-hover:scale-110 transition"
                    >
                        <i class="ti ti-box"></i>
                    </div>
                    {#if stats.productsChange.type === 'up'}
                        <span
                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-600 text-[11px] font-extrabold"
                        >
                            <i class="ti ti-trending-up text-sm"></i> {stats.productsChange.value}
                        </span>
                    {:else if stats.productsChange.type === 'down'}
                        <span
                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-rose-50 text-rose-600 text-[11px] font-extrabold"
                        >
                            <i class="ti ti-trending-down text-sm"></i> {stats.productsChange.value}
                        </span>
                    {:else}
                        <span
                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-slate-100 text-slate-500 text-[11px] font-extrabold"
                        >
                            <i class="ti ti-minus text-sm"></i> {stats.productsChange.value}
                        </span>
                    {/if}
                </div>
                <p
                    class="text-xs font-bold text-slate-500 uppercase tracking-widest font-outfit mb-1"
                >
                    Active Products
                </p>
                <h3
                    class="font-outfit font-black text-2xl sm:text-3xl text-slate-800"
                >
                    {stats.activeProductsCount}
                </h3>
            </div>

            <!-- Card 4: Customers -->
            <div
                class="bg-white rounded-3xl py-8 px-6 border border-slate-200 shadow-soft hover:shadow-lg transition duration-300 group"
            >
                <div class="flex justify-between items-start mb-4">
                    <div
                        class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center text-2xl group-hover:scale-110 transition"
                    >
                        <i class="ti ti-users"></i>
                    </div>
                    {#if stats.customersChange.type === 'up'}
                        <span
                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-600 text-[11px] font-extrabold"
                        >
                            <i class="ti ti-trending-up text-sm"></i> {stats.customersChange.value}
                        </span>
                    {:else if stats.customersChange.type === 'down'}
                        <span
                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-rose-50 text-rose-600 text-[11px] font-extrabold"
                        >
                            <i class="ti ti-trending-down text-sm"></i> {stats.customersChange.value}
                        </span>
                    {:else}
                        <span
                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-slate-100 text-slate-500 text-[11px] font-extrabold"
                        >
                            <i class="ti ti-minus text-sm"></i> {stats.customersChange.value}
                        </span>
                    {/if}
                </div>
                <p
                    class="text-xs font-bold text-slate-500 uppercase tracking-widest font-outfit mb-1"
                >
                    Total Customers
                </p>
                <h3
                    class="font-outfit font-black text-2xl sm:text-3xl text-slate-800"
                >
                    {stats.customersCount}
                </h3>
            </div>
        </div>

        <!-- Section: Status Operasional Pesanan -->
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h2
                    class="font-outfit font-black text-lg text-slate-800 tracking-tight flex items-center gap-2"
                >
                    <i
                        class="ti ti-device-analytics text-xl"
                        style="color: {primaryColor};"
                    ></i>
                    Status Operasional Pesanan
                </h2>
                <Link
                    href="/admin/transactions"
                    class="text-xs font-bold hover:underline flex items-center gap-1"
                    style="color: {primaryColor};"
                >
                    Kelola Pesanan <i class="ti ti-arrow-right"></i>
                </Link>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                <!-- Card 1: Total Pesanan Baru -->
                <div
                    class="bg-white rounded-3xl p-6 border border-slate-200 shadow-soft hover:shadow-lg transition duration-300 group cursor-pointer"
                >
                    <div class="flex justify-between items-start mb-4">
                        <div
                            class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-2xl group-hover:scale-110 transition"
                        >
                            <i class="ti ti-receipt"></i>
                        </div>
                        <span
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-amber-50 border border-amber-100 text-amber-600 text-[10px] font-bold uppercase tracking-wider"
                        >
                            <span
                                class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"
                            ></span> Baru
                        </span>
                    </div>
                    <p
                        class="text-xs font-bold text-slate-500 uppercase tracking-widest font-outfit mb-1"
                    >
                        Total Pesanan Baru
                    </p>
                    <h3
                        class="font-outfit font-black text-2xl sm:text-3xl text-slate-800"
                    >
                        {orderStats.newCount}
                    </h3>
                </div>

                <!-- Card 2: Pesanan di Proses -->
                <div
                    class="bg-white rounded-3xl p-6 border border-slate-200 shadow-soft hover:shadow-lg transition duration-300 group cursor-pointer"
                >
                    <div class="flex justify-between items-start mb-4">
                        <div
                            class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-2xl group-hover:scale-110 transition"
                        >
                            <i class="ti ti-box"></i>
                        </div>
                        <span
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-indigo-50 border border-indigo-100 text-indigo-600 text-[10px] font-bold uppercase tracking-wider"
                        >
                            <span
                                class="w-1.5 h-1.5 rounded-full bg-indigo-500 animate-pulse"
                            ></span> Di Proses
                        </span>
                    </div>
                    <p
                        class="text-xs font-bold text-slate-500 uppercase tracking-widest font-outfit mb-1"
                    >
                        Pesanan di Proses
                    </p>
                    <h3
                        class="font-outfit font-black text-2xl sm:text-3xl text-slate-800"
                    >
                        {orderStats.readyCount}
                    </h3>
                </div>

                <!-- Card 3: Pesanan dikirim -->
                <div
                    class="bg-white rounded-3xl p-6 border border-slate-200 shadow-soft hover:shadow-lg transition duration-300 group cursor-pointer"
                >
                    <div class="flex justify-between items-start mb-4">
                        <div
                            class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-2xl group-hover:scale-110 transition"
                        >
                            <i class="ti ti-truck-delivery"></i>
                        </div>
                        <span
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-50 border border-emerald-100 text-emerald-600 text-[10px] font-bold uppercase tracking-wider"
                        >
                            <span
                                class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"
                            ></span> Dikirim
                        </span>
                    </div>
                    <p
                        class="text-xs font-bold text-slate-500 uppercase tracking-widest font-outfit mb-1"
                    >
                        Pesanan dikirim
                    </p>
                    <h3
                        class="font-outfit font-black text-2xl sm:text-3xl text-slate-800"
                    >
                        {orderStats.shippingCount}
                    </h3>
                </div>
            </div>
        </div>

        <!-- Main Charts Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Revenue Analytics Chart (2 Cols) -->
            <div
                class="lg:col-span-2 bg-white rounded-3xl border border-slate-200 shadow-soft p-6"
            >
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h3
                            class="font-outfit font-black text-lg text-slate-800"
                        >
                            Revenue Analytics
                        </h3>
                        <p class="text-xs text-slate-500 font-medium">
                            Tren pendapatan penjualan per bulan
                        </p>
                    </div>
                    <button
                        class="p-2 text-slate-400 rounded-lg hover:bg-slate-50 transition hover:text-[var(--hover-color)]"
                        style="--tw-text-opacity: 1; --hover-color: {primaryColor};"
                    >
                        <i class="ti ti-dots-vertical"></i>
                    </button>
                </div>
                <div class="w-full h-80">
                    <canvas bind:this={canvas}></canvas>
                </div>
            </div>

            <!-- Top Selling Products / Quick Actions -->
            <div
                class="bg-white rounded-3xl border border-slate-200 shadow-soft p-6 flex flex-col"
            >
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h3
                            class="font-outfit font-black text-lg text-slate-800"
                        >
                            Top Products
                        </h3>
                        <p class="text-xs text-slate-500 font-medium">
                            Berdasarkan volume penjualan
                        </p>
                    </div>
                    <Link
                        href="/admin/products"
                        class="text-xs font-bold hover:underline"
                        style="color: {primaryColor};">Lihat Semua</Link
                    >
                </div>

                <div class="space-y-5 flex-grow">
                    {#each topProducts as product}
                        <div
                            class="flex items-center gap-4 group cursor-pointer"
                        >
                            <img
                                src={product.image}
                                alt="Product"
                                class="w-14 h-14 rounded-xl object-cover border border-slate-100 transition"
                            />
                            <div class="flex-grow">
                                <h4
                                    class="text-sm font-bold text-slate-800 transition hover:text-[var(--hover-color)]"
                                    style="--hover-color: {primaryColor};"
                                >
                                    {product.name}
                                </h4>
                                <p class="text-xs text-slate-500 font-medium">
                                    {product.category}
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-black text-slate-800">
                                    {product.sales}
                                </p>
                                <p
                                    class="text-[10px] font-bold text-emerald-500 uppercase"
                                >
                                    Sales
                                </p>
                            </div>
                        </div>
                    {/each}
                </div>

                <!-- Quick Action -->
                <div class="mt-6 pt-6 border-t border-slate-100">
                    <Link
                        href="/admin/products"
                        class="w-full py-3 text-white font-bold rounded-xl text-xs transition duration-200 flex items-center justify-center gap-2 shadow-lg font-outfit uppercase tracking-wider"
                        style="background-color: {primaryColor};"
                    >
                        <i class="ti ti-box text-sm"></i> Kelola Semua Produk
                    </Link>
                </div>
            </div>
        </div>

        <!-- Recent Orders Table -->
        <div
            class="bg-white rounded-3xl border border-slate-200 shadow-soft overflow-hidden"
        >
            <div
                class="p-6 border-b border-slate-200 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4"
            >
                <div>
                    <h3 class="font-outfit font-black text-lg text-slate-800">
                        Recent Orders
                    </h3>
                    <p class="text-xs text-slate-500 font-medium">
                        Transaksi pesanan terbaru yang masuk ke sistem.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Link
                        href="/admin/transactions"
                        class="px-4 py-2 border border-slate-200 hover:border-slate-300 text-slate-600 font-bold text-xs rounded-lg transition"
                        >View All Orders</Link
                    >
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[800px]">
                    <thead>
                        <tr class="bg-slate-50/50">
                            <th
                                class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-200"
                                >Order ID</th
                            >
                            <th
                                class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-200"
                                >Customer</th
                            >
                            <th
                                class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-200"
                                >Date</th
                            >
                            <th
                                class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-200"
                                >Total Amount</th
                            >
                            <th
                                class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-200"
                                >Status</th
                            >
                            <th
                                class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-200 text-right"
                                >Action</th
                            >
                        </tr>
                    </thead>
                    <tbody
                        class="text-sm font-medium text-slate-700 divide-y divide-slate-100"
                    >
                        {#each recentOrders as order}
                            <tr class="hover:bg-slate-50/50 transition">
                                <td class="px-6 py-4 font-bold text-slate-800"
                                    >{order.id}</td
                                >
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="w-8 h-8 rounded-full bg-slate-200 flex items-center justify-center text-slate-600 font-bold text-xs"
                                        >
                                            {order.initials}
                                        </div>
                                        <div>
                                            <p class="font-bold text-slate-800">
                                                {order.customer}
                                            </p>
                                            <p
                                                class="text-[11px] text-slate-500 font-normal"
                                            >
                                                {order.email}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-slate-500"
                                    >{order.date}</td
                                >
                                <td class="px-6 py-4 font-bold"
                                    >Rp {order.amount.toLocaleString(
                                        'id-ID',
                                    )}</td
                                >
                                <td class="px-6 py-4">
                                    {#if order.status === 'Paid'}
                                        <span
                                            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-100 text-emerald-600 text-[10px] font-bold uppercase tracking-wider"
                                        >
                                            <span
                                                class="w-1.5 h-1.5 rounded-full bg-emerald-500"
                                            ></span> Paid
                                        </span>
                                    {:else if order.status === 'Pending'}
                                        <span
                                            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-50 border border-amber-100 text-amber-600 text-[10px] font-bold uppercase tracking-wider"
                                        >
                                            <span
                                                class="w-1.5 h-1.5 rounded-full bg-amber-500"
                                            ></span> Pending
                                        </span>
                                    {:else}
                                        <span
                                            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-100 border border-slate-200 text-slate-600 text-[10px] font-bold uppercase tracking-wider"
                                        >
                                            <span
                                                class="w-1.5 h-1.5 rounded-full bg-slate-400"
                                            ></span>
                                            {order.status}
                                        </span>
                                    {/if}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <Link
                                        href="/admin/transactions/{order.raw_id}"
                                        class="inline-block p-2 text-slate-400 rounded-lg transition hover:text-[var(--hover-color)]"
                                        style="--hover-color: {primaryColor};"
                                        title="View Detail"
                                        ><i class="ti ti-eye"></i></Link
                                    >
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</AdminLayout>
