<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    public function view(User $user, Transaction $transaction): bool
    {
        if ($user->hasAnyRole(['Super Admin', 'Admin', 'Admin Toko', 'Administrator'])) {
            return true;
        }

        return $user->id === $transaction->user_id;
    }

    public function manage(User $user, Transaction $transaction): bool
    {
        return $user->hasAnyRole(['Super Admin', 'Admin', 'Admin Toko', 'Administrator']);
    }

    public function cancel(User $user, Transaction $transaction): bool
    {
        if ($user->hasAnyRole(['Super Admin', 'Admin', 'Admin Toko', 'Administrator'])) {
            return true;
        }

        return $user->id === $transaction->user_id && $transaction->status === 'belum_bayar';
    }

    public function complete(User $user, Transaction $transaction): bool
    {
        if ($user->hasAnyRole(['Super Admin', 'Admin', 'Admin Toko', 'Administrator'])) {
            return true;
        }

        return $user->id === $transaction->user_id && $transaction->status === 'dikirim';
    }
}
