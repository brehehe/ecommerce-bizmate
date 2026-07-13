<?php

namespace App\Models;

use App\Notifications\QueuedResetPassword;
use App\Notifications\QueuedVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'is_active', 'last_active_at', 'coins_balance', 'avatar', 'phone_number', 'gender', 'birth_date'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, HasUuids, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_active_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    public function customerAddresses()
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function customerBankAccounts()
    {
        return $this->hasMany(CustomerBankAccount::class);
    }

    public function coinHistories()
    {
        return $this->hasMany(CoinHistory::class)->latest();
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    // ── Membership relationships ─────────────────────────────

    public function membership()
    {
        return $this->hasOne(CustomerMembership::class);
    }

    public function membershipHistories()
    {
        return $this->hasMany(MembershipHistory::class)->latest();
    }

    public function membershipPoints()
    {
        return $this->hasMany(MembershipPoint::class)->latest();
    }

    public function membershipCashbacks()
    {
        return $this->hasMany(MembershipCashback::class)->latest();
    }

    public function membershipVouchers()
    {
        return $this->hasMany(MembershipVoucher::class)->latest();
    }

    public function activeMembershipVouchers()
    {
        return $this->hasMany(MembershipVoucher::class)->active();
    }

    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new QueuedResetPassword($token));
    }

    /**
     * Send the email verification notification.
     *
     * @return void
     */
    public function sendEmailVerificationNotification()
    {
        $this->notify(new QueuedVerifyEmail);
    }
}
