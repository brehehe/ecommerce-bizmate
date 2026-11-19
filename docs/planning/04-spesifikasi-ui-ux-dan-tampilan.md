# 🎨 Spesifikasi Desain UI/UX & Tata Letak Layar (Screen Mockups & Layouts)

Dokumen ini merinci rancangan tata letak (*wireframes*), hierarki visual, komponen antarmuka, dan arsitektur layar (*screen architecture*) untuk 3 ekosistem utama **Bizmate**: **Storefront Pelanggan**, **Portal Kurir Seluler**, dan **Admin Backoffice**.

---

## 1. Fondasi Desain & Design System Tokens

Sistem antarmuka dibangun dengan **Tailwind CSS v4** dan **Svelte 5 Runes** dengan token visual konsisten:

- **Tipografi**: `Plus Jakarta Sans` / `Inter` untuk kemudahan membaca data tabular, teks tebal yang tegas untuk angka finansial, dan keterbacaan tinggi di layar mobile.
- **Palet Warna Inti**:
  - **Brand Primary**: Emerald Deep (`#059669` / `#10b981`) melambangkan transaksi bisnis yang aman, segar, dan terpercaya.
  - **Brand Secondary / Accent**: Indigo / Violet (`#6366f1`) untuk elemen interaktif khusus (Membership VIP, AI Tools, 3D AR).
  - **Netral**: Slate Grey (`slate-50` hingga `slate-900`) untuk kontras teks tajam dan tema gelap yang ramah mata.
  - **Semantik Status**:
    - Sukses / Lunas / Selesai: `emerald-600` (`#16a34a`)
    - Menunggu Pembayaran / Diproses: `amber-500` (`#f59e0b`)
    - Dikirim / Kurir Jalan: `blue-600` (`#2563eb`)
    - Dibatalkan / Gagal / Retur: `rose-600` (`#e11d48`)
- **Pustaka Ikon**: Lucide Svelte (vektor SVG tajam, ringan, dan konsisten).

---

## 2. Tata Letak Layar Customer / Storefront

### A. Beranda Interaktif (`Storefront/Home.svelte`)

Struktur wireframe desktop & responsif mobile:

```
┌────────────────────────────────────────────────────────────────────────┐
│ [TOPBAR] Logo Bizmate | [Search Bar: Cari produk, brand...] | 🔍 | 🛒(3) | 💬 | [Cek Pesanan] | [Masuk/Daftar] │
├────────────────────────────────────────────────────────────────────────┤
│ [HERO BANNER CMS CAROUSEL]                                            │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │  🔥 FLASH SALE 11.11 - DISKON S/D 80%                           │  │
│  │  [Belanja Sekarang ->]                       [Gambar Promo HD]   │  │
│  └──────────────────────────────────────────────────────────────────┘  │
├────────────────────────────────────────────────────────────────────────┤
│ [FLASH SALE WIDGET] ⚡ Berakhir dalam: ⏱️ 02:45:12                     │
│ ┌──────────────┐ ┌──────────────┐ ┌──────────────┐ ┌──────────────┐     │
│ │ [Foto Produk]│ │ [Foto Produk]│ │ [Foto Produk]│ │ [Foto Produk]│     │
│ │ Diskon 50%   │ │ Diskon 40%   │ │ Diskon 30%   │ │ Diskon 70%   │     │
│ │ Rp 99.000    │ │ Rp 149.000   │ │ Rp 199.000   │ │ Rp 49.000    │     │
│ │ [=== 80% Terjual] [== 45% Terjual] [==== 90% Terjual] [= 20% Terjual] │
│ └──────────────┘ └──────────────┘ └──────────────┘ └──────────────┘     │
├────────────────────────────────────────────────────────────────────────┤
│ [KATEGORI PILIHAN]                                                    │
│ [👟 Sepatu]  [👕 Pakaian]  [📱 Elektronik]  [🎒 Tas]  [⚡ Digital]    │
├────────────────────────────────────────────────────────────────────────┤
│ [PRODUK TERLARIS & REKOMENDASI]                                       │
│ ┌──────────────┐ ┌──────────────┐ ┌──────────────┐ ┌──────────────┐     │
│ │ [Foto] [3D AR]│ │ [Foto]       │ │ [Foto] [3D AR]│ │ [Foto] [Video]│   │
│ │ Sepatu Alpha │ │ T-Shirt Basic│ │ Tas Backpack │ │ Jam Smartwatch│   │
│ │ Rp 250.000   │ │ Rp 85.000    │ │ Rp 180.000   │ │ Rp 450.000   │     │
│ │ ⭐ 4.9 (1.2k)│ │ ⭐ 4.8 (850) │ │ ⭐ 5.0 (310) │ │ ⭐ 4.7 (95)  │     │
│ └──────────────┘ └──────────────┘ └──────────────┘ └──────────────┘     │
├────────────────────────────────────────────────────────────────────────┤
│ [FOOTER CMS] Tentang Kami | Kebijakan Garansi & Retur | Kontak CS WA  │
└────────────────────────────────────────────────────────────────────────┘
```

