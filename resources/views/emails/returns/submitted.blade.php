<!DOCTYPE html>
<html lang="id" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Pengajuan Retur Berhasil</title>
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
gi
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
                $logoUrl = $appUrl . "/storage/" . $cleanLogoPath;
            } elseif (file_exists($publicPath)) {
                $logoUrl = $appUrl . "/" . $cleanLogoPath;
            } else {
                $logoUrl = $appUrl . '/' . $cleanLogoPath;
            }
        }
    }
@endphp

<!-- Preheader -->
<div style="display:none;font-size:1px;color:#f5f8fa;line-height:1px;max-height:0px;max-width:0px;opacity:0;overflow:hidden;">
    Pengajuan retur #{{ $returnRequest->return_number }} berhasil dikirim — {{ $storeName }}
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

                        <p style="font-size:15px;margin:0 0 12px 0;color:#222222;">Hai <strong>{{ $returnRequest->user->name }}</strong>,</p>
                        <p style="font-size:14px;line-height:1.5;margin:0 0 25px 0;color:#444444;">
                            Pengajuan retur Anda <strong style="color:#0056b3;">#{{ $returnRequest->return_number }}</strong> untuk pesanan <strong>#{{ $returnRequest->transaction->transaction_number }}</strong> telah berhasil dikirim dan saat ini sedang menunggu review oleh admin.
                        </p>

                        {{-- Section: Return Details --}}
                        <div style="font-size:13px;font-weight:bold;color:#444444;text-transform:uppercase;margin-bottom:12px;border-bottom:1px solid #eeeeee;padding-bottom:6px;letter-spacing:0.5px;">
                            Rincian Retur
                        </div>

                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:20px;font-size:13px;line-height:1.6;">
                            <tr>
                                <td width="130" style="color:#777777;padding:3px 0;vertical-align:top;">No. Retur:</td>
                                <td style="color:#0056b3;font-weight:bold;padding:3px 0;vertical-align:top;">#{{ $returnRequest->return_number }}</td>
                            </tr>
                            <tr>
                                <td width="130" style="color:#777777;padding:3px 0;vertical-align:top;">No. Pesanan:</td>
                                <td style="color:#333333;font-weight:bold;padding:3px 0;vertical-align:top;">#{{ $returnRequest->transaction->transaction_number }}</td>
                            </tr>
                            <tr>
                                <td style="color:#777777;padding:3px 0;vertical-align:top;">Tanggal Pengajuan:</td>
                                <td style="color:#333333;padding:3px 0;vertical-align:top;">{{ $returnRequest->created_at->format('d M Y H:i') }} WIB</td>
                            </tr>
                            <tr>
                                <td style="color:#777777;padding:3px 0;vertical-align:top;">Jenis Retur:</td>
                                <td style="color:#333333;padding:3px 0;vertical-align:top;">{{ $returnRequest->type === 'refund' ? 'Pengembalian Dana (Refund)' : 'Penggantian Barang' }}</td>
                            </tr>
                            <tr>
                                <td style="color:#777777;padding:3px 0;vertical-align:top;">Alasan Retur:</td>
                                <td style="color:#333333;padding:3px 0;vertical-align:top;">{{ $returnRequest->reason }}</td>
                            </tr>
                            <tr>
                                <td style="color:#777777;padding:3px 0;vertical-align:top;">Status:</td>
                                <td style="color:#0056b3;font-weight:bold;padding:3px 0;vertical-align:top;">Menunggu Review</td>
                            </tr>
                        </table>

                        {{-- Section: Products --}}
                        <div style="font-size:13px;font-weight:bold;color:#444444;text-transform:uppercase;margin-bottom:12px;border-bottom:1px solid #eeeeee;padding-bottom:6px;letter-spacing:0.5px;">
                            Produk Diretur
                        </div>

                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="border-collapse:collapse;margin-bottom:20px;">
                            @foreach($returnRequest->items as $item)
                            <tr style="border-bottom:1px solid #f0f0f0;">
                                {{-- Product info --}}
                                <td style="padding:10px 0;vertical-align:top;font-size:13px;line-height:1.4;">
                                    <div style="font-weight:bold;color:#333333;margin-bottom:3px;">{{ $item->product_name }}</div>
                                    @if($item->variant_name)
                                        <div style="font-size:11px;color:#666666;margin-bottom:3px;">Variasi: {{ $item->variant_name }}</div>
                                    @endif
                                    <div style="color:#888888;">Jumlah: {{ $item->quantity_returned }}</div>
                                </td>
                                {{-- Price --}}
                                <td align="right" style="padding:10px 0;vertical-align:top;font-size:13px;font-weight:bold;color:#333333;white-space:nowrap;">
                                    Rp {{ number_format($item->refund_subtotal, 0, ',', '.') }}
                                </td>
                            </tr>
                            @endforeach
                            @if($returnRequest->type === 'refund')
                            <tr>
                                <td style="padding:15px 0 0 0;font-size:15px;font-weight:bold;color:#222222;border-top:1px solid #eeeeee;">Total Pengembalian Dana</td>
                                <td align="right" style="padding:15px 0 0 0;font-size:15px;font-weight:bold;color:#0056b3;border-top:1px solid #eeeeee;">
                                    Rp {{ number_format($returnRequest->refund_amount, 0, ',', '.') }}
                                </td>
                            </tr>
                            @endif
                        </table>

                        {{-- Button CTA --}}
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" style="margin:30px auto 10px auto;">
                            <tr>
                                <td align="center" bgcolor="#0056b3" style="border-radius:4px;">
                                    <a href="{{ $appUrl }}/transactions/{{ $returnRequest->transaction_id }}" target="_blank" style="font-size:15px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#ffffff;text-decoration:none;border-radius:4px;padding:12px 35px;border:1px solid #0056b3;display:inline-block;font-weight:bold;">
                                        Lihat Status Retur
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
