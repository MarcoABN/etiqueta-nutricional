<?php

namespace App\Filament\Resources\SettlementResource\Pages;

use App\Filament\Resources\SettlementResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Models\Settlement;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

class EditSettlement extends EditRecord
{
    protected static string $resource = SettlementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),

            Actions\Action::make('export_details')
                ->label('Exportar')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->action(function (Settlement $record) {
                    $fileName = 'Fechamento_' . ($record->request->display_id ?? 'Avulso') . '.xlsx';

                    return response()->streamDownload(function () use ($record) {
                        $options = new Options();
                        $writer = new Writer($options);
                        $writer->openToFile('php://output');

                        // ==========================================
                        // PREPARAÇÃO DE DADOS E HELPER DE CONVERSÃO
                        // ==========================================
                        $initialTotal = $record->items()->sum('initial_value');
                        $overallTotal = $record->overall_total;
                        $expenses = $record->expenses()->orderBy('expense_number')->get();
                        $items = $record->items()->with('requestItem.product')->get();
                        $totalVal = (float) $record->total_value;

                        // Função helper para converter e arredondar para Dólar de forma segura
                        $usdQuote = (float) $record->usd_quote;
                        $toUsd = fn($value) => $usdQuote > 0 ? round((float) $value / $usdQuote, 2) : 0;

                        // ==========================================
                        // ABA 1: RESUMO E DESPESAS (BRL)
                        // ==========================================
                        $sheet1 = $writer->getCurrentSheet();
                        $sheet1->setName('Resumo e Despesas (R$)');

                        $writer->addRow(Row::fromValues(['Fechamento']));
                        $writer->addRow(Row::fromValues([
                            'Solicitação:',
                            $record->request->display_id ?? '-',
                            'Modalidade Envio:',
                            $record->request->shipping_type ?? 'Não Informado',
                            'Cotação USD:',
                            round($usdQuote, 2),
                            'Fator de Cálculo:',
                            round((float) $record->calculation_factor, 2) . '%',
                        ]));

                        $writer->addRow(Row::fromValues([
                            'Total Inicial:',
                            round((float) $initialTotal, 2),
                            'Total Parcial:',
                            round((float) $record->total_value, 2),
                            'Total Despesas:',
                            round((float) $record->total_expenses, 2),
                            '% Despesa:',
                            round((float) $record->expense_percentage, 2) . '%',
                            'Total Geral:',
                            round((float) $overallTotal, 2)
                        ]));

                        $writer->addRow(Row::fromValues([]));
                        $writer->addRow(Row::fromValues(['Despesas']));
                        $writer->addRow(Row::fromValues(['Descrição da Despesa', 'Valor (R$)']));

                        if ($expenses->isEmpty()) {
                            $writer->addRow(Row::fromValues(['Nenhuma despesa lançada.', 0]));
                        } else {
                            foreach ($expenses as $exp) {
                                $writer->addRow(Row::fromValues([$exp->description, round((float) $exp->amount, 2)]));
                            }
                        }

                        // ==========================================
                        // ABA 2: DETALHAMENTO DE PRODUTOS (BRL)
                        // ==========================================
                        $sheet2 = $writer->addNewSheetAndMakeItCurrent();
                        $sheet2->setName('Detalhamento (R$)');

                        $headersBrl = [
                            'Cód. Winthor',
                            'Nome PT',
                            'Nome EN',
                            'Cód. Barras',
                            'Qtd Caixa',
                            'QTD',
                            'V. UN (R$)',
                            'Valor Inicial (R$)',
                            'Valor Parcial (R$)',
                            'Rateio Despesas (R$)',
                            '% Participação rateio',
                            'Valor Final (R$)'
                        ];
                        $writer->addRow(Row::fromValues($headersBrl));

                        foreach ($items as $item) {
                            $reqItem = $item->requestItem;
                            $product = $reqItem?->product;
                            $percentage = $totalVal > 0 ? ((float) $item->partial_value / $totalVal) : 0;
                            $totalApportionment = $item->final_value - $item->partial_value;

                            $writer->addRow(Row::fromValues([
                                $reqItem?->winthor_code ?? $product?->codprod ?? '-',
                                $reqItem?->product_name ?? '-',
                                $product?->product_name_en ?? '-',
                                $product?->barcode ?? $product?->ean ?? '-',
                                $product?->qtunitcx ?? '-',
                                round((float) ($reqItem?->quantity ?? 0), 2),
                                round((float) ($reqItem?->unit_price ?? 0), 2),
                                round((float) $item->initial_value, 2),
                                round((float) $item->partial_value, 2),
                                round((float) $totalApportionment, 2),
                                round((float) ($percentage * 100), 2),
                                round((float) $item->final_value, 2),
                            ]));
                        }

                        // ==========================================
                        // ABA 3: RESUMO E DESPESAS (USD)
                        // ==========================================
                        $sheet3 = $writer->addNewSheetAndMakeItCurrent();
                        $sheet3->setName('Resumo e Despesas (US$)');

                        $writer->addRow(Row::fromValues(['Fechamento (Valores em Dólar)']));
                        $writer->addRow(Row::fromValues([
                            'Solicitação:',
                            $record->request->display_id ?? '-',
                            'Modalidade Envio:',
                            $record->request->shipping_type ?? 'Não Informado',
                            'Cotação USD:',
                            round($usdQuote, 2), // Cotação não é convertida
                            'Fator de Cálculo:',
                            round((float) $record->calculation_factor, 2) . '%',
                        ]));

                        $writer->addRow(Row::fromValues([
                            'Total Inicial:',
                            $toUsd($initialTotal),
                            'Total Parcial:',
                            $toUsd($record->total_value),
                            'Total Despesas:',
                            $toUsd($record->total_expenses),
                            '% Despesa:',
                            round((float) $record->expense_percentage, 2) . '%', // Porcentagem é mantida
                            'Total Geral:',
                            $toUsd($overallTotal)
                        ]));

                        $writer->addRow(Row::fromValues([]));
                        $writer->addRow(Row::fromValues(['Despesas']));
                        $writer->addRow(Row::fromValues(['Descrição da Despesa', 'Valor (US$)']));

                        if ($expenses->isEmpty()) {
                            $writer->addRow(Row::fromValues(['Nenhuma despesa lançada.', 0]));
                        } else {
                            foreach ($expenses as $exp) {
                                $writer->addRow(Row::fromValues([$exp->description, $toUsd($exp->amount)]));
                            }
                        }

                        // ==========================================
                        // ABA 4: DETALHAMENTO DE PRODUTOS (USD)
                        // ==========================================
                        $sheet4 = $writer->addNewSheetAndMakeItCurrent();
                        $sheet4->setName('Detalhamento (US$)');

                        $headersUsd = [
                            'Cód. Winthor',
                            'Nome PT',
                            'Nome EN',
                            'Cód. Barras',
                            'Qtd Caixa',
                            'QTD',
                            'V. UN (US$)',
                            'Valor Inicial (US$)',
                            'Valor Parcial (US$)',
                            'Rateio Despesas (US$)',
                            '% Participação rateio',
                            'Valor Final (US$)'
                        ];
                        $writer->addRow(Row::fromValues($headersUsd));

                        foreach ($items as $item) {
                            $reqItem = $item->requestItem;
                            $product = $reqItem?->product;
                            $percentage = $totalVal > 0 ? ((float) $item->partial_value / $totalVal) : 0;
                            $totalApportionment = $item->final_value - $item->partial_value;

                            $writer->addRow(Row::fromValues([
                                $reqItem?->winthor_code ?? $product?->codprod ?? '-',
                                $reqItem?->product_name ?? '-',
                                $product?->product_name_en ?? '-',
                                $product?->barcode ?? $product?->ean ?? '-',
                                $product?->qtunitcx ?? '-',
                                round((float) ($reqItem?->quantity ?? 0), 2),
                                $toUsd($reqItem?->unit_price ?? 0),       // V. UN
                                $toUsd($item->initial_value),             // V. Inicial
                                $toUsd($item->partial_value),             // V. Parcial
                                $toUsd($totalApportionment),              // Rateio
                                round((float) ($percentage * 100), 2),    // A porcentagem de rateio não sofre conversão cambial
                                $toUsd($item->final_value),               // V. Final
                            ]));
                        }

                        $writer->close();
                    }, $fileName, [
                        'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ]);
                }),
        ];
    }
}