---

### B. Halaman Detail Produk (`Storefront/Product.svelte`)

Rancangan tata letak 2 kolom desktop (kiri media interaktif, kanan rincian varian & pembelian):

```
┌──────────────────────────────────────┬─────────────────────────────────┐
│ [KOLOM KIRI: MEDIA INTERAKTIF]       │ [KOLOM KANAN: SPESIFIKASI]      │
│                                      │                                 │
│ ┌──────────────────────────────────┐ │ [Brand: NIKEX]                  │
│ │                                  │ │ SEPATU RUNNING PRO ATHLETE 2026 │
│ │  [CANVAS 3D AR MODEL VIEWER]     │ │ ⭐⭐⭐⭐⭐ 4.9 (428 Ulasan)   │
│ │   Rotasi 360° | Klik [View in AR]│ │                                 │
│ │   Pilihan Tab: [Foto] [3D] [Video│ │ HARGA:                          │
│ └──────────────────────────────────┘ │ Rp 299.000  (Coret: Rp 450.000) │
│                                      │ Diskon 33% | Hemat Rp 151.000   │
│ [Thumbnail 1] [Thumbnail 2] [Video]  │                                 │
│                                      │ ── SKEMA HARGA GROSIR (TIER) ── │
│ ── SPESIFIKASI LENGKAP ────────────  │ Beli 5 - 19 unit  : Rp 280.000  │
│ • Berat: 800 Gram                    │ Beli >= 20 unit   : Rp 265.000  │
│ • Material: Breathable Mesh          │                                 │
│ • Garansi: 30 Hari Penggantian Baru  │ ── PILIHAN VARIAN ───────────── │
│                                      │ Warna: [ Hitam ] [ Putih ] [Biru│
│ [Deskripsi Lengkap dari AI & Owner]  │ Ukuran: [ 40 ] [ 41 ] [ 42 ] [43│
│ [Size Chart Modal Button]            │                                 │
│                                      │ Jumlah: [ - ]  1  [ + ] (Sisa 8)│
│ ── ULASAN PELANGGAN ───────────────  │                                 │
│ Filter: [Semua] [⭐5] [⭐4] [Ada Foto│ [ 🛒 Tambah Keranjang ]         │
│ Ulasan dari "Budi S." (Foto terlampir│ [ ⚡ Beli Sekarang Langsung ]   │
└──────────────────────────────────────┴─────────────────────────────────┘
```

> [!NOTE]
> Di perangkat mobile, tombol **"Tambah Keranjang"** dan **"Beli Sekarang"** terkunci di bagian bawah layar (*Sticky Mobile Bottom Action Bar*) agar pengguna selalu dapat bertransaksi tanpa harus menggulir ke atas.

---

### C. Halaman Checkout Terpadu (`Storefront/Checkout.svelte`)

Mendukung mode Guest (Tamu tanpa login) dan Member:

