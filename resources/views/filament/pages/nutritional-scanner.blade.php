<x-filament-panels::page>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    <style>
        /* UI Ultra Clean */
        .fi-topbar, .fi-header, .fi-sidebar, .fi-footer, .fi-breadcrumbs { display: none !important; }
        .fi-main-ctn, .fi-page { padding: 0 !important; margin: 0 !important; max-width: 100% !important; }
        
        .app-container { 
            height: 100dvh; 
            background: #000; 
            position: relative;
            overflow: hidden;
        }

        #scanner-view { position: absolute; inset: 0; z-index: 10; }
        #reader { width: 100%; height: 100%; object-fit: cover; }

        .product-overlay {
            position: absolute;
            bottom: 0; left: 0; width: 100%;
            background: #111827;
            border-radius: 24px 24px 0 0;
            padding: 20px;
            z-index: 100;
            box-shadow: 0 -10px 25px rgba(0,0,0,0.5);
        }

        .btn-action {
            width: 100%; padding: 16px; border-radius: 12px;
            font-weight: bold; font-size: 1rem;
            transition: all 0.2s;
        }
    </style>

    <div class="app-container">
        <div id="scanner-view" wire:ignore>
            <div id="reader"></div>
        </div>

        @if($foundProduct)
            <div class="product-overlay">
                <div class="text-center mb-4">
                    <p class="text-gray-400 text-xs uppercase">Produto</p>
                    <h3 class="text-white text-lg font-bold">{{ $foundProduct->product_name }}</h3>
                </div>
                
                <div class="mb-4 bg-gray-800 rounded-lg p-2">
                    {{ $this->form }}
                </div>

                <div class="space-y-3">
                    <button wire:click="processImage" wire:loading.attr="disabled" class="btn-action bg-success-600 text-white flex items-center justify-center gap-2">
                        <span wire:loading.remove>Confirmar e Enviar</span>
                        <span wire:loading class="animate-spin text-xl">⌛</span>
                    </button>

                    <button wire:click="resetScanner" class="btn-action bg-gray-700 text-white">
                        Cancelar
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
                const config = { fps: 10, qrbox: { width: 280, height: 150 } };

                html5QrCode.start(
                    { facingMode: "environment" }, 
                    config, 
                    (text) => {
                        html5QrCode.stop().then(() => @this.handleBarcodeScan(text));
                    }
                ).catch(err => console.error(err));
            }

            startScanner();
            Livewire.on('reset-scanner', () => setTimeout(startScanner, 400));
        });
    </script>
</x-filament-panels::page>