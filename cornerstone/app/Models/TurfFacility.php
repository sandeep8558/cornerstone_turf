<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TurfFacility extends Model
{
    protected $fillable = ['turf_id', 'name', 'is_active'];

    public function turf()
    {
        return $this->belongsTo(Turf::class);
    }
}
