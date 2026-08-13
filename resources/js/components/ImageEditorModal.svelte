<script lang="ts">
    import { onMount } from 'svelte';
    import { slide } from 'svelte/transition';

    let {
        isOpen = false,
        imageSrc = '',
        bannerType = 'hero',
        onSave,
        onClose,
    } = $props<{
        isOpen: boolean;
        imageSrc: string;
        bannerType?: 'hero' | 'hero_mobile' | 'side' | 'middle_wide' | 'popup' | 'general';
        onSave: (file: File, previewUrl: string) => void;
        onClose: () => void;
    }>();

    // Editor Core State
    let editorCanvas = $state<HTMLCanvasElement | null>(null);
    let editorLoadedImage = $state<HTMLImageElement | null>(null);
    let editorWidth = $state(1500);
    let editorHeight = $state(400);
    let lockAspectRatio = $state(true);

    // Independent Scale Controls (Lebar X & Tinggi Y untuk Perlebaran Bebas Gambar)
    let editorScaleX = $state(1.0);
    let editorScaleY = $state(1.0);
    let linkScales = $state(true); // Default true (skala proporsional 1:1)

    let editorRotation = $state(0); // 0, 90, 180, 270
    let editorSharpen = $state(0); // 0.0 to 1.0
    let editorRemoveBg = $state(false);
    let editorTolerance = $state(30); // 5 to 150

    // Interactive Crop State
    let isCropMode = $state(false);
    let cropDragging = $state(false);
    let cropStart = $state({ x: 0, y: 0 });
    let cropBox = $state({ x: 0, y: 0, w: 0, h: 0 });

    let fileInputEl = $state<HTMLInputElement>();

    // Crop Confirmation State
    let cropConfirmPending = $state(false);
    let pendingCropData = $state<{ srcX: number; srcY: number; srcW: number; srcH: number } | null>(null);

    // Dynamic preset sizes based on bannerType
    const presets = $derived.by(() => {
        if (bannerType === 'hero') {
            return [
                { w: 1750, h: 500, label: '1750 × 500 (Ideal 3.5:1)' },
                { w: 1500, h: 428, label: '1500 × 428 (Rasio 3.5:1)' },
                { w: 1400, h: 400, label: '1400 × 400 (Alt Kompak)' },
            ];
        } else if (bannerType === 'hero_mobile') {
            return [
                { w: 1400, h: 500, label: '1400 × 500 (Ideal Mobile 2.8:1)' },
                { w: 1750, h: 625, label: '1750 × 625 (Rasio 2.8:1)' },
                { w: 1050, h: 375, label: '1050 × 375 (Alt Kompak)' },
            ];
        } else if (bannerType === 'side') {
            return [
                { w: 750, h: 300, label: '750 × 300 (Ideal 2.5:1)' },
                { w: 720, h: 288, label: '720 × 288 (Alt Medium)' },
                { w: 600, h: 240, label: '600 × 240 (Alt Kompak)' },
                { w: 600, h: 600, label: '600 × 600 (1:1 Persegi)' },
            ];
        } else if (bannerType === 'middle_wide') {
            return [
                { w: 1800, h: 600, label: '1800 × 600 (Ideal)' },
                { w: 1440, h: 400, label: '1440 × 400 (Alt Medium)' },
                { w: 1200, h: 330, label: '1200 × 330 (Alt Kompak)' },
                { w: 1800, h: 400, label: '1800 × 400 (Lebar 4.5:1)' },
            ];
        } else if (bannerType === 'popup') {
            return [
                { w: 800, h: 1000, label: '800 × 1000 (Ideal Potret 4:5)' },
                { w: 800, h: 800, label: '800 × 800 (1:1 Persegi)' },
                { w: 600, h: 750, label: '600 × 750 (Alt Medium)' },
                { w: 720, h: 1280, label: '720 × 1280 (9:16)' },
            ];
        }
        return [
            { w: 1200, h: 675, label: '16:9' },
            { w: 800, h: 600, label: '4:3' },
            { w: 600, h: 600, label: '1:1' },
        ];
    });

    // Load image when imageSrc changes or modal opens
    $effect(() => {
        if (isOpen && imageSrc) {
            loadImage(imageSrc);
        }
    });

    function loadImage(src: string) {
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = () => {
            editorLoadedImage = img;
            isCropMode = false;
            cropBox = { x: 0, y: 0, w: 0, h: 0 };
            editorRotation = 0;
            editorSharpen = 0;
            editorRemoveBg = false;

            // Set initial dimensions according to bannerType defaults
            if (bannerType === 'hero') {
                editorWidth = 1750;
                editorHeight = 500;
            } else if (bannerType === 'hero_mobile') {
                editorWidth = 1400;
                editorHeight = 500;
            } else if (bannerType === 'side') {
                editorWidth = 750;
                editorHeight = 300;
            } else if (bannerType === 'middle_wide') {
                editorWidth = 1800;
                editorHeight = 600;
            } else if (bannerType === 'popup') {
                editorWidth = 800;
                editorHeight = 1000;
            } else {
                editorWidth = img.naturalWidth;
                editorHeight = img.naturalHeight;
            }

            editorScaleX = 1.0;
            editorScaleY = 1.0;
            linkScales = true;

            fillImage();
        };
        img.src = src;
    }

    function handleInlineFileChange(e: Event) {
        const target = e.target as HTMLInputElement;
        const file = target.files?.[0];
        if (file) {
            loadImage(URL.createObjectURL(file));
        }
    }

    function setPresetDimensions(w: number, h: number) {
        editorWidth = w;
        editorHeight = h;
        fillImage();
    }

    function setOriginalImageDimensions() {
        if (!editorLoadedImage) return;
        const isRotated90 = editorRotation % 180 !== 0;
        editorWidth = isRotated90 ? editorLoadedImage.naturalHeight : editorLoadedImage.naturalWidth;
        editorHeight = isRotated90 ? editorLoadedImage.naturalWidth : editorLoadedImage.naturalHeight;
        editorScaleX = 1.0;
        editorScaleY = 1.0;
    }

    function handleWidthInput(e: Event) {
        const val = parseInt((e.target as HTMLInputElement).value, 10);
        if (isNaN(val) || val < 16) return;
        if (lockAspectRatio && editorWidth > 0) {
            const ratio = editorHeight / editorWidth;
            editorWidth = val;
            editorHeight = Math.round(val * ratio);
        } else {
            editorWidth = val;
        }
        fillImage();
    }

    function handleHeightInput(e: Event) {
        const val = parseInt((e.target as HTMLInputElement).value, 10);
        if (isNaN(val) || val < 16) return;
        if (lockAspectRatio && editorHeight > 0) {
            const ratio = editorWidth / editorHeight;
            editorHeight = val;
            editorWidth = Math.round(val * ratio);
        } else {
            editorHeight = val;
        }
        fillImage();
    }

    function swapDimensions() {
        const temp = editorWidth;
        editorWidth = editorHeight;
        editorHeight = temp;
        fillImage();
    }

    // Scaling & Stretching Helper Functions
    function fitImage() {
        if (!editorLoadedImage) return;
        const isRotated90 = editorRotation % 180 !== 0;
        const imgW = isRotated90 ? editorLoadedImage.naturalHeight : editorLoadedImage.naturalWidth;
        const imgH = isRotated90 ? editorLoadedImage.naturalWidth : editorLoadedImage.naturalHeight;
        const scaleW = editorWidth / imgW;
        const scaleH = editorHeight / imgH;
        const s = Math.min(scaleW, scaleH);
        editorScaleX = s;
        editorScaleY = s;
    }

    function fillImage() {
        if (!editorLoadedImage) return;
        const isRotated90 = editorRotation % 180 !== 0;
        const imgW = isRotated90 ? editorLoadedImage.naturalHeight : editorLoadedImage.naturalWidth;
        const imgH = isRotated90 ? editorLoadedImage.naturalWidth : editorLoadedImage.naturalHeight;
        const scaleW = editorWidth / imgW;
        const scaleH = editorHeight / imgH;
        const s = Math.max(scaleW, scaleH);
        editorScaleX = s;
        editorScaleY = s;
    }

    function stretchWidthToFill() {
        if (!editorLoadedImage) return;
        const isRotated90 = editorRotation % 180 !== 0;
        const imgW = isRotated90 ? editorLoadedImage.naturalHeight : editorLoadedImage.naturalWidth;
        editorScaleX = editorWidth / imgW;
    }

    function stretchHeightToFill() {
        if (!editorLoadedImage) return;
        const isRotated90 = editorRotation % 180 !== 0;
        const imgH = isRotated90 ? editorLoadedImage.naturalWidth : editorLoadedImage.naturalHeight;
        editorScaleY = editorHeight / imgH;
    }

    function stretchFullToFill() {
        if (!editorLoadedImage) return;
        const isRotated90 = editorRotation % 180 !== 0;
        const imgW = isRotated90 ? editorLoadedImage.naturalHeight : editorLoadedImage.naturalWidth;
        const imgH = isRotated90 ? editorLoadedImage.naturalWidth : editorLoadedImage.naturalHeight;
        editorScaleX = editorWidth / imgW;
        editorScaleY = editorHeight / imgH;
    }

    function handleScaleXChange(newScaleX: number) {
        const val = Math.max(0.1, Math.min(3.0, newScaleX));
        editorScaleX = val;
        if (linkScales) {
            editorScaleY = val;
        }
    }

    function handleScaleYChange(newScaleY: number) {
        const val = Math.max(0.1, Math.min(3.0, newScaleY));
        editorScaleY = val;
        if (linkScales) {
            editorScaleX = val;
        }
    }

    function enhanceToHD() {
        editorSharpen = 0.4;
    }

    // Crop Pointer Events
    function toggleCropMode() {
        isCropMode = !isCropMode;
        if (isCropMode) {
            cropBox = { x: 0, y: 0, w: 0, h: 0 };
        }
    }

    function cancelCrop() {
        isCropMode = false;
        cropBox = { x: 0, y: 0, w: 0, h: 0 };
    }

    function getCropEventPos(e: MouseEvent, element: HTMLElement) {
        const rect = element.getBoundingClientRect();
        return {
            x: e.clientX - rect.left,
            y: e.clientY - rect.top,
        };
    }

    function onCropPointerDown(e: MouseEvent) {
        if (!isCropMode || !editorCanvas) return;
        const pos = getCropEventPos(e, editorCanvas);
        cropDragging = true;
        cropStart = pos;
        cropBox = { x: pos.x, y: pos.y, w: 0, h: 0 };
    }

    function onCropPointerMove(e: MouseEvent) {
        if (!cropDragging || !editorCanvas) return;
        const pos = getCropEventPos(e, editorCanvas);
        const x = Math.min(pos.x, cropStart.x);
        const y = Math.min(pos.y, cropStart.y);
        const w = Math.abs(pos.x - cropStart.x);
        const h = Math.abs(pos.y - cropStart.y);

        const canvasRect = editorCanvas.getBoundingClientRect();
        const clampedX = Math.max(0, Math.min(x, canvasRect.width));
        const clampedY = Math.max(0, Math.min(y, canvasRect.height));
        const clampedW = Math.min(w, canvasRect.width - clampedX);
        const clampedH = Math.min(h, canvasRect.height - clampedY);

        cropBox = { x: clampedX, y: clampedY, w: clampedW, h: clampedH };
    }

    function onCropPointerUp() {
        if (!cropDragging) return;
        cropDragging = false;

        if (!editorCanvas || cropBox.w < 4 || cropBox.h < 4) {
            return;
        }

        const canvasRect = editorCanvas.getBoundingClientRect();
        const scaleX = editorCanvas.width / canvasRect.width;
        const scaleY = editorCanvas.height / canvasRect.height;

        const srcX = Math.round(cropBox.x * scaleX);
        const srcY = Math.round(cropBox.y * scaleY);
        const srcW = Math.round(cropBox.w * scaleX);
        const srcH = Math.round(cropBox.h * scaleY);

        if (srcW < 1 || srcH < 1) return;

        // Show confirmation instead of immediately cropping
        pendingCropData = { srcX, srcY, srcW, srcH };
        cropConfirmPending = true;
    }

    function confirmCrop() {
        if (!pendingCropData || !editorCanvas) return;
        const { srcX, srcY, srcW, srcH } = pendingCropData;

        const ctx = editorCanvas.getContext('2d');
        if (!ctx) return;

        const croppedData = ctx.getImageData(srcX, srcY, srcW, srcH);

        const tempCanvas = document.createElement('canvas');
        tempCanvas.width = srcW;
        tempCanvas.height = srcH;
        const tempCtx = tempCanvas.getContext('2d');
        if (!tempCtx) return;

        tempCtx.putImageData(croppedData, 0, 0);

        const newImg = new Image();
        newImg.onload = () => {
            editorLoadedImage = newImg;
            editorWidth = srcW;
            editorHeight = srcH;
            editorScaleX = 1.0;
            editorScaleY = 1.0;
            isCropMode = false;
            cropBox = { x: 0, y: 0, w: 0, h: 0 };
        };
        newImg.src = tempCanvas.toDataURL();

        cropConfirmPending = false;
        pendingCropData = null;
    }

    function cancelCropConfirm() {
        cropConfirmPending = false;
        pendingCropData = null;
        // Keep isCropMode active so user can re-select
    }

    // High Quality Bicubic Rendering Engine (Dengan Independent Scale X & Y)
    $effect(() => {
        if (isOpen && editorLoadedImage && editorCanvas) {
            renderEditorCanvas(
                editorLoadedImage,
                editorScaleX,
                editorScaleY,
                editorRotation,
                editorRemoveBg,
                editorTolerance,
                editorWidth,
                editorHeight,
                editorSharpen
            );
        }
    });

    function renderEditorCanvas(
        img: HTMLImageElement,
        scaleX: number,
        scaleY: number,
        rotation: number,
        removeBg: boolean,
        tolerance: number,
        canvasWidth: number,
        canvasHeight: number,
        sharpenAmount: number
    ) {
        if (!editorCanvas) return;
        const ctx = editorCanvas.getContext('2d');
        if (!ctx) return;

        editorCanvas.width = canvasWidth;
        editorCanvas.height = canvasHeight;

        ctx.clearRect(0, 0, canvasWidth, canvasHeight);

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

        const destImgData = ctx.createImageData(canvasWidth, canvasHeight);
        const destData = destImgData.data;

        function getCubicWeight(t: number) {
            const absT = Math.abs(t);
            if (absT <= 1) {
                return 1.5 * absT * absT * absT - 2.5 * absT * absT + 1;
            } else if (absT < 2) {
                return -0.5 * absT * absT * absT + 2.5 * absT * absT - 4 * absT + 2;
            }
            return 0;
        }

        const halfCW = canvasWidth / 2;
        const halfCH = canvasHeight / 2;
        const halfTW = tempW / 2;
        const halfTH = tempH / 2;

        for (let y = 0; y < canvasHeight; y++) {
            const v = halfTH + (y - halfCH) / scaleY;
            const yRow = Math.floor(v);
            const dy = v - yRow;

            const wY0 = getCubicWeight(dy + 1);
            const wY1 = getCubicWeight(dy);
            const wY2 = getCubicWeight(dy - 1);
            const wY3 = getCubicWeight(dy - 2);

            const destRowOffset = y * canvasWidth * 4;

            for (let x = 0; x < canvasWidth; x++) {
                const u = halfTW + (x - halfCW) / scaleX;
                const destOffset = destRowOffset + x * 4;

                if (u < -1 || u >= tempW + 1 || v < -1 || v >= tempH + 1) {
                    destData[destOffset + 3] = 0;
                    continue;
                }

                const xCol = Math.floor(u);
                const dx = u - xCol;

                const wX0 = getCubicWeight(dx + 1);
                const wX1 = getCubicWeight(dx);
                const wX2 = getCubicWeight(dx - 1);
                const wX3 = getCubicWeight(dx - 2);

                let r = 0, g = 0, b = 0, a = 0;

                for (let m = -1; m <= 2; m++) {
                    const py = yRow + m;
                    if (py < 0 || py >= tempH) continue;

                    const weightY =
                        m === -1 ? wY0 : m === 0 ? wY1 : m === 1 ? wY2 : wY3;

                    const srcRowOffset = py * tempW * 4;

                    for (let n = -1; n <= 2; n++) {
                        const px = xCol + n;
                        if (px < 0 || px >= tempW) continue;

                        const weightX =
                            n === -1 ? wX0 : n === 0 ? wX1 : n === 1 ? wX2 : wX3;
                        const weight = weightX * weightY;

                        const srcOffset = srcRowOffset + px * 4;

                        r += srcData[srcOffset] * weight;
                        g += srcData[srcOffset + 1] * weight;
                        b += srcData[srcOffset + 2] * weight;
                        a += srcData[srcOffset + 3] * weight;
                    }
                }

                destData[destOffset] = Math.min(255, Math.max(0, r));
                destData[destOffset + 1] = Math.min(255, Math.max(0, g));
                destData[destOffset + 2] = Math.min(255, Math.max(0, b));
                destData[destOffset + 3] = Math.min(255, Math.max(0, a));
            }
        }

        // Apply background removal (white keying) if active
        if (removeBg) {
            for (let i = 0; i < destData.length; i += 4) {
                const pr = destData[i];
                const pg = destData[i + 1];
                const pb = destData[i + 2];

                const isNearWhite =
                    pr >= 255 - tolerance &&
                    pg >= 255 - tolerance &&
                    pb >= 255 - tolerance;

                if (isNearWhite) {
                    const maxDiff = Math.max(
                        Math.abs(pr - pg),
                        Math.abs(pg - pb),
                        Math.abs(pr - pb)
                    );
                    if (maxDiff < 20) {
                        destData[i + 3] = 0;
                    }
                }
            }
        }

        // Apply Sharpening pass if requested (> 0)
        if (sharpenAmount > 0) {
            const sharpenData = ctx.createImageData(canvasWidth, canvasHeight);
            const src = destData;
            const dst = sharpenData.data;

            dst.set(src);

            const amount = sharpenAmount * 0.8;
            const kernel = [
                0, -amount, 0,
                -amount, 1 + 4 * amount, -amount,
                0, -amount, 0
            ];

            for (let y = 1; y < canvasHeight - 1; y++) {
                for (let x = 1; x < canvasWidth - 1; x++) {
                    const idx = (y * canvasWidth + x) * 4;

                    if (src[idx + 3] === 0) continue;

                    for (let c = 0; c < 3; c++) {
                        let val = 0;
                        let kIdx = 0;

                        for (let ky = -1; ky <= 1; ky++) {
                            for (let kx = -1; kx <= 1; kx++) {
                                const pIdx = ((y + ky) * canvasWidth + (x + kx)) * 4 + c;
                                val += src[pIdx] * kernel[kIdx++];
                            }
                        }

                        dst[idx + c] = Math.min(255, Math.max(0, val));
                    }
                }
            }

            ctx.putImageData(sharpenData, 0, 0);
        } else {
            ctx.putImageData(destImgData, 0, 0);
        }
    }

    function handleSave() {
        if (!editorCanvas) return;

        editorCanvas.toBlob((blob) => {
            if (!blob) return;
            const file = new File([blob], `banner_${Date.now()}.png`, {
                type: 'image/png',
            });
            const previewUrl = URL.createObjectURL(file);
            onSave(file, previewUrl);
            onClose();
        }, 'image/png');
    }
