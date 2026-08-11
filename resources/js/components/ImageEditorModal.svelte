<script lang="ts">
    import { onMount } from 'svelte';

    let {
        isOpen = false,
        imageSrc = '',
        bannerType = 'hero',
        onSave,
        onClose,
    } = $props<{
        isOpen: boolean;
        imageSrc: string;
        bannerType?: 'hero' | 'side' | 'middle_wide' | 'popup' | 'general';
        onSave: (file: File, previewUrl: string) => void;
        onClose: () => void;
    }>();

    // Editor Tabs
    type TabType = 'crop' | 'rotate' | 'adjust' | 'remove_bg';
    let activeTab = $state<TabType>('crop');

    // Canvas & Image State
    let canvasEl = $state<HTMLCanvasElement>();
    let originalImage = $state<HTMLImageElement | null>(null);
    let originalWidth = $state(0);
    let originalHeight = $state(0);

    // Transformations
    let rotation = $state(0); // 0, 90, 180, 270
    let flipH = $state(false);
    let flipV = $state(false);

    // Crop State
    let aspectPreset = $state<string>('free'); // 'free', '16:9', '4:3', '1:1', '4:1', '21:9', '9:16', '3:1'
    let cropX = $state(0);
    let cropY = $state(0);
    let cropW = $state(0);
    let cropH = $state(0);
    let isDraggingCrop = $state(false);
    let cropDragStart = $state<{ x: number; y: number }>({ x: 0, y: 0 });

    // Dimension / Resize State
    let targetWidth = $state(0);
    let targetHeight = $state(0);
    let lockAspect = $state(true);

    // Adjustments & HD Filters
    let brightness = $state(100); // 0 to 200 (100 normal)
    let contrast = $state(100); // 0 to 200 (100 normal)
    let saturation = $state(100); // 0 to 200 (100 normal)
    let sharpness = $state(0); // 0 to 100
    let exportQuality = $state(92); // 50 to 100%

    // Remove BG & Transparency
    let removeBgMode = $state(false);
    let transparentBg = $state(true);
    let bgFillColor = $state('#ffffff');
    let bgTolerance = $state(30); // 0 to 100
    let keyColor = $state<{ r: number; g: number; b: number } | null>(null);

    // Zoom & Pan
    let zoomLevel = $state(100); // 50% to 200%

    // Recommended aspect ratios based on banner type
    const aspectPresets = $derived.by(() => {
        if (bannerType === 'hero') {
            return [
                { id: 'free', label: 'Utuh / Full (Gambar Asli 100%)' },
                { id: '1500:600', label: '1500 × 600 px (Rasio 2.5:1)' },
                { id: '1420:560', label: '1420 × 560 px (Lanskap)' },
                { id: '21:9', label: '21:9 (Ultrawide)' },
            ];
        } else if (bannerType === 'side') {
            return [
                { id: 'free', label: 'Utuh / Full (Gambar Asli 100%)' },
                { id: '750:300', label: '750 × 300 px (Rasio 2.5:1)' },
                { id: '3:4', label: '3:4 (Potret Side)' },
                { id: '1:1', label: '1:1 (Persegi)' },
            ];
        } else if (bannerType === 'middle_wide') {
            return [
                { id: 'free', label: 'Utuh / Full (Gambar Asli 100%)' },
                { id: '1800:500', label: '1800 × 500 px (Maks 500px)' },
                { id: '1800:400', label: '1800 × 400 px (Lebar 4.5:1)' },
                { id: '16:5', label: '16:5 (Lanskap Lebar)' },
            ];
        } else if (bannerType === 'popup') {
            return [
                { id: 'free', label: 'Utuh / Full (Gambar Asli 100%)' },
                { id: '4:5', label: '800 × 1000 px (Potret 4:5)' },
                { id: '1:1', label: '800 × 800 px (Persegi 1:1)' },
                { id: '9:16', label: '9:16 (Full Screen)' },
            ];
        }
        return [
            { id: 'free', label: 'Utuh / Full (Gambar Asli 100%)' },
            { id: '16:9', label: '16:9' },
            { id: '4:3', label: '4:3' },
            { id: '1:1', label: '1:1' },
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
            originalImage = img;
            originalWidth = img.naturalWidth;
            originalHeight = img.naturalHeight;
            targetWidth = img.naturalWidth;
            targetHeight = img.naturalHeight;

            // Set initial crop to full image
            cropX = 0;
            cropY = 0;
            cropW = img.naturalWidth;
            cropH = img.naturalHeight;

            // Reset transformations
            rotation = 0;
            flipH = false;
            flipV = false;
            brightness = 100;
            contrast = 100;
            saturation = 100;
            sharpness = 0;
            keyColor = null;
            removeBgMode = false;

            // Apply default aspect preset to 'free' (100% full original uncropped image)
            applyAspectPreset('free');
            renderCanvas();
        };
        img.src = src;
    }

    function applyAspectPreset(preset: string) {
        aspectPreset = preset;
        if (!originalWidth || !originalHeight) return;

        if (preset === 'free') {
            cropX = 0;
            cropY = 0;
            cropW = originalWidth;
            cropH = originalHeight;
            targetWidth = originalWidth;
            targetHeight = originalHeight;
            renderCanvas();
            return;
        }

        const [rw, rh] = preset.split(':').map(Number);
        if (!rw || !rh) return;

        const targetRatio = rw / rh;
        const currentRatio = originalWidth / originalHeight;

        if (currentRatio > targetRatio) {
            // Image is wider than target ratio -> crop width
            cropH = originalHeight;
            cropW = Math.round(originalHeight * targetRatio);
            cropX = Math.round((originalWidth - cropW) / 2);
            cropY = 0;
        } else {
            // Image is taller than target ratio -> crop height
            cropW = originalWidth;
            cropH = Math.round(originalWidth / targetRatio);
            cropX = 0;
            cropY = Math.round((originalHeight - cropH) / 2);
        }

        targetWidth = cropW;
        targetHeight = cropH;
        renderCanvas();
    }

    function handleWidthInput(val: number) {
        targetWidth = val;
        if (lockAspect && cropW > 0 && cropH > 0) {
            targetHeight = Math.round((val * cropH) / cropW);
        }
        renderCanvas();
    }

    function handleHeightInput(val: number) {
        targetHeight = val;
        if (lockAspect && cropW > 0 && cropH > 0) {
            targetWidth = Math.round((val * cropW) / cropH);
        }
        renderCanvas();
    }

    function rotateClockwise() {
        rotation = (rotation + 90) % 360;
        renderCanvas();
    }

    function rotateCounterClockwise() {
        rotation = (rotation - 90 + 360) % 360;
        renderCanvas();
    }

    function toggleFlipH() {
        flipH = !flipH;
        renderCanvas();
    }

    function toggleFlipV() {
        flipV = !flipV;
        renderCanvas();
    }

    function renderCanvas() {
        if (!canvasEl || !originalImage) return;

        const ctx = canvasEl.getContext('2d', { willReadFrequently: true });
        if (!ctx) return;

        const renderW = targetWidth > 0 ? targetWidth : cropW;
        const renderH = targetHeight > 0 ? targetHeight : cropH;

        canvasEl.width = renderW;
        canvasEl.height = renderH;

        ctx.clearRect(0, 0, renderW, renderH);

        // Fill background if transparentBg is false
        if (!transparentBg && bgFillColor) {
            ctx.fillStyle = bgFillColor;
            ctx.fillRect(0, 0, renderW, renderH);
        }

        ctx.save();

        // Position & Transformation matrix
        ctx.translate(renderW / 2, renderH / 2);
        ctx.rotate((rotation * Math.PI) / 180);
        ctx.scale(flipH ? -1 : 1, flipV ? -1 : 1);

        // Apply Brightness / Contrast / Saturation CSS filters
        ctx.filter = `brightness(${brightness}%) contrast(${contrast}%) saturate(${saturation}%)`;

        const drawW = (rotation % 180 !== 0) ? renderH : renderW;
        const drawH = (rotation % 180 !== 0) ? renderW : renderH;

        ctx.drawImage(
            originalImage,
            cropX,
            cropY,
            cropW,
            cropH,
            -drawW / 2,
            -drawH / 2,
            drawW,
            drawH
        );

        ctx.restore();

        // Apply Sharpness HD Pass if > 0
        if (sharpness > 0) {
            applySharpnessFilter(ctx, renderW, renderH, sharpness / 100);
        }

        // Apply Background Removal / Keying if keyColor is active
        if (keyColor && removeBgMode) {
            applyColorKeying(ctx, renderW, renderH, keyColor, bgTolerance);
        }
    }

    function applySharpnessFilter(
        ctx: CanvasRenderingContext2D,
        w: number,
        h: number,
        amount: number
    ) {
        try {
            const imgData = ctx.getImageData(0, 0, w, h);
            const data = imgData.data;
            const factor = amount * 0.5;

            // Simple 3x3 sharpen kernel
            for (let i = w * 4 + 4; i < data.length - w * 4 - 4; i += 4) {
                data[i] = Math.min(
                    255,
                    Math.max(0, data[i] + (data[i] - data[i - 4]) * factor)
                );
                data[i + 1] = Math.min(
                    255,
                    Math.max(0, data[i + 1] + (data[i + 1] - data[i - 4 + 1]) * factor)
                );
                data[i + 2] = Math.min(
                    255,
                    Math.max(0, data[i + 2] + (data[i + 2] - data[i - 4 + 2]) * factor)
                );
            }
            ctx.putImageData(imgData, 0, 0);
        } catch (e) {
            console.error('Error applying sharpness filter:', e);
        }
    }

    function applyColorKeying(
        ctx: CanvasRenderingContext2D,
        w: number,
        h: number,
        key: { r: number; g: number; b: number },
        tolerance: number
    ) {
        try {
            const imgData = ctx.getImageData(0, 0, w, h);
            const data = imgData.data;
            const tolSq = (tolerance * 2.55) ** 2;

            for (let i = 0; i < data.length; i += 4) {
                const dr = data[i] - key.r;
                const dg = data[i + 1] - key.g;
                const db = data[i + 2] - key.b;
                const distSq = dr * dr + dg * dg + db * db;

                if (distSq <= tolSq) {
                    if (transparentBg) {
                        data[i + 3] = 0; // Transparent
                    } else {
                        const hex = bgFillColor.replace('#', '');
                        data[i] = parseInt(hex.substring(0, 2), 16) || 255;
                        data[i + 1] = parseInt(hex.substring(2, 4), 16) || 255;
                        data[i + 2] = parseInt(hex.substring(4, 6), 16) || 255;
                    }
                }
            }
            ctx.putImageData(imgData, 0, 0);
        } catch (e) {
            console.error('Color keying error:', e);
        }
    }

    function handleCanvasClick(e: MouseEvent) {
        if (!removeBgMode || !canvasEl) return;
        const rect = canvasEl.getBoundingClientRect();
        const scaleX = canvasEl.width / rect.width;
        const scaleY = canvasEl.height / rect.height;

        const x = Math.round((e.clientX - rect.left) * scaleX);
        const y = Math.round((e.clientY - rect.top) * scaleY);

        const ctx = canvasEl.getContext('2d');
        if (!ctx) return;

        const pixel = ctx.getImageData(x, y, 1, 1).data;
        keyColor = { r: pixel[0], g: pixel[1], b: pixel[2] };
        renderCanvas();
    }

    function resetAll() {
        if (!originalImage) return;
        zoomLevel = 100;
        rotation = 0;
        flipH = false;
        flipV = false;
        brightness = 100;
        contrast = 100;
        saturation = 100;
        sharpness = 0;
        exportQuality = 92;
        removeBgMode = false;
        transparentBg = true;
        keyColor = null;
        cropX = 0;
        cropY = 0;
        cropW = originalWidth;
        cropH = originalHeight;
        targetWidth = originalWidth;
        targetHeight = originalHeight;
        aspectPreset = 'free';
        renderCanvas();
    }

    function handleSave() {
        if (!canvasEl) return;

        const format = transparentBg ? 'image/png' : 'image/jpeg';
        const quality = exportQuality / 100;

        canvasEl.toBlob(
            (blob) => {
                if (!blob) return;
                const ext = transparentBg ? 'png' : 'jpg';
                const file = new File([blob], `banner_edited_${Date.now()}.${ext}`, {
                    type: format,
                });
                const previewUrl = URL.createObjectURL(file);
                onSave(file, previewUrl);
                onClose();
            },
            format,
            quality
        );
    }
</script>

{#if isOpen}
    <div
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/80 backdrop-blur-sm p-3 sm:p-6 overflow-y-auto"
    >
        <div
            class="bg-white rounded-3xl shadow-2xl border border-slate-200 w-full max-w-5xl overflow-hidden flex flex-col max-h-[92vh] font-sans"
        >
            <!-- Modal Header -->
            <div
                class="px-6 py-4 border-b border-slate-100 bg-slate-50/80 flex items-center justify-between shrink-0"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-blue-600 to-indigo-600 flex items-center justify-center shadow-md shadow-blue-500/20 text-white shrink-0"
                    >
                        <i class="ti ti-photo-edit text-xl"></i>
                    </div>
                    <div>
                        <h2 class="font-outfit font-black text-lg text-slate-800 tracking-tight">
                            Studio Editor Gambar Banner
                        </h2>
                        <p class="text-xs text-slate-500 font-medium">
                            Rotasi, Potong, HD Filters, Hapus Background & Atur Resolusi
                        </p>
                    </div>
                </div>
                <button
                    onclick={onClose}
                    class="w-9 h-9 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-500 transition flex items-center justify-center cursor-pointer"
                >
                    <i class="ti ti-x text-lg"></i>
                </button>
            </div>

            <!-- Modal Content (Main Split) -->
            <div class="flex-grow grid grid-cols-1 lg:grid-cols-12 min-h-0 overflow-hidden">
                <!-- Left: Canvas Studio View (Col 7) -->
                <div
                    class="lg:col-span-7 bg-slate-900/95 p-4 sm:p-6 flex flex-col items-center justify-center relative overflow-hidden min-h-[320px] lg:min-h-[460px]"
                >
                    <!-- Checkerboard pattern for transparency -->
                    <div
                        class="absolute inset-0 opacity-20 pointer-events-none"
                        style="background-image: radial-gradient(#ffffff 1px, transparent 1px); background-size: 16px 16px;"
                    ></div>

                    <!-- Main Interactive Canvas -->
                    <div
                        class="relative max-w-full max-h-full flex items-center justify-center overflow-auto shadow-2xl rounded-xl border border-white/10 p-2 bg-black/40"
                    >
                        <!-- svelte-ignore a11y_click_events_have_key_events -->
                        <!-- svelte-ignore a11y_no_noninteractive_element_interactions -->
                        <canvas
                            bind:this={canvasEl}
                            onclick={handleCanvasClick}
                            class="max-w-full max-h-[380px] sm:max-h-[420px] object-contain rounded-lg transition-transform duration-150 {removeBgMode
                                ? 'cursor-crosshair'
                                : 'cursor-default'}"
                            style="transform: scale({zoomLevel / 100});"
                        ></canvas>
                    </div>

                    <!-- Canvas Floating Controls -->
                    <div
                        class="absolute bottom-3 left-1/2 -translate-x-1/2 flex items-center gap-2 bg-slate-800/90 backdrop-blur-md px-3.5 py-1.5 rounded-full border border-white/10 shadow-lg text-white text-xs"
                    >
                        <span class="text-slate-300 text-[11px] font-semibold mr-1">Zoom</span>
                        <button
                            onclick={() => (zoomLevel = Math.max(50, zoomLevel - 25))}
                            class="w-6 h-6 rounded-full bg-slate-700 hover:bg-slate-600 flex items-center justify-center font-bold"
                        >
                            -
                        </button>
                        <span class="w-12 text-center font-mono font-bold text-sky-400">{zoomLevel}%</span>
                        <button
                            onclick={() => (zoomLevel = Math.min(200, zoomLevel + 25))}
                            class="w-6 h-6 rounded-full bg-slate-700 hover:bg-slate-600 flex items-center justify-center font-bold"
                        >
                            +
                        </button>
                        <span class="w-px h-4 bg-white/20 mx-1"></span>
                        <button
                            type="button"
                            onclick={resetAll}
                            class="text-slate-300 hover:text-white flex items-center gap-1 hover:underline transition text-[11px] cursor-pointer"
                        >
                            <i class="ti ti-refresh text-sm"></i>
                            Reset
                        </button>
                    </div>
                </div>

                <!-- Right: Editing Controls Panel (Col 5) -->
                <div class="lg:col-span-5 flex flex-col bg-white border-t lg:border-t-0 lg:border-l border-slate-200 overflow-hidden">
                    <!-- Tab Buttons -->
                    <div class="grid grid-cols-4 border-b border-slate-100 bg-slate-50/50 p-1.5 gap-1 shrink-0 text-xs font-bold">
                        <button
                            onclick={() => (activeTab = 'crop')}
                            class="py-2 rounded-xl flex flex-col items-center gap-1 transition {activeTab === 'crop' ? 'bg-white text-blue-600 shadow-sm border border-slate-200/80' : 'text-slate-500 hover:text-slate-800'}"
                        >
                            <i class="ti ti-crop text-base"></i>
                            <span>Potong</span>
                        </button>
                        <button
                            onclick={() => (activeTab = 'rotate')}
                            class="py-2 rounded-xl flex flex-col items-center gap-1 transition {activeTab === 'rotate' ? 'bg-white text-blue-600 shadow-sm border border-slate-200/80' : 'text-slate-500 hover:text-slate-800'}"
                        >
                            <i class="ti ti-rotate-clockwise text-base"></i>
                            <span>Rotasi</span>
                        </button>
                        <button
                            onclick={() => (activeTab = 'adjust')}
                            class="py-2 rounded-xl flex flex-col items-center gap-1 transition {activeTab === 'adjust' ? 'bg-white text-blue-600 shadow-sm border border-slate-200/80' : 'text-slate-500 hover:text-slate-800'}"
                        >
                            <i class="ti ti-adjustments-horizontal text-base"></i>
                            <span>HD Filter</span>
                        </button>
                        <button
                            onclick={() => (activeTab = 'remove_bg')}
                            class="py-2 rounded-xl flex flex-col items-center gap-1 transition {activeTab === 'remove_bg' ? 'bg-white text-blue-600 shadow-sm border border-slate-200/80' : 'text-slate-500 hover:text-slate-800'}"
                        >
                            <i class="ti ti-wand text-base"></i>
                            <span>Hapus BG</span>
                        </button>
                    </div>

                    <!-- Tab Panel Content (Scrollable) -->
                    <div class="flex-grow p-5 space-y-5 overflow-y-auto min-h-0 text-slate-700 text-xs">
                        {#if activeTab === 'crop'}
                            <!-- CROP & DIMENSION TAB -->
                            <div class="space-y-4">
                                <h3 class="font-outfit font-bold text-sm text-slate-800 flex items-center gap-2">
                                    <i class="ti ti-aspect-ratio text-blue-600 text-base"></i>
                                    Rasio Aspek & Dimensi Presets
                                </h3>

                                <div class="grid grid-cols-2 gap-2">
                                    {#each aspectPresets as p}
                                        <button
                                            onclick={() => applyAspectPreset(p.id)}
                                            class="p-2.5 rounded-xl border text-left font-medium transition flex items-center justify-between {aspectPreset === p.id ? 'border-blue-500 bg-blue-50/50 text-blue-700 font-bold shadow-xs' : 'border-slate-200 hover:border-slate-300 text-slate-600'}"
                                        >
                                            <span>{p.label}</span>
                                            {#if aspectPreset === p.id}
                                                <i class="ti ti-check text-blue-600"></i>
                                            {/if}
                                        </button>
                                    {/each}
                                </div>

                                <div class="pt-3 border-t border-slate-100 space-y-3">
                                    <div class="flex items-center justify-between">
                                        <h4 class="font-bold text-slate-800">Atur Ukuran Resolusi (px)</h4>
                                        <label class="flex items-center gap-1.5 cursor-pointer text-slate-600 font-medium">
                                            <input type="checkbox" bind:checked={lockAspect} class="rounded text-blue-600 focus:ring-blue-500" />
                                            <span>Kunci Rasio</span>
                                        </label>
                                    </div>

                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <span class="text-[11px] text-slate-500 font-medium block mb-1">Lebar (W)</span>
                                            <input
                                                type="number"
                                                value={targetWidth}
                                                oninput={(e) => handleWidthInput(Number((e.target as HTMLInputElement).value))}
                                                class="w-full px-3 py-2 border border-slate-200 rounded-xl text-slate-800 font-bold focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                            />
                                        </div>
                                        <div>
                                            <span class="text-[11px] text-slate-500 font-medium block mb-1">Tinggi (H)</span>
                                            <input
                                                type="number"
                                                value={targetHeight}
                                                oninput={(e) => handleHeightInput(Number((e.target as HTMLInputElement).value))}
                                                class="w-full px-3 py-2 border border-slate-200 rounded-xl text-slate-800 font-bold focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        {:else if activeTab === 'rotate'}
                            <!-- ROTATE & FLIP TAB -->
                            <div class="space-y-4">
                                <h3 class="font-outfit font-bold text-sm text-slate-800 flex items-center gap-2">
                                    <i class="ti ti-rotate text-blue-600 text-base"></i>
                                    Rotasi & Pemutaran Gambar
                                </h3>

                                <div class="grid grid-cols-2 gap-2.5">
                                    <button
                                        onclick={rotateCounterClockwise}
                                        class="p-3 rounded-xl border border-slate-200 hover:bg-slate-50 flex items-center justify-center gap-2 font-bold text-slate-700 transition"
                                    >
                                        <i class="ti ti-rotate-2 text-lg text-blue-600"></i>
                                        -90° Kiri
                                    </button>
                                    <button
                                        onclick={rotateClockwise}
                                        class="p-3 rounded-xl border border-slate-200 hover:bg-slate-50 flex items-center justify-center gap-2 font-bold text-slate-700 transition"
                                    >
                                        <i class="ti ti-rotate-clockwise-2 text-lg text-blue-600"></i>
                                        +90° Kanan
                                    </button>
                                </div>

                                <div class="pt-3 border-t border-slate-100 space-y-2">
                                    <h4 class="font-bold text-slate-800 mb-2">Cermin (Flip Image)</h4>
                                    <div class="grid grid-cols-2 gap-2.5">
                                        <button
                                            onclick={toggleFlipH}
                                            class="p-3 rounded-xl border flex items-center justify-center gap-2 font-bold transition {flipH ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-slate-200 hover:bg-slate-50 text-slate-700'}"
                                        >
                                            <i class="ti ti-flip-horizontal text-lg"></i>
                                            Flip Horizontal
                                        </button>
                                        <button
                                            onclick={toggleFlipV}
                                            class="p-3 rounded-xl border flex items-center justify-center gap-2 font-bold transition {flipV ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-slate-200 hover:bg-slate-50 text-slate-700'}"
                                        >
                                            <i class="ti ti-flip-vertical text-lg"></i>
                                            Flip Vertical
                                        </button>
                                    </div>
                                </div>
                            </div>
                        {:else if activeTab === 'adjust'}
                            <!-- ADJUSTMENTS & HD FILTERS TAB -->
                            <div class="space-y-4">
                                <h3 class="font-outfit font-bold text-sm text-slate-800 flex items-center gap-2">
                                    <i class="ti ti-sparkles text-blue-600 text-base"></i>
                                    Kualitas & Filter HD
                                </h3>

                                <div class="space-y-3">
                                    <div>
                                        <div class="flex justify-between font-bold text-slate-700 mb-1">
                                            <span>Kecerahan (Brightness)</span>
                                            <span class="text-blue-600 font-mono">{brightness}%</span>
                                        </div>
                                        <input
                                            type="range"
                                            min="30"
                                            max="170"
                                            bind:value={brightness}
                                            oninput={renderCanvas}
                                            class="w-full accent-blue-600 cursor-pointer"
                                        />
                                    </div>

                                    <div>
                                        <div class="flex justify-between font-bold text-slate-700 mb-1">
                                            <span>Kontras (Contrast)</span>
                                            <span class="text-blue-600 font-mono">{contrast}%</span>
                                        </div>
                                        <input
                                            type="range"
                                            min="30"
                                            max="170"
                                            bind:value={contrast}
                                            oninput={renderCanvas}
                                            class="w-full accent-blue-600 cursor-pointer"
                                        />
                                    </div>

                                    <div>
                                        <div class="flex justify-between font-bold text-slate-700 mb-1">
                                            <span>Saturasi Warna</span>
                                            <span class="text-blue-600 font-mono">{saturation}%</span>
                                        </div>
                                        <input
                                            type="range"
                                            min="0"
                                            max="200"
                                            bind:value={saturation}
                                            oninput={renderCanvas}
                                            class="w-full accent-blue-600 cursor-pointer"
                                        />
                                    </div>

                                    <div>
                                        <div class="flex justify-between font-bold text-slate-700 mb-1">
                                            <span>Ketajaman HD (Clarity)</span>
                                            <span class="text-blue-600 font-mono">{sharpness}%</span>
                                        </div>
                                        <input
                                            type="range"
                                            min="0"
                                            max="100"
                                            bind:value={sharpness}
                                            oninput={renderCanvas}
                                            class="w-full accent-blue-600 cursor-pointer"
                                        />
                                    </div>

                                    <div class="pt-3 border-t border-slate-100">
                                        <div class="flex justify-between font-bold text-slate-700 mb-1">
                                            <span>Kualitas Hasil Ekspor</span>
                                            <span class="text-blue-600 font-mono">{exportQuality}%</span>
                                        </div>
                                        <input
                                            type="range"
                                            min="50"
                                            max="100"
                                            bind:value={exportQuality}
                                            class="w-full accent-blue-600 cursor-pointer"
                                        />
                                    </div>
                                </div>
                            </div>
                        {:else if activeTab === 'remove_bg'}
                            <!-- REMOVE BG TAB -->
                            <div class="space-y-4">
                                <h3 class="font-outfit font-bold text-sm text-slate-800 flex items-center gap-2">
                                    <i class="ti ti-wand text-blue-600 text-base"></i>
                                    Hapus & Ganti Background
                                </h3>

                                <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 text-amber-800 text-[11px] flex gap-2">
                                    <i class="ti ti-info-circle text-amber-600 text-base shrink-0"></i>
                                    <span>Aktifkan mode lalu <strong>klik pada warna background gambar</strong> di kanvas sebelah kiri untuk menghapusnya secara otomatis.</span>
                                </div>

                                <button
                                    onclick={() => {
                                        removeBgMode = !removeBgMode;
                                        renderCanvas();
                                    }}
                                    class="w-full py-2.5 px-4 rounded-xl font-bold flex items-center justify-center gap-2 transition cursor-pointer {removeBgMode ? 'bg-emerald-600 text-white shadow-md' : 'bg-slate-800 text-white hover:bg-slate-700'}"
                                >
                                    <i class="ti ti-pointer text-base"></i>
                                    {removeBgMode ? 'Mode Pemilih Warna Aktif (Klik Gambar)' : 'Aktifkan Tool Hapus Background'}
                                </button>

                                {#if keyColor}
                                    <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl space-y-3">
                                        <div class="flex items-center justify-between">
                                            <span class="font-bold text-slate-700">Warna Terpilih:</span>
                                            <div class="flex items-center gap-2">
                                                <div
                                                    class="w-5 h-5 rounded-full border border-slate-300 shadow-xs"
                                                    style="background-color: rgb({keyColor.r}, {keyColor.g}, {keyColor.b});"
                                                ></div>
                                                <button
                                                    onclick={() => { keyColor = null; renderCanvas(); }}
                                                    class="text-red-500 hover:text-red-700 font-bold"
                                                >
                                                    Hapus
                                                </button>
                                            </div>
                                        </div>

                                        <div>
                                            <div class="flex justify-between font-bold text-slate-700 mb-1">
                                                <span>Toleransi Penghapusan Warna</span>
                                                <span class="text-blue-600 font-mono">{bgTolerance}</span>
                                            </div>
                                            <input
                                                type="range"
                                                min="5"
                                                max="80"
                                                bind:value={bgTolerance}
                                                oninput={renderCanvas}
                                                class="w-full accent-blue-600 cursor-pointer"
                                            />
                                        </div>
                                    </div>
                                {/if}

                                <div class="pt-3 border-t border-slate-100 space-y-3">
                                    <h4 class="font-bold text-slate-800">Isi Background Pengganti</h4>
                                    <div class="grid grid-cols-2 gap-2">
                                        <button
                                            onclick={() => { transparentBg = true; renderCanvas(); }}
                                            class="p-2.5 rounded-xl border text-center font-bold transition flex items-center justify-center gap-2 {transparentBg ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-slate-200 hover:bg-slate-50 text-slate-700'}"
                                        >
                                            <i class="ti ti-brand-abstract text-base"></i>
                                            Transparan (PNG)
                                        </button>
                                        <button
                                            onclick={() => { transparentBg = false; renderCanvas(); }}
                                            class="p-2.5 rounded-xl border text-center font-bold transition flex items-center justify-center gap-2 {!transparentBg ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-slate-200 hover:bg-slate-50 text-slate-700'}"
                                        >
                                            <i class="ti ti-palette text-base"></i>
                                            Warna Padat
                                        </button>
                                    </div>

                                    {#if !transparentBg}
                                        <div class="flex items-center gap-3 pt-1">
                                            <span class="font-bold text-slate-700">Pilih Warna:</span>
                                            <input
                                                type="color"
                                                bind:value={bgFillColor}
                                                oninput={renderCanvas}
                                                class="w-10 h-8 rounded border border-slate-200 cursor-pointer p-0"
                                            />
                                            <span class="font-mono text-slate-600 uppercase font-bold">{bgFillColor}</span>
                                        </div>
                                    {/if}
                                </div>
                            </div>
                        {/if}
                    </div>

                    <!-- Action Footer -->
                    <div class="p-4 border-t border-slate-100 bg-slate-50/80 flex items-center justify-between shrink-0 gap-3">
                        <button
                            onclick={onClose}
                            class="px-4 py-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-100 text-slate-700 font-bold transition cursor-pointer"
                        >
                            Batal
                        </button>
                        <button
                            onclick={handleSave}
                            class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-bold transition shadow-md shadow-blue-500/20 flex items-center gap-2 cursor-pointer"
                        >
                            <i class="ti ti-check text-base"></i>
                            Terapkan & Simpan Gambar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
{/if}
