<?php

namespace App\Http\Controllers;

use App\Models\AccountType;
use App\Models\AccountGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AccountTypeController extends Controller
{
    public function index()
    {
        $accountTypes = AccountType::with('group')
            ->where('is_active', 1)
            ->get();
        
        return response()->json($accountTypes);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'group_id' => 'required|integer|exists:account_groups,id',
            'code' => 'required|string|max:30|unique:account_types,code',
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
            $accountType = AccountType::create([
                'group_id' => $request->group_id,
                'code' => $request->code,
                'description' => $request->description,
                'is_active' => $request->is_active ?? 1
            ]);

            $accountType->load('group');

            return response()->json([
                'message' => 'Account type created successfully',
                'data' => $accountType
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create account type',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $accountType = AccountType::find($id);
        
        if (!$accountType) {
            return response()->json([
                'message' => 'Account type not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'group_id' => 'required|integer|exists:account_groups,id',
            'code' => 'required|string|max:20|unique:account_types,code,' . $id,
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
            $accountType->update([
                'group_id' => $request->group_id,
                'code' => $request->code,
                'description' => $request->description,
                'is_active' => $request->is_active ?? $accountType->is_active
            ]);

            $accountType->load('group');

            return response()->json([
                'message' => 'Account type updated successfully',
                'data' => $accountType
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update account type',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $accountType = AccountType::with('group')->find($id);
        
        if (!$accountType) {
            return response()->json([
                'message' => 'Account type not found'
            ], 404);
        }

        return response()->json($accountType);
    }

    public function destroy($id)
    {
        $accountType = AccountType::find($id);
        
        if (!$accountType) {
            return response()->json([
                'message' => 'Account type not found'
            ], 404);
        }

        try {
            $accountType->update(['is_active' => 0]);

            return response()->json([
                'message' => 'Account type deactivated successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to deactivate account type',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}