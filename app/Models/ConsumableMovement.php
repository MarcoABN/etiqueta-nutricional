<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsumableMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'consumable_id',
        'user_id',
        'type',
        'quantity',
        'previous_balance',
        'current_balance',
        'reason',
    ];

    public function consumable(): BelongsTo
    {
        return $this->belongsTo(Consumable::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}