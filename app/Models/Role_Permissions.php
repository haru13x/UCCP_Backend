<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role_Permissions extends Model
{
      use HasFactory;
    protected $guarded = [];
    protected $with = ['permission'];
    protected $table = 'role_permission';
    
    public function permission()
    {
        return $this->belongsTo(Permissions::class, 'permission_id','id');
    }
}
