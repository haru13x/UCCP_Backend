<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable // ✅
{
    use HasFactory;

    protected $guarded = [];
    protected $with = ['details', 'role'];
    protected $table = 'users';

    public function details()
    {
        return $this->hasOne(UserDetails::class, 'user_id', 'id',);
    }
    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id', 'id');
    }
    public function registeredUsers()
    {
        return $this->hasMany(EventRegistration::class, 'user_id');
    }
    public function accountType(){
        return $this->hasMany(UserAccountType::class,'user_id', 'id')
        ->where('status',1);
    }
    // public function role_permission(){
    //     return $this->belongsTo(Role_Permissions::class,'user_id','id');
    // }
}
