# 👥 Analisis Peran & Matriks Fitur Lengkap (Role & Feature Specification)

Dokumen ini memuat analisis mendalam seluruh fitur sistem e-commerce **Bizmate** yang dipetakan secara terperinci berdasarkan setiap peran pengguna (*user role*): **Customer / Pelanggan (termasuk Guest)**, **Kurir Toko (Internal Fleet)**, **Admin & Backoffice**, serta **Seller / Multi-Vendor (Mode Marketplace)**.

---

## 1. Matriks Aksesibilitas Peran (*Role Access Matrix*)

| Modul & Kapabilitas | Customer (Member) | Guest (Tamu) | Kurir Toko | Admin / Backoffice | Seller (Jika Aktif) |
|---|:---:|:---:|:---:|:---:|:---:|
| **Eksplorasi Katalog & Search** | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Viewer 3D AR & Video Produk** | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Keranjang Belanja (Smart Cart)** | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Checkout & Pembayaran** | ✅ | ✅ (No Login) | ❌ | ❌ | ❌ |
| **Pelacakan Pesanan (/cek-pesanan)** | ✅ | ✅ (Verifikasi HP) | ❌ | ✅ | ✅ |
| **Manajemen Alamat & Buku Rekening** | ✅ | ❌ (Per Transaksi) | ❌ | ✅ (Audit) | ❌ |
| **Membership VIP & Loyalty Koin** | ✅ | ❌ | ❌ | ✅ (Atur Rule) | ❌ |
| **Live Chat Toko** | ✅ | ❌ (Minta Login) | ❌ | ✅ (CS Portal) | ❌ |
| **Pengajuan Retur / Refund** | ✅ | ✅ (Via Cs/Admin) | ❌ | ✅ (Verifikasi) | ❌ |
| **Dashboard Pengantaran Paket** | ❌ | ❌ | ✅ (Mobile Portal) | ✅ (Monitoring) | ❌ |
| **Scan Barcode / QR Label Resi** | ❌ | ❌ | ✅ | ✅ | ❌ |
| **Upload Bukti Serah Terima (POD)** | ❌ | ❌ | ✅ | ✅ (Review) | ❌ |
| **Manajemen Master Produk & Varian** | ❌ | ❌ | ❌ | ✅ | ✅ (Produk Sendiri) |
| **AI Generator (Deskripsi & Foto)** | ❌ | ❌ | ❌ | ✅ | ✅ |
| **Verifikasi Pembayaran Manual** | ❌ | ❌ | ❌ | ✅ | ❌ |
| **Penugasan Kurir & Dispatch** | ❌ | ❌ | ❌ | ✅ | ❌ |
| **Laporan Finansial & Laba Rugi (HPP)** | ❌ | ❌ | ❌ | ✅ | ❌ (Hanya Omset) |
| **Konfigurasi Sistem & API Eksternal** | ❌ | ❌ | ❌ | ✅ (Superadmin) | ❌ |
| **Iklan Produk (Seller Ads & Wallet)** | ❌ | ❌ | ❌ | ✅ | ✅ |

---

## 2. Modul Fitur Customer & Guest (Storefront Experience)

Pengalaman pelanggan dirancang dengan kecepatan tinggi (*instant navigation*) berbasis Svelte 5 Runes dan Tailwind CSS v4, memastikan konversi penjualan maksimal baik di desktop maupun tampilan mobile PWA.

### A. Penjelajahan Katalog & Produk
1. **Beranda Interaktif (`Home.svelte`)**:
   - Hero Banner CMS responsif dengan carousel otomatis dan link rute kampanye.
   - Flash Sale Widget dengan countdown timer live dan progress bar stok tersisa.
   - Pilihan Kategori Populer dalam bentuk grid ikonik.
   - Slider Brand/Merek resmi yang terdaftar.
   - Algoritma Rekomendasi Produk (*Personalized recommendations* berdasarkan riwayat pencarian & kategori favorit).
   - Prompt instalasi PWA (*Progressive Web App*) untuk pengalaman menyerupai aplikasi natif.
