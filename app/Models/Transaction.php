<?php

namespace App\Models;

use App\Mail\OrderStatusChanged;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

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
        'courier_name',
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
            'dikirim' => 'Dikirim',
            'selesai' => 'Selesai',
            'batal' => 'Batal',
        ];
    }

    protected static function booted(): void
    {
        static::updated(function (Transaction $transaction) {
            $statusChanged = $transaction->isDirty('status');
            $trackingChanged = $transaction->isDirty('tracking_number');

            if ($statusChanged || $trackingChanged) {
                if ($statusChanged) {
                    $description = match ($transaction->status) {
                        'belum_bayar' => 'Menunggu pembayaran.',
                        'menunggu' => 'Pembayaran sedang dikonfirmasi / Menunggu konfirmasi.',
                        'diproses' => 'Pesanan sedang diproses.',
                        'dikemas' => 'Pesanan sedang dikemas.',
                        'dikirim' => 'Pesanan telah dikirim.',
                        'selesai' => 'Pesanan telah diterima. Transaksi selesai.',
                        'batal' => 'Pesanan dibatalkan.'.($transaction->cancel_reason ? ' Alasan: '.$transaction->cancel_reason : ''),
                        default => 'Status pesanan diperbarui menjadi: '.$transaction->status,
                    };

                    $transaction->statusHistories()->create([
                        'status' => $transaction->status,
                        'description' => $description,
                        'created_by' => auth()->id(),
                    ]);

                    // Create database notification for the customer
                    try {
                        Notification::create([
                            'user_id' => $transaction->user_id,
                            'title' => 'Pembaruan Status Pesanan',
                            'message' => 'Pesanan Anda #'.$transaction->transaction_number.' kini '.match ($transaction->status) {
                                'belum_bayar' => 'menunggu pembayaran.',
                                'menunggu' => 'menunggu konfirmasi pembayaran.',
                                'diproses' => 'sedang diproses.',
                                'dikemas' => 'sedang dikemas.',
                                'dikirim' => 'telah dikirim.',
                                'selesai' => 'selesai / telah diterima.',
                                'batal' => 'dibatalkan.',
                                default => 'diperbarui menjadi '.$transaction->status,
                            },
                            'type' => 'transaction_status',
                            'url' => '/transactions/'.$transaction->id,
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
                            ? 'Ada konfirmasi pembayaran dari '.$customerName.' untuk transaksi #'.$transaction->transaction_number.' (Status: Menunggu Konfirmasi).'
                            : 'Pesanan #'.$transaction->transaction_number.' oleh '.$customerName.' kini '.match ($transaction->status) {
                                'belum_bayar' => 'menunggu pembayaran',
                                'menunggu' => 'menunggu konfirmasi pembayaran',
                                'diproses' => 'sedang diproses',
                                'dikemas' => 'sedang dikemas',
                                'dikirim' => 'telah dikirim',
                                'selesai' => 'selesai / telah diterima',
                                'batal' => 'dibatalkan',
                                default => 'diperbarui menjadi '.$transaction->status,
                            }.' (Status: '.$statusLabel.').';

                        Notification::create([
                            'user_id' => null, // null means Admin global
                            'title' => $title,
                            'message' => $message,
                            'type' => $transaction->status === 'menunggu' ? 'payment_proof' : 'transaction_status',
                            'url' => '/admin/transactions/'.$transaction->id,
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
                            ->send(new OrderStatusChanged($transaction, $storeName, $storeLogo));
                    }
                } catch (\Throwable $e) {
                    Log::error('Order status change email failed for transaction '.$transaction->transaction_number.': '.$e->getMessage());
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
                    'message' => 'Ada pesanan masuk atas nama '.$customerName.' dengan nomor transaksi #'.$transaction->transaction_number.' (Status: '.$statusLabel.').',
                    'type' => 'new_order',
                    'url' => '/admin/transactions/'.$transaction->id,
                    'is_read' => false,
                ]);
            } catch (\Throwable $e) {
                // Fail silently
            }
        });
    }

    /**
     * Generate a unique transaction number.
     */
    public static function generateNumber(): string
    {
        $prefix = 'TRX-'.now()->format('Ymd').'-';
        $last = static::where('transaction_number', 'like', $prefix.'%')
            ->orderByDesc('transaction_number')
            ->value('transaction_number');

        $seq = $last ? (int) substr($last, -5) + 1 : 1;

        return $prefix.str_pad($seq, 5, '0', STR_PAD_LEFT);
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
        return $this->hasOne(TransactionPayment::class)->latestOfMany();
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

    public function statusHistories(): HasMany
    {
        return $this->hasMany(TransactionStatusHistory::class)->orderBy('created_at', 'asc');
    }
}
