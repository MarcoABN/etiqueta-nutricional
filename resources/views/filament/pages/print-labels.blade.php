<x-filament-panels::page>
    
    <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow border border-gray-200 dark:border-gray-700">
        <div class="flex flex-col md:flex-row items-end gap-4">
            
            <div class="flex-grow w-full md:w-auto">
                <x-filament-panels::form wire:submit="searchProduct">
                    {{ $this->form }}
                </x-filament-panels::form>
            </div>

            <div class="w-full md:w-auto pb-1"> <x-filament::button
                    onclick="printDirectly()"
                    color="primary"
                    icon="heroicon-o-printer"
                    class="w-full md:w-auto h-11" :disabled="!$this->product"
                >
                    IMPRIMIR
                </x-filament::button>
            </div>
        </div>
    </div>

    @if($this->product)
        <div class="space-y-6 mt-6">
            
            <div class="flex flex-col items-center justify-center bg-gray-100 dark:bg-gray-900 p-8 rounded-lg border border-dashed border-gray-400">
                <div class="flex items-center gap-2 mb-4 text-gray-500">
                    <x-heroicon-o-eye class="w-5 h-5"/>
                    <span class="text-sm font-medium uppercase tracking-wider">Preview de Impressão (114x80mm)</span>
                </div>
                
                <div class="shadow-2xl border border-gray-300 bg-white">
                    @include('components.fda-label-template', [
                    'product' => $this->product,
                    'settings' => $settings // <--- Passando aqui também
                ])
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow border-l-4 border-green-500 overflow-hidden">
                <div class="p-4 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600 flex justify-between items-center">
                    <h3 class="font-bold text-gray-700 dark:text-gray-200 flex items-center gap-2">
                        <x-heroicon-o-check-circle class="w-5 h-5 text-green-500"/>
                        Dados Obrigatórios (FDA)
                    </h3>
                    <span class="text-xs text-gray-500">Cód: {{ $this->product->codprod }}</span>
                </div>

                <div class="p-6 grid grid-cols-1 gap-6">
                    <div>
                        <span class="block text-xs font-bold text-gray-400 uppercase mb-1">Statement of Identity (Nome EN)</span>
                        <div class="text-lg font-bold text-green-700 dark:text-green-400">
                            {{ $this->product->product_name_en }}
                        </div>
                        <div class="text-sm text-gray-400">{{ $this->product->product_name }} (Ref. PT)</div>
                    </div>

                    <div>
                        <span class="block text-xs font-bold text-gray-400 uppercase mb-1">Ingredient List</span>
                        <div class="text-sm text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-900 p-3 rounded border">
                            {{ $this->product->ingredients }}
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <span class="block text-xs font-bold text-gray-400 uppercase mb-1">Allergens</span>
                            <div class="text-sm">
                                <span class="font-bold text-red-600">Contains:</span> {{ $this->product->allergens_contains ?? 'N/A' }}
                            </div>
                            @if($this->product->allergens_may_contain)
                                <div class="text-sm mt-1">
                                    <span class="font-bold text-orange-500">May Contain:</span> {{ $this->product->allergens_may_contain }}
                                </div>
                            @endif
                        </div>

                        <div>
                            <span class="block text-xs font-bold text-gray-400 uppercase mb-1">Distributor / Origin</span>
                            <div class="text-sm text-gray-600 dark:text-gray-400 whitespace-pre-line">
                                {{ $this->product->imported_by }}
                            </div>
                            <div class="text-sm font-bold mt-1">Product of {{ $this->product->origin_country ?? 'Brazil' }}</div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <iframe id="printFrame" src="" style="width:0;height:0;border:0;border:none;"></iframe>

        <div class="w-full md:w-auto pb-1">
    <x-filament::button
        color="primary"
        icon="heroicon-o-printer"
        class="w-full md:w-auto h-11"
        :disabled="!$this->product"
        x-on:click="
            // Pega a URL gerada pelo PHP
            const url = '{{ route('print.label', ['product' => $this->product->id]) }}?qty={{ $this->quantity }}';
            
            // Define o SRC do iframe (dispara a impressão)
            document.getElementById('printFrame').src = url;
            
            // Feedback visual (opcional)
            new FilamentNotification()
                .title('Enviado para impressão')
                .success()
                .send();
        "
    >
        IMPRIMIR
    </x-filament::button>
</div>
    @else
        <div class="mt-10 flex flex-col items-center justify-center text-gray-400">
            <x-heroicon-o-qr-code class="w-16 h-16 mb-4 opacity-20"/>
            <p class="text-lg">Aguardando leitura do código...</p>
        </div>
    @endif
</x-filament-panels::page>