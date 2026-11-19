# 🎨 Spesifikasi Desain UI/UX & Wireframe Layar FoodPick (Screens & Interface Layouts)

Dokumen ini menyajikan rancangan antarmuka visual (*UI/UX mockups*), hierarki tata letak, serta komponen layar untuk seluruh ekosistem **FoodPick**: **Storefront Resto & Menu**, **Modal Kustomisasi Topping/Pedas**, **Halaman Checkout Slot Waktu**, **Kitchen Display System (KDS)**, dan **Portal Kurir Makanan**.

---

## 1. Halaman Direktori Resto & Kuliner (`Storefront/Home.svelte`)

Saat mode FoodPick aktif (`IS_FOODPICK=true`), beranda beradaptasi menjadi direktori kuliner modern bernuansa *warm cream* kuliner yang organik, natural, dan bebas dari tampilan kaku ala AI (*anti-slop*).

### A. Tampilan Desktop (Web Browser Layar Lebar)
Menggunakan palet warna *warm cream* (`#fbf4eb`), kartu resto horizontal lebar dengan tombol aksi mencolok (`Lihat menu →`), serta header dinamis berbasis kota resto (`Pilihan resto di Surabaya`):

```
┌────────────────────────────────────────────────────────────────────────────────────────┐
│ [🍴 Dapur & Resto Terpilih]                                                            │
│ ┌────────────────────────────────────────────────────────────────────────────────────┐ │
│ │  Mau makan apa hari ini?                                     [ Foto Piring Hidangan│ │
│ │  Pilih resto favorit, lalu pesan untuk diambil atau diantar.   Kuliner Nusantara   │ │
│ │                                                                Ayam Bakar/Sambal   │ │
│ │  🛍️ Ambil sendiri   |   🍽️ Makan di tempat   |   🛵 Diantar resto     Goreng ]      │ │
│ └────────────────────────────────────────────────────────────────────────────────────┘ │
│                                                                                        │
│ [ Kategori Kuliner ]:                                                                  │
│ ( Semua Kuliner )  [🍗 Aneka Ayam & Bebek]  [☕ Kopi & Minuman]  [🍜 Bakso & Mie] [ > ]│
│ *(Aktif: Rust Brown #a0460d)*                                                          │
│                                                                                        │
│ ── Pilihan resto di Surabaya ───────────────────────── [ 🔍 Cari resto... ] [Daftar Mitra >]
│    Pesan menu favorit Anda langsung dari resto terpercaya.                             │
│                                                                                        │
│ ┌────────────────────────────────────────────────────────────────────────────────────┐ │
│ │ ┌───────────────┐  Test                                    [🟢 Buka]               │ │
│ │ │               │  Aneka Nasi & Lauk                                               │ │
│ │ │ [Foto Menu/   │                                              ┌─────────────────┐ │ │
│ │ │  Banner Resto]│  ⭐ 4.9    ⏱️ 20 menit    📍 Bratang 1H, Sby │  Lihat menu →   │ │ │
│ │ └───────────────┘                                              └─────────────────┘ │ │
│ └────────────────────────────────────────────────────────────────────────────────────┘ │
└────────────────────────────────────────────────────────────────────────────────────────┘
```

### B. Tampilan Mobile (Smartphone / Layar Sentuh)
Mengoptimalkan kenyamanan jempol dengan hero banner berfoto hidangan melingkar di kanan atas, 3 mini-card pilihan layanan (*Ambil Sendiri*, *Makan di Tempat*, *Diantar Kurir*), kartu resto kompak 2-baris, diikuti banner Flash Sale dan rekomendasi kuliner 2 kolom:

