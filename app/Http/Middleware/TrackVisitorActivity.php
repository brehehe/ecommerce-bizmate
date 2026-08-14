<?php

namespace App\Http\Middleware;

use App\Models\PageView;
use App\Models\Product;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class TrackVisitorActivity
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only track successful GET requests for web pages
        if ($request->isMethod('GET') && $this->shouldTrack($request, $response)) {
            $this->recordPageView($request);
        }

        return $response;
    }

    /**
     * Determine if the current request should be tracked.
     */
    protected function shouldTrack(Request $request, Response $response): bool
    {
        // Only track 200 OK responses
        if ($response->getStatusCode() !== 200) {
            return false;
        }

        $path = trim($request->path(), '/');

        // Exclude admin, courier, api, and system internal paths
        if ($request->is('admin*', 'kurir*', 'api*', 'up', 'email/verify*', 'zozzuehmqewbobfo*')) {
            return false;
        }

        // Exclude file assets and well-known paths
        if (preg_match('/\.(ico|png|jpg|jpeg|gif|svg|webp|css|js|woff|woff2|ttf|map|json|xml|txt)$/i', $path)) {
            return false;
        }

        // Exclude robots and automated crawlers if detected
        $userAgent = $request->userAgent() ?? '';
        if (preg_match('/bot|crawl|spider|slurp|facebookexternalhit|whatsapp|telegram/i', $userAgent)) {
            return false;
        }

        return true;
    }

    /**
     * Record the page view into database.
     */
    protected function recordPageView(Request $request): void
    {
        try {
            $sessionId = $request->hasSession() ? $request->session()->getId() : substr(hash('sha256', ($request->ip() ?? '127.0.0.1').($request->userAgent() ?? '')), 0, 64);
            $user = $request->user();
            $userId = $user?->id;

            if ($user) {
                // Update user's last active timestamp quietly
                $user->forceFill(['last_active_at' => now()])->saveQuietly();
            }

            $sellerId = $this->resolveSellerId($request);
            $device = $this->detectDevice($request->userAgent() ?? '');

            PageView::create([
                'session_id' => $sessionId,
                'user_id' => $userId,
                'seller_id' => $sellerId,
                'ip_address' => $request->ip(),
                'url' => Str::limit($request->fullUrl(), 500, ''),
                'path' => Str::limit('/'.ltrim($request->path(), '/'), 255, ''),
                'route_name' => $request->route()?->getName(),
                'device' => $device,
                'referer' => Str::limit($request->headers->get('referer'), 500, ''),
                'user_agent' => Str::limit($request->userAgent(), 500, ''),
            ]);
        } catch (\Throwable $e) {
            // Silently log exception so tracking never breaks user experience
            Log::debug('Failed to record page view: '.$e->getMessage());
        }
    }

    /**
     * Resolve seller ID associated with the current page request.
     */
    protected function resolveSellerId(Request $request): ?string
    {
        $routeName = $request->route()?->getName();

        // 1. Seller Store route: /{slug}
        if ($routeName === 'seller.store') {
            $slug = $request->route('slug');
            if ($slug) {
                $seller = User::whereRaw('LOWER(store_slug) = ?', [Str::lower($slug)])
                    ->where('is_seller', true)
                    ->first(['id']);

                if ($seller) {
                    return $seller->id;
                }
            }
        }

        // 2. Product Detail route: /products/{product}
        if ($routeName === 'products.show') {
            $productParam = $request->route('product');
            if ($productParam) {
                if (is_object($productParam) && isset($productParam->user_id)) {
                    return $productParam->user_id;
                }

                if (is_string($productParam)) {
                    $product = Str::isUuid($productParam)
                        ? Product::where('id', $productParam)->first(['user_id'])
                        : Product::where('slug', $productParam)->first(['user_id']);

                    if ($product) {
                        return $product->user_id;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Detect client device from user agent.
     */
    protected function detectDevice(string $userAgent): string
    {
        if (preg_match('/(tablet|ipad|playbook|silk)|(android(?!.*mobi))/i', $userAgent)) {
            return 'tablet';
        }

        if (preg_match('/Mobile|iP(hone|od)|Android|BlackBerry|IEMobile|Kindle|Silk-Accelerated|(hpw|web)OS|Opera M(obi|ini)/i', $userAgent)) {
            return 'mobile';
        }

        return 'desktop';
    }
}
