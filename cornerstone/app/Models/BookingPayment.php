<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingPayment extends Model
{
    protected $fillable = [
        'booking_id',
        'type',
        'amount',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
