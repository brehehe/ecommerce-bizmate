<script>
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { useForm, Link, page } from '@inertiajs/svelte';
    import {
        update as adminProductsUpdate,
        index as adminProductsIndex,
    } from '@/routes/admin/products';

    // Import new UI components
    import Input from '@/components/ui/Input.svelte';
    import InputCurrency from '@/components/ui/InputCurrency.svelte';
    import Textarea from '@/components/ui/Textarea.svelte';
    import SelectSearch from '@/components/ui/SelectSearch.svelte';
    import SelectSearchMultiple from '@/components/ui/SelectSearchMultiple.svelte';
    import Toggle from '@/components/ui/Toggle.svelte';

    let { categories = [], brands = [], product } = $props();

    let globalTaxEnabled = $derived(page.props.settings?.tax_enabled ?? false);
    let globalTaxPercentage = $derived(
        page.props.settings?.tax_percentage ?? 0,
    );

    const form = useForm({
        _method: 'PUT',
        name: product.name,
        sku: product.sku,
        category_ids: product.categories ? product.categories.map((c) => c.id) : (product.category_id ? [product.category_id] : []),
        brand_ids: product.brands ? product.brands.map((b) => b.id) : (product.brand_id ? [product.brand_id] : []),
        specifications: product.specifications || {},
        size_chart: product.size_chart || null,

        price: product.product_price?.price || '',
        cost: product.product_price?.cost || '',

        stock: product.product_stock?.stock || '',
        min_stock: product.product_stock?.min_stock || '',
        min_purchase: product.product_stock?.min_purchase || 1,
        is_unlimited: !!product.product_stock?.is_unlimited,
        stock_status: product.stock_status || 'Tersedia (In Stock)',

        summary: product.summary || '',
        description: product.description || '',
        weight: product.weight || '',
        length: product.length || '',
        width: product.width || '',
        height: product.height || '',
        tax_enabled: !!product.tax_enabled,
        tax_rate: product.tax_rate || '',
        active: !!product.active,
        photos: [],
        variations: [],
        variants: [],
        tier_prices: product.tier_prices
            ? product.tier_prices.map((tp) => ({
                  min_qty: tp.min_qty,
                  price: tp.price,
              }))
            : [],
        video_url: product.video_path || '',
        video_file: null,
        model_3d_url: product.model_3d_path || '',
        model_3d_file: null,
        model_3d_usdz_url: product.model_3d_usdz_path || '',
        model_3d_usdz_file: null,
    });

    function formatImagePath(path) {
        if (!path) return '';
        if (
            path.startsWith('http://') ||
            path.startsWith('https://') ||
            path.startsWith('/')
        ) {
            return path;
        }
        return '/' + path;
    }

    let uploadedPhotos = $state(
        product.images
            ? product.images.map((img) => formatImagePath(img.path))
            : [],
    );
    let enableVariants = $state(
        !!(product.variations && product.variations.length > 0),
    );
    let useVariantImages = $state(false);

    // Interactive Media Previews & Handlers
    let videoPreview = $state(product.video_path ? formatImagePath(product.video_path) : '');
    let model3dFileName = $state(product.model_3d_path ? product.model_3d_path.split('/').pop() : '');
    let model3dUsdzFileName = $state(product.model_3d_usdz_path ? product.model_3d_usdz_path.split('/').pop() : '');

    function handleVideoFile(e) {
        const file = e.target.files[0];
        if (file) {
            form.video_file = file;
            videoPreview = URL.createObjectURL(file);
        }
    }

    function removeVideo() {
        form.video_file = null;
        form.video_url = '';
        videoPreview = '';
    }

    function handleModel3dFile(e) {
        const file = e.target.files[0];
        if (file) {
            form.model_3d_file = file;
            model3dFileName = file.name;
        }
    }

    function removeModel3d() {
        form.model_3d_file = null;
        form.model_3d_url = '';
        model3dFileName = '';
    }

    function handleModel3dUsdzFile(e) {
        const file = e.target.files[0];
        if (file) {
            form.model_3d_usdz_file = file;
            model3dUsdzFileName = file.name;
        }
    }

    function removeModel3dUsdz() {
        form.model_3d_usdz_file = null;
        form.model_3d_usdz_url = '';
        model3dUsdzFileName = '';
    }
    let variations = $state(
        (product.variations || []).map((v, idx) => ({
            id: v.id,
            name: v.name,
            use_images:
                v.use_images ??
                ((v.options && v.options.some((opt) => opt.image)) ||
                    (idx === 0 && v.options && v.options.length > 0)),
            options: (v.options || []).map((opt) => ({
                id: opt.id,
                name: opt.name,
                description: opt.description || '',
                image: opt.image || '',
            })),
        })),
    );

    // Map variants back from server to UI format
    let variants = $state(
        product.variants
            ? product.variants.map((v) => {
                  const sortedOptions = v.options
                      ? [...v.options].sort(
                            (a, b) =>
                                a.product_variation_id - b.product_variation_id,
                        )
                      : [];
                  const optionIdStr = sortedOptions.map((o) => o.id).join('_');
                  return {
                      id: optionIdStr || 'v-' + v.id,
                      name:
                          sortedOptions.map((o) => o.name).join(' - ') || v.sku,
                      sku: v.sku,
                      is_custom:
                          v.product_price !== null ||
                          v.product_stock !== null ||
                          v.weight !== null,
                      custom_price: v.product_price !== null,
                      custom_stock: v.product_stock !== null,
                      custom_weight: v.weight !== null,
                      price: v.product_price?.price ?? '',
                      cost: v.product_price?.cost ?? '',
                      tier_prices: v.tier_prices
                          ? v.tier_prices.map((tp) => ({
                                min_qty: tp.min_qty,
                                price: tp.price,
                            }))
                          : [],
                      stock: v.product_stock?.stock ?? '',
                      min_stock: v.product_stock?.min_stock ?? '',
                      min_purchase: v.product_stock?.min_purchase ?? 1,
                      is_unlimited: v.product_stock?.is_unlimited ?? false,
                      weight: v.weight ?? '',
                      length: v.length ?? '',
                      width: v.width ?? '',
                      height: v.height ?? '',
                      active: true,
                  };
              })
            : [],
    );

    let globalCustomPrice = $state(
        product.variants
            ? product.variants.some((v) => v.product_price !== null)
            : false,
    );
    let globalCustomStock = $state(
        product.variants
            ? product.variants.some((v) => v.product_stock !== null)
            : false,
    );
    let globalCustomWeight = $state(
        product.variants
            ? product.variants.some((v) => v.weight !== null)
            : false,
    );

    // Track last product ID for SPA/Inertia hydration sync
    let lastProductId = $state(product.id);

    let specifications = $state(
        product.specifications
            ? Object.entries(product.specifications).map(([label, value]) => ({
                  label,
                  value: String(value),
              }))
            : [],
    );

    let showSizeChart = $state(!!(product.size_chart && product.size_chart.enabled));
    let sizeChartHeaders = $state(
        product.size_chart?.headers || ['Ukuran', 'Lebar Dada (cm)', 'Panjang (cm)', 'Panjang Lengan (cm)']
    );
    let sizeChartRows = $state(
        product.size_chart?.rows || [
            { size: 'S', values: ['48', '68', '21'], min_height: 150, max_height: 160, min_weight: 45, max_weight: 55 },
            { size: 'M', values: ['50', '70', '22'], min_height: 160, max_height: 170, min_weight: 55, max_weight: 65 },
            { size: 'L', values: ['52', '72', '23'], min_height: 170, max_height: 180, min_weight: 65, max_weight: 75 },
            { size: 'XL', values: ['54', '74', '24'], min_height: 180, max_height: 190, min_weight: 75, max_weight: 85 }
        ]
    );

    function addSizeChartHeader() {
        sizeChartHeaders = [...sizeChartHeaders, 'Kolom Baru'];
        sizeChartRows = sizeChartRows.map(row => ({
            ...row,
            values: [...row.values, '']
        }));
    }

    function removeSizeChartHeader(index) {
        if (index === 0) return;
        sizeChartHeaders = sizeChartHeaders.filter((_, i) => i !== index);
        sizeChartRows = sizeChartRows.map(row => ({
            ...row,
            values: row.values.filter((_, i) => i !== (index - 1))
        }));
    }

    function addSizeChartRow() {
        const valCount = sizeChartHeaders.length - 1;
        const emptyVals = Array(valCount).fill('');
        sizeChartRows = [
            ...sizeChartRows,
            { size: '', values: emptyVals, min_height: '', max_height: '', min_weight: '', max_weight: '' }
        ];
    }

    function removeSizeChartRow(index) {
        sizeChartRows = sizeChartRows.filter((_, i) => i !== index);
    }

    function addSpecification() {
        specifications = [...specifications, { label: '', value: '' }];
    }

    function removeSpecification(index) {
        specifications = specifications.filter((_, i) => i !== index);
    }

    let brandOptions = $derived(
        brands.map((b) => ({ id: b.id, name: b.name })),
    );

    $effect(() => {
        if (product && product.id !== lastProductId) {
            lastProductId = product.id;

            uploadedPhotos = product.images
                ? product.images.map((img) => formatImagePath(img.path))
                : [];
            enableVariants = !!(
                product.variations && product.variations.length > 0
            );

            variations = (product.variations || []).map((v, idx) => ({
                id: v.id,
                name: v.name,
                use_images:
                    v.use_images ??
                    ((v.options && v.options.some((opt) => opt.image)) ||
                        (idx === 0 && v.options && v.options.length > 0)),
                options: (v.options || []).map((opt) => ({
                    id: opt.id,
                    name: opt.name,
                    description: opt.description || '',
                    image: opt.image || '',
                })),
            }));

            variants = product.variants
                ? product.variants.map((v) => {
                      const sortedOptions = v.options
                          ? [...v.options].sort(
                                (a, b) =>
                                    a.product_variation_id -
                                    b.product_variation_id,
                            )
                          : [];
                      const optionIdStr = sortedOptions
                          .map((o) => o.id)
                          .join('_');
                      return {
                          id: optionIdStr || 'v-' + v.id,
                          name:
                              sortedOptions.map((o) => o.name).join(' - ') ||
                              v.sku,
                          sku: v.sku,
                          is_custom:
                              v.product_price !== null ||
                              v.product_stock !== null ||
                              v.weight !== null,
                          custom_price: v.product_price !== null,
                          custom_stock: v.product_stock !== null,
                          custom_weight: v.weight !== null,
                          price: v.product_price?.price ?? '',
                          cost: v.product_price?.cost ?? '',
                          tier_prices: v.tier_prices
                              ? v.tier_prices.map((tp) => ({
                                    min_qty: tp.min_qty,
                                    price: tp.price,
                                }))
                              : [],
                          stock: v.product_stock?.stock ?? '',
                          min_stock: v.product_stock?.min_stock ?? '',
                          min_purchase: v.product_stock?.min_purchase ?? 1,
                          is_unlimited: v.product_stock?.is_unlimited ?? false,
                          weight: v.weight ?? '',
                          length: v.length ?? '',
                          width: v.width ?? '',
                          height: v.height ?? '',
                          active: true,
                      };
                  })
                : [];

            globalCustomPrice = product.variants
                ? product.variants.some((v) => v.product_price !== null)
                : false;
            globalCustomStock = product.variants
                ? product.variants.some((v) => v.product_stock !== null)
                : false;
            globalCustomWeight = product.variants
                ? product.variants.some((v) => v.weight !== null)
                : false;

            // Sync form properties
            form.name = product.name;
            form.sku = product.sku;
            form.category_ids = product.categories ? product.categories.map((c) => c.id) : (product.category_id ? [product.category_id] : []);
            form.brand_ids = product.brands ? product.brands.map((b) => b.id) : (product.brand_id ? [product.brand_id] : []);
            form.brand = product.brand || '';
            form.specifications = product.specifications || {};
            specifications = product.specifications
                ? Object.entries(product.specifications).map(([label, value]) => ({
                      label,
                      value: String(value),
                  }))
                : [];
            form.size_chart = product.size_chart || null;
            showSizeChart = !!(product.size_chart && product.size_chart.enabled);
            sizeChartHeaders = product.size_chart?.headers || ['Ukuran', 'Lebar Dada (cm)', 'Panjang (cm)', 'Panjang Lengan (cm)'];
            sizeChartRows = product.size_chart?.rows || [
                { size: 'S', values: ['48', '68', '21'], min_height: 150, max_height: 160, min_weight: 45, max_weight: 55 },
                { size: 'M', values: ['50', '70', '22'], min_height: 160, max_height: 170, min_weight: 55, max_weight: 65 },
                { size: 'L', values: ['52', '72', '23'], min_height: 170, max_height: 180, min_weight: 65, max_weight: 75 },
                { size: 'XL', values: ['54', '74', '24'], min_height: 180, max_height: 190, min_weight: 75, max_weight: 85 }
            ];
            form.price = product.product_price?.price || '';
            form.cost = product.product_price?.cost || '';
            form.stock = product.product_stock?.stock || '';
            form.min_stock = product.product_stock?.min_stock || '';
            form.min_purchase = product.product_stock?.min_purchase || 1;
            form.is_unlimited = !!product.product_stock?.is_unlimited;
            form.stock_status = product.stock_status || 'Tersedia (In Stock)';
            form.summary = product.summary || '';
            form.description = product.description || '';
            form.weight = product.weight || '';
            form.length = product.length || '';
            form.width = product.width || '';
            form.height = product.height || '';
            form.tax_enabled = !!product.tax_enabled;
            form.tax_rate = product.tax_rate || '';
            form.active = !!product.active;
            form.tier_prices = product.tier_prices
                ? product.tier_prices.map((tp) => ({
                      min_qty: tp.min_qty,
                      price: tp.price,
                  }))
                : [];
        }
    });

    // Derived category options for SelectSearch
    let categoryOptions = $derived(
        categories.map((c) => ({ id: c.id, name: c.name })),
    );

    let taxAmount = $derived(
        form.tax_enabled && form.price
            ? (form.price * globalTaxPercentage) / 100
            : 0,
    );
    let finalPrice = $derived(form.price ? form.price + taxAmount : 0);

    function triggerPhotoUpload() {
        document.getElementById('multi-photo-input').click();
    }

    function handlePhotoUpload(event) {
        const files = Array.from(event.target.files);
        files.forEach((file) => {
            const reader = new FileReader();
            reader.onload = (e) => {
                uploadedPhotos = [...uploadedPhotos, e.target.result];
            };
            reader.readAsDataURL(file);
        });
    }

    function removePhoto(index) {
        uploadedPhotos = uploadedPhotos.filter((_, i) => i !== index);
    }

    function addVariation() {
        if (variations.length < 2) {
            variations = [
                ...variations,
                {
                    id: Date.now(),
                    name: '',
                    use_images: variations.length === 0,
                    options: [],
                },
            ];
        }
    }

    function removeVariation(index) {
        variations = variations.filter((_, i) => i !== index);
        generateCombinations();
    }

    function addOption(vIndex) {
        const nameInput = document.getElementById(`new-opt-name-${vIndex}`);
        if (!nameInput || !nameInput.value.trim()) return;

        variations[vIndex].options = [
            ...variations[vIndex].options,
            {
                id: 'opt-' + Date.now(),
                name: nameInput.value.trim(),
                description: '',
                image: '',
            },
        ];
        nameInput.value = '';
        generateCombinations();
    }

    function removeOption(vIndex, oIndex) {
        variations[vIndex].options = variations[vIndex].options.filter(
            (_, i) => i !== oIndex,
        );
        generateCombinations();
    }

    function uploadOptionImage(event, vIndex, oIndex) {
        const file = event.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = (e) => {
            variations[vIndex].options[oIndex].image = e.target.result;
            generateCombinations();
        };
        reader.readAsDataURL(file);
    }

    function generateCombinations() {
        if (variations.length === 0 || variations[0].options.length === 0) {
            variants = [];
            return;
        }

        const v1 = variations[0];
        let newVariants = [];

        if (variations.length === 1 || variations[1].options.length === 0) {
            newVariants = v1.options.map((opt) => ({
                id: opt.id,
                name: opt.name,
                image: opt.image,
                sku: product.sku
                    ? `${product.sku}-${opt.name.toUpperCase().replace(/[^A-Z0-9]/g, '')}`
                    : '',
                is_custom: false,
                custom_price: false,
                custom_stock: false,
                custom_weight: false,
                price: '',
                cost: '',
                tier_prices: [],
                stock: '',
                min_stock: '',
                min_purchase: 1,
                is_unlimited: false,
                weight: '',
                length: '',
                width: '',
                height: '',
                active: true,
            }));
        } else {
            const v2 = variations[1];
            v1.options.forEach((o1) => {
                v2.options.forEach((o2) => {
                    newVariants.push({
                        id: `${o1.id}_${o2.id}`,
                        name: `${o1.name} - ${o2.name}`,
                        image: o1.image || o2.image,
                        sku: product.sku
                            ? `${product.sku}-${o1.name.toUpperCase().replace(/[^A-Z0-9]/g, '')}-${o2.name.toUpperCase().replace(/[^A-Z0-9]/g, '')}`
                            : '',
                        is_custom: false,
                        custom_price: false,
                        custom_stock: false,
                        custom_weight: false,
                        price: '',
                        cost: '',
                        tier_prices: [],
                        stock: '',
                        min_stock: '',
                        min_purchase: 1,
                        is_unlimited: false,
                        weight: '',
                        length: '',
                        width: '',
                        height: '',
                        active: true,
                    });
                });
            });
        }

        variants = newVariants.map((nv) => {
            const existing = variants.find(
                (ev) => String(ev.id) === String(nv.id),
            );
            if (existing) {
                return {
                    ...nv,
                    ...existing,
                    name: nv.name,
                    image: nv.image,
                };
            }

            // Try to find a parent/prefix match (sharing the same first option ID, e.g. "Hitam" matches "Hitam - XL")
            const firstOptionIdOfNew = String(nv.id).split('_')[0];
            const parentVariant = variants.find((ev) => {
                const firstOptionIdOfEv = String(ev.id).split('_')[0];
                return firstOptionIdOfEv === firstOptionIdOfNew;
            });

            if (parentVariant) {
                return {
                    ...nv,
                    is_custom: parentVariant.is_custom,
                    custom_price: parentVariant.custom_price,
                    custom_stock: parentVariant.custom_stock,
                    custom_weight: parentVariant.custom_weight,
                    price: parentVariant.price,
                    cost: parentVariant.cost,
                    stock: parentVariant.stock,
                    min_stock: parentVariant.min_stock,
                    min_purchase: parentVariant.min_purchase,
                    is_unlimited: parentVariant.is_unlimited,
                    weight: parentVariant.weight,
                    length: parentVariant.length,
                    width: parentVariant.width,
                    height: parentVariant.height,
                    active: parentVariant.active,
                };
            }

            // Inherit settings from the last configured variant if it's a new one and has no parent/prefix match
            if (variants.length > 0) {
                const lastExisting = variants[variants.length - 1];
                return {
                    ...nv,
                    is_custom: lastExisting.is_custom,
                    custom_price: lastExisting.custom_price,
                    custom_stock: lastExisting.custom_stock,
                    custom_weight: lastExisting.custom_weight,
                    price: lastExisting.price,
                    cost: lastExisting.cost,
                    stock: lastExisting.stock,
                    min_stock: lastExisting.min_stock,
                    min_purchase: lastExisting.min_purchase,
                    is_unlimited: lastExisting.is_unlimited,
                    weight: lastExisting.weight,
                    length: lastExisting.length,
                    width: lastExisting.width,
                    height: lastExisting.height,
                    active: lastExisting.active,
                };
            }
            return nv;
        });
    }

    function submit() {
        form.photos = $state.snapshot(uploadedPhotos);
        const rawVariations = $state.snapshot(variations);
        const rawVariants = $state.snapshot(variants);

        // Filter and set master tier prices
        form.tier_prices = form.tier_prices
            ? form.tier_prices.filter(
                  (tp) =>
                      tp.min_qty >= 2 &&
                      tp.price !== '' &&
                      tp.price !== null &&
                      Number(tp.price) > 0,
              )
            : [];

        // Serialize specifications to dynamic key-value object
        const specObj = {};
        specifications.forEach((spec) => {
            if (spec.label.trim()) {
                specObj[spec.label.trim()] = spec.value.trim();
            }
        });
        form.specifications = specObj;

        form.size_chart = showSizeChart ? {
            enabled: true,
            headers: $state.snapshot(sizeChartHeaders),
            rows: $state.snapshot(sizeChartRows)
        } : null;

        form.variations = enableVariants ? rawVariations : [];
        // Only submit active variants, and clear hidden properties if toggle is off
        form.variants = enableVariants
            ? rawVariants
                  .filter((v) => v.active)
                  .map((v) => {
                      const isCustom =
                          globalCustomPrice ||
                          globalCustomStock ||
                          globalCustomWeight;
                      return {
                          ...v,
                          is_custom: isCustom,
                          custom_price: globalCustomPrice,
                          custom_stock: globalCustomStock,
                          custom_weight: globalCustomWeight,
                          price: isCustom && globalCustomPrice ? v.price : '',
                          cost: isCustom && globalCustomPrice ? v.cost : '',
                          // Filter and set variant tier prices
                          tier_prices:
                              isCustom && globalCustomPrice && v.tier_prices
                                  ? v.tier_prices.filter(
                                        (tp) =>
                                            tp.min_qty >= 2 &&
                                            tp.price !== '' &&
                                            tp.price !== null &&
                                            Number(tp.price) > 0,
                                    )
                                  : [],
                          stock: isCustom && globalCustomStock ? v.stock : '',
                          min_stock:
                              isCustom && globalCustomStock ? v.min_stock : '',
                          min_purchase:
                              isCustom && globalCustomStock
                                  ? v.min_purchase
                                  : 1,
                          is_unlimited:
                              isCustom && globalCustomStock
                                  ? v.is_unlimited
                                  : false,
                          weight:
                              isCustom && globalCustomWeight ? v.weight : '',
                          length:
                              isCustom && globalCustomWeight ? v.length : '',
                          width: isCustom && globalCustomWeight ? v.width : '',
                          height:
                              isCustom && globalCustomWeight ? v.height : '',
                      };
                  })
            : [];
        form.post(adminProductsUpdate.url({ product: product.id }));
    }
