<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventSponsors extends Model
{
    use HasFactory;
     protected $guarded = [];
    protected $table = 'event_sponsors';
}
