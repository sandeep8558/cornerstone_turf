<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\TurfController;
use App\Http\Controllers\Api\SlotController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\OfferController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\SliderController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

Route::get('/locations', [LocationController::class, 'index']);
Route::get('/turfs', [TurfController::class, 'index']);
Route::get('/turf/{id}', [TurfController::class, 'show']);
Route::get('/slots', [SlotController::class, 'index']);
Route::get('/settings', [SettingController::class, 'index']);
Route::get('/offers', [OfferController::class, 'index']);
Route::get('/sliders', [SliderController::class, 'index']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/profile/update', [AuthController::class, 'updateProfile']);
    Route::post('/profile/delete', [AuthController::class, 'deleteAccount']);
    Route::post('/coupons/validate', [OfferController::class, 'validateCoupon']);
    
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::post('/bookings/calculate-long-booking', [BookingController::class, 'calculateLongBooking']);
    Route::post('/bookings/long', [BookingController::class, 'storeLongBooking']);
    Route::get('/manager/bookings', [BookingController::class, 'managerIndex']);
    Route::get('/manager/users', [AuthController::class, 'getUsersForBooking']);
    Route::post('/manager/users', [AuthController::class, 'createClient']);
    Route::post('/manager/bookings/{id}/collect', [BookingController::class, 'collectPayment']);
    Route::post('/manager/bookings/{id}/status', [BookingController::class, 'updateStatus']);
    Route::get('/manager/bookings/{id}', [BookingController::class, 'managerShow']);
    Route::get('/bookings/{id}', [BookingController::class, 'show']);
});
