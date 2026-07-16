<script lang="ts">
    import { onMount } from 'svelte';
    import { useForm, router, page, Link } from '@inertiajs/svelte';

    const primaryColor = $derived(page.props.theme?.primary_color || '#0c4cb4');
    const secondaryColor = $derived(
        page.props.theme?.secondary_color || '#fa7315',
    );
    const isLogisticEnabled = $derived((page.props as any).settings?.logistic_enabled ?? true);
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { slide, fade } from 'svelte/transition';
    import { showToast } from '@/utils/toast';

    import Input from '@/components/ui/Input.svelte';
    import Textarea from '@/components/ui/Textarea.svelte';
    import Toggle from '@/components/ui/Toggle.svelte';
    import SelectSearch from '@/components/ui/SelectSearch.svelte';
    import ColorPicker from '@/components/ui/ColorPicker.svelte';
    import InputCurrency from '@/components/ui/InputCurrency.svelte';

    let { settings = {}, env_keys = {}, couriers = [] } = $props();

    // svelte-ignore state_referenced_locally
    const form = useForm({
        store_logo: null,
        store_icon: null,
        store_name: settings.store_name || '',
        store_email: settings.store_email || '',
        store_phone: settings.store_phone || '',
        store_whatsapp: settings.store_whatsapp || '',
        store_instagram: settings.store_instagram || '',
        store_tiktok: settings.store_tiktok || '',
        store_description: settings.store_description || '',

        primary_color: settings.primary_color || '#0c4cb4',
        secondary_color: settings.secondary_color || '#fa7315',
        store_font: settings.store_font || 'Plus Jakarta Sans',

        tax_enabled:
            settings.tax_enabled === 'true' ||
            settings.tax_enabled === true ||
            settings.tax_enabled === '1',
        tax_percentage: settings.tax_percentage || 0,

        province_id: settings.province_id || '',
        province_name: settings.province_name || '',
        regency_id: settings.regency_id || '',
        regency_name: settings.regency_name || '',
        district_id: settings.district_id || '',
        district_name: settings.district_name || '',
        village_id: settings.village_id || '',
        village_name: settings.village_name || '',
        postal_code: settings.postal_code || '',
        address: settings.address || '',
        latitude: settings.latitude || '-6.2088',
        longitude: settings.longitude || '106.8456',

        bank_name: settings.bank_name || '',
        bank_account: settings.bank_account || '',
        bank_holder: settings.bank_holder || '',
        shipping_rate: settings.shipping_rate || '',
        bank_name: settings.bank_name || '',
        bank_account: settings.bank_account || '',
        bank_holder: settings.bank_holder || '',
        shipping_rate: settings.shipping_rate || '',

        rajaongkir_url:
            settings.rajaongkir_url || 'https://rajaongkir.komerce.id/api/v1/',
        rajaongkir_shipping_cost:
            settings.rajaongkir_shipping_cost ||
            '390d25e9d86ded71cb771c363778cccf',

        shipping_delivery_key:
            settings.shipping_delivery_key ||
            'sdfh2Qgp5a2e20929ec5ff822tkkgf6S',
        payment_api_key:
            settings.payment_api_key || 'sdfh2Qgp5a2e20929ec5ff822tkkgf6S',
        qrisly_api_key:
            settings.qrisly_api_key || 'sdfh2Qgp5a2e20929ec5ff822tkkgf6S',

        shipping_delivery_enabled:
            settings.shipping_delivery_enabled === 'true' ||
            settings.shipping_delivery_enabled === true ||
            settings.shipping_delivery_enabled === '1',
        biteship_enabled:
            settings.biteship_enabled === 'true' ||
            settings.biteship_enabled === true ||
            settings.biteship_enabled === '1',
        biteship_url: settings.biteship_url || 'https://api.biteship.com/v1/',
        biteship_secret_key: settings.biteship_secret_key || '',
        payment_api_enabled:
            settings.payment_api_enabled === 'true' ||
            settings.payment_api_enabled === true ||
            settings.payment_api_enabled === '1',
        payment_api_admin_fee: settings.payment_api_admin_fee || 0,
        qrisly_api_enabled:
            settings.qrisly_api_enabled === 'true' ||
            settings.qrisly_api_enabled === true ||
            settings.qrisly_api_enabled === '1',
        qrisly_api_admin_fee: settings.qrisly_api_admin_fee || 0,
        midtrans_api_enabled:
            settings.midtrans_api_enabled === 'true' ||
            settings.midtrans_api_enabled === true ||
            settings.midtrans_api_enabled === '1',
        xendit_api_enabled:
            settings.xendit_api_enabled === 'true' ||
            settings.xendit_api_enabled === true ||
            settings.xendit_api_enabled === '1',
        komerce_delivery_url:
            settings.komerce_delivery_url ||
            'https://api-sandbox.collaborator.komerce.id/api/v1/',

        storefront_cart_button_style:
            settings.storefront_cart_button_style || 'button',
        enable_cod:
            settings.enable_cod === 'true' ||
            settings.enable_cod === true ||
            settings.enable_cod === '1',

        coins_enabled:
            settings.coins_enabled === 'true' ||
            settings.coins_enabled === true ||
            settings.coins_enabled === '1',
        coin_conversion_rate: settings.coin_conversion_rate || 1,
        coin_earning_method: settings.coin_earning_method || 'proportional',
        coin_earning_rate_rupiah: settings.coin_earning_rate_rupiah || 1000,
        coin_earning_rate_coins: settings.coin_earning_rate_coins || 1,
        coin_earning_tiers: Array.isArray(settings.coin_earning_tiers)
            ? settings.coin_earning_tiers
            : settings.coin_earning_tiers
              ? JSON.parse(settings.coin_earning_tiers)
              : [],
        coin_min_purchase_redeem: settings.coin_min_purchase_redeem || 0,
        coin_max_redeem_per_txn: settings.coin_max_redeem_per_txn || 50000,
        coin_max_redeem_percentage: settings.coin_max_redeem_percentage || 100,
        coin_terms_conditions: settings.coin_terms_conditions || '',

        holiday_mode:
            settings.holiday_mode === 'true' ||
            settings.holiday_mode === true ||
            settings.holiday_mode === '1',
        always_open:
            settings.always_open === 'true' ||
            settings.always_open === true ||
            settings.always_open === '1' ||
            settings.always_open === undefined, // default true
        operational_hours: Array.isArray(settings.operational_hours)
            ? settings.operational_hours
            : settings.operational_hours
              ? typeof settings.operational_hours === 'string'
                  ? JSON.parse(settings.operational_hours)
                  : settings.operational_hours
              : {
                    monday: { active: true, open: '09:00', close: '17:00' },
                    tuesday: { active: true, open: '09:00', close: '17:00' },
                    wednesday: { active: true, open: '09:00', close: '17:00' },
                    thursday: { active: true, open: '09:00', close: '17:00' },
                    friday: { active: true, open: '09:00', close: '17:00' },
                    saturday: { active: true, open: '09:00', close: '15:00' },
                    sunday: { active: false, open: '09:00', close: '12:00' },
                },

        refund_points_enabled:
            settings.refund_points_enabled === 'true' ||
            settings.refund_points_enabled === true ||
            settings.refund_points_enabled === '1',
        refund_min_amount_transfer: settings.refund_min_amount_transfer || 0,
        refund_transfer_days: settings.refund_transfer_days || '3-5',
        refund_terms_transfer: settings.refund_terms_transfer || '',
        refund_min_amount_points: settings.refund_min_amount_points || 0,
        refund_terms_points: settings.refund_terms_points || '',
        payment_expiry_hours: settings.payment_expiry_hours || 24,
        auto_complete_days: settings.auto_complete_days || 7,
        extend_auto_complete_days: settings.extend_auto_complete_days || 3,

        self_pickup_enabled:
            settings.self_pickup_enabled === 'true' ||
            settings.self_pickup_enabled === true ||
            settings.self_pickup_enabled === '1',
        self_pickup_fee: settings.self_pickup_fee || 0,
        store_courier_enabled:
            settings.store_courier_enabled === 'true' ||
            settings.store_courier_enabled === true ||
            settings.store_courier_enabled === '1',
        store_courier_type: settings.store_courier_type || 'flat',
        store_courier_flat_fee: settings.store_courier_flat_fee || 0,
        store_courier_per_km_fee: settings.store_courier_per_km_fee || 0,
        store_courier_max_radius: settings.store_courier_max_radius || 50,
        store_courier_round_up:
            settings.store_courier_round_up === 'true' ||
            settings.store_courier_round_up === true ||
            settings.store_courier_round_up === '1',
        store_courier_tiered_rates: (() => {
            try {
                if (Array.isArray(settings.store_courier_tiered_rates)) {
                    return settings.store_courier_tiered_rates;
                }
                if (typeof settings.store_courier_tiered_rates === 'string') {
                    return JSON.parse(settings.store_courier_tiered_rates);
                }
            } catch (e) {
                // ignore
            }
            return [];
        })(),
    });

    const dayLabels = {
        monday: 'Senin',
        tuesday: 'Selasa',
        wednesday: 'Rabu',
        thursday: 'Kamis',
        friday: 'Jumat',
        saturday: 'Sabtu',
        sunday: 'Minggu',
    };

    const themePresets = [
        {
            id: 'royal_blue',
            name: 'Royal Blue',
            sub: 'ROYAL BLUE',
            primary: '#0c4cb4',
            secondary: '#fa7315',
        },
        {
            id: 'emerald',
            name: 'Emerald',
            sub: 'EMERALD',
            primary: '#059669',
            secondary: '#10b981',
        },
        {
            id: 'sunset_orange',
            name: 'Sunset Orange',
            sub: 'SUNSET ORANGE',
            primary: '#ea580c',
            secondary: '#f97316',
        },
        {
            id: 'velvet_purple',
            name: 'Velvet Purple',
            sub: 'VELVET PURPLE',
            primary: '#7c3aed',
            secondary: '#a855f7',
        },
        {
            id: 'ocean_teal',
            name: 'Ocean Teal',
            sub: 'OCEAN TEAL',
            primary: '#0f766e',
            secondary: '#06b6d4',
        },
    ];

    let forcedCustom = $state(false);

    let currentPreset = $derived(
        forcedCustom
            ? 'custom'
            : themePresets.find(
                  (p) =>
                      p.primary.toLowerCase() ===
                          form.primary_color.toLowerCase() &&
                      p.secondary.toLowerCase() ===
                          form.secondary_color.toLowerCase(),
              )?.id || 'custom',
    );

    function setPreset(id: string) {
        if (id === 'custom') return;
        const p = themePresets.find((x) => x.id === id);
        if (p) {
            form.primary_color = p.primary;
            form.secondary_color = p.secondary;
            forcedCustom = false;
        }
    }

    const fontOptions = [
        { id: 'Plus Jakarta Sans', name: 'Plus Jakarta Sans' },
        { id: 'Outfit', name: 'Outfit' },
        { id: 'Arial', name: 'Arial (Sistem)' },
        { id: 'Inter', name: 'Inter' },
        { id: 'Roboto', name: 'Roboto' },
        { id: 'Poppins', name: 'Poppins' },
        { id: 'Montserrat', name: 'Montserrat' },
        { id: 'Nunito', name: 'Nunito' },
        { id: 'Ubuntu', name: 'Ubuntu' },
        { id: 'Playfair Display', name: 'Playfair Display' },
        { id: 'Merriweather', name: 'Merriweather' },
    ];

    $effect(() => {
        if (!form.holiday_mode) {
            form.always_open = true;
        }
    });

    $effect(() => {
        if (form.shipping_delivery_enabled) {
            form.biteship_enabled = false;
        }
    });

    $effect(() => {
        if (form.biteship_enabled) {
            form.shipping_delivery_enabled = false;
        }
    });

    $effect(() => {
        if (form.store_font) {
            document.documentElement.style.setProperty(
                '--dynamic-font-sans',
                `'${form.store_font}', ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji'`,
                'important',
            );
            document.documentElement.style.setProperty(
                '--dynamic-font-outfit',
                `'${form.store_font}', sans-serif`,
                'important',
            );
        }
    });

    let imagePreview = $state(null);
    let iconPreview = $state(null);

    // Image Editor Modal States
    let isEditorOpen = $state(false);
    let editorTarget = $state<'logo' | 'icon'>('logo');
    let editorImageSrc = $state<string | null>(null);
    let editorFilename = $state('');
    let editorFileType = $state('image/png');
    let editorScale = $state(1.0);
    let editorRotation = $state(0);
    let editorRemoveBg = $state(false);
    let editorTolerance = $state(30);
    let editorCanvas = $state<HTMLCanvasElement | null>(null);
    let editorLoadedImage = $state<HTMLImageElement | null>(null);

    // Custom Width & Height states
    let editorWidth = $state(512);
    let editorHeight = $state(512);
    let lockAspectRatio = $state(true);
    let editorSharpen = $state(0);

    // Landscape validation for logo: must be wider than tall
    const isLogoNotLandscape = $derived(
        editorTarget === 'logo' && editorWidth <= editorHeight,
    );
    let originalAspectRatio = $state(1);

    // Crop states
    let isCropMode = $state(false);
    let cropPreviewEl = $state<HTMLDivElement | null>(null);
    let cropBox = $state({ x: 0, y: 0, w: 0, h: 0 });
    let cropDragging = $state(false);
    let cropStart = $state({ x: 0, y: 0 });
    let editorInlineFileInput = $state<HTMLInputElement | null>(null);

    function openEditor(file: File, target: 'logo' | 'icon') {
        editorTarget = target;
        editorFilename = file.name;
        editorFileType = file.type || 'image/png';

        // Reset controls
        editorScale = 1.0;
        editorRotation = 0;
        editorRemoveBg = false;
        editorTolerance = 30;
        editorLoadedImage = null;
        editorSharpen = 0;

        const reader = new FileReader();
        reader.onload = (event) => {
            editorImageSrc = event.target?.result as string;

            const img = new Image();
            img.src = editorImageSrc;
            img.onload = () => {
                editorLoadedImage = img;
                if (target === 'icon') {
                    const size = Math.min(
                        img.naturalWidth,
                        img.naturalHeight,
                        128,
                    );
                    editorWidth = size;
                    editorHeight = size;
                    originalAspectRatio = 1.0;
                } else {
                    editorWidth = img.naturalWidth;
                    editorHeight = img.naturalHeight;
                    originalAspectRatio = img.naturalWidth / img.naturalHeight;
                }
                lockAspectRatio = true;
                isEditorOpen = true;
            };
        };
        reader.readAsDataURL(file);
    }

    function openEditorInline(e: Event) {
        const input = e.target as HTMLInputElement;
        if (input.files && input.files[0]) {
            // Keep the modal open and just swap the image
            openEditor(input.files[0], editorTarget);
            input.value = '';
        }
    }

    // Crop helpers
    function startCropMode() {
        isCropMode = true;
        cropBox = { x: 0, y: 0, w: 0, h: 0 };
    }

    function cancelCrop() {
        isCropMode = false;
        cropBox = { x: 0, y: 0, w: 0, h: 0 };
    }

    function getCropEventPos(
        e: MouseEvent | TouchEvent,
        el: HTMLCanvasElement,
    ) {
        const rect = el.getBoundingClientRect();
        const clientX = 'touches' in e ? e.touches[0].clientX : e.clientX;
        const clientY = 'touches' in e ? e.touches[0].clientY : e.clientY;
        return {
            x: Math.max(0, Math.min(clientX - rect.left, rect.width)),
            y: Math.max(0, Math.min(clientY - rect.top, rect.height)),
        };
    }

    function onCropPointerDown(e: MouseEvent | TouchEvent) {
        if (!isCropMode || !editorCanvas) {
            return;
        }
        e.preventDefault();
        const pos = getCropEventPos(e, editorCanvas);
        cropStart = pos;
        cropBox = { x: pos.x, y: pos.y, w: 0, h: 0 };
        cropDragging = true;
    }

    function onCropPointerMove(e: MouseEvent | TouchEvent) {
        if (!cropDragging || !editorCanvas) {
            return;
        }
        e.preventDefault();
        const pos = getCropEventPos(e, editorCanvas);
        cropBox = {
            x: Math.min(pos.x, cropStart.x),
            y: Math.min(pos.y, cropStart.y),
            w: Math.abs(pos.x - cropStart.x),
            h: Math.abs(pos.y - cropStart.y),
        };
    }

    function onCropPointerUp() {
        cropDragging = false;
    }

    function applyCrop() {
        if (!editorCanvas || cropBox.w < 4 || cropBox.h < 4) {
            showToast('Area crop terlalu kecil.', 'error');
            return;
        }
        // Compute scale factor from displayed canvas size to actual canvas pixel dimensions
        const canvasRect = editorCanvas.getBoundingClientRect();
        const scaleX = editorCanvas.width / canvasRect.width;
        const scaleY = editorCanvas.height / canvasRect.height;

        const cx = Math.round(cropBox.x * scaleX);
        const cy = Math.round(cropBox.y * scaleY);
        const cw = Math.round(cropBox.w * scaleX);
        const ch = Math.round(cropBox.h * scaleY);

        const ctx = editorCanvas.getContext('2d');
        if (!ctx) {
            return;
        }

        const imgData = ctx.getImageData(cx, cy, cw, ch);
        editorCanvas.width = cw;
        editorCanvas.height = ch;
        ctx.putImageData(imgData, 0, 0);

        // Update output dimensions to match crop
        editorWidth = cw;
        editorHeight = ch;
        originalAspectRatio = cw / ch;

        // Convert cropped canvas to a new image to reload into editor
        editorCanvas.toBlob((blob) => {
            if (!blob) {
                return;
            }
            const croppedFile = new File([blob], editorFilename, {
                type: 'image/png',
            });
            openEditor(croppedFile, editorTarget);
        }, 'image/png');

        isCropMode = false;
        cropBox = { x: 0, y: 0, w: 0, h: 0 };
        showToast('Crop berhasil diterapkan!', 'success');
    }

    function handleWidthInput(e: Event) {
        const val = parseInt((e.target as HTMLInputElement).value);
        if (isNaN(val) || val < 16) return;
        editorWidth = val;
        if (lockAspectRatio) {
            editorHeight = Math.round(editorWidth / originalAspectRatio);
        }
    }

    function handleHeightInput(e: Event) {
        const val = parseInt((e.target as HTMLInputElement).value);
        if (isNaN(val) || val < 16) return;
        editorHeight = val;
        if (lockAspectRatio) {
            editorWidth = Math.round(editorHeight * originalAspectRatio);
        }
    }

    function swapDimensions() {
        const temp = editorWidth;
        editorWidth = editorHeight;
        editorHeight = temp;
        originalAspectRatio = editorWidth / editorHeight;
    }

    function setPresetDimensions(w: number, h: number) {
        editorWidth = w;
        editorHeight = h;
        originalAspectRatio = w / h;
    }

    function fitImage() {
        if (!editorLoadedImage) return;
        const wRatio = editorWidth / editorLoadedImage.naturalWidth;
        const hRatio = editorHeight / editorLoadedImage.naturalHeight;
        editorScale = +Math.min(wRatio, hRatio).toFixed(2);
    }

    function fillImage() {
        if (!editorLoadedImage) return;
        const wRatio = editorWidth / editorLoadedImage.naturalWidth;
        const hRatio = editorHeight / editorLoadedImage.naturalHeight;
        editorScale = +Math.max(wRatio, hRatio).toFixed(2);
    }

    function enhanceToHD() {
        // Double output dimensions (limit to 4096px max)
        editorWidth = Math.min(editorWidth * 2, 4096);
        editorHeight = Math.min(editorHeight * 2, 4096);
        // Double scale to keep relative image size inside the larger canvas
        editorScale = Math.min(editorScale * 2, 3.0);
        // Set sharpen value to 0.5 (50% intensity) for quality preset
        editorSharpen = 0.5;

        showToast('Kualitas gambar berhasil ditingkatkan ke HD!', 'success');
    }

    function drawEditorImage(
        img: HTMLImageElement,
        scale: number,
        rotation: number,
        removeBg: boolean,
        tolerance: number,
        canvasWidth: number,
        canvasHeight: number,
        sharpenAmount: number,
    ) {
        if (!editorCanvas) return;
        const ctx = editorCanvas.getContext('2d');
        if (!ctx) return;

        editorCanvas.width = canvasWidth;
        editorCanvas.height = canvasHeight;

        // Clear canvas
        ctx.clearRect(0, 0, canvasWidth, canvasHeight);

        // 1. Draw rotated image at 100% crisp size to a temporary canvas
        const isRotated90 = rotation % 180 !== 0;
        const tempW = isRotated90 ? img.naturalHeight : img.naturalWidth;
        const tempH = isRotated90 ? img.naturalWidth : img.naturalHeight;

        const tempCanvas = document.createElement('canvas');
        tempCanvas.width = tempW;
        tempCanvas.height = tempH;
        const tempCtx = tempCanvas.getContext('2d');
        if (!tempCtx) return;

        tempCtx.translate(tempW / 2, tempH / 2);
        tempCtx.rotate((rotation * Math.PI) / 180);
        tempCtx.drawImage(img, -img.naturalWidth / 2, -img.naturalHeight / 2);

        const srcImgData = tempCtx.getImageData(0, 0, tempW, tempH);
        const srcData = srcImgData.data;

        // 2. Perform Bicubic Upscaling directly onto the destination canvas Image Data
        const destImgData = ctx.createImageData(canvasWidth, canvasHeight);
        const destData = destImgData.data;

        function getCubicWeight(t: number) {
            const absT = Math.abs(t);
            if (absT <= 1) {
                return 1.5 * absT * absT * absT - 2.5 * absT * absT + 1;
            } else if (absT < 2) {
                return (
                    -0.5 * absT * absT * absT + 2.5 * absT * absT - 4 * absT + 2
                );
            }
            return 0;
        }

        const halfCW = canvasWidth / 2;
        const halfCH = canvasHeight / 2;
        const halfTW = tempW / 2;
        const halfTH = tempH / 2;

        for (let y = 0; y < canvasHeight; y++) {
            const v = halfTH + (y - halfCH) / scale;
            const yRow = Math.floor(v);
            const dy = v - yRow;

            const wY0 = getCubicWeight(dy + 1);
            const wY1 = getCubicWeight(dy);
            const wY2 = getCubicWeight(dy - 1);
            const wY3 = getCubicWeight(dy - 2);

            const destRowOffset = y * canvasWidth * 4;

            for (let x = 0; x < canvasWidth; x++) {
                const u = halfTW + (x - halfCW) / scale;
                const destOffset = destRowOffset + x * 4;

                // If coordinate is outside the rotated source image bounds, leave it transparent
                if (u < -1 || u >= tempW || v < -1 || v >= tempH) {
                    destData[destOffset] = 0;
                    destData[destOffset + 1] = 0;
                    destData[destOffset + 2] = 0;
                    destData[destOffset + 3] = 0;
                    continue;
                }

                const xCol = Math.floor(u);
                const dx = u - xCol;

                const wX0 = getCubicWeight(dx + 1);
                const wX1 = getCubicWeight(dx);
                const wX2 = getCubicWeight(dx - 1);
                const wX3 = getCubicWeight(dx - 2);

                let r = 0,
                    g = 0,
                    b = 0,
                    a = 0;
                let weightSum = 0;

                for (let j = -1; j <= 2; j++) {
                    const row = yRow + j;
                    if (row < 0 || row >= tempH) continue;
                    const weightY =
                        j === -1 ? wY0 : j === 0 ? wY1 : j === 1 ? wY2 : wY3;
                    const srcRowOffset = row * tempW * 4;

                    for (let i = -1; i <= 2; i++) {
                        const col = xCol + i;
                        if (col < 0 || col >= tempW) continue;
                        const weightX =
                            i === -1
                                ? wX0
                                : i === 0
                                  ? wX1
                                  : i === 1
                                    ? wX2
                                    : wX3;
                        const weight = weightX * weightY;

                        const offset = srcRowOffset + col * 4;
                        r += srcData[offset] * weight;
                        g += srcData[offset + 1] * weight;
                        b += srcData[offset + 2] * weight;
                        a += srcData[offset + 3] * weight;
                        weightSum += weight;
                    }
                }

                if (weightSum > 0) {
                    destData[destOffset] = Math.min(
                        Math.max(r / weightSum, 0),
                        255,
                    );
                    destData[destOffset + 1] = Math.min(
                        Math.max(g / weightSum, 0),
                        255,
                    );
                    destData[destOffset + 2] = Math.min(
                        Math.max(b / weightSum, 0),
                        255,
                    );
                    destData[destOffset + 3] = Math.min(
                        Math.max(a / weightSum, 0),
                        255,
                    );
                } else {
                    destData[destOffset] = 0;
                    destData[destOffset + 1] = 0;
                    destData[destOffset + 2] = 0;
                    destData[destOffset + 3] = 0;
                }
            }
        }

        ctx.putImageData(destImgData, 0, 0);

        // 3. Sharpening Filter (Applied before background removal) using 9-pixel kernel including diagonals
        if (sharpenAmount > 0) {
            const imgData = ctx.getImageData(0, 0, canvasWidth, canvasHeight);
            const data = imgData.data;
            const originalData = new Uint8ClampedArray(data);

            const a = sharpenAmount;
            const b = 1 + 8 * a;

            for (let y = 1; y < canvasHeight - 1; y++) {
                const yOff = y * canvasWidth;
                for (let x = 1; x < canvasWidth - 1; x++) {
                    const dstOff = (yOff + x) * 4;

                    let r = originalData[dstOff] * b;
                    let g = originalData[dstOff + 1] * b;
                    let bVal = originalData[dstOff + 2] * b;

                    const prevRowOff = (y - 1) * canvasWidth * 4;
                    const currRowOff = y * canvasWidth * 4;
                    const nextRowOff = (y + 1) * canvasWidth * 4;

                    const n0 = prevRowOff + (x - 1) * 4;
                    const n1 = prevRowOff + x * 4;
                    const n2 = prevRowOff + (x + 1) * 4;
                    const n3 = currRowOff + (x - 1) * 4;
                    const n4 = currRowOff + (x + 1) * 4;
                    const n5 = nextRowOff + (x - 1) * 4;
                    const n6 = nextRowOff + x * 4;
                    const n7 = nextRowOff + (x + 1) * 4;

                    const neighborR =
                        originalData[n0] +
                        originalData[n1] +
                        originalData[n2] +
                        originalData[n3] +
                        originalData[n4] +
                        originalData[n5] +
                        originalData[n6] +
                        originalData[n7];
                    const neighborG =
                        originalData[n0 + 1] +
                        originalData[n1 + 1] +
                        originalData[n2 + 1] +
                        originalData[n3 + 1] +
                        originalData[n4 + 1] +
                        originalData[n5 + 1] +
                        originalData[n6 + 1] +
                        originalData[n7 + 1];
                    const neighborB =
                        originalData[n0 + 2] +
                        originalData[n1 + 2] +
                        originalData[n2 + 2] +
                        originalData[n3 + 2] +
                        originalData[n4 + 2] +
                        originalData[n5 + 2] +
                        originalData[n6 + 2] +
                        originalData[n7 + 2];

                    r -= neighborR * a;
                    g -= neighborG * a;
                    bVal -= neighborB * a;

                    data[dstOff] = r < 0 ? 0 : r > 255 ? 255 : r;
                    data[dstOff + 1] = g < 0 ? 0 : g > 255 ? 255 : g;
                    data[dstOff + 2] = bVal < 0 ? 0 : bVal > 255 ? 255 : bVal;
                    // Preserve original alpha channel
                    data[dstOff + 3] = originalData[dstOff + 3];
                }
            }
            ctx.putImageData(imgData, 0, 0);
        }

        // 4. Background removal (make white/near-white transparent)
        if (removeBg) {
            const imgData = ctx.getImageData(0, 0, canvasWidth, canvasHeight);
            const data = imgData.data;

            for (let i = 0; i < data.length; i += 4) {
                const r = data[i];
                const g = data[i + 1];
                const b = data[i + 2];
                if (
                    r >= 255 - tolerance &&
                    g >= 255 - tolerance &&
                    b >= 255 - tolerance
                ) {
                    data[i + 3] = 0; // Set alpha to 0 (transparent)
                }
            }
            ctx.putImageData(imgData, 0, 0);
        }
    }

    $effect(() => {
        if (isEditorOpen && editorLoadedImage && editorCanvas) {
            drawEditorImage(
                editorLoadedImage,
                editorScale,
                editorRotation,
                editorRemoveBg,
                editorTolerance,
                editorWidth,
                editorHeight,
                editorSharpen,
            );
        }
    });

    function applyEdits() {
        if (!editorCanvas) return;

        // Block non-landscape logos
        if (editorTarget === 'logo' && editorWidth <= editorHeight) {
            showToast(
                'Logo harus berformat landscape (lebar > tinggi). Sesuaikan dimensi terlebih dahulu.',
                'error',
            );
            return;
        }

        editorCanvas.toBlob((blob) => {
            if (blob) {
                const editedFile = new File(
                    [blob],
                    editorFilename.replace(/\.[^/.]+$/, '') + '.png',
                    {
                        type: 'image/png',
                        lastModified: Date.now(),
                    },
                );

                if (editorTarget === 'logo') {
                    form.store_logo = editedFile;
                    imagePreview = URL.createObjectURL(editedFile);
                } else {
                    form.store_icon = editedFile;
                    iconPreview = URL.createObjectURL(editedFile);
                }

                showToast(
                    `Berhasil menerapkan perubahan pada ${editorTarget === 'logo' ? 'Logo' : 'Icon'} Toko.`,
                    'success',
                );
                isEditorOpen = false;
            }
        }, 'image/png');
    }

    async function editCurrentImage(target: 'logo' | 'icon') {
        let imageUrl: string | null = null;
        let defaultFilename = 'logo.png';

        if (target === 'logo') {
            imageUrl = imagePreview || settings.store_logo;
            defaultFilename = 'store_logo.png';
        } else {
            imageUrl = iconPreview || settings.store_icon;
            defaultFilename = 'store_icon.png';
        }

        if (!imageUrl) return;

        try {
            showToast('Memuat gambar untuk diedit...', 'success');
            const response = await fetch(imageUrl);
            if (!response.ok) throw new Error('Gagal mengunduh gambar');
            const blob = await response.blob();
            const file = new File([blob], defaultFilename, {
                type: blob.type || 'image/png',
            });
            openEditor(file, target);
        } catch (error) {
            console.error(error);
            showToast('Gagal memuat gambar untuk diedit.', 'error');
        }
    }

    let provinces = $state([]);
    let regencies = $state([]);
    let districts = $state([]);
    let villages = $state([]);

    let mapContainer: HTMLElement;
    let map: any;
    let marker: any;
    let searchQuery = $state('');
    let mapSearchResults = $state([]);
    let showMapSearchDropdown = $state(false);
    let isFetchingLocation = $state(false);

    onMount(async () => {
        await fetchProvinces();

        if (form.province_id) await fetchRegencies(form.province_id);
        if (form.regency_id) await fetchDistricts(form.regency_id);
        if (form.district_id) await fetchVillages(form.district_id);

        const L = await import('leaflet');
        await import('leaflet/dist/leaflet.css');

        delete L.Icon.Default.prototype._getIconUrl;
        L.Icon.Default.mergeOptions({
            iconRetinaUrl:
                'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-icon-2x.png',
            iconUrl:
                'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-icon.png',
            shadowUrl:
                'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-shadow.png',
        });

        map = L.map(mapContainer).setView(
            [parseFloat(form.latitude), parseFloat(form.longitude)],
            13,
        );

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
        }).addTo(map);

        marker = L.marker(
            [parseFloat(form.latitude), parseFloat(form.longitude)],
            { draggable: true },
        ).addTo(map);

        marker.on('dragend', function (e) {
            const position = marker.getLatLng();
            form.latitude = position.lat.toString();
            form.longitude = position.lng.toString();
        });

        map.on('click', function (e) {
            marker.setLatLng(e.latlng);
            form.latitude = e.latlng.lat.toString();
            form.longitude = e.latlng.lng.toString();
        });
    });

    async function searchLocation() {
        if (!searchQuery) return;
        try {
            const res = await fetch(
                `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(searchQuery)}`,
            );
            const data = await res.json();
            if (data && data.length > 0) {
                mapSearchResults = data;
                showMapSearchDropdown = true;
            } else {
                mapSearchResults = [];
                showMapSearchDropdown = true;
            }
        } catch (e) {
            console.error('Geocoding error', e);
        }
    }

    function selectMapResult(result) {
        const lat = parseFloat(result.lat);
        const lon = parseFloat(result.lon);
        map.setView([lat, lon], 15);
        marker.setLatLng([lat, lon]);
        form.latitude = lat.toString();
        form.longitude = lon.toString();
        searchQuery = result.display_name;
        showMapSearchDropdown = false;
    }

    async function getCurrentLocation() {
        if (isFetchingLocation) return;
        if (!navigator.geolocation) {
            showToast('Geolokasi tidak didukung oleh browser Anda.', 'error');
            return;
        }

        isFetchingLocation = true;

        navigator.geolocation.getCurrentPosition(
            async (position) => {
                const lat = position.coords.latitude;
                const lon = position.coords.longitude;

                form.latitude = lat.toString();
                form.longitude = lon.toString();

                if (map && marker) {
                    map.setView([lat, lon], 15);
                    marker.setLatLng([lat, lon]);
                }

                // Coba lakukan reverse geocoding untuk melengkapi searchQuery alamat
                try {
                    const res = await fetch(
                        `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lon}`,
                    );
                    const data = await res.json();
                    if (data && data.display_name) {
                        searchQuery = data.display_name;
                    }
                } catch (err) {
                    console.error('Reverse geocoding error:', err);
                }

                isFetchingLocation = false;
                showToast('Lokasi berhasil diperbarui.', 'success');
            },
            (error) => {
                isFetchingLocation = false;
                let errorMessage = 'Gagal mendapatkan lokasi.';
                if (error.code === error.PERMISSION_DENIED) {
                    errorMessage = 'Izin akses lokasi ditolak.';
                } else if (error.code === error.POSITION_UNAVAILABLE) {
                    errorMessage = 'Informasi lokasi tidak tersedia.';
                } else if (error.code === error.TIMEOUT) {
                    errorMessage = 'Waktu permintaan lokasi habis.';
                }
                showToast(errorMessage, 'error');
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0,
            },
        );
    }

    async function fetchProvinces() {
        const res = await fetch(
            'https://www.emsifa.com/api-wilayah-indonesia/api/provinces.json',
        );
        provinces = await res.json();
    }

    async function fetchRegencies(provinceId: string) {
        const res = await fetch(
            `https://www.emsifa.com/api-wilayah-indonesia/api/regencies/${provinceId}.json`,
        );
        regencies = await res.json();
    }

    async function fetchDistricts(regencyId: string) {
        const res = await fetch(
            `https://www.emsifa.com/api-wilayah-indonesia/api/districts/${regencyId}.json`,
        );
        districts = await res.json();
    }

    async function fetchVillages(districtId: string) {
        const res = await fetch(
            `https://www.emsifa.com/api-wilayah-indonesia/api/villages/${districtId}.json`,
        );
        villages = await res.json();
    }

    function handleProvinceChange(id) {
        form.province_name = provinces.find((p) => p.id === id)?.name || '';
        form.regency_id = '';
        form.regency_name = '';
        form.district_id = '';
        form.district_name = '';
        form.village_id = '';
        form.village_name = '';
        regencies = [];
        districts = [];
        villages = [];
        if (id) fetchRegencies(id);
    }

    function handleRegencyChange(id) {
        form.regency_name = regencies.find((r) => r.id === id)?.name || '';
        form.district_id = '';
        form.district_name = '';
        form.village_id = '';
        form.village_name = '';
        districts = [];
        villages = [];
        if (id) fetchDistricts(id);
    }

    function handleDistrictChange(id) {
        form.district_name = districts.find((d) => d.id === id)?.name || '';
        form.village_id = '';
        form.village_name = '';
        villages = [];
        if (id) fetchVillages(id);
    }

    function handleVillageChange(id) {
        form.village_name = villages.find((v) => v.id === id)?.name || '';
    }

    function handleLogoChange(e) {
        if (e.target.files.length) {
            const file = e.target.files[0];
            // Batas maksimal 2MB (2 * 1024 * 1024 byte)
            if (file.size > 2 * 1024 * 1024) {
                showToast(
                    'Ukuran berkas logo terlalu besar. Maksimal 2MB.',
                    'error',
                );
                e.target.value = ''; // Reset file input
                return;
            }
            // Pre-check orientation before opening editor
            const preImg = new Image();
            const objectUrl = URL.createObjectURL(file);
            preImg.onload = () => {
                URL.revokeObjectURL(objectUrl);
                if (preImg.naturalWidth <= preImg.naturalHeight) {
                    showToast(
                        'Perhatian: Gambar ini bukan format landscape. Logo toko wajib berformat landscape (lebar > tinggi). Sesuaikan dimensi di editor sebelum menyimpan.',
                        'error',
                    );
                }
                openEditor(file, 'logo');
            };
            preImg.src = objectUrl;
            e.target.value = '';
        }
    }

    function handleIconChange(e) {
        if (e.target.files.length) {
            const file = e.target.files[0];
            if (file.size > 2 * 1024 * 1024) {
                showToast(
                    'Ukuran berkas icon terlalu besar. Maksimal 2MB.',
                    'error',
                );
                e.target.value = '';
                return;
            }
            openEditor(file, 'icon');
            e.target.value = '';
        }
    }

    function addEarningTier() {
        form.coin_earning_tiers = [
            ...form.coin_earning_tiers,
            { min_purchase: 50000, earn_coins: 50 },
        ];
    }

    function removeEarningTier(index: number) {
        form.coin_earning_tiers = form.coin_earning_tiers.filter(
            (_, i) => i !== index,
        );
    }

    function addCourierTier() {
        form.store_courier_tiered_rates = [
            ...form.store_courier_tiered_rates,
            { max_distance: null, fee: null },
        ];
    }

    function removeCourierTier(index: number) {
        form.store_courier_tiered_rates =
            form.store_courier_tiered_rates.filter((_, i) => i !== index);
    }

    function submit() {
        form.transform((data) => ({
            ...data,
            coin_earning_tiers: JSON.stringify(data.coin_earning_tiers || []),
            store_courier_tiered_rates: JSON.stringify(
                data.store_courier_tiered_rates || [],
            ),
        })).post('/admin/settings', {
            preserveScroll: true,
            onSuccess: () => {
                // Handled by flash message automatically
            },
        });
    }

    function backupDatabase() {
        alert('Fitur Backup sedang dalam pengembangan.');
    }
    function restoreDatabase(e) {
        alert('Fitur Restore sedang dalam pengembangan.');
    }
    function resetDatabase() {
        if (confirm('Yakin ingin melakukan reset?'))
            alert('Fitur Reset sedang dalam pengembangan.');
    }
    const settingsTabs = [
        { id: 'profil', label: 'Profil Toko', icon: 'ti-building-store' },
        { id: 'alamat', label: 'Alamat', icon: 'ti-map-pin' },
        { id: 'refund', label: 'Refund', icon: 'ti-receipt-refund' },
        { id: 'jam', label: 'Jam Operasional', icon: 'ti-clock' },
        {
            id: 'checkout',
            label: 'Checkout & Kurir',
            icon: 'ti-truck-delivery',
        },
        { id: 'database', label: 'Database', icon: 'ti-database' },
    ];

    let activeTab = $state('profil');

    function scrollToSection(id: string) {
        activeTab = id;
        const el = document.getElementById('section-' + id);
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    const activeCouriers = $derived(couriers.filter((c) => c.is_active));
    const inactiveCouriers = $derived(couriers.filter((c) => !c.is_active));
</script>

<svelte:head>
    <title>Pengaturan Toko</title>
</svelte:head>

<AdminLayout>
    <main class="flex-grow p-4 sm:p-8 w-full max-w-full mx-auto space-y-8">
        <form
            onsubmit={(e) => {
                e.preventDefault();
                submit();
            }}
        >
            <!-- Premium Page Header -->
            <div
                class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6"
            >
                <div>
                    <div class="flex items-center gap-2.5 mb-1">
                        <div
                            class="w-9 h-9 rounded-xl flex items-center justify-center shadow-sm shrink-0"
                            style="background: linear-gradient(135deg, {primaryColor}22, {primaryColor}44); color: {primaryColor};"
                        >
                            <i class="ti ti-settings text-base"></i>
                        </div>
                        <h1
                            class="font-outfit font-black text-2xl text-slate-800 tracking-tight"
                        >
                            Pengaturan Terpadu
                        </h1>
                    </div>
                    <p class="text-xs text-slate-400 font-medium ml-11">
                        Konfigurasi toko, kurir, pembayaran, dan branding.
                    </p>
                </div>
                <button
                    type="submit"
                    disabled={form.processing}
                    class="px-5 py-2.5 text-white font-bold text-sm rounded-xl shadow-lg hover:shadow-xl transition flex items-center gap-2 self-stretch sm:self-auto justify-center disabled:opacity-70 shrink-0"
                    style="background: linear-gradient(135deg, {primaryColor}, {primaryColor}cc);"
                    aria-label="Simpan Semua Pengaturan"
                >
                    {#if form.processing}
                        <i class="ti ti-loader animate-spin text-lg"></i>
                        Menyimpan...
                    {:else}
                        <i class="ti ti-device-floppy text-lg"></i>
                        Simpan Semua
                    {/if}
                </button>
            </div>

            <!-- Tab Navigation -->
            <div class="flex gap-1 overflow-x-auto pb-1 mb-6 scrollbar-hide">
                {#each settingsTabs as tab}
                    <button
                        type="button"
                        onclick={() => scrollToSection(tab.id)}
                        class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold whitespace-nowrap transition shrink-0 {activeTab ===
                        tab.id
                            ? 'text-white shadow-sm'
                            : 'text-slate-500 bg-white border border-slate-200 hover:bg-slate-50'}"
                        style={activeTab === tab.id
                            ? `background: linear-gradient(135deg, ${primaryColor}, ${primaryColor}cc);`
                            : ''}
                    >
                        <i class="ti {tab.icon} text-sm"></i>
                        {tab.label}
                    </button>
                {/each}
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                <div class="lg:col-span-8 space-y-8">
                    <div
                        class="bg-white border border-slate-100 shadow-sm rounded-3xl p-6 sm:p-8 space-y-6"
                        id="section-profil"
                    >
                        <div
                            class="flex items-center gap-3 border-b border-slate-100 pb-4"
                        >
                            <div
                                class="p-2.5 bg-slate-50 rounded-xl"
                                style="color: {primaryColor}; background-color: {primaryColor}1A;"
                            >
                                <i class="ti ti-building-store text-lg"></i>
                            </div>
                            <div>
                                <h3
                                    class="font-outfit font-black text-slate-800 text-base leading-none"
                                >
                                    Profil & Informasi Toko
                                </h3>
                                <p
                                    class="text-xs text-slate-400 font-medium mt-1"
                                >
                                    Informasi utama toko yang ditampilkan di
                                    landing page & invoice
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <Input
                                id="input-shop-name"
                                bind:value={form.store_name}
                                label="Nama Toko"
                                placeholder="Contoh: Bizmate Premium Store"
                                required={true}
                            />
                            <Input
                                id="input-shop-email"
                                bind:value={form.store_email}
                                label="Email Kontak Toko"
                                type="email"
                                placeholder="Contoh: support@bizmate.id"
                                required={true}
                            />
                            <Input
                                id="input-shop-phone"
                                bind:value={form.store_phone}
                                label="Telepon Toko"
                                placeholder="Contoh: 021-xxxxxx"
                            />
                            <Input
                                id="input-shop-whatsapp"
                                bind:value={form.store_whatsapp}
                                label="WhatsApp Toko (WA-Link)"
                                placeholder="Contoh: 6285179720622"
                                required={true}
                            />
                            <Input
                                id="input-shop-instagram"
                                bind:value={form.store_instagram}
                                label="Instagram Toko"
                                placeholder="bizmate.premium"
                                prefix="@"
                            />
                            <Input
                                id="input-shop-tiktok"
                                bind:value={form.store_tiktok}
                                label="TikTok Toko"
                                placeholder="bizmate.official"
                                prefix="@"
                            />

                            <div class="sm:col-span-2">
                                <Textarea
                                    id="input-shop-description"
                                    bind:value={form.store_description}
                                    label="Deskripsi Singkat Toko"
                                    placeholder="Tuliskan slogan / penjelasan singkat mengenai kelebihan toko Anda..."
                                    rows={3}
                                />
                            </div>
                        </div>
                    </div>

                    <div
                        id="section-alamat"
                        class="bg-white border border-slate-100 shadow-sm rounded-3xl p-6 sm:p-8 space-y-6"
                    >
                        <div
                            class="flex items-center gap-3 border-b border-slate-100 pb-4"
                        >
                            <div
                                class="p-2.5 bg-rose-50 text-rose-500 rounded-xl"
                            >
                                <i class="ti ti-map-pin text-lg"></i>
                            </div>
                            <div>
                                <h3
                                    class="font-outfit font-black text-slate-800 text-base leading-none"
                                >
                                    Alamat Lengkap & Peta
                                </h3>
                                <p
                                    class="text-xs text-slate-400 font-medium mt-1"
                                >
                                    Konfigurasi lokasi toko untuk perhitungan
                                    ongkos kirim dan navigasi.
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <SelectSearch
                                bind:value={form.province_id}
                                options={provinces}
                                label="Provinsi"
                                placeholder="Pilih Provinsi"
                                required={true}
                                onchange={handleProvinceChange}
                            />
                            <SelectSearch
                                bind:value={form.regency_id}
                                options={regencies}
                                label="Kota / Kabupaten"
                                placeholder="Pilih Kota/Kabupaten"
                                required={true}
                                disabled={!regencies.length &&
                                    !form.province_id}
                                onchange={handleRegencyChange}
                            />
                            <SelectSearch
                                bind:value={form.district_id}
                                options={districts}
                                label="Kecamatan"
                                placeholder="Pilih Kecamatan"
                                required={true}
                                disabled={!districts.length && !form.regency_id}
                                onchange={handleDistrictChange}
                            />
                            <SelectSearch
                                bind:value={form.village_id}
                                options={villages}
                                label="Kelurahan"
                                placeholder="Pilih Kelurahan"
                                required={true}
                                disabled={!villages.length && !form.district_id}
                                onchange={handleVillageChange}
                            />

                            <div class="col-span-full">
                                <Input
                                    bind:value={form.postal_code}
                                    label="Kode Pos"
                                    placeholder="Masukkan Kode Pos"
                                    required={true}
                                />
                            </div>

                            <div class="col-span-full">
                                <Textarea
                                    bind:value={form.address}
                                    label="Alamat Lengkap"
                                    placeholder="Nama Jalan, Gedung, No. Rumah..."
                                    rows={2}
                                />
                            </div>
                        </div>

                        <div class="mt-8 border-t border-slate-100 pt-6">
                            <p
                                class="block text-xs font-bold text-slate-600 mb-2"
                            >
                                Titik Peta (Pin Lokasi)
                            </p>

                            <div class="relative mb-4">
                                <div class="flex gap-2">
                                    <input
                                        type="text"
                                        bind:value={searchQuery}
                                        placeholder="Cari alamat di peta..."
                                        class="flex-grow px-4 py-2.5 rounded-lg border border-slate-200 outline-none text-sm"
                                        onkeydown={(e) =>
                                            e.key === 'Enter' &&
                                            searchLocation()}
                                    />
                                    <button
                                        type="button"
                                        onclick={searchLocation}
                                        class="px-6 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-bold text-sm transition flex items-center gap-2"
                                    >
                                        <i class="ti ti-search"></i> Cari
                                    </button>
                                    <button
                                        type="button"
                                        onclick={getCurrentLocation}
                                        disabled={isFetchingLocation}
                                        class="px-4 py-2.5 bg-sky-50 hover:bg-sky-100 disabled:opacity-50 disabled:cursor-not-allowed text-sky-700 rounded-lg font-bold text-sm transition flex items-center gap-2 shrink-0 border border-sky-100"
                                    >
                                        {#if isFetchingLocation}
                                            <i class="ti ti-loader animate-spin"
                                            ></i>
                                        {:else}
                                            <i class="ti ti-device-gps"></i>
                                        {/if}
                                        Lokasi Saya
                                    </button>
                                </div>

                                {#if showMapSearchDropdown}
                                    <div
                                        class="absolute z-50 w-full mt-1 bg-white border border-slate-200 rounded-xl shadow-lg max-h-60 overflow-y-auto flex flex-col"
                                    >
                                        <div
                                            class="flex justify-between items-center p-3 border-b border-slate-100 bg-slate-50 sticky top-0"
                                        >
                                            <span
                                                class="text-xs font-bold text-slate-500"
                                                >Hasil Pencarian</span
                                            >
                                            <button
                                                type="button"
                                                aria-label="Close"
                                                onclick={() =>
                                                    (showMapSearchDropdown = false)}
                                                class="text-slate-400 hover:text-slate-700"
                                            >
                                                <i class="ti ti-x"></i>
                                            </button>
                                        </div>
                                        {#if mapSearchResults.length === 0}
                                            <div
                                                class="p-4 text-center text-sm text-slate-500"
                                            >
                                                Alamat tidak ditemukan
                                            </div>
                                        {:else}
                                            {#each mapSearchResults as result}
                                                <!-- svelte-ignore a11y_click_events_have_key_events -->
                                                <div
                                                    role="button"
                                                    tabindex="0"
                                                    class="p-3 text-sm hover:bg-slate-50 cursor-pointer border-b border-slate-50 last:border-0"
                                                    onclick={() =>
                                                        selectMapResult(result)}
                                                >
                                                    <span
                                                        class="block text-slate-800 font-medium"
                                                        >{result.name ||
                                                            result.display_name.split(
                                                                ',',
                                                            )[0]}</span
                                                    >
                                                    <span
                                                        class="block text-slate-500 text-xs mt-0.5 truncate"
                                                        >{result.display_name}</span
                                                    >
                                                </div>
                                            {/each}
                                        {/if}
                                    </div>
                                {/if}
                            </div>

                            <div
                                bind:this={mapContainer}
                                class="w-full h-80 rounded-2xl border border-slate-200 z-10 relative mb-4"
                            ></div>

                            <div class="flex gap-4">
                                <div class="flex-grow">
                                    <Input
                                        bind:value={form.latitude}
                                        label="Latitude"
                                        readonly={true}
                                    />
                                </div>
                                <div class="flex-grow">
                                    <Input
                                        bind:value={form.longitude}
                                        label="Longitude"
                                        readonly={true}
                                    />
                                </div>
                            </div>
                        </div>
                    </div>


                    <!-- REFUND & CANCELLATION SYSTEM -->
                    <div
                        class="bg-white border border-slate-100 shadow-sm rounded-3xl p-6 sm:p-8 space-y-6"
                        id="section-refund"
                    >
                        <div
                            class="flex items-center gap-3 border-b border-slate-100 pb-4"
                        >
                            <div
                                class="p-2.5 bg-red-50 text-red-500 rounded-xl"
                            >
                                <i class="ti ti-receipt-refund text-lg"></i>
                            </div>
                            <div>
                                <h3
                                    class="font-outfit font-black text-slate-800 text-base leading-none"
                                >
                                    Refund & Pengajuan Pembatalan
                                </h3>
                                <p
                                    class="text-xs text-slate-400 font-medium mt-1"
                                >
                                    Atur pengembalian dana (transfer bank &
                                    koin), syarat ketentuan, minimal refund, dan
                                    estimasi waktu proses.
                                </p>
                            </div>
                        </div>

                        <div class="space-y-6">
                            <!-- Section: Bank Transfer -->
                            <div class="space-y-4">
                                <h4
                                    class="text-xs font-black text-slate-700 uppercase tracking-tight block"
                                >
                                    Refund via Transfer Bank
                                </h4>
                                <div
                                    class="grid grid-cols-1 sm:grid-cols-2 gap-6"
                                >
                                    <InputCurrency
                                        id="input-refund-min-transfer"
                                        bind:value={
                                            form.refund_min_amount_transfer
                                        }
                                        label="Minimal Refund Transfer Bank"
                                        placeholder="0"
                                        required={true}
                                    />
                                    <div class="space-y-1.5 font-sans">
                                        <label
                                            for="input-refund-days"
                                            class="block text-xs font-bold text-slate-600"
                                        >
                                            Estimasi Hari Kerja Transfer (Hari)
                                        </label>
                                        <input
                                            id="input-refund-days"
                                            type="text"
                                            bind:value={
                                                form.refund_transfer_days
                                            }
                                            placeholder="Contoh: 3-5"
                                            class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:outline-none transition font-sans"
                                            required
                                        />
                                    </div>
                                </div>
                                <Textarea
                                    id="input-refund-terms-transfer"
                                    bind:value={form.refund_terms_transfer}
                                    label="Syarat & Ketentuan Refund Transfer Bank"
                                    placeholder="Tuliskan syarat & ketentuan refund via Transfer Bank untuk dibaca oleh pelanggan..."
                                    rows={3}
                                />
                            </div>

                            <div class="h-px bg-slate-100 my-2"></div>

                            <!-- Section: Loyalty Points (Coins) -->
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <span
                                            class="text-xs font-black text-slate-700 uppercase tracking-tight block"
                                            >Aktifkan Refund ke Koin Toko</span
                                        >
                                        <p
                                            class="text-[10px] text-slate-400 mt-0.5"
                                        >
                                            Memungkinkan pelanggan memilih
                                            refund ke saldo koin toko (instan
                                            setelah disetujui).
                                        </p>
                                    </div>
                                    <Toggle
                                        bind:checked={
                                            form.refund_points_enabled
                                        }
                                    />
                                </div>

                                {#if form.refund_points_enabled}
                                    <div
                                        class="space-y-4 pt-2"
                                        transition:slide
                                    >
                                        <InputCurrency
                                            id="input-refund-min-points"
                                            bind:value={
                                                form.refund_min_amount_points
                                            }
                                            label="Minimal Refund via Koin Toko"
                                            placeholder="0"
                                            required={true}
                                        />
                                        <Textarea
                                            id="input-refund-terms-points"
                                            bind:value={
                                                form.refund_terms_points
                                            }
                                            label="Syarat & Ketentuan Refund via Koin Toko"
                                            placeholder="Tuliskan syarat & ketentuan refund via Koin Toko untuk dibaca oleh pelanggan..."
                                            rows={3}
                                        />
                                    </div>
                                {/if}
                            </div>
                        </div>
                    </div>

                    <div
                        class="bg-white border border-slate-100 shadow-sm rounded-3xl p-6 space-y-6"
                        id="section-jam"
                    >
                        <div
                            class="flex items-center gap-3 border-b border-slate-100 pb-4"
                        >
                            <div
                                class="p-2.5 bg-amber-50 text-amber-500 rounded-xl"
                            >
                                <i class="ti ti-clock text-lg"></i>
                            </div>
                            <div>
                                <h3
                                    class="font-outfit font-black text-slate-800 text-base leading-none"
                                >
                                    Jam Operasional & Status Toko
                                </h3>
                                <p
                                    class="text-xs text-slate-400 font-medium mt-1"
                                >
                                    Konfigurasi hari kerja, jam operasional, dan
                                    Mode Libur.
                                </p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <!-- HOLIDAY MODE -->
                            <div
                                class="bg-amber-50/50 border border-amber-100/50 rounded-2xl p-4 flex items-center justify-between gap-4"
                            >
                                <div>
                                    <h4
                                        class="font-bold text-amber-700 text-xs sm:text-sm uppercase tracking-wider flex items-center gap-1.5 mb-1"
                                    >
                                        <i
                                            class="ti ti-ship text-amber-600 text-base"
                                        ></i> MODE LIBUR TOKO (HOLIDAY MODE)
                                    </h4>
                                    <p
                                        class="text-xs text-amber-600/80 font-semibold leading-relaxed max-w-sm"
                                    >
                                        Aktifkan untuk menutup toko sementara.
                                        Seluruh halaman e-commerce akan
                                        menampilkan banner libur dan proses
                                        checkout dinonaktifkan sepenuhnya.
                                    </p>
                                </div>
                                <div class="shrink-0 scale-125 origin-right">
                                    <Toggle bind:checked={form.holiday_mode} />
                                </div>
                            </div>

                            <div class="h-px bg-slate-100 my-2"></div>

                            <div class="flex items-center justify-between">
                                <div>
                                    <span
                                        class="text-xs font-black text-slate-700 uppercase tracking-tight block"
                                        >Toko Buka Terus (24 Jam)</span
                                    >
                                    <p
                                        class="text-[10px] text-slate-400 mt-0.5"
                                    >
                                        Abaikan jadwal mingguan dan buka toko 24
                                        jam penuh setiap harinya.
                                    </p>
                                </div>
                                <Toggle
                                    bind:checked={form.always_open}
                                    disabled={!form.holiday_mode}
                                />
                            </div>

                            <!-- WEEKLY SCHEDULE -->
                            {#if form.holiday_mode && !form.always_open}
                                <div transition:slide class="space-y-4 pt-2">
                                    <h4
                                        class="text-xs font-black text-slate-700 uppercase tracking-tight block"
                                    >
                                        JADWAL OPERASIONAL MINGGUAN
                                    </h4>

                                    <div
                                        class="border border-slate-100 rounded-2xl overflow-hidden"
                                    >
                                        <div
                                            class="grid grid-cols-12 gap-2 bg-slate-50 px-4 py-3 border-b border-slate-100"
                                        >
                                            <div
                                                class="col-span-4 sm:col-span-3 text-[10px] font-black text-slate-400 uppercase tracking-widest"
                                            >
                                                HARI
                                            </div>
                                            <div
                                                class="col-span-3 sm:col-span-2 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center"
                                            >
                                                STATUS
                                            </div>
                                            <div
                                                class="col-span-5 sm:col-span-7 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right"
                                            >
                                                JAM BUKA - TUTUP
                                            </div>
                                        </div>

                                        <div class="divide-y divide-slate-100">
                                            {#each Object.keys(dayLabels) as day}
                                                <div
                                                    class="grid grid-cols-12 gap-2 px-4 py-4 items-center transition-colors {form
                                                        .operational_hours[day]
                                                        .active
                                                        ? 'bg-white'
                                                        : 'bg-slate-50/50'}"
                                                >
                                                    <div
                                                        class="col-span-4 sm:col-span-3 font-bold text-sm text-slate-700"
                                                    >
                                                        {dayLabels[day]}
                                                    </div>
                                                    <div
                                                        class="col-span-3 sm:col-span-2 flex justify-center"
                                                    >
                                                        <Toggle
                                                            bind:checked={
                                                                form
                                                                    .operational_hours[
                                                                    day
                                                                ].active
                                                            }
                                                        />
                                                    </div>
                                                    <div
                                                        class="col-span-5 sm:col-span-7 flex justify-end items-center gap-2"
                                                    >
                                                        <div
                                                            class="relative w-24"
                                                        >
                                                            <input
                                                                type="time"
                                                                bind:value={
                                                                    form
                                                                        .operational_hours[
                                                                        day
                                                                    ].open
                                                                }
                                                                disabled={!form
                                                                    .operational_hours[
                                                                    day
                                                                ].active}
                                                                class="w-full text-xs font-medium bg-white border border-slate-200 rounded-lg px-2.5 py-1.5 text-center focus:ring-1 focus:ring-slate-300 outline-none disabled:opacity-50 disabled:bg-slate-50"
                                                            />
                                                        </div>
                                                        <span
                                                            class="text-slate-300 font-bold"
                                                            >-</span
                                                        >
                                                        <div
                                                            class="relative w-24"
                                                        >
                                                            <input
                                                                type="time"
                                                                bind:value={
                                                                    form
                                                                        .operational_hours[
                                                                        day
                                                                    ].close
                                                                }
                                                                disabled={!form
                                                                    .operational_hours[
                                                                    day
                                                                ].active}
                                                                class="w-full text-xs font-medium bg-white border border-slate-200 rounded-lg px-2.5 py-1.5 text-center focus:ring-1 focus:ring-slate-300 outline-none disabled:opacity-50 disabled:bg-slate-50"
                                                            />
                                                        </div>
                                                    </div>
                                                </div>
                                            {/each}
                                        </div>
                                    </div>
                                </div>
                            {/if}
                        </div>
                    </div>

                    <div
                        id="section-checkout"
                        class="bg-white border border-slate-100 shadow-sm rounded-3xl p-6 space-y-6"
                    >
                        {#if env_keys.show_checkout_settings !== false}
                            <div
                                class="flex items-center gap-3 border-b border-slate-100 pb-4"
                            >
                                <div
                                    class="p-2.5 bg-indigo-50 text-indigo-500 rounded-xl"
                                >
                                    <i
                                        class="ti ti-shopping-cart-discount text-lg"
                                    ></i>
                                </div>
                                <div>
                                    <h3
                                        class="font-outfit font-black text-slate-800 text-base leading-none"
                                    >
                                        Checkout & Ongkir
                                    </h3>
                                    <p
                                        class="text-xs text-slate-400 font-medium mt-1"
                                    >
                                        Konfigurasi metode kirim & bayar.
                                    </p>
                                </div>
                            </div>
                        {/if}

                        <!-- Courier Overview Panel -->
                        {#if couriers.length > 0}
                            <div
                                class="bg-gradient-to-r from-indigo-50 to-blue-50/50 border border-indigo-100 rounded-2xl p-4"
                            >
                                <div
                                    class="flex items-center justify-between mb-3"
                                >
                                    <div class="flex items-center gap-2">
                                        <div
                                            class="w-7 h-7 rounded-lg bg-indigo-500 flex items-center justify-center shrink-0"
                                        >
                                            <i
                                                class="ti ti-truck-delivery text-white text-xs"
                                            ></i>
                                        </div>
                                        <div>
                                            <h4
                                                class="text-xs font-black text-slate-800"
                                            >
                                                Kurir Logistik Aktif
                                            </h4>
                                            <p
                                                class="text-[10px] text-slate-400"
                                            >
                                                Data diambil dari Master Data · <span
                                                    class="font-bold text-emerald-600"
                                                    >{activeCouriers.length} aktif</span
                                                >
                                                / {couriers.length} total
                                            </p>
                                        </div>
                                    </div>
                                    <a
                                        href="/admin/master-data/couriers"
                                        class="px-2.5 py-1 text-[10px] font-black text-indigo-600 bg-white border border-indigo-200 rounded-lg hover:bg-indigo-50 transition flex items-center gap-1 shrink-0"
                                    >
                                        <i class="ti ti-external-link text-xs"
                                        ></i>
                                        Kelola Kurir
                                    </a>
                                </div>
                                <div class="flex flex-wrap gap-1.5">
                                    {#each couriers as courier}
                                        <span
                                            class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[10px] font-bold border {courier.is_active
                                                ? 'bg-white text-slate-700 border-slate-200'
                                                : 'bg-slate-100/50 text-slate-400 border-slate-200/50 line-through'}"
                                        >
                                            {#if courier.is_active}
                                                <span
                                                    class="w-1.5 h-1.5 rounded-full bg-emerald-400 shrink-0"
                                                ></span>
                                            {:else}
                                                <span
                                                    class="w-1.5 h-1.5 rounded-full bg-slate-300 shrink-0"
                                                ></span>
                                            {/if}
                                            {courier.name}
                                        </span>
                                    {/each}
                                </div>
                                <p
                                    class="text-[10px] text-slate-400 mt-2.5 leading-relaxed"
                                >
                                    <i class="ti ti-info-circle text-xs"></i>
                                    Kurir aktif/nonaktif dapat dikelola di halaman
                                    <a
                                        href="/admin/master-data/couriers"
                                        class="font-bold text-indigo-600 hover:underline"
                                        >Master Data → Kurir</a
                                    >. Kurir yang aktif akan tampil sebagai
                                    pilihan pengiriman saat checkout.
                                </p>
                            </div>
                        {/if}

                        <div class="h-px bg-slate-100"></div>

                        <div class="space-y-5">
                            <div class="space-y-3.5">
                                <span
                                    class="text-xs font-black text-slate-700 uppercase tracking-tight block"
                                    >Pengaturan Pajak</span
                                >
                                <Toggle
                                    bind:checked={form.tax_enabled}
                                    label="Pajak (Tax) Aktif"
                                    icon="ti-receipt-tax"
                                />

                                {#if form.tax_enabled}
                                    <div transition:slide>
                                        <p
                                            class="block text-xs font-bold text-slate-600 mb-2 mt-2"
                                        >
                                            Nominal Pajak (%)
                                        </p>
                                        <div class="relative">
                                            <input
                                                type="number"
                                                step="0.1"
                                                bind:value={form.tax_percentage}
                                                class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:outline-none transition"
                                                placeholder="Contoh: 11"
                                            />
                                            <div
                                                class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none text-slate-400 font-bold"
                                            >
                                                %
                                            </div>
                                        </div>
                                    </div>
                                {/if}
                            </div>

                            <div class="h-px bg-slate-100"></div>

                            {#if env_keys.show_checkout_settings !== false}
                                <!-- Self Pickup Settings -->
                                <div class="space-y-2">
                                    <Toggle
                                        bind:checked={form.self_pickup_enabled}
                                        label="Aktifkan Pengambilan di Toko (Self-Pickup)"
                                        icon="ti-building-store"
                                    />
                                    {#if form.self_pickup_enabled}
                                        <div class="pl-6 border-l-2 border-slate-100 mt-2 text-xs text-slate-400 font-medium" transition:slide>
                                            Ubah biaya penanganan ini di menu <Link href="/admin/master-data/cost" class="text-blue-600 hover:underline font-bold">Master Biaya</Link>.
                                        </div>
                                    {/if}
                                </div>

                                <div class="h-px bg-slate-100 my-2"></div>

                                <!-- Store Courier Settings -->
                                <div class="space-y-2">
                                    <Toggle
                                        bind:checked={form.store_courier_enabled}
                                        label="Aktifkan Kurir Toko"
                                        icon="ti-truck-delivery"
                                    />
                                    {#if form.store_courier_enabled}
                                        <div class="pl-6 border-l-2 border-slate-100 mt-2 text-xs text-slate-400 font-medium" transition:slide>
                                            Ubah biaya dan perhitungan kurir toko ini di menu <Link href="/admin/master-data/couriers" class="text-blue-600 hover:underline font-bold">Master Kurir</Link>.
                                        </div>
                                    {/if}
                                </div>

                                {#if isLogisticEnabled}
                                    <div class="h-px bg-slate-100 my-2"></div>

                                    <!-- Logistik API Summary Card -->
                                    <div class="rounded-xl border border-blue-100 bg-blue-50/40 p-3.5">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-2.5">
                                                <div class="w-7 h-7 rounded-lg bg-blue-500 flex items-center justify-center shrink-0">
                                                    <i class="ti ti-truck-delivery text-white text-xs"></i>
                                                </div>
                                                <div>
                                                    <p class="text-xs font-black text-slate-800">Logistik API</p>
                                                    <p class="text-[10px] text-slate-400">
                                                        {#if form.shipping_delivery_enabled}
                                                            <span class="text-blue-600 font-bold">Komerce Delivery aktif</span>
                                                        {:else if form.biteship_enabled}
                                                            <span class="text-emerald-600 font-bold">Biteship aktif</span>
                                                        {:else}
                                                            <span class="text-slate-400">Tidak ada provider aktif</span>
                                                        {/if}
                                                    </p>
                                                </div>
                                            </div>
                                            <a href="/admin/master-data/logistic-api" class="px-2.5 py-1 text-[10px] font-black text-blue-600 bg-white border border-blue-200 rounded-lg hover:bg-blue-50 transition flex items-center gap-1 shrink-0">
                                                <i class="ti ti-settings text-xs"></i> Kelola
                                            </a>
                                        </div>
                                    </div>
                                {/if}

                                <!-- Payment Gateway Summary Card -->
                                <div class="rounded-xl border border-violet-100 bg-violet-50/40 p-3.5">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-7 h-7 rounded-lg bg-violet-500 flex items-center justify-center shrink-0">
                                                <i class="ti ti-credit-card text-white text-xs"></i>
                                            </div>
                                            <div>
                                                <p class="text-xs font-black text-slate-800">Payment Gateway</p>
                                                <p class="text-[10px] text-slate-400">
                                                    {#if form.payment_api_enabled}
                                                        <span class="text-violet-600 font-bold">Payment API (Komerce) aktif</span>
                                                    {:else if form.qrisly_api_enabled}
                                                        <span class="text-violet-600 font-bold">QRISLY API aktif</span>
                                                    {:else if form.midtrans_api_enabled}
                                                        <span class="text-emerald-600 font-bold">Midtrans Snap aktif</span>
                                                    {:else if form.xendit_api_enabled}
                                                        <span class="text-amber-600 font-bold">Xendit Invoices aktif</span>
                                                    {:else}
                                                        <span class="text-slate-400">Tidak ada gateway aktif</span>
                                                    {/if}
                                                </p>
                                            </div>
                                        </div>
                                        <a href="/admin/master-data/payment-methods" class="px-2.5 py-1 text-[10px] font-black text-violet-600 bg-white border border-violet-200 rounded-lg hover:bg-violet-50 transition flex items-center gap-1 shrink-0">
                                            <i class="ti ti-settings text-xs"></i> Kelola
                                        </a>
                                    </div>
                                </div>

                                <div class="h-px bg-slate-100"></div>
                            {/if}

                            <div class="space-y-3.5">
                                <span
                                    class="text-xs font-black text-slate-700 uppercase tracking-tight block"
                                >
                                    Batas Waktu Transaksi
                                </span>
                                <p class="text-[11px] text-slate-400 font-bold">
                                    Konfigurasi batas waktu pembayaran,
                                    pembatalan otomatis, dan konfirmasi
                                    penerimaan pesanan.
                                </p>
                                <div class="grid grid-cols-1 gap-4 mt-2">
                                    <div>
                                        <Input
                                            id="input-payment-expiry-hours"
                                            type="number"
                                            bind:value={
                                                form.payment_expiry_hours
                                            }
                                            label="Batas Waktu Pembayaran (Jam)"
                                            placeholder="Contoh: 24"
                                            min="1"
                                            required={true}
                                        />
                                        <span
                                            class="text-[10px] text-slate-400 mt-1 block"
                                            >Durasi (jam) sebelum pesanan belum
                                            dibayar dibatalkan otomatis.</span
                                        >
                                    </div>
                                    <div>
                                        <Input
                                            id="input-auto-complete-days"
                                            type="number"
                                            bind:value={form.auto_complete_days}
                                            label="Selesai Otomatis Kirim (Hari)"
                                            placeholder="Contoh: 7"
                                            min="1"
                                            required={true}
                                        />
                                        <span
                                            class="text-[10px] text-slate-400 mt-1 block"
                                            >Durasi (hari) sebelum pesanan
                                            berstatus dikirim diselesaikan
                                            otomatis.</span
                                        >
                                    </div>
                                    <div>
                                        <Input
                                            id="input-extend-auto-complete-days"
                                            type="number"
                                            bind:value={
                                                form.extend_auto_complete_days
                                            }
                                            label="Perpanjangan Jangka Waktu (Hari)"
                                            placeholder="Contoh: 3"
                                            min="1"
                                            required={true}
                                        />
                                        <span
                                            class="text-[10px] text-slate-400 mt-1 block"
                                            >Durasi tambahan (hari) ketika
                                            customer memperpanjang penerimaan.</span
                                        >
                                    </div>
                                </div>
                            </div>

                            <div class="h-px bg-slate-100"></div>

                            <div class="space-y-3">
                                <span
                                    class="text-xs font-black text-slate-700 uppercase tracking-tight block"
                                >
                                    Desain Tombol Keranjang (Storefront)
                                </span>
                                <p class="text-[11px] text-slate-400 font-bold">
                                    Pilih tampilan tombol "+ Keranjang" pada
                                    daftar produk di halaman depan toko Anda.
                                </p>
                                <div class="grid grid-cols-3 gap-2 mt-2">
                                    <button
                                        type="button"
                                        onclick={() =>
                                            (form.storefront_cart_button_style =
                                                'button')}
                                        class="flex flex-col items-center justify-center p-3 rounded-2xl border text-center transition duration-200 hover:bg-slate-50 cursor-pointer
                                               {form.storefront_cart_button_style ===
                                        'button'
                                            ? 'border-blue-500 bg-blue-50/20'
                                            : 'border-slate-200 bg-white'}"
                                    >
                                        <div
                                            class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center mb-2"
                                        >
                                            <i
                                                class="ti ti-shopping-cart text-lg"
                                            ></i>
                                        </div>
                                        <span
                                            class="text-[11px] font-bold text-slate-700"
                                            >Tombol Bawah</span
                                        >
                                        <span
                                            class="text-[9px] text-slate-400 mt-0.5 leading-none"
                                            >"+ KERANJANG"</span
                                        >
                                    </button>

                                    <button
                                        type="button"
                                        onclick={() =>
                                            (form.storefront_cart_button_style =
                                                'icon')}
                                        class="flex flex-col items-center justify-center p-3 rounded-2xl border text-center transition duration-200 hover:bg-slate-50 cursor-pointer
                                               {form.storefront_cart_button_style ===
                                        'icon'
                                            ? 'border-blue-500 bg-blue-50/20'
                                            : 'border-slate-200 bg-white'}"
                                    >
                                        <div
                                            class="w-8 h-8 rounded-full bg-green-100 text-green-600 flex items-center justify-center mb-2"
                                        >
                                            <i class="ti ti-plus text-lg"></i>
                                        </div>
                                        <span
                                            class="text-[11px] font-bold text-slate-700"
                                            >Ikon Pojok</span
                                        >
                                        <span
                                            class="text-[9px] text-slate-400 mt-0.5 leading-none"
                                            >"+" di Foto</span
                                        >
                                    </button>

                                    <button
                                        type="button"
                                        onclick={() =>
                                            (form.storefront_cart_button_style =
                                                'none')}
                                        class="flex flex-col items-center justify-center p-3 rounded-2xl border text-center transition duration-200 hover:bg-slate-50 cursor-pointer
                                               {form.storefront_cart_button_style ===
                                        'none'
                                            ? 'border-blue-500 bg-blue-50/20'
                                            : 'border-slate-200 bg-white'}"
                                    >
                                        <div
                                            class="w-8 h-8 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center mb-2"
                                        >
                                            <i class="ti ti-eye text-lg"></i>
                                        </div>
                                        <span
                                            class="text-[11px] font-bold text-slate-700"
                                            >Tanpa Tombol</span
                                        >
                                        <span
                                            class="text-[9px] text-slate-400 mt-0.5 leading-none"
                                            >Hanya Detail</span
                                        >
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        class="bg-white border border-slate-100 shadow-sm rounded-3xl p-6 space-y-6"
                        id="section-database"
                    >
                        <div
                            class="flex items-center gap-3 border-b border-slate-100 pb-4"
                        >
                            <div
                                class="p-2.5 bg-rose-50 text-rose-600 rounded-xl"
                            >
                                <i class="ti ti-database text-lg"></i>
                            </div>
                            <div>
                                <h3
                                    class="font-outfit font-black text-slate-800 text-base leading-none"
                                >
                                    Database Maintenance
                                </h3>
                                <p
                                    class="text-xs text-slate-400 font-medium mt-1"
                                >
                                    Pemeliharaan, backup, dan reset database
                                    sistem
                                </p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <button
                                type="button"
                                onclick={backupDatabase}
                                class="w-full py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-sm rounded-xl transition flex items-center justify-center gap-2"
                            >
                                <i class="ti ti-download"></i>
                                Backup Database (JSON)
                            </button>

                            <div class="space-y-2">
                                <span
                                    class="text-xs font-bold text-slate-500 block"
                                    >Restore Database</span
                                >
                                <div class="relative">
                                    <label
                                        for="restore-file-input"
                                        class="w-full py-3 border border-slate-200 hover:bg-slate-50 text-slate-600 font-bold text-sm rounded-xl transition flex items-center justify-center gap-2 cursor-pointer"
                                    >
                                        <i class="ti ti-upload"></i> Upload Backup
                                        File
                                    </label>
                                    <input
                                        type="file"
                                        id="restore-file-input"
                                        accept=".json"
                                        class="hidden"
                                        onchange={restoreDatabase}
                                    />
                                </div>
                            </div>

                            <div class="h-px bg-slate-100 my-4"></div>

                            <button
                                type="button"
                                onclick={resetDatabase}
                                class="w-full py-3 bg-rose-50 hover:bg-rose-100 text-rose-600 font-bold text-sm rounded-xl transition flex items-center justify-center gap-2"
                            >
                                <i class="ti ti-refresh-alert"></i>
                                Reset ke Kondisi Pabrik (Re-seed)
                            </button>
                        </div>
                    </div>
                </div>
                <div class="lg:col-span-4 space-y-8">
                    <div
                        class="bg-white border border-slate-100 shadow-sm rounded-3xl p-6 space-y-6"
                    >
                        <div
                            class="flex items-center gap-3 border-b border-slate-100 pb-4"
                        >
                            <div
                                class="p-2.5 bg-purple-50 text-purple-500 rounded-xl"
                            >
                                <i class="ti ti-photo text-lg"></i>
                            </div>
                            <div>
                                <h3
                                    class="font-outfit font-black text-slate-800 text-base leading-none"
                                >
                                    Logo Toko
                                </h3>
                                <p
                                    class="text-xs text-slate-400 font-medium mt-1"
                                >
                                    Klik kotak untuk mengganti logo
                                </p>
                            </div>
                        </div>

                        <div
                            class="relative w-full aspect-video sm:aspect-square max-w-[240px] mx-auto rounded-3xl border-2 border-dashed border-slate-200 bg-slate-50 transition cursor-pointer flex flex-col items-center justify-center overflow-hidden group"
                        >
                            <input
                                type="file"
                                accept="image/*"
                                onchange={handleLogoChange}
                                class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                            />

                            {#if imagePreview || settings.store_logo}
                                <img
                                    src={imagePreview || settings.store_logo}
                                    alt="Logo"
                                    class="w-full h-full object-cover"
                                />
                                <div
                                    class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition flex items-center justify-center pointer-events-none"
                                >
                                    <div class="flex flex-col items-center">
                                        <i
                                            class="ti ti-photo-edit text-2xl text-white mb-1"
                                        ></i>
                                        <span
                                            class="text-white font-bold text-xs"
                                            >Ubah Logo</span
                                        >
                                    </div>
                                </div>
                            {:else}
                                <i
                                    class="ti ti-cloud-upload text-4xl text-slate-300 mb-2 transition-colors"
                                ></i>
                                <span
                                    class="text-xs font-bold text-slate-400 transition-colors text-center px-4"
                                    >Upload gambar JPG/PNG</span
                                >
                            {/if}
                        </div>

                        {#if imagePreview || settings.store_logo}
                            <div class="flex justify-center -mt-2 mb-2">
                                <button
                                    type="button"
                                    onclick={() => editCurrentImage('logo')}
                                    class="px-4 py-2 border border-slate-200 hover:border-slate-300 text-slate-600 bg-white hover:bg-slate-50 rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shadow-sm active:scale-95"
                                >
                                    <i class="ti ti-crop text-sm"></i>
                                    Edit Gambar Saat Ini
                                </button>
                            </div>
                        {/if}

                        <div
                            class="flex items-start gap-2.5 bg-amber-50 p-3.5 rounded-2xl border border-amber-200"
                        >
                            <i
                                class="ti ti-alert-triangle text-amber-500 text-base mt-0.5 shrink-0"
                            ></i>
                            <div>
                                <span
                                    class="text-[10px] font-black text-amber-700 uppercase tracking-tight block"
                                    >Logo Wajib Format Landscape</span
                                >
                                <p
                                    class="text-[10px] text-amber-700 font-semibold mt-0.5 leading-relaxed"
                                >
                                    Logo harus <strong
                                        >lebih lebar dari tingginya</strong
                                    >
                                    (orientasi landscape).<br />
                                    Format PNG/JPG, maks. 2MB.<br />
                                    Resolusi rekomendasi:
                                    <strong>1024 × 512 px</strong> (2:1) atau
                                    <strong>1200 × 400 px</strong> (3:1).
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        class="bg-white border border-slate-100 shadow-sm rounded-3xl p-6 space-y-6"
                    >
                        <div
                            class="flex items-center gap-3 border-b border-slate-100 pb-4"
                        >
                            <div
                                class="p-2.5 bg-pink-50 text-pink-500 rounded-xl"
                            >
                                <i class="ti ti-app-window text-lg"></i>
                            </div>
                            <div>
                                <h3
                                    class="font-outfit font-black text-slate-800 text-base leading-none"
                                >
                                    Icon Toko
                                </h3>
                                <p
                                    class="text-xs text-slate-400 font-medium mt-1"
                                >
                                    Klik kotak untuk mengganti favicon
                                </p>
                            </div>
                        </div>

                        <div
                            class="relative w-full aspect-square max-w-[120px] mx-auto rounded-3xl border-2 border-dashed border-slate-200 bg-slate-50 transition cursor-pointer flex flex-col items-center justify-center overflow-hidden group"
                        >
                            <input
                                type="file"
                                accept="image/*"
                                onchange={handleIconChange}
                                class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                            />

                            {#if iconPreview || settings.store_icon}
                                <img
                                    src={iconPreview || settings.store_icon}
                                    alt="Icon"
                                    class="w-full h-full object-cover"
                                />
                                <div
                                    class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition flex items-center justify-center pointer-events-none"
                                >
                                    <div class="flex flex-col items-center">
                                        <i
                                            class="ti ti-photo-edit text-2xl text-white mb-1"
                                        ></i>
                                        <span
                                            class="text-white font-bold text-xs"
                                            >Ubah Icon</span
                                        >
                                    </div>
                                </div>
                            {:else}
                                <i
                                    class="ti ti-cloud-upload text-3xl text-slate-300 mb-1 transition-colors"
                                ></i>
                                <span
                                    class="text-[10px] font-bold text-slate-400 transition-colors text-center px-2"
                                    >Upload Icon</span
                                >
                            {/if}
                        </div>

                        {#if iconPreview || settings.store_icon}
                            <div class="flex justify-center -mt-2 mb-2">
                                <button
                                    type="button"
                                    onclick={() => editCurrentImage('icon')}
                                    class="px-3 py-1.5 border border-slate-200 hover:border-slate-300 text-slate-600 bg-white hover:bg-slate-50 rounded-xl text-[11px] font-bold transition flex items-center gap-1 cursor-pointer shadow-sm active:scale-95"
                                >
                                    <i class="ti ti-crop text-xs"></i>
                                    Edit Gambar Saat Ini
                                </button>
                            </div>
                        {/if}

                        <div
                            class="flex items-start gap-2.5 bg-slate-50 p-3.5 rounded-2xl border border-slate-100"
                        >
                            <i
                                class="ti ti-info-circle text-slate-400 text-base mt-0.5"
                            ></i>
                            <div>
                                <span
                                    class="text-[10px] font-black text-slate-700 uppercase tracking-tight block"
                                    >Rekomendasi Dimensi Icon</span
                                >
                                <p
                                    class="text-[10px] text-slate-400 font-semibold mt-0.5 leading-relaxed"
                                >
                                    Disarankan menggunakan format PNG
                                    transparan.<br />
                                    Rasio:
                                    <strong class="text-slate-600"
                                        >1:1 (Persegi)</strong
                                    >. Resolusi Minimum:
                                    <strong class="text-slate-600"
                                        >128 x 128 px</strong
                                    >.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        class="bg-white border border-slate-100 shadow-sm rounded-3xl p-6 space-y-6"
                    >
                        <div
                            class="flex items-center gap-3 border-b border-slate-100 pb-4"
                        >
                            <div
                                class="p-2.5 bg-slate-50"
                                style="color: {secondaryColor}; background-color: {secondaryColor}1A; rounded-xl"
                            >
                                <i class="ti ti-palette text-lg"></i>
                            </div>
                            <div>
                                <h3
                                    class="font-outfit font-black text-slate-800 text-base leading-none"
                                >
                                    Warna Brand (Theme)
                                </h3>
                                <p
                                    class="text-xs text-slate-400 font-medium mt-1"
                                >
                                    Warna branding toko.
                                </p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <h4
                                class="text-xs font-black text-slate-700 uppercase tracking-tight mb-2 block"
                            >
                                PILIH PRESET WARNA
                            </h4>

                            <div class="flex flex-col gap-2.5">
                                {#each themePresets as preset}
                                    <button
                                        type="button"
                                        onclick={() => setPreset(preset.id)}
                                        class="w-full flex items-center justify-between p-4 rounded-2xl border transition-all cursor-pointer text-left
                                        {currentPreset === preset.id
                                            ? 'border-brand-teal bg-white shadow-soft ring-1 ring-brand-teal/20'
                                            : 'border-slate-100 hover:border-slate-200 bg-white hover:bg-slate-50'}"
                                        style={currentPreset === preset.id
                                            ? `border-color: ${preset.primary}; box-shadow: 0 0 0 1px ${preset.primary}33;`
                                            : ''}
                                    >
                                        <div class="flex items-center gap-4">
                                            <div class="flex -space-x-2">
                                                <div
                                                    class="w-6 h-6 rounded-full ring-2 ring-white z-10"
                                                    style="background-color: {preset.primary};"
                                                ></div>
                                                <div
                                                    class="w-6 h-6 rounded-full ring-2 ring-white"
                                                    style="background-color: {preset.secondary};"
                                                ></div>
                                            </div>
                                            <div>
                                                <p
                                                    class="font-outfit font-bold text-slate-800 text-sm leading-tight"
                                                >
                                                    {preset.name}
                                                </p>
                                                <p
                                                    class="text-[10px] font-black text-slate-400 uppercase tracking-widest mt-0.5"
                                                >
                                                    {preset.sub}
                                                </p>
                                            </div>
                                        </div>
                                        <div
                                            class="w-5 h-5 rounded-full flex items-center justify-center transition-colors {currentPreset ===
                                            preset.id
                                                ? 'text-white'
                                                : 'border-2 border-slate-200'}"
                                            style={currentPreset === preset.id
                                                ? `background-color: ${preset.primary};`
                                                : ''}
                                        >
                                            {#if currentPreset === preset.id}
                                                <i class="ti ti-check text-xs"
                                                ></i>
                                            {/if}
                                        </div>
                                    </button>
                                {/each}

                                <button
                                    type="button"
                                    class="w-full flex items-center justify-between p-4 rounded-2xl border transition-all cursor-pointer text-left mt-1
                                    {currentPreset === 'custom'
                                        ? 'border-brand-teal bg-white shadow-soft ring-1 ring-brand-teal/20'
                                        : 'border-slate-100 hover:border-slate-200 bg-white hover:bg-slate-50'}"
                                    style={currentPreset === 'custom'
                                        ? `border-color: ${form.primary_color}; box-shadow: 0 0 0 1px ${form.primary_color}33;`
                                        : ''}
                                    onclick={() => {
                                        forcedCustom = true;
                                    }}
                                >
                                    <div class="flex items-center gap-4">
                                        <div class="flex -space-x-2">
                                            <div
                                                class="w-6 h-6 rounded-full ring-2 ring-white z-10"
                                                style="background-color: {form.primary_color};"
                                            ></div>
                                            <div
                                                class="w-6 h-6 rounded-full ring-2 ring-white"
                                                style="background-color: {form.secondary_color};"
                                            ></div>
                                        </div>
                                        <div>
                                            <p
                                                class="font-outfit font-bold text-slate-800 text-sm leading-tight"
                                            >
                                                Kustom Warna Sendiri
                                            </p>
                                            <p
                                                class="text-[10px] font-black text-slate-400 uppercase tracking-widest mt-0.5"
                                            >
                                                CUSTOM BRAND
                                            </p>
                                        </div>
                                    </div>
                                    <div
                                        class="w-5 h-5 rounded-full flex items-center justify-center transition-colors {currentPreset ===
                                        'custom'
                                            ? 'text-white'
                                            : 'border-2 border-slate-200'}"
                                        style={currentPreset === 'custom'
                                            ? `background-color: ${form.primary_color};`
                                            : ''}
                                    >
                                        {#if currentPreset === 'custom'}
                                            <i class="ti ti-check text-xs"></i>
                                        {/if}
                                    </div>
                                </button>
                            </div>

                            {#if currentPreset === 'custom'}
                                <div
                                    class="p-4 bg-slate-50 rounded-2xl border border-slate-100 space-y-4 mt-4"
                                    transition:slide
                                >
                                    <ColorPicker
                                        id="primary_color"
                                        label="Primary Color"
                                        bind:value={form.primary_color}
                                        class="font-mono uppercase"
                                    />
                                    <ColorPicker
                                        id="secondary_color"
                                        label="Secondary Color"
                                        bind:value={form.secondary_color}
                                        class="font-mono uppercase"
                                    />
                                </div>
                            {/if}

                            <div class="mt-6 border-t border-slate-100 pt-5">
                                <h4
                                    class="text-xs font-black text-slate-700 uppercase tracking-tight mb-3 block"
                                >
                                    PILIH FONT WEBSITE
                                </h4>
                                <div
                                    class="grid grid-cols-1 md:grid-cols-2 gap-4"
                                >
                                    {#each fontOptions as font}
                                        <button
                                            type="button"
                                            onclick={() => {
                                                form.store_font = font.id;
                                            }}
                                            class="w-full flex items-center justify-between p-3.5 rounded-2xl border transition-all cursor-pointer text-left
                                            {form.store_font === font.id
                                                ? 'border-brand-teal bg-white shadow-soft ring-1 ring-brand-teal/20'
                                                : 'border-slate-100 hover:border-slate-200 bg-white hover:bg-slate-50'}"
                                        >
                                            <span
                                                class="font-bold text-sm text-slate-800"
                                                style="font-family: '{font.id}', sans-serif;"
                                            >
                                                {font.name}
                                            </span>
                                            <div
                                                class="w-5 h-5 rounded-full flex items-center justify-center transition-colors {form.store_font ===
                                                font.id
                                                    ? 'text-white'
                                                    : 'border-2 border-slate-200'}"
                                                style={form.store_font ===
                                                font.id
                                                    ? `background-color: ${form.primary_color || '#0f766e'};`
                                                    : ''}
                                            >
                                                {#if form.store_font === font.id}
                                                    <i
                                                        class="ti ti-check text-xs"
                                                    ></i>
                                                {/if}
                                            </div>
                                        </button>
                                    {/each}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </main>

    {#if isEditorOpen}
        <div
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            transition:fade
        >
            <!-- Backdrop -->
            <!-- svelte-ignore a11y_click_events_have_key_events -->
            <!-- svelte-ignore a11y_no_static_element_interactions -->
            <div
                class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"
                onclick={() => (isEditorOpen = false)}
            ></div>

            <!-- Modal Content -->
            <div
                class="bg-white rounded-3xl shadow-2xl max-w-4xl w-full max-h-[90vh] flex flex-col overflow-hidden relative z-10 border border-slate-100 transition-all scale-100"
                transition:slide
            >
                <!-- Header -->
                <div
                    class="px-6 py-5 border-b border-slate-100 flex items-center justify-between"
                >
                    <div>
                        <h3
                            class="font-outfit font-black text-slate-800 text-lg"
                        >
                            Edit {editorTarget === 'logo' ? 'Logo' : 'Icon'} Toko
                        </h3>
                        <p class="text-xs text-slate-400 font-medium">
                            Sesuaikan ukuran, rotasi, crop, dan transparansi
                            gambar sebelum diunggah
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <!-- Change image inline -->
                        <button
                            type="button"
                            onclick={() => editorInlineFileInput?.click()}
                            class="flex items-center gap-1.5 px-3 py-2 rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-600 text-xs font-bold transition"
                            title="Ganti gambar"
                        >
                            <i class="ti ti-photo-edit text-base"></i>
                            <span class="hidden sm:inline">Ganti Gambar</span>
                        </button>
                        <input
                            type="file"
                            accept="image/*"
                            bind:this={editorInlineFileInput}
                            onchange={openEditorInline}
                            class="hidden"
                        />
                        <button
                            type="button"
                            class="p-2 rounded-xl hover:bg-slate-50 text-slate-400 hover:text-slate-600 transition-colors"
                            onclick={() => (isEditorOpen = false)}
                            aria-label="Tutup"
                        >
                            <i class="ti ti-x text-xl"></i>
                        </button>
                    </div>
                </div>

                <!-- Body -->
                <div
                    class="p-6 overflow-y-auto flex-grow grid grid-cols-1 md:grid-cols-2 gap-8"
                >
                    <!-- Canvas Preview Area (Left) -->
                    <div
                        class="flex flex-col items-center justify-center bg-slate-50 rounded-2xl p-6 border border-slate-100 relative min-h-[300px]"
                    >
                        <!-- Crop mode indicator -->
                        {#if isCropMode}
                            <div
                                class="w-full mb-3 flex items-center gap-2 px-3 py-2 bg-amber-50 border border-amber-200 rounded-xl"
                            >
                                <i class="ti ti-crop text-amber-600 text-sm"
                                ></i>
                                <p
                                    class="text-[10px] font-bold text-amber-700 flex-grow"
                                >
                                    Seret di atas gambar untuk memilih area crop
                                </p>
                                <button
                                    type="button"
                                    onclick={cancelCrop}
                                    class="text-[10px] font-black text-amber-600 hover:text-amber-800 uppercase tracking-wider"
                                    >Batal</button
                                >
                            </div>
                        {/if}

                        <!-- svelte-ignore a11y_no_static_element_interactions -->
                        <!-- Canvas wrapper: aspect ratio follows output dimensions so preview is accurate -->
                        <div
                            bind:this={cropPreviewEl}
                            class="checkerboard rounded-xl shadow-inner border {isCropMode
                                ? 'border-amber-400'
                                : 'border-slate-200'} overflow-hidden relative select-none"
                            style="width: min(320px, 100%); aspect-ratio: {editorWidth} / {editorHeight}; max-height: 280px;"
                        >
                            <!-- svelte-ignore a11y_no_static_element_interactions -->
                            <canvas
                                bind:this={editorCanvas}
                                class="w-full h-full {isCropMode
                                    ? 'cursor-crosshair'
                                    : 'cursor-default'}"
                                onmousedown={isCropMode
                                    ? onCropPointerDown
                                    : undefined}
                                onmousemove={isCropMode
                                    ? onCropPointerMove
                                    : undefined}
                                onmouseup={isCropMode
                                    ? onCropPointerUp
                                    : undefined}
                                onmouseleave={isCropMode
                                    ? onCropPointerUp
                                    : undefined}
                            ></canvas>

                            <!-- Crop selection overlay -->
                            {#if isCropMode && cropBox.w > 2 && cropBox.h > 2}
                                <div
                                    class="absolute border-2 border-amber-400 bg-amber-400/10 pointer-events-none"
                                    style="left:{cropBox.x}px; top:{cropBox.y}px; width:{cropBox.w}px; height:{cropBox.h}px;"
                                >
                                    <!-- Corner handles -->
                                    <div
                                        class="absolute -top-1.5 -left-1.5 w-3 h-3 bg-amber-400 rounded-sm"
                                    ></div>
                                    <div
                                        class="absolute -top-1.5 -right-1.5 w-3 h-3 bg-amber-400 rounded-sm"
                                    ></div>
                                    <div
                                        class="absolute -bottom-1.5 -left-1.5 w-3 h-3 bg-amber-400 rounded-sm"
                                    ></div>
                                    <div
                                        class="absolute -bottom-1.5 -right-1.5 w-3 h-3 bg-amber-400 rounded-sm"
                                    ></div>
                                </div>
                            {/if}
                        </div>

                        <!-- Crop dimension info -->
                        {#if isCropMode && cropBox.w > 2 && cropBox.h > 2}
                            <p
                                class="text-[10px] text-amber-600 font-bold mt-2"
                            >
                                {Math.round(cropBox.w)} × {Math.round(
                                    cropBox.h,
                                )} px (preview)
                            </p>
                        {:else}
                            <p
                                class="text-[10px] text-slate-400 font-semibold mt-3 flex items-center gap-1"
                            >
                                <i class="ti ti-info-circle"></i> Kotak kotak-kotak
                                menandakan area transparan (tanpa background)
                            </p>
                        {/if}
                    </div>

                    <!-- Controls Area (Right) -->
                    <div class="flex flex-col justify-between space-y-6">
                        <div class="space-y-5">
                            <!-- Custom Width & Height Inputs -->
                            <div class="space-y-2">
                                <span
                                    class="text-xs font-bold text-slate-700 block"
                                    >Dimensi Output (Piksel)</span
                                >
                                <div class="flex items-center gap-3">
                                    <!-- Width Input -->
                                    <div class="flex-1 relative">
                                        <input
                                            type="number"
                                            min="16"
                                            max="4096"
                                            value={editorWidth}
                                            oninput={handleWidthInput}
                                            class="w-full pl-3 pr-8 py-2.5 border border-slate-200 rounded-xl text-xs font-bold focus:ring-2 focus:outline-none focus:border-brand-teal transition bg-slate-50 focus:bg-white"
                                        />
                                        <span
                                            class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-slate-400"
                                            >W (px)</span
                                        >
                                    </div>

                                    <!-- Lock Aspect Ratio -->
                                    <button
                                        type="button"
                                        onclick={() =>
                                            (lockAspectRatio =
                                                !lockAspectRatio)}
                                        class="p-2.5 rounded-xl border transition flex items-center justify-center {lockAspectRatio
                                            ? 'border-brand-teal bg-brand-teal/5 text-brand-teal'
                                            : 'border-slate-200 hover:border-slate-300 text-slate-400 bg-white'}"
                                        title={lockAspectRatio
                                            ? 'Kunci Rasio Aktif'
                                            : 'Kunci Rasio Nonaktif'}
                                    >
                                        <i
                                            class={lockAspectRatio
                                                ? 'ti ti-lock text-base'
                                                : 'ti ti-lock-open text-base'}
                                        ></i>
                                    </button>

                                    <!-- Height Input -->
                                    <div class="flex-1 relative">
                                        <input
                                            type="number"
                                            min="16"
                                            max="4096"
                                            value={editorHeight}
                                            oninput={handleHeightInput}
                                            class="w-full pl-3 pr-8 py-2.5 border border-slate-200 rounded-xl text-xs font-bold focus:ring-2 focus:outline-none focus:border-brand-teal transition bg-slate-50 focus:bg-white"
                                        />
                                        <span
                                            class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-slate-400"
                                            >H (px)</span
                                        >
                                    </div>

                                    <!-- Swap Button -->
                                    <button
                                        type="button"
                                        onclick={swapDimensions}
                                        class="p-2.5 rounded-xl border border-slate-200 hover:border-slate-300 text-slate-500 bg-white hover:bg-slate-50 transition flex items-center justify-center"
                                        title="Tukar Lebar & Tinggi"
                                    >
                                        <i
                                            class="ti ti-arrows-left-right text-base"
                                        ></i>
                                    </button>
                                </div>

                                <!-- Preset sizes -->
                                <div class="flex flex-wrap gap-1.5 mt-1">
                                    {#if editorTarget === 'logo'}
                                        <button
                                            type="button"
                                            onclick={() =>
                                                setPresetDimensions(1024, 576)}
                                            class="px-2 py-1 text-[10px] font-bold rounded-lg border border-slate-100 hover:border-slate-200 text-slate-500 hover:text-slate-700 bg-slate-50 transition"
                                        >
                                            1024x576 (16:9)
                                        </button>
                                        <button
                                            type="button"
                                            onclick={() =>
                                                setPresetDimensions(1200, 400)}
                                            class="px-2 py-1 text-[10px] font-bold rounded-lg border border-slate-100 hover:border-slate-200 text-slate-500 hover:text-slate-700 bg-slate-50 transition"
                                        >
                                            1200x400 (3:1)
                                        </button>
                                        <button
                                            type="button"
                                            onclick={() =>
                                                setPresetDimensions(1024, 512)}
                                            class="px-2 py-1 text-[10px] font-bold rounded-lg border border-slate-100 hover:border-slate-200 text-slate-500 hover:text-slate-700 bg-slate-50 transition"
                                        >
                                            1024x512 (2:1)
                                        </button>
                                    {:else}
                                        <button
                                            type="button"
                                            onclick={() =>
                                                setPresetDimensions(512, 512)}
                                            class="px-2 py-1 text-[10px] font-bold rounded-lg border border-slate-100 hover:border-slate-200 text-slate-500 hover:text-slate-700 bg-slate-50 transition"
                                        >
                                            512x512
                                        </button>
                                        <button
                                            type="button"
                                            onclick={() =>
                                                setPresetDimensions(256, 256)}
                                            class="px-2 py-1 text-[10px] font-bold rounded-lg border border-slate-100 hover:border-slate-200 text-slate-500 hover:text-slate-700 bg-slate-50 transition"
                                        >
                                            256x256
                                        </button>
                                        <button
                                            type="button"
                                            onclick={() =>
                                                setPresetDimensions(128, 128)}
                                            class="px-2 py-1 text-[10px] font-bold rounded-lg border border-slate-100 hover:border-slate-200 text-slate-500 hover:text-slate-700 bg-slate-50 transition"
                                        >
                                            128x128
                                        </button>
                                    {/if}
                                    <button
                                        type="button"
                                        onclick={() => {
                                            if (editorLoadedImage) {
                                                setPresetDimensions(
                                                    editorLoadedImage.naturalWidth,
                                                    editorLoadedImage.naturalHeight,
                                                );
                                            }
                                        }}
                                        class="px-2 py-1 text-[10px] font-bold rounded-lg border border-slate-100 hover:border-slate-200 text-slate-500 hover:text-slate-700 bg-slate-50 transition"
                                    >
                                        Asli
                                    </button>
                                </div>
                            </div>

                            <!-- Resize / Scale Slider -->
                            <div class="space-y-2">
                                <div class="flex justify-between items-center">
                                    <span
                                        class="text-xs font-bold text-slate-700"
                                        >Skala / Zoom (Ukuran)</span
                                    >
                                    <span
                                        class="text-xs font-bold text-slate-500"
                                        >{Math.round(editorScale * 100)}%</span
                                    >
                                </div>
                                <div class="flex items-center gap-3">
                                    <button
                                        type="button"
                                        class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-600 transition"
                                        onclick={() =>
                                            (editorScale = Math.max(
                                                0.2,
                                                +(editorScale - 0.1).toFixed(1),
                                            ))}
                                        aria-label="Kurangi skala"
                                    >
                                        <i class="ti ti-minus"></i>
                                    </button>
                                    <input
                                        type="range"
                                        min="0.2"
                                        max="3"
                                        step="0.1"
                                        bind:value={editorScale}
                                        class="flex-grow accent-brand-teal h-1.5 bg-slate-100 rounded-lg appearance-none cursor-pointer"
                                    />
                                    <button
                                        type="button"
                                        class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-600 transition"
                                        onclick={() =>
                                            (editorScale = Math.min(
                                                3,
                                                +(editorScale + 0.1).toFixed(1),
                                            ))}
                                        aria-label="Tambah skala"
                                    >
                                        <i class="ti ti-plus"></i>
                                    </button>
                                </div>

                                <!-- Fit / Fill Helper Buttons -->
                                <div class="flex gap-2 pt-1">
                                    <button
                                        type="button"
                                        onclick={fitImage}
                                        class="flex-1 py-1.5 px-3 text-[10px] font-bold rounded-xl border border-slate-100 hover:border-slate-200 text-slate-600 bg-slate-50 hover:bg-slate-100 transition text-center"
                                    >
                                        Pas ke Canvas (Fit)
                                    </button>
                                    <button
                                        type="button"
                                        onclick={fillImage}
                                        class="flex-1 py-1.5 px-3 text-[10px] font-bold rounded-xl border border-slate-100 hover:border-slate-200 text-slate-600 bg-slate-50 hover:bg-slate-100 transition text-center"
                                    >
                                        Penuhi Canvas (Fill)
                                    </button>
                                    <button
                                        type="button"
                                        onclick={() => (editorScale = 1.0)}
                                        class="py-1.5 px-3 text-[10px] font-bold rounded-xl border border-slate-100 hover:border-slate-200 text-slate-600 bg-slate-50 hover:bg-slate-100 transition text-center"
                                    >
                                        100%
                                    </button>
                                </div>
                            </div>

                            <!-- Rotation Controls -->
                            <div class="space-y-2">
                                <span
                                    class="text-xs font-bold text-slate-700 block"
                                    >Putar Gambar (Rotasi)</span
                                >
                                <div class="grid grid-cols-4 gap-2">
                                    {#each [0, 90, 180, 270] as deg}
                                        <button
                                            type="button"
                                            class="py-2 px-3 text-xs font-bold rounded-xl border transition-all text-center {editorRotation ===
                                            deg
                                                ? 'border-brand-teal bg-brand-teal/5 text-brand-teal'
                                                : 'border-slate-100 hover:border-slate-200 text-slate-600 bg-white'}"
                                            onclick={() =>
                                                (editorRotation = deg)}
                                        >
                                            {deg}°
                                        </button>
                                    {/each}
                                </div>
                            </div>

                            <!-- HD Mode / Quality Enhancement Controls -->
                            <div
                                class="p-4 bg-gradient-to-br from-indigo-50/50 to-brand-teal/5 rounded-2xl border border-slate-100 space-y-3"
                            >
                                <div class="flex items-center justify-between">
                                    <div>
                                        <span
                                            class="text-xs font-bold text-slate-800 flex items-center gap-1.5"
                                        >
                                            <i
                                                class="ti ti-sparkles text-indigo-500"
                                            ></i>
                                            Tingkatkan Kualitas (HD Mode)
                                        </span>
                                        <p
                                            class="text-[10px] text-slate-500 mt-0.5 font-medium"
                                        >
                                            Kurangi blur dan pertajam detail
                                            gambar secara otomatis
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        onclick={enhanceToHD}
                                        class="px-2.5 py-1.5 text-[10px] font-bold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white shadow-sm hover:shadow transition flex items-center gap-1 shrink-0"
                                    >
                                        <i class="ti ti-wand text-xs"></i>
                                        Auto HD
                                    </button>
                                </div>

                                <div class="space-y-2 pt-1">
                                    <div
                                        class="flex justify-between items-center"
                                    >
                                        <span
                                            class="text-[10px] font-bold text-slate-600"
                                        >
                                            Ketajaman (Sharpening)
                                        </span>
                                        <span
                                            class="text-[10px] font-bold text-indigo-600"
                                        >
                                            {Math.round(editorSharpen * 100)}%
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <button
                                            type="button"
                                            class="w-6 h-6 rounded-md bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-600 transition text-xs"
                                            onclick={() =>
                                                (editorSharpen = Math.max(
                                                    0,
                                                    +(
                                                        editorSharpen - 0.1
                                                    ).toFixed(1),
                                                ))}
                                            aria-label="Kurangi ketajaman"
                                        >
                                            <i class="ti ti-minus"></i>
                                        </button>
                                        <input
                                            type="range"
                                            min="0"
                                            max="1"
                                            step="0.05"
                                            bind:value={editorSharpen}
                                            class="flex-grow accent-indigo-600 h-1 bg-slate-200 rounded-lg appearance-none cursor-pointer"
                                        />
                                        <button
                                            type="button"
                                            class="w-6 h-6 rounded-md bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-600 transition text-xs"
                                            onclick={() =>
                                                (editorSharpen = Math.min(
                                                    1,
                                                    +(
                                                        editorSharpen + 0.1
                                                    ).toFixed(1),
                                                ))}
                                            aria-label="Tambah ketajaman"
                                        >
                                            <i class="ti ti-plus"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Background Removal Controls -->
                            <div
                                class="p-4 bg-slate-50 rounded-2xl border border-slate-100 space-y-3"
                            >
                                <div class="flex items-center justify-between">
                                    <div>
                                        <span
                                            class="text-xs font-bold text-slate-700 block"
                                            >Hapus Background Putih</span
                                        >
                                        <p
                                            class="text-[10px] text-slate-400 mt-0.5 font-medium"
                                        >
                                            Membuat latar belakang putih/terang
                                            menjadi transparan
                                        </p>
                                    </div>
                                    <Toggle bind:checked={editorRemoveBg} />
                                </div>

                                {#if editorRemoveBg}
                                    <div
                                        class="space-y-2 pt-2"
                                        transition:slide
                                    >
                                        <div
                                            class="flex justify-between items-center"
                                        >
                                            <span
                                                class="text-[10px] font-bold text-slate-600"
                                                >Sensitivitas Warna</span
                                            >
                                            <span
                                                class="text-[10px] font-bold text-slate-500"
                                                >{editorTolerance}</span
                                            >
                                        </div>
                                        <input
                                            type="range"
                                            min="5"
                                            max="150"
                                            step="5"
                                            bind:value={editorTolerance}
                                            class="w-full accent-brand-teal h-1.5 bg-slate-200 rounded-lg appearance-none cursor-pointer"
                                        />
                                        <p
                                            class="text-[9px] text-amber-600 font-bold leading-normal"
                                        >
                                            *Naikkan jika background putih tidak
                                            terhapus sempurna. Turunkan jika
                                            bagian logo ikut terhapus.
                                        </p>
                                    </div>
                                {/if}
                            </div>
                        </div>

                        <!-- Landscape warning banner for logo -->
                        {#if isLogoNotLandscape}
                            <div
                                class="flex items-start gap-2.5 bg-rose-50 border border-rose-200 rounded-2xl px-4 py-3"
                            >
                                <i
                                    class="ti ti-rotate-rectangle text-rose-500 text-base mt-0.5 shrink-0"
                                ></i>
                                <div>
                                    <span
                                        class="text-[11px] font-black text-rose-700 uppercase tracking-tight block"
                                        >Bukan Format Landscape</span
                                    >
                                    <p
                                        class="text-[10px] text-rose-600 font-semibold mt-0.5 leading-relaxed"
                                    >
                                        Logo toko <strong
                                            >wajib berformat landscape</strong
                                        >
                                        (lebar &gt; tinggi).<br />
                                        Sesuaikan dimensi lebar &amp; tinggi di atas,
                                        atau gunakan preset landscape, sebelum menekan
                                        Terapkan.
                                    </p>
                                </div>
                            </div>
                        {/if}

                        <!-- Footer Buttons -->
                        <div
                            class="flex items-center gap-3 pt-4 border-t border-slate-100"
                        >
                            <button
                                type="button"
                                class="py-3 px-4 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs font-bold transition text-center uppercase tracking-wider"
                                onclick={() => (isEditorOpen = false)}
                            >
                                Batal
                            </button>

                            {#if isCropMode}
                                <!-- Crop apply button -->
                                <button
                                    type="button"
                                    class="py-3 px-4 rounded-xl border border-amber-200 bg-amber-50 hover:bg-amber-100 text-amber-700 text-xs font-bold transition text-center uppercase tracking-wider flex items-center gap-2"
                                    onclick={applyCrop}
                                >
                                    <i class="ti ti-crop"></i>
                                    Terapkan Crop
                                </button>
                            {:else}
                                <!-- Crop mode toggle -->
                                <button
                                    type="button"
                                    class="py-3 px-4 rounded-xl border border-amber-200 bg-amber-50 hover:bg-amber-100 text-amber-700 text-xs font-bold transition text-center uppercase tracking-wider flex items-center gap-2"
                                    onclick={startCropMode}
                                    title="Mode Crop"
                                >
                                    <i class="ti ti-crop text-sm"></i>
                                    <span class="hidden sm:inline">Crop</span>
                                </button>

                                <button
                                    type="button"
                                    class="flex-1 py-3 px-4 rounded-xl text-white text-xs font-bold transition text-center uppercase tracking-wider shadow-md hover:shadow-lg hover:-translate-y-0.5 active:translate-y-0 disabled:opacity-50 disabled:cursor-not-allowed disabled:translate-y-0 disabled:shadow-none"
                                    style="background-color: {isLogoNotLandscape
                                        ? '#94a3b8'
                                        : primaryColor};"
                                    onclick={applyEdits}
                                    disabled={isLogoNotLandscape}
                                    title={isLogoNotLandscape
                                        ? 'Logo harus landscape sebelum dapat diterapkan'
                                        : 'Terapkan perubahan'}
                                >
                                    {#if isLogoNotLandscape}
                                        <i class="ti ti-alert-triangle mr-1"
                                        ></i>Wajib Landscape
                                    {:else}
                                        Terapkan
                                    {/if}
                                </button>
                            {/if}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    {/if}
</AdminLayout>

<style>
    .checkerboard {
        background-image:
            linear-gradient(45deg, #e2e8f0 25%, transparent 25%),
            linear-gradient(-45deg, #e2e8f0 25%, transparent 25%),
            linear-gradient(45deg, transparent 75%, #e2e8f0 75%),
            linear-gradient(-45deg, transparent 75%, #e2e8f0 75%);
        background-size: 20px 20px;
        background-position:
            0 0,
            0 10px,
            10px -10px,
            -10px 0px;
        background-color: #ffffff;
    }
</style>
