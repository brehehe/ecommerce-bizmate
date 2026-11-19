# 🔄 Skema Alur Kerja Sistem Lengkap (End-to-End System Workflow)

Dokumen ini memetakan seluruh skema alur proses bisnis (*business workflow*), diagram alir (*flowcharts*), diagram sekuens (*sequence diagrams*), serta mesin status (*state machines*) pada sistem e-commerce **Bizmate**.

---

## 1. Peta Alur Arsitektur Tingkat Tinggi (*High-Level Workflow*)

```mermaid
flowchart TD
    subgraph CUSTOMER_JOURNEY [Perjalanan Pelanggan]
        Browse[1. Jelajah Katalog & Detail Produk 3D/Video] --> AddCart[2. Pilih Varian & Tambah ke Keranjang]
        AddCart --> Cart[3. Smart Cart: Checklist Item & Input Voucher]
        Cart --> CheckoutDecision{Mode Pembelian?}
        CheckoutDecision -- Member --> MemberCheckout[4a. Checkout Member: Alamat Tersimpan & Poin VIP]
        CheckoutDecision -- Guest --> GuestCheckout[4b. Guest Checkout: Input Nama, HP, Email]
        MemberCheckout --> ShipSelect[5. Pilih Metode Pengiriman]
        GuestCheckout --> ShipSelect
        ShipSelect --> PaySelect[6. Pilih Metode Pembayaran]
        PaySelect --> CreateOrder[7. Eksekusi Buat Pesanan]
    end

    subgraph PAYMENT_ENGINE [Mesin Pembayaran]
        CreateOrder --> PayType{Tipe Pembayaran?}
        PayType -- Payment Gateway --> MidtransSnap[Midtrans VA / QRIS / E-Wallet]
        PayType -- Transfer Manual --> UploadProof[Upload Bukti Transfer]
        PayType -- COD --> WaitDelivery[Tunggu Kurir Mengantar]
        
        MidtransSnap --> MidtransWebhook[Webhook Notifikasi Midtrans]
        MidtransWebhook --> VerifyPayment[Validasi Lunas Otomatis]
        
        UploadProof --> AdminVerify{Admin Verifikasi?}
        AdminVerify -- Setuju --> VerifyPayment
        AdminVerify -- Tolak --> RejectNotice[Notifikasi Bukti Ditolak]
    end

    subgraph FULFILLMENT_ENGINE [Pemenuhan & Logistik]
        VerifyPayment --> ReserveStock[Potong Stok & Catat Mutasi Inventaris]
        ReserveStock --> QueueInvoice[Queue Job: Kirim Faktur PDF ke Email]
        QueueInvoice --> AdminPack[Admin Kemas Paket & Cetak Label Thermal]
        
        AdminPack --> CourierType{Tipe Pengiriman?}
        CourierType -- Kurir Toko Internal --> AssignInternal[Tugaskan ke Kurir Internal]
        CourierType -- Ekspedisi Biteship/Komerce --> RequestPickup[Request Pickup API Ekspedisi]
        CourierType -- Ambil di Toko --> SelfPickupReady[Notifikasi Siap Diambil di Toko]
        
        AssignInternal --> KurirApp[Kurir Buka Portal /kurir via HP]
        KurirApp --> KurirDeliver[Kurir Antar: GPS Navigasi & WA Kontak]
        KurirDeliver --> KurirPOD[Upload Foto Bukti Serah Terima POD]
        
        RequestPickup --> EkspedisiTransit[Ekspedisi Kirim & Sinkron Resi Webhook]
        EkspedisiTransit --> EkspedisiDelivered[Status Terkirim Otomatis]
        
        KurirPOD --> OrderDone[Pesanan Selesai]
        EkspedisiDelivered --> OrderDone
        SelfPickupReady --> OrderDone
    end

    subgraph POST_PURCHASE [Purna Jual & Loyalty]
        OrderDone --> RewardPoints[Tambah Koin Loyalty & Update Level Member]
        OrderDone --> ReviewProduct[Pelanggan Isi Ulasan Berbintang]
        OrderDone --> DisputeCheck{Ada Komplain?}
        DisputeCheck -- Ya --> ReturnRefundFlow[Alur Pengajuan Retur & Refund]
    end
```

---

## 2. Alur Autentikasi & Manajemen Sesi

Sistem mendukung 4 gerbang masuk otentikasi yang aman dan terisolasi:

