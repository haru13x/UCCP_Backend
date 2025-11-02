<?php

namespace App\Http\Controllers;

use App\Models\CategoryRating;
use App\Models\Event;
use App\Models\EventLocation;
use App\Models\EventMode;
use App\Models\EventRegistration;
use App\Models\Notification;
use App\Models\Review;
use App\Models\UserAccountType;
use App\Models\User;
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

        // Validate barcode parameter
        if (!$barcode) {
            return response()->json([
                'message' => 'Barcode parameter is required.',
                'type' => 2
            ], 200);
        }

        // Find event by barcode (no eventtype relationship needed)
        $event = Event::where('barcode', $barcode)
            ->orderBy('start_date', 'desc')
            ->first();

        if (!$event) {
            return response()->json([
                'message' => 'No event found for this barcode: ' . $barcode,
                'type' => 2
            ], 200);
        }

        // Ensure user is authenticated
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'message' => 'Authentication required.',
                'type' => 2
            ], 200);
        }

        // Check if user is registered for this event
        $registration = EventRegistration::where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$registration) {
            return response()->json([
                'message' => 'You are not registered for this event.',
                'type' => 2
            ], 200);
        }

        // Enforce timing rules: attendance allowed from 60 minutes before start until event end
        try {
            // Build start/end datetimes
            $start = \Carbon\Carbon::parse(($event->start_date ?? '') . ' ' . ($event->start_time ?? '00:00:00'));
            $end = \Carbon\Carbon::parse(($event->end_date ?? $event->start_date ?? '') . ' ' . ($event->end_time ?? '23:59:59'));
            $now = \Carbon\Carbon::now();

            // Too early: earlier than 60 minutes before start
            if ($now->lt($start->copy()->subMinutes(60))) {
                return response()->json([
                    'message' => 'Attendance opens 1 hour before the event starts.',
                    'type' => 2
                ], 200);
            }

            // Too late: event ended
            if ($end && $now->gt($end)) {
                return response()->json([
                    'message' => 'The event has ended. Attendance is closed.',
                    'type' => 2
                ], 200);
            }
        } catch (\Exception $e) {
            // If parsing fails, be conservative and block
            return response()->json([
                'message' => 'Unable to verify event schedule for attendance.',
                'type' => 2
            ], 200);
        }

        // Prevent duplicate attendance
        if (($registration->is_attend ?? 0) == 1 || !empty($registration->attend_time)) {
            return response()->json([
                'message' => 'Attendance already marked for this event.',
                'type' => 2
            ], 200);
        }

        // Mark attendance if not already marked
        if (!$registration->is_attend) {
            $registration->update([
                'is_attend' => true,
                'attend_time' => now(),
                'updated_by' => $user->id
            ]);
        }

        // Return success response without eventtype data
        return response()->json([
            'message' => $registration->getOriginal('is_attend') ? 'Attendance already marked' : 'Attendance marked successfully',
            'event' => $event,
            'attendance_status' => $registration->is_attend,
            'type' => 1
        ], 200);
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

        // Filter events based on user role and group
        if (auth()->check()) {
            $user = auth()->user();
            
            // Admin (role_id = 1) can see all events
            if ($user->role_id != 1) {
                $userGroupId = $user->group_id;
                
                // For non-admin users:
                // 1. Show conference events only if they have an active event mode matching user's group
                // 2. Show non-conference events only if they have an active event mode matching user's group
                $query->whereHas('eventModes', function($modeQ) use ($userGroupId) {
                    $modeQ->where('account_group_id', $userGroupId)
                          ->where('status_id', 1);
                });
            }
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

        // Filter by category (account group) - admin-only
        if ($request->has('category') && !empty($request->category)) {
            $isAdmin = auth()->check() && auth()->user()->role_id == 1;
            if ($isAdmin) {
                $catParam = $request->category;
                $catIds = is_array($catParam) ? $catParam : array_map('trim', explode(',', $catParam));
                $catIds = array_filter($catIds, function ($v) { return $v !== ''; });
                $catIdsInt = array_map('intval', $catIds);

                $query->whereHas('eventModes', function($modeQ) use ($catIdsInt) {
                    $modeQ->whereIn('account_group_id', $catIdsInt)
                          ->where('status_id', 1);
                });
            }
        }

        $events = $query->with(['locations', 'eventModes.eventGroup'])->orderBy('start_date', 'desc')->get()
            ->map(function ($event) {
                // Add account group IDs for frontend
                $accountGroupIds = $event->eventModes
                    ->pluck('account_group_id')
                    ->filter()
                    ->unique()
                    ->values()
                    ->toArray();
                $event->accountGroupIds = $accountGroupIds;

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
        $event = $query->with(['locations', 'eventModes.eventGroup'])->where('id', $id)
            ->orderBy('start_date', 'desc')->get()
            ->map(function ($event) {
                // Add account group IDs for frontend
                $accountGroupIds = $event->eventModes
                    ->pluck('account_group_id')
                    ->filter()
                    ->unique()
                    ->values()
                    ->toArray();
                $event->accountGroupIds = $accountGroupIds;

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


            // Remove legacy participant payloads (account types) — categories (account groups) only

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

            // Location behavior: only non-conference events have a single location_id
            if ($request->isconference) {
                // Conference events are open to all locations; clear location_id on event
                $validated['location_id'] = null;
            } else if (empty($validated['location_id']) && $request->user && $request->user->location_id) {
                // For non-conference, default to creator's location when not provided
                $validated['location_id'] = $request->user->location_id;
            }

            $event = Event::create($validated);

            // Handle locations: conference => no specific locations; non-conference => single location
            if ($request->isconference) {
                // Do not create event_locations for conference events (open to all)
            } else {
                $singleLocationId = $event->location_id ?? (auth()->user()->location_id ?? null);
                if ($singleLocationId) {
                    EventLocation::updateOrCreate(
                        [
                            'event_id' => $event->id,
                            'location_id' => (int)$singleLocationId,
                        ],
                        [
                            'event_id' => $event->id,
                            'location_id' => (int)$singleLocationId,
                            'created_at' => Carbon::now()
                        ]
                    );
                }
            }
            // For regular events, only use venue field (no event_locations table)

            // Handle category as comma-separated account group IDs
            // Only admins can set arbitrary categories; non-admins are forced to their own group
            $isAdmin = false;
            if (auth()->check() && auth()->user()->role_id == 1) { $isAdmin = true; }
            if (isset($request->user) && isset($request->user->role_id) && $request->user->role_id == 1) { $isAdmin = true; }

            $categoryIds = [];
            if ($isAdmin && !empty($validated['category'])) {
                $categoryIds = array_filter(array_map('trim', explode(',', $validated['category'])));
            } else {
                // Force to creator's group when not admin
                $forcedGroup = null;
                if (isset($request->user) && isset($request->user->group_id)) {
                    $forcedGroup = $request->user->group_id;
                } elseif (auth()->check()) {
                    $forcedGroup = auth()->user()->group_id ?? null;
                }
                if ($forcedGroup) {
                    $categoryIds = [ (string)$forcedGroup ];
                }
            }

            // First, deactivate all existing event modes for this event
            EventMode::where('event_id', $event->id)
                ->update(['status_id' => 2]); // Set status to inactive

            // Then create or reactivate event modes for the current categories
            foreach ($categoryIds as $groupId) {
                if (!empty(trim($groupId))) {
                    EventMode::updateOrCreate(
                        [
                            'event_id' => $event->id,
                            'account_group_id' => (int)trim($groupId),
                        ],
                        [
                            'status_id' => 1, // Set status to active
                            'created_by' => $request->user->id,
                        ]
                    );
                }
            }

            // Participant creation already handled via categories above; no participantData processing

            $processedUserIds = []; // Store user IDs that we've already notified

            // Notify users belonging to selected groups (via user.group_id, not account types)
            foreach ($categoryIds as $groupId) {
                $users = User::where('group_id', (int)trim($groupId))
                    ->where('status_id', 1)
                    ->get();

                foreach ($users as $user) {
                    // Skip if user is null or was already processed
                    if (!$user || in_array($user->id, $processedUserIds)) {
                        continue;
                    }

                    $title = ' New Event: ' . $validated['title'];
                    $body = ' ' . ($validated['venue'] ?? 'Venue TBD') .
                        '  ' . ($validated['start_time'] ?? '') .
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

            // Location behavior: only non-conference events have a single location_id
            if ($request->isconference) {
                $validated['location_id'] = null; // clear any incoming location for conferences
            } else if (empty($validated['location_id']) && $request->user && $request->user->location_id) {
                $validated['location_id'] = $request->user->location_id;
            }

            // Update event
            $event = Event::findOrFail($id);
            $event->update($validated);

            // Handle locations: conference => none; non-conference => single location
            \App\Models\EventLocation::where('event_id', $event->id)->delete();
            if ($request->isconference) {
                // Do not attach specific locations for conference events
            } else {
                $singleLocationId = $event->location_id ?? (auth()->user()->location_id ?? null);
                if ($singleLocationId) {
                    EventLocation::updateOrCreate(
                        [
                            'event_id' => $event->id,
                            'location_id' => (int)$singleLocationId,
                        ],
                        [
                            'event_id' => $event->id,
                            'location_id' => (int)$singleLocationId,
                            'updated_at' => Carbon::now()
                        ]
                    );
                }
            }
            // For regular events, only use venue field (no event_locations table)

            // Remove all existing EventModes for this event
            // We'll recreate them based on participant data
            EventMode::where('event_id', $event->id)->delete();

            // Rebuild EventModes based on categories only; ignore participantData entirely
            $isAdmin = false;
            if (auth()->check() && (auth()->user()->role_id == 1)) { $isAdmin = true; }
            if (isset($request->user) && isset($request->user->role_id) && $request->user->role_id == 1) { $isAdmin = true; }

            $categoryStr = $request->input('category', '');
            $categoryIds = [];
            if ($isAdmin && !empty($categoryStr)) {
                $categoryIds = array_filter(array_map('trim', explode(',', $categoryStr)));
            } else {
                $forcedGroup = null;
                if (isset($request->user) && isset($request->user->group_id)) {
                    $forcedGroup = $request->user->group_id;
                } elseif (auth()->check()) {
                    $forcedGroup = auth()->user()->group_id ?? null;
                }
                if ($forcedGroup) { $categoryIds = [ (string)$forcedGroup ]; }
            }

            foreach ($categoryIds as $groupId) {
                if (!empty(trim($groupId))) {
                    // Create a single EventMode per group (account types removed)
                    EventMode::create([
                        'event_id' => $event->id,
                        'account_group_id' => (int)trim($groupId),
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

        $events = Event::query();

        if ($user->role_id != 1) {
            $userLocationId = $user->location_id;
            $userGroupId = $user->group_id;

            $events->where(function ($query) use ($userLocationId, $userGroupId) {
                // For conference events: only filter by group_id in event modes
                $query->where(function ($q) use ($userGroupId) {
                    $q->where('isconference', true)
                      ->whereHas('eventModes', function ($em) use ($userGroupId) {
                          $em->where('account_group_id', $userGroupId)
                             ->where('status_id', 1);
                      });
                })
                // For non-conference events: filter by both location and group_id
                ->orWhere(function ($q) use ($userLocationId, $userGroupId) {
                    $q->where('isconference', false)
                      ->whereHas('eventLocations', function ($el) use ($userLocationId) {
                          $el->where('location_id', $userLocationId);
                      })
                      ->whereHas('eventModes', function ($em) use ($userGroupId) {
                          $em->where('account_group_id', $userGroupId)
                             ->where('status_id', 1);
                      });
                });
            });
        }

        $events->whereDoesntHave('eventRegistrations', function ($q) use ($userId) {
            $q->where('user_id', $userId); // Excludes events already registered by user
        });

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

        $event = $events->orderBy('start_date', 'desc')->get();
            // ->map(function ($event) {
            //     $eventTypes = $event->eventModes && $event->eventModes->count() > 0
            //         ? $event->eventModes->map(function($mode) { return $mode->eventType; })->filter()
            //         : collect([]);

            //     $event->event_types = $eventTypes;
            //     return $event;
            // });

        return response()->json($event, 200);
    }

    public function myEventList(Request $request, $filter = null)
    {
        $userId = $request->user->id;

        $query = Event::whereHas('eventRegistrations', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        });
 
        // Handle filter parameter (upcoming, today, past)
        if ($filter) {
            $today = Carbon::today();
            switch ($filter) {
                case 'today':
                    $query->whereDate('start_date', $today);
                    break;
                case 'past':
                    $query->whereDate('start_date', '<', $today);
                    break;
                case 'upcoming':
                default:
                    $query->whereDate('start_date', '>', $today);
                    break;
            }
        } else {
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
        }

        $events = $query->with(['locations'])->orderBy('start_date', 'asc')->get()
            ->map(function ($event) {
                // Account type/eventType removed; ensure no legacy mapping
                $event->event_types = collect([]);

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
        $user = $request->user();
        if (!$user) {
            return response()->json([], 200);
        }
        $userId = $user->id;

        $query = Event::whereHas('eventRegistrations', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        });

        if ($request->filled('date')) {
            $date = Carbon::parse($request->date)->format('Y-m-d');
            $query->whereDate('start_date', $date);
        } elseif ($request->filled('start_date') && $request->filled('end_date')) {
            // Support date range queries for monthly calendar dots
            $startDate = Carbon::parse($request->start_date)->format('Y-m-d');
            $endDate = Carbon::parse($request->end_date)->format('Y-m-d');
            $query->whereBetween('start_date', [$startDate, $endDate]);
        }

        // Return only core event fields; no heavy relationships for mobile calendar
        return response()->json($query->orderBy('start_date', 'asc')->get(), 200);
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
        $event = Event::with(['eventRegistrations',  'locations','eventModes'])->findOrFail($id);

        // Resolve existing QR record by event_id. If absent, generate via controller.
        $qrRecord = \App\Models\QR::where('event_id', $event->id)->first();
        if (!$qrRecord) {
            // Generate a QR using the dedicated controller to ensure consistent filename (barcode-based)
            $qrController = new QRCodeController();
            $generateRes = $qrController->generate($event->id);
            // Refresh record after generation
            $qrRecord = \App\Models\QR::where('event_id', $event->id)->first();
        }

        // Build both a public URL and an absolute public path for DomPDF image embedding
        $qrUrl = null;
        $qrPublicPath = null;
        if ($qrRecord && $qrRecord->qr_path) {
            $qrUrl = asset('storage/' . $qrRecord->qr_path);
            $qrPublicPath = public_path('storage/' . $qrRecord->qr_path);
        }

        $data = [
            'event' => $event,
            'qrUrl' => $qrUrl,
            'qrPublicPath' => $qrPublicPath,
        ];

        // Enable remote content if needed and render PDF
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
            // Remove invalid/unused account type relation
            // 'eventModes.eventType',
            'eventMode',
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
            $otherCount = max(0, $registered - $maleCount - $femaleCount);

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
            // Gender chart - modern doughnut with clear labels
            $genderChartUrl = "https://quickchart.io/chart?c=" . urlencode(json_encode([
                'type' => 'doughnut',
                'data' => [
                    'labels' => ['👨 Male', '👩 Female', '⚧ Other'],
                    'datasets' => [[
                        'data' => [max(0, $maleCount), max(0, $femaleCount), max(0, $otherCount)],
                        'backgroundColor' => ['#3B82F6', '#EC4899', '#10B981'],
                        'borderWidth' => 3,
                        'borderColor' => '#ffffff',
                    ]],
                ],
                'options' => [
                    'plugins' => [
                        'legend' => [ 'display' => false ],
                        'datalabels' => [
                            'display' => true,
                            'color' => '#ffffff',
                            'font' => ['weight' => 'bold', 'size' => 12],
                            'formatter' => 'function(value, ctx) { const total = (ctx.dataset.data||[]).reduce((a,b)=>a+(b||0),0); const pct = total ? Math.round((value/total)*100) : 0; return value + " (" + pct + "%)"; }'
                        ]
                    ],
                    'layout' => [ 'padding' => ['top' => 6, 'bottom' => 20, 'left' => 6, 'right' => 6] ],
                    'responsive' => true,
                    'maintainAspectRatio' => false,
                ],
                'plugins' => ['https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels']
            ]));

            // Attendance chart - horizontal bar with single dataset for clarity
            $attendanceChartUrl = "https://quickchart.io/chart?c=" . urlencode(json_encode([
                'type' => 'bar',
                'data' => [
                    'labels' => ['📝 Registered', '✅ Attended', '❌ Not Attended'],
                    'datasets' => [[
                        'label' => 'Count',
                        'data' => [max(0, $registered), max(0, $attended), max(0, $notAttended)],
                        'backgroundColor' => ['#10B981', '#6366F1', '#F43F5E'],
                        'borderColor' => ['#059669', '#4F46E5', '#E11D48'],
                        'borderWidth' => 2,
                        'borderRadius' => 6,
                        'borderSkipped' => false
                    ]]
                ],
                'options' => [
                    'indexAxis' => 'y',
                    'scales' => [
                        'x' => [
                            'beginAtZero' => true,
                            'grid' => ['color' => '#e2e8f0', 'lineWidth' => 1],
                            'ticks' => ['font' => ['size' => 11, 'weight' => 'bold'], 'color' => '#475569']
                        ],
                        'y' => [
                            'grid' => ['display' => false],
                            'ticks' => ['font' => ['size' => 12, 'weight' => 'bold'], 'color' => '#1e40af']
                        ]
                    ],
                    'plugins' => [
                        'legend' => ['display' => false],
                        'datalabels' => [
                            'display' => true,
                            'anchor' => 'center',
                            'align' => 'center',
                            'color' => '#ffffff',
                            'backgroundColor' => 'rgba(0,0,0,0.25)',
                            'borderRadius' => 4,
                            'padding' => 4,
                            'font' => ['weight' => 'bold', 'size' => 12],
                            'formatter' => 'function(value) { return value > 0 ? value : "0"; }'
                        ]
                    ],
                    'layout' => [
                        'padding' => ['bottom' => 20, 'top' => 10, 'left' => 10, 'right' => 10]
                    ],
                    'responsive' => true,
                    'maintainAspectRatio' => false
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
                // Check if user already has a review for this event
                $existingReview = Review::where('event_id', $eventId)
                    ->where('user_id', $user->id)
                    ->first();

                if ($existingReview) {
                    return response()->json([
                        'message' => 'You have already submitted a review for this event. You can only edit your existing review.',
                        'error' => 'duplicate_review'
                    ], 409);
                }

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
            ], $validated['reviewId'] ? 201 : 201);
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
            'message' => 'Review updated successfullys!',
            'review' => $review,
        ], 201);
    }
    public function getNewNotifications(Request $request)
    {
        $user = $request->user(); // assuming auth

        // Get all unnotified notifications for the user
        $notifications = Notification::where('user_id', $user->id)
            ->where('is_notify', 0)
            ->orderBy('created_at', 'asc')
            ->get();

        // Mark them as notified only if we found some
        if ($notifications->count() > 0) {
            Notification::where('user_id', $user->id)
                ->where('is_notify', 0)
                ->update(['is_notify' => 1]);
        }

        return response()->json([
            'status' => true,
            'data' => $notifications->toArray()
        ]);
    }

    public function markNotificationRead(Request $request, $id)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['status' => false, 'message' => 'Unauthorized'], 401);
        }

        $notification = Notification::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$notification) {
            return response()->json(['status' => false, 'message' => 'Notification not found'], 404);
        }

        $notification->is_read = 1;
        $notification->save();

        return response()->json([
            'status' => true,
            'data' => $notification
        ], 200);
    }
    public function getAllNotifications(Request $request){
         $user = $request->user(); // assuming auth

        $notification = Notification::where('user_id', $user->id)
         
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
    public function getRecentNotifications(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['status' => false, 'message' => 'Unauthorized'], 401);
        }

        $notifications = Notification::where('user_id', $user->id)
            ->where('is_read', 0)
            ->where('is_notify', 0)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();
        if ($notifications->count() > 0) {
            $notifications->update(['is_notify' => 1]);
        }
        return response()->json([
            'status' => true,
            'data' => $notifications,
            'total' => $notifications->count(),
            'user' => $user,
        ], 200);
    }

    // Returns event overview counts for registrations, attendance, and reviews
    public function overview($eventId)
    {
        $event = Event::find($eventId);
        if (!$event) {
            return response()->json(['message' => 'Event not found'], 404);
        }

        $registrationsTotal = EventRegistration::where('event_id', $eventId)->count();
        $registrationsCancelled = EventRegistration::where('event_id', $eventId)->where('statusId', 2)->count();
        $attended = EventRegistration::where('event_id', $eventId)->where('is_attend', 1)->count();
        $absent = max($registrationsTotal - $attended, 0);
        $reviewsCount = Review::where('event_id', $eventId)->count();
        $avgRating = Review::where('event_id', $eventId)->avg('rating');

        // Compute category averages from category_rating (singular) joined to reviews
        $categoryAverages = DB::table('category_rating')
            ->join('reviews', 'category_rating.rating_id', '=', 'reviews.id')
            ->where('reviews.event_id', $eventId)
            ->selectRaw('AVG(category_rating.venue) as venue, AVG(category_rating.speaker) as speaker, AVG(category_rating.event) as events, AVG(category_rating.food) as foods, AVG(category_rating.accommodation) as accommodation')
            ->first();

        return response()->json([
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'start_date' => $event->start_date,
                'start_time' => $event->start_time,
                'isconference' => $event->isconference,
            ],
            'registrations' => [
                'total' => $registrationsTotal,
                'cancelled' => $registrationsCancelled,
                'active' => max($registrationsTotal - $registrationsCancelled, 0),
            ],
            'attendance' => [
                'present' => $attended,
                'absent' => $absent,
            ],
            'reviews' => [
                'count' => $reviewsCount,
                'avg_rating' => round($avgRating ?? 0, 2),
                'category_averages' => [
                    'venue' => round(($categoryAverages->venue ?? 0), 2),
                    'speaker' => round(($categoryAverages->speaker ?? 0), 2),
                    'events' => round(($categoryAverages->events ?? 0), 2),
                    'foods' => round(($categoryAverages->foods ?? 0), 2),
                    'accommodation' => round(($categoryAverages->accommodation ?? 0), 2),
                ],
            ],
        ], 200);
    }
}
