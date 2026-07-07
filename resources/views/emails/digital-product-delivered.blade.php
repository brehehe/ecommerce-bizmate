<!DOCTYPE html>
<html lang="id" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Informasi Pengiriman Produk Digital</title>
    <style type="text/css">
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        body { margin: 0 !important; padding: 0 !important; background-color: #f5f8fa; }
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

    $customerName = $transaction->user?->name ?? 'Pelanggan';
    $detailUrl = $appUrl . '/transactions/' . $transaction->id;
@endphp

<!-- Email Wrapper -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color:#f5f8fa;">
    <tr>
        <td style="padding: 40px 20px;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="max-width:600px;margin:0 auto;">

                <!-- Header -->
                <tr>
                    <td style="background:linear-gradient(135deg,#0c4cb4,#2563eb);border-radius:16px 16px 0 0;padding:32px 40px;text-align:center;">
                        <div style="width:72px;height:72px;background:rgba(255,255,255,0.15);border-radius:50%;margin:0 auto 16px;display:flex;align-items:center;justify-content:center;font-size:36px;line-height:72px;text-align:center;">
                            🔑
                        </div>
                        <h1 style="margin:0;font-size:24px;font-weight:700;color:#ffffff;letter-spacing:-0.5px;">Produk Digital Anda Telah Dikirim!</h1>
                        <p style="margin:8px 0 0;font-size:15px;color:rgba(255,255,255,0.8);">Lisensi / informasi produk Anda dapat dilihat di bawah ini</p>
                    </td>
                </tr>

                <!-- Body -->
                <tr>
                    <td style="background:#ffffff;padding:36px 40px;">
                        <p style="margin:0 0 16px;font-size:15px;color:#374151;">
                            Halo, <strong>{{ $customerName }}</strong>! 👋
                        </p>
                        <p style="margin:0 0 24px;font-size:15px;color:#374151;line-height:1.6;">
                            Informasi pengiriman digital untuk produk <strong>{{ $item->product_name }}</strong> pada pesanan <strong>#{{ $transaction->transaction_number }}</strong> Anda telah diperbarui oleh toko kami.
                        </p>

                        <!-- Digital Info Box -->
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:28px;">
                            <tr>
                                <td style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:12px;padding:20px 24px;">
                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                        <tr>
                                            <td style="vertical-align:top;">
                                                <p style="margin:0 0 6px;font-size:13px;font-weight:700;color:#1d4ed8;text-transform:uppercase;letter-spacing:0.5px;">Informasi / Lisensi / Link Download:</p>
                                                
                                                @if (str_starts_with(strtolower($item->note), 'http://') || str_starts_with(strtolower($item->note), 'https://'))
                                                    <p style="margin:4px 0 0;font-size:16px;font-weight:800;color:#1e3a8a;word-break:break-all;">
                                                        <a href="{{ $item->note }}" target="_blank" style="color:#2563eb;text-decoration:underline;">{{ $item->note }}</a>
                                                    </p>
                                                @else
                                                    <p style="margin:4px 0 0;font-size:16px;font-family:Consolas, Monaco, monospace;font-weight:800;color:#1e3a8a;word-break:break-all;background:#ffffff;border:1px dashed #bfdbfe;padding:8px 12px;border-radius:6px;">
                                                        {{ $item->note }}
                                                    </p>
                                                @endif
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>

                        <!-- Order Info -->
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="border:1px solid #e5e7eb;border-radius:12px;margin-bottom:28px;overflow:hidden;">
                            <tr>
                                <td style="background:#f9fafb;padding:14px 20px;border-bottom:1px solid #e5e7eb;">
                                    <p style="margin:0;font-size:13px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.5px;">Detail Pesanan</p>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:20px;">
                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                        <tr>
                                            <td style="padding:6px 0;font-size:14px;color:#6b7280;width:40%;">No. Transaksi</td>
                                            <td style="padding:6px 0;font-size:14px;font-weight:700;color:#111827;text-align:right;">#{{ $transaction->transaction_number }}</td>
                                        </tr>
                                        <tr>
                                            <td style="padding:6px 0;font-size:14px;color:#6b7280;width:40%;">Nama Produk</td>
                                            <td style="padding:6px 0;font-size:14px;font-weight:700;color:#111827;text-align:right;">{{ $item->product_name }}</td>
                                        </tr>
                                        @if ($item->variant_name)
                                            <tr>
                                                <td style="padding:6px 0;font-size:14px;color:#6b7280;width:40%;">Varian</td>
                                                <td style="padding:6px 0;font-size:14px;font-weight:700;color:#111827;text-align:right;">{{ $item->variant_name }}</td>
                                            </tr>
                                        @endif
                                        <tr>
                                            <td style="padding:6px 0;font-size:14px;color:#6b7280;width:40%;">Jumlah Beli</td>
                                            <td style="padding:6px 0;font-size:14px;font-weight:700;color:#111827;text-align:right;">{{ $item->quantity }}x</td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>

                        <!-- CTA Button -->
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-top:10px;margin-bottom:20px;">
                            <tr>
                                <td style="text-align:center;">
                                    <a href="{{ $detailUrl }}" target="_blank" style="background-color:#0c4cb4;color:#ffffff;display:inline-block;padding:14px 28px;font-size:15px;font-weight:700;text-decoration:none;border-radius:12px;box-shadow:0 4px 12px rgba(12,76,180,0.25);">Lihat Detail Transaksi</a>
                                </td>
                            </tr>
                        </table>

                        <p style="margin:24px 0 0;font-size:14px;color:#6b7280;line-height:1.5;text-align:center;">
                            Jika Anda membutuhkan bantuan lebih lanjut, silakan hubungi tim dukungan kami.
                        </p>
                    </td>
                </tr>

                <!-- Footer -->
                <tr>
                    <td style="padding:32px 40px;text-align:center;">
                        @if ($logoUrl)
                            <img src="{{ $logoUrl }}" alt="{{ $storeName }}" style="max-height:36px;margin-bottom:16px;" />
                        @else
                            <p style="margin:0 0 16px;font-size:18px;font-weight:800;color:#374151;">{{ $storeName }}</p>
                        @endif
                        <p style="margin:0;font-size:12px;color:#9ca3af;">
                            &copy; {{ date('Y') }} {{ $storeName }}. All rights reserved.
                        </p>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>

</body>
</html>