```mermaid
sequenceDiagram
    autonumber
    actor User as Pengguna
    participant Web as Web Browser / Client
    participant Auth as Auth Controller
    participant OAuth as Socialite / Google / PadelGigs
    participant DB as Database (Users & Roles)

    alt Login Customer Biasa
        User->>Web: Masukkan Email & Password
        Web->>Auth: POST /login
        Auth->>DB: Cek Hash Bcrypt Password
        DB-->>Auth: Kredensial Valid
        Auth->>Web: Buat Sesi Web Guard (Role: Customer)
    else Social Login Google / SSO
        User->>Web: Klik 'Login dengan Google / PadelGigs'
        Web->>OAuth: Redirect ke OAuth Provider
        OAuth-->>Web: Callback dengan Auth Code & Token
        Web->>Auth: GET /auth/{provider}/callback
        Auth->>DB: Temukan atau Buat User Baru (Auto-Register)
        Auth->>Web: Set Sesi Login Sukses
    else Login Kurir Toko
        User->>Web: Akses /kurir/login
        Web->>Auth: POST /kurir/login
        Auth->>DB: Validasi User memiliki Spatie Role 'Kurir'
        DB-->>Auth: Role Sesuai
        Auth->>Web: Buka Portal Khusus Kurir (/kurir/dashboard)
    else Login Admin Backoffice
        User->>Web: Akses /login atau /admin
        Web->>Auth: POST /login
        Auth->>DB: Validasi Spatie Role 'Administrator' / 'Staff'
        DB-->>Auth: Role Sesuai
        Auth->>Web: Buka Panel Backoffice (/admin/dashboard)
    end
```

---

## 3. Alur Checkout Detail: Tamu (*Guest*) vs Member Terdaftar

```mermaid
flowchart TD
    StartCheck([Klik Tombol 'Checkout' di Keranjang]) --> CheckConfig{is_checkout_without_login == true?}
    
    CheckConfig -- Tidak Aktif --> RequireLogin{User Sudah Login?}
    RequireLogin -- Belum --> ForceLogin[Arahkan ke Halaman Login / Daftar]
    RequireLogin -- Sudah --> EnterMemberCheckout[Buka Checkout Member]
    
    CheckConfig -- Aktif --> AuthStatus{User Sedang Login?}
    AuthStatus -- Sudah --> EnterMemberCheckout
    AuthStatus -- Belum --> EnterGuestCheckout[Buka Formulir Guest Checkout]
    
    subgraph GUEST_DATA [Formulir Data Tamu]
        EnterGuestCheckout --> InpName[Input: Nama Lengkap]
        InpName --> InpPhone[Input: No HP / WhatsApp Minimal 8 Digit]
        InpPhone --> InpEmail[Input: Email Valid untuk Pengiriman Faktur]
        InpEmail --> InpManualAddress[Input: Alamat Lengkap & Kode Pos]
    end
    
    subgraph MEMBER_DATA [Data Member Terdaftar]
        EnterMemberCheckout --> PickSavedAddress[Pilih Alamat dari Buku Alamat]
        PickSavedAddress --> ApplyCoins[Gunakan Saldo Poin Koin Loyalty]
        ApplyCoins --> ApplyMemberDisc[Diskon Otomatis Tingkat Membership]
    end
    
    InpManualAddress --> ShippingEngine
    ApplyMemberDisc --> ShippingEngine
    
    subgraph ShippingEngine [Kalkulasi Biaya Pengiriman]
        DetermineType{Pilihan Kurir?}
        DetermineType -- Ekspedisi Biteship/Komerce --> APIBiteship[Hitung Ongkir via API: Berat x Kota Tujuan]
        DetermineType -- Kurir Toko Internal --> CalcHaversine[Hitung Jarak GPS: Toko ke Rumah via Haversine]
        DetermineType -- Ambil di Toko --> ZeroShip[Ongkir Rp 0 + Pilih Jam Pickup]
        DetermineType -- Produk Digital --> ZeroShipDigital[Ongkir Rp 0 + Akses Download]
    end
    
    APIBiteship --> TotalSummary[Ringkasan Total Belanja & Kupon]
    CalcHaversine --> TotalSummary
    ZeroShip --> TotalSummary
    ZeroShipDigital --> TotalSummary
    
    TotalSummary --> SubmitOrder[Klik 'Bayar Sekarang']
    SubmitOrder --> SaveDB[Simpan Transaksi di Database]
    SaveDB --> TriggerQueue[Queue Job: Kirim Konfirmasi & Resi via Email]
```

---

## 4. Alur Lifecycle Pembayaran & State Machine

Status pesanan dikelola melalui kombinasi dua kolom status pada tabel `transactions`: **`status`** (alur fisik pesanan) dan **`payment_status`** (alur finansial pembayaran).

