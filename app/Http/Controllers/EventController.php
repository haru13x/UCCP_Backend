<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventController extends Controller
{


    public function index()
    {
        $events = Event::with(['eventPrograms','eventsSponser'])->orderBy('start_date', 'desc')->get();
        return response()->json($events);
    }
   public function store(Request $request)
{
    try {
        DB::connection('mysql')->beginTransaction();

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
            'sponsors' => 'nullable|array',
            'programs' => 'nullable|array',
        ]);

        $programs = $validated['programs'] ?? [];
        $sponsors = $validated['sponsors'] ?? [];

        unset($validated['programs'], $validated['sponsors']);

        $validated['created_by'] = auth()->id();
        $validated['status_id'] = 1;

        // Create the event
        $event = Event::create($validated);

        // Save sponsors
        foreach ($sponsors as $row) {
            $event->eventsSponser()->create([
                'name' => $row['name'],
                'donated' => $row['donated'] ?? null,
                'logo' => $row['logo'] ?? null,
                'contact_person' => $row['contact_person'] ?? null,
                'created_at' => now(),
            ]);
        }

        // Save programs
        foreach ($programs as $row) {
            $event->eventPrograms()->create([
                'start_time' => $row['start_time'],
                'end_time' => $row['end_time'],
                'activity' => $row['activity'],
                'speaker' => $row['speaker'],
                'created_at' => now(),
            ]);
        }

        DB::connection('mysql')->commit();

        return response()->json($event, 201);
    } catch (\Throwable $e) {
        DB::connection('mysql')->rollback();
        return response()->json(['error' => $e->getMessage()], 500);
    }
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
            $event = Event::where('id', $id)->update($validated);
            return response()->json($event, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    public function today()
    {
        return Event::whereDate('start_date', Carbon::today())->get();
    }

    public function upcoming()
    {
        return Event::whereDate('start_date', '>', Carbon::today())->get();
    }
}
