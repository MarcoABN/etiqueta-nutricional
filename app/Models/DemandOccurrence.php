<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemandOccurrence extends Model
{
    use HasFactory;

    protected $fillable = [
        'demand_id',
        'user_id',
        'content',
        'attachments', // <-- Adicionado aqui
    ];

    // Faz o cast (conversão) automático do JSON para Array
    protected function casts(): array
    {
        return [
            'attachments' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function demand(): BelongsTo
    {
        return $this->belongsTo(Demand::class);
    }
}