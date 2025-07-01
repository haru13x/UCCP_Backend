<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoleController extends Model
{
    use HasFactory;
      public function index()
    {
        return Role::with('role_permissions')->get();
    }

    public function show($id)
    {
        return Role::with('permissions')->findOrFail($id);
    }
}
