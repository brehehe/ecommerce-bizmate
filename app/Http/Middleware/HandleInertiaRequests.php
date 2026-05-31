<?php

namespace App\Http\Middleware;

use App\Models\CartItem;
use App\Models\ChatMessage;
use App\Models\Notification;
use App\Models\ProductStock;
use App\Models\Setting;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $primaryColor = '#0c4cb4';
        $secondaryColor = '#fa7315';
        $taxEnabled = false;
        $taxPercentage = 0;
        $storeName = config('app.name');
        $storeLogo = null;

        $setupTourCompleted = false;

        try {
            if (Schema::hasTable('settings')) {
                $primaryColor = Setting::where('key', 'primary_color')->value('value') ?? $primaryColor;
                $secondaryColor = Setting::where('key', 'secondary_color')->value('value') ?? $secondaryColor;
                $taxEnabled = Setting::where('key', 'tax_enabled')->value('value') === '1';
                $taxPercentage = Setting::where('key', 'tax_percentage')->value('value') ?? 0;
                $storeName = Setting::where('key', 'store_name')->value('value') ?? $storeName;
                $storeLogo = Setting::where('key', 'store_logo')->value('value');
                $setupTourCompleted = Setting::where('key', 'setup_tour_completed')->value('value') === '1';
            }
        } catch (\Throwable $e) {
            // Fallback when database is not ready
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
            ],
            'auth' => [
                'user' => $request->user(),
            ],
            'cartCount' => $request->user() ? CartItem::where('user_id', $request->user()->id)->sum('quantity') : 0,
            'chatUnreadCount' => $request->user() ? ChatMessage::whereHas('chat', fn ($q) => $q->where('user_id', $request->user()->id))->where('sender_type', 'admin')->where('is_read', false)->count() : 0,
            'adminChatUnreadCount' => $request->user() ? ChatMessage::where('sender_type', 'user')->where('is_read', false)->count() : 0,
            'theme' => [
                'primary_color' => $primaryColor,
                'secondary_color' => $secondaryColor,
            ],
            'settings' => [
                'tax_enabled' => $taxEnabled,
                'tax_percentage' => (float) $taxPercentage,
                'store_name' => $storeName,
                'store_logo' => $storeLogo,
                'setup_tour_completed' => $setupTourCompleted,
            ],
            'adminNotifications' => $request->user() && ! $request->user()->hasRole('Customer') ? [
                'lowStockCount' => ProductStock::where('is_unlimited', false)
                    ->where('stock', '>', 0)
                    ->whereColumn('stock', '<=', 'min_stock')
                    ->count(),
                'outOfStockCount' => ProductStock::where('is_unlimited', false)
                    ->where('stock', '<=', 0)
                    ->count(),
                'lowStockItems' => ProductStock::with(['product', 'variant.options'])
                    ->where('is_unlimited', false)
                    ->where('stock', '>', 0)
                    ->whereColumn('stock', '<=', 'min_stock')
                    ->orderBy('stock', 'asc')
                    ->limit(5)
                    ->get()
                    ->map(fn ($ps) => [
                        'id' => $ps->id,
                        'product_id' => $ps->product_id,
                        'name' => $ps->product ? $ps->product->name.($ps->variant && $ps->variant->options->count() > 0 ? ' ('.$ps->variant->options->pluck('name')->implode(', ').')' : '') : 'Unknown Product',
                        'stock' => $ps->stock,
                        'min_stock' => $ps->min_stock,
                    ]),
                'outOfStockItems' => ProductStock::with(['product', 'variant.options'])
                    ->where('is_unlimited', false)
                    ->where('stock', '<=', 0)
                    ->limit(5)
                    ->get()
                    ->map(fn ($ps) => [
                        'id' => $ps->id,
                        'product_id' => $ps->product_id,
                        'name' => $ps->product ? $ps->product->name.($ps->variant && $ps->variant->options->count() > 0 ? ' ('.$ps->variant->options->pluck('name')->implode(', ').')' : '') : 'Unknown Product',
                        'stock' => $ps->stock,
                    ]),
                'transactionCounts' => [
                    'belum_bayar' => Transaction::where('status', 'belum_bayar')->count(),
                    'menunggu' => Transaction::where('status', 'menunggu')->count(),
                    'diproses' => Transaction::where('status', 'diproses')->count(),
                ],
                'notifications' => Notification::whereNull('user_id')
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get()
                    ->map(fn ($n) => [
                        'id' => $n->id,
                        'title' => $n->title,
                        'message' => $n->message,
                        'type' => $n->type,
                        'url' => $n->url,
                        'is_read' => $n->is_read,
                        'created_at' => $n->created_at->diffForHumans(),
                        'time_raw' => $n->created_at->toIso8601String(),
                    ]),
            ] : null,
            'customerNotifications' => $request->user() ? Notification::where('user_id', $request->user()->id)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(fn ($n) => [
                    'id' => $n->id,
                    'title' => $n->title,
                    'message' => $n->message,
                    'type' => $n->type,
                    'url' => $n->url,
                    'is_read' => $n->is_read,
                    'created_at' => $n->created_at->diffForHumans(),
                    'time_raw' => $n->created_at->toIso8601String(),
                ]) : [],
        ];
    }
}
