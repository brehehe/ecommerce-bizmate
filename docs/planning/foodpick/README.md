# 🍔 Dokumentasi & Perencanaan Arsitektur Bisnis FoodPick (Multi-Resto, Dine-In / Takeaway & Resto Delivery)

Dokumen ini memuat spesifikasi menyeluruh mengenai transformasi aplikasi e-commerce **Bizmate** menjadi platform pemesanan makanan & minuman (**FoodPick / Multi-Resto Platform**) menyerupai GoFood, GrabFood, ShopeeFood, atau sistem pemesanan mandiri resto (Chownow / Toast POS).

Konsep ini dirancang **modular dan adaptif** dengan memanfaatkan pengendali variabel lingkungan (`.env`) **tanpa mengubah atau merusak struktur kode e-commerce yang sudah ada** (*Zero Breaking Changes*).

---

## 1. Konsep Bisnis & Prinsip "Zero Breaking Changes"

```
                              ┌───────────────────────────────┐
                              │     BIZMATE CORE PLATFORM     │
                              │ (Laravel 13 + Svelte 5 Monolith)│
                              └──────────────┬────────────────┘
                                             │
                       ┌─────────────────────┴─────────────────────┐
                       │                                           │
             [IS_FOODPICK=false]                          [IS_FOODPICK=true]
                       │                                           │
         ┌─────────────▼─────────────┐               ┌─────────────▼─────────────┐
         │    E-COMMERCE UMUM        │               │   FOODPICK RESTO & DINE   │
         │ • Produk Fisik & Digital  │               │ • Menu Makanan & Minuman  │
         │ • Kurir Ekspedisi (JNE...)│               │ • Hide Kurir Ekspedisi    │
         │ • Multi-Varian Standar    │               │ • Ambil di Resto (Pickup) │
         │ • Durasi Kirim 2-3 Hari   │               │ • Kurir Dapur / Resto     │
         │ • Seller Barang / Toko    │               │ • STRICT 1 RESTO PER CART │
         └───────────────────────────┘               │ • AUTO-RESET CART BEDA    │
                                                     │ • Slot Tanggal & Jam Siap │
                                                     │ • Add-ons / Level Pedas   │
                                                     │ • Kitchen Display (KDS)   │
                                                     │ • Mitra Resto Terpisah    │
                                                     └───────────────────────────┘
```

> [!IMPORTANT]
> **Aturan Mutlak 1 Resto per Transaksi (*Strict Single Resto & Auto-Reset Cart*)**:
> Dalam satu transaksi nampan belanja, pelanggan **hanya dapat memesan menu dari 1 Resto**. Jika pelanggan memilih atau menambahkan menu dari resto lain yang berbeda, sistem secara **otomatis me-reset (mengosongkan nampan transaksi resto sebelumnya)** dan mengisinya dengan menu dari resto yang baru dipilih, disertai notifikasi visual instan (*toast/alert*).

---

## 2. Matriks Konfigurasi Lingkungan (`.env Reference`)

Kombinasi nilai variabel lingkungan di bawah ini mengontrol perilaku platform:

