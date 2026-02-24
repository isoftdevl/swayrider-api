<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'user_type',
        'bank_name',
        'bank_code',
        'account_number',
        'account_name',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    // Polymorphic relation if we want to retrieve user via account, though simpler logic usually suffices
    public function user()
    {
        return $this->morphTo();
    }
}
