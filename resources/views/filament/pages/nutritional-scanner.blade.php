<x-filament-panels::page>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    <style>
        /* Remove cabeçalhos e paddings excessivos do Filament */
        .fi-header { display: none !important; } 
        .fi-main-ctn { padding-top: 10px !important; }
        .fi-form-actions { display: none !important; }
        
        /* Ajuste para o FileUpload parecer mais um botão de câmera */
        .filepond--drop-label { color: #4ade80; font-weight: bold; }
        .filepond--panel-root { background-color: #f3f4f6; border: 2px dashed #ccc; }
    </style>

    {{-- Cabeçalho Minimalista --}}
    <div class="flex items-center justify-between pb-2 border-b mb-2">
        <h2 class="text-lg font-bold text-gray-800 dark:text-white">
            Scanner
        </h2>
        <div class="px-2 py-0.5 text-xs font-bold rounded bg-gray-200 dark:bg-gray-700">
            {{ $foundProduct ? 'PASSO 2: FOTO' : 'PASSO 1: EAN' }}
        </div>
    </div>

    {{-- PASSO 1: LEITOR DE CÓDIGO DE BARRAS --}}
    {{-- Só aparece se NÃO tiver achado produto ainda --}}
    @if(!$foundProduct)
        <div class="flex flex-col items-center justify-center space-y-4 h-[60vh]">
            <div class="w-full bg-black rounded-lg overflow-hidden shadow-lg relative">
                <div id="reader" class="w-full"></div>
                
                {{-- Mira visual sobre o scanner --}}
                <div class="absolute inset-0 pointer-events-none border-2 border-red-500/50 rounded-lg"></div>
            </div>
            <p class="text-center text-gray-500 animate-pulse">
                Aponte a câmera para o código de barras
            </p>
        </div>
    @endif

    {{-- PASSO 2: FOTO DA TABELA E SALVAR --}}
    {{-- Só aparece se o produto FOI encontrado --}}
    @if($foundProduct)
        <div class="flex flex-col h-full">
            
            {{-- Resumo do Produto Encontrado --}}
            <div class="mb-3 p-3 bg-green-50 border border-green-200 rounded-lg shadow-sm dark:bg-green-900/20 dark:border-green-800">
                <h3 class="font-bold text-gray-900 dark:text-white text-sm leading-tight">
                    {{ $foundProduct->product_name }}
                </h3>
                <p class="mt-1 text-xs font-mono text-gray-500 dark:text-gray-400">
                    EAN: {{ $scannedCode }}
                </p>
            </div>

            <form wire:submit="save" class="flex flex-col gap-4">
                
                {{-- Área de Upload (Já configurada para abrir câmera) --}}
                <div class="bg-white dark:bg-gray-800 p-2 rounded-lg shadow-sm border">
                    <p class="mb-2 text-xs font-bold text-gray-500 uppercase text-center">
                        Toque abaixo para tirar a foto da tabela
                    </p>
                    {{ $this->form }}
                </div>
                
                {{-- Botões de Ação (30/70) --}}
                <div class="flex w-full gap-2 mt-2">
                    
                    {{-- CANCELAR (30%) --}}
                    <div style="width: 30%">
                        <x-filament::button 
                            wire:click="resetScanner" 
                            type="button" 
                            color="gray" 
                            size="lg" 
                            class="w-full h-14 flex justify-center items-center">
                            <span class="text-xs font-bold">CANCELAR</span>
                        </x-filament::button>
                    </div>

                    {{-- SALVAR (70%) --}}
                    <div style="width: 70%">
                        <x-filament::button 
                            type="submit" 
                            color="success" 
                            size="lg" 
                            class="w-full h-14 flex justify-center items-center shadow-lg">
                            <x-heroicon-m-check-circle class="w-6 h-6 mr-2" />
                            <span class="font-bold">SALVAR & PRÓXIMO</span>
                        </x-filament::button>
                    </div>

                </div>
            </form>
        </div>
    @endif

    {{-- Lógica JS do Scanner --}}
    <script>
        document.addEventListener('livewire:initialized', () => {
            let scanner = null;

            function initScanner() {
                // Se já estamos na etapa 2 (produto achado), não liga câmera
                if (@json($foundProduct)) return;

                // Limpa anterior para evitar conflito
                if (scanner) { try { scanner.clear(); } catch(e) {} }

                // Configuração para melhor performance no mobile
                scanner = new Html5QrcodeScanner(
                    "reader", 
                    { 
                        fps: 10, 
                        qrbox: {width: 250, height: 150},
                        aspectRatio: 1.0,
                        showTorchButtonIfSupported: true, // Botão de lanterna
                        rememberLastUsedCamera: true
                    },
                    false
                );

                scanner.render(onScanSuccess, onScanFailure);
            }

            function onScanSuccess(decodedText) {
                // Para o scanner visualmente
                if (scanner) scanner.clear();
                
                // Envia para o PHP processar
                @this.handleBarcodeScan(decodedText);
            }

            function onScanFailure(error) {
                // Ignora erros de frame vazio
                if (error?.includes("permission")) {
                    alert("Erro: O navegador bloqueou a câmera. Verifique as permissões e o HTTPS.");
                }
            }

            // Inicia na carga da página
            initScanner();

            // Reinicia quando o usuário clica em "Cancelar" ou "Salvar e Próximo"
            Livewire.on('reset-scanner', () => {
                setTimeout(initScanner, 300);
            });
        });
    </script>
</x-filament-panels::page>