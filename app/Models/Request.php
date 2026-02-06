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
            // Lógica para gerar ID legível: SOL-{ANO}-{SEQUENCIA}
            $year = date('Y');
            // Busca o último número deste ano (Postgres safe)
            $last = DB::table('requests')
                ->where('display_id', 'like', "SOL-{$year}-%")
                ->orderBy('display_id', 'desc')
                ->first();

            $sequence = 1;
            if ($last) {
                $parts = explode('-', $last->display_id);
                $sequence = intval(end($parts)) + 1;
            }

            $model->display_id = sprintf('SOL-%s-%04d', $year, $sequence);
        });
    }

    public function items()
    {
        return $this->hasMany(RequestItem::class);
    }
}