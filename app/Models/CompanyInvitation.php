<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyInvitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id', 'rider_id', 'email', 'phone', 
        'token', 'status', 'expires_at'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function rider()
    {
        return $this->belongsTo(Rider::class);
    }
}