```mermaid
stateDiagram-v2
    [*] --> menunggu_pembayaran: Transaksi Dibuat
    
    state Pembayaran {
        menunggu_pembayaran --> belum_dibayar: Awal
        belum_dibayar --> lunas: Midtrans Webhook (Settlement)
        belum_dibayar --> menunggu_konfirmasi: Customer Upload Struk Manual
        
        menunggu_konfirmasi --> lunas: Admin Approve Bukti
        menunggu_konfirmasi --> belum_dibayar: Admin Tolak Bukti (Minta Upload Ulang)
        
        belum_dibayar --> kedaluwarsa: Melewati Batas Waktu Bayar (Auto-Cancel)
        belum_dibayar --> gagal: Pembayaran Gagal di Gateway
    }
    
    menunggu_pembayaran --> dibayar: payment_status == lunas
    menunggu_pembayaran --> batal: payment_status == kedaluwarsa ATAU Dibatalkan
    
    dibayar --> diproses: Admin / Sistem Memverifikasi Pesanan
    diproses --> dikirim: Diserahkan ke Kurir / Input Resi
    
    dikirim --> selesai: Diterima Pembeli (POD / Auto H+2)
    dikirim --> komplain: Pembeli Mengajukan Retur / Refund
    
    komplain --> selesai: Retur Selesai / Refund Cair
    komplain --> dikirim: Komplain Ditolak Admin
    
    selesai --> [*]
    batal --> [*]
```

### Tabel Matriks Perubahan Status (*State Transition Table*)

| Dari Status | Ke Status | Pemicu (*Trigger*) | Aksi Sistem Otomatis |
|---|---|---|---|
| `menunggu_pembayaran` | `dibayar` | Webhook Midtrans `settlement` ATAU Admin setujui bukti manual | Kirim email nota pembayaran, potong stok mutasi, kirim event broadcast Reverb |
| `menunggu_pembayaran` | `batal` | Scheduled command cron mengecek pesanan expired (> 24 jam) | Kembalikan kuota voucher, kembalikan koin yang terpakai, kembalikan stok |
| `dibayar` | `diproses` | Admin menekan tombol "Proses Pesanan" | Status berpindah ke antrean gudang untuk dicetak label packing slip-nya |
| `diproses` | `dikirim` | Admin menugaskan kurir internal ATAU menginput nomor resi ekspedisi | Kirim email pemberitahuan resi kurir beserta link pelacakan paket live |
| `dikirim` | `selesai` | Kurir upload foto POD berhasil ATAU pembeli klik "Pesanan Diterima" | Cairkan koin cashback reward, akumulasi total belanja untuk naik level VIP |
| `dikirim` | `komplain` | Pembeli mengajukan komplain barang rusak / tidak lengkap | Tahan pelepasan dana, buat tiket di menu Retur/Refund admin |

---

## 5. Alur Penugasan & Pengantaran Kurir Toko (*Internal Fleet Flow*)

```mermaid
sequenceDiagram
    autonumber
    actor Admin as Admin Gudang
    actor Kurir as Kurir Toko
    actor Cust as Pembeli
    participant App as Sistem Bizmate (/kurir)
    participant Maps as Google Maps / Waze API
    participant WA as WhatsApp Client

    Admin->>App: Pilih Pesanan -> Tugaskan ke Kurir X
    App-->>Kurir: Notifikasi Pesanan Baru di Portal Kurir
    Kurir->>App: Buka /kurir/dashboard di Ponsel
    Kurir->>App: Scan Barcode pada Label Paket Fisik
    App-->>Kurir: Buka Rincian Tugas Pengantaran (Status: Menunggu Antar)
    Kurir->>App: Tekan 'Mulai Kirim' (Status: Sedang Dikirim)
    
    Kurir->>App: Tekan Ikon GPS Peta
    App->>Maps: Buka Koordinat GPS Alamat Penerima (Lat, Long)
    Maps-->>Kurir: Panduan Rute Jalan Menuju Rumah Pembeli
    
    Kurir->>App: Tekan Ikon WhatsApp
    App->>WA: Buka Chat Pembeli dengan Templat Konfirmasi Paket
    WA-->>Cust: Menerima Pesan Pemberitahuan Kurir Mendekat
    
    Kurir->>Cust: Tiba di Alamat & Serah Terima Paket
    
    alt Pembayaran Non-COD
        Kurir->>App: Ambil Foto Serah Terima Paket (Kamera HP)
        Kurir->>App: Input Nama Penerima & Hubungan (Ybs / Keluarga)
        Kurir->>App: Tekan 'Selesaikan Pengiriman'
    else Pembayaran COD (Tunai)
        Kurir->>Cust: Tagih Pembayaran Tunai Sebesar Grand Total
        Cust->>Kurir: Menyerahkan Uang Tunai
        Kurir->>App: Centang 'Uang Tunai Telah Diterima'
        Kurir->>App: Ambil Foto Paket & Input Nama Penerima
        Kurir->>App: Tekan 'Selesaikan Pengiriman'
    end
    
    App->>App: Simpan Foto POD, Timestamp, & Geotag
    App->>App: Update Status Pesanan -> 'selesai' & Pembayaran -> 'lunas'
    App-->>Cust: Notifikasi Paket Berhasil Diterima via Email & WA
```

