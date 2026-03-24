<?php

namespace App\Filament\Exports;

use App\Models\Settlement;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class SettlementExporter extends Exporter
{
    protected static ?string $model = Settlement::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('request.observation')->label('Descrição da Solicitação'),
            ExportColumn::make('usd_quote')->label('Cotação USD'),
            ExportColumn::make('calculation_factor')->label('Fator de Cálculo'),
            ExportColumn::make('total_value')->label('Valor Total'),
            ExportColumn::make('total_expenses')->label('Total de Despesas'),
            ExportColumn::make('expense_percentage')->label('% Despesa'),
            ExportColumn::make('created_at')->label('Data do Fechamento'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'A exportação dos Fechamentos foi concluída e o Excel (XLSX) está pronto para download.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' linha(s) falharam na exportação.';
        }

        return $body;
    }
}
