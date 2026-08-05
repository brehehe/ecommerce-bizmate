<?php

require 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Helvetica');
$options->set('isFontSubsettingEnabled', true);

$dompdf = new Dompdf($options);

// =====================================================================
// DATA PORTOFOLIO 10 SISTEM APLIKASI (BAHASA MUDAH DIPAHAMI & NON-TEKNIS)
// =====================================================================
$projects = [
    [
        'title'        => 'Bizmate POS & Inventory Toko',
        'subtitle'     => 'Aplikasi Toko Online & Kasir Terpadu',
        'category'     => 'Perdagangan & Toko Online',
        'url'          => 'https://bizmate.toti.my.id/',
        'image'        => '/var/www/html/toti/pdf_images/bizmate.jpg',
        'label_main'   => 'Tampilan Toko Online & Katalog Barang',
        'image_dash'   => '/var/www/html/toti/pdf_images/bizmate_dash.jpg',
        'label_dash'   => 'Panel Kasir Toko & Laporan Penjualan',
        'price'        => 'Rp 10.000.000 – Rp 25.000.000',
        'license'      => 'Hak Milik Penuh / Bebas Langganan Bulanan',
        'color'        => '#0f766e',
        'light'        => '#f0fdfa',
        'overview'     => 'Solusi toko digital terpadu yang menghubungkan penjualan kasir di toko fisik dengan penjualan toko online secara otomatis, sehingga stok barang selalu pas dan tidak pernah selisih.',
        'features'     => [
            'Toko Online 24 Jam'         => 'Pembeli dapat melihat katalog barang lengkap dengan pilihan warna/ukuran dan memesan langsung dari HP.',
            'Kasir Toko Fisik Cepat'     => 'Proses transaksi kasir instan dengan pencarian barang, barcode scanner, dan cetak struk belanja.',
            'Stok Terhubung Otomatis'    => 'Setiap ada penjualan di toko fisik maupun online, jumlah stok barang otomatis berkurang secara langsung.',
            'Laporan Keuntungan Rapi'    => 'Grafik ringkasan omset harian, total keuntungan bersih, serta daftar barang yang paling laris dipesan.'
        ],
        'creds'        => [
            ['Pemilik Toko (Super Admin)', 'admin@bizmate.com', 'password'],
            ['Kasir Toko',                 'admin-toko@bizmate.com', 'password'],
            ['Pembeli / Pelanggan',        'customer@bizmate.com', 'password']
        ]
    ],
    [
        'title'        => 'BPHTB Online Daerah',
        'subtitle'     => 'Portal Pajak Tanah & Bangunan Pemkab/Pemkot',
        'category'     => 'Pemerintahan & Pajak Daerah',
        'url'          => 'https://bphtb.toti.my.id/',
        'image'        => '/var/www/html/toti/pdf_images/bphtb.jpg',
        'label_main'   => 'Portal Pengajuan Berkas Pajak',
        'image_dash'   => '/var/www/html/toti/pdf_images/bphtb_dash.jpg',
        'label_dash'   => 'Panel Verifikasi Petugas Bapenda',
        'price'        => 'Rp 45.000.000 – Rp 85.000.000',
        'license'      => 'Sistem Informasi Pemda / Multi-Persetujuan',
        'color'        => '#0369a1',
        'light'        => '#f0f9ff',
        'overview'     => 'Sistem pelayanan perpajakan daerah untuk otomatisasi perhitungan, pengajuan dokumen oleh Notaris/PPAT, dan verifikasi Surat Setoran Pajak Daerah (SSPD) secara online.',
        'features'     => [
            'Perhitungan Pajak Otomatis' => 'Menghitung besaran nilai pajak terutang secara otomatis tanpa risiko kesalahan rumus manual.',
            'Pengecekan NIK & PBB'       => 'Memeriksa keabsahan NIK Wajib Pajak dan riwayat lunas PBB untuk mencegah kecurangan data.',
            'Alur Persetujuan Bertingkat' => 'Dokumen diproses berurutan dari Petugas Pendaftaran, Verifikator, hingga Kepala Dinas.',
            'Cetak Berkas Ber-QR Code'  => 'Mencetak lembar validasi pajak sah yang dilengkapi kode stempel QR Code resmi pemerintah.'
        ],
        'creds'        => [
            ['Admin Pengelola',  'superadmin@bphtb.com', 'password'],
            ['Notaris / PPAT',   'notaris@bphtb.com',    'password'],
            ['Verifikator Pajak', 'verifikator@bphtb.com', 'password']
        ]
    ],
    [
        'title'        => 'Caelestis Agency CMS',
        'subtitle'     => 'Website Profil Perusahaan & Portofolio Bisnis',
        'category'     => 'Profil Bisnis & Perusahaan',
        'url'          => 'https://caelestis.toti.my.id/ dan https://caelestis.toti.my.id/admin',
        'image'        => '/var/www/html/toti/pdf_images/caelestis.jpg',
        'label_main'   => 'Tampilan Profil Utama Perusahaan',
        'image_dash'   => '',
        'label_dash'   => '',
        'price'        => 'Rp 8.000.000 – Rp 15.000.000',
        'license'      => 'Paket Website Profil & Kelola Konten',
        'color'        => '#6d28d9',
        'light'        => '#f5f3ff',
        'overview'     => 'Website representasi bisnis yang elegan untuk memamerkan produk, katalog layanan, hasil karya proyek, serta kemudahan mengelola isi berita tanpa perlu mengerti koding.',
        'features'     => [
            'Katalog Portofolio Produk' => 'Menampilkan daftar hasil kerja dan produk unggulan perusahaan secara rapi dan menarik.',
            'Pengelola Berita & Artikel' => 'Kemudahan menambah atau mengubah isi artikel berita dan informasi terbaru kapan saja.',
            'Formulir Penawaran Klien'  => 'Pengunjung dapat langsung mengisi formulir minat atau pertanyaan yang masuk ke sistem.',
            'Tampilan Cepat & Ringan'    => 'Halaman web dapat dibuka dengan sangat cepat dan nyaman melalui smartphone maupun komputer.'
        ],
        'creds'        => [
            ['Admin Pengelola Konten', 'admin@caelestis.com (username: admin)', '12345678']
        ]
    ],
    [
        'title'        => 'E-Budgeting Enterprise',
        'subtitle'     => 'Sistem Perencanaan Anggaran & Keuangan Bisnis',
        'category'     => 'Keuangan & Akuntansi Perusahaan',
        'url'          => 'https://e-budgeting.toti.my.id/login',
        'image'        => '/var/www/html/toti/pdf_images/ebudgeting.jpg',
        'label_main'   => 'Portal Masuk Keuangan',
        'image_dash'   => '/var/www/html/toti/pdf_images/ebudgeting_dash.jpg',
        'label_dash'   => 'Dashboard Pembukuan & Laba Rugi',
        'price'        => 'Rp 35.000.000 – Rp 65.000.000',
        'license'      => 'Paket Manajemen Anggaran & Akuntansi Bisnis',
        'color'        => '#047857',
        'light'        => '#ecfdf5',
        'overview'     => 'Aplikasi akuntansi dan pengontrol anggaran biaya operasional perusahaan untuk mengawasi pengeluaran tiap divisi serta memproyeksikan estimasi keuntungan bisnis.',
        'features'     => [
            'Proyeksi Laba & Rugi'       => 'Menyusun rancangan belanja operasional dan menghitung perkiraan laba rugi perusahaan.',
            'Pembukuan Akuntansi Rapi'   => 'Pencatatan uang masuk dan keluar secara tertata (Jurnal Umum, Arus Kas, dan Neraca).',
            'Persetujuan Pengeluaran'    => 'Pengajuan pencairan dana dari divisi kerja yang memerlukan persetujuan dari Pimpinan.',
            'Cetak Laporan Keuangan'     => 'Laporan keuangan dapat diunduh menjadi dokumen PDF atau file Excel dengan satu klik.'
        ],
        'creds'        => [
            ['Admin Keuangan', 'e-budgetingadmin@gmail.com (username: admin)', '12345678'],
            ['Staf Divisi',    'e-budgetingppic@gmail.com (username: ppic)',   '12345678']
        ]
    ],
    [
        'title'        => 'e-ASMARA Gresik',
        'subtitle'     => 'Portal Pengaduan & Aspirasi Warga Masyarakat',
        'category'     => 'Pelayanan Publik & Komunikasi Warga',
        'url'          => 'https://easmara.toti.my.id/',
        'image'        => '/var/www/html/toti/pdf_images/easmara.jpg',
        'label_main'   => 'Tampilan Pengajuan Laporan Warga',
        'image_dash'   => '/var/www/html/toti/pdf_images/easmara_dash.jpg',
        'label_dash'   => 'Dashboard Lacak Status Laporan',
        'price'        => 'Rp 10.000.000 – Rp 15.000.000',
        'license'      => 'Portal Pelayanan Publik & Tiket Layanan',
        'color'        => '#b91c1c',
        'light'        => '#fef2f2',
        'overview'     => 'Sistem informasi pelayanan publik resmi untuk menampung aduan, saran, dan aspirasi masyarakat secara transparan, akuntabel, dan mudah dipantau prosesnya.',
        'features'     => [
            'Formulir Laporan Mudah'     => 'Warga dapat mengirimkan pengaduan lengkap dengan foto bukti dan memilih kategori dinas terkait.',
            'Login Praktis Google'       => 'Warga dapat langsung masuk menggunakan akun Google tanpa perlu mengisi formulir daftar yang rumit.',
            'Lacak Status Nomor Resi'    => 'Nomor tiket unik agar warga mengetahui perkembangan aduannya (Diterima -> Diproses -> Selesai).',
            'Panel Petugas Pemkab'       => 'Dashboard petugas untuk membalas laporan warga dan meneruskan ke bidang kerja yang sesuai.'
        ],
        'creds'        => [
            ['Admin Pengelola', 'superadmin@easmara.com',          '12345678'],
            ['Akun Warga',      'faizalfebriyanto886@easmara.com', '12345678']
        ]
    ],
    [
        'title'        => 'SIMKlinik & Apotek (StarKids)',
        'subtitle'     => 'Sistem Informasi Layanan Klinik & Obat',
        'category'     => 'Kesehatan & Layanan Klinik',
        'url'          => 'https://klinik.toti.my.id/',
        'image'        => '/var/www/html/toti/pdf_images/klinik.jpg',
        'label_main'   => 'Portal Pendaftaran & Layanan Obat',
        'image_dash'   => '/var/www/html/toti/pdf_images/klinik_dash.jpg',
        'label_dash'   => 'Rekam Medis Pasien & Catatan Dokter',
        'price'        => 'Rp 45.000.000 – Rp 60.000.000',
        'license'      => 'Paket Sistem Klinik & Integrasi SatuSehat Kemenkes',
        'color'        => '#0284c7',
        'light'        => '#f0f9ff',
        'overview'     => 'Aplikasi pengelola klinik kesehatan terpadu mulai dari pendaftaran antrean berobat pasien, pencatatan rekam medis dokter, apotek obat, hingga pembayaran kasir.',
        'features'     => [
            'Antrean Pasien Tertata'     => 'Pendaftaran antrean berobat pasien di kasir depan secara cepat dan otomatis.',
            'Rekam Medis Dokter (RME)'   => 'Dokter mencatat riwayat penyakit, keluhan, dan resep obat secara digital.',
            'Integrasi Kemenkes'         => 'Data riwayat kesehatan terhubung otomatis dengan platform nasional SatuSehat Kemenkes.',
            'Manajemen Apotek & Kasir'   => 'Pengawasan stok obat apotek, peringatan kadaluarsa, dan cetak rincian biaya berobat.'
        ],
        'creds'        => [
            ['Admin Klinik', 'starkidsmedicalcenter@gmail.com (user: starkidsmedicalcenter)', '12345678']
        ]
    ],
    [
        'title'        => 'Pro-CBT Examination',
        'subtitle'     => 'Sistem Ujian Online & Seleksi Berkomputer',
        'category'     => 'Pendidikan & Ujian Berkomputer',
        'url'          => 'https://procbt.toti.my.id/',
        'image'        => '/var/www/html/toti/pdf_images/procbt.jpg',
        'label_main'   => 'Halaman Masuk Token Ujian',
        'image_dash'   => '/var/www/html/toti/pdf_images/procbt_dash.jpg',
        'label_dash'   => 'Dashboard Pengawas Ujian (Proctoring)',
        'price'        => 'Rp 60.000.000 – Rp 80.000.000',
        'license'      => 'Paket Engine Ujian Aman & Token Kunci',
        'color'        => '#4f46e5',
        'light'        => '#eeeffe',
        'overview'     => 'Sistem ujian online berbasis komputer dengan pengawasan digital yang aman untuk sekolah, kampus, maupun proses seleksi penerimaan karyawan baru.',
        'features'     => [
            'Keamanan Token Ujian'       => 'Peserta wajib memasukkan kode token kunci rahasia dari pengawas sebelum ujian dibuka.',
            'Pengacak Soal Otomatis'     => 'Urutan nomor soal dan pilihan jawaban diacak otomatis untuk mencegah peserta saling mencontek.',
            'Pantau Peserta Ujian'       => 'Pengawas dapat melihat daftar peserta yang sedang mengerjakan ujian secara langsung.',
            'Nilai Langsung Keluar'      => 'Hasil skor ujian peserta langsung terhitung otomatis begitu ujian selesai dikirim.'
        ],
        'creds'        => [
            ['Admin Ujian',      'procbt',                   '12345678'],
            ['Guru / Pengawas',  'muhammad.irfan@test.com',  '12345678'],
            ['Siswa / Peserta',  'ahmad.fauzi@student.test.com', '12345678']
        ]
    ],
    [
        'title'        => 'Retail POS & Kasir Toko',
        'subtitle'     => 'Sistem Kasir Toko Ritel & Stok Barang',
        'category'     => 'Ritel & Kasir Penjualan',
        'url'          => 'https://retail.toti.my.id/',
        'image'        => '/var/www/html/toti/pdf_images/retail.jpg',
        'label_main'   => 'Halaman Depan Toko Ritel',
        'image_dash'   => '/var/www/html/toti/pdf_images/retail_dash.jpg',
        'label_dash'   => 'Tampilan Kasir & Transaksi Cepat',
        'price'        => 'Rp 22.000.000 – Rp 40.000.000',
        'license'      => 'Paket Kasir Ritel & Pengelola Stok Gudang',
        'color'        => '#c2410c',
        'light'        => '#fff7ed',
        'overview'     => 'Perangkat lunak kasir toko ritel dan manajemen persediaan stok barang gudang untuk toko, minimarket, atau usaha yang memiliki banyak cabang.',
        'features'     => [
            'Kasir Cepat & Barcode'      => 'Proses transaksi belanja cepat menggunakan alat scan barcode dan kalkulasi kembalian.',
            'Cetak Nota Printer Thermal' => 'Mencetak struk belanja toko secara otomatis ke printer thermal ukuran struk kasir.',
            'Pengaturan Diskon & Promo'  => 'Mudah mengatur harga potongan diskon, paket promo belanja, atau harga grosir.',
            'Pengingat Stok Habis'       => 'Pemberitahuan otomatis jika barang tertentu di gudang jumlahnya mulai menipis.'
        ],
        'creds'        => [
            ['Pemilik Toko', 'teknokop@gmail.com (username: teknokop)', '12345678']
        ]
    ],
    [
        'title'        => 'RME Dental Specialist',
        'subtitle'     => 'Rekam Medis Digital Khusus Klinik Gigi',
        'category'     => 'Kesehatan & Klinik Spesialis Gigi',
        'url'          => 'https://rmegigi.toti.my.id/',
        'image'        => '/var/www/html/toti/pdf_images/rmegigi.jpg',
        'label_main'   => 'Dashboard Dokter Gigi',
        'image_dash'   => '',
        'label_dash'   => '',
        'price'        => 'Rp 32.000.000 – Rp 58.000.000',
        'license'      => 'Paket Rekam Medis Gigi & Odontogram Visual',
        'color'        => '#0f766e',
        'light'        => '#f0fdfa',
        'overview'     => 'Aplikasi rekam medis digital khusus klinik spesialis gigi yang dilengkapi dengan diagram peta gigi (Odontogram) visual yang dapat diklik langsung.',
        'features'     => [
            'Diagram Gigi Interaktif'    => 'Gambar peta gigi pasien dapat diklik untuk mencatat tindakan penambalan, pencabutan, atau implan.',
            'Catatan Riwayat Medis'      => 'Mencatat riwayat keluhan gigi, penanganan medis dokter, dan resep obat yang diberikan.',
            'Sync SatuSehat Kemenkes'    => 'Pelaporan data hasil pemeriksaan gigi terkoneksi langsung dengan sistem Kementerian Kesehatan.',
            'Jadwal Janji Temu Pasien'   => 'Pengaturan jadwal kedatangan pasien dan rincian penghitungan total biaya perawatan.'
        ],
        'creds'        => [
            ['Super Admin Dokter', 'rmegigi', '12345678']
        ]
    ],
    [
        'title'        => 'SIAKAMIL Sekolah & Pesantren',
        'subtitle'     => 'Sistem Informasi Akademik & E-Rapor',
        'category'     => 'Pendidikan & Manajemen Sekolah',
        'url'          => 'http://siakamil.toti.my.id/',
        'image'        => '/var/www/html/toti/pdf_images/siakamil.jpg',
        'label_main'   => 'Dashboard Akademik & Nilai Rapor',
        'image_dash'   => '',
        'label_dash'   => '',
        'price'        => 'Rp 10.000.000 – Rp 25.000.000',
        'license'      => 'Paket Sistem Informasi Akademik & E-Rapor Sekolah',
        'color'        => '#be123c',
        'light'        => '#fff1f2',
        'overview'     => 'Sistem informasi pengelolaan akademik terpadu untuk sekolah, madrasah, dan pondok pesantren mulai dari data siswa, absensi, hingga cetak E-Rapor.',
        'features'     => [
            'Data Induk Sekolah Terpusat' => 'Menyimpan data lengkap siswa, guru, wali murid, serta pembagian kelompok kelas.',
            'Jadwal & Absensi Kehadiran' => 'Pengaturan jadwal pelajaran sekolah dan pencatatan presensi kehadiran siswa.',
            'Pengumpulan Tugas Online'   => 'Fitur pembagian tugas sekolah secara digital dan pengumpulan berkas dari siswa.',
            'Penyusunan E-Rapor Otomatis' => 'Penginputan nilai ujian oleh guru dan pencetakan hasil rapor semester secara otomatis.'
        ],
        'creds'        => [
            ['Admin / Guru', 'admin@gmail.com (username: admin)', 'password']
        ]
    ]
];

