<x-filament-panels::page>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    <style>
        /* --- LIMPEZA GERAL DO FILAMENT --- */
        .fi-topbar, .fi-header, .fi-breadcrumbs, .fi-logo { display: none !important; }
        .fi-main-ctn { padding: 0 !important; margin: 0 !important; max-width: 100% !important; }
        .fi-page { padding: 0 !important; height: 100vh; overflow: hidden; background-color: #000; }
        
        /* --- ESTILIZAÇÃO DO UPLOAD PARA PARECER CÂMERA --- */
        /* Esconde bordas e fundos do FilePond padrão */
        .filepond--panel-root { background-color: transparent !important; border: none !important; }
        .filepond--root { margin-bottom: 0 !important; height: 100% !important; }
        
        /* Esconde textos padrão "Arraste e solte" */
        .filepond--drop-label { display: none !important; }

        /* Cria o Botão de Obturador (Shutter) */
        .camera-trigger-overlay {
            width: 80px;
            height: 80px;
            background-color: white;
            border-radius: 50%;
            border: 4px solid #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
            transition: transform 0.1s;
        }
        .camera-trigger-overlay:active { transform: scale(0.95); background-color: #f0f0f0; }
        .camera-trigger-inner {
            width: 60px; height: 60px; background-color: #ef4444; border-radius: 50%;
        }

        /* Área de Preview da Imagem (quando carregada) */
        .filepond--item { width: 100%; height: 100%; }
        
        /* Layout Flexível */
        .app-container { height: 100vh; display: flex; flex-direction: column; background-color: #000; }
    </style>

    {{-- BARRA SUPERIOR FIXA --}}
    <div class="fixed top-0 w-full z-50 bg-black/80 backdrop-blur text-white p-3 flex justify-between items-center border-b border-gray-800">
        <span class="font-bold text-lg tracking-wider">
            {{ $foundProduct ? 'FOTO' : 'SCANNER' }}
        </span>
        @if($foundProduct)
             <span class="text-xs font-mono text-green-400 border border-green-400 px-2 py-0.5 rounded">DETECTADO</span>
        @endif
    </div>

    <div class="app-container pt-14">

        {{-- MODO 1: SCANNER DE CÓDIGO DE BARRAS --}}
        <div id="scanner-view" class="{{ $foundProduct ? 'hidden' : 'flex' }} flex-1 relative bg-black items-center justify-center overflow-hidden">
            {{-- Vídeo ocupa tudo --}}
            <div id="reader" style="width: 100%; height: 100%; object-fit: cover;"></div>
            
            {{-- Elementos Visuais Sobrepostos (HUD) --}}
            <div class="absolute inset-0 pointer-events-none flex items-center justify-center">
                {{-- Mira --}}
                <div class="w-64 h-40 border-2 border-red-500/80 rounded-lg relative shadow-[0_0_0_9999px_rgba(0,0,0,0.5)]">
                    <div class="absolute top-0 left-0 w-4 h-4 border-t-4 border-l-4 border-red-500 -mt-1 -ml-1"></div>
                    <div class="absolute top-0 right-0 w-4 h-4 border-t-4 border-r-4 border-red-500 -mt-1 -mr-1"></div>
                    <div class="absolute bottom-0 left-0 w-4 h-4 border-b-4 border-l-4 border-red-500 -mb-1 -ml-1"></div>
                    <div class="absolute bottom-0 right-0 w-4 h-4 border-b-4 border-r-4 border-red-500 -mb-1 -mr-1"></div>
                </div>
            </div>
            
            <p class="absolute bottom-10 text-white/70 text-sm font-medium animate-pulse">
                Aponte para o código de barras
            </p>
        </div>

        {{-- MODO 2: PRODUTO IDENTIFICADO & FOTO --}}
        <div id="photo-view" class="{{ $foundProduct ? 'flex' : 'hidden' }} flex-1 flex-col bg-gray-900 relative">
            
            {{-- Info do Produto (Topo) --}}
            <div class="p-4 bg-gray-800 border-b border-gray-700">
                <h2 class="text-white font-bold text-lg leading-tight truncate">
                    {{ $foundProduct?->product_name }}
                </h2>
                <p class="text-gray-400 text-sm font-mono mt-1">EAN: {{ $scannedCode }}</p>
            </div>

            {{-- Área da Câmera (FileUpload disfarçado) --}}
            <div class="flex-1 relative bg-black flex items-center justify-center overflow-hidden">
                
                {{-- O FileUpload do Filament fica invisível mas clicável por cima de tudo --}}
                <div class="absolute inset-0 z-10 opacity-0 cursor-pointer">
                    {{ $this->form }}
                </div>

                {{-- O Que o usuário VÊ (Interface Fake) --}}
                <div class="pointer-events-none flex flex-col items-center justify-center space-y-4">
                    <x-heroicon-o-camera class="w-20 h-20 text-gray-600"/>
                    <p class="text-gray-500 font-bold uppercase tracking-widest text-sm">Toque para Capturar</p>
                </div>

            </div>

            {{-- Botões de Ação (Base) --}}
            <div class="p-5 bg-black flex justify-between items-center pb-8">
                
                {{-- Botão Voltar --}}
                <button wire:click="resetScanner" type="button" class="text-white p-3 rounded-full hover:bg-gray-800 transition">
                    <x-heroicon-o-x-mark class="w-8 h-8"/>
                </button>

                {{-- Botão "Shutter" (Visual apenas - o clique real vai no FileUpload acima) --}}
                <div class="camera-trigger-overlay pointer-events-none">
                    <div class="camera-trigger-inner"></div>
                </div>

                {{-- Botão Salvar (Só habilita se tiver foto carregada - lógica visual) --}}
                <button wire:click="save" type="button" class="text-green-400 p-3 rounded-full hover:bg-gray-800 transition">
                    <x-heroicon-m-check class="w-10 h-10"/>
                </button>
            </div>
        </div>

    </div>

    {{-- SCRIPTS DO SCANNER --}}
    <script>
        document.addEventListener('livewire:initialized', () => {
            let html5QrCode = null;

            function startScanner() {
                // Se já tem produto, não liga
                if (@json($foundProduct)) {
                    stopScanner();
                    return;
                }

                const config = { fps: 10, qrbox: { width: 250, height: 150 }, aspectRatio: 1.0 };
                
                // Usa a classe PRO (sem UI) em vez do Scanner (com UI)
                html5QrCode = new Html5Qrcode("reader");
                
                html5QrCode.start(
                    { facingMode: "environment" }, // Força câmera traseira
                    config,
                    (decodedText) => {
                        // Sucesso
                        stopScanner();
                        @this.handleBarcodeScan(decodedText);
                    },
                    (errorMessage) => {
                        // Falha de leitura (ignorar logs para performance)
                    }
                ).catch(err => {
                    if (err?.includes("permission")) alert("Erro: Dê permissão à câmera.");
                });
            }

            function stopScanner() {
                if (html5QrCode) {
                    html5QrCode.stop().then(() => {
                        html5QrCode.clear();
                    }).catch(err => {});
                }
            }

            // Inicia
            startScanner();

            // Eventos
            Livewire.on('reset-scanner', () => {
                setTimeout(startScanner, 300);
            });
            
            // Se der erro de EAN não encontrado, reinicia o scanner após o usuário fechar o alerta
            Livewire.on('reset-scanner-error', () => {
                 setTimeout(startScanner, 1000);
            });
        });
    </script>
</x-filament-panels::page>