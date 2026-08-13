<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('sellers:backfill-store-slug')]
#[Description('Assign a unique store_slug to every seller account that is missing one so their storefront page works.')]
class BackfillStoreSlugs extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $sellers = User::where('is_seller', true)
            ->where(function ($q) {
                $q->whereNull('store_slug')->orWhere('store_slug', '');
            })
            ->orderBy('name')
            ->get();

        if ($sellers->isEmpty()) {
            $this->info('Tidak ada seller yang kekurangan store_slug.');

            return self::SUCCESS;
        }

        foreach ($sellers as $seller) {
            $baseName = $seller->store_name ?: $seller->name;
            $baseSlug = Str::slug($baseName);
            if (empty($baseSlug)) {
                $baseSlug = 'toko';
            }

            $storeSlug = $baseSlug.'-'.Str::lower(Str::random(4));
            while (User::where('store_slug', $storeSlug)->where('id', '!=', $seller->id)->exists()) {
                $storeSlug = $baseSlug.'-'.Str::lower(Str::random(4));
            }

            $seller->forceFill(['store_slug' => $storeSlug])->save();

            $this->info("{$seller->name} → {$storeSlug}");
        }

        $this->info("Selesai. {$sellers->count()} store_slug dibuatkan.");

        return self::SUCCESS;
    }
}
