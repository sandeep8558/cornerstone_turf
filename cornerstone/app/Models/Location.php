<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $fillable = [
        'name',
        'address',
        'lat',
        'lon',
    ];

    public function slots()
    {
        return $this->hasMany(Slot::class);
    }

    public function blockBookings()
    {
        return $this->hasMany(BlockBooking::class);
    }
}
