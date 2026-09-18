<?php

namespace App\Filament\Resources\SettlementResource\Pages;

use App\Exports\SettlementsConsolidatedExport;
use App\Filament\Resources\SettlementResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;

class ListSettlements extends ListRecords
{
    protected static string $resource = SettlementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export_consolidated')
                ->label('Exportar Consolidado')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->tooltip('Exporta em XLSX os fechamentos listados, respeitando busca e filtros aplicados.')
                ->action(function () {
                    $query = $this->getFilteredSortedTableQuery();

                    if (!$query->exists()) {
                        Notification::make()
                            ->title('Nenhum fechamento encontrado para exportar.')
                            ->warning()
                            ->send();

                        return null;
                    }

                    return SettlementsConsolidatedExport::download($query);
                }),

            Actions\CreateAction::make(),
        ];
    }

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return MaxWidth::Full;
    }
}
