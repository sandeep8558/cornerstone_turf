<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SlotCategory extends Model
{
    protected $fillable = [
        'category_name',
        'is_active',
    ];

    public function slots()
    {
        return $this->hasMany(Slot::class);
    }}
