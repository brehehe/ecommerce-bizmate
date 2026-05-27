<script lang="ts">
    import StorefrontLayout from '@/components/layouts/StorefrontLayout.svelte';
    import { page, router, Link } from '@inertiajs/svelte';
    import { fade, slide } from 'svelte/transition';
    import { onMount, tick, untrack } from 'svelte';
    import { showToast } from '@/utils/toast';
    import 'leaflet/dist/leaflet.css';

    let { addresses = [] } = $props();

    // Theme configuration
    const primary = $derived(
        (page.props as any).theme?.primary_color ?? '#0c4cb4',
    );
    const secondary = $derived(
        (page.props as any).theme?.secondary_color ?? '#fa7315',
    );

    // Navigation & Step state
    // 'list' | 'search' | 'map' | 'form'
    let step = $state<'list' | 'search' | 'map' | 'form'>('list');
    let activeTab = $state<'semua' | 'teman'>('semua');
    let searchQuery = $state('');

    // Form inputs and validation
    let addressId = $state<number | null>(null); // Null if creating
    let formLabel = $state('Rumah');
    let formFullAddress = $state('');
    let formNote = $state('');
    let formReceiverName = $state('');
    let formPhoneNumber = $state('');
    let formIsPrimary = $state(false);
    let formAgree = $state(false);

    // Map & Geocoding state
    let mapLatitude = $state<number | null>(null);
    let mapLongitude = $state<number | null>(null);
    let pinpointAddress = $state('Wonokromo, Kota Surabaya, Jawa Timur');
    let searchInput = $state('');
    let searchResults = $state<any[]>([]);
    let searchLoading = $state(false);
    let locationLoading = $state(false);
    let isEditing = $state(false);

    // Leaflet variables
    let L: any;
    let map: any;
    let marker: any;
    let mapContainer: HTMLDivElement;

    // Local address list filtering
    const filteredAddresses = $derived(
        addresses.filter((addr: any) => {
            const term = searchQuery.toLowerCase();
            return (
                addr.label.toLowerCase().includes(term) ||
                addr.receiver_name.toLowerCase().includes(term) ||
                addr.full_address.toLowerCase().includes(term)
            );
        }),
    );

    // active selected address (for visual checkmark indicator)
    let selectedAddressId = $state<number | null>(null);
    $effect(() => {
        if (addresses.length > 0 && selectedAddressId === null) {
            const primaryAddr = addresses.find((a: any) => a.is_primary);
            selectedAddressId = primaryAddr ? primaryAddr.id : addresses[0].id;
        }
    });

    // Auto-initialize map when step transitions to 'map'
    $effect(() => {
        if (step === 'map') {
            untrack(() => {
                const lat = mapLatitude ?? -7.2575;
                const lon = mapLongitude ?? 112.7521;
                initLeafletMap(lat, lon);
            });
        }
    });

    // Handle back button clicks
    function goBack() {
        if (step === 'search') {
            step = 'list';
        } else if (step === 'map') {
            step = 'search';
        } else if (step === 'form') {
            if (isEditing) {
                step = 'list';
            } else {
                step = 'map';
            }
        } else if (step === 'list') {
            router.visit('/');
        }
    }

    // Geolocation logic
    function getBrowserLocation() {
        locationLoading = true;

        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                async (position) => {
                    const lat = position.coords.latitude;
                    const lon = position.coords.longitude;
                    mapLatitude = lat;
                    mapLongitude = lon;

                    await performReverseGeocode(lat, lon);
                    locationLoading = false;

                    // Transition step; $effect will auto-initialize map
                    step = 'map';
                },
                async (error) => {
                    console.warn('GPS denied or failed, trying IP fallback...', error);
                    await fallbackToIpLocation();
                },
                { enableHighAccuracy: false, timeout: 5000 },
            );
        } else {
            fallbackToIpLocation();
        }
    }

    // IP-based Geolocation fallback (avoids browser prompts/blocks)
    async function fallbackToIpLocation() {
        try {
            // Fetch approximate location using our safe backend proxy (prevents CORS)
            const response = await fetch('/api/addresses/ip-location');
            if (response.ok) {
                const data = await response.json();
                if (data.latitude && data.longitude) {
                    const lat = parseFloat(data.latitude);
                    const lon = parseFloat(data.longitude);
                    mapLatitude = lat;
                    mapLongitude = lon;

                    await performReverseGeocode(lat, lon);
                    locationLoading = false;

                    // Transition step; $effect will auto-initialize map
                    step = 'map';
                    showToast('Lokasi diperkirakan berdasarkan internet (IP). Silakan geser pin untuk ketepatan.', 'success');
                    return;
                }
            }
        } catch (err) {
            console.error('IP Geolocation failed:', err);
        }

        // Complete fallback if both GPS and IP geocoding fail
        locationLoading = false;
        showToast('Gagal mendeteksi lokasi otomatis. Silakan cari alamat atau isi manual.', 'error');
        step = 'form';
    }

    // Search query suggestion logic
    let debounceTimer: any;
    function handleSearchInput() {
        clearTimeout(debounceTimer);
        if (!searchInput.trim()) {
            searchResults = [];
            return;
        }

        searchLoading = true;
        debounceTimer = setTimeout(async () => {
            try {
                const response = await fetch(
                    `/api/addresses/search?q=${encodeURIComponent(searchInput)}`,
                );
                if (response.ok) {
                    searchResults = await response.json();
                }
            } catch (err) {
                console.error('Error fetching suggestions:', err);
            } finally {
                searchLoading = false;
            }
        }, 500);
    }

    // Reverse geocoding helper
    async function performReverseGeocode(lat: number, lon: number) {
        try {
            const response = await fetch(
                `/api/addresses/reverse?lat=${lat}&lon=${lon}`,
            );
            if (response.ok) {
                const data = await response.json();
                pinpointAddress =
                    data.display_name || `${lat.toFixed(5)}, ${lon.toFixed(5)}`;
                formFullAddress = pinpointAddress;
            } else {
                pinpointAddress = `${lat.toFixed(5)}, ${lon.toFixed(5)}`;
            }
        } catch (err) {
            pinpointAddress = `${lat.toFixed(5)}, ${lon.toFixed(5)}`;
        }
    }

    // Select autocomplete suggestion
    async function selectSuggestion(item: any) {
        const lat = parseFloat(item.lat);
        const lon = parseFloat(item.lon);
        mapLatitude = lat;
        mapLongitude = lon;
        pinpointAddress = item.display_name || item.name;
        formFullAddress = pinpointAddress;

        // Transition step; $effect will auto-initialize map
        step = 'map';
    }

    // Initialize/update Leaflet Map
    async function initLeafletMap(lat: number, lon: number) {
        // Wait for Svelte DOM updates to resolve bind:this container reference
        await tick();
        if (!mapContainer) return;

        // Load Leaflet dynamically to avoid SSR window errors
        if (!L) {
            L = await import('leaflet');
        }

        // Clean up existing map instance if any
        if (map) {
            map.remove();
        }

        // Tokopedia-style beautiful pinpoint marker icon
        // Adapts dynamically to primary theme color
        const customIcon = L.divIcon({
            className: 'custom-div-icon',
            html: `<div style="background-color: ${primary}; width: 36px; height: 36px; border-radius: 50%; border: 3px solid white; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);"><i class="ti ti-map-pin text-white text-xl"></i></div>`,
            iconSize: [36, 36],
            iconAnchor: [18, 36],
        });

        map = L.map(mapContainer, {
            zoomControl: false,
            attributionControl: false,
        }).setView([lat, lon], 16);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
        }).addTo(map);

        // Add standard zoom control at top right
        L.control
            .zoom({
                position: 'topright',
            })
            .addTo(map);

        marker = L.marker([lat, lon], {
            icon: customIcon,
            draggable: true,
        }).addTo(map);

        // Fetch info on dragend
        marker.on('dragend', async () => {
            const position = marker.getLatLng();
            mapLatitude = position.lat;
            mapLongitude = position.lng;
            await performReverseGeocode(position.lat, position.lng);
        });

        // Map click reposition
        map.on('click', async (e: any) => {
            const { lat: clickLat, lng: clickLon } = e.latlng;
            marker.setLatLng([clickLat, clickLon]);
            mapLatitude = clickLat;
            mapLongitude = clickLon;
            await performReverseGeocode(clickLat, clickLon);
        });

        // Force Leaflet to recalculate map dimensions and sizes after it renders in DOM
        setTimeout(() => {
            if (map) {
                map.invalidateSize();
            }
        }, 200);
    }

    // Re-center map using current browser location
    function recenterMap() {
        if (!navigator.geolocation) return;
        navigator.geolocation.getCurrentPosition((position) => {
            const lat = position.coords.latitude;
            const lon = position.coords.longitude;
            mapLatitude = lat;
            mapLongitude = lon;
            if (map && marker) {
                map.setView([lat, lon], 16);
                marker.setLatLng([lat, lon]);
            }
            performReverseGeocode(lat, lon);
        });
    }

    // Open add address flow
    function startAddAddress() {
        addressId = null;
        isEditing = false;
        formLabel = 'Rumah';
        formFullAddress = '';
        formNote = '';
        formReceiverName = (page.props.auth as any)?.user?.name || '';
        formPhoneNumber = '';
        formIsPrimary = addresses.length === 0; // Primary by default if first address
        formAgree = false;
        mapLatitude = null;
        mapLongitude = null;
        searchInput = '';
        searchResults = [];

        step = 'search';
    }

    // Open edit address flow
    function startEditAddress(addr: any) {
        addressId = addr.id;
        isEditing = true;
        formLabel = addr.label;
        formFullAddress = addr.full_address;
        formNote = addr.note || '';
        formReceiverName = addr.receiver_name;
        formPhoneNumber = addr.phone_number;
        formIsPrimary = addr.is_primary;
        formAgree = true; // Pre-checked for edits
        mapLatitude = addr.latitude;
        mapLongitude = addr.longitude;

        if (addr.latitude && addr.longitude) {
            pinpointAddress = addr.full_address;
            step = 'form';
        } else {
            step = 'form';
        }
    }

    // Save Address handler
    function saveAddress() {
        if (!formAgree) {
            showToast('Anda harus menyetujui Syarat & Ketentuan.', 'error');
            return;
        }

        const data = {
            label: formLabel,
            receiver_name: formReceiverName,
            phone_number: formPhoneNumber,
            full_address: formFullAddress,
            latitude: mapLatitude,
            longitude: mapLongitude,
            note: formNote,
            is_primary: formIsPrimary,
        };

        if (isEditing && addressId) {
            router.put(`/profile/addresses/${addressId}`, data, {
                onSuccess: () => {
                    showToast('Alamat berhasil diperbarui.', 'success');
                    step = 'list';
                },
                onError: (errors) => {
                    const firstError = Object.values(errors)[0] as string;
                    showToast(firstError || 'Gagal menyimpan alamat.', 'error');
                },
            });
        } else {
            router.post('/profile/addresses', data, {
                onSuccess: () => {
                    showToast('Alamat berhasil ditambahkan.', 'success');
                    step = 'list';
                },
                onError: (errors) => {
                    const firstError = Object.values(errors)[0] as string;
                    showToast(firstError || 'Gagal menyimpan alamat.', 'error');
                },
            });
        }
    }

    // Quick set primary from list view
    function selectAndSetPrimary(id: number) {
        selectedAddressId = id;
    }

    function confirmPilihAlamat() {
        if (!selectedAddressId) return;
        const selected = addresses.find((a: any) => a.id === selectedAddressId);
        if (!selected) return;

        // If it's not already primary, set it as primary
        if (!selected.is_primary) {
            router.post(
                `/profile/addresses/${selectedAddressId}/make-primary`,
                {},
                {
                    onSuccess: () => {
                        showToast(
                            'Alamat berhasil dipilih sebagai alamat utama.',
                            'success',
                        );
                    },
                },
            );
        } else {
            showToast('Alamat utama berhasil dipilih.', 'success');
        }
    }
