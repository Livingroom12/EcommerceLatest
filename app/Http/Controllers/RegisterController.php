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

}
