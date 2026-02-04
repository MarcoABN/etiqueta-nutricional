<x-filament-panels::page>
    {{-- Importa a biblioteca de QR Code leve --}}
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    {{-- Estilos para transformar a página em "App Mode" --}}
    <style>
        /* Esconde elementos padrões do Filament para tela cheia */
        .fi-topbar, .fi-header, .fi-breadcrumbs, .fi-sidebar, .fi-footer { display: none !important; }
        .fi-main-ctn, .fi-page { padding: 0 !important; margin: 0 !important; max-width: 100% !important; }
        
        /* Container principal */
        .fi-page { 
            height: 100dvh; 
            overflow: hidden; 
            background: #000; 
            color: white; 
            position: relative;
        }

        /* Área do Scanner de Código de Barras */
        #scanner-view { 
            position: absolute; 
            inset: 0; 
            z-index: 10; 
            background: #000; 
        }
        #reader { 
            width: 100%; 
            height: 100%; 
            object-fit: cover; 
        }

        /* Botão de Trocar Câmera */
        .btn-switch-camera {
            position: absolute; top: 20px; right: 20px; z-index: 50;
            width: 48px; height: 48px; border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(5px); border: 1px solid rgba(255,255,255,0.3);
            display: flex; align-items: center; justify-content: center;
            color: white; cursor: pointer;
        }

        /* Área de Edição/Upload (Overlay) */
        .product-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            background: #111827; /* Gray-900 */
            border-top-left-radius: 1.5rem;
            border-top-right-radius: 1.5rem;
            padding: 1.5rem;
            z-index: 100; /* Acima do scanner */
            box-shadow: 0 -4px 20px rgba(0,0,0,0.5);
            display: flex;
            flex-direction: column;
            gap: 1rem;
            max-height: 85vh; /* Evita que cubra tudo em telas pequenas */
            overflow-y: auto;
        }
    </style>

    <div class="app-container">
        
        {{-- 1. CAMADA DO SCANNER (Fundo) --}}
        <div id="scanner-view" wire:ignore>
            <div id="reader"></div>
            <button id="btn-switch" class="btn-switch-camera">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
            </button>
        </div>

        {{-- 2. CAMADA DO PRODUTO ENCONTRADO (Overlay) --}}
        @if($foundProduct)
            <div class="product-overlay">
                {{-- Título do Produto --}}
                <div class="text-center mb-2">
                    <span class="text-xs text-gray-400 uppercase tracking-wider">Produto Identificado</span>
                    <h3 class="text-white text-xl font-bold leading-tight mt-1">
                        {{ $foundProduct->product_name }}
                    </h3>
                </div>
                
                {{-- Formulário do Filament (FileUpload) --}}
                <div class="bg-gray-800 p-3 rounded-xl border border-gray-700">
                    {{ $this->form }}
                </div>

                {{-- Instruções Visuais --}}
                <div class="text-xs text-gray-400 text-center space-y-1">
                    <p>1. Tire a foto 📸</p>
                    <p>2. Clique no <span class="text-amber-400 font-bold">Lápis (✏️)</span> na foto para recortar a tabela</p>
                    <p>3. Clique em Confirmar abaixo</p>
                </div>

                {{-- Ações (Botões Manuais) --}}
                <div class="flex flex-col gap-3 mt-2">
                    {{-- Botão Processar --}}
                    <button 
                        wire:click="processImage" 
                        wire:loading.attr="disabled"
                        class="w-full bg-green-600 hover:bg-green-500 disabled:opacity-50 text-white font-bold py-3 px-4 rounded-xl shadow-lg text-lg flex items-center justify-center gap-2 transition-all"
                    >
                        <span wire:loading.remove wire:target="processImage">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        </span>
                        <span wire:loading wire:target="processImage" class="animate-spin">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        </span>
                        Confirmar e Processar IA
                    </button>

                    {{-- Botão Cancelar --}}
                    <button 
                        wire:click="resetScanner" 
                        class="w-full bg-gray-700 hover:bg-gray-600 text-white font-medium py-3 rounded-xl transition-all"
                    >
                        Cancelar / Ler Outro
                    </button>
                </div>
            </div>
        @endif
    </div>

    {{-- Script de Controle da Câmera --}}
    <script>
        document.addEventListener('livewire:initialized', () => {
            let html5QrCode = null;
            let currentCameraId = null;
            let backCameras = [];

            // Função para carregar câmeras traseiras
            async function loadCameras() {
                try {
                    const devices = await Html5Qrcode.getCameras();
                    if (devices && devices.length) {
                        // Filtra câmeras traseiras (environment)
                        backCameras = devices; 
                        // Tenta pegar a última (geralmente a principal em celulares com varias lentes)
                        currentCameraId = backCameras[backCameras.length - 1].id;
                    }
                } catch (err) {
                    console.error("Erro ao listar câmeras", err);
                }
            }

            // Inicia o Scanner
            async function startScanner() {
                // Se já tiver produto encontrado, NÃO inicia a câmera (para economizar bateria e foco)
                if (@json($foundProduct)) return;

                // Limpa instância anterior se houver bug
                if (html5QrCode) {
                    try { await html5QrCode.stop(); } catch(e) {}
                    html5QrCode = null;
                }

                document.getElementById('reader').innerHTML = '';
                
                if (backCameras.length === 0) await loadCameras();
                if (!currentCameraId && backCameras.length > 0) currentCameraId = backCameras[0].id;

                html5QrCode = new Html5Qrcode("reader");
                
                const config = { 
                    fps: 10, 
                    qrbox: { width: 250, height: 150 }, // Box retangular para código de barras
                    aspectRatio: 1.77 
                };

                html5QrCode.start(
                    currentCameraId ? { deviceId: { exact: currentCameraId } } : { facingMode: "environment" }, 
                    config, 
                    (decodedText) => {
                        // Sucesso na leitura
                        console.log("Lido:", decodedText);
                        html5QrCode.stop().then(() => {
                            html5QrCode = null;
                            // Chama o método PHP
                            @this.handleBarcodeScan(decodedText);
                        });
                    },
                    (errorMessage) => {
                        // Ignora erros de frame vazio
                    }
                ).catch((err) => {
                    console.log("Erro start", err);
                });
            }

            // Botão de Troca de Câmera
            document.getElementById('btn-switch')?.addEventListener('click', async () => {
                if (backCameras.length < 2) return;
                
                // Pega o próximo index
                let index = backCameras.findIndex(c => c.id === currentCameraId);
                let nextIndex = (index + 1) % backCameras.length;
                currentCameraId = backCameras[nextIndex].id;
                
                await startScanner();
            });

            // Inicialização
            startScanner();

            // Evento disparado pelo PHP quando clica em "Cancelar" ou "Confirmar"
            Livewire.on('reset-scanner', () => {
                // Pequeno delay para a UI limpar antes de reabrir a câmera
                setTimeout(() => {
                    startScanner();
                }, 500);
            });
        });
    </script>
</x-filament-panels::page>