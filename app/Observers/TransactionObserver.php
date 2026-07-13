<?php

namespace App\Observers;

use App\Events\NotificationUpdated;
use App\Events\TransactionUpdated;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MembershipService;
use Illuminate\Support\Facades\Log;

class TransactionObserver
{
    public function __construct(protected MembershipService $membershipService) {}

    /**
     * Fired when a transaction is created.
     */
    public function created(Transaction $transaction): void
    {
        broadcast(new TransactionUpdated($transaction))->toOthers();
        $this->broadcastAdminNotifications();
    }

    /**
     * Fired when a transaction is updated.
     */
    public function updated(Transaction $transaction): void
    {
        // Broadcast real-time update
        broadcast(new TransactionUpdated($transaction))->toOthers();
        $this->broadcastAdminNotifications();

        // Only trigger when status changes to 'selesai'
        if (! $transaction->wasChanged('status') || $transaction->status !== 'selesai') {
            return;
        }

        $user = $transaction->user;

        if (! $user || ! $user->hasRole('Customer')) {
            return;
        }

        // Award points & cashback
        $this->membershipService->awardPoints($user, $transaction);
        $this->membershipService->awardCashback($user, $transaction);

        // Sync membership level (may trigger upgrade)
        $this->membershipService->syncMembership($user);
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
            Log::error('Broadcast admin notifications failed: '.$e->getMessage());
        }
    }
}
