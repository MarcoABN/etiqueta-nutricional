<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentStep extends Model
{
    protected $fillable = ['shipment_id', 'name', 'responsible_name', 'scheduled_date', 'is_completed'];

    protected $casts = [
        'scheduled_date' => 'date',
        'is_completed' => 'boolean',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}