<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ ($type ?? 'kitchen') === 'kitchen' ? 'Tiket Dapur' : 'Struk Kasir' }} - {{ $transaction->transaction_number }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Courier+Prime:ital,wght@0,400;0,700;1,400&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>

    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --paper-width-58: 58mm;
            --print-width-58: 48mm;
            --paper-width-80: 80mm;
            --print-width-80: 72mm;
            --font-mono: 'Courier Prime', 'Courier New', Courier, monospace;
            --font-ui: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            font-family: var(--font-mono);
            background: #e2e8f0;
            color: #000;
            padding: 70px 20px 40px;
            font-size: 12px;
            line-height: 1.25;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* ===================================================
           CONTROL BAR (SCREEN ONLY)
           =================================================== */
        .ctrl-bar {
            position: fixed;
            top: 0; left: 0; right: 0;
            background: #0f172a;
            color: #f8fafc;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 9999;
            font-family: var(--font-ui);
        }
        .ctrl-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .ctrl-title {
            font-weight: 700;
            font-size: 14px;
            letter-spacing: -0.2px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .ctrl-title span {
            color: #f97316;
        }
        .segmented-ctrl {
            display: inline-flex;
            background: #1e293b;
            padding: 3px;
            border-radius: 8px;
            border: 1px solid #334155;
        }
        .segmented-btn {
            background: transparent;
            border: none;
            color: #94a3b8;
            padding: 5px 12px;
            font-size: 12px;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.15s ease;
            font-family: var(--font-ui);
        }
        .segmented-btn.active {
            background: #f97316;
            color: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            border: none;
            transition: all 0.15s ease;
            font-family: var(--font-ui);
        }
        .btn-print {
            background: #10b981;
            color: #fff;
        }
        .btn-print:hover {
            background: #059669;
        }
        .btn-close {
            background: #334155;
            color: #cbd5e1;
        }
        .btn-close:hover {
            background: #475569;
            color: #fff;
        }

        /* ===================================================
           RECEIPT PAPER WRAPPER & CARD
           =================================================== */
        .paper-viewport {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: calc(100vh - 120px);
        }
        .receipt-card {
            background: #ffffff;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            color: #000;
            padding: 12px 10px;
            transition: width 0.2s ease;
            position: relative;
        }

        /* Format sizing */
        .receipt-card.format-58mm {
            width: var(--paper-width-58);
            max-width: var(--paper-width-58);
            font-size: 11px;
        }
        .receipt-card.format-80mm {
            width: var(--paper-width-80);
            max-width: var(--paper-width-80);
            font-size: 12.5px;
            padding: 16px 14px;
        }

        /* Visual tear edges */
        .receipt-card::before, .receipt-card::after {
            content: "";
            position: absolute;
            left: 0; right: 0;
            height: 6px;
            background-size: 12px 6px;
        }
        .receipt-card::before {
            top: -6px;
            background-image: radial-gradient(circle at 6px 6px, transparent 6px, #ffffff 6px);
        }
        .receipt-card::after {
            bottom: -6px;
            background-image: radial-gradient(circle at 6px 0, transparent 6px, #ffffff 6px);
        }

        /* Typography & Alignment */
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .font-bold { font-weight: 700; }
        .font-normal { font-weight: 400; }
        .uppercase { text-transform: uppercase; }

        .line-divider {
            border-top: 1px dashed #000;
            margin: 6px 0;
        }
        .line-double {
            border-top: 2px solid #000;
            margin: 6px 0;
        }

        .header-resto {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 2px;
            line-height: 1.2;
        }
        .format-80mm .header-resto {
            font-size: 16px;
        }
        .header-sub {
            font-size: 10px;
            color: #222;
            margin-bottom: 2px;
        }
        .badge-banner {
            border: 2px solid #000;
            padding: 4px;
            margin: 6px 0;
            font-weight: 700;
            text-align: center;
            font-size: 12px;
            letter-spacing: 0.5px;
        }
        .format-80mm .badge-banner {
            font-size: 14px;
            padding: 6px;
        }

        .grid-row {
            display: flex;
            justify-content: space-between;
            margin: 2px 0;
            word-break: break-word;
        }
        .grid-label {
            flex-shrink: 0;
            color: #111;
        }
        .grid-val {
            text-align: right;
            font-weight: 700;
        }

        /* Items Section */
        .item-row {
            margin: 6px 0;
        }
        .item-main {
            display: flex;
            justify-content: space-between;
            font-weight: 700;
        }
        .item-qty-title {
            display: flex;
            gap: 4px;
            flex: 1;
        }
        .item-qty {
            font-size: 12px;
            flex-shrink: 0;
        }
        .format-80mm .item-qty {
            font-size: 14px;
        }
        .item-subtotal {
            text-align: right;
            margin-left: 6px;
        }
        .item-modifier {
            font-size: 10px;
            padding-left: 16px;
            color: #222;
            margin-top: 1px;
            line-height: 1.2;
        }
        .format-80mm .item-modifier {
            font-size: 11px;
        }
        .item-note-box {
            font-size: 10px;
            margin-top: 2px;
            padding: 2px 4px;
            border-left: 2px solid #000;
            margin-left: 14px;
            font-style: italic;
            font-weight: 700;
        }

        .qr-wrapper {
            display: flex;
            justify-content: center;
            margin: 8px 0 4px;
        }
        .barcode-svg {
            max-width: 100%;
            height: auto;
        }

        .footer-text {
            font-size: 9.5px;
            text-align: center;
            margin-top: 6px;
            line-height: 1.3;
        }

        /* Mode visibility */
        .mode-kitchen, .mode-cashier {
            display: none;
        }
        body[data-type="kitchen"] .mode-kitchen {
            display: block;
        }
        body[data-type="cashier"] .mode-cashier {
            display: block;
        }

        /* ===================================================
           PRINT MEDIA SPECIFICATIONS
           =================================================== */
        @media print {
            @page {
                margin: 0;
                size: auto;
            }
            body {
                background: #ffffff !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .ctrl-bar {
                display: none !important;
            }
            .paper-viewport {
                min-height: auto !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .receipt-card {
                box-shadow: none !important;
                border: none !important;
                margin: 0 auto !important;
                padding: 2mm 3mm !important;
            }
            .receipt-card::before, .receipt-card::after {
                display: none !important;
            }
            .receipt-card.format-58mm {
                width: var(--print-width-58) !important;
                max-width: var(--print-width-58) !important;
            }
            .receipt-card.format-80mm {
                width: var(--print-width-80) !important;
                max-width: var(--print-width-80) !important;
            }
        }
    </style>
</head>
<body data-type="{{ $type ?? 'kitchen' }}">

    <!-- TOP CONTROL BAR FOR SCREEN VIEW -->
    <header class="ctrl-bar">
        <div class="ctrl-group">
            <div class="ctrl-title">
                <span>🖨️</span> THERMAL PRINTER POS
            </div>
            <!-- Type Selector -->
            <div class="segmented-ctrl">
                <button type="button" class="segmented-btn {{ ($type ?? 'kitchen') === 'kitchen' ? 'active' : '' }}" onclick="switchType('kitchen')">
                    🍳 Tiket Dapur
                </button>
                <button type="button" class="segmented-btn {{ ($type ?? 'kitchen') === 'cashier' ? 'active' : '' }}" onclick="switchType('cashier')">
                    🧾 Struk Kasir
                </button>
            </div>
            <!-- Format Size Selector -->
            <div class="segmented-ctrl">
                <button type="button" class="segmented-btn {{ ($format ?? '58mm') === '58mm' ? 'active' : '' }}" onclick="switchFormat('58mm')">
                    58 mm
                </button>
                <button type="button" class="segmented-btn {{ ($format ?? '58mm') === '80mm' ? 'active' : '' }}" onclick="switchFormat('80mm')">
                    80 mm
                </button>
            </div>
        </div>

        <div class="ctrl-group">
            <button type="button" class="btn-action btn-print" onclick="window.print()">
                🖨️ Cetak Struk
            </button>
            <button type="button" class="btn-action btn-close" onclick="window.close() || history.back()">
                ✖ Tutup
            </button>
        </div>
    </header>

    @php
        $restoName = $transaction->restoProfile?->resto_name ?? $transaction->resto?->store_name ?? $storeName;
        $restoAddress = $transaction->restoProfile?->address ?? $transaction->resto?->store_address;
        $restoPhone = $transaction->restoProfile?->phone_number ?? $transaction->resto?->phone_number;
        $customerName = $transaction->customer_name ?? $transaction->user?->name ?? 'Pelanggan';
        $customerPhone = $transaction->customer_phone ?? $transaction->user?->phone_number;

        $fulfillment = $transaction->fulfillment_type ?? ($transaction->shipping_courier === 'self_pickup' ? 'self_pickup' : 'store_courier');
        $pickupType = $transaction->pickup_type ?? 'takeaway';
        $tableNumber = $transaction->table_number;

        $scheduledDate = $transaction->scheduled_date;
        $scheduledTime = $transaction->scheduled_time_slot;

        // Label Layanan
        if ($fulfillment === 'dine_in' || $pickupType === 'dine_in') {
            $serviceLabel = 'MAKAN DI TEMPAT (DINE-IN)';
            $tableBadge = $tableNumber ? "MEJA: {$tableNumber}" : 'MEJA: -';
        } elseif ($fulfillment === 'self_pickup' || $pickupType === 'takeaway') {
            $serviceLabel = 'BUNGKUS (TAKEAWAY)';
            $tableBadge = 'AMBIL DI KASIR';
        } else {
            $serviceLabel = 'KURIR RESTO (DELIVERY)';
            $tableBadge = 'PENGANTARAN KE ALAMAT';
        }
    @endphp

    <div class="paper-viewport">
        <main id="receiptCard" class="receipt-card {{ ($format ?? '58mm') === '80mm' ? 'format-80mm' : 'format-58mm' }}">

            <!-- ============================================================== -->
            <!-- MODE 1: TIKET PESANAN DAPUR (KITCHEN SLIP)                     -->
            <!-- ============================================================== -->
            <section class="mode-kitchen">
                <div class="text-center">
                    <div class="header-resto uppercase">{{ $restoName }}</div>
                    <div class="header-sub">DAPUR & KITCHEN DISPLAY</div>
                </div>

                <div class="line-double"></div>

                <div class="badge-banner">
                    *** {{ $serviceLabel }} ***
                    @if($tableNumber && ($fulfillment === 'dine_in' || $pickupType === 'dine_in'))
                        <div style="font-size: 15px; margin-top: 2px;">★ {{ $tableBadge }} ★</div>
                    @endif
                </div>

                <div class="grid-row">
                    <span class="grid-label">No. Tiket:</span>
                    <span class="grid-val">#{{ substr($transaction->transaction_number, -8) }}</span>
                </div>
                <div class="grid-row">
                    <span class="grid-label">ID Lengkap:</span>
                    <span class="grid-val" style="font-size: 10px;">{{ $transaction->transaction_number }}</span>
                </div>
                <div class="grid-row">
                    <span class="grid-label">Waktu Pesan:</span>
                    <span class="grid-val">{{ $transaction->created_at->format('d/m/Y H:i') }} WIB</span>
                </div>
                <div class="grid-row">
                    <span class="grid-label">Pelanggan:</span>
                    <span class="grid-val">{{ $customerName }}</span>
                </div>

                @if($scheduledTime)
                    <div class="grid-row" style="margin-top: 3px; background: #000; color: #fff; padding: 2px 4px;">
                        <span class="grid-label" style="color: #fff; font-weight: 700;">TARGET SIAP:</span>
                        <span class="grid-val" style="color: #fff;">{{ $scheduledTime }} WIB</span>
                    </div>
                @endif

                <div class="line-divider"></div>
                <div class="text-center font-bold" style="letter-spacing: 1px;">DAFTAR MENU YANG HARUS DIMASAK</div>
                <div class="line-divider"></div>

                @php $totalKitchenQty = 0; @endphp
                @foreach($transaction->items as $item)
                    @php
                        $qty = $item->quantity;
                        $totalKitchenQty += $qty;
                        $name = $item->product_name ?? $item->product?->name ?? 'Menu';

                        // Parse notes / modifiers
                        $modifiers = [];
                        $itemNote = null;
                        if (!empty($item->selected_modifiers) && is_array($item->selected_modifiers)) {
                            foreach ($item->selected_modifiers as $sm) {
                                $extra = (!empty($sm['additional_price']) && $sm['additional_price'] > 0) ? ' (+Rp '.number_format($sm['additional_price'], 0, ',', '.').')' : '';
                                $group = !empty($sm['group_name']) ? "{$sm['group_name']}: " : '';
                                $modifiers[] = "{$group}{$sm['name']}{$extra}";
                            }
                        }
                        if (!empty($item->note)) {
                            if (str_contains($item->note, 'Catatan:')) {
                                $parts = explode('Catatan:', $item->note);
                                if (empty($modifiers) && !empty(trim($parts[0], ' |'))) {
                                    $modParts = explode('|', trim($parts[0], ' |'));
                                    foreach ($modParts as $mp) {
                                        if (trim($mp)) $modifiers[] = trim($mp);
                                    }
                                }
                                $itemNote = trim($parts[1]);
                            } elseif (empty($modifiers)) {
                                $itemNote = $item->note;
                            }
                        }
                    @endphp

                    <div class="item-row">
                        <div class="item-main">
                            <div class="item-qty-title">
                                <span class="item-qty font-bold">[ {{ $qty }}x ]</span>
                                <span class="font-bold">{{ strtoupper($name) }}</span>
                            </div>
                        </div>

                        @if($item->variant_name)
                            <div class="item-modifier">• Varian: {{ $item->variant_name }}</div>
                        @endif

                        @foreach($modifiers as $mod)
                            <div class="item-modifier">• {{ $mod }}</div>
                        @endforeach

                        @if($itemNote)
                            <div class="item-note-box">
                                ★ KOKI: {{ $itemNote }}
                            </div>
                        @endif
                    </div>
                @endforeach

                <div class="line-divider"></div>

                <div class="grid-row font-bold">
                    <span>TOTAL PORSI:</span>
                    <span>{{ $totalKitchenQty }} Porsi</span>
                </div>

                @if($transaction->notes)
                    <div class="item-note-box" style="margin-left: 0; margin-top: 6px; padding: 4px; border: 1px solid #000;">
                        ★ CATATAN PESANAN:<br>{{ $transaction->notes }}
                    </div>
                @endif

                <div class="line-double"></div>

                <div class="qr-wrapper">
                    <svg id="barcodeKitchen" class="barcode-svg"></svg>
                </div>

                <div class="footer-text font-bold">
                    *** SEGERA SIAPKAN DENGAN BAIK ***<br>
                    Status: {{ strtoupper($transaction->kitchen_status ?: 'PESANAN MASUK') }}
                </div>
            </section>


            <!-- ============================================================== -->
            <!-- MODE 2: STRUK KASIR & PELANGGAN (CASHIER RECEIPT)              -->
            <!-- ============================================================== -->
            <section class="mode-cashier">
                <div class="text-center">
                    <div class="header-resto uppercase">{{ $restoName }}</div>
                    @if($restoAddress)
                        <div class="header-sub">{{ $restoAddress }}</div>
                    @endif
                    @if($restoPhone)
                        <div class="header-sub">Telp/WA: {{ $restoPhone }}</div>
                    @endif
                </div>

                <div class="line-double"></div>

                <div class="grid-row">
                    <span class="grid-label">No. Nota:</span>
                    <span class="grid-val">{{ $transaction->transaction_number }}</span>
                </div>
                <div class="grid-row">
                    <span class="grid-label">Tanggal :</span>
                    <span class="grid-val">{{ $transaction->created_at->format('d/m/Y H:i') }}</span>
                </div>
                <div class="grid-row">
                    <span class="grid-label">Kasir   :</span>
                    <span class="grid-val">Kasir Resto</span>
                </div>
                <div class="grid-row">
                    <span class="grid-label">Pelanggan:</span>
                    <span class="grid-val">{{ $customerName }}</span>
                </div>
                @if($customerPhone)
                    <div class="grid-row">
                        <span class="grid-label">No. HP  :</span>
                        <span class="grid-val">{{ $customerPhone }}</span>
                    </div>
                @endif

                <div class="grid-row" style="margin-top: 2px;">
                    <span class="grid-label">Layanan :</span>
                    <span class="grid-val">{{ $serviceLabel }}</span>
                </div>
                @if($tableNumber && ($fulfillment === 'dine_in' || $pickupType === 'dine_in'))
                    <div class="grid-row">
                        <span class="grid-label">No. Meja:</span>
                        <span class="grid-val">MEJA {{ $tableNumber }}</span>
                    </div>
                @endif

                <div class="line-divider"></div>
                <div class="grid-row font-bold" style="font-size: 10px;">
                    <span>ITEM PESANAN</span>
                    <span>HARGA / TOTAL</span>
                </div>
                <div class="line-divider"></div>

                @php $cashierSubtotal = 0; @endphp
                @foreach($transaction->items as $item)
                    @php
                        $qty = $item->quantity;
                        $price = (float) ($item->harga_akhir ?? $item->harga_jual ?? 0);
                        $itemSubtotal = (float) ($item->subtotal ?? ($price * $qty));
                        $cashierSubtotal += $itemSubtotal;
                        $name = $item->product_name ?? $item->product?->name ?? 'Menu';

                        // Parse notes / modifiers
                        $modifiers = [];
                        $itemNote = null;
                        if (!empty($item->selected_modifiers) && is_array($item->selected_modifiers)) {
                            foreach ($item->selected_modifiers as $sm) {
                                $extra = (!empty($sm['additional_price']) && $sm['additional_price'] > 0) ? ' (+Rp '.number_format($sm['additional_price'], 0, ',', '.').')' : '';
                                $group = !empty($sm['group_name']) ? "{$sm['group_name']}: " : '';
                                $modifiers[] = "{$group}{$sm['name']}{$extra}";
                            }
                        }
                        if (!empty($item->note)) {
                            if (str_contains($item->note, 'Catatan:')) {
                                $parts = explode('Catatan:', $item->note);
                                if (empty($modifiers) && !empty(trim($parts[0], ' |'))) {
                                    $modParts = explode('|', trim($parts[0], ' |'));
                                    foreach ($modParts as $mp) {
                                        if (trim($mp)) $modifiers[] = trim($mp);
                                    }
                                }
                                $itemNote = trim($parts[1]);
                            } elseif (empty($modifiers)) {
                                $itemNote = $item->note;
                            }
                        }
                    @endphp

                    <div class="item-row">
                        <div class="item-main">
                            <div class="item-qty-title">
                                <span class="font-bold">{{ $qty }}x {{ $name }}</span>
                            </div>
                            <span class="item-subtotal font-bold">Rp {{ number_format($itemSubtotal, 0, ',', '.') }}</span>
                        </div>
                        <div class="item-modifier">
                            @ {{ number_format($price, 0, ',', '.') }}
                            @if($item->variant_name)
                                • {{ $item->variant_name }}
                            @endif
                        </div>

                        @foreach($modifiers as $mod)
                            <div class="item-modifier">+ {{ $mod }}</div>
                        @endforeach

                        @if($itemNote)
                            <div class="item-note-box">
                                Ket: {{ $itemNote }}
                            </div>
                        @endif
                    </div>
                @endforeach

                <div class="line-divider"></div>

                <div class="grid-row">
                    <span class="grid-label">Subtotal Menu:</span>
                    <span class="grid-val font-normal">Rp {{ number_format($transaction->total_amount ?? $cashierSubtotal, 0, ',', '.') }}</span>
                </div>

                @if((float)$transaction->shipping_cost > 0)
                    <div class="grid-row">
                        <span class="grid-label">Ongkos Kirim:</span>
                        <span class="grid-val font-normal">Rp {{ number_format($transaction->shipping_cost, 0, ',', '.') }}</span>
                    </div>
                @endif

                @if((float)($transaction->discount_amount ?? 0) > 0)
                    <div class="grid-row">
                        <span class="grid-label">Diskon:</span>
                        <span class="grid-val font-normal">-Rp {{ number_format($transaction->discount_amount, 0, ',', '.') }}</span>
                    </div>
                @endif

                @if((float)($transaction->service_fee ?? 0) > 0)
                    <div class="grid-row">
                        <span class="grid-label">Biaya Layanan:</span>
                        <span class="grid-val font-normal">Rp {{ number_format($transaction->service_fee, 0, ',', '.') }}</span>
                    </div>
                @endif

                <div class="line-divider"></div>

                <div class="grid-row" style="font-size: 13px;">
                    <span class="grid-label font-bold">TOTAL BAYAR:</span>
                    <span class="grid-val font-bold">Rp {{ number_format($transaction->grand_total, 0, ',', '.') }}</span>
                </div>

                <div class="line-divider"></div>

                <div class="grid-row">
                    <span class="grid-label">Pembayaran:</span>
                    <span class="grid-val">{{ strtoupper($transaction->paymentMethod?->name ?? 'TUNAI / QRIS') }}</span>
                </div>
                <div class="grid-row">
                    <span class="grid-label">Status Bayar:</span>
                    <span class="grid-val">
                        @if($transaction->payment_status === 'lunas' || $transaction->status === 'selesai' || $transaction->status === 'diproses')
                            [ LUNAS ]
                        @else
                            [ {{ strtoupper($transaction->payment_status ?: 'BELUM LUNAS') }} ]
                        @endif
                    </span>
                </div>

                @if($transaction->notes)
                    <div class="item-note-box" style="margin-left: 0; margin-top: 6px;">
                        Catatan: {{ $transaction->notes }}
                    </div>
                @endif

                <div class="line-double"></div>

                <div class="qr-wrapper">
                    <div id="qrcodeCashier"></div>
                </div>

                <div class="footer-text">
                    Terima kasih atas pesanan Anda!<br>
                    Silakan simpan struk ini sebagai bukti transaksi.<br>
                    Lacak Pesanan: <b>{{ url('/cek-pesanan?trx='.$transaction->transaction_number) }}</b>
                </div>
            </section>

        </main>
    </div>

    <script>
        const trxNumber = @json($transaction->transaction_number);
        const trackingUrl = @json(url('/cek-pesanan?trx='.$transaction->transaction_number));
        const autoPrint = @json((bool) ($autoPrint ?? true));

        // Render Barcode for Kitchen Slip
        try {
            JsBarcode("#barcodeKitchen", trxNumber, {
                format: "CODE128",
                width: 1.4,
                height: 35,
                displayValue: true,
                fontSize: 10,
                font: "Courier Prime",
                margin: 2
            });
        } catch (e) {
            console.error("Barcode generation error:", e);
        }

        // Render QR Code for Cashier Slip
        try {
            const qrEl = document.getElementById("qrcodeCashier");
            if (qrEl) {
                new QRCode(qrEl, {
                    text: trackingUrl,
                    width: 70,
                    height: 70,
                    colorDark : "#000000",
                    colorLight : "#ffffff",
                    correctLevel : QRCode.CorrectLevel.M
                });
            }
        } catch (e) {
            console.error("QRCode generation error:", e);
        }

        // Switch between Kitchen and Cashier receipts dynamically
        function switchType(type) {
            document.body.setAttribute('data-type', type);
            document.querySelectorAll('.ctrl-bar .segmented-ctrl:first-of-type .segmented-btn').forEach(btn => {
                btn.classList.toggle('active', btn.textContent.toLowerCase().includes(type === 'kitchen' ? 'dapur' : 'kasir'));
            });
            document.title = (type === 'kitchen' ? 'Tiket Dapur' : 'Struk Kasir') + ' - ' + trxNumber;
        }

        // Switch between 58mm and 80mm paper formats dynamically
        function switchFormat(format) {
            const card = document.getElementById('receiptCard');
            if (format === '80mm') {
                card.classList.remove('format-58mm');
                card.classList.add('format-80mm');
            } else {
                card.classList.remove('format-80mm');
                card.classList.add('format-58mm');
            }
            document.querySelectorAll('.ctrl-bar .segmented-ctrl:last-of-type .segmented-btn').forEach(btn => {
                btn.classList.toggle('active', btn.textContent.includes(format));
            });
        }

        // Auto print upon load if requested
        window.addEventListener('DOMContentLoaded', () => {
            if (autoPrint) {
                setTimeout(() => {
                    window.print();
                }, 400);
            }
        });
    </script>
</body>
</html>

