<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            $user->referral_code = self::generateReferralCode();
        });
    }

    public static function generateReferralCode()
    {
        do {
            $code = 'SRU' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 5));
        } while (self::where('referral_code', $code)->exists());
        
        return $code;
    }

    protected $fillable = [
        'name', 'email', 'phone', 'password', 'profile_photo', 
        'status', 'fcm_token', 
        'email_verified_at', 'phone_verified_at',
        'referral_code', 'referred_by',
        'email_verification_code', 'email_verification_expires_at',
        'password_reset_code', 'password_reset_expires_at'
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'email_verification_expires_at' => 'datetime',
        'password_reset_expires_at' => 'datetime',
    ];

    protected $appends = ['total_deliveries', 'total_spent', 'wallet_balance'];

    public function getTotalDeliveriesAttribute()
    {
        return $this->deliveries()->count();
    }

    public function getTotalSpentAttribute()
    {
        return $this->deliveries()->where('status', 'delivered')->sum('total_price') ?? 0;
    }

    public function getWalletBalanceAttribute()
    {
        return $this->wallet ? $this->wallet->balance : 0;
    }

    public function wallet()
    {
        return $this->morphOne(Wallet::class, 'owner');
    }

    public function deliveries()
    {
        return $this->hasMany(Delivery::class);
    }

    // ... relations

    public function paymentMethods()
    {
        return $this->hasMany(PaymentMethod::class);
    }
    
    public function notifications()
    {
        return $this->morphMany(Notification::class, 'notifiable');
    }

    public function ratings()
    {
        return $this->hasMany(Rating::class);
    }

    // Referrals made by this user
    public function referrals()
    {
        return $this->morphMany(Referral::class, 'referrer');
    }

    // Referral that brought this user
    public function referredBy()
    {
        return $this->morphMany(Referral::class, 'referred');
    }
}
