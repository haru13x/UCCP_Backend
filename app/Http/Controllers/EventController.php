<?php

namespace App\Http\Controllers;

use App\Models\CategoryRating;
use App\Models\Event;
use App\Models\EventMode;
use App\Models\EventRegistration;
use App\Models\Notification;
use App\Models\Review;
use App\Models\UserAccountType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use QuickChart;

class EventController extends Controller
{
    
    // EventController.php
    public function scanEvent(Request $request)
    {
        $barcode = $request->barcode;

        $event = Event::where('barcode', $barcode)
            ->with(['eventMode.eventType']) // eager load if needed
            ->orderBy('start_date', 'desc')
            ->first();

        if (!$event) {
            return response()->json([
                'message' => 'No event found for this barcode.' . $barcode,
                'type' => 2
            ], 200);
        }

        // Get user's account types (e.g., ["student", "staff", ...])
        $userAccountTypes = $request->user()->accountType->pluck('type')->toArray();

        // Get event allowed types (e.g., ["student", "alumni", ...])
        $eventAllowedTypes = $event->eventMode && $event->eventMode->account_type 
            ? [$event->eventMode->account_type] 
            : [];

        // Check if there's any intersection
        if (empty(array_intersect($userAccountTypes, $eventAllowedTypes))) {
            return response()->json([
                'message' => 'This event is not intended for your account type.',
                'type' => 2,

            ], 200);
        }

        // Append event_types for response
        $event->event_types = $event->eventMode && $event->eventMode->eventType 
            ? collect([$event->eventMode->eventType])
            : collect([]);

        return response()->json($event, 200);
    }