2. **Pencarian Cepat & Filter Terarah (`Search.svelte`, `Category.svelte`, `Brands.svelte`)**:
   - Pencarian cerdas instan (*instant auto-suggestion*) dengan penyorotan kata kunci (*highlight keyword*).
   - Filter harga multi-rentang (minimum - maksimum slider).
   - Filter multi-kategori, brand/merek, kondisi barang (Baru / Bekas), serta batas rating bintang ulasan.
   - Sorting dinamis: Terlaris, Termurah, Termahal, Terbaru, Rating Tertinggi.
3. **Halaman Detail Produk Kaya Media (`Product.svelte`)**:
   - **Galeri Foto Interaktif**: Thumbnail selector, zoom resolusi tinggi, dan crop swipe di mobile.
   - **3D Augmented Reality (AR) Viewer**: Menampilkan model 3D interaktif berformat `.glb` / `.usdz` yang dapat diputar 360 derajat atau diproyeksikan langsung ke ruangan menggunakan kamera ponsel (*Quick Look iOS / Model-Viewer Android*).
   - **Video Player**: Pemutar video terintegrasi (Plyr.js) untuk showcase produk tanpa meninggalkan halaman.
   - **Matriks Multi-Varian (`VariantSelectorModal.svelte`)**: Dukungan kombinasi varian bersarang (misal: Warna x Ukuran x Edisi). Setiap kombinasi memiliki SKU unik, stok tersendiri, dan penyesuaian harga real-time.
   - **Tabel Harga Grosir / Tier Pricing**: Menampilkan diskon otomatis bertingkat (misal: beli 5-10 diskon 5%, beli >10 diskon 10%).
   - **Size Chart Modal**: Panduan tabel ukuran presisi (panjang, lebar dada, rekomendasi berat badan).
   - **Ulasan Pelanggan**: Filter rating berbintang (1-5), filter ulasan berfoto/video unboxing, serta dukungan ulasan anonim (*hide name*).

---

### B. Keranjang Belanja Pintar (*Smart Cart*)
- **Komponen**: `Cart.svelte`
- **Penyimpanan Hibrida**:
  - Untuk Member: Tersimpan persisten di database (`cart_items`) tersinkronisasi lintas perangkat.
  - Untuk Tamu (Guest): Tersimpan di LocalStorage / Session, dan otomatis dimigrasikan ke database begitu pelanggan login atau melakukan transaksi.
- **Seleksi Per-Item (`is_checked`)**: Pelanggan dapat memilih sebagian barang yang ingin dicheckout tanpa menghapus item lainnya dari keranjang.
- **Validasi Stok Real-Time**: Deteksi otomatis apabila stok varian berubah atau menipis di server saat keranjang dibuka.
- **Kupon & Voucher Cepat**: Input kode voucher diskon dan bebas ongkir langsung di ringkasan belanja keranjang.

---

### C. Mesin Checkout Ganda (Guest vs Member)
- **Komponen**: `Checkout.svelte`
- **Dua Jalur Pembelian**:
  1. **Member Checkout**: Memilih dari daftar alamat tersimpan (`customer_addresses`), klaim reward diskon membership, dan tukar loyalty koin.
  2. **Guest Checkout (Beli Tanpa Login)**:
     - Diaktifkan melalui variabel lingkungan `is_checkout_without_login=true`.
     - Pelanggan hanya mengisi 3 data utama: **Nama Lengkap**, **Nomor WhatsApp/HP** (validasi 8-15 digit), dan **Alamat Email**.
     - Sistem otomatis membuatkan akun pasif di latar belakang untuk mencatat riwayat transaksi.
