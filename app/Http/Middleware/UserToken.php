<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

class UserToken
{
    /**
     * Handle an incoming request and validate the API token.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {

        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['error' => 'Unauthorized: No token provided' . $token], 401);
        }
        $user = User::where('api_token', $token)->first();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized: Invalid token or insufficient permissions'], 401);
        }

        // Add the patient to the request
        $request->merge(['user' => $user]);

        return $next($request);
    }
}
