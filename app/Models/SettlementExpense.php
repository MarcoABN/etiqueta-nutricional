<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettlementExpense extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    protected $fillable = [ ];

    protected $casts = [
        'use_custom_quote' => 'boolean',
        'custom_usd_quote' => 'decimal:4',
    ];

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(Settlement::class);
    }
}