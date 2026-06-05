<script lang="ts">
    import { page, router } from '@inertiajs/svelte';
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import Pagination from '@/components/ui/Pagination.svelte';

    const primaryColor = $derived(page.props.theme?.primary_color || '#0c4cb4');
    const secondaryColor = $derived(
        page.props.theme?.secondary_color || '#fa7315',
    );

    let {
        stocks = { data: [], links: [], total: 0 },
        metrics = {
            total_sku: 0,
            total_stock: 0,
            total_asset_value: 0,
            total_retail_value: 0,
            potential_profit: 0,
            low_stock_count: 0,
        },
        filters = { search: '', status: '' },
    } = $props();

    // svelte-ignore state_referenced_locally
    let searchQuery = $state(filters.search || '');
    // svelte-ignore state_referenced_locally
    let selectedStatus = $state(filters.status || '');

    function applyFilter() {
        router.get(
            '/admin/reports/stocks',
            {
                search: searchQuery,
                status: selectedStatus,
            },
            {
                preserveState: true,
            },
        );
    }

    function resetFilter() {
        searchQuery = '';
        selectedStatus = '';
        applyFilter();
    }

    function exportToCSV() {
        const headers = [
            'Nama Produk / Varian',
            'SKU',
            'Kategori',
            'HPP / Harga Beli',
            'Harga Jual',
            'Sisa Stok',
            'Valuasi Aset',
        ];
        const csvRows = [headers.join(',')];

        stocks.data.forEach((s: any) => {
            const row = [
                `"${s.name.replace(/"/g, '""')}"`,
                s.sku,
                s.category_name,
                Math.round(s.cost),
                Math.round(s.price),
                s.is_unlimited ? 'Tanpa Batas' : s.stock,
                s.is_unlimited ? 0 : Math.round(s.stock * s.cost),
            ];
            csvRows.push(row.join(','));
        });

        const csvContent = 'data:text/csv;charset=utf-8,' + csvRows.join('\n');
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement('a');
        link.setAttribute('href', encodedUri);
        link.setAttribute('download', `laporan_stok_dan_inventaris.csv`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    function formatRupiah(value: number) {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
        }).format(value);
    }
</script>

<svelte:head>
    <title>Laporan Stok & Inventaris</title>
</svelte:head>

