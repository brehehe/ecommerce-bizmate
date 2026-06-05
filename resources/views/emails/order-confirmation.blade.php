<!DOCTYPE html>
<html lang="id" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Pesanan Berhasil Dibuat</title>
    <!--[if mso]>
    <noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript>
    <![endif]-->
    <style type="text/css">
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        body { margin: 0 !important; padding: 0 !important; background-color: #f5f8fa; }
        @media only screen and (max-width: 600px) {
            .email-container { width: 100% !important; max-width: 100% !important; }
            .product-img { width: 60px !important; height: 60px !important; }
            .btn-mobile { width: 100% !important; display: block !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#f5f8fa;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;">

@php
    $logoUrl = null;
    if ($storeLogo) {
        if (str_starts_with($storeLogo, 'http://') || str_starts_with($storeLogo, 'https://')) {
            $logoUrl = $storeLogo;
        } else {
            $cleanLogoPath = ltrim($storeLogo, '/');
            $storagePath = storage_path('app/public/' . $cleanLogoPath);
            $publicPath = public_path($cleanLogoPath);
            
            if (file_exists($storagePath)) {
                $logoUrl = $appUrl . "/storage/" . ltrim($cleanLogoPath, "/");
            } elseif (file_exists($publicPath)) {
                $logoUrl = $appUrl . "/" . ltrim($cleanLogoPath, "/");
            } else {
                $logoUrl = $appUrl . '/' . $cleanLogoPath;
            }
        }
    }
@endphp

<!-- Preheader -->
<div style="display:none;font-size:1px;color:#f5f8fa;line-height:1px;max-height:0px;max-width:0px;opacity:0;overflow:hidden;">
    Pesanan #{{ $transaction->transaction_number }} berhasil dibuat — {{ $storeName }}
</div>

<!-- Outer wrapper -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color:#f5f8fa;">
    <tr>
        <td align="center" style="padding:20px 10px;">

            <!-- Email container -->
            <table class="email-container" role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" style="max-width:600px;background:#ffffff;border-radius:6px;overflow:hidden;border:1px solid #e8e8e8;">

                {{-- ══════════ HEADER ══════════ --}}
                <tr>
                    <td style="padding:24px 30px;border-bottom:3px solid #0056b3;text-align:center;">
                        
                        <div style="color:#0056b3;font-size:20px;font-weight:bold;letter-spacing:0.5px;">{{ $storeName }}</div>
                    </td>
                </tr>

                {{-- ══════════ BODY ══════════ --}}
                <tr>
                    <td style="padding:30px 25px;">

                        <p style="font-size:15px;margin:0 0 12px 0;color:#222222;">Hai <strong>{{ $transaction->user->name }}</strong>,</p>
                        <p style="font-size:14px;line-height:1.5;margin:0 0 25px 0;color:#444444;">
                            Pesanan Anda <strong style="color:#0056b3;">#{{ $transaction->transaction_number }}</strong> telah berhasil dibuat pada {{ $transaction->created_at->format('d/m/Y H:i') }} WIB.
                        </p>

                        {{-- Section: Order Details --}}
                        <div style="font-size:13px;font-weight:bold;color:#444444;text-transform:uppercase;margin-bottom:12px;border-bottom:1px solid #eeeeee;padding-bottom:6px;letter-spacing:0.5px;">
                            Rincian Pesanan
                        </div>

                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:20px;font-size:13px;line-height:1.6;">
                            <tr>
                                <td width="130" style="color:#777777;padding:3px 0;vertical-align:top;">No. Pesanan:</td>
                                <td style="color:#0056b3;font-weight:bold;padding:3px 0;vertical-align:top;">#{{ $transaction->transaction_number }}</td>
                            </tr>
                            <tr>
                                <td style="color:#777777;padding:3px 0;vertical-align:top;">Tanggal Pemesanan:</td>
                                <td style="color:#333333;padding:3px 0;vertical-align:top;">{{ $transaction->created_at->format('d M Y H:i') }} WIB</td>
                            </tr>
                            <tr>
                                <td style="color:#777777;padding:3px 0;vertical-align:top;">Penjual:</td>
                                <td style="color:#333333;padding:3px 0;vertical-align:top;">{{ $storeName }}</td>
                            </tr>
                            <tr>
                                <td style="color:#777777;padding:3px 0;vertical-align:top;">Status:</td>
                                <td style="color:#0056b3;font-weight:bold;padding:3px 0;vertical-align:top;">Belum Bayar</td>
                            </tr>
                        </table>

                        {{-- Section: Products --}}
                        <div style="font-size:13px;font-weight:bold;color:#444444;text-transform:uppercase;margin-bottom:12px;border-bottom:1px solid #eeeeee;padding-bottom:6px;letter-spacing:0.5px;">
                            Produk Dipesan
                        </div>

                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="border-collapse:collapse;margin-bottom:20px;">
                            @foreach($transaction->items as $item)
                            <tr style=\"border-bottom:1px solid #f0f0f0;\">
                                {{-- Product info --}}
                                <td style="padding:10px 0;vertical-align:top;font-size:13px;line-height:1.4;">
                                    <div style="font-weight:bold;color:#333333;margin-bottom:3px;">{{ $item->product_name }}</div>
                                    @if($item->variant_name)
                                        <div style="font-size:11px;color:#666666;margin-bottom:3px;">Variasi: {{ $item->variant_name }}</div>
                                    @endif
                                    <div style="color:#888888;">x{{ $item->quantity }}</div>
                                    @if($item->note)
                                        @php
                                            $noteText = trim($item->note);
                                            $isLink = str_starts_with(strtolower($noteText), 'http://') || str_starts_with(strtolower($noteText), 'https://');
                                        @endphp
                                        <div style="font-size:11px;color:#f97316;margin-top:4px;padding:3px 6px;background-color:#fff7ed;border:1px solid #ffedd5;border-radius:4px;display:inline-block;">
                                            <strong>Catatan:</strong>
                                            @if($isLink)
                                                <a href="{{ $noteText }}" target="_blank" style="color:#0056b3;text-decoration:underline;">{{ $noteText }}</a>
                                            @else
                                                <code style="font-family:monospace;font-size:12px;background:#ffe4e6;padding:1px 3px;border-radius:2px;color:#e11d48;">{{ $noteText }}</code>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                {{-- Price --}}
                                <td align="right" style="padding:10px 0;vertical-align:top;font-size:13px;font-weight:bold;color:#333333;white-space:nowrap;">
                                    Rp {{ number_format($item->subtotal, 0, ',', '.') }}
                                </td>
                            </tr>
                            @endforeach
                        </table>

                        {{-- Section: Payment Summary --}}
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="font-size:13px;color:#555555;margin-bottom:25px;line-height:1.6;">
                            <tr>
                                <td style="padding:3px 0;">Subtotal untuk Produk</td>
                                <td align="right" style="padding:3px 0;color:#333333;">Rp {{ number_format($transaction->subtotal, 0, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <td style="padding:3px 0;">
                                    Total Ongkos Kirim
                                    @if($transaction->shipping_courier)
                                        ({{ strtoupper($transaction->shipping_courier) }} {{ $transaction->shipping_service }})
                                    @endif
                                </td>
                                <td align="right" style="padding:3px 0;color:#333333;">Rp {{ number_format($transaction->shipping_fee, 0, ',', '.') }}</td>
                            </tr>
                            @if($transaction->shipping_discount > 0)
                            <tr>
                                <td style="padding:3px 0;color:#0056b3;">Diskon Pengiriman</td>
                                <td align="right" style="padding:3px 0;color:#0056b3;">-Rp {{ number_format($transaction->shipping_discount, 0, ',', '.') }}</td>
                            </tr>
                            @endif
                            @if($transaction->discount_amount > 0)
                            <tr>
                                <td style="padding:3px 0;color:#0056b3;">Diskon Voucher ({{ $transaction->voucher_code }})</td>
                                <td align="right" style="padding:3px 0;color:#0056b3;">-Rp {{ number_format($transaction->discount_amount, 0, ',', '.') }}</td>
                            </tr>
                            @endif
                            @if($transaction->admin_fee > 0)
                            <tr>
                                <td style="padding:3px 0;">Biaya Admin</td>
                                <td align="right" style="padding:3px 0;color:#333333;">Rp {{ number_format($transaction->admin_fee, 0, ',', '.') }}</td>
                            </tr>
                            @endif
                            <tr>
                                <td colspan="2" style="padding:8px 0 0 0;"><div style="border-top:1px solid #eeeeee;"></div></td>
                            </tr>
                            <tr>
                                <td style="padding:8px 0 0 0;font-size:15px;font-weight:bold;color:#222222;">Total Pembayaran</td>
                                <td align="right" style="padding:8px 0 0 0;font-size:15px;font-weight:bold;color:#0056b3;">
                                    Rp {{ number_format($transaction->grand_total, 0, ',', '.') }}
                                </td>
                            </tr>
                        </table>

                        {{-- Section: Shipping Info --}}
                        @if($transaction->shipping_courier === 'digital')
                            <div style="font-size:13px;font-weight:bold;color:#444444;text-transform:uppercase;margin-bottom:12px;border-bottom:1px solid #eeeeee;padding-bottom:6px;letter-spacing:0.5px;">
                                Informasi Pengiriman
                            </div>
                            <div style="font-size:13px;color:#1e40af;line-height:1.5;margin-bottom:25px;background-color:#eff6ff;border:1px solid #bfdbfe;border-radius:4px;padding:12px;">
                                <strong>Pengiriman Digital</strong><br />
                                Produk digital Anda akan dikirimkan melalui email / chat catatan.
                                @if($transaction->notes)
                                    <div style="margin-top:8px;padding-top:8px;border-top:1px dashed #bfdbfe;font-size:12px;color:#1e40af;">
                                        <strong>Catatan:</strong> {{ $transaction->notes }}
                                    </div>
                                @endif
                            </div>
                        @elseif($transaction->shipping_courier === 'self_pickup')
                            @php
                                $storeAddress = \App\Models\Setting::where('key', 'address')->value('value') ?? '';
                                $storeProvince = \App\Models\Setting::where('key', 'province_name')->value('value') ?? '';
                                $storeRegency = \App\Models\Setting::where('key', 'regency_name')->value('value') ?? '';
                                $storeDistrict = \App\Models\Setting::where('key', 'district_name')->value('value') ?? '';
                                $storeVillage = \App\Models\Setting::where('key', 'village_name')->value('value') ?? '';
                                $storePostalCode = \App\Models\Setting::where('key', 'postal_code')->value('value') ?? '';
                                $storeAddrParts = array_filter([
                                    $storeAddress,
                                    $storeVillage,
                                    $storeDistrict,
                                    $storeRegency,
                                    $storeProvince,
                                    $storePostalCode
                                ]);
                                $fullStoreAddress = implode(', ', $storeAddrParts);
                            @endphp
                            <div style="font-size:13px;font-weight:bold;color:#444444;text-transform:uppercase;margin-bottom:12px;border-bottom:1px solid #eeeeee;padding-bottom:6px;letter-spacing:0.5px;">
                                Tempat Pengambilan Barang (Self Pickup)
                            </div>
                            <div style="font-size:13px;color:#065f46;line-height:1.5;margin-bottom:25px;background-color:#ecfdf5;border:1px solid #a7f3d0;border-radius:4px;padding:12px;">
                                <strong>{{ $storeName }}</strong><br />
                                {{ $fullStoreAddress ?: 'Alamat toko belum diatur.' }}
                                <div style="margin-top:10px;text-align:center;">
                                    <p style="font-size:11px;font-weight:bold;color:#065f46;margin:0 0 5px 0;text-transform:uppercase;">Scan Kode Transaksi Saat Pengambilan</p>
                                    <img
                                        src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data={{ urlencode($transaction->transaction_number) }}"
                                        alt="QR Code Transaksi"
                                        width="120"
                                        height="120"
                                        style="border:1px solid #a7f3d0;border-radius:6px;padding:3px;background-color:#ffffff;display:inline-block;"
                                    />
                                </div>
                                @if($transaction->notes)
                                    <div style="margin-top:8px;padding-top:8px;border-top:1px dashed #a7f3d0;font-size:12px;color:#065f46;">
                                        <strong>Catatan:</strong> {{ $transaction->notes }}
                                    </div>
                                @endif
                            </div>
                        @else
                            <div style="font-size:13px;font-weight:bold;color:#444444;text-transform:uppercase;margin-bottom:12px;border-bottom:1px solid #eeeeee;padding-bottom:6px;letter-spacing:0.5px;">
                                Alamat Pengiriman
                            </div>
                            <div style="font-size:13px;color:#333333;line-height:1.5;margin-bottom:25px;background-color:#fafafa;border:1px solid #eeeeee;border-radius:4px;padding:12px;">
                                <strong style="color:#111111;">{{ $transaction->customerAddress->receiver_name ?? $transaction->user->name }}</strong>
                                @if($transaction->customerAddress->phone_number ?? false)
                                    <span style="color:#666666;margin-left:5px;">({{ $transaction->customerAddress->phone_number }})</span>
                                @endif
                                <br />
                                @php
                                    $addr = $transaction->customerAddress;
                                    $parts = array_filter([
                                        $addr->full_address ?? null,
                                        $addr->district_name ?? null,
                                        $addr->regency_name ?? null,
                                        $addr->province_name ?? null,
                                        $addr->postal_code ?? null,
                                    ]);
                                @endphp
                                {{ implode(', ', $parts) ?: '-' }}
                                @if($transaction->notes)
                                    <div style="margin-top:8px;padding-top:8px;border-top:1px dashed #dddddd;font-size:12px;color:#666666;">
                                        <strong>Catatan:</strong> {{ $transaction->notes }}
                                    </div>
                                @endif
                            </div>
                        @endif

                        {{-- Button CTA --}}
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" style="margin:30px auto 10px auto;">
                            <tr>
                                <td align="center" bgcolor="#0056b3" style="border-radius:4px;">
                                    <a href="{{ $appUrl }}/transactions/{{ $transaction->id }}" target="_blank" style="font-size:15px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#ffffff;text-decoration:none;border-radius:4px;padding:12px 35px;border:1px solid #0056b3;display:inline-block;font-weight:bold;">
                                        Lihat Detail Pesanan
                                    </a>
                                </td>
                            </tr>
                        </table>

                    </td>
                </tr>

                {{-- ══════════ FOOTER ══════════ --}}
                <tr>
                    <td align="center" style="padding:20px;background-color:#fafafa;border-top:1px solid #e8e8e8;font-size:11px;color:#888888;line-height:1.6;">
                        <p style="margin:0 0 5px 0;">Email ini dikirim secara otomatis oleh <strong>{{ $storeName }}</strong>.</p>
                        <p style="margin:0;">&copy; {{ date('Y') }} {{ $storeName }}. Hak Cipta Dilindungi.</p>
                    </td>
                </tr>

            </table>
            {{-- / Email container --}}

        </td>
    </tr>
</table>

</body>
</html>
