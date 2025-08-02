<?php

namespace App\Http\Controllers;

use App\Models\Permissions;
use App\Models\Role;
use App\Models\Role_Permissions;
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
        }
    }

    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $role->update([
            'name' => $request->name,
            'slug' => $request->slug,
            'description' => $request->description,
        ]);

        $selectedPermissionIds = $request->permissions ?? [];

        // Get all current role_permission entries (active or inactive)
        $currentRolePermissions = Role_Permissions::where('role_id', $id)->get()->keyBy('permission_id');

        $allPermissionIds = Permissions::pluck('id')->toArray();

        foreach ($allPermissionIds as $permId) {
            $isSelected = in_array($permId, $selectedPermissionIds);
            $existing = $currentRolePermissions->get($permId);

            if ($isSelected) {
                if ($existing) {
                    if ($existing->status_id != 1) {
                        $existing->update(['status_id' => 1]);
                    }
                } else {
                    // Only insert if it doesn't exist
                    Role_Permissions::create([
                        'role_id' => $id,
                        'permission_id' => $permId,
                        'status_id' => 1,
                        'created_by' => $request->created_by ?? auth()->id(),
                    ]);
                }
            } else {
                if ($existing && $existing->status_id == 1) {
                    $existing->update(['status_id' => 2]);
                }
            }
        }

        return response()->json(['message' => 'Role and permissions updated']);
    }
}
