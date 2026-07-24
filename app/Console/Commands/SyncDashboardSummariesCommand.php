<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SyncDashboardSummariesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dashboard:sync-summaries {--days= : Limit sync to N days back. Omit to sync all historical dates}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pre-calculate and sync daily transaction, refund, and return metrics into dashboard_daily_summaries table';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting dashboard daily summaries pre-aggregation sync...');

        $paidStatuses = ['diproses', 'dikirim', 'selesai'];
        $daysLimit = $this->option('days') ? (int) $this->option('days') : null;

        $startDate = $daysLimit ? Carbon::today()->subDays($daysLimit)->toDateString() : null;

        // 1. Grouped Revenue & Paid Orders by Date
        $paidTxQuery = DB::table('transactions')
            ->whereIn('status', $paidStatuses)
            ->selectRaw('DATE(created_at) as date, SUM(grand_total) as revenue, COUNT(id) as paid_orders_count')
            ->groupBy(DB::raw('DATE(created_at)'));

        if ($startDate) {
            $paidTxQuery->where('created_at', '>=', $startDate);
        }

        $paidTxStats = $paidTxQuery->get()->keyBy('date');

        // 2. Grouped Total Orders by Date
        $totalTxQuery = DB::table('transactions')
            ->selectRaw('DATE(created_at) as date, COUNT(id) as orders_count')
            ->groupBy(DB::raw('DATE(created_at)'));

        if ($startDate) {
            $totalTxQuery->where('created_at', '>=', $startDate);
        }

        $totalTxStats = $totalTxQuery->get()->keyBy('date');

        // 3. Grouped Refunds by Date
        $refundsQuery = DB::table('refund_requests')
            ->selectRaw('DATE(created_at) as date, COUNT(id) as refunds_count, SUM(refund_amount) as refunds_amount')
            ->groupBy(DB::raw('DATE(created_at)'));

        if ($startDate) {
            $refundsQuery->where('created_at', '>=', $startDate);
        }

        $refundStats = $refundsQuery->get()->keyBy('date');

        // 4. Grouped Returns by Date
        $returnsQuery = DB::table('returns')
            ->selectRaw('DATE(created_at) as date, COUNT(id) as returns_count, SUM(COALESCE(refund_amount, 0)) as returns_amount')
            ->groupBy(DB::raw('DATE(created_at)'));

        if ($startDate) {
            $returnsQuery->where('created_at', '>=', $startDate);
        }

        $returnStats = $returnsQuery->get()->keyBy('date');

        // Collect all distinct dates
        $allDates = collect([])
            ->merge($paidTxStats->keys())
            ->merge($totalTxStats->keys())
            ->merge($refundStats->keys())
            ->merge($returnStats->keys())
            ->unique()
            ->sort();

        if ($allDates->isEmpty()) {
            $this->warn('No dates found to summarize.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Aggregating data for %d distinct days...', $allDates->count()));

        $now = now()->toDateTimeString();
        $records = [];

        foreach ($allDates as $dateStr) {
            $pTx = $paidTxStats->get($dateStr);
            $tTx = $totalTxStats->get($dateStr);
            $rf = $refundStats->get($dateStr);
            $rt = $returnStats->get($dateStr);

            $records[] = [
                'date' => $dateStr,
                'revenue' => $pTx ? (float) $pTx->revenue : 0,
                'orders_count' => $tTx ? (int) $tTx->orders_count : 0,
                'paid_orders_count' => $pTx ? (int) $pTx->paid_orders_count : 0,
                'refunds_count' => $rf ? (int) $rf->refunds_count : 0,
                'refunds_amount' => $rf ? (float) $rf->refunds_amount : 0,
                'returns_count' => $rt ? (int) $rt->returns_count : 0,
                'returns_amount' => $rt ? (float) $rt->returns_amount : 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Bulk upsert into dashboard_daily_summaries in chunks of 500
        foreach (array_chunk($records, 500) as $chunk) {
            DB::table('dashboard_daily_summaries')->upsert(
                $chunk,
                ['date'],
                [
                    'revenue',
                    'orders_count',
                    'paid_orders_count',
                    'refunds_count',
                    'refunds_amount',
                    'returns_count',
                    'returns_amount',
                    'updated_at',
                ]
            );
        }

        $this->info('Successfully pre-aggregated daily dashboard summaries!');

        return self::SUCCESS;
    }
}
