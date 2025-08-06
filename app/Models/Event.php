<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Event extends Model
{
    use HasFactory;

    protected $guarded = [];
    // protected $with = ['eventMode.eventType'];
    protected $table = 'events';
    protected $appends = ['is_registered', 'is_attended'];

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
    public function eventPrograms()
    {
        return $this->hasMany(EventPrograms::class);
    }
    public function eventsSponser()
    {
        return $this->hasMany(EventSponsors::class);
    }
    public function eventRegistrations()
    {
        return $this->hasMany(EventRegistration::class);
    }
    public function eventMode()
    {
        return $this->hasMany(EventMode::class, 'event_id', 'id')
            ->where('status_id', 1);
    }
    public function reviews()
    {
        return $this->hasMany(Review::class, 'event_id', 'id');
    }
}