```
┌──────────────────────────────────────────────┐
│ [🔍 Cari produk, brand...        ] [🔔] [TN] │
├──────────────────────────────────────────────┤
│ ┌──────────────────────────────────────────┐ │
│ │ [🍴 Dapur & Resto Terpilih]              │ │
│ │ Mau makan apa hari ini?   (( Foto Dish   │ │
│ │ Pesan dari resto favorit,     Melingkar ))│ │
│ │ ambil sendiri atau diantar.              │ │
│ │ ┌─────────────┐ ┌─────────────┐ ┌──────┐ │ │
│ │ │ 🛍️ Ambil    │ │ 🍽️ Makan    │ │ 🛵   │ │ │
│ │ │   Sendiri   │ │   di Tempat │ │Diantar│ │
│ │ │ (Takeaway)  │ │   (Dine-In) │ │ Kurir│ │ │
│ │ └─────────────┘ └─────────────┘ └──────┘ │ │
│ └──────────────────────────────────────────┘ │
│                                              │
│ [Semua Kuliner] [🍗 Aneka Ayam] [☕ Kopi...] │
│                                              │
│ Mitra Resto & Dapur Pilihan  [ 1 Resto ]     │
│ Pesan menu favorit Anda langsung dari resto  │
│ [ 🔍 Cari resto...               ] [Daftar >]│
│                                              │
│ ┌──────────────────────────────────────────┐ │
│ │ ┌───────┐  Test             [🟢 BUKA] ⭐4.9│ │
│ │ │ Foto  │  Aneka Nasi & Lauk             │ │
│ │ │ 1:1   │  📍 Bratang 1H No 23, Surabaya │ │
│ │ └───────┘  ⏱️ Siap ~20 mnt    Lihat Menu >│ │
│ └──────────────────────────────────────────┘ │
│                                              │
│ ┌──────────────────────────────────────────┐ │
│ │ ⚡ KILAT Belanja FLASH SALE S.D. 70%      │ │
│ └──────────────────────────────────────────┘ │
│                                              │
│ ✨ Rekomendasi Untuk Anda                    │
│ ┌──────────────────┐  ┌──────────────────┐   │
│ │ [Foto Ayam Nasi] │  │ [Foto Fried Chck]│   │
│ │ Rp 22.000        │  │ Rp 25.000        │   │
│ └──────────────────┘  └──────────────────┘   │
├──────────────────────────────────────────────┤
│ [🏠 Beranda] [🏬 Produk] [➕ Jual] [💬] [👤]  │
└──────────────────────────────────────────────┘
```

---

## 2. Halaman Etalase Resto & Daftar Menu (`Storefront/SellerStore.svelte`)

Menampilkan daftar menu makanan per kategori kuliner:

```
┌────────────────────────────────────────────────────────────────────────┐
│ ⬅️ Kembali ke Direktori                                                │
│ ┌────────────────────────────────────────────────────────────────────┐ │
│ │ [BANNER FOTO DAPUR RESTO]                                          │ │
│ │ [Logo] BEBEK SINJAY PAK JANGKUNG                         [🟢 BUKA] │ │
│ │ ⭐ 4.9 (850+ Ulasan) • 📍 1.4 km dari lokasi Anda                  │ │
│ │ Alamat: Jl. Soekarno Hatta No. 45, Malang                          │ │
│ │ Jam Buka: 09:00 - 21:30 WIB • ⏱️ Estimasi Waktu Masak: ~20 Menit   │ │
│ └────────────────────────────────────────────────────────────────────┘ │
├────────────────────────────────────────────────────────────────────────┤
│ [TAB KATEGORI MENU]: [ Semua ] [ Makanan Utama ] [ Minuman ] [ Snack ] │
├────────────────────────────────────────────────────────────────────────┤
│ ── MAKANAN UTAMA ───────────────────────────────────────────────────── │
│ ┌────────────────────────────────────────────────────────────────────┐ │
│ │ [Foto 1:1]  PAKET NASI BEBEK KREMES MADURA                         │ │
│ │             Bebek goreng empuk bumbu hitam dengan kremes gurih,    │ │
│ │             nasi putih hangat, sambal korek, dan lalapan segar.    │ │
│ │             Rp 35.000                                              │ │
│ │             [ + TAMBAH KE NAMPAN ]                                 │ │
│ └────────────────────────────────────────────────────────────────────┘ │
│ ┌────────────────────────────────────────────────────────────────────┐ │
│ │ [Foto 1:1]  AYAM GEPREK SAMBAL BAWANG                              │ │
│ │             Ayam krispi digeprek dengan cabai rawit segar pedas.   │ │
│ │             Rp 22.000                                              │ │
│ │             [ + TAMBAH KE NAMPAN ]                                 │ │
│ └────────────────────────────────────────────────────────────────────┘ │
└────────────────────────────────────────────────────────────────────────┘
```