    public function cancelEvent(Request $request, $id)
    {
        $event = Event::find($id);

        if (!$event) {
            return response()->json(['message' => 'Event not found'], 404);
        }
        $user = Auth::user();
        $event->status_id = 2;
        $event->cancel_reason = $request->reason ?? '';
        $event->cancel_date = Carbon::now();
        $event->cancel_by = $user->name ?? ''; // Assuming 2 is the status for cancelled
        $event->save();
        // Optionally, you can also update related registrations
        EventRegistration::where('event_id', $id)
            ->update(['statusId' => 2]); // Assuming 2 is the status for cancelled
        return response()->json(['message' => 'Event cancelled successfully', 'event' => $event]);
    }
    public function index(Request $request)
    {
        $query = Event::query();

        // Filter by user's location_id if role is not 1
        if (auth()->check() && auth()->user()->role_id != 1) {
            $userLocationId = auth()->user()->location_id;
            $query->whereHas('eventLocations', function ($q) use ($userLocationId) {
                $q->where('location_id', $userLocationId);
            });
        }

        // Filter by status_id (e.g. 1 = active, 2 = cancelled)
        if ($request->has('status_id')) {
            $query->where('status_id', $request->status_id);
        } else {
            $query->where('status_id', 1); // Default to active events
        }

        // Date filter: today, past, upcoming
        if ($request->has('date_filter')) {
            $today = Carbon::today();

            switch ($request->date_filter) {
                case 'today':
                    $query->whereDate('start_date', $today);
                    break;
                case 'past':
                    $query->whereDate('start_date', '<', $today);
                    break;
                case 'upcoming':
                    $query->whereDate('start_date', '>=', $today);
                    break;
            }
        }

        // Search by title
        if ($request->has('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        $events = $query->with(['locations', 'eventMode.eventType', 'eventMode.eventGroup'])->orderBy('start_date', 'desc')->get()
            ->map(function ($event) {
                $eventTypes = $event->eventMode && $event->eventMode->eventType 
                    ? collect([$event->eventMode->eventType])
                    : collect([]);

                $event->event_types = $eventTypes;
                
                // Add account group IDs for frontend
                $accountGroupIds = $event->eventMode
                    ->pluck('account_group_id')
                    ->filter()
                    ->unique()
                    ->values()
                    ->toArray();
                $event->accountGroupIds = $accountGroupIds;
                
                // Add participants data for frontend
                $participants = $event->eventMode && $event->eventMode->account_type_id 
                    ? [$event->eventMode->account_type_id] 
                    : [];
                $event->participants = $participants;
                
                // Add participantData for frontend
                $participantData = $event->eventMode 
                    ? [[
                        'account_type_id' => $event->eventMode->account_type_id,
                        'account_group_id' => $event->eventMode->account_group_id
                    ]]
                    : [];
                $event->participantData = $participantData;
                
                // Override category field with comma-separated account group IDs
                $event->category = implode(',', $accountGroupIds);
                
                // Simplified location handling - only use venue field
                // For conference events, map locations to conference_locations
                if ($event->isconference) {
                    $event->conference_locations = $event->locations->pluck('id')->toArray();
                    $event->location_data = $event->locations->map(function ($churchLocation) {
                        return [
                            'location_id' => $churchLocation->id,
                            'id' => $churchLocation->id,
                            'name' => $churchLocation->name,
                            'slug' => $churchLocation->slug,
                            'description' => $churchLocation->description,
                        ];
                    });
                } else {
                    // For regular events, use venue field only
                    $event->conference_locations = [];
                    $event->location_data = [];
                    // Set location_id for single location events (legacy support)
                    if ($event->locations->count() > 0) {
                        $event->location_id = $event->locations->first()->id;
                    }
                }
                
                return $event;
            });

        return response()->json($events);
    }

    public function getEvent($id)
    {
        $query = Event::query();
        $event = $query->with(['locations', 'eventMode.eventType', 'eventMode.eventGroup'])->where('id', $id)
            ->orderBy('start_date', 'desc')->get()
            ->map(function ($event) {
                $eventTypes = $event->eventMode && $event->eventMode->eventType 
                    ? collect([$event->eventMode->eventType])
                    : collect([]);

                $event->event_types = $eventTypes;
                
                // Add account group IDs for frontend
                $accountGroupIds = $event->eventMode
                    ->pluck('account_group_id')
                    ->filter()
                    ->unique()
                    ->values()
                    ->toArray();
                $event->accountGroupIds = $accountGroupIds;
                
                // Add participants data for frontend
                $participants = $event->eventMode && $event->eventMode->account_type_id 
                    ? [$event->eventMode->account_type_id] 
                    : [];
                $event->participants = $participants;
                
                // Add participantData for frontend
                $participantData = $event->eventMode 
                    ? [[
                        'account_type_id' => $event->eventMode->account_type_id,
                        'account_group_id' => $event->eventMode->account_group_id
                    ]]
                    : [];
                $event->participantData = $participantData;
                
                // Override category field with comma-separated account group IDs
                $event->category = implode(',', $accountGroupIds);
                
                // Simplified location handling - only use venue field
                // For conference events, map locations to conference_locations
                if ($event->isconference) {
                    $event->conference_locations = $event->locations->pluck('id')->toArray();
                    $event->location_data = $event->locations->map(function ($churchLocation) {
                        return [
                            'id' => $churchLocation->id,
                            'name' => $churchLocation->name,
                            'slug' => $churchLocation->slug,
                            'description' => $churchLocation->description,
                        ];
                    });
                } else {
                    // For regular events, use venue field only
                    $event->conference_locations = [];
                    $event->location_data = [];
                }
                
                return $event;
            })->first();
        return response()->json($event, 200);
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
                'location_id' => 'nullable|string',
                'isconference' => 'nullable|boolean',
            ]);

         
            $participants = json_decode($request->input('participants', '[]'), true);
            $participantData = json_decode($request->input('participantData', '[]'), true);

            // Remove if accidentally included
            
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
            
            // If no location_id is provided, use the authenticated user's location_id
            if (empty($validated['location_id']) && $request->user && $request->user->location_id) {
                $validated['location_id'] = $request->user->location_id;
            }
            
            $event = Event::create($validated);

            // Handle locations - only for conference events
            if ($request->isconference && $request->has('conference_locations')) {
                // Handle multiple locations for conference events
                $conferenceLocations = json_decode($request->input('conference_locations', '[]'), true);
                if (is_array($conferenceLocations)) {
                    foreach ($conferenceLocations as $locationData) {
                        // Handle both array of IDs and array of objects
                        if (is_array($locationData)) {
                            // If it's an object with location_id property
                            $locationId = $locationData['location_id'] ?? null;
                        } else {
                            // If it's just an ID (integer or string)
                            $locationId = $locationData;
                        }
                        
                        if ($locationId === null || !is_numeric($locationId)) {
                            Log::error('Invalid location_id for conference location:', ['locationData' => $locationData]);
                            continue;
                        }
                        
                        \App\Models\EventLocation::create([
                            'event_id' => $event->id,
                            'location_id' => (int)$locationId,
                        ]);
                    }
                }
            }
            // For regular events, only use venue field (no event_locations table)

            // Handle category as comma-separated account group IDs
            if (!empty($validated['category'])) {
                $categoryIds = explode(',', $validated['category']);
                foreach ($categoryIds as $groupId) {
                    if (!empty(trim($groupId))) {
                        // Get all account types for this group
                        $accountTypes = \App\Models\AccountType::where('group_id', trim($groupId))->get();
                        
                        foreach ($accountTypes as $accountType) {
                            EventMode::create([
                                'event_id' => $event->id,
                                'account_type_id' => $accountType->id,
                                'account_group_id' => trim($groupId),
                                'status_id' => 1,
                                'created_by' => $request->user->id,
                            ]);
                        }
                    }
                }
            }

            // Handle participant data with multiple account groups
            if (!empty($participantData)) {
                // Use new participantData structure with account_group_id for each participant
                foreach ($participantData as $participant) {
                    // Validate account_group_id is not null
                    $accountGroupId = $participant['account_group_id'] ?? null;
                    if ($accountGroupId === null) {
                        Log::error('Store: account_group_id is null for participant', [
                            'participant' => $participant,
                            'participantData' => $participantData
                        ]);
                        continue; // Skip this participant
                    }
                    
                    EventMode::create([
                        'account_type_id' => $participant['account_type_id'],
                        'event_id' => $event->id,
                        'status_id' => 1,
                        'account_group_id' => $accountGroupId,
                        'created_by' => $request->user->id,
                    ]);
                }
            } else {
                // Fallback to old structure for backward compatibility
                foreach ($participants as $participantId) {
                    EventMode::create([
                        'account_type_id' => $participantId,
                        'event_id' => $event->id,
                        'status_id' => 1,
                        'account_group_id' => $request->account_group_id,
                        'created_by' => $request->user->id,
                    ]);
                }
            }

            $processedUserIds = []; // Store user IDs that we've already notified

            // Get participant IDs for notifications
            $participantIds = [];
            if (!empty($participantData)) {
                $participantIds = array_column($participantData, 'account_type_id');
            } else {
                $participantIds = $participants;
            }

            foreach ($participantIds as $participantId) {
                $users = UserAccountType::with('user')
                    ->where('account_type_id', $participantId)
                    ->where('status', 1)
                    ->get();

                foreach ($users as $userAccountType) {
                    $user = $userAccountType->user;

                    // Skip if user is null or was already processed
                    if (!$user || in_array($user->id, $processedUserIds)) {
                        continue;
                    }

                    $title = '📅 New Event: ' . $validated['title'];
                    $body = '📍 ' . ($validated['venue'] ?? 'Venue TBD') .
                        ' | 🕒 ' . ($validated['start_time'] ?? '') .
                        ' ' . ($validated['start_date'] ?? '');

                    // Save notification to DB
                    Notification::create([
                        'user_id' => $user->id,
                        'title' => $title,
                        'body' => $body,
                        'event_id' => $event->id,
                        'type' => 'created',
                    ]);

                    // Send push notification
                    $notificationData = [
                        'to' => $user->push_token,
                        'title' => $title,
                        'body' => $body,
                        'sound' => 'default',
                        'data' => [
                            'type' => 'event',
                            'event_id' => $event->id,
                        ],
                    ];

                    // Http::post('https://exp.host/--/api/v2/push/send', $notificationData);

                    // Mark this user as processed
                    $processedUserIds[] = $user->id;
                }
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
            // Validate that ID is present first
            $request->validate([
                'id' => 'required|integer|exists:events,id',
            ]);
            
            $id = $request->id;
            
            // Additional check to ensure ID is not null
            if (!$id) {
                return response()->json(['error' => 'Event ID is required for update'], 400);
            }
            
            $validated = $request->validate([
                'title' => 'required|string',
                'start_date' => 'nullable|date',
                'start_time' => 'nullable|string',
                'end_date' => 'nullable|date',
                'end_time' => 'nullable|string',
                'category' => 'nullable|string',
                'organizer' => 'nullable|string',
                'contact' => 'nullable|string',
                'venue' => 'nullable|string',
                'address' => 'nullable|string',
                'latitude' => 'nullable|numeric',
                'longitude' => 'nullable|numeric',
                'description' => 'nullable|string',
                'image' => 'nullable|file|image|max:5048',
                'location_id' => 'nullable|string',
                'isconference' => 'nullable|boolean',
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

            // If no location_id is provided, use the authenticated user's location_id
            if (empty($validated['location_id']) && $request->user && $request->user->location_id) {
                $validated['location_id'] = $request->user->location_id;
            }
            
            // Update event
            $event = Event::findOrFail($id);
            $event->update($validated);
            
            // Handle locations - only for conference events
            // Always remove existing event locations first
            \App\Models\EventLocation::where('event_id', $event->id)->delete();
            
            if ($request->isconference && $request->has('conference_locations')) {
                // Handle multiple locations for conference events
                $conferenceLocations = json_decode($request->input('conference_locations', '[]'), true);
                if (is_array($conferenceLocations)) {
                    foreach ($conferenceLocations as $locationData) {
                        // Handle both array of IDs and array of objects
                        if (is_array($locationData)) {
                            // If it's an object with location_id property
                            $locationId = $locationData['location_id'] ?? null;
                        } else {
                            // If it's just an ID (integer or string)
                            $locationId = $locationData;
                        }
                        
                        if ($locationId === null || !is_numeric($locationId)) {
                            Log::error('Invalid location_id for conference location:', ['locationData' => $locationData]);
                            continue;
                        }
                        
                        \App\Models\EventLocation::create([
                            'event_id' => $event->id,
                            'location_id' => (int)$locationId,
                        ]);
                    }
                }
            }
            // For regular events, only use venue field (no event_locations table)
            
            // Remove all existing EventModes for this event
            // We'll recreate them based on participant data
            EventMode::where('event_id', $event->id)->delete();
            
            // Create new event modes (participants) based on participantData
            $participantData = json_decode($request->input('participantData', '[]'), true);
            
            if (!empty($participantData) && is_array($participantData)) {
                // Use participantData structure with account_group_id information
                foreach ($participantData as $participant) {
                    // Ensure participant is an array and has required fields
                    if (!is_array($participant)) {
                        Log::error('Invalid participant data structure:', ['participant' => $participant]);
                        continue;
                    }
                    
                    $accountTypeId = $participant['account_type_id'] ?? null;
                    $accountGroupId = $participant['account_group_id'] ?? null;
                    
                    if ($accountTypeId === null || $accountGroupId === null) {
                        Log::error('Missing required fields for participant:', $participant);
                        continue; // Skip this participant if required fields are missing
                    }
                    
                    EventMode::create([
                        'event_id' => $event->id,
                        'account_type_id' => (int)$accountTypeId,
                        'account_group_id' => (int)$accountGroupId,
                        'status_id' => 1,
                        'created_by' => auth()->id() ?? 1,
                        'created_at' => now(),
                    ]);
                }
            }
            
            // $processedUserIds = [];
            // foreach ($participantIds as $participantId) {
            //     $users = UserAccountType::with('user')
            //         ->where('account_type_id', $participantId)
            //         ->where('status', 1)
            //         ->get();

            //     foreach ($users as $userAccountType) {
            //         $user = $userAccountType->user;

            //         // Skip if this user was already processed
            //         if (in_array($user->id, $processedUserIds)) {
            //             continue;
            //         }

            //         $title = '📅 Updated: ' . $validated['title'];
            //         $body = '📍 ' . ($validated['venue'] ?? 'Venue TBD') .
            //             ' | 🕒 ' . ($validated['start_time'] ?? '') .
            //             ' ' . ($validated['start_date'] ?? '');

            //         // Save notification to DB
            //         Notification::create([
            //             'user_id' => $user->id,
            //             'title' => $title,
            //             'body' => $body,
            //             'event_id' => $event->id,
            //             'type' => 'created',
            //         ]);

            //         // Send push notification
            //         $notificationData = [
            //             'to' => $user->push_token,
            //             'title' => $title,
            //             'body' => $body,
            //             'sound' => 'default',
            //             'data' => [
            //                 'type' => 'event',
            //                 'event_id' => $event->id,
            //             ],
            //         ];

            //         // Http::post('https://exp.host/--/api/v2/push/send', $notificationData);

            //         // Mark this user as processed
            //         $processedUserIds[] = $user->id;
                    
            //     }
            // }
            




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
            'status' => 'success',
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
            $userLocationId = auth()->user()->location_id;
            $events = Event::whereHas('eventMode', function ($query) use ($userAccountTypeIds) {
                $query->whereIn('account_type_id', $userAccountTypeIds);
            })
                ->whereDoesntHave('eventRegistrations', function ($q) use ($userId) {
                    $q->where('user_id', $userId); // 👈 excludes events already registered by user
                })
            ->whereHas('eventLocations', function ($q) use ($userLocationId) {
                $q->where('location_id', $userLocationId);
            });
        

        } else {
            $events = Event::query();
            $events->whereDoesntHave('eventRegistrations', function ($q) use ($userId) {
                $q->where('user_id', $userId); // 👈 excludes events already registered by user
            });
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

        $event = $events->orderBy('start_date', 'desc')->get()
            ->map(function ($event) {
                $eventTypes = $event->eventMode && $event->eventMode->eventType 
                    ? collect([$event->eventMode->eventType])
                    : collect([]);

                $event->event_types = $eventTypes;
                return $event;
            });

        return response()->json($event, 200);
    }

    public function myEventList(Request $request)
    {
        $userId = $request->user->id;

        $query = Event::whereHas('eventRegistrations', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        });

        // Check if date filter is provided (for specific date filtering)
        $dateFilter = $request->query('date');

        if ($dateFilter) {
            // If specific date is provided, filter by that exact date 
            $query->whereDate('start_date', $dateFilter);
        } else {
            // Monthly filtering based on month and year parameters
            $month = $request->query('month', Carbon::now()->month); // Default to current month
            $year = $request->query('year', Carbon::now()->year);   // Default to current year

            // Validate month and year
            if ($month < 1 || $month > 12) {
                return response()->json(['error' => 'Invalid month. Must be between 1-12'], 400);
            }

            if ($year < 1900 || $year > 2100) {
                return response()->json(['error' => 'Invalid year. Must be between 1900-2100'], 400);
            }

            // Filter events for the specified month and year
            $query->whereMonth('start_date', $month)
                ->whereYear('start_date', $year);
        }

        $events = $query->with(['locations'])->orderBy('start_date', 'asc')->get()
            ->map(function ($event) {
                $eventTypes = $event->eventMode && $event->eventMode->eventType 
                    ? collect([$event->eventMode->eventType])
                    : collect([]);

                $event->event_types = $eventTypes;
                
                // Add location data
                $event->location_data = $event->locations->map(function ($location) {
                    return [
                        'id' => $location->id,
                        'church_location' => $location->churchLocation,
                        'created_at' => $location->created_at,
                    ];
                });
                
                return $event;
            });

        return response()->json($events, 200);
    }
    public function myCalendarList(Request $request)
    {
        $userId = $request->user->id;

        $query = Event::whereHas('eventRegistrations', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        });
        $date = Carbon::parse($request->date)->format('Y-m-d');
        if ($date) {

            $query->whereDate('start_date', $date);
        }




        return $query->with(['eventPrograms', 'eventsSponser'])->get();
    }

    public function attendance(Request $request)
    {
        $event = Event::with('eventRegistrations')->findOrFail($request->event_id);
        $userId = $request->user_id ?? $request->user->id;
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
    public function generatePdf(Request $request)
    {
        $validated = $request->validate([
            'fromDate' => 'required|date',
            'toDate' => 'required|date|after_or_equal:fromDate',
            'status' => 'required|in:1,2',
            'locationId' => 'nullable|integer',
        ]);

        $query = Event::with([
            'eventRegistrations.details',
            'eventMode.eventType',
            'locations'
        ])
            ->where(function ($q) use ($validated) {
                $q->whereDate('start_date', '<=', $validated['toDate'])
                    ->whereDate('end_date', '>=', $validated['fromDate']);
            })
            ->where('status_id', $validated['status']);

        // Add location filter if provided
        if (!empty($validated['locationId'])) {
            // Get the location name from church_locations table
            $location = \App\Models\ChurchLocation::find($validated['locationId']);
            if ($location) {
                $query->where(function ($q) use ($validated, $location) {
                    // Filter by venue name (for regular events)
                    $q->where('venue', 'LIKE', '%' . $location->name . '%')
                      // Or filter by conference locations (for conference events)
                      ->orWhereHas('locations', function ($subQuery) use ($validated) {
                          $subQuery->where('church_location.id', $validated['locationId']);
                      });
                });
            }
        }

        $events = $query->orderBy('start_date')->get();

        // Generate dynamic stats and charts
        $eventData = [];

        foreach ($events as $event) {
            $registered = $event->eventRegistrations()->count();

            // Count attendees (is_attend = 1)
            $attended = $event->eventRegistrations()->where('is_attend', 1)->count();
            $notAttended = $registered - $attended;

            // Count male & female based on user details -> gender_id
            $maleCount = $event->eventRegistrations()
                ->whereHas('details', function ($q) {
                    $q->where('sex_id', 1); // 1 = male
                })
                ->count();

            $femaleCount = $event->eventRegistrations()
                ->whereHas('details', function ($q) {
                    $q->where('sex_id', 2); // 2 = female
                })
                ->count();

            // Get reviews and ratings data
            $reviews = Review::where('event_id', $event->id)
                ->with('categoryRatings', 'user')
                ->get();
            
            $totalReviews = $reviews->count();
            $averageRating = $totalReviews > 0 ? $reviews->avg('rating') : 0;
            
            // Calculate category averages
            $categoryAverages = [
                'venue' => 0,
                'speaker' => 0,
                'events' => 0,
                'foods' => 0,
                'accommodation' => 0
            ];
            
            if ($totalReviews > 0) {
                $categoryRatings = CategoryRating::whereIn('rating_id', $reviews->pluck('id'))->get();
                $categoryAverages['venue'] = $categoryRatings->avg('venue') ?? 0;
                $categoryAverages['speaker'] = $categoryRatings->avg('speaker') ?? 0;
                $categoryAverages['events'] = $categoryRatings->avg('event') ?? 0;
                $categoryAverages['foods'] = $categoryRatings->avg('food') ?? 0;
                $categoryAverages['accommodation'] = $categoryRatings->avg('accommodation') ?? 0;
            }
            
            // Handle location - use conference_locations if available, otherwise venue
            $locationText = 'N/A';
            if ($event->conferenceLocations && $event->conferenceLocations->isNotEmpty()) {
                $locationText = $event->conferenceLocations->pluck('name')->join(', ');
            } elseif ($event->venue) {
                $locationText = $event->venue;
            }
            // Gender chart - handle zero data case
            $genderChartUrl = "https://quickchart.io/chart?c=" . urlencode(json_encode([
                'type' => 'doughnut',
                'data' => [
                    'labels' => ['👨 Male', '👩 Female'],
                    'datasets' => [[
                        'data' => [$maleCount > 0 ? $maleCount : 0, $femaleCount > 0 ? $femaleCount : 0],
                        'backgroundColor' => [
                            '#3B82F6', // Tailwind blue-500
                            '#EC4899', // Tailwind pink-500
                        ],
                        'borderWidth' => 3,
                        'borderColor' => '#ffffff',
                        'hoverBorderWidth' => 4,
                        'hoverBorderColor' => '#1e40af'
                    ]],
                ],
                'options' => [
                    'plugins' => [
                        'legend' => [
                            'position' => 'bottom',
                            'labels' => [
                                'usePointStyle' => true,
                                'pointStyle' => 'circle',
                                'padding' => 15,
                                'font' => ['size' => 12, 'weight' => 'bold'],
                                'boxWidth' => 15,
                                'color' => '#1e40af'
                            ]
                        ],
                        'datalabels' => [
                            'display' => true,
                            'color' => '#ffffff',
                            'font' => ['weight' => 'bold', 'size' => 13],
                            'formatter' => 'function(value, context) { return value > 0 ? value : "0"; }',
                            'textStrokeColor' => '#000000',
                            'textStrokeWidth' => 1
                        ]
                    ],
                    'layout' => [
                        'padding' => [
                            'top' => 10,
                            'bottom' => 30,
                            'left' => 10,
                            'right' => 10
                        ]
                    ],
                    'responsive' => true,
                    'maintainAspectRatio' => false,
                ],
                'plugins' => ['https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels']
            ]));

            // Attendance chart - handle zero data case
            $attendanceChartUrl = "https://quickchart.io/chart?c=" . urlencode(json_encode([
                'type' => 'bar',
                'data' => [
                    'labels' => ['📊 Event Participation'],
                    'datasets' => [
                        [
                            'label' => '📝 Registered',
                            'data' => [$registered > 0 ? $registered : 0],
                            'backgroundColor' => '#10B981', // emerald-500
                            'borderColor' => '#059669',
                            'borderWidth' => 2,
                            'borderRadius' => 4,
                            'borderSkipped' => false
                        ],
                        [
                            'label' => '✅ Attended',
                            'data' => [$attended > 0 ? $attended : 0],
                            'backgroundColor' => '#6366F1', // indigo-500
                            'borderColor' => '#4f46e5',
                            'borderWidth' => 2,
                            'borderRadius' => 4,
                            'borderSkipped' => false
                        ],
                        [
                            'label' => '❌ Not Attended',
                            'data' => [$notAttended > 0 ? $notAttended : 0],
                            'backgroundColor' => '#F43F5E', // rose-500
                            'borderColor' => '#e11d48',
                            'borderWidth' => 2,
                            'borderRadius' => 4,
                            'borderSkipped' => false
                        ]
                    ],
                ],
                'options' => [
                    'scales' => [
                        'y' => [
                            'min' => 0,
                            'beginAtZero' => true,
                            'grid' => [
                                'color' => '#e2e8f0',
                                'lineWidth' => 1
                            ],
                            'ticks' => [
                                'stepSize' => $registered > 10 ? 5 : 1,
                                'callback' => 'function(value) { return Number.isInteger(value) ? value : null; }',
                                'font' => ['size' => 11, 'weight' => 'bold'],
                                'color' => '#475569'
                            ]
                        ],
                        'x' => [
                            'grid' => [
                                'display' => false
                            ],
                            'ticks' => [
                                'font' => ['size' => 11, 'weight' => 'bold'],
                                'maxRotation' => 0,
                                'color' => '#1e40af'
                            ]
                        ]
                    ],
                    'plugins' => [
                        'legend' => [
                            'display' => true,
                            'position' => 'bottom',
                            'labels' => [
                                'usePointStyle' => true,
                                'pointStyle' => 'rect',
                                'padding' => 15,
                                'font' => ['size' => 11, 'weight' => 'bold'],
                                'boxWidth' => 15,
                                'color' => '#1e40af'
                            ]
                        ],
                        'datalabels' => [
                            'display' => true,
                            'anchor' => 'end',
                            'align' => 'top',
                            'color' => '#1e40af',
                            'backgroundColor' => '#ffffff',
                            'borderColor' => '#e2e8f0',
                            'borderWidth' => 1,
                            'borderRadius' => 3,
                            'padding' => 4,
                            'font' => [
                                'weight' => 'bold',
                                'size' => 11
                            ],
                            'formatter' => 'function(value, context) { return value > 0 ? value : "0"; }'
                        ]
                    ],
                    'layout' => [
                        'padding' => [
                            'bottom' => 30,
                            'top' => 15,
                            'left' => 10,
                            'right' => 10
                        ]
                    ],
                    'responsive' => true,
                    'maintainAspectRatio' => false,
                ],
                'plugins' => ['https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels']
            ]));


            $eventData[$event->id] = compact(
                'genderChartUrl', 
                'attendanceChartUrl', 
                'registered', 
                'attended', 
                'notAttended', 
                'maleCount', 
                'femaleCount',
                'totalReviews',
                'averageRating',
                'categoryAverages',
                'locationText',
                'reviews'
            );
        }

        $pdf = PDF::setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
        ])->loadView('charts_pdf', [
            'events' => $events,
            'eventData' => $eventData,
            'fromDate' => $validated['fromDate'],
            'toDate' => $validated['toDate'],
        ])->setPaper('a4', 'portrait');

        return $pdf->download('event_report_' . now()->format('Y-m-d') . '.pdf');
    }



