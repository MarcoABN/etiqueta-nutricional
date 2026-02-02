<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str; // Importante para gerar o UUID

class Product extends Model
{
    use HasFactory;

    // 1. Definimos que o ID não é auto-incremento (1, 2, 3...)
    public $incrementing = false;

    // 2. O tipo do ID é string
    protected $keyType = 'string';

    protected $guarded = ['id'];

    // 3. Evento para criar o UUID automaticamente ao salvar
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Se não tiver ID, gera um UUID v4 padrão
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }
}