---

## 3. Modal Kustomisasi Menu Dinamis (`FoodModifierModal.svelte`)

Saat pelanggan mengklik tombol "+ Pesan" atau "+ Tambah ke Nampan", sistem membuka modal kustomisasi. Seluruh grup dan opsi dimuat secara **100% dinamis** dari database (dikonfigurasikan melalui `/admin/products/{id}/modifiers`):

### A. Kondisi Produk dengan Kustomisasi Khusus
Jika resto telah mengatur grup modifier (misal: Level Pedas, Pilihan Nasi, Ekstra Topping):

```
┌────────────────────────────────────────────────────────────────────────┐
│ 🍽️ Kustomisasi Menu                                                  ✖ │
│    Pilih opsi dan kustomisasi menu sesuai selera Anda                  │
├────────────────────────────────────────────────────────────────────────┤
│ [Foto] PAKET NASI AYAM GEPREK (Rp 22.000)                              │
├────────────────────────────────────────────────────────────────────────┤
│ ── 1. PILIHAN LEVEL PEDAS (Wajib Pilih 1) ──────────────────────────── │
│ ( ) Level 0: Tidak Pedas (Tanpa Cabai)                    +Rp 0        │
│ ( ) Level 1: Sedang (Cabai 3)                             +Rp 0        │
│ (o) Level 2: Pedas Nampol (Cabai 7)                       +Rp 0        │
│ ( ) Level 3: Ekstra Pedas Gila (Cabai 15)                 +Rp 3.000    │
│                                                                        │
│ ── 2. EKSTRA TOPPING / LAUK (Bisa Pilih Banyak) ────────────────────── │
│ [x] Tambah Telur Mata Sapi Goreng                         +Rp 4.000    │
│ [ ] Tambah Keju Mozzarella Leleh                          +Rp 6.000    │
│                                                                        │
│ ── CATATAN KHUSUS KOKI DAPUR ───────────────────────────────────────── │
│ [ Sambal dipisah di wadah kecil ya koki, jangan campur ke nasi      ] │
├────────────────────────────────────────────────────────────────────────┤
│ Jumlah: [ - ]  1  [ + ]     TOTAL HARGA: Rp 26.000                     │
│ [ 🛍️ Masukkan ke Nampan Belanja ]                                      │
└────────────────────────────────────────────────────────────────────────┘
```

### B. Kondisi Produk Standar (Tanpa Modifier di Database)
Jika menu belum atau tidak memerlukan pilihan variasi (misal: "Es Teh Manis", "Kerupuk Kaleng", atau produk pengujian baru), sistem **TIDAK memunculkan opsi fiktif**:

```
┌────────────────────────────────────────────────────────────────────────┐
│ 🍽️ Kustomisasi Menu                                                  ✖ │
│    Atur jumlah pesanan dan catatan koki dapur                          │
├────────────────────────────────────────────────────────────────────────┤
│ [Foto] ES TEH MANIS JUMBO (Rp 5.000)                                   │
├────────────────────────────────────────────────────────────────────────┤
│ ℹ️ Menu ini disajikan standar tanpa pilihan topping tambahan.           │
│    Tentukan jumlah pesanan dan tuliskan catatan koki jika diperlukan.  │
│                                                                        │
│ ── CATATAN KHUSUS KOKI DAPUR ───────────────────────────────────────── │
│ [ Es batunya sedikit saja ya kak                                    ] │
├────────────────────────────────────────────────────────────────────────┤
│ Jumlah: [ - ]  2  [ + ]     TOTAL HARGA: Rp 10.000                     │
│ [ 🛍️ Masukkan ke Nampan Belanja ]                                      │
└────────────────────────────────────────────────────────────────────────┘
```

---

## 4. Komponen Notifikasi Reset Otomatis Beda Resto (*Auto-Reset Toast & Modal*)

Ketika pembeli yang sudah memiliki pesanan di **Resto A** mencoba menambahkan menu dari **Resto B**, sistem memicu antarmuka reset otomatis:

