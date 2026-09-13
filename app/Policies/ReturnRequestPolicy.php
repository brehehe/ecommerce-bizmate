<?php

namespace App\Policies;

use App\Models\ReturnRequest;
use App\Models\User;

class ReturnRequestPolicy
{
    public function view(User $user, ReturnRequest $returnRequest): bool
    {
        if ($user->hasAnyRole(['Super Admin', 'Admin', 'Admin Toko', 'Administrator'])) {
            return true;
        }

        return $user->id === $returnRequest->user_id;
    }

    public function manage(User $user, ReturnRequest $returnRequest): bool
    {
        return $user->hasAnyRole(['Super Admin', 'Admin', 'Admin Toko', 'Administrator']);
    }
}
