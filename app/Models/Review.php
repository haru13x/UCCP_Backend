<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Review extends Model
{
    use HasFactory;

    protected $guarded = [];
    protected $table = 'reviews';

    // Append 'is_mine' automatically when converting model to array/JSON
    protected $appends = ['is_mine'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    // Dynamic attribute: is_mine
    public function getIsMineAttribute()
    {
        $user = Auth::user();

        if (!$user) return false;
        return ($this->user_id === $user->id);
    }
}