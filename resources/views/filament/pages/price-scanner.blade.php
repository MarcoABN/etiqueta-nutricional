<x-filament-panels::page>

    {{-- Seleção de Filial (Só aparece se não tiver filial) --}}
    @if(!$selectedFilial)
        <div class="max-w-md mx-auto bg-white p-6 rounded-lg shadow dark:bg-gray-800">
            <h2 class="text-xl font-bold mb-4">Configuração Inicial</h2>
            {{ $this->form }}
        </div>
    @else
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            
            {{-- ÁREA DO SCANNER (Esquerda) --}}
            {{-- Usamos x-show para controlar a visibilidade sem destruir o elemento HTML se possível, ou @if do blade --}}
            <div class="bg-gray-100 p-4 rounded-lg dark:bg-gray-900 text-center relative">
                
                {{-- Cabeçalho da Filial --}}
                <div class="flex justify-between items-center mb-4">
                    <span class="badge bg-primary-500 text-white px-3 py-1 rounded font-bold">
                        Filial: {{ $selectedFilial }}
                    </span>
                    <button wire:click="changeFilial" class="text-sm text-red-500 hover:text-red-700 underline">
                        Trocar
                    </button>
                </div>

                {{-- Lógica de Exibição: Se NÃO tiver produto encontrado, mostra Câmera --}}
                @if(!$foundProduct)
                    <div 
                        x-data="{
                            init() {
                                // Aqui entraria o código de inicialização do seu scanner JS (ex: Html5Qrcode)
                                console.log('Scanner Iniciado');
                                this.startScanner();
                                
                                // OUVINTE DO EVENTO: Quando o PHP diz 'reset-scanner-ui', rodamos isso
                                Livewire.on('reset-scanner-ui', () => {
                                    console.log('Reiniciando Scanner...');
                                    this.startScanner();
                                });
                            },
                            startScanner() {
                                // **COLOQUE AQUI O CÓDIGO PARA DAR START NA SUA LIB DE SCANNER**
                                // Exemplo fictício:
                                // html5QrcodeScanner.render((decodedText) => {
                                //     $wire.handleBarcodeScan(decodedText);
                                // });
                                
                                // Para teste sem câmera real (Input Manual):
                                setTimeout(() => {
                                   this.$refs.inputFoco.focus();
                                }, 100);
                            }
                        }"
                        class="border-2 border-dashed border-gray-400 h-64 flex flex-col items-center justify-center rounded bg-black text-white"
                    >
                        <x-heroicon-o-qr-code class="w-16 h-16 mx-auto mb-2 opacity-50"/>
                        <p class="text-gray-300">Aponte a câmera</p>

                        {{-- Input invisível para capturar leitores de código de barras USB/Bluetooth se houver --}}
                        <input x-ref="inputFoco" type="text" 
                            wire:keydown.enter="handleBarcodeScan($event.target.value)" 
                            class="opacity-0 absolute top-0 left-0 h-full w-full cursor-pointer"
                            inputmode="none" 
                        >
                        
                        {{-- Botão de teste manual (apenas desenvolvimento) --}}
                        <div class="mt-4 z-10">
                            <input type="text" 
                                wire:keydown.enter="handleBarcodeScan($event.target.value)" 
                                placeholder="Digitar EAN manual"
                                class="text-black text-sm p-1 rounded"
                            >
                        </div>
                    </div>
                @else
                    {{-- Feedback visual de sucesso ao ler --}}
                    <div class="h-64 flex flex-col items-center justify-center bg-green-100 dark:bg-green-900 rounded border-2 border-green-500 animate-pulse">
                        <x-heroicon-o-check-circle class="w-20 h-20 text-green-600 dark:text-green-300 mb-2"/>
                        <span class="text-green-800 dark:text-green-100 font-bold text-xl">LIDO COM SUCESSO!</span>
                    </div>
                @endif
            </div>

            {{-- ÁREA DE EDIÇÃO (Direita/Abaixo) --}}
            @if($foundProduct)
                <div class="bg-white p-6 rounded-lg shadow-lg border-t-4 border-primary-500 dark:bg-gray-800 animate-fade-in-up">
                    
                    <h2 class="text-lg font-bold text-gray-700 dark:text-gray-200 mb-4 border-b pb-2">
                        {{ $foundProduct->DESCRICAO }}
                    </h2>

                    <div class="grid grid-cols-2 gap-4 text-sm mb-6">
                        <div>
                            <span class="block text-gray-500 text-xs">CÓDIGO</span>
                            <span class="font-mono font-bold">{{ $foundProduct->CODPROD }}</span>
                        </div>
                        <div>
                            <span class="block text-gray-500 text-xs">EAN</span>
                            <span class="font-mono">{{ $foundProduct->CODAUXILIAR }}</span>
                        </div>
                        <div>
                            <span class="block text-gray-500 text-xs">CUSTO</span>
                            <span class="font-bold text-red-600">R$ {{ number_format($foundProduct->CUSTOULTENT, 2, ',', '.') }}</span>
                        </div>
                        <div>
                            <span class="block text-gray-500 text-xs">VENDA ATUAL</span>
                            <span class="font-bold text-gray-800 dark:text-white">R$ {{ number_format($foundProduct->PVENDA, 2, ',', '.') }}</span>
                        </div>
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                        <label class="block text-center text-sm font-bold text-primary-600 mb-2 uppercase">
                            Novo Preço de Venda
                        </label>
                        
                        <input type="number" step="0.01" 
                            wire:model="novoPreco"
                            wire:keydown.enter="savePrice"
                            class="block w-full text-center text-3xl font-bold text-gray-900 border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500"
                            placeholder="0.00"
                            autofocus
                        />
                        <p class="text-xs text-center text-gray-500 mt-1">Pressione Enter para salvar</p>
                    </div>

                    <div class="flex gap-3 mt-6">
                        <button wire:click="resetProductState" class="flex-1 py-3 bg-gray-200 text-gray-700 font-bold rounded hover:bg-gray-300 transition">
                            CANCELAR
                        </button>
                        <button wire:click="savePrice" class="flex-1 py-3 bg-primary-600 text-white font-bold rounded hover:bg-primary-700 shadow-md transition transform active:scale-95">
                            SALVAR PREÇO
                        </button>
                    </div>
                </div>
            @endif

        </div>
    @endif

</x-filament-panels::page>