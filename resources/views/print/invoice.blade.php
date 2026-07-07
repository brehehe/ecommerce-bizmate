<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $transaction->transaction_number }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Outfit:wght@400;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: #1e293b;
            margin: 0;
            padding: 40px;
            background-color: #ffffff;
            font-size: 13px;
            line-height: 1.5;
        }
        .invoice-box {
            max-width: 800px;
            margin: auto;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 25px;
            margin-bottom: 30px;
        }
        .logo-title {
            font-family: 'Outfit', sans-serif;
            font-size: 28px;
            font-weight: 800;
            color: #0c4cb4;
            margin: 0 0 5px 0;
        }
        .logo-sub {
            font-size: 11px;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 0;
        }
        .invoice-title {
            font-family: 'Outfit', sans-serif;
            font-size: 24px;
            font-weight: 800;
            color: #1e293b;
            margin: 0 0 5px 0;
            text-align: right;
            text-transform: uppercase;
        }
        .invoice-num {
            font-family: monospace;
            font-size: 14px;
            font-weight: bold;
            color: #0f172a;
            margin: 0;
            text-align: right;
        }
        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 35px;
        }
        .meta-title {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            color: #94a3b8;
            letter-spacing: 1px;
            margin-bottom: 8px;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 4px;
        }
        .meta-value {
            font-size: 13px;
            color: #334155;
            margin: 0;
        }
        .meta-value strong {
            color: #0f172a;
        }
        .table-items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 35px;
        }
        .table-items th {
            background-color: #f8fafc;
            color: #475569;
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 12px 16px;
            text-align: left;
            border-bottom: 2px solid #e2e8f0;
        }
        .table-items td {
            padding: 16px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
        }
        .item-name {
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 4px 0;
        }
        .item-variant {
            font-size: 11px;
            color: #64748b;
            margin: 0;
        }
        .item-sku {
            font-family: monospace;
            font-size: 10px;
            color: #94a3b8;
            margin-top: 4px;
        }
        .summary-wrapper {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }
        .summary-table {
            width: 320px;
            border-collapse: collapse;
        }
        .summary-table td {
            padding: 8px 16px;
            color: #475569;
        }
        .summary-table tr.total-row td {
            border-top: 2px solid #e2e8f0;
            padding-top: 15px;
            font-weight: 800;
            color: #0f172a;
            font-size: 16px;
        }
        .footer {
            margin-top: 60px;
            text-align: center;
            font-size: 11px;
            color: #94a3b8;
            border-top: 1px dashed #e2e8f0;
            padding-top: 20px;
        }
        @media print {
            body {
                padding: 20px;
                margin: 0;
            }
            .no-print {
                display: none !important;
            }
            @page {
                margin: 0;
            }
        }
        .print-btn-bar {
            max-width: 800px;
            margin: 0 auto 20px auto;
            display: flex;
            justify-content: flex-end;
        }
        .print-btn {
            background-color: #0c4cb4;
            color: #ffffff;
            border: none;
            padding: 10px 20px;
            font-size: 12px;
            font-weight: bold;
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Plus Jakarta Sans', sans-serif;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 6px -1px rgba(12, 76, 180, 0.2);
            transition: all 0.2s;
        }
        .print-btn:hover {
            opacity: 0.9;
        }
        .download-btn {
            background-color: #10b981;
            color: #ffffff;
            border: none;
            padding: 10px 20px;
            font-size: 12px;
            font-weight: bold;
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Plus Jakarta Sans', sans-serif;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.2);
            transition: all 0.2s;
            margin-right: 10px;
        }
        .download-btn:hover {
            opacity: 0.9;
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
</head>
<body>

    <div class="print-btn-bar no-print">
        <button class="download-btn" onclick="downloadPDF()">Unduh PDF</button>
        <button class="print-btn" onclick="window.print()">Cetak Invoice</button>
    </div>

    <div class="invoice-box">
        <div class="header">
            <div>
                <h1 class="logo-title">{{ $storeName }}</h1>
                <p class="logo-sub">E-Commerce Invoice</p>
            </div>
            <div>
                <h2 class="invoice-title">Invoice</h2>
                <p class="invoice-num">{{ $transaction->transaction_number }}</p>
            </div>
        </div>

        <div class="meta-grid">
            <div>
                <div class="meta-title">Info Pembeli</div>
                <div class="meta-value">
                    <strong>{{ $transaction->user->name }}</strong><br>
                    {{ $transaction->user->email }}
                </div>

                <div class="meta-title" style="margin-top: 20px;">Alamat Pengiriman</div>
                <div class="meta-value">
                    @if($transaction->customerAddress)
                        <strong>{{ $transaction->customerAddress->receiver_name }}</strong><br>
                        {{ $transaction->customerAddress->phone_number }}<br>
                        {{ $transaction->customerAddress->full_address }}<br>
                        @if($transaction->customerAddress->regency_name)
                            {{ $transaction->customerAddress->district_name }}, {{ $transaction->customerAddress->regency_name }}, {{ $transaction->customerAddress->province_name }} {{ $transaction->customerAddress->postal_code }}
                        @endif
                    @else
                        -
                    @endif
                </div>
            </div>

            <div>
                <div class="meta-title">Info Transaksi</div>
                <div class="meta-value">
                    Tanggal Transaksi: <strong>{{ $transaction->created_at->setTimezone('Asia/Jakarta')->format('d F Y H:i') }} WIB</strong><br>
                    Metode Pembayaran: <strong>{{ $transaction->paymentMethod->name }}</strong><br>
                    Status Transaksi: <strong style="text-transform: uppercase;">{{ str_replace('_', ' ', $transaction->status) }}</strong>
                </div>

                @if($transaction->tracking_number)
                    <div class="meta-title" style="margin-top: 20px;">Info Pengiriman</div>
                    <div class="meta-value">
                        Kurir: <strong style="text-transform: uppercase;">{{ $transaction->courier_name ?? $transaction->shipping_courier }}</strong><br>
                        Nomor Resi: <strong style="font-family: monospace;">{{ $transaction->tracking_number }}</strong>
                    </div>
                @endif
            </div>
        </div>

        <table class="table-items">
            <thead>
                <tr>
                    <th>Produk</th>
                    <th style="text-align: right; width: 120px;">Harga Satuan</th>
                    <th style="text-align: center; width: 60px;">Jumlah</th>
                    <th style="text-align: right; width: 120px;">Total Harga</th>
                </tr>
            </thead>
            <tbody>
                @foreach($transaction->items as $item)
                    <tr>
                        <td>
                            <p class="item-name">{{ $item->product_name }}</p>
                            @if($item->variant_name)
                                <p class="item-variant">Varian: {{ $item->variant_name }}</p>
                            @endif
                            <p class="item-sku">SKU: {{ $item->product_sku }}</p>
                        </td>
                        <td style="text-align: right;">Rp {{ number_format($item->harga_akhir, 0, ',', '.') }}</td>
                        <td style="text-align: center;">{{ $item->quantity }}</td>
                        <td style="text-align: right; font-weight: bold;">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="summary-wrapper">
            <table class="summary-table">
                <tr>
                    <td>Subtotal Produk:</td>
                    <td style="text-align: right; font-weight: bold;">Rp {{ number_format($transaction->subtotal, 0, ',', '.') }}</td>
                </tr>
                @if($transaction->discount_amount > 0)
                    <tr style="color: #16a34a;">
                        <td>Diskon Voucher:</td>
                        <td style="text-align: right; font-weight: bold;">-Rp {{ number_format($transaction->discount_amount, 0, ',', '.') }}</td>
                    </tr>
                @endif
                <tr>
                    <td>Ongkos Kirim:</td>
                    <td style="text-align: right; font-weight: bold;">Rp {{ number_format($transaction->shipping_fee, 0, ',', '.') }}</td>
                </tr>
                @if($transaction->shipping_discount > 0)
                    <tr style="color: #16a34a;">
                        <td>Diskon Ongkos Kirim:</td>
                        <td style="text-align: right; font-weight: bold;">-Rp {{ number_format($transaction->shipping_discount, 0, ',', '.') }}</td>
                    </tr>
                @endif
                @if($transaction->admin_fee > 0)
                    <tr>
                        <td>Biaya Admin:</td>
                        <td style="text-align: right; font-weight: bold;">Rp {{ number_format($transaction->admin_fee, 0, ',', '.') }}</td>
                    </tr>
                @endif
                @if($transaction->application_fee > 0)
                    <tr>
                        <td>Biaya Aplikasi:</td>
                        <td style="text-align: right; font-weight: bold;">Rp {{ number_format($transaction->application_fee, 0, ',', '.') }}</td>
                    </tr>
                @endif
                <tr class="total-row">
                    <td>Total Belanja:</td>
                    <td style="text-align: right; color: #0c4cb4;">Rp {{ number_format($transaction->grand_total, 0, ',', '.') }}</td>
                </tr>
            </table>
            @if(\App\Models\Setting::where('key', 'tax_enabled')->value('value') == '1')
                <div style="text-align: right; font-size: 11px; color: #64748b; margin-top: 8px; font-style: italic; padding-right: 16px;">
                    * Harga sudah termasuk Pajak
                </div>
            @endif
        </div>

        <div class="footer">
            <p>Terima kasih telah berbelanja di {{ $storeName }}!</p>
            <p>Jika Anda membutuhkan bantuan, silakan hubungi Customer Support kami.</p>
        </div>
    </div>

    <script>
        function downloadPDF() {
            const element = document.querySelector('.invoice-box');
            
            const opt = {
                margin:       15,
                filename:     'Invoice-{{ $transaction->transaction_number }}.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true, letterRendering: true },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            
            html2pdf().set(opt).from(element).save().then(() => {
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.has('download')) {
                    setTimeout(() => {
                        window.close();
                    }, 500);
                }
            });
        }

        window.onload = function() {
            setTimeout(function() {
                const urlParams = new URLSearchParams(window.location.search);
                const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
                if (urlParams.has('download') || isMobile) {
                    downloadPDF();
                } else {
                    window.print();
                }
            }, 600);
        }
    </script>
</body>
</html>
