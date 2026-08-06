<script lang="ts">
    import { onMount } from 'svelte';

    let {
        show = false,
        onClose = () => {},
        selectedLocation = '',
        onSelectLocation = () => {},
        primary = '#fa7315',
    } = $props();

    let searchQuery = $state('');
    let tempSelected = $state('');
    let activeTab = $state<'all' | 'province' | 'city'>('all');
    let isFetchingApi = $state(false);
    let apiProvinces = $state<{ id: string; name: string }[]>([]);

    // Helper to capitalize words properly
    function formatLocationName(str: string) {
        if (!str) return '';
        return str
            .toLowerCase()
            .split(' ')
            .map((word) => {
                if (['dki', 'di', 'ntb', 'ntt'].includes(word)) return word.toUpperCase();
                return word.charAt(0).toUpperCase() + word.slice(1);
            })
            .join(' ');
    }

    // Static fallback database of Indonesian Provinces & Cities grouped by Letter
    const staticLocationGroups = [
        {
            letter: 'A',
            items: [
                { name: 'Aceh', type: 'province' },
                { name: 'Kota Ambon', type: 'city' },
                { name: 'Kab. Asahan', type: 'city' },
            ],
        },
        {
            letter: 'B',
            items: [
                { name: 'Bali', type: 'province' },
                { name: 'Bangka Belitung', type: 'province' },
                { name: 'Banten', type: 'province' },
                { name: 'Bengkulu', type: 'province' },
                { name: 'Kota Bandung', type: 'city' },
                { name: 'Kab. Bandung', type: 'city' },
                { name: 'Kota Batam', type: 'city' },
                { name: 'Kota Bekasi', type: 'city' },
                { name: 'Kab. Bekasi', type: 'city' },
                { name: 'Kota Bogor', type: 'city' },
                { name: 'Kab. Bogor', type: 'city' },
            ],
        },
        {
            letter: 'C',
            items: [
                { name: 'Kota Cilegon', type: 'city' },
                { name: 'Kota Cimahi', type: 'city' },
                { name: 'Kota Cirebon', type: 'city' },
                { name: 'Kab. Cirebon', type: 'city' },
                { name: 'Kab. Cianjur', type: 'city' },
            ],
        },
        {
            letter: 'D',
            items: [
                { name: 'DKI Jakarta', type: 'province' },
                { name: 'DI Yogyakarta', type: 'province' },
                { name: 'Dalam Negeri', type: 'province' },
                { name: 'Kota Depok', type: 'city' },
                { name: 'Kota Denpasar', type: 'city' },
                { name: 'Kab. Deli Serdang', type: 'city' },
            ],
        },
        {
            letter: 'G',
            items: [
                { name: 'Gorontalo', type: 'province' },
                { name: 'Kab. Garut', type: 'city' },
                { name: 'Kota Gresik', type: 'city' },
            ],
        },
        {
            letter: 'J',
            items: [
                { name: 'Jambi', type: 'province' },
                { name: 'Jawa Barat', type: 'province' },
                { name: 'Jawa Tengah', type: 'province' },
                { name: 'Jawa Timur', type: 'province' },
                { name: 'Jabodetabek', type: 'city' },
                { name: 'Jakarta Barat', type: 'city' },
                { name: 'Jakarta Pusat', type: 'city' },
                { name: 'Jakarta Selatan', type: 'city' },
                { name: 'Jakarta Timur', type: 'city' },
                { name: 'Jakarta Utara', type: 'city' },
                { name: 'Kota Jayapura', type: 'city' },
                { name: 'Kab. Jember', type: 'city' },
            ],
        },
        {
            letter: 'K',
            items: [
                { name: 'Kalimantan Barat', type: 'province' },
                { name: 'Kalimantan Selatan', type: 'province' },
                { name: 'Kalimantan Tengah', type: 'province' },
                { name: 'Kalimantan Timur', type: 'province' },
                { name: 'Kalimantan Utara', type: 'province' },
                { name: 'Kota Kediri', type: 'city' },
                { name: 'Kota Kendari', type: 'city' },
                { name: 'Kota Kupang', type: 'city' },
                { name: 'Kab. Karawang', type: 'city' },
                { name: 'Kab. Kudus', type: 'city' },
            ],
        },
        {
            letter: 'L',
            items: [
                { name: 'Lampung', type: 'province' },
                { name: 'Kota Madiun', type: 'city' },
                { name: 'Kota Magelang', type: 'city' },
                { name: 'Kota Makassar', type: 'city' },
                { name: 'Kota Malang', type: 'city' },
                { name: 'Kota Manado', type: 'city' },
                { name: 'Kota Mataram', type: 'city' },
                { name: 'Medan', type: 'city' },
                { name: 'Kota Mojokerto', type: 'city' },
            ],
        },
        {
            letter: 'N',
            items: [
                { name: 'Nusa Tenggara Barat', type: 'province' },
                { name: 'Nusa Tenggara Timur', type: 'province' },
            ],
        },
        {
            letter: 'P',
            items: [
                { name: 'Papua', type: 'province' },
                { name: 'Papua Barat', type: 'province' },
                { name: 'Papua Pegunungan', type: 'province' },
                { name: 'Papua Selatan', type: 'province' },
                { name: 'Papua Tengah', type: 'province' },
                { name: 'Kota Padang', type: 'city' },
                { name: 'Kota Palembang', type: 'city' },
                { name: 'Kota Palu', type: 'city' },
                { name: 'Kota Pekanbaru', type: 'city' },
                { name: 'Kota Pontianak', type: 'city' },
                { name: 'Kota Probolinggo', type: 'city' },
            ],
        },
        {
            letter: 'R',
            items: [
                { name: 'Riau', type: 'province' },
                { name: 'Kepulauan Riau', type: 'province' },
            ],
        },
        {
            letter: 'S',
            items: [
                { name: 'Sulawesi Barat', type: 'province' },
                { name: 'Sulawesi Selatan', type: 'province' },
                { name: 'Sulawesi Tengah', type: 'province' },
                { name: 'Sulawesi Tenggara', type: 'province' },
                { name: 'Sulawesi Utara', type: 'province' },
                { name: 'Sumatra Barat', type: 'province' },
                { name: 'Sumatra Selatan', type: 'province' },
                { name: 'Sumatra Utara', type: 'province' },
                { name: 'Kota Samarinda', type: 'city' },
                { name: 'Semarang', type: 'city' },
                { name: 'Kota Serang', type: 'city' },
                { name: 'Kota Solo / Surakarta', type: 'city' },
                { name: 'Surabaya', type: 'city' },
                { name: 'Kab. Sidoarjo', type: 'city' },
                { name: 'Kab. Sukabumi', type: 'city' },
            ],
        },
        {
            letter: 'T',
            items: [
                { name: 'Kota Tangerang', type: 'city' },
                { name: 'Kota Tangerang Selatan', type: 'city' },
                { name: 'Kab. Tangerang', type: 'city' },
                { name: 'Kota Tasikmalaya', type: 'city' },
                { name: 'Kota Tegal', type: 'city' },
                { name: 'Kota Ternate', type: 'city' },
            ],
        },
    ];

    // Fetch Provinces dynamically from Emsifa API Wilayah Indonesia
    async function loadApiProvinces() {
        if (apiProvinces.length > 0) return;
        isFetchingApi = true;
        try {
            const res = await fetch(
                'https://www.emsifa.com/api-wilayah-indonesia/api/provinces.json',
            );
            if (res.ok) {
                const data = await res.json();
                apiProvinces = data.map((p: any) => ({
                    id: p.id,
                    name: formatLocationName(p.name),
                }));
            }
        } catch (e) {
            console.error('Failed to load provinces API', e);
        } finally {
            isFetchingApi = false;
        }
    }

    // Sync tempSelected with selectedLocation when modal opens
    $effect(() => {
        if (show) {
            tempSelected = selectedLocation || '';
            searchQuery = '';
            loadApiProvinces();
        }
    });

    // Merged & Grouped items
    const allGroups = $derived.by(() => {
        // Clone static groups
        const map = new Map<string, { name: string; type: 'province' | 'city' }[]>();

        // Add static locations
        staticLocationGroups.forEach((g) => {
            map.set(g.letter, [...g.items]);
        });

        // Merge API Provinces if fetched
        apiProvinces.forEach((p) => {
            const name = p.name;
            const letter = name.charAt(0).toUpperCase();
            const current = map.get(letter) || [];
            if (!current.some((item) => item.name.toLowerCase() === name.toLowerCase())) {
                current.push({ name, type: 'province' });
                current.sort((a, b) => a.name.localeCompare(b.name));
                map.set(letter, current);
            }
        });

        // Convert Map to sorted array of groups
        const result: { letter: string; items: { name: string; type: 'province' | 'city' }[] }[] = [];
        Array.from(map.keys())
            .sort()
            .forEach((letter) => {
                result.push({
                    letter,
                    items: map.get(letter) || [],
                });
            });
        return result;
    });

    // Filtered Groups based on search query & tab filter
    const filteredGroups = $derived.by(() => {
        const q = searchQuery.toLowerCase().trim();

        return allGroups
            .map((group) => ({
                letter: group.letter,
                items: group.items.filter((item) => {
                    const matchesSearch = !q || item.name.toLowerCase().includes(q);
                    const matchesTab =
                        activeTab === 'all' ||
                        (activeTab === 'province' && item.type === 'province') ||
                        (activeTab === 'city' && item.type === 'city');
                    return matchesSearch && matchesTab;
                }),
            }))
            .filter((group) => group.items.length > 0);
    });

    function toggleSelect(loc: string) {
        if (tempSelected === loc) {
            tempSelected = '';
        } else {
            tempSelected = loc;
        }
    }

    function handleReset() {
        tempSelected = '';
    }

    function handleConfirm() {
        onSelectLocation(tempSelected);
        onClose();
    }
