@php
    $request = \App\Models\Request::with('items.product')->find($requestId);
    $factorDec = (floatval($factor) ?: 70) / 100;
    $totalVal = floatval($totalValue);
    $totalExp = floatval($totalExpenses);
    
    // Lógica para exibição do Dólar como informação secundária
    $usd = floatval($usdQuote);
    $isUsd = ($showInUsd ?? false) && $usd > 0;
@endphp

@if($request && $request->items->count() > 0)
    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm text-left">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-4 py-2">Produto</th>
                    <th class="px-4 py-2">Qtd</th>
                    <th class="px-4 py-2">V. UN</th>
                    <th class="px-4 py-2" title="Qtd * Valor UN">Valor Inicial</th>
                    <th class="px-4 py-2" title="Valor Inicial / Fator">Valor Parcial</th>
                    <th class="px-4 py-2 text-orange-600" title="Valor rateado + % de participação daquele item">Rateio Despesas</th>
                    <th class="px-4 py-2" title="Valor Parcial + Rateio das Despesas">Valor Final</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($request->items as $item)
                    @php
                        // Cálculos fixos da base real (R$)
                        $vUnit = $item->unit_price ?? 0;
                        $initial = ($item->quantity ?? 0) * $vUnit;
                        $partial = $factorDec > 0 ? $initial / $factorDec : 0;
                        
                        $percentage = $totalVal > 0 ? ($partial / $totalVal) * 100 : 0;
                        $apportionment = $totalVal > 0 ? ($partial / $totalVal) * $totalExp : 0;
                        
                        $final = $partial + $apportionment;
                    @endphp
                    <tr>
                        <td class="px-4 py-2 align-top">{{ $item->product_name }}</td>
                        <td class="px-4 py-2 align-top">{{ number_format($item->quantity, 2, ',', '.') }}</td>
                        
                        <td class="px-4 py-2 align-top whitespace-nowrap">
                            R$ {{ number_format($vUnit, 2, ',', '.') }}
                            @if($isUsd) <br><span class="text-xs text-gray-500 dark:text-gray-400">≈ US$ {{ number_format($vUnit / $usd, 2, ',', '.') }}</span> @endif
                        </td>
                        
                        <td class="px-4 py-2 font-medium align-top whitespace-nowrap">
                            R$ {{ number_format($initial, 2, ',', '.') }}
                            @if($isUsd) <br><span class="text-xs text-gray-500 dark:text-gray-400">≈ US$ {{ number_format($initial / $usd, 2, ',', '.') }}</span> @endif
                        </td>
                        
                        <td class="px-4 py-2 text-blue-600 align-top whitespace-nowrap">
                            R$ {{ number_format($partial, 2, ',', '.') }}
                            @if($isUsd) <br><span class="text-xs text-blue-400/80">≈ US$ {{ number_format($partial / $usd, 2, ',', '.') }}</span> @endif
                        </td>
                        
                        <td class="px-4 py-2 text-orange-600 align-top whitespace-nowrap">
                            R$ {{ number_format($apportionment, 2, ',', '.') }} 
                            <span class="text-xs text-gray-400 dark:text-gray-500 ml-1">({{ number_format($percentage, 2, ',', '.') }}%)</span>
                            @if($isUsd) <br><span class="text-xs text-orange-400/80">≈ US$ {{ number_format($apportionment / $usd, 2, ',', '.') }}</span> @endif
                        </td>
                        
                        <td class="px-4 py-2 text-green-600 font-bold align-top whitespace-nowrap">
                            R$ {{ number_format($final, 2, ',', '.') }}
                            @if($isUsd) <br><span class="text-xs text-green-500/80 font-normal">≈ US$ {{ number_format($final / $usd, 2, ',', '.') }}</span> @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <p class="text-gray-500 italic">Selecione uma solicitação válida para visualizar os cálculos rateados.</p>
@endif