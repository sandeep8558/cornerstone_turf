<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new user.
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'mobile_number' => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'mobile_number' => $request->mobile_number,
            'password' => Hash::make($request->password),
        ]);

        // Assign default role
        $user->assignRole('Client');

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->load('roles'),
            'is_manager' => $user->hasRole('Manager') || $user->hasRole('Administrator'),
        ], 201);
    }

    /**
     * Login user and create token.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required',
            'password' => 'required',
        ]);

        $loginField = filter_var($request->email, FILTER_VALIDATE_EMAIL) ? 'email' : 'mobile_number';
        $user = User::where($loginField, $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->load('roles'),
            'is_manager' => $user->hasRole('Manager') || $user->hasRole('Administrator'),
        ]);
    }

    /**
     * Logout user (Revoke the token).
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Successfully logged out'
        ]);
    }

    /**
     * Get the authenticated User.
     */
    public function me(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'user' => $user->load('roles'),
            'is_manager' => $user->hasRole('Manager') || $user->hasRole('Administrator'),
        ]);
    }

    /**
     * Send OTP for password reset.
     */
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        // Generate 4 digit OTP
        $otp = rand(1000, 9999);
        
        // Save to database
        DB::table('password_reset_otps')->updateOrInsert(
            ['email' => $request->email],
            [
                'otp' => $otp,
                'expires_at' => now()->addMinutes(10),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Use Laravel Mail to send the OTP
        Mail::raw("Your OTP for Cornerstone Turf password reset is: $otp", function ($message) use ($request) {
            $message->to($request->email)
                    ->subject('Password Reset OTP - Cornerstone Turf');
        });

        return response()->json([
            'message' => 'OTP has been sent to your email.'
        ]);
    }

    /**
     * Verify OTP.
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string|size:4',
        ]);

        $record = DB::table('password_reset_otps')
            ->where('email', $request->email)
            ->where('otp', $request->otp)
            ->where('expires_at', '>', now())
            ->first();

        if (!$record) {
            return response()->json(['message' => 'Invalid or expired OTP.'], 422);
        }

        return response()->json(['message' => 'OTP verified successfully.']);
    }

    /**
     * Reset password using OTP.
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string|size:4',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $record = DB::table('password_reset_otps')
            ->where('email', $request->email)
            ->where('otp', $request->otp)
            ->where('expires_at', '>', now())
            ->first();

        if (!$record) {
            return response()->json(['message' => 'Invalid or expired OTP.'], 422);
        }

        $user = User::where('email', $request->email)->first();
        $user->update(['password' => Hash::make($request->password)]);

        // Delete the OTP record after successful reset
        DB::table('password_reset_otps')->where('email', $request->email)->delete();

        return response()->json(['message' => 'Password has been reset successfully.']);
    }

    /**
     * Update user profile.
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();
        
        $request->validate([
            'name' => 'required|string|max:255',
            'mobile_number' => 'nullable|string|max:20',
            'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120', // Max 5MB
        ]);

        $data = [
            'name' => $request->name,
            'mobile_number' => $request->mobile_number,
        ];

        if ($request->hasFile('profile_photo')) {
            // Delete old photo if exists
            if ($user->profile_photo && \Illuminate\Support\Facades\Storage::disk('public')->exists($user->profile_photo)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($user->profile_photo);
            }
            
            $path = $request->file('profile_photo')->store('profile_photos', 'public');
            $data['profile_photo'] = $path;
        }

        $user->update($data);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user
        ]);
    }

    /**
     * Delete user account.
     */
    public function deleteAccount(Request $request)
    {
        $user = $request->user();

        // Delete profile photo if exists
        if ($user->profile_photo && \Illuminate\Support\Facades\Storage::disk('public')->exists($user->profile_photo)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($user->profile_photo);
        }

        // Optional: delete related records if not cascading in DB
        // e.g. $user->bookings()->delete();
        
        $user->currentAccessToken()->delete();
        $user->delete();

        return response()->json([
            'message' => 'Account deleted successfully'
        ]);
    }

    /**
     * Get users for client booking selection (Managers/Administrators only).
     */
    public function getUsersForBooking(Request $request)
    {
        // Authorization Check
        if (!$request->user()->hasRole('Manager') && !$request->user()->hasRole('Administrator')) {
            return response()->json(['message' => 'Unauthorized. Managers and Administrators only.'], 403);
        }

        $search = $request->query('search', '');

        // If search query is empty or less than 2 characters, do not perform any DB fetch and return empty results
        if (strlen(trim($search)) < 2) {
            return response()->json(['users' => []]);
        }

        $users = User::query()
            ->whereDoesntHave('roles', function ($query) {
                $query->where('name', 'Manager');
            })
            ->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('mobile_number', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email', 'mobile_number']);

        return response()->json(['users' => $users]);
    }

    /**
     * Create a new client user (Managers/Administrators only).
     */
    public function createClient(Request $request)
    {
        // Authorization Check
        if (!$request->user()->hasRole('Manager') && !$request->user()->hasRole('Administrator')) {
            return response()->json(['message' => 'Unauthorized. Managers and Administrators only.'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'mobile_number' => 'required|string|max:20|unique:users',
            'email' => 'nullable|string|email|max:255|unique:users',
        ]);

        // If email is not provided, generate a placeholder email like mobile_number@cornerstoneturfs.com
        $email = $request->email ?: $request->mobile_number . '@cornerstoneturfs.com';

        // Check if email already exists after generation (in case email was empty but already registered)
        if (User::where('email', $email)->exists()) {
            return response()->json(['message' => 'A user with this mobile number/placeholder email already exists.'], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $email,
            'mobile_number' => $request->mobile_number,
            'password' => Hash::make(\Illuminate\Support\Str::random(12)), // Generate random password
        ]);

        // Assign default role
        $user->assignRole('Client');

        return response()->json([
            'message' => 'Client created successfully',
            'user' => $user
        ], 201);
    }
}