</script>

{#if show}
    <div class="fixed inset-0 z-[300] flex items-center justify-center p-3 sm:p-6 select-none">
        <!-- Backdrop -->
        <button
            type="button"
            aria-label="Tutup Modal Lokasi"
            onclick={onClose}
            class="absolute inset-0 bg-black/60 backdrop-blur-xs w-full h-full cursor-default border-0"
        ></button>

        <!-- Modal Dialog (Elevated on mobile with mb-16 sm:mb-0 so footer is never obscured) -->
        <div class="relative w-full max-w-2xl bg-white dark:bg-slate-900 rounded-3xl shadow-2xl flex flex-col max-h-[78vh] sm:max-h-[85vh] mb-16 sm:mb-0 overflow-hidden z-10 border border-slate-100 dark:border-slate-800">
            <!-- Modal Header -->
            <div class="px-5 sm:px-6 py-3.5 sm:py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between gap-3 shrink-0 bg-white dark:bg-slate-900 z-10">
                <h3 class="text-base font-bold text-slate-800 dark:text-white font-outfit shrink-0">
                    Lokasi
                </h3>

                <!-- Search Input -->
                <div class="relative flex-1 max-w-md">
                    <input
                        type="text"
                        bind:value={searchQuery}
                        placeholder="Cari Provinsi & Kota..."
                        class="w-full pl-3.5 pr-9 py-2 text-xs bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-slate-300 text-slate-800 dark:text-white"
                    />
                    <i class="ti ti-search absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                </div>

                <!-- Close Button -->
                <button
                    type="button"
                    onclick={onClose}
                    class="w-8 h-8 rounded-full flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                    aria-label="Tutup"
                >
                    <i class="ti ti-x text-lg"></i>
                </button>
            </div>

            <!-- Tab Filters (Semua | Provinsi | Kota/Kabupaten) -->
            <div class="px-6 py-2 bg-slate-50/70 dark:bg-slate-800/40 border-b border-slate-100 dark:border-slate-800 flex items-center gap-2 overflow-x-auto scrollbar-none shrink-0">
                <button
                    type="button"
                    onclick={() => (activeTab = 'all')}
                    class="px-3 py-1 rounded-lg text-xs font-bold transition shrink-0
                           {activeTab === 'all'
                        ? 'bg-slate-900 text-white shadow-xs'
                        : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/50'}"
                >
                    Semua Lokasi
                </button>
                <button
                    type="button"
                    onclick={() => (activeTab = 'province')}
                    class="px-3 py-1 rounded-lg text-xs font-bold transition shrink-0 flex items-center gap-1.5
                           {activeTab === 'province'
                        ? 'bg-slate-900 text-white shadow-xs'
                        : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/50'}"
                >
                    <i class="ti ti-map text-xs"></i>
                    Provinsi ({apiProvinces.length || 38})
                </button>
                <button
                    type="button"
                    onclick={() => (activeTab = 'city')}
                    class="px-3 py-1 rounded-lg text-xs font-bold transition shrink-0 flex items-center gap-1.5
                           {activeTab === 'city'
                        ? 'bg-slate-900 text-white shadow-xs'
                        : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/50'}"
                >
                    <i class="ti ti-building text-xs"></i>
                    Kota / Kabupaten
                </button>
            </div>

            <!-- Modal Content (Alphabetical Groups & Items) -->
            <div class="flex-1 overflow-y-auto p-5 sm:p-6 space-y-6 scrollbar-thin">
                {#if isFetchingApi}
                    <div class="py-3 text-center text-slate-400 text-xs font-medium flex items-center justify-center gap-2">
                        <i class="ti ti-loader animate-spin text-sm"></i>
                        Memuat data wilayah Indonesia...
                    </div>
                {/if}

                {#if filteredGroups.length === 0 && !isFetchingApi}
                    <div class="py-12 text-center text-slate-400 text-xs font-medium">
                        Tidak ada lokasi yang cocok dengan "{searchQuery}"
                    </div>
                {:else}
                    {#each filteredGroups as group}
                        <div class="space-y-3">
                            <h4 class="text-xs font-black text-slate-400 uppercase tracking-widest border-b border-slate-100 dark:border-slate-800 pb-1">
                                {group.letter}
                            </h4>
                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2">
                                {#each group.items as item}
                                    <button
                                        type="button"
                                        onclick={() => toggleSelect(item.name)}
                                        class="flex items-center gap-2 px-3 py-2 rounded-xl text-xs font-bold text-left transition border group
                                               {tempSelected === item.name
                                            ? 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-400 shadow-xs'
                                            : 'bg-slate-50/70 dark:bg-slate-800/50 text-slate-600 dark:text-slate-300 border-slate-200/70 dark:border-slate-700/70 hover:border-slate-300'}"
                                    >
                                        <div
                                            class="w-4 h-4 rounded-md border flex items-center justify-center shrink-0 transition
                                                   {tempSelected === item.name
                                                ? 'bg-amber-500 border-amber-500 text-white'
                                                : 'border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800'}"
                                        >
                                            {#if tempSelected === item.name}
                                                <i class="ti ti-check text-[10px] font-bold"></i>
                                            {/if}
                                        </div>
                                        <span class="truncate">{item.name}</span>
                                    </button>
                                {/each}
                            </div>
                        </div>
                    {/each}
                {/if}
            </div>

            <!-- Modal Footer (RESET & KONFIRMASI) -->
            <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-end gap-3 shrink-0 bg-white dark:bg-slate-900 z-10">
                <button
                    type="button"
                    onclick={handleReset}
                    class="px-5 py-2.5 rounded-xl text-xs font-bold text-slate-500 hover:text-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 active:scale-95 transition"
                >
                    RESET
                </button>
                <button
                    type="button"
                    onclick={handleConfirm}
                    class="px-6 py-2.5 rounded-xl text-xs font-bold text-white shadow-md active:scale-95 transition"
                    style="background-color: {primary};"
                >
                    KONFIRMASI
                </button>
            </div>
        </div>
    </div>
{/if}
