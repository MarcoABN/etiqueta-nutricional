<x-filament-panels::page>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        
        /* === RESET ESTRUTURAL DO FILAMENT === */
        .fi-topbar, .fi-header, .fi-breadcrumbs, .fi-logo, .fi-sidebar, .fi-footer { display: none !important; }
        .fi-main-ctn, .fi-page { padding: 0 !important; margin: 0 !important; max-width: 100% !important; }
        .fi-page { height: 100dvh; overflow: hidden; background: #000; font-family: 'Inter', sans-serif; }

        /* === FILEPOND OCULTO === */
        .filepond--root {
            position: absolute !important; width: 1px !important; height: 1px !important;
            padding: 0 !important; margin: -1px !important; overflow: hidden !important;
            clip: rect(0,0,0,0) !important; white-space: nowrap !important; border: 0 !important;
            opacity: 0 !important; pointer-events: none;
        }

        :root { --primary: #22c55e; }

        .app-container {
            height: 100dvh; width: 100%; position: fixed; top: 0; left: 0; background: #000;
            display: flex; flex-direction: column;
        }

        /* === VIEW 1: SCANNER === */
        #scanner-view { position: absolute; inset: 0; z-index: 10; background: #000; }
        #reader { width: 100%; height: 100%; object-fit: cover; }
        #reader video { object-fit: cover; width: 100%; height: 100%; }

        /* Botão Trocar Câmera */
        .switch-camera-btn {
            position: absolute; top: 20px; right: 20px; z-index: 50;
            width: 48px; height: 48px; border-radius: 50%;
            background: rgba(0,0,0,0.6); border: 1px solid rgba(255,255,255,0.3);
            color: white; display: flex; align-items: center; justify-content: center;
            backdrop-filter: blur(4px); cursor: pointer;
        }
        .switch-camera-btn:active { transform: scale(0.95); background: rgba(255,255,255,0.2); }

        .scan-frame {
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
            width: min(75vw, 300px); height: 180px; pointer-events: none; z-index: 20;
            box-shadow: 0 0 0 9999px rgba(0,0,0,0.6); border-radius: 12px;
        }
        .scan-corner { position: absolute; width: 30px; height: 30px; border-color: var(--primary); }
        .scan-corner.tl { top: 0; left: 0; border-top: 4px solid; border-left: 4px solid; border-top-left-radius: 12px; }
        .scan-corner.tr { top: 0; right: 0; border-top: 4px solid; border-right: 4px solid; border-top-right-radius: 12px; }
        .scan-corner.bl { bottom: 0; left: 0; border-bottom: 4px solid; border-left: 4px solid; border-bottom-left-radius: 12px; }
        .scan-corner.br { bottom: 0; right: 0; border-bottom: 4px solid; border-right: 4px solid; border-bottom-right-radius: 12px; }
        
        .scan-line {
            position: absolute; width: 100%; height: 2px; background: var(--primary);
            box-shadow: 0 0 4px var(--primary); animation: scanning 2s infinite ease-in-out;
        }
        @keyframes scanning { 0% {top: 10%; opacity: 0;} 50% {opacity: 1;} 100% {top: 90%; opacity: 0;} }

        .scan-text {
            position: absolute; bottom: 15%; left: 0; right: 0; text-align: center;
            color: white; font-size: 14px; font-weight: 500; text-shadow: 0 2px 4px rgba(0,0,0,0.8); z-index: 21;
        }

        /* === VIEW 2: CONFIRMAÇÃO & FOTO === */
        #photo-view { 
            position: absolute; inset: 0; z-index: 20; background: #111; 
            display: flex; flex-direction: column;
        }

        .header-info {
            background: linear-gradient(180deg, rgba(0,0,0,0.9) 0%, rgba(0,0,0,0.6) 100%);
            padding: 24px 20px; border-bottom: 1px solid rgba(255,255,255,0.1);
            position: relative; z-index: 30;
        }
        .header-info h2 { color: white; font-size: 20px; font-weight: 700; margin-bottom: 4px; line-height: 1.2; }
        .header-info span { 
            display: inline-block; color: #9ca3af; font-size: 13px; font-family: monospace; 
            background: rgba(255,255,255,0.1); padding: 2px 6px; border-radius: 4px; margin-top: 4px;
        }

        .viewport-area {
            flex: 1; position: relative; display: flex; align-items: center; justify-content: center;
            background: #000; overflow: hidden; padding: 20px;
        }

        .confirm-stage {
            display: flex; flex-direction: column; align-items: center; gap: 20px; text-align: center;
            width: 100%;
        }
        .confirm-btn {
            background: var(--primary); color: #000; border: none;
            padding: 18px 32px; border-radius: 12px; font-size: 16px; font-weight: 700;
            display: flex; align-items: center; gap: 10px; width: 100%; max-width: 320px;
            justify-content: center; box-shadow: 0 4px 20px rgba(34, 197, 94, 0.3);
            transition: transform 0.1s; cursor: pointer;
        }
        .confirm-btn:active { transform: scale(0.96); }

        .preview-stage {
            width: 100%; height: 100%; display: flex;
            flex-direction: column; align-items: center; justify-content: center;
        }
        .preview-image { 
            width: 100%; height: 100%; object-fit: contain; border-radius: 8px; 
            box-shadow: 0 0 20px rgba(0,0,0,0.5);
        }

        .controls-bar {
            height: 100px; background: #000; display: flex; align-items: center; justify-content: space-between;
            padding: 0 30px; border-top: 1px solid rgba(255,255,255,0.1);
        }
        .icon-btn {
            width: 50px; height: 50px; border-radius: 50%; background: #222; color: white;
            display: flex; align-items: center; justify-content: center; border: 1px solid #333;
        }
        .save-btn {
            background: var(--primary); color: #000; width: 60px; height: 60px;
            box-shadow: 0 0 15px rgba(34, 197, 94, 0.4); border: none;
        }
        .save-btn:disabled { opacity: 0.5; filter: grayscale(1); }

        .hidden { display: none !important; }
    </style>

    <div class="app-container">

        {{-- FORMULÁRIO INVISÍVEL --}}
        <div style="position: absolute; opacity: 0; pointer-events: none; width: 0; height: 0; overflow: hidden">
            {{ $this->form }}
        </div>

        {{-- === VIEW 1: SCANNER === --}}
        <div id="scanner-view" class="{{ $foundProduct ? 'hidden' : '' }}">
            <div id="reader"></div>
            
            {{-- Botão Trocar Câmera (Invisível até o JS detectar câmeras) --}}
            <button id="btn-switch-cam" class="switch-camera-btn" style="display: none;">
                <x-heroicon-o-arrows-right-left class="w-6 h-6" />
            </button>

            <div class="scan-frame">
                <div class="scan-corner tl"></div><div class="scan-corner tr"></div>
                <div class="scan-corner bl"></div><div class="scan-corner br"></div>
                <div class="scan-line"></div>
            </div>
            <div class="scan-text">Aponte para o código de barras</div>
        </div>

        {{-- === VIEW 2: FLUXO DE CONFIRMAÇÃO E FOTO === --}}
        <div id="photo-view" 
             class="{{ $foundProduct ? '' : 'hidden' }}"
             x-data="{ 
                 mode: 'confirm',
                 init() {
                     // Se já existir imagem salva no produto, mostra preview
                     if (@js(!empty($foundProduct->image_nutritional))) {
                        this.mode = 'preview';
                     }
                 }
             }"
             @reset-scanner.window="mode = 'confirm'"
             @set-preview-mode.window="mode = 'preview'" {{-- Novo Listener Seguro --}}
        >
            <div class="header-info">
                <h2>{{ $foundProduct?->product_name ?? 'Produto Identificado' }}</h2>
                <span>EAN: {{ $scannedCode }}</span>
            </div>

            <div class="viewport-area" wire:ignore>
                {{-- ESTÁGIO 1: Confirmação --}}
                <div class="confirm-stage" x-show="mode === 'confirm'">
                    <p class="text-gray-400 text-sm">Confirme o produto para tirar a foto.</p>
                    <button id="btn-confirm-capture" class="confirm-btn">
                        <x-heroicon-o-camera class="w-6 h-6"/>
                        FOTOGRAFAR
                    </button>
                </div>

                {{-- ESTÁGIO 2: Preview --}}
                <div class="preview-stage" x-show="mode === 'preview'" style="display: none;">
                    <img id="local-preview" class="preview-image" alt="Preview">
                    <button id="btn-retake" class="text-gray-400 text-xs mt-4 underline p-2">Tirar outra foto</button>
                </div>
            </div>

            <div class="controls-bar">
                <button wire:click="resetScanner" class="icon-btn">
                    <x-heroicon-o-x-mark class="w-6 h-6"/>
                </button>

                <div x-show="mode === 'preview'" style="display: none;">
                    <button wire:click="save" wire:loading.attr="disabled" class="icon-btn save-btn">
                        <span wire:loading.remove><x-heroicon-m-check class="w-8 h-8"/></span>
                        <span wire:loading><x-heroicon-o-arrow-path class="w-6 h-6 animate-spin"/></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('livewire:initialized', () => {
            let html5QrCode = null;
            let currentCameraId = null;
            let availableCameras = [];
            let currentCameraIndex = 0;

            const btnConfirm = document.getElementById('btn-confirm-capture');
            const btnRetake = document.getElementById('btn-retake');
            const previewImg = document.getElementById('local-preview');
            const btnSwitchCam = document.getElementById('btn-switch-cam');

            // === LÓGICA DE UPLOAD (Correção Mobile) ===
            function openCamera() {
                // Seleciona o input no momento do clique (pois o Filament pode ter re-renderizado)
                const fileInput = document.querySelector('input[type="file"].filepond--browser');
                
                if (fileInput) {
                    // Remove listeners antigos para evitar duplicação
                    fileInput.onchange = null;
                    
                    // Adiciona novo listener
                    fileInput.onchange = (e) => {
                        if (e.target.files && e.target.files[0]) {
                            const file = e.target.files[0];
                            previewImg.src = URL.createObjectURL(file);
                            
                            // Dispara evento para o AlpineJS atualizar a tela
                            window.dispatchEvent(new CustomEvent('set-preview-mode'));
                        }
                    };
                    
                    // Abre câmera
                    fileInput.click();
                } else {
                    alert("Erro: Câmera não inicializada. Recarregue a página.");
                }
            }

            if(btnConfirm) btnConfirm.addEventListener('click', openCamera);
            if(btnRetake) btnRetake.addEventListener('click', openCamera);


            // === LÓGICA DO SCANNER E CÂMERAS ===
            async function startScanner() {
                if (@json($foundProduct)) return;

                // Se já estiver rodando, não faz nada
                if (html5QrCode && html5QrCode.isScanning) return;

                try {
                    // Pega as câmeras apenas uma vez
                    if (availableCameras.length === 0) {
                        availableCameras = await Html5Qrcode.getCameras();
                        
                        // Ordena para priorizar traseiras
                        availableCameras.sort((a, b) => {
                            const labelA = a.label.toLowerCase();
                            const isBackA = labelA.includes('back') || labelA.includes('traseira');
                            return isBackA ? -1 : 1;
                        });

                        // Se tiver mais de 1 câmera, mostra o botão de troca
                        if (availableCameras.length > 1) {
                            btnSwitchCam.style.display = 'flex';
                        }
                    }

                    if (availableCameras.length === 0) {
                        alert("Nenhuma câmera encontrada.");
                        return;
                    }

                    // Define câmera atual (ou a primeira da lista)
                    currentCameraId = availableCameras[currentCameraIndex].id;

                    html5QrCode = new Html5Qrcode("reader");
                    
                    await html5QrCode.start(
                        { deviceId: { exact: currentCameraId } }, 
                        { fps: 10, qrbox: { width: 250, height: 150 }, aspectRatio: 1.77 },
                        (decodedText) => {
                            stopScanner();
                            @this.handleBarcodeScan(decodedText);
                        },
                        () => {}
                    );

                } catch (err) {
                    console.error("Erro ao iniciar scanner:", err);
                }
            }

            function stopScanner() {
                if (html5QrCode) {
                    return html5QrCode.stop().then(() => {
                        html5QrCode.clear();
                    }).catch(err => console.log("Erro ao parar", err));
                }
                return Promise.resolve();
            }

            // Lógica de Troca de Câmera
            btnSwitchCam.addEventListener('click', async () => {
                if (availableCameras.length < 2) return;

                await stopScanner();

                // Avança índice circularmente
                currentCameraIndex = (currentCameraIndex + 1) % availableCameras.length;
                
                // Pequeno delay para garantir que o DOM do vídeo limpou
                setTimeout(startScanner, 200);
            });

            // Inicia
            startScanner();

            // Listeners Livewire
            Livewire.on('reset-scanner', () => {
                previewImg.src = '';
                // Reseta variáveis
                setTimeout(startScanner, 500);
            });
            
            Livewire.on('reset-scanner-error', () => setTimeout(startScanner, 1500));
        });
    </script>
</x-filament-panels::page>