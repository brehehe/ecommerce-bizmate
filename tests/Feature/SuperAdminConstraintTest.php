<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Setup roles
    Role::create(['name' => 'Super Admin']);
    Role::create(['name' => 'Admin']);

    // Create current logged in admin (which is a Super Admin)
    $this->admin = User::factory()->create(['is_active' => true]);
    $this->admin->assignRole('Super Admin');
});

test('cannot delete super admin if they are the only super admin', function () {
    // Since $this->admin is the only Super Admin
    $response = $this->actingAs($this->admin)->delete(route('admin.master-data.admins.destroy', $this->admin));

    $response->assertRedirect();
    $response->assertSessionHas('error', 'Super Admin tidak dapat dihapus karena harus tersisa minimal satu Super Admin.');
    $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
});

test('can delete super admin if there is another super admin', function () {
    $otherSuperAdmin = User::factory()->create(['is_active' => true]);
    $otherSuperAdmin->assignRole('Super Admin');

    $response = $this->actingAs($this->admin)->delete(route('admin.master-data.admins.destroy', $otherSuperAdmin));

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Admin berhasil dihapus.');
    $this->assertDatabaseMissing('users', ['id' => $otherSuperAdmin->id]);
});

test('cannot deactivate super admin if they are the only active super admin', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.master-data.admins.toggle-active', $this->admin));

    $response->assertRedirect();
    $response->assertSessionHas('error', 'Status Super Admin tidak dapat dinonaktifkan karena harus tersisa minimal satu Super Admin aktif.');
    $this->assertEquals(true, $this->admin->fresh()->is_active);
});

test('can deactivate super admin if there is another active super admin', function () {
    $otherSuperAdmin = User::factory()->create(['is_active' => true]);
    $otherSuperAdmin->assignRole('Super Admin');

    $response = $this->actingAs($this->admin)->post(route('admin.master-data.admins.toggle-active', $otherSuperAdmin));

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Status admin berhasil diubah.');
    $this->assertEquals(false, $otherSuperAdmin->fresh()->is_active);
});

test('cannot change role of super admin if they are the only super admin', function () {
    $response = $this->actingAs($this->admin)->put(route('admin.master-data.admins.update', $this->admin), [
        'name' => 'Super Admin Updated',
        'role' => 'Admin',
    ]);

    $response->assertSessionHasErrors(['role']);
    $this->assertTrue($this->admin->fresh()->hasRole('Super Admin'));
});

test('can change role of super admin if there is another super admin', function () {
    $otherSuperAdmin = User::factory()->create(['is_active' => true]);
    $otherSuperAdmin->assignRole('Super Admin');

    $response = $this->actingAs($this->admin)->put(route('admin.master-data.admins.update', $otherSuperAdmin), [
        'name' => 'Other Admin Updated',
        'role' => 'Admin',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Data admin berhasil diperbarui.');
    $this->assertTrue($otherSuperAdmin->fresh()->hasRole('Admin'));
    $this->assertFalse($otherSuperAdmin->fresh()->hasRole('Super Admin'));
});
