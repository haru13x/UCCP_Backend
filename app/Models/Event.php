<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use App\Models\ChurchLocation;

class Event extends Model
{
    use HasFactory;

    protected $guarded = [];
    // protected $with = ['eventMode.eventType'];
    protected $table = 'events';
    protected $appends = ['is_registered', 'is_attended'];
    protected $with = ['locations', 'eventLocations'];
    public function getIsAttendedAttribute()
    {
        $user = Auth::user();

        if (!$user) return false;

        return (int)$this->eventRegistrations()->where('user_id', $user->id)->where('is_attend', 1)->exists();
    }
    public function getIsRegisteredAttribute()
    {
        $user = Auth::user();

        if (!$user) return false;

        return (int)$this->eventRegistrations()->where('user_id', $user->id)->exists();
    }

    public function eventRegistrations()
    {
        return $this->hasMany(EventRegistration::class);
    }
    public function eventMode()
    {
        return $this->hasOne(EventMode::class, 'event_id', 'id')
            ->where('status_id', 1);
    }
    public function reviews()
    {
        return $this->hasMany(Review::class, 'event_id', 'id');
    }
    public function locations()
    {
        return $this->belongsToMany(ChurchLocation::class, 'event_locations', 'event_id', 'location_id');
    }

    public function eventLocations()
    {
        return $this->hasMany(EventLocation::class, 'event_id', 'id');
    }
}
