<?php

namespace App\Models;

use App\Mail\OrderStatusChanged;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class Transaction extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'transaction_number',
        'user_id',
        'customer_address_id',
        'payment_method_id',
        'courier_id',
        'status',
        'subtotal',
        'discount_amount',
        'shipping_fee',
        'shipping_discount',
        'admin_fee',
        'application_fee',
        'grand_total',
        'shipping_courier',
        'shipping_service',
        'shipping_etd',
        'voucher_code',
        'voucher_discount_type',
        'voucher_discount_value',
        'notes',
        'cancel_reason',
        'cancelled_at',
        'tracking_number',
        'booking_code',
        'courier_name',
        'return_status',
        'is_replacement_transaction',
        'original_transaction_id',
        'coins_redeemed',
        'coins_value',
        'coins_earned',
        'payment_expires_at',
        'auto_complete_at',
        'is_extended',
        'delivery_arrived_at',
        'delivery_photos',
        'courier_user_id',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'shipping_discount' => 'decimal:2',
        'admin_fee' => 'decimal:2',
        'application_fee' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'voucher_discount_value' => 'decimal:2',
        'cancelled_at' => 'datetime',
        'is_replacement_transaction' => 'boolean',
        'coins_redeemed' => 'integer',
        'coins_value' => 'integer',
        'coins_earned' => 'integer',
        'payment_expires_at' => 'datetime',
        'auto_complete_at' => 'datetime',
        'is_extended' => 'boolean',
        'delivery_arrived_at' => 'datetime',
        'delivery_photos' => 'array',
    ];

    protected $appends = [
        'customer_name',
        'customer_email',
        'customer_phone',
        'items_summary',
        'grand_total_formatted',
        'subtotal_formatted',
        'discount_amount_formatted',
        'shipping_cost_formatted',
        'created_at_formatted',
    ];

    /**
     * Status labels in Indonesian.
     *
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            'belum_bayar' => 'Belum Bayar',
            'menunggu' => 'Menunggu Konfirmasi',
            'diproses' => 'Diproses',
            'dikemas' => 'Dikemas',
            'out_for_pickup' => 'Out for Pickup',
            'dikirim' => 'Dikirim',
            'selesai' => 'Selesai',
            'batal' => 'Batal',
        ];
    }

    /**
     * Return status labels in Indonesian.
     *
     * @return array<string, string>
     */
    public static function returnStatusLabels(): array
    {
        return [
            'menunggu_review' => 'Menunggu Review',
            'disetujui' => 'Retur Disetujui',
            'ditolak' => 'Retur Ditolak',
            'barang_dikirim_customer' => 'Barang Dikirim',
            'barang_diterima_toko' => 'Diterima Toko',
            'refund_diproses' => 'Refund Diproses',
            'selesai' => 'Retur Selesai',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Transaction $transaction) {
            if ($transaction->status === 'belum_bayar') {
                $hours = (int) (Setting::where('key', 'payment_expiry_hours')->value('value') ?? 24);
                $transaction->payment_expires_at = now()->addHours($hours);
            }
        });

        static::saving(function (Transaction $transaction) {
            if ($transaction->shipping_courier === 'store_courier' && in_array($transaction->status, ['dikemas', 'out_for_pickup', 'dikirim'])) {
                if (empty($transaction->booking_code)) {
                    $transaction->booking_code = 'ST-' . $transaction->transaction_number;
                }
                if (empty($transaction->tracking_number)) {
                    $transaction->tracking_number = 'RSI-' . str_replace('TRX-', '', $transaction->transaction_number) . '-' . now()->format('Ymd');
                }
            }
        });

        static::updating(function (Transaction $transaction) {
            if ($transaction->isDirty('status') && $transaction->status === 'dikirim') {
                $days = (int) (Setting::where('key', 'auto_complete_days')->value('value') ?? 7);
                $transaction->auto_complete_at = now()->addDays($days);
            }
        });

        static::updated(function (Transaction $transaction) {
            $statusChanged = $transaction->isDirty('status');
            $trackingChanged = $transaction->isDirty('tracking_number');
            $bookingCodeChanged = $transaction->isDirty('booking_code');

            if ($statusChanged || $trackingChanged || $bookingCodeChanged) {
                // 1. Booking Code Changed (Store Courier auto log)
                if ($bookingCodeChanged && ! empty($transaction->booking_code) && $transaction->shipping_courier === 'store_courier') {
                    $transaction->statusHistories()->create([
                        'status' => $transaction->status,
                        'description' => 'Kode Booking Sudah dibuat',
                        'created_by' => auth()->id(),
                    ]);
                }

                // 2. Tracking Number Changed (Store Courier auto log)
                if ($trackingChanged && ! empty($transaction->tracking_number) && $transaction->shipping_courier === 'store_courier') {
                    $transaction->statusHistories()->create([
                        'status' => $transaction->status,
                        'description' => 'Resi Telah dibuat',
                        'created_by' => auth()->id(),
                    ]);
                }

                if ($statusChanged) {
                    // Award coins when status is updated to completed ('selesai')
                    if ($transaction->status === 'selesai' && $transaction->coins_earned > 0) {
                        $alreadyAwarded = CoinHistory::where('transaction_id', $transaction->id)
                            ->where('type', 'earn')
                            ->exists();
                        if (! $alreadyAwarded) {
                            $user = $transaction->user;
                            if ($user) {
                                $user->increment('coins_balance', $transaction->coins_earned);
                                CoinHistory::create([
                                    'user_id' => $user->id,
                                    'transaction_id' => $transaction->id,
                                    'amount' => $transaction->coins_earned,
                                    'type' => 'earn',
                                    'description' => 'Mendapatkan Poin dari transaksi #' . $transaction->transaction_number,
                                ]);
                            }
                        }
                    }

                    // Refund coins when status is updated to cancelled ('batal')
                    if ($transaction->status === 'batal' && $transaction->coins_redeemed > 0) {
                        $alreadyRefunded = CoinHistory::where('transaction_id', $transaction->id)
                            ->where('type', 'refund')
                            ->exists();
                        if (! $alreadyRefunded) {
                            $user = $transaction->user;
                            if ($user) {
                                $user->increment('coins_balance', $transaction->coins_redeemed);
                                CoinHistory::create([
                                    'user_id' => $user->id,
                                    'transaction_id' => $transaction->id,
                                    'amount' => $transaction->coins_redeemed,
                                    'type' => 'refund',
                                    'description' => 'Pengembalian Poin dari pembatalan transaksi #' . $transaction->transaction_number,
                                ]);
                            }
                        }
                    }

                    $description = match ($transaction->status) {
                        'belum_bayar' => 'Menunggu pembayaran.',
                        'menunggu' => 'Pembayaran sedang dikonfirmasi / Menunggu konfirmasi.',
                        'diproses' => 'Pesanan sedang diproses.',
                        'dikemas' => 'Pesanan sedang dikemas.',
                        'out_for_pickup' => $transaction->shipping_courier === 'store_courier' ? 'Sudah dipick' : 'Kurir sedang dalam perjalanan untuk menjemput paket (Out for Pickup).',
                        'dikirim' => $transaction->shipping_courier === 'store_courier' ? 'Dalam Pengantaran' : 'Pesanan telah dikirim.',
                        'selesai' => 'Pesanan telah diterima. Transaksi selesai.',
                        'batal' => 'Pesanan dibatalkan.' . ($transaction->cancel_reason ? ' Alasan: ' . $transaction->cancel_reason : ''),
                        default => 'Status pesanan diperbarui menjadi: ' . $transaction->status,
                    };

                    $transaction->statusHistories()->create([
                        'status' => $transaction->status,
                        'description' => $description,
                        'created_by' => auth()->id(),
                    ]);

                    // Create database notification for the customer
                    try {
                        $notifMessage = 'Pesanan Anda #' . $transaction->transaction_number . ' kini ' . match ($transaction->status) {
                            'belum_bayar' => 'menunggu pembayaran.',
                            'menunggu' => 'menunggu konfirmasi pembayaran.',
                            'diproses' => 'sedang diproses.',
                            'dikemas' => 'sedang dikemas.',
                            'out_for_pickup' => 'sedang dalam proses penjemputan oleh kurir (Out for Pickup).',
                            'dikirim' => 'telah dikirim.',
                            'selesai' => 'selesai / telah diterima.',
                            'batal' => 'dibatalkan.',
                            default => 'diperbarui menjadi ' . $transaction->status,
                        };

                        if (in_array($transaction->status, ['diproses', 'dikirim', 'selesai'])) {
                            $digitalItems = $transaction->items()->whereHas('product', function ($query) {
                                $query->where('is_digital', true);
                            })->get();

                            if ($digitalItems->isNotEmpty()) {
                                $notifMessage .= ' Rincian produk digital Anda:';
                                foreach ($digitalItems as $item) {
                                    if ($item->note) {
                                        $notifMessage .= ' [' . $item->product_name . ': ' . $item->note . ']';
                                    }
                                }
                            }
                        }

                        Notification::create([
                            'user_id' => $transaction->user_id,
                            'title' => 'Pembaruan Status Pesanan',
                            'message' => $notifMessage,
                            'type' => 'transaction_status',
                            'url' => '/transactions/' . $transaction->id,
                            'is_read' => false,
                        ]);
                    } catch (\Throwable $e) {
                        // Fail silently or log
                    }

                    // Create database notification for admins when transaction status is updated
                    try {
                        $customerName = $transaction->user ? $transaction->user->name : 'Customer';
                        $statusLabel = Transaction::statusLabels()[$transaction->status] ?? $transaction->status;

                        $title = $transaction->status === 'menunggu' ? 'Konfirmasi Pembayaran' : 'Pembaruan Status Pesanan';
                        $message = $transaction->status === 'menunggu'
                            ? 'Ada konfirmasi pembayaran dari ' . $customerName . ' untuk transaksi #' . $transaction->transaction_number . ' (Status: Menunggu Konfirmasi).'
                            : 'Pesanan #' . $transaction->transaction_number . ' oleh ' . $customerName . ' kini ' . match ($transaction->status) {
                                'belum_bayar' => 'menunggu pembayaran',
                                'menunggu' => 'menunggu konfirmasi pembayaran',
                                'diproses' => 'sedang diproses',
                                'dikemas' => 'sedang dikemas',
                                'out_for_pickup' => 'sedang dalam proses penjemputan oleh kurir (Out for Pickup)',
                                'dikirim' => 'telah dikirim',
                                'selesai' => 'selesai / telah diterima',
                                'batal' => 'dibatalkan',
                                default => 'diperbarui menjadi ' . $transaction->status,
                            } . ' (Status: ' . $statusLabel . ').';

                        Notification::create([
                            'user_id' => null, // null means Admin global
                            'title' => $title,
                            'message' => $message,
                            'type' => $transaction->status === 'menunggu' ? 'payment_proof' : 'transaction_status',
                            'url' => '/admin/transactions/' . $transaction->id,
                            'is_read' => false,
                        ]);
                    } catch (\Throwable $e) {
                        // Fail silently
                    }
                }

                // Send email notification to user
                try {
                    $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
                    $storeLogo = Setting::where('key', 'store_logo')->value('value');

                    // Ensure relations are loaded
                    $transaction->loadMissing(['user', 'items.product', 'customerAddress', 'paymentMethod']);

                    if ($transaction->user && $transaction->user->email) {
                        Mail::to($transaction->user->email)
                            ->queue(new OrderStatusChanged($transaction, $storeName, $storeLogo));
                    }
                } catch (\Throwable $e) {
                    Log::error('Order status change email failed for transaction ' . $transaction->transaction_number . ': ' . $e->getMessage());
                }
            }
        });

        static::created(function (Transaction $transaction) {
            // Create database notification for admins when a new transaction is created
            try {
                $customerName = $transaction->user ? $transaction->user->name : 'Customer';
                $statusLabel = Transaction::statusLabels()[$transaction->status] ?? $transaction->status;
                Notification::create([
                    'user_id' => null, // null means Admin global
                    'title' => 'Pesanan Masuk Baru',
                    'message' => 'Ada pesanan masuk atas nama ' . $customerName . ' dengan nomor transaksi #' . $transaction->transaction_number . ' (Status: ' . $statusLabel . ').',
                    'type' => 'new_order',
                    'url' => '/admin/transactions/' . $transaction->id,
                    'is_read' => false,
                ]);
            } catch (\Throwable $e) {
                // Fail silently
            }

            // Create database notification for the customer when a new transaction is created
            try {
                $hasDigital = $transaction->items()->whereHas('product', function ($query) {
                    $query->where('is_digital', true);
                })->exists();

                $message = 'Pesanan Anda #' . $transaction->transaction_number . ' berhasil dibuat.';
                if ($hasDigital) {
                    $message .= ' Catatan/kode produk digital Anda akan aktif setelah pembayaran dikonfirmasi.';
                } else {
                    $message .= ' Silakan selesaikan pembayaran agar pesanan dapat segera diproses.';
                }

                Notification::create([
                    'user_id' => $transaction->user_id,
                    'title' => 'Pesanan Berhasil Dibuat',
                    'message' => $message,
                    'type' => 'transaction_status',
                    'url' => '/transactions/' . $transaction->id,
                    'is_read' => false,
                ]);
            } catch (\Throwable $e) {
                // Fail silently
            }
        });
    }

    /**
     * Process auto status updates (cancel unpaid and complete shipped).
     */
    public static function processAutoStatusUpdates(?string $userId = null): void
    {
        // 1. Unpaid Expiry
        $query = static::where('status', 'belum_bayar')
            ->whereNotNull('payment_expires_at')
            ->where('payment_expires_at', '<', now());
        if ($userId) {
            $query->where('user_id', $userId);
        }
        foreach ($query->get() as $transaction) {
            $transaction->update([
                'status' => 'batal',
                'cancel_reason' => 'Pembatalan otomatis oleh sistem karena batas waktu pembayaran habis.',
                'cancelled_at' => now(),
            ]);
        }

        // 2. Auto-complete shipped
        $queryComplete = static::where('status', 'dikirim')
            ->whereNotNull('auto_complete_at')
            ->where('auto_complete_at', '<', now());
        if ($userId) {
            $queryComplete->where('user_id', $userId);
        }
        foreach ($queryComplete->get() as $transaction) {
            $transaction->update([
                'status' => 'selesai',
            ]);
        }
    }

    /**
     * Generate a unique transaction number.
     */
    public static function generateNumber(): string
    {
        $prefix = 'TRX-' . now()->format('Ymd') . '-';
        $operator = DB::connection()->getDriverName() === 'sqlite' ? 'like' : 'ilike';
        $last = static::where('transaction_number', $operator, $prefix . '%')
            ->orderByDesc('transaction_number')
            ->value('transaction_number');

        $seq = $last ? (int) substr($last, -5) + 1 : 1;

        return $prefix . str_pad($seq, 5, '0', STR_PAD_LEFT);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customerAddress(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(TransactionPayment::class)->orderBy('created_at', 'desc');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(TransactionPayment::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(Courier::class);
    }

    public function courierUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'courier_user_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(TransactionStatusHistory::class)->orderBy('created_at', 'asc');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(ReturnRequest::class);
    }

    public function activeReturn(): HasOne
    {
        return $this->hasOne(ReturnRequest::class)->whereNotIn('status', ['ditolak'])->orderBy('created_at', 'desc');
    }

    public function refundRequests(): HasMany
    {
        return $this->hasMany(RefundRequest::class);
    }

    public function activeRefundRequest(): HasOne
    {
        return $this->hasOne(RefundRequest::class)->whereNotIn('status', ['ditolak'])->orderBy('created_at', 'desc');
    }

    /**
     * Get the customer name.
     */
    public function getCustomerNameAttribute(): string
    {
        return $this->user?->name ?? $this->customerAddress?->name ?? '—';
    }

    /**
     * Get the customer email.
     */
    public function getCustomerEmailAttribute(): string
    {
        return $this->user?->email ?? $this->customerAddress?->email ?? '—';
    }

    /**
     * Get the customer phone.
     */
    public function getCustomerPhoneAttribute(): string
    {
        return $this->customerAddress?->phone_number ?? $this->user?->phone_number ?? '—';
    }

    /**
     * Get the items summary.
     */
    public function getItemsSummaryAttribute(): string
    {
        return $this->items->map(function ($item): ?string {
            return $item->product_name;
        })->filter()->implode(', ') ?: '—';
    }

    /**
     * Get the formatted grand total.
     */
    public function getGrandTotalFormattedAttribute(): string
    {
        return 'Rp ' . number_format((float) $this->grand_total, 0, ',', '.');
    }

    /**
     * Get the formatted subtotal.
     */
    public function getSubtotalFormattedAttribute(): string
    {
        return 'Rp ' . number_format((float) $this->subtotal, 0, ',', '.');
    }

    /**
     * Get the formatted discount amount.
     */
    public function getDiscountAmountFormattedAttribute(): string
    {
        return 'Rp ' . number_format((float) $this->discount_amount, 0, ',', '.');
    }

    /**
     * Get the formatted shipping cost.
     */
    public function getShippingCostFormattedAttribute(): string
    {
        return 'Rp ' . number_format((float) $this->shipping_fee, 0, ',', '.');
    }

    /**
     * Get the formatted created at date.
     */
    public function getCreatedAtFormattedAttribute(): string
    {
        return $this->created_at?->translatedFormat('d F Y H:i') ?? $this->created_at?->format('d M Y H:i') ?? '—';
    }
}