```
┌──────────────────────────────────────┬─────────────────────────────────┐
│ [BAGIAN KIRI: DATA & PENGIRIMAN]     │ [BAGIAN KANAN: RINGKASAN BAYAR] │
│                                      │                                 │
│ ── 1. DATA PENERIMA ───────────────  │ ── RINGKASAN PESANAN ────────── │
│ [Jika Guest]:                        │ 2x Sepatu Running Pro (Hitam 42)│
│ Nama Lengkap  : [ Budi Santoso     ] │    Rp 598.000                   │
│ No. WhatsApp  : [ 081234567890     ] │ 1x T-Shirt Basic (Putih L)      │
│ Email Faktur  : [ budi@gmail.com   ] │    Rp 85.000                    │
│ [Jika Member]: [Pilih Alamat Buku]   │                                 │
│                                      │ Subtotal Produk   : Rp 683.000  │
│ ── 2. METODE PENGIRIMAN ───────────  │ Biaya Pengiriman  : Rp  18.000  │
│ [ Tab Pilihan Pengiriman ]:          │ Biaya Layanan     : Rp   1.000  │
│ (o) Kurir Ekspedisi (JNE/J&T/SiCepat │ Potongan Voucher  : -Rp 25.000  │
│ ( ) Kurir Toko (Radius 4.2 km - Rp15k│ Diskon VIP Gold   : -Rp 15.000  │
│ ( ) Ambil di Toko (Self Pickup - Rp0)│ Tukar 5.000 Poin  : -Rp  5.000  │
│                                      │ ─────────────────────────────── │
│ ── 3. METODE PEMBAYARAN ───────────  │ TOTAL PEMBAYARAN  : Rp 657.000  │
│ (o) Virtual Account BCA / Mandiri    │                                 │
│ ( ) QRIS (Gopay, ShopeePay, DANA)    │ [Kupon Promo: [BIZMATE] [Pakai] │
│ ( ) Transfer Bank Manual (Upload Bukt│                                 │
│ ( ) Cash on Delivery (COD)           │ [ 🔒 BAYAR SEKARANG ]           │
└──────────────────────────────────────┴─────────────────────────────────┘
```

---

### D. Halaman Pelacakan Pesanan Terbuka (`Storefront/TrackOrder.svelte`)

Layar pelacakan publik yang dapat diakses pembeli guest tanpa login:

```
┌────────────────────────────────────────────────────────────────────────┐
│                      🔍 LACAK STATUS PESANAN ANDA                      │
│     Masukkan Nomor Pesanan dan Nomor HP/Email untuk Melihat Status     │
│  ┌─────────────────────────────┐ ┌──────────────────────────────────┐  │
│  │ TRX-20261113-0042           │ │ 081234567890                     │  │
│  └─────────────────────────────┘ └──────────────────────────────────┘  │
│                        [ CARI PESANAN SEKARANG ]                       │
├────────────────────────────────────────────────────────────────────────┤
│ HASIL PELACAKAN: TRX-20261113-0042                                     │
│ Status Pesanan : [ DIKIRIM OLEH KURIR TOKO ] 🚚                        │
│ Status Bayar   : [ LUNAS VIA QRIS ] ✅                                 │
│ Estimasi Tiba  : Hari ini sebelum pukul 17:00                          │
│                                                                        │
│ ── TIMELINE PENGIRIMAN ─────────────────────────────────────────────── │
│ ● 14:15 - Kurir Toko (Ahmad) sedang menuju ke alamat pengiriman       │
│ ○ 13:00 - Paket selesai dikemas dan diserahkan ke kurir internal       │
│ ○ 11:30 - Pembayaran QRIS terkonfirmasi lunas                          │
│ ○ 11:25 - Pesanan berhasil dibuat                                      │
│                                                                        │
│ [ Hubungi CS WhatsApp ]                   [ Unduh Faktur Tagihan PDF ] │
└────────────────────────────────────────────────────────────────────────┘
```

---

## 3. Tata Letak Layar Kurir Toko (Mobile-First Portal)

Portal armada kurir (`/kurir`) dioptimalkan untuk navigasi satu tangan di layar smartphone:

### A. Dashboard Tugas Pengantaran (`Kurir/Dashboard.svelte`)

