<x-filament-panels::page>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        .fi-topbar, .fi-header, .fi-breadcrumbs, .fi-logo, .fi-sidebar, .fi-footer { display: none !important; }
        .fi-main-ctn, .fi-page { padding: 0 !important; margin: 0 !important; max-width: 100% !important; }
        .fi-page { height: 100dvh; overflow: hidden; background: #000; font-family: 'Inter', sans-serif; }
        :root { --primary: #22c55e; --danger: #ef4444; }
        .app-container { height: 100dvh; width: 100%; position: fixed; top: 0; left: 0; background: #000; display: flex; flex-direction: column; }
        .hidden { display: none !important; }
        
        /* Scanner */
        #scanner-view { position: absolute; inset: 0; z-index: 10; background: #000; }
        #reader { width: 100%; height: 100%; object-fit: cover; }
        .scan-frame { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: min(75vw, 300px); height: 180px; pointer-events: none; z-index: 20; box-shadow: 0 0 0 9999px rgba(0,0,0,0.6); border-radius: 12px; }
        .scan-line { position: absolute; width: 100%; height: 2px; background: var(--primary); box-shadow: 0 0 4px var(--primary); animation: scanning 2s infinite ease-in-out; }
        @keyframes scanning { 0% {top: 10%; opacity: 0;} 50% {opacity: 1;} 100% {top: 90%; opacity: 0;} }

        /* UI Camadas */
        .full-screen-layer { position: absolute; inset: 0; z-index: 20; background: #111; display: flex; flex-direction: column; }
        .viewport-area { flex: 1; position: relative; display: flex; align-items: center; justify-content: center; background: #000; overflow: hidden; padding: 20px; }
        
        /* Botões */
        .confirm-btn { background: var(--primary); color: #000; border: none; padding: 18px 32px; border-radius: 12px; font-size: 16px; font-weight: 700; display: flex; align-items: center; gap: 10px; width: 100%; max-width: 320px; justify-content: center; box-shadow: 0 4px 20px rgba(34, 197, 94, 0.3); }
        .icon-btn { width: 50px; height: 50px; border-radius: 50%; background: #222; color: white; display: flex; align-items: center; justify-content: center; border: 1px solid #333; }
        .save-btn { background: var(--primary); color: #000; width: 60px; height: 60px; box-shadow: 0 0 15px rgba(34, 197, 94, 0.4); border: none; }
        .controls-bar { height: 100px; background: #000; display: flex; align-items: center; justify-content: space-between; padding: 0 30px; border-top: 1px solid rgba(255,255,255,0.1); }
        .preview-image { width: 100%; height: 100%; object-fit: contain; border-radius: 8px; }

        /* Erro */
        .error-box { background: rgba(239, 68, 68, 0.1); border: 1px solid var(--danger); color: var(--danger); padding: 10px; border-radius: 8px; margin-top: 15px; text-align: center; font-size: 14px; }
    </style>

    <div class="app-container">

        {{-- INPUT OCULTO --}}
        <input type="file" 
               wire:model.live="photo" 
               accept="image/*" 
               capture="environment" 
               id="native-camera-input" 
               style="display: none;"
        >

        {{-- SCANNER --}}
        <div id="scanner-view" class="{{ $step === 'scan' ? '' : 'hidden' }}" wire:ignore>
            <div id="reader"></div>
            <div class="scan-frame"><div class="scan-line"></div></div>
            <div style="position: absolute; bottom: 15%; width: 100%; text-align: center; color: white;">Aponte para o código de barras</div>
        </div>

        {{-- UI DE PRODUTO --}}
        @if($step !== 'scan' && $foundProduct)
        <div class="full-screen-layer">
            
            <div style="background: rgba(0,0,0,0.8); padding: 20px; border-bottom: 1px solid #333;">
                <h2 style="color: white; font-weight: bold; font-size: 1.2rem;">{{ $foundProduct->product_name }}</h2>
                <span style="color: #999; font-family: monospace;">EAN: {{ $scannedCode }}</span>
            </div>

            <div class="viewport-area">
                
                {{-- ETAPA 1: CONFIRMAR / FOTOGRAFAR --}}
                @if($step === 'confirm')
                    <div style="width: 100%; display: flex; flex-direction: column; align-items: center;">
                        
                        {{-- O Botão: Só aparece se NÃO estiver carregando --}}
                        <div wire:loading.remove wire:target="photo">
                            <button type="button" onclick="document.getElementById('native-camera-input').click()" class="confirm-btn">
                                <x-heroicon-o-camera class="w-6 h-6"/>
                                FOTOGRAFAR
                            </button>
                        </div>

                        {{-- O Loader: Substitui o botão EXATAMENTE no mesmo lugar --}}
                        <div wire:loading.flex wire:target="photo" style="flex-direction: column; align-items: center; color: var(--primary);">
                            <x-heroicon-o-arrow-path class="w-12 h-12 animate-spin"/>
                            <p class="mt-4 font-bold text-lg">Processando...</p>
                            <p class="text-sm text-gray-500">Enviando imagem para o servidor</p>
                        </div>

                        {{-- EXIBIÇÃO DE ERRO DE UPLOAD --}}
                        @error('photo')
                            <div class="error-box">
                                <strong>Erro na Imagem:</strong><br>
                                {{ $message }}
                                <br><br>
                                <small>Tente tirar a foto novamente.</small>
                            </div>
                        @enderror

                    </div>
                @endif

                {{-- ETAPA 2: PREVIEW --}}
                @if($step === 'preview')
                    <div style="width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center;">
                        @if ($photo)
                            <img src="{{ $photo->temporaryUrl() }}" class="preview-image">
                        @elseif ($foundProduct->image_nutritional)
                            <img src="{{ asset('storage/' . $foundProduct->image_nutritional) }}" class="preview-image">
                        @endif
                        
                        <button type="button" onclick="document.getElementById('native-camera-input').click()" class="text-gray-400 text-xs mt-4 underline p-2">
                            Tirar outra foto
                        </button>
                    </div>
                @endif
            </div>

            <div class="controls-bar">
                <button wire:click="resetScanner" class="icon-btn">
                    <x-heroicon-o-x-mark class="w-6 h-6"/>
                </button>

                @if($step === 'preview')
                    <button wire:click="save" wire:loading.attr="disabled" class="icon-btn save-btn">
                        <span wire:loading.remove><x-heroicon-m-check class="w-8 h-8"/></span>
                        <span wire:loading><x-heroicon-o-arrow-path class="w-6 h-6 animate-spin"/></span>
                    </button>
                @endif
            </div>
        </div>
        @endif
    </div>

    {{-- Script Scanner (Mantido) --}}
    <script>
        document.addEventListener('livewire:initialized', () => {
            let html5QrCode = null;
            async function startScanner() {
                if (@json($step !== 'scan')) return;
                try {
                    const cameras = await Html5Qrcode.getCameras();
                    if (cameras && cameras.length) {
                        const backCam = cameras.find(c => c.label.toLowerCase().includes('back') || c.label.toLowerCase().includes('environment')) || cameras[0];
                        html5QrCode = new Html5Qrcode("reader");
                        await html5QrCode.start(backCam.id, { fps: 10, qrbox: { width: 250, height: 150 } }, 
                            (decodedText) => { html5QrCode.stop().then(() => { @this.handleBarcodeScan(decodedText); }); }, 
                            () => {}
                        );
                    }
                } catch (e) { console.error(e); }
            }
            startScanner();
            Livewire.on('reset-scanner', () => setTimeout(startScanner, 500));
            Livewire.on('reset-scanner-error', () => setTimeout(startScanner, 1500));
        });
    </script>
</x-filament-panels::page>