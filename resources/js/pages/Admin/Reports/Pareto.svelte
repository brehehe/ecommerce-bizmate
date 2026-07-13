<script lang="ts">
    import { page, router } from '@inertiajs/svelte';
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import Chart from 'chart.js/auto';

    const primaryColor = $derived(page.props.theme?.primary_color || '#0c4cb4');
    const secondaryColor = $derived(
        page.props.theme?.secondary_color || '#fa7315',
    );
    const storeName = $derived((page.props as any).settings?.store_name || 'Bizmate');

    function formatDate(dateStr: string) {
        if (!dateStr) return '—';
        return new Date(dateStr).toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        });
    }

    let {
        items = [],
        chartData = { labels: [], values: [], cumulativePercentages: [] },
        metrics = {
            total_items: 0,
            total_value: 0,
            vital_few_count: 0,
            vital_few_value: 0,
            trivial_many_count: 0,
            trivial_many_value: 0,
            vital_few_pct: 0,
        },
        movingMetrics = {
            fast: { count: 0, qty: 0, value: 0 },
            medium: { count: 0, qty: 0, value: 0 },
            slow: { count: 0, qty: 0, value: 0 },
        },
        isProductType = false,
        filters = { date_from: '', date_to: '', type: 'product_revenue', preset: '' },
    } = $props();

    // svelte-ignore state_referenced_locally
    let dateFrom = $state(filters.date_from);
    // svelte-ignore state_referenced_locally
    let dateTo = $state(filters.date_to);
    // svelte-ignore state_referenced_locally
    let activePreset = $state(filters.preset || 'bulanan');
    // svelte-ignore state_referenced_locally
    let selectedType = $state(filters.type);

    // Moving filter tab: 'all' | 'fast' | 'medium' | 'slow'
    let movingFilter = $state<'all' | 'fast' | 'medium' | 'slow'>('all');

    // Filter tabel berdasarkan moving_category — berlaku untuk semua tipe
    const filteredItems = $derived(
        movingFilter === 'all'
            ? (items as any[])
            : (items as any[]).filter(
                  (i: any) => i.moving_category === movingFilter,
              ),
    );

    let paretoCanvas = $state<HTMLCanvasElement>();
    let paretoChart: Chart | null = null;

    const typeOptions = [
        {
            value: 'product_revenue',
            label: 'Produk — Omset Tertinggi',
            icon: 'ti-cash',
            color: '#6366f1',
        },
        {
            value: 'product_qty',
            label: 'Produk — Terlaris (Qty)',
            icon: 'ti-package',
            color: '#10b981',
        },
        {
            value: 'customer_spending',
            label: 'Pelanggan — Pembeli Terbesar',
            icon: 'ti-user-dollar',
            color: '#f59e0b',
        },
        {
            value: 'category_revenue',
            label: 'Kategori — Omset Tertinggi',
            icon: 'ti-category',
            color: '#ef4444',
        },
    ];

    // Label moving disesuaikan per tipe analisis
    const movingConfigByType: Record<
        string,
        Record<
            'fast' | 'medium' | 'slow',
            {
                label: string;
                emoji: string;
                color: string;
                bg: string;
                border: string;
                icon: string;
                desc: string;
            }
        >
    > = {
        product_revenue: {
            fast: {
                label: 'Fast Moving',
                emoji: '🚀',
                color: '#10b981',
                bg: '#ecfdf5',
                border: '#6ee7b7',
                icon: 'ti-trending-up',
                desc: 'Produk dengan perputaran cepat (top 50% qty)',
            },
            medium: {
                label: 'Medium Moving',
                emoji: '📦',
                color: '#f59e0b',
                bg: '#fffbeb',
                border: '#fcd34d',
                icon: 'ti-trending-neutral',
                desc: 'Produk dengan perputaran sedang (qty 50–80%)',
            },
            slow: {
                label: 'Slow Moving',
                emoji: '🐢',
                color: '#ef4444',
                bg: '#fef2f2',
                border: '#fca5a5',
                icon: 'ti-trending-down',
                desc: 'Produk dengan perputaran lambat (qty >80%)',
            },
        },
        product_qty: {
            fast: {
                label: 'Fast Moving',
                emoji: '🚀',
                color: '#10b981',
                bg: '#ecfdf5',
                border: '#6ee7b7',
                icon: 'ti-trending-up',
                desc: 'Produk dengan perputaran cepat (top 50% qty)',
            },
            medium: {
                label: 'Medium Moving',
                emoji: '📦',
                color: '#f59e0b',
                bg: '#fffbeb',
                border: '#fcd34d',
                icon: 'ti-trending-neutral',
                desc: 'Produk dengan perputaran sedang (qty 50–80%)',
            },
            slow: {
                label: 'Slow Moving',
                emoji: '🐢',
                color: '#ef4444',
                bg: '#fef2f2',
                border: '#fca5a5',
                icon: 'ti-trending-down',
                desc: 'Produk dengan perputaran lambat (qty >80%)',
            },
        },
        customer_spending: {
            fast: {
                label: 'High Value',
                emoji: '💎',
                color: '#6366f1',
                bg: '#eef2ff',
                border: '#a5b4fc',
                icon: 'ti-crown',
                desc: 'Pelanggan bernilai tinggi (top 50% belanja)',
            },
            medium: {
                label: 'Mid Value',
                emoji: '⭐',
                color: '#f59e0b',
                bg: '#fffbeb',
                border: '#fcd34d',
                icon: 'ti-star',
                desc: 'Pelanggan bernilai menengah (50–80%)',
            },
            slow: {
                label: 'Low Value',
                emoji: '👤',
                color: '#94a3b8',
                bg: '#f8fafc',
                border: '#cbd5e1',
                icon: 'ti-user',
                desc: 'Pelanggan bernilai rendah (>80%)',
            },
        },
        category_revenue: {
            fast: {
                label: 'Unggulan',
                emoji: '🏆',
                color: '#10b981',
                bg: '#ecfdf5',
                border: '#6ee7b7',
                icon: 'ti-trophy',
                desc: 'Kategori penyumbang terbesar (top 50% omset)',
            },
            medium: {
                label: 'Menengah',
                emoji: '📊',
                color: '#f59e0b',
                bg: '#fffbeb',
                border: '#fcd34d',
                icon: 'ti-chart-bar',
                desc: 'Kategori kontributor menengah (50–80%)',
            },
            slow: {
                label: 'Lemah',
                emoji: '📉',
                color: '#ef4444',
                bg: '#fef2f2',
                border: '#fca5a5',
                icon: 'ti-chart-bar-off',
                desc: 'Kategori kontributor kecil (>80%)',
            },
        },
    };

    const movingConfig = $derived(
        movingConfigByType[selectedType] ??
            movingConfigByType['product_revenue'],
    );

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

        movingFilter = 'all';
        router.get(
            '/admin/reports/pareto',
            {
                date_from: dateFrom,
                date_to: dateTo,
                preset: activePreset,
                type: selectedType,
            },
            { preserveState: true },
        );
    }

    function applyFilter() {
        movingFilter = 'all';
        router.get(
            '/admin/reports/pareto',
            {
                date_from: dateFrom,
                date_to: dateTo,
                preset: activePreset,
                type: selectedType,
            },
            { preserveState: true },
        );
    }

    function resetFilter() {
        dateFrom = formatDateLocal(new Date(new Date().setDate(new Date().getDate() - 29)));
        dateTo = formatDateLocal(new Date());
        activePreset = 'bulanan';
        selectedType = 'product_revenue';
        movingFilter = 'all';

        router.get(
            '/admin/reports/pareto',
            {
                date_from: dateFrom,
                date_to: dateTo,
                preset: 'bulanan',
                type: 'product_revenue',
            },
            { preserveState: true },
        );
    }

    function formatRupiah(value: number) {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
        }).format(value);
    }

    function formatNumber(value: number) {
        return new Intl.NumberFormat('id-ID').format(value);
    }

    function getValueLabel(value: number) {
        if (selectedType === 'product_qty') {
            return formatNumber(value) + ' pcs';
        }
        return formatRupiah(value);
    }

    function getColumnHeader() {
        if (selectedType === 'product_qty') {
            return 'Kuantitas Terjual';
        }
        if (selectedType === 'customer_spending') {
            return 'Total Belanja';
        }
        return 'Omset';
    }

    function getSubLabelHeader() {
        if (
            selectedType === 'product_revenue' ||
            selectedType === 'product_qty'
        ) {
            return 'Kategori';
        }
        if (selectedType === 'customer_spending') {
            return 'Email';
        }
        return '';
    }

    function getMovingBadge(cat: string) {
        const cfg = movingConfig[cat as keyof typeof movingConfig];
        if (!cfg) return null;
        return cfg;
    }

    function exportToCSV() {
        const typeLabel =
            typeOptions.find((t) => t.value === selectedType)?.label ??
            selectedType;
        const headers = [
            'Rank',
            'Nama',
            getSubLabelHeader() || 'Info',
            'Qty',
            getColumnHeader(),
            'Kontribusi (%)',
            'Kumulatif (%)',
            'Kategori Pareto',
            'Moving',
        ];
        const csvRows = [headers.join(',')];

        (items as any[]).forEach((item: any) => {
            const row = [
                item.rank,
                `"${(item.label ?? '').replace(/"/g, '""')}"`,
                `"${(item.category_name ?? '').replace(/"/g, '""')}"`,
                item.qty_sold,
                Math.round(item.value),
                item.percentage,
                item.cumulative_percentage,
                item.is_vital_few ? 'Vital Few (80%)' : 'Trivial Many (20%)',
                item.moving_category ?? '-',
            ];
            csvRows.push(row.join(','));
        });

        const csvContent = 'data:text/csv;charset=utf-8,' + csvRows.join('\n');
        const link = document.createElement('a');
        link.setAttribute('href', encodeURI(csvContent));
        link.setAttribute(
            'download',
            `laporan_pareto_${selectedType}_${dateFrom}_sd_${dateTo}.csv`,
        );
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    function buildChart() {
        if (!paretoCanvas) {
            return;
        }
        if (paretoChart) {
            paretoChart.destroy();
        }

        const ctx = paretoCanvas.getContext('2d');
        if (!ctx) {
            return;
        }

        const cleanChartData = $state.snapshot(chartData);
        const cleanItems = $state.snapshot(items);

        const labels = [...(cleanChartData.labels as string[])];
        const values = [...(cleanChartData.values as number[])];
        const cumulative = [...(cleanChartData.cumulativePercentages as number[])];

        // Color bars by moving_category if product type
        const barColors = labels.map((lbl, i) => {
            if (isProductType) {
                const item = (cleanItems as any[]).find(
                    (it: any) => it.label === lbl,
                );
                if (item?.moving_category === 'fast') return '#10b981cc';
                if (item?.moving_category === 'medium') return '#f59e0bcc';
                if (item?.moving_category === 'slow') return '#ef4444cc';
            }
            const pct = cumulative[i] ?? 100;
            return pct <= 80 ? primaryColor + 'cc' : '#cbd5e1';
        });

        paretoChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        type: 'bar',
                        label: getColumnHeader(),
                        data: values,
                        backgroundColor: barColors,
                        borderRadius: 6,
                        order: 2,
                        yAxisID: 'y',
                    },
                    {
                        type: 'line',
                        label: 'Kumulatif (%)',
                        data: cumulative,
                        borderColor: secondaryColor,
                        backgroundColor: secondaryColor + '20',
                        borderWidth: 2.5,
                        pointBackgroundColor: secondaryColor,
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        tension: 0.3,
                        fill: false,
                        order: 1,
                        yAxisID: 'y2',
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { boxWidth: 12, font: { size: 11 } },
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                if (context.dataset.type === 'line') {
                                    return `Kumulatif: ${context.parsed.y.toFixed(1)}%`;
                                }
                                return `${getColumnHeader()}: ${getValueLabel(context.parsed.y)}`;
                            },
                            afterLabel: function (context) {
                                if (
                                    !isProductType ||
                                    context.dataset.type === 'line'
                                )
                                    return '';
                                const lbl = context.label;
                                const item = (items as any[]).find(
                                    (it: any) => it.label === lbl,
                                );
                                if (!item?.moving_category) return '';
                                const cfg =
                                    movingConfig[
                                        item.moving_category as keyof typeof movingConfig
                                    ];
                                return cfg ? `${cfg.emoji} ${cfg.label}` : '';
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 9 }, maxRotation: 35 },
                    },
                    y: {
                        position: 'left',
                        grid: { color: '#f1f5f9' },
                        ticks: {
                            font: { size: 9 },
                            callback: (v) => {
                                if (selectedType === 'product_qty') {
                                    return formatNumber(Number(v));
                                }
                                const n = Number(v);
                                if (n >= 1_000_000) {
                                    return (
                                        'Rp' + (n / 1_000_000).toFixed(0) + 'jt'
                                    );
                                }
                                if (n >= 1_000) {
                                    return 'Rp' + (n / 1_000).toFixed(0) + 'rb';
                                }
                                return 'Rp' + n;
                            },
                        },
                    },
                    y2: {
                        position: 'right',
                        min: 0,
                        max: 100,
                        grid: { drawOnChartArea: false },
                        ticks: { font: { size: 9 }, callback: (v) => v + '%' },
                    },
                },
            },
        });
    }

    $effect(() => {
        const _labels = chartData.labels;
        const _values = chartData.values;
        const _cum = chartData.cumulativePercentages;
        const canvas = paretoCanvas;

        if (!canvas) {
            return;
        }

        buildChart();

        return () => {
            if (paretoChart) {
                paretoChart.destroy();
                paretoChart = null;
            }
        };
    });
