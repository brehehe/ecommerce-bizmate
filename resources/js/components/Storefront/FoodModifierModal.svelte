<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { fade, fly } from 'svelte/transition';
    import { cubicOut } from 'svelte/easing';
    import { showToast } from '@/utils/toast';

    let {
        product = null as any,
        selectedVariantId = null as string | null,
        show = false,
        onClose = () => {},
        onAdded = () => {},
        primary = '#0c4cb4',
        secondary = '#fa7315',
    } = $props();

    let customSelections = $state<Record<string, any>>({});
    let chefNote = $state('');
    let qty = $state(1);
    let isSubmitting = $state(false);
    let imgLoadFailed = $state(false);

    const customModifiers = $derived<any[]>(
        product?.menu_modifiers || product?.menuModifiers || []
    );
    const hasCustomModifiers = $derived(customModifiers.length > 0);

    $effect(() => {
        if (show && product) {
            imgLoadFailed = false;
            chefNote = '';
            qty = 1;
            isSubmitting = false;

            const initialCustom: Record<string, any> = {};
            if (customModifiers.length > 0) {
                for (const mod of customModifiers) {
                    if (mod.is_multiple) {
                        initialCustom[mod.id] = (mod.items || [])
                            .filter((it: any) => it.is_default && it.is_available !== false)
                            .map((it: any) => it.id);
                    } else {
                        const def = (mod.items || []).find((it: any) => it.is_default && it.is_available !== false) 
                            || (mod.is_required ? (mod.items || []).find((it: any) => it.is_available !== false) : null);
                        initialCustom[mod.id] = def ? def.id : '';
                    }
                }
            }
            customSelections = initialCustom;
        }
    });

    const basePrice = $derived.by(() => {
        if (!product) return 0;
        if (product.is_promo && product.promo_price) {
            return Number(product.promo_price);
        }
        return Number(product.product_price?.price ?? product.price ?? 0);
    });

    const modifierAdjustment = $derived.by(() => {
        let adj = 0;
        if (hasCustomModifiers) {
            for (const mod of customModifiers) {
                const sel = customSelections[mod.id];
                if (Array.isArray(sel)) {
                    for (const itemId of sel) {
                        const item = (mod.items || []).find((it: any) => it.id === itemId);
                        if (item) adj += Number(item.additional_price || 0);
                    }
                } else if (sel) {
                    const item = (mod.items || []).find((it: any) => it.id === sel);
                    if (item) adj += Number(item.additional_price || 0);
                }
            }
        }
        return adj;
    });

    const unitPrice = $derived(Math.max(0, basePrice + modifierAdjustment));
    const totalPrice = $derived(unitPrice * qty);
    const productThumb = $derived(getProductThumb(product));

    function formatRupiah(num: number): string {
        return 'Rp ' + Number(num || 0).toLocaleString('id-ID');
    }

    function getProductThumb(prod: any): string | null {
        let p: string | null = null;
        if (prod?.images?.length > 0) {
            p = prod.images[0].url || prod.images[0].path;
        } else if (prod?.image) {
            p = prod.image;
        }
        if (!p || typeof p !== 'string') return null;
        if (p.startsWith('http://') || p.startsWith('https://')) return p;
        if (p.startsWith('/')) return p;
        if (p.startsWith('storage/')) return '/' + p;
        return '/storage/' + p;
    }

    function toggleCustomMultiple(groupId: string, itemId: string, maxSelectable: number) {
        const current = (customSelections[groupId] as string[]) || [];
        if (current.includes(itemId)) {
            customSelections[groupId] = current.filter((id) => id !== itemId);
        } else {
            if (current.length < maxSelectable) {
                customSelections[groupId] = [...current, itemId];
            } else {
                showToast(`Maksimal memilih ${maxSelectable} pilihan.`, 'error');
            }
        }
    }

    function buildSelectedModifiers(): any[] {
        const result: any[] = [];
        if (hasCustomModifiers) {
            for (const mod of customModifiers) {
                const sel = customSelections[mod.id];
                if (Array.isArray(sel) && sel.length > 0) {
                    for (const itemId of sel) {
                        const it = (mod.items || []).find((x: any) => x.id === itemId);
                        if (it) {
                            result.push({
                                modifier_id: mod.id,
                                item_id: it.id,
                                group_name: mod.group_name,
                                name: it.name,
                                additional_price: Number(it.additional_price || 0),
                            });
                        }
                    }
                } else if (typeof sel === 'string' && sel) {
                    const it = (mod.items || []).find((x: any) => x.id === sel);
                    if (it) {
                        result.push({
                            modifier_id: mod.id,
                            item_id: it.id,
                            group_name: mod.group_name,
                            name: it.name,
                            additional_price: Number(it.additional_price || 0),
                        });
                    }
                }
            }
        }
        return result;
    }

    function buildFormattedNote(): string {
        const parts: string[] = [];

        if (hasCustomModifiers) {
            for (const mod of customModifiers) {
                const sel = customSelections[mod.id];
                if (Array.isArray(sel) && sel.length > 0) {
                    const names = sel
                        .map((id) => {
                            const it = (mod.items || []).find((x: any) => x.id === id);
                            if (!it) return null;
                            const extra = Number(it.additional_price || 0);
                            return extra > 0 ? `${it.name} (+${formatRupiah(extra)})` : it.name;
                        })
                        .filter(Boolean);
                    if (names.length > 0) parts.push(`${mod.group_name}: ${names.join(', ')}`);
                } else if (typeof sel === 'string' && sel) {
                    const it = (mod.items || []).find((x: any) => x.id === sel);
                    if (it) {
                        const extra = Number(it.additional_price || 0);
                        const label = extra > 0 ? `${it.name} (+${formatRupiah(extra)})` : it.name;
                        parts.push(`${mod.group_name}: ${label}`);
                    }
                }
            }
        }

        if (chefNote.trim()) {
            parts.push(`Catatan: ${chefNote.trim()}`);
        }

        return parts.join(' | ');
    }

    function submitOrder() {
        if (!product || isSubmitting) return;
        isSubmitting = true;

        const note = buildFormattedNote();
        const selectedMods = buildSelectedModifiers();

        router.post(
            '/cart',
            {
                product_id: product.id,
                product_variant_id: selectedVariantId ?? null,
                quantity: qty,
                note: note,
                selected_modifiers: selectedMods,
            },
            {
                preserveScroll: true,
                onSuccess: (pageResp: any) => {
                    onAdded();
                    onClose();
                    const newCartCount = pageResp?.props?.cartCount;
                    window.dispatchEvent(
                        new CustomEvent('cart-updated', {
                            detail:
                                newCartCount !== undefined
                                    ? { cartCount: newCartCount }
                                    : { delta: qty },
                        }),
                    );
                    const flashSuccess = pageResp.props?.flash?.success;
                    showToast(flashSuccess || 'Menu berhasil ditambahkan ke nampan!', 'success');
                },
                onError: (errs) => {
                    const msg = Object.values(errs)[0] || 'Gagal menambahkan pesanan.';
                    showToast(msg as string, 'error');
                },
                onFinish: () => {
                    isSubmitting = false;
                },
            },
        );
    }
