# docs/planning.md — Master Plan Laravel: Full Database CRUD, Transactions, Trillion-Scale Performance & Security Audit

**Dokumen Rencana dan Pedoman Implementasi (Non-FoodPick & FoodPick)**  
*Dokumen ini merupakan pedoman arsitektur, audit kinerja, transaksi basis data, dan audit keamanan menyeluruh. Tidak ada perubahan destruktif pada source project atau database hanya karena dokumen ini dibuat.*

---

## Role & Mandat Arsitektur
Bertindak sebagai **Senior Laravel Engineer**, **Database Performance Engineer**, dan **Application Security Engineer**. Dokumen ini dirancang untuk mengaudit, memelihara, mengoptimalkan, dan mengamankan aplikasi Laravel **Bizmate** pada kedua moda operasional:
1. **Mode E-Commerce Ritel Standar (`IS_FOODPICK=false`)**: Katalog master produk, multi-varian (Warna x Ukuran x SKU), harga grosir (*tier pricing*), ekspedisi logistik (Biteship & Komerce), kurir toko internal, sistem retur barang, dan program membership loyalitas.
2. **Mode Kuliner & Multi-Resto FoodPick (`IS_FOODPICK=true`)**: Direktori resto/dapur mitra, kustomisasi menu dinamis per produk tanpa hardcoded (`/admin/products/{id}/modifiers`), *Kitchen Display System (KDS)*, armada pengantaran kurir resto (`/admin/courier-dispatch`), penegakan tegas **1 Resto per Cart dengan Auto-Reset Otomatis**, cetak struk thermal 58mm/80mm, serta logistik eksklusif: Ambil di Resto (Bungkus/Dine-In) & Kurir Resto internal berbasis GPS Haversine.

Dokumen ini bekerja berdasarkan kondisi kode nyata dan arsitektur yang sudah berjalan di repositori saat ini tanpa berasumsi project masih kosong atau perlu dibangun ulang (*zero rewrite*).

---

## 1. Tech Stack dan Batasan

Arsitektur aplikasi berjalan di atas ekosistem modern yang terpasang pada repositori:
- **Framework Utama**: Laravel 13 running on PHP 8.3
- **Database Engine**: MySQL 8.0+ / PostgreSQL 14+ (dengan SQLite untuk automated test isolation)
- **Cache, Queue & Session**: Redis / Database Queue Driver
- **Frontend SPA**: Svelte 5 (menggunakan *Runes*: `$state`, `$derived`, `$props`, `$effect`) & Inertia.js v3 (HTTP standalone request, layout props, optimistic updates, instant visits)
- **CSS Engine**: Tailwind CSS v4 dengan sistem palet dinamis
- **Route Typing**: `@laravel/vite-plugin-wayfinder` (TypeScript auto-generated controller actions & routes)
- **Real-time WebSockets**: Laravel Reverb (untuk live chat CS toko & sinkronisasi live status KDS dapur / transaksi)
- **Testing Suite**: Pest PHP 4 & PHPUnit 12 (412+ automated tests)
- **Web Server & Host**: Nginx pada Ubuntu Linux Server / macOS Development

> [!IMPORTANT]
> **Larangan Pergantian Stack**: Dilarang mengganti teknologi yang sudah berjalan dengan teknologi lain tanpa alasan teknis yang terukur dan persetujuan eksplisit.

---

## 2. Aturan Utama — Jangan Merusak Project (*Zero Breaking Changes*)

