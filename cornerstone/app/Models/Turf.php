<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Turf extends Model
{
    protected $fillable = [
        'location_id',
        'name',
        'description',
        'turf_type',
        'area',
        'equipments',
        'is_active',
    ];

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function photos()
    {
        return $this->hasMany(TurfPhoto::class);
    }

    public function facilities()
    {
        return $this->hasMany(TurfFacility::class);
    }

    public function sports()
    {
        return $this->hasMany(TurfSport::class);
    }

    public function slots()
    {
        return $this->hasMany(Slot::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function blockBookings()
    {
        return $this->hasMany(BlockBooking::class);
    }
}
