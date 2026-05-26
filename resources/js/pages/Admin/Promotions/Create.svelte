<script lang="ts">
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { useForm, Link } from '@inertiajs/svelte';
    import { showToast } from '@/utils/toast';
    import Input from '@/components/ui/Input.svelte';
    import InputCurrency from '@/components/ui/InputCurrency.svelte';
    import Select from '@/components/ui/Select.svelte';
    import SelectSearch from '@/components/ui/SelectSearch.svelte';
    import Toggle from '@/components/ui/Toggle.svelte';
    import Textarea from '@/components/ui/Textarea.svelte';
    import { store as adminPromotionsStore } from '@/routes/admin/promotions';

    let { products = [] } = $props();

    // Targeting scope state
    let targetScope = $state('all');

    // Modal state
    let showModal = $state(false);
    let modalSearch = $state('');
    let tempSelectedIds = $state<string[]>([]);

    // Bulk actions state
    let bulkDiscountType = $state('percentage');
    let bulkDiscountValue = $state('');
    let bulkPromoStock = $state('');

    // Flash Sale Schedule state
    let flashSaleDuration = $state('3'); // default 3 hours
    let customDurationHours = $state('');

    const flashSaleDurationOptions = [
        { id: '1', name: '1 Jam' },
        { id: '2', name: '2 Jam' },
        { id: '3', name: '3 Jam (Rekomendasi)' },
        { id: '4', name: '4 Jam' },
        { id: '6', name: '6 Jam' },
        { id: '12', name: '12 Jam' },
        { id: '24', name: '24 Jam' },
        { id: 'custom', name: 'Durasi Kustom...' },
    ];

    function formatFriendlyDatetime(dateStr: string) {
        if (!dateStr) return '-';
        const d = new Date(dateStr);
        if (isNaN(d.getTime())) return '-';
        const pad = (n: number) => n.toString().padStart(2, '0');
        return `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()}, ${pad(d.getHours())}.${pad(d.getMinutes())} WIB`;
    }

    // Helper actions for custom multi-item bundling
    function addBuyItem() {
        form.settings.bundle.buy_items = [
            ...form.settings.bundle.buy_items,
            { product_id: '', product_variant_id: '', qty: 1 }
        ];
    }

    function removeBuyItem(index: number) {
        form.settings.bundle.buy_items = form.settings.bundle.buy_items.filter((_, i) => i !== index);
    }

    function addGetItem() {
        form.settings.bundle.get_items = [
            ...form.settings.bundle.get_items,
            { product_id: '', product_variant_id: '', qty: 1, discount_type: 'free', discount_value: 0 }
        ];
    }

    function removeGetItem(index: number) {
        form.settings.bundle.get_items = form.settings.bundle.get_items.filter((_, i) => i !== index);
    }

    const form = useForm({
        name: '',
        type: 'promo_toko',
        code: '',
        discount_type: 'percentage',
        discount_value: '',
        min_purchase: '',
        max_discount: '',
        quota: '',
        start_time: '',
        end_time: '',
        is_active: true,
        settings: {
            keep_tier_prices: false,
            bundle: {
                buy_items: [
                    { product_id: '', product_variant_id: '', qty: 1 }
                ],
                get_items: [
                    { product_id: '', product_variant_id: '', qty: 1, discount_type: 'free', discount_value: 0 }
                ]
            },
        },
        items: [] as Array<{
            product_id: number;
            product_variant_id: number | null;
            product_name: string;
            variant_name: string;
            base_price: number;
            discount_type: string;
            discount_value: number | string;
            promo_price: number | string;
            promo_stock: number | string;
        }>,
    });

    // Flatten products and variants for single-level selection
    let selectableItems = $derived.by(() => {
        let items: Array<{
            id: string;
            product_id: number;
            product_variant_id: number | null;
            name: string;
            sku: string;
            image: string;
            category_name: string;
            price: number;
            stock: number;
        }> = [];

        products.forEach((p) => {
            if (!p.variants || p.variants.length === 0) {
                items.push({
                    id: `${p.id}-null`,
                    product_id: p.id,
                    product_variant_id: null,
                    name: p.name,
                    sku: p.sku || '',
                    image: p.image || '',
                    category_name: p.category_name || 'Tanpa Kategori',
                    price: p.price,
                    stock: p.stock,
                });
            } else {
                p.variants.forEach((v) => {
                    items.push({
                        id: `${p.id}-${v.id}`,
                        product_id: p.id,
                        product_variant_id: v.id,
                        name: `${p.name} - ${v.name}`,
                        sku: v.sku || p.sku || '',
                        image: v.image || p.image || '',
                        category_name: p.category_name || 'Tanpa Kategori',
                        price: v.price,
                        stock: v.stock,
                    });
                });
            }
        });

        return items;
    });

    let filteredSelectableItems = $derived.by(() => {
        const query = modalSearch.toLowerCase().trim();
        if (!query) return selectableItems;
        return selectableItems.filter(
            (item) =>
                item.name.toLowerCase().includes(query) ||
                item.sku.toLowerCase().includes(query),
        );
    });

    function toggleSelection(itemId: string) {
        if (tempSelectedIds.includes(itemId)) {
            tempSelectedIds = tempSelectedIds.filter((id) => id !== itemId);
        } else {
            tempSelectedIds = [...tempSelectedIds, itemId];
        }
    }

    let isAllVisibleSelected = $derived(
        filteredSelectableItems.length > 0 &&
            filteredSelectableItems.every((item) =>
                tempSelectedIds.includes(item.id),
            ),
    );

    function toggleSelectAllVisible() {
        const visibleIds = filteredSelectableItems.map((item) => item.id);
        const allSelected = visibleIds.every((id) =>
            tempSelectedIds.includes(id),
        );

        if (allSelected) {
            tempSelectedIds = tempSelectedIds.filter(
                (id) => !visibleIds.includes(id),
            );
        } else {
            const newIds = visibleIds.filter(
                (id) => !tempSelectedIds.includes(id),
            );
            tempSelectedIds = [...tempSelectedIds, ...newIds];
        }
    }

    function openSelectionModal() {
        modalSearch = '';
        tempSelectedIds = form.items.map(
            (item) => `${item.product_id}-${item.product_variant_id}`,
        );
        showModal = true;
    }

    function saveModalSelections() {
        const newItems: typeof form.items = [];

        tempSelectedIds.forEach((id) => {
            const found = selectableItems.find((item) => item.id === id);
            if (!found) return;

            const existing = form.items.find(
                (item) =>
                    item.product_id == found.product_id &&
                    item.product_variant_id == found.product_variant_id,
            );

            if (existing) {
                newItems.push(existing);
            } else {
                newItems.push({
                    product_id: found.product_id,
                    product_variant_id: found.product_variant_id,
                    product_name: found.name,
                    variant_name: found.product_variant_id
                        ? 'Varian'
                        : 'Tanpa Varian',
                    base_price: found.price,
                    discount_type: 'percentage',
                    discount_value: '',
                    promo_price: '',
                    promo_stock: '',
                });
            }
        });

        form.items = newItems;
        showModal = false;
    }

    function applyBulkSettings() {
        if (!form.items.length) return;

        const val = bulkDiscountValue !== '' ? Number(bulkDiscountValue) : '';
        const stock = bulkPromoStock !== '' ? Number(bulkPromoStock) : '';

        form.items = form.items.map((item) => {
            let discVal: number | string = '';
            let promoVal: number | string = '';

            if (bulkDiscountType === 'percentage') {
                discVal = val;
            } else if (bulkDiscountType === 'fixed') {
                discVal = val;
            } else if (bulkDiscountType === 'custom') {
                promoVal = val;
            }

            return {
                ...item,
                discount_type: bulkDiscountType,
                discount_value: discVal,
                promo_price: promoVal,
                promo_stock: stock,
            };
        });

        showToast('Pengaturan massal berhasil diterapkan.', 'success');
    }




    $effect(() => {
        // Reset code if not voucher type
        if (
            form.type !== 'voucher_belanja' &&
            form.type !== 'voucher_gratis_ongkir'
        ) {
            form.code = '';
        }
        // Force discount type to percentage for gratis ongkir 100% or allow overrides
        if (form.type === 'voucher_gratis_ongkir') {
            form.discount_type = 'percentage';
        }
    });

    $effect(() => {
        if (form.type === 'flash_sale') {
            if (form.start_time) {
                const start = new Date(form.start_time);
                let hours = 3;
                if (flashSaleDuration === 'custom') {
                    hours = Number(customDurationHours) || 0;
                } else {
                    hours = Number(flashSaleDuration) || 0;
                }
                const end = new Date(start.getTime() + hours * 60 * 60 * 1000);
                if (!isNaN(end.getTime())) {
                    const pad = (n: number) => n.toString().padStart(2, '0');
                    form.end_time = `${end.getFullYear()}-${pad(end.getMonth() + 1)}-${pad(end.getDate())}T${pad(end.getHours())}:${pad(end.getMinutes())}`;
                }
            }
        }
    });



    function addProductToTargets() {
        if (!selectedProductId) return;
        const prod = products.find((p) => p.id == selectedProductId);
        if (!prod) return;

        if (selectedVariantId === 'all' || !prod.variants.length) {
            const alreadyExists = form.items.some(
                (item) =>
                    item.product_id == prod.id &&
                    item.product_variant_id === null,
            );
            if (!alreadyExists) {
                form.items = [
                    ...form.items,
                    {
                        product_id: prod.id,
                        product_variant_id: null,
                        product_name: prod.name,
                        variant_name: prod.variants.length
                            ? 'Semua Varian'
                            : 'Tanpa Varian',
                        base_price: prod.price,
                        discount_type: form.discount_type || 'percentage',
                        discount_value: form.discount_value || '',
                        promo_price: '',
                    },
                ];
            } else {
                showToast('Produk sudah ada di daftar target.', 'warning');
            }
        } else {
            const variant = prod.variants.find(
                (v) => v.id == selectedVariantId,
            );
            if (!variant) return;

            const alreadyExists = form.items.some(
                (item) =>
                    item.product_id == prod.id &&
                    item.product_variant_id == variant.id,
            );
            if (!alreadyExists) {
                form.items = [
                    ...form.items,
                    {
                        product_id: prod.id,
                        product_variant_id: variant.id,
                        product_name: prod.name,
                        variant_name: variant.name,
                        base_price: variant.price,
                        discount_type: form.discount_type || 'percentage',
                        discount_value: form.discount_value || '',
                        promo_price: '',
                    },
                ];
            } else {
                showToast(
                    'Varian produk sudah ada di daftar target.',
                    'warning',
                );
            }
        }

        selectedProductId = '';
        selectedVariantId = '';
    }

    function removeTargetItem(index: number) {
        form.items = form.items.filter((_, i) => i !== index);
    }

    function calculateFinalPrice(item: any) {
        if (item.discount_type === 'custom') {
            return Number(item.promo_price) || 0;
        }
        if (item.discount_type === 'percentage') {
            const disc = Number(item.discount_value) || 0;
            return Math.max(
                0,
                item.base_price - (item.base_price * disc) / 100,
            );
        }
        if (item.discount_type === 'fixed') {
            const disc = Number(item.discount_value) || 0;
            return Math.max(0, item.base_price - disc);
        }
        return item.base_price;
    }

    // Dynamic bundling simulation calculator
    let bundleSummary = $derived.by(() => {
        let totalBuyNormal = 0;
        let totalGetNormal = 0;
        let totalDiscount = 0;

        form.settings.bundle.buy_items.forEach(item => {
            if (!item.product_id) return;
            const prod = products.find(p => p.id == item.product_id);
            if (!prod) return;
            
            let price = prod.price;
            if (item.product_variant_id && prod.variants) {
                const vari = prod.variants.find(v => v.id == item.product_variant_id);
                if (vari) price = vari.price;
            }
            
            const qty = Number(item.qty) || 0;
            totalBuyNormal += price * qty;
        });

        form.settings.bundle.get_items.forEach(item => {
            if (!item.product_id) return;
            const prod = products.find(p => p.id == item.product_id);
            if (!prod) return;
            
            let price = prod.price;
            if (item.product_variant_id && prod.variants) {
                const vari = prod.variants.find(v => v.id == item.product_variant_id);
                if (vari) price = vari.price;
            }
            
            const qty = Number(item.qty) || 0;
            const normalVal = price * qty;
            totalGetNormal += normalVal;

            if (item.discount_type === 'free') {
                totalDiscount += normalVal;
            } else if (item.discount_type === 'percentage') {
                const pct = Number(item.discount_value) || 0;
                totalDiscount += (normalVal * pct) / 100;
            } else if (item.discount_type === 'fixed') {
                const fix = Number(item.discount_value) || 0;
                totalDiscount += Math.min(normalVal, fix);
            }
        });

        const totalNormal = totalBuyNormal + totalGetNormal;
        const totalToPay = Math.max(0, totalNormal - totalDiscount);

        return {
            totalBuyNormal,
            totalGetNormal,
            totalNormal,
            totalDiscount,
            totalToPay
        };
    });

    function submit() {
        if (targetScope === 'all') {
            form.items = [];
        }



        form.post(adminPromotionsStore.url(), {
            onSuccess: () => {
                showToast('Promosi berhasil ditambahkan!', 'success');
            },
        });
    }

    const typeOptions = [
        { id: 'promo_toko', name: 'Promo Toko (Diskon Produk)' },
        { id: 'voucher_belanja', name: 'Voucher Belanja (Diskon Belanja)' },
        { id: 'voucher_gratis_ongkir', name: 'Voucher Gratis Ongkir' },
        { id: 'flash_sale', name: 'Flash Sale' },
        { id: 'bundling_gift', name: 'Promo Bundling & Gift' },
    ];

    const discountTypeOptions = [
        { id: 'percentage', name: 'Persentase (%)' },
        { id: 'fixed', name: 'Nominal Tetap (Rp)' },
    ];

    const itemDiscountTypeOptions = [
        { id: 'percentage', name: 'Persentase (%)' },
        { id: 'fixed', name: 'Nominal (Rp)' },
        { id: 'custom', name: 'Harga Promo Kustom (Rp)' },
    ];

    const bundleGiftDiscountOptions = [
        { id: 'free', name: 'Gratis 100%' },
        { id: 'percentage', name: 'Persentase (%)' },
        { id: 'fixed', name: 'Nominal Tetap (Rp)' },
    ];
