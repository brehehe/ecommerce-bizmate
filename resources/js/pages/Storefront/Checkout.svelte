<script lang="ts">
    import { page, router, Link } from '@inertiajs/svelte';
    import { untrack, onDestroy } from 'svelte';
    import { fade } from 'svelte/transition';
    import StorefrontLayout from '@/components/layouts/StorefrontLayout.svelte';
    import { showToast } from '@/utils/toast';
    import AddressModal from './Checkout/AddressModal.svelte';
    import CheckoutHeader from './Checkout/CheckoutHeader.svelte';
    import GuestCustomerSection from './Checkout/GuestCustomerSection.svelte';
    import LoyaltyPointsSection from './Checkout/LoyaltyPointsSection.svelte';
    import MobileCheckoutBar from './Checkout/MobileCheckoutBar.svelte';
    import OrderNotesSection from './Checkout/OrderNotesSection.svelte';
    import OrderSummarySection from './Checkout/OrderSummarySection.svelte';
    import PaymentMethodsSection from './Checkout/PaymentMethodsSection.svelte';
    import ProductListSection from './Checkout/ProductListSection.svelte';
    import PromoExpiryNotice from './Checkout/PromoExpiryNotice.svelte';
    import ScannerModal from './Checkout/ScannerModal.svelte';
    import VoucherModal from './Checkout/VoucherModal.svelte';
    import VoucherSection from './Checkout/VoucherSection.svelte';

    // QR/Barcode Scanner States for Checkout
    let showScanModal = $state(false);
    let scanError = $state('');
    let isScanning = $state(false);
    let html5QrScanner: any = null;
    let availableCameras = $state<any[]>([]);
    let selectedCameraId = $state('');

    async function startScanning() {
        scanError = '';
        try {
            const { Html5Qrcode } = await import('html5-qrcode');
            // Request permissions and get cameras
            const devices = await Html5Qrcode.getCameras();
            if (devices && devices.length > 0) {
                availableCameras = devices;
                if (!selectedCameraId) {
                    // Prefer back camera if available
                    const backCam = devices.find(
                        (d: any) =>
                            d.label.toLowerCase().includes('back') ||
                            d.label.toLowerCase().includes('rear'),
                    );
                    selectedCameraId = backCam ? backCam.id : devices[0].id;
                }

                // Initialize scanner
                if (!html5QrScanner) {
                    html5QrScanner = new Html5Qrcode('checkout-scanner-reader');
                }

                isScanning = true;
                await html5QrScanner.start(
                    selectedCameraId,
                    {
                        fps: 10,
                        qrbox: (width: number, height: number) => {
                            const min = Math.min(width, height);
                            return { width: min * 0.7, height: min * 0.7 };
                        },
                        aspectRatio: 1.0,
                    },
                    (decodedText: string) => {
                        handleScanSuccess(decodedText);
                    },
                    (errorMessage: string) => {
                        // Verbose debug scanning errors can be ignored
                    },
                );
            } else {
                scanError =
                    'Kamera tidak ditemukan. Pastikan izin kamera telah diberikan.';
            }
        } catch (err: any) {
            scanError = 'Gagal mengakses kamera: ' + (err.message || err);
            isScanning = false;
        }
    }

    async function stopScanning() {
        if (html5QrScanner && isScanning) {
            try {
                await html5QrScanner.stop();
            } catch (e) {
                console.error('Error stopping scanner:', e);
            }
            isScanning = false;
        }
    }

    async function changeCamera(cameraId: string) {
        selectedCameraId = cameraId;
        if (isScanning) {
            await stopScanning();
            await startScanning();
        }
    }

    function openScanModal() {
        showScanModal = true;
        scanError = '';
        isScanning = false;
        // Start scanning automatically on modal open
        setTimeout(() => {
            startScanning();
        }, 100);
    }

    async function closeScanModal() {
        await stopScanning();
        showScanModal = false;
    }

    async function handleScanSuccess(decodedText: string) {
        if (!decodedText) return;
        await stopScanning();
        // Play beep sound
        try {
            const ctx = new (
                window.AudioContext || (window as any).webkitAudioContext
            )();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(1000, ctx.currentTime);
            gain.gain.setValueAtTime(0, ctx.currentTime);
            gain.gain.linearRampToValueAtTime(0.3, ctx.currentTime + 0.05);
            gain.gain.exponentialRampToValueAtTime(
                0.0001,
                ctx.currentTime + 0.25,
            );
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.3);
        } catch (e) {
            console.error('AudioContext error:', e);
        }

        // Put decoded text in input field
        manualPromoCode = decodedText.trim().toUpperCase();
        showScanModal = false;
        showToast('Voucher berhasil dipindai!', 'success');
    }

    onDestroy(() => {
        stopScanning();
    });

    let {
        cartItems = [],
        addresses = [],
        paymentMethods = [],
        midtransEnabledMethods = [],
        midtransAdminFee = 0,
        activePromotions = [],
        vouchers = [],
        storeName = '',
        storeLogo = '',
        storeOriginCity = null,
        appFee = 0,
        additionalCosts = [],
        appliedVoucher: initialAppliedVoucher = null,
        couriers = [],
        isNewUser = false,
        biteshipEnabled = false,
        isGuestCheckout = false,
        isFoodPick = false,
        foodpick = null,
    } = $props();

    const storeSettings = $derived((page.props as any).settings || {});
    const isFoodPickMode = $derived(
        Boolean(
            isFoodPick ||
            storeSettings.is_foodpick ||
            (page.props as any).app_config?.is_foodpick,
        ),
    );
    const foodpickConfig = $derived(
        foodpick ||
            storeSettings.foodpick ||
            (page.props as any).app_config?.foodpick ||
            {},
    );

    const isGuest = $derived(
        isGuestCheckout || !(page.props as any).auth?.user,
    );

    // Guest Customer Information States
    let guestName = $state('');
    let guestEmail = $state('');
    let guestPhone = $state('');

    // Guest Address States
    let guestReceiverName = $state('');
    let guestReceiverPhone = $state('');
    let guestSameAsCustomer = $state(true);
    let guestFullAddress = $state('');
    let guestProvinceId = $state('');
    let guestProvinceName = $state('');
    let guestRegencyId = $state('');
    let guestRegencyName = $state('');
    let guestDistrictId = $state('');
    let guestDistrictName = $state('');
    let guestPostalCode = $state('');
    let guestVillageId = $state('');
    let guestVillageName = $state('');
    let guestBiteshipAreaId = $state('');
    let guestRajaongkirDestId = $state('');
    let guestLat = $state<number | null>(null);
    let guestLng = $state<number | null>(null);

    // Guest destination search states
    let destinationQuery = $state('');
    let destinationResults = $state<any[]>([]);
    let isSearchingDestination = $state(false);
    let showDestinationDropdown = $state(false);
    let showManualRegionalFields = $state(false);
    let destinationSearchTimeout: any = null;

    function handleDestinationSearch(val: string) {
        destinationQuery = val;
        clearTimeout(destinationSearchTimeout);
        if (!val || val.trim().length < 3) {
            destinationResults = [];
            showDestinationDropdown = false;
            return;
        }
        destinationSearchTimeout = setTimeout(async () => {
            isSearchingDestination = true;
            try {
                const resp = await fetch(
                    `/checkout/komerce/search-destination?keyword=${encodeURIComponent(val.trim())}`,
                );
                if (resp.ok) {
                    const json = await resp.json();
                    destinationResults = json.data ?? [];
                    showDestinationDropdown = destinationResults.length > 0;
                } else {
                    const bResp = await fetch(
                        `/api/addresses/biteship-areas?q=${encodeURIComponent(val.trim())}`,
                    );
                    if (bResp.ok) {
                        const bJson = await bResp.json();
                        destinationResults = (bJson.areas ?? []).map(
                            (a: any) => ({
                                id: a.id,
                                label: a.name,
                                city_name:
                                    a.administrative_division_level_2_name,
                                district_name:
                                    a.administrative_division_level_3_name,
                                postal_code: a.postal_code,
                                latitude: a.latitude ?? null,
                                longitude: a.longitude ?? null,
                            }),
                        );
                        showDestinationDropdown = destinationResults.length > 0;
                    }
                }
            } catch (err) {
                console.error('Error searching destination:', err);
            } finally {
                isSearchingDestination = false;
            }
        }, 350);
    }

    let isDetectingGuestGps = $state(false);
    function detectGuestGps() {
        if (!navigator.geolocation) {
            showToast('Geolocation tidak didukung browser ini.', 'error');
            return;
        }
        isDetectingGuestGps = true;
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                guestLat = Number(pos.coords.latitude.toFixed(6));
                guestLng = Number(pos.coords.longitude.toFixed(6));
                isDetectingGuestGps = false;
                showToast(
                    'Titik koordinat GPS berhasil terdeteksi!',
                    'success',
                );
                if (selectedCourier) {
                    fetchShipping();
                }
            },
            () => {
                isDetectingGuestGps = false;
                showToast('Gagal mendeteksi lokasi GPS.', 'error');
            },
            { enableHighAccuracy: true, timeout: 10000 },
        );
    }

    function selectDestination(item: any) {
        destinationQuery =
            item.label ||
            `${item.district_name || ''}, ${item.city_name || ''} ${item.postal_code || item.zip_code || ''}`;
        guestRegencyName = item.city_name || '';
        guestDistrictName = item.district_name || '';
        guestPostalCode = String(item.postal_code || item.zip_code || '');
        guestBiteshipAreaId = item.id != null ? String(item.id) : '';
        guestRajaongkirDestId = item.id != null ? String(item.id) : '';
        if (item.latitude && item.longitude) {
            guestLat = Number(item.latitude);
            guestLng = Number(item.longitude);
        }
        showDestinationDropdown = false;
    }

    function clearDestination() {
        destinationQuery = '';
        guestRegencyName = '';
        guestDistrictName = '';
        guestPostalCode = '';
        guestBiteshipAreaId = '';
        guestRajaongkirDestId = '';
        destinationResults = [];
        showDestinationDropdown = false;
    }

    const isGuestCustomerValid = $derived(
        !isGuest ||
            (guestName.trim().length > 0 &&
                guestPhone.trim().length >= 8 &&
                guestEmail.trim().includes('@')),
    );

    const isGuestAddressFilled = $derived(
        !isGuest ||
            (guestFullAddress.trim().length > 0 &&
                (guestRegencyName.trim().length > 0 ||
                    guestBiteshipAreaId.trim().length > 0) &&
                guestPostalCode.trim().length > 0),
    );

    const primary = $derived(
        (page.props as any).theme?.primary_color ?? '#ee4d2d',
    );
    const secondary = $derived(
        (page.props as any).theme?.secondary_color ?? '#fa7315',
    );

    const userIsNew = $derived(
        isNewUser || (page.props as any).isNewUser || false,
    );

    // Store Open Logic
    const isStoreOpen = $derived.by(() => {
        if (storeSettings.holiday_mode) return false;
        if (storeSettings.always_open) return true;
        if (!storeSettings.operational_hours) return true;

        const now = new Date();
        const days = [
            'sunday',
            'monday',
            'tuesday',
            'wednesday',
            'thursday',
            'friday',
            'saturday',
        ];
        const currentDay = days[now.getDay()];
        const currentHours = storeSettings.operational_hours[currentDay];

        if (!currentHours || !currentHours.active) return false;

        const currentTime = now.getHours() * 60 + now.getMinutes();

        const openParts = currentHours.open.split(':');
        const openTime = parseInt(openParts[0]) * 60 + parseInt(openParts[1]);

        const closeParts = currentHours.close.split(':');
        const closeTime =
            parseInt(closeParts[0]) * 60 + parseInt(closeParts[1]);

        return currentTime >= openTime && currentTime <= closeTime;
    });

    /** Driven by CHECKOUT_LOCKED + CHECKOUT_LOCKED_MESSAGE in .env */
    const isCheckoutLocked = $derived(
        (storeSettings.checkout_locked as boolean) === true,
    );
    const checkoutLockedMessage = $derived(
        (storeSettings.checkout_locked_message as string) ||
            'Checkout sedang dinonaktifkan sementara. Silakan coba lagi nanti.',
    );

    const visibleVouchers = $derived.by(() => {
        const base = (vouchers || []).filter(
            (v: any) =>
                (v.type === 'voucher_gratis_ongkir' ||
                    v.type === 'voucher_belanja') &&
                v.settings?.show_publicly !== false &&
                v.settings?.show_publicly !== 'false',
        );
        const extra: any[] = [];
        if (appliedVoucher && Array.isArray(appliedVoucher.promotions)) {
            for (const p of appliedVoucher.promotions) {
                if (!base.some((b) => b.id === p.id)) {
                    extra.push(p);
                }
            }
        } else if (appliedVoucher && appliedVoucher.promotion) {
            const p = appliedVoucher.promotion;
            if (!base.some((b) => b.id === p.id)) {
                extra.push(p);
            }
        }
        return [...base, ...extra];
    });

    let expandedTerms = $state<Record<number, boolean>>({});

    function toggleTerms(id: number) {
        expandedTerms[id] = !expandedTerms[id];
    }

    const shippingVouchers = $derived(
        visibleVouchers.filter((v: any) => v.type === 'voucher_gratis_ongkir'),
    );

    const discountVouchers = $derived(
        visibleVouchers.filter((v: any) => v.type !== 'voucher_gratis_ongkir'),
    );

    const activeAppliedVouchers = $derived.by(() => {
        if (!appliedVoucher) return [];
        let list: any[] = [];
        if (Array.isArray(appliedVoucher.promotions)) {
            list = appliedVoucher.promotions;
        } else if (appliedVoucher.promotion) {
            list = [appliedVoucher.promotion];
        }
        return list;
    });

    // Selected address
    // svelte-ignore state_referenced_locally
    let selectedAddressId = $state(
        addresses.find((a: any) => a.is_primary)?.id ??
            addresses[0]?.id ??
            null,
    );
    $effect(() => {
        if (!selectedAddressId && addresses && addresses.length > 0) {
            selectedAddressId =
                addresses.find((a: any) => a.is_primary)?.id ??
                addresses[0]?.id;
        }
    });
    let showAddressModal = $state(false);

    const guestAddressObject = $derived.by(() => {
        if (!isGuest) return null;
        const finalReceiver = guestSameAsCustomer
            ? guestName
            : guestReceiverName || guestName;
        const finalPhone = guestSameAsCustomer
            ? guestPhone
            : guestReceiverPhone || guestPhone;
        return {
            id: 'guest-address',
            receiver_name: finalReceiver || 'Pelanggan',
            phone_number: finalPhone || '',
            full_address: guestFullAddress,
            province_id: guestProvinceId != null ? String(guestProvinceId) : '',
            province_name: guestProvinceName,
            regency_id:
                guestRegencyId != null
                    ? String(guestRegencyId)
                    : guestBiteshipAreaId
                      ? String(guestBiteshipAreaId)
                      : '',
            city_id:
                guestRegencyId != null
                    ? String(guestRegencyId)
                    : guestBiteshipAreaId
                      ? String(guestBiteshipAreaId)
                      : '',
            regency_name: guestRegencyName,
            district_id: guestDistrictId != null ? String(guestDistrictId) : '',
            district_name: guestDistrictName,
            postal_code: guestPostalCode ? String(guestPostalCode) : '',
            biteship_area_id: guestBiteshipAreaId
                ? String(guestBiteshipAreaId)
                : '',
            rajaongkir_destination_id: guestRajaongkirDestId
                ? String(guestRajaongkirDestId)
                : '',
            latitude: guestLat,
            longitude: guestLng,
            is_primary: true,
        };
    });

    const selectedAddress = $derived(
        isGuest
            ? isGuestAddressFilled
                ? guestAddressObject
                : null
            : addresses.find((a: any) => a.id === selectedAddressId),
    );

    // Payment
    // svelte-ignore state_referenced_locally
    let selectedPaymentMethodId = $state(paymentMethods[0]?.id ?? null);
    const selectedPaymentMethod = $derived(
        paymentMethods.find((pm: any) => pm.id === selectedPaymentMethodId),
    );

    $effect(() => {
        if (!selectedPaymentMethodId && paymentMethods?.length > 0) {
            selectedPaymentMethodId = paymentMethods[0].id;
        }
    });

    // Midtrans Core API method selection
    let selectedMidtransMethodKey = $state<string | null>(null);
    // If midtransEnabledMethods is available and user selects it, detect 'midtrans' gateway
    const midtransPaymentMethod = $derived(
        paymentMethods.find((pm: any) =>
            pm.name?.toLowerCase().includes('midtrans'),
        ),
    );
    // When a Core API method is selected, auto-select the Midtrans gateway payment method
    $effect(() => {
        if (selectedMidtransMethodKey && midtransPaymentMethod) {
            selectedPaymentMethodId = midtransPaymentMethod.id;
        }
    });
    // When switching away from midtrans gateway, clear the selected core api method
    $effect(() => {
        if (selectedPaymentMethodId !== midtransPaymentMethod?.id) {
            selectedMidtransMethodKey = null;
        }
    });

    // Icons for Midtrans payment methods
    const midtransMethodIcons: Record<string, string> = {
        bca_va: 'ti-building-bank',
        bni_va: 'ti-building-bank',
        bri_va: 'ti-building-bank',
        mandiri_bill: 'ti-building-bank',
        permata_va: 'ti-building-bank',
        cimb_va: 'ti-building-bank',
        danamon_va: 'ti-building-bank',
        qris: 'ti-qrcode',
        gopay: 'ti-wallet',
        shopeepay: 'ti-wallet',
        indomaret: 'ti-building-store',
        alfamart: 'ti-building-store',
        credit_card: 'ti-credit-card',
    };
    const midtransMethodColors: Record<string, string> = {
        bca_va: '#005baa',
        bni_va: '#f68b1f',
        bri_va: '#015cab',
        mandiri_bill: '#00346e',
        permata_va: '#e31e24',
        cimb_va: '#005baa',
        danamon_va: '#f68b1f',
        qris: '#e31e24',
        gopay: '#00aa13',
        shopeepay: '#ee4d2d',
        indomaret: '#005baa',
        alfamart: '#ee4d2d',
        credit_card: '#0c4cb4',
    };

    // Shipping
    const availableCouriers = $derived.by(() => {
        const hideExpedition =
            isFoodPickMode && (foodpickConfig.hide_expedition ?? true);
        const list =
            biteshipEnabled && !hideExpedition
                ? couriers.map((c: any) => c.code)
                : [];

        const allowStoreCourier = isFoodPickMode
            ? (foodpickConfig.allow_store_courier ?? true)
            : Boolean(storeSettings.store_courier_enabled);

        const allowSelfPickup = isFoodPickMode
            ? (foodpickConfig.allow_self_pickup ?? true)
            : Boolean(storeSettings.self_pickup_enabled);

        if (allowStoreCourier) {
            list.push('store_courier');
        }
        if (allowSelfPickup) {
            list.push('self_pickup');
        }
        return list;
    });

    const courierLabels = $derived.by(() => {
        const labels = couriers.reduce(
            (acc: Record<string, string>, c: any) => {
                acc[c.code] = c.name;
                return acc;
            },
            {},
        );
        labels['store_courier'] = isFoodPickMode
            ? 'Kurir Resto / Pengantaran'
            : 'Kurir Toko';
        labels['self_pickup'] = isFoodPickMode
            ? 'Ambil di Resto / Takeaway'
            : 'Ambil di Toko';
        return labels;
    });

    let selectedCourier = $state('');
    const selectedCourierId = $derived(
        couriers.find((c: any) => c.code === selectedCourier)?.id ?? null,
    );

    // Self Pickup Scheduling State & Slot Calculations
    let pickupDate = $state('');
    let pickupTime = $state('');
    let pickupSubtype = $state<'takeaway' | 'dine_in'>('takeaway');
    let tableNumber = $state<string>('');

    const dayNamesId = [
        'Minggu',
        'Senin',
        'Selasa',
        'Rabu',
        'Kamis',
        'Jumat',
        'Sabtu',
    ];
    const monthNamesId = [
        'Jan',
        'Feb',
        'Mar',
        'Apr',
        'Mei',
        'Jun',
        'Jul',
        'Agt',
        'Sep',
        'Okt',
        'Nov',
        'Des',
    ];
    const daysKeys = [
        'sunday',
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
    ];

    function getStoreHoursForDate(d: Date) {
        if (storeSettings.holiday_mode) return null;
        if (storeSettings.always_open) {
            return { active: true, open: '08:00', close: '21:30' };
        }
        if (!storeSettings.operational_hours) {
            return { active: true, open: '08:00', close: '21:00' };
        }
        const dayKey = daysKeys[d.getDay()];
        const dayConfig = storeSettings.operational_hours[dayKey];
        if (!dayConfig || !dayConfig.active) return null;
        return {
            active: true,
            open: dayConfig.open || '08:00',
            close: dayConfig.close || '21:00',
        };
    }

    function calculateTimeSlots(
        dateStr: string,
        isToday: boolean,
        hours: { open: string; close: string },
    ) {
        const [openH, openM] = (hours.open || '08:00').split(':').map(Number);
        const [closeH, closeM] = (hours.close || '21:00')
            .split(':')
            .map(Number);
        const openTotalM = openH * 60 + openM;
        const closeTotalM = closeH * 60 + closeM;

        let startM = openTotalM;

        const prepMinutes = isFoodPickMode
            ? Number(foodpickConfig.default_prep_minutes) || 20
            : 60;
        const intervalMinutes = isFoodPickMode
            ? Number(foodpickConfig.slot_interval_minutes) || 15
            : 30;

        if (isToday) {
            const now = new Date();
            const nowBuffer = new Date(now.getTime() + prepMinutes * 60 * 1000);
            let bH = nowBuffer.getHours();
            let bM = nowBuffer.getMinutes();
            const rem = bM % intervalMinutes;
            if (rem !== 0) {
                bM += intervalMinutes - rem;
                if (bM >= 60) {
                    bH += Math.floor(bM / 60);
                    bM = bM % 60;
                }
            }
            const bufferTotalM = bH * 60 + bM;
            startM = Math.max(openTotalM, bufferTotalM);
        }

        const slots: string[] = [];
        for (let m = startM; m <= closeTotalM; m += intervalMinutes) {
            const h = Math.floor(m / 60);
            const min = m % 60;
            const hStr = String(h).padStart(2, '0');
            const minStr = String(min).padStart(2, '0');
            slots.push(`${hStr}:${minStr}`);
        }

        return slots;
    }

    const availablePickupDates = $derived.by(() => {
        const list: Array<{
            dateStr: string;
            label: string;
            dayName: string;
            dateFormatted: string;
            isToday: boolean;
        }> = [];

        const now = new Date();

        for (let i = 0; i < 7; i++) {
            const d = new Date();
            d.setDate(now.getDate() + i);
            const hours = getStoreHoursForDate(d);
            if (!hours) continue;

            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const dateNum = String(d.getDate()).padStart(2, '0');
            const dateStr = `${year}-${month}-${dateNum}`;

            const isToday = i === 0;
            const isTomorrow = i === 1;

            if (isToday) {
                const slots = calculateTimeSlots(dateStr, true, hours);
                if (slots.length === 0) continue;
            }

            const dayName = dayNamesId[d.getDay()];
            const monthName = monthNamesId[d.getMonth()];
            const dateFormatted = `${d.getDate()} ${monthName}`;

            let label = `${dayName}, ${dateFormatted}`;
            if (isToday) label = `Hari Ini (${dateFormatted})`;
            else if (isTomorrow) label = `Besok (${dateFormatted})`;

            list.push({
                dateStr,
                label,
                dayName,
                dateFormatted,
                isToday,
            });
        }

        return list;
    });

    const availablePickupSlots = $derived.by(() => {
        if (!pickupDate) return [];
        const [year, month, day] = pickupDate.split('-').map(Number);
        const selectedD = new Date(year, month - 1, day);
        const now = new Date();
        const isToday =
            selectedD.getFullYear() === now.getFullYear() &&
            selectedD.getMonth() === now.getMonth() &&
            selectedD.getDate() === now.getDate();

        const hours = getStoreHoursForDate(selectedD);
        if (!hours) return [];

        return calculateTimeSlots(pickupDate, isToday, hours);
    });

    $effect(() => {
        if (selectedCourier === 'self_pickup') {
            if (availablePickupDates.length > 0) {
                const found = availablePickupDates.find(
                    (d) => d.dateStr === pickupDate,
                );
                if (!found) {
                    pickupDate = availablePickupDates[0].dateStr;
                }
            }
        }
    });

    $effect(() => {
        if (selectedCourier === 'self_pickup' && pickupDate) {
            if (availablePickupSlots.length > 0) {
                if (!pickupTime || !availablePickupSlots.includes(pickupTime)) {
                    pickupTime = availablePickupSlots[0];
                }
            } else {
                pickupTime = '';
            }
        }
    });

    const selectedPickupSummary = $derived.by(() => {
        if (!pickupDate || !pickupTime) return '';
        const found = availablePickupDates.find(
            (d) => d.dateStr === pickupDate,
        );
        if (found) {
            return `${found.dayName}, ${found.dateFormatted} pukul ${pickupTime} WIB`;
        }
        return `${pickupDate} pukul ${pickupTime} WIB`;
    });
    let isInternational = $state(false);
    let internationalCountries = $state([]);
    let selectedCountryId = $state('');
    let loadingCountries = $state(false);

    async function fetchInternationalCountries() {
        if (internationalCountries.length > 0) return;
        loadingCountries = true;
        try {
            const resp = await fetch('/checkout/international-destinations');
            const data = await resp.json();
            if (resp.ok && data.destinations) {
                internationalCountries = data.destinations;
            }
        } catch (e) {
            console.error('Failed to load international countries', e);
        } finally {
            loadingCountries = false;
        }
    }

    $effect(() => {
        if (isInternational) {
            fetchInternationalCountries();
        }
    });

    let searchCourierQuery = $state('');
    let showCourierDropdown = $state(false);
    function closeCourierDropdown(e: MouseEvent) {
        const target = e.target as HTMLElement;
        if (!target.closest('.courier-select-container')) {
            showCourierDropdown = false;
        }
        if (!target.closest('.destination-search-container')) {
            showDestinationDropdown = false;
        }
    }
    let shippingOptions: any[] = $state([]);
    let selectedShipping: any = $state(null);
    let loadingShipping = $state(false);
    let shippingError = $state('');
    // Track couriers that returned no available routes for the current address
    let unavailableCouriers = $state<Set<string>>(new Set());

    // Couriers filtered to exclude those with no coverage for current address
    const visibleCouriers = $derived(
        availableCouriers.filter((c: string) => !unavailableCouriers.has(c)),
    );

    async function autoSelectShipping(courierIndex = 0) {
        if (isDigitalOnly) return;
        if (!selectedAddress && selectedCourier !== 'self_pickup') return;
        if (courierIndex >= availableCouriers.length) {
            if (isFoodPickMode && availableCouriers.length > 0) {
                selectedCourier = availableCouriers[0];
            } else {
                selectedCourier = '';
                shippingOptions = [];
                selectedShipping = null;
                shippingError =
                    'Tidak ada layanan pengiriman yang tersedia untuk alamat ini.';
            }
            return;
        }

        selectedCourier = availableCouriers[courierIndex];
        await fetchShipping();

        if (!shippingOptions || shippingOptions.length === 0) {
            await autoSelectShipping(courierIndex + 1);
        }
    }

    let lastAddressId = $state(untrack(() => selectedAddressId));
    let lastGuestDest = $state('');
    let initialAutoSelectDone = $state(false);

    $effect(() => {
        if (isDigitalOnly) return;
        if (
            !isGuest &&
            selectedAddressId &&
            !initialAutoSelectDone &&
            availableCouriers.length > 0
        ) {
            initialAutoSelectDone = true;
            autoSelectShipping(0);
        } else if (
            !isGuest &&
            selectedAddressId &&
            selectedAddressId !== lastAddressId
        ) {
            lastAddressId = selectedAddressId;
            // Reset unavailable couriers — may differ per address
            unavailableCouriers = new Set();
            if (selectedCourier) {
                fetchShipping().then(() => {
                    if (!shippingOptions || shippingOptions.length === 0) {
                        autoSelectShipping(0);
                    }
                });
            } else {
                autoSelectShipping(0);
            }
        } else if (isGuest && isGuestAddressFilled) {
            const currentDest = `${guestRegencyName}_${guestBiteshipAreaId}_${guestPostalCode}`;
            if (currentDest !== lastGuestDest) {
                lastGuestDest = currentDest;
                unavailableCouriers = new Set();
                if (selectedCourier) {
                    fetchShipping().then(() => {
                        if (!shippingOptions || shippingOptions.length === 0) {
                            autoSelectShipping(0);
                        }
                    });
                } else {
                    autoSelectShipping(0);
                }
            }
        }
    });

    // Voucher
    let initialVoucherCode = $derived.by(() => {
        if (!initialAppliedVoucher) return '';
        if (Array.isArray(initialAppliedVoucher.promotions)) {
            return initialAppliedVoucher.promotions
                .map((p: any) => p.code)
                .join(',');
        } else if (initialAppliedVoucher.promotion) {
            return initialAppliedVoucher.promotion.code;
        }
        return '';
    });

    // svelte-ignore state_referenced_locally
    let voucherInputCode = $state(initialVoucherCode);
    let manualPromoCode = $state('');
    // svelte-ignore state_referenced_locally
    let appliedVoucher: any = $state(initialAppliedVoucher);
    let voucherError = $state('');
    let voucherLoading = $state(false);
    let voucherModalOpen = $state(false);

    // Read query parameter and apply voucher reactively using page.url to prevent SPA race conditions
    const queryVoucher = $derived.by(() => {
        const urlStr = page.url;
        if (!urlStr) return '';
        try {
            const url = new URL(urlStr, 'http://localhost');
            return url.searchParams.get('voucher') ?? '';
        } catch {
            return '';
        }
    });

    // svelte-ignore state_referenced_locally
    let lastAppliedQueryVoucher = $state(initialVoucherCode);
    $effect(() => {
        const currentQuery = queryVoucher;
        if (currentQuery && currentQuery !== lastAppliedQueryVoucher) {
            lastAppliedQueryVoucher = currentQuery;
            voucherInputCode = currentQuery;
            applyVoucherCodes(currentQuery);
        }
    });

    // Reactive effect to re-apply vouchers if shipping fee changes to ensure shipping discounts calculate correctly
    $effect(() => {
        if (voucherInputCode && shippingFee > 0) {
            applyVoucherCodes(voucherInputCode);
        }
    });

    function selectVoucher(voucher: any) {
        let codes = voucherInputCode
            .split(',')
            .map((c) => c.trim().toUpperCase())
            .filter(Boolean);

        if (voucher.type === 'voucher_gratis_ongkir') {
            const existingShipping = vouchers.find(
                (v) =>
                    v.type === 'voucher_gratis_ongkir' &&
                    codes.includes(v.code.toUpperCase()),
            );
            if (existingShipping) {
                codes = codes.filter(
                    (c) => c !== existingShipping.code.toUpperCase(),
                );
            }
            codes.push(voucher.code.toUpperCase());
        } else {
            const existingDiscount = vouchers.find(
                (v) =>
                    v.type !== 'voucher_gratis_ongkir' &&
                    codes.includes(v.code.toUpperCase()),
            );
            if (existingDiscount) {
                codes = codes.filter(
                    (c) => c !== existingDiscount.code.toUpperCase(),
                );
            }
            codes.push(voucher.code.toUpperCase());
        }

        voucherInputCode = codes.join(',');
        applyVoucherCodes(voucherInputCode);
    }

    function deselectVoucher(voucher: any) {
        let codes = voucherInputCode
            .split(',')
            .map((c) => c.trim().toUpperCase())
            .filter(Boolean);
        codes = codes.filter((c) => c !== voucher.code.toUpperCase());
        voucherInputCode = codes.join(',');
        applyVoucherCodes(voucherInputCode);
    }

    // Notes
    let orderNotes = $state('');
    let itemNotes = $state<Record<string | number, string>>({});

    $effect(() => {
        if (cartItems && cartItems.length > 0) {
            let updated = false;
            const nextNotes = { ...itemNotes };
            for (const it of cartItems) {
                if (it.id && nextNotes[it.id] === undefined && it.note) {
                    nextNotes[it.id] = it.note;
                    updated = true;
                }
            }
            if (updated) {
                itemNotes = nextNotes;
            }
        }
    });

    const isDigitalOnly = $derived(
        (cartItems || []).every((item: any) => item.product?.is_digital),
    );

    // Submitting
    let isSubmitting = $state(false);

    function fmt(price: any): string {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
        }).format(Number(price) || 0);
    }

    function formatNumber(num: number): string {
        return new Intl.NumberFormat('id-ID').format(num);
    }

    function withOpacity(hex: string, alpha: number) {
        if (!hex) return `rgba(0, 0, 0, ${alpha})`;
        let r = 0,
            g = 0,
            b = 0;
        if (hex.length === 4) {
            r = parseInt(hex[1] + hex[1], 16);
            g = parseInt(hex[2] + hex[2], 16);
            b = parseInt(hex[3] + hex[3], 16);
        } else if (hex.length === 7) {
            r = parseInt(hex.substring(1, 3), 16);
            g = parseInt(hex.substring(3, 5), 16);
            b = parseInt(hex.substring(5, 7), 16);
        }
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }

    /**
     * Returns the correct image src:
     * - Full URLs (http/https) are returned as-is
     * - Paths starting with / are returned as-is
     * - Other paths get a leading /
     * - null/empty → noimage fallback
     */
    function formatImagePath(path: string | null | undefined): string {
        if (!path) return '/noimage/image.png';
        if (
            path.startsWith('http://') ||
            path.startsWith('https://') ||
            path.startsWith('/')
        ) {
            return path;
        }
        return '/' + path;
    }

    function getCartItemImage(item: any): string {
        const variant = item.productVariant ?? item.product_variant;
        const product = item.product;
        if (variant?.image) return formatImagePath(variant.image);
        if (product?.images?.length > 0) {
            return formatImagePath(
                product.images[0].url || product.images[0].path,
            );
        }
        if (product?.image) return formatImagePath(product.image);
        return '/noimage/image.png';
    }

    // --- Bundling eligibility (mirrors Cart.svelte) ---
    function getBuyItemCartStatus(buyItem: any) {
        const cartItem = cartItems.find((ci: any) => {
            const matchProduct =
                Number(ci.product_id) === Number(buyItem.product_id);
            const matchVariant = buyItem.product_variant_id
                ? Number(ci.product_variant_id) ===
                  Number(buyItem.product_variant_id)
                : true;
            return matchProduct && matchVariant;
        });
        // In checkout all items are checked
        const currentQty = cartItem ? cartItem.quantity : 0;
        const requiredQty = Number(buyItem.qty);
        return {
            inCart: !!cartItem,
            currentQty,
            requiredQty,
            isMet: currentQty >= requiredQty,
        };
    }

    function checkPromoEligibility(promo: any): boolean {
        const bundle = promo.settings?.bundle;
        if (!bundle || !bundle.buy_items) return false;
        return bundle.buy_items.every(
            (buyItem: any) => getBuyItemCartStatus(buyItem).isMet,
        );
    }

    const eligibleBundlingPromos = $derived(
        (activePromotions as any[]).filter(
            (p) => p.type === 'bundling_gift' && checkPromoEligibility(p),
        ),
    );

    // Calculate totals
    const subtotal = $derived(
        cartItems.reduce(
            (acc: number, item: any) =>
                acc +
                Number(
                    item.subtotal ??
                        (item.unit_price ??
                            item.computed_price ??
                            item.price ??
                            item.product?.product_price?.price ??
                            item.product?.productPrice?.price ??
                            0) * item.quantity,
                ),
            0,
        ),
    );
    const shippingFee = $derived(
        isDigitalOnly
            ? 0
            : Number(
                  selectedShipping ? (selectedShipping.cost[0]?.value ?? 0) : 0,
              ),
    );
    const shippingDiscount = $derived(
        Number(appliedVoucher?.shipping_discount ?? 0),
    );
    const discountAmount = $derived(
        Number(appliedVoucher?.discount_amount ?? 0),
    );
    const adminFee = $derived.by(() => {
        // If a Midtrans Core API method is selected, use midtransAdminFee
        const isMidtransCoreSelected = !!(
            selectedMidtransMethodKey &&
            midtransPaymentMethod &&
            selectedPaymentMethodId === midtransPaymentMethod.id
        );
        return isMidtransCoreSelected
            ? Number(midtransAdminFee ?? 0)
            : Number((selectedPaymentMethod as any)?.admin_fee ?? 0);
    });

    const applicationFee = $derived(Number(appFee ?? 0));

    // Coins Integration
    let useCoins = $state(false);

    // Dynamic calculations for coins
    const coinsSettings = $derived((page.props as any).settings);
    const authUser = $derived((page.props as any).auth?.user);
    const coinsEnabled = $derived(coinsSettings?.coins_enabled);
    const coinConversionRate = $derived(
        Number(coinsSettings?.coin_conversion_rate || 1),
    );
    const coinMinPurchaseRedeem = $derived(
        Number(coinsSettings?.coin_min_purchase_redeem || 0),
    );
    const coinMaxRedeemPerTxn = $derived(
        Number(coinsSettings?.coin_max_redeem_per_txn || 50000),
    );
    const coinMaxRedeemPercentage = $derived(
        Number(coinsSettings?.coin_max_redeem_percentage || 100),
    );

    // User's balance
    const userCoinsBalance = $derived(Number(authUser?.coins_balance || 0));
    const userCoinsValue = $derived(userCoinsBalance * coinConversionRate);

    // Validate if user meets minimum purchase rule to redeem coins
    const isCoinsMinPurchaseMet = $derived(subtotal >= coinMinPurchaseRedeem);

    // Calculate maximum coins we can redeem for this order
    const maxCoinsRedeemValueAllowed = $derived.by(() => {
        // Max based on percentage of order subtotal
        const maxPercentValue = (subtotal * coinMaxRedeemPercentage) / 100;
        // Max cap per txn in Rupiah
        const maxCapValue = coinMaxRedeemPerTxn;

        return Math.min(userCoinsValue, maxPercentValue, maxCapValue);
    });

    const maxCoinsRedeemAllowed = $derived(
        Math.floor(maxCoinsRedeemValueAllowed / coinConversionRate),
    );

    // Dynamic discount applied by coins
    const coinDiscountAmount = $derived.by(() => {
        if (
            !useCoins ||
            !coinsEnabled ||
            !isCoinsMinPurchaseMet ||
            maxCoinsRedeemAllowed <= 0
        ) {
            return 0;
        }
        return maxCoinsRedeemAllowed * coinConversionRate;
    });

    // Prospective coins earned
    const coinEarningMethod = $derived(
        coinsSettings?.coin_earning_method || 'proportional',
    );
    const coinEarningRateRupiah = $derived(
        Number(coinsSettings?.coin_earning_rate_rupiah || 1000),
    );
    const coinEarningRateCoins = $derived(
        Number(coinsSettings?.coin_earning_rate_coins || 1),
    );
    const coinEarningTiers = $derived(coinsSettings?.coin_earning_tiers || []);

    const prospectiveCoinsEarned = $derived.by(() => {
        if (!coinsEnabled) return 0;

        if (appliedVoucher && appliedVoucher.is_points_voucher) {
            return Number(appliedVoucher.voucher_points || 0);
        }

        if (useCoins && coinDiscountAmount > 0) return 0;

        if (coinEarningMethod === 'proportional') {
            if (coinEarningRateRupiah <= 0) return 0;
            return (
                Math.floor(subtotal / coinEarningRateRupiah) *
                coinEarningRateCoins
            );
        } else if (coinEarningMethod === 'tiered') {
            const sortedTiers = [...coinEarningTiers].sort(
                (a, b) => Number(b.min_purchase) - Number(a.min_purchase),
            );
            for (const tier of sortedTiers) {
                if (subtotal >= Number(tier.min_purchase)) {
                    return Number(tier.earn_coins);
                }
            }
        }
        return 0;
    });

    const activeAdditionalCosts = $derived(
        (additionalCosts || []).filter(
            (c: any) => c.is_active || c.is_active === '1' || c.is_active === 1,
        ),
    );
    const additionalCostsSum = $derived(
        activeAdditionalCosts.reduce(
            (sum: number, c: any) => sum + Number(c.value || 0),
            0,
        ),
    );

    const grandTotal = $derived(
        Math.max(
            0,
            subtotal -
                discountAmount -
                coinDiscountAmount +
                (shippingFee - shippingDiscount) +
                adminFee +
                applicationFee +
                additionalCostsSum,
        ),
    );

    // Total weight for shipping
    const totalWeightGrams = $derived(
        cartItems.reduce((acc: number, item: any) => {
            if (item.product?.is_digital) {
                return acc;
            }
            const w =
                (item.productVariant ?? item.product_variant)?.weight ??
                item.product?.weight ??
                1000;
            return acc + w * item.quantity;
        }, 0),
    );

    function getCleanEtd(etd: any, service: string = ''): string {
        if (!etd) {
            return getFallbackEtdRaw(service);
        }
        const clean = etd
            .toString()
            .toLowerCase()
            .replace(/days?|hari/gi, '')
            .trim();
        if (clean === '' || clean === '-') {
            return getFallbackEtdRaw(service);
        }
        return clean;
    }

    function getFallbackEtdRaw(service: string): string {
        const s = service.toLowerCase();
        if (s.includes('cargo') || s.includes('gokil') || s.includes('jtr')) {
            return '3-7';
        }
        if (
            s.includes('instant') ||
            s.includes('sameday') ||
            s.includes('same day') ||
            s.includes('gojek') ||
            s.includes('grab')
        ) {
            return '0';
        }
        return '2-4';
    }

    function getEtdLabel(etd: any, service: string = ''): string {
        const clean = getCleanEtd(etd, service);
        if (
            clean === '0' ||
            clean === '0-0' ||
            clean === '0-0 hari' ||
            clean === '0 - 0'
        ) {
            return 'Hari yang sama (Same Day)';
        }
        return `Estimasi: ${clean} hari`;
    }

    async function fetchShipping() {
        if (isInternational && !selectedCountryId) {
            shippingError = 'Pilih negara tujuan terlebih dahulu.';
            return;
        }
        if (selectedCourier !== 'self_pickup' && !selectedAddress) return;
        if (!selectedCourier) return;

        loadingShipping = true;
        shippingError = '';
        shippingOptions = [];
        selectedShipping = null;

        try {
            const rawDest =
                selectedCourier === 'self_pickup'
                    ? 'self_pickup'
                    : isInternational
                      ? selectedCountryId
                          ? String(selectedCountryId)
                          : ''
                      : selectedAddress?.biteship_area_id ||
                        selectedAddress?.city_id ||
                        selectedAddress?.regency_id ||
                        selectedAddress?.postal_code ||
                        selectedAddress?.regency_name ||
                        selectedAddress?.full_address ||
                        (isFoodPickMode ? 'store_courier' : '');

            const destinationStr =
                selectedCourier === 'self_pickup'
                    ? 'self_pickup'
                    : rawDest
                      ? String(rawDest)
                      : isFoodPickMode
                        ? 'store_courier'
                        : '';

            const destAreaIdStr =
                selectedCourier !== 'self_pickup' &&
                selectedAddress?.biteship_area_id != null &&
                selectedAddress?.biteship_area_id !== ''
                    ? String(selectedAddress.biteship_area_id)
                    : undefined;

            const resp = await fetch('/checkout/shipping-cost', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as any
                        )?.content ?? '',
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    destination: destinationStr,
                    weight:
                        Math.max(1, Math.ceil(totalWeightGrams / 1000)) * 1000,
                    courier: selectedCourier,
                    is_international: isInternational,
                    address_id:
                        selectedCourier === 'self_pickup' || isGuest
                            ? null
                            : selectedAddress?.id
                              ? String(selectedAddress.id)
                              : null,
                    latitude:
                        selectedAddress?.latitude != null
                            ? Number(selectedAddress.latitude)
                            : undefined,
                    longitude:
                        selectedAddress?.longitude != null
                            ? Number(selectedAddress.longitude)
                            : undefined,
                    dest_area_id: destAreaIdStr,
                    biteship_area_id: destAreaIdStr,
                    resto_id: cartItems?.[0]?.product?.user_id
                        ? String(cartItems[0].product.user_id)
                        : undefined,
                }),
            });

            const data = await resp.json();
            if (resp.ok && data.results && data.results.length > 0) {
                // Collect costs from ALL result groups (Biteship returns all services
                // for one courier grouped; other providers may return multiple groups)
                const allServices: any[] = [];
                for (const result of data.results) {
                    const costs = result.costs ?? [];
                    allServices.push(...costs);
                }
                shippingOptions = allServices;
                if (shippingOptions.length > 0) {
                    shippingOptions.sort(
                        (a: any, b: any) =>
                            (a.cost?.[0]?.value ?? 0) -
                            (b.cost?.[0]?.value ?? 0),
                    );
                    selectedShipping = shippingOptions[0];
                    // Mark courier as available for this address
                    unavailableCouriers = new Set(
                        [...unavailableCouriers].filter(
                            (c) => c !== selectedCourier,
                        ),
                    );
                } else {
                    // No services under this courier — mark as unavailable
                    unavailableCouriers = new Set([
                        ...unavailableCouriers,
                        selectedCourier,
                    ]);
                    shippingError =
                        'Tidak ada layanan pengiriman tersedia untuk kurir ini.';
                }
            } else {
                // API returned error or empty — mark courier as unavailable
                unavailableCouriers = new Set([
                    ...unavailableCouriers,
                    selectedCourier,
                ]);
                shippingError =
                    data.error ??
                    data.message ??
                    'Tidak ada layanan pengiriman tersedia.';
            }
        } catch {
            shippingError = 'Gagal memuat ongkir. Coba lagi.';
        } finally {
            loadingShipping = false;
        }
    }

    async function applyVoucherCodes(codesStr: string) {
        if (!codesStr.trim()) {
            appliedVoucher = null;
            voucherInputCode = '';
            return;
        }
        voucherLoading = true;
        voucherError = '';

        try {
            const resp = await fetch('/checkout/apply-voucher', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as any
                        )?.content ?? '',
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    code: codesStr,
                    subtotal: subtotal,
                    shipping_fee: shippingFee,
                }),
            });

            const data = await resp.json();
            if (data.valid) {
                appliedVoucher = data;
                voucherInputCode = codesStr;
            } else {
                voucherError = data.message ?? 'Voucher tidak valid.';
            }
        } catch {
            voucherError = 'Gagal memvalidasi voucher.';
        } finally {
            voucherLoading = false;
        }
    }

    async function applyVoucher() {
        const codeToApply = manualPromoCode.trim().toUpperCase();
        if (!codeToApply) return;

        voucherLoading = true;
        voucherError = '';

        // Add codeToApply to the comma-separated list
        let codes = voucherInputCode
            .split(',')
            .map((c) => c.trim().toUpperCase())
            .filter(Boolean);
        if (!codes.includes(codeToApply)) {
            codes.push(codeToApply);
        }

        const newCodesStr = codes.join(',');

        try {
            const resp = await fetch('/checkout/apply-voucher', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        (
                            document.querySelector(
                                'meta[name="csrf-token"]',
                            ) as any
                        )?.content ?? '',
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    code: newCodesStr,
                    subtotal: subtotal,
                    shipping_fee: shippingFee,
                }),
            });

            const data = await resp.json();
            if (data.valid) {
                appliedVoucher = data;
                voucherInputCode = newCodesStr;
                manualPromoCode = ''; // Clear manual input upon success!
                showToast(
                    data.message ?? 'Voucher berhasil diterapkan!',
                    'success',
                );
            } else {
                voucherError = data.message ?? 'Voucher tidak valid.';
            }
        } catch {
            voucherError = 'Gagal memvalidasi voucher.';
        } finally {
            voucherLoading = false;
        }
    }

    function removeVoucher() {
        appliedVoucher = null;
        voucherInputCode = '';
        voucherError = '';
        manualPromoCode = '';
    }

    function submitCheckout() {
        if (isCheckoutLocked) {
            showToast(checkoutLockedMessage, 'error');
            return;
        }
        if (!isStoreOpen) {
            showToast(
                'Toko sedang tutup. Checkout tidak tersedia saat ini.',
                'error',
            );
            return;
        }
        if (isGuest) {
            if (!guestName.trim()) {
                showToast('Nama pemesan wajib diisi.', 'error');
                return;
            }
            if (!guestPhone.trim() || guestPhone.trim().length < 8) {
                showToast(
                    'Nomor WhatsApp / HP pemesan wajib diisi minimal 8 digit.',
                    'error',
                );
                return;
            }
            if (
                !guestEmail.trim() ||
                !guestEmail.includes('@') ||
                !guestEmail.includes('.')
            ) {
                showToast(
                    'Alamat email pemesan wajib diisi dengan format valid.',
                    'error',
                );
                return;
            }
        }
        if (!isDigitalOnly) {
            if (selectedCourier !== 'self_pickup') {
                if (!isGuest && !selectedAddressId) {
                    showToast(
                        'Pilih alamat pengiriman terlebih dahulu.',
                        'error',
                    );
                    return;
                }
                if (isGuest) {
                    if (!guestFullAddress.trim()) {
                        showToast(
                            'Alamat lengkap pengiriman wajib diisi.',
                            'error',
                        );
                        return;
                    }
                    if (
                        !guestRegencyName.trim() &&
                        !guestBiteshipAreaId.trim()
                    ) {
                        showToast(
                            'Kota/Kabupaten atau lokasi tujuan pengiriman wajib dipilih/diisi.',
                            'error',
                        );
                        return;
                    }
                    if (!guestPostalCode.trim()) {
                        showToast(
                            'Kode pos tujuan pengiriman wajib diisi.',
                            'error',
                        );
                        return;
                    }
                }
            }
            if (!selectedCourier) {
                showToast('Pilih kurir pengiriman terlebih dahulu.', 'error');
                return;
            }
            if (!selectedShipping) {
                showToast('Pilih layanan pengiriman terlebih dahulu.', 'error');
                return;
            }
            if (
                selectedCourier === 'self_pickup' &&
                (!pickupDate || !pickupTime)
            ) {
                showToast(
                    'Pilih tanggal dan jam pengambilan barang terlebih dahulu.',
                    'error',
                );
                return;
            }
        }
        if (!selectedPaymentMethodId && midtransEnabledMethods.length === 0) {
            showToast('Pilih metode pembayaran.', 'error');
            return;
        }
        if (
            selectedPaymentMethodId === midtransPaymentMethod?.id &&
            midtransEnabledMethods.length > 0 &&
            !selectedMidtransMethodKey
        ) {
            showToast(
                'Pilih metode pembayaran Midtrans terlebih dahulu.',
                'error',
            );
            return;
        }
        if (
            !selectedPaymentMethodId &&
            midtransEnabledMethods.length > 0 &&
            !selectedMidtransMethodKey
        ) {
            showToast('Pilih metode pembayaran terlebih dahulu.', 'error');
            return;
        }

        isSubmitting = true;

        const payload: any = {
            customer_address_id: isDigitalOnly
                ? null
                : selectedCourier === 'self_pickup' || isGuest
                  ? null
                  : selectedAddressId,
            payment_method_id: selectedPaymentMethodId,
            midtrans_payment_type_key: selectedMidtransMethodKey ?? undefined,
            courier_id: isDigitalOnly ? null : selectedCourierId,
            shipping_courier: isDigitalOnly ? 'digital' : selectedCourier,
            shipping_service: isDigitalOnly
                ? 'email'
                : (selectedShipping?.service ?? ''),
            shipping_etd: isDigitalOnly
                ? '0'
                : getCleanEtd(
                      selectedShipping?.cost?.[0]?.etd,
                      selectedShipping?.service ||
                          selectedShipping?.description,
                  ),
            shipping_fee: isDigitalOnly ? 0 : shippingFee,
            pickup_date: selectedCourier === 'self_pickup' ? pickupDate : null,
            pickup_time: selectedCourier === 'self_pickup' ? pickupTime : null,
            pickup_at:
                selectedCourier === 'self_pickup' && pickupDate && pickupTime
                    ? `${pickupDate} ${pickupTime}:00`
                    : null,
            resto_id: cartItems?.[0]?.product?.user_id ?? null,
            fulfillment_type:
                selectedCourier === 'self_pickup'
                    ? pickupSubtype === 'dine_in'
                        ? 'dine_in'
                        : 'self_pickup'
                    : 'store_courier',
            pickup_type:
                selectedCourier === 'self_pickup' ? pickupSubtype : 'delivery',
            table_number:
                selectedCourier === 'self_pickup' && pickupSubtype === 'dine_in'
                    ? tableNumber.trim()
                    : null,
            scheduled_date:
                selectedCourier === 'self_pickup' ? pickupDate : null,
            scheduled_time_slot:
                selectedCourier === 'self_pickup' ? pickupTime : null,
            voucher_code: voucherInputCode ?? '',
            notes: orderNotes,
            use_coins: isGuest ? false : useCoins,
            item_notes: itemNotes,
        };

        if (isGuest) {
            payload.customer_name = guestName.trim();
            payload.customer_email = guestEmail.trim().toLowerCase();
            payload.customer_phone = guestPhone.trim();

            if (!isDigitalOnly && selectedCourier !== 'self_pickup') {
                payload.full_address = guestFullAddress.trim();
                payload.province_name = guestProvinceName || 'Indonesia';
                payload.regency_name = guestRegencyName || '-';
                payload.district_name = guestDistrictName || '-';
                payload.postal_code = guestPostalCode || '00000';
                payload.province_id = guestProvinceId || null;
                payload.regency_id = guestRegencyId || null;
                payload.district_id = guestDistrictId || null;
                payload.village_id = guestVillageId || null;
                payload.village_name = guestVillageName || null;
                payload.biteship_area_id = guestBiteshipAreaId || null;
                payload.rajaongkir_destination_id =
                    guestRajaongkirDestId || null;
                payload.latitude = guestLat || null;
                payload.longitude = guestLng || null;
                payload.receiver_name = (
                    guestSameAsCustomer
                        ? guestName
                        : guestReceiverName || guestName
                ).trim();
                payload.phone_number = (
                    guestSameAsCustomer
                        ? guestPhone
                        : guestReceiverPhone || guestPhone
                ).trim();
            }
        }

        router.post('/checkout', payload, {
            onError: (errors) => {
                isSubmitting = false;
                const errorMsg = (Object.values(errors)[0] as string) || '';
                showToast(
                    errorMsg ||
                        'Terjadi kesalahan. Periksa data dan coba lagi.',
                    'error',
                );
            },
            onFinish: () => {
                isSubmitting = false;
            },
        });
    }

    // ─── Promo Expiry Tracking ───────────────────────────────────────────────
    // Collect all unique promo end times from cart items (flash sale, promo_produk, etc.)
    const promoEndTimes = $derived.by(() => {
        const times: { name: string; endTime: Date }[] = [];
        const seen = new Set<string>();
        for (const item of cartItems as any[]) {
            const src = (item.productVariant ??
                item.product_variant ??
                item.product) as any;
            if (src?.promo_end_time && src?.is_promo && src?.promo_type) {
                const key = src.promo_end_time;
                if (!seen.has(key)) {
                    seen.add(key);
                    const d = new Date(String(key).replace(' ', 'T'));
                    if (!isNaN(d.getTime())) {
                        times.push({
                            name:
                                src.promo_type === 'flash_sale'
                                    ? 'Flash Sale'
                                    : src.promo_type === 'promo_produk'
                                      ? 'Promo Produk'
                                      : src.promo_type === 'bundling_gift'
                                        ? 'Bundling Gift'
                                        : 'Promo',
                            endTime: d,
                        });
                    }
                }
            }
        }
        // Sort by soonest to expire first
        return times.sort((a, b) => a.endTime.getTime() - b.endTime.getTime());
    });

    let promoCountdowns = $state<
        { name: string; remaining: number; label: string }[]
    >([]);
    let promoExpiredNames = $state<string[]>([]);
    let promoExpiryTimer: ReturnType<typeof setInterval> | null = null;

    function computePromoCountdowns() {
        const now = Date.now();
        const updated: typeof promoCountdowns = [];
        const expired: string[] = [];

        for (const item of promoEndTimes) {
            const diff = item.endTime.getTime() - now;
            if (diff <= 0) {
                expired.push(item.name);
            } else {
                const h = Math.floor(diff / 3_600_000);
                const m = Math.floor((diff % 3_600_000) / 60_000);
                const s = Math.floor((diff % 60_000) / 1_000);
                const label =
                    h > 0
                        ? `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
                        : `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
                updated.push({ name: item.name, remaining: diff, label });
            }
        }

        promoCountdowns = updated;

        // If newly expired promos detected, reload to get fresh pricing from server
        if (expired.length > 0) {
            const newExpired = expired.filter(
                (n) => !promoExpiredNames.includes(n),
            );
            if (newExpired.length > 0) {
                promoExpiredNames = [...promoExpiredNames, ...newExpired];
                showToast(
                    `${newExpired.join(', ')} telah berakhir. Harga diperbarui ke harga normal.`,
                    'error',
                );
                // Reload checkout page so server recalculates prices without the expired promo
                setTimeout(() => router.reload(), 1500);
            }
        }
    }

    $effect(() => {
        if (promoEndTimes.length > 0) {
            computePromoCountdowns();
            promoExpiryTimer = setInterval(computePromoCountdowns, 1000);
        } else {
            if (promoExpiryTimer) clearInterval(promoExpiryTimer);
            promoExpiryTimer = null;
        }
        return () => {
            if (promoExpiryTimer) clearInterval(promoExpiryTimer);
        };
    });

    // Soonest expiring promo (to show main warning banner)
    const soonestPromoCountdown = $derived(
        promoCountdowns.length > 0 ? promoCountdowns[0] : null,
    );

    // Show urgent warning when promo expires in less than 5 minutes
    const isPromoExpiringSoon = $derived(
        soonestPromoCountdown !== null &&
            soonestPromoCountdown.remaining < 5 * 60 * 1000,
    );

    const statusColors: Record<string, string> = {
        belum_bayar: '#f59e0b',
        menunggu: '#3b82f6',
        diproses: '#8b5cf6',
        dikemas: '#06b6d4',
        dikirim: '#f97316',
        selesai: '#22c55e',
        batal: '#ef4444',
    };

    const isCheckoutDisabled = $derived(
        isSubmitting ||
            isCheckoutLocked ||
            (!isDigitalOnly &&
                selectedCourier !== 'self_pickup' &&
                (!isGuest ? !selectedAddressId : !isGuestAddressFilled)) ||
            (isGuest && !isGuestCustomerValid) ||
            !selectedPaymentMethodId ||
            (!isDigitalOnly &&
                selectedCourier !== 'self_pickup' &&
                !selectedShipping) ||
            !isStoreOpen,
    );
