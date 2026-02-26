<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Request extends Model
{
    use HasUuids, SoftDeletes;

    protected $guarded = ['id'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Lógica para gerar ID objetivo: {ANO}{SEQUENCIA} (Ex: 2026001)
            $year = date('Y');
            
            // Busca o último número deste ano
            $last = DB::table('requests')
                ->where('display_id', 'like', "{$year}%")
                ->orderBy('display_id', 'desc')
                ->first();

            $sequence = 1;
            if ($last) {
                // Remove os 4 primeiros caracteres (o ano) para pegar apenas a sequência numérica
                $sequence = intval(substr($last->display_id, 4)) + 1;
            }

            // Formata a string combinando o ano com uma sequência de 3 dígitos (001, 002, etc.)
            $model->display_id = sprintf('%s%03d', $year, $sequence);
        });
    }

    public function items()
    {
        return $this->hasMany(RequestItem::class);
    }
}