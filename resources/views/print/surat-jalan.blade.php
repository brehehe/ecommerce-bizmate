<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surat Jalan #{{ $transaction->transaction_number }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Outfit:wght@500;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f8fafc;
            color: #0f172a;
            font-size: 11px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* ── Screen Wrapper ── */
        .page-wrapper {
            max-width: 720px;
            margin: 24px auto;
            padding: 0 16px 32px;
        }

        /* ── Print Button Bar ── */
        .print-bar {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-bottom: 16px;
        }
        .btn-print {
            background: #0f172a;
            color: #fff;
            border: none;
            padding: 10px 22px;
            font-size: 12px;
            font-weight: 700;
            border-radius: 8px;
            cursor: pointer;
            letter-spacing: 0.3px;
        }
        .btn-print:hover { background: #1e293b; }

        /* ── Paper Card ── */
        .paper {
            background: #fff;
            border: 2px solid #000;
            padding: 24px 28px;
        }

        /* ── Header ── */
        .sj-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            border-bottom: 3px solid #000;
            padding-bottom: 14px;
            margin-bottom: 14px;
            gap: 12px;
        }
        .sj-logo-area {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .sj-logo {
            width: 48px;
            height: 48px;
            object-fit: contain;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }
        .sj-store-name {
            font-family: 'Outfit', sans-serif;
            font-size: 20px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            line-height: 1;
        }
        .sj-store-meta {
            font-size: 9px;
            color: #64748b;
            margin-top: 3px;
            font-weight: 600;
            line-height: 1.4;
        }
        .sj-title-area {
            text-align: right;
        }
        .sj-doc-title {
            font-family: 'Outfit', sans-serif;
            font-size: 22px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1px;
            line-height: 1;
        }
        .sj-doc-sub {
            font-size: 9px;
            color: #475569;
            font-weight: 600;
            margin-top: 3px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* ── Info Grid ── */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            border: 1.5px solid #000;
            margin-bottom: 14px;
        }
        .info-panel {
            padding: 10px 14px;
        }
        .info-panel + .info-panel {
            border-left: 1.5px solid #000;
        }
        .info-panel-title {
            font-size: 8.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #64748b;
            margin-bottom: 6px;
        }
        .info-name {
            font-family: 'Outfit', sans-serif;
            font-size: 15px;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 3px;
        }
        .info-detail {
            font-size: 10px;
            color: #334155;
            line-height: 1.5;
            font-weight: 500;
        }
        .info-phone {
            font-family: monospace;
            font-size: 12px;
            font-weight: 700;
            margin-top: 4px;
            color: #0f172a;
        }

        /* ── Tracking Row ── */
        .tracking-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            border: 1.5px solid #000;
            margin-bottom: 14px;
        }
        .tracking-cell {
            padding: 8px 12px;
            border-right: 1.5px solid #000;
        }
        .tracking-cell:last-child { border-right: none; }
        .tracking-label {
            font-size: 8.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            margin-bottom: 3px;
        }
        .tracking-value {
            font-family: 'Outfit', sans-serif;
            font-size: 13px;
            font-weight: 800;
            font-style: normal;
        }
        .tracking-mono {
            font-family: monospace;
            font-size: 12px;
            font-weight: 700;
            word-break: break-all;
        }

        /* ── Items Table ── */
        .items-section {
            margin-bottom: 14px;
        }
        .items-title {
            font-family: 'Outfit', sans-serif;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #475569;
            margin-bottom: 6px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        thead th {
            background: #0f172a;
            color: #fff;
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 6px 8px;
            text-align: left;
        }
        tbody td {
            padding: 7px 8px;
            font-size: 10.5px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr:nth-child(even) td { background: #f8fafc; }
        .td-no { width: 4%; text-align: center; font-weight: 700; color: #94a3b8; }
        .td-name { width: 44%; font-weight: 700; }
        .td-sku { width: 20%; font-family: monospace; font-size: 9.5px; color: #64748b; }
        .td-variant { width: 18%; color: #64748b; }
        .td-qty { width: 7%; text-align: center; font-weight: 800; font-size: 13px; }
        .td-price { width: 7%; text-align: right; font-weight: 700; font-size: 10px; white-space: nowrap; }

        /* ── Summary ── */
        .summary-section {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 16px;
            align-items: start;
            margin-bottom: 16px;
        }
        .summary-notes {
            border: 1.5px dashed #cbd5e1;
            padding: 10px 12px;
            font-size: 10px;
            color: #64748b;
            font-style: italic;
            min-height: 64px;
        }
        .summary-notes-label {
            font-size: 8.5px;
            font-weight: 800;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
            font-style: normal;
        }
        .summary-totals {
            min-width: 200px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 4px 0;
            font-size: 10.5px;
            font-weight: 600;
            color: #475569;
            border-bottom: 1px dashed #e2e8f0;
        }
        .total-row:last-child { border-bottom: none; }
        .total-row.grand {
            margin-top: 4px;
            padding-top: 8px;
            font-size: 14px;
            font-weight: 800;
            color: #0f172a;
            border-top: 2px solid #000;
            border-bottom: none;
        }

        /* ── Signature Row ── */
        .signature-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 8px;
            border-top: 2px dashed #000;
            padding-top: 12px;
            margin-top: 4px;
        }
        .sig-box {
            border: 1.5px solid #e2e8f0;
            padding: 8px 10px;
            text-align: center;
        }
        .sig-label {
            font-size: 8.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #94a3b8;
            margin-bottom: 36px;
        }
        .sig-line {
            border-top: 1px solid #000;
            padding-top: 4px;
            font-size: 9px;
            font-weight: 700;
            color: #64748b;
        }

        /* ── Footer ── */
        .footer {
            margin-top: 10px;
            font-size: 8.5px;
            color: #94a3b8;
            text-align: center;
            font-weight: 600;
            letter-spacing: 0.3px;
        }

        /* ── Print ── */
        @media print {
            body { background: #fff; }
            .page-wrapper { margin: 0; padding: 0; max-width: 100%; }
            .print-bar { display: none !important; }
            .paper {
                border: none;
                padding: 16px 20px;
            }
            @page {
                size: A4 portrait;
                margin: 10mm;
            }
        }
    </style>
</head>
<body>

<div class="page-wrapper">

    <!-- Print Button Bar -->
    <div class="print-bar no-print">
        <button class="btn-print" onclick="window.print()">🖨️ Cetak Surat Jalan</button>
    </div>

    <div class="paper">

        <!-- ═══════════════ HEADER ═══════════════ -->
        <div class="sj-header">
            <div class="sj-logo-area">
                @if($storeLogo)
                    <img src="{{ asset('storage/' . $storeLogo) }}" alt="{{ $storeName }}" class="sj-logo" onerror="this.style.display='none'">
                @endif
                <div>
                    <div class="sj-store-name">{{ $storeName }}</div>
                    <div class="sj-store-meta">
                        {{ $storeAddress }}{{ $storeCity ? ', ' . $storeCity : '' }}<br>
                        @if($storePhone) Telp: {{ $storePhone }} @endif
                    </div>
                </div>
            </div>
            <div class="sj-title-area">
                <div class="sj-doc-title">Surat Jalan</div>
                <div class="sj-doc-sub">Kurir Toko &bull; Pengiriman Langsung</div>
                <div style="font-size:9px; color:#94a3b8; margin-top:4px;">
                    Dicetak: {{ now()->format('d/m/Y H:i') }}
                </div>
            </div>
        </div>

        <!-- ═══════════════ TRACKING INFO ═══════════════ -->
        <div class="tracking-row">
            <div class="tracking-cell">
                <div class="tracking-label">No. Pesanan</div>
                <div class="tracking-mono">#{{ $transaction->transaction_number }}</div>
            </div>
            <div class="tracking-cell">
                <div class="tracking-label">No. Resi</div>
                <div class="tracking-mono">{{ $transaction->tracking_number ?? '—' }}</div>
            </div>
            <div class="tracking-cell">
                <div class="tracking-label">Petugas Kurir</div>
                <div class="tracking-value">{{ $transaction->courierUser?->name ?? '—' }}</div>
            </div>
        </div>

        <!-- ═══════════════ ADDRESS GRID ═══════════════ -->
        <div class="info-grid">
            <!-- Pengirim -->
            <div class="info-panel">
                <div class="info-panel-title">📦 Pengirim</div>
                <div class="info-name">{{ $storeName }}</div>
                <div class="info-detail">
                    {{ $storeAddress }}<br>
                    {{ $storeCity }}
                </div>
                <div class="info-phone">{{ $storePhone }}</div>
            </div>
            <!-- Penerima -->
            <div class="info-panel">
                <div class="info-panel-title">🏠 Penerima</div>
                <div class="info-name">
                    {{ $transaction->customerAddress?->receiver_name ?? $transaction->user?->name ?? '—' }}
                </div>
                @if($transaction->customerAddress)
                    <div class="info-detail">
                        {{ $transaction->customerAddress->address }}<br>
                        @if($transaction->customerAddress->district_name)
                            {{ $transaction->customerAddress->district_name }},
                        @endif
                        @if($transaction->customerAddress->regency_name)
                            {{ $transaction->customerAddress->regency_name }}<br>
                        @endif
                        @if($transaction->customerAddress->province_name)
                            {{ $transaction->customerAddress->province_name }}
                        @endif
                        @if($transaction->customerAddress->postal_code)
                            {{ $transaction->customerAddress->postal_code }}
                        @endif
                    </div>
                    @if($transaction->customerAddress->phone_number)
                        <div class="info-phone">{{ $transaction->customerAddress->phone_number }}</div>
                    @endif
                @elseif($transaction->user?->phone_number)
                    <div class="info-phone">{{ $transaction->user->phone_number }}</div>
                @endif
            </div>
        </div>

        <!-- ═══════════════ ITEMS TABLE ═══════════════ -->
        <div class="items-section">
            <div class="items-title">Daftar Barang</div>
            <table>
                <thead>
                    <tr>
                        <th class="td-no">#</th>
                        <th class="td-name">Nama Produk</th>
                        <th class="td-sku">SKU</th>
                        <th class="td-variant">Variasi</th>
                        <th class="td-qty">Qty</th>
                        <th class="td-price">Harga</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($transaction->items as $item)
                        <tr>
                            <td class="td-no">{{ $loop->iteration }}</td>
                            <td class="td-name">{{ $item->product_name }}</td>
                            <td class="td-sku">{{ $item->product_sku ?? '—' }}</td>
                            <td class="td-variant">{{ $item->variant_name ?? '—' }}</td>
                            <td class="td-qty">{{ $item->quantity }}</td>
                            <td class="td-price">Rp {{ number_format($item->harga_akhir * $item->quantity, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- ═══════════════ SUMMARY ═══════════════ -->
        <div class="summary-section">
            <div class="summary-notes">
                <div class="summary-notes-label">Catatan</div>
                {{ $transaction->customer_note ?: 'Tidak ada catatan khusus.' }}
            </div>
            <div class="summary-totals">
                <div class="total-row">
                    <span>Subtotal</span>
                    <span>Rp {{ number_format($transaction->subtotal_after_discount ?? ($transaction->grand_total - $transaction->shipping_cost), 0, ',', '.') }}</span>
                </div>
                <div class="total-row">
                    <span>Ongkir (Kurir Toko)</span>
                    <span>Rp {{ number_format($transaction->shipping_cost ?? 0, 0, ',', '.') }}</span>
                </div>
                @if(($transaction->discount_amount ?? 0) > 0)
                    <div class="total-row">
                        <span>Diskon</span>
                        <span>– Rp {{ number_format($transaction->discount_amount, 0, ',', '.') }}</span>
                    </div>
                @endif
                <div class="total-row grand">
                    <span>Total</span>
                    <span>Rp {{ number_format($transaction->grand_total, 0, ',', '.') }}</span>
                </div>
            </div>
        </div>

        <!-- ═══════════════ SIGNATURES ═══════════════ -->
        <div class="signature-row">
            <div class="sig-box">
                <div class="sig-label">Disiapkan oleh</div>
                <div class="sig-line">Gudang / Admin</div>
            </div>
            <div class="sig-box">
                <div class="sig-label">Kurir Pengantar</div>
                <div class="sig-line">{{ $transaction->courierUser?->name ?? '________________' }}</div>
            </div>
            <div class="sig-box">
                <div class="sig-label">Penerima &amp; Tanggal</div>
                <div class="sig-line">______ / __________</div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer" style="margin-top: 14px;">
            Dokumen ini dihasilkan secara otomatis oleh sistem {{ $storeName }} &bull; {{ now()->format('d F Y H:i') }}
        </div>

    </div>
</div>

<script>
    window.onload = function () {
        setTimeout(function () {
            window.print();
        }, 400);
    };
</script>
</body>
</html>
