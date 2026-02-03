<x-filament-panels::page>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    {{-- CSS Específico para limpar espaçamentos extras do Filament Mobile --}}
    <style>
        .fi-main-ctn { padding-top: 0.5rem !important; }
        .fi-header { display: none !important; } /* Garante sumir o header padrão */
    </style>

    {{-- Cabeçalho Compacto Customizado --}}
    <div class="mb-2 border-b pb-2 flex justify-between items-center">
        <h2 class="font-bold text-lg text-gray-800 dark:text-white">Scanner</h2>
        <span class="text-xs text-gray-500 bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded">
            {{ $foundProduct ? 'Modo: Upload' : 'Modo: Leitura' }}
        </span>
    </div>

    {{-- ESTADO 1: ESCANEANDO --}}
    @if(!$foundProduct)
        <div class="space-y-4">
            <div class="p-2 bg-white rounded-lg shadow-sm dark:bg-gray-800 border dark:border-gray-700">
                <div id="reader" class="w-full rounded overflow-hidden"></div>
                <p class="text-center text-xs text-gray-400 mt-2">Aponte para o código EAN</p>
            </div>
        </div>
    @endif

    {{-- ESTADO 2: PRODUTO ENCONTRADO --}}
    @if($foundProduct)
        <div class="flex flex-col h-full">
            
            {{-- Card do Produto (Compacto) --}}
            <div class="bg-blue-50 dark:bg-blue-900/30 border-l-4 border-blue-500 p-3 mb-4 rounded-r shadow-sm">
                <h3 class="font-bold text-sm text-gray-900 dark:text-gray-100 leading-tight">
                    {{ $foundProduct->product_name }}
                </h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 font-mono">
                    EAN: {{ $scannedCode }}
                </p>
            </div>

            {{-- Formulário de Upload --}}
            <form wire:submit="save" class="flex flex-col gap-4">
                
                {{-- Área da Imagem --}}
                <div class="bg-white dark:bg-gray-900 rounded-lg p-1">
                    {{ $this->form }}
                </div>
                
                {{-- Botões 70/30 na linha de baixo --}}
                <div class="flex gap-2 w-full mt-2">
                    
                    {{-- Botão Cancelar (30%) --}}
                    <div style="width: 30%;">
                        <x-filament::button 
                            wire:click="resetScanner" 
                            color="gray" 
                            type="button" 
                            size="lg"
                            class="w-full justify-center h-12"> {{-- h-12 facilita o toque --}}
                            <span class="text-xs sm:text-sm">Cancelar</span>
                        </x-filament::button>
                    </div>

                    {{-- Botão Salvar (70%) --}}
                    <div style="width: 70%;">
                        <x-filament::button 
                            type="submit" 
                            color="success" 
                            size="lg"
                            class="w-full justify-center h-12 shadow-lg">
                            <div class="flex items-center gap-2">
                                <x-heroicon-o-camera class="w-5 h-5"/>
                                <span class="font-bold">SALVAR FOTO</span>
                            </div>
                        </x-filament::button>
                    </div>

                </div>
            </form>
        </div>
    @endif

    <script>
        document.addEventListener('livewire:initialized', () => {
            let html5QrcodeScanner = null;

            function startScanner() {
                if (@json($foundProduct)) return;
                
                if (html5QrcodeScanner) html5QrcodeScanner.clear();

                // Configuração otimizada para Mobile
                html5QrcodeScanner = new Html5QrcodeScanner(
                    "reader", 
                    { 
                        fps: 10, 
                        qrbox: {width: 250, height: 150},
                        aspectRatio: 1.0,
                        showTorchButtonIfSupported: true 
                    },
                    false
                );
                html5QrcodeScanner.render(onScanSuccess, onScanFailure);
            }

            function onScanSuccess(decodedText) {
                html5QrcodeScanner.clear();
                @this.handleBarcodeScan(decodedText);
            }

            function onScanFailure(error) {
                if (error?.includes("permission")) {
                    alert("Erro: Permita o acesso à câmera e use HTTPS.");
                }
            }

            startScanner();

            Livewire.on('reset-scanner', () => {
                setTimeout(startScanner, 300);
            });
        });
    </script>
</x-filament-panels::page>