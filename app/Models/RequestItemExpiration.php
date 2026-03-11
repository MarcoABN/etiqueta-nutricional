<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestItemExpiration extends Model
{
    protected $guarded = ['id'];
    
    protected $casts = [
        'expiration_date' => 'date',
    ];

    public function requestItem()
    {
        return $this->belongsTo(RequestItem::class);
    }
}