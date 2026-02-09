<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LabelSetting extends Model
{
    // Permite atribuição em massa de todos os campos
    protected $guarded = [];

    // Opcional: Casts para garantir tipos numéricos
    protected $casts = [
        'padding_top' => 'float',
        'padding_bottom' => 'float',
        'padding_left' => 'float',
        'padding_right' => 'float',
        'gap_width' => 'float',
        'font_scale' => 'integer',
    ];
}