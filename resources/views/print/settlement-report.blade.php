<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Impressão de Fechamento - {{ $settlement->request->display_id ?? 'Avulso' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Configuração para folha A4 Vertical (Portrait) */
        @page {
            size: A4 portrait;
            margin: 10mm;
        }
        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .no-print { display: none !important; }
        }
        
        /* Estilos base da Tabela ajustados para o espaço mais estreito */
        body { font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; background-color: white; color: #1f2937; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 1rem; table-layout: fixed; }
        th, td { border: 1px solid #e5e7eb; padding: 4px 6px; text-align: left; vertical-align: top; word-wrap: break-word; }
        th { background-color: #f9fafb; font-weight: 600; font-size: 9px; text-transform: uppercase; color: #4b5563; }
        
        /* Classes para formatar R$ e US$ na mesma célula (Fontes levemente menores para Portrait) */
        .val-brl { font-weight: 600; color: #111827; font-size: 10px; display: block; }
        .val-usd { color: #6b7280; font-size: 8px; display: block; margin-top: 1px; }
        .bg-highlight { background-color: #f0fdf4 !important; }
    </style>
</head>
<body onload="window.print()" class="text-[10px]">

    <div class="no-print p-4 bg-gray-100 flex justify-end gap-4 mb-4">
        <button onclick="window.close()" class="px-4 py-2 bg-gray-300 rounded font-medium hover:bg-gray-400 text-sm">Fechar Janela</button>
        <button onclick="window.print()" class="px-4 py-2 bg-blue-600 rounded text-white font-medium hover:bg-blue-700 text-sm">Imprimir Novamente</button>
    </div>

    <div class="max-w-[210mm] mx-auto">
        
        <h1 class="text-xl font-bold text-center mb-4 text-gray-800 uppercase tracking-wide border-b pb-2">
            Relatório de Fechamento: {{ $settlement->request->display_id ?? '-' }}
        </h1>

        <h2 class="text-xs font-bold bg-gray-800 text-white p-1.5 mb-1 rounded-t-sm uppercase">Resumo Financeiro</h2>
        <table class="mb-4">
            <tr>
                <th class="text-center w-[12%]">Cot. USD</th>
                <th class="text-center w-[10%]">Fator</th>
                <th class="text-right w-[18%]">Total Inicial</th>
                <th class="text-right w-[20%]">Total Real (Produtos)</th>
                <th class="text-right w-[20%]">Total Despesas</th>
                <th class="text-right w-[20%] bg-highlight border-green-200">Total Final</th>
            </tr>
            <tr>
                <td class="text-center font-medium" style="vertical-align: middle;">R$ {{ number_format($usdQuote, 4, ',', '.') }}</td>
                <td class="text-center font-medium" style="vertical-align: middle;">{{ number_format((float) $settlement->calculation_factor, 2, ',', '.') }}%</td>
                
                <td class="text-right">
                    <span class="val-brl">R$ {{ number_format((float) $initialTotal, 2, ',', '.') }}</span>
                    <span class="val-usd">≈ US$ {{ number_format($toUsd($initialTotal), 2, ',', '.') }}</span>
                </td>
                
                <td class="text-right">
                    <span class="val-brl text-blue-700">R$ {{ number_format((float) $settlement->total_value, 2, ',', '.') }}</span>
                    <span class="val-usd">≈ US$ {{ number_format($toUsd($settlement->total_value), 2, ',', '.') }}</span>
                </td>
                
                <td class="text-right">
                    <span class="val-brl text-red-700">R$ {{ number_format((float) $settlement->total_expenses, 2, ',', '.') }}</span>
                    <span class="val-usd text-[8px]">Rateio: {{ number_format((float) $settlement->expense_percentage, 2, ',', '.') }}%</span>
                </td>
                
                <td class="text-right bg-highlight border-green-200">
                    <span class="val-brl text-green-800 text-[11px]">R$ {{ number_format((float) $overallTotal, 2, ',', '.') }}</span>
                    <span class="val-usd text-green-700 font-medium text-[9px]">≈ US$ {{ number_format($totalGeralUsd, 2, ',', '.') }}</span>
                </td>
            </tr>
        </table>

        @if($expenses->isNotEmpty())
            <h2 class="text-xs font-bold bg-gray-800 text-white p-1.5 mb-1 rounded-t-sm uppercase">Detalhamento de Despesas</h2>
            <table class="mb-4">
                <tr>
                    <th class="w-[60%]">Descrição da Despesa</th>
                    <th class="text-center w-[20%]">Cotação Utilizada</th>
                    <th class="text-right w-[20%]">Valor da Despesa</th>
                </tr>
                
                @foreach($expenses as $exp)
                    @php
                        $useCustom = (bool) $exp->use_custom_quote;
                        $customQuote = (float) $exp->custom_usd_quote;
                        $quoteToUse = ($useCustom && $customQuote > 0) ? $customQuote : $usdQuote;
                        $usdAmount = $quoteToUse > 0 ? (float) $exp->amount / $quoteToUse : 0;
                    @endphp
                    <tr>
                        <td class="font-medium text-gray-800" style="vertical-align: middle;">{{ $exp->description }}</td>
                        <td class="text-center text-gray-600 text-[9px]" style="vertical-align: middle;">
                            {{ $useCustom ? 'Específica (R$ ' . number_format($customQuote, 4, ',', '.') . ')' : 'Global' }}
                        </td>
                        <td class="text-right bg-red-50">
                            <span class="val-brl text-red-800">R$ {{ number_format((float) $exp->amount, 2, ',', '.') }}</span>
                            <span class="val-usd text-red-600 font-medium">≈ US$ {{ number_format($usdAmount, 2, ',', '.') }}</span>
                        </td>
                    </tr>
                @endforeach
            </table>
        @endif

        <h2 class="text-xs font-bold bg-gray-800 text-white p-1.5 mb-1 rounded-t-sm uppercase">Detalhamento e Rateio de Itens</h2>
        <table>
            <tr>
                <th class="text-center w-[8%]">Cód.</th>
                <th class="w-[28%]">Produto</th>
                <th class="text-center w-[6%]">Qtd</th>
                <th class="text-center w-[8%]">% Rateio</th>
                <th class="text-right w-[12%]">V. Unitário</th>
                <th class="text-right w-[13%]">V. Real</th>
                <th class="text-right w-[12%]">Rateio Desp.</th>
                <th class="text-right w-[13%] bg-gray-100">Valor Final</th>
            </tr>
            
            @foreach($items as $item)
                @php
                    $reqItem = $item->requestItem;
                    $percentage = $totalVal > 0 ? ((float) $item->partial_value / $totalVal) : 0;
                    $apportionmentBrl = $item->final_value - $item->partial_value;
                    
                    $partialUsd = $toUsd($item->partial_value);
                    $apportionmentUsd = $percentage * $totalExpensesUsd;
                    $finalUsd = $partialUsd + $apportionmentUsd;
                @endphp
                <tr>
                    <td class="text-center text-gray-500 text-[9px]">{{ $reqItem?->winthor_code ?? '-' }}</td>
                    <td class="font-medium text-gray-800 text-[9px] leading-tight">{{ $reqItem?->product_name ?? '-' }}</td>
                    <td class="text-center" style="vertical-align: middle;">{{ round((float) ($reqItem?->quantity ?? 0), 2) }}</td>
                    <td class="text-center text-gray-600 text-[9px]" style="vertical-align: middle;">{{ number_format($percentage * 100, 2, ',', '.') }}%</td>
                    
                    <td class="text-right">
                        <span class="val-brl">{{ number_format((float) ($reqItem?->unit_price ?? 0), 2, ',', '.') }}</span>
                        <span class="val-usd">≈ {{ number_format($toUsd($reqItem?->unit_price ?? 0), 2, ',', '.') }}</span>
                    </td>
                    
                    <td class="text-right">
                        <span class="val-brl">{{ number_format((float) $item->partial_value, 2, ',', '.') }}</span>
                        <span class="val-usd">≈ {{ number_format($partialUsd, 2, ',', '.') }}</span>
                    </td>
                    
                    <td class="text-right">
                        <span class="val-brl text-red-700">+ {{ number_format((float) $apportionmentBrl, 2, ',', '.') }}</span>
                        <span class="val-usd">≈ {{ number_format($apportionmentUsd, 2, ',', '.') }}</span>
                    </td>
                    
                    <td class="text-right bg-gray-50">
                        <span class="val-brl text-green-700">R$ {{ number_format((float) $item->final_value, 2, ',', '.') }}</span>
                        <span class="val-usd font-medium">≈ US$ {{ number_format($finalUsd, 2, ',', '.') }}</span>
                    </td>
                </tr>
            @endforeach
        </table>
    </div>
</body>
</html>