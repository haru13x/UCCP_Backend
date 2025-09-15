<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserDetails extends Model
{
    use HasFactory;
    protected $with = ['sex', 'churchLocation'];
    protected $guarded = [];
    protected $table = 'user_details';
    
    public function sex(){
        return $this->belongsTo(Sex::class,'sex_id','id');
    }
    
    public function churchLocation(){
        return $this->belongsTo(ChurchLocation::class,'church_location_id','id');
    }
}
