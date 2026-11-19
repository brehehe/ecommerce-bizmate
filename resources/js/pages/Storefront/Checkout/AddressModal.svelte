<script lang="ts">
    import { Link } from '@inertiajs/svelte';

    let {
        addresses,
        selectedAddressId,
        primary,
        onClose,
        onSelect,
    }: {
        addresses: any[];
        selectedAddressId: number | string | null;
        primary: string;
        onClose: () => void;
        onSelect: (addressId: number | string) => void;
    } = $props();

    function handleKeydown(event: KeyboardEvent) {
        if (event.key === 'Escape') {
            onClose();
        }
    }
</script>

<svelte:window onkeydown={handleKeydown} />

<!-- svelte-ignore a11y_no_static_element_interactions -->
<!-- svelte-ignore a11y_click_events_have_key_events -->
<div
    class="fixed inset-0 z-50 flex items-end lg:items-center justify-center"
    onclick={onClose}
>
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
    <!-- svelte-ignore a11y_click_events_have_key_events -->
    <div
        class="relative z-10 bg-white w-full lg:max-w-lg rounded-t-3xl lg:rounded-2xl max-h-[80vh] overflow-y-auto"
        role="dialog"
        aria-modal="true"
        aria-labelledby="address-modal-title"
        onclick={(event: MouseEvent) => event.stopPropagation()}
    >
        <div
            class="sticky top-0 bg-white px-4 pt-4 pb-3 border-b border-slate-100 flex items-center justify-between"
        >
            <h3 id="address-modal-title" class="font-bold text-slate-800">
                Pilih Alamat
            </h3>
            <button
                aria-label="Tutup"
                onclick={onClose}
                class="p-1 rounded-full hover:bg-slate-100"
            >
                <i class="ti ti-x text-slate-600"></i>
            </button>
        </div>
        <div class="p-4 space-y-3">
            {#each addresses as address (address.id)}
                <button
                    onclick={() => onSelect(address.id)}
                    class="w-full text-left p-3 rounded-xl border-2 transition {selectedAddressId ===
                    address.id
                        ? 'border-transparent'
                        : 'border-slate-200 hover:border-slate-300'}"
                    style={selectedAddressId === address.id
                        ? `border-color:${primary}; background:${primary}10`
                        : ''}
                >
                    <div class="flex items-center gap-2 mb-1 flex-wrap">
                        <span class="font-bold text-sm text-slate-800"
                            >{address.receiver_name}</span
                        >
                        <span class="text-xs text-slate-500"
                            >{address.phone_number}</span
                        >
                        {#if address.is_primary}
                            <span
                                class="text-[10px] font-bold px-1.5 py-0.5 rounded-full text-white"
                                style="background:{primary}">Utama</span
                            >
                        {/if}
                        <span
                            class="text-[10px] text-slate-400 border border-slate-200 px-1.5 py-0.5 rounded-full"
                            >{address.label}</span
                        >
                    </div>
                    <p class="text-xs text-slate-600 leading-relaxed">
                        {address.full_address}
                    </p>
                    {#if address.regency_name}
                        <p class="text-xs text-slate-500">
                            {address.district_name}, {address.regency_name}, {address.province_name}
                            {address.postal_code}
                        </p>
                    {/if}
                </button>
            {/each}
            <Link
                href="/profile/addresses?from=checkout"
                class="flex items-center gap-2 text-sm font-semibold px-3 py-2.5 rounded-xl border-2 border-dashed border-slate-200 hover:border-slate-300 text-slate-500 hover:text-slate-700 transition"
            >
                <i class="ti ti-plus text-lg"></i>
                Tambah Alamat Baru
            </Link>
        </div>
    </div>
</div>
