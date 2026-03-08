<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Login;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'message' => 'User registered successfully',
            'user'    => $user,
        ], 201);
    }

    public function login(Request $request)
    {
        $key = 'login:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'error' => 'Too many login attempts. Try again in ' . RateLimiter::availableIn($key) . ' seconds.',
            ], 429);
        }

        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            RateLimiter::hit($key);
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        RateLimiter::clear($key);

        Login::create([
            'user_id'    => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
        ]);

        $expiration = $request->boolean('rememberMe') ? now()->addDays(30) : now()->addHours(24);
        $token = $user->createToken('auth_token', [], $expiration)->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user'    => $user,
            'token'   => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        Log::info('User logged out', [
            'user_id'    => $request->user()->id,
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function user(Request $request)
    {
        return response()->json($request->user());
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name'          => 'sometimes|required|string|max:255',
            'email'         => 'sometimes|required|email|max:255|unique:users,email,' . $user->id,
            'password'      => 'sometimes|required|string|min:8|confirmed',
            'profile_image' => 'sometimes|required|string',
        ]);

        $data = $request->only(array_filter(['name', 'email', 'profile_image'], fn($k) => $request->has($k), ARRAY_FILTER_USE_KEY));
        $sensitiveChanged = $request->hasAny(['email', 'password']);

        if ($request->has('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        Notification::create([
            'user_id' => $user->id,
            'type'    => 'success',
            'title'   => 'Account Updated',
            'message' => 'Your account settings have been updated successfully.',
            'read'    => false,
        ]);

        if ($sensitiveChanged) {
            $user->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();
            $newToken = $user->createToken('auth_token', [], now()->addHours(24))->plainTextToken;

            return response()->json([
                'message' => 'Profile updated. New token issued for security.',
                'user'    => $user,
                'token'   => $newToken,
            ]);
        }

        return response()->json([
            'message' => 'Profile updated successfully',
            'user'    => $user,
        ]);
    }

    // ✅ FIXED: removed dead code after return in try block
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            // Security: don't reveal if email exists
            return response()->json([
                'message' => 'If that email is in our system, we sent a reset link.',
            ]);
        }

        $token = bin2hex(random_bytes(32));
        $user->password_reset_token      = $token;
        $user->password_reset_expires_at = now()->addHour();
        $user->save();

        $resetUrl = env('FRONTEND_URL') . '/reset-password?token=' . $token . '&email=' . urlencode($user->email);

        try {
            Mail::send('emails.password_reset', ['resetLink' => $resetUrl], function ($message) use ($user) {
                $message->to($user->email)->subject('Password Reset Request');
            });

            return response()->json([
                'status'  => 'success',
                'message' => 'Password reset email sent successfully!',
            ]);
        } catch (\Exception $e) {
            Log::error('Password reset email failed: ' . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to send reset email. Please try again later.',
            ], 500);
        }
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'token'    => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::where('email', $request->email)
            ->where('password_reset_token', $request->token)
            ->where('password_reset_expires_at', '>', now())
            ->first();

        if (!$user) {
            return response()->json(['message' => 'Invalid or expired token.'], 400);
        }

        $user->password                  = Hash::make($request->password);
        $user->password_reset_token      = null;
        $user->password_reset_expires_at = null;
        $user->save();

        Notification::create([
            'user_id' => $user->id,
            'type'    => 'success',
            'title'   => 'Password Reset',
            'message' => 'Your password has been reset successfully.',
            'read'    => false,
        ]);

        return response()->json(['message' => 'Password reset successfully.']);
    }
}