</script>

{#if isOpen}
    <div
        class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6"
    >
        <!-- Backdrop -->
        <!-- svelte-ignore a11y_click_events_have_key_events -->
        <!-- svelte-ignore a11y_no_static_element_interactions -->
        <div
            class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"
            onclick={onClose}
        ></div>

        <!-- Modal Content -->
        <div
            class="bg-white rounded-3xl shadow-2xl {bannerType === 'hero' || bannerType === 'hero_mobile' || bannerType === 'middle_wide' ? 'max-w-5xl' : 'max-w-4xl'} w-full max-h-[92vh] flex flex-col overflow-hidden relative z-10 border border-slate-100 transition-all font-sans"
        >
            <!-- Header -->
            <div
                class="px-6 py-5 border-b border-slate-100 flex items-center justify-between shrink-0"
            >
                <div>
                    <h3 class="font-outfit font-black text-slate-800 text-lg">
                        Studio Editor Gambar Banner
                    </h3>
                    <p class="text-xs text-slate-400 font-medium">
                        Sesuaikan ukuran, rotasi, crop, perlebaran gambar, dan transparansi sebelum diunggah
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        onclick={() => fileInputEl?.click()}
                        class="flex items-center gap-1.5 px-3 py-2 rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-600 text-xs font-bold transition cursor-pointer"
                        title="Ganti gambar"
                    >
                        <i class="ti ti-photo-edit text-base"></i>
                        <span class="hidden sm:inline">Ganti Gambar</span>
                    </button>
                    <input
                        type="file"
                        accept="image/*"
                        bind:this={fileInputEl}
                        onchange={handleInlineFileChange}
                        class="hidden"
                    />

                    <button
                        type="button"
                        onclick={onClose}
                        class="w-9 h-9 rounded-xl bg-slate-50 hover:bg-slate-100 text-slate-400 hover:text-slate-600 flex items-center justify-center transition cursor-pointer"
                    >
                        <i class="ti ti-x text-lg"></i>
                    </button>
                </div>
            </div>

            <!-- Body Grid (Split Left Preview & Right Controls) -->
            <div class="p-6 overflow-y-auto flex-grow grid grid-cols-1 {bannerType === 'hero' || bannerType === 'hero_mobile' || bannerType === 'middle_wide' ? 'lg:grid-cols-[1fr_360px]' : 'lg:grid-cols-2'} gap-8">
                <!-- Left: Studio Preview Area -->
                <div class="flex flex-col items-center justify-center bg-slate-50/50 rounded-2xl p-4 border border-slate-100 min-h-[300px]">
                    <!-- Crop Toggle Button -->
                    <div class="w-full flex justify-between items-center mb-3">
                        <span class="text-xs font-bold text-slate-700">Preview Studio Gambar</span>
                        <button
                            type="button"
                            onclick={toggleCropMode}
                            class="px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer {isCropMode
                                ? 'bg-amber-500 text-white shadow-sm'
                                : 'bg-white border border-slate-200 hover:border-slate-300 text-slate-700'}"
                        >
                            <i class="ti ti-crop text-sm"></i>
                            {isCropMode ? 'Batal Crop' : 'Pilih Area Crop (Potong)'}
                        </button>
                    </div>

                    {#if isCropMode}
                        <div class="w-full bg-amber-50 border border-amber-200 rounded-xl px-3 py-1.5 mb-2 flex items-center justify-between">
                            <p class="text-[10px] font-bold text-amber-700">
                                Seret di atas gambar untuk memilih area crop
                            </p>
                            <button
                                type="button"
                                onclick={cancelCrop}
                                class="text-[10px] font-black text-amber-600 hover:text-amber-800 uppercase tracking-wider cursor-pointer"
                            >
                                Batal
                            </button>
                        </div>
                    {/if}

                    <!-- Canvas Wrapper: aspect ratio follows output dimensions -->
                    <div
                        class="checkerboard rounded-xl shadow-inner border {isCropMode
                            ? 'border-amber-400'
                            : 'border-slate-200'} overflow-hidden relative select-none w-full"
                        style="aspect-ratio: {editorWidth} / {editorHeight};"
                    >
                        <!-- svelte-ignore a11y_no_static_element_interactions -->
                        <canvas
                            bind:this={editorCanvas}
                            class="w-full h-full {isCropMode ? 'cursor-crosshair' : 'cursor-default'}"
                            onmousedown={isCropMode ? onCropPointerDown : undefined}
                            onmousemove={isCropMode ? onCropPointerMove : undefined}
                            onmouseup={isCropMode ? onCropPointerUp : undefined}
                            onmouseleave={isCropMode ? onCropPointerUp : undefined}
                        ></canvas>

                        <!-- Interactive Crop Selection Box -->
                        {#if isCropMode && cropBox.w > 2 && cropBox.h > 2}
                            <div
                                class="absolute border-2 border-amber-400 bg-amber-400/10 pointer-events-none"
                                style="left:{cropBox.x}px; top:{cropBox.y}px; width:{cropBox.w}px; height:{cropBox.h}px;"
                            >
                                <div class="absolute -top-1.5 -left-1.5 w-3 h-3 bg-amber-400 rounded-sm"></div>
                                <div class="absolute -top-1.5 -right-1.5 w-3 h-3 bg-amber-400 rounded-sm"></div>
                                <div class="absolute -bottom-1.5 -left-1.5 w-3 h-3 bg-amber-400 rounded-sm"></div>
                                <div class="absolute -bottom-1.5 -right-1.5 w-3 h-3 bg-amber-400 rounded-sm"></div>
                            </div>
                        {/if}
                    </div>

                    {#if isCropMode && cropBox.w > 2 && cropBox.h > 2}
                        <p class="text-[10px] text-amber-600 font-bold mt-2 font-mono">
                            {Math.round(cropBox.w)} × {Math.round(cropBox.h)} px (preview)
                        </p>
                    {:else}
                        <p class="text-[10px] text-slate-400 font-semibold mt-3 flex items-center gap-1">
                            <i class="ti ti-info-circle"></i> Kotak kotak-kotak menandakan area transparan (tanpa background)
                        </p>
                    {/if}
                </div>

                <!-- Right: Controls Area -->
                <div class="flex flex-col justify-between space-y-6">
                    <div class="space-y-5">
                        <!-- Dimensi Output: locked badge for hero, editable for others -->
                        <div class="space-y-2">
                            {#if bannerType === 'hero'}
                                <!-- Locked size indicator for hero desktop banner -->
                                <div class="flex items-center gap-2 p-3 bg-blue-50 border border-blue-100 rounded-xl">
                                    <i class="ti ti-device-desktop text-blue-500 text-sm shrink-0"></i>
                                    <div>
                                        <span class="text-xs font-black text-blue-700">Ukuran Output Terkunci: 1750 × 500 px</span>
                                        <p class="text-[10px] text-blue-400 font-medium mt-0.5">Desktop · Rasio 3.5:1 · Lebar sejajar header</p>
                                    </div>
                                </div>
                            {:else if bannerType === 'hero_mobile'}
                                <!-- Locked size indicator for hero mobile banner -->
                                <div class="flex items-center gap-2 p-3 bg-emerald-50 border border-emerald-100 rounded-xl">
                                    <i class="ti ti-device-mobile text-emerald-500 text-sm shrink-0"></i>
                                    <div>
                                        <span class="text-xs font-black text-emerald-700">Ukuran Output Terkunci: 1400 × 500 px</span>
                                        <p class="text-[10px] text-emerald-400 font-medium mt-0.5">Mobile · Rasio 2.8:1</p>
                                    </div>
                                </div>
                            {:else if bannerType === 'middle_wide'}
                                <!-- Locked size indicator for middle wide banner -->
                                <div class="flex items-center gap-2 p-3 bg-purple-50 border border-purple-100 rounded-xl">
                                    <i class="ti ti-lock text-purple-500 text-sm shrink-0"></i>
                                    <div>
                                        <span class="text-xs font-black text-purple-700">Ukuran Output Terkunci: 1800 × 600 px</span>
                                        <p class="text-[10px] text-purple-400 font-medium mt-0.5">Rasio 3:1 · Ukuran ideal Banner Lebar Tengah</p>
                                    </div>
                                </div>
                            {:else}
                            <span class="text-xs font-bold text-slate-700 block">Dimensi Output (Piksel)</span>
                            <div class="flex items-center gap-3">
                                <!-- Width Input -->
                                <div class="flex-1 relative">
                                    <input
                                        type="number"
                                        min="16"
                                        max="4096"
                                        value={editorWidth}
                                        oninput={handleWidthInput}
                                        class="w-full pl-3 pr-8 py-2.5 border border-slate-200 rounded-xl text-xs font-bold focus:ring-2 focus:outline-none focus:border-brand-teal transition bg-slate-50 focus:bg-white font-mono"
                                    />
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-slate-400">W (px)</span>
                                </div>

                                <!-- Lock Aspect Ratio -->
                                <button
                                    type="button"
                                    onclick={() => (lockAspectRatio = !lockAspectRatio)}
                                    class="p-2.5 rounded-xl border transition flex items-center justify-center cursor-pointer {lockAspectRatio
                                        ? 'border-brand-teal bg-brand-teal/5 text-brand-teal'
                                        : 'border-slate-200 hover:border-slate-300 text-slate-400 bg-white'}"
                                    title={lockAspectRatio ? 'Kunci Rasio Aktif' : 'Kunci Rasio Nonaktif'}
                                >
                                    <i class={lockAspectRatio ? 'ti ti-lock text-base' : 'ti ti-lock-open text-base'}></i>
                                </button>

                                <!-- Height Input -->
                                <div class="flex-1 relative">
                                    <input
                                        type="number"
                                        min="16"
                                        max="4096"
                                        value={editorHeight}
                                        oninput={handleHeightInput}
                                        class="w-full pl-3 pr-8 py-2.5 border border-slate-200 rounded-xl text-xs font-bold focus:ring-2 focus:outline-none focus:border-brand-teal transition bg-slate-50 focus:bg-white font-mono"
                                    />
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-slate-400">H (px)</span>
                                </div>

                                <!-- Swap Button -->
                                <button
                                    type="button"
                                    onclick={swapDimensions}
                                    class="p-2.5 rounded-xl border border-slate-200 hover:border-slate-300 text-slate-500 bg-white hover:bg-slate-50 transition flex items-center justify-center cursor-pointer"
                                    title="Tukar Lebar & Tinggi"
                                >
                                    <i class="ti ti-arrows-left-right text-base"></i>
                                </button>
                            </div>

                            <!-- Preset sizes -->
                            <div class="flex flex-wrap gap-1.5 mt-1">
                                {#each presets as p}
                                    <button
                                        type="button"
                                        onclick={() => setPresetDimensions(p.w, p.h)}
                                        class="px-2.5 py-1 text-[10px] font-bold rounded-lg border border-slate-100 hover:border-slate-200 text-slate-500 hover:text-slate-700 bg-slate-50 transition cursor-pointer"
                                    >
                                        {p.label}
                                    </button>
                                {/each}
                                <button
                                    type="button"
                                    onclick={setOriginalImageDimensions}
                                    class="px-2.5 py-1 text-[10px] font-bold rounded-lg border border-slate-100 hover:border-slate-200 text-slate-500 hover:text-slate-700 bg-slate-50 transition cursor-pointer"
                                >
                                    Asli (Ukuran Gambar)
                                </button>
                            </div>
                            {/if}
                        </div>

                        <!-- Scale / Zoom & Independent Stretch (Perlebaran Gambar) Controls -->
                        <div class="p-3.5 bg-slate-50 rounded-2xl border border-slate-100 space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                                    <i class="ti ti-arrows-maximize text-blue-600"></i>
                                    Skala & Perlebaran Gambar
                                </span>
                                <button
                                    type="button"
                                    onclick={() => (linkScales = !linkScales)}
                                    class="px-2 py-1 text-[10px] font-bold rounded-lg border transition flex items-center gap-1 cursor-pointer {linkScales
                                        ? 'border-blue-500 bg-blue-50 text-blue-700'
                                        : 'border-amber-400 bg-amber-50 text-amber-700'}"
                                    title={linkScales ? 'Skala Terkunci (1:1 Proporsional)' : 'Skala Bebas (Perlebar X & Y Terpisah)'}
                                >
                                    <i class={linkScales ? 'ti ti-link text-xs' : 'ti ti-link-off text-xs'}></i>
                                    <span>{linkScales ? 'Terkunci 1:1' : 'Bebas X/Y'}</span>
                                </button>
                            </div>

                            <!-- Scale X Slider (Lebar ↔) -->
                            <div class="space-y-1.5">
                                <div class="flex justify-between items-center text-[11px]">
                                    <span class="font-bold text-slate-600 flex items-center gap-1">
                                        <i class="ti ti-arrows-horizontal text-blue-500"></i>
                                        Lebar Gambar (Perlebar ke Samping ↔)
                                    </span>
                                    <span class="font-mono font-bold text-slate-700">{Math.round(editorScaleX * 100)}%</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        class="w-7 h-7 rounded-lg bg-white border border-slate-200 hover:bg-slate-100 flex items-center justify-center text-slate-600 transition cursor-pointer text-xs font-bold"
                                        onclick={() => handleScaleXChange(+(editorScaleX - 0.05).toFixed(2))}
                                        aria-label="Kurangi perlebaran"
                                    >
                                        <i class="ti ti-minus"></i>
                                    </button>
                                    <input
                                        type="range"
                                        min="0.1"
                                        max="3"
                                        step="0.05"
                                        bind:value={editorScaleX}
                                        oninput={(e) => handleScaleXChange(Number((e.target as HTMLInputElement).value))}
                                        class="flex-grow accent-blue-600 h-1.5 bg-slate-200 rounded-lg appearance-none cursor-pointer"
                                    />
                                    <button
                                        type="button"
                                        class="w-7 h-7 rounded-lg bg-white border border-slate-200 hover:bg-slate-100 flex items-center justify-center text-slate-600 transition cursor-pointer text-xs font-bold"
                                        onclick={() => handleScaleXChange(+(editorScaleX + 0.05).toFixed(2))}
                                        aria-label="Tambah perlebaran"
                                    >
                                        <i class="ti ti-plus"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Scale Y Slider (Tinggi ↕) -->
                            {#if !linkScales}
                                <div class="space-y-1.5 pt-1" transition:slide>
                                    <div class="flex justify-between items-center text-[11px]">
                                        <span class="font-bold text-slate-600 flex items-center gap-1">
                                            <i class="ti ti-arrows-vertical text-indigo-500"></i>
                                            Tinggi Gambar (Meninggikan ↕)
                                        </span>
                                        <span class="font-mono font-bold text-slate-700">{Math.round(editorScaleY * 100)}%</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button
                                            type="button"
                                            class="w-7 h-7 rounded-lg bg-white border border-slate-200 hover:bg-slate-100 flex items-center justify-center text-slate-600 transition cursor-pointer text-xs font-bold"
                                            onclick={() => handleScaleYChange(+(editorScaleY - 0.05).toFixed(2))}
                                            aria-label="Kurangi tinggi"
                                        >
                                            <i class="ti ti-minus"></i>
                                        </button>
                                        <input
                                            type="range"
                                            min="0.1"
                                            max="3"
                                            step="0.05"
                                            bind:value={editorScaleY}
                                            oninput={(e) => handleScaleYChange(Number((e.target as HTMLInputElement).value))}
                                            class="flex-grow accent-indigo-600 h-1.5 bg-slate-200 rounded-lg appearance-none cursor-pointer"
                                        />
                                        <button
                                            type="button"
                                            class="w-7 h-7 rounded-lg bg-white border border-slate-200 hover:bg-slate-100 flex items-center justify-center text-slate-600 transition cursor-pointer text-xs font-bold"
                                            onclick={() => handleScaleYChange(+(editorScaleY + 0.05).toFixed(2))}
                                            aria-label="Tambah tinggi"
                                        >
                                            <i class="ti ti-plus"></i>
                                        </button>
                                    </div>
                                </div>
                            {/if}

                            <!-- Fit / Stretch / Fill Helper Buttons -->
                            <div class="grid grid-cols-3 gap-1.5 pt-1">
                                <button
                                    type="button"
                                    onclick={stretchWidthToFill}
                                    class="py-1.5 px-2 text-[10px] font-bold rounded-xl border border-slate-200 hover:border-blue-400 text-slate-700 bg-white hover:bg-blue-50 transition text-center cursor-pointer flex items-center justify-center gap-1"
                                    title="Lebarkan gambar memenuhi frame kiri-kanan"
                                >
                                    <i class="ti ti-arrows-maximize text-blue-600"></i>
                                    Lebarkan ↔
                                </button>
                                <button
                                    type="button"
                                    onclick={stretchFullToFill}
                                    class="py-1.5 px-2 text-[10px] font-bold rounded-xl border border-slate-200 hover:border-blue-400 text-slate-700 bg-white hover:bg-blue-50 transition text-center cursor-pointer flex items-center justify-center gap-1"
                                    title="Tarik gambar memenuhi 100% frame (Full Stretch)"
                                >
                                    <i class="ti ti-transform text-blue-600"></i>
                                    Stretch Full ⤢
                                </button>
                                <button
                                    type="button"
                                    onclick={fitImage}
                                    class="py-1.5 px-2 text-[10px] font-bold rounded-xl border border-slate-200 hover:border-slate-300 text-slate-600 bg-white hover:bg-slate-50 transition text-center cursor-pointer"
                                >
                                    Proporsional
                                </button>
                            </div>
                        </div>

                        <!-- Rotation Controls -->
                        <div class="space-y-2">
                            <span class="text-xs font-bold text-slate-700 block">Putar Gambar (Rotasi)</span>
                            <div class="grid grid-cols-4 gap-2">
                                {#each [0, 90, 180, 270] as deg}
                                    <button
                                        type="button"
                                        class="py-2 px-3 text-xs font-bold rounded-xl border transition-all text-center cursor-pointer {editorRotation === deg
                                            ? 'border-brand-teal bg-brand-teal/5 text-brand-teal'
                                            : 'border-slate-100 hover:border-slate-200 text-slate-600 bg-white'}"
                                        onclick={() => (editorRotation = deg)}
                                    >
                                        {deg}°
                                    </button>
                                {/each}
                            </div>
                        </div>

                        <!-- HD Mode / Quality Enhancement Controls -->
                        <div class="p-4 bg-gradient-to-br from-indigo-50/50 to-brand-teal/5 rounded-2xl border border-slate-100 space-y-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                                        <i class="ti ti-sparkles text-indigo-500"></i>
                                        Tingkatkan Kualitas (HD Mode)
                                    </span>
                                    <p class="text-[10px] text-slate-500 mt-0.5 font-medium">
                                        Kurangi blur dan pertajam detail gambar secara otomatis
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    onclick={enhanceToHD}
                                    class="px-2.5 py-1.5 text-[10px] font-bold rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white shadow-sm hover:shadow transition flex items-center gap-1 shrink-0 cursor-pointer"
                                >
                                    <i class="ti ti-wand text-xs"></i>
                                    Auto HD
                                </button>
                            </div>

                            <div class="space-y-2 pt-1">
                                <div class="flex justify-between items-center">
                                    <span class="text-[10px] font-bold text-slate-600">Ketajaman (Sharpening)</span>
                                    <span class="text-[10px] font-bold text-indigo-600">{Math.round(editorSharpen * 100)}%</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <button
                                        type="button"
                                        class="w-6 h-6 rounded-md bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-600 transition text-xs cursor-pointer"
                                        onclick={() => (editorSharpen = Math.max(0, +(editorSharpen - 0.1).toFixed(1)))}
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
                                        class="w-6 h-6 rounded-md bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-600 transition text-xs cursor-pointer"
                                        onclick={() => (editorSharpen = Math.min(1, +(editorSharpen + 0.1).toFixed(1)))}
                                        aria-label="Tambah ketajaman"
                                    >
                                        <i class="ti ti-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Background Removal Controls -->
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100 space-y-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="text-xs font-bold text-slate-700 block">Hapus Background Putih</span>
                                    <p class="text-[10px] text-slate-400 mt-0.5 font-medium">
                                        Membuat latar belakang putih/terang menjadi transparan
                                    </p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" bind:checked={editorRemoveBg} class="sr-only peer" />
                                    <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600"></div>
                                </label>
                            </div>

                            {#if editorRemoveBg}
                                <div class="space-y-2 pt-2" transition:slide>
                                    <div class="flex justify-between items-center">
                                        <span class="text-[10px] font-bold text-slate-600">Sensitivitas Warna</span>
                                        <span class="text-[10px] font-bold text-slate-500 font-mono">{editorTolerance}</span>
                                    </div>
                                    <input
                                        type="range"
                                        min="5"
                                        max="150"
                                        step="5"
                                        bind:value={editorTolerance}
                                        class="w-full accent-brand-teal h-1.5 bg-slate-200 rounded-lg appearance-none cursor-pointer"
                                    />
                                    <p class="text-[9px] text-amber-600 font-bold leading-normal">
                                        *Naikkan jika background putih tidak terhapus sempurna. Turunkan jika gambar utama ikut terhapus.
                                    </p>
                                </div>
                            {/if}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Actions -->
            <div class="p-4 border-t border-slate-100 bg-slate-50/80 flex items-center justify-between shrink-0 gap-3">
                <button
                    type="button"
                    onclick={onClose}
                    class="px-5 py-2.5 rounded-xl border border-slate-300 hover:bg-slate-100 font-bold text-slate-600 transition cursor-pointer text-xs"
                >
                    Batal
                </button>
                <button
                    type="button"
                    onclick={handleSave}
                    class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-bold transition shadow-lg shadow-blue-500/25 flex items-center gap-2 cursor-pointer text-xs"
                >
                    <i class="ti ti-check text-base"></i>
                    Terapkan & Simpan Gambar
                </button>
            </div>

            <!-- Crop Confirmation Overlay -->
            {#if cropConfirmPending && pendingCropData}
                <!-- svelte-ignore a11y_no_static_element_interactions -->
                <div class="absolute inset-0 z-20 bg-slate-900/50 backdrop-blur-[2px] flex items-center justify-center rounded-3xl">
                    <div class="bg-white rounded-2xl shadow-2xl border border-amber-200 p-6 mx-6 max-w-sm w-full" transition:slide>
                        <div class="flex items-start gap-3 mb-4">
                            <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center shrink-0">
                                <i class="ti ti-crop text-amber-600 text-lg"></i>
                            </div>
                            <div>
                                <h4 class="font-outfit font-black text-slate-800 text-sm">Konfirmasi Pemotongan Gambar</h4>
                                <p class="text-[11px] text-slate-500 mt-0.5 font-medium leading-relaxed">
                                    Gambar akan dipotong ke area yang dipilih. Tindakan ini tidak dapat dibatalkan setelah diterapkan.
                                </p>
                            </div>
                        </div>

                        <div class="bg-amber-50 border border-amber-100 rounded-xl px-3 py-2 mb-5 flex items-center gap-2">
                            <i class="ti ti-ruler text-amber-500 text-sm shrink-0"></i>
                            <span class="text-[11px] font-bold text-amber-700 font-mono">
                                Area Crop: {pendingCropData.srcW} × {pendingCropData.srcH} px
                            </span>
                        </div>

                        <div class="flex gap-2">
                            <button
                                type="button"
                                onclick={cancelCropConfirm}
                                class="flex-1 py-2.5 px-4 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-600 font-bold text-xs transition cursor-pointer"
                            >
                                <i class="ti ti-arrow-back-up text-sm"></i>
                                Pilih Ulang
                            </button>
                            <button
                                type="button"
                                onclick={confirmCrop}
                                class="flex-1 py-2.5 px-4 rounded-xl bg-amber-500 hover:bg-amber-600 text-white font-bold text-xs transition shadow-sm shadow-amber-500/25 flex items-center justify-center gap-1.5 cursor-pointer"
                            >
                                <i class="ti ti-scissors text-sm"></i>
                                Ya, Potong Gambar
                            </button>
                        </div>
                    </div>
                </div>
            {/if}
        </div>
    </div>
{/if}

<style>
    .checkerboard {
        background-color: #f8fafc;
        background-image:
            linear-gradient(45deg, #e2e8f0 25%, transparent 25%),
            linear-gradient(-45deg, #e2e8f0 25%, transparent 25%),
            linear-gradient(45deg, transparent 75%, #e2e8f0 75%),
            linear-gradient(-45deg, transparent 75%, #e2e8f0 75%);
        background-size: 16px 16px;
        background-position:
            0 0,
            0 8px,
            8px -8px,
            -8px 0px;
    }
</style>
