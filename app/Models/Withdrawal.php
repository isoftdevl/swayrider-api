<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Withdrawal extends Model
{
    use HasFactory;

    protected $fillable = [
        'withdrawable_type',
        'withdrawable_id',
        'wallet_id',
        'amount',
        'bank_name',
        'account_number',
        'account_name',
        'status',
        'reference',
        'processed_by',
        'processed_at',
        'rejection_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    protected $appends = ['rider'];

    public function withdrawable()
    {
        return $this->morphTo();
    }

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public function processor()
    {
        return $this->belongsTo(Admin::class, 'processed_by');
    }

    // Accessor for backward compatibility with frontend
    public function getRiderAttribute()
    {
        return $this->withdrawable_type === 'App\Models\Rider' ? $this->withdrawable : null;
    }
}
