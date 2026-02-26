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

    protected function getFooterWidgets(): array
    {
        return [
            \App\Livewire\RequestItemsWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            // --- AÇÃO: EXPORTAR INVOICE ---
            Actions\Action::make('export_invoice')
                ->label('Exportar Invoice')
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->action(function (Request $record) {
                    return response()->streamDownload(function () use ($record) {
                        echo "\xEF\xBB\xBF"; 
                        $handle = fopen('php://output', 'w');
                        
                        fputcsv($handle, ['ITENS', 'NCM', 'DESCRIPTION', 'UNID', 'QTDE', 'Vlr Unitario', 'TOTAL'], ';');

                        $items = $record->items()->with('product')->get();

                        $sequential = 1;
                        $sumQtde = 0;
                        $sumTotal = 0;

                        foreach ($items as $item) {
                            $ncm = $item->product ? $item->product->ncm : '';
                            
                            // Busca o nome em inglês. Se estiver vazio ou for item manual, usa o nome padrão
                            $description = $item->product_name; 
                            if ($item->product && !empty($item->product->product_name_en)) {
                                $description = $item->product->product_name_en;
                            }
                            
                            $unid = $item->packaging;
                            $qtde = $item->quantity;
                            $vlrUnit = $item->unit_price ?? 0;
                            $total = $qtde * $vlrUnit;

                            $sumQtde += $qtde;
                            $sumTotal += $total;

                            fputcsv($handle, [
                                $sequential++,
                                $ncm,
                                $description,
                                $unid,
                                number_format($qtde, 2, ',', ''),
                                number_format($vlrUnit, 2, ',', ''),
                                number_format($total, 2, ',', '')
                            ], ';');
                        }

                        fputcsv($handle, [
                            '',             
                            '',             
                            '',             
                            'TOTAIS',       
                            number_format($sumQtde, 2, ',', ''), 
                            '',             
                            number_format($sumTotal, 2, ',', '') 
                        ], ';');

                        fclose($handle);
                    }, "invoice_{$record->display_id}.csv");
                }),

            // --- AÇÃO: EXPORTAR EXCEL (PADRÃO) ---
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

            // --- AÇÃO: IMPRIMIR ---
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