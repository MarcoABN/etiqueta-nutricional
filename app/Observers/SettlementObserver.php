<?php

namespace App\Observers;

use App\Models\Settlement;

class SettlementObserver
{
    public function saved(Settlement $settlement): void
    {
        $factorDec = ($settlement->calculation_factor ?: 70) / 100;
        $totalValue = $settlement->total_value;
        $totalExpenses = $settlement->total_expenses;

        $request = $settlement->request()->with('items')->first();

        // Limpa os itens antigos para recalcular
        $settlement->items()->delete();

        foreach ($request->items as $item) {
            $initial = ($item->quantity ?? 0) * ($item->unit_price ?? 0);
            $partial = $factorDec > 0 ? $initial / $factorDec : 0;
            $apportionment = $totalValue > 0 ? ($partial / $totalValue) * $totalExpenses : 0;
            $final = $partial + $apportionment;

            $settlement->items()->create([
                'request_item_id' => $item->id,
                'initial_value' => round($initial, 2),
                'partial_value' => round($partial, 2),
                'final_value' => round($final, 2),
            ]);
        }
    }
}