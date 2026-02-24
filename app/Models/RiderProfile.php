<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RiderProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'rider_id',
        'id_type',
        'id_number',
        'id_front_photo',
        'id_back_photo',
        'selfie_photo',
        'bike_registration_number',
        'bike_photo',
        'bike_papers',
        'police_clearance',
        'emergency_contact_name',
        'emergency_contact_phone',
        'address',
        'city',
        'state',
        'verification_status',
        'rejection_reason',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
    ];

    protected $appends = ['kyc_documents'];

    public function getKycDocumentsAttribute()
    {
        return [
            'id_front' => $this->id_front_photo ? asset($this->id_front_photo) : null,
            'id_back' => $this->id_back_photo ? asset($this->id_back_photo) : null,
            'selfie' => $this->selfie_photo ? asset($this->selfie_photo) : null,
            'bike_photo' => $this->bike_photo ? asset($this->bike_photo) : null,
            'bike_registration' => $this->bike_papers ? asset($this->bike_papers) : null,
             // Add other fields mapped to labels in frontend
             // Front end labels: id_front, id_back, selfie, bike_photo, bike_registration
             // Database: id_front_photo, id_back_photo, selfie_photo, bike_photo, bike_papers
        ];
    }
    
    public function rider()
    {
        return $this->belongsTo(Rider::class);
    }

    public function verifier()
    {
        return $this->belongsTo(Admin::class, 'verified_by');
    }
}
