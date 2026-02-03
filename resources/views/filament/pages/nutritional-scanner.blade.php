<x-filament-panels::page class="h-full">
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    <style>
        /* Ajustes para parecer App Nativo */
        .filament-main-content { padding: 0 !important; }
        #reader { width: 100%; border-radius: 12px; overflow: hidden; background: #000; }
        #reader video { object-fit: cover; border-radius: 12px; }
    </style>

    {{-- ESTADO 1: CÂMERA (Leitura) --}}
    <div x-show="!$wire.foundProduct" class="flex flex-col h-screen-safe space-y-4">
        
        {{-- Área da Câmera --}}
        <div class="relative w-full bg-black rounded-xl overflow-hidden shadow-lg aspect-[3/4]">
            <div id="reader" class="w-full h-full"></div>
            
            {{-- Mira Vermelha (Overlay) --}}
            <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                <div class="w-64 h-40 border-2 border-red-500/50 rounded-lg"></div>
            </div>
            
            {{-- Status --}}
            <div class="absolute bottom-4 left-0 right-0 text-center">
                <span class="bg-black/50 text-white px-3 py-1 rounded-full text-sm">
                    Aponte para o código de barras
                </span>
            </div>
        </div>

        {{-- Botões de Controle da Câmera --}}
        <div class="grid grid-cols-1 gap-3 p-2">
            <x-filament::button 
                color="gray" 
                size="xl" 
                class="w-full py-4"
                onclick="restartCameraManual()">
                🔄 Reiniciar Câmera
            </x-filament::button>
        </div>
    </div>

    {{-- ESTADO 2: PRODUTO ENCONTRADO (Formulário) --}}
    <div x-show="$wire.foundProduct" class="space-y-4" x-cloak>
        
        {{-- Card do Produto --}}
        @if($foundProduct)
        <div class="p-4 bg-primary-50 border border-primary-200 rounded-xl shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <div class="text-xs font-bold text-primary-600 uppercase tracking-wider mb-1">Produto Identificado</div>
            <h2 class="text-lg font-bold leading-tight text-gray-900 dark:text-white">
                {{ $foundProduct->product_name }}
            </h2>
            <div class="mt-2 text-sm font-mono text-gray-500">
                EAN: {{ $scannedCode }}
            </div>
        </div>
        @endif

        {{-- Formulário de Upload --}}
        <form wire:submit="save" class="space-y-6">
            {{ $this->form }}

            <div class="grid grid-cols-2 gap-3 pt-4">
                {{-- Botão Voltar/Cancelar --}}
                <x-filament::button 
                    type="button" 
                    color="danger" 
                    size="xl"
                    outlined
                    wire:click="resetScanner">
                    ❌ Cancelar
                </x-filament::button>

                {{-- Botão Salvar --}}
                <x-filament::button 
                    type="submit" 
                    color="success" 
                    size="xl"
                    class="w-full">
                    ✅ Salvar
                </x-filament::button>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener('livewire:initialized', () => {
            let html5QrCode = null;
            const cameraId = "reader";

            // Função Principal de Início
            window.startCamera = function() {
                // Se já existir instância, para antes de recomeçar
                if (html5QrCode) {
                    stopCamera().then(() => initCamera());
                } else {
                    initCamera();
                }
            }

            function initCamera() {
                html5QrCode = new Html5Qrcode(cameraId);
                
                const config = { fps: 10, qrbox: { width: 250, height: 150 } };
                
                // FORÇA CÂMERA TRASEIRA (ENVIRONMENT)
                html5QrCode.start(
                    { facingMode: "environment" }, 
                    config, 
                    onScanSuccess, 
                    onScanFailure
                ).catch(err => {
                    console.log("Erro ao iniciar câmera: ", err);
                    if(err?.name === 'NotAllowedError') {
                        alert('Permissão de câmera negada. Verifique o HTTPS.');
                    }
                });
            }

            window.stopCamera = function() {
                if (html5QrCode && html5QrCode.isScanning) {
                    return html5QrCode.stop().then(() => {
                        html5QrCode.clear();
                    }).catch(err => console.log("Erro ao parar: ", err));
                }
                return Promise.resolve();
            }

            // Ação Manual do Botão de Reinício
            window.restartCameraManual = function() {
                stopCamera().then(() => {
                    setTimeout(startCamera, 200);
                });
            }

            function onScanSuccess(decodedText, decodedResult) {
                console.log(`Lido: ${decodedText}`);
                
                // Pausa a câmera visualmente
                html5QrCode.pause(); 
                
                // Envia para o Backend
                @this.handleBarcodeScan(decodedText);
            }

            function onScanFailure(error) {
                // Ignora erros de frame vazio
            }

            // --- EVENTOS DO LIVEWIRE ---

            // 1. Iniciar ao carregar a página
            startCamera();

            // 2. Quando o PHP manda "start-camera" (após salvar ou cancelar)
            Livewire.on('start-camera', () => {
                // Pequeno delay para a DOM mudar (x-show)
                setTimeout(() => {
                    if(html5QrCode && html5QrCode.isScanning) {
                        html5QrCode.resume();
                    } else {
                        startCamera();
                    }
                }, 300);
            });

            // 3. Quando o produto não é encontrado (retoma leitura)
            Livewire.on('resume-camera', () => {
                setTimeout(() => {
                    if(html5QrCode) html5QrCode.resume();
                }, 1000); // Espera 1s para o usuário ver o erro
            });
        });
    </script>
</x-filament-panels::page>