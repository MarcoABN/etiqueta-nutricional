<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids; // 1. Adicione a importação aqui

class SettlementExpense extends Model
{
    use HasFactory, HasUuids; // 2. Adicione a trait aqui ao lado do HasFactory

    protected $guarded = ['id'];

    protected $casts = [
        'use_custom_quote' => 'boolean',
        'custom_usd_quote' => 'decimal:4',
    ];

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(Settlement::class);
    }
}