```
┌────────────────────────────────────────┐
│ 🛵 KURIR BIZMATE       [Shift: Aktif]  │
│ Halo, Ahmad Fauzi                      │
├────────────────────────────────────────┤
│ [📊 RINGKASAN HARI INI]                │
│ ┌──────────────┐ ┌──────────────────┐  │
│ │ 8 DITUGASKAN │ │ 5 SUKSES DIANTAR │  │
│ └──────────────┘ └──────────────────┘  │
├────────────────────────────────────────┤
│ [Tab: Ditugaskan (3)] [Tab: Selesai (5)]│
├────────────────────────────────────────┤
│ [KARTU TUGAS 1]             [ 2.4 KM ] │
│ 📦 #TRX-20261113-0042                  │
│ Penerima : Budi Santoso                │
│ HP       : 0812-3456-7890              │
│ Alamat   : Jl. Kenanga No. 14, RT 02/05│
│            Kec. Lowokwaru, Kota Malang │
│ Tagihan  : [ LUNAS - NON COD ]         │
│                                        │
│ [ 🗺️ Buka Google Maps ] [ 💬 Chat WA ] │
│ [ 👉 PROSES PENGANTARAN ]              │
├────────────────────────────────────────┤
│ [KARTU TUGAS 2]             [ 4.1 KM ] │
│ 📦 #TRX-20261113-0045                  │
│ Penerima : Siti Aminah                 │
│ Tagihan  : [ ⚠️ TAGIH COD: Rp 185.000 ]│
│ [ 🗺️ Buka Google Maps ] [ 💬 Chat WA ] │
│ [ 👉 PROSES PENGANTARAN ]              │
├────────────────────────────────────────┤
│ [ 📷 SCAN BARCODE RESI DENGAN KAMERA ] │
└────────────────────────────────────────┘
```

---

### B. Layar Bukti Pengantaran / POD (`Kurir/TransactionDetail.svelte`)

```
┌────────────────────────────────────────┐
│ ⬅️ DETAIL PENGIRIMAN TRX-0042          │
├────────────────────────────────────────┤
│ 1. AMBIL FOTO BUKTI SERAH TERIMA (POD) │
│ ┌────────────────────────────────────┐ │
│ │  [ 📷 BUKA KAMERA & AMBIL FOTO ]   │ │
│ │  Preview: [Foto Paket & Penerima]  │ │
│ └────────────────────────────────────┘ │
│                                        │
│ 2. IDENTITAS PENERIMA                  │
│ Nama Penerima Paket:                   │
│ [ Budi Santoso (Ybs)                 ] │
│ Hubungan:                              │
│ [ Dropdown: Yang Bersangkutan      v ] │
│ (Pilihan: Keluarga / Satpam / Tetangga)│
│                                        │
│ 3. VALIDASI PEMBAYARAN TUNAI           │
│ [x] Uang Tunai COD Rp 185.000 telah    │
│     diterima dari pemesan.             │
│                                        │
│ Catatan Pengantaran:                   │
│ [ Diterima langsung di teras depan   ] │
│                                        │
│ [ ✅ SELESAIKAN PENGANTARAN SEKARANG ] │
└────────────────────────────────────────┘
```

---

## 4. Tata Letak Layar Admin & Backoffice

Panel kerja desktop padat data (*data-dense desktop view*) untuk kecepatan tinggi:

### A. Dashboard Bisnis & Analitik (`Admin/Dashboard.svelte`)

```
┌──────────┬─────────────────────────────────────────────────────────────┐
│ BIZMATE  │ [🔍 Cari transaksi, produk, user...]  🔔(4)  [Admin Profile]│
├──────────┼─────────────────────────────────────────────────────────────┤
│ • Dashbd │ [ 💰 GMV BULAN INI ]   [ 📦 TOTAL ORDER ]   [ 🎯 AOV ]      │
│ • Produk │   Rp 248.500.000         1.420 Pesanan        Rp 175.000    │
│ • Order  │   ▲ +18.4% vs bln lalu   ▲ +12%               Stabil        │
│ • Kurir  ├─────────────────────────────────────────────────────────────┤
│ • Promosi│ [ GRAFIK TREN PENJUALAN 30 HARI TERAKHIR ]                  │
│ • Member │  Rp |                                                       │
│ • Retur  │     |     /\    /\      /\_                                 │
│ • Laporan│     |__--/  \--/  \____/   \_____                           │
│ • Setting│     +---------------------------- Tanggal                   │
│          ├──────────────────────────────┬──────────────────────────────┤
│          │ [ ⚠️ PERINGATAN STOK RENDAH] │ [ 📊 ANALISIS PARETO 80/20 ] │
│          │ • Sepatu Alpha 42 (Sisa: 2)  │ Top 3 Produk Penyumbang Omset│
│          │ • T-Shirt Black L (Sisa: 1)  │ 1. Sepatu Running Pro (42%)  │
│          │ • Tas Hydropack (Sisa: 0)    │ 2. Smartwatch Z1 (24%)       │
│          │ [Kelola Inventaris ->]       │ 3. Jacket Waterproof (16%)   │
│          ├──────────────────────────────┴──────────────────────────────┤
│          │ [ PESANAN TERBARU MEMBUTUHKAN TINDAKAN ]                    │
│          │ TRX-0042 | Budi S. | Rp 657k | Menunggu Verifikasi Transfer │
│          │ TRX-0043 | Joko W. | Rp 120k | Perlu Packing Kurir Toko    │
└──────────┴─────────────────────────────────────────────────────────────┘
```

