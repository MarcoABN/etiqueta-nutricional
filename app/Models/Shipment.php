<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipment extends Model
{
    protected $fillable = ['name', 'description'];

    public function steps(): HasMany
    {
        return $this->hasMany(ShipmentStep::class)->orderBy('scheduled_date', 'asc');
    }
}