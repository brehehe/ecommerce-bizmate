<?php

namespace App\Policies;

use App\Models\RefundRequest;
use App\Models\User;

class RefundRequestPolicy
{
    public function view(User $user, RefundRequest $refundRequest): bool
    {
        if ($user->hasAnyRole(['Super Admin', 'Admin', 'Admin Toko', 'Administrator'])) {
            return true;
        }

        return $user->id === $refundRequest->user_id;
    }

    public function manage(User $user, RefundRequest $refundRequest): bool
    {
        return $user->hasAnyRole(['Super Admin', 'Admin', 'Admin Toko', 'Administrator']);
    }
}
