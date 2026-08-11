<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(Request $request, Notification $notification): RedirectResponse
    {
        if ($notification->user_id === null) {
            if (! $request->user()->hasAnyRole(['Super Admin', 'Admin Penjualan', 'Admin Toko'])) {
                abort(403);
            }
        } elseif ($notification->user_id !== $request->user()->id) {
            abort(403);
        }

        $notification->update(['is_read' => true]);

        return redirect()->back();
    }

    /**
     * Mark all notifications of the user as read.
     */
    public function markAllAsRead(Request $request): RedirectResponse
    {
        $type = $request->input('type');

        if ($type === 'admin' && $request->user()->hasAnyRole(['Super Admin', 'Admin Penjualan', 'Admin Toko'])) {
            Notification::whereNull('user_id')
                ->where('is_read', false)
                ->update(['is_read' => true]);
        } else {
            Notification::where('user_id', $request->user()->id)
                ->where('is_read', false)
                ->update(['is_read' => true]);
        }

        return redirect()->back();
    }
}
