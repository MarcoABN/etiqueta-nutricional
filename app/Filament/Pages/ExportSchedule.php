<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;

class ExportSchedule extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $title = 'Cronograma de Exportação';
    protected static ?string $navigationGroup = 'Operação';
    protected static ?int $navigationSort = 2;
    protected static string $view = 'filament.pages.export-schedule';

    // Libera a largura máxima da tela para não espremer o calendário
    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::Full;
    }
}