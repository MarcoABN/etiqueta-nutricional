<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;

class Settlement extends Model
{
    use HasUuids, SoftDeletes;

    protected $guarded = ['id'];
    protected $fillable = [
        // ... seus outros campos
        'overall_total',
    ];

    public function request()
    {
        return $this->belongsTo(Request::class);
    }

    public function expenses()
    {
        return $this->hasMany(SettlementExpense::class)->orderBy('expense_number');
    }

    public function items()
    {
        return $this->hasMany(SettlementItem::class);
    }
}
