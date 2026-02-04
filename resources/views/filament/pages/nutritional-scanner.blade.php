<x-filament-panels::page>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    <style>
        /* UI Modo Aplicativo - Sem bordas ou menus */
        .fi-topbar, .fi-header, .fi-sidebar, .fi-footer, .fi-breadcrumbs { display: none !important; }
        .fi-main-ctn, .fi-page { padding: 0 !important; margin: 0 !important; max-width: 100% !important; }
        
        .app-container { 
            height: 100dvh; 
            background: #000; 
            position: relative;
            overflow: hidden;
        }

        #scanner-view { position: absolute; inset: 0; z-index: 10; }
        #reader { width: 100%; height: 100%; }
        #reader video { object-fit: cover !important; }

        .product-overlay {
            position: absolute;
            bottom: 0; left: 0; width: 100%;
            background: #111827;
            border-radius: 24px 24px 0 0;
            padding: 24px;
            z-index: 100;
            box-shadow: 0 -10px 30px rgba(0,0,0,0.8);
        }

        .btn-confirm {
            width: 100%; background: #22c55e; color: white; padding: 16px; 
            border-radius: 12px; font-weight: 800; font-size: 1.1rem;
            margin-bottom: 12px;
        }
        .btn-cancel {
            width: 100%; background: #374151; color: white; padding: 12px; 
            border-radius: 12px; font-weight: 600;
        }
    </style>

    <div class="app-container">
        <div id="scanner-view" wire:ignore>
            <div id="reader"></div>
        </div>

        @if($foundProduct)
            <div class="product-overlay">
                <div class="text-center mb-5">
                    <p class="text-gray-400 text-xs uppercase tracking-widest">Produto Identificado</p>
                    <h3 class="text-white text-xl font-bold">{{ $foundProduct->product_name }}</h3>
                </div>
                
                <div class="mb-5 bg-gray-800 rounded-xl p-2 border border-gray-700">
                    {{ $this->form }}
                </div>

                <div class="flex flex-col">
                    <button wire:click="processImage" wire:loading.attr="disabled" class="btn-confirm">
                        <span wire:loading.remove>CONFIRMAR E ENVIAR</span>
                        <span wire:loading>ENVIANDO...</span>
                    </button>

                    <button wire:click="resetScanner" class="btn-cancel">
                        CANCELAR
                    </button>
                </div>
            </div>
        @endif
    </div>

    <script>
        document.addEventListener('livewire:initialized', () => {
            let html5QrCode = null;

            async function startScanner() {
                if (@json($foundProduct)) return;
                
                html5QrCode = new Html5Qrcode("reader");
                
                // Configuração explícita para aceitar códigos de barras de produtos
                const config = { 
                    fps: 20, 
                    qrbox: { width: 280, height: 160 },
                    aspectRatio: 1.0 
                };

                html5QrCode.start(
                    { facingMode: "environment" }, 
                    config, 
                    (text) => {
                        html5QrCode.stop().then(() => {
                            @this.handleBarcodeScan(text);
                        });
                    }
                ).catch(err => console.error("Erro ao iniciar câmera:", err));
            }

            startScanner();

            Livewire.on('reset-scanner', () => {
                setTimeout(startScanner, 500);
            });
        });
    </script>
</x-filament-panels::page>