</script>

<svelte:head>
    <title>Edit Produk: {product.name}</title>
</svelte:head>

<AdminLayout>
    <div class="flex-grow flex flex-col min-h-screen">
        <main class="flex-grow p-4 sm:p-8 w-full max-w-full mx-auto">
            <div class="flex items-center justify-between gap-4 mb-6">
                <div>
                    <h2 class="font-outfit font-black text-2xl text-slate-800">
                        Edit Produk
                    </h2>
                    <p
                        class="text-xs text-slate-400 font-bold uppercase tracking-wider"
                    >
                        Perbarui data produk mebel di katalog toko Anda
                    </p>
                </div>
                <Link
                    href={adminProductsIndex.url()}
                    class="px-4 py-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 font-bold rounded-xl text-xs transition"
                >
                    Kembali
                </Link>
            </div>

            <form
                onsubmit={(e) => {
                    e.preventDefault();
                    submit();
                }}
                class="space-y-6"
            >
                <!-- Card: Media -->
                <div
                    class="bg-white rounded-3xl border border-slate-200 p-6 shadow-soft"
                >
                    <h3
                        class="font-outfit font-black text-lg text-slate-800 mb-4 border-b border-slate-100 pb-4"
                    >
                        Foto Produk <span class="text-red-600">*</span>
                    </h3>
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-4">
                        {#each uploadedPhotos as photo, i}
                            <div
                                class="relative rounded-2xl aspect-square overflow-hidden bg-slate-100 border border-slate-200 group"
                            >
                                <img
                                    src={photo}
                                    alt="Upload {i}"
                                    class="w-full h-full object-cover"
                                />
                                {#if i === 0}
                                    <div
                                        class="absolute top-2 left-2 bg-brand-blueRoyal text-white text-[9px] font-bold px-2 py-0.5 rounded-md"
                                    >
                                        Utama
                                    </div>
                                {/if}
                                <button
                                    type="button"
                                    onclick={() => removePhoto(i)}
                                    class="absolute top-2 right-2 w-6 h-6 rounded-full bg-slate-900/60 text-white hover:bg-rose-600 flex items-center justify-center opacity-0 group-hover:opacity-100 transition"
                                >
                                    <i class="ti ti-x text-xs"></i>
                                </button>
                            </div>
                        {/each}
                        <button
                            type="button"
                            onclick={triggerPhotoUpload}
                            class="border-2 border-dashed border-slate-200 rounded-2xl aspect-square flex flex-col items-center justify-center hover:bg-slate-50 text-slate-400 transition"
                        >
                            <i class="ti ti-camera-plus text-2xl mb-1"></i>
                            <span class="text-[10px] font-bold"
                                >Tambah Foto</span
                            >
                        </button>
                    </div>
                    <input
                        type="file"
                        id="multi-photo-input"
                        class="hidden"
                        accept="image/*"
                        multiple
                        onchange={handlePhotoUpload}
                    />
                </div>

                <!-- Card: Informasi Dasar -->
                <div
                    class="bg-white rounded-3xl border border-slate-200 p-6 shadow-soft"
                >
                    <h3
                        class="font-outfit font-black text-lg text-slate-800 mb-4 border-b border-slate-100 pb-4"
                    >
                        Informasi Dasar
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <Input
                            bind:value={form.name}
                            id="name"
                            label="Nama Produk"
                            placeholder="Cth: Meja Kayu Jati"
                            required={true}
                            error={form.errors.name}
                        />
                        <Input
                            bind:value={form.sku}
                            id="sku"
                            label="Kode SKU"
                            placeholder="Cth: MJ-001"
                            required={true}
                            error={form.errors.sku}
                        />
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <SelectSearchMultiple
                            bind:value={form.category_ids}
                            options={categoryOptions}
                            label="Kategori Produk"
                            required={true}
                            error={form.errors.category_ids}
                        />
                        <SelectSearchMultiple
                            bind:value={form.brand_ids}
                            options={brandOptions}
                            label="Merek (Brand)"
                            error={form.errors.brand_ids}
                        />
                    </div>
                    <div class="space-y-4">
                        <Input
                            bind:value={form.summary}
                            id="summary"
                            label="Ringkasan Singkat"
                            placeholder="Satu kalimat tentang produk..."
                            error={form.errors.summary}
                        />
                        <Textarea
                            bind:value={form.description}
                            id="description"
                            label="Deskripsi Lengkap"
                            placeholder="Penjelasan detail..."
                            required={true}
                            error={form.errors.description}
                        />
                    </div>
                </div>

                <!-- Card: Master Harga & Stok -->
                <div
                    class="bg-white rounded-3xl border border-slate-200 p-6 shadow-soft"
                >
                    <h3
                        class="font-outfit font-black text-lg text-slate-800 mb-4 border-b border-slate-100 pb-4"
                    >
                        Harga & Stok (Master)
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <InputCurrency
                            bind:value={form.price}
                            id="price"
                            label="Harga Jual"
                            prefix="Rp"
                            required={true}
                            error={form.errors.price}
                        />
                        <InputCurrency
                            bind:value={form.cost}
                            id="cost"
                            label="Harga Modal (HPP)"
                            prefix="Rp"
                            error={form.errors.cost}
                        />
                    </div>

                    <!-- Wholesale Prices Section (Master) -->
                    <div class="mb-6 border-t border-slate-100 pt-5">
                        <div class="flex items-center justify-between mb-4">
                            <span
                                class="text-xs font-bold text-slate-500 flex items-center gap-1.5 uppercase tracking-wider"
                            >
                                <i class="ti ti-tags text-sm text-slate-400"
                                ></i> Harga Grosir (Master)
                            </span>
                            <button
                                type="button"
                                onclick={() => {
                                    if (!form.tier_prices)
                                        form.tier_prices = [];
                                    form.tier_prices.push({
                                        min_qty: 2,
                                        price: '',
                                    });
                                }}
                                class="px-3 py-1.5 bg-brand-blueRoyal/5 hover:bg-brand-blueRoyal/10 text-brand-blueRoyal text-xs font-bold rounded-xl flex items-center gap-1.5 transition"
                            >
                                <i class="ti ti-plus text-xs"></i> Tambah Grosir
                            </button>
                        </div>

                        {#if !form.tier_prices || form.tier_prices.length === 0}
                            <p class="text-xs text-slate-400 italic">
                                Belum ada harga grosir untuk produk ini.
                            </p>
                        {:else}
                            <div
                                class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4"
                            >
                                {#each form.tier_prices as tier, idx (idx)}
                                    <div
                                        class="flex items-center gap-3 bg-slate-50/50 p-3 rounded-2xl border border-slate-100 relative group"
                                    >
                                        <div class="w-24 shrink-0">
                                            <Input
                                                type="number"
                                                min="2"
                                                bind:value={tier.min_qty}
                                                id={`tier-min-qty-${idx}`}
                                                label="Min. Qty"
                                                placeholder="2"
                                            />
                                        </div>
                                        <div class="flex-grow">
                                            <InputCurrency
                                                bind:value={tier.price}
                                                id={`tier-price-${idx}`}
                                                label="Harga Satuan"
                                                prefix="Rp"
                                                placeholder="0"
                                            />
                                        </div>
                                        <button
                                            type="button"
                                            onclick={() => {
                                                form.tier_prices =
                                                    form.tier_prices.filter(
                                                        (_, i) => i !== idx,
                                                    );
                                            }}
                                            class="absolute -top-2 -right-2 w-6 h-6 bg-rose-500 text-white rounded-full flex items-center justify-center text-xs opacity-0 group-hover:opacity-100 transition-opacity shadow-md hover:bg-rose-600 cursor-pointer z-10"
                                            title="Hapus Grosir"
                                        >
                                            <i class="ti ti-x"></i>
                                        </button>
                                    </div>
                                {/each}
                            </div>
                        {/if}
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <Input
                            bind:value={form.stock}
                            type="number"
                            min="0"
                            id="stock"
                            label="Stok Saat Ini"
                            required={!form.is_unlimited}
                            readonly={form.is_unlimited}
                            error={form.errors.stock}
                        />
                        <Input
                            bind:value={form.min_stock}
                            type="number"
                            min="0"
                            id="min_stock"
                            label="Batas Minimum (Alert)"
                            error={form.errors.min_stock}
                        />
                        <Input
                            bind:value={form.min_purchase}
                            type="number"
                            min="1"
                            id="min_purchase"
                            label="Min Pembelian"
                            error={form.errors.min_purchase}
                        />
                    </div>

                    <div class="flex items-center gap-4">
                        <div class="flex-1">
                            <Toggle
                                bind:checked={form.is_unlimited}
                                label="Stok Tidak Terbatas"
                                description="Pilih jika barang selalu diproduksi/tersedia"
                                icon="ti-infinity"
                            />
                        </div>
                        {#if globalTaxEnabled}
                            <div
                                class="flex-1 p-4 bg-slate-50 border border-slate-200 rounded-2xl"
                            >
                                <Toggle
                                    bind:checked={form.tax_enabled}
                                    label="Belum Termasuk Pajak"
                                    description="Aktifkan jika harga belum ditambah pajak {globalTaxPercentage}%"
                                    icon="ti-receipt-tax"
                                />
                                {#if form.price > 0}
                                    {#if form.tax_enabled}
                                        <div
                                            class="mt-3 pt-3 border-t border-slate-200 text-xs text-slate-600 font-medium space-y-1"
                                        >
                                            <div class="flex justify-between">
                                                <span>Harga Asli (DPP):</span>
                                                <span
                                                    class="font-bold text-slate-800"
                                                    >Rp {form.price.toLocaleString(
                                                        'id-ID',
                                                    )}</span
                                                >
                                            </div>
                                            <div
                                                class="flex justify-between text-rose-500"
                                            >
                                                <span
                                                    >Pajak PPN ({globalTaxPercentage}%):</span
                                                >
                                                <span
                                                    >+ Rp {taxAmount.toLocaleString(
                                                        'id-ID',
                                                    )}</span
                                                >
                                            </div>
                                            <div
                                                class="flex justify-between text-slate-800 font-black pt-1 border-t border-dashed border-slate-200"
                                            >
                                                <span>Total Pembeli Bayar:</span
                                                >
                                                <span
                                                    class="text-brand-blueRoyal text-sm font-black"
                                                    >Rp {finalPrice.toLocaleString(
                                                        'id-ID',
                                                    )}</span
                                                >
                                            </div>
                                        </div>
                                    {:else}
                                        <div
                                            class="mt-3 pt-3 border-t border-slate-200 text-xs text-slate-600 font-medium space-y-1"
                                        >
                                            <div class="flex justify-between">
                                                <span>Total Pembeli Bayar:</span
                                                >
                                                <span
                                                    class="font-bold text-slate-800"
                                                    >Rp {form.price.toLocaleString(
                                                        'id-ID',
                                                    )}</span
                                                >
                                            </div>
                                            <div
                                                class="flex justify-between text-slate-500"
                                            >
                                                <span>Harga Asli (DPP):</span>
                                                <span
                                                    >Rp {Math.round(
                                                        form.price /
                                                            (1 +
                                                                globalTaxPercentage /
                                                                    100),
                                                    ).toLocaleString(
                                                        'id-ID',
                                                    )}</span
                                                >
                                            </div>
                                            <div
                                                class="flex justify-between text-rose-500"
                                            >
                                                <span
                                                    >Pajak PPN ({globalTaxPercentage}%
                                                    di dalam):</span
                                                >
                                                <span
                                                    >Rp {Math.round(
                                                        form.price -
                                                            form.price /
                                                                (1 +
                                                                    globalTaxPercentage /
                                                                        100),
                                                    ).toLocaleString(
                                                        'id-ID',
                                                    )}</span
                                                >
                                            </div>
                                        </div>
                                    {/if}
                                {:else}
                                    <div
                                        class="mt-3 pt-3 border-t border-slate-200 text-xs text-slate-500 font-medium"
                                    >
                                        Harga sudah termasuk pajak atau tidak
                                        kena pajak tambahan. Total pembeli
                                        bayar: <strong class="text-slate-800"
                                            >Rp 0</strong
                                        >
                                    </div>
                                {/if}
                            </div>
                        {/if}
                    </div>
                </div>

                <!-- Card: Dimensi Master -->
                <div
                    class="bg-white rounded-3xl border border-slate-200 p-6 shadow-soft"
                >
                    <h3
                        class="font-outfit font-black text-lg text-slate-800 mb-4 border-b border-slate-100 pb-4"
                    >
                        Pengiriman & Dimensi (Master)
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <Input
                            bind:value={form.weight}
                            type="number"
                            min="0"
                            id="weight"
                            label="Berat"
                            prefix="Gram"
                            error={form.errors.weight}
                        />
                        <Input
                            bind:value={form.length}
                            type="number"
                            min="0"
                            id="length"
                            label="Panjang"
                            prefix="Cm"
                            error={form.errors.length}
                        />
                        <Input
                            bind:value={form.width}
                            type="number"
                            min="0"
                            id="width"
                            label="Lebar"
                            prefix="Cm"
                            error={form.errors.width}
                        />
                        <Input
                            bind:value={form.height}
                            type="number"
                            min="0"
                            id="height"
                            label="Tinggi"
                            prefix="Cm"
                            error={form.errors.height}
                        />
                    </div>
                </div>

                <!-- Card: Spesifikasi Produk -->
                <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-soft">
                    <div class="flex items-center justify-between mb-4 border-b border-slate-100 pb-4">
                        <div>
                            <h3 class="font-outfit font-black text-lg text-slate-800">
                                Spesifikasi Produk
                            </h3>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-wider mt-1">
                                Tambahkan detail spesifikasi produk (misal: Bahan, Warna, Garansi)
                            </p>
                        </div>
                        <button
                            type="button"
                            onclick={addSpecification}
                            class="px-3.5 py-2 bg-brand-blueRoyal/5 hover:bg-brand-blueRoyal/10 text-brand-blueRoyal text-xs font-bold rounded-xl flex items-center gap-1.5 transition duration-200 font-outfit uppercase tracking-wider"
                        >
                            <i class="ti ti-plus text-xs"></i> Tambah Spesifikasi
                        </button>
                    </div>

                    {#if specifications.length === 0}
                        <div class="text-center py-10 border border-dashed border-slate-200 rounded-2xl bg-slate-50/30 flex flex-col items-center justify-center">
                            <div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mb-2">
                                <i class="ti ti-list text-lg"></i>
                            </div>
                            <p class="text-xs text-slate-500 font-medium">Belum ada spesifikasi produk yang ditambahkan.</p>
                            <button
                                type="button"
                                onclick={addSpecification}
                                class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-[10px] font-black rounded-lg uppercase tracking-wider transition mt-3 font-outfit"
                            >
                                Tambah Pertama
                            </button>
                        </div>
                    {:else}
                        <div class="space-y-4">
                            {#each specifications as spec, idx (idx)}
                                <div class="flex items-center gap-4 bg-slate-50/50 p-4 rounded-2xl border border-slate-100 relative group animate-in fade-in zoom-in-95 duration-150">
                                    <div class="flex-grow grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <Input
                                            type="text"
                                            bind:value={spec.label}
                                            id={`spec-label-${idx}`}
                                            label="Nama Spesifikasi"
                                            placeholder="Contoh: Bahan, Warna, Garansi"
                                            required={true}
                                        />
                                        <Input
                                            type="text"
                                            bind:value={spec.value}
                                            id={`spec-val-${idx}`}
                                            label="Nilai / Value"
                                            placeholder="Contoh: Kayu Jati, Putih, 1 Tahun"
                                            required={true}
                                        />
                                    </div>
                                    <button
                                        type="button"
                                        onclick={() => removeSpecification(idx)}
                                        class="w-9 h-9 rounded-xl border border-slate-250 hover:bg-rose-50 hover:text-rose-600 text-slate-500 flex items-center justify-center transition shrink-0 self-end mb-1 cursor-pointer"
                                        title="Hapus Spesifikasi"
                                    >
                                        <i class="ti ti-trash text-sm"></i>
                                    </button>
                                </div>
                            {/each}
                        </div>
                    {/if}
                </div>

                <!-- Card: Panduan Ukuran & Rekomendasi (Size Guide) -->
                <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-soft">
                    <div class="flex items-center justify-between mb-4 border-b border-slate-100 pb-4">
                        <div>
                            <h3 class="font-outfit font-black text-lg text-slate-800">
                                Panduan Ukuran & Kalkulator Rekomendasi
                            </h3>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-wider mt-1">
                                Aktifkan untuk menampilkan tabel panduan ukuran pakaian dan kalkulator rekomendasi tinggi/berat badan otomatis
                            </p>
                        </div>
                        <Toggle
                            bind:checked={showSizeChart}
                            label="Aktifkan Panduan Ukuran"
                        />
                    </div>

                    {#if showSizeChart}
                        <div class="space-y-6">
                            <!-- Kolom / Headers Builder -->
                            <div class="bg-slate-50/50 p-4 rounded-2xl border border-slate-100">
                                <div class="flex items-center justify-between mb-3">
                                    <span class="text-xs font-bold text-slate-600 uppercase tracking-wider">
                                        Kolom Tabel Ukuran
                                    </span>
                                    <button
                                        type="button"
                                        onclick={addSizeChartHeader}
                                        class="px-2.5 py-1 bg-brand-blueRoyal/5 hover:bg-brand-blueRoyal/10 text-brand-blueRoyal text-[10px] font-black rounded-lg uppercase tracking-wider transition"
                                    >
                                        + Tambah Kolom
                                    </button>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    {#each sizeChartHeaders as header, hIdx}
                                        <div class="flex items-center bg-white border border-slate-200 rounded-lg p-1 text-xs pl-2.5 shadow-sm">
                                            {#if hIdx === 0}
                                                <span class="font-bold text-slate-600 pr-2">{header}</span>
                                            {:else}
                                                <input
                                                    type="text"
                                                    bind:value={sizeChartHeaders[hIdx]}
                                                    class="w-28 focus:outline-none font-bold text-slate-700 bg-transparent"
                                                />
                                                <button
                                                    type="button"
                                                    onclick={() => removeSizeChartHeader(hIdx)}
                                                    class="text-slate-400 hover:text-rose-500 pl-1.5 pr-0.5"
                                                    title="Hapus Kolom"
                                                >
                                                    <i class="ti ti-x text-xs"></i>
                                                </button>
                                            {/if}
                                        </div>
                                    {/each}
                                </div>
                            </div>

                            <!-- Baris / Rows Builder -->
                            <div class="overflow-x-auto border border-slate-100 rounded-2xl">
                                <table class="w-full text-left text-xs border-collapse">
                                    <thead>
                                        <tr class="bg-slate-50 border-b border-slate-100">
                                            <th class="p-3 font-bold text-slate-500 uppercase tracking-wider w-16">Opsi</th>
                                            <th class="p-3 font-bold text-slate-500 uppercase tracking-wider w-24 text-center">{sizeChartHeaders[0]}</th>
                                            {#each sizeChartHeaders.slice(1) as header}
                                                <th class="p-3 font-bold text-slate-500 uppercase tracking-wider min-w-[80px] text-center">{header}</th>
                                            {/each}
                                            <th class="p-3 font-bold text-slate-500 uppercase tracking-wider w-40 text-center">Tinggi (cm)</th>
                                            <th class="p-3 font-bold text-slate-500 uppercase tracking-wider w-40 text-center">Berat (kg)</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        {#each sizeChartRows as row, rIdx}
                                            <tr class="hover:bg-slate-50/30">
                                                <td class="p-2 text-center">
                                                    <button
                                                        type="button"
                                                        onclick={() => removeSizeChartRow(rIdx)}
                                                        class="w-6 h-6 rounded-md hover:bg-rose-50 hover:text-rose-600 text-slate-400 flex items-center justify-center transition mx-auto cursor-pointer"
                                                        title="Hapus Baris"
                                                    >
                                                        <i class="ti ti-trash"></i>
                                                    </button>
                                                </td>
                                                <td class="p-2">
                                                    <input
                                                        type="text"
                                                        bind:value={row.size}
                                                        class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-1.5 focus:border-brand-blueRoyal focus:ring-1 focus:ring-brand-blueRoyal/20 focus:outline-none font-bold text-slate-700 text-center"
                                                        placeholder="S/M/L"
                                                    />
                                                </td>
                                                {#each sizeChartHeaders.slice(1) as _, hIdx}
                                                    <td class="p-2">
                                                        <input
                                                            type="text"
                                                            bind:value={row.values[hIdx]}
                                                            class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-1.5 focus:border-brand-blueRoyal focus:ring-1 focus:ring-brand-blueRoyal/20 focus:outline-none text-slate-600 text-center font-medium"
                                                            placeholder="Cth: 50"
                                                        />
                                                    </td>
                                                {/each}
                                                <!-- Tinggi range -->
                                                <td class="p-2">
                                                    <div class="flex items-center gap-1.5 justify-center">
                                                        <input
                                                            type="number"
                                                            bind:value={row.min_height}
                                                            class="w-16 bg-white border border-slate-200 rounded-lg px-1.5 py-1.5 text-center focus:outline-none"
                                                            placeholder="Min"
                                                        />
                                                        <span class="text-slate-400">-</span>
                                                        <input
                                                            type="number"
                                                            bind:value={row.max_height}
                                                            class="w-16 bg-white border border-slate-200 rounded-lg px-1.5 py-1.5 text-center focus:outline-none"
                                                            placeholder="Max"
                                                        />
                                                    </div>
                                                </td>
                                                <!-- Berat range -->
                                                <td class="p-2">
                                                    <div class="flex items-center gap-1.5 justify-center">
                                                        <input
                                                            type="number"
                                                            bind:value={row.min_weight}
                                                            class="w-16 bg-white border border-slate-200 rounded-lg px-1.5 py-1.5 text-center focus:outline-none"
                                                            placeholder="Min"
                                                        />
                                                        <span class="text-slate-400">-</span>
                                                        <input
                                                            type="number"
                                                            bind:value={row.max_weight}
                                                            class="w-16 bg-white border border-slate-200 rounded-lg px-1.5 py-1.5 text-center focus:outline-none"
                                                            placeholder="Max"
                                                        />
                                                    </div>
                                                </td>
                                            </tr>
                                        {/each}
                                    </tbody>
                                </table>
                            </div>

                            <button
                                type="button"
                                onclick={addSizeChartRow}
                                class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl flex items-center justify-center gap-1.5 transition w-full"
                            >
                                <i class="ti ti-plus text-xs"></i> Tambah Baris Ukuran
                            </button>
                        </div>
                    {/if}
                </div>

                <!-- Card: Media Interaktif (Video & 3D Augmented Reality) -->
                <div class="bg-white rounded-3xl border border-slate-200 p-6 shadow-soft">
                    <div class="border-b border-slate-100 pb-4 mb-5">
                        <h3 class="font-outfit font-black text-lg text-slate-800 flex items-center gap-2">
                            <i class="ti ti-video text-brand-blueRoyal"></i> Media Interaktif (Video & 3D AR)
                        </h3>
                        <p class="text-xs text-slate-400 font-bold uppercase tracking-wider mt-1">
                            Tambahkan video demonstrasi dan model 3D (format GLB/USDZ) untuk visual Augmented Reality di mobile.
                        </p>
                    </div>

                    <div class="space-y-6">
                        <!-- 1. Video Section -->
                        <div class="bg-slate-50/50 p-4 rounded-2xl border border-slate-100">
                            <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                                <i class="ti ti-movie text-base text-slate-400"></i> Video Produk
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- Upload Video -->
                                <div class="bg-white border-2 border-dashed border-slate-200 rounded-xl p-4 flex flex-col items-center justify-center text-center hover:border-brand-blueRoyal/40 transition relative">
                                    {#if videoPreview}
                                        <div class="w-full relative rounded-lg overflow-hidden border border-slate-100 bg-black aspect-video max-h-40">
                                            <video src={videoPreview} class="w-full h-full object-contain" controls></video>
                                            <button
                                                type="button"
                                                onclick={removeVideo}
                                                class="absolute top-1.5 right-1.5 w-7 h-7 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center shadow-md transition"
                                                title="Hapus Video"
                                            >
                                                <i class="ti ti-trash text-sm"></i>
                                            </button>
                                        </div>
                                    {:else}
                                        <i class="ti ti-video-plus text-3xl text-slate-350 mb-1.5"></i>
                                        <span class="text-xs font-bold text-slate-600 mb-0.5">Upload File Video</span>
                                        <span class="text-[10px] text-slate-400 font-medium mb-3">MP4, WEBM, atau MOV (Maks. 50MB)</span>
                                        <input
                                            type="file"
                                            accept="video/mp4,video/quicktime,video/webm"
                                            onchange={handleVideoFile}
                                            class="absolute inset-0 opacity-0 cursor-pointer w-full h-full"
                                        />
                                        <button type="button" class="px-3.5 py-1.5 bg-brand-blueRoyal/5 hover:bg-brand-blueRoyal/10 text-brand-blueRoyal text-[10px] font-black rounded-lg uppercase tracking-wider transition">Pilih File</button>
                                    {/if}
                                </div>
                                <!-- Video URL -->
                                <div class="flex flex-col justify-center">
                                    <label class="block text-[10px] font-bold text-slate-400 mb-1.5 uppercase tracking-wider">Atau Masukkan URL / Path Video</label>
                                    <Input
                                        id="video_url"
                                        placeholder="Cth: storage/products/videos/demo.mp4"
                                        bind:value={form.video_url}
                                    />
                                    <p class="text-[10px] text-slate-400 font-medium mt-2 leading-relaxed">
                                        *Catatan: Jika Anda mengunggah file video sekaligus memasukkan URL, file unggahan akan diprioritaskan.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- 2. Model 3D Section -->
                        <div class="bg-slate-50/50 p-4 rounded-2xl border border-slate-100">
                            <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                                <i class="ti ti-cube text-base text-slate-400"></i> Model 3D & Augmented Reality
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- GLB File -->
                                <div class="space-y-4">
                                    <div class="bg-white border-2 border-dashed border-slate-200 rounded-xl p-4 flex flex-col items-center justify-center text-center hover:border-brand-blueRoyal/40 transition relative">
                                        {#if model3dFileName}
                                            <div class="w-full flex items-center gap-2 bg-green-50/30 p-2.5 rounded-lg border border-green-100">
                                                <div class="w-8 h-8 rounded-lg bg-green-100 flex items-center justify-center text-green-600 shrink-0">
                                                    <i class="ti ti-cube-send text-base"></i>
                                                </div>
                                                <div class="min-w-0 flex-1 text-left">
                                                    <p class="text-[10px] text-green-600 font-bold uppercase tracking-wider">GLB Model Siap</p>
                                                    <p class="text-xs text-slate-700 font-semibold truncate leading-tight mt-0.5">{model3dFileName}</p>
                                                </div>
                                                <button
                                                    type="button"
                                                    onclick={removeModel3d}
                                                    class="p-1 hover:bg-slate-100 rounded-lg text-slate-400 hover:text-red-500 transition shrink-0"
                                                    title="Hapus Model"
                                                >
                                                    <i class="ti ti-trash text-sm"></i>
                                                </button>
                                            </div>
                                        {:else}
                                            <i class="ti ti-box-margin text-3xl text-slate-350 mb-1.5"></i>
                                            <span class="text-xs font-bold text-slate-600 mb-0.5">Model 3D (format GLB)</span>
                                            <span class="text-[10px] text-slate-400 font-medium mb-3">Format GLB Standar Web (Maks. 50MB)</span>
                                            <input
                                                type="file"
                                                accept=".glb"
                                                onchange={handleModel3dFile}
                                                class="absolute inset-0 opacity-0 cursor-pointer w-full h-full"
                                            />
                                            <button type="button" class="px-3.5 py-1.5 bg-brand-blueRoyal/5 hover:bg-brand-blueRoyal/10 text-brand-blueRoyal text-[10px] font-black rounded-lg uppercase tracking-wider transition">Pilih File GLB</button>
                                        {/if}
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-400 mb-1.5 uppercase tracking-wider">Atau URL Model GLB</label>
                                        <Input
                                            id="model_3d_url"
                                            placeholder="Cth: storage/products/models/item.glb"
                                            bind:value={form.model_3d_url}
                                        />
                                    </div>
                                </div>

                                <!-- USDZ File (iOS AR) -->
                                <div class="space-y-4">
                                    <div class="bg-white border-2 border-dashed border-slate-200 rounded-xl p-4 flex flex-col items-center justify-center text-center hover:border-brand-blueRoyal/40 transition relative">
                                        {#if model3dUsdzFileName}
                                            <div class="w-full flex items-center gap-2 bg-brand-blueRoyal/5 p-2.5 rounded-lg border border-brand-blueRoyal/10">
                                                <div class="w-8 h-8 rounded-lg bg-brand-blueRoyal/10 flex items-center justify-center text-brand-blueRoyal shrink-0">
                                                    <i class="ti ti-brand-apple text-base"></i>
                                                </div>
                                                <div class="min-w-0 flex-1 text-left">
                                                    <p class="text-[10px] text-brand-blueRoyal font-bold uppercase tracking-wider">iOS USDZ Model Siap</p>
                                                    <p class="text-xs text-slate-700 font-semibold truncate leading-tight mt-0.5">{model3dUsdzFileName}</p>
                                                </div>
                                                <button
                                                    type="button"
                                                    onclick={removeModel3dUsdz}
                                                    class="p-1 hover:bg-slate-100 rounded-lg text-slate-400 hover:text-red-500 transition shrink-0"
                                                    title="Hapus Model iOS"
                                                >
                                                    <i class="ti ti-trash text-sm"></i>
                                                </button>
                                            </div>
                                        {:else}
                                            <i class="ti ti-brand-apple text-3xl text-slate-350 mb-1.5"></i>
                                            <span class="text-xs font-bold text-slate-600 mb-0.5">Model iOS (format USDZ)</span>
                                            <span class="text-[10px] text-slate-400 font-medium mb-3">Untuk Augmented Reality di Safari iOS (Maks. 50MB)</span>
                                            <input
                                                type="file"
                                                accept=".usdz"
                                                onchange={handleModel3dUsdzFile}
                                                class="absolute inset-0 opacity-0 cursor-pointer w-full h-full"
                                            />
                                            <button type="button" class="px-3.5 py-1.5 bg-brand-blueRoyal/5 hover:bg-brand-blueRoyal/10 text-brand-blueRoyal text-[10px] font-black rounded-lg uppercase tracking-wider transition">Pilih File USDZ</button>
                                        {/if}
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-400 mb-1.5 uppercase tracking-wider">Atau URL Model USDZ</label>
                                        <Input
                                            id="model_3d_usdz_url"
                                            placeholder="Cth: storage/products/models/item.usdz"
                                            bind:value={form.model_3d_usdz_url}
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card: Variasi -->
                <div
                    class="bg-white rounded-3xl border border-slate-200 p-6 shadow-soft"
                >
                    <div
                        class="flex items-center justify-between mb-4 border-b border-slate-100 pb-4"
                    >
                        <h3
                            class="font-outfit font-black text-lg text-slate-800"
                        >
                            Variasi Produk
                        </h3>
                        <Toggle
                            bind:checked={enableVariants}
                            label="Gunakan Variasi"
                            description="Warna, Ukuran, dll"
                        />
                    </div>

                    {#if enableVariants}
                        <div class="space-y-6">
                            {#each variations as v, vIndex}
                                <div
                                    class="bg-slate-50 border border-slate-200 rounded-3xl p-5 sm:p-6 space-y-5"
                                >
                                    <div
                                        class="flex justify-between items-center border-b border-slate-200 pb-3"
                                    >
                                        <div
                                            class="font-bold text-sm text-slate-800"
                                        >
                                            Tipe Variasi {vIndex + 1}
                                        </div>
                                        <div class="flex items-center gap-4">
                                            <label
                                                class="flex items-center gap-2 cursor-pointer text-xs font-bold text-slate-600 hover:text-brand-blueRoyal bg-white px-3 py-1.5 rounded-lg border border-slate-200 shadow-sm transition"
                                            >
                                                <input
                                                    type="checkbox"
                                                    bind:checked={v.use_images}
                                                    class="rounded border-slate-300 text-brand-blueRoyal focus:ring-brand-blueRoyal w-4 h-4"
                                                />
                                                <span>Tambah Gambar</span>
                                            </label>
                                            <div
                                                class="w-px h-4 bg-slate-300"
                                            ></div>
                                            <button
                                                type="button"
                                                onclick={() =>
                                                    removeVariation(vIndex)}
                                                class="flex items-center gap-1 text-rose-500 text-xs font-bold hover:text-rose-600 bg-rose-50 hover:bg-rose-100 px-3 py-1.5 rounded-lg transition"
                                                ><i class="ti ti-trash"></i> Hapus</button
                                            >
                                        </div>
                                    </div>

                                    <div>
                                        <Input
                                            bind:value={v.name}
                                            placeholder="Misal: Warna"
                                        />
                                    </div>

                                    <div
                                        class="grid grid-cols-2 md:grid-cols-3 gap-3 pt-1"
                                    >
                                        {#each v.options as opt, oIndex}
                                            <div
                                                class="flex items-center bg-white border rounded-xl overflow-hidden p-1"
                                            >
                                                {#if v.use_images}
                                                    <div
                                                        class="relative w-8 h-8 rounded-lg overflow-hidden bg-slate-100 flex-shrink-0 cursor-pointer group mr-1.5"
                                                        onclick={() =>
                                                            document
                                                                .getElementById(
                                                                    `opt-img-${vIndex}-${oIndex}`,
                                                                )
                                                                .click()}
                                                    >
                                                        {#if opt.image}
                                                            <img
                                                                src={opt.image.startsWith(
                                                                    'data:',
                                                                ) ||
                                                                opt.image.startsWith(
                                                                    'http',
                                                                ) ||
                                                                opt.image.startsWith(
                                                                    '/',
                                                                )
                                                                    ? opt.image
                                                                    : '/' +
                                                                      opt.image}
                                                                alt="var"
                                                                class="w-full h-full object-cover"
                                                            />
                                                        {:else}
                                                            <div
                                                                class="w-full h-full flex items-center justify-center text-slate-400 group-hover:text-brand-blueRoyal"
                                                            >
                                                                <i
                                                                    class="ti ti-photo text-lg"
                                                                ></i>
                                                            </div>
                                                        {/if}
                                                        <input
                                                            type="file"
                                                            id={`opt-img-${vIndex}-${oIndex}`}
                                                            class="hidden"
                                                            accept="image/*"
                                                            onchange={(e) =>
                                                                uploadOptionImage(
                                                                    e,
                                                                    vIndex,
                                                                    oIndex,
                                                                )}
                                                        />
                                                    </div>
                                                {/if}
                                                <input
                                                    type="text"
                                                    bind:value={opt.name}
                                                    oninput={generateCombinations}
                                                    class="flex-grow px-3 py-2 text-sm focus:outline-none"
                                                    placeholder="Opsi"
                                                />
                                                <button
                                                    type="button"
                                                    onclick={() =>
                                                        removeOption(
                                                            vIndex,
                                                            oIndex,
                                                        )}
                                                    class="px-2 text-slate-400 hover:text-rose-500"
                                                    ><i class="ti ti-trash"
                                                    ></i></button
                                                >
                                            </div>
                                        {/each}
                                        <div
                                            class="flex items-center border border-dashed border-brand-blueRoyal rounded-xl overflow-hidden bg-white p-1"
                                        >
                                            <input
                                                type="text"
                                                id={`new-opt-name-${vIndex}`}
                                                class="flex-grow px-3 py-2 text-sm focus:outline-none"
                                                placeholder="Tambah opsi baru... (Tekan Enter)"
                                                onkeydown={(e) => {
                                                    if (e.key === 'Enter') {
                                                        e.preventDefault();
                                                        addOption(vIndex);
                                                    }
                                                }}
                                            />
                                            <button
                                                type="button"
                                                onclick={() =>
                                                    addOption(vIndex)}
                                                class="px-4 text-brand-blueRoyal font-bold transition flex items-center justify-center"
                                                ><i class="ti ti-plus"
                                                ></i></button
                                            >
                                        </div>
                                    </div>
                                </div>
                            {/each}

                            {#if variations.length < 2}
                                <button
                                    type="button"
                                    onclick={addVariation}
                                    class="w-full py-3 bg-slate-50 border border-dashed border-slate-300 text-slate-600 font-bold rounded-xl text-sm transition hover:bg-slate-100"
                                >
                                    + Tambah Tipe Variasi
                                </button>
                            {/if}

                            {#if variations.length > 0 && variations[0].options.length > 0}
                                <div
                                    class="mt-6 p-5 border border-slate-200 rounded-3xl bg-slate-50/50 space-y-4"
                                >
                                    <div class="flex flex-col">
                                        <h4
                                            class="font-outfit font-bold text-sm text-slate-800"
                                        >
                                            Pengaturan Khusus Varian
                                        </h4>
                                        <p
                                            class="text-[11px] text-slate-400 font-medium leading-none"
                                        >
                                            Aktifkan untuk mengatur harga, stok,
                                            atau dimensi yang berbeda antar
                                            varian
                                        </p>
                                    </div>
                                    <div
                                        class="grid grid-cols-1 sm:grid-cols-3 gap-4"
                                    >
                                        <div
                                            class="p-3 bg-white border border-slate-200 rounded-2xl flex items-center shadow-sm"
                                        >
                                            <Toggle
                                                bind:checked={globalCustomPrice}
                                                label="Harga Berbeda"
                                                description="Atur harga per varian"
                                            />
                                        </div>
                                        <div
                                            class="p-3 bg-white border border-slate-200 rounded-2xl flex items-center shadow-sm"
                                        >
                                            <Toggle
                                                bind:checked={globalCustomStock}
                                                label="Stok Berbeda"
                                                description="Atur stok per varian"
                                            />
                                        </div>
                                        <div
                                            class="p-3 bg-white border border-slate-200 rounded-2xl flex items-center shadow-sm"
                                        >
                                            <Toggle
                                                bind:checked={
                                                    globalCustomWeight
                                                }
                                                label="Dimensi Berbeda"
                                                description="Atur berat per varian"
                                            />
                                        </div>
                                    </div>
                                </div>
                            {/if}

                            {#if variants.length > 0}
                                <div class="mt-6 space-y-4">
                                    {#each variants as variant (variant.id)}
                                        <div
                                            class="bg-white border {variant.active
                                                ? 'border-brand-blueRoyal ring-1 ring-brand-blueRoyal/20 shadow-sm'
                                                : 'border-slate-200 opacity-60'} rounded-2xl p-5 transition-all"
                                        >
                                            <div
                                                class="flex items-center justify-between mb-4"
                                            >
                                                <div
                                                    class="flex items-center gap-3"
                                                >
                                                    <div
                                                        class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center overflow-hidden"
                                                    >
                                                        {#if variant.image}
                                                            <img
                                                                src={variant.image.startsWith(
                                                                    'data:',
                                                                ) ||
                                                                variant.image.startsWith(
                                                                    'http',
                                                                ) ||
                                                                variant.image.startsWith(
                                                                    '/',
                                                                )
                                                                    ? variant.image
                                                                    : '/' +
                                                                      variant.image}
                                                                alt="var"
                                                                class="w-full h-full object-cover"
                                                            />
                                                        {:else}
                                                            <i
                                                                class="ti ti-box text-slate-400 text-xl"
                                                            ></i>
                                                        {/if}
                                                    </div>
                                                    <div>
                                                        <h4
                                                            class="font-bold text-slate-800 text-base"
                                                        >
                                                            {variant.name}
                                                        </h4>
                                                        <div class="mt-1">
                                                            <Input
                                                                bind:value={
                                                                    variant.sku
                                                                }
                                                                id={`sku-${variant.id}`}
                                                                placeholder="SKU Varian (Opsional)"
                                                            />
                                                        </div>
                                                    </div>
                                                </div>
                                                <div
                                                    class="flex items-center gap-6"
                                                >
                                                    <Toggle
                                                        bind:checked={
                                                            variant.active
                                                        }
                                                        label={variant.active
                                                            ? 'Varian Aktif'
                                                            : 'Varian Nonaktif'}
                                                    />

                                                    {#if variant.active && (globalCustomPrice || globalCustomStock || globalCustomWeight)}
                                                        <button
                                                            type="button"
                                                            onclick={() =>
                                                                (variant.expanded =
                                                                    !variant.expanded)}
                                                            class="w-8 h-8 rounded-lg bg-slate-50 border border-slate-200 hover:bg-slate-100 text-slate-600 flex items-center justify-center transition"
                                                            title={variant.expanded ===
                                                            true
                                                                ? 'Tutup Detail'
                                                                : 'Buka Detail'}
                                                        >
                                                            <i
                                                                class="ti {variant.expanded ===
                                                                true
                                                                    ? 'ti-chevron-up'
                                                                    : 'ti-chevron-down'} text-base"
                                                            ></i>
                                                        </button>
                                                    {/if}
                                                </div>
                                            </div>

                                            {#if variant.active && (globalCustomPrice || globalCustomStock || globalCustomWeight) && variant.expanded === true}
                                                <div
                                                    class="mt-6 pt-6 border-t border-slate-100"
                                                >
                                                    {#if globalCustomPrice}
                                                        <!-- Harga Section -->
                                                        <div
                                                            class="mb-6 p-5 border border-slate-100 rounded-2xl bg-white shadow-sm"
                                                        >
                                                            <h4
                                                                class="font-bold text-xs text-slate-500 uppercase tracking-wider mb-4"
                                                            >
                                                                Detail Harga
                                                            </h4>
                                                            <div
                                                                class="grid grid-cols-1 md:grid-cols-2 gap-4"
                                                            >
                                                                <InputCurrency
                                                                    bind:value={
                                                                        variant.price
                                                                    }
                                                                    id={`price-${variant.id}`}
                                                                    label="Harga Jual *"
                                                                    prefix="Rp"
                                                                />
                                                                <InputCurrency
                                                                    bind:value={
                                                                        variant.cost
                                                                    }
                                                                    id={`cost-${variant.id}`}
                                                                    label="Harga Modal (HPP)"
                                                                    prefix="Rp"
                                                                />
                                                            </div>

                                                            {#if globalTaxEnabled && variant.price > 0}
                                                                {#if form.tax_enabled}
                                                                    <div
                                                                        class="mt-4 pt-3 border-t border-slate-100 text-xs text-slate-600 font-medium space-y-1"
                                                                    >
                                                                        <div
                                                                            class="flex justify-between"
                                                                        >
                                                                            <span
                                                                                >Harga
                                                                                Asli
                                                                                (DPP):</span
                                                                            >
                                                                            <span
                                                                                class="font-bold text-slate-800"
                                                                                >Rp
                                                                                {Number(
                                                                                    variant.price,
                                                                                ).toLocaleString(
                                                                                    'id-ID',
                                                                                )}</span
                                                                            >
                                                                        </div>
                                                                        <div
                                                                            class="flex justify-between text-rose-500"
                                                                        >
                                                                            <span
                                                                                >Pajak
                                                                                PPN
                                                                                ({globalTaxPercentage}%):</span
                                                                            >
                                                                            <span
                                                                                >+
                                                                                Rp
                                                                                {Math.round(
                                                                                    (Number(
                                                                                        variant.price,
                                                                                    ) *
                                                                                        globalTaxPercentage) /
                                                                                        100,
                                                                                ).toLocaleString(
                                                                                    'id-ID',
                                                                                )}</span
                                                                            >
                                                                        </div>
                                                                        <div
                                                                            class="flex justify-between text-slate-800 font-black pt-1 border-t border-dashed border-slate-200"
                                                                        >
                                                                            <span
                                                                                >Total
                                                                                Pembeli
                                                                                Bayar:</span
                                                                            >
                                                                            <span
                                                                                class="text-brand-blueRoyal text-sm font-black"
                                                                                >Rp
                                                                                {Math.round(
                                                                                    Number(
                                                                                        variant.price,
                                                                                    ) *
                                                                                        (1 +
                                                                                            globalTaxPercentage /
                                                                                                100),
                                                                                ).toLocaleString(
                                                                                    'id-ID',
                                                                                )}</span
                                                                            >
                                                                        </div>
                                                                    </div>
                                                                {:else}
                                                                    <div
                                                                        class="mt-4 pt-3 border-t border-slate-100 text-xs text-slate-600 font-medium space-y-1"
                                                                    >
                                                                        <div
                                                                            class="flex justify-between"
                                                                        >
                                                                            <span
                                                                                >Total
                                                                                Pembeli
                                                                                Bayar:</span
                                                                            >
                                                                            <span
                                                                                class="font-bold text-slate-800"
                                                                                >Rp
                                                                                {Number(
                                                                                    variant.price,
                                                                                ).toLocaleString(
                                                                                    'id-ID',
                                                                                )}</span
                                                                            >
                                                                        </div>
                                                                        <div
                                                                            class="flex justify-between text-slate-500"
                                                                        >
                                                                            <span
                                                                                >Harga
                                                                                Asli
                                                                                (DPP):</span
                                                                            >
                                                                            <span
                                                                                >Rp
                                                                                {Math.round(
                                                                                    Number(
                                                                                        variant.price,
                                                                                    ) /
                                                                                        (1 +
                                                                                            globalTaxPercentage /
                                                                                                100),
                                                                                ).toLocaleString(
                                                                                    'id-ID',
                                                                                )}</span
                                                                            >
                                                                        </div>
                                                                        <div
                                                                            class="flex justify-between text-rose-500"
                                                                        >
                                                                            <span
                                                                                >Pajak
                                                                                PPN
                                                                                ({globalTaxPercentage}%
                                                                                di
                                                                                dalam):</span
                                                                            >
                                                                            <span
                                                                                >Rp
                                                                                {Math.round(
                                                                                    Number(
                                                                                        variant.price,
                                                                                    ) -
                                                                                        Number(
                                                                                            variant.price,
                                                                                        ) /
                                                                                            (1 +
                                                                                                globalTaxPercentage /
                                                                                                    100),
                                                                                ).toLocaleString(
                                                                                    'id-ID',
                                                                                )}</span
                                                                            >
                                                                        </div>
                                                                    </div>
                                                                {/if}
                                                            {/if}

                                                            <!-- Variant Wholesale Section -->
                                                            <div
                                                                class="mt-4 border-t border-slate-100 pt-4"
                                                            >
                                                                <div
                                                                    class="flex items-center justify-between mb-3"
                                                                >
                                                                    <span
                                                                        class="text-xs font-bold text-slate-500 flex items-center gap-1.5 uppercase tracking-wider"
                                                                    >
                                                                        <i
                                                                            class="ti ti-tags text-sm text-slate-400"
                                                                        ></i>
                                                                        Harga
                                                                        Grosir ({variant.name})
                                                                    </span>
                                                                    <button
                                                                        type="button"
                                                                        onclick={() => {
                                                                            if (
                                                                                !variant.tier_prices
                                                                            )
                                                                                variant.tier_prices =
                                                                                    [];
                                                                            variant.tier_prices.push(
                                                                                {
                                                                                    min_qty: 2,
                                                                                    price: '',
                                                                                },
                                                                            );
                                                                        }}
                                                                        class="px-2.5 py-1 bg-brand-blueRoyal/5 hover:bg-brand-blueRoyal/10 text-brand-blueRoyal text-[10px] font-bold rounded-lg flex items-center gap-1 transition"
                                                                    >
                                                                        <i
                                                                            class="ti ti-plus text-xs"
                                                                        ></i> Tambah
                                                                        Grosir
                                                                    </button>
                                                                </div>

                                                                {#if !variant.tier_prices || variant.tier_prices.length === 0}
                                                                    <p
                                                                        class="text-[11px] text-slate-400 italic"
                                                                    >
                                                                        Belum
                                                                        ada
                                                                        harga
                                                                        grosir
                                                                        untuk
                                                                        varian
                                                                        ini.
                                                                    </p>
                                                                {:else}
                                                                    <div
                                                                        class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3"
                                                                    >
                                                                        {#each variant.tier_prices as tier, idx (idx)}
                                                                            <div
                                                                                class="flex items-center gap-3 bg-slate-50/50 p-2.5 rounded-xl border border-slate-100 relative group"
                                                                            >
                                                                                <div
                                                                                    class="w-24 shrink-0"
                                                                                >
                                                                                    <Input
                                                                                        type="number"
                                                                                        min="2"
                                                                                        bind:value={
                                                                                            tier.min_qty
                                                                                        }
                                                                                        id={`v-tier-min-qty-${variant.id}-${idx}`}
                                                                                        label="Min. Qty"
                                                                                        placeholder="2"
                                                                                    />
                                                                                </div>
                                                                                <div
                                                                                    class="flex-grow"
                                                                                >
                                                                                    <InputCurrency
                                                                                        bind:value={
                                                                                            tier.price
                                                                                        }
                                                                                        id={`v-tier-price-${variant.id}-${idx}`}
                                                                                        label="Harga Satuan"
                                                                                        prefix="Rp"
                                                                                        placeholder="0"
                                                                                    />
                                                                                </div>
                                                                                <button
                                                                                    type="button"
                                                                                    onclick={() => {
                                                                                        variant.tier_prices =
                                                                                            variant.tier_prices.filter(
                                                                                                (
                                                                                                    _,
                                                                                                    i,
                                                                                                ) =>
                                                                                                    i !==
                                                                                                    idx,
                                                                                            );
                                                                                    }}
                                                                                    class="absolute -top-1.5 -right-1.5 w-5 h-5 bg-rose-500 text-white rounded-full flex items-center justify-center text-[10px] opacity-0 group-hover:opacity-100 transition-opacity shadow-sm hover:bg-rose-600 cursor-pointer z-10"
                                                                                    title="Hapus Grosir"
                                                                                >
                                                                                    <i
                                                                                        class="ti ti-x"

                                                                                    ></i>
                                                                                </button>
                                                                            </div>
                                                                        {/each}
                                                                    </div>
                                                                {/if}
                                                            </div>
                                                        </div>
                                                    {/if}

                                                    {#if globalCustomStock}
                                                        <!-- Stok Section -->
                                                        <div
                                                            class="mb-6 p-5 border border-slate-100 rounded-2xl bg-white shadow-sm"
                                                        >
                                                            <h4
                                                                class="font-bold text-xs text-slate-500 uppercase tracking-wider mb-4"
                                                            >
                                                                Detail Stok
                                                            </h4>
                                                            <div
                                                                class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4"
                                                            >
                                                                <Input
                                                                    bind:value={
                                                                        variant.stock
                                                                    }
                                                                    type="number"
                                                                    min="0"
                                                                    id={`stock-${variant.id}`}
                                                                    label="Stok Saat Ini"
                                                                    placeholder={form.stock !==
                                                                    ''
                                                                        ? `${form.stock} (Ikut Stok Utama)`
                                                                        : 'Ikut Stok Utama'}
                                                                    readonly={variant.is_unlimited}
                                                                />
                                                                <Input
                                                                    bind:value={
                                                                        variant.min_stock
                                                                    }
                                                                    type="number"
                                                                    min="0"
                                                                    id={`min_stock-${variant.id}`}
                                                                    label="Batas Minimum (Alert)"
                                                                    placeholder="0"
                                                                />
                                                                <Input
                                                                    bind:value={
                                                                        variant.min_purchase
                                                                    }
                                                                    type="number"
                                                                    min="1"
                                                                    id={`min_purchase-${variant.id}`}
                                                                    label="Min Pembelian"
                                                                    placeholder="1"
                                                                />
                                                            </div>
                                                            <div
                                                                class="text-[11px] text-slate-400 font-medium mb-4 flex items-center gap-1.5 bg-slate-50 p-3 rounded-xl border border-slate-200"
                                                            >
                                                                <i
                                                                    class="ti ti-info-circle text-slate-500 text-sm"
                                                                ></i>
                                                                <span
                                                                    >Jika kolom
                                                                    stok
                                                                    dikosongkan,
                                                                    varian ini
                                                                    akan
                                                                    menggunakan
                                                                    stok utama
                                                                    produk (<strong
                                                                        >{form.stock ||
                                                                            0}</strong
                                                                    >).</span
                                                                >
                                                            </div>
                                                            <div
                                                                class="p-3 border border-slate-200 rounded-xl bg-slate-50 inline-block w-full"
                                                            >
                                                                <Toggle
                                                                    bind:checked={
                                                                        variant.is_unlimited
                                                                    }
                                                                    label="Stok Tidak Terbatas"
                                                                    description="Pilih jika varian ini selalu diproduksi/tersedia"
                                                                />
                                                            </div>
                                                        </div>
                                                    {/if}

                                                    {#if globalCustomWeight}
                                                        <!-- Dimensi Section -->
                                                        <div
                                                            class="mb-4 p-5 border border-slate-100 rounded-2xl bg-white shadow-sm"
                                                        >
                                                            <h4
                                                                class="font-bold text-xs text-slate-500 uppercase tracking-wider mb-4"
                                                            >
                                                                Detail
                                                                Pengiriman &
                                                                Dimensi
                                                            </h4>
                                                            <div
                                                                class="grid grid-cols-1 md:grid-cols-4 gap-4"
                                                            >
                                                                <Input
                                                                    bind:value={
                                                                        variant.weight
                                                                    }
                                                                    type="number"
                                                                    min="0"
                                                                    id={`weight-${variant.id}`}
                                                                    label="Berat"
                                                                    prefix="Gram"
                                                                    placeholder={form.weight !==
                                                                    ''
                                                                        ? form.weight
                                                                        : 'Ikut Utama'}
                                                                />
                                                                <Input
                                                                    bind:value={
                                                                        variant.length
                                                                    }
                                                                    type="number"
                                                                    min="0"
                                                                    id={`length-${variant.id}`}
                                                                    label="Panjang"
                                                                    prefix="Cm"
                                                                    placeholder={form.length !==
                                                                    ''
                                                                        ? form.length
                                                                        : 'Ikut Utama'}
                                                                />
                                                                <Input
                                                                    bind:value={
                                                                        variant.width
                                                                    }
                                                                    type="number"
                                                                    min="0"
                                                                    id={`width-${variant.id}`}
                                                                    label="Lebar"
                                                                    prefix="Cm"
                                                                    placeholder={form.width !==
                                                                    ''
                                                                        ? form.width
                                                                        : 'Ikut Utama'}
                                                                />
                                                                <Input
                                                                    bind:value={
                                                                        variant.height
                                                                    }
                                                                    type="number"
                                                                    min="0"
                                                                    id={`height-${variant.id}`}
                                                                    label="Tinggi"
                                                                    prefix="Cm"
                                                                    placeholder={form.height !==
                                                                    ''
                                                                        ? form.height
                                                                        : 'Ikut Utama'}
                                                                />
                                                            </div>
                                                        </div>
                                                    {/if}
                                                </div>
                                            {/if}
                                        </div>
                                    {/each}
                                </div>
                            {/if}
                        </div>
                    {/if}
                </div>

                <div class="flex justify-end gap-4">
                    <button
                        type="submit"
                        disabled={form.processing}
                        class="px-8 py-3.5 bg-brand-blueRoyal text-white font-bold rounded-xl shadow-lg hover:bg-blue-800 transition"
                    >
                        {form.processing ? 'Menyimpan...' : 'Simpan Perubahan'}
                    </button>
                </div>
            </form>
        </main>
    </div>
</AdminLayout>