- **4 Mode Logistik Pengiriman**:
  1. **Kurir Ekspedisi Reguler (Biteship & Komerce API)**: Pilihan JNE, J&T, SiCepat, Anteraja, Ninja Express, ID Express, GoSend/Grab Instant. Perhitungan ongkir real-time berdasarkan berat paket (`weight_grams`) dan kode pos tujuan.
  2. **Kurir Toko (Internal Fleet)**:
     - Penghitungan otomatis jarak radius (*Haversine Formula*) dari koordinat GPS toko ke koordinat penerima.
     - Mode tarif: Flat radius dalam kota, tarif per-km, atau tarif zona bertingkat.
  3. **Ambil di Toko (*Self Pickup*)**:
     - Bebas biaya kirim Rp 0.
     - Pemesan memilih tanggal dan slot waktu pengambilan (per 30 menit) sesuai jam buka toko.
  4. **Produk Digital / Lisensi**:
     - Pengiriman otomatis lisensi/file ke email dan halaman rincian transaksi tanpa biaya kirim.
- **Opsi Pembayaran Lengkap**:
  - **Payment Gateway Otomatis (Midtrans Core/Snap API)**: BCA VA, Mandiri Bill, BNI VA, BRI VA, Permata VA, QRIS (Gopay, ShopeePay, OVO, DANA, LinkAja).
  - **Transfer Bank Manual**: Verifikasi admin dengan upload foto bukti transfer (*payment proof*).
  - **Cash on Delivery (COD)**: Pembayaran tunai saat barang diterima dengan batasan wilayah & kurir tertentu.

---

### D. Pelacakan Pesanan Terbuka & Transparan
- **Komponen**: `/cek-pesanan` (`TrackOrder.svelte`)
- **Akses Tanpa Perlu Login**: Memudahkan pembeli guest melacak pesanan dari link SMS/WhatsApp/Email.
- **Verifikasi Keamanan Berlapis**: Wajib memasukkan **Nomor Transaksi** (`TRX-YYYYMMDD-XXXX`) dan mencocokkan dengan **Email** atau **Nomor Handphone**.
- **Fitur Live Tracking**:
  - Countdown timer sisa waktu bayar (dengan pembatalan otomatis jika expired).
  - Petunjuk rekening pembayaran dan formulir upload bukti transfer instan.
  - Tracking status pengiriman ekspedisi live langsung dari API kurir.
  - Link langsung ke WhatsApp Customer Service dengan membawa konteks transaksi.

---

### E. Program Loyalitas & Membership VIP
- **Komponen**: `Membership.svelte` & `Profile.svelte`
- **Tingkatan Member**: *Classic*, *Bronze*, *Silver*, *Gold*, *Platinum*.
- **Keuntungan**:
  - Diskon checkout otomatis (misal: Gold diskon 5% flat untuk semua produk).
  - Cashback Koin Loyalty setiap selesai berbelanja.
  - Akses awal (*Early Access*) untuk flash sale atau peluncuran produk terbatas.
  - Kupon diskon eksklusif bulanan.
- **Koin Loyalty Bizmate**:
  - Didapat dari transaksi belanja yang selesai.
  - Dapat ditukarkan sebagai pemotong nominal pembayaran saat checkout (1 koin = Rp 1 atau rasio yang ditentukan admin).

---

### F. Komplain, Retur & Refund
- **Komponen**: `Returns.svelte`, `Refunds.svelte`, `RefundDetail.svelte`
- **Mekanisme**:
  - Pelanggan dapat mengajukan pengembalian barang atau dana sebelum pesanan diselesaikan (atau maksimal H+3 setelah paket diterima).
  - Unggah foto dan video bukti *unboxing* paket.
  - Input alasan retur: Barang rusak/cacat, produk tidak sesuai deskripsi, salah kirim ukuran/warna, barang kurang.
  - Input nomor resi pengembalian saat barang dikirim balik ke toko.
  - Pencairan refund via rekening bank pembeli (`customer_bank_accounts`) atau transfer payment gateway.

---

### G. Live Chat Terintegrasi
- **Komponen**: `Chat.svelte`
- Didukung oleh **Laravel Reverb WebSockets** untuk komunikasi instan 2 arah tanpa refresh.
- Fitur chat:
  - Kirim pesan teks dan upload gambar lampiran.
  - Stiker kustom toko (`chat_stickers`).
  - Indikator status pesan (Terkirim, Dibaca) dan status online CS toko.

