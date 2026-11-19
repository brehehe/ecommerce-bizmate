<?php

use App\Models\MembershipLevel;
use App\Models\RestoProfile;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.membership_enabled' => false]);
    config(['app.is_foodpick' => true]);

    if (! Role::where('name', 'Super Admin')->exists()) {
        Role::create(['name' => 'Super Admin']);
    }
    if (! Role::where('name', 'Admin')->exists()) {
        Role::create(['name' => 'Admin']);
    }
    if (! Role::where('name', 'Seller')->exists()) {
        Role::create(['name' => 'Seller']);
    }
    if (! Role::where('name', 'Customer')->exists()) {
        Role::create(['name' => 'Customer']);
    }

    MembershipLevel::create([
        'name' => 'Bronze',
        'slug' => 'bronze',
        'min_spend' => 0,
        'min_transactions' => 0,
        'order' => 1,
        'is_active' => true,
    ]);

    $this->admin = User::create([
        'name' => 'Platform Admin',
        'email' => 'admin@platform.com',
        'password' => bcrypt('password'),
    ]);
    $this->admin->assignRole('Super Admin');

    $this->candidate = User::create([
        'name' => 'Pak Slamet',
        'email' => 'slamet@bebek.com',
        'password' => bcrypt('password'),
        'phone_number' => '081298765432',
        'is_seller' => false,
        'store_name' => 'Bebek Slamet Mantap',
        'store_slug' => 'bebek-slamet',
    ]);
    $this->candidate->assignRole('Customer');

    $this->restoProfile = RestoProfile::create([
        'user_id' => $this->candidate->id,
        'resto_name' => 'Bebek Slamet Mantap',
        'slug' => 'bebek-slamet',
        'culinary_category' => 'Aneka Ayam & Bebek',
        'address' => 'Jl. Kawi No. 20',
        'latitude' => -7.980000,
        'longitude' => 112.630000,
        'avg_prep_time_minutes' => 20,
        'allow_pickup' => true,
        'allow_delivery' => true,
        'max_delivery_radius_km' => 7.0,
        'is_open_now' => false,
        'approval_status' => 'pending_approval',
    ]);
});

test('admin can view resto applications list with counts', function () {
    $this->actingAs($this->admin);

    $response = $this->get('/admin/restos');
    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Restos/Index')
        ->where('counts.pending', 1)
        ->has('restos.data', 1)
        ->where('restos.data.0.resto_name', 'Bebek Slamet Mantap')
    );
});

test('admin can approve pending resto and grant seller role', function () {
    $this->actingAs($this->admin);

    expect($this->restoProfile->approval_status)->toBe('pending_approval');
    expect($this->candidate->is_seller)->toBeFalse();

    $response = $this->post("/admin/restos/{$this->restoProfile->id}/approve");
    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->restoProfile->refresh();
    $this->candidate->refresh();

    expect($this->restoProfile->approval_status)->toBe('approved');
    expect($this->restoProfile->is_open_now)->toBeTrue();
    expect($this->candidate->is_seller)->toBeTrue();
    expect($this->candidate->hasRole('Seller'))->toBeTrue();
});

test('admin can reject resto application', function () {
    $this->actingAs($this->admin);

    $response = $this->post("/admin/restos/{$this->restoProfile->id}/reject");
    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->restoProfile->refresh();
    expect($this->restoProfile->approval_status)->toBe('rejected');
});

test('admin can suspend an active resto and toggle open status', function () {
    $this->actingAs($this->admin);

    $this->restoProfile->update(['approval_status' => 'approved', 'is_open_now' => true]);

    // Suspend
    $suspendResponse = $this->post("/admin/restos/{$this->restoProfile->id}/suspend");
    $suspendResponse->assertRedirect();

    $this->restoProfile->refresh();
    expect($this->restoProfile->approval_status)->toBe('suspended');
    expect($this->restoProfile->is_open_now)->toBeFalse();

    // Toggle open
    $toggleResponse = $this->post("/admin/restos/{$this->restoProfile->id}/toggle-open");
    $toggleResponse->assertRedirect();

    $this->restoProfile->refresh();
    expect($this->restoProfile->is_open_now)->toBeTrue();
});

test('non-admin user cannot access resto approval actions', function () {
    config(['app.is_seller' => true]);
    $this->candidate->removeRole('Customer');
    $this->candidate->assignRole('Seller');
    $this->candidate->update(['is_seller' => true]);

    $this->actingAs($this->candidate);

    $response = $this->get('/admin/restos');
    $response->assertStatus(403);

    $approveResponse = $this->post("/admin/restos/{$this->restoProfile->id}/approve");
    $approveResponse->assertStatus(403);
});

test('sales report shares culinary fulfillment breakdown in foodpick mode', function () {
    $this->actingAs($this->admin);

    // Create dine_in transaction
    Transaction::create([
        'user_id' => $this->admin->id,
        'resto_id' => $this->candidate->id,
        'transaction_number' => 'TRX-REPORT-DINEIN',
        'total_amount' => 45000,
        'shipping_cost' => 0,
        'shipping_courier' => 'self_pickup',
        'fulfillment_type' => 'dine_in',
        'pickup_type' => 'dine_in',
        'status' => 'selesai',
        'created_at' => now(),
    ]);

    $response = $this->get('/admin/reports/sales');
    $response->assertStatus(200);
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Reports/Sales')
        ->has('culinaryFulfillment')
        ->where('culinaryFulfillment.dine_in', 1)
    );
});
