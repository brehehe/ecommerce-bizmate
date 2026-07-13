<?php

namespace App\Console\Commands;

use App\Models\CustomerMembership;
use App\Models\MembershipVoucher;
use App\Models\Notification;
use App\Services\MembershipService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ProcessMembershipBirthdayBonus extends Command
{
    protected $signature = 'membership:birthday-bonus {--dry-run : Show what would be processed without actually running}';

    protected $description = 'Issue birthday bonus vouchers to members whose birthday is today';

    public function __construct(private readonly MembershipService $membershipService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $today = Carbon::today();
        $month = $today->month;
        $day = $today->day;
        $dryRun = $this->option('dry-run');

        $this->info("Processing birthday bonuses for {$today->toDateString()}...");

        // Find members whose birthday is today
        $memberships = CustomerMembership::with(['user', 'level.activeBenefits'])
            ->whereHas('user', function ($q) use ($month, $day) {
                $q->whereNotNull('birth_date')
                    ->whereMonth('birth_date', $month)
                    ->whereDay('birth_date', $day);
            })
            ->get();

        if ($memberships->isEmpty()) {
            $this->info('No members have a birthday today.');

            return self::SUCCESS;
        }

        $issued = 0;
        $skipped = 0;

        foreach ($memberships as $membership) {
            $user = $membership->user;
            $level = $membership->level;

            if (! $level) {
                $skipped++;

                continue;
            }

            // Check if level has birthday_bonus benefit
            $birthdayBenefit = $level->activeBenefits
                ->where('type', 'birthday_bonus')
                ->first();

            if (! $birthdayBenefit) {
                $skipped++;

                continue;
            }

            // Check if already issued a birthday voucher this year
            $alreadyIssued = $user->membershipVouchers()
                ->where('trigger', 'birthday')
                ->whereYear('valid_from', $today->year)
                ->exists();

            if ($alreadyIssued) {
                $this->line("  Skipping {$user->name} — birthday voucher already issued this year.");
                $skipped++;

                continue;
            }

            if (! $dryRun) {
                \DB::transaction(function () use ($user, $level, $birthdayBenefit) {
                    MembershipVoucher::create([
                        'user_id' => $user->id,
                        'membership_level_id' => $level->id,
                        'code' => strtoupper('BDAY-'.Str::random(8)),
                        'label' => "Hadiah Ulang Tahun {$level->name}",
                        'discount_type' => 'percentage',
                        'discount_value' => $birthdayBenefit->value,
                        'min_purchase' => 0,
                        'trigger' => 'birthday',
                        'status' => 'active',
                        'valid_from' => now()->toDateString(),
                        'valid_until' => now()->addDays(30)->toDateString(),
                    ]);
                });

                // Notify user
                Notification::create([
                    'user_id' => $user->id,
                    'title' => '🎂 Selamat Ulang Tahun!',
                    'message' => "Selamat ulang tahun, {$user->name}! Kami memberikan voucher diskon {$birthdayBenefit->value}% khusus untuk Anda.",
                    'type' => 'birthday_bonus',
                    'url' => '/profile',
                    'is_read' => false,
                ]);

                $this->line("  ✓ Issued birthday voucher to {$user->name} ({$birthdayBenefit->value}% off)");
            } else {
                $this->line("  [DRY-RUN] Would issue birthday voucher to {$user->name} ({$birthdayBenefit->value}% off)");
            }

            $issued++;
        }

        $this->info("Done. Issued: {$issued}, Skipped: {$skipped}.");

        return self::SUCCESS;
    }
}
