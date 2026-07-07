<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class RefundRequest extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'refund_number',
        'transaction_id',
        'user_id',
        'status',
        'refund_method',
        'reason',
        'notes_admin',
        'bank_name',
        'account_number',
        'account_name',
        'refund_amount',
        'processed_by',
        'processed_at',
        'refunded_at',
    ];

    protected $casts = [
        'refund_amount' => 'decimal:2',
        'processed_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    /**
     * Refund status labels in Indonesian.
     *
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            'menunggu_konfirmasi' => 'Menunggu Konfirmasi',
            'disetujui' => 'Disetujui',
            'ditolak' => 'Ditolak',
            'selesai' => 'Refund Selesai',
        ];
    }

    /**
     * Generate a unique refund number.
     */
    public static function generateNumber(): string
    {
        $prefix = 'RFD-' . now()->format('Ymd') . '-';
        $operator = DB::connection()->getDriverName() === 'sqlite' ? 'like' : 'ilike';
        $last = static::where('refund_number', $operator, $prefix . '%')
            ->orderByDesc('refund_number')
            ->value('refund_number');

        $seq = $last ? (int) substr($last, -5) + 1 : 1;

        return $prefix . str_pad($seq, 5, '0', STR_PAD_LEFT);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function processedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