Wajib mematuhi 13 aturan mutlak berikut:
1. **Pertahankan Seluruh Perilaku Eksisting**: Pertahankan seluruh fitur, alur bisnis, validasi, hak akses (Spatie RBAC), route, API contract, dan tampilan UI/UX yang sudah ada.
2. **Tanpa Rewrite**: Jangan melakukan *rewrite* atau *rebuild* aplikasi secara menyeluruh.
3. **Tanpa Penghapusan Destruktif**: Jangan menghapus fitur, kolom, tabel, route, middleware, permission, atau data yang sudah digunakan.
4. **Struktur DB Non-Destruktif**: Jangan mengubah struktur database secara destruktif (semua penambahan kolom baru wajib berstatus `nullable` atau memiliki nilai `default`).
5. **Larangan Migrasi Otomatis**: Jangan menjalankan `migrate`, `db:seed`, `factory`, atau proses pengisian data dummy secara otomatis di lingkungan production.
6. **Tanpa Dummy Data Pengujian**: Jangan membuat data dummy buatan sendiri pada database aktif.
7. **Perlindungan Kredensial**: Jangan mengubah file `.env` atau menampilkan nilai secret, password, private key, token, maupun credential pada log atau output publik.
8. **Minimalisasi Dependensi**: Jangan menambahkan package pihak ketiga baru jika kebutuhan sudah dapat ditangani oleh Laravel Core atau package yang sudah terpasang.
9. **Anti Over-Engineering**: Hindari abstraksi berlebihan (misal: memaksakan Repository Pattern ke semua endpoint) dan optimasi prematur tanpa metrik baseline.
10. **Integritas Bisnis**: Jangan mengubah perilaku aplikasi hanya demi mengejar skor benchmark.
11. **Evaluasi Terarah Query Builder**: Pada endpoint dengan beban tinggi, evaluasi `DB::query()` / `DB::table()` untuk operasi read-only atau agregasi yang tidak membutuhkan hydration Model, namun jangan memaksa seluruh query menggunakannya.
12. **Konservasi Fitur Eloquent**: Jangan menghilangkan Eloquent scope, cast, accessor, policy, tenant/resto isolation, soft delete, atau observer secara tidak sengaja ketika mengubah jenis query.
13. **Proposal Perubahan Berisiko**: Setiap perubahan yang berpotensi berdampak pada data production atau infrastruktur server wajib dibuatkan dokumen usulan dan rencana mitigasi (*rollback plan*) terlebih dahulu.

---

## 3. Audit Awal Project & Klasifikasi Temuan

Sebelum melakukan perubahan kode, seluruh modul wajib diaudit dengan klasifikasi prioritas:
- **Critical (P0)**: Menyebabkan error sistem fatal, kebocoran data sensitif antar-resto/pelanggan (IDOR/BOLA), risiko SQL injection, atau kerusakan data finansial.
- **High (P1)**: Berdampak besar pada penurunan performa (misal: N+1 query pada listing menu/produk, transaksi tanpa timeout, table full scan pada tabel transaksi).
- **Medium (P2)**: Berpotensi menurunkan efisiensi memory PHP atau kejelasan arsitektur (misal: pemanggilan `get()->count()` alih-alih `count()` SQL).
- **Low (P3)**: Peningkatan minor kualitas kode, konsistensi formatting (Pint), atau dead code removal.

---

## 4. Audit dan Perbaikan Masalah N+1 Query

Pencarian masalah N+1 difokuskan pada listing katalog, keranjang belanja, dashboard admin, dan monitor dapur KDS.

### A. Pola Bermasalah pada Mode E-Commerce (Non-FoodPick)
```php
// SEBELUM (N+1: Query ke product_prices dan product_stocks dipanggil berulang di setiap loop)
$products = Product::latest()->take(20)->get();
foreach ($products as $product) {
    echo $product->price->price; // Menjalankan query baru per baris
    echo $product->stock->stock; // Menjalankan query baru per baris
}

// PERBAIKAN (Eager Loading Terarah)
$products = Product::with([
    'price:id,priceable_id,priceable_type,price,cost',
    'stock:id,stockable_id,stockable_type,stock',
    'brand:id,name,slug',
    'images' => fn($q) => $q->orderBy('order_column')->limit(1),
])->latest()->take(20)->get();
```

### B. Pola Bermasalah pada Mode Kuliner FoodPick
```php
// SEBELUM (N+1: Mengambil menu makanan beserta modifiers dan opsi topping di dalam loop)
$menus = Product::where('user_id', $restoId)->get();
foreach ($menus as $menu) {
    foreach ($menu->menuModifiers as $modifier) {
        echo $modifier->items->pluck('name'); // Memicu ratusan query berantai
    }
}

// PERBAIKAN (Nested Eager Loading Bersyarat)
$menus = Product::where('user_id', $restoId)
    ->with([
        'price:id,priceable_id,priceable_type,price',
        'menuModifiers.items' => fn($q) => $q->where('is_available', true),
    ])
    ->get();
```

---

## 5. Optimalisasi Kueri Database (Query Optimization)

1. **Eliminasi `SELECT *`**: Wajib mendefinisikan kolom eksplisit pada query listing (`select(['id', 'name', 'price', 'slug'])`).
2. **Dorong Agregasi ke Database**:
   - ❌ Jangan: `Product::all()->count()` atau `Product::all()->sum('price')`.
   - ✅ Gunakan: `Product::count()` atau `ProductPrice::sum('price')`.
3. **Gunakan Keberadaan Cepat**: Gunakan `exists()` alih-alih `count() > 0`.
4. **Pencegahan Wildcard Scan**: Hindari `LIKE '%keyword%'` pada tabel berukuran jutaan baris tanpa index Full-Text atau pg_trgm.

