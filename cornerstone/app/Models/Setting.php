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
        'support_phone',
        'whatsapp_booking_message',
    ];

    public static function defaultWhatsappBookingMessage(): string
    {
        return "📋 Booking Details\n" .
            "━━━━━━━━━━━━━━━\n" .
            "👤 Name : {name}\n" .
            "📅 Date : {date}\n" .
            "⏰ Time : {time}\n" .
            "⏱️ Duration : {duration}\n" .
            "🟩 Box : {box}\n\n" .
            "💳 Payment Summary\n" .
            "Sub Total : ₹ {sub_total}\n" .
            "Coupon Discount : ₹ {coupon_discount}\n" .
            "Additional Discount : ₹ {additional_discount}\n" .
            "Total Amount : ₹ {total_amount}\n" .
            "Advance Paid : ₹ {paid_amount}\n" .
            "Due Amount : ₹ {due_amount}\n\n" .
            "📞 Support Contact : {support_contact}\n\n" .
            "Thank you for your booking!\n\n" .
            "Regards,\n" .
            "Cornerstone Turf\n\n" .
            "⚠️ Notes :\n" .
            "✦ Once a transaction is confirmed, it cannot be Cancelled & Amounts are non-refundable.";
    }
}
