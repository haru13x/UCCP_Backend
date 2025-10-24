<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Event;
use App\Models\EventMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Get chart data for dashboard
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function chart(Request $request)
    {
        $year = $request->get('year', date('Y'));
        $user = auth()->user();

        // Base query for events
        $eventsQuery = Event::query()
            ->whereYear('start_date', $year);

        // If not admin, filter events by user's group_id
        if ($user->role_id !== 1) {
            $eventsQuery->whereHas('eventModes', function ($query) use ($user) {
                $query->where('account_group_id', $user->group_id)
                    ->where('status_id', 1);
            });
        }

        // Get monthly event counts
        $events = $eventsQuery->select(
                DB::raw('MONTHNAME(start_date) as month'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy(DB::raw('MONTH(start_date)'), DB::raw('MONTHNAME(start_date)'))
            ->orderByRaw('MONTH(start_date)')
            ->get();

        // Get monthly user registrations
        $users = User::select(
                DB::raw('MONTHNAME(created_at) as month'),
                DB::raw('COUNT(*) as count')
            )
            ->whereYear('created_at', $year)
            ->when($user->role_id !== 1, function ($query) use ($user) {
                $query->where('group_id', $user->group_id);
            })
            ->groupBy(DB::raw('MONTH(created_at)'), DB::raw('MONTHNAME(created_at)'))
            ->orderByRaw('MONTH(created_at)')
            ->get();

        // Get totals
        $totalEvents = $eventsQuery->count();
        $totalUsers = User::when($user->role_id !== 1, function ($query) use ($user) {
                $query->where('group_id', $user->group_id);
            })
            ->whereYear('created_at', $year)
            ->count();

        // Get event categories distribution
        $eventCategories = Event::select('category', 'isconference', DB::raw('COUNT(*) as count'))
            ->when($user->role_id !== 1, function ($query) use ($user) {
                $query->whereHas('eventModes', function ($q) use ($user) {
                    $q->where('account_group_id', $user->group_id)
                        ->where('status_id', 1);
                });
            })
            ->whereYear('start_date', $year)
            ->groupBy('category', 'isconference')
            ->get();

        return response()->json([
            'events' => $events,
            'users' => $users,
            'totalEvents' => $totalEvents,
            'totalUsers' => $totalUsers,
            'eventCategories' => $eventCategories,
            'year' => $year
        ]);
    }

    /**
     * Get summary data for dashboard
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function summary()
    {
        $user = auth()->user();
        $today = Carbon::today();

        // Base queries
        $eventsQuery = Event::query();
        $usersQuery = User::query();

        // Apply filters for non-admin users
        if ($user->role_id !== 1) {
            $eventsQuery->whereHas('eventModes', function ($query) use ($user) {
                $query->where('account_group_id', $user->group_id)
                    ->where('status_id', 1);
            });
            $usersQuery->where('group_id', $user->group_id);
        }

        return response()->json([
            'users' => $usersQuery->count(),
            'events' => $eventsQuery->count(),
            'todayEvents' => $eventsQuery->clone()->whereDate('start_date', $today)->count(),
            'newUsers' => $usersQuery->clone()->whereDate('created_at', $today)->count(),
            'upcomingEvents' => $eventsQuery->clone()
                ->where('start_date', '>', $today)
                ->orderBy('start_date')
                ->limit(5)
                ->with(['eventModes' => function ($query) {
                    $query->where('status_id', 1);
                }])
                ->get()
        ]);
    }

    /**
     * Get events for a specific date
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getEventsByDate(Request $request)
    {
        $date = Carbon::parse($request->date);
        $user = auth()->user();

        $eventsQuery = Event::query()
            ->whereDate('start_date', $date)
            ->with(['eventModes' => function ($query) {
                $query->where('status_id', 1)
                    ->with('eventGroup');
            }]);

        // If not admin, filter events by user's group_id
        if ($user->role_id !== 1) {
            $eventsQuery->whereHas('eventModes', function ($query) use ($user) {
                $query->where('account_group_id', $user->group_id)
                    ->where('status_id', 1);
            });
        }

        $events = $eventsQuery->get();

        return response()->json([
            'events' => $events,
            'date' => $date->format('Y-m-d'),
            'isAdmin' => $user->role_id === 1
        ]);
    }
}