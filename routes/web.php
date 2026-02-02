<?php

use Illuminate\Support\Facades\Route;
use App\Models\Produto;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/print/label/{product}', function (Product $product) {
    $qty = request()->query('qty', 1); // Pega a qtd ou usa 1 como padrão
    return view('labels.fda-114x80', ['product' => $product, 'qty' => $qty]);
})->name('print.label');

Route::get('/print/label/{product}', function (App\Models\Product $product) {
    $qty = request()->query('qty', 1);

    // Carrega configuração do banco (ou usa default se der erro)
    $settings = App\Models\LabelSetting::first() ?? new App\Models\LabelSetting([
        'padding_top' => 2,
        'padding_left' => 2,
        'padding_right' => 2,
        'gap_width' => 6,
        'font_scale' => 100
    ]);

    return view('labels.fda-114x80', [
        'product' => $product,
        'qty' => $qty,
        'settings' => $settings // <--- Passando a variável
    ]);
})->name('print.label');

Route::get('/imprimir-etiqueta/{record}', function (Product $record) {
    return view('etiqueta', ['produto' => $record]);
})->name('imprimir.etiqueta');