<x-filament-panels::page>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    <style>
        /* Reset para tela cheia mobile */
        .fi-topbar, .fi-header, .fi-breadcrumbs, .fi-sidebar, .fi-footer { display: none !important; }
        .fi-main-ctn, .fi-page { padding: 0 !important; margin: 0 !important; max-width: 100% !important; }
        .fi-page { height: 100dvh; overflow: hidden; background: #000; }

        .app-container {
            height: 100dvh; width: 100%; position: fixed; top: 0; left: 0; background: #000;
            display: flex; flex-direction: column; z-index: 10;
        }

        /* Esconde o FileUpload original mas mantém funcional */
        .hidden-form-container { 
            position: absolute; opacity: 0; pointer-events: none; width: 1px; height: 1px; 
        }

        /* Estilo Scanner */
        #scanner-view { position: absolute; inset: 0; z-index: 20; background: #000; }
        #reader { width: 100%; height: 100%; object-fit: cover; }
        
        .scan-frame {
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
            width: 280px; height: 180px; pointer-events: none; z-index: 30;
            box-shadow: 0 0 0 9999px rgba(0,0,0,0.7); border-radius: 15px;
            border: 2px solid rgba(255,255,255,0.2);
        }
        .scan-line {
            position: absolute; width: 100%; height: 2px; background: #22c55e;
            box-shadow: 0 0 10px #22c55e; animation: scanning 2s infinite ease-in-out;
        }
        @keyframes scanning { 0% {top: 10%;} 50% {top: 90%;} 100% {top: 10%;} }

        /* Estilo Passo 2 (Foto) */
        #photo-view { 
            position: absolute; inset: 0; z-index: 40; background: #111; 
            display: flex; flex-direction: column;
        }
        .product-card {
            background: #1f2937; padding: 20px; border-bottom: 1px solid #374151;
        }
        .btn-capture {
            background: #22c55e; color: #000; padding: 20px; border-radius: 16px;
            font-weight: 800; display: flex; align-items: center; justify-content: center; gap: 10px;
            width: 100%; font-size: 1.1rem;
        }
        .btn-save {
            background: #22c55e; width: 70%; height: 60px; border-radius: 12px; color: #000; font-weight: bold;
        }
        .btn-cancel {
            background: #374151; width: 25%; height: 60px; border-radius: 12px; color: #fff;
        }
    </style>

    <div class="app-container">
        
        {{-- O formulário fica aqui, invisível, mas operável via JS --}}
        <div class="hidden-form-container">
            {{ $this->form }}
        </div>

        {{-- PASSO 1: SCANNER --}}
        <div id="scanner-view" class="{{ $foundProduct ? 'hidden' : '' }}">
            <div id="reader"></div>
            <div class="scan-frame">
                <div class="scan-line"></div>
            </div>
            <div class="absolute bottom-10 w-full text-center text-white font-medium px-4">
                Aproxime o código de barras da linha verde
            </div>
        </div>

        {{-- PASSO 2: FOTO E CONFIRMAÇÃO --}}
        @if($foundProduct)
            <div id="photo-view">
                <div class="product-card">
                    <span class="text-green-400 text-xs font-bold uppercase tracking-widest">Produto Encontrado</span>
                    <h2 class="text-white text-xl font-bold leading-tight">{{ $foundProduct->product_name }}</h2>
                    <p class="text-gray-400 font-mono text-sm mt-1">EAN: {{ $scannedCode }}</p>
                </div>

                <div class="flex-1 flex flex-col items-center justify-center p-6 gap-6 text-center">
                    <div class="w-24 h-24 bg-green-500/10 rounded-full flex items-center justify-center">
                        <x-heroicon-o-camera class="w-12 h-12 text-green-500" />
                    </div>
                    
                    <div>
                        <h3 class="text-white text-lg font-semibold">Tabela Nutricional</h3>
                        <p class="text-gray-400 text-sm">Toque no botão abaixo para capturar a foto da tabela no verso da embalagem.</p>
                    </div>

                    <button type="button" onclick="triggerFileUpload()" class="btn-capture shadow-xl active:scale-95 transition-transform">
                        <x-heroicon-s-camera class="w-6 h-6" />
                        CAPTURAR FOTO
                    </button>
                    
                    {{-- Preview da imagem carregada (se houver) --}}
                    <div id="upload-status" class="text-xs font-bold text-green-500 hidden">
                        ✓ IMAGEM PRONTA
                    </div>
                </div>

                <div class="p-4 bg-black/50 border-t border-white/10 flex justify-between items-center gap-2">
                    <button wire:click="resetScanner" class="btn-cancel">
                        VOLTAR
                    </button>
                    <button wire:click="save" wire:loading.attr="disabled" class="btn-save flex items-center justify-center gap-2">
                        <span wire:loading.remove wire:target="save">SALVAR E PRÓXIMO</span>
                        <span wire:loading wire:target="save">SALVANDO...</span>
                    </button>
                </div>
            </div>
        @endif
    </div>

    <script>
        document.addEventListener('livewire:initialized', () => {
            let html5QrCode = null;

            function startScanner() {
                if (@json($foundProduct)) return;
                
                html5QrCode = new Html5Qrcode("reader");
                const config = { fps: 10, qrbox: { width: 250, height: 150 }, aspectRatio: 1.77 };

                html5QrCode.start(
                    { facingMode: "environment" }, 
                    config,
                    (decodedText) => {
                        html5QrCode.stop().then(() => {
                            @this.handleBarcodeScan(decodedText);
                        });
                    }
                ).catch(err => console.error("Erro camera:", err));
            }

            // Função para clicar no input do Filament escondido
            window.triggerFileUpload = function() {
                const input = document.querySelector('.hidden-form-container input[type="file"]');
                if (input) {
                    input.click();
                    // Listener para dar um feedback visual se o arquivo foi selecionado
                    input.addEventListener('change', () => {
                        document.getElementById('upload-status').classList.remove('hidden');
                    }, { once: true });
                }
            };

            startScanner();

            Livewire.on('reset-scanner', () => {
                setTimeout(startScanner, 400);
            });

            Livewire.on('reset-scanner-error', () => {
                setTimeout(startScanner, 2000);
            });
        });
    </script>
</x-filament-panels::page>