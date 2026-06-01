<!DOCTYPE html>
<html lang="id" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Update Status Pesanan</title>
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
                $logoUrl = $message->embed($storagePath);
            } elseif (file_exists($publicPath)) {
                $logoUrl = $message->embed($publicPath);
            } else {
                $logoUrl = $appUrl . '/' . $cleanLogoPath;
            }
        }
    }

    $statusText = match ($transaction->status) {
        'belum_bayar' => 'Menunggu Pembayaran',
        'menunggu' => 'Menunggu Konfirmasi',
        'diproses' => 'Sedang Diproses',
        'dikemas' => 'Sedang Dikemas',
        'dikirim' => 'Sedang Dikirim',
        'selesai' => 'Pesanan Selesai',
        'batal' => 'Dibatalkan',
        default => 'Status Diperbarui',
    };

    $statusDescription = match ($transaction->status) {
        'belum_bayar' => 'Pesanan Anda menunggu pembayaran. Silakan selesaikan pembayaran agar pesanan Anda dapat segera diproses.',
        'menunggu' => 'Pembayaran Anda telah kami terima dan saat ini sedang menunggu konfirmasi/verifikasi.',
        'diproses' => 'Pembayaran berhasil dikonfirmasi. Penjual sedang memproses pesanan Anda.',
        'dikemas' => 'Pesanan Anda saat ini sedang dikemas dengan rapi dan bersiap untuk diserahkan ke kurir.',
        'dikirim' => 'Kabar gembira! Pesanan Anda telah dikirim dan sedang dalam perjalanan ke alamat Anda.',
        'selesai' => 'Terima kasih! Pesanan Anda telah selesai dan diterima dengan baik. Selamat menikmati produk Anda!',
        'batal' => 'Pesanan Anda telah dibatalkan.' . ($transaction->cancel_reason ? ' Alasan: ' . $transaction->cancel_reason : ''),
        default => 'Status pesanan Anda telah diperbarui menjadi ' . $statusText,
    };
@endphp

