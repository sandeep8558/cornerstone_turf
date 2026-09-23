<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $fillable = [
        'date',
        'location_id',
        'user_id',
        'turf_id',
        'amount',
        'additional_discount',
        'payment_type',
        'status',
        'players',
        'came',
        'cancelled_at',
        'refund_amount',
    ];

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function turf()
    {
        return $this->belongsTo(Turf::class);
    }

    public function slots()
    {
        return $this->belongsToMany(Slot::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function bookingPayments()
    {
        return $this->hasMany(BookingPayment::class);
    }

    public function getTotalReceivedAttribute()
    {
        return $this->bookingPayments()->sum('amount');
    }

    public function getIsFullyPaidAttribute()
    {
        return $this->total_received >= $this->amount;
    }

    public function couponUsage()
    {
        return $this->hasOne(CouponUsage::class);
    }
}
