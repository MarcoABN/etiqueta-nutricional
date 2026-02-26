<?php

namespace App\Filament\Resources\RequestResource\Pages;

use App\Filament\Resources\RequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Models\Request;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Livewire\Component;

class EditRequest extends EditRecord
{
    protected static string $resource = RequestResource::class;

    // --- ADICIONADO: Renderiza o widget de itens no rodapé da página ---
    protected function getFooterWidgets(): array
    {
        return [
            \App\Livewire\RequestItemsWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export_csv')
                ->label('Exportar Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->form([
                    Radio::make('filter_type')
                        ->label('Filtrar por origem:')
                        ->options([
                            'all' => 'Todos',
                            'registered' => 'Somente Cadastrados',
                            'manual' => 'Somente Manuais',
                        ])
                        ->default('all')
                        ->inline()
                        ->required(),
                ])
                ->action(function (Request $record, array $data) {
                    return response()->streamDownload(function () use ($record, $data) {
                        echo "\xEF\xBB\xBF"; 
                        $handle = fopen('php://output', 'w');
                        
                        $query = $record->items();

                        if ($data['filter_type'] === 'registered') {
                            $query->whereNotNull('product_id');
                        } elseif ($data['filter_type'] === 'manual') {
                            $query->whereNull('product_id');
                        }

                        $items = $query->get();

                        $registered = $items->filter(fn($i) => !empty($i->product_id));
                        $manual = $items->filter(fn($i) => empty($i->product_id));

                        if ($registered->isNotEmpty()) {
                            fputcsv($handle, ['--- PRODUTOS CADASTRADOS ---'], ';');
                            fputcsv($handle, ['ID Pedido', 'Cód WinThor', 'Produto', 'Qtd', 'Valor Un', 'Valor Total', 'Obs'], ';');

                            foreach ($registered as $item) {
                                $valUn = $item->unit_price ? number_format($item->unit_price, 2, ',', '') : '0,00';
                                $valTot = number_format($item->quantity * ($item->unit_price ?? 0), 2, ',', '');

                                fputcsv($handle, [
                                    $record->display_id,
                                    $item->winthor_code,
                                    $item->product_name,
                                    number_format($item->quantity, 2, ',', ''),
                                    $valUn,
                                    $valTot,
                                    $item->observation
                                ], ';');
                            }
                        }

                        if ($registered->isNotEmpty() && $manual->isNotEmpty()) {
                            fputcsv($handle, [], ';'); 
                            fputcsv($handle, [], ';'); 
                        }

                        if ($manual->isNotEmpty()) {
                            fputcsv($handle, ['--- ITENS MANUAIS ---'], ';');
                            fputcsv($handle, ['ID Pedido', 'Tipo', 'Descrição do Item', 'Qtd', 'Valor Un', 'Valor Total', 'Obs'], ';');

                            foreach ($manual as $item) {
                                $valUn = $item->unit_price ? number_format($item->unit_price, 2, ',', '') : '0,00';
                                $valTot = number_format($item->quantity * ($item->unit_price ?? 0), 2, ',', '');

                                fputcsv($handle, [
                                    $record->display_id,
                                    'MANUAL',
                                    $item->product_name,
                                    number_format($item->quantity, 2, ',', ''),
                                    $valUn,
                                    $valTot,
                                    $item->observation
                                ], ';');
                            }
                        }

                        fclose($handle);
                    }, "pedido_{$record->display_id}.csv");
                }),

            Actions\Action::make('print')
                ->label('Imprimir')
                ->icon('heroicon-o-printer')
                ->form([
                    Radio::make('filter_type')
                        ->label('Filtrar por origem:')
                        ->options([
                            'all' => 'Todos',
                            'registered' => 'Somente Cadastrados',
                            'manual' => 'Somente Manuais',
                        ])
                        ->default('all')
                        ->inline()
                        ->required(),

                    Select::make('order_by')
                        ->label('Ordenação dos Itens')
                        ->options([
                            'product_name' => 'Descrição do Produto (Alfabética)',
                            'created_at' => 'Ordem de Inserção (Cronológica)',
                        ])
                        ->default('product_name')
                        ->required()
                        ->native(false),
                ])
                ->action(function (Request $record, array $data, Component $livewire) {
                    $url = route('request.print', [
                        'record' => $record,
                        'filter_type' => $data['filter_type'],
                        'order_by' => $data['order_by']
                    ]);
                    
                    $livewire->js("window.open('$url', '_blank')");
                }),

            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}