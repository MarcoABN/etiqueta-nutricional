<?php

namespace App\Models;

use Exception;
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

    protected static function booted(): void
    {
        // Impede Criação, Atualização ou Deleção se a Solicitação estiver trancada
        $lockdownCheck = function (Pallet $pallet) {
            // Carrega a solicitação e o settlement caso não estejam carregados
            $pallet->loadMissing('request.settlement');
            $request = $pallet->request;

            $isLocked = ($request?->is_locked ?? false) || ($request?->settlement?->is_locked ?? false);

            if ($isLocked) {
                throw new Exception("Operação negada: A solicitação vinculada a este pallet já está consolidada.");
            }
        };

        static::creating($lockdownCheck);
        static::updating($lockdownCheck);
        static::deleting($lockdownCheck);
    }
}
