<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $with = ['role_permissions'];
    protected $table = 'roles';
    public function role_permissions()
    {
        return $this->hasMany(Role_Permissions::class, 'role_id', 'id')->where('status_id', 1);
    }
}
