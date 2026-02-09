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
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">
                        Preview: {{ $this->labelLayout === 'tabular' ? 'Tabular (Modo Duplo)' : 'Padrão (100x80mm)' }}
                    </span>
                    
                    {{-- CONTAINER DE PREVIEW (100mm x 80mm) --}}
                    {{-- IMPORTANTE: Removemos o grid/rotate daqui para não quebrar o layout --}}
                    <div class="bg-white shadow-lg border border-gray-300 relative overflow-hidden flex items-center justify-center" 
                         style="width: 100mm; height: 80mm;">
                        
                        @if($this->labelLayout === 'standard')
                            @include('components.fda-label-template', [
                                'product' => $this->product,
                                'settings' => $settings
                            ])
                        @else
                            {{-- O componente Tabular JÁ CONTÉM as duas etiquetas rotacionadas --}}
                            @include('components.fda-label-tabular', [
                                'product' => $this->product, 
                                'settings' => $settings
                            ])
                        @endif

                    </div>
                </div>

                <div class="flex flex-col items-end gap-2">
                    <x-filament::button
                        color="success"
                        icon="heroicon-o-printer"
                        size="xl"
                        class="w-full md:w-auto px-8 py-4 text-lg shadow-xl"
                        x-on:click="
                            document.getElementById('printFrame').src = '{{ route('print.label', ['product' => $this->product?->id ?? '0']) }}?qty={{ $this->quantity }}&layout={{ $this->labelLayout }}';
                            new FilamentNotification().title('Enviado para impressão').success().send();
                        "
                    >
                        IMPRIMIR ETIQUETAS
                    </x-filament::button>
                    <span class="text-sm text-gray-500">
                        Configuração: <strong>{{ $this->quantity }}</strong> etiquetas ({{ ucfirst($this->labelLayout) }})
                    </span>
                </div>
            </div>

            {{-- Detalhes do Produto --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden flex flex-col h-full">
                    <div class="p-3 bg-gray-100 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600 flex items-center justify-between">
                        <h3 class="font-bold text-gray-700 dark:text-gray-200 flex items-center gap-2">
                            <x-heroicon-o-photo class="w-5 h-5"/>
                            Foto Original
                        </h3>
                    </div>
                    <div class="flex-1 bg-black flex items-center justify-center p-4 relative min-h-[400px]">
                        @if($this->product->image_nutritional)
                            <img src="{{ asset('storage/' . $this->product->image_nutritional) }}" class="max-w-full max-h-[600px] object-contain rounded border border-gray-700" alt="Foto">
                        @else
                            <div class="flex flex-col items-center text-gray-500 opacity-60">
                                <x-heroicon-o-camera class="w-16 h-16 mb-2"/>
                                <span class="text-sm">Sem imagem</span>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 overflow-hidden flex flex-col h-full">
                     <div class="p-3 bg-gray-100 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600 flex items-center justify-between">
                        <h3 class="font-bold text-gray-700 dark:text-gray-200 flex items-center gap-2">
                            <x-heroicon-o-table-cells class="w-5 h-5"/>
                            Dados Extraídos
                        </h3>
                    </div>
                    <div class="p-5 space-y-5 overflow-y-auto max-h-[600px]">
                        <div>
                             <div class="text-sm text-gray-400 uppercase font-bold">Product Name (EN)</div>
                             <div class="text-lg font-bold text-green-600 dark:text-green-400 leading-tight">
                                {{ $this->product->product_name_en ?? 'PENDENTE DE TRADUÇÃO' }}
                            </div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-900 p-3 rounded border border-gray-200 dark:border-gray-700">
                            <span class="block text-xs font-bold text-blue-500 uppercase mb-1">Ingredients (EN)</span>
                            <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">
                                {{ $this->product->ingredients ?? 'Nenhum ingrediente cadastrado.' }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    @else
        <div class="mt-12 flex flex-col items-center justify-center text-gray-400 animate-pulse">
            <x-heroicon-o-qr-code class="w-24 h-24 mb-4 opacity-20"/>
            <p class="text-xl font-medium">Bipe um produto para começar</p>
        </div>
    @endif

    <iframe id="printFrame" src="" style="width:0;height:0;border:0;border:none;"></iframe>

</x-filament-panels::page>