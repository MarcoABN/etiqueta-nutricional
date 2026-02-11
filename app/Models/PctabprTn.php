<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PctabprTn extends Model
{
    // AQUI ESTÁ O SEGREDO: O nome deve ser exato
    protected $table = 'PCTABPR_TN'; 

    protected $fillable = [
        'CODFILIAL',
        'CODPROD',
        'CODAUXILIAR',
        'DESCRICAO',
        'CUSTOULTENT',
        'PVENDA',
        'QTESTOQUE',
        'PVENDA_NOVO',
    ];
}