</script>

{#if show && product}
    <div
        class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-black/60 backdrop-blur-sm"
        transition:fade={{ duration: 150 }}
        onclick={onClose}
        role="button"
        tabindex="0"
        onkeydown={(e) => e.key === 'Escape' && onClose()}
    >
        <div
            class="bg-white dark:bg-slate-900 rounded-t-3xl sm:rounded-3xl max-w-lg w-full max-h-[90vh] flex flex-col shadow-2xl border border-slate-100 dark:border-slate-800 overflow-hidden"
            transition:fly={{ y: 50, duration: 200, easing: cubicOut }}
            onclick={(e) => e.stopPropagation()}
            role="dialog"
            aria-modal="true"
            tabindex="-1"
        >
            <!-- Header -->
            <div class="p-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between shrink-0 bg-slate-50/50 dark:bg-slate-800/40">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center text-lg">
                        <i class="ti ti-tools-kitchen-2"></i>
                    </div>
                    <div>
                        <h3 class="font-black text-slate-900 dark:text-white text-base leading-tight">
                            Kustomisasi Menu
                        </h3>
                        <p class="text-xs text-slate-400">
                            {#if hasCustomModifiers}
                                Pilih opsi dan kustomisasi menu sesuai selera Anda
                            {:else}
                                Atur jumlah pesanan dan catatan koki dapur
                            {/if}
                        </p>
                    </div>
                </div>

                <button
                    type="button"
                    onclick={onClose}
                    class="p-2 rounded-full text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                >
                    <i class="ti ti-x text-lg"></i>
                </button>
            </div>

            <!-- Scrollable Content -->
            <div class="p-5 space-y-5 overflow-y-auto flex-1">
                <!-- Product Snippet -->
                <div class="flex items-center gap-3 p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200/60 dark:border-slate-700/60">
                    {#if productThumb && !imgLoadFailed}
                        <img
                            src={productThumb}
                            alt={product.name}
                            onerror={() => (imgLoadFailed = true)}
                            class="w-14 h-14 rounded-xl object-cover shrink-0 border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800"
                        />
                    {:else}
                        <div class="w-14 h-14 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200/70 dark:border-amber-800/50 flex items-center justify-center shrink-0 text-amber-600 dark:text-amber-400">
                            <i class="ti ti-tools-kitchen-2 text-xl"></i>
                        </div>
                    {/if}
                    <div class="flex-1 min-w-0">
                        <h4 class="font-bold text-sm text-slate-900 dark:text-white truncate">
                            {product.name}
                        </h4>
                        <p class="text-xs font-black mt-0.5" style="color: {primary};">
                            {formatRupiah(basePrice)}
                        </p>
                    </div>
                </div>

                {#if hasCustomModifiers}
                    <!-- Dynamic Database Menu Modifiers -->
                    {#each customModifiers as mod, idx}
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-1.5">
                                    <i class="ti ti-tools-kitchen text-amber-500"></i>
                                    {idx + 1}. {mod.group_name}
                                </span>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {mod.is_required ? 'text-rose-500 bg-rose-50 dark:bg-rose-950/50' : 'text-slate-500 bg-slate-100 dark:bg-slate-800'}">
                                    {mod.is_required ? 'Wajib Dipilih' : 'Opsional'}
                                </span>
                            </div>

                            <div class="grid grid-cols-1 gap-2">
                                {#each (mod.items || []) as item}
                                    <label
                                        class="flex items-center justify-between p-3 rounded-2xl border cursor-pointer transition text-xs {
                                            (mod.is_multiple ? (customSelections[mod.id] || []).includes(item.id) : customSelections[mod.id] === item.id)
                                                ? 'border-amber-500 bg-amber-50/50 dark:bg-amber-950/20 text-slate-900 dark:text-white font-bold ring-1 ring-amber-500'
                                                : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 text-slate-700 dark:text-slate-300'
                                        }"
                                    >
                                        <div class="flex items-center gap-2.5">
                                            {#if mod.is_multiple}
                                                <input
                                                    type="checkbox"
                                                    checked={(customSelections[mod.id] || []).includes(item.id)}
                                                    onchange={() => toggleCustomMultiple(mod.id, item.id, mod.max_selectable || 5)}
                                                    class="rounded text-amber-600 focus:ring-amber-500"
                                                />
                                            {:else}
                                                <input
                                                    type="radio"
                                                    name={`custom_group_${mod.id}`}
                                                    value={item.id}
                                                    checked={customSelections[mod.id] === item.id}
                                                    onchange={() => (customSelections[mod.id] = item.id)}
                                                    class="text-amber-600 focus:ring-amber-500"
                                                />
                                            {/if}
                                            <span>{item.name}</span>
                                        </div>
                                        <span class="font-semibold {item.additional_price > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-400'}">
                                            {item.additional_price > 0 ? `+${formatRupiah(item.additional_price)}` : '+Rp 0'}
                                        </span>
                                    </label>
                                {/each}
                            </div>
                        </div>
                    {/each}
                {:else}
                    <!-- Standar Menu (No Modifiers) -->
                    <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-700/60 flex items-start gap-3 text-xs text-slate-500 dark:text-slate-400">
                        <i class="ti ti-info-circle text-base text-slate-400 shrink-0 mt-0.5"></i>
                        <span>Menu ini disajikan standar tanpa pilihan topping tambahan. Tentukan jumlah pesanan dan tuliskan catatan khusus koki di bawah jika diperlukan.</span>
                    </div>
                {/if}

                <!-- Catatan Koki Dapur -->
                <div class="space-y-1.5">
                    <label for="chef-note-input" class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-1.5">
                        <i class="ti ti-pencil text-slate-400"></i>
                        Catatan Khusus Koki Dapur
                    </label>
                    <textarea
                        id="chef-note-input"
                        bind:value={chefNote}
                        placeholder="Contoh: Sambal dipisah di wadah kecil ya koki, jangan pakai daun bawang..."
                        rows="2"
                        class="w-full p-3 text-xs rounded-2xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:ring-2 resize-none"
                        style="--tw-ring-color: {primary};"
                    ></textarea>
                </div>
            </div>

            <!-- Footer Action Bar -->
            <div class="p-4 sm:p-5 border-t border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/60 shrink-0 space-y-3">
                <div class="flex items-center justify-between">
                    <!-- Qty Selector -->
                    <div class="flex items-center gap-2 bg-white dark:bg-slate-900 rounded-2xl p-1 border border-slate-200 dark:border-slate-700">
                        <button
                            type="button"
                            onclick={() => (qty = Math.max(1, qty - 1))}
                            disabled={qty <= 1}
                            class="w-8 h-8 rounded-xl flex items-center justify-center text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 disabled:opacity-40"
                        >
                            <i class="ti ti-minus text-sm"></i>
                        </button>
                        <span class="w-8 text-center font-bold text-sm text-slate-900 dark:text-white">
                            {qty}
                        </span>
                        <button
                            type="button"
                            onclick={() => (qty = qty + 1)}
                            class="w-8 h-8 rounded-xl flex items-center justify-center text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"
                        >
                            <i class="ti ti-plus text-sm"></i>
                        </button>
                    </div>

                    <!-- Total Price -->
                    <div class="text-right">
                        <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block">Total Harga</span>
                        <span class="text-lg font-black text-slate-900 dark:text-white" style="color: {primary};">
                            {formatRupiah(totalPrice)}
                        </span>
                    </div>
                </div>

                <button
                    type="button"
                    onclick={submitOrder}
                    disabled={isSubmitting}
                    class="w-full py-3.5 px-6 rounded-2xl font-bold text-sm text-white shadow-md hover:shadow-lg transition-all active:scale-[0.99] flex items-center justify-center gap-2 disabled:opacity-50 cursor-pointer"
                    style="background-color: {primary};"
                >
                    {#if isSubmitting}
                        <i class="ti ti-loader-2 animate-spin text-lg"></i>
                        <span>Menambahkan...</span>
                    {:else}
                        <i class="ti ti-shopping-bag-plus text-lg"></i>
                        <span>Masukkan ke Nampan Belanja</span>
                    {/if}
                </button>
            </div>
        </div>
    </div>
{/if}

