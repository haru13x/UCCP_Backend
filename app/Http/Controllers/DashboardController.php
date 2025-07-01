<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Event;
use App\Models\Meeting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
public function chart(Request $request)
{
    $year = $request->get('year', date('Y'));

    $events = DB::table('events')
        ->selectRaw('MONTHNAME(start_date) as month, COUNT(*) as count')
        ->whereYear('start_date', $year)
        ->groupBy(DB::raw('MONTH(start_date)'), DB::raw('MONTHNAME(start_date)'))
        ->orderByRaw('MONTH(start_date)')
        ->get();

    $meetings = DB::table('meetings')
        ->selectRaw('MONTHNAME(start_date) as month, COUNT(*) as count')
        ->whereYear('start_date', $year)
        ->groupBy(DB::raw('MONTH(start_date)'), DB::raw('MONTHNAME(start_date)'))
        ->orderByRaw('MONTH(start_date)')
        ->get();
    $users = DB::table('users')
        ->selectRaw('MONTHNAME(created_at) as month, COUNT(*) as count')
        ->whereYear('created_at', $year)
        ->groupBy(DB::raw('MONTH(created_at)'), DB::raw('MONTHNAME(created_at)'))
        ->orderByRaw('MONTH(created_at)')
        ->get();

    $totalEvents = DB::table('events')->whereYear('start_date', $year)->count();
    $totalMeetings = DB::table('meetings')->whereYear('start_date', $year)->count();
 $totalUsers = DB::table('users')->whereYear('created_at', $year)->count();

    return response()->json([
        'events' => $events,
        'meetings' => $meetings,
        'users' => $users,
        'totalEvents' => $totalEvents,
        'totalMeetings' => $totalMeetings,
        'totalUsers' => $totalUsers,
    ]);}

public function summary()
{
    return response()->json([
        'users' => User::count(),
        'events' => Event::count(),
        'meetings' => Meeting::count(),
    ]);
}
}