### A. Tampilan Floating Toast Alert (Reset Otomatis Langsung)
Banner mengambang yang muncul di sudut atas layar dengan transisi slide-in:

```
┌────────────────────────────────────────────────────────────────────────┐
│ ⚠️ NAMPAN BELANJA OTOMATIS DI-RESET!                                 ✖ │
│ Pesanan sebelumnya dari "Bebek Sinjay" telah dikosongkan.              │
│ Nampan Anda sekarang berisi menu dari "Kopi Kenangan Senja".           │
└────────────────────────────────────────────────────────────────────────┘
```

### B. Opsi Modal Konfirmasi Preventif (Pilihan Alternatif UX)
Modal popup yang muncul sebelum nampan direset:

```
┌────────────────────────────────────────────────────────────────────────┐
│ 🍽️ GANTI RESTO & KOSONGKAN NAMPAN?                                    │
├────────────────────────────────────────────────────────────────────────┤
│ Nampan Anda saat ini berisi menu dari:                                 │
│ 🏬 Bebek Sinjay Pak Jangkung (2 Item - Rp 49.000)                      │
│                                                                        │
│ Anda hanya dapat memesan dari 1 Resto dalam satu transaksi.            │
│ Jika Anda memesan dari "Kopi Kenangan Senja", menu sebelumnya          │
│ akan otomatis dihapus.                                                 │
├────────────────────────────────────────────────────────────────────────┤
│ [ ❌ Tetap di Resto Sebelumnya ]   [ 🔄 Ya, Reset & Ganti Resto Baru ] │
└────────────────────────────────────────────────────────────────────────┘
```

---

## 5. Halaman Checkout Makanan Terpadu (Guest & Slot Waktu)

Menghilangkan kurir ekspedisi, fokus pada Ambil Sendiri vs Kurir Resto:

```
┌──────────────────────────────────────┬─────────────────────────────────┐
│ [BAGIAN KIRI: DATA & WAKTU PEMESANAN]│ [BAGIAN KANAN: RINGKASAN MENU]  │
│                                      │                                 │
│ ── 1. DATA PEMESAN (GUEST / MEMBER) ─│ ── RESTO: BEBEK SINJAY MADURA ─ │
│ Nama Pemesan : [ Budi Santoso      ] │ 1x Paket Bebek Kremes Madura    │
│ No. WhatsApp : [ 0812-3456-7890    ] │    • Nasi Uduk (+Rp 2.000)      │
│ Email Nota   : [ budi@gmail.com    ] │    • Tambah Telur (+Rp 4.000)   │
│                                      │    Subtotal: Rp 41.000          │
│ ── 2. METODE PEMENUHAN MAKANAN ──────│ 1x Es Jeruk Peras Jumbo         │
│ ┌──────────────────┐ ┌─────────────┐ │    Subtotal: Rp  8.000          │
│ │(o) AMBIL DI RESTO│ │( )KURIR RESTO││                                 │
│ └──────────────────┘ └─────────────┘ │ Subtotal Menu     : Rp 49.000   │
│                                      │ Ongkos Kirim      : Rp      0   │
│ [PILIHAN LAYANAN]:                   │ Biaya Layanan     : Rp  1.000   │
│ (o) Bungkus Bawa Pulang (Takeaway)   │ Diskon Promo      : -Rp 5.000   │
│ ( ) Makan di Tempat (Dine-In)        │ ─────────────────────────────── │
│     Nomor Meja: [ Meja 07          ] │ TOTAL PEMBAYARAN  : Rp 45.000   │
│                                      │                                 │
│ ── 3. ESTIMASI JAM SIAP DIAMBIL ──── │ ── 4. METODE PEMBAYARAN ─────── │
│ Tanggal Ambil:                       │ (o) QRIS Instan (BCA/Gopay/DANA)│
│ [ Dropdown: Hari Ini (13 Nov 2026) v]│ ( ) Bayar Tunai di Kasir Resto  │
│                                      │ ( ) Transfer Bank Manual        │
│ Jam Makanan Siap Diambil di Resto:   │                                 │
│ [ Dropdown: 12:30 WIB (Paling Cepat)v]│ [ 🔒 PESAN SEKARANG ]           │
│ *(Dapur butuh ~20 menit untuk masak)*│                                 │
└──────────────────────────────────────┴─────────────────────────────────┘
```

