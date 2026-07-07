<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookEvent extends Model
{
    protected $fillable = [
        'source',
        'idempotency_key',
        'processed_status_code',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    /**
     * Check if a webhook event with the given key has already been processed.
     */
    public static function alreadyProcessed(string $key): bool
    {
        return self::where('idempotency_key', $key)->exists();
    }

    /**
     * Record that a webhook event has been processed.
     */
    public static function record(string $source, string $key, array $payload = [], int $statusCode = 200): void
    {
        self::firstOrCreate(
            ['idempotency_key' => $key],
            [
                'source' => $source,
                'processed_status_code' => $statusCode,
                'payload' => $payload,
            ]
        );
    }
}
