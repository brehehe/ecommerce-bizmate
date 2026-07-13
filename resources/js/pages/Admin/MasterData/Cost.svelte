<script lang="ts">
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { page, useForm } from '@inertiajs/svelte';
    import { showToast } from '@/utils/toast';
    import Toggle from '@/components/ui/Toggle.svelte';
    import { slide } from 'svelte/transition';

    let {
        settings = {} as any,
    } = $props();

    const primaryColor = $derived(
        (page.props as any).theme?.primary_color ?? '#0c4cb4',
    );

    // Parse additional costs JSON safely
    let initialCosts: any[] = [];
    try {
        if (settings.additional_costs) {
            initialCosts = typeof settings.additional_costs === 'string'
                ? JSON.parse(settings.additional_costs)
                : settings.additional_costs;
        }
    } catch (e) {
        console.error('Failed to parse additional costs:', e);
    }

    // Form to manage operational costs and dynamic fee items
    const form = useForm({
        shipping_rate: Number(settings.shipping_rate || 0),
        self_pickup_enabled: settings.self_pickup_enabled === 'true' || settings.self_pickup_enabled === true || settings.self_pickup_enabled === '1',
        self_pickup_fee: Number(settings.self_pickup_fee || 0),
        additional_costs: Array.isArray(initialCosts) ? initialCosts : [],
    });

    function addCostRow() {
        form.additional_costs = [
            ...form.additional_costs,
            {
                id: 'cost_' + Math.random().toString(36).substring(2, 9),
                name: '',
                value: 0,
                is_active: true,
            }
        ];
    }

    function removeCostRow(id: string) {
        form.additional_costs = form.additional_costs.filter((item: any) => item.id !== id);
    }

    function handleSubmit(e: Event) {
        e.preventDefault();
        
        // Clean dynamic rows where name is empty
        const cleanedCosts = form.additional_costs.filter((c: any) => c.name.trim() !== '');

        form.transform((data: any) => ({
            ...data,
            additional_costs: JSON.stringify(cleanedCosts),
        })).post('/admin/master-data/cost', {
            preserveScroll: true,
            onSuccess: () => {
                showToast('Pengaturan biaya berhasil disimpan.', 'success');
            },
            onError: () => {
                showToast('Gagal menyimpan pengaturan biaya.', 'error');
            }
        });
    }
</script>

<svelte:head>
    <title>Master Biaya — Admin</title>
</svelte:head>

<AdminLayout>
    <main class="w-full max-w-[1600px] mx-auto px-4 sm:px-6 py-6 space-y-5">
        <!-- Page Header -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-semibold tracking-tight text-slate-900">Master Biaya</h1>
                <p class="mt-0.5 text-sm text-slate-500">Kelola semua jenis biaya transaksi dan biaya operasional toko</p>
            </div>
            <button
                type="button"
                onclick={addCostRow}
                class="h-9 px-4 text-xs font-semibold text-white rounded-lg transition-opacity hover:opacity-90 flex items-center justify-center gap-1.5 cursor-pointer shadow-xs"
                style="background-color: {primaryColor};"
            >
                <i class="ti ti-plus text-sm"></i>
                <span>Tambah Biaya Baru</span>
            </button>
        </div>

        <!-- Main Form & Table Card Container -->
        <form onsubmit={handleSubmit} class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <!-- Table Section -->
            <div class="overflow-x-auto">
                <table class="w-full responsive-table">
                    <thead>
                        <tr class="border-b border-slate-100 bg-slate-50/50">
                            <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Nama Biaya</th>
                            <th class="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400 w-64">Jumlah Biaya</th>
                            <th class="px-4 py-3 text-center text-[10px] font-semibold uppercase tracking-wider text-slate-400 w-36">Status</th>
                            <th class="px-4 py-3 text-center text-[10px] font-semibold uppercase tracking-wider text-slate-400 w-24">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <!-- Fixed Row: Biaya Aplikasi -->
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-4 py-3.5">
                                <div class="flex flex-col">
                                    <span class="text-sm font-semibold text-slate-700">Biaya Aplikasi (Platform Fee)</span>
                                    <span class="text-[10px] text-slate-400 font-normal">Biaya operasional aplikasi tetap per transaksi.</span>
                                </div>
                            </td>
                            <td class="px-4 py-3.5">
                                <div class="relative w-full max-w-xs">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs font-bold font-mono">Rp</span>
                                    <input
                                        type="number"
                                        min="0"
                                        bind:value={form.shipping_rate}
                                        class="w-full pl-8 pr-3 py-1.5 border border-slate-200 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 rounded-lg text-xs font-medium focus:outline-none transition bg-white"
                                        required
                                    />
                                </div>
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-600">
                                    <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                                    Sistem Aktif
                                </span>
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                <span class="text-xs text-slate-300 font-medium">—</span>
                            </td>
                        </tr>

                        <!-- Dynamic Custom Rows -->
                        {#each form.additional_costs as cost, index (cost.id)}
                            <tr class="hover:bg-slate-50/50 transition-colors" transition:slide={{ duration: 150 }}>
                                <td class="px-4 py-3">
                                    <input
                                        type="text"
                                        bind:value={cost.name}
                                        placeholder="Contoh: Biaya Packing Kayu"
                                        class="w-full px-3 py-1.5 border border-slate-200 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 rounded-lg text-xs font-medium focus:outline-none transition bg-white"
                                        required
                                    />
                                </td>
                                <td class="px-4 py-3">
                                    <div class="relative w-full max-w-xs">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs font-bold font-mono">Rp</span>
                                        <input
                                            type="number"
                                            min="0"
                                            bind:value={cost.value}
                                            class="w-full pl-8 pr-3 py-1.5 border border-slate-200 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 rounded-lg text-xs font-medium focus:outline-none transition bg-white"
                                            required
                                        />
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <label class="inline-flex items-center cursor-pointer justify-center">
                                        <input
                                            type="checkbox"
                                            bind:checked={cost.is_active}
                                            class="sr-only peer"
                                        />
                                        <div class="relative w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-500"></div>
                                    </label>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <button
                                        type="button"
                                        onclick={() => removeCostRow(cost.id)}
                                        class="p-1 text-slate-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition"
                                        title="Hapus Biaya"
                                    >
                                        <i class="ti ti-trash text-base"></i>
                                    </button>
                                </td>
                            </tr>
                        {/each}

                        <!-- Empty State Table Helper -->
                        {#if form.additional_costs.length === 0}
                            <tr>
                                <td colspan="4" class="px-4 py-6 text-center text-slate-400 font-medium text-xs bg-slate-50/20">
                                    Belum ada biaya tambahan kustom. Klik "Tambah Biaya Baru" untuk mendefinisikan biaya lainnya.
                                </td>
                            </tr>
                        {/if}
                    </tbody>
                </table>
            </div>

            <!-- Self Pickup Handling Options Panel -->
            <div class="p-5 border-t border-slate-100 bg-slate-50/20 space-y-4">
                <Toggle
                    bind:checked={form.self_pickup_enabled}
                    label="Aktifkan Pengambilan di Toko (Self-Pickup)"
                    icon="ti-building-store"
                />
                <p class="text-[10px] text-slate-400 font-medium">
                    Aktifkan metode ini jika Anda ingin pelanggan dapat mengambil sendiri pesanannya di toko fisik Anda.
                </p>

                {#if form.self_pickup_enabled}
                    <div class="pl-6 border-l-2 border-slate-200 mt-2 grid grid-cols-1 md:grid-cols-2 gap-6" transition:slide={{ duration: 200 }}>
                        <div class="space-y-1.5">
                            <label for="input-self-pickup-fee" class="block text-xs font-bold text-slate-600">
                                Biaya Penanganan Ambil di Toko
                            </label>
                            <div class="relative max-w-xs">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs font-bold font-mono">Rp</span>
                                <input
                                    id="input-self-pickup-fee"
                                    type="number"
                                    min="0"
                                    bind:value={form.self_pickup_fee}
                                    class="w-full pl-8 pr-3 py-1.5 border border-slate-200 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 rounded-lg text-xs font-medium focus:outline-none transition bg-white"
                                    required
                                />
                            </div>
                            <p class="text-[10px] text-slate-400 font-medium">
                                Biaya administrasi atau pengemasan khusus ketika pelanggan memilih untuk mengambil pesanan di outlet Anda.
                            </p>
                        </div>
                    </div>
                {/if}
            </div>

            <!-- Save Changes Card Footer -->
            <div class="p-4 border-t border-slate-100 bg-slate-50/40 flex items-center justify-end">
                <button
                    type="submit"
                    disabled={form.processing}
                    class="h-9 px-5 rounded-lg text-white text-xs font-semibold transition flex items-center gap-1.5 cursor-pointer shadow-xs"
                    style="background-color: {primaryColor};"
                >
                    {#if form.processing}
                        <span class="w-3.5 h-3.5 border-2 border-white/35 border-t-white rounded-full animate-spin"></span>
                        Menyimpan...
                    {:else}
                        <i class="ti ti-device-floppy text-sm"></i>
                        Simpan Perubahan
                    {/if}
                </button>
            </div>
        </form>
    </main>
</AdminLayout>
