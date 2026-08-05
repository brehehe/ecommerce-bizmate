<?php

namespace App\Events;

use App\Models\ProductListingPayment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ListingPaymentConfirmed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $id;

    public string $order_id;

    public string $product_id;

    public ?string $user_id;

    public string $status;

    public int $days;

    public float $amount;

    public ?string $paid_at;

    /**
     * Create a new event instance.
     */
    public function __construct(ProductListingPayment $listingPayment)
    {
        $this->id = (string) $listingPayment->id;
        $this->order_id = (string) $listingPayment->order_id;
        $this->product_id = (string) $listingPayment->product_id;
        $this->user_id = $listingPayment->user_id ? (string) $listingPayment->user_id : null;
        $this->status = (string) $listingPayment->status;
        $this->days = (int) $listingPayment->days;
        $this->amount = (float) $listingPayment->amount;
        $this->paid_at = $listingPayment->paid_at?->toIso8601String();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new Channel('listing-payment.'.$this->order_id),
            new PrivateChannel('admin'),
        ];

        if ($this->user_id) {
            $channels[] = new PrivateChannel('user.'.$this->user_id);
        }

        return $channels;
    }

    /**
     * Get the event name to broadcast as.
     */
    public function broadcastAs(): string
    {
        return 'listing.payment.confirmed';
    }
}
