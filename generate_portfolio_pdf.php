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
// DATA PORTOFOLIO 10 SISTEM (LUXE MINIMALIST EXECUTIVE THEME)
// =====================================================================
$projects = [
    [
        'title'        => 'Bizmate ERP & Store',
        'subtitle'     => 'Platform E-Commerce, POS Kasir & ERP Toko Terpadu',
        'category'     => 'E-Commerce & Retail ERP',
        'url'          => 'https://bizmate.toti.my.id/',
        'image'        => '/var/www/html/toti/pdf_images/bizmate.jpg',
        'label_main'   => 'Antarmuka Storefront & Katalog Produk',
        'image_dash'   => '/var/www/html/toti/pdf_images/bizmate_dash.jpg',
        'label_dash'   => 'Dashboard Admin Store Manager & Analitik',
        'price'        => 'Rp 10.000.000 – Rp 25.000.000',
        'license'      => 'Full Source Code / On-Premise ERP Package',
        'color'        => '#0f766e',
        'light'        => '#f0fdfa',
        'overview'     => 'Platform manajemen toko online dan kasir terpadu yang memadukan katalog produk multi-varian, pemrosesan pesanan, manajemen stok gudang otomatis, serta laporan transaksi real-time.',
        'details'      => [
            'Multi-Variant Catalog Engine'  => 'Pengelolaan varian produk kompleks (ukuran, warna, stok) dengan skema harga grosir dan harga khusus promo.',
            'Async Product Importer'        => 'Pemrosesan file katalog raksasa di latar belakang via Laravel Queue Worker (ImportProductsJob) tanpa mengganggu antarmuka pengguna.',
            'Real-Time Transaction Sync'    => 'Penggunaan WebSockets via Laravel Reverb & Echo untuk sinkronisasi transaksi kasir dan stok produk secara langsung.',
            'Dashboard Analytics & KPI'     => 'Visualisasi grafik omset penjualan, margin keuntungan, dan analisis barang paling laris menggunakan Chart.js.',
            'Role-Based Access Control'     => 'Manajemen hak akses bertingkat untuk Super Admin, Admin Penjualan, Admin Toko, dan Pelanggan berbasis Spatie Permission.'
        ],
        'stack'        => ['Laravel 13', 'Inertia.js 3', 'Svelte 5', 'Tailwind CSS v4', 'Laravel Reverb', 'Pest PHP v4'],
        'creds'        => [
            ['Super Admin',     'admin@bizmate.com',             'password'],
            ['Admin Penjualan', 'admin-penjualan@bizmate.com',   'password'],
            ['Admin Toko',      'admin-toko@bizmate.com',        'password'],
            ['Customer',        'customer@bizmate.com',          'password']
        ]
    ],
    [
        'title'        => 'BPHTB Online',
        'subtitle'     => 'Sistem Informasi Pajak Daerah BPHTB & Notaris',
        'category'     => 'Government & Taxation',
        'url'          => 'https://bphtb.toti.my.id/',
        'image'        => '/var/www/html/toti/pdf_images/bphtb.jpg',
        'label_main'   => 'Halaman Login',
        'image_dash'   => '/var/www/html/toti/pdf_images/bphtb_dash.jpg',
        'label_dash'   => 'Dashboard Verifikasi Bapenda & Validasi NOP',
        'price'        => 'Rp 45.000.000 – Rp 85.000.000',
        'license'      => 'Enterprise Gov Software / Multi-Level Approval Engine',
        'color'        => '#0369a1',
        'light'        => '#f0f9ff',
        'overview'     => 'Portal perpajakan daerah untuk otomatisasi perhitungan, pengajuan, dan verifikasi Surat Setoran Pajak Daerah (SSPD) BPHTB dari Notaris/PPAT ke Badan Pendapatan Daerah.',
        'details'      => [
            'E-Form SSPD Notaris'           => 'Formulir digital pengajuan NOP PBB, NJOP, NOPTKP, dan kalkulasi otomatis nilai BPHTB terutang oleh Notaris/PPAT.',
            'Validasi PBB & NIK Wajib Pajak' => 'Pengecekan riwayat lunas PBB dan integrasi validasi identitas NIK untuk mencegah kecurangan data transaksi.',
            'Modul Approval Multi-Level'    => 'Alur verifikasi bertingkat dari Petugas Pendaftaran -> Surveyor -> Verifikator -> Kepala Bidang -> Kepala Dinas.',
            'Generasi Berkas Resmi PDF'     => 'Pencetakan dokumen SSPD sah berstempel QR Code via Laravel DomPDF serta ekspor laporan bulanan ke Excel.'
        ],
        'stack'        => ['Laravel 13', 'Livewire 4', 'Tailwind CSS', 'Laravel DomPDF', 'Maatwebsite Excel', 'Spatie Permission'],
        'creds'        => [
            ['Super Admin',      'superadmin@bphtb.com',   'password'],
            ['Notaris / PPAT',   'notaris@bphtb.com',      'password'],
            ['Verifikator',      'verifikator@bphtb.com',  'password'],
            ['Surveyor',         'surveyor@bphtb.com',     'password']
        ]
    ],
    [
        'title'        => 'Caelestis Agency CMS',
        'subtitle'     => 'Company Profile & Portfolio CMS Software House',
        'category'     => 'Corporate & Agency CMS',
        'url'          => 'https://caelestis.toti.my.id/ dan https://caelestis.toti.my.id/admin',
        'image'        => '/var/www/html/toti/pdf_images/caelestis.jpg',
        'label_main'   => 'Halaman Utama Website Perusahaan',
        'price'        => 'Rp 8.000.000 – Rp 15.000.000',
        'license'      => 'Custom Web & CMS Content Package',
        'color'        => '#6d28d9',
        'light'        => '#f5f3ff',
        'overview'     => 'Website representasi perusahaan teknologi dan CMS pengelola portofolio proyek, katalog layanan IT, artikel edukasi, serta alur kerja pengembangan perangkat lunak.',
        'details'      => [
            'Dynamic Project Showcase'      => 'Katalog portofolio dengan pencarian instan dan filter berdasarkan stack teknologi (React, Laravel, Flutter) serta sektor industri.',
            'Content Management System'     => 'Pengelolaan blog, berita rilis, dan halaman layanan perusahaan dengan editor teks kaya (WYSIWYG).',
            'Interactive Working Process'   => 'Penyajian alur kerja pengembangan software (Requirement -> Design -> Code -> QA) dan testimoni klien.',
            'Lead Acquisition & SEO'        => 'Formulir pengajuan penawaran proyek (Inbound Leads) dan pengolah meta tag SEO otomatis untuk mesin pencari.'
        ],
        'stack'        => ['Laravel', 'Livewire', 'Blade Templates', 'Tailwind CSS', 'Spatie Permission'],
        'creds'        => [
            ['Admin Panel', 'admin@caelestis.com (username: admin)', '12345678']
        ]
    ],
    [
        'title'        => 'E-Budgeting Enterprise',
        'subtitle'     => 'Sistem Perencanaan Anggaran & Akuntansi Perusahaan',
        'category'     => 'Finance & Corporate Accounting',
        'url'          => 'https://e-budgeting.toti.my.id/login',
        'image'        => '/var/www/html/toti/pdf_images/ebudgeting.jpg',
        'label_main'   => 'Portal Otentikasi Keuangan',
        'image_dash'   => '/var/www/html/toti/pdf_images/ebudgeting_dash.jpg',
        'label_dash'   => 'Dashboard Akuntansi & Pembukuan Jurnal',
        'price'        => 'Rp 35.000.000 – Rp 65.000.000',
        'license'      => 'Enterprise Accounting & Financial Budgeting Package',
        'color'        => '#047857',
        'light'        => '#ecfdf5',
        'overview'     => 'Perangkat lunak akuntansi dan perencanaan anggaran biaya operasional (RAB) perusahaan untuk mengontrol alokasi dana dan proyeksi laba-rugi.',
        'details'      => [
            'Profit & Loss Projection Engine' => 'Penyusunan estimasi omset, HPP, beban operasional, dan proyeksi laba-rugi per divisi.',
            'Akuntansi & Pembukuan Jurnal'  => 'Pencatatan transaksi Jurnal Umum, Cash Flow, Saldo Awal, dan Neraca Keuangan.',
            'Kalkulasi HPP & Depreciation'  => 'Perhitungan rinci komponen harga pokok bahan, overhead produksi, inschiet, serta beban penyusutan aktiva tetap.',
            'Workflow Approval Anggaran'    => 'Alur pengajuan pengeluaran dari unit kerja (PPIC, Marketing, HRD, IT, Gudang) ke Direksi.'
        ],
        'stack'        => ['Laravel', 'Financial Accounting Engine', 'Chart.js', 'Livewire', 'Laravel DomPDF'],
        'creds'        => [
            ['Admin Keuangan', 'e-budgetingadmin@gmail.com (user: admin)',          '12345678'],
            ['Staf PPIC',      'e-budgetingppic@gmail.com (user: ppic)',            '12345678'],
            ['Staf Marketing', 'e-budgetingmarketing@gmail.com (user: marketing)', '12345678']
        ]
    ],
    [
        'title'        => 'e-ASMARA Gresik',
        'subtitle'     => 'Portal Layanan Aspirasi & Pengaduan Masyarakat',
        'category'     => 'Government & Public Service',
        'url'          => 'https://easmara.toti.my.id/',
        'image'        => '/var/www/html/toti/pdf_images/easmara.jpg',
        'label_main'   => 'Portal Pelayanan Aspirasi Warga',
        'image_dash'   => '/var/www/html/toti/pdf_images/easmara_dash.jpg',
        'label_dash'   => 'Dashboard Lacak Progress Tiket Pengaduan',
        'price'        => 'Rp 10.000.000 – Rp 15.000.000',
        'license'      => 'Public Service Portal & E-Government Ticketing',
        'color'        => '#b91c1c',
        'light'        => '#fef2f2',
        'overview'     => 'Sistem informasi pelayanan publik Pemkab Gresik untuk menampung aduan, aspirasi, dan saran warga secara online dan transparan.',
        'details'      => [
            'E-Aduan & Lampiran Bukti'      => 'Formulir pengajuan laporan masyarakat dengan kategori dinas/komisi terkait beserta unggah foto lokasi.',
            'Single Sign-On (Google OAuth)' => 'Login praktis akun Google tanpa registrasi ulang untuk mempercepat akses pelaporan warga.',
            'Tracking Progress Tiket'       => 'Nomor resi unik bagi warga untuk melacak status penanganan aduan (Diterima -> Disposisi -> Tindak Lanjut -> Selesai).',
            'Filament Management Dashboard' => 'Panel admin bagi petugas Pemkab untuk mengelola, meneruskan aduan ke OPD, dan mempublikasikan balasan resmi.'
        ],
        'stack'        => ['Laravel', 'Filament Admin Panel', 'Livewire', 'Google Socialite OAuth'],
        'creds'        => [
            ['Super Admin',     'superadmin@easmara.com',            '12345678'],
            ['Akun Warga',      'faizalfebriyanto886@easmara.com',   '12345678']
        ]
    ],
    [
        'title'        => 'SIMKlinik & Apotek',
        'subtitle'     => 'Sistem Informasi Manajemen Klinik Terpadu',
        'category'     => 'Healthcare & Clinic Management',
        'url'          => 'https://klinik.toti.my.id/',
        'image'        => '/var/www/html/toti/pdf_images/klinik.jpg',
        'label_main'   => 'Portal Pelayanan Klinik & Depo Farmasi',
        'image_dash'   => '/var/www/html/toti/pdf_images/klinik_dash.jpg',
        'label_dash'   => 'Dashboard Rekam Medis Elektronik (RME) Pasien',
        'price'        => 'Rp 45.000.000 – Rp 60.000.000',
        'license'      => 'Clinic SIM & Kemenkes SatuSehat Integration API Package',
        'color'        => '#0284c7',
        'light'        => '#f0f9ff',
        'overview'     => 'Platform manajemen klinik kesehatan dan depo farmasi terintegrasi dari pendaftaran pasien, rekam medis dokter, kasir, hingga bridging Kemenkes.',
        'details'      => [
            'Rekam Medis Elektronik (RME)'  => 'Pencatatan riwayat konsultasi pasien (Encounter), keluhan (Observation), dan kode diagnosa penyakit ICD-10.',
            'Integrasi Kemenkes SatuSehat'  => 'Sync otomatis data kunjungan dan tindakan medis ke API platform kesehatan nasional Kemenkes.',
            'Depo Farmasi & E-Resep'        => 'Pengelolaan inventaris obat, obat kadaluarsa (Defecta), stok opname, dan peracikan resep obat medis.',
            'Kasir & Billing Kesehatan'     => 'Perhitungan biaya konsultasi, tindakan, obat, klaim asuransi/BPJS, dan cetak nota thermal.'
        ],
        'stack'        => ['Laravel', 'Kemenkes SatuSehat API', 'Livewire', 'Thermal Printing Engine', 'Spatie Permission'],
        'creds'        => [
            ['Super Admin Klinik', 'starkidsmedicalcenter@gmail.com (username: starkidsmedicalcenter)', '12345678']
        ]
    ],
    [
        'title'        => 'Pro-CBT Examination',
        'subtitle'     => 'Advanced Proctoring & Online Exam System',
        'category'     => 'Education & Exam Proctoring',
        'url'          => 'https://procbt.toti.my.id/',
        'image'        => '/var/www/html/toti/pdf_images/procbt.jpg',
        'label_main'   => 'Portal Otentikasi Token Sesi Ujian',
        'image_dash'   => '/var/www/html/toti/pdf_images/procbt_dash.jpg',
        'label_dash'   => 'Dashboard Master Ujian & Pengawas (Proctoring)',
        'price'        => 'Rp 60.000.000 – Rp 80.000.000',
        'license'      => 'High-Security Examination & Token Proctoring Package',
        'color'        => '#4f46e5',
        'light'        => '#eeeffe',
        'overview'     => 'Engine ujian komputer berintegritas tinggi khusus sertifikasi atau seleksi ketat dengan protokol pengawasan digital terenkripsi.',
        'details'      => [
            'Security Key Token Engine'     => 'Otentikasi token unik terenkripsi (UsrSecKey) yang wajib dimasukkan peserta sebelum sesi ujian dapat dibuka.',
            'Proctoring Monitor Console'   => 'Panel pengawas ujian untuk melihat daftar aktif, penghentian ujian jarak jauh, dan penanganan insiden.',
            'Algoritma Shuffling & Bank Soal' => 'Distribusi variasi butir soal otomatis untuk mencegah penyontek antar peserta terdekat.',
            'Analisis Kualitas Soal'        => 'Evaluasi statistik mengenai tingkat kesukaran dan daya beda butir soal berdasarkan hasil tes.'
        ],
        'stack'        => ['Laravel', 'Realtime WebSockets', 'Security Token Engine', 'Livewire'],
        'creds'        => [
            ['Admin',            'procbt',                   '12345678'],
            ['Dosen / Pengawas', 'muhammad.irfan@test.com',  '12345678'],
            ['Mahasiswa',        'ahmad.fauzi@student.test.com', '12345678']
        ]
    ],
    [
        'title'        => 'Retail POS & Inventory',
        'subtitle'     => 'Point of Sale & Inventory Multi-Cabang',
        'category'     => 'Retail & Inventory POS',
        'url'          => 'https://retail.toti.my.id/',
        'image'        => '/var/www/html/toti/pdf_images/retail.jpg',
        'label_main'   => 'Antarmuka Portal Ritel Toko',
        'image_dash'   => '/var/www/html/toti/pdf_images/retail_dash.jpg',
        'label_dash'   => 'Dashboard Fast Checkout Kasir & Transaksi',
        'price'        => 'Rp 22.000.000 – Rp 40.000.000',
        'license'      => 'Multi-Branch Retail POS & Inventory Package',
        'color'        => '#c2410c',
        'light'        => '#fff7ed',
        'overview'     => 'Sistem kasir toko dan manajemen persediaan barang untuk bisnis ritel dengan banyak cabang/gudang dan cetak struk otomatis.',
        'details'      => [
            'POS Fast-Checkout Interface'   => 'Antarmuka kasir cepat dengan dukungan barcode scanner, perhitungan kembalian, dan cetak nota printer thermal.',
            'Transfer Stok Multi-Gudang'    => 'Pengaturan mutasi barang antar cabang, stok opname berkala, dan retur barang rusak.',
            'Manajemen Promosi & Diskon'    => 'Pengaturan harga grosir bertingkat, diskon persentase/nominal, dan promo bundle.',
            'Procurement Supplier'          => 'Alur Pembelian (Purchase Order) dari pengajuan hingga penerimaan fisik barang di gudang.'
        ],
        'stack'        => ['Laravel', 'Livewire', 'Vue.js', 'Thermal Printing Engine', 'Spatie Permission'],
        'creds'        => [
            ['Super Admin / Owner', 'teknokop@gmail.com (user: teknokop)', '12345678']
        ]
    ],
    [
        'title'        => 'RME Dental Specialist',
        'subtitle'     => 'Rekam Medis Elektronik Klinik Gigi Spesialis',
        'category'     => 'Healthcare & Dental Care',
        'url'          => 'https://rmegigi.toti.my.id/',
        'image'        => '/var/www/html/toti/pdf_images/rmegigi.jpg',
        'label_main'   => 'Dashboard',
        'image_dash'   => '',
        'label_dash'   => '',
        'price'        => 'Rp 32.000.000 – Rp 58.000.000',
        'license'      => 'Interactive Odontogram & Dental Clinic SIM Package',
        'color'        => '#0f766e',
        'light'        => '#f0fdfa',
        'overview'     => 'Sistem rekam medis khusus spesialis kedokteran gigi dengan pemetaan diagram gigi visual (Odontogram) dan bridging Kemenkes.',
        'details'      => [
            'Interactive SVG Odontogram'    => 'Diagram 52/32 gigi pasien yang dapat diklik untuk mencatat kondisi gigi (karies, implan, penambalan, dll) secara visual.',
            'Dental Medical Record & ICD-10' => 'Catatan diagnosa medis dental, rencana perawatan (treatment plan), dan resep tindakan medis.',
            'Sync SatuSehat Kemenkes'       => 'Pengiriman data pemeriksaan dan tindakan kedokteran gigi ke API SatuSehat.',
            'Penjadwalan Pasien & Billing'  => 'Booking antrean dokter gigi dan kalkulasi rincian biaya tindakan medis.'
        ],
        'stack'        => ['Laravel', 'Svelte', 'Livewire', 'Kemenkes SatuSehat API', 'SVG Canvas Odontogram'],
        'creds'        => [
            ['Super Admin', 'rmegigi', '12345678']
        ]
    ],
    [
        'title'        => 'SIAKAMIL Academic',
        'subtitle'     => 'Sistem Informasi Akademik Sekolah & Pesantren',
        'category'     => 'Education & SIAKAD',
        'url'          => 'http://siakamil.toti.my.id/',
        'image'        => '/var/www/html/toti/pdf_images/siakamil.jpg',
        'label_main'   => 'Dashboard Akademik & E-Rapor Siswa',
        'image_dash'   => '',
        'label_dash'   => '',
        'price'        => 'Rp 10.000.000 – Rp 25.000.000',
        'license'      => 'Academic SIAKAD & E-Rapor System Package',
        'color'        => '#be123c',
        'light'        => '#fff1f2',
        'overview'     => 'Sistem informasi akademik terpadu untuk pengelolaan administrasi sekolah, madrasah, dan pondok pesantren secara digital.',
        'details'      => [
            'Data Induk Sekolah'            => 'Manajemen terpusat data siswa, guru, orang tua/wali, serta struktur rombel/kelas.',
            'Kurikulum & Alokasi Mengajar'  => 'Penjadwalan mata pelajaran, alokasi jam mengajar guru, dan pemetaan kurikulum.',
            'Presensi Kehadiran Digital'    => 'Catatan absensi siswa harian dan per mata pelajaran.',
            'Modul Tugas & E-Rapor'         => 'Pembagian tugas online, pengumpulan dokumen tugas siswa, dan penginputan nilai rapor semester.'
        ],
        'stack'        => ['Laravel', 'Livewire', 'Bootstrap', 'Tailwind CSS', 'MySQL'],
        'creds'        => [
            ['Admin / Guru', 'admin@gmail.com (user: admin)', 'password']
        ]
    ]
];

