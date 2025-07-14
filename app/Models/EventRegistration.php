<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventRegistration extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $table = 'event_registrations';
    protected $with = ['details'];
    public function event()
    {
        return $this->belongsTo(Event::class);
    }
    public function details()
    {
        return $this->belongsTo(UserDetails::class, 'user_id', 'user_id');
    }
}
