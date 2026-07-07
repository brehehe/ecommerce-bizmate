<?php

namespace App\Events;

use App\Models\CartItem;
use App\Models\ChatMessage;
use App\Models\ProductStock;
use App\Models\RefundRequest;
use App\Models\ReturnRequest;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The broadcast data.
     *
     * @var array<string, mixed>
     */
    public array $data;

    /**
     * Create a new event instance.
     */
    public function __construct(public string $userId)
    {
        $user = User::find($userId);
        if (! $user) {
            $this->data = [];

            return;
        }

        $isAdmin = ! $user->hasRole('Customer');

        if ($isAdmin) {
            $adminChatUnreadCount = ChatMessage::where('sender_type', 'user')->where('is_read', false)->count();
            $lowStockCount = ProductStock::where('is_unlimited', false)
                ->where('stock', '>', 0)
                ->whereColumn('stock', '<=', 'min_stock')
                ->count();
            $outOfStockCount = ProductStock::where('is_unlimited', false)
                ->where('stock', '<=', 0)
                ->count();
            $unpaidTxn = Transaction::where('status', 'belum_bayar')->count();
            $pendingTxn = Transaction::where('status', 'menunggu')->count();
            $processedTxn = Transaction::where('status', 'diproses')->count();
            $pendingReturn = ReturnRequest::where('status', 'menunggu_review')->count();
            $pendingRefund = RefundRequest::where('status', 'menunggu_konfirmasi')->count();

            $this->data = [
                'adminChatUnreadCount' => $adminChatUnreadCount,
                'adminNotifications' => [
                    'lowStockCount' => $lowStockCount,
                    'outOfStockCount' => $outOfStockCount,
                    'transactionCounts' => [
                        'belum_bayar' => $unpaidTxn,
                        'menunggu' => $pendingTxn,
                        'diproses' => $processedTxn,
                    ],
                    'returnCounts' => [
                        'menunggu_review' => $pendingReturn,
                    ],
                    'refundCounts' => [
                        'menunggu_konfirmasi' => $pendingRefund,
                    ],
                ],
            ];
        } else {
            $chatUnreadCount = ChatMessage::whereHas('chat', fn ($q) => $q->where('user_id', $userId))
                ->where('sender_type', 'admin')
                ->where('is_read', false)
                ->count();
            $cartCount = CartItem::where('user_id', $userId)->count();

            $this->data = [
                'chatUnreadCount' => $chatUnreadCount,
                'cartCount' => $cartCount,
            ];
        }
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->userId),
        ];
    }

    /**
     * Get the event name to broadcast as.
     */
    public function broadcastAs(): string
    {
        return 'notification.updated';
    }
}
