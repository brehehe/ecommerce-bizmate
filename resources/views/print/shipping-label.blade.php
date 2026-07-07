<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Label Pengiriman - {{ $transaction->transaction_number }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>

    <style>
        /* ===========================
           SCREEN STYLES
        =========================== */
        *, *::before, *::after {
            box-sizing: border-box;
        }

        :root {
            --lw: 7.5cm;
            --lh: 10cm;
            --lm: 0.2cm;
            --fs: 8px;
            --border: 2px solid #000;
        }

        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            background: #f1f5f9;
            color: #000;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* ===========================
           CONTROL BAR (SCREEN ONLY)
        =========================== */
        .ctrl-bar {
            position: fixed;
            top: 0; left: 0; right: 0;
            background: rgba(10, 17, 35, 0.95);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255,255,255,0.1);
            color: #fff;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            z-index: 9999;
            font-size: 12px;
        }

        .ctrl-logo {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 700;
            font-size: 14px;
            color: #38bdf8;
            flex-shrink: 0;
        }

        .ctrl-groups {
            display: flex;
            align-items: flex-end;
            gap: 12px;
            flex-wrap: wrap;
            flex: 1;
            justify-content: center;
        }

        .ctrl-field {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }
        .ctrl-field label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #94a3b8;
            font-weight: 600;
        }
        .ctrl-field select,
        .ctrl-field input[type="range"] {
            background: #1e293b;
            color: #fff;
            border: 1px solid #475569;
            padding: 5px 10px;
            border-radius: 6px;
            outline: none;
            cursor: pointer;
            font-size: 11px;
            height: 30px;
        }
        .ctrl-field select:focus {
            border-color: #38bdf8;
        }

        .ctrl-dims {
            display: flex;
            flex-direction: row;
            align-items: flex-end;
            gap: 8px;
        }
        .dim-input {
            display: flex;
            align-items: center;
            gap: 5px;
            background: #1e293b;
            border: 1px solid #475569;
            padding: 5px 8px;
            border-radius: 6px;
            height: 30px;
        }
        .dim-input label {
            color: #94a3b8;
            font-size: 9px;
            text-transform: uppercase;
            font-weight: 600;
        }
        .dim-input input[type="number"] {
            background: transparent;
            border: none;
            color: #fff;
            width: 38px;
            outline: none;
            font-weight: 700;
            text-align: center;
            font-size: 12px;
        }
        .dim-input input[type="number"]::-webkit-outer-spin-button,
        .dim-input input[type="number"]::-webkit-inner-spin-button {
            -webkit-appearance: none;
        }
        .dim-input input[type="number"] { -moz-appearance: textfield; }
        .dim-input .unit {
            color: #64748b;
            font-size: 9px;
            font-weight: 600;
        }

        .slider-wrap {
            display: flex;
            flex-direction: row !important;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }
        .slider-wrap input[type="range"] {
            -webkit-appearance: none;
            width: 100px;
            height: 5px;
            background: #334155;
            border-radius: 3px;
            outline: none;
            cursor: pointer;
            border: none;
            padding: 0;
        }
        .slider-wrap input[type="range"]::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 14px;
            height: 14px;
            background: #38bdf8;
            border-radius: 50%;
            cursor: pointer;
        }
        #fs-val {
            color: #38bdf8;
            font-weight: 700;
            font-size: 11px;
            min-width: 30px;
        }

        .ctrl-actions {
            display: flex;
            gap: 8px;
            flex-shrink: 0;
        }

        .btn-print {
            background: #38bdf8;
            color: #0f172a;
            border: none;
            padding: 7px 16px;
            border-radius: 6px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            transition: all 0.2s;
        }
        .btn-print:hover { background: #0ea5e9; transform: translateY(-1px); }

        .btn-bt {
            background: #10b981;
            color: #fff;
            border: none;
            padding: 7px 16px;
            border-radius: 6px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            transition: all 0.2s;
        }
        .btn-bt:hover { background: #059669; transform: translateY(-1px); }
        .btn-bt:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

        /* ===========================
           LABEL WRAPPER (SCREEN)
        =========================== */
        .page-wrapper {
            padding-top: 80px;
            padding-bottom: 30px;
            min-height: 100vh;
            display: flex;
            align-items: flex-start;
            justify-content: center;
        }

        /* ===========================
           LABEL CONTAINER
        =========================== */
        .label {
            width: calc(var(--lw) - var(--lm) * 2);
            height: calc(var(--lh) - var(--lm) * 2);
            border: var(--border);
            background: #fff;
            margin: var(--lm) auto;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            font-size: var(--fs);
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
        }

        /* ===========================
           LABEL SECTIONS
        =========================== */

        /* -- Header: Courier | Platform | Store -- */
        .lbl-header {
            display: grid;
            grid-template-columns: 1fr 1.5fr 1fr;
            border-bottom: var(--border);
            height: 4em;
            flex-shrink: 0;
        }
        .lbl-courier {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border-right: 1px solid #000;
            padding: 0.3em;
        }
        .lbl-courier .lbl-sub { font-size: 0.75em; color: #666; }
        .lbl-courier .courier-badge {
            background: #000;
            color: #fff;
            padding: 0.15em 0.5em;
            border-radius: 3px;
            font-weight: 800;
            font-size: 0.95em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 0.2em;
        }
        .lbl-platform {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .lbl-platform .plat-name {
            font-weight: 800;
            font-size: 1.4em;
            letter-spacing: -0.5px;
            color: #000;
        }
        .lbl-platform .plat-name span { color: #00bfa6; }
        .lbl-platform .plat-url { font-size: 0.75em; color: #666; }
        .lbl-store {
            border-left: 1px solid #000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.3em;
            font-weight: 700;
            font-size: 0.85em;
            text-transform: uppercase;
            word-break: break-all;
            text-align: center;
        }

        /* -- Barcode -- */
        .lbl-barcode {
            border-bottom: var(--border);
            padding: 0.4em 0 0.2em;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            height: 5.5em;
        }
        #barcode {
            max-width: 90%;
            height: 3em;
        }
        .lbl-awb {
            font-family: monospace;
            font-size: 1em;
            font-weight: 700;
            margin-top: 0.2em;
            letter-spacing: 1px;
        }

        /* -- COD (conditional) -- */
        .lbl-cod {
            border-bottom: var(--border);
            padding: 0.4em;
            text-align: center;
            font-size: 1.1em;
            font-weight: 800;
            background: #fef3c7;
            flex-shrink: 0;
        }

        /* -- Service -- */
        .lbl-service {
            border-bottom: var(--border);
            padding: 0.4em;
            text-align: center;
            font-size: 1em;
            font-weight: 700;
            text-transform: uppercase;
            flex-shrink: 0;
        }

        /* -- Routing + Weight -- */
        .lbl-routing {
            border-bottom: var(--border);
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            height: 3.5em;
            flex-shrink: 0;
        }
        .routing-code {
            background: #000;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1.8em;
            text-transform: uppercase;
            border-right: 2px solid #000;
        }
        .routing-meta {
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 0.4em 0.7em;
            gap: 0.2em;
            font-size: 0.9em;
        }
        .routing-meta-row {
            display: flex;
            justify-content: space-between;
            font-weight: 600;
        }
        .routing-meta-row span:last-child { font-weight: 800; }

        /* -- Address Grid -- */
        .lbl-address {
            border-bottom: var(--border);
            display: grid;
            grid-template-columns: 1fr 1fr;
            flex: 1;
            min-height: 0;
        }
        .addr-col {
            padding: 0.5em;
            overflow: hidden;
        }
        .addr-col:first-child { border-right: 1px solid #000; }
        .addr-label {
            font-size: 0.8em;
            font-weight: 800;
            text-transform: uppercase;
            color: #555;
            border-bottom: 1px dashed #ccc;
            padding-bottom: 0.15em;
            margin-bottom: 0.3em;
        }
        .addr-name {
            font-size: 1em;
            font-weight: 800;
            margin-bottom: 0.1em;
        }
        .addr-phone {
            font-weight: 700;
            margin-bottom: 0.25em;
            font-size: 0.9em;
        }
        .addr-text {
            font-size: 0.85em;
            line-height: 1.3;
            color: #222;
        }

        /* -- Footer Info -- */
        .lbl-footer-info {
            border-bottom: var(--border);
            padding: 0.5em 0.6em;
            font-size: 0.9em;
            line-height: 1.4;
            flex-shrink: 0;
        }
        .fi-row { margin: 0.2em 0; }
        .fi-label { font-weight: 700; text-transform: uppercase; }

        /* -- Branding -- */
        .lbl-brand {
            padding: 0.35em;
            text-align: center;
            font-size: 0.75em;
            color: #777;
            flex-shrink: 0;
        }

        /* ===========================
           PRINT STYLES
        =========================== */
        @page {
            size: 7.5cm 10cm;
            margin: 0;
        }

        @media print {
            html {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            body {
                margin: 0 !important;
                padding: 0 !important;
                background: #fff !important;
            }

            .ctrl-bar,
            .no-print {
                display: none !important;
            }

            .page-wrapper {
                padding: 0 !important;
                margin: 0 !important;
                min-height: 0 !important;
                display: block !important;
            }

            .label {
                width: calc(100vw - var(--lm) * 2) !important;
                height: calc(100vh - var(--lm) * 2) !important;
                margin: var(--lm) !important;
                box-shadow: none !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                overflow: hidden !important;
            }
        }
    </style>
</head>
<body>
    @php
        $totalQty = $transaction->items->sum('quantity');
        $realWeightGrams = $transaction->items->sum(function ($item) {
            return ($item->productVariant->weight ?? $item->product->weight ?? 1000) * $item->quantity;
        });
        $realWeightKg = $realWeightGrams / 1000;
        $itemNames = $transaction->items->map(function ($item) {
            return $item->product_name.' (x'.$item->quantity.')';
        })->join(', ');
        $awbNumber = $transaction->tracking_number ?? 'BELUM-ADA-RESI';
        $isCod = str_contains(strtolower($transaction->paymentMethod->name ?? ''), 'cod');
        $courierName = strtoupper($transaction->courier_name ?? $transaction->shipping_courier ?? 'KURIR');
        $serviceType = strtoupper(preg_replace('/\[.*?\]\s*/', '', $transaction->shipping_service ?? 'REG'));
    @endphp

    <!-- ========== CONTROL BAR ========== -->
    <div class="ctrl-bar no-print" id="ctrl-bar">
        <div class="ctrl-logo">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                <path d="M17 17h2a2 2 0 0 0 2 -2v-4a2 2 0 0 0 -2 -2h-14a2 2 0 0 0 -2 2v4a2 2 0 0 0 2 2h2" />
                <path d="M17 9v-4a2 2 0 0 0 -2 -2h-6a2 2 0 0 0 -2 2v4" />
                <path d="M7 13m0 2a2 2 0 0 1 2 -2h6a2 2 0 0 1 2 2v4a2 2 0 0 1 -2 2h-6a2 2 0 0 1 -2 -2z" />
            </svg>
            Print Manager
        </div>

        <div class="ctrl-groups">
            <!-- Preset -->
            <div class="ctrl-field">
                <label for="preset-sel">Ukuran Label</label>
                <select id="preset-sel" onchange="onPresetChange()">
                    <option value="7.5x10" selected>7,5 × 10 cm (Thermal)</option>
                    <option value="10x15">10 × 15 cm (Thermal Besar)</option>
                    <option value="A6">A6 (10,5 × 14,8 cm)</option>
                    <option value="custom">Kustom</option>
                </select>
            </div>

            <!-- Dimensions -->
            <div class="ctrl-dims">
                <div class="dim-input">
                    <label for="inp-w">W</label>
                    <input type="number" id="inp-w" step="0.5" value="7.5" min="4" max="30" oninput="onDimChange()">
                    <span class="unit">cm</span>
                </div>
                <div class="dim-input">
                    <label for="inp-h">H</label>
                    <input type="number" id="inp-h" step="0.5" value="10" min="4" max="40" oninput="onDimChange()">
                    <span class="unit">cm</span>
                </div>
                <div class="dim-input">
                    <label for="inp-m">Margin</label>
                    <input type="number" id="inp-m" step="0.1" value="0.2" min="0" max="1" oninput="applyAll()">
                    <span class="unit">cm</span>
                </div>
            </div>

            <!-- Border -->
            <div class="ctrl-field">
                <label for="sel-border">Garis Tepi</label>
                <select id="sel-border" onchange="applyAll()">
                    <option value="0">Tanpa Garis</option>
                    <option value="1">1 px</option>
                    <option value="2" selected>2 px (Default)</option>
                    <option value="3">3 px</option>
                    <option value="4">4 px (Tebal)</option>
                </select>
            </div>

            <!-- Font Size -->
            <div class="ctrl-field slider-wrap">
                <label for="sl-fs">Zoom Teks</label>
                <input type="range" id="sl-fs" min="7" max="14" value="8" oninput="applyAll()">
                <span id="fs-val">8px</span>
            </div>
        </div>

        <div class="ctrl-actions">
            <button class="btn-print" onclick="doPrint()">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                    <path d="M17 17h2a2 2 0 0 0 2 -2v-4a2 2 0 0 0 -2 -2h-14a2 2 0 0 0 -2 2v4a2 2 0 0 0 2 2h2" />
                    <path d="M17 9v-4a2 2 0 0 0 -2 -2h-6a2 2 0 0 0 -2 2v4" />
                    <path d="M7 13m0 2a2 2 0 0 1 2 -2h6a2 2 0 0 1 2 2v4a2 2 0 0 1 -2 2h-6a2 2 0 0 1 -2 -2z" />
                </svg>
                Cetak Label
            </button>
            <button class="btn-bt" id="btn-bt" onclick="printBluetooth()">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                    <path d="M7 8l10 8l-5 4v-16l5 4l-10 8" />
                </svg>
                Bluetooth
            </button>
        </div>
    </div>

    <!-- ========== PAGE WRAPPER ========== -->
    <div class="page-wrapper">

        <!-- ========== LABEL ========== -->
        <div class="label" id="lbl">

            <!-- Header -->
            <div class="lbl-header">
                <div class="lbl-courier">
                    <span class="lbl-sub">KURIR</span>
                    <span class="courier-badge">{{ $courierName }}</span>
                </div>
                <div class="lbl-platform">
                    <div class="plat-name">bite<span>ship</span></div>
                    <div class="plat-url">www.biteship.com</div>
                </div>
                <div class="lbl-store">{{ $storeName }}</div>
            </div>

            <!-- Barcode -->
            <div class="lbl-barcode">
                <svg id="barcode"></svg>
                <div class="lbl-awb">{{ $awbNumber }}</div>
            </div>

            <!-- COD -->
            @if($isCod)
                <div class="lbl-cod">
                    💰 COD — Rp {{ number_format($transaction->grand_total, 0, ',', '.') }}
                </div>
            @endif

            <!-- Service -->
            <div class="lbl-service">
                Layanan: {{ $serviceType }}
            </div>

            <!-- Routing + Weight -->
            <div class="lbl-routing">
                <div class="routing-code">{{ $routingCode ?? 'RC' }}</div>
                <div class="routing-meta">
                    <div class="routing-meta-row">
                        <span>Qty</span>
                        <span>{{ $totalQty }} Pcs</span>
                    </div>
                    <div class="routing-meta-row">
                        <span>Berat</span>
                        <span>{{ number_format($realWeightKg, 1) }} Kg</span>
                    </div>
                </div>
            </div>

            <!-- Address -->
            <div class="lbl-address">
                <div class="addr-col">
                    <div class="addr-label">Penerima</div>
                    <div class="addr-name">{{ $transaction->customerAddress->receiver_name ?? '-' }}</div>
                    <div class="addr-phone">{{ $transaction->customerAddress->phone_number ?? '-' }}</div>
                    <div class="addr-text">
                        {{ $transaction->customerAddress->full_address ?? '-' }}
                        @if($transaction->customerAddress && $transaction->customerAddress->regency_name)
                            <br><strong>{{ $transaction->customerAddress->district_name }}, {{ $transaction->customerAddress->regency_name }}, {{ $transaction->customerAddress->province_name }} {{ $transaction->customerAddress->postal_code }}</strong>
                        @endif
                    </div>
                </div>
                <div class="addr-col">
                    <div class="addr-label">Pengirim</div>
                    <div class="addr-name">{{ $storeName }}</div>
                    <div class="addr-phone">{{ $storePhone }}</div>
                    <div class="addr-text">
                        {{ $storeAddress }}<br>
                        <strong>{{ $storeCity }}</strong>
                    </div>
                </div>
            </div>

            <!-- Footer Info -->
            <div class="lbl-footer-info">
                <div class="fi-row">
                    <span class="fi-label">Barang: </span>
                    <span>{{ $itemNames ?: 'Paket Belanja' }}</span>
                </div>
                <div class="fi-row">
                    <span class="fi-label">Catatan: </span>
                    <span>{{ $transaction->notes ?? 'Tidak Ada' }}</span>
                </div>
            </div>

            <!-- Brand -->
            <div class="lbl-brand">
                Pengiriman melalui platform Biteship · www.biteship.com
            </div>

        </div><!-- /.label -->
    </div><!-- /.page-wrapper -->

    <!-- Dynamic @page rule injected by JS -->
    <style id="dyn-page"></style>

    <script>
        // ===========================
        // PRESETS
        // ===========================
        const PRESETS = {
            '7.5x10': { w: 7.5, h: 10 },
            '10x15':  { w: 10,  h: 15 },
            'A6':     { w: 10.5,h: 14.8 },
        };

        // ===========================
        // APPLY ALL STYLES
        // ===========================
        function applyAll() {
            const presetVal = document.getElementById('preset-sel').value;
            const w = parseFloat(document.getElementById('inp-w').value) || 7.5;
            const h = parseFloat(document.getElementById('inp-h').value) || 10.0;
            const m = parseFloat(document.getElementById('inp-m').value) || 0;
            const border = parseInt(document.getElementById('sel-border').value) || 0;
            const fs = parseInt(document.getElementById('sl-fs').value) || 8;

            document.getElementById('fs-val').textContent = fs + 'px';

            const borderVal = border > 0 ? `${border}px solid #000` : 'none';

            const root = document.documentElement;
            root.style.setProperty('--lw', w + 'cm');
            root.style.setProperty('--lh', h + 'cm');
            root.style.setProperty('--lm', m + 'cm');
            root.style.setProperty('--fs', fs + 'px');
            root.style.setProperty('--border', borderVal);

            // Update barcode
            renderBarcode(w, h, m);

            // Inject dynamic @page rule for print
            const pageStyle = `
                @page {
                    size: ${w}cm ${h}cm;
                    margin: 0;
                }
                @media print {
                    .label {
                        width: calc(100vw - ${m * 2}cm) !important;
                        height: calc(100vh - ${m * 2}cm) !important;
                        margin: ${m}cm !important;
                        --border: ${borderVal} !important;
                        --fs: ${fs}px !important;
                    }
                }
            `;
            document.getElementById('dyn-page').textContent = pageStyle;
        }

        function onPresetChange() {
            const val = document.getElementById('preset-sel').value;
            if (PRESETS[val]) {
                document.getElementById('inp-w').value = PRESETS[val].w;
                document.getElementById('inp-h').value = PRESETS[val].h;
            }
            applyAll();
        }

        function onDimChange() {
            const w = parseFloat(document.getElementById('inp-w').value) || 7.5;
            const h = parseFloat(document.getElementById('inp-h').value) || 10;
            const sel = document.getElementById('preset-sel');

            let matched = 'custom';
            for (const [key, p] of Object.entries(PRESETS)) {
                if (Math.abs(p.w - w) < 0.01 && Math.abs(p.h - h) < 0.01) {
                    matched = key;
                    break;
                }
            }
            sel.value = matched;
            applyAll();
        }

        // ===========================
        // BARCODE RENDER
        // ===========================
        function renderBarcode(w, h, m) {
            if (typeof JsBarcode === 'undefined') return;
            const awb = "{{ addslashes($awbNumber) }}";
            const availW = w - (m * 2);
            const bWidth = Math.max(1.0, Math.min(2.2, (availW / 7.5) * 1.7));
            const bHeight = Math.max(28, Math.min(60, (h / 10) * 42));
            try {
                JsBarcode("#barcode", awb, {
                    format: "CODE128",
                    width: bWidth,
                    height: bHeight,
                    displayValue: false,
                    margin: 0,
                });
            } catch (e) {
                console.warn("Barcode error:", e);
            }
        }

        // ===========================
        // PRINT
        // ===========================
        function doPrint() {
            applyAll();
            setTimeout(() => window.print(), 100);
        }

        // ===========================
        // BLUETOOTH PRINT (TSPL)
        // ===========================
        function splitLines(text, maxLen) {
            if (!text) return [];
            const words = String(text).split(' ');
            const lines = [];
            let cur = '';
            words.forEach(w => {
                const test = cur ? cur + ' ' + w : w;
                if (test.length <= maxLen) { cur = test; }
                else { if (cur) lines.push(cur); cur = w; }
            });
            if (cur) lines.push(cur);
            return lines;
        }

        async function printBluetooth() {
            if (typeof navigator.bluetooth === 'undefined') {
                alert('Web Bluetooth API tidak didukung.\n\nPastikan:\n1. Menggunakan browser Chrome/Edge terbaru\n2. Akses via HTTPS atau localhost\n3. Aktifkan flag: chrome://flags/#enable-experimental-web-platform-features');
                return;
            }

            const btn = document.getElementById('btn-bt');
            const origHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '🔵 Connecting...';

            try {
                const device = await navigator.bluetooth.requestDevice({
                    acceptAllDevices: true,
                    optionalServices: [
                        '000018f0-0000-1000-8000-00805f9b34fb',
                        '0000ffe0-0000-1000-8000-00805f9b34fb',
                        '49535343-fe7d-4ae5-8fa9-9fafd205e455',
                    ]
                });

                btn.innerHTML = '🔵 Connecting to ' + device.name + '...';
                const server = await device.gatt.connect();

                // Try multiple common BLE printer service UUIDs
                let service = null;
                const serviceUUIDs = [
                    '000018f0-0000-1000-8000-00805f9b34fb',
                    '0000ffe0-0000-1000-8000-00805f9b34fb',
                    '49535343-fe7d-4ae5-8fa9-9fafd205e455',
                ];
                for (const uuid of serviceUUIDs) {
                    try {
                        service = await server.getPrimaryService(uuid);
                        if (service) break;
                    } catch (_) { /* try next */ }
                }

                if (!service) {
                    // Fallback: get first available service
                    const services = await server.getPrimaryServices();
                    if (services.length > 0) service = services[0];
                }

                if (!service) throw new Error('Tidak ada service printer ditemukan.');

                const characteristics = await service.getCharacteristics();
                const writeChar = characteristics.find(c => c.properties.write || c.properties.writeWithoutResponse);

                if (!writeChar) throw new Error('Tidak ada writable characteristic ditemukan.');

                btn.innerHTML = '🖨️ Sending...';

                // Build TSPL data
                const w = parseFloat(document.getElementById('inp-w').value) || 7.5;
                const h = parseFloat(document.getElementById('inp-h').value) || 10;
                const wMm = Math.round(w * 10);
                const hMm = Math.round(h * 10);

                const receiverName  = "{{ addslashes($transaction->customerAddress->receiver_name ?? '-') }}";
                const receiverPhone = "{{ addslashes($transaction->customerAddress->phone_number ?? '-') }}";
                const receiverAddr  = "{{ addslashes(trim(($transaction->customerAddress->full_address ?? '') . ' ' . ($transaction->customerAddress->district_name ?? '') . ' ' . ($transaction->customerAddress->regency_name ?? '') . ' ' . ($transaction->customerAddress->province_name ?? '') . ' ' . ($transaction->customerAddress->postal_code ?? ''))) }}";
                const senderName    = "{{ addslashes($storeName) }}";
                const senderPhone   = "{{ addslashes($storePhone) }}";
                const senderAddr    = "{{ addslashes($storeAddress . ' ' . $storeCity) }}";
                const itemsStr      = "{{ addslashes($itemNames ?: 'Paket Belanja') }}";
                const notes         = "{{ addslashes($transaction->notes ?? 'Tidak Ada') }}";
                const awb           = "{{ addslashes($awbNumber) }}";
                const courier       = "{{ $courierName }}";
                const service_type  = "{{ $serviceType }}";
                const routing       = "{{ $routingCode ?? 'RC' }}";
                const qty           = "{{ $totalQty }}";
                const weight        = "{{ number_format($realWeightKg, 1) }}";

                const pxW = Math.round((wMm / 25.4) * 203);
                const pxH = Math.round((hMm / 25.4) * 203);

                const addrLines   = splitLines(receiverAddr, 42);
                const senderLines = splitLines(senderAddr, 42);
                const itemLines   = splitLines(itemsStr, 42);

                let tspl = '';
                tspl += `SIZE ${wMm} mm, ${hMm} mm\r\n`;
                tspl += `GAP 3 mm, 0 mm\r\n`;
                tspl += `DIRECTION 1\r\n`;
                tspl += `REFERENCE 0,0\r\n`;
                tspl += `CLS\r\n`;

                // Border box
                tspl += `BOX 8,8,${pxW - 8},${pxH - 8},4\r\n`;

                // Header
                tspl += `TEXT 20,20,"4",0,1,1,"KURIR: ${courier}"\r\n`;
                tspl += `TEXT 20,60,"3",0,1,1,"LAYANAN: ${service_type}"\r\n`;
                tspl += `BAR 8,95,${pxW - 16},3\r\n`;

                // Barcode
                tspl += `BARCODE 20,105,"128",70,1,0,2,2,"${awb}"\r\n`;
                tspl += `TEXT 20,185,"2",0,1,1,"RESI: ${awb}"\r\n`;
                tspl += `BAR 8,210,${pxW - 16},3\r\n`;

                // Routing & qty
                const halfW = Math.round(pxW / 2);
                tspl += `REVERSE 8,218,${halfW - 8},${halfW - 8 < 240 ? 60 : 60}\r\n`;
                tspl += `TEXT 20,228,"5",0,1,1,"${routing}"\r\n`;
                tspl += `TEXT ${halfW + 10},225,"2",0,1,1,"Qty : ${qty} Pcs"\r\n`;
                tspl += `TEXT ${halfW + 10},255,"2",0,1,1,"Berat : ${weight} Kg"\r\n`;
                tspl += `BAR 8,290,${pxW - 16},3\r\n`;

                // Receiver
                let y = 305;
                tspl += `TEXT 20,${y},"3",0,1,1,"PENERIMA:"\r\n`; y += 40;
                tspl += `TEXT 20,${y},"3",0,1,1,"${receiverName}"\r\n`; y += 40;
                tspl += `TEXT 20,${y},"2",0,1,1,"Tlp: ${receiverPhone}"\r\n`; y += 30;
                addrLines.slice(0, 3).forEach(line => {
                    tspl += `TEXT 20,${y},"2",0,1,1,"${line}"\r\n`; y += 28;
                });
                tspl += `BAR 8,${y + 5},${pxW - 16},3\r\n`; y += 20;

                // Sender
                tspl += `TEXT 20,${y},"3",0,1,1,"PENGIRIM:"\r\n`; y += 40;
                tspl += `TEXT 20,${y},"3",0,1,1,"${senderName}"\r\n`; y += 40;
                tspl += `TEXT 20,${y},"2",0,1,1,"Tlp: ${senderPhone}"\r\n`; y += 30;
                senderLines.slice(0, 2).forEach(line => {
                    tspl += `TEXT 20,${y},"2",0,1,1,"${line}"\r\n`; y += 28;
                });
                tspl += `BAR 8,${y + 5},${pxW - 16},3\r\n`; y += 18;

                // Items & notes
                tspl += `TEXT 20,${y},"2",0,1,1,"BARANG: ${itemLines[0] || ''}"\r\n`; y += 28;
                if (itemLines[1]) { tspl += `TEXT 20,${y},"2",0,1,1,"${itemLines[1]}"\r\n`; y += 28; }
                tspl += `TEXT 20,${y},"2",0,1,1,"CATATAN: ${notes}"\r\n`; y += 30;

                // Footer
                tspl += `TEXT 20,${pxH - 40},"1",0,1,1,"Pengiriman melalui Biteship - www.biteship.com"\r\n`;
                tspl += `PRINT 1,1\r\n`;

                // Send in chunks
                const encoder = new TextEncoder();
                const bytes = encoder.encode(tspl);
                const chunkSize = 512;

                for (let i = 0; i < bytes.length; i += chunkSize) {
                    const chunk = bytes.slice(i, i + chunkSize);
                    if (writeChar.properties.writeWithoutResponse) {
                        await writeChar.writeValueWithoutResponse(chunk);
                    } else {
                        await writeChar.writeValue(chunk);
                    }
                    await new Promise(r => setTimeout(r, 30));
                }

                btn.innerHTML = origHtml;
                btn.disabled = false;
                alert('✅ Label berhasil dikirim ke printer Bluetooth!\n\nJika tidak ada cetakan, pastikan printer dalam mode TSPL/TSC.');

            } catch (err) {
                btn.innerHTML = origHtml;
                btn.disabled = false;
                console.error('Bluetooth error:', err);

                let msg = err.message || String(err);
                let tip = '';

                if (msg.includes('User cancelled')) {
                    return; // User closed dialog, no alert needed
                }
                if (msg.includes('GATT') || msg.includes('connect') || msg.includes('Unsupported')) {
                    tip = '\n\n💡 Tip: Printer Bluetooth Classic tidak bisa diakses langsung via browser.\n'
                        + 'Gunakan cara alternatif:\n'
                        + '1. Pair printer via System Settings > Bluetooth\n'
                        + '2. Tambah printer di System Settings > Printers\n'
                        + '3. Gunakan tombol "Cetak Label" dan pilih printer tersebut';
                }
                alert('❌ Gagal cetak Bluetooth:\n' + msg + tip);
            }
        }

        // ===========================
        // INIT
        // ===========================
        window.addEventListener('DOMContentLoaded', function () {
            applyAll();
        });

        window.addEventListener('load', function () {
            applyAll();
            // Auto-open print dialog after brief delay
            setTimeout(() => window.print(), 500);
        });
    </script>
</body>
</html>
