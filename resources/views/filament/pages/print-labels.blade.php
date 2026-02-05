<x-filament-panels::page>
    
    <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow border border-gray-200 dark:border-gray-700">
        <x-filament-panels::form wire:submit="searchProduct">
            {{ $this->form }}
        </x-filament-panels::form>
    </div>

    @if($this->product)
        <div class="animate-fade-in-up space-y-6">

            <div class="bg-gray-50 dark:bg-gray-900 p-4 rounded-lg border border-gray-200 dark:border-gray-700 flex flex-col md:flex-row items-center justify-between gap-6">
                
                <div class="flex flex-col items-center">
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Preview da Etiqueta (100x80mm)</span>
                    <div class="bg-white shadow-lg border border-gray-300 transform transition-transform hover:scale-105 origin-left" style="width: 100mm; height: 80mm; overflow: hidden;">
                        @include('components.fda-label-template', [
                            'product' => $this->product,
                            'settings' => $settings
                        ])
                    </div>
                </div>

                <div class="flex flex-col items-end gap-2">
                    <x-filament::button
                        color="success"
                        icon="heroicon-o-printer"
                        size="xl"
                        class="w-full md:w-auto px-8 py-4 text-lg shadow-xl"
                        x-on:click="
                            document.getElementById('printFrame').src = '{{ route('print.label', ['product' => $this->product?->id ?? '0']) }}?qty={{ $this->quantity }}';
                            new FilamentNotification().title('Enviado para impressão').success().send();
                        "
                    >
                        IMPRIMIR ETIQUETAS
                    </x-filament::button>
                    <span class="text-sm text-gray-500">Quantidade configurada: <strong>{{ $this->quantity }}</strong></span>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden flex flex-col h-full">
                    <div class="p-3 bg-gray-100 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600 flex items-center justify-between">
                        <h3 class="font-bold text-gray-700 dark:text-gray-200 flex items-center gap-2">
                            <x-heroicon-o-photo class="w-5 h-5"/>
                            Foto Original (Referência)
                        </h3>
                    </div>
                    
                    <div class="flex-1 bg-black flex items-center justify-center p-4 relative min-h-[400px]">
                        @if($this->product->image_nutritional)
                            <img 
                                src="{{ asset('storage/' . $this->product->image_nutritional) }}" 
                                class="max-w-full max-h-[600px] object-contain rounded border border-gray-700"
                                alt="Foto Tabela Nutricional"
                            >
                        @else
                            <div class="flex flex-col items-center text-gray-500 opacity-60">
                                <x-heroicon-o-camera class="w-16 h-16 mb-2"/>
                                <span class="text-sm">Nenhuma imagem capturada</span>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden flex flex-col h-full">
                    <div class="p-3 bg-gray-100 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600 flex items-center justify-between">
                        <h3 class="font-bold text-gray-700 dark:text-gray-200 flex items-center gap-2">
                            <x-heroicon-o-table-cells class="w-5 h-5"/>
                            Dados do Sistema (Extração IA)
                        </h3>
                        <span class="text-xs bg-gray-200 dark:bg-gray-600 px-2 py-1 rounded text-gray-600 dark:text-gray-300">
                            ID: {{ $this->product->id }}
                        </span>
                    </div>

                    <div class="p-5 space-y-5 overflow-y-auto max-h-[600px]">
                        
                        <div>
                            <div class="text-sm text-gray-400 uppercase font-bold">Product Name (EN)</div>
                            <div class="text-lg font-bold text-green-600 dark:text-green-400 leading-tight">
                                {{ $this->product->product_name_en ?? 'PENDENTE DE TRADUÇÃO' }}
                            </div>
                            <div class="text-sm text-gray-500 mt-1">{{ $this->product->product_name }}</div>
                        </div>

                        <div class="border-2 border-black dark:border-gray-500 p-1 bg-white text-black">
                            <h4 class="font-black text-xl border-b border-black pb-1">Nutrition Facts</h4>
                            <div class="text-sm border-b-4 border-black pb-1 mb-1">
                                <div class="flex justify-between font-bold">
                                    <span>Serving size</span>
                                    <span>{{ $this->product->serving_size_quantity }}{{ $this->product->serving_size_unit }} ({{ $this->product->serving_weight }})</span>
                                </div>
                            </div>
                            
                            <div class="flex justify-between items-end border-b-4 border-black pb-1">
                                <div>
                                    <div class="font-bold text-xs">Amount per serving</div>
                                    <div class="font-black text-2xl">Calories</div>
                                </div>
                                <div class="font-black text-3xl">{{ $this->product->calories }}</div>
                            </div>

                            <div class="text-xs w-full text-right font-bold border-b border-black py-1">% Daily Value*</div>

                            <table class="w-full text-sm">
                                <tbody class="divide-y divide-gray-300">
                                    <tr>
                                        <td class="py-1"><span class="font-bold">Total Fat</span> {{ $this->product->total_fat }}g</td>
                                        <td class="text-right font-bold">{{ $this->product->total_fat_dv }}%</td>
                                    </tr>
                                    <tr>
                                        <td class="pl-4 py-1">Saturated Fat {{ $this->product->sat_fat }}g</td>
                                        <td class="text-right font-bold">{{ $this->product->sat_fat_dv }}%</td>
                                    </tr>
                                    <tr>
                                        <td class="pl-4 py-1">Trans Fat {{ $this->product->trans_fat }}g</td>
                                        <td class="text-right"></td>
                                    </tr>
                                    <tr>
                                        <td class="py-1"><span class="font-bold">Cholesterol</span> {{ $this->product->cholesterol }}mg</td>
                                        <td class="text-right font-bold">{{ $this->product->cholesterol_dv }}%</td>
                                    </tr>
                                    <tr>
                                        <td class="py-1"><span class="font-bold">Sodium</span> {{ $this->product->sodium }}mg</td>
                                        <td class="text-right font-bold">{{ $this->product->sodium_dv }}%</td>
                                    </tr>
                                    <tr>
                                        <td class="py-1"><span class="font-bold">Total Carbohydrate</span> {{ $this->product->total_carb }}g</td>
                                        <td class="text-right font-bold">{{ $this->product->total_carb_dv }}%</td>
                                    </tr>
                                    <tr>
                                        <td class="pl-4 py-1">Dietary Fiber {{ $this->product->fiber }}g</td>
                                        <td class="text-right font-bold">{{ $this->product->fiber_dv }}%</td>
                                    </tr>
                                    <tr>
                                        <td class="pl-4 py-1">
                                            Total Sugars {{ $this->product->total_sugars }}g
                                            <div class="pl-2 text-xs">Includes {{ $this->product->added_sugars }}g Added Sugars</div>
                                        </td>
                                        <td class="text-right align-bottom font-bold">{{ $this->product->added_sugars_dv }}%</td>
                                    </tr>
                                    <tr>
                                        <td class="py-1 border-t-4 border-black"><span class="font-bold">Protein</span> {{ $this->product->protein }}g</td>
                                        <td class="text-right border-t-4 border-black"></td>
                                    </tr>
                                </tbody>
                            </table>
                            <div class="border-t border-black pt-1 text-[10px] leading-tight mt-1">
                                Vitamin D {{ $this->product->vitamin_d }} • Calcium {{ $this->product->calcium }} • Iron {{ $this->product->iron }} • Potassium {{ $this->product->potassium }}
                            </div>
                        </div>

                        <div class="space-y-3 pt-2">
                            <div class="bg-gray-50 dark:bg-gray-900 p-3 rounded border border-gray-200 dark:border-gray-700">
                                <span class="block text-xs font-bold text-blue-500 uppercase mb-1">Ingredients (EN)</span>
                                <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
                                    {{ $this->product->ingredients ?? 'Nenhum ingrediente cadastrado.' }}
                                </p>
                            </div>

                            @if($this->product->allergens_contains || $this->product->allergens_may_contain)
                            <div class="grid grid-cols-2 gap-4">
                                <div class="bg-red-50 dark:bg-red-900/20 p-2 rounded border border-red-100 dark:border-red-900/30">
                                    <span class="block text-[10px] font-bold text-red-600 uppercase">Contains</span>
                                    <span class="text-sm font-bold text-red-700 dark:text-red-400">{{ $this->product->allergens_contains ?? '-' }}</span>
                                </div>
                                <div class="bg-yellow-50 dark:bg-yellow-900/20 p-2 rounded border border-yellow-100 dark:border-yellow-900/30">
                                    <span class="block text-[10px] font-bold text-yellow-600 uppercase">May Contain</span>
                                    <span class="text-sm text-yellow-700 dark:text-yellow-400">{{ $this->product->allergens_may_contain ?? '-' }}</span>
                                </div>
                            </div>
                            @endif
                        </div>

                    </div>
                </div>
            </div>

        </div>
    @else
        <div class="mt-12 flex flex-col items-center justify-center text-gray-400 animate-pulse">
            <x-heroicon-o-qr-code class="w-24 h-24 mb-4 opacity-20"/>
            <p class="text-xl font-medium">Bipe um produto para começar</p>
            <p class="text-sm opacity-60">Use o campo de busca acima</p>
        </div>
    @endif

    <iframe id="printFrame" src="" style="width:0;height:0;border:0;border:none;"></iframe>

</x-filament-panels::page>