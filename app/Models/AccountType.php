<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountType extends Model
{
    use HasFactory;
     protected $guarded = [];
    protected $table = 'account_types';
      public function group()
    {
        return $this->belongsTo(AccountGroup::class, 'group_id');
    }

}