---

## 6. Alur Retur Barang & Pengembalian Dana (*Return & Refund Flow*)

```mermaid
flowchart TD
    Delivered([Paket Berstatus 'Dikirim' / 'Selesai']) --> IssueFound{Barang Rusak / Salah Kirim?}
    IssueFound -- Ya --> OpenComplaint[Buka Halaman /returns atau /refunds]
    
    OpenComplaint --> FormComplaint[Isi Alasan: Rusak, Cacat, Tidak Sesuai]
    FormComplaint --> UploadEvidence[Upload Bukti Foto & Video Unboxing Wajib]
    UploadEvidence --> ChooseSolution{Solusi yang Diminta?}
    
    ChooseSolution -- Ganti Barang Baru --> ReturnGoods[Alur Retur Barang]
    ChooseSolution -- Minta Uang Kembali --> RefundMoney[Alur Pengembalian Dana]
    
    ReturnGoods --> AdminReviewReturn{Admin Mengkaji Bukti}
    RefundMoney --> AdminReviewRefund{Admin Mengkaji Bukti}
    
    AdminReviewReturn -- Bukti Tidak Valid --> RejectComplaint[Tolak Komplain dengan Alasan Tertulis]
    AdminReviewRefund -- Bukti Tidak Valid --> RejectComplaint
    
    AdminReviewReturn -- Bukti Valid --> ApproveReturn[Setujui Retur: Berikan Alamat Gudang Toko]
    ApproveReturn --> CustomerShip[Pembeli Kirim Barang Balik & Input Resi]
    CustomerShip --> WarehouseReceive[Gudang Menerima & Memeriksa Fisik Barang]
    WarehouseReceive --> ShipReplacement[Kirimkan Barang Pengganti ke Pembeli]
    ShipReplacement --> ReturnDone([Komplain Retur Selesai])
    
    AdminReviewRefund -- Bukti Valid --> ApproveRefund[Setujui Pengembalian Dana]
    ApproveRefund --> PayoutGateway[Transfer Dana via Rekening Bank / Flip / Midtrans]
    PayoutGateway --> RefundDone([Pengembalian Dana Berhasil])
```

---

## 7. Alur Pengurangan & Pengembalian Stok (*Stock Movements Cycle*)

Stok produk dijaga dengan akurasi mutlak untuk mencegah situasi *overselling* (terjual melebihi stok yang ada):

```mermaid
flowchart LR
    A[Stok Awal di Gudang] -->|Checkout Berhasil Dibuat| B[Status: Reserved / Pengurangan Sementara]
    B -->|Pembayaran Lunas| C[Status: Sold / Penjualan Final]
    B -->|Waktu Bayar Habis / Dibatalkan| D[Auto-Restock / Stok Dikembalikan]
    C -->|Retur Barang Disetujui Masuk Gudang| E[Restock Manual via Audit Opname]
    
    subgraph AUDIT_TRAIL [Tabel stock_movements]
        direction TB
        Log1[Catat ID User / Sistem]
        Log2[Catat Tipe: sale, cancel, restock, adjustment]
        Log3[Catat Jumlah Perubahan: + / -]
        Log4[Catat Stok Akhir Setelah Berubah]
    end
    
    B -.-> AUDIT_TRAIL
    C -.-> AUDIT_TRAIL
    D -.-> AUDIT_TRAIL
    E -.-> AUDIT_TRAIL
```

---

## 8. Alur Siaran Real-Time (*WebSockets Event Flow*)

Menggunakan **Laravel Reverb** untuk pengalaman reaktif tanpa *polling* berkala:

```mermaid
sequenceDiagram
    autonumber
    actor Customer as Browser Pelanggan
    participant Reverb as Laravel Reverb WebSocket Server
    participant Backend as Laravel Backend (Events & Channels)
    actor Admin as Browser Admin / CS Toko

    Note over Customer,Admin: Skenario 1: Live Chat Pelanggan & CS
    Customer->>Backend: Kirim Pesan Chat Baru
    Backend->>Backend: Simpan di chat_messages
    Backend->>Reverb: Broadcast Event 'ChatMessageSent' ke Channel 'chat.{id}'
    Reverb-->>Admin: Pesan Langsung Muncul di Layar CS secara Instan

    Note over Customer,Admin: Skenario 2: Update Status Transaksi Realtime
    Admin->>Backend: Update Status Pesanan -> 'dikirim'
    Backend->>Reverb: Broadcast Event 'TransactionStatusUpdated'
    Reverb-->>Customer: Badge Status di Halaman /cek-pesanan Berubah Live tanpa Refresh
```

