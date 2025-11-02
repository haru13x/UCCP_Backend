<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventMode extends Model
{
    use HasFactory;
     protected $guarded = [];
    protected $table = 'event_modes';
    protected $with = ['eventGroup'];
    public function eventGroup(){
        return $this->belongsTo(AccountGroup::class,'account_group_id','id');
    }
     public function event()
    {
        return $this->belongsTo(Event::class, 'event_id', 'id');
    }
}