> [!NOTE]
> **Pembaruan Sistem Logistik Pintar & Anti-Gagal (Smart Delivery Fallback)**:
> Jika pembeli memilih opsi **"Diantar Kurir Resto"**, sistem menerapkan mekanisme kalkulasi adaptif:
> 1. **Perhitungan Jarak GPS (Haversine)**: Mengukur jarak kilometer dari koordinat dapur resto (`resto_profiles.latitude`, `resto_profiles.longitude`) ke titik koordinat pembeli.
> 2. **Fallback Otomatis Tarif Dasar**: Jika pelanggan checkout sebagai Tamu (*Guest Checkout*) dan belum mengaktifkan GPS / hanya mengetik teks alamat, sistem **TIDAK MELEMPARKAN ERROR** `"Tidak ada layanan pengiriman yang tersedia untuk alamat ini"`. Sistem secara cerdas menggunakan **Tarif Dasar Pengantaran Flat** yang diatur pemilik resto (`$restoProfile->delivery_fee_base ?? 10000`).
> 3. **Ambang Gratis Ongkir**: Jika subtotal belanja mencapai batas `delivery_free_min_order`, ongkir otomatis menjadi **Rp 0 (Gratis Ongkir)**.
> 4. **Metode Pembayaran Dinamis Per Resto**: Bagian kanan menampilkan metode pembayaran independen milik resto terkait:
>    - **QRIS Resto**: Dilengkapi nama merchant dan scan gambar QRIS asli resto.
>    - **Transfer Bank**: Dilengkapi nama bank pilihan resto, nomor rekening resmi, dan nama pemilik rekening.
>    - **Bayar di Tempat (COD / Kasir)**: Untuk pembayaran tunai langsung saat makanan diserahkan.

---

## 6. Layar Monitor Dapur: Kitchen Display System (KDS)

Dijalankan pada tablet/layar sentuh di dapur resto (`/resto/kds`):

```
┌────────────────────────────────────────────────────────────────────────┐
│ 🍳 DAPUR RESTO BEBEK SINJAY             🔔 Bel Suara [ON]   12:10 WIB  │
├────────────────────┬────────────────────┬──────────────────────────────┤
│ 📥 PESANAN BARU (1)│ 🍳 SEDANG DIMASAK  │ 📦 SIAP DIAMBIL / ANTAR (2) │
├────────────────────┼────────────────────┼──────────────────────────────┤
│ [TIKET #0042]      │ [TIKET #0041]      │ [TIKET #0040]                │
│ Waktu: 12:08 WIB   │ Target: 12:20 WIB  │ Status: Menunggu Pelanggan   │
│ [ AMBIL DI RESTO ] │ [ KURIR RESTO ]    │ Pemesan: Ibu Ratna (Takeaway)│
│ Jam Siap: 12:30 WIB│ Jam Antar: 12:25   │ 1x Ayam Bakar Madu           │
│ Pemesan: Budi S.   │ Pemesan: Dimas     │ 1x Es Teh Manis              │
│                    │                    │                              │
│ • 1x Bebek Kremes  │ • 2x Nasi Goreng   │ [ WA: Pesanan Siap Terkirim] │
│   - Nasi Uduk      │   - Level 3 (Pedas)│ [ ✅ SELESAIKAN ORDER ]       │
│   - Level 2        │   - Telur Ceplok   │                              │
│   - Tambah Telur   │                    │                              │
│ Catatan: Sambal    │ Catatan: Bawang    │                              │
│ dipisah!           │ merah banyakin     │                              │
│                    │                    │                              │
│ [ ▶️ MULAI MASAK ] │ [ 📦 SELESAI MASAK ]│                              │
└────────────────────┴────────────────────┴──────────────────────────────┘
```

---

## 7. Layar Pelacakan Status Makanan Pelanggan (`Storefront/TrackOrder.svelte`)

Layar pelacakan real-time pembeli yang responsif di ponsel:

