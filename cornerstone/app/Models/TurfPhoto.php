<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TurfPhoto extends Model
{
    protected $fillable = ['turf_id', 'photo'];
    protected $appends = ['photo_url'];

    public function getPhotoUrlAttribute()
    {
        if (!$this->photo)
            return null;

        $baseUrl = config('app.admin_url', 'https://admin.cornerstoneturfs.com/');
        return rtrim($baseUrl, '/') . '/' . ltrim($this->photo, '/');
    }

    public function turf()
    {
        return $this->belongsTo(Turf::class);
    }
}
