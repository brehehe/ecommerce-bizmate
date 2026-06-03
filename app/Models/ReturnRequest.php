<?php

namespace App\Models;

use App\Mail\ReturnStatusChanged;
use App\Mail\ReturnSubmitted;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ReturnRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'returns';

    protected $fillable = [
        'return_number',
        'transaction_id',
        'user_id',
        'status',
        'type',
        'reason',
        'notes_admin',
        'return_tracking_number',
        'return_courier_name',
        'replacement_tracking_number',
        'replacement_courier_name',
        'refund_amount',
        'replacement_transaction_id',
        'approved_by',
        'received_by',
        'approved_at',
        'rejected_at',
        'received_at',
        'refunded_at',
    ];

    protected $casts = [
        'refund_amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'received_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    /**
     * Return status labels in Indonesian.
     *
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            'menunggu_review' => 'Menunggu Review',
            'disetujui' => 'Disetujui',
            'ditolak' => 'Ditolak',
            'barang_dikirim_customer' => 'Barang Dikirim Customer',
            'barang_diterima_toko' => 'Barang Diterima Toko',
            'refund_diproses' => 'Refund Diproses',
            'selesai' => 'Selesai',
        ];
    }

    /**
     * Generate a unique return number.
     */
    public static function generateNumber(): string
    {
        $prefix = 'RTR-'.now()->format('Ymd').'-';
        $last = static::where('return_number', 'ilike', $prefix.'%')
            ->orderByDesc('return_number')
            ->value('return_number');

        $seq = $last ? (int) substr($last, -5) + 1 : 1;

        return $prefix.str_pad($seq, 5, '0', STR_PAD_LEFT);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function receivedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function replacementTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'replacement_transaction_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnItem::class, 'return_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(ReturnMedia::class, 'return_id');
    }

    protected static function booted(): void
    {
        static::created(function (ReturnRequest $return) {
            try {
                $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
                $storeLogo = Setting::where('key', 'store_logo')->value('value');
                $return->loadMissing('user');
                if ($return->user && $return->user->email) {
                    Mail::to($return->user->email)
                        ->queue(new ReturnSubmitted($return, $storeName, $storeLogo));
                }
            } catch (\Throwable $e) {
                Log::error('Return submitted email failed: '.$e->getMessage());
            }
        });

        static::updated(function (ReturnRequest $return) {
            if ($return->isDirty('status')) {
                try {
                    $storeName = Setting::where('key', 'store_name')->value('value') ?? config('app.name');
                    $storeLogo = Setting::where('key', 'store_logo')->value('value');
                    $return->loadMissing('user');
                    if ($return->user && $return->user->email) {
                        Mail::to($return->user->email)
                            ->queue(new ReturnStatusChanged($return, $storeName, $storeLogo));
                    }
                } catch (\Throwable $e) {
                    Log::error('Return status change email failed: '.$e->getMessage());
                }
            }
        });
    }
}