```
┌────────────────────────────────────────┐
│ 🛵 STATUS PESANAN MAKANAN ANDA         │
│ No. Pesanan: #TRX-FOOD-20261113-0042   │
│ Resto      : Bebek Sinjay Pak Jangkung │
├────────────────────────────────────────┤
│ [📊 PROGRESS MEMASAK REALTIME]         │
│  [ Diterima ] ── [ Dimasak ] ── [ Siap Diambil ] ── [ Selesai ]
│       ●               ●               ○                  ○     │
│                                        │
│ 🔥 "Dapur sedang memasak pesanan Anda!"│
│ Estimasi Siap: Pukul 12:30 WIB (15 mnt)│
├────────────────────────────────────────┤
│ ── RINCIAN PENGAMBILAN ─────────────── │
│ Metode: Ambil Sendiri (Takeaway)       │
│ Lokasi: Jl. Soekarno Hatta No. 45      │
│ [ 🗺️ Buka Peta Menuju Resto ]          │
│                                        │
│ ── KODE AMBIL DI KASIR RESTO ───────── │
│ ┌────────────────────────────────────┐ │
│ │          KODE: BZM-0042            │ │
│ │    [ Tampilkan QR Code Kasir ]     │ │
│ └────────────────────────────────────┘ │
│                                        │
│ [ 💬 Hubungi WhatsApp Resto ]          │
└────────────────────────────────────────┘
```

---

## 8. Layar Profil Dapur, Skema Tarif Pengantaran & Pembayaran Resto (`/admin/resto-profile`)

Didesain khusus untuk pemilik dan manajer resto dengan standar antarmuka konsisten yang selaras dengan `/admin/transactions` dan `/admin/chats` (sidebar navigasi resmi, palet warna primer/sekunder, kartu formulir modular, dan pratinjau live):