// =====================================================================
// BUILD CARDS HTML (ULTRA-PREMIUM EXECUTIVE THEME)
// =====================================================================
$allCardsHtml = '';

foreach ($projects as $index => $p) {
    $projectNo = sprintf('%02d', $index + 1);

    // 2-Column Table Grid for Spec Details
    $detailsArray = [];
    foreach ($p['details'] as $title => $desc) {
        $detailsArray[] = ['title' => $title, 'desc' => $desc];
    }
    $detailsGrid = '<table style="width: 100%; border-collapse: collapse; margin-bottom: 4px;">';
    for ($i = 0; $i < count($detailsArray); $i += 2) {
        $detailsGrid .= '<tr>';
        
        $c1 = $detailsArray[$i];
        $detailsGrid .= '
        <td style="width: 50%; vertical-align: top; padding: 0 4px 5px 0;">
            <div class="spec-card">
                <div class="spec-title">' . htmlspecialchars($c1['title']) . '</div>
                <div class="spec-desc">' . htmlspecialchars($c1['desc']) . '</div>
            </div>
        </td>';

        if (isset($detailsArray[$i + 1])) {
            $c2 = $detailsArray[$i + 1];
            $detailsGrid .= '
            <td style="width: 50%; vertical-align: top; padding: 0 0 5px 4px;">
                <div class="spec-card">
                    <div class="spec-title">' . htmlspecialchars($c2['title']) . '</div>
                    <div class="spec-desc">' . htmlspecialchars($c2['desc']) . '</div>
                </div>
            </td>';
        } else {
            $detailsGrid .= '<td style="width: 50%;"></td>';
        }
        $detailsGrid .= '</tr>';
    }
    $detailsGrid .= '</table>';

    $stackHtml = '';
    foreach ($p['stack'] as $s) {
        $stackHtml .= '<span class="luxe-badge">' . htmlspecialchars($s) . '</span>';
    }

    $credRows = '';
    foreach ($p['creds'] as $c) {
        $credRows .= '
        <tr>
            <td class="cr-role">' . htmlspecialchars($c[0]) . '</td>
            <td class="cr-login">' . htmlspecialchars($c[1]) . '</td>
            <td class="cr-pass">' . htmlspecialchars($c[2]) . '</td>
        </tr>';
    }

    // LUXE DUAL DEVICE SCREENSHOT FRAME WITH REAL COLORED DOTS
    $imgTag = '';
    $hasMain = !empty($p['image']) && file_exists($p['image']);
    $hasDash = !empty($p['image_dash']) && file_exists($p['image_dash']);

    if ($hasMain && $hasDash) {
        $mainData = base64_encode(file_get_contents($p['image']));
        $dashData = base64_encode(file_get_contents($p['image_dash']));
        $imgTag = '
        <div class="luxe-gallery">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="width: 50%; padding-right: 6px; vertical-align: top;">
                        <div class="frame-sublabel">' . htmlspecialchars($p['label_main']) . '</div>
                        <div class="luxe-window">
                            <div class="luxe-bar"><span class="b-dot b-dot-red"></span><span class="b-dot b-dot-yellow"></span><span class="b-dot b-dot-green"></span></div>
                            <img src="data:image/jpeg;base64,' . $mainData . '" class="luxe-img" />
                        </div>
                    </td>
                    <td style="width: 50%; padding-left: 6px; vertical-align: top;">
                        <div class="frame-sublabel">' . htmlspecialchars($p['label_dash']) . '</div>
                        <div class="luxe-window">
                            <div class="luxe-bar"><span class="b-dot b-dot-red"></span><span class="b-dot b-dot-yellow"></span><span class="b-dot b-dot-green"></span></div>
                            <img src="data:image/jpeg;base64,' . $dashData . '" class="luxe-img" />
                        </div>
                    </td>
                </tr>
            </table>
        </div>';
    } elseif ($hasMain) {
        $mainData = base64_encode(file_get_contents($p['image']));
        $imgTag = '
        <div class="luxe-gallery">
            <div class="frame-sublabel">' . htmlspecialchars($p['label_main']) . '</div>
            <div class="luxe-window">
                <div class="luxe-bar"><span class="b-dot b-dot-red"></span><span class="b-dot b-dot-yellow"></span><span class="b-dot b-dot-green"></span></div>
                <img src="data:image/jpeg;base64,' . $mainData . '" class="luxe-img" />
            </div>
        </div>';
    }

    $cardStyle = ($index < count($projects) - 1) ? 'style="page-break-after: always;"' : '';
    
    // Parse URL (support multiple URLs separated by "dan" or comma or array)
    $rawUrl = $p['url'];
    if (is_array($rawUrl)) {
        $urlList = $rawUrl;
    } else {
        $urlList = array_map('trim', preg_split('/\s+dan\s+|,/', $rawUrl));
    }
    $urlLinks = [];
    foreach ($urlList as $u) {
        if (!empty($u)) {
            $urlLinks[] = '<a href="' . htmlspecialchars($u) . '" style="color: ' . $p['color'] . '; font-weight: bold; text-decoration: none;">' . htmlspecialchars($u) . '</a>';
        }
    }
    $urlHtml = implode(' &nbsp;&bull;&nbsp; ', $urlLinks);

    $allCardsHtml .= '
    <div class="luxe-project-card" ' . $cardStyle . '>
        <div class="luxe-card-top">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="width: 65%;">
                        <div class="luxe-num" style="color: ' . $p['color'] . ';">PROYEK SYSTEM #' . $projectNo . ' &bull; ' . htmlspecialchars($p['category']) . '</div>
                        <div class="luxe-title">' . htmlspecialchars($p['title']) . '</div>
                        <div class="luxe-sub">' . htmlspecialchars($p['subtitle']) . '</div>
                        <div class="luxe-url">' . $urlHtml . '</div>
                    </td>
                    <td style="width: 35%; text-align: right; vertical-align: top;">
                        <div class="luxe-price-tag" style="border: 1px solid ' . $p['color'] . '44; background: ' . $p['light'] . ';">
                            <div class="l-price-lbl">Perkiraan Biaya</div>
                            <div class="l-price-val" style="color: ' . $p['color'] . ';">' . htmlspecialchars($p['price']) . '</div>
                            <div class="l-price-sub">' . htmlspecialchars($p['license']) . '</div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="luxe-card-body">
            <div class="luxe-overview-quote">
                <strong>Tujuan & Solusi Bisnis:</strong> ' . htmlspecialchars($p['overview']) . '
            </div>

            ' . $imgTag . '

            <div class="luxe-heading">Spesifikasi Modul &amp; Kapabilitas Teknis</div>
            ' . $detailsGrid . '

            <div class="luxe-heading" style="margin-top: 10px;">Teknologi Utama</div>
            <div style="margin-bottom: 12px;">
                ' . $stackHtml . '
            </div>

            <div class="luxe-cred-box">
                <div class="luxe-cred-title">Akses Demo System &amp; Credential Logins</div>
                <table class="luxe-cred-table" cellpadding="0" cellspacing="0">
                    <thead>
                        <tr>
                            <th style="width: 25%;">Role Access</th>
                            <th style="width: 55%;">Email / Username</th>
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
// HTML FULL DOCUMENT (SWISS EXECUTIVE LUXURY DESIGN THEME)
// =====================================================================
$totalProjects = count($projects);
$sectorCount = count(array_unique(array_column($projects, 'category')));

$html = '<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Katalog Portofolio & Penawaran Sistem Web Enterprise</title>
<style>
    @page {
        margin: 0;
        size: A4 portrait;
    }
    html, body {
        margin: 0;
        padding: 0;
        width: 100%;
        font-family: "Helvetica", "Arial", sans-serif;
        font-size: 9pt;
        color: #1e293b;
        line-height: 1.45;
        background-color: #ffffff;
    }

    /* 1. ENTERPRISE DARK COVER PAGE (FULL BLEED ZERO MARGIN) */
    .dark-cover-wrap {
        width: 595.28pt;
        height: 841.89pt;
        padding: 0;
        background-color: #060b17;
        color: #ffffff;
        border-radius: 0;
        position: relative;
        box-sizing: border-box;
        overflow: hidden;
        page-break-after: always;
    }
    .c-inner {
        position: absolute;
        top: 40pt;
        left: 40pt;
        width: 515.28pt;
        z-index: 2;
    }
    .cover-dec-svg {
        position: absolute;
        top: -40px;
        right: -40px;
        width: 420px;
        height: 420px;
        z-index: 1;
        pointer-events: none;
    }
    .c-hdr-track {
        font-size: 7.5pt;
        font-weight: bold;
        letter-spacing: 2px;
        text-transform: uppercase;
        color: #00a3ff;
        margin-bottom: 25px;
    }
    .c-title-box {
        margin-bottom: 25px;
    }
    .c-title-main {
        font-size: 28pt;
        font-weight: 800;
        color: #ffffff;
        line-height: 1.15;
        margin: 0 0 10px 0;
        letter-spacing: -0.5px;
    }
    .c-title-cyan {
        color: #00a3ff;
    }
    .c-title-sub {
        font-size: 10.5pt;
        color: #94a3b8;
        font-weight: normal;
        line-height: 1.4;
    }

    .c-stats-tbl {
        width: 515.28pt;
        table-layout: fixed;
        border-collapse: collapse;
        margin-bottom: 25px;
    }
    .c-stat-card {
        background: #0f192e;
        border: 1px solid #1e2d4a;
        border-radius: 6px;
        padding: 12px 6px;
        text-align: center;
        box-sizing: border-box;
    }
    .c-stat-num {
        font-size: 22pt;
        font-weight: bold;
        color: #00a3ff;
        line-height: 1;
        margin-bottom: 4px;
    }
    .c-stat-lbl {
        font-size: 6.2pt;
        font-weight: bold;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .c-desc-p {
        font-size: 8.5pt;
        color: #94a3b8;
        line-height: 1.6;
        margin-bottom: 25px;
        width: 515.28pt;
    }

    .c-sec-box {
        margin-bottom: 25px;
    }
    .c-sec-lbl {
        font-size: 7.5pt;
        font-weight: bold;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        margin-bottom: 10px;
    }
    .c-pills-row {
        line-height: 2.2;
    }
    .c-pill {
        display: inline-block;
        border: 1px solid #1e2d4a;
        background: #0c1527;
        color: #94a3b8;
        font-size: 7pt;
        font-weight: bold;
        padding: 4px 10px;
        border-radius: 12px;
        margin-right: 4px;
        margin-bottom: 5px;
    }

    .c-ftr-area {
        position: absolute;
        bottom: 40pt;
        left: 40pt;
        width: 515.28pt;
        border-top: 1px solid #1e293b;
        padding-top: 15px;
        z-index: 2;
    }
    .c-ftr-lbl {
        font-size: 6.8pt;
        font-weight: bold;
        color: #64748b;
        letter-spacing: 1.2px;
        text-transform: uppercase;
        margin-bottom: 4px;
    }
    .c-ftr-author {
        font-size: 11pt;
        font-weight: bold;
        color: #ffffff;
        margin-bottom: 2px;
    }
    .c-ftr-subrole {
        font-size: 8pt;
        font-weight: bold;
        color: #00a3ff;
        margin-bottom: 2px;
    }
    .c-ftr-tech {
        font-size: 7.5pt;
        color: #64748b;
    }

    .c-badge-confidential {
        display: inline-block;
        border: 1px solid #991b1b;
        color: #f87171;
        font-size: 7pt;
        font-weight: bold;
        letter-spacing: 1px;
        padding: 3px 8px;
        border-radius: 3px;
        background: rgba(153, 27, 27, 0.15);
        margin-bottom: 4px;
    }
    .c-ftr-sub {
        font-size: 7pt;
        color: #64748b;
    }

    /* 2. DEVELOPER PROFILE & CONTACT PAGE (FULL BLEED ZERO MARGIN) */
    .prof-page-wrap {
        padding: 35px 40px 20px 40px;
        box-sizing: border-box;
        background-color: #ffffff;
        page-break-after: always;
    }
    .prof-header {
        background: #0f172a;
        color: #ffffff;
        border-left: none;
        border-radius: 6px;
        padding: 16px 20px;
        margin-bottom: 14px;
    }
    .prof-name {
        font-size: 16pt;
        font-weight: bold;
        color: #ffffff;
        margin-bottom: 3px;
        letter-spacing: -0.3px;
    }
    .prof-title {
        font-size: 9pt;
        color: #00a3ff;
        font-weight: bold;
        margin-bottom: 6px;
    }
    .prof-desc {
        font-size: 8.2pt;
        color: #cbd5e1;
        line-height: 1.45;
    }

    .prof-contact-card {
        background: #070c18;
        color: #ffffff;
        border-radius: 6px;
        padding: 14px 16px;
        margin-bottom: 15px;
    }
    .prof-ct-title {
        font-size: 8.2pt;
        font-weight: bold;
        color: #00a3ff;
        text-transform: uppercase;
        letter-spacing: 1.2px;
        margin-bottom: 6px;
        border-bottom: 1px solid #1e293b;
        padding-bottom: 4px;
    }
    .prof-ct-table {
        width: 100%;
        border-collapse: collapse;
    }
    .prof-ct-table td {
        font-size: 8.2pt;
        padding: 3px 0;
        color: #e2e8f0;
    }

    .luxe-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 14px;
        border-radius: 6px;
        overflow: hidden;
    }
    .luxe-table th {
        background: #0f172a;
        color: #ffffff;
        font-size: 7.8pt;
        text-transform: uppercase;
        letter-spacing: 1px;
        padding: 7px 10px;
        text-align: left;
    }
    .luxe-table td {
        padding: 5.5px 10px;
        border-bottom: 1px solid #f1f5f9;
        font-size: 8.2pt;
        color: #334155;
    }
    .luxe-table tr:nth-child(even) td {
        background-color: #f8fafc;
    }

    /* 3. PROJECT CARDS & BROWSER MOCKUPS (FULL BLEED ZERO MARGIN) */
    .luxe-project-card {
        border: none;
        border-radius: 0;
        padding: 30px 40px 15px 40px;
        box-sizing: border-box;
        margin-bottom: 0;
        page-break-inside: avoid;
        background: #ffffff;
        box-shadow: none;
    }
    .luxe-project-card:last-child {
        page-break-after: auto;
    }
    .luxe-card-top {
        padding: 4px 0 12px 0;
        border-bottom: none;
        background: transparent;
    }
    .luxe-num {
        font-size: 7.5pt;
        font-weight: bold;
        letter-spacing: 1px;
        margin-bottom: 2px;
    }
    .luxe-title {
        font-size: 15pt;
        font-weight: bold;
        color: #0f172a;
        margin-bottom: 2px;
    }
    .luxe-sub {
        font-size: 9pt;
        color: #475569;
        margin-bottom: 4px;
    }
    .luxe-url {
        font-size: 8.5pt;
        font-weight: bold;
        text-decoration: underline;
    }

    .luxe-price-tag {
        padding: 7px 14px;
        border-radius: 6px;
        display: inline-block;
        text-align: right;
    }
    .l-price-lbl {
        font-size: 7pt;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.6px;
    }
    .l-price-val {
        font-size: 10pt;
        font-weight: bold;
        margin: 1px 0;
    }
    .l-price-sub {
        font-size: 7pt;
        color: #475569;
    }

    .luxe-card-body {
        padding: 10px 0 0 0;
    }
    .luxe-overview-quote {
        background: #f8fafc;
        border-left: none;
        padding: 10px 14px;
        font-size: 8.5pt;
        color: #334155;
        margin-bottom: 14px;
        border-radius: 5px;
        line-height: 1.45;
    }

    /* DEVICE FRAME MOCKUPS WITH MAC DOTS (BORDERLESS) */
    .luxe-gallery {
        margin-bottom: 14px;
        background: transparent;
        border: none;
        padding: 0;
    }
    .frame-sublabel {
        font-size: 7.5pt;
        font-weight: bold;
        color: #0284c7;
        margin-bottom: 5px;
    }
    .luxe-window {
        width: 100%;
        background: #ffffff;
        border: none;
        border-radius: 5px;
        overflow: hidden;
    }
    .luxe-bar {
        background: #f1f5f9;
        padding: 5px 8px;
        border-bottom: none;
        line-height: 1;
    }
    .b-dot {
        display: inline-block;
        width: 6px;
        height: 6px;
        border-radius: 50%;
        margin-right: 3px;
    }
    .b-dot-red { background: #ff5f56; }
    .b-dot-yellow { background: #ffbd2e; }
    .b-dot-green { background: #27c93f; }

    .luxe-img {
        width: 100%;
        height: auto;
        display: block;
    }

    .luxe-heading {
        font-size: 8pt;
        font-weight: bold;
        color: #334155;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        margin-bottom: 8px;
        border-bottom: 1.5px solid #00a3ff;
        padding-bottom: 3px;
        display: inline-block;
    }

    /* FEATURE SPECIFICATION CARDS (BORDERLESS) */
    .spec-card {
        background: #f8fafc;
        border: none;
        border-left: none;
        border-radius: 4px;
        padding: 8px 12px;
        box-sizing: border-box;
    }
    .spec-title {
        font-size: 8.2pt;
        font-weight: bold;
        color: #0f172a;
        margin-bottom: 2px;
    }
    .spec-desc {
        font-size: 7.6pt;
        color: #475569;
        line-height: 1.35;
    }

    .luxe-badge {
        display: inline-block;
        background: #f1f5f9;
        color: #1e293b;
        font-size: 7.5pt;
        font-weight: bold;
        padding: 3px 9px;
        border-radius: 4px;
        margin-right: 4px;
        margin-bottom: 4px;
        border: 1px solid #cbd5e1;
    }

    .luxe-cred-box {
        background: #f8fafc;
        color: #0f172a;
        border-radius: 6px;
        padding: 12px 15px;
        margin-top: 10px;
        border: 1px solid #e2e8f0;
    }
    .luxe-cred-title {
        font-size: 8pt;
        font-weight: bold;
        color: #0f172a;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        margin-bottom: 8px;
    }
    .luxe-cred-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 8pt;
    }
    .luxe-cred-table th {
        text-align: left;
        color: #64748b;
        border-bottom: 1px solid #cbd5e1;
        padding-bottom: 4px;
        font-size: 7.5pt;
        text-transform: uppercase;
    }
    .luxe-cred-table td {
        padding: 5px 0;
        color: #1e293b;
        border-bottom: 1px dashed #e2e8f0;
    }
    .cr-role { font-weight: bold; color: #0284c7; }
    .cr-login { font-family: "Courier", monospace; color: #0f172a; font-weight: bold; }
    .cr-pass { font-family: "Courier", monospace; font-weight: bold; color: #0284c7; }

    .page-break {
        page-break-after: always;
    }
</style>
</head>
<body><div class="dark-cover-wrap">
    <!-- SVG Vector Decoration (Concentric Circles & Dot Grid) -->
    <svg class="cover-dec-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 350 350">
        <circle cx="330" cy="20" r="310" fill="none" stroke="#1d2d47" stroke-width="1.5" />
        <circle cx="330" cy="20" r="235" fill="none" stroke="#1d2d47" stroke-width="1.5" />
        <circle cx="330" cy="20" r="160" fill="none" stroke="#1d2d47" stroke-width="1.5" />
        <circle cx="330" cy="20" r="85" fill="none" stroke="#1d2d47" stroke-width="1.5" />

        <circle cx="260" cy="70" r="1.8" fill="#1e3a5f" />
        <circle cx="275" cy="70" r="1.8" fill="#1e3a5f" />
        <circle cx="290" cy="70" r="1.8" fill="#1e3a5f" />
        <circle cx="305" cy="70" r="1.8" fill="#1e3a5f" />

        <circle cx="260" cy="85" r="1.8" fill="#1e3a5f" />
        <circle cx="275" cy="85" r="1.8" fill="#1e3a5f" />
        <circle cx="290" cy="85" r="1.8" fill="#1e3a5f" />
        <circle cx="305" cy="85" r="1.8" fill="#1e3a5f" />

        <circle cx="260" cy="100" r="1.8" fill="#1e3a5f" />
        <circle cx="275" cy="100" r="1.8" fill="#1e3a5f" />
        <circle cx="290" cy="100" r="1.8" fill="#1e3a5f" />
        <circle cx="305" cy="100" r="1.8" fill="#1e3a5f" />

        <circle cx="260" cy="115" r="1.8" fill="#1e3a5f" />
        <circle cx="275" cy="115" r="1.8" fill="#1e3a5f" />
        <circle cx="290" cy="115" r="1.8" fill="#1e3a5f" />
        <circle cx="305" cy="115" r="1.8" fill="#1e3a5f" />

        <circle cx="260" cy="130" r="1.8" fill="#1e3a5f" />
        <circle cx="275" cy="130" r="1.8" fill="#1e3a5f" />
        <circle cx="290" cy="130" r="1.8" fill="#1e3a5f" />
        <circle cx="305" cy="130" r="1.8" fill="#1e3a5f" />

        <circle cx="260" cy="145" r="1.8" fill="#1e3a5f" />
        <circle cx="275" cy="145" r="1.8" fill="#1e3a5f" />
        <circle cx="290" cy="145" r="1.8" fill="#1e3a5f" />
        <circle cx="305" cy="145" r="1.8" fill="#1e3a5f" />
    </svg>

    <div class="c-inner">
        <div class="c-hdr-track">WEB DEVELOPER PORTFOLIO &nbsp;&bull;&nbsp; DOCUMENTATION &amp; DEMO ACCESS &nbsp;&bull;&nbsp; 2026</div>

        <div class="c-title-box">
            <div class="c-title-main">
                Portfolio<br>
                <span class="c-title-cyan">' . $totalProjects . ' Enterprise</span><br>
                Web Applications
            </div>
            <div class="c-title-sub">Analisis Teknis, Fitur Unggulan &amp; Akun Demo Lengkap</div>
        </div>

        <table class="c-stats-tbl" cellpadding="0" cellspacing="0" style="table-layout: fixed; width: 515.28pt;">
            <tr>
                <td style="width: 25%; padding-right: 4px;">
                    <div class="c-stat-card">
                        <div class="c-stat-num">' . $totalProjects . '</div>
                        <div class="c-stat-lbl">TOTAL PROYEK</div>
                    </div>
                </td>
                <td style="width: 25%; padding: 0 2px;">
                    <div class="c-stat-card">
                        <div class="c-stat-num">' . $sectorCount . '</div>
                        <div class="c-stat-lbl">SEKTOR INDUSTRI</div>
                    </div>
                </td>
                <td style="width: 25%; padding: 0 2px;">
                    <div class="c-stat-card">
                        <div class="c-stat-num">15+</div>
                        <div class="c-stat-lbl">TECH STACK</div>
                    </div>
                </td>
                <td style="width: 25%; padding-left: 4px;">
                    <div class="c-stat-card">
                        <div class="c-stat-num">100%</div>
                        <div class="c-stat-lbl">LIVE &amp; DEPLOYED</div>
                    </div>
                </td>
            </tr>
        </table>

        <div class="c-desc-p">
            Dokumen ini menyajikan dokumentasi teknis komprehensif dari ' . $totalProjects . ' aplikasi web skala enterprise yang telah dikembangkan, di-deploy secara live, dan digunakan oleh klien nyata. Setiap proyek mencakup deskripsi arsitektur sistem, fitur unggulan, teknologi yang digunakan, serta akun akses demo untuk keperluan demonstrasi kepada klien.
        </div>

        <div class="c-sec-box">
            <div class="c-sec-lbl">SEKTOR &amp; INDUSTRI YANG TERCAKUP</div>
            <div class="c-pills-row">
                <span class="c-pill">E-Commerce &amp; ERP</span>
                <span class="c-pill">Government &amp; Taxation</span>
                <span class="c-pill">Healthcare &amp; Clinic</span>
                <span class="c-pill">Education &amp; LMS</span>
                <span class="c-pill">Finance &amp; Accounting</span>
                <span class="c-pill">Retail POS</span>
                <span class="c-pill">Public Service</span>
            </div>
        </div>
    </div>

    <div class="c-ftr-area">
        <table style="width: 515.28pt; border-collapse: collapse; table-layout: fixed;">
            <tr>
                <td style="width: 60%; vertical-align: bottom;">
                    <div class="c-ftr-lbl">DISUSUN OLEH</div>
                    <div class="c-ftr-author">Totiyono Nugroho</div>
                    <div class="c-ftr-subrole">Full-Stack Web Developer</div>
                    <div class="c-ftr-tech">Laravel &bull; Svelte &bull; Livewire &bull; Inertia.js &bull; PHP 8.3</div>
                </td>
                <td style="width: 40%; text-align: right; vertical-align: bottom;">
                    <div class="c-badge-confidential">CONFIDENTIAL</div>
                    <div class="c-ftr-sub">Khusus Keperluan Demo &bull; Surabaya, Juli 2026</div>
                </td>
            </tr>
        </table>
    </div>
</div>

<!-- PAGE 2: DEVELOPER PROFILE & CONTACT INFO & TECH MATRIX -->
<div class="prof-page-wrap">
    <div class="prof-header">
        <div class="prof-name">Totiyono Nugroho</div>
        <div class="prof-title">Full-Stack Software Engineer &amp; Web Application Architect</div>
        <div class="prof-desc">
            Pengembang perangkat lunak berpengalaman dalam merancang dan mengimplementasikan sistem informasi skala besar untuk sektor pemerintah, kesehatan, pendidikan, dan bisnis komersial. Berfokus pada performa kode yang optimal, arsitektur yang terukur (scalable), keamanan data, dan kemudahan antarmuka pengguna.
        </div>
    </div>

    <div class="prof-contact-card">
        <div class="prof-ct-title">Contact Information</div>
        <table class="prof-ct-table">
            <tr>
                <td style="font-weight: bold; color: #94a3b8; width: 22%;">Nama Lengkap</td>
                <td style="font-weight: bold; color: #ffffff; width: 78%;">Totiyono Nugroho</td>
            </tr>
            <tr>
                <td style="font-weight: bold; color: #94a3b8;">Email Utama</td>
                <td><a href="mailto:contact@toti.my.id" style="color: #38bdf8; text-decoration: none; font-weight: bold;">contact@toti.my.id</a></td>
            </tr>
            <tr>
                <td style="font-weight: bold; color: #94a3b8;">Nomor WhatsApp</td>
                <td style="font-weight: bold; color: #ffffff;">+62 856-5662-9097 (085656629097)</td>
            </tr>
            <tr>
                <td style="font-weight: bold; color: #94a3b8;">Website / Portfolio</td>
                <td><a href="https://toti.my.id" style="color: #38bdf8; text-decoration: none; font-weight: bold;">toti.my.id</a></td>
            </tr>
            <tr>
                <td style="font-weight: bold; color: #94a3b8;">GitHub Repository</td>
                <td><a href="https://github.com/brehehe" style="color: #38bdf8; text-decoration: none; font-weight: bold;">github.com/brehehe</a></td>
            </tr>
        </table>
    </div>

    <div class="luxe-heading" style="font-size: 9pt; margin-bottom: 8px;">Matriks Kapabilitas Teknologi &amp; Keahlian</div>
    <table class="luxe-table">
        <thead>
            <tr>
                <th style="width: 25%;">Domain Teknologi</th>
                <th style="width: 75%;">Spesifikasi Framework &amp; Tools</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Backend</strong></td>
                <td>Laravel 10–13, PHP 8.x, REST API, Queue &amp; Scheduler, Redis, Octane, FrankenPHP</td>
            </tr>
            <tr>
                <td><strong>Frontend</strong></td>
                <td>Svelte 5, Livewire 3, Alpine.js, Tailwind CSS, Vite, Inertia.js</td>
            </tr>
            <tr>
                <td><strong>Database</strong></td>
                <td>PostgreSQL, MySQL, MariaDB</td>
            </tr>
            <tr>
                <td><strong>DevOps</strong></td>
                <td>Ubuntu Server, Docker, Nginx, Apache, Git, GitHub, CI/CD, Supervisor, Certbot, Cloudflare</td>
            </tr>
            <tr>
                <td><strong>Cloud &amp; Infra</strong></td>
                <td>VPS, Linux Administration, Reverse Proxy, SSL, CDN</td>
            </tr>
        </tbody>
    </table>

    <div class="luxe-heading" style="font-size: 9pt; margin-bottom: 8px; margin-top: 15px;">Layanan Tambahan &amp; Infrastruktur (Optional Add-Ons)</div>
    <table class="luxe-table">
        <thead>
            <tr>
                <th style="width: 28%;">Layanan Add-On</th>
                <th style="width: 50%;">Cakupan Deskripsi Layanan</th>
                <th style="width: 22%; text-align: right;">Perkiraan Biaya</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Domain &amp; SSL Certificate</strong></td>
                <td>Registrasi nama domain (.com / .co.id / .id / .go.id) + Sertifikat SSL HTTPS High Security (1 Tahun)</td>
                <td style="text-align: right; font-weight: bold; color: #0284c7;">Rp 500k – 1.5jt /thn</td>
            </tr>
            <tr>
                <td><strong>Server Cloud VPS Dedicated</strong></td>
                <td>Server Cloud VPS (4GB-16GB RAM, SSD NVMe, Nginx, Config Security, Daily Backup &amp; Hardening) (1 Tahun)</td>
                <td style="text-align: right; font-weight: bold; color: #0284c7;">Rp 3jt – 12jt /thn</td>
            </tr>
            <tr>
                <td><strong>Cloud Storage S3 (Media)</strong></td>
                <td>Object Storage S3 (DigitalOcean Spaces / AWS) untuk arsip PDF, foto RME &amp; berkas besar (1 Tahun)</td>
                <td style="text-align: right; font-weight: bold; color: #0284c7;">Rp 1.5jt – 4.5jt /thn</td>
            </tr>
            <tr>
                <td><strong>Maintenance &amp; Support</strong></td>
                <td>Monitoring server 24/7, patching keamanan, garansi bug-fix, backup berkala &amp; priority support (1 Tahun)</td>
                <td style="text-align: right; font-weight: bold; color: #0284c7;">Rp 5jt – 15jt /thn</td>
            </tr>
            <tr>
                <td><strong>Training &amp; Data Migration</strong></td>
                <td>Pelatihan pengguna (User Training), pembuatan User Manual Guide, &amp; impor data awal dari Excel/CSV</td>
                <td style="text-align: right; font-weight: bold; color: #0284c7;">Rp 2.5jt – 6jt (one-time)</td>
            </tr>
        </tbody>
    </table>
    <p style="font-size: 7.5pt; color: #64748b; margin-top: 4px; font-style: italic;">
        * Untuk kebutuhan kustomisasi modul khusus, paket add-on lainnya, atau konsultasi infrastruktur server, silakan hubungi kontak saya langsung.
    </p>

    <div class="luxe-heading" style="font-size: 9pt; margin-bottom: 8px; margin-top: 15px;">Daftar Sistem &amp; Perkiraan Biaya</div>
    <p style="font-size: 8.5pt; color: #475569; margin-bottom: 15px;">
        Halaman berikutnya memuat rincian mendalam mengenai tangkapan layar (multi-view screenshots antarmuka &amp; dashboard), spesifikasi teknis, modul utama, perkiraan biaya pengembangan, dan akun login demo untuk ke-' . $totalProjects . ' sistem aplikasi web di bawah ini.
    </p>
</div>

<!-- RINCIAN 10 SISTEM -->
' . $allCardsHtml . '

</body>
</html>
';

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$pdfOutput = $dompdf->output();

$targetPath1 = '/var/www/html/toti/Dokumentasi_Portofolio_11_Web_App.pdf';
$targetPath2 = '/home/idtotech/.gemini/antigravity-ide/brain/cf7b0b49-6174-45d0-9e74-de448c014462/Dokumentasi_Portofolio_11_Web_App.pdf';

file_put_contents($targetPath1, $pdfOutput);
file_put_contents($targetPath2, $pdfOutput);

echo "SUCCESS: Generated Swiss Luxe Minimalist Executive PDF (" . strlen($pdfOutput) . " bytes)\nSaved to: " . $targetPath1 . "\nSaved to: " . $targetPath2 . "\n";
