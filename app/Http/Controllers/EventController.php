<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventMode;
use App\Models\EventRegistration;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class EventController extends Controller
{
    // EventController.php
    public function scanEvent(Request $request)
    {
        $barcode = $request->barcode;
        $event = Event::where('barcode', $barcode)->first();
        return response()->json($event, 200);
    }


    public function index(Request $request)
    {

        $events = Event::orderBy('start_date', 'desc')
            ->get()
            ->map(function ($event) {
                // Flatten event_types from eventMode
                $eventTypes = $event->eventMode
                    ->pluck('eventType')
                    ->filter() // remove nulls
                    ->values();

                // Add flattened event_types to event
                $event->event_types = $eventTypes;

                return $event;
            });

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
            $participants = json_decode($request->input('participants', '[]'), true);

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
            $validated['created_by'] = $request->user->id;
            $event = Event::create($validated);

            foreach ($participants as $participantId) {
                EventMode::create([
                    'account_type_id' => $participantId,
                    'event_id' => $event->id,
                    'status_id' => 1,
                    'account_group_id' => $request->account_group_id,
                    'created_by' => $request->user->id,
                ]);
            }


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
        DB::beginTransaction();

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

                'venue' => 'nullable|string',
                'address' => 'nullable|string',
                'latitude' => 'nullable|numeric',
                'longitude' => 'nullable|numeric',
                'description' => 'nullable|string',
                'image' => 'nullable|file|image|max:5048',

            ]);

            // Handle image upload if exists
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $filename = time() . '.' . $file->getClientOriginalExtension();
                $destinationPath = public_path('storage/event-images');

                if (!file_exists($destinationPath)) {
                    mkdir($destinationPath, 0755, true);
                }

                $file->move($destinationPath, $filename);
                $validated['image'] = 'event-images/' . $filename;
            }

            // Update event
            $event = Event::findOrFail($id);
            $event->update($validated);

            // Update event modes (participants)
            $participantIds = $request->participants ?? [];

            // If it's a string (like "[1,2,3]"), convert it to array
            if (is_string($participantIds)) {
                $participantIds = json_decode($participantIds, true);
            }

            if (!is_array($participantIds)) {
                return response()->json(['error' => 'Invalid participant IDs format'], 400);
            }

            $existingModes = EventMode::where('event_id', $event->id)->get();
            $existingModeIds = $existingModes->pluck('account_type_id')->toArray();

            // Deactivate or keep active existing modes
            foreach ($existingModes as $mode) {
                if (in_array($mode->account_type_id, $participantIds)) {
                    $mode->update(['status_id' => 1]); // active
                } else {
                    $mode->update(['status_id' => 2]); // inactive
                }
            }

            // Add new modes
            foreach ($participantIds as $accountTypeId) {
                if (!in_array($accountTypeId, $existingModeIds)) {

                    EventMode::create([
                        'event_id' => $event->id,
                        'account_type_id' => (int)$accountTypeId,
                        'account_group_id' => (int)$request->account_group_id,
                        'status_id' => 1,
                        'created_by' => auth()->id() ?? 1,
                        'created_at' => now(),
                    ]);
                }
            }




            DB::commit();
            return response()->json($event, 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function eventRegisteration(Request $request, $id)
    {
        $event = Event::findOrFail($id);


        $registration = $event->eventRegistrations()->updateOrcreate(
            [
                'user_id' => $request->user->id,
                'event_id' => $event->id,
            ],
            [

                'user_id' => $request->user->id,
                'event_id' => $event->id,
                'statusId' => 1,
                'registered_time' => now(),
                'created_by' => $request->user->id,
                'time_in' => now()->toTimeString(),
            ]
        );
        return response()->json([
            'message' => 'Event registration successful',
            'event' => $event,
        ], 200);
    }
    public function eventMultipleRegisteration(Request $request)
    {
        $event = Event::findOrFail($request->event_id);
        $users = $request->input('users', []);
        foreach ($users as $row) {
            $registration = $event->eventRegistrations()->updateOrcreate(
                [
                    'user_id' => $row,
                    'event_id' => $event->id,
                ],
                [

                    'user_id' => $row,
                    'event_id' => $event->id,
                    'statusId' => 1,
                    'registered_time' => now(),
                    'created_by' => $request->user->id,
                    'time_in' => now()->toTimeString(),
                ]
            );
        }

        return response()->json([
            'message' => 'Event registration successful',
            'event' => $event,
        ]);
    }
    public function getEventRegisteredUsers(Request $request, $id)
    {
        try {
            // Find event or fail
            $query = EventRegistration::with('details', 'event')
                ->where('event_id', $id);

            // If there's a search query
            $search = urldecode($request->search ?? '');

            if ($search) {

                $query->whereHas('details', function ($q) use ($search) {
                    $q->where('first_name', 'like', '%' . $search . '%')
                        ->orWhere('last_name', 'like', '%' . $search . '%')
                        ->orWhere('user_id', 'like', '%' . $search . '%');
                });
            }
            $query->get();
            // Pagination (default 10 per page)
            $registeredUsers = $query->paginate(5);

            return response()->json([
                'registered_users' => $registeredUsers
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }
    }
    public function isRegistered(Request $request, $id)
    {
        $isRegistered = EventRegistration::where('user_id', $request->user->id)
            ->where('event_id', $id)->first();
        if ($isRegistered) {
            return response()->json(true, 200);
        } else {
            return response()->json(false, 200);
        }
    }
    public function list(Request $request, $type = null)
    {
        $user = $request->user;
        $userId = $user->id;

        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if ($user->role_id != 1) {
            $userAccountTypeIds = $user->accountType->pluck('account_type_id');

            $events = Event::whereHas('eventMode', function ($query) use ($userAccountTypeIds) {
                $query->whereIn('account_type_id', $userAccountTypeIds);
            })
                ->whereDoesntHave('eventRegistrations', function ($q) use ($userId) {
                    $q->where('user_id', $userId); // 👈 excludes events already registered by user
                });
        } else {
            $events = Event::query();
        }

        if ($request->has('search')) {
            $search = $request->search;
            $events->where('title', 'LIKE', "%" . $search . "%");
        }

        if ($type === 'today') {
            $events->whereDate('start_date', Carbon::today());
        } elseif ($type === 'upcoming') {
            $events->whereDate('start_date', '>', Carbon::today());
        } elseif ($type === 'past') {
            $events->whereDate('start_date', '<', Carbon::today());
        }

        return $events->with('eventMode')->get();
    }

    public function myEventList(Request $request, $type)
    {
        $userId = $request->user->id;

        $query = Event::whereHas('eventRegistrations', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        });

        // Filter by type
        if ($type === 'today') {
            $query->whereDate('start_date', Carbon::today());
        } elseif ($type === 'upcoming') {
            $query->whereDate('start_date', '>', Carbon::today());
        } elseif ($type === 'past') {
            $query->whereDate('start_date', '<', Carbon::today());
        } else {
            return response()->json(['error' => 'Invalid type'], 400);
        }

        return $query->with(['eventPrograms', 'eventsSponser'])->get();
    }

    public function attendance(Request $request)
    {
        $event = Event::with('eventRegistrations')->findOrFail($request->event_id);
        $userId = $request->user_id;
        // Check if the user is registered for the event
        $registration = $event->eventRegistrations()->where('user_id', $userId)->first();

        if (!$registration) {
            return response()->json(['error' => 'User not registered for this event'], 404);
        }

        // Update attendance
        $registration->update([
            'attendTypeId' => 2,
            'is_attend' => 1, // Assuming 2 means attended
            'attend_time' => Carbon::now(),
        ]);

        return response()->json(['message' => 'Attendance marked successfully']);
    }

    public function printSummary($id)
    {
        $event = Event::findOrFail($id);

        // Create QR code with event ID or barcode
        $filename = 'event-' . $event->id . '.png';
        $storagePath = storage_path('app/public/qrcodes/' . $filename);

        // Generate QR code image only if not exists
        if (!file_exists($storagePath)) {
            QrCode::format('png')->size(200)->generate($event->barcode ?? $event->id, $storagePath);
        }

        // Pass full file path to PDF
        $qrImage = $storagePath;

        $data = [
            'event' => $event,
            'qrImage' => $qrImage,
        ];

        $pdf = Pdf::loadView('event-summary', $data);
        return $pdf->stream('event-summary.pdf');
    }
}
