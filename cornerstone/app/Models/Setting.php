<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'booking_open_days',
        'is_cancellation_active',
        'cancellation_hours',
        'cancellation_fee',
        'is_refund_active',
        'is_part_payment_active',
        'min_part_payment',
        'is_pay_at_location_active',
        'razorpay_key',
        'razorpay_secret',
        'sms_gateway_token',
    ];
}
