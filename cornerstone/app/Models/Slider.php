<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Slider extends Model
{
    protected $fillable = [
        'title',
        'image',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $appends = ['image_url'];

    protected static function booted()
    {
        static::updating(function ($slider) {
            if ($slider->isDirty('image')) {
                $oldImage = $slider->getOriginal('image');
                if ($oldImage && \Illuminate\Support\Facades\Storage::disk('public_folder')->exists($oldImage)) {
                    \Illuminate\Support\Facades\Storage::disk('public_folder')->delete($oldImage);
                }
            }
        });

        static::deleting(function ($slider) {
            if ($slider->image && \Illuminate\Support\Facades\Storage::disk('public_folder')->exists($slider->image)) {
                \Illuminate\Support\Facades\Storage::disk('public_folder')->delete($slider->image);
            }
        });
    }

    public function getImageUrlAttribute()
    {
        if (!$this->image) {
            return null;
        }

        $baseUrl = config('app.admin_url', 'https://admin.cornerstoneturfs.com/');
        return rtrim($baseUrl, '/') . '/' . ltrim($this->image, '/');
    }
}
