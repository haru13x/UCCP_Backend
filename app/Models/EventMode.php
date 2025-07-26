<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventMode extends Model
{
    use HasFactory;
     protected $guarded = [];
    protected $table = 'event_modes';
    
     public function eventType()
    {
        return $this->belongsTo(AccountType::class, 'account_type_id', 'id');
    }
    public function eventGroup(){
        return $this->belongsTo(AccountGroup::class,'account_group_id','id');
    }
     public function event()
    {
        return $this->belongsTo(Event::class, 'event_id', 'id');
    }
}
