<?php

use App\Models\LabelSetting;
use App\Models\Product;
use App\Services\GeminiFdaTranslator;
use Illuminate\Support\Facades\Route;
use App\Models\Produto;
use App\Models\Request;

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