<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Label Pengiriman Biteship - {{ $transaction->transaction_number }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            color: #000000;
            margin: 0;
            padding: 10px;
            background-color: #ffffff;
            font-size: 11px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .label-container {
            width: 100%;
            max-width: 480px;
            margin: 0 auto;
            border: 2px solid #000;
            box-sizing: border-box;
            background-color: #fff;
        }
        
        /* Top Row: Courier and Platform Logos */
        .top-header {
            display: grid;
            grid-template-columns: 1fr 1.5fr 1fr;
            border-bottom: 2px solid #000;
            align-items: center;
            padding: 8px;
            text-align: center;
        }
        .courier-logo-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 14px;
            border-right: 1px solid #000;
            height: 100%;
            padding-right: 4px;
        }
        .courier-name-badge {
            background-color: #000;
            color: #fff;
            padding: 3px 6px;
            border-radius: 3px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 4px;
        }
        .platform-logo-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .platform-logo-text {
            font-weight: 800;
            font-size: 18px;
            letter-spacing: -0.5px;
            color: #000;
            display: flex;
            align-items: center;
            gap: 2px;
        }
        .platform-logo-text span {
            color: #00bfa6;
        }
        .platform-url {
            font-size: 9px;
            color: #555;
            margin-top: 2px;
        }
        .store-logo-section {
            border-left: 1px solid #000;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            padding-left: 4px;
            word-break: break-all;
        }

        /* Barcode Area */
        .barcode-section {
            border-bottom: 2px solid #000;
            padding: 8px 0;
            text-align: center;
        }
        #barcode {
            width: 85%;
            max-height: 65px;
            margin: 0 auto;
        }
        .awb-text {
            font-family: monospace;
            font-size: 13px;
            font-weight: 700;
            margin-top: 4px;
            letter-spacing: 1px;
        }

        /* COD Section */
        .cod-section {
            border-bottom: 2px solid #000;
            padding: 6px;
            text-align: center;
            font-size: 13px;
            font-weight: 800;
            background-color: #f3f4f6;
        }

        /* Service Type Section */
        .service-section {
            border-bottom: 2px solid #000;
            padding: 6px;
            text-align: center;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }

        /* Routing Code & Qty/Weight Section */
        .routing-qty-section {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            border-bottom: 2px solid #000;
        }
        .routing-box {
            padding: 10px;
            font-weight: 800;
            font-size: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-right: 2px solid #000;
            background-color: #000;
            color: #fff;
            text-align: center;
            text-transform: uppercase;
        }
        .qty-weight-box {
            padding: 6px 10px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 4px;
            font-size: 11px;
        }
        .qty-weight-row {
            display: flex;
            justify-content: space-between;
            font-weight: 600;
        }
        .qty-weight-row span:last-child {
            font-weight: 800;
        }

        /* Address Grid */
        .address-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            border-bottom: 2px solid #000;
            min-height: 120px;
        }
        .receiver-column {
            padding: 8px;
            border-right: 1px solid #000;
        }
        .sender-column {
            padding: 8px;
        }
        .address-title {
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            color: #444;
            margin-bottom: 4px;
            border-bottom: 1px dashed #ccc;
            padding-bottom: 2px;
        }
        .address-name {
            font-size: 12px;
            font-weight: 800;
            margin-bottom: 2px;
        }
        .address-phone {
            font-weight: 700;
            margin-bottom: 4px;
        }
        .address-text {
            font-size: 10px;
            line-height: 1.3;
            color: #111;
        }

        /* Footer Info Section */
        .footer-info-section {
            padding: 8px;
            border-bottom: 2px solid #000;
            font-size: 10.5px;
            line-height: 1.4;
        }
        .footer-info-row {
            margin: 4px 0;
        }
        .footer-info-label {
            font-weight: 700;
            text-transform: uppercase;
        }

        /* Branding footer */
        .branding-footer {
            padding: 6px;
            text-align: center;
            font-size: 9px;
            color: #555;
            font-weight: 500;
        }

        /* Packing list toggle / print buttons */
        .print-btn-bar {
            max-width: 480px;
            margin: 0 auto 10px auto;
            display: flex;
            justify-content: flex-end;
        }
        .print-btn {
            background-color: #000;
            color: #fff;
            border: none;
            padding: 8px 18px;
            font-size: 12px;
            font-weight: 700;
            border-radius: 4px;
            cursor: pointer;
            text-transform: uppercase;
        }
        .print-btn:hover {
            opacity: 0.9;
        }

        @media print {
            body {
                padding: 0;
                margin: 0;
            }
            .no-print {
                display: none !important;
            }
            .label-container {
                max-width: 100%;
                width: 100%;
                border: 2px solid #000;
            }
            @page {
                size: portrait;
                margin: 0;
            }
        }
    </style>
