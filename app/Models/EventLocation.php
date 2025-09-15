<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\ChurchLocation;

class EventLocation extends Model
{
    use HasFactory;

    protected $guarded = [];
    protected $table = 'event_locations';

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function location()
    {
        return $this->belongsTo(ChurchLocation::class, 'location_id');
    }
}