<AdminLayout>
    <main class="flex-grow p-4 sm:p-8 w-full max-w-[1600px] mx-auto space-y-6">
        <!-- Page Header -->
        <div
            class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4"
        >
            <div>
                <h1
                    class="font-outfit font-black text-2xl sm:text-3xl text-slate-800 tracking-tight"
                >
                    Laporan Stok & Inventaris (Valuasi)
                </h1>
                <p class="text-sm text-slate-500 font-medium mt-1">
                    Valuasi total modal aset stok tersimpan, estimasi profit
                    kotor, dan peringatan limit stok kritis.
                </p>
            </div>

            <button
                onclick={exportToCSV}
                class="flex items-center justify-center gap-2 px-5 py-3 bg-white border border-slate-200 text-slate-700 font-bold rounded-2xl text-xs hover:bg-slate-50 transition duration-200 shadow-sm uppercase tracking-wider font-outfit shrink-0"
            >
                <i class="ti ti-download text-base"></i>
                <span>Ekspor CSV</span>
            </button>
        </div>

        <!-- Filter Card -->
        <div
            class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-4"
        >
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label
                        for="search"
                        class="text-xs font-bold text-slate-500 uppercase tracking-wider"
                        >Cari Nama / SKU</label
                    >
                    <input
                        type="text"
                        id="search"
                        bind:value={searchQuery}
                        placeholder="Ketik nama atau SKU..."
                        class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-sm font-semibold rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-blueRoyal/20 focus:border-brand-blueRoyal transition"
                    />
                </div>
                <div class="space-y-1.5">
                    <label
                        for="status"
                        class="text-xs font-bold text-slate-500 uppercase tracking-wider"
                        >Status Persediaan</label
                    >
                    <select
                        id="status"
                        bind:value={selectedStatus}
                        class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-sm font-semibold rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-blueRoyal/20 focus:border-brand-blueRoyal transition cursor-pointer"
                    >
                        <option value="">Semua Status</option>
                        <option value="aman">Persediaan Aman / Unlimited</option
                        >
                        <option value="kritis"
                            >Stok Kritis (stok &lt;= min_stock)</option
                        >
                        <option value="habis">Stok Habis (stok &lt;= 0)</option>
                    </select>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
                <button
                    onclick={resetFilter}
                    class="px-5 py-3 border border-slate-200 hover:bg-slate-50 text-slate-500 font-bold rounded-2xl text-xs transition duration-200 uppercase tracking-wider font-outfit"
                >
                    Reset Filter
                </button>
                <button
                    onclick={applyFilter}
                    class="px-6 py-3 bg-brand-blueRoyal hover:bg-blue-800 text-white font-bold rounded-2xl text-xs transition duration-200 shadow-lg shadow-brand-blueRoyal/20 uppercase tracking-wider font-outfit"
                >
                    Cari & Filter
                </button>
            </div>
        </div>

        <!-- KPI Cards Grid -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- Card 1: Valuasi Aset HPP -->
            <div
                class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm flex flex-col justify-between"
            >
                <div>
                    <span
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-lg text-blue-600 bg-blue-50 mb-3"
                    >
                        <i class="ti ti-building-store"></i>
                    </span>
                    <p
                        class="text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit"
                    >
                        Valuasi Modal Aset (HPP)
                    </p>
                    <h3
                        class="font-outfit font-black text-xl sm:text-2xl text-slate-800 mt-1"
                    >
                        {formatRupiah(metrics.total_asset_value)}
                    </h3>
                    <p class="text-[11px] text-slate-400 mt-1">
                        Total uang terinvestasi di stok fisik.
                    </p>
                </div>
            </div>

            <!-- Card 2: Potensi Nilai Retail -->
            <div
                class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm flex flex-col justify-between"
            >
                <div>
                    <span
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-lg text-emerald-600 bg-emerald-50 mb-3"
                    >
                        <i class="ti ti-tags"></i>
                    </span>
                    <p
                        class="text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit"
                    >
                        Valuasi Nilai Ritel
                    </p>
                    <h3
                        class="font-outfit font-black text-xl sm:text-2xl text-slate-800 mt-1"
                    >
                        {formatRupiah(metrics.total_retail_value)}
                    </h3>
                    <p class="text-[11px] text-slate-400 mt-1">
                        Nilai jual jika seluruh stok terjual.
                    </p>
                </div>
            </div>

            <!-- Card 3: Estimasi Profit Kotor Stok -->
            <div
                class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm flex flex-col justify-between"
            >
                <div>
                    <span
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-lg text-purple-600 bg-purple-50 mb-3"
                    >
                        <i class="ti ti-trending-up"></i>
                    </span>
                    <p
                        class="text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit"
                    >
                        Potensi Margin Profit
                    </p>
                    <h3
                        class="font-outfit font-black text-xl sm:text-2xl text-slate-800 mt-1"
                    >
                        {formatRupiah(metrics.potential_profit)}
                    </h3>
                    <p class="text-[11px] text-slate-400 mt-1">
                        Nilai Ritel dikurangi Modal HPP.
                    </p>
                </div>
            </div>

            <!-- Card 4: Limit Stok Kritis -->
            <div
                class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm flex flex-col justify-between"
            >
                <div>
                    <span
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-lg {metrics.low_stock_count >
                        0
                            ? 'text-rose-600 bg-rose-50'
                            : 'text-slate-600 bg-slate-50'} mb-3"
                    >
                        <i class="ti ti-alert-triangle"></i>
                    </span>
                    <p
                        class="text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit"
                    >
                        Item Stok Kritis
                    </p>
                    <h3
                        class="font-outfit font-black text-xl sm:text-2xl mt-1 {metrics.low_stock_count >
                        0
                            ? 'text-rose-600'
                            : 'text-slate-800'}"
                    >
                        {metrics.low_stock_count} SKU
                    </h3>
                    <p class="text-[11px] text-slate-400 mt-1">
                        SKU dengan sisa stok di bawah limit.
                    </p>
                </div>
            </div>
        </div>

        <!-- Inventory Table Card -->
        <div
            class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-sm"
        >
            <div class="p-6 border-b border-slate-100">
                <h3 class="font-outfit font-black text-lg text-slate-800">
                    Rincian Stok Barang & Varian
                </h3>
                <p class="text-xs text-slate-500 font-medium">
                    Daftar inventori fisik lengkap, harga pokok, harga jual, dan
                    status persediaan.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[900px]">
                    <thead>
                        <tr
                            class="bg-slate-50/50 text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit border-b border-slate-100"
                        >
                            <th class="py-4 px-6">Nama Produk / Varian</th>
                            <th class="py-4 px-6">SKU</th>
                            <th class="py-4 px-6">Kategori</th>
                            <th class="py-4 px-6 text-right"
                                >Harga Pokok (HPP)</th
                            >
                            <th class="py-4 px-6 text-right"
                                >Harga Ritel Jual</th
                            >
                            <th class="py-4 px-6 text-center">Sisa Stok</th>
                            <th class="py-4 px-6 text-right">Nilai Aset Stok</th
                            >
                            <th class="py-4 px-6 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-slate-100 text-slate-700 text-sm font-medium"
                    >
                        {#if stocks.data.length === 0}
                            <tr>
                                <td
                                    colspan="8"
                                    class="py-12 text-center text-slate-400 font-bold font-outfit"
                                >
                                    <i
                                        class="ti ti-archive text-3xl block mb-2 text-slate-300"
                                    ></i>
                                    Tidak ada data persediaan stok.
                                </td>
                            </tr>
                        {:else}
                            {#each stocks.data as item}
                                {@const isCritical =
                                    !item.is_unlimited &&
                                    item.stock <= item.min_stock &&
                                    item.stock > 0}
                                {@const isOut =
                                    !item.is_unlimited && item.stock <= 0}
                                {@const isUnlimited = item.is_unlimited}

                                <tr class="hover:bg-slate-50/50 transition">
                                    <td
                                        class="py-4 px-6 font-bold text-slate-800"
                                        >{item.name}</td
                                    >
                                    <td
                                        class="py-4 px-6 text-slate-500 text-xs font-semibold"
                                        >{item.sku}</td
                                    >
                                    <td class="py-4 px-6">
                                        <span
                                            class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-slate-100 text-slate-600"
                                        >
                                            {item.category_name}
                                        </span>
                                    </td>
                                    <td
                                        class="py-4 px-6 text-right text-slate-500 font-semibold"
                                        >{formatRupiah(item.cost)}</td
                                    >
                                    <td
                                        class="py-4 px-6 text-right text-slate-600 font-semibold"
                                        >{formatRupiah(item.price)}</td
                                    >
                                    <td
                                        class="py-4 px-6 text-center font-bold text-slate-700"
                                    >
                                        {#if isUnlimited}
                                            <span
                                                class="text-xs text-slate-400 font-bold"
                                                >♾️ Tanpa Batas</span
                                            >
                                        {:else}
                                            {item.stock} pcs
                                        {/if}
                                    </td>
                                    <td
                                        class="py-4 px-6 text-right font-black text-slate-800"
                                    >
                                        {#if isUnlimited}
                                            Rp 0
                                        {:else}
                                            {formatRupiah(
                                                item.stock * item.cost,
                                            )}
                                        {/if}
                                    </td>
                                    <td class="py-4 px-6 text-center">
                                        {#if isUnlimited}
                                            <span
                                                class="px-2.5 py-1 rounded-lg text-[9px] font-black uppercase tracking-wider bg-sky-50 text-sky-600 border border-sky-200/50"
                                            >
                                                Aman
                                            </span>
                                        {:else if isOut}
                                            <span
                                                class="px-2.5 py-1 rounded-lg text-[9px] font-black uppercase tracking-wider bg-rose-50 text-rose-600 border border-rose-200/50"
                                            >
                                                Habis
                                            </span>
                                        {:else if isCritical}
                                            <span
                                                class="px-2.5 py-1 rounded-lg text-[9px] font-black uppercase tracking-wider bg-amber-50 text-amber-600 border border-amber-200/50"
                                            >
                                                Kritis
                                            </span>
                                        {:else}
                                            <span
                                                class="px-2.5 py-1 rounded-lg text-[9px] font-black uppercase tracking-wider bg-emerald-50 text-emerald-600 border border-emerald-200/50"
                                            >
                                                Aman
                                            </span>
                                        {/if}
                                    </td>
                                </tr>
                            {/each}
                        {/if}
                    </tbody>
                </table>
            </div>

            <Pagination paginator={stocks} />
        </div>
    </main>
</AdminLayout>
