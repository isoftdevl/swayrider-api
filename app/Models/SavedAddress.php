<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SavedAddress extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'label', 'custom_label', 'address',
        'latitude', 'longitude', 'contact_name', 'contact_phone',
        'instructions', 'is_default'
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'is_default' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Set this address as default and unset others
    public function setAsDefault()
    {
        // Unset all other defaults for this user
        self::where('user_id', $this->user_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);
        
        $this->update(['is_default' => true]);
    }
}
