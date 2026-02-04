<x-filament-panels::page>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    <style>
        /* (MANTENHA SEUS ESTILOS CSS ORIGINAIS AQUI - omiti para economizar espaço) */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        .fi-topbar, .fi-header, .fi-breadcrumbs, .fi-logo, .fi-sidebar, .fi-footer { display: none !important; }
        .fi-main-ctn, .fi-page { padding: 0 !important; margin: 0 !important; max-width: 100% !important; }
        .fi-page { height: 100dvh; overflow: hidden; background: #000; font-family: 'Inter', sans-serif; }
        :root { --primary: #22c55e; }
        .app-container { height: 100dvh; width: 100%; position: fixed; top: 0; left: 0; background: #000; display: flex; flex-direction: column; }
        #scanner-view { position: absolute; inset: 0; z-index: 10; background: #000; }
        #reader { width: 100%; height: 100%; object-fit: cover; }
        .switch-camera-btn { position: absolute; top: 20px; right: 20px; z-index: 50; width: 48px; height: 48px; border-radius: 50%; background: rgba(0,0,0,0.6); border: 1px solid rgba(255,255,255,0.3); color: white; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(4px); cursor: pointer; }
        .scan-frame { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: min(75vw, 300px); height: 180px; pointer-events: none; z-index: 20; box-shadow: 0 0 0 9999px rgba(0,0,0,0.6); border-radius: 12px; }
        .scan-corner { position: absolute; width: 30px; height: 30px; border-color: var(--primary); }
        .scan-corner.tl { top: 0; left: 0; border-top: 4px solid; border-left: 4px solid; border-top-left-radius: 12px; }
        .scan-corner.tr { top: 0; right: 0; border-top: 4px solid; border-right: 4px solid; border-top-right-radius: 12px; }
        .scan-corner.bl { bottom: 0; left: 0; border-bottom: 4px solid; border-left: 4px solid; border-bottom-left-radius: 12px; }
        .scan-corner.br { bottom: 0; right: 0; border-bottom: 4px solid; border-right: 4px solid; border-bottom-right-radius: 12px; }
        .scan-line { position: absolute; width: 100%; height: 2px; background: var(--primary); box-shadow: 0 0 4px var(--primary); animation: scanning 2s infinite ease-in-out; }
        @keyframes scanning { 0% {top: 10%; opacity: 0;} 50% {opacity: 1;} 100% {top: 90%; opacity: 0;} }
        .scan-text { position: absolute; bottom: 15%; left: 0; right: 0; text-align: center; color: white; font-size: 14px; font-weight: 500; text-shadow: 0 2px 4px rgba(0,0,0,0.8); z-index: 21; }
        #photo-view { position: absolute; inset: 0; z-index: 20; background: #111; display: flex; flex-direction: column; }
        .header-info { background: linear-gradient(180deg, rgba(0,0,0,0.9) 0%, rgba(0,0,0,0.6) 100%); padding: 24px 20px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .header-info h2 { color: white; font-size: 20px; font-weight: 700; margin-bottom: 4px; line-height: 1.2; }
        .header-info span { display: inline-block; color: #9ca3af; font-size: 13px; font-family: monospace; background: rgba(255,255,255,0.1); padding: 2px 6px; border-radius: 4px; margin-top: 4px; }
        .viewport-area { flex: 1; position: relative; display: flex; align-items: center; justify-content: center; background: #000; overflow: hidden; padding: 20px; }
        .confirm-stage { display: flex; flex-direction: column; align-items: center; gap: 20px; text-align: center; width: 100%; }
        .confirm-btn { background: var(--primary); color: #000; border: none; padding: 18px 32px; border-radius: 12px; font-size: 16px; font-weight: 700; display: flex; align-items: center; gap: 10px; width: 100%; max-width: 320px; justify-content: center; box-shadow: 0 4px 20px rgba(34, 197, 94, 0.3); }
        .preview-stage { width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .preview-image { width: 100%; height: 100%; object-fit: contain; border-radius: 8px; box-shadow: 0 0 20px rgba(0,0,0,0.5); }
        .controls-bar { height: 100px; background: #000; display: flex; align-items: center; justify-content: space-between; padding: 0 30px; border-top: 1px solid rgba(255,255,255,0.1); }
        .icon-btn { width: 50px; height: 50px; border-radius: 50%; background: #222; color: white; display: flex; align-items: center; justify-content: center; border: 1px solid #333; }
        .save-btn { background: var(--primary); color: #000; width: 60px; height: 60px; box-shadow: 0 0 15px rgba(34, 197, 94, 0.4); border: none; transition: all 0.3s ease; }
        .save-btn:disabled { opacity: 0.5; filter: grayscale(1); cursor: not-allowed; }
        .hidden { display: none !important; }
    </style>

    <div class="app-container">
        
        {{-- INPUT NATIVO (O segredo: wire:model.live ativa o updatedPhoto no PHP) --}}
        <input type="file" 
               wire:model.live="photo" 
               accept="image/*" 
               capture="environment" 
               id="native-camera-input" 
               style="display: none;"
        >

        {{-- SCANNER --}}
        <div id="scanner-view" class="{{ $foundProduct ? 'hidden' : '' }}" wire:ignore>
            <div id="reader"></div>
            <button id="btn-switch-cam" class="switch-camera-btn" style="display: none;">
                <x-heroicon-o-arrows-right-left class="w-6 h-6" />
            </button>
            <div class="scan-frame"><div class="scan-corner tl"></div><div class="scan-corner tr"></div><div class="scan-corner bl"></div><div class="scan-corner br"></div><div class="scan-line"></div></div>
            <div class="scan-text">Aponte para o código de barras</div>
        </div>

        {{-- FOTO / PREVIEW --}}
        <div id="photo-view" 
             class="{{ $foundProduct ? '' : 'hidden' }}"
             x-data="{ 
                 // CORREÇÃO CRÍTICA: Vincula o estado JS ao PHP. 
                 // Quando o PHP mudar para 'preview' após o upload, o JS obedece.
                 mode: @entangle('viewMode')
             }"
        >
            <div class="header-info">
                <h2>{{ $foundProduct?->product_name ?? 'Produto Identificado' }}</h2>
                <span>EAN: {{ $scannedCode }}</span>
            </div>

            <div class="viewport-area">
                {{-- MODO 1: CONFIRMAÇÃO (Botão de Câmera) --}}
                <div class="confirm-stage" x-show="mode === 'confirm'">
                    <p class="text-gray-400 text-sm">Confirme o produto para tirar a foto.</p>
                    {{-- Ao clicar, apenas abre o input nativo. O resto é com o Livewire --}}
                    <button type="button" onclick="document.getElementById('native-camera-input').click()" class="confirm-btn">
                        <x-heroicon-o-camera class="w-6 h-6"/>
                        FOTOGRAFAR
                    </button>
                    
                    {{-- Feedback de carregamento enquanto o celular processa a foto --}}
                    <div wire:loading wire:target="photo" class="text-green-500 text-sm mt-4 animate-pulse">
                        Processando imagem...
                    </div>
                </div>

                {{-- MODO 2: PREVIEW (Mostra a foto) --}}
                <div class="preview-stage" x-show="mode === 'preview'" style="display: none;">
                    @if ($photo)
                        {{-- Preview da imagem temporária (recém tirada) --}}
                        <img src="{{ $photo->temporaryUrl() }}" class="preview-image" alt="New Preview">
                    @elseif ($foundProduct?->image_nutritional)
                        {{-- Imagem já salva no banco --}}
                        <img src="{{ asset('storage/' . $foundProduct->image_nutritional) }}" class="preview-image" alt="Saved Preview">
                    @endif
                    
                    <button type="button" onclick="document.getElementById('native-camera-input').click()" class="text-gray-400 text-xs mt-4 underline p-2">
                        Tirar outra foto
                    </button>
                </div>
            </div>

            <div class="controls-bar">
                <button wire:click="resetScanner" class="icon-btn">
                    <x-heroicon-o-x-mark class="w-6 h-6"/>
                </button>

                <div x-show="mode === 'preview'" style="display: none;">
                    <button wire:click="save" 
                            wire:loading.attr="disabled"
                            wire:target="photo, save"
                            class="icon-btn save-btn">
                        
                        {{-- Ícone Normal --}}
                        <span wire:loading.remove wire:target="photo, save">
                            <x-heroicon-m-check class="w-8 h-8"/>
                        </span>
                        
                        {{-- Ícone Uploading (caso a net esteja lenta) --}}
                        <span wire:loading wire:target="photo">
                            <x-heroicon-o-arrow-up-tray class="w-6 h-6 animate-bounce"/>
                        </span>

                        {{-- Ícone Salvando --}}
                        <span wire:loading wire:target="save">
                            <x-heroicon-o-arrow-path class="w-6 h-6 animate-spin"/>
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- SCRIPTS DO SCANNER --}}
    <script>
        document.addEventListener('livewire:initialized', () => {
            let html5QrCode = null;
            let availableCameras = [];
            let currentCameraIndex = 0;
            const btnSwitchCam = document.getElementById('btn-switch-cam');

            async function startScanner() {
                // Não inicia scanner se o produto já foi encontrado
                if (@json($foundProduct)) return;
                
                if (html5QrCode && html5QrCode.isScanning) return;

                try {
                    if (availableCameras.length === 0) {
                        availableCameras = await Html5Qrcode.getCameras();
                        availableCameras.sort((a, b) => {
                            const labelA = a.label.toLowerCase();
                            const isBack = labelA.includes('back') || labelA.includes('traseira') || labelA.includes('environment');
                            return isBack ? -1 : 1;
                        });
                        if (availableCameras.length > 1) btnSwitchCam.style.display = 'flex';
                    }

                    if (availableCameras.length > 0) {
                        html5QrCode = new Html5Qrcode("reader");
                        await html5QrCode.start(
                            { deviceId: { exact: availableCameras[currentCameraIndex].id } }, 
                            { fps: 10, qrbox: { width: 250, height: 150 }, aspectRatio: 1.77 },
                            (decodedText) => {
                                stopScanner();
                                @this.handleBarcodeScan(decodedText);
                            },
                            () => {}
                        );
                    }
                } catch (err) { console.error("Erro Cam:", err); }
            }

            function stopScanner() {
                if (html5QrCode) return html5QrCode.stop().then(() => html5QrCode.clear()).catch(()=>{});
                return Promise.resolve();
            }

            btnSwitchCam.addEventListener('click', async () => {
                if (availableCameras.length < 2) return;
                await stopScanner();
                currentCameraIndex = (currentCameraIndex + 1) % availableCameras.length;
                setTimeout(startScanner, 200);
            });

            startScanner();

            Livewire.on('reset-scanner', () => {
                setTimeout(startScanner, 500);
            });
            
            Livewire.on('reset-scanner-error', () => setTimeout(startScanner, 1500));
        });
    </script>
</x-filament-panels::page>