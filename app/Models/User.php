<?php

namespace App\Models;

use App\Notifications\QueuedResetPassword;
use App\Notifications\QueuedVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Crypt;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'email_verified_at', 'is_active', 'is_seller', 'store_name', 'store_slug', 'store_logo', 'store_description', 'last_active_at', 'coins_balance', 'avatar', 'phone_number', 'gender', 'birth_date', 'padelgigs_user_id', 'padelgigs_access_token', 'padelgigs_refresh_token', 'padelgigs_token_expires_at'])]
#[Hidden(['password', 'remember_token', 'padelgigs_access_token', 'padelgigs_refresh_token'])]
class User extends Authenticatable
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
            'is_seller' => 'boolean',
            'padelgigs_token_expires_at' => 'datetime',
        ];
    }

    /**
     * Accessor & Mutator for encrypted PadelGigs Access Token.
     */
    protected function padelgigsAccessToken(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Crypt::decryptString($value) : null,
            set: fn (?string $value) => $value ? Crypt::encryptString($value) : null,
        );
    }

    /**
     * Accessor & Mutator for encrypted PadelGigs Refresh Token.
     */
    protected function padelgigsRefreshToken(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Crypt::decryptString($value) : null,
            set: fn (?string $value) => $value ? Crypt::encryptString($value) : null,
        );
    }

    public function isLinkedToPadelgigs(): bool
    {
        return $this->padelgigs_user_id !== null;
    }

    public function hasLocalPassword(): bool
    {
        return $this->password !== null;
    }

    public function isPadelgigsTokenExpired(): bool
    {
        if (! $this->padelgigs_token_expires_at) {
            return true;
        }

        return $this->padelgigs_token_expires_at->subMinutes(5)->isPast();
    }

    public function products()
    {
        return $this->hasMany(Product::class);
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

    public function adWallet()
    {
        return $this->hasOne(SellerAdWallet::class);
    }

    public function adTransactions()
    {
        return $this->hasMany(SellerAdTransaction::class)->latest();
    }

    public function productAds()
    {
        return $this->hasMany(ProductAd::class)->latest();
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
