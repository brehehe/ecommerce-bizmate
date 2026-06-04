<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Label Pengiriman {{ $transaction->transaction_number }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Outfit:wght@500;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.4/lib/browser.min.js"></script>
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: #000000;
            margin: 0;
            padding: 20px;
            background-color: #ffffff;
            font-size: 11px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .label-container {
            width: 100%;
            max-width: 480px;
            margin: 0 auto;
            border: 3px solid #000;
            box-sizing: border-box;
            padding: 12px;
            background-color: #fff;
            position: relative;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px dashed #000;
            padding-bottom: 10px;
            margin-bottom: 8px;
        }
        .header-left {
            font-family: 'Outfit', sans-serif;
            font-size: 16px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .header-center {
            font-family: 'Outfit', sans-serif;
            font-size: 18px;
            font-weight: 800;
            text-align: center;
            border-left: 2px solid #000;
            border-right: 2px solid #000;
            padding: 0 16px;
            letter-spacing: 1px;
        }
        .header-right {
            font-family: 'Outfit', sans-serif;
            font-size: 16px;
            font-weight: 800;
            text-transform: uppercase;
            text-align: right;
        }
        .resi-box {
            border: 2px solid #000;
            text-align: center;
            font-size: 16px;
            font-weight: 800;
            padding: 6px;
            margin: 8px 0;
            font-family: monospace;
            letter-spacing: 0.5px;
        }
        .barcode-container {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 4px 0;
            border-bottom: 2px dashed #000;
            margin-bottom: 8px;
        }
        #barcode {
            width: 100%;
            max-height: 70px;
        }
        .address-grid {
            display: grid;
            grid-template-columns: 1.6fr 1fr;
            border-bottom: 2px dashed #000;
            padding-bottom: 8px;
            margin-bottom: 8px;
            font-size: 11px;
            line-height: 1.4;
        }
        .address-left {
            padding-right: 10px;
        }
        .address-right {
            padding-left: 10px;
            border-left: 1px dashed #000;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
        }
        .grid-title {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            color: #555;
            display: block;
            margin-bottom: 2px;
        }
        .highlight-text {
            font-size: 13px;
            font-weight: 800;
            color: #000;
            display: block;
        }
        .badge-label {
            display: inline-block;
            border: 1px solid #000;
            font-size: 9px;
            font-weight: bold;
            padding: 1px 5px;
            margin: 4px 0;
            border-radius: 2px;
            text-transform: uppercase;
            background-color: #fff;
            width: fit-content;
        }
        .address-detail {
            margin: 4px 0 0 0;
            font-size: 11px;
            color: #111;
            font-weight: 500;
            word-break: break-word;
        }
        .sender-phone {
            font-family: monospace;
            font-size: 12px;
            font-weight: bold;
            margin: 2px 0 6px 0;
        }
        .sender-city {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            margin-top: auto;
            color: #000;
        }
        .destination-boxes {
            display: flex;
            gap: 6px;
            margin: 8px 0;
        }
        .dest-box {
            flex: 1;
            border: 2px solid #000;
            text-align: center;
            font-weight: 800;
            font-size: 14px;
            padding: 6px;
            text-transform: uppercase;
            font-family: 'Outfit', sans-serif;
            letter-spacing: 0.5px;
        }
        .payment-banner {
            border: 2px solid #000;
            display: flex;
            margin: 8px 0;
            overflow: hidden;
        }
        .payment-left {
            width: 35%;
            border-right: 2px solid #000;
            padding: 6px;
            font-weight: 800;
            font-size: 12px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f1f5f9;
            text-transform: uppercase;
            font-family: 'Outfit', sans-serif;
        }
        .payment-right {
            width: 65%;
            padding: 6px;
            font-size: 9.5px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-style: italic;
        }
        .meta-section {
            display: grid;
            grid-template-columns: 1.4fr 1fr;
            border-bottom: 2px dashed #000;
            padding: 8px 0;
            align-items: center;
            margin-bottom: 8px;
        }
        .meta-details {
            font-size: 10.5px;
            line-height: 1.5;
        }
        .meta-row {
            margin: 2px 0;
            font-weight: 600;
        }
        .meta-barcode-container {
            display: flex;
            justify-content: flex-end;
            align-items: center;
        }
        #mini-qrcode {
            width: 70px;
            height: 70px;
            display: block;
        }
        .packing-list-section {
            padding-top: 4px;
            font-size: 10px;
        }
        .packing-list-title {
            font-family: 'Outfit', sans-serif;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
            color: #333;
        }
        .packing-list-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }
        .packing-list-table th {
            border-bottom: 1.5px solid #000;
            text-align: left;
            padding: 4px 2px;
            font-size: 9px;
            font-weight: 800;
            color: #000;
            text-transform: uppercase;
        }
        .packing-list-table td {
            border-bottom: 1px dashed #ccc;
            padding: 5px 2px;
            font-size: 10px;
            color: #000;
            vertical-align: middle;
        }
        .packing-list-table tr:last-child td {
            border-bottom: none;
        }
        
        .print-btn-bar {
            max-width: 480px;
            margin: 0 auto 15px auto;
            display: flex;
            justify-content: flex-end;
        }
        .print-btn {
            background-color: #0f172a;
            color: #ffffff;
            border: none;
            padding: 10px 22px;
            font-size: 12px;
            font-weight: 700;
            border-radius: 6px;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: background-color 0.2s ease;
        }
        .print-btn:hover {
            background-color: #1e293b;
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
                border: 3px solid #000;
                max-width: 100%;
                width: 100%;
                box-sizing: border-box;
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
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <span style="font-size: 18px;">🛒</span>
                <span>{{ $storeName }}</span>
            </div>
            <div class="header-center">
                {{ strtoupper($transaction->shipping_service ?? 'REG') }}
            </div>
            <div class="header-right">
                {{ strtoupper($transaction->courier_name ?? $transaction->shipping_courier ?? 'KURIR') }}
            </div>
        </div>

        <!-- Resi Box -->
        <div class="resi-box">
            No. Resi: {{ $transaction->tracking_number ?? 'BELUM MASUK RESI' }}
        </div>

        <!-- Barcode -->
        <div class="barcode-container">
            <svg id="barcode"></svg>
        </div>

        <!-- Sender / Receiver Grid -->
        <div class="address-grid">
            <div class="address-left">
                <span class="grid-title">Penerima:</span>
                <span class="highlight-text">{{ $transaction->customerAddress->receiver_name ?? '-' }}</span>
                @if($transaction->customerAddress && $transaction->customerAddress->label)
                    <span class="badge-label">{{ strtoupper($transaction->customerAddress->label) }}</span>
                @else
                    <span class="badge-label">ALAMAT</span>
                @endif
                <p class="address-detail">
                    {{ $transaction->customerAddress->full_address ?? '-' }}
                    @if($transaction->customerAddress && $transaction->customerAddress->regency_name)
                        <br><strong>{{ $transaction->customerAddress->district_name }}, {{ $transaction->customerAddress->regency_name }}, {{ $transaction->customerAddress->province_name }} {{ $transaction->customerAddress->postal_code }}</strong>
                    @endif
                </p>
            </div>
            <div class="address-right">
                <span class="grid-title">Pengirim:</span>
                <span class="highlight-text" style="font-size: 11px;">{{ $storeName }}</span>
                <p style="margin: 2px 0; font-size: 9.5px; font-weight: bold; color: #333;">Telp: {{ $storePhone }}</p>
                <p style="margin: 2px 0 6px 0; font-size: 9px; line-height: 1.3; color: #444; word-break: break-word;">{{ $storeAddress }}</p>
                
                <div style="margin-top: auto; border-top: 1px dashed #000; padding-top: 4px; margin-bottom: 2px;">
                    <span class="grid-title">No. HP Penerima:</span>
                    <p class="sender-phone">{{ $transaction->customerAddress->phone_number ?? '-' }}</p>
                </div>
                <p class="sender-city">{{ strtoupper($storeCity) }}</p>
            </div>
        </div>

        <!-- Destination Highlights -->
        <div class="destination-boxes">
            <div class="dest-box">
                {{ strtoupper($transaction->customerAddress->regency_name ?? 'REGENCY') }}
            </div>
            <div class="dest-box">
                {{ strtoupper($transaction->customerAddress->district_name ?? 'DISTRICT') }}
            </div>
        </div>

        <!-- Payment Banner -->
        <div class="payment-banner">
            @if(str_contains(strtolower($transaction->paymentMethod->name ?? ''), 'cod'))
                <div class="payment-left" style="background-color: #fee2e2;">COD</div>
                <div class="payment-right">Kurir wajib menagih pembayaran sejumlah <strong>Rp {{ number_format($transaction->grand_total, 0, ',', '.') }}</strong> ke Penerima.</div>
            @else
                <div class="payment-left">CASHLESS</div>
                <div class="payment-right">Penjual tidak perlu bayar ongkir ke Kurir</div>
            @endif
        </div>

        <!-- Metadata & Mini Barcode -->
        <div class="meta-section">
            <div class="meta-details">
                <div class="meta-row">Berat: <strong>{{ number_format($transaction->items->sum('quantity') * 500) }} gr</strong></div>
                <div class="meta-row">COD: <strong>{{ str_contains(strtolower($transaction->paymentMethod->name ?? ''), 'cod') ? 'Rp ' . number_format($transaction->grand_total, 0, ',', '.') : 'Rp 0' }}</strong></div>
                <div class="meta-row">Batas Kirim: <strong>{{ $transaction->created_at ? $transaction->created_at->addDays(2)->format('d-m-Y') : date('d-m-Y') }}</strong></div>
                <div class="meta-row" style="font-size: 9.5px; word-break: break-all;">No. Pesanan: <strong>{{ $transaction->transaction_number }}</strong></div>
            </div>
            <div class="meta-barcode-container">
                <canvas id="mini-qrcode"></canvas>
            </div>
        </div>

        <!-- Packing List -->
        <div class="packing-list-section">
            <div class="packing-list-title">Daftar Isi Paket (Packing List)</div>
            <table class="packing-list-table">
                <thead>
                    <tr>
                        <th style="width: 5%;">#</th>
                        <th style="width: 45%;">Nama Produk</th>
                        <th style="width: 25%;">SKU</th>
                        <th style="width: 15%;">Variasi</th>
                        <th style="width: 10%; text-align: center;">Qty</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($transaction->items as $item)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td style="font-weight: 600;">{{ $item->product_name }}</td>
                            <td style="font-family: monospace; font-size: 9.5px;">{{ $item->product_sku ?? '-' }}</td>
                            <td>{{ $item->variant_name ?? '-' }}</td>
                            <td style="text-align: center; font-weight: 800; font-size: 12px;">x{{ $item->quantity }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Generate main barcode
        JsBarcode("#barcode", "{{ $transaction->tracking_number ?? 'BELUM-ADA-RESI' }}", {
            format: "CODE128",
            width: 2,
            height: 55,
            displayValue: false,
            margin: 0
        });

        // Generate mini QR code for order number
        QRCode.toCanvas(document.getElementById('mini-qrcode'), "{{ $transaction->tracking_number }}", {
            width: 70,
            margin: 0,
            errorCorrectionLevel: 'H'
        }, function (error) {
            if (error) console.error(error);
        });

        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 600);
        }
    </script>
</body>
</html>