---

## 3. Modul Fitur Kurir Toko (Internal Fleet Portal)

Portal kurir diakses melalui rute `/kurir` yang dirancang khusus untuk operasional pengantaran oleh tim logistik internal toko menggunakan perangkat mobile.

### A. Autentikasi Khusus Kurir
- **Rute**: `/kurir/login` (`resources/js/pages/Kurir/Login.svelte`)
- Dilindungi oleh Spatie role `Kurir` (Guard khusus, isolasi dari modul admin dan storefront).
- Manajemen sesi terenkripsi yang aman untuk perangkat seluler.

### B. Dashboard Pengantaran Paket (`Kurir/Dashboard.svelte`)
1. **Statistik Harian Kurir**:
   - Total paket yang ditugaskan hari ini (*Assigned*).
   - Paket yang sedang dalam perjalanan (*On Delivery*).
   - Paket yang sukses terkirim (*Completed*).
   - Paket yang gagal terkirim (*Failed/Pending*).
2. **Tab Navigasi Status Pengiriman**:
   - **Ditugaskan (Menunggu Pickup)**: Paket siap diambil di gudang/toko.
   - **Sedang Dikirim**: Paket sedang berada di kendaraan kurir menuju pelanggan.
   - **Riwayat Selesai**: Riwayat pengiriman yang telah selesai.
3. **Kartu Tugas Pengantaran (*Delivery Card*)**:
   - Nomor pesanan (`TRX-XXXXXX`).
   - Nama penerima dan alamat lengkap (termasuk RT/RW, kelurahan, kecamatan, dan patokan rumah).
   - Tag status pembayaran: **LUNAS** (Non-COD) atau **TAGIH COD: Rp XXX.XXX**.
   - Jarak estimasi pengiriman (km).

### C. Aksi Cepat & Navigasi Rute
- **Tombol WhatsApp & Telepon Satu-Klik**: Membuka aplikasi WhatsApp dengan pesan pembuka otomatis:
  > *"Halo Bapak/Ibu [Nama Penerima], saya [Nama Kurir] dari Bizmate. Saya sedang mengantarkan paket pesanan [No TRX] ke alamat Anda..."*
- **Tombol Navigasi GPS**: Membuka Google Maps atau Waze dengan titik koordinat presisi (`latitude`, `longitude`) yang tersimpan di pesanan.

### D. Pemindai Barcode & QR Code
- Memanfaatkan kamera ponsel untuk memindai label resi yang tertempel pada paket fisik.
- Mengarahkan kurir secara instan ke detail tugas pengiriman tanpa perlu mengetik nomor transaksi secara manual.

### E. Penyelesaian Pengantaran Anti-Fraud (*Proof of Delivery / POD*)
- **Komponen**: `Kurir/TransactionDetail.svelte`
- Untuk menyatakan paket terkirim, kurir diwajibkan melengkapi:
  1. **Foto Bukti Penerimaan (*Delivery Photos*)**: Mengambil foto serah terima paket bersama penerima atau foto peletakan paket di depan pintu/pagar.
  2. **Nama Penerima Asli**: Menginput nama orang yang menerima paket secara fisik.
  3. **Hubungan Penerima**: Pilihan dropdown (Yang bersangkutan, Pasangan, Anggota keluarga serumah, Rekan kerja, Satpam/Sekuriti, Tetangga).
  4. **Geotagging & Timestamp Otomatis**: Menyimpan waktu kedatangan (`delivery_arrived_at`) dan koordinat kurir saat menekan tombol selesai.
  5. **Konfirmasi Pembayaran COD**: Jika pesanan bertipe COD, kurir wajib mencentang validasi bahwa uang tunai telah diterima dengan nominal yang sesuai sebelum tombol selesai aktif.

---

## 4. Modul Fitur Admin & Backoffice

