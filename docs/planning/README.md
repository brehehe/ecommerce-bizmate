# 📚 Dokumentasi & Perencanaan Lengkap E-Commerce Bizmate (Master Planning Suite)

Selamat datang di repositori dokumentasi dan perencanaan arsitektur menyeluruh aplikasi e-commerce **Bizmate**. Dokumen ini disusun untuk memberikan gambaran lengkap mengenai kapabilitas bisnis, spesifikasi fungsional untuk setiap peran pengguna, diagram alur transaksi, rancangan database, serta tata letak antarmuka sistem.

---

## 🏛️ Ringkasan Arsitektur Teknologi

Bizmate dirancang dengan arsitektur **Modern Monolith SPA** yang menyatukan kestabilan ekosistem Laravel di sisi server dengan kecepatan reaktif frontend modern tanpa overhead API boilerplate:

- **Backend Framework**: Laravel 13 running on PHP 8.3
- **Frontend SPA**: Svelte 5 (menggunakan paradigma *Runes* reaktif: `$state`, `$derived`, `$props`, `$effect`)
- **Penghubung SPA**: Inertia.js v3 (HTTP standalone requests, automatic rollback, instant visits, partial reload)
- **Styling Engine**: Tailwind CSS v4 dengan sistem palet dinamis
- **Type Safety Rute**: Laravel Wayfinder (TypeScript auto-generated controller actions & named routes)
- **Basis Data**: MySQL / PostgreSQL / SQLite dengan 40+ tabel terindeks komposit optimal
- **Real-time WebSockets**: Laravel Reverb untuk live chat pelanggan-CS dan update status transaksi instan
- **Pemrosesan Asinkron**: Laravel Queue (Database/Redis) untuk pengiriman faktur email dan webhook kurir
- **Suite Pengujian**: Pest PHP 4 (412+ pengujian otomatis)

---

## 📑 Struktur & Indeks Dokumen Perencanaan

Dokumentasi ini dibagi menjadi modul-modul tematik yang saling terintegrasi:

