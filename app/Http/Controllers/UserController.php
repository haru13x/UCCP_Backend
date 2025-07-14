<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserDetails;
use Dotenv\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    // In UserController.php
    public function index()
    {
        $users = User::get(); // Use ->get() instead of ->all()
        return response()->json($users, 200);
    }
    public function searchUsers(Request $request)
    {
        $search = $request->search ?? $request->query('search');
        $eventId = $request->event_id ?? $request->query('event_id');
        $users = User::with(['details', 'registeredUsers' => function ($q) use ($eventId) {
            $q->where('event_id', $eventId);
        }])
            ->when($search, function ($query) use ($search) {
                $query->whereHas('details', function ($q) use ($search) {
                    $q->where('first_name', 'like', "%$search%")
                        ->orWhere('last_name', 'like', "%$search%");
                })->orWhere('id', 'like', "%$search%");
            })
            ->take(20)
            ->get()
            ->map(function ($user) {
                $user->is_registered = $user->registeredUsers->isNotEmpty();
                unset($user->registeredUsers); // optional cleanup
                return $user;
            });


        return response()->json($users);
    }

    public function register(Request $request)
    {
        DB::connection('mysql')->beginTransaction();
        try {
            $apiToken = Str::random(60); // Generate 60-char token
            $name = $request->last_name ?? $request->lastName . $request->first_name ?? $request->firstName;
            $user = User::create([
                'username' => $request->username ?? $request->email, // Use email as username if not provided
                'name' => $name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'api_token' => $apiToken,
                'role_id' => $request->role ?? 2, // Ensure this is passed in the request
                'status_id' => 1,
            ]);

            $user->details()->create([
                'user_id' => $user->id,
                'first_name' => $request->first_name ?? $request->firstName,
                'last_name' => $request->last_name ?? $request->lastName,
                'middle_name' => $request->middle_name,
                'birthdate' => $request->birthdate,
                'sex_id' => $request->gender,
                'status_id' => 1,
                'phone_number' => $request->phone,
                'created_by' => $user->id,

            ]);
            DB::connection('mysql')->commit();
            return response()->json([
                'message' => 'User registered successfully',
                'user' => $user->load('details', 'role'),
                'api_token' => $apiToken,
            ], 201);
        } catch (\Exception $e) {
            DB::connection('mysql')->rollback();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required',
            'password' => 'required'
        ]);

        $user = User::with('details', 'role')->where('email', $request->email)
        ->orWhere('username',$request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid email or password'], 401);
        }

        // Generate token
        $token = Str::random(60);
        $user->api_token = $token;
        $user->save();

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'api_token' => $token
        ]);
    }

    public function update(Request $request)
    {
        // Validate input
        
        $user = User::findOrFail($request->user_id);

        // Update user basic info
        $user->update([
            'name' => $request->name ?? $user->name,
            'username' => $request->username ?? $user->username,
            'email' => $request->email ?? $user->email,
            'role_id' => $request->role ?? $user->role_id,
        ]);

        // If password is being updated
        if ($request->filled('password')) {
            $user->password = bcrypt($request->password);
            $user->save();
        }

        // Update user_details (create if missing)
        $details = $user->details ?: new UserDetails(['user_id' => $user->id]);

        $details->first_name = $request->firstName ?? $details->first_name;
        $details->middle_name = $request->middleName ?? $details->middle_name;
        $details->last_name = $request->lastName ?? $details->last_name;
        $details->birthdate = $request->birthdate ?? $details->birthdate;
        $details->address = $request->address ?? $details->address;
        $details->phone_number = $request->phone ?? $details->phone_number;
        $details->sex_id = $request->gender ?? $details->sex_id;

        $details->save();

        return response()->json([
            'message' => 'User updated successfully',
            'user' => $user->fresh(),
        ]);
    }
}
