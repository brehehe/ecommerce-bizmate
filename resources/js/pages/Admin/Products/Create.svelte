<script>
    import AdminLayout from '@/components/layouts/AdminLayout.svelte';
    import { page, useForm, Link, router } from '@inertiajs/svelte';
    import { showToast } from '@/utils/toast';
    import {
        store as adminProductsStore,
        index as adminProductsIndex,
        create as adminProductsCreate,
    } from '@/routes/admin/products';

    // Import new UI components
    import Input from '@/components/ui/Input.svelte';
    import InputCurrency from '@/components/ui/InputCurrency.svelte';
    import RichEditor from '@/components/ui/RichEditor.svelte';
    import SelectSearch from '@/components/ui/SelectSearch.svelte';
    import SelectSearchMultiple from '@/components/ui/SelectSearchMultiple.svelte';
    import Toggle from '@/components/ui/Toggle.svelte';
    import ProductImageSearchModal from '@/components/ProductImageSearchModal.svelte';

    let { categories = [], brands = [], ai_enabled = false } = $props();

    let enable3dModels = $derived(page.props.settings?.enable_3d_models ?? true);
    let membershipEnabled = $derived(page.props.settings?.membership_enabled ?? true);

    // AI Description state
    let isGeneratingAi = $state(false);
    let aiKeywords = $state('');
    let showAiKeywords = $state(false);

    // Image Search state & handler
    let showImageSearchModal = $state(false);
    function handleWebImageSelect(images) {
        if (Array.isArray(images)) {
            uploadedPhotos = [...uploadedPhotos, ...images];
            showToast(`${images.length} gambar berhasil diunduh.`, 'success');
        } else {
            uploadedPhotos = [...uploadedPhotos, images];
            showToast('Gambar otomatis berhasil diunduh.', 'success');
        }
    }

    async function generateAiDescription() {
        if (isGeneratingAi) return;
        if (!form.name) {
            showToast('Isi nama produk terlebih dahulu.', 'warning');
            return;
        }
        isGeneratingAi = true;
        try {
            const selectedCategoryNames = categories
                .filter((c) => form.category_ids.includes(c.id))
                .map((c) => c.name);
            const selectedBrandNames = brands
                .filter((b) => form.brand_ids.includes(b.id))
                .map((b) => b.name);

            const res = await fetch('/admin/ai/generate-description', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
                },
                body: JSON.stringify({
                    name: form.name,
                    categories: selectedCategoryNames,
                    brands: selectedBrandNames,
                    price: form.price ? String(form.price) : null,
                    keywords: aiKeywords || null,
                }),
            });
            const data = await res.json();
            if (!res.ok) {
                showToast(data.message || 'Gagal menghasilkan deskripsi AI.', 'error');
                return;
            }
            form.description = data.description;
            
            // Imperatively update RichEditor to bypass any reactivity issues
            const editorEl = document.getElementById('description');
            if (editorEl) {
                editorEl.dispatchEvent(new CustomEvent('update-html', { detail: data.description }));
            }

            showToast('Deskripsi berhasil dibuat oleh AI! Silakan review dan edit sesuai kebutuhan.', 'success');
        } catch (err) {
            showToast('Terjadi kesalahan saat menghubungi AI.', 'error');
        } finally {
            isGeneratingAi = false;
        }
    }

    const form = useForm({
        name: '',
        sku: '',
        category_ids: [],
        brand_ids: [],
        specifications: {},
        size_chart: null,

        // Master Price
        price: '',
        cost: '',

        // Master Stock
        stock: '',
        min_stock: '',
        min_purchase: 1,
        is_unlimited: false,
        stock_status: 'Tersedia (In Stock)',

        summary: '',
        description: '',
        weight: '',
        length: '',
        width: '',
        height: '',
        tax_enabled: false,
        tax_rate: '',
        active: true,
        is_digital: false,
        is_exclusive: false,
        exclusive_min_level_order: 0,
        is_early_access: false,
        early_access_until: '',
        early_access_min_level_order: 0,
        photos: [],
        variations: [],
        variants: [],
        tier_prices: [],
        video_url: '',
        video_file: null,
        model_3d_url: '',
        model_3d_file: null,
        model_3d_usdz_url: '',
        model_3d_usdz_file: null,
    });

    let uploadedPhotos = $state([]);

    // Quick Add Category/Brand Modals
    let showQuickAddCategoryModal = $state(false);
    let quickCategoryName = $state('');
    let quickCategorySlug = $state('');
    let quickCategoryParentId = $state('');
    let isSubmittingCategory = $state(false);

    let showQuickAddBrandModal = $state(false);
    let quickBrandName = $state('');
    let isSubmittingBrand = $state(false);

    $effect(() => {
        if (showQuickAddCategoryModal) {
            quickCategorySlug = quickCategoryName
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
        }
    });

    function closeQuickAddCategory() {
        showQuickAddCategoryModal = false;
        quickCategoryName = '';
        quickCategorySlug = '';
        quickCategoryParentId = '';
    }

    function closeQuickAddBrand() {
        showQuickAddBrandModal = false;
        quickBrandName = '';
    }

    function handleQuickAddCategory(e) {
        e.preventDefault();
        if (isSubmittingCategory) return;
        isSubmittingCategory = true;

        router.post(
            '/admin/categories',
            {
                name: quickCategoryName,
                slug: quickCategorySlug,
                media_type: 'icon',
                icon: 'ti-tag',
                parent_id: quickCategoryParentId || null,
            },
            {
                preserveState: true,
                preserveScroll: true,
                onSuccess: (pageRes) => {
                    isSubmittingCategory = false;
                    const updatedCategories = pageRes.props.categories || [];
                    const newCat = updatedCategories.find(
                        (c) =>
                            c.name.toLowerCase() ===
                                quickCategoryName.toLowerCase() ||
                            c.slug === quickCategorySlug,
                    );
                    if (newCat) {
                        if (!form.category_ids.includes(newCat.id)) {
                            form.category_ids = [
                                ...form.category_ids,
                                newCat.id,
                            ];
                        }
                    }
                    closeQuickAddCategory();
                },
                onError: (errs) => {
                    isSubmittingCategory = false;
                    const firstError = Object.values(errs)[0];
                    if (firstError) {
                        alert(firstError);
                    }
                },
            },
        );
    }

    function handleQuickAddBrand(e) {
        e.preventDefault();
        if (isSubmittingBrand) return;
        isSubmittingBrand = true;

        router.post(
            '/admin/master-data/brands',
            {
                name: quickBrandName,
                is_active: true,
            },
            {
                preserveState: true,
                preserveScroll: true,
                onSuccess: (pageRes) => {
                    isSubmittingBrand = false;
                    const updatedBrands = pageRes.props.brands || [];
                    const newBrand = updatedBrands.find(
                        (b) =>
                            b.name.toLowerCase() ===
                            quickBrandName.toLowerCase(),
                    );
                    if (newBrand) {
                        if (!form.brand_ids.includes(newBrand.id)) {
                            form.brand_ids = [...form.brand_ids, newBrand.id];
                        }
                    }
                    closeQuickAddBrand();
                },
                onError: (errs) => {
                    isSubmittingBrand = false;
                    const firstError = Object.values(errs)[0];
                    if (firstError) {
                        alert(firstError);
                    }
                },
            },
        );
    }

    let enableVariants = $state(false);
    let useVariantImages = $state(false);

    // Interactive Media Previews & Handlers
    let videoPreview = $state('');
    let model3dFileName = $state('');
    let model3dUsdzFileName = $state('');

    // AI Image-to-3D Generator State
    let isImageTo3dModalOpen = $state(false);
    let generatorStep = $state(1); // 1: Select/Upload, 2: Loading, 3: Editor
    let activeTab = $state('image_to_3d'); // 'image_to_3d', 'glb_logo', 'scratch', 'ai_3d'
    let selectedGenImage = $state(''); // base64 or URL (Front)
    let selectedGenImageBack = $state(''); // base64 or URL (Back)
    let customGenFile = $state(null);
    let customGenFileBack = $state(null);
    let generationProgress = $state(0);
    let progressMessage = $state('Menginisialisasi...');

    // Editor settings & Volumetric Extrusion
    let depth = $state(0.3); // compatibility
    let baseThickness = $state(0.15); // silhouette/3d card thickness
    let reliefDepth = $state(0.02); // detail height
    let flatMesh = $state(true); // flat faces or relief
    let backColor = $state('#ffffff'); // base backing color
    let sideColor = $state('#334155'); // side wall color
    let bevelEnabled = $state(false);
    let smoothEdge = $state(true);
    let modelType = $state('plane'); // 'plane', 'shirt', 'custom', or preset IDs

    // Logo decal positioning & transformation
    let logoScale = $state(0.3);
    let logoX = $state(0.0);
    let logoY = $state(0.04);
    let logoZ = $state(0.15);
    let logoZBack = $state(-0.15);
    let logoRotation = $state(0);
    let logoOpacity = $state(1.0);

    // Mockup 3D model positioning & transformation
    let mockupScale = $state(10);
    let mockupX = $state(0.0);
    let mockupY = $state(-1.5);
    let mockupZ = $state(0.0);
    let mockupRotationX = $state(0);
    let mockupRotationY = $state(0);
    let mockupRotationZ = $state(0);

    let customMockupFile = $state(null);
    let customMockupUrl = $state('');

    let cachedShirtGltf = null;
    let cachedModels = {};
    let metalness = $state(0.1);
    let roughness = $state(0.6);
    let resolution = $state(60);
    let tintColor = $state('#ffffff');
    let autoRotate = $state(true);

    // Background Removal & Invert Height settings
    let removeBg = $state(true);
    let bgTolerance = $state(0.15);
    let invertHeight = $state(false);
    let bgColorR = $state(255);
    let bgColorG = $state(255);
    let bgColorB = $state(255);
    let isFirstLoad = $state(true);

    // Canva-like Layer System
    let designLayers = $state([]);
    let selectedLayerId = $state(null);
    let selectedMaterial = $state('cotton');
    let selectedColor = $state('#ffffff');
    let projectionMode = $state('decal');
    let activeView = $state('front');
    let activeSide = $state('front');

    // 2D interactive canvas state
    let canvas2dEl = $state(null); // the <canvas> element for 2D preview
    let canvas2dZoom = $state(1.0); // zoom level for 2D canvas
    let canvas2dPanX = $state(0); // pan offset X
    let canvas2dPanY = $state(0); // pan offset Y
    let isDraggingLayer = $state(false); // true when dragging a layer
    let dragOffsetX = $state(0);
    let dragOffsetY = $state(0);
    let isResizingLayer = $state(false);
    let isPanning2d = $state(false); // right-click/space+drag pan
    let pan2dStartX = 0,
        pan2dStartY = 0,
        pan2dStartOffsetX = 0,
        pan2dStartOffsetY = 0;
    const CANVAS_VIRTUAL_SIZE = 512; // virtual pixel size of the design canvas

    // Text editing properties
    let textInput = $state('');
    let fontFamily = $state('Arial');
    let textColor = $state('#ffffff');
    let fontSize = $state(32);
    let isBold = $state(false);
    let isItalic = $state(false);
    let textStroke = $state(false);
    let textStrokeColor = $state('#000000');

    // QR Code properties
    let qrInput = $state('https://');

    // Presets Definition
    const presets = [
        {
            id: 'shirt',
            name: 'Kaos Polos',
            path: '/assets/shirt_baked.glb',
            meshName: 'T_Shirt_male',
            scale: 10,
            y: -1.5,
            z: 0,
        },
        {
            id: 'hoodie',
            name: 'Hoodie Realistis',
            path: '/assets/hoodie.glb',
            meshName: '',
            scale: 1.0,
            y: -1.0,
            z: 0,
        },
        {
            id: 'mug',
            name: 'Mug / Gelas',
            path: '/assets/mug.glb',
            meshName: '',
            scale: 2.0,
            y: -0.5,
            z: 0,
        },
        {
            id: 'cap',
            name: 'Topi',
            path: '/assets/cap.glb',
            meshName: '',
            scale: 1.0,
            y: -0.2,
            z: 0,
        },
        {
            id: 'totebag',
            name: 'Totebag',
            path: '/assets/totebag.glb',
            meshName: '',
            scale: 1.0,
            y: -1.0,
            z: 0,
        },
        {
            id: 'tumbler',
            name: 'Tumbler',
            path: '/assets/tumbler.glb',
            meshName: '',
            scale: 1.5,
            y: -0.8,
            z: 0,
        },
        {
            id: 'botol',
            name: 'Botol Sport',
            path: '/assets/botol.glb',
            meshName: '',
            scale: 1.5,
            y: -0.8,
            z: 0,
        },
        {
            id: 'piring',
            name: 'Piring Keramik',
            path: '/assets/piring.glb',
            meshName: '',
            scale: 1.2,
            y: 0.0,
            z: 0,
        },
        {
            id: 'plakat',
            name: 'Plakat Akrilik',
            path: '/assets/plakat.glb',
            meshName: '',
            scale: 1.3,
            y: -0.5,
            z: 0,
        },
    ];

    let canvasContainer = $state(null);
    let threeScene,
        threeCamera,
        threeRenderer,
        threeMesh,
        threeTexture,
        threeGeometry,
        threeMaterial,
        resizeListener;
    let frontCanvas, backCanvas;
    let animationFrameId;

    // Reactively redraw dynamic canvases and update Three.js models
    $effect(() => {
        if (isImageTo3dModalOpen && generatorStep === 3) {
            redrawCompositeCanvas();
            updateThreeMesh();
        }
    });

    function handleVideoFile(e) {
        const file = e.target.files[0];
        if (file) {
            form.video_file = file;
            videoPreview = URL.createObjectURL(file);
        }
    }

    function removeVideo() {
        form.video_file = null;
        videoPreview = '';
    }

    function handleModel3dFile(e) {
        const file = e.target.files[0];
        if (file) {
            form.model_3d_file = file;
            model3dFileName = file.name;
        }
    }

    // Layer System operations
    function addLayer(type, content = '') {
        const id = Date.now();
        let newLayer = null;
        if (type === 'image') {
            newLayer = {
                id,
                type: 'image',
                name: content ? 'Logo' : 'Gambar',
                src: content,
                x: 256,
                y: 256,
                scale: 0.5,
                rotation: 0,
                opacity: 1.0,
                lock: false,
                hide: false,
            };
        } else if (type === 'text') {
            newLayer = {
                id,
                type: 'text',
                text: content || textInput || 'Teks Baru',
                x: 256,
                y: 256,
                scale: 1.0,
                rotation: 0,
                opacity: 1.0,
                lock: false,
                hide: false,
                fontFamily: fontFamily,
                color: textColor,
                fontSize: fontSize,
                bold: isBold,
                italic: isItalic,
                stroke: textStroke,
                strokeColor: textStrokeColor,
            };
            textInput = '';
        } else if (type === 'qr') {
            newLayer = {
                id,
                type: 'qr',
                text: content || qrInput || 'https://example.com',
                x: 256,
                y: 256,
                scale: 0.3,
                rotation: 0,
                opacity: 1.0,
                lock: false,
                hide: false,
            };
        }

        if (newLayer) {
            designLayers = [...designLayers, newLayer];
            selectedLayerId = id;
            redrawCompositeCanvas();
        }
    }

    function deleteLayer(id) {
        designLayers = designLayers.filter((l) => l.id !== id);
        if (selectedLayerId === id) selectedLayerId = null;
        redrawCompositeCanvas();
    }

    function duplicateLayer(layer) {
        const id = Date.now();
        const duplicated = {
            ...layer,
            id,
            x: Math.min(480, layer.x + 20),
            y: Math.min(480, layer.y + 20),
        };
        designLayers = [...designLayers, duplicated];
        selectedLayerId = id;
        redrawCompositeCanvas();
    }

    function moveLayerOrder(index, direction) {
        const nextIndex = index + direction;
        if (nextIndex < 0 || nextIndex >= designLayers.length) return;
        const copy = [...designLayers];
        const temp = copy[index];
        copy[index] = copy[nextIndex];
        copy[nextIndex] = temp;
        designLayers = copy;
        redrawCompositeCanvas();
    }

    /** Returns the bounding box of a layer in virtual canvas coordinates */
    function getLayerBounds(layer) {
        let w = 0,
            h = 0;
        if (layer.type === 'image') {
            const size = CANVAS_VIRTUAL_SIZE * layer.scale;
            w = size;
            h = size;
        } else if (layer.type === 'text') {
            // approximate: font size * scale * char count
            const approxW = Math.max(
                60,
                (layer.text?.length || 8) * layer.fontSize * layer.scale * 0.6,
            );
            const approxH = layer.fontSize * layer.scale * 1.2;
            w = approxW;
            h = approxH;
        } else if (layer.type === 'qr') {
            const size = 120 * layer.scale;
            w = size;
            h = size;
        }
        return { x: layer.x - w / 2, y: layer.y - h / 2, w, h };
    }

    /** Convert mouse event position inside canvas2dEl to virtual canvas coordinates.
     *  Uses canvas pixel space (canvas2dEl.width/height) not CSS space. */
    function mouseToVirtualCoords(e) {
        if (!canvas2dEl) return { vx: 0, vy: 0 };
        const rect = canvas2dEl.getBoundingClientRect();
        // Scale factor: canvas pixel size / CSS displayed size
        const scaleX = canvas2dEl.width / rect.width;
        const scaleY = canvas2dEl.height / rect.height;
        const mouseX = (e.clientX - rect.left) * scaleX;
        const mouseY = (e.clientY - rect.top) * scaleY;
        // canvas is centered, zoom applied, then pan applied
        const elW = canvas2dEl.width;
        const elH = canvas2dEl.height;
        const originX = elW / 2 + canvas2dPanX;
        const originY = elH / 2 + canvas2dPanY;
        const vx = (mouseX - originX) / canvas2dZoom + CANVAS_VIRTUAL_SIZE / 2;
        const vy = (mouseY - originY) / canvas2dZoom + CANVAS_VIRTUAL_SIZE / 2;
        return { vx, vy };
    }

    /** Hit test: returns matching layer id or null. Also returns if it's a resize handle. */
    function hitTestCanvas2d(vx, vy) {
        // Reverse iterate so top-most layer is hit first
        for (let i = designLayers.length - 1; i >= 0; i--) {
            const layer = designLayers[i];
            if (layer.hide) continue;
            const b = getLayerBounds(layer);
            // Check resize handle (bottom-right corner)
            const handleSize = 14 / canvas2dZoom;
            if (
                vx >= b.x + b.w - handleSize &&
                vx <= b.x + b.w + handleSize &&
                vy >= b.y + b.h - handleSize &&
                vy <= b.y + b.h + handleSize
            ) {
                return { id: layer.id, mode: 'resize' };
            }
            // Check body
            if (vx >= b.x && vx <= b.x + b.w && vy >= b.y && vy <= b.y + b.h) {
                return { id: layer.id, mode: 'move' };
            }
        }
        return null;
    }

    function onCanvas2dMouseDown(e) {
        if (e.button === 2) {
            // Right click = pan
            isPanning2d = true;
            pan2dStartX = e.clientX;
            pan2dStartY = e.clientY;
            pan2dStartOffsetX = canvas2dPanX;
            pan2dStartOffsetY = canvas2dPanY;
            e.preventDefault();
            return;
        }
        const { vx, vy } = mouseToVirtualCoords(e);
        const hit = hitTestCanvas2d(vx, vy);
        if (hit) {
            selectedLayerId = hit.id;
            const layer = designLayers.find((l) => l.id === hit.id);
            if (hit.mode === 'resize') {
                isResizingLayer = true;
                isDraggingLayer = false;
                dragOffsetX = vx - layer.x;
                dragOffsetY = vy - layer.y;
            } else {
                isDraggingLayer = true;
                isResizingLayer = false;
                dragOffsetX = vx - layer.x;
                dragOffsetY = vy - layer.y;
            }
        } else {
            selectedLayerId = null;
        }
    }

    function onCanvas2dMouseMove(e) {
        if (isPanning2d) {
            canvas2dPanX = pan2dStartOffsetX + (e.clientX - pan2dStartX);
            canvas2dPanY = pan2dStartOffsetY + (e.clientY - pan2dStartY);
            return;
        }
        if (!selectedLayerId) return;
        const { vx, vy } = mouseToVirtualCoords(e);
        if (isDraggingLayer) {
            const idx = designLayers.findIndex((l) => l.id === selectedLayerId);
            if (idx !== -1) {
                designLayers[idx].x = Math.round(vx - dragOffsetX);
                designLayers[idx].y = Math.round(vy - dragOffsetY);
                designLayers = [...designLayers]; // trigger reactivity
                redrawCompositeCanvas();
            }
        } else if (isResizingLayer) {
            const idx = designLayers.findIndex((l) => l.id === selectedLayerId);
            if (idx !== -1) {
                const layer = designLayers[idx];
                const dx = vx - layer.x;
                const dy = vy - layer.y;
                const dist = Math.max(Math.abs(dx), Math.abs(dy));
                if (layer.type === 'image') {
                    layer.scale = Math.max(
                        0.05,
                        (dist / CANVAS_VIRTUAL_SIZE) * 2,
                    );
                } else if (layer.type === 'text') {
                    layer.scale = Math.max(0.1, dist / 60);
                } else if (layer.type === 'qr') {
                    layer.scale = Math.max(0.05, dist / 60);
                }
                designLayers = [...designLayers];
                redrawCompositeCanvas();
            }
        }
    }

    function onCanvas2dMouseUp(e) {
        isDraggingLayer = false;
        isResizingLayer = false;
        isPanning2d = false;
    }

    function onCanvas2dWheel(e) {
        e.preventDefault();
        const zoomDelta = e.deltaY > 0 ? 0.9 : 1.1;
        canvas2dZoom = Math.max(0.2, Math.min(5.0, canvas2dZoom * zoomDelta));
    }

    function onCanvas2dContextMenu(e) {
        e.preventDefault();
    }

    /** Draws the 2D interactive canvas including selection handles */
    function drawCanvas2dPreview() {
        if (!canvas2dEl) return;
        const ctx = canvas2dEl.getContext('2d');
        const elW = canvas2dEl.width;
        const elH = canvas2dEl.height;
        ctx.clearRect(0, 0, elW, elH);

        // Checkerboard transparent bg
        const tileSize = 16;
        for (let ty = 0; ty < elH; ty += tileSize) {
            for (let tx = 0; tx < elW; tx += tileSize) {
                ctx.fillStyle =
                    (Math.floor(tx / tileSize) + Math.floor(ty / tileSize)) %
                        2 ===
                    0
                        ? '#e2e8f0'
                        : '#f8fafc';
                ctx.fillRect(tx, ty, tileSize, tileSize);
            }
        }

        // Apply zoom and pan transform centered in canvas
        ctx.save();
        const originX = elW / 2 + canvas2dPanX;
        const originY = elH / 2 + canvas2dPanY;
        ctx.translate(originX, originY);
        ctx.scale(canvas2dZoom, canvas2dZoom);
        ctx.translate(-CANVAS_VIRTUAL_SIZE / 2, -CANVAS_VIRTUAL_SIZE / 2);

        // White canvas background
        ctx.fillStyle = '#ffffff';
        ctx.shadowColor = 'rgba(0,0,0,0.18)';
        ctx.shadowBlur = 20;
        ctx.fillRect(0, 0, CANVAS_VIRTUAL_SIZE, CANVAS_VIRTUAL_SIZE);
        ctx.shadowBlur = 0;

        // If there's a base image, draw it
        if (selectedGenImage) {
            const cachedImg = _canvas2dImgCache[selectedGenImage];
            if (cachedImg) {
                ctx.drawImage(
                    cachedImg,
                    0,
                    0,
                    CANVAS_VIRTUAL_SIZE,
                    CANVAS_VIRTUAL_SIZE,
                );
            } else {
                const img = new Image();
                img.crossOrigin = 'anonymous';
                img.src = selectedGenImage;
                img.onload = () => {
                    _canvas2dImgCache[selectedGenImage] = img;
                    drawCanvas2dPreview();
                };
            }
        }

        // Draw all layers
        designLayers.forEach((layer) => {
            if (layer.hide) return;
            ctx.save();
            ctx.globalAlpha = layer.opacity !== undefined ? layer.opacity : 1.0;
            ctx.translate(layer.x, layer.y);
            ctx.rotate((layer.rotation * Math.PI) / 180);

            if (layer.type === 'image' && layer.src) {
                const size = CANVAS_VIRTUAL_SIZE * layer.scale;
                const cachedImg = _canvas2dImgCache[layer.src];
                if (cachedImg) {
                    ctx.drawImage(cachedImg, -size / 2, -size / 2, size, size);
                } else {
                    const img = new Image();
                    img.crossOrigin = 'anonymous';
                    img.src = layer.src;
                    img.onload = () => {
                        _canvas2dImgCache[layer.src] = img;
                        drawCanvas2dPreview();
                    };
                    // Placeholder while loading
                    ctx.strokeStyle = '#94a3b8';
                    ctx.setLineDash([6, 3]);
                    ctx.strokeRect(-size / 2, -size / 2, size, size);
                    ctx.setLineDash([]);
                }
            } else if (layer.type === 'text') {
                const fontStyle = `${layer.italic ? 'italic ' : ''}${layer.bold ? 'bold ' : ''}${layer.fontSize * layer.scale}px ${layer.fontFamily}`;
                ctx.font = fontStyle;
                ctx.fillStyle = layer.color;
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                if (layer.text) {
                    ctx.fillText(layer.text, 0, 0);
                    if (layer.stroke) {
                        ctx.strokeStyle = layer.strokeColor || '#000000';
                        ctx.lineWidth = 2 / canvas2dZoom;
                        ctx.strokeText(layer.text, 0, 0);
                    }
                }
            } else if (layer.type === 'qr') {
                const size = 120 * layer.scale;
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(-size / 2, -size / 2, size, size);
                ctx.strokeStyle = '#000000';
                ctx.lineWidth = 2;
                ctx.strokeRect(-size / 2, -size / 2, size, size);
                ctx.fillStyle = '#000000';
                ctx.font = `bold ${size * 0.12}px monospace`;
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText('QR CODE', 0, 0);
            }
            ctx.restore();

            // Draw selection highlight + handles
            if (layer.id === selectedLayerId) {
                ctx.save();
                ctx.translate(layer.x, layer.y);
                ctx.rotate((layer.rotation * Math.PI) / 180);
                const b = getLayerBounds(layer);
                const bx = -b.w / 2,
                    by = -b.h / 2;
                ctx.strokeStyle = '#3b82f6';
                ctx.lineWidth = 2 / canvas2dZoom;
                ctx.setLineDash([5 / canvas2dZoom, 3 / canvas2dZoom]);
                ctx.strokeRect(bx, by, b.w, b.h);
                ctx.setLineDash([]);
                // Resize handle (bottom-right)
                const hs = 8 / canvas2dZoom;
                ctx.fillStyle = '#ffffff';
                ctx.strokeStyle = '#3b82f6';
                ctx.lineWidth = 2 / canvas2dZoom;
                ctx.fillRect(bx + b.w - hs, by + b.h - hs, hs * 2, hs * 2);
                ctx.strokeRect(bx + b.w - hs, by + b.h - hs, hs * 2, hs * 2);
                // Corner dots
                [
                    [bx, by],
                    [bx + b.w, by],
                    [bx, by + b.h],
                ].forEach(([cx, cy]) => {
                    ctx.beginPath();
                    ctx.arc(cx, cy, hs * 0.7, 0, Math.PI * 2);
                    ctx.fillStyle = '#3b82f6';
                    ctx.fill();
                });
                ctx.restore();
            }
        });
        ctx.restore();
    }

    let _canvas2dImgCache = {};

    /** Update canvas 2d on any change using effect */
    $effect(() => {
        // Reactive dependencies: layers, selectedLayerId, zoom, pan, selectedGenImage
        const _ = [
            designLayers,
            selectedLayerId,
            canvas2dZoom,
            canvas2dPanX,
            canvas2dPanY,
            selectedGenImage,
        ];
        if (canvas2dEl) {
            drawCanvas2dPreview();
        }
    });

    function handleLayerImageUpload(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (event) => {
                addLayer('image', event.target.result);
            };
            reader.readAsDataURL(file);
        }
    }

    function removeModel3d() {
        form.model_3d_file = null;
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
        model3dUsdzFileName = '';
    }

    function openImageTo3dModal() {
        isImageTo3dModalOpen = true;
        generatorStep = 1;
        activeTab = 'image_to_3d';
        selectedGenImage = uploadedPhotos.length > 0 ? uploadedPhotos[0] : '';
        selectedGenImageBack = '';
        customGenFile = null;
        customGenFileBack = null;
        backColor = '#ffffff';
        sideColor = '#334155';
        modelType = 'plane';
        logoScale = 0.3;
        logoX = 0.0;
        logoY = 0.04;
        logoZ = 0.15;
        logoZBack = -0.15;
        logoRotation = 0;
        logoOpacity = 1.0;
        mockupScale = 10;
        mockupX = 0;
        mockupY = -1.5;
        mockupZ = 0;
        mockupRotationX = 0;
        mockupRotationY = 0;
        mockupRotationZ = 0;
        customMockupFile = null;
        customMockupUrl = '';
        selectedMaterial = 'cotton';
        selectedColor = '#ffffff';
        designLayers = [];
        selectedLayerId = null;
        projectionMode = 'decal';
        activeView = 'front';
        activeSide = 'front';
        frontCanvas = null;
        backCanvas = null;
        isFirstLoad = true;
        bevelEnabled = false;
        smoothEdge = true;
    }

    function closeImageTo3dModal() {
        cleanupThreeJS();
        isImageTo3dModalOpen = false;
    }

    function selectGenImage(img) {
        selectedGenImage = img;
        customGenFile = null;
        isFirstLoad = true;
    }

    function handleGenImageUpload(e) {
        const file = e.target.files[0];
        if (file) {
            customGenFile = file;
            const reader = new FileReader();
            reader.onload = (event) => {
                selectedGenImage = event.target.result;
                isFirstLoad = true;
            };
            reader.readAsDataURL(file);
        }
    }

    function handleGenImageUploadBack(e) {
        const file = e.target.files[0];
        if (file) {
            customGenFileBack = file;
            const reader = new FileReader();
            reader.onload = (event) => {
                selectedGenImageBack = event.target.result;
            };
            reader.readAsDataURL(file);
        }
    }

    function handleCustomMockupUpload(e) {
        const file = e.target.files[0];
        if (file) {
            customMockupFile = file;
            customMockupUrl = URL.createObjectURL(file);
            modelType = 'custom';
            mockupScale = 1.0;
            mockupY = 0.0;
            mockupX = 0.0;
            mockupZ = 0.0;
            updateThreeMesh();
        }
    }

    async function loadThreeJS() {
        // Reinforce checking and mapping if somehow fflate was loaded elsewhere/in module scope
        if (!window.fflate && typeof fflate !== 'undefined') {
            window.fflate = fflate;
        }
        if (
            window.THREE &&
            window.THREE.GLTFLoader &&
            window.THREE.DecalGeometry &&
            window.THREE.USDZExporter &&
            window.fflate
        )
            return;

        return new Promise((resolve, reject) => {
            const script1 = document.createElement('script');
            script1.src =
                'https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js';
            script1.onload = () => {
                const script2 = document.createElement('script');
                script2.src =
                    'https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/exporters/GLTFExporter.js';
                script2.onload = () => {
                    const script3 = document.createElement('script');
                    script3.src =
                        'https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/GLTFLoader.js';
                    script3.onload = () => {
                        const script4 = document.createElement('script');
                        script4.src =
                            'https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/geometries/DecalGeometry.js';
                        script4.onload = () => {
                            // Load fflate first (required by USDZExporter for compression)
                            const fflateScript =
                                document.createElement('script');
                            fflateScript.src =
                                'https://cdn.jsdelivr.net/npm/fflate@0.8.2/umd/index.min.js';

                            // Temporarily hide AMD/CommonJS globals to prevent UMD hijacking by global context loaders
                            const oldDefine = window.define;
                            const oldExports = window.exports;
                            const oldModule = window.module;

                            try {
                                window.define = undefined;
                                window.exports = undefined;
                                window.module = undefined;
                            } catch (e) {
                                console.warn(
                                    'Could not temporarily disable global loaders:',
                                    e,
                                );
                            }

                            fflateScript.onload = () => {
                                // Restore AMD/CommonJS globals
                                try {
                                    window.define = oldDefine;
                                    window.exports = oldExports;
                                    window.module = oldModule;
                                } catch (e) {
                                    console.warn(
                                        'Could not restore global loaders:',
                                        e,
                                    );
                                }

                                // Explicitly map fflate if loaded inside legacy formats
                                if (!window.fflate) {
                                    if (typeof fflate !== 'undefined') {
                                        window.fflate = fflate;
                                    } else if (
                                        oldExports &&
                                        oldExports.fflate
                                    ) {
                                        window.fflate = oldExports.fflate;
                                    } else if (
                                        oldModule &&
                                        oldModule.exports &&
                                        oldModule.exports.fflate
                                    ) {
                                        window.fflate =
                                            oldModule.exports.fflate;
                                    }
                                }

                                const script5 =
                                    document.createElement('script');
                                script5.src =
                                    'https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/exporters/USDZExporter.js';
                                script5.onload = resolve;
                                script5.onerror = reject;
                                document.head.appendChild(script5);
                            };
                            fflateScript.onerror = (err) => {
                                // Restore AMD/CommonJS globals
                                try {
                                    window.define = oldDefine;
                                    window.exports = oldExports;
                                    window.module = oldModule;
                                } catch (e) {
                                    console.warn(
                                        'Could not restore global loaders:',
                                        e,
                                    );
                                }
                                reject(err);
                            };
                            document.head.appendChild(fflateScript);
                        };
                        script4.onerror = reject;
                        document.head.appendChild(script4);
                    };
                    script3.onerror = reject;
                    document.head.appendChild(script3);
                };
                script2.onerror = reject;
                document.head.appendChild(script2);
            };
            script1.onerror = reject;
            document.head.appendChild(script1);
        });
    }

    function startGeneration() {
        if (activeTab !== 'scratch' && !selectedGenImage) return;
        generatorStep = 2;
        generationProgress = 0;
        progressMessage = 'Menganalisis struktur gambar...';

        const interval = setInterval(() => {
            generationProgress += 2;

            if (activeTab === 'ai_3d') {
                if (generationProgress < 25) {
                    progressMessage = 'Mengirim gambar ke Tripo/Meshy AI...';
                } else if (generationProgress < 60) {
                    progressMessage =
                        'Membuat model 3D awan titik (Point Cloud)...';
                } else if (generationProgress < 85) {
                    progressMessage = 'Merekonstruksi mesh PBR volumetric...';
                } else {
                    progressMessage = 'Mengunduh file GLB model 3D...';
                }
            } else if (activeTab === 'scratch') {
                if (generationProgress < 40) {
                    progressMessage = 'Memuat preset model 3D...';
                } else if (generationProgress < 80) {
                    progressMessage = 'Menginisialisasi material & kanvas...';
                } else {
                    progressMessage = 'Menyiapkan viewport 3D...';
                }
            } else {
                if (generationProgress < 25) {
                    progressMessage = 'Menganalisis struktur gambar...';
                } else if (generationProgress < 60) {
                    progressMessage = 'Membuat model mesh 3D (Z-relief)...';
                } else if (generationProgress < 85) {
                    progressMessage = 'Membuat texture maps & material PBR...';
                } else {
                    progressMessage = 'Mengoptimalkan mesh GLB (Decimation)...';
                }
            }

            if (generationProgress >= 100) {
                clearInterval(interval);
                setTimeout(async () => {
                    generatorStep = 3;
                    await loadThreeJS();
                    setTimeout(() => {
                        initThreeJS();
                        redrawCompositeCanvas();
                    }, 50);
                }, 400);
            }
        }, 30);
    }

    function initThreeJS() {
        if (!canvasContainer) return;
        cleanupThreeJS();

        const width = canvasContainer.clientWidth;
        const height = canvasContainer.clientHeight || 550;

        threeScene = new THREE.Scene();
        threeScene.background = new THREE.Color('#f8fafc');

        threeCamera = new THREE.PerspectiveCamera(45, width / height, 0.1, 100);
        threeCamera.position.set(0, 0, 5);

        threeRenderer = new THREE.WebGLRenderer({
            antialias: true,
            alpha: true,
        });
        threeRenderer.setSize(width, height);
        threeRenderer.setPixelRatio(window.devicePixelRatio);

        canvasContainer.innerHTML = '';
        canvasContainer.appendChild(threeRenderer.domElement);

        // Studio Lighting Setup
        const ambientLight = new THREE.AmbientLight('#ffffff', 0.5);
        threeScene.add(ambientLight);

        const dirLight1 = new THREE.DirectionalLight('#ffffff', 0.8);
        dirLight1.position.set(5, 5, 5);
        threeScene.add(dirLight1);

        const dirLight2 = new THREE.DirectionalLight('#ffffff', 0.4);
        dirLight2.position.set(-5, 5, -5); // Rim/back lighting
        threeScene.add(dirLight2);

        // Dynamic PointLight for glossy cursor highlight reflections
        const studioPointLight = new THREE.PointLight('#ffffff', 0.6, 15);
        studioPointLight.position.set(0, 0, 4);
        threeScene.add(studioPointLight);

        updateThreeMesh();

        let isDragging = false;
        let previousMousePosition = { x: 0, y: 0 };

        threeRenderer.domElement.addEventListener('mousedown', () => {
            isDragging = true;
        });
        threeRenderer.domElement.addEventListener('mousemove', (e) => {
            const rect = threeRenderer.domElement.getBoundingClientRect();
            const mouseX = ((e.clientX - rect.left) / rect.width) * 2 - 1;
            const mouseY = -((e.clientY - rect.top) / rect.height) * 2 + 1;

            // Posisi PointLight interaktif mengikuti kursor untuk highlight mengkilap
            studioPointLight.position.x = mouseX * 4;
            studioPointLight.position.y = mouseY * 4;
            studioPointLight.position.z = 3;

            const deltaMove = {
                x: e.offsetX - previousMousePosition.x,
                y: e.offsetY - previousMousePosition.y,
            };
            if (isDragging && threeMesh) {
                threeMesh.rotation.y += deltaMove.x * 0.01;
                threeMesh.rotation.x += deltaMove.y * 0.01;
            }
            previousMousePosition = {
                x: e.offsetX,
                y: e.offsetY,
            };
        });
        window.addEventListener('mouseup', () => {
            isDragging = false;
        });

        threeRenderer.domElement.addEventListener('wheel', (e) => {
            const currentDistance = threeCamera.position.length();
            let newDistance = currentDistance + e.deltaY * 0.01;
            newDistance = Math.max(0.5, Math.min(newDistance, 30));

            if (Math.abs(newDistance - currentDistance) > 0.0001) {
                e.preventDefault();
                if (currentDistance > 0) {
                    threeCamera.position.multiplyScalar(
                        newDistance / currentDistance,
                    );
                }
            }
        });

        // Dynamic resize listener to fill client height/width
        resizeListener = () => {
            if (!canvasContainer || !threeRenderer || !threeCamera) return;
            const w = canvasContainer.clientWidth;
            const h = canvasContainer.clientHeight || 550;
            threeCamera.aspect = w / h;
            threeCamera.updateProjectionMatrix();
            threeRenderer.setSize(w, h);
        };
        window.addEventListener('resize', resizeListener);
    }

    function setCameraView(view) {
        if (!threeCamera) return;
        activeView = view;
        switch (view) {
            case 'front':
                threeCamera.position.set(0, 0, 5);
                break;
            case 'back':
                threeCamera.position.set(0, 0, -5);
                break;
            case 'left':
                threeCamera.position.set(-5, 0, 0);
                break;
            case 'right':
                threeCamera.position.set(5, 0, 0);
                break;
            case 'top':
                threeCamera.position.set(0, 5, 0.01);
                break;
            case 'bottom':
                threeCamera.position.set(0, -5, 0.01);
                break;
        }
        threeCamera.lookAt(0, 0, 0);
    }

    function exportPrintReady4K() {
        const visibleLayers = designLayers.filter((l) => !l.hide);

        // Preload all images in the layers
        const loadPromises = visibleLayers.map((layer) => {
            if (layer.type === 'image' && layer.src) {
                return new Promise((resolve) => {
                    const img = new Image();
                    img.crossOrigin = 'anonymous';
                    img.src = layer.src;
                    if (img.complete) {
                        resolve({ layer, img });
                    } else {
                        img.onload = () => resolve({ layer, img });
                        img.onerror = () => resolve({ layer, img: null });
                    }
                });
            }
            return Promise.resolve({ layer, img: null });
        });

        Promise.all(loadPromises).then((loadedResults) => {
            const tempCanvas = document.createElement('canvas');
            tempCanvas.width = 3840;
            tempCanvas.height = 3840;
            const ctx = tempCanvas.getContext('2d');

            // Sublimation standard: transparent background
            ctx.clearRect(0, 0, 3840, 3840);

            ctx.save();
            // Scale dari koordinat virtual 512px ke 3840px (Rasio: 7.5x)
            ctx.scale(7.5, 7.5);

            loadedResults.forEach(({ layer, img }) => {
                ctx.save();
                ctx.globalAlpha =
                    layer.opacity !== undefined ? layer.opacity : 1.0;
                ctx.translate(layer.x, layer.y);
                ctx.rotate((layer.rotation * Math.PI) / 180);

                if (layer.type === 'image' && img) {
                    const size = 512 * layer.scale;
                    ctx.drawImage(img, -size / 2, -size / 2, size, size);
                } else if (layer.type === 'text') {
                    const fontStyle = `${layer.italic ? 'italic ' : ''}${layer.bold ? 'bold ' : ''}${layer.fontSize * layer.scale}px ${layer.fontFamily}`;
                    ctx.font = fontStyle;
                    ctx.fillStyle = layer.color;
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';

                    if (layer.text) {
                        ctx.fillText(layer.text, 0, 0);
                        if (layer.stroke) {
                            ctx.strokeStyle = layer.strokeColor || '#000000';
                            ctx.lineWidth = 2;
                            ctx.strokeText(layer.text, 0, 0);
                        }
                    }
                } else if (layer.type === 'qr') {
                    const size = 120 * layer.scale;
                    ctx.fillStyle = '#ffffff';
                    ctx.fillRect(-size / 2, -size / 2, size, size);
                    ctx.strokeStyle = '#000000';
                    ctx.lineWidth = 4;
                    ctx.strokeRect(-size / 2, -size / 2, size, size);

                    ctx.fillStyle = '#000000';
                    const finderSize = size * 0.25;

                    // Top-Left Finder
                    ctx.fillRect(
                        -size / 2 + 4,
                        -size / 2 + 4,
                        finderSize,
                        finderSize,
                    );
                    ctx.fillStyle = '#ffffff';
                    ctx.fillRect(
                        -size / 2 + 8,
                        -size / 2 + 8,
                        finderSize - 8,
                        finderSize - 8,
                    );
                    ctx.fillStyle = '#000000';
                    ctx.fillRect(
                        -size / 2 + 10,
                        -size / 2 + 10,
                        finderSize - 12,
                        finderSize - 12,
                    );

                    // Top-Right Finder
                    ctx.fillRect(
                        size / 2 - finderSize - 4,
                        -size / 2 + 4,
                        finderSize,
                        finderSize,
                    );
                    ctx.fillStyle = '#ffffff';
                    ctx.fillRect(
                        size / 2 - finderSize,
                        -size / 2 + 8,
                        finderSize - 8,
                        finderSize - 8,
                    );
                    ctx.fillStyle = '#000000';
                    ctx.fillRect(
                        size / 2 - finderSize + 2,
                        -size / 2 + 10,
                        finderSize - 12,
                        finderSize - 12,
                    );

                    // Bottom-Left Finder
                    ctx.fillRect(
                        -size / 2 + 4,
                        size / 2 - finderSize - 4,
                        finderSize,
                        finderSize,
                    );
                    ctx.fillStyle = '#ffffff';
                    ctx.fillRect(
                        -size / 2 + 8,
                        size / 2 - finderSize,
                        finderSize - 8,
                        finderSize - 8,
                    );
                    ctx.fillStyle = '#000000';
                    ctx.fillRect(
                        -size / 2 + 10,
                        size / 2 - finderSize + 2,
                        finderSize - 12,
                        finderSize - 12,
                    );

                    ctx.fillStyle = '#000000';
                    const step = size / 10;
                    for (let i = 0; i < 6; i++) {
                        for (let j = 0; j < 6; j++) {
                            if (
                                (i < 3 && j < 3) ||
                                (i > 3 && j < 3) ||
                                (i < 3 && j > 3)
                            )
                                continue;
                            if ((i + j) % 2 === 0) {
                                ctx.fillRect(
                                    -size / 2 + i * step + step,
                                    -size / 2 + j * step + step,
                                    step,
                                    step,
                                );
                            }
                        }
                    }
                }
                ctx.restore();
            });

            ctx.restore();

            // Unduh file cetak PNG transparan 4K
            const dataUrl = tempCanvas.toDataURL('image/png');
            const link = document.createElement('a');
            link.download = `print_ready_${side}_4k.png`;
            link.href = dataUrl;
            link.click();
        });
    }

    function downloadMockupSnapshot4K() {
        if (!threeRenderer || !threeScene || !threeCamera) return;

        // Simpan ukuran asli renderer
        const originalWidth = canvasContainer.clientWidth;
        const originalHeight = 400;
        const targetSize = 3840;

        // Atur renderer ke resolusi 4K dan perbarui aspek rasio kamera
        threeRenderer.setSize(targetSize, targetSize, false);
        threeCamera.aspect = 1;
        threeCamera.updateProjectionMatrix();

        // Render paksa pada resolusi tinggi
        threeRenderer.render(threeScene, threeCamera);

        // Tangkap snapshot
        const dataUrl = threeRenderer.domElement.toDataURL('image/png');

        // Trigger download
        const link = document.createElement('a');
        link.download = `mockup_snapshot_4k.png`;
        link.href = dataUrl;
        link.click();

        // Kembalikan ukuran asli renderer & aspek rasio
        threeRenderer.setSize(originalWidth, originalHeight);
        threeCamera.aspect = originalWidth / originalHeight;
        threeCamera.updateProjectionMatrix();

        // Render kembali ke viewport responsif
        threeRenderer.render(threeScene, threeCamera);
    }

    // Composites all active layers onto the high resolution 2D canvas
    function redrawCompositeCanvas() {
        if (!frontCanvas) {
            frontCanvas = document.createElement('canvas');
            frontCanvas.width = 512;
            frontCanvas.height = 512;
        }
        if (!backCanvas) {
            backCanvas = document.createElement('canvas');
            backCanvas.width = 512;
            backCanvas.height = 512;
        }

        const drawSide = (canvas, baseImgUrl) => {
            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, 512, 512);

            // Draw background color (base color of the product) only for plane silhouette
            if (modelType === 'plane') {
                ctx.fillStyle = selectedColor;
                ctx.fillRect(0, 0, 512, 512);
            }

            // If base image exists (from photo upload), draw it first
            if (baseImgUrl) {
                const img = new Image();
                img.crossOrigin = 'anonymous';
                img.src = baseImgUrl;
                img.onload = () => {
                    ctx.drawImage(img, 0, 0, 512, 512);
                    drawLayersOnCtx(ctx);
                    updateThreeTexturesFromCanvases();
                };
                if (img.complete) {
                    ctx.drawImage(img, 0, 0, 512, 512);
                    drawLayersOnCtx(ctx);
                }
            } else {
                drawLayersOnCtx(ctx);
            }
        };

        drawSide(frontCanvas, selectedGenImage);
        drawSide(backCanvas, selectedGenImageBack);

        updateThreeTexturesFromCanvases();
    }

    function drawLayersOnCtx(ctx) {
        const visibleLayers = designLayers.filter((l) => !l.hide);

        visibleLayers.forEach((layer) => {
            ctx.save();
            ctx.globalAlpha = layer.opacity !== undefined ? layer.opacity : 1.0;

            ctx.translate(layer.x, layer.y);
            ctx.rotate((layer.rotation * Math.PI) / 180);

            if (layer.type === 'image' && layer.src) {
                const img = new Image();
                img.crossOrigin = 'anonymous';
                img.src = layer.src;
                const size = 512 * layer.scale;
                if (img.complete) {
                    ctx.drawImage(img, -size / 2, -size / 2, size, size);
                } else {
                    img.onload = () => {
                        redrawCompositeCanvas(); // Redraw once loaded
                    };
                }
            } else if (layer.type === 'text') {
                const fontStyle = `${layer.italic ? 'italic ' : ''}${layer.bold ? 'bold ' : ''}${layer.fontSize * layer.scale}px ${layer.fontFamily}`;
                ctx.font = fontStyle;
                ctx.fillStyle = layer.color;
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';

                if (layer.text) {
                    ctx.fillText(layer.text, 0, 0);
                    if (layer.stroke) {
                        ctx.strokeStyle = layer.strokeColor || '#000000';
                        ctx.lineWidth = 2;
                        ctx.strokeText(layer.text, 0, 0);
                    }
                }
            } else if (layer.type === 'qr') {
                const size = 120 * layer.scale;
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(-size / 2, -size / 2, size, size);
                ctx.strokeStyle = '#000000';
                ctx.lineWidth = 4;
                ctx.strokeRect(-size / 2, -size / 2, size, size);

                ctx.fillStyle = '#000000';
                const finderSize = size * 0.25;
                // Top-Left Finder
                ctx.fillRect(
                    -size / 2 + 4,
                    -size / 2 + 4,
                    finderSize,
                    finderSize,
                );
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(
                    -size / 2 + 8,
                    -size / 2 + 8,
                    finderSize - 8,
                    finderSize - 8,
                );
                ctx.fillStyle = '#000000';
                ctx.fillRect(
                    -size / 2 + 10,
                    -size / 2 + 10,
                    finderSize - 12,
                    finderSize - 12,
                );

                // Top-Right Finder
                ctx.fillRect(
                    size / 2 - finderSize - 4,
                    -size / 2 + 4,
                    finderSize,
                    finderSize,
                );
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(
                    size / 2 - finderSize,
                    -size / 2 + 8,
                    finderSize - 8,
                    finderSize - 8,
                );
                ctx.fillStyle = '#000000';
                ctx.fillRect(
                    size / 2 - finderSize + 2,
                    -size / 2 + 10,
                    finderSize - 12,
                    finderSize - 12,
                );

                // Bottom-Left Finder
                ctx.fillRect(
                    -size / 2 + 4,
                    size / 2 - finderSize - 4,
                    finderSize,
                    finderSize,
                );
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(
                    -size / 2 + 8,
                    size / 2 - finderSize,
                    finderSize - 8,
                    finderSize - 8,
                );
                ctx.fillStyle = '#000000';
                ctx.fillRect(
                    -size / 2 + 10,
                    size / 2 - finderSize + 2,
                    finderSize - 12,
                    finderSize - 12,
                );

                ctx.fillStyle = '#000000';
                const step = size / 10;
                for (let i = 0; i < 6; i++) {
                    for (let j = 0; j < 6; j++) {
                        if (
                            (i < 3 && j < 3) ||
                            (i > 3 && j < 3) ||
                            (i < 3 && j > 3)
                        )
                            continue;
                        if ((i + j) % 2 === 0) {
                            ctx.fillRect(
                                -size / 2 + i * step + step,
                                -size / 2 + j * step + step,
                                step,
                                step,
                            );
                        }
                    }
                }
            }
            ctx.restore();
        });
    }

    function updateThreeTexturesFromCanvases() {
        if (!threeScene || !threeMesh) return;
        threeMesh.traverse((child) => {
            if (child.isMesh && child.material) {
                if (child.material.map) {
                    child.material.map.needsUpdate = true;
                }
            }
        });
        if (threeTexture) {
            threeTexture.needsUpdate = true;
        }
    }

    function onModelTypeChange() {
        const preset = presets.find((p) => p.id === modelType);
        if (preset) {
            mockupScale = preset.scale;
            mockupY = preset.y;
            mockupZ = preset.z ?? 0;
            mockupX = 0;
        } else if (modelType === 'plane') {
            mockupScale = 10;
            mockupY = -1.5;
            mockupZ = 0;
            mockupX = 0;
        } else if (modelType === 'custom') {
            mockupScale = 1.0;
            mockupY = 0.0;
            mockupZ = 0.0;
            mockupX = 0.0;
        }
        updateThreeMesh();
    }

    function updateThreeMesh() {
        if (!threeScene) return;

        if (!frontCanvas || !backCanvas) {
            redrawCompositeCanvas();
            return;
        }

        const w = 128;
        const h = 128;

        const tempCanvasFront = document.createElement('canvas');
        tempCanvasFront.width = w;
        tempCanvasFront.height = h;
        const tempCtxFront = tempCanvasFront.getContext('2d');
        tempCtxFront.drawImage(frontCanvas, 0, 0, w, h);
        const imgDataFront = tempCtxFront.getImageData(0, 0, w, h);
        const pixelsFront = imgDataFront.data;

        const tempCanvasBack = document.createElement('canvas');
        tempCanvasBack.width = w;
        tempCanvasBack.height = h;
        const tempCtxBack = tempCanvasBack.getContext('2d');
        tempCtxBack.drawImage(backCanvas, 0, 0, w, h);
        const imgDataBack = tempCtxBack.getImageData(0, 0, w, h);
        const pixelsBack = imgDataBack.data;

        if (isFirstLoad && selectedGenImage) {
            let rSum = 0,
                gSum = 0,
                bSum = 0;
            let validCornerCount = 0;
            const corners = [
                [0, 0],
                [w - 1, 0],
                [0, h - 1],
                [w - 1, h - 1],
            ];
            corners.forEach(([cx, cy]) => {
                const idx = (cy * w + cx) * 4;
                const alpha = pixelsFront[idx + 3];
                if (alpha > 50) {
                    rSum += pixelsFront[idx];
                    gSum += pixelsFront[idx + 1];
                    bSum += pixelsFront[idx + 2];
                    validCornerCount++;
                }
            });

            if (validCornerCount > 0) {
                bgColorR = Math.round(rSum / validCornerCount);
                bgColorG = Math.round(gSum / validCornerCount);
                bgColorB = Math.round(bSum / validCornerCount);
                const bgBrightness =
                    (0.299 * bgColorR + 0.587 * bgColorG + 0.114 * bgColorB) /
                    255;
                invertHeight = bgBrightness > 0.5;
                removeBg = true;
            } else {
                removeBg = false;
                let fgBrightnessSum = 0;
                let fgPixelCount = 0;
                for (let i = 0; i < pixelsFront.length; i += 4) {
                    const alpha = pixelsFront[i + 3];
                    if (alpha > 50) {
                        const r = pixelsFront[i];
                        const g = pixelsFront[i + 1];
                        const b = pixelsFront[i + 2];
                        const brightness =
                            (0.299 * r + 0.587 * g + 0.114 * b) / 255;
                        fgBrightnessSum += brightness;
                        fgPixelCount++;
                    }
                }
                const avgFgBrightness =
                    fgPixelCount > 0 ? fgBrightnessSum / fgPixelCount : 0.5;
                invertHeight = avgFgBrightness < 0.5;
            }
            isFirstLoad = false;
        }

        if (removeBg && selectedGenImage) {
            for (let y = 0; y < h; y++) {
                for (let x = 0; x < w; x++) {
                    const idx = (y * w + x) * 4;
                    const r = pixelsFront[idx];
                    const g = pixelsFront[idx + 1];
                    const b = pixelsFront[idx + 2];
                    const a = pixelsFront[idx + 3];

                    if (a > 0) {
                        const dist = Math.sqrt(
                            Math.pow((r - bgColorR) / 255, 2) +
                                Math.pow((g - bgColorG) / 255, 2) +
                                Math.pow((b - bgColorB) / 255, 2),
                        );
                        if (dist < bgTolerance) {
                            pixelsFront[idx + 3] = 0;
                        }
                    }
                }
            }
            tempCtxFront.putImageData(imgDataFront, 0, 0);

            for (let i = 0; i < pixelsFront.length; i += 4) {
                if (pixelsFront[i + 3] === 0) {
                    pixelsBack[i + 3] = 0;
                }
            }
            tempCtxBack.putImageData(imgDataBack, 0, 0);
        }

        if (modelType === 'plane') {
            buildThreeMeshes(
                tempCanvasFront,
                tempCanvasBack,
                pixelsFront,
                pixelsBack,
                w,
                h,
            );
        } else {
            loadAndProcessMockupModel(
                frontCanvas,
                backCanvas,
                pixelsFront,
                pixelsBack,
                w,
                h,
            );
        }
    }

    function detectFrontColor() {
        if (!selectedGenImage) return;
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = () => {
            const canvas = document.createElement('canvas');
            const maxDim = 128;
            let w = img.width;
            let h = img.height;
            if (w > maxDim || h > maxDim) {
                if (w > h) {
                    h = Math.round((h * maxDim) / w);
                    w = maxDim;
                } else {
                    w = Math.round((w * maxDim) / h);
                    h = maxDim;
                }
            }
            canvas.width = w;
            canvas.height = h;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, w, h);
            const imgData = ctx.getImageData(0, 0, w, h);
            const pixels = imgData.data;

            let fgR = 0,
                fgG = 0,
                fgB = 0,
                fgCount = 0;
            for (let y = 0; y < h; y++) {
                for (let x = 0; x < w; x++) {
                    const idx = (y * w + x) * 4;
                    const r = pixels[idx];
                    const g = pixels[idx + 1];
                    const b = pixels[idx + 2];
                    const a = pixels[idx + 3];

                    let isBg = false;
                    if (removeBg) {
                        const dist = Math.sqrt(
                            Math.pow((r - bgColorR) / 255, 2) +
                                Math.pow((g - bgColorG) / 255, 2) +
                                Math.pow((b - bgColorB) / 255, 2),
                        );
                        if (dist < bgTolerance) {
                            isBg = true;
                        }
                    }

                    if (a > 50 && !isBg) {
                        fgR += r;
                        fgG += g;
                        fgB += b;
                        fgCount++;
                    }
                }
            }
            const avgR = fgCount > 0 ? Math.round(fgR / fgCount) : 0;
            const avgG = fgCount > 0 ? Math.round(fgG / fgCount) : 0;
            const avgB = fgCount > 0 ? Math.round(fgB / fgCount) : 0;

            const rgbToHex = (r, g, b) =>
                '#' +
                [r, g, b]
                    .map((x) => {
                        const hex = x.toString(16);
                        return hex.length === 1 ? '0' + hex : hex;
                    })
                    .join('');

            selectedColor = rgbToHex(avgR, avgG, avgB);
            updateThreeMesh();
        };
        img.src = selectedGenImage;
    }

    function loadAndProcessMockupModel(
        canvasFront,
        canvasBack,
        pixelsFront,
        pixelsBack,
        w,
        h,
    ) {
        let modelPath = '';
        if (modelType === 'shirt') {
            modelPath = '/assets/shirt_baked.glb';
        } else if (modelType === 'custom') {
            modelPath = customMockupUrl;
        } else {
            const preset = presets.find((p) => p.id === modelType);
            modelPath = preset ? preset.path : '/assets/shirt_baked.glb';
        }

        if (!modelPath) {
            buildProceduralModel(
                modelType,
                canvasFront,
                canvasBack,
                pixelsFront,
                pixelsBack,
                w,
                h,
            );
            return;
        }

        if (cachedModels[modelPath]) {
            processMockupModel(
                cachedModels[modelPath],
                canvasFront,
                canvasBack,
                pixelsFront,
                pixelsBack,
                w,
                h,
            );
        } else {
            const loader = new THREE.GLTFLoader();
            loader.load(
                modelPath,
                (gltf) => {
                    cachedModels[modelPath] = gltf;
                    processMockupModel(
                        gltf,
                        canvasFront,
                        canvasBack,
                        pixelsFront,
                        pixelsBack,
                        w,
                        h,
                    );
                },
                undefined,
                (err) => {
                    // Seamless fallback to procedural model if GLB is missing or fails to load
                    console.warn(
                        `Could not load GLB model at ${modelPath}, falling back to procedural mesh:`,
                        err,
                    );
                    buildProceduralModel(
                        modelType,
                        canvasFront,
                        canvasBack,
                        pixelsFront,
                        pixelsBack,
                        w,
                        h,
                    );
                },
            );
        }
    }

    function processMockupModel(
        gltf,
        canvasFront,
        canvasBack,
        pixelsFront,
        pixelsBack,
        w,
        h,
    ) {
        cleanupThreeJS();

        threeMesh = new THREE.Group();

        const clonedScene = gltf.scene.clone();

        clonedScene.position.set(mockupX, mockupY, mockupZ);
        clonedScene.rotation.set(
            (mockupRotationX * Math.PI) / 180,
            (mockupRotationY * Math.PI) / 180,
            (mockupRotationZ * Math.PI) / 180,
        );
        clonedScene.scale.set(mockupScale, mockupScale, mockupScale);

        let targetMesh = null;
        clonedScene.traverse((child) => {
            if (child.isMesh && !targetMesh) {
                targetMesh = child;
            }
        });

        if (!targetMesh) {
            console.error('No mesh found in GLTF model');
            return;
        }

        const newMaterial = targetMesh.material.clone();
        newMaterial.color = new THREE.Color(selectedColor);
        newMaterial.roughness = roughness;
        newMaterial.metalness = metalness;
        newMaterial.side = THREE.DoubleSide;

        if (selectedMaterial === 'cotton') {
            newMaterial.roughness = 0.8;
            newMaterial.metalness = 0.05;
        } else if (selectedMaterial === 'polyester') {
            newMaterial.roughness = 0.5;
            newMaterial.metalness = 0.1;
        } else if (selectedMaterial === 'metal') {
            newMaterial.roughness = 0.2;
            newMaterial.metalness = 0.8;
        } else if (selectedMaterial === 'ceramic') {
            newMaterial.roughness = 0.3;
            newMaterial.metalness = 0.1;
        } else if (selectedMaterial === 'glass') {
            newMaterial.roughness = 0.1;
            newMaterial.metalness = 0.1;
            newMaterial.transparent = true;
            newMaterial.opacity = 0.6;
        } else if (selectedMaterial === 'plastic') {
            newMaterial.roughness = 0.4;
            newMaterial.metalness = 0.0;
        }

        targetMesh.material = newMaterial;
        threeMesh.add(clonedScene);

        const aspect = h / w;

        if (canvasFront) {
            const textureFront = new THREE.CanvasTexture(canvasFront);
            textureFront.colorSpace = THREE.SRGBColorSpace;

            const rotationRad = (logoRotation * Math.PI) / 180;
            const decalGeoFront = new THREE.DecalGeometry(
                targetMesh,
                new THREE.Vector3(logoX, logoY, logoZ),
                new THREE.Euler(0, 0, rotationRad),
                new THREE.Vector3(logoScale, logoScale * aspect, 0.2),
            );
            const decalMatFront = new THREE.MeshStandardMaterial({
                map: textureFront,
                transparent: true,
                opacity: logoOpacity,
                roughness: roughness,
                metalness: metalness,
                polygonOffset: true,
                polygonOffsetFactor: -4,
            });
            const decalFront = new THREE.Mesh(decalGeoFront, decalMatFront);
            targetMesh.add(decalFront);
        }

        if (canvasBack) {
            const textureBack = new THREE.CanvasTexture(canvasBack);
            textureBack.colorSpace = THREE.SRGBColorSpace;

            const rotationRad = (logoRotation * Math.PI) / 180;
            const decalGeoBack = new THREE.DecalGeometry(
                targetMesh,
                new THREE.Vector3(-logoX, logoY, logoZBack),
                new THREE.Euler(0, Math.PI, rotationRad),
                new THREE.Vector3(logoScale, logoScale * aspect, 0.2),
            );
            const decalMatBack = new THREE.MeshStandardMaterial({
                map: textureBack,
                transparent: true,
                opacity: logoOpacity,
                roughness: roughness,
                metalness: metalness,
                polygonOffset: true,
                polygonOffsetFactor: -4,
            });
            const decalBack = new THREE.Mesh(decalGeoBack, decalMatBack);
            targetMesh.add(decalBack);
        }

        threeScene.add(threeMesh);
        threeGeometry = targetMesh.geometry;

        if (threeRenderer && threeCamera) {
            animate();
        }
    }

    function buildProceduralModel(
        type,
        canvasFront,
        canvasBack,
        pixelsFront,
        pixelsBack,
        w,
        h,
    ) {
        cleanupThreeJS();

        threeMesh = new THREE.Group();

        let targetMesh = null;
        let proceduralGroup = new THREE.Group();

        let localLogoZ = logoZ;
        let localLogoZBack = logoZBack;

        if (type === 'shirt') {
            localLogoZ = 0.72;
            localLogoZBack = -0.72;
        } else if (type === 'hoodie') {
            localLogoZ = 0.77;
            localLogoZBack = -0.77;
        } else if (type === 'mug') {
            localLogoZ = 0.82;
            localLogoZBack = -0.82;
        } else if (type === 'tumbler') {
            localLogoZ = 0.67;
            localLogoZBack = -0.67;
        } else if (type === 'botol') {
            localLogoZ = 0.62;
            localLogoZBack = -0.62;
        } else if (type === 'cap') {
            localLogoZ = 0.45;
            localLogoZBack = -0.45;
        } else if (type === 'totebag') {
            localLogoZ = 0.12;
            localLogoZBack = -0.12;
        } else if (type === 'piring') {
            localLogoZ = 0.05;
            localLogoZBack = -0.05;
        } else if (type === 'plakat') {
            localLogoZ = 0.08;
            localLogoZBack = -0.08;
        }

        const matColor = new THREE.Color(selectedColor);
        const material = new THREE.MeshStandardMaterial({
            color: matColor,
            roughness: roughness,
            metalness: metalness,
            side: THREE.DoubleSide,
        });

        if (selectedMaterial === 'cotton') {
            material.roughness = 0.8;
            material.metalness = 0.05;
        } else if (selectedMaterial === 'polyester') {
            material.roughness = 0.5;
            material.metalness = 0.1;
        } else if (selectedMaterial === 'metal') {
            material.roughness = 0.2;
            material.metalness = 0.85;
        } else if (selectedMaterial === 'ceramic') {
            material.roughness = 0.25;
            material.metalness = 0.05;
        } else if (selectedMaterial === 'glass') {
            material.roughness = 0.1;
            material.metalness = 0.1;
            material.transparent = true;
            material.opacity = 0.5;
        } else if (selectedMaterial === 'plastic') {
            material.roughness = 0.35;
            material.metalness = 0.0;
        }

        if (type === 'shirt') {
            // Body of the shirt: Cylinder
            const bodyGeo = new THREE.CylinderGeometry(0.7, 0.7, 1.8, 32);
            const body = new THREE.Mesh(bodyGeo, material);
            proceduralGroup.add(body);

            // Left Sleeve: Cylinder rotated
            const leftSleeveGeo = new THREE.CylinderGeometry(
                0.24,
                0.2,
                0.6,
                16,
            );
            const leftSleeve = new THREE.Mesh(leftSleeveGeo, material);
            leftSleeve.position.set(0.75, 0.6, 0);
            leftSleeve.rotation.z = -Math.PI / 4;
            proceduralGroup.add(leftSleeve);

            // Right Sleeve: Cylinder rotated
            const rightSleeveGeo = new THREE.CylinderGeometry(
                0.24,
                0.2,
                0.6,
                16,
            );
            const rightSleeve = new THREE.Mesh(rightSleeveGeo, material);
            rightSleeve.position.set(-0.75, 0.6, 0);
            rightSleeve.rotation.z = Math.PI / 4;
            proceduralGroup.add(rightSleeve);

            // Collar/Neck: Torus representing collar rim
            const collarMat = new THREE.MeshStandardMaterial({
                color: new THREE.Color(selectedColor),
                roughness: Math.min(1.0, roughness + 0.15),
                metalness: metalness,
            });
            const collarGeo = new THREE.TorusGeometry(0.3, 0.06, 8, 32);
            const collar = new THREE.Mesh(collarGeo, collarMat);
            collar.position.set(0, 0.9, 0);
            collar.rotation.x = Math.PI / 2;
            proceduralGroup.add(collar);

            targetMesh = body;
        } else if (type === 'hoodie') {
            // Body of the hoodie
            const bodyGeo = new THREE.CylinderGeometry(0.75, 0.72, 1.8, 32);
            const body = new THREE.Mesh(bodyGeo, material);
            proceduralGroup.add(body);

            // Left Sleeve
            const leftSleeveGeo = new THREE.CylinderGeometry(
                0.25,
                0.18,
                0.75,
                16,
            );
            const leftSleeve = new THREE.Mesh(leftSleeveGeo, material);
            leftSleeve.position.set(0.85, 0.45, 0);
            leftSleeve.rotation.z = -Math.PI / 3;
            proceduralGroup.add(leftSleeve);

            // Right Sleeve
            const rightSleeveGeo = new THREE.CylinderGeometry(
                0.25,
                0.18,
                0.75,
                16,
            );
            const rightSleeve = new THREE.Mesh(rightSleeveGeo, material);
            rightSleeve.position.set(-0.85, 0.45, 0);
            rightSleeve.rotation.z = Math.PI / 3;
            proceduralGroup.add(rightSleeve);

            // Hoodie Cap (Hood)
            const hoodGeo = new THREE.SphereGeometry(0.48, 32, 32);
            const hood = new THREE.Mesh(hoodGeo, material);
            hood.position.set(0, 1.25, -0.15);
            proceduralGroup.add(hood);

            // Front pocket
            const pocketGeo = new THREE.BoxGeometry(0.6, 0.35, 0.15);
            const pocket = new THREE.Mesh(pocketGeo, material);
            pocket.position.set(0, -0.4, 0.65);
            proceduralGroup.add(pocket);

            targetMesh = body;
        } else if (type === 'mug') {
            const cupGeo = new THREE.CylinderGeometry(
                0.8,
                0.8,
                1.8,
                32,
                1,
                true,
            );
            const cupOuter = new THREE.Mesh(cupGeo, material);
            proceduralGroup.add(cupOuter);

            const cupInnerGeo = new THREE.CylinderGeometry(
                0.76,
                0.76,
                1.76,
                32,
                1,
                true,
            );
            const cupInner = new THREE.Mesh(cupInnerGeo, material);
            cupInner.scale.set(1, 1, -1);
            proceduralGroup.add(cupInner);

            const bottomGeo = new THREE.CylinderGeometry(0.8, 0.8, 0.04, 32);
            const bottom = new THREE.Mesh(bottomGeo, material);
            bottom.position.y = -0.9;
            proceduralGroup.add(bottom);

            const handleGeo = new THREE.TorusGeometry(
                0.5,
                0.12,
                16,
                64,
                Math.PI * 1.3,
            );
            const handle = new THREE.Mesh(handleGeo, material);
            handle.position.set(-0.72, 0, 0);
            handle.rotation.z = Math.PI * 0.35;
            proceduralGroup.add(handle);

            targetMesh = cupOuter;
        } else if (type === 'tumbler') {
            const bodyGeo = new THREE.CylinderGeometry(0.65, 0.55, 2.2, 32);
            const body = new THREE.Mesh(bodyGeo, material);
            proceduralGroup.add(body);

            const steelMat = new THREE.MeshStandardMaterial({
                color: new THREE.Color('#d1d5db'),
                roughness: 0.2,
                metalness: 0.85,
            });
            const collarGeo = new THREE.CylinderGeometry(0.55, 0.65, 0.2, 32);
            const collar = new THREE.Mesh(collarGeo, steelMat);
            collar.position.y = 1.15;
            proceduralGroup.add(collar);

            const capGeo = new THREE.CylinderGeometry(0.48, 0.52, 0.35, 32);
            const cap = new THREE.Mesh(capGeo, material);
            cap.position.y = 1.4;
            proceduralGroup.add(cap);

            targetMesh = body;
        } else if (type === 'botol') {
            const bodyGeo = new THREE.CylinderGeometry(0.6, 0.6, 2.0, 32);
            const body = new THREE.Mesh(bodyGeo, material);
            proceduralGroup.add(body);

            const neckGeo = new THREE.CylinderGeometry(0.35, 0.6, 0.4, 32);
            const neck = new THREE.Mesh(neckGeo, material);
            neck.position.y = 1.1;
            proceduralGroup.add(neck);

            const lidMat = new THREE.MeshStandardMaterial({
                color: new THREE.Color('#1e293b'),
                roughness: 0.4,
                metalness: 0.1,
            });
            const capGeo = new THREE.CylinderGeometry(0.38, 0.38, 0.3, 32);
            const cap = new THREE.Mesh(capGeo, lidMat);
            cap.position.y = 1.35;
            proceduralGroup.add(cap);

            targetMesh = body;
        } else if (type === 'cap') {
            const domeGeo = new THREE.SphereGeometry(
                0.85,
                32,
                16,
                0,
                Math.PI * 2,
                0,
                Math.PI / 2,
            );
            const dome = new THREE.Mesh(domeGeo, material);
            dome.rotation.x = -Math.PI / 2;
            proceduralGroup.add(dome);

            const visorGeo = new THREE.BoxGeometry(1.0, 0.04, 0.65);
            const visor = new THREE.Mesh(visorGeo, material);
            visor.position.set(0, -0.4, 0.7);
            visor.rotation.x = Math.PI * 0.04;
            proceduralGroup.add(visor);

            targetMesh = dome;
        } else if (type === 'totebag') {
            const bagGeo = new THREE.BoxGeometry(1.5, 1.7, 0.15, 4, 4, 1);
            const bag = new THREE.Mesh(bagGeo, material);
            proceduralGroup.add(bag);

            const strapMat = new THREE.MeshStandardMaterial({
                color: new THREE.Color('#e2e8f0'),
                roughness: 0.9,
                metalness: 0.0,
                side: THREE.DoubleSide,
            });
            const strapGeo = new THREE.TorusGeometry(0.4, 0.05, 8, 32, Math.PI);
            const strap1 = new THREE.Mesh(strapGeo, strapMat);
            strap1.position.set(0, 0.85, 0.06);
            proceduralGroup.add(strap1);

            const strap2 = new THREE.Mesh(strapGeo, strapMat);
            strap2.position.set(0, 0.85, -0.06);
            proceduralGroup.add(strap2);

            targetMesh = bag;
        } else if (type === 'piring') {
            const plateGeo = new THREE.CylinderGeometry(
                1.4,
                1.1,
                0.16,
                64,
                1,
                true,
            );
            const plate = new THREE.Mesh(plateGeo, material);
            plate.rotation.x = Math.PI / 2;
            proceduralGroup.add(plate);

            const baseGeo = new THREE.CylinderGeometry(1.1, 1.1, 0.02, 64);
            const base = new THREE.Mesh(baseGeo, material);
            base.position.z = -0.07;
            base.rotation.x = Math.PI / 2;
            proceduralGroup.add(base);

            targetMesh = base;
        } else if (type === 'plakat') {
            const plaqueGeo = new THREE.BoxGeometry(1.4, 1.9, 0.08);
            const glassMat = new THREE.MeshStandardMaterial({
                color: new THREE.Color('#ffffff'),
                roughness: 0.05,
                metalness: 0.1,
                transparent: true,
                opacity: 0.6,
                side: THREE.DoubleSide,
            });
            const plaque = new THREE.Mesh(plaqueGeo, glassMat);
            proceduralGroup.add(plaque);

            const baseMat = new THREE.MeshStandardMaterial({
                color: new THREE.Color('#5c4033'),
                roughness: 0.7,
                metalness: 0.1,
            });
            const baseGeo = new THREE.BoxGeometry(1.6, 0.15, 0.6);
            const base = new THREE.Mesh(baseGeo, baseMat);
            base.position.y = -1.0;
            proceduralGroup.add(base);

            targetMesh = plaque;
        } else {
            const genericGeo = new THREE.BoxGeometry(1.5, 2.0, 0.2);
            const generic = new THREE.Mesh(genericGeo, material);
            proceduralGroup.add(generic);
            targetMesh = generic;
        }

        threeMesh.add(proceduralGroup);

        proceduralGroup.position.set(mockupX, mockupY, mockupZ);
        proceduralGroup.rotation.set(
            (mockupRotationX * Math.PI) / 180,
            (mockupRotationY * Math.PI) / 180,
            (mockupRotationZ * Math.PI) / 180,
        );

        const preset = presets.find((p) => p.id === type);
        const presetScale = preset ? preset.scale : 1.0;
        const finalScale = mockupScale / presetScale;
        proceduralGroup.scale.set(finalScale, finalScale, finalScale);

        const aspect = h / w;

        if (canvasFront) {
            const textureFront = new THREE.CanvasTexture(canvasFront);
            textureFront.colorSpace = THREE.SRGBColorSpace;

            const rotationRad = (logoRotation * Math.PI) / 180;
            const decalGeoFront = new THREE.DecalGeometry(
                targetMesh,
                new THREE.Vector3(logoX, logoY, localLogoZ),
                new THREE.Euler(0, 0, rotationRad),
                new THREE.Vector3(logoScale, logoScale * aspect, 0.2),
            );
            const decalMatFront = new THREE.MeshStandardMaterial({
                map: textureFront,
                transparent: true,
                opacity: logoOpacity,
                roughness: roughness,
                metalness: metalness,
                polygonOffset: true,
                polygonOffsetFactor: -4,
            });
            const decalFront = new THREE.Mesh(decalGeoFront, decalMatFront);
            targetMesh.add(decalFront);
        }

        if (canvasBack) {
            const textureBack = new THREE.CanvasTexture(canvasBack);
            textureBack.colorSpace = THREE.SRGBColorSpace;

            const rotationRad = (logoRotation * Math.PI) / 180;
            const decalGeoBack = new THREE.DecalGeometry(
                targetMesh,
                new THREE.Vector3(-logoX, logoY, localLogoZBack),
                new THREE.Euler(0, Math.PI, rotationRad),
                new THREE.Vector3(logoScale, logoScale * aspect, 0.2),
            );
            const decalMatBack = new THREE.MeshStandardMaterial({
                map: textureBack,
                transparent: true,
                opacity: logoOpacity,
                roughness: roughness,
                metalness: metalness,
                polygonOffset: true,
                polygonOffsetFactor: -4,
            });
            const decalBack = new THREE.Mesh(decalGeoBack, decalMatBack);
            targetMesh.add(decalBack);
        }

        threeScene.add(threeMesh);
        threeGeometry = targetMesh.geometry;

        if (threeRenderer && threeCamera) {
            animate();
        }
    }

    function buildThreeMeshes(
        canvasFront,
        canvasBack,
        pixelsFront,
        pixelsBack,
        w,
        h,
    ) {
        cleanupThreeJS();

        threeMesh = new THREE.Group();

        const frontPositions = [];
        const frontNormals = [];
        const frontUvs = [];
        const frontIndices = [];
        let frontVertexIndex = 0;

        const backPositions = [];
        const backNormals = [];
        const backUvs = [];
        const backIndices = [];
        let backVertexIndex = 0;

        const sidePositions = [];
        const sideNormals = [];
        const sideUvs = [];
        const sideIndices = [];
        let sideVertexIndex = 0;

        const dx = 3 / w;
        const dy = 3 / h;
        const halfThick = baseThickness / 2;

        const isOpaque = (px, py) => {
            if (px < 0 || px >= w || py < 0 || py >= h) return false;
            const idx = (py * w + px) * 4;
            return pixelsFront[idx + 3] > 50;
        };

        const getZHeight = (px, py, side) => {
            const idx = (py * w + px) * 4;
            const r = pixelsFront[idx] || 0;
            const g = pixelsFront[idx + 1] || 0;
            const b = pixelsFront[idx + 2] || 0;
            let brightness = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
            if (invertHeight) brightness = 1 - brightness;

            if (side === 'front') {
                return halfThick + (flatMesh ? 0 : brightness * reliefDepth);
            } else {
                let backBrightness = brightness;
                if (pixelsBack) {
                    const bIdx = (py * w + px) * 4;
                    const br = pixelsBack[bIdx] || 0;
                    const bg = pixelsBack[bIdx + 1] || 0;
                    const bb = pixelsBack[bIdx + 2] || 0;
                    backBrightness =
                        (0.299 * br + 0.587 * bg + 0.114 * bb) / 255;
                    if (invertHeight) backBrightness = 1 - backBrightness;
                }
                return (
                    -halfThick - (flatMesh ? 0 : backBrightness * reliefDepth)
                );
            }
        };

        for (let y = 0; y < h; y++) {
            for (let x = 0; x < w; x++) {
                if (!isOpaque(x, y)) continue;

                const X = (x / (w - 1) - 0.5) * 3;
                const Y = (0.5 - y / (h - 1)) * 3;

                const zF = getZHeight(x, y, 'front');
                const zB = getZHeight(x, y, 'back');

                // Front Quad
                frontPositions.push(
                    X - dx / 2,
                    Y - dy / 2,
                    zF,
                    X + dx / 2,
                    Y - dy / 2,
                    zF,
                    X + dx / 2,
                    Y + dy / 2,
                    zF,
                    X - dx / 2,
                    Y + dy / 2,
                    zF,
                );
                frontNormals.push(0, 0, 1, 0, 0, 1, 0, 0, 1, 0, 0, 1);
                frontUvs.push(
                    x / w,
                    1 - y / h,
                    (x + 1) / w,
                    1 - y / h,
                    (x + 1) / w,
                    1 - (y + 1) / h,
                    x / w,
                    1 - (y + 1) / h,
                );
                frontIndices.push(
                    frontVertexIndex,
                    frontVertexIndex + 1,
                    frontVertexIndex + 2,
                    frontVertexIndex,
                    frontVertexIndex + 2,
                    frontVertexIndex + 3,
                );
                frontVertexIndex += 4;

                // Back Quad
                backPositions.push(
                    X + dx / 2,
                    Y - dy / 2,
                    zB,
                    X - dx / 2,
                    Y - dy / 2,
                    zB,
                    X - dx / 2,
                    Y + dy / 2,
                    zB,
                    X + dx / 2,
                    Y + dy / 2,
                    zB,
                );
                backNormals.push(0, 0, -1, 0, 0, -1, 0, 0, -1, 0, 0, -1);
                backUvs.push(
                    1 - (x + 1) / w,
                    1 - y / h,
                    1 - x / w,
                    1 - y / h,
                    1 - x / w,
                    1 - (y + 1) / h,
                    1 - (x + 1) / w,
                    1 - (y + 1) / h,
                );
                backIndices.push(
                    backVertexIndex,
                    backVertexIndex + 1,
                    backVertexIndex + 2,
                    backVertexIndex,
                    backVertexIndex + 2,
                    backVertexIndex + 3,
                );
                backVertexIndex += 4;

                // Sides (connecting quads)
                if (!isOpaque(x + 1, y)) {
                    sidePositions.push(
                        X + dx / 2,
                        Y - dy / 2,
                        zB,
                        X + dx / 2,
                        Y + dy / 2,
                        zB,
                        X + dx / 2,
                        Y + dy / 2,
                        zF,
                        X + dx / 2,
                        Y - dy / 2,
                        zF,
                    );
                    sideNormals.push(1, 0, 0, 1, 0, 0, 1, 0, 0, 1, 0, 0);
                    sideUvs.push(0, 0, 0, 0, 0, 0, 0, 0);
                    sideIndices.push(
                        sideVertexIndex,
                        sideVertexIndex + 1,
                        sideVertexIndex + 2,
                        sideVertexIndex,
                        sideVertexIndex + 2,
                        sideVertexIndex + 3,
                    );
                    sideVertexIndex += 4;
                }
                if (!isOpaque(x - 1, y)) {
                    sidePositions.push(
                        X - dx / 2,
                        Y - dy / 2,
                        zF,
                        X - dx / 2,
                        Y + dy / 2,
                        zF,
                        X - dx / 2,
                        Y + dy / 2,
                        zB,
                        X - dx / 2,
                        Y - dy / 2,
                        zB,
                    );
                    sideNormals.push(-1, 0, 0, -1, 0, 0, -1, 0, 0, -1, 0, 0);
                    sideUvs.push(0, 0, 0, 0, 0, 0, 0, 0);
                    sideIndices.push(
                        sideVertexIndex,
                        sideVertexIndex + 1,
                        sideVertexIndex + 2,
                        sideVertexIndex,
                        sideVertexIndex + 2,
                        sideVertexIndex + 3,
                    );
                    sideVertexIndex += 4;
                }
                if (!isOpaque(x, y - 1)) {
                    sidePositions.push(
                        X - dx / 2,
                        Y + dy / 2,
                        zB,
                        X - dx / 2,
                        Y + dy / 2,
                        zF,
                        X + dx / 2,
                        Y + dy / 2,
                        zF,
                        X + dx / 2,
                        Y + dy / 2,
                        zB,
                    );
                    sideNormals.push(0, 1, 0, 0, 1, 0, 0, 1, 0, 0, 1, 0);
                    sideUvs.push(0, 0, 0, 0, 0, 0, 0, 0);
                    sideIndices.push(
                        sideVertexIndex,
                        sideVertexIndex + 1,
                        sideVertexIndex + 2,
                        sideVertexIndex,
                        sideVertexIndex + 2,
                        sideVertexIndex + 3,
                    );
                    sideVertexIndex += 4;
                }
                if (!isOpaque(x, y + 1)) {
                    sidePositions.push(
                        X - dx / 2,
                        Y - dy / 2,
                        zF,
                        X - dx / 2,
                        Y - dy / 2,
                        zB,
                        X + dx / 2,
                        Y - dy / 2,
                        zB,
                        X + dx / 2,
                        Y - dy / 2,
                        zF,
                    );
                    sideNormals.push(0, -1, 0, 0, -1, 0, 0, -1, 0, -1, 0, 0);
                    sideUvs.push(0, 0, 0, 0, 0, 0, 0, 0);
                    sideIndices.push(
                        sideVertexIndex,
                        sideVertexIndex + 1,
                        sideVertexIndex + 2,
                        sideVertexIndex,
                        sideVertexIndex + 2,
                        sideVertexIndex + 3,
                    );
                    sideVertexIndex += 4;
                }
            }
        }

        if (frontPositions.length > 0) {
            const geoFront = new THREE.BufferGeometry();
            geoFront.setAttribute(
                'position',
                new THREE.Float32BufferAttribute(frontPositions, 3),
            );
            geoFront.setAttribute(
                'normal',
                new THREE.Float32BufferAttribute(frontNormals, 3),
            );
            geoFront.setAttribute(
                'uv',
                new THREE.Float32BufferAttribute(frontUvs, 2),
            );
            geoFront.setIndex(frontIndices);

            threeTexture = new THREE.CanvasTexture(frontCanvas);
            threeTexture.colorSpace = THREE.SRGBColorSpace;
            threeMaterial = new THREE.MeshStandardMaterial({
                map: threeTexture,
                roughness: roughness,
                metalness: metalness,
                color: new THREE.Color(tintColor),
                side: THREE.DoubleSide,
                transparent: true,
                alphaTest: 0.05,
            });
            const meshFront = new THREE.Mesh(geoFront, threeMaterial);
            threeMesh.add(meshFront);
            threeGeometry = geoFront;
        }

        if (backPositions.length > 0) {
            const geoBack = new THREE.BufferGeometry();
            geoBack.setAttribute(
                'position',
                new THREE.Float32BufferAttribute(backPositions, 3),
            );
            geoBack.setAttribute(
                'normal',
                new THREE.Float32BufferAttribute(backNormals, 3),
            );
            geoBack.setAttribute(
                'uv',
                new THREE.Float32BufferAttribute(backUvs, 2),
            );
            geoBack.setIndex(backIndices);

            const texBack = new THREE.CanvasTexture(backCanvas);
            texBack.colorSpace = THREE.SRGBColorSpace;
            const matBack = new THREE.MeshStandardMaterial({
                map: texBack,
                roughness: roughness,
                metalness: metalness,
                color: new THREE.Color(tintColor),
                side: THREE.DoubleSide,
                transparent: true,
                alphaTest: 0.05,
            });
            const meshBack = new THREE.Mesh(geoBack, matBack);
            threeMesh.add(meshBack);
        }

        if (sidePositions.length > 0) {
            const geoSide = new THREE.BufferGeometry();
            geoSide.setAttribute(
                'position',
                new THREE.Float32BufferAttribute(sidePositions, 3),
            );
            geoSide.setAttribute(
                'normal',
                new THREE.Float32BufferAttribute(sideNormals, 3),
            );
            geoSide.setAttribute(
                'uv',
                new THREE.Float32BufferAttribute(sideUvs, 2),
            );
            geoSide.setIndex(sideIndices);

            const matSide = new THREE.MeshStandardMaterial({
                color: new THREE.Color(sideColor),
                roughness: roughness,
                metalness: metalness,
                side: THREE.DoubleSide,
            });
            const meshSide = new THREE.Mesh(geoSide, matSide);
            threeMesh.add(meshSide);
        }

        threeScene.add(threeMesh);

        if (threeRenderer && threeCamera) {
            animate();
        }
    }

    function animate() {
        if (!threeRenderer || !threeScene || !threeCamera) return;
        animationFrameId = requestAnimationFrame(animate);
        if (autoRotate && threeMesh) {
            threeMesh.rotation.y += 0.005;
        }
        threeRenderer.render(threeScene, threeCamera);
    }

    function cleanupThreeJS() {
        if (resizeListener) {
            window.removeEventListener('resize', resizeListener);
            resizeListener = null;
        }
        if (animationFrameId) {
            cancelAnimationFrame(animationFrameId);
        }
        if (threeMesh) {
            threeMesh.traverse((child) => {
                if (child.isMesh) {
                    if (child.geometry) child.geometry.dispose();
                    if (child.material) {
                        if (Array.isArray(child.material)) {
                            child.material.forEach((m) => m.dispose());
                        } else {
                            child.material.dispose();
                        }
                    }
                }
            });
            if (threeScene) {
                threeScene.remove(threeMesh);
            }
        }
        threeMesh = null;
    }

    function applyGeneratedModel() {
        if (!threeMesh) return;

        // Ensure fflate is defined globally right before USDZExporter runs
        if (!window.fflate && typeof fflate !== 'undefined') {
            window.fflate = fflate;
        }

        const prevAutoRotate = autoRotate;
        autoRotate = false;
        threeMesh.rotation.set(0, 0, 0);

        // 1. Export GLB format via GLTFExporter
        // NOTE: Three.js r128 GLTFExporter.parse() takes 3 args: (scene, onDone, options)
        // The 4-arg variant (with error callback) was added in r137+.
        const gltfExporter = new THREE.GLTFExporter();
        gltfExporter.parse(
            threeMesh,
            (glbResult) => {
                // glbResult is an ArrayBuffer when binary: true
                const glbBlob = new Blob([glbResult], {
                    type: 'model/gltf-binary',
                });
                const glbFile = new File(
                    [glbBlob],
                    'ai_generated_product.glb',
                    { type: 'model/gltf-binary' },
                );

                form.model_3d_file = glbFile;
                model3dFileName = 'ai_generated_product.glb';

                // 2. Export USDZ format via USDZExporter (for iOS Augmented Reality support)
                try {
                    const usdzExporter = new THREE.USDZExporter();
                    const usdzPromise = usdzExporter.parse(
                        threeMesh,
                        (usdzResult) => {
                            if (usdzResult) {
                                const usdzBlob = new Blob([usdzResult], {
                                    type: 'model/vnd.usdz+zip',
                                });
                                const usdzFile = new File(
                                    [usdzBlob],
                                    'ai_generated_product.usdz',
                                    { type: 'model/vnd.usdz+zip' },
                                );

                                form.model_3d_usdz_file = usdzFile;
                                model3dUsdzFileName =
                                    'ai_generated_product.usdz';
                            }

                            cleanupThreeJS();
                            isImageTo3dModalOpen = false;
                        },
                    );

                    // Fallback: USDZExporter may return a Promise instead of calling callback
                    if (usdzPromise && typeof usdzPromise.then === 'function') {
                        usdzPromise.then((usdzResult) => {
                            const usdzBlob = new Blob([usdzResult], {
                                type: 'model/vnd.usdz+zip',
                            });
                            const usdzFile = new File(
                                [usdzBlob],
                                'ai_generated_product.usdz',
                                { type: 'model/vnd.usdz+zip' },
                            );

                            form.model_3d_usdz_file = usdzFile;
                            model3dUsdzFileName = 'ai_generated_product.usdz';

                            cleanupThreeJS();
                            isImageTo3dModalOpen = false;
                        });
                    }
                } catch (usdzErr) {
                    console.error('Error exporting USDZ:', usdzErr);
                    // Fallback: close modal even if USDZ fails
                    cleanupThreeJS();
                    isImageTo3dModalOpen = false;
                }
            },
            { binary: true },
        ); // r128 API: 3rd arg is options, NOT error callback
    }
    let variations = $state([]); // up to 2
    let variants = $state([]); // combinations
    let globalCustomPrice = $state(false);
    let globalCustomStock = $state(false);
    let globalCustomWeight = $state(false);

    let specifications = $state([]);

    function addSpecification() {
        specifications = [...specifications, { label: '', value: '' }];
    }

    function removeSpecification(index) {
        specifications = specifications.filter((_, i) => i !== index);
    }

    let showSizeChart = $state(false);
    let sizeChartHeaders = $state([
        'Ukuran',
        'Lebar Dada (cm)',
        'Panjang (cm)',
        'Panjang Lengan (cm)',
    ]);
    let sizeChartRows = $state([
        {
            size: 'S',
            values: ['48', '68', '21'],
            min_height: 150,
            max_height: 160,
            min_weight: 45,
            max_weight: 55,
        },
        {
            size: 'M',
            values: ['50', '70', '22'],
            min_height: 160,
            max_height: 170,
            min_weight: 55,
            max_weight: 65,
        },
        {
            size: 'L',
            values: ['52', '72', '23'],
            min_height: 170,
            max_height: 180,
            min_weight: 65,
            max_weight: 75,
        },
        {
            size: 'XL',
            values: ['54', '74', '24'],
            min_height: 180,
            max_height: 190,
            min_weight: 75,
            max_weight: 85,
        },
    ]);

    function addSizeChartHeader() {
        sizeChartHeaders = [...sizeChartHeaders, 'Kolom Baru'];
        sizeChartRows = sizeChartRows.map((row) => ({
            ...row,
            values: [...row.values, ''],
        }));
    }

    function removeSizeChartHeader(index) {
        if (index === 0) return;
        sizeChartHeaders = sizeChartHeaders.filter((_, i) => i !== index);
        sizeChartRows = sizeChartRows.map((row) => ({
            ...row,
            values: row.values.filter((_, i) => i !== index - 1),
        }));
    }

    function addSizeChartRow() {
        const valCount = sizeChartHeaders.length - 1;
        const emptyVals = Array(valCount).fill('');
        sizeChartRows = [
            ...sizeChartRows,
            {
                size: '',
                values: emptyVals,
                min_height: '',
                max_height: '',
                min_weight: '',
                max_weight: '',
            },
        ];
    }

    function removeSizeChartRow(index) {
        sizeChartRows = sizeChartRows.filter((_, i) => i !== index);
    }

    // Derived category options for SelectSearch
    let categoryOptions = $derived(
        categories.map((c) => ({ id: c.id, name: c.name })),
    );

    // Derived brand options for SelectSearch
    let brandOptions = $derived(
        brands.map((b) => ({ id: b.id, name: b.name })),
    );

    let globalTaxEnabled = $derived(page.props.settings?.tax_enabled ?? false);
    let globalTaxPercentage = $derived(
        page.props.settings?.tax_percentage ?? 0,
    );
    let taxAmount = $derived(
        form.tax_enabled && form.price
            ? (Number(form.price) * globalTaxPercentage) / 100
            : 0,
    );
    let finalPrice = $derived(form.price ? Number(form.price) + taxAmount : 0);

    function triggerPhotoUpload() {
        document.getElementById('multi-photo-input').click();
    }

    function compressImageIfNeeded(file) {
        return new Promise((resolve, reject) => {
            if (!file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = (e) => resolve(e.target.result);
                reader.onerror = (err) => reject(err);
                reader.readAsDataURL(file);
                return;
            }

            const sizeLimit = 5 * 1024 * 1024; // 5MB
            if (file.size <= sizeLimit) {
                const reader = new FileReader();
                reader.onload = (e) => resolve(e.target.result);
                reader.onerror = (err) => reject(err);
                reader.readAsDataURL(file);
                return;
            }

            const reader = new FileReader();
            reader.onload = (event) => {
                const img = new Image();
                img.onload = () => {
                    const canvas = document.createElement('canvas');
                    let width = img.width;
                    let height = img.height;

                    const maxDim = 2048;
                    if (width > maxDim || height > maxDim) {
                        if (width > height) {
                            height = Math.round((height * maxDim) / width);
                            width = maxDim;
                        } else {
                            width = Math.round((width * maxDim) / height);
                            height = maxDim;
                        }
                    }

                    canvas.width = width;
                    canvas.height = height;

                    const ctx = canvas.getContext('2d');
                    if (!ctx) {
                        reject(
                            new Error('Gagal mendapatkan 2D context canvas.'),
                        );
                        return;
                    }
                    ctx.drawImage(img, 0, 0, width, height);

                    const compressedDataUrl = canvas.toDataURL(
                        'image/jpeg',
                        0.7,
                    );
                    resolve(compressedDataUrl);
                };
                img.onerror = (err) => reject(err);
                img.src = event.target.result;
            };
            reader.onerror = (err) => reject(err);
            reader.readAsDataURL(file);
        });
    }

    async function handlePhotoUpload(event) {
        const files = Array.from(event.target.files);
        try {
            const promises = files.map((file) => compressImageIfNeeded(file));
            const results = await Promise.all(promises);
            uploadedPhotos = [...uploadedPhotos, ...results];
        } catch (error) {
            console.error('Error processing upload:', error);
            showToast('Gagal memproses beberapa gambar.', 'error');
        }
    }

    function removePhoto(index) {
        uploadedPhotos = uploadedPhotos.filter((_, i) => i !== index);
    }

    function movePhoto(index, direction) {
        const targetIndex = index + direction;
        if (targetIndex < 0 || targetIndex >= uploadedPhotos.length) return;
        const temp = uploadedPhotos[index];
        uploadedPhotos[index] = uploadedPhotos[targetIndex];
        uploadedPhotos[targetIndex] = temp;
        uploadedPhotos = [...uploadedPhotos];
    }

    function moveVariation(index, direction) {
        const targetIndex = index + direction;
        if (targetIndex < 0 || targetIndex >= variations.length) return;
        const temp = variations[index];
        variations[index] = variations[targetIndex];
        variations[targetIndex] = temp;
        variations = [...variations];
        generateCombinations();
    }

    function moveOption(vIndex, oIndex, direction) {
        const targetIndex = oIndex + direction;
        if (targetIndex < 0 || targetIndex >= variations[vIndex].options.length)
            return;
        const temp = variations[vIndex].options[oIndex];
        variations[vIndex].options[oIndex] =
            variations[vIndex].options[targetIndex];
        variations[vIndex].options[targetIndex] = temp;
        variations[vIndex].options = [...variations[vIndex].options];
        generateCombinations();
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
                sku: form.sku
                    ? `${form.sku}-${opt.name.toUpperCase().replace(/[^A-Z0-9]/g, '')}`
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
                        sku: form.sku
                            ? `${form.sku}-${o1.name.toUpperCase().replace(/[^A-Z0-9]/g, '')}-${o2.name.toUpperCase().replace(/[^A-Z0-9]/g, '')}`
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

        form.size_chart = showSizeChart
            ? {
                  enabled: true,
                  headers: $state.snapshot(sizeChartHeaders),
                  rows: $state.snapshot(sizeChartRows),
              }
            : null;

        form.variations = enableVariants ? rawVariations : [];
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
        form.post(adminProductsStore.url());
    }

    function submitAndCreate() {
        form.photos = $state.snapshot(uploadedPhotos);
        const rawVariations = $state.snapshot(variations);
        const rawVariants = $state.snapshot(variants);

        form.tier_prices = form.tier_prices
            ? form.tier_prices.filter(
                  (tp) =>
                      tp.min_qty >= 2 &&
                      tp.price !== '' &&
                      tp.price !== null &&
                      Number(tp.price) > 0,
              )
            : [];

        const specObj = {};
        specifications.forEach((spec) => {
            if (spec.label.trim()) {
                specObj[spec.label.trim()] = spec.value.trim();
            }
        });
        form.specifications = specObj;

        form.size_chart = showSizeChart
            ? {
                  enabled: true,
                  headers: $state.snapshot(sizeChartHeaders),
                  rows: $state.snapshot(sizeChartRows),
              }
            : null;

        form.variations = enableVariants ? rawVariations : [];
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
        form.post(adminProductsStore.url(), {
            onSuccess: () => {
                window.location.href = adminProductsCreate.url();
            },
        });
    }
