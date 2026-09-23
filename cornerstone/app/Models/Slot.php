<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Slot extends Model
{
    protected $fillable = [
        'location_id',
        'turf_id',
        'slot_category_id',
        'from',
        'to',
        'minutes',
        'mon_amount',
        'tue_amount',
        'wed_amount',
        'thu_amount',
        'fri_amount',
        'sat_amount',
        'sun_amount',
        'is_active',
    ];

    public function turf()
    {
        return $this->belongsTo(Turf::class);
    }

    public function slotCategory()
    {
        return $this->belongsTo(SlotCategory::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function bookings()
    {
        return $this->belongsToMany(Booking::class);
    }

    public function blockBookingSlots()
    {
        return $this->hasMany(BlockBookingSlot::class);
    }
}
