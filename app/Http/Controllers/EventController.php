<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EventController extends Controller
{


    public function index()
    {
        $events = Event::with(['eventPrograms', 'eventsSponser'])->orderBy('start_date', 'desc')->get();
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
                'image' => 'nullable|image|max:5000',
            ]);

            // Extract from request instead of $validated
            $programs = $request->input('programs', []);
            $sponsors = $request->input('sponsors', []);

            // Remove if accidentally included
            unset($validated['programs'], $validated['sponsors']);
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $filename = time() . '.' . $file->getClientOriginalExtension();
                $destinationPath = public_path('storage/event-images'); // public/storage/event-images

                // Ensure the directory exists
                if (!file_exists($destinationPath)) {
                    mkdir($destinationPath, 0755, true);
                }

                $file->move($destinationPath, $filename);

                // Save path relative to public or storage
                $validated['image'] = 'event-images/' . $filename;
            }


            $validated['status_id'] = 1;

            $event = Event::create($validated);
            $qrController = new QRCodeController();
             $qrController->generate($event->id);
            // (optional) handle $programs and $sponsors here



            // Save sponsors
            // foreach ($sponsors as $row) {
            //     $event->eventsSponser()->create([
            //         'name' => $row['name'],
            //         'donated' => $row['donated'] ?? null,
            //         'logo' => $row['logo'] ?? null,
            //         'contact_person' => $row['contact_person'] ?? null,
            //         'created_at' => now(),
            //     ]);
            // }

            // // Save programs
            // foreach ($programs as $row) {
            //     $event->eventPrograms()->create([
            //         'start_time' => $row['start_time'],
            //         'end_time' => $row['end_time'],
            //         'activity' => $row['activity'],
            //         'speaker' => $row['speaker'],
            //         'created_at' => now(),
            //     ]);
            // }

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
                'image' => 'nullable|file|image|max:5048', // ⬅️ validate image
            ]);

            // Handle new image upload if exists
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $filename = time() . '.' . $file->getClientOriginalExtension();
                $destinationPath = public_path('storage/event-images'); // public/storage/event-images

                // Ensure the directory exists
                if (!file_exists($destinationPath)) {
                    mkdir($destinationPath, 0755, true);
                }

                $file->move($destinationPath, $filename);

                // Save path relative to public or storage
                $validated['image'] = 'event-images/' . $filename;
            }


            $event = Event::findOrFail($id);
            $event->update($validated);

            return response()->json($event, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function register($id){
        $event = Event::findOrFail($id);
        return response()->json([
            'message' => 'Event registration successful',
            'event' => $event
        ]);
    }



    public function today()
    {
        return Event::whereDate('start_date', Carbon::today())->get();
    }

    public function upcoming()
    {
        return Event::whereDate('start_date', '>', Carbon::today())->get();
    }
     public function past(Request $request)
    {
        // return response()->json($request->user->id);
        return Event::whereDate('start_date', '<' ,Carbon::today())->get();
    }
}
