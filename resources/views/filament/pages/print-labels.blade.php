<x-filament-panels::page>
    
    <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow border border-gray-200 dark:border-gray-700">
        <x-filament-panels::form wire:submit="searchProduct">
            {{ $this->form }}
        </x-filament-panels::form>
    </div>

    @if($this->product)
        <div class="animate-fade-in-up mt-6">
            
            {{-- Forçando 3 colunas verticais (lado a lado) --}}
            <div class="flex flex-row gap-6 items-stretch w-full overflow-x-auto">
                
                {{-- COLUNA 1: PREVIEW DA ETIQUETA --}}
                <div class="flex-1 min-w-[380px] flex flex-col items-center bg-gray-50 dark:bg-gray-900 p-6 rounded-xl border border-gray-200 dark:border-gray-700">
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4 text-center">
                        Preview: {{ $this->labelLayout === 'tabular' ? 'Tabular (Modo Duplo)' : 'Padrão (100x80mm)' }}
                    </span>
                    
                    <div class="bg-white shadow-lg border border-gray-300 relative overflow-hidden flex items-center justify-center" 
                         style="width: 100mm; height: 80mm;">
                        @if($this->labelLayout === 'standard')
                            @include('components.fda-label-template', [
                                'product' => $this->product,
                                'settings' => $settings
                            ])
                        @else
                            @include('components.fda-label-tabular', [
                                'product' => $this->product, 
                                'settings' => $settings
                            ])
                        @endif
                    </div>
                </div>

                {{-- COLUNA 2: DADOS DO PRODUTO --}}
                <div class="flex-1 min-w-[250px] flex flex-col space-y-5 bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                    
                    {{-- Bloco 1: Status --}}
                    <div>
                        <span class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Status da Integração</span>
                        @php
                            $status = $this->product->import_status ?? 'Indefinido';
                            $badgeColor = match($status) {
                                'Liberado' => 'success',
                                'Bloqueado' => 'danger',
                                default => 'warning',
                            };
                        @endphp
                        
                        <div class="inline-flex">
                            <x-filament::badge :color="$badgeColor" size="lg">
                                {{ $status }}
                            </x-filament::badge>
                        </div>
                    </div>

                    <hr class="border-gray-100 dark:border-gray-700">
                    <br>
                    
                    {{-- Bloco 2: Produto (PT e EN agrupados) --}}
                    <div>
                        <span class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Produto</span>
                        
                        <div class="text-lg font-extrabold text-gray-900 dark:text-gray-100 leading-tight mb-1">
                            {{ $this->product->product_name }}
                        </div>
                        
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400 italic leading-snug">
                            {{ $this->product->product_name_en ?? 'Pendente de tradução' }}
                        </div>
                    </div>
                    <br>
                    
                    <hr class="border-gray-100 dark:border-gray-700">

                    {{-- Bloco 3: Quantidade --}}
                    <div>
                        <span class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Qtd Cx</span>
                        <div class="text-lg font-bold text-gray-900 dark:text-gray-100">
                            {{ $this->product->qtunitcx ?? 'N/D' }}
                        </div>
                    </div>
                </div>

                {{-- COLUNA 3: BOTÃO DE IMPRESSÃO --}}
                <div class="flex-1 min-w-[250px] flex flex-col items-center justify-center bg-gray-50 dark:bg-gray-900 p-6 rounded-xl border border-gray-200 dark:border-gray-700">
                    <x-filament::button
                        color="success"
                        icon="heroicon-o-printer"
                        size="xl"
                        class="w-full max-w-xs px-8 py-6 text-lg shadow-xl transition-transform hover:scale-105"
                        x-on:click="
                            document.getElementById('printFrame').src = '{{ route('print.label', ['product' => $this->product?->id ?? '0']) }}?layout={{ $this->labelLayout }}';
                            new FilamentNotification().title('Janela de impressão aberta').success().send();
                        "
                    >
                        IMPRIMIR ETIQUETAS
                    </x-filament::button>
                    
                    
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