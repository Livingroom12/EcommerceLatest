<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        // Validate Request
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:8'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors occurred',
                'errors' => $validator->errors(),
                'result' => (object) [],
            ], 422);
        }

        // Find User
        $user = User::where('email', $request->email)->first();
        // dd($user);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Credentials do not exist in our records',
                'result' => (object) [],
            ], 401);
        }

        // Check Password
        if (!Hash::check(trim($request->password), $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Incorrect password',
                'result' => (object) [],
            ], 401);
        }

        // Generate Token with a proper name
        $token = $user->createToken('User_Login_Token')->plainTextToken;


        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'result' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_id' => $user->role_id,
                'access_token' => $token,
            ],
        ], 200);
    }


}