```
┌──────────────────────────────────────────────────────────┬──────────────────────┐
│ [DASHBOARD / PROFIL DAPUR RESTO]                         │ [PRATINJAU KARTU]    │
│ Profil Dapur & Mitra Resto  [🟢 TERVERIFIKASI]           │ ┌──────────────────┐ │
│ Atur operasional, koordinat GPS, tarif kurir & pembayaran│ │ [Foto 1:1]  🟢BUKA│ │
│                                                          │ │ Bebek Sinjay     │ │
│ ── 1. IDENTITAS DAPUR & KATEGORI KULINER ─────────────── │ │ Aneka Nasi & Bebk│ │
│ Nama Resto / Dapur : [ Bebek Sinjay Pak Jangkung       ] │ │ ⭐ 4.9 • 20 menit │ │
│ Kategori Utama     : [ Dropdown: Aneka Ayam & Bebek   v] │ │ 📍 Bratang 1H    │ │
│ Deskripsi Cita Rasa: [ Bebek goreng sambal mangga muda ] │ └──────────────────┘ │
│ Logo Resto (1:1)   : [ Unggah Logo ]                     │ [ Simpan Profil ]    │
│ Banner (Landscape) : [ Unggah Banner ]                   │                      │
│                                                          │ [ 🍳 Buka Layar KDS ]│
│ ── 2. ALAMAT & TITIK KOORDINAT GPS ───────────────────── │                      │
│ Alamat Lengkap Resto:                                    │                      │
│ [ Jl. Bratang 1H No. 23, Gubeng, Kota Surabaya        ] │                      │
│ Latitude: [ -7.290123 ]    Longitude: [ 112.754321    ] │                      │
│ [ 📍 Deteksi GPS Saya ]    [ 🗺️ Buka di Google Maps ↗ ]  │                      │
│                                                          │                      │
│ ── 3. WAKTU MEMASAK & SKEMA PENGANTARAN ──────────────── │                      │
│ Estimasi Waktu Masak : [ 20 ] Menit                      │                      │
│ Radius Maksimal Kurir: [ 10 ] KM                         │                      │
│ [x] Izinkan Ambil di Resto (Takeaway & Dine-In)          │                      │
│ [x] Izinkan Kurir Resto (Diantar Armada Sendiri)         │                      │
│                                                          │                      │
│ SKEMA TARIF ONGKOS KIRIM KURIR RESTO:                    │                      │
│ (o) Tarif Flat (Tarif tetap seluruh area radius)         │                      │
│ ( ) Tarif Berbasis Jarak (Per KM dari titik dapur GPS)   │                      │
│ Tarif Dasar Pengantaran   : [ Rp 10.000                ] │                      │
│ Minimal Belanja Bebas Ongkir: [ Rp 100.000             ] │                      │
│                                                          │                      │
│ ── 4. JADWAL JAM OPERASIONAL HARIAN ──────────────────── │                      │
│ Senin s/d Jumat : [ 09.00 ] s/d [ 21.00 ]  [ ] Libur     │                      │
│ Sabtu s/d Minggu: [ 09.00 ] s/d [ 22.00 ]  [ ] Libur     │                      │
│                                                          │                      │
│ ── 5. METODE PEMBAYARAN RESTO (MANDIRI & INDEPENDEN) ─── │                      │
│ ┌──────────────────────────────────────────────────────┐ │                      │
│ │ [x] Bayar di Tempat (COD / Kasir Resto)              │ │                      │
│ │     Pelanggan membayar tunai saat ambil / antar.     │ │                      │
│ ├──────────────────────────────────────────────────────┤ │                      │
│ │ [x] Transfer Bank Resmi Resto                        │ │                      │
│ │     Pilih Bank    : [ BCA                          v]│ │                      │
│ │     No. Rekening  : [ 8290-123-456                 ] │ │                      │
│ │     Nama Rekening : [ Totiyono Nugroho             ] │ │                      │
│ ├──────────────────────────────────────────────────────┤ │                      │
│ │ [x] QRIS Resto (Semua E-Wallet & M-Banking)          │ │                      │
│ │     Nama Merchant : [ BEBEK SINJAY SURABAYA        ] │ │                      │
│ │     Unggah QRIS   : [ Ganti File QRIS ]              │ │                      │
│ │     [ Pratinjau Gambar Kode QRIS Resto Aktif ]       │ │                      │
│ ├──────────────────────────────────────────────────────┤ │                      │
│ │ [x] Payment Gateway: WARIS QRIS Dinamis Resto        │ │                      │
│ │     API Base URL  : [ https://analisis.giradia.id/..]│ │                      │
│ │     WARIS API Key : [ wrs_live_resto_secret...     ] │ │                      │
│ │     Webhook Secret: [ whsec_resto_hmac...          ] │ │                      │
│ │     QRIS Code     : [ random                       ] │ │                      │
│ │     Biaya Admin   : [ Rp 0                         ] │ │                      │
│ │     Webhook URL   : /api/webhooks/waris              │ │                      │
│ └──────────────────────────────────────────────────────┘ │                      │
└──────────────────────────────────────────────────────────┴──────────────────────┘
```

---

## 9. Layar Manajemen Modifiers & Varian Produk Dinamis (`/admin/products/{id}/modifiers`)

Memberikan kendali penuh kepada resto untuk menyusun grup variasi menu tanpa mengandalkan nilai default fiktif. Tampilan dirancang bersih dan profesional mengikuti bahasa desain `/admin/transactions`:

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│ [KATALOG PRODUK / KUSTOMISASI MODIFIERS]                                        │
│ Kustomisasi & Modifiers Menu: "Paket Nasi Bebek Kremes"                         │
│ Kelola level pedas, pilihan karbohidrat, dan aneka topping tambahan             │
├─────────────────────────────────────────────────────────────────────────────────┤
│ [ ⬅️ Kembali ke Detail Produk ]                           [ ➕ Tambah Grup Baru ]│
│                                                                                 │
│ ┌─────────────────────────────────────────────────────────────────────────────┐ │
│ │ 🌶️ Pilihan Level Pedas                   [ Wajib Pilih ] [ Pilihan Tunggal]│ │
│ │ Opsi yang Tersedia:                                                         │ │
│ │ • Level 0: Tidak Pedas (Tanpa Cabai)                     +Rp 0   (Default) │ │
│ │ • Level 1: Sedang (Cabai 3)                              +Rp 0             │ │
│ │ • Level 2: Pedas Nampol (Cabai 7)                        +Rp 0             │ │
│ │ • Level 3: Ekstra Pedas Gila (Cabai 15)                  +Rp 3.000         │ │
│ │ [ ✏️ Edit Grup & Opsi ]                                    [ 🗑️ Hapus Grup ] │ │
│ └─────────────────────────────────────────────────────────────────────────────┘ │
│                                                                                 │
│ ┌─────────────────────────────────────────────────────────────────────────────┐ │
│ │ 🍳 Ekstra Lauk & Topping Tambahan        [ Opsional ]    [ Pilihan Ganda ]  │ │
│ │ Batas Maksimal: 5 Pilihan                                                   │ │
│ │ Opsi yang Tersedia:                                                         │ │
│ │ • Tambah Telur Mata Sapi Goreng                          +Rp 4.000         │ │
│ │ • Tambah Sambal Korek Cup                                +Rp 3.000         │ │
│ │ • Tambah Keju Mozzarella Leleh                           +Rp 6.000         │ │
│ │ [ ✏️ Edit Grup & Opsi ]                                    [ 🗑️ Hapus Grup ] │ │
│ └─────────────────────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### Modal Input Grup & Opsi Modifier Dinamis:
- **Nama Grup**: e.g., "Pilihan Level Pedas", "Jenis Nasi", "Topping Ekstra".
- **Aturan Pilihan**:
  - `is_required`: Wajib dipilih (pelanggan tidak bisa checkout sebelum memilih) vs Opsional.
  - `is_multiple`: Radio (Pilih 1 saja) vs Checkbox (Pilih banyak).
  - `max_selectable`: Batas maksimal item yang dapat dicentang jika multiple.