$totalProjects = count($projects);

// =====================================================================
// GENERATE HTML RINCIAN 10 PROYEK (1 PROYEK PER HALAMAN)
// =====================================================================
$allCardsHtml = '';
foreach ($projects as $index => $p) {
    $projectNo = sprintf('%02d', $index + 1);

    // Multi-URL parser
    $rawUrl = $p['url'];
    $urlList = is_array($rawUrl) ? $rawUrl : array_map('trim', preg_split('/\s+dan\s+|,/', $rawUrl));
    $urlLinks = [];
    foreach ($urlList as $u) {
        if (!empty($u)) {
            $urlLinks[] = '<a href="' . htmlspecialchars($u) . '" style="color: ' . $p['color'] . '; font-weight: bold; text-decoration: underline;">' . htmlspecialchars($u) . '</a>';
        }
    }
    $urlHtml = implode(' &nbsp;&bull;&nbsp; ', $urlLinks);

    // Screenshot rendering logic
    $hasMain = !empty($p['image']) && file_exists($p['image']);
    $hasDash = !empty($p['image_dash']) && file_exists($p['image_dash']);
    $imgTag = '';

    if ($hasMain && $hasDash) {
        $mainData = base64_encode(file_get_contents($p['image']));
        $dashData = base64_encode(file_get_contents($p['image_dash']));
        $imgTag = '
        <div class="gallery-container">
            <table cellpadding="0" cellspacing="0" style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="width: 50%; padding-right: 6px; vertical-align: top;">
                        <div class="gallery-label">' . htmlspecialchars($p['label_main']) . '</div>
                        <div class="browser-frame">
                            <div class="browser-dots"><span class="dot dot-red"></span><span class="dot dot-yellow"></span><span class="dot dot-green"></span></div>
                            <img src="data:image/jpeg;base64,' . $mainData . '" class="browser-img" />
                        </div>
                    </td>
                    <td style="width: 50%; padding-left: 6px; vertical-align: top;">
                        <div class="gallery-label">' . htmlspecialchars($p['label_dash']) . '</div>
                        <div class="browser-frame">
                            <div class="browser-dots"><span class="dot dot-red"></span><span class="dot dot-yellow"></span><span class="dot dot-green"></span></div>
                            <img src="data:image/jpeg;base64,' . $dashData . '" class="browser-img" />
                        </div>
                    </td>
                </tr>
            </table>
        </div>';
    } elseif ($hasMain) {
        $mainData = base64_encode(file_get_contents($p['image']));
        $imgTag = '
        <div class="gallery-container">
            <div class="gallery-label">' . htmlspecialchars($p['label_main']) . '</div>
            <div class="browser-frame">
                <div class="browser-dots"><span class="dot dot-red"></span><span class="dot dot-yellow"></span><span class="dot dot-green"></span></div>
                <img src="data:image/jpeg;base64,' . $mainData . '" class="browser-img" />
            </div>
        </div>';
    }

    // Key Features Grid (4 items in 2x2 layout)
    $featuresGrid = '<table class="features-table" cellpadding="0" cellspacing="0">';
    $featKeys = array_keys($p['features']);
    for ($i = 0; $i < count($featKeys); $i += 2) {
        $k1 = $featKeys[$i];
        $v1 = $p['features'][$k1];

        $k2 = isset($featKeys[$i + 1]) ? $featKeys[$i + 1] : null;
        $v2 = $k2 ? $p['features'][$k2] : null;

        $featuresGrid .= '<tr>';
        $featuresGrid .= '<td style="width: 50%; padding-right: 8px; vertical-align: top; padding-bottom: 8px;">
            <div class="feature-item">
                <div class="feature-title">&#10003; ' . htmlspecialchars($k1) . '</div>
                <div class="feature-desc">' . htmlspecialchars($v1) . '</div>
            </div>
        </td>';

        if ($k2) {
            $featuresGrid .= '<td style="width: 50%; padding-left: 8px; vertical-align: top; padding-bottom: 8px;">
                <div class="feature-item">
                    <div class="feature-title">&#10003; ' . htmlspecialchars($k2) . '</div>
                    <div class="feature-desc">' . htmlspecialchars($v2) . '</div>
                </div>
            </td>';
        } else {
            $featuresGrid .= '<td style="width: 50%;"></td>';
        }
        $featuresGrid .= '</tr>';
    }
    $featuresGrid .= '</table>';

    // Demo Credentials table rows
    $credRows = '';
    foreach ($p['creds'] as $cr) {
        $credRows .= '<tr>
            <td class="cr-role">' . htmlspecialchars($cr[0]) . '</td>
            <td class="cr-login">' . htmlspecialchars($cr[1]) . '</td>
            <td class="cr-pass">' . htmlspecialchars($cr[2]) . '</td>
        </tr>';
    }

    $cardStyle = ($index < count($projects) - 1) ? 'style="page-break-after: always;"' : '';

    $allCardsHtml .= '
    <div class="project-page-card" ' . $cardStyle . '>
        <div class="project-header">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="width: 65%; vertical-align: top;">
                        <div class="project-badge" style="color: ' . $p['color'] . ';">APLIKASI #' . $projectNo . ' &bull; ' . htmlspecialchars($p['category']) . '</div>
                        <div class="project-title">' . htmlspecialchars($p['title']) . '</div>
                        <div class="project-subtitle">' . htmlspecialchars($p['subtitle']) . '</div>
                        <div class="project-url">' . $urlHtml . '</div>
                    </td>
                    <td style="width: 35%; text-align: right; vertical-align: top;">
                        <div class="price-box" style="border: 1px solid ' . $p['color'] . '44; background: ' . $p['light'] . ';">
                            <div class="price-label">Perkiraan Investasi</div>
                            <div class="price-value" style="color: ' . $p['color'] . ';">' . htmlspecialchars($p['price']) . '</div>
                            <div class="price-sub">' . htmlspecialchars($p['license']) . '</div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="project-body">
            <div class="overview-box">
                <strong>Manfaat Utama:</strong> ' . htmlspecialchars($p['overview']) . '
            </div>

            ' . $imgTag . '

            <div class="section-heading">Fitur &amp; Keunggulan Utama Aplikasi</div>
            ' . $featuresGrid . '

            <div class="cred-box">
                <div class="cred-title">Akses Uji Coba (Demo Login System)</div>
                <table class="cred-table" cellpadding="0" cellspacing="0">
                    <thead>
                        <tr>
                            <th style="width: 30%;">Hak Akses (Role)</th>
                            <th style="width: 50%;">Email / Username</th>
                            <th style="width: 20%;">Password</th>
                        </tr>
                    </thead>
                    <tbody>
                        ' . $credRows . '
                    </tbody>
                </table>
            </div>
        </div>
    </div>';
}

