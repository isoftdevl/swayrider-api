<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Rider extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'phone', 'password', 'profile_photo', 'status',
        'is_online', 'current_latitude', 'current_longitude', 'rating',
        'total_deliveries', 'total_earnings', 'bike_registration_number',
        'emergency_contact_name', 'emergency_contact_phone', 'company_id',
        'fcm_token', 'last_location_update',
        'email_verification_code', 'email_verification_expires_at', 
        'email_verified_at', 'phone_verified_at',
        'referral_code', 'referred_by'
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'is_online' => 'boolean',
        'current_latitude' => 'decimal:8',
        'current_longitude' => 'decimal:8',
        'rating' => 'decimal:2',
        'total_earnings' => 'decimal:2',
        'last_location_update' => 'datetime',
        'email_verification_expires_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
    ];

    protected $appends = ['kyc_status', 'wallet_balance', 'kyc_documents'];

    public function getKycStatusAttribute()
    {
        return $this->profile ? $this->profile->verification_status : 'pending';
    }

    public function getWalletBalanceAttribute()
    {
        return $this->wallet ? $this->wallet->balance : 0;
    }

    public function getKycDocumentsAttribute()
    {
        return $this->profile ? $this->profile->kyc_documents : null;
    }

    public function profile()
    {
        return $this->hasOne(RiderProfile::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function wallet()
    {
        return $this->morphOne(Wallet::class, 'owner');
    }
    
    public function deliveries()
    {
        return $this->hasMany(Delivery::class);
    }

    public function assignedDeliveries()
    {
        return $this->hasMany(Delivery::class, 'rider_id');
    }

    public function activeDelivery()
    {
        return $this->hasOne(Delivery::class)->whereIn('status', ['assigned', 'rider_accepted', 'picked_up', 'in_transit', 'arrived']);
    }

    public function locations()
    {
        return $this->hasMany(RiderLocation::class);
    }

    public function ratings()
    {
        return $this->hasMany(Rating::class);
    }

    // Referrals made by this rider
    public function referrals()
    {
        return $this->morphMany(Referral::class, 'referrer');
    }

    // Referral that brought this rider
    public function referredBy()
    {
        return $this->morphMany(Referral::class, 'referred');
    }

    // Generate unique referral code
    public static function generateReferralCode()
    {
        do {
            $code = 'SR' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
        } while (self::where('referral_code', $code)->exists());
        
        return $code;
    }
}