Panel kontrol pusat bagi pengelola toko dan superadmin untuk memonitor, mengonfigurasi, dan mengeksekusi operasional e-commerce secara menyeluruh (`/admin`).

### A. Dashboard Eksekutif & Business Intelligence (`Admin/Dashboard.svelte`)
- **Metric Cards (KPI)**:
  - Total Pendapatan Bersih (GMV).
  - Jumlah Pesanan Sukses vs Pending.
  - Nilai Rata-rata Transaksi (*Average Order Value / AOV*).
  - Jumlah Pengguna Baru & Pengunjung Unik.
- **Visualisasi Tren Interaktif (Chart.js)**:
  - Grafik tren omset harian, mingguan, dan bulanan.
  - Perbandingan omset produk fisik vs produk digital.
- **Analisis Pareto 80/20 (`Admin/Reports/Pareto.svelte`)**:
  - Mengidentifikasi 20% produk teratas yang menghasilkan 80% total keuntungan toko untuk optimalisasi stok dan promosi.
- **Widget Peringatan Stok Menipis (*Low Stock Alerts*)**:
  - Daftar produk dengan stok di bawah batas ambang (*reorder point*).
- **Pemantau Keranjang Terbengkalai (*Abandoned Carts Monitor*)**:
  - Memantau calon pembeli yang meninggalkan keranjang belanja beserta tombol kirim reminder voucher diskon.

---

### B. Manajemen Master Produk & Multi-Varian (`Admin/Products/`)
1. **Penyusunan Katalog Lengkap**:
   - Judul, slug ramah SEO, kategori bersarang, dan brand/merek.
   - Penanda produk fisik (butuh logistik) atau digital (akses link download / lisensi).
   - Berat produk (gram) dan dimensi (P x L x T) untuk kalkulasi volumetrik kurir.
   - Kondisi produk (Baru / Bekas) dan catatan garansi.
2. **Pengelolaan Multi-Varian Tingkat Lanjut**:
   - Pembuat kombinasi varian otomatis (Warna, Ukuran, Material).
   - Pengaturan individual per varian: SKU, Barcode, Stok fisik, Harga modal (HPP / Cost), Harga jual normal, dan Harga promo.
3. **Harga Grosir / Tier Pricing (`ProductTierPrice`)**:
   - Menentukan rentang kuantiti grosir (contoh: 5-19 unit Rp 95.000, 20-49 unit Rp 90.000, >= 50 unit Rp 85.000).
4. **Media Interaktif & 3D Model**:
   - Upload banyak foto dengan fitur seret-urut (*drag-and-drop sort order*) dan pemotong gambar (*image crop*).
   - Upload file model 3D (`.glb` untuk Android/Web, `.usdz` untuk ekosistem Apple iOS).
   - Integrasi URL video demo produk (YouTube / MP4 langsung via Plyr).
5. **Asisten AI Terintegrasi**:
   - **AI Description Generator**: Menghasilkan deskripsi penjualan persuasif, spesifikasi bullet-point, dan hashtag SEO menggunakan Gemini / OpenAgentic API hanya dengan memasukkan nama produk & kata kunci.
   - **AI Product Studio Generator**: Menghasilkan foto produk berlatar belakang studio profesional secara otomatis.
   - **Google Reverse Image Lookup**: Mencari spesifikasi dan foto referensi produk dari web.
6. **Upload Massal Harga & Stok (`MasterData/PriceUpload.svelte`)**:
   - Ekspor dan impor data harga serta stok produk menggunakan format Excel/CSV untuk pembaruan cepat.

---

### C. Manajemen Inventaris & Log Mutasi Stok (`Admin/StockMovements/`)
- **Pencatatan Mutasi Otomatis (`stock_movements`)**:
  - Setiap perubahan stok terekam secara permanen: Waktu, User eksekutor, Tipe pergerakan (`sale`, `restock`, `adjustment`, `return`, `cancellation`), Jumlah (+/-), dan Stok akhir.