---

## 6. Strategi High Load, Large Dataset & Penghematan Memory

### 6.1 Matriks Kebutuhan Pekerjaan
| Kebutuhan Pekerjaan | Pilihan Awal | Hal yang Wajib Diverifikasi |
|---|---|---|
| **Detail Entitas Kompleks** | Eloquent dengan `select()` & `with()` | Policy, mutator, cast, soft delete |
| **Daftar Besar Read-Only** | `DB::table()` dengan kolom eksplisit | Filter otorisasi, pagination, sort deterministik |
| **Statistik & Total Omset** | SQL Aggregate (`COUNT`, `SUM`, `GROUP BY`) | Presisi angka dan penanganan data `NULL` |
| **Pemrosesan Batch Massal** | `chunkById()` / `lazyById()` pada PK stabil | Urutan ID, idempotency jika terjadi interupsi |
| **Ekspor Laporan Transaksi** | Queue Job Asinkron + Streaming CSV/Excel | Batas memory worker, timeout, link expiring |
| **Update Stok Massal** | Conditional Update / Batch Set | Pengurangan stok tidak boleh negatif (`>= $qty`) |

---

## 7. Kebijakan Penggunaan `DB::query()` / `DB::table()` pada Query Berat

### 7.1 Kapan Beralih ke Query Builder?
- Endpoint read-only dengan throughput tinggi (misal: auto-suggest search, public order tracking `/cek-pesanan`, KDS live poll fallback).
- Menghindari overhead pembuatan ribuan objek Model Eloquent (*hydration overhead*).

### 7.2 Contoh Implementasi pada Listing Pesanan Read-Only
```php
use Illuminate\Support\Facades\DB;

$orders = DB::table('transactions')
    ->select([
        'id',
        'transaction_number',
        'customer_name',
        'grand_total',
        'status',
        'payment_status',
        'created_at',
    ])
    ->where('user_id', $authorizedUserId)
    ->orderByDesc('id')
    ->simplePaginate(20);
```

---

## 8. Panduan Pengindeksan Basis Data (*Database Indexing*)

Indeks komposit dirancang berdasarkan pola akses *WHERE*, *JOIN*, dan *ORDER BY*:

1. **Tabel Transaksi E-Commerce & FoodPick**:
   - `INDEX (user_id, status, created_at)`: Optimal untuk tab riwayat transaksi pelanggan.
   - `INDEX (resto_id, kitchen_status, created_at)`: Optimal untuk monitor dapur KDS Resto.
   - `INDEX (courier_user_id, status)`: Optimal untuk dashboard tugas armada kurir internal.
   - `UNIQUE INDEX (transaction_number)`: Pencarian instan pelacakan pesanan publik.
2. **Tabel Katalog & Menu**:
   - `INDEX (category_id, is_promo, order_column)`: Optimal untuk browsing etalase.
   - `INDEX (user_id, is_active)`: Filter produk per toko/resto mitra.

---

## 9. Optimalisasi Eloquent Model

1. **Hindari Query di Accessor**: Dilarang meletakkan query database di dalam get-accessor (misal: `public function getTotalReviewsAttribute()`). Gunakan `withCount('reviews')`.
2. **Penggunaan `$appends` Terbatas**: Atribut `$appends` pada Model hanya digunakan bila benar-benar diperlukan di seluruh respons serialisasi JSON/Inertia.
3. **Pencegahan Lazy Loading di Development**:
   ```php
   // app/Providers/AppServiceProvider.php
   Model::preventLazyLoading(! app()->isProduction());
   ```

---

## 10. Arsitektur Controller, Service, dan Form Request

Pola arsitektur ringan menjaga controller tetap ramping:
```
HTTP Request 
  ──► Route + Throttle + Auth Middleware
  ──► FormRequest (Validasi Payload & Authorization)
  ──► Controller (Orkestrasi Alur)
  ──► Action / Service Class (Logika Bisnis & Transaksi Database)
  ──► DB::transaction / Eloquent
  ──► Inertia Props / API Resource (Hanya Field yang Dibutuhkan)
  ──► HTTP Response
```

---

## 11. Panduan Transaksi & Integritas Data (*Atomic Transactions*)

