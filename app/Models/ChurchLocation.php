<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChurchLocation extends Model
{
    use HasFactory;
    
    protected $guarded = [];
    protected $table = 'church_location';
    
    protected $fillable = [
        'name',
        'slug',
        'description',
        'status_id',
        'created_by'
    ];
    
    // Relationship with User (creator)
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
