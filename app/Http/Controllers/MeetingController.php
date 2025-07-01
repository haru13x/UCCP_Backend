<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use Carbon\Carbon;
use Illuminate\Http\Request;

class MeetingController extends Controller
{

 public function today()
    {
        return Meeting::whereDate('start_date', Carbon::today())->get();
    }

    public function upcoming()
    {
        return Meeting::whereDate('start_date', '>', Carbon::today())->get();
    }
    public function index()
    {
        $events = Meeting::orderBy('start_date', 'desc')->get();
        return response()->json($events);
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string',
            'start_date' => 'nullable|date',
            'start_time' => 'nullable',
            'end_date' => 'nullable|date',
            'end_time' => 'nullable',
            'category' => 'nullable|string',
            'organizer' => 'nullable|string',
            'contact' => 'nullable|string',
            'attendees' => 'nullable|integer',
            'venue' => 'nullable|string',
            'address' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'description' => 'nullable|string',
        ]);

        $validated['created_by'] = auth()->id();
        $validated['status_id'] = 1;

        $event = Meeting::create($validated);
        return response()->json($event, 201);
    }
    public function update(Request $request)
    {
        try {
            $id = $request->id;

            $validated = $request->validate([
                'title' => 'required|string',
                'start_date' => 'nullable|date',
                'start_time' => 'nullable',

                'end_date' => 'nullable|date',
                'end_time' => 'nullable',
                'category' => 'nullable|string',
                'organizer' => 'nullable|string',
                'contact' => 'nullable|string',
                'attendees' => 'nullable|integer',
                'venue' => 'nullable|string',
                'address' => 'nullable|string',
                'latitude' => 'nullable|numeric',
                'longitude' => 'nullable|numeric',
                'description' => 'nullable|string',
            ]);
            $event = Meeting::where('id', $id)->update($validated);
            return response()->json($event, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
