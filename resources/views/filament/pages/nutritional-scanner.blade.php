<x-filament-panels::page>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    {{-- CSS MÁGICO: Remove o cabeçalho padrão do Filament e ajusta margens mobile --}}
    <style>
        .fi-header { display: none !important; } 
        .fi-main-ctn { padding-top: 10px !important; }
        .fi-form-actions { display: none !important; } /* Esconde botões padrão se houver */
    </style>

    {{-- Cabeçalho Personalizado (Compacto) --}}
    <div class="flex items-center justify-between pb-3 border-b mb-3">
        <h2 class="text-xl font-bold text-gray-800 dark:text-white">
            Scanner
        </h2>
        <div class="px-2 py-1 text-xs font-bold rounded bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
            {{ $foundProduct ? 'UPLOAD' : 'LEITURA' }}
        </div>
    </div>

    {{-- ESTADO 1: CÂMERA (LEITURA) --}}
    @if(!$foundProduct)
        <div class="space-y-4">
            <div class="p-3 bg-white border rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
                <div id="reader" class="w-full overflow-hidden rounded-lg bg-black"></div>
                <p class="mt-3 text-center text-sm text-gray-500">
                    Aponte a câmera para o código de barras
                </p>
            </div>
        </div>
    @endif

    {{-- ESTADO 2: PRODUTO ENCONTRADO (UPLOAD) --}}
    @if($foundProduct)
        <div class="flex flex-col h-full">
            
            {{-- Card de Informação do Produto --}}
            <div class="mb-4 p-3 bg-blue-50 border-l-4 border-blue-500 rounded shadow-sm dark:bg-blue-900/20">
                <h3 class="font-bold text-gray-900 dark:text-white leading-tight">
                    {{ $foundProduct->product_name }}
                </h3>
                <p class="mt-1 text-xs font-mono text-gray-500 dark:text-gray-400">
                    EAN: {{ $scannedCode }}
                </p>
            </div>

            {{-- Formulário --}}
            <form wire:submit="save">
                <div class="mb-4">
                    {{ $this->form }}
                </div>
                
                {{-- GRID DE BOTÕES OTIMIZADA (30% / 70%) --}}
                <div class="flex w-full gap-2">
                    
                    {{-- Botão Cancelar (30%) --}}
                    <div style="width: 30%">
                        <x-filament::button 
                            wire:click="resetScanner" 
                            type="button" 
                            color="gray" 
                            size="lg" 
                            class="w-full h-12 flex justify-center items-center">
                            Cancelar
                        </x-filament::button>
                    </div>

                    {{-- Botão Salvar (70%) --}}
                    <div style="width: 70%">
                        <x-filament::button 
                            type="submit" 
                            color="success" 
                            size="lg" 
                            class="w-full h-12 flex justify-center items-center shadow-md">
                            <x-heroicon-m-camera class="w-5 h-5 mr-2" />
                            <span class="font-bold">SALVAR FOTO</span>
                        </x-filament::button>
                    </div>

                </div>
            </form>
        </div>
    @endif

    {{-- Scripts do Scanner --}}
    <script>
        document.addEventListener('livewire:initialized', () => {
            let html5QrcodeScanner = null;

            function startScanner() {
                // Se já achou produto, não liga câmera
                if (@json($foundProduct)) return;
                
                // Limpa instância anterior se houver
                if (html5QrcodeScanner) {
                    try { html5QrcodeScanner.clear(); } catch(e) {}
                }

                // Configuração Otimizada para Celular
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
                if (html5QrcodeScanner) {
                    html5QrcodeScanner.clear();
                }
                @this.handleBarcodeScan(decodedText);
            }

            function onScanFailure(error) {
                // Silencioso para erros comuns de leitura frame a frame
                if (error?.includes("permission")) {
                    alert("Erro: Dê permissão para a câmera e use HTTPS.");
                }
            }

            // Inicia ao carregar
            startScanner();

            // Ouve evento do PHP para resetar
            Livewire.on('reset-scanner', () => {
                setTimeout(startScanner, 300);
            });
        });
    </script>
</x-filament-panels::page>