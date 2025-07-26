<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountGroup extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $table = 'account_groups';

     public function accountTypes()
    {
        return $this->hasMany(AccountType::class, 'group_id');
    }
}
