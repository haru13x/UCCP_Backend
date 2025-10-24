<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegistrationOtp extends Model
{
    protected $table = 'registration_otps';

    protected $fillable = [
        'email',
        'otp',
        'registration_data',
        'expires_at'
    ];

    protected $casts = [
        'registration_data' => 'array',
        'expires_at' => 'datetime'
    ];
}