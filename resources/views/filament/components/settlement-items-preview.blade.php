@php
    $request = \App\Models\Request::with('items.product')->find($requestId);
    $factorDec = (floatval($factor) ?: 70) / 100;
    $totalVal = floatval($totalValue);
    $totalExp = floatval($totalExpenses);
    
    // Lógica para exibição condicional (Dólar vs Real) com cálculos em tempo real
    $usd = floatval($usdQuote);
    $isUsd = ($showInUsd ?? false) && $usd > 0;
    
    $sym = $isUsd ? 'US$' : 'R$';
    $div = $isUsd ? $usd : 1;
@endphp

@if($request && $request->items->count() > 0)
    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm text-left">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-4 py-2">Produto</th>
                    <th class="px-4 py-2">Qtd</th>
                    <th class="px-4 py-2">V. UN ({{ $sym }})</th>
                    <th class="px-4 py-2" title="Qtd * Valor UN">Valor Inicial ({{ $sym }})</th>
                    <th class="px-4 py-2" title="Valor Inicial / Fator">Valor Parcial ({{ $sym }})</th>
                    
                    {{-- NOVA COLUNA: Rateio --}}
                    <th class="px-4 py-2 text-orange-600" title="Valor rateado + % de participação daquele item">Rateio Despesas ({{ $sym }})</th>
                    
                    <th class="px-4 py-2" title="Valor Parcial + Rateio das Despesas">Valor Final ({{ $sym }})</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($request->items as $item)
                    @php
                        // Cálculos da base real
                        $initial = ($item->quantity ?? 0) * ($item->unit_price ?? 0);
                        $partial = $factorDec > 0 ? $initial / $factorDec : 0;
                        
                        $percentage = $totalVal > 0 ? ($partial / $totalVal) * 100 : 0;
                        $apportionment = $totalVal > 0 ? ($partial / $totalVal) * $totalExp : 0;
                        
                        $final = $partial + $apportionment;
                        
                        // Conversão de exibição para a tela
                        $vUnitDisplay = ($item->unit_price ?? 0) / $div;
                        $initialDisplay = $initial / $div;
                        $partialDisplay = $partial / $div;
                        $apportionmentDisplay = $apportionment / $div;
                        $finalDisplay = $final / $div;
                    @endphp
                    <tr>
                        <td class="px-4 py-2">{{ $item->product_name }}</td>
                        <td class="px-4 py-2">{{ number_format($item->quantity, 2, ',', '.') }}</td>
                        <td class="px-4 py-2">{{ $sym }} {{ number_format($vUnitDisplay, 2, ',', '.') }}</td>
                        <td class="px-4 py-2 font-medium">{{ $sym }} {{ number_format($initialDisplay, 2, ',', '.') }}</td>
                        <td class="px-4 py-2 text-blue-600">{{ $sym }} {{ number_format($partialDisplay, 2, ',', '.') }}</td>
                        
                        {{-- NOVA CÉLULA: Rateio + % --}}
                        <td class="px-4 py-2 text-orange-600">
                            {{ $sym }} {{ number_format($apportionmentDisplay, 2, ',', '.') }} 
                            <span class="text-xs text-gray-400 dark:text-gray-500 ml-1">({{ number_format($percentage, 2, ',', '.') }}%)</span>
                        </td>
                        
                        <td class="px-4 py-2 text-green-600 font-bold">{{ $sym }} {{ number_format($finalDisplay, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <p class="text-gray-500 italic">Selecione uma solicitação válida para visualizar os cálculos rateados.</p>
@endif