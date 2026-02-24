<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Company extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'phone', 'password', 'logo', 'cac_number', 
        'cac_document', 'tax_id', 'address', 'city', 'state', 
        'status', 'total_riders', 'commission_rate', 'is_verified', 
        'verification_documents', 'contact_person_name', 'contact_person_phone'
    ];

    protected $hidden = [
        'password', 'remember_token', 'verification_documents'
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'verification_documents' => 'array',
        'commission_rate' => 'decimal:2',
    ];

    public function riders()
    {
        return $this->hasMany(Rider::class);
    }

    public function invitations()
    {
        return $this->hasMany(CompanyInvitation::class);
    }

    public function wallet()
    {
        return $this->morphOne(Wallet::class, 'owner');
    }

    public function withdrawals()
    {
        return $this->morphMany(Withdrawal::class, 'withdrawable');
    }
}