// =====================================================================
// FULL HTML DOCUMENT (CLEAN LIGHT EXECUTIVE PRESENTATION THEME)
// =====================================================================
$html = '<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Katalog &amp; Portofolio Aplikasi Web Enterprise</title>
<style>
    @page {
        margin: 0;
        size: A4 portrait;
    }
    html, body {
        margin: 0;
        padding: 0;
        width: 100%;
        font-family: Helvetica, Arial, sans-serif;
        background-color: #ffffff;
        color: #1e293b;
        -webkit-print-color-adjust: exact;
    }

    /* 1. COVER PAGE DESIGN (EXECUTIVE DARK NAVY) */
    .cover-wrap {
        width: 100%;
        height: 841.89pt;
        background-color: #0b1329;
        color: #ffffff;
        position: relative;
        box-sizing: border-box;
        overflow: hidden;
        page-break-after: always;
    }
    .cover-inner {
        position: absolute;
        top: 45pt;
        left: 35pt;
        right: 35pt;
        z-index: 2;
    }
    .cover-tag {
        font-size: 8pt;
        font-weight: bold;
        letter-spacing: 2.5px;
        text-transform: uppercase;
        color: #38bdf8;
        margin-bottom: 25px;
    }
    .cover-title {
        font-size: 28pt;
        font-weight: 800;
        color: #ffffff;
        line-height: 1.18;
        margin: 0 0 12px 0;
        letter-spacing: -0.5px;
    }
    .cover-title-cyan {
        color: #38bdf8;
    }
    .cover-subtitle {
        font-size: 11pt;
        color: #94a3b8;
        line-height: 1.5;
        margin-bottom: 30px;
    }
    .cover-stats-tbl {
        width: 100%;
        table-layout: fixed;
        border-collapse: collapse;
        margin-bottom: 25px;
    }
    .cover-stat-card {
        background: #111c38;
        border: 1px solid #1e2d4a;
        border-radius: 6px;
        padding: 12px 6px;
        text-align: center;
        box-sizing: border-box;
    }
    .cover-stat-num {
        font-size: 22pt;
        font-weight: bold;
        color: #38bdf8;
        line-height: 1;
        margin-bottom: 4px;
    }
    .cover-stat-lbl {
        font-size: 6.2pt;
        font-weight: bold;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .cover-desc {
        font-size: 9pt;
        color: #cbd5e1;
        line-height: 1.6;
        margin-bottom: 30px;
        width: 100%;
    }
    .cover-sec-title {
        font-size: 8pt;
        font-weight: bold;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        margin-bottom: 12px;
    }
    .cover-pill {
        display: inline-block;
        border: 1px solid #1e2d4a;
        background: #111c38;
        color: #cbd5e1;
        font-size: 7.5pt;
        font-weight: bold;
        padding: 5px 12px;
        border-radius: 14px;
        margin-right: 4px;
        margin-bottom: 6px;
    }
    .cover-footer {
        position: absolute;
        bottom: 40pt;
        left: 35pt;
        right: 35pt;
        border-top: 1px solid #1e293b;
        padding-top: 16px;
        z-index: 2;
    }
    .c-ftr-lbl {
        font-size: 7pt;
        font-weight: bold;
        color: #64748b;
        letter-spacing: 1.5px;
        text-transform: uppercase;
        margin-bottom: 4px;
    }
    .c-ftr-author {
        font-size: 13pt;
        font-weight: bold;
        color: #ffffff;
        margin-bottom: 2px;
    }
    .c-ftr-role {
        font-size: 9pt;
        font-weight: bold;
        color: #38bdf8;
        margin-bottom: 2px;
    }
    .c-ftr-sub {
        font-size: 8pt;
        color: #64748b;
    }
    .c-badge {
        display: inline-block;
        border: 1px solid #0284c7;
        color: #38bdf8;
        font-size: 7.5pt;
        font-weight: bold;
        letter-spacing: 1px;
        padding: 4px 10px;
        border-radius: 4px;
        background: rgba(2, 132, 199, 0.15);
        margin-bottom: 4px;
    }

    /* 2. PAGE LAYOUT (CLEAN LIGHT EXECUTIVE PRESENTATION) */
    .page-wrap {
        width: 100%;
        padding: 30pt 35pt 25pt 35pt;
        box-sizing: border-box;
        position: relative;
        page-break-after: always;
        background-color: #ffffff;
    }
    .page-title-main {
        font-size: 18pt;
        font-weight: bold;
        color: #0f172a;
        margin-bottom: 4px;
    }
    .page-sub-main {
        font-size: 9pt;
        color: #64748b;
        margin-bottom: 20px;
    }

    .service-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 14px 16px;
        margin-bottom: 14px;
    }
    .service-card-title {
        font-size: 10pt;
        font-weight: bold;
        color: #0284c7;
        margin-bottom: 4px;
    }
    .service-card-desc {
        font-size: 8.5pt;
        color: #334155;
        line-height: 1.5;
    }

    .simple-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 8.5pt;
        margin-bottom: 15px;
    }
    .simple-table th {
        background: #f1f5f9;
        color: #0f172a;
        text-align: left;
        padding: 8px 10px;
        font-weight: bold;
        border-bottom: 2px solid #cbd5e1;
    }
    .simple-table td {
        padding: 8px 10px;
        border-bottom: 1px solid #e2e8f0;
        color: #334155;
    }

    /* 3. PROJECT CARD DESIGN (1 PAGE PER PROJECT) */
    .project-page-card {
        padding: 35pt 40pt 30pt 40pt;
        box-sizing: border-box;
        position: relative;
        background-color: #ffffff;
    }
    .project-header {
        margin-bottom: 14px;
        border-bottom: 1px solid #e2e8f0;
        padding-bottom: 10px;
    }
    .project-badge {
        font-size: 7.5pt;
        font-weight: bold;
        letter-spacing: 1px;
        text-transform: uppercase;
        margin-bottom: 3px;
    }
    .project-title {
        font-size: 17pt;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.15;
        margin-bottom: 3px;
    }
    .project-subtitle {
        font-size: 9.5pt;
        color: #64748b;
        margin-bottom: 6px;
    }
    .project-url {
        font-size: 8.5pt;
    }

    .price-box {
        padding: 8px 12px;
        border-radius: 6px;
        text-align: right;
    }
    .price-label {
        font-size: 7pt;
        font-weight: bold;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .price-value {
        font-size: 10.5pt;
        font-weight: bold;
        margin: 2px 0;
    }
    .price-sub {
        font-size: 7pt;
        color: #64748b;
    }

    .overview-box {
        background: #f8fafc;
        border-left: 3px solid #0284c7;
        padding: 8px 12px;
        font-size: 8.5pt;
        color: #334155;
        line-height: 1.5;
        margin-bottom: 12px;
        border-radius: 0 6px 6px 0;
    }

    .gallery-container {
        margin-bottom: 14px;
    }
    .gallery-label {
        font-size: 7.5pt;
        font-weight: bold;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 4px;
    }
    .browser-frame {
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        overflow: hidden;
        background: #ffffff;
    }
    .browser-dots {
        background: #f1f5f9;
        padding: 4px 8px;
        border-bottom: 1px solid #e2e8f0;
        line-height: 1;
    }
    .dot {
        display: inline-block;
        width: 6px;
        height: 6px;
        border-radius: 50%;
        margin-right: 3px;
    }
    .dot-red { background: #ef4444; }
    .dot-yellow { background: #f59e0b; }
    .dot-green { background: #10b981; }
    .browser-img {
        width: 100%;
        display: block;
        height: auto;
    }

    .section-heading {
        font-size: 9pt;
        font-weight: bold;
        color: #0f172a;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 8px;
    }

    .features-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 10px;
    }
    .feature-item {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 8px 10px;
        box-sizing: border-box;
    }
    .feature-title {
        font-size: 8.2pt;
        font-weight: bold;
        color: #0f172a;
        margin-bottom: 3px;
    }
    .feature-desc {
        font-size: 7.5pt;
        color: #475569;
        line-height: 1.4;
    }

    .cred-box {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 10px 12px;
        margin-top: 6px;
    }
    .cred-title {
        font-size: 8pt;
        font-weight: bold;
        color: #0f172a;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 6px;
    }
    .cred-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 7.8pt;
    }
    .cred-table th {
        text-align: left;
        color: #64748b;
        border-bottom: 1px solid #cbd5e1;
        padding-bottom: 4px;
        font-size: 7.2pt;
        text-transform: uppercase;
    }
    .cred-table td {
        padding: 4px 0;
        color: #1e293b;
        border-bottom: 1px dashed #e2e8f0;
    }
    .cr-role { font-weight: bold; color: #0284c7; }
    .cr-login { font-family: "Courier", monospace; color: #0f172a; font-weight: bold; }
    .cr-pass { font-family: "Courier", monospace; font-weight: bold; color: #0284c7; }
</style>
</head>
<body>

<!-- HALAMAN 1: COVER SLIDE (DARK EXECUTIVE NAVY) -->
<div class="cover-wrap">
    <div class="cover-inner">
        <div class="cover-tag">KATALOG &amp; PORTOFOLIO APLIKASI WEB &bull; 2026</div>

        <div style="margin-bottom: 25px;">
            <div class="cover-title">
                Katalog Portofolio<br>
                <span class="cover-title-cyan">' . $totalProjects . ' Aplikasi Web</span><br>
                Enterprise Ready
            </div>
            <div class="cover-subtitle">Panduan Solusi Perangkat Lunak, Fitur Utama &amp; Akses Uji Coba (Demo)</div>
        </div>

        <table class="cover-stats-tbl" cellpadding="0" cellspacing="0">
            <tr>
                <td style="width: 25%; padding-right: 4px;">
                    <div class="cover-stat-card">
                        <div class="cover-stat-num">' . $totalProjects . '</div>
                        <div class="cover-stat-lbl">SISTEM SIAP PAKAI</div>
                    </div>
                </td>
                <td style="width: 25%; padding: 0 2px;">
                    <div class="cover-stat-card">
                        <div class="cover-stat-num">7</div>
                        <div class="cover-stat-lbl">SEKTOR INDUSTRI</div>
                    </div>
                </td>
                <td style="width: 25%; padding: 0 2px;">
                    <div class="cover-stat-card">
                        <div class="cover-stat-num">100%</div>
                        <div class="cover-stat-lbl">LIVE &amp; TERUJI</div>
                    </div>
                </td>
                <td style="width: 25%; padding-left: 4px;">
                    <div class="cover-stat-card">
                        <div class="cover-stat-num">100%</div>
                        <div class="cover-stat-lbl">GARANSI &amp; SUPPORT</div>
                    </div>
                </td>
            </tr>
        </table>

        <div class="cover-desc">
            Dokumen ini menyajikan katalog portofolio aplikasi web profesional yang siap digunakan dan dapat disesuaikan (*custom*) sesuai kebutuhan alur bisnis Anda. Setiap proyek dilengkapi dengan penjelasan manfaat bisnis dalam bahasa yang mudah dipahami, gambaran antarmuka, serta akses uji coba (*demo login*) secara langsung.
        </div>

        <div>
            <div class="cover-sec-title">SEKTOR BISA DILAYANI</div>
            <div>
                <span class="cover-pill">Toko Online &amp; Kasir (POS)</span>
                <span class="cover-pill">Pemerintahan &amp; Pajak</span>
                <span class="cover-pill">Klinik &amp; Kesehatan</span>
                <span class="cover-pill">Sekolah &amp; Kampus</span>
                <span class="cover-pill">Keuangan &amp; Akuntansi</span>
                <span class="cover-pill">Profil Perusahaan</span>
            </div>
        </div>
    </div>

    <div class="cover-footer">
        <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
            <tr>
                <td style="width: 60%; vertical-align: bottom;">
                    <div class="c-ftr-lbl">PENYUSUN &amp; PENGEMBANG</div>
                    <div class="c-ftr-author">Totiyono Nugroho</div>
                    <div class="c-ftr-role">Full-Stack Web Developer &amp; IT Consultant</div>
                    <div class="c-ftr-sub">Laravel &bull; Svelte &bull; Livewire &bull; Inertia.js &bull; Cloud Infrastructure</div>
                </td>
                <td style="width: 40%; text-align: right; vertical-align: bottom;">
                    <div class="c-badge">EXECUTIVE CATALOGUE</div>
                    <div class="c-ftr-sub">Khusus Keperluan Demostrasi &bull; 2026</div>
                </td>
            </tr>
        </table>
    </div>
</div>

<!-- HALAMAN 2: PROFIL PENGEMBANG & LAYANAN PERANGKAT LUNAK -->
<div class="page-wrap">
    <div class="page-title-main">Profil &amp; Keunggulan Pengembang</div>
    <div class="page-sub-main">Komitmen Memberikan Perangkat Lunak Berkualitas Tinggi, Aman, dan Mudah Digunakan</div>

    <div class="service-card">
        <div class="service-card-title">1. Bebas Biaya Langganan Bulanan (Hak Milik Penuh)</div>
        <div class="service-card-desc">Sistem yang dikembangkan menjadi milik Anda sepenuhnya tanpa ada biaya sewa atau langganan bulanan yang memberatkan. Sistem dipasang di server milik Anda sendiri.</div>
    </div>

    <div class="service-card">
        <div class="service-card-title">2. Tampilan Modern &amp; Sangat Mudah Digunakan (User Friendly)</div>
        <div class="service-card-desc">Desain antarmuka dirancang bersih dan intuitif sehingga staf maupun pengguna awam dapat langsung mengoperasikannya tanpa perlu waktu pelatihan yang lama. Dapat dibuka dari HP, Tablet, dan Komputer.</div>
    </div>

    <div class="service-card">
        <div class="service-card-title">3. Disesuaikan Sepenuhnya Dengan Alur Bisnis Anda (Customizable)</div>
        <div class="service-card-desc">Setiap fitur dan alur kerja dalam aplikasi dapat disesuaikan 100% dengan kebutuhan khusus perusahaan, klinik, sekolah, atau instansi Anda.</div>
    </div>

    <div class="service-card">
        <div class="service-card-title">4. Garansi Perbaikan &amp; Pendampingan</div>
        <div class="service-card-desc">Setiap pengerjaan dilengkapi garansi perbaikan kendala (*bug fix*) serta pendampingan saat awal pemasangan (*deployment*) hingga sistem siap digunakan secara penuh.</div>
    </div>

    <div style="font-size: 10pt; font-weight: bold; color: #0f172a; margin-top: 18px; margin-bottom: 8px;">Informasi Kontak &amp; Konsultasi</div>
    <table class="simple-table">
        <tr>
            <td style="font-weight: bold; width: 30%;">Pengembang Utama</td>
            <td style="font-weight: bold; color: #0284c7;">Totiyono Nugroho</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">WhatsApp / Telepon</td>
            <td>+62 856-5662-9097 (085656629097)</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Email Utama</td>
            <td>contact@toti.my.id</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Website Portofolio</td>
            <td>https://toti.my.id</td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Repository Kode</td>
            <td>https://github.com/brehehe</td>
        </tr>
    </table>
</div>

<!-- HALAMAN RINCIAN 10 APLIKASI WEB (1 HALAMAN PER PROYEK) -->
' . $allCardsHtml . '

</body>
</html>
';

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$pdfOutput = $dompdf->output();

$targetPath1 = '/var/www/html/toti/Dokumentasi_Portofolio_Simple.pdf';
$targetPath2 = '/home/idtotech/.gemini/antigravity-ide/brain/4982b673-c769-4422-bf0e-646b0e3c1bec/Dokumentasi_Portofolio_Simple.pdf';

file_put_contents($targetPath1, $pdfOutput);
file_put_contents($targetPath2, $pdfOutput);

echo "SUCCESS: Generated Simple Executive Portfolio PDF (" . strlen($pdfOutput) . " bytes)\nSaved to: " . $targetPath1 . "\nSaved to: " . $targetPath2 . "\n";

