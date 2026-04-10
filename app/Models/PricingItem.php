<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PricingItem extends Model
{
    protected $guarded = [];

    public function pricing()
    {
        return $this->belongsTo(Pricing::class);
    }

    public function settlementItem()
    {
        return $this->belongsTo(SettlementItem::class); // Assumindo que você tem esse model do fechamento
    }
}
