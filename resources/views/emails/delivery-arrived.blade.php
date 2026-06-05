<!DOCTYPE html>
<html lang="id" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Paket Anda Telah Tiba</title>
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
                $logoUrl = $message->embed($storagePath);
            } elseif (file_exists($publicPath)) {
                $logoUrl = $message->embed($publicPath);
            } else {
                $logoUrl = $appUrl . '/' . $cleanLogoPath;
            }
        }
    }

    $customerName = $transaction->user?->name ?? 'Pelanggan';
    $completeUrl = $appUrl . '/transactions/' . $transaction->id;
@endphp

<!-- Email Wrapper -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color:#f5f8fa;">
    <tr>
        <td style="padding: 40px 20px;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="max-width:600px;margin:0 auto;">

                <!-- Header -->
                <tr>
                    <td style="background:linear-gradient(135deg,#0c4cb4,#2563eb);border-radius:16px 16px 0 0;padding:32px 40px;text-align:center;">
                        @if($logoUrl)
                            <img src="{{ $logoUrl }}" alt="{{ $storeName }}" style="height:48px;width:auto;object-fit:contain;margin-bottom:20px;border-radius:8px;" />
                        @endif
                        <div style="width:72px;height:72px;background:rgba(255,255,255,0.15);border-radius:50%;margin:0 auto 16px;display:flex;align-items:center;justify-content:center;font-size:36px;line-height:72px;text-align:center;">
                            📦
                        </div>
                        <h1 style="margin:0;font-size:24px;font-weight:700;color:#ffffff;letter-spacing:-0.5px;">Paket Anda Telah Tiba!</h1>
                        <p style="margin:8px 0 0;font-size:15px;color:rgba(255,255,255,0.8);">Kurir toko telah mengantarkan pesanan Anda</p>
                    </td>
                </tr>

                <!-- Body -->
                <tr>
                    <td style="background:#ffffff;padding:36px 40px;">
                        <p style="margin:0 0 16px;font-size:15px;color:#374151;">
                            Halo, <strong>{{ $customerName }}</strong>! 👋
                        </p>
                        <p style="margin:0 0 24px;font-size:15px;color:#374151;line-height:1.6;">
                            Kurir toko kami telah berhasil mengantarkan paket pesanan
                            <strong>#{{ $transaction->transaction_number }}</strong> ke alamat tujuan Anda.
                        </p>

                        <!-- Status Box -->
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:28px;">
                            <tr>
                                <td style="background:#f0fdf4;border:1px solid #86efac;border-radius:12px;padding:20px 24px;">
                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                        <tr>
                                            <td width="44" style="vertical-align:top;padding-right:12px;">
                                                <div style="width:40px;height:40px;background:#22c55e;border-radius:50%;text-align:center;line-height:40px;font-size:20px;">✓</div>
                                            </td>
                                            <td style="vertical-align:top;">
                                                <p style="margin:0 0 4px;font-size:14px;font-weight:700;color:#15803d;">Status Pengiriman</p>
                                                <p style="margin:0;font-size:16px;font-weight:800;color:#166534;">✅ Paket Telah Tiba di Tujuan</p>
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
                                    <p style="margin:0;font-size:13px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.5px;">Informasi Pesanan</p>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:20px;">
                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                        <tr>
                                            <td style="padding:6px 0;font-size:14px;color:#6b7280;width:50%;">No. Transaksi</td>
                                            <td style="padding:6px 0;font-size:14px;font-weight:700;color:#111827;text-align:right;">#{{ $transaction->transaction_number }}</td>
                                        </tr>
                                        @if($transaction->tracking_number)
                                        <tr>
                                            <td style="padding:6px 0;font-size:14px;color:#6b7280;">No. Resi</td>
                                            <td style="padding:6px 0;font-size:14px;font-weight:700;color:#111827;text-align:right;">{{ $transaction->tracking_number }}</td>
                                        </tr>
                                        @endif
                                        @if($transaction->customerAddress)
                                        <tr>
                                            <td style="padding:6px 0;font-size:14px;color:#6b7280;vertical-align:top;">Alamat</td>
                                            <td style="padding:6px 0;font-size:14px;color:#111827;text-align:right;">{{ $transaction->customerAddress->address }}</td>
                                        </tr>
                                        @endif
                                        <tr>
                                            <td style="padding:6px 0;font-size:14px;color:#6b7280;">Tiba Pada</td>
                                            <td style="padding:6px 0;font-size:14px;font-weight:700;color:#111827;text-align:right;">{{ now()->locale('id')->isoFormat('D MMMM YYYY, HH:mm') }} WIB</td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>

                        <!-- Important Note -->
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:28px;">
                            <tr>
                                <td style="background:#fffbeb;border:1px solid #fcd34d;border-radius:12px;padding:18px 20px;">
                                    <p style="margin:0 0 8px;font-size:14px;font-weight:700;color:#92400e;">⚠️ Penting — Konfirmasi Pesanan</p>
                                    <p style="margin:0;font-size:14px;color:#78350f;line-height:1.6;">
                                        Jika paket sudah Anda terima dan kondisinya baik, jangan lupa untuk mengklik
                                        <strong>"Pesanan Selesai"</strong> agar pesanan dapat ditutup dan Anda mendapatkan poin reward!
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <!-- CTA Button -->
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:8px;">
                            <tr>
                                <td style="text-align:center;padding:8px 0;">
                                    <a href="{{ $completeUrl }}" style="display:inline-block;background:linear-gradient(135deg,#0c4cb4,#2563eb);color:#ffffff;font-size:15px;font-weight:700;text-decoration:none;padding:16px 40px;border-radius:12px;letter-spacing:0.3px;">
                                        ✅ Konfirmasi Pesanan Selesai
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <p style="margin:16px 0 0;font-size:13px;color:#9ca3af;text-align:center;">
                            Atau salin link ini ke browser Anda:<br/>
                            <span style="color:#2563eb;">{{ $completeUrl }}</span>
                        </p>
                    </td>
                </tr>

                <!-- Footer -->
                <tr>
                    <td style="background:#f9fafb;border-top:1px solid #e5e7eb;border-radius:0 0 16px 16px;padding:24px 40px;text-align:center;">
                        <p style="margin:0 0 4px;font-size:14px;font-weight:700;color:#374151;">{{ $storeName }}</p>
                        <p style="margin:0;font-size:12px;color:#9ca3af;">
                            Email ini dikirim otomatis. Jika Anda tidak memesan di toko kami, abaikan email ini.
                        </p>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>

</body>
</html>