- **Audit Fisik & Penyesuaian (*Stock Opname*)**:
  - Formulir penyesuaian stok riil gudang dengan pencatatan alasan selisih (barang rusak, hilang, bonus supplier).

---

### D. Manajemen Transaksi & Pemenuhan Pesanan (`Admin/Transactions/`)
1. **Pusat Monitoring Status Pesanan**:
   - Filter transaksi berdasarkan: Semua, Menunggu Pembayaran, Perlu Verifikasi, Diproses, Dikirim, Selesai, Dibatalkan, Retur/Komplain.
   - Pencarian instan berdasarkan No Transaksi, Nama Pembeli, Email, Nomor Telepon, atau Nomor Resi.
2. **Verifikasi Pembayaran Manual**:
   - Preview foto struk/bukti transfer bank dari pembeli.
   - Tombol **Setujui (Approve)**: Mengubah status menjadi `dibayar` dan memicu pemotongan stok otomatis.
   - Tombol **Tolak (Reject)**: Memberikan catatan alasan penolakan yang otomatis terkirim ke email pembeli.
3. **Logistik & Dispatch Paket**:
   - **Penugasan Kurir Toko**: Memilih armada kurir internal dari dropdown user kurir aktif.
   - **Request Pickup Ekspedisi (Biteship/Komerce)**: Memanggil kurir JNE/J&T/SiCepat untuk pickup ke toko dengan 1 klik.
   - **Input Resi Massal**: Memperbarui nomor resi pengiriman untuk banyak pesanan sekaligus.
4. **Pencetakan Dokumen Operasional**:
   - **Cetak Faktur / Invoice PDF**: Dokumen tagihan resmi dengan QR Code verifikasi.
   - **Cetak Label Pengiriman (Shipping Label / Packing Slip)**: Label ukuran kertas A6 atau thermal printer lengkap dengan barcode resi, rincian barang yang harus dipacking (*pick list*), dan alamat pengiriman.

---

### E. Manajemen Promosi & Loyalty (`Admin/Promotions/` & `Admin/Membership/`)
1. **Flash Sale Manager**:
   - Pengaturan rentang tanggal dan jam mulai/selesai.
   - Pemilihan produk yang diikutsertakan, alokasi kuota unit promo, dan persentase diskon.
2. **Kupon Voucher Belanja & Bebas Ongkir**:
   - Pembuatan kode voucher unik (contoh: `BIZMATEHEMAT`).
   - Tipe potongan: Persentase (dengan batas maksimal diskon) atau Nominal Flat.
   - Pengaturan minimal belanja (*minimum purchase*) dan batas kuota klaim per pengguna.
3. **Promo Bundling / Hadiah Pembelian (*Buy X Get Y*)**:
   - Konfigurasi beli produk utama X gratis produk pelengkap Y.
4. **Pengaturan Tingkat Membership & Aturan Koin**:
   - Menentukan ambang batas pembelanjaan untuk naik tingkat ke Bronze, Silver, Gold, Platinum.
   - Menentukan persentase cashback koin untuk tiap tingkat member.
   - Penyesuaian manual saldo koin pengguna jika terjadi kompensasi layanan.

---

### F. Manajemen Komplain, Retur & Refund (`Admin/Returns/` & `Admin/Refunds/`)
- Menerima dan mengkaji laporan barang rusak atau pengembalian dana dari pelanggan.
- Memutar video unboxing dan memeriksa foto bukti kondisi barang yang diunggah pembeli.
- Mengirim instruksi alamat retur gudang kepada pembeli.
- Memproses pencairan dana kembali (*refund disbursement*) melalui transfer rekening atau integrasi Flip/Xendit.

---

### G. Layanan Pelanggan & Live Chat (`Admin/Chat/`)
- Kotak masuk terpusat untuk seluruh percakapan pelanggan dari storefront.
- Fitur template balasan cepat (*canned responses*) untuk pertanyaan seputar ongkir, stok, atau garansi.
- Pengiriman stiker kustom toko dan attachment file gambar.

---

