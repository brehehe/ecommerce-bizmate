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
        body { margin: 0 !important; padding: 0 !important; background-color: #f0f4f8; }
        @media only screen and (max-width: 600px) {
            .email-container { width: 100% !important; max-width: 100% !important; }
            .product-img { width: 64px !important; height: 64px !important; }
            .btn-mobile { width: 100% !important; display: block !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#f0f4f8;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;">

<!-- Preheader -->
<div style="display:none;font-size:1px;color:#f0f4f8;line-height:1px;max-height:0px;max-width:0px;opacity:0;overflow:hidden;">
    Pesanan #{{ $transaction->transaction_number }} berhasil dibuat — {{ $storeName }}
</div>

<!-- Outer wrapper -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color:#f0f4f8;">
    <tr>
        <td align="center" style="padding:32px 16px;">

            <!-- Email container -->
            <table class="email-container" role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" style="max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 32px rgba(0,0,0,0.10);">

                {{-- ══════════ HEADER ══════════ --}}
                <tr>
                    <td style="background:linear-gradient(135deg,#1e293b 0%,#334155 100%);padding:32px 40px;text-align:center;">
                        @if($storeLogo)
                            <img src="{{ $appUrl . '/storage/' . $storeLogo }}" alt="{{ $storeName }}" height="48" style="max-height:48px;margin-bottom:12px;display:block;margin-left:auto;margin-right:auto;" />
                        @endif
                        <div style="color:#ffffff;font-size:22px;font-weight:800;letter-spacing:-0.5px;">{{ $storeName }}</div>
                        <div style="color:rgba(255,255,255,0.65);font-size:13px;margin-top:4px;">Konfirmasi Pesanan</div>
                    </td>
                </tr>

                {{-- ══════════ SUCCESS BANNER ══════════ --}}
                <tr>
                    <td style="background:linear-gradient(135deg,#10b981 0%,#059669 100%);padding:28px 40px;text-align:center;">
                        <div style="font-size:40px;margin-bottom:10px;">✅</div>
                        <div style="color:#ffffff;font-size:22px;font-weight:800;margin-bottom:6px;">Pesanan Berhasil Dibuat!</div>
                        <div style="color:rgba(255,255,255,0.90);font-size:14px;">Halo <strong>{{ $transaction->user->name }}</strong>, pesananmu sudah kami terima dengan baik.</div>
                    </td>
                </tr>

                {{-- ══════════ BODY ══════════ --}}
                <tr>
                    <td style="padding:32px 40px;">

                        {{-- Order Meta --}}
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#f8fafc;border-radius:12px;border:1px solid #e2e8f0;margin-bottom:28px;">
                            <tr>
                                <td style="padding:16px 20px;">
                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                        <tr>
                                            <td width="33%" style="vertical-align:top;">
                                                <div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.8px;margin-bottom:5px;">No. Pesanan</div>
                                                <div style="font-size:14px;font-weight:800;color:#1e293b;">#{{ $transaction->transaction_number }}</div>
                                            </td>
                                            <td width="33%" style="vertical-align:top;text-align:center;">
                                                <div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.8px;margin-bottom:5px;">Tanggal</div>
                                                <div style="font-size:14px;font-weight:800;color:#1e293b;">{{ $transaction->created_at->format('d M Y') }}</div>
                                            </td>
                                            <td width="33%" style="vertical-align:top;text-align:right;">
                                                <div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.8px;margin-bottom:5px;">Status</div>
                                                <div style="font-size:14px;font-weight:800;color:#f59e0b;">Belum Bayar</div>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>

                        {{-- Section: Products --}}
                        <div style="font-size:11px;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:0.8px;margin-bottom:14px;padding-bottom:10px;border-bottom:2px solid #f1f5f9;">🛍️&nbsp; Produk yang Dipesan</div>

                        @foreach($transaction->items as $item)
                        @php
                            $imgPath = $item->product_image;
                            $imgUrl  = $imgPath
                                ? (str_starts_with($imgPath, 'http') ? $imgPath : $appUrl . '/storage/' . $imgPath)
                                : null;
                        @endphp
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:14px;padding-bottom:14px;border-bottom:1px solid #f1f5f9;">
                            <tr>
                                {{-- Product image --}}
                                <td width="80" style="vertical-align:top;padding-right:16px;">
                                    @if($imgUrl)
                                        <img class="product-img" src="{{ $imgUrl }}" alt="{{ $item->product_name }}" width="80" height="80" style="width:80px;height:80px;border-radius:10px;object-fit:cover;border:1px solid #e2e8f0;display:block;" />
                                    @else
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="80" height="80" style="background:#f1f5f9;border-radius:10px;border:1px solid #e2e8f0;">
                                            <tr><td align="center" valign="middle" style="font-size:28px;">📦</td></tr>
                                        </table>
                                    @endif
                                </td>
                                {{-- Product info --}}
                                <td style="vertical-align:top;">
                                    <div style="font-size:14px;font-weight:700;color:#1e293b;line-height:1.4;margin-bottom:5px;">{{ $item->product_name }}</div>
                                    @if($item->variant_name)
                                        <div style="display:inline-block;font-size:11px;font-weight:600;color:#6366f1;background:#eef2ff;border-radius:4px;padding:2px 8px;margin-bottom:6px;">{{ $item->variant_name }}</div>
                                    @endif
                                    <div style="font-size:12px;color:#64748b;margin-top:4px;">
                                        Qty: <strong>{{ $item->quantity }}</strong>
                                        &nbsp;&times;&nbsp;
                                        Rp {{ number_format($item->harga_akhir, 0, ',', '.') }}
                                    </div>
                                </td>
                                {{-- Price --}}
                                <td style="vertical-align:top;text-align:right;white-space:nowrap;padding-left:12px;">
                                    <div style="font-size:14px;font-weight:800;color:#1e293b;">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</div>
                                </td>
                            </tr>
                        </table>
                        @endforeach

                        {{-- Section: Payment Summary --}}
                        <div style="font-size:11px;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:0.8px;margin-top:8px;margin-bottom:14px;padding-bottom:10px;border-bottom:2px solid #f1f5f9;">💰&nbsp; Ringkasan Pembayaran</div>

                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#f8fafc;border-radius:12px;border:1px solid #e2e8f0;margin-bottom:28px;">
                            <tr>
                                <td style="padding:18px 20px;">
                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                        <tr>
                                            <td style="font-size:13px;color:#475569;padding:4px 0;">Subtotal</td>
                                            <td style="font-size:13px;color:#475569;padding:4px 0;text-align:right;">Rp {{ number_format($transaction->subtotal, 0, ',', '.') }}</td>
                                        </tr>
                                        <tr>
                                            <td style="font-size:13px;color:#475569;padding:4px 0;">
                                                Ongkos Kirim
                                                @if($transaction->shipping_courier)
                                                    ({{ strtoupper($transaction->shipping_courier) }} {{ $transaction->shipping_service }})
                                                @endif
                                            </td>
                                            <td style="font-size:13px;color:#475569;padding:4px 0;text-align:right;">Rp {{ number_format($transaction->shipping_fee, 0, ',', '.') }}</td>
                                        </tr>
                                        @if($transaction->shipping_discount > 0)
                                        <tr>
                                            <td style="font-size:13px;color:#10b981;padding:4px 0;">Diskon Ongkir</td>
                                            <td style="font-size:13px;color:#10b981;padding:4px 0;text-align:right;">- Rp {{ number_format($transaction->shipping_discount, 0, ',', '.') }}</td>
                                        </tr>
                                        @endif
                                        @if($transaction->discount_amount > 0)
                                        <tr>
                                            <td style="font-size:13px;color:#10b981;padding:4px 0;">Diskon Voucher ({{ $transaction->voucher_code }})</td>
                                            <td style="font-size:13px;color:#10b981;padding:4px 0;text-align:right;">- Rp {{ number_format($transaction->discount_amount, 0, ',', '.') }}</td>
                                        </tr>
                                        @endif
                                        @if($transaction->admin_fee > 0)
                                        <tr>
                                            <td style="font-size:13px;color:#475569;padding:4px 0;">Biaya Admin</td>
                                            <td style="font-size:13px;color:#475569;padding:4px 0;text-align:right;">Rp {{ number_format($transaction->admin_fee, 0, ',', '.') }}</td>
                                        </tr>
                                        @endif
                                        <tr>
                                            <td colspan="2" style="padding:0;"><div style="height:1px;background:#e2e8f0;margin:10px 0;"></div></td>
                                        </tr>
                                        <tr>
                                            <td style="font-size:16px;font-weight:800;color:#1e293b;padding:4px 0;">Total Bayar</td>
                                            <td style="font-size:16px;font-weight:800;color:#1e293b;padding:4px 0;text-align:right;">Rp {{ number_format($transaction->grand_total, 0, ',', '.') }}</td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>

                        {{-- Section: Shipping Info --}}
                        <div style="font-size:11px;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:0.8px;margin-bottom:14px;padding-bottom:10px;border-bottom:2px solid #f1f5f9;">🚚&nbsp; Informasi Pengiriman</div>

                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#f8fafc;border-radius:12px;border:1px solid #e2e8f0;margin-bottom:32px;">
                            <tr>
                                <td style="padding:18px 20px;">
                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                        <tr>
                                            <td width="90" style="font-size:12px;font-weight:600;color:#94a3b8;vertical-align:top;padding:5px 12px 5px 0;">Penerima</td>
                                            <td style="font-size:13px;color:#334155;font-weight:500;padding:5px 0;">{{ $transaction->customerAddress->recipient_name ?? $transaction->user->name }}</td>
                                        </tr>
                                        @if($transaction->customerAddress->phone ?? false)
                                        <tr>
                                            <td style="font-size:12px;font-weight:600;color:#94a3b8;vertical-align:top;padding:5px 12px 5px 0;">Telepon</td>
                                            <td style="font-size:13px;color:#334155;font-weight:500;padding:5px 0;">{{ $transaction->customerAddress->phone }}</td>
                                        </tr>
                                        @endif
                                        <tr>
                                            <td style="font-size:12px;font-weight:600;color:#94a3b8;vertical-align:top;padding:5px 12px 5px 0;">Alamat</td>
                                            <td style="font-size:13px;color:#334155;font-weight:500;padding:5px 0;line-height:1.6;">
                                                @php
                                                    $addr = $transaction->customerAddress;
                                                    $parts = array_filter([
                                                        $addr->address ?? null,
                                                        $addr->district ?? null,
                                                        $addr->city ?? null,
                                                        $addr->province ?? null,
                                                        $addr->postal_code ?? null,
                                                    ]);
                                                @endphp
                                                {{ implode(', ', $parts) ?: '-' }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="font-size:12px;font-weight:600;color:#94a3b8;vertical-align:top;padding:5px 12px 5px 0;">Kurir</td>
                                            <td style="font-size:13px;color:#334155;font-weight:500;padding:5px 0;">
                                                {{ strtoupper($transaction->shipping_courier) }} — {{ $transaction->shipping_service }}
                                                @if($transaction->shipping_etd)
                                                    <br /><span style="font-size:11px;color:#94a3b8;">Estimasi {{ $transaction->shipping_etd }} hari kerja</span>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="font-size:12px;font-weight:600;color:#94a3b8;vertical-align:top;padding:5px 12px 5px 0;">Pembayaran</td>
                                            <td style="font-size:13px;color:#334155;font-weight:500;padding:5px 0;">{{ $transaction->paymentMethod->name ?? '-' }}</td>
                                        </tr>
                                        @if($transaction->notes)
                                        <tr>
                                            <td style="font-size:12px;font-weight:600;color:#94a3b8;vertical-align:top;padding:5px 12px 5px 0;">Catatan</td>
                                            <td style="font-size:13px;color:#334155;font-weight:500;padding:5px 0;">{{ $transaction->notes }}</td>
                                        </tr>
                                        @endif
                                    </table>
                                </td>
                            </tr>
                        </table>

                        {{-- CTA Button --}}
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:8px;">
                            <tr>
                                <td align="center">
                                    <a href="{{ $appUrl }}/transactions/{{ $transaction->id }}"
                                       style="display:inline-block;background:linear-gradient(135deg,#6366f1 0%,#4f46e5 100%);color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;padding:15px 48px;border-radius:50px;letter-spacing:0.3px;mso-padding-alt:0;text-align:center;">
                                        <!--[if mso]><i style="letter-spacing:48px;mso-font-width:-100%;mso-text-raise:20pt">&nbsp;</i><![endif]-->
                                        Lihat Detail Pesanan &rarr;
                                        <!--[if mso]><i style="letter-spacing:48px;mso-font-width:-100%">&nbsp;</i><![endif]-->
                                    </a>
                                </td>
                            </tr>
                        </table>

                    </td>
                </tr>

                {{-- ══════════ FOOTER ══════════ --}}
                <tr>
                    <td style="background:#f8fafc;border-top:1px solid #e2e8f0;padding:24px 40px;text-align:center;">
                        <p style="margin:0;font-size:12px;color:#94a3b8;line-height:1.8;">
                            Email ini dikirim otomatis oleh <strong style="color:#64748b;">{{ $storeName }}</strong>.<br />
                            Harap tidak membalas email ini. Untuk bantuan, hubungi kami melalui aplikasi.<br />
                            <br />
                            &copy; {{ date('Y') }} {{ $storeName }}. All rights reserved.
                        </p>
                    </td>
                </tr>

            </table>
            {{-- / Email container --}}

        </td>
    </tr>
</table>

</body>
</html>