</script>

<svelte:head>
    <title>Buat Promosi Baru</title>
</svelte:head>

<AdminLayout>
    <div class="flex-grow p-4 sm:p-8 w-full max-w-full mx-auto">
        <div class="flex items-center gap-4 mb-6">
            <Link
                href="/admin/promotions"
                class="w-10 h-10 rounded-xl bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-600 transition"
            >
                <i class="ti ti-chevron-left text-lg"></i>
            </Link>
            <div>
                <h3 class="font-outfit font-black text-2xl text-slate-800">
                    Buat Promosi Baru
                </h3>
                <p
                    class="text-xs text-slate-400 font-bold uppercase tracking-wider mt-1"
                >
                    Isi detail parameter promosi di bawah ini
                </p>
            </div>
        </div>

        <form
            onsubmit={(e) => {
                e.preventDefault();
                submit();
            }}
            class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start"
        >
            <!-- Core Configurations -->
            <div class="lg:col-span-2 space-y-6">
                <div
                    class="bg-white rounded-3xl border border-slate-200 shadow-card p-6 sm:p-8 space-y-6"
                >
                    <h3
                        class="font-outfit font-black text-lg text-slate-800 pb-3 border-b border-slate-100"
                    >
                        Informasi Utama Promosi
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <Input
                                id="promo-name"
                                bind:value={form.name}
                                label="Nama Promosi / Kampanye"
                                placeholder="Contoh: Diskon Gajian Mei, Flash Sale Lebaran"
                                required={true}
                                error={form.errors.name}
                            />
                        </div>

                        <div>
                            <Select
                                label="Tipe Promosi"
                                options={typeOptions}
                                bind:value={form.type}
                                required={true}
                                error={form.errors.type}
                            />
                        </div>

                        {#if form.type === 'voucher_belanja' || form.type === 'voucher_gratis_ongkir'}
                            <div>
                                <Input
                                    id="promo-code"
                                    bind:value={form.code}
                                    label="Kode Voucher"
                                    placeholder="Contoh: GAJIANMAI10"
                                    required={true}
                                    error={form.errors.code}
                                />
                            </div>
                        {/if}
                    </div>

                    <!-- Discount Values Config -->
                    {#if form.type !== 'bundling_gift' && form.type !== 'voucher_gratis_ongkir'}
                        <div
                            class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-3 border-t border-slate-100"
                        >
                            <div>
                                <Select
                                    label="Jenis Diskon"
                                    options={discountTypeOptions}
                                    bind:value={form.discount_type}
                                    required={form.type !== 'flash_sale' && targetScope !== 'specific'}
                                />
                            </div>

                            {#if form.discount_type === 'percentage'}
                                <div>
                                    <Input
                                        id="discount-val"
                                        bind:value={form.discount_value}
                                        type="number"
                                        label="Diskon Persentase (%)"
                                        placeholder="Contoh: 10"
                                        min="0"
                                        max="100"
                                        required={form.type !== 'flash_sale' && targetScope !== 'specific'}
                                        error={form.errors.discount_value}
                                    />
                                </div>
                            {:else}
                                <div>
                                    <InputCurrency
                                        id="discount-val-curr"
                                        bind:value={form.discount_value}
                                        label="Nominal Diskon Rupiah (Rp)"
                                        placeholder="Contoh: 50.000"
                                        required={form.type !== 'flash_sale' && targetScope !== 'specific'}
                                        error={form.errors.discount_value}
                                    />
                                </div>
                            {/if}
                        </div>
                    {/if}

                    <!-- Free Shipping Voucher Custom Settings -->
                    {#if form.type === 'voucher_gratis_ongkir'}
                        <div
                            class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-3 border-t border-slate-100"
                        >
                            <div>
                                <label
                                    class="text-xs font-bold text-slate-600 block mb-2"
                                    >Nilai Potongan Ongkir</label
                                >
                                <div
                                    class="bg-slate-50 p-3 rounded-2xl border border-slate-200 text-xs font-semibold text-slate-500"
                                >
                                    Gratis Ongkir 100% (Kosongkan nominal di
                                    samping untuk potongan penuh)
                                </div>
                            </div>
                            <div>
                                <InputCurrency
                                    id="gratis-ongkir-cap"
                                    bind:value={form.discount_value}
                                    label="Batas Maksimal Potongan Ongkir (Rp)"
                                    placeholder="Contoh: 20.000 (Opsional)"
                                    error={form.errors.discount_value}
                                />
                            </div>
                        </div>
                    {/if}

                    <!-- Vouchers Terms (Min Purchase, Max Discount, Quota) -->
                    {#if form.type === 'voucher_belanja' || form.type === 'voucher_gratis_ongkir'}
                        <div
                            class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-3 border-t border-slate-100"
                        >
                            <div>
                                <InputCurrency
                                    id="min-purchase"
                                    bind:value={form.min_purchase}
                                    label="Minimal Pembelian (Rp)"
                                    placeholder="Contoh: 100.000"
                                    error={form.errors.min_purchase}
                                />
                            </div>

                            <div>
                                <InputCurrency
                                    id="max-discount"
                                    bind:value={form.max_discount}
                                    label="Maksimal Diskon (Rp)"
                                    placeholder="Contoh: 50.000 (Opsional)"
                                    readonly={form.discount_type !==
                                        'percentage' &&
                                        form.type !== 'voucher_gratis_ongkir'}
                                    error={form.errors.max_discount}
                                />
                            </div>

                            <div>
                                <Input
                                    id="promo-quota"
                                    bind:value={form.quota}
                                    type="number"
                                    label="Kuota Penggunaan"
                                    placeholder="Contoh: 100 (Opsional)"
                                    min="0"
                                    error={form.errors.quota}
                                />
                            </div>
                        </div>
                    {/if}
                </div>



                <!-- Bundling & Gift Settings Section -->
                {#if form.type === 'bundling_gift'}
                    <div
                        class="bg-white rounded-3xl border border-slate-200 shadow-card p-6 sm:p-8 space-y-6"
                    >
                        <div class="pb-3 border-b border-slate-100 flex items-center justify-between">
                            <div>
                                <h3
                                    class="font-outfit font-black text-lg text-slate-800"
                                >
                                    Pengaturan Promo Bundling & Gift
                                </h3>
                                <p class="text-xs text-slate-500 font-medium mt-1">
                                    Atur kondisi: Pembelian produk-produk tertentu akan mendapatkan bonus/diskon produk hadiah.
                                </p>
                            </div>
                        </div>

                        <!-- Buy Condition -->
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <h4
                                    class="text-xs font-bold text-slate-400 uppercase tracking-widest"
                                >
                                    1. Syarat Pembelian (Buy)
                                </h4>
                                <button
                                    type="button"
                                    onclick={addBuyItem}
                                    class="flex items-center gap-1.5 px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-brand-blueRoyal text-[9px] font-black rounded-lg uppercase tracking-wider transition duration-200 font-outfit"
                                >
                                    <i class="ti ti-plus text-[10px]"></i> Tambah Syarat Produk
                                </button>
                            </div>

                            <div class="space-y-3">
                                {#each form.settings.bundle.buy_items as buyItem, i}
                                    <div
                                        class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end bg-slate-50 p-4 rounded-2xl border border-slate-200/60 relative"
                                    >
                                        <div class="md:col-span-5">
                                            <SelectSearch
                                                label="Produk Utama Yang Harus Dibeli"
                                                options={products.map((p) => ({
                                                    id: p.id,
                                                    name: p.name,
                                                }))}
                                                bind:value={buyItem.product_id}
                                                placeholder="Pilih produk..."
                                                onchange={() => {
                                                    buyItem.product_variant_id = '';
                                                }}
                                            />
                                        </div>
                                        <div class="md:col-span-3">
                                            <Input
                                                id={`buy-qty-${i}`}
                                                bind:value={buyItem.qty}
                                                type="number"
                                                label="Jumlah Min. Pembelian"
                                                placeholder="1"
                                                min="1"
                                                required={true}
                                            />
                                        </div>
                                        <div class="md:col-span-4 flex items-end gap-2">
                                            {#if buyItem.product_id}
                                                {@const selectedProd = products.find(p => p.id == buyItem.product_id)}
                                                {#if selectedProd && selectedProd.variants && selectedProd.variants.length > 0}
                                                    <div class="flex-grow">
                                                        <Select
                                                            label="Spesifik Varian (Opsional)"
                                                            options={[
                                                                {
                                                                    id: '',
                                                                    name: 'Semua Varian',
                                                                },
                                                                ...selectedProd.variants.map((v) => ({
                                                                    id: v.id,
                                                                    name: v.name,
                                                                })),
                                                            ]}
                                                            bind:value={buyItem.product_variant_id}
                                                            placeholder="Pilih varian..."
                                                        />
                                                    </div>
                                                {/if}
                                            {/if}
                                            {#if form.settings.bundle.buy_items.length > 1}
                                                <button
                                                    type="button"
                                                    onclick={() => removeBuyItem(i)}
                                                    class="p-2.5 bg-red-50 hover:bg-red-100 text-red-650 rounded-xl transition duration-200 mb-1"
                                                >
                                                    <i class="ti ti-trash text-base"></i>
                                                </button>
                                            {/if}
                                        </div>
                                    </div>
                                {/each}
                            </div>
                        </div>

                        <!-- Get Condition -->
                        <div class="space-y-4 pt-4 border-t border-slate-150">
                            <div class="flex items-center justify-between">
                                <h4
                                    class="text-xs font-bold text-slate-400 uppercase tracking-widest"
                                >
                                    2. Hadiah / Produk Bonus (Get)
                                </h4>
                                <button
                                    type="button"
                                    onclick={addGetItem}
                                    class="flex items-center gap-1.5 px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-brand-blueRoyal text-[9px] font-black rounded-lg uppercase tracking-wider transition duration-200 font-outfit"
                                >
                                    <i class="ti ti-plus text-[10px]"></i> Tambah Hadiah / Bonus
                                </button>
                            </div>

                            <div class="space-y-4">
                                {#each form.settings.bundle.get_items as getItem, i}
                                    <div
                                        class="space-y-4 bg-slate-50 p-4 rounded-2xl border border-slate-200/60 relative"
                                    >
                                        <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                                            <div class="md:col-span-5">
                                                <SelectSearch
                                                    label="Produk Hadiah / Bonus"
                                                    options={products.map((p) => ({
                                                        id: p.id,
                                                        name: p.name,
                                                    }))}
                                                    bind:value={getItem.product_id}
                                                    placeholder="Pilih produk..."
                                                    onchange={() => {
                                                        getItem.product_variant_id = '';
                                                    }}
                                                />
                                            </div>
                                            <div class="md:col-span-3">
                                                <Input
                                                    id={`get-qty-${i}`}
                                                    bind:value={getItem.qty}
                                                    type="number"
                                                    label="Jumlah Hadiah"
                                                    placeholder="1"
                                                    min="1"
                                                    required={true}
                                                />
                                            </div>
                                            <div class="md:col-span-4 flex items-end gap-2">
                                                {#if getItem.product_id}
                                                    {@const selectedProd = products.find(p => p.id == getItem.product_id)}
                                                    {#if selectedProd && selectedProd.variants && selectedProd.variants.length > 0}
                                                        <div class="flex-grow">
                                                            <Select
                                                                label="Spesifik Varian (Opsional)"
                                                                options={[
                                                                    {
                                                                        id: '',
                                                                        name: 'Semua Varian',
                                                                    },
                                                                    ...selectedProd.variants.map((v) => ({
                                                                        id: v.id,
                                                                        name: v.name,
                                                                    })),
                                                                ]}
                                                                bind:value={getItem.product_variant_id}
                                                                placeholder="Pilih varian..."
                                                            />
                                                        </div>
                                                    {/if}
                                                {/if}
                                                {#if form.settings.bundle.get_items.length > 1}
                                                    <button
                                                        type="button"
                                                        onclick={() => removeGetItem(i)}
                                                        class="p-2.5 bg-red-50 hover:bg-red-100 text-red-650 rounded-xl transition duration-200 mb-1"
                                                    >
                                                        <i class="ti ti-trash text-base"></i>
                                                    </button>
                                                {/if}
                                            </div>
                                        </div>

                                        <!-- Gift Pricing Options -->
                                        <div
                                            class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-3 border-t border-slate-150"
                                        >
                                            <div>
                                                <Select
                                                    label="Jenis Diskon Hadiah"
                                                    options={bundleGiftDiscountOptions}
                                                    bind:value={getItem.discount_type}
                                                    required={true}
                                                />
                                            </div>

                                            {#if getItem.discount_type === 'percentage'}
                                                <div>
                                                    <Input
                                                        id={`get-discount-percentage-${i}`}
                                                        bind:value={getItem.discount_value}
                                                        type="number"
                                                        label="Diskon Persentase Hadiah (%)"
                                                        placeholder="Contoh: 50"
                                                        min="0"
                                                        max="100"
                                                        required={true}
                                                    />
                                                </div>
                                            {:else}
                                                <div>
                                                    <InputCurrency
                                                        id={`get-discount-fixed-${i}`}
                                                        bind:value={getItem.discount_value}
                                                        label="Nominal Diskon Hadiah (Rp)"
                                                        placeholder="Contoh: 10.000"
                                                        readonly={getItem.discount_type === 'free'}
                                                        required={getItem.discount_type !== 'free'}
                                                    />
                                                </div>
                                            {/if}
                                        </div>
                                    </div>
                                {/each}
                            </div>
                        </div>

                        <!-- Summary Section -->
                        {#if form.settings.bundle.buy_items.some(item => item.product_id) || form.settings.bundle.get_items.some(item => item.product_id)}
                            <div class="mt-6 pt-5 border-t border-slate-150 bg-slate-50/50 -mx-6 -mb-6 p-6 rounded-b-3xl">
                                <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-4 flex items-center gap-2">
                                    <i class="ti ti-calculator text-brand-blueRoyal text-base"></i> Ringkasan Simulasi Bundling
                                </h4>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <div class="bg-white p-4 rounded-2xl border border-slate-150 shadow-sm flex flex-col justify-between">
                                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Total Harga Normal (BUY + GET)</span>
                                        <span class="font-outfit font-bold text-base text-slate-700">
                                            Rp {bundleSummary.totalNormal.toLocaleString('id-ID')}
                                        </span>
                                    </div>
                                    <div class="bg-white p-4 rounded-2xl border border-slate-150 shadow-sm flex flex-col justify-between">
                                        <span class="text-[10px] font-bold text-orange-500 uppercase tracking-wider block mb-1">Potongan Bundling (Minus)</span>
                                        <span class="font-outfit font-bold text-base text-orange-600">
                                            - Rp {bundleSummary.totalDiscount.toLocaleString('id-ID')}
                                        </span>
                                    </div>
                                    <div class="bg-white p-4 rounded-2xl border border-brand-blueRoyal/20 bg-blue-50/20 shadow-sm flex flex-col justify-between">
                                        <span class="text-[10px] font-bold text-brand-blueRoyal uppercase tracking-wider block mb-1">Total Yang Dibayar Pembeli</span>
                                        <span class="font-outfit font-black text-lg text-brand-blueRoyal">
                                            Rp {bundleSummary.totalToPay.toLocaleString('id-ID')}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        {/if}
                    </div>
                {/if}
            </div>

            <!-- Sidebar Info (Duration & Toggle Status) -->
            <div class="w-full space-y-6">
                <div
                    class="bg-white rounded-3xl border border-slate-200 shadow-card p-6 space-y-6 sticky top-6"
                >
                    <h3
                        class="font-outfit font-black text-lg text-slate-800 pb-3 border-b border-slate-100"
                    >
                        Jadwal & Status
                    </h3>

                    {#if form.type === 'flash_sale'}
                        <div>
                            <Input
                                id="start-time"
                                bind:value={form.start_time}
                                type="datetime-local"
                                label="Waktu Mulai Flash Sale"
                                required={true}
                                error={form.errors.start_time}
                            />
                        </div>

                        <div class="space-y-4">
                            <Select
                                label="Durasi Flash Sale"
                                options={flashSaleDurationOptions}
                                bind:value={flashSaleDuration}
                                required={true}
                            />

                            {#if flashSaleDuration === 'custom'}
                                <Input
                                    id="custom-duration"
                                    bind:value={customDurationHours}
                                    type="number"
                                    label="Durasi (Jam)"
                                    placeholder="Masukkan durasi jam, misal: 5"
                                    min="1"
                                    required={true}
                                />
                            {/if}

                            <div class="bg-blue-50/50 border border-brand-blueRoyal/10 p-3.5 rounded-2xl text-[11px] text-slate-650 space-y-1">
                                <span class="font-bold text-brand-blueRoyal block uppercase tracking-wider text-[9px]">Sistem Otomatisasi Waktu</span>
                                <div class="flex justify-between items-center text-[10px]">
                                    <span class="font-medium text-slate-500">Akan Berakhir Pada:</span>
                                    <span class="font-outfit font-black text-brand-blueRoyal">
                                        {formatFriendlyDatetime(form.end_time)}
                                    </span>
                                </div>
                            </div>
                        </div>

                    {:else}
                        <div>
                            <Input
                                id="start-time"
                                bind:value={form.start_time}
                                type="datetime-local"
                                label="Waktu Mulai"
                                required={true}
                                error={form.errors.start_time}
                            />
                        </div>

                        <div>
                            <Input
                                id="end-time"
                                bind:value={form.end_time}
                                type="datetime-local"
                                label="Waktu Berakhir"
                                required={true}
                                error={form.errors.end_time}
                            />
                        </div>
                    {/if}

                    <div class="pt-2">
                        <Toggle
                            bind:checked={form.is_active}
                            label="Aktifkan Promosi"
                            description="Tentukan apakah promosi langsung berjalan pada jadwal yang diatur."
                            icon="ti-toggle-left"
                        />
                    </div>

                    <div class="pt-2 border-t border-slate-100 mt-2">
                        <Toggle
                            bind:checked={form.settings.keep_tier_prices}
                            label="Tetap Aktifkan Harga Grosir"
                            description="Jika diaktifkan, potongan harga grosir/lusinan akan tetap berlaku bersamaan dengan promo ini."
                            icon="ti-tags"
                        />
                    </div>

                    <div class="pt-4">
                        <button
                            type="submit"
                            disabled={form.processing}
                            class="w-full py-3 bg-brand-blueRoyal hover:bg-blue-800 text-white font-bold rounded-xl text-xs transition shadow-lg shadow-brand-blueRoyal/20 font-outfit uppercase tracking-wider disabled:opacity-50 flex justify-center items-center gap-2"
                        >
                            {#if form.processing}
                                <i class="ti ti-loader animate-spin text-lg"
                                ></i> Menyimpan...
                            {:else}
                                <i class="ti ti-device-floppy text-lg"></i> Simpan
                                Promosi
                            {/if}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Product Targets Section (Spanning full layout below side-by-side columns!) -->
            {#if form.type !== 'bundling_gift' && form.type !== 'voucher_gratis_ongkir'}
                <div class="lg:col-span-3 bg-white rounded-3xl border border-slate-200 shadow-card p-6 sm:p-8 space-y-6 w-full">
                    <!-- Section Header -->
                    <div class="flex items-center gap-3 pb-3 border-b border-slate-100">
                        <span class="w-6 h-6 rounded-full bg-blue-50 text-brand-blueRoyal font-black text-xs flex items-center justify-center">
                            4
                        </span>
                        <h3 class="font-outfit font-black text-xs text-brand-blueRoyal tracking-wider uppercase">
                            Target & Jangkauan Produk
                        </h3>
                    </div>

                    <!-- Card Radio Options -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Option 1: Semua Produk -->
                        <!-- svelte-ignore a11y_click_events_have_key_events -->
                        <!-- svelte-ignore a11y_no_static_element_interactions -->
                        <div
                            onclick={() => targetScope = 'all'}
                            class="flex items-start gap-4 p-5 rounded-2xl border-2 cursor-pointer transition duration-200 {targetScope === 'all' ? 'border-brand-blueRoyal bg-white shadow-sm' : 'border-slate-200 bg-white hover:border-slate-350'}"
                        >
                            <div class="mt-0.5">
                                <div class="w-4 h-4 rounded-full border flex items-center justify-center {targetScope === 'all' ? 'border-brand-blueRoyal' : 'border-slate-350'}">
                                    {#if targetScope === 'all'}
                                        <div class="w-2.5 h-2.5 rounded-full bg-brand-blueRoyal"></div>
                                    {/if}
                                </div>
                            </div>
                            <div>
                                <h4 class="font-outfit font-bold text-xs text-slate-800 uppercase tracking-wider">
                                    Semua Produk Toko
                                </h4>
                                <p class="text-[11px] text-slate-400 font-semibold mt-1 leading-relaxed">
                                    Kupon ini otomatis berlaku untuk seluruh daftar katalog furniture di bizmate.
                                </p>
                            </div>
                        </div>

                        <!-- Option 2: Produk Pilihan Spesifik -->
                        <!-- svelte-ignore a11y_click_events_have_key_events -->
                        <!-- svelte-ignore a11y_no_static_element_interactions -->
                        <div
                            onclick={() => targetScope = 'specific'}
                            class="flex items-start gap-4 p-5 rounded-2xl border-2 cursor-pointer transition duration-200 {targetScope === 'specific' ? 'border-brand-blueRoyal bg-white shadow-sm' : 'border-slate-200 bg-white hover:border-slate-350'}"
                        >
                            <div class="mt-0.5">
                                <div class="w-4 h-4 rounded-full border flex items-center justify-center {targetScope === 'specific' ? 'border-brand-blueRoyal' : 'border-slate-350'}">
                                    {#if targetScope === 'specific'}
                                        <div class="w-2.5 h-2.5 rounded-full bg-brand-blueRoyal"></div>
                                    {/if}
                                </div>
                            </div>
                            <div>
                                <h4 class="font-outfit font-bold text-xs text-slate-800 uppercase tracking-wider">
                                    Produk Pilihan Spesifik
                                </h4>
                                <p class="text-[11px] text-slate-400 font-semibold mt-1 leading-relaxed">
                                    Tentukan dan pilih secara manual produk furniture apa saja yang layak menggunakan promo ini.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Specific Products Settings -->
                    {#if targetScope === 'specific'}
                        <div class="space-y-4 pt-4 border-t border-slate-100">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <h4 class="font-outfit font-bold text-xs text-slate-800 uppercase tracking-wider">
                                        Daftar Target Produk Terpilih
                                    </h4>
                                    <p class="text-[11px] text-slate-400 font-semibold mt-1 leading-relaxed">
                                        Tentukan nilai potongan harga secara individu atau gunakan panel ubah massal di bawah.
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    onclick={openSelectionModal}
                                    class="flex items-center gap-1.5 px-4 py-2 bg-blue-50 hover:bg-blue-100 text-brand-blueRoyal text-[10px] font-black rounded-xl uppercase tracking-wider transition duration-200 font-outfit"
                                >
                                    <i class="ti ti-plus text-xs"></i> Pilih & Atur Produk
                                </button>
                            </div>

                            <!-- Empty State -->
                            {#if form.items.length === 0}
                                <div class="text-center py-12 border border-dashed border-slate-200 rounded-2xl bg-slate-50/50 flex flex-col items-center justify-center">
                                    <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mb-3">
                                        <i class="ti ti-package text-xl"></i>
                                    </div>
                                    <p class="text-xs text-slate-500 font-medium">
                                        Belum ada produk target yang dipilih.
                                    </p>
                                    <button
                                        type="button"
                                        onclick={openSelectionModal}
                                        class="px-5 py-2 bg-brand-blueRoyal hover:bg-blue-800 text-white text-[10px] font-black rounded-xl uppercase tracking-wider transition duration-200 mt-4 shadow-md font-outfit"
                                    >
                                        PILIH PRODUK SEKARANG
                                    </button>
                                </div>
                            {:else}
                                <!-- Bulk Action Card -->
                                <div class="bg-white border border-slate-200 rounded-2xl p-5 space-y-4 shadow-sm">
                                    <div class="flex items-center gap-2 pb-2.5 border-b border-slate-105">
                                        <i class="ti ti-settings text-orange-500 text-sm"></i>
                                        <h4 class="font-outfit font-black text-[10px] text-slate-800 tracking-wider uppercase">
                                            Ubah Massal Potongan Produk
                                        </h4>
                                    </div>
                                    <div class="flex flex-wrap gap-3 items-end">
                                        <div class="w-full sm:w-48">
                                            <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1.5">Tipe Potongan</label>
                                            <select
                                                bind:value={bulkDiscountType}
                                                class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-brand-blueRoyal/20 focus:border-brand-blueRoyal text-xs font-semibold text-slate-700 h-10"
                                            >
                                                <option value="percentage">Diskon Persen (%)</option>
                                                <option value="fixed">Potongan Tetap (Rp)</option>
                                                <option value="custom">Harga Promo Kustom (Rp)</option>
                                            </select>
                                        </div>
                                        <div class="w-full sm:w-44">
                                            <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1.5">Nilai Potongan</label>
                                            <input
                                                type="number"
                                                bind:value={bulkDiscountValue}
                                                placeholder="Nilai potongan..."
                                                class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-brand-blueRoyal/20 focus:border-brand-blueRoyal text-xs font-semibold text-slate-700 h-10"
                                            />
                                        </div>
                                        <div class="w-full sm:w-44">
                                            <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1.5">Stok Promo (Ops.)</label>
                                            <input
                                                type="number"
                                                bind:value={bulkPromoStock}
                                                placeholder="Stok Promo (Ops.)"
                                                class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-brand-blueRoyal/20 focus:border-brand-blueRoyal text-xs font-semibold text-slate-700 h-10"
                                            />
                                        </div>
                                        <button
                                            type="button"
                                            onclick={applyBulkSettings}
                                            class="px-5 py-2.5 bg-orange-500 hover:bg-orange-600 text-white text-[10px] font-black rounded-xl uppercase tracking-wider transition duration-200 h-10 shadow-sm font-outfit"
                                        >
                                            Terapkan ke Semua
                                        </button>
                                    </div>
                                </div>

                                <!-- Selected Products Table -->
                                <div class="overflow-x-auto border border-slate-200 rounded-2xl w-full">
                                    <table class="w-full text-left border-collapse text-[11px]">
                                        <thead>
                                            <tr class="bg-slate-50 border-b border-slate-200">
                                                <th class="px-4 py-3.5 font-bold text-slate-500">PRODUK</th>
                                                <th class="px-4 py-3.5 font-bold text-slate-500">HARGA DASAR</th>
                                                <th class="px-4 py-3.5 font-bold text-slate-500 w-[20%]">TIPE POTONGAN</th>
                                                <th class="px-4 py-3.5 font-bold text-slate-500 w-[18%]">NILAI POTONGAN</th>
                                                <th class="px-4 py-3.5 font-bold text-slate-500 w-[15%]">STOK PROMO</th>
                                                <th class="px-4 py-3.5 font-bold text-slate-500">HARGA AKHIR PROMO</th>
                                                <th class="px-4 py-3.5 font-bold text-slate-500 text-center w-[5%]">HAPUS</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-150 bg-white">
                                            {#each form.items as item, index}
                                                <tr class="hover:bg-slate-50/50">
                                                    <td class="px-4 py-3">
                                                        <div class="flex items-center">
                                                            <img
                                                                src={selectableItems.find(p => p.product_id == item.product_id && p.product_variant_id == item.product_variant_id)?.image || '/assets/images/placeholder.jpg'}
                                                                alt={item.product_name}
                                                                class="w-10 h-10 object-cover rounded-lg border border-slate-100 mr-3"
                                                            />
                                                            <div>
                                                                <p class="font-bold text-slate-700">
                                                                    {item.product_name}
                                                                    {#if item.variant_name && item.variant_name !== 'Semua Varian' && item.variant_name !== 'Tanpa Varian'}
                                                                        <span class="text-slate-500 font-normal"> - {item.variant_name}</span>
                                                                    {/if}
                                                                </p>
                                                                <span class="text-[9px] text-slate-400 font-semibold uppercase">
                                                                    {selectableItems.find(p => p.product_id == item.product_id && p.product_variant_id == item.product_variant_id)?.sku || ''}
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-3 font-semibold text-slate-600">
                                                        Rp {item.base_price.toLocaleString('id-ID')}
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <select
                                                            bind:value={item.discount_type}
                                                            class="w-full px-2 py-1.5 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none text-xs font-semibold text-slate-700 focus:border-brand-blueRoyal h-8"
                                                        >
                                                            <option value="percentage">% Persentase</option>
                                                            <option value="fixed">Rp Rupiah</option>
                                                            <option value="custom">Harga Kustom</option>
                                                        </select>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        {#if item.discount_type === 'custom'}
                                                            <input
                                                                type="text"
                                                                oninput={(e: any) => {
                                                                    const val = e.target.value.replace(/\D/g, '');
                                                                    item.promo_price = val ? parseInt(val, 10) : '';
                                                                }}
                                                                value={item.promo_price !== '' ? item.promo_price : ''}
                                                                class="w-full min-w-[100px] px-2.5 py-1 border border-slate-200 rounded-lg text-xs font-bold text-emerald-650 h-8 focus:ring-1 focus:ring-emerald-500/20 focus:border-emerald-500"
                                                                placeholder="Harga Promo..."
                                                            />
                                                        {:else}
                                                            <input
                                                                type="text"
                                                                oninput={(e: any) => {
                                                                    const val = e.target.value.replace(/\D/g, '');
                                                                    item.discount_value = val ? parseInt(val, 10) : '';
                                                                }}
                                                                value={item.discount_value !== '' ? item.discount_value : ''}
                                                                class="w-full min-w-[90px] px-2.5 py-1 border border-slate-200 rounded-lg text-xs font-semibold text-slate-700 h-8 focus:ring-1 focus:ring-brand-blueRoyal/20 focus:border-brand-blueRoyal"
                                                                placeholder={item.discount_type === 'percentage' ? '10%' : 'Rp 10.000'}
                                                            />
                                                        {/if}
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <input
                                                            type="text"
                                                            oninput={(e: any) => {
                                                                const val = e.target.value.replace(/\D/g, '');
                                                                item.promo_stock = val ? parseInt(val, 10) : '';
                                                            }}
                                                            value={item.promo_stock !== '' ? item.promo_stock : ''}
                                                            placeholder="Tak terbatas"
                                                            class="w-full min-w-[105px] px-2.5 py-1 border border-slate-200 rounded-lg text-xs font-semibold text-slate-650 h-8 focus:ring-1 focus:ring-brand-blueRoyal/20 focus:border-brand-blueRoyal"
                                                        />
                                                    </td>
                                                    <td class="px-4 py-3 font-bold text-brand-blueRoyal">
                                                        Rp {calculateFinalPrice(item).toLocaleString('id-ID')}
                                                    </td>
                                                    <td class="px-4 py-3 text-center">
                                                        <button
                                                            type="button"
                                                            onclick={() => removeTargetItem(index)}
                                                            class="p-1.5 hover:bg-slate-100 text-slate-400 hover:text-red-600 rounded-lg transition"
                                                        >
                                                            <i class="ti ti-trash text-base"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            {/each}
                                        </tbody>
                                    </table>
                                </div>
                            {/if}
                        </div>
                    {/if}
                </div>
            {/if}
        </form>
    </div>

    <!-- Product Selection Modal -->
    {#if showModal}
        <div class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <!-- Backdrop -->
            <!-- svelte-ignore a11y_click_events_have_key_events -->
            <!-- svelte-ignore a11y_no_static_element_interactions -->
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick={() => showModal = false}></div>

            <!-- Modal content -->
            <div class="relative bg-white rounded-3xl w-full max-w-4xl shadow-2xl flex flex-col max-h-[85vh] overflow-hidden z-10 border border-slate-100">
                <!-- Close button -->
                <button
                    type="button"
                    onclick={() => showModal = false}
                    class="absolute top-6 right-6 w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center hover:bg-slate-200 text-slate-500 transition"
                >
                    <i class="ti ti-x text-lg"></i>
                </button>

                <!-- Header -->
                <div class="p-6 pb-4 border-b border-slate-150">
                    <h3 class="font-outfit font-black text-xl text-slate-800">
                        Pilih Produk Target
                    </h3>
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mt-1">
                        PILIH PRODUK FURNITURE YANG AKAN DIKENAKAN PROMOSI
                    </p>
                </div>

                <!-- Body -->
                <div class="p-6 overflow-y-auto flex-grow flex flex-col min-h-0 space-y-4">
                    <!-- Search & Selected Counter -->
                    <div class="flex flex-col sm:flex-row gap-4 items-center justify-between">
                        <div class="relative w-full sm:w-80">
                            <i class="ti ti-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                            <input
                                type="text"
                                bind:value={modalSearch}
                                placeholder="Cari nama produk atau SKU..."
                                class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-brand-blueRoyal/20 focus:border-brand-blueRoyal text-xs font-semibold text-slate-700 h-9"
                            />
                        </div>
                        <div class="flex items-center gap-2 self-end sm:self-center">
                            <span class="bg-blue-50 text-brand-blueRoyal text-[10px] font-black tracking-wider px-3 py-1.5 rounded-lg uppercase">
                                {tempSelectedIds.length} Terpilih
                            </span>
                        </div>
                    </div>

                    <!-- Empty Select Status -->
                    {#if tempSelectedIds.length === 0}
                        <p class="text-xs text-slate-400 font-medium italic">
                            Belum ada produk spesifik terpilih.
                        </p>
                    {/if}

                    <!-- Scrollable Table -->
                    <div class="overflow-x-auto border border-slate-200 rounded-2xl flex-grow">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200">
                                    <th class="px-4 py-3 font-bold text-slate-500 w-[5%] text-center">
                                        <input
                                            type="checkbox"
                                            checked={isAllVisibleSelected}
                                            onchange={toggleSelectAllVisible}
                                            class="w-4 h-4 rounded border-slate-350 text-brand-blueRoyal focus:ring-brand-blueRoyal/20"
                                        />
                                    </th>
                                    <th class="px-4 py-3 font-bold text-slate-500">PRODUK</th>
                                    <th class="px-4 py-3 font-bold text-slate-500">KATEGORI</th>
                                    <th class="px-4 py-3 font-bold text-slate-500">HARGA</th>
                                    <th class="px-4 py-3 font-bold text-slate-500">STOK</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-150 bg-white">
                                {#each filteredSelectableItems as item}
                                    <tr
                                        class="hover:bg-slate-50/50 cursor-pointer"
                                        onclick={() => toggleSelection(item.id)}
                                    >
                                        <!-- svelte-ignore a11y_click_events_have_key_events -->
                                        <!-- svelte-ignore a11y_no_noninteractive_element_interactions -->
                                        <td class="px-4 py-3 text-center" onclick={(e) => e.stopPropagation()}>
                                            <input
                                                type="checkbox"
                                                checked={tempSelectedIds.includes(item.id)}
                                                onchange={() => toggleSelection(item.id)}
                                                class="w-4 h-4 rounded border-slate-350 text-brand-blueRoyal focus:ring-brand-blueRoyal/20"
                                            />
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center">
                                                <img
                                                    src={item.image || '/assets/images/placeholder.jpg'}
                                                    alt={item.name}
                                                    class="w-10 h-10 object-cover rounded-lg border border-slate-100 mr-3"
                                                />
                                                <div>
                                                    <p class="font-bold text-slate-700">{item.name}</p>
                                                    <span class="text-[10px] text-slate-400 font-semibold uppercase">{item.sku}</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="bg-slate-100 text-slate-600 text-[10px] font-bold px-2.5 py-1 rounded-full uppercase tracking-wider">
                                                {item.category_name}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 font-semibold text-slate-750">
                                            Rp {item.price.toLocaleString('id-ID')}
                                        </td>
                                        <td class="px-4 py-3">
                                            {#if item.stock === 0}
                                                <span class="text-red-500 font-bold">0</span>
                                            {:else if item.stock === 9999}
                                                <span class="text-slate-500 font-semibold">Tak Terbatas</span>
                                            {:else}
                                                <span class="text-slate-600 font-semibold">{item.stock}</span>
                                            {/if}
                                        </td>
                                    </tr>
                                {/each}
                                {#if filteredSelectableItems.length === 0}
                                    <tr>
                                        <td colspan="5" class="text-center py-8 text-slate-400">
                                            Produk tidak ditemukan.
                                        </td>
                                    </tr>
                                {/if}
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Footer -->
                <div class="p-6 border-t border-slate-150 flex justify-end gap-3 bg-slate-50">
                    <button
                        type="button"
                        onclick={() => showModal = false}
                        class="px-6 py-2.5 border border-slate-200 hover:bg-slate-100 text-slate-650 text-xs font-bold rounded-xl transition duration-200"
                    >
                        BATAL
                    </button>
                    <button
                        type="button"
                        onclick={saveModalSelections}
                        class="px-6 py-2.5 bg-brand-blueRoyal hover:bg-blue-800 text-white text-xs font-bold rounded-xl transition duration-200 shadow-md font-outfit"
                    >
                        SIMPAN PILIHAN
                    </button>
                </div>
            </div>
        </div>
    {/if}
</AdminLayout>
