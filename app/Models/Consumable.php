<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Consumable extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'unit',
        'min_quantity',
        'current_quantity',
        'description',
    ];

    public function movements(): HasMany
    {
        return $this->hasMany(ConsumableMovement::class);
    }
}