<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAccountType extends Model
{
    use HasFactory;
    protected $with = ['accountGroup', 'accountType'];
    protected $guarded = [];
    protected $table = 'user_account_type';
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
    public function accountGroup()
    {
        return  $this->belongsTo(AccountGroup::class, 'group_id', 'id');
    }
    public function accountType()
    {
        return $this->belongsTo(AccountType::class, 'account_type_id', 'id');
    }
}
