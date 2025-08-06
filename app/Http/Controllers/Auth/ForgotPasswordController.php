<?php

// app/Http/Controllers/Auth/ForgotPasswordController.php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PasswordReset;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ForgotPasswordController extends Controller
{
    public function forgotPassword(Request $request)
    {
        // $request->inp(['email' => 'required|email|exists:users,email']);

        $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = now()->addMinutes(10);

        PasswordReset::updateOrCreate(
            ['email' => $request->email],
            ['otp' => $otp, 'expires_at' => $expiresAt]
        );

        // Send OTP via email
        Mail::raw("Your OTP for password reset is: $otp\nIt expires in 10 minutes.", function ($message) use ($request) {
            $message->to($request->email)->subject('Password Reset OTP');
        });

        return response()->json([
            'message' => 'OTP sent to your email.',
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            // 'email' => 'required|email|exists:users,email',
            'otp' => 'required|string|size:6',
        ]);

        $reset = PasswordReset::where('email', $request->email)
            ->where('otp', $request->otp)
            ->where('expires_at', '>', now())
            ->first();

        if (!$reset) {
            return response()->json([
                'message' => 'Invalid or expired OTP.',
            ], 400);
        }

        return response()->json(['message' => 'OTP verified.']);
    }

    public function resetPassword(Request $request)
    {
        // $request->validate([
        //     // 'email' => 'required|email|exists:users,email',
        //     'otp' => 'required|string|size:6',
        //     'new_password' => 'required|string|min:6|confirmed',
        // ]);

        $reset = PasswordReset::where('email', $request->email)
            ->where('otp', $request->otp)
            ->where('expires_at', '>', now())
            ->first();

        if (!$reset) {
            return response()->json([
                'message' => 'Invalid or expired OTP.',
            ], 400);
        }

        $user = User::where('email', $request->email)->first();
        $user->password = bcrypt($request->new_password);
        $user->save();

        // Delete used OTP
        $reset->delete();

        return response()->json([
            'message' => 'Password reset successfully.',
        ]);
    }
}