<?php

namespace App\Observers;

use App\Events\NotificationUpdated;
use App\Events\ReturnRequestUpdated;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ReturnRequestObserver
{
    /**
     * Fired when a return request is created.
     */
    public function created(ReturnRequest $returnRequest): void
    {
        broadcast(new ReturnRequestUpdated($returnRequest))->toOthers();
        $this->broadcastAdminNotifications();
    }

    /**
     * Fired when a return request is updated.
     */
    public function updated(ReturnRequest $returnRequest): void
    {
        broadcast(new ReturnRequestUpdated($returnRequest))->toOthers();
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
            Log::error('Broadcast admin notifications failed for return: '.$e->getMessage());
        }
    }
}