    // public function generatePdf(Request $request)
    //     {
    //         $validated = $request->validate([
    //             'fromDate' => 'required|date',
    //             'toDate' => 'required|date',
    //             'status' => 'required|in:1,2',
    //             'organizerId' => 'nullable|integer',
    //         ]);

    //         $query = Event::with([
    //             'eventRegistrations.details',
    //             'eventsSponser',
    //             'eventPrograms',
    //             'eventMode.eventType'
    //         ])
    //             ->where(function ($q) use ($validated) {
    //                 // Find events where the range overlaps with fromDate and toDate
    //                 $q->whereDate('start_date', '<=', $validated['toDate'])
    //                     ->whereDate('end_date', '>=', $validated['fromDate']);
    //             })
    //             ->where('status_id', $validated['status']);

    //         if (!empty($validated['organizerId'])) {
    //             $query->where('organizer_id', $validated['organizerId']);
    //         }

    //         $events = $query->orderBy('start_date')->get();


    //         $pdf = PDF::loadView('chard_pdf', [
    //             'events' => $events,
    //             'fromDate' => $validated['fromDate'],
    //             'toDate' => $validated['toDate']
    //         ]);

    //         return $pdf->download('event_report.pdf');
    //     }

    public function submitReview(Request $request, $eventId)
    {
        try {
            DB::beginTransaction();

            $user = Auth::user();

            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            // Validate request
            $validated = $request->validate([
                'reviewId' => 'nullable|integer|exists:reviews,id', // For updates
                'rating' => 'nullable|integer|between:1,5', // Overall rating
                'category_ratings' => 'nullable|array',
                'category_ratings.venue' => 'nullable|integer|between:1,5',
                'category_ratings.speaker' => 'nullable|integer|between:1,5',
                'category_ratings.events' => 'nullable|integer|between:1,5',
                'category_ratings.foods' => 'nullable|integer|between:1,5',
                'category_ratings.accommodation' => 'nullable|integer|between:1,5',
                'comment' => 'nullable|string|max:1000',
            ]);

            // Check if event exists
            $event = Event::find($eventId);
            if (!$event) {
                return response()->json(['message' => 'Event not found'], 404);
            }

            // Check if user is registered for the event
            $isRegistered = $event->eventRegistrations()
                ->where('user_id', $user->id)
                ->exists();

            if (!$isRegistered) {
                return response()->json(['message' => 'You must be registered to review this event'], 403);
            }

            // Check if this is an update or new review
            if ($validated['reviewId']) {
                // Update existing review
                $review = Review::where('id', $validated['reviewId'])
                    ->where('user_id', $user->id)
                    ->where('event_id', $eventId)
                    ->first();
                
                if (!$review) {
                    return response()->json(['message' => 'Review not found or unauthorized'], 404);
                }
                
                $review->update([
                    'rating' => $validated['rating'],
                    'comment' => $validated['comment'],
                ]);
                
                // Update category ratings
                $categories = $validated['category_ratings'] ?? [];
                if (!empty($categories)) {
                    $categoryRating = CategoryRating::where('rating_id', $review->id)->first();
                    if ($categoryRating) {
                        $categoryRating->update([
                            'venue' => $categories['venue'] ?? null,
                            'speaker' => $categories['speaker'] ?? null,
                            'event' => $categories['events'] ?? null,
                            'food' => $categories['foods'] ?? null,
                            'accommodation' => $categories['accommodation'] ?? null,
                        ]);
                    } else {
                        CategoryRating::create([
                            'rating_id' => $review->id,
                            'venue' => $categories['venue'] ?? null,
                            'speaker' => $categories['speaker'] ?? null,
                            'event' => $categories['events'] ?? null,
                            'food' => $categories['foods'] ?? null,
                            'accommodation' => $categories['accommodation'] ?? null,
                        ]);
                    }
                }
            } else {
                // Create new review
                $review = Review::create([
                    'event_id' => $eventId,
                    'user_id' => $user->id,
                    'rating' => $validated['rating'],
                    'comment' => $validated['comment'],
                ]);
                
                $categories = $validated['category_ratings'] ?? [];
                if (!empty($categories)) {
                    CategoryRating::create([
                        'rating_id' => $review->id,
                        'venue' => $categories['venue'] ?? null,
                        'speaker' => $categories['speaker'] ?? null,
                        'event' => $categories['events'] ?? null,
                        'food' => $categories['foods'] ?? null,
                        'accommodation' => $categories['accommodation'] ?? null,
                    ]);
                }
            }
            return response()->json([
                'message' => $validated['reviewId'] ? 'Review updated successfully' : 'Review submitted successfully',
                'review' => [
                    'id' => $review->id,
                    'rating' => $review->rating,
                    'comment' => $review->comment,
                    'category_ratings' => $review->categoryRatings ? [
                        'venue' => $review->categoryRatings->first()?->venue ?? 0,
                        'speaker' => $review->categoryRatings->first()?->speaker ?? 0,
                        'events' => $review->categoryRatings->first()?->event ?? 0,
                        'foods' => $review->categoryRatings->first()?->food ?? 0,
                        'accommodation' => $review->categoryRatings->first()?->accommodation ?? 0,
                    ] : null,
                    'user_id' => $review->user_id,
                    'created_at' => $review->created_at,
                ]
            ], $validated['reviewId'] ? 200 : 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Database transaction failed: ' . $e->getMessage()], 500);
        } finally {
            DB::commit();
        }
    }

    public function getReviews($eventId)
    {
        try {
            $review = Review::where('event_id', $eventId)
                ->with('categoryRatings', 'user') // assuming you have a User relationship  
                ->get();
            

            $formattedReviews = $review->map(function ($r) {
                return [
                    'id' => $r->id,
                    'rating' => $r->rating,
                    'comment' => $r->comment,
                    'is_mine' => $r->is_mine,
                    'category_ratings' => $r->categoryRatings ? [
                        'venue' => $r->categoryRatings->first()?->venue ?? 0,
                        'speaker' => $r->categoryRatings->first()?->speaker ?? 0,
                        'events' => $r->categoryRatings->first()?->event ?? 0,
                        'foods' => $r->categoryRatings->first()?->food ?? 0,
                        'accommodation' => $r->categoryRatings->first()?->accommodation ?? 0,
                    ] : null,
                    'user_id' => $r->user_id,
                    'created_at' => $r->created_at,
                ];
            });

            return response()->json(['reviews' => $formattedReviews], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch reviews: ' . $e->getMessage()], 500);
            //throw $th;
        }
    }

    public function updateReview(Request $request, $eventId)
    {
        $user = Auth::user();
        $event = Event::findOrFail($eventId);
        $reviewId = $request->reviewId;
        // Validate the user is registered
        if (!$event->eventRegistrations()->where('user_id', $user->id)->exists()) {
            return response()->json([
                'message' => 'You must be registered to review this event.'
            ], 403);
        }

        // Find the user's review for this event
        $review = Review::where('user_id', $user->id)
            ->where('id', $reviewId)
            ->where('event_id', $eventId)
            ->first();

        if (!$review) {
            return response()->json([
                'message' => 'You have not reviewed this event yet.'
            ], 404);
        }

        // Validate input
        $request->validate([
            'rating' => 'nullable|integer|between:1,5',
            'comment' => 'required|string|max:1000',
        ]);

        // Update review
        $review->where('id', $reviewId)->update([
            'rating' => $request->rating,
            'comment' => $request->comment,
        ]);

        return response()->json([
            'message' => 'Review updated successfully!',
            'review' => $review,
        ], 200);
    }
    public function getNewNotifications(Request $request)
    {
        $user = $request->user(); // assuming auth

        $notification = Notification::where('user_id', $user->id)
            ->where('is_notify', 0)
            ->orderBy('created_at', 'asc')
            ->first();

        if ($notification) {
            $notification->update(['is_notify' => 1]);
        }

        return response()->json([
            'status' => true,
            'data' => $notification ? [$notification] : []
        ]);
    }
}
