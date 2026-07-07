<!DOCTYPE html>
<html lang="id" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>Pembaruan Status Pesanan</title>
    <style type="text/css">
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        body { margin: 0 !important; padding: 0 !important; background-color: #f5f8fa; }
        @media only screen and (max-width: 600px) {
            .email-container { width: 100% !important; max-width: 100% !important; }
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

    $customerName = $transaction->user?->name ?? 'Pelanggan';
    $detailUrl = $appUrl . '/transactions/' . $transaction->id;
    $isSelfPickupReady = ($transaction->shipping_courier === 'self_pickup' && $transaction->status === 'out_for_pickup');

    $statusLabels = [
        'belum_bayar' => 'Belum Bayar',
        'menunggu' => 'Menunggu Konfirmasi',
        'diproses' => 'Sedang Diproses',
        'dikemas' => 'Sedang Dikemas',
        'out_for_pickup' => $transaction->shipping_courier === 'self_pickup' ? 'Siap Diambil' : 'Out for Pickup',
        'dikirim' => 'Sedang Dikirim',
        'selesai' => 'Selesai',
        'batal' => 'Dibatalkan',
    ];

    $statusText = $statusLabels[$transaction->status] ?? ucfirst($transaction->status);
@endphp

<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color:#f5f8fa;">
    <tr>
        <td style="padding: 40px 20px;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e5e7eb;">

                @if($isSelfPickupReady)
                    <!-- Header for Ready for Pickup -->
                    <tr>
                        <td style="background:linear-gradient(135deg,#10b981,#059669);padding:32px 40px;text-align:center;color:#ffffff;">
                            <div style="width:72px;height:72px;background:rgba(255,255,255,0.15);border-radius:50%;margin:0 auto 16px;display:flex;align-items:center;justify-content:center;font-size:36px;line-height:72px;text-align:center;">
                                🏪
                            </div>
                            <h1 style="margin:0;font-size:24px;font-weight:700;letter-spacing:-0.5px;">Pesanan Siap Diambil!</h1>
                            <p style="margin:8px 0 0;font-size:15px;color:rgba(255,255,255,0.9);">Silakan kunjungi toko kami untuk mengambil pesanan Anda</p>
                        </td>
                    </tr>
                @else
                    <!-- General Header -->
                    <tr>
                        <td style="background:linear-gradient(135deg,#0c4cb4,#2563eb);padding:32px 40px;text-align:center;color:#ffffff;">
                            <div style="width:72px;height:72px;background:rgba(255,255,255,0.15);border-radius:50%;margin:0 auto 16px;display:flex;align-items:center;justify-content:center;font-size:36px;line-height:72px;text-align:center;">
                                🔔
                            </div>
                            <h1 style="margin:0;font-size:24px;font-weight:700;letter-spacing:-0.5px;">Status Pesanan Diperbarui</h1>
                            <p style="margin:8px 0 0;font-size:15px;color:rgba(255,255,255,0.9);">Pesanan #{{ $transaction->transaction_number }} telah berubah status</p>
                        </td>
                    </tr>
                @endif

                <!-- Body -->
                <tr>
                    <td style="background:#ffffff;padding:36px 40px;">
                        <p style="margin:0 0 16px;font-size:15px;color:#374151;">
                            Halo, <strong>{{ $customerName }}</strong>! 👋
                        </p>

                        @if($isSelfPickupReady)
                            <p style="margin:0 0 24px;font-size:15px;color:#374151;line-height:1.6;">
                                Kabar baik! Pesanan Anda dengan nomor transaksi <strong>#{{ $transaction->transaction_number }}</strong> saat ini telah selesai dikemas dan siap untuk diambil di toko kami.
                            </p>

                            <!-- Store Pickup Info Box -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:28px;">
                                <tr>
                                    <td style="background:#ecfdf5;border:1px solid #a7f3d0;border-radius:12px;padding:20px 24px;">
                                        <h3 style="margin:0 0 8px;font-size:14px;font-weight:700;color:#065f46;">🏪 Alamat Pengambilan Barang</h3>
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
                                        <p style="margin:0 0 16px;font-size:14px;color:#065f46;line-height:1.5;">
                                            <strong>{{ $storeName }}</strong><br/>
                                            {{ $fullStoreAddress ?: 'Alamat toko belum diatur.' }}
                                        </p>
                                        
                                        <div style="text-align:center;padding:12px;background:#ffffff;border:1px solid #d1fae5;border-radius:8px;">
                                            <p style="margin:0 0 8px;font-size:11px;font-weight:700;color:#065f46;text-transform:uppercase;letter-spacing:0.5px;">Tunjukkan QR Code ini kepada kasir/petugas:</p>
                                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data={{ urlencode($transaction->transaction_number) }}" alt="QR Code Transaksi" width="150" height="150" style="display:inline-block;border-radius:6px;" />
                                            <p style="margin:8px 0 0;font-size:12px;font-weight:bold;color:#374151;font-family:monospace;">{{ $transaction->transaction_number }}</p>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        @else
                            <p style="margin:0 0 24px;font-size:15px;color:#374151;line-height:1.6;">
                                Kami ingin menginformasikan bahwa status pesanan Anda <strong>#{{ $transaction->transaction_number }}</strong> saat ini diperbarui menjadi:
                            </p>

                            <!-- Status Box -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:28px;">
                                <tr>
                                    <td style="background:#f3f4f6;border:1px solid #e5e7eb;border-radius:12px;padding:20px 24px;text-align:center;">
                                        <span style="font-size:13px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.5px;display:block;margin-bottom:4px;">Status Saat Ini</span>
                                        <span style="font-size:20px;font-weight:800;color:#1e40af;">💼 {{ $statusText }}</span>
                                    </td>
                                </tr>
                            </table>
                        @endif

                        <!-- Order Info Card -->
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="border:1px solid #e5e7eb;border-radius:12px;margin-bottom:28px;overflow:hidden;">
                            <tr>
                                <td style="background:#f9fafb;padding:14px 20px;border-bottom:1px solid #e5e7eb;">
                                    <p style="margin:0;font-size:13px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.5px;">Rincian Pesanan</p>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:20px;">
                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                        @foreach($transaction->items as $item)
                                            <tr>
                                                <td style="padding:6px 0;font-size:14px;color:#374151;vertical-align:top;">
                                                    <strong>{{ $item->product_name }}</strong>
                                                    @if($item->variant_name)
                                                        <br/><span style="font-size:11px;color:#6b7280;">Variasi: {{ $item->variant_name }}</span>
                                                    @endif
                                                    <br/><span style="font-size:12px;color:#9ca3af;">x{{ $item->quantity }}</span>
                                                </td>
                                                <td style="padding:6px 0;font-size:14px;font-weight:700;color:#111827;text-align:right;vertical-align:top;">
                                                    Rp {{ number_format($item->harga_akhir * $item->quantity, 0, ',', '.') }}
                                                </td>
                                            </tr>
                                        @endforeach
                                        <tr>
                                            <td colspan="2" style="padding:10px 0 0 0;border-top:1px solid #f3f4f6;"></td>
                                        </tr>
                                        <tr>
                                            <td style="padding:4px 0;font-size:13px;color:#6b7280;">Subtotal Produk</td>
                                            <td style="padding:4px 0;font-size:13px;color:#111827;text-align:right;">Rp {{ number_format($transaction->subtotal, 0, ',', '.') }}</td>
                                        </tr>
                                        @if($transaction->shipping_fee > 0)
                                            <tr>
                                                <td style="padding:4px 0;font-size:13px;color:#6b7280;">Ongkos Kirim</td>
                                                <td style="padding:4px 0;font-size:13px;color:#111827;text-align:right;">Rp {{ number_format($transaction->shipping_fee, 0, ',', '.') }}</td>
                                            </tr>
                                        @endif
                                        @if($transaction->discount_amount > 0)
                                            <tr>
                                                <td style="padding:4px 0;font-size:13px;color:#10b981;">Diskon Voucher</td>
                                                <td style="padding:4px 0;font-size:13px;color:#10b981;text-align:right;">-Rp {{ number_format($transaction->discount_amount, 0, ',', '.') }}</td>
                                            </tr>
                                        @endif
                                        <tr>
                                            <td style="padding:10px 0 0 0;font-size:15px;font-weight:800;color:#111827;">Total</td>
                                            <td style="padding:10px 0 0 0;font-size:15px;font-weight:800;color:#2563eb;text-align:right;">Rp {{ number_format($transaction->grand_total, 0, ',', '.') }}</td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>

                        <!-- CTA Button -->
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom:8px;">
                            <tr>
                                <td style="text-align:center;padding:8px 0;">
                                    <a href="{{ $detailUrl }}" style="display:inline-block;background:linear-gradient(135deg,#0c4cb4,#2563eb);color:#ffffff;font-size:15px;font-weight:700;text-decoration:none;padding:16px 40px;border-radius:12px;letter-spacing:0.3px;">
                                        Lihat Rincian Pesanan
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <p style="margin:16px 0 0;font-size:13px;color:#9ca3af;text-align:center;">
                            Atau salin link ini ke browser Anda:<br/>
                            <span style="color:#2563eb;">{{ $detailUrl }}</span>
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