### 11.1 Pola Utama: `DB::transaction()` dengan Penanganan Error Bersih
```php
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

try {
    $transactionId = DB::transaction(function () use ($payload, $userId) {
        // 1. Buat record transaksi utama
        $trxId = DB::table('transactions')->insertGetId([
            'transaction_number' => 'TRX-' . date('Ymd') . '-' . strtoupper(Str::random(4)),
            'user_id'            => $userId,
            'resto_id'           => $payload['resto_id'] ?? null,
            'fulfillment_type'   => $payload['fulfillment_type'],
            'scheduled_time_slot'=> $payload['scheduled_time_slot'] ?? null,
            'grand_total'        => $payload['grand_total'],
            'status'             => 'menunggu_pembayaran',
            'payment_status'     => 'belum_dibayar',
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        // 2. Insert rincian item transaksi
        DB::table('transaction_items')->insert($payload['items']);

        return $trxId;
    }, attempts: 3);

    Log::info('transaction_create_succeeded', ['trx_id' => $transactionId]);
} catch (\Throwable $e) {
    Log::error('transaction_create_failed', [
        'exception_type' => get_class($e),
        'message'        => $e->getMessage(),
    ]);
    throw $e;
}
```

---

## 12. Penanganan Konkurensi & Anti-Race Condition Stok Makanan/Produk

Untuk mencegah *overselling* saat Flash Sale atau lonjakan pesanan jam makan siang (FoodPick):
```php
use App\Exceptions\InsufficientStockException;
use Illuminate\Support\Facades\DB;

DB::transaction(function () use ($productId, $qtyPurchased) {
    // Kunci baris stok produk di database level writer
    $stock = DB::table('product_stocks')
        ->where('product_id', $productId)
        ->lockForUpdate()
        ->first();

    if (! $stock || $stock->stock < $qtyPurchased) {
        throw new InsufficientStockException('Maaf, porsi menu/stok barang tidak mencukupi.');
    }

    DB::table('product_stocks')
        ->where('product_id', $productId)
        ->decrement('stock', $qtyPurchased);
});
```

---

## 13. Khusus FoodPick: Penegakan Aturan 1 Resto & Auto-Reset Keranjang

Dalam mode kuliner (`IS_FOODPICK=true`), satu transaksi hanya boleh memproses pesanan dari **1 Resto**:

```php
namespace App\Actions\FoodPick;

use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;

class AddToCartAction
{
    public function execute(Request $request): array
    {
        $productId = $request->input('product_id');
        $quantity  = $request->input('quantity', 1);
        $product   = Product::findOrFail($productId);
        $newRestoId= $product->user_id;

        $userId    = auth()->id();
        $sessionId = session()->getId();

        // Cek apakah ada menu dari resto lain di keranjang aktif
        $existingCart = CartItem::query()
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->when(!$userId, fn($q) => $q->where('session_id', $sessionId))
            ->with('product')
            ->get();

        $cartWasReset = false;

        if ($existingCart->isNotEmpty() && $existingCart->first()->product->user_id !== $newRestoId) {
            // EKSEKUSI RESET OTOMATIS: Kosongkan seluruh item resto lama
            CartItem::query()
                ->when($userId, fn($q) => $q->where('user_id', $userId))
                ->when(!$userId, fn($q) => $q->where('session_id', $sessionId))
                ->delete();

            $cartWasReset = true;
        }

        // Simpan menu dari resto yang baru
        $cartItem = CartItem::create([
            'user_id'    => $userId,
            'session_id' => $userId ? null : $sessionId,
            'product_id' => $productId,
            'quantity'   => $quantity,
            'notes'      => json_encode($request->input('modifiers', [])),
        ]);

        return [
            'success'        => true,
            'cart_was_reset' => $cartWasReset,
            'cart_item'      => $cartItem,
        ];
    }
}
```

---

## 14. Pemrosesan Asinkron: Queue & Background Jobs

Pindahkan seluruh beban I/O eksternal keluar dari siklus HTTP request pengguna:
1. **Email Konfirmasi & Faktur PDF**: Implementasikan `ShouldQueue` pada Mailable.
2. **Pola After-Commit**: Pastikan job hanya di-dispatch setelah transaksi database benar-benar berhasil di-commit:
   ```php
   SendOrderConfirmationEmail::dispatch($orderId)->afterCommit();
   ```
3. **Idempotency Webhook**: Setiap webhook dari Midtrans atau Biteship wajib memeriksa `order_id` dan `status` untuk mencegah eksekusi ganda jika notifikasi dikirim berulang (*network retry*).

---

## 15. Frontend Optimization: Svelte 5 (Runes), Inertia v3 & Vite

