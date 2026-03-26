<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pallet extends Model
{
    use HasUuids;

    protected $guarded = [];

    public function request()
    {
        return $this->belongsTo(Request::class);
    }

    public function pallets(): HasMany
    {
        return $this->hasMany(Pallet::class);
    }
}