### H. Laporan Keuangan & Analitik Bisnis (`Admin/Reports/`)
1. **Laporan Penjualan (*Sales Report*)**: Filter rentang tanggal fleksibel, breakdown per metode pembayaran, dan per kurir.
2. **Laporan Laba Rugi (*Profit & Loss Report*)**:
   $$\text{Laba Kotor} = \text{Omset Penjualan Bersih} - \text{HPP (Harga Pokok Penjualan / Cost)}$$
   Menampilkan performa margin keuntungan riil toko setelah dipotong diskon dan subsidi ongkir.
3. **Laporan Valuasi Aset Stok**: Total nilai uang yang tertanam dalam inventaris barang di gudang.
4. **Laporan Kinerja Kurir**: Jumlah pengantaran sukses, rata-rata durasi antar, dan komisi kurir toko.
5. **Laporan Ulasan & Kepuasan Pelanggan**: Analisis sentimen rating produk dan respon komplain.

---

### I. Konfigurasi Sistem & Master Data (`Admin/MasterData/` & `Admin/Settings/`)
- **Konfigurasi Toko**: Nama toko, logo, favicon, alamat gudang asal pengiriman (lengkap dengan koordinat GPS dan Biteship Area ID).
- **Metode Pembayaran**: Pengaturan rekening bank manual (BCA, Mandiri, BRI) dan kredensial Midtrans Server Key & Client Key.
- **Logistik Eksternal**: Kunci API Biteship & Komerce, penentuan kurir ekspedisi aktif.
- **Aturan Tarif Kurir Toko**: Penentuan radius maksimal (km), tarif per kilometer, atau tarif flat zona.
- **Pengaturan Fitur (App Config)**:
  - Toggle Guest Checkout (`is_checkout_without_login`).
  - Toggle Kunci Checkout Darurat (`CHECKOUT_LOCKED` & pesan maintenance).
  - Toggle Fitur Membership & Koin.
  - Toggle Fitur 3D Model Viewer & Fitur AI.
- **Manajemen Hak Akses & Role (Spatie Permission)**:
  - Membuat peran kustom (misal: Staff Gudang, Staff CS, Finance) dan menentukan permission akses rute/menu secara granular.

---

## 5. Modul Fitur Seller & Multi-Vendor (Mode Marketplace)

Jika opsi `IS_SELLER=true` diaktifkan di `.env`, Bizmate dapat bertransformasi menjadi platform multi-toko (marketplace):

1. **Pendaftaran Penjual (*Become a Seller*)**:
   - Customer dapat mendaftar menjadi penjual resmi dengan mengisi nama toko, domain toko (`/toko/{slug}`), foto profil toko, deskripsi, dan rekening bank penampung dana.
2. **Biaya Listing Produk (*Product Listing Payments*)**:
   - Pengelola platform dapat memberlakukan biaya sewa slot posting produk berdasarkan durasi masa aktif iklan produk (misal: 30 hari = Rp 15.000, 60 hari = Rp 25.000, 90 hari = Rp 35.000).
   - Penjual membayar biaya listing menggunakan Midtrans/Flip sebelum produknya dipublikasikan ke katalog umum.
3. **Dompet Saldo Iklan Penjual (*Seller Ad Wallet*)**:
   - Penjual dapat melakukan isi ulang (*top up*) saldo dompet iklan toko mereka.
4. **Kampanye Iklan Produk Bersponsor (*Product Ads*)**:
   - Penjual dapat mempromosikan produk mereka agar muncul di posisi teratas:
     - **Penempatan Halaman Utama (Homepage Featured)**.
     - **Penempatan Teratas Hasil Pencarian (Search Top Placement)**.
     - **Penempatan Rekomendasi Produk Serupa (Related Products)**.
   - Model penagihan: CPC (*Cost Per Click* - saldo terpotong setiap iklan diklik) atau CPM (*Cost Per Mille* - saldo terpotong per 1.000 tayangan).
   - Penandaan label khusus badge *"Sponsored / Teriklan"* pada kartu produk.

