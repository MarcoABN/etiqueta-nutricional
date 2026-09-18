<?php

namespace App\Exports;

use App\Models\Settlement;
use App\Models\SettlementExpense;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Consolidação de vários fechamentos em um único XLSX:
 *  - Aba "Consolidado": uma linha por fechamento + linha de totais
 *  - Aba "Despesas": todas as despesas lançadas
 *  - Aba "Itens Rateados": todos os itens com o rateio aplicado
 */
class SettlementsConsolidatedExport
{
    private const MONEY = '#,##0.00';
    private const QUOTE = '#,##0.0000';
    private const PERCENT = '0.000';
    private const DATE = 'dd/mm/yyyy hh:mm';

    private Style $headerStyle;
    private Style $totalStyle;

    /** @var array<string, Style> */
    private array $formatStyles = [];

    public static function download(Builder $query): StreamedResponse
    {
        $settlements = $query
            ->with(['request', 'expenses', 'items.requestItem.product'])
            ->get();

        $fileName = 'Fechamentos_Consolidado_' . now()->format('Y-m-d_His') . '.xlsx';

        return response()->streamDownload(
            fn() => (new self())->write($settlements),
            $fileName,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    public function __construct()
    {
        $this->headerStyle = (new Style())
            ->setFontBold()
            ->setBackgroundColor('D9E1F2')
            ->setShouldWrapText();

        $this->totalStyle = (new Style())
            ->setFontBold()
            ->setBackgroundColor('F2F2F2');
    }

    /**
     * @param Collection<int, Settlement> $settlements
     */
    public function write(Collection $settlements): void
    {
        $options = new Options();
        $options->setColumnWidthForRange(16, 1, 16);
        $options->setColumnWidth(40, 2);

        $writer = new Writer($options);
        $writer->openToFile('php://output');

        $writer->getCurrentSheet()->setName('Consolidado');
        $this->writeSummarySheet($writer, $settlements);

        $writer->addNewSheetAndMakeItCurrent()->setName('Despesas');
        $this->writeExpensesSheet($writer, $settlements);

        $writer->addNewSheetAndMakeItCurrent()->setName('Itens Rateados');
        $this->writeItemsSheet($writer, $settlements);

        $writer->close();
    }

    private function writeSummarySheet(Writer $writer, Collection $settlements): void
    {
        $writer->addRow(Row::fromValues([
            'Nº Solicitação',
            'Solicitação',
            'Modalidade Envio',
            'Data Fechamento',
            'Situação',
            'Cotação USD',
            'Fator (%)',
            'Total Inicial (R$)',
            'Total Real (R$)',
            'Desp. Internas (R$)',
            'Desp. Externas (R$)',
            'Total Despesas (R$)',
            '% Despesa',
            'Total Final (R$)',
            'Total Despesas (US$)',
            'Total Final (US$)',
        ], $this->headerStyle));

        $formats = [
            3 => self::DATE,
            5 => self::QUOTE,
            6 => self::MONEY,
            7 => self::MONEY,
            8 => self::MONEY,
            9 => self::MONEY,
            10 => self::MONEY,
            11 => self::MONEY,
            12 => self::PERCENT,
            13 => self::MONEY,
            14 => self::MONEY,
            15 => self::MONEY,
        ];

        $totals = array_fill_keys(['initial', 'real', 'internal', 'external', 'expenses', 'final', 'expensesUsd', 'finalUsd'], 0.0);

        foreach ($settlements as $settlement) {
            $usdQuote = (float) $settlement->usd_quote;
            $initial = (float) $settlement->items->sum('initial_value');
            $real = (float) $settlement->total_value;
            $internal = (float) $settlement->expenses->where('is_external', false)->sum('amount');
            $external = (float) $settlement->expenses->where('is_external', true)->sum('amount');
            $expenses = (float) $settlement->total_expenses;
            $final = $real + $expenses;
            $expensesUsd = $this->expensesInUsd($settlement);
            $finalUsd = ($usdQuote > 0 ? $real / $usdQuote : 0) + $expensesUsd;

            $totals['initial'] += $initial;
            $totals['real'] += $real;
            $totals['internal'] += $internal;
            $totals['external'] += $external;
            $totals['expenses'] += $expenses;
            $totals['final'] += $final;
            $totals['expensesUsd'] += $expensesUsd;
            $totals['finalUsd'] += $finalUsd;

            $writer->addRow($this->row([
                $settlement->request?->display_id ?? '-',
                $settlement->request?->observation ?? '-',
                $settlement->request?->shipping_type ?? 'Não Informado',
                $settlement->created_at,
                $this->status($settlement),
                round($usdQuote, 4),
                round((float) $settlement->calculation_factor, 2),
                round($initial, 2),
                round($real, 2),
                round($internal, 2),
                round($external, 2),
                round($expenses, 2),
                round((float) $settlement->expense_percentage, 3),
                round($final, 2),
                round($expensesUsd, 2),
                round($finalUsd, 2),
            ], $formats));
        }

        $writer->addRow($this->row([
            'TOTAL (' . $settlements->count() . ' fechamento(s))',
            '',
            '',
            '',
            '',
            '',
            '',
            round($totals['initial'], 2),
            round($totals['real'], 2),
            round($totals['internal'], 2),
            round($totals['external'], 2),
            round($totals['expenses'], 2),
            // % ponderado: despesas totais sobre o total real somado
            round($totals['real'] > 0 ? ($totals['expenses'] / $totals['real']) * 100 : 0, 3),
            round($totals['final'], 2),
            round($totals['expensesUsd'], 2),
            round($totals['finalUsd'], 2),
        ], $formats, $this->totalStyle));
    }

    private function writeExpensesSheet(Writer $writer, Collection $settlements): void
    {
        $writer->addRow(Row::fromValues([
            'Nº Solicitação',
            'Solicitação',
            'Ordem',
            'Descrição da Despesa',
            'Tipo',
            'Valor (R$)',
            'Cotação',
            'Cotação Utilizada',
            'Valor (US$)',
        ], $this->headerStyle));

        $formats = [
            5 => self::MONEY,
            7 => self::QUOTE,
            8 => self::MONEY,
        ];

        $totalBrl = 0.0;
        $totalUsd = 0.0;

        foreach ($settlements as $settlement) {
            foreach ($settlement->expenses as $expense) {
                $quote = $this->expenseQuote($expense, (float) $settlement->usd_quote);
                $amount = (float) $expense->amount;
                $amountUsd = $quote > 0 ? $amount / $quote : 0;

                $totalBrl += $amount;
                $totalUsd += $amountUsd;

                $writer->addRow($this->row([
                    $settlement->request?->display_id ?? '-',
                    $settlement->request?->observation ?? '-',
                    (int) $expense->expense_number,
                    $expense->description,
                    $expense->is_external ? 'Externa' : 'Interna',
                    round($amount, 2),
                    $this->hasCustomQuote($expense) ? 'Específica' : 'Global',
                    round($quote, 4),
                    round($amountUsd, 2),
                ], $formats));
            }
        }

        $writer->addRow($this->row([
            'TOTAL',
            '',
            '',
            '',
            '',
            round($totalBrl, 2),
            '',
            '',
            round($totalUsd, 2),
        ], $formats, $this->totalStyle));
    }

    private function writeItemsSheet(Writer $writer, Collection $settlements): void
    {
        $writer->addRow(Row::fromValues([
            'Nº Solicitação',
            'Solicitação',
            'Cód. Winthor',
            'Produto',
            'QTD',
            'V. UN (R$)',
            'Valor Inicial (R$)',
            'Valor Real (R$)',
            'Rateio Despesas (R$)',
            '% Participação rateio',
            'Valor Final (R$)',
            'Valor Final (US$)',
        ], $this->headerStyle));

        $formats = [
            4 => self::MONEY,
            5 => self::MONEY,
            6 => self::MONEY,
            7 => self::MONEY,
            8 => self::MONEY,
            9 => self::PERCENT,
            10 => self::MONEY,
            11 => self::MONEY,
        ];

        $totals = array_fill_keys(['initial', 'real', 'apportionment', 'final', 'finalUsd'], 0.0);

        foreach ($settlements as $settlement) {
            $usdQuote = (float) $settlement->usd_quote;
            $totalValue = (float) $settlement->total_value;
            $expensesUsd = $this->expensesInUsd($settlement);

            $items = $settlement->items->sortBy(
                fn($item) => strtolower($item->requestItem?->product_name ?? '')
            );

            foreach ($items as $item) {
                $reqItem = $item->requestItem;
                $partial = (float) $item->partial_value;
                $final = (float) $item->final_value;
                $percentage = $totalValue > 0 ? $partial / $totalValue : 0;
                $finalUsd = ($usdQuote > 0 ? $partial / $usdQuote : 0) + ($percentage * $expensesUsd);

                $totals['initial'] += (float) $item->initial_value;
                $totals['real'] += $partial;
                $totals['apportionment'] += $final - $partial;
                $totals['final'] += $final;
                $totals['finalUsd'] += $finalUsd;

                $writer->addRow($this->row([
                    $settlement->request?->display_id ?? '-',
                    $settlement->request?->observation ?? '-',
                    $reqItem?->winthor_code ?? $reqItem?->product?->codprod ?? '-',
                    $reqItem?->product_name ?? '-',
                    round((float) ($reqItem?->quantity ?? 0), 2),
                    round((float) ($reqItem?->unit_price ?? 0), 2),
                    round((float) $item->initial_value, 2),
                    round($partial, 2),
                    round($final - $partial, 2),
                    round($percentage * 100, 3),
                    round($final, 2),
                    round($finalUsd, 2),
                ], $formats));
            }
        }

        $writer->addRow($this->row([
            'TOTAL',
            '',
            '',
            '',
            '',
            '',
            round($totals['initial'], 2),
            round($totals['real'], 2),
            round($totals['apportionment'], 2),
            '',
            round($totals['final'], 2),
            round($totals['finalUsd'], 2),
        ], $formats, $this->totalStyle));
    }

    /**
     * Mesma regra da exportação individual: despesa com cotação específica usa a própria,
     * caso contrário usa a cotação global do fechamento.
     */
    private function expensesInUsd(Settlement $settlement): float
    {
        $globalQuote = (float) $settlement->usd_quote;

        return (float) $settlement->expenses->sum(function (SettlementExpense $expense) use ($globalQuote) {
            $quote = $this->expenseQuote($expense, $globalQuote);

            return $quote > 0 ? (float) $expense->amount / $quote : 0;
        });
    }

    private function expenseQuote(SettlementExpense $expense, float $globalQuote): float
    {
        return $this->hasCustomQuote($expense) ? (float) $expense->custom_usd_quote : $globalQuote;
    }

    private function hasCustomQuote(SettlementExpense $expense): bool
    {
        return $expense->use_custom_quote && (float) $expense->custom_usd_quote > 0;
    }

    private function status(Settlement $settlement): string
    {
        if ($settlement->trashed()) {
            return 'Excluído';
        }

        return $settlement->is_locked ? 'Consolidado' : 'Aberto';
    }

    /**
     * Monta a linha aplicando o formato numérico/data por coluna (índice base 0).
     */
    private function row(array $values, array $formats, ?Style $rowStyle = null): Row
    {
        $cells = [];

        foreach (array_values($values) as $index => $value) {
            $format = $formats[$index] ?? null;
            $isFormattable = is_int($value) || is_float($value) || $value instanceof \DateTimeInterface;

            $style = ($format && $isFormattable)
                ? ($this->formatStyles[$format] ??= (new Style())->setFormat($format))
                : null;

            $cells[] = Cell::fromValue($value, $style);
        }

        return new Row($cells, $rowStyle);
    }
}
