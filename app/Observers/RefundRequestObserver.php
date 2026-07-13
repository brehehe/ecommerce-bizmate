<?php

namespace App\Observers;

use App\Models\RefundRequest;
use App\Events\RefundRequestUpdated;
use App\Events\NotificationUpdated;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class RefundRequestObserver
{
    /**
     * Fired when a refund request is created.
     */
    public function created(RefundRequest $refundRequest): void
    {
        broadcast(new RefundRequestUpdated($refundRequest))->toOthers();
        $this->broadcastAdminNotifications();
    }

    /**
     * Fired when a refund request is updated.
     */
    public function updated(RefundRequest $refundRequest): void
    {
        broadcast(new RefundRequestUpdated($refundRequest))->toOthers();
        $this->broadcastAdminNotifications();
    }

    /**
     * Helper to broadcast notification count updates to all admin/staff users.
     */
    protected function broadcastAdminNotifications(): void
    {
        try {
            $admins = User::whereDoesntHave('roles', function ($q) {
                $q->where('name', 'Customer');
            })->get();

            foreach ($admins as $admin) {
                broadcast(new NotificationUpdated($admin->id))->toOthers();
            }
        } catch (\Throwable $e) {
            Log::error('Broadcast admin notifications failed for refund: ' . $e->getMessage());
        }
    }
}