</head>
<body>

    <div class="print-btn-bar no-print">
        <button class="print-btn" onclick="window.print()">Cetak Label</button>
    </div>

    <div class="label-container">
        <!-- Top Header: Courier and Biteship Branding -->
        <div class="top-header">
            <div class="courier-logo-section">
                <span style="font-size: 10px; color: #555;">KURIR</span>
                <span class="courier-name-badge">{{ strtoupper($transaction->courier_name ?? $transaction->shipping_courier ?? 'KURIR') }}</span>
            </div>
            
            <div class="platform-logo-section">
                <div class="platform-logo-text">
                    biteship
                </div>
                <div class="platform-url">www.biteship.com</div>
            </div>

            <div class="store-logo-section">
                {{ $storeName }}
            </div>
        </div>

        <!-- Barcode Area -->
        <div class="barcode-section">
            <svg id="barcode"></svg>
            <div class="awb-text">Nomor Resi - {{ $transaction->tracking_number ?? 'BELUM MASUK RESI' }}</div>
        </div>

        <!-- COD Info (Only shows if payment method is COD) -->
        @if(str_contains(strtolower($transaction->paymentMethod->name ?? ''), 'cod'))
            <div class="cod-section">
                Nilai COD: Rp. {{ number_format($transaction->grand_total, 0, ',', '.') }}
            </div>
        @endif

        <!-- Service Type -->
        <div class="service-section">
            Jenis Layanan - {{ strtoupper(preg_replace('/\[.*?\]\s*/', '', $transaction->shipping_service ?? 'REG')) }}
        </div>

        <!-- Routing Code & Qty/Weight Section -->
        <div class="routing-qty-section">
            <div class="routing-box">
                {{ $routingCode ?? 'NO-RC' }}
            </div>
            <div class="qty-weight-box">
                @php
                    $totalQty = $transaction->items->sum('quantity');
                    $realWeightGrams = $transaction->items->sum(function($item) {
                        return ($item->productVariant->weight ?? $item->product->weight ?? 1000) * $item->quantity;
                    });
                    $realWeightKg = $realWeightGrams / 1000;
                @endphp
                <div class="qty-weight-row">
                    <span>Quantity</span>
                    <span>: {{ $totalQty }} Pcs</span>
                </div>
                <div class="qty-weight-row">
                    <span>Weight</span>
                    <span>: {{ number_format($realWeightKg, 1) }} Kg</span>
                </div>
            </div>
        </div>

        <!-- Address Grid -->
        <div class="address-section">
            <div class="receiver-column">
                <div class="address-title">Alamat Penerima:</div>
                <div class="address-name">{{ $transaction->customerAddress->receiver_name ?? '-' }}</div>
                <div class="address-phone">{{ $transaction->customerAddress->phone_number ?? '-' }}</div>
                <div class="address-text">
                    {{ $transaction->customerAddress->full_address ?? '-' }}
                    @if($transaction->customerAddress && $transaction->customerAddress->regency_name)
                        <br><strong>{{ $transaction->customerAddress->district_name }}, {{ $transaction->customerAddress->regency_name }}, {{ $transaction->customerAddress->province_name }} {{ $transaction->customerAddress->postal_code }}</strong>
                    @endif
                </div>
            </div>
            
            <div class="sender-column">
                <div class="address-title">Alamat Pengirim:</div>
                <div class="address-name">{{ $storeName }}</div>
                <div class="address-phone">{{ $storePhone }}</div>
                <div class="address-text">
                    {{ $storeAddress }}
                    <br><strong>{{ $storeCity }}</strong>
                </div>
            </div>
        </div>

        <!-- Footer Info Section -->
        <div class="footer-info-section">
            <div class="footer-info-row">
                <span class="footer-info-label">Jenis Barang:</span> 
                @php
                    $itemNames = $transaction->items->map(function($item) {
                        return $item->product_name . ' (x' . $item->quantity . ')';
                    })->join(', ');
                @endphp
                <span>{{ $itemNames ?: 'Paket Belanja' }}</span>
            </div>
            
            <div class="footer-info-row">
                <span class="footer-info-label">Catatan:</span> 
                <span>{{ $transaction->notes ?? 'Tidak Ada' }}</span>
            </div>
        </div>

        <!-- Biteship Platform Branding -->
        <div class="branding-footer">
            Pengiriman melalui platform Biteship<br>www.biteship.com
        </div>
    </div>

    <script>
        // Generate main barcode
        JsBarcode("#barcode", "{{ $transaction->tracking_number ?? 'BELUM-ADA-RESI' }}", {
            format: "CODE128",
            width: 2.2,
            height: 50,
            displayValue: false,
            margin: 0
        });

        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 600);
        }
    </script>
</body>
</html>
