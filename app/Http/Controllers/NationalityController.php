<?php

namespace App\Http\Controllers;

use App\Models\Nationality;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NationalityController extends Controller
{
    public function index(Request $request)
    {
        $query = Nationality::query();

        // Status filter: active=1, inactive=2, all=no filter
        $status = $request->query('status', 'active');
        if ($status === 'active') {
            $query->where('status_id', 1);
        } elseif ($status === 'inactive') {
            $query->where('status_id', 2);
        }

        // Optional search by name, code, description
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
        $nationality = Nationality::find($id);
        if (!$nationality) {
            return response()->json(['message' => 'Nationality not found'], 404);
        }
        return response()->json($nationality);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:10',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $nationality = Nationality::create([
            'name' => $request->name,
            'code' => $request->code,
            'description' => $request->description,
            'status_id' => ($request->is_active ?? 1) == 1 ? 1 : 2,
            'created_by' => auth()->id() ?? 1,
        ]);

        return response()->json([
            'message' => 'Nationality created successfully',
            'data' => $nationality,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $nationality = Nationality::find($id);
        if (!$nationality) {
            return response()->json(['message' => 'Nationality not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:10',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $nationality->update([
            'name' => $request->name,
            'code' => $request->code,
            'description' => $request->description,
            'status_id' => ($request->is_active ?? ($nationality->status_id == 1 ? 1 : 2)) == 1 ? 1 : 2,
        ]);

        return response()->json([
            'message' => 'Nationality updated successfully',
            'data' => $nationality,
        ], 200);
    }

    public function destroy($id)
    {
        $nationality = Nationality::find($id);
        if (!$nationality) {
            return response()->json(['message' => 'Nationality not found'], 404);
        }

        // Soft delete: set status_id to 2 (inactive)
        $nationality->update(['status_id' => 2]);

        return response()->json(['message' => 'Nationality deleted successfully'], 200);
    }
}