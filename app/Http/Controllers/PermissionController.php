<?php

namespace App\Http\Controllers;

use App\Models\Permissions;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PermissionController extends Controller
{
 public function index()
{
    $permissions = Permissions::with('group')->get()->groupBy(function ($item) {
        return optional($item->group)->name ?? 'Ungrouped';
    });

    return response()->json(
        $permissions->map(function ($perms, $groupName) {
            return [
                'group' => $groupName,
                'permissions' => $perms->values(),
            ];
        })->values()
    );
}


    public function store(Request $request)
{
    $request->validate([
        'name' => 'required|string',
        'slug' => 'required|string|unique:roles,slug',
        'description' => 'nullable|string',
        'permissions' => 'array',
    ]);

    DB::beginTransaction();
    try {
        $role = Role::create([
            'name' => $request->name,
            'slug' => $request->slug,
            'description' => $request->description,
            'status_id' => 1,
            'created_by' => $request->created_by,
        ]);

        if ($request->has('permissions')) {
            foreach ($request->permissions as $permId) {
                DB::table('role_permission')->insert([
                    'role_id' => $role->id,
                    'permission_id' => $permId,
                    'status_id' => 1,
                    'created_by' => $request->created_by,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        DB::commit();
        return response()->json(['message' => 'Role created successfully']);
    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json(['error' => $e->getMessage()], 500);
    }}
}
