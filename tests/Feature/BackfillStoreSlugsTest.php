<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('backfill command assigns a store_slug to sellers missing one', function () {
    $seller = User::factory()->create([
        'name' => 'Kokoh Yanuar',
        'is_seller' => true,
        'store_name' => 'Kokoh Yanuar',
        'store_slug' => null,
    ]);
    User::factory()->create([
        'name' => 'Budi',
        'is_seller' => true,
        'store_slug' => 'budi-abc1',
    ]);

    $this->artisan('sellers:backfill-store-slug')->assertSuccessful();

    $seller->refresh();
    expect($seller->store_slug)->not->toBeNull();
    expect($seller->store_slug)->toStartWith('kokoh-yanuar-');
});

test('backfill command produces unique store slugs', function () {
    $first = User::factory()->create([
        'name' => 'Kokoh Yanuar',
        'is_seller' => true,
        'store_slug' => 'kokoh-yanuar-aaaa',
    ]);
    $second = User::factory()->create([
        'name' => 'Kokoh Yanuar',
        'is_seller' => true,
        'store_slug' => null,
    ]);

    $this->artisan('sellers:backfill-store-slug')->assertSuccessful();

    $second->refresh();
    expect($second->store_slug)->not->toBe($first->store_slug);
});

test('backfill command skips sellers that already have a store_slug', function () {
    $seller = User::factory()->create([
        'name' => 'Hasbi',
        'is_seller' => true,
        'store_slug' => 'hasbi-gkqy',
    ]);

    $this->artisan('sellers:backfill-store-slug')
        ->assertSuccessful()
        ->expectsOutputToContain('Tidak ada seller yang kekurangan store_slug.');

    expect($seller->refresh()->store_slug)->toBe('hasbi-gkqy');
});
