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
            Actions\Action::make('export_invoice')
                ->label('Exportar Invoice')
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->action(function (Request $record) {
                    return response()->streamDownload(function () use ($record) {

                        $writer = new \OpenSpout\Writer\XLSX\Writer();
                        $writer->openToFile('php://output');

                        // ==========================================
                        // ABA 1: INVOICE
                        // ==========================================
                        $sheet1 = $writer->getCurrentSheet();
                        $sheet1->setName('Invoice');

                        // Cabeçalho Aba 1
                        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                            'ITENS',
                            'NCM',
                            'DESCRIPTION',
                            'UNID',
                            'QTDE',
                            'Vlr Unitario',
                            'TOTAL'
                        ]));

                        $items = $record->items()->with('product')->get();

                        $sequential = 1;
                        $sumQtde = 0;
                        $sumTotal = 0;

                        // Variáveis para guardar os dados da Aba 2
                        $sumNetWeight = 0;
                        $sheet2Data = [];

                        foreach ($items as $item) {
                            $ncm = $item->product ? $item->product->ncm : '';

                            // Busca o nome em inglês. Se vazio ou item manual, usa o nome padrão
                            $description = $item->product_name;
                            if ($item->product && !empty($item->product->product_name_en)) {
                                $description = $item->product->product_name_en;
                            }

                            $unid = $item->packaging;
                            $qtde = (float) $item->quantity;
                            $vlrUnit = (float) ($item->unit_price ?? 0);
                            $total = $qtde * $vlrUnit;

                            $sumQtde += $qtde;
                            $sumTotal += $total;

                            // Escreve a linha na Aba 1
                            $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                                $sequential,
                                $ncm,
                                $description,
                                $unid,
                                $qtde,
                                $vlrUnit,
                                $total
                            ]));

                            // ------------------------------------------------
                            // CÁLCULOS PARA A ABA 2 (PACKING LIST)
                            // ------------------------------------------------
                            $pesoliq = 0;
                            $qtunitcx = 1;

                            if ($item->product) {
                                // Converte string "1,5" para float "1.5" para evitar erros de cálculo
                                $pesoliq = (float) str_replace(',', '.', $item->product->pesoliq ?? '0');
                                $qtunitcx = (float) str_replace(',', '.', $item->product->qtunitcx ?? '1');
                            }

                            // Qtd. Unit. na Caixa * Peso Líquido * Quantidade de Caixas
                            $netWeight = $qtunitcx * $pesoliq * $qtde;
                            $sumNetWeight += $netWeight;

                            // Guarda a linha na memória para escrever depois
                            $sheet2Data[] = [
                                $sequential,
                                $ncm,
                                $description,
                                $netWeight, // NET W.
                                '',         // GROSS W. (Vazio)
                                $qtde       // BOXES QTY
                            ];

                            $sequential++;
                        }

                        // Linha de Totais da Aba 1
                        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                            '',
                            '',
                            '',
                            'TOTAIS',
                            $sumQtde,
                            '',
                            $sumTotal
                        ]));


                        // ==========================================
                        // ABA 2: PACKING LIST (PESOS E CAIXAS)
                        // ==========================================
                        $sheet2 = $writer->addNewSheetAndMakeItCurrent();
                        $sheet2->setName('Packing List');

                        // Cabeçalho Aba 2
                        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                            'ITENS',
                            'NCM',
                            'DESCRIPTION',
                            'NET W.',
                            'GROSS W.',
                            'BOXES QTY'
                        ]));

                        // Escreve os dados que estavam guardados
                        foreach ($sheet2Data as $row) {
                            $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues($row));
                        }

                        // Linha de Totais da Aba 2
                        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues([
                            '',
                            '',
                            'TOTAIS',
                            $sumNetWeight,
                            '',
                            $sumQtde
                        ]));

                        $writer->close();
                    }, "invoice_{$record->display_id}.xlsx");
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