---

### B. Editor Master Produk (`Admin/Products/Create.svelte`)

Dilengkapi tab navigasi modular:

```
┌────────────────────────────────────────────────────────────────────────┐
│ 📦 TAMBAH PRODUK BARU                                   [ Simpan Draft]│
├────────────────────────────────────────────────────────────────────────┤
│ [Tab: 1. Umum] [Tab: 2. Varian & Stok] [Tab: 3. Media 3D] [Tab: 4. AI] │
├────────────────────────────────────────────────────────────────────────┤
│ [TAB 4: ASISTEN AI GENERATOR]                                         │
│ Masukkan Kata Kunci: [ Sepatu lari pria, empuk, carbon plate, tahan air│
│ Gaya Penulisan     : [ Dropdown: Persuasif & Menggugah Minat Beli   v ]│
│                                                                        │
│ [ ✨ GENERATE DESKRIPSI & SPESIFIKASI DENGAN AI ]                      │
│                                                                        │
│ Hasil Output Generator (Dapat Diedit Langsung):                        │
│ ┌────────────────────────────────────────────────────────────────────┐ │
│ │ 👟 Taklukkan Setiap Langkah dengan Sensasi Kecepatan Maksimal!      │ │
│ │ Sepatu Running Pro Athlete 2026 dirancang dengan bantalan busa     │ │
│ │ responsif dan pelat karbon ultra-ringan yang mengembalikan energi..│ │
│ │ • Material: Breathable Water-Repellent Mesh                         │ │
│ │ • Berat: Hanya 195 gram                                             │ │
│ └────────────────────────────────────────────────────────────────────┘ │
│ [ Terapkan ke Kolom Deskripsi Utama ]                                  │
└────────────────────────────────────────────────────────────────────────┘
```

---

### C. Manajemen Verifikasi Pembayaran & Dispatch Transaksi (`Admin/Transactions/Show.svelte`)

```
┌────────────────────────────────────────────────────────────────────────┐
│ RINCIAN TRANSAKSI: TRX-20261113-0042                                   │
├──────────────────────────────────────┬─────────────────────────────────┤
│ [DATA PEMBELI & PENGIRIMAN]          │ [VERIFIKASI BUKTI TRANSFER]     │
│ Nama    : Budi Santoso (Member)      │ Metode: Transfer Manual BCA     │
│ Kontak  : 0812-3456-7890             │ Nominal: Rp 657.000             │
│ Alamat  : Jl. Kenanga No. 14, Malang │                                 │
│ Kurir   : Kurir Toko (Internal Fleet)│ [Preview Foto Struk Transfer]   │
│                                      │ Struk Valid Terlihat: Rp 657.000│
│ [ITEM BELANJA]                       │                                 │
│ 2x Sepatu Pro Athlete (Hitam 42)     │ [ ✅ SETUJUI PEMBAYARAN ]       │
│                                      │ [ ❌ TOLAK DENGAN ALASAN ]      │
├──────────────────────────────────────┴─────────────────────────────────┤
│ [LOGISTIK & PENGIRIMAN]                                                │
│ Tugaskan Armada Kurir Toko: [ Pilih Kurir: Ahmad Fauzi (Aktif)      v ]│
│ [ TUGASKAN & KIRIM NOTIFIKASI KE KURIR ]                               │
│                                                                        │
│ [ 🖨️ Cetak Thermal Label Resi A6 (Barcode) ]  [ 📄 Cetak Faktur PDF ]   │
└────────────────────────────────────────────────────────────────────────┘
```

