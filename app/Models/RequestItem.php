<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;

class RequestItem extends Model
{
    use HasUuids, SoftDeletes;

    public function request()
    {
        return $this->belongsTo(Request::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function expirations()
    {
        return $this->hasMany(RequestItemExpiration::class);
    }

    protected $fillable = [
        'request_id',
        'product_id',
        'winthor_code',
        'product_name',
        'product_name_en', // Novo
        'ncm',             // Novo
        'barcode',         // Novo
        'pesoliq',         // Novo
        'qtunitcx',        // Novo
        'unidade',         // Novo
        'quantity',
        'packaging',
        'unit_price',
        'observation',
    ];
}
