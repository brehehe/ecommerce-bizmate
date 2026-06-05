<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import Pagination from '@/components/ui/Pagination.svelte';

    let {
        reviews = { data: [], links: [], total: 0 },
        summary = { total: 0, reported: 0, anonymous: 0, avg_rating: 0 },
        ratingDistribution = {} as Record<number, number>,
        filters = {
            date_from: '',
            date_to: '',
            search: '',
            rating: '',
            reported_only: false,
            anonymous_only: false,
        },
    } = $props();

    // svelte-ignore state_referenced_locally
    let dateFrom = $state(filters.date_from);
    // svelte-ignore state_referenced_locally
    let dateTo = $state(filters.date_to);
    // svelte-ignore state_referenced_locally
    let searchQuery = $state(filters.search || '');
    // svelte-ignore state_referenced_locally
    let ratingFilter = $state(filters.rating || '');
    // svelte-ignore state_referenced_locally
    let reportedOnly = $state(filters.reported_only || false);
    // svelte-ignore state_referenced_locally
    let anonymousOnly = $state(filters.anonymous_only || false);

    function applyFilter() {
        router.get(
            '/admin/reports/reviews',
            {
                date_from: dateFrom,
                date_to: dateTo,
                search: searchQuery,
                rating: ratingFilter,
                reported_only: reportedOnly ? '1' : '',
                anonymous_only: anonymousOnly ? '1' : '',
            },
            { preserveState: true },
        );
    }

    function resetFilter() {
        dateFrom = filters.date_from;
        dateTo = filters.date_to;
        searchQuery = '';
        ratingFilter = '';
        reportedOnly = false;
        anonymousOnly = false;
        applyFilter();
    }

    function formatDate(dateStr: string) {
        if (!dateStr) return '-';
        return new Date(dateStr).toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        });
    }

    const RATING_LABELS: Record<number, string> = {
        1: 'Buruk',
        2: 'Kurang',
        3: 'Cukup',
        4: 'Bagus',
        5: 'Sempurna',
    };

    const RATING_COLORS: Record<number, string> = {
        1: '#ef4444',
        2: '#f97316',
        3: '#eab308',
        4: '#84cc16',
        5: '#22c55e',
    };

    function ratingLabel(r: number): string {
        return RATING_LABELS[r] ?? '-';
    }

    const totalInDist = $derived(
        Object.values(ratingDistribution).reduce(
            (a: number, b) => a + (b as number),
            0,
        ),
    );

    function exportToCSV() {
        const headers = [
            'ID',
            'Pelanggan',
            'Produk',
            'Rating',
            'Komentar',
            'Anonim',
            'Dilaporkan',
            'Alasan Laporan',
            'Tanggal',
        ];
        const rows = [headers.join(',')];

        (reviews.data as any[]).forEach((r: any) => {
            rows.push(
                [
                    r.id,
                    `"${(r.is_anonymous ? 'Anonim' : (r.user?.name ?? '-')).replace(/"/g, '""')}"`,
                    `"${(r.product?.name ?? '-').replace(/"/g, '""')}"`,
                    r.rating,
                    `"${(r.comment ?? '').replace(/"/g, '""')}"`,
                    r.is_anonymous ? 'Ya' : 'Tidak',
                    r.is_reported ? 'Ya' : 'Tidak',
                    `"${(r.report_reason ?? '').replace(/"/g, '""')}"`,
                    new Date(r.created_at).toISOString().split('T')[0],
                ].join(','),
            );
        });

        const csv = 'data:text/csv;charset=utf-8,' + rows.join('\n');
        const link = document.createElement('a');
        link.setAttribute('href', encodeURI(csv));
        link.setAttribute(
            'download',
            `laporan_ulasan_${dateFrom}_sd_${dateTo}.csv`,
        );
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>

<svelte:head>
    <title>Laporan Ulasan Produk</title>
</svelte:head>

<AdminLayout>
    <main class="flex-grow p-4 sm:p-8 w-full max-w-[1600px] mx-auto space-y-6">
        <!-- ── Page Header ─────────────────────────────────────── -->
        <div
            class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4"
        >
            <div>
                <h1
                    class="font-outfit font-black text-2xl sm:text-3xl text-slate-800 tracking-tight"
                >
                    Laporan Ulasan Produk
                </h1>
                <p class="text-sm text-slate-500 font-medium mt-1">
                    Pantau ulasan pelanggan, ulasan anonim, dan laporan
                    pelanggaran konten.
                </p>
            </div>
            <button
                onclick={exportToCSV}
                class="flex items-center gap-2 px-5 py-2.5 bg-white border border-slate-200 text-slate-700 font-bold rounded-2xl text-xs hover:bg-slate-50 transition shadow-sm uppercase tracking-wider font-outfit shrink-0"
            >
                <i class="ti ti-download text-base"></i>
                Ekspor CSV
            </button>
        </div>

        <!-- ── Filter Card ─────────────────────────────────────── -->
        <div
            class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-5"
        >
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="space-y-1.5">
                    <label
                        for="date_from"
                        class="text-[10px] font-bold text-slate-400 uppercase tracking-widest"
                    >
                        Tanggal Mulai
                    </label>
                    <input
                        type="date"
                        id="date_from"
                        bind:value={dateFrom}
                        class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-sm font-semibold rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-blueRoyal/20 focus:border-brand-blueRoyal transition"
                    />
                </div>
                <div class="space-y-1.5">
                    <label
                        for="date_to"
                        class="text-[10px] font-bold text-slate-400 uppercase tracking-widest"
                    >
                        Tanggal Selesai
                    </label>
                    <input
                        type="date"
                        id="date_to"
                        bind:value={dateTo}
                        class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-sm font-semibold rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-blueRoyal/20 focus:border-brand-blueRoyal transition"
                    />
                </div>
                <div class="space-y-1.5">
                    <label
                        for="search"
                        class="text-[10px] font-bold text-slate-400 uppercase tracking-widest"
                    >
                        Pencarian
                    </label>
                    <div class="relative">
                        <i
                            class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"
                        ></i>
                        <input
                            type="text"
                            id="search"
                            bind:value={searchQuery}
                            placeholder="Nama, produk, atau komentar..."
                            class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-sm font-semibold rounded-xl pl-9 pr-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-blueRoyal/20 focus:border-brand-blueRoyal transition"
                        />
                    </div>
                </div>
                <div class="space-y-1.5">
                    <label
                        for="rating"
                        class="text-[10px] font-bold text-slate-400 uppercase tracking-widest"
                    >
                        Rating
                    </label>
                    <select
                        id="rating"
                        bind:value={ratingFilter}
                        class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-sm font-semibold rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-blueRoyal/20 focus:border-brand-blueRoyal transition"
                    >
                        <option value="">Semua Rating</option>
                        <option value="5">★★★★★ — Sempurna</option>
                        <option value="4">★★★★☆ — Bagus</option>
                        <option value="3">★★★☆☆ — Cukup</option>
                        <option value="2">★★☆☆☆ — Kurang</option>
                        <option value="1">★☆☆☆☆ — Buruk</option>
                    </select>
                </div>
            </div>

            <!-- Quick toggles + action buttons -->
            <div
                class="flex flex-wrap items-center gap-3 pt-1 border-t border-slate-100"
            >
                <!-- svelte-ignore a11y_click_events_have_key_events -->
                <div
                    role="button"
                    tabindex="0"
                    class="flex items-center gap-2 px-3 py-1.5 rounded-xl border cursor-pointer select-none transition"
                    style="border-color:{reportedOnly
                        ? '#fca5a5'
                        : '#e2e8f0'}; background:{reportedOnly
                        ? '#fef2f2'
                        : 'transparent'};"
                    onclick={() => (reportedOnly = !reportedOnly)}
                    onkeydown={(e: KeyboardEvent) => {
                        if (e.key === 'Enter' || e.key === ' ')
                            reportedOnly = !reportedOnly;
                    }}
                >
                    <i
                        class="ti ti-flag text-xs"
                        style="color:{reportedOnly ? '#ef4444' : '#94a3b8'};"
                    ></i>
                    <span
                        class="text-xs font-semibold"
                        style="color:{reportedOnly ? '#ef4444' : '#64748b'};"
                    >
                        Hanya Dilaporkan
                    </span>
                    {#if reportedOnly}
                        <i class="ti ti-circle-check text-xs text-red-400"></i>
                    {/if}
                </div>

                <!-- svelte-ignore a11y_click_events_have_key_events -->
                <div
                    role="button"
                    tabindex="0"
                    class="flex items-center gap-2 px-3 py-1.5 rounded-xl border cursor-pointer select-none transition"
                    style="border-color:{anonymousOnly
                        ? '#a5b4fc'
                        : '#e2e8f0'}; background:{anonymousOnly
                        ? '#eef2ff'
                        : 'transparent'};"
                    onclick={() => (anonymousOnly = !anonymousOnly)}
                    onkeydown={(e: KeyboardEvent) => {
                        if (e.key === 'Enter' || e.key === ' ')
                            anonymousOnly = !anonymousOnly;
                    }}
                >
                    <i
                        class="ti ti-user-off text-xs"
                        style="color:{anonymousOnly ? '#6366f1' : '#94a3b8'};"
                    ></i>
                    <span
                        class="text-xs font-semibold"
                        style="color:{anonymousOnly ? '#6366f1' : '#64748b'};"
                    >
                        Hanya Anonim
                    </span>
                    {#if anonymousOnly}
                        <i class="ti ti-circle-check text-xs text-indigo-400"
                        ></i>
                    {/if}
                </div>

                <div class="ml-auto flex items-center gap-2">
                    <button
                        onclick={resetFilter}
                        class="px-4 py-2 border border-slate-200 hover:bg-slate-50 text-slate-500 font-bold rounded-xl text-xs transition uppercase tracking-wider font-outfit"
                    >
                        Reset
                    </button>
                    <button
                        onclick={applyFilter}
                        class="px-5 py-2 bg-brand-blueRoyal hover:bg-blue-800 text-white font-bold rounded-xl text-xs transition shadow shadow-brand-blueRoyal/20 uppercase tracking-wider font-outfit"
                    >
                        Terapkan
                    </button>
                </div>
            </div>
        </div>

        <!-- ── KPI Cards ────────────────────────────────────────── -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            {#each [{ label: 'Total Ulasan', value: summary.total, sub: 'Dalam periode ini', icon: 'ti-message-star', color: 'amber' }, { label: 'Rata-rata Rating', value: `${summary.avg_rating.toFixed(1)}/5`, sub: 'Keseluruhan produk', icon: 'ti-star-filled', color: 'yellow' }, { label: 'Dilaporkan', value: summary.reported, sub: 'Perlu ditinjau admin', icon: 'ti-flag', color: 'red' }, { label: 'Anonim', value: summary.anonymous, sub: 'Nama disembunyikan', icon: 'ti-user-off', color: 'indigo' }] as card}
                {@const colorMap: Record<string, string> = {
                    amber: 'text-amber-600 bg-amber-50',
                    yellow: 'text-yellow-500 bg-yellow-50',
                    red: 'text-red-500 bg-red-50',
                    indigo: 'text-indigo-500 bg-indigo-50',
                }}
                <div
                    class="bg-white rounded-3xl p-5 border border-slate-200 shadow-sm flex items-center justify-between gap-3"
                >
                    <div class="min-w-0">
                        <p
                            class="text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit truncate"
                        >
                            {card.label}
                        </p>
                        <p
                            class="font-outfit font-black text-2xl text-slate-800 mt-1 leading-none"
                        >
                            {card.value}
                        </p>
                        <p class="text-[11px] text-slate-400 mt-1 truncate">
                            {card.sub}
                        </p>
                    </div>
                    <span
                        class="w-12 h-12 rounded-2xl flex items-center justify-center text-xl shrink-0 {colorMap[
                            card.color
                        ]}"
                    >
                        <i class="ti {card.icon}"></i>
                    </span>
                </div>
            {/each}
        </div>

        <!-- ── Rating Distribution ──────────────────────────────── -->
        <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h2 class="font-outfit font-black text-lg text-slate-800">
                        Distribusi Rating
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Sebaran penilaian produk dari semua ulasan dalam periode
                        ini.
                    </p>
                </div>
                <span class="text-xs font-bold text-slate-400"
                    >{totalInDist} total</span
                >
            </div>
            <div class="space-y-3">
                {#each [5, 4, 3, 2, 1] as star}
                    {@const count = ratingDistribution[star] ?? 0}
                    {@const pct =
                        totalInDist > 0 ? (count / totalInDist) * 100 : 0}
                    {@const color = RATING_COLORS[star]}
                    <div class="flex items-center gap-3">
                        <!-- Star label -->
                        <div class="w-24 shrink-0 flex items-center gap-1.5">
                            <i
                                class="ti ti-star-filled text-xs"
                                style="color:{color};"
                            ></i>
                            <span class="text-xs font-bold text-slate-700"
                                >{star}</span
                            >
                            <span class="text-[10px] text-slate-400"
                                >— {RATING_LABELS[star]}</span
                            >
                        </div>
                        <!-- Bar -->
                        <div
                            class="flex-grow h-3 bg-slate-100 rounded-full overflow-hidden"
                        >
                            <div
                                class="h-full rounded-full transition-all duration-500"
                                style="width:{pct}%; background:{color};"
                            ></div>
                        </div>
                        <!-- Count -->
                        <div class="w-20 shrink-0 text-right">
                            <span class="text-xs font-bold text-slate-600"
                                >{count}</span
                            >
                            <span class="text-[10px] text-slate-400 ml-1"
                                >ulasan</span
                            >
                            {#if pct > 0}
                                <span class="text-[9px] text-slate-400 ml-1"
                                    >({pct.toFixed(0)}%)</span
                                >
                            {/if}
                        </div>
                    </div>
                {/each}
            </div>
        </div>

        <!-- ── Reviews Table ────────────────────────────────────── -->
        <div
            class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-sm"
        >
            <div
                class="p-6 border-b border-slate-100 flex items-center justify-between"
            >
                <div>
                    <h2 class="font-outfit font-black text-lg text-slate-800">
                        Daftar Ulasan
                    </h2>
                    <p class="text-xs text-slate-500 font-medium mt-0.5">
                        {reviews.total ?? 0} ulasan ditemukan
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[920px]">
                    <thead>
                        <tr
                            class="bg-slate-50 text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit border-b border-slate-200"
                        >
                            <th class="py-3.5 px-5">Pelanggan</th>
                            <th class="py-3.5 px-5">Produk</th>
                            <th class="py-3.5 px-5 text-center w-36">Rating</th>
                            <th class="py-3.5 px-5">Komentar</th>
                            <th class="py-3.5 px-5 text-center w-36">Status</th>
                            <th class="py-3.5 px-5 text-right w-32">Tanggal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        {#if (reviews.data as any[]).length === 0}
                            <tr>
                                <td colspan="6" class="py-16 text-center">
                                    <div
                                        class="flex flex-col items-center gap-2"
                                    >
                                        <span
                                            class="w-12 h-12 rounded-2xl bg-slate-50 flex items-center justify-center"
                                        >
                                            <i
                                                class="ti ti-star text-2xl text-slate-300"
                                            ></i>
                                        </span>
                                        <p
                                            class="font-bold text-slate-400 font-outfit"
                                        >
                                            Tidak ada ulasan
                                        </p>
                                        <p class="text-xs text-slate-400">
                                            Coba ubah rentang tanggal atau
                                            filter yang digunakan.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        {:else}
                            {#each reviews.data as review}
                                <tr
                                    class="hover:bg-slate-50/60 transition group"
                                >
                                    <!-- Pelanggan -->
                                    <td class="py-4 px-5">
                                        <div class="flex items-center gap-2.5">
                                            {#if review.is_anonymous}
                                                <span
                                                    class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center shrink-0"
                                                >
                                                    <i
                                                        class="ti ti-user-off text-indigo-400 text-xs"
                                                    ></i>
                                                </span>
                                                <div class="min-w-0">
                                                    <p
                                                        class="text-xs font-bold text-slate-500 italic"
                                                    >
                                                        Anonim
                                                    </p>
                                                    <p
                                                        class="text-[10px] text-slate-400 truncate"
                                                    >
                                                        {review.user?.name ??
                                                            '-'}
                                                    </p>
                                                </div>
                                            {:else}
                                                <span
                                                    class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center shrink-0 font-black text-slate-500 text-xs uppercase"
                                                >
                                                    {(
                                                        review.user?.name ?? 'A'
                                                    ).charAt(0)}
                                                </span>
                                                <div class="min-w-0">
                                                    <p
                                                        class="text-xs font-bold text-slate-800 truncate"
                                                    >
                                                        {review.user?.name ??
                                                            '-'}
                                                    </p>
                                                    <p
                                                        class="text-[10px] text-slate-400 truncate"
                                                    >
                                                        {review.user?.email ??
                                                            '-'}
                                                    </p>
                                                </div>
                                            {/if}
                                        </div>
                                    </td>

                                    <!-- Produk -->
                                    <td class="py-4 px-5 max-w-[180px]">
                                        <p
                                            class="text-xs font-semibold text-slate-700 line-clamp-1"
                                        >
                                            {review.product?.name ?? '-'}
                                        </p>
                                        {#if review.product_variant?.options?.length > 0}
                                            <p
                                                class="text-[10px] text-slate-400 mt-0.5"
                                            >
                                                {review.product_variant.options
                                                    .map((o: any) => o.value)
                                                    .join(', ')}
                                            </p>
                                        {/if}
                                    </td>

                                    <!-- Rating -->
                                    <td class="py-4 px-5 text-center">
                                        <div
                                            class="inline-flex items-center gap-0.5 mb-0.5"
                                        >
                                            {#each [1, 2, 3, 4, 5] as s}
                                                <i
                                                    class="ti ti-star-filled text-xs"
                                                    style="color:{s <=
                                                    review.rating
                                                        ? RATING_COLORS[
                                                              review.rating
                                                          ]
                                                        : '#e2e8f0'};"
                                                ></i>
                                            {/each}
                                        </div>
                                        <p
                                            class="text-[10px] font-semibold"
                                            style="color:{RATING_COLORS[
                                                review.rating
                                            ]};"
                                        >
                                            {ratingLabel(review.rating)}
                                        </p>
                                    </td>

                                    <!-- Komentar -->
                                    <td class="py-4 px-5 max-w-xs">
                                        <p
                                            class="text-xs text-slate-600 leading-relaxed line-clamp-2"
                                        >
                                            {review.comment || '—'}
                                        </p>
                                        {#if review.media?.length > 0}
                                            <span
                                                class="inline-flex items-center gap-1 mt-1.5 text-[10px] font-semibold text-indigo-500 bg-indigo-50 px-2 py-0.5 rounded-full"
                                            >
                                                <i
                                                    class="ti ti-photo text-[10px]"
                                                ></i>
                                                {review.media.length} media
                                            </span>
                                        {/if}
                                    </td>

                                    <!-- Status -->
                                    <td class="py-4 px-5 text-center">
                                        {#if review.is_reported}
                                            <div
                                                class="flex flex-col items-center gap-1"
                                            >
                                                <span
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-red-50 text-red-500 border border-red-100"
                                                >
                                                    <i class="ti ti-flag-filled"
                                                    ></i>
                                                    Dilaporkan
                                                </span>
                                                {#if review.report_reason}
                                                    <p
                                                        class="text-[10px] text-slate-400 text-center line-clamp-2 max-w-[110px]"
                                                    >
                                                        {review.report_reason}
                                                    </p>
                                                {/if}
                                            </div>
                                        {:else}
                                            <span
                                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-600 border border-emerald-100"
                                            >
                                                <i class="ti ti-circle-check"
                                                ></i>
                                                Aman
                                            </span>
                                        {/if}
                                    </td>

                                    <!-- Tanggal -->
                                    <td class="py-4 px-5 text-right">
                                        <span
                                            class="text-xs text-slate-500 font-medium"
                                            >{formatDate(
                                                review.created_at,
                                            )}</span
                                        >
                                    </td>
                                </tr>
                            {/each}
                        {/if}
                    </tbody>
                </table>
            </div>

            <Pagination paginator={reviews} />
        </div>
    </main>
</AdminLayout>
