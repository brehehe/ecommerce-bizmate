<script lang="ts">
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { useForm, page } from '@inertiajs/svelte';
    import { showToast } from '@/utils/toast';
    import Input from '@/components/ui/Input.svelte';
    import { slide } from 'svelte/transition';

    const primaryColor = $derived(page.props.theme?.primary_color || '#0c4cb4');

    let { settings = {}, env_keys = {} } = $props();

    function getActiveProvider() {
        if (settings.shipping_delivery_enabled === '1' || settings.shipping_delivery_enabled === true) return 'komerce';
        if (settings.biteship_enabled === '1' || settings.biteship_enabled === true) return 'biteship';
        return 'none';
    }

    let selectedProvider = $state(getActiveProvider());

    // svelte-ignore state_referenced_locally
    const form = useForm({
        shipping_delivery_key: settings.shipping_delivery_key || '',
        rajaongkir_url: settings.rajaongkir_url || '',
        rajaongkir_shipping_cost: settings.rajaongkir_shipping_cost || '',
        komerce_delivery_url: settings.komerce_delivery_url || '',
        biteship_url: settings.biteship_url || 'https://api.biteship.com/v1/',
        biteship_secret_key: settings.biteship_secret_key || '',
    });

    const providers = [
        {
            id: 'komerce',
            name: 'Komerce Delivery',
            subtitle: 'Tersedia untuk aktivasi',
            description: 'Platform logistik terintegrasi dengan RajaOngkir. Mendukung pencarian ongkir, pengiriman, dan tracking via Komerce API.',
            icon: 'ti-truck-delivery',
            color: '#2563eb',
            bgColor: '#dbeafe',
            fields: [
                { key: 'rajaongkir_url', label: 'RajaOngkir Base URL', placeholder: 'https://rajaongkir.komerce.id/api/v1/', envKey: 'rajaongkir_url' },
                { key: 'rajaongkir_shipping_cost', label: 'Shipping Cost Key', placeholder: 'Masukkan Shipping Cost Key', envKey: 'rajaongkir_shipping_cost' },
                { key: 'komerce_delivery_url', label: 'Komerce Delivery Base URL', placeholder: 'https://api-sandbox.collaborator.komerce.id/api/v1/', envKey: 'komerce_delivery_url' },
                { key: 'shipping_delivery_key', label: 'Shipping Delivery API Key', placeholder: 'Masukkan API Key', envKey: 'shipping_delivery_key' },
            ],
        },
        {
            id: 'biteship',
            name: 'Biteship',
            subtitle: 'Tersedia untuk aktivasi',
            description: 'Platform logistik modern dengan ratusan kurir partner. Mendukung multi-kurir, real-time tracking, dan COD.',
            icon: 'ti-package',
            color: '#059669',
            bgColor: '#d1fae5',
            fields: [
                { key: 'biteship_url', label: 'Biteship Base URL', placeholder: 'https://api.biteship.com/v1/', envKey: null },
                { key: 'biteship_secret_key', label: 'Biteship Secret Key / Token', placeholder: 'biteship_live.xxx atau biteship_test.xxx', envKey: null },
            ],
        },
        {
            id: 'none',
            name: 'Tanpa API Logistik',
            subtitle: 'Hanya kurir manual',
            description: 'Tidak menggunakan platform logistik API. Hanya kurir yang dikonfigurasi manual di Master Data yang tersedia.',
            icon: 'ti-box-off',
            color: '#64748b',
            bgColor: '#f1f5f9',
            fields: [],
        },
    ];

    function submit() {
        const data: Record<string, any> = {
            shipping_delivery_enabled: selectedProvider === 'komerce' ? '1' : '0',
            biteship_enabled: selectedProvider === 'biteship' ? '1' : '0',
        };
        if (selectedProvider === 'komerce') {
            if (!env_keys.rajaongkir_url) data.rajaongkir_url = form.rajaongkir_url;
            if (!env_keys.rajaongkir_shipping_cost) data.rajaongkir_shipping_cost = form.rajaongkir_shipping_cost;
            if (!env_keys.komerce_delivery_url) data.komerce_delivery_url = form.komerce_delivery_url;
            if (!env_keys.shipping_delivery_key) data.shipping_delivery_key = form.shipping_delivery_key;
        }
        if (selectedProvider === 'biteship') {
            data.biteship_url = form.biteship_url;
            data.biteship_secret_key = form.biteship_secret_key;
        }
        form.transform(() => data).post('/admin/master-data/logistic-api', {
            preserveScroll: true,
            onSuccess: () => showToast('Konfigurasi Logistik API berhasil disimpan.', 'success'),
        });
    }
</script>

<svelte:head>
    <title>Logistik API — Master Data</title>
</svelte:head>

