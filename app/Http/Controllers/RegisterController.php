<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendOtp;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use App\Mail\ExampleMail;

use Illuminate\Support\Str;

class RegisterController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors occurred',
                'errors' => $validator->errors(),
                'result' => (object) [],
            ], 422);
        }

        $otp = rand(1000, 9999);

        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'otp' => $otp,
        ];
        Cache::put('otp_' . $request->email, $data, now()->addMinutes(10));
        Mail::to($request->email)->send(new SendOtp($otp));

        return response()->json([
            'success' => true,
            'message' => 'OTP sent successfully',
            'result' => [
                'otp' => $otp,
            ]
        ], 200);
    }
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'otp' => 'required|numeric|digits:4',
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors occurred',
                'errors' => $validator->errors(),
                'result' => (object) [],
            ], 422);
        }

        $cachedData = Cache::get('otp_' . $request->email);

        if (!$cachedData) {
            return response()->json([
                'success' => false,
                'message' => 'OTP expired or not found',
                'result' => (object) [],
            ], 400);
        }

        if ($request->otp != $cachedData['otp']) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP',
                'result' => (object) [],
            ], 400);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            // User create karo and save OTP
            $user = User::create([
                'name' => $cachedData['name'],
                'email' => $request->email,
                'password' => $cachedData['password'],
                'role_id' => 1,
                'otp' => $request->otp, // ⬅️ Save OTP in user table
            ]);
        } else {
            // User already che to update karo OTP
            $user->otp = $request->otp;
            $user->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'User verified successfully',
            'result' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_id' => $user->role_id,
                'otp' => $user->otp, // Return otp if you want
            ]
        ], 200);
    }

    public function forgetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 400);
        }

        $email = $request->input('email');
        $user = User::where('email', $email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User does not exist',
                'result' => (object) []
            ], 404);
        }

        // Generate OTP and update user record
        $otp = rand(1000, 9999);
        $user->otp = $otp;
        $user->save();

        // Send OTP via email
        $emailContent = new ExampleMail($otp);
        Mail::to($email)->send($emailContent);

        return response()->json([
            'success' => true,
            'message' => 'OTP Created successfully',
            'result' => [
                'otp' => $otp,
            ],
        ]);
    }

    public function resetVerifyOTP(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|numeric',
        ]);

        $email = $request->input('email');
        $otp = $request->input('otp');

        $user = User::where('email', $email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
                'result' => (object) []
            ], 404);
        }

        if ($user->otp != $otp) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP',
                'result' => (object) []
            ], 400);
        }

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'OTP verified successfully',
            'result' => [
                'email' => $email,
            ],
        ]);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|exists:users,email',
            'password' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation error', 'errors' => $validator->errors()], 400);
        }

        $user = User::where('email', $request->input('email'))->first();

        $user->update([
            'password' => $request->input('password')
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password Updated Successfully.'
        ], 200);
    }



    public function completeProfile(Request $request)
    {

        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'gender' => 'nullable|in:male,female,other',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $user = auth()->user();

        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($user->image && file_exists(public_path('storage/' . $user->image))) {
                unlink(public_path('storage/' . $user->image));
            }

            // Create a unique file name
            $image = $request->file('image');
            $imageName = Str::uuid() . '.' . $image->getClientOriginalExtension();

            // Move the file to public/storage/profile_images
            $destinationPath = public_path('storage/profile_images');
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }

            $image->move($destinationPath, $imageName);

            // Save path relative to storage
            $user->image = 'profile_images/' . $imageName;
        }

        // Update other fields
        $user->name = $request->name;
        $user->phone = $request->phone;
        $user->gender = $request->gender;
        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'gender' => $user->gender,
                'image_url' => $user->image ? asset('storage/' . $user->image) : null,
            ],
        ]);
    }


}
