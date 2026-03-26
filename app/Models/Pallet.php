<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Pallet extends Model
{
    use HasUuids;

    protected $guarded = [];

    public function request()
    {
        return $this->belongsTo(Request::class);
    }

    
}