<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /**
     * Display the system settings.
     */
    public function index()
    {
        $settings = Setting::first();
        
        if (!$settings) {
            return response()->json(['message' => 'Settings not found'], 404);
        }

        // We only return public-safe settings to the mobile app
        return response()->json([
            'razorpay_key' => $settings->razorpay_key,
            'is_part_payment_active' => (bool)$settings->is_part_payment_active,
            'min_part_payment' => (float)$settings->min_part_payment,
            'is_pay_at_location_active' => (bool)$settings->is_pay_at_location_active,
            'booking_open_days' => (int)$settings->booking_open_days,
        ]);
    }
}