| Variabel Lingkungan | Nilai Default | Opsi Nilai | Penjelasan & Dampak Sistem |
|---|:---:|:---:|---|
| **`IS_FOODPICK`** | `false` | `true` / `false` | **Sakelar Utama**: Jika `true`, UI beradaptasi menjadi tampilan kuliner, mengaktifkan alur jam pickup/masak, menyembunyikan kurir ekspedisi luar kota, memisahkan registrasi resto, dan menegakkan aturan 1 resto auto-reset. |
| **`IS_SELLER`** | `false` | `true` / `false` | Jika digabungkan dengan `IS_FOODPICK=true`: <br>• `true` = **Multi-Resto Marketplace** (Banyak resto/mitra penjual terdaftar seperti ShopeeFood). <br>• `false` = **Single Brand Resto** (1 brand resto dengan satu atau beberapa cabang dapur). |
| **`is_checkout_without_login`** | `true` | `true` / `false` | • `true` = Pelanggan bisa memesan makanan secara instan **tanpa harus login** (cukup isi Nama, WhatsApp, Alamat/Slot). <br>• `false` = Pelanggan wajib login/daftar akun terlebih dahulu. |
| **`FOODPICK_ALLOW_STORE_COURIER`** | `true` | `true` / `false` | Menampilkan opsi pengantaran oleh armada kurir internal toko/resto berbasis radius GPS. |
| **`FOODPICK_ALLOW_SELF_PICKUP`** | `true` | `true` / `false` | Menampilkan opsi ambil langsung di resto (Bungkus / Takeaway / Dine-in) dengan ongkos kirim Rp 0. |
| **`FOODPICK_HIDE_EXPEDITION`** | `true` | `true` / `false` | Menyembunyikan seluruh kurir paket ekspedisi (JNE, J&T, SiCepat, dll.) saat checkout makanan. |
| **`FOODPICK_DEFAULT_PREP_MINUTES`**| `20` | Angka (Menit)| Waktu persiapan/memasak standar untuk kalkulasi estimasi jam makanan siap diambil. |
| **`FOODPICK_SLOT_INTERVAL_MINUTES`**| `15` | Angka (Menit)| Interval pemilih jam pengambilan/pengantaran (contoh: 12:00, 12:15, 12:30, 12:45). |

---

## 3. Peta Dokumen Spesifikasi FoodPick

Dokumentasi FoodPick terbagi ke dalam 5 dokumen spesifikasi mendalam:

