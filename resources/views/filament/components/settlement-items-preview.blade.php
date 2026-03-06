@php
    $request = \App\Models\Request::with('items.product')->find($requestId);
    $factorDec = (floatval($factor) ?: 70) / 100;
    $totalVal = floatval($totalValue);
    $totalExp = floatval($totalExpenses);

    // Lógica para exibição do Dólar como informação secundária
    $usd = floatval($usdQuote);
    $isUsd = ($showInUsd ?? false) && $usd > 0;

    // Pré-calcula os dados no PHP para o Alpine.js manipular a ordenação
    $itemsData = [];
    if ($request && $request->items->count() > 0) {
        foreach($request->items as $item) {
            $vUnit = $item->unit_price ?? 0;
            $initial = ($item->quantity ?? 0) * $vUnit;
            $partial = $factorDec > 0 ? $initial / $factorDec : 0;
            
            $percentage = $totalVal > 0 ? ($partial / $totalVal) * 100 : 0;
            $apportionment = $totalVal > 0 ? ($partial / $totalVal) * $totalExp : 0;
            
            $final = $partial + $apportionment;

            $itemsData[] = [
                'name' => $item->product_name ?? '',
                'qty' => floatval($item->quantity ?? 0),
                'vUnit' => floatval($vUnit),
                'initial' => floatval($initial),
                'partial' => floatval($partial),
                'apportionment' => floatval($apportionment),
                'percentage' => floatval($percentage),
                'final' => floatval($final),
            ];
        }
    }
@endphp

@if(!empty($itemsData))
    <div 
        x-data="{
            sortCol: 'name',
            sortAsc: true,
            items: @js($itemsData),
            usd: {{ $usd ?: 1 }},
            isUsd: {{ $isUsd ? 'true' : 'false' }},
            
            sortBy(col) {
                if (this.sortCol === col) {
                    this.sortAsc = !this.sortAsc;
                } else {
                    this.sortCol = col;
                    this.sortAsc = true;
                }
            },
            
            get sortedItems() {
                return this.items.sort((a, b) => {
                    let valA = a[this.sortCol];
                    let valB = b[this.sortCol];
                    
                    if (typeof valA === 'string') {
                        valA = valA.toLowerCase();
                        valB = valB.toLowerCase();
                    }
                    
                    if (valA < valB) return this.sortAsc ? -1 : 1;
                    if (valA > valB) return this.sortAsc ? 1 : -1;
                    return 0;
                });
            },
            
            formatBRL(value) {
                return value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
        }"
        class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700"
    >
        <table class="w-full text-sm text-left">
            <thead class="bg-gray-50 dark:bg-gray-800 select-none">
                <tr>
                    <th @click="sortBy('name')" class="px-4 py-2 cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
                        Produto <span x-show="sortCol === 'name'" x-text="sortAsc ? '↑' : '↓'"></span>
                    </th>
                    <th @click="sortBy('qty')" class="px-4 py-2 cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
                        Qtd <span x-show="sortCol === 'qty'" x-text="sortAsc ? '↑' : '↓'"></span>
                    </th>
                    <th @click="sortBy('vUnit')" class="px-4 py-2 cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
                        V. UN <span x-show="sortCol === 'vUnit'" x-text="sortAsc ? '↑' : '↓'"></span>
                    </th>
                    <th @click="sortBy('initial')" class="px-4 py-2 cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors" title="Qtd * Valor UN">
                        Valor Inicial <span x-show="sortCol === 'initial'" x-text="sortAsc ? '↑' : '↓'"></span>
                    </th>
                    <th @click="sortBy('partial')" class="px-4 py-2 cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors" title="Valor Inicial / Fator">
                        Valor Parcial <span x-show="sortCol === 'partial'" x-text="sortAsc ? '↑' : '↓'"></span>
                    </th>
                    <th @click="sortBy('apportionment')" class="px-4 py-2 text-orange-600 cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors" title="Valor rateado + % de participação">
                        Rateio Despesas <span x-show="sortCol === 'apportionment'" x-text="sortAsc ? '↑' : '↓'"></span>
                    </th>
                    <th @click="sortBy('final')" class="px-4 py-2 cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors" title="Valor Parcial + Rateio das Despesas">
                        Valor Final <span x-show="sortCol === 'final'" x-text="sortAsc ? '↑' : '↓'"></span>
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                <template x-for="(item, index) in sortedItems" :key="index">
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="px-4 py-2 align-top" x-text="item.name"></td>
                        <td class="px-4 py-2 align-top" x-text="formatBRL(item.qty)"></td>
                        
                        <td class="px-4 py-2 align-top whitespace-nowrap">
                            R$ <span x-text="formatBRL(item.vUnit)"></span>
                            <template x-if="isUsd">
                                <div><span class="text-xs text-gray-500 dark:text-gray-400">≈ US$ <span x-text="formatBRL(item.vUnit / usd)"></span></span></div>
                            </template>
                        </td>
                        
                        <td class="px-4 py-2 font-medium align-top whitespace-nowrap">
                            R$ <span x-text="formatBRL(item.initial)"></span>
                            <template x-if="isUsd">
                                <div><span class="text-xs text-gray-500 dark:text-gray-400">≈ US$ <span x-text="formatBRL(item.initial / usd)"></span></span></div>
                            </template>
                        </td>
                        
                        <td class="px-4 py-2 text-blue-600 align-top whitespace-nowrap">
                            R$ <span x-text="formatBRL(item.partial)"></span>
                            <template x-if="isUsd">
                                <div><span class="text-xs text-blue-400/80">≈ US$ <span x-text="formatBRL(item.partial / usd)"></span></span></div>
                            </template>
                        </td>
                        
                        <td class="px-4 py-2 text-orange-600 align-top whitespace-nowrap">
                            R$ <span x-text="formatBRL(item.apportionment)"></span>
                            <span class="text-xs text-gray-400 dark:text-gray-500 ml-1">(<span x-text="formatBRL(item.percentage)"></span>%)</span>
                            <template x-if="isUsd">
                                <div><span class="text-xs text-orange-400/80">≈ US$ <span x-text="formatBRL(item.apportionment / usd)"></span></span></div>
                            </template>
                        </td>
                        
                        <td class="px-4 py-2 text-green-600 font-bold align-top whitespace-nowrap">
                            R$ <span x-text="formatBRL(item.final)"></span>
                            <template x-if="isUsd">
                                <div><span class="text-xs text-green-500/80 font-normal">≈ US$ <span x-text="formatBRL(item.final / usd)"></span></span></div>
                            </template>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
@else
    <p class="text-gray-500 italic">Selecione uma solicitação válida para visualizar os cálculos rateados.</p>
@endif