<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlockBooking extends Model
{
    protected $fillable = [
        'location_id',
        'turf_id',
        'date',
        'reason',
    ];

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function turf()
    {
        return $this->belongsTo(Turf::class);
    }

    public function slots()
    {
        return $this->belongsToMany(Slot::class, 'block_booking_slots');
    }
}