1. **[01. Arsitektur .env & Fitur Peran Resto](file:///Users/macbookair/Documents/website/ecommerce/ecommerce-bizmate/docs/planning/foodpick/01-arsitektur-env-dan-fitur-resto.md)**
   - Analisis kebutuhan fungsional: Mitra Resto, Pelanggan Kuliner (Member & Guest), Kurir Resto, dan Admin Platform.
   - Aturan tegas 1 resto dan penanganan auto-reset nampan belanja antar-resto.
   - Adaptasi teks (*wording customization*) antara mode e-commerce vs mode makanan.
2. **[02. Alur Registrasi Mitra Resto vs Pelanggan](file:///Users/macbookair/Documents/website/ecommerce/ecommerce-bizmate/docs/planning/foodpick/02-alur-registrasi-resto-dan-customer.md)**
   - Pemisahan total gerbang registrasi Resto (`/register-resto`) dengan Pelanggan (`/register`).
   - Formulir legalitas dapur, titik koordinat GPS resto, jam operasional buka-tutup, dan rekening pencairan.
   - Verifikasi dan persetujuan mitra resto oleh tim admin.
3. **[03. Alur Pemesanan Makanan, KDS & Logistik](file:///Users/macbookair/Documents/website/ecommerce/ecommerce-bizmate/docs/planning/foodpick/03-alur-pemesanan-kds-dan-fulfillment.md)**
   - Flowchart pemesanan makanan: Guest vs Member.
   - Mekanisme reset otomatis nampan transaksi (*Auto-Reset Cart Mechanism*) saat berpindah resto.
   - Kitchen Display System (KDS): Siklus status masak (Diterima $\rightarrow$ Dimasak $\rightarrow$ Siap Diambil/Diantar).
   - Pemilihan tanggal & slot jam siap diambil vs jam diantar kurir resto.
4. **[04. Skema Database & Modifikasi Master Data](file:///Users/macbookair/Documents/website/ecommerce/ecommerce-bizmate/docs/planning/foodpick/04-skema-database-dan-master-data.md)**
   - Penegakan integritas data: 1 resto per transaksi pada level database (`transactions.resto_id`).
   - Penambahan relasi profil resto, jam operasional, status kesiapan dapur, dan menu modifiers (level pedas, extra topping, catatan koki).
   - Logika backend `AddToCartAction` dengan auto-reset nampan lama.
5. **[05. Spesifikasi Desain UI/UX & Wireframe Layar](file:///Users/macbookair/Documents/website/ecommerce/ecommerce-bizmate/docs/planning/foodpick/05-spesifikasi-tampilan-ui-ux.md)**
   - Wireframe Halaman Etalase Resto, Modal Kustomisasi Topping & Level Pedas Dinamis.
   - Wireframe Toast / Dialog Notifikasi Reset Otomatis Nampan Beda Resto.
   - Checkout Slot Jam, Smart Delivery Fallback, dan Pembayaran Dinamis Per Resto.
   - Layar Monitor Dapur (KDS), Dispatch Kurir Resto, dan Layar Profil Resto.

---

## 4. Fitur Unggulan Arsitektur Terbaru (Rilis v2.1 FoodPick)

Rilis arsitektur terbaru FoodPick melengkapi kebutuhan operasional harian resto mandiri maupun jaringan dapur bersama:

### 1. Metode Pembayaran Mandiri Per Resto (*Decentralized Resto Payments*)
Setiap resto memiliki kendali mandiri untuk mengaktifkan dan mengelola saluran pembayaran sendiri melalui `/admin/resto-profile`:
- **Bayar di Tempat (COD / Kasir Resto)**: Sangat ideal untuk pesanan Ambil Sendiri (Takeaway/Dine-In) atau diantar kurir internal resto.
- **Transfer Bank Resmi Resto**: Pemilik resto memilih bank (BCA, Mandiri, BRI, BNI, BSI, dll.), mengisi nomor rekening resmi, dan nama penerima dana.
- **QRIS Resto Manual / Statis**: Unggah gambar QR code toko asli beserta nama *merchant*. Saat checkout, pembeli langsung disuguhkan kode QR resto tersebut.
- **Payment Gateway: WARIS QRIS Dinamis Resto**: Resto dapat mengonfigurasi API Key, Webhook Secret, dan QRIS Code Target WARIS mandiri untuk generate dynamic QRIS real-time dengan verifikasi instan via Webhook.

### 2. Logistik Cerdas & Fallback Pengantaran (*Zero Shipping Failure*)
Mengatasi masalah kegagalan checkout umum pada sistem e-commerce standar ("Tidak ada layanan pengiriman yang tersedia untuk alamat ini"):
- **Mode Fleksibel**: Resto dapat memilih skema tarif **Flat** (ongkir seragam) atau **Per KM** berbasis jarak Haversine koordinat GPS.
- **Smart Fallback untuk Tamu (Guest)**: Jika pelanggan belum menyalakan izin GPS atau hanya memilih area kecamatan melalui teks, sistem secara cerdas menggunakan **Tarif Dasar Flat Resto** (`delivery_fee_base`, default Rp 10.000). Transaksi tidak pernah macet akibat ketiadaan kurir!
- **Ambang Bebas Ongkir**: Fitur otomatis gratis ongkir jika nilai pesanan makanan mencapai `delivery_free_min_order`.

### 3. Kustomisasi Menu 100% Dinamis (*Dynamic Product Modifiers*)
Varian makanan tidak lagi menggunakan hardcoded default fiktif:
- Dikelola langsung melalui rute `/admin/products/{id}/modifiers`.
- Pemilik resto dapat membuat grup varian tanpa batas (Level Pedas, Pilihan Nasi, Ekstra Topping, Suhu Minuman, dll.).
- Mendukung opsi **Pilihan Tunggal (Radio)** atau **Pilihan Ganda (Checkbox)**, pengaturan wajib (*required*) atau opsional, dan batas maksimum pilihan.
- Jika menu tidak memiliki modifier di database, modal kustomisasi hanya menampilkan pengatur kuantitas dan catatan koki dapur tanpa opsi palsu.

### 4. Standar Antarmuka Admin Terpadu & Elegan
Tiga halaman administrasi baru:
- **Profil Dapur & Pengantaran**: `http://localhost:8000/admin/resto-profile`
- **Manajemen Modifiers Menu**: `http://localhost:8000/admin/products/{id}/modifiers`
- **Dispatch Kurir Internal**: `http://localhost:8000/admin/courier-dispatch`

Seluruh halaman ini mengikuti konsistensi visual resmi Bizmate Admin yang telah ada (`/admin/transactions` dan `/admin/chats`), mencakup *card layout*, navigasi tab, tombol primer bertema dinamis, dan integrasi notifikasi toast.

