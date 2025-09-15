<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            return response()->json([
                'error' => 'Unauthorized: No token provided',
                'message' => 'Please login to update profile'
            ], 401);
        }
        
        $user = User::with('accountType')->where('api_token', $token)->first();
        
        if (!$user) {
            return response()->json([
                'error' => 'Unauthorized: Invalid token or insufficient permissions',
                'message' => 'Please login to update profile',
                'debug' => [
                    'token_provided' => !empty($token),
                    'token_length' => strlen($token ?? ''),
                    'token_preview' => $token ? substr($token, 0, 10) . '...' : 'null'
                ]
            ], 401);
        }

        // Add the user to the request
        $request->merge(['user' => $user]);
        Auth::setUser($user);
        return $next($request);
    }
}
