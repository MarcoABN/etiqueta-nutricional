<?php

use App\Models\LabelSetting;
use App\Models\Pallet;
use App\Models\Product;
use App\Services\GeminiFdaTranslator;
use Illuminate\Support\Facades\Route;
use App\Models\Produto;
use App\Models\Request;
use App\Models\Settlement;

Route::get('/', function () {
    return redirect('/admin/login');
});

Route::get('/print/label/{product}', function (Product $product) {
    $qty = request()->query('qty', 1); // Pega a qtd ou usa 1 como padrão
    return view('labels.fda-114x80', ['product' => $product, 'qty' => $qty]);
})->name('print.label');

Route::get('/print/label/{product}', function (Product $product) {
    $qty = request()->query('qty', 1);

    // CORREÇÃO: Busca configurações no banco ou cria padrão de 0.5mm
    $settings = LabelSetting::firstOrCreate(
        ['id' => 1],
        [
            'padding_top' => 0.5,
            'padding_bottom' => 0.5,
            'padding_left' => 0.5,
            'padding_right' => 0.5,
            'gap_width' => 6.0,
            'font_scale' => 100,
        ]
    );

    // Chama a nova view 'labels.print'
    return view('labels.print', [
        'product' => $product,
        'qty' => $qty,
        'settings' => $settings
    ]);
})->name('print.label');

Route::get('/imprimir-etiqueta/{record}', function (Product $record) {
    return view('etiqueta', ['produto' => $record]);
})->name('imprimir.etiqueta');

// Rota simples para imprimir a solicitação
Route::get('/admin/requests/{record}/print', function (Request $record) {
    // Se o arquivo está em resources/views/print/request.blade.php
    return view('print.request', ['record' => $record]);
})->name('request.print')->middleware('auth');

Route::get('/fechamentos/{settlement}/imprimir', function (Settlement $settlement) {
    // 1. Carrega as relações para evitar consultas repetidas (N+1)
    $settlement->load(['request', 'items.requestItem.product', 'expenses']);

    // 2. Prepara os dados iniciais
    $initialTotal = $settlement->items()->sum('initial_value');
    $overallTotal = $settlement->overall_total;
    $expenses = $settlement->expenses()->orderBy('expense_number')->get();

    // 3. Ordena os itens alfabeticamente (igual ao seu Excel)
    $items = $settlement->items
        ->sortBy(fn($item) => strtolower($item->requestItem?->product_name ?? ''));

    $totalVal = (float) $settlement->total_value;
    $usdQuote = (float) $settlement->usd_quote;

    // Helper para converter BRL para USD
    $toUsd = fn($value) => $usdQuote > 0 ? (float) $value / $usdQuote : 0;

    // 4. Calcula o total de despesas em USD respeitando cotações customizadas
    $totalExpensesUsd = 0;
    foreach ($expenses as $exp) {
        $quoteToUse = ($exp->use_custom_quote && $exp->custom_usd_quote > 0) ? (float) $exp->custom_usd_quote : $usdQuote;
        if ($quoteToUse > 0) {
            $totalExpensesUsd += ((float) $exp->amount / $quoteToUse);
        }
    }

    // 5. Total Geral em USD
    $totalGeralUsd = $toUsd($settlement->total_value) + $totalExpensesUsd;

    // 6. Retorna a View passando todas as variáveis
    return view('print.settlement-report', compact(
        'settlement',
        'initialTotal',
        'overallTotal',
        'expenses',
        'items',
        'totalVal',
        'usdQuote',
        'toUsd',
        'totalExpensesUsd',
        'totalGeralUsd'
    ));
})->name('settlement.print')->middleware(['web', 'auth']);

Route::get('/print/pallet/{pallet}', function (Pallet $pallet) {
    return view('print.pallet-label', compact('pallet'));
})->name('print.pallet');