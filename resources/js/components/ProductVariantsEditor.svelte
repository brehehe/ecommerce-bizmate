<script>
    import Toggle from '@/components/ui/Toggle.svelte';
    import Input from '@/components/ui/Input.svelte';
    import InputCurrency from '@/components/ui/InputCurrency.svelte';

    let {
        enableProductVariants = false,
        enableVariants = $bindable(false),
        variations = $bindable([]),
        variants = $bindable([]),
        globalCustomPrice = $bindable(false),
        globalCustomStock = $bindable(false),
        globalCustomWeight = $bindable(false),
        onAddVariation,
        onRemoveVariation,
        onAddOption,
        onRemoveOption,
    } = $props();
</script>

{#if enableProductVariants}
    <div class="bg-white rounded-xl border border-slate-200 p-5 sm:p-6 shadow-xs">
        <h3 class="text-base font-semibold text-slate-900 border-b border-slate-150 pb-3 mb-4 flex items-center gap-2">
            <i class="ti ti-adjustments-horizontal text-brand-blueRoyal text-lg"></i>
            Varian Produk
        </h3>

        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-1">
            <Toggle
                bind:checked={enableVariants}
                label="Produk memiliki varian"
                description="Gunakan untuk pilihan seperti warna, ukuran, atau material."
                icon="ti-category-2"
            />
        </div>

        {#if enableVariants}
            <div class="mt-5 space-y-4">
                {#if variations.length === 0}
                    <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-5 text-center">
                        <p class="text-sm font-semibold text-slate-700">Belum ada jenis varian</p>
                        <p class="mt-1 text-xs text-slate-500">Contoh: Warna dengan opsi Hitam dan Putih.</p>
                        <button
                            type="button"
                            onclick={onAddVariation}
                            class="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-brand-blueRoyal px-3 py-2 text-xs font-semibold text-white transition-colors hover:bg-brand-blueRoyal/90 cursor-pointer"
                        >
                            <i class="ti ti-plus text-sm"></i>
                            Tambah Varian
                        </button>
                    </div>
                {/if}

                {#each variations as variation, variationIndex (variation.id)}
                    <div class="rounded-xl border border-slate-200 p-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                            <div class="w-full sm:max-w-sm">
                                <label for={`variation-name-${variationIndex}`} class="mb-1.5 block text-xs font-semibold text-slate-700">
                                    Jenis varian {variationIndex + 1}
                                </label>
                                <input
                                    id={`variation-name-${variationIndex}`}
                                    type="text"
                                    bind:value={variation.name}
                                    placeholder={variationIndex === 0 ? 'Contoh: Warna' : 'Contoh: Ukuran'}
                                    required={true}
                                    class="h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-800 placeholder:text-slate-400 focus:border-brand-blueRoyal focus:outline-none focus:ring-2 focus:ring-brand-blueRoyal/15"
                                />
                            </div>
                            <button
                                type="button"
                                onclick={() => onRemoveVariation(variationIndex)}
                                class="inline-flex h-10 items-center justify-center gap-1.5 rounded-lg border border-rose-200 px-3 text-xs font-semibold text-rose-600 transition-colors hover:bg-rose-50 cursor-pointer"
                            >
                                <i class="ti ti-trash text-sm"></i>
                                Hapus
                            </button>
                        </div>

                        <div class="mt-4">
                            <p class="text-xs font-semibold text-slate-700">Pilihan</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                {#each variation.options as option, optionIndex (option.id)}
                                    <span class="inline-flex items-center gap-1 rounded-lg bg-brand-blueRoyal/8 px-2.5 py-1.5 text-xs font-medium text-brand-blueRoyal">
                                        {option.name}
                                        <button
                                            type="button"
                                            aria-label={`Hapus pilihan ${option.name}`}
                                            onclick={() => onRemoveOption(variationIndex, optionIndex)}
                                            class="inline-flex h-4 w-4 items-center justify-center rounded hover:bg-brand-blueRoyal/15 cursor-pointer"
                                        >
                                            <i class="ti ti-x text-sm"></i>
                                        </button>
                                    </span>
                                {/each}
                            </div>
                            <div class="mt-3 flex gap-2">
                                <input
                                    id={`new-opt-name-${variationIndex}`}
                                    type="text"
                                    placeholder={variationIndex === 0 ? 'Contoh: Hitam' : 'Contoh: XL'}
                                    class="h-9 min-w-0 flex-1 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-800 placeholder:text-slate-400 focus:border-brand-blueRoyal focus:outline-none focus:ring-2 focus:ring-brand-blueRoyal/15"
                                    onkeydown={(event) => {
                                        if (event.key === 'Enter') {
                                            event.preventDefault();
                                            onAddOption(variationIndex);
                                        }
                                    }}
                                />
                                <button
                                    type="button"
                                    onclick={() => onAddOption(variationIndex)}
                                    class="inline-flex h-9 items-center justify-center gap-1.5 rounded-lg border border-brand-blueRoyal/20 bg-brand-blueRoyal/5 px-3 text-xs font-semibold text-brand-blueRoyal transition-colors hover:bg-brand-blueRoyal/10 cursor-pointer"
                                >
                                    <i class="ti ti-plus text-sm"></i>
                                    Tambah
                                </button>
                            </div>
                        </div>
                    </div>
                {/each}

                {#if variations.length === 1}
                    <button
                        type="button"
                        onclick={onAddVariation}
                        class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-blueRoyal hover:text-brand-blueRoyal/80 cursor-pointer"
                    >
                        <i class="ti ti-plus text-sm"></i>
                        Tambah jenis varian kedua
                    </button>
                {/if}

                {#if variants.length > 0}
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                        <p class="text-xs font-semibold text-emerald-900">{variants.length} kombinasi varian dibuat</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            {#each variants as variant (variant.id)}
                                <span class="rounded-md bg-white px-2 py-1 text-xs text-emerald-800 shadow-2xs">{variant.name}</span>
                            {/each}
                        </div>
                        <p class="mt-2 text-[11px] text-emerald-700">Harga dan stok mengikuti data master produk.</p>
                    </div>

                    <div class="rounded-xl border border-slate-200 p-4">
                        <div>
                            <p class="text-sm font-semibold text-slate-800">Pengaturan per varian</p>
                            <p class="mt-1 text-xs text-slate-500">Aktifkan bagian yang ingin dibedakan dari data master produk.</p>
                        </div>

                        <div class="mt-4 grid gap-2 lg:grid-cols-3">
                            <Toggle
                                bind:checked={globalCustomPrice}
                                label="Harga per varian"
                                description="Harga jual dan beli"
                                icon="ti-currency-rupiah"
                            />
                            <Toggle
                                bind:checked={globalCustomStock}
                                label="Stok per varian"
                                description="Stok dan batas pembelian"
                                icon="ti-package"
                            />
                            <Toggle
                                bind:checked={globalCustomWeight}
                                label="Dimensi per varian"
                                description="Berat dan ukuran paket"
                                icon="ti-ruler-measure"
                            />
                        </div>

                        {#if globalCustomPrice || globalCustomStock || globalCustomWeight}
                            <div class="mt-4 space-y-3">
                                {#each variants as variant, variantIndex (variant.id)}
                                    <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                                        <p class="text-sm font-semibold text-slate-800">{variant.name}</p>
                                        <div class="mt-3 space-y-4">
                                            {#if globalCustomPrice}
                                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                                    <InputCurrency
                                                        bind:value={variant.price}
                                                        id={`variant-price-${variantIndex}`}
                                                        label="Harga Jual"
                                                        prefix="Rp"
                                                    />
                                                    <InputCurrency
                                                        bind:value={variant.cost}
                                                        id={`variant-cost-${variantIndex}`}
                                                        label="Harga Beli / Modal"
                                                        prefix="Rp"
                                                    />
                                                </div>
                                            {/if}

                                            {#if globalCustomStock}
                                                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                                    <Input
                                                        bind:value={variant.stock}
                                                        id={`variant-stock-${variantIndex}`}
                                                        label="Quantity / Stok"
                                                        type="number"
                                                        min="0"
                                                    />
                                                    <Input
                                                        bind:value={variant.min_stock}
                                                        id={`variant-min-stock-${variantIndex}`}
                                                        label="Batas Minimum"
                                                        type="number"
                                                        min="0"
                                                    />
                                                    <Input
                                                        bind:value={variant.min_purchase}
                                                        id={`variant-min-purchase-${variantIndex}`}
                                                        label="Min. Pembelian"
                                                        type="number"
                                                        min="1"
                                                    />
                                                </div>
                                                <div class="max-w-md rounded-xl border border-slate-200 bg-white p-1">
                                                    <Toggle
                                                        bind:checked={variant.is_unlimited}
                                                        label="Stok tidak terbatas"
                                                        description="Aktifkan jika varian ini selalu tersedia."
                                                        icon="ti-infinity"
                                                    />
                                                </div>
                                            {/if}

                                            {#if globalCustomWeight}
                                                <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                                                    <Input
                                                        bind:value={variant.weight}
                                                        id={`variant-weight-${variantIndex}`}
                                                        label="Berat (gram)"
                                                        type="number"
                                                        min="0"
                                                    />
                                                    <Input
                                                        bind:value={variant.length}
                                                        id={`variant-length-${variantIndex}`}
                                                        label="Panjang (cm)"
                                                        type="number"
                                                        min="0"
                                                    />
                                                    <Input
                                                        bind:value={variant.width}
                                                        id={`variant-width-${variantIndex}`}
                                                        label="Lebar (cm)"
                                                        type="number"
                                                        min="0"
                                                    />
                                                    <Input
                                                        bind:value={variant.height}
                                                        id={`variant-height-${variantIndex}`}
                                                        label="Tinggi (cm)"
                                                        type="number"
                                                        min="0"
                                                    />
                                                </div>
                                            {/if}
                                        </div>
                                    </div>
                                {/each}
                            </div>
                        {/if}
                    </div>
                {/if}
            </div>
        {/if}
    </div>
{/if}
