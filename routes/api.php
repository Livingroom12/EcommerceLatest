<?php

use App\Http\Controllers\LoginController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RegisterController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/login',[LoginController::class,'login']);
Route::post('/register',[RegisterController::class,'register']);
Route::post('/verify-otp',[RegisterController::class,'verifyOtp']);

Route::post('/forget-password', [RegisterController::class,'forgetPassword']);
Route::post('verify-otp', [RegisterController::class, 'verifyOTP']);
Route::post('resetVerify-otp', [RegisterController::class, 'resetVerifyOTP']);


