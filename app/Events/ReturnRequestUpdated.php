<?php

namespace App\Events;

use App\Models\ReturnRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReturnRequestUpdated implements ShouldBroadcastNow
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
    public function __construct(public ReturnRequest $returnRequest)
    {
        $this->data = [
            'id' => $returnRequest->id,
            'return_number' => $returnRequest->return_number,
            'transaction_id' => $returnRequest->transaction_id,
            'status' => $returnRequest->status,
            'user_id' => $returnRequest->user_id,
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

        if ($this->returnRequest->user_id) {
            $channels[] = new PrivateChannel('user.'.$this->returnRequest->user_id);
        }

        return $channels;
    }

    /**
     * Get the event name to broadcast as.
     */
    public function broadcastAs(): string
    {
        return 'return.updated';
    }
}