</script>

<svelte:head>
    <title>Buat Produk Baru — Admin</title>
</svelte:head>

<AdminLayout>
    <main class="w-full max-w-[1600px] mx-auto px-4 sm:px-6 py-6 space-y-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-semibold tracking-tight text-slate-900">Buat Produk Baru</h1>
                <p class="mt-0.5 text-sm text-slate-550">Tambahkan produk mebel baru ke dalam katalog toko Anda</p>
            </div>
            <Link
                href={adminProductsIndex.url()}
                class="h-9 rounded-lg border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-650 transition-colors hover:bg-slate-50 flex items-center justify-center self-start sm:self-auto cursor-pointer"
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
                class="bg-white rounded-xl border border-slate-200 p-5 sm:p-6 shadow-xs"
            >
                <h3
                    class="text-base font-semibold text-slate-900 border-b border-slate-150 pb-3 mb-4"
                >
                    Foto Produk <span class="text-rose-500">*</span>
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
                                    aria-label="Hapus Foto"
                                    type="button"
                                    onclick={() => removePhoto(i)}
                                    class="absolute top-2 right-2 w-6 h-6 rounded-full bg-slate-900/60 text-white hover:bg-rose-600 flex items-center justify-center opacity-0 group-hover:opacity-100 transition"
                                >
                                    <i class="ti ti-x text-xs"></i>
                                </button>
                                <div
                                    class="absolute bottom-2 left-2 right-2 flex justify-between gap-1 opacity-0 group-hover:opacity-100 transition"
                                >
                                    {#if i > 0}
                                        <button
                                            title="Geser Kiri"
                                            type="button"
                                            onclick={() => movePhoto(i, -1)}
                                            class="w-6 h-6 rounded bg-slate-900/60 text-white hover:bg-brand-blueRoyal flex items-center justify-center transition"
                                        >
                                            <i class="ti ti-arrow-left text-xs"
                                            ></i>
                                        </button>
                                    {:else}
                                        <div></div>
                                    {/if}
                                    {#if i < uploadedPhotos.length - 1}
                                        <button
                                            title="Geser Kanan"
                                            type="button"
                                            onclick={() => movePhoto(i, 1)}
                                            class="w-6 h-6 rounded bg-slate-900/60 text-white hover:bg-brand-blueRoyal flex items-center justify-center transition"
                                        >
                                            <i class="ti ti-arrow-right text-xs"
                                            ></i>
                                        </button>
                                    {:else}
                                        <div></div>
                                    {/if}
                                </div>
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
                    <div class="mt-3 flex gap-2">
                        <button
                            type="button"
                            onclick={() => {
                                if (!form.name.trim()) {
                                    showToast('Silakan isi nama produk terlebih dahulu untuk mencari gambar.', 'warning');
                                    return;
                                }
                                showImageSearchModal = true;
                            }}
                            class="h-9 px-4 rounded-xl border border-brand-blueRoyal/20 bg-brand-blueRoyal/5 text-brand-blueRoyal hover:bg-brand-blueRoyal/10 text-xs font-semibold flex items-center justify-center gap-1.5 transition-colors cursor-pointer"
                        >
                            <i class="ti ti-search text-sm"></i>
                            Cari Gambar Otomatis
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
                    class="bg-white rounded-xl border border-slate-200 p-5 sm:p-6 shadow-xs"
                >
                    <h3
                        class="text-base font-semibold text-slate-900 border-b border-slate-150 pb-3 mb-4"
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
                    <div
                        class="mb-6 p-4 bg-slate-50 border border-slate-200 rounded-2xl"
                    >
                        <Toggle
                            bind:checked={form.is_digital}
                            label="Produk Digital"
                            description="Aktifkan jika produk ini tidak memerlukan pengiriman fisik (voucher, lisensi, dll)"
                            icon="ti-device-laptop"
                        />
                    </div>

                    <!-- Membership Settings -->
                    {#if membershipEnabled}
                        <div class="mb-6 space-y-3">
                            <p class="text-xs font-bold text-slate-600 uppercase tracking-wider">Pengaturan Membership</p>

                            <!-- Exclusive product -->
                            <div class="p-4 bg-amber-50 border border-amber-200 rounded-2xl space-y-3">
                                <Toggle
                                    bind:checked={form.is_exclusive}
                                    label="Produk Eksklusif Member"
                                    description="Hanya bisa dilihat dan dibeli oleh member dengan level tertentu"
                                    icon="ti-lock"
                                />
                                {#if form.is_exclusive}
                                    <div class="space-y-1.5 pt-1">
                                        <label class="text-xs font-semibold text-slate-600">Minimum Level Order (urutan level membership)</label>
                                        <input
                                            type="number"
                                            bind:value={form.exclusive_min_level_order}
                                            min="0"
                                            placeholder="0 = semua level, 1 = Silver ke atas, dst"
                                            class="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm focus:border-slate-400 focus:outline-none transition-colors"
                                        />
                                        <p class="text-[10px] text-slate-400">0 = Member, 1 = Silver, 2 = Gold, 3 = Platinum, 4 = Diamond</p>
                                    </div>
                                {/if}
                            </div>

                            <!-- Early access -->
                            <div class="p-4 bg-violet-50 border border-violet-200 rounded-2xl space-y-3">
                                <Toggle
                                    bind:checked={form.is_early_access}
                                    label="Produk Early Access"
                                    description="Produk bisa diakses lebih awal oleh member tertentu sebelum dijual ke publik"
                                    icon="ti-eye"
                                />
                                {#if form.is_early_access}
                                    <div class="grid grid-cols-2 gap-3 pt-1">
                                        <div class="space-y-1.5">
                                            <label class="text-xs font-semibold text-slate-600">Early Access Sampai</label>
                                            <input
                                                type="datetime-local"
                                                bind:value={form.early_access_until}
                                                class="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm focus:border-slate-400 focus:outline-none transition-colors"
                                            />
                                        </div>
                                        <div class="space-y-1.5">
                                            <label class="text-xs font-semibold text-slate-600">Minimum Level Order</label>
                                            <input
                                                type="number"
                                                bind:value={form.early_access_min_level_order}
                                                min="0"
                                                placeholder="0 = semua member"
                                                class="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm focus:border-slate-400 focus:outline-none transition-colors"
                                            />
                                        </div>
                                    </div>
                                    <p class="text-[10px] text-slate-400">Setelah tanggal early access, produk otomatis tersedia untuk semua pelanggan</p>
                                {/if}
                            </div>
                        </div>
                    {/if}

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <span
                                    class="text-xs font-bold text-slate-600 block"
                                >
                                    Kategori Produk
                                </span>
                                <button
                                    type="button"
                                    onclick={() =>
                                        (showQuickAddCategoryModal = true)}
                                    class="text-xs text-brand-blueRoyal hover:text-brand-blueRoyal/85 font-black flex items-center gap-1 transition cursor-pointer"
                                >
                                    <i class="ti ti-plus"></i> Tambah Kategori
                                </button>
                            </div>
                            <SelectSearchMultiple
                                bind:value={form.category_ids}
                                options={categoryOptions}
                                label=""
                                placeholder="Pilih Kategori Produk"
                                error={form.errors.category_ids}
                            />
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <span
                                    class="text-xs font-bold text-slate-600 block"
                                >
                                    Merek (Brand)
                                </span>
                                <button
                                    type="button"
                                    onclick={() =>
                                        (showQuickAddBrandModal = true)}
                                    class="text-xs text-brand-blueRoyal hover:text-brand-blueRoyal/85 font-black flex items-center gap-1 transition cursor-pointer"
                                >
                                    <i class="ti ti-plus"></i> Tambah Merek
                                </button>
                            </div>
                            <SelectSearchMultiple
                                bind:value={form.brand_ids}
                                options={brandOptions}
                                label=""
                                placeholder="Pilih Merek (Brand)"
                                error={form.errors.brand_ids}
                            />
                        </div>
                    </div>
                    <div class="space-y-4">
                        <!-- AI Description Generator -->
                        {#if ai_enabled}
                            <div class="rounded-xl border border-violet-200 bg-gradient-to-r from-violet-50 to-indigo-50 p-4">
                                <div class="flex items-center justify-between gap-3 flex-wrap">
                                    <div class="flex items-center gap-2">
                                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-violet-600 text-white">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M12 2a2 2 0 0 1 2 2c0 .74-.4 1.39-1 1.73V7h1a7 7 0 0 1 7 7h1a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v1a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-1H2a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h1a7 7 0 0 1 7-7h1V5.73c-.6-.34-1-.99-1-1.73a2 2 0 0 1 2-2"/>
                                                <path d="M7.5 13.5h.01M16.5 13.5h.01"/>
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-sm font-semibold text-violet-900">Isi Deskripsi dengan AI</p>
                                            <p class="text-xs text-violet-600">Generate deskripsi otomatis berdasarkan data produk</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button
                                            type="button"
                                            onclick={() => showAiKeywords = !showAiKeywords}
                                            class="rounded-lg border border-violet-300 bg-white px-3 py-1.5 text-xs font-medium text-violet-700 hover:bg-violet-50 transition-colors"
                                        >
                                            {showAiKeywords ? 'Sembunyikan' : '+ Kata Kunci'}
                                        </button>
                                        <button
                                            type="button"
                                            onclick={generateAiDescription}
                                            disabled={isGeneratingAi}
                                            class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-violet-700 disabled:opacity-60 disabled:cursor-not-allowed transition-all"
                                        >
                                            {#if isGeneratingAi}
                                                <svg class="h-3.5 w-3.5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                                </svg>
                                                Generating...
                                            {:else}
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                                                </svg>
                                                Generate Deskripsi
                                            {/if}
                                        </button>
                                    </div>
                                </div>
                                {#if showAiKeywords}
                                    <div class="mt-3">
                                        <input
                                            type="text"
                                            bind:value={aiKeywords}
                                            placeholder="Tambahkan kata kunci atau fitur khusus (opsional)..."
                                            class="w-full rounded-lg border border-violet-300 bg-white px-3 py-2 text-sm text-slate-700 placeholder-slate-400 focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-500/20"
                                        />
                                    </div>
                                {/if}
                            </div>
                        {/if}
                        <RichEditor
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
                    class="bg-white rounded-xl border border-slate-200 p-5 sm:p-6 shadow-xs"
                >
                    <h3
                        class="text-base font-semibold text-slate-900 border-b border-slate-150 pb-3 mb-4"
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
                                                    >Rp {Number(
                                                        form.price,
                                                    ).toLocaleString(
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
                                                    >Rp {Number(
                                                        form.price,
                                                    ).toLocaleString(
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

                {#if !form.is_digital}
                    <!-- Card: Dimensi Master -->
                    <div
                        class="bg-white rounded-xl border border-slate-200 p-5 sm:p-6 shadow-xs"
                    >
                        <h3
                            class="text-base font-semibold text-slate-900 border-b border-slate-150 pb-3 mb-4"
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
                {/if}

                <!-- Card: Spesifikasi Produk -->
                <div
                    class="bg-white rounded-xl border border-slate-200 p-5 sm:p-6 shadow-xs"
                >
                    <div
                        class="flex items-center justify-between mb-4 border-b border-slate-150 pb-3"
                    >
                        <div>
                            <h3
                                class="text-base font-semibold text-slate-900"
                            >
                                Spesifikasi Produk
                            </h3>
                            <p
                                class="text-xs text-slate-400 font-bold uppercase tracking-wider mt-1"
                            >
                                Tambahkan detail spesifikasi produk (misal:
                                Bahan, Warna, Garansi)
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
                        <div
                            class="text-center py-10 border border-dashed border-slate-200 rounded-2xl bg-slate-50/30 flex flex-col items-center justify-center"
                        >
                            <div
                                class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mb-2"
                            >
                                <i class="ti ti-list text-lg"></i>
                            </div>
                            <p class="text-xs text-slate-500 font-medium">
                                Belum ada spesifikasi produk yang ditambahkan.
                            </p>
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
                                <div
                                    class="flex items-center gap-4 bg-slate-50/50 p-4 rounded-2xl border border-slate-100 relative group animate-in fade-in zoom-in-95 duration-150"
                                >
                                    <div
                                        class="flex-grow grid grid-cols-1 sm:grid-cols-2 gap-4"
                                    >
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
                <div
                    class="bg-white rounded-xl border border-slate-200 p-5 sm:p-6 shadow-xs"
                >
                    <div
                        class="flex items-center justify-between mb-4 border-b border-slate-150 pb-3"
                    >
                        <div>
                            <h3
                                class="text-base font-semibold text-slate-900"
                            >
                                Panduan Ukuran & Kalkulator Rekomendasi
                            </h3>
                            <p
                                class="text-xs text-slate-400 font-bold uppercase tracking-wider mt-1"
                            >
                                Aktifkan untuk menampilkan tabel panduan ukuran
                                pakaian dan kalkulator rekomendasi tinggi/berat
                                badan otomatis
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
                            <div
                                class="bg-slate-50/50 p-4 rounded-2xl border border-slate-100"
                            >
                                <div
                                    class="flex items-center justify-between mb-3"
                                >
                                    <span
                                        class="text-xs font-bold text-slate-600 uppercase tracking-wider"
                                    >
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
                                        <div
                                            class="flex items-center bg-white border border-slate-200 rounded-lg p-1 text-xs pl-2.5 shadow-sm"
                                        >
                                            {#if hIdx === 0}
                                                <span
                                                    class="font-bold text-slate-600 pr-2"
                                                    >{header}</span
                                                >
                                            {:else}
                                                <input
                                                    type="text"
                                                    bind:value={
                                                        sizeChartHeaders[hIdx]
                                                    }
                                                    class="w-28 focus:outline-none font-bold text-slate-700 bg-transparent"
                                                />
                                                <button
                                                    type="button"
                                                    onclick={() =>
                                                        removeSizeChartHeader(
                                                            hIdx,
                                                        )}
                                                    class="text-slate-400 hover:text-rose-500 pl-1.5 pr-0.5"
                                                    title="Hapus Kolom"
                                                >
                                                    <i class="ti ti-x text-xs"
                                                    ></i>
                                                </button>
                                            {/if}
                                        </div>
                                    {/each}
                                </div>
                            </div>

                            <!-- Baris / Rows Builder -->
                            <div
                                class="overflow-x-auto border border-slate-100 rounded-2xl"
                            >
                                <table
                                    class="w-full text-left text-xs border-collapse"
                                >
                                    <thead>
                                        <tr
                                            class="bg-slate-50 border-b border-slate-100"
                                        >
                                            <th
                                                class="p-3 font-bold text-slate-500 uppercase tracking-wider w-16"
                                                >Opsi</th
                                            >
                                            <th
                                                class="p-3 font-bold text-slate-500 uppercase tracking-wider w-24 text-center"
                                                >{sizeChartHeaders[0]}</th
                                            >
                                            {#each sizeChartHeaders.slice(1) as header}
                                                <th
                                                    class="p-3 font-bold text-slate-500 uppercase tracking-wider min-w-[80px] text-center"
                                                    >{header}</th
                                                >
                                            {/each}
                                            <th
                                                class="p-3 font-bold text-slate-500 uppercase tracking-wider w-40 text-center"
                                                >Tinggi (cm)</th
                                            >
                                            <th
                                                class="p-3 font-bold text-slate-500 uppercase tracking-wider w-40 text-center"
                                                >Berat (kg)</th
                                            >
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        {#each sizeChartRows as row, rIdx}
                                            <tr class="hover:bg-slate-50/30">
                                                <td class="p-2 text-center">
                                                    <button
                                                        type="button"
                                                        onclick={() =>
                                                            removeSizeChartRow(
                                                                rIdx,
                                                            )}
                                                        class="w-6 h-6 rounded-md hover:bg-rose-50 hover:text-rose-600 text-slate-400 flex items-center justify-center transition mx-auto cursor-pointer"
                                                        title="Hapus Baris"
                                                    >
                                                        <i class="ti ti-trash"
                                                        ></i>
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
                                                            bind:value={
                                                                row.values[hIdx]
                                                            }
                                                            class="w-full bg-white border border-slate-200 rounded-lg px-2.5 py-1.5 focus:border-brand-blueRoyal focus:ring-1 focus:ring-brand-blueRoyal/20 focus:outline-none text-slate-600 text-center font-medium"
                                                            placeholder="Cth: 50"
                                                        />
                                                    </td>
                                                {/each}
                                                <!-- Tinggi range -->
                                                <td class="p-2">
                                                    <div
                                                        class="flex items-center gap-1.5 justify-center"
                                                    >
                                                        <input
                                                            type="number"
                                                            bind:value={
                                                                row.min_height
                                                            }
                                                            class="w-16 bg-white border border-slate-200 rounded-lg px-1.5 py-1.5 text-center focus:outline-none"
                                                            placeholder="Min"
                                                        />
                                                        <span
                                                            class="text-slate-400"
                                                            >-</span
                                                        >
                                                        <input
                                                            type="number"
                                                            bind:value={
                                                                row.max_height
                                                            }
                                                            class="w-16 bg-white border border-slate-200 rounded-lg px-1.5 py-1.5 text-center focus:outline-none"
                                                            placeholder="Max"
                                                        />
                                                    </div>
                                                </td>
                                                <!-- Berat range -->
                                                <td class="p-2">
                                                    <div
                                                        class="flex items-center gap-1.5 justify-center"
                                                    >
                                                        <input
                                                            type="number"
                                                            bind:value={
                                                                row.min_weight
                                                            }
                                                            class="w-16 bg-white border border-slate-200 rounded-lg px-1.5 py-1.5 text-center focus:outline-none"
                                                            placeholder="Min"
                                                        />
                                                        <span
                                                            class="text-slate-400"
                                                            >-</span
                                                        >
                                                        <input
                                                            type="number"
                                                            bind:value={
                                                                row.max_weight
                                                            }
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
                <div
                    class="bg-white rounded-xl border border-slate-200 p-5 sm:p-6 shadow-xs"
                >
                    <div class="border-b border-slate-150 pb-3 mb-5">
                        <h3
                            class="text-base font-semibold text-slate-900 flex items-center gap-2"
                        >
                            <i class="ti ti-video text-brand-blueRoyal"></i> Media
                            Interaktif {#if enable3dModels}(Video & 3D AR){:else}(Video){/if}
                        </h3>
                        <p
                            class="text-xs text-slate-400 font-bold uppercase tracking-wider mt-1"
                        >
                            {#if enable3dModels}
                                Tambahkan video demonstrasi dan model 3D (format
                                GLB/USDZ) untuk visual Augmented Reality di mobile.
                            {:else}
                                Tambahkan video demonstrasi produk.
                            {/if}
                        </p>
                    </div>

                    <div class="space-y-6">
                        <!-- 1. Video Section -->
                        <div
                            class="bg-slate-50/50 p-4 rounded-2xl border border-slate-100"
                        >
                            <h4
                                class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-3 flex items-center gap-1.5"
                            >
                                <i class="ti ti-movie text-base text-slate-400"
                                ></i> Video Produk
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- Upload Video -->
                                <div
                                    class="bg-white border-2 border-dashed border-slate-200 rounded-xl p-4 flex flex-col items-center justify-center text-center hover:border-brand-blueRoyal/40 transition relative"
                                >
                                    {#if videoPreview}
                                        <div
                                            class="w-full relative rounded-lg overflow-hidden border border-slate-100 bg-black aspect-video max-h-40"
                                        >
                                            <!-- svelte-ignore a11y_media_has_caption -->
                                            <video
                                                src={videoPreview}
                                                class="w-full h-full object-contain"
                                                controls
                                            ></video>
                                            <button
                                                type="button"
                                                onclick={removeVideo}
                                                class="absolute top-1.5 right-1.5 w-7 h-7 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center shadow-md transition"
                                                title="Hapus Video"
                                            >
                                                <i class="ti ti-trash text-sm"
                                                ></i>
                                            </button>
                                        </div>
                                    {:else}
                                        <i
                                            class="ti ti-video-plus text-3xl text-slate-350 mb-1.5"
                                        ></i>
                                        <span
                                            class="text-xs font-bold text-slate-600 mb-0.5"
                                            >Upload File Video</span
                                        >
                                        <span
                                            class="text-[10px] text-slate-400 font-medium mb-3"
                                            >MP4, WEBM, atau MOV (Maks. 50MB)</span
                                        >
                                        <input
                                            type="file"
                                            accept="video/mp4,video/quicktime,video/webm"
                                            onchange={handleVideoFile}
                                            class="absolute inset-0 opacity-0 cursor-pointer w-full h-full"
                                        />
                                        <button
                                            type="button"
                                            class="px-3.5 py-1.5 bg-brand-blueRoyal/5 hover:bg-brand-blueRoyal/10 text-brand-blueRoyal text-[10px] font-black rounded-lg uppercase tracking-wider transition"
                                            >Pilih File</button
                                        >
                                    {/if}
                                </div>
                                <!-- Video URL -->
                                <div class="flex flex-col justify-center">
                                    <p
                                        class="block text-[10px] font-bold text-slate-400 mb-1.5 uppercase tracking-wider"
                                    >
                                        Atau Masukkan URL / Path Video
                                    </p>
                                    <Input
                                        id="video_url"
                                        placeholder="Cth: storage/products/videos/demo.mp4"
                                        bind:value={form.video_url}
                                    />
                                    <p
                                        class="text-[10px] text-slate-400 font-medium mt-2 leading-relaxed"
                                    >
                                        *Catatan: Jika Anda mengunggah file
                                        video sekaligus memasukkan URL, file
                                        unggahan akan diprioritaskan.
                                    </p>
                                </div>
                            </div>
                        </div>                        {#if enable3dModels}
                            <!-- 2. Model 3D Section -->
                            <div
                                class="bg-slate-50/50 p-4 rounded-2xl border border-slate-100"
                            >
                                <div class="flex items-center justify-between mb-3">
                                    <h4
                                        class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5"
                                    >
                                        <i
                                            class="ti ti-cube text-base text-slate-400"
                                        ></i> Model 3D & Augmented Reality
                                    </h4>
                                    <button
                                        type="button"
                                        onclick={openImageTo3dModal}
                                        class="px-2.5 py-1.5 bg-brand-blueRoyal/10 hover:bg-brand-blueRoyal/20 text-brand-blueRoyal text-[10px] font-black rounded-lg uppercase tracking-wider transition flex items-center gap-1.5 cursor-pointer"
                                    >
                                        <i class="ti ti-wand text-xs"></i> AI Gambar ke
                                        3D
                                    </button>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- GLB File -->
                                    <div class="space-y-4">
                                        <div
                                            class="bg-white border-2 border-dashed border-slate-200 rounded-xl p-4 flex flex-col items-center justify-center text-center hover:border-brand-blueRoyal/40 transition relative"
                                        >
                                            {#if model3dFileName}
                                                <div
                                                    class="w-full flex items-center gap-2 bg-green-50/30 p-2.5 rounded-lg border border-green-100"
                                                >
                                                    <div
                                                        class="w-8 h-8 rounded-lg bg-green-100 flex items-center justify-center text-green-600 shrink-0"
                                                    >
                                                        <i
                                                            class="ti ti-cube-send text-base"
                                                        ></i>
                                                    </div>
                                                    <div
                                                        class="min-w-0 flex-1 text-left"
                                                    >
                                                        <p
                                                            class="text-[10px] text-green-600 font-bold uppercase tracking-wider"
                                                        >
                                                            GLB Model Siap
                                                        </p>
                                                        <p
                                                            class="text-xs text-slate-700 font-semibold truncate leading-tight mt-0.5"
                                                        >
                                                            {model3dFileName}
                                                        </p>
                                                    </div>
                                                    <button
                                                        type="button"
                                                        onclick={removeModel3d}
                                                        class="p-1 hover:bg-slate-100 rounded-lg text-slate-400 hover:text-red-500 transition shrink-0"
                                                        title="Hapus Model"
                                                    >
                                                        <i
                                                            class="ti ti-trash text-sm"
                                                        ></i>
                                                    </button>
                                                </div>
                                            {:else}
                                                <i
                                                    class="ti ti-box-margin text-3xl text-slate-350 mb-1.5"
                                                ></i>
                                                <span
                                                    class="text-xs font-bold text-slate-600 mb-0.5"
                                                    >Model 3D (format GLB)</span
                                                >
                                                <span
                                                    class="text-[10px] text-slate-400 font-medium mb-3"
                                                    >Format GLB Standar Web (Maks.
                                                    50MB)</span
                                                >
                                                <input
                                                    type="file"
                                                    accept=".glb"
                                                    onchange={handleModel3dFile}
                                                    class="absolute inset-0 opacity-0 cursor-pointer w-full h-full"
                                                />
                                                <button
                                                    type="button"
                                                    class="px-3.5 py-1.5 bg-brand-blueRoyal/5 hover:bg-brand-blueRoyal/10 text-brand-blueRoyal text-[10px] font-black rounded-lg uppercase tracking-wider transition"
                                                    >Pilih File GLB</button
                                                >
                                            {/if}
                                        </div>
                                        <div>
                                            <p
                                                class="block text-[10px] font-bold text-slate-400 mb-1.5 uppercase tracking-wider"
                                            >
                                                Atau URL Model GLB
                                            </p>
                                            <Input
                                                id="model_3d_url"
                                                placeholder="Cth: storage/products/models/item.glb"
                                                bind:value={form.model_3d_url}
                                            />
                                        </div>
                                    </div>

                                    <!-- USDZ File (iOS AR) -->
                                    <div class="space-y-4">
                                        <div
                                            class="bg-white border-2 border-dashed border-slate-200 rounded-xl p-4 flex flex-col items-center justify-center text-center hover:border-brand-blueRoyal/40 transition relative"
                                        >
                                            {#if model3dUsdzFileName}
                                                <div
                                                    class="w-full flex items-center gap-2 bg-brand-blueRoyal/5 p-2.5 rounded-lg border border-brand-blueRoyal/10"
                                                >
                                                    <div
                                                        class="w-8 h-8 rounded-lg bg-brand-blueRoyal/10 flex items-center justify-center text-brand-blueRoyal shrink-0"
                                                    >
                                                        <i
                                                            class="ti ti-brand-apple text-base"
                                                        ></i>
                                                    </div>
                                                    <div
                                                        class="min-w-0 flex-1 text-left"
                                                    >
                                                        <p
                                                            class="text-[10px] text-brand-blueRoyal font-bold uppercase tracking-wider"
                                                        >
                                                            iOS USDZ Model Siap
                                                        </p>
                                                        <p
                                                            class="text-xs text-slate-700 font-semibold truncate leading-tight mt-0.5"
                                                        >
                                                            {model3dUsdzFileName}
                                                        </p>
                                                    </div>
                                                    <button
                                                        type="button"
                                                        onclick={removeModel3dUsdz}
                                                        class="p-1 hover:bg-slate-100 rounded-lg text-slate-400 hover:text-red-500 transition shrink-0"
                                                        title="Hapus Model iOS"
                                                    >
                                                        <i
                                                            class="ti ti-trash text-sm"
                                                        ></i>
                                                    </button>
                                                </div>
                                            {:else}
                                                <i
                                                    class="ti ti-brand-apple text-3xl text-slate-350 mb-1.5"
                                                ></i>
                                                <span
                                                    class="text-xs font-bold text-slate-600 mb-0.5"
                                                    >Model iOS (format USDZ)</span
                                                >
                                                <span
                                                    class="text-[10px] text-slate-400 font-medium mb-3"
                                                    >Untuk Augmented Reality di
                                                    Safari iOS (Maks. 50MB)</span
                                                >
                                                <input
                                                    type="file"
                                                    accept=".usdz"
                                                    onchange={handleModel3dUsdzFile}
                                                    class="absolute inset-0 opacity-0 cursor-pointer w-full h-full"
                                                />
                                                <button
                                                    type="button"
                                                    class="px-3.5 py-1.5 bg-brand-blueRoyal/5 hover:bg-brand-blueRoyal/10 text-brand-blueRoyal text-[10px] font-black rounded-lg uppercase tracking-wider transition"
                                                    >Pilih File USDZ</button
                                                >
                                            {/if}
                                        </div>
                                        <div>
                                            <p
                                                class="block text-[10px] font-bold text-slate-400 mb-1.5 uppercase tracking-wider"
                                            >
                                                Atau URL Model USDZ
                                            </p>
                                            <Input
                                                id="model_3d_usdz_url"
                                                placeholder="Cth: storage/products/models/item.usdz"
                                                bind:value={form.model_3d_usdz_url}
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        {/if}v>
                    </div>
                </div>

                <!-- Card: Variasi -->
                <div
                    class="bg-white rounded-xl border border-slate-200 p-5 sm:p-6 shadow-xs"
                >
                    <div
                        class="flex items-center justify-between mb-4 border-b border-slate-150 pb-3"
                    >
                        <h3
                            class="text-base font-semibold text-slate-900"
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
                                    class="bg-slate-50 border border-slate-200 rounded-xl p-5 sm:p-6 space-y-5"
                                >
                                    <div
                                        class="flex justify-between items-center border-b border-slate-200 pb-3"
                                    >
                                        <div
                                            class="flex items-center gap-2 font-bold text-sm text-slate-800"
                                        >
                                            <span
                                                >Tipe Variasi {vIndex + 1}</span
                                            >
                                            <div
                                                class="flex items-center gap-0.5 ml-2"
                                            >
                                                {#if vIndex > 0}
                                                    <button
                                                        type="button"
                                                        onclick={() =>
                                                            moveVariation(
                                                                vIndex,
                                                                -1,
                                                            )}
                                                        class="p-1 rounded hover:bg-slate-200 text-slate-500 hover:text-slate-700 transition"
                                                        title="Pindahkan ke Atas"
                                                    >
                                                        <i
                                                            class="ti ti-arrow-up text-xs"
                                                        ></i>
                                                    </button>
                                                {/if}
                                                {#if vIndex < variations.length - 1}
                                                    <button
                                                        type="button"
                                                        onclick={() =>
                                                            moveVariation(
                                                                vIndex,
                                                                1,
                                                            )}
                                                        class="p-1 rounded hover:bg-slate-200 text-slate-500 hover:text-slate-700 transition"
                                                        title="Pindahkan ke Bawah"
                                                    >
                                                        <i
                                                            class="ti ti-arrow-down text-xs"
                                                        ></i>
                                                    </button>
                                                {/if}
                                            </div>
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
                                                    <!-- svelte-ignore a11y_no_static_element_interactions -->
                                                    <!-- svelte-ignore a11y_click_events_have_key_events -->
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
                                                <div
                                                    class="flex items-center gap-0.5 border-r border-slate-100 pr-1 flex-shrink-0"
                                                >
                                                    {#if oIndex > 0}
                                                        <button
                                                            type="button"
                                                            onclick={() =>
                                                                moveOption(
                                                                    vIndex,
                                                                    oIndex,
                                                                    -1,
                                                                )}
                                                            class="p-1 rounded hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition"
                                                            title="Geser Kiri"
                                                        >
                                                            <i
                                                                class="ti ti-chevron-left text-xs"
                                                            ></i>
                                                        </button>
                                                    {/if}
                                                    {#if oIndex < v.options.length - 1}
                                                        <button
                                                            type="button"
                                                            onclick={() =>
                                                                moveOption(
                                                                    vIndex,
                                                                    oIndex,
                                                                    1,
                                                                )}
                                                            class="p-1 rounded hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition"
                                                            title="Geser Kanan"
                                                        >
                                                            <i
                                                                class="ti ti-chevron-right text-xs"
                                                            ></i>
                                                        </button>
                                                    {/if}
                                                </div>
                                                <button
                                                    aria-label="Hapus opsi"
                                                    type="button"
                                                    onclick={() =>
                                                        removeOption(
                                                            vIndex,
                                                            oIndex,
                                                        )}
                                                    class="px-2 text-slate-400 hover:text-rose-500 flex-shrink-0"
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
                                                aria-label="Hapus varian"
                                                type="button"
                                                onclick={() =>
                                                    addOption(vIndex)}
                                                class="px-4 bg-brand-blueLight hover:bg-brand-blueRoyal hover:text-white text-brand-blueRoyal font-bold transition flex items-center justify-center"
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
                                    class="mt-6 p-5 border border-slate-200 rounded-xl bg-slate-50/50 space-y-4"
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
                                                                        Harga Grosir
                                                                        ({variant.name})
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

                <div class="flex justify-end gap-3">
                    <button
                        type="button"
                        disabled={form.processing}
                        onclick={submitAndCreate}
                        class="px-6 py-3.5 bg-white border border-slate-200 text-slate-700 font-bold rounded-xl hover:bg-slate-50 transition flex items-center gap-2"
                    >
                        <i class="ti ti-circle-plus text-base"></i>
                        {form.processing ? 'Menyimpan...' : 'Simpan & Tambah'}
                    </button>
                    <button
                        type="submit"
                        disabled={form.processing}
                        class="px-8 py-3.5 bg-brand-blueRoyal text-white font-bold rounded-xl shadow-lg hover:bg-blue-800 transition flex items-center gap-2"
                    >
                        <i class="ti ti-device-floppy text-base"></i>
                        {form.processing ? 'Menyimpan...' : 'Simpan Produk'}
                    </button>
                </div>
            </form>
        </main>

    {#if isImageTo3dModalOpen}
        <div
            class="fixed inset-0 z-50 flex flex-col bg-white overflow-hidden animate-in fade-in duration-200 w-screen h-screen"
        >
            <div class="w-full h-full flex flex-col overflow-hidden">
                <!-- Header -->
                <div
                    class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-900 text-white shadow-md"
                >
                    <div class="flex items-center gap-2">
                        <div
                            class="w-8 h-8 rounded-lg bg-brand-blueRoyal/10 flex items-center justify-center text-brand-blueRoyal"
                        >
                            <i class="ti ti-wand text-lg"></i>
                        </div>
                        <div>
                            <h3
                                class="font-outfit font-black text-white text-base leading-tight"
                            >
                                AI Gambar-ke-3D Generator
                            </h3>
                            <p
                                class="text-[10px] text-slate-300 font-bold uppercase tracking-wider mt-0.5"
                            >
                                Konversi gambar produk Anda menjadi model 3D
                                interaktif
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        aria-label="Tutup"
                        onclick={closeImageTo3dModal}
                        class="w-8 h-8 rounded-full bg-slate-800 hover:bg-rose-600 hover:text-white flex items-center justify-center text-slate-300 transition cursor-pointer"
                    >
                        <i class="ti ti-x text-sm"></i>
                    </button>
                </div>

                <!-- Body -->
                <div class="flex-grow overflow-y-auto p-6 bg-slate-50/20">
                    {#if generatorStep === 1}
                        <!-- Step 1 Wizard Tabs -->
                        <div
                            class="mb-6 flex flex-wrap gap-2 border-b border-slate-100 pb-4"
                        >
                            <button
                                type="button"
                                onclick={() => {
                                    activeTab = 'image_to_3d';
                                }}
                                class="px-4 py-2.5 text-xs font-bold rounded-xl flex items-center gap-1.5 transition {activeTab ===
                                'image_to_3d'
                                    ? 'bg-brand-blueRoyal text-white'
                                    : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50'}"
                            >
                                <i class="ti ti-photo text-sm"></i> Gambar 2D ke 3D
                                (Solid)
                            </button>
                            <button
                                type="button"
                                onclick={() => {
                                    activeTab = 'glb_logo';
                                    modelType = 'custom';
                                }}
                                class="px-4 py-2.5 text-xs font-bold rounded-xl flex items-center gap-1.5 transition {activeTab ===
                                'glb_logo'
                                    ? 'bg-brand-blueRoyal text-white'
                                    : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50'}"
                            >
                                <i class="ti ti-box text-sm"></i> Upload Mockup 3D
                                (.glb)
                            </button>
                            <button
                                type="button"
                                onclick={() => {
                                    activeTab = 'scratch';
                                    modelType = 'shirt';
                                }}
                                class="px-4 py-2.5 text-xs font-bold rounded-xl flex items-center gap-1.5 transition {activeTab ===
                                'scratch'
                                    ? 'bg-brand-blueRoyal text-white'
                                    : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50'}"
                            >
                                <i class="ti ti-circle-plus text-sm"></i> Mulai dari
                                Kosong
                            </button>
                            <button
                                type="button"
                                onclick={() => {
                                    activeTab = 'ai_3d';
                                }}
                                class="px-4 py-2.5 text-xs font-bold rounded-xl flex items-center gap-1.5 transition {activeTab ===
                                'ai_3d'
                                    ? 'bg-brand-blueRoyal text-white'
                                    : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50'}"
                            >
                                <i class="ti ti-cpu text-sm"></i> AI Generate 3D
                            </button>
                        </div>

                        {#if activeTab === 'image_to_3d'}
                            <!-- Tab 1: Image Silhouette -->
                            <div class="space-y-6">
                                <span
                                    class="text-xs font-bold text-slate-700 uppercase tracking-wider block mb-1"
                                    >Upload Gambar Desain (PNG Transparan
                                    Disarankan)</span
                                >
                                <div
                                    class="grid grid-cols-1 md:grid-cols-2 gap-4"
                                >
                                    <div
                                        class="border-2 border-dashed border-slate-200 rounded-2xl p-4 bg-white flex flex-col items-center justify-center text-center hover:border-brand-blueRoyal/40 transition relative min-h-[140px]"
                                    >
                                        {#if selectedGenImage}
                                            <div
                                                class="flex items-center gap-3 bg-slate-50 p-2.5 rounded-lg border border-slate-100 w-full"
                                            >
                                                <img
                                                    src={selectedGenImage}
                                                    class="w-12 h-12 rounded object-cover border"
                                                    alt="preview"
                                                />
                                                <div
                                                    class="min-w-0 flex-1 text-left"
                                                >
                                                    <p
                                                        class="text-[11px] text-slate-700 font-bold truncate leading-tight"
                                                    >
                                                        {customGenFile
                                                            ? customGenFile.name
                                                            : 'Gambar Galeri'}
                                                    </p>
                                                    <p
                                                        class="text-[9px] text-slate-400 font-semibold mt-0.5"
                                                    >
                                                        Tampak Depan (Wajib)
                                                    </p>
                                                </div>
                                                <button
                                                    type="button"
                                                    aria-label="Hapus Gambar"
                                                    onclick={() => {
                                                        selectedGenImage = '';
                                                        customGenFile = null;
                                                    }}
                                                    class="p-1 hover:bg-slate-200 rounded-lg text-slate-400 hover:text-red-500 transition shrink-0"
                                                >
                                                    <i
                                                        class="ti ti-trash text-sm"
                                                    ></i>
                                                </button>
                                            </div>
                                        {:else}
                                            <i
                                                class="ti ti-photo-plus text-2xl text-slate-350 mb-1.5"
                                            ></i>
                                            <span
                                                class="text-xs font-bold text-slate-650 mb-0.5"
                                                >Pilih Tampak Depan</span
                                            >
                                            <span
                                                class="text-[9px] text-slate-400 font-medium mb-3"
                                                >Format JPG/PNG (Maks. 5MB)</span
                                            >
                                            <input
                                                type="file"
                                                accept="image/*"
                                                onchange={handleGenImageUpload}
                                                class="absolute inset-0 opacity-0 cursor-pointer w-full h-full"
                                            />
                                            <button
                                                type="button"
                                                class="px-3 py-1 bg-brand-blueRoyal/5 hover:bg-brand-blueRoyal/10 text-brand-blueRoyal text-[9px] font-black rounded-lg uppercase tracking-wider transition"
                                                >Pilih File</button
                                            >
                                        {/if}
                                    </div>
                                    <div
                                        class="border-2 border-dashed border-slate-200 rounded-2xl p-4 bg-white flex flex-col items-center justify-center text-center hover:border-brand-blueRoyal/40 transition relative min-h-[140px]"
                                    >
                                        {#if selectedGenImageBack}
                                            <div
                                                class="flex items-center gap-3 bg-slate-50 p-2.5 rounded-lg border border-slate-100 w-full"
                                            >
                                                <img
                                                    src={selectedGenImageBack}
                                                    class="w-12 h-12 rounded object-cover border"
                                                    alt="preview"
                                                />
                                                <div
                                                    class="min-w-0 flex-1 text-left"
                                                >
                                                    <p
                                                        class="text-[11px] text-slate-700 font-bold truncate leading-tight"
                                                    >
                                                        {customGenFileBack
                                                            ? customGenFileBack.name
                                                            : 'Gambar Belakang'}
                                                    </p>
                                                    <p
                                                        class="text-[9px] text-slate-400 font-semibold mt-0.5"
                                                    >
                                                        Tampak Belakang
                                                        (Opsional)
                                                    </p>
                                                </div>
                                                <button
                                                    type="button"
                                                    aria-label="Hapus Gambar"
                                                    onclick={() => {
                                                        selectedGenImageBack =
                                                            '';
                                                        customGenFileBack =
                                                            null;
                                                    }}
                                                    class="p-1 hover:bg-slate-200 rounded-lg text-slate-400 hover:text-red-500 transition shrink-0"
                                                >
                                                    <i
                                                        class="ti ti-trash text-sm"
                                                    ></i>
                                                </button>
                                            </div>
                                        {:else}
                                            <i
                                                class="ti ti-photo-plus text-2xl text-slate-350 mb-1.5"
                                            ></i>
                                            <span
                                                class="text-xs font-bold text-slate-650 mb-0.5"
                                                >Pilih Tampak Belakang</span
                                            >
                                            <span
                                                class="text-[9px] text-slate-400 font-medium mb-3"
                                                >Kosongkan untuk warna solid</span
                                            >
                                            <input
                                                type="file"
                                                accept="image/*"
                                                onchange={handleGenImageUploadBack}
                                                class="absolute inset-0 opacity-0 cursor-pointer w-full h-full"
                                            />
                                            <button
                                                type="button"
                                                class="px-3 py-1 bg-brand-blueRoyal/5 hover:bg-brand-blueRoyal/10 text-brand-blueRoyal text-[9px] font-black rounded-lg uppercase tracking-wider transition"
                                                >Pilih File</button
                                            >
                                        {/if}
                                    </div>
                                </div>
                            </div>
                        {:else if activeTab === 'glb_logo'}
                            <!-- Tab 2: GLB Mockup + Logo -->
                            <div class="space-y-6">
                                <span
                                    class="text-xs font-bold text-slate-700 uppercase tracking-wider block mb-1"
                                    >Unggah Model Mockup 3D (.glb) & Desain Logo
                                    Anda</span
                                >
                                <div
                                    class="grid grid-cols-1 md:grid-cols-2 gap-4"
                                >
                                    <div
                                        class="border-2 border-dashed border-slate-200 rounded-2xl p-4 bg-white flex flex-col items-center justify-center text-center hover:border-brand-blueRoyal/40 transition relative min-h-[140px]"
                                    >
                                        {#if customMockupFile}
                                            <div
                                                class="flex items-center gap-3 bg-slate-50 p-2.5 rounded-lg border border-slate-100 w-full"
                                            >
                                                <div
                                                    class="w-12 h-12 rounded bg-brand-blueRoyal/10 flex items-center justify-center text-brand-blueRoyal text-lg border"
                                                >
                                                    <i class="ti ti-box"></i>
                                                </div>
                                                <div
                                                    class="min-w-0 flex-1 text-left"
                                                >
                                                    <p
                                                        class="text-[11px] text-slate-700 font-bold truncate leading-tight"
                                                    >
                                                        {customMockupFile.name}
                                                    </p>
                                                    <p
                                                        class="text-[9px] text-slate-400 font-semibold mt-0.5"
                                                    >
                                                        Mockup 3D (.GLB)
                                                    </p>
                                                </div>
                                                <button
                                                    type="button"
                                                    aria-label="Hapus Mockup"
                                                    onclick={() => {
                                                        customMockupFile = null;
                                                        customMockupUrl = '';
                                                    }}
                                                    class="p-1 hover:bg-slate-200 rounded-lg text-slate-400 hover:text-red-500 transition shrink-0"
                                                >
                                                    <i
                                                        class="ti ti-trash text-sm"
                                                    ></i>
                                                </button>
                                            </div>
                                        {:else}
                                            <i
                                                class="ti ti-box-margin text-2xl text-slate-350 mb-1.5"
                                            ></i>
                                            <span
                                                class="text-xs font-bold text-slate-650 mb-0.5"
                                                >Upload Mockup 3D (.glb)</span
                                            >
                                            <span
                                                class="text-[9px] text-slate-400 font-medium mb-3"
                                                >Model 3D Gelas, Kaos, Topi dll</span
                                            >
                                            <input
                                                type="file"
                                                accept=".glb,.gltf"
                                                onchange={handleCustomMockupUpload}
                                                class="absolute inset-0 opacity-0 cursor-pointer w-full h-full"
                                            />
                                            <button
                                                type="button"
                                                class="px-3 py-1 bg-brand-blueRoyal/5 hover:bg-brand-blueRoyal/10 text-brand-blueRoyal text-[9px] font-black rounded-lg uppercase tracking-wider transition"
                                                >Pilih File</button
                                            >
                                        {/if}
                                    </div>
                                    <div
                                        class="border-2 border-dashed border-slate-200 rounded-2xl p-4 bg-white flex flex-col items-center justify-center text-center hover:border-brand-blueRoyal/40 transition relative min-h-[140px]"
                                    >
                                        {#if selectedGenImage}
                                            <div
                                                class="flex items-center gap-3 bg-slate-50 p-2.5 rounded-lg border border-slate-100 w-full"
                                            >
                                                <img
                                                    src={selectedGenImage}
                                                    class="w-12 h-12 rounded object-cover border"
                                                    alt="preview"
                                                />
                                                <div
                                                    class="min-w-0 flex-1 text-left"
                                                >
                                                    <p
                                                        class="text-[11px] text-slate-700 font-bold truncate leading-tight"
                                                    >
                                                        {customGenFile
                                                            ? customGenFile.name
                                                            : 'Gambar Logo'}
                                                    </p>
                                                    <p
                                                        class="text-[9px] text-slate-400 font-semibold mt-0.5"
                                                    >
                                                        Gambar Logo / Sablon
                                                    </p>
                                                </div>
                                                <button
                                                    type="button"
                                                    aria-label="Hapus Logo"
                                                    onclick={() => {
                                                        selectedGenImage = '';
                                                        customGenFile = null;
                                                    }}
                                                    class="p-1 hover:bg-slate-200 rounded-lg text-slate-400 hover:text-red-500 transition shrink-0"
                                                >
                                                    <i
                                                        class="ti ti-trash text-sm"
                                                    ></i>
                                                </button>
                                            </div>
                                        {:else}
                                            <i
                                                class="ti ti-photo-plus text-2xl text-slate-350 mb-1.5"
                                            ></i>
                                            <span
                                                class="text-xs font-bold text-slate-650 mb-0.5"
                                                >Upload Logo / Desain</span
                                            >
                                            <span
                                                class="text-[9px] text-slate-400 font-medium mb-3"
                                                >Format JPG/PNG (Maks. 5MB)</span
                                            >
                                            <input
                                                type="file"
                                                accept="image/*"
                                                onchange={handleGenImageUpload}
                                                class="absolute inset-0 opacity-0 cursor-pointer w-full h-full"
                                            />
                                            <button
                                                type="button"
                                                class="px-3 py-1 bg-brand-blueRoyal/5 hover:bg-brand-blueRoyal/10 text-brand-blueRoyal text-[9px] font-black rounded-lg uppercase tracking-wider transition"
                                                >Pilih File</button
                                            >
                                        {/if}
                                    </div>
                                </div>
                            </div>
                        {:else if activeTab === 'scratch'}
                            <!-- Tab 3: Blank Canvas -->
                            <div class="space-y-4">
                                <span
                                    class="text-xs font-bold text-slate-700 uppercase tracking-wider block mb-1"
                                    >Pilih Preset Objek Awal</span
                                >
                                <div
                                    class="grid grid-cols-2 sm:grid-cols-3 gap-3"
                                >
                                    {#each presets as pr}
                                        <button
                                            type="button"
                                            onclick={() => {
                                                modelType = pr.id;
                                                mockupScale = pr.scale;
                                                mockupY = pr.y;
                                            }}
                                            class="p-4 rounded-2xl border-2 transition text-left flex flex-col justify-between aspect-[1.3] {modelType ===
                                            pr.id
                                                ? 'border-brand-blueRoyal bg-brand-blueRoyal/5 text-brand-blueRoyal shadow-sm'
                                                : 'border-slate-200 hover:border-slate-350 text-slate-650 bg-white hover:bg-slate-50'} shadow-sm"
                                        >
                                            <i
                                                class="ti {pr.id === 'shirt'
                                                    ? 'ti-shirt'
                                                    : pr.id === 'hoodie'
                                                      ? 'ti-jacket'
                                                      : pr.id === 'mug'
                                                        ? 'ti-cup'
                                                        : pr.id === 'cap'
                                                          ? 'ti-crown'
                                                          : pr.id === 'totebag'
                                                            ? 'ti-briefcase'
                                                            : pr.id ===
                                                                'tumbler'
                                                              ? 'ti-bottle'
                                                              : pr.id ===
                                                                  'botol'
                                                                ? 'ti-bottle-nfc'
                                                                : pr.id ===
                                                                    'piring'
                                                                  ? 'ti-disc'
                                                                  : pr.id ===
                                                                      'plakat'
                                                                    ? 'ti-award'
                                                                    : 'ti-box'} text-2xl mb-2"
                                            ></i>
                                            <span
                                                class="text-xs font-black font-outfit truncate w-full"
                                                >{pr.name}</span
                                            >
                                        </button>
                                    {/each}
                                </div>
                            </div>
                        {:else if activeTab === 'ai_3d'}
                            <!-- Tab 4: AI Generate 3D -->
                            <div class="space-y-6">
                                <span
                                    class="text-xs font-bold text-slate-700 uppercase tracking-wider block mb-1"
                                    >Generate Model 3D dari Foto Tunggal
                                    (Meshy/Tripo AI)</span
                                >
                                <div
                                    class="border-2 border-dashed border-slate-200 rounded-2xl p-6 bg-white flex flex-col items-center justify-center text-center hover:border-brand-blueRoyal/40 transition relative min-h-[160px]"
                                >
                                    {#if selectedGenImage}
                                        <div
                                            class="flex items-center gap-3 bg-slate-50 p-2.5 rounded-lg border border-slate-100 w-full max-w-md"
                                        >
                                            <img
                                                src={selectedGenImage}
                                                class="w-12 h-12 rounded object-cover border"
                                                alt="preview"
                                            />
                                            <div
                                                class="min-w-0 flex-1 text-left"
                                            >
                                                <p
                                                    class="text-[11px] text-slate-700 font-bold truncate leading-tight"
                                                >
                                                    {customGenFile
                                                        ? customGenFile.name
                                                        : 'Gambar Input'}
                                                </p>
                                                <p
                                                    class="text-[9px] text-slate-400 font-semibold mt-0.5"
                                                >
                                                    Input Foto AI
                                                </p>
                                            </div>
                                            <button
                                                type="button"
                                                aria-label="Hapus Foto AI"
                                                onclick={() => {
                                                    selectedGenImage = '';
                                                    customGenFile = null;
                                                }}
                                                class="p-1 hover:bg-slate-200 rounded-lg text-slate-400 hover:text-red-500 transition shrink-0"
                                            >
                                                <i class="ti ti-trash text-sm"
                                                ></i>
                                            </button>
                                        </div>
                                    {:else}
                                        <i
                                            class="ti ti-cpu text-2xl text-slate-350 mb-1.5"
                                        ></i>
                                        <span
                                            class="text-xs font-bold text-slate-650 mb-0.5"
                                            >Upload Foto Produk</span
                                        >
                                        <span
                                            class="text-[9px] text-slate-400 font-medium mb-3"
                                            >AI akan merekonstruksi model 3D
                                            utuh</span
                                        >
                                        <input
                                            type="file"
                                            accept="image/*"
                                            onchange={handleGenImageUpload}
                                            class="absolute inset-0 opacity-0 cursor-pointer w-full h-full"
                                        />
                                        <button
                                            type="button"
                                            class="px-3 py-1 bg-brand-blueRoyal/5 hover:bg-brand-blueRoyal/10 text-brand-blueRoyal text-[9px] font-black rounded-lg uppercase tracking-wider transition"
                                            >Pilih Foto</button
                                        >
                                    {/if}
                                </div>
                            </div>
                        {/if}
                    {:else}
                        <!-- Step 2: Loading State and Step 3: Editor -->
                        <div
                            class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-stretch h-[calc(100vh-170px)] overflow-hidden"
                        >
                            <!-- Left Panel: 2D Interactive Canvas Design Editor -->
                            <div
                                class="lg:col-span-4 bg-slate-100 rounded-2xl overflow-hidden border border-slate-200 flex flex-col h-full relative"
                            >
                                {#if generatorStep === 2}
                                    <div
                                        class="flex flex-col items-center justify-center p-6 text-center h-full max-w-sm mx-auto"
                                    >
                                        <div
                                            class="relative w-16 h-16 mb-3 flex items-center justify-center"
                                        >
                                            <div
                                                class="absolute inset-0 rounded-full border-4 border-slate-200"
                                            ></div>
                                            <div
                                                class="absolute inset-0 rounded-full border-4 border-brand-blueRoyal border-t-transparent animate-spin"
                                            ></div>
                                            <div
                                                class="text-xs font-black text-slate-700"
                                            >
                                                {generationProgress}%
                                            </div>
                                        </div>
                                        <h4
                                            class="text-sm font-bold text-slate-700"
                                        >
                                            {progressMessage}
                                        </h4>
                                    </div>
                                {:else if generatorStep === 3}
                                    <!-- 2D Canvas Header -->
                                    <div
                                        class="px-3 py-2 border-b border-slate-200 bg-white flex items-center justify-between shrink-0"
                                    >
                                        <div class="flex items-center gap-2">
                                            <i
                                                class="ti ti-vector-bezier-2 text-brand-blueRoyal text-sm"
                                            ></i>
                                            <span
                                                class="text-[11px] font-black text-slate-700 uppercase tracking-wider"
                                                >Kanvas Desain 2D</span
                                            >
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <button
                                                type="button"
                                                onclick={() => {
                                                    canvas2dZoom = Math.max(
                                                        0.2,
                                                        canvas2dZoom - 0.2,
                                                    );
                                                }}
                                                class="w-6 h-6 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-600 text-xs transition"
                                                title="Zoom Out"
                                                ><i
                                                    class="ti ti-minus text-[10px]"
                                                ></i></button
                                            >
                                            <span
                                                class="text-[10px] font-bold text-slate-500 w-10 text-center"
                                                >{Math.round(
                                                    canvas2dZoom * 100,
                                                )}%</span
                                            >
                                            <button
                                                type="button"
                                                onclick={() => {
                                                    canvas2dZoom = Math.min(
                                                        5.0,
                                                        canvas2dZoom + 0.2,
                                                    );
                                                }}
                                                class="w-6 h-6 rounded-lg bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-600 text-xs transition"
                                                title="Zoom In"
                                                ><i
                                                    class="ti ti-plus text-[10px]"
                                                ></i></button
                                            >
                                            <button
                                                type="button"
                                                onclick={() => {
                                                    canvas2dZoom = 1.0;
                                                    canvas2dPanX = 0;
                                                    canvas2dPanY = 0;
                                                }}
                                                class="px-2 h-6 rounded-lg bg-slate-100 hover:bg-slate-200 text-[9px] font-black text-slate-500 transition ml-1"
                                                >Reset</button
                                            >
                                        </div>
                                    </div>
                                    <!-- 2D Canvas Container -->
                                    <div
                                        class="flex-1 overflow-hidden relative"
                                    >
                                        <canvas
                                            bind:this={canvas2dEl}
                                            width="600"
                                            height="600"
                                            class="w-full h-full block {isDraggingLayer
                                                ? 'cursor-grabbing'
                                                : isPanning2d
                                                  ? 'cursor-all-scroll'
                                                  : isResizingLayer
                                                    ? 'cursor-se-resize'
                                                    : 'cursor-default'}"
                                            onmousedown={onCanvas2dMouseDown}
                                            onmousemove={onCanvas2dMouseMove}
                                            onmouseup={onCanvas2dMouseUp}
                                            onmouseleave={onCanvas2dMouseUp}
                                            onwheel={onCanvas2dWheel}
                                            oncontextmenu={onCanvas2dContextMenu}
                                        ></canvas>
                                        <!-- Canvas hint overlay -->
                                        <div
                                            class="absolute bottom-2 left-2 bg-slate-900/60 text-white text-[9px] font-black rounded px-2 py-1 uppercase tracking-wider flex items-center gap-1 backdrop-blur-sm pointer-events-none"
                                        >
                                            <i class="ti ti-hand-click text-xs"
                                            ></i> Klik & Geser Layer · Scroll Zoom
                                            · Klik Kanan Pan
                                        </div>
                                    </div>
                                {/if}
                            </div>

                            <!-- Center Panel: 3D Viewport -->
                            <div
                                class="lg:col-span-5 bg-slate-100 rounded-2xl overflow-hidden border border-slate-200 flex flex-col justify-between relative h-full"
                            >
                                {#if generatorStep === 2}
                                    <!-- Progress State -->
                                    <div
                                        class="flex flex-col items-center justify-center p-6 text-center h-full max-w-sm mx-auto"
                                    >
                                        <h4
                                            class="text-sm font-bold text-slate-700"
                                        >
                                            {progressMessage}
                                        </h4>
                                        <p
                                            class="text-[10px] text-slate-400 font-medium mt-1"
                                        >
                                            Menggunakan WebGL untuk merender
                                            model 3D interaktif.
                                        </p>
                                    </div>
                                {:else if generatorStep === 3}
                                    <!-- Three.js Viewport -->
                                    <div
                                        bind:this={canvasContainer}
                                        class="w-full h-full min-h-[400px] lg:h-full cursor-grab active:cursor-grabbing"
                                    ></div>

                                    <!-- Viewpoint selector buttons -->
                                    <div
                                        class="absolute top-3 left-3 flex gap-1 bg-white/90 p-1.5 rounded-xl shadow-md border border-slate-200/60 backdrop-blur-sm"
                                    >
                                        <button
                                            type="button"
                                            onclick={() =>
                                                setCameraView('front')}
                                            class="px-2 py-1 text-[9px] font-black uppercase rounded-lg transition-all {activeView ===
                                            'front'
                                                ? 'bg-brand-blueRoyal text-white'
                                                : 'hover:bg-slate-100 text-slate-600'}"
                                            title="Tampak Depan">Depan</button
                                        >
                                        <button
                                            type="button"
                                            onclick={() =>
                                                setCameraView('back')}
                                            class="px-2 py-1 text-[9px] font-black uppercase rounded-lg transition-all {activeView ===
                                            'back'
                                                ? 'bg-brand-blueRoyal text-white'
                                                : 'hover:bg-slate-100 text-slate-600'}"
                                            title="Tampak Belakang"
                                            >Belakang</button
                                        >
                                        <button
                                            type="button"
                                            onclick={() =>
                                                setCameraView('left')}
                                            class="px-2 py-1 text-[9px] font-black uppercase rounded-lg transition-all {activeView ===
                                            'left'
                                                ? 'bg-brand-blueRoyal text-white'
                                                : 'hover:bg-slate-100 text-slate-600'}"
                                            title="Samping">Samping</button
                                        >
                                        <button
                                            type="button"
                                            onclick={() => setCameraView('top')}
                                            class="px-2 py-1 text-[9px] font-black uppercase rounded-lg transition-all {activeView ===
                                            'top'
                                                ? 'bg-brand-blueRoyal text-white'
                                                : 'hover:bg-slate-100 text-slate-600'}"
                                            title="Atas">Atas</button
                                        >
                                    </div>

                                    <!-- Rotate toggle -->
                                    <button
                                        type="button"
                                        onclick={() =>
                                            (autoRotate = !autoRotate)}
                                        class="absolute top-3 right-3 w-8 h-8 rounded-xl bg-white/85 border border-slate-200/55 flex items-center justify-center text-slate-600 shadow backdrop-blur-sm hover:bg-slate-50 transition"
                                        title="Putar Otomatis"
                                    >
                                        <i
                                            class="ti {autoRotate
                                                ? 'ti-rotate-dot text-brand-blueRoyal'
                                                : 'ti-rotate-2d'} text-sm"
                                        ></i>
                                    </button>

                                    <!-- Gesture/Interaction hints -->
                                    <div
                                        class="absolute bottom-3 left-3 bg-slate-900/60 text-white text-[9px] font-black rounded px-2 py-1 uppercase tracking-wider flex items-center gap-1 backdrop-blur-sm pointer-events-none"
                                    >
                                        <i
                                            class="ti ti-rotate-2d text-xs animate-spin-slow"
                                        ></i> Geser & Scroll untuk melihat 3D
                                    </div>
                                {/if}
                            </div>

                            <!-- Right Side: Editor Panel -->
                            <div
                                class="lg:col-span-3 flex flex-col justify-between h-full overflow-y-auto pr-1 pb-4"
                            >
                                {#if generatorStep === 2}
                                    <div
                                        class="flex items-center justify-center h-full border border-dashed border-slate-200 rounded-2xl p-6 bg-white text-center text-xs text-slate-400 font-medium"
                                    >
                                        Menunggu pembuatan model 3D selesai...
                                    </div>
                                {:else if generatorStep === 3}
                                    <!-- Dynamic Tabs inside steps -->
                                    <div
                                        class="bg-white border border-slate-200 rounded-2xl p-4 space-y-4 flex-grow"
                                    >
                                        <!-- Model Type & presets selector -->
                                        <div class="space-y-1">
                                            <p
                                                class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block"
                                            >
                                                Tipe Mockup Objek
                                            </p>
                                            <select
                                                bind:value={modelType}
                                                onchange={onModelTypeChange}
                                                class="w-full text-xs border border-slate-200 rounded-xl p-2 bg-white font-bold text-slate-700 focus:outline-none focus:border-brand-blueRoyal cursor-pointer"
                                            >
                                                <option value="plane"
                                                    >Siluet Gambar Asli (3D Card
                                                    Solid)</option
                                                >
                                                <option value="shirt"
                                                    >Kaos Polos (T-Shirt)</option
                                                >
                                                <option value="custom"
                                                    >Model 3D Kustom (.glb)</option
                                                >
                                                {#each presets as pr}
                                                    {#if pr.id !== 'shirt'}
                                                        <option value={pr.id}
                                                            >{pr.name}</option
                                                        >
                                                    {/if}
                                                {/each}
                                            </select>
                                        </div>

                                        <!-- Custom mockup upload file input -->
                                        {#if modelType === 'custom'}
                                            <div
                                                class="space-y-1 bg-slate-50 p-2.5 rounded-xl border border-slate-200"
                                            >
                                                <span
                                                    class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block"
                                                    >Ganti Mockup Kustom (.glb)</span
                                                >
                                                <input
                                                    type="file"
                                                    accept=".glb,.gltf"
                                                    onchange={handleCustomMockupUpload}
                                                    class="w-full text-xs"
                                                />
                                            </div>
                                        {/if}

                                        <!-- Layer Creator Section -->
                                        <div class="space-y-2 border-t pt-3">
                                            <span
                                                class="text-[10px] font-black text-slate-700 uppercase tracking-wider flex items-center gap-1"
                                                ><i
                                                    class="ti ti-layers-intersect text-brand-blueRoyal text-sm"
                                                ></i> Tambah Elemen Desain</span
                                            >

                                            <div class="flex flex-wrap gap-1.5">
                                                <!-- Text adder -->
                                                <div class="flex gap-1 w-full">
                                                    <input
                                                        type="text"
                                                        bind:value={textInput}
                                                        placeholder="Ketik teks..."
                                                        class="flex-grow border rounded-lg px-2.5 py-1 text-xs focus:outline-none focus:border-brand-blueRoyal"
                                                    />
                                                    <button
                                                        type="button"
                                                        onclick={() =>
                                                            addLayer('text')}
                                                        class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-lg transition border"
                                                        >Teks</button
                                                    >
                                                </div>

                                                <!-- Image layer upload -->
                                                <label
                                                    class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-lg transition border flex items-center gap-1 cursor-pointer"
                                                >
                                                    <i
                                                        class="ti ti-photo text-xs"
                                                    ></i>
                                                    Logo / Gambar
                                                    <input
                                                        type="file"
                                                        accept="image/*"
                                                        onchange={handleLayerImageUpload}
                                                        class="hidden"
                                                    />
                                                </label>

                                                <!-- QR code layer -->
                                                <button
                                                    type="button"
                                                    onclick={() =>
                                                        addLayer('qr')}
                                                    class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-lg transition border flex items-center gap-1"
                                                >
                                                    <i
                                                        class="ti ti-qrcode text-xs"
                                                    ></i> QR Code
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Active Layers List -->
                                        {#if designLayers.length > 0}
                                            <div
                                                class="space-y-1.5 bg-slate-50/50 p-2.5 rounded-xl border border-slate-150"
                                            >
                                                <span
                                                    class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block"
                                                    >Daftar Layer ({designLayers.length})</span
                                                >
                                                <div
                                                    class="space-y-1 max-h-[140px] overflow-y-auto"
                                                >
                                                    {#each [...designLayers].reverse() as layer, idx}
                                                        <div
                                                            class="flex items-center justify-between gap-2 p-1.5 rounded-lg border bg-white {selectedLayerId ===
                                                            layer.id
                                                                ? 'border-brand-blueRoyal ring-1 ring-brand-blueRoyal/10'
                                                                : 'border-slate-100'}"
                                                        >
                                                            <button
                                                                type="button"
                                                                onclick={() =>
                                                                    (selectedLayerId =
                                                                        layer.id)}
                                                                class="flex-grow text-left font-semibold text-xs truncate text-slate-700"
                                                            >
                                                                {#if layer.type === 'text'}
                                                                    <i
                                                                        class="ti ti-typography text-brand-blueRoyal mr-1"
                                                                    ></i>
                                                                    {layer.text}
                                                                {:else if layer.type === 'qr'}
                                                                    <i
                                                                        class="ti ti-qrcode text-emerald-600 mr-1"
                                                                    ></i> QR Code
                                                                {:else}
                                                                    <i
                                                                        class="ti ti-photo text-rose-500 mr-1"
                                                                    ></i>
                                                                    {layer.name ||
                                                                        'Gambar'}
                                                                {/if}
                                                            </button>
                                                            <div
                                                                class="flex items-center gap-1"
                                                            >
                                                                <button
                                                                    type="button"
                                                                    onclick={() => {
                                                                        layer.hide =
                                                                            !layer.hide;
                                                                        designLayers =
                                                                            [
                                                                                ...designLayers,
                                                                            ];
                                                                        redrawCompositeCanvas();
                                                                    }}
                                                                    class="p-1 hover:bg-slate-100 rounded text-slate-400 {layer.hide
                                                                        ? 'text-rose-500'
                                                                        : 'text-slate-400'}"
                                                                    title="Sembunyikan"
                                                                    ><i
                                                                        class="ti {layer.hide
                                                                            ? 'ti-eye-off'
                                                                            : 'ti-eye'} text-xs"

                                                                    ></i></button
                                                                >
                                                                <button
                                                                    type="button"
                                                                    onclick={() =>
                                                                        duplicateLayer(
                                                                            layer,
                                                                        )}
                                                                    class="p-1 hover:bg-slate-100 rounded text-slate-400"
                                                                    title="Duplikat"
                                                                    ><i
                                                                        class="ti ti-copy text-xs"

                                                                    ></i></button
                                                                >
                                                                <button
                                                                    type="button"
                                                                    onclick={() =>
                                                                        deleteLayer(
                                                                            layer.id,
                                                                        )}
                                                                    class="p-1 hover:bg-slate-100 rounded text-rose-500"
                                                                    title="Hapus"
                                                                    ><i
                                                                        class="ti ti-trash text-xs"

                                                                    ></i></button
                                                                >
                                                            </div>
                                                        </div>
                                                    {/each}
                                                </div>
                                            </div>

                                            <!-- Selected Layer Transform Sliders -->
                                            {#if selectedLayerId && designLayers.find((l) => l.id === selectedLayerId)}
                                                {@const selLayer =
                                                    designLayers.find(
                                                        (l) =>
                                                            l.id ===
                                                            selectedLayerId,
                                                    )}
                                                <div
                                                    class="bg-brand-blueRoyal/5 border border-brand-blueRoyal/10 rounded-xl p-3 space-y-2"
                                                >
                                                    <span
                                                        class="text-[9px] font-bold text-brand-blueRoyal uppercase tracking-wider block border-b pb-1"
                                                        >Edit Layer Terpilih</span
                                                    >

                                                    <!-- Text specific properties -->
                                                    {#if selLayer.type === 'text'}
                                                        <div
                                                            class="space-y-1 pb-1"
                                                        >
                                                            <input
                                                                type="text"
                                                                bind:value={
                                                                    selLayer.text
                                                                }
                                                                oninput={redrawCompositeCanvas}
                                                                class="w-full text-xs border rounded-lg px-2 py-1 focus:outline-none"
                                                            />
                                                            <div
                                                                class="flex gap-2 items-center"
                                                            >
                                                                <input
                                                                    type="color"
                                                                    bind:value={
                                                                        selLayer.color
                                                                    }
                                                                    oninput={redrawCompositeCanvas}
                                                                    class="w-6 h-6 rounded border cursor-pointer p-0"
                                                                />
                                                                <select
                                                                    bind:value={
                                                                        selLayer.fontFamily
                                                                    }
                                                                    onchange={redrawCompositeCanvas}
                                                                    class="text-xs border rounded p-1 bg-white flex-grow"
                                                                >
                                                                    <option
                                                                        value="Arial"
                                                                        >Arial</option
                                                                    >
                                                                    <option
                                                                        value="Courier New"
                                                                        >Courier
                                                                        New</option
                                                                    >
                                                                    <option
                                                                        value="Times New Roman"
                                                                        >Times
                                                                        New
                                                                        Roman</option
                                                                    >
                                                                    <option
                                                                        value="Georgia"
                                                                        >Georgia</option
                                                                    >
                                                                    <option
                                                                        value="Impact"
                                                                        >Impact</option
                                                                    >
                                                                </select>
                                                            </div>
                                                            <div
                                                                class="flex gap-2"
                                                            >
                                                                <button
                                                                    type="button"
                                                                    onclick={() => {
                                                                        selLayer.bold =
                                                                            !selLayer.bold;
                                                                        redrawCompositeCanvas();
                                                                    }}
                                                                    class="flex-1 py-0.5 text-xs border rounded font-black {selLayer.bold
                                                                        ? 'bg-slate-200 border-slate-300'
                                                                        : 'bg-white'}"
                                                                    >B</button
                                                                >
                                                                <button
                                                                    type="button"
                                                                    onclick={() => {
                                                                        selLayer.italic =
                                                                            !selLayer.italic;
                                                                        redrawCompositeCanvas();
                                                                    }}
                                                                    class="flex-1 py-0.5 text-xs border rounded italic {selLayer.italic
                                                                        ? 'bg-slate-200 border-slate-300'
                                                                        : 'bg-white'}"
                                                                    >I</button
                                                                >
                                                                <button
                                                                    type="button"
                                                                    onclick={() => {
                                                                        selLayer.stroke =
                                                                            !selLayer.stroke;
                                                                        redrawCompositeCanvas();
                                                                    }}
                                                                    class="flex-grow py-0.5 text-[10px] border rounded {selLayer.stroke
                                                                        ? 'bg-slate-200 border-slate-300'
                                                                        : 'bg-white'}"
                                                                    >Stroke</button
                                                                >
                                                            </div>
                                                        </div>
                                                    {/if}

                                                    <!-- Position X -->
                                                    <div class="space-y-1">
                                                        <div
                                                            class="flex justify-between text-[10px] font-bold"
                                                        >
                                                            <span
                                                                class="text-slate-500"
                                                                >Posisi X</span
                                                            >
                                                            <span
                                                                class="text-brand-blueRoyal"
                                                                >{Math.round(
                                                                    selLayer.x,
                                                                )}</span
                                                            >
                                                        </div>
                                                        <input
                                                            type="range"
                                                            min="0"
                                                            max="512"
                                                            step="2"
                                                            bind:value={
                                                                selLayer.x
                                                            }
                                                            oninput={redrawCompositeCanvas}
                                                            class="w-full h-1 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-brand-blueRoyal"
                                                        />
                                                    </div>

                                                    <!-- Position Y -->
                                                    <div class="space-y-1">
                                                        <div
                                                            class="flex justify-between text-[10px] font-bold"
                                                        >
                                                            <span
                                                                class="text-slate-500"
                                                                >Posisi Y</span
                                                            >
                                                            <span
                                                                class="text-brand-blueRoyal"
                                                                >{Math.round(
                                                                    selLayer.y,
                                                                )}</span
                                                            >
                                                        </div>
                                                        <input
                                                            type="range"
                                                            min="0"
                                                            max="512"
                                                            step="2"
                                                            bind:value={
                                                                selLayer.y
                                                            }
                                                            oninput={redrawCompositeCanvas}
                                                            class="w-full h-1 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-brand-blueRoyal"
                                                        />
                                                    </div>

                                                    <!-- Layer Scale -->
                                                    <div class="space-y-1">
                                                        <div
                                                            class="flex justify-between text-[10px] font-bold"
                                                        >
                                                            <span
                                                                class="text-slate-500"
                                                                >Ukuran (Skala)</span
                                                            >
                                                            <span
                                                                class="text-brand-blueRoyal"
                                                                >{selLayer.scale.toFixed(
                                                                    2,
                                                                )}</span
                                                            >
                                                        </div>
                                                        <input
                                                            type="range"
                                                            min="0.05"
                                                            max="3.0"
                                                            step="0.05"
                                                            bind:value={
                                                                selLayer.scale
                                                            }
                                                            oninput={redrawCompositeCanvas}
                                                            class="w-full h-1 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-brand-blueRoyal"
                                                        />
                                                    </div>

                                                    <!-- Layer Rotation -->
                                                    <div class="space-y-1">
                                                        <div
                                                            class="flex justify-between text-[10px] font-bold"
                                                        >
                                                            <span
                                                                class="text-slate-500"
                                                                >Rotasi</span
                                                            >
                                                            <span
                                                                class="text-brand-blueRoyal"
                                                                >{selLayer.rotation}°</span
                                                            >
                                                        </div>
                                                        <input
                                                            type="range"
                                                            min="0"
                                                            max="360"
                                                            step="5"
                                                            bind:value={
                                                                selLayer.rotation
                                                            }
                                                            oninput={redrawCompositeCanvas}
                                                            class="w-full h-1 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-brand-blueRoyal"
                                                        />
                                                    </div>

                                                    <!-- Layer Opacity -->
                                                    <div class="space-y-1">
                                                        <div
                                                            class="flex justify-between text-[10px] font-bold"
                                                        >
                                                            <span
                                                                class="text-slate-500"
                                                                >Transparansi</span
                                                            >
                                                            <span
                                                                class="text-brand-blueRoyal"
                                                                >{Math.round(
                                                                    (selLayer.opacity ||
                                                                        1.0) *
                                                                        100,
                                                                )}%</span
                                                            >
                                                        </div>
                                                        <input
                                                            type="range"
                                                            min="0.0"
                                                            max="1.0"
                                                            step="0.05"
                                                            bind:value={
                                                                selLayer.opacity
                                                            }
                                                            oninput={redrawCompositeCanvas}
                                                            class="w-full h-1 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-brand-blueRoyal"
                                                        />
                                                    </div>
                                                </div>
                                            {/if}
                                        {/if}

                                        <!-- Projection Sliders -->
                                        <div class="space-y-2 border-t pt-3">
                                            <span
                                                class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1"
                                                >Pengaturan Penempatan Logo 3D
                                                (Decal)</span
                                            >

                                            <!-- Logo scale -->
                                            <div class="space-y-1">
                                                <div
                                                    class="flex justify-between text-xs font-semibold"
                                                >
                                                    <span class="text-slate-500"
                                                        >Skala Proyeksi Decal</span
                                                    >
                                                    <span
                                                        class="text-brand-blueRoyal font-bold"
                                                        >{logoScale.toFixed(
                                                            2,
                                                        )}</span
                                                    >
                                                </div>
                                                <input
                                                    type="range"
                                                    min="0.05"
                                                    max="2.0"
                                                    step="0.05"
                                                    bind:value={logoScale}
                                                    oninput={updateThreeMesh}
                                                    class="w-full h-1 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-brand-blueRoyal"
                                                />
                                            </div>

                                            <!-- Logo X -->
                                            <div class="space-y-1">
                                                <div
                                                    class="flex justify-between text-xs font-semibold"
                                                >
                                                    <span class="text-slate-500"
                                                        >Geser Sablon (X)</span
                                                    >
                                                    <span
                                                        class="text-brand-blueRoyal font-bold"
                                                        >{logoX.toFixed(
                                                            2,
                                                        )}</span
                                                    >
                                                </div>
                                                <input
                                                    type="range"
                                                    min="-2.0"
                                                    max="2.0"
                                                    step="0.02"
                                                    bind:value={logoX}
                                                    oninput={updateThreeMesh}
                                                    class="w-full h-1 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-brand-blueRoyal"
                                                />
                                            </div>

                                            <!-- Logo Y -->
                                            <div class="space-y-1">
                                                <div
                                                    class="flex justify-between text-xs font-semibold"
                                                >
                                                    <span class="text-slate-500"
                                                        >Geser Sablon (Y)</span
                                                    >
                                                    <span
                                                        class="text-brand-blueRoyal font-bold"
                                                        >{logoY.toFixed(
                                                            2,
                                                        )}</span
                                                    >
                                                </div>
                                                <input
                                                    type="range"
                                                    min="-2.0"
                                                    max="2.0"
                                                    step="0.02"
                                                    bind:value={logoY}
                                                    oninput={updateThreeMesh}
                                                    class="w-full h-1 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-brand-blueRoyal"
                                                />
                                            </div>

                                            <!-- Logo Depth Z (Front/Back) -->
                                            <div class="grid grid-cols-2 gap-3">
                                                <div class="space-y-1">
                                                    <span
                                                        class="text-[10px] font-semibold text-slate-500"
                                                        >Kedalaman Depan (Z)</span
                                                    >
                                                    <input
                                                        type="range"
                                                        min="-2.0"
                                                        max="2.0"
                                                        step="0.02"
                                                        bind:value={logoZ}
                                                        oninput={updateThreeMesh}
                                                        class="w-full h-1 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-brand-blueRoyal"
                                                    />
                                                </div>
                                                <div class="space-y-1">
                                                    <span
                                                        class="text-[10px] font-semibold text-slate-500"
                                                        >Kedalaman Belakang (Z)</span
                                                    >
                                                    <input
                                                        type="range"
                                                        min="-2.0"
                                                        max="2.0"
                                                        step="0.02"
                                                        bind:value={logoZBack}
                                                        oninput={updateThreeMesh}
                                                        class="w-full h-1 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-brand-blueRoyal"
                                                    />
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Model adjustments (mockup positioning) -->
                                        <div class="space-y-2 border-t pt-3">
                                            <span
                                                class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block mb-1"
                                                >Penyesuaian Model Mockup 3D</span
                                            >

                                            <!-- Model scale -->
                                            <div class="space-y-1">
                                                <div
                                                    class="flex justify-between text-xs font-semibold"
                                                >
                                                    <span class="text-slate-500"
                                                        >Skala Model</span
                                                    >
                                                    <span
                                                        class="text-brand-blueRoyal font-bold"
                                                        >{mockupScale.toFixed(
                                                            1,
                                                        )}</span
                                                    >
                                                </div>
                                                <input
                                                    type="range"
                                                    min="0.1"
                                                    max="30.0"
                                                    step="0.1"
                                                    bind:value={mockupScale}
                                                    oninput={updateThreeMesh}
                                                    class="w-full h-1 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-brand-blueRoyal"
                                                />
                                            </div>

                                            <!-- Model Y offset -->
                                            <div class="space-y-1">
                                                <div
                                                    class="flex justify-between text-xs font-semibold"
                                                >
                                                    <span class="text-slate-500"
                                                        >Posisi Vertikal (Y)</span
                                                    >
                                                    <span
                                                        class="text-brand-blueRoyal font-bold"
                                                        >{mockupY.toFixed(
                                                            2,
                                                        )}</span
                                                    >
                                                </div>
                                                <input
                                                    type="range"
                                                    min="-5.0"
                                                    max="5.0"
                                                    step="0.05"
                                                    bind:value={mockupY}
                                                    oninput={updateThreeMesh}
                                                    class="w-full h-1 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-brand-blueRoyal"
                                                />
                                            </div>

                                            <!-- Model X offset -->
                                            <div class="space-y-1">
                                                <div
                                                    class="flex justify-between text-xs font-semibold"
                                                >
                                                    <span class="text-slate-500"
                                                        >Posisi Horizontal (X)</span
                                                    >
                                                    <span
                                                        class="text-brand-blueRoyal font-bold"
                                                        >{mockupX.toFixed(
                                                            2,
                                                        )}</span
                                                    >
                                                </div>
                                                <input
                                                    type="range"
                                                    min="-5.0"
                                                    max="5.0"
                                                    step="0.05"
                                                    bind:value={mockupX}
                                                    oninput={updateThreeMesh}
                                                    class="w-full h-1 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-brand-blueRoyal"
                                                />
                                            </div>

                                            <!-- Model Rotations (X, Y, Z) -->
                                            <div class="grid grid-cols-3 gap-2">
                                                <div class="space-y-1">
                                                    <span
                                                        class="text-[9px] font-bold text-slate-500"
                                                        >Rotasi X ({mockupRotationX}°)</span
                                                    >
                                                    <input
                                                        type="range"
                                                        min="0"
                                                        max="360"
                                                        step="5"
                                                        bind:value={
                                                            mockupRotationX
                                                        }
                                                        oninput={updateThreeMesh}
                                                        class="w-full h-1 accent-brand-blueRoyal"
                                                    />
                                                </div>
                                                <div class="space-y-1">
                                                    <span
                                                        class="text-[9px] font-bold text-slate-500"
                                                        >Rotasi Y ({mockupRotationY}°)</span
                                                    >
                                                    <input
                                                        type="range"
                                                        min="0"
                                                        max="360"
                                                        step="5"
                                                        bind:value={
                                                            mockupRotationY
                                                        }
                                                        oninput={updateThreeMesh}
                                                        class="w-full h-1 accent-brand-blueRoyal"
                                                    />
                                                </div>
                                                <div class="space-y-1">
                                                    <span
                                                        class="text-[9px] font-bold text-slate-500"
                                                        >Rotasi Z ({mockupRotationZ}°)</span
                                                    >
                                                    <input
                                                        type="range"
                                                        min="0"
                                                        max="360"
                                                        step="5"
                                                        bind:value={
                                                            mockupRotationZ
                                                        }
                                                        oninput={updateThreeMesh}
                                                        class="w-full h-1 accent-brand-blueRoyal"
                                                    />
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Material and Base Color selection -->
                                        <div class="space-y-2 border-t pt-3">
                                            <span
                                                class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block"
                                                >Material & Warna Dasar Produk</span
                                            >

                                            <div class="grid grid-cols-2 gap-3">
                                                <div class="space-y-1">
                                                    <span
                                                        class="text-[10px] font-semibold text-slate-500"
                                                        >Pilih Material</span
                                                    >
                                                    <select
                                                        bind:value={
                                                            selectedMaterial
                                                        }
                                                        onchange={updateThreeMesh}
                                                        class="w-full text-xs border border-slate-200 rounded-lg p-2 bg-white font-bold text-slate-700"
                                                    >
                                                        <option value="cotton"
                                                            >Katun (Cotton)</option
                                                        >
                                                        <option
                                                            value="polyester"
                                                            >Polyester</option
                                                        >
                                                        <option value="metal"
                                                            >Logam (Metal)</option
                                                        >
                                                        <option value="ceramic"
                                                            >Keramik (Ceramic)</option
                                                        >
                                                        <option value="glass"
                                                            >Kaca (Glass)</option
                                                        >
                                                        <option value="plastic"
                                                            >Plastik (Plastic)</option
                                                        >
                                                    </select>
                                                </div>
                                                <div class="space-y-1">
                                                    <span
                                                        class="text-[10px] font-semibold text-slate-500"
                                                        >Warna Dasar</span
                                                    >
                                                    <div
                                                        class="flex gap-2 items-center"
                                                    >
                                                        <input
                                                            type="color"
                                                            bind:value={
                                                                selectedColor
                                                            }
                                                            oninput={redrawCompositeCanvas}
                                                            class="w-7 h-7 rounded border cursor-pointer p-0"
                                                        />
                                                        <input
                                                            type="text"
                                                            bind:value={
                                                                selectedColor
                                                            }
                                                            oninput={redrawCompositeCanvas}
                                                            class="border border-slate-200 rounded-lg px-1.5 py-1 text-[11px] w-20 uppercase font-bold focus:outline-none"
                                                        />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Silhouette-specific settings (thickness, removebg, side color) -->
                                        {#if modelType === 'plane'}
                                            <div
                                                class="space-y-2 border-t pt-3 bg-slate-50 p-2.5 rounded-xl border border-slate-150"
                                            >
                                                <span
                                                    class="text-[10px] font-bold text-slate-700 uppercase tracking-wider block"
                                                    >Pengaturan Ketebalan Siluet
                                                    Solid</span
                                                >

                                                <!-- Silhouette Base Thickness -->
                                                <div class="space-y-1">
                                                    <div
                                                        class="flex justify-between text-xs font-semibold"
                                                    >
                                                        <span
                                                            class="text-slate-500"
                                                            >Ketebalan Sisi
                                                            Siluet</span
                                                        >
                                                        <span
                                                            class="text-brand-blueRoyal font-bold"
                                                            >{baseThickness.toFixed(
                                                                2,
                                                            )}</span
                                                        >
                                                    </div>
                                                    <input
                                                        type="range"
                                                        min="0.02"
                                                        max="0.80"
                                                        step="0.01"
                                                        bind:value={
                                                            baseThickness
                                                        }
                                                        oninput={updateThreeMesh}
                                                        class="w-full h-1 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-brand-blueRoyal"
                                                    />
                                                </div>

                                                <!-- Silhouette Side Wall Color -->
                                                <div class="space-y-1">
                                                    <div
                                                        class="flex justify-between text-xs font-semibold"
                                                    >
                                                        <span
                                                            class="text-slate-500"
                                                            >Warna Sisi Samping</span
                                                        >
                                                        <span
                                                            class="text-brand-blueRoyal font-bold uppercase"
                                                            >{sideColor}</span
                                                        >
                                                    </div>
                                                    <div
                                                        class="flex gap-2 items-center"
                                                    >
                                                        <input
                                                            type="color"
                                                            bind:value={
                                                                sideColor
                                                            }
                                                            oninput={updateThreeMesh}
                                                            class="w-7 h-7 rounded border cursor-pointer p-0"
                                                        />
                                                        <input
                                                            type="text"
                                                            bind:value={
                                                                sideColor
                                                            }
                                                            oninput={updateThreeMesh}
                                                            class="border border-slate-200 rounded-lg px-1.5 py-1 text-[11px] w-20 uppercase font-bold focus:outline-none"
                                                        />
                                                    </div>
                                                </div>

                                                <!-- Flat mesh toggle -->
                                                <div
                                                    class="flex items-center justify-between pt-1"
                                                >
                                                    <span
                                                        class="text-xs font-semibold text-slate-500"
                                                        >Tampilan Rata (Mesh
                                                        Spikes Rata)</span
                                                    >
                                                    <label
                                                        class="relative inline-flex items-center cursor-pointer"
                                                    >
                                                        <input
                                                            type="checkbox"
                                                            bind:checked={
                                                                flatMesh
                                                            }
                                                            onchange={updateThreeMesh}
                                                            class="sr-only peer"
                                                        />
                                                        <div
                                                            class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:height-4 after:width-4 after:transition-all peer-checked:bg-brand-blueRoyal"
                                                        ></div>
                                                    </label>
                                                </div>

                                                {#if !flatMesh}
                                                    <!-- Relief Depth Slider -->
                                                    <div
                                                        class="space-y-1 pl-3 border-l-2 border-slate-200"
                                                    >
                                                        <div
                                                            class="flex justify-between text-xs font-semibold"
                                                        >
                                                            <span
                                                                class="text-slate-500 text-[11px]"
                                                                >Tebal Relief
                                                                Detail</span
                                                            >
                                                            <span
                                                                class="text-brand-blueRoyal font-bold text-[11px]"
                                                                >{reliefDepth.toFixed(
                                                                    3,
                                                                )}</span
                                                            >
                                                        </div>
                                                        <input
                                                            type="range"
                                                            min="0.0"
                                                            max="0.20"
                                                            step="0.005"
                                                            bind:value={
                                                                reliefDepth
                                                            }
                                                            oninput={updateThreeMesh}
                                                            class="w-full h-1 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-brand-blueRoyal"
                                                        />
                                                    </div>
                                                {/if}

                                                <!-- Hapus Background Option -->
                                                <div
                                                    class="flex items-center justify-between pt-1"
                                                >
                                                    <span
                                                        class="text-xs font-semibold text-slate-500"
                                                        >Hapus Background Asli
                                                        (Auto-Detect)</span
                                                    >
                                                    <label
                                                        class="relative inline-flex items-center cursor-pointer"
                                                    >
                                                        <input
                                                            type="checkbox"
                                                            bind:checked={
                                                                removeBg
                                                            }
                                                            onchange={updateThreeMesh}
                                                            class="sr-only peer"
                                                        />
                                                        <div
                                                            class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:height-4 after:width-4 after:transition-all peer-checked:bg-brand-blueRoyal"
                                                        ></div>
                                                    </label>
                                                </div>

                                                {#if removeBg}
                                                    <!-- Background Tolerance Slider -->
                                                    <div
                                                        class="space-y-1 pl-3 border-l-2 border-slate-200"
                                                    >
                                                        <div
                                                            class="flex justify-between text-xs font-semibold"
                                                        >
                                                            <span
                                                                class="text-slate-500 text-[11px]"
                                                                >Toleransi Warna
                                                                BG</span
                                                            >
                                                            <span
                                                                class="text-brand-blueRoyal font-bold text-[11px]"
                                                                >{bgTolerance.toFixed(
                                                                    2,
                                                                )}</span
                                                            >
                                                        </div>
                                                        <input
                                                            type="range"
                                                            min="0.02"
                                                            max="0.50"
                                                            step="0.01"
                                                            bind:value={
                                                                bgTolerance
                                                            }
                                                            oninput={updateThreeMesh}
                                                            class="w-full h-1 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-brand-blueRoyal"
                                                        />
                                                    </div>
                                                {/if}
                                            </div>
                                        {/if}
                                    </div>
                                {/if}
                            </div>
                        </div>
                    {/if}
                </div>

                <!-- Footer -->
                <div
                    class="px-6 py-4 border-t border-slate-100 flex justify-between items-center bg-slate-50/50"
                >
                    {#if generatorStep === 1}
                        <div></div>
                        <button
                            type="button"
                            onclick={startGeneration}
                            disabled={activeTab !== 'scratch' &&
                                !selectedGenImage &&
                                !customMockupFile}
                            class="px-5 py-2.5 bg-brand-blueRoyal text-white text-xs font-bold rounded-xl flex items-center gap-1.5 transition disabled:opacity-50 disabled:cursor-not-allowed shadow-md cursor-pointer uppercase tracking-wider font-outfit"
                        >
                            Mulai Kustomisasi 3D <i class="ti ti-arrow-right"
                            ></i>
                        </button>
                    {:else if generatorStep === 2}
                        <div></div>
                        <button
                            type="button"
                            disabled
                            class="px-5 py-2.5 bg-slate-200 text-slate-400 text-xs font-bold rounded-xl flex items-center gap-1.5 disabled:cursor-not-allowed uppercase tracking-wider font-outfit"
                        >
                            Sedang Memproses...
                        </button>
                    {:else if generatorStep === 3}
                        <div class="flex flex-wrap gap-2 items-center">
                            <button
                                type="button"
                                onclick={() => {
                                    generatorStep = 1;
                                    cleanupThreeJS();
                                }}
                                class="px-4 py-2.5 bg-slate-150 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition uppercase tracking-wider font-outfit cursor-pointer"
                            >
                                Kembali
                            </button>
                            <button
                                type="button"
                                onclick={() => exportPrintReady4K('front')}
                                class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl flex items-center gap-1.5 transition shadow-sm cursor-pointer uppercase tracking-wider font-outfit"
                            >
                                <i class="ti ti-download text-sm"></i> Cetak Depan
                                4K
                            </button>
                            <button
                                type="button"
                                onclick={() => exportPrintReady4K('back')}
                                class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl flex items-center gap-1.5 transition shadow-sm cursor-pointer uppercase tracking-wider font-outfit"
                            >
                                <i class="ti ti-download text-sm"></i> Cetak Belakang
                                4K
                            </button>
                            <button
                                type="button"
                                onclick={downloadMockupSnapshot4K}
                                class="px-4 py-2.5 bg-indigo-650 hover:bg-indigo-750 text-white text-xs font-bold rounded-xl flex items-center gap-1.5 transition shadow-sm cursor-pointer uppercase tracking-wider font-outfit"
                            >
                                <i class="ti ti-camera text-sm"></i> Snapshot Mockup
                                4K
                            </button>
                        </div>
                        <button
                            type="button"
                            onclick={applyGeneratedModel}
                            class="px-5 py-2.5 bg-brand-blueRoyal text-white text-xs font-bold rounded-xl flex items-center gap-1.5 shadow-md transition uppercase tracking-wider font-outfit cursor-pointer"
                        >
                            Gunakan Model 3D <i class="ti ti-check"></i>
                        </button>
                    {/if}
                </div>
            </div>
        </div>
    {/if}

    {#if showQuickAddCategoryModal}
        <div
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm animate-in fade-in duration-200"
            role="dialog"
            aria-modal="true"
        >
            <div
                class="bg-white rounded-xl border border-slate-200 p-6 shadow-2xl max-w-md w-full animate-in zoom-in-95 duration-200"
            >
                <!-- Header -->
                <div class="flex justify-between items-center mb-4 pb-3 border-b border-slate-100">
                    <h3 class="text-base font-semibold text-slate-900">
                        Tambah Kategori Baru
                    </h3>
                    <button
                        type="button"
                        aria-label="Tutup"
                        onclick={closeQuickAddCategory}
                        class="w-8 h-8 rounded-full bg-slate-50 hover:bg-rose-50 hover:text-rose-600 flex items-center justify-center text-slate-400 transition cursor-pointer"
                    >
                        <i class="ti ti-x text-sm"></i>
                    </button>
                </div>

                <!-- Form -->
                <form onsubmit={handleQuickAddCategory} class="space-y-4">
                    <Input
                        bind:value={quickCategoryName}
                        id="quick_category_name"
                        label="Nama Kategori"
                        placeholder="Cth: Sepatu Pria"
                        required={true}
                    />

                    <Input
                        bind:value={quickCategorySlug}
                        id="quick_category_slug"
                        label="Slug Kategori"
                        placeholder="cth: sepatu-pria"
                        required={true}
                    />

                    <div class="space-y-2">
                        <label
                            class="text-xs font-bold text-slate-600 block"
                            for="quick_category_parent"
                        >
                            Kategori Induk
                        </label>
                        <select
                            bind:value={quickCategoryParentId}
                            id="quick_category_parent"
                            class="w-full px-3 py-2 border border-slate-200 rounded-xl text-sm transition bg-white focus:border-brand-blueRoyal focus:ring-1 focus:ring-brand-blueRoyal/20"
                        >
                            <option value="">Tidak Ada (Kategori Utama)</option>
                            {#each categoryOptions as opt}
                                <option value={opt.id}>{opt.name}</option>
                            {/each}
                        </select>
                    </div>

                    <!-- Footer Buttons -->
                    <div
                        class="flex justify-end gap-3 pt-4 border-t border-slate-100"
                    >
                        <button
                            type="button"
                            onclick={closeQuickAddCategory}
                            class="px-4 py-2 bg-slate-50 hover:bg-slate-100 text-slate-600 text-xs font-bold rounded-xl transition"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            disabled={isSubmittingCategory}
                            class="px-4 py-2 bg-brand-blueRoyal hover:bg-brand-blueRoyal/95 text-white text-xs font-bold rounded-xl transition flex items-center gap-1.5 disabled:opacity-50 cursor-pointer"
                        >
                            {#if isSubmittingCategory}
                                <i class="ti ti-loader animate-spin text-sm"
                                ></i> Menyimpan...
                            {:else}
                                Simpan Kategori
                            {/if}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    {/if}

    {#if showQuickAddBrandModal}
        <div
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm animate-in fade-in duration-200"
            role="dialog"
            aria-modal="true"
        >
            <div
                class="bg-white rounded-xl border border-slate-200 p-6 shadow-2xl max-w-md w-full animate-in zoom-in-95 duration-200"
            >
                <!-- Header -->
                <div class="flex justify-between items-center mb-4 pb-3 border-b border-slate-100">
                    <h3 class="text-base font-semibold text-slate-900">
                        Tambah Merek Baru
                    </h3>
                    <button
                        type="button"
                        aria-label="Tutup"
                        onclick={closeQuickAddBrand}
                        class="w-8 h-8 rounded-full bg-slate-50 hover:bg-rose-50 hover:text-rose-600 flex items-center justify-center text-slate-400 transition cursor-pointer"
                    >
                        <i class="ti ti-x text-sm"></i>
                    </button>
                </div>

                <!-- Form -->
                <form onsubmit={handleQuickAddBrand} class="space-y-4">
                    <Input
                        bind:value={quickBrandName}
                        id="quick_brand_name"
                        label="Nama Merek"
                        placeholder="Cth: Nike"
                        required={true}
                    />

                    <!-- Footer Buttons -->
                    <div
                        class="flex justify-end gap-3 pt-4 border-t border-slate-100"
                    >
                        <button
                            type="button"
                            onclick={closeQuickAddBrand}
                            class="px-4 py-2 bg-slate-50 hover:bg-slate-100 text-slate-600 text-xs font-bold rounded-xl transition"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            disabled={isSubmittingBrand}
                            class="px-4 py-2 bg-brand-blueRoyal hover:bg-brand-blueRoyal/95 text-white text-xs font-bold rounded-xl transition flex items-center gap-1.5 disabled:opacity-50 cursor-pointer"
                        >
                            {#if isSubmittingBrand}
                                <i class="ti ti-loader animate-spin text-sm"
                                ></i> Menyimpan...
                            {:else}
                                Simpan Merek
                            {/if}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    {/if}

    <ProductImageSearchModal
        show={showImageSearchModal}
        productName={form.name}
        onselect={handleWebImageSelect}
        onclose={() => showImageSearchModal = false}
    />
</AdminLayout>
