<?php

namespace App\Events;

use App\Models\RefundRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RefundRequestUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Event data payload.
     *
     * @var array<string, mixed>
     */
    public array $data;

    /**
     * Create a new event instance.
     */
    public function __construct(public RefundRequest $refundRequest)
    {
        $this->data = [
            'id' => $refundRequest->id,
            'refund_number' => $refundRequest->refund_number,
            'transaction_id' => $refundRequest->transaction_id,
            'status' => $refundRequest->status,
            'user_id' => $refundRequest->user_id,
        ];
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('admin'),
        ];

        if ($this->refundRequest->user_id) {
            $channels[] = new PrivateChannel('user.'.$this->refundRequest->user_id);
        }

        return $channels;
    }

    /**
     * Get the event name to broadcast as.
     */
    public function broadcastAs(): string
    {
        return 'refund.updated';
    }
}