- **Daftar Opsi Item**:
  - Nama opsi (e.g. "Level 2 (Pedas)"), harga tambahan (e.g. `Rp 3.000`), status default, dan status ketersediaan (*In Stock / Out of Stock*).

---

## 10. Layar Dispatch & Manajemen Pengantaran Kurir Resto (`/admin/courier-dispatch`)

Layar operasional bagi admin resto atau koordinator kurir internal untuk memantau pesanan delivery yang siap diberangkatkan:

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│ [KURIR & PENGANTARAN RESTO]                                                     │
│ Daftar Pengantaran Makanan oleh Kurir Internal Resto                             │
├─────────────────────────────────────────────────────────────────────────────────┤
│ [ Tab: Siap Diantar (3) ]   [ Tab: Sedang Diantar (2) ]   [ Tab: Selesai (18) ] │
├─────────────────────────────────────────────────────────────────────────────────┤
│ [ 🔍 Cari No. Transaksi, Nama Pelanggan, atau Alamat...                      ] │
│                                                                                 │
│ ┌─────────────────────────────────────────────────────────────────────────────┐ │
│ │ #TRX-20261116-0045   •   Waktu Pesan: 12:15 WIB (15 mnt lalu)               │ │
│ │ Pelanggan : Totiyono Nugroho (0856-5662-9097)                               │ │
│ │ Alamat    : Jl. Ngagel Madya No. 12, Wonokromo, Surabaya                    │ │
│ │ Pesanan   : 2x Ayam Geprek Sambal Bawang, 2x Es Teh Jumbo                   │ │
│ │ Total Bayar: Rp 54.000 (COD / Bayar di Tempat)                              │ │
│ │ Jarak     : 2.4 KM  |  Ongkir: Rp 10.000                                    │ │
│ │                                                                             │ │
│ │ [ 🗺️ Buka Rute Google Maps ]     [ 💬 Hubungi WhatsApp Pelanggan ]          │ │
│ │ [ 🛵 TUGASKAN KURIR / BERANGKAT ]                                            │ │
│ └─────────────────────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────────────────┘
```

Fitur Unggulan Layar Dispatch:
1. **Navigasi Cepat 1-Klik**: Tombol Google Maps otomatis membuka navigasi koordinat/alamat tujuan pemesan.
2. **Komunikasi WhatsApp Instan**: Template pesan WhatsApp otomatis menyapa pelanggan dan menginformasikan bahwa pesanan sedang dalam perjalanan bersama kurir resto.
3. **Pembaruan Status Real-Time**: Transisi status dari `ready_for_pickup` $\rightarrow$ `sedang_dikirim` $\rightarrow$ `selesai` secara langsung memperbarui monitor KDS dan layar lacak pesanan milik pelanggan.


