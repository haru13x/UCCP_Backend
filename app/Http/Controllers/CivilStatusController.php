<?php

namespace App\Http\Controllers;

use App\Models\CivilStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CivilStatusController extends Controller
{
    public function index(Request $request)
    {
        $query = CivilStatus::query();

        // Status filter: active=1, inactive=2, all=no filter
        $status = $request->query('status', 'active');
        if ($status === 'active') {
            $query->where('status_id', 1);
        } elseif ($status === 'inactive') {
            $query->where('status_id', 2);
        }

        // Optional search by name or code
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('code', 'like', "%$search%")
                  ->orWhere('description', 'like', "%$search%");
            });
        }

        return response()->json($query->orderBy('name')->get());
    }

    public function show($id)
    {
        $civilStatus = CivilStatus::find($id);
        if (!$civilStatus) {
            return response()->json(['message' => 'Civil status not found'], 404);
        }
        return response()->json($civilStatus);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $civilStatus = CivilStatus::create([
            'name' => $request->name,
            'code' => $request->code,
            'description' => $request->description,
            'status_id' => ($request->is_active ?? 1) == 1 ? 1 : 2,
            'created_by' => auth()->id() ?? 1,
        ]);

        return response()->json([
            'message' => 'Civil status created successfully',
            'data' => $civilStatus,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $civilStatus = CivilStatus::find($id);
        if (!$civilStatus) {
            return response()->json(['message' => 'Civil status not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $civilStatus->update([
            'name' => $request->name,
            'code' => $request->code,
            'description' => $request->description,
            'status_id' => ($request->is_active ?? ($civilStatus->status_id == 1 ? 1 : 2)) == 1 ? 1 : 2,
        ]);

        return response()->json([
            'message' => 'Civil status updated successfully',
            'data' => $civilStatus,
        ], 200);
    }

    public function destroy($id)
    {
        $civilStatus = CivilStatus::find($id);
        if (!$civilStatus) {
            return response()->json(['message' => 'Civil status not found'], 404);
        }

        // Soft delete: set status_id to 2 (inactive)
        $civilStatus->update(['status_id' => 2]);

        return response()->json(['message' => 'Civil status deleted successfully'], 200);
    }
}