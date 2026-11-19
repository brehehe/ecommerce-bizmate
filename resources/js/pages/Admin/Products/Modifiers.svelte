<script lang="ts">
    import { usePage, router, Link } from '@inertiajs/svelte';
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { showToast } from '@/utils/toast';

    let { product, storeName = '' } = $props();

    const page = usePage();
    const primary = $derived(
        (page.props as any).theme?.primary_color ?? '#0c4cb4',
    );
    let showModal = $state(false);
    let editingModifierId = $state<string | null>(null);

    let formGroupName = $state('');
    let formIsRequired = $state(false);
    let formIsMultiple = $state(false);
    let formMaxSelectable = $state(5);
    let formItems = $state<
        Array<{
            name: string;
            additional_price: number;
            is_default: boolean;
            is_available: boolean;
        }>
    >([
        {
            name: '',
            additional_price: 0,
            is_default: false,
            is_available: true,
        },
    ]);
    let isSubmitting = $state(false);

    function formatRupiah(amount: number) {
        return 'Rp ' + Number(amount || 0).toLocaleString('id-ID');
    }

    function openCreateModal() {
        editingModifierId = null;
        formGroupName = '';
        formIsRequired = false;
        formIsMultiple = false;
        formMaxSelectable = 5;
        formItems = [
            {
                name: '',
                additional_price: 0,
                is_default: false,
                is_available: true,
            },
            {
                name: '',
                additional_price: 0,
                is_default: false,
                is_available: true,
            },
        ];
        showModal = true;
    }

    function openEditModal(modifier: any) {
        editingModifierId = modifier.id;
        formGroupName = modifier.group_name;
        formIsRequired = modifier.is_required;
        formIsMultiple = modifier.is_multiple;
        formMaxSelectable = modifier.max_selectable || 5;
        formItems = modifier.items.map((it: any) => ({
            name: it.name,
            additional_price: Number(it.additional_price || 0),
            is_default: Boolean(it.is_default),
            is_available: it.is_available !== false,
        }));
        showModal = true;
    }

    function addItemRow() {
        formItems = [
            ...formItems,
            {
                name: '',
                additional_price: 0,
                is_default: false,
                is_available: true,
            },
        ];
    }

    function removeItemRow(index: number) {
        if (formItems.length <= 1) {
            showToast('Minimal harus ada 1 opsi pilihan.', 'error');

            return;
        }

        formItems = formItems.filter((_, i) => i !== index);
    }

    function handleSaveModifier() {
        if (!formGroupName.trim()) {
            showToast('Nama grup kustomisasi wajib diisi.', 'error');

            return;
        }

        const validItems = formItems.filter((it) => it.name.trim() !== '');

        if (validItems.length === 0) {
            showToast('Minimal harus ada 1 pilihan opsi dengan nama.', 'error');

            return;
        }

        isSubmitting = true;
        const payload = {
            group_name: formGroupName.trim(),
            is_required: formIsRequired,
            is_multiple: formIsMultiple,
            max_selectable: formIsMultiple ? formMaxSelectable : 1,
            items: validItems,
        };

        if (editingModifierId) {
            router.put(
                `/admin/products/${product.id}/modifiers/${editingModifierId}`,
                payload,
                {
                    preserveScroll: true,
                    onFinish: () => {
                        isSubmitting = false;
                    },
                    onSuccess: () => {
                        showModal = false;
                        showToast(
                            'Kustomisasi berhasil diperbarui!',
                            'success',
                        );
                    },
                    onError: (errs) => {
                        const firstErr = Object.values(errs)[0];
                        showToast(
                            firstErr || 'Gagal memperbarui kustomisasi.',
                            'error',
                        );
                    },
                },
            );
        } else {
            router.post(`/admin/products/${product.id}/modifiers`, payload, {
                preserveScroll: true,
                onFinish: () => {
                    isSubmitting = false;
                },
                onSuccess: () => {
                    showModal = false;
                    showToast(
                        'Kustomisasi menu berhasil ditambahkan!',
                        'success',
                    );
                },
                onError: (errs) => {
                    const firstErr = Object.values(errs)[0];
                    showToast(
                        firstErr || 'Gagal menambahkan kustomisasi.',
                        'error',
                    );
                },
            });
        }
    }

    function handleDeleteModifier(modifierId: string) {
        if (
            !confirm('Apakah Anda yakin ingin menghapus grup kustomisasi ini?')
        ) {
            return;
        }

        router.delete(`/admin/products/${product.id}/modifiers/${modifierId}`, {
            preserveScroll: true,
            onSuccess: () =>
                showToast('Grup kustomisasi berhasil dihapus.', 'success'),
            onError: () => showToast('Gagal menghapus kustomisasi.', 'error'),
        });
    }