<AdminLayout>
    <div class="flex-grow flex flex-col min-h-screen">
        <main class="flex-grow p-4 sm:p-8 w-full max-w-full mx-auto space-y-6">

        <!-- Header -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-2">
            <div>
                <div class="flex items-center gap-2.5 mb-1">
                    <div class="w-9 h-9 rounded-xl flex items-center justify-center shadow-sm shrink-0"
                        style="background: linear-gradient(135deg, {primaryColor}22, {primaryColor}44); color: {primaryColor};">
                        <i class="ti ti-api text-base"></i>
                    </div>
                    <h1 class="font-outfit font-black text-2xl text-slate-800 tracking-tight">Logistik API</h1>
                </div>
                <p class="text-xs text-slate-400 font-medium ml-11">Pilih platform logistik yang digunakan untuk hitung ongkir & pengiriman.</p>
            </div>
            <a href="/admin/master-data/couriers"
                class="flex items-center gap-1.5 px-3.5 py-2 text-xs font-bold text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition shrink-0">
                <i class="ti ti-truck-delivery text-sm"></i>
                Kelola Kurir
            </a>
        </div>

        <!-- Provider Selection -->
        <div class="bg-white border border-slate-100 shadow-sm rounded-3xl p-6 space-y-5">
            <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                <div class="p-2.5 rounded-xl" style="background-color: {primaryColor}1A; color: {primaryColor};">
                    <i class="ti ti-plug-connected text-lg"></i>
                </div>
                <div>
                    <h3 class="font-outfit font-black text-slate-800 text-base leading-none">Pilih Provider Logistik</h3>
                    <p class="text-xs text-slate-400 font-medium mt-1">Hanya satu provider yang dapat aktif pada satu waktu.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                {#each providers as provider}
                    <button
                        type="button"
                        onclick={() => (selectedProvider = provider.id)}
                        class="group relative flex flex-col items-start gap-3 p-4 rounded-2xl border-2 text-left transition-all duration-200 cursor-pointer {selectedProvider === provider.id ? 'border-blue-500 bg-blue-50/50 shadow-sm' : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50/50'}"
                    >
                        {#if selectedProvider === provider.id}
                            <div class="absolute top-3 right-3 w-5 h-5 rounded-full bg-blue-500 flex items-center justify-center">
                                <i class="ti ti-check text-white text-[10px]"></i>
                            </div>
                        {/if}
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
                            style="background-color: {provider.bgColor}; color: {provider.color};">
                            <i class="ti {provider.icon} text-lg"></i>
                        </div>
                        <div>
                            <p class="text-sm font-black text-slate-800">{provider.name}</p>
                            <p class="text-[11px] font-medium mt-0.5" style="color: {selectedProvider === provider.id ? provider.color : '#94a3b8'}">
                                {provider.subtitle}
                            </p>
                        </div>
                    </button>
                {/each}
            </div>

            {#each providers as provider}
                {#if selectedProvider === provider.id}
                    <div class="rounded-xl border border-slate-100 bg-slate-50/60 px-4 py-3" transition:slide>
                        <p class="text-xs text-slate-500 leading-relaxed">{provider.description}</p>
                    </div>
                {/if}
            {/each}
        </div>

        <!-- Configuration Fields -->
        {#each providers as provider}
            {#if selectedProvider === provider.id && provider.fields.length > 0}
                <div class="bg-white border border-slate-100 shadow-sm rounded-3xl p-6 space-y-5" transition:slide>
                    <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                        <div class="p-2.5 rounded-xl" style="background-color: {provider.bgColor}; color: {provider.color};">
                            <i class="ti ti-settings text-lg"></i>
                        </div>
                        <div>
                            <h3 class="font-outfit font-black text-slate-800 text-base leading-none">Konfigurasi {provider.name}</h3>
                            <p class="text-xs text-slate-400 font-medium mt-1">Masukkan credential API dari dashboard provider.</p>
                        </div>
                    </div>
                    <div class="space-y-4">
                        {#each provider.fields as field}
                            <div class="space-y-1.5">
                                <Input
                                    id="input-{field.key}"
                                    bind:value={form[field.key]}
                                    label={field.label}
                                    placeholder={field.placeholder}
                                    required={true}
                                />
                            </div>
                        {/each}
                    </div>
                </div>
            {/if}
        {/each}

        <!-- Save -->
        <div class="flex justify-end">
            <button
                type="button"
                onclick={submit}
                disabled={form.processing}
                class="px-6 py-3 text-white font-bold text-sm rounded-xl shadow-lg hover:shadow-xl transition flex items-center gap-2 disabled:opacity-70"
                style="background: linear-gradient(135deg, {primaryColor}, {primaryColor}cc);"
            >
                {#if form.processing}
                    <i class="ti ti-loader animate-spin text-lg"></i> Menyimpan...
                {:else}
                    <i class="ti ti-device-floppy text-lg"></i> Simpan Konfigurasi
                {/if}
            </button>
        </div>

        <!-- Info -->
        <div class="bg-blue-50/60 border border-blue-100 rounded-2xl p-4">
            <div class="flex items-start gap-3">
                <i class="ti ti-info-circle text-blue-500 text-lg mt-0.5 shrink-0"></i>
                <p class="text-xs text-blue-600 leading-relaxed">
                    Logistik API digunakan untuk menghitung ongkos kirim secara otomatis saat checkout.
                    Kurir yang muncul di checkout tetap bergantung pada data di
                    <a href="/admin/master-data/couriers" class="font-bold underline">Master Data → Kurir</a>.
                    Jika tidak ada provider aktif, hanya kurir dengan tarif manual yang tersedia.
                </p>
            </div>
        </div>

        </main>
    </div>
</AdminLayout>
