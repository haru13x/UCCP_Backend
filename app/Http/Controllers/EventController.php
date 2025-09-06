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
        $eventAllowedTypes = $event->eventMode->pluck('account_type')->toArray();

        // Check if there's any intersection
        if (empty(array_intersect($userAccountTypes, $eventAllowedTypes))) {
            return response()->json([
                'message' => 'This event is not intended for your account type.',
                'type' => 2,

            ], 200);
        }

        // Append event_types for response
        $event->event_types = $event->eventMode
            ->pluck('eventType')
            ->filter()
            ->values();

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

        $events = $query->orderBy('start_date', 'desc')->get()
            ->map(function ($event) {
                $eventTypes = $event->eventMode
                    ->pluck('eventType')
                    ->filter()
                    ->values();

                $event->event_types = $eventTypes;
                return $event;
            });

        return response()->json($events);
    }

    public function getEvent($id)
    {
        $query = Event::query();
        $event = $query->where('id', $id)
            ->orderBy('start_date', 'desc')->get()
            ->map(function ($event) {
                $eventTypes = $event->eventMode
                    ->pluck('eventType')
                    ->filter()
                    ->values();

                $event->event_types = $eventTypes;
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

            $processedUserIds = []; // Store user IDs that we've already notified

            foreach ($participants as $participantId) {
                $users = UserAccountType::with('user')
                    ->where('account_type_id', $participantId)
                    ->where('status', 1)
                    ->get();

                foreach ($users as $userAccountType) {
                    $user = $userAccountType->user;

                    // Skip if this user was already processed
                    if (in_array($user->id, $processedUserIds)) {
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
                'latitude',
                'longitude',
                'description' => 'nullable|string',
                'image' => 'nullable|file|image|max:5048',
                'location_id' => 'nullable|string',
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
            $processedUserIds = [];
            foreach ($participantIds as $participantId) {
                $users = UserAccountType::with('user')
                    ->where('account_type_id', $participantId)
                    ->where('status', 1)
                    ->get();

                foreach ($users as $userAccountType) {
                    $user = $userAccountType->user;

                    // Skip if this user was already processed
                    if (in_array($user->id, $processedUserIds)) {
                        continue;
                    }

                    $title = '📅 Updated: ' . $validated['title'];
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

            $events = Event::whereHas('eventMode', function ($query) use ($userAccountTypeIds) {
                $query->whereIn('account_type_id', $userAccountTypeIds);
            })
                ->whereDoesntHave('eventRegistrations', function ($q) use ($userId) {
                    $q->where('user_id', $userId); // 👈 excludes events already registered by user
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
                $eventTypes = $event->eventMode
                    ->pluck('eventType')
                    ->filter()
                    ->values();

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

        $events = $query->orderBy('start_date', 'asc')->get()
            ->map(function ($event) {
                $eventTypes = $event->eventMode
                    ->pluck('eventType')
                    ->filter()
                    ->values();

                $event->event_types = $eventTypes;
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
            'organizerId' => 'nullable|integer',
        ]);

        $query = Event::with([
            'eventRegistrations.details',
            'eventsSponser',
            'eventPrograms',
            'eventMode.eventType'
        ])
            ->where(function ($q) use ($validated) {
                $q->whereDate('start_date', '<=', $validated['toDate'])
                    ->whereDate('end_date', '>=', $validated['fromDate']);
            })
            ->where('status_id', $validated['status']);

        if ($validated['organizerId']) {
            $query->where('organizer', $validated['organizerId']);
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
            // Gender chart
            $genderChartUrl = "https://quickchart.io/chart?c=" . urlencode(json_encode([
                'type' => 'pie',
                'data' => [
                    'labels' => ['Male', 'Female'],
                    'datasets' => [[
                        'data' => [$maleCount, $femaleCount],
                        'backgroundColor' => [
                            '#3B82F6', // Tailwind blue-500
                            '#EF4444', // Tailwind red-500
                        ],
                    ]],
                ],
                'options' => [
                    'plugins' => [
                        'legend' => ['position' => 'bottom'],
                    ],
                    'responsive' => true,
                ],
            ]));

            // Attendance chart
            $attendanceChartUrl = "https://quickchart.io/chart?c=" . urlencode(json_encode([
                'type' => 'bar',
                'data' => [
                    'labels' => ['Participants'],
                    'datasets' => [
                        [
                            'label' => 'Registered',
                            'data' => [$registered],
                            'backgroundColor' => '#10B981' // emerald-500
                        ],
                        [
                            'label' => 'Attended',
                            'data' => [$attended],
                            'backgroundColor' => '#4010b9' // purple-ish
                        ],
                        [
                            'label' => 'Not Attended',
                            'data' => [$notAttended],
                            'backgroundColor' => '#F43F5E' // rose-500
                        ]
                    ],
                ],
                'options' => [
                    'scales' => [
                        'y' => [
                            'min' => 0,
                            'beginAtZero' => true,
                            'ticks' => ['stepSize' => 5]
                        ]
                    ],
                    'plugins' => [
                        'legend' => [
                            'display' => true,
                            'position' => 'top'
                        ],
                        'datalabels' => [
                            'anchor' => 'end',
                            'align' => 'bottom',
                            'color' => '#000',
                            'font' => [
                                'weight' => 'bold',
                                'size' => 12
                            ]
                        ]
                    ]
                ],
                'plugins' => ['https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels']
            ]));


            $eventData[$event->id] = compact('genderChartUrl', 'attendanceChartUrl', 'registered', 'attended', 'notAttended', 'maleCount', 'femaleCount');
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
                    'speaker' => $categories['speaker'] ?? null, // Note: frontend uses 'speaker'
                    'event' => $categories['events'] ?? null, // Note: frontend uses 'events'
                    'food' => $categories['foods'] ?? null, // Note: frontend uses 'foods'
                    'accommodation' => $categories['accommodation'] ?? null,
                ]);
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
            ->where('user_id', Auth::id())
                ->with( 'categoryRatings') // assuming you have a User relationship  
                ->get();
            

            return response()->json([
                'review' => [
                    'id' => $review[0]->id,
                    'rating' => $review[0]->rating,
                    'comment' => $review[0]->comment,
                    'is_mine' => $review[0]->is_mine,
                    'category_ratings' => $review[0]->categoryRatings ? [
                        'venue' => $review[0]->categoryRatings->first()?->venue ?? 0,
                        'speaker' => $review[0]->categoryRatings->first()?->speaker ?? 0,
                        'events' => $review[0]->categoryRatings->first()?->event ?? 0,
                        'foods' => $review[0]->categoryRatings->first()?->food ?? 0,
                        'accommodation' => $review[0]->categoryRatings->first()?->accommodation ?? 0,
                    ] : null,
                    'user_id' => $review[0]->user_id,
                    'created_at' => $review[0]->created_at,
                ]
            ], 200);
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
