<x-filament-panels::page>
    {{-- Carrega biblioteca de leitura de QR/Barcode --}}
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    {{-- ESTADO 1: ESCANEANDO (Mostra câmera) --}}
    @if(!$foundProduct)
        <div class="space-y-4">
            <div class="p-4 bg-white rounded-lg shadow dark:bg-gray-800">
                <h2 class="text-lg font-bold mb-2 text-center">Aponte para o Código de Barras</h2>
                
                {{-- Área da Câmera --}}
                <div id="reader" width="600px" class="mx-auto border-2 border-gray-300 rounded-lg overflow-hidden"></div>
                
                <div class="mt-4 text-center text-sm text-gray-500">
                    Aguardando leitura...
                </div>
            </div>
        </div>
    @endif

    {{-- ESTADO 2: PRODUTO ENCONTRADO (Mostra Upload) --}}
    @if($foundProduct)
        <div class="space-y-4">
            <div class="p-4 bg-green-50 border border-green-200 rounded-lg dark:bg-green-900/20 dark:border-green-800">
                <h3 class="font-bold text-green-800 dark:text-green-400">
                    {{ $foundProduct->product_name }}
                </h3>
                <p class="text-sm text-green-700 dark:text-green-500">
                    EAN: {{ $scannedCode }}
                </p>
            </div>

            {{-- Formulário do Filament (FileUpload) --}}
            <form wire:submit="save">
                {{ $this->form }}
                
                <div class="mt-4 flex gap-3">
                    <x-filament::button type="submit" color="success" class="w-full">
                        Salvar e Próximo
                    </x-filament::button>
                    
                    <x-filament::button wire:click="resetScanner" color="gray" type="button">
                        Cancelar
                    </x-filament::button>
                </div>
            </form>
        </div>
    @endif

    {{-- SCRIPT DE CONTROLE DA CÂMERA --}}
    <script>
        document.addEventListener('livewire:initialized', () => {
            let html5QrcodeScanner = null;

            function startScanner() {
                // Se já existir produto encontrado, não liga a câmera
                if (@json($foundProduct)) return;

                // Destrói instância anterior se existir para evitar travar
                if (html5QrcodeScanner) { 
                    html5QrcodeScanner.clear(); 
                }

                html5QrcodeScanner = new Html5QrcodeScanner(
                    "reader", 
                    { 
                        fps: 10, 
                        qrbox: {width: 250, height: 150}, // Retângulo estilo código de barras
                        aspectRatio: 1.0
                    },
                    /* verbose= */ false
                );

                html5QrcodeScanner.render(onScanSuccess, onScanFailure);
            }

            function onScanSuccess(decodedText, decodedResult) {
                // Toca um bipe (opcional)
                // new Audio('/beep.mp3').play();

                console.log(`Código lido: ${decodedText}`);
                
                // Para a câmera
                html5QrcodeScanner.clear();

                // Manda para o Livewire (PHP)
                @this.handleBarcodeScan(decodedText);
            }

            function onScanFailure(error) {
                // Falhas comuns de leitura, não precisa fazer nada
            }

            // Inicia ao carregar
            startScanner();

            // Ouve evento do PHP para reiniciar scanner (quando clica em "Salvar e Próximo")
            Livewire.on('reset-scanner', () => {
                setTimeout(startScanner, 500); // Pequeno delay para a DOM atualizar
            });
        });
    </script>
</x-filament-panels::page>