<!-- Preheader -->
<div style="display:none;font-size:1px;color:#f5f8fa;line-height:1px;max-height:0px;max-width:0px;opacity:0;overflow:hidden;">
    Status pesanan #{{ $transaction->transaction_number }} kini {{ strtolower($statusText) }} — {{ $storeName }}
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
                        @if($logoUrl)
                            <img src="{{ $logoUrl }}" alt="{{ $storeName }}" height="35" style="max-height:35px;margin-bottom:8px;display:block;margin-left:auto;margin-right:auto;" />
                        @endif
                        <div style="color:#0056b3;font-size:20px;font-weight:bold;letter-spacing:0.5px;">{{ $storeName }}</div>
                    </td>
                </tr>

                {{-- ══════════ BODY ══════════ --}}
                <tr>
                    <td style="padding:30px 25px;">

                        <p style="font-size:15px;margin:0 0 12px 0;color:#222222;">Hai <strong>{{ $transaction->user->name }}</strong>,</p>
                        <p style="font-size:14px;line-height:1.5;margin:0 0 25px 0;color:#444444;">
                            Ada pembaruan untuk status pesanan Anda <strong style="color:#0056b3;">#{{ $transaction->transaction_number }}</strong>.
                        </p>

                        {{-- Status Highlight Card --}}
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color:#f2f7fd;border:1px solid #d4e3f5;border-radius:4px;margin-bottom:25px;">
                            <tr>
                                <td style="padding:20px;">
                                    <div style="font-size:12px;font-weight:bold;color:#666666;text-transform:uppercase;margin-bottom:5px;">Status Pesanan Saat Ini</div>
                                    <div style="font-size:18px;font-weight:bold;color:#0056b3;margin-bottom:10px;">{{ $statusText }}</div>
                                    <div style="font-size:13px;color:#333333;line-height:1.5;margin:0;">{{ $statusDescription }}</div>
                                </td>
                            </tr>
                        </table>

                        {{-- Resi / Courier Box (If Shipped) --}}
                        @if($transaction->status === 'dikirim' && $transaction->tracking_number)
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="border:1px solid #eeeeee;border-radius:4px;margin-bottom:25px;background-color:#fafafa;">
                            <tr>
                                <td style="padding:15px;">
                                    <div style="font-size:13px;font-weight:bold;color:#444444;margin-bottom:8px;">Informasi Pengiriman</div>
                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="font-size:13px;">
                                        <tr>
                                            <td width="100" style="color:#777777;padding:3px 0;">Kurir:</td>
                                            <td style="color:#333333;font-weight:bold;padding:3px 0;">
                                                {{ strtoupper($transaction->courier_name ?? $transaction->shipping_courier ?? '-') }} 
                                                @if($transaction->shipping_service)
                                                    ({{ $transaction->shipping_service }})
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="color:#777777;padding:3px 0;">No. Resi:</td>
                                            <td style="color:#0056b3;font-weight:bold;padding:3px 0;font-family:monospace;font-size:14px;">{{ $transaction->tracking_number }}</td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                        @endif

                        {{-- Section: Products --}}
                        <div style="font-size:13px;font-weight:bold;color:#444444;text-transform:uppercase;margin-bottom:12px;border-bottom:1px solid #eeeeee;padding-bottom:6px;letter-spacing:0.5px;">
                            Produk Dipesan
                        </div>

                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="border-collapse:collapse;margin-bottom:20px;">
                            @foreach($transaction->items as $item)
                            @php
                                $imgPath = $item->product_image;
                                $imgUrl = null;
                                if ($imgPath) {
                                    if (str_starts_with($imgPath, 'http://') || str_starts_with($imgPath, 'https://')) {
                                        $imgUrl = $imgPath;
                                    } else {
                                        $cleanPath = ltrim($imgPath, '/');
                                        $localPath = storage_path('app/public/' . $cleanPath);
                                        if (file_exists($localPath)) {
                                            $imgUrl = $message->embed($localPath);
                                        } else {
                                            $imgUrl = $appUrl . '/storage/' . $cleanPath;
                                        }
                                    }
                                }
                            @endphp
                            <tr style="border-bottom:1px solid #f0f0f0;">
                                {{-- Product image --}}
                                <td width="70" style="padding:10px 10px 10px 0;vertical-align:top;">
                                    @if($imgUrl)
                                        <img class="product-img" src="{{ $imgUrl }}" alt="{{ $item->product_name }}" width="60" height="60" style="width:60px;height:60px;border-radius:4px;object-fit:cover;border:1px solid #e8e8e8;display:block;" />
                                    @else
                                        <div style="width:60px;height:60px;background-color:#f5f5f5;border-radius:4px;border:1px solid #e8e8e8;text-align:center;line-height:60px;font-size:20px;color:#aaaaaa;">
                                            📦
                                        </div>
                                    @endif
                                </td>
                                {{-- Product info --}}
                                <td style="padding:10px 0;vertical-align:top;font-size:13px;line-height:1.4;">
                                    <div style="font-weight:bold;color:#333333;margin-bottom:3px;">{{ $item->product_name }}</div>
                                    @if($item->variant_name)
                                        <div style="font-size:11px;color:#666666;margin-bottom:3px;">Variasi: {{ $item->variant_name }}</div>
                                    @endif
                                    <div style="color:#888888;">x{{ $item->quantity }}</div>
                                </td>
                                {{-- Price --}}
                                <td align="right" style="padding:10px 0;vertical-align:top;font-size:13px;font-weight:bold;color:#333333;white-space:nowrap;">
                                    Rp {{ number_format($item->subtotal, 0, ',', '.') }}
                                </td>
                            </tr>
                            @endforeach
                        </table>

                        {{-- Total Pembayaran --}}
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="font-size:13px;margin-bottom:25px;line-height:1.6;">
                            <tr>
                                <td style="font-size:14px;font-weight:bold;color:#222222;">Total Pembayaran</td>
                                <td align="right" style="font-size:14px;font-weight:bold;color:#0056b3;">
                                    Rp {{ number_format($transaction->grand_total, 0, ',', '.') }}
                                </td>
                            </tr>
                        </table>

                        {{-- Section: Shipping Info --}}
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
