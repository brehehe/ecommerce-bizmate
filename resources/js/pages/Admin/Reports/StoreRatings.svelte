<script lang="ts">
    import { page, router } from '@inertiajs/svelte';
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import Pagination from '@/components/ui/Pagination.svelte';

    interface ReviewItem {
        id: string;
        transaction_id: string;
        customer_name: string;
        customer_phone?: string | null;
        rating: number;
        comment?: string | null;
        created_at: string;
        transaction?: {
            id: string;
            transaction_number: string;
            created_at: string;
            status: string;
        } | null;
        resto_profile?: {
            id: string;
            resto_name: string;
            culinary_category?: string | null;
        } | null;
        seller?: {
            id: string;
            name: string;
            store_name?: string | null;
        } | null;
    }

    interface ReviewsPagination {
        data: ReviewItem[];
        links: any[];
        total: number;
        current_page: number;
        last_page: number;
        from: number;
        to: number;
    }

    let {
        reviews = { data: [], links: [], total: 0 } as unknown as ReviewsPagination,
        summary = {
            total: 0,
            avg_rating: null,
            five_star_count: 0,
            with_comment_count: 0,
            satisfaction_rate: 100,
        },
        ratingDistribution = { 5: 0, 4: 0, 3: 0, 2: 0, 1: 0 } as Record<number, number>,
        filters = {
            date_from: '',
            date_to: '',
            preset: 'bulanan',
            search: '',
            rating: '',
        },
        isFoodPick = false,
    } = $props();

    // svelte-ignore state_referenced_locally
    let dateFrom = $state(filters.date_from);
    // svelte-ignore state_referenced_locally
    let dateTo = $state(filters.date_to);
    // svelte-ignore state_referenced_locally
    let activePreset = $state(filters.preset || 'bulanan');
    // svelte-ignore state_referenced_locally
    let searchQuery = $state(filters.search || '');
    // svelte-ignore state_referenced_locally
    let ratingFilter = $state(filters.rating || '');

    function formatDateLocal(date: Date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function selectPreset(preset: string) {
        activePreset = preset;
        const now = new Date();
        let fromDate = new Date();

        if (preset === 'harian') {
            fromDate = now;
        } else if (preset === 'mingguan') {
            fromDate.setDate(now.getDate() - 6);
        } else if (preset === 'bulanan') {
            fromDate.setDate(now.getDate() - 29);
        } else if (preset === 'tahunan') {
            fromDate = new Date(now.getFullYear(), 0, 1);
        }

        dateFrom = formatDateLocal(fromDate);
        dateTo = formatDateLocal(now);

        applyFilter();
    }

    function applyFilter() {
        router.get(
            '/admin/reports/store-ratings',
            {
                date_from: dateFrom,
                date_to: dateTo,
                preset: activePreset,
                search: searchQuery,
                rating: ratingFilter,
            },
            { preserveState: true, replace: true },
        );
    }

    function resetFilter() {
        searchQuery = '';
        ratingFilter = '';
        selectPreset('bulanan');
    }

    function formatDateDisplay(dateStr: string) {
        if (!dateStr) return '-';
        const d = new Date(dateStr);
        return d.toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    function getPercentage(count: number, total: number) {
        if (!total || total === 0) return 0;
        return Math.round((count / total) * 100);
    }
</script>

<svelte:head>
    <title>Laporan {isFoodPick ? 'Rating Resto' : 'Rating Toko'} - Bizmate</title>
</svelte:head>

<AdminLayout>
    <div class="space-y-6">
        <!-- ── Header ─────────────────────────────────────────────── -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">
                    Laporan {isFoodPick ? 'Rating & Ulasan Resto' : 'Rating & Ulasan Toko'}
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    Pantau performa kepuasan pelanggan dan ulasan pesanan yang masuk secara real-time.
                </p>
            </div>

            <!-- Date Presets -->
            <div class="inline-flex items-center rounded-lg border border-slate-200 bg-white p-1 shadow-xs">
                {#each [
                    { id: 'harian', label: 'Hari Ini' },
                    { id: 'mingguan', label: '7 Hari' },
                    { id: 'bulanan', label: '30 Hari' },
                    { id: 'tahunan', label: 'Tahun Ini' },
                ] as p}
                    <button
                        type="button"
                        onclick={() => selectPreset(p.id)}
                        class="rounded-md px-3 py-1.5 text-xs font-semibold transition cursor-pointer {activePreset === p.id
                            ? 'bg-blue-600 text-white shadow-2xs'
                            : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50'}"
                    >
                        {p.label}
                    </button>
                {/each}
            </div>
        </div>

        <!-- ── Metrics Overview Cards ─────────────────────────────── -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Card 1: Rata-Rata Rating -->
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-slate-500">Rata-Rata Rating</span>
                    <span class="p-2 rounded-lg bg-amber-50 text-amber-600">
                        <i class="ti ti-star-filled text-base"></i>
                    </span>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <span class="text-3xl font-black tracking-tight text-slate-900">
                        {summary.avg_rating !== null ? summary.avg_rating : '-'}
                    </span>
                    <span class="text-sm font-semibold text-slate-400">/ 5.0</span>
                </div>
                <div class="mt-2 flex items-center gap-1 text-amber-500 text-xs">
                    {#each Array(5) as _, idx}
                        <i
                            class="ti ti-star-filled {summary.avg_rating && summary.avg_rating >= idx + 1
                                ? 'text-amber-400'
                                : summary.avg_rating && summary.avg_rating >= idx + 0.5
                                  ? 'text-amber-300'
                                  : 'text-slate-200'}"
                        ></i>
                    {/each}
                    <span class="ml-1 text-[11px] text-slate-500">
                        ({summary.total} penilaian)
                    </span>
                </div>
            </div>

            <!-- Card 2: Total Ulasan -->
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-slate-500">Total Penilaian</span>
                    <span class="p-2 rounded-lg bg-blue-50 text-blue-600">
                        <i class="ti ti-message-2 text-base"></i>
                    </span>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <span class="text-3xl font-black tracking-tight text-slate-900">
                        {summary.total}
                    </span>
                    <span class="text-xs text-slate-500">ulasan</span>
                </div>
                <div class="mt-2 text-xs text-slate-500 flex items-center gap-1">
                    <i class="ti ti-notes text-slate-400"></i>
                    <span>{summary.with_comment_count} ulasan disertai komentar</span>
                </div>
            </div>

            <!-- Card 3: Bintang 5 -->
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-slate-500">Ulasan Bintang 5</span>
                    <span class="p-2 rounded-lg bg-emerald-50 text-emerald-600">
                        <i class="ti ti-thumb-up text-base"></i>
                    </span>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <span class="text-3xl font-black tracking-tight text-emerald-600">
                        {summary.five_star_count}
                    </span>
                    <span class="text-xs text-slate-500">
                        ({getPercentage(summary.five_star_count, summary.total)}%)
                    </span>
                </div>
                <div class="mt-2 text-xs text-slate-500">
                    Rating tertinggi dari pelanggan puas
                </div>
            </div>

            <!-- Card 4: Kepuasan Pelanggan -->
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs flex flex-col justify-between">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-slate-500">Tingkat Kepuasan</span>
                    <span class="p-2 rounded-lg bg-indigo-50 text-indigo-600">
                        <i class="ti ti-chart-pie text-base"></i>
                    </span>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <span class="text-3xl font-black tracking-tight text-indigo-600">
                        {summary.satisfaction_rate}%
                    </span>
                </div>
                <div class="mt-2 text-xs text-slate-500">
                    Bintang 4 & 5 dari seluruh penilaian
                </div>
            </div>
        </div>

        <!-- ── Rating Breakdown & Distribution ─────────────────────── -->
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-xs">
            <h2 class="text-sm font-bold text-slate-900 mb-4 flex items-center gap-2">
                <i class="ti ti-chart-bar text-base text-slate-500"></i>
                Distribusi Bintang Penilaian
            </h2>
            <div class="space-y-2.5 max-w-2xl">
                {#each [5, 4, 3, 2, 1] as star}
                    {@const count = ratingDistribution[star] || 0}
                    {@const pct = getPercentage(count, summary.total)}
                    <button
                        type="button"
                        onclick={() => {
                            ratingFilter = ratingFilter === String(star) ? '' : String(star);
                            applyFilter();
                        }}
                        class="w-full flex items-center gap-3 text-xs group hover:bg-slate-50 p-1.5 rounded-lg transition cursor-pointer {ratingFilter === String(star) ? 'bg-amber-50/60 font-semibold' : ''}"
                    >
                        <span class="w-16 flex items-center gap-1 font-semibold text-slate-700 shrink-0">
                            <span>{star} Bintang</span>
                            <i class="ti ti-star-filled text-amber-400 text-xs"></i>
                        </span>

                        <div class="flex-1 h-3 rounded-full bg-slate-100 overflow-hidden relative">
                            <div
                                class="h-full rounded-full transition-all duration-300 {star >= 4
                                    ? 'bg-amber-400'
                                    : star === 3
                                      ? 'bg-yellow-400'
                                      : 'bg-rose-400'}"
                                style="width: {pct}%;"
                            ></div>
                        </div>

                        <span class="w-20 text-right text-slate-500 shrink-0 font-mono">
                            {count} ({pct}%)
                        </span>
                    </button>
                {/each}
            </div>
        </div>

        <!-- ── Filter Toolbar ──────────────────────────────────────── -->
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-xs">
            <div class="flex flex-col md:flex-row items-stretch md:items-center gap-3">
                <!-- Search Input -->
                <div class="relative flex-1">
                    <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                    <input
                        type="text"
                        bind:value={searchQuery}
                        onkeydown={(e) => e.key === 'Enter' && applyFilter()}
                        placeholder="Cari pelanggan, ulasan, nomor pesanan..."
                        class="w-full pl-9 pr-3 py-2 text-xs rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                    />
                </div>

                <!-- Rating Filter -->
                <div class="w-full md:w-44">
                    <select
                        bind:value={ratingFilter}
                        onchange={applyFilter}
                        class="w-full px-3 py-2 text-xs rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white transition"
                    >
                        <option value="">Semua Bintang</option>
                        <option value="5">⭐⭐⭐⭐⭐ (5 Bintang)</option>
                        <option value="4">⭐⭐⭐⭐ (4 Bintang)</option>
                        <option value="3">⭐⭐⭐ (3 Bintang)</option>
                        <option value="2">⭐⭐ (2 Bintang)</option>
                        <option value="1">⭐ (1 Bintang)</option>
                    </select>
                </div>

                <!-- Date Range Inputs -->
                <div class="flex items-center gap-2">
                    <input
                        type="date"
                        bind:value={dateFrom}
                        onchange={() => { activePreset = 'custom'; applyFilter(); }}
                        class="px-2.5 py-2 text-xs rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
                        aria-label="Dari tanggal"
                    />
                    <span class="text-xs text-slate-400">s/d</span>
                    <input
                        type="date"
                        bind:value={dateTo}
                        onchange={() => { activePreset = 'custom'; applyFilter(); }}
                        class="px-2.5 py-2 text-xs rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
                        aria-label="Sampai tanggal"
                    />
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-2 shrink-0">
                    <button
                        type="button"
                        onclick={applyFilter}
                        class="px-3.5 py-2 text-xs font-semibold rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition cursor-pointer shadow-2xs"
                    >
                        Filter
                    </button>
                    {#if searchQuery || ratingFilter || activePreset !== 'bulanan'}
                        <button
                            type="button"
                            onclick={resetFilter}
                            class="px-3 py-2 text-xs font-semibold rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 transition cursor-pointer"
                        >
                            Reset
                        </button>
                    {/if}
                </div>
            </div>
        </div>

        <!-- ── Reviews Table ───────────────────────────────────────── -->
        <div class="rounded-xl border border-slate-200 bg-white overflow-hidden shadow-xs">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-semibold uppercase tracking-wider text-[11px]">
                        <tr>
                            <th class="px-4 py-3">Tanggal</th>
                            <th class="px-4 py-3">No. Pesanan</th>
                            <th class="px-4 py-3">Pelanggan</th>
                            <th class="px-4 py-3">{isFoodPick ? 'Resto' : 'Toko'}</th>
                            <th class="px-4 py-3">Rating</th>
                            <th class="px-4 py-3">Ulasan / Komentar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        {#if reviews.data && reviews.data.length > 0}
                            {#each reviews.data as rev (rev.id)}
                                <tr class="hover:bg-slate-50/70 transition">
                                    <!-- Tanggal -->
                                    <td class="px-4 py-3.5 whitespace-nowrap text-slate-500">
                                        {formatDateDisplay(rev.created_at)}
                                    </td>

                                    <!-- No. Pesanan -->
                                    <td class="px-4 py-3.5 whitespace-nowrap">
                                        {#if rev.transaction}
                                            <a
                                                href={`/admin/transactions/${rev.transaction.id}`}
                                                class="font-mono font-semibold text-blue-600 hover:text-blue-800 hover:underline"
                                            >
                                                #{rev.transaction.transaction_number}
                                            </a>
                                        {:else}
                                            <span class="text-slate-400 font-mono">-</span>
                                        {/if}
                                    </td>

                                    <!-- Pelanggan -->
                                    <td class="px-4 py-3.5 whitespace-nowrap">
                                        <div class="font-medium text-slate-900">
                                            {rev.customer_name}
                                        </div>
                                        {#if rev.customer_phone}
                                            <div class="text-[11px] text-slate-400">
                                                {rev.customer_phone}
                                            </div>
                                        {/if}
                                    </td>

                                    <!-- Toko / Resto -->
                                    <td class="px-4 py-3.5 whitespace-nowrap">
                                        <div class="font-medium text-slate-900">
                                            {rev.resto_profile?.resto_name || rev.seller?.store_name || rev.seller?.name || '-'}
                                        </div>
                                        {#if rev.resto_profile?.culinary_category}
                                            <span class="text-[10px] text-slate-400">
                                                {rev.resto_profile.culinary_category}
                                            </span>
                                        {/if}
                                    </td>

                                    <!-- Rating -->
                                    <td class="px-4 py-3.5 whitespace-nowrap">
                                        <div class="inline-flex items-center gap-1.5 px-2 py-1 rounded-md bg-amber-50 border border-amber-200/60 text-amber-700 font-bold">
                                            <i class="ti ti-star-filled text-amber-500 text-xs"></i>
                                            <span>{rev.rating}.0</span>
                                        </div>
                                    </td>

                                    <!-- Komentar / Ulasan -->
                                    <td class="px-4 py-3.5">
                                        {#if rev.comment}
                                            <p class="text-slate-700 line-clamp-3 leading-relaxed max-w-md">
                                                "{rev.comment}"
                                            </p>
                                        {:else}
                                            <span class="text-slate-400 italic text-[11px]">
                                                (Tidak ada ulasan tertulis)
                                            </span>
                                        {/if}
                                    </td>
                                </tr>
                            {/each}
                        {:else}
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <div class="max-w-xs mx-auto text-center space-y-2">
                                        <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mx-auto text-xl">
                                            <i class="ti ti-star-off"></i>
                                        </div>
                                        <p class="font-semibold text-slate-700 text-sm">Belum Ada Ulasan</p>
                                        <p class="text-xs text-slate-400 leading-relaxed">
                                            Belum ada ulasan rating toko yang cocok dengan filter tanggal atau kriteria pencarian ini.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        {/if}
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            {#if reviews.links && reviews.links.length > 3}
                <div class="border-t border-slate-100 p-4">
                    <Pagination data={reviews} itemLabel="Ulasan" />
                </div>
            {/if}
        </div>
    </div>
</AdminLayout>
