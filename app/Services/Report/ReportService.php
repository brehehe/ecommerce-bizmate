<?php

namespace App\Services\Report;

use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportService
{
    /**
     * Get date range from request or set defaults (last 30 days).
     *
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    public function getDateRange(Request $request): array
    {
        $preset = $request->input('preset');

        if ($preset) {
            switch ($preset) {
                case 'harian':
                    $dateFrom = Carbon::today()->startOfDay();
                    $dateTo = Carbon::today()->endOfDay();
                    break;
                case 'mingguan':
                    $dateFrom = Carbon::now()->subDays(6)->startOfDay();
                    $dateTo = Carbon::now()->endOfDay();
                    break;
                case 'bulanan':
                    $dateFrom = Carbon::now()->subDays(29)->startOfDay();
                    $dateTo = Carbon::now()->endOfDay();
                    break;
                case 'tahunan':
                    $dateFrom = Carbon::now()->startOfYear()->startOfDay();
                    $dateTo = Carbon::now()->endOfDay();
                    break;
                default:
                    $dateFrom = $request->input('date_from')
                        ? Carbon::parse($request->input('date_from'))->startOfDay()
                        : Carbon::now()->subDays(29)->startOfDay();
                    $dateTo = $request->input('date_to')
                        ? Carbon::parse($request->input('date_to'))->endOfDay()
                        : Carbon::now()->endOfDay();
                    $preset = 'custom';
                    break;
            }
        } else {
            $dateFrom = $request->input('date_from')
                ? Carbon::parse($request->input('date_from'))->startOfDay()
                : Carbon::now()->subDays(29)->startOfDay();

            $dateTo = $request->input('date_to')
                ? Carbon::parse($request->input('date_to'))->endOfDay()
                : Carbon::now()->endOfDay();

            $today = Carbon::today()->format('Y-m-d');
            $weekAgo = Carbon::now()->subDays(6)->format('Y-m-d');
            $monthAgo = Carbon::now()->subDays(29)->format('Y-m-d');
            $yearStart = Carbon::now()->startOfYear()->format('Y-m-d');
            $nowStr = Carbon::now()->format('Y-m-d');

            $fromStr = $dateFrom->format('Y-m-d');
            $toStr = $dateTo->format('Y-m-d');

            if ($fromStr === $today && $toStr === $today) {
                $preset = 'harian';
            } elseif ($fromStr === $weekAgo && $toStr === $nowStr) {
                $preset = 'mingguan';
            } elseif ($fromStr === $monthAgo && $toStr === $nowStr) {
                $preset = 'bulanan';
            } elseif ($fromStr === $yearStart && $toStr === $nowStr) {
                $preset = 'tahunan';
            } else {
                $preset = 'custom';
            }
        }

        return [$dateFrom, $dateTo, $preset];
    }

    /**
     * Get seller info if logged-in user is a seller (and not Super Admin / Admin).
     */
    public function getSellerInfo(Request $request): ?array
    {
        $user = $request->user();
        if ($user && $user->is_seller && ! $user->hasAnyRole(['Super Admin', 'Admin'])) {
            return [
                'is_seller' => true,
                'user_id' => $user->id,
            ];
        }

        return null;
    }
}