1. **Manajemen State Reaktif Svelte 5**: Gunakan `$state` untuk data lokal dan `$derived` untuk kalkulasi subtotal/ongkir otomatis.
2. **Minimalisasi Props Inertia**: Hanya kirim data yang ditampilkan di halaman. Hindari memuat seluruh relasi yang tidak dirender.
3. **Paginasi Server-Side**: Jangan pernah mengirimkan ribuan baris data transaksi ke klien browser. Gunakan paginasi standar 15-25 item per halaman.

---

## 16. Audit Keamanan & Perlindungan Data Sensitif (*Security Audit Playbook*)

1. **Perlindungan IDOR / BOLA**: Jangan mengandalkan `find($id)` tanpa memverifikasi kepemilikan data:
   ```php
   // AMAN: Terisolasi berdasarkan user ID yang login
   $order = Transaction::where('user_id', auth()->id())->findOrFail($id);
   ```
2. **Pencegahan SQL Injection**: Seluruh input dinamis wajib menggunakan binding parameter (`?` atau named parameter). Dilarang menyambungkan string variabel langsung ke `whereRaw()` atau `orderByRaw()`.
3. **Masking Data Sensitif**: Nomor rekening bank penjual/resto, nomor telepon pelanggan pada log publik, dan kredensial API gateway wajib disamarkan (*redacted*).
4. **Rate Limiting**: Lindungi endpoint publik `/cek-pesanan`, `/checkout`, dan `/login` dengan middleware throttling (`throttle:6,1`).

---

## 17. Desain Skalabilitas Data Ekstrem (Hingga Triliunan Baris)

Pada skala data ekstrem, tidak ada satu kueri ajaib yang membuat triliunan baris menjadi ringan secara instan. Strategi bertingkat yang wajib diterapkan:

```
┌────────────────────────────────────────────────────────┐
│ TRILIUNAN BARIS: Partisi Waktu / Tenant + Cold Storage │
├────────────────────────────────────────────────────────┤
│ RATUSAN JUTA BARIS: Materialized Summary + Read Replica│
├────────────────────────────────────────────────────────┤
│ PULUHAN JUTA BARIS: Keyset Pagination + Indeks Komposit│
├────────────────────────────────────────────────────────┤
│ JUTAAN BARIS: Eager Loading Terarah + DB Agregasi      │
└────────────────────────────────────────────────────────┘
```

1. **Partition Pruning**: Partisi tabel transaksi berdasarkan rentang waktu bulanan/tahunan (`PARTITION BY RANGE (YEAR(created_at))`). Kueri yang menyertakan rentang tanggal hanya akan memindai partisi yang relevan.
2. **Keyset / Seek Pagination**: Hindari `OFFSET 1000000`. Gunakan cursor pagination berbasis index unik:
   ```php
   $orders = DB::table('transactions')
       ->where('id', '<', $lastSeenId)
       ->orderByDesc('id')
       ->limit(25)
       ->get();
   ```
3. **Tabel Ringkasan Agregat**: Dashboard omset harian membaca dari tabel pra-kalkulasi `dashboard_daily_summaries` yang diperbarui secara terjadwal/inkremental, bukan melakukan kalkulasi penuh `SUM()` pada jutaan baris transaksi setiap kali dashboard dibuka.

---

## 18. Siklus Pengujian Regresi (*Testing & Verification*)

Setiap perbaikan kode wajib diverifikasi melalui rangkaian uji otomatis tanpa membuat data dummy baru di database production:
```bash
# Menjalankan pengujian fungsional terarah dengan Pest PHP
php artisan test --compact --filter=TransactionTest

# Menjalankan analisis gaya kode (Pint)
vendor/bin/pint --dirty --format agent
```

---

## 19. Checklist Final Implementasi (*Execution Checklist*)

- [ ] Seluruh endpoint CRUD memilih antara Eloquent atau Query Builder secara terukur berdasarkan kebutuhan objek/hydration.
- [ ] Semua penulisan data mutasi melibatkan `DB::transaction()` dengan penanganan rollback otomatis saat terjadi exception.
- [ ] Pola pengurangan stok menerapkan proteksi konkurensi (`lockForUpdate` atau conditional decrement).
- [ ] Mode FoodPick (`IS_FOODPICK=true`) mematuhi aturan ketat **1 Resto per Cart dengan Reset Otomatis**.
- [ ] Seluruh query publik terproteksi dari SQL injection via parameter binding dan allowlist kolom.
- [ ] Tidak ada berkas `.env`, token, atau password yang terekspos pada log atau respons API.
- [ ] Tidak ada proses migrasi destruktif atau pengisian seeder otomatis di lingkungan production.
- [ ] Seluruh fitur, tampilan visual, alur checkout tanpa login, dan portal kurir tetap berjalan normal tanpa regresi.

