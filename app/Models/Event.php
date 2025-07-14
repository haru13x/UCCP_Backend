<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    protected $guarded = [];
    protected $table = 'events';

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
}
