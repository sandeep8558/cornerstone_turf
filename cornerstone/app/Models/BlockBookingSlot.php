<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlockBookingSlot extends Model
{
    protected $fillable = [
        'block_booking_id',
        'slot_id',
    ];

    public function blockBooking()
    {
        return $this->belongsTo(BlockBooking::class);
    }

    public function slot()
    {
        return $this->belongsTo(Slot::class);
    }
}
