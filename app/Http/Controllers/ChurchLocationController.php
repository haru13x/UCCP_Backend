<?php

namespace App\Http\Controllers;

use App\Models\ChurchLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ChurchLocationController extends Controller
{
    public function index()
    {
        $churchLocations = ChurchLocation::
            where('status_id', 1)
            ->orderBy('created_at', 'desc')
            ->get();
        
        return response()->json($churchLocations);
    }

    public function getChurchLocations()
    {
               $churchLocations = ChurchLocation::where('status_id', 1)
            ->orderBy('created_at', 'desc')
            ->get();
        
        return response()->json($churchLocations);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $churchLocation = ChurchLocation::create([
                'name' => $request->name,
                'slug' => Str::slug($request->name),
                'description' => $request->description,
                'status_id' => $request->is_active ?? 1,
                'created_by' => auth()->id() ?? 1
            ]);

            $churchLocation->load('creator');

            return response()->json([
                'message' => 'Church location created successfully',
                'data' => $churchLocation
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create church location',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $churchLocation = ChurchLocation::with('creator')->find($id);
        
        if (!$churchLocation) {
            return response()->json([
                'message' => 'Church location not found'
            ], 404);
        }

        return response()->json($churchLocation);
    }

    public function update(Request $request, $id)
    {
        $churchLocation = ChurchLocation::find($id);
        
        if (!$churchLocation) {
            return response()->json([
                'message' => 'Church location not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $churchLocation->update([
                'name' => $request->name,
                'slug' => Str::slug($request->name),
                'description' => $request->description,
                'status_id' => $request->is_active ?? $churchLocation->status_id
            ]);

            $churchLocation->load('creator');

            return response()->json([
                'message' => 'Church location updated successfully',
                'data' => $churchLocation
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update church location',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        $churchLocation = ChurchLocation::find($id);
        
        if (!$churchLocation) {
            return response()->json([
                'message' => 'Church location not found'
            ], 404);
        }

        try {
            // Soft delete by setting status_id to 2 (inactive)
            $churchLocation->update(['status_id' => 2]);

            return response()->json([
                'message' => 'Church location deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete church location',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}