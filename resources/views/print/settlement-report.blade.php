<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Impressão de Fechamento - {{ $settlement->request->display_id ?? 'Avulso' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Configuração obrigatória para folha A4 Deitada */
        @page {
            size: A4 landscape;
            margin: 10mm;
        }
        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .no-print { display: none !important; }
        }
        
        /* Estilos base da Tabela */
        body { font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; font-size: 11px; background-color: white; color: #1f2937; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 1rem; }
        th, td { border: 1px solid #e5e7eb; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background-color: #f9fafb; font-weight: 600; font-size: 10px; text-transform: uppercase; color: #4b5563; }
        
        /* Classes para formatar R$ e US$ na mesma célula */
        .val-brl { font-weight: 600; color: #111827; font-size: 11px; display: block; }
        .val-usd { color: #6b7280; font-size: 9px; display: block; margin-top: 2px; }
        .bg-highlight { background-color: #f0fdf4 !important; }
    </style>
</head>
<body onload="window.print()">

    <div class="no-print p-4 bg-gray-100 flex justify-end gap-4 mb-4">
        <button onclick="window.close()" class="px-4 py-2 bg-gray-300 rounded font-medium hover:bg-gray-400">Fechar Janela</button>
        <button onclick="window.print()" class="px-4 py-2 bg-blue-600 rounded text-white font-medium hover:bg-blue-700">Imprimir Novamente</button>
    </div>

    <div class="max-w-[297mm] mx-auto">
        <h1 class="text-2xl font-bold text-center mb-6 text-gray-800">
            Relatório de Fechamento: {{ $settlement->request->display_id ?? '-' }}
        </h1>

        <h2 class="text-sm font-bold bg-gray-800 text-white p-2 mb-2 rounded-t-sm">Resumo Financeiro</h2>
        <table class="mb-6">
            <tr>
                <th class="text-center">Cotação USD Global</th>
                <th class="text-center">Fator de Cálculo</th>
                <th class="text-right">Total Inicial</th>
                <th class="text-right">Total Parcial (Produtos)</th>
                <th class="text-right">Total Despesas</th>
                <th class="text-right bg-highlight border-green-200">Total Geral</th>
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
                    <span class="val-usd text-xs">Rateio: {{ number_format((float) $settlement->expense_percentage, 2, ',', '.') }}%</span>
                </td>
                
                <td class="text-right bg-highlight border-green-200">
                    <span class="val-brl text-green-800 text-sm">R$ {{ number_format((float) $overallTotal, 2, ',', '.') }}</span>
                    <span class="val-usd text-green-700 font-medium">≈ US$ {{ number_format($totalGeralUsd, 2, ',', '.') }}</span>
                </td>
            </tr>
        </table>

        <h2 class="text-sm font-bold bg-gray-800 text-white p-2 mb-2 rounded-t-sm">Detalhamento e Rateio de Itens</h2>
        <table>
            <tr>
                <th class="text-center w-12">Cód.</th>
                <th>Produto</th>
                <th class="text-center w-12">Qtd</th>
                <th class="text-center w-16">% Rateio</th>
                <th class="text-right w-24">Valor Unitário</th>
                <th class="text-right w-28">Valor Parcial</th>
                <th class="text-right w-28">Rateio Despesas</th>
                <th class="text-right w-32 bg-gray-100">Valor Final</th>
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
                    <td class="text-center text-gray-500">{{ $reqItem?->winthor_code ?? '-' }}</td>
                    <td class="font-medium text-gray-800">{{ $reqItem?->product_name ?? '-' }}</td>
                    <td class="text-center" style="vertical-align: middle;">{{ round((float) ($reqItem?->quantity ?? 0), 2) }}</td>
                    <td class="text-center text-gray-600" style="vertical-align: middle;">{{ number_format($percentage * 100, 2, ',', '.') }}%</td>
                    
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