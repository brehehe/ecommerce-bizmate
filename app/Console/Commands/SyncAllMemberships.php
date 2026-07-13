<?php

namespace App\Console\Commands;

use App\Models\MembershipLevel;
use App\Models\User;
use App\Services\MembershipService;
use Illuminate\Console\Command;

class SyncAllMemberships extends Command
{
    protected $signature = 'membership:sync
                            {--user= : Sync membership for a specific user ID}
                            {--level= : Only sync users currently at this level slug}
                            {--dry-run : Preview changes without applying them}
                            {--force : Skip confirmation prompt}
                            {--chunk=100 : Number of users to process per batch}';

    protected $description = 'Sync membership levels for all customers based on their purchase history';

    public function __construct(private readonly MembershipService $membershipService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $userId = $this->option('user');
        $levelSlug = $this->option('level');
        $chunkSize = (int) $this->option('chunk');

        $this->info('');
        $this->info('  ─────────────────────────────────────────');
        $this->info('   Membership Sync');
        $this->info('  ─────────────────────────────────────────');
        if ($dryRun) {
            $this->warn('  [DRY RUN] No changes will be applied.');
        }
        $this->info('');

        // ── Single user mode ─────────────────────────────────
        if ($userId) {
            $user = User::find($userId);
            if (! $user) {
                $this->error("User with ID [{$userId}] not found.");

                return self::FAILURE;
            }

            $this->processSingleUser($user, $dryRun);

            return self::SUCCESS;
        }

        // ── Bulk mode ─────────────────────────────────────────
        $query = User::whereHas('roles', fn ($q) => $q->where('name', 'Customer'));

        if ($levelSlug) {
            $level = MembershipLevel::where('slug', $levelSlug)->first();
            if (! $level) {
                $this->error("Level with slug [{$levelSlug}] not found.");

                return self::FAILURE;
            }
            $query->whereHas('membership', fn ($q) => $q->where('membership_level_id', $level->id));
            $this->info("  Filtering: only users at level [{$level->name}]");
        }

        $total = $query->count();
        $this->info("  Users to process: {$total}");
        $this->info('');

        if (! $dryRun && ! $this->option('force')) {
            if (! $this->confirm("  Proceed with syncing {$total} members?", true)) {
                $this->info('  Aborted.');

                return self::SUCCESS;
            }
        }

        $upgraded = 0;
        $downgraded = 0;
        $unchanged = 0;
        $newMembers = 0;
        $errors = 0;

        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
        $bar->setMessage('Processing...');
        $bar->start();

        $query->chunkById($chunkSize, function ($users) use (
            $dryRun, $bar, &$upgraded, &$downgraded, &$unchanged, &$newMembers, &$errors
        ) {
            foreach ($users as $user) {
                try {
                    $before = $user->membership()->with('level')->first();
                    $beforeLevel = $before?->level;

                    if (! $dryRun) {
                        $this->membershipService->syncMembership($user);
                    } else {
                        // Dry run: calculate what would happen
                        $stats = $this->membershipService->calculateStats($user);
                        $eligible = $this->membershipService->getEligibleLevel($stats);

                        if (! $before) {
                            $newMembers++;
                            $bar->setMessage("Would create: {$user->name} → ".($eligible?->name ?? 'Member'));
                            $bar->advance();

                            continue;
                        }

                        if ($eligible && $eligible->id !== $beforeLevel?->id) {
                            $isUpgrade = ($eligible->order > ($beforeLevel?->order ?? -1));
                            if ($isUpgrade) {
                                $upgraded++;
                                $bar->setMessage("[DRY] UP: {$user->name} {$beforeLevel?->name} → {$eligible->name}");
                            } else {
                                $downgraded++;
                                $bar->setMessage("[DRY] DOWN: {$user->name} {$beforeLevel?->name} → {$eligible->name}");
                            }
                        } else {
                            $unchanged++;
                        }

                        $bar->advance();

                        continue;
                    }

                    // After sync, check what changed
                    $after = $user->fresh()->membership()->with('level')->first();
                    $afterLevel = $after?->level;

                    if (! $before && $after) {
                        $newMembers++;
                        $bar->setMessage("New: {$user->name} → {$afterLevel?->name}");
                    } elseif ($before && $after && $beforeLevel?->id !== $afterLevel?->id) {
                        $isUpgrade = ($afterLevel?->order ?? 0) > ($beforeLevel?->order ?? 0);
                        if ($isUpgrade) {
                            $upgraded++;
                            $bar->setMessage("UP: {$user->name} {$beforeLevel?->name} → {$afterLevel?->name}");
                        } else {
                            $downgraded++;
                            $bar->setMessage("DOWN: {$user->name} {$beforeLevel?->name} → {$afterLevel?->name}");
                        }
                    } else {
                        $unchanged++;
                        $bar->setMessage("OK: {$user->name}");
                    }
                } catch (\Throwable $e) {
                    $errors++;
                    $bar->setMessage("ERR: {$user->name} — {$e->getMessage()}");
                }

                $bar->advance();
            }
        });

        $bar->finish();

        // ── Summary ───────────────────────────────────────────
        $this->info('');
        $this->info('');
        $this->info('  ─────────────────────────────────────────');
        $this->info('   Summary'.($dryRun ? ' [DRY RUN]' : ''));
        $this->info('  ─────────────────────────────────────────');
        $this->table(
            ['Status', 'Count'],
            [
                ['✓ Naik Level (Upgraded)',    $upgraded],
                ['↓ Turun Level (Downgraded)', $downgraded],
                ['+ New Members',              $newMembers],
                ['= Tidak Berubah',            $unchanged],
                ['✗ Error',                    $errors],
                ['─ Total Diproses',           $total],
            ]
        );

        if ($errors > 0) {
            $this->warn("  {$errors} error(s) occurred. Check the log for details.");

            return self::FAILURE;
        }

        $this->info('');
        if ($dryRun) {
            $this->info('  Dry run complete. Run without --dry-run to apply changes.');
        } else {
            $this->info('  Sync complete. All memberships are up to date.');
        }
        $this->info('');

        return self::SUCCESS;
    }

    protected function processSingleUser(User $user, bool $dryRun): void
    {
        $before = $user->membership()->with('level')->first();
        $stats = $this->membershipService->calculateStats($user);
        $eligible = $this->membershipService->getEligibleLevel($stats);

        $this->info("  User     : {$user->name} ({$user->email})");
        $this->info('  Level    : '.($before?->level?->name ?? 'Belum ada'));
        $this->info('  Eligible : '.($eligible?->name ?? 'Belum ada'));
        $this->info('  Stats    :');
        $this->info('    Total Belanja  : Rp '.number_format($stats['total_purchase'], 0, ',', '.'));
        $this->info("    Total Transaksi: {$stats['total_transactions']}x");
        $this->info("    Total Produk   : {$stats['total_products']} produk");

        if ($before?->level?->id === $eligible?->id) {
            $this->info('  Tidak ada perubahan level.');

            return;
        }

        if (! $dryRun) {
            $this->membershipService->syncMembership($user);
            $after = $user->fresh()->membership()->with('level')->first();
            $this->info('  Level diperbarui → '.$after?->level?->name);
        } else {
            $direction = ($eligible?->order ?? 0) > ($before?->level?->order ?? 0) ? 'NAIK' : 'TURUN';
            $this->warn("  [DRY RUN] {$direction}: {$before?->level?->name} → {$eligible?->name}");
        }
    }
}
