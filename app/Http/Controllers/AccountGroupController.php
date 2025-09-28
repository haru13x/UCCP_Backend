<?php

namespace App\Http\Controllers;

use App\Models\AccountGroup;
use App\Models\AccountType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AccountGroupController extends Controller
{
    public function index(Request $request)
    {
        $query = AccountGroup::query();
        
        // Apply status filter
        $status = $request->query('status', 'all');
        if ($status === 'active') {
            $query->where('is_active', 1);
        } elseif ($status === 'inactive') {
            $query->where('is_active', 0);
        }
        // If status is 'all', no filter is applied
        
        // Apply name filter
        $search = $request->query('search');
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('code', 'LIKE', '%' . $search . '%')
                  ->orWhere('description', 'LIKE', '%' . $search . '%');
            });
        }
        
        $accountGroups = $query->get();
        
        return response()->json($accountGroups);
    }

    public function getGroups(Request $request)
    {
          $query = AccountGroup::query();
        
        // Apply status filter
        $status = $request->query('status', 'all');
        if ($status === 'active') {
            $query->where('is_active', 1);
        } elseif ($status === 'inactive') {
            $query->where('is_active', 0);
        }
        // If status is 'all', no filter is applied
             $search = $request->query('search');
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('code', 'LIKE', '%' . $search . '%')
                  ->orWhere('description', 'LIKE', '%' . $search . '%');
            });
        }
        
        $accountGroups = $query->get();
        
        return response()->json($accountGroups);
    }

    public function getTypesByGroup($groupId)
    {
        return response()->json(AccountType::where('group_id', $groupId)->where('is_active', 1)->get());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:20|unique:account_groups,code',
            'description' => 'required|string|max:255',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $accountGroup = AccountGroup::create([
                'code' => $request->code,
                'description' => $request->description,
                'created_by' => auth()->user()->name ?? 'system',
                'is_active' => $request->is_active ?? 1
            ]);

            return response()->json([
                'message' => 'Account group created successfully',
                'data' => $accountGroup
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create account group',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $accountGroup = AccountGroup::find($id);
        
        if (!$accountGroup) {
            return response()->json([
                'message' => 'Account group not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:20|unique:account_groups,code,' . $id,
            'description' => 'required|string|max:255',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $accountGroup->update([
                'code' => $request->code,
                'description' => $request->description,
                'is_active' => $request->is_active ?? $accountGroup->is_active
            ]);

            return response()->json([
                'message' => 'Account group updated successfully',
                'data' => $accountGroup->fresh()
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update account group',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