1. **[01. Analisis Peran & Matriks Fitur Lengkap](file:///Users/macbookair/Documents/website/ecommerce/ecommerce-bizmate/docs/planning/01-analisis-dan-fitur-role.md)**
   - Matriks hak akses untuk setiap peran (*Role Access Matrix*).
   - Fitur Storefront Customer & Guest (Katalog, 3D AR Model, Video Plyr, Smart Cart, Dual Checkout).
   - Fitur Armada Kurir Toko (Portal `/kurir`, rute GPS, kontak WhatsApp satu-klik, scanner resi, POD anti-fraud).
   - Fitur Admin Backoffice (Executive BI, manajemen varian & inventaris, dispatch kurir, promo, laporan laba rugi HPP).
   - Fitur Multi-Vendor / Seller (Toko `/toko/{slug}`, listing fee, seller ad wallet, iklan bersponsor).

2. **[02. Skema Alur Kerja Sistem (Workflows & Diagrams)](file:///Users/macbookair/Documents/website/ecommerce/ecommerce-bizmate/docs/planning/02-skema-alur-sistem.md)**
   - Diagram alir tingkat tinggi perjalanan pelanggan dari discovery hingga purna jual.
   - Alur autentikasi Customer, Kurir, Admin, dan Google/PadelGigs SSO.
   - Skema checkout detail: Guest vs Member terdaftar.
   - Mesin status pembayaran & siklus pesanan (*State Machine & Transition Matrix*).
   - Alur pengantaran kurir toko internal (*Internal Fleet Flow*).
   - Alur komplain, pengembalian barang (*returns*), dan pengembalian dana (*refunds*).
   - Alur mutasi inventaris & restok otomatis (*Stock Movements Cycle*).
   - Alur siaran real-time WebSockets via Laravel Reverb.

3. **[03. Arsitektur & Skema Database Lengkap](file:///Users/macbookair/Documents/website/ecommerce/ecommerce-bizmate/docs/planning/03-arsitektur-dan-skema-database.md)**
   - Diagram Relasi Entitas menyeluruh (*Complete ERD Mermaid*).
   - Kamus data lengkap untuk 40+ tabel database (tipe data, nullability, default, foreign keys, catatan bisnis).
   - Strategi pengindeksan performa (*composite indexes*) untuk beban kueri skala tinggi.

4. **[04. Spesifikasi Desain UI/UX & Tata Letak Layar](file:///Users/macbookair/Documents/website/ecommerce/ecommerce-bizmate/docs/planning/04-spesifikasi-ui-ux-dan-tampilan.md)**
   - Fondasi desain (*Design System Tokens*, tipografi, palet semantik status).
   - Wireframe & tata letak Storefront Pelanggan (Beranda, Detail Produk 3D, Checkout, Pelacakan Pesanan Terbuka).
   - Wireframe portal Kurir Toko berbasis *mobile-first* (Dashboard tugas, modal POD serah terima paket).
   - Wireframe Admin Backoffice padat data (Dashboard BI, Editor Produk AI, Verifikasi Pembayaran, Cetak Label Thermal).

5. **[05. Integrasi Pihak Ketiga, Keamanan & Roadmap Implementasi](file:///Users/macbookair/Documents/website/ecommerce/ecommerce-bizmate/docs/planning/05-integrasi-dan-roadmap-implementasi.md)**
   - Integrasi Payment Gateway Midtrans (Signature SHA512, pemetaan status webhook).
   - Integrasi Logistik Biteship & Komerce (Tarif real-time, pencarian area, webhook resi).
   - Asisten AI Produk (Gemini & OpenAgentic API).
   - Mitigasi keamanan konkurensi stok (*Pessimistic Locking* `lockForUpdate`).
   - Peta jalan pengembangan (*Development Roadmap Timeline* 5 Fase).

6. **[docs/planning.md (Master Plan Laravel: Full CRUD, Transactions & Trillion-Scale Audit)](file:///Users/macbookair/Documents/website/ecommerce/ecommerce-bizmate/docs/planning.md)**
   - Pedoman komprehensif audit database, optimasi query N+1, kebijakan `DB::table()` vs Eloquent, transaksi atomik, penanganan konkurensi stok, dan audit keamanan skala besar untuk moda **Non-FoodPick** maupun **FoodPick**.

---

## 🍔 Modul Baru: Arsitektur Bisnis FoodPick (`IS_FOODPICK=true`)

Ketika platform diaktifkan sebagai platform kuliner multi-resto (GoFood / ShopeeFood style), seluruh dokumentasi spesifikasinya dapat diakses pada sub-direktori **[docs/planning/foodpick/](file:///Users/macbookair/Documents/website/ecommerce/ecommerce-bizmate/docs/planning/foodpick/README.md)**:

1. **[foodpick/README.md (Ringkasan Bisnis & Matriks .env)](file:///Users/macbookair/Documents/website/ecommerce/ecommerce-bizmate/docs/planning/foodpick/README.md)**
2. **[foodpick/01-arsitektur-env-dan-fitur-resto.md (Fitur Peran & Adaptasi Wording)](file:///Users/macbookair/Documents/website/ecommerce/ecommerce-bizmate/docs/planning/foodpick/01-arsitektur-env-dan-fitur-resto.md)**
3. **[foodpick/02-alur-registrasi-resto-dan-customer.md (Pemisahan Registrasi Resto vs Pelanggan)](file:///Users/macbookair/Documents/website/ecommerce/ecommerce-bizmate/docs/planning/foodpick/02-alur-registrasi-resto-dan-customer.md)**
4. **[foodpick/03-alur-pemesanan-kds-dan-fulfillment.md (Pemesanan Makanan, KDS Dapur & Jam Pickup)](file:///Users/macbookair/Documents/website/ecommerce/ecommerce-bizmate/docs/planning/foodpick/03-alur-pemesanan-kds-dan-fulfillment.md)**
5. **[foodpick/04-skema-database-dan-master-data.md (ERD, Migrasi & Menu Modifiers)](file:///Users/macbookair/Documents/website/ecommerce/ecommerce-bizmate/docs/planning/foodpick/04-skema-database-dan-master-data.md)**
6. **[foodpick/05-spesifikasi-tampilan-ui-ux.md (Mockup Layar Resto, KDS & Checkout)](file:///Users/macbookair/Documents/website/ecommerce/ecommerce-bizmate/docs/planning/foodpick/05-spesifikasi-tampilan-ui-ux.md)**
   - Strategi pengujian otomatis dengan Pest PHP.

---

## 🚀 Panduan Ringkas Menjalankan Lingkungan Lokal

Untuk menjalankan seluruh lingkungan sistem (Backend, Frontend HMR, Antrean Email, dan WebSockets):

```bash
# 1. Jalankan semua layanan secara bersamaan
composer run dev

# Atau jalankan secara terpisah pada 4 terminal:
php artisan serve          # Port 8000 (Backend API & Pages)
bun run dev                # Port 5173 (Vite Frontend Hot-Reload)
php artisan queue:work     # Pemroses antrean email faktur & webhook
php artisan reverb:start   # Port 8080 (Server WebSocket Realtime)
```

### Akses Kredensial Bawaan Sistem:
- **Admin Portal** (`http://localhost:8000/admin`): `admin@bizmate.id` / `password`
- **Kurir Portal** (`http://localhost:8000/kurir`): `kurir@bizmate.id` / `password`
- **Customer Storefront** (`http://localhost:8000`): `customer@bizmate.id` / `password` (atau coba langsung fitur Guest Checkout tanpa login).