</script>

<svelte:head>
    <title>Analisis Pareto — {storeName}</title>
</svelte:head>

<AdminLayout>
    <main class="flex-grow p-4 sm:p-8 w-full max-w-[1600px] mx-auto space-y-6 print:p-0 print:bg-white">
        <!-- Print Header -->
        <div class="hidden print:block text-center space-y-1.5 mb-6">
            <h1 class="font-outfit font-black text-2xl text-slate-800 tracking-tight">{storeName}</h1>
            <h2 class="font-outfit font-bold text-lg text-slate-700">Analisis Pareto (80/20)</h2>
            <p class="text-xs text-slate-500 font-medium">Periode: {formatDate(dateFrom)} s/d {formatDate(dateTo)}</p>
            <p class="text-[10px] text-slate-400">Dicetak pada: {new Date().toLocaleString('id-ID')}</p>
        </div>

        <!-- Page Header -->
        <div
            class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 print:hidden"
        >
            <div>
                <h1
                    class="font-outfit font-black text-2xl sm:text-3xl text-slate-800 tracking-tight"
                >
                    Analisis Pareto (80/20)
                </h1>
                <p class="text-sm text-slate-500 font-medium mt-1">
                    Temukan faktor kecil yang berkontribusi besar terhadap
                    performa bisnis Anda.
                </p>
            </div>

            <div class="flex items-center gap-3 shrink-0 w-full sm:w-auto">
                <button
                    onclick={() => window.print()}
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

        <!-- Type Selector -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 print:hidden">
            {#each typeOptions as opt}
                <button
                    onclick={() => {
                        selectedType = opt.value;
                        applyFilter();
                    }}
                    class="flex items-center gap-3 p-4 rounded-2xl border-2 transition-all duration-200 text-left {selectedType ===
                    opt.value
                        ? 'shadow-md'
                        : 'border-slate-200 bg-white hover:border-slate-300'}"
                    style={selectedType === opt.value
                        ? `border-color: ${opt.color}; background: ${opt.color}12;`
                        : ''}
                >
                    <span
                        class="w-9 h-9 rounded-xl flex items-center justify-center text-white text-base shrink-0"
                        style="background-color: {opt.color};"
                    >
                        <i class="ti {opt.icon}"></i>
                    </span>
                    <span
                        class="text-xs font-bold leading-tight"
                        style={selectedType === opt.value
                            ? `color: ${opt.color};`
                            : 'color: #475569;'}
                    >
                        {opt.label}
                    </span>
                </button>
            {/each}
        </div>

        <!-- Filter Card -->
        <div class="bg-white rounded-3xl border border-slate-200 p-5 sm:p-6 shadow-sm space-y-4 print:hidden">
            <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-4">
                <!-- Presets -->
                <div class="flex flex-wrap items-center gap-1.5 bg-slate-100/80 p-1 rounded-2xl w-full xl:w-auto">
                    <button
                        onclick={() => selectPreset('harian')}
                        class="flex-1 xl:flex-none text-center px-4 py-2 text-[11px] font-bold rounded-xl transition-all duration-200 whitespace-nowrap uppercase tracking-wider font-outfit
                            {activePreset === 'harian' ? 'bg-white text-slate-800 shadow-xs' : 'text-slate-500 hover:text-slate-800'}"
                    >
                        Harian
                    </button>
                    <button
                        onclick={() => selectPreset('mingguan')}
                        class="flex-1 xl:flex-none text-center px-4 py-2 text-[11px] font-bold rounded-xl transition-all duration-200 whitespace-nowrap uppercase tracking-wider font-outfit
                            {activePreset === 'mingguan' ? 'bg-white text-slate-800 shadow-xs' : 'text-slate-500 hover:text-slate-800'}"
                    >
                        Mingguan
                    </button>
                    <button
                        onclick={() => selectPreset('bulanan')}
                        class="flex-1 xl:flex-none text-center px-4 py-2 text-[11px] font-bold rounded-xl transition-all duration-200 whitespace-nowrap uppercase tracking-wider font-outfit
                            {activePreset === 'bulanan' ? 'bg-white text-slate-800 shadow-xs' : 'text-slate-500 hover:text-slate-800'}"
                    >
                        Bulanan
                    </button>
                    <button
                        onclick={() => selectPreset('tahunan')}
                        class="flex-1 xl:flex-none text-center px-4 py-2 text-[11px] font-bold rounded-xl transition-all duration-200 whitespace-nowrap uppercase tracking-wider font-outfit
                            {activePreset === 'tahunan' ? 'bg-white text-slate-800 shadow-xs' : 'text-slate-500 hover:text-slate-800'}"
                    >
                        Tahunan
                    </button>
                </div>
            </div>

            <!-- Advanced Filters Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-end pt-2 border-t border-slate-100">
                <!-- Custom Dates -->
                <div class="sm:col-span-2 space-y-1.5">
                    <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider" for="date_from">Periode Tanggal</label>
                    <div class="flex items-center gap-2">
                        <div class="relative flex-1">
                            <input
                                id="date_from"
                                type="date"
                                bind:value={dateFrom}
                                onchange={() => activePreset = 'custom'}
                                class="w-full bg-slate-50 border border-slate-200 text-slate-755 text-xs font-semibold rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-blueRoyal/20 focus:border-brand-blueRoyal transition cursor-pointer"
                            />
                        </div>
                        <span class="text-xs text-slate-400 font-bold uppercase tracking-wider">s/d</span>
                        <div class="relative flex-1">
                            <input
                                id="date_to"
                                type="date"
                                bind:value={dateTo}
                                onchange={() => activePreset = 'custom'}
                                class="w-full bg-slate-50 border border-slate-200 text-slate-755 text-xs font-semibold rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-blueRoyal/20 focus:border-brand-blueRoyal transition cursor-pointer"
                            />
                        </div>
                    </div>
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

        <!-- KPI Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Total Item Card -->
            <div
                class="bg-white rounded-3xl p-5 border border-slate-200 shadow-sm"
            >
                <div class="flex items-center gap-2 mb-3">
                    <span
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-lg text-indigo-600 bg-indigo-50 shrink-0"
                    >
                        <i class="ti ti-list-numbers"></i>
                    </span>
                    <span
                        class="text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit"
                    >
                        {#if selectedType === 'customer_spending'}
                            Total Pelanggan
                        {:else if selectedType === 'category_revenue'}
                            Total Kategori
                        {:else}
                            Total Jenis Produk
                        {/if}
                    </span>
                </div>
                <h3 class="font-outfit font-black text-2xl text-slate-800 mt-1">
                    {metrics.total_items}
                </h3>
                <p class="text-xs text-slate-400 font-semibold mt-1">
                    {#if selectedType === 'customer_spending'}
                        pelanggan unik berbelanja
                    {:else if selectedType === 'category_revenue'}
                        kategori terdata transaksi
                    {:else}
                        jenis produk unik terjual
                    {/if}
                </p>
            </div>

            <!-- Total Value Card -->
            <div
                class="bg-white rounded-3xl p-5 border border-slate-200 shadow-sm"
            >
                <div class="flex items-center gap-2 mb-3">
                    <span
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-lg text-emerald-600 bg-emerald-50 shrink-0"
                    >
                        <i class="ti ti-chart-pie"></i>
                    </span>
                    <span
                        class="text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit"
                    >
                        {#if selectedType === 'product_qty'}
                            Total Kuantitas
                        {:else}
                            Total Omset
                        {/if}
                    </span>
                </div>
                <h3 class="font-outfit font-black text-xl text-slate-800 mt-1">
                    {selectedType === 'product_qty'
                        ? formatNumber(metrics.total_value) + ' pcs'
                        : formatRupiah(metrics.total_value)}
                </h3>
                <p class="text-xs text-slate-400 font-semibold mt-1">
                    {#if selectedType === 'product_qty'}
                        total kuantitas produk terjual
                    {:else if selectedType === 'customer_spending'}
                        total nilai transaksi pelanggan
                    {:else}
                        total nilai omset kotor (gross)
                    {/if}
                </p>
            </div>

            <!-- Vital Few Card -->
            <div
                class="bg-white rounded-3xl p-5 border-2 shadow-sm"
                style="border-color: {primaryColor}30; background: {primaryColor}06;"
            >
                <div class="flex items-center gap-2 mb-3">
                    <span
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-lg text-white shrink-0"
                        style="background-color: {primaryColor};"
                    >
                        <i class="ti ti-star"></i>
                    </span>
                    <span
                        class="text-[10px] font-black uppercase tracking-widest"
                        style="color: {primaryColor};">Vital Few (80%)</span
                    >
                </div>
                <h3 class="font-outfit font-black text-2xl text-slate-800">
                    {metrics.vital_few_count}
                    {#if selectedType === 'customer_spending'}
                        pelanggan
                    {:else if selectedType === 'category_revenue'}
                        kategori
                    {:else}
                        produk
                    {/if}
                </h3>
                <p class="text-xs text-slate-500 font-semibold mt-1">
                    hanya {metrics.vital_few_pct}% dari total, tapi menyumbang {metrics.total_value > 0 ? Math.round((metrics.vital_few_value / metrics.total_value) * 100) : 0}% {selectedType === 'product_qty' ? 'qty' : 'omset'} ({selectedType === 'product_qty' ? formatNumber(metrics.vital_few_value) + ' pcs' : formatRupiah(metrics.vital_few_value)})
                </p>
            </div>

            <!-- Trivial Many Card -->
            <div
                class="bg-white rounded-3xl p-5 border border-slate-200 shadow-sm"
            >
                <div class="flex items-center gap-2 mb-3">
                    <span
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-lg text-slate-400 bg-slate-50 shrink-0"
                    >
                        <i class="ti ti-dots"></i>
                    </span>
                    <span
                        class="text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit"
                    >
                        Trivial Many (20%)
                    </span>
                </div>
                <h3 class="font-outfit font-black text-2xl text-slate-800 mt-1">
                    {metrics.trivial_many_count}
                    {#if selectedType === 'customer_spending'}
                        pelanggan
                    {:else if selectedType === 'category_revenue'}
                        kategori
                    {:else}
                        produk
                    {/if}
                </h3>
                <p class="text-xs text-slate-400 font-semibold mt-1">
                    sisa {Math.max(0, 100 - metrics.vital_few_pct)}% dari total, hanya menyumbang {metrics.total_value > 0 ? Math.round((metrics.trivial_many_value / metrics.total_value) * 100) : 0}% {selectedType === 'product_qty' ? 'qty' : 'omset'} ({selectedType === 'product_qty' ? formatNumber(metrics.trivial_many_value) + ' pcs' : formatRupiah(metrics.trivial_many_value)})
                </p>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════════════════
             MOVING / TIER CLASSIFICATION — untuk SEMUA tipe analisis
             ══════════════════════════════════════════════════════════════════════ -->
        {#if (items as any[]).length > 0}
            <div class="space-y-4">
                <!-- Section Header -->
                <div class="flex items-center gap-3">
                    <div
                        class="w-10 h-10 rounded-2xl bg-gradient-to-br from-emerald-400 to-teal-500 flex items-center justify-center text-white text-lg shadow-md shadow-emerald-200"
                    >
                        <i class="ti ti-activity"></i>
                    </div>
                    <div>
                        <h3
                            class="font-outfit font-black text-lg text-slate-800 leading-none"
                        >
                            {#if isProductType}Klasifikasi Perputaran Produk
                            {:else if selectedType === 'customer_spending'}Klasifikasi
                                Nilai Pelanggan
                            {:else}Klasifikasi Kontribusi Kategori{/if}
                        </h3>
                        <p class="text-xs text-slate-500 font-medium mt-0.5">
                            {#if isProductType}Berdasarkan kumulatif qty terjual
                                — Fast (0–50%), Medium (50–80%), Slow (>80%)
                            {:else if selectedType === 'customer_spending'}Berdasarkan
                                kumulatif nilai belanja — High Value (0–50%),
                                Mid Value (50–80%), Low Value (>80%)
                            {:else}Berdasarkan kumulatif omset — Unggulan
                                (0–50%), Menengah (50–80%), Lemah (>80%){/if}
                        </p>
                    </div>
                </div>

                <!-- Moving Cards -->
                <!-- Moving Cards: 3 kartu interaktif -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    {#each ['fast', 'medium', 'slow'] as const as cat}
                        {@const cfg = movingConfig[cat]}
                        {@const data = movingMetrics[cat]}
                        <button
                            onclick={() =>
                                (movingFilter =
                                    movingFilter === cat ? 'all' : cat)}
                            class="text-left p-5 rounded-3xl border-2 transition-all duration-200 cursor-pointer hover:shadow-md group"
                            style="border-color: {movingFilter === cat
                                ? cfg.color
                                : cfg.border}; background: {movingFilter === cat
                                ? cfg.bg
                                : 'white'};"
                        >
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center gap-2.5">
                                    <span
                                        class="w-9 h-9 rounded-xl flex items-center justify-center text-white text-base"
                                        style="background-color: {cfg.color};"
                                    >
                                        <i class="ti {cfg.icon}"></i>
                                    </span>
                                    <div>
                                        <p
                                            class="text-[10px] font-black uppercase tracking-widest"
                                            style="color: {cfg.color};"
                                        >
                                            {cfg.label}
                                        </p>
                                        <p
                                            class="text-[9px] text-slate-400 font-medium"
                                        >
                                            {cfg.desc}
                                        </p>
                                    </div>
                                </div>
                                {#if movingFilter === cat}
                                    <span
                                        class="text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full text-white"
                                        style="background-color: {cfg.color};"
                                    >
                                        Filter Aktif
                                    </span>
                                {/if}
                            </div>
                            <div class="grid grid-cols-3 gap-2 mt-2">
                                <div
                                    class="bg-white/70 rounded-xl p-2 text-center"
                                >
                                    <p
                                        class="font-outfit font-black text-xl text-slate-800"
                                    >
                                        {data.count}
                                    </p>
                                    <p
                                        class="text-[9px] text-slate-400 font-bold uppercase"
                                    >
                                        {isProductType
                                            ? 'Produk'
                                            : selectedType ===
                                                'customer_spending'
                                              ? 'Pelanggan'
                                              : 'Kategori'}
                                    </p>
                                </div>
                                <div
                                    class="bg-white/70 rounded-xl p-2 text-center"
                                >
                                    <p
                                        class="font-outfit font-black text-xl text-slate-800"
                                    >
                                        {isProductType
                                            ? formatNumber(data.qty)
                                            : '-'}
                                    </p>
                                    <p
                                        class="text-[9px] text-slate-400 font-bold uppercase"
                                    >
                                        Total Qty
                                    </p>
                                </div>
                                <div
                                    class="bg-white/70 rounded-xl p-2 text-center"
                                >
                                    <p
                                        class="font-outfit font-black text-sm text-slate-800 leading-tight"
                                    >
                                        {selectedType === 'product_qty'
                                            ? formatNumber(data.value)
                                            : formatRupiah(data.value)}
                                    </p>
                                    <p
                                        class="text-[9px] text-slate-400 font-bold uppercase"
                                    >
                                        Nilai
                                    </p>
                                </div>
                            </div>
                            <!-- Progress bar showing share of total qty -->
                            {#if movingMetrics.fast.qty + movingMetrics.medium.qty + movingMetrics.slow.qty > 0}
                                {@const totalQtyAll =
                                    movingMetrics.fast.qty +
                                    movingMetrics.medium.qty +
                                    movingMetrics.slow.qty}
                                {@const pct = Math.round(
                                    (data.qty / totalQtyAll) * 100,
                                )}
                                <div class="mt-3 flex items-center gap-2">
                                    <div
                                        class="flex-grow bg-slate-100 rounded-full h-1.5"
                                    >
                                        <div
                                            class="h-1.5 rounded-full transition-all duration-500"
                                            style="width: {pct}%; background-color: {cfg.color};"
                                        ></div>
                                    </div>
                                    <span
                                        class="text-[9px] font-black"
                                        style="color: {cfg.color};"
                                        >{pct}% qty</span
                                    >
                                </div>
                            {/if}
                        </button>
                    {/each}
                </div>

                <!-- Moving filter active notice -->
                {#if movingFilter !== 'all'}
                    {@const cfg = movingConfig[movingFilter]}
                    <div
                        class="flex items-center gap-3 px-4 py-3 rounded-2xl border"
                        style="border-color: {cfg.border}; background: {cfg.bg};"
                    >
                        <i
                            class="ti {cfg.icon} text-base"
                            style="color: {cfg.color};"
                        ></i>
                        <p
                            class="text-xs font-bold flex-grow"
                            style="color: {cfg.color};"
                        >
                            Menampilkan {filteredItems.length} produk
                            <strong>{cfg.label}</strong>. Klik kartu lagi atau
                            pilih "Semua" untuk reset.
                        </p>
                        <button
                            onclick={() => (movingFilter = 'all')}
                            class="text-[10px] font-black uppercase tracking-wider px-3 py-1 rounded-lg text-white"
                            style="background-color: {cfg.color};"
                        >
                            Semua
                        </button>
                    </div>
                {/if}
            </div>
        {/if}

        <!-- Pareto Chart -->
        <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm print:hidden">
            <div class="mb-4">
                <h3 class="font-outfit font-black text-lg text-slate-800">
                    Diagram Pareto
                </h3>
                <p class="text-xs text-slate-500 font-medium">
                    {#if isProductType}
                        Warna batang: 🚀 Fast Moving · 📦 Medium Moving · 🐢
                        Slow Moving. Garis = % kumulatif.
                    {:else if selectedType === 'customer_spending'}
                        Warna batang: 💎 High Value · ⭐ Mid Value · 👤 Low
                        Value. Garis = % kumulatif.
                    {:else if selectedType === 'category_revenue'}
                        Warna batang: 🏆 Unggulan · 📊 Menengah · 📉 Lemah.
                        Garis = % kumulatif.
                    {:else}
                        Batang berwarna = Vital Few (berkontribusi ≤80%
                        kumulatif). Garis = % kumulatif.
                    {/if}
                </p>
            </div>
            <div class="h-80 w-full">
                {#if (items as any[]).length === 0}
                    <div
                        class="h-full flex flex-col items-center justify-center text-slate-400"
                    >
                        <i
                            class="ti ti-chart-bar-off text-4xl mb-2 text-slate-300"
                        ></i>
                        <span class="font-bold text-sm"
                            >Tidak ada data untuk periode ini.</span
                        >
                    </div>
                {:else}
                    <canvas bind:this={paretoCanvas}></canvas>
                {/if}
            </div>
            {#if (items as any[]).length > 0}
                <div class="mt-4 flex flex-wrap gap-3 justify-center">
                    {#each ['fast', 'medium', 'slow'] as const as cat}
                        {@const cfg = movingConfig[cat]}
                        <span
                            class="flex items-center gap-1.5 text-[10px] font-bold"
                            style="color: {cfg.color};"
                        >
                            <span
                                class="w-3 h-3 rounded-sm inline-block"
                                style="background-color: {cfg.color};"
                            ></span>
                            {cfg.emoji}
                            {cfg.label}
                        </span>
                    {/each}
                </div>
            {/if}
        </div>

        <!-- 80/20 Insight Box -->
        {#if (items as any[]).length > 0}
            <div
                class="rounded-3xl p-6 border-2"
                style="border-color: {primaryColor}30; background: linear-gradient(135deg, {primaryColor}08, {secondaryColor}08);"
            >
                <div class="flex items-start gap-4">
                    <div
                        class="w-12 h-12 rounded-2xl flex items-center justify-center text-white text-2xl shrink-0"
                        style="background: linear-gradient(135deg, {primaryColor}, {secondaryColor});"
                    >
                        <i class="ti ti-bulb"></i>
                    </div>
                    <div>
                        <h4
                            class="font-outfit font-black text-lg text-slate-800"
                        >
                            Insight Pareto 80/20
                        </h4>
                        <p
                            class="text-sm text-slate-600 font-medium mt-1 leading-relaxed"
                        >
                            Hanya <strong class="text-slate-800"
                                >{metrics.vital_few_count} item</strong
                            >
                            ({metrics.vital_few_pct}%) berkontribusi pada
                            <strong class="text-slate-800">≥80%</strong> dari
                            total nilai. Fokuskan sumber daya Anda pada
                            item-item tersebut untuk hasil yang optimal. Sisa
                            <strong class="text-slate-800"
                                >{metrics.trivial_many_count} item</strong
                            >
                            ({100 - metrics.vital_few_pct}%) hanya berkontribusi
                            pada sisanya.
                        </p>
                        {#if isProductType && movingMetrics.slow.count > 0}
                            <p
                                class="text-sm text-slate-600 font-medium mt-2 leading-relaxed"
                            >
                                ⚠️ Terdapat <strong class="text-red-600"
                                    >{movingMetrics.slow.count} produk Slow Moving</strong
                                > yang perlu perhatian — pertimbangkan promosi, bundling,
                                atau clearance untuk meningkatkan perputarannya.
                            </p>
                        {:else if selectedType === 'customer_spending' && movingMetrics.slow.count > 0}
                            <p
                                class="text-sm text-slate-600 font-medium mt-2 leading-relaxed"
                            >
                                💡 Terdapat <strong class="text-slate-700"
                                    >{movingMetrics.slow.count} pelanggan Low Value</strong
                                >. Pertimbangkan program loyalitas atau
                                penawaran khusus untuk meningkatkan spending
                                mereka.
                            </p>
                        {:else if selectedType === 'category_revenue' && movingMetrics.slow.count > 0}
                            <p
                                class="text-sm text-slate-600 font-medium mt-2 leading-relaxed"
                            >
                                📊 Terdapat <strong class="text-slate-700"
                                    >{movingMetrics.slow.count} kategori Lemah</strong
                                >. Evaluasi strategi merchandising dan promosi
                                pada kategori-kategori tersebut.
                            </p>
                        {/if}
                    </div>
                </div>
            </div>
        {/if}

        <!-- Detail Table -->
        <div
            class="bg-white rounded-3xl border border-slate-200 overflow-hidden shadow-sm"
        >
            <div
                class="p-6 border-b border-slate-100 flex flex-wrap items-center justify-between gap-4"
            >
                <div>
                    <h3 class="font-outfit font-black text-lg text-slate-800">
                        Tabel Detail
                    </h3>
                    <p class="text-xs text-slate-500 font-medium">
                        Lengkap dengan persentase kontribusi dan kumulatif.
                    </p>
                </div>
                <div
                    class="flex flex-wrap items-center gap-3 text-xs font-bold"
                >
                    <!-- Pareto legend -->
                    <span class="flex items-center gap-1.5">
                        <span
                            class="w-3 h-3 rounded-full inline-block"
                            style="background-color: {primaryColor};"
                        ></span>
                        Vital Few
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span
                            class="w-3 h-3 rounded-full inline-block bg-slate-300"
                        ></span>
                        Trivial Many
                    </span>
                    {#if (items as any[]).length > 0}
                        <span class="w-px h-4 bg-slate-200"></span>
                        {#each ['fast', 'medium', 'slow'] as const as cat}
                            {@const cfg = movingConfig[cat]}
                            <span
                                class="flex items-center gap-1"
                                style="color: {cfg.color};"
                            >
                                <i class="ti {cfg.icon} text-sm"></i>
                                {cfg.label}
                            </span>
                        {/each}
                    {/if}
                </div>
            </div>

            <!-- Moving Tab Filter — tampil untuk semua tipe -->
            {#if (items as any[]).length > 0}
                <div class="px-6 pt-4 flex items-center gap-2 flex-wrap">
                    <button
                        onclick={() => (movingFilter = 'all')}
                        class="px-4 py-1.5 rounded-xl text-xs font-bold transition-all {movingFilter ===
                        'all'
                            ? 'text-white shadow-sm'
                            : 'text-slate-500 bg-slate-100 hover:bg-slate-200'}"
                        style={movingFilter === 'all'
                            ? `background-color: ${primaryColor};`
                            : ''}
                    >
                        Semua ({(items as any[]).length})
                    </button>
                    {#each ['fast', 'medium', 'slow'] as const as cat}
                        {@const cfg = movingConfig[cat]}
                        {@const count = movingMetrics[cat].count}
                        <button
                            onclick={() =>
                                (movingFilter =
                                    movingFilter === cat ? 'all' : cat)}
                            class="px-4 py-1.5 rounded-xl text-xs font-bold transition-all border-2"
                            style={movingFilter === cat
                                ? `background-color: ${cfg.color}; border-color: ${cfg.color}; color: white;`
                                : `border-color: ${cfg.border}; color: ${cfg.color}; background: ${cfg.bg};`}
                        >
                            {cfg.emoji}
                            {cfg.label} ({count})
                        </button>
                    {/each}
                </div>
            {/if}

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse responsive-table pareto-report-table">
                    <thead>
                        <tr
                            class="bg-slate-50/50 text-[10px] font-bold text-slate-400 uppercase tracking-widest font-outfit border-b border-slate-100"
                        >
                            <th class="py-4 px-5 w-12">#</th>
                            <th class="py-4 px-5">Nama</th>
                            {#if getSubLabelHeader()}
                                <th class="py-4 px-5">{getSubLabelHeader()}</th>
                            {/if}
                            <th class="py-4 px-5 text-right">
                                {selectedType === 'customer_spending'
                                    ? 'Total Pesanan'
                                    : 'Qty Terjual'}
                            </th>
                            <th class="py-4 px-5 text-right"
                                >{getColumnHeader()}</th
                            >
                            <th class="py-4 px-5 text-right">Kontribusi</th>
                            <th class="py-4 px-5 text-right">Kumulatif</th>
                            <th class="py-4 px-5 text-center">Pareto</th>
                            {#if (items as any[]).length > 0}
                                <th class="py-4 px-5 text-center">Tier</th>
                            {/if}
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-slate-100 text-slate-700 text-sm font-medium"
                    >
                        {#if filteredItems.length === 0}
                            <tr>
                                <td
                                    colspan="9"
                                    class="py-12 text-center text-slate-400 font-bold font-outfit"
                                >
                                    <i
                                        class="ti ti-chart-bar-off text-3xl block mb-2 text-slate-300"
                                    ></i>
                                    Tidak ada data pada periode ini.
                                </td>
                            </tr>
                        {:else}
                            {#each filteredItems as item}
                                {@const movCat = (item as any)
                                    .moving_category as
                                    | keyof typeof movingConfig
                                    | undefined}
                                {@const movCfg = movCat
                                    ? movingConfig[movCat]
                                    : null}
                                <tr
                                    class="hover:bg-slate-50/50 transition {(
                                        item as any
                                    ).is_vital_few
                                        ? ''
                                        : 'opacity-60'}"
                                >
                                    <td class="py-3 px-5" data-label="Rank">
                                        <span
                                            class="w-7 h-7 rounded-lg text-xs font-black flex items-center justify-center"
                                            style={(item as any).is_vital_few
                                                ? `background: ${primaryColor}15; color: ${primaryColor};`
                                                : 'background: #f1f5f9; color: #94a3b8;'}
                                        >
                                            {(item as any).rank}
                                        </span>
                                    </td>
                                    <td
                                        class="py-3 px-5 font-bold text-slate-800 max-w-[240px]"
                                        data-label="Nama"
                                    >
                                        <span
                                            class="truncate block"
                                            title={(item as any).label}
                                            >{(item as any).label}</span
                                        >
                                    </td>
                                    {#if getSubLabelHeader()}
                                        <td
                                            class="py-3 px-5 text-slate-500 text-xs max-w-[160px]"
                                            data-label={getSubLabelHeader()}
                                        >
                                            <span
                                                class="truncate block"
                                                title={(item as any)
                                                    .category_name}
                                                >{(item as any).category_name ??
                                                    '-'}</span
                                            >
                                        </td>
                                    {/if}
                                    <td
                                        class="py-3 px-5 text-right text-slate-600 font-semibold"
                                        data-label={selectedType === 'customer_spending' ? 'Total Pesanan' : 'Qty Terjual'}
                                    >
                                        {formatNumber((item as any).qty_sold)}
                                        {#if selectedType !== 'customer_spending'}<span
                                                class="text-xs text-slate-400"
                                            >
                                                pcs</span
                                            >{/if}
                                        {#if selectedType === 'customer_spending'}<span
                                                class="text-xs text-slate-400"
                                            >
                                                pesanan</span
                                            >{/if}
                                    </td>
                                    <td
                                        class="py-3 px-5 text-right font-bold text-slate-800"
                                        data-label={getColumnHeader()}
                                    >
                                        {getValueLabel((item as any).value)}
                                    </td>
                                    <td class="py-3 px-5 text-right" data-label="Kontribusi">
                                        <span
                                            class="font-semibold text-slate-700"
                                            >{(item as any).percentage}%</span
                                        >
                                        <div
                                            class="w-full bg-slate-100 rounded-full h-1 mt-1 max-w-[80px] ml-auto"
                                        >
                                            <div
                                                class="h-1 rounded-full transition-all"
                                                style="width: {(item as any)
                                                    .percentage}%; background-color: {(
                                                    item as any
                                                ).is_vital_few
                                                    ? primaryColor
                                                    : '#94a3b8'};"
                                            ></div>
                                        </div>
                                    </td>
                                    <td class="py-3 px-5 text-right" data-label="Kumulatif">
                                        <span
                                            class="font-bold {(item as any)
                                                .cumulative_percentage <= 80
                                                ? 'text-emerald-600'
                                                : 'text-slate-400'}"
                                        >
                                            {(item as any)
                                                .cumulative_percentage}%
                                        </span>
                                    </td>
                                    <td class="py-3 px-5 text-center" data-label="Pareto">
                                        {#if (item as any).is_vital_few}
                                            <span
                                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider text-white"
                                                style="background-color: {primaryColor};"
                                            >
                                                <i class="ti ti-star text-xs"
                                                ></i> Vital
                                            </span>
                                        {:else}
                                            <span
                                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-slate-100 text-slate-400"
                                            >
                                                Trivial
                                            </span>
                                        {/if}
                                    </td>
                                    {#if (items as any[]).length > 0}
                                        <td class="py-3 px-5 text-center" data-label="Tier">
                                            {#if movCfg}
                                                <span
                                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider"
                                                    style="background-color: {movCfg.bg}; color: {movCfg.color}; border: 1px solid {movCfg.border};"
                                                >
                                                    <i
                                                        class="ti {movCfg.icon} text-xs"
                                                    ></i>
                                                    {movCfg.label}
                                                </span>
                                            {/if}
                                        </td>
                                    {/if}
                                </tr>
                            {/each}
                        {/if}
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</AdminLayout>

<style>
    @media (max-width: 640px) {
        .pareto-report-table td:first-child {
            display: flex !important;
        }
        .pareto-report-table td[data-label="Nama"] {
            flex-direction: column !important;
            align-items: flex-start !important;
            gap: 4px;
        }
        .pareto-report-table td[data-label="Nama"] > * {
            text-align: left !important;
            width: 100% !important;
        }
    }
</style>
