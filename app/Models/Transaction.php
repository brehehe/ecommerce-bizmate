<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'transaction_number',
        'user_id',
        'customer_address_id',
        'payment_method_id',
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
}
