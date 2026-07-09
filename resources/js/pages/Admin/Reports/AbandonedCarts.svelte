<script lang="ts">
    import { page, router } from '@inertiajs/svelte';
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import Pagination from '@/components/ui/Pagination.svelte';

    const primaryColor = $derived(page.props.theme?.primary_color || '#0c4cb4');
    const secondaryColor = $derived(page.props.theme?.secondary_color || '#fa7315');
    const storeName = $derived((page.props as any).settings?.store_name || 'Bizmate');

    let {
        abandonedCarts = { data: [], links: [], total: 0, current_page: 1, last_page: 1, per_page: 15 },
        metrics = {
            total_users: 0,
            total_items: 0,
            total_value: 0
        },
        filters = { search: '', per_page: 15 }
    } = $props();

    // svelte-ignore state_referenced_locally
    let searchQuery = $state(filters.search || '');
    // svelte-ignore state_referenced_locally
    let perPageFilter = $state(filters.per_page || 15);

    function applyFilter() {
        router.get(
            '/admin/reports/abandoned-carts',
            {
                search: searchQuery,
                per_page: perPageFilter
            },
            {
                preserveState: true,
                preserveScroll: true
            }
        );
    }

    let searchTimeout: ReturnType<typeof setTimeout>;
    function handleSearchInput() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(applyFilter, 300);
    }

    function resetFilter() {
        searchQuery = '';
        perPageFilter = 15;
        applyFilter();
    }

    function printReport() {
        window.print();
    }

    function exportToCSV() {
        const headers = [
            'Nama Pembeli',
            'Email Pembeli',
            'Produk',
            'SKU',
            'Varian',
            'Kuantitas',
            'Harga Satuan',
            'Subtotal',
            'Aktif Terakhir'
        ];
        const csvRows = [headers.join(',')];

        abandonedCarts.data.forEach((user: any) => {
            user.items.forEach((item: any) => {
                const row = [
                    `"${user.name.replace(/"/g, '""')}"`,
                    `"${user.email.replace(/"/g, '""')}"`,
                    `"${item.product_name.replace(/"/g, '""')}"`,
                    item.sku,
                    item.variant_name ? `"${item.variant_name.replace(/"/g, '""')}"` : '—',
                    item.quantity,
                    Math.round(item.price),
                    Math.round(item.subtotal),
                    item.last_active
                ];
                csvRows.push(row.join(','));
            });
        });

        const csvContent = 'data:text/csv;charset=utf-8,' + csvRows.join('\n');
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement('a');
        link.setAttribute('href', encodedUri);
        link.setAttribute('download', `laporan_keranjang_terbengkalai.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    function formatRupiah(value: number) {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0
        }).format(value);
    }
</script>

<svelte:head>
    <title>Laporan Keranjang Terbengkalai — {storeName}</title>
</svelte:head>

<AdminLayout>
    <main class="flex-grow p-4 sm:p-8 w-full max-w-[1600px] mx-auto space-y-6 print:p-0 print:bg-white">
        
        <!-- Print Header -->
        <div class="hidden print:block text-center space-y-1.5 mb-6">
            <h1 class="font-outfit font-black text-2xl text-slate-800 tracking-tight">{storeName}</h1>
            <h2 class="font-outfit font-bold text-lg text-slate-700">Laporan Keranjang Terbengkalai</h2>
            <p class="text-xs text-slate-500 font-medium">Dicetak pada: {new Date().toLocaleString('id-ID')}</p>
        </div>

        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 print:hidden">
            <div>
                <h1 class="font-outfit font-black text-2xl sm:text-3xl text-slate-800 tracking-tight">
                    Laporan Keranjang Terbengkalai
                </h1>
                <p class="text-sm text-slate-500 font-medium mt-1">
                    Daftar pembeli yang menyimpan barang di keranjang belanja tanpa menyelesaikan checkout (&gt;2 jam).
                </p>
            </div>

            <div class="flex items-center gap-3 shrink-0 w-full sm:w-auto">
                <button
                    onclick={printReport}
                    class="flex-grow sm:flex-grow-0 flex items-center justify-center gap-2 px-5 py-3 bg-white border border-slate-200 text-slate-700 font-bold rounded-2xl text-xs hover:bg-slate-50 transition duration-200 shadow-sm uppercase tracking-wider font-outfit shrink-0 cursor-pointer"
                >
                    <i class="ti ti-printer text-base"></i>
                    <span>Cetak PDF</span>
                </button>
                <button
                    onclick={exportToCSV}
                    class="flex-grow sm:flex-grow-0 flex items-center justify-center gap-2 px-5 py-3 bg-white border border-slate-200 text-slate-700 font-bold rounded-2xl text-xs hover:bg-slate-50 transition duration-200 shadow-sm uppercase tracking-wider font-outfit shrink-0 cursor-pointer"
                >
                    <i class="ti ti-download text-base"></i>
                    <span>Ekspor CSV</span>
                </button>
            </div>
        </div>

        <!-- Metrics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-6">
            <!-- Metric 1: Total Users -->
            <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm flex flex-col justify-between transition hover:shadow-md duration-200">
                <div>
                    <span class="w-8 h-8 rounded-lg flex items-center justify-center text-lg text-blue-600 bg-blue-50 mb-3">
                        <i class="ti ti-users"></i>
                    </span>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit">Jumlah Pelanggan</p>
                    <h3 class="font-outfit font-black text-xl sm:text-2xl text-slate-800 mt-1">{metrics.total_users} Orang</h3>
                </div>
            </div>

            <!-- Metric 2: Total Items -->
            <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm flex flex-col justify-between transition hover:shadow-md duration-200">
                <div>
                    <span class="w-8 h-8 rounded-lg flex items-center justify-center text-lg text-slate-600 bg-slate-50 mb-3">
                        <i class="ti ti-shopping-cart"></i>
                    </span>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit">Total Item Tertinggal</p>
                    <h3 class="font-outfit font-black text-xl sm:text-2xl text-slate-800 mt-1">{metrics.total_items} Pcs</h3>
                </div>
            </div>

            <!-- Metric 3: Total Potential Revenue -->
            <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm flex flex-col justify-between transition hover:shadow-md duration-200">
                <div>
                    <span class="w-8 h-8 rounded-lg flex items-center justify-center text-lg text-rose-600 bg-rose-50 mb-3">
                        <i class="ti ti-coin"></i>
                    </span>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit">Potensi Omset Hilang</p>
                    <h3 class="font-outfit font-black text-xl sm:text-2xl text-rose-600 mt-1">{formatRupiah(metrics.total_value)}</h3>
                </div>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="bg-white rounded-3xl border border-slate-200 p-5 sm:p-6 shadow-sm space-y-4 print:hidden">
            <!-- Advanced Filters Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end pt-2">
                <!-- Search Input -->
                <div class="sm:col-span-8 space-y-1.5">
                    <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider" for="search-input">Cari Pelanggan</label>
                    <input
                        type="text"
                        id="search-input"
                        bind:value={searchQuery}
                        oninput={handleSearchInput}
                        placeholder="Cari nama atau email pembeli..."
                        class="w-full bg-slate-50 border border-slate-200 text-slate-755 text-xs font-semibold rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-blueRoyal/20 focus:border-brand-blueRoyal transition"
                    />
                </div>

                <!-- Per Page Filter -->
                <div class="sm:col-span-4 space-y-1.5">
                    <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider" for="per-page-select">Per Halaman</label>
                    <select
                        id="per-page-select"
                        bind:value={perPageFilter}
                        onchange={applyFilter}
                        class="w-full bg-slate-50 border border-slate-200 text-slate-755 text-xs font-semibold rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-blueRoyal/20 focus:border-brand-blueRoyal transition cursor-pointer"
                    >
                        <option value={10}>10 Baris</option>
                        <option value={15}>15 Baris</option>
                        <option value={30}>30 Baris</option>
                        <option value={50}>50 Baris</option>
                    </select>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row justify-end items-center gap-2 pt-2 border-t border-slate-50">
                <button
                    onclick={resetFilter}
                    class="w-full sm:w-auto px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition duration-200 uppercase tracking-wider font-outfit shrink-0 cursor-pointer"
                >
                    Reset Filter
                </button>
                <button
                    onclick={applyFilter}
                    class="w-full sm:w-auto px-6 py-2.5 bg-brand-blueRoyal hover:bg-blue-800 text-white font-bold rounded-xl text-xs transition duration-200 shadow-md shadow-brand-blueRoyal/10 uppercase tracking-wider font-outfit shrink-0 cursor-pointer"
                >
                    Terapkan Filter
                </button>
            </div>
        </div>

        <!-- Report Table Card -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50/50 text-[11px] font-bold uppercase tracking-wider text-slate-500 font-outfit">
                            <th class="py-4 px-6">Pelanggan</th>
                            <th class="py-4 px-6">Item di Keranjang</th>
                            <th class="py-4 px-6 text-center">Total Qty</th>
                            <th class="py-4 px-6 text-right">Total Nilai</th>
                            <th class="py-4 px-6 text-right">Aktif Terakhir</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700 text-sm">
                        {#each abandonedCarts.data as user}
                            <tr class="hover:bg-slate-50/30 transition duration-150">
                                <td class="py-4 px-6">
                                    <p class="font-bold text-slate-800">{user.name}</p>
                                    <p class="text-xs text-slate-400 font-medium mt-0.5">{user.email}</p>
                                </td>
                                <td class="py-4 px-6 min-w-[280px]">
                                    <div class="space-y-1.5">
                                        {#each user.items as item}
                                            <div class="flex items-start justify-between text-xs gap-3">
                                                <span class="font-medium text-slate-600 truncate max-w-[240px]">
                                                    {item.product_name}
                                                    {#if item.variant_name}
                                                        <span class="text-slate-400">({item.variant_name})</span>
                                                    {/if}
                                                </span>
                                                <span class="text-slate-400 shrink-0 font-mono font-bold">
                                                    {item.quantity}x @ {formatRupiah(item.price)}
                                                </span>
                                            </div>
                                        {/each}
                                    </div>
                                </td>
                                <td class="py-4 px-6 text-center font-mono font-bold text-slate-600">
                                    {user.total_items}
                                </td>
                                <td class="py-4 px-6 text-right font-black text-slate-800 font-mono">
                                    {formatRupiah(user.total_value)}
                                </td>
                                <td class="py-4 px-6 text-right text-xs text-slate-400 font-medium font-mono">
                                    {user.last_active}
                                </td>
                            </tr>
                        {:else}
                            <tr>
                                <td colspan="5" class="py-12 text-center text-slate-400 font-medium">
                                    <i class="ti ti-shopping-cart-x text-4xl text-slate-300 block mb-2"></i>
                                    Tidak ditemukan keranjang belanja terbengkalai.
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>

            <!-- Pagination (Hidden on print) -->
            {#if abandonedCarts.total > 0}
                <div class="border-t border-slate-100 p-6 print:hidden">
                    <Pagination links={abandonedCarts.links} />
                </div>
            {/if}
        </div>

    </main>
</AdminLayout>
