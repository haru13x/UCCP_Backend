<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CategoryRating extends Model
{
    use HasFactory;
     protected $guarded = [];
    protected $table = 'category_rating';
    public $timestamps = false;
}
