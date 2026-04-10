<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pricing extends Model
{
    protected $guarded = [];

    public function settlement()
    {
        return $this->belongsTo(Settlement::class);
    }

    public function items()
    {
        return $this->hasMany(PricingItem::class);
    }
}