</script>

<StorefrontLayout hideMobileFooter={true}>
    <div
        class="max-w-md mx-auto min-h-[calc(100vh-56px)] md:min-h-[calc(100vh-140px)] bg-white shadow-md flex flex-col relative"
    >
        <!-- ====== STEP 1: LIST VIEW ====== -->
        {#if step === 'list'}
            <!-- Header -->
            <div
                class="sticky top-0 z-30 bg-white border-b border-slate-100 px-4 py-4 flex items-center justify-between"
            >
                <div class="flex items-center gap-3">
                    <button
                        onclick={goBack}
                        class="p-1 hover:bg-slate-100 rounded-full transition"
                        aria-label="Kembali"
                    >
                        <i class="ti ti-arrow-left text-xl text-slate-700"></i>
                    </button>
                    <h1 class="font-outfit font-black text-lg text-slate-800">
                        Detail Alamat
                    </h1>
                </div>
                <button
                    onclick={startAddAddress}
                    class="text-sm font-bold transition hover:opacity-80"
                    style="color: {primary};"
                >
                    Tambah Alamat
                </button>
            </div>

            <!-- Search -->
            <div class="px-4 py-3 border-b border-slate-100">
                <div class="relative">
                    <i
                        class="ti ti-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"
                    ></i>
                    <input
                        type="text"
                        bind:value={searchQuery}
                        placeholder="Cari Alamat"
                        class="w-full pl-10 pr-4 py-2.5 text-sm bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:bg-white focus:ring-1 focus:ring-slate-300 transition"
                    />
                </div>
            </div>

            <!-- Tabs -->
            <div
                class="flex border-b border-slate-100 text-center font-bold text-sm"
            >
                <button
                    onclick={() => (activeTab = 'semua')}
                    class="flex-1 py-3 transition relative {activeTab ===
                    'semua'
                        ? 'text-slate-800'
                        : 'text-slate-400 hover:text-slate-600'}"
                >
                    Semua Alamat
                    {#if activeTab === 'semua'}
                        <div
                            class="absolute bottom-0 left-0 right-0 h-[3px] rounded-t"
                            style="background-color: {primary};"
                        ></div>
                    {/if}
                </button>
                <button
                    onclick={() => (activeTab = 'teman')}
                    class="flex-1 py-3 transition relative {activeTab ===
                    'teman'
                        ? 'text-slate-800'
                        : 'text-slate-400 hover:text-slate-600'}"
                >
                    Dari Teman
                    {#if activeTab === 'teman'}
                        <div
                            class="absolute bottom-0 left-0 right-0 h-[3px] rounded-t"
                            style="background-color: {primary};"
                        ></div>
                    {/if}
                </button>
            </div>

            <!-- List Body -->
            <div class="flex-grow p-4 overflow-y-auto pb-24 space-y-4">
                {#if activeTab === 'teman'}
                    <div class="py-12 text-center" transition:fade>
                        <div
                            class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-3"
                        >
                            <i class="ti ti-users text-2xl text-slate-400"></i>
                        </div>
                        <p class="text-sm font-bold text-slate-700">
                            Belum ada alamat dari teman
                        </p>
                        <p
                            class="text-xs text-slate-400 mt-1 max-w-[240px] mx-auto"
                        >
                            Alamat yang dibagikan oleh kontak Anda akan muncul
                            di sini.
                        </p>
                    </div>
                {:else if filteredAddresses.length === 0}
                    <div class="py-12 text-center" transition:fade>
                        <div
                            class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-3"
                        >
                            <i class="ti ti-map text-2xl text-slate-400"></i>
                        </div>
                        <p class="text-sm font-bold text-slate-700">
                            Tidak ada alamat ditemukan
                        </p>
                        <p class="text-xs text-slate-400 mt-1">
                            {searchQuery
                                ? 'Coba cari dengan kata kunci lain.'
                                : 'Silakan tambahkan alamat pengiriman baru Anda.'}
                        </p>
                    </div>
                {:else}
                    {#each filteredAddresses as addr (addr.id)}
                        <div
                            onclick={() => selectAndSetPrimary(addr.id)}
                            class="relative p-4 border rounded-2xl transition cursor-pointer flex flex-col gap-2.5 {selectedAddressId ===
                            addr.id
                                ? 'bg-emerald-50/20'
                                : 'hover:bg-slate-50/50 bg-white shadow-sm'}"
                            style="border-color: {selectedAddressId === addr.id
                                ? primary
                                : '#e2e8f0'};"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="text-xs font-black text-slate-700"
                                        >{addr.label}</span
                                    >
                                    {#if addr.is_primary}
                                        <span
                                            class="px-2 py-0.5 text-[9px] font-bold text-slate-500 bg-slate-100 rounded-md border border-slate-200"
                                            >Utama</span
                                        >
                                    {/if}
                                </div>
                                <button
                                    class="text-slate-400 hover:text-slate-600 p-1"
                                    aria-label="Bagikan"
                                >
                                    <i class="ti ti-share text-base"></i>
                                </button>
                            </div>

                            <div>
                                <h3
                                    class="font-outfit font-black text-base text-slate-800 leading-tight"
                                >
                                    {addr.receiver_name}
                                </h3>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    {addr.phone_number}
                                </p>
                                <p
                                    class="text-xs text-slate-600 mt-1.5 leading-relaxed"
                                >
                                    {addr.full_address}
                                </p>
                                {#if addr.note}
                                    <p
                                        class="text-[11px] text-slate-400 mt-1 italic leading-tight"
                                    >
                                        <i class="ti ti-note mr-0.5"></i>
                                        Catatan: "{addr.note}"
                                    </p>
                                {/if}
                            </div>

                            {#if addr.latitude && addr.longitude}
                                <div
                                    class="flex items-center gap-1.5 text-xs font-bold"
                                    style="color: {primary};"
                                >
                                    <i class="ti ti-map-pin"></i>
                                    <span>Sudah Pinpoint</span>
                                </div>
                            {/if}

                            <div
                                class="flex items-center justify-between pt-2 border-t border-slate-100 mt-1"
                            >
                                <button
                                    onclick={(e) => {
                                        e.stopPropagation();
                                        startEditAddress(addr);
                                    }}
                                    class="px-4 py-2 border border-slate-200 rounded-xl hover:bg-slate-50 font-bold text-xs text-slate-600 transition"
                                >
                                    Ubah Alamat
                                </button>

                                {#if selectedAddressId === addr.id}
                                    <div
                                        class="flex items-center justify-center w-6 h-6 rounded-full text-white"
                                        style="background-color: {primary};"
                                    >
                                        <i class="ti ti-check text-xs font-bold"
                                        ></i>
                                    </div>
                                {/if}
                            </div>
                        </div>
                    {/each}
                {/if}
            </div>

            <!-- Sticky Bottom Action -->
            <div
                class="absolute bottom-0 left-0 right-0 p-4 bg-white border-t border-slate-100 z-30"
            >
                <button
                    onclick={confirmPilihAlamat}
                    disabled={!selectedAddressId}
                    class="w-full py-3.5 rounded-2xl font-bold text-white shadow-lg transition flex items-center justify-center gap-2 hover:opacity-90 disabled:opacity-50"
                    style="background-color: {primary};"
                >
                    Pilih Alamat
                </button>
            </div>

            <!-- ====== STEP 2: SEARCH VIEW ====== -->
        {:else if step === 'search'}
            <!-- Header -->
            <div
                class="bg-white border-b border-slate-100 px-4 py-4 sticky top-0 z-30"
            >
                <div class="flex items-center gap-3">
                    <button
                        onclick={goBack}
                        class="p-1 hover:bg-slate-100 rounded-full transition"
                        aria-label="Kembali"
                    >
                        <i class="ti ti-arrow-left text-xl text-slate-700"></i>
                    </button>
                    <div>
                        <h1
                            class="font-outfit font-black text-lg text-slate-800"
                        >
                            Cari Alamat
                        </h1>
                        <p class="text-xs text-slate-400">
                            Di mana lokasi tujuan pengirimanmu?
                        </p>
                    </div>
                </div>
            </div>

            <div class="px-4 py-4 space-y-4 flex-grow overflow-y-auto">
                <!-- Autocomplete Input -->
                <div class="relative">
                    <i
                        class="ti ti-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"
                    ></i>
                    <input
                        type="text"
                        bind:value={searchInput}
                        oninput={handleSearchInput}
                        placeholder="Contoh: Surabaya"
                        class="w-full pl-10 pr-10 py-3 text-sm bg-slate-50 border border-slate-200 rounded-2xl focus:outline-none focus:bg-white focus:ring-1 focus:ring-slate-300 transition"
                    />
                    {#if searchInput}
                        <button
                            onclick={() => {
                                searchInput = '';
                                searchResults = [];
                            }}
                            class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                        >
                            <i class="ti ti-x text-base"></i>
                        </button>
                    {/if}
                </div>

                <!-- Geolocation trigger -->
                <button
                    onclick={getBrowserLocation}
                    disabled={locationLoading}
                    class="w-full flex items-center gap-3 p-3 bg-slate-50 hover:bg-slate-100 border border-slate-100 rounded-2xl transition text-left"
                >
                    <div
                        class="w-10 h-10 rounded-xl bg-white border border-slate-100 flex items-center justify-center shadow-sm"
                    >
                        {#if locationLoading}
                            <i
                                class="ti ti-loader animate-spin text-lg"
                                style="color: {primary};"
                            ></i>
                        {:else}
                            <i
                                class="ti ti-location text-lg"
                                style="color: {primary};"
                            ></i>
                        {/if}
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-700">
                            Gunakan Lokasi Saat Ini
                        </h3>
                        <p class="text-[11px] text-slate-400 mt-0.5">
                            Deteksi cepat koordinat GPS Anda
                        </p>
                    </div>
                </button>

                <!-- Manual entry fallback -->
                <button
                    onclick={() => {
                        step = 'form';
                        pinpointAddress = '';
                        formFullAddress = '';
                    }}
                    class="w-full text-center py-3 border border-dashed border-slate-200 rounded-2xl hover:bg-slate-50 font-bold text-xs text-slate-600 transition"
                >
                    Tidak ketemu? Isi alamat secara manual
                </button>

                <!-- Search Results Suggestions -->
                {#if searchLoading}
                    <div class="py-12 text-center" transition:fade>
                        <i
                            class="ti ti-loader animate-spin text-2xl text-slate-400 mb-2"
                        ></i>
                        <p class="text-xs text-slate-400">
                            Mencari lokasi terbaik...
                        </p>
                    </div>
                {:else if searchResults.length > 0}
                    <div class="space-y-1 pt-2" transition:slide>
                        <h4 class="text-xs font-bold text-slate-400 px-1 mb-2">
                            Hasil Pencarian
                        </h4>
                        {#each searchResults as item}
                            <button
                                onclick={() => selectSuggestion(item)}
                                class="w-full flex gap-3.5 p-3 hover:bg-slate-50 rounded-2xl text-left transition border border-transparent hover:border-slate-100"
                            >
                                <div
                                    class="w-9 h-9 rounded-xl bg-slate-100 flex items-center justify-center shrink-0 mt-0.5"
                                >
                                    <i class="ti ti-map-pin text-slate-500"></i>
                                </div>
                                <div class="overflow-hidden">
                                    <h5
                                        class="text-sm font-bold text-slate-700 truncate"
                                    >
                                        {item.address?.road ||
                                            item.address?.suburb ||
                                            item.name}
                                    </h5>
                                    <p
                                        class="text-[11px] text-slate-400 mt-0.5 truncate leading-normal"
                                    >
                                        {item.display_name}
                                    </p>
                                </div>
                            </button>
                        {/each}
                    </div>
                {/if}
            </div>

            <!-- ====== STEP 3: MAP PINPOINT ====== -->
        {:else if step === 'map'}
            <!-- Header -->
            <div
                class="bg-white border-b border-slate-100 px-4 py-4 sticky top-0 z-30 flex items-center gap-3"
            >
                <button
                    onclick={goBack}
                    class="p-1 hover:bg-slate-100 rounded-full transition"
                    aria-label="Kembali"
                >
                    <i class="ti ti-arrow-left text-xl text-slate-700"></i>
                </button>
                <h1 class="font-outfit font-black text-lg text-slate-800">
                    Tentukan Pinpoint Lokasi
                </h1>
            </div>

            <!-- Map Area -->
            <div
                class="flex-grow relative bg-slate-100 overflow-hidden min-h-[300px]"
            >
                <div bind:this={mapContainer} class="absolute inset-0 w-full h-full z-10"></div>

                <!-- Floating Overlays on Map -->
                <div class="absolute bottom-4 left-4 right-4 z-20 flex gap-2.5">
                    <button
                        onclick={recenterMap}
                        class="flex-1 bg-white/95 hover:bg-white border border-slate-100 py-2.5 px-4 rounded-xl shadow-lg flex items-center justify-center gap-2 font-bold text-xs text-slate-700 transition"
                    >
                        <i
                            class="ti ti-target text-sm"
                            style="color: {primary};"
                        ></i> Gunakan Lokasi Sa...
                    </button>
                    <button
                        onclick={() => (step = 'search')}
                        class="flex-1 bg-white/95 hover:bg-white border border-slate-100 py-2.5 px-4 rounded-xl shadow-lg flex items-center justify-center gap-2 font-bold text-xs text-slate-700 transition"
                    >
                        <i
                            class="ti ti-search text-sm"
                            style="color: {primary};"
                        ></i> Cari Ulang Alamat
                    </button>
                </div>
            </div>

            <!-- Bottom Card details -->
            <div
                class="bg-white border-t border-slate-100 p-4 z-30 flex flex-col gap-4"
            >
                <div class="flex gap-3">
                    <div
                        class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center shrink-0"
                    >
                        <i class="ti ti-map-pin text-slate-500"></i>
                    </div>
                    <div>
                        <h4
                            class="text-sm font-bold text-slate-800 flex items-center gap-1.5 leading-none"
                        >
                            Pinpoint Lokasi Aktif <i
                                class="ti ti-help text-xs text-slate-400"
                            ></i>
                        </h4>
                        <p
                            class="text-xs text-slate-500 mt-1.5 leading-relaxed line-clamp-2"
                        >
                            {pinpointAddress}
                        </p>
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <button
                        onclick={() => (step = 'form')}
                        class="w-full py-3.5 rounded-2xl font-bold text-white shadow-lg transition hover:opacity-90 flex items-center justify-center"
                        style="background-color: {primary};"
                    >
                        Pilih Lokasi & Lanjut Isi Alamat
                    </button>
                    <button
                        onclick={() => {
                            step = 'form';
                            pinpointAddress = '';
                            formFullAddress = '';
                        }}
                        class="w-full py-3 rounded-2xl font-bold text-slate-600 hover:bg-slate-50 border border-slate-200 text-xs transition flex items-center justify-center"
                    >
                        Tidak Ketemu? Isi Alamat Manual
                    </button>
                </div>
            </div>

            <!-- ====== STEP 4: FORM VIEW ====== -->
        {:else if step === 'form'}
            <!-- Header -->
            <div
                class="bg-white border-b border-slate-100 px-4 py-4 sticky top-0 z-30 flex items-center gap-3"
            >
                <button
                    onclick={goBack}
                    class="p-1 hover:bg-slate-100 rounded-full transition"
                    aria-label="Kembali"
                >
                    <i class="ti ti-arrow-left text-xl text-slate-700"></i>
                </button>
                <h1 class="font-outfit font-black text-lg text-slate-800">
                    Detail Alamat
                </h1>
            </div>

            <!-- Form Body -->
            <div class="flex-grow p-4 overflow-y-auto pb-28 space-y-5">
                <!-- Pinpoint preview -->
                {#if mapLatitude && mapLongitude}
                    <div
                        class="p-3.5 bg-slate-50 border border-slate-100 rounded-2xl flex gap-3"
                    >
                        <i
                            class="ti ti-map-pin text-slate-400 shrink-0 text-base mt-0.5"
                        ></i>
                        <div class="overflow-hidden">
                            <h4
                                class="text-[11px] font-bold text-slate-400 uppercase tracking-wider"
                            >
                                Pinpoint Lokasi
                            </h4>
                            <p
                                class="text-xs text-slate-600 mt-0.5 truncate leading-relaxed"
                            >
                                {pinpointAddress}
                            </p>
                        </div>
                    </div>
                {/if}

                <!-- Label Alamat -->
                <div>
                    <div class="flex justify-between items-center mb-1.5">
                        <label
                            for="label-alamat"
                            class="text-xs font-bold text-slate-600"
                            >Label Alamat</label
                        >
                        <span class="text-[10px] text-slate-400"
                            >{formLabel.length}/30</span
                        >
                    </div>
                    <input
                        id="label-alamat"
                        type="text"
                        bind:value={formLabel}
                        maxlength="30"
                        placeholder="Contoh: Rumah, Kantor, Kos"
                        class="w-full px-4 py-3 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:outline-none focus:ring-1 focus:ring-slate-300 transition"
                    />
                </div>

                <!-- Alamat Lengkap -->
                <div>
                    <div class="flex justify-between items-center mb-1.5">
                        <label
                            for="alamat-lengkap"
                            class="text-xs font-bold text-slate-600"
                            >Alamat Lengkap</label
                        >
                        <span class="text-[10px] text-slate-400"
                            >{formFullAddress.length}/200</span
                        >
                    </div>
                    <textarea
                        id="alamat-lengkap"
                        bind:value={formFullAddress}
                        maxlength="200"
                        rows="3"
                        placeholder="Tulis alamat jalan lengkap, blok, nomer rumah, RT/RW, kelurahan..."
                        class="w-full px-4 py-3 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:outline-none focus:ring-1 focus:ring-slate-300 transition resize-none"
                    ></textarea>
                </div>

                <!-- Catatan Untuk Kurir -->
                <div>
                    <div class="flex justify-between items-center mb-1.5">
                        <label
                            for="catatan-kurir"
                            class="text-xs font-bold text-slate-600"
                            >Catatan Untuk Kurir (Opsional)</label
                        >
                        <span class="text-[10px] text-slate-400"
                            >{formNote.length}/45</span
                        >
                    </div>
                    <input
                        id="catatan-kurir"
                        type="text"
                        bind:value={formNote}
                        maxlength="45"
                        placeholder="Contoh: Pagar hitam, samping warung bakso"
                        class="w-full px-4 py-3 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:outline-none focus:ring-1 focus:ring-slate-300 transition"
                    />
                    <p class="text-[10px] text-slate-400 mt-1 pl-1">
                        Warna rumah, patokan, pesan khusus, dll.
                    </p>
                </div>

                <!-- Nama Penerima -->
                <div>
                    <div class="flex justify-between items-center mb-1.5">
                        <label
                            for="nama-penerima"
                            class="text-xs font-bold text-slate-600"
                            >Nama Penerima</label
                        >
                        <span class="text-[10px] text-slate-400"
                            >{formReceiverName.length}/50</span
                        >
                    </div>
                    <input
                        id="nama-penerima"
                        type="text"
                        bind:value={formReceiverName}
                        maxlength="50"
                        placeholder="Nama Lengkap Penerima"
                        class="w-full px-4 py-3 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:outline-none focus:ring-1 focus:ring-slate-300 transition"
                    />
                </div>

                <!-- Nomor HP -->
                <div>
                    <div class="flex justify-between items-center mb-1.5">
                        <label
                            for="nomor-hp"
                            class="text-xs font-bold text-slate-600"
                            >Nomor HP</label
                        >
                        <span class="text-[10px] text-slate-400"
                            >{formPhoneNumber.length}/15</span
                        >
                    </div>
                    <div class="relative">
                        <input
                            id="nomor-hp"
                            type="tel"
                            bind:value={formPhoneNumber}
                            maxlength="15"
                            placeholder="Contoh: 08123456789"
                            class="w-full pl-4 pr-10 py-3 text-sm border border-slate-200 rounded-xl bg-slate-50 focus:bg-white focus:outline-none focus:ring-1 focus:ring-slate-300 transition"
                        />
                        <button
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 p-1"
                            aria-label="Kontak"
                        >
                            <i class="ti ti-address-book text-lg"></i>
                        </button>
                    </div>
                </div>

                <hr class="border-slate-100 my-1" />

                <!-- Jadikan Alamat Utama -->
                <label class="flex items-start gap-3 cursor-pointer">
                    <input
                        type="checkbox"
                        bind:checked={formIsPrimary}
                        class="mt-1 w-4 h-4 text-emerald-600 bg-slate-100 border-slate-300 rounded focus:ring-emerald-500"
                    />
                    <div>
                        <span
                            class="text-xs font-bold text-slate-700 select-none"
                            >Jadikan alamat utama</span
                        >
                        <p class="text-[10px] text-slate-400 select-none">
                            Setiap pesanan baru otomatis dikirim ke alamat ini.
                        </p>
                    </div>
                </label>

                <!-- Terms agreement -->
                <label class="flex items-start gap-3 cursor-pointer">
                    <input
                        type="checkbox"
                        bind:checked={formAgree}
                        class="mt-1 w-4 h-4 text-emerald-600 bg-slate-100 border-slate-300 rounded focus:ring-emerald-500"
                    />
                    <div
                        class="select-none leading-relaxed text-[11px] text-slate-500"
                    >
                        Saya menyetujui <span class="font-bold text-slate-700"
                            >Syarat & Ketentuan</span
                        >
                        serta
                        <span class="font-bold text-slate-700"
                            >Kebijakan Privasi</span
                        >
                        pengatur alamat di {(page.props as any).settings
                            ?.store_name || 'Toko Kami'}.
                    </div>
                </label>
            </div>

            <!-- Sticky Bottom Save -->
            <div
                class="absolute bottom-0 left-0 right-0 p-4 bg-white border-t border-slate-100 z-30"
            >
                <button
                    onclick={saveAddress}
                    disabled={!formAgree ||
                        !formLabel.trim() ||
                        !formFullAddress.trim() ||
                        !formReceiverName.trim() ||
                        !formPhoneNumber.trim()}
                    class="w-full py-3.5 rounded-2xl font-bold text-white shadow-lg transition flex items-center justify-center gap-2 hover:opacity-90 disabled:opacity-50"
                    style="background-color: {primary};"
                >
                    Simpan
                </button>
            </div>
        {/if}
    </div>
</StorefrontLayout>

<style>
    /* Prevent default map outline border */
    :global(.leaflet-container) {
        font-family: inherit;
        outline: none;
    }
</style>