</script>

<svelte:window onclick={closeCourierDropdown} />

<StorefrontLayout
    {storeName}
    {storeLogo}
    hideMobileFooter={true}
    hideMobileBottomNav={true}
>
    <div class="min-h-dvh bg-slate-50">
        <CheckoutHeader />

        <div class="max-w-6xl mx-auto px-4 py-4 pb-24">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <!-- Left Column -->
                <div class="lg:col-span-2 space-y-4">
                    <PromoExpiryNotice
                        {promoCountdowns}
                        {promoExpiredNames}
                        {soonestPromoCountdown}
                        {isPromoExpiringSoon}
                    />

                    {#if isGuest}
                        <GuestCustomerSection
                            {primary}
                            bind:guestName
                            bind:guestPhone
                            bind:guestEmail
                        />
                    {/if}

                    {#if !isDigitalOnly}
                        <!-- Address Section -->
                        <div
                            class="bg-white rounded-2xl shadow-sm border border-slate-100 relative"
                        >
                            {#if selectedCourier === 'self_pickup'}
                                <div
                                    class="py-2.5 px-4 flex items-center justify-between border-b border-slate-100 bg-slate-50 rounded-t-2xl"
                                >
                                    <div class="flex items-center gap-2">
                                        <i
                                            class="ti {isFoodPickMode
                                                ? 'ti-tools-kitchen-2 text-amber-600'
                                                : 'ti-building-store text-emerald-600'} text-lg"
                                        ></i>
                                        <span
                                            class="font-bold text-slate-800 text-sm"
                                            >{isFoodPickMode
                                                ? 'Lokasi & Waktu Kesiapan (Ambil di Resto)'
                                                : 'Lokasi & Waktu Pengambilan (Ambil di Toko)'}</span
                                        >
                                    </div>
                                    <span
                                        class="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full {isFoodPickMode
                                            ? 'bg-amber-100 text-amber-800 border border-amber-200'
                                            : 'bg-emerald-100 text-emerald-800 border border-emerald-200'}"
                                    >
                                        {isFoodPickMode
                                            ? 'Ambil di Resto'
                                            : 'Self Pickup'}
                                    </span>
                                </div>
                                <div class="py-3.5 px-4 space-y-3.5">
                                    {#if isFoodPickMode}
                                        <!-- Pilihan Layanan: Takeaway vs Dine-In -->
                                        <div class="space-y-2">
                                            <label
                                                class="block text-[11px] font-bold text-slate-700"
                                            >
                                                Pilihan Layanan Kuliner:
                                            </label>
                                            <div class="grid grid-cols-2 gap-2">
                                                <button
                                                    type="button"
                                                    onclick={() =>
                                                        (pickupSubtype =
                                                            'takeaway')}
                                                    class="py-2 px-3 rounded-xl border text-xs font-bold transition flex items-center justify-center gap-2 cursor-pointer {pickupSubtype ===
                                                    'takeaway'
                                                        ? 'bg-amber-600 text-white border-amber-600 shadow-xs'
                                                        : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'}"
                                                >
                                                    <span>🥡</span>
                                                    <span
                                                        >Bungkus (Takeaway)</span
                                                    >
                                                </button>
                                                <button
                                                    type="button"
                                                    onclick={() =>
                                                        (pickupSubtype =
                                                            'dine_in')}
                                                    class="py-2 px-3 rounded-xl border text-xs font-bold transition flex items-center justify-center gap-2 cursor-pointer {pickupSubtype ===
                                                    'dine_in'
                                                        ? 'bg-amber-600 text-white border-amber-600 shadow-xs'
                                                        : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'}"
                                                >
                                                    <span>🍽️</span>
                                                    <span>Makan di Tempat</span>
                                                </button>
                                            </div>

                                            {#if pickupSubtype === 'dine_in'}
                                                <div
                                                    class="p-2.5 bg-amber-50/70 border border-amber-200 rounded-xl space-y-1"
                                                >
                                                    <label
                                                        for="table_number_input"
                                                        class="block text-2xs font-extrabold uppercase text-amber-900 tracking-wider"
                                                    >
                                                        Nomor Meja Resto:
                                                    </label>
                                                    <input
                                                        id="table_number_input"
                                                        type="text"
                                                        bind:value={tableNumber}
                                                        placeholder="Contoh: Meja 07 / Lantai 2"
                                                        class="w-full text-xs px-3 py-1.5 rounded-lg border border-amber-300 bg-white focus:outline-hidden focus:ring-1 focus:ring-amber-500 font-bold"
                                                    />
                                                    <span
                                                        class="text-3xs text-amber-700 block"
                                                        >Pesanan akan langsung
                                                        diantar pramusaji ke
                                                        meja Anda.</span
                                                    >
                                                </div>
                                            {/if}
                                        </div>
                                    {/if}

                                    <!-- Store Address Box -->
                                    <div
                                        class="p-3 bg-slate-50/80 rounded-xl border border-slate-200/80 flex items-start gap-2.5"
                                    >
                                        <i
                                            class="ti ti-map-pin text-emerald-600 text-base mt-0.5 shrink-0"
                                        ></i>
                                        <div class="min-w-0 flex-1">
                                            <p
                                                class="text-xs font-bold text-slate-800 mb-0.5"
                                            >
                                                {storeSettings.store_name ||
                                                    storeName}
                                            </p>
                                            <p
                                                class="text-[11px] text-slate-600 leading-relaxed font-medium"
                                            >
                                                {storeSettings.store_address}
                                                {#if storeSettings.store_village},
                                                    {storeSettings.store_village}{/if}
                                                {#if storeSettings.store_district},
                                                    {storeSettings.store_district}{/if}
                                                {#if storeSettings.store_regency},
                                                    {storeSettings.store_regency}{/if}
                                                {#if storeSettings.store_province},
                                                    {storeSettings.store_province}{/if}
                                                {#if storeSettings.store_postal_code}
                                                    {storeSettings.store_postal_code}{/if}
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Pickup Schedule Picker -->
                                    <div
                                        class="p-3.5 bg-gradient-to-br from-emerald-50/60 to-teal-50/30 rounded-2xl border border-emerald-200/80 space-y-3"
                                    >
                                        <div
                                            class="flex items-center justify-between"
                                        >
                                            <div
                                                class="flex items-center gap-1.5"
                                            >
                                                <i
                                                    class="ti ti-calendar-time text-emerald-600 text-base"
                                                ></i>
                                                <span
                                                    class="text-xs font-bold text-slate-800"
                                                    >Pilih Waktu Pengambilan</span
                                                >
                                            </div>
                                            <span
                                                class="text-[10px] font-semibold text-emerald-700 bg-emerald-100/80 px-2 py-0.5 rounded-full border border-emerald-200"
                                            >
                                                {isFoodPickMode
                                                    ? `Slot / ${foodpickConfig.slot_interval_minutes || 15} Menit`
                                                    : 'Slot / 30 Menit'}
                                            </span>
                                        </div>

                                        <!-- Date Selector -->
                                        <div>
                                            <label
                                                class="block text-[11px] font-bold text-slate-600 mb-1.5"
                                            >
                                                Tanggal Pengambilan:
                                            </label>
                                            {#if availablePickupDates.length > 0}
                                                <div
                                                    class="flex items-center gap-1.5 overflow-x-auto pb-1.5 scrollbar-thin"
                                                >
                                                    {#each availablePickupDates as d}
                                                        <button
                                                            type="button"
                                                            onclick={() =>
                                                                (pickupDate =
                                                                    d.dateStr)}
                                                            class="px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition-all border cursor-pointer {pickupDate ===
                                                            d.dateStr
                                                                ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs'
                                                                : 'bg-white text-slate-700 border-slate-200 hover:border-emerald-300 hover:bg-emerald-50/40'}"
                                                        >
                                                            {d.label}
                                                        </button>
                                                    {/each}
                                                </div>
                                            {:else}
                                                <p
                                                    class="text-xs text-rose-500 font-medium p-2 bg-rose-50 rounded-xl border border-rose-100"
                                                >
                                                    Toko tidak membuka jadwal
                                                    pengambilan dalam waktu
                                                    dekat.
                                                </p>
                                            {/if}
                                        </div>

                                        <!-- Time Slot Selector -->
                                        <div>
                                            <div
                                                class="flex items-center justify-between mb-1.5"
                                            >
                                                <label
                                                    class="block text-[11px] font-bold text-slate-600"
                                                >
                                                    Jam Pengambilan:
                                                </label>
                                                {#if availablePickupSlots.length > 0}
                                                    <span
                                                        class="text-[10.5px] font-medium text-emerald-700"
                                                    >
                                                        Paling cepat: <strong
                                                            >{availablePickupSlots[0]}</strong
                                                        >
                                                    </span>
                                                {/if}
                                            </div>

                                            {#if availablePickupSlots.length > 0}
                                                <div
                                                    class="grid grid-cols-4 sm:grid-cols-6 gap-1.5 max-h-48 overflow-y-auto p-1.5 bg-white/80 rounded-xl border border-emerald-100"
                                                >
                                                    {#each availablePickupSlots as slot}
                                                        <button
                                                            type="button"
                                                            onclick={() =>
                                                                (pickupTime =
                                                                    slot)}
                                                            class="py-2 px-1 rounded-lg text-xs font-mono font-bold text-center transition border cursor-pointer {pickupTime ===
                                                            slot
                                                                ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs ring-2 ring-emerald-200'
                                                                : 'bg-white text-slate-700 border-slate-200 hover:border-emerald-300 hover:bg-emerald-50'}"
                                                        >
                                                            {slot}
                                                        </button>
                                                    {/each}
                                                </div>
                                            {:else}
                                                <div
                                                    class="p-3 bg-rose-50 rounded-xl border border-rose-100 text-xs text-rose-600 flex items-center gap-1.5"
                                                >
                                                    <i
                                                        class="ti ti-alert-circle text-sm shrink-0"
                                                    ></i>
                                                    <span
                                                        >Tidak ada jam tersisa
                                                        untuk hari ini (melewati
                                                        jam operasional toko).
                                                        Silakan pilih tanggal
                                                        berikutnya.</span
                                                    >
                                                </div>
                                            {/if}
                                        </div>

                                        <!-- Selected Schedule Banner -->
                                        {#if selectedPickupSummary}
                                            <div
                                                class="p-2.5 bg-white rounded-xl border border-emerald-200 flex items-center gap-2 text-xs text-slate-800"
                                            >
                                                <i
                                                    class="ti ti-clock text-emerald-600 text-base shrink-0"
                                                ></i>
                                                <div class="min-w-0">
                                                    <p
                                                        class="text-[10px] font-bold text-slate-400 uppercase tracking-wider leading-none mb-0.5"
                                                    >
                                                        Jadwal Pengambilan
                                                        Dipilih
                                                    </p>
                                                    <p
                                                        class="text-xs font-extrabold text-emerald-800 truncate"
                                                    >
                                                        {selectedPickupSummary}
                                                    </p>
                                                </div>
                                            </div>
                                        {/if}
                                    </div>

                                    <div
                                        class="p-2.5 bg-emerald-50 rounded-xl border border-emerald-100 flex items-center gap-2 text-[11px] font-bold text-emerald-700"
                                    >
                                        <i
                                            class="ti ti-info-circle text-sm shrink-0"
                                        ></i>
                                        <span
                                            >Pesanan Anda akan disiapkan di
                                            toko. Anda dapat mengambilnya sesuai
                                            jadwal di atas setelah status
                                            pembayaran selesai.</span
                                        >
                                    </div>
                                </div>
                            {:else}
                                <div
                                    class="py-2.5 px-4 flex items-center justify-between border-b border-slate-100 rounded-t-2xl"
                                >
                                    <div class="flex items-center gap-2">
                                        <i
                                            class="ti ti-map-pin text-lg"
                                            style="color:{primary}"
                                        ></i>
                                        <span
                                            class="font-bold text-slate-800 text-sm"
                                            >Alamat Pengiriman</span
                                        >
                                    </div>
                                    {#if !isGuest}
                                        <button
                                            onclick={() =>
                                                (showAddressModal = true)}
                                            class="text-xs font-bold px-3 py-1.5 rounded-lg bg-slate-50 hover:bg-slate-100 transition"
                                            style="color:{primary}"
                                        >
                                            Ganti Alamat
                                        </button>
                                    {/if}
                                </div>

                                {#if !isGuest}
                                    {#if selectedAddress}
                                        <div class="py-3 px-4">
                                            <div class="flex items-start gap-2">
                                                <div class="flex-1 min-w-0">
                                                    <div
                                                        class="flex items-center gap-2 flex-wrap mb-1"
                                                    >
                                                        <span
                                                            class="font-bold text-slate-800 text-sm"
                                                            >{selectedAddress.receiver_name}</span
                                                        >
                                                        <span
                                                            class="text-xs text-slate-500 font-medium"
                                                            >{selectedAddress.phone_number}</span
                                                        >
                                                        {#if selectedAddress.is_primary}
                                                            <span
                                                                class="text-[10px] font-black px-2 py-0.5 rounded-full text-white uppercase tracking-wider"
                                                                style="background:{primary}"
                                                                >Utama</span
                                                            >
                                                        {/if}
                                                    </div>
                                                    <p
                                                        class="text-xs text-slate-600 leading-relaxed font-medium"
                                                    >
                                                        {selectedAddress.full_address}
                                                        {#if selectedAddress.village_name},
                                                            {selectedAddress.village_name}{/if}
                                                        {#if selectedAddress.district_name},
                                                            {selectedAddress.district_name}{/if}
                                                        {#if selectedAddress.regency_name},
                                                            {selectedAddress.regency_name}{/if}
                                                        {#if selectedAddress.province_name},
                                                            {selectedAddress.province_name}{/if}
                                                        {#if selectedAddress.postal_code}
                                                            {selectedAddress.postal_code}{/if}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    {:else}
                                        <div class="py-3 px-4">
                                            <div
                                                class="bg-amber-50 border border-amber-200 rounded-xl p-3 text-amber-700 text-sm font-medium"
                                            >
                                                <i
                                                    class="ti ti-alert-triangle mr-1"
                                                ></i>
                                                Belum ada alamat.
                                                <Link
                                                    href="/profile/addresses?from=checkout"
                                                    class="font-black underline"
                                                    >Tambah Alamat</Link
                                                >
                                            </div>
                                        </div>
                                    {/if}
                                {:else}
                                    <!-- Guest Shipping Address Form -->
                                    <div class="p-4 space-y-4">
                                        <!-- Same as Customer toggle -->
                                        <label
                                            class="flex items-center gap-2.5 cursor-pointer select-none"
                                        >
                                            <input
                                                type="checkbox"
                                                bind:checked={
                                                    guestSameAsCustomer
                                                }
                                                class="w-4 h-4 rounded text-emerald-600 focus:ring-emerald-500 border-slate-300"
                                            />
                                            <span
                                                class="text-xs font-semibold text-slate-700"
                                            >
                                                Nama dan No. HP penerima sama
                                                dengan data pemesan {guestName
                                                    ? `(${guestName})`
                                                    : ''}
                                            </span>
                                        </label>

                                        {#if !guestSameAsCustomer}
                                            <div
                                                class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3 bg-slate-50/70 rounded-xl border border-slate-100"
                                            >
                                                <div>
                                                    <label
                                                        class="block text-[11px] font-bold text-slate-700 mb-1"
                                                    >
                                                        Nama Penerima <span
                                                            class="text-rose-500"
                                                            >*</span
                                                        >
                                                    </label>
                                                    <input
                                                        type="text"
                                                        bind:value={
                                                            guestReceiverName
                                                        }
                                                        placeholder="Nama penerima paket"
                                                        class="w-full px-3 py-2 bg-white rounded-xl border border-slate-200 text-xs font-medium focus:outline-none focus:ring-2 focus:ring-slate-300"
                                                    />
                                                </div>
                                                <div>
                                                    <label
                                                        class="block text-[11px] font-bold text-slate-700 mb-1"
                                                    >
                                                        No. HP Penerima <span
                                                            class="text-rose-500"
                                                            >*</span
                                                        >
                                                    </label>
                                                    <input
                                                        type="tel"
                                                        bind:value={
                                                            guestReceiverPhone
                                                        }
                                                        placeholder="Nomor HP aktif penerima"
                                                        class="w-full px-3 py-2 bg-white rounded-xl border border-slate-200 text-xs font-medium focus:outline-none focus:ring-2 focus:ring-slate-300"
                                                    />
                                                </div>
                                            </div>
                                        {/if}

                                        <!-- Full Address -->
                                        <div>
                                            <label
                                                class="block text-xs font-bold text-slate-700 mb-1"
                                            >
                                                Alamat Lengkap (Jalan, No.
                                                Rumah, RT/RW, Patokan) <span
                                                    class="text-rose-500"
                                                    >*</span
                                                >
                                            </label>
                                            <textarea
                                                bind:value={guestFullAddress}
                                                rows="2"
                                                placeholder="Contoh: Jl. Raya Darmo No. 45, RT 02 / RW 05, Kel. Wonokromo, dekat minimarket"
                                                class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-medium focus:outline-none focus:ring-2 focus:ring-slate-300 resize-none leading-relaxed"
                                                required
                                            ></textarea>
                                        </div>

                                        <!-- Search Destination / Area -->
                                        <div
                                            class="destination-search-container relative"
                                        >
                                            <div
                                                class="flex items-center justify-between mb-1.5"
                                            >
                                                <label
                                                    class="block text-xs font-bold text-slate-700"
                                                >
                                                    Cari Kota / Kecamatan / Kode
                                                    Pos Tujuan <span
                                                        class="text-rose-500"
                                                        >*</span
                                                    >
                                                </label>
                                                <div
                                                    class="flex items-center gap-2"
                                                >
                                                    <button
                                                        type="button"
                                                        onclick={detectGuestGps}
                                                        disabled={isDetectingGuestGps}
                                                        class="text-[11px] font-bold text-blue-600 hover:text-blue-700 flex items-center gap-1 cursor-pointer disabled:opacity-50"
                                                    >
                                                        <i
                                                            class="ti {isDetectingGuestGps
                                                                ? 'ti-loader-2 animate-spin'
                                                                : 'ti-current-location'}"
                                                        ></i>
                                                        <span
                                                            >{isDetectingGuestGps
                                                                ? 'Mendeteksi...'
                                                                : 'Pin Lokasi GPS'}</span
                                                        >
                                                    </button>
                                                    {#if guestLat && guestLng}
                                                        <span
                                                            class="text-[10px] text-emerald-700 font-semibold bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200"
                                                        >
                                                            ✓ GPS Aktif
                                                        </span>
                                                    {/if}
                                                </div>
                                            </div>
                                            <div class="relative">
                                                <i
                                                    class="ti ti-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-base pointer-events-none"
                                                ></i>
                                                <input
                                                    type="text"
                                                    value={destinationQuery}
                                                    onfocus={() => {
                                                        if (
                                                            destinationResults.length >
                                                            0
                                                        ) {
                                                            showDestinationDropdown = true;
                                                        }
                                                    }}
                                                    oninput={(e) =>
                                                        handleDestinationSearch(
                                                            (
                                                                e.target as HTMLInputElement
                                                            ).value,
                                                        )}
                                                    placeholder="Contoh: Wonokromo Surabaya, atau kode pos 60245..."
                                                    class="w-full pl-10 pr-10 py-2.5 bg-white rounded-xl border border-slate-200 text-xs font-medium focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-500/10 transition shadow-2xs"
                                                />
                                                {#if isSearchingDestination}
                                                    <div
                                                        class="absolute right-3.5 top-1/2 -translate-y-1/2 flex items-center"
                                                    >
                                                        <i
                                                            class="ti ti-loader-2 animate-spin text-blue-600 text-sm"
                                                        ></i>
                                                    </div>
                                                {:else if destinationQuery}
                                                    <button
                                                        type="button"
                                                        onclick={clearDestination}
                                                        class="w-6 h-6 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-400 hover:text-slate-600 transition absolute right-2.5 top-1/2 -translate-y-1/2 flex items-center justify-center cursor-pointer"
                                                        aria-label="Hapus tujuan"
                                                    >
                                                        <i
                                                            class="ti ti-x text-xs"
                                                        ></i>
                                                    </button>
                                                {/if}

                                                <!-- Autocomplete Dropdown -->
                                                {#if showDestinationDropdown && destinationResults.length > 0}
                                                    <div
                                                        class="absolute left-0 right-0 top-full mt-2 z-50 bg-white rounded-2xl shadow-2xl shadow-slate-900/15 border border-slate-200/90 overflow-hidden ring-1 ring-black/5"
                                                    >
                                                        <div
                                                            class="px-3.5 py-2 bg-slate-50 border-b border-slate-100 flex items-center justify-between text-[11px] font-bold text-slate-500"
                                                        >
                                                            <span
                                                                class="flex items-center gap-1.5 text-slate-700"
                                                            >
                                                                <i
                                                                    class="ti ti-map-pin text-blue-600"
                                                                ></i>
                                                                Hasil Pencarian ({destinationResults.length})
                                                            </span>
                                                            <span
                                                                class="text-[10px] text-slate-400 font-medium"
                                                                >Klik untuk
                                                                memilih</span
                                                            >
                                                        </div>
                                                        <div
                                                            class="max-h-60 overflow-y-auto divide-y divide-slate-100 overscroll-contain"
                                                        >
                                                            {#each destinationResults as item}
                                                                <button
                                                                    type="button"
                                                                    onclick={() =>
                                                                        selectDestination(
                                                                            item,
                                                                        )}
                                                                    class="w-full text-left px-3.5 py-2.5 hover:bg-blue-50/50 transition-colors flex items-center justify-between gap-3 group cursor-pointer"
                                                                >
                                                                    <div
                                                                        class="flex items-start gap-2.5 min-w-0"
                                                                    >
                                                                        <div
                                                                            class="w-7 h-7 rounded-lg bg-slate-100 text-slate-500 flex items-center justify-center shrink-0 mt-0.5 group-hover:bg-blue-100 group-hover:text-blue-600 transition"
                                                                        >
                                                                            <i
                                                                                class="ti ti-map-pin text-xs"

                                                                            ></i>
                                                                        </div>
                                                                        <div
                                                                            class="min-w-0 flex-1"
                                                                        >
                                                                            <p
                                                                                class="text-xs font-bold text-slate-800 group-hover:text-blue-700 transition truncate"
                                                                            >
                                                                                {item.label ||
                                                                                    `${item.district_name || ''}, ${item.city_name || ''}`}
                                                                            </p>
                                                                            <p
                                                                                class="text-[11px] text-slate-500 truncate mt-0.5 flex items-center gap-1.5 flex-wrap"
                                                                            >
                                                                                <span
                                                                                    >{item.district_name
                                                                                        ? `${item.district_name}, `
                                                                                        : ''}{item.city_name ||
                                                                                        ''}</span
                                                                                >
                                                                                {#if item.postal_code || item.zip_code}
                                                                                    <span
                                                                                        class="inline-block px-1.5 py-0.5 rounded-md bg-slate-100 text-slate-600 font-mono text-[10px] font-semibold border border-slate-200/60"
                                                                                    >
                                                                                        {item.postal_code ||
                                                                                            item.zip_code}
                                                                                    </span>
                                                                                {/if}
                                                                            </p>
                                                                        </div>
                                                                    </div>
                                                                    <span
                                                                        class="text-xs font-bold text-emerald-700 bg-emerald-50 group-hover:bg-emerald-600 group-hover:text-white px-2.5 py-1 rounded-lg shrink-0 transition flex items-center gap-1 border border-emerald-200 group-hover:border-transparent"
                                                                    >
                                                                        Pilih <i
                                                                            class="ti ti-check text-[10px]"
                                                                        ></i>
                                                                    </span>
                                                                </button>
                                                            {/each}
                                                        </div>
                                                    </div>
                                                {/if}
                                            </div>

                                            <!-- Destination Selected Confirmation Banner -->
                                            {#if guestRegencyName || guestDistrictName}
                                                <div
                                                    class="mt-2.5 p-3 bg-gradient-to-r from-emerald-50 to-teal-50/40 rounded-2xl border border-emerald-200/80 flex items-center justify-between gap-3 shadow-2xs"
                                                >
                                                    <div
                                                        class="flex items-center gap-2.5 min-w-0"
                                                    >
                                                        <div
                                                            class="w-8 h-8 rounded-xl bg-emerald-600 text-white flex items-center justify-center shrink-0 shadow-xs"
                                                        >
                                                            <i
                                                                class="ti ti-map-pin-check text-base"
                                                            ></i>
                                                        </div>
                                                        <div class="min-w-0">
                                                            <div
                                                                class="flex items-center gap-1.5"
                                                            >
                                                                <span
                                                                    class="text-[10px] uppercase font-black text-emerald-800 tracking-wider"
                                                                >
                                                                    Lokasi
                                                                    Pengiriman
                                                                    Terpilih
                                                                </span>
                                                                <span
                                                                    class="w-1.5 h-1.5 rounded-full bg-emerald-500"
                                                                ></span>
                                                            </div>
                                                            <p
                                                                class="text-xs font-bold text-slate-800 truncate mt-0.5"
                                                            >
                                                                {guestDistrictName
                                                                    ? `${guestDistrictName}, `
                                                                    : ''}{guestRegencyName}
                                                                {#if guestPostalCode}
                                                                    <span
                                                                        class="font-mono text-emerald-700 font-bold"
                                                                        >({guestPostalCode})</span
                                                                    >
                                                                {/if}
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <button
                                                        type="button"
                                                        onclick={clearDestination}
                                                        class="px-3 py-1.5 text-xs font-bold text-rose-600 hover:text-white hover:bg-rose-600 rounded-xl transition border border-rose-200 bg-white shadow-2xs shrink-0 flex items-center gap-1 cursor-pointer"
                                                    >
                                                        <i
                                                            class="ti ti-edit text-xs"
                                                        ></i>
                                                        Ubah
                                                    </button>
                                                </div>
                                            {/if}
                                        </div>

                                        <!-- Toggle for Manual Regional Inputs -->
                                        <div>
                                            <button
                                                type="button"
                                                onclick={() =>
                                                    (showManualRegionalFields =
                                                        !showManualRegionalFields)}
                                                class="text-[11px] font-bold text-slate-500 hover:text-slate-800 flex items-center gap-1 transition"
                                            >
                                                <i
                                                    class="ti ti-chevron-{showManualRegionalFields
                                                        ? 'up'
                                                        : 'down'} text-xs"
                                                ></i>
                                                {showManualRegionalFields
                                                    ? 'Sembunyikan detail manual'
                                                    : 'Atur detail wilayah (Provinsi, Kota, Kecamatan, Kode Pos) secara manual'}
                                            </button>

                                            {#if showManualRegionalFields}
                                                <div
                                                    class="mt-2.5 grid grid-cols-2 gap-2.5 p-3 bg-slate-50/70 rounded-xl border border-slate-100"
                                                >
                                                    <div>
                                                        <label
                                                            class="block text-[10.5px] font-bold text-slate-600 mb-1"
                                                        >
                                                            Provinsi
                                                        </label>
                                                        <input
                                                            type="text"
                                                            bind:value={
                                                                guestProvinceName
                                                            }
                                                            placeholder="Contoh: Jawa Timur"
                                                            class="w-full px-2.5 py-1.5 bg-white rounded-lg border border-slate-200 text-xs font-medium focus:outline-none focus:ring-1 focus:ring-slate-300"
                                                        />
                                                    </div>
                                                    <div>
                                                        <label
                                                            class="block text-[10.5px] font-bold text-slate-600 mb-1"
                                                        >
                                                            Kota / Kabupaten <span
                                                                class="text-rose-500"
                                                                >*</span
                                                            >
                                                        </label>
                                                        <input
                                                            type="text"
                                                            bind:value={
                                                                guestRegencyName
                                                            }
                                                            placeholder="Contoh: Kota Surabaya"
                                                            class="w-full px-2.5 py-1.5 bg-white rounded-lg border border-slate-200 text-xs font-medium focus:outline-none focus:ring-1 focus:ring-slate-300"
                                                        />
                                                    </div>
                                                    <div>
                                                        <label
                                                            class="block text-[10.5px] font-bold text-slate-600 mb-1"
                                                        >
                                                            Kecamatan
                                                        </label>
                                                        <input
                                                            type="text"
                                                            bind:value={
                                                                guestDistrictName
                                                            }
                                                            placeholder="Contoh: Wonokromo"
                                                            class="w-full px-2.5 py-1.5 bg-white rounded-lg border border-slate-200 text-xs font-medium focus:outline-none focus:ring-1 focus:ring-slate-300"
                                                        />
                                                    </div>
                                                    <div>
                                                        <label
                                                            class="block text-[10.5px] font-bold text-slate-600 mb-1"
                                                        >
                                                            Kode Pos <span
                                                                class="text-rose-500"
                                                                >*</span
                                                            >
                                                        </label>
                                                        <input
                                                            type="text"
                                                            bind:value={
                                                                guestPostalCode
                                                            }
                                                            placeholder="Contoh: 60245"
                                                            class="w-full px-2.5 py-1.5 bg-white rounded-lg border border-slate-200 text-xs font-medium focus:outline-none focus:ring-1 focus:ring-slate-300"
                                                        />
                                                    </div>
                                                </div>
                                            {/if}
                                        </div>
                                    </div>
                                {/if}
                            {/if}
                        </div>
                    {/if}

                    <ProductListSection
                        {primary}
                        {cartItems}
                        {subtotal}
                        {eligibleBundlingPromos}
                        bind:itemNotes
                        {fmt}
                        {getCartItemImage}
                        {formatImagePath}
                    />

                    {#if !isDigitalOnly}
                        <!-- Shipping Section -->
                        <div
                            class="bg-white rounded-2xl shadow-sm border border-slate-100"
                        >
                            <div class="px-4 pt-4 pb-3">
                                <div class="flex items-center gap-2 mb-3">
                                    <i
                                        class="ti ti-truck text-lg"
                                        style="color:{primary}"
                                    ></i>
                                    <span
                                        class="font-bold text-slate-800 text-sm"
                                        >Pengiriman</span
                                    >
                                </div>

                                {#if selectedCourier !== 'self_pickup' && !selectedAddress}
                                    <p class="text-xs text-slate-400 italic">
                                        {isGuest
                                            ? 'Isi alamat dan pilih kota/kecamatan tujuan di atas terlebih dahulu untuk mengecek ongkir.'
                                            : 'Pilih alamat terlebih dahulu untuk mengecek ongkir.'}
                                    </p>
                                {:else}
                                    <!-- International Shipping Option -->
                                    <!-- <div class="mb-4 p-3.5 bg-slate-50 border border-slate-100 rounded-2xl">
                                    <label class="flex items-center justify-between cursor-pointer">
                                        <div class="flex flex-col">
                                            <span class="text-xs font-bold text-slate-800">Kirim ke Luar Negeri?</span>
                                            <span class="text-[10px] text-slate-500">Gunakan Komerce RajaOngkir Internasional</span>
                                        </div>
                                        <input
                                            type="checkbox"
                                            bind:checked={isInternational}
                                            class="rounded text-indigo-600 focus:ring-indigo-500 h-4 w-4"
                                            onclick={() => {
                                                selectedShipping = null;
                                                shippingOptions = [];
                                                selectedCourier = '';
                                                selectedCountryId = '';
                                            }}
                                        />
                                    </label>
                                    
                                    {#if isInternational}
                                        <div class="mt-3">
                                            <p class="text-xs font-semibold text-slate-500 mb-1.5">Pilih Negara Tujuan</p>
                                            <div class="relative">
                                                <select
                                                    bind:value={selectedCountryId}
                                                    onchange={() => {
                                                        selectedShipping = null;
                                                        shippingOptions = [];
                                                        if (selectedCourier) fetchShipping();
                                                    }}
                                                    class="w-full p-2.5 bg-white border border-slate-200 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-slate-100 transition-all font-medium text-slate-700"
                                                >
                                                    <option value="">-- Pilih Negara --</option>
                                                    {#each internationalCountries as country}
                                                        <option value={country.country_id}>{country.country_name}</option>
                                                    {/each}
                                                </select>
                                                {#if loadingCountries}
                                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] text-slate-400">Loading...</span>
                                                {/if}
                                            </div>
                                        </div>
                                    {/if}
                                </div> -->

                                    <!-- FoodPick Direct Fulfillment Selector -->
                                    {#if isFoodPickMode}
                                        <div class="mb-4">
                                            <p
                                                class="text-xs font-semibold text-slate-500 mb-2"
                                            >
                                                Pilihan Pemenuhan Makanan
                                            </p>
                                            <div
                                                class="grid grid-cols-2 gap-2.5"
                                            >
                                                <button
                                                    type="button"
                                                    onclick={() => {
                                                        selectedCourier =
                                                            'store_courier';
                                                        shippingError = '';
                                                        fetchShipping();
                                                    }}
                                                    class="flex flex-col items-center justify-center gap-1.5 p-3.5 rounded-2xl border text-center transition-all cursor-pointer {selectedCourier ===
                                                    'store_courier'
                                                        ? 'border-transparent text-white shadow-sm ring-2 ring-offset-2'
                                                        : 'border-slate-200 bg-slate-50/70 text-slate-700 hover:bg-slate-100 hover:border-slate-300'}"
                                                    style={selectedCourier ===
                                                    'store_courier'
                                                        ? `background-color:${primary}; --tw-ring-color:${primary};`
                                                        : ''}
                                                >
                                                    <i
                                                        class="ti ti-moped text-xl"
                                                    ></i>
                                                    <span
                                                        class="text-xs font-bold leading-tight"
                                                        >Diantar Kurir Resto</span
                                                    >
                                                    <span
                                                        class="text-[10.5px] opacity-80 font-medium"
                                                        >Diantar ke alamat Anda</span
                                                    >
                                                </button>

                                                <button
                                                    type="button"
                                                    onclick={() => {
                                                        selectedCourier =
                                                            'self_pickup';
                                                        shippingError = '';
                                                        fetchShipping();
                                                    }}
                                                    class="flex flex-col items-center justify-center gap-1.5 p-3.5 rounded-2xl border text-center transition-all cursor-pointer {selectedCourier ===
                                                    'self_pickup'
                                                        ? 'border-transparent text-white shadow-sm ring-2 ring-offset-2'
                                                        : 'border-slate-200 bg-slate-50/70 text-slate-700 hover:bg-slate-100 hover:border-slate-300'}"
                                                    style={selectedCourier ===
                                                    'self_pickup'
                                                        ? `background-color:${primary}; --tw-ring-color:${primary};`
                                                        : ''}
                                                >
                                                    <i
                                                        class="ti ti-shopping-bag text-xl"
                                                    ></i>
                                                    <span
                                                        class="text-xs font-bold leading-tight"
                                                        >Ambil di Resto</span
                                                    >
                                                    <span
                                                        class="text-[10.5px] opacity-80 font-medium"
                                                        >Takeaway / Dine-In</span
                                                    >
                                                </button>
                                            </div>
                                        </div>
                                    {/if}

                                    <!-- Courier selection -->
                                    <div
                                        class="mb-3 relative courier-select-container {isFoodPickMode
                                            ? 'hidden'
                                            : ''}"
                                    >
                                        <div
                                            class="flex items-center justify-between mb-2"
                                        >
                                            <p
                                                class="text-xs font-semibold text-slate-500"
                                            >
                                                Pilih Kurir
                                            </p>
                                            {#if totalWeightGrams > 0}
                                                <span
                                                    class="text-[10px] font-semibold text-slate-500 bg-slate-100 px-2 py-0.5 rounded-full"
                                                >
                                                    Berat: {(
                                                        totalWeightGrams / 1000
                                                    ).toFixed(2)} kg ({totalWeightGrams.toLocaleString()}g)
                                                </span>
                                            {/if}
                                        </div>

                                        <!-- Dropdown Trigger -->
                                        <button
                                            onclick={() =>
                                                (showCourierDropdown =
                                                    !showCourierDropdown)}
                                            class="w-full flex items-center justify-between p-3.5 rounded-2xl border transition-all duration-200 text-left bg-slate-50/50 hover:bg-slate-50 hover:border-slate-200 active:scale-[0.99] {showCourierDropdown
                                                ? 'border-slate-300 bg-white ring-2 ring-slate-100'
                                                : 'border-slate-100'}"
                                        >
                                            <div
                                                class="flex items-center gap-2.5"
                                            >
                                                <i
                                                    class="ti ti-truck text-base text-slate-500"
                                                ></i>
                                                {#if selectedCourier}
                                                    <span
                                                        class="text-xs font-bold text-slate-800"
                                                    >
                                                        {courierLabels[
                                                            selectedCourier
                                                        ] ??
                                                            selectedCourier.toUpperCase()}
                                                    </span>
                                                {:else}
                                                    <span
                                                        class="text-xs font-medium text-slate-400"
                                                    >
                                                        Pilih kurir
                                                        pengiriman...
                                                    </span>
                                                {/if}
                                            </div>
                                            <i
                                                class="ti ti-chevron-down text-xs text-slate-400 transition-transform duration-200 {showCourierDropdown
                                                    ? 'rotate-180'
                                                    : ''}"
                                            ></i>
                                        </button>

                                        <!-- Dropdown Panel -->
                                        {#if showCourierDropdown}
                                            <div
                                                class="absolute z-50 left-0 right-0 mt-1.5 bg-white rounded-2xl border border-slate-100 shadow-xl overflow-hidden"
                                                transition:fade={{
                                                    duration: 100,
                                                }}
                                            >
                                                <!-- Search input -->
                                                <div
                                                    class="p-2 border-b border-slate-50 sticky top-0 bg-white z-10"
                                                >
                                                    <div class="relative">
                                                        <i
                                                            class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"
                                                        ></i>
                                                        <input
                                                            type="text"
                                                            bind:value={
                                                                searchCourierQuery
                                                            }
                                                            placeholder="Cari kurir..."
                                                            class="w-full pl-9 pr-8 py-2 bg-slate-50 border border-slate-100 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-slate-100 transition-all font-medium text-slate-700"
                                                        />
                                                        {#if searchCourierQuery}
                                                            <button
                                                                aria-label="Tutup pencarian"
                                                                onclick={() =>
                                                                    (searchCourierQuery =
                                                                        '')}
                                                                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 p-0.5"
                                                            >
                                                                <i
                                                                    class="ti ti-x text-xs"
                                                                ></i>
                                                            </button>
                                                        {/if}
                                                    </div>
                                                </div>

                                                <!-- Options List -->
                                                <div
                                                    class="max-h-60 overflow-y-auto p-1.5 space-y-0.5 scrollbar-thin"
                                                >
                                                    {#each visibleCouriers.filter( (c) => (courierLabels[c] ?? c)
                                                                .toLowerCase()
                                                                .includes(searchCourierQuery.toLowerCase()) ) as courier}
                                                        <button
                                                            onclick={() => {
                                                                selectedCourier =
                                                                    courier;
                                                                showCourierDropdown = false;
                                                                searchCourierQuery =
                                                                    '';
                                                                fetchShipping();
                                                            }}
                                                            class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-left text-xs font-semibold transition active:scale-[0.99] {selectedCourier ===
                                                            courier
                                                                ? 'text-white font-bold shadow-sm'
                                                                : 'text-slate-700 hover:bg-slate-50'}"
                                                            style={selectedCourier ===
                                                            courier
                                                                ? `background-color:${primary};`
                                                                : ''}
                                                        >
                                                            <span
                                                                >{courierLabels[
                                                                    courier
                                                                ] ??
                                                                    courier.toUpperCase()}</span
                                                            >
                                                            {#if selectedCourier === courier}
                                                                <i
                                                                    class="ti ti-check text-xs font-bold text-white"
                                                                ></i>
                                                            {/if}
                                                        </button>
                                                    {:else}
                                                        <div
                                                            class="py-6 text-center text-xs text-slate-400 flex flex-col items-center justify-center gap-1.5"
                                                        >
                                                            <i
                                                                class="ti ti-search-off text-lg text-slate-300"
                                                            ></i>
                                                            <span
                                                                >{searchCourierQuery
                                                                    ? `Kurir "${searchCourierQuery}" tidak ditemukan`
                                                                    : 'Semua kurir tidak tersedia untuk alamat ini'}</span
                                                            >
                                                        </div>
                                                    {/each}
                                                </div>
                                            </div>
                                        {/if}
                                    </div>

                                    {#if loadingShipping}
                                        <div class="space-y-2 mt-3">
                                            {#each [1, 2, 3] as _}
                                                <div
                                                    class="h-16 bg-slate-50 border border-slate-100 rounded-2xl animate-pulse flex items-center justify-between px-4"
                                                >
                                                    <div
                                                        class="space-y-2 w-2/3"
                                                    >
                                                        <div
                                                            class="h-4 bg-slate-200/80 rounded-md w-1/2"
                                                        ></div>
                                                        <div
                                                            class="h-3 bg-slate-200/80 rounded-md w-3/4"
                                                        ></div>
                                                    </div>
                                                    <div
                                                        class="h-5 bg-slate-200/80 rounded-md w-16"
                                                    ></div>
                                                </div>
                                            {/each}
                                        </div>
                                    {:else if shippingError}
                                        <div
                                            class="mt-3 p-3 bg-red-50 border border-red-100 text-red-600 rounded-2xl text-xs font-medium flex items-center gap-2"
                                        >
                                            <i
                                                class="ti ti-alert-circle text-base"
                                            ></i>
                                            <span>{shippingError}</span>
                                        </div>
                                    {:else if shippingOptions.length > 0}
                                        <div
                                            class="space-y-2 mt-3 max-h-[350px] overflow-y-auto scrollbar-thin pr-1"
                                        >
                                            {#each shippingOptions as opt}
                                                <button
                                                    onclick={() =>
                                                        (selectedShipping =
                                                            opt)}
                                                    class="w-full flex items-center justify-between gap-4 p-3.5 rounded-2xl border transition-all duration-200 text-left active:scale-[0.99] {selectedShipping?.service ===
                                                    opt.service
                                                        ? 'shadow-sm border-transparent'
                                                        : 'border-slate-100 hover:border-slate-200 bg-slate-50/30'}"
                                                    style={selectedShipping?.service ===
                                                    opt.service
                                                        ? `border-color:${primary}; background-color:${primary}08`
                                                        : ''}
                                                >
                                                    <div
                                                        class="flex items-center gap-3 flex-grow min-w-0"
                                                    >
                                                        <div
                                                            class="w-5 h-5 rounded-full border flex items-center justify-center transition-all duration-200 shrink-0"
                                                            style={selectedShipping?.service ===
                                                            opt.service
                                                                ? `border-color:${primary}; background-color:${primary}`
                                                                : 'border-slate-300 bg-white'}
                                                        >
                                                            {#if selectedShipping?.service === opt.service}
                                                                <i
                                                                    class="ti ti-check text-[10px] text-white"
                                                                ></i>
                                                            {/if}
                                                        </div>
                                                        <div
                                                            class="min-w-0 flex-grow"
                                                        >
                                                            <div
                                                                class="flex items-center gap-1.5 flex-wrap"
                                                            >
                                                                <span
                                                                    class="uppercase px-1.5 py-0.5 rounded bg-slate-100 text-[9px] font-bold text-slate-500 tracking-wider shrink-0"
                                                                >
                                                                    {courierLabels[
                                                                        selectedCourier
                                                                    ] ||
                                                                        selectedCourier}
                                                                </span>
                                                                <span
                                                                    class="text-sm font-bold text-slate-800"
                                                                >
                                                                    {opt.service}
                                                                </span>
                                                            </div>
                                                            <p
                                                                class="text-xs text-slate-500 mt-1 font-medium leading-relaxed"
                                                            >
                                                                {opt.description}
                                                            </p>
                                                            <p
                                                                class="text-[10px] text-slate-400 mt-1 flex items-center gap-1 font-medium"
                                                            >
                                                                <i
                                                                    class="ti ti-clock text-xs"
                                                                ></i>
                                                                <span
                                                                    >{getEtdLabel(
                                                                        opt
                                                                            .cost?.[0]
                                                                            ?.etd,
                                                                        opt.service ||
                                                                            opt.description,
                                                                    )}</span
                                                                >
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div
                                                        class="text-right shrink-0"
                                                    >
                                                        <p
                                                            class="text-sm font-bold"
                                                            style="color:{primary}"
                                                        >
                                                            {fmt(
                                                                opt.cost?.[0]
                                                                    ?.value ??
                                                                    0,
                                                            )}
                                                        </p>
                                                    </div>
                                                </button>
                                            {/each}
                                        </div>
                                    {:else if selectedCourier}
                                        <div
                                            class="flex flex-col items-center justify-center py-6 px-4 bg-slate-50/50 rounded-2xl border border-dashed border-slate-200 text-center mt-3"
                                        >
                                            <div
                                                class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center mb-2 animate-bounce"
                                            >
                                                <i
                                                    class="ti ti-loader text-lg text-slate-500"
                                                ></i>
                                            </div>
                                            <p
                                                class="text-xs font-medium text-slate-500"
                                            >
                                                Memilih kurir untuk melihat
                                                layanan...
                                            </p>
                                        </div>
                                    {:else}
                                        <div
                                            class="flex flex-col items-center justify-center py-6 px-4 bg-slate-50/50 rounded-2xl border border-dashed border-slate-200 text-center mt-3"
                                        >
                                            <div
                                                class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center mb-2"
                                            >
                                                <i
                                                    class="ti ti-truck-delivery text-lg text-slate-400"
                                                ></i>
                                            </div>
                                            <p
                                                class="text-xs font-medium text-slate-500"
                                            >
                                                Pilih kurir di atas untuk
                                                melihat pilihan layanan &
                                                ongkir.
                                            </p>
                                        </div>
                                    {/if}
                                {/if}
                            </div>
                        </div>
                    {/if}

                    <VoucherSection
                        {primary}
                        {visibleVouchers}
                        {activeAppliedVouchers}
                        {shippingDiscount}
                        {discountAmount}
                        {voucherError}
                        {fmt}
                        {withOpacity}
                        onOpen={() => (voucherModalOpen = true)}
                        {deselectVoucher}
                    />

                    <LoyaltyPointsSection
                        bind:useCoins
                        {coinsEnabled}
                        {isGuest}
                        {userCoinsBalance}
                        {userCoinsValue}
                        {isCoinsMinPurchaseMet}
                        {maxCoinsRedeemAllowed}
                        {coinMinPurchaseRedeem}
                        {subtotal}
                        {coinDiscountAmount}
                        {coinConversionRate}
                        {coinMaxRedeemPercentage}
                        {coinMaxRedeemPerTxn}
                        {prospectiveCoinsEarned}
                        {appliedVoucher}
                        {formatNumber}
                    />
                    <OrderNotesSection {primary} bind:orderNotes />
                </div>

                <!-- Right Column: Payment Method + Summary (desktop only) -->
                <div class="space-y-4">
                    <PaymentMethodsSection
                        {primary}
                        {paymentMethods}
                        {midtransEnabledMethods}
                        {selectedPaymentMethod}
                        {midtransMethodColors}
                        {midtransMethodIcons}
                        bind:selectedPaymentMethodId
                        bind:selectedMidtransMethodKey
                    />

                    <OrderSummarySection
                        {primary}
                        {subtotal}
                        {discountAmount}
                        {coinDiscountAmount}
                        {isDigitalOnly}
                        {selectedShipping}
                        {shippingFee}
                        {shippingDiscount}
                        {adminFee}
                        {applicationFee}
                        {activeAdditionalCosts}
                        {grandTotal}
                        {isCheckoutLocked}
                        {checkoutLockedMessage}
                        {isStoreOpen}
                        {isSubmitting}
                        {isCheckoutDisabled}
                        {fmt}
                        {submitCheckout}
                    />
                </div>
            </div>
        </div>

        <MobileCheckoutBar
            {primary}
            {grandTotal}
            {isSubmitting}
            {isCheckoutDisabled}
            {fmt}
            {submitCheckout}
        />
    </div>

    {#if showAddressModal}
        <AddressModal
            {addresses}
            {selectedAddressId}
            {primary}
            onClose={() => (showAddressModal = false)}
            onSelect={(addressId) => {
                selectedAddressId = addressId;
                showAddressModal = false;
                selectedShipping = null;
                shippingOptions = [];
            }}
        />
    {/if}
    <VoucherModal
        bind:voucherModalOpen
        bind:manualPromoCode
        {primary}
        {voucherLoading}
        {visibleVouchers}
        {shippingVouchers}
        {discountVouchers}
        {subtotal}
        {appliedVoucher}
        {voucherInputCode}
        {expandedTerms}
        {userIsNew}
        {applyVoucher}
        {openScanModal}
        {withOpacity}
        {fmt}
        {toggleTerms}
        {deselectVoucher}
        {selectVoucher}
        {formatNumber}
    />

    {#if showScanModal}
        <ScannerModal
            {primary}
            {scanError}
            {isScanning}
            {startScanning}
            {closeScanModal}
        />
    {/if}
</StorefrontLayout>
