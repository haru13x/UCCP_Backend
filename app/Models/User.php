<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
     use HasFactory;

    protected $guarded = [];
    protected $with = ['details','role'];
    protected $table = 'users';

    public function details(){
        return $this->hasOne(UserDetails::class,'user_id','id',);
    }
    public function role(){
        return $this->belongsTo(Role::class,'role_id','id');
    }
}
