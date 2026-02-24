<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Referral extends Model
{
    use HasFactory;

    protected $fillable = [
        'referrer_type', 'referrer_id', 'referred_type', 'referred_id',
        'referral_code', 'reward_amount', 'reward_claimed', 'reward_claimed_at',
        'condition_met', 'condition_met_at'
    ];

    protected $casts = [
        'reward_amount' => 'decimal:2',
        'reward_claimed' => 'boolean',
        'reward_claimed_at' => 'datetime',
        'condition_met' => 'boolean',
        'condition_met_at' => 'datetime',
    ];

    // Polymorphic relationships
    public function referrer()
    {
        return $this->morphTo();
    }

    public function referred()
    {
        return $this->morphTo();
    }

    // Check if referral is qualified (referred user completed first delivery)
    public function isQualified()
    {
        return $this->condition_met;
    }

    // Check if reward has been claimed
    public function isClaimed()
    {
        return $this->reward_claimed;
    }

    // Mark referral as qualified
    public function markAsQualified()
    {
        $this->update([
            'condition_met' => true,
            'condition_met_at' => now()
        ]);
    }

    // Claim reward
    public function claimReward()
    {
        if (!$this->isQualified() || $this->isClaimed()) {
            return false;
        }

        $this->update([
            'reward_claimed' => true,
            'reward_claimed_at' => now()
        ]);

        return true;
    }
}
