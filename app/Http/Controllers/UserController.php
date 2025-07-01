<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
class UserController extends Controller
{
// In UserController.php
public function index() {
    $users = User::get(); // Use ->get() instead of ->all()
    return response()->json($users, 200);
}


public function register(Request $request)
{
    DB::connection('mysql')->beginTransaction();
    try {
       $apiToken = Str::random(60); // Generate 60-char token
    $name = $request->last_name . $request->first_name;
    $user = User::create([
        'username' => $request->username,
        'name' => $name,
        'email' => $request->email,
        'password' => Hash::make($request->password),
        'api_token' => $apiToken,
        'role_id' => 2,
         'status_id' => 1,
    ]);

   $user->details()->create([
        'user_id' => $user->id,
        'first_name' => $request->first_name,
        'last_name' => $request->last_name,
        'middle_name' => $request->middle_name,
        'sex_id' => $request->gender,
         'status_id' => 1,
         'created_by' => $user->id,
    ]);
    DB::connection('mysql')->commit();
    return response()->json([
        'message' => 'User registered successfully',
        'user' => $user,
        'api_token' => $apiToken,
    ], 201);

    } catch (\Exception $e) {
        DB::connection('mysql')->rollback();
        return response()->json(['error' => $e->getMessage()],500);
    }
   
}

 public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $user = User::with('details','role')->where('email', $request->email)->first();

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
}
