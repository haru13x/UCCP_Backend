<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        $query = Role::with('role_permissions');

        $query->where('id', '!=', 1);
        // Apply status filter
        $status = $request->query('status', 'all');
        if ($status === 'active') {
            $query->where('status_id', 1);
        } elseif ($status === 'inactive') {
            $query->where('status_id', 0);
        }

        // If status is 'all', no filter is applied
        
        $roles = $query->get();
        
        return response()->json($roles);
    }

    public function show($id)
    {
        return Role::with('permissions')->findOrFail($id);
    }
}