</script>

<svelte:head>
    <title>Kustomisasi Menu: {product.name} — {storeName}</title>
</svelte:head>

<AdminLayout>
    <main class="w-full max-w-[1600px] mx-auto px-4 sm:px-6 py-6 space-y-5">
        <!-- ── Page Header & Breadcrumbs ───────────────────────────── -->
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <div
                    class="flex items-center gap-1.5 text-xs text-slate-500 mb-1"
                >
                    <Link
                        href="/admin/products"
                        class="hover:text-slate-800 transition-colors"
                        >Katalog Produk</Link
                    >
                    <span>/</span>
                    <span class="text-slate-800 font-medium"
                        >Kustomisasi Menu</span
                    >
                </div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1
                        class="text-xl font-semibold tracking-tight text-slate-900"
                    >
                        Kustomisasi Menu: {product.name}
                    </h1>
                    <span
                        class="text-xs font-semibold text-slate-700 bg-slate-100 px-2.5 py-0.5 rounded-full"
                    >
                        {formatRupiah(product.price)}
                    </span>
                    {#if product.sku}
                        <span class="text-xs font-mono text-slate-400">
                            SKU: {product.sku}
                        </span>
                    {/if}
                </div>
                <p class="mt-0.5 text-sm text-slate-500">
                    Atur variasi pilihan, level pedas, pilihan nasi, dan extra
                    topping untuk menu ini
                </p>
            </div>

            <div class="flex items-center gap-2.5">
                <Link
                    href="/admin/products"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 shadow-2xs hover:bg-slate-50 transition-colors"
                >
                    <i class="ti ti-arrow-left text-base"></i>
                    Kembali
                </Link>
                <button
                    onclick={openCreateModal}
                    class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold text-white shadow-xs transition-opacity hover:opacity-90 active:scale-95 cursor-pointer"
                    style="background-color: {primary};"
                    type="button"
                >
                    <i class="ti ti-plus text-base"></i>
                    Tambah Grup Kustomisasi
                </button>
            </div>
        </div>

        <!-- ── Modifier Groups List ──────────────────────────────────── -->
        {#if !product.modifiers || product.modifiers.length === 0}
            <div
                class="bg-white p-12 text-center rounded-xl border border-slate-200 shadow-2xs"
            >
                <div
                    class="w-12 h-12 mx-auto rounded-full bg-slate-100 text-slate-500 flex items-center justify-center text-xl mb-3"
                >
                    <i class="ti ti-tools-kitchen-2"></i>
                </div>
                <h3 class="text-sm font-semibold text-slate-900">
                    Belum Ada Kustomisasi Khusus
                </h3>
                <p class="text-xs text-slate-500 mt-1 max-w-md mx-auto">
                    Menu ini saat ini disajikan secara standar tanpa pilihan
                    tambahan. Anda dapat menambahkan grup kustomisasi (seperti
                    Pilihan Level Pedas, Pilihan Nasi, Saus, atau Topping
                    Ekstra) yang dapat dipilih pelanggan saat memesan.
                </p>
                <button
                    onclick={openCreateModal}
                    class="inline-flex items-center gap-2 mt-4 px-4 py-2 rounded-lg text-sm font-semibold text-white shadow-xs transition-opacity hover:opacity-90 cursor-pointer"
                    style="background-color: {primary};"
                    type="button"
                >
                    <i class="ti ti-plus text-base"></i>
                    <span>Buat Grup Kustomisasi Pertama</span>
                </button>
            </div>
        {:else}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                {#each product.modifiers as modifier (modifier.id)}
                    <div
                        class="bg-white rounded-xl border border-slate-200 shadow-2xs hover:border-slate-300 transition-colors flex flex-col justify-between overflow-hidden"
                    >
                        <div class="p-4">
                            <div
                                class="flex items-start justify-between gap-2 pb-3 border-b border-slate-100"
                            >
                                <div>
                                    <h3
                                        class="font-semibold text-sm text-slate-900"
                                    >
                                        {modifier.group_name}
                                    </h3>
                                    <div
                                        class="flex items-center gap-1.5 mt-1.5"
                                    >
                                        <span
                                            class="text-[10px] font-semibold px-2 py-0.5 rounded-full {modifier.is_required
                                                ? 'bg-rose-50 text-rose-700 border border-rose-100'
                                                : 'bg-slate-100 text-slate-600'}"
                                        >
                                            {modifier.is_required
                                                ? 'Wajib Pilih 1'
                                                : 'Opsional'}
                                        </span>
                                        <span
                                            class="text-[10px] font-semibold px-2 py-0.5 rounded-full {modifier.is_multiple
                                                ? 'bg-blue-50 text-blue-700 border border-blue-100'
                                                : 'bg-amber-50 text-amber-700 border border-amber-100'}"
                                        >
                                            {modifier.is_multiple
                                                ? 'Bisa Banyak'
                                                : 'Pilihan Tunggal'}
                                        </span>
                                    </div>
                                </div>

                                <div class="flex items-center gap-1">
                                    <button
                                        onclick={() => openEditModal(modifier)}
                                        class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors cursor-pointer"
                                        title="Edit Grup"
                                    >
                                        <i class="ti ti-pencil text-sm"></i>
                                    </button>
                                    <button
                                        onclick={() =>
                                            handleDeleteModifier(modifier.id)}
                                        class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer"
                                        title="Hapus Grup"
                                    >
                                        <i class="ti ti-trash text-sm"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Items List -->
                            <div class="mt-3 space-y-1.5">
                                {#each modifier.items as item (item.id)}
                                    <div
                                        class="flex items-center justify-between text-xs py-1.5 px-2.5 rounded-lg bg-slate-50 border border-slate-100"
                                    >
                                        <span
                                            class="font-medium text-slate-800 truncate"
                                        >
                                            {item.name}
                                            {#if item.is_default}
                                                <span
                                                    class="text-[10px] text-slate-500 font-semibold ml-1"
                                                    >(Default)</span
                                                >
                                            {/if}
                                        </span>
                                        <span
                                            class="font-mono text-xs font-semibold text-slate-600 shrink-0"
                                        >
                                            {item.additional_price > 0
                                                ? `+${formatRupiah(item.additional_price)}`
                                                : '+Rp 0'}
                                        </span>
                                    </div>
                                {/each}
                            </div>
                        </div>

                        <div
                            class="px-4 py-2.5 border-t border-slate-100 bg-slate-50/50 text-xs text-slate-500 flex items-center justify-between"
                        >
                            <span>{modifier.items.length} pilihan opsi</span>
                            <span
                                >{modifier.is_multiple
                                    ? `Maks. ${modifier.max_selectable} item`
                                    : 'Radio'}</span
                            >
                        </div>
                    </div>
                {/each}
            </div>
        {/if}
    </main>

    <!-- ── Add/Edit Modifier Modal ──────────────────────────────── -->
    {#if showModal}
        <div
            class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 p-0 backdrop-blur-xs sm:items-center sm:p-4"
            role="presentation"
        >
            <div
                class="flex max-h-[95dvh] w-full max-w-2xl flex-col overflow-hidden rounded-t-2xl border border-slate-200 bg-white shadow-xl sm:max-h-[90dvh] sm:rounded-2xl"
                role="dialog"
                aria-modal="true"
                aria-labelledby="modifier-modal-title"
            >
                <div
                    class="flex shrink-0 items-center justify-between border-b border-slate-100 bg-slate-50/60 px-4 py-4 sm:px-5"
                >
                    <h3
                        class="text-base font-semibold text-slate-900 flex items-center gap-2"
                    >
                        <i class="ti ti-tools-kitchen-2 text-slate-700"></i>
                        <span id="modifier-modal-title"
                            >{editingModifierId
                                ? 'Edit Grup Kustomisasi'
                                : 'Tambah Grup Kustomisasi Menu'}</span
                        >
                    </h3>
                    <button
                        onclick={() => (showModal = false)}
                        class="flex h-11 w-11 shrink-0 cursor-pointer items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 sm:h-9 sm:w-9"
                        type="button"
                        aria-label="Tutup modal"
                    >
                        <i class="ti ti-x text-lg"></i>
                    </button>
                </div>

                <div
                    class="min-h-0 flex-1 space-y-5 overflow-y-auto p-4 sm:p-5"
                >
                    <!-- Group Name -->
                    <div>
                        <label
                            for="group_name_input"
                            class="block text-xs font-semibold text-slate-700 mb-1"
                        >
                            Nama Grup Kustomisasi <span class="text-rose-500"
                                >*</span
                            >
                        </label>
                        <input
                            id="group_name_input"
                            type="text"
                            bind:value={formGroupName}
                            placeholder="Contoh: Pilihan Level Pedas, Ekstra Topping..."
                            class="h-11 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 placeholder-slate-400 focus:border-slate-400 focus:outline-none transition-colors sm:h-9"
                            required
                        />
                    </div>

                    <!-- Type Toggles -->
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <label
                            class="flex items-start gap-2.5 p-3 rounded-lg border border-slate-200 hover:bg-slate-50 transition-colors cursor-pointer"
                        >
                            <input
                                type="checkbox"
                                bind:checked={formIsRequired}
                                class="mt-0.5 rounded text-slate-900 focus:ring-0"
                            />
                            <div>
                                <div
                                    class="text-xs font-semibold text-slate-800"
                                >
                                    Wajib Dipilih
                                </div>
                                <div class="text-[11px] text-slate-500">
                                    Pelanggan harus memilih minimal 1 opsi
                                </div>
                            </div>
                        </label>

                        <label
                            class="flex items-start gap-2.5 p-3 rounded-lg border border-slate-200 hover:bg-slate-50 transition-colors cursor-pointer"
                        >
                            <input
                                type="checkbox"
                                bind:checked={formIsMultiple}
                                class="mt-0.5 rounded text-slate-900 focus:ring-0"
                            />
                            <div>
                                <div
                                    class="text-xs font-semibold text-slate-800"
                                >
                                    Pilih Banyak
                                </div>
                                <div class="text-[11px] text-slate-500">
                                    Bisa memilih lebih dari 1 opsi
                                </div>
                            </div>
                        </label>
                    </div>

                    <!-- Items Rows -->
                    <div class="space-y-3 border-t border-slate-100 pt-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold text-slate-700">
                                    Daftar Opsi Pilihan & Tambahan Harga
                                </p>
                                <p class="mt-0.5 text-[11px] text-slate-500">
                                    Harga tambahan boleh diisi 0 jika tidak ada
                                    biaya.
                                </p>
                            </div>
                            <button
                                type="button"
                                onclick={addItemRow}
                                class="inline-flex h-11 shrink-0 cursor-pointer items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 shadow-2xs transition-colors hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900 sm:h-9"
                            >
                                <i class="ti ti-plus"></i>
                                <span>Tambah Opsi</span>
                            </button>
                        </div>

                        <div class="space-y-3">
                            {#each formItems as item, idx (item)}
                                <div
                                    class="grid grid-cols-[minmax(0,1fr)_2.75rem] items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 p-3 sm:grid-cols-[minmax(0,1fr)_10rem_2.75rem]"
                                >
                                    <div
                                        class="col-start-1 row-start-1 min-w-0"
                                    >
                                        <label
                                            class="sr-only"
                                            for={`modifier-item-name-${idx}`}
                                        >
                                            Nama opsi {idx + 1}
                                        </label>
                                        <input
                                            id={`modifier-item-name-${idx}`}
                                            type="text"
                                            bind:value={item.name}
                                            placeholder="Nama opsi (misal: Level 2 / Tambah Telur)"
                                            class="h-11 w-full min-w-0 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-800 transition-colors placeholder:text-slate-400 focus:border-slate-400 focus:outline-none sm:h-10"
                                        />
                                    </div>
                                    <div
                                        class="col-start-1 row-start-2 min-w-0 sm:col-start-2 sm:row-start-1"
                                    >
                                        <label
                                            class="sr-only"
                                            for={`modifier-item-price-${idx}`}
                                        >
                                            Tambahan harga opsi {idx + 1}
                                        </label>
                                        <div
                                            class="flex h-11 min-w-0 overflow-hidden rounded-lg border border-slate-200 bg-white transition-colors focus-within:border-slate-400 sm:h-10"
                                        >
                                            <span
                                                class="flex shrink-0 items-center border-r border-slate-200 bg-slate-100 px-2.5 text-xs font-semibold text-slate-500"
                                            >
                                                +Rp
                                            </span>
                                            <input
                                                id={`modifier-item-price-${idx}`}
                                                type="number"
                                                min="0"
                                                step="500"
                                                bind:value={
                                                    item.additional_price
                                                }
                                                placeholder="0"
                                                class="min-w-0 flex-1 border-0 bg-white px-2.5 text-right text-sm font-medium text-slate-800 outline-none [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                            />
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        onclick={() => removeItemRow(idx)}
                                        class="col-start-2 row-span-2 row-start-1 flex h-11 w-11 cursor-pointer items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-rose-50 hover:text-rose-600 sm:col-start-3 sm:row-span-1"
                                        title="Hapus Opsi"
                                        aria-label={`Hapus opsi ${idx + 1}`}
                                    >
                                        <i class="ti ti-trash text-base"></i>
                                    </button>
                                </div>
                            {/each}
                        </div>
                    </div>
                </div>

                <div
                    class="flex shrink-0 items-center justify-end gap-2.5 border-t border-slate-100 bg-slate-50/60 px-4 py-3.5 sm:px-5"
                >
                    <button
                        type="button"
                        onclick={() => (showModal = false)}
                        class="h-11 px-4 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-100 transition-colors cursor-pointer sm:h-9"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        disabled={isSubmitting}
                        onclick={handleSaveModifier}
                        class="h-11 px-4 rounded-lg text-sm font-semibold text-white shadow-xs transition-opacity hover:opacity-90 flex items-center gap-1.5 disabled:opacity-50 cursor-pointer sm:h-9"
                        style="background-color: {primary};"
                    >
                        {#if isSubmitting}
                            <i class="ti ti-loader-2 animate-spin"></i>
                            <span>Menyimpan...</span>
                        {:else}
                            <i class="ti ti-check"></i>
                            <span>Simpan Kustomisasi</span>
                        {/if}
                    </button>
                </div>
            </div>
        </div>
    {/if}
</AdminLayout>
