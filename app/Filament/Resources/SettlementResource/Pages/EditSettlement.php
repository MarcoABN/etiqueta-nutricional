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

    // Oculta o botão "Guardar" padrão se o fechamento estiver travado (is_locked)
    protected function getSaveFormAction(): \Filament\Actions\Action
    {
        return parent::getSaveFormAction()
            ->hidden(fn() => $this->record->is_locked);
    }

    protected function getHeaderActions(): array
    {
        return [
            // --- AÇÃO: CONSOLIDAR FECHAMENTO ---
            Actions\Action::make('consolidate_settlement')
                ->label('Consolidar Fechamento')
                ->icon('heroicon-o-lock-closed')
                ->color('success') // Verde: indica que o processo foi concluído com êxito
                ->requiresConfirmation()
                ->modalHeading('Consolidar Fechamento?')
                ->modalDescription('Após consolidar, os dados da solicitação, produtos e despesas se tornarão imutáveis (somente leitura). Deseja continuar?')
                ->hidden(fn() => $this->record->is_locked)
                ->action(function () {
                    $this->record->update(['is_locked' => true]);
                    \Filament\Notifications\Notification::make()
                        ->title('Fechamento consolidado com sucesso!')
                        ->success()
                        ->send();
                }),

            // --- AÇÃO: REABRIR FECHAMENTO ---
            Actions\Action::make('reopen_settlement')
                ->label('Reabrir Fechamento')
                ->icon('heroicon-o-lock-open')
                ->color('warning') // Laranja/Amarelo: indica uma ação de exceção/atenção
                ->requiresConfirmation()
                ->modalHeading('Reabrir Fechamento?')
                ->modalDescription('Atenção: Reabrir permitirá que os valores e despesas sejam editados novamente. Deseja continuar?')
                ->visible(fn() => $this->record->is_locked)
                ->action(function () {
                    $this->record->update(['is_locked' => false]);
                    \Filament\Notifications\Notification::make()
                        ->title('Fechamento reaberto para edição!')
                        ->warning()
                        ->send();
                }),

            // --- AÇÃO EXISTENTE: IMPRIMIR RELATÓRIO ---
            Actions\Action::make('print_report')
                ->label('Imprimir Relatório')
                ->icon('heroicon-o-printer')
                ->color('info')
                ->url(fn(Settlement $record): string => route('settlement.print', $record))
                ->openUrlInNewTab(),

            // --- AÇÃO EXISTENTE: EXPORTAR EXCEL ---
            Actions\Action::make('export_details')
                ->label('Exportar')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->action(function (Settlement $record) {
                    $safeName = \Illuminate\Support\Str::slug($record->request->observation ?? 'Avulso');
                    $fileName = 'Fechamento_' . $safeName . '.xlsx';

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

                        // Itens ordenados alfabeticamente pelo nome do produto em Português
                        $items = $record->items()
                            ->with('requestItem.product')
                            ->get()
                            ->sortBy(function ($item) {
                                return strtolower($item->requestItem?->product_name ?? '');
                            });

                        $totalVal = (float) $record->total_value;

                        // Função helper para converter e arredondar para Dólar global de forma segura
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
                            $record->request->observation ?? '-',
                            'Modalidade Envio:',
                            $record->request->shipping_type ?? 'Não Informado',
                            'Cotação USD Global:',
                            round($usdQuote, 4),
                            'Fator de Cálculo:',
                            round((float) $record->calculation_factor, 2) . '%',
                        ]));

                        $writer->addRow(Row::fromValues([
                            'Total Inicial:',
                            round((float) $initialTotal, 2),
                            'Total Real:',
                            round((float) $record->total_value, 2),
                            'Total Despesas:',
                            round((float) $record->total_expenses, 2),
                            '% Despesa:',
                            round((float) $record->expense_percentage, 2) . '%',
                            'Total Final:',
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
                            'Valor Real (R$)',
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

                            // Tratamento estrito de tipo para a Qtd Caixa
                            $qtdCaixaRaw = $product?->qtunitcx;

                            if (is_numeric($qtdCaixaRaw)) {
                                $floatVal = (float) $qtdCaixaRaw;
                                // Se o valor com decimais for matematicamente igual à sua versão inteira (25.00 == 25)
                                // forçamos o tipo para (int). Caso contrário, mantemos como (float).
                                $qtdCaixaExibicao = ($floatVal == (int) $floatVal) ? (int) $floatVal : $floatVal;
                            } else {
                                // Se for um texto ou fração exata da embalagem (ex: "2 1/2"), passa direto
                                $qtdCaixaExibicao = $qtdCaixaRaw ?? '-';
                            }

                            $writer->addRow(Row::fromValues([
                                $reqItem?->winthor_code ?? $product?->codprod ?? '-',
                                $reqItem?->product_name ?? '-',
                                $product?->product_name_en ?? '-',
                                $product?->barcode ?? $product?->ean ?? '-',
                                $qtdCaixaExibicao,
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

                        // Recalcular o Total das Despesas em USD respeitando cotações personalizadas
                        $totalExpensesUsd = 0;
                        foreach ($expenses as $exp) {
                            $quoteToUse = ($exp->use_custom_quote && $exp->custom_usd_quote > 0) ? (float) $exp->custom_usd_quote : $usdQuote;
                            if ($quoteToUse > 0) {
                                $totalExpensesUsd += ((float) $exp->amount / $quoteToUse);
                            }
                        }

                        $totalGeralUsd = $toUsd($record->total_value) + $totalExpensesUsd;

                        $writer->addRow(Row::fromValues(['Fechamento (Valores em Dólar)']));
                        $writer->addRow(Row::fromValues([
                            'Solicitação:',
                            $record->request->display_id ?? '-',
                            'Modalidade Envio:',
                            $record->request->shipping_type ?? 'Não Informado',
                            'Cotação USD Global:',
                            round($usdQuote, 4),
                            'Fator de Cálculo:',
                            round((float) $record->calculation_factor, 2) . '%',
                        ]));

                        $writer->addRow(Row::fromValues([
                            'Total Inicial:',
                            $toUsd($initialTotal),
                            'Total Real:',
                            $toUsd($record->total_value),
                            'Total Despesas:',
                            round($totalExpensesUsd, 2),
                            '% Despesa:',
                            round((float) $record->expense_percentage, 2) . '%',
                            'Total Final:',
                            round($totalGeralUsd, 2)
                        ]));

                        $writer->addRow(Row::fromValues([]));
                        $writer->addRow(Row::fromValues(['Despesas']));
                        $writer->addRow(Row::fromValues(['Descrição da Despesa', 'Cotação Utilizada', 'Valor (US$)']));

                        if ($expenses->isEmpty()) {
                            $writer->addRow(Row::fromValues(['Nenhuma despesa lançada.', '-', 0]));
                        } else {
                            foreach ($expenses as $exp) {
                                $useCustom = (bool) $exp->use_custom_quote;
                                $customQuote = (float) $exp->custom_usd_quote;
                                $quoteToUse = ($useCustom && $customQuote > 0) ? $customQuote : $usdQuote;

                                $usdAmount = $quoteToUse > 0 ? round((float) $exp->amount / $quoteToUse, 2) : 0;

                                $cotacaoStr = $useCustom ? 'Específica (' . round($customQuote, 4) . ')' : 'Global (' . round($usdQuote, 4) . ')';

                                $writer->addRow(Row::fromValues([
                                    $exp->description,
                                    $cotacaoStr,
                                    $usdAmount
                                ]));
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
                            'Valor Real (US$)',
                            'Rateio Despesas (US$)',
                            '% Participação rateio',
                            'Valor Final (US$)'
                        ];
                        $writer->addRow(Row::fromValues($headersUsd));

                        foreach ($items as $item) {
                            $reqItem = $item->requestItem;
                            $product = $reqItem?->product;

                            // Calcula o percentual de participação do item
                            $percentage = $totalVal > 0 ? ((float) $item->partial_value / $totalVal) : 0;

                            // Calcula os valores em USD baseados no percentual, e não na conversão direta do BRL final
                            $partialUsd = $toUsd($item->partial_value);
                            $apportionmentUsd = round($percentage * $totalExpensesUsd, 2);
                            $finalUsd = $partialUsd + $apportionmentUsd;

                            // Tratamento estrito de tipo para a Qtd Caixa
                            $qtdCaixaRaw = $product?->qtunitcx;

                            if (is_numeric($qtdCaixaRaw)) {
                                $floatVal = (float) $qtdCaixaRaw;
                                // Se o valor com decimais for matematicamente igual à sua versão inteira (25.00 == 25)
                                // forçamos o tipo para (int). Caso contrário, mantemos como (float).
                                $qtdCaixaExibicao = ($floatVal == (int) $floatVal) ? (int) $floatVal : $floatVal;
                            } else {
                                // Se for um texto ou fração exata da embalagem (ex: "2 1/2"), passa direto
                                $qtdCaixaExibicao = $qtdCaixaRaw ?? '-';
                            }

                            $writer->addRow(Row::fromValues([
                                $reqItem?->winthor_code ?? $product?->codprod ?? '-',
                                $reqItem?->product_name ?? '-',
                                $product?->product_name_en ?? '-',
                                $product?->barcode ?? $product?->ean ?? '-',
                                $qtdCaixaExibicao,
                                round((float) ($reqItem?->quantity ?? 0), 2),
                                $toUsd($reqItem?->unit_price ?? 0),
                                $toUsd($item->initial_value),
                                $partialUsd,                              // V. Parcial calculado
                                $apportionmentUsd,                        // Rateio proporcional em USD real
                                round((float) ($percentage * 100), 2),
                                $finalUsd,                                // V. Final preciso
                            ]));
                        }

                        $writer->close();
                    }, $fileName, [
                        'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ]);
                }),

            // --- AÇÃO EXISTENTE: APAGAR (Ocultada se estiver travado) ---
            Actions\DeleteAction::make()
                ->hidden(fn() => $this->record->is_locked),
        